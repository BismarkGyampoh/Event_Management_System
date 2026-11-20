<?php
/**
 * My Bookings - Attendee Page
 * Event Management System 007
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(['attendee']);

$pageTitle = 'My Bookings - Event Management System';
$attendeeId = getCurrentUserId();

$message = '';
$messageType = '';

// Handle cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel') {
    if (!validateCsrfToken()) {
        $message = 'Invalid form submission.';
        $messageType = 'danger';
    } else {
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        
        if ($ticketId <= 0) {
            $message = 'Invalid ticket.';
            $messageType = 'danger';
        } else {
            try {
                $sql = "SELECT * FROM CancelBooking_007(CAST(? AS INTEGER), CAST(? AS INTEGER))";
                $stmt = getDbConnection()->prepare($sql);
                $stmt->execute([$ticketId, $attendeeId]);
                $result = $stmt->fetch();
                
                if ($result && isset($result['status']) && $result['status'] === 'Success') {
                    setFlashMessage('success', $result['message']);
                    redirect('my_bookings.php');
                } else {
                    $message = $result['message'] ?? 'Error cancelling booking.';
                    $messageType = 'danger';
                }
            } catch (Exception $e) {
                error_log('Attendee booking cancellation error: ' . $e->getMessage());
                $message = 'We could not cancel this booking. Please try again.';
                $messageType = 'danger';
            }
        }
    }
}

// Get user's bookings with pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

try {
    $totalBookings = fetchOne("
        SELECT COUNT(*) as count FROM Tickets_007 WHERE attendee_id = :attendee_id
    ", [':attendee_id' => $attendeeId])['count'] ?? 0;
    
    $bookings = fetchAll("
        SELECT t.ticket_id, t.ticket_number, t.ticket_status, t.booking_date,
               e.event_name, e.event_date, e.ticket_price, e.event_id, e.event_status,
               v.venue_name, v.city, v.address,
               p.amount as payment_amount, p.payment_status as payment_status
        FROM Tickets_007 t
        INNER JOIN Events_007 e ON t.event_id = e.event_id
        INNER JOIN Venues_007 v ON e.venue_id = v.venue_id
        LEFT JOIN Payments_007 p ON t.ticket_id = p.ticket_id
        WHERE t.attendee_id = :attendee_id
        ORDER BY e.event_date DESC
        LIMIT $perPage OFFSET $offset
    ", [':attendee_id' => $attendeeId]);
} catch (Exception $e) {
    $totalBookings = 0;
    $bookings = [];
}

$totalPages = ceil($totalBookings / $perPage);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.9/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-yEPZ7jP9S0QKiUQ0m3jjsO4ProIW7wvR9gXkI7JOGa2XVsw53Yke2bsdkBbjmmiX" crossorigin="anonymous">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo getAssetUrl('css/style.css'); ?>" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <nav class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse show" id="sidebarMenu">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="browse_events.php">
                                <i class="fas fa-search me-2"></i>Browse Events
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="my_bookings.php">
                                <i class="fas fa-ticket-alt me-2"></i>My Bookings
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="profile.php">
                                <i class="fas fa-user me-2"></i>Profile
                            </a>
                        </li>
                        <li class="nav-item mt-3">
                            <a class="nav-link text-danger" href="../logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-ticket-alt me-2"></i>My Bookings</h1>
                    <a href="browse_events.php" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i>Browse Events
                    </a>
                </div>
                
                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php displayFlashMessages(); ?>
                
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">All Bookings (<?php echo number_format($totalBookings); ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($bookings)): ?>
                            <div class="empty-state">
                                <span class="empty-state-mark"><i class="fas fa-ticket-alt"></i></span>
                                <h3>No bookings yet</h3>
                                <p class="text-muted">Browse events and book your first ticket.</p>
                                <a href="browse_events.php" class="btn btn-primary">Browse Events</a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Ticket #</th>
                                            <th>Event</th>
                                            <th>Date & Time</th>
                                            <th>Venue</th>
                                            <th>Status</th>
                                            <th>Payment</th>
                                            <th>Booked On</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($bookings as $booking): ?>
                                            <tr>
                                                <td>
                                                    <code><?php echo htmlspecialchars($booking['ticket_number']); ?></code>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($booking['event_name']); ?></strong>
                                                    <?php if ($booking['event_status'] === 'cancelled'): ?>
                                                        <br><span class="badge bg-danger">Event cancelled</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo formatDate($booking['event_date']); ?></td>
                                                <td>
                                                    <?php echo htmlspecialchars($booking['venue_name']); ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($booking['city']); ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo getStatusBadgeClass($booking['ticket_status']); ?>">
                                                        <?php echo ucwords(str_replace('_', ' ', $booking['ticket_status'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo getStatusBadgeClass($booking['payment_status'] ?? 'pending'); ?>">
                                                        <?php echo ucwords(str_replace('_', ' ', $booking['payment_status'] ?? 'pending')); ?>
                                                    </span>
                                                    <br><small class="<?php echo $booking['payment_status'] === 'completed' ? 'text-success' : 'text-muted'; ?>">
                                                        <?php echo formatCurrency($booking['payment_amount'] ?? $booking['ticket_price']); ?>
                                                    </small>
                                                </td>
                                                <td><?php echo formatDate($booking['booking_date']); ?></td>
                                                <td>
                                                    <a href="ticket.php?ticket_id=<?php echo $booking['ticket_id']; ?>" class="btn btn-sm btn-outline-primary mb-1">
                                                        <i class="fas fa-qrcode"></i> View Ticket
                                                    </a>
                                                    <?php if ($booking['event_status'] !== 'cancelled' && $booking['ticket_status'] !== 'cancelled' && strtotime($booking['event_date']) > time()): ?>
                                                        <form method="POST" class="d-inline" onsubmit="return confirm('Cancel this booking? If you already paid in cash, contact the organizer about the refund.');">
                                                            <?php echo csrfTokenField(); ?>
                                                            <input type="hidden" name="action" value="cancel">
                                                            <input type="hidden" name="ticket_id" value="<?php echo $booking['ticket_id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger mb-1">
                                                                <i class="fas fa-times"></i> Cancel
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Pagination -->
                            <?php if ($totalPages > 1): ?>
                                <nav aria-label="Page navigation" class="mt-4">
                                    <ul class="pagination justify-content-center mb-0">
                                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
