<?php
session_start();
require_once 'config/db.php';
require_once 'config/functions.php';

echo "<h1>Debug Ca Làm Việc</h1>";

// Hiển thị thông tin thời gian
$current_time = date('H:i:s');
$current_date = date('Y-m-d');
echo "<div style='margin-bottom: 20px;'>";
echo "<p><strong>Thời gian hiện tại:</strong> {$current_time}</p>";
echo "<p><strong>Ngày hiện tại:</strong> {$current_date}</p>";
echo "</div>";

// Hiển thị thông tin ca làm việc từ database
echo "<h2>Ca làm việc trong cơ sở dữ liệu:</h2>";
$shifts_query = "SELECT * FROM Shifts ORDER BY shift_id";
$shifts_result = $conn->query($shifts_query);

if ($shifts_result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f2f2f2;'>";
    echo "<th style='padding: 8px; text-align: left;'>ID</th>";
    echo "<th style='padding: 8px; text-align: left;'>Tên ca</th>";
    echo "<th style='padding: 8px; text-align: left;'>Giờ bắt đầu</th>";
    echo "<th style='padding: 8px; text-align: left;'>Giờ kết thúc</th>";
    echo "<th style='padding: 8px; text-align: left;'>Kiểm tra thời gian hiện tại</th>";
    echo "</tr>";
    
    while ($shift = $shifts_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td style='padding: 8px;'>" . $shift['shift_id'] . "</td>";
        echo "<td style='padding: 8px;'>" . $shift['shift_name'] . "</td>";
        echo "<td style='padding: 8px;'>" . $shift['start_time'] . "</td>";
        echo "<td style='padding: 8px;'>" . $shift['end_time'] . "</td>";
        
        // Kiểm tra nếu thời gian hiện tại nằm trong ca này
        $start_time = $shift['start_time'];
        $end_time = $shift['end_time'];
        
        // Trường hợp 1: Ca không vượt qua nửa đêm
        if ($start_time <= $end_time) {
            $in_shift = ($current_time >= $start_time && $current_time <= $end_time);
            $condition = "'{$current_time}' BETWEEN '{$start_time}' AND '{$end_time}'";
        } 
        // Trường hợp 2: Ca vượt qua nửa đêm
        else {
            $in_shift = ($current_time >= $start_time || $current_time <= $end_time);
            $condition = "'{$current_time}' >= '{$start_time}' OR '{$current_time}' <= '{$end_time}'";
        }
        
        if ($in_shift) {
            echo "<td style='padding: 8px; background-color: #d4edda; color: #155724;'>✓ Thời gian hiện tại nằm trong ca này ({$condition})</td>";
        } else {
            echo "<td style='padding: 8px; background-color: #f8d7da; color: #721c24;'>✗ Thời gian hiện tại không nằm trong ca này ({$condition})</td>";
        }
        
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "<p>Không tìm thấy ca làm việc nào trong cơ sở dữ liệu!</p>";
}

// Hiển thị code của hàm getCurrentShift hiện tại
echo "<h2>Code hiện tại của hàm getCurrentShift():</h2>";
$reflection = new ReflectionFunction('getCurrentShift');
$startLine = $reflection->getStartLine();
$endLine = $reflection->getEndLine();
$length = $endLine - $startLine + 1;

$file = new SplFileObject($reflection->getFileName());
$file->seek($startLine - 1);
$code = "";
for ($i = 0; $i < $length; $i++) {
    $code .= $file->current();
    $file->next();
}

echo "<pre style='background-color: #f5f5f5; padding: 15px; border-radius: 5px; overflow: auto;'>{$code}</pre>";

// Kiểm tra hàm getCurrentShift
echo "<h2>Kiểm tra hàm getCurrentShift():</h2>";
$current_shift = getCurrentShift($conn);

if ($current_shift) {
    echo "<p style='color: green; font-weight: bold;'>✓ Đã xác định được ca làm việc hiện tại: ID = {$current_shift}</p>";
} else {
    echo "<p style='color: red; font-weight: bold;'>✗ Không xác định được ca làm việc hiện tại.</p>";
    echo "<p>Kiểm tra lại hàm getCurrentShift và dữ liệu trong bảng Shifts.</p>";
}

// Kiểm tra mã trên trang POS
echo "<h2>Kiểm tra mã trên trang POS:</h2>";
echo "<pre style='background-color: #f5f5f5; padding: 15px; border-radius: 5px; overflow: auto;'>
// Get current shift
\$current_shift = getCurrentShift(\$conn);
if (!\$current_shift) {
    // If cannot determine shift, assign default value to continue
    \$current_shift = 1;
    \$shift_warning = \"Không thể xác định ca làm việc hiện tại. Đã sử dụng ca mặc định.\";
}
</pre>";

// Xử lý ca làm việc mặc định
echo "<h2>Xử lý ca làm việc mặc định:</h2>";
echo "<p>Khi không thể xác định ca làm việc, hệ thống sẽ sử dụng ca mặc định (ID = 1).</p>";

$default_shift_query = "SELECT * FROM Shifts WHERE shift_id = 1";
$default_shift_result = $conn->query($default_shift_query);

if ($default_shift_result && $default_shift_result->num_rows > 0) {
    $default_shift = $default_shift_result->fetch_assoc();
    echo "<p style='color: green;'>✓ Ca mặc định tồn tại: <strong>{$default_shift['shift_name']}</strong> ({$default_shift['start_time']} - {$default_shift['end_time']})</p>";
} else {
    echo "<p style='color: red;'>✗ Ca mặc định (ID = 1) không tồn tại trong cơ sở dữ liệu!</p>";
    echo "<p>Hãy tạo ca làm việc với ID = 1 hoặc thay đổi ID mặc định trong file staff/pos.php</p>";
}

// Kiểm tra xem các ca làm việc có phủ đầy đủ 24 giờ không
echo "<h2>Kiểm tra phạm vi ca làm việc:</h2>";
$shifts_query = "SELECT * FROM Shifts ORDER BY start_time";
$shifts_result = $conn->query($shifts_query);

if ($shifts_result->num_rows > 0) {
    $shifts = array();
    while ($row = $shifts_result->fetch_assoc()) {
        $shifts[] = $row;
    }
    
    // Kiểm tra xem các ca làm việc có phủ đầy đủ 24 giờ không
    $all_covered = true;
    $gaps = array();
    
    for ($i = 0; $i < count($shifts); $i++) {
        $current = $shifts[$i];
        $next = $shifts[($i + 1) % count($shifts)];
        
        if ($current['end_time'] !== $next['start_time']) {
            $all_covered = false;
            $gaps[] = "Khoảng trống giữa {$current['shift_name']} (kết thúc {$current['end_time']}) và {$next['shift_name']} (bắt đầu {$next['start_time']})";
        }
    }
    
    if ($all_covered) {
        echo "<p style='color: green;'>✓ Các ca làm việc đã phủ đầy đủ 24 giờ.</p>";
    } else {
        echo "<p style='color: orange;'>⚠ Có khoảng trống giữa các ca làm việc:</p>";
        echo "<ul>";
        foreach ($gaps as $gap) {
            echo "<li>{$gap}</li>";
        }
        echo "</ul>";
        echo "<p>Điều này có thể dẫn đến việc không xác định được ca làm việc ở một số thời điểm cụ thể.</p>";
    }
} else {
    echo "<p>Không có ca làm việc nào để kiểm tra.</p>";
}

// Đề xuất giải pháp
echo "<h2>Đề xuất giải pháp:</h2>";
echo "<ol>";
echo "<li>Đảm bảo hàm getCurrentShift đã được cập nhật để xử lý ca vượt qua nửa đêm:</li>";
echo "<pre style='background-color: #f5f5f5; padding: 15px; border-radius: 5px; overflow: auto;'>
function getCurrentShift(\$conn) {
    \$current_time = date('H:i:s');
    
    // Trường hợp 1: Ca làm việc không vượt qua nửa đêm
    \$query1 = \"SELECT shift_id FROM Shifts WHERE ? BETWEEN start_time AND end_time\";
    \$stmt1 = \$conn->prepare(\$query1);
    \$stmt1->bind_param(\"s\", \$current_time);
    \$stmt1->execute();
    \$result1 = \$stmt1->get_result();
    
    if (\$result1->num_rows > 0) {
        \$row = \$result1->fetch_assoc();
        return \$row['shift_id'];
    }
    
    // Trường hợp 2: Ca làm việc vượt qua nửa đêm (giờ kết thúc < giờ bắt đầu)
    \$query2 = \"SELECT shift_id FROM Shifts WHERE start_time > end_time 
                AND (? >= start_time OR ? <= end_time)\";
    \$stmt2 = \$conn->prepare(\$query2);
    \$stmt2->bind_param(\"ss\", \$current_time, \$current_time);
    \$stmt2->execute();
    \$result2 = \$stmt2->get_result();
    
    if (\$result2->num_rows > 0) {
        \$row = \$result2->fetch_assoc();
        return \$row['shift_id'];
    }
    
    return null;
}</pre>";
echo "<li>Đảm bảo rằng có ca làm việc có ID = 1 trong cơ sở dữ liệu.</li>";
echo "<li>Kiểm tra xem các ca làm việc đã phủ đủ 24 giờ chưa, tránh khoảng trống.</li>";
echo "<li>Restart web server sau khi cập nhật code để đảm bảo thay đổi được áp dụng.</li>";
echo "</ol>";

echo "<div style='margin-top: 20px;'>";
echo "<a href='update_shift_function.php' style='display: inline-block; padding: 10px 20px; margin-right: 10px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 4px;'>Cập nhật hàm getCurrentShift</a>";
echo "<a href='staff/pos.php' style='display: inline-block; padding: 10px 20px; background-color: #2196F3; color: white; text-decoration: none; border-radius: 4px;'>Quay lại trang POS</a>";
echo "</div>";
?> 