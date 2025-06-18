<?php
/**
 * BENGKEL MANAGEMENT PRO - VEHICLE CLASS
 * Version: 3.1.0
 * Complete Vehicle Management System
 */

class Vehicle {
    private $db;
    private $table = 'vehicles';
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    // ========================================
    // VEHICLE CRUD OPERATIONS
    // ========================================
    
    public function create($data) {
        $this->db->beginTransaction();
        
        try {
            // Validate required fields
            $requiredFields = ['customer_id', 'license_plate', 'brand', 'model', 'year'];
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    throw new Exception("Field {$field} is required");
                }
            }
            
            // Validate license plate uniqueness
            if ($this->licensePlateExists($data['license_plate'])) {
                throw new Exception('License plate already exists');
            }
            
            // Validate year
            $currentYear = date('Y');
            if ($data['year'] < 1900 || $data['year'] > $currentYear + 1) {
                throw new Exception('Invalid vehicle year');
            }
            
            // Set default values
            $data['status'] = $data['status'] ?? 'active';
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['created_by'] = $_SESSION['user_id'] ?? null;
            
            // Normalize license plate
            $data['license_plate'] = strtoupper(str_replace(' ', '', $data['license_plate']));
            
            // Insert vehicle
            $vehicleId = $this->db->insert($this->table, $data);
            
            // Create vehicle profile if additional data provided
            if (isset($data['additional_info'])) {
                $this->createVehicleProfile($vehicleId, $data['additional_info']);
            }
            
            // Log activity
            $this->logActivity($vehicleId, 'created', 'Vehicle registered');
            
            $this->db->commit();
            
