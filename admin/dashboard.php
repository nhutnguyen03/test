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

// Get today's date
$today = date('Y-m-d');

// Get today's revenue
$today_revenue_query = "SELECT SUM(total_price) as total FROM Orders WHERE DATE(order_time) = ? AND status IN ('Đã thanh toán', 'Hoàn thành')";
$today_revenue_stmt = $conn->prepare($today_revenue_query);
$today_revenue_stmt->bind_param("s", $today);
$today_revenue_stmt->execute();
$today_revenue_result = $today_revenue_stmt->get_result();
$today_revenue = $today_revenue_result->fetch_assoc()['total'] ?? 0;

// Get today's orders count
$today_orders_query = "SELECT COUNT(*) as count FROM Orders WHERE DATE(order_time) = ? AND status IN ('Đã thanh toán', 'Hoàn thành')";
$today_orders_stmt = $conn->prepare($today_orders_query);
$today_orders_stmt->bind_param("s", $today);
$today_orders_stmt->execute();
$today_orders_result = $today_orders_stmt->get_result();
$today_orders = $today_orders_result->fetch_assoc()['count'] ?? 0;

// Get current month's revenue
$current_month = date('m');
$current_year = date('Y');
$month_revenue_query = "SELECT SUM(total_price) as total FROM Orders WHERE MONTH(order_time) = ? AND YEAR(order_time) = ? AND status IN ('Đã thanh toán', 'Hoàn thành')";
$month_revenue_stmt = $conn->prepare($month_revenue_query);
$month_revenue_stmt->bind_param("ss", $current_month, $current_year);
$month_revenue_stmt->execute();
$month_revenue_result = $month_revenue_stmt->get_result();
$month_revenue = $month_revenue_result->fetch_assoc()['total'] ?? 0;

// Get recent orders
$recent_orders_query = "SELECT o.*, t.table_name, p.payment_method 
                       FROM Orders o 
                       LEFT JOIN Tables t ON o.table_id = t.table_id 
                       LEFT JOIN Payments p ON o.order_id = p.order_id 
                       ORDER BY o.order_time DESC LIMIT 10";
$recent_orders_result = $conn->query($recent_orders_query);

// Get low stock materials
$low_stock_query = "SELECT m.*, s.supplier_name 
                   FROM Materials m 
                   JOIN Suppliers s ON m.supplier_id = s.supplier_id 
                   WHERE m.stock_quantity < m.low_stock_threshold 
                   ORDER BY m.stock_quantity ASC";
$low_stock_result = $conn->query($low_stock_query);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bảng Điều Khiển - Hệ Thống Quản Lý Quán Cà Phê</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <!-- Navigation -->
    <?php include 'navbar.php'; ?>
    
    <!-- Main Content -->
    <div class="container">
        <div class="dashboard">
            <div class="dashboard-header">
                <h1>Bảng Điều Khiển</h1>
                <p>Xin chào, <?php echo $_SESSION['username']; ?>!</p>
            </div>
            
            <div class="dashboard-cards">
                <div class="card">
                    <div class="card-title">Doanh Thu Hôm Nay</div>
                    <div class="card-value"><?php echo formatCurrency($today_revenue); ?></div>
                </div>
                
                <div class="card">
                    <div class="card-title">Đơn Hàng Hôm Nay</div>
                    <div class="card-value"><?php echo $today_orders; ?></div>
                </div>
                
                <div class="card">
                    <div class="card-title">Doanh Thu Tháng Này</div>
                    <div class="card-value"><?php echo formatCurrency($month_revenue); ?></div>
                </div>
                
                <div class="card">
                    <div class="card-title">Ngày</div>
                    <div class="card-value"><?php echo date('d/m/Y'); ?></div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <h2>Đơn Hàng Gần Đây</h2>
                        
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Mã Đơn</th>
                                    <th>Bàn</th>
                                    <th>Thời Gian</th>
                                    <th>Tổng Tiền</th>
                                    <th>Trạng Thái</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($recent_orders_result->num_rows > 0): ?>
                                    <?php while ($order = $recent_orders_result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $order['order_id']; ?></td>
                                            <td><?php echo $order['table_name']; ?></td>
                                            <td><?php echo date('H:i d/m/Y', strtotime($order['order_time'])); ?></td>
                                            <td><?php echo formatCurrency($order['total_price']); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $order['status'])); ?>">
                                                    <?php echo $order['status']; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center">Không có đơn hàng nào</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        
                        <div class="text-center mt-3">
                            <a href="orders.php" class="btn btn-secondary">Xem Tất Cả Đơn Hàng</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card">
                        <h2>Nguyên Liệu Sắp Hết</h2>
                        
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Tên Nguyên Liệu</th>
                                    <th>Nhà Cung Cấp</th>
                                    <th>Số Lượng</th>
                                    <th>Đơn Vị</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($low_stock_result->num_rows > 0): ?>
                                    <?php while ($material = $low_stock_result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $material['material_name']; ?></td>
                                            <td><?php echo $material['supplier_name']; ?></td>
                                            <td><?php echo $material['stock_quantity']; ?></td>
                                            <td><?php echo $material['unit']; ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center">Không có nguyên liệu nào sắp hết</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        
                        <div class="text-center mt-3">
                            <a href="inventory.php" class="btn btn-secondary">Quản Lý Kho Hàng</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>