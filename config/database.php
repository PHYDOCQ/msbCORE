<?php
require_once __DIR__ . '/config.php';

class Database {
    private static $instance = null;
    private $connection;
    private $transactionCount = 0;
    const TIMEOUT = 30;

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
                PDO::ATTR_TIMEOUT => self::TIMEOUT
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
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }

    public function isConnected(): bool {
        return $this->connection !== null;
    }

    public function query($sql, $params = []) {
        try {
            debugLog(['sql' => $sql, 'params' => $params], 'DB_QUERY');
            $stmt = $this->connection->prepare($sql);
            $start = microtime(true);
            $stmt->execute($params);
            debugLog("Query executed in " . (microtime(true) - $start) . "s", 'DB_PERF');
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

    // ========================================
    // CRUD METHODS
    // ========================================

    /**
     * Select single record
     */
    public function selectOne($sql, $params = []) {
        try {
            debugLog(['sql' => $sql, 'params' => $params], 'DB_SELECT_ONE');
            $stmt = $this->query($sql, $params);
            $result = $stmt->fetch();
            debugLog(['result' => $result ? 'found' : 'not found'], 'DB_SELECT_ONE_RESULT');
            return $result;
        } catch (Exception $e) {
            debugLog(['error' => $e->getMessage(), 'sql' => $sql], 'DB_SELECT_ONE_ERROR');
            throw $e;
        }
    }

    /**
     * Select multiple records
     */
    public function select($sql, $params = []) {
        try {
            debugLog(['sql' => $sql, 'params' => $params], 'DB_SELECT');
            $stmt = $this->query($sql, $params);
            $results = $stmt->fetchAll();
            debugLog(['count' => count($results)], 'DB_SELECT_RESULT');
            return $results;
        } catch (Exception $e) {
            debugLog(['error' => $e->getMessage(), 'sql' => $sql], 'DB_SELECT_ERROR');
            throw $e;
        }
    }

    /**
     * Insert record
     */
    public function insert($table, $data) {
        try {
            debugLog(['table' => $table, 'data' => $data], 'DB_INSERT');
            
            $columns = array_keys($data);
            $placeholders = array_map(function($col) { return ":$col"; }, $columns);
            
            $sql = sprintf(
                "INSERT INTO %s (%s) VALUES (%s)",
                $table,
                implode(', ', $columns),
                implode(', ', $placeholders)
            );
            
            $this->query($sql, $data);
            $insertId = $this->lastInsertId();
            
            debugLog(['insert_id' => $insertId], 'DB_INSERT_RESULT');
            return $insertId;
        } catch (Exception $e) {
            debugLog(['error' => $e->getMessage(), 'table' => $table, 'data' => $data], 'DB_INSERT_ERROR');
            throw $e;
        }
    }

    /**
     * Update records
     */
    public function update($table, $data, $where, $whereParams = []) {
        try {
            debugLog(['table' => $table, 'data' => $data, 'where' => $where, 'whereParams' => $whereParams], 'DB_UPDATE');
            
            $setParts = array_map(function($col) { return "$col = :$col"; }, array_keys($data));
            $sql = sprintf("UPDATE %s SET %s WHERE %s", $table, implode(', ', $setParts), $where);
            
            $params = array_merge($data, $whereParams);
            $stmt = $this->query($sql, $params);
            $affectedRows = $stmt->rowCount();
            
            debugLog(['affected_rows' => $affectedRows], 'DB_UPDATE_RESULT');
            return $affectedRows;
        } catch (Exception $e) {
            debugLog(['error' => $e->getMessage(), 'table' => $table], 'DB_UPDATE_ERROR');
            throw $e;
        }
    }

    /**
     * Delete records
     */
    public function delete($table, $where, $whereParams = []) {
        try {
            debugLog(['table' => $table, 'where' => $where, 'whereParams' => $whereParams], 'DB_DELETE');
            
            $sql = "DELETE FROM $table WHERE $where";
            $stmt = $this->query($sql, $whereParams);
            $affectedRows = $stmt->rowCount();
            
            debugLog(['affected_rows' => $affectedRows], 'DB_DELETE_RESULT');
            return $affectedRows;
        } catch (Exception $e) {
            debugLog(['error' => $e->getMessage(), 'table' => $table], 'DB_DELETE_ERROR');
            throw $e;
        }
    }

    /**
     * Count records
     */
    public function count($table, $where = '1=1', $whereParams = []) {
        try {
            debugLog(['table' => $table, 'where' => $where, 'whereParams' => $whereParams], 'DB_COUNT');
            
            $sql = "SELECT COUNT(*) as count FROM $table WHERE $where";
            $result = $this->selectOne($sql, $whereParams);
            $count = $result ? (int)$result['count'] : 0;
            
            debugLog(['count' => $count], 'DB_COUNT_RESULT');
            return $count;
        } catch (Exception $e) {
            debugLog(['error' => $e->getMessage(), 'table' => $table], 'DB_COUNT_ERROR');
            throw $e;
        }
    }

    /**
     * Check if record exists
     */
    public function exists($table, $where, $whereParams = []) {
        try {
            debugLog(['table' => $table, 'where' => $where, 'whereParams' => $whereParams], 'DB_EXISTS');
            
            $count = $this->count($table, $where, $whereParams);
            $exists = $count > 0;
            
            debugLog(['exists' => $exists], 'DB_EXISTS_RESULT');
            return $exists;
        } catch (Exception $e) {
            debugLog(['error' => $e->getMessage(), 'table' => $table], 'DB_EXISTS_ERROR');
            throw $e;
        }
    }

    /**
     * Get table schema information
     */
    public function getTableSchema($table) {
        try {
            debugLog(['table' => $table], 'DB_SCHEMA');
            
            $sql = "DESCRIBE $table";
            $schema = $this->select($sql);
            
            debugLog(['columns' => count($schema)], 'DB_SCHEMA_RESULT');
            return $schema;
        } catch (Exception $e) {
            debugLog(['error' => $e->getMessage(), 'table' => $table], 'DB_SCHEMA_ERROR');
            throw $e;
        }
    }

    /**
     * Execute multiple queries in transaction
     */
    public function executeTransaction($queries) {
        $this->beginTransaction();
        try {
            debugLog(['query_count' => count($queries)], 'DB_TRANSACTION_START');
            
            $results = [];
            foreach ($queries as $index => $query) {
                debugLog(['query_index' => $index, 'sql' => $query['sql']], 'DB_TRANSACTION_QUERY');
                $results[] = $this->query($query['sql'], $query['params'] ?? []);
            }
            
            $this->commit();
            debugLog('Transaction completed successfully', 'DB_TRANSACTION_SUCCESS');
            return $results;
        } catch (Exception $e) {
            $this->rollback();
            debugLog(['error' => $e->getMessage()], 'DB_TRANSACTION_ERROR');
            throw $e;
        }
    }

    /**
     * Get database performance metrics
     */
    public function getPerformanceMetrics() {
        try {
            $metrics = [
                'connection_stats' => $this->getStats(),
                'slow_queries' => $this->selectOne("SHOW STATUS LIKE 'Slow_queries'"),
                'connections' => $this->selectOne("SHOW STATUS LIKE 'Connections'"),
                'uptime' => $this->selectOne("SHOW STATUS LIKE 'Uptime'"),
                'queries' => $this->selectOne("SHOW STATUS LIKE 'Queries'")
            ];
            
            debugLog($metrics, 'DB_PERFORMANCE_METRICS');
            return $metrics;
        } catch (Exception $e) {
            debugLog(['error' => $e->getMessage()], 'DB_PERFORMANCE_ERROR');
            return [];
        }
    }
}

$db = Database::getInstance()->getConnection();
