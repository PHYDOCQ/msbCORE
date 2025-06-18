<?php
/**
 * BENGKEL MANAGEMENT PRO - REPORT CLASS
 * Version: 3.1.0
 * Complete Reporting and Analytics System
 */

class Report {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    // ========================================
    // FINANCIAL REPORTS
    // ========================================
    
    public function getRevenueReport($startDate, $endDate, $groupBy = 'day') {
        $groupByClause = $this->getGroupByClause($groupBy);
        
        return $this->db->select(
            "SELECT 
                {$groupByClause} as period,
                COUNT(DISTINCT wo.id) as total_orders,
                COALESCE(SUM(wo.total_amount), 0) as total_revenue,
                COALESCE(SUM(wos.total_price), 0) as service_revenue,
                COALESCE(SUM(wop.total_price), 0) as parts_revenue,
                COALESCE(AVG(wo.total_amount), 0) as avg_order_value
             FROM work_orders wo
             LEFT JOIN work_order_services wos ON wo.id = wos.work_order_id
             LEFT JOIN work_order_parts wop ON wo.id = wop.work_order_id
             WHERE wo.status = 'completed'
             AND DATE(wo.completed_at) BETWEEN :start_date AND :end_date
             AND wo.deleted_at IS NULL
             GROUP BY {$groupByClause}
             ORDER BY period ASC",
            ['start_date' => $startDate, 'end_date' => $endDate]
        );
    }
    
    public function getProfitReport($startDate, $endDate) {
        return $this->db->select(
            "SELECT 
                DATE(wo.completed_at) as date,
                COALESCE(SUM(wo.total_amount), 0) as total_revenue,
                COALESCE(SUM(wop.quantity * i.cost_price), 0) as total_cost,
                COALESCE(SUM(wo.total_amount) - SUM(wop.quantity * i.cost_price), 0) as gross_profit,
                CASE 
                    WHEN SUM(wo.total_amount) > 0 
                    THEN ((SUM(wo.total_amount) - SUM(wop.quantity * i.cost_price)) / SUM(wo.total_amount)) * 100
                    ELSE 0 
                END as profit_margin
             FROM work_orders wo
             LEFT JOIN work_order_parts wop ON wo.id = wop.work_order_id
             LEFT JOIN inventory i ON wop.inventory_id = i.id
             WHERE wo.status = 'completed'
             AND DATE(wo.completed_at) BETWEEN :start_date AND :end_date
             AND wo.deleted_at IS NULL
             GROUP BY DATE(wo.completed_at)
             ORDER BY date ASC",
            ['start_date' => $startDate, 'end_date' => $endDate]
        );
    }
    
    public function getPaymentReport($startDate, $endDate) {
        return $this->db->select(
            "SELECT 
                p.payment_method,
                COUNT(*) as transaction_count,
                COALESCE(SUM(p.amount), 0) as total_amount,
                COALESCE(AVG(p.amount), 0) as avg_amount
             FROM payments p
             JOIN work_orders wo ON p.work_order_id = wo.id
             WHERE DATE(p.payment_date) BETWEEN :start_date AND :end_date
             AND p.status = 'completed'
             AND wo.deleted_at IS NULL
             GROUP BY p.payment_method
             ORDER BY total_amount DESC",
            ['start_date' => $startDate, 'end_date' => $endDate]
        );
    }
    
    public function getOutstandingPayments() {
        return $this->db->select(
            "SELECT 
                wo.work_order_number,
                wo.total_amount,
                COALESCE(SUM(p.amount), 0) as paid_amount,
                (wo.total_amount - COALESCE(SUM(p.amount), 0)) as outstanding_amount,
                c.name as customer_name,
                c.phone as customer_phone,
                wo.completed_at,
                DATEDIFF(NOW(), wo.completed_at) as days_overdue
             FROM work_orders wo
             LEFT JOIN payments p ON wo.id = p.work_order_id AND p.status = 'completed'
             LEFT JOIN customers c ON wo.customer_id = c.id
             WHERE wo.status = 'completed'
             AND wo.deleted_at IS NULL
             GROUP BY wo.id
             HAVING outstanding_amount > 0
             ORDER BY days_overdue DESC, outstanding_amount DESC"
        );
    }
    
    // ========================================
    // OPERATIONAL REPORTS
    // ========================================
    
