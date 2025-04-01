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

// Set up filters
$date_filter = isset($_GET['date_filter']) ? $_GET['date_filter'] : date('Y-m-d');
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';

// Build base query
$base_query = "SELECT o.*, t.table_name, p.payment_method, p.status as payment_status 
              FROM Orders o 
              LEFT JOIN Tables t ON o.table_id = t.table_id 
              LEFT JOIN Payments p ON o.order_id = p.order_id WHERE 1=1";

// Add filter conditions
$params = array();
$types = "";

// Always filter by date unless explicitly showing all
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

// Filter by shift if current shift is valid and no filters are applied
if ($current_shift && !isset($_GET['date_filter']) && !isset($_GET['status_filter'])) {
    $base_query .= " AND o.shift_id = ?";
    $params[] = $current_shift;
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
            // Không cần cập nhật trạng thái bàn nữa vì đã bỏ cột status
            $success = "Đã cập nhật trạng thái đơn hàng thành '$new_status'";
            
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
            <a href="pos.php" class="navbar-brand">Quản Lý Quán Cà Phê</a>
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
                <h1>Đơn Hàng</h1>
                <?php if (!$current_shift): ?>
                <div class="alert alert-info">Hiển thị tất cả đơn hàng trong ngày hôm nay</div>
                <?php else: ?>
                <div class="alert alert-info">Hiển thị đơn hàng trong ca hiện tại</div>
                <?php endif; ?>
            </div>
            
            <!-- Filter Options -->
            <div class="filter-options" style="margin-bottom: 20px;">
                <form method="GET" action="orders.php" class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="date_filter">Ngày:</label>
                            <input type="date" id="date_filter" name="date_filter" class="form-control" 
                                   value="<?php echo isset($_GET['date_filter']) ? $_GET['date_filter'] : date('Y-m-d'); ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
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
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary form-control">Lọc</button>
                        </div>
                    </div>
                </form>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="alert alert-info workflow-info">
                <strong>Quy trình xử lý đơn hàng:</strong>
                <ul>
                    <li><span class="status-badge status-chờ-chế-biến">Chờ chế biến</span> - Đơn hàng mới được tạo sau khi khách đã thanh toán, đang chờ pha chế</li>
                    <li><span class="status-badge status-hoàn-thành">Hoàn thành</span> - Đơn hàng đã được pha chế xong, sẵn sàng giao cho khách</li>
                    <li><span class="status-badge status-đã-thanh-toán">Đã thanh toán</span> - Đơn hàng đã được tạo và thanh toán (xử lý qua POS)</li>
                    <li><span class="status-badge status-đã-hủy">Đã hủy</span> - Đơn hàng đã bị hủy</li>
                </ul>
            </div>
            
            <div class="card">
                <h2>Quản Lý Đơn Hàng & Pha Chế</h2>
                
                <table class="table">
                    <thead>
                        <tr>
                            <th>Mã Đơn</th>
                            <th>Bàn</th>
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
                                    <td><?php echo date('H:i:s d/m/Y', strtotime($order['order_time'])); ?></td>
                                    <td><?php echo formatCurrency($order['total_price']); ?></td>
                                    <td><?php echo $order['payment_method']; ?></td>
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
                                        <a href="receipt.php?order_id=<?php echo $order['order_id']; ?>" class="btn btn-secondary btn-sm">Xem</a>
                                        
                                        <?php if ($order['status'] === 'Chờ chế biến'): ?>
                                            <form method="POST" action="orders.php" style="display: inline;">
                                                <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                                <input type="hidden" name="table_id" value="<?php echo $order['table_id']; ?>">
                                                <input type="hidden" name="new_status" value="Hoàn thành">
                                                <button type="submit" name="update_status" class="btn btn-success btn-sm">Hoàn thành</button>
                                            </form>
                                            
                                            <form method="POST" action="orders.php" style="display: inline;">
                                                <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                                <input type="hidden" name="table_id" value="<?php echo $order['table_id']; ?>">
                                                <input type="hidden" name="new_status" value="Đã hủy">
                                                <button type="submit" name="update_status" class="btn btn-danger btn-sm">Hủy</button>
                                            </form>
                                        <?php elseif ($order['status'] === 'Chờ thanh toán'): ?>
                                            <form method="POST" action="orders.php" style="display: inline;">
                                                <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                                <input type="hidden" name="table_id" value="<?php echo $order['table_id']; ?>">
                                                <input type="hidden" name="new_status" value="Đã hủy">
                                                <button type="submit" name="update_status" class="btn btn-danger btn-sm">Hủy</button>
                                            </form>
                                        <?php elseif ($order['status'] === 'Hoàn thành'): ?>
                                            <!-- Đã loại bỏ nút thanh toán vì quán áp dụng quy trình pay to earn -->
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