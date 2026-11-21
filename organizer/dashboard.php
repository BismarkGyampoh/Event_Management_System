<?php
/**
 * Organizer Dashboard
 * Event Management System 007
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(['organizer']);

$pageTitle = 'Organizer Dashboard - Event Management System';
$organizerId = getCurrentUserId();

try {
    $marketStats = fetchOne("SELECT COUNT(*) FILTER (WHERE b.status = 'paid') AS paid_orders,
        COALESCE(SUM(b.gross_minor) FILTER (WHERE b.status IN ('paid','refund_pending')), 0) AS gross_minor,
        COALESCE(SUM(b.commission_minor) FILTER (WHERE b.status IN ('paid','refund_pending')), 0) AS commission_minor,
        COALESCE(SUM(b.gateway_fee_minor) FILTER (WHERE b.status IN ('paid','refund_pending')), 0) AS fees_minor,
        COALESCE(SUM(b.organizer_net_minor) FILTER (WHERE b.status IN ('paid','refund_pending')), 0) AS net_minor
        FROM BookingOrders_007 b WHERE b.organizer_id = ?", [$organizerId]);
} catch (Throwable $e) { $marketStats = null; }

try {
    // Get organizer's events
    $myEvents = fetchAll("
        SELECT e.*, v.venue_name, v.city,
               (SELECT COUNT(*) FROM Tickets_007 WHERE event_id = e.event_id AND ticket_status NOT IN ('cancelled', 'expired')) as tickets_sold,
               (SELECT COALESCE(SUM(amount), 0) FROM Payments_007 
                WHERE ticket_id IN (SELECT ticket_id FROM Tickets_007 WHERE event_id = e.event_id) 
                AND payment_status = 'completed') as revenue
        FROM Events_007 e
        INNER JOIN Venues_007 v ON e.venue_id = v.venue_id
        WHERE e.organizer_id = :organizer_id
        ORDER BY e.event_date DESC
    ", [':organizer_id' => $organizerId]);
    
    // Get statistics
    $stats = [
        'total_events' => count($myEvents),
        'published_events' => count(array_filter($myEvents, function($e) { return $e['event_status'] === 'published'; })),
        'total_tickets' => array_sum(array_column($myEvents, 'tickets_sold')),
        'total_revenue' => array_sum(array_column($myEvents, 'revenue'))
    ];
} catch (Exception $e) {
    $myEvents = [];
    $stats = ['total_events' => 0, 'published_events' => 0, 'total_tickets' => 0, 'total_revenue' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Manage your EventMS events, ticket sales and attendee activity.">
    <title><?php echo $pageTitle; ?></title>
    <link href="<?php echo getAssetUrl('css/main.css'); ?>" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <nav class="col-md-3 col-lg-2 d-md-block bg-light sidebar dashboard-sidebar" id="sidebarMenu" aria-label="Organizer dashboard navigation">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard.php">
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
                            <a class="nav-link" href="view_booking.php">
                                <i class="fas fa-ticket-alt me-2"></i>Bookings
                            </a>
                        </li>
                        <li class="nav-item"><a class="nav-link" href="payment_account.php"><i class="fas fa-university me-2"></i>Settlement account</a></li>
                        <li class="nav-item"><a class="nav-link" href="transactions.php"><i class="fas fa-receipt me-2"></i>Transactions</a></li>
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
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 role-dashboard organizer-dashboard">
                <header class="dashboard-page-header d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <div class="dashboard-heading-copy">
                        <p class="section-eyebrow mb-1">Your event workspace</p>
                        <h1>Organizer Dashboard</h1>
                    </div>
                    <a href="create_event.php" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i>Create Event
                    </a>
                </header>
                
                <!-- Statistics Cards -->
                <div class="row g-4 mb-4 dashboard-stat-grid">
                    <div class="col-md-3">
                        <div class="card text-center border-0 shadow-sm h-100">
                            <div class="card-body">
                                <i class="fas fa-calendar fa-2x text-primary mb-2"></i>
                                <h4 class="fw-bold"><?php echo number_format($stats['total_events']); ?></h4>
                                <p class="text-muted small mb-0">Total Events</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center border-0 shadow-sm h-100">
                            <div class="card-body">
                                <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                                <h4 class="fw-bold"><?php echo number_format($stats['published_events']); ?></h4>
                                <p class="text-muted small mb-0">Published</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center border-0 shadow-sm h-100">
                            <div class="card-body">
                                <i class="fas fa-ticket-alt fa-2x text-warning mb-2"></i>
                                <h4 class="fw-bold"><?php echo number_format($stats['total_tickets']); ?></h4>
                                <p class="text-muted small mb-0">Tickets Sold</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center border-0 shadow-sm h-100">
                            <div class="card-body">
                                <i class="fas fa-coins fa-2x text-success mb-2"></i>
                                <h4 class="fw-bold"><?php echo formatCurrency($stats['total_revenue']); ?></h4>
                                <p class="text-muted small mb-0">Total Revenue</p>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($marketStats): ?>
                <section class="card border-0 shadow-sm mb-4 dashboard-panel"><div class="card-body"><div class="d-flex justify-content-between align-items-center"><h2 class="h5 mb-0">Online payment overview</h2><a href="payment_account.php" class="btn btn-sm btn-outline-primary">Settlement account</a></div><div class="row g-3 mt-2"><div class="col-sm-6 col-lg"><small class="text-muted">Gross ticket sales</small><div class="fw-semibold">GHS <?php echo number_format(((int)$marketStats['gross_minor']) / 100, 2); ?></div></div><div class="col-sm-6 col-lg"><small class="text-muted">Platform commission</small><div class="fw-semibold">GHS <?php echo number_format(((int)$marketStats['commission_minor']) / 100, 2); ?></div></div><div class="col-sm-6 col-lg"><small class="text-muted">Gateway fees</small><div class="fw-semibold">GHS <?php echo number_format(((int)$marketStats['fees_minor']) / 100, 2); ?></div></div><div class="col-sm-6 col-lg"><small class="text-muted">Organizer allocation</small><div class="fw-semibold">GHS <?php echo number_format(((int)$marketStats['net_minor']) / 100, 2); ?></div></div></div><p class="small text-muted mt-3 mb-0">Organizer allocation is calculated from verified payments. It does not confirm that a settlement has reached your bank.</p></div></section>
                <?php endif; ?>
                
                <!-- My Events -->
                <div class="card border-0 shadow-sm dashboard-panel">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-calendar me-2"></i>My Events</h5>
                        <a href="my_events.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($myEvents)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-calendar-plus fa-4x text-muted mb-3"></i>
                                <h5 class="text-muted">No events yet</h5>
                                <p class="text-muted">Create your first event to get started!</p>
                                <a href="create_event.php" class="btn btn-primary">
                                    <i class="fas fa-plus me-1"></i>Create Event
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Event Name</th>
                                            <th>Date</th>
                                            <th>Venue</th>
                                            <th>Status</th>
                                            <th>Tickets</th>
                                            <th>Revenue</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (array_slice($myEvents, 0, 5) as $event): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($event['event_name']); ?></strong>
                                                </td>
                                                <td><?php echo formatDate($event['event_date']); ?></td>
                                                <td><?php echo htmlspecialchars($event['venue_name']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo getStatusBadgeClass($event['event_status']); ?>">
                                                        <?php echo ucfirst($event['event_status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info">
                                                        <?php echo $event['tickets_sold']; ?>/<?php echo $event['max_capacity']; ?>
                                                    </span>
                                                </td>
                                                <td class="fw-bold text-success"><?php echo formatCurrency($event['revenue']); ?></td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="edit_event.php?id=<?php echo $event['event_id']; ?>" 
                                                           class="btn btn-outline-primary">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <a href="view_booking.php?event_id=<?php echo $event['event_id']; ?>" 
                                                           class="btn btn-outline-info">
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
                
                <!-- Quick Actions -->
                <div class="row mt-4">
                    <div class="col-lg-4">
                        <div class="card border-0 shadow-sm dashboard-panel">
                            <div class="card-body text-center">
                                <i class="fas fa-plus-circle fa-3x text-primary mb-3"></i>
                                <h5>Create New Event</h5>
                                <p class="text-muted">Add a new event to your portfolio</p>
                                <a href="create_event.php" class="btn btn-primary">Create Event</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="card border-0 shadow-sm dashboard-panel">
                            <div class="card-body text-center">
                                <i class="fas fa-ticket-alt fa-3x text-info mb-3"></i>
                                <h5>View Bookings</h5>
                                <p class="text-muted">See who booked your events</p>
                                <a href="view_booking.php" class="btn btn-info">View Bookings</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="card border-0 shadow-sm dashboard-panel">
                            <div class="card-body text-center">
                                <i class="fas fa-chart-line fa-3x text-success mb-3"></i>
                                <h5>Revenue Report</h5>
                                <p class="text-muted">Track your earnings</p>
                                <a href="my_events.php" class="btn btn-success">View Report</a>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
