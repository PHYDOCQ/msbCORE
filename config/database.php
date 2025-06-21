<?php
// Simple database configuration for testing
define('DB_HOST', 'localhost');
define('DB_NAME', 'msbcore_bengkel');
define('DB_USER', 'root');
define('DB_PASS', '');

// Create a simple PDO connection with error handling
try {
    $pdo = new PDO("mysql:host=" . DB_HOST, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME);
    $pdo->exec("USE " . DB_NAME);
    
    // Create a simple users table for testing
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL,
        email VARCHAR(100) NOT NULL,
        role ENUM('admin', 'manager', 'technician', 'staff') DEFAULT 'staff',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Insert some test data
    $pdo->exec("INSERT IGNORE INTO users (id, username, email, role) VALUES 
        (1, 'admin', 'admin@garage.com', 'admin'),
        (2, 'manager1', 'manager@garage.com', 'manager'),
        (3, 'tech1', 'tech1@garage.com', 'technician'),
        (4, 'staff1', 'staff@garage.com', 'staff')");
    
} catch (PDOException $e) {
    // For testing purposes, we'll create a mock connection
    $pdo = null;
}

// Global database connection
$GLOBALS['db'] = $pdo;
?>
