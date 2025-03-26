<?php
session_start();
require_once '../config/db.php';
require_once '../config/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

// Check if user is admin
if ($_SESSION['role'] !== 'Quản lý') {
    header("Location: ../index.php");
    exit();
}

// Check and create necessary report tables if they don't exist
function ensureReportTablesExist($conn) {
    // Check if Revenue_Reports table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'Revenue_Reports'");
    if ($table_check->num_rows == 0) {
        // Create Revenue_Reports table
        $create_revenue_table = "CREATE TABLE Revenue_Reports (
            report_id INT AUTO_INCREMENT PRIMARY KEY,
            shift_id INT NOT NULL,
            total_revenue DECIMAL(10,2) NOT NULL,
            total_orders INT NOT NULL,
            report_date DATE DEFAULT CURRENT_DATE,
            FOREIGN KEY (shift_id) REFERENCES Shifts(shift_id)
        )";
        $conn->query($create_revenue_table);
        error_log("Created Revenue_Reports table");
    }
    
    // Check if Profit_Reports table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'Profit_Reports'");
    if ($table_check->num_rows == 0) {
        // Create Profit_Reports table
        $create_profit_table = "CREATE TABLE Profit_Reports (
            report_id INT AUTO_INCREMENT PRIMARY KEY,
            month INT NOT NULL,
            year INT NOT NULL,
            total_revenue DECIMAL(10,2) NOT NULL,
            total_cost DECIMAL(10,2) NOT NULL,
            total_orders INT NOT NULL DEFAULT 0,
            net_profit DECIMAL(10,2) GENERATED ALWAYS AS (total_revenue - total_cost) STORED,
            UNIQUE (month, year)
        )";
        $conn->query($create_profit_table);
        error_log("Created Profit_Reports table");
    }
    
    // Check if Stock_Entries table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'Stock_Entries'");
    if ($table_check->num_rows == 0) {
        // Create Stock_Entries table
        $create_stock_entries_table = "CREATE TABLE Stock_Entries (
            entry_id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            quantity INT NOT NULL,
            unit_cost DECIMAL(10,2) NOT NULL,
            total_cost DECIMAL(10,2) NOT NULL,
            entry_datetime DATETIME DEFAULT CURRENT_TIMESTAMP,
            entry_type ENUM('nhập', 'xuất') NOT NULL,
            notes TEXT,
            FOREIGN KEY (product_id) REFERENCES Products(product_id)
        )";
        $conn->query($create_stock_entries_table);
        error_log("Created Stock_Entries table");
    }
}

// Ensure report tables exist
ensureReportTablesExist($conn);

// Filter parameters
$filter_type = isset($_GET['filter_type']) ? $_GET['filter_type'] : 'shift';
$filter_date = isset($_GET['filter_date']) ? $_GET['filter_date'] : date('Y-m-d');
$filter_month = isset($_GET['filter_month']) ? (int)$_GET['filter_month'] : date('m');
$filter_year = isset($_GET['filter_year']) ? (int)$_GET['filter_year'] : date('Y');

// Session message for reports
$success_message = '';
if (isset($_SESSION['report_success'])) {
    $success_message = $_SESSION['report_success'];
    unset($_SESSION['report_success']);
}

// Pagination for shift reports
$shift_per_page = 10;
$shift_page = isset($_GET['shift_page']) ? (int)$_GET['shift_page'] : 1;
$shift_offset = ($shift_page - 1) * $shift_per_page;
$shift_total_query = "SELECT COUNT(*) FROM Revenue_Reports WHERE DATE(report_date) = '$filter_date'";
$shift_total_result = $conn->query($shift_total_query)->fetch_row()[0];
$shift_total_pages = ceil($shift_total_result / $shift_per_page);

// Revenue Reports by Shift
$shift_reports_query = "SELECT rr.*, s.shift_name 
                        FROM Revenue_Reports rr 
                        JOIN Shifts s ON rr.shift_id = s.shift_id 
                        WHERE DATE(rr.report_date) = ? 
                        ORDER BY rr.report_date LIMIT $shift_offset, $shift_per_page";
$shift_reports_stmt = $conn->prepare($shift_reports_query);
$shift_reports_stmt->bind_param("s", $filter_date);
$shift_reports_stmt->execute();
$shift_reports_result = $shift_reports_stmt->get_result();

// Daily Revenue
$daily_revenue_query = "SELECT DATE(o.order_time) AS report_day, SUM(o.total_price) AS daily_revenue, COUNT(o.order_id) AS daily_orders
                        FROM Orders o 
                        WHERE DATE(o.order_time) = ? AND o.status IN ('Đã thanh toán', 'Hoàn thành')
                        GROUP BY DATE(o.order_time)";
$daily_revenue_stmt = $conn->prepare($daily_revenue_query);
$daily_revenue_stmt->bind_param("s", $filter_date);
$daily_revenue_stmt->execute();
$daily_revenue_result = $daily_revenue_stmt->get_result();

// Profit Reports by Month/Year
$profit_reports_query = "SELECT * FROM Profit_Reports 
                         WHERE month = ? AND year = ?";
$profit_reports_stmt = $conn->prepare($profit_reports_query);
$profit_reports_stmt->bind_param("ii", $filter_month, $filter_year);
$profit_reports_stmt->execute();
$profit_reports_result = $profit_reports_stmt->get_result();

// Data for Profit Chart
$yearly_profit_data = [];
for ($i = $filter_year - 4; $i <= $filter_year; $i++) {
    $query = "SELECT net_profit FROM Profit_Reports WHERE year = $i AND month = $filter_month";
    $result = $conn->query($query);
    $row = $result->fetch_assoc();
    $yearly_profit_data[$i] = $row && $row['net_profit'] !== null ? (float)$row['net_profit'] : 0;
}

