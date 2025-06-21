<?php
$pageTitle = "Dashboard";
require_once '../includes/header.php';
?>

<!-- Enhanced Dashboard with Charts -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <div>
        <h1 class="h2">📊 Advanced Dashboard</h1>
        <small class="text-muted">
            <span class="real-time-indicator"></span>
            Real-time data • Last updated: <span id="lastUpdated">Loading...</span>
        </small>
    </div>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="refreshDashboard">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="exportDashboard()">
                <i class="fas fa-download"></i> Export
            </button>
            <button type="button" class="btn btn-sm btn-outline-info" onclick="toggleAutoRefresh()">
                <i class="fas fa-play" id="autoRefreshIcon"></i> Auto-refresh
            </button>
        </div>
    </div>
</div>

<!-- Statistics Cards Row -->
<div class="row mb-4">
    <!-- Total Work Orders -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Total Work Orders
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800" id="totalWorkOrders">
                            <div class="spinner-border spinner-border-sm" role="status"></div>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-clipboard-list fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Pending Work Orders -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            Pending Orders
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800" id="pendingWorkOrders">
                            <div class="spinner-border spinner-border-sm" role="status"></div>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-clock fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Monthly Revenue -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            Monthly Revenue
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800" id="monthlyRevenue">
                            <div class="spinner-border spinner-border-sm" role="status"></div>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Technicians -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            Active Technicians
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800" id="activeTechnicians">
                            <div class="spinner-border spinner-border-sm" role="status"></div>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-users fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
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

<!-- Enhanced Dashboard Styles -->
<style>
.border-left-primary {
    border-left: 0.25rem solid #4e73df !important;
}

.border-left-success {
    border-left: 0.25rem solid #1cc88a !important;
}

.border-left-info {
    border-left: 0.25rem solid #36b9cc !important;
}

.border-left-warning {
    border-left: 0.25rem solid #f6c23e !important;
}

.animate-pulse {
    animation: pulse 1s ease-in-out;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

.card {
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.toast-container {
    max-width: 350px;
}

.toast {
    backdrop-filter: blur(10px);
    border-radius: 8px;
}

.progress {
    border-radius: 10px;
    overflow: hidden;
}

.progress-bar {
    transition: width 0.6s ease;
}

.btn {
    transition: all 0.2s ease-in-out;
}

.btn:hover {
    transform: translateY(-1px);
}

.table-hover tbody tr:hover {
    background-color: rgba(0,0,0,0.05);
    transform: scale(1.01);
    transition: all 0.2s ease-in-out;
}

.spinner-border-sm {
    width: 1rem;
    height: 1rem;
}

.text-gray-800 {
    color: #5a5c69 !important;
}

.text-gray-300 {
    color: #dddfeb !important;
}

.shadow {
    box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15) !important;
}

.notification-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: #dc3545;
    color: white;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    font-size: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.real-time-indicator {
    display: inline-block;
    width: 8px;
    height: 8px;
    background: #28a745;
    border-radius: 50%;
    animation: blink 2s infinite;
}

@keyframes blink {
    0%, 50% { opacity: 1; }
    51%, 100% { opacity: 0.3; }
}
</style>

<!-- Include Chart.js and custom charts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="../assets/js/charts.js"></script>

<script>
// Auto-refresh functionality
let autoRefreshInterval;
let isAutoRefreshEnabled = true;

// Auto-refresh dashboard every 5 minutes
function startAutoRefresh() {
    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
    }
    
    autoRefreshInterval = setInterval(function() {
        if (isAutoRefreshEnabled) {
            loadDashboardCharts();
            loadRecentActivities();
            updateLastUpdatedTime();
        }
    }, 300000); // 5 minutes
}

// Toggle auto-refresh
function toggleAutoRefresh() {
    isAutoRefreshEnabled = !isAutoRefreshEnabled;
    const icon = document.getElementById('autoRefreshIcon');
    
    if (isAutoRefreshEnabled) {
        icon.className = 'fas fa-pause';
        startAutoRefresh();
        showToast('Auto-refresh enabled', 'info', 2000);
    } else {
        icon.className = 'fas fa-play';
        if (autoRefreshInterval) {
            clearInterval(autoRefreshInterval);
        }
        showToast('Auto-refresh disabled', 'warning', 2000);
    }
}

// Update last updated time
function updateLastUpdatedTime() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    });
    document.getElementById('lastUpdated').textContent = timeString;
}

// Refresh button functionality
document.getElementById('refreshDashboard').addEventListener('click', function() {
    const button = this;
    const icon = button.querySelector('i');
    
    // Add loading animation
    icon.className = 'fas fa-spinner fa-spin';
    button.disabled = true;
    
    loadDashboardCharts();
    loadRecentActivities();
    updateLastUpdatedTime();
    
    // Reset button after 2 seconds
    setTimeout(() => {
        icon.className = 'fas fa-sync-alt';
        button.disabled = false;
        showToast('Dashboard refreshed successfully!', 'success', 2000);
    }, 2000);
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
                // Update statistics cards
                updateStatisticsCards(data.stats);
                
                // Update charts with the data
                console.log('Dashboard data loaded:', data);
                // Chart update logic would go here
                
                showToast('Dashboard data updated successfully!', 'success', 3000);
            } else {
                showToast('Failed to load dashboard data', 'warning');
            }
        })
        .catch(error => {
            console.error('Error loading dashboard data:', error);
            showToast('Error connecting to server', 'danger');
            
            // Show fallback data
            updateStatisticsCards({
                totalWorkOrders: 'N/A',
                pendingWorkOrders: 'N/A',
                monthlyRevenue: 'N/A',
                activeTechnicians: 'N/A'
            });
        });
}

