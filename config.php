<?php
// =============================================
// ShopWise AI - Database Configuration
// =============================================

// Database connection settings
define('DB_HOST', 'localhost');
define('DB_USER', 'root');           // Default XAMPP username
define('DB_PASS', '');               // Default XAMPP password (empty)
define('DB_NAME', 'shopwise_db');

// Create database connection
function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}

// Timezone setting
date_default_timezone_set('Asia/Manila');

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

?>