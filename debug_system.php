<?php
// Comprehensive System Debug Script
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔧 msbCORE System Debug Report</h1>";
echo "<style>
body { font-family: Arial, sans-serif; margin: 20px; }
.section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
.pass { color: green; font-weight: bold; }
.fail { color: red; font-weight: bold; }
.warn { color: orange; font-weight: bold; }
.info { color: blue; font-weight: bold; }
</style>";

// Test 1: Configuration Files
echo "<div class='section'>";
echo "<h2>📋 Configuration Files</h2>";

$configFiles = [
    'config/config.php' => 'Main Configuration',
    'config/database.php' => 'Database Configuration', 
    'config/security.php' => 'Security Configuration',
    'composer.json' => 'Composer Configuration'
];

foreach ($configFiles as $file => $description) {
    if (file_exists($file)) {
        echo "<div class='pass'>✅ $description: EXISTS</div>";
        
        // Check if file is readable
        if (is_readable($file)) {
            echo "<div class='info'>   📖 File is readable</div>";
        } else {
            echo "<div class='warn'>   ⚠️ File is not readable</div>";
        }
        
        // Check file size
        $size = filesize($file);
        if ($size > 0) {
            echo "<div class='info'>   📏 File size: " . number_format($size) . " bytes</div>";
        } else {
            echo "<div class='fail'>   ❌ File is empty</div>";
        }
    } else {
        echo "<div class='fail'>❌ $description: MISSING</div>";
    }
}
echo "</div>";

// Test 2: Include Configuration
echo "<div class='section'>";
echo "<h2>🔗 Loading Configuration</h2>";

try {
    require_once 'config/config.php';
    echo "<div class='pass'>✅ Main config loaded successfully</div>";
    
    // Check important constants
    $constants = ['APP_NAME', 'DB_HOST', 'DB_NAME', 'DB_USER', 'DEBUG_MODE'];
    foreach ($constants as $const) {
        if (defined($const)) {
            $value = constant($const);
            echo "<div class='info'>   📌 $const: " . (is_bool($value) ? ($value ? 'true' : 'false') : $value) . "</div>";
        } else {
            echo "<div class='fail'>   ❌ $const: NOT DEFINED</div>";
        }
    }
} catch (Exception $e) {
    echo "<div class='fail'>❌ Error loading config: " . $e->getMessage() . "</div>";
}
echo "</div>";

// Test 3: Database Connection
echo "<div class='section'>";
echo "<h2>🗄️ Database Connection</h2>";

try {
    require_once 'config/database.php';
    echo "<div class='pass'>✅ Database class loaded successfully</div>";
    
    $db = Database::getInstance();
    echo "<div class='pass'>✅ Database instance created</div>";
    
    $connection = $db->getConnection();
    if ($connection) {
        echo "<div class='pass'>✅ Database connection established</div>";
        
        // Test basic query
        try {
            $result = $db->selectOne("SELECT 1 as test, NOW() as current_time");
            if ($result && $result['test'] == 1) {
                echo "<div class='pass'>✅ Basic query test passed</div>";
                echo "<div class='info'>   🕒 Server time: " . $result['current_time'] . "</div>";
            } else {
                echo "<div class='fail'>❌ Basic query test failed</div>";
            }
        } catch (Exception $e) {
            echo "<div class='fail'>❌ Query test error: " . $e->getMessage() . "</div>";
        }
        
        // Test CRUD methods
        $methods = ['selectOne', 'select', 'insert', 'update', 'delete', 'count', 'exists'];
        foreach ($methods as $method) {
            if (method_exists($db, $method)) {
                echo "<div class='pass'>✅ Method $method: EXISTS</div>";
            } else {
                echo "<div class='fail'>❌ Method $method: MISSING</div>";
            }
        }
        
    } else {
        echo "<div class='fail'>❌ Database connection failed</div>";
    }
} catch (Exception $e) {
    echo "<div class='fail'>❌ Database error: " . $e->getMessage() . "</div>";
}
echo "</div>";

// Test 4: Class Files
echo "<div class='section'>";
echo "<h2>📁 Class Files</h2>";

$classFiles = [
    'classes/User.php' => 'User Management',
    'classes/Customer.php' => 'Customer Management',
    'classes/Vehicle.php' => 'Vehicle Management', 
    'classes/Service.php' => 'Service Management',
    'classes/Email.php' => 'Email System',
    'classes/Notification.php' => 'Notification System',
    'classes/Report.php' => 'Report System'
];

foreach ($classFiles as $file => $description) {
    if (file_exists($file)) {
        echo "<div class='pass'>✅ $description: EXISTS</div>";
        
        // Try to include and check syntax
        try {
            $content = file_get_contents($file);
            if (strpos($content, '<?php') === 0) {
                echo "<div class='info'>   📝 Valid PHP file</div>";
            } else {
                echo "<div class='warn'>   ⚠️ Missing PHP opening tag</div>";
            }
            
            // Check for class definition
            $className = basename($file, '.php');
            if (strpos($content, "class $className") !== false) {
                echo "<div class='info'>   🏗️ Class $className defined</div>";
            } else {
                echo "<div class='warn'>   ⚠️ Class $className not found</div>";
            }
        } catch (Exception $e) {
            echo "<div class='fail'>   ❌ Error reading file: " . $e->getMessage() . "</div>";
        }
    } else {
        echo "<div class='fail'>❌ $description: MISSING</div>";
    }
}
echo "</div>";

