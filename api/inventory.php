<?php
/**
 * BENGKEL MANAGEMENT PRO - INVENTORY CLASS
 * Version: 3.1.0
 * Complete Inventory Management System
 */

class Inventory {
    private $db;
    private $table = 'inventory';
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    // ========================================
    // INVENTORY CRUD OPERATIONS
    // ========================================
    
    public function create($data) {
        $this->db->beginTransaction();
        
        try {
            // Validate required fields
            $requiredFields = ['name', 'part_number', 'category_id', 'unit_price', 'minimum_stock'];
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    throw new Exception("Field {$field} is required");
                }
            }
            
            // Check if part number already exists
            if ($this->partNumberExists($data['part_number'])) {
                throw new Exception('Part number already exists');
            }
            
            // Validate numeric fields
            if (!is_numeric($data['unit_price']) || $data['unit_price'] < 0) {
                throw new Exception('Invalid unit price');
            }
            
            if (!is_numeric($data['minimum_stock']) || $data['minimum_stock'] < 0) {
                throw new Exception('Invalid minimum stock');
            }
            
            // Set default values
            $data['current_stock'] = $data['current_stock'] ?? 0;
            $data['status'] = $data['status'] ?? 'active';
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['created_by'] = $_SESSION['user_id'] ?? null;
            
            // Insert inventory item
            $inventoryId = $this->db->insert($this->table, $data);
            
            // Create initial stock entry if stock > 0
            if ($data['current_stock'] > 0) {
                $this->addStockMovement($inventoryId, $data['current_stock'], 'initial_stock', 'Initial stock entry');
            }
            
            // Log activity
            $this->logActivity($inventoryId, 'created', 'Inventory item created');
            
            $this->db->commit();
            
