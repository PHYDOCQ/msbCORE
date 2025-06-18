<?php
/**
 * BENGKEL MANAGEMENT PRO - CUSTOMER CLASS
 * Version: 3.1.0
 * Complete Customer Management System
 */

class Customer {
    private $db;
    private $table = 'customers';
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    // ========================================
    // CUSTOMER CRUD OPERATIONS
    // ========================================
    
    public function create($data) {
        $this->db->beginTransaction();
        
        try {
            // Validate required fields
            $requiredFields = ['name', 'email', 'phone'];
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    throw new Exception("Field {$field} is required");
                }
            }
            
            // Validate email format
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Invalid email format');
            }
            
            // Check if email already exists
            if ($this->emailExists($data['email'])) {
                throw new Exception('Email already exists');
            }
            
            // Generate customer code
            $data['customer_code'] = $this->generateCustomerCode();
            
            // Set default values
            $data['status'] = $data['status'] ?? 'active';
            $data['customer_type'] = $data['customer_type'] ?? 'individual';
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['created_by'] = $_SESSION['user_id'] ?? null;
            
            // Insert customer
            $customerId = $this->db->insert($this->table, $data);
            
            // Create customer profile if additional data provided
            if (isset($data['additional_info'])) {
                $this->createCustomerProfile($customerId, $data['additional_info']);
            }
            
            // Log activity
            $this->logActivity($customerId, 'created', 'Customer created');
            
            $this->db->commit();
            
            return $customerId;
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    public function update($id, $data) {
        $this->db->beginTransaction();
        
        try {
            $customer = $this->findById($id);
            if (!$customer) {
                throw new Exception('Customer not found');
            }
            
            // Validate email if being updated
            if (isset($data['email'])) {
                if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                    throw new Exception('Invalid email format');
                }
                
                if ($this->emailExists($data['email'], $id)) {
                    throw new Exception('Email already exists');
                }
            }
            
            $data['updated_at'] = date('Y-m-d H:i:s');
            $data['updated_by'] = $_SESSION['user_id'] ?? null;
            
            // Update customer
            $updated = $this->db->update(
                $this->table,
                $data,
                'id = :id',
                ['id' => $id]
            );
            
            // Update customer profile if additional data provided
            if (isset($data['additional_info'])) {
                $this->updateCustomerProfile($id, $data['additional_info']);
            }
            
            if ($updated) {
                $this->logActivity($id, 'updated', 'Customer information updated');
            }
            
            $this->db->commit();
            
            return $updated > 0;
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    public function delete($id) {
        try {
            $customer = $this->findById($id);
            if (!$customer) {
                throw new Exception('Customer not found');
            }
            
            // Check if customer has active work orders
            $activeWorkOrders = $this->getActiveWorkOrders($id);
            if (!empty($activeWorkOrders)) {
                throw new Exception('Cannot delete customer with active work orders');
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
                $this->logActivity($id, 'deleted', 'Customer deleted');
            }
            
            return $deleted > 0;
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    public function findById($id) {
        return $this->db->selectOne(
            "SELECT c.*, cp.*, 
                    COUNT(DISTINCT v.id) as total_vehicles,
                    COUNT(DISTINCT wo.id) as total_work_orders,
                    COALESCE(SUM(CASE WHEN wo.status = 'completed' THEN wo.total_amount ELSE 0 END), 0) as total_spent
             FROM {$this->table} c
             LEFT JOIN customer_profiles cp ON c.id = cp.customer_id
             LEFT JOIN vehicles v ON c.id = v.customer_id AND v.status = 'active'
             LEFT JOIN work_orders wo ON c.id = wo.customer_id AND wo.deleted_at IS NULL
             WHERE c.id = :id AND c.status != 'deleted'
             GROUP BY c.id",
            ['id' => $id]
        );
    }
    
    public function findByEmail($email) {
        return $this->db->selectOne(
            "SELECT * FROM {$this->table} WHERE email = :email AND status != 'deleted'",
            ['email' => $email]
        );
    }
    
    public function findByCode($customerCode) {
        return $this->db->selectOne(
            "SELECT * FROM {$this->table} WHERE customer_code = :customer_code AND status != 'deleted'",
            ['customer_code' => $customerCode]
        );
    }
    
    public function getAll($filters = []) {
        $where = ["c.status != 'deleted'"];
        $params = [];
        
        if (!empty($filters['status'])) {
            $where[] = "c.status = :status";
            $params['status'] = $filters['status'];
        }
        
        if (!empty($filters['customer_type'])) {
            $where[] = "c.customer_type = :customer_type";
            $params['customer_type'] = $filters['customer_type'];
        }
        
        if (!empty($filters['search'])) {
            $where[] = "(c.name LIKE :search OR c.email LIKE :search OR c.phone LIKE :search OR c.customer_code LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }
        
        if (!empty($filters['date_from'])) {
            $where[] = "DATE(c.created_at) >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where[] = "DATE(c.created_at) <= :date_to";
            $params['date_to'] = $filters['date_to'];
        }
        
        $whereClause = implode(' AND ', $where);
        $orderBy = $filters['order_by'] ?? 'c.created_at DESC';
        
        $sql = "SELECT c.*, 
                       COUNT(DISTINCT v.id) as total_vehicles,
                       COUNT(DISTINCT wo.id) as total_work_orders,
                       COALESCE(SUM(CASE WHEN wo.status = 'completed' THEN wo.total_amount ELSE 0 END), 0) as total_spent,
                       MAX(wo.created_at) as last_service_date
                FROM {$this->table} c
                LEFT JOIN vehicles v ON c.id = v.customer_id AND v.status = 'active'
                LEFT JOIN work_orders wo ON c.id = wo.customer_id AND wo.deleted_at IS NULL
                WHERE {$whereClause}
                GROUP BY c.id
                ORDER BY {$orderBy}";
        
        return $this->db->select($sql, $params);
    }
    
    public function paginate($page = 1, $perPage = 20, $filters = []) {
        $where = ["c.status != 'deleted'"];
        $params = [];
        
        // Apply same filters as getAll method
        if (!empty($filters['status'])) {
            $where[] = "c.status = :status";
            $params['status'] = $filters['status'];
        }
        
        if (!empty($filters['search'])) {
            $where[] = "(c.name LIKE :search OR c.email LIKE :search OR c.phone LIKE :search OR c.customer_code LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }
        
        $whereClause = implode(' AND ', $where);
        $orderBy = $filters['order_by'] ?? 'c.created_at DESC';
        
        $sql = "SELECT c.*, 
                       COUNT(DISTINCT v.id) as total_vehicles,
                       COUNT(DISTINCT wo.id) as total_work_orders,
                       COALESCE(SUM(CASE WHEN wo.status = 'completed' THEN wo.total_amount ELSE 0 END), 0) as total_spent
                FROM {$this->table} c
                LEFT JOIN vehicles v ON c.id = v.customer_id AND v.status = 'active'
                LEFT JOIN work_orders wo ON c.id = wo.customer_id AND wo.deleted_at IS NULL
                WHERE {$whereClause}
                GROUP BY c.id
                ORDER BY {$orderBy}";
        
        return $this->db->paginate($sql, $params, $page, $perPage);
    }
    
    // ========================================
    // CUSTOMER RELATIONSHIPS
    // ========================================
    
    public function getVehicles($customerId) {
        return $this->db->select(
            "SELECT v.*, 
                    COUNT(wo.id) as total_services,
                    MAX(wo.created_at) as last_service_date,
                    COALESCE(SUM(CASE WHEN wo.status = 'completed' THEN wo.total_amount ELSE 0 END), 0) as total_service_cost
             FROM vehicles v
             LEFT JOIN work_orders wo ON v.id = wo.vehicle_id AND wo.deleted_at IS NULL
             WHERE v.customer_id = :customer_id AND v.status = 'active'
             GROUP BY v.id
             ORDER BY v.created_at DESC",
            ['customer_id' => $customerId]
        );
    }
    
    public function getWorkOrders($customerId, $limit = null) {
        $sql = "SELECT wo.*, v.license_plate, v.brand, v.model
                FROM work_orders wo
                LEFT JOIN vehicles v ON wo.vehicle_id = v.id
                WHERE wo.customer_id = :customer_id AND wo.deleted_at IS NULL
                ORDER BY wo.created_at DESC";
        
        if ($limit) {
            $sql .= " LIMIT {$limit}";
        }
        
        return $this->db->select($sql, ['customer_id' => $customerId]);
    }
    
    public function getActiveWorkOrders($customerId) {
        return $this->db->select(
            "SELECT wo.*, v.license_plate, v.brand, v.model
             FROM work_orders wo
             LEFT JOIN vehicles v ON wo.vehicle_id = v.id
             WHERE wo.customer_id = :customer_id 
             AND wo.status IN ('pending', 'confirmed', 'in_progress', 'waiting_parts')
             AND wo.deleted_at IS NULL
             ORDER BY wo.created_at DESC",
            ['customer_id' => $customerId]
        );
    }
    
    public function getServiceHistory($customerId, $vehicleId = null) {
        $where = "wo.customer_id = :customer_id AND wo.status = 'completed' AND wo.deleted_at IS NULL";
        $params = ['customer_id' => $customerId];
        
        if ($vehicleId) {
            $where .= " AND wo.vehicle_id = :vehicle_id";
            $params['vehicle_id'] = $vehicleId;
        }
        
        return $this->db->select(
            "SELECT wo.*, v.license_plate, v.brand, v.model,
                    GROUP_CONCAT(s.name SEPARATOR ', ') as services
             FROM work_orders wo
             LEFT JOIN vehicles v ON wo.vehicle_id = v.id
             LEFT JOIN work_order_services wos ON wo.id = wos.work_order_id
             LEFT JOIN services s ON wos.service_id = s.id
             WHERE {$where}
             GROUP BY wo.id
             ORDER BY wo.completed_at DESC",
            $params
        );
    }
    
    // ========================================
    // CUSTOMER ANALYTICS
    // ========================================
    
    public function getCustomerStats($customerId) {
        $stats = [];
        
        // Basic stats
        $basicStats = $this->db->selectOne(
            "SELECT 
                COUNT(DISTINCT v.id) as total_vehicles,
                COUNT(DISTINCT wo.id) as total_work_orders,
                COUNT(CASE WHEN wo.status = 'completed' THEN 1 END) as completed_work_orders,
                COALESCE(SUM(CASE WHEN wo.status = 'completed' THEN wo.total_amount ELSE 0 END), 0) as total_spent,
                COALESCE(AVG(CASE WHEN wo.status = 'completed' THEN wo.total_amount ELSE NULL END), 0) as avg_order_value,
                MIN(wo.created_at) as first_service_date,
                MAX(wo.created_at) as last_service_date
             FROM customers c
             LEFT JOIN vehicles v ON c.id = v.customer_id AND v.status = 'active'
             LEFT JOIN work_orders wo ON c.id = wo.customer_id AND wo.deleted_at IS NULL
             WHERE c.id = :customer_id",
            ['customer_id' => $customerId]
        );
        
        $stats = array_merge($stats, $basicStats);
        
        // Monthly spending
        $monthlySpending = $this->db->select(
            "SELECT 
                DATE_FORMAT(wo.created_at, '%Y-%m') as month,
                COALESCE(SUM(wo.total_amount), 0) as total
             FROM work_orders wo
             WHERE wo.customer_id = :customer_id 
             AND wo.status = 'completed'
             AND wo.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
             AND wo.deleted_at IS NULL
             GROUP BY DATE_FORMAT(wo.created_at, '%Y-%m')
             ORDER BY month ASC",
            ['customer_id' => $customerId]
        );
        
        $stats['monthly_spending'] = $monthlySpending;
        
        // Most used services
        $topServices = $this->db->select(
            "SELECT s.name, COUNT(*) as usage_count, SUM(wos.total_price) as total_revenue
             FROM work_order_services wos
             JOIN services s ON wos.service_id = s.id
             JOIN work_orders wo ON wos.work_order_id = wo.id
             WHERE wo.customer_id = :customer_id AND wo.status = 'completed'
             GROUP BY s.id, s.name
             ORDER BY usage_count DESC
             LIMIT 10",
            ['customer_id' => $customerId]
        );
        
        $stats['top_services'] = $topServices;
        
        return $stats;
    }
    
    public function getLoyaltyScore($customerId) {
        $customer = $this->findById($customerId);
        if (!$customer) return 0;
        
        $score = 0;
        
        // Points for years as customer
        $yearsAsCustomer = (strtotime('now') - strtotime($customer['created_at'])) / (365.25 * 24 * 60 * 60);
        $score += floor($yearsAsCustomer) * 10;
        
        // Points for total spending
        $totalSpent = floatval($customer['total_spent']);
        $score += floor($totalSpent / 1000000) * 5; // 5 points per 1 million spent
        
        // Points for number of work orders
        $totalOrders = intval($customer['total_work_orders']);
        $score += $totalOrders * 2;
        
        // Points for recent activity (last 6 months)
        $recentActivity = $this->db->count(
            'work_orders',
            'customer_id = :customer_id AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) AND deleted_at IS NULL',
            ['customer_id' => $customerId]
        );
        $score += $recentActivity * 5;
        
        return min($score, 1000); // Cap at 1000 points
    }
    
    public function getCustomerSegment($customerId) {
        $loyaltyScore = $this->getLoyaltyScore($customerId);
        $customer = $this->findById($customerId);
        
        if (!$customer) return 'unknown';
        
        $totalSpent = floatval($customer['total_spent']);
        $totalOrders = intval($customer['total_work_orders']);
        
        // Define segments based on spending and loyalty
        if ($totalSpent >= 10000000 && $loyaltyScore >= 500) {
            return 'vip';
        } elseif ($totalSpent >= 5000000 && $loyaltyScore >= 300) {
            return 'premium';
        } elseif ($totalSpent >= 1000000 && $loyaltyScore >= 100) {
            return 'gold';
        } elseif ($totalOrders >= 3 && $loyaltyScore >= 50) {
            return 'regular';
        } else {
            return 'new';
        }
    }
    
    // ========================================
    // CUSTOMER PROFILE MANAGEMENT
    // ========================================
    
    private function createCustomerProfile($customerId, $profileData) {
        $profileData['customer_id'] = $customerId;
        $profileData['created_at'] = date('Y-m-d H:i:s');
        
        return $this->db->insert('customer_profiles', $profileData);
    }
    
    private function updateCustomerProfile($customerId, $profileData) {
        $exists = $this->db->exists('customer_profiles', 'customer_id = :customer_id', ['customer_id' => $customerId]);
        
        if ($exists) {
            $profileData['updated_at'] = date('Y-m-d H:i:s');
            return $this->db->update(
                'customer_profiles',
                $profileData,
                'customer_id = :customer_id',
                ['customer_id' => $customerId]
            );
        } else {
            return $this->createCustomerProfile($customerId, $profileData);
        }
    }
    
    // ========================================
    // UTILITY METHODS
    // ========================================
    
    private function generateCustomerCode() {
        $prefix = 'CUS';
        $date = date('ym');
        
        // Get the last customer code for this month
        $lastCode = $this->db->selectOne(
            "SELECT customer_code FROM {$this->table} 
             WHERE customer_code LIKE :pattern 
             ORDER BY id DESC LIMIT 1",
            ['pattern' => $prefix . $date . '%']
        );
        
        if ($lastCode) {
            $lastSequence = intval(substr($lastCode['customer_code'], -4));
            $sequence = str_pad($lastSequence + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $sequence = '0001';
        }
        
        return $prefix . $date . $sequence;
    }
    
    public function emailExists($email, $excludeId = null) {
        $sql = "SELECT id FROM {$this->table} WHERE email = :email AND status != 'deleted'";
        $params = ['email' => $email];
        
        if ($excludeId) {
            $sql .= " AND id != :exclude_id";
            $params['exclude_id'] = $excludeId;
        }
        
        $result = $this->db->selectOne($sql, $params);
        return $result !== false;
    }
    
    private function logActivity($customerId, $action, $description) {
        $this->db->insert('customer_activities', [
            'customer_id' => $customerId,
            'action' => $action,
            'description' => $description,
            'performed_by' => $_SESSION['user_id'] ?? null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    public function search($query, $limit = 10) {
        return $this->db->select(
            "SELECT id, customer_code, name, email, phone
             FROM {$this->table}
             WHERE status = 'active'
             AND (name LIKE :query OR email LIKE :query OR phone LIKE :query OR customer_code LIKE :query)
             ORDER BY name ASC
             LIMIT {$limit}",
            ['query' => "%{$query}%"]
        );
    }
    
    public function getGlobalStats() {
        $stats = [];
        
        // Total customers by status
        $statusStats = $this->db->select(
            "SELECT status, COUNT(*) as count FROM {$this->table} 
             WHERE status != 'deleted' GROUP BY status"
        );
        
        foreach ($statusStats as $stat) {
            $stats['by_status'][$stat['status']] = $stat['count'];
        }
        
        // New customers this month
        $stats['new_this_month'] = $this->db->count(
            $this->table,
            "status = 'active' AND DATE_FORMAT(created_at, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')"
        );
        
        // Total active customers
        $stats['total_active'] = $this->db->count($this->table, "status = 'active'");
        
        // Customer segments
        $allActiveCustomers = $this->getAll(['status' => 'active']);
        $segments = [];
        
        foreach ($allActiveCustomers as $customer) {
            $segment = $this->getCustomerSegment($customer['id']);
            $segments[$segment] = ($segments[$segment] ?? 0) + 1;
        }
        
        $stats['by_segment'] = $segments;
        
        return $stats;
    }
}
