<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole(['attendee']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCsrfToken()) { http_response_code(400); exit('Invalid refund request.'); }
$bookingId = (int)($_POST['booking_id'] ?? 0);
$reason = trim((string)($_POST['reason'] ?? ''));
if ($bookingId < 1 || $reason === '' || strlen($reason) > 500) { setFlashMessage('danger', 'Enter a short refund reason.'); redirect('my_bookings.php'); }
try {
    $pdo = getDbConnection();
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("SELECT b.booking_id, b.status, b.gross_minor, p.marketplace_payment_id FROM BookingOrders_007 b
        JOIN MarketplacePayments_007 p ON p.booking_id = b.booking_id AND p.status = 'success'
        WHERE b.booking_id = ? AND b.attendee_id = ? FOR UPDATE OF b, p");
    $stmt->execute([$bookingId, getCurrentUserId()]);
    $order = $stmt->fetch();
    if (!$order || !in_array($order['status'], ['paid', 'refund_pending'], true)) throw new RuntimeException('No eligible paid booking was found.');
    $refund = $pdo->prepare("INSERT INTO PaymentRefunds_007 (marketplace_payment_id, requested_by, amount_minor, reason)
        VALUES (?, ?, ?, ?) ON CONFLICT (marketplace_payment_id) WHERE status IN ('requested','processing','needs_attention') DO NOTHING");
    $refund->execute([$order['marketplace_payment_id'], getCurrentUserId(), $order['gross_minor'], $reason]);
    if ($refund->rowCount() !== 1) throw new RuntimeException('A refund request is already open for this booking.');
    $pdo->prepare("UPDATE BookingOrders_007 SET status = 'refund_pending', updated_at = CURRENT_TIMESTAMP WHERE booking_id = ?")->execute([$bookingId]);
    $pdo->commit();
    setFlashMessage('success', 'Refund request sent for administrator review.');
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    setFlashMessage('danger', $e instanceof RuntimeException ? $e->getMessage() : 'Refund request could not be submitted.');
}
redirect('my_bookings.php');
