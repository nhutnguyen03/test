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

// Filter parameters
$filter_type = isset($_GET['filter_type']) ? sanitize($_GET['filter_type']) : 'shift';
$filter_date = isset($_GET['filter_date']) ? sanitize($_GET['filter_date']) : date('Y-m-d');
$filter_month = isset($_GET['filter_month']) ? (int)$_GET['filter_month'] : date('m');
$filter_year = isset($_GET['filter_year']) ? (int)$_GET['filter_year'] : date('Y');

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

// Revenue Reports by Day
$daily_revenue_query = "SELECT DATE(order_time) AS report_day, SUM(total_price) AS daily_revenue, COUNT(order_id) AS daily_orders 
                        FROM Orders 
                        WHERE DATE(order_time) = ? AND status = 'Đã thanh toán'
                        GROUP BY DATE(order_time)";
$daily_revenue_stmt = $conn->prepare($daily_revenue_query);
$daily_revenue_stmt->bind_param("s", $filter_date);
$daily_revenue_stmt->execute();
$daily_revenue_result = $daily_revenue_stmt->get_result();

// Revenue Reports by Month/Year
$monthly_revenue_query = "SELECT MONTH(order_time) AS report_month, YEAR(order_time) AS report_year, 
                          SUM(total_price) AS monthly_revenue, COUNT(order_id) AS monthly_orders 
                          FROM Orders 
                          WHERE MONTH(order_time) = ? AND YEAR(order_time) = ? AND status = 'Đã thanh toán'
                          GROUP BY MONTH(order_time), YEAR(order_time)";
$monthly_revenue_stmt = $conn->prepare($monthly_revenue_query);
$monthly_revenue_stmt->bind_param("ii", $filter_month, $filter_year);
$monthly_revenue_stmt->execute();
$monthly_revenue_result = $monthly_revenue_stmt->get_result();

$yearly_revenue_query = "SELECT YEAR(order_time) AS report_year, SUM(total_price) AS yearly_revenue, COUNT(order_id) AS yearly_orders 
                         FROM Orders 
                         WHERE YEAR(order_time) = ? AND status = 'Đã thanh toán'
                         GROUP BY YEAR(order_time)";
$yearly_revenue_stmt = $conn->prepare($yearly_revenue_query);
$yearly_revenue_stmt->bind_param("i", $filter_year);
$yearly_revenue_stmt->execute();
$yearly_revenue_result = $yearly_revenue_stmt->get_result();

// Profit Reports by Month/Year
$profit_reports_query = "SELECT * FROM Profit_Reports 
                         WHERE month = ? AND year = ?";
$profit_reports_stmt = $conn->prepare($profit_reports_query);
$profit_reports_stmt->bind_param("ii", $filter_month, $filter_year);
$profit_reports_stmt->execute();
$profit_reports_result = $profit_reports_stmt->get_result();

// Data for Charts
$monthly_revenue_data = [];
for ($i = 1; $i <= 12; $i++) {
    $query = "SELECT SUM(total_price) AS revenue 
              FROM Orders 
              WHERE MONTH(order_time) = $i AND YEAR(order_time) = $filter_year AND status = 'Đã thanh toán'";
    $result = $conn->query($query);
    $row = $result->fetch_assoc();
    $monthly_revenue_data[] = $row && $row['revenue'] !== null ? (float)$row['revenue'] : 0;
}

$yearly_profit_data = [];
for ($i = $filter_year - 4; $i <= $filter_year; $i++) {
    $query = "SELECT net_profit FROM Profit_Reports WHERE year = $i AND month = $filter_month";
    $result = $conn->query($query);
    $row = $result->fetch_assoc();
    $yearly_profit_data[$i] = $row && $row['net_profit'] !== null ? (float)$row['net_profit'] : 0;
}

