<?php
/**
 * Browse Events - Public Page
 * Event Management System 007
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Browse Events - Event Management System';

// Get search/filter parameters
$search = is_string($_GET['search'] ?? null) ? trim($_GET['search']) : '';
$search = mb_substr($search, 0, 120);
$dateFrom = is_string($_GET['date_from'] ?? null) && preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $_GET['date_from']) ? $_GET['date_from'] : '';
$dateTo = is_string($_GET['date_to'] ?? null) && preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $_GET['date_to']) ? $_GET['date_to'] : '';
$category = is_string($_GET['category'] ?? null) ? $_GET['category'] : '';
$city = is_string($_GET['city'] ?? null) ? trim($_GET['city']) : '';
$sort = is_string($_GET['sort'] ?? null) ? $_GET['sort'] : 'date_asc';
$categories = getEventCategories();
if (!in_array($category, $categories, true)) $category = '';
if (strlen($city) > 100) $city = substr($city, 0, 100);
if (!in_array($sort, ['date_asc', 'price_asc', 'price_desc'], true)) $sort = 'date_asc';

// Build query
$whereConditions = ["e.event_status = 'published'", "e.event_date > CURRENT_TIMESTAMP"];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(e.event_name ILIKE ? OR e.event_description ILIKE ? OR e.event_category ILIKE ? OR EXISTS (SELECT 1 FROM Venues_007 sv WHERE sv.venue_id = e.venue_id AND sv.city ILIKE ?))";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($category !== '') {
    $whereConditions[] = 'e.event_category = ?';
    $params[] = $category;
}

if ($city !== '') {
    $whereConditions[] = 'EXISTS (SELECT 1 FROM Venues_007 cv WHERE cv.venue_id = e.venue_id AND cv.city ILIKE ?)';
    $params[] = '%' . $city . '%';
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
$page = min(100000, max(1, (int)($_GET['page'] ?? 1)));
$perPage = 9;
$offset = ($page - 1) * $perPage;

try {
    $totalEvents = fetchOne("SELECT COUNT(*) as count FROM Events_007 e WHERE $whereClause", $params)['count'] ?? 0;
    
    $events = fetchAll("
        SELECT e.*, o.full_name as organizer_name, v.venue_name, v.city,
               (e.max_capacity - (SELECT COUNT(*) FROM Tickets_007 WHERE event_id = e.event_id AND ticket_status NOT IN ('cancelled', 'expired'))) as available_tickets
        FROM Events_007 e
        INNER JOIN Organizers_007 o ON e.organizer_id = o.organizer_id
        INNER JOIN Venues_007 v ON e.venue_id = v.venue_id
        WHERE $whereClause
        ORDER BY " . ($sort === 'price_asc' ? 'e.ticket_price ASC, e.event_date ASC' : ($sort === 'price_desc' ? 'e.ticket_price DESC, e.event_date ASC' : 'e.event_date ASC')) . "
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
    <meta name="description" content="Browse upcoming events by date, place and category on EventMS.">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link href="<?php echo getAssetUrl('css/main.css'); ?>" rel="stylesheet">
<link rel="icon" type="image/svg+xml" href="<?php echo getAssetUrl('favicon.svg'); ?>">
</head>
<body>
    <?php include __DIR__ . '/includes/header.php'; ?>

    <!-- Main Content -->
    <main id="main-content" class="py-5 browse-page">
        <div class="container">
            <div class="discovery-intro mb-4">
                <p class="section-eyebrow mb-2">Find your next experience</p>
                <h1>Explore events</h1>
                <p>Good plans start with a little discovery. Search by what you love, when you’re free, or where you want to go.</p>
            </div>

            <nav class="category-filter-pills mb-4" aria-label="Filter events by category">
                <a class="category-filter-pill <?php echo $category === '' ? 'active' : ''; ?>" href="<?php echo getBaseUrl(); ?>browse_events.php">All events</a>
                <?php foreach ($categories as $categoryOption): ?>
                    <a class="category-filter-pill <?php echo $category === $categoryOption ? 'active' : ''; ?>" href="<?php echo getBaseUrl(); ?>browse_events.php?<?php echo htmlspecialchars(http_build_query(array_filter(['category' => $categoryOption, 'city' => $city, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'sort' => $sort]))); ?>"><?php echo htmlspecialchars($categoryOption); ?></a>
                <?php endforeach; ?>
            </nav>
            
            <!-- Search and Filter -->
            <div class="card discovery-filter-panel mb-5">
                <div class="card-body">
                    <form method="GET" class="row g-3" role="search">
                        <div class="col-md-4">
                            <label class="form-label" for="event-search">Search events</label>
                            <input id="event-search" type="search" class="form-control" name="search" 
                                   placeholder="Search events..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="date-from">From</label>
                            <input id="date-from" type="date" class="form-control" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="date-to">To</label>
                            <input id="date-to" type="date" class="form-control" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="event-category">Category</label>
                            <select id="event-category" class="form-select" name="category"><option value="">All categories</option><?php foreach ($categories as $categoryOption): ?><option value="<?php echo htmlspecialchars($categoryOption); ?>" <?php echo $category === $categoryOption ? 'selected' : ''; ?>><?php echo htmlspecialchars($categoryOption); ?></option><?php endforeach; ?></select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="event-city">City</label>
                            <input id="event-city" type="search" class="form-control" name="city" placeholder="Any city" value="<?php echo htmlspecialchars($city); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="event-sort">Sort by</label>
                            <select id="event-sort" class="form-select" name="sort"><option value="date_asc" <?php echo $sort === 'date_asc' ? 'selected' : ''; ?>>Soonest</option><option value="price_asc" <?php echo $sort === 'price_asc' ? 'selected' : ''; ?>>Price: low to high</option><option value="price_desc" <?php echo $sort === 'price_desc' ? 'selected' : ''; ?>>Price: high to low</option></select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <div class="d-grid gap-2 w-100">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search me-1"></i>Search
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="events-results-header mb-3">
                <div><p class="section-eyebrow mb-1">Your shortlist</p><h2><?php echo $totalEvents ? number_format((int)$totalEvents) . ' events to explore' : 'Nothing matches yet'; ?></h2></div>
                <?php if ($search !== '' || $category !== '' || $city !== '' || $dateFrom !== '' || $dateTo !== ''): ?><a class="clear-filters-link" href="<?php echo getBaseUrl(); ?>browse_events.php">Clear filters</a><?php endif; ?>
            </div>

            <!-- Events Grid -->
            <div class="row g-4">
                <?php if (empty($events)): ?>
                    <div class="col-12">
                        <div class="empty-state discovery-empty-state">
                            <span class="empty-state-mark"><i class="fas fa-compass"></i></span>
                            <h3>No events match those filters</h3>
                            <p class="text-muted mb-3">Try another category, city, or date range to open up more possibilities.</p>
                            <a href="<?php echo getBaseUrl(); ?>browse_events.php" class="btn btn-primary">See all events</a>
                        </div>
                    </div>
                <?php else: ?>
                    <?php $usedBrowseImages = []; foreach ($events as $event): $eventCardImage = getUniqueEventImageUrl($event['event_id'], $event['cover_image_path'] ?? null, $usedBrowseImages); ?>
                        <div class="col-xl-4 col-md-6">
                            <a href="<?php echo getBaseUrl(); ?>event_details.php?event_id=<?php echo (int)$event['event_id']; ?>" class="card h-100 border-0 shadow-sm event-card" aria-label="View event: <?php echo htmlspecialchars($event['event_name'], ENT_QUOTES, 'UTF-8'); ?>">
                                <div class="event-card-media">
                                    <img class="event-card-image" src="<?php echo htmlspecialchars($eventCardImage); ?>" alt="Photography for <?php echo htmlspecialchars($event['event_category'] ?? 'an upcoming event'); ?>" width="640" height="480" loading="lazy" decoding="async" referrerpolicy="no-referrer">
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
                                    <h2 class="card-title fw-bold"><?php echo htmlspecialchars($event['event_name']); ?></h2>
                                    <span class="event-category-label"><?php echo htmlspecialchars($event['event_category'] ?? 'Other'); ?></span>
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
                                        <span class="fw-bold text-primary"><?php echo (float)$event['ticket_price'] <= 0 ? 'Free' : 'From ' . htmlspecialchars(formatCurrency($event['ticket_price'])); ?></span>
                                        <span class="badge <?php echo $event['available_tickets'] > 0 ? 'bg-success' : 'bg-danger'; ?>">
                                            <?php echo $event['available_tickets'] > 0 ? $event['available_tickets'] . ' available' : 'Sold Out'; ?>
                                        </span>
                                    </div>
                                    <?php if ($event['available_tickets'] > 0): ?>
                                        <span class="btn btn-primary w-100 mt-2">View event <span aria-hidden="true">↗</span></span>
                                    <?php else: ?>
                                        <span class="btn btn-secondary w-100 mt-2">Sold Out</span>
                                    <?php endif; ?>
                                </div>
                            </a>
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

    <?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
