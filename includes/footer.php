<?php
/**
 * Footer Template
 * Event Management System 007
 */
?>
    <!-- Footer -->
    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container">
            <div class="row align-items-center gy-3">
                <div class="col-md-7 mb-3">
                    <h5 class="fw-bold"><i class="fas fa-calendar-alt me-2"></i>EventMS</h5>
                    <p class="small text-muted mb-0">Events, people and places worth making time for.</p>
                </div>
                <div class="col-md-5 mb-3 text-md-end">
                    <a href="<?php echo getBaseUrl(); ?>browse_events.php" class="text-white me-3">Browse events</a>
                    <a href="<?php echo getBaseUrl(); ?>login.php" class="text-white">Sign in</a>
                </div>
            </div>
            <hr class="border-secondary my-3">
            <div class="small text-muted">
                &copy; <?php echo date('Y'); ?> EventMS
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.9/dist/js/bootstrap.bundle.min.js" integrity="sha384-WyOxWvTyr1QaMPfiPJSv76iReVthorl5uWlFtZPyK0IEB0hGzo9y5Bp9mYanr8Bu" crossorigin="anonymous"></script>
    <!-- Custom JS -->
    <script src="<?php echo getAssetUrl('js/script.js'); ?>"></script>
    
    <?php if (isset($additionalScripts)): ?>
        <?php echo $additionalScripts; ?>
    <?php endif; ?>
