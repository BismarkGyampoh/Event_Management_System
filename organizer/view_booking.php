<?php
/**
 * View Bookings - Organizer Page
 * Event Management System 007
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(['organizer']);

$pageTitle = 'View Bookings - Organizer Dashboard';
$organizerId = getCurrentUserId();

$eventId = (int)($_GET['event_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken()) {
        setFlashMessage('danger', 'Invalid form submission. Please try again.');
        redirect(getBaseUrl() . 'organizer/view_booking.php');
    }

    $action = $_POST['action'] ?? '';
    $ticketId = (int)($_POST['ticket_id'] ?? 0);
    $returnEventId = max(0, (int)($_POST['event_id'] ?? 0));

    try {
        $pdo = getDbConnection();
        $pdo->beginTransaction();

        $booking = fetchOne("
            SELECT t.ticket_id, t.ticket_status, t.attendee_id,
                   p.payment_status, p.payment_method
            FROM Tickets_007 t
            INNER JOIN Events_007 e ON e.event_id = t.event_id
            INNER JOIN Payments_007 p ON p.ticket_id = t.ticket_id
            WHERE t.ticket_id = :ticket_id AND e.organizer_id = :organizer_id
            FOR UPDATE OF t, p
        ", [':ticket_id' => $ticketId, ':organizer_id' => $organizerId]);

        if (!$booking) {
            throw new RuntimeException('Booking not found for one of your events.');
        }

        if ($action === 'confirm_cash_payment'
            && $booking['ticket_status'] === 'booked'
            && $booking['payment_status'] === 'pending'
            && $booking['payment_method'] === 'cash') {
            $stmt = $pdo->prepare("
                UPDATE Payments_007 SET payment_status = 'completed', payment_date = CURRENT_TIMESTAMP
                WHERE ticket_id = :ticket_id AND payment_status = 'pending'
            ");
            $stmt->execute([':ticket_id' => $ticketId]);
            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('Payment status changed; refresh the page and try again.');
            }

            $stmt = $pdo->prepare("
                UPDATE Tickets_007 SET ticket_status = 'confirmed'
                WHERE ticket_id = :ticket_id AND ticket_status = 'booked'
            ");
            $stmt->execute([':ticket_id' => $ticketId]);
            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('Booking status changed; refresh the page and try again.');
            }

            $stmt = $pdo->prepare("UPDATE Attendees_007 SET loyalty_points = loyalty_points + 10 WHERE attendee_id = :attendee_id");
            $stmt->execute([':attendee_id' => $booking['attendee_id']]);
            logAuditAction($organizerId, 'organizer', 'PAYMENT_CONFIRMED', 'Payments_007', $ticketId);
            $successMessage = 'Cash payment confirmed. The ticket is now valid for entry.';
        } elseif ($action === 'confirm_cash_refund'
            && $booking['ticket_status'] === 'cancelled'
            && $booking['payment_status'] === 'refund_pending'
            && $booking['payment_method'] === 'cash') {
            $stmt = $pdo->prepare("
                UPDATE Payments_007 SET payment_status = 'refunded', payment_date = CURRENT_TIMESTAMP
                WHERE ticket_id = :ticket_id AND payment_status = 'refund_pending'
            ");
            $stmt->execute([':ticket_id' => $ticketId]);
            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('Refund status changed; refresh the page and try again.');
            }

            $stmt = $pdo->prepare("
                UPDATE Attendees_007 SET loyalty_points = GREATEST(0, loyalty_points - 10)
                WHERE attendee_id = :attendee_id
            ");
            $stmt->execute([':attendee_id' => $booking['attendee_id']]);
            logAuditAction($organizerId, 'organizer', 'CASH_REFUND_CONFIRMED', 'Payments_007', $ticketId);
            $successMessage = 'Cash refund marked as completed.';
        } else {
            throw new RuntimeException('This booking cannot be changed in its current state.');
        }

        $pdo->commit();
        setFlashMessage('success', $successMessage);
        $returnUrl = getBaseUrl() . 'organizer/view_booking.php';
        redirect($returnUrl . ($returnEventId > 0 ? '?event_id=' . $returnEventId : ''));
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Organizer payment action error: ' . $e->getMessage());
        setFlashMessage('danger', $e instanceof RuntimeException
            ? $e->getMessage()
            : 'Could not update this payment. Please try again.');
        $returnUrl = getBaseUrl() . 'organizer/view_booking.php';
        redirect($returnUrl . ($returnEventId > 0 ? '?event_id=' . $returnEventId : ''));
    }
}

// Get organizer's events
try {
    $myEvents = fetchAll("
        SELECT event_id, event_name, event_date 
        FROM Events_007 
        WHERE organizer_id = :organizer_id 
        ORDER BY event_date DESC
    ", [':organizer_id' => $organizerId]);
} catch (Exception $e) {
    $myEvents = [];
}

// Build query for bookings
$whereConditions = ["e.organizer_id = :organizer_id"];
$params = [':organizer_id' => $organizerId];

if ($eventId > 0) {
    // Verify the event belongs to this organizer
    $eventBelongsToOrganizer = fetchOne("SELECT event_id FROM Events_007 WHERE event_id = :event_id AND organizer_id = :organizer_id2", [':event_id' => $eventId, ':organizer_id2' => $organizerId]);
    if ($eventBelongsToOrganizer) {
        $whereConditions[] = "t.event_id = :event_id";
        $params[':event_id'] = $eventId;
    }
}

$whereClause = implode(" AND ", $whereConditions);

// Get bookings with pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

try {
    $totalBookings = fetchOne("
        SELECT COUNT(*) as count 
        FROM Tickets_007 t
        INNER JOIN Events_007 e ON t.event_id = e.event_id
        WHERE $whereClause
    ", $params)['count'] ?? 0;
    
    $bookings = fetchAll("
        SELECT t.ticket_id, t.ticket_number, t.ticket_status, t.booking_date, t.attendee_id,
               a.full_name as attendee_name, a.email as attendee_email,
               e.event_name, e.event_date, e.ticket_price,
               p.amount as payment_amount, p.payment_status as payment_status,
               p.payment_method as payment_method
        FROM Tickets_007 t
        INNER JOIN Events_007 e ON t.event_id = e.event_id
        INNER JOIN Attendees_007 a ON t.attendee_id = a.attendee_id
        LEFT JOIN Payments_007 p ON t.ticket_id = p.ticket_id
        WHERE $whereClause
        ORDER BY t.booking_date DESC
        LIMIT $perPage OFFSET $offset
    ", $params);
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
    <link href="<?php echo getAssetUrl('css/main.css'); ?>" rel="stylesheet">
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
                            <a class="nav-link" href="my_events.php">
                                <i class="fas fa-calendar me-2"></i>My Events
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="create_event.php">
                                <i class="fas fa-plus-circle me-2"></i>Create Event
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="view_booking.php">
                                <i class="fas fa-ticket-alt me-2"></i>Bookings
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
                    <h1 class="h2"><i class="fas fa-ticket-alt me-2"></i>View Bookings</h1>
                </div>
                
                <!-- Filter -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label">Filter by Event</label>
                                <select class="form-select" name="event_id" onchange="this.form.submit()">
                                    <option value="">All Events</option>
                                    <?php foreach ($myEvents as $ev): ?>
                                        <option value="<?php echo $ev['event_id']; ?>" 
                                                <?php echo $eventId == $ev['event_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($ev['event_name']); ?> 
                                            (<?php echo formatDate($ev['event_date']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter me-1"></i>Filter
                                </button>
                                <a href="view_booking.php" class="btn btn-outline-secondary">Reset</a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Bookings Table -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Bookings (<?php echo number_format($totalBookings); ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($bookings)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-ticket-alt fa-4x text-muted mb-3"></i>
                                <h5 class="text-muted">No bookings found</h5>
                                <p class="text-muted">
                                    <?php echo $eventId > 0 ? 'This event has no bookings yet.' : 'No bookings for your events yet.'; ?>
                                </p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Ticket #</th>
                                            <th>Attendee</th>
                                            <th>Event</th>
                                            <th>Event Date</th>
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
                                                    <strong><?php echo htmlspecialchars($booking['attendee_name']); ?></strong>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($booking['attendee_email']); ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($booking['event_name']); ?></td>
                                                <td><?php echo formatDate($booking['event_date']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo getStatusBadgeClass($booking['ticket_status']); ?>">
                                                        <?php echo ucwords(str_replace('_', ' ', $booking['ticket_status'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo getStatusBadgeClass($booking['payment_status']); ?>">
                                                        <?php echo ucwords(str_replace('_', ' ', $booking['payment_status'] ?? 'pending')); ?>
                                                    </span>
                                                    <br><small class="<?php echo $booking['payment_status'] === 'completed' ? 'text-success' : 'text-muted'; ?>">
                                                        <?php echo formatCurrency($booking['payment_amount'] ?? $booking['ticket_price']); ?>
                                                    </small>
                                                </td>
                                                <td><?php echo formatDate($booking['booking_date']); ?></td>
                                                <td class="text-nowrap">
                                                    <?php if ($booking['payment_status'] === 'pending' && $booking['ticket_status'] === 'booked'): ?>
                                                        <form method="POST" class="d-inline" onsubmit="return confirm('Confirm that you received this cash payment?');">
                                                            <?php echo csrfTokenField(); ?>
                                                            <input type="hidden" name="action" value="confirm_cash_payment">
                                                            <input type="hidden" name="ticket_id" value="<?php echo (int)$booking['ticket_id']; ?>">
                                                            <input type="hidden" name="event_id" value="<?php echo $eventId; ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-success">Confirm cash received</button>
                                                        </form>
                                                    <?php elseif ($booking['payment_status'] === 'refund_pending' && $booking['payment_method'] === 'cash'): ?>
                                                        <form method="POST" class="d-inline" onsubmit="return confirm('Confirm that you have refunded the cash payment?');">
                                                            <?php echo csrfTokenField(); ?>
                                                            <input type="hidden" name="action" value="confirm_cash_refund">
                                                            <input type="hidden" name="ticket_id" value="<?php echo (int)$booking['ticket_id']; ?>">
                                                            <input type="hidden" name="event_id" value="<?php echo $eventId; ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-warning">Confirm cash refunded</button>
                                                        </form>
                                                    <?php elseif ($booking['payment_status'] === 'refund_pending'): ?>
                                                        <span class="text-warning">Manual review required</span>
                                                    <?php else: ?>
                                                        <span class="text-muted">—</span>
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
