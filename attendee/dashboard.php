<?php
/**
 * Attendee Dashboard
 * Event Management System 007
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(['attendee']);

$pageTitle = 'Dashboard - Event Management System';
$attendeeId = getCurrentUserId();
$loyaltyPoints = $_SESSION['loyalty_points'] ?? 0;

try {
    // Get user's bookings
    $myBookings = fetchAll("
        SELECT t.ticket_id, t.ticket_number, t.ticket_status, t.booking_date,
               e.event_name, e.event_date, e.ticket_price, e.event_id,
               v.venue_name, v.city, v.address
        FROM Tickets_007 t
        INNER JOIN Events_007 e ON t.event_id = e.event_id
        INNER JOIN Venues_007 v ON e.venue_id = v.venue_id
        WHERE t.attendee_id = ?
        ORDER BY e.event_date DESC
        LIMIT 5
    ", [$attendeeId]);
    
    // Get upcoming events
    $upcomingEvents = fetchAll("
        SELECT e.*, o.full_name as organizer_name, v.venue_name, v.city,
               (e.max_capacity - (SELECT COUNT(*) FROM Tickets_007 WHERE event_id = e.event_id AND ticket_status NOT IN ('cancelled', 'expired'))) as available_tickets
        FROM Events_007 e
        INNER JOIN Organizers_007 o ON e.organizer_id = o.organizer_id
        INNER JOIN Venues_007 v ON e.venue_id = v.venue_id
        WHERE e.event_status = 'published' AND e.event_date > CURRENT_TIMESTAMP
        ORDER BY e.event_date ASC
        LIMIT 3
    ");
    
    // Get statistics
    $stats = [
        'total_bookings' => fetchOne("SELECT COUNT(*) as count FROM Tickets_007 WHERE attendee_id = ?", [$attendeeId])['count'] ?? 0,
        'upcoming' => fetchOne("
            SELECT COUNT(*) as count FROM Tickets_007 t
            INNER JOIN Events_007 e ON t.event_id = e.event_id
            WHERE t.attendee_id = ? AND e.event_date > CURRENT_TIMESTAMP AND t.ticket_status NOT IN ('cancelled', 'expired')
        ", [$attendeeId])['count'] ?? 0,
        'completed' => fetchOne("
            SELECT COUNT(*) as count FROM Tickets_007 t
            INNER JOIN Events_007 e ON t.event_id = e.event_id
            WHERE t.attendee_id = ? AND e.event_date <= CURRENT_TIMESTAMP
        ", [$attendeeId])['count'] ?? 0,
        'loyalty_points' => $loyaltyPoints
    ];
} catch (Exception $e) {
    $myBookings = [];
    $upcomingEvents = [];
    $stats = ['total_bookings' => 0, 'upcoming' => 0, 'completed' => 0, 'loyalty_points' => 0];
}
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
            <nav class="col-md-3 col-lg-2 d-md-block bg-light sidebar dashboard-sidebar collapse show" id="sidebarMenu" aria-label="Attendee dashboard navigation">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="browse_events.php">
                                <i class="fas fa-search me-2"></i>Browse Events
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="my_bookings.php">
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
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 role-dashboard attendee-dashboard">
                <header class="dashboard-page-header d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <div class="dashboard-heading-copy">
                        <p class="section-eyebrow mb-1">Your event life</p>
                        <h1>Welcome, <?php echo htmlspecialchars(getCurrentUserName()); ?>!</h1>
                    </div>
                    <a href="browse_events.php" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i>Browse Events
                    </a>
                </header>
                
                <!-- Statistics Cards -->
                <div class="row g-4 mb-4 dashboard-stat-grid">
                    <div class="col-md-3">
                        <div class="card text-center border-0 shadow-sm h-100">
                            <div class="card-body">
                                <i class="fas fa-ticket-alt fa-2x text-primary mb-2"></i>
                                <h4 class="fw-bold"><?php echo number_format($stats['total_bookings']); ?></h4>
                                <p class="text-muted small mb-0">Total Bookings</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center border-0 shadow-sm h-100">
                            <div class="card-body">
                                <i class="fas fa-calendar-check fa-2x text-success mb-2"></i>
                                <h4 class="fw-bold"><?php echo number_format($stats['upcoming']); ?></h4>
                                <p class="text-muted small mb-0">Upcoming Events</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center border-0 shadow-sm h-100">
                            <div class="card-body">
                                <i class="fas fa-check-circle fa-2x text-info mb-2"></i>
                                <h4 class="fw-bold"><?php echo number_format($stats['completed']); ?></h4>
                                <p class="text-muted small mb-0">Completed</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center border-0 shadow-sm h-100">
                            <div class="card-body">
                                <i class="fas fa-star fa-2x text-warning mb-2"></i>
                                <h4 class="fw-bold"><?php echo number_format($stats['loyalty_points']); ?></h4>
                                <p class="text-muted small mb-0">Loyalty Points</p>
                                <small class="text-muted">Tier: <?php echo getLoyaltyTier($stats['loyalty_points']); ?></small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <!-- My Bookings -->
                    <div class="col-lg-8 mb-4">
                        <div class="card border-0 shadow-sm dashboard-panel">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="fas fa-ticket-alt me-2"></i>My Recent Bookings</h5>
                                <a href="my_bookings.php" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                            <div class="card-body">
                                <?php if (empty($myBookings)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-ticket-alt fa-4x text-muted mb-3"></i>
                                        <h5 class="text-muted">No bookings yet</h5>
                                        <p class="text-muted">Browse events and book your first ticket!</p>
                                        <a href="browse_events.php" class="btn btn-primary">Browse Events</a>
                                    </div>
                                <?php else: ?>
                                    <div class="list-group list-group-flush attendee-bookings-list">
                                        <?php foreach ($myBookings as $booking): ?>
                                            <div class="list-group-item">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <h6 class="mb-1"><?php echo htmlspecialchars($booking['event_name']); ?></h6>
                                                        <p class="mb-1 small text-muted">
                                                            <i class="fas fa-calendar me-1"></i><?php echo formatDate($booking['event_date']); ?>
                                                            <i class="fas fa-map-marker-alt ms-2 me-1"></i><?php echo htmlspecialchars($booking['venue_name']); ?>
                                                        </p>
                                                        <code><?php echo htmlspecialchars($booking['ticket_number']); ?></code>
                                                    </div>
                                                    <div class="text-end">
                                                        <span class="badge bg-<?php echo getStatusBadgeClass($booking['ticket_status']); ?>">
                                                            <?php echo ucfirst($booking['ticket_status']); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Upcoming Events -->
                    <div class="col-lg-4 mb-4">
                        <div class="card border-0 shadow-sm dashboard-panel">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="fas fa-calendar-plus me-2"></i>Discover Events</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($upcomingEvents)): ?>
                                    <p class="text-muted text-center py-4">No upcoming events.</p>
                                <?php else: ?>
                                    <?php foreach ($upcomingEvents as $event): ?>
                                        <div class="dashboard-discovery-event">
                                            <a href="../event_details.php?event_id=<?php echo (int)$event['event_id']; ?>" class="dashboard-discovery-image" aria-label="View <?php echo htmlspecialchars($event['event_name']); ?>"><img src="<?php echo htmlspecialchars(getEventImageUrl($event['event_id'], $event['cover_image_path'] ?? null, $event['event_category'] ?? null)); ?>" alt="" loading="lazy"></a>
                                            <div class="dashboard-discovery-copy">
                                                <span class="event-category-label"><?php echo htmlspecialchars($event['event_category'] ?? 'Other'); ?></span>
                                                <h6><a href="../event_details.php?event_id=<?php echo (int)$event['event_id']; ?>"><?php echo htmlspecialchars($event['event_name']); ?></a></h6>
                                                <p class="small text-muted mb-2"><i class="fas fa-calendar me-1" aria-hidden="true"></i><?php echo formatDate($event['event_date']); ?> <i class="fas fa-location-dot ms-2 me-1" aria-hidden="true"></i><?php echo htmlspecialchars($event['city']); ?></p>
                                                <div class="d-flex justify-content-between align-items-center"><span class="fw-bold text-primary"><?php echo formatCurrency($event['ticket_price']); ?></span><span class="badge bg-<?php echo $event['available_tickets'] > 0 ? 'success' : 'danger'; ?>"><?php echo $event['available_tickets'] > 0 ? $event['available_tickets'] . ' left' : 'Sold Out'; ?></span></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    <a href="browse_events.php" class="btn btn-outline-primary w-100">
                                        Browse All Events
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Quick Links -->
                        <div class="card border-0 shadow-sm mt-3 dashboard-panel">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="fas fa-link me-2"></i>Quick Links</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <a href="profile.php" class="btn btn-outline-primary">
                                        <i class="fas fa-user-edit me-2"></i>Edit Profile
                                    </a>
                                    <a href="my_bookings.php" class="btn btn-outline-success">
                                        <i class="fas fa-ticket-alt me-2"></i>My Bookings
                                    </a>
                                </div>
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
