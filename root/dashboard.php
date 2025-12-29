<?php
require_once '../includes/config.php';
requireLogin();

// Route to appropriate dashboard based on user type
$userType = getUserType();

switch ($userType) {
    case 'admin':
        include '../admin/admin_dashboard.php';
        break;
    case 'worker':
        include '../worker/worker_dashboard.php';
        break;
    case 'customer':
        include '../customer/customer_dashboard.php';
        break;
    default:
        header('Location: logout.php');
        exit();
}
?>
