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

// Get suppliers for dropdown
$suppliers_query = "SELECT * FROM Suppliers WHERE status = 'Hoạt động' ORDER BY supplier_name";
$suppliers_result = $conn->query($suppliers_query);

// Process stock transaction form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_stock'])) {
    $material_id = (int)$_POST['material_id'];
    $supplier_id = (int)$_POST['supplier_id'];
    $quantity = (int)$_POST['quantity'];
    // Đơn giá có thể được gửi dưới dạng đã được định dạng (có dấu phân cách) 
    // hoặc dạng số nguyên, cần chuyển đổi thành float
    $unit_price = (float)str_replace([',', '.'], '', $_POST['unit_price']);
    $notes = sanitize($_POST['notes']);
    $transaction_type = 'Nhập';
    
    if ($material_id <= 0 || $supplier_id <= 0 || $quantity <= 0 || $unit_price <= 0) {
        $error = "Vui lòng điền đầy đủ thông tin phiếu nhập kho";
    } else {
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Add stock transaction
            $transaction_query = "INSERT INTO Stock_Transactions (material_id, transaction_type, quantity, unit_price, note) 
                                VALUES (?, ?, ?, ?, ?)";
            $transaction_stmt = $conn->prepare($transaction_query);
            $transaction_stmt->bind_param("isids", $material_id, $transaction_type, $quantity, $unit_price, $notes);
            $transaction_stmt->execute();
            
            // Update material stock quantity
            $update_query = "UPDATE Materials SET 
                            stock_quantity = stock_quantity + ?, 
                            status = IF(stock_quantity + ? > 0, 'Còn hàng', 'Hết hàng') 
                            WHERE material_id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("iii", $quantity, $quantity, $material_id);
            $update_stmt->execute();
            
            // Commit transaction
            $conn->commit();
            $success = "Nhập kho thành công";
            
            // Thêm thông báo để kích hoạt cập nhật báo cáo lợi nhuận
            $_SESSION['stock_in_success'] = true;
        } catch (Exception $e) {
            // Rollback on error
            $conn->rollback();
            $error = "Lỗi khi nhập kho: " . $e->getMessage();
        }
    }
}

// Filter settings
$filter_material = isset($_GET['filter_material']) ? (int)$_GET['filter_material'] : 0;
$filter_supplier = isset($_GET['filter_supplier']) ? (int)$_GET['filter_supplier'] : 0;
$filter_from_date = isset($_GET['filter_from_date']) ? $_GET['filter_from_date'] : '';
$filter_to_date = isset($_GET['filter_to_date']) ? $_GET['filter_to_date'] : '';

// Build query with filters
$transactions_where = "WHERE t.transaction_type = 'Nhập'";

if ($filter_material > 0) {
    $transactions_where .= " AND t.material_id = $filter_material";
}

