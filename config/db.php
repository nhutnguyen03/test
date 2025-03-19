<?php
// Đặt múi giờ cho Việt Nam
date_default_timezone_set('Asia/Ho_Chi_Minh');
// Database configuration
$db_host = 'localhost';
$db_user = 'root';
$db_password = '';
$db_name = 'coffee_shop';

// Create connection
$conn = new mysqli($db_host, $db_user, $db_password, $db_name);
// Đặt timezone cho MySQL
$conn->query("SET time_zone = '+07:00'");

// Check connection
if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}

// Set charset to utf8mb4
$conn->set_charset("utf8mb4");
?>