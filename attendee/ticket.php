<?php
/**
 * Download Ticket / View QR Code - Attendee Page
 * Event Management System 007
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(['attendee']);

$pageTitle = 'My Ticket - Attendee Dashboard';
$attendeeId = getCurrentUserId();

$ticketId = (int)($_GET['ticket_id'] ?? 0);

// Get ticket details
$ticket = fetchOne("
    SELECT t.ticket_id, t.ticket_number, t.ticket_status, t.booking_date, t.check_in_time,
           e.event_name, e.event_description, e.event_date, e.end_date, e.ticket_price, e.event_status,
           v.venue_name, v.address as venue_address, v.city,
           a.full_name as attendee_name, a.email as attendee_email,
           p.amount as payment_amount, p.payment_status
    FROM Tickets_007 t
    INNER JOIN Events_007 e ON t.event_id = e.event_id
    INNER JOIN Venues_007 v ON e.venue_id = v.venue_id
    INNER JOIN Attendees_007 a ON t.attendee_id = a.attendee_id
    LEFT JOIN Payments_007 p ON t.ticket_id = p.ticket_id
    WHERE t.ticket_id = :ticket_id AND t.attendee_id = :attendee_id
", [':ticket_id' => $ticketId, ':attendee_id' => $attendeeId]);

if (!$ticket) {
    setFlashMessage('danger', 'Ticket not found.');
    header('Location: my_bookings.php');
    exit;
}

$ticketIsValid = $ticket['payment_status'] === 'completed'
    && $ticket['event_status'] === 'published'
    && in_array($ticket['ticket_status'], ['confirmed', 'checked_in'], true);

// Handle download request
if (isset($_GET['download'])) {
    if (!$ticketIsValid) {
        $downloadMessage = $ticket['ticket_status'] === 'cancelled'
            ? 'This booking was cancelled and is not a valid ticket.'
            : 'This booking is not a valid ticket yet. Payment and organizer confirmation are required.';
        setFlashMessage('warning', $downloadMessage);
        redirect(getBaseUrl() . 'attendee/ticket.php?ticket_id=' . $ticketId);
    }

    $ticketNumberJson = json_encode($ticket['ticket_number'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

    // Set headers for download
    header('Content-Type: text/html');
    header('Content-Disposition: attachment; filename="ticket-' . $ticket['ticket_number'] . '.html"');
    
    // Output simple HTML ticket
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>Ticket - ' . htmlspecialchars($ticket['event_name']) . '</title>
        <link rel="stylesheet" href="' . htmlspecialchars(getAssetUrl('css/main.css'), ENT_QUOTES, 'UTF-8') . '">
    </head>
    <body class="ticket-download">
        <div class="ticket">
            <div class="header">
                <h1>' . htmlspecialchars($ticket['event_name']) . '</h1>
                <p>Official Event Ticket</p>
            </div>
            
            <div class="qr-code" id="ticketQr" aria-label="Ticket QR code"></div>
            
            <div class="ticket-number">
                <strong>' . htmlspecialchars($ticket['ticket_number']) . '</strong>
            </div>
            
            <div class="details">
                <p><strong>Attendee:</strong> ' . htmlspecialchars($ticket['attendee_name']) . '</p>
                <p><strong>Email:</strong> ' . htmlspecialchars($ticket['attendee_email']) . '</p>
                <p><strong>Date:</strong> ' . formatDate($ticket['event_date']) . '</p>
                <p><strong>Venue:</strong> ' . htmlspecialchars($ticket['venue_name']) . '</p>
                <p><strong>Address:</strong> ' . htmlspecialchars($ticket['venue_address'] . ', ' . $ticket['city']) . '</p>
                <p><strong>Ticket Price:</strong> ' . formatCurrency($ticket['payment_amount'] ?? $ticket['ticket_price']) . '</p>
                <p><strong>Status:</strong> ' . ucfirst($ticket['ticket_status']) . '</p>
            </div>
            
            <div class="footer">
                <p>Present this ticket at the venue entrance.</p>
                <p>Event Management System - Generated on ' . date('M d, Y h:i A') . '</p>
            </div>
        </div>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" integrity="sha512-CNgIRecGo7nphbeZ04Sc13ka07paqdeTu0WR1IM4kNcpmBAUSHSQX0FslNhTDadL4O5SAGapGt4FodqL8My0mA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
        <script>new QRCode(document.getElementById("ticketQr"), {text: ' . $ticketNumberJson . ', width: 200, height: 200});</script>
    </body>
    </html>';
    exit;
}

$ticketNumberJson = json_encode($ticket['ticket_number'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="View your EventMS ticket and entry QR code.">
    <title><?php echo $pageTitle; ?></title>
    <link href="<?php echo getAssetUrl('css/main.css'); ?>" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <main>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="mb-4">
                    <a href="my_bookings.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to My Bookings
                    </a>
                </div>
                
                <div class="ticket-card">
                    <div class="ticket-inner">
                        <div class="ticket-header">
                            <span class="badge bg-primary status-badge mb-3">
                                <i class="fas fa-ticket-alt me-1"></i><?php echo $ticketIsValid ? 'Official Event Ticket' : 'Booking Summary'; ?>
                            </span>
                            <h1 class="h3 mb-2"><?php echo htmlspecialchars($ticket['event_name']); ?></h1>
                            <p class="text-muted mb-0"><?php echo htmlspecialchars($ticket['event_description'] ?? ''); ?></p>
                        </div>
                        
                        <?php if ($ticketIsValid): ?>
                            <div class="qr-container">
                                <div id="ticketQr" data-ticket="<?php echo htmlspecialchars($ticket['ticket_number'], ENT_QUOTES, 'UTF-8'); ?>" aria-label="Ticket QR code"></div>
                                <p class="text-muted mt-2 mb-0"><small>Scan this QR code at the venue entrance</small></p>
                            </div>
                            
                            <div class="ticket-number-box">
                                <strong><?php echo htmlspecialchars($ticket['ticket_number']); ?></strong>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning text-center">
                                <?php if ($ticket['event_status'] !== 'published'): ?>
                                    <strong>Event cancelled or closed</strong>
                                    <div class="small mt-1">This ticket is not valid for entry. Contact the organizer about refund arrangements.</div>
                                <?php elseif ($ticket['ticket_status'] === 'cancelled'): ?>
                                    <strong>Booking cancelled</strong>
                                    <div class="small mt-1">This booking is not valid for entry.</div>
                                <?php elseif ($ticket['payment_status'] !== 'completed'): ?>
                                    <strong>Reservation only — payment pending</strong>
                                    <div class="small mt-1">This is not valid for entry. The organizer must confirm payment before a ticket is issued.</div>
                                <?php else: ?>
                                    <strong>Awaiting organizer confirmation</strong>
                                    <div class="small mt-1">Payment is recorded, but the ticket is not valid for entry until the booking is confirmed.</div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mt-4">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="detail-row">
                                        <span class="text-muted">Attendee</span>
                                        <strong><?php echo htmlspecialchars($ticket['attendee_name']); ?></strong>
                                    </div>
                                    <div class="detail-row">
                                        <span class="text-muted">Email</span>
                                        <strong><?php echo htmlspecialchars($ticket['attendee_email']); ?></strong>
                                    </div>
                                    <div class="detail-row">
                                        <span class="text-muted">Event Date</span>
                                        <strong><?php echo formatDate($ticket['event_date']); ?></strong>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="detail-row">
                                        <span class="text-muted">Venue</span>
                                        <strong><?php echo htmlspecialchars($ticket['venue_name']); ?></strong>
                                    </div>
                                    <div class="detail-row">
                                        <span class="text-muted">Location</span>
                                        <strong><?php echo htmlspecialchars($ticket['city']); ?></strong>
                                    </div>
                                    <div class="detail-row">
                                        <span class="text-muted">Status</span>
                                        <span class="badge bg-<?php echo getStatusBadgeClass($ticket['ticket_status']); ?>">
                                            <?php echo ucfirst($ticket['ticket_status']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4 p-3 bg-light rounded">
                            <div class="row align-items-center">
                                <div class="col">
                                    <small class="text-muted">
                                        <?php
                                        $paymentLabels = [
                                            'completed' => 'Amount paid',
                                            'pending' => 'Amount due',
                                            'refund_pending' => 'Refund pending',
                                            'refunded' => 'Refunded amount',
                                            'failed' => 'Payment not completed',
                                        ];
                                        echo htmlspecialchars($paymentLabels[$ticket['payment_status'] ?? 'pending'] ?? 'Payment amount');
                                        ?>
                                    </small>
                                    <h4 class="mb-0 <?php echo $ticket['payment_status'] === 'completed' ? 'text-success' : 'text-warning'; ?>">
                                        <?php echo formatCurrency($ticket['payment_amount'] ?? $ticket['ticket_price']); ?>
                                    </h4>
                                </div>
                                <div class="col-auto">
                                    <span class="badge bg-<?php echo getStatusBadgeClass($ticket['payment_status']); ?> fs-6">
                                        <?php echo ucwords(str_replace('_', ' ', $ticket['payment_status'] ?? 'pending')); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($ticketIsValid): ?>
                            <div class="mt-4 d-grid gap-2 d-md-flex justify-content-md-center">
                                <a href="?ticket_id=<?php echo $ticketId; ?>&download=1" class="btn btn-primary btn-lg">
                                    <i class="fas fa-download me-2"></i>Download Ticket
                                </a>
                                <button onclick="window.print()" class="btn btn-outline-secondary btn-lg">
                                    <i class="fas fa-print me-2"></i>Print
                                </button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($ticket['check_in_time']): ?>
                            <div class="mt-4 alert alert-success text-center">
                                <i class="fas fa-check-circle me-2"></i>
                                Checked in on <?php echo formatDate($ticket['check_in_time'], 'M d, Y h:i A'); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="mt-4 card border-0 shadow-sm">
                    <div class="card-body">
                        <h5 class="mb-3"><i class="fas fa-info-circle me-2"></i>Important Information</h5>
                        <ul class="mb-0">
                            <li>Only a ticket with completed payment is valid for entry</li>
                            <li>For pay-in-person bookings, the organizer must confirm receipt before the ticket is issued</li>
                            <li>Arrive at least 30 minutes before the event start time</li>
                            <li>This ticket is non-transferable and valid for one person only</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </main>
    
    <?php include __DIR__ . '/../includes/footer.php'; ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" integrity="sha512-CNgIRecGo7nphbeZ04Sc13ka07paqdeTu0WR1IM4kNcpmBAUSHSQX0FslNhTDadL4O5SAGapGt4FodqL8My0mA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script>
        const qr = document.getElementById('ticketQr');
        if (qr) new QRCode(qr, {text: qr.dataset.ticket, width: 220, height: 220});
    </script>
    
</body>
</html>
