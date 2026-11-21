<?php
/**
 * Admin Dashboard
 * Event Management System 007
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(['admin']);

$pageTitle = 'Admin Dashboard - Event Management System';

// Get statistics
try {
    $stats = [
        'total_events' => fetchOne("SELECT COUNT(*) as count FROM Events_007")['count'] ?? 0,
        'total_organizers' => fetchOne("SELECT COUNT(*) as count FROM Organizers_007")['count'] ?? 0,
        'total_attendees' => fetchOne("SELECT COUNT(*) as count FROM Attendees_007")['count'] ?? 0,
        'total_tickets_sold' => fetchOne("SELECT COUNT(*) as count FROM Tickets_007 WHERE ticket_status != 'cancelled'")['count'] ?? 0,
        'total_revenue' => fetchOne("SELECT COALESCE(SUM(amount), 0) as total FROM Payments_007 WHERE payment_status = 'completed'")['total'] ?? 0,
        'pending_bookings' => fetchOne("SELECT COUNT(*) as count FROM Payments_007 WHERE payment_status = 'pending'")['count'] ?? 0
    ];
} catch (Exception $e) {
    $stats = [
        'total_events' => 0,
        'total_organizers' => 0,
        'total_attendees' => 0,
        'total_tickets_sold' => 0,
        'total_revenue' => 0,
        'pending_bookings' => 0
    ];
}

// Get recent events
try {
    $recentEvents = fetchAll("
        SELECT e.*, o.full_name as organizer_name, v.venue_name,
               (SELECT COUNT(*) FROM Tickets_007 WHERE event_id = e.event_id AND ticket_status != 'cancelled') as tickets_sold
        FROM Events_007 e
        INNER JOIN Organizers_007 o ON e.organizer_id = o.organizer_id
        INNER JOIN Venues_007 v ON e.venue_id = v.venue_id
        ORDER BY e.created_at DESC
        LIMIT 5
    ");
} catch (Exception $e) {
    $recentEvents = [];
}

// Get recent audit logs
try {
    $recentLogs = fetchAll("
        SELECT * FROM Audit_Log_007
        ORDER BY created_at DESC
        LIMIT 10
    ");
} catch (Exception $e) {
    $recentLogs = [];
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
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block bg-light sidebar dashboard-sidebar" id="sidebarMenu" aria-label="Administrator dashboard navigation">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_users.php">
                                <i class="fas fa-users-cog me-2"></i>Manage Users
                            </a>
                        </li>
                        <li class="nav-item"><a class="nav-link" href="payment_settings.php"><i class="fas fa-money-check-alt me-2"></i>Payments</a></li>
                        <li class="nav-item"><a class="nav-link" href="payments.php"><i class="fas fa-receipt me-2"></i>Transactions</a></li>
                        <li class="nav-item"><a class="nav-link" href="refunds.php"><i class="fas fa-undo me-2"></i>Refunds</a></li>
                        <li class="nav-item">
                            <a class="nav-link" href="audit_logs.php">
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
                            <a class="nav-link text-danger" href="logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 role-dashboard admin-dashboard">
                <header class="dashboard-page-header d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <div class="dashboard-heading-copy">
                        <p class="section-eyebrow mb-1">Platform overview</p>
                        <h1>Admin Dashboard</h1>
                    </div>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                                <i class="fas fa-print me-1"></i>Print
                            </button>
                        </div>
                    </div>
                </header>
                
                <!-- Statistics Cards -->
                <div class="row g-4 mb-4 dashboard-stat-grid">
                    <div class="col-md-4 col-xl-2">
                        <div class="card text-center border-0 shadow-sm h-100">
                            <div class="card-body">
                                <i class="fas fa-calendar fa-2x text-primary mb-2"></i>
                                <h4 class="fw-bold"><?php echo number_format($stats['total_events']); ?></h4>
                                <p class="text-muted small mb-0">Total Events</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 col-xl-2">
                        <div class="card text-center border-0 shadow-sm h-100">
                            <div class="card-body">
                                <i class="fas fa-briefcase fa-2x text-success mb-2"></i>
                                <h4 class="fw-bold"><?php echo number_format($stats['total_organizers']); ?></h4>
                                <p class="text-muted small mb-0">Organizers</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 col-xl-2">
                        <div class="card text-center border-0 shadow-sm h-100">
                            <div class="card-body">
                                <i class="fas fa-users fa-2x text-info mb-2"></i>
                                <h4 class="fw-bold"><?php echo number_format($stats['total_attendees']); ?></h4>
                                <p class="text-muted small mb-0">Attendees</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 col-xl-2">
                        <div class="card text-center border-0 shadow-sm h-100">
                            <div class="card-body">
                                <i class="fas fa-ticket-alt fa-2x text-warning mb-2"></i>
                                <h4 class="fw-bold"><?php echo number_format($stats['total_tickets_sold']); ?></h4>
                                <p class="text-muted small mb-0">Tickets Sold</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 col-xl-2">
                        <div class="card text-center border-0 shadow-sm h-100">
                            <div class="card-body">
                                <i class="fas fa-coins fa-2x text-success mb-2"></i>
                                <h4 class="fw-bold"><?php echo formatCurrency($stats['total_revenue']); ?></h4>
                                <p class="text-muted small mb-0">Total Revenue</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 col-xl-2">
                        <div class="card text-center border-0 shadow-sm h-100">
                            <div class="card-body">
                                <i class="fas fa-clock fa-2x text-secondary mb-2"></i>
                                <h4 class="fw-bold"><?php echo number_format($stats['pending_bookings']); ?></h4>
                                <p class="text-muted small mb-0">Pending</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <!-- Recent Events -->
                    <div class="col-lg-8 mb-4">
                        <div class="card border-0 shadow-sm dashboard-panel">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="fas fa-calendar me-2"></i>Recent Events</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recentEvents)): ?>
                                    <p class="text-muted text-center py-4">No events found.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Event Name</th>
                                                    <th>Organizer</th>
                                                    <th>Date</th>
                                                    <th>Status</th>
                                                    <th>Tickets</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recentEvents as $event): ?>
                                                    <tr>
                                                        <td>
                                                            <a href="#" class="text-decoration-none fw-bold">
                                                                <?php echo htmlspecialchars($event['event_name']); ?>
                                                            </a>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($event['organizer_name']); ?></td>
                                                        <td><?php echo formatDate($event['event_date']); ?></td>
                                                        <td>
                                                            <span class="badge bg-<?php echo getStatusBadgeClass($event['event_status']); ?>">
                                                                <?php echo ucfirst($event['event_status']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-info">
                                                                <?php echo $event['tickets_sold']; ?>/<?php echo $event['max_capacity']; ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="col-lg-4 mb-4">
                        <div class="card border-0 shadow-sm dashboard-panel">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <a href="manage_users.php" class="btn btn-primary">
                                        <i class="fas fa-users-cog me-2"></i>Manage Users
                                    </a>
                                    <a href="audit_logs.php" class="btn btn-success">
                                        <i class="fas fa-history me-2"></i>View Audit Logs
                                    </a>
                                    <a href="reports.php" class="btn btn-info">
                                        <i class="fas fa-chart-bar me-2"></i>Generate Reports
                                    </a>
                                    <a href="backup.php" class="btn btn-warning">
                                        <i class="fas fa-database me-2"></i>Database Backup
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Recent Audit Logs -->
                        <div class="card border-0 shadow-sm mt-4 dashboard-panel">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Activity</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recentLogs)): ?>
                                    <p class="text-muted text-center py-4">No recent activity.</p>
                                <?php else: ?>
                                    <ul class="list-group list-group-flush">
                                        <?php foreach ($recentLogs as $log): ?>
                                            <li class="list-group-item px-0">
                                                <small class="d-block text-muted"><?php echo formatDate($log['created_at']); ?></small>
                                                <strong><?php echo ucfirst($log['user_type']); ?></strong> 
                                                <?php echo htmlspecialchars($log['action_type']); ?>
                                                <?php if ($log['table_name']): ?>
                                                    on <?php echo htmlspecialchars($log['table_name']); ?>
                                                <?php endif; ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
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
