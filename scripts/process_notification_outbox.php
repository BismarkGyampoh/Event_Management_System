<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/mailer/ResendMailer.php';

$pdo = getDbConnection();
$sent = 0;
$failed = 0;
$batchSize = 25;

for ($i = 0; $i < $batchSize; $i++) {
    $pdo->beginTransaction();
    $claim = $pdo->query("SELECT notification_id, booking_id, idempotency_key, attempts FROM NotificationOutbox_007
        WHERE (status = 'queued' AND available_at <= CURRENT_TIMESTAMP)
           OR (status = 'processing' AND claimed_at < CURRENT_TIMESTAMP - INTERVAL '10 minutes')
        ORDER BY created_at, notification_id LIMIT 1 FOR UPDATE SKIP LOCKED");
    $item = $claim->fetch();
    if (!$item) {
        $pdo->commit();
        break;
    }
    $pdo->prepare("UPDATE NotificationOutbox_007 SET status = 'processing', attempts = attempts + 1, claimed_at = CURRENT_TIMESTAMP WHERE notification_id = ?")->execute([$item['notification_id']]);
    $pdo->commit();

    try {
        $stmt = $pdo->prepare("SELECT b.booking_reference, a.email, a.full_name, e.event_name FROM BookingOrders_007 b
            JOIN Attendees_007 a ON a.attendee_id = b.attendee_id JOIN Events_007 e ON e.event_id = b.event_id
            WHERE b.booking_id = ? AND b.status = 'paid'");
        $stmt->execute([$item['booking_id']]);
        $booking = $stmt->fetch();
        if (!$booking) throw new RuntimeException('booking_not_paid');
        $name = htmlspecialchars((string)$booking['full_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $event = htmlspecialchars((string)$booking['event_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $reference = htmlspecialchars((string)$booking['booking_reference'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        (new ResendMailer())->send($booking['email'], 'Your event booking is confirmed',
            '<p>Hello ' . $name . ',</p><p>Your payment for <strong>' . $event . '</strong> has been verified.</p><p>Booking reference: <strong>' . $reference . '</strong></p>',
            $item['idempotency_key']);
        $pdo->prepare("UPDATE NotificationOutbox_007 SET status = 'sent', sent_at = CURRENT_TIMESTAMP, claimed_at = NULL, last_error_code = NULL WHERE notification_id = ?")->execute([$item['notification_id']]);
        $sent++;
    } catch (Throwable $e) {
        $attempt = (int)$item['attempts'] + 1;
        $retryMinutes = min(1440, 5 * (2 ** min(8, max(0, $attempt - 1))));
        $state = $attempt >= 8 ? 'failed' : 'queued';
        $stmt = $pdo->prepare("UPDATE NotificationOutbox_007 SET status = ?, available_at = CURRENT_TIMESTAMP + (? * INTERVAL '1 minute'), claimed_at = NULL, last_error_code = 'delivery_failed' WHERE notification_id = ?");
        $stmt->execute([$state, $retryMinutes, $item['notification_id']]);
        error_log('Notification outbox delivery failed.');
        $failed++;
    }
}

fwrite(STDOUT, 'Notification outbox: ' . $sent . ' sent; ' . $failed . ' delivery failures this run.' . PHP_EOL);
