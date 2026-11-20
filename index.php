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
                   (e.max_capacity - (SELECT COUNT(*) FROM Tickets_007 WHERE event_id = e.event_id AND ticket_status != 'cancelled')) as available_tickets
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
                        <a class="nav-link active" href="<?php echo getBaseUrl(); ?>">
                            <i class="fas fa-home me-1"></i>Home
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo getBaseUrl(); ?>browse_events.php">
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

    <header class="hero-section">
        <div class="container">
            <div class="row align-items-center g-5">
                <div class="col-lg-7">
                    <p class="hero-eyebrow">A local calendar for Ghana</p>
                    <h1>See what’s happening. Make a plan.</h1>
                    <p class="lead mb-4">Concerts, workshops, community gatherings and more. Find an event, choose your date, and book your spot.</p>
                    <div class="hero-actions d-flex flex-wrap gap-3">
                        <a href="<?php echo getBaseUrl(); ?>browse_events.php" class="btn btn-primary btn-lg">
                            Browse events <i class="fas fa-arrow-right ms-2"></i>
                        </a>
                        <?php if (!isLoggedIn()): ?>
                            <a href="<?php echo getBaseUrl(); ?>login.php" class="btn btn-outline-dark btn-lg">
                                Organizer sign in
                            </a>
                        <?php endif; ?>
                    </div>
                    <form class="hero-search" action="<?php echo getBaseUrl(); ?>browse_events.php" method="GET" role="search">
                        <label class="visually-hidden" for="homeEventSearch">Search events</label>
                        <i class="fas fa-search" aria-hidden="true"></i>
                        <input id="homeEventSearch" class="form-control" type="search" name="search" placeholder="Try ‘live music’ or ‘Accra’">
                        <button class="btn btn-primary" type="submit">Find events</button>
                    </form>
                </div>
                <div class="col-lg-5">
                    <div class="hero-visual" aria-hidden="true">
                        <div class="event-poster">
                            <div class="poster-top">
                                <span>EVENTMS / GHANA</span>
                                <span>GHANA</span>
                            </div>
                            <div class="poster-art"></div>
                            <h2 class="poster-title">Make room<br>for going out.</h2>
                            <div class="poster-bottom">
                                <span>FIND YOUR NEXT EVENT</span>
                                <span>01 — 03</span>
                            </div>
                        </div>
                        <p class="hero-caption">A good plan starts with knowing what’s on.</p>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <section class="how-it-works">
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
                <div class="empty-state">
                    <span class="empty-state-mark"><i class="far fa-calendar"></i></span>
                    <h3>No events on the calendar just yet</h3>
                    <p class="text-muted mb-3">Check back soon, or browse all events to see what’s available.</p>
                    <a href="<?php echo getBaseUrl(); ?>browse_events.php" class="btn btn-outline-primary">Browse events</a>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($upcomingEvents as $event): ?>
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
                                            <a href="<?php echo getBaseUrl(); ?>attendee/book_ticket.php?event_id=<?php echo (int)$event['event_id']; ?>" class="btn btn-primary w-100 mt-2">
                                                <i class="fas fa-ticket-alt me-1"></i>Book Now
                                            </a>
                                        <?php else: ?>
                                            <a href="<?php echo getBaseUrl(); ?>login.php" class="btn btn-primary w-100 mt-2">
                                                <i class="fas fa-ticket-alt me-1"></i>Sign in to book
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
                    <a href="<?php echo getBaseUrl(); ?>login.php" class="btn btn-light btn-lg">
                        Organizer sign in <i class="fas fa-arrow-right ms-2"></i>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <footer class="bg-dark text-white py-4">
        <div class="container">
            <div class="row align-items-center gy-3">
                <div class="col-md-7">
                    <a href="<?php echo getBaseUrl(); ?>" class="text-white fw-semibold">
                        <i class="fas fa-calendar-alt me-2"></i>EventMS
                    </a>
                    <p class="small text-muted mb-0 mt-1">Events, people and places worth making time for.</p>
                </div>
                <div class="col-md-5 text-md-end">
                    <a href="<?php echo getBaseUrl(); ?>browse_events.php" class="text-white me-3">Browse events</a>
                    <a href="<?php echo getBaseUrl(); ?>login.php" class="text-white">Organizer sign in</a>
                </div>
            </div>
            <hr class="border-secondary my-3">
            <div class="text-muted small">
                &copy; <?php echo date('Y'); ?> EventMS
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.9/dist/js/bootstrap.bundle.min.js" integrity="sha384-WyOxWvTyr1QaMPfiPJSv76iReVthorl5uWlFtZPyK0IEB0hGzo9y5Bp9mYanr8Bu" crossorigin="anonymous"></script>
    <script src="<?php echo getAssetUrl('js/script.js'); ?>"></script>
</body>
</html>
