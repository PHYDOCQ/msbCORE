<?php
// Simple test page to verify server functionality
header('Content-Type: application/json');

echo json_encode([
    'status' => 'success',
    'message' => 'msbCORE server is running',
    'timestamp' => date('Y-m-d H:i:s'),
    'php_version' => PHP_VERSION,
    'security_features' => [
        'session_security' => 'enabled',
        'csrf_protection' => 'enabled',
        'xss_prevention' => 'enabled',
        'rate_limiting' => 'enabled'
    ]
]);
?>
