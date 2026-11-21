<?php
/**
 * Reports - Admin Page
 * Event Management System 007
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(['admin']);

$pageTitle = 'Reports - Admin Dashboard';

// Get report data
try {
    // Revenue by event
    $revenueByEvent = fetchAll("
        SELECT e.event_name, o.full_name as organizer_name, 
               COUNT(t.ticket_id) as tickets_sold,
               COALESCE(SUM(CASE WHEN t.ticket_status NOT IN ('cancelled', 'expired') AND p.payment_status = 'completed' THEN p.amount ELSE 0 END), 0) as revenue
        FROM Events_007 e
        INNER JOIN Organizers_007 o ON e.organizer_id = o.organizer_id
        LEFT JOIN Tickets_007 t ON e.event_id = t.event_id
        LEFT JOIN Payments_007 p ON t.ticket_id = p.ticket_id AND p.payment_status = 'completed'
        GROUP BY e.event_id, e.event_name, o.full_name
        ORDER BY revenue DESC
    ");
    
    // Monthly revenue
    $monthlyRevenue = fetchAll("
        SELECT TO_CHAR(p.payment_date, 'YYYY-MM') as month,
               COUNT(DISTINCT p.payment_id) as transactions,
               COALESCE(SUM(p.amount), 0) as revenue
        FROM Payments_007 p
        WHERE p.payment_status = 'completed'
        GROUP BY TO_CHAR(p.payment_date, 'YYYY-MM')
        ORDER BY month DESC
        LIMIT 12
    ");
    
    // Event status summary
    $eventStatusSummary = fetchAll("
        SELECT event_status, COUNT(*) as count
        FROM Events_007
        GROUP BY event_status
    ");
    
    // Top organizers
    $topOrganizers = fetchAll("
        SELECT o.full_name, o.company_name,
               COUNT(DISTINCT e.event_id) as events_created,
               COALESCE(SUM(CASE WHEN t.ticket_status NOT IN ('cancelled', 'expired') AND p.payment_status = 'completed' THEN p.amount ELSE 0 END), 0) as total_revenue
        FROM Organizers_007 o
        LEFT JOIN Events_007 e ON o.organizer_id = e.organizer_id
        LEFT JOIN Tickets_007 t ON e.event_id = t.event_id
        LEFT JOIN Payments_007 p ON t.ticket_id = p.ticket_id AND p.payment_status = 'completed'
        GROUP BY o.organizer_id, o.full_name, o.company_name
        ORDER BY total_revenue DESC
        LIMIT 5
    ");
    
    // Calculate totals
    $totalRevenue = fetchOne("SELECT COALESCE(SUM(amount), 0) as total FROM Payments_007 WHERE payment_status = 'completed'")['total'] ?? 0;
    $totalTickets = fetchOne("SELECT COUNT(*) as count FROM Tickets_007 WHERE ticket_status NOT IN ('cancelled', 'expired')")['count'] ?? 0;
    $totalEvents = fetchOne("SELECT COUNT(*) as count FROM Events_007")['count'] ?? 0;
} catch (Exception $e) {
    $revenueByEvent = [];
    $monthlyRevenue = [];
    $eventStatusSummary = [];
    $topOrganizers = [];
    $totalRevenue = 0;
    $totalTickets = 0;
    $totalEvents = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link href="<?php echo getAssetUrl('css/main.css'); ?>" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="icon" type="image/svg+xml" href="<?php echo getAssetUrl('favicon.svg'); ?>">
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
                            <a class="nav-link" href="audit_logs.php">
                                <i class="fas fa-history me-2"></i>Audit Logs
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="reports.php">
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
                    <h1 class="h2"><i class="fas fa-chart-bar me-2"></i>Reports & Analytics</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                                <i class="fas fa-print me-1"></i>Print Report
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="exportReport()">
                                <i class="fas fa-download me-1"></i>Export CSV
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Summary Cards -->
                <div class="row g-4 mb-4">
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm text-center h-100">
                            <div class="card-body">
                                <i class="fas fa-coins fa-3x text-success mb-3"></i>
                                <h3 class="fw-bold"><?php echo formatCurrency($totalRevenue); ?></h3>
                                <p class="text-muted mb-0">Total Revenue</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm text-center h-100">
                            <div class="card-body">
                                <i class="fas fa-ticket-alt fa-3x text-primary mb-3"></i>
                                <h3 class="fw-bold"><?php echo number_format($totalTickets); ?></h3>
                                <p class="text-muted mb-0">Total Tickets Sold</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm text-center h-100">
                            <div class="card-body">
                                <i class="fas fa-calendar fa-3x text-warning mb-3"></i>
                                <h3 class="fw-bold"><?php echo number_format($totalEvents); ?></h3>
                                <p class="text-muted mb-0">Total Events</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <!-- Revenue by Event -->
                    <div class="col-lg-8 mb-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="fas fa-money-bill-wave me-2"></i>Revenue by Event</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>Event</th>
                                                <th>Organizer</th>
                                                <th>Tickets Sold</th>
                                                <th>Revenue</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($revenueByEvent as $event): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($event['event_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($event['organizer_name']); ?></td>
                                                    <td><?php echo $event['tickets_sold']; ?></td>
                                                    <td class="fw-bold text-success"><?php echo formatCurrency($event['revenue']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Event Status Summary -->
                    <div class="col-lg-4 mb-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Event Status</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="statusChart"></canvas>
                                <div class="mt-3">
                                    <?php foreach ($eventStatusSummary as $status): ?>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span>
                                                <span class="badge bg-<?php echo getStatusBadgeClass($status['event_status']); ?>">
                                                    <?php echo ucfirst($status['event_status']); ?>
                                                </span>
                                            </span>
                                            <span class="fw-bold"><?php echo $status['count']; ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <!-- Monthly Revenue Chart -->
                    <div class="col-lg-6 mb-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Monthly Revenue</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="revenueChart"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Top Organizers -->
                    <div class="col-lg-6 mb-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="fas fa-trophy me-2"></i>Top Organizers</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>Organizer</th>
                                                <th>Events</th>
                                                <th>Revenue</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($topOrganizers as $org): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($org['full_name']); ?></strong>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($org['company_name'] ?? ''); ?></small>
                                                    </td>
                                                    <td><?php echo $org['events_created']; ?></td>
                                                    <td class="fw-bold text-success"><?php echo formatCurrency($org['total_revenue']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script>
        // Event Status Pie Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_map(function($s) { return ucfirst($s['event_status']); }, $eventStatusSummary)); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_map(function($s) { return $s['count']; }, $eventStatusSummary)); ?>,
                    backgroundColor: ['#198754', '#6c757d', '#dc3545', '#0dcaf0']
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
        
        // Monthly Revenue Chart
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        new Chart(revenueCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_map(function($m) { return $m['month']; }, array_reverse($monthlyRevenue))); ?>,
                datasets: [{
                    label: 'Revenue',
                    data: <?php echo json_encode(array_map(function($m) { return $m['revenue']; }, array_reverse($monthlyRevenue))); ?>,
                    backgroundColor: '#0d6efd'
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
        
        function exportReport() {
            window.location.href = 'export_report.php';
        }
    </script>
    
    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
