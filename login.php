<?php
/**
 * Login Page
 * Event Management System 007
 * Auto-detects user role (Admin, Organizer, or Attendee)
 */

error_reporting(E_ALL);
$debugMode = getenv('APP_DEBUG') === '1'
    && in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
ini_set('display_errors', $debugMode ? '1' : '0');

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// Debug: Test database connection
if ($debugMode && isset($_GET['test_db'])) {
    $debugClientAddress = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!in_array($debugClientAddress, ['127.0.0.1', '::1'], true)) {
        http_response_code(404);
        exit;
    }

    try {
        $pdo = getDbConnection();
        echo "<div class='alert alert-success'><strong>✓ Database Connection Successful!</strong></div>";
        
        $stmt = $pdo->query("SELECT current_database() AS current_db");
        $db = $stmt->fetch();
        echo "<div class='alert alert-info'>Current Database: <strong>" . $db['current_db'] . "</strong></div>";
        
        // Check if Organizers_007 table exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = current_schema() AND table_name = lower(?)");
        $stmt->execute(['Organizers_007']);
        $tableExists = $stmt->fetchColumn() > 0;
        
        if ($tableExists) {
            echo "<div class='alert alert-success'>✓ Organizers_007 table exists</div>";
            
            $stmt = $pdo->query("SELECT COUNT(*) FROM Organizers_007");
            $count = $stmt->fetchColumn();
            echo "<div class='alert alert-info'>Organizers count: <strong>$count</strong></div>";
            
            if ($count > 0) {
                $stmt = $pdo->query("SELECT organizer_id, username, email, full_name FROM Organizers_007");
                $organizers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo "<div class='alert alert-info'><strong>Organizers in database:</strong><br><pre>";
                foreach ($organizers as $org) {
                    echo "ID: {$org['organizer_id']}, Username: {$org['username']}, Email: {$org['email']}, Name: {$org['full_name']}\n";
                }
                echo "</pre></div>";
            } else {
                echo "<div class='alert alert-warning'><strong>⚠ Organizers_007 table is empty!</strong><br>";
                echo "Create an organizer account before trying to log in.</div>";
            }
        } else {
            echo "<div class='alert alert-danger'><strong>✗ Organizers_007 table does NOT exist!</strong><br>";
            echo "Run database_setup_postgresql.sql in your PostgreSQL database.</div>";
        }
        
        // Check other tables
        $tables = ['Events_007', 'Attendees_007', 'Venues_007', 'Tickets_007'];
        echo "<div class='alert alert-info'><strong>Table Status:</strong><br>";
        foreach ($tables as $table) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = current_schema() AND table_name = lower(?)");
            $stmt->execute([$table]);
            $exists = $stmt->fetchColumn() > 0;
            echo ($exists ? "✓" : "✗") . " $table " . ($exists ? "exists" : "MISSING") . "<br>";
        }
        echo "</div>";
        
    } catch (Exception $e) {
        echo "<div class='alert alert-danger'><strong>✗ Database Error:</strong> " . $e->getMessage() . "<br>";
        echo "<br><strong>Troubleshooting:</strong><br>";
        echo "1. Confirm the PostgreSQL server is running<br>";
        echo "2. Check DB_HOST, DB_PORT, DB_NAME, DB_USER and DB_PASSWORD<br>";
        echo "3. Run database_setup_postgresql.sql in the configured database<br>";
        echo "4. Ensure PHP PDO_PGSQL is enabled</div>";
    }
}

// Redirect if already logged in
if (isLoggedIn()) {
    $userRole = getCurrentUserRole();
    switch ($userRole) {
        case 'admin':
            redirect(getBaseUrl() . 'admin/dashboard.php');
            break;
        case 'organizer':
            redirect(getBaseUrl() . 'organizer/dashboard.php');
            break;
        case 'attendee':
            redirect(getBaseUrl() . 'attendee/dashboard.php');
            break;
        default:
            redirect(getBaseUrl());
    }
}

