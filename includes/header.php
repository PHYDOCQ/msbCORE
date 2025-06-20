<?php
if (!isset($pageTitle)) {
    $pageTitle = 'Dashboard';
}

require_once dirname(__FILE__) . '/../config/config.php';
require_once dirname(__FILE__) . '/../config/database.php';
require_once dirname(__FILE__) . '/../includes/auth.php';
require_once dirname(__FILE__) . '/../includes/functions.php';

// Require authentication for all pages using this header
$auth->requireLogin();

// Get current user info
$currentUser = $auth->getCurrentUser();

// Generate CSRF token
$csrfToken = Security::generateCSRF();

// Check for flash messages
$flashMessage = $_SESSION['flash_message'] ?? null;
$flashType = $_SESSION['flash_type'] ?? 'info';
if ($flashMessage) {
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}

// Get notifications count
try {
    $notificationQuery = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
    $stmt = $db->prepare($notificationQuery);
    $stmt->execute([$currentUser['id']]);
    $unreadNotifications = $stmt->fetch()['count'];
} catch (Exception $e) {
    debugLog(['error' => $e->getMessage()], 'NOTIFICATION_COUNT_ERROR');
    $unreadNotifications = 0;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo $csrfToken; ?>">
    <title><?php echo htmlspecialchars($pageTitle . ' - ' . APP_NAME); ?></title>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo APP_URL; ?>assets/css/style.css" rel="stylesheet">
    <link href="<?php echo APP_URL; ?>assets/css/custom.css" rel="stylesheet">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?php echo APP_URL; ?>assets/favicon.ico">
    
    <!-- Meta tags for SEO and security -->
    <meta name="description" content="<?php echo APP_NAME; ?> - Sistem manajemen bengkel profesional">
    <meta name="author" content="<?php echo APP_NAME; ?>">
    <meta name="robots" content="noindex, nofollow">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    
    <script>
        // Global JavaScript variables
        window.APP_CONFIG = {
            url: '<?php echo APP_URL; ?>',
            debug: <?php echo DEBUG_MODE ? 'true' : 'false'; ?>,
            user: {
                id: <?php echo $currentUser['id']; ?>,
                name: '<?php echo htmlspecialchars($currentUser['full_name']); ?>',
                role: '<?php echo htmlspecialchars($currentUser['role']); ?>'
            },
            csrf_token: '<?php echo $csrfToken; ?>'
        };
    </script>
</head>
<body class="sb-nav-fixed">
    <!-- Loading spinner -->
    <div id="loading-spinner" class="loading-spinner d-none">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="sb-topnav navbar navbar-expand navbar-dark bg-dark">
        <!-- Navbar Brand-->
        <a class="navbar-brand ps-3" href="<?php echo APP_URL; ?>index.php">
            <i class="fas fa-tools me-2"></i><?php echo APP_NAME; ?>
        </a>
        
        <!-- Sidebar Toggle-->
        <button class="btn btn-link btn-sm order-1 order-lg-0 me-4 me-lg-0" id="sidebarToggle" href="#">
            <i class="fas fa-bars"></i>
        </button>
        
        <!-- Navbar Search-->
        <form class="d-none d-md-inline-block form-inline ms-auto me-0 me-md-3 my-2 my-md-0" action="<?php echo APP_URL; ?>search.php" method="GET">
            <div class="input-group">
                <input class="form-control" type="text" placeholder="Search..." aria-label="Search for..." aria-describedby="btnNavbarSearch" name="q">
                <button class="btn btn-primary" id="btnNavbarSearch" type="submit">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </form>
        
        <!-- Navbar-->
        <ul class="navbar-nav ms-auto ms-md-0 me-3 me-lg-4">
            <!-- Notifications -->
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle position-relative" id="notificationDropdown" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-bell"></i>
                    <?php if ($unreadNotifications > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notification-badge">
                            <?php echo $unreadNotifications > 99 ? '99+' : $unreadNotifications; ?>
                            <span class="visually-hidden">unread notifications</span>
                        </span>
                    <?php endif; ?>
                </a>
                <ul class="dropdown-menu dropdown-menu-end notification-dropdown" aria-labelledby="notificationDropdown">
                    <li><h6 class="dropdown-header">Notifications</h6></li>
                    <li><hr class="dropdown-divider" /></li>
                    <div id="notificationsList">
                        <!-- Populated via AJAX -->
                        <li><span class="dropdown-item-text">Loading notifications...</span></li>
                    </div>
                    <li><hr class="dropdown-divider" /></li>
                    <li><a class="dropdown-item text-center" href="<?php echo APP_URL; ?>modules/notifications/list.php">View All</a></li>
                </ul>
            </li>
            
            <!-- User Menu -->
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" id="navbarDropdown" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-user fa-fw me-1"></i><?php echo htmlspecialchars($currentUser['full_name']); ?>
                </a>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                    <li><a class="dropdown-item" href="<?php echo APP_URL; ?>modules/users/profile.php">
                        <i class="fas fa-user-edit me-2"></i>Profile
                    </a></li>
                    <li><a class="dropdown-item" href="<?php echo APP_URL; ?>modules/settings/index.php">
                        <i class="fas fa-cog me-2"></i>Settings
                    </a></li>
                    <li><hr class="dropdown-divider" /></li>
                    <li><a class="dropdown-item" href="#" onclick="logout(); return false;">
                        <i class="fas fa-sign-out-alt me-2"></i>Logout
                    </a></li>
                </ul>
            </li>
        </ul>
    </nav>
    
    <div id="layoutSidenav">
        <!-- Sidebar -->
        <div id="layoutSidenav_nav">
            <nav class="sb-sidenav accordion sb-sidenav-dark" id="sidenavAccordion">
                <div class="sb-sidenav-menu">
                    <div class="nav">
                        <!-- Core Section -->
                        <div class="sb-sidenav-menu-heading">Core</div>
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>" 
                           href="<?php echo APP_URL; ?>index.php">
                            <div class="sb-nav-link-icon"><i class="fas fa-tachometer-alt"></i></div>
                            Dashboard
                        </a>
                        
                        <!-- Management Section -->
                        <div class="sb-sidenav-menu-heading">Management</div>
                        
                        <!-- Customers -->
                        <a class="nav-link <?php echo strpos($_SERVER['REQUEST_URI'], 'customers') !== false ? 'active' : ''; ?>" 
                           href="<?php echo APP_URL; ?>modules/customers/list.php">
                            <div class="sb-nav-link-icon"><i class="fas fa-users"></i></div>
                            Customers
                        </a>
                        
                        <!-- Vehicles -->
                        <a class="nav-link <?php echo strpos($_SERVER['REQUEST_URI'], 'vehicles') !== false ? 'active' : ''; ?>" 
                           href="<?php echo APP_URL; ?>modules/vehicles/list.php">
                            <div class="sb-nav-link-icon"><i class="fas fa-car"></i></div>
                            Vehicles
                        </a>
                        
                        <!-- Work Orders -->
                        <a class="nav-link <?php echo strpos($_SERVER['REQUEST_URI'], 'work_orders') !== false ? 'active' : ''; ?>" 
                           href="<?php echo APP_URL; ?>modules/work_orders/list.php">
                            <div class="sb-nav-link-icon"><i class="fas fa-clipboard-list"></i></div>
                            Work Orders
                            <?php
                            try {
                                $pendingQuery = "SELECT COUNT(*) as count FROM work_orders WHERE status = 'pending'";
                                $stmt = $db->prepare($pendingQuery);
                                $stmt->execute();
                                $pendingCount = $stmt->fetch()['count'];
                                if ($pendingCount > 0) {
                                    echo '<span class="badge bg-warning ms-2">' . $pendingCount . '</span>';
                                }
                            } catch (Exception $e) {
                                debugLog(['error' => $e->getMessage()], 'PENDING_COUNT_ERROR');
                            }
                            ?>
                        </a>
                        
                        <!-- Inventory -->
                        <a class="nav-link <?php echo strpos($_SERVER['REQUEST_URI'], 'inventory') !== false ? 'active' : ''; ?>" 
                           href="<?php echo APP_URL; ?>modules/inventory/list.php">
                            <div class="sb-nav-link-icon"><i class="fas fa-boxes"></i></div>
                            Inventory
                            <?php
                            try {
                                $lowStockQuery = "SELECT COUNT(*) as count FROM inventory WHERE current_stock <= minimum_stock AND is_active = 1";
                                $stmt = $db->prepare($lowStockQuery);
                                $stmt->execute();
                                $lowStockCount = $stmt->fetch()['count'];
                                if ($lowStockCount > 0) {
                                    echo '<span class="badge bg-danger ms-2">' . $lowStockCount . '</span>';
                                }
                            } catch (Exception $e) {
                                debugLog(['error' => $e->getMessage()], 'LOW_STOCK_COUNT_ERROR');
                            }
                            ?>
                        </a>
                        
                        <!-- Reports Section -->
                        <div class="sb-sidenav-menu-heading">Reports</div>
                        <a class="nav-link collapsed <?php echo strpos($_SERVER['REQUEST_URI'], 'reports') !== false ? 'active' : ''; ?>" 
                           href="#" data-bs-toggle="collapse" data-bs-target="#collapseReports" 
                           aria-expanded="false" aria-controls="collapseReports">
                            <div class="sb-nav-link-icon"><i class="fas fa-chart-area"></i></div>
                            Reports
                            <div class="sb-sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                        </a>
                        <div class="collapse <?php echo strpos($_SERVER['REQUEST_URI'], 'reports') !== false ? 'show' : ''; ?>" 
                             id="collapseReports" aria-labelledby="headingOne" data-bs-parent="#sidenavAccordion">
                            <nav class="sb-sidenav-menu-nested nav">
                                <a class="nav-link" href="<?php echo APP_URL; ?>modules/reports/work_orders.php">Work Orders</a>
                                <a class="nav-link" href="<?php echo APP_URL; ?>modules/reports/financial.php">Financial</a>
                                <a class="nav-link" href="<?php echo APP_URL; ?>modules/reports/inventory.php">Inventory</a>
                                <a class="nav-link" href="<?php echo APP_URL; ?>modules/reports/customers.php">Customers</a>
                            </nav>
                        </div>
                        
                        <!-- Admin Section -->
                        <?php if ($auth->hasRole(['admin', 'manager'])): ?>
                        <div class="sb-sidenav-menu-heading">Administration</div>
                        <a class="nav-link <?php echo strpos($_SERVER['REQUEST_URI'], 'users') !== false ? 'active' : ''; ?>" 
                           href="<?php echo APP_URL; ?>modules/users/list.php">
                            <div class="sb-nav-link-icon"><i class="fas fa-users-cog"></i></div>
                            Users
                        </a>
                        <a class="nav-link <?php echo strpos($_SERVER['REQUEST_URI'], 'settings') !== false ? 'active' : ''; ?>" 
                           href="<?php echo APP_URL; ?>modules/settings/index.php">
                            <div class="sb-nav-link-icon"><i class="fas fa-cog"></i></div>
                            Settings
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Sidebar Footer -->
                <div class="sb-sidenav-footer">
                    <div class="small">Logged in as:</div>
                    <?php echo htmlspecialchars($currentUser['role']); ?>
                </div>
            </nav>
        </div>
        
        <!-- Main Content -->
        <div id="layoutSidenav_content">
            <main>
                <div class="container-fluid px-4">
                    
                    <!-- Toast Container -->
                    <div class="toast-container"></div>
                    
                    <!-- Flash Messages (converted to toast) -->
                    <?php if ($flashMessage): ?>
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            const flashType = '<?php echo $flashType; ?>';
                            const flashMessage = '<?php echo addslashes($flashMessage); ?>';
                            
                            // Convert flash type to toast type
                            let toastType = 'info';
                            let title = 'Informasi';
                            
                            switch(flashType) {
                                case 'success':
                                    toastType = 'success';
                                    title = 'Berhasil';
                                    break;
                                case 'danger':
                                case 'error':
                                    toastType = 'error';
                                    title = 'Error';
                                    break;
                                case 'warning':
                                    toastType = 'warning';
                                    title = 'Peringatan';
                                    break;
                                case 'info':
                                default:
                                    toastType = 'info';
                                    title = 'Informasi';
                                    break;
                            }
                            
                            showToast(flashMessage, toastType, {
                                title: title,
                                duration: 6000,
                                sound: true
                            });
                        });
                    </script>
                    <?php endif; ?>
                    
                    <!-- Debug Panel (Development Only) -->
                    <?php if (DEBUG_MODE): ?>
                    <div class="debug-panel mt-3">
                        <button class="btn btn-sm btn-outline-info" type="button" data-bs-toggle="collapse" 
                                data-bs-target="#debugInfo" aria-expanded="false" aria-controls="debugInfo">
                            <i class="fas fa-bug"></i> Debug Info
                        </button>
                        <div class="collapse mt-2" id="debugInfo">
                            <div class="card">
                                <div class="card-body">
                                    <h6>Debug Information</h6>
                                    <small>
                                        <strong>User ID:</strong> <?php echo $currentUser['id']; ?><br>
                                        <strong>Role:</strong> <?php echo $currentUser['role']; ?><br>
                                        <strong>Page:</strong> <?php echo $_SERVER['PHP_SELF']; ?><br>
                                        <strong>Memory Usage:</strong> <?php echo Utils::formatFileSize(memory_get_usage()); ?><br>
                                        <strong>Execution Time:</strong> <span id="execution-time">-</span>ms
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
