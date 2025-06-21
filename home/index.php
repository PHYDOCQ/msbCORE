<?php
$pageTitle = "Dashboard";
require_once '../includes/header.php';
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
                <a href="../modules/work_orders/list.php" class="btn btn-sm btn-outline-primary">View All</a>
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
<script src="../assets/js/charts.js"></script>

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
    window.location.href = '../exports/dashboard_export.php';
}

function loadDashboardCharts() {
    // Load dashboard data from API
    fetch('../api/dashboard.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update charts with the data
                console.log('Dashboard data loaded:', data);
                // Chart update logic would go here
            }
        })
        .catch(error => {
            console.error('Error loading dashboard data:', error);
        });
}

function loadRecentActivities() {
    // Load recent work orders
    fetch('../api/recent_activities.php')
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
    loadDashboardCharts();
    loadRecentActivities();
});
</script>

<?php include '../includes/footer.php'; ?>

