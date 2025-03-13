<?php
session_start();
require_once '../config/db.php';
require_once '../config/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

// Check if user is staff
if ($_SESSION['role'] !== 'Ca sáng' && $_SESSION['role'] !== 'Ca chiều') {
    header("Location: ../index.php");
    exit();
}

// Get current shift
$current_shift = getCurrentShift($conn);

// Get orders for current shift
$orders_query = "SELECT o.*, t.table_name, p.payment_method, p.status as payment_status 
                FROM Orders o 
                LEFT JOIN Tables t ON o.table_id = t.table_id 
                LEFT JOIN Payments p ON o.order_id = p.order_id 
                WHERE o.shift_id = ? 
                ORDER BY o.order_time DESC";
$orders_stmt = $conn->prepare($orders_query);
$orders_stmt->bind_param("i", $current_shift);
$orders_stmt->execute();
$orders_result = $orders_stmt->get_result();

// Process order status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id = $_POST['order_id'] ?? 0;
    $new_status = $_POST['new_status'] ?? '';
    $table_id = $_POST['table_id'] ?? '';
    
    if ($order_id && $new_status) {
        // Update order status
        $update_query = "UPDATE Orders SET status = ? WHERE order_id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("si", $new_status, $order_id);
        
        if ($update_stmt->execute()) {
            // If order is completed or canceled, update table status
            if ($new_status === 'Đã thanh toán' || $new_status === 'Đã hủy') {
                $table_query = "UPDATE Tables SET status = 'Trống' WHERE table_id = ?";
                $table_stmt = $conn->prepare($table_query);
                $table_stmt->bind_param("s", $table_id);
                $table_stmt->execute();
            }
            
            // Redirect to refresh the page
            header("Location: orders.php");
            exit();
        } else {
            $error = "Lỗi khi cập nhật trạng thái đơn hàng";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Đơn Hàng - Hệ Thống Quản Lý Quán Cà Phê</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="container">
            <a href="index.php" class="navbar-brand">Quản Lý Quán Cà Phê</a>
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a href="pos.php" class="nav-link">Bán Hàng</a>
                </li>
                <li class="nav-item">
                    <a href="orders.php" class="nav-link">Đơn Hàng</a>
                </li>
                <li class="nav-item">
                    <a href="../logout.php" class="nav-link">Đăng Xuất</a>
                </li>
            </ul>
        </div>
    </nav>
    
    <!-- Main Content -->
    <div class="container">
        <div class="dashboard">
            <div class="dashboard-header">
                <h1>Quản Lý Đơn Hàng</h1>
                <p>Nhân viên: <?php echo $_SESSION['username']; ?> | Ca: <?php echo $_SESSION['role']; ?></p>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="card">
                <h2>Đơn Hàng Trong Ca Hiện Tại</h2>
                
                <table class="table">
                    <thead>
                        <tr>
                            <th>Mã Đơn</th>
                            <th>Bàn</th>
                            <th>Thời Gian</th>
                            <th>Tổng Tiền</th>
                            <th>Thanh Toán</th>
                            <th>Trạng Thái</th>
                            <th>Thao Tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($orders_result->num_rows > 0): ?>
                            <?php while ($order = $orders_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $order['order_id']; ?></td>
                                    <td><?php echo $order['table_name']; ?></td>
                                    <td><?php echo date('H:i:s d/m/Y', strtotime($order['order_time'])); ?></td>
                                    <td><?php echo formatCurrency($order['total_price']); ?></td>
                                    <td><?php echo $order['payment_method']; ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $order['status'])); ?>">
                                            <?php echo $order['status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="receipt.php?order_id=<?php echo $order['order_id']; ?>" class="btn btn-secondary btn-sm">Xem</a>
                                        
                                        <?php if ($order['status'] === 'Chờ thanh toán'): ?>
                                            <form method="POST" action="orders.php" style="display: inline;">
                                                <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                                <input type="hidden" name="table_id" value="<?php echo $order['table_id']; ?>">
                                                <input type="hidden" name="new_status" value="Đã thanh toán">
                                                <button type="submit" name="update_status" class="btn btn-primary btn-sm">Thanh Toán</button>
                                            </form>
                                            
                                            <form method="POST" action="orders.php" style="display: inline;">
                                                <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                                <input type="hidden" name="table_id" value="<?php echo $order['table_id']; ?>">
                                                <input type="hidden" name="new_status" value="Đã hủy">
                                                <button type="submit" name="update_status" class="btn btn-danger btn-sm">Hủy</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center">Không có đơn hàng nào</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>