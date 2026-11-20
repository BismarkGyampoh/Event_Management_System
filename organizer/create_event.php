<?php
/**
 * Create Event - Organizer Page
 * Event Management System 007
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(['organizer']);

$pageTitle = 'Create Event - Organizer Dashboard';
$organizerId = getCurrentUserId();

$message = '';
$messageType = '';

// Get venues for dropdown
try {
    $venues = fetchAll("SELECT * FROM Venues_007 ORDER BY venue_name");
} catch (Exception $e) {
    $venues = [];
}

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

        if ($eventName === '' || !in_array($eventCategory, getEventCategories(), true)
            || $eventDate === null || ($endDateRaw !== '' && $endDate === null)
            || ($endDate !== null && $endDate < $eventDate) || !$venue
            || !preg_match('/\A(?:0|[1-9][0-9]{0,7})(?:\.[0-9]{1,2})?\z/', $ticketPriceRaw)
            || $ticketPrice === false || $ticketPrice < 0
            || $maxCapacity === false || $maxCapacity < 1 || $maxCapacity > (int)$venue['capacity']
            || !in_array($eventStatus, ['draft', 'published'], true)
            || ($eventStatus === 'published' && $eventDate <= date('Y-m-d H:i:s'))) {
            $message = 'Enter a valid future start time, optional end time after it, a nonnegative price, and capacity within the venue limit.';
            $messageType = 'danger';
        } else {
            $pdo = getDbConnection();
            $coverImagePath = null;
            try {
                $pdo->beginTransaction();
                $coverImagePath = saveEventCoverUpload($_FILES['cover_image'] ?? null);
                $sql = "INSERT INTO Events_007 
                        (organizer_id, venue_id, event_name, event_description, event_category, cover_image_path, event_date, end_date, ticket_price, max_capacity, event_status) 
                        VALUES (:organizer_id, :venue_id, :event_name, :event_description, :event_category, :cover_image_path, :event_date, :end_date, :ticket_price, :max_capacity, :event_status)
                        RETURNING event_id";
                $stmt = getDbConnection()->prepare($sql);
                $stmt->execute([
                    ':organizer_id' => $organizerId,
                    ':venue_id' => $venueId,
                    ':event_name' => $eventName,
                    ':event_description' => $eventDescription,
                    ':event_category' => $eventCategory,
                    ':cover_image_path' => $coverImagePath,
                    ':event_date' => $eventDate,
                    ':end_date' => $endDate,
                    ':ticket_price' => $ticketPrice,
                    ':max_capacity' => $maxCapacity,
                    ':event_status' => $eventStatus
                ]);
                
                $newEventId = $stmt->fetchColumn();
                $ticketType = $pdo->prepare("INSERT INTO TicketTypes_007 (event_id, type_name, price_minor, quantity) VALUES (?, 'General Admission', ROUND(CAST(? AS NUMERIC) * 100)::BIGINT, ?)");
                $ticketType->execute([$newEventId, (string)($_POST['ticket_price'] ?? ''), $maxCapacity]);
                logAuditAction($organizerId, 'organizer', 'INSERT', 'Events_007', $newEventId, [], [
                    'event_name' => $eventName, 'event_category' => $eventCategory,
                    'venue_id' => $venueId, 'event_date' => $eventDate,
                    'end_date' => $endDate, 'ticket_price' => $ticketPrice,
                    'max_capacity' => $maxCapacity, 'event_status' => $eventStatus
                ]);
                $pdo->commit();
                
                $message = 'Event created successfully!';
                $messageType = 'success';
                
                // Redirect to my events page after short delay
                header("Refresh: 2; URL=my_events.php");
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Organizer event creation failed: ' . $e->getMessage());
                $message = 'We could not create this event. Please try again.';
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
                    <h1 class="h2"><i class="fas fa-plus-circle me-2"></i>Create New Event</h1>
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
                            <div class="card-header bg-white">
                                <h5 class="mb-0">Event Details</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" enctype="multipart/form-data">
                                    <?php echo csrfTokenField(); ?>

                                    <div class="form-section-heading"><span>01</span><div><strong>Event basics</strong><small>Give people a clear first impression.</small></div></div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label" for="event-name">Event name *</label>
                                        <input id="event-name" type="text" class="form-control" name="event_name" required 
                                               placeholder="Enter event name" value="<?php echo htmlspecialchars($_POST['event_name'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label" for="event-description">Description</label>
                                        <textarea id="event-description" class="form-control" name="event_description" rows="4" 
                                                  placeholder="Describe your event"><?php echo htmlspecialchars($_POST['event_description'] ?? ''); ?></textarea>
                                    </div>

                                    <div class="form-section-heading"><span>02</span><div><strong>Look and category</strong><small>Help the right audience find your event.</small></div></div>
                                    <div class="row g-3 mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label" for="eventCategory">Category *</label>
                                            <select class="form-select" id="eventCategory" name="event_category" required>
                                                <?php foreach (getEventCategories() as $category): ?>
                                                    <option value="<?php echo htmlspecialchars($category); ?>" <?php echo (($_POST['event_category'] ?? 'Other') === $category) ? 'selected' : ''; ?>><?php echo htmlspecialchars($category); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label" for="eventCover">Cover photo</label>
                                            <input class="form-control" type="file" id="eventCover" name="cover_image" accept="image/jpeg,image/png,image/webp">
                                            <small class="text-muted">JPEG, PNG, or WebP, up to 5 MB.</small>
                                        </div>
                                    </div>
                                    
                                    <div class="form-section-heading"><span>03</span><div><strong>When and where</strong><small>Set the venue and event schedule.</small></div></div>
                                    <div class="row g-3 mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label" for="event-venue">Venue *</label>
                                            <select id="event-venue" class="form-select" name="venue_id" required>
                                                <option value="">Select a venue</option>
                                                <?php foreach ($venues as $venue): ?>
                                                    <option value="<?php echo $venue['venue_id']; ?>" 
                                                            <?php echo (($_POST['venue_id'] ?? '') == $venue['venue_id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($venue['venue_name']); ?> 
                                                        (<?php echo htmlspecialchars($venue['city']); ?> - Capacity: <?php echo $venue['capacity']; ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label class="form-label" for="event-status">Publication status</label>
                                            <select id="event-status" class="form-select" name="event_status">
                                                <option value="draft" <?php echo (($_POST['event_status'] ?? 'draft') === 'draft') ? 'selected' : ''; ?>>Draft</option>
                                                <option value="published" <?php echo (($_POST['event_status'] ?? '') === 'published') ? 'selected' : ''; ?>>Published</option>
                                            </select>
                                            <small class="text-muted">Published events are visible to attendees</small>
                                        </div>
                                    </div>
                                    
                                    <div class="row g-3 mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label" for="event-start">Start date and time *</label>
                                            <input id="event-start" type="datetime-local" class="form-control" name="event_date" required 
                                                   value="<?php echo htmlspecialchars($_POST['event_date'] ?? ''); ?>">
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label class="form-label" for="event-end">End date and time</label>
                                            <input id="event-end" type="datetime-local" class="form-control" name="end_date" 
                                                   value="<?php echo htmlspecialchars($_POST['end_date'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="form-section-heading"><span>04</span><div><strong>Tickets and capacity</strong><small>You can add more ticket categories after creating the event.</small></div></div>
                                    <div class="row g-3 mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label" for="ticket-price">Starting ticket price (GHS) *</label>
                                            <input id="ticket-price" type="number" class="form-control" name="ticket_price" required 
                                                   min="0" step="0.01" placeholder="0.00" 
                                                   value="<?php echo htmlspecialchars($_POST['ticket_price'] ?? ''); ?>">
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label class="form-label" for="max-capacity">Event capacity *</label>
                                            <input id="max-capacity" type="number" class="form-control" name="max_capacity" required 
                                                   min="1" placeholder="100" 
                                                   value="<?php echo htmlspecialchars($_POST['max_capacity'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-1"></i>Create Event
                                        </button>
                                        <a href="my_events.php" class="btn btn-outline-secondary">
                                            <i class="fas fa-times me-1"></i>Cancel
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="fas fa-lightbulb me-2"></i>Tips</h5>
                            </div>
                            <div class="card-body">
                                <ul class="mb-0">
                                    <li class="mb-2">Choose a descriptive event name that attracts attention.</li>
                                    <li class="mb-2">Provide a detailed description to help attendees understand your event.</li>
                                    <li class="mb-2">Set an appropriate ticket price based on your target audience.</li>
                                    <li class="mb-2">Consider the venue capacity when setting max tickets.</li>
                                    <li class="mb-2">Start with "Draft" status to preview before publishing.</li>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="card border-0 shadow-sm mt-3">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="fas fa-building me-2"></i>Available Venues</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($venues)): ?>
                                    <p class="text-muted">No venues available.</p>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($venues as $venue): ?>
                                            <div class="list-group-item px-0">
                                                <strong><?php echo htmlspecialchars($venue['venue_name']); ?></strong>
                                                <br><small class="text-muted">
                                                    <?php echo htmlspecialchars($venue['city']); ?> | 
                                                    Capacity: <?php echo number_format($venue['capacity']); ?>
                                                </small>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
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
