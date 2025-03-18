<?php
// Maximum error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration - duplicate from config/db.php for isolated testing
$db_host = 'localhost';
$db_user = 'root';
$db_password = '';
$db_name = 'coffee_shop';

echo "<h1>Standalone Database Test</h1>";

// Create connection
$conn = new mysqli($db_host, $db_user, $db_password, $db_name);

// Check connection
if ($conn->connect_error) {
    die("<p style='color: red;'>Connection failed: " . $conn->connect_error . "</p>");
} else {
    echo "<p style='color: green;'>Database connection successful!</p>";
    echo "<p>Server: " . $conn->host_info . "</p>";
    echo "<p>Database: " . $db_name . "</p>";
}

// Set charset to utf8mb4
$conn->set_charset("utf8mb4");

// Check if the Promotions table exists
$tableCheckQuery = "SHOW TABLES LIKE 'Promotions'";
$tableResult = $conn->query($tableCheckQuery);

if ($tableResult === false) {
    echo "<p style='color: red;'>Error executing query: " . $conn->error . "</p>";
} else {
    if ($tableResult->num_rows == 0) {
        echo "<p style='color: orange;'>Promotions table does not exist. Creating it now...</p>";
        
        // Create the table
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
        } else {
            echo "<p style='color: red;'>Error creating table: " . $conn->error . "</p>";
        }
    } else {
        echo "<p style='color: green;'>Promotions table exists!</p>";
        
        // Show all promotions
        $all_promos_query = "SELECT * FROM Promotions";
        $all_result = $conn->query($all_promos_query);
        
        if ($all_result === false) {
            echo "<p style='color: red;'>Error querying promotions: " . $conn->error . "</p>";
        } else {
            if ($all_result->num_rows > 0) {
                echo "<h3>All Promotions:</h3>";
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
                echo "<p>No promotions found in the database.</p>";
                
                // Add a sample promotion
                echo "<p>Adding a sample promotion...</p>";
                $insertPromoSQL = "INSERT INTO Promotions (promo_code, description, discount_value, start_date, end_date, status)
                                  VALUES ('SAMPLE25', 'Sample discount of 25,000 VND', 25000, '2023-01-01', '2025-12-31', 'Hoạt động')";
                
                if ($conn->query($insertPromoSQL)) {
                    echo "<p style='color: green;'>Added sample promotion code: SAMPLE25</p>";
                    echo "<p>Refreshing page in 2 seconds...</p>";
                    echo "<script>setTimeout(function(){ window.location.reload(); }, 2000);</script>";
                } else {
                    echo "<p style='color: red;'>Error creating sample promotion: " . $conn->error . "</p>";
                }
            }
        }
    }
}

// Create a basic form to add promos directly
echo "<h2>Add New Promotion Code</h2>";
echo "<form method='post' action=''>";
echo "<label>Promotion Code: <input type='text' name='promo_code' required></label><br>";
echo "<label>Description: <input type='text' name='description'></label><br>";
echo "<label>Discount Value: <input type='number' name='discount_value' required></label><br>";
echo "<label>Start Date: <input type='date' name='start_date' value='" . date('Y-m-d') . "' required></label><br>";
echo "<label>End Date: <input type='date' name='end_date' value='" . date('Y-m-d', strtotime('+1 year')) . "' required></label><br>";
echo "<button type='submit' name='add_promo'>Add Promotion</button>";
echo "</form>";

// Process form submission
if (isset($_POST['add_promo'])) {
    $promo_code = $_POST['promo_code'];
    $description = $_POST['description'];
    $discount_value = $_POST['discount_value'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    
    $insert_query = "INSERT INTO Promotions (promo_code, description, discount_value, start_date, end_date, status) 
                     VALUES (?, ?, ?, ?, ?, 'Hoạt động')";
    
    $stmt = $conn->prepare($insert_query);
    $stmt->bind_param("ssdss", $promo_code, $description, $discount_value, $start_date, $end_date);
    
    if ($stmt->execute()) {
        echo "<p style='color: green;'>Successfully added promotion code: {$promo_code}</p>";
        echo "<script>window.location.href = window.location.href;</script>";
    } else {
        echo "<p style='color: red;'>Error adding promotion: " . $stmt->error . "</p>";
    }
}

// Test direct JSON output of promotions for API simulation
echo "<h2>API Test (JSON Output)</h2>";
echo "<pre id='json-output'>Loading...</pre>";

echo "<script>
    fetch('api/get_promos_simple.php')
        .then(response => response.text())
        .then(data => {
            document.getElementById('json-output').innerText = data;
        })
        .catch(error => {
            document.getElementById('json-output').innerText = 'Error: ' + error;
        });
</script>";
?> 