function updateStatisticsCards(stats) {
    // Update each statistics card with animation
    const cards = {
        'totalWorkOrders': stats.totalWorkOrders || 0,
        'pendingWorkOrders': stats.pendingWorkOrders || 0,
        'monthlyRevenue': formatCurrency(stats.monthlyRevenue || 0),
        'activeTechnicians': stats.activeTechnicians || 0
    };
    
    Object.keys(cards).forEach(cardId => {
        const element = document.getElementById(cardId);
        if (element) {
            // Add loading animation
            element.style.opacity = '0.5';
            
            setTimeout(() => {
                element.innerHTML = cards[cardId];
                element.style.opacity = '1';
                
                // Add pulse animation for updated values
                element.classList.add('animate-pulse');
                setTimeout(() => {
                    element.classList.remove('animate-pulse');
                }, 1000);
            }, 300);
        }
    });
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(amount);
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
                <td><a href="../modules/work_orders/detail.php?id=${wo.id}">${wo.work_order_number}</a></td>
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

function showToast(message, type = 'info', duration = 5000) {
    // Enhanced toast notification with animations and auto-disappear
    const toastContainer = getOrCreateToastContainer();
    
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white bg-${type} border-0 mb-2`;
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');
    
    // Add sound effect for important notifications
    if (type === 'danger' || type === 'warning') {
        playNotificationSound();
    }
    
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <i class="fas ${getToastIcon(type)} me-2"></i>
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" onclick="removeToast(this)"></button>
        </div>
    `;
    
    // Add entrance animation
    toast.style.transform = 'translateX(100%)';
    toast.style.transition = 'transform 0.3s ease-in-out, opacity 0.3s ease-in-out';
    
    toastContainer.appendChild(toast);
    
    // Trigger entrance animation
    setTimeout(() => {
        toast.style.transform = 'translateX(0)';
    }, 10);
    
    // Auto-remove after duration
    setTimeout(() => {
        removeToast(toast.querySelector('.btn-close'));
    }, duration);
}

function getOrCreateToastContainer() {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'toast-container position-fixed top-0 end-0 p-3';
        container.style.zIndex = '9999';
        document.body.appendChild(container);
    }
    return container;
}

function getToastIcon(type) {
    const icons = {
        'success': 'fa-check-circle',
        'danger': 'fa-exclamation-triangle',
        'warning': 'fa-exclamation-circle',
        'info': 'fa-info-circle',
        'primary': 'fa-bell'
    };
    return icons[type] || 'fa-info-circle';
}

function removeToast(button) {
    const toast = button.closest('.toast');
    if (toast) {
        toast.style.transform = 'translateX(100%)';
        toast.style.opacity = '0';
        setTimeout(() => {
            if (toast.parentElement) {
                toast.remove();
            }
        }, 300);
    }
}

function playNotificationSound() {
    // Simple notification sound using Web Audio API
    try {
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();
        const oscillator = audioContext.createOscillator();
        const gainNode = audioContext.createGain();
        
        oscillator.connect(gainNode);
        gainNode.connect(audioContext.destination);
        
        oscillator.frequency.setValueAtTime(800, audioContext.currentTime);
        oscillator.frequency.setValueAtTime(600, audioContext.currentTime + 0.1);
        
        gainNode.gain.setValueAtTime(0.1, audioContext.currentTime);
        gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.2);
        
        oscillator.start(audioContext.currentTime);
        oscillator.stop(audioContext.currentTime + 0.2);
    } catch (e) {
        // Fallback for browsers that don't support Web Audio API
        console.log('Notification sound not supported');
    }
}

// Initialize dashboard
document.addEventListener('DOMContentLoaded', function() {
    // Set initial time
    updateLastUpdatedTime();
    
    // Load initial data
    loadDashboardCharts();
    loadRecentActivities();
    
    // Start auto-refresh
    startAutoRefresh();
    
    // Add keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl+R or F5 to refresh
        if ((e.ctrlKey && e.key === 'r') || e.key === 'F5') {
            e.preventDefault();
            document.getElementById('refreshDashboard').click();
        }
        
        // Ctrl+Space to toggle auto-refresh
        if (e.ctrlKey && e.code === 'Space') {
            e.preventDefault();
            toggleAutoRefresh();
        }
    });
    
    // Show welcome message
    setTimeout(() => {
        showToast('Welcome to the Advanced Dashboard! 🚀', 'primary', 4000);
    }, 1000);
});

// Add visibility change handler to pause/resume auto-refresh
document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        // Page is hidden, pause auto-refresh to save resources
        if (autoRefreshInterval) {
            clearInterval(autoRefreshInterval);
        }
    } else {
        // Page is visible again, resume auto-refresh
        if (isAutoRefreshEnabled) {
            startAutoRefresh();
            // Refresh data immediately when page becomes visible
            loadDashboardCharts();
            loadRecentActivities();
            updateLastUpdatedTime();
        }
    }
});
</script>

<?php include '../includes/footer.php'; ?>

