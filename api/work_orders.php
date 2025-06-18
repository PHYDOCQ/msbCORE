<?php
/**
 * BENGKEL MANAGEMENT PRO - WORK ORDER CLASS
 * Version: 3.1.0
 * Complete Work Order Management System
 */

class WorkOrder {
    private $db;
    private $table = 'work_orders';
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    // ========================================
    // WORK ORDER CRUD OPERATIONS
    // ========================================
    
    public function create($data) {
        $this->db->beginTransaction();
        
        try {
            // Validate required fields
            $requiredFields = ['customer_id', 'vehicle_id', 'description'];
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    throw new Exception("Field {$field} is required");
                }
            }
            
            // Generate work order number
            $data['work_order_number'] = $this->generateWorkOrderNumber();
            
            // Set default values
            $data['status'] = 'pending';
            $data['priority'] = $data['priority'] ?? 'normal';
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['created_by'] = $_SESSION['user_id'] ?? null;
            
            // Calculate estimated completion date
            if (empty($data['estimated_completion'])) {
                $data['estimated_completion'] = $this->calculateEstimatedCompletion($data['priority']);
            }
            
            // Insert work order
            $workOrderId = $this->db->insert($this->table, $data);
            
            // Add services if provided
            if (!empty($data['services'])) {
                $this->addServices($workOrderId, $data['services']);
            }
            
            // Add initial status history
            $this->addStatusHistory($workOrderId, 'pending', 'Work order created', $_SESSION['user_id'] ?? null);
            
            // Create notification
            $this->createNotification($workOrderId, 'created');
            
            $this->db->commit();
            
