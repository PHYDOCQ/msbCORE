<?php
class Database {
    private $pdo;
    
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
    
    public function getConnection() {
        return $this->pdo;
    }
    
    public function query($sql) {
        if ($this->pdo) {
            return $this->pdo->query($sql);
        }
        return false;
    }
}
?>
