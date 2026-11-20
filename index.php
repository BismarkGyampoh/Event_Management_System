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
            LIMIT 6";
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
    <title><?php echo $pageTitle; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.9/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-yEPZ7jP9S0QKiUQ0m3jjsO4ProIW7wvR9gXkI7JOGa2XVsw53Yke2bsdkBbjmmiX" crossorigin="anonymous">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo getAssetUrl('css/style.css'); ?>" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/includes/header.php'; ?>

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
                        <input id="homeEventSearch" class="form-control" type="search" name="search" placeholder="Search by event, category or city">
                        <button class="btn btn-primary" type="submit">Find events</button>
                    </form>
                </div>
                <div class="col-lg-5">
                    <div class="hero-visual" aria-label="A selection of event photography">
                        <div class="hero-photo-board">
                            <?php $heroEvent = $upcomingEvents[0] ?? null; $secondHeroEvent = $upcomingEvents[1] ?? null; ?>
                            <a class="hero-photo hero-photo-main" href="<?php echo $heroEvent ? 'event_details.php?event_id=' . (int)$heroEvent['event_id'] : 'browse_events.php'; ?>" aria-label="<?php echo $heroEvent ? 'Explore ' . htmlspecialchars($heroEvent['event_name']) : 'Explore upcoming events'; ?>">
                                <img src="<?php echo htmlspecialchars($heroEvent ? getEventImageUrl($heroEvent['event_id'], $heroEvent['cover_image_path'] ?? null, $heroEvent['event_category'] ?? null) : getEventImageUrl(4, null, 'Music')); ?>" alt="" fetchpriority="high">
                                <span class="hero-image-caption"><?php echo $heroEvent ? htmlspecialchars($heroEvent['event_category'] ?? 'Upcoming event') : 'Find something worth going out for'; ?></span>
                            </a>
                            <a class="hero-photo hero-photo-small hero-photo-top" href="browse_events.php?category=<?php echo rawurlencode('Food & Drink'); ?>" aria-label="Explore food and drink events"><img src="<?php echo htmlspecialchars(getEventImageUrl(11, null, 'Food & Drink')); ?>" alt="" loading="lazy"></a>
                            <a class="hero-photo hero-photo-small hero-photo-bottom" href="browse_events.php?category=<?php echo rawurlencode('Technology'); ?>" aria-label="Explore technology events"><img src="<?php echo htmlspecialchars($secondHeroEvent ? getEventImageUrl($secondHeroEvent['event_id'], $secondHeroEvent['cover_image_path'] ?? null, $secondHeroEvent['event_category'] ?? null) : getEventImageUrl(21, null, 'Technology')); ?>" alt="" loading="lazy"></a>
                            <?php if ($heroEvent): ?><a class="hero-event-note" href="event_details.php?event_id=<?php echo (int)$heroEvent['event_id']; ?>"><span>Coming up</span><strong><?php echo htmlspecialchars($heroEvent['event_name']); ?></strong><small><?php echo htmlspecialchars($heroEvent['city']); ?></small></a><?php endif; ?>
                        </div>
                    </div>
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
                <?php foreach (getEventCategories() as $category): ?><a class="category-tile" href="<?php echo getBaseUrl(); ?>browse_events.php?category=<?php echo rawurlencode($category); ?>"><img class="category-tile-image" src="<?php echo htmlspecialchars(getEventImageUrl(0, null, $category)); ?>" alt="" loading="lazy" decoding="async"><span class="category-tile-shade" aria-hidden="true"></span><span class="category-tile-label"><?php echo htmlspecialchars($category); ?><i class="fas fa-arrow-right" aria-hidden="true"></i></span></a><?php endforeach; ?>
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
                    <?php foreach ($upcomingEvents as $event): ?>
                        <div class="col-xl-4 col-md-6">
                            <div class="card h-100 border-0 shadow-sm event-card">
                                <div class="event-card-media">
                                    <img class="event-card-image" src="<?php echo htmlspecialchars(getEventImageUrl($event['event_id'], $event['cover_image_path'] ?? null, $event['event_category'] ?? null)); ?>" alt="" loading="lazy" decoding="async" referrerpolicy="no-referrer">
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
                                    <h5 class="card-title fw-bold"><a href="<?php echo getBaseUrl(); ?>event_details.php?event_id=<?php echo (int)$event['event_id']; ?>"><?php echo htmlspecialchars($event['event_name']); ?></a></h5>
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
                                        <span class="fw-bold text-primary"><?php echo formatCurrency($event['ticket_price']); ?></span>
                                        <span class="badge <?php echo $event['available_tickets'] > 0 ? 'bg-success' : 'bg-danger'; ?>">
                                            <?php echo $event['available_tickets'] > 0 ? $event['available_tickets'] . ' available' : 'Sold Out'; ?>
                                        </span>
                                    </div>
                                        <?php if ($event['available_tickets'] > 0): ?>
                                        <?php if (isLoggedIn() && hasRole('attendee')): ?>
                                            <a href="<?php echo getBaseUrl(); ?>event_details.php?event_id=<?php echo (int)$event['event_id']; ?>" class="btn btn-primary w-100 mt-2">
                                                <i class="fas fa-arrow-right me-1"></i>View event
                                            </a>
                                        <?php else: ?>
                                            <a href="<?php echo getBaseUrl(); ?>event_details.php?event_id=<?php echo (int)$event['event_id']; ?>" class="btn btn-primary w-100 mt-2">
                                                <i class="fas fa-arrow-right me-1"></i>View event
                                            </a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <button class="btn btn-secondary w-100 mt-2" disabled>Sold Out</button>
                                    <?php endif; ?>
                                </div>
                            </div>
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

    <?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
