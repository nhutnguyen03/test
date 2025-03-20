<?php
session_start();
require_once '../config/db.php';
require_once '../config/functions.php';

// Enable error logging
error_log("Receipt.php started");

// Check if user is logged in
if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

// Check if user is staff or admin
if ($_SESSION['role'] !== 'Ca sáng' && $_SESSION['role'] !== 'Ca chiều' && $_SESSION['role'] !== 'Quản lý') {
    header("Location: ../index.php");
    exit();
}

// Check if order ID is provided
if (!isset($_GET['order_id']) || empty($_GET['order_id'])) {
    header("Location: pos.php");
    exit();
}

$order_id = $_GET['order_id'];
error_log("Processing order ID: $order_id");

// Get order details
$order_query = "SELECT o.*, t.table_name, p.discount_code, p.discount_value, u.username 
               FROM Orders o 
               LEFT JOIN Tables t ON o.table_id = t.table_id 
               LEFT JOIN Promotions p ON o.promo_id = p.promo_id 
               LEFT JOIN Users u ON o.user_id = u.user_id 
               WHERE o.order_id = ?";
$order_stmt = $conn->prepare($order_query);
$order_stmt->bind_param("i", $order_id);
$order_stmt->execute();
$order_result = $order_stmt->get_result();

if ($order_result->num_rows === 0) {
    error_log("Order not found: $order_id");
    header("Location: pos.php");
    exit();
}

$order = $order_result->fetch_assoc();
error_log("Order found successfully");

// Get order items
$items_query = "SELECT od.*, p.product_name, p.size 
               FROM Order_Details od 
               JOIN Products p ON od.product_id = p.product_id 
               WHERE od.order_id = ?";
