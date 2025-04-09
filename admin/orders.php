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

// Set up filters
$date_filter = isset($_GET['date_filter']) ? $_GET['date_filter'] : date('Y-m-d');
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';
$shift_filter = isset($_GET['shift_filter']) ? $_GET['shift_filter'] : '';

// Build base query
$base_query = "SELECT o.*, t.table_name, u.username as staff_name, s.shift_name, p.payment_method, p.status as payment_status
              FROM Orders o 
              LEFT JOIN Tables t ON o.table_id = t.table_id 
              LEFT JOIN Users u ON o.user_id = u.user_id
              LEFT JOIN Shifts s ON o.shift_id = s.shift_id
              LEFT JOIN Payments p ON o.order_id = p.order_id 
              WHERE 1=1";

// Add filter conditions
$params = array();
$types = "";

// Filter by date if specified
if ($date_filter) {
    $base_query .= " AND DATE(o.order_time) = ?";
    $params[] = $date_filter;
    $types .= "s";
}

// Filter by status if specified
if ($status_filter) {
    $base_query .= " AND o.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

// Filter by shift if specified
if ($shift_filter) {
    $base_query .= " AND o.shift_id = ?";
    $params[] = $shift_filter;
    $types .= "i";
}

// Add order by
$base_query .= " ORDER BY o.order_time DESC";

// Prepare and execute query
$orders_stmt = $conn->prepare($base_query);

if (!empty($params)) {
    // Create dynamic bind_param arguments
    $bind_params = array($types);
    for ($i = 0; $i < count($params); $i++) {
        $bind_params[] = &$params[$i];
    }
    call_user_func_array(array($orders_stmt, 'bind_param'), $bind_params);
}

$orders_stmt->execute();
$orders_result = $orders_stmt->get_result();

// Get shifts for filter dropdown
$shifts_query = "SELECT * FROM Shifts ORDER BY shift_name";
$shifts_result = $conn->query($shifts_query);
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
        <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php 
                    echo $_SESSION['success']; 
                    unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <?php 
                    echo $_SESSION['error']; 
                    unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>
        
        <div class="dashboard">
            <div class="dashboard-header">
                <h1>Quản Lý Đơn Hàng</h1>
                <p>Xem và quản lý tất cả đơn hàng trong hệ thống</p>
            </div>
            
            <!-- Filter Options -->
            <div class="filter-options" style="margin-bottom: 20px;">
                <form method="GET" action="orders.php" class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="date_filter">Ngày:</label>
                            <input type="date" id="date_filter" name="date_filter" class="form-control" 
                                   value="<?php echo isset($_GET['date_filter']) ? $_GET['date_filter'] : date('Y-m-d'); ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="status_filter">Trạng thái:</label>
                            <select id="status_filter" name="status_filter" class="form-control">
                                <option value="">Tất cả</option>
                                <option value="Chờ chế biến" <?php echo isset($_GET['status_filter']) && $_GET['status_filter'] === 'Chờ chế biến' ? 'selected' : ''; ?>>Chờ chế biến</option>
                                <option value="Hoàn thành" <?php echo isset($_GET['status_filter']) && $_GET['status_filter'] === 'Hoàn thành' ? 'selected' : ''; ?>>Hoàn thành</option>
                                <option value="Đã thanh toán" <?php echo isset($_GET['status_filter']) && $_GET['status_filter'] === 'Đã thanh toán' ? 'selected' : ''; ?>>Đã thanh toán</option>
                                <option value="Đã hủy" <?php echo isset($_GET['status_filter']) && $_GET['status_filter'] === 'Đã hủy' ? 'selected' : ''; ?>>Đã hủy</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="shift_filter">Ca làm việc:</label>
                            <select id="shift_filter" name="shift_filter" class="form-control">
                                <option value="">Tất cả</option>
                                <?php while($shift = $shifts_result->fetch_assoc()): ?>
                                <option value="<?php echo $shift['shift_id']; ?>" <?php echo isset($_GET['shift_filter']) && $_GET['shift_filter'] == $shift['shift_id'] ? 'selected' : ''; ?>>
                                    <?php echo $shift['shift_name']; ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary form-control">Lọc</button>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- <div class="alert alert-info workflow-info">
                <strong>Quy trình xử lý đơn hàng:</strong>
                <ul>
                    <li><span class="status-badge status-chờ-chế-biến">Chờ chế biến</span> - Đơn hàng mới được tạo sau khi khách đã thanh toán, đang chờ pha chế</li>
                    <li><span class="status-badge status-hoàn-thành">Hoàn thành</span> - Đơn hàng đã được pha chế xong, sẵn sàng giao cho khách</li>
                    <li><span class="status-badge status-đã-thanh-toán">Đã thanh toán</span> - Đơn hàng đã được tạo và thanh toán (xử lý qua POS)</li>
                    <li><span class="status-badge status-đã-hủy">Đã hủy</span> - Đơn hàng đã bị hủy</li>
                </ul>
            </div> -->
            
            <div class="card">
                <h2>Danh Sách Đơn Hàng</h2>
                
                <table class="table">
                    <thead>
                        <tr>
                            <th>Mã Đơn</th>
                            <th>Bàn</th>
                            <th>Nhân viên</th>
                            <th>Ca</th>
                            <th>Thời Gian</th>
                            <th>Tổng Tiền</th>
                            <th>Thanh Toán</th>
                            <th>Trạng Thái</th>
                            <th>Ghi Chú</th>
                            <th>Thao Tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($orders_result->num_rows > 0): ?>
                            <?php while ($order = $orders_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $order['order_id']; ?></td>
                                    <td><?php echo $order['table_name']; ?></td>
                                    <td><?php echo $order['staff_name']; ?></td>
                                    <td><?php echo $order['shift_name']; ?></td>
                                    <td><?php echo date('H:i:s d/m/Y', strtotime($order['order_time'])); ?></td>
                                    <td><?php echo formatCurrency($order['total_price']); ?></td>
                                    <td><?php echo !empty($order['payment_method']) ? $order['payment_method'] : '<span class="text-muted">Chưa có</span>'; ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $order['status'])); ?>">
                                            <?php echo $order['status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($order['notes'])): ?>
                                            <button type="button" class="btn btn-info btn-sm" onclick="alert('<?php echo addslashes(htmlspecialchars($order['notes'])); ?>')">Xem</button>
                                        <?php else: ?>
                                            <span class="text-muted">Không có</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="order_details.php?order_id=<?php echo $order['order_id']; ?>" class="btn btn-secondary btn-sm">Chi tiết</a>
                                        <!-- <a href="../staff/receipt.php?order_id=<?php echo $order['order_id']; ?>" class="btn btn-info btn-sm">Hóa đơn</a> -->
                                        <button onclick="confirmVoidBill(<?php echo $order['order_id']; ?>)" class="btn btn-danger btn-sm">Void Bill</button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" class="text-center">Không có đơn hàng nào</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script>
        function confirmVoidBill(orderId) {
            if (confirm('Bạn có chắc chắn muốn xóa đơn hàng này? Hành động này không thể hoàn tác.')) {
                window.location.href = 'void_bill.php?order_id=' + orderId;
            }
        }
    </script>
</body>
</html> 