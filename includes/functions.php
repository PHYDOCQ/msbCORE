<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';

if (!class_exists('Utils')) {
class Utils {
    
    public static function generateCustomerCode($db = null) {
        if (!$db) {
            $db = Database::getInstance()->getConnection();
        }
        
        $query = "SELECT COUNT(*) + 1 as next_num FROM customers";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $num = $stmt->fetch()['next_num'];
        
        do {
            $code = 'CUST' . str_pad($num, 6, '0', STR_PAD_LEFT);
            $checkQuery = "SELECT id FROM customers WHERE customer_code = ? LIMIT 1";
            $checkStmt = $db->prepare($checkQuery);
            $checkStmt->execute([$code]);
            
            if ($checkStmt->rowCount() === 0) {
                break;
            }
            $num++;
        } while (true);
        
        debugLog(['generated_code' => $code], 'CUSTOMER_CODE');
        return $code;
    }
    
    public static function generateWorkOrderNumber($db = null) {
        if (!$db) {
            $db = Database::getInstance()->getConnection();
        }
        
        $prefix = 'WO' . date('Ym');
        $query = "SELECT COUNT(*) + 1 as next_num FROM work_orders WHERE work_order_number LIKE ? ORDER BY id DESC LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->execute([$prefix . '%']);
        $result = $stmt->fetch();
        $num = $result ? $result['next_num'] : 1;
        
        do {
            $number = $prefix . str_pad($num, 4, '0', STR_PAD_LEFT);
            $checkQuery = "SELECT id FROM work_orders WHERE work_order_number = ? LIMIT 1";
            $checkStmt = $db->prepare($checkQuery);
            $checkStmt->execute([$number]);
            
            if ($checkStmt->rowCount() === 0) {
                break;
            }
            $num++;
        } while (true);
        
        debugLog(['generated_number' => $number], 'WORK_ORDER_NUMBER');
        return $number;
    }
    
    public static function generateItemCode($categoryId, $db = null) {
        if (!$db) {
            $db = Database::getInstance()->getConnection();
        }
        
        // Get category prefix
        $categoryQuery = "SELECT name FROM inventory_categories WHERE id = ? LIMIT 1";
        $categoryStmt = $db->prepare($categoryQuery);
        $categoryStmt->execute([$categoryId]);
        $category = $categoryStmt->fetch();
        
        if (!$category) {
            throw new Exception('Category not found');
        }
        
        $prefix = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $category['name']), 0, 3));
        if (strlen($prefix) < 3) {
            $prefix = str_pad($prefix, 3, 'X');
        }
        
        // Get next number
        $query = "SELECT COUNT(*) + 1 as next_num FROM inventory WHERE category_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$categoryId]);
        $num = $stmt->fetch()['next_num'];
        
        do {
            $code = $prefix . str_pad($num, 5, '0', STR_PAD_LEFT);
            $checkQuery = "SELECT id FROM inventory WHERE item_code = ? LIMIT 1";
            $checkStmt = $db->prepare($checkQuery);
            $checkStmt->execute([$code]);
            
            if ($checkStmt->rowCount() === 0) {
                break;
            }
            $num++;
        } while (true);
        
        debugLog(['generated_code' => $code, 'category_id' => $categoryId], 'ITEM_CODE');
        return $code;
    }
    
    public static function formatCurrency($amount, $currency = 'IDR') {
        $amount = floatval($amount);
        
        switch ($currency) {
            case 'IDR':
                return 'Rp ' . number_format($amount, 0, ',', '.');
            case 'USD':
                return '$' . number_format($amount, 2, '.', ',');
            default:
                return number_format($amount, 2, ',', '.');
        }
    }
    
    public static function formatDate($date, $format = 'd/m/Y H:i', $timezone = null) {
        if (empty($date)) {
            return '-';
        }
        
        try {
            $dt = new DateTime($date);
            if ($timezone) {
                $dt->setTimezone(new DateTimeZone($timezone));
            }
            return $dt->format($format);
        } catch (Exception $e) {
            debugLog(['error' => $e->getMessage(), 'date' => $date], 'DATE_FORMAT_ERROR');
            return '-';
        }
    }
    
    public static function formatDateRange($startDate, $endDate, $format = 'd/m/Y') {
        if (empty($startDate)) {
            return '-';
        }
        
        $start = self::formatDate($startDate, $format);
        
        if (empty($endDate)) {
            return $start;
        }
        
        $end = self::formatDate($endDate, $format);
        
        return $start . ' - ' . $end;
    }
    
    public static function formatFileSize($bytes, $decimals = 2) {
        $size = ['B', 'KB', 'MB', 'GB', 'TB'];
        $factor = floor((strlen($bytes) - 1) / 3);
        
        return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . ' ' . $size[$factor];
    }
    
    public static function timeAgo($datetime, $full = false) {
        $now = new DateTime;
        $ago = new DateTime($datetime);
        $diff = $now->diff($ago);
        
        $strings = [
            'y' => 'year',
            'm' => 'month', 
            'd' => 'day',
            'h' => 'hour',
            'i' => 'minute',
            's' => 'second',
        ];
        
        foreach ($strings as $key => $value) {
            if ($diff->$key) {
                $time = $diff->$key . ' ' . $value . ($diff->$key > 1 ? 's' : '');
                return $time . ' ago';
            }
        }
        
        return 'just now';
    }
    
    public static function uploadFile($file, $directory, $allowedTypes = null) {
        try {
            // Use default allowed types if not specified
            if ($allowedTypes === null) {
                $allowedTypes = array_merge(ALLOWED_IMAGE_EXTENSIONS, ALLOWED_DOCUMENT_EXTENSIONS);
            }
            
            // Validate file
            $errors = Security::validateFileUpload($file, $allowedTypes);
            if (!empty($errors)) {
                throw new Exception(implode(', ', $errors));
            }
            
            // Create directory if it doesn't exist
            $uploadPath = __DIR__ . '/../assets/uploads/' . trim($directory, '/') . '/';
            if (!is_dir($uploadPath)) {
                if (!mkdir($uploadPath, 0755, true)) {
                    throw new Exception('Failed to create upload directory');
                }
            }
            
            // Generate secure filename
            $filename = Security::generateSecureFilename($file['name']);
            $targetPath = $uploadPath . $filename;
            
            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                throw new Exception('Failed to move uploaded file');
            }
            
            // Set proper permissions
            chmod($targetPath, 0644);
            
            debugLog([
                'original_name' => $file['name'],
                'new_filename' => $filename,
                'size' => $file['size'],
                'directory' => $directory
            ], 'FILE_UPLOAD');
            
            return [
                'success' => true,
                'filename' => $filename,
                'original_name' => $file['name'],
                'size' => $file['size'],
                'path' => 'assets/uploads/' . trim($directory, '/') . '/' . $filename
            ];
            
        } catch (Exception $e) {
            debugLog(['error' => $e->getMessage(), 'file' => $file['name'] ?? 'unknown'], 'FILE_UPLOAD_ERROR');
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    public static function deleteFile($filePath) {
        try {
            $fullPath = __DIR__ . '/../' . ltrim($filePath, '/');
            
            if (file_exists($fullPath)) {
                if (unlink($fullPath)) {
                    debugLog(['deleted_file' => $filePath], 'FILE_DELETE');
                    return true;
                } else {
                    throw new Exception('Failed to delete file');
                }
            }
            
            return true; // File doesn't exist, consider it deleted
            
        } catch (Exception $e) {
            debugLog(['error' => $e->getMessage(), 'file' => $filePath], 'FILE_DELETE_ERROR');
            return false;
        }
    }
    
    public static function generateQRCode($data, $size = 200) {
        // Using a free QR code API service
        $baseUrl = 'https://api.qrserver.com/v1/create-qr-code/';
        $params = http_build_query([
            'size' => $size . 'x' . $size,
            'data' => $data,
            'format' => 'png'
        ]);
        
        return $baseUrl . '?' . $params;
    }
    
    public static function sendNotification($userId, $title, $message, $type = 'info', $actionUrl = null) {
        try {
            $db = Database::getInstance()->getConnection();
            
            $query = "INSERT INTO notifications (user_id, title, message, notification_type, action_url, created_at) 
                     VALUES (?, ?, ?, ?, ?, NOW())";
            
            $stmt = $db->prepare($query);
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
    
    public static function broadcastNotification($roles, $title, $message, $type = 'info', $actionUrl = null) {
        try {
            $db = Database::getInstance()->getConnection();
            
            if (!is_array($roles)) {
                $roles = [$roles];
            }
            
            $placeholders = str_repeat('?,', count($roles) - 1) . '?';
            
            $userQuery = "SELECT id FROM users WHERE role IN ($placeholders) AND is_active = 1";
            $userStmt = $db->prepare($userQuery);
            $userStmt->execute($roles);
            $users = $userStmt->fetchAll();
            
            $count = 0;
            foreach ($users as $user) {
                if (self::sendNotification($user['id'], $title, $message, $type, $actionUrl)) {
                    $count++;
                }
            }
            
            debugLog([
                'roles' => $roles,
                'title' => $title,
                'users_notified' => $count
            ], 'BROADCAST_NOTIFICATION');
            
            return $count;
            
        } catch (Exception $e) {
            debugLog(['error' => $e->getMessage(), 'roles' => $roles], 'BROADCAST_NOTIFICATION_ERROR');
            return 0;
        }
    }
    
    public static function checkLowStock($threshold = null) {
        try {
            $db = Database::getInstance()->getConnection();
            
            $query = "SELECT * FROM inventory 
                     WHERE current_stock <= COALESCE(minimum_stock, 5) 
                     AND is_active = 1";
            
            if ($threshold !== null) {
                $query .= " AND current_stock <= ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$threshold]);
            } else {
                $stmt = $db->prepare($query);
                $stmt->execute();
            }
            
            $lowStockItems = $stmt->fetchAll();
            
            if (!empty($lowStockItems)) {
                // Notify admins
                $title = 'Low Stock Alert';
                $message = count($lowStockItems) . ' items are running low on stock. Please restock soon.';
                self::broadcastNotification(['admin', 'manager'], $title, $message, 'warning', 'modules/inventory/list.php?filter=low_stock');
                
                debugLog(['count' => count($lowStockItems)], 'LOW_STOCK_CHECK');
            }
            
            return $lowStockItems;
            
        } catch (Exception $e) {
            debugLog(['error' => $e->getMessage()], 'LOW_STOCK_CHECK_ERROR');
            return [];
        }
    }
    
    public static function logActivity($entityType, $entityId, $action, $description = null, $userId = null) {
        try {
            $db = Database::getInstance()->getConnection();
            
            if (!$userId) {
                $userId = $_SESSION['user_id'] ?? null;
            }
            
            $query = "INSERT INTO activity_log (entity_type, entity_id, action, description, user_id, ip_address, user_agent, created_at) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $db->prepare($query);
            $result = $stmt->execute([
                $entityType,
                $entityId,
                $action,
                $description,
                $userId,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
            
            debugLog([
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'action' => $action,
                'user_id' => $userId
            ], 'ACTIVITY_LOG');
            
            return $result;
            
        } catch (Exception $e) {
            debugLog(['error' => $e->getMessage()], 'ACTIVITY_LOG_ERROR');
            return false;
        }
    }
    
    public static function getSystemStats() {
        try {
            $db = Database::getInstance()->getConnection();
            
            $stats = [];
            
            // Work Order Stats
            $woQuery = "SELECT 
                           COUNT(*) as total,
                           COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
                           COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress,
                           COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed
                       FROM work_orders";
            $stats['work_orders'] = $db->query($woQuery)->fetch();
            
            // Customer Stats
            $customerQuery = "SELECT 
                                COUNT(*) as total,
                                COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH) THEN 1 END) as new_this_month
                             FROM customers WHERE is_active = 1";
            $stats['customers'] = $db->query($customerQuery)->fetch();
            
            // Inventory Stats
            $inventoryQuery = "SELECT 
                                 COUNT(*) as total_items,
                                 COUNT(CASE WHEN current_stock <= minimum_stock THEN 1 END) as low_stock,
                                 COUNT(CASE WHEN current_stock <= 0 THEN 1 END) as out_of_stock
                              FROM inventory WHERE is_active = 1";
            $stats['inventory'] = $db->query($inventoryQuery)->fetch();
            
            // Revenue Stats
            $revenueQuery = "SELECT 
                               SUM(CASE WHEN status = 'completed' AND YEAR(actual_completion_date) = YEAR(NOW()) AND MONTH(actual_completion_date) = MONTH(NOW()) THEN final_amount ELSE 0 END) as monthly_revenue,
                               SUM(CASE WHEN status = 'completed' AND DATE(actual_completion_date) >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN final_amount ELSE 0 END) as weekly_revenue
                            FROM work_orders";
            $stats['revenue'] = $db->query($revenueQuery)->fetch();
            
            return $stats;
            
        } catch (Exception $e) {
            debugLog(['error' => $e->getMessage()], 'SYSTEM_STATS_ERROR');
            return [];
        }
    }
    
    public static function cleanupOldFiles($directory, $daysOld = 30) {
        $path = __DIR__ . '/../assets/' . trim($directory, '/') . '/';
        $cutoffTime = time() - ($daysOld * 24 * 3600);
        $deletedCount = 0;
        
        if (is_dir($path)) {
            $files = scandir($path);
            foreach ($files as $file) {
                if ($file != '.' && $file != '..') {
                    $filePath = $path . $file;
                    if (is_file($filePath) && filemtime($filePath) < $cutoffTime) {
                        if (unlink($filePath)) {
                            $deletedCount++;
                        }
                    }
                }
            }
        }
        
        debugLog(['directory' => $directory, 'deleted_count' => $deletedCount], 'CLEANUP_OLD_FILES');
        return $deletedCount;
    }
}

// Helper functions for backward compatibility
if (!function_exists('generateCustomerCode')) {
    function generateCustomerCode() {
        return Utils::generateCustomerCode();
    }
}

if (!function_exists('generateWorkOrderNumber')) {
    function generateWorkOrderNumber() {
        return Utils::generateWorkOrderNumber();
    }
}

if (!function_exists('formatCurrency')) {
    function formatCurrency($amount, $currency = 'IDR') {
        return Utils::formatCurrency($amount, $currency);
    }
}

if (!function_exists('formatDate')) {
    function formatDate($date, $format = 'd/m/Y H:i') {
        return Utils::formatDate($date, $format);
    }
}

if (!function_exists('createNotification')) {
    function createNotification($userId, $title, $message, $type = 'info', $actionUrl = null) {
        return Utils::sendNotification($userId, $title, $message, $type, $actionUrl);
    }
}
?>