            return $inventoryId;
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    public function update($id, $data) {
        try {
            $item = $this->findById($id);
            if (!$item) {
                throw new Exception('Inventory item not found');
            }
            
            // Validate part number if being updated
            if (isset($data['part_number']) && $this->partNumberExists($data['part_number'], $id)) {
                throw new Exception('Part number already exists');
            }
            
            // Validate numeric fields
            if (isset($data['unit_price']) && (!is_numeric($data['unit_price']) || $data['unit_price'] < 0)) {
                throw new Exception('Invalid unit price');
            }
            
            if (isset($data['minimum_stock']) && (!is_numeric($data['minimum_stock']) || $data['minimum_stock'] < 0)) {
                throw new Exception('Invalid minimum stock');
            }
            
            // Don't allow direct stock updates through this method
            unset($data['current_stock']);
            
            $data['updated_at'] = date('Y-m-d H:i:s');
            $data['updated_by'] = $_SESSION['user_id'] ?? null;
            
            $updated = $this->db->update(
                $this->table,
                $data,
                'id = :id',
                ['id' => $id]
            );
            
            if ($updated) {
                $this->logActivity($id, 'updated', 'Inventory item updated');
            }
            
            return $updated > 0;
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    public function delete($id) {
        try {
            $item = $this->findById($id);
            if (!$item) {
                throw new Exception('Inventory item not found');
            }
            
            // Check if item is used in any work orders
            $usageCount = $this->db->count('work_order_parts', 'inventory_id = :inventory_id', ['inventory_id' => $id]);
            if ($usageCount > 0) {
                throw new Exception('Cannot delete inventory item that has been used in work orders');
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
                $this->logActivity($id, 'deleted', 'Inventory item deleted');
            }
            
            return $deleted > 0;
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    public function findById($id) {
        return $this->db->selectOne(
            "SELECT i.*, ic.name as category_name, s.name as supplier_name
             FROM {$this->table} i
             LEFT JOIN inventory_categories ic ON i.category_id = ic.id
             LEFT JOIN suppliers s ON i.supplier_id = s.id
             WHERE i.id = :id AND i.status != 'deleted'",
            ['id' => $id]
        );
    }
    
    public function findByPartNumber($partNumber) {
        return $this->db->selectOne(
            "SELECT i.*, ic.name as category_name
             FROM {$this->table} i
             LEFT JOIN inventory_categories ic ON i.category_id = ic.id
             WHERE i.part_number = :part_number AND i.status != 'deleted'",
            ['part_number' => $partNumber]
        );
    }
    
    public function getAll($filters = []) {
        $where = ["i.status != 'deleted'"];
        $params = [];
        
        if (!empty($filters['status'])) {
            $where[] = "i.status = :status";
            $params['status'] = $filters['status'];
        }
        
        if (!empty($filters['category_id'])) {
            $where[] = "i.category_id = :category_id";
            $params['category_id'] = $filters['category_id'];
        }
        
        if (!empty($filters['supplier_id'])) {
            $where[] = "i.supplier_id = :supplier_id";
            $params['supplier_id'] = $filters['supplier_id'];
        }
        
        if (!empty($filters['low_stock'])) {
            $where[] = "i.current_stock <= i.minimum_stock";
        }
        
        if (!empty($filters['out_of_stock'])) {
            $where[] = "i.current_stock = 0";
        }
        
        if (!empty($filters['search'])) {
            $where[] = "(i.name LIKE :search OR i.part_number LIKE :search OR i.description LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }
        
        $whereClause = implode(' AND ', $where);
        $orderBy = $filters['order_by'] ?? 'i.name ASC';
        
        $sql = "SELECT i.*, ic.name as category_name, s.name as supplier_name,
                       CASE 
                           WHEN i.current_stock = 0 THEN 'out_of_stock'
                           WHEN i.current_stock <= i.minimum_stock THEN 'low_stock'
                           ELSE 'in_stock'
                       END as stock_status
                FROM {$this->table} i
                LEFT JOIN inventory_categories ic ON i.category_id = ic.id
                LEFT JOIN suppliers s ON i.supplier_id = s.id
                WHERE {$whereClause}
                ORDER BY {$orderBy}";
        
        return $this->db->select($sql, $params);
    }
    
    public function paginate($page = 1, $perPage = 20, $filters = []) {
        $where = ["i.status != 'deleted'"];
        $params = [];
        
        // Apply same filters as getAll method
        if (!empty($filters['status'])) {
            $where[] = "i.status = :status";
            $params['status'] = $filters['status'];
        }
        
        if (!empty($filters['search'])) {
            $where[] = "(i.name LIKE :search OR i.part_number LIKE :search OR i.description LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }
        
        $whereClause = implode(' AND ', $where);
        $orderBy = $filters['order_by'] ?? 'i.name ASC';
        
        $sql = "SELECT i.*, ic.name as category_name, s.name as supplier_name,
                       CASE 
                           WHEN i.current_stock = 0 THEN 'out_of_stock'
                           WHEN i.current_stock <= i.minimum_stock THEN 'low_stock'
                           ELSE 'in_stock'
                       END as stock_status
                FROM {$this->table} i
                LEFT JOIN inventory_categories ic ON i.category_id = ic.id
                LEFT JOIN suppliers s ON i.supplier_id = s.id
                WHERE {$whereClause}
                ORDER BY {$orderBy}";
        
        return $this->db->paginate($sql, $params, $page, $perPage);
    }
    
    // ========================================
    // STOCK MANAGEMENT
    // ========================================
    
    public function updateStock($inventoryId, $quantity, $type, $reference = null, $notes = null) {
        $this->db->beginTransaction();
        
        try {
            $item = $this->findById($inventoryId);
            if (!$item) {
                throw new Exception('Inventory item not found');
            }
            
            $newStock = $item['current_stock'] + $quantity;
            
            if ($newStock < 0) {
                throw new Exception('Insufficient stock');
            }
            
            // Update current stock
            $this->db->update(
                $this->table,
                [
                    'current_stock' => $newStock,
                    'last_stock_update' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ],
                'id = :id',
                ['id' => $inventoryId]
            );
            
            // Add stock movement record
            $this->addStockMovement($inventoryId, $quantity, $type, $notes, $reference);
            
            // Check for low stock alert
            if ($newStock <= $item['minimum_stock']) {
                $this->createLowStockAlert($inventoryId);
            }
            
            $this->db->commit();
            
            return $newStock;
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    public function addStock($inventoryId, $quantity, $notes = null) {
        return $this->updateStock($inventoryId, $quantity, 'stock_in', null, $notes);
    }
    
    public function removeStock($inventoryId, $quantity, $notes = null) {
        return $this->updateStock($inventoryId, -$quantity, 'stock_out', null, $notes);
    }
    
    public function adjustStock($inventoryId, $newQuantity, $notes = null) {
        $item = $this->findById($inventoryId);
        if (!$item) {
            throw new Exception('Inventory item not found');
        }
        
        $adjustment = $newQuantity - $item['current_stock'];
        $type = $adjustment >= 0 ? 'adjustment_in' : 'adjustment_out';
        
        return $this->updateStock($inventoryId, $adjustment, $type, null, $notes);
    }
    
    private function addStockMovement($inventoryId, $quantity, $type, $notes = null, $reference = null) {
        return $this->db->insert('inventory_movements', [
            'inventory_id' => $inventoryId,
            'quantity' => $quantity,
            'type' => $type,
            'reference_type' => $reference ? 'work_order' : null,
            'reference_id' => $reference,
            'notes' => $notes,
            'performed_by' => $_SESSION['user_id'] ?? null,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    public function getStockMovements($inventoryId, $limit = null) {
        $sql = "SELECT im.*, u.name as performed_by_name
                FROM inventory_movements im
                LEFT JOIN users u ON im.performed_by = u.id
                WHERE im.inventory_id = :inventory_id
                ORDER BY im.created_at DESC";
        
        if ($limit) {
            $sql .= " LIMIT {$limit}";
        }
        
        return $this->db->select($sql, ['inventory_id' => $inventoryId]);
    }
    
    // ========================================
    // STOCK ALERTS
    // ========================================
    
    private function createLowStockAlert($inventoryId) {
        // Check if alert already exists
        $existingAlert = $this->db->selectOne(
            "SELECT id FROM inventory_alerts 
             WHERE inventory_id = :inventory_id AND type = 'low_stock' AND status = 'active'",
            ['inventory_id' => $inventoryId]
        );
        
        if (!$existingAlert) {
            $this->db->insert('inventory_alerts', [
                'inventory_id' => $inventoryId,
                'type' => 'low_stock',
                'message' => 'Stock level is below minimum threshold',
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
    }
    
    public function getLowStockItems() {
        return $this->db->select(
            "SELECT i.*, ic.name as category_name
             FROM {$this->table} i
             LEFT JOIN inventory_categories ic ON i.category_id = ic.id
             WHERE i.status = 'active' 
             AND i.current_stock <= i.minimum_stock
             ORDER BY (i.current_stock / NULLIF(i.minimum_stock, 0)) ASC"
        );
    }
    
    public function getOutOfStockItems() {
        return $this->db->select(
            "SELECT i.*, ic.name as category_name
             FROM {$this->table} i
             LEFT JOIN inventory_categories ic ON i.category_id = ic.id
             WHERE i.status = 'active' AND i.current_stock = 0
             ORDER BY i.name ASC"
        );
    }
    
    public function getActiveAlerts() {
        return $this->db->select(
            "SELECT ia.*, i.name as item_name, i.part_number, i.current_stock, i.minimum_stock
             FROM inventory_alerts ia
             JOIN {$this->table} i ON ia.inventory_id = i.id
             WHERE ia.status = 'active'
             ORDER BY ia.created_at DESC"
        );
    }
    
    public function dismissAlert($alertId) {
        return $this->db->update(
            'inventory_alerts',
            [
                'status' => 'dismissed',
                'dismissed_at' => date('Y-m-d H:i:s'),
                'dismissed_by' => $_SESSION['user_id'] ?? null
            ],
            'id = :id',
            ['id' => $alertId]
        );
    }
    
    // ========================================
    // INVENTORY ANALYTICS
    // ========================================
    
    public function getInventoryStats() {
        $stats = [];
        
        // Total items by status
        $statusStats = $this->db->select(
            "SELECT 
                CASE 
                    WHEN current_stock = 0 THEN 'out_of_stock'
                    WHEN current_stock <= minimum_stock THEN 'low_stock'
                    ELSE 'in_stock'
                END as stock_status,
                COUNT(*) as count
             FROM {$this->table}
             WHERE status = 'active'
             GROUP BY stock_status"
        );
        
        foreach ($statusStats as $stat) {
            $stats['by_stock_status'][$stat['stock_status']] = $stat['count'];
        }
        
        // Total inventory value
        $valueStats = $this->db->selectOne(
            "SELECT 
                COUNT(*) as total_items,
                SUM(current_stock * unit_price) as total_value,
                AVG(unit_price) as avg_unit_price
             FROM {$this->table}
             WHERE status = 'active'"
        );
        
        $stats = array_merge($stats, $valueStats);
        
        // Top categories by value
        $categoryStats = $this->db->select(
            "SELECT ic.name, COUNT(i.id) as item_count, SUM(i.current_stock * i.unit_price) as total_value
             FROM inventory_categories ic
             LEFT JOIN {$this->table} i ON ic.id = i.category_id AND i.status = 'active'
             GROUP BY ic.id, ic.name
             ORDER BY total_value DESC
             LIMIT 10"
        );
        
        $stats['by_category'] = $categoryStats;
        
        // Recent movements
        $recentMovements = $this->db->select(
            "SELECT im.*, i.name as item_name, i.part_number
             FROM inventory_movements im
             JOIN {$this->table} i ON im.inventory_id = i.id
             ORDER BY im.created_at DESC
             LIMIT 10"
        );
        
        $stats['recent_movements'] = $recentMovements;
        
        return $stats;
    }
    
    public function getUsageAnalytics($inventoryId, $days = 30) {
        return $this->db->select(
            "SELECT 
                DATE(im.created_at) as date,
                SUM(CASE WHEN im.quantity < 0 THEN ABS(im.quantity) ELSE 0 END) as usage_quantity,
                COUNT(CASE WHEN im.quantity < 0 THEN 1 END) as usage_count
             FROM inventory_movements im
             WHERE im.inventory_id = :inventory_id
             AND im.created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
             GROUP BY DATE(im.created_at)
             ORDER BY date ASC",
            ['inventory_id' => $inventoryId, 'days' => $days]
        );
    }
    
    public function getTopUsedItems($limit = 10, $days = 30) {
        return $this->db->select(
            "SELECT i.*, ic.name as category_name,
                    SUM(ABS(im.quantity)) as total_used,
                    COUNT(im.id) as usage_frequency
             FROM {$this->table} i
             LEFT JOIN inventory_categories ic ON i.category_id = ic.id
             LEFT JOIN inventory_movements im ON i.id = im.inventory_id 
                 AND im.quantity < 0 
                 AND im.created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
             WHERE i.status = 'active'
             GROUP BY i.id
             HAVING total_used > 0
             ORDER BY total_used DESC
             LIMIT :limit",
            ['days' => $days, 'limit' => $limit]
        );
    }
    
    // ========================================
    // UTILITY METHODS
    // ========================================
    
    public function partNumberExists($partNumber, $excludeId = null) {
        $sql = "SELECT id FROM {$this->table} WHERE part_number = :part_number AND status != 'deleted'";
        $params = ['part_number' => $partNumber];
        
        if ($excludeId) {
            $sql .= " AND id != :exclude_id";
            $params['exclude_id'] = $excludeId;
        }
        
        $result = $this->db->selectOne($sql, $params);
        return $result !== false;
    }
    
    private function logActivity($inventoryId, $action, $description) {
        $this->db->insert('inventory_activities', [
            'inventory_id' => $inventoryId,
            'action' => $action,
            'description' => $description,
            'performed_by' => $_SESSION['user_id'] ?? null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    public function search($query, $limit = 10) {
        return $this->db->select(
            "SELECT i.id, i.name, i.part_number, i.current_stock, i.unit_price, ic.name as category_name
             FROM {$this->table} i
             LEFT JOIN inventory_categories ic ON i.category_id = ic.id
             WHERE i.status = 'active'
             AND (i.name LIKE :query OR i.part_number LIKE :query OR i.description LIKE :query)
             ORDER BY i.name ASC
             LIMIT {$limit}",
            ['query' => "%{$query}%"]
        );
    }
    
    public function generateReorderReport() {
        return $this->db->select(
            "SELECT i.*, ic.name as category_name, s.name as supplier_name, s.email as supplier_email,
                    (i.minimum_stock * 2) as suggested_order_quantity,
                    ((i.minimum_stock * 2) * i.unit_price) as estimated_cost
             FROM {$this->table} i
             LEFT JOIN inventory_categories ic ON i.category_id = ic.id
             LEFT JOIN suppliers s ON i.supplier_id = s.id
             WHERE i.status = 'active' 
             AND i.current_stock <= i.minimum_stock
             ORDER BY ic.name ASC, i.name ASC"
        );
    }
    
    public function bulkUpdatePrices($categoryId, $percentage, $type = 'increase') {
        $this->db->beginTransaction();
        
        try {
            $multiplier = $type === 'increase' ? (1 + $percentage / 100) : (1 - $percentage / 100);
            
            $updated = $this->db->query(
                "UPDATE {$this->table} 
                 SET unit_price = unit_price * :multiplier,
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
}
