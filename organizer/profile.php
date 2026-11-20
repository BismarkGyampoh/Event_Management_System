<?php
/**
 * Profile - Organizer Page
 * Event Management System 007
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(['organizer']);

$pageTitle = 'My Profile - Organizer Dashboard';
$organizerId = getCurrentUserId();

$message = '';
$messageType = '';

// Get current user data
$user = fetchOne("SELECT * FROM Organizers_007 WHERE organizer_id = :organizer_id", [':organizer_id' => $organizerId]);

if (!$user) {
    setFlashMessage('danger', 'User not found.');
    redirect('dashboard.php');
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    if (!validateCsrfToken()) {
        $message = 'Invalid form submission.';
        $messageType = 'danger';
    } else {
        $fullName = sanitize($_POST['full_name'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $companyName = sanitize($_POST['company_name'] ?? '');
        
        if (empty($fullName)) {
            $message = 'Full name is required.';
            $messageType = 'danger';
        } else {
            try {
                $sql = "UPDATE Organizers_007 SET full_name = :full_name, phone = :phone, company_name = :company_name WHERE organizer_id = :organizer_id";
                $stmt = getDbConnection()->prepare($sql);
                $stmt->execute([
                    ':full_name' => $fullName,
                    ':phone' => $phone,
                    ':company_name' => $companyName,
                    ':organizer_id' => $organizerId
                ]);
                
                // Update session
                $_SESSION['user_name'] = $fullName;
                
                logAuditAction($organizerId, 'organizer', 'UPDATE', 'Organizers_007', $organizerId, $user, $_POST);
                
                // Refresh user data
                $user = fetchOne("SELECT * FROM Organizers_007 WHERE organizer_id = :organizer_id", [':organizer_id' => $organizerId]);
                
                $message = 'Profile updated successfully!';
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Error updating profile: ' . $e->getMessage();
                $messageType = 'danger';
            }
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    if (!validateCsrfToken()) {
        $message = 'Invalid form submission.';
        $messageType = 'danger';
    } else {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $message = 'All password fields are required.';
            $messageType = 'danger';
        } elseif ($newPassword !== $confirmPassword) {
            $message = 'New passwords do not match.';
            $messageType = 'danger';
        } elseif (strlen($newPassword) < 12) {
            $message = 'Password must be at least 12 characters.';
            $messageType = 'danger';
        } elseif (!verifyPassword($currentPassword, $user['password'])) {
            $message = 'Current password is incorrect.';
            $messageType = 'danger';
        } else {
            try {
                $hashedPassword = hashPassword($newPassword);
                $sql = "UPDATE Organizers_007 SET password = :password WHERE organizer_id = :organizer_id";
                $stmt = getDbConnection()->prepare($sql);
                $stmt->execute([
                    ':password' => $hashedPassword,
                    ':organizer_id' => $organizerId
                ]);
                
                logAuditAction($organizerId, 'organizer', 'UPDATE_PASSWORD', 'Organizers_007', $organizerId);
                
                $message = 'Password changed successfully!';
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Error changing password.';
                $messageType = 'danger';
            }
        }
    }
}

// Get organizer statistics
try {
    $stats = [
        'total_events' => fetchOne("SELECT COUNT(*) as count FROM Events_007 WHERE organizer_id = :organizer_id", [':organizer_id' => $organizerId])['count'] ?? 0,
        'total_tickets_sold' => fetchOne("
            SELECT COUNT(*) as count FROM Tickets_007 t
            INNER JOIN Events_007 e ON t.event_id = e.event_id
            WHERE e.organizer_id = :organizer_id AND t.ticket_status != 'cancelled'
        ", [':organizer_id' => $organizerId])['count'] ?? 0,
        'total_revenue' => fetchOne("
            SELECT COALESCE(SUM(amount), 0) as total FROM Payments_007 p
            INNER JOIN Tickets_007 t ON p.ticket_id = t.ticket_id
            INNER JOIN Events_007 e ON t.event_id = e.event_id
            WHERE e.organizer_id = :organizer_id AND p.payment_status = 'completed'
        ", [':organizer_id' => $organizerId])['total'] ?? 0
    ];
} catch (Exception $e) {
    $stats = ['total_events' => 0, 'total_tickets_sold' => 0, 'total_revenue' => 0];
}
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
                            <a class="nav-link" href="my_events.php">
                                <i class="fas fa-calendar me-2"></i>My Events
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="create_event.php">
                                <i class="fas fa-plus-circle me-2"></i>Create Event
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="view_booking.php">
                                <i class="fas fa-ticket-alt me-2"></i>Bookings
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
                
                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-lg-4">
                        <!-- Profile Card -->
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <i class="fas fa-user-tie fa-5x text-primary"></i>
                                </div>
                                <h4 class="fw-bold"><?php echo htmlspecialchars($user['full_name']); ?></h4>
                                <p class="text-muted mb-1">@<?php echo htmlspecialchars($user['username']); ?></p>
                                <span class="badge bg-primary fs-6">Event Organizer</span>
                            </div>
                        </div>
                        
                        <!-- Statistics -->
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Statistics</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Total Events</span>
                                    <strong><?php echo $stats['total_events']; ?></strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Tickets Sold</span>
                                    <strong><?php echo $stats['total_tickets_sold']; ?></strong>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Total Revenue</span>
                                    <strong class="text-success"><?php echo formatCurrency($stats['total_revenue']); ?></strong>
                                </div>
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
                                    <span class="float-end"><?php echo htmlspecialchars($user['email']); ?></span>
                                </div>
                                <div class="mb-2">
                                    <strong>Phone:</strong>
                                    <span class="float-end"><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="mb-2">
                                    <strong>Company:</strong>
                                    <span class="float-end"><?php echo htmlspecialchars($user['company_name'] ?? 'N/A'); ?></span>
                                </div>
                                <div>
                                    <strong>Joined:</strong>
                                    <span class="float-end"><?php echo formatDate($user['created_at']); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-8">
                        <!-- Edit Profile -->
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Edit Profile</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <?php echo csrfTokenField(); ?>
                                    <input type="hidden" name="action" value="update_profile">
                                    
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Full Name *</label>
                                            <input type="text" class="form-control" name="full_name" 
                                                   value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label class="form-label">Username</label>
                                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                                            <small class="text-muted">Username cannot be changed</small>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label class="form-label">Email</label>
                                            <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                                            <small class="text-muted">Email cannot be changed</small>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label class="form-label">Phone</label>
                                            <input type="tel" class="form-control" name="phone" 
                                                   value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                        </div>
                                        
                                        <div class="col-12">
                                            <label class="form-label">Company Name</label>
                                            <input type="text" class="form-control" name="company_name" 
                                                   value="<?php echo htmlspecialchars($user['company_name'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="mt-3">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-1"></i>Save Changes
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Change Password -->
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="fas fa-lock me-2"></i>Change Password</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <?php echo csrfTokenField(); ?>
                                    <input type="hidden" name="action" value="change_password">
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Current Password</label>
                                        <input type="password" class="form-control" name="current_password" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">New Password</label>
                                        <input type="password" class="form-control" name="new_password" required minlength="12" autocomplete="new-password">
                                        <small class="text-muted">Minimum 12 characters</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Confirm New Password</label>
                                        <input type="password" class="form-control" name="confirm_password" required>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-warning">
                                        <i class="fas fa-key me-1"></i>Change Password
                                    </button>
                                </form>
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
