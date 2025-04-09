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

// Get suppliers for dropdown
$suppliers_query = "SELECT * FROM Suppliers WHERE status = 'Hoạt động'";
$suppliers_result = $conn->query($suppliers_query);

// Filter materials
$filter_supplier = isset($_GET['filter_supplier']) ? (int)$_GET['filter_supplier'] : 0;
$filter_status = isset($_GET['filter_status']) ? sanitize($_GET['filter_status']) : '';
$materials_where = "WHERE 1=1";
if ($filter_supplier > 0) {
    $materials_where .= " AND m.supplier_id = $filter_supplier";
}
if (!empty($filter_status)) {
    $materials_where .= " AND m.status = '$filter_status'";
}
$materials_query = "SELECT m.*, s.supplier_name FROM Materials m 
                    JOIN Suppliers s ON m.supplier_id = s.supplier_id 
                    $materials_where ORDER BY m.material_name";
$materials_result = $conn->query($materials_query);

// Export to Excel
if (isset($_GET['export'])) {
    // Đặt header cho UTF-8
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="materials_export_' . date('Ymd') . '.xls"');
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
        <th>ID</th>
        <th>Tên</th>
        <th>Nhà Cung Cấp</th>
        <th>Đơn Vị</th>
        <th>Tồn Kho</th>
        <th>Tồn Cuối</th>
        <th>Sử Dụng</th>
    </tr>';
    
    // Get data
    $materials_export_query = "SELECT m.material_id, m.material_name, s.supplier_name, m.unit, m.stock_quantity 
                               FROM Materials m JOIN Suppliers s ON m.supplier_id = s.supplier_id $materials_where";
    $materials_export_result = $conn->query($materials_export_query);
    
    // Data rows
    while ($row = $materials_export_result->fetch_assoc()) {
        echo '<tr>';
        echo '<td>' . $row['material_id'] . '</td>';
        echo '<td>' . $row['material_name'] . '</td>';
        echo '<td>' . $row['supplier_name'] . '</td>';
        echo '<td>' . $row['unit'] . '</td>';
        echo '<td>' . $row['stock_quantity'] . '</td>';
        echo '<td></td>'; // Tồn cuối - để trống để người dùng điền sau khi kiểm kê
        echo '<td></td>'; // Sử dụng - để trống để người dùng điền sau khi kiểm kê
        echo '</tr>';
    }
    
    echo '</table>';
    echo '</body>';
    echo '</html>';
    exit;
}

// Process material form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_material'])) {
        $material_name = sanitize($_POST['material_name']);
        $supplier_id = (int)$_POST['supplier_id'];
        $unit = sanitize($_POST['unit']);
        $stock_quantity = (int)$_POST['stock_quantity'];
        $low_stock_threshold = (int)$_POST['low_stock_threshold'];
        
        if (empty($material_name) || $supplier_id <= 0 || empty($unit)) {
            $error = "Vui lòng điền đầy đủ thông tin nguyên liệu";
        } else {
            $insert_query = "INSERT INTO Materials (material_name, supplier_id, unit, stock_quantity, low_stock_threshold) 
                            VALUES (?, ?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bind_param("sisii", $material_name, $supplier_id, $unit, $stock_quantity, $low_stock_threshold);
            
            if ($insert_stmt->execute()) {
                $success = "Thêm nguyên liệu thành công";
                // Reset form
                $_POST = array();
            } else {
                $error = "Lỗi khi thêm nguyên liệu: " . $conn->error;
            }
        }
    } elseif (isset($_POST['update_material'])) {
        $material_id = (int)$_POST['material_id'];
        $material_name = sanitize($_POST['material_name']);
        $supplier_id = (int)$_POST['supplier_id'];
        $unit = sanitize($_POST['unit']);
        $stock_quantity = (int)$_POST['stock_quantity'];
        $low_stock_threshold = (int)$_POST['low_stock_threshold'];
        
        if (empty($material_name) || $supplier_id <= 0 || empty($unit)) {
            $error = "Vui lòng điền đầy đủ thông tin nguyên liệu";
        } else {
            $update_query = "UPDATE Materials SET 
                            material_name = ?, 
                            supplier_id = ?, 
                            unit = ?, 
                            stock_quantity = ?,
                            low_stock_threshold = ?,
                            status = IF(stock_quantity > 0, 'Còn hàng', 'Hết hàng')
                            WHERE material_id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("sisiii", $material_name, $supplier_id, $unit, $stock_quantity, $low_stock_threshold, $material_id);
            
            if ($update_stmt->execute()) {
                $success = "Cập nhật nguyên liệu thành công";
                // Reset edit mode
                unset($_GET['edit']);
            } else {
                $error = "Lỗi khi cập nhật nguyên liệu: " . $conn->error;
            }
        }
    } elseif (isset($_POST['delete_material'])) {
        $material_id = (int)$_POST['material_id'];
        
        if ($material_id > 0) {
            $delete_query = "DELETE FROM Materials WHERE material_id = ?";
            $delete_stmt = $conn->prepare($delete_query);
            $delete_stmt->bind_param("i", $material_id);
            
            if ($delete_stmt->execute()) {
                $success = "Xóa nguyên vật liệu thành công";
                $materials_result = $conn->query($materials_query);
            } else {
                $error = "Lỗi khi xóa nguyên vật liệu: " . $conn->error;
            }
        }
    }
}

