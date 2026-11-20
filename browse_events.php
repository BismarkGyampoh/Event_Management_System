<?php
/**
 * Browse Events - Public Page
 * Event Management System 007
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Browse Events - Event Management System';

// Get search/filter parameters
$search = $_GET['search'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

// Build query
$whereConditions = ["e.event_status = 'published'", "e.event_date > CURRENT_TIMESTAMP"];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(e.event_name LIKE ? OR e.event_description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($dateFrom)) {
    $whereConditions[] = "e.event_date >= ?";
    $params[] = $dateFrom . ' 00:00:00';
}

if (!empty($dateTo)) {
    $whereConditions[] = "e.event_date <= ?";
    $params[] = $dateTo . ' 23:59:59';
}

$whereClause = implode(" AND ", $whereConditions);

// Get events with pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 9;
$offset = ($page - 1) * $perPage;

try {
    $totalEvents = fetchOne("SELECT COUNT(*) as count FROM Events_007 e WHERE $whereClause", $params)['count'] ?? 0;
    
    $events = fetchAll("
        SELECT e.*, o.full_name as organizer_name, v.venue_name, v.city,
               (e.max_capacity - (SELECT COUNT(*) FROM Tickets_007 WHERE event_id = e.event_id AND ticket_status != 'cancelled')) as available_tickets
        FROM Events_007 e
        INNER JOIN Organizers_007 o ON e.organizer_id = o.organizer_id
        INNER JOIN Venues_007 v ON e.venue_id = v.venue_id
        WHERE $whereClause
        ORDER BY e.event_date ASC
        LIMIT $perPage OFFSET $offset
    ", $params);
} catch (Exception $e) {
    $totalEvents = 0;
    $events = [];
}

$totalPages = ceil($totalEvents / $perPage);
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
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="<?php echo getBaseUrl(); ?>">
                <i class="fas fa-calendar-alt me-2"></i>EventMS
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                    aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo getBaseUrl(); ?>">
                            <i class="fas fa-home me-1"></i>Home
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="<?php echo getBaseUrl(); ?>browse_events.php">
                            <i class="fas fa-search me-1"></i>Browse Events
                        </a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <?php if (isLoggedIn()): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user-circle me-1"></i>
                                <?php echo htmlspecialchars(getCurrentUserName()); ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <a class="dropdown-item" href="<?php echo getBaseUrl(); ?>attendee/profile.php">
                                        <i class="fas fa-id-card me-2"></i>My Profile
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item text-danger" href="<?php echo getBaseUrl(); ?>logout.php">
                                        <i class="fas fa-sign-out-alt me-2"></i>Logout
                                    </a>
                                </li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="btn btn-outline-light ms-2" href="<?php echo getBaseUrl(); ?>login.php">
                                <i class="fas fa-sign-in-alt me-1"></i>Login
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="btn btn-light ms-2" href="<?php echo getBaseUrl(); ?>register.php">Create account</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Flash Messages -->
    <div class="container mt-3">
        <?php displayFlashMessages(); ?>
    </div>

    <!-- Main Content -->
    <main class="py-5">
        <div class="container">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center mb-4">
                <h1 class="h2"><i class="fas fa-search me-2"></i>Browse Events</h1>
            </div>
            
            <!-- Search and Filter -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Search</label>
                            <input type="text" class="form-control" name="search" 
                                   placeholder="Search events..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Date From</label>
                            <input type="date" class="form-control" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Date To</label>
                            <input type="date" class="form-control" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <div class="d-grid gap-2 w-100">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search me-1"></i>Search
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Events Grid -->
            <div class="row g-4">
                <?php if (empty($events)): ?>
                    <div class="col-12">
                        <div class="empty-state">
                            <span class="empty-state-mark"><i class="far fa-calendar"></i></span>
                            <h3>No events found</h3>
                            <p class="text-muted mb-0">Try adjusting your filters, or check back later.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($events as $event): ?>
                        <div class="col-xl-4 col-md-6">
                            <div class="card h-100 border-0 shadow-sm event-card">
                                <div class="event-card-media">
                                    <img class="event-card-image" src="<?php echo getEventImageUrl($event['event_id']); ?>" alt="" loading="lazy" decoding="async" referrerpolicy="no-referrer">
                                    <time class="event-date-chip" datetime="<?php echo htmlspecialchars(date('Y-m-d', strtotime($event['event_date']))); ?>">
                                        <span><?php echo htmlspecialchars(date('M', strtotime($event['event_date']))); ?></span>
                                        <strong><?php echo htmlspecialchars(date('d', strtotime($event['event_date']))); ?></strong>
                                    </time>
                                </div>
                                <div class="card-header event-card-location">
                                    <span class="badge bg-light text-dark">
                                        <i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($event['city']); ?>
                                    </span>
                                </div>
                                <div class="card-body">
                                    <h5 class="card-title fw-bold"><?php echo htmlspecialchars($event['event_name']); ?></h5>
                                    <p class="text-muted small mb-2">
                                        <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($event['organizer_name']); ?>
                                    </p>
                                    <p class="text-muted small mb-2">
                                        <i class="fas fa-map-pin me-1"></i><?php echo htmlspecialchars($event['venue_name']); ?>
                                    </p>
                                    <p class="text-primary fw-bold mb-2">
                                        <i class="fas fa-calendar me-1"></i><?php echo formatDate($event['event_date']); ?>
                                    </p>
                                    <p class="card-text small"><?php echo truncateText($event['event_description'] ?? '', 80); ?></p>
                                </div>
                                <div class="card-footer bg-white border-top-0">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="fw-bold text-primary"><?php echo formatCurrency($event['ticket_price']); ?></span>
                                        <span class="badge <?php echo $event['available_tickets'] > 0 ? 'bg-success' : 'bg-danger'; ?>">
                                            <?php echo $event['available_tickets'] > 0 ? $event['available_tickets'] . ' available' : 'Sold Out'; ?>
                                        </span>
                                    </div>
                                    <?php if ($event['available_tickets'] > 0): ?>
                                        <?php if (isLoggedIn()): ?>
                                            <a href="<?php echo getBaseUrl(); ?>attendee/book_ticket.php?event_id=<?php echo $event['event_id']; ?>" class="btn btn-primary w-100 mt-2">
                                                <i class="fas fa-ticket-alt me-1"></i>Book Now
                                            </a>
                                        <?php else: ?>
                                            <a href="<?php echo getBaseUrl(); ?>login.php" class="btn btn-primary w-100 mt-2">
                                                <i class="fas fa-sign-in-alt me-1"></i>Login to Book
                                            </a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <button class="btn btn-secondary w-100 mt-2" disabled>Sold Out</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
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
            
            <p class="text-center text-muted mt-2">
                Showing <?php echo count($events); ?> of <?php echo $totalEvents; ?> events
            </p>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5 class="fw-bold"><i class="fas fa-calendar-alt me-2"></i>EventMS</h5>
                    <p class="small text-muted">Your complete event management solution.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <a href="#" class="text-white me-3"><i class="fab fa-facebook fa-lg"></i></a>
                    <a href="#" class="text-white me-3"><i class="fab fa-twitter fa-lg"></i></a>
                    <a href="#" class="text-white"><i class="fab fa-instagram fa-lg"></i></a>
                </div>
            </div>
            <hr class="border-secondary">
            <div class="text-center text-muted small">
                &copy; <?php echo date('Y'); ?> Event Management System. All rights reserved.
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.9/dist/js/bootstrap.bundle.min.js" integrity="sha384-WyOxWvTyr1QaMPfiPJSv76iReVthorl5uWlFtZPyK0IEB0hGzo9y5Bp9mYanr8Bu" crossorigin="anonymous"></script>
    <script src="<?php echo getAssetUrl('js/script.js'); ?>"></script>
</body>
</html>
