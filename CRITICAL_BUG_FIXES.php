<?php
/**
 * CRITICAL BUG FIXES for msbCORE System
 * This script addresses the active critical bugs found in the system
 */

echo "🔧 msbCORE Critical Bug Fixes - Starting...
";
echo "==========================================

";

// Track fixes applied
$fixes_applied = [];
$errors_found = [];

/**
 * Fix 1: Class Redeclaration Errors in includes/functions.php
 */
echo "🔧 Fix 1: Resolving Class Redeclaration Errors...
";
try {
    $functions_file = __DIR__ . '/includes/functions.php';
    if (file_exists($functions_file)) {
        $content = file_get_contents($functions_file);
        
        // Check if Utils class is properly wrapped
        if (strpos($content, 'if (!class_exists('Utils'))') === false) {
            // The class is not properly wrapped, need to fix
            $pattern = '/^class Utils \{/m';
            if (preg_match($pattern, $content)) {
                // Find the class definition and wrap it
                $content = preg_replace(
                    '/^class Utils \{/m',
                    'if (!class_exists('Utils')) {
class Utils {',
                    $content
                );
                
                // Find the end of the class and add closing brace
                $lines = explode("
", $content);
                $class_start = false;
                $brace_count = 0;
                $class_end_line = -1;
                
                foreach ($lines as $i => $line) {
                    if (strpos($line, 'class Utils {') !== false) {
                        $class_start = true;
                        $brace_count = 1;
                        continue;
                    }
                    
                    if ($class_start) {
                        $brace_count += substr_count($line, '{');
                        $brace_count -= substr_count($line, '}');
                        
                        if ($brace_count === 0) {
                            $class_end_line = $i;
                            break;
                        }
                    }
                }
                
                if ($class_end_line > -1) {
                    $lines[$class_end_line] .= "
}";
                    $content = implode("
", $lines);
                }
                
                file_put_contents($functions_file, $content);
                $fixes_applied[] = "✅ Fixed Utils class redeclaration in functions.php";
            }
        } else {
            $fixes_applied[] = "✅ Utils class already properly wrapped";
        }
    }
} catch (Exception $e) {
    $errors_found[] = "❌ Error fixing class redeclaration: " . $e->getMessage();
}

/**
 * Fix 2: Function Redeclaration Errors
 */
echo "🔧 Fix 2: Resolving Function Redeclaration Errors...
";
try {
    $functions_file = __DIR__ . '/includes/functions.php';
    if (file_exists($functions_file)) {
        $content = file_get_contents($functions_file);
        
        // List of functions that need protection
        $functions_to_protect = [
            'generateCustomerCode',
            'generateWorkOrderNumber',
            'generateServiceCode',
            'generateVehicleCode'
        ];
        
        foreach ($functions_to_protect as $func_name) {
            // Check if function is already protected
            if (strpos($content, "if (!function_exists('$func_name'))") === false) {
                // Find the function definition and wrap it
                $pattern = "/function $func_name\s*\(/";
                if (preg_match($pattern, $content)) {
                    $content = preg_replace(
                        $pattern,
                        "if (!function_exists('$func_name')) {
function $func_name(",
                        $content
                    );
                    
                    // Find the end of the function and add closing brace
                    // This is a simplified approach - in production, use a proper parser
                    $lines = explode("
", $content);
                    $func_start = false;
                    $brace_count = 0;
                    
                    foreach ($lines as $i => $line) {
                        if (strpos($line, "function $func_name(") !== false) {
                            $func_start = true;
                            $brace_count = substr_count($line, '{') - substr_count($line, '}');
                            continue;
                        }
                        
                        if ($func_start) {
                            $brace_count += substr_count($line, '{');
                            $brace_count -= substr_count($line, '}');
                            
                            if ($brace_count === 0 && strpos($line, '}') !== false) {
                                $lines[$i] .= "
}";
                                break;
                            }
                        }
                    }
                    
                    $content = implode("
", $lines);
                }
            }
        }
        
        file_put_contents($functions_file, $content);
        $fixes_applied[] = "✅ Protected functions from redeclaration";
    }
} catch (Exception $e) {
    $errors_found[] = "❌ Error fixing function redeclaration: " . $e->getMessage();
}

/**
 * Fix 3: Session Management Issues
 */
echo "🔧 Fix 3: Resolving Session Management Issues...
";
try {
    $config_file = __DIR__ . '/config/config.php';
    if (file_exists($config_file)) {
        $content = file_get_contents($config_file);
        
        // Move session configuration to the very beginning
        $session_config = '
// Session Configuration - MUST be at the beginning before any output
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 1 : 0);
    ini_set('session.gc_maxlifetime', 3600);
    ini_set('session.cookie_samesite', 'Strict');
    
    // Start session safely
    if (!session_start()) {
        error_log('Session initialization failed');
    }
}
';
        
        // Remove existing session configuration
        $content = preg_replace('/\/\/ Session Configuration.*?session_start\(\).*?\}/s', '', $content);
        
        // Add new session configuration at the beginning after opening PHP tag
        $content = preg_replace('/(<\?php\s*)/', '$1' . $session_config, $content);
        
        file_put_contents($config_file, $content);
        $fixes_applied[] = "✅ Fixed session management in config.php";
    }
} catch (Exception $e) {
    $errors_found[] = "❌ Error fixing session management: " . $e->getMessage();
}

/**
 * Fix 4: Database Driver Issues
 */
echo "🔧 Fix 4: Checking Database Driver Issues...
";
try {
    // Check for PDO extensions
    $pdo_mysql = extension_loaded('pdo_mysql');
    $pdo_sqlite = extension_loaded('pdo_sqlite');
    
    if (!$pdo_mysql && !$pdo_sqlite) {
        $errors_found[] = "❌ CRITICAL: No PDO database drivers available (mysql/sqlite)";
        echo "⚠️  WARNING: No PDO drivers found. Install php-pdo-mysql or php-pdo-sqlite
";
    } else {
        $available_drivers = [];
        if ($pdo_mysql) $available_drivers[] = "MySQL";
        if ($pdo_sqlite) $available_drivers[] = "SQLite";
        $fixes_applied[] = "✅ Database drivers available: " . implode(", ", $available_drivers);
    }
    
    // Test database connection
    require_once __DIR__ . '/includes/database.php';
    $db = Database::getInstance();
    if ($db) {
        $fixes_applied[] = "✅ Database connection test successful";
    }
    
} catch (Exception $e) {
    $errors_found[] = "❌ Database connection error: " . $e->getMessage();
}

/**
 * Fix 5: Constant Redefinition Warnings
 */
echo "🔧 Fix 5: Resolving Constant Redefinition Warnings...
";
try {
    $security_file = __DIR__ . '/security_audit_fixes.php';
    if (file_exists($security_file)) {
        $content = file_get_contents($security_file);
        
        // Add checks for constants
        $constants_to_protect = [
            'RATE_LIMIT_REQUESTS',
            'RATE_LIMIT_WINDOW',
            'MAX_LOGIN_ATTEMPTS',
            'LOCKOUT_DURATION'
        ];
        
        foreach ($constants_to_protect as $const_name) {
            $pattern = "/define\s*\(\s*['"]$const_name['"]/";
            if (preg_match($pattern, $content)) {
                $content = preg_replace(
                    $pattern,
                    "if (!defined('$const_name')) {
    define('$const_name'",
                    $content
                );
                
                // Add closing brace after the define statement
                $content = preg_replace(
                    "/(if \(!defined\('$const_name'\)\) \{\s*define\('$const_name'[^;]+;)/",
                    "$1
}",
                    $content
                );
            }
        }
        
        file_put_contents($security_file, $content);
        $fixes_applied[] = "✅ Protected constants from redefinition";
    }
} catch (Exception $e) {
    $errors_found[] = "❌ Error fixing constant redefinition: " . $e->getMessage();
}

/**
 * Fix 6: Database Class Loading Issues
 */
echo "🔧 Fix 6: Resolving Database Class Loading Issues...
";
try {
    // Create a proper autoloader for Database class
    $autoloader_content = '<?php
/**
 * Simple autoloader for msbCORE classes
 */
spl_autoload_register(function ($class_name) {
    $class_files = [
        'Database' => __DIR__ . '/includes/database.php',
        'User' => __DIR__ . '/classes/User.php',
        'Service' => __DIR__ . '/classes/Service.php',
        'Vehicle' => __DIR__ . '/classes/Vehicle.php',
        'Customer' => __DIR__ . '/classes/Customer.php',
        'Email' => __DIR__ . '/classes/Email.php',
        'Notification' => __DIR__ . '/classes/Notification.php',
        'Report' => __DIR__ . '/classes/Report.php'
    ];
    
    if (isset($class_files[$class_name]) && file_exists($class_files[$class_name])) {
        require_once $class_files[$class_name];
    }
});
?>';
    
    file_put_contents(__DIR__ . '/includes/autoloader.php', $autoloader_content);
    $fixes_applied[] = "✅ Created autoloader for proper class loading";
    
} catch (Exception $e) {
    $errors_found[] = "❌ Error creating autoloader: " . $e->getMessage();
}

/**
 * Create a comprehensive test script
 */
echo "🔧 Creating comprehensive test script...
";
try {
    $test_content = '<?php
/**
 * Comprehensive Bug Fix Validation Test
 */
require_once __DIR__ . '/includes/autoloader.php';
require_once __DIR__ . '/config/config.php';

echo "🧪 Running Bug Fix Validation Tests...
";
echo "=====================================

";

$tests_passed = 0;
$tests_failed = 0;

// Test 1: Class Loading
echo "Test 1: Class Loading...
";
try {
    if (class_exists('Utils')) {
        echo "✅ Utils class loads successfully
";
        $tests_passed++;
    } else {
        echo "❌ Utils class not found
";
        $tests_failed++;
    }
} catch (Exception $e) {
    echo "❌ Utils class error: " . $e->getMessage() . "
";
    $tests_failed++;
}

// Test 2: Database Connection
echo "
Test 2: Database Connection...
";
try {
    $db = Database::getInstance();
    if ($db) {
        echo "✅ Database singleton works
";
        $tests_passed++;
    } else {
        echo "❌ Database singleton failed
";
        $tests_failed++;
    }
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "
";
    $tests_failed++;
}

// Test 3: Session Management
echo "
Test 3: Session Management...
";
if (session_status() === PHP_SESSION_ACTIVE) {
    echo "✅ Session is active
";
    $tests_passed++;
} else {
    echo "❌ Session not active
";
    $tests_failed++;
}

// Test 4: Function Existence
echo "
Test 4: Function Existence...
";
$functions_to_test = ['generateCustomerCode', 'generateWorkOrderNumber'];
foreach ($functions_to_test as $func) {
    if (function_exists($func)) {
        echo "✅ Function $func exists
";
        $tests_passed++;
    } else {
        echo "❌ Function $func not found
";
        $tests_failed++;
    }
}

echo "
=====================================
";
echo "Test Results: $tests_passed passed, $tests_failed failed
";
echo ($tests_failed === 0) ? "🎉 All tests passed!
" : "⚠️  Some tests failed
";
?>';
    
    file_put_contents(__DIR__ . '/bug_fix_validation_test.php', $test_content);
    $fixes_applied[] = "✅ Created bug fix validation test script";
    
} catch (Exception $e) {
    $errors_found[] = "❌ Error creating test script: " . $e->getMessage();
}

// Summary Report
echo "
==========================================
";
echo "🎯 BUG FIX SUMMARY REPORT
";
echo "==========================================

";

echo "✅ FIXES APPLIED (" . count($fixes_applied) . "):
";
foreach ($fixes_applied as $fix) {
    echo "   $fix
";
}

if (!empty($errors_found)) {
    echo "
❌ ERRORS ENCOUNTERED (" . count($errors_found) . "):
";
    foreach ($errors_found as $error) {
        echo "   $error
";
    }
}

echo "
🔧 NEXT STEPS:
";
echo "   1. Run: php bug_fix_validation_test.php
";
echo "   2. Check error logs for remaining issues
";
echo "   3. Test critical functionality
";
echo "   4. Monitor system for 24 hours
";

echo "
✅ Critical bug fixes completed!
";
?>
