<?php
// Simplified version with maximum error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../../config/db.php';
require_once '../../config/functions.php';

// Always show a result for debugging
header('Content-Type: application/json');

// Get current date
$current_date = date('Y-m-d');

// Check if the table structure matches the expected structure
$describe_query = "DESCRIBE Promotions";
$describe_result = $conn->query($describe_query);
$columns = [];
if ($describe_result) {
    while ($row = $describe_result->fetch_assoc()) {
        $columns[] = $row['Field'];
    }
}

// Check which column names to use based on actual table structure
$promo_code_column = in_array('promo_code', $columns) ? 'promo_code' : 'discount_code';
$discount_value_column = in_array('discount_value', $columns) ? 'discount_value' : 'discount_value';
$description_column = in_array('description', $columns) ? 'description' : 'description';

// Simple query to get all active promo codes
$query = "SELECT * FROM Promotions 
          WHERE status = 'Hoạt động' 
          AND start_date <= '$current_date' 
          AND end_date >= '$current_date'
          ORDER BY end_date ASC";

$result = $conn->query($query);

if (!$result) {
    echo json_encode([
        'error' => 'Database query error: ' . $conn->error,
        'query' => $query,
        'columns' => $columns
    ]);
    exit();
}

$promos = [];
while ($row = $result->fetch_assoc()) {
    $promos[] = [
        'promo_id' => $row['promo_id'],
        'promo_code' => $row[$promo_code_column],
        'description' => isset($row[$description_column]) ? $row[$description_column] : '',
        'discount_value' => $row[$discount_value_column],
        'end_date' => $row['end_date']
    ];
}

echo json_encode([
    'promos' => $promos,
    'count' => count($promos),
    'current_date' => $current_date,
    'query' => $query,
    'column_mapping' => [
        'promo_code_column' => $promo_code_column,
        'discount_value_column' => $discount_value_column,
        'description_column' => $description_column
    ],
    'columns' => $columns
]);
?> 