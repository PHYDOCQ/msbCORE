                </div>
            </main>
            
            <!-- Footer -->
            <footer class="py-4 bg-light mt-auto">
                <div class="container-fluid px-4">
                    <div class="d-flex align-items-center justify-content-between small">
                        <div class="text-muted">
                            Copyright &copy; <?php echo APP_NAME . ' ' . date('Y'); ?>
                        </div>
                        <div>
                            <span class="text-muted">Version <?php echo APP_VERSION; ?></span>
                            <?php if (DEBUG_MODE): ?>
                                <span class="badge bg-warning text-dark ms-2">
                                    <i class="fas fa-code"></i> Development
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3">
        <div id="liveToast" class="toast hide" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header">
                <i class="fas fa-info-circle me-2 toast-icon"></i>
                <strong class="me-auto toast-title">Notification</strong>
                <small class="text-muted toast-time">now</small>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body"></div>
        </div>
    </div>

    <!-- Confirm Modal -->
    <div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmModalLabel">Confirm Action</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="confirmModalBody">
                    Are you sure you want to perform this action?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirmModalAction">Confirm</button>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="<?php echo APP_URL; ?>assets/js/app.js"></script>
    <script src="<?php echo APP_URL; ?>assets/js/charts.js"></script>
    <script src="<?php echo APP_URL; ?>assets/js/validation.js"></script>

    <script>
        // Initialize app
        document.addEventListener('DOMContentLoaded', function() {
            // Record page load time
            if (window.performance && window.APP_CONFIG.debug) {
                const loadTime = Math.round(window.performance.now());
                const executionTimeElement = document.getElementById('execution-time');
                if (executionTimeElement) {
                    executionTimeElement.textContent = loadTime;
                }
            }
            
            // Initialize tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // Initialize popovers
            var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
            var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
                return new bootstrap.Popover(popoverTriggerEl);
            });
            
            // Load notifications
            loadNotifications();
            
            // Set up periodic refresh for notifications
            setInterval(loadNotifications, 60000); // Every minute
            
            // Debug output
            if (window.APP_CONFIG.debug) {
                console.log('🚗 <?php echo APP_NAME; ?> initialized');
                console.log('User:', window.APP_CONFIG.user);
                console.log('Page load completed in:', Math.round(window.performance.now()), 'ms');
            }
        });
    </script>
    
    <?php
    // Log page view for analytics
    if (isset($_SESSION['user_id'])) {
        Utils::logActivity('page_view', $_SESSION['user_id'], 'view', $_SERVER['REQUEST_URI']);
    }
    ?>
</body>
</html>
