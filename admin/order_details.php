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

// Check if order ID is provided
if (!isset($_GET['order_id']) || empty($_GET['order_id'])) {
    header("Location: orders.php");
    exit();
}

$order_id = $_GET['order_id'];

// Get order details
$order_query = "SELECT o.*, t.table_name, u.username as staff_name, s.shift_name, 
                p.payment_method, p.transaction_code, p.status as payment_status, 
                pr.discount_code, pr.discount_value
               FROM Orders o 
               LEFT JOIN Tables t ON o.table_id = t.table_id 
               LEFT JOIN Users u ON o.user_id = u.user_id
               LEFT JOIN Shifts s ON o.shift_id = s.shift_id
               LEFT JOIN Payments p ON o.order_id = p.order_id 
               LEFT JOIN Promotions pr ON o.promo_id = pr.promo_id
               WHERE o.order_id = ?";
$order_stmt = $conn->prepare($order_query);
$order_stmt->bind_param("i", $order_id);
$order_stmt->execute();
$order_result = $order_stmt->get_result();

if ($order_result->num_rows == 0) {
    header("Location: orders.php");
    exit();
}

$order = $order_result->fetch_assoc();

// Get order items
$items_query = "SELECT od.*, p.product_name, p.size 
               FROM Order_Details od
               JOIN Products p ON od.product_id = p.product_id
               WHERE od.order_id = ?";
