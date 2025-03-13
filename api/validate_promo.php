<?php
session_start();
require_once '../config/db.php';
require_once '../config/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['valid' => false, 'message' => 'Unauthorized']);
    exit();
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['valid' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get promo code from request
$promo_code = isset($_POST['promo_code']) ? sanitize($_POST['promo_code']) : '';

if (empty($promo_code)) {
    header('Content-Type: application/json');
    echo json_encode(['valid' => false, 'message' => 'Promo code is required']);
    exit();
}

// Check if promo code exists and is valid
$query = "SELECT * FROM Promotions 
         WHERE discount_code = ? 
         AND status = 'Hoạt động' 
         AND CURRENT_DATE BETWEEN start_date AND end_date";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $promo_code);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $promo = $result->fetch_assoc();
    header('Content-Type: application/json');
    echo json_encode([
        'valid' => true,
        'promo_id' => $promo['promo_id'],
        'discount_value' => $promo['discount_value'],
        'message' => 'Promo code is valid'
    ]);
} else {
    header('Content-Type: application/json');
    echo json_encode(['valid' => false, 'message' => 'Invalid or expired promo code']);
}
?>