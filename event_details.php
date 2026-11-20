<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$eventId = filter_var($_GET['event_id'] ?? null, FILTER_VALIDATE_INT);
if (!$eventId || $eventId < 1) { http_response_code(404); exit('Event not found.'); }
$event = fetchOne("SELECT e.*, o.full_name AS organizer_name, o.company_name, v.venue_name, v.address, v.city, v.amenities
    FROM Events_007 e JOIN Organizers_007 o ON o.organizer_id = e.organizer_id
    JOIN Venues_007 v ON v.venue_id = e.venue_id WHERE e.event_id = ? AND e.event_status = 'published' AND e.event_date > CURRENT_TIMESTAMP", [$eventId]);
if (!$event) { http_response_code(404); exit('This event is not available.'); }

$event['available_tickets'] = 0;
try {
    $available = fetchOne("SELECT GREATEST(0, ? -
        (SELECT COUNT(*) FROM Tickets_007 t WHERE t.event_id = ? AND t.ticket_status NOT IN ('cancelled','expired')) -
        COALESCE((SELECT SUM(bi.quantity) FROM BookingItems_007 bi JOIN BookingOrders_007 b ON b.booking_id = bi.booking_id
            WHERE b.event_id = ? AND b.status = 'pending' AND b.expires_at > CURRENT_TIMESTAMP), 0)) AS available",
        [$event['max_capacity'], $eventId, $eventId]);
    $event['available_tickets'] = (int)($available['available'] ?? 0);
} catch (Throwable $e) {
    $event['available_tickets'] = getAvailableTickets($eventId);
}

