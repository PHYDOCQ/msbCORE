<?php
class Database {
    private $pdo;
    
    /**
     * Initializes an in-memory SQLite database with a users table and sample data.
     *
     * Creates a PDO connection to an in-memory SQLite database, sets up the users table, and inserts predefined user records. If the connection or setup fails, the database connection is set to null.
     */
    public function __construct() {
        try {
            $this->pdo = new PDO("sqlite::memory:");
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Create a simple users table for testing
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL,
                email TEXT NOT NULL,
                role TEXT DEFAULT 'staff'
            )");
            
            // Insert test data
            $this->pdo->exec("INSERT OR IGNORE INTO users (id, username, email, role) VALUES 
                (1, 'admin', 'admin@garage.com', 'admin'),
                (2, 'manager1', 'manager@garage.com', 'manager'),
                (3, 'tech1', 'tech1@garage.com', 'technician'),
                (4, 'staff1', 'staff@garage.com', 'staff')");
                
        } catch (PDOException $e) {
            $this->pdo = null;
        }
    }
    
    /**
     * Retrieves the current PDO database connection.
     *
     * @return PDO|null The PDO instance if the connection is available, or null if the connection failed.
     */
    public function getConnection() {
        return $this->pdo;
    }
    
    /**
     * Executes an SQL query on the in-memory SQLite database.
     *
     * @param string $sql The SQL query to execute.
     * @return PDOStatement|false The result of the query, or false if the database connection is unavailable.
     */
    public function query($sql) {
        if ($this->pdo) {
            return $this->pdo->query($sql);
        }
        return false;
    }
}
?>
