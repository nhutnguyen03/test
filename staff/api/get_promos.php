<?php
session_start();
require_once '../../config/db.php';
require_once '../../config/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get active promo codes
$current_date = date('Y-m-d');
$promos = [];

try {
    // Check if the Promotions table exists
    $tableCheckQuery = "SHOW TABLES LIKE 'Promotions'";
    $tableResult = $conn->query($tableCheckQuery);
    
    if ($tableResult->num_rows == 0) {
        throw new Exception("Promotions table does not exist");
    }
    
    // Check the structure of the Promotions table
    $structureQuery = "DESCRIBE Promotions";
    $structureResult = $conn->query($structureQuery);
    
    $requiredColumns = ['promo_id', 'promo_code', 'description', 'discount_value', 'start_date', 'end_date', 'status'];
    $existingColumns = [];
    
    while ($row = $structureResult->fetch_assoc()) {
        $existingColumns[] = $row['Field'];
    }
    
    $missingColumns = array_diff($requiredColumns, $existingColumns);
    if (!empty($missingColumns)) {
        throw new Exception("Promotions table is missing columns: " . implode(', ', $missingColumns));
    }
    
    $promo_query = "SELECT * FROM Promotions 
                   WHERE status = 'Hoạt động' 
                   AND start_date <= ? 
                   AND end_date >= ?
                   ORDER BY end_date ASC";

    $stmt = $conn->prepare($promo_query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("ss", $current_date, $current_date);
    $success = $stmt->execute();
    
    if (!$success) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $promos[] = [
            'promo_id' => $row['promo_id'],
            'promo_code' => $row['promo_code'],
            'description' => $row['description'],
            'discount_value' => $row['discount_value'],
            'end_date' => $row['end_date']
        ];
    }
} catch (Exception $e) {
    // Return error
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
    exit();
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode(['promos' => $promos]);
?> 