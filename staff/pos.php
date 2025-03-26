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

// Xác định ca làm việc hiện tại
$current_shift = getCurrentShift($conn);
$shift_warning = '';

// Kiểm tra xem có ca làm việc hợp lệ hay không
$shift_query = "SELECT * FROM Shifts WHERE shift_id = ?";
$shift_stmt = $conn->prepare($shift_query);
$shift_stmt->bind_param("i", $current_shift);
$shift_stmt->execute();
$shift_result = $shift_stmt->get_result();

// Nếu không tìm thấy ca làm việc phù hợp với thời gian hiện tại
if ($shift_result->num_rows == 0) {
    $current_shift = 1; // Mặc định sử dụng ca sáng
    $shift_warning = '<div class="alert alert-warning">
        <strong>Lưu ý:</strong> Đang sử dụng ca mặc định (Ca sáng). 
        Kiểm tra cài đặt ca làm việc <a href="../add_shifts.php">tại đây</a>.
    </div>';
} else {
    // Kiểm tra xem thời gian hiện tại có nằm trong ca làm việc này hay không
    $current_time = date('H:i:s');
    $shift_data = $shift_result->fetch_assoc();
    
    if ($current_time < $shift_data['start_time'] || $current_time > $shift_data['end_time']) {
        $shift_warning = '<div class="alert alert-warning">
            <strong>Lưu ý:</strong> Thời gian hiện tại (' . $current_time . ') không nằm trong 
            ' . $shift_data['shift_name'] . ' (' . $shift_data['start_time'] . ' - ' . $shift_data['end_time'] . ').
            <a href="../update_shift_function.php">Kiểm tra/Cập nhật</a>
        </div>';
    }
}

// Get tables
$tables_query = "SELECT * FROM Tables ORDER BY table_name";
$tables_result = $conn->query($tables_query);

// Get categories
$categories_query = "SELECT * FROM Categories WHERE status = 'Hoạt động' ORDER BY category_name";
$categories_result = $conn->query($categories_query);

// Get products
$products_query = "SELECT p.*, c.category_name FROM Products p 
                  JOIN Categories c ON p.category_id = c.category_id 
                  WHERE p.status = 'Hoạt động' 
                  ORDER BY p.category_id, p.product_name, p.size";
$products_result = $conn->query($products_query);

// Process order submission or complete order
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Xử lý khi ấn thanh toán để tạo đơn hàng mới
    if (isset($_POST['submit_order'])) {
        $table_id = $_POST['table_id'] ?? '';
        $promo_id = !empty($_POST['promo_id']) ? $_POST['promo_id'] : null;
        $total_price = $_POST['total_price'] ?? 0;
        $product_ids = $_POST['product_id'] ?? [];
        $quantities = $_POST['quantity'] ?? [];
        $prices = $_POST['price'] ?? [];
        $payment_method = $_POST['payment_method'] ?? '';
        $transaction_code = $_POST['transaction_code'] ?? null;
        $order_notes = $_POST['order_notes'] ?? '';
        
        // Debug info
        error_log("Product IDs: " . print_r($product_ids, true));
        error_log("Quantities: " . print_r($quantities, true));
        error_log("Prices: " . print_r($prices, true));
        
        // Validate inputs
        if (empty($table_id) || empty($product_ids) || empty($payment_method)) {
            $error = "Vui lòng điền đầy đủ thông tin đơn hàng";
        } else {
            // Start transaction
            $conn->begin_transaction();
            
            try {
                // Insert order
                $order_query = "INSERT INTO Orders (user_id, table_id, shift_id, promo_id, total_price, notes, status, completed) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $order_stmt = $conn->prepare($order_query);
                $completed = 0; // Giá trị mặc định cho completed
                $status = 'Chờ chế biến'; // Giá trị mặc định cho status
                $order_stmt->bind_param("isiddssi", $_SESSION['user_id'], $table_id, $current_shift, $promo_id, $total_price, $order_notes, $status, $completed);
                $order_stmt->execute();
                
                $order_id = $conn->insert_id;
                
                // Insert order details
                foreach ($product_ids as $product_id) {
                    // Ensure product_id is numeric and exists in quantities array
                    $product_id = intval($product_id);
                    if (isset($quantities[$product_id]) && isset($prices[$product_id])) {
                        $quantity = intval($quantities[$product_id]);
                        $price = floatval($prices[$product_id]);
                        
                        $detail_query = "INSERT INTO Order_Details (order_id, product_id, quantity, price) 
                                       VALUES (?, ?, ?, ?)";
                        $detail_stmt = $conn->prepare($detail_query);
                        $detail_stmt->bind_param("iiid", $order_id, $product_id, $quantity, $price);
                        $detail_stmt->execute();
                        
                        // Log entry for debugging
                        error_log("Added product ID: $product_id, Quantity: $quantity, Price: $price to order $order_id");
                    } else {
                        error_log("Missing quantity or price for product ID: $product_id");
                    }
                }
                
                // Insert payment
                $payment_query = "INSERT INTO Payments (order_id, payment_method, amount, transaction_code, status) 
                                VALUES (?, ?, ?, ?, 'Thành công')";
                $payment_stmt = $conn->prepare($payment_query);
                $payment_stmt->bind_param("isds", $order_id, $payment_method, $total_price, $transaction_code);
                $payment_stmt->execute();
                
                // Update table usage information in another way
                $table_query = "UPDATE Orders SET notes = CONCAT(notes, ' | ') WHERE order_id = ?";
                $table_stmt = $conn->prepare($table_query);
                $table_stmt->bind_param("i", $order_id);
                $table_stmt->execute();
                
                // Commit transaction
                $conn->commit();
                
                // Redirect to receipt page
                header("Location: receipt.php?order_id=" . $order_id . "&autoprint=1");
                exit();
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollback();
                $error = "Lỗi khi xử lý đơn hàng: " . $e->getMessage();
            }
        }
    }
    
    // Xử lý khi nhân viên pha chế ấn "Hoàn thành"
    elseif (isset($_POST['complete_order'])) {
        $order_id = $_POST['order_id'] ?? '';
        
        if (!empty($order_id)) {
            try {
                $update_query = "UPDATE Orders SET status = 'Hoàn thành', completed = 1 WHERE order_id = ?";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bind_param("i", $order_id);
                $update_stmt->execute();
                
                if ($update_stmt->affected_rows > 0) {
                    $success = "Đơn hàng đã được đánh dấu là hoàn thành!";
                } else {
                    $error = "Không tìm thấy đơn hàng hoặc đã hoàn thành trước đó.";
                }
            } catch (Exception $e) {
                $error = "Lỗi khi cập nhật trạng thái đơn hàng: " . $e->getMessage();
            }
        } else {
            $error = "Vui lòng cung cấp ID đơn hàng để hoàn thành.";
        }
    }
}

