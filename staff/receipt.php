<?php
session_start();
require_once '../config/db.php';
require_once '../config/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

// Check if order ID is provided
if (!isset($_GET['order_id']) || empty($_GET['order_id'])) {
    header("Location: pos.php");
    exit();
}

$order_id = $_GET['order_id'];

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
    header("Location: pos.php");
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
        .receipt {
            max-width: 400px;
            margin: 0 auto;
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .receipt-header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 1px dashed #ddd;
            padding-bottom: 10px;
        }
        
        .receipt-title {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .receipt-info {
            margin-bottom: 20px;
        }
        
        .receipt-info p {
            margin: 5px 0;
        }
        
        .receipt-items {
            margin-bottom: 20px;
        }
        
        .receipt-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        
        .receipt-total {
            border-top: 1px dashed #ddd;
            padding-top: 10px;
            font-weight: bold;
        }
        
        .receipt-footer {
            text-align: center;
            margin-top: 20px;
            font-style: italic;
        }
        
        .print-btn {
            display: block;
            margin: 20px auto;
        }
        
        .receipt-notes {
            margin-top: 15px;
            border-top: 1px dashed #ddd;
            padding-top: 15px;
        }
        
        .receipt-notes h3 {
            font-size: 16px;
            margin-bottom: 5px;
            color: #4CAF50;
        }
        
        .receipt-notes p {
            font-style: italic;
            background-color: #f9f9f9;
            padding: 8px;
            border-radius: 4px;
            border-left: 3px solid #4CAF50;
        }
    </style>
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
            <button class="btn btn-primary print-btn" onclick="window.print()">In Hóa Đơn</button>
            
            <div class="receipt">
                <div class="receipt-header">
                    <div class="receipt-title">Quán Cà Phê</div>
                    <p>Địa chỉ: 123 Đường ABC, Quận XYZ</p>
                    <p>Điện thoại: 0123 456 789</p>
                </div>
                
                <div class="receipt-info">
                    <p><strong>Hóa đơn #:</strong> <?php echo $order_id; ?></p>
                    <p><strong>Ngày:</strong> <?php echo date('d/m/Y H:i', strtotime($order['order_time'])); ?></p>
                    <p><strong>Bàn:</strong> <?php echo $order['table_name']; ?></p>
                    <p><strong>Nhân viên:</strong> <?php echo $order['username']; ?></p>
                </div>
                
                <div class="receipt-items">
                    <h3>Chi tiết đơn hàng</h3>
                    
                    <?php
                    $subtotal = 0;
                    while ($item = $items_result->fetch_assoc()) {
                        $item_total = $item['quantity'] * $item['price'];
                        $subtotal += $item_total;
                    ?>
                        <div class="receipt-item">
                            <div>
                                <span><?php echo $item['product_name']; ?> (<?php echo $item['size']; ?>)</span>
                                <div><?php echo $item['quantity']; ?> x <?php echo formatCurrency($item['price']); ?></div>
                            </div>
                            <div><?php echo formatCurrency($item_total); ?></div>
                        </div>
                    <?php } ?>
                </div>
                
                <div class="receipt-total">
                    <div class="receipt-item">
                        <span>Tổng cộng:</span>
                        <span><?php echo formatCurrency($subtotal); ?></span>
                    </div>
                    
                    <?php if ($order['promo_id']): ?>
                    <div class="receipt-item">
                        <span>Giảm giá (<?php echo $order['discount_code']; ?>):</span>
                        <span>-<?php echo formatCurrency($order['discount_value']); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="receipt-item">
                        <span>Thành tiền:</span>
                        <span><?php echo formatCurrency($order['total_price']); ?></span>
                    </div>
                    
                    <div class="receipt-item">
                        <span>Phương thức thanh toán:</span>
                        <span><?php echo $payment['payment_method']; ?></span>
                    </div>
                </div>
                
                <?php if (!empty($order['notes'])): ?>
                <div class="receipt-notes">
                    <h3>Ghi chú:</h3>
                    <p><?php echo htmlspecialchars($order['notes']); ?></p>
                </div>
                <?php endif; ?>
                
                <div class="receipt-footer">
                    <p>Cảm ơn quý khách đã ghé quán!</p>
                    <p>Hẹn gặp lại quý khách!</p>
                </div>
            </div>
            
            <div class="actions" style="text-align: center; margin-top: 20px;">
                <a href="pos.php" class="btn btn-secondary">Quay lại bán hàng</a>
                <button class="btn btn-primary kitchen-print-btn" onclick="printKitchenTicket()">In phiếu chế biến</button>
                <a href="orders.php" class="btn btn-info">Xem danh sách đơn hàng</a>
            </div>
            
            <!-- Kitchen Ticket - Hidden by default, shown when printing -->
            <div id="kitchen-ticket" style="display: none;">
                <div class="receipt" style="max-width: 300px;">
                    <div class="receipt-header">
                        <div class="receipt-title">PHIẾU CHẾ BIẾN</div>
                        <p><strong>Đơn #:</strong> <?php echo $order_id; ?></p>
                        <p><strong>Thời gian:</strong> <?php echo date('H:i d/m/Y', strtotime($order['order_time'])); ?></p>
                        <p><strong>Bàn:</strong> <?php echo $order['table_name']; ?></p>
                    </div>
                    
                    <div class="receipt-items">
                        <h3>Các món cần chế biến</h3>
                        
                        <?php
                        // Reset pointer to first row
                        $items_stmt->execute();
                        $items_result = $items_stmt->get_result();
                        
                        while ($item = $items_result->fetch_assoc()) {
                        ?>
                            <div class="receipt-item" style="font-size: 18px; margin-bottom: 10px;">
                                <div style="font-weight: bold;">
                                    <?php echo $item['quantity']; ?> x <?php echo $item['product_name']; ?> (<?php echo $item['size']; ?>)
                                </div>
                            </div>
                        <?php } ?>
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
        </div>
    </div>
    
    <script>
        // Automatically print the receipt when the page loads
        window.onload = function() {
            // Check if this is a direct load from checkout
            const autoprint = <?php echo isset($_GET['autoprint']) ? 'true' : 'false'; ?>;
            if (autoprint) {
                window.print();
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
            
            printWindow.onload = function() {
                printWindow.print();
                printWindow.close();
            };
        }
    </script>
</body>
</html>