$ticketTypes = [];
try {
    $ticketTypes = fetchAll("SELECT t.ticket_type_id, t.type_name, t.description, t.price_minor, t.quantity,
        GREATEST(0, t.quantity
          - (SELECT COUNT(*) FROM Tickets_007 x WHERE x.ticket_type_id = t.ticket_type_id AND x.ticket_status NOT IN ('cancelled','expired'))
          - COALESCE((SELECT SUM(bi.quantity) FROM BookingItems_007 bi JOIN BookingOrders_007 b ON b.booking_id = bi.booking_id
              WHERE bi.ticket_type_id = t.ticket_type_id AND b.status = 'pending' AND b.expires_at > CURRENT_TIMESTAMP), 0)) AS available
        FROM TicketTypes_007 t WHERE t.event_id = ? AND t.is_active = TRUE
          AND (t.sales_start IS NULL OR t.sales_start <= CURRENT_TIMESTAMP)
          AND (t.sales_end IS NULL OR t.sales_end > CURRENT_TIMESTAMP)
        ORDER BY t.price_minor, t.ticket_type_id", [$eventId]);
} catch (Throwable $e) {
    // The legacy event price remains available to show if the ticket-category migration is pending.
}
$pageTitle = $event['event_name'] . ' | EventMS';
$eventImage = getEventImageUrl($event['event_id'], $event['cover_image_path'] ?? null, $event['event_category'] ?? null);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?php echo htmlspecialchars(truncateText($event['event_description'] ?? '', 155)); ?>">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.9/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-yEPZ7jP9S0QKiUQ0m3jjsO4ProIW7wvR9gXkI7JOGa2XVsw53Yke2bsdkBbjmmiX" crossorigin="anonymous">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo getAssetUrl('css/style.css'); ?>" rel="stylesheet">
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>
<main class="event-details-page">
    <div class="container py-4 py-lg-5">
        <a class="event-back-link" href="browse_events.php"><i class="fas fa-arrow-left me-2" aria-hidden="true"></i>Back to explore</a>
        <div class="event-detail-hero mt-3">
            <img src="<?php echo htmlspecialchars($eventImage); ?>" alt="" fetchpriority="high">
            <div class="event-detail-image-overlay"></div>
            <span class="event-detail-category"><?php echo htmlspecialchars($event['event_category'] ?? 'Event'); ?></span>
            <div class="event-detail-hero-copy"><p class="event-detail-kicker">An experience to look forward to</p><h1><?php echo htmlspecialchars($event['event_name']); ?></h1><p class="mb-0"><i class="fas fa-user me-2" aria-hidden="true"></i><?php echo htmlspecialchars($event['organizer_name']); ?></p></div>
        </div>
        <div class="row g-4 g-xl-5 mt-1">
            <div class="col-lg-8">
                <section class="event-detail-section"><p class="section-eyebrow">The details</p><h2>About this event</h2><div class="event-description"><?php echo nl2br(htmlspecialchars($event['event_description'] ?: 'More information about this event will be shared by the organizer.')); ?></div></section>
                <section class="event-facts-grid" aria-label="Event information">
                    <div class="event-fact"><span class="event-fact-icon"><i class="far fa-calendar" aria-hidden="true"></i></span><div><small>Date and time</small><strong><?php echo htmlspecialchars(date('D, M j, Y', strtotime($event['event_date']))); ?></strong><span><?php echo htmlspecialchars(date('g:i A', strtotime($event['event_date']))); ?><?php if (!empty($event['end_date'])): ?> - <?php echo htmlspecialchars(date('g:i A', strtotime($event['end_date']))); ?><?php endif; ?></span></div></div>
                    <div class="event-fact"><span class="event-fact-icon"><i class="fas fa-location-dot" aria-hidden="true"></i></span><div><small>Location</small><strong><?php echo htmlspecialchars($event['venue_name']); ?></strong><span><?php echo htmlspecialchars(trim($event['address'] . ', ' . $event['city'], ', ')); ?></span></div></div>
                    <div class="event-fact"><span class="event-fact-icon"><i class="fas fa-ticket" aria-hidden="true"></i></span><div><small>Availability</small><strong><?php echo (int)$event['available_tickets']; ?> spots left</strong><span>Limited by venue capacity</span></div></div>
                    <div class="event-fact"><span class="event-fact-icon"><i class="fas fa-user-group" aria-hidden="true"></i></span><div><small>Hosted by</small><strong><?php echo htmlspecialchars($event['organizer_name']); ?></strong><span><?php echo htmlspecialchars($event['company_name'] ?: 'Event organizer'); ?></span></div></div>
                </section>
                <?php if (!empty($event['amenities'])): ?><section class="event-detail-section mt-5"><p class="section-eyebrow">At the venue</p><h2>Good to know</h2><p><?php echo nl2br(htmlspecialchars($event['amenities'])); ?></p></section><?php endif; ?>
            </div>
            <aside class="col-lg-4" aria-label="Tickets and booking">
                <div class="ticket-selection-card">
                    <p class="section-eyebrow">Your place awaits</p><h2>Tickets</h2>
                    <?php if ($ticketTypes): ?>
                        <div class="ticket-options"><?php foreach ($ticketTypes as $ticketType): ?><div class="ticket-option"><div><strong><?php echo htmlspecialchars($ticketType['type_name']); ?></strong><?php if (!empty($ticketType['description'])): ?><small><?php echo htmlspecialchars($ticketType['description']); ?></small><?php endif; ?><small><?php echo (int)$ticketType['available']; ?> available</small></div><strong class="ticket-option-price"><?php echo (int)$ticketType['price_minor'] === 0 ? 'Free' : 'GHS ' . number_format(((int)$ticketType['price_minor']) / 100, 2); ?></strong></div><?php endforeach; ?></div>
                    <?php else: ?>
                        <div class="ticket-option"><div><strong>General admission</strong><small><?php echo (int)$event['available_tickets']; ?> available</small></div><strong class="ticket-option-price"><?php echo ((float)$event['ticket_price']) <= 0 ? 'Free' : htmlspecialchars(formatCurrency($event['ticket_price'])); ?></strong></div>
                    <?php endif; ?>
                    <?php if ((int)$event['available_tickets'] > 0): ?>
                        <?php if (isLoggedIn() && hasRole('attendee')): ?><a class="btn btn-primary btn-lg w-100 mt-4" href="attendee/book_ticket.php?event_id=<?php echo (int)$event['event_id']; ?>">Choose tickets <i class="fas fa-arrow-right ms-2" aria-hidden="true"></i></a>
                        <?php else: ?><a class="btn btn-primary btn-lg w-100 mt-4" href="login.php">Sign in to book <i class="fas fa-arrow-right ms-2" aria-hidden="true"></i></a><p class="small text-muted text-center mt-2 mb-0">Sign in as an attendee to reserve your tickets.</p><?php endif; ?>
                    <?php else: ?><button class="btn btn-secondary w-100 mt-4" disabled>Sold out</button><?php endif; ?>
                    <div class="secure-booking-note"><i class="fas fa-shield-halved" aria-hidden="true"></i><span>Clear booking details before you confirm. Payment methods appear at checkout when enabled.</span></div>
                </div>
            </aside>
        </div>
    </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
</body></html>
