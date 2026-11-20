<?php
/**
 * Profile - Attendee Page
 * Event Management System 007
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(['attendee']);

$pageTitle = 'My Profile - Event Management System';
$attendeeId = getCurrentUserId();

$message = '';
$messageType = '';

// Get current user data
$user = fetchOne("SELECT * FROM Attendees_007 WHERE attendee_id = :attendee_id", [':attendee_id' => $attendeeId]);

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
        $address = sanitize($_POST['address'] ?? '');
        
        if (empty($fullName)) {
            $message = 'Full name is required.';
            $messageType = 'danger';
        } else {
            try {
                $sql = "UPDATE Attendees_007 SET full_name = :full_name, phone = :phone, address = :address WHERE attendee_id = :attendee_id";
                $stmt = getDbConnection()->prepare($sql);
                $stmt->execute([
                    ':full_name' => $fullName,
                    ':phone' => $phone,
                    ':address' => $address,
                    ':attendee_id' => $attendeeId
                ]);
                
                // Update session
                $_SESSION['user_name'] = $fullName;
                
                logAuditAction($attendeeId, 'attendee', 'UPDATE', 'Attendees_007', $attendeeId, $user, $_POST);
                
                // Refresh user data
                $user = fetchOne("SELECT * FROM Attendees_007 WHERE attendee_id = :attendee_id", [':attendee_id' => $attendeeId]);
                
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
                $sql = "UPDATE Attendees_007 SET password = :password WHERE attendee_id = :attendee_id";
                $stmt = getDbConnection()->prepare($sql);
                $stmt->execute([
                    ':password' => $hashedPassword,
                    ':attendee_id' => $attendeeId
                ]);
                
                logAuditAction($attendeeId, 'attendee', 'UPDATE_PASSWORD', 'Attendees_007', $attendeeId);
                
                $message = 'Password changed successfully!';
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Error changing password.';
                $messageType = 'danger';
            }
        }
    }
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
                            <a class="nav-link" href="browse_events.php">
                                <i class="fas fa-search me-2"></i>Browse Events
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="my_bookings.php">
                                <i class="fas fa-ticket-alt me-2"></i>My Bookings
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
                    <!-- Profile Info -->
                    <div class="col-lg-8">
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="fas fa-user-edit me-2"></i>Edit Profile</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <?php echo csrfTokenField(); ?>
                                    <input type="hidden" name="action" value="update_profile">
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Email Address</label>
                                        <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                                        <small class="text-muted">Email cannot be changed.</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Full Name *</label>
                                        <input type="text" class="form-control" name="full_name" 
                                               value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Phone</label>
                                        <input type="text" class="form-control" name="phone" 
                                               value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Address</label>
                                        <textarea class="form-control" name="address" rows="2"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i>Update Profile
                                    </button>
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
                                        <small class="text-muted">Current password is "password" for demo accounts.</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">New Password</label>
                                        <input type="password" class="form-control" name="new_password" required minlength="12">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Confirm New Password</label>
                                        <input type="password" class="form-control" name="confirm_password" required minlength="12">
                                    </div>
                                    
                                    <button type="submit" class="btn btn-warning">
                                        <i class="fas fa-key me-1"></i>Change Password
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sidebar -->
                    <div class="col-lg-4">
                        <!-- Loyalty Card -->
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-header bg-warning text-dark">
                                <h5 class="mb-0"><i class="fas fa-star me-2"></i>Loyalty Program</h5>
                            </div>
                            <div class="card-body text-center">
                                <div class="display-4 fw-bold text-warning mb-2">
                                    <?php echo number_format($user['loyalty_points'] ?? 0); ?>
                                </div>
                                <p class="text-muted mb-3">Loyalty Points</p>
                                
                                <div class="badge bg-primary fs-6 mb-3">
                                    <?php echo getLoyaltyTier($user['loyalty_points'] ?? 0); ?> Tier
                                </div>
                                
                                <hr>
                                
                                <div class="text-start">
                                    <p class="mb-2"><small><strong>Benefits:</strong></small></p>
                                    <ul class="list-unstyled small text-muted">
                                        <li><i class="fas fa-check text-success me-1"></i>10 points per booking</li>
                                        <li><i class="fas fa-check text-success me-1"></i>Bronze: 0+ points</li>
                                        <li><i class="fas fa-check text-success me-1"></i>Silver: 100+ points</li>
                                        <li><i class="fas fa-check text-success me-1"></i>Gold: 500+ points</li>
                                        <li><i class="fas fa-check text-success me-1"></i>Platinum: 1000+ points</li>
                                    </ul>
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
                                    <small class="text-muted">Member Since</small>
                                    <br><strong><?php echo formatDate($user['created_at']); ?></strong>
                                </div>
                                <div class="mb-2">
                                    <small class="text-muted">Last Updated</small>
                                    <br><strong><?php echo formatDate($user['updated_at']); ?></strong>
                                </div>
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
