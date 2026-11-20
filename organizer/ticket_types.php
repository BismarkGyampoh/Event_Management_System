<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/payments/Money.php';
requireRole(['organizer']);
$organizerId = (int)getCurrentUserId();
$eventId = (int)($_GET['event_id'] ?? $_POST['event_id'] ?? 0);
$event = fetchOne('SELECT event_id, event_name, max_capacity FROM Events_007 WHERE event_id = ? AND organizer_id = ?', [$eventId, $organizerId]);
if (!$event) { http_response_code(404); exit('Event not found.'); }
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken()) $message = 'Invalid form submission.';
    else {
        $typeId = (int)($_POST['ticket_type_id'] ?? 0);
        $name = trim((string)($_POST['type_name'] ?? ''));
        try {
            if (!preg_match('/\A[\pL\pN][\pL\pN &()_\-]{0,79}\z/u', $name)) throw new InvalidArgumentException('Enter a valid ticket type name.');
            $priceMinor = Money::ghsToMinor((string)($_POST['price'] ?? ''));
            $quantity = filter_var($_POST['quantity'] ?? null, FILTER_VALIDATE_INT);
            if ($quantity === false || $quantity < 1) throw new InvalidArgumentException('Ticket quantity must be at least one.');
            $pdo = getDbConnection();
            $pdo->beginTransaction();
            $lock = $pdo->prepare('SELECT max_capacity FROM Events_007 WHERE event_id = ? AND organizer_id = ? FOR UPDATE');
            $lock->execute([$eventId, $organizerId]);
            $capacity = (int)$lock->fetchColumn();
            if (!$capacity) throw new RuntimeException('Event is no longer available.');
            $existingStmt = $pdo->prepare('SELECT * FROM TicketTypes_007 WHERE event_id = ? AND ticket_type_id = ? FOR UPDATE');
            $existingStmt->execute([$eventId, $typeId]);
            $existing = $typeId ? $existingStmt->fetch() : false;
            if ($typeId && !$existing) throw new RuntimeException('Ticket type not found.');
            $totals = $pdo->prepare('SELECT COALESCE(SUM(quantity),0) FROM TicketTypes_007 WHERE event_id = ? AND is_active = TRUE AND ticket_type_id <> ?');
            $totals->execute([$eventId, $typeId]);
            if ((int)$totals->fetchColumn() + $quantity > $capacity) throw new InvalidArgumentException('Active ticket quantities cannot exceed the event capacity.');
            if ($existing) {
                $sales = $pdo->prepare("SELECT COUNT(*) + COALESCE((SELECT SUM(bi.quantity) FROM BookingItems_007 bi JOIN BookingOrders_007 b ON b.booking_id = bi.booking_id WHERE bi.ticket_type_id = ? AND b.status = 'pending' AND b.expires_at > CURRENT_TIMESTAMP),0) FROM Tickets_007 WHERE ticket_type_id = ? AND ticket_status NOT IN ('cancelled','expired')");
                $sales->execute([$typeId, $typeId]);
                if ($quantity < (int)$sales->fetchColumn()) throw new InvalidArgumentException('Quantity cannot be lower than sold tickets and active reservations.');
                $stmt = $pdo->prepare('UPDATE TicketTypes_007 SET type_name = ?, price_minor = ?, quantity = ? WHERE ticket_type_id = ? AND event_id = ?');
                $stmt->execute([$name, $priceMinor, $quantity, $typeId, $eventId]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO TicketTypes_007 (event_id, type_name, price_minor, quantity) VALUES (?, ?, ?, ?)');
                $stmt->execute([$eventId, $name, $priceMinor, $quantity]);
            }
            $pdo->commit();
            $message = 'Ticket type saved.';
        } catch (PDOException $e) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            $message = 'Ticket type could not be saved. Check that the name is unique for this event.';
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            $message = $e instanceof InvalidArgumentException ? $e->getMessage() : 'Ticket type could not be saved.';
        }
    }
}
$types = fetchAll("SELECT t.*, (SELECT COUNT(*) FROM Tickets_007 x WHERE x.ticket_type_id = t.ticket_type_id AND x.ticket_status NOT IN ('cancelled','expired')) AS sold FROM TicketTypes_007 t WHERE event_id = ? ORDER BY ticket_type_id", [$eventId]);
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Ticket types</title><link href="../assets/css/style.css" rel="stylesheet"><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.9/dist/css/bootstrap.min.css" rel="stylesheet"></head><body><?php include __DIR__ . '/../includes/header.php'; ?><main class="container py-4"><h1 class="h2">Ticket types: <?php echo htmlspecialchars($event['event_name']); ?></h1><?php if ($message): ?><div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div><?php endif; ?><div class="table-responsive mb-4"><table class="table"><thead><tr><th>Type</th><th>Price</th><th>Sold</th><th>Quantity</th><th>Action</th></tr></thead><tbody><?php foreach ($types as $type): ?><tr><td><?php echo htmlspecialchars($type['type_name']); ?></td><td>GHS <?php echo number_format(((int)$type['price_minor']) / 100, 2); ?></td><td><?php echo (int)$type['sold']; ?></td><td><?php echo (int)$type['quantity']; ?></td><td><form method="post" class="row g-2"><?php echo csrfTokenField(); ?><input type="hidden" name="event_id" value="<?php echo $eventId; ?>"><input type="hidden" name="ticket_type_id" value="<?php echo (int)$type['ticket_type_id']; ?>"><div class="col"><input class="form-control form-control-sm" name="type_name" maxlength="80" value="<?php echo htmlspecialchars($type['type_name']); ?>" required></div><div class="col"><input class="form-control form-control-sm" name="price" inputmode="decimal" value="<?php echo number_format(((int)$type['price_minor']) / 100, 2, '.', ''); ?>" required></div><div class="col"><input class="form-control form-control-sm" name="quantity" type="number" min="1" value="<?php echo (int)$type['quantity']; ?>" required></div><div class="col-auto"><button class="btn btn-sm btn-primary">Save</button></div></form></td></tr><?php endforeach; ?></tbody></table></div><h2 class="h4">Add ticket type</h2><form method="post" class="row g-2"><?php echo csrfTokenField(); ?><input type="hidden" name="event_id" value="<?php echo $eventId; ?>"><div class="col-md-4"><label class="form-label" for="type_name">Name</label><input class="form-control" name="type_name" id="type_name" maxlength="80" required></div><div class="col-md-3"><label class="form-label" for="price">Price (GHS)</label><input class="form-control" name="price" id="price" inputmode="decimal" required></div><div class="col-md-3"><label class="form-label" for="quantity">Quantity</label><input class="form-control" name="quantity" id="quantity" type="number" min="1" max="<?php echo (int)$event['max_capacity']; ?>" required></div><div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary">Add type</button></div></form></main><?php include __DIR__ . '/../includes/footer.php'; ?></body></html>
