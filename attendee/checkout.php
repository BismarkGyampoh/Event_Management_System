<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/payments/PaymentService.php';

requireRole(['attendee']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCsrfToken()) {
    http_response_code(400);
    exit('Invalid checkout request.');
}
try {
    $attendee = fetchOne('SELECT email FROM Attendees_007 WHERE attendee_id = ?', [getCurrentUserId()]);
    if (!$attendee) throw new RuntimeException('Attendee account could not be found.');
    $result = (new PaymentService(getDbConnection()))->startCheckout(
        (int)getCurrentUserId(), (int)($_POST['event_id'] ?? 0), (int)($_POST['ticket_type_id'] ?? 0),
        (int)($_POST['quantity'] ?? 0), $attendee['email'], getBaseUrl() . 'attendee/payment_result.php'
    );
    header('Location: ' . $result['authorization_url'], true, 303);
    exit;
} catch (Throwable $e) {
    error_log('Checkout could not be initialized.');
    setFlashMessage('danger', $e instanceof InvalidArgumentException || $e instanceof RuntimeException ? $e->getMessage() : 'Checkout could not be started.');
    redirect('browse_events.php');
}