    public function getWorkOrderReport($startDate, $endDate, $filters = []) {
        $where = [
            "DATE(wo.created_at) BETWEEN :start_date AND :end_date",
            "wo.deleted_at IS NULL"
        ];
        $params = ['start_date' => $startDate, 'end_date' => $endDate];
        
        if (!empty($filters['status'])) {
            $where[] = "wo.status = :status";
            $params['status'] = $filters['status'];
        }
        
        if (!empty($filters['technician_id'])) {
            $where[] = "wo.assigned_to = :technician_id";
            $params['technician_id'] = $filters['technician_id'];
        }
        
        if (!empty($filters['priority'])) {
            $where[] = "wo.priority = :priority";
            $params['priority'] = $filters['priority'];
        }
        
        $whereClause = implode(' AND ', $where);
        
        return $this->db->select(
            "SELECT 
                wo.*,
                c.name as customer_name,
                v.license_plate,
                v.brand,
                v.model,
                u.name as technician_name,
                CASE 
                    WHEN wo.status = 'completed' AND wo.estimated_completion > wo.completed_at THEN 'on_time'
                    WHEN wo.status = 'completed' AND wo.estimated_completion <= wo.completed_at THEN 'delayed'
                    WHEN wo.status != 'completed' AND wo.estimated_completion < NOW() THEN 'overdue'
                    ELSE 'on_schedule'
                END as schedule_status
             FROM work_orders wo
             LEFT JOIN customers c ON wo.customer_id = c.id
             LEFT JOIN vehicles v ON wo.vehicle_id = v.id
             LEFT JOIN users u ON wo.assigned_to = u.id
             WHERE {$whereClause}
             ORDER BY wo.created_at DESC",
            $params
        );
    }
    
    public function getTechnicianPerformanceReport($startDate, $endDate) {
        return $this->db->select(
            "SELECT 
                u.name as technician_name,
                COUNT(wo.id) as total_orders,
                COUNT(CASE WHEN wo.status = 'completed' THEN 1 END) as completed_orders,
                COUNT(CASE WHEN wo.status = 'completed' AND wo.estimated_completion >= wo.completed_at THEN 1 END) as on_time_completions,
                COALESCE(SUM(CASE WHEN wo.status = 'completed' THEN wo.total_amount ELSE 0 END), 0) as total_revenue,
                COALESCE(AVG(CASE WHEN wo.status = 'completed' THEN wo.total_amount ELSE NULL END), 0) as avg_order_value,
                COALESCE(AVG(CASE WHEN wo.status = 'completed' THEN TIMESTAMPDIFF(HOUR, wo.started_at, wo.completed_at) ELSE NULL END), 0) as avg_completion_time,
                CASE 
                    WHEN COUNT(CASE WHEN wo.status = 'completed' THEN 1 END) > 0 
                    THEN (COUNT(CASE WHEN wo.status = 'completed' AND wo.estimated_completion >= wo.completed_at THEN 1 END) / COUNT(CASE WHEN wo.status = 'completed' THEN 1 END)) * 100
                    ELSE 0 
                END as on_time_percentage
             FROM users u
             LEFT JOIN work_orders wo ON u.id = wo.assigned_to 
                 AND DATE(wo.created_at) BETWEEN :start_date AND :end_date
                 AND wo.deleted_at IS NULL
             WHERE u.role = 'technician' AND u.status = 'active'
             GROUP BY u.id, u.name
             ORDER BY total_revenue DESC",
            ['start_date' => $startDate, 'end_date' => $endDate]
        );
    }
    
    public function getServicePopularityReport($startDate, $endDate) {
        return $this->db->select(
            "SELECT 
                s.name as service_name,
                sc.name as category_name,
                COUNT(wos.id) as usage_count,
                SUM(wos.quantity) as total_quantity,
                COALESCE(SUM(wos.total_price), 0) as total_revenue,
                COALESCE(AVG(wos.total_price), 0) as avg_price,
                s.price as current_price
             FROM services s
             LEFT JOIN service_categories sc ON s.category_id = sc.id
             LEFT JOIN work_order_services wos ON s.id = wos.service_id
             LEFT JOIN work_orders wo ON wos.work_order_id = wo.id
             WHERE wo.status = 'completed'
             AND DATE(wo.completed_at) BETWEEN :start_date AND :end_date
             AND wo.deleted_at IS NULL
             GROUP BY s.id, s.name, sc.name, s.price
             ORDER BY usage_count DESC, total_revenue DESC",
            ['start_date' => $startDate, 'end_date' => $endDate]
        );
    }
    
    // ========================================
    // INVENTORY REPORTS
    // ========================================
    
