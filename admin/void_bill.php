<?php
session_start();
require_once '../config/db.php';
require_once '../config/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

// Check if user is admin
if ($_SESSION['role'] !== 'Quản lý') {
    header("Location: ../index.php");
    exit();
}

// Check if order_id is provided
if (!isset($_GET['order_id'])) {
    $_SESSION['error'] = "Không tìm thấy đơn hàng";
    header("Location: orders.php");
    exit();
}

$order_id = $_GET['order_id'];

// First check if order exists
$check_order = $conn->prepare("SELECT order_id FROM Orders WHERE order_id = ?");
$check_order->bind_param("i", $order_id);
$check_order->execute();
$result = $check_order->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "Đơn hàng #$order_id không tồn tại";
    header("Location: orders.php");
    exit();
}

// Start transaction
$conn->begin_transaction();

try {
    // 1. Delete from Order_Details first (child of Orders)
    $delete_order_details = $conn->prepare("DELETE FROM Order_Details WHERE order_id = ?");
    $delete_order_details->bind_param("i", $order_id);
    $delete_order_details->execute();
    
    // Log the action
    error_log("Deleted Order_Details for order #$order_id");

    // 2. Delete from Payments (child of Orders)
    $delete_payments = $conn->prepare("DELETE FROM Payments WHERE order_id = ?");
    $delete_payments->bind_param("i", $order_id);
    $delete_payments->execute();
    
    // Log the action
    error_log("Deleted Payments for order #$order_id");

    // 3. Finally delete the order from Orders table
    $delete_order = $conn->prepare("DELETE FROM Orders WHERE order_id = ?");
    $delete_order->bind_param("i", $order_id);
    $delete_order->execute();
    
    // Log the action
    error_log("Deleted Order #$order_id");

    // Commit transaction
    $conn->commit();
    $_SESSION['success'] = "Đã xóa đơn hàng #$order_id thành công";

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    $_SESSION['error'] = "Có lỗi xảy ra khi xóa đơn hàng: " . $e->getMessage();
    error_log("Error voiding bill #$order_id: " . $e->getMessage());
}

// Redirect back to orders page
header("Location: orders.php");
exit(); 