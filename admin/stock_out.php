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

// Get materials for dropdown
$materials_query = "SELECT m.*, s.supplier_name FROM Materials m JOIN Suppliers s ON m.supplier_id = s.supplier_id ORDER BY m.material_name";
$materials_result = $conn->query($materials_query);

// Process stock transaction form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_usage'])) {
    $material_id = (int)$_POST['material_id'];
    $quantity = (int)$_POST['quantity'];
    $notes = sanitize($_POST['notes']);
    $transaction_type = 'Xuất';
    
    if ($material_id <= 0 || $quantity <= 0) {
        $error = "Vui lòng điền đầy đủ thông tin phiếu xuất kho";
    } else {
        // Check if enough stock is available
        $stock_query = "SELECT stock_quantity FROM Materials WHERE material_id = ?";
        $stock_stmt = $conn->prepare($stock_query);
        $stock_stmt->bind_param("i", $material_id);
        $stock_stmt->execute();
        $stock_result = $stock_stmt->get_result();
        $current_stock = $stock_result->fetch_assoc()['stock_quantity'];
        
        if ($current_stock < $quantity) {
            $error = "Số lượng xuất vượt quá tồn kho hiện có ($current_stock)";
        } else {
            // Begin transaction
            $conn->begin_transaction();
            
            try {
                // Get material price for total amount calculation
                $material_query = "SELECT unit_price FROM Stock_Transactions WHERE material_id = ? AND transaction_type = 'Nhập' ORDER BY transaction_id DESC LIMIT 1";
                $material_stmt = $conn->prepare($material_query);
                $material_stmt->bind_param("i", $material_id);
                $material_stmt->execute();
                $material_result = $material_stmt->get_result();
                
                // Default unit price if no previous transaction
                $unit_price = 0;
                if ($material_result->num_rows > 0) {
                    $unit_price = $material_result->fetch_assoc()['unit_price'];
                }
                
                $total_amount = $quantity * $unit_price;
                
                // Add stock transaction
                $transaction_query = "INSERT INTO Stock_Transactions (material_id, transaction_type, quantity, unit_price, note) 
                                    VALUES (?, ?, ?, ?, ?)";
                $transaction_stmt = $conn->prepare($transaction_query);
                $transaction_stmt->bind_param("isids", $material_id, $transaction_type, $quantity, $unit_price, $notes);
                $transaction_stmt->execute();
                
                // Update material stock quantity
                $update_query = "UPDATE Materials SET 
                                stock_quantity = stock_quantity - ?, 
                                status = IF(stock_quantity - ? > 0, 'Còn hàng', 'Hết hàng') 
                                WHERE material_id = ?";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bind_param("iii", $quantity, $quantity, $material_id);
                $update_stmt->execute();
                
                // Commit transaction
                $conn->commit();
                $success = "Xuất kho thành công";
            } catch (Exception $e) {
                // Rollback on error
                $conn->rollback();
                $error = "Lỗi khi xuất kho: " . $e->getMessage();
            }
        }
    }
}

// Filter settings
$filter_material = isset($_GET['filter_material']) ? (int)$_GET['filter_material'] : 0;
$filter_from_date = isset($_GET['filter_from_date']) ? $_GET['filter_from_date'] : '';
$filter_to_date = isset($_GET['filter_to_date']) ? $_GET['filter_to_date'] : '';

// Build query with filters
$transactions_where = "WHERE t.transaction_type = 'Xuất'";

if ($filter_material > 0) {
    $transactions_where .= " AND t.material_id = $filter_material";
}

if (!empty($filter_from_date)) {
    $transactions_where .= " AND DATE(t.transaction_datetime) >= '" . date('Y-m-d', strtotime($filter_from_date)) . "'";
}

if (!empty($filter_to_date)) {
    $transactions_where .= " AND DATE(t.transaction_datetime) <= '" . date('Y-m-d', strtotime($filter_to_date)) . "'";
}

