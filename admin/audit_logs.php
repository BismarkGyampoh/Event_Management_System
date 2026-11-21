<?php
/**
 * Audit Logs - Admin Page
 * Event Management System 007
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(['admin']);

$pageTitle = 'Audit Logs - Admin Dashboard';

// Get filter parameters
$userType = $_GET['user_type'] ?? '';
$actionType = $_GET['action_type'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

// Build query
$whereConditions = [];
$params = [];

if (!empty($userType)) {
    $whereConditions[] = "user_type = ?";
    $params[] = $userType;
}

if (!empty($actionType)) {
    $whereConditions[] = "action_type = ?";
    $params[] = $actionType;
}

if (!empty($dateFrom)) {
    $whereConditions[] = "created_at >= ?";
    $params[] = $dateFrom . ' 00:00:00';
}

if (!empty($dateTo)) {
    $whereConditions[] = "created_at <= ?";
    $params[] = $dateTo . ' 23:59:59';
}

$whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

// Get audit logs with pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

try {
    $totalLogs = fetchOne("SELECT COUNT(*) as count FROM Audit_Log_007 $whereClause", $params)['count'] ?? 0;
    $logs = fetchAll("SELECT * FROM Audit_Log_007 $whereClause ORDER BY created_at DESC LIMIT $perPage OFFSET $offset", $params);
    foreach ($logs as &$log) {
        foreach (['old_values', 'new_values'] as $field) {
            $decoded = json_decode($log[$field] ?? 'null', true);
            if (is_array($decoded)) {
                $log[$field] = json_encode(redactAuditValues($decoded), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }
    }
    unset($log);
} catch (Exception $e) {
    $totalLogs = 0;
    $logs = [];
}

$totalPages = ceil($totalLogs / $perPage);
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
                            <a class="nav-link" href="manage_users.php">
                                <i class="fas fa-users-cog me-2"></i>Manage Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="audit_logs.php">
                                <i class="fas fa-history me-2"></i>Audit Logs
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="reports.php">
                                <i class="fas fa-chart-bar me-2"></i>Reports
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="backup.php">
                                <i class="fas fa-database me-2"></i>Backup
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
                    <h1 class="h2"><i class="fas fa-history me-2"></i>Audit Logs</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="exportLogs()">
                                <i class="fas fa-download me-1"></i>Export
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">User Type</label>
                                <select class="form-select" name="user_type">
                                    <option value="">All</option>
                                    <option value="admin" <?php echo $userType === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                    <option value="organizer" <?php echo $userType === 'organizer' ? 'selected' : ''; ?>>Organizer</option>
                                    <option value="attendee" <?php echo $userType === 'attendee' ? 'selected' : ''; ?>>Attendee</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Action Type</label>
                                <select class="form-select" name="action_type">
                                    <option value="">All</option>
                                    <option value="LOGIN" <?php echo $actionType === 'LOGIN' ? 'selected' : ''; ?>>Login</option>
                                    <option value="LOGOUT" <?php echo $actionType === 'LOGOUT' ? 'selected' : ''; ?>>Logout</option>
                                    <option value="INSERT" <?php echo $actionType === 'INSERT' ? 'selected' : ''; ?>>Insert</option>
                                    <option value="UPDATE" <?php echo $actionType === 'UPDATE' ? 'selected' : ''; ?>>Update</option>
                                    <option value="DELETE" <?php echo $actionType === 'DELETE' ? 'selected' : ''; ?>>Delete</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Date From</label>
                                <input type="date" class="form-control" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Date To</label>
                                <input type="date" class="form-control" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>">
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter me-1"></i>Filter
                                </button>
                                <a href="audit_logs.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-sync-alt me-1"></i>Reset
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Audit Logs Table -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Audit Logs (<?php echo number_format($totalLogs); ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($logs)): ?>
                            <p class="text-muted text-center py-4">No audit logs found.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>User</th>
                                            <th>Type</th>
                                            <th>Action</th>
                                            <th>Table</th>
                                            <th>Record</th>
                                            <th>Details</th>
                                            <th>IP Address</th>
                                            <th>Timestamp</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($logs as $log): ?>
                                            <tr>
                                                <td><small><?php echo $log['audit_id']; ?></small></td>
                                                <td>
                                                    <span class="badge bg-<?php echo getStatusBadgeClass($log['user_type']); ?>">
                                                        <?php echo ucfirst($log['user_type']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($log['action_type']); ?></td>
                                                <td><?php echo htmlspecialchars($log['table_name'] ?? '-'); ?></td>
                                                <td><?php echo $log['record_id'] ?? '-'; ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#detailsModal<?php echo $log['audit_id']; ?>">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                </td>
                                                <td><small><?php echo htmlspecialchars($log['ip_address'] ?? '-'); ?></small></td>
                                                <td><small><?php echo formatDate($log['created_at']); ?></small></td>
                                            </tr>
                                            
                                            <!-- Details Modal -->
                                            <div class="modal fade" id="detailsModal<?php echo $log['audit_id']; ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Audit Log Details</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="mb-3">
                                                                <strong>User ID:</strong> <?php echo $log['user_id']; ?>
                                                            </div>
                                                            <div class="mb-3">
                                                                <strong>User Type:</strong> <?php echo ucfirst($log['user_type']); ?>
                                                            </div>
                                                            <div class="mb-3">
                                                                <strong>Action:</strong> <?php echo htmlspecialchars($log['action_type']); ?>
                                                            </div>
                                                            <div class="mb-3">
                                                                <strong>Table:</strong> <?php echo htmlspecialchars($log['table_name'] ?? '-'); ?>
                                                            </div>
                                                            <div class="mb-3">
                                                                <strong>Record ID:</strong> <?php echo $log['record_id'] ?? '-'; ?>
                                                            </div>
                                                            <div class="mb-3">
                                                                <strong>IP Address:</strong> <?php echo htmlspecialchars($log['ip_address'] ?? '-'); ?>
                                                            </div>
                                                            <div class="mb-3">
                                                                <strong>User Agent:</strong> <small><?php echo htmlspecialchars($log['user_agent'] ?? '-'); ?></small>
                                                            </div>
                                                            <div class="mb-3">
                                                                <strong>Old Values:</strong>
                                                                <pre class="bg-light p-2"><?php echo htmlspecialchars($log['old_values'] ?? 'N/A'); ?></pre>
                                                            </div>
                                                            <div class="mb-3">
                                                                <strong>New Values:</strong>
                                                                <pre class="bg-light p-2"><?php echo htmlspecialchars($log['new_values'] ?? 'N/A'); ?></pre>
                                                            </div>
                                                            <div class="mb-3">
                                                                <strong>Created:</strong> <?php echo formatDate($log['created_at']); ?>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Pagination -->
                            <?php if ($totalPages > 1): ?>
                                <nav aria-label="Page navigation" class="mt-4">
                                    <ul class="pagination justify-content-center mb-0">
                                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script>
        function exportLogs() {
            window.location.href = 'export_logs.php?<?php echo http_build_query($_GET); ?>';
        }
    </script>
    
    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
