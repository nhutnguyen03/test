<?php
require_once 'config/db.php';
require_once 'config/functions.php';

echo "<h1>Cập nhật hàm getCurrentShift</h1>";

// Hiển thị thời gian hiện tại
$current_time = date('H:i:s');
echo "<p><strong>Thời gian hiện tại:</strong> {$current_time}</p>";

// Hiển thị các ca làm việc
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
    echo "</tr>";
    
    while ($shift = $shifts_result->fetch_assoc()) {
        $in_shift = ($current_time >= $shift['start_time'] && $current_time <= $shift['end_time']);
        $row_style = $in_shift ? "background-color: #d4edda;" : "";
        
        echo "<tr style='{$row_style}'>";
        echo "<td style='padding: 8px;'>" . $shift['shift_id'] . "</td>";
        echo "<td style='padding: 8px;'>" . $shift['shift_name'] . "</td>";
        echo "<td style='padding: 8px;'>" . $shift['start_time'] . "</td>";
        echo "<td style='padding: 8px;'>" . $shift['end_time'] . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "<p>Không tìm thấy ca làm việc nào trong cơ sở dữ liệu.</p>";
}

// Tạo hàm getCurrentShift cải tiến với debug
$improved_function = <<<'EOD'
/**
 * Get current shift based on time
 * @param mysqli $conn
 * @return int|null
 */
function getCurrentShift($conn) {
    $current_time = date('H:i:s');
    
    // Kiểm tra xem thời gian hiện tại có nằm trong một ca làm việc nào không
    $query = "SELECT shift_id FROM Shifts WHERE ? BETWEEN start_time AND end_time";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $current_time);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['shift_id'];
    }
    
    // Nếu không tìm thấy ca phù hợp, trả về null
    // Điều này là bình thường nếu thời gian hiện tại nằm ngoài giờ hoạt động của quán
    return null;
}
EOD;

// Test hàm mới
$result = getCurrentShift($conn);
echo "<h2>Kiểm tra hàm getCurrentShift hiện tại:</h2>";
if ($result) {
    echo "<p style='color: green; font-weight: bold;'>✓ Đã xác định được ca làm việc hiện tại: ID = {$result}</p>";
} else {
    echo "<p style='color: red; font-weight: bold;'>✗ Không xác định được ca làm việc hiện tại.</p>";
    
    // Test trực tiếp
    echo "<h3>Kiểm tra trực tiếp:</h3>";
    
    $check_query = "SELECT * FROM Shifts WHERE '{$current_time}' BETWEEN start_time AND end_time";
    echo "<p>SQL Query: <code>{$check_query}</code></p>";
    
    $check_result = $conn->query($check_query);
    if ($check_result && $check_result->num_rows > 0) {
        $check_row = $check_result->fetch_assoc();
        echo "<p style='color: green;'>✓ Tìm thấy ca làm việc: {$check_row['shift_name']} (ID = {$check_row['shift_id']})</p>";
    } else {
        echo "<p style='color: red;'>✗ Truy vấn SQL không trả về kết quả.</p>";
    }
    
    // Phân tích giờ hiện tại so với các ca
    echo "<h3>Phân tích giờ hiện tại so với các ca:</h3>";
    $shift_matches = false;
    
    $shifts_query = "SELECT * FROM Shifts";
    $shifts_result = $conn->query($shifts_query);
    
    while ($shift = $shifts_result->fetch_assoc()) {
        $start_time = $shift['start_time'];
        $end_time = $shift['end_time'];
        
        echo "<p>So sánh với ca <strong>{$shift['shift_name']}</strong> ({$start_time} - {$end_time}):</p>";
        
        $is_between = ($current_time >= $start_time && $current_time <= $end_time);
        
        if ($is_between) {
            echo "<p style='color: green; margin-left: 20px;'>✓ {$current_time} >= {$start_time} && {$current_time} <= {$end_time} = TRUE</p>";
            $shift_matches = true;
        } else {
            echo "<p style='color: red; margin-left: 20px;'>✗ {$current_time} >= {$start_time} && {$current_time} <= {$end_time} = FALSE</p>";
            
            if ($current_time < $start_time) {
                echo "<p style='margin-left: 20px;'>Thời gian hiện tại ({$current_time}) NHỎ HƠN giờ bắt đầu ca ({$start_time})</p>";
            }
            if ($current_time > $end_time) {
                echo "<p style='margin-left: 20px;'>Thời gian hiện tại ({$current_time}) LỚN HƠN giờ kết thúc ca ({$end_time})</p>";
            }
        }
    }
    
    if (!$shift_matches) {
        echo "<p style='font-weight: bold;'>Kết luận: Thời gian hiện tại không nằm trong bất kỳ ca làm việc nào.</p>";
    }
}

// Thông tin sửa lỗi
echo "<h2>Hướng dẫn sửa lỗi:</h2>";
echo "<div style='padding: 15px; border-radius: 5px; background-color: #f9f9f9; margin-bottom: 20px;'>";
echo "<ol>";
echo "<li><strong>Cập nhật múi giờ (timezone):</strong> Có thể có sự chênh lệch giữa múi giờ PHP và MySQL.</li>";
echo "<p><a href='fix_timezone.php' style='display: inline-block; padding: 8px 15px; background-color: #2196F3; color: white; text-decoration: none; border-radius: 4px; margin: 10px 0;'>Cập nhật Timezone</a></p>";

echo "<li><strong>Kiểm tra và cập nhật dữ liệu ca làm việc:</strong> Đảm bảo dữ liệu ca làm việc được cài đặt đúng.</li>";
echo "<p><a href='add_shifts.php' style='display: inline-block; padding: 8px 15px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 4px; margin: 10px 0;'>Cập nhật Ca Làm Việc</a></p>";

echo "<li><strong>Kiểm tra giờ hệ thống:</strong> Đảm bảo đồng hồ máy chủ được đặt đúng.</li>";
echo "<p><a href='debug_time.php' style='display: inline-block; padding: 8px 15px; background-color: #ff9800; color: white; text-decoration: none; border-radius: 4px; margin: 10px 0;'>Debug Thời Gian</a></p>";
echo "</ol>";
echo "</div>";

// Hiển thị giờ làm việc của quán
echo "<h2>Giờ hoạt động của quán:</h2>";
echo "<p>Dựa vào cài đặt ca làm việc, quán hoạt động từ <strong>07:00</strong> đến <strong>22:00</strong> mỗi ngày.</p>";
echo "<p>Nếu truy cập hệ thống ngoài giờ này, thông báo \"Thời gian hiện tại nằm ngoài giờ hoạt động\" là đúng.</p>";

// Liên kết điều hướng
echo "<div style='margin-top: 20px;'>";
echo "<a href='staff/pos.php' style='display: inline-block; padding: 10px 20px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 4px;'>Quay lại POS</a>";
echo "</div>";
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
    h1, h2, h3 {
        color: #333;
    }
    table {
        margin-bottom: 20px;
    }
    code {
        background-color: #f5f5f5;
        padding: 2px 5px;
        border-radius: 3px;
        font-family: monospace;
    }
</style> 