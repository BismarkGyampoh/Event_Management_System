<?php
/**
 * Check-In / Scan for Entry - Organizer Page
 * Event Management System 007
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(['organizer']);

$pageTitle = 'Check-In / Scan Entry - Organizer Dashboard';
$organizerId = getCurrentUserId();

$message = '';
$messageType = '';

// Get organizer's events
try {
    try {
        $myEvents = fetchAll("
            SELECT event_id, event_name, event_date, venue_id,
                   (SELECT COUNT(*) FROM Tickets_007 WHERE event_id = Events_007.event_id AND ticket_status IN ('confirmed', 'checked_in')) as total_tickets,
                   (SELECT COUNT(*) FROM Tickets_007 WHERE event_id = Events_007.event_id AND ticket_status = 'checked_in') as checked_in
            FROM Events_007
            WHERE organizer_id = :organizer_id AND event_status = 'published' AND event_date >= CURRENT_TIMESTAMP
            ORDER BY event_date ASC
        ", [':organizer_id' => $organizerId]);
    } catch (Exception $refreshError) {
        error_log('Organizer check-in stats refresh error: ' . $refreshError->getMessage());
        $message = 'Ticket checked in, but event statistics could not be refreshed.';
        $messageType = 'warning';
    }
} catch (Exception $e) {
    $myEvents = [];
}

$selectedEventId = (int)($_GET['event_id'] ?? 0);
$ticketInfo = null;

// Handle check-in
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken()) {
        $message = 'Invalid form submission.';
        $messageType = 'danger';
    } else {
        $action = $_POST['action'] ?? '';
        $ticketNumber = sanitize($_POST['ticket_number'] ?? '');
        $eventId = (int)($_POST['event_id'] ?? 0);
        
        if ($action === 'lookup') {
            // Look up ticket
            $ticketInfo = fetchOne("
                SELECT t.ticket_id, t.ticket_number, t.ticket_status, t.booking_date,
                       a.full_name as attendee_name, a.email as attendee_email,
                       e.event_id, e.event_name, e.event_date, e.event_status, e.ticket_price,
                       v.venue_name,
                       p.amount as payment_amount, p.payment_status
                FROM Tickets_007 t
                INNER JOIN Events_007 e ON t.event_id = e.event_id
                INNER JOIN Attendees_007 a ON t.attendee_id = a.attendee_id
                INNER JOIN Venues_007 v ON e.venue_id = v.venue_id
                LEFT JOIN Payments_007 p ON t.ticket_id = p.ticket_id
                WHERE t.ticket_number = :ticket_number AND e.organizer_id = :organizer_id
            ", [':ticket_number' => $ticketNumber, ':organizer_id' => $organizerId]);
            
            if ($ticketInfo) {
                $selectedEventId = $ticketInfo['event_id'];
                if ($ticketInfo['event_status'] !== 'published') {
                    $message = 'This event is cancelled or closed. The ticket cannot be used for entry.';
                    $messageType = 'danger';
                } elseif ($ticketInfo['ticket_status'] === 'checked_in') {
                    $message = 'This ticket has already been checked in!';
                    $messageType = 'warning';
                } elseif ($ticketInfo['payment_status'] !== 'completed'
                    || !in_array($ticketInfo['ticket_status'], ['confirmed', 'checked_in'], true)) {
                    $message = 'Only a confirmed, paid ticket can be checked in.';
                    $messageType = 'danger';
                }
            } else {
                $message = 'Ticket not found or does not belong to your events.';
                $messageType = 'danger';
            }
        } elseif ($action === 'checkin') {
            $ticketId = (int)($_POST['ticket_id'] ?? 0);
            
            // Verify ticket belongs to organizer's event
            $ticket = fetchOne("
                SELECT t.ticket_id, t.ticket_number, t.ticket_status,
                       e.event_name, e.event_id, e.event_status, a.full_name as attendee_name,
                       p.payment_status
                FROM Tickets_007 t
                INNER JOIN Events_007 e ON t.event_id = e.event_id
                INNER JOIN Attendees_007 a ON t.attendee_id = a.attendee_id
                INNER JOIN Payments_007 p ON p.ticket_id = t.ticket_id
                WHERE t.ticket_id = :ticket_id AND e.organizer_id = :organizer_id
            ", [':ticket_id' => $ticketId, ':organizer_id' => $organizerId]);
            
            if ($ticket) {
                if ($ticket['event_status'] !== 'published') {
                    $message = 'This event is cancelled or closed. The ticket cannot be used for entry.';
                    $messageType = 'danger';
                } elseif ($ticket['ticket_status'] === 'checked_in') {
                    $message = 'Ticket already checked in!';
                    $messageType = 'warning';
                } elseif ($ticket['payment_status'] !== 'completed' || $ticket['ticket_status'] !== 'confirmed') {
                    $message = 'Only a confirmed, paid ticket can be checked in.';
                    $messageType = 'danger';
                } else {
                    try {
                        $sql = "
                            UPDATE Tickets_007 t
                            SET ticket_status = 'checked_in', check_in_time = CURRENT_TIMESTAMP
                            WHERE t.ticket_id = :ticket_id
                              AND t.ticket_status = 'confirmed'
                              AND EXISTS (
                                  SELECT 1 FROM Payments_007 p
                                  WHERE p.ticket_id = t.ticket_id AND p.payment_status = 'completed'
                              )
                              AND EXISTS (
                                  SELECT 1 FROM Events_007 e
                                  WHERE e.event_id = t.event_id AND e.organizer_id = :organizer_id
                                    AND e.event_status = 'published' AND e.event_date > CURRENT_TIMESTAMP
                              )
                        ";
                        $pdo = getDbConnection();
                        $pdo->beginTransaction();
                        $eventLock = fetchOne('SELECT event_status, event_date FROM Events_007 WHERE event_id = :event_id AND organizer_id = :organizer_id FOR UPDATE', [
                            ':event_id' => $ticket['event_id'], ':organizer_id' => $organizerId
                        ]);
                        if (!$eventLock || $eventLock['event_status'] !== 'published'
                            || strtotime($eventLock['event_date']) <= time()) {
                            throw new RuntimeException('This event is closed and cannot accept check-ins.');
                        }
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([':ticket_id' => $ticketId, ':organizer_id' => $organizerId]);

                        if ($stmt->rowCount() !== 1) {
                            throw new RuntimeException('Ticket status changed or payment is not confirmed.');
                        }
                        
                        logAuditAction($organizerId, 'organizer', 'CHECK_IN', 'Tickets_007', $ticketId, $ticket, ['status' => 'checked_in']);
                        $pdo->commit();
                        
                        $message = 'Successfully checked in: ' . ($ticket['attendee_name'] ?? 'Ticket ' . $ticket['ticket_number']);
                        $messageType = 'success';
                        
                        // Refresh events list
                        $myEvents = fetchAll("
                            SELECT event_id, event_name, event_date, venue_id,
                                   (SELECT COUNT(*) FROM Tickets_007 WHERE event_id = Events_007.event_id AND ticket_status IN ('confirmed', 'checked_in')) as total_tickets,
                                   (SELECT COUNT(*) FROM Tickets_007 WHERE event_id = Events_007.event_id AND ticket_status = 'checked_in') as checked_in
                            FROM Events_007 
                            WHERE organizer_id = :organizer_id AND event_status = 'published' AND event_date >= CURRENT_TIMESTAMP
                            ORDER BY event_date ASC
                        ", [':organizer_id' => $organizerId]);
                    } catch (Exception $e) {
                        if (isset($pdo) && $pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        error_log('Organizer check-in error: ' . $e->getMessage());
                        $message = 'Could not check in this ticket. Refresh and try again.';
                        $messageType = 'danger';
                    }
                }
            } else {
                $message = 'Ticket not found.';
                $messageType = 'danger';
            }
        }
    }
}

// Get attendees for selected event
$attendees = [];
if ($selectedEventId > 0) {
    $attendees = fetchAll("
        SELECT t.ticket_id, t.ticket_number, t.ticket_status, t.booking_date, t.check_in_time,
               a.full_name as attendee_name, a.email as attendee_email,
               p.payment_status
        FROM Tickets_007 t
        INNER JOIN Attendees_007 a ON t.attendee_id = a.attendee_id
        LEFT JOIN Payments_007 p ON t.ticket_id = p.ticket_id
        WHERE t.event_id = :event_id
          AND EXISTS (
              SELECT 1 FROM Events_007 e
              WHERE e.event_id = t.event_id AND e.organizer_id = :organizer_id
          )
        ORDER BY t.ticket_status, a.full_name
    ", [':event_id' => $selectedEventId, ':organizer_id' => $organizerId]);
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
                            <a class="nav-link" href="create_event.php">
                                <i class="fas fa-plus-circle me-2"></i>Create Event
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="view_booking.php">
                                <i class="fas fa-ticket-alt me-2"></i>Bookings
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="check_in.php">
                                <i class="fas fa-qrcode me-2"></i>Scan Entry
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
                    <h1 class="h2"><i class="fas fa-qrcode me-2"></i>Check-In / Scan Entry</h1>
                </div>
                
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <div class="row">
                    <!-- Scan Section -->
                    <div class="col-lg-6 mb-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="fas fa-barcode me-2"></i>Scan Ticket</h5>
                            </div>
                            <div class="card-body">
                                <div class="scan-box mb-4">
                                    <i class="fas fa-qrcode"></i>
                                    <h5>Enter Ticket Number</h5>
                                    <p class="text-muted">Enter the ticket number from the attendee's confirmation</p>
                                </div>
                                
                                <form method="POST">
                                    <?php echo csrfTokenField(); ?>
                                    <input type="hidden" name="action" value="lookup">
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Ticket Number</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control form-control-lg" name="ticket_number" 
                                                   placeholder="e.g., TKT-1-1234567890-123" 
                                                   value="<?php echo htmlspecialchars($_POST['ticket_number'] ?? ''); ?>" required>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-search me-1"></i>Lookup
                                            </button>
                                        </div>
                                    </div>
                                </form>
                                
                                <?php if ($ticketInfo): ?>
                                    <div class="ticket-found mt-4">
                                        <div class="card <?php echo $ticketInfo['ticket_status'] === 'checked_in' ? 'border-warning' : 'border-success'; ?>">
                                            <div class="card-header <?php echo $ticketInfo['ticket_status'] === 'checked_in' ? 'bg-warning' : 'bg-success'; ?> text-white">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span>
                                                        <?php if ($ticketInfo['ticket_status'] === 'checked_in'): ?>
                                                            <i class="fas fa-exclamation-triangle me-2"></i>Already Checked In
                                                        <?php else: ?>
                                                            <i class="fas fa-check-circle me-2"></i>Valid Ticket
                                                        <?php endif; ?>
                                                    </span>
                                                    <code class="text-white"><?php echo htmlspecialchars($ticketInfo['ticket_number']); ?></code>
                                                </div>
                                            </div>
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-sm-6">
                                                        <p class="mb-1"><strong>Attendee:</strong></p>
                                                        <p class="mb-1"><?php echo htmlspecialchars($ticketInfo['attendee_name']); ?></p>
                                                        <small class="text-muted"><?php echo htmlspecialchars($ticketInfo['attendee_email']); ?></small>
                                                    </div>
                                                    <div class="col-sm-6">
                                                        <p class="mb-1"><strong>Event:</strong></p>
                                                        <p class="mb-1"><?php echo htmlspecialchars($ticketInfo['event_name']); ?></p>
                                                        <small class="text-muted"><?php echo formatDate($ticketInfo['event_date']); ?></small>
                                                    </div>
                                                </div>
                                                <hr>
                                                <div class="row">
                                                    <div class="col-sm-6">
                                                        <p class="mb-1"><strong>Venue:</strong></p>
                                                        <p class="mb-0"><?php echo htmlspecialchars($ticketInfo['venue_name']); ?></p>
                                                    </div>
                                                    <div class="col-sm-6">
                                                        <p class="mb-1"><strong>Payment:</strong></p>
                                                        <p class="mb-0">
                                                            <span class="badge bg-<?php echo getStatusBadgeClass($ticketInfo['payment_status']); ?>">
                                                                <?php echo ucfirst($ticketInfo['payment_status']); ?>
                                                            </span>
                                                            - <?php echo formatCurrency($ticketInfo['payment_amount'] ?? $ticketInfo['ticket_price']); ?>
                                                        </p>
                                                    </div>
                                                </div>
                                                
                                                <?php if ($ticketInfo['event_status'] === 'published' && $ticketInfo['ticket_status'] === 'confirmed' && $ticketInfo['payment_status'] === 'completed'): ?>
                                                    <form method="POST" class="mt-3">
                                                        <?php echo csrfTokenField(); ?>
                                                        <input type="hidden" name="action" value="checkin">
                                                        <input type="hidden" name="ticket_id" value="<?php echo $ticketInfo['ticket_id']; ?>">
                                                        <input type="hidden" name="ticket_number" value="<?php echo htmlspecialchars($ticketInfo['ticket_number']); ?>">
                                                        <button type="submit" class="btn btn-success btn-lg w-100">
                                                            <i class="fas fa-check-circle me-2"></i>Check In Attendee
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Event Stats & Guest List -->
                    <div class="col-lg-6 mb-4">
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="fas fa-calendar-check me-2"></i>Select Event</h5>
                            </div>
                            <div class="card-body">
                                <form method="GET">
                                    <select class="form-select" name="event_id" onchange="this.form.submit()">
                                        <option value="">-- Select Event --</option>
                                        <?php foreach ($myEvents as $event): ?>
                                            <option value="<?php echo $event['event_id']; ?>" 
                                                    <?php echo $selectedEventId == $event['event_id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($event['event_name']); ?> 
                                                (<?php echo formatDate($event['event_date']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                                
                                <?php if ($selectedEventId > 0): ?>
                                    <?php 
                                    $selectedEvent = null;
                                    foreach ($myEvents as $e) {
                                        if ($e['event_id'] == $selectedEventId) {
                                            $selectedEvent = $e;
                                            break;
                                        }
                                    }
                                    ?>
                                    <?php if ($selectedEvent): ?>
                                        <div class="row mt-3 text-center">
                                            <div class="col-4">
                                                <div class="p-3 bg-light rounded">
                                                    <h4 class="mb-0"><?php echo $selectedEvent['total_tickets']; ?></h4>
                                                    <small class="text-muted">Total Tickets</small>
                                                </div>
                                            </div>
                                            <div class="col-4">
                                                <div class="p-3 bg-success text-white rounded">
                                                    <h4 class="mb-0"><?php echo $selectedEvent['checked_in']; ?></h4>
                                                    <small>Checked In</small>
                                                </div>
                                            </div>
                                            <div class="col-4">
                                                <div class="p-3 bg-warning text-dark rounded">
                                                    <h4 class="mb-0"><?php echo $selectedEvent['total_tickets'] - $selectedEvent['checked_in']; ?></h4>
                                                    <small>Pending</small>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if ($selectedEventId > 0 && !empty($attendees)): ?>
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0"><i class="fas fa-users me-2"></i>Guest List</h5>
                                    <span class="badge bg-primary"><?php echo count($attendees); ?> attendees</span>
                                </div>
                                <div class="card-body p-0 scroll-panel">
                                    <?php foreach ($attendees as $attendee): ?>
                                        <div class="attendee-card p-3 border-bottom <?php echo $attendee['ticket_status'] === 'checked_in' ? 'bg-light' : ''; ?>">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($attendee['attendee_name']); ?></strong>
                                                    <?php if ($attendee['ticket_status'] === 'checked_in'): ?>
                                                        <span class="badge bg-success ms-2">
                                                            <i class="fas fa-check"></i> Checked In
                                                        </span>
                                                    <?php endif; ?>
                                                    <br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($attendee['attendee_email']); ?></small>
                                                </div>
                                                <div class="text-end">
                                                    <code class="small"><?php echo htmlspecialchars($attendee['ticket_number']); ?></code>
                                                    <?php if ($attendee['check_in_time']): ?>
                                                        <br><small class="text-success">
                                                            <?php echo date('h:i A', strtotime($attendee['check_in_time'])); ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <?php include __DIR__ . '/../includes/footer.php'; ?>
    
</body>
</html>