$items_stmt = $conn->prepare($items_query);
$items_stmt->bind_param("i", $order_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chi Tiết Đơn Hàng #<?php echo $order_id; ?> - Hệ Thống Quản Lý Quán Cà Phê</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .order-info {
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .table-info {
            width: 100%;
            margin-bottom: 20px;
            border-collapse: separate;
            border-spacing: 0;
        }
        
        .table-info th, .table-info td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .table-info th {
            width: 40%;
            color: #555;
            font-weight: 600;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 30px;
            display: inline-block;
            font-size: 14px;
            font-weight: 500;
            color: white;
        }
        
        .status-hoàn-thành {
            background-color: #4CAF50;
        }
        
        .status-chờ-chế-biến {
            background-color: #2196F3;
        }
        
        .status-đã-thanh-toán {
            background-color: #9C27B0;
        }
        
        .status-đã-hủy {
            background-color: #F44336;
        }
        
        .order-notes {
            margin-top: 20px;
            padding: 15px;
            background-color: #fff;
            border-left: 4px solid #4CAF50;
            border-radius: 4px;
        }
        
        .note-content {
            margin-top: 10px;
            padding: 10px;
            background-color: #f5f5f5;
            border-radius: 4px;
            color: #333;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            border-radius: 4px;
            overflow: hidden;
        }
        
        .table thead {
            background-color: #f2f2f2;
        }
        
        .table th, .table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .table tbody tr:hover {
            background-color: #f9f9f9;
        }
        
        .table tfoot {
            background-color: #f9f9f9;
            font-weight: 500;
        }
        
        .table tfoot th {
            text-align: right;
        }
        
        .text-right {
            text-align: right !important;
        }
        
        .action-buttons {
            margin-top: 20px;
            display: flex;
            gap: 10px;
        }
        
        .mt-4 {
            margin-top: 1.5rem;
        }
        
        .row {
            display: flex;
            flex-wrap: wrap;
            margin-right: -15px;
            margin-left: -15px;
        }
        
        .col-md-6 {
            flex: 0 0 50%;
            max-width: 50%;
            padding-right: 15px;
            padding-left: 15px;
            box-sizing: border-box;
        }
        
        @media (max-width: 768px) {
            .row {
                flex-direction: column;
            }
            .col-md-6 {
                flex: 0 0 100%;
                max-width: 100%;
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
                <li class="nav-item">
                    <a href="products.php" class="nav-link">Sản Phẩm</a>
                </li>
                <li class="nav-item">
                    <a href="categories.php" class="nav-link active">Danh Mục</a>
                </li>
                <li class="nav-item">
                    <a href="inventory.php" class="nav-link">Kho Hàng</a>
                </li>
                <li class="nav-item">
                    <a href="promotions.php" class="nav-link">Khuyến Mãi</a>
                </li>
                <li class="nav-item">
                    <a href="reports.php" class="nav-link">Báo Cáo</a>
                </li>
                <li class="nav-item">
                    <a href="users.php" class="nav-link">Nhân Viên</a>
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
                <h1>Chi Tiết Đơn Hàng #<?php echo $order_id; ?></h1>
                <a href="orders.php" class="btn btn-secondary">Trở về danh sách đơn hàng</a>
            </div>
            
            <div class="card">
                <h2>Thông Tin Đơn Hàng</h2>
                
                <div class="order-info">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table-info">
                                <tr>
                                    <th>Mã đơn hàng:</th>
                                    <td>#<?php echo $order['order_id']; ?></td>
                                </tr>
                                <tr>
                                    <th>Thời gian:</th>
                                    <td><?php echo date('H:i:s d/m/Y', strtotime($order['order_time'])); ?></td>
                                </tr>
                                <tr>
                                    <th>Trạng thái:</th>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $order['status'])); ?>">
                                            <?php echo $order['status']; ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Bàn:</th>
                                    <td><?php echo $order['table_name']; ?></td>
                                </tr>
                                <tr>
                                    <th>Nhân viên:</th>
                                    <td><?php echo $order['staff_name']; ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table-info">
                                <tr>
                                    <th>Ca làm việc:</th>
                                    <td><?php echo $order['shift_name']; ?></td>
                                </tr>
                                <tr>
                                    <th>Phương thức thanh toán:</th>
                                    <td><?php echo $order['payment_method']; ?></td>
                                </tr>
                                <?php if ($order['payment_method'] !== 'Tiền mặt' && !empty($order['transaction_code'])): ?>
                                <tr>
                                    <th>Mã giao dịch:</th>
                                    <td><?php echo $order['transaction_code']; ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if (!empty($order['discount_code'])): ?>
                                <tr>
                                    <th>Mã khuyến mãi:</th>
                                    <td><?php echo $order['discount_code']; ?></td>
                                </tr>
                                <tr>
                                    <th>Giảm giá:</th>
                                    <td><?php echo formatCurrency($order['discount_value']); ?></td>
                                </tr>
                                <?php endif; ?>
                                <tr>
                                    <th>Tổng tiền:</th>
                                    <td><strong><?php echo formatCurrency($order['total_price']); ?></strong></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <?php if (!empty($order['notes'])): ?>
                    <div class="order-notes">
                        <h3>Ghi chú đơn hàng</h3>
                        <div class="note-content"><?php echo nl2br(htmlspecialchars($order['notes'])); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card mt-4">
                <h2>Các Sản Phẩm Trong Đơn Hàng</h2>
                
                <table class="table">
                    <thead>
                        <tr>
                            <th>Sản phẩm</th>
                            <th>Kích thước</th>
                            <th>Đơn giá</th>
                            <th>Số lượng</th>
                            <th>Thành tiền</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $subtotal = 0;
                        while ($item = $items_result->fetch_assoc()): 
                            $item_total = $item['quantity'] * $item['price'];
                            $subtotal += $item_total;
                        ?>
                            <tr>
                                <td><?php echo $item['product_name']; ?></td>
                                <td><?php echo $item['size']; ?></td>
                                <td><?php echo formatCurrency($item['price']); ?></td>
                                <td><?php echo $item['quantity']; ?></td>
                                <td><?php echo formatCurrency($item_total); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="4" class="text-right">Tổng cộng:</th>
                            <td><?php echo formatCurrency($subtotal); ?></td>
                        </tr>
                        <?php if (!empty($order['discount_value'])): ?>
                        <tr>
                            <th colspan="4" class="text-right">Giảm giá:</th>
                            <td>-<?php echo formatCurrency($order['discount_value']); ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <th colspan="4" class="text-right">Thành tiền:</th>
                            <td><strong><?php echo formatCurrency($order['total_price']); ?></strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            <div class="action-buttons mt-4">
                <a href="../staff/receipt.php?order_id=<?php echo $order_id; ?>" class="btn btn-primary">Xem Hóa Đơn</a>
                <a href="orders.php" class="btn btn-secondary">Trở Về</a>
            </div>
        </div>
    </div>
</body>
</html> 