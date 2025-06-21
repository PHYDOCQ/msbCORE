<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/auth.php';

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
?>
