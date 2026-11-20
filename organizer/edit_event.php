<?php
/**
 * Edit Event - Organizer Page
 * Event Management System 007
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(['organizer']);

$pageTitle = 'Edit Event - Organizer Dashboard';
$organizerId = getCurrentUserId();

$message = '';
$messageType = '';

// Get event ID
$eventId = (int)($_GET['id'] ?? 0);

if ($eventId <= 0) {
    setFlashMessage('danger', 'Invalid event ID.');
    redirect('my_events.php');
}

// Get event details
$event = fetchOne("
    SELECT e.*, v.venue_name, v.city
    FROM Events_007 e
    INNER JOIN Venues_007 v ON e.venue_id = v.venue_id
    WHERE e.event_id = :event_id AND e.organizer_id = :organizer_id
", [':event_id' => $eventId, ':organizer_id' => $organizerId]);

if (!$event) {
    setFlashMessage('danger', 'Event not found or you do not have permission to edit it.');
    redirect('my_events.php');
}

// Get venues for dropdown
try {
    $venues = fetchAll("SELECT * FROM Venues_007 ORDER BY venue_name");
} catch (Exception $e) {
    $venues = [];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken()) {
        $message = 'Invalid form submission.';
        $messageType = 'danger';
    } else {
        $eventName = sanitize($_POST['event_name'] ?? '');
        $eventDescription = sanitize($_POST['event_description'] ?? '');
        $eventCategory = $_POST['event_category'] ?? 'Other';
        $venueId = (int)($_POST['venue_id'] ?? 0);
        $eventDateRaw = $_POST['event_date'] ?? '';
        $endDateRaw = $_POST['end_date'] ?? '';
        $ticketPriceRaw = trim((string)($_POST['ticket_price'] ?? ''));
        $ticketPrice = filter_var($ticketPriceRaw, FILTER_VALIDATE_FLOAT);
        $maxCapacity = filter_var($_POST['max_capacity'] ?? null, FILTER_VALIDATE_INT);
        $eventStatus = $_POST['event_status'] ?? 'draft';
        $eventDate = parseEventDateTime($eventDateRaw);
        $endDate = $endDateRaw === '' ? null : parseEventDateTime($endDateRaw);
        $venue = $venueId > 0 ? fetchOne('SELECT capacity FROM Venues_007 WHERE venue_id = :venue_id', [':venue_id' => $venueId]) : null;
        $ticketsSold = fetchOne("SELECT COUNT(*) AS count FROM Tickets_007 WHERE event_id = :event_id AND ticket_status NOT IN ('cancelled', 'expired')", [':event_id' => $eventId]);
        $currentStatusAllowsPast = in_array($eventStatus, ['completed', 'cancelled'], true);

        if ($eventName === '' || !in_array($eventCategory, getEventCategories(), true)
            || $eventDate === null || ($endDateRaw !== '' && $endDate === null)
            || ($endDate !== null && $endDate < $eventDate) || !$venue
            || !preg_match('/\A(?:0|[1-9][0-9]{0,7})(?:\.[0-9]{1,2})?\z/', $ticketPriceRaw)
            || $ticketPrice === false || $ticketPrice < 0
            || $maxCapacity === false || $maxCapacity < 1 || $maxCapacity > (int)$venue['capacity']
            || $maxCapacity < (int)($ticketsSold['count'] ?? 0)
            || !in_array($eventStatus, ['draft', 'published', 'cancelled', 'completed'], true)
            || (!$currentStatusAllowsPast && $eventDate <= date('Y-m-d H:i:s'))) {
            $message = 'Enter valid event details. Capacity must fit the venue and existing bookings; active events need a future start time.';
            $messageType = 'danger';
        } else {
            $newCoverImagePath = null;
            try {
                $pdo = getDbConnection();
                $pdo->beginTransaction();
                $newCoverImagePath = saveEventCoverUpload($_FILES['cover_image'] ?? null);
                $oldData = fetchOne('SELECT * FROM Events_007 WHERE event_id = :event_id AND organizer_id = :organizer_id FOR UPDATE', [
                    ':event_id' => $eventId, ':organizer_id' => $organizerId
                ]);
                if (!$oldData) {
                    throw new RuntimeException('Event not found or no longer belongs to your account.');
                }
                $lockedBookingCount = fetchOne("SELECT COUNT(*) AS count FROM Tickets_007 WHERE event_id = :event_id AND ticket_status NOT IN ('cancelled', 'expired')", [':event_id' => $eventId]);
                if ($maxCapacity < (int)($lockedBookingCount['count'] ?? 0)) {
                    throw new RuntimeException('Capacity cannot be lower than the number of active bookings.');
                }
                if ($eventStatus === 'cancelled' && $oldData['event_status'] !== 'cancelled') {
                    $checkedIn = fetchOne("SELECT COUNT(*) AS count FROM Tickets_007 WHERE event_id = :event_id AND ticket_status IN ('checked_in', 'used')", [':event_id' => $eventId]);
                    if ((int)($checkedIn['count'] ?? 0) > 0) {
                        throw new RuntimeException('An event with checked-in attendees cannot be cancelled from this page.');
                    }
                }

                $sql = "UPDATE Events_007 SET 
                        venue_id = :venue_id, event_name = :event_name, event_description = :event_description, 
                        event_category = :event_category, cover_image_path = COALESCE(:cover_image_path, cover_image_path),
                        event_date = :event_date, end_date = :end_date, ticket_price = :ticket_price, 
                        max_capacity = :max_capacity, event_status = :event_status
                        WHERE event_id = :event_id AND organizer_id = :organizer_id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':venue_id' => $venueId,
                    ':event_name' => $eventName,
                    ':event_description' => $eventDescription,
                    ':event_category' => $eventCategory,
                    ':cover_image_path' => $newCoverImagePath,
                    ':event_date' => $eventDate,
                    ':end_date' => $endDate,
                    ':ticket_price' => $ticketPrice,
                    ':max_capacity' => $maxCapacity,
                    ':event_status' => $eventStatus,
                    ':event_id' => $eventId,
                    ':organizer_id' => $organizerId
                ]);
                
                if ($eventStatus === 'cancelled' && $oldData['event_status'] !== 'cancelled') {
                    $pdo->prepare("INSERT INTO PaymentRefunds_007 (marketplace_payment_id, requested_by, amount_minor, reason)
                        SELECT p.marketplace_payment_id, b.organizer_id, b.gross_minor, 'Event cancelled by organizer; administrator refund review required'
                        FROM BookingOrders_007 b JOIN MarketplacePayments_007 p ON p.booking_id = b.booking_id
                        WHERE b.event_id = ? AND b.status = 'paid' AND p.status = 'success'
                        ON CONFLICT (marketplace_payment_id) WHERE status IN ('requested','processing','needs_attention') DO NOTHING")
                        ->execute([$eventId]);
                    $pdo->prepare("UPDATE BookingOrders_007 b SET status = 'refund_pending', updated_at = CURRENT_TIMESTAMP
                        WHERE b.event_id = ? AND b.status = 'paid' AND EXISTS (SELECT 1 FROM MarketplacePayments_007 p WHERE p.booking_id = b.booking_id AND p.status = 'success')")
                        ->execute([$eventId]);
                    $pdo->prepare("UPDATE BookingOrders_007 SET status = 'cancelled', updated_at = CURRENT_TIMESTAMP WHERE event_id = ? AND status = 'pending'")
                        ->execute([$eventId]);
                    $pdo->prepare("UPDATE MarketplacePayments_007 p SET status = 'cancelled' FROM BookingOrders_007 b WHERE b.booking_id = p.booking_id AND b.event_id = ? AND p.status IN ('initializing','pending')")
                        ->execute([$eventId]);
                    $pdo->prepare("UPDATE Tickets_007 SET ticket_status = 'cancelled' WHERE event_id = :event_id AND ticket_status IN ('booked', 'confirmed')")
                        ->execute([':event_id' => $eventId]);
                    $pdo->prepare("UPDATE Payments_007 SET payment_status = CASE WHEN payment_status = 'completed' THEN 'refund_pending' ELSE 'failed' END WHERE ticket_id IN (SELECT ticket_id FROM Tickets_007 WHERE event_id = :event_id) AND payment_status IN ('pending', 'completed')")
                        ->execute([':event_id' => $eventId]);
                    $pdo->prepare("UPDATE EventWaitlist_007 SET status = 'expired' WHERE event_id = :event_id AND status IN ('waiting', 'invited')")
                        ->execute([':event_id' => $eventId]);
                }

                logAuditAction($organizerId, 'organizer', 'UPDATE', 'Events_007', $eventId, $oldData, [
                    'venue_id' => $venueId, 'event_name' => $eventName, 'event_category' => $eventCategory,
                    'cover_image_updated' => $newCoverImagePath !== null, 'event_date' => $eventDate,
                    'end_date' => $endDate, 'ticket_price' => $ticketPrice,
                    'max_capacity' => $maxCapacity, 'event_status' => $eventStatus
                ]);
                $pdo->commit();
                $newCoverImagePath = null;
                
                // Refresh event data
                $event = fetchOne("SELECT * FROM Events_007 WHERE event_id = :event_id", [':event_id' => $eventId]);
                
                $message = 'Event updated successfully!';
                $messageType = 'success';
            } catch (Throwable $e) {
                if (isset($pdo) && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($newCoverImagePath !== null) {
                    $newImageFile = __DIR__ . '/../assets/' . str_replace('/', DIRECTORY_SEPARATOR, $newCoverImagePath);
                    if (is_file($newImageFile)) unlink($newImageFile);
                }
                error_log('Organizer event update failed: ' . $e->getMessage());
                $message = 'We could not update this event. Please review the details and try again.';
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
                            <a class="nav-link" href="my_events.php">
                                <i class="fas fa-calendar me-2"></i>My Events
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="create_event.php">
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
                    <h1 class="h2"><i class="fas fa-edit me-2"></i>Edit Event</h1>
                </div>
                
                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Event Details</h5>
                                <span class="badge bg-<?php echo getStatusBadgeClass($event['event_status']); ?>">
                                    <?php echo ucfirst($event['event_status']); ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <form method="POST" enctype="multipart/form-data">
                                    <?php echo csrfTokenField(); ?>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Event Name *</label>
                                        <input type="text" class="form-control" name="event_name" required 
                                               value="<?php echo htmlspecialchars($event['event_name']); ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Description</label>
                                        <textarea class="form-control" name="event_description" rows="4"><?php echo htmlspecialchars($event['event_description'] ?? ''); ?></textarea>
                                    </div>

                                    <div class="row g-3 mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label" for="eventCategory">Category *</label>
                                            <select class="form-select" id="eventCategory" name="event_category" required>
                                                <?php foreach (getEventCategories() as $category): ?>
                                                    <option value="<?php echo htmlspecialchars($category); ?>" <?php echo ($event['event_category'] ?? 'Other') === $category ? 'selected' : ''; ?>><?php echo htmlspecialchars($category); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label" for="eventCover">Change cover photo</label>
                                            <input class="form-control" type="file" id="eventCover" name="cover_image" accept="image/jpeg,image/png,image/webp">
                                            <small class="text-muted">JPEG, PNG, or WebP, up to 5 MB.</small>
                                        </div>
                                    </div>
                                    
                                    <div class="row g-3 mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Venue *</label>
                                            <select class="form-select" name="venue_id" required>
                                                <?php foreach ($venues as $venue): ?>
                                                    <option value="<?php echo $venue['venue_id']; ?>" 
                                                            <?php echo $event['venue_id'] == $venue['venue_id'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($venue['venue_name']); ?> 
                                                        (<?php echo htmlspecialchars($venue['city']); ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label class="form-label">Event Status</label>
                                            <select class="form-select" name="event_status">
                                                <option value="draft" <?php echo $event['event_status'] === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                                <option value="published" <?php echo $event['event_status'] === 'published' ? 'selected' : ''; ?>>Published</option>
                                                <option value="cancelled" <?php echo $event['event_status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                <option value="completed" <?php echo $event['event_status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="row g-3 mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Start Date & Time *</label>
                                            <input type="datetime-local" class="form-control" name="event_date" required 
                                                   value="<?php echo date('Y-m-d\TH:i', strtotime($event['event_date'])); ?>">
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label class="form-label">End Date & Time</label>
                                            <input type="datetime-local" class="form-control" name="end_date" 
                                                   value="<?php echo $event['end_date'] ? date('Y-m-d\TH:i', strtotime($event['end_date'])) : ''; ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="row g-3 mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Ticket Price (GHS) *</label>
                                            <input type="number" class="form-control" name="ticket_price" required 
                                                   min="0" step="0.01" value="<?php echo $event['ticket_price']; ?>">
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label class="form-label">Max Capacity *</label>
                                            <input type="number" class="form-control" name="max_capacity" required 
                                                   min="1" value="<?php echo $event['max_capacity']; ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-1"></i>Update Event
                                        </button>
                                        <a href="my_events.php" class="btn btn-outline-secondary">
                                            <i class="fas fa-arrow-left me-1"></i>Back
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">Event Stats</h5>
                            </div>
                            <div class="card-body">
                                <?php
                                $ticketsSold = fetchOne("SELECT COUNT(*) as count FROM Tickets_007 WHERE event_id = :event_id AND ticket_status NOT IN ('cancelled', 'expired')", [':event_id' => $eventId])['count'] ?? 0;
                                $revenue = fetchOne("
                                    SELECT COALESCE(SUM(amount), 0) as total 
                                    FROM Payments_007 
                                    WHERE ticket_id IN (SELECT ticket_id FROM Tickets_007 WHERE event_id = :event_id)
                                    AND payment_status = 'completed'
                                ", [':event_id' => $eventId])['total'] ?? 0;
                                ?>
                                <div class="mb-2">
                                    <strong>Tickets Sold:</strong>
                                    <span class="float-end"><?php echo $ticketsSold; ?>/<?php echo $event['max_capacity']; ?></span>
                                </div>
                                <div class="mb-2">
                                    <strong>Revenue:</strong>
                                    <span class="float-end fw-bold text-success"><?php echo formatCurrency($revenue); ?></span>
                                </div>
                                <div>
                                    <strong>Created:</strong>
                                    <span class="float-end"><?php echo formatDate($event['created_at']); ?></span>
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