// Test 5: Include Files
echo "<div class='section'>";
echo "<h2>📚 Include Files</h2>";

$includeFiles = [
    'includes/functions.php' => 'Utility Functions',
    'includes/auth.php' => 'Authentication',
    'includes/header.php' => 'Header Template',
    'includes/footer.php' => 'Footer Template',
    'includes/validation.php' => 'Validation Functions'
];

foreach ($includeFiles as $file => $description) {
    if (file_exists($file)) {
        echo "<div class='pass'>✅ $description: EXISTS</div>";
        
        // Check file size
        $size = filesize($file);
        echo "<div class='info'>   📏 Size: " . number_format($size) . " bytes</div>";
    } else {
        echo "<div class='fail'>❌ $description: MISSING</div>";
    }
}
echo "</div>";

// Test 6: Functions.php Specific Tests
echo "<div class='section'>";
echo "<h2>🔧 Functions.php Analysis</h2>";

if (file_exists('includes/functions.php')) {
    try {
        require_once 'includes/functions.php';
        echo "<div class='pass'>✅ Functions.php loaded successfully</div>";
        
        // Check if Utils class exists
        if (class_exists('Utils')) {
            echo "<div class='pass'>✅ Utils class exists</div>";
            
            // Check Utils methods
            $utilsMethods = ['generateCustomerCode', 'generateWorkOrderNumber', 'formatCurrency', 'formatDate'];
            foreach ($utilsMethods as $method) {
                if (method_exists('Utils', $method)) {
                    echo "<div class='pass'>✅ Utils::$method: EXISTS</div>";
                } else {
                    echo "<div class='fail'>❌ Utils::$method: MISSING</div>";
                }
            }
        } else {
            echo "<div class='fail'>❌ Utils class not found</div>";
        }
        
        // Check helper functions
        $helperFunctions = ['generateCustomerCode', 'generateWorkOrderNumber', 'formatCurrency', 'debugLog'];
        foreach ($helperFunctions as $func) {
            if (function_exists($func)) {
                echo "<div class='pass'>✅ Function $func: EXISTS</div>";
            } else {
                echo "<div class='fail'>❌ Function $func: MISSING</div>";
            }
        }
        
    } catch (Exception $e) {
        echo "<div class='fail'>❌ Error loading functions.php: " . $e->getMessage() . "</div>";
    }
} else {
    echo "<div class='fail'>❌ functions.php file missing</div>";
}
echo "</div>";

// Test 7: Directory Structure
echo "<div class='section'>";
echo "<h2>📂 Directory Structure</h2>";

$directories = [
    'assets' => 'Assets Directory',
    'assets/css' => 'CSS Files',
    'assets/js' => 'JavaScript Files', 
    'assets/uploads' => 'Upload Directory',
    'api' => 'API Endpoints',
    'classes' => 'PHP Classes',
    'config' => 'Configuration',
    'includes' => 'Include Files',
    'logs' => 'Log Files',
    'database' => 'Database Files'
];

foreach ($directories as $dir => $description) {
    if (is_dir($dir)) {
        echo "<div class='pass'>✅ $description: EXISTS</div>";
        
        // Check if writable
        if (is_writable($dir)) {
            echo "<div class='info'>   ✏️ Directory is writable</div>";
        } else {
            echo "<div class='warn'>   ⚠️ Directory is not writable</div>";
        }
        
        // Count files
        $files = scandir($dir);
        $fileCount = count($files) - 2; // Exclude . and ..
        echo "<div class='info'>   📄 Contains $fileCount items</div>";
    } else {
        echo "<div class='fail'>❌ $description: MISSING</div>";
    }
}
echo "</div>";

// Test 8: Log Files Analysis
echo "<div class='section'>";
echo "<h2>📋 Log Files Analysis</h2>";

$logFiles = ['logs/error.log', 'logs/debug.log'];
foreach ($logFiles as $logFile) {
    if (file_exists($logFile)) {
        echo "<div class='pass'>✅ $logFile: EXISTS</div>";
        
        $size = filesize($logFile);
        echo "<div class='info'>   📏 Size: " . number_format($size) . " bytes</div>";
        
        if ($size > 0) {
            $lines = file($logFile);
            $lineCount = count($lines);
            echo "<div class='info'>   📄 Lines: $lineCount</div>";
            
            // Show last few lines
            if ($lineCount > 0) {
                echo "<div class='info'>   📝 Last entry: " . trim(end($lines)) . "</div>";
            }
        } else {
            echo "<div class='info'>   📄 Log file is empty</div>";
        }
    } else {
        echo "<div class='warn'>⚠️ $logFile: MISSING</div>";
    }
}
echo "</div>";

echo "<div class='section'>";
echo "<h2>✅ Debug Complete</h2>";
echo "<p>System debug analysis completed at " . date('Y-m-d H:i:s') . "</p>";
echo "</div>";
?>
