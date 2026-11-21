<?php
/**
 * Manage Users - Admin Page
 * Event Management System 007
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(['admin']);

$pageTitle = 'Manage Users - Admin Dashboard';

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken()) {
        setFlashMessage('danger', 'Invalid form submission.');
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'create_organizer') {
            $username = sanitize($_POST['username'] ?? '');
            $email = sanitize($_POST['email'] ?? '');
            $fullName = sanitize($_POST['full_name'] ?? '');
            $password = $_POST['password'] ?? '';
            $email = strtolower($email);
            
            if (strlen($username) < 3 || strlen($username) > 50 || !isValidEmail($email)
                || strlen($email) > 100 || strlen($fullName) < 2 || strlen($fullName) > 100
                || strlen($password) < 12 || strlen($_POST['phone'] ?? '') > 20) {
                $message = 'Use a valid username, email, name, phone number, and password of at least 12 characters.';
                $messageType = 'danger';
            } else {
                try {
                    $sql = "INSERT INTO Organizers_007 (username, password, email, full_name, phone, company_name, approval_status) 
                            VALUES (:username, :password, :email, :full_name, :phone, :company_name, 'approved')
                            RETURNING organizer_id";
                    $stmt = getDbConnection()->prepare($sql);
                    $stmt->execute([
                        ':username' => $username,
                        ':password' => hashPassword($password),
                        ':email' => $email,
                        ':full_name' => $fullName,
                        ':phone' => $_POST['phone'] ?? '',
                        ':company_name' => $_POST['company_name'] ?? ''
                    ]);
                    
                    $newId = $stmt->fetchColumn();
                    logAuditAction(getCurrentUserId(), 'admin', 'INSERT', 'Organizers_007', $newId, [], [
                        'username' => $username, 'email' => $email, 'full_name' => $fullName,
                        'phone' => $_POST['phone'] ?? '', 'company_name' => $_POST['company_name'] ?? ''
                    ]);
                    
                    $message = 'Organizer created and approved successfully!';
                    $messageType = 'success';
                } catch (Exception $e) {
                    $message = 'Error creating organizer: ' . $e->getMessage();
                    $messageType = 'danger';
                }
            }
        } elseif ($action === 'approve_organizer') {
            $organizerId = (int)($_POST['organizer_id'] ?? 0);
            
            if ($organizerId > 0) {
                try {
                    $oldData = fetchOne("SELECT * FROM Organizers_007 WHERE organizer_id = :organizer_id", [':organizer_id' => $organizerId]);
                    $sql = "UPDATE Organizers_007 SET approval_status = 'approved' WHERE organizer_id = :organizer_id";
                    $stmt = getDbConnection()->prepare($sql);
                    $stmt->execute([':organizer_id' => $organizerId]);
                    logAuditAction(getCurrentUserId(), 'admin', 'APPROVE', 'Organizers_007', $organizerId, $oldData, ['approval_status' => 'approved']);
                    
                    $message = 'Organizer approved successfully!';
                    $messageType = 'success';
                } catch (Exception $e) {
                    $message = 'Error approving organizer: ' . $e->getMessage();
                    $messageType = 'danger';
                }
            }
        } elseif ($action === 'reject_organizer') {
            $organizerId = (int)($_POST['organizer_id'] ?? 0);
            
            if ($organizerId > 0) {
                try {
                    $oldData = fetchOne("SELECT * FROM Organizers_007 WHERE organizer_id = :organizer_id", [':organizer_id' => $organizerId]);
                    $sql = "UPDATE Organizers_007 SET approval_status = 'rejected' WHERE organizer_id = :organizer_id";
                    $stmt = getDbConnection()->prepare($sql);
                    $stmt->execute([':organizer_id' => $organizerId]);
                    logAuditAction(getCurrentUserId(), 'admin', 'REJECT', 'Organizers_007', $organizerId, $oldData, ['approval_status' => 'rejected']);
                    
                    $message = 'Organizer rejected.';
                    $messageType = 'warning';
                } catch (Exception $e) {
                    $message = 'Error rejecting organizer: ' . $e->getMessage();
                    $messageType = 'danger';
                }
            }
        } elseif ($action === 'ban_organizer') {
            $organizerId = (int)($_POST['organizer_id'] ?? 0);
            
            if ($organizerId > 0) {
                try {
                    $oldData = fetchOne("SELECT * FROM Organizers_007 WHERE organizer_id = :organizer_id", [':organizer_id' => $organizerId]);
                    $sql = "UPDATE Organizers_007 SET approval_status = 'banned' WHERE organizer_id = :organizer_id";
                    $stmt = getDbConnection()->prepare($sql);
                    $stmt->execute([':organizer_id' => $organizerId]);
                    logAuditAction(getCurrentUserId(), 'admin', 'BAN', 'Organizers_007', $organizerId, $oldData, ['approval_status' => 'banned']);
                    
                    $message = 'Organizer has been banned.';
                    $messageType = 'warning';
                } catch (Exception $e) {
                    $message = 'Error banning organizer.';
                    $messageType = 'danger';
                }
            }
        } elseif ($action === 'delete_organizer') {
            $organizerId = (int)($_POST['organizer_id'] ?? 0);
            
            if ($organizerId > 0) {
                try {
                    $oldData = fetchOne("SELECT * FROM Organizers_007 WHERE organizer_id = :organizer_id", [':organizer_id' => $organizerId]);
                    $stmt = getDbConnection()->prepare("DELETE FROM Organizers_007 WHERE organizer_id = :organizer_id");
                    $stmt->execute([':organizer_id' => $organizerId]);
                    logAuditAction(getCurrentUserId(), 'admin', 'DELETE', 'Organizers_007', $organizerId, $oldData, []);
                    
                    $message = 'Organizer deleted successfully!';
                    $messageType = 'success';
                } catch (Exception $e) {
                    $message = 'Error deleting organizer.';
                    $messageType = 'danger';
                }
            }
        }
    }
}

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Paginate account listings and never load password hashes into the page.
try {
    $organizerCount = (int)(fetchOne('SELECT COUNT(*) AS count FROM Organizers_007')['count'] ?? 0);
    $organizers = fetchAll("SELECT organizer_id, username, email, full_name, company_name, approval_status, created_at
        FROM Organizers_007 ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
} catch (Exception $e) {
    $organizerCount = 0;
    $organizers = [];
}

try {
    $attendeeCount = (int)(fetchOne('SELECT COUNT(*) AS count FROM Attendees_007')['count'] ?? 0);
    $attendees = fetchAll("SELECT attendee_id, full_name, email, phone, loyalty_points, created_at
        FROM Attendees_007 ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
} catch (Exception $e) {
    $attendeeCount = 0;
    $attendees = [];
}
$totalPages = max(1, (int)ceil(max($organizerCount, $attendeeCount) / $perPage));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link href="<?php echo getAssetUrl('css/main.css'); ?>" rel="stylesheet">
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
                            <a class="nav-link active" href="manage_users.php">
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
                    <h1 class="h2"><i class="fas fa-users-cog me-2"></i>Manage Users</h1>
                </div>
                
                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Pending Organizers -->
                <?php 
                $pendingOrganizers = array_filter($organizers, function($o) { return ($o['approval_status'] ?? 'pending') === 'pending'; });
                if (!empty($pendingOrganizers)): 
                ?>
                    <div class="card border-0 shadow-sm mb-4 border-warning">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0"><i class="fas fa-user-clock me-2"></i>Pending Organizer Approvals (<?php echo count($pendingOrganizers); ?>)</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Username</th>
                                            <th>Email</th>
                                            <th>Full Name</th>
                                            <th>Company</th>
                                            <th>Registered</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pendingOrganizers as $org): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($org['username']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($org['email']); ?></td>
                                                <td><?php echo htmlspecialchars($org['full_name']); ?></td>
                                                <td><?php echo htmlspecialchars($org['company_name'] ?? '-'); ?></td>
                                                <td><?php echo formatDate($org['created_at']); ?></td>
                                                <td>
                                                    <form method="POST" class="d-inline">
                                                        <?php echo csrfTokenField(); ?>
                                                        <input type="hidden" name="action" value="approve_organizer">
                                                        <input type="hidden" name="organizer_id" value="<?php echo $org['organizer_id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-success">
                                                            <i class="fas fa-check"></i> Approve
                                                        </button>
                                                    </form>
                                                    <form method="POST" class="d-inline">
                                                        <?php echo csrfTokenField(); ?>
                                                        <input type="hidden" name="action" value="reject_organizer">
                                                        <input type="hidden" name="organizer_id" value="<?php echo $org['organizer_id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger">
                                                            <i class="fas fa-times"></i> Reject
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Create New Organizer -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Create New Organizer</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <?php echo csrfTokenField(); ?>
                            <input type="hidden" name="action" value="create_organizer">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Username *</label>
                                    <input type="text" class="form-control" name="username" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email *</label>
                                    <input type="email" class="form-control" name="email" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Full Name *</label>
                                    <input type="text" class="form-control" name="full_name" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Phone</label>
                                    <input type="text" class="form-control" name="phone">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Company Name</label>
                                    <input type="text" class="form-control" name="company_name">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Password *</label>
                                    <input type="password" class="form-control" name="password" required minlength="12" autocomplete="new-password">
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-plus me-1"></i>Create & Approve Organizer
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Organizers List -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="fas fa-list me-2"></i>All Organizers</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Full Name</th>
                                        <th>Company</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($organizers as $org): ?>
                                        <tr>
                                            <td><?php echo $org['organizer_id']; ?></td>
                                            <td><strong><?php echo htmlspecialchars($org['username']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($org['email']); ?></td>
                                            <td><?php echo htmlspecialchars($org['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($org['company_name'] ?? '-'); ?></td>
                                            <td>
                                                <?php 
                                                $status = $org['approval_status'] ?? 'approved';
                                                $statusClasses = [
                                                    'approved' => 'success',
                                                    'pending' => 'warning',
                                                    'rejected' => 'danger',
                                                    'banned' => 'dark'
                                                ];
                                                ?>
                                                <span class="badge bg-<?php echo $statusClasses[$status] ?? 'secondary'; ?>">
                                                    <?php echo ucfirst($status); ?>
                                                </span>
                                            </td>
                                            <td><?php echo formatDate($org['created_at']); ?></td>
                                            <td>
                                                <?php if ($status === 'approved'): ?>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to BAN this organizer? They will not be able to login.');">
                                                        <?php echo csrfTokenField(); ?>
                                                        <input type="hidden" name="action" value="ban_organizer">
                                                        <input type="hidden" name="organizer_id" value="<?php echo $org['organizer_id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-dark">
                                                            <i class="fas fa-ban"></i> Ban
                                                        </button>
                                                    </form>
                                                <?php elseif ($status === 'banned'): ?>
                                                    <form method="POST" class="d-inline">
                                                        <?php echo csrfTokenField(); ?>
                                                        <input type="hidden" name="action" value="approve_organizer">
                                                        <input type="hidden" name="organizer_id" value="<?php echo $org['organizer_id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-success">
                                                            <i class="fas fa-check"></i> Unban
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to DELETE this organizer? This cannot be undone.');">
                                                    <?php echo csrfTokenField(); ?>
                                                    <input type="hidden" name="action" value="delete_organizer">
                                                    <input type="hidden" name="organizer_id" value="<?php echo $org['organizer_id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Attendees List -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="fas fa-users me-2"></i>All Attendees</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Loyalty Points</th>
                                        <th>Registered</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($attendees as $att): ?>
                                        <tr>
                                            <td><?php echo $att['attendee_id']; ?></td>
                                            <td><strong><?php echo htmlspecialchars($att['full_name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($att['email']); ?></td>
                                            <td><?php echo htmlspecialchars($att['phone'] ?? '-'); ?></td>
                                            <td>
                                                <span class="badge bg-info"><?php echo number_format($att['loyalty_points'] ?? 0); ?> pts</span>
                                                <small class="text-muted ms-1"><?php echo getLoyaltyTier($att['loyalty_points'] ?? 0); ?></small>
                                            </td>
                                            <td><?php echo formatDate($att['created_at']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php if ($totalPages > 1): ?>
                    <nav class="mt-4" aria-label="User list pages"><ul class="pagination justify-content-center">
                        <?php for ($pageNumber = 1; $pageNumber <= $totalPages; $pageNumber++): ?>
                            <li class="page-item <?php echo $pageNumber === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $pageNumber; ?>"><?php echo $pageNumber; ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul></nav>
                <?php endif; ?>
            </main>
        </div>
    </div>
    
    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
