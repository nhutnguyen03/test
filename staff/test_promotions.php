<?php
session_start();
require_once '../config/db.php';
require_once '../config/functions.php';

// Display errors for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check database connection
echo "<h1>Database Connection Test</h1>";
if ($conn->connect_error) {
    echo "<p style='color: red;'>Connection failed: " . $conn->connect_error . "</p>";
    exit();
} else {
    echo "<p style='color: green;'>Database connection successful!</p>";
    echo "<p>Server: " . $conn->host_info . "</p>";
}

// Check if user is logged in
echo "<h1>Login Status</h1>";
if (!isLoggedIn()) {
    echo "<p style='color: red;'>ERROR: Not logged in</p>";
    echo "<p>Note: You need to be logged in to access promotion codes. Please log in first.</p>";
    echo "<p><a href='../login.php'>Go to login page</a></p>";
    exit();
} else {
    echo "<p style='color: green;'>User is logged in as: " . $_SESSION['username'] . " (Role: " . $_SESSION['role'] . ")</p>";
}

echo "<h1>Testing Promotion Codes</h1>";
echo "<p>Current Date: " . date('Y-m-d') . "</p>";

// Check if the Promotions table exists
echo "<h2>Checking Database Structure</h2>";
$tableCheckQuery = "SHOW TABLES";
$tableResult = $conn->query($tableCheckQuery);

echo "<h3>Available Tables:</h3>";
echo "<ul>";
$promotionsTableExists = false;
while ($row = $tableResult->fetch_row()) {
    echo "<li>{$row[0]}</li>";
    if (strtolower($row[0]) === 'promotions') {
        $promotionsTableExists = true;
    }
}
echo "</ul>";

if (!$promotionsTableExists) {
    echo "<p style='color: red;'>ERROR: The Promotions table does not exist!</p>";
    
    // Create the table if it doesn't exist
    echo "<h3>Creating Promotions Table</h3>";
    $createTableSQL = "CREATE TABLE IF NOT EXISTS Promotions (
        promo_id INT AUTO_INCREMENT PRIMARY KEY,
        promo_code VARCHAR(50) NOT NULL UNIQUE,
        description TEXT,
        discount_value DECIMAL(10,2) NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'Hoạt động'
    )";
    
    if ($conn->query($createTableSQL)) {
        echo "<p style='color: green;'>Successfully created Promotions table!</p>";
        
        // Insert a sample promotion
        $insertPromoSQL = "INSERT INTO Promotions (promo_code, description, discount_value, start_date, end_date, status)
                           VALUES ('WELCOME10', 'Discount 10,000 VND for new customers', 10000, '2023-01-01', '2025-12-31', 'Hoạt động')";
        
        if ($conn->query($insertPromoSQL)) {
            echo "<p style='color: green;'>Added sample promotion code: WELCOME10</p>";
        } else {
            echo "<p style='color: red;'>Error creating sample promotion: " . $conn->error . "</p>";
        }
        
        $promotionsTableExists = true;
    } else {
        echo "<p style='color: red;'>Error creating table: " . $conn->error . "</p>";
    }
}