// Pagination settings
$per_page = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $per_page;

// Get total transactions matching filter
$count_query = "SELECT COUNT(*) as total FROM Stock_Transactions t 
                JOIN Materials m ON t.material_id = m.material_id 
                $transactions_where";
$count_result = $conn->query($count_query);
$total_rows = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $per_page);

// Get transactions
$transactions_query = "SELECT t.*, m.material_name, m.unit 
                      FROM Stock_Transactions t 
                      JOIN Materials m ON t.material_id = m.material_id 
                      $transactions_where 
                      ORDER BY t.transaction_datetime DESC 
                      LIMIT $offset, $per_page";
$transactions_result = $conn->query($transactions_query);

// Calculate totals for summary
$totals_query = "SELECT 
                SUM(t.quantity) as total_quantity,
                COUNT(*) as transaction_count
                FROM Stock_Transactions t 
                JOIN Materials m ON t.material_id = m.material_id 
                $transactions_where";
$totals_result = $conn->query($totals_query);
$totals = $totals_result->fetch_assoc();

// Handle Excel export
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    // Đặt header cho UTF-8
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="stock_out_report_' . date('Ymd') . '.xls"');
    header('Cache-Control: max-age=0');
    
    // Tạo BOM (Byte Order Mark) để Excel nhận dạng UTF-8
    echo chr(239) . chr(187) . chr(191);
    
    // Tạo HTML table thay vì text để định dạng cột riêng biệt
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head>';
    echo '<body>';
    echo '<table border="1">';
    
    // Header row
    echo '<tr>
        <th>Mã</th>
        <th>Nguyên Liệu</th>
        <th>Số Lượng</th>
        <th>Đơn Vị</th>
        <th>Ngày Xuất</th>
        <th>Ghi Chú</th>
    </tr>';
    
    // No pagination for export
    $export_query = "SELECT t.*, m.material_name, m.unit 
                    FROM Stock_Transactions t 
                    JOIN Materials m ON t.material_id = m.material_id 
                    $transactions_where 
                    ORDER BY t.transaction_datetime DESC";
    $export_result = $conn->query($export_query);
    
    // Data rows
    while ($row = $export_result->fetch_assoc()) {
        $date = date('d/m/Y H:i', strtotime($row['transaction_datetime']));
        echo '<tr>';
        echo '<td>' . $row['transaction_id'] . '</td>';
        echo '<td>' . $row['material_name'] . '</td>';
        echo '<td>' . $row['quantity'] . '</td>';
        echo '<td>' . $row['unit'] . '</td>';
        echo '<td>' . $date . '</td>';
        echo '<td>' . $row['note'] . '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
    echo '</body>';
    echo '</html>';
    exit;
}