// Auto-generate reports from Orders
if (isset($_GET['generate'])) {
    if ($_GET['generate'] == 'shift') {
        $shift_query = "SELECT o.shift_id, s.shift_name, SUM(o.total_price) AS total_revenue, COUNT(o.order_id) AS total_orders, DATE(o.order_time) AS report_date,
                       SUM(CASE WHEN p.payment_method = 'Tiền mặt' THEN o.total_price ELSE 0 END) AS cash_amount,
                       SUM(CASE WHEN p.payment_method = 'Thẻ' THEN o.total_price ELSE 0 END) AS card_amount,
                       SUM(CASE WHEN p.payment_method = 'MoMo' THEN o.total_price ELSE 0 END) AS momo_amount,
                       SUM(CASE WHEN p.payment_method NOT IN ('Tiền mặt', 'Thẻ', 'MoMo') THEN o.total_price ELSE 0 END) AS other_amount
                    FROM Orders o 
                    JOIN Shifts s ON o.shift_id = s.shift_id
                    LEFT JOIN Payments p ON o.order_id = p.order_id
                    WHERE DATE(o.order_time) = ? AND o.status IN ('Đã thanh toán', 'Hoàn thành')
                    GROUP BY o.shift_id, DATE(o.order_time)";
        $shift_stmt = $conn->prepare($shift_query);
        $shift_stmt->bind_param("s", $filter_date);
        $shift_stmt->execute();
        $shift_result = $shift_stmt->get_result();
        
        // Store result for display
        $_SESSION['temp_report_data'] = array();
        while ($row = $shift_result->fetch_assoc()) {
            $_SESSION['temp_report_data'][] = $row;
        }
        
        $_SESSION['report_type'] = 'shift';
        header("Location: reports.php?filter_type=shift&filter_date=$filter_date&preview=1");
        exit;
    } 
    elseif ($_GET['generate'] == 'daily') {
        // Generate daily report from Orders
        $daily_query = "SELECT DATE(o.order_time) AS report_date, SUM(o.total_price) AS total_revenue, 
                        COUNT(o.order_id) AS total_orders,
                        SUM(CASE WHEN p.payment_method = 'Tiền mặt' THEN o.total_price ELSE 0 END) AS cash_amount,
                        SUM(CASE WHEN p.payment_method = 'Thẻ' THEN o.total_price ELSE 0 END) AS card_amount,
                        SUM(CASE WHEN p.payment_method = 'MoMo' THEN o.total_price ELSE 0 END) AS momo_amount,
                        SUM(CASE WHEN p.payment_method NOT IN ('Tiền mặt', 'Thẻ', 'MoMo') THEN o.total_price ELSE 0 END) AS other_amount
                        FROM Orders o 
                        LEFT JOIN Payments p ON o.order_id = p.order_id
                        WHERE DATE(o.order_time) = ? AND o.status IN ('Đã thanh toán', 'Hoàn thành')
                        GROUP BY DATE(o.order_time)";
        $daily_stmt = $conn->prepare($daily_query);
        $daily_stmt->bind_param("s", $filter_date);
        $daily_stmt->execute();
        $daily_result = $daily_stmt->get_result();
        
        // Store result for display
        if ($daily_result->num_rows > 0) {
            $_SESSION['temp_report_data'] = $daily_result->fetch_assoc();
            $_SESSION['report_type'] = 'daily';
            header("Location: reports.php?filter_type=daily&filter_date=$filter_date&preview=1");
        } else {
            $_SESSION['report_success'] = "Không có dữ liệu đơn hàng cho ngày " . date('d/m/Y', strtotime($filter_date)) . "!";
            header("Location: reports.php?filter_type=daily&filter_date=$filter_date");
        }
        exit;
    }
    elseif ($_GET['generate'] == 'profit') {
        // Generate profit report for month/year
        $profit_query = "SELECT MONTH(o.order_time) AS report_month, YEAR(o.order_time) AS report_year, 
                        SUM(o.total_price) AS total_revenue, COUNT(o.order_id) AS total_orders 
                        FROM Orders o 
                        WHERE MONTH(o.order_time) = ? AND YEAR(o.order_time) = ? AND o.status IN ('Đã thanh toán', 'Hoàn thành')
                        GROUP BY MONTH(o.order_time), YEAR(o.order_time)";
        $profit_stmt = $conn->prepare($profit_query);
        $profit_stmt->bind_param("ii", $filter_month, $filter_year);
        $profit_stmt->execute();
        $profit_result = $profit_stmt->get_result();
        
        if ($profit_result->num_rows > 0) {
            $profit_data = $profit_result->fetch_assoc();
            
            // Get total costs for this month (from stock entries)
            $cost_query = "SELECT COALESCE(SUM(total_cost), 0) AS total_cost 
                        FROM Stock_Entries 
                        WHERE MONTH(entry_datetime) = ? AND YEAR(entry_datetime) = ?";
            $cost_stmt = $conn->prepare($cost_query);
            $cost_stmt->bind_param("ii", $filter_month, $filter_year);
            $cost_stmt->execute();
            $cost_result = $cost_stmt->get_result();
            $cost_data = $cost_result->fetch_assoc();
            
            $profit_data['total_cost'] = $cost_data['total_cost'] ?? 0;
            $profit_data['net_profit'] = $profit_data['total_revenue'] - $profit_data['total_cost'];
            
            $_SESSION['temp_report_data'] = $profit_data;
            $_SESSION['report_type'] = 'profit';
            header("Location: reports.php?filter_type=profit&filter_month=$filter_month&filter_year=$filter_year&preview=1");
        } else {
            $_SESSION['report_success'] = "Không có dữ liệu doanh thu cho tháng $filter_month/$filter_year!";
            header("Location: reports.php?filter_type=profit&filter_month=$filter_month&filter_year=$filter_year");
        }
        exit;
    }
}

