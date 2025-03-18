<?php
require_once 'config/db.php';

echo "Checking Orders table structure:\n";
$result = $conn->query('DESCRIBE Orders');
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . ' - ' . $row['Type'] . "\n";
    }
} else {
    echo "Error: " . $conn->error;
}
?> 