if ($promotionsTableExists) {
    // Check table structure
    echo "<h3>Promotions Table Structure:</h3>";
    $structureQuery = "DESCRIBE Promotions";
    $structureResult = $conn->query($structureQuery);
    
    echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th></tr>";
    while ($row = $structureResult->fetch_assoc()) {
        echo "<tr><td>{$row['Field']}</td><td>{$row['Type']}</td><td>{$row['Null']}</td><td>{$row['Key']}</td></tr>";
    }
    echo "</table>";
    
    // Query active promotions
    echo "<h2>Active Promotions</h2>";
    $current_date = date('Y-m-d');
    
    $promo_query = "SELECT * FROM Promotions 
                   WHERE status = 'Hoạt động' 
                   AND start_date <= '$current_date' 
                   AND end_date >= '$current_date'
                   ORDER BY end_date ASC";
    
    $result = $conn->query($promo_query);
    
    if (!$result) {
        echo "<p style='color: red;'>Error executing query: " . $conn->error . "</p>";
    } else {
        if ($result->num_rows > 0) {
            echo "<table border='1'><tr><th>ID</th><th>Code</th><th>Description</th><th>Discount</th><th>Start</th><th>End</th><th>Status</th></tr>";
            while ($row = $result->fetch_assoc()) {
                echo "<tr>
                    <td>{$row['promo_id']}</td>
                    <td>{$row['promo_code']}</td>
                    <td>{$row['description']}</td>
                    <td>{$row['discount_value']}</td>
                    <td>{$row['start_date']}</td>
                    <td>{$row['end_date']}</td>
                    <td>{$row['status']}</td>
                </tr>";
            }
            echo "</table>";
        } else {
            echo "<p>No active promotions found.</p>";
            
            // Show all promotions regardless of status
            echo "<h3>All Promotions (regardless of status or date):</h3>";
            $all_promos_query = "SELECT * FROM Promotions";
            $all_result = $conn->query($all_promos_query);
            
            if ($all_result->num_rows > 0) {
                echo "<table border='1'><tr><th>ID</th><th>Code</th><th>Description</th><th>Discount</th><th>Start</th><th>End</th><th>Status</th></tr>";
                while ($row = $all_result->fetch_assoc()) {
                    echo "<tr>
                        <td>{$row['promo_id']}</td>
                        <td>{$row['promo_code']}</td>
                        <td>{$row['description']}</td>
                        <td>{$row['discount_value']}</td>
                        <td>{$row['start_date']}</td>
                        <td>{$row['end_date']}</td>
                        <td>{$row['status']}</td>
                    </tr>";
                }
                echo "</table>";
            } else {
                echo "<p>No promotions found in the database at all.</p>";
            }
        }
    }
}

// Test the API endpoint directly
echo "<h2>Testing API Endpoint</h2>";
echo "<p>Location: " . __DIR__ . "/api/get_promos.php</p>";

$apiFile = __DIR__ . "/api/get_promos.php";
if (file_exists($apiFile)) {
    echo "<p style='color: green;'>API file exists</p>";
} else {
    echo "<p style='color: red;'>API file does not exist!</p>";
}

// Check isLoggedIn function
echo "<h2>Checking Login Status</h2>";
echo "isLoggedIn() returns: " . (isLoggedIn() ? "true" : "false");
echo "<br>Current session data:<br>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// Provide a direct link to the API endpoint for testing
echo "<p><a href='api/get_promos.php' target='_blank'>Test get_promos.php directly</a></p>";
?>

<h2>Manual Promotion Code Manager</h2>
<form method="post" action="">
    <h3>Add New Promotion</h3>
    <label>Code: <input type="text" name="promo_code" required></label><br>
    <label>Description: <input type="text" name="description"></label><br>
    <label>Discount Value: <input type="number" name="discount_value" required></label><br>
    <label>Start Date: <input type="date" name="start_date" required value="<?php echo date('Y-m-d'); ?>"></label><br>
    <label>End Date: <input type="date" name="end_date" required value="<?php echo date('Y-m-d', strtotime('+1 year')); ?>"></label><br>
    <label>Status: 
        <select name="status">
            <option value="Hoạt động">Hoạt động</option>
            <option value="Không hoạt động">Không hoạt động</option>
        </select>
    </label><br>
    <button type="submit" name="add_promo">Add Promotion</button>
</form>

<?php
// Handle form submission
if (isset($_POST['add_promo'])) {
    $promo_code = $_POST['promo_code'];
    $description = $_POST['description'];
    $discount_value = $_POST['discount_value'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $status = $_POST['status'];
    
    $insert_query = "INSERT INTO Promotions (promo_code, description, discount_value, start_date, end_date, status) 
                     VALUES (?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($insert_query);
    $stmt->bind_param("ssdsss", $promo_code, $description, $discount_value, $start_date, $end_date, $status);
    
    if ($stmt->execute()) {
        echo "<p style='color: green;'>Successfully added promotion code: {$promo_code}</p>";
        echo "<script>window.location.reload();</script>";
    } else {
        echo "<p style='color: red;'>Error adding promotion: " . $stmt->error . "</p>";
    }
}
?> 