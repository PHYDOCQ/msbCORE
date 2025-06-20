<?php
// Application Configuration
define('APP_NAME', 'Bengkel Management Pro');
define('APP_VERSION', '3.1.0');

// Safe URL detection
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$path = dirname($_SERVER['SCRIPT_NAME'] ?? '') . '/';
define('APP_URL', $protocol . $host . $path);

// Environment Detection
$serverName = $_SERVER['SERVER_NAME'] ?? 'localhost';
define('APP_ENV', $serverName === 'localhost' ? 'development' : 'production');
define('DEBUG_MODE', APP_ENV === 'development');

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'msb_bengkel');
define('DB_USER', 'root');
define('DB_PASS', '');

// Security Configuration
define('ENCRYPTION_KEY', hash('sha256', 'bengkel_management_secret_key_2024'));
define('JWT_SECRET', hash('sha256', 'jwt_secret_key_bengkel_2024'));
define('BCRYPT_ROUNDS', 12);
define('SESSION_LIFETIME', 3600); // 1 hour

// File Upload Configuration
define('UPLOAD_MAX_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_IMAGE_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('ALLOWED_DOCUMENT_EXTENSIONS', ['pdf', 'doc', 'docx', 'txt']);

// Email Configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your-email@gmail.com');
define('SMTP_PASS', 'your-app-password');
define('FROM_EMAIL', 'system@bengkel.com');
define('FROM_NAME', APP_NAME);

// Pagination Configuration
define('RECORDS_PER_PAGE', 20);
define('MAX_PAGINATION_LINKS', 5);

// Rate Limiting
define('RATE_LIMIT_REQUESTS', 100);
define('RATE_LIMIT_WINDOW', 3600); // 1 hour

// Session Configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 1 : 0);
ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
ini_set('session.cookie_samesite', 'Strict');

// Error Reporting
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// Timezone
date_default_timezone_set('Asia/Jakarta');

// Auto-create directories
$directories = [
    __DIR__ . '/../assets/uploads/work_orders',
    __DIR__ . '/../assets/uploads/vehicles',
    __DIR__ . '/../assets/uploads/profiles',
    __DIR__ . '/../assets/temp',
    __DIR__ . '/../exports/reports',
    __DIR__ . '/../logs'
];

foreach($directories as $dir) {
    if(!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Debug Helper Function
function debugLog($data, $label = 'DEBUG') {
    if (DEBUG_MODE) {
        $timestamp = date('Y-m-d H:i:s');
        $message = "[$timestamp] $label: " . (is_array($data) || is_object($data) ? json_encode($data, JSON_PRETTY_PRINT) : $data) . PHP_EOL;
        file_put_contents(__DIR__ . '/../logs/debug.log', $message, FILE_APPEND);
    }
}

// Session start with error handling
if (session_status() === PHP_SESSION_NONE) {
    if (!session_start()) {
        debugLog('Session start failed', 'ERROR');
        die('Session initialization failed');
    }
}
?>
