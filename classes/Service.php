<?php
/**
 * BENGKEL MANAGEMENT PRO - SERVICE CLASS
 * Version: 3.1.0
 * Complete Service Management System
 */

class Service {
    private $db;
    private $table = 'services';
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    // ========================================
    // SERVICE CRUD OPERATIONS
    // ========================================
    
    public function create($data) {
        try {
            debugLog(['action' => 'create_service', 'data' => $data], 'SERVICE_CREATE_START');
            
            // Validate required fields
            $requiredFields = ['name', 'category_id', 'price', 'duration_minutes'];
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    debugLog(['missing_field' => $field], 'SERVICE_VALIDATION_ERROR');
                    throw new Exception("Field {$field} is required");
                }
            }
            debugLog('Required fields validation passed', 'SERVICE_VALIDATION');
            
            // Validate numeric fields
            if (!is_numeric($data['price']) || $data['price'] < 0) {
                debugLog(['invalid_price' => $data['price']], 'SERVICE_VALIDATION_ERROR');
                throw new Exception('Invalid price');
            }
            
            if (!is_numeric($data['duration_minutes']) || $data['duration_minutes'] <= 0) {
                debugLog(['invalid_duration' => $data['duration_minutes']], 'SERVICE_VALIDATION_ERROR');
                throw new Exception('Invalid duration');
            }
            debugLog('Numeric fields validation passed', 'SERVICE_VALIDATION');
            
            // Set default values
            $data['status'] = $data['status'] ?? 'active';
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['created_by'] = $_SESSION['user_id'] ?? null;
            
            debugLog(['prepared_data' => $data], 'SERVICE_CREATE_PREPARED');
            
            // Insert service
            $serviceId = $this->db->insert($this->table, $data);
            
            debugLog(['service_id' => $serviceId], 'SERVICE_CREATE_SUCCESS');
            
            // Log activity
            $this->logActivity($serviceId, 'created', 'Service created');
            
