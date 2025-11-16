<?php
// db.php

// Use environment variables if available, otherwise use defaults
// For production, set these in your server environment or config file
$host = getenv('DB_HOST') ?: 'localhost';
$user = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASS') ?: 'root';
$database = getenv('DB_NAME') ?: 'SalonDB';

// Enable error reporting for development, disable for production
// Set to MYSQLI_REPORT_OFF in production
mysqli_report(MYSQLI_REPORT_OFF);

try {
    // Create connection
    $conn = new mysqli($host, $user, $password, $database);
    
    // Set charset to prevent charset-based attacks
    $conn->set_charset("utf8mb4");
    
    // Set connection timeouts to prevent DoS
    $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
    $conn->options(MYSQLI_OPT_READ_TIMEOUT, 5);
    
} catch (mysqli_sql_exception $e) {
    // Log error but don't expose details to user
    error_log("Database connection failed: " . $e->getMessage());
    
    // Don't expose database errors to users
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        die("Database connection failed: " . $e->getMessage());
    } else {
        die("Database connection failed. Please try again later.");
    }
}
?>
