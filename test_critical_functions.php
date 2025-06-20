<?php
/**
 * CRITICAL FUNCTIONALITY TEST SCRIPT
 * Tests the most important functions after error fixes
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/security.php';
require_once __DIR__ . '/includes/functions.php';

echo "<h1>🔧 Critical Functions Test</h1>
";
echo "<div style='font-family: monospace; background: #f5f5f5; padding: 20px;'>
";

$results = [];

// Test 1: Database Connection
echo "<h2>📊 Database Connection Test</h2>
";
try {
    $db = Database::getInstance();
    $connection = $db->getConnection();
    echo "✅ <strong>Database Connection:</strong> SUCCESS
<br>";
    $results['database'] = 'PASS';
} catch (Exception $e) {
    echo "❌ <strong>Database Connection:</strong> FAILED - " . $e->getMessage() . "
<br>";
    $results['database'] = 'FAIL';
}

// Test 2: Security Functions
echo "<h2>🔒 Security Functions Test</h2>
";
try {
    // Test password hashing
    $password = 'test123';
    $hash = Security::hashPassword($password);
    $verify = Security::verifyPassword($password, $hash);
    
    if ($verify) {
        echo "✅ <strong>Password Hashing:</strong> SUCCESS
<br>";
        $results['password_hash'] = 'PASS';
    } else {
        echo "❌ <strong>Password Hashing:</strong> FAILED - Verification failed
<br>";
        $results['password_hash'] = 'FAIL';
    }
    
    // Test token generation
    $token = Security::generateToken();
    if (strlen($token) === 32) {
        echo "✅ <strong>Token Generation:</strong> SUCCESS
<br>";
        $results['token_gen'] = 'PASS';
    } else {
        echo "❌ <strong>Token Generation:</strong> FAILED - Invalid token length
<br>";
        $results['token_gen'] = 'FAIL';
    }
    
    // Test CSRF
    $csrf = Security::generateCSRF();
    if (!empty($csrf)) {
        echo "✅ <strong>CSRF Token:</strong> SUCCESS
<br>";
        $results['csrf'] = 'PASS';
    } else {
        echo "❌ <strong>CSRF Token:</strong> FAILED
<br>";
        $results['csrf'] = 'FAIL';
    }
    
} catch (Exception $e) {
    echo "❌ <strong>Security Functions:</strong> FAILED - " . $e->getMessage() . "
<br>";
    $results['security'] = 'FAIL';
}

// Test 3: Utility Functions
echo "<h2>🛠️ Utility Functions Test</h2>
";
try {
    // Test formatFileSize
    $size = Utils::formatFileSize(1024);
    if (strpos($size, 'KB') !== false) {
        echo "✅ <strong>formatFileSize:</strong> SUCCESS - " . $size . "
<br>";
        $results['format_size'] = 'PASS';
    } else {
        echo "❌ <strong>formatFileSize:</strong> FAILED - " . $size . "
<br>";
        $results['format_size'] = 'FAIL';
    }
    
    // Test formatCurrency
    $currency = Utils::formatCurrency(1000000);
    if (strpos($currency, 'Rp') !== false) {
        echo "✅ <strong>formatCurrency:</strong> SUCCESS - " . $currency . "
<br>";
        $results['format_currency'] = 'PASS';
    } else {
        echo "❌ <strong>formatCurrency:</strong> FAILED - " . $currency . "
<br>";
        $results['format_currency'] = 'FAIL';
    }
    
    // Test formatDate
    $date = Utils::formatDate('2024-01-01 12:00:00');
    if ($date !== '-') {
        echo "✅ <strong>formatDate:</strong> SUCCESS - " . $date . "
<br>";
        $results['format_date'] = 'PASS';
    } else {
        echo "❌ <strong>formatDate:</strong> FAILED
<br>";
        $results['format_date'] = 'FAIL';
    }
    
} catch (Exception $e) {
    echo "❌ <strong>Utility Functions:</strong> FAILED - " . $e->getMessage() . "
<br>";
    $results['utils'] = 'FAIL';
}

// Test 4: Class Loading
echo "<h2>📁 Class Loading Test</h2>
";
$classes = ['User', 'Service', 'Vehicle', 'Customer', 'Report', 'Email', 'Notification'];
foreach ($classes as $className) {
    $filePath = __DIR__ . '/classes/' . $className . '.php';
    if (file_exists($filePath)) {
        try {
            require_once $filePath;
            if (class_exists($className)) {
                echo "✅ <strong>Class {$className}:</strong> SUCCESS
<br>";
                $results["class_$className"] = 'PASS';
            } else {
                echo "❌ <strong>Class {$className}:</strong> FAILED - Class not found
<br>";
                $results["class_$className"] = 'FAIL';
            }
        } catch (Exception $e) {
            echo "❌ <strong>Class {$className}:</strong> FAILED - " . $e->getMessage() . "
<br>";
            $results["class_$className"] = 'FAIL';
        }
    } else {
        echo "⚠️ <strong>Class {$className}:</strong> FILE NOT FOUND
<br>";
        $results["class_$className"] = 'WARN';
    }
}

// Test 5: Configuration
echo "<h2>⚙️ Configuration Test</h2>
";
$configs = ['APP_NAME', 'APP_URL', 'DB_HOST', 'DB_NAME', 'ENCRYPTION_KEY'];
foreach ($configs as $config) {
    if (defined($config)) {
        echo "✅ <strong>{$config}:</strong> " . (strlen(constant($config)) > 20 ? substr(constant($config), 0, 20) . '...' : constant($config)) . "
<br>";
        $results["config_$config"] = 'PASS';
    } else {
        echo "❌ <strong>{$config}:</strong> NOT DEFINED
<br>";
        $results["config_$config"] = 'FAIL';
    }
}

// Summary
echo "<h2>📋 Test Summary</h2>
";
$passed = count(array_filter($results, fn($r) => $r === 'PASS'));
$warned = count(array_filter($results, fn($r) => $r === 'WARN'));
$failed = count(array_filter($results, fn($r) => $r === 'FAIL'));
$total = count($results);

echo "<div style='background: #e8f5e8; padding: 15px; border-radius: 5px; margin: 10px 0;'>
";
echo "<strong>Total Tests:</strong> $total<br>
";
echo "<strong style='color: green;'>✅ Passed:</strong> $passed<br>
";
echo "<strong style='color: orange;'>⚠️ Warnings:</strong> $warned<br>
";
echo "<strong style='color: red;'>❌ Failed:</strong> $failed<br>
";

$percentage = $total > 0 ? round(($passed / $total) * 100, 1) : 0;
echo "<strong>Success Rate:</strong> $percentage%<br>
";
echo "</div>
";

if ($failed === 0) {
    echo "<div style='background: #e6f3ff; padding: 15px; border-radius: 5px; margin: 10px 0;'>
";
    echo "<strong>🎯 Status:</strong> <span style='color: green; font-weight: bold;'>ALL CRITICAL FUNCTIONS WORKING ✅</span>
";
    echo "</div>
";
} else {
    echo "<div style='background: #ffe6e6; padding: 15px; border-radius: 5px; margin: 10px 0;'>
";
    echo "<strong>⚠️ Status:</strong> <span style='color: red; font-weight: bold;'>SOME ISSUES REMAIN ❌</span>
";
    echo "</div>
";
}

echo "</div>
";
?>
