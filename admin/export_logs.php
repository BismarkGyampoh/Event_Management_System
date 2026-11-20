<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(['admin']);

$conditions = [];
$params = [];
$userType = $_GET['user_type'] ?? '';
$actionType = $_GET['action_type'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

if (in_array($userType, ['admin', 'organizer', 'attendee'], true)) {
    $conditions[] = 'user_type = ?';
    $params[] = $userType;
}
if (is_string($actionType) && $actionType !== '' && strlen($actionType) <= 50) {
    $conditions[] = 'action_type = ?';
    $params[] = $actionType;
}
if (is_string($dateFrom) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $conditions[] = 'created_at >= ?';
    $params[] = $dateFrom . ' 00:00:00';
}
if (is_string($dateTo) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $conditions[] = 'created_at < (?::date + INTERVAL \'1 day\')';
    $params[] = $dateTo;
}
$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="eventms-audit-logs-' . date('Y-m-d') . '.csv"');
header('Cache-Control: no-store, private');
$output = fopen('php://output', 'w');
fputcsv($output, ['Audit ID', 'User ID', 'User Type', 'Action', 'Table', 'Record ID', 'Old Values', 'New Values', 'IP Address', 'User Agent', 'Created At']);

$stmt = getDbConnection()->prepare("SELECT audit_id, user_id, user_type, action_type, table_name, record_id,
    old_values, new_values, ip_address, user_agent, created_at FROM Audit_Log_007 $where ORDER BY created_at DESC");
$stmt->execute($params);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    foreach (['old_values', 'new_values'] as $field) {
        $decoded = json_decode($row[$field] ?? 'null', true);
        if (is_array($decoded)) {
            $row[$field] = json_encode(redactAuditValues($decoded), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }
    $cells = [];
    foreach ($row as $value) {
        $value = is_scalar($value) || $value === null ? (string)$value : json_encode($value);
        if (preg_match('/^[\x00-\x20]*[=+@-]/', $value)) {
            $value = "'" . $value;
        }
        $cells[] = $value;
    }
    fputcsv($output, $cells);
}
fclose($output);
