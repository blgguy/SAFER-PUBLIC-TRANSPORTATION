<?php
/**
 * Database Connection File
 * Simple mysqli connection for Safe Transport system
 * File: config/db.php
 */

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'user');
define('DB_PASS', 'password'); // Empty for local development, change in production
define('DB_NAME', 'dbname');

// Create connection
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Set charset to UTF-8 (important for special characters)
mysqli_set_charset($conn, "utf8mb4");

/**
 * Test database connection
 * Returns true if connection is successful
 */
function test_connection() {
    global $conn;
    if (mysqli_ping($conn)) {
        return true;
    }
    return false;
}

/**
 * Close database connection
 * Call this at the end of scripts
 */
function close_connection() {
    global $conn;
    if ($conn) {
        mysqli_close($conn);
    }
}

// Uncomment below to test connection when including this file
// if (test_connection()) {
//     echo "Database connected successfully!";
// } else {
//     echo "Database connection failed!";
// }
?>