// Hiển thị thông báo lỗi hoặc thành công (nếu cần)
if (isset($error)) {
    echo '<div class="alert alert-danger">' . $error . '</div>';
}
if (isset($success)) {
    echo '<div class="alert alert-success">' . $success . '</div>';
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bán Hàng - Hệ Thống Quản Lý Quán Cà Phê</title>
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
                <div id="clock"></div>
                <script>
                    function updateClock() {
                        const now = new Date();
                        const timeString = now.toLocaleTimeString('vi-VN');
                        document.getElementById('clock').textContent = timeString;
                    }
                    
                    // Update clock immediately and every second
                    updateClock();
                    setInterval(updateClock, 1000);
                </script>
                <h1>Bán Hàng</h1>
                <p>Nhân viên: <?php echo $_SESSION['username']; ?> | Ca: <?php echo $_SESSION['role']; ?></p>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if (isset($shift_warning)): ?>
                <?php echo $shift_warning; ?>
            <?php endif; ?>
            
            <div class="pos-container">
                <!-- Tables Section -->
                <div class="tables-section">
                    <h2>Chọn Bàn</h2>
                    
                    <div class="table-grid">
                        <?php if ($tables_result->num_rows > 0): ?>
                            <?php while ($table = $tables_result->fetch_assoc()): ?>
                                <div class="table-item" 
                                     data-id="<?php echo $table['table_id']; ?>">
                                    <div class="table-name"><?php echo $table['table_name']; ?></div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p>Không có bàn nào</p>
                        <?php endif; ?>
                    </div>
                    
                    <h2 class="mt-4">Chọn Sản Phẩm</h2>
                    
                    <div class="categories-list">
                        <div class="category-item active" data-id="all">Tất cả</div>
                        <?php if ($categories_result->num_rows > 0): ?>
                            <?php while ($category = $categories_result->fetch_assoc()): ?>
                                <div class="category-item" data-id="<?php echo $category['category_id']; ?>">
                                    <?php echo $category['category_name']; ?>
                                </div>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </div>
                    
                    <div class="products-grid">
                        <?php if ($products_result->num_rows > 0): ?>
                            <?php while ($product = $products_result->fetch_assoc()): ?>
                                <div class="product-item" 
                                     data-id="<?php echo $product['product_id']; ?>"
                                     data-category="<?php echo $product['category_id']; ?>"
                                     data-price="<?php echo $product['price']; ?>"
                                     data-size="<?php echo $product['size']; ?>">
                                    <div class="product-name">
                                        <?php echo $product['product_name']; ?>
                                    </div>
                                    <div class="product-size">
                                        <?php echo $product['size']; ?>
                                    </div>
                                    <div class="product-price">
                                        <?php echo formatCurrency($product['price']); ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p>Không có sản phẩm nào</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Order Section -->
                <div class="order-section" style="display: none;">
                    <h2>Đơn Hàng</h2>
                    
                    <form id="order_form" method="POST" action="pos.php">
                        <input type="hidden" id="selected_table_id" name="table_id" value="">
                        <input type="hidden" id="promo_id" name="promo_id" value="">
                        <input type="hidden" id="discount_value" name="discount_value" value="0">
                        <input type="hidden" id="total_price" name="total_price" value="0">
                        
                        <div class="table-actions">
                            <button type="button" id="close_order_btn" class="btn btn-danger">Đóng</button>
                            <button type="button" id="exit_order_btn" class="btn btn-warning">Tạm Thoát</button>
                        </div>
                        
                        <div class="order-items">
                            <!-- Order items will be added here dynamically -->
                        </div>
                        
                        <!-- Order Notes Section -->
                        <div class="order-notes-section">
                            <div class="form-group">
                                <label for="order_notes">Ghi chú đơn hàng</label>
                                <textarea id="order_notes" name="order_notes" class="form-control" placeholder=""></textarea>
                            </div>
                        </div>
                        
                        <div class="promo-code-section">
                            <div class="form-group">
                                <label for="promo_code">Mã Khuyến Mãi</label>
                                <div class="promo-input-group">
                                    <input type="text" id="promo_code" name="promo_code" placeholder="Nhập mã khuyến mãi">
                                    <button type="button" id="apply_promo_btn" class="btn btn-secondary">Áp Dụng</button>
                                    <button type="button" id="show_promos_btn" class="btn btn-info">Xem Mã KM</button>
                                </div>
                            </div>
                            <div id="available_promos" class="available-promos" style="display: none;">
                                <!-- Available promo codes will be loaded here -->
                            </div>
                            <div id="discount_info" style="display: none; color: #4CAF50; margin-bottom: 10px;">
                                <div class="discount-info-content"></div>
                                <button type="button" id="remove_promo_btn" class="btn btn-danger btn-sm">Bỏ mã KM</button>
                            </div>
                        </div>
                        
                        <div class="order-summary">
                            <div class="order-total">
                                Tổng tiền: <span class="order-total-value">0 đ</span>
                            </div>
                            
                            <div class="payment-section">
                                <h3>Thanh Toán</h3>
                                <div class="form-group">
                                    <label for="payment_method">Phương thức thanh toán:</label>
                                    <select id="payment_method" name="payment_method" class="form-control" required>
                                        <option value="">Chọn phương thức thanh toán</option>
                                        <option value="Tiền mặt">Tiền mặt</option>
                                        <option value="Thẻ">Thẻ</option>
                                        <option value="Chuyển khoản">Chuyển khoản</option>
                                        <option value="MoMo">MoMo</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="transaction_code">Mã giao dịch (nếu có):</label>
                                    <input type="text" id="transaction_code" name="transaction_code" class="form-control">
                                </div>
                            </div>
                            
                            <button type="button" id="checkout_btn" class="btn btn-primary btn-block">Thanh Toán</button>
                            <button type="submit" name="submit_order" id="submit_order_btn" style="display: none;">Submit</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/script.js"></script>
    <script>
        // Define API paths for promo codes
        window.apiBasePath = 'api/';  // Relative path from current directory
        
        // Complete replacement for the checkout process
        document.addEventListener('DOMContentLoaded', function() {
            // Replace the checkout button handler
            const originalCheckoutBtn = document.getElementById('checkout_btn');
            if (originalCheckoutBtn) {
                // Remove any existing event listeners by cloning and replacing
                const newCheckoutBtn = originalCheckoutBtn.cloneNode(true);
                originalCheckoutBtn.parentNode.replaceChild(newCheckoutBtn, originalCheckoutBtn);
                
                // Add our new event listener
                newCheckoutBtn.addEventListener('click', function() {
                    // Get the selected table
                    const tableId = document.getElementById('selected_table_id').value;
                    if (!tableId) {
                        alert('Vui lòng chọn bàn trước khi thanh toán.');
                        return;
                    }
                    
                    // Check if there are items in the order
                    const orderItems = document.querySelectorAll('.order-item');
                    if (orderItems.length === 0) {
                        alert('Vui lòng thêm sản phẩm vào đơn hàng.');
                        return;
                    }
                    
                    // Get the payment method from the dropdown
                    const paymentMethodSelect = document.getElementById('payment_method');
                    const paymentMethod = paymentMethodSelect.value;
                    
                    if (!paymentMethod) {
                        alert('Vui lòng chọn phương thức thanh toán.');
                        return;
                    }
                    
                    console.log("Payment method selected:", paymentMethod);
                    
                    // Submit the form by clicking the submit button
                    document.getElementById('submit_order_btn').click();
                });
            }
        });
    </script>
</body>
</html>