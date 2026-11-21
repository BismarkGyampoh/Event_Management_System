<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/payments/PaystackGateway.php';
requireRole(['admin']);
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken()) $message = 'Invalid form submission.';
    else {
        $refundId = (int)($_POST['refund_id'] ?? 0);
        $action = (string)($_POST['action'] ?? '');
        if ($refundId > 0 && $action === 'reject') {
            $pdo = getDbConnection();
            $pdo->beginTransaction();
            $order = $pdo->prepare('SELECT b.booking_id FROM PaymentRefunds_007 r JOIN MarketplacePayments_007 p ON p.marketplace_payment_id = r.marketplace_payment_id JOIN BookingOrders_007 b ON b.booking_id = p.booking_id WHERE r.refund_id = ? AND r.status = \'requested\' FOR UPDATE OF r, b');
            $order->execute([$refundId]);
            $bookingId = $order->fetchColumn();
            if ($bookingId) {
                $pdo->prepare("UPDATE PaymentRefunds_007 SET status = 'rejected', approved_by = ?, approved_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE refund_id = ?")->execute([getCurrentUserId(), $refundId]);
                $pdo->prepare("UPDATE BookingOrders_007 b SET status = CASE WHEN EXISTS (SELECT 1 FROM Tickets_007 t WHERE t.booking_id = b.booking_id) THEN 'paid' ELSE 'expired' END, updated_at = CURRENT_TIMESTAMP WHERE b.booking_id = ? AND b.status = 'refund_pending'")->execute([$bookingId]);
                $pdo->commit();
                logAuditAction(getCurrentUserId(), 'admin', 'UPDATE', 'PaymentRefunds_007', $refundId, [], ['status' => 'rejected']);
                $message = 'Refund request rejected.';
            } else {
                $pdo->rollBack();
                $message = 'Refund request is no longer awaiting review.';
            }
        } elseif ($refundId > 0 && $action === 'approve') {
            $pdo = getDbConnection();
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("SELECT r.*, p.transaction_reference, p.status AS payment_status, b.status AS booking_status, b.organizer_id FROM PaymentRefunds_007 r
                    JOIN MarketplacePayments_007 p ON p.marketplace_payment_id = r.marketplace_payment_id
                    JOIN BookingOrders_007 b ON b.booking_id = p.booking_id WHERE r.refund_id = ? FOR UPDATE OF r, p, b");
                $stmt->execute([$refundId]);
                $refund = $stmt->fetch();
                if (!$refund || $refund['status'] !== 'requested' || $refund['payment_status'] !== 'success') throw new RuntimeException('Refund is not eligible for approval.');
                $providerReference = 'rfd_' . str_pad((string)$refundId, 12, '0', STR_PAD_LEFT);
                $pdo->prepare("UPDATE PaymentRefunds_007 SET status = 'processing', provider_refund_id = ?, approved_by = ?, approved_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE refund_id = ?")->execute([$providerReference, getCurrentUserId(), $refundId]);
                $pdo->commit();
                $gateway = new PaystackGateway();
                $result = $gateway->refund($refund['transaction_reference'], (int)$refund['amount_minor'], $refund['reason'], $providerReference);
                $status = in_array(($result['status'] ?? ''), ['pending', 'processing'], true) ? 'processing' : 'needs_attention';
                $update = $pdo->prepare('UPDATE PaymentRefunds_007 SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE refund_id = ? AND status = \'processing\'');
                $update->execute([$status, $refundId]);
                logAuditAction(getCurrentUserId(), 'admin', 'UPDATE', 'PaymentRefunds_007', $refundId, [], ['status' => $status]);
                $message = $status === 'processing' ? 'Refund submitted to Paystack and is awaiting provider confirmation.' : 'Provider returned an unexpected refund state; manual reconciliation is required.';
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $pdo->prepare("UPDATE PaymentRefunds_007 SET status = 'needs_attention', updated_at = CURRENT_TIMESTAMP WHERE refund_id = ? AND status = 'processing'")->execute([$refundId]);
                error_log('Admin refund approval provider call failed.');
                $message = 'Refund could not be confirmed with the provider; it was flagged for manual reconciliation.';
            }
        }
    }
}
$refunds = fetchAll("SELECT r.*, p.transaction_reference, b.booking_reference, e.event_name, o.full_name AS organizer_name, a.full_name AS attendee_name
    FROM PaymentRefunds_007 r JOIN MarketplacePayments_007 p ON p.marketplace_payment_id = r.marketplace_payment_id
    JOIN BookingOrders_007 b ON b.booking_id = p.booking_id JOIN Events_007 e ON e.event_id = b.event_id
    JOIN Organizers_007 o ON o.organizer_id = b.organizer_id JOIN Attendees_007 a ON a.attendee_id = b.attendee_id
    ORDER BY CASE WHEN r.status = 'requested' THEN 0 ELSE 1 END, r.created_at DESC LIMIT 250");
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Refund review</title><link href="../assets/css/main.css" rel="stylesheet"><link rel="icon" type="image/svg+xml" href="<?php echo getAssetUrl('favicon.svg'); ?>">
</head><body><?php include __DIR__ . '/../includes/header.php'; ?><main class="container py-4"><h1 class="h2">Refund requests</h1><?php if ($message): ?><div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div><?php endif; ?><p class="alert alert-warning">Refund approval submits a provider request; it does not prove settlement reversal. Reconcile organizer settlement status before approving settled transactions.</p><div class="table-responsive"><table class="table"><thead><tr><th>Booking / Event</th><th>Attendee / Organizer</th><th>Amount</th><th>Reason</th><th>Status</th><th>Review</th></tr></thead><tbody><?php foreach ($refunds as $refund): ?><tr><td><?php echo htmlspecialchars($refund['booking_reference'] . ' · ' . $refund['event_name']); ?><br><small><?php echo htmlspecialchars($refund['transaction_reference']); ?></small></td><td><?php echo htmlspecialchars($refund['attendee_name'] . ' / ' . $refund['organizer_name']); ?></td><td>GHS <?php echo number_format(((int)$refund['amount_minor']) / 100, 2); ?></td><td><?php echo htmlspecialchars($refund['reason']); ?></td><td><?php echo htmlspecialchars($refund['status']); ?></td><td><?php if ($refund['status'] === 'requested'): ?><form method="post" class="d-flex gap-2"><?php echo csrfTokenField(); ?><input type="hidden" name="refund_id" value="<?php echo (int)$refund['refund_id']; ?>"><button name="action" value="approve" class="btn btn-sm btn-success">Approve</button><button name="action" value="reject" class="btn btn-sm btn-outline-danger">Reject</button></form><?php endif; ?></td></tr><?php endforeach; ?><?php if (!$refunds): ?><tr><td colspan="6" class="text-muted">No refund requests.</td></tr><?php endif; ?></tbody></table></div></main><?php include __DIR__ . '/../includes/footer.php'; ?></body></html>