    public function getInventoryReport() {
        return $this->db->select(
            "SELECT 
                i.*,
                ic.name as category_name,
                s.name as supplier_name,
                (i.current_stock * i.unit_price) as stock_value,
                CASE 
                    WHEN i.current_stock = 0 THEN 'out_of_stock'
                    WHEN i.current_stock <= i.minimum_stock THEN 'low_stock'
                    ELSE 'in_stock'
                END as stock_status,
                COALESCE(usage.total_used, 0) as total_used_30_days,
                CASE 
                    WHEN COALESCE(usage.total_used, 0) > 0 
                    THEN i.current_stock / (COALESCE(usage.total_used, 0) / 30)
                    ELSE NULL 
                END as days_of_stock_remaining
             FROM inventory i
             LEFT JOIN inventory_categories ic ON i.category_id = ic.id
             LEFT JOIN suppliers s ON i.supplier_id = s.id
             LEFT JOIN (
                 SELECT 
                     im.inventory_id,
                     SUM(ABS(im.quantity)) as total_used
                 FROM inventory_movements im
                 WHERE im.quantity < 0
                 AND im.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                 GROUP BY im.inventory_id
             ) usage ON i.id = usage.inventory_id
             WHERE i.status = 'active'
             ORDER BY ic.name ASC, i.name ASC"
        );
    }
    
    public function getInventoryMovementReport($startDate, $endDate, $inventoryId = null) {
        $where = ["DATE(im.created_at) BETWEEN :start_date AND :end_date"];
        $params = ['start_date' => $startDate, 'end_date' => $endDate];
        
        if ($inventoryId) {
            $where[] = "im.inventory_id = :inventory_id";
            $params['inventory_id'] = $inventoryId;
        }
        
        $whereClause = implode(' AND ', $where);
        
        return $this->db->select(
            "SELECT 
                im.*,
                i.name as item_name,
                i.part_number,
                u.name as performed_by_name,
                wo.work_order_number
             FROM inventory_movements im
             LEFT JOIN inventory i ON im.inventory_id = i.id
             LEFT JOIN users u ON im.performed_by = u.id
             LEFT JOIN work_orders wo ON im.reference_id = wo.id AND im.reference_type = 'work_order'
             WHERE {$whereClause}
             ORDER BY im.created_at DESC",
            $params
        );
    }
    
    public function getInventoryValuationReport() {
        return $this->db->select(
            "SELECT 
                ic.name as category_name,
                COUNT(i.id) as item_count,
                SUM(i.current_stock) as total_quantity,
                SUM(i.current_stock * i.unit_price) as total_value,
                AVG(i.unit_price) as avg_unit_price,
                MIN(i.unit_price) as min_unit_price,
                MAX(i.unit_price) as max_unit_price
             FROM inventory_categories ic
             LEFT JOIN inventory i ON ic.id = i.category_id AND i.status = 'active'
             GROUP BY ic.id, ic.name
             ORDER BY total_value DESC"
        );
    }
    
    // ========================================
    // CUSTOMER REPORTS
    // ========================================
    
    public function getCustomerReport($startDate, $endDate) {
        return $this->db->select(
            "SELECT 
                c.*,
                COUNT(DISTINCT wo.id) as total_orders,
                COUNT(DISTINCT v.id) as total_vehicles,
                COALESCE(SUM(CASE WHEN wo.status = 'completed' THEN wo.total_amount ELSE 0 END), 0) as total_spent,
                COALESCE(AVG(CASE WHEN wo.status = 'completed' THEN wo.total_amount ELSE NULL END), 0) as avg_order_value,
                MIN(wo.created_at) as first_order_date,
                MAX(wo.created_at) as last_order_date,
                DATEDIFF(NOW(), MAX(wo.created_at)) as days_since_last_order
             FROM customers c
             LEFT JOIN work_orders wo ON c.id = wo.customer_id 
                 AND DATE(wo.created_at) BETWEEN :start_date AND :end_date
                 AND wo.deleted_at IS NULL
             LEFT JOIN vehicles v ON c.id = v.customer_id AND v.status = 'active'
             WHERE c.status = 'active'
             GROUP BY c.id
             ORDER BY total_spent DESC",
            ['start_date' => $startDate, 'end_date' => $endDate]
        );
    }
    
