<?php $hostEventUrl = getBaseUrl() . (function_exists('hasRole') && hasRole('organizer') ? 'organizer/dashboard.php' : 'login.php'); ?>
<footer class="site-footer">
  <div class="wrap footer-grid">
    <div class="footer-brand-block"><a class="wordmark wordmark--footer" href="<?php echo getBaseUrl(); ?>"><span class="wordmark__symbol" aria-hidden="true">e</span>eventms</a><p>Good things happen when people get together.</p></div>
    <div><h2 class="footer-heading">Explore</h2><a href="<?php echo getBaseUrl(); ?>browse_events.php">Find events</a><a href="<?php echo getBaseUrl(); ?>#popular-categories">Browse categories</a></div>
    <div><h2 class="footer-heading">Make a plan</h2><a href="<?php echo htmlspecialchars($hostEventUrl); ?>">Host an event</a><a href="<?php echo getBaseUrl(); ?>register.php">Create account</a></div>
  </div>
  <div class="wrap footer-bottom"><span>© <?php echo date('Y'); ?> EventMS</span><span>Made for getting together.</span></div>
</footer>
<script src="<?php echo getAssetUrl('js/script.js'); ?>" defer></script>
<?php if (isset($additionalScripts)): ?><?php echo $additionalScripts; ?><?php endif; ?>
