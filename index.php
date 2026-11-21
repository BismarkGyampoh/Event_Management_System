<?php
/**
 * Home Page
 * Event Management System 007
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Home - Event Management System';

// Get upcoming events
try {
    $sql = "SELECT e.*, o.full_name as organizer_name, v.venue_name, v.city,
                   (e.max_capacity - (SELECT COUNT(*) FROM Tickets_007 WHERE event_id = e.event_id AND ticket_status NOT IN ('cancelled', 'expired'))) as available_tickets
            FROM Events_007 e
            INNER JOIN Organizers_007 o ON e.organizer_id = o.organizer_id
            INNER JOIN Venues_007 v ON e.venue_id = v.venue_id
            WHERE e.event_status = 'published' AND e.event_date > CURRENT_TIMESTAMP
            ORDER BY e.event_date ASC
            LIMIT 3";
    $upcomingEvents = fetchAll($sql);
} catch (Exception $e) {
    $upcomingEvents = [];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="description" content="Find live music, local gatherings and good things to do around Ghana with EventMS.">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link href="<?php echo getAssetUrl('css/main.css'); ?>" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/includes/header.php'; ?>

    <main id="main-content">
    <header class="hero-section">
        <div class="container">
            <div class="row align-items-center g-5">
                <div class="col-lg-7">
                    <p class="hero-eyebrow"><span class="hero-eyebrow-mark" aria-hidden="true"></span>Find your next memorable moment</p>
                    <h1>Make plans <em>worth looking forward to.</em></h1>
                    <p class="lead mb-4">Discover live events, local gatherings and new experiences. Find what’s happening and book your place.</p>
                    <div class="hero-actions d-flex flex-wrap gap-3">
                        <a href="<?php echo getBaseUrl(); ?>browse_events.php" class="btn btn-primary btn-lg">
                            Browse events <i class="fas fa-arrow-right ms-2"></i>
                        </a>
                        <?php if (!isLoggedIn()): ?>
                            <a href="<?php echo getBaseUrl() . (isLoggedIn() && hasRole('organizer') ? 'organizer/dashboard.php' : 'login.php'); ?>" class="btn btn-outline-dark btn-lg">
                                Host an event
                            </a>
                        <?php endif; ?>
                    </div>
                    <form class="hero-search" action="<?php echo getBaseUrl(); ?>browse_events.php" method="GET" role="search">
                        <label class="visually-hidden" for="homeEventSearch">Search events</label>
                        <i class="fas fa-search" aria-hidden="true"></i>
                        <input id="homeEventSearch" class="form-control" type="search" name="search" placeholder="Search events or categories">
                        <label class="visually-hidden" for="homeEventDate">From date</label>
                        <input id="homeEventDate" type="date" name="date_from" aria-label="From date">
                        <label class="visually-hidden" for="homeEventCity">City</label>
                        <input id="homeEventCity" type="search" name="city" placeholder="City">
                        <button class="btn btn-primary" type="submit">Find events</button>
                    </form>
                </div>
        </div>
        </div>
    </header>

    <section class="how-it-works" id="how-it-works">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <div class="how-step">
                        <span class="how-step-number">01</span>
                        <div>
                            <h3>Find your kind of event</h3>
                            <p>Search by name, date, place or price.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="how-step">
                        <span class="how-step-number">02</span>
                        <div>
                            <h3>Book your place</h3>
                            <p>Choose an event and reserve a ticket.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="how-step">
                        <span class="how-step-number">03</span>
                        <div>
                            <h3>Keep your plans together</h3>
                            <p>Your bookings and tickets stay in one place.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="category-section" id="popular-categories">
        <div class="container">
            <div class="home-section-heading"><p class="section-eyebrow">Go where your curiosity takes you</p><h2>Explore by interest</h2><p class="home-section-intro">From live music to hands-on learning, find events that feel like you.</p></div>
            <div class="category-grid" aria-label="Browse event categories">
                <?php $categoryIconPaths = ['Arts & Culture'=>'M4 4h16v16H4zM8 8h8v8H8z','Community'=>'M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2m6-10a4 4 0 1 0 0-8 4 4 0 0 0 0 8','Food & Drink'=>'M4 3v7a4 4 0 0 0 8 0V3M8 3v18m12-9h-5V3h5v18','Health & Wellness'=>'M20 5a5 5 0 0 0-7 0l-1 1-1-1a5 5 0 0 0-7 7l8 8 8-8a5 5 0 0 0 0-7','Learning'=>'M2 8l10-5 10 5-10 5L2 8zm4 2v6c4 3 8 3 12 0v-6','Music'=>'M9 18V5l12-2v13M9 18a3 3 0 1 1-3-3 3 3 0 0 1 3 3','Nightlife'=>'M12 3v7m-5-5 5 5 5-5M5 13h14l-2 8H7l-2-8z','Sports'=>'M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm-4 3 4 3 4-3','Technology'=>'M4 4h16v12H4zM8 20h8m-4-4v4','Other'=>'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18zm0 5v4m0 4h.01']; foreach (getEventCategories() as $category): ?><a class="category-tile" href="<?php echo getBaseUrl(); ?>browse_events.php?category=<?php echo rawurlencode($category); ?>"><svg class="category-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="<?php echo $categoryIconPaths[$category] ?? $categoryIconPaths['Other']; ?>" stroke-linecap="round" stroke-linejoin="round"/></svg><span><?php echo htmlspecialchars($category); ?></span></a><?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Upcoming Events Section -->
    <section class="home-section">
        <div class="container">
            <div class="home-section-heading d-flex justify-content-between align-items-end gap-3">
                <div>
                    <p class="section-eyebrow">On the calendar</p>
                    <h2>Coming up</h2>
                </div>
                <a href="<?php echo getBaseUrl(); ?>browse_events.php" class="btn btn-outline-primary">
                    Browse all <i class="fas fa-arrow-right ms-1"></i>
                </a>
            </div>
            
            <?php if (empty($upcomingEvents)): ?>
                <div class="empty-state home-empty-state">
                    <div class="empty-state-art" aria-hidden="true"><span><i class="far fa-calendar"></i></span><i class="fas fa-sparkles"></i></div>
                    <div class="empty-state-copy"><p class="section-eyebrow">A little breathing room</p><h3>The calendar is open for something new.</h3>
                    <p class="text-muted mb-4">There are no upcoming published events right now. Browse by interest or check back when organisers add what’s next.</p>
                    <a href="<?php echo getBaseUrl(); ?>browse_events.php" class="btn btn-primary">Explore all events <i class="fas fa-arrow-right ms-2"></i></a></div>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php $usedHomeImages = []; foreach ($upcomingEvents as $event): $eventCardImage = getUniqueEventImageUrl($event['event_id'], $event['cover_image_path'] ?? null, $usedHomeImages); ?>
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
                                        <i class="fas fa-user me-1"></i>By <?php echo htmlspecialchars($event['organizer_name']); ?>
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
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="organizer-cta">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <p class="section-eyebrow text-white-50">For organizers</p>
                    <h2>Putting an event together?</h2>
                    <p>Sign in to create events, manage bookings and keep track of your attendees.</p>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <a href="<?php echo getBaseUrl() . (isLoggedIn() && hasRole('organizer') ? 'organizer/dashboard.php' : 'login.php'); ?>" class="btn btn-light btn-lg">
                        Host an event <i class="fas fa-arrow-right ms-2"></i>
                    </a>
                </div>
            </div>
        </div>
    </section>

    </main>
    <?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