error_log("Executing query: $items_query with order_id: $order_id");
$items_stmt = $conn->prepare($items_query);
$items_stmt->bind_param("i", $order_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();
error_log("Items found: " . $items_result->num_rows);

// Get payment details
$payment_query = "SELECT * FROM Payments WHERE order_id = ?";
$payment_stmt = $conn->prepare($payment_query);
$payment_stmt->bind_param("i", $order_id);
$payment_stmt->execute();
$payment_result = $payment_stmt->get_result();
$payment = $payment_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hóa Đơn #<?php echo $order_id; ?> - Hệ Thống Quản Lý Quán Cà Phê</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        @media print {
            body * {
                visibility: hidden;
            }
            .receipt-container, .receipt-container * {
                visibility: visible;
            }
            .receipt-container {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }
            .actions, .navbar {
                display: none !important;
            }
        }
        
        .receipt-container {
            max-width: 800px;
            margin: 20px auto;
            background: white;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-radius: 8px;
        }
        
        .receipt-header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px dashed #ddd;
        }
        
        .receipt-title {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
            color: #333;
        }
        
        .receipt-subtitle {
            font-size: 16px;
            color: #555;
            margin-bottom: 10px;
        }
        
        .receipt-info {
            margin-bottom: 20px;
        }
        
        .info-group {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
        }
        
        .info-item {
            flex: 1;
        }
        
        .info-label {
            font-weight: bold;
            display: block;
            margin-bottom: 5px;
            color: #555;
        }
        
        .info-value {
            color: #333;
        }
        
        .receipt-items {
            margin-bottom: 20px;
        }
        
        .receipt-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        .receipt-table th {
            background-color: #f5f5f5;
            padding: 10px;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #ddd;
        }
        
        .receipt-table td {
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .receipt-table .item-name {
            width: 50%;
        }
        
        .receipt-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        
        .receipt-total {
            margin-top: 20px;
            text-align: right;
        }
        
        .total-row {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 10px;
        }
        
        .total-label {
            font-weight: bold;
            margin-right: 15px;
            color: #555;
            width: 150px;
            text-align: right;
        }
        
        .total-value {
            width: 120px;
            text-align: right;
            color: #333;
        }
        
        .grand-total {
            font-size: 18px;
            font-weight: bold;
            color: #4CAF50;
            border-top: 2px solid #ddd;
            padding-top: 10px;
        }
        
        .receipt-footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px dashed #ddd;
            color: #777;
            font-style: italic;
        }
        
        .actions {
            text-align: center;
            margin-top: 20px;
        }
        
        .btn {
            margin: 0 5px;
        }
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 30px;
            display: inline-block;
            font-size: 12px;
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
        
        /* Kitchen ticket styling */
        .kitchen-ticket {
            border: 2px dashed #000;
            padding: 15px;
            margin-top: 20px;
            background: #f9f9f9;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="container">
            <a href="<?php echo $_SESSION['role'] === 'Quản lý' ? '../admin/index.php' : 'index.php'; ?>" class="navbar-brand">Quản Lý Quán Cà Phê</a>
            <ul class="navbar-nav">
                <?php if ($_SESSION['role'] === 'Quản lý'): ?>
                <li class="nav-item">
                    <a href="../admin/dashboard.php" class="nav-link">Dashboard</a>
                </li>
                <li class="nav-item">
                    <a href="../admin/orders.php" class="nav-link">Đơn Hàng</a>
                </li>
                <?php else: ?>
                <li class="nav-item">
                    <a href="pos.php" class="nav-link">Bán Hàng</a>
                </li>
                <li class="nav-item">
                    <a href="orders.php" class="nav-link">Đơn Hàng</a>
                </li>
                <?php endif; ?>
                <li class="nav-item">
                    <a href="../logout.php" class="nav-link">Đăng Xuất</a>
                </li>
            </ul>
        </div>
    </nav>
    
    <!-- Main Content -->
    <div class="container">
        <div class="receipt-container">
            <div class="receipt-header">
                <h1 class="receipt-title">Coffee Shop</h1>
                <p class="receipt-subtitle">Hóa Đơn Thanh Toán</p>
                <p>Số: #<?php echo $order['order_id']; ?></p>
            </div>
            
            <div class="receipt-info">
                <div class="info-group">
                    <div class="info-item">
                        <span class="info-label">Thời gian:</span>
                        <span class="info-value"><?php echo date('H:i:s d/m/Y', strtotime($order['order_time'])); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Bàn:</span>
                        <span class="info-value"><?php echo $order['table_name']; ?></span>
                    </div>
                </div>
                
                <div class="info-group">
                    <div class="info-item">
                        <span class="info-label">Nhân viên:</span>
                        <span class="info-value"><?php echo $order['username']; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Trạng thái:</span>
                        <span class="info-value">
                            <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $order['status'])); ?>">
                                <?php echo $order['status']; ?>
                            </span>
                        </span>
                    </div>
                </div>
                
                <?php if (!empty($payment['payment_method'])): ?>
                <div class="info-group">
                    <div class="info-item">
                        <span class="info-label">Phương thức thanh toán:</span>
                        <span class="info-value"><?php echo $payment['payment_method']; ?></span>
                    </div>
                    <?php if ($payment['payment_method'] !== 'Tiền mặt' && !empty($payment['transaction_code'])): ?>
                    <div class="info-item">
                        <span class="info-label">Mã giao dịch:</span>
                        <span class="info-value"><?php echo $payment['transaction_code']; ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="receipt-items">
                <h3>Chi Tiết Đơn Hàng</h3>
                <table class="receipt-table">
                    <thead>
                        <tr>
                            <th class="item-name">Sản phẩm</th>
                            <th>Đơn giá</th>
                            <th>Số lượng</th>
                            <th>Thành tiền</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $subtotal = 0;
                        $items_stmt->data_seek(0); // Reset pointer to start
                        while ($item = $items_result->fetch_assoc()): 
                            $item_total = $item['quantity'] * $item['price'];
                            $subtotal += $item_total;
                        ?>
                            <tr>
                                <td><?php echo $item['product_name']; ?> (<?php echo $item['size']; ?>)</td>
                                <td><?php echo formatCurrency($item['price']); ?></td>
                                <td><?php echo $item['quantity']; ?></td>
                                <td><?php echo formatCurrency($item_total); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="receipt-total">
                <div class="total-row">
                    <div class="total-label">Tổng cộng:</div>
                    <div class="total-value"><?php echo formatCurrency($subtotal); ?></div>
                </div>
                
                <?php if (!empty($order['discount_value'])): ?>
                <div class="total-row">
                    <div class="total-label">Giảm giá:</div>
                    <div class="total-value">- <?php echo formatCurrency($order['discount_value']); ?></div>
                </div>
                <div class="total-row">
                    <div class="total-label">Mã khuyến mãi:</div>
                    <div class="total-value"><?php echo $order['discount_code']; ?></div>
                </div>
                <?php endif; ?>
                
                <div class="total-row grand-total">
                    <div class="total-label">Thành tiền:</div>
                    <div class="total-value"><?php echo formatCurrency($order['total_price']); ?></div>
                </div>
            </div>
            
            <?php if (!empty($order['notes'])): ?>
            <div class="receipt-notes">
                <h3>Ghi chú:</h3>
                <p><?php echo nl2br(htmlspecialchars($order['notes'])); ?></p>
            </div>
            <?php endif; ?>
            
            <div class="receipt-footer">
                <p>Cảm ơn quý khách đã sử dụng dịch vụ!</p>
                <p>Hẹn gặp lại quý khách!</p>
            </div>
        </div>
        
        <!-- Kitchen ticket for printing -->
        <div id="kitchen-ticket" style="display: none;">
            <div class="receipt">
                <div class="receipt-header">
                    <div class="receipt-title">PHIẾU CHẾ BIẾN</div>
                    <div>#<?php echo $order['order_id']; ?> - <?php echo date('H:i d/m/Y', strtotime($order['order_time'])); ?></div>
                    <div><?php echo $order['table_name']; ?></div>
                </div>
                
                <div class="receipt-items">
                    <?php 
                    error_log("Starting to process items for kitchen ticket");
                    $items_stmt->data_seek(0); // Reset pointer to start
                    $item_count = 0;
                    while ($item = $items_result->fetch_assoc()) {
                        $item_count++; 
                        error_log("Processing item: " . print_r($item, true));
                    ?>
                        <div class="receipt-item" style="font-size: 18px; margin-bottom: 10px;">
                            <div style="font-weight: bold;">
                                <?php echo $item['quantity']; ?> x <?php echo $item['product_name']; ?> (<?php echo $item['size']; ?>)
                            </div>
                        </div>
                    <?php } 
                    
                    // If no items processed, try to display using direct queries
                    if ($item_count == 0) {
                        error_log("No items found with JOIN query, trying direct queries");
                        $debug_query = "SELECT * FROM Order_Details WHERE order_id = $order_id";
                        $debug_result = $conn->query($debug_query);
                        error_log("Direct query found " . $debug_result->num_rows . " items");
                        
                        if ($debug_result->num_rows > 0) {
                            while ($detail = $debug_result->fetch_assoc()) {
                                // Get product info
                                $prod_query = "SELECT product_name, size FROM Products WHERE product_id = " . $detail['product_id'];
                                $prod_result = $conn->query($prod_query);
                                if ($prod_result->num_rows > 0) {
                                    $product = $prod_result->fetch_assoc();
                                    ?>
                                    <div class="receipt-item" style="font-size: 18px; margin-bottom: 10px;">
                                        <div style="font-weight: bold;">
                                            <?php echo $detail['quantity']; ?> x <?php echo $product['product_name']; ?> (<?php echo $product['size']; ?>)
                                        </div>
                                    </div>
                                    <?php
                                }
                            }
                        } else {
                            ?>
                            <div class="receipt-item" style="font-size: 18px; margin-bottom: 10px; color: red;">
                                <div style="font-weight: bold;">
                                    Không tìm thấy sản phẩm nào
                                </div>
                            </div>
                            <?php
                        }
                    }
                    ?>
                </div>
                
                <div class="receipt-footer">
                    <p>Ghi chú: Làm nhanh nhé!</p>
                </div>
                
                <?php if (!empty($order['notes'])): ?>
                <div class="receipt-notes" style="margin-top: 15px; border-top: 1px dashed #000; padding-top: 10px;">
                    <h3 style="font-size: 16px; margin-bottom: 5px; font-weight: bold;">Yêu cầu của khách:</h3>
                    <p style="font-size: 16px; background-color: #f9f9f9; padding: 8px; border-radius: 4px; border-left: 3px solid #4CAF50;"><?php echo htmlspecialchars($order['notes']); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Button Controls -->
        <div class="actions" style="text-align: center; margin-top: 20px;">
            <button class="btn btn-primary" onclick="window.print()">In Hóa Đơn</button>
            <?php if ($_SESSION['role'] === 'Quản lý'): ?>
            <a href="../admin/orders.php" class="btn btn-secondary">Quay lại danh sách đơn hàng</a>
            <?php else: ?>
            <a href="pos.php" class="btn btn-secondary">Quay lại bán hàng</a>
            <button class="btn btn-primary kitchen-print-btn" onclick="printKitchenTicket()">In phiếu chế biến</button>
            <a href="orders.php" class="btn btn-info">Xem danh sách đơn hàng</a>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Automatically print the receipt when the page loads
        window.onload = function() {
            // Check if this is a direct load from checkout
            const autoprint = <?php echo isset($_GET['autoprint']) ? 'true' : 'false'; ?>;
            if (autoprint) {
                // In hóa đơn chính
                window.print();
                
                // Sau khi in hóa đơn, in phiếu chế biến
                setTimeout(function() {
                    printKitchenTicket();
                }, 1000); // Đợi 1 giây sau khi in hóa đơn
            }
        };
        
        function printKitchenTicket() {
            const content = document.getElementById('kitchen-ticket').innerHTML;
            const printWindow = window.open('', 'PRINT', 'height=600,width=800');
            
            printWindow.document.write('<html><head><title>Phiếu chế biến</title>');
            printWindow.document.write('<style>');
            printWindow.document.write(`
                body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
                .receipt {
                    max-width: 300px;
                    margin: 0 auto;
                    background-color: white;
                    padding: 15px;
                }
                .receipt-header {
                    text-align: center;
                    margin-bottom: 15px;
                    border-bottom: 1px dashed #000;
                    padding-bottom: 10px;
                }
                .receipt-title {
                    font-size: 18px;
                    font-weight: bold;
                    margin-bottom: 5px;
                }
                .receipt-items { margin-bottom: 15px; }
                .receipt-footer {
                    text-align: center;
                    margin-top: 15px;
                    font-style: italic;
                    border-top: 1px dashed #000;
                    padding-top: 10px;
                }
            `);
            printWindow.document.write('</style></head><body>');
            printWindow.document.write(content);
            printWindow.document.write('</body></html>');
            
            printWindow.document.close();
            printWindow.focus();
            
            // Tự động in và đóng cửa sổ
            printWindow.onload = function() {
                printWindow.print();
                setTimeout(function() {
                    printWindow.close();
                }, 500);
            };
        }
    </script>
</body>
</html>