<?php
/**
 * Profile - Admin Page
 * Event Management System 007
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(['admin']);

$pageTitle = 'My Profile - Admin Dashboard';
$adminId = getCurrentUserId();

$admin = [
    'id' => $adminId,
    'username' => getenv('APP_ADMIN_USERNAME') ?: 'Configured in hosting environment',
    'email' => 'Managed through hosting configuration',
    'full_name' => 'System Administrator',
    'role' => 'Administrator'
];
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
                            <a class="nav-link active" href="profile.php">
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
                    <h1 class="h2"><i class="fas fa-user me-2"></i>My Profile</h1>
                </div>
                
                <div class="row">
                    <div class="col-lg-4">
                        <!-- Profile Card -->
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <i class="fas fa-user-shield fa-5x text-primary"></i>
                                </div>
                                <h4 class="fw-bold"><?php echo htmlspecialchars($admin['full_name']); ?></h4>
                                <p class="text-muted mb-1">@<?php echo htmlspecialchars($admin['username']); ?></p>
                                <span class="badge bg-danger fs-6">Administrator</span>
                            </div>
                        </div>
                        
                        <!-- Account Info -->
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Account Info</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-2">
                                    <strong>Email:</strong>
                                    <span class="float-end"><?php echo htmlspecialchars($admin['email']); ?></span>
                                </div>
                                <div class="mb-2">
                                    <strong>Role:</strong>
                                    <span class="float-end"><span class="badge bg-danger">Admin</span></span>
                                </div>
                                <div>
                                    <strong>Status:</strong>
                                    <span class="float-end"><span class="badge bg-success">Active</span></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-8">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="fas fa-lock me-2"></i>Administrator credentials</h5>
                            </div>
                            <div class="card-body">
                                <p>Administrator credentials are provided by the hosting environment.</p>
                                <p class="mb-0">Rotate them in your hosting secret settings, then redeploy. This page does not collect or change passwords.</p>
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
