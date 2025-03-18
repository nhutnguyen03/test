<?php
require_once 'config/db.php';

echo "<h1>Thêm/Cập nhật Ca Làm Việc</h1>";

// Khởi tạo mảng các ca làm việc theo thiết kế
$shifts = array(
    array(
        'shift_id' => 1,
        'shift_name' => 'Ca sáng',
        'start_time' => '07:00:00',
        'end_time' => '14:00:00'
    ),
    array(
        'shift_id' => 2,
        'shift_name' => 'Ca chiều',
        'start_time' => '14:00:00',
        'end_time' => '22:00:00'
    )
);

// Kiểm tra và cập nhật/thêm ca làm việc
if (isset($_POST['update_shifts'])) {
    // Bắt đầu transaction
    $conn->begin_transaction();
    
    try {
        foreach ($shifts as $shift) {
            // Kiểm tra xem ca làm việc đã tồn tại chưa
            $check_query = "SELECT * FROM Shifts WHERE shift_id = ?";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bind_param("i", $shift['shift_id']);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows > 0) {
                // Cập nhật ca làm việc đã tồn tại
                $update_query = "UPDATE Shifts SET shift_name = ?, start_time = ?, end_time = ? WHERE shift_id = ?";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bind_param("sssi", $shift['shift_name'], $shift['start_time'], $shift['end_time'], $shift['shift_id']);
                $update_stmt->execute();
                
                echo "<p>Đã cập nhật ca làm việc: {$shift['shift_name']} ({$shift['start_time']} - {$shift['end_time']})</p>";
            } else {
                // Thêm ca làm việc mới
                $insert_query = "INSERT INTO Shifts (shift_id, shift_name, start_time, end_time) VALUES (?, ?, ?, ?)";
                $insert_stmt = $conn->prepare($insert_query);
                $insert_stmt->bind_param("isss", $shift['shift_id'], $shift['shift_name'], $shift['start_time'], $shift['end_time']);
                $insert_stmt->execute();
                
                echo "<p>Đã thêm ca làm việc mới: {$shift['shift_name']} ({$shift['start_time']} - {$shift['end_time']})</p>";
            }
        }
        
        // Xóa ca làm việc không cần thiết
        $delete_other_shifts = "DELETE FROM Shifts WHERE shift_id > 2";
        $conn->query($delete_other_shifts);
        
        // Commit transaction
        $conn->commit();
        
        echo "<div class='success-message'>✅ Đã cập nhật tất cả ca làm việc thành công!</div>";
    } catch (Exception $e) {
        // Rollback nếu có lỗi
        $conn->rollback();
        echo "<div class='error-message'>❌ Lỗi khi cập nhật ca làm việc: " . $e->getMessage() . "</div>";
    }
}

// Hiển thị danh sách ca làm việc hiện tại
echo "<h2>Danh sách ca làm việc hiện tại:</h2>";
$current_shifts_query = "SELECT * FROM Shifts ORDER BY shift_id";
$current_shifts_result = $conn->query($current_shifts_query);

if ($current_shifts_result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%; margin-bottom: 20px;'>";
    echo "<tr style='background-color: #f2f2f2;'>";
    echo "<th style='padding: 8px; text-align: left;'>ID</th>";
    echo "<th style='padding: 8px; text-align: left;'>Tên ca</th>";
    echo "<th style='padding: 8px; text-align: left;'>Giờ bắt đầu</th>";
    echo "<th style='padding: 8px; text-align: left;'>Giờ kết thúc</th>";
    echo "</tr>";
    
    while ($shift = $current_shifts_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td style='padding: 8px;'>" . $shift['shift_id'] . "</td>";
        echo "<td style='padding: 8px;'>" . $shift['shift_name'] . "</td>";
        echo "<td style='padding: 8px;'>" . $shift['start_time'] . "</td>";
        echo "<td style='padding: 8px;'>" . $shift['end_time'] . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "<p>Chưa có ca làm việc nào trong cơ sở dữ liệu.</p>";
}

// Hiển thị form cập nhật ca làm việc
echo "<h2>Cập nhật ca làm việc:</h2>";
echo "<p>Nhấn nút bên dưới để thêm/cập nhật ca làm việc theo thiết kế của hệ thống:</p>";
echo "<ul>";
foreach ($shifts as $shift) {
    echo "<li><strong>{$shift['shift_name']}</strong>: {$shift['start_time']} - {$shift['end_time']}</li>";
}
echo "</ul>";

echo "<form method='post' action=''>";
echo "<button type='submit' name='update_shifts' style='padding: 10px 20px; background-color: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px;'>Cập nhật Ca Làm Việc</button>";
echo "</form>";

echo "<div class='warning-message'><strong>Lưu ý:</strong> Cập nhật sẽ xóa tất cả ca làm việc có ID > 2 (nếu có).</div>";

echo "<h2>Giải thích về thời gian ngoài ca làm việc:</h2>";
echo "<p>Hệ thống của bạn chỉ có 2 ca làm việc: Ca sáng (7:00 - 14:00) và Ca chiều (14:00 - 22:00).</p>";
echo "<p>Vậy từ 22:00 tối đến 7:00 sáng hôm sau là thời gian quán <strong>không hoạt động</strong>.</p>";
echo "<p>Nếu truy cập hệ thống trong thời gian này, bạn sẽ thấy thông báo <em>\"Không thể xác định ca làm việc hiện tại\"</em>.</p>";
echo "<p>Đây là <strong>hành vi mong muốn</strong> vì quán không hoạt động vào khoảng thời gian này.</p>";

// Hiển thị thời gian hiện tại
$current_time = date('H:i:s');
echo "<h2>Thời gian hiện tại:</h2>";
echo "<p>Hiện tại là: <strong>{$current_time}</strong></p>";

// Kiểm tra thời gian hiện tại có thuộc ca làm việc nào không
$in_shift = false;
foreach ($shifts as $shift) {
    if (($current_time >= $shift['start_time'] && $current_time <= $shift['end_time'])) {
        echo "<p class='success-message'>✅ Hiện tại đang trong <strong>{$shift['shift_name']}</strong> ({$shift['start_time']} - {$shift['end_time']})</p>";
        $in_shift = true;
        break;
    }
}

if (!$in_shift) {
    echo "<p class='warning-message'>⚠️ Hiện tại đang <strong>ngoài giờ làm việc</strong> của quán.</p>";
}

// Các liên kết điều hướng
echo "<div style='margin-top: 20px;'>";
echo "<a href='shift_debug.php' style='display: inline-block; padding: 10px 20px; margin-right: 10px; background-color: #2196F3; color: white; text-decoration: none; border-radius: 4px;'>Kiểm tra Ca Làm Việc</a>";
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
    h1, h2 {
        color: #333;
    }
    table {
        margin-bottom: 20px;
    }
    .success-message {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
        padding: 10px;
        border-radius: 4px;
        margin: 15px 0;
    }
    .warning-message {
        background-color: #fff3cd;
        color: #856404;
        border: 1px solid #ffeeba;
        padding: 10px;
        border-radius: 4px;
        margin: 15px 0;
    }
    .error-message {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
        padding: 10px;
        border-radius: 4px;
        margin: 15px 0;
    }
</style> 