// Get material details for edit
$edit_material = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $material_id = (int)$_GET['edit'];
    $edit_query = "SELECT * FROM Materials WHERE material_id = ?";
    $edit_stmt = $conn->prepare($edit_query);
    $edit_stmt->bind_param("i", $material_id);
    $edit_stmt->execute();
    $edit_result = $edit_stmt->get_result();
    
    if ($edit_result->num_rows > 0) {
        $edit_material = $edit_result->fetch_assoc();
    }
}

// Get low stock materials for dashboard view
$low_stock_query = "SELECT m.*, s.supplier_name 
                   FROM Materials m 
                   JOIN Suppliers s ON m.supplier_id = s.supplier_id 
                   WHERE m.stock_quantity < m.low_stock_threshold 
                   ORDER BY m.stock_quantity ASC
                   LIMIT 5";
$low_stock_result = $conn->query($low_stock_query);

// Get most recent stock transactions
$recent_transactions_query = "SELECT t.*, m.material_name, 
                            CASE t.transaction_type WHEN 'Nhập' THEN 'success' ELSE 'warning' END AS badge_class
                            FROM Stock_Transactions t
                            JOIN Materials m ON t.material_id = m.material_id
                            ORDER BY t.transaction_datetime DESC
                            LIMIT 5";
$recent_transactions_result = $conn->query($recent_transactions_query);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Kho Hàng - Hệ Thống Quản Lý Quán Cà Phê</title>
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
        .quick-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .quick-actions a {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 15px;
            background-color: #fff;
            border-radius: 5px;
            border: 1px solid #ddd;
            text-decoration: none;
            color: #333;
            transition: all 0.3s;
        }
        .quick-actions a:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .quick-actions a i {
            font-size: 24px;
            margin-bottom: 10px;
        }
        .dashboard-widgets {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 20px;
        }
        .widget {
            flex: 1;
            min-width: 250px;
            background-color: #fff;
            border-radius: 5px;
            border: 1px solid #ddd;
            padding: 15px;
        }
        .widget h3 {
            margin-top: 0;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        .transaction-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .transaction-badge {
            padding: 3px 8px;
            border-radius: 3px;
            color: white;
            font-size: 12px;
        }
        .transaction-badge.success {
            background-color: #4CAF50;
        }
        .transaction-badge.warning {
            background-color: #f0ad4e;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="container">
            <a href="dashboard.php" class="navbar-brand">Quản Lý Quán Cà Phê</a>
            <ul class="navbar-nav">
                <li class="nav-item"><a href="products.php" class="nav-link">Sản Phẩm</a></li>
                <li class="nav-item"><a href="categories.php" class="nav-link">Danh Mục</a></li>
                <li class="nav-item"><a href="inventory.php" class="nav-link active">Kho Hàng</a></li>
                <li class="nav-item"><a href="promotions.php" class="nav-link">Khuyến Mãi</a></li>
                <li class="nav-item"><a href="reports.php" class="nav-link">Báo Cáo</a></li>
                <li class="nav-item"><a href="users.php" class="nav-link">Nhân Viên</a></li>
                <li class="nav-item"><a href="../logout.php" class="nav-link">Đăng Xuất</a></li>
            </ul>
        </div>
    </nav>
    
    <!-- Main Content -->
    <div class="container">
        <div class="dashboard">
            <div class="dashboard-header">
                <h1>Quản Lý Kho Hàng</h1>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <!-- Inventory Navigation -->
            <div class="inventory-nav">
                <a href="inventory.php" class="active">Nguyên Liệu</a>
                <a href="suppliers.php">Nhà Cung Cấp</a>
                <a href="stock_in.php">Nhập Kho</a>
                <a href="stock_out.php">Xuất Kho</a>
            </div>
            
            <!-- Quick Actions -->
            <div class="quick-actions">
                <a href="?quick_action=add_material">
                    <i class="fa fa-plus-circle"></i>
                    Thêm Nguyên Liệu
                </a>
                <a href="stock_in.php?quick_action=add_stock">
                    <i class="fa fa-arrow-circle-down"></i>
                    Nhập Kho
                </a>
                <a href="stock_out.php?quick_action=use_stock">
                    <i class="fa fa-arrow-circle-up"></i>
                    Xuất Kho
                </a>
                <a href="?export=materials">
                    <i class="fa fa-file-excel"></i>
                    Xuất Excel
                </a>
            </div>
            
            <!-- Dashboard Widgets -->
            <div class="dashboard-widgets">
                <!-- Low Stock Widget -->
                <div class="widget">
                    <h3>Sắp Hết Hàng</h3>
                    <?php if ($low_stock_result->num_rows > 0): ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Tên Nguyên Liệu</th>
                                    <th>Tồn Kho</th>
                                    <th>Đơn Vị</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($material = $low_stock_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $material['material_name']; ?></td>
                                        <td><strong><?php echo $material['stock_quantity']; ?></strong></td>
                                        <td><?php echo $material['unit']; ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>Không có nguyên liệu nào sắp hết.</p>
                    <?php endif; ?>
                </div>
                
                <!-- Recent Transactions Widget -->
                <div class="widget">
                    <h3>Nhập/Xuất Kho Gần Đây</h3>
                    <?php if ($recent_transactions_result && $recent_transactions_result->num_rows > 0): ?>
                        <?php while ($transaction = $recent_transactions_result->fetch_assoc()): ?>
                            <div class="transaction-item">
                                <div>
                                    <span class="transaction-badge <?php echo $transaction['badge_class']; ?>">
                                        <?php echo $transaction['transaction_type']; ?>
                                    </span>
                                    <?php echo $transaction['material_name']; ?> - <?php echo $transaction['quantity']; ?> 
                                </div>
                                <div><?php echo date('d/m/Y H:i', strtotime($transaction['transaction_datetime'])); ?></div>
                            </div>
                        <?php endwhile; ?>
                        <div style="margin-top: 10px; text-align: right;">
                            <a href="stock_in.php">Xem tất cả giao dịch</a>
                        </div>
                    <?php else: ?>
                        <p>Không có giao dịch gần đây.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="row">
                <!-- Materials List -->
                <div class="col-md-<?php echo $edit_material ? '8' : '12'; ?>">
                    <div class="card">
                        <h2>Danh Sách Nguyên Liệu</h2>
                        <form method="GET" action="inventory.php" class="mb-3">
                            <div class="row">
                                <div class="col-md-5">
                                    <label for="filter_supplier">Nhà Cung Cấp</label>
                                    <select id="filter_supplier" name="filter_supplier" class="form-control">
                                        <option value="0">-- Tất cả --</option>
                                        <?php $suppliers_result->data_seek(0); while ($supplier = $suppliers_result->fetch_assoc()): ?>
                                            <option value="<?php echo $supplier['supplier_id']; ?>" <?php echo $filter_supplier == $supplier['supplier_id'] ? 'selected' : ''; ?>>
                                                <?php echo $supplier['supplier_name']; ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-5">
                                    <label for="filter_status">Trạng Thái</label>
                                    <select id="filter_status" name="filter_status" class="form-control">
                                        <option value="">-- Tất cả --</option>
                                        <option value="Còn hàng" <?php echo $filter_status == 'Còn hàng' ? 'selected' : ''; ?>>Còn hàng</option>
                                        <option value="Hết hàng" <?php echo $filter_status == 'Hết hàng' ? 'selected' : ''; ?>>Hết hàng</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label>&nbsp;</label>
                                    <button type="submit" class="btn btn-primary form-control">Lọc</button>
                                </div>
                            </div>
                        </form>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Tên Nguyên Liệu</th>
                                    <th>Nhà Cung Cấp</th>
                                    <th>Đơn Vị</th>
                                    <th>Tồn Kho</th>
                                    <th>Ngưỡng Cảnh Báo</th>
                                    <th>Trạng Thái</th>
                                    <th>Thao Tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($materials_result->num_rows > 0): ?>
                                    <?php while ($material = $materials_result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $material['material_id']; ?></td>
                                            <td><?php echo $material['material_name']; ?></td>
                                            <td><?php echo $material['supplier_name']; ?></td>
                                            <td><?php echo $material['unit']; ?></td>
                                            <td>
                                                <?php echo $material['stock_quantity']; ?>
                                                <?php if ($material['stock_quantity'] < $material['low_stock_threshold']): ?>
                                                    <span class="badge badge-warning">Sắp hết</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $material['low_stock_threshold']; ?></td>
                                            <td>
                                                <span class="status-badge-supplier status-<?php echo strtolower(str_replace(' ', '-', htmlspecialchars($material['status']))); ?>">
                                                    <?php echo $material['status']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="?edit=<?php echo $material['material_id']; ?>" class="btn btn-sm btn-primary">Sửa</a>
                                                <a href="stock_in.php?material_id=<?php echo $material['material_id']; ?>" class="btn btn-sm btn-success">Nhập Kho</a>
                                                <a href="stock_out.php?material_id=<?php echo $material['material_id']; ?>" class="btn btn-sm btn-warning">Xuất Kho</a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="7" class="text-center">Không có nguyên liệu nào</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Edit/Add Material Form -->
                <?php if ($edit_material || isset($_GET['quick_action']) && $_GET['quick_action'] == 'add_material'): ?>
                <div class="col-md-4">
                    <div class="card">
                        <h2><?php echo $edit_material ? 'Cập Nhật Nguyên Liệu' : 'Thêm Nguyên Liệu Mới'; ?></h2>
                        <form method="POST" action="inventory.php">
                            <?php if ($edit_material): ?>
                                <input type="hidden" name="material_id" value="<?php echo $edit_material['material_id']; ?>">
                            <?php endif; ?>
                            <div class="form-group">
                                <label for="material_name">Tên Nguyên Liệu</label>
                                <input type="text" id="material_name" name="material_name" class="form-control" value="<?php echo $edit_material ? $edit_material['material_name'] : ''; ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="supplier_id">Nhà Cung Cấp</label>
                                <select id="supplier_id" name="supplier_id" class="form-control" required>
                                    <option value="">-- Chọn Nhà Cung Cấp --</option>
                                    <?php $suppliers_result->data_seek(0); while ($supplier = $suppliers_result->fetch_assoc()): ?>
                                        <option value="<?php echo $supplier['supplier_id']; ?>" <?php echo ($edit_material && $edit_material['supplier_id'] == $supplier['supplier_id']) ? 'selected' : ''; ?>>
                                            <?php echo $supplier['supplier_name']; ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="unit">Đơn Vị</label>
                                <input type="text" id="unit" name="unit" class="form-control" value="<?php echo $edit_material ? $edit_material['unit'] : ''; ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="stock_quantity">Số Lượng Tồn Kho</label>
                                <input type="number" id="stock_quantity" name="stock_quantity" class="form-control" value="<?php echo isset($edit_material) ? $edit_material['stock_quantity'] : '0'; ?>" min="0" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="low_stock_threshold">Ngưỡng Cảnh Báo Sắp Hết Hàng</label>
                                <input type="number" id="low_stock_threshold" name="low_stock_threshold" class="form-control" value="<?php echo isset($edit_material) ? $edit_material['low_stock_threshold'] : '10'; ?>" min="1" required>
                                <small class="form-text text-muted">Khi số lượng tồn kho thấp hơn ngưỡng này, hệ thống sẽ hiển thị cảnh báo</small>
                            </div>
                            
                            <?php if ($edit_material): ?>
                                <button type="submit" name="update_material" class="btn btn-primary btn-block">Cập Nhật</button>
                                <a href="inventory.php" class="btn btn-secondary btn-block">Hủy</a>
                            <?php else: ?>
                                <button type="submit" name="add_material" class="btn btn-primary btn-block">Thêm Nguyên Liệu</button>
                                <a href="inventory.php" class="btn btn-secondary btn-block">Hủy</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>