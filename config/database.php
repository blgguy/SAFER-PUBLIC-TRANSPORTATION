<?php
// File: config/database.php
// Description: Database connection configuration using PDO.

// Load environment variables (assuming they are loaded by the entry point)
$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '3306';
$db   = getenv('DB_NAME') ?: 'dbname';
$user = getenv('DB_USER') ?: 'user';
$pass = getenv('DB_PASS') ?: 'password';
$charset = 'utf8mb4';

// Data Source Name string
$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";

// PDO Connection Options
$db_options = [
    // Required: Use exceptions for error handling
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    
    // Recommended: Use associative arrays for fetching
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    
    // Recommended: Disable emulation of prepared statements
    PDO::ATTR_EMULATE_PREPARES   => false,
    
    // Connection Pooling/Persistence: Use persistent connection for high-traffic. 
    // WARNING: Use with caution; may cause issues in some setups. 
    // For this app, we'll keep it disabled (default) or explicitly set to false.
    // To enable pooling: PDO::ATTR_PERSISTENT => true,
    
    // Connection Timeout (in seconds)
    PDO::ATTR_TIMEOUT => 5, 
    
    // MySQL-specific: Connection timeout (for connection pooling)
    PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4' 
];

// Export the configuration array
return [
    'dsn' => $dsn,
    'user' => $user,
    'pass' => $pass,
    'options' => $db_options,
    // Add a flag for connection pooling strategy if needed by the DatabaseConnection class
    'pooling_enabled' => false
];