    public function getCustomerRetentionReport() {
        return $this->db->select(
            "SELECT 
                DATE_FORMAT(wo.created_at, '%Y-%m') as month,
                COUNT(DISTINCT wo.customer_id) as active_customers,
                COUNT(DISTINCT new_customers.customer_id) as new_customers,
                COUNT(DISTINCT returning_customers.customer_id) as returning_customers
             FROM work_orders wo
             LEFT JOIN (
                 SELECT 
                     customer_id,
                     MIN(DATE_FORMAT(created_at, '%Y-%m')) as first_month
                 FROM work_orders
                 WHERE deleted_at IS NULL
                 GROUP BY customer_id
             ) new_customers ON wo.customer_id = new_customers.customer_id 
                 AND DATE_FORMAT(wo.created_at, '%Y-%m') = new_customers.first_month
             LEFT JOIN (
                 SELECT DISTINCT
                     wo1.customer_id,
                     DATE_FORMAT(wo1.created_at, '%Y-%m') as month
                 FROM work_orders wo1
                 WHERE EXISTS (
                     SELECT 1 FROM work_orders wo2
                     WHERE wo2.customer_id = wo1.customer_id
                     AND wo2.created_at < wo1.created_at
                     AND wo2.deleted_at IS NULL
                 )
                 AND wo1.deleted_at IS NULL
             ) returning_customers ON wo.customer_id = returning_customers.customer_id 
                 AND DATE_FORMAT(wo.created_at, '%Y-%m') = returning_customers.month
             WHERE wo.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
             AND wo.deleted_at IS NULL
             GROUP BY DATE_FORMAT(wo.created_at, '%Y-%m')
             ORDER BY month ASC"
        );
    }
    
    // ========================================
    // VEHICLE REPORTS
    // ========================================
    
    public function getVehicleReport($startDate, $endDate) {
        return $this->db->select(
            "SELECT 
                v.*,
                c.name as customer_name,
                COUNT(wo.id) as service_count,
                COALESCE(SUM(CASE WHEN wo.status = 'completed' THEN wo.total_amount ELSE 0 END), 0) as total_service_cost,
                MAX(wo.created_at) as last_service_date,
                DATEDIFF(NOW(), MAX(wo.created_at)) as days_since_last_service
             FROM vehicles v
             LEFT JOIN customers c ON v.customer_id = c.id
             LEFT JOIN work_orders wo ON v.id = wo.vehicle_id 
                 AND DATE(wo.created_at) BETWEEN :start_date AND :end_date
                 AND wo.deleted_at IS NULL
             WHERE v.status = 'active'
             GROUP BY v.id
             ORDER BY total_service_cost DESC",
            ['start_date' => $startDate, 'end_date' => $endDate]
        );
    }
    
    public function getVehicleBrandReport() {
        return $this->db->select(
            "SELECT 
                v.brand,
                COUNT(DISTINCT v.id) as vehicle_count,
                COUNT(wo.id) as total_services,
                COALESCE(SUM(CASE WHEN wo.status = 'completed' THEN wo.total_amount ELSE 0 END), 0) as total_revenue,
                COALESCE(AVG(CASE WHEN wo.status = 'completed' THEN wo.total_amount ELSE NULL END), 0) as avg_service_cost
             FROM vehicles v
             LEFT JOIN work_orders wo ON v.id = wo.vehicle_id AND wo.deleted_at IS NULL
             WHERE v.status = 'active'
             GROUP BY v.brand
             ORDER BY total_revenue DESC"
        );
    }
    
    // ========================================
    // DASHBOARD ANALYTICS
    // ========================================
    
    public function getDashboardStats($period = 'today') {
        $dateCondition = $this->getDateCondition($period);
        
        $stats = [];
        
        // Revenue stats
        $revenueStats = $this->db->selectOne(
            "SELECT 
                COUNT(CASE WHEN wo.status = 'completed' THEN 1 END) as completed_orders,
                COALESCE(SUM(CASE WHEN wo.status = 'completed' THEN wo.total_amount ELSE 0 END), 0) as total_revenue,
                COUNT(CASE WHEN wo.status IN ('pending', 'confirmed', 'in_progress') THEN 1 END) as active_orders,
                COUNT(CASE WHEN wo.status = 'pending' THEN 1 END) as pending_orders
             FROM work_orders wo
             WHERE {$dateCondition} AND wo.deleted_at IS NULL"
        );
        
        $stats['revenue'] = $revenueStats;
        
        // Customer stats
        $customerStats = $this->db->selectOne(
            "SELECT 
                COUNT(DISTINCT wo.customer_id) as active_customers,
                COUNT(DISTINCT new_customers.id) as new_customers
             FROM work_orders wo
             LEFT JOIN customers new_customers ON wo.customer_id = new_customers.id 
                 AND DATE(new_customers.created_at) = CURDATE()
             WHERE {$dateCondition} AND wo.deleted_at IS NULL"
        );
        
        $stats['customers'] = $customerStats;
        
        // Inventory alerts
        $inventoryAlerts = $this->db->selectOne(
            "SELECT 
                COUNT(CASE WHEN current_stock = 0 THEN 1 END) as out_of_stock,
                COUNT(CASE WHEN current_stock <= minimum_stock AND current_stock > 0 THEN 1 END) as low_stock
             FROM inventory
             WHERE status = 'active'"
        );
        
        $stats['inventory'] = $inventoryAlerts;
        
        return $stats;
    }
    