            return $serviceId;
        } catch (Exception $e) {
            debugLog(['error' => $e->getMessage(), 'data' => $data], 'SERVICE_CREATE_ERROR');
            throw $e;
        }
    }
    
    public function update($id, $data) {
        try {
            $service = $this->findById($id);
            if (!$service) {
                throw new Exception('Service not found');
            }
            
            // Validate numeric fields if being updated
            if (isset($data['price']) && (!is_numeric($data['price']) || $data['price'] < 0)) {
                throw new Exception('Invalid price');
            }
            
            if (isset($data['duration_minutes']) && (!is_numeric($data['duration_minutes']) || $data['duration_minutes'] <= 0)) {
                throw new Exception('Invalid duration');
            }
            
            $data['updated_at'] = date('Y-m-d H:i:s');
            $data['updated_by'] = $_SESSION['user_id'] ?? null;
            
            $updated = $this->db->update(
                $this->table,
                $data,
                'id = :id',
                ['id' => $id]
            );
            
            if ($updated) {
                $this->logActivity($id, 'updated', 'Service updated');
            }
            
            return $updated > 0;
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    public function delete($id) {
        try {
            $service = $this->findById($id);
            if (!$service) {
                throw new Exception('Service not found');
            }
            
            // Check if service is used in any work orders
            $usageCount = $this->db->count('work_order_services', 'service_id = :service_id', ['service_id' => $id]);
            if ($usageCount > 0) {
                throw new Exception('Cannot delete service that has been used in work orders');
            }
            
            // Soft delete
            $deleted = $this->db->update(
                $this->table,
                [
                    'status' => 'deleted',
                    'deleted_at' => date('Y-m-d H:i:s'),
                    'deleted_by' => $_SESSION['user_id'] ?? null
                ],
                'id = :id',
                ['id' => $id]
            );
            
            if ($deleted) {
                $this->logActivity($id, 'deleted', 'Service deleted');
            }
            
            return $deleted > 0;
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    public function findById($id) {
        return $this->db->selectOne(
            "SELECT s.*, sc.name as category_name,
                    COUNT(DISTINCT wos.work_order_id) as usage_count,
                    COALESCE(SUM(wos.total_price), 0) as total_revenue
             FROM {$this->table} s
             LEFT JOIN service_categories sc ON s.category_id = sc.id
             LEFT JOIN work_order_services wos ON s.id = wos.service_id
             WHERE s.id = :id AND s.status != 'deleted'
             GROUP BY s.id",
            ['id' => $id]
        );
    }
    
    public function getAll($filters = []) {
        $where = ["s.status != 'deleted'"];
        $params = [];
        
        if (!empty($filters['status'])) {
            $where[] = "s.status = :status";
            $params['status'] = $filters['status'];
        }
        
        if (!empty($filters['category_id'])) {
            $where[] = "s.category_id = :category_id";
            $params['category_id'] = $filters['category_id'];
        }
        
        if (!empty($filters['search'])) {
            $where[] = "(s.name LIKE :search OR s.description LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }
        
        if (!empty($filters['price_min'])) {
            $where[] = "s.price >= :price_min";
            $params['price_min'] = $filters['price_min'];
        }
        
        if (!empty($filters['price_max'])) {
            $where[] = "s.price <= :price_max";
            $params['price_max'] = $filters['price_max'];
        }
        
        $whereClause = implode(' AND ', $where);
        $orderBy = $filters['order_by'] ?? 's.name ASC';
        
        $sql = "SELECT s.*, sc.name as category_name,
                       COUNT(DISTINCT wos.work_order_id) as usage_count,
                       COALESCE(SUM(wos.total_price), 0) as total_revenue
                FROM {$this->table} s
                LEFT JOIN service_categories sc ON s.category_id = sc.id
                LEFT JOIN work_order_services wos ON s.id = wos.service_id
                WHERE {$whereClause}
                GROUP BY s.id
                ORDER BY {$orderBy}";
        
        return $this->db->select($sql, $params);
    }
    
    public function paginate($page = 1, $perPage = 20, $filters = []) {
        $where = ["s.status != 'deleted'"];
        $params = [];
        
        // Apply same filters as getAll method
        if (!empty($filters['status'])) {
            $where[] = "s.status = :status";
            $params['status'] = $filters['status'];
        }
        
        if (!empty($filters['search'])) {
            $where[] = "(s.name LIKE :search OR s.description LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }
        
        $whereClause = implode(' AND ', $where);
        $orderBy = $filters['order_by'] ?? 's.name ASC';
        
        $sql = "SELECT s.*, sc.name as category_name,
                       COUNT(DISTINCT wos.work_order_id) as usage_count,
                       COALESCE(SUM(wos.total_price), 0) as total_revenue
                FROM {$this->table} s
                LEFT JOIN service_categories sc ON s.category_id = sc.id
                LEFT JOIN work_order_services wos ON s.id = wos.service_id
                WHERE {$whereClause}
                GROUP BY s.id
                ORDER BY {$orderBy}";
        
        return $this->db->paginate($sql, $params, $page, $perPage);
    }
    
    // ========================================
    // SERVICE ANALYTICS
    // ========================================
    
    public function getServiceStats($serviceId = null) {
        $stats = [];
        
        if ($serviceId) {
            // Stats for specific service
            $serviceStats = $this->db->selectOne(
                "SELECT 
                    COUNT(DISTINCT wos.work_order_id) as total_orders,
                    SUM(wos.quantity) as total_quantity,
                    COALESCE(SUM(wos.total_price), 0) as total_revenue,
                    COALESCE(AVG(wos.total_price), 0) as avg_revenue_per_order,
                    MIN(wo.created_at) as first_used_date,
                    MAX(wo.created_at) as last_used_date
                 FROM work_order_services wos
                 JOIN work_orders wo ON wos.work_order_id = wo.id
                 WHERE wos.service_id = :service_id AND wo.status = 'completed'",
                ['service_id' => $serviceId]
            );
            
            $stats = $serviceStats;
            
            // Monthly usage
            $monthlyStats = $this->db->select(
                "SELECT 
                    DATE_FORMAT(wo.created_at, '%Y-%m') as month,
                    COUNT(DISTINCT wos.work_order_id) as order_count,
                    SUM(wos.quantity) as quantity,
                    COALESCE(SUM(wos.total_price), 0) as revenue
                 FROM work_order_services wos
                 JOIN work_orders wo ON wos.work_order_id = wo.id
                 WHERE wos.service_id = :service_id 
                 AND wo.status = 'completed'
                 AND wo.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                 GROUP BY DATE_FORMAT(wo.created_at, '%Y-%m')
                 ORDER BY month ASC",
                ['service_id' => $serviceId]
            );
            
            $stats['monthly_stats'] = $monthlyStats;
        } else {
            // Global service stats
            $globalStats = $this->db->selectOne(
                "SELECT 
                    COUNT(*) as total_services,
                    COUNT(CASE WHEN status = 'active' THEN 1 END) as active_services,
                    AVG(price) as avg_price,
                    MIN(price) as min_price,
                    MAX(price) as max_price
                 FROM {$this->table}
                 WHERE status != 'deleted'"
            );
            
            $stats = $globalStats;
            
            // Top services by revenue
            $topServices = $this->db->select(
                "SELECT s.name, s.price,
                        COUNT(DISTINCT wos.work_order_id) as order_count,
                        COALESCE(SUM(wos.total_price), 0) as total_revenue
                 FROM {$this->table} s
                 LEFT JOIN work_order_services wos ON s.id = wos.service_id
                 LEFT JOIN work_orders wo ON wos.work_order_id = wo.id AND wo.status = 'completed'
                 WHERE s.status = 'active'
                 GROUP BY s.id, s.name, s.price
                 ORDER BY total_revenue DESC
                 LIMIT 10"
            );
            
            $stats['top_services'] = $topServices;
            
            // Services by category
            $categoryStats = $this->db->select(
                "SELECT sc.name as category_name,
                        COUNT(s.id) as service_count,
                        AVG(s.price) as avg_price,
                        COALESCE(SUM(wos.total_price), 0) as total_revenue
                 FROM service_categories sc
                 LEFT JOIN {$this->table} s ON sc.id = s.category_id AND s.status = 'active'
                 LEFT JOIN work_order_services wos ON s.id = wos.service_id
                 LEFT JOIN work_orders wo ON wos.work_order_id = wo.id AND wo.status = 'completed'
                 GROUP BY sc.id, sc.name
                 ORDER BY total_revenue DESC"
            );
            
            $stats['by_category'] = $categoryStats;
        }
        
        return $stats;
    }
    
    public function getPopularServices($limit = 10, $days = 30) {
        return $this->db->select(
            "SELECT s.*, sc.name as category_name,
                    COUNT(DISTINCT wos.work_order_id) as order_count,
                    SUM(wos.quantity) as total_quantity,
                    COALESCE(SUM(wos.total_price), 0) as total_revenue
             FROM {$this->table} s
             LEFT JOIN service_categories sc ON s.category_id = sc.id
             LEFT JOIN work_order_services wos ON s.id = wos.service_id
             LEFT JOIN work_orders wo ON wos.work_order_id = wo.id 
                 AND wo.status = 'completed'
                 AND wo.created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
             WHERE s.status = 'active'
             GROUP BY s.id
             HAVING order_count > 0
             ORDER BY order_count DESC, total_revenue DESC
             LIMIT :limit",
            ['days' => $days, 'limit' => $limit]
        );
    }
    
    public function getServicePerformance($days = 30) {
        return $this->db->select(
            "SELECT s.name, s.price, s.duration_minutes,
                    COUNT(DISTINCT wos.work_order_id) as order_count,
                    COALESCE(SUM(wos.total_price), 0) as total_revenue,
                    COALESCE(AVG(wos.total_price), 0) as avg_revenue_per_order,
                    (COALESCE(SUM(wos.total_price), 0) / NULLIF(s.duration_minutes, 0)) as revenue_per_minute
             FROM {$this->table} s
             LEFT JOIN work_order_services wos ON s.id = wos.service_id
             LEFT JOIN work_orders wo ON wos.work_order_id = wo.id 
                 AND wo.status = 'completed'
                 AND wo.created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
             WHERE s.status = 'active'
             GROUP BY s.id, s.name, s.price, s.duration_minutes
             ORDER BY revenue_per_minute DESC",
            ['days' => $days]
        );
    }
    
    // ========================================
    // SERVICE CATEGORIES
    // ========================================
    
    public function getCategories() {
        return $this->db->select(
            "SELECT sc.*, COUNT(s.id) as service_count
             FROM service_categories sc
             LEFT JOIN {$this->table} s ON sc.id = s.category_id AND s.status = 'active'
             WHERE sc.status = 'active'
             GROUP BY sc.id
             ORDER BY sc.name ASC"
        );
    }
    
    public function createCategory($data) {
        try {
            if (empty($data['name'])) {
                throw new Exception('Category name is required');
            }
            
            $data['status'] = 'active';
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['created_by'] = $_SESSION['user_id'] ?? null;
            
            return $this->db->insert('service_categories', $data);
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    public function updateCategory($id, $data) {
        try {
            $data['updated_at'] = date('Y-m-d H:i:s');
            $data['updated_by'] = $_SESSION['user_id'] ?? null;
            
            return $this->db->update(
                'service_categories',
                $data,
                'id = :id',
                ['id' => $id]
            );
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    public function deleteCategory($id) {
        try {
            // Check if category has services
            $serviceCount = $this->db->count($this->table, 'category_id = :category_id AND status != "deleted"', ['category_id' => $id]);
            if ($serviceCount > 0) {
                throw new Exception('Cannot delete category that has services');
            }
            
            return $this->db->update(
                'service_categories',
                [
                    'status' => 'deleted',
                    'deleted_at' => date('Y-m-d H:i:s'),
                    'deleted_by' => $_SESSION['user_id'] ?? null
                ],
                'id = :id',
                ['id' => $id]
            );
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    // ========================================
    // UTILITY METHODS
    // ========================================
    
    private function logActivity($serviceId, $action, $description) {
        $this->db->insert('service_activities', [
            'service_id' => $serviceId,
            'action' => $action,
            'description' => $description,
            'performed_by' => $_SESSION['user_id'] ?? null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    public function search($query, $limit = 10) {
        return $this->db->select(
            "SELECT s.id, s.name, s.price, s.duration_minutes, sc.name as category_name
             FROM {$this->table} s
             LEFT JOIN service_categories sc ON s.category_id = sc.id
             WHERE s.status = 'active'
             AND (s.name LIKE :query OR s.description LIKE :query)
             ORDER BY s.name ASC
             LIMIT {$limit}",
            ['query' => "%{$query}%"]
        );
    }
    
    public function bulkUpdatePrices($categoryId, $percentage, $type = 'increase') {
        $this->db->beginTransaction();
        
        try {
            $multiplier = $type === 'increase' ? (1 + $percentage / 100) : (1 - $percentage / 100);
            
            $updated = $this->db->query(
                "UPDATE {$this->table} 
                 SET price = price * :multiplier,
                     updated_at = NOW(),
                     updated_by = :updated_by
                 WHERE category_id = :category_id AND status = 'active'",
                [
                    'multiplier' => $multiplier,
                    'category_id' => $categoryId,
                    'updated_by' => $_SESSION['user_id'] ?? null
                ]
            );
            
            $this->db->commit();
            
            return $updated->rowCount();
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    public function getServicesByCategory($categoryId) {
        return $this->db->select(
            "SELECT * FROM {$this->table}
             WHERE category_id = :category_id AND status = 'active'
             ORDER BY name ASC",
            ['category_id' => $categoryId]
        );
    }
    
    public function getRecommendedServices($vehicleId) {
        // Get services based on vehicle's service history and maintenance schedule
        return $this->db->select(
            "SELECT DISTINCT s.*, sc.name as category_name
             FROM {$this->table} s
             LEFT JOIN service_categories sc ON s.category_id = sc.id
             LEFT JOIN work_order_services wos ON s.id = wos.service_id
             LEFT JOIN work_orders wo ON wos.work_order_id = wo.id
             WHERE s.status = 'active'
             AND (
                 s.maintenance_interval_km > 0 OR 
                 s.maintenance_interval_months > 0 OR
                 wo.vehicle_id = :vehicle_id
             )
             GROUP BY s.id
             ORDER BY COUNT(wo.id) DESC, s.name ASC
             LIMIT 10",
            ['vehicle_id' => $vehicleId]
        );
    }
}
