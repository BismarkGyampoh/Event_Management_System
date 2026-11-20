<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/payments/PaymentService.php';
require_once __DIR__ . '/../includes/payments/PaystackWebhookVerifier.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}
$length = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($length < 1 || $length > 1048576) {
    http_response_code(413);
    exit;
}
$raw = file_get_contents('php://input', false, null, 0, 1048577);
$apiSecret = getenv('PAYSTACK_TEST_SECRET_KEY') ?: '';
$webhookSecret = getenv('PAYSTACK_TEST_WEBHOOK_SECRET') ?: '';
if (getenv('PAYSTACK_MODE') !== 'test' || !is_string($raw) || strlen($raw) > 1048576
    || !PaystackWebhookVerifier::verify($raw, $_SERVER, $webhookSecret, $apiSecret)) {
    http_response_code(401);
    exit;
}
$event = json_decode($raw, true);
if (!is_array($event) || !is_string($event['event'] ?? null)) {
    http_response_code(400);
    exit;
}
$reference = (string)($event['data']['reference'] ?? '');
$hash = hash('sha256', $raw);
$pdo = getDbConnection();
try {
    $pdo->beginTransaction();
    $insert = $pdo->prepare("INSERT INTO PaymentWebhookEvents_007 (provider, payload_hash, event_type, transaction_reference, signature_verified)
        VALUES ('paystack', ?, ?, ?, TRUE) ON CONFLICT (provider, payload_hash) DO NOTHING RETURNING webhook_event_id");
    $insert->execute([$hash, substr($event['event'], 0, 64), $reference ?: null]);
    $eventId = $insert->fetchColumn();
    if (!$eventId) {
        $existing = $pdo->prepare("SELECT webhook_event_id, processing_status FROM PaymentWebhookEvents_007 WHERE provider = 'paystack' AND payload_hash = ? FOR UPDATE");
        $existing->execute([$hash]);
        $row = $existing->fetch();
        if (!$row || in_array($row['processing_status'], ['processed', 'ignored'], true)) {
            $pdo->commit();
            http_response_code(200);
            exit;
        }
        $eventId = $row['webhook_event_id'];
        $reset = $pdo->prepare("UPDATE PaymentWebhookEvents_007 SET processing_status = 'received' WHERE webhook_event_id = ?");
        $reset->execute([$eventId]);
    }
    $pdo->commit();
    if ($event['event'] === 'charge.success' && $reference !== '') {
        (new PaymentService($pdo))->verifyAndConfirm($reference);
        $status = 'processed';
    } elseif (in_array($event['event'], ['refund.pending', 'refund.processing', 'refund.needs-attention', 'refund.failed', 'refund.processed'], true)) {
        $refundData = $event['data'] ?? [];
        $transactionReference = (string)($refundData['transaction_reference'] ?? '');
        $refundAmount = filter_var($refundData['amount'] ?? null, FILTER_VALIDATE_INT);
        if ($transactionReference === '' || $refundAmount === false || strtoupper((string)($refundData['currency'] ?? '')) !== 'GHS') {
            throw new RuntimeException('Refund webhook is incomplete.');
        }
        $providerRefundReference = (string)($refundData['refund_reference'] ?? '');
        $refundStatus = [
            'refund.pending' => 'processing', 'refund.processing' => 'processing',
            'refund.needs-attention' => 'needs_attention', 'refund.failed' => 'failed', 'refund.processed' => 'processed',
        ][$event['event']];
        if ($providerRefundReference !== '') {
            $providerRefund = (new PaystackGateway())->getRefund($providerRefundReference);
            if (($providerRefund['reference'] ?? '') !== $providerRefundReference
                || (int)($providerRefund['amount'] ?? -1) !== (int)$refundAmount
                || strtoupper((string)($providerRefund['currency'] ?? '')) !== 'GHS') {
                throw new RuntimeException('Provider refund verification did not match the webhook.');
            }
            $providerStatus = (string)($providerRefund['status'] ?? '');
            $refundStatus = [
                'pending' => 'processing', 'processing' => 'processing', 'needs-attention' => 'needs_attention',
                'failed' => 'failed', 'processed' => 'processed',
            ][$providerStatus] ?? throw new RuntimeException('Provider returned an unknown refund status.');
        }
        $pdo->beginTransaction();
        $findRefund = $pdo->prepare("SELECT r.refund_id, r.amount_minor, b.booking_id, b.event_id, b.gross_minor, p.marketplace_payment_id FROM PaymentRefunds_007 r
            JOIN MarketplacePayments_007 p ON p.marketplace_payment_id = r.marketplace_payment_id
            JOIN BookingOrders_007 b ON b.booking_id = p.booking_id
            WHERE (p.transaction_reference = ? OR r.provider_refund_id = ?)
            ORDER BY CASE WHEN r.provider_refund_id = ? THEN 0 ELSE 1 END, r.created_at DESC LIMIT 1 FOR UPDATE OF r, b");
        $findRefund->execute([$transactionReference, $providerRefundReference, $providerRefundReference]);
        $refundRow = $findRefund->fetch();
        if (!$refundRow || (int)$refundRow['amount_minor'] !== (int)$refundAmount) {
            $pdo->rollBack();
            throw new RuntimeException('Refund webhook does not match an open refund record.');
        }
        $current = $pdo->prepare('SELECT status FROM PaymentRefunds_007 WHERE refund_id = ?');
        $current->execute([$refundRow['refund_id']]);
        $currentStatus = $current->fetchColumn();
        if (in_array($currentStatus, ['processed', 'failed', 'rejected'], true) && in_array($refundStatus, ['processing', 'needs_attention'], true)) {
            $refundStatus = $currentStatus;
        }
        $saveRefund = $pdo->prepare('UPDATE PaymentRefunds_007 SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE refund_id = ?');
        $saveRefund->execute([$refundStatus, $refundRow['refund_id']]);
        if ($refundStatus === 'processed') {
            $bookingStatus = (int)$refundRow['amount_minor'] >= (int)$refundRow['gross_minor'] ? 'refunded' : 'partially_refunded';
            $pdo->prepare('UPDATE BookingOrders_007 SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE booking_id = ?')->execute([$bookingStatus, $refundRow['booking_id']]);
            $pdo->prepare("UPDATE MarketplacePayments_007 SET status = 'reversed' WHERE marketplace_payment_id = ?")->execute([$refundRow['marketplace_payment_id']]);
            if ($bookingStatus === 'refunded') $pdo->prepare("UPDATE Tickets_007 SET ticket_status = 'cancelled' WHERE booking_id = ? AND ticket_status IN ('confirmed','booked')")->execute([$refundRow['booking_id']]);
            if ($bookingStatus === 'refunded') $pdo->query('SELECT PromoteWaitlist_007(' . (int)$refundRow['event_id'] . ')');
        } elseif ($refundStatus === 'failed') {
            $restore = $pdo->prepare("UPDATE BookingOrders_007 b SET status = CASE WHEN EXISTS (SELECT 1 FROM Tickets_007 t WHERE t.booking_id = b.booking_id) THEN 'paid' ELSE 'expired' END, updated_at = CURRENT_TIMESTAMP WHERE booking_id = ?");
            $restore->execute([$refundRow['booking_id']]);
        }
        $pdo->commit();
        $status = 'processed';
    } else {
        $status = 'ignored';
    }
    $update = $pdo->prepare('UPDATE PaymentWebhookEvents_007 SET processing_status = ?, processed_at = CURRENT_TIMESTAMP WHERE webhook_event_id = ?');
    $update->execute([$status, $eventId]);
    http_response_code(200);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if (isset($eventId) && $eventId) {
        $failed = $pdo->prepare("UPDATE PaymentWebhookEvents_007 SET processing_status = 'failed' WHERE webhook_event_id = ?");
        $failed->execute([$eventId]);
    }
    error_log('Payment webhook processing failed.');
    http_response_code(500);
}