    public function getRevenueChart($days = 30) {
        return $this->db->select(
            "SELECT 
                DATE(wo.completed_at) as date,
                COALESCE(SUM(wo.total_amount), 0) as revenue,
                COUNT(wo.id) as order_count
             FROM work_orders wo
             WHERE wo.status = 'completed'
             AND wo.completed_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
             AND wo.deleted_at IS NULL
             GROUP BY DATE(wo.completed_at)
             ORDER BY date ASC",
            ['days' => $days]
        );
    }
    
    // ========================================
    // EXPORT FUNCTIONS
    // ========================================
    
    public function exportToCSV($data, $filename, $headers = []) {
        if (empty($data)) {
            throw new Exception('No data to export');
        }
        
        // Set headers for CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Expires: 0');
        
        $output = fopen('php://output', 'w');
        
        // Write headers
        if (empty($headers)) {
            $headers = array_keys($data[0]);
        }
        fputcsv($output, $headers);
        
        // Write data
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit;
    }
    
    public function exportToPDF($data, $title, $filename) {
        // This would integrate with a PDF library like TCPDF or FPDF
        // Implementation depends on your PDF library choice
        
        throw new Exception('PDF export not implemented yet');
    }
    
    // ========================================
    // UTILITY METHODS
    // ========================================
    
    private function getGroupByClause($groupBy) {
        switch ($groupBy) {
            case 'hour':
                return "DATE_FORMAT(wo.completed_at, '%Y-%m-%d %H:00:00')";
            case 'day':
                return "DATE(wo.completed_at)";
            case 'week':
                return "DATE_FORMAT(wo.completed_at, '%Y-%u')";
            case 'month':
                return "DATE_FORMAT(wo.completed_at, '%Y-%m')";
            case 'year':
                return "YEAR(wo.completed_at)";
            default:
                return "DATE(wo.completed_at)";
        }
    }
    
    private function getDateCondition($period) {
        switch ($period) {
            case 'today':
                return "DATE(wo.created_at) = CURDATE()";
            case 'yesterday':
                return "DATE(wo.created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
            case 'this_week':
                return "YEARWEEK(wo.created_at) = YEARWEEK(NOW())";
            case 'last_week':
                return "YEARWEEK(wo.created_at) = YEARWEEK(NOW()) - 1";
            case 'this_month':
                return "MONTH(wo.created_at) = MONTH(NOW()) AND YEAR(wo.created_at) = YEAR(NOW())";
            case 'last_month':
                return "MONTH(wo.created_at) = MONTH(NOW()) - 1 AND YEAR(wo.created_at) = YEAR(NOW())";
            case 'this_year':
                return "YEAR(wo.created_at) = YEAR(NOW())";
            case 'last_30_days':
                return "wo.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            default:
                return "DATE(wo.created_at) = CURDATE()";
        }
    }
    
    public function generateReportId() {
        return 'RPT' . date('YmdHis') . rand(1000, 9999);
    }
    
    public function saveReportHistory($reportType, $parameters, $generatedBy) {
        return $this->db->insert('report_history', [
            'report_id' => $this->generateReportId(),
            'report_type' => $reportType,
            'parameters' => json_encode($parameters),
            'generated_by' => $generatedBy,
            'generated_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    public function getReportHistory($userId = null, $limit = 50) {
        $where = "1=1";
        $params = [];
        
        if ($userId) {
            $where = "rh.generated_by = :user_id";
            $params['user_id'] = $userId;
        }
        
        return $this->db->select(
            "SELECT rh.*, u.name as generated_by_name
             FROM report_history rh
             LEFT JOIN users u ON rh.generated_by = u.id
             WHERE {$where}
             ORDER BY rh.generated_at DESC
             LIMIT {$limit}",
            $params
        );
    }
}
