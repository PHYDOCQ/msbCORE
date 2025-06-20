<?php
require_once __DIR__ . '/../config/database.php';

class Notification {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    public function send($userId, $title, $message, $type = 'info', $actionUrl = null) {
        try {
            $query = "INSERT INTO notifications (user_id, title, message, type, action_url, created_at) 
                     VALUES (?, ?, ?, ?, ?, NOW())";
            
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([$userId, $title, $message, $type, $actionUrl]);
            
            if ($result) {
                debugLog([
                    'user_id' => $userId,
                    'title' => $title,
                    'type' => $type
                ], 'NOTIFICATION_SENT');
            }
            
            return $result;
            
        } catch (Exception $e) {
            debugLog(['error' => $e->getMessage(), 'user_id' => $userId], 'NOTIFICATION_ERROR');
            return false;
        }
    }
    
    public function getUnread($userId, $limit = 10) {
        try {
            $query = "SELECT * FROM notifications 
                     WHERE user_id = ? AND is_read = 0 
                     ORDER BY created_at DESC 
                     LIMIT ?";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$userId, $limit]);
            
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            debugLog(['error' => $e->getMessage(), 'user_id' => $userId], 'NOTIFICATION_GET_ERROR');
            return [];
        }
    }
    
    public function markAsRead($notificationId, $userId = null) {
        try {
            $query = "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ?";
            $params = [$notificationId];
            
            if ($userId) {
                $query .= " AND user_id = ?";
                $params[] = $userId;
            }
            
            $stmt = $this->db->prepare($query);
            return $stmt->execute($params);
            
        } catch (Exception $e) {
            debugLog(['error' => $e->getMessage(), 'notification_id' => $notificationId], 'NOTIFICATION_MARK_READ_ERROR');
            return false;
        }
    }
    
    public function getCount($userId, $unreadOnly = true) {
        try {
            $query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ?";
            $params = [$userId];
            
            if ($unreadOnly) {
                $query .= " AND is_read = 0";
            }
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            
            $result = $stmt->fetch();
            return $result ? $result['count'] : 0;
            
        } catch (Exception $e) {
            debugLog(['error' => $e->getMessage(), 'user_id' => $userId], 'NOTIFICATION_COUNT_ERROR');
            return 0;
        }
    }
    
    public function deleteOld($daysOld = 30) {
        try {
            $query = "DELETE FROM notifications 
                     WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
            
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([$daysOld]);
            
            $deletedCount = $stmt->rowCount();
            debugLog(['deleted_count' => $deletedCount, 'days_old' => $daysOld], 'NOTIFICATION_CLEANUP');
            
            return $deletedCount;
            
        } catch (Exception $e) {
            debugLog(['error' => $e->getMessage()], 'NOTIFICATION_CLEANUP_ERROR');
            return 0;
        }
    }
}
?>
