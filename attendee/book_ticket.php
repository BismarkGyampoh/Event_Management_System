<?php
/**
 * Book Ticket - Attendee Page
 * Event Management System 007
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(['attendee']);

$pageTitle = 'Book Ticket - Event Management System';
$attendeeId = getCurrentUserId();

$eventId = (int)($_GET['event_id'] ?? 0);

if ($eventId <= 0) {
    setFlashMessage('danger', 'Invalid event.');
    redirect('browse_events.php');
}

// Get event details
$event = fetchOne("
    SELECT e.*, o.full_name AS organizer_name, v.venue_name, v.city, v.address, v.amenities,
           (e.max_capacity - (SELECT COUNT(*) FROM Tickets_007 WHERE event_id = e.event_id AND ticket_status NOT IN ('cancelled', 'expired'))) as available_tickets
    FROM Events_007 e
    INNER JOIN Organizers_007 o ON e.organizer_id = o.organizer_id
    INNER JOIN Venues_007 v ON e.venue_id = v.venue_id
    WHERE e.event_id = ? AND e.event_status = 'published'
", [$eventId]);

if (!$event) {
    setFlashMessage('danger', 'Event not found or not available.');
    redirect('browse_events.php');
}

$marketplaceTypes = [];
$marketplaceEnabled = false;
try {
    $marketplaceTypes = fetchAll("SELECT ticket_type_id, type_name, description, price_minor, quantity FROM TicketTypes_007 t
        WHERE event_id = ? AND is_active = TRUE AND price_minor > 0 AND (sales_start IS NULL OR sales_start <= CURRENT_TIMESTAMP)
        AND (sales_end IS NULL OR sales_end > CURRENT_TIMESTAMP) ORDER BY price_minor, ticket_type_id", [$eventId]);
    $paymentState = fetchOne("SELECT s.payments_enabled, s.commission_bps, a.verification_status FROM PlatformPaymentSettings_007 s
        LEFT JOIN OrganizerPaymentAccounts_007 a ON a.organizer_id = ? WHERE s.settings_id = 1", [$event['organizer_id']]);
    $marketplaceEnabled = isDatabaseBooleanTrue($paymentState['payments_enabled'] ?? false) && $paymentState['commission_bps'] !== null
        && ($paymentState['verification_status'] ?? '') === 'verified' && getenv('PAYSTACK_MODE') === 'test';
} catch (Throwable $e) {
    // The legacy cash reservation remains available if marketplace migrations are not yet installed.
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken()) {
        $message = 'Invalid form submission.';
        $messageType = 'danger';
    } else {
        $paymentMethod = $_POST['payment_method'] ?? '';
        
        if ($paymentMethod !== 'cash') {
            $message = 'Pay-in-person is the only booking option currently available.';
            $messageType = 'danger';
        } elseif ($event['available_tickets'] <= 0) {
            $message = 'Sorry, this event is sold out.';
            $messageType = 'danger';
        } else {
            try {
                $sql = "SELECT * FROM BookTicket_007(CAST(? AS INTEGER), CAST(? AS INTEGER), CAST(? AS VARCHAR))";
                $stmt = getDbConnection()->prepare($sql);
                $stmt->execute([$eventId, $attendeeId, $paymentMethod]);
                $result = $stmt->fetch();
                
                if ($result && isset($result['status']) && $result['status'] === 'Success') {
                    setFlashMessage('warning', $result['message'] . ' Booking reference: ' . $result['ticket_number']);
                    redirect('my_bookings.php');
                } else {
                    $message = $result['message'] ?? 'Error booking ticket. Please try again.';
                    $messageType = 'danger';
                }
            } catch (Exception $e) {
                error_log('Attendee booking error: ' . $e->getMessage());
                $message = 'We could not complete this booking. Please try again.';
                $messageType = 'danger';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link href="<?php echo getAssetUrl('css/main.css'); ?>" rel="stylesheet">
<link rel="icon" type="image/svg+xml" href="<?php echo getAssetUrl('favicon.svg'); ?>">
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <nav class="col-md-3 col-lg-2 d-md-block bg-light sidebar dashboard-sidebar booking-sidebar collapse show" id="sidebarMenu" aria-label="Attendee booking navigation">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="browse_events.php">
                                <i class="fas fa-search me-2"></i>Browse Events
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="my_bookings.php">
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
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 booking-page attendee-booking-page">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom booking-page-header">
                    <div><p class="section-eyebrow mb-1">One step closer</p><h1>Complete your booking</h1></div>
                    <a href="browse_events.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-1"></i>Back to events
                    </a>
                </div>
                
                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <div class="booking-event-card mb-4">
                    <img class="booking-event-image" src="<?php echo htmlspecialchars(getEventImageUrl($event['event_id'], $event['cover_image_path'] ?? null, $event['event_category'] ?? null)); ?>" alt="" fetchpriority="high">
                    <div class="booking-event-copy">
                        <span class="event-category-label"><?php echo htmlspecialchars($event['event_category'] ?? 'Event'); ?></span>
                        <h2><?php echo htmlspecialchars($event['event_name']); ?></h2>
                        <p class="mb-2"><i class="far fa-calendar me-2" aria-hidden="true"></i><?php echo formatDate($event['event_date']); ?><?php if ($event['end_date']): ?> – <?php echo formatDate($event['end_date']); ?><?php endif; ?></p>
                        <p class="mb-2"><i class="fas fa-location-dot me-2" aria-hidden="true"></i><?php echo htmlspecialchars($event['venue_name'] . ', ' . $event['city']); ?></p>
                        <p class="mb-0"><i class="far fa-user me-2" aria-hidden="true"></i>Hosted by <?php echo htmlspecialchars($event['organizer_name']); ?></p>
                    </div>
                </div>

                <div class="row g-4">
                    <div class="col-lg-8">
                        <div class="card booking-main-card">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">Confirm Your Booking</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <?php echo csrfTokenField(); ?>
                                    
                                    <h6 class="fw-bold mb-3">Payment arrangement</h6>
                                    <div class="row g-3 mb-4">
                                        <div class="col-md-8">
                                            <div class="form-check card p-3 booking-choice">
                                                <input class="form-check-input" type="radio" name="payment_method" 
                                                       id="cash" value="cash" checked>
                                                <label class="form-check-label w-100" for="cash">
                                                    <i class="fas fa-money-bill-wave fa-2x me-2"></i>
                                                    <span class="fw-bold">Pay in person</span>
                                                    <small class="d-block text-muted ms-4">Your reservation stays unpaid until the organizer confirms receipt.</small>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        This creates an unpaid reservation, not a paid ticket. The organizer must confirm payment before your ticket is valid for entry.
                                    </div>
                                    
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-success btn-lg" 
                                                <?php echo $event['available_tickets'] <= 0 ? 'disabled' : ''; ?>>
                                            <i class="fas fa-calendar-check me-1"></i>Reserve - <?php echo formatCurrency($event['ticket_price']); ?>
                                        </button>
                                        <a href="browse_events.php" class="btn btn-outline-secondary btn-lg">Cancel</a>
                                    </div>
                                </form>
                                <?php if ($marketplaceEnabled && $marketplaceTypes): ?>
                                <hr class="my-4"><h6 class="fw-bold">Pay online (test mode)</h6>
                                <form method="post" action="checkout.php" class="row g-3 align-items-end">
                                    <?php echo csrfTokenField(); ?><input type="hidden" name="event_id" value="<?php echo (int)$eventId; ?>">
                                    <div class="col-md-7"><label for="ticket_type_id" class="form-label">Ticket type</label><select class="form-select" required name="ticket_type_id" id="ticket_type_id"><?php foreach ($marketplaceTypes as $ticketType): ?><option value="<?php echo (int)$ticketType['ticket_type_id']; ?>"><?php echo htmlspecialchars($ticketType['type_name']); ?> — <?php echo formatCurrency(((int)$ticketType['price_minor']) / 100); ?></option><?php endforeach; ?></select></div>
                                    <div class="col-md-3"><label for="quantity" class="form-label">Quantity</label><input class="form-control" type="number" id="quantity" name="quantity" min="1" max="10" value="1" required></div>
                                    <div class="col-md-2"><button class="btn btn-primary w-100">Pay securely</button></div>
                                    <div class="col-12"><small class="text-muted">Checkout is in test mode. The server verifies payment before tickets are issued.</small></div>
                                </form><?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <!-- Order Summary -->
                        <div class="card booking-summary-card">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="fas fa-shopping-cart me-2"></i>Order Summary</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Ticket Price</span>
                                    <span><?php echo formatCurrency($event['ticket_price']); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Quantity</span>
                                    <span>1</span>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between fw-bold">
                                    <span>Total</span>
                                    <span class="text-success h4 mb-0"><?php echo formatCurrency($event['ticket_price']); ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Availability -->
                        <div class="card border-0 shadow-sm mt-3">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-muted">Available Tickets</span>
                                    <span class="badge <?php echo $event['available_tickets'] > 10 ? 'bg-success' : ($event['available_tickets'] > 0 ? 'bg-warning' : 'bg-danger'); ?> fs-6">
                                        <?php echo $event['available_tickets']; ?>
                                    </span>
                                </div>
                                <progress class="progress progress--capacity mt-2 <?php echo $event['available_tickets'] > 10 ? 'is-healthy' : 'is-low'; ?>" max="100" value="<?php echo min(100, ($event['available_tickets'] / $event['max_capacity']) * 100); ?>" aria-label="Available ticket capacity"></progress>
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