            return $vehicleId;
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    public function update($id, $data) {
        $this->db->beginTransaction();
        
        try {
            $vehicle = $this->findById($id);
            if (!$vehicle) {
                throw new Exception('Vehicle not found');
            }
            
            // Validate license plate if being updated
            if (isset($data['license_plate'])) {
                $data['license_plate'] = strtoupper(str_replace(' ', '', $data['license_plate']));
                if ($this->licensePlateExists($data['license_plate'], $id)) {
                    throw new Exception('License plate already exists');
                }
            }
            
            // Validate year if being updated
            if (isset($data['year'])) {
                $currentYear = date('Y');
                if ($data['year'] < 1900 || $data['year'] > $currentYear + 1) {
                    throw new Exception('Invalid vehicle year');
                }
            }
            
            $data['updated_at'] = date('Y-m-d H:i:s');
            $data['updated_by'] = $_SESSION['user_id'] ?? null;
            
            // Update vehicle
            $updated = $this->db->update(
                $this->table,
                $data,
                'id = :id',
                ['id' => $id]
            );
            
            // Update vehicle profile if additional data provided
            if (isset($data['additional_info'])) {
                $this->updateVehicleProfile($id, $data['additional_info']);
            }
            
            if ($updated) {
                $this->logActivity($id, 'updated', 'Vehicle information updated');
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
            $vehicle = $this->findById($id);
            if (!$vehicle) {
                throw new Exception('Vehicle not found');
            }
            
            // Check if vehicle has active work orders
            $activeWorkOrders = $this->getActiveWorkOrders($id);
            if (!empty($activeWorkOrders)) {
                throw new Exception('Cannot delete vehicle with active work orders');
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
                $this->logActivity($id, 'deleted', 'Vehicle deleted');
            }
            
            return $deleted > 0;
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    public function findById($id) {
        return $this->db->selectOne(
            "SELECT v.*, vp.*, c.name as customer_name, c.email as customer_email,
                    COUNT(DISTINCT wo.id) as total_services,
                    COALESCE(SUM(CASE WHEN wo.status = 'completed' THEN wo.total_amount ELSE 0 END), 0) as total_service_cost,
                    MAX(wo.created_at) as last_service_date
             FROM {$this->table} v
             LEFT JOIN vehicle_profiles vp ON v.id = vp.vehicle_id
             LEFT JOIN customers c ON v.customer_id = c.id
             LEFT JOIN work_orders wo ON v.id = wo.vehicle_id AND wo.deleted_at IS NULL
             WHERE v.id = :id AND v.status != 'deleted'
             GROUP BY v.id",
            ['id' => $id]
        );
    }
    
    public function findByLicensePlate($licensePlate) {
        $licensePlate = strtoupper(str_replace(' ', '', $licensePlate));
        return $this->db->selectOne(
            "SELECT v.*, c.name as customer_name, c.email as customer_email
             FROM {$this->table} v
             LEFT JOIN customers c ON v.customer_id = c.id
             WHERE v.license_plate = :license_plate AND v.status != 'deleted'",
            ['license_plate' => $licensePlate]
        );
    }
    
    public function getAll($filters = []) {
        $where = ["v.status != 'deleted'"];
        $params = [];
        
        if (!empty($filters['status'])) {
            $where[] = "v.status = :status";
            $params['status'] = $filters['status'];
        }
        
        if (!empty($filters['customer_id'])) {
            $where[] = "v.customer_id = :customer_id";
            $params['customer_id'] = $filters['customer_id'];
        }
        
        if (!empty($filters['brand'])) {
            $where[] = "v.brand = :brand";
            $params['brand'] = $filters['brand'];
        }
        
        if (!empty($filters['year_from'])) {
            $where[] = "v.year >= :year_from";
            $params['year_from'] = $filters['year_from'];
        }
        
        if (!empty($filters['year_to'])) {
            $where[] = "v.year <= :year_to";
            $params['year_to'] = $filters['year_to'];
        }
        
        if (!empty($filters['search'])) {
            $where[] = "(v.license_plate LIKE :search OR v.brand LIKE :search OR v.model LIKE :search OR c.name LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }
        
        $whereClause = implode(' AND ', $where);
        $orderBy = $filters['order_by'] ?? 'v.created_at DESC';
        
        $sql = "SELECT v.*, c.name as customer_name,
                       COUNT(DISTINCT wo.id) as total_services,
                       MAX(wo.created_at) as last_service_date
                FROM {$this->table} v
                LEFT JOIN customers c ON v.customer_id = c.id
                LEFT JOIN work_orders wo ON v.id = wo.vehicle_id AND wo.deleted_at IS NULL
                WHERE {$whereClause}
                GROUP BY v.id
                ORDER BY {$orderBy}";
        
        return $this->db->select($sql, $params);
    }
    
    public function paginate($page = 1, $perPage = 20, $filters = []) {
        $where = ["v.status != 'deleted'"];
        $params = [];
        
        // Apply same filters as getAll method
        if (!empty($filters['status'])) {
            $where[] = "v.status = :status";
            $params['status'] = $filters['status'];
        }
        
        if (!empty($filters['search'])) {
            $where[] = "(v.license_plate LIKE :search OR v.brand LIKE :search OR v.model LIKE :search OR c.name LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }
        
        $whereClause = implode(' AND ', $where);
        $orderBy = $filters['order_by'] ?? 'v.created_at DESC';
        
        $sql = "SELECT v.*, c.name as customer_name,
                       COUNT(DISTINCT wo.id) as total_services,
                       MAX(wo.created_at) as last_service_date
                FROM {$this->table} v
                LEFT JOIN customers c ON v.customer_id = c.id
                LEFT JOIN work_orders wo ON v.id = wo.vehicle_id AND wo.deleted_at IS NULL
                WHERE {$whereClause}
                GROUP BY v.id
                ORDER BY {$orderBy}";
        
        return $this->db->paginate($sql, $params, $page, $perPage);
    }
    
    // ========================================
    // VEHICLE RELATIONSHIPS
    // ========================================
    
    public function getWorkOrders($vehicleId, $limit = null) {
        $sql = "SELECT wo.*, c.name as customer_name
                FROM work_orders wo
                LEFT JOIN customers c ON wo.customer_id = c.id
                WHERE wo.vehicle_id = :vehicle_id AND wo.deleted_at IS NULL
                ORDER BY wo.created_at DESC";
        
        if ($limit) {
            $sql .= " LIMIT {$limit}";
        }
        
        return $this->db->select($sql, ['vehicle_id' => $vehicleId]);
    }
    
    public function getActiveWorkOrders($vehicleId) {
        return $this->db->select(
            "SELECT wo.*, c.name as customer_name
             FROM work_orders wo
             LEFT JOIN customers c ON wo.customer_id = c.id
             WHERE wo.vehicle_id = :vehicle_id 
             AND wo.status IN ('pending', 'confirmed', 'in_progress', 'waiting_parts')
             AND wo.deleted_at IS NULL
             ORDER BY wo.created_at DESC",
            ['vehicle_id' => $vehicleId]
        );
    }
    
    public function getServiceHistory($vehicleId) {
        return $this->db->select(
            "SELECT wo.*, c.name as customer_name,
                    GROUP_CONCAT(s.name SEPARATOR ', ') as services,
                    wo.total_amount
             FROM work_orders wo
             LEFT JOIN customers c ON wo.customer_id = c.id
             LEFT JOIN work_order_services wos ON wo.id = wos.work_order_id
             LEFT JOIN services s ON wos.service_id = s.id
             WHERE wo.vehicle_id = :vehicle_id 
             AND wo.status = 'completed'
             AND wo.deleted_at IS NULL
             GROUP BY wo.id
             ORDER BY wo.completed_at DESC",
            ['vehicle_id' => $vehicleId]
        );
    }
    
    public function getMaintenanceSchedule($vehicleId) {
        $vehicle = $this->findById($vehicleId);
        if (!$vehicle) return [];
        
        // Get last service records
        $lastServices = $this->db->select(
            "SELECT s.name, s.maintenance_interval_km, s.maintenance_interval_months,
                    wo.completed_at, vp.current_mileage
             FROM work_order_services wos
             JOIN services s ON wos.service_id = s.id
             JOIN work_orders wo ON wos.work_order_id = wo.id
             LEFT JOIN vehicle_profiles vp ON wo.vehicle_id = vp.vehicle_id
             WHERE wo.vehicle_id = :vehicle_id 
             AND wo.status = 'completed'
             AND (s.maintenance_interval_km > 0 OR s.maintenance_interval_months > 0)
             ORDER BY wo.completed_at DESC",
            ['vehicle_id' => $vehicleId]
        );
        
        $schedule = [];
        $currentMileage = $vehicle['current_mileage'] ?? 0;
        
        foreach ($lastServices as $service) {
            $nextDueKm = null;
            $nextDueDate = null;
            
            if ($service['maintenance_interval_km'] > 0) {
                $nextDueKm = $currentMileage + $service['maintenance_interval_km'];
            }
            
            if ($service['maintenance_interval_months'] > 0) {
                $nextDueDate = date('Y-m-d', strtotime($service['completed_at'] . ' +' . $service['maintenance_interval_months'] . ' months'));
            }
            
            $schedule[] = [
                'service_name' => $service['name'],
                'last_service_date' => $service['completed_at'],
                'next_due_km' => $nextDueKm,
                'next_due_date' => $nextDueDate,
                'is_overdue' => $this->isMaintenanceOverdue($nextDueKm, $nextDueDate, $currentMileage)
            ];
        }
        
        return $schedule;
    }
    
    private function isMaintenanceOverdue($nextDueKm, $nextDueDate, $currentMileage) {
        $overdueByKm = $nextDueKm && $currentMileage >= $nextDueKm;
        $overdueByDate = $nextDueDate && date('Y-m-d') >= $nextDueDate;
        
        return $overdueByKm || $overdueByDate;
    }
    
    // ========================================
    // VEHICLE ANALYTICS
    // ========================================
    
    public function getVehicleStats($vehicleId) {
        $stats = [];
        
        // Basic stats
        $basicStats = $this->db->selectOne(
            "SELECT 
                COUNT(wo.id) as total_services,
                COUNT(CASE WHEN wo.status = 'completed' THEN 1 END) as completed_services,
                COALESCE(SUM(CASE WHEN wo.status = 'completed' THEN wo.total_amount ELSE 0 END), 0) as total_spent,
                COALESCE(AVG(CASE WHEN wo.status = 'completed' THEN wo.total_amount ELSE NULL END), 0) as avg_service_cost,
                MIN(wo.created_at) as first_service_date,
                MAX(wo.created_at) as last_service_date
             FROM work_orders wo
             WHERE wo.vehicle_id = :vehicle_id AND wo.deleted_at IS NULL",
            ['vehicle_id' => $vehicleId]
        );
        
        $stats = array_merge($stats, $basicStats);
        
        // Monthly service costs
        $monthlyStats = $this->db->select(
            "SELECT 
                DATE_FORMAT(wo.created_at, '%Y-%m') as month,
                COUNT(*) as service_count,
                COALESCE(SUM(wo.total_amount), 0) as total_cost
             FROM work_orders wo
             WHERE wo.vehicle_id = :vehicle_id 
             AND wo.status = 'completed'
             AND wo.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
             AND wo.deleted_at IS NULL
             GROUP BY DATE_FORMAT(wo.created_at, '%Y-%m')
             ORDER BY month ASC",
            ['vehicle_id' => $vehicleId]
        );
        
        $stats['monthly_stats'] = $monthlyStats;
        
        // Most frequent services
        $topServices = $this->db->select(
            "SELECT s.name, COUNT(*) as frequency, SUM(wos.total_price) as total_cost
             FROM work_order_services wos
             JOIN services s ON wos.service_id = s.id
             JOIN work_orders wo ON wos.work_order_id = wo.id
             WHERE wo.vehicle_id = :vehicle_id AND wo.status = 'completed'
             GROUP BY s.id, s.name
             ORDER BY frequency DESC
             LIMIT 10",
            ['vehicle_id' => $vehicleId]
        );
        
        $stats['top_services'] = $topServices;
        
        return $stats;
    }
    
    // ========================================
    // VEHICLE PROFILE MANAGEMENT
    // ========================================
    
    private function createVehicleProfile($vehicleId, $profileData) {
        $profileData['vehicle_id'] = $vehicleId;
        $profileData['created_at'] = date('Y-m-d H:i:s');
        
        return $this->db->insert('vehicle_profiles', $profileData);
    }
    
    private function updateVehicleProfile($vehicleId, $profileData) {
        $exists = $this->db->exists('vehicle_profiles', 'vehicle_id = :vehicle_id', ['vehicle_id' => $vehicleId]);
        
        if ($exists) {
            $profileData['updated_at'] = date('Y-m-d H:i:s');
            return $this->db->update(
                'vehicle_profiles',
                $profileData,
                'vehicle_id = :vehicle_id',
                ['vehicle_id' => $vehicleId]
            );
        } else {
            return $this->createVehicleProfile($vehicleId, $profileData);
        }
    }
    
    public function updateMileage($vehicleId, $newMileage, $recordedBy = null) {
        try {
            $vehicle = $this->findById($vehicleId);
            if (!$vehicle) {
                throw new Exception('Vehicle not found');
            }
            
            $currentMileage = $vehicle['current_mileage'] ?? 0;
            
            if ($newMileage < $currentMileage) {
                throw new Exception('New mileage cannot be less than current mileage');
            }
            
            // Update vehicle profile
            $this->updateVehicleProfile($vehicleId, [
                'current_mileage' => $newMileage,
                'last_mileage_update' => date('Y-m-d H:i:s')
            ]);
            
            // Log mileage history
            $this->db->insert('vehicle_mileage_history', [
                'vehicle_id' => $vehicleId,
                'previous_mileage' => $currentMileage,
                'new_mileage' => $newMileage,
                'recorded_by' => $recordedBy ?? $_SESSION['user_id'],
                'recorded_at' => date('Y-m-d H:i:s')
            ]);
            
            $this->logActivity($vehicleId, 'mileage_updated', "Mileage updated from {$currentMileage} to {$newMileage}");
            
            return true;
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    // ========================================
    // UTILITY METHODS
    // ========================================
    
    public function licensePlateExists($licensePlate, $excludeId = null) {
        $licensePlate = strtoupper(str_replace(' ', '', $licensePlate));
        
        $sql = "SELECT id FROM {$this->table} WHERE license_plate = :license_plate AND status != 'deleted'";
        $params = ['license_plate' => $licensePlate];
        
        if ($excludeId) {
            $sql .= " AND id != :exclude_id";
            $params['exclude_id'] = $excludeId;
        }
        
        $result = $this->db->selectOne($sql, $params);
        return $result !== false;
    }
    
    private function logActivity($vehicleId, $action, $description) {
        $this->db->insert('vehicle_activities', [
            'vehicle_id' => $vehicleId,
            'action' => $action,
            'description' => $description,
            'performed_by' => $_SESSION['user_id'] ?? null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    public function search($query, $limit = 10) {
        return $this->db->select(
            "SELECT v.id, v.license_plate, v.brand, v.model, v.year, c.name as customer_name
             FROM {$this->table} v
             LEFT JOIN customers c ON v.customer_id = c.id
             WHERE v.status = 'active'
             AND (v.license_plate LIKE :query OR v.brand LIKE :query OR v.model LIKE :query OR c.name LIKE :query)
             ORDER BY v.license_plate ASC
             LIMIT {$limit}",
            ['query' => "%{$query}%"]
        );
    }
    
    public function getBrands() {
        return $this->db->select(
            "SELECT DISTINCT brand FROM {$this->table} 
             WHERE status = 'active' AND brand IS NOT NULL 
             ORDER BY brand ASC"
        );
    }
    
    public function getModels($brand = null) {
        $sql = "SELECT DISTINCT model FROM {$this->table} 
                WHERE status = 'active' AND model IS NOT NULL";
        $params = [];
        
        if ($brand) {
            $sql .= " AND brand = :brand";
            $params['brand'] = $brand;
        }
        
        $sql .= " ORDER BY model ASC";
        
        return $this->db->select($sql, $params);
    }
    
    public function getYearRange() {
        $result = $this->db->selectOne(
            "SELECT MIN(year) as min_year, MAX(year) as max_year 
             FROM {$this->table} 
             WHERE status = 'active'"
        );
        
        return [
            'min_year' => $result['min_year'] ?? date('Y'),
            'max_year' => $result['max_year'] ?? date('Y')
        ];
    }
    
    public function getGlobalStats() {
        $stats = [];
        
        // Total vehicles by status
        $statusStats = $this->db->select(
            "SELECT status, COUNT(*) as count FROM {$this->table} 
             WHERE status != 'deleted' GROUP BY status"
        );
        
        foreach ($statusStats as $stat) {
            $stats['by_status'][$stat['status']] = $stat['count'];
        }
        
        // Vehicles by brand
        $brandStats = $this->db->select(
            "SELECT brand, COUNT(*) as count FROM {$this->table} 
             WHERE status = 'active' GROUP BY brand ORDER BY count DESC LIMIT 10"
        );
        
        $stats['by_brand'] = $brandStats;
        
        // Vehicles by year
        $yearStats = $this->db->select(
            "SELECT year, COUNT(*) as count FROM {$this->table} 
             WHERE status = 'active' GROUP BY year ORDER BY year DESC"
        );
        
        $stats['by_year'] = $yearStats;
        
        // New vehicles this month
        $stats['new_this_month'] = $this->db->count(
            $this->table,
            "status = 'active' AND DATE_FORMAT(created_at, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')"
        );
        
        // Total active vehicles
        $stats['total_active'] = $this->db->count($this->table, "status = 'active'");
        
        return $stats;
    }
    
    public function getVehiclesDueForMaintenance() {
        return $this->db->select(
            "SELECT v.*, c.name as customer_name, c.phone as customer_phone,
                    vp.current_mileage, vp.last_service_date
             FROM {$this->table} v
             LEFT JOIN customers c ON v.customer_id = c.id
             LEFT JOIN vehicle_profiles vp ON v.id = vp.vehicle_id
             WHERE v.status = 'active'
             AND (
                 (vp.last_service_date IS NULL) OR
                 (vp.last_service_date < DATE_SUB(NOW(), INTERVAL 6 MONTH)) OR
                 (vp.current_mileage - vp.last_service_mileage >= 10000)
             )
             ORDER BY vp.last_service_date ASC"
        );
    }
}