if ($filter_supplier > 0) {
    $transactions_where .= " AND m.supplier_id = $filter_supplier";
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
$transactions_query = "SELECT t.*, m.material_name, m.unit, s.supplier_name 
                      FROM Stock_Transactions t 
                      JOIN Materials m ON t.material_id = m.material_id 
                      JOIN Suppliers s ON m.supplier_id = s.supplier_id 
                      $transactions_where 
                      ORDER BY t.transaction_datetime DESC 
                      LIMIT $offset, $per_page";
$transactions_result = $conn->query($transactions_query);

// Calculate totals for summary
$totals_query = "SELECT 
                SUM(t.quantity) as total_quantity,
                SUM(t.total_amount) as total_amount,
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
    header('Content-Disposition: attachment; filename="stock_in_report_' . date('Ymd') . '.xls"');
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
        <th>Nhà Cung Cấp</th>
        <th>Số Lượng</th>
        <th>Đơn Vị</th>
        <th>Đơn Giá</th>
        <th>Thành Tiền</th>
        <th>Ngày Nhập</th>
        <th>Ghi Chú</th>
    </tr>';
    
    // No pagination for export
    $export_query = "SELECT t.*, m.material_name, m.unit, s.supplier_name 
                    FROM Stock_Transactions t 
                    JOIN Materials m ON t.material_id = m.material_id 
                    JOIN Suppliers s ON m.supplier_id = s.supplier_id 
                    $transactions_where 
                    ORDER BY t.transaction_datetime DESC";
    $export_result = $conn->query($export_query);
    
    // Data rows
    while ($row = $export_result->fetch_assoc()) {
        $date = date('d/m/Y H:i', strtotime($row['transaction_datetime']));
        echo '<tr>';
        echo '<td>' . $row['transaction_id'] . '</td>';
        echo '<td>' . $row['material_name'] . '</td>';
        echo '<td>' . $row['supplier_name'] . '</td>';
        echo '<td>' . $row['quantity'] . '</td>';
        echo '<td>' . $row['unit'] . '</td>';
        echo '<td>' . number_format($row['unit_price']) . '</td>';
        echo '<td>' . number_format($row['total_amount']) . '</td>';
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
    <title>Quản Lý Nhập Kho - Hệ Thống Quản Lý Quán Cà Phê</title>
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
            color: #4CAF50;
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
        .quantity-input {
            width: 80px;
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
                <h1>Quản Lý Nhập Kho</h1>
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
                <a href="stock_in.php" class="active">Nhập Kho</a>
                <a href="stock_out.php">Xuất Kho</a>
            </div>
            
            <!-- Mode Selection -->
            <div class="mode-nav">
                <a href="?mode=single" class="mode-link <?php echo (!isset($_GET['mode']) || $_GET['mode'] !== 'batch') ? 'active' : ''; ?>">Nhập Từng Sản Phẩm</a>
                <a href="?mode=batch" class="mode-link <?php echo (isset($_GET['mode']) && $_GET['mode'] === 'batch') ? 'active' : ''; ?>">Kiểm Kê Hàng Loạt</a>
            </div>
            
            <?php if (isset($_GET['mode']) && $_GET['mode'] === 'batch'): ?>
            <!-- Batch Stock Entry -->
            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <h2>Kiểm Kê Hàng Loạt</h2>
                        
                        <?php
                        // Process batch stock entry form
                        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['batch_stock_entry'])) {
                            $batch_note = sanitize($_POST['batch_note']);
                            $transaction_type = 'Nhập';
                            $entries_count = 0;
                            
                            // Begin transaction
                            $conn->begin_transaction();
                            
                            try {
                                foreach ($_POST['quantity'] as $material_id => $quantity) {
                                    $material_id = (int)$material_id;
                                    $quantity = (int)$quantity;
                                    // Xử lý đơn giá có dấu phân cách
                                    $unit_price = (float)str_replace([',', '.'], '', $_POST['unit_price'][$material_id] ?? 0);
                                    
                                    // Skip if quantity is 0
                                    if ($quantity <= 0) {
                                        continue;
                                    }
                                    
                                    // Add stock transaction
                                    $transaction_query = "INSERT INTO Stock_Transactions (material_id, transaction_type, quantity, unit_price, note) 
                                                        VALUES (?, ?, ?, ?, ?)";
                                    $transaction_stmt = $conn->prepare($transaction_query);
                                    $transaction_stmt->bind_param("isids", $material_id, $transaction_type, $quantity, $unit_price, $batch_note);
                                    $transaction_stmt->execute();
                                    
                                    // Update material stock quantity
                                    $update_query = "UPDATE Materials SET 
                                                    stock_quantity = stock_quantity + ?, 
                                                    status = IF(stock_quantity + ? > 0, 'Còn hàng', 'Hết hàng') 
                                                    WHERE material_id = ?";
                                    $update_stmt = $conn->prepare($update_query);
                                    $update_stmt->bind_param("iii", $quantity, $quantity, $material_id);
                                    $update_stmt->execute();
                                    
                                    $entries_count++;
                                }
                                
                                // Commit transaction
                                $conn->commit();
                                
                                if ($entries_count > 0) {
                                    echo '<div class="alert alert-success">Đã cập nhật ' . $entries_count . ' nguyên liệu vào kho</div>';
                                } else {
                                    echo '<div class="alert alert-warning">Không có nguyên liệu nào được cập nhật</div>';
                                }
                                
                            } catch (Exception $e) {
                                // Rollback on error
                                $conn->rollback();
                                echo '<div class="alert alert-danger">Lỗi khi nhập kho: ' . $e->getMessage() . '</div>';
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
                                            <th>Số Lượng Nhập</th>
                                            <th>Đơn Giá (VNĐ)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        // Get all materials
                                        $all_materials_query = "SELECT m.*, s.supplier_name FROM Materials m JOIN Suppliers s ON m.supplier_id = s.supplier_id ORDER BY m.material_name";
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
                                                <td><?php echo $material['stock_quantity']; ?></td>
                                                <td><?php echo $material['unit']; ?></td>
                                                <td>
                                                    <input type="number" name="quantity[<?php echo $material['material_id']; ?>]" value="0" min="0" class="form-control quantity-input">
                                                </td>
                                                <td>
                                                    <input type="text" name="unit_price[<?php echo $material['material_id']; ?>]" value="<?php echo number_format($material['unit_price'] ?? 0, 0, '', '.'); ?>" class="form-control price-input">
                                                </td>
                                            </tr>
                                        <?php 
                                            endwhile;
                                        else: 
                                        ?>
                                            <tr>
                                                <td colspan="6" class="text-center">Không có nguyên liệu nào</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <button type="submit" name="batch_stock_entry" class="btn btn-primary btn-block mt-3">Lưu Phiếu Nhập Kho</button>
                        </form>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <!-- Single Item Stock Entry -->
            <div class="row">
                <!-- Add Stock Form -->
                <div class="col-md-4">
                    <div class="card">
                        <h2>Phiếu Nhập Kho</h2>
                        <form method="POST" action="stock_in.php">
                            <div class="form-group">
                                <label for="material_id">Chọn Nguyên Liệu</label>
                                <select id="material_id" name="material_id" class="form-control" required>
                                    <option value="">-- Chọn Nguyên Liệu --</option>
                                    <?php 
                                    $materials_result->data_seek(0);
                                    while ($material = $materials_result->fetch_assoc()): 
                                    ?>
                                        <option value="<?php echo $material['material_id']; ?>" 
                                            data-supplier="<?php echo $material['supplier_id']; ?>"
                                            <?php echo (isset($selected_material) && $selected_material['material_id'] == $material['material_id']) ? 'selected' : ''; ?>>
                                            <?php echo $material['material_name']; ?> (<?php echo $material['unit']; ?>)
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="supplier_id">Nhà Cung Cấp</label>
                                <select id="supplier_id" name="supplier_id" class="form-control" required>
                                    <option value="">-- Chọn Nhà Cung Cấp --</option>
                                    <?php 
                                    $suppliers_result->data_seek(0);
                                    while ($supplier = $suppliers_result->fetch_assoc()): 
                                    ?>
                                        <option value="<?php echo $supplier['supplier_id']; ?>"
                                            <?php echo (isset($selected_material) && $selected_material['supplier_id'] == $supplier['supplier_id']) ? 'selected' : ''; ?>>
                                            <?php echo $supplier['supplier_name']; ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="quantity">Số Lượng</label>
                                <input type="number" id="quantity" name="quantity" min="1" class="form-control" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="unit_price">Đơn Giá (VNĐ)</label>
                                <input type="text" id="unit_price" name="unit_price" class="form-control price-input" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="notes">Ghi Chú</label>
                                <textarea id="notes" name="notes" class="form-control" rows="3"></textarea>
                            </div>
                            
                            <button type="submit" name="add_stock" class="btn btn-primary btn-block">Nhập Kho</button>
                        </form>
                    </div>
                </div>
                
                <!-- Transactions List -->
                <div class="col-md-8">
                    <div class="card">
                        <h2>Lịch Sử Nhập Kho</h2>
                        
                        <!-- Summary Stats -->
                        <div class="summary-stats">
                            <div class="stat-card">
                                <h3>Tổng Phiếu Nhập</h3>
                                <p><?php echo number_format($totals['transaction_count']); ?></p>
                            </div>
                            <div class="stat-card">
                                <h3>Tổng SL Nhập</h3>
                                <p><?php echo number_format($totals['total_quantity']); ?></p>
                            </div>
                            <div class="stat-card">
                                <h3>Tổng Giá Trị</h3>
                                <p><?php echo number_format($totals['total_amount']); ?> VNĐ</p>
                            </div>
                        </div>
            <?php endif; ?>

            <?php if (!isset($_GET['mode']) || $_GET['mode'] !== 'batch'): ?>
                        <!-- Filter Form -->
                        <form method="GET" action="stock_in.php" class="filter-form">
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
                                <label for="filter_supplier">Nhà Cung Cấp</label>
                                <select id="filter_supplier" name="filter_supplier" class="form-control">
                                    <option value="0">-- Tất cả --</option>
                                    <?php 
                                    $suppliers_result->data_seek(0);
                                    while ($supplier = $suppliers_result->fetch_assoc()): 
                                    ?>
                                        <option value="<?php echo $supplier['supplier_id']; ?>" <?php echo $filter_supplier == $supplier['supplier_id'] ? 'selected' : ''; ?>>
                                            <?php echo $supplier['supplier_name']; ?>
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
                                <a href="stock_in.php" class="btn btn-secondary">Reset</a>
                                <a href="stock_in.php?<?php echo http_build_query($_GET); ?>&export=excel" class="btn btn-success">Xuất Excel</a>
                            </div>
                        </form>
                    <?php endif; ?>

                    <!-- Transactions Table -->
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Mã</th>
                                    <th>Nguyên Liệu</th>
                                    <th>SL</th>
                                    <th>Đơn Giá</th>
                                    <th>Thành Tiền</th>
                                    <th>Thời Gian</th>
                                    <th>Ghi Chú</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($transactions_result->num_rows > 0): ?>
                                    <?php while ($transaction = $transactions_result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $transaction['transaction_id']; ?></td>
                                            <td><?php echo $transaction['material_name']; ?> 
                                                <span class="text-muted">(<?php echo $transaction['supplier_name']; ?>)</span>
                                            </td>
                                            <td><?php echo $transaction['quantity']; ?> <?php echo $transaction['unit']; ?></td>
                                            <td><?php echo number_format($transaction['unit_price']); ?></td>
                                            <td><?php echo number_format($transaction['total_amount']); ?></td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($transaction['transaction_datetime'])); ?></td>
                                            <td><?php echo $transaction['note']; ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="7" class="text-center">Không có dữ liệu</td></tr>
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
    </div>
    
    <!-- JavaScript for Stock Management -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Material and Supplier Association for single mode
            const materialSelect = document.getElementById('material_id');
            const supplierSelect = document.getElementById('supplier_id');
            
            if (materialSelect && supplierSelect) {
                materialSelect.addEventListener('change', function() {
                    const selectedOption = materialSelect.options[materialSelect.selectedIndex];
                    const supplierId = selectedOption.getAttribute('data-supplier');
                    
                    if (supplierId) {
                        for (let i = 0; i < supplierSelect.options.length; i++) {
                            if (supplierSelect.options[i].value === supplierId) {
                                supplierSelect.selectedIndex = i;
                                break;
                            }
                        }
                    }
                });
            }
            
            // Price formatting for all price inputs
            const priceInputs = document.querySelectorAll('.price-input');
            
            function formatPrice(input) {
                // Remove all non-digit characters except dots and commas
                let value = input.value.replace(/[^\d.,]/g, '');
                
                // Replace all commas with dots for consistency
                value = value.replace(/,/g, '.');
                
                // Only keep the first dot (period) and remove the rest
                const parts = value.split('.');
                if (parts.length > 1) {
                    value = parts[0] + '.' + parts.slice(1).join('');
                }
                
                // Remove any non-digit for numeric calculation
                const numericValue = value.replace(/[^\d]/g, '');
                
                // Format with thousands separator
                const formattedValue = numericValue.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                
                // Set the formatted value back to the input
                input.value = formattedValue;
                
                // Store the raw value as a data attribute for form submission
                input.setAttribute('data-value', numericValue);
            }
            
            priceInputs.forEach(input => {
                // Initial formatting
                formatPrice(input);
                
                // Format on input
                input.addEventListener('input', function() {
                    formatPrice(this);
                });
                
                // Format on blur (in case they leave the field without input event firing)
                input.addEventListener('blur', function() {
                    formatPrice(this);
                });
            });
            
            // Process form submission to convert formatted prices back to numbers
            const batchForm = document.querySelector('form[action="?mode=batch"]');
            const singleForm = document.querySelector('form[action="stock_in.php"]');
            
            function processFormSubmit(form) {
                if (!form) return;
                
                form.addEventListener('submit', function(e) {
                    const priceInputs = form.querySelectorAll('.price-input');
                    
                    priceInputs.forEach(input => {
                        // Remove all non-digit characters to get the numeric value
                        const numericValue = input.value.replace(/[^\d]/g, '');
                        // Set the raw value for submission
                        input.value = numericValue;
                    });
                });
            }
            
            processFormSubmit(batchForm);
            processFormSubmit(singleForm);
            
            // Batch mode enhancements
            const quantityInputs = document.querySelectorAll('.quantity-input');
            
            if (quantityInputs.length > 0) {
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
                quantityInputs.forEach((input, index) => {
                    input.addEventListener('keydown', function(e) {
                        if (e.key === 'Enter' || e.key === 'ArrowDown') {
                            e.preventDefault();
                            // Move to next input
                            if (index < quantityInputs.length - 1) {
                                quantityInputs[index + 1].focus();
                            }
                        } else if (e.key === 'ArrowUp') {
                            e.preventDefault();
                            // Move to previous input
                            if (index > 0) {
                                quantityInputs[index - 1].focus();
                            }
                        }
                    });
                });
            }
        });
    </script>
</body>
</html> 