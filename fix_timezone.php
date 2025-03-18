<?php
// Kiểm tra timezone hiện tại
echo "<h1>Cập nhật Timezone</h1>";

echo "<h2>Timezone trước khi cập nhật:</h2>";
echo "<p><strong>PHP Timezone:</strong> " . date_default_timezone_get() . "</p>";
echo "<p><strong>Thời gian PHP hiện tại:</strong> " . date('Y-m-d H:i:s') . "</p>";

// Lấy thời gian MySQL
require_once 'config/db.php';
$mysql_time_query = "SELECT NOW() as mysql_time";
$mysql_time_result = $conn->query($mysql_time_query);
$mysql_time = '';
if ($mysql_time_result && $mysql_time_row = $mysql_time_result->fetch_assoc()) {
    $mysql_time = $mysql_time_row['mysql_time'];
}
echo "<p><strong>Thời gian MySQL hiện tại:</strong> " . $mysql_time . "</p>";

// Cập nhật timezone trong config/db.php
$db_file = 'config/db.php';
$db_content = file_get_contents($db_file);

// Kiểm tra nếu đã có cài đặt timezone
if (strpos($db_content, 'date_default_timezone_set') === false) {
    // Thêm cài đặt timezone vào đầu file sau <?php
    $new_content = preg_replace(
        '/^<\?php/m',
        "<?php\n// Đặt múi giờ cho Việt Nam\ndate_default_timezone_set('Asia/Ho_Chi_Minh');",
        $db_content
    );
    
    // Sao lưu file gốc
    $backup_file = 'config/db.php.bak';
    if (!file_exists($backup_file)) {
        file_put_contents($backup_file, $db_content);
        echo "<p>✓ Đã sao lưu file gốc tại: {$backup_file}</p>";
    }
    
    // Ghi nội dung mới
    if (file_put_contents($db_file, $new_content)) {
        echo "<p style='color: green;'>✓ Đã thêm cài đặt timezone vào file {$db_file}</p>";
    } else {
        echo "<p style='color: red;'>✗ Không thể ghi vào file {$db_file}. Vui lòng kiểm tra quyền truy cập.</p>";
    }
} else {
    echo "<p>File {$db_file} đã có cài đặt timezone.</p>";
}

// Thêm cài đặt timezone cho MySQL
if (strpos($db_content, 'SET time_zone') === false) {
    // Tìm vị trí của $conn để thêm cài đặt timezone sau đó
    $conn_pos = strpos($db_content, '$conn =');
    if ($conn_pos !== false) {
        // Tìm vị trí kết thúc câu lệnh $conn
        $semi_pos = strpos($db_content, ';', $conn_pos);
        if ($semi_pos !== false) {
            $new_content = substr($db_content, 0, $semi_pos + 1) . 
                           "\n// Đặt timezone cho MySQL\n" .
                           '$conn->query("SET time_zone = \'+07:00\'");' . 
                           substr($db_content, $semi_pos + 1);
            
            // Ghi nội dung mới
            if (file_put_contents($db_file, $new_content)) {
                echo "<p style='color: green;'>✓ Đã thêm cài đặt timezone cho MySQL vào file {$db_file}</p>";
            } else {
                echo "<p style='color: red;'>✗ Không thể ghi vào file {$db_file}. Vui lòng kiểm tra quyền truy cập.</p>";
            }
        }
    }
} else {
    echo "<p>File {$db_file} đã có cài đặt timezone cho MySQL.</p>";
}

// Cập nhật timezone trong memory cho phiên hiện tại
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Cập nhật timezone cho MySQL trong phiên hiện tại
$conn->query("SET time_zone = '+07:00'");

echo "<h2>Timezone sau khi cập nhật:</h2>";
echo "<p><strong>PHP Timezone:</strong> " . date_default_timezone_get() . "</p>";
echo "<p><strong>Thời gian PHP hiện tại:</strong> " . date('Y-m-d H:i:s') . "</p>";

// Lấy lại thời gian MySQL
$mysql_time_query = "SELECT NOW() as mysql_time";
$mysql_time_result = $conn->query($mysql_time_query);
$mysql_time = '';
if ($mysql_time_result && $mysql_time_row = $mysql_time_result->fetch_assoc()) {
    $mysql_time = $mysql_time_row['mysql_time'];
}
echo "<p><strong>Thời gian MySQL hiện tại:</strong> " . $mysql_time . "</p>";

// Kiểm tra ca làm việc sau khi cập nhật timezone
require_once 'config/functions.php';
$current_shift = getCurrentShift($conn);

echo "<h2>Kiểm tra ca làm việc:</h2>";
if ($current_shift) {
    echo "<p style='color: green; font-weight: bold;'>✓ Đã xác định được ca làm việc hiện tại: ID = {$current_shift}</p>";
    
    // Lấy thông tin ca làm việc
    $shift_query = "SELECT * FROM Shifts WHERE shift_id = ?";
    $shift_stmt = $conn->prepare($shift_query);
    $shift_stmt->bind_param("i", $current_shift);
    $shift_stmt->execute();
    $shift_result = $shift_stmt->get_result();
    
    if ($shift_result && $shift = $shift_result->fetch_assoc()) {
        echo "<p>Ca làm việc hiện tại: <strong>{$shift['shift_name']}</strong> ({$shift['start_time']} - {$shift['end_time']})</p>";
    }
} else {
    $current_time = date('H:i:s');
    echo "<p style='color: red; font-weight: bold;'>✗ Không xác định được ca làm việc hiện tại.</p>";
    echo "<p>Thời gian hiện tại: {$current_time}</p>";
    
    $shifts_query = "SELECT * FROM Shifts";
    $shifts_result = $conn->query($shifts_query);
    
    echo "<p>Danh sách ca làm việc:</p>";
    echo "<ul>";
    while ($shift = $shifts_result->fetch_assoc()) {
        echo "<li>{$shift['shift_name']}: {$shift['start_time']} - {$shift['end_time']}</li>";
    }
    echo "</ul>";
}

echo "<h2>Các bước tiếp theo:</h2>";
echo "<p>1. Hãy khởi động lại máy chủ web của bạn (XAMPP/Apache) để đảm bảo thay đổi được áp dụng đầy đủ.</p>";
echo "<p>2. Cập nhật lại ca làm việc nếu cần:</p>";
echo "<a href='add_shifts.php' style='display: inline-block; padding: 10px 15px; margin-right: 10px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 4px;'>Cập nhật Ca Làm Việc</a>";
echo "<p>3. Quay lại trang POS để kiểm tra:</p>";
echo "<a href='staff/pos.php' style='display: inline-block; padding: 10px 15px; background-color: #2196F3; color: white; text-decoration: none; border-radius: 4px;'>Quay lại POS</a>";
?>

<style>
    body {
        font-family: Arial, sans-serif;
        line-height: 1.6;
        margin: 20px;
        max-width: 1000px;
        margin: 0 auto;
        padding: 20px;
    }
    h1, h2 {
        color: #333;
    }
</style> 