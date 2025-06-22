<?php
/**
 * Database Class - Singleton Pattern Implementation
 * Provides secure database connection and query methods
 */

class Database {
    private static $instance = null;
    private $pdo;
    private $host;
    private $dbname;
    private $username;
    private $password;
    
    private function __construct() {
        // Load database configuration
        $this->host = defined('DB_HOST') ? DB_HOST : 'localhost';
        $this->dbname = defined('DB_NAME') ? DB_NAME : 'msbcore_bengkel';
        $this->username = defined('DB_USER') ? DB_USER : 'root';
        $this->password = defined('DB_PASS') ? DB_PASS : '';
        
        $this->connect();
    }
    
    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Establish database connection
     */
    private function connect() {
        try {
            // Try MySQL connection first
            $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];
            
            $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
            
            // Test connection
            $this->pdo->query("SELECT 1");
            
        } catch (PDOException $e) {
            // Fallback to SQLite for development/testing
            try {
                $sqliteFile = __DIR__ . '/../database/msbcore.sqlite';
                $this->pdo = new PDO("sqlite:$sqliteFile");
                $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                
                $this->createTestTables();
                
            } catch (PDOException $sqliteError) {
                // Last resort: in-memory SQLite
                $this->pdo = new PDO("sqlite::memory:");
                $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                
                $this->createTestTables();
            }
        }
    }
    
    /**
     * Create basic tables for testing
     */
    private function createTestTables() {
        $tables = [
            "CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                email TEXT NOT NULL UNIQUE,
                password_hash TEXT,
                full_name TEXT,
                role TEXT DEFAULT 'staff',
                is_active INTEGER DEFAULT 1,
                failed_login_attempts INTEGER DEFAULT 0,
                last_failed_login DATETIME,
                account_locked_until DATETIME,
                last_login_at DATETIME,
                last_login_ip TEXT,
                login_count INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            
            "CREATE TABLE IF NOT EXISTS remember_tokens (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                token_hash TEXT NOT NULL,
                expires_at DATETIME NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )",
            
            "CREATE TABLE IF NOT EXISTS user_activities (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                action TEXT NOT NULL,
                description TEXT,
                ip_address TEXT,
                user_agent TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )"
        ];
        
        foreach ($tables as $sql) {
            $this->pdo->exec($sql);
        }
        
        // Insert test users with proper password hashes
        $testUsers = [
            ['admin', 'admin@garage.com', password_hash('admin123', PASSWORD_DEFAULT), 'System Administrator', 'admin'],
            ['manager1', 'manager@garage.com', password_hash('manager123', PASSWORD_DEFAULT), 'Workshop Manager', 'manager'],
            ['tech1', 'tech1@garage.com', password_hash('tech123', PASSWORD_DEFAULT), 'Senior Technician', 'technician'],
            ['staff1', 'staff@garage.com', password_hash('staff123', PASSWORD_DEFAULT), 'Staff Member', 'staff']
        ];
        
        $stmt = $this->pdo->prepare("INSERT OR IGNORE INTO users (username, email, password_hash, full_name, role) VALUES (?, ?, ?, ?, ?)");
        foreach ($testUsers as $user) {
            $stmt->execute($user);
        }
    }
    
    /**
     * Get PDO connection
     */
    public function getConnection() {
        return $this->pdo;
    }
    
    /**
     * Execute a query
     */
    public function query($sql, $params = []) {
        try {
            if (empty($params)) {
                return $this->pdo->query($sql);
            } else {
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($params);
                return $stmt;
            }
        } catch (PDOException $e) {
            if (function_exists('debugLog')) {
                debugLog(['error' => $e->getMessage(), 'sql' => $sql, 'params' => $params], 'DATABASE_ERROR');
            }
            throw $e;
        }
    }
    
    /**
     * Select single record
     */
    public function selectOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }
    
    /**
     * Select multiple records
     */
    public function select($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Insert record
     */
    public function insert($table, $data) {
        $columns = implode(',', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);
        
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Update record
     */
    public function update($table, $data, $where, $whereParams = []) {
        $setParts = [];
        foreach (array_keys($data) as $key) {
            $setParts[] = "{$key} = :{$key}";
        }
        $setClause = implode(', ', $setParts);
        
        $sql = "UPDATE {$table} SET {$setClause} WHERE {$where}";
        $stmt = $this->pdo->prepare($sql);
        
        // Merge data and where parameters
        $allParams = array_merge($data, $whereParams);
        return $stmt->execute($allParams);
    }
    
    /**
     * Delete record
     */
    public function delete($table, $where, $whereParams = []) {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($whereParams);
    }
    
    /**
     * Count records
     */
    public function count($table, $where = '1=1', $whereParams = []) {
        $sql = "SELECT COUNT(*) as count FROM {$table} WHERE {$where}";
        $result = $this->selectOne($sql, $whereParams);
        return $result ? (int)$result['count'] : 0;
    }
    
    /**
     * Begin transaction
     */
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }
    
    /**
     * Commit transaction
     */
    public function commit() {
        return $this->pdo->commit();
    }
    
    /**
     * Rollback transaction
     */
    public function rollback() {
        return $this->pdo->rollback();
    }
    
    /**
     * Check if in transaction
     */
    public function inTransaction() {
        return $this->pdo->inTransaction();
    }
    
    /**
     * Prevent cloning
     */
    private function __clone() {}
    
    /**
     * Prevent unserialization
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}
?>
