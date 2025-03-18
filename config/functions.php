<?php
// Common functions for the application

/**
 * Sanitize input data
 * @param string $data
 * @return string
 */
function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Check if user is logged in
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Check if user has admin role
 * @return bool
 */
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'Quản lý';
}

/**
 * Get current shift based on time
 * @param mysqli $conn
 * @return int|null
 */
function getCurrentShift($conn) {
    $current_time = date('H:i:s');
    
    // Kiểm tra xem thời gian hiện tại có nằm trong một ca làm việc nào không
    // Sử dụng BETWEEN trong SQL để xác định ca làm việc
    $query = "SELECT shift_id FROM Shifts WHERE ? BETWEEN start_time AND end_time";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $current_time);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['shift_id'];
    }
    
    // Nếu không tìm thấy ca phù hợp, trả về ca mặc định (1 - Ca sáng)
    // Thời gian hiện tại nằm ngoài giờ hoạt động của quán (7:00 - 22:00)
    return 1; // Default to morning shift
}

/**
 * Format currency
 * @param float $amount
 * @return string
 */
function formatCurrency($amount) {
    return number_format($amount, 0, ',', '.') . ' đ';
}

/**
 * Generate order ID
 * @return string
 */
function generateOrderId() {
    return date('YmdHis') . rand(100, 999);
}
?>