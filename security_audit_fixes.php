<?php
/**
 * SECURITY AUDIT AND FIXES FOR MSBCORE
 * This file contains security improvements and vulnerability fixes
 */

// Security Configuration Constants
if (!defined('SECURITY_CONSTANTS_LOADED')) {
    define('SECURITY_CONSTANTS_LOADED', true);
    
    // Session Security
    define('SESSION_TIMEOUT', 3600); // 1 hour
    define('SESSION_REGENERATE_INTERVAL', 300); // 5 minutes
    define('MAX_LOGIN_ATTEMPTS', 5);
    define('LOCKOUT_DURATION', 900); // 15 minutes
    
    // Password Security
    define('MIN_PASSWORD_LENGTH', 8);
    define('PASSWORD_REQUIRE_UPPERCASE', true);
    define('PASSWORD_REQUIRE_LOWERCASE', true);
    define('PASSWORD_REQUIRE_NUMBERS', true);
    define('PASSWORD_REQUIRE_SYMBOLS', true);
    
    // File Upload Security
    define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
    define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx']);
    define('UPLOAD_PATH', __DIR__ . '/uploads/');
    
    // Rate Limiting
    define('RATE_LIMIT_REQUESTS', 100);
    define('RATE_LIMIT_WINDOW', 3600); // 1 hour
    
    // CSRF Protection
    define('CSRF_TOKEN_LIFETIME', 3600); // 1 hour
    
    // Encryption
    if (!defined('ENCRYPTION_KEY')) {
        define('ENCRYPTION_KEY', hash('sha256', 'msbcore_encryption_key_' . $_SERVER['SERVER_NAME'] ?? 'localhost'));
    }
}

class SecurityAudit {
    
    /**
     * Initializes a secure PHP session with enhanced security settings.
     *
     * Configures session cookies to be HTTP-only, secure (if using HTTPS), strict mode, and SameSite=Strict. Starts the session if not already active, regenerates the session ID at defined intervals to prevent fixation, and enforces session timeout. Returns true if the session is valid, or false if the session has expired and was destroyed.
     *
     * @return bool True if the session is active and valid, false if the session was destroyed due to timeout.
     */
    public static function initializeSecureSession() {
        // Prevent session fixation
        if (session_status() === PHP_SESSION_NONE) {
            // Set secure session parameters
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
            ini_set('session.use_strict_mode', 1);
            ini_set('session.cookie_samesite', 'Strict');
            
            session_start();
            
            // Regenerate session ID periodically
            if (!isset($_SESSION['last_regeneration'])) {
                $_SESSION['last_regeneration'] = time();
            } elseif (time() - $_SESSION['last_regeneration'] > SESSION_REGENERATE_INTERVAL) {
                session_regenerate_id(true);
                $_SESSION['last_regeneration'] = time();
            }
            
            // Check session timeout
            if (isset($_SESSION['last_activity']) && 
                (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
                session_destroy();
                return false;
            }
            
            $_SESSION['last_activity'] = time();
        }
        
        return true;
    }
    
    /**
     * Checks whether a password meets the configured strength requirements.
     *
     * Validates the password against minimum length, uppercase, lowercase, numeric, and special character rules as defined by security constants.
     *
     * @param string $password The password to validate.
     * @return array An array of error messages for each unmet requirement; empty if the password is strong.
     */
    public static function validatePasswordStrength($password) {
        $errors = [];
        
        if (strlen($password) < MIN_PASSWORD_LENGTH) {
            $errors[] = "Password must be at least " . MIN_PASSWORD_LENGTH . " characters long";
        }
        
        if (PASSWORD_REQUIRE_UPPERCASE && !preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain at least one uppercase letter";
        }
        
        if (PASSWORD_REQUIRE_LOWERCASE && !preg_match('/[a-z]/', $password)) {
            $errors[] = "Password must contain at least one lowercase letter";
        }
        
        if (PASSWORD_REQUIRE_NUMBERS && !preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain at least one number";
        }
        
        if (PASSWORD_REQUIRE_SYMBOLS && !preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = "Password must contain at least one special character";
        }
        
        return $errors;
    }
    
    /**
     * Checks if login attempts for the given identifier are within allowed limits to prevent brute force attacks.
     *
     * Tracks failed login attempts in the session and determines if the account is currently locked out or how many attempts remain before lockout. Resets attempt count after the lockout period expires.
     *
     * @param string $identifier Unique identifier for tracking login attempts (e.g., username or IP address).
     * @return array Associative array with keys:
     *   - 'allowed' (bool): Whether login attempts are currently permitted.
     *   - 'remaining_attempts' (int): Number of attempts left before lockout.
     *   - 'locked_until' (int): Unix timestamp when lockout expires (0 if not locked).
     */
    public static function checkBruteForce($identifier) {
        $key = 'login_attempts_' . hash('sha256', $identifier);
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['count' => 0, 'last_attempt' => 0, 'locked_until' => 0];
        }
        
        $attempts = &$_SESSION[$key];
        
        // Check if account is locked
        if ($attempts['locked_until'] > time()) {
            return [
                'allowed' => false,
                'locked_until' => $attempts['locked_until'],
                'remaining_attempts' => 0
            ];
        }
        
        // Reset attempts if lockout period has passed
        if ($attempts['locked_until'] > 0 && $attempts['locked_until'] <= time()) {
            $attempts = ['count' => 0, 'last_attempt' => 0, 'locked_until' => 0];
        }
        
        return [
            'allowed' => $attempts['count'] < MAX_LOGIN_ATTEMPTS,
            'remaining_attempts' => MAX_LOGIN_ATTEMPTS - $attempts['count'],
            'locked_until' => $attempts['locked_until']
        ];
    }
    
