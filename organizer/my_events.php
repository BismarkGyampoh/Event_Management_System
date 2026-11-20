<?php
/**
 * My Events - Organizer Page
 * Event Management System 007
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(['organizer']);

$pageTitle = 'My Events - Organizer Dashboard';
$organizerId = getCurrentUserId();

try {
    $events = fetchAll("
        SELECT e.*, v.venue_name, v.city, v.address,
               (SELECT COUNT(*) FROM Tickets_007 WHERE event_id = e.event_id AND ticket_status != 'cancelled') as tickets_sold,
               (SELECT COALESCE(SUM(amount), 0) FROM Payments_007 
                WHERE ticket_id IN (SELECT ticket_id FROM Tickets_007 WHERE event_id = e.event_id) 
                AND payment_status = 'completed') as revenue
        FROM Events_007 e
        INNER JOIN Venues_007 v ON e.venue_id = v.venue_id
        WHERE e.organizer_id = :organizer_id
        ORDER BY e.event_date DESC
    ", [':organizer_id' => $organizerId]);
} catch (Exception $e) {
    $events = [];
}
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
                            <a class="nav-link active" href="my_events.php">
                                <i class="fas fa-calendar me-2"></i>My Events
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="create_event.php">
                                <i class="fas fa-plus-circle me-2"></i>Create Event
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="view_booking.php">
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
                    <h1 class="h2"><i class="fas fa-calendar me-2"></i>My Events</h1>
                    <a href="create_event.php" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i>Create Event
                    </a>
                </div>
                
                <?php displayFlashMessages(); ?>
                
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">All Events (<?php echo count($events); ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($events)): ?>
                            <div class="empty-state">
                                <span class="empty-state-mark"><i class="far fa-calendar-plus"></i></span>
                                <h3>No events yet</h3>
                                <p class="text-muted">Create your first event to get started.</p>
                                <a href="create_event.php" class="btn btn-primary btn-lg">
                                    <i class="fas fa-plus me-2"></i>Create Event
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Event Name</th>
                                            <th>Date & Time</th>
                                            <th>Venue</th>
                                            <th>Ticket Price</th>
                                            <th>Status</th>
                                            <th>Tickets</th>
                                            <th>Revenue</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($events as $event): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($event['event_name']); ?></strong>
                                                    <br><small class="text-muted"><?php echo truncateText($event['event_description'] ?? '', 50); ?></small>
                                                </td>
                                                <td>
                                                    <?php echo formatDate($event['event_date']); ?>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($event['venue_name']); ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($event['city']); ?></small>
                                                </td>
                                                <td><?php echo formatCurrency($event['ticket_price']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo getStatusBadgeClass($event['event_status']); ?>">
                                                        <?php echo ucfirst($event['event_status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info">
                                                        <?php echo $event['tickets_sold']; ?>/<?php echo $event['max_capacity']; ?>
                                                    </span>
                                                    <div class="progress mt-1" style="height: 5px; width: 80px;">
                                                        <div class="progress-bar" role="progressbar" 
                                                             style="width: <?php echo ($event['tickets_sold'] / $event['max_capacity']) * 100; ?>%"></div>
                                                    </div>
                                                </td>
                                                <td class="fw-bold text-success"><?php echo formatCurrency($event['revenue']); ?></td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="edit_event.php?id=<?php echo $event['event_id']; ?>" 
                                                           class="btn btn-outline-primary" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <a href="view_booking.php?event_id=<?php echo $event['event_id']; ?>" 
                                                           class="btn btn-outline-info" title="View Bookings">
                                                            <i class="fas fa-ticket-alt"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
