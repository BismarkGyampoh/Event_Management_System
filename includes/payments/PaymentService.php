<?php

require_once __DIR__ . '/Money.php';
require_once __DIR__ . '/PaystackGateway.php';

/** Payment orchestration. Provider calls and persisted financial state stay server-side. */
final class PaymentService
{
    private PDO $db;
    private PaymentGatewayInterface $gateway;

    public function __construct(PDO $db, ?PaymentGatewayInterface $gateway = null)
    {
        $this->db = $db;
        $this->gateway = $gateway ?: new PaystackGateway();
    }

    public function startCheckout(int $attendeeId, int $eventId, int $ticketTypeId, int $quantity, string $email, string $callbackUrl): array
    {
        if ($quantity < 1 || $quantity > 10 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Choose between 1 and 10 tickets and provide a valid account email.');
        }
        $settings = $this->db->query('SELECT commission_bps, gateway_fee_payer, payments_enabled FROM PlatformPaymentSettings_007 WHERE settings_id = 1')->fetch();
        $enabled = $settings && ($settings['payments_enabled'] === true || in_array(strtolower((string)$settings['payments_enabled']), ['1', 't', 'true'], true));
        if (!$settings || !$enabled || $settings['commission_bps'] === null || $settings['gateway_fee_payer'] !== 'organizer') {
            throw new RuntimeException('Online checkout is not enabled by the platform administrator.');
        }

        $this->db->beginTransaction();
        try {
            // Locking the event serializes inventory reservations across ticket types and prevents overselling.
            $eventStmt = $this->db->prepare("SELECT event_id, organizer_id, event_name, event_date, event_status, max_capacity FROM Events_007 WHERE event_id = ? FOR UPDATE");
            $eventStmt->execute([$eventId]);
            $event = $eventStmt->fetch();
            if (!$event || $event['event_status'] !== 'published' || strtotime($event['event_date']) <= time()) {
                throw new RuntimeException('This event is not currently available for ticket sales.');
            }
            $this->db->prepare("UPDATE Tickets_007 SET ticket_status = 'expired' WHERE event_id = ? AND ticket_status = 'booked' AND expires_at IS NOT NULL AND expires_at <= CURRENT_TIMESTAMP")->execute([$eventId]);
            $this->db->prepare("UPDATE Payments_007 p SET payment_status = 'failed' FROM Tickets_007 t WHERE p.ticket_id = t.ticket_id AND t.event_id = ? AND t.ticket_status = 'expired' AND p.payment_status = 'pending'")->execute([$eventId]);
            $this->db->prepare("UPDATE BookingOrders_007 SET status = 'expired', updated_at = CURRENT_TIMESTAMP WHERE event_id = ? AND status = 'pending' AND expires_at <= CURRENT_TIMESTAMP")->execute([$eventId]);
            $openOrder = $this->db->prepare("SELECT 1 FROM BookingOrders_007 WHERE event_id = ? AND attendee_id = ? AND status = 'pending' AND expires_at > CURRENT_TIMESTAMP LIMIT 1");
            $openOrder->execute([$eventId, $attendeeId]);
            if ($openOrder->fetchColumn()) throw new RuntimeException('You already have an active checkout for this event. Finish it or wait for its reservation to expire.');
            $typeStmt = $this->db->prepare('SELECT * FROM TicketTypes_007 WHERE ticket_type_id = ? AND event_id = ? AND is_active = TRUE FOR UPDATE');
            $typeStmt->execute([$ticketTypeId, $eventId]);
            $type = $typeStmt->fetch();
            if (!$type || ($type['sales_start'] && strtotime($type['sales_start']) > time()) || ($type['sales_end'] && strtotime($type['sales_end']) <= time())) {
                throw new RuntimeException('This ticket type is not currently on sale.');
            }
            $usedStmt = $this->db->prepare("SELECT
                (SELECT COUNT(*) FROM Tickets_007 t WHERE t.ticket_type_id = ? AND t.ticket_status NOT IN ('cancelled','expired')) +
                COALESCE((SELECT SUM(bi.quantity) FROM BookingItems_007 bi JOIN BookingOrders_007 b ON b.booking_id = bi.booking_id
                    WHERE bi.ticket_type_id = ? AND b.status = 'pending' AND b.expires_at > CURRENT_TIMESTAMP), 0) AS reserved");
            $usedStmt->execute([$ticketTypeId, $ticketTypeId]);
            $reserved = (int)$usedStmt->fetchColumn();
            if ($reserved + $quantity > (int)$type['quantity']) throw new RuntimeException('Not enough tickets remain in this category.');
            $eventReservedStmt = $this->db->prepare("SELECT
                (SELECT COUNT(*) FROM Tickets_007 t WHERE t.event_id = ? AND t.ticket_status NOT IN ('cancelled','expired')) +
                COALESCE((SELECT SUM(bi.quantity) FROM BookingItems_007 bi JOIN BookingOrders_007 b ON b.booking_id = bi.booking_id
                    WHERE b.event_id = ? AND b.status = 'pending' AND b.expires_at > CURRENT_TIMESTAMP), 0) AS reserved");
            $eventReservedStmt->execute([$eventId, $eventId]);
            if ((int)$eventReservedStmt->fetchColumn() + $quantity > (int)$event['max_capacity']) {
                throw new RuntimeException('Not enough event capacity remains.');
            }

            $accountStmt = $this->db->prepare("SELECT subaccount_code FROM OrganizerPaymentAccounts_007 WHERE organizer_id = ? AND verification_status = 'verified' AND provider_verified = TRUE");
            $accountStmt->execute([$event['organizer_id']]);
            $subaccount = $accountStmt->fetchColumn();
            if (!$subaccount) throw new RuntimeException('The organiser payment account is not yet verified and active.');

            $gross = (int)$type['price_minor'] * $quantity;
            if ($gross < 0 || $gross > 9000000000000) throw new RuntimeException('The ticket total is outside the supported amount range.');
            if ($gross === 0) throw new RuntimeException('Free tickets do not require online payment. Use the free reservation option.');
            $commissionBps = (int)$settings['commission_bps'];
            $commission = Money::commission($gross, $commissionBps);
            $reference = 'evt_' . bin2hex(random_bytes(18));
            $idempotency = bin2hex(random_bytes(32));
            $insert = $this->db->prepare("INSERT INTO BookingOrders_007
                (booking_reference, attendee_id, event_id, organizer_id, gross_minor, commission_bps, commission_minor, expires_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP + INTERVAL '15 minutes') RETURNING booking_id");
            $insert->execute([$reference, $attendeeId, $eventId, $event['organizer_id'], $gross, $commissionBps, $commission]);
            $bookingId = (int)$insert->fetchColumn();
            $line = $this->db->prepare('INSERT INTO BookingItems_007 (booking_id, ticket_type_id, type_name_snapshot, unit_price_minor, quantity, line_total_minor) VALUES (?, ?, ?, ?, ?, ?)');
            $line->execute([$bookingId, $ticketTypeId, $type['type_name'], $type['price_minor'], $quantity, $gross]);
            $pay = $this->db->prepare("INSERT INTO MarketplacePayments_007 (booking_id, provider, transaction_reference, idempotency_key, amount_minor, currency) VALUES (?, 'paystack', ?, ?, ?, 'GHS')");
            $pay->execute([$bookingId, $reference, $idempotency, $gross]);
            $this->db->commit();

            try {
                $initialized = $this->gateway->initialize([
                    'email' => $email,
                    'amount' => (string)$gross,
                    'currency' => 'GHS',
                    'reference' => $reference,
                    'callback_url' => $callbackUrl,
                    'subaccount' => $subaccount,
                    'transaction_charge' => $commission,
                    'bearer' => 'subaccount',
                    'channels' => ['card', 'mobile_money', 'bank_transfer', 'ussd'],
                    'metadata' => ['booking_reference' => $reference],
                ]);
                $update = $this->db->prepare("UPDATE MarketplacePayments_007 SET status = 'pending' WHERE transaction_reference = ? AND status = 'initializing'");
                $update->execute([$reference]);
                $checkoutHost = strtolower((string)parse_url((string)($initialized['authorization_url'] ?? ''), PHP_URL_HOST));
                if (($initialized['reference'] ?? '') !== $reference || empty($initialized['authorization_url'])
                    || !filter_var($initialized['authorization_url'], FILTER_VALIDATE_URL)
                    || parse_url($initialized['authorization_url'], PHP_URL_SCHEME) !== 'https'
                    || !($checkoutHost === 'paystack.com' || str_ends_with($checkoutHost, '.paystack.com'))) {
                    throw new RuntimeException('Payment provider returned an invalid checkout URL.');
                }
                return ['authorization_url' => $initialized['authorization_url'], 'reference' => $reference];
            } catch (Throwable $e) {
                $stmt = $this->db->prepare("UPDATE MarketplacePayments_007 SET status = 'failed' WHERE transaction_reference = ? AND status IN ('initializing','pending')");
                $stmt->execute([$reference]);
                $stmt = $this->db->prepare("UPDATE BookingOrders_007 SET status = 'expired', updated_at = CURRENT_TIMESTAMP WHERE booking_id = ? AND status = 'pending'");
                $stmt->execute([$bookingId]);
                throw $e;
            }
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    /** Verify with Paystack and atomically confirm a booking. Safe for retries and duplicate webhooks. */
    public function verifyAndConfirm(string $reference): array
    {
        if (!preg_match('/\Aevt_[a-f0-9]{36}\z/', $reference)) throw new InvalidArgumentException('Invalid payment reference.');
        $verified = $this->gateway->verify($reference);
        $stmt = $this->db->prepare("SELECT p.*, b.booking_id, b.booking_reference, b.attendee_id, b.event_id, b.organizer_id, b.status AS booking_status,
            b.gross_minor, b.commission_minor, b.expires_at, a.email, opa.subaccount_code
            FROM MarketplacePayments_007 p JOIN BookingOrders_007 b ON b.booking_id = p.booking_id
            JOIN Attendees_007 a ON a.attendee_id = b.attendee_id
            JOIN OrganizerPaymentAccounts_007 opa ON opa.organizer_id = b.organizer_id
            WHERE p.transaction_reference = ?");
        $stmt->execute([$reference]);
        $row = $stmt->fetch();
        if (!$row) throw new RuntimeException('Payment reference was not found.');
        if (($verified['reference'] ?? '') !== $reference || strtoupper((string)($verified['currency'] ?? '')) !== 'GHS'
            || (int)($verified['amount'] ?? -1) !== (int)$row['amount_minor']) {
            throw new RuntimeException('Verified payment does not match the booking amount or currency.');
        }
        $providerEmail = strtolower((string)($verified['customer']['email'] ?? ''));
        if ($providerEmail !== '' && !hash_equals(strtolower((string)$row['email']), $providerEmail)) {
            throw new RuntimeException('Verified payment customer does not match this attendee.');
        }
        if (isset($verified['transaction_charge']) && (int)$verified['transaction_charge'] !== (int)$row['commission_minor']) {
            throw new RuntimeException('Verified payment commission does not match the booking snapshot.');
        }
        if (($verified['status'] ?? '') !== 'success') return ['status' => (string)($verified['status'] ?? 'pending'), 'booking_reference' => $row['booking_reference']];
        $verifiedSubaccount = $verified['subaccount']['subaccount_code'] ?? ($verified['subaccount'] ?? null);
        if (!is_string($verifiedSubaccount) || !hash_equals((string)$row['subaccount_code'], $verifiedSubaccount)) {
            throw new RuntimeException('Verified payment recipient does not match this organiser.');
        }
        $fee = filter_var($verified['fees'] ?? null, FILTER_VALIDATE_INT);
        if ($fee === false || $fee < 0) throw new RuntimeException('Payment provider did not return a valid processing fee.');

        $this->db->beginTransaction();
        try {
            $lock = $this->db->prepare('SELECT * FROM BookingOrders_007 WHERE booking_id = ? FOR UPDATE');
            $lock->execute([$row['booking_id']]);
            $booking = $lock->fetch();
            $paymentLock = $this->db->prepare('SELECT * FROM MarketplacePayments_007 WHERE marketplace_payment_id = ? FOR UPDATE');
            $paymentLock->execute([$row['marketplace_payment_id']]);
            $payment = $paymentLock->fetch();
            if ($payment['status'] === 'success') {
                if ($booking['status'] === 'paid') $this->queueBookingConfirmation((int)$booking['booking_id']);
                $this->db->commit();
                return ['status' => $booking['status'] === 'refund_pending' ? 'refund_pending' : 'success', 'booking_reference' => $booking['booking_reference']];
            }
            $providerId = (string)($verified['id'] ?? '');
            if ($providerId === '') throw new RuntimeException('Payment provider returned no transaction identifier.');
            if ($booking['status'] !== 'pending' || strtotime($booking['expires_at']) <= time()) {
                $p = $this->db->prepare("UPDATE MarketplacePayments_007 SET status = 'success', provider_transaction_id = ?, provider_fee_minor = ?, provider_status = 'success', verified_at = CURRENT_TIMESTAMP WHERE marketplace_payment_id = ?");
                $p->execute([$providerId, $fee, $payment['marketplace_payment_id']]);
                $b = $this->db->prepare("UPDATE BookingOrders_007 SET status = 'refund_pending', gateway_fee_minor = ?, organizer_net_minor = GREATEST(0, gross_minor - commission_minor - ?), updated_at = CURRENT_TIMESTAMP WHERE booking_id = ?");
                $b->execute([$fee, $fee, $booking['booking_id']]);
                $refund = $this->db->prepare("INSERT INTO PaymentRefunds_007 (marketplace_payment_id, requested_by, amount_minor, reason)
                    VALUES (?, ?, ?, 'Payment completed after the ticket reservation expired; administrator review required')
                    ON CONFLICT (marketplace_payment_id) WHERE status IN ('requested','processing','needs_attention') DO NOTHING");
                $refund->execute([$payment['marketplace_payment_id'], $booking['attendee_id'], $booking['gross_minor']]);
                $this->db->commit();
                return ['status' => 'refund_pending', 'booking_reference' => $booking['booking_reference']];
            }
            if ((int)$fee + (int)$booking['commission_minor'] > (int)$booking['gross_minor']) {
                throw new RuntimeException('Payment fees and commission exceed the booking total; manual reconciliation is required.');
            }
            $itemStmt = $this->db->prepare('SELECT * FROM BookingItems_007 WHERE booking_id = ?');
            $itemStmt->execute([$booking['booking_id']]);
            foreach ($itemStmt->fetchAll() as $item) {
                for ($i = 0; $i < (int)$item['quantity']; $i++) {
                    $ticket = $this->db->prepare("INSERT INTO Tickets_007 (event_id, attendee_id, ticket_number, ticket_status, booking_id, ticket_type_id)
                        VALUES (?, ?, ?, 'confirmed', ?, ?)");
                    $ticket->execute([$booking['event_id'], $booking['attendee_id'], strtoupper(bin2hex(random_bytes(10))), $booking['booking_id'], $item['ticket_type_id']]);
                }
            }
            $payUpdate = $this->db->prepare("UPDATE MarketplacePayments_007 SET status = 'success', provider_transaction_id = ?, provider_fee_minor = ?, provider_status = 'success', verified_at = CURRENT_TIMESTAMP WHERE marketplace_payment_id = ?");
            $payUpdate->execute([$providerId, $fee, $payment['marketplace_payment_id']]);
            $bookUpdate = $this->db->prepare("UPDATE BookingOrders_007 SET status = 'paid', gateway_fee_minor = ?, organizer_net_minor = gross_minor - commission_minor - ?, paid_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE booking_id = ?");
            $bookUpdate->execute([$fee, $fee, $booking['booking_id']]);
            $this->queueBookingConfirmation((int)$booking['booking_id']);
            $this->db->commit();
            return ['status' => 'success', 'booking_reference' => $booking['booking_reference']];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    private function queueBookingConfirmation(int $bookingId): void
    {
        $stmt = $this->db->prepare("INSERT INTO NotificationOutbox_007 (event_type, booking_id, idempotency_key)
            VALUES ('booking_confirmed', ?, ?) ON CONFLICT (idempotency_key) DO NOTHING");
        $stmt->execute([$bookingId, 'booking-confirmed-' . $bookingId]);
    }
}
