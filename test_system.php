<?php
/**
 * SYSTEM VALIDATION AND TESTING SCRIPT
 * Tests all major components and validates the system integrity
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/security.php';

class SystemValidator {
    private $db;
    private $results = [];
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function runAllTests() {
        debugLog('Starting system validation tests', 'SYSTEM_TEST');
        
        echo "<h1>🔧 msbCORE System Validation Report</h1>
";
        echo "<div style='font-family: monospace; background: #f5f5f5; padding: 20px;'>
";
        
        $this->testDatabaseConnection();
        $this->testDatabaseMethods();
        $this->testClassFiles();
        $this->testConfigurationFiles();
        $this->testSecurityFeatures();
        $this->testAPIEndpoints();
        $this->testFileStructure();
        
        $this->displaySummary();
        echo "</div>
";
        
        debugLog(['results' => $this->results], 'SYSTEM_TEST_COMPLETE');
    }
    
    private function testDatabaseConnection() {
        echo "<h2>📊 Database Connection Tests</h2>
";
        
        try {
            $connection = $this->db->getConnection();
            $this->addResult('Database Connection', 'PASS', 'Successfully connected to database');
            
            // Test basic query
            $result = $this->db->query("SELECT 1 as test")->fetch();
            if ($result['test'] == 1) {
                $this->addResult('Basic Query', 'PASS', 'Basic SELECT query executed successfully');
            } else {
                $this->addResult('Basic Query', 'FAIL', 'Basic query returned unexpected result');
            }
            
            // Test transaction support
            $this->db->beginTransaction();
            $this->db->commit();
            $this->addResult('Transaction Support', 'PASS', 'Transaction methods working correctly');
            
        } catch (Exception $e) {
            $this->addResult('Database Connection', 'FAIL', $e->getMessage());
        }
    }
    
    private function testDatabaseMethods() {
        echo "<h2>🔧 Database CRUD Methods Tests</h2>
";
        
        $methods = ['selectOne', 'select', 'insert', 'update', 'delete', 'count', 'exists'];
        
        foreach ($methods as $method) {
            if (method_exists($this->db, $method)) {
                $this->addResult("Method: $method", 'PASS', "Method $method exists and is callable");
            } else {
                $this->addResult("Method: $method", 'FAIL', "Method $method is missing");
            }
        }
    }
    
    private function testClassFiles() {
        echo "<h2>📁 Class Files Tests</h2>
";
        
        $classFiles = [
            'User' => 'classes/User.php',
            'Service' => 'classes/Service.php',
            'Vehicle' => 'classes/Vehicle.php',
            'Customer' => 'classes/Customer.php',
            'Report' => 'classes/Report.php',
            'Email' => 'classes/Email.php',
            'Notification' => 'classes/Notification.php'
        ];
        
        foreach ($classFiles as $className => $filePath) {
            $fullPath = __DIR__ . '/' . $filePath;
            
            if (file_exists($fullPath)) {
                require_once $fullPath;
                
                if (class_exists($className)) {
                    try {
                        $instance = new $className();
                        $this->addResult("Class: $className", 'PASS', "Class loads and instantiates correctly");
                    } catch (Exception $e) {
                        $this->addResult("Class: $className", 'FAIL', "Class instantiation failed: " . $e->getMessage());
                    }
                } else {
                    $this->addResult("Class: $className", 'FAIL', "Class definition not found in file");
                }
            } else {
                $this->addResult("Class: $className", 'FAIL', "File not found: $filePath");
            }
        }
    }
    
    private function testConfigurationFiles() {
        echo "<h2>⚙️ Configuration Files Tests</h2>
";
        
        $configFiles = [
            'config/config.php',
            'config/database.php',
            'config/security.php'
        ];
        
        foreach ($configFiles as $file) {
            $fullPath = __DIR__ . '/' . $file;
            
            if (file_exists($fullPath)) {
                $this->addResult("Config: $file", 'PASS', "Configuration file exists");
                
                // Check if file is readable
                if (is_readable($fullPath)) {
                    $this->addResult("Readable: $file", 'PASS', "File is readable");
                } else {
                    $this->addResult("Readable: $file", 'FAIL', "File is not readable");
                }
            } else {
                $this->addResult("Config: $file", 'FAIL', "Configuration file missing");
            }
        }
        
        // Test constants
        $constants = ['APP_NAME', 'APP_VERSION', 'DB_HOST', 'DB_NAME', 'DEBUG_MODE'];
        foreach ($constants as $constant) {
            if (defined($constant)) {
                $this->addResult("Constant: $constant", 'PASS', "Constant is defined");
            } else {
                $this->addResult("Constant: $constant", 'FAIL', "Constant is not defined");
            }
        }
    }
    
    private function testSecurityFeatures() {
        echo "<h2>🔒 Security Features Tests</h2>
";
        
        // Test Security class methods
        $securityMethods = ['hashPassword', 'verifyPassword', 'generateToken', 'sanitizeInput', 'generateCSRF'];
        
        foreach ($securityMethods as $method) {
            if (method_exists('Security', $method)) {
                $this->addResult("Security: $method", 'PASS', "Security method exists");
            } else {
                $this->addResult("Security: $method", 'FAIL', "Security method missing");
            }
        }
        
        // Test password hashing
        try {
            $password = 'test123';
            $hash = Security::hashPassword($password);
            $verify = Security::verifyPassword($password, $hash);
            
            if ($verify) {
                $this->addResult('Password Hashing', 'PASS', 'Password hashing and verification working');
            } else {
                $this->addResult('Password Hashing', 'FAIL', 'Password verification failed');
            }
        } catch (Exception $e) {
            $this->addResult('Password Hashing', 'FAIL', $e->getMessage());
        }
        
        // Test token generation
        try {
            $token = Security::generateToken(32);
            if (strlen($token) === 32) {
                $this->addResult('Token Generation', 'PASS', 'Token generation working correctly');
            } else {
                $this->addResult('Token Generation', 'FAIL', 'Token length incorrect');
            }
        } catch (Exception $e) {
            $this->addResult('Token Generation', 'FAIL', $e->getMessage());
        }
    }
    
    private function testAPIEndpoints() {
        echo "<h2>🌐 API Endpoints Tests</h2>
";
        
        $apiFiles = [
            'api/dashboard.php',
            'api/inventory.php',
            'api/work_orders.php',
            'api/notifications.php',
            'api/FileUpload.php'
        ];
        
        foreach ($apiFiles as $file) {
            $fullPath = __DIR__ . '/' . $file;
            
            if (file_exists($fullPath)) {
                $this->addResult("API: $file", 'PASS', "API endpoint file exists");
                
                // Check for PHP syntax errors
                $output = shell_exec("php -l "$fullPath" 2>&1");
                if (strpos($output, 'No syntax errors') !== false) {
                    $this->addResult("Syntax: $file", 'PASS', "No PHP syntax errors");
                } else {
                    $this->addResult("Syntax: $file", 'FAIL', "PHP syntax errors found");
                }
            } else {
                $this->addResult("API: $file", 'FAIL', "API endpoint file missing");
            }
        }
    }
    
    private function testFileStructure() {
        echo "<h2>📂 File Structure Tests</h2>
";
        
        $requiredDirs = [
            'assets/css',
            'assets/js',
            'assets/uploads',
            'classes',
            'config',
            'includes',
            'api',
            'logs'
        ];
        
        foreach ($requiredDirs as $dir) {
            $fullPath = __DIR__ . '/' . $dir;
            
            if (is_dir($fullPath)) {
                $this->addResult("Directory: $dir", 'PASS', "Directory exists");
                
                if (is_writable($fullPath)) {
                    $this->addResult("Writable: $dir", 'PASS', "Directory is writable");
                } else {
                    $this->addResult("Writable: $dir", 'WARN', "Directory is not writable");
                }
            } else {
                $this->addResult("Directory: $dir", 'FAIL', "Directory missing");
            }
        }
        
        // Test critical files
        $criticalFiles = [
            'index.php',
            'login.php',
            'composer.json'
        ];
        
        foreach ($criticalFiles as $file) {
            $fullPath = __DIR__ . '/' . $file;
            
            if (file_exists($fullPath)) {
                $this->addResult("File: $file", 'PASS', "Critical file exists");
            } else {
                $this->addResult("File: $file", 'FAIL', "Critical file missing");
            }
        }
    }
    
    private function addResult($test, $status, $message) {
        $this->results[] = [
            'test' => $test,
            'status' => $status,
            'message' => $message
        ];
        
        $icon = $status === 'PASS' ? '✅' : ($status === 'WARN' ? '⚠️' : '❌');
        $color = $status === 'PASS' ? 'green' : ($status === 'WARN' ? 'orange' : 'red');
        
        echo "<div style='color: $color; margin: 5px 0;'>$icon <strong>$test:</strong> $message</div>
";
    }
    
    private function displaySummary() {
        echo "<h2>📋 Test Summary</h2>
";
        
        $passed = count(array_filter($this->results, fn($r) => $r['status'] === 'PASS'));
        $warned = count(array_filter($this->results, fn($r) => $r['status'] === 'WARN'));
        $failed = count(array_filter($this->results, fn($r) => $r['status'] === 'FAIL'));
        $total = count($this->results);
        
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
        
        if ($failed > 0) {
            echo "<div style='background: #ffe6e6; padding: 15px; border-radius: 5px; margin: 10px 0;'>
";
            echo "<strong>⚠️ Issues Found:</strong><br>
";
            foreach ($this->results as $result) {
                if ($result['status'] === 'FAIL') {
                    echo "• {$result['test']}: {$result['message']}<br>
";
                }
            }
            echo "</div>
";
        }
        
        echo "<div style='background: #e6f3ff; padding: 15px; border-radius: 5px; margin: 10px 0;'>
";
        echo "<strong>🎯 System Status:</strong> ";
        if ($failed === 0) {
            echo "<span style='color: green; font-weight: bold;'>HEALTHY ✅</span>";
        } elseif ($failed <= 3) {
            echo "<span style='color: orange; font-weight: bold;'>NEEDS ATTENTION ⚠️</span>";
        } else {
            echo "<span style='color: red; font-weight: bold;'>CRITICAL ISSUES ❌</span>";
        }
        echo "</div>
";
    }
}

// Run the validation if accessed directly
if (basename($_SERVER['PHP_SELF']) === 'test_system.php') {
    $validator = new SystemValidator();
    $validator->runAllTests();
}
?>
