<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'task_manager');

// Application configuration
define('SITE_NAME', 'TaskManager Pro');
define('BASE_URL', 'http://localhost/domestic-utility-worker-management-system/');

// Create connection
function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $conn->set_charset("utf8mb4");
    return $conn;
}

// Close connection
function closeDBConnection($conn) {
    if ($conn) {
        $conn->close();
    }
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_type']);
}

// Check user type
function getUserType() {
    return $_SESSION['user_type'] ?? null;
}

// Get user ID
function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

// Get user name
function getUserName() {
    return $_SESSION['user_name'] ?? 'Guest';
}

// Redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
         header('Location: ' . BASE_URL . 'root/login_new.php');
        exit();
    }
}

// Redirect if not specific user type
function requireUserType($type) {
    requireLogin();
    if (getUserType() !== $type) {
        header('Location: ' . BASE_URL . 'root/dashboard.php');
        exit();
    }
}

// Sanitize input
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Format currency
function formatCurrency($amount) {
    return '$' . number_format($amount, 2);
}

// Format date
function formatDate($date) {
    return date('M d, Y', strtotime($date));
}

// Format datetime
function formatDateTime($datetime) {
    return date('M d, Y h:i A', strtotime($datetime));
}

// Success message
function setSuccess($message) {
    $_SESSION['success'] = $message;
}

// Error message
function setError($message) {
    $_SESSION['error'] = $message;
}

// Get and clear success message
function getSuccess() {
    if (isset($_SESSION['success'])) {
        $msg = $_SESSION['success'];
        unset($_SESSION['success']);
        return $msg;
    }
    return null;
}

// Get and clear error message
function getError() {
    if (isset($_SESSION['error'])) {
        $msg = $_SESSION['error'];
        unset($_SESSION['error']);
        return $msg;
    }
    return null;
}
?>
