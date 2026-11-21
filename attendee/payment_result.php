<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/payments/PaymentService.php';
requireRole(['attendee']);
$reference = (string)($_GET['reference'] ?? $_GET['trxref'] ?? '');
$outcome = 'failed';
$bookingRef = '';
try {
    $ownedBeforeVerify = fetchOne('SELECT b.booking_reference FROM MarketplacePayments_007 p JOIN BookingOrders_007 b ON b.booking_id = p.booking_id WHERE p.transaction_reference = ? AND b.attendee_id = ?', [$reference, getCurrentUserId()]);
    if (!$ownedBeforeVerify) throw new RuntimeException('Booking not found.');
    $result = (new PaymentService(getDbConnection()))->verifyAndConfirm($reference);
    $owner = fetchOne('SELECT booking_reference FROM BookingOrders_007 WHERE booking_reference = ? AND attendee_id = ?', [$result['booking_reference'], getCurrentUserId()]);
    if (!$owner) throw new RuntimeException('Booking not found.');
    $bookingRef = $owner['booking_reference'];
    $outcome = $result['status'];
} catch (Throwable $e) {
    error_log('Payment return verification failed.');
}
$pageTitle = 'Payment status';
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Payment status</title><link href="../assets/css/main.css" rel="stylesheet"></head><body><?php include __DIR__ . '/../includes/header.php'; ?><main class="container py-5"><div class="card shadow-sm mx-auto" class="card shadow-sm mx-auto payment-result-card"><div class="card-body p-4"><h1 class="h3">Payment <?php echo htmlspecialchars(str_replace('_', ' ', $outcome)); ?></h1><?php if ($outcome === 'success'): ?><p>Your payment is verified and your booking is confirmed.</p><?php elseif ($outcome === 'refund_pending'): ?><p>Payment arrived after the reservation expired. It has been flagged for an administrator to review.</p><?php else: ?><p>We could not confirm a successful payment yet. Check your bookings before trying again.</p><?php endif; ?><?php if ($bookingRef): ?><p>Booking reference: <strong><?php echo htmlspecialchars($bookingRef); ?></strong></p><?php endif; ?><a class="btn btn-primary" href="my_bookings.php">View my bookings</a></div></div></main><?php include __DIR__ . '/../includes/footer.php'; ?></body></html>
