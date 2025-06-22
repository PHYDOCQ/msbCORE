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
    
    /**
     * Initializes the Database instance with configuration parameters and establishes a database connection.
     *
     * Loads connection settings from defined constants or defaults, then attempts to connect to the database.
     */
    private function __construct() {
        // Load database configuration
        $this->host = defined('DB_HOST') ? DB_HOST : 'localhost';
        $this->dbname = defined('DB_NAME') ? DB_NAME : 'msbcore_bengkel';
        $this->username = defined('DB_USER') ? DB_USER : 'root';
        $this->password = defined('DB_PASS') ? DB_PASS : '';
        
        $this->connect();
    }
    
    /**
     * Returns the singleton instance of the Database class.
     *
     * Ensures only one instance of the Database exists throughout the application.
     *
     * @return Database The singleton Database instance.
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Establishes a database connection using MySQL if available, with fallback to file-based or in-memory SQLite.
     *
     * Attempts to connect to a MySQL database with UTF-8 settings. If the connection fails, falls back to a file-based SQLite database, and if that also fails, uses an in-memory SQLite database. For SQLite connections, initializes test tables and data.
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
     * Creates required tables and inserts test users for SQLite testing.
     *
     * Sets up the `users`, `remember_tokens`, and `user_activities` tables if they do not exist, and populates the `users` table with predefined test accounts using hashed passwords.
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
     * Returns the current PDO database connection instance.
     *
     * @return \PDO The active PDO connection.
     */
    public function getConnection() {
        return $this->pdo;
    }
    
    /**
     * Executes a SQL query, optionally with parameters, and returns the result.
     *
     * If parameters are provided, the query is prepared and executed with those parameters; otherwise, it is executed directly.
     * Returns a PDOStatement object on success.
     *
     * @param string $sql The SQL query to execute.
     * @param array $params Optional parameters to bind to the query.
     * @return \PDOStatement The resulting PDO statement.
     * @throws \PDOException If the query execution fails.
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
     * Executes a query and returns the first matching record as an associative array.
     *
     * @param string $sql The SQL query to execute.
     * @param array $params Optional parameters to bind to the query.
     * @return array|false The first record as an associative array, or false if no record is found.
     */
    public function selectOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }
    
    /**
     * Executes a SQL query and returns all matching records as an array.
     *
     * @param string $sql The SQL query to execute.
     * @param array $params Optional parameters to bind to the query.
     * @return array An array of all matching records.
     */
    public function select($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Inserts a new record into the specified table.
     *
     * @param string $table The name of the table to insert into.
     * @param array $data An associative array of column names and values to insert.
     * @return string The ID of the newly inserted record.
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
     * Updates records in a table matching the specified condition.
     *
     * @param string $table The name of the table to update.
     * @param array $data An associative array of columns and their new values.
     * @param string $where The WHERE clause to specify which records to update.
     * @param array $whereParams Optional associative array of parameters for the WHERE clause.
     * @return bool True on success, false on failure.
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
     * Deletes records from a table matching the specified condition.
     *
     * @param string $table The name of the table to delete from.
     * @param string $where The WHERE clause to specify which records to delete.
     * @param array $whereParams Optional parameters to bind to the WHERE clause.
     * @return bool True on success, false on failure.
     */
    public function delete($table, $where, $whereParams = []) {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($whereParams);
    }
    
    /**
     * Returns the number of records in a table matching the specified condition.
     *
     * @param string $table The name of the table to count records from.
     * @param string $where Optional SQL WHERE clause to filter records. Defaults to '1=1' (all records).
     * @param array $whereParams Optional parameters for the WHERE clause.
     * @return int The count of matching records.
     */
    public function count($table, $where = '1=1', $whereParams = []) {
        $sql = "SELECT COUNT(*) as count FROM {$table} WHERE {$where}";
        $result = $this->selectOne($sql, $whereParams);
        return $result ? (int)$result['count'] : 0;
    }
    
    /**
     * Starts a new database transaction.
     *
     * @return bool True on success, false if a transaction is already active.
     */
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }
    
    /**
     * Commits the current database transaction.
     *
     * @return bool True on success, false on failure.
     */
    public function commit() {
        return $this->pdo->commit();
    }
    
    /**
     * Rolls back the current database transaction.
     *
     * @return bool True on success, false on failure.
     */
    public function rollback() {
        return $this->pdo->rollback();
    }
    
    /**
     * Determines whether a database transaction is currently active.
     *
     * @return bool True if a transaction is active, false otherwise.
     */
    public function inTransaction() {
        return $this->pdo->inTransaction();
    }
    
    /**
 * Prevents cloning of the singleton instance.
 */
    private function __clone() {}
    
    /**
     * Prevents unserialization of the singleton instance.
     *
     * @throws Exception Always thrown to prevent unserialization.
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}
?>
