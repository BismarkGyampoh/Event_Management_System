<?php
/**
 * Footer Template
 * Event Management System 007
 */
?>
    <!-- Footer -->
    <footer class="bg-dark text-white py-5 mt-5">
        <div class="container">
            <div class="row gy-4 align-items-start">
                <div class="col-lg-6">
                    <a href="<?php echo getBaseUrl(); ?>" class="footer-brand"><i class="fas fa-calendar-days me-2" aria-hidden="true"></i>EventMS</a>
                    <p class="small text-muted mb-0 mt-2">Find your people. Make a little room for something new.</p>
                </div>
                <div class="col-6 col-lg-3">
                    <p class="footer-heading">Discover</p>
                    <a class="footer-link" href="<?php echo getBaseUrl(); ?>browse_events.php">Browse events</a>
                    <a class="footer-link" href="<?php echo getBaseUrl(); ?>#popular-categories">Explore categories</a>
                </div>
                <div class="col-6 col-lg-3">
                    <p class="footer-heading">For organisers</p>
                    <a class="footer-link" href="<?php echo getBaseUrl() . (function_exists('hasRole') && hasRole('organizer') ? 'organizer/dashboard.php' : 'login.php'); ?>">Host an event</a>
                    <a class="footer-link" href="<?php echo getBaseUrl(); ?>register.php">Create an account</a>
                </div>
            </div>
            <div class="footer-bottom"><span>&copy; <?php echo date('Y'); ?> EventMS</span><a href="<?php echo getBaseUrl(); ?>login.php">Sign in</a></div>
        </div>
    </footer>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.9/dist/js/bootstrap.bundle.min.js" integrity="sha384-WyOxWvTyr1QaMPfiPJSv76iReVthorl5uWlFtZPyK0IEB0hGzo9y5Bp9mYanr8Bu" crossorigin="anonymous"></script>
    <!-- Custom JS -->
    <script src="<?php echo getAssetUrl('js/script.js'); ?>"></script>
    
    <?php if (isset($additionalScripts)): ?>
        <?php echo $additionalScripts; ?>
    <?php endif; ?>
