<?php
require_once 'config/db.php';
require_once 'config/functions.php';

echo "<h1>Debug Thời Gian Hệ Thống</h1>";

// Hiển thị thời gian hệ thống chi tiết
echo "<h2>Thời gian hệ thống:</h2>";
echo "<div style='margin-bottom: 15px;'>";
echo "<p><strong>Thời gian hiện tại (H:i:s):</strong> " . date('H:i:s') . "</p>";
echo "<p><strong>Thời gian hiện tại (h:i:s A):</strong> " . date('h:i:s A') . "</p>";
echo "<p><strong>Ngày hiện tại:</strong> " . date('Y-m-d') . "</p>";
echo "<p><strong>Timezone:</strong> " . date_default_timezone_get() . "</p>";
echo "</div>";

// Kiểm tra cài đặt timezone trong PHP
echo "<h2>Cài đặt Timezone:</h2>";
echo "<div style='margin-bottom: 15px;'>";
echo "<p>Timezone hiện tại: <strong>" . date_default_timezone_get() . "</strong></p>";
echo "<p>Thời gian PHP: <strong>" . date('Y-m-d H:i:s') . "</strong></p>";
echo "<p>Thời gian MySQL: ";
$mysql_time_query = "SELECT NOW() as mysql_time";
$mysql_time_result = $conn->query($mysql_time_query);
if ($mysql_time_result && $mysql_time_row = $mysql_time_result->fetch_assoc()) {
    echo "<strong>" . $mysql_time_row['mysql_time'] . "</strong>";
}
echo "</p>";
echo "</div>";

// Kiểm tra các ca làm việc
echo "<h2>Danh sách ca làm việc:</h2>";
$shifts_query = "SELECT * FROM Shifts ORDER BY shift_id";
$shifts_result = $conn->query($shifts_query);

