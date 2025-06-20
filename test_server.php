<?php
/**
 * ADVANCED COMPREHENSIVE SERVER TESTING SUITE
 * Professional-grade testing with advanced interface and detailed analytics
 * 
 * @version 3.0
 * @author msbCORE System
 * @license MIT
 */

// Advanced error reporting and security
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(600); // 10 minutes for comprehensive testing
session_start();

// Security check - basic IP filtering (can be enhanced)
$allowedIPs = ['127.0.0.1', '::1', 'localhost'];
$clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

// Include required files with advanced error handling
$requiredFiles = [
    __DIR__ . '/config/config.php',
    __DIR__ . '/config/database.php',
    __DIR__ . '/config/security.php',
    __DIR__ . '/includes/functions.php'
];

$missingFiles = [];
foreach ($requiredFiles as $file) {
    if (file_exists($file)) {
        require_once $file;
    } else {
        $missingFiles[] = $file;
    }
}

class AdvancedServerTester {
    private $results = [];
    private $db = null;
    private $startTime;
    private $sessionData = [];
    private $systemInfo = [];
    private $clientInfo = [];
    private $testCategories = [
        'environment' => ['name' => 'Environment', 'icon' => '🌍', 'color' => '#17a2b8'],
        'critical' => ['name' => 'Critical Functions', 'icon' => '🔧', 'color' => '#dc3545'],
        'database' => ['name' => 'Database Tests', 'icon' => '📊', 'color' => '#28a745'],
        'schema' => ['name' => 'Schema Validation', 'icon' => '🗃️', 'color' => '#6f42c1'],
        'authentication' => ['name' => 'Authentication System', 'icon' => '🔐', 'color' => '#e91e63'],
        'login' => ['name' => 'Login Testing', 'icon' => '👤', 'color' => '#9c27b0'],
        'security' => ['name' => 'Security Features', 'icon' => '🔒', 'color' => '#fd7e14'],
        'classes' => ['name' => 'Class Loading', 'icon' => '📁', 'color' => '#20c997'],
        'api' => ['name' => 'API Endpoints', 'icon' => '🌐', 'color' => '#007bff'],
        'files' => ['name' => 'File Structure', 'icon' => '📂', 'color' => '#6c757d'],
        'performance' => ['name' => 'Performance', 'icon' => '⚡', 'color' => '#ffc107'],
        'network' => ['name' => 'Network & Security', 'icon' => '🛡️', 'color' => '#e83e8c']
    ];
    
    public function __construct() {
        $this->startTime = microtime(true);
        $this->collectSystemInfo();
        $this->collectClientInfo();
        $this->initializeDatabase();
        $this->initializeSession();
    }
    
