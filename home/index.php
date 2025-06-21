<?php
header('Content-Type: application/json');
require_once 'config/database.php';
require_once 'includes/auth.php';

debugLog(['user_id' => $_SESSION['user_id'] ?? 'anonymous', 'endpoint' => 'dashboard'], 'API_DASHBOARD_START');

$auth->requireLogin();

try {
    $response = ['success' => true, 'data' => []];
    
    debugLog('Starting dashboard data collection', 'API_DASHBOARD_DATA');
    
    // Work Order Status Data
    $statusQuery = "SELECT 
                        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
                        COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress,
                        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
                        COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled,
                        COUNT(CASE WHEN status = 'on_hold' THEN 1 END) as on_hold
                    FROM work_orders";
    
    debugLog(['query' => 'work_order_status'], 'API_DASHBOARD_QUERY');
    $response['workOrderStatus'] = $db->query($statusQuery)->fetch();
    debugLog(['result' => $response['workOrderStatus']], 'API_DASHBOARD_STATUS_RESULT');
    
    // Revenue Trend (Last 12 months)
    $revenueTrendQuery = "SELECT 
                            DATE_FORMAT(actual_completion_date, '%Y-%m') as month,
                            SUM(final_amount) as revenue
                         FROM work_orders 
                         WHERE status = 'completed' 
                           AND actual_completion_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                         GROUP BY DATE_FORMAT(actual_completion_date, '%Y-%m')
                         ORDER BY month";
    
    $revenueData = $db->query($revenueTrendQuery)->fetchAll();
    $response['revenueTrend'] = [
        'labels' => array_column($revenueData, 'month'),
        'values' => array_column($revenueData, 'revenue')
    ];
    
    // Technician Performance
    $techPerfQuery = "SELECT 
                        u.full_name,
                        COUNT(wo.id) as active_orders,
                        AVG(wo.progress_percentage) as avg_progress
                      FROM users u
                      LEFT JOIN work_orders wo ON u.id = wo.technician_id AND wo.status IN ('pending', 'in_progress')
                      WHERE u.role = 'technician' AND u.is_active = 1
                      GROUP BY u.id, u.full_name
                      ORDER BY active_orders DESC";
    
    $techData = $db->query($techPerfQuery)->fetchAll();
    $response['technicianPerformance'] = [
        'labels' => array_column($techData, 'full_name'),
        'active_orders' => array_column($techData, 'active_orders'),
        'avg_progress' => array_column($techData, 'avg_progress')
    ];
    
    // Inventory Status (Low stock items)
    $inventoryQuery = "SELECT name, current_stock, minimum_stock
                       FROM inventory 
                       WHERE current_stock <= minimum_stock AND is_active = 1
                       ORDER BY (current_stock - minimum_stock) ASC
                       LIMIT 10";
    
    $inventoryData = $db->query($inventoryQuery)->fetchAll();
    $response['inventoryStatus'] = [
        'labels' => array_column($inventoryData, 'name'),
        'current_stock' => array_column($inventoryData, 'current_stock'),
        'minimum_stock' => array_column($inventoryData, 'minimum_stock')
    ];
    
    echo json_encode($response);
    
} catch(Exception $e) {
    debugLog(['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()], 'API_DASHBOARD_ERROR');
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'message' => DEBUG_MODE ? $e->getMessage() : 'Terjadi kesalahan sistem. Silakan coba lagi.',
        'code' => 'DASHBOARD_ERROR',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?><?php
$page_title = "Dashboard";
include 'includes/header.php';
?>

<!-- Enhanced Dashboard with Charts -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">📊 Advanced Dashboard</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="refreshDashboard">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="exportDashboard()">
                <i class="fas fa-download"></i> Export
            </button>
        </div>
    </div>
</div>

<!-- Statistics Cards Row -->
<div class="row mb-4">
    <!-- Existing stat cards... -->
</div>

<!-- Charts Row -->
<div class="row mb-4">
    <!-- Work Order Status Chart -->
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold">
                    <i class="fas fa-chart-pie"></i> Work Order Status Distribution
                </h6>
            </div>
            <div class="card-body">
                <div style="height: 300px;">
                    <canvas id="workOrderStatusChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Revenue Trend Chart -->
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold">
                    <i class="fas fa-chart-line"></i> Revenue Trend (12 Months)
                </h6>
            </div>
            <div class="card-body">
                <div style="height: 300px;">
                    <canvas id="revenueTrendChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Performance Charts Row -->
<div class="row mb-4">
    <!-- Technician Performance -->
    <div class="col-lg-8 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold">
                    <i class="fas fa-users"></i> Technician Performance
                </h6>
            </div>
            <div class="card-body">
                <div style="height: 300px;">
                    <canvas id="technicianPerformanceChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Inventory Status -->
    <div class="col-lg-4 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold">
                    <i class="fas fa-boxes"></i> Low Stock Alert
                </h6>
            </div>
            <div class="card-body">
                <div style="height: 300px;">
                    <canvas id="inventoryStatusChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Activities & Alerts -->
<div class="row">
    <!-- Recent Work Orders with Enhanced Display -->
    <div class="col-lg-8 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold">
                    <i class="fas fa-clock"></i> Recent Work Orders
                </h6>
                <a href="modules/work_orders/list.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>WO Number</th>
                                <th>Vehicle</th>
                                <th>Customer</th>
                                <th>Status</th>
                                <th>Progress</th>
                                <th>Priority</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="recentWorkOrders">
                            <!-- Populated via AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Notifications & Alerts -->
    <div class="col-lg-4 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold">
                    <i class="fas fa-bell"></i> Notifications & Alerts
                </h6>
            </div>
            <div class="card-body">
                <div id="notificationsList">
                    <!-- Populated via AJAX -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Include Chart.js and custom charts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="assets/js/charts.js"></script>

<script>
// Auto-refresh dashboard every 5 minutes
setInterval(function() {
    loadDashboardCharts();
    loadRecentActivities();
}, 300000);

// Refresh button functionality
document.getElementById('refreshDashboard').addEventListener('click', function() {
    loadDashboardCharts();
    loadRecentActivities();
    showToast('Dashboard refreshed successfully!', 'success');
});

function exportDashboard() {
    window.location.href = 'exports/dashboard_export.php';
}

function loadRecentActivities() {
    // Load recent work orders
    fetch('api/recent_activities.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateRecentWorkOrders(data.workOrders);
                updateNotifications(data.notifications);
            }
        });
}

function updateRecentWorkOrders(workOrders) {
    const tbody = document.getElementById('recentWorkOrders');
    tbody.innerHTML = '';
    
    workOrders.forEach(wo => {
        const row = `
            <tr>
                <td><a href="modules/work_orders/detail.php?id=${wo.id}">${wo.work_order_number}</a></td>
                <td>${wo.license_plate}</td>
                <td>${wo.customer_name}</td>
                <td><span class="badge bg-${getStatusColor(wo.status)}">${wo.status.replace('_', ' ')}</span></td>
                <td>
                    <div class="progress" style="height: 20px;">
                        <div class="progress-bar" style="width: ${wo.progress_percentage}%">${wo.progress_percentage}%</div>
                    </div>
                </td>
                <td><span class="badge bg-${getPriorityColor(wo.priority)}">${wo.priority}</span></td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="quickUpdate(${wo.id})">
                        <i class="fas fa-edit"></i>
                    </button>
                </td>
            </tr>
        `;
        tbody.innerHTML += row;
    });
}

function getStatusColor(status) {
    const colors = {
        'pending': 'warning',
        'in_progress': 'info', 
        'completed': 'success',
        'cancelled': 'danger',
        'on_hold': 'secondary'
    };
    return colors[status] || 'secondary';
}

function getPriorityColor(priority) {
    const colors = {
        'low': 'success',
        'normal': 'info',
        'high': 'warning', 
        'urgent': 'danger'
    };
    return colors[priority] || 'info';
}

function showToast(message, type = 'info') {
    // Toast notification implementation
    const toast = document.createElement('div');
    toast.className = `alert alert-${type} position-fixed`;
    toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999;';
    toast.innerHTML = `
        ${message}
        <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
    `;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        if (toast.parentElement) {
            toast.remove();
        }
    }, 5000);
}

// Initialize dashboard
document.addEventListener('DOMContentLoaded', function() {
    loadRecentActivities();
});
</script>

<?php include 'includes/footer.php'; ?>

