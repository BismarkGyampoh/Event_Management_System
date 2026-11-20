<?php
/**
 * Header Template
 * Event Management System 007
 */

// Start session and include database connection
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$userRole = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : '';
$userName = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : '';

// Get current page for active navigation
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="<?php echo getBaseUrl(); ?>">
                <i class="fas fa-calendar-alt me-2"></i>EventMS
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                    aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <!-- Shared navigation; role-specific links live in each dashboard sidebar. -->
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage == 'index' ? 'active' : ''; ?>" 
                           href="<?php echo getBaseUrl(); ?>">
                            <i class="fas fa-home me-1"></i>Home
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage == 'browse_events' ? 'active' : ''; ?>" href="<?php echo getBaseUrl(); ?>browse_events.php">
                            <i class="fas fa-compass me-1"></i>Explore
                        </a>
                    </li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo getBaseUrl(); ?>#popular-categories">Categories</a></li>
                </ul>
                
                <!-- User Menu -->
                <ul class="navbar-nav">
                    <?php if ($isLoggedIn): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user-circle me-1"></i>
                                <?php echo htmlspecialchars($userName); ?>
                                <span class="badge bg-secondary ms-1"><?php echo ucfirst($userRole); ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <?php if ($userRole === 'admin'): ?>
                                    <li><a class="dropdown-item" href="<?php echo getBaseUrl(); ?>admin/dashboard.php"><i class="fas fa-gauge-high me-2"></i>Dashboard</a></li>
                                <?php elseif ($userRole === 'organizer'): ?>
                                    <li><a class="dropdown-item" href="<?php echo getBaseUrl(); ?>organizer/dashboard.php"><i class="fas fa-gauge-high me-2"></i>Dashboard</a></li>
                                <?php elseif ($userRole === 'attendee'): ?>
                                    <li><a class="dropdown-item" href="<?php echo getBaseUrl(); ?>attendee/dashboard.php"><i class="fas fa-gauge-high me-2"></i>Dashboard</a></li>
                                <?php endif; ?>
                                <?php if ($userRole === 'admin'): ?>
                                    <li>
                                        <a class="dropdown-item" href="<?php echo getBaseUrl(); ?>admin/profile.php">
                                            <i class="fas fa-id-card me-2"></i>My Profile
                                        </a>
                                    </li>
                                <?php elseif ($userRole === 'organizer'): ?>
                                    <li>
                                        <a class="dropdown-item" href="<?php echo getBaseUrl(); ?>organizer/profile.php">
                                            <i class="fas fa-id-card me-2"></i>My Profile
                                        </a>
                                    </li>
                                <?php elseif ($userRole === 'attendee'): ?>
                                    <li>
                                        <a class="dropdown-item" href="<?php echo getBaseUrl(); ?>attendee/profile.php">
                                            <i class="fas fa-id-card me-2"></i>My Profile
                                        </a>
                                    </li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item text-danger" href="<?php echo getBaseUrl(); ?>logout.php">
                                        <i class="fas fa-sign-out-alt me-2"></i>Logout
                                    </a>
                                </li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link btn btn-outline-light ms-2" href="<?php echo getBaseUrl(); ?>login.php">
                                <i class="fas fa-sign-in-alt me-1"></i>Login
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="btn btn-light ms-2" href="<?php echo getBaseUrl(); ?>register.php">Create account</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Flash Messages -->
    <div class="container mt-3">
        <?php displayFlashMessages(); ?>
    </div>

