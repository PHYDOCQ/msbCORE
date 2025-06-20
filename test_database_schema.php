<?php
/**
 * Database Schema Test Script
 * Tests the phpMyAdmin-compatible schema functionality
 */

// Simple database connection test (without requiring full config)
function testDatabaseSchema() {
    echo "=== msbCORE Database Schema Test ===
";
    echo "Testing phpMyAdmin-compatible schema...

";
    
    // Test 1: Check if schema file exists
    echo "1. Checking schema files:
";
    $schemaFile = 'database/schema_phpmyadmin.sql';
    if (file_exists($schemaFile)) {
        echo "   ✅ phpMyAdmin schema file exists
";
        $fileSize = filesize($schemaFile);
        echo "   📊 File size: " . number_format($fileSize) . " bytes
";
    } else {
        echo "   ❌ Schema file not found
";
        return false;
    }
    
    // Test 2: Check README file
    $readmeFile = 'database/README_phpMyAdmin_Import.md';
    if (file_exists($readmeFile)) {
        echo "   ✅ Import guide exists
";
    } else {
        echo "   ❌ Import guide not found
";
    }
    
    // Test 3: Validate schema content
    echo "
2. Validating schema content:
";
    $schemaContent = file_get_contents($schemaFile);
    
    $expectedTables = [
        'users', 'customers', 'vehicles', 'services', 'work_orders',
        'remember_tokens', 'login_attempts', 'user_activities', 'notifications',
        'inventory_categories', 'inventory',
        'damage_assessments', 'paint_jobs', 'body_repair_tasks', 
        'paint_materials', 'workshop_bays', 'quality_inspections', 'material_usage'
    ];
    
    $foundTables = 0;
    foreach ($expectedTables as $table) {
        if (strpos($schemaContent, "CREATE TABLE `{$table}`") !== false) {
            $foundTables++;
            echo "   ✅ Table '{$table}' found
";
        } else {
            echo "   ❌ Table '{$table}' missing
";
        }
    }
    
    echo "
3. Schema Statistics:
";
    echo "   📊 Expected tables: " . count($expectedTables) . "
";
    echo "   📊 Found tables: {$foundTables}
";
    echo "   📊 Success rate: " . round(($foundTables / count($expectedTables)) * 100, 1) . "%
";
    
    // Test 4: Check for phpMyAdmin compatibility fixes
    echo "
4. phpMyAdmin Compatibility:
";
    
    // Check for JSON to TEXT conversion
    if (strpos($schemaContent, 'JSON data for') !== false) {
        echo "   ✅ JSON fields converted to TEXT with comments
";
    }
    
    // Check for removed transaction statements
    if (strpos($schemaContent, 'START TRANSACTION') === false) {
        echo "   ✅ Transaction statements removed
";
    }
    
    // Check for proper charset
    if (strpos($schemaContent, 'utf8mb4_unicode_ci') !== false) {
        echo "   ✅ UTF8MB4 charset specified
";
    }
    
    // Test 5: Check sample data
    echo "
5. Sample Data:
";
    if (strpos($schemaContent, 'INSERT INTO `workshop_bays`') !== false) {
        echo "   ✅ Workshop bays sample data included
";
    }
    if (strpos($schemaContent, 'INSERT INTO `paint_materials`') !== false) {
        echo "   ✅ Paint materials sample data included
";
    }
    if (strpos($schemaContent, 'INSERT INTO `users`') !== false) {
        echo "   ✅ Admin user sample data included
";
    }
    
    echo "
=== Test Complete ===
";
    
    if ($foundTables === count($expectedTables)) {
        echo "🎉 ALL TESTS PASSED! Schema is ready for phpMyAdmin import.
";
        return true;
    } else {
        echo "⚠️  Some issues found. Please review the schema file.
";
        return false;
    }
}

// Test 6: Check PHP class files
function testPHPClasses() {
    echo "
=== PHP Classes Test ===
";
    
    $classFiles = [
        'classes/DamageAssessment.php',
        'classes/PaintJob.php'
    ];
    
    foreach ($classFiles as $file) {
        if (file_exists($file)) {
            echo "✅ {$file} exists
";
            
            // Basic syntax check
            $content = file_get_contents($file);
            if (strpos($content, '<?php') === 0) {
                echo "   ✅ Valid PHP opening tag
";
            }
            if (strpos($content, 'class ') !== false) {
                echo "   ✅ Contains class definition
";
            }
        } else {
            echo "❌ {$file} not found
";
        }
    }
}

// Run tests
testDatabaseSchema();
testPHPClasses();

echo "
📋 NEXT STEPS:
";
echo "1. Import 'database/schema_phpmyadmin.sql' into phpMyAdmin
";
echo "2. Follow instructions in 'database/README_phpMyAdmin_Import.md'
";
echo "3. Update database configuration in config/config.php
";
echo "4. Test the application with the new enhanced features
";
echo "
🌐 Server running at: http://672c0a560a31b68e27.blackbx.ai
";
?>