// Get material details if specified in URL
$selected_material = null;
if (isset($_GET['material_id']) && is_numeric($_GET['material_id'])) {
    $material_id = (int)$_GET['material_id'];
    $material_query = "SELECT m.*, s.supplier_id, s.supplier_name 
                      FROM Materials m 
                      JOIN Suppliers s ON m.supplier_id = s.supplier_id 
                      WHERE m.material_id = ?";
    $material_stmt = $conn->prepare($material_query);
    $material_stmt->bind_param("i", $material_id);
    $material_stmt->execute();
    $material_result = $material_stmt->get_result();
    
    if ($material_result->num_rows > 0) {
        $selected_material = $material_result->fetch_assoc();
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Xuất Kho - Hệ Thống Quản Lý Quán Cà Phê</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .inventory-nav {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .inventory-nav a {
            padding: 8px 16px;
            background-color: #f1f1f1;
            border-radius: 4px;
            text-decoration: none;
            color: #333;
            font-weight: bold;
        }
        .inventory-nav a.active {
            background-color: #4CAF50;
            color: white;
        }
        .mode-nav {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .mode-nav a {
            flex: 1;
            padding: 10px;
            background-color: #f1f1f1;
            border-radius: 4px;
            text-decoration: none;
            color: #333;
            text-align: center;
            font-weight: bold;
        }
        .mode-nav a.active {
            background-color: #2196F3;
            color: white;
        }
        .summary-stats {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        .stat-card {
            flex: 1;
            background-color: #fff;
            border-radius: 5px;
            border: 1px solid #ddd;
            padding: 15px;
            text-align: center;
        }
        .stat-card h3 {
            margin-top: 0;
            color: #333;
            font-size: 18px;
        }
        .stat-card p {
            margin-bottom: 0;
            font-size: 24px;
            font-weight: bold;
            color: #f44336; /* Red for outbound */
        }
        .filter-form {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
            padding: 15px;
            background-color: #f9f9f9;
            border-radius: 5px;
        }
        .filter-form .form-group {
            flex: 1;
            min-width: 200px;
        }
        .filter-form .btn-group {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }
        .pagination a, .pagination span {
            padding: 8px 16px;
            margin: 0 4px;
            border: 1px solid #ddd;
            color: #4CAF50;
            text-decoration: none;
            border-radius: 4px;
        }
        .pagination a:hover {
            background-color: #f1f1f1;
        }
        .pagination .active {
            background-color: #4CAF50;
            color: white;
            border: 1px solid #4CAF50;
        }
        .pagination .disabled {
            color: #aaa;
            pointer-events: none;
        }
        .stock-warning {
            color: #f44336;
            font-weight: bold;
            margin-top: 10px;
        }
        .quantity-out-input {
            width: 80px;
        }
        .stock-value {
            font-weight: bold;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <?php include 'navbar.php'; ?>
    
    <!-- Main Content -->
    <div class="container">
        <div class="dashboard">
            <div class="dashboard-header">
                <h1>Quản Lý Xuất Kho</h1>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <!-- Inventory Navigation -->
            <div class="inventory-nav">
                <a href="inventory.php">Nguyên Liệu</a>
                <a href="suppliers.php">Nhà Cung Cấp</a>
                <a href="stock_in.php">Nhập Kho</a>
                <a href="stock_out.php" class="active">Xuất Kho</a>
            </div>
            
            <!-- Mode Selection -->
            <div class="mode-nav">
                <a href="?mode=single" class="mode-link <?php echo (!isset($_GET['mode']) || $_GET['mode'] !== 'batch') ? 'active' : ''; ?>">Xuất Từng Sản Phẩm</a>
                <a href="?mode=batch" class="mode-link <?php echo (isset($_GET['mode']) && $_GET['mode'] === 'batch') ? 'active' : ''; ?>">Kiểm Kê Hàng Loạt</a>
            </div>
            
            <?php if (isset($_GET['mode']) && $_GET['mode'] === 'batch'): ?>
            <!-- Batch Stock Out -->
            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <h2>Kiểm Kê Xuất Kho Hàng Loạt</h2>
                        
                        <?php
                        // Process batch stock out form
                        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['batch_stock_out'])) {
                            $batch_note = sanitize($_POST['batch_note']);
                            $transaction_type = 'Xuất';
                            $entries_count = 0;
                            $error_count = 0;
                            
                            // Begin transaction
                            $conn->begin_transaction();
                            
                            try {
                                foreach ($_POST['quantity'] as $material_id => $quantity) {
                                    $material_id = (int)$material_id;
                                    $quantity = (int)$quantity;
                                    
                                    // Skip if quantity is 0
                                    if ($quantity <= 0) {
                                        continue;
                                    }
                                    
                                    // Check if enough stock is available
                                    $stock_query = "SELECT stock_quantity FROM Materials WHERE material_id = ?";
                                    $stock_stmt = $conn->prepare($stock_query);
                                    $stock_stmt->bind_param("i", $material_id);
                                    $stock_stmt->execute();
                                    $stock_result = $stock_stmt->get_result();
                                    $current_stock = $stock_result->fetch_assoc()['stock_quantity'];
                                    
                                    if ($current_stock < $quantity) {
                                        $error_count++;
                                        continue; // Skip this entry due to insufficient stock
                                    }
                                    
                                    // Get material price for total amount calculation
                                    $material_query = "SELECT unit_price FROM Stock_Transactions WHERE material_id = ? AND transaction_type = 'Nhập' ORDER BY transaction_id DESC LIMIT 1";
                                    $material_stmt = $conn->prepare($material_query);
                                    $material_stmt->bind_param("i", $material_id);
                                    $material_stmt->execute();
                                    $material_result = $material_stmt->get_result();
                                    
                                    // Default unit price if no previous transaction
                                    $unit_price = 0;
                                    if ($material_result->num_rows > 0) {
                                        $unit_price = $material_result->fetch_assoc()['unit_price'];
                                    }
                                    
                                    // Add stock transaction
                                    $transaction_query = "INSERT INTO Stock_Transactions (material_id, transaction_type, quantity, unit_price, note) 
                                                        VALUES (?, ?, ?, ?, ?)";
                                    $transaction_stmt = $conn->prepare($transaction_query);
                                    $transaction_stmt->bind_param("isids", $material_id, $transaction_type, $quantity, $unit_price, $batch_note);
                                    $transaction_stmt->execute();
                                    
                                    // Update material stock quantity
                                    $update_query = "UPDATE Materials SET 
                                                    stock_quantity = stock_quantity - ?, 
                                                    status = IF(stock_quantity - ? > 0, 'Còn hàng', 'Hết hàng') 
                                                    WHERE material_id = ?";
                                    $update_stmt = $conn->prepare($update_query);
                                    $update_stmt->bind_param("iii", $quantity, $quantity, $material_id);
                                    $update_stmt->execute();
                                    
                                    $entries_count++;
                                }
                                
                                // Commit transaction
                                $conn->commit();
                                
                                if ($entries_count > 0) {
                                    echo '<div class="alert alert-success">Đã xuất ' . $entries_count . ' nguyên liệu khỏi kho</div>';
                                    if ($error_count > 0) {
                                        echo '<div class="alert alert-warning">Có ' . $error_count . ' nguyên liệu không thể xuất do tồn kho không đủ</div>';
                                    }
                                } else {
                                    if ($error_count > 0) {
                                        echo '<div class="alert alert-danger">Không thể xuất ' . $error_count . ' nguyên liệu do tồn kho không đủ</div>';
                                    } else {
                                        echo '<div class="alert alert-warning">Không có nguyên liệu nào được xuất</div>';
                                    }
                                }
                                
                            } catch (Exception $e) {
                                // Rollback on error
                                $conn->rollback();
                                echo '<div class="alert alert-danger">Lỗi khi xuất kho: ' . $e->getMessage() . '</div>';
                            }
                        }
                        ?>
                        
                        <form method="POST" action="?mode=batch">
                            <div class="form-group">
                                <label for="batch_note">Ghi Chú Chung</label>
                                <textarea id="batch_note" name="batch_note" class="form-control" rows="2"></textarea>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Mã</th>
                                            <th>Nguyên Liệu</th>
                                            <th>Tồn Kho</th>
                                            <th>Đơn Vị</th>
                                            <th>Số Lượng Xuất</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        // Get all materials with stock > 0
                                        $all_materials_query = "SELECT m.*, s.supplier_name FROM Materials m JOIN Suppliers s ON m.supplier_id = s.supplier_id WHERE m.stock_quantity > 0 ORDER BY m.material_name";
                                        $all_materials_result = $conn->query($all_materials_query);
                                        
                                        if ($all_materials_result->num_rows > 0):
                                            while ($material = $all_materials_result->fetch_assoc()): 
                                        ?>
                                            <tr>
                                                <td><?php echo $material['material_id']; ?></td>
                                                <td>
                                                    <?php echo $material['material_name']; ?>
                                                    <span class="text-muted">(<?php echo $material['supplier_name']; ?>)</span>
                                                </td>
                                                <td class="stock-value"><?php echo $material['stock_quantity']; ?></td>
                                                <td><?php echo $material['unit']; ?></td>
                                                <td>
                                                    <input type="number" name="quantity[<?php echo $material['material_id']; ?>]" value="0" min="0" max="<?php echo $material['stock_quantity']; ?>" class="form-control quantity-out-input" data-stock="<?php echo $material['stock_quantity']; ?>">
                                                </td>
                                            </tr>
                                        <?php 
                                            endwhile;
                                        else: 
                                        ?>
                                            <tr>
                                                <td colspan="5" class="text-center">Không có nguyên liệu nào trong kho</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <button type="submit" name="batch_stock_out" class="btn btn-primary btn-block mt-3">Lưu Phiếu Xuất Kho</button>
                        </form>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <!-- Single Item Stock Out -->
            <div class="row">
                <!-- Stock Out Form -->
                <div class="col-md-4">
                    <div class="card">
                        <h2>Phiếu Xuất Kho</h2>
                        <form method="POST" action="stock_out.php">
                            <div class="form-group">
                                <label for="material_id">Chọn Nguyên Liệu</label>
                                <select id="material_id" name="material_id" class="form-control" required>
                                    <option value="">-- Chọn Nguyên Liệu --</option>
                                    <?php 
                                    $materials_result->data_seek(0);
                                    while ($material = $materials_result->fetch_assoc()): 
                                    ?>
                                        <option value="<?php echo $material['material_id']; ?>" 
                                            data-stock="<?php echo $material['stock_quantity']; ?>"
                                            data-unit="<?php echo $material['unit']; ?>"
                                            <?php echo (isset($selected_material) && $selected_material['material_id'] == $material['material_id']) ? 'selected' : ''; ?>>
                                            <?php echo $material['material_name']; ?> (Tồn: <?php echo $material['stock_quantity']; ?> <?php echo $material['unit']; ?>)
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                                <div id="stock-info" class="mt-2"></div>
                            </div>
                            
                            <div class="form-group">
                                <label for="quantity">Số Lượng</label>
                                <input type="number" id="quantity" name="quantity" min="1" class="form-control" required>
                                <div id="stock-warning" class="stock-warning"></div>
                            </div>
                            
                            <div class="form-group">
                                <label for="notes">Ghi Chú</label>
                                <textarea id="notes" name="notes" class="form-control" rows="3"></textarea>
                            </div>
                            
                            <button type="submit" name="add_usage" class="btn btn-primary btn-block">Xuất Kho</button>
                        </form>
                    </div>
                    
                    <!-- Low Stock Materials -->
                    <div class="card mt-3">
                        <h2>Nguyên Liệu Sắp Hết</h2>
                        <?php
                        $low_stock_query = "SELECT m.*, s.supplier_name 
                                            FROM Materials m 
                                            JOIN Suppliers s ON m.supplier_id = s.supplier_id 
                                            WHERE m.stock_quantity < 10 
                                            ORDER BY m.stock_quantity ASC 
                                            LIMIT 5";
                        $low_stock_result = $conn->query($low_stock_query);
                        
                        if ($low_stock_result->num_rows > 0):
                        ?>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Nguyên Liệu</th>
                                        <th>Tồn Kho</th>
                                        <th>Thao Tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($material = $low_stock_result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $material['material_name']; ?></td>
                                            <td><?php echo $material['stock_quantity']; ?> <?php echo $material['unit']; ?></td>
                                            <td>
                                                <a href="stock_in.php?material_id=<?php echo $material['material_id']; ?>" class="btn btn-success btn-sm">Nhập Kho</a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p class="text-center">Không có nguyên liệu nào sắp hết.</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Transactions List -->
                <div class="col-md-8">
                    <div class="card">
                        <h2>Lịch Sử Xuất Kho</h2>
                        
                        <!-- Summary Stats -->
                        <div class="summary-stats">
                            <div class="stat-card">
                                <h3>Tổng Phiếu Xuất</h3>
                                <p><?php echo number_format($totals['transaction_count']); ?></p>
                            </div>
                            <div class="stat-card">
                                <h3>Tổng SL Xuất</h3>
                                <p><?php echo number_format($totals['total_quantity']); ?></p>
                            </div>
                        </div>
                        
                        <!-- Filter Form -->
                        <form method="GET" action="stock_out.php" class="filter-form">
                            <div class="form-group">
                                <label for="filter_material">Nguyên Liệu</label>
                                <select id="filter_material" name="filter_material" class="form-control">
                                    <option value="0">-- Tất cả --</option>
                                    <?php 
                                    $materials_result->data_seek(0);
                                    while ($material = $materials_result->fetch_assoc()): 
                                    ?>
                                        <option value="<?php echo $material['material_id']; ?>" <?php echo $filter_material == $material['material_id'] ? 'selected' : ''; ?>>
                                            <?php echo $material['material_name']; ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="filter_from_date">Từ Ngày</label>
                                <input type="date" id="filter_from_date" name="filter_from_date" class="form-control" value="<?php echo $filter_from_date; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="filter_to_date">Đến Ngày</label>
                                <input type="date" id="filter_to_date" name="filter_to_date" class="form-control" value="<?php echo $filter_to_date; ?>">
                            </div>
                            
                            <div class="btn-group">
                                <button type="submit" class="btn btn-primary">Lọc</button>
                                <a href="stock_out.php" class="btn btn-secondary">Reset</a>
                                <a href="stock_out.php?<?php echo http_build_query($_GET); ?>&export=excel" class="btn btn-success">Xuất Excel</a>
                            </div>
                        </form>
                        
                        <!-- Transactions Table -->
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Mã</th>
                                        <th>Nguyên Liệu</th>
                                        <th>SL</th>
                                        <th>Đơn Vị</th>
                                        <th>Thời Gian</th>
                                        <th>Ghi Chú</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($transactions_result->num_rows > 0): ?>
                                        <?php while ($transaction = $transactions_result->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo $transaction['transaction_id']; ?></td>
                                                <td><?php echo $transaction['material_name']; ?></td>
                                                <td><?php echo $transaction['quantity']; ?></td>
                                                <td><?php echo $transaction['unit']; ?></td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($transaction['transaction_datetime'])); ?></td>
                                                <td><?php echo $transaction['note']; ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr><td colspan="6" class="text-center">Không có dữ liệu</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">&laquo;</a>
                                <?php else: ?>
                                    <span class="disabled">&laquo;</span>
                                <?php endif; ?>
                                
                                <?php
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);
                                
                                if ($start_page > 1) {
                                    echo '<a href="?' . http_build_query(array_merge($_GET, ['page' => 1])) . '">1</a>';
                                    if ($start_page > 2) {
                                        echo '<span class="disabled">...</span>';
                                    }
                                }
                                
                                for ($i = $start_page; $i <= $end_page; $i++) {
                                    if ($i == $page) {
                                        echo '<span class="active">' . $i . '</span>';
                                    } else {
                                        echo '<a href="?' . http_build_query(array_merge($_GET, ['page' => $i])) . '">' . $i . '</a>';
                                    }
                                }
                                
                                if ($end_page < $total_pages) {
                                    if ($end_page < $total_pages - 1) {
                                        echo '<span class="disabled">...</span>';
                                    }
                                    echo '<a href="?' . http_build_query(array_merge($_GET, ['page' => $total_pages])) . '">' . $total_pages . '</a>';
                                }
                                ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">&raquo;</a>
                                <?php else: ?>
                                    <span class="disabled">&raquo;</span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- JavaScript for Stock Validation -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // For single item mode
            const materialSelect = document.getElementById('material_id');
            const quantityInput = document.getElementById('quantity');
            const stockWarning = document.getElementById('stock-warning');
            const stockInfo = document.getElementById('stock-info');
            
            function updateStockInfo() {
                if (materialSelect && materialSelect.selectedIndex > 0) {
                    const selectedOption = materialSelect.options[materialSelect.selectedIndex];
                    const stockQty = selectedOption.getAttribute('data-stock');
                    const unit = selectedOption.getAttribute('data-unit');
                    
                    stockInfo.textContent = `Tồn kho hiện tại: ${stockQty} ${unit}`;
                } else if (stockInfo) {
                    stockInfo.textContent = '';
                }
            }
            
            function validateQuantity() {
                if (materialSelect && materialSelect.selectedIndex > 0 && quantityInput.value) {
                    const selectedOption = materialSelect.options[materialSelect.selectedIndex];
                    const stockQty = parseInt(selectedOption.getAttribute('data-stock'));
                    const quantity = parseInt(quantityInput.value);
                    
                    if (quantity > stockQty) {
                        stockWarning.textContent = `Số lượng xuất vượt quá tồn kho (${stockQty})`;
                        return false;
                    } else {
                        stockWarning.textContent = '';
                        return true;
                    }
                }
                return true;
            }
            
            if (materialSelect) {
                materialSelect.addEventListener('change', function() {
                    updateStockInfo();
                    validateQuantity();
                });
            }
            
            if (quantityInput) {
                quantityInput.addEventListener('input', validateQuantity);
            }
            
            // For batch mode
            const batchQuantityInputs = document.querySelectorAll('.quantity-out-input');
            
            if (batchQuantityInputs.length > 0) {
                // Add quick filter for materials
                const searchBox = document.createElement('input');
                searchBox.type = 'text';
                searchBox.placeholder = 'Tìm kiếm nguyên liệu...';
                searchBox.className = 'form-control mb-3';
                searchBox.id = 'material-search';
                
                // Find the table
                const materialTable = document.querySelector('.table-striped');
                if (materialTable) {
                    materialTable.parentNode.insertBefore(searchBox, materialTable);
                    
                    // Add search functionality
                    searchBox.addEventListener('input', function() {
                        const searchTerm = this.value.toLowerCase();
                        const rows = materialTable.querySelectorAll('tbody tr');
                        
                        rows.forEach(row => {
                            const materialName = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
                            if (materialName.includes(searchTerm)) {
                                row.style.display = '';
                            } else {
                                row.style.display = 'none';
                            }
                        });
                    });
                }
                
                // Add keyboard navigation for faster data entry
                batchQuantityInputs.forEach((input, index) => {
                    input.addEventListener('input', function() {
                        const stockQty = parseInt(this.getAttribute('data-stock'));
                        const quantity = parseInt(this.value || 0);
                        
                        if (quantity > stockQty) {
                            this.setCustomValidity(`Số lượng xuất vượt quá tồn kho (${stockQty})`);
                            this.classList.add('is-invalid');
                        } else {
                            this.setCustomValidity('');
                            this.classList.remove('is-invalid');
                        }
                    });

                    input.addEventListener('keydown', function(e) {
                        if (e.key === 'Enter' || e.key === 'ArrowDown') {
                            e.preventDefault();
                            // Move to next input
                            if (index < batchQuantityInputs.length - 1) {
                                batchQuantityInputs[index + 1].focus();
                            }
                        } else if (e.key === 'ArrowUp') {
                            e.preventDefault();
                            // Move to previous input
                            if (index > 0) {
                                batchQuantityInputs[index - 1].focus();
                            }
                        }
                    });
                });
            }
            
            // Initialize form validation
            const singleForm = document.querySelector('form[name="add_usage"]');
            if (singleForm) {
                singleForm.addEventListener('submit', function(e) {
                    if (!validateQuantity()) {
                        e.preventDefault();
                        alert('Số lượng xuất không thể vượt quá tồn kho hiện tại');
                    }
                });
            }
            
            // Initialize on page load
            updateStockInfo();
        });
    </script>
</body>
</html> 