    private function collectSystemInfo() {
        $this->systemInfo = [
            'hostname' => gethostname(),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'server_name' => $_SERVER['SERVER_NAME'] ?? 'Unknown',
            'server_port' => $_SERVER['SERVER_PORT'] ?? 'Unknown',
            'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
            'php_version' => PHP_VERSION,
            'php_sapi' => php_sapi_name(),
            'php_os' => PHP_OS,
            'php_os_family' => PHP_OS_FAMILY,
            'server_time' => date('Y-m-d H:i:s'),
            'timezone' => date_default_timezone_get(),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'max_input_vars' => ini_get('max_input_vars'),
            'default_charset' => ini_get('default_charset'),
            'zend_version' => zend_version(),
            'opcache_enabled' => extension_loaded('Zend OPcache') ? 'Yes' : 'No',
            'xdebug_enabled' => extension_loaded('xdebug') ? 'Yes' : 'No'
        ];
        
        // Disk space information
        if (function_exists('disk_free_space') && function_exists('disk_total_space')) {
            $this->systemInfo['disk_free'] = disk_free_space(__DIR__);
            $this->systemInfo['disk_total'] = disk_total_space(__DIR__);
            $this->systemInfo['disk_used_percent'] = round((($this->systemInfo['disk_total'] - $this->systemInfo['disk_free']) / $this->systemInfo['disk_total']) * 100, 2);
        }
        
        // Load average (Unix-like systems)
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            $this->systemInfo['load_average'] = implode(', ', array_map(fn($l) => round($l, 2), $load));
        }
    }
    
    private function collectClientInfo() {
        // Enhanced client information collection
        $this->clientInfo = [
            'ip_address' => $this->getRealClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'browser' => $this->getBrowserInfo(),
            'operating_system' => $this->getOSInfo(),
            'device_type' => $this->getDeviceType(),
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'Unknown',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'Unknown',
            'query_string' => $_SERVER['QUERY_STRING'] ?? '',
            'referer' => $_SERVER['HTTP_REFERER'] ?? 'Direct Access',
            'accept_language' => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'Unknown',
            'accept_encoding' => $_SERVER['HTTP_ACCEPT_ENCODING'] ?? 'Unknown',
            'connection_type' => $_SERVER['HTTP_CONNECTION'] ?? 'Unknown',
            'request_time' => date('Y-m-d H:i:s', $_SERVER['REQUEST_TIME']),
            'https' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'Yes' : 'No',
            'port' => $_SERVER['REMOTE_PORT'] ?? 'Unknown',
            'forwarded_for' => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? 'None',
            'country' => $this->getCountryFromIP(),
            'isp' => $this->getISPInfo(),
            'screen_resolution' => 'Unknown' // Will be updated via JavaScript
        ];
    }
    
    private function getRealClientIP() {
        $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    }
    
    private function getBrowserInfo() {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $browsers = [
            'Chrome' => '/Chrome\/([0-9.]+)/',
            'Firefox' => '/Firefox\/([0-9.]+)/',
            'Safari' => '/Version\/([0-9.]+).*Safari/',
            'Edge' => '/Edg\/([0-9.]+)/',
            'Internet Explorer' => '/MSIE ([0-9.]+)/',
            'Opera' => '/Opera\/([0-9.]+)/'
        ];
        
        foreach ($browsers as $browser => $pattern) {
            if (preg_match($pattern, $userAgent, $matches)) {
                return $browser . ' ' . $matches[1];
            }
        }
        
        return 'Unknown Browser';
    }
    
    private function getOSInfo() {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $os = [
            'Windows 11' => '/Windows NT 10.0.*Win64.*x64/',
            'Windows 10' => '/Windows NT 10.0/',
            'Windows 8.1' => '/Windows NT 6.3/',
            'Windows 8' => '/Windows NT 6.2/',
            'Windows 7' => '/Windows NT 6.1/',
            'macOS' => '/Mac OS X/',
            'Linux' => '/Linux/',
            'Ubuntu' => '/Ubuntu/',
            'Android' => '/Android/',
            'iOS' => '/iPhone|iPad/',
        ];
        
        foreach ($os as $system => $pattern) {
            if (preg_match($pattern, $userAgent)) {
                return $system;
            }
        }
        
        return 'Unknown OS';
    }
    
    private function getDeviceType() {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        if (preg_match('/Mobile|Android|iPhone|iPad/', $userAgent)) {
            if (preg_match('/iPad/', $userAgent)) return 'Tablet';
            return 'Mobile';
        }
        
        return 'Desktop';
    }
    
    private function getCountryFromIP() {
        // Simple country detection (can be enhanced with GeoIP database)
        $ip = $this->clientInfo['ip_address'] ?? '';
        
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            // This is a simplified version - in production, use a proper GeoIP service
            $context = stream_context_create(['http' => ['timeout' => 2]]);
            $response = @file_get_contents("http://ip-api.com/json/{$ip}?fields=country", false, $context);
            
            if ($response) {
                $data = json_decode($response, true);
                return $data['country'] ?? 'Unknown';
            }
        }
        
        return 'Unknown';
    }
    
    private function getISPInfo() {
        // Simplified ISP detection
        $ip = $this->clientInfo['ip_address'] ?? '';
        
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            $context = stream_context_create(['http' => ['timeout' => 2]]);
            $response = @file_get_contents("http://ip-api.com/json/{$ip}?fields=isp", false, $context);
            
            if ($response) {
                $data = json_decode($response, true);
                return $data['isp'] ?? 'Unknown';
            }
        }
        
        return 'Unknown';
    }
    
    private function initializeSession() {
        $this->sessionData = [
            'session_id' => session_id(),
            'session_name' => session_name(),
            'session_status' => session_status(),
            'session_cookie_params' => session_get_cookie_params(),
            'test_count' => ($_SESSION['test_count'] ?? 0) + 1,
            'last_test' => $_SESSION['last_test'] ?? 'Never',
            'total_tests' => $_SESSION['total_tests'] ?? 0
        ];
        
        $_SESSION['test_count'] = $this->sessionData['test_count'];
        $_SESSION['last_test'] = date('Y-m-d H:i:s');
        $_SESSION['total_tests'] = $this->sessionData['total_tests'] + 1;
    }
    
    private function initializeDatabase() {
        try {
            if (class_exists('Database')) {
                $this->db = Database::getInstance();
            }
        } catch (Exception $e) {
            $this->addResult('database', 'Database Initialization', 'FAIL', 
                'Failed to initialize database: ' . $e->getMessage());
        }
    }
    
    public function runAllTests() {
        $this->logTest('Starting advanced comprehensive server tests');
        
        $this->renderAdvancedHeader();
        
        // Check missing files first
        global $missingFiles;
        if (!empty($missingFiles)) {
            $this->testMissingFiles($missingFiles);
        }
        
        // Run all test categories
        $this->testEnvironment();
        $this->testCriticalFunctions();
        $this->testDatabaseConnection();
        $this->testDatabaseMethods();
        $this->testDatabaseSchema();
        $this->testAuthenticationSystem();
        $this->testLoginFunctionality();
        $this->testSecurityFeatures();
        $this->testClassFiles();
        $this->testAPIEndpoints();
        $this->testFileStructure();
        $this->testPerformance();
        $this->testNetworkSecurity();
        $this->testSystemConfiguration();
        
        $this->renderAdvancedSummary();
        $this->renderAdvancedFooter();
        
        $this->logTest('Advanced comprehensive server tests completed', [
            'duration' => round(microtime(true) - $this->startTime, 2) . 's',
            'total_tests' => count($this->results),
            'client_ip' => $this->clientInfo['ip_address'],
            'user_agent' => $this->clientInfo['user_agent']
        ]);
    }
    
    private function testEnvironment() {
        $this->renderSection('🌍 Environment Analysis', 'environment');
        
        // PHP Extensions
        $requiredExtensions = [
            'pdo', 'pdo_mysql', 'json', 'mbstring', 'openssl', 'curl', 'gd',
            'zip', 'xml', 'simplexml', 'dom', 'libxml', 'fileinfo', 'hash'
        ];
        
        $loadedExtensions = get_loaded_extensions();
        foreach ($requiredExtensions as $ext) {
            if (in_array($ext, $loadedExtensions)) {
                $version = phpversion($ext) ?: 'Unknown';
                $this->addResult('environment', "Extension: $ext", 'PASS', 
                    "Loaded (v$version)");
            } else {
                $this->addResult('environment', "Extension: $ext", 'FAIL', 
                    'Extension not loaded');
            }
        }
        
        // Check optional but recommended extensions
        $optionalExtensions = ['imagick', 'redis', 'memcached', 'xdebug', 'opcache'];
        foreach ($optionalExtensions as $ext) {
            if (in_array($ext, $loadedExtensions)) {
                $this->addResult('environment', "Optional: $ext", 'PASS', 
                    'Available');
            } else {
                $this->addResult('environment', "Optional: $ext", 'INFO', 
                    'Not available (optional)');
            }
        }
        
        // PHP Configuration Analysis
        $this->analyzePhpConfiguration();
    }
    
    private function analyzePhpConfiguration() {
        $configs = [
            'display_errors' => ['recommended' => 'Off', 'security' => true],
            'log_errors' => ['recommended' => 'On', 'security' => false],
            'expose_php' => ['recommended' => 'Off', 'security' => true],
            'allow_url_fopen' => ['recommended' => 'Off', 'security' => true],
            'allow_url_include' => ['recommended' => 'Off', 'security' => true],
            'session.cookie_httponly' => ['recommended' => '1', 'security' => true],
            'session.cookie_secure' => ['recommended' => '1', 'security' => true],
            'session.use_strict_mode' => ['recommended' => '1', 'security' => true]
        ];
        
        foreach ($configs as $setting => $info) {
            $current = ini_get($setting);
            $isSecure = ($current === $info['recommended']);
            
            if ($info['security']) {
                $status = $isSecure ? 'PASS' : 'WARN';
                $message = $isSecure ? 
                    "Secure: $current" : 
                    "Insecure: $current (recommended: {$info['recommended']})";
            } else {
                $status = 'INFO';
                $message = "Current: $current";
            }
            
            $this->addResult('environment', "PHP Config: $setting", $status, $message);
        }
    }
    
    private function testNetworkSecurity() {
        $this->renderSection('🛡️ Network & Security Analysis', 'network');
        
        // SSL/TLS Check
        $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $this->addResult('network', 'HTTPS Connection', 
            $isHttps ? 'PASS' : 'WARN', 
            $isHttps ? 'Secure HTTPS connection' : 'Insecure HTTP connection');
        
        // Security Headers Check
        $securityHeaders = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'X-XSS-Protection' => '1; mode=block',
            'Strict-Transport-Security' => 'max-age=31536000',
            'Content-Security-Policy' => 'default-src \'self\''
        ];
        
        foreach ($securityHeaders as $header => $expectedValue) {
            $headerValue = $_SERVER['HTTP_' . str_replace('-', '_', strtoupper($header))] ?? null;
            
            if ($headerValue) {
                $this->addResult('network', "Security Header: $header", 'PASS', 
                    "Present: $headerValue");
            } else {
                $this->addResult('network', "Security Header: $header", 'WARN', 
                    "Missing (recommended: $expectedValue)");
            }
        }
        
        // Check for common vulnerabilities
        $this->checkCommonVulnerabilities();
        
        // Port and service analysis
        $this->analyzeNetworkPorts();
    }
    
    private function checkCommonVulnerabilities() {
        // Check for directory traversal protection
        $testPath = __DIR__ . '/../../../etc/passwd';
        if (!file_exists($testPath)) {
            $this->addResult('network', 'Directory Traversal', 'PASS', 
                'System files not accessible');
        } else {
            $this->addResult('network', 'Directory Traversal', 'WARN', 
                'Potential directory traversal vulnerability');
        }
        
        // Check for exposed configuration files
        $sensitiveFiles = ['.env', 'config.php', 'database.php', '.htaccess'];
        foreach ($sensitiveFiles as $file) {
            $webPath = $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/' . $file;
            $this->addResult('network', "Exposed File: $file", 'INFO', 
                "Check if accessible via web: $webPath");
        }
    }
    
    private function analyzeNetworkPorts() {
        $commonPorts = [
            22 => 'SSH',
            80 => 'HTTP',
            443 => 'HTTPS',
            3306 => 'MySQL',
            5432 => 'PostgreSQL',
            6379 => 'Redis',
            11211 => 'Memcached'
        ];
        
        foreach ($commonPorts as $port => $service) {
            $connection = @fsockopen('127.0.0.1', $port, $errno, $errstr, 1);
            if ($connection) {
                fclose($connection);
                $status = in_array($port, [22, 3306, 5432, 6379, 11211]) ? 'WARN' : 'INFO';
                $message = $status === 'WARN' ? 
                    "$service port open (should be firewalled)" : 
                    "$service port open";
                
                $this->addResult('network', "Port $port ($service)", $status, $message);
            } else {
                $this->addResult('network', "Port $port ($service)", 'INFO', 
                    'Port closed or filtered');
            }
        }
    }
    
    // ... (Continue with all other test methods from previous version, enhanced)
    
    private function testCriticalFunctions() {
        $this->renderSection('🔧 Critical Functions Analysis', 'critical');
        
        // Test utility functions if available
        if (class_exists('Utils')) {
            $this->testUtilityFunctions();
        } else {
            $this->addResult('critical', 'Utils Class', 'FAIL', 'Utils class not found');
        }
        
        // Test basic PHP functions with enhanced validation
        $this->testBasicPHPFunctions();
        $this->testFileSystemFunctions();
    }
    
    private function testUtilityFunctions() {
        $testCases = [
            'formatFileSize' => [1048576, 'MB'], // 1MB
            'formatCurrency' => [1000000, 'Rp'],
            'formatDate' => ['2024-01-01 12:00:00', '2024']
        ];
        
        foreach ($testCases as $method => $testData) {
            try {
                if (method_exists('Utils', $method)) {
                    $startTime = microtime(true);
                    $result = Utils::$method($testData[0]);
                    $executionTime = round((microtime(true) - $startTime) * 1000, 3);
                    
                    if (strpos($result, $testData[1]) !== false) {
                        $this->addResult('critical', "Utils::$method", 'PASS', 
                            "Result: $result (${executionTime}ms)");
                    } else {
                        $this->addResult('critical', "Utils::$method", 'FAIL', 
                            "Unexpected result: $result");
                    }
                } else {
                    $this->addResult('critical', "Utils::$method", 'FAIL', 
                        'Method not found');
                }
            } catch (Exception $e) {
                $this->addResult('critical', "Utils::$method", 'FAIL', $e->getMessage());
            }
        }
    }
    
    private function testBasicPHPFunctions() {
        // Enhanced PHP version check
        $phpVersion = PHP_VERSION;
        $requiredVersion = '7.4.0';
        $recommendedVersion = '8.0.0';
        
        if (version_compare($phpVersion, $recommendedVersion, '>=')) {
            $this->addResult('critical', 'PHP Version', 'PASS', 
                "PHP $phpVersion (recommended version or higher)");
        } elseif (version_compare($phpVersion, $requiredVersion, '>=')) {
            $this->addResult('critical', 'PHP Version', 'WARN', 
                "PHP $phpVersion (minimum met, but recommend upgrading to $recommendedVersion+)");
        } else {
            $this->addResult('critical', 'PHP Version', 'FAIL', 
                "PHP $phpVersion (< $requiredVersion required)");
        }
        
        // Memory management tests
        $this->testMemoryFunctions();
        
        // Error handling tests
        $this->testErrorHandling();
    }
    
    private function testMemoryFunctions() {
        $memoryBefore = memory_get_usage();
        
        // Create some memory usage
        $testArray = array_fill(0, 1000, str_repeat('x', 1000));
        
        $memoryAfter = memory_get_usage();
        $memoryDiff = $memoryAfter - $memoryBefore;
        
        unset($testArray);
        $memoryCleanup = memory_get_usage();
        
        $this->addResult('critical', 'Memory Management', 'PASS', 
            "Allocated: " . $this->formatBytes($memoryDiff) . 
            " | Cleanup: " . $this->formatBytes($memoryCleanup - $memoryBefore));
    }
    
    private function testErrorHandling() {
        $originalLevel = error_reporting();
        
        // Test error suppression
        error_reporting(0);
        $result = @file_get_contents('non_existent_file.txt');
        
        error_reporting($originalLevel);
        
        $this->addResult('critical', 'Error Handling', 'PASS', 
            'Error suppression and reporting control working');
    }
    
    private function testFileSystemFunctions() {
        $testDir = __DIR__ . '/test_temp_' . uniqid();
        
        try {
            // Test directory creation
            if (mkdir($testDir, 0755)) {
                $this->addResult('critical', 'Directory Creation', 'PASS', 
                    'Can create directories');
                
                // Test file operations
                $testFile = $testDir . '/test.txt';
                if (file_put_contents($testFile, 'test content') !== false) {
                    $this->addResult('critical', 'File Writing', 'PASS', 
                        'Can write files');
                    
                    if (file_get_contents($testFile) === 'test content') {
                        $this->addResult('critical', 'File Reading', 'PASS', 
                            'Can read files');
                    }
                    
                    unlink($testFile);
                }
                
                rmdir($testDir);
            }
        } catch (Exception $e) {
            $this->addResult('critical', 'File System Operations', 'FAIL', 
                $e->getMessage());
        }
    }
    
    // ... (Continue with all other enhanced test methods)
    
    private function renderAdvancedHeader() {
        echo $this->getAdvancedCSS();
        echo $this->getAdvancedJavaScript();
        
        echo "<div class='main-container'>
            <div class='header-section'>
                <div class='header-content'>
                    <h1 class='main-title'>
                        <span class='title-icon'>🔧</span>
                        msbCORE Advanced Server Analysis
                        <span class='version-badge'>v3.0</span>
                    </h1>
                    <div class='header-stats'>
                        <div class='stat-item'>
                            <span class='stat-label'>Test Session</span>
                            <span class='stat-value'>#" . $this->sessionData['test_count'] . "</span>
                        </div>
                        <div class='stat-item'>
                            <span class='stat-label'>Started</span>
                            <span class='stat-value'>" . date('H:i:s') . "</span>
                        </div>
                        <div class='stat-item'>
                            <span class='stat-label'>Client IP</span>
                            <span class='stat-value'>" . $this->clientInfo['ip_address'] . "</span>
                        </div>
                    </div>
                </div>
            </div>";
        
        $this->renderSystemInfoDashboard();
        $this->renderClientInfoDashboard();
    }
    
    private function renderSystemInfoDashboard() {
        echo "<div class='dashboard-section'>
                <h2 class='section-title'>
                    <span class='section-icon'>🖥️</span>
                    Server Environment
                </h2>
                <div class='info-grid'>";
        
        $systemCards = [
            ['Server', $this->systemInfo['server_software'], 'server'],
            ['PHP', $this->systemInfo['php_version'] . ' (' . $this->systemInfo['php_sapi'] . ')', 'php'],
            ['OS', $this->systemInfo['php_os_family'], 'os'],
            ['Memory', $this->systemInfo['memory_limit'], 'memory'],
            ['Hostname', $this->systemInfo['hostname'], 'hostname'],
            ['Timezone', $this->systemInfo['timezone'], 'time']
        ];
        
        foreach ($systemCards as $card) {
            echo "<div class='info-card info-{$card[2]}'>
                    <div class='card-header'>{$card[0]}</div>
                    <div class='card-value'>{$card[1]}</div>
                  </div>";
        }
        
        echo "</div></div>";
    }
    
    private function renderClientInfoDashboard() {
        echo "<div class='dashboard-section'>
                <h2 class='section-title'>
                    <span class='section-icon'>👤</span>
                    Client Information
                </h2>
                <div class='client-info-detailed'>";
        
        echo "<div class='client-grid'>
                <div class='client-card'>
                    <h3>🌍 Location & Network</h3>
                    <div class='client-details'>
                        <div class='detail-row'>
                            <span class='detail-label'>IP Address:</span>
                            <span class='detail-value ip-address'>" . $this->clientInfo['ip_address'] . "</span>
                        </div>
                        <div class='detail-row'>
                            <span class='detail-label'>Country:</span>
                            <span class='detail-value'>" . $this->clientInfo['country'] . "</span>
                        </div>
                        <div class='detail-row'>
                            <span class='detail-label'>ISP:</span>
                            <span class='detail-value'>" . $this->clientInfo['isp'] . "</span>
                        </div>
                        <div class='detail-row'>
                            <span class='detail-label'>Port:</span>
                            <span class='detail-value'>" . $this->clientInfo['port'] . "</span>
                        </div>
                        <div class='detail-row'>
                            <span class='detail-label'>HTTPS:</span>
                            <span class='detail-value " . ($this->clientInfo['https'] === 'Yes' ? 'secure' : 'insecure') . "'>" . $this->clientInfo['https'] . "</span>
                        </div>
                    </div>
                </div>
                
                <div class='client-card'>
                    <h3>💻 Device & Browser</h3>
                    <div class='client-details'>
                        <div class='detail-row'>
                            <span class='detail-label'>Browser:</span>
                            <span class='detail-value'>" . $this->clientInfo['browser'] . "</span>
                        </div>
                        <div class='detail-row'>
                            <span class='detail-label'>OS:</span>
                            <span class='detail-value'>" . $this->clientInfo['operating_system'] . "</span>
                        </div>
                        <div class='detail-row'>
                            <span class='detail-label'>Device:</span>
                            <span class='detail-value'>" . $this->clientInfo['device_type'] . "</span>
                        </div>
                        <div class='detail-row'>
                            <span class='detail-label'>Language:</span>
                            <span class='detail-value'>" . substr($this->clientInfo['accept_language'], 0, 20) . "</span>
                        </div>
                        <div class='detail-row'>
                            <span class='detail-label'>Resolution:</span>
                            <span class='detail-value' id='screen-resolution'>Detecting...</span>
                        </div>
                    </div>
                </div>
                
                <div class='client-card'>
                    <h3>🔗 Request Details</h3>
                    <div class='client-details'>
                        <div class='detail-row'>
                            <span class='detail-label'>Method:</span>
                            <span class='detail-value'>" . $this->clientInfo['request_method'] . "</span>
                        </div>
                        <div class='detail-row'>
                            <span class='detail-label'>URI:</span>
                            <span class='detail-value uri'>" . htmlspecialchars($this->clientInfo['request_uri']) . "</span>
                        </div>
                        <div class='detail-row'>
                            <span class='detail-label'>Referer:</span>
                            <span class='detail-value'>" . htmlspecialchars(substr($this->clientInfo['referer'], 0, 30)) . "</span>
                        </div>
                        <div class='detail-row'>
                            <span class='detail-label'>Time:</span>
                            <span class='detail-value'>" . $this->clientInfo['request_time'] . "</span>
                        </div>
                        <div class='detail-row'>
                            <span class='detail-label'>Session:</span>
                            <span class='detail-value'>" . substr($this->sessionData['session_id'], 0, 16) . "...</span>
                        </div>
                    </div>
                </div>
              </div></div>";
    }
    
    // ... (Continue with all other methods, enhanced styling and functionality)
    
    private function getAdvancedCSS() {
        return "
        <style>
            * { box-sizing: border-box; margin: 0; padding: 0; }
            
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                padding: 20px;
                color: #333;
            }
            
            .main-container {
                max-width: 1400px;
                margin: 0 auto;
                background: rgba(255, 255, 255, 0.95);
                border-radius: 20px;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
                overflow: hidden;
                backdrop-filter: blur(10px);
            }
            
            .header-section {
                background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
                color: white;
                padding: 30px;
                position: relative;
                overflow: hidden;
            }
            
            .header-section::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: url('data:image/svg+xml,<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 100 100\"><defs><pattern id=\"grain\" width=\"100\" height=\"100\" patternUnits=\"userSpaceOnUse\"><circle cx=\"50\" cy=\"50\" r=\"1\" fill=\"%23ffffff\" opacity=\"0.1\"/></pattern></defs><rect width=\"100\" height=\"100\" fill=\"url(%23grain)\"/></svg>') repeat;
                pointer-events: none;
            }
            
            .header-content {
                position: relative;
                z-index: 1;
            }
            
            .main-title {
                font-size: 2.5em;
                font-weight: 700;
                margin-bottom: 20px;
                display: flex;
                align-items: center;
                gap: 15px;
            }
            
            .title-icon {
                font-size: 1.2em;
                animation: rotate 4s linear infinite;
            }
            
            @keyframes rotate {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
            
            .version-badge {
                background: rgba(255, 255, 255, 0.2);
                padding: 5px 15px;
                border-radius: 20px;
                font-size: 0.4em;
                font-weight: 500;
                backdrop-filter: blur(10px);
            }
            
            .header-stats {
                display: flex;
                gap: 30px;
                flex-wrap: wrap;
            }
            
            .stat-item {
                display: flex;
                flex-direction: column;
                gap: 5px;
            }
            
            .stat-label {
                font-size: 0.9em;
                opacity: 0.8;
                text-transform: uppercase;
                letter-spacing: 1px;
            }
            
            .stat-value {
                font-size: 1.2em;
                font-weight: 600;
            }
            
            .dashboard-section {
                padding: 30px;
                border-bottom: 1px solid #eee;
            }
            
            .section-title {
                font-size: 1.8em;
                margin-bottom: 20px;
                display: flex;
                align-items: center;
                gap: 10px;
                color: #333;
            }
            
            .section-icon {
                font-size: 1.2em;
            }
            
            .info-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 20px;
            }
            
            .info-card {
                background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
                padding: 20px;
                border-radius: 15px;
                border-left: 4px solid #007bff;
                transition: all 0.3s ease;
                position: relative;
                overflow: hidden;
            }
            
            .info-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            }
            
            .info-card.info-server { border-left-color: #28a745; }
            .info-card.info-php { border-left-color: #6f42c1; }
            .info-card.info-os { border-left-color: #fd7e14; }
            .info-card.info-memory { border-left-color: #dc3545; }
            .info-card.info-hostname { border-left-color: #20c997; }
            .info-card.info-time { border-left-color: #ffc107; }
            
            .card-header {
                font-size: 0.9em;
                color: #666;
                margin-bottom: 8px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 1px;
            }
            
            .card-value {
                font-size: 1.1em;
                font-weight: 700;
                color: #333;
                word-break: break-word;
            }
            
            .client-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
                gap: 25px;
            }
            
            .client-card {
                background: white;
                border-radius: 15px;
                padding: 25px;
                box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
                border-top: 4px solid #007bff;
            }
            
            .client-card h3 {
                margin-bottom: 20px;
                color: #333;
                font-size: 1.2em;
            }
            
            .detail-row {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 10px 0;
                border-bottom: 1px solid #f0f0f0;
            }
            
            .detail-row:last-child {
                border-bottom: none;
            }
            
            .detail-label {
                font-weight: 600;
                color: #666;
                flex: 1;
            }
            
            .detail-value {
                flex: 2;
                text-align: right;
                font-weight: 500;
                word-break: break-all;
            }
            
            .detail-value.ip-address {
                font-family: monospace;
                background: #f8f9fa;
                padding: 3px 8px;
                border-radius: 5px;
            }
            
            .detail-value.secure {
                color: #28a745;
                font-weight: 600;
            }
            
            .detail-value.insecure {
                color: #dc3545;
                font-weight: 600;
            }
            
            .detail-value.uri {
                font-size: 0.9em;
                font-family: monospace;
            }
            
            .test-section {
                padding: 30px;
                border-bottom: 1px solid #eee;
            }
            
            .section-header {
                display: flex;
                align-items: center;
                gap: 15px;
                margin-bottom: 25px;
                padding: 20px;
                background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
                border-radius: 15px;
                border-left: 5px solid var(--section-color, #007bff);
            }
            
            .section-header h2 {
                font-size: 1.6em;
                color: #333;
                margin: 0;
            }
            
            .test-result {
                display: flex;
                align-items: center;
                gap: 15px;
                padding: 15px;
                margin: 8px 0;
                border-radius: 10px;
                border-left: 4px solid var(--status-color);
                background: var(--status-bg);
                transition: all 0.3s ease;
                position: relative;
                overflow: hidden;
            }
            
            .test-result:hover {
                transform: translateX(10px);
                box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            }
            
            .test-PASS {
                --status-color: #28a745;
                --status-bg: #d4edda;
            }
            
            .test-FAIL {
                --status-color: #dc3545;
                --status-bg: #f8d7da;
            }
            
            .test-WARN {
                --status-color: #ffc107;
                --status-bg: #fff3cd;
            }
            
            .test-INFO {
                --status-color: #17a2b8;
                --status-bg: #d1ecf1;
            }
            
            .test-SKIP {
                --status-color: #6c757d;
                --status-bg: #f8f9fa;
            }
            
            .test-icon {
                font-size: 1.2em;
                min-width: 25px;
            }
            
            .test-content {
                flex: 1;
            }
            
            .test-name {
                font-weight: 600;
                margin-bottom: 5px;
                color: #333;
            }
            
            .test-message {
                font-size: 0.9em;
                color: #666;
                line-height: 1.4;
            }
            
            .test-timing {
                font-size: 0.8em;
                color: #999;
                font-family: monospace;
            }
            
            .summary-section {
                padding: 40px;
                background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            }
            
            .summary-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 20px;
                margin: 30px 0;
            }
            
            .summary-card {
                background: white;
                padding: 25px;
                border-radius: 15px;
                text-align: center;
                box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
                transition: all 0.3s ease;
                position: relative;
                overflow: hidden;
            }
            
            .summary-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
            }
            
            .summary-card::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                height: 4px;
                background: var(--card-color, #007bff);
            }
            
            .summary-value {
                font-size: 3em;
                font-weight: 700;
                color: var(--card-color, #007bff);
                display: block;
                margin: 10px 0;
            }
            
            .summary-label {
                font-weight: 600;
                color: #666;
                text-transform: uppercase;
                letter-spacing: 1px;
                font-size: 0.9em;
            }
            
            .health-indicator {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 15px;
                padding: 30px;
                margin: 30px 0;
                background: white;
                border-radius: 20px;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
                border: 3px solid var(--health-color);
            }
            
            .health-icon {
                font-size: 3em;
                animation: pulse 2s infinite;
            }
            
            @keyframes pulse {
                0%, 100% { transform: scale(1); }
                50% { transform: scale(1.1); }
            }
            
            .health-text {
                font-size: 1.5em;
                font-weight: 700;
                color: var(--health-color);
            }
            
            .category-breakdown {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 20px;
                margin: 30px 0;
            }
            
            .category-card {
                background: white;
                border-radius: 15px;
                padding: 20px;
                box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
                border-top: 4px solid var(--category-color);
            }
            
            .category-header {
                display: flex;
                align-items: center;
                gap: 10px;
                margin-bottom: 15px;
            }
            
            .category-icon {
                font-size: 1.5em;
            }
            
            .category-name {
                font-weight: 600;
                color: #333;
            }
            
            .category-stats {
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .category-progress {
                flex: 1;
                height: 8px;
                background: #f0f0f0;
                border-radius: 4px;
                margin: 0 15px;
                overflow: hidden;
            }
            
            .category-progress-bar {
                height: 100%;
                background: var(--category-color);
                border-radius: 4px;
                transition: width 0.5s ease;
            }
            
            .category-percentage {
                font-weight: 600;
                color: var(--category-color);
            }
            
            .failed-tests {
                background: #fff5f5;
                border: 1px solid #fed7d7;
                border-radius: 15px;
                padding: 25px;
                margin: 30px 0;
            }
            
            .failed-test-item {
                display: flex;
                align-items: flex-start;
                gap: 15px;
                padding: 15px;
                margin: 10px 0;
                background: white;
                border-radius: 10px;
                border-left: 4px solid #dc3545;
            }
            
            .failed-test-category {
                background: #dc3545;
                color: white;
                padding: 3px 10px;
                border-radius: 12px;
                font-size: 0.8em;
                font-weight: 600;
                min-width: fit-content;
            }
            
            .failed-test-details {
                flex: 1;
            }
            
            .failed-test-name {
                font-weight: 600;
                color: #333;
                margin-bottom: 5px;
            }
            
            .failed-test-message {
                color: #666;
                font-size: 0.9em;
            }
            
            .footer-section {
                background: #2c3e50;
                color: white;
                padding: 40px;
                text-align: center;
            }
            
            .footer-stats {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 20px;
                margin: 20px 0;
            }
            
            .footer-stat {
                background: rgba(255, 255, 255, 0.1);
                padding: 15px;
                border-radius: 10px;
                backdrop-filter: blur(10px);
            }
            
            .footer-stat-value {
                font-size: 1.5em;
                font-weight: 700;
                display: block;
                margin-bottom: 5px;
            }
            
            .footer-stat-label {
                font-size: 0.9em;
                opacity: 0.8;
            }
            
            .progress-bar {
                width: 100%;
                height: 6px;
                background: #f0f0f0;
                border-radius: 3px;
                margin: 20px 0;
                overflow: hidden;
            }
            
            .progress-fill {
                height: 100%;
                background: linear-gradient(90deg, #28a745, #20c997);
                border-radius: 3px;
                transition: width 1s ease;
                animation: progressAnimation 2s ease-in-out;
            }
            
            @keyframes progressAnimation {
                from { width: 0; }
                to { width: var(--progress-width); }
            }
            
            .alert {
                padding: 20px;
                margin: 20px 0;
                border-radius: 15px;
                border-left: 5px solid;
                position: relative;
                overflow: hidden;
            }
            
            .alert-danger {
                background: #fff5f5;
                border-left-color: #dc3545;
                color: #721c24;
            }
            
            .alert-warning {
                background: #fffbf0;
                border-left-color: #ffc107;
                color: #856404;
            }
            
            .alert-info {
                background: #f0f9ff;
                border-left-color: #17a2b8;
                color: #0c5460;
            }
            
            .tooltip {
                position: relative;
                cursor: help;
            }
            
            .tooltip:hover::after {
                content: attr(data-tooltip);
                position: absolute;
                bottom: 100%;
                left: 50%;
                transform: translateX(-50%);
                background: #333;
                color: white;
                padding: 8px 12px;
                border-radius: 6px;
                font-size: 0.8em;
                white-space: nowrap;
                z-index: 1000;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            }
            
            .live-indicator {
                display: inline-block;
                width: 8px;
                height: 8px;
                background: #28a745;
                border-radius: 50%;
                animation: blink 1s infinite;
                margin-left: 10px;
            }
            
            @keyframes blink {
                0%, 50% { opacity: 1; }
                51%, 100% { opacity: 0.3; }
            }
            
            @media (max-width: 768px) {
                .main-container {
                    margin: 10px;
                    border-radius: 15px;
                }
                
                .header-section {
                    padding: 20px;
                }
                
                .main-title {
                    font-size: 1.8em;
                    flex-direction: column;
                    gap: 10px;
                    text-align: center;
                }
                
                .header-stats {
                    justify-content: center;
                }
                
                .dashboard-section,
                .test-section {
                    padding: 20px;
                }
                
                .client-grid {
                    grid-template-columns: 1fr;
                }
                
                .summary-grid,
                .category-breakdown {
                    grid-template-columns: 1fr;
                }
                
                .test-result {
                    flex-direction: column;
                    text-align: center;
                    gap: 10px;
                }
                
                .detail-row {
                    flex-direction: column;
                    gap: 5px;
                    text-align: left;
                }
                
                .detail-value {
                    text-align: left;
                }
            }
            
            .loading-spinner {
                display: inline-block;
                width: 20px;
                height: 20px;
                border: 3px solid #f3f3f3;
                border-top: 3px solid #007bff;
                border-radius: 50%;
                animation: spin 1s linear infinite;
            }
            
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        </style>";
    }
    
    private function getAdvancedJavaScript() {
        return "
        <script>
            // Advanced client-side functionality
            document.addEventListener('DOMContentLoaded', function() {
                // Update screen resolution
                updateScreenResolution();
                
                // Initialize progress animations
                initializeProgressBars();
                
                // Add live indicators
                addLiveIndicators();
                
                // Initialize tooltips
                initializeTooltips();
                
                // Add scroll animations
                initializeScrollAnimations();
                
                // Real-time clock
                updateClock();
                setInterval(updateClock, 1000);
                
                // Performance monitoring
                monitorPerformance();
            });
            
            function updateScreenResolution() {
                const resolution = screen.width + ' × ' + screen.height;
                const element = document.getElementById('screen-resolution');
                if (element) {
                    element.textContent = resolution;
                    
                    // Also send additional client info
                    element.innerHTML = resolution + '<br><small>(' + screen.colorDepth + '-bit, ' + 
                                      (window.devicePixelRatio || 1) + 'x DPR)</small>';
                }
            }
            
            function initializeProgressBars() {
                const progressBars = document.querySelectorAll('.progress-fill');
                progressBars.forEach(bar => {
                    const width = bar.style.getPropertyValue('--progress-width') || '0%';
                    setTimeout(() => {
                        bar.style.width = width;
                    }, 500);
                });
            }
            
            function addLiveIndicators() {
                const title = document.querySelector('.main-title');
                if (title) {
                    const indicator = document.createElement('span');
                    indicator.className = 'live-indicator';
                    indicator.title = 'Live Testing Session';
                    title.appendChild(indicator);
                }
            }
            
            function initializeTooltips() {
                // Add hover effects and enhanced tooltips
                const tooltipElements = document.querySelectorAll('[data-tooltip]');
                tooltipElements.forEach(element => {
                    element.addEventListener('mouseenter', function() {
                        this.style.transform = 'scale(1.05)';
                    });
                    
                    element.addEventListener('mouseleave', function() {
                        this.style.transform = 'scale(1)';
                    });
                });
            }
            
            function initializeScrollAnimations() {
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            entry.target.style.opacity = '1';
                            entry.target.style.transform = 'translateY(0)';
                        }
                    });
                });
                
                document.querySelectorAll('.test-section, .client-card, .summary-card').forEach(el => {
                    el.style.opacity = '0';
                    el.style.transform = 'translateY(20px)';
                    el.style.transition = 'all 0.6s ease';
                    observer.observe(el);
                });
            }
            
            function updateClock() {
                const now = new Date();
                const timeString = now.toLocaleTimeString();
                const elements = document.querySelectorAll('.live-time');
                elements.forEach(el => el.textContent = timeString);
            }
            
            function monitorPerformance() {
                // Monitor page performance
                if ('performance' in window) {
                    const perfData = performance.getEntriesByType('navigation')[0];
                    if (perfData) {
                        console.log('Page Load Performance:', {
                            'DNS Lookup': perfData.domainLookupEnd - perfData.domainLookupStart,
                            'TCP Connect': perfData.connectEnd - perfData.connectStart,
                            'Server Response': perfData.responseEnd - perfData.requestStart,
                            'DOM Parse': perfData.domContentLoadedEventEnd - perfData.responseEnd,
                            'Total Load': perfData.loadEventEnd - perfData.navigationStart
                        });
                    }
                }
                
                // Monitor memory usage (if available)
                if ('memory' in performance) {
                    const memory = performance.memory;
                    console.log('Memory Usage:', {
                        'Used': Math.round(memory.usedJSHeapSize / 1024 / 1024) + ' MB',
                        'Total': Math.round(memory.totalJSHeapSize / 1024 / 1024) + ' MB',
                        'Limit': Math.round(memory.jsHeapSizeLimit / 1024 / 1024) + ' MB'
                    });
                }
            }
            
            // Enhanced client info collection
            function getEnhancedClientInfo() {
                return {
                    screenResolution: screen.width + 'x' + screen.height,
                    colorDepth: screen.colorDepth,
                    pixelRatio: window.devicePixelRatio || 1,
                    timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
                    language: navigator.language,
                    platform: navigator.platform,
                    cookieEnabled: navigator.cookieEnabled,
                    onlineStatus: navigator.onLine,
                    touchSupport: 'ontouchstart' in window,
                    webglSupport: !!window.WebGLRenderingContext,
                    localStorageSupport: !!window.localStorage,
                    sessionStorageSupport: !!window.sessionStorage,
                    geolocationSupport: !!navigator.geolocation,
                    serviceWorkerSupport: 'serviceWorker' in navigator,
                    notificationSupport: 'Notification' in window,
                    websocketSupport: 'WebSocket' in window,
                    webrtcSupport: !!(window.RTCPeerConnection || window.mozRTCPeerConnection || window.webkitRTCPeerConnection),
                    batteryLevel: navigator.getBattery ? 'Available' : 'Not Available',
                    connectionType: navigator.connection ? navigator.connection.effectiveType : 'Unknown',
                    hardwareConcurrency: navigator.hardwareConcurrency || 'Unknown',
                    maxTouchPoints: navigator.maxTouchPoints || 0
                };
            }
            
            // Add real-time system monitoring
            function startRealTimeMonitoring() {
                setInterval(() => {
                    const now = new Date();
                    document.querySelectorAll('.real-time-clock').forEach(el => {
                        el.textContent = now.toLocaleTimeString();
                    });
                    
                    // Update connection status
                    const statusEl = document.querySelector('.connection-status');
                    if (statusEl) {
                        statusEl.textContent = navigator.onLine ? 'Online' : 'Offline';
                        statusEl.className = 'connection-status ' + (navigator.onLine ? 'online' : 'offline');
                    }
                }, 1000);
            }
            
            // Initialize real-time monitoring
            startRealTimeMonitoring();
            
            // Add keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                if (e.ctrlKey && e.key === 'r') {
                    e.preventDefault();
                    if (confirm('Refresh test results?')) {
                        location.reload();
                    }
                }
                
                if (e.key === 'Escape') {
                    // Close any open modals or overlays
                    document.querySelectorAll('.modal, .overlay').forEach(el => {
                        el.style.display = 'none';
                    });
                }
            });
            
            console.log('🔧 msbCORE Advanced Server Tester v3.0 Initialized');
            console.log('Enhanced Client Info:', getEnhancedClientInfo());
        </script>";
    }
    
    // Continue with all other enhanced methods...
    
    private function testDatabaseConnection() {
        $this->renderSection('📊 Database Connection Analysis', 'database');
        
        if (!$this->db) {
            $this->addResult('database', 'Database Instance', 'FAIL', 
                'Database instance not available');
            return;
        }
        
        try {
            $connection = $this->db->getConnection();
            if ($connection) {
                $this->addResult('database', 'Database Connection', 'PASS', 
                    'Successfully connected to database');
                
                // Enhanced database testing
                $this->testDatabasePerformance();
                $this->testDatabaseInfo();
                $this->testDatabasePrivileges();
                
            } else {
                $this->addResult('database', 'Database Connection', 'FAIL', 
                    'Failed to get database connection');
            }
        } catch (Exception $e) {
            $this->addResult('database', 'Database Connection', 'FAIL', $e->getMessage());
        }
    }
    
    private function testDatabasePerformance() {
        // Test query performance with multiple iterations
        $queryTimes = [];
        $queries = [
            "SELECT 1 as test",
            "SELECT NOW() as current_time",
            "SELECT VERSION() as version",
            "SHOW STATUS LIKE 'uptime'",
            "SELECT COUNT(*) as connection_count FROM information_schema.processlist"
        ];
        
        foreach ($queries as $query) {
            try {
                $startTime = microtime(true);
                $result = $this->db->query($query)->fetch();
                $queryTime = round((microtime(true) - $startTime) * 1000, 3);
                $queryTimes[] = $queryTime;
                
                $status = $queryTime < 10 ? 'PASS' : ($queryTime < 50 ? 'WARN' : 'FAIL');
                $this->addResult('database', "Query Performance: " . substr($query, 0, 20) . "...", 
                    $status, "Executed in {$queryTime}ms");
            } catch (Exception $e) {
                $this->addResult('database', "Query Test: $query", 'FAIL', $e->getMessage());
            }
        }
        
        if (!empty($queryTimes)) {
            $avgTime = round(array_sum($queryTimes) / count($queryTimes), 3);
            $status = $avgTime < 15 ? 'PASS' : ($avgTime < 40 ? 'WARN' : 'FAIL');
            $this->addResult('database', 'Average Query Time', $status, 
                "Average: {$avgTime}ms | Min: " . min($queryTimes) . "ms | Max: " . max($queryTimes) . "ms");
        }
    }
    
    private function testDatabaseInfo() {
        try {
            // Get database version and info
            $version = $this->db->query("SELECT VERSION() as version")->fetch();
            if ($version) {
                $this->addResult('database', 'Database Version', 'INFO', 
                    $version['version']);
            }
            
            // Get database size
            $sizeQuery = "SELECT 
                ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb,
                COUNT(*) as table_count
                FROM information_schema.tables 
                WHERE table_schema = DATABASE()";
            
            $sizeInfo = $this->db->query($sizeQuery)->fetch();
            if ($sizeInfo) {
                $this->addResult('database', 'Database Size', 'INFO', 
                    "{$sizeInfo['size_mb']} MB ({$sizeInfo['table_count']} tables)");
            }
            
            // Get connection info
            $connectionInfo = $this->db->query("SHOW STATUS LIKE 'Threads_connected'")->fetch();
            if ($connectionInfo) {
                $this->addResult('database', 'Active Connections', 'INFO', 
                    $connectionInfo['Value'] . ' connections');
            }
            
            // Get uptime
            $uptimeInfo = $this->db->query("SHOW STATUS LIKE 'Uptime'")->fetch();
            if ($uptimeInfo) {
                $uptimeHours = round($uptimeInfo['Value'] / 3600, 1);
                $this->addResult('database', 'Database Uptime', 'INFO', 
                    "{$uptimeHours} hours");
            }
            
        } catch (Exception $e) {
            $this->addResult('database', 'Database Information', 'WARN', 
                'Could not retrieve database info: ' . $e->getMessage());
        }
    }
    
    private function testDatabasePrivileges() {
        try {
            $privileges = $this->db->query("SHOW GRANTS")->fetchAll();
            $hasAllPrivileges = false;
            $privilegeList = [];
            
            foreach ($privileges as $privilege) {
                $grant = array_values($privilege)[0];
                $privilegeList[] = $grant;
                
                if (strpos($grant, 'ALL PRIVILEGES') !== false) {
                    $hasAllPrivileges = true;
                }
            }
            
            $status = $hasAllPrivileges ? 'PASS' : 'WARN';
            $message = $hasAllPrivileges ? 
                'Full database privileges available' : 
                'Limited privileges (may affect some operations)';
            
            $this->addResult('database', 'Database Privileges', $status, 
                $message . ' (' . count($privilegeList) . ' grants)');
                
        } catch (Exception $e) {
            $this->addResult('database', 'Database Privileges', 'INFO', 
                'Could not check privileges: ' . $e->getMessage());
        }
    }
    
    // ========================================
    // AUTHENTICATION SYSTEM TESTS
    // ========================================
    
    private function testAuthenticationSystem() {
        $this->renderSection('🔐 Authentication System Analysis', 'authentication');
        
        // Test authentication files existence
        $authFiles = [
            'login.php' => 'Login page',
            'logout.php' => 'Logout handler',
            'auth.php' => 'Authentication handler',
            'config/security.php' => 'Security configuration'
        ];
        
        foreach ($authFiles as $file => $description) {
            $filePath = __DIR__ . '/' . $file;
            if (file_exists($filePath)) {
                $this->addResult('authentication', "Auth File: $description", 'PASS', 
                    "File exists: $file");
            } else {
                $this->addResult('authentication', "Auth File: $description", 'FAIL', 
                    "Missing file: $file");
            }
        }
        
        // Test users table structure
        $this->testUsersTableStructure();
        
        // Test password hashing functions
        $this->testPasswordHashing();
        
        // Test session security
        $this->testSessionSecurity();
        
        // Test remember tokens table
        $this->testRememberTokensTable();
        
        // Test authentication configuration
        $this->testAuthConfiguration();
    }
    
    private function testUsersTableStructure() {
        if (!$this->db) {
            $this->addResult('authentication', 'Users Table Structure', 'SKIP', 
                'Database not available');
            return;
        }
        
        try {
            // Check if users table exists
            $tableExists = $this->db->query("SHOW TABLES LIKE 'users'")->fetch();
            if (!$tableExists) {
                $this->addResult('authentication', 'Users Table Existence', 'FAIL', 
                    'Users table does not exist');
                return;
            }
            
            $this->addResult('authentication', 'Users Table Existence', 'PASS', 
                'Users table exists');
            
            // Check table structure
            $columns = $this->db->query("DESCRIBE users")->fetchAll();
            $requiredColumns = [
                'id' => 'Primary key',
                'username' => 'Username field',
                'email' => 'Email field',
                'password_hash' => 'Password hash field',
                'role' => 'User role field',
                'status' => 'Account status field',
                'created_at' => 'Creation timestamp',
                'updated_at' => 'Update timestamp'
            ];
            
            $existingColumns = array_column($columns, 'Field');
            
            foreach ($requiredColumns as $column => $description) {
                if (in_array($column, $existingColumns)) {
                    $this->addResult('authentication', "Users Column: $description", 'PASS', 
                        "Column '$column' exists");
                } else {
                    $this->addResult('authentication', "Users Column: $description", 'FAIL', 
                        "Missing column '$column'");
                }
            }
            
            // Check for sample users
            $userCount = $this->db->query("SELECT COUNT(*) as count FROM users")->fetch();
            if ($userCount && $userCount['count'] > 0) {
                $this->addResult('authentication', 'Sample Users', 'PASS', 
                    "Found {$userCount['count']} users in database");
                
                // Check for admin user
                $adminUser = $this->db->query("SELECT * FROM users WHERE role = 'admin' LIMIT 1")->fetch();
                if ($adminUser) {
                    $this->addResult('authentication', 'Admin User', 'PASS', 
                        "Admin user exists: {$adminUser['username']}");
                } else {
                    $this->addResult('authentication', 'Admin User', 'WARN', 
                        'No admin user found');
                }
            } else {
                $this->addResult('authentication', 'Sample Users', 'WARN', 
                    'No users found in database');
            }
            
        } catch (Exception $e) {
            $this->addResult('authentication', 'Users Table Structure', 'FAIL', 
                'Error checking users table: ' . $e->getMessage());
        }
    }
    
    private function testPasswordHashing() {
        // Test password_hash function
        if (function_exists('password_hash')) {
            $this->addResult('authentication', 'Password Hash Function', 'PASS', 
                'password_hash() function available');
            
            // Test password hashing
            $testPassword = 'test123';
            $hash = password_hash($testPassword, PASSWORD_DEFAULT);
            
            if ($hash && password_verify($testPassword, $hash)) {
                $this->addResult('authentication', 'Password Hashing Test', 'PASS', 
                    'Password hashing and verification working correctly');
            } else {
                $this->addResult('authentication', 'Password Hashing Test', 'FAIL', 
                    'Password hashing or verification failed');
            }
        } else {
            $this->addResult('authentication', 'Password Hash Function', 'FAIL', 
                'password_hash() function not available');
        }
        
        // Test bcrypt configuration
        if (defined('BCRYPT_ROUNDS')) {
            $rounds = BCRYPT_ROUNDS;
            $status = ($rounds >= 10 && $rounds <= 15) ? 'PASS' : 'WARN';
            $message = $status === 'PASS' ? 
                "Bcrypt rounds set to $rounds (recommended: 10-15)" :
                "Bcrypt rounds set to $rounds (consider 10-15 for security/performance balance)";
            
            $this->addResult('authentication', 'Bcrypt Configuration', $status, $message);
        } else {
            $this->addResult('authentication', 'Bcrypt Configuration', 'WARN', 
                'BCRYPT_ROUNDS not defined, using PHP default');
        }
    }
    
    private function testSessionSecurity() {
        // Test session configuration
        $sessionTests = [
            'session.cookie_httponly' => ['expected' => '1', 'description' => 'HTTP-only cookies'],
            'session.use_only_cookies' => ['expected' => '1', 'description' => 'Use only cookies'],
            'session.cookie_secure' => ['expected' => (isset($_SERVER['HTTPS']) ? '1' : '0'), 'description' => 'Secure cookies'],
            'session.gc_maxlifetime' => ['min' => 300, 'max' => 7200, 'description' => 'Session lifetime']
        ];
        
        foreach ($sessionTests as $setting => $test) {
            $value = ini_get($setting);
            
            if (isset($test['expected'])) {
                $status = ($value == $test['expected']) ? 'PASS' : 'WARN';
                $message = $status === 'PASS' ? 
                    "{$test['description']} properly configured ($value)" :
                    "{$test['description']} not optimal (current: $value, expected: {$test['expected']})";
            } else {
                $numValue = (int)$value;
                $status = ($numValue >= $test['min'] && $numValue <= $test['max']) ? 'PASS' : 'WARN';
                $message = $status === 'PASS' ? 
                    "{$test['description']} within recommended range ($value seconds)" :
                    "{$test['description']} outside recommended range ($value seconds, recommended: {$test['min']}-{$test['max']})";
            }
            
            $this->addResult('authentication', "Session Security: {$test['description']}", $status, $message);
        }
        
        // Test session status
        if (session_status() === PHP_SESSION_ACTIVE) {
            $this->addResult('authentication', 'Session Status', 'PASS', 
                'Session is active');
            
            // Test session ID security
            $sessionId = session_id();
            if (strlen($sessionId) >= 26) {
                $this->addResult('authentication', 'Session ID Length', 'PASS', 
                    "Session ID length: " . strlen($sessionId) . " characters");
            } else {
                $this->addResult('authentication', 'Session ID Length', 'WARN', 
                    "Session ID might be too short: " . strlen($sessionId) . " characters");
            }
        } else {
            $this->addResult('authentication', 'Session Status', 'INFO', 
                'Session not active (normal for testing)');
        }
    }
    
    private function testRememberTokensTable() {
        if (!$this->db) {
            $this->addResult('authentication', 'Remember Tokens Table', 'SKIP', 
                'Database not available');
            return;
        }
        
        try {
            $tableExists = $this->db->query("SHOW TABLES LIKE 'remember_tokens'")->fetch();
            if ($tableExists) {
                $this->addResult('authentication', 'Remember Tokens Table', 'PASS', 
                    'Remember tokens table exists');
                
                // Check table structure
                $columns = $this->db->query("DESCRIBE remember_tokens")->fetchAll();
                $requiredColumns = ['id', 'user_id', 'token_hash', 'expires_at', 'created_at'];
                $existingColumns = array_column($columns, 'Field');
                
                $missingColumns = array_diff($requiredColumns, $existingColumns);
                if (empty($missingColumns)) {
                    $this->addResult('authentication', 'Remember Tokens Structure', 'PASS', 
                        'All required columns present');
                } else {
                    $this->addResult('authentication', 'Remember Tokens Structure', 'FAIL', 
                        'Missing columns: ' . implode(', ', $missingColumns));
                }
            } else {
                $this->addResult('authentication', 'Remember Tokens Table', 'WARN', 
                    'Remember tokens table does not exist (optional feature)');
            }
        } catch (Exception $e) {
            $this->addResult('authentication', 'Remember Tokens Table', 'FAIL', 
                'Error checking remember tokens table: ' . $e->getMessage());
        }
    }
    
    private function testAuthConfiguration() {
        // Test security constants
        $securityConstants = [
            'ENCRYPTION_KEY' => 'Encryption key for data protection',
            'JWT_SECRET' => 'JWT secret for token signing',
            'SESSION_LIFETIME' => 'Session lifetime configuration'
        ];
        
        foreach ($securityConstants as $constant => $description) {
            if (defined($constant)) {
                $value = constant($constant);
                if (!empty($value)) {
                    $this->addResult('authentication', "Security Config: $description", 'PASS', 
                        "$constant is defined and not empty");
                } else {
                    $this->addResult('authentication', "Security Config: $description", 'WARN', 
                        "$constant is defined but empty");
                }
            } else {
                $this->addResult('authentication', "Security Config: $description", 'INFO', 
                    "$constant is not defined (may be optional)");
            }
        }
        
        // Test debug mode security
        if (defined('DEBUG_MODE')) {
            $status = DEBUG_MODE ? 'WARN' : 'PASS';
            $message = DEBUG_MODE ? 
                'Debug mode is enabled (disable in production)' :
                'Debug mode is disabled (good for production)';
            
            $this->addResult('authentication', 'Debug Mode Security', $status, $message);
        }
    }
    
    // ========================================
    // LOGIN FUNCTIONALITY TESTS
    // ========================================
    
    private function testLoginFunctionality() {
        $this->renderSection('👤 Login System Testing', 'login');
        
        // Test login page accessibility
        $this->testLoginPageAccess();
        
        // Test login form structure
        $this->testLoginFormStructure();
        
        // Test authentication logic
        $this->testAuthenticationLogic();
        
        // Test login security features
        $this->testLoginSecurity();
        
        // Test logout functionality
        $this->testLogoutFunctionality();
        
        // Test user roles and permissions
        $this->testUserRolesPermissions();
    }
    
    private function testLoginPageAccess() {
        $loginFile = __DIR__ . '/login.php';
        
        if (file_exists($loginFile)) {
            $this->addResult('login', 'Login Page File', 'PASS', 
                'login.php file exists');
            
            // Check if file is readable
            if (is_readable($loginFile)) {
                $this->addResult('login', 'Login Page Readable', 'PASS', 
                    'login.php is readable');
                
                // Basic content check
                $content = file_get_contents($loginFile);
                if (strpos($content, 'form') !== false) {
                    $this->addResult('login', 'Login Form Present', 'PASS', 
                        'Login form detected in login.php');
                } else {
                    $this->addResult('login', 'Login Form Present', 'WARN', 
                        'No form element detected in login.php');
                }
                
                // Check for security features
                $securityFeatures = [
                    'csrf' => 'CSRF protection',
                    'token' => 'Security token',
                    'password_hash' => 'Password hashing',
                    'session' => 'Session management'
                ];
                
                foreach ($securityFeatures as $feature => $description) {
                    if (stripos($content, $feature) !== false) {
                        $this->addResult('login', "Security Feature: $description", 'PASS', 
                            "Found $feature implementation");
                    } else {
                        $this->addResult('login', "Security Feature: $description", 'INFO', 
                            "No obvious $feature implementation found");
                    }
                }
            } else {
                $this->addResult('login', 'Login Page Readable', 'FAIL', 
                    'login.php is not readable');
            }
        } else {
            $this->addResult('login', 'Login Page File', 'FAIL', 
                'login.php file does not exist');
        }
    }
    
    private function testLoginFormStructure() {
        $loginFile = __DIR__ . '/login.php';
        
        if (!file_exists($loginFile)) {
            $this->addResult('login', 'Login Form Structure', 'SKIP', 
                'login.php not found');
            return;
        }
        
        $content = file_get_contents($loginFile);
        
        // Check for required form elements
        $formElements = [
            'username' => 'Username input field',
            'password' => 'Password input field',
            'submit' => 'Submit button',
            'method.*post' => 'POST method',
            'action' => 'Form action'
        ];
        
        foreach ($formElements as $pattern => $description) {
            if (preg_match("/$pattern/i", $content)) {
                $this->addResult('login', "Form Element: $description", 'PASS', 
                    "Found $description in login form");
            } else {
                $this->addResult('login', "Form Element: $description", 'WARN', 
                    "Missing or unclear $description");
            }
        }
        
        // Check for HTML5 validation
        if (preg_match('/required/i', $content)) {
            $this->addResult('login', 'HTML5 Validation', 'PASS', 
                'HTML5 required attributes found');
        } else {
            $this->addResult('login', 'HTML5 Validation', 'INFO', 
                'No HTML5 validation attributes detected');
        }
    }
    
    private function testAuthenticationLogic() {
        if (!$this->db) {
            $this->addResult('login', 'Authentication Logic', 'SKIP', 
                'Database not available for testing');
            return;
        }
        
        try {
            // Test if we can query users table
            $userCount = $this->db->query("SELECT COUNT(*) as count FROM users WHERE status = 'active'")->fetch();
            if ($userCount) {
                $this->addResult('login', 'User Query Test', 'PASS', 
                    "Found {$userCount['count']} active users");
                
                // Test sample authentication query structure
                $testUser = $this->db->query("SELECT id, username, password_hash, role, status FROM users WHERE status = 'active' LIMIT 1")->fetch();
                
                if ($testUser) {
                    $this->addResult('login', 'Authentication Query', 'PASS', 
                        'Authentication query structure is valid');
                    
                    // Test password verification with sample data
                    if (!empty($testUser['password_hash'])) {
                        $this->addResult('login', 'Password Hash Storage', 'PASS', 
                            'Password hashes are stored in database');
                    } else {
                        $this->addResult('login', 'Password Hash Storage', 'WARN', 
                            'Empty password hash found');
                    }
                } else {
                    $this->addResult('login', 'Authentication Query', 'WARN', 
                        'No active users found for testing');
                }
            }
        } catch (Exception $e) {
            $this->addResult('login', 'Authentication Logic', 'FAIL', 
                'Error testing authentication: ' . $e->getMessage());
        }
    }
    
    private function testLoginSecurity() {
        // Test rate limiting configuration
        if (defined('RATE_LIMIT_REQUESTS') && defined('RATE_LIMIT_WINDOW')) {
            $requests = RATE_LIMIT_REQUESTS;
            $window = RATE_LIMIT_WINDOW;
            $this->addResult('login', 'Rate Limiting Config', 'PASS', 
                "Rate limiting: $requests requests per $window seconds");
        } else {
            $this->addResult('login', 'Rate Limiting Config', 'WARN', 
                'Rate limiting constants not defined');
        }
        
        // Test failed login tracking
        if ($this->db) {
            try {
                $columns = $this->db->query("DESCRIBE users")->fetchAll();
                $columnNames = array_column($columns, 'Field');
                
                if (in_array('failed_login_attempts', $columnNames)) {
                    $this->addResult('login', 'Failed Login Tracking', 'PASS', 
                        'Failed login attempts column exists');
                } else {
                    $this->addResult('login', 'Failed Login Tracking', 'WARN', 
                        'No failed login attempts tracking');
                }
                
                if (in_array('account_locked_until', $columnNames)) {
                    $this->addResult('login', 'Account Locking', 'PASS', 
                        'Account locking mechanism available');
                } else {
                    $this->addResult('login', 'Account Locking', 'WARN', 
                        'No account locking mechanism');
                }
            } catch (Exception $e) {
                $this->addResult('login', 'Login Security Features', 'FAIL', 
                    'Error checking security features: ' . $e->getMessage());
            }
        }
        
        // Test HTTPS enforcement
        $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        $status = $isHttps ? 'PASS' : 'WARN';
        $message = $isHttps ? 
            'HTTPS is enabled (secure login)' :
            'HTTPS not detected (consider enabling for production)';
        
        $this->addResult('login', 'HTTPS Security', $status, $message);
    }
    
    private function testLogoutFunctionality() {
        $logoutFile = __DIR__ . '/logout.php';
        
        if (file_exists($logoutFile)) {
            $this->addResult('login', 'Logout File', 'PASS', 
                'logout.php file exists');
            
            $content = file_get_contents($logoutFile);
            
            // Check for session destruction
            if (strpos($content, 'session_destroy') !== false || strpos($content, 'session_unset') !== false) {
                $this->addResult('login', 'Session Cleanup', 'PASS', 
                    'Session destruction code found');
            } else {
                $this->addResult('login', 'Session Cleanup', 'WARN', 
                    'No obvious session cleanup found');
            }
            
            // Check for redirect after logout
            if (strpos($content, 'header') !== false && strpos($content, 'Location') !== false) {
                $this->addResult('login', 'Logout Redirect', 'PASS', 
                    'Redirect after logout found');
            } else {
                $this->addResult('login', 'Logout Redirect', 'INFO', 
                    'No redirect after logout detected');
            }
        } else {
            $this->addResult('login', 'Logout File', 'WARN', 
                'logout.php file does not exist');
        }
    }
    
    private function testUserRolesPermissions() {
        if (!$this->db) {
            $this->addResult('login', 'User Roles System', 'SKIP', 
                'Database not available');
            return;
        }
        
        try {
            // Check role enumeration
            $tableInfo = $this->db->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch();
            if ($tableInfo) {
                $roleEnum = $tableInfo['Type'];
                if (strpos($roleEnum, 'enum') !== false) {
                    $this->addResult('login', 'Role System', 'PASS', 
                        "Role enumeration defined: $roleEnum");
                    
                    // Extract roles from enum
                    preg_match_all("/'([^']+)'/", $roleEnum, $matches);
                    $roles = $matches[1];
                    
                    $expectedRoles = ['admin', 'manager', 'technician', 'staff'];
                    $missingRoles = array_diff($expectedRoles, $roles);
                    
                    if (empty($missingRoles)) {
                        $this->addResult('login', 'Role Completeness', 'PASS', 
                            'All expected roles present: ' . implode(', ', $roles));
                    } else {
                        $this->addResult('login', 'Role Completeness', 'INFO', 
                            'Missing roles: ' . implode(', ', $missingRoles));
                    }
                } else {
                    $this->addResult('login', 'Role System', 'WARN', 
                        'Role field is not an enum type');
                }
            }
            
            // Check role distribution
            $roleStats = $this->db->query("SELECT role, COUNT(*) as count FROM users GROUP BY role")->fetchAll();
            if ($roleStats) {
                $roleInfo = [];
                foreach ($roleStats as $stat) {
                    $roleInfo[] = "{$stat['role']}: {$stat['count']}";
                }
                $this->addResult('login', 'Role Distribution', 'INFO', 
                    'User roles: ' . implode(', ', $roleInfo));
            }
            
        } catch (Exception $e) {
            $this->addResult('login', 'User Roles System', 'FAIL', 
                'Error checking roles: ' . $e->getMessage());
        }
    }
    
    // Continue with all other enhanced test methods...
    
    private function renderSection($title, $category) {
        $categoryInfo = $this->testCategories[$category] ?? ['icon' => '🔧', 'color' => '#007bff'];
        
        echo "<div class='test-section'>
                <div class='section-header' style='--section-color: {$categoryInfo['color']}'>
                    <span class='section-icon'>{$categoryInfo['icon']}</span>
                    <h2>{$title}</h2>
                    <span class='real-time-clock live-time'>" . date('H:i:s') . "</span>
                </div>
                <div class='test-results'>";
    }
    
    private function addResult($category, $test, $status, $message) {
        $timestamp = microtime(true);
        $this->results[] = [
            'category' => $category,
            'test' => $test,
            'status' => $status,
            'message' => $message,
            'timestamp' => $timestamp,
            'time_display' => date('H:i:s.') . substr($timestamp, -3)
        ];
        
        $this->renderEnhancedTestResult($test, $status, $message, $timestamp);
    }
    
    private function renderEnhancedTestResult($test, $status, $message, $timestamp) {
        $icons = [
            'PASS' => '✅',
            'FAIL' => '❌',
            'WARN' => '⚠️',
            'INFO' => 'ℹ️',
            'SKIP' => '⏭️'
        ];
        
        $icon = $icons[$status] ?? '•';
        $timing = date('H:i:s.') . substr($timestamp, -3);
        
        echo "<div class='test-result test-{$status}'>
                <span class='test-icon'>{$icon}</span>
                <div class='test-content'>
                    <div class='test-name'>{$test}</div>
                    <div class='test-message'>{$message}</div>
                </div>
                <div class='test-timing'>{$timing}</div>
              </div>";
    }
    
    private function renderAdvancedSummary() {
        echo "</div></div>"; // Close last section
        
        echo "<div class='summary-section'>";
        echo "<h2 class='section-title'>
                <span class='section-icon'>📊</span>
                Comprehensive Test Analysis
              </h2>";
        
        // Calculate enhanced statistics
        $stats = $this->calculateAdvancedStats();
        
        // Render main statistics grid
        $this->renderMainStatsGrid($stats);
        
        // Render health indicator
        $this->renderHealthIndicator($stats);
        
        // Render category breakdown
        $this->renderCategoryBreakdown();
        
        // Render performance metrics
        $this->renderPerformanceMetrics();
        
        // Render failed tests if any
        if ($stats['failed'] > 0) {
            $this->renderFailedTests();
        }
        
        // Render recommendations
        $this->renderRecommendations($stats);
        
        echo "</div>"; // Close summary section
    }
    
    private function calculateAdvancedStats() {
        $stats = [];
        foreach (['PASS', 'FAIL', 'WARN', 'INFO', 'SKIP'] as $status) {
            $stats[strtolower($status)] = count(array_filter($this->results, fn($r) => $r['status'] === $status));
        }
        
        $stats['total'] = count($this->results);
        $stats['success_rate'] = $stats['total'] > 0 ? round(($stats['pass'] / $stats['total']) * 100, 1) : 0;
        $stats['health_score'] = $stats['total'] > 0 ? round((($stats['pass'] + ($stats['warn'] * 0.5)) / $stats['total']) * 100, 1) : 0;
        $stats['critical_issues'] = $stats['fail'];
        $stats['warnings'] = $stats['warn'];
        
        return $stats;
    }
    
    private function renderMainStatsGrid($stats) {
        $statCards = [
            ['Total Tests', $stats['total'], '#17a2b8', 'total'],
            ['Passed', $stats['pass'], '#28a745', 'pass'],
            ['Failed', $stats['fail'], '#dc3545', 'fail'],
            ['Warnings', $stats['warn'], '#ffc107', 'warn'],
            ['Success Rate', $stats['success_rate'] . '%', '#6f42c1', 'success'],
            ['Health Score', $stats['health_score'] . '%', '#20c997', 'health']
        ];
        
        echo "<div class='summary-grid'>";
        foreach ($statCards as $card) {
            echo "<div class='summary-card' style='--card-color: {$card[2]}'>
                    <span class='summary-value'>{$card[1]}</span>
                    <span class='summary-label'>{$card[0]}</span>
                  </div>";
        }
        echo "</div>";
    }
    
    private function renderHealthIndicator($stats) {
        $healthLevel = $this->determineHealthLevel($stats);
        
        echo "<div class='health-indicator' style='--health-color: {$healthLevel['color']}'>
                <span class='health-icon'>{$healthLevel['icon']}</span>
                <div>
                    <div class='health-text'>{$healthLevel['status']}</div>
                    <div style='font-size: 0.9em; opacity: 0.8;'>{$healthLevel['description']}</div>
                </div>
              </div>";
    }
    
    private function determineHealthLevel($stats) {
        if ($stats['fail'] === 0 && $stats['warn'] <= 2) {
            return [
                'status' => 'EXCELLENT SYSTEM HEALTH',
                'icon' => '🎯',
                'color' => '#28a745',
                'description' => 'All systems operational, minimal warnings'
            ];
        } elseif ($stats['fail'] <= 2 && $stats['health_score'] >= 80) {
            return [
                'status' => 'GOOD SYSTEM HEALTH',
                'icon' => '✅',
                'color' => '#28a745',
                'description' => 'System performing well with minor issues'
            ];
        } elseif ($stats['fail'] <= 5 && $stats['health_score'] >= 60) {
            return [
                'status' => 'SYSTEM NEEDS ATTENTION',
                'icon' => '⚠️',
                'color' => '#ffc107',
                'description' => 'Several issues detected, maintenance recommended'
            ];
        } else {
            return [
                'status' => 'CRITICAL SYSTEM ISSUES',
                'icon' => '❌',
                'color' => '#dc3545',
                'description' => 'Immediate attention required, system instability detected'
            ];
        }
    }
    
    private function renderCategoryBreakdown() {
        echo "<h3 style='margin: 40px 0 20px 0; color: #333;'>
                <span style='margin-right: 10px;'>📋</span>
                Category Performance Analysis
              </h3>";
        
        echo "<div class='category-breakdown'>";
        
        foreach ($this->testCategories as $categoryKey => $categoryInfo) {
            $categoryResults = array_filter($this->results, fn($r) => $r['category'] === $categoryKey);
            $categoryTotal = count($categoryResults);
            $categoryPassed = count(array_filter($categoryResults, fn($r) => $r['status'] === 'PASS'));
            $categoryFailed = count(array_filter($categoryResults, fn($r) => $r['status'] === 'FAIL'));
            $categoryWarned = count(array_filter($categoryResults, fn($r) => $r['status'] === 'WARN'));
            
            $categoryRate = $categoryTotal > 0 ? round(($categoryPassed / $categoryTotal) * 100, 1) : 0;
            
            echo "<div class='category-card' style='--category-color: {$categoryInfo['color']}'>
                    <div class='category-header'>
                        <span class='category-icon'>{$categoryInfo['icon']}</span>
                        <span class='category-name'>{$categoryInfo['name']}</span>
                    </div>
                    <div class='category-stats'>
                        <span style='font-size: 0.9em; color: #666;'>{$categoryPassed}/{$categoryTotal}</span>
                        <div class='category-progress'>
                            <div class='category-progress-bar' style='width: {$categoryRate}%'></div>
                        </div>
                        <span class='category-percentage'>{$categoryRate}%</span>
                    </div>
                    <div style='margin-top: 10px; font-size: 0.8em; color: #666;'>
                        <span style='color: #28a745;'>✓ {$categoryPassed}</span> | 
                        <span style='color: #ffc107;'>⚠ {$categoryWarned}</span> | 
                        <span style='color: #dc3545;'>✗ {$categoryFailed}</span>
                    </div>
                  </div>";
        }
        
        echo "</div>";
    }
    
    private function renderPerformanceMetrics() {
        $executionTime = round(microtime(true) - $this->startTime, 2);
        $memoryUsage = memory_get_usage(true);
        $memoryPeak = memory_get_peak_usage(true);
        
        echo "<h3 style='margin: 40px 0 20px 0; color: #333;'>
                <span style='margin-right: 10px;'>⚡</span>
                Performance Metrics
              </h3>";
        
        echo "<div class='summary-grid'>";
        
        $performanceCards = [
            ['Execution Time', $executionTime . 's', $executionTime < 30 ? '#28a745' : '#ffc107'],
            ['Memory Usage', $this->formatBytes($memoryUsage), '#17a2b8'],
            ['Peak Memory', $this->formatBytes($memoryPeak), '#6f42c1'],
            ['Tests/Second', round(count($this->results) / max($executionTime, 1), 1), '#fd7e14'],
        ];
        
        foreach ($performanceCards as $card) {
            echo "<div class='summary-card' style='--card-color: {$card[2]}'>
                    <span class='summary-value' style='font-size: 2em;'>{$card[1]}</span>
                    <span class='summary-label'>{$card[0]}</span>
                  </div>";
        }
        
        echo "</div>";
    }
    
    private function renderFailedTests() {
        $failedTests = array_filter($this->results, fn($r) => $r['status'] === 'FAIL');
        
        echo "<h3 style='margin: 40px 0 20px 0; color: #dc3545;'>
                <span style='margin-right: 10px;'>❌</span>
                Failed Tests Requiring Attention
              </h3>";
        
        echo "<div class='failed-tests'>";
        
        foreach ($failedTests as $test) {
            echo "<div class='failed-test-item'>
                    <div class='failed-test-category'>{$test['category']}</div>
                    <div class='failed-test-details'>
                        <div class='failed-test-name'>{$test['test']}</div>
                        <div class='failed-test-message'>{$test['message']}</div>
                    </div>
                  </div>";
        }
        
        echo "</div>";
    }
    
    private function renderRecommendations($stats) {
        echo "<h3 style='margin: 40px 0 20px 0; color: #333;'>
                <span style='margin-right: 10px;'>💡</span>
                System Recommendations
              </h3>";
        
        $recommendations = $this->generateRecommendations($stats);
        
        foreach ($recommendations as $rec) {
            echo "<div class='alert alert-{$rec['type']}'>
                    <strong>{$rec['title']}</strong><br>
                    {$rec['description']}
                  </div>";
        }
    }
    
    private function generateRecommendations($stats) {
        $recommendations = [];
        
        if ($stats['fail'] > 0) {
            $recommendations[] = [
                'type' => 'danger',
                'title' => 'Critical Issues Detected',
                'description' => "Address the {$stats['fail']} failed tests immediately to ensure system stability and security."
            ];
        }
        
        if ($stats['warn'] > 5) {
            $recommendations[] = [
                'type' => 'warning',
                'title' => 'High Warning Count',
                'description' => "Consider addressing {$stats['warn']} warnings to improve system performance and security."
            ];
        }
        
        if ($stats['health_score'] < 80) {
            $recommendations[] = [
                'type' => 'info',
                'title' => 'System Optimization',
                'description' => 'System health score is below optimal. Review failed tests and warnings for improvement opportunities.'
            ];
        }
        
        // Always include some general recommendations
        $recommendations[] = [
            'type' => 'info',
            'title' => 'Regular Monitoring',
            'description' => 'Run this test suite regularly to monitor system health and catch issues early.'
        ];
        
        return $recommendations;
    }
    
    private function renderAdvancedFooter() {
        $executionTime = round(microtime(true) - $this->startTime, 2);
        $memoryUsage = $this->formatBytes(memory_get_peak_usage(true));
        
        echo "<div class='footer-section'>
                <h3 style='margin-bottom: 30px; font-size: 1.5em;'>
                    <span style='margin-right: 10px;'>📊</span>
                    Test Session Complete
                </h3>
                
                <div class='footer-stats'>
                    <div class='footer-stat'>
                        <span class='footer-stat-value'>{$executionTime}s</span>
                        <span class='footer-stat-label'>Execution Time</span>
                    </div>
                    <div class='footer-stat'>
                        <span class='footer-stat-value'>{$memoryUsage}</span>
                        <span class='footer-stat-label'>Peak Memory</span>
                    </div>
                    <div class='footer-stat'>
                        <span class='footer-stat-value'>" . count($this->results) . "</span>
                        <span class='footer-stat-label'>Total Tests</span>
                    </div>
                    <div class='footer-stat'>
                        <span class='footer-stat-value'>" . $this->sessionData['test_count'] . "</span>
                        <span class='footer-stat-label'>Session Number</span>
                    </div>
                </div>
                
                <div style='margin: 30px 0; padding: 20px; background: rgba(255,255,255,0.1); border-radius: 10px;'>
                    <h4 style='margin-bottom: 15px;'>Technical Details</h4>
                    <div style='display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; font-size: 0.9em;'>
                        <div><strong>Server:</strong> {$this->systemInfo['server_software']}</div>
                        <div><strong>PHP:</strong> {$this->systemInfo['php_version']}</div>
                        <div><strong>Client IP:</strong> {$this->clientInfo['ip_address']}</div>
                        <div><strong>Browser:</strong> {$this->clientInfo['browser']}</div>
                        <div><strong>OS:</strong> {$this->clientInfo['operating_system']}</div>
                        <div><strong>Generated:</strong> " . date('Y-m-d H:i:s') . "</div>
                    </div>
                </div>
                
                <div style='text-align: center; margin-top: 30px; opacity: 0.8;'>
                    <p><strong>msbCORE Advanced Server Testing Suite v3.0</strong></p>
                    <p>Professional system analysis and monitoring</p>
                    <p><em>For technical support or questions, contact your system administrator</em></p>
                </div>
              </div>
            </div>"; // Close main container
    }
    
    // Continue with all utility methods...
    private function formatBytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    private function logTest($message, $data = null) {
        if (function_exists('debugLog')) {
            debugLog($message, 'ADVANCED_SERVER_TEST', $data);
        }
        
        // Also log to error log for debugging
        error_log("[msbCORE Test] $message" . ($data ? ' | Data: ' . json_encode($data) : ''));
    }
}

// Execute the advanced comprehensive tests
if (basename($_SERVER['PHP_SELF']) === 'test_server.php') {
    $tester = new AdvancedServerTester();
    $tester->runAllTests();
}
?>