// Save reports to database
if (isset($_GET['save_report'])) {
    if ($_GET['save_report'] == 'shift' && isset($_SESSION['temp_report_data'])) {
        $reports_saved = 0;
        foreach ($_SESSION['temp_report_data'] as $report) {
            // Check if report already exists
            $check_query = "SELECT COUNT(*) FROM Revenue_Reports WHERE shift_id = ? AND report_date = ?";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bind_param("is", $report['shift_id'], $report['report_date']);
            $check_stmt->execute();
            $exists = $check_stmt->get_result()->fetch_row()[0];
            
            if ($exists == 0) {
                // Insert new report
                $insert_query = "INSERT INTO Revenue_Reports (shift_id, total_revenue, total_orders, report_date) 
                            VALUES (?, ?, ?, ?)";
                $insert_stmt = $conn->prepare($insert_query);
                $insert_stmt->bind_param("idis", 
                    $report['shift_id'], 
                    $report['total_revenue'], 
                    $report['total_orders'], 
                    $report['report_date']
                );
                $insert_stmt->execute();
                $reports_saved++;
            }
        }
        
        if ($reports_saved > 0) {
            $_SESSION['report_success'] = "Đã lưu $reports_saved báo cáo theo ca cho ngày " . date('d/m/Y', strtotime($filter_date));
        } else {
            $_SESSION['report_success'] = "Các báo cáo đã tồn tại trong hệ thống";
        }
        
        unset($_SESSION['temp_report_data']);
        unset($_SESSION['report_type']);
        header("Location: reports.php?filter_type=shift&filter_date=$filter_date");
        exit;
    } 
    elseif ($_GET['save_report'] == 'daily' && isset($_SESSION['temp_report_data'])) {
        $report = $_SESSION['temp_report_data'];
        
        // For daily report, we need to insert for all shifts of that day
        $shift_query = "SELECT shift_id 
                        FROM Orders 
                        WHERE DATE(order_time) = ? AND status IN ('Đã thanh toán', 'Hoàn thành')
                        GROUP BY shift_id";
        $shift_stmt = $conn->prepare($shift_query);
        $shift_stmt->bind_param("s", $filter_date);
        $shift_stmt->execute();
        $shift_result = $shift_stmt->get_result();
        
        $reports_saved = 0;
        while ($shift = $shift_result->fetch_assoc()) {
            // Check if report already exists
            $check_query = "SELECT COUNT(*) FROM Revenue_Reports WHERE shift_id = ? AND report_date = ?";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bind_param("is", $shift['shift_id'], $filter_date);
            $check_stmt->execute();
            $exists = $check_stmt->get_result()->fetch_row()[0];
            
            if ($exists == 0) {
                // Get revenue and orders for this shift with payment method breakdown
                $shift_data_query = "SELECT 
                                    SUM(o.total_price) AS shift_revenue, 
                                    COUNT(o.order_id) AS shift_orders,
                                    SUM(CASE WHEN p.payment_method = 'Tiền mặt' THEN o.total_price ELSE 0 END) AS shift_cash_amount,
                                    SUM(CASE WHEN p.payment_method = 'Thẻ' THEN o.total_price ELSE 0 END) AS shift_card_amount,
                                    SUM(CASE WHEN p.payment_method = 'MoMo' THEN o.total_price ELSE 0 END) AS shift_momo_amount,
                                    SUM(CASE WHEN p.payment_method NOT IN ('Tiền mặt', 'Thẻ', 'MoMo') THEN o.total_price ELSE 0 END) AS shift_other_amount
                                    FROM Orders o 
                                    LEFT JOIN Payments p ON o.order_id = p.order_id
                                    WHERE DATE(o.order_time) = ? AND o.shift_id = ? AND o.status IN ('Đã thanh toán', 'Hoàn thành')";
                $shift_data_stmt = $conn->prepare($shift_data_query);
                $shift_data_stmt->bind_param("si", $filter_date, $shift['shift_id']);
                $shift_data_stmt->execute();
                $shift_data = $shift_data_stmt->get_result()->fetch_assoc();
                
                // Insert new report
                $insert_query = "INSERT INTO Revenue_Reports (shift_id, total_revenue, total_orders, report_date) 
                                VALUES (?, ?, ?, ?)";
                $insert_stmt = $conn->prepare($insert_query);
                $insert_stmt->bind_param("idis", 
                    $shift['shift_id'], 
                    $shift_data['shift_revenue'], 
                    $shift_data['shift_orders'], 
                    $filter_date
                );
                $insert_stmt->execute();
                $reports_saved++;
            }
        }
        
        if ($reports_saved > 0) {
            $_SESSION['report_success'] = "Đã lưu báo cáo theo ngày " . date('d/m/Y', strtotime($filter_date)) . " cho $reports_saved ca";
        } else {
            $_SESSION['report_success'] = "Các báo cáo đã tồn tại trong hệ thống";
        }
        
        unset($_SESSION['temp_report_data']);
        unset($_SESSION['report_type']);
        header("Location: reports.php?filter_type=daily&filter_date=$filter_date");
        exit;
    } 
    elseif ($_GET['save_report'] == 'profit' && isset($_SESSION['temp_report_data'])) {
        $report = $_SESSION['temp_report_data'];
        
        // Check if profit report exists
        $check_query = "SELECT COUNT(*) FROM Profit_Reports WHERE month = ? AND year = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("ii", $report['report_month'], $report['report_year']);
        $check_stmt->execute();
        $exists = $check_stmt->get_result()->fetch_row()[0];
        
        if ($exists == 0) {
            // Insert new profit report
            $insert_query = "INSERT INTO Profit_Reports (month, year, total_revenue, total_cost, total_orders) 
                            VALUES (?, ?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bind_param("iiddi", $report['report_month'], $report['report_year'], $report['total_revenue'], 
                                    $report['total_cost'], $report['total_orders']);
            $insert_stmt->execute();
            $_SESSION['report_success'] = "Đã lưu báo cáo lợi nhuận tháng {$report['report_month']}/{$report['report_year']}";
        } else {
            // Update existing report
            $update_query = "UPDATE Profit_Reports 
                            SET total_revenue = ?, total_cost = ?, total_orders = ? 
                            WHERE month = ? AND year = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("ddiii", $report['total_revenue'], $report['total_cost'], 
                                    $report['total_orders'], $report['report_month'], $report['report_year']);
            $update_stmt->execute();
            $_SESSION['report_success'] = "Đã cập nhật báo cáo lợi nhuận tháng {$report['report_month']}/{$report['report_year']}";
        }
        
        unset($_SESSION['temp_report_data']);
        unset($_SESSION['report_type']);
        header("Location: reports.php?filter_type=profit&filter_month={$report['report_month']}&filter_year={$report['report_year']}");
        exit;
    }
}

// Export to Excel
if (isset($_GET['export'])) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $_GET['export'] . '_report_' . date('Ymd') . '.xls"');
    
    if ($_GET['export'] == 'shift') {
        echo "ID\tCa Làm Việc\tDoanh Thu\tTiền mặt\tThẻ\tMoMo\tKhác\tSố Đơn Hàng\tNgày Báo Cáo\n";
        $export_query = "SELECT rr.report_id, s.shift_name, rr.total_revenue, rr.total_orders, rr.report_date, rr.shift_id 
                         FROM Revenue_Reports rr JOIN Shifts s ON rr.shift_id = s.shift_id 
                         WHERE DATE(rr.report_date) = '$filter_date'";
        $export_result = $conn->query($export_query);
        while ($row = $export_result->fetch_assoc()) {
            // Get payment breakdown for this shift
            $payment_query = "SELECT 
                SUM(CASE WHEN p.payment_method = 'Tiền mặt' THEN o.total_price ELSE 0 END) AS cash_amount,
                SUM(CASE WHEN p.payment_method = 'Thẻ' THEN o.total_price ELSE 0 END) AS card_amount,
                SUM(CASE WHEN p.payment_method = 'MoMo' THEN o.total_price ELSE 0 END) AS momo_amount,
                SUM(CASE WHEN p.payment_method NOT IN ('Tiền mặt', 'Thẻ', 'MoMo') THEN o.total_price ELSE 0 END) AS other_amount
                FROM Orders o 
                LEFT JOIN Payments p ON o.order_id = p.order_id
                WHERE DATE(o.order_time) = ? AND o.shift_id = ? AND o.status IN ('Đã thanh toán', 'Hoàn thành')";
            $payment_stmt = $conn->prepare($payment_query);
            $payment_stmt->bind_param("si", $row['report_date'], $row['shift_id']);
            $payment_stmt->execute();
            $payment_data = $payment_stmt->get_result()->fetch_assoc();
            
            $cash_amount = $payment_data['cash_amount'] ?? 0;
            $card_amount = $payment_data['card_amount'] ?? 0;
            $momo_amount = $payment_data['momo_amount'] ?? 0;
            $other_amount = $payment_data['other_amount'] ?? 0;
            
            echo implode("\t", [
                $row['report_id'], 
                $row['shift_name'], 
                $row['total_revenue'], 
                $cash_amount, 
                $card_amount, 
                $momo_amount, 
                $other_amount, 
                $row['total_orders'], 
                date('d/m/Y', strtotime($row['report_date']))
            ]) . "\n";
        }
    } elseif ($_GET['export'] == 'daily') {
        echo "Ngày\tDoanh Thu\tTiền mặt\tThẻ\tMoMo\tKhác\tSố Đơn Hàng\n";
        
        // First get total revenue and orders
        $base_query = "SELECT DATE(order_time) AS report_day, SUM(total_price) AS daily_revenue, COUNT(order_id) AS daily_orders 
                     FROM Orders WHERE DATE(order_time) = '$filter_date' AND status IN ('Đã thanh toán', 'Hoàn thành') GROUP BY DATE(order_time)";
        $base_result = $conn->query($base_query);
        
        // Get payment breakdown
        $payment_query = "SELECT 
                        SUM(CASE WHEN p.payment_method = 'Tiền mặt' THEN o.total_price ELSE 0 END) AS cash_amount,
                        SUM(CASE WHEN p.payment_method = 'Thẻ' THEN o.total_price ELSE 0 END) AS card_amount,
                        SUM(CASE WHEN p.payment_method = 'MoMo' THEN o.total_price ELSE 0 END) AS momo_amount,
                        SUM(CASE WHEN p.payment_method NOT IN ('Tiền mặt', 'Thẻ', 'MoMo') THEN o.total_price ELSE 0 END) AS other_amount
                        FROM Orders o 
                        LEFT JOIN Payments p ON o.order_id = p.order_id
                        WHERE DATE(o.order_time) = ? AND o.status IN ('Đã thanh toán', 'Hoàn thành')";
        $payment_stmt = $conn->prepare($payment_query);
        $payment_stmt->bind_param("s", $filter_date);
        $payment_stmt->execute();
        $payment_result = $payment_stmt->get_result()->fetch_assoc();
        
        if (!$payment_result) {
            $payment_result = [
                'cash_amount' => 0,
                'card_amount' => 0,
                'momo_amount' => 0,
                'other_amount' => 0
            ];
        }
        
        $total_cash = $payment_result['cash_amount'] ?? 0;
        $total_card = $payment_result['card_amount'] ?? 0;
        $total_momo = $payment_result['momo_amount'] ?? 0;
        $total_other = $payment_result['other_amount'] ?? 0;
        
        if ($base_result->num_rows > 0) {
            $row = $base_result->fetch_assoc();
            echo implode("\t", [
                date('d/m/Y', strtotime($row['report_day'])),
                $row['daily_revenue'],
                $total_cash,
                $total_card,
                $total_momo,
                $total_other,
                $row['daily_orders']
            ]) . "\n";
        }
    } elseif ($_GET['export'] == 'profit') {
        echo "ID\tTháng\tNăm\tDoanh Thu\tChi Phí\tSố Đơn Hàng\tLợi Nhuận\n";
        $export_query = "SELECT report_id, month, year, total_revenue, total_cost, total_orders, net_profit 
                         FROM Profit_Reports WHERE month = $filter_month AND year = $filter_year";
        $export_result = $conn->query($export_query);
        while ($row = $export_result->fetch_assoc()) {
            echo implode("\t", [$row['report_id'], $row['month'], $row['year'], $row['total_revenue'], $row['total_cost'], $row['total_orders'], $row['net_profit']]) . "\n";
        }
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Báo Cáo - Hệ Thống Quản Lý Quán Cà Phê</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .tabs {
            overflow: hidden;
            border-bottom: 2px solid #ddd;
            margin-bottom: 20px;
        }
        .tabs button {
            background-color: #f1f1f1;
            float: left;
            border: none;
            outline: none;
            cursor: pointer;
            padding: 12px 20px;
            transition: 0.3s;
            font-size: 16px;
            border-radius: 5px 5px 0 0;
            margin-right: 5px;
        }
        .tabs button:hover {
            background-color: #ddd;
        }
        .tabs button.active {
            background-color: #4CAF50;
            color: white;
        }
        .tab-content {
            display: none;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 0 5px 5px 5px;
        }
        .tab-content.active {
            display: block;
        }
        .export-btn, .generate-btn {
            float: right;
            margin-bottom: 10px;
            margin-left: 10px;
        }
        .pagination {
            margin-top: 20px;
            text-align: center;
        }
        .pagination a {
            padding: 8px 16px;
            text-decoration: none;
            color: #4CAF50;
            border: 1px solid #ddd;
            margin: 0 4px;
        }
        .pagination a.active {
            background-color: #4CAF50;
            color: white;
        }
        canvas {
            max-width: 100%;
            margin-top: 20px;
        }
        
        /* Print styles */
        @media print {
            body * {
                visibility: hidden;
            }
            .card-body, .card-body * {
                visibility: visible;
            }
            .card-body {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="container">
            <a href="dashboard.php" class="navbar-brand">Quản Lý Quán Cà Phê</a>
            <ul class="navbar-nav">
                <li class="nav-item"><a href="products.php" class="nav-link">Sản Phẩm</a></li>
                <li class="nav-item"><a href="categories.php" class="nav-link">Danh Mục</a></li>
                <li class="nav-item"><a href="inventory.php" class="nav-link">Kho Hàng</a></li>
                <li class="nav-item"><a href="promotions.php" class="nav-link">Khuyến Mãi</a></li>
                <li class="nav-item"><a href="reports.php" class="nav-link active">Báo Cáo</a></li>
                <li class="nav-item"><a href="users.php" class="nav-link">Nhân Viên</a></li>
                <li class="nav-item"><a href="../logout.php" class="nav-link">Đăng Xuất</a></li>
            </ul>
        </div>
    </nav>
    
    <!-- Main Content -->
    <div class="container">
        <?php if (!empty($success_message)): ?>
        <div class="alert alert-success">
            <?php echo $success_message; ?>
        </div>
        <?php endif; ?>
        
        <div class="dashboard">
            <div class="dashboard-header">
                <h1>Báo Cáo</h1>
            </div>
            
            <div class="tabs">
                <button class="tablinks <?php echo $filter_type == 'shift' ? 'active' : ''; ?>" onclick="showTab('shift')">Theo Ca</button>
                <button class="tablinks <?php echo $filter_type == 'daily' ? 'active' : ''; ?>" onclick="showTab('daily')">Theo Ngày</button>
                <button class="tablinks <?php echo $filter_type == 'profit' ? 'active' : ''; ?>" onclick="showTab('profit')">Lợi Nhuận</button>
            </div>

            <?php if (isset($_GET['preview']) && isset($_SESSION['temp_report_data']) && isset($_SESSION['report_type'])): ?>
            <!-- Preview Report -->
            <div class="card mb-4">
                <div class="card-header">
                    <h2>Xem Trước Báo Cáo 
                        <button onclick="window.print();" class="btn btn-info btn-sm export-btn">In Báo Cáo</button>
                        <a href="reports.php?filter_type=<?php echo $filter_type; ?>&filter_date=<?php echo $filter_date; ?>&filter_month=<?php echo $filter_month; ?>&filter_year=<?php echo $filter_year; ?>&save_report=<?php echo $_SESSION['report_type']; ?>" class="btn btn-primary btn-sm generate-btn">Lưu Báo Cáo</a>
                    </h2>
                </div>
                <div class="card-body">
                    <?php if ($_SESSION['report_type'] == 'shift'): ?>
                        <h3>Báo Cáo Theo Ca Ngày <?php echo date('d/m/Y', strtotime($filter_date)); ?></h3>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Ca Làm Việc</th>
                                    <th>Doanh Thu</th>
                                    <th>Tiền mặt</th>
                                    <th>Thẻ</th>
                                    <th>MoMo</th>
                                    <th>Khác</th>
                                    <th>Số Đơn Hàng</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total_revenue = 0;
                                $total_orders = 0;
                                $total_cash = 0;
                                $total_card = 0;
                                $total_momo = 0;
                                $total_other = 0;
                                foreach ($_SESSION['temp_report_data'] as $report): 
                                    $total_revenue += $report['total_revenue'];
                                    $total_orders += $report['total_orders'];
                                    
                                    // Get payment breakdown for this shift
                                    $payment_query = "SELECT 
                                        SUM(CASE WHEN p.payment_method = 'Tiền mặt' THEN o.total_price ELSE 0 END) AS cash_amount,
                                        SUM(CASE WHEN p.payment_method = 'Thẻ' THEN o.total_price ELSE 0 END) AS card_amount,
                                        SUM(CASE WHEN p.payment_method = 'MoMo' THEN o.total_price ELSE 0 END) AS momo_amount,
                                        SUM(CASE WHEN p.payment_method NOT IN ('Tiền mặt', 'Thẻ', 'MoMo') THEN o.total_price ELSE 0 END) AS other_amount
                                        FROM Orders o 
                                        LEFT JOIN Payments p ON o.order_id = p.order_id
                                        WHERE DATE(o.order_time) = ? AND o.shift_id = ? AND o.status IN ('Đã thanh toán', 'Hoàn thành')";
                                    $payment_stmt = $conn->prepare($payment_query);
                                    $payment_stmt->bind_param("si", $report['report_date'], $report['shift_id']);
                                    $payment_stmt->execute();
                                    $payment_data = $payment_stmt->get_result()->fetch_assoc();
                                    
                                    $cash_amount = $payment_data['cash_amount'] ?? 0;
                                    $card_amount = $payment_data['card_amount'] ?? 0;
                                    $momo_amount = $payment_data['momo_amount'] ?? 0;
                                    $other_amount = $payment_data['other_amount'] ?? 0;
                                    
                                    $total_cash += $cash_amount;
                                    $total_card += $card_amount;
                                    $total_momo += $momo_amount;
                                    $total_other += $other_amount;
                                ?>
                                    <tr>
                                        <td><?php echo $report['shift_name']; ?></td>
                                        <td><?php echo formatCurrency($report['total_revenue']); ?></td>
                                        <td><?php echo formatCurrency($cash_amount); ?></td>
                                        <td><?php echo formatCurrency($card_amount); ?></td>
                                        <td><?php echo formatCurrency($momo_amount); ?></td>
                                        <td><?php echo formatCurrency($other_amount); ?></td>
                                        <td><?php echo $report['total_orders']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="table-success">
                                    <th>Tổng Cộng</th>
                                    <th><?php echo formatCurrency($total_revenue); ?></th>
                                    <th><?php echo formatCurrency($total_cash); ?></th>
                                    <th><?php echo formatCurrency($total_card); ?></th>
                                    <th><?php echo formatCurrency($total_momo); ?></th>
                                    <th><?php echo formatCurrency($total_other); ?></th>
                                    <th><?php echo $total_orders; ?></th>
                                </tr>
                            </tbody>
                        </table>
                    <?php elseif ($_SESSION['report_type'] == 'daily'): ?>
                        <h3>Báo Cáo Theo Ngày <?php echo date('d/m/Y', strtotime($filter_date)); ?></h3>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Ngày</th>
                                    <th>Doanh Thu</th>
                                    <th>Tiền mặt</th>
                                    <th>Thẻ</th>
                                    <th>MoMo</th>
                                    <th>Khác</th>
                                    <th>Số Đơn Hàng</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Get payment breakdown for this day 
                                $payment_query = "SELECT 
                                    SUM(CASE WHEN p.payment_method = 'Tiền mặt' THEN o.total_price ELSE 0 END) AS cash_amount,
                                    SUM(CASE WHEN p.payment_method = 'Thẻ' THEN o.total_price ELSE 0 END) AS card_amount,
                                    SUM(CASE WHEN p.payment_method = 'MoMo' THEN o.total_price ELSE 0 END) AS momo_amount,
                                    SUM(CASE WHEN p.payment_method NOT IN ('Tiền mặt', 'Thẻ', 'MoMo') THEN o.total_price ELSE 0 END) AS other_amount
                                    FROM Orders o 
                                    LEFT JOIN Payments p ON o.order_id = p.order_id
                                    WHERE DATE(o.order_time) = ? AND o.status IN ('Đã thanh toán', 'Hoàn thành')";
                                $payment_stmt = $conn->prepare($payment_query);
                                $payment_stmt->bind_param("s", $filter_date);
                                $payment_stmt->execute();
                                $payment_data = $payment_stmt->get_result()->fetch_assoc();
                                
                                $cash_amount = $payment_data['cash_amount'] ?? 0;
                                $card_amount = $payment_data['card_amount'] ?? 0;
                                $momo_amount = $payment_data['momo_amount'] ?? 0;
                                $other_amount = $payment_data['other_amount'] ?? 0;
                                ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($_SESSION['temp_report_data']['report_date'])); ?></td>
                                    <td><?php echo formatCurrency($_SESSION['temp_report_data']['total_revenue']); ?></td>
                                    <td><?php echo formatCurrency($cash_amount); ?></td>
                                    <td><?php echo formatCurrency($card_amount); ?></td>
                                    <td><?php echo formatCurrency($momo_amount); ?></td>
                                    <td><?php echo formatCurrency($other_amount); ?></td>
                                    <td><?php echo $_SESSION['temp_report_data']['total_orders']; ?></td>
                                </tr>
                            </tbody>
                        </table>
                    <?php elseif ($_SESSION['report_type'] == 'profit'): ?>
                        <h3>Báo Cáo Lợi Nhuận Tháng <?php echo $_SESSION['temp_report_data']['report_month']; ?>/<?php echo $_SESSION['temp_report_data']['report_year']; ?></h3>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Tháng</th>
                                    <th>Năm</th>
                                    <th>Doanh Thu</th>
                                    <th>Chi Phí</th>
                                    <th>Số Đơn Hàng</th>
                                    <th>Lợi Nhuận</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><?php echo $_SESSION['temp_report_data']['report_month']; ?></td>
                                    <td><?php echo $_SESSION['temp_report_data']['report_year']; ?></td>
                                    <td><?php echo formatCurrency($_SESSION['temp_report_data']['total_revenue']); ?></td>
                                    <td><?php echo formatCurrency($_SESSION['temp_report_data']['total_cost']); ?></td>
                                    <td><?php echo $_SESSION['temp_report_data']['total_orders']; ?></td>
                                    <td><?php echo formatCurrency($_SESSION['temp_report_data']['net_profit']); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    <?php endif; ?>
                    <div class="text-center mt-3">
                        <p>Ngày xuất báo cáo: <?php echo date('d/m/Y H:i:s'); ?></p>
                        <p>Người xuất báo cáo: <?php echo $_SESSION['username']; ?></p>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Shift Reports -->
            <div id="shift" class="tab-content <?php echo $filter_type == 'shift' ? 'active' : ''; ?>">
                <div class="card">
                    <h2>Báo Cáo Theo Ca 
                        <a href="reports.php?filter_type=shift&filter_date=<?php echo $filter_date; ?>&export=shift" class="btn btn-success btn-sm export-btn">Xuất Excel</a>
                        <a href="reports.php?filter_type=shift&filter_date=<?php echo $filter_date; ?>&generate=shift" class="btn btn-primary btn-sm generate-btn">Tạo Báo Cáo</a>
                    </h2>
                    <form method="GET" action="reports.php" class="mb-3">
                        <input type="hidden" name="filter_type" value="shift">
                        <div class="row">
                            <div class="col-md-6">
                                <label for="filter_date">Chọn Ngày</label>
                                <input type="date" id="filter_date" name="filter_date" value="<?php echo $filter_date; ?>" onchange="this.form.submit()">
                            </div>
                        </div>
                    </form>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Ca Làm Việc</th>
                                <th>Doanh Thu</th>
                                <th>Tiền mặt</th>
                                <th>Thẻ</th>
                                <th>MoMo</th>
                                <th>Khác</th>
                                <th>Số Đơn Hàng</th>
                                <th>Ngày Báo Cáo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($shift_reports_result->num_rows > 0): ?>
                                <?php while ($report = $shift_reports_result->fetch_assoc()): 
                                    // Get payment breakdown for this shift
                                    $payment_query = "SELECT 
                                        SUM(CASE WHEN p.payment_method = 'Tiền mặt' THEN o.total_price ELSE 0 END) AS cash_amount,
                                        SUM(CASE WHEN p.payment_method = 'Thẻ' THEN o.total_price ELSE 0 END) AS card_amount,
                                        SUM(CASE WHEN p.payment_method = 'MoMo' THEN o.total_price ELSE 0 END) AS momo_amount,
                                        SUM(CASE WHEN p.payment_method NOT IN ('Tiền mặt', 'Thẻ', 'MoMo') THEN o.total_price ELSE 0 END) AS other_amount
                                        FROM Orders o 
                                        LEFT JOIN Payments p ON o.order_id = p.order_id
                                        WHERE DATE(o.order_time) = ? AND o.shift_id = ? AND o.status IN ('Đã thanh toán', 'Hoàn thành')";
                                    $payment_stmt = $conn->prepare($payment_query);
                                    $payment_stmt->bind_param("si", $report['report_date'], $report['shift_id']);
                                    $payment_stmt->execute();
                                    $payment_data = $payment_stmt->get_result()->fetch_assoc();
                                    
                                    $cash_amount = $payment_data['cash_amount'] ?? 0;
                                    $card_amount = $payment_data['card_amount'] ?? 0;
                                    $momo_amount = $payment_data['momo_amount'] ?? 0;
                                    $other_amount = $payment_data['other_amount'] ?? 0;
                                ?>
                                    <tr>
                                        <td><?php echo $report['report_id']; ?></td>
                                        <td><?php echo $report['shift_name']; ?></td>
                                        <td><?php echo formatCurrency($report['total_revenue']); ?></td>
                                        <td><?php echo formatCurrency($cash_amount); ?></td>
                                        <td><?php echo formatCurrency($card_amount); ?></td>
                                        <td><?php echo formatCurrency($momo_amount); ?></td>
                                        <td><?php echo formatCurrency($other_amount); ?></td>
                                        <td><?php echo $report['total_orders']; ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($report['report_date'])); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="9" class="text-center">Không có báo cáo cho ngày này</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <div class="pagination">
                        <?php for ($i = 1; $i <= $shift_total_pages; $i++): ?>
                            <a href="reports.php?filter_type=shift&filter_date=<?php echo $filter_date; ?>&shift_page=<?php echo $i; ?>" class="<?php echo $i == $shift_page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>

            <!-- Daily Reports -->
            <div id="daily" class="tab-content <?php echo $filter_type == 'daily' ? 'active' : ''; ?>">
                <div class="card">
                    <h2>Báo Cáo Theo Ngày 
                        <a href="reports.php?filter_type=daily&filter_date=<?php echo $filter_date; ?>&export=daily" class="btn btn-success btn-sm export-btn">Xuất Excel</a>
                        <a href="reports.php?filter_type=daily&filter_date=<?php echo $filter_date; ?>&generate=daily" class="btn btn-primary btn-sm generate-btn">Tạo Báo Cáo</a>
                    </h2>
                    <form method="GET" action="reports.php" class="mb-3">
                        <input type="hidden" name="filter_type" value="daily">
                        <div class="row">
                            <div class="col-md-6">
                                <label for="filter_date">Chọn Ngày</label>
                                <input type="date" id="filter_date" name="filter_date" value="<?php echo $filter_date; ?>" onchange="this.form.submit()">
                            </div>
                        </div>
                    </form>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Ngày</th>
                                <th>Doanh Thu</th>
                                <th>Tiền mặt</th>
                                <th>Thẻ</th>
                                <th>MoMo</th>
                                <th>Khác</th>
                                <th>Số Đơn Hàng</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($daily_revenue_result->num_rows > 0): ?>
                                <?php
                                // Get payment breakdown for this day
                                $payment_query = "SELECT 
                                    SUM(CASE WHEN p.payment_method = 'Tiền mặt' THEN o.total_price ELSE 0 END) AS cash_amount,
                                    SUM(CASE WHEN p.payment_method = 'Thẻ' THEN o.total_price ELSE 0 END) AS card_amount,
                                    SUM(CASE WHEN p.payment_method = 'MoMo' THEN o.total_price ELSE 0 END) AS momo_amount,
                                    SUM(CASE WHEN p.payment_method NOT IN ('Tiền mặt', 'Thẻ', 'MoMo') THEN o.total_price ELSE 0 END) AS other_amount
                                    FROM Orders o 
                                    LEFT JOIN Payments p ON o.order_id = p.order_id
                                    WHERE DATE(o.order_time) = ? AND o.status IN ('Đã thanh toán', 'Hoàn thành')";
                                $payment_stmt = $conn->prepare($payment_query);
                                $payment_stmt->bind_param("s", $filter_date);
                                $payment_stmt->execute();
                                $payment_data = $payment_stmt->get_result()->fetch_assoc();
                                
                                $cash_amount = $payment_data['cash_amount'] ?? 0;
                                $card_amount = $payment_data['card_amount'] ?? 0;
                                $momo_amount = $payment_data['momo_amount'] ?? 0;
                                $other_amount = $payment_data['other_amount'] ?? 0;
                                ?>
                                
                                <?php while ($report = $daily_revenue_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y', strtotime($report['report_day'])); ?></td>
                                        <td><?php echo formatCurrency($report['daily_revenue']); ?></td>
                                        <td><?php echo formatCurrency($cash_amount); ?></td>
                                        <td><?php echo formatCurrency($card_amount); ?></td>
                                        <td><?php echo formatCurrency($momo_amount); ?></td>
                                        <td><?php echo formatCurrency($other_amount); ?></td>
                                        <td><?php echo $report['daily_orders']; ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="7" class="text-center">Không có báo cáo cho ngày này</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Profit Reports -->
            <div id="profit" class="tab-content <?php echo $filter_type == 'profit' ? 'active' : ''; ?>">
                <div class="card">
                    <h2>Báo Cáo Lợi Nhuận 
                        <a href="reports.php?filter_type=profit&filter_month=<?php echo $filter_month; ?>&filter_year=<?php echo $filter_year; ?>&export=profit" class="btn btn-success btn-sm export-btn">Xuất Excel</a>
                        <a href="reports.php?filter_type=profit&filter_month=<?php echo $filter_month; ?>&filter_year=<?php echo $filter_year; ?>&generate=profit" class="btn btn-primary btn-sm generate-btn">Tạo Báo Cáo</a>
                    </h2>
                    <form method="GET" action="reports.php" class="mb-3">
                        <input type="hidden" name="filter_type" value="profit">
                        <div class="row">
                            <div class="col-md-6">
                                <label for="filter_month">Chọn Tháng</label>
                                <select id="filter_month" name="filter_month" onchange="this.form.submit()">
                                    <?php for ($i = 1; $i <= 12; $i++): ?>
                                        <option value="<?php echo $i; ?>" <?php echo $filter_month == $i ? 'selected' : ''; ?>><?php echo $i; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="filter_year">Chọn Năm</label>
                                <input type="number" id="filter_year" name="filter_year" value="<?php echo $filter_year; ?>" onchange="this.form.submit()">
                            </div>
                        </div>
                    </form>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Tháng</th>
                                <th>Năm</th>
                                <th>Doanh Thu</th>
                                <th>Chi Phí</th>
                                <th>Số Đơn Hàng</th>
                                <th>Lợi Nhuận</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($profit_reports_result->num_rows > 0): ?>
                                <?php while ($report = $profit_reports_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $report['report_id']; ?></td>
                                        <td><?php echo $report['month']; ?></td>
                                        <td><?php echo $report['year']; ?></td>
                                        <td><?php echo formatCurrency($report['total_revenue']); ?></td>
                                        <td><?php echo formatCurrency($report['total_cost']); ?></td>
                                        <td><?php echo $report['total_orders']; ?></td>
                                        <td><?php echo formatCurrency($report['net_profit']); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="7" class="text-center">Không có báo cáo lợi nhuận cho tháng/năm này</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <canvas id="profitChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.tablinks').forEach(btn => btn.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            document.querySelector(`button[onclick="showTab('${tabId}')"]`).classList.add('active');
        }

        // Profit Chart
        const profitCtx = document.getElementById('profitChart').getContext('2d');
        new Chart(profitCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_keys($yearly_profit_data)); ?>,
                datasets: [{
                    label: 'Lợi Nhuận Theo Năm (Tháng <?php echo $filter_month; ?>)',
                    data: <?php echo json_encode(array_values($yearly_profit_data)); ?>,
                    fill: false,
                    borderColor: 'rgb(75, 192, 192)',
                    tension: 0.1
                }]
            },
            options: {
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    </script>
</body>
</html>