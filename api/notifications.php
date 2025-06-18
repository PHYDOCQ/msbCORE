<?php
/**
 * BENGKEL MANAGEMENT PRO - NOTIFICATION CLASS
 * Version: 3.1.0
 * Complete Notification Management System
 */

class Notification {
    private $db;
    private $table = 'notifications';
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    // ========================================
    // NOTIFICATION CRUD OPERATIONS
    // ========================================
    
    public function create($data) {
        try {
            // Validate required fields
            $requiredFields = ['user_id', 'title', 'message', 'type'];
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    throw new Exception("Field {$field} is required");
                }
            }
            
            // Set default values
            $data['status'] = 'unread';
            $data['priority'] = $data['priority'] ?? 'normal';
            $data['created_at'] = date('Y-m-d H:i:s');
            
            // Insert notification
            $notificationId = $this->db->insert($this->table, $data);
            
            // Send real-time notification if enabled
            $this->sendRealTimeNotification($data);
            
            // Send email notification if required
            if (!empty($data['send_email']) && $data['send_email']) {
                $this->sendEmailNotification($data);
            }
            
            return $notificationId;
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    public function markAsRead($id, $userId = null) {
        try {
            $where = 'id = :id';
            $params = ['id' => $id];
            
            // Ensure user can only mark their own notifications as read
            if ($userId) {
                $where .= ' AND user_id = :user_id';
                $params['user_id'] = $userId;
            }
            
            $updated = $this->db->update(
                $this->table,
                [
                    'status' => 'read',
                    'read_at' => date('Y-m-d H:i:s')
                ],
                $where,
                $params
            );
            
            return $updated > 0;
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    public function markAllAsRead($userId) {
        try {
            $updated = $this->db->update(
                $this->table,
                [
                    'status' => 'read',
                    'read_at' => date('Y-m-d H:i:s')
                ],
                'user_id = :user_id AND status = :status',
                ['user_id' => $userId, 'status' => 'unread']
            );
            
            return $updated;
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    public function delete($id, $userId = null) {
        try {
            $where = 'id = :id';
            $params = ['id' => $id];
            
            // Ensure user can only delete their own notifications
            if ($userId) {
                $where .= ' AND user_id = :user_id';
                $params['user_id'] = $userId;
            }
            
            $deleted = $this->db->delete($this->table, $where, $params);
            
            return $deleted > 0;
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    public function findById($id) {
        return $this->db->selectOne(
            "SELECT n.*, u.name as user_name
             FROM {$this->table} n
             LEFT JOIN users u ON n.user_id = u.id
             WHERE n.id = :id",
            ['id' => $id]
        );
    }
    
    // ========================================
    // USER NOTIFICATIONS
    // ========================================
    
    public function getUserNotifications($userId, $filters = []) {
        $where = ['n.user_id = :user_id'];
        $params = ['user_id' => $userId];
        
        if (!empty($filters['status'])) {
            $where[] = 'n.status = :status';
            $params['status'] = $filters['status'];
        }
        
        if (!empty($filters['type'])) {
            $where[] = 'n.type = :type';
            $params['type'] = $filters['type'];
        }
        
        if (!empty($filters['priority'])) {
            $where[] = 'n.priority = :priority';
            $params['priority'] = $filters['priority'];
        }
        
        $whereClause = implode(' AND ', $where);
        $limit = $filters['limit'] ?? 50;
        $orderBy = $filters['order_by'] ?? 'n.created_at DESC';
        
        $sql = "SELECT n.*
                FROM {$this->table} n
                WHERE {$whereClause}
                ORDER BY {$orderBy}
                LIMIT {$limit}";
        
        return $this->db->select($sql, $params);
    }
    
    public function getUnreadCount($userId) {
        return $this->db->count(
            $this->table,
            'user_id = :user_id AND status = :status',
            ['user_id' => $userId, 'status' => 'unread']
        );
    }
    
    public function getRecentNotifications($userId, $limit = 10) {
        return $this->db->select(
            "SELECT * FROM {$this->table}
             WHERE user_id = :user_id
             ORDER BY created_at DESC
             LIMIT {$limit}",
            ['user_id' => $userId]
        );
    }
    
    // ========================================
    // BULK NOTIFICATIONS
    // ========================================
    
    public function sendToMultipleUsers($userIds, $data) {
        $this->db->beginTransaction();
        
        try {
            $notificationIds = [];
            
            foreach ($userIds as $userId) {
                $notificationData = array_merge($data, ['user_id' => $userId]);
                $notificationIds[] = $this->create($notificationData);
            }
            
            $this->db->commit();
            
            return $notificationIds;
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    public function sendToRole($role, $data) {
        // Get all users with the specified role
        $users = $this->db->select(
            "SELECT id FROM users WHERE role = :role AND status = 'active'",
            ['role' => $role]
        );
        
        $userIds = array_column($users, 'id');
        
        return $this->sendToMultipleUsers($userIds, $data);
    }
    
    public function sendToAllUsers($data, $excludeUserIds = []) {
        $where = "status = 'active'";
        $params = [];
        
        if (!empty($excludeUserIds)) {
            $placeholders = [];
            foreach ($excludeUserIds as $index => $userId) {
                $placeholders[] = ":exclude_{$index}";
                $params["exclude_{$index}"] = $userId;
            }
            $where .= " AND id NOT IN (" . implode(',', $placeholders) . ")";
        }
        
        $users = $this->db->select("SELECT id FROM users WHERE {$where}", $params);
        $userIds = array_column($users, 'id');
        
        return $this->sendToMultipleUsers($userIds, $data);
    }
    
    // ========================================
    // SYSTEM NOTIFICATIONS
    // ========================================
    
    public function createWorkOrderNotification($workOrderId, $type, $additionalData = []) {
        $workOrder = new WorkOrder();
        $wo = $workOrder->findById($workOrderId);
        
        if (!$wo) return false;
        
        $notifications = [];
        
        switch ($type) {
            case 'created':
                // Notify managers and admins
                $notifications[] = [
                    'roles' => ['admin', 'manager'],
                    'title' => 'New Work Order Created',
                    'message' => "Work order #{$wo['work_order_number']} has been created for {$wo['customer_name']}",
                    'type' => 'work_order',
                    'priority' => 'normal',
                    'reference_id' => $workOrderId,
                    'action_url' => "/work-orders/view.php?id={$workOrderId}"
                ];
                break;
                
            case 'assigned':
                // Notify assigned technician
                if ($wo['assigned_to']) {
                    $notifications[] = [
                        'user_ids' => [$wo['assigned_to']],
                        'title' => 'Work Order Assigned',
                        'message' => "Work order #{$wo['work_order_number']} has been assigned to you",
                        'type' => 'work_order',
                        'priority' => 'high',
                        'reference_id' => $workOrderId,
                        'action_url' => "/work-orders/view.php?id={$workOrderId}"
                    ];
                }
                break;
                
            case 'status_changed':
                $oldStatus = $additionalData['old_status'] ?? '';
                $newStatus = $additionalData['new_status'] ?? '';
                
                // Notify customer and assigned technician
                $userIds = [];
                if ($wo['assigned_to']) {
                    $userIds[] = $wo['assigned_to'];
                }
                
                if (!empty($userIds)) {
                    $notifications[] = [
                        'user_ids' => $userIds,
                        'title' => 'Work Order Status Updated',
                        'message' => "Work order #{$wo['work_order_number']} status changed from {$oldStatus} to {$newStatus}",
                        'type' => 'work_order',
                        'priority' => 'normal',
                        'reference_id' => $workOrderId,
                        'action_url' => "/work-orders/view.php?id={$workOrderId}"
                    ];
                }
                break;
                
            case 'completed':
                // Notify managers and customer
                $notifications[] = [
                    'roles' => ['admin', 'manager'],
                    'title' => 'Work Order Completed',
                    'message' => "Work order #{$wo['work_order_number']} has been completed",
                    'type' => 'work_order',
                    'priority' => 'normal',
                    'reference_id' => $workOrderId,
                    'action_url' => "/work-orders/view.php?id={$workOrderId}"
                ];
                break;
        }
        
        // Send notifications
        foreach ($notifications as $notification) {
            if (isset($notification['roles'])) {
                foreach ($notification['roles'] as $role) {
                    $this->sendToRole($role, $notification);
                }
            } elseif (isset($notification['user_ids'])) {
                $this->sendToMultipleUsers($notification['user_ids'], $notification);
            }
        }
        
        return true;
    }
    
    public function createInventoryNotification($inventoryId, $type, $additionalData = []) {
        $inventory = new Inventory();
        $item = $inventory->findById($inventoryId);
        
        if (!$item) return false;
        
        $notifications = [];
        
        switch ($type) {
            case 'low_stock':
                $notifications[] = [
                    'roles' => ['admin', 'manager'],
                    'title' => 'Low Stock Alert',
                    'message' => "Item '{$item['name']}' is running low on stock (Current: {$item['current_stock']}, Minimum: {$item['minimum_stock']})",
                    'type' => 'inventory',
                    'priority' => 'high',
                    'reference_id' => $inventoryId,
                    'action_url' => "/inventory/view.php?id={$inventoryId}"
                ];
                break;
                
            case 'out_of_stock':
                $notifications[] = [
                    'roles' => ['admin', 'manager'],
                    'title' => 'Out of Stock Alert',
                    'message' => "Item '{$item['name']}' is out of stock",
                    'type' => 'inventory',
                    'priority' => 'urgent',
                    'reference_id' => $inventoryId,
                    'action_url' => "/inventory/view.php?id={$inventoryId}"
                ];
                break;
        }
        
        // Send notifications
        foreach ($notifications as $notification) {
            if (isset($notification['roles'])) {
                foreach ($notification['roles'] as $role) {
                    $this->sendToRole($role, $notification);
                }
            }
        }
        
        return true;
    }
    
    public function createMaintenanceReminder($vehicleId, $serviceType, $dueDate) {
        $vehicle = new Vehicle();
        $v = $vehicle->findById($vehicleId);
        
        if (!$v) return false;
        
        $notification = [
            'title' => 'Maintenance Reminder',
            'message' => "Vehicle {$v['license_plate']} ({$v['brand']} {$v['model']}) is due for {$serviceType} on {$dueDate}",
            'type' => 'maintenance',
            'priority' => 'normal',
            'reference_id' => $vehicleId,
            'action_url' => "/vehicles/view.php?id={$vehicleId}"
        ];
        
        // Send to managers and admins
        $this->sendToRole('admin', $notification);
        $this->sendToRole('manager', $notification);
        
        return true;
    }
    
    // ========================================
    // NOTIFICATION PREFERENCES
    // ========================================
    
    public function getUserPreferences($userId) {
        $preferences = $this->db->selectOne(
            "SELECT * FROM notification_preferences WHERE user_id = :user_id",
            ['user_id' => $userId]
        );
        
        if (!$preferences) {
            // Create default preferences
            $defaultPreferences = [
                'user_id' => $userId,
                'email_notifications' => 1,
                'push_notifications' => 1,
                'work_order_notifications' => 1,
                'inventory_notifications' => 1,
                'maintenance_notifications' => 1,
                'system_notifications' => 1,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $this->db->insert('notification_preferences', $defaultPreferences);
            return $defaultPreferences;
        }
        
        return $preferences;
    }
    
    public function updateUserPreferences($userId, $preferences) {
        try {
            $preferences['updated_at'] = date('Y-m-d H:i:s');
            
            $exists = $this->db->exists('notification_preferences', 'user_id = :user_id', ['user_id' => $userId]);
            
            if ($exists) {
                return $this->db->update(
                    'notification_preferences',
                    $preferences,
                    'user_id = :user_id',
                    ['user_id' => $userId]
                );
            } else {
                $preferences['user_id'] = $userId;
                $preferences['created_at'] = date('Y-m-d H:i:s');
                return $this->db->insert('notification_preferences', $preferences);
            }
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    // ========================================
    // NOTIFICATION DELIVERY
    // ========================================
    
    private function sendRealTimeNotification($data) {
        // Implementation for real-time notifications (WebSocket, Server-Sent Events, etc.)
        // This would integrate with your real-time notification system
        
        // Example: Send to WebSocket server
        if (function_exists('curl_init')) {
            $payload = json_encode([
                'user_id' => $data['user_id'],
                'title' => $data['title'],
                'message' => $data['message'],
                'type' => $data['type'],
                'priority' => $data['priority'] ?? 'normal'
            ]);
            
            // Send to WebSocket server or push notification service
            // Implementation depends on your setup
        }
    }
    
    private function sendEmailNotification($data) {
        // Get user email
        $user = $this->db->selectOne(
            "SELECT email, name FROM users WHERE id = :user_id",
            ['user_id' => $data['user_id']]
        );
        
        if (!$user) return false;
        
        // Check user preferences
        $preferences = $this->getUserPreferences($data['user_id']);
        if (!$preferences['email_notifications']) {
            return false;
        }
        
        // Send email using your email service
        // This would integrate with your email system (PHPMailer, SendGrid, etc.)
        
        return true;
    }
    
    // ========================================
    // NOTIFICATION ANALYTICS
    // ========================================
    
    public function getNotificationStats($userId = null) {
        $stats = [];
        
        if ($userId) {
            // Stats for specific user
            $userStats = $this->db->selectOne(
                "SELECT 
                    COUNT(*) as total_notifications,
                    COUNT(CASE WHEN status = 'unread' THEN 1 END) as unread_count,
                    COUNT(CASE WHEN status = 'read' THEN 1 END) as read_count,
                    COUNT(CASE WHEN priority = 'urgent' THEN 1 END) as urgent_count,
                    COUNT(CASE WHEN priority = 'high' THEN 1 END) as high_count
                 FROM {$this->table}
                 WHERE user_id = :user_id",
                ['user_id' => $userId]
            );
            
            $stats = $userStats;
            
            // Notifications by type
            $typeStats = $this->db->select(
                "SELECT type, COUNT(*) as count
                 FROM {$this->table}
                 WHERE user_id = :user_id
                 GROUP BY type
                 ORDER BY count DESC",
                ['user_id' => $userId]
            );
            
            $stats['by_type'] = $typeStats;
        } else {
            // Global stats
            $globalStats = $this->db->selectOne(
                "SELECT 
                    COUNT(*) as total_notifications,
                    COUNT(CASE WHEN status = 'unread' THEN 1 END) as unread_count,
                    COUNT(CASE WHEN status = 'read' THEN 1 END) as read_count,
                    COUNT(DISTINCT user_id) as total_users_notified
                 FROM {$this->table}"
            );
            
            $stats = $globalStats;
            
            // Notifications by type
            $typeStats = $this->db->select(
                "SELECT type, COUNT(*) as count
                 FROM {$this->table}
                 GROUP BY type
                 ORDER BY count DESC"
            );
            
            $stats['by_type'] = $typeStats;
            
            // Recent notification activity
            $recentActivity = $this->db->select(
                "SELECT DATE(created_at) as date, COUNT(*) as count
                 FROM {$this->table}
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                 GROUP BY DATE(created_at)
                 ORDER BY date ASC"
            );
            
            $stats['recent_activity'] = $recentActivity;
        }
        
        return $stats;
    }
    
    // ========================================
    // CLEANUP AND MAINTENANCE
    // ========================================
    
    public function cleanupOldNotifications($days = 90) {
        try {
            $deleted = $this->db->delete(
                $this->table,
                'created_at < DATE_SUB(NOW(), INTERVAL :days DAY) AND status = :status',
                ['days' => $days, 'status' => 'read']
            );
            
            return $deleted;
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    public function getNotificationTemplates() {
        return [
            'work_order_created' => [
                'title' => 'New Work Order Created',
                'message' => 'Work order #{work_order_number} has been created for {customer_name}',
                'type' => 'work_order',
                'priority' => 'normal'
            ],
            'work_order_assigned' => [
                'title' => 'Work Order Assigned',
                'message' => 'Work order #{work_order_number} has been assigned to you',
                'type' => 'work_order',
                'priority' => 'high'
            ],
            'work_order_completed' => [
                'title' => 'Work Order Completed',
                'message' => 'Work order #{work_order_number} has been completed',
                'type' => 'work_order',
                'priority' => 'normal'
            ],
            'low_stock_alert' => [
                'title' => 'Low Stock Alert',
                'message' => 'Item {item_name} is running low on stock',
                'type' => 'inventory',
                'priority' => 'high'
            ],
            'maintenance_reminder' => [
                'title' => 'Maintenance Reminder',
                'message' => 'Vehicle {license_plate} is due for maintenance',
                'type' => 'maintenance',
                'priority' => 'normal'
            ]
        ];
    }
}
