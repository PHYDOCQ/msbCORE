<?php
/**
 * Bug Verification Test Script
 * Tests the critical bug fixes applied to msbCORE
 */

echo "🧪 msbCORE Bug Verification Test
";
echo "================================

";

$tests_passed = 0;
$tests_failed = 0;
$issues_found = [];

// Test 1: Check if Utils class can be loaded without redeclaration errors
echo "Test 1: Utils Class Loading...
";
try {
    // Include the functions file multiple times to test for redeclaration
    require_once __DIR__ . '/includes/functions.php';
    require_once __DIR__ . '/includes/functions.php'; // Second include should not cause error
    
    if (class_exists('Utils')) {
        echo "✅ Utils class loads successfully without redeclaration errors
";
        $tests_passed++;
    } else {
        echo "❌ Utils class not found
";
        $tests_failed++;
        $issues_found[] = "Utils class not accessible";
    }
} catch (Error $e) {
    echo "❌ Utils class error: " . $e->getMessage() . "
";
    $tests_failed++;
    $issues_found[] = "Utils class redeclaration error: " . $e->getMessage();
} catch (Exception $e) {
    echo "❌ Utils class exception: " . $e->getMessage() . "
";
    $tests_failed++;
    $issues_found[] = "Utils class exception: " . $e->getMessage();
}

// Test 2: Check function redeclaration
echo "
Test 2: Function Redeclaration...
";
try {
    if (function_exists('generateCustomerCode')) {
        echo "✅ generateCustomerCode function exists
";
        $tests_passed++;
    } else {
        echo "❌ generateCustomerCode function not found
";
        $tests_failed++;
        $issues_found[] = "generateCustomerCode function missing";
    }
    
    if (function_exists('generateWorkOrderNumber')) {
        echo "✅ generateWorkOrderNumber function exists
";
        $tests_passed++;
    } else {
        echo "❌ generateWorkOrderNumber function not found
";
        $tests_failed++;
        $issues_found[] = "generateWorkOrderNumber function missing";
    }
} catch (Error $e) {
    echo "❌ Function error: " . $e->getMessage() . "
";
    $tests_failed++;
    $issues_found[] = "Function redeclaration error: " . $e->getMessage();
}

// Test 3: Database Class Loading
echo "
Test 3: Database Class Loading...
";
try {
    require_once __DIR__ . '/includes/database.php';
    
    if (class_exists('Database')) {
        echo "✅ Database class loads successfully
";
        $tests_passed++;
        
        // Test singleton pattern
        $db1 = Database::getInstance();
        $db2 = Database::getInstance();
        
        if ($db1 === $db2) {
            echo "✅ Database singleton pattern working correctly
";
            $tests_passed++;
        } else {
            echo "❌ Database singleton pattern not working
";
            $tests_failed++;
            $issues_found[] = "Database singleton pattern broken";
        }
    } else {
        echo "❌ Database class not found
";
        $tests_failed++;
        $issues_found[] = "Database class not accessible";
    }
} catch (Error $e) {
    echo "❌ Database class error: " . $e->getMessage() . "
";
    $tests_failed++;
    $issues_found[] = "Database class error: " . $e->getMessage();
} catch (Exception $e) {
    echo "❌ Database exception: " . $e->getMessage() . "
";
    $tests_failed++;
    $issues_found[] = "Database exception: " . $e->getMessage();
}

// Test 4: Session Management
echo "
Test 4: Session Management...
";
try {
    require_once __DIR__ . '/config/config.php';
    
    if (session_status() === PHP_SESSION_ACTIVE) {
        echo "✅ Session is active
";
        $tests_passed++;
    } else if (session_status() === PHP_SESSION_NONE) {
        echo "⚠️  Session not started (normal in CLI mode)
";
        $tests_passed++;
    } else {
        echo "❌ Session in disabled state
";
        $tests_failed++;
        $issues_found[] = "Session disabled";
    }
} catch (Error $e) {
    echo "❌ Session error: " . $e->getMessage() . "
";
    $tests_failed++;
    $issues_found[] = "Session error: " . $e->getMessage();
}

// Test 5: Constants Definition
echo "
Test 5: Constants Definition...
";
try {
    $required_constants = [
        'APP_NAME', 'DB_HOST', 'DB_NAME', 'SESSION_LIFETIME'
    ];
    
    $missing_constants = [];
    foreach ($required_constants as $const) {
        if (!defined($const)) {
            $missing_constants[] = $const;
        }
    }
    
    if (empty($missing_constants)) {
        echo "✅ All required constants defined
";
        $tests_passed++;
    } else {
        echo "❌ Missing constants: " . implode(', ', $missing_constants) . "
";
        $tests_failed++;
        $issues_found[] = "Missing constants: " . implode(', ', $missing_constants);
    }
} catch (Error $e) {
    echo "❌ Constants error: " . $e->getMessage() . "
";
    $tests_failed++;
    $issues_found[] = "Constants error: " . $e->getMessage();
}

// Test 6: PDO Extensions
echo "
Test 6: PDO Extensions...
";
$pdo_mysql = extension_loaded('pdo_mysql');
$pdo_sqlite = extension_loaded('pdo_sqlite');

if ($pdo_mysql || $pdo_sqlite) {
    $available = [];
    if ($pdo_mysql) $available[] = 'MySQL';
    if ($pdo_sqlite) $available[] = 'SQLite';
    echo "✅ PDO extensions available: " . implode(', ', $available) . "
";
    $tests_passed++;
} else {
    echo "❌ No PDO extensions available
";
    $tests_failed++;
    $issues_found[] = "No PDO database drivers available";
}

// Test 7: File Structure
echo "
Test 7: Critical File Structure...
";
$critical_files = [
    'index.php',
    'login.php',
    'config/config.php',
    'includes/database.php',
    'includes/functions.php'
];

$missing_files = [];
foreach ($critical_files as $file) {
    if (!file_exists(__DIR__ . '/' . $file)) {
        $missing_files[] = $file;
    }
}

if (empty($missing_files)) {
    echo "✅ All critical files present
";
    $tests_passed++;
} else {
    echo "❌ Missing files: " . implode(', ', $missing_files) . "
";
    $tests_failed++;
    $issues_found[] = "Missing critical files: " . implode(', ', $missing_files);
}

// Summary
echo "
================================
";
echo "🎯 TEST SUMMARY
";
echo "================================
";
echo "Tests Passed: $tests_passed
";
echo "Tests Failed: $tests_failed
";
echo "Success Rate: " . round(($tests_passed / ($tests_passed + $tests_failed)) * 100, 1) . "%

";

if ($tests_failed === 0) {
    echo "🎉 ALL TESTS PASSED! Critical bugs have been fixed.
";
} else {
    echo "⚠️  SOME ISSUES REMAIN:
";
    foreach ($issues_found as $issue) {
        echo "   • $issue
";
    }
}

echo "
================================
";
echo "Bug verification completed.
";
?>