if ($shifts_result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f2f2f2;'>";
    echo "<th style='padding: 8px; text-align: left;'>ID</th>";
    echo "<th style='padding: 8px; text-align: left;'>Tên ca</th>";
    echo "<th style='padding: 8px; text-align: left;'>Giờ bắt đầu</th>";
    echo "<th style='padding: 8px; text-align: left;'>Giờ kết thúc</th>";
    echo "<th style='padding: 8px; text-align: left;'>So sánh với giờ hiện tại</th>";
    echo "</tr>";
    
    $current_time = date('H:i:s');
    
    while ($shift = $shifts_result->fetch_assoc()) {
        $start_time = $shift['start_time'];
        $end_time = $shift['end_time'];
        
        // Chuẩn hóa định dạng thời gian để so sánh
        $current_time_obj = new DateTime($current_time);
        $start_time_obj = new DateTime($start_time);
        $end_time_obj = new DateTime($end_time);
        
        // Định dạng lại để hiển thị
        $formatted_current = $current_time_obj->format('H:i:s');
        $formatted_start = $start_time_obj->format('H:i:s');
        $formatted_end = $end_time_obj->format('H:i:s');
        
        echo "<tr>";
        echo "<td style='padding: 8px;'>" . $shift['shift_id'] . "</td>";
        echo "<td style='padding: 8px;'>" . $shift['shift_name'] . "</td>";
        echo "<td style='padding: 8px;'>" . $start_time . "</td>";
        echo "<td style='padding: 8px;'>" . $end_time . "</td>";
        
        // Kiểm tra thời gian hiện tại có nằm trong ca làm việc không
        $in_shift = ($formatted_current >= $formatted_start && $formatted_current <= $formatted_end);
        
        if ($in_shift) {
            echo "<td style='padding: 8px; background-color: #d4edda; color: #155724;'>
                ✓ Giờ hiện tại ({$formatted_current}) nằm trong ca này<br>
                <code>{$formatted_current} >= {$formatted_start} && {$formatted_current} <= {$formatted_end}</code> = TRUE
            </td>";
        } else {
            echo "<td style='padding: 8px; background-color: #f8d7da; color: #721c24;'>
                ✗ Giờ hiện tại ({$formatted_current}) không nằm trong ca này<br>
                <code>{$formatted_current} >= {$formatted_start} && {$formatted_current} <= {$formatted_end}</code> = FALSE
            </td>";
        }
        
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "<p>Không tìm thấy ca làm việc nào trong cơ sở dữ liệu.</p>";
}

// Kiểm tra SQL BETWEEN
echo "<h2>Kiểm tra SQL BETWEEN:</h2>";
$current_time = date('H:i:s');
$shifts_query = "SELECT *, ('{$current_time}' BETWEEN start_time AND end_time) as in_shift FROM Shifts ORDER BY shift_id";
$shifts_result = $conn->query($shifts_query);

if ($shifts_result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f2f2f2;'>";
    echo "<th style='padding: 8px; text-align: left;'>ID</th>";
    echo "<th style='padding: 8px; text-align: left;'>Tên ca</th>";
    echo "<th style='padding: 8px; text-align: left;'>Giờ bắt đầu</th>";
    echo "<th style='padding: 8px; text-align: left;'>Giờ kết thúc</th>";
    echo "<th style='padding: 8px; text-align: left;'>SQL BETWEEN Result</th>";
    echo "</tr>";
    
    while ($shift = $shifts_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td style='padding: 8px;'>" . $shift['shift_id'] . "</td>";
        echo "<td style='padding: 8px;'>" . $shift['shift_name'] . "</td>";
        echo "<td style='padding: 8px;'>" . $shift['start_time'] . "</td>";
        echo "<td style='padding: 8px;'>" . $shift['end_time'] . "</td>";
        
        if ($shift['in_shift']) {
            echo "<td style='padding: 8px; background-color: #d4edda; color: #155724;'>
                ✓ Thuộc ca làm việc (SQL trả về TRUE)<br>
                <code>'{$current_time}' BETWEEN '{$shift['start_time']}' AND '{$shift['end_time']}'</code>
            </td>";
        } else {
            echo "<td style='padding: 8px; background-color: #f8d7da; color: #721c24;'>
                ✗ Không thuộc ca làm việc (SQL trả về FALSE)<br>
                <code>'{$current_time}' BETWEEN '{$shift['start_time']}' AND '{$shift['end_time']}'</code>
            </td>";
        }
        
        echo "</tr>";
    }
    
    echo "</table>";
}

// Debug hàm getCurrentShift
echo "<h2>Kiểm tra hàm getCurrentShift:</h2>";
$current_shift_id = getCurrentShift($conn);

if ($current_shift_id) {
    echo "<p style='color: green; font-weight: bold;'>✓ Đã xác định được ca làm việc hiện tại: ID = {$current_shift_id}</p>";
    
    // Lấy thông tin ca làm việc
    $shift_query = "SELECT * FROM Shifts WHERE shift_id = ?";
    $shift_stmt = $conn->prepare($shift_query);
    $shift_stmt->bind_param("i", $current_shift_id);
    $shift_stmt->execute();
    $shift_result = $shift_stmt->get_result();
    
    if ($shift_result && $shift = $shift_result->fetch_assoc()) {
        echo "<p>Ca làm việc hiện tại: <strong>{$shift['shift_name']}</strong> ({$shift['start_time']} - {$shift['end_time']})</p>";
    }
} else {
    echo "<p style='color: red; font-weight: bold;'>✗ Không xác định được ca làm việc hiện tại.</p>";
    
    // Debug câu truy vấn
    $debug_query = "SELECT * FROM Shifts WHERE '" . date('H:i:s') . "' BETWEEN start_time AND end_time";
    echo "<p>Câu truy vấn: <code>$debug_query</code></p>";
    
    $debug_result = $conn->query($debug_query);
    if ($debug_result->num_rows === 0) {
        echo "<p>Không tìm thấy ca làm việc phù hợp với thời gian hiện tại.</p>";
    }
}

// Đề xuất giải pháp
echo "<h2>Đề xuất giải pháp:</h2>";
?>

<div style="margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; background-color: #f9f9f9;">
    <h3>Giải pháp 1: Cập nhật timezone</h3>
    <p>Đảm bảo PHP và MySQL sử dụng cùng timezone:</p>
    <pre><?php echo htmlspecialchars('
// Thêm vào đầu file config/db.php hoặc index.php
date_default_timezone_set("Asia/Ho_Chi_Minh");

// Đặt timezone cho MySQL
$conn->query("SET time_zone = '+07:00'");
    '); ?></pre>
    
    <h3>Giải pháp 2: Kiểm tra dữ liệu ca làm việc</h3>
    <p>Đảm bảo dữ liệu ca làm việc được cài đặt đúng:</p>
    <ul>
        <li>Ca sáng: 07:00:00 - 14:00:00</li>
        <li>Ca chiều: 14:00:00 - 22:00:00</li>
    </ul>
    <p>Hãy chạy script cập nhật ca làm việc:</p>
    <a href="add_shifts.php" style="display: inline-block; padding: 10px 15px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 4px;">Cập nhật Ca Làm Việc</a>
    
    <h3>Giải pháp 3: Điều chỉnh hàm getCurrentShift</h3>
    <p>Sửa hàm getCurrentShift với debug rõ ràng hơn:</p>
    <a href="update_shift_function.php" style="display: inline-block; padding: 10px 15px; background-color: #2196F3; color: white; text-decoration: none; border-radius: 4px;">Cập nhật Hàm getCurrentShift</a>
</div>

<div style="margin-top: 20px;">
    <a href="staff/pos.php" style="display: inline-block; padding: 10px 20px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 4px;">Quay lại POS</a>
</div>

<style>
    body {
        font-family: Arial, sans-serif;
        line-height: 1.6;
        margin: 20px;
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
    }
    h1, h2, h3 {
        color: #333;
    }
    table {
        margin-bottom: 20px;
        width: 100%;
    }
    code {
        background-color: #f5f5f5;
        padding: 2px 5px;
        border-radius: 3px;
        font-family: monospace;
    }
    pre {
        background-color: #f5f5f5;
        padding: 10px;
        border-radius: 5px;
        overflow-x: auto;
    }
</style> 