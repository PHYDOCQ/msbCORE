<?php
require_once __DIR__ . '/config.php';

class Database {
    private static $instance = null;
    private $connection;
    private $transactionCount = 0;
    
    private function __construct() {
        try {
            $dsn = sprintf(
                "mysql:host=%s;dbname=%s;charset=utf8mb4",
                DB_HOST,
                DB_NAME
            );
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
                PDO::ATTR_TIMEOUT => 30
            ];
            
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
            
            debugLog('Database connection established successfully', 'DB');
            
        } catch(PDOException $e) {
            debugLog('Database connection failed: ' . $e->getMessage(), 'ERROR');
            
            if (DEBUG_MODE) {
                die("Database connection failed: " . $e->getMessage());
            } else {
                die("Database connection failed. Please check configuration.");
            }
        }
    }
    
    public static function getInstance() {
        if(self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function query($sql, $params = []) {
        try {
            debugLog(['sql' => $sql, 'params' => $params], 'DB_QUERY');
            
            $stmt = $this->connection->prepare($sql);
            $start = microtime(true);
            $result = $stmt->execute($params);
            $duration = microtime(true) - $start;
            
            debugLog("Query executed in {$duration}s", 'DB_PERF');
            
            return $stmt;
            
        } catch(PDOException $e) {
            debugLog(['error' => $e->getMessage(), 'sql' => $sql, 'params' => $params], 'DB_ERROR');
            throw new Exception("Database error: " . $e->getMessage());
        }
    }
    
    public function fetch($sql, $params = []) {
        return $this->query($sql, $params)->fetch();
    }
    
    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll();
    }
    
    public function exec($sql) {
        try {
            debugLog($sql, 'DB_EXEC');
            return $this->connection->exec($sql);
        } catch(PDOException $e) {
            debugLog(['error' => $e->getMessage(), 'sql' => $sql], 'DB_ERROR');
            throw new Exception("Database error: " . $e->getMessage());
        }
    }
    
    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }
    
    public function beginTransaction() {
        if ($this->transactionCount === 0) {
            debugLog('Transaction started', 'DB_TRANSACTION');
            $this->connection->beginTransaction();
        }
        $this->transactionCount++;
        return true;
    }
    
    public function commit() {
        $this->transactionCount--;
        if ($this->transactionCount === 0) {
            debugLog('Transaction committed', 'DB_TRANSACTION');
            return $this->connection->commit();
        }
        return true;
    }
    
    public function rollback() {
        $this->transactionCount = 0;
        debugLog('Transaction rolled back', 'DB_TRANSACTION');
        return $this->connection->rollback();
    }
    
    public function escape($string) {
        return $this->connection->quote($string);
    }
    
    public function getStats() {
        return [
            'active_transactions' => $this->transactionCount,
            'server_version' => $this->connection->getAttribute(PDO::ATTR_SERVER_VERSION),
            'connection_status' => $this->connection->getAttribute(PDO::ATTR_CONNECTION_STATUS)
        ];
    }
}

// Global database instance
$db = Database::getInstance()->getConnection();
?>