$error = '';
$identifier = ''; // Can be username or email

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!validateCsrfToken()) {
        error_log("CSRF token validation failed");
        setFlashMessage('danger', 'Invalid form submission. Please try again.');
        redirect(getBaseUrl() . 'login.php');
    }
    
    $identifier = sanitize($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($identifier)) {
        $error = 'Please enter your username or email.';
    } elseif (empty($password)) {
        $error = 'Please enter your password.';
    } elseif (isLoginRateLimited()) {
        http_response_code(429);
        $error = 'Too many unsuccessful attempts. Wait 15 minutes and try again.';
    } else {
        // Auto-detect user type and authenticate
        try {
            $pdo = getDbConnection();
            $user = null;
            $userType = null;
            
            // Admin credentials are supplied through the hosting environment.
            $adminUsername = getenv('APP_ADMIN_USERNAME');
            $adminPassword = getenv('APP_ADMIN_PASSWORD');
            if ($adminUsername !== false && $adminUsername !== ''
                && $adminPassword !== false && $adminPassword !== ''
                && hash_equals($adminUsername, $identifier)
                && hash_equals($adminPassword, $password)) {
                $user = [
                    'id' => 1,
                    'name' => 'System Administrator',
                    'email' => ''
                ];
                $userType = 'admin';
            }
            
            // 2. Check Organizer credentials (username or email)
            if ($user === null) {
                $sql = "SELECT organizer_id, username, password, full_name, email, approval_status 
                        FROM Organizers_007 
                        WHERE username = :identifier OR LOWER(email) = :identifier2";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':identifier' => $identifier, ':identifier2' => strtolower($identifier)]);
                $organizer = $stmt->fetch();
                
                // Debug: Check if query returned results
                if ($debugMode) {
                    error_log("Organizer query executed. Found: " . ($organizer ? 'Yes' : 'No'));
                    echo "<div class='alert alert-info'><strong>Debug:</strong> Organizer found: " . ($organizer ? 'Yes' : 'No') . "</div>";
                }
                
                if ($organizer) {
                    $passwordValid = password_verify($password, $organizer['password']);
                    
                    if ($passwordValid) {
                        // Check approval status
                        $approvalStatus = $organizer['approval_status'] ?? 'approved';
                        if ($approvalStatus === 'pending') {
                            $error = 'Your account is pending approval. Please wait for an administrator to approve your account.';
                        } elseif ($approvalStatus === 'rejected') {
                            $error = 'Your account application has been rejected. Please contact support.';
                        } elseif ($approvalStatus === 'banned') {
                            $error = 'Your account has been banned. Please contact support for assistance.';
                        } else {
                            $user = [
                                'id' => $organizer['organizer_id'],
                                'name' => $organizer['full_name'],
                                'email' => $organizer['email']
                            ];
                            $userType = 'organizer';
                            error_log("Organizer login successful for: " . $organizer['username']);
                        }
                    } else {
                        error_log("Password verification failed for organizer: " . $organizer['username']);
                    }
                } else {
                    if ($debugMode) {
                        error_log("No organizer found with identifier: " . $identifier);
                        echo "<div class='alert alert-warning'><strong>Debug:</strong> No organizer found with identifier: " . htmlspecialchars($identifier) . "</div>";
                    }
                }
            }
            
            // 3. Check Attendee credentials (email)
            if ($user === null) {
                $sql = "SELECT attendee_id, email, password, full_name, loyalty_points 
                        FROM Attendees_007 
                        WHERE LOWER(email) = :email";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':email' => strtolower($identifier)]);
                $attendee = $stmt->fetch();
                
                if ($attendee) {
                    $passwordValid = password_verify($password, $attendee['password']);
                    
                    if ($passwordValid) {
                        $user = [
                            'id' => $attendee['attendee_id'],
                            'name' => $attendee['full_name'],
                            'email' => $attendee['email'],
                            'loyalty_points' => $attendee['loyalty_points']
                        ];
                        $userType = 'attendee';
                    }
                }
            }
            
            // Set session based on detected user type
            if ($user !== null && $userType !== null) {
                clearLoginFailures();
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_role'] = $userType;
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                
                if ($userType === 'attendee' && isset($user['loyalty_points'])) {
                    $_SESSION['loyalty_points'] = $user['loyalty_points'];
                }
                
                // Log the login action
                // Map user types to their actual table names (no 'Admins_007' table exists)
                $auditTableMap = [
                    'admin'     => 'System',
                    'organizer' => 'Organizers_007',
                    'attendee'  => 'Attendees_007'
                ];
                $auditTable = $auditTableMap[$userType] ?? 'System';
                logAuditAction($user['id'], $userType, 'LOGIN', $auditTable, $user['id']);
                
                // Redirect based on user type
                switch ($userType) {
                    case 'admin':
                        redirect(getBaseUrl() . 'admin/dashboard.php');
                        break;
                    case 'organizer':
                        redirect(getBaseUrl() . 'organizer/dashboard.php');
                        break;
                    case 'attendee':
                        redirect(getBaseUrl() . 'attendee/dashboard.php');
                        break;
                }
            } else {
                recordLoginFailure();
                $error = 'Invalid username/email or password. Please try again.';
            }
            
        } catch (Exception $e) {
            error_log('Login failed because of an internal application error.');
            $error = 'An error occurred. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Event Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.9/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-yEPZ7jP9S0QKiUQ0m3jjsO4ProIW7wvR9gXkI7JOGa2XVsw53Yke2bsdkBbjmmiX" crossorigin="anonymous">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo getAssetUrl('css/style.css'); ?>" rel="stylesheet">
</head>
<body class="auth-page d-flex align-items-center py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="login-card p-5">
                    <div class="text-center mb-4">
                        <i class="fas fa-calendar-alt fa-4x text-primary mb-3"></i>
                        <h3 class="fw-bold">EventMS</h3>
                        <p class="text-muted">Sign in to your account</p>
                        <small class="text-muted fst-italic">
                            <i class="fas fa-info-circle me-1"></i>
                            Your role will be detected automatically
                        </small>
                    </div>
                    
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <?php echo csrfTokenField(); ?>
                        
                        <div class="mb-3">
                            <label class="form-label">Username or Email</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                <input type="text" class="form-control" id="identifier" name="identifier" 
                                       placeholder="Enter username or email" 
                                       value="<?php echo htmlspecialchars($identifier); ?>" required>
                            </div>
                            <small class="form-text text-muted">
                                Enter your username (for organizers/admins) or email (for attendees)
                            </small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" 
                                       placeholder="Enter password" required>
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="remember" name="remember">
                            <label class="form-check-label" for="remember">Remember me</label>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100 py-2">
                            <i class="fas fa-sign-in-alt me-2"></i>Sign In
                        </button>
                    </form>
                    
                    <p class="text-center text-muted mt-4 mb-0">
                        New to EventMS?
                        <a href="<?php echo getBaseUrl(); ?>register.php">Create an attendee account</a>
                    </p>
                    <div class="text-center mt-4">
                        <a href="<?php echo getBaseUrl(); ?>" class="text-muted text-decoration-none">
                            <i class="fas fa-arrow-left me-1"></i>Back to Home
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.9/dist/js/bootstrap.bundle.min.js" integrity="sha384-WyOxWvTyr1QaMPfiPJSv76iReVthorl5uWlFtZPyK0IEB0hGzo9y5Bp9mYanr8Bu" crossorigin="anonymous"></script>
    <script src="<?php echo getAssetUrl('js/script.js'); ?>"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const togglePassword = document.getElementById('togglePassword');
            const password = document.getElementById('password');
            
            togglePassword.addEventListener('click', function() {
                const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
                password.setAttribute('type', type);
                this.querySelector('i').classList.toggle('fa-eye');
                this.querySelector('i').classList.toggle('fa-eye-slash');
            });
        });
    </script>
</body>
</html>
