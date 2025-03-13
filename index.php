<?php
session_start();
// Redirect to login page if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Redirect based on user role
if (isset($_SESSION['role'])) {
    switch ($_SESSION['role']) {
        case 'Quản lý':
            header("Location: admin/dashboard.php");
            break;
        case 'Ca sáng':
        case 'Ca chiều':
            header("Location: staff/pos.php");
            break;
        default:
            // Invalid role, logout
            session_destroy();
            header("Location: login.php?error=invalid_role");
            break;
    }
    exit();
}
?>