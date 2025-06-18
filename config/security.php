<?php
require_once __DIR__ . '/config.php';

class Security {
    
    public static function hashPassword($password) {
        $options = [
            'memory_cost' => 65536, // 64 MB
            'time_cost' => 4,       // 4 iterations
            'threads' => 3          // 3 threads
        ];
        
        return password_hash($password, PASSWORD_ARGON2ID, $options);
    }
    
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    public static function generateToken($length = 32) {
        try {
            return bin2hex(random_bytes($length / 2));
        } catch (Exception $e) {
            debugLog('Token generation failed: ' . $e->getMessage(), 'ERROR');
            // Fallback to less secure method
            return md5(uniqid(mt_rand(), true));
        }
    }
    
    public static function encrypt($data, $key = ENCRYPTION_KEY) {
        $cipher = 'aes-256-gcm';
        $iv = random_bytes(openssl_cipher_iv_length($cipher));
        $tag = '';
        
        $encrypted = openssl_encrypt($data, $cipher, $key, OPENSSL_RAW_DATA, $iv, $tag);
        
        if ($encrypted === false) {
            throw new Exception('Encryption failed');
        }
        
        return base64_encode($iv . $tag . $encrypted);
    }
    
    public static function decrypt($encryptedData, $key = ENCRYPTION_KEY) {
        $data = base64_decode($encryptedData);
        if ($data === false) {
            return false;
        }
        
        $cipher = 'aes-256-gcm';
        $ivLength = openssl_cipher_iv_length($cipher);
        
        $iv = substr($data, 0, $ivLength);
        $tag = substr($data, $ivLength, 16);
        $encrypted = substr($data, $ivLength + 16);
        
        $result = openssl_decrypt($encrypted, $cipher, $key, OPENSSL_RAW_DATA, $iv, $tag);
        
        return $result !== false ? $result : false;
    }
    
    public static function sanitizeInput($input, $type = 'string') {
        if (is_array($input)) {
            return array_map(function($item) use ($type) {
                return self::sanitizeInput($item, $type);
            }, $input);
        }
        
        // Remove null bytes
        $input = str_replace("\0", '', $input);
        
        switch ($type) {
            case 'email':
                return filter_var(trim($input), FILTER_SANITIZE_EMAIL);
            case 'url':
                return filter_var(trim($input), FILTER_SANITIZE_URL);
            case 'int':
                return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
            case 'float':
                return filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            case 'html':
                return htmlspecialchars(trim($input), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            default:
                return htmlspecialchars(trim($input), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
    }
    
    public static function validateCSRF($token) {
        return isset($_SESSION['csrf_token']) && 
               !empty($_SESSION['csrf_token']) && 
               hash_equals($_SESSION['csrf_token'], $token);
    }
    
    public static function generateCSRF() {
        $_SESSION['csrf_token'] = self::generateToken();
        $_SESSION['csrf_token_time'] = time();
        return $_SESSION['csrf_token'];
    }
    
    public static function isCSRFExpired($maxAge = 3600) {
        return !isset($_SESSION['csrf_token_time']) || 
               (time() - $_SESSION['csrf_token_time']) > $maxAge;
    }
    
    public static function rateLimitCheck($identifier, $maxAttempts = RATE_LIMIT_REQUESTS, $timeWindow = RATE_LIMIT_WINDOW) {
        $key = "rate_limit_" . hash('sha256', $identifier);
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['count' => 0, 'time' => time()];
        }
        
        // Reset if time window has passed
        if (time() - $_SESSION[$key]['time'] > $timeWindow) {
            $_SESSION[$key] = ['count' => 0, 'time' => time()];
        }
        
        $_SESSION[$key]['count']++;
        
        $isAllowed = $_SESSION[$key]['count'] <= $maxAttempts;
        
        debugLog([
            'identifier' => $identifier,
            'count' => $_SESSION[$key]['count'],
            'allowed' => $isAllowed
        ], 'RATE_LIMIT');
        
        return $isAllowed;
    }
    
    public static function validateFileUpload($file, $allowedTypes = ALLOWED_IMAGE_EXTENSIONS) {
        $errors = [];
        
        // Check if file was uploaded
        if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
            $errors[] = 'No file uploaded';
            return $errors;
        }
        
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Upload error: ' . $file['error'];
            return $errors;
        }
        
        // Check file size
        if ($file['size'] > UPLOAD_MAX_SIZE) {
            $errors[] = 'File too large. Maximum size: ' . (UPLOAD_MAX_SIZE / 1024 / 1024) . 'MB';
        }
        
        // Check file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedTypes)) {
            $errors[] = 'Invalid file type. Allowed: ' . implode(', ', $allowedTypes);
        }
        
        // Check MIME type for images
        if (in_array($extension, ALLOWED_IMAGE_EXTENSIONS)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            $allowedMimes = [
                'image/jpeg',
                'image/png', 
                'image/gif',
                'image/webp'
            ];
            
            if (!in_array($mimeType, $allowedMimes)) {
                $errors[] = 'Invalid image format';
            }
        }
        
        return $errors;
    }
    
    public static function generateSecureFilename($originalFilename) {
        $extension = pathinfo($originalFilename, PATHINFO_EXTENSION);
        $timestamp = time();
        $random = self::generateToken(16);
        
        return sprintf('%s_%s.%s', $timestamp, $random, $extension);
    }
    
    public static function preventXSS($input) {
        if (is_array($input)) {
            return array_map([self::class, 'preventXSS'], $input);
        }
        
        // Remove potentially dangerous tags
        $input = preg_replace('#<script[^>]*>.*?</script>#is', '', $input);
        $input = preg_replace('#<iframe[^>]*>.*?</iframe>#is', '', $input);
        $input = preg_replace('#<object[^>]*>.*?</object>#is', '', $input);
        $input = preg_replace('#<embed[^>]*>.*?</embed>#is', '', $input);
        
        // Remove javascript: protocol
        $input = preg_replace('#javascript:#i', '', $input);
        
        // Remove on* event handlers
        $input = preg_replace('#\s*on\w+\s*=#i', '', $input);
        
        return $input;
    }
    
    public static function logSecurityEvent($event, $details = []) {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => $event,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'user_id' => $_SESSION['user_id'] ?? null,
            'details' => $details
        ];
        
        debugLog($logData, 'SECURITY');
        
        // In production, you might want to send this to a security monitoring service
        if (!DEBUG_MODE && in_array($event, ['login_failed', 'rate_limit_exceeded', 'csrf_failed'])) {
            // Send alert to security team
        }
    }
}

// Security headers
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    if (!DEBUG_MODE) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}
?>