    /**
     * Increments the failed login attempt count for the specified identifier and applies lockout if the maximum is exceeded.
     *
     * Updates session data to track failed attempts and sets a lockout period when the maximum allowed attempts are reached.
     *
     * @param string $identifier The unique identifier for tracking login attempts (e.g., username or IP address).
     */
    public static function recordFailedLogin($identifier) {
        $key = 'login_attempts_' . hash('sha256', $identifier);
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['count' => 0, 'last_attempt' => 0, 'locked_until' => 0];
        }
        
        $_SESSION[$key]['count']++;
        $_SESSION[$key]['last_attempt'] = time();
        
        if ($_SESSION[$key]['count'] >= MAX_LOGIN_ATTEMPTS) {
            $_SESSION[$key]['locked_until'] = time() + LOCKOUT_DURATION;
        }
        
        // Log security event
        error_log("Failed login attempt for: " . $identifier . " (Attempt " . $_SESSION[$key]['count'] . "/" . MAX_LOGIN_ATTEMPTS . ")");
    }
    
    /**
     * Removes stored failed login attempt data for the specified identifier from the session.
     *
     * Call this after a successful login to reset brute force tracking for the user or IP.
     */
    public static function clearLoginAttempts($identifier) {
        $key = 'login_attempts_' . hash('sha256', $identifier);
        unset($_SESSION[$key]);
    }
    
    /**
     * Validates an uploaded file for security and compliance with configured restrictions.
     *
     * Checks for upload presence, errors, file size limits, allowed extensions, MIME type, and scans file content for potentially malicious patterns. Returns an array of error messages if validation fails; an empty array indicates the file is valid.
     *
     * @param array $file The uploaded file array from $_FILES.
     * @return array List of error messages if validation fails; empty if the file is valid.
     */
    public static function validateFileUpload($file) {
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
        if ($file['size'] > MAX_FILE_SIZE) {
            $errors[] = 'File too large. Maximum size: ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB';
        }
        
        // Check file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ALLOWED_EXTENSIONS)) {
            $errors[] = 'Invalid file type. Allowed: ' . implode(', ', ALLOWED_EXTENSIONS);
        }
        
        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        $allowedMimes = [
            'image/jpeg', 'image/png', 'image/gif',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];
        
        if (!in_array($mimeType, $allowedMimes)) {
            $errors[] = 'Invalid file format detected';
        }
        
        // Check for malicious content
        $content = file_get_contents($file['tmp_name']);
        if (preg_match('/<script|javascript:|vbscript:|onload=|onerror=/i', $content)) {
            $errors[] = 'Potentially malicious content detected';
        }
        
        return $errors;
    }
    
    /**
     * Generates a secure, unique filename by combining the current timestamp and a random string, preserving the original file extension.
     *
     * @param string $originalFilename The original name of the uploaded file.
     * @return string The generated secure filename.
     */
    public static function generateSecureFilename($originalFilename) {
        $extension = pathinfo($originalFilename, PATHINFO_EXTENSION);
        $timestamp = time();
        $random = bin2hex(random_bytes(16));
        
        return sprintf('%s_%s.%s', $timestamp, $random, $extension);
    }
    
    /**
     * Recursively sanitizes input by removing null bytes and stripping dangerous SQL keywords to mitigate SQL injection risks.
     *
     * @param mixed $input The input string or array to sanitize.
     * @return mixed The sanitized input with potentially harmful SQL elements removed.
     */
    public static function sanitizeForSQL($input) {
        if (is_array($input)) {
            return array_map([self::class, 'sanitizeForSQL'], $input);
        }
        
        // Remove null bytes
        $input = str_replace(" ", '', $input);
        
        // Remove or escape dangerous SQL keywords
        $dangerous = ['UNION', 'SELECT', 'INSERT', 'UPDATE', 'DELETE', 'DROP', 'CREATE', 'ALTER'];
        foreach ($dangerous as $keyword) {
            $input = preg_replace('/\b' . $keyword . '\b/i', '', $input);
        }
        
        return trim($input);
    }
    
    /**
     * Sanitizes input to prevent cross-site scripting (XSS) attacks.
     *
     * Recursively removes dangerous HTML tags, scripting protocols, and event handlers from the input, then encodes remaining HTML entities.
     *
     * @param mixed $input The input string or array to sanitize.
     * @return mixed The sanitized input with XSS vectors removed.
     */
    public static function preventXSS($input) {
        if (is_array($input)) {
            return array_map([self::class, 'preventXSS'], $input);
        }
        
        // Remove potentially dangerous tags
        $input = preg_replace('#<script[^>]*>.*?</script>#is', '', $input);
        $input = preg_replace('#<iframe[^>]*>.*?</iframe>#is', '', $input);
        $input = preg_replace('#<object[^>]*>.*?</object>#is', '', $input);
        $input = preg_replace('#<embed[^>]*>.*?</embed>#is', '', $input);
        $input = preg_replace('#<link[^>]*>#is', '', $input);
        $input = preg_replace('#<meta[^>]*>#is', '', $input);
        
        // Remove javascript: protocol
        $input = preg_replace('#javascript:#i', '', $input);
        $input = preg_replace('#vbscript:#i', '', $input);
        $input = preg_replace('#data:#i', '', $input);
        
        // Remove on* event handlers
        $input = preg_replace('#\s*on\w+\s*=#i', '', $input);
        
        // Encode remaining HTML entities
        return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    /**
     * Generates and stores a cryptographically secure CSRF token in the session, creating a new token if none exists or if the current token has expired.
     *
     * @return string The current valid CSRF token.
     */
    public static function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token']) || 
            !isset($_SESSION['csrf_token_time']) ||
            (time() - $_SESSION['csrf_token_time']) > CSRF_TOKEN_LIFETIME) {
            
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = time();
        }
        
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Validates a CSRF token against the session token and checks for expiration.
     *
     * @param string $token The CSRF token to validate.
     * @return bool True if the token is valid and not expired, false otherwise.
     */
    public static function validateCSRFToken($token) {
        if (!isset($_SESSION['csrf_token']) || 
            !isset($_SESSION['csrf_token_time'])) {
            return false;
        }
        
        // Check if token has expired
        if ((time() - $_SESSION['csrf_token_time']) > CSRF_TOKEN_LIFETIME) {
            unset($_SESSION['csrf_token'], $_SESSION['csrf_token_time']);
            return false;
        }
        
        // Use hash_equals to prevent timing attacks
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Checks and enforces rate limiting for a given identifier within a specified time window.
     *
     * Tracks the number of requests made by the identifier (such as a user or IP address) in the current time window. Returns whether further requests are allowed, how many requests remain, and when the rate limit will reset.
     *
     * @param string $identifier Unique key to track rate limiting (e.g., user ID or IP address).
     * @param int $maxRequests Maximum number of allowed requests in the time window.
     * @param int $timeWindow Duration of the rate limit window in seconds.
     * @return array{
     *     allowed: bool,
     *     remaining: int,
     *     reset_time: int
     * } Associative array indicating if the request is allowed, remaining requests, and the reset timestamp.
     */
    public static function checkRateLimit($identifier, $maxRequests = RATE_LIMIT_REQUESTS, $timeWindow = RATE_LIMIT_WINDOW) {
        $key = 'rate_limit_' . hash('sha256', $identifier);
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['count' => 0, 'window_start' => time()];
        }
        
        $rateData = &$_SESSION[$key];
        
        // Reset if time window has passed
        if (time() - $rateData['window_start'] > $timeWindow) {
            $rateData = ['count' => 0, 'window_start' => time()];
        }
        
        $rateData['count']++;
        
        $allowed = $rateData['count'] <= $maxRequests;
        
        if (!$allowed) {
            error_log("Rate limit exceeded for: " . $identifier);
        }
        
        return [
            'allowed' => $allowed,
            'remaining' => max(0, $maxRequests - $rateData['count']),
            'reset_time' => $rateData['window_start'] + $timeWindow
        ];
    }
    
    /**
     * Sends HTTP security headers to enhance browser protection.
     *
     * Sets headers to prevent MIME type sniffing, clickjacking, and cross-site scripting, enforces strict referrer and content security policies, and applies HTTP Strict Transport Security (HSTS) when HTTPS is enabled.
     */
    public static function setSecurityHeaders() {
        if (!headers_sent()) {
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: DENY');
            header('X-XSS-Protection: 1; mode=block');
            header('Referrer-Policy: strict-origin-when-cross-origin');
            header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:;");
            
            // Only set HSTS in production with HTTPS
            if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
                header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
            }
        }
    }
    
    /**
     * Logs a security-related event with contextual metadata.
     *
     * Records the event type, timestamp, client IP, user agent, user ID (if available), and additional details to the error log. Recognizes critical events for potential alerting.
     *
     * @param string $event The type or name of the security event.
     * @param array $details Optional additional information about the event.
     */
    public static function logSecurityEvent($event, $details = []) {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => $event,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'user_id' => $_SESSION['user_id'] ?? null,
            'details' => $details
        ];
        
        $logMessage = json_encode($logData);
        error_log("[SECURITY] " . $logMessage);
        
        // In production, you might want to send critical events to a monitoring service
        $criticalEvents = ['sql_injection_attempt', 'xss_attempt', 'brute_force_attack', 'unauthorized_access'];
        if (in_array($event, $criticalEvents)) {
            // Send alert to security team
            // mail('security@yourcompany.com', 'Security Alert', $logMessage);
        }
    }
}

// Initialize security on every request
SecurityAudit::initializeSecureSession();
SecurityAudit::setSecurityHeaders();
?>