// Auto-generate reports from Orders
if (isset($_GET['generate']) && $_GET['generate'] == 'shift') {
    $shift_query = "SELECT shift_id, SUM(total_price) AS total_revenue, COUNT(order_id) AS total_orders, DATE(order_time) AS report_date 
                    FROM Orders 
                    WHERE DATE(order_time) = ? AND status = 'Đã thanh toán'
                    GROUP BY shift_id, DATE(order_time)";
    $shift_stmt = $conn->prepare($shift_query);
    $shift_stmt->bind_param("s", $filter_date);
    $shift_stmt->execute();
    $shift_result = $shift_stmt->get_result();
    
    while ($row = $shift_result->fetch_assoc()) {
        $check_query = "SELECT COUNT(*) FROM Revenue_Reports WHERE shift_id = ? AND report_date = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("is", $row['shift_id'], $row['report_date']);
        $check_stmt->execute();
        $exists = $check_stmt->get_result()->fetch_row()[0];
        
        if ($exists == 0) {
            $insert_query = "INSERT INTO Revenue_Reports (shift_id, total_revenue, total_orders, report_date) 
                            VALUES (?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bind_param("idis", $row['shift_id'], $row['total_revenue'], $row['total_orders'], $row['report_date']);
            $insert_stmt->execute();
        }
    }
    header("Location: reports.php?filter_type=shift&filter_date=$filter_date");
    exit;
}

// Export to Excel
if (isset($_GET['export'])) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $_GET['export'] . '_report_' . date('Ymd') . '.xls"');
    
    if ($_GET['export'] == 'shift') {
        echo "ID\tCa Làm Việc\tDoanh Thu\tSố Đơn Hàng\tNgày Báo Cáo\n";
        $export_query = "SELECT rr.report_id, s.shift_name, rr.total_revenue, rr.total_orders, rr.report_date 
                         FROM Revenue_Reports rr JOIN Shifts s ON rr.shift_id = s.shift_id 
                         WHERE DATE(rr.report_date) = '$filter_date'";
        $export_result = $conn->query($export_query);
        while ($row = $export_result->fetch_assoc()) {
            echo implode("\t", [$row['report_id'], $row['shift_name'], $row['total_revenue'], $row['total_orders'], date('d/m/Y', strtotime($row['report_date']))]) . "\n";
        }
    } elseif ($_GET['export'] == 'daily') {
        echo "Ngày\tDoanh Thu\tSố Đơn Hàng\n";
        $export_query = "SELECT DATE(order_time) AS report_day, SUM(total_price) AS daily_revenue, COUNT(order_id) AS daily_orders 
                         FROM Orders WHERE DATE(order_time) = '$filter_date' AND status = 'Đã thanh toán' GROUP BY DATE(order_time)";
        $export_result = $conn->query($export_query);
        while ($row = $export_result->fetch_assoc()) {
            echo implode("\t", [date('d/m/Y', strtotime($row['report_day'])), $row['daily_revenue'], $row['daily_orders']]) . "\n";
        }
    } elseif ($_GET['export'] == 'monthly') {
        echo "Tháng\tNăm\tDoanh Thu\tSố Đơn Hàng\n";
        $export_query = "SELECT MONTH(order_time) AS report_month, YEAR(order_time) AS report_year, SUM(total_price) AS monthly_revenue, COUNT(order_id) AS monthly_orders 
                         FROM Orders WHERE MONTH(order_time) = $filter_month AND YEAR(order_time) = $filter_year AND status = 'Đã thanh toán' GROUP BY MONTH(order_time), YEAR(order_time)";
        $export_result = $conn->query($export_query);
        while ($row = $export_result->fetch_assoc()) {
            echo implode("\t", [$row['report_month'], $row['report_year'], $row['monthly_revenue'], $row['monthly_orders']]) . "\n";
        }
    } elseif ($_GET['export'] == 'yearly') {
        echo "Năm\tDoanh Thu\tSố Đơn Hàng\n";
        $export_query = "SELECT YEAR(order_time) AS report_year, SUM(total_price) AS yearly_revenue, COUNT(order_id) AS yearly_orders 
                         FROM Orders WHERE YEAR(order_time) = $filter_year AND status = 'Đã thanh toán' GROUP BY YEAR(order_time)";
        $export_result = $conn->query($export_query);
        while ($row = $export_result->fetch_assoc()) {
            echo implode("\t", [$row['report_year'], $row['yearly_revenue'], $row['yearly_orders']]) . "\n";
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
        <div class="dashboard">
            <div class="dashboard-header">
                <h1>Báo Cáo</h1>
            </div>
            
            <div class="tabs">
                <button class="tablinks <?php echo $filter_type == 'shift' ? 'active' : ''; ?>" onclick="showTab('shift')">Theo Ca</button>
                <button class="tablinks <?php echo $filter_type == 'daily' ? 'active' : ''; ?>" onclick="showTab('daily')">Theo Ngày</button>
                <button class="tablinks <?php echo $filter_type == 'monthly' ? 'active' : ''; ?>" onclick="showTab('monthly')">Theo Tháng</button>
                <button class="tablinks <?php echo $filter_type == 'yearly' ? 'active' : ''; ?>" onclick="showTab('yearly')">Theo Năm</button>
                <button class="tablinks <?php echo $filter_type == 'profit' ? 'active' : ''; ?>" onclick="showTab('profit')">Lợi Nhuận</button>
            </div>

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
                                <th>Số Đơn Hàng</th>
                                <th>Ngày Báo Cáo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($shift_reports_result->num_rows > 0): ?>
                                <?php while ($report = $shift_reports_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $report['report_id']; ?></td>
                                        <td><?php echo $report['shift_name']; ?></td>
                                        <td><?php echo formatCurrency($report['total_revenue']); ?></td>
                                        <td><?php echo $report['total_orders']; ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($report['report_date'])); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="text-center">Không có báo cáo cho ngày này</td></tr>
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
                                <th>Số Đơn Hàng</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($daily_revenue_result->num_rows > 0): ?>
                                <?php while ($report = $daily_revenue_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y', strtotime($report['report_day'])); ?></td>
                                        <td><?php echo formatCurrency($report['daily_revenue']); ?></td>
                                        <td><?php echo $report['daily_orders']; ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="3" class="text-center">Không có báo cáo cho ngày này</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Monthly Reports -->
            <div id="monthly" class="tab-content <?php echo $filter_type == 'monthly' ? 'active' : ''; ?>">
                <div class="card">
                    <h2>Báo Cáo Theo Tháng 
                        <a href="reports.php?filter_type=monthly&filter_month=<?php echo $filter_month; ?>&filter_year=<?php echo $filter_year; ?>&export=monthly" class="btn btn-success btn-sm export-btn">Xuất Excel</a>
                    </h2>
                    <form method="GET" action="reports.php" class="mb-3">
                        <input type="hidden" name="filter_type" value="monthly">
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
                                <th>Tháng</th>
                                <th>Năm</th>
                                <th>Doanh Thu</th>
                                <th>Số Đơn Hàng</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($monthly_revenue_result->num_rows > 0): ?>
                                <?php while ($report = $monthly_revenue_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $report['report_month']; ?></td>
                                        <td><?php echo $report['report_year']; ?></td>
                                        <td><?php echo formatCurrency($report['monthly_revenue']); ?></td>
                                        <td><?php echo $report['monthly_orders']; ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="text-center">Không có báo cáo cho tháng này</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <canvas id="monthlyRevenueChart"></canvas>
                </div>
            </div>

            <!-- Yearly Reports -->
            <div id="yearly" class="tab-content <?php echo $filter_type == 'yearly' ? 'active' : ''; ?>">
                <div class="card">
                    <h2>Báo Cáo Theo Năm 
                        <a href="reports.php?filter_type=yearly&filter_year=<?php echo $filter_year; ?>&export=yearly" class="btn btn-success btn-sm export-btn">Xuất Excel</a>
                    </h2>
                    <form method="GET" action="reports.php" class="mb-3">
                        <input type="hidden" name="filter_type" value="yearly">
                        <div class="row">
                            <div class="col-md-6">
                                <label for="filter_year">Chọn Năm</label>
                                <input type="number" id="filter_year" name="filter_year" value="<?php echo $filter_year; ?>" onchange="this.form.submit()">
                            </div>
                        </div>
                    </form>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Năm</th>
                                <th>Doanh Thu</th>
                                <th>Số Đơn Hàng</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($yearly_revenue_result->num_rows > 0): ?>
                                <?php while ($report = $yearly_revenue_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $report['report_year']; ?></td>
                                        <td><?php echo formatCurrency($report['yearly_revenue']); ?></td>
                                        <td><?php echo $report['yearly_orders']; ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="3" class="text-center">Không có báo cáo cho năm này</td></tr>
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

        // Monthly Revenue Chart
        const monthlyRevenueCtx = document.getElementById('monthlyRevenueChart').getContext('2d');
        new Chart(monthlyRevenueCtx, {
            type: 'bar',
            data: {
                labels: ['Th1', 'Th2', 'Th3', 'Th4', 'Th5', 'Th6', 'Th7', 'Th8', 'Th9', 'Th10', 'Th11', 'Th12'],
                datasets: [{
                    label: 'Doanh Thu Theo Tháng (<?php echo $filter_year; ?>)',
                    data: <?php echo json_encode($monthly_revenue_data); ?>,
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });

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