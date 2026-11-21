<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config/database.php';
$isLoggedIn = isset($_SESSION['user_id']);
$userRole = isset($_SESSION['user_role']) ? (string)$_SESSION['user_role'] : '';
$userName = isset($_SESSION['user_name']) ? (string)$_SESSION['user_name'] : '';
$currentPage = basename($_SERVER['PHP_SELF'] ?? '', '.php');
?>
<a class="skip-link" href="#main-content">Skip to content</a>
<header class="site-header">
  <div class="site-header__inner">
    <a class="wordmark" href="<?php echo getBaseUrl(); ?>" aria-label="EventMS home"><span class="wordmark__symbol" aria-hidden="true">e</span>eventms</a>
    <button class="menu-toggle" type="button" aria-controls="primary-navigation" aria-expanded="false" aria-label="Open navigation"><span></span><span></span></button>
    <nav class="primary-nav" id="primary-navigation" aria-label="Main navigation">
      <a <?php echo $currentPage === 'browse_events' ? 'aria-current="page"' : ''; ?> href="<?php echo getBaseUrl(); ?>browse_events.php">Explore</a>
      <a href="<?php echo getBaseUrl(); ?>#popular-categories">Categories</a>
      <div class="nav-account">
      <?php if ($isLoggedIn):
        $dashboard = $userRole === 'admin' ? 'admin/dashboard.php' : ($userRole === 'organizer' ? 'organizer/dashboard.php' : 'attendee/dashboard.php');
        $profile = $userRole === 'admin' ? 'admin/profile.php' : ($userRole === 'organizer' ? 'organizer/profile.php' : 'attendee/profile.php'); ?>
        <button class="nav-user" type="button" aria-expanded="false" aria-controls="account-menu"><?php echo htmlspecialchars($userName); ?><span class="nav-user__role"><?php echo htmlspecialchars(ucfirst($userRole)); ?></span></button>
        <div class="account-menu" id="account-menu" hidden><a href="<?php echo getBaseUrl() . $dashboard; ?>">Dashboard</a><a href="<?php echo getBaseUrl() . $profile; ?>">My profile</a><a href="<?php echo getBaseUrl(); ?>logout.php">Sign out</a></div>
      <?php else: ?>
        <a class="nav-login" href="<?php echo getBaseUrl(); ?>login.php">Log in</a>
        <a class="button button--small" href="<?php echo getBaseUrl(); ?>register.php">Create account</a>
      <?php endif; ?>
      </div>
    </nav>
  </div>
</header>
<?php if (function_exists('displayFlashMessages')): ?><div class="flash-region wrap"><?php displayFlashMessages(); ?></div><?php endif; ?>
