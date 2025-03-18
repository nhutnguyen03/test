<?php
// Maximum error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../../config/db.php';
require_once '../../config/functions.php';

// Always show a result for debugging
header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get promo code from request
$promo_code = $_POST['promo_code'] ?? '';

if (empty($promo_code)) {
    echo json_encode(['valid' => false, 'message' => 'Mã khuyến mãi không được để trống']);
    exit();
}

// Check table structure to determine column names
$describe_query = "DESCRIBE Promotions";
$describe_result = $conn->query($describe_query);
$columns = [];

if ($describe_result) {
    while ($row = $describe_result->fetch_assoc()) {
        $columns[] = $row['Field'];
    }
}

// Determine which column name to use for promo_code
$promo_code_column = in_array('promo_code', $columns) ? 'promo_code' : 'discount_code';
$discount_value_column = in_array('discount_value', $columns) ? 'discount_value' : 'discount_value';

// Validate promo code
$current_date = date('Y-m-d');
$promo_query = "SELECT * FROM Promotions 
               WHERE $promo_code_column = ? 
               AND status = 'Hoạt động' 
               AND start_date <= ? 
               AND end_date >= ?
               LIMIT 1";

$stmt = $conn->prepare($promo_query);
if (!$stmt) {
    echo json_encode([
        'valid' => false, 
        'message' => 'Database error: ' . $conn->error,
        'columns' => $columns
    ]);
    exit();
}

$stmt->bind_param("sss", $promo_code, $current_date, $current_date);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $promo = $result->fetch_assoc();
    echo json_encode([
        'valid' => true,
        'promo_id' => $promo['promo_id'],
        'discount_value' => $promo[$discount_value_column],
        'message' => 'Mã khuyến mãi hợp lệ'
    ]);
} else {
    echo json_encode([
        'valid' => false, 
        'message' => 'Mã khuyến mãi không hợp lệ hoặc đã hết hạn',
        'query' => str_replace('?', "'$promo_code'", $promo_query),
    echo json_encode(['valid' => false, 'message' => 'Mã khuyến mãi không hợp lệ hoặc đã hết hạn']);
}
?> 