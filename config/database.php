<?php
/**
 * Database Configuration
 * Defines database connection constants safely
 */

// Prevent constant redefinition
if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
}

if (!defined('DB_NAME')) {
    define('DB_NAME', 'msbcore_bengkel');
}

if (!defined('DB_USER')) {
    define('DB_USER', 'root');
}

if (!defined('DB_PASS')) {
    define('DB_PASS', '');
}

// Database connection options
if (!defined('DB_CHARSET')) {
    define('DB_CHARSET', 'utf8mb4');
}

if (!defined('DB_COLLATION')) {
    define('DB_COLLATION', 'utf8mb4_unicode_ci');
}

// Connection timeout settings
if (!defined('DB_TIMEOUT')) {
    define('DB_TIMEOUT', 30);
}

// SSL settings for production
if (!defined('DB_SSL_MODE')) {
    define('DB_SSL_MODE', false);
}

/**
 * Returns the singleton instance of the Database class.
 *
 * @return Database The shared Database instance.
 */
function getDatabase() {
    return Database::getInstance();
}

/**
 * Returns the PDO connection from the singleton Database instance.
 *
 * @return PDO The active PDO database connection.
 */
function getDatabaseConnection() {
    return Database::getInstance()->getConnection();
}

// Initialize database connection if not already done
if (!isset($GLOBALS['db_initialized'])) {
    try {
        // Load the Database class if not already loaded
        if (!class_exists('Database')) {
            require_once __DIR__ . '/../includes/database.php';
        }
        
        // Initialize the database connection
        $db = Database::getInstance();
        $GLOBALS['db'] = $db->getConnection();
        $GLOBALS['db_initialized'] = true;
        
        if (function_exists('debugLog')) {
            debugLog(['status' => 'Database initialized successfully'], 'DATABASE_INIT');
        }
        
    } catch (Exception $e) {
        if (function_exists('debugLog')) {
            debugLog(['error' => $e->getMessage()], 'DATABASE_INIT_ERROR');
        }
        
        // For testing, try SQLite fallback
        try {
            $sqliteDb = new PDO('sqlite::memory:');
            $sqliteDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $GLOBALS['db'] = $sqliteDb;
            $GLOBALS['db_initialized'] = true;
            error_log("Using SQLite fallback for testing");
        } catch (Exception $sqliteError) {
            // Set null connection for graceful degradation
            $GLOBALS['db'] = null;
            $GLOBALS['db_initialized'] = false;
            error_log("Database initialization failed: " . $e->getMessage());
        }
    }
}
?>