            return $workOrderId;
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    public function update($id, $data) {
        $this->db->beginTransaction();
        
        try {
            $currentWorkOrder = $this->findById($id);
            if (!$currentWorkOrder) {
                throw new Exception('Work order not found');
            }
            
            $data['updated_at'] = date('Y-m-d H:i:s');
            $data['updated_by'] = $_SESSION['user_id'] ?? null;
            
            // Check if status is being changed
            if (isset($data['status']) && $data['status'] !== $currentWorkOrder['status']) {
                $this->updateStatus($id, $data['status'], $data['status_notes'] ?? null);
                unset($data['status'], $data['status_notes']);
            }
            
            // Update work order
            $updated = $this->db->update(
                $this->table,
                $data,
                'id = :id',
                ['id' => $id]
            );
            
            // Update services if provided
            if (isset($data['services'])) {
                $this->updateServices($id, $data['services']);
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
            $workOrder = $this->findById($id);
            if (!$workOrder) {
                throw new Exception('Work order not found');
            }
            
            // Check if work order can be deleted
            if (in_array($workOrder['status'], ['in_progress', 'completed'])) {
                throw new Exception('Cannot delete work order that is in progress or completed');
            }
            
            // Soft delete
            $deleted = $this->db->update(
                $this->table,
                [
                    'status' => 'cancelled',
                    'deleted_at' => date('Y-m-d H:i:s'),
                    'deleted_by' => $_SESSION['user_id'] ?? null
                ],
                'id = :id',
                ['id' => $id]
            );
            
            if ($deleted) {
                $this->addStatusHistory($id, 'cancelled', 'Work order deleted', $_SESSION['user_id'] ?? null);
            }
            
            return $deleted > 0;
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    public function findById($id) {
        return $this->db->selectOne(
            "SELECT wo.*, 
                    c.name as customer_name, c.email as customer_email, c.phone as customer_phone,
                    v.license_plate, v.brand, v.model, v.year,
                    u1.name as created_by_name,
                    u2.name as assigned_to_name
             FROM {$this->table} wo
             LEFT JOIN customers c ON wo.customer_id = c.id
             LEFT JOIN vehicles v ON wo.vehicle_id = v.id
             LEFT JOIN users u1 ON wo.created_by = u1.id
             LEFT JOIN users u2 ON wo.assigned_to = u2.id
             WHERE wo.id = :id",
            ['id' => $id]
        );
    }
    
    public function getAll($filters = []) {
        $where = ["wo.deleted_at IS NULL"];
        $params = [];
        
        if (!empty($filters['status'])) {
            if (is_array($filters['status'])) {
                $placeholders = [];
                foreach ($filters['status'] as $index => $status) {
                    $placeholders[] = ":status_{$index}";
                    $params["status_{$index}"] = $status;
                }
                $where[] = "wo.status IN (" . implode(',', $placeholders) . ")";
            } else {
                $where[] = "wo.status = :status";
                $params['status'] = $filters['status'];
            }
        }
        
        if (!empty($filters['priority'])) {
            $where[] = "wo.priority = :priority";
            $params['priority'] = $filters['priority'];
        }
        
        if (!empty($filters['assigned_to'])) {
            $where[] = "wo.assigned_to = :assigned_to";
            $params['assigned_to'] = $filters['assigned_to'];
        }
        
        if (!empty($filters['customer_id'])) {
            $where[] = "wo.customer_id = :customer_id";
            $params['customer_id'] = $filters['customer_id'];
        }
        
        if (!empty($filters['date_from'])) {
            $where[] = "DATE(wo.created_at) >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where[] = "DATE(wo.created_at) <= :date_to";
            $params['date_to'] = $filters['date_to'];
        }
        
        if (!empty($filters['search'])) {
            $where[] = "(wo.work_order_number LIKE :search OR c.name LIKE :search OR v.license_plate LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }
        
        $whereClause = implode(' AND ', $where);
        $orderBy = $filters['order_by'] ?? 'wo.created_at DESC';
        
        $sql = "SELECT wo.*, 
                       c.name as customer_name,
                       v.license_plate, v.brand, v.model,
                       u.name as assigned_to_name
                FROM {$this->table} wo
                LEFT JOIN customers c ON wo.customer_id = c.id
                LEFT JOIN vehicles v ON wo.vehicle_id = v.id
                LEFT JOIN users u ON wo.assigned_to = u.id
                WHERE {$whereClause}
                ORDER BY {$orderBy}";
        
        return $this->db->select($sql, $params);
    }
    
    public function paginate($page = 1, $perPage = 20, $filters = []) {
        $where = ["wo.deleted_at IS NULL"];
        $params = [];
        
        // Apply same filters as getAll method
        if (!empty($filters['status'])) {
            if (is_array($filters['status'])) {
                $placeholders = [];
                foreach ($filters['status'] as $index => $status) {
                    $placeholders[] = ":status_{$index}";
                    $params["status_{$index}"] = $status;
                }
                $where[] = "wo.status IN (" . implode(',', $placeholders) . ")";
            } else {
                $where[] = "wo.status = :status";
                $params['status'] = $filters['status'];
            }
        }
        
        if (!empty($filters['search'])) {
            $where[] = "(wo.work_order_number LIKE :search OR c.name LIKE :search OR v.license_plate LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }
        
        $whereClause = implode(' AND ', $where);
        $orderBy = $filters['order_by'] ?? 'wo.created_at DESC';
        
        $sql = "SELECT wo.*, 
                       c.name as customer_name,
                       v.license_plate, v.brand, v.model,
                       u.name as assigned_to_name
                FROM {$this->table} wo
                LEFT JOIN customers c ON wo.customer_id = c.id
                LEFT JOIN vehicles v ON wo.vehicle_id = v.id
                LEFT JOIN users u ON wo.assigned_to = u.id
                WHERE {$whereClause}
                ORDER BY {$orderBy}";
        
        return $this->db->paginate($sql, $params, $page, $perPage);
    }
    
    // ========================================
    // STATUS MANAGEMENT
    // ========================================
    
    public function updateStatus($workOrderId, $newStatus, $notes = null) {
        $this->db->beginTransaction();
        
        try {
            $workOrder = $this->findById($workOrderId);
            if (!$workOrder) {
                throw new Exception('Work order not found');
            }
            
            $validStatuses = ['pending', 'confirmed', 'in_progress', 'waiting_parts', 'completed', 'cancelled'];
            if (!in_array($newStatus, $validStatuses)) {
                throw new Exception('Invalid status');
            }
            
            // Validate status transition
            $this->validateStatusTransition($workOrder['status'], $newStatus);
            
            $updateData = [
                'status' => $newStatus,
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => $_SESSION['user_id'] ?? null
            ];
            
            // Set completion date if completed
            if ($newStatus === 'completed') {
                $updateData['completed_at'] = date('Y-m-d H:i:s');
                $updateData['completed_by'] = $_SESSION['user_id'] ?? null;
            }
            
            // Set start date if starting work
            if ($newStatus === 'in_progress' && empty($workOrder['started_at'])) {
                $updateData['started_at'] = date('Y-m-d H:i:s');
                $updateData['started_by'] = $_SESSION['user_id'] ?? null;
            }
            
            // Update work order
            $this->db->update(
                $this->table,
                $updateData,
                'id = :id',
                ['id' => $workOrderId]
            );
            
            // Add to status history
            $this->addStatusHistory($workOrderId, $newStatus, $notes, $_SESSION['user_id'] ?? null);
            
            // Create notification
            $this->createNotification($workOrderId, 'status_changed', [
                'old_status' => $workOrder['status'],
                'new_status' => $newStatus
            ]);
            
            $this->db->commit();
            
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    private function validateStatusTransition($currentStatus, $newStatus) {
        $allowedTransitions = [
            'pending' => ['confirmed', 'cancelled'],
            'confirmed' => ['in_progress', 'cancelled'],
            'in_progress' => ['waiting_parts', 'completed', 'cancelled'],
            'waiting_parts' => ['in_progress', 'cancelled'],
            'completed' => [], // No transitions allowed from completed
            'cancelled' => [] // No transitions allowed from cancelled
        ];
        
        if (!isset($allowedTransitions[$currentStatus])) {
            throw new Exception("Invalid current status: {$currentStatus}");
        }
        
        if (!in_array($newStatus, $allowedTransitions[$currentStatus])) {
            throw new Exception("Cannot change status from {$currentStatus} to {$newStatus}");
        }
    }
    
    public function getStatusHistory($workOrderId) {
        return $this->db->select(
            "SELECT wsh.*, u.name as changed_by_name
             FROM work_order_status_history wsh
             LEFT JOIN users u ON wsh.changed_by = u.id
             WHERE wsh.work_order_id = :work_order_id
             ORDER BY wsh.changed_at ASC",
            ['work_order_id' => $workOrderId]
        );
    }
    
    private function addStatusHistory($workOrderId, $status, $notes = null, $changedBy = null) {
        return $this->db->insert('work_order_status_history', [
            'work_order_id' => $workOrderId,
            'status' => $status,
            'notes' => $notes,
            'changed_by' => $changedBy,
            'changed_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    // ========================================
    // SERVICE MANAGEMENT
    // ========================================
    
    public function addServices($workOrderId, $services) {
        foreach ($services as $service) {
            $this->db->insert('work_order_services', [
                'work_order_id' => $workOrderId,
                'service_id' => $service['service_id'],
                'quantity' => $service['quantity'] ?? 1,
                'unit_price' => $service['unit_price'],
                'total_price' => $service['total_price'] ?? ($service['unit_price'] * ($service['quantity'] ?? 1)),
                'notes' => $service['notes'] ?? null,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
        
        // Update work order total
        $this->updateWorkOrderTotal($workOrderId);
    }
    
    public function updateServices($workOrderId, $services) {
        $this->db->beginTransaction();
        
        try {
            // Remove existing services
            $this->db->delete('work_order_services', 'work_order_id = :work_order_id', ['work_order_id' => $workOrderId]);
            
            // Add new services
            if (!empty($services)) {
                $this->addServices($workOrderId, $services);
            }
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    public function getServices($workOrderId) {
        return $this->db->select(
            "SELECT wos.*, s.name as service_name, s.description as service_description
             FROM work_order_services wos
             LEFT JOIN services s ON wos.service_id = s.id
             WHERE wos.work_order_id = :work_order_id
             ORDER BY wos.created_at ASC",
            ['work_order_id' => $workOrderId]
        );
    }
    
    // ========================================
    // INVENTORY MANAGEMENT
    // ========================================
    
    public function addParts($workOrderId, $parts) {
        foreach ($parts as $part) {
            $this->db->insert('work_order_parts', [
                'work_order_id' => $workOrderId,
                'inventory_id' => $part['inventory_id'],
                'quantity' => $part['quantity'],
                'unit_price' => $part['unit_price'],
                'total_price' => $part['total_price'] ?? ($part['unit_price'] * $part['quantity']),
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // Update inventory stock
            $inventory = new Inventory();
            $inventory->updateStock($part['inventory_id'], -$part['quantity'], 'used_in_work_order', $workOrderId);
        }
        
        // Update work order total
        $this->updateWorkOrderTotal($workOrderId);
    }
    
    public function getParts($workOrderId) {
        return $this->db->select(
            "SELECT wop.*, i.name as part_name, i.part_number, i.current_stock
             FROM work_order_parts wop
             LEFT JOIN inventory i ON wop.inventory_id = i.id
             WHERE wop.work_order_id = :work_order_id
             ORDER BY wop.created_at ASC",
            ['work_order_id' => $workOrderId]
        );
    }
    
    // ========================================
    // ASSIGNMENT MANAGEMENT
    // ========================================
    
    public function assignTechnician($workOrderId, $technicianId, $notes = null) {
        try {
            // Verify technician exists and has correct role
            $user = new User();
            $technician = $user->findById($technicianId);
            
            if (!$technician || !in_array($technician['role'], ['technician', 'admin', 'manager'])) {
                throw new Exception('Invalid technician');
            }
            
            $updated = $this->db->update(
                $this->table,
                [
                    'assigned_to' => $technicianId,
                    'assigned_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ],
                'id = :id',
                ['id' => $workOrderId]
            );
            
            if ($updated) {
                // Add to assignment history
                $this->addAssignmentHistory($workOrderId, $technicianId, $notes);
                
                // Create notification
                $this->createNotification($workOrderId, 'assigned', ['technician_id' => $technicianId]);
            }
            
            return $updated > 0;
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    public function unassignTechnician($workOrderId, $notes = null) {
        try {
            $workOrder = $this->findById($workOrderId);
            if (!$workOrder) {
                throw new Exception('Work order not found');
            }
            
            if (empty($workOrder['assigned_to'])) {
                throw new Exception('Work order is not assigned to any technician');
            }
            
            $updated = $this->db->update(
                $this->table,
                [
                    'assigned_to' => null,
                    'updated_at' => date('Y-m-d H:i:s')
                ],
                'id = :id',
                ['id' => $workOrderId]
            );
            
            if ($updated) {
                // Add to assignment history
                $this->addAssignmentHistory($workOrderId, null, $notes, 'unassigned');
            }
            
            return $updated > 0;
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    private function addAssignmentHistory($workOrderId, $technicianId, $notes = null, $action = 'assigned') {
        return $this->db->insert('work_order_assignments', [
            'work_order_id' => $workOrderId,
            'technician_id' => $technicianId,
            'action' => $action,
            'notes' => $notes,
            'assigned_by' => $_SESSION['user_id'] ?? null,
            'assigned_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    // ========================================
    // FINANCIAL CALCULATIONS
    // ========================================
    
    private function updateWorkOrderTotal($workOrderId) {
        // Calculate services total
        $servicesTotal = $this->db->selectOne(
            "SELECT COALESCE(SUM(total_price), 0) as total FROM work_order_services WHERE work_order_id = :work_order_id",
            ['work_order_id' => $workOrderId]
        )['total'] ?? 0;
        
        // Calculate parts total
        $partsTotal = $this->db->selectOne(
            "SELECT COALESCE(SUM(total_price), 0) as total FROM work_order_parts WHERE work_order_id = :work_order_id",
            ['work_order_id' => $workOrderId]
        )['total'] ?? 0;
        
        $subtotal = $servicesTotal + $partsTotal;
        
        // Get tax rate from settings (default 10%)
        $taxRate = 0.10; // This could be from settings
        $tax = $subtotal * $taxRate;
        $total = $subtotal + $tax;
        
        // Update work order
        $this->db->update(
            $this->table,
            [
                'subtotal' => $subtotal,
                'tax_amount' => $tax,
                'total_amount' => $total,
                'updated_at' => date('Y-m-d H:i:s')
            ],
            'id = :id',
            ['id' => $workOrderId]
        );
        
        return $total;
    }
    
    public function calculateTotal($workOrderId) {
        return $this->updateWorkOrderTotal($workOrderId);
    }
    
    // ========================================
    // UTILITY METHODS
    // ========================================
    
    private function generateWorkOrderNumber() {
        $prefix = 'WO';
        $date = date('Ymd');
        
        // Get the last work order number for today
        $lastNumber = $this->db->selectOne(
            "SELECT work_order_number FROM {$this->table} 
             WHERE work_order_number LIKE :pattern 
             ORDER BY id DESC LIMIT 1",
            ['pattern' => $prefix . $date . '%']
        );
        
        if ($lastNumber) {
            $lastSequence = intval(substr($lastNumber['work_order_number'], -4));
            $sequence = str_pad($lastSequence + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $sequence = '0001';
        }
        
        return $prefix . $date . $sequence;
    }
    
    private function calculateEstimatedCompletion($priority) {
        $hoursToAdd = [
            'low' => 72,      // 3 days
            'normal' => 48,   // 2 days
            'high' => 24,     // 1 day
            'urgent' => 12    // 12 hours
        ];
        
        $hours = $hoursToAdd[$priority] ?? 48;
        return date('Y-m-d H:i:s', strtotime("+{$hours} hours"));
    }
    
    private function createNotification($workOrderId, $type, $data = []) {
        $notification = new Notification();
        
        $workOrder = $this->findById($workOrderId);
        if (!$workOrder) return;
        
        $messages = [
            'created' => "New work order #{$workOrder['work_order_number']} created",
            'assigned' => "Work order #{$workOrder['work_order_number']} assigned to you",
            'status_changed' => "Work order #{$workOrder['work_order_number']} status changed to {$data['new_status']}"
        ];
        
        $message = $messages[$type] ?? "Work order #{$workOrder['work_order_number']} updated";
        
        // Notify relevant users based on type
        $userIds = [];
        
        switch ($type) {
            case 'assigned':
                if (!empty($data['technician_id'])) {
                    $userIds[] = $data['technician_id'];
                }
                break;
            case 'status_changed':
                if ($workOrder['assigned_to']) {
                    $userIds[] = $workOrder['assigned_to'];
                }
                break;
        }
        
        foreach ($userIds as $userId) {
            $notification->create([
                'user_id' => $userId,
                'title' => 'Work Order Update',
                'message' => $message,
                'type' => 'work_order',
                'reference_id' => $workOrderId,
                'action_url' => "/work-orders/view.php?id={$workOrderId}"
            ]);
        }
    }
    
    public function getStats($filters = []) {
        $stats = [];
        
        $whereConditions = ["deleted_at IS NULL"];
        $params = [];
        
        // Apply date filter if provided
        if (!empty($filters['date_from'])) {
            $whereConditions[] = "DATE(created_at) >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $whereConditions[] = "DATE(created_at) <= :date_to";
            $params['date_to'] = $filters['date_to'];
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        // Total by status
        $statusStats = $this->db->select(
            "SELECT status, COUNT(*) as count FROM {$this->table} 
             WHERE {$whereClause} GROUP BY status",
            $params
        );
        
        foreach ($statusStats as $stat) {
            $stats['by_status'][$stat['status']] = $stat['count'];
        }
        
        // Total by priority
        $priorityStats = $this->db->select(
            "SELECT priority, COUNT(*) as count FROM {$this->table} 
             WHERE {$whereClause} GROUP BY priority",
            $params
        );
        
        foreach ($priorityStats as $stat) {
            $stats['by_priority'][$stat['priority']] = $stat['count'];
        }
        
        // Revenue stats
        $revenueStats = $this->db->selectOne(
            "SELECT 
                COUNT(*) as total_orders,
                COALESCE(SUM(total_amount), 0) as total_revenue,
                COALESCE(AVG(total_amount), 0) as avg_order_value
             FROM {$this->table} 
             WHERE {$whereClause} AND status = 'completed'",
            $params
        );
        
        $stats['revenue'] = $revenueStats;
        
        return $stats;
    }
    
    public function getOverdueWorkOrders() {
        return $this->db->select(
            "SELECT wo.*, c.name as customer_name, v.license_plate
             FROM {$this->table} wo
             LEFT JOIN customers c ON wo.customer_id = c.id
             LEFT JOIN vehicles v ON wo.vehicle_id = v.id
             WHERE wo.status IN ('pending', 'confirmed', 'in_progress', 'waiting_parts')
             AND wo.estimated_completion < NOW()
             AND wo.deleted_at IS NULL
             ORDER BY wo.estimated_completion ASC"
        );
    }
    
    public function getTechnicianWorkload($technicianId = null) {
        $where = "wo.status IN ('confirmed', 'in_progress', 'waiting_parts') AND wo.deleted_at IS NULL";
        $params = [];
        
        if ($technicianId) {
            $where .= " AND wo.assigned_to = :technician_id";
            $params['technician_id'] = $technicianId;
        }
        
        return $this->db->select(
            "SELECT u.id, u.name, COUNT(wo.id) as active_orders
             FROM users u
             LEFT JOIN {$this->table} wo ON u.id = wo.assigned_to AND {$where}
             WHERE u.role = 'technician' AND u.status = 'active'
             GROUP BY u.id, u.name
             ORDER BY active_orders ASC, u.name ASC",
            $params
        );
    }
}
