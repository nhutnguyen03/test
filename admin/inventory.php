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

// Pagination for stock entries
$entries_per_page = 10;
$entries_page = isset($_GET['entries_page']) ? (int)$_GET['entries_page'] : 1;
$entries_offset = ($entries_page - 1) * $entries_per_page;
$entries_total_query = "SELECT COUNT(*) FROM Stock_Entries";
$entries_total_result = $conn->query($entries_total_query)->fetch_row()[0];
$entries_total_pages = ceil($entries_total_result / $entries_per_page);
$entries_query = "SELECT se.*, m.material_name, s.supplier_name 
                  FROM Stock_Entries se 
                  JOIN Materials m ON se.material_id = m.material_id 
                  JOIN Suppliers s ON se.supplier_id = s.supplier_id 
                  ORDER BY se.entry_datetime DESC LIMIT $entries_offset, $entries_per_page";
$entries_result = $conn->query($entries_query);

// Pagination for stock usages
$usages_per_page = 10;
$usages_page = isset($_GET['usages_page']) ? (int)$_GET['usages_page'] : 1;
$usages_offset = ($usages_page - 1) * $usages_per_page;
$usages_total_query = "SELECT COUNT(*) FROM Stock_Usages";
$usages_total_result = $conn->query($usages_total_query)->fetch_row()[0];
$usages_total_pages = ceil($usages_total_result / $usages_per_page);
$usages_query = "SELECT su.*, m.material_name 
                 FROM Stock_Usages su 
                 JOIN Materials m ON su.material_id = m.material_id 
                 ORDER BY su.usage_date DESC LIMIT $usages_offset, $usages_per_page";
$usages_result = $conn->query($usages_query);

// Export to Excel
if (isset($_GET['export'])) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $_GET['export'] . '_export_' . date('Ymd') . '.xls"');
    
    if ($_GET['export'] == 'materials') {
        echo "ID\tTên\tNhà Cung Cấp\tĐơn Vị\tTồn Kho\tTrạng Thái\n";
        $materials_export_query = "SELECT m.material_id, m.material_name, s.supplier_name, m.unit, m.stock_quantity, m.status 
                                   FROM Materials m JOIN Suppliers s ON m.supplier_id = s.supplier_id $materials_where";
        $materials_export_result = $conn->query($materials_export_query);
        while ($row = $materials_export_result->fetch_assoc()) {
            echo implode("\t", [$row['material_id'], $row['material_name'], $row['supplier_name'], $row['unit'], $row['stock_quantity'], $row['status']]) . "\n";
        }
    } elseif ($_GET['export'] == 'entries') {
        echo "ID\tNguyên Vật Liệu\tNhà Cung Cấp\tSố Lượng\tĐơn Giá\tTổng Chi Phí\tThời Gian\n";
        $entries_export_query = "SELECT se.entry_id, m.material_name, s.supplier_name, se.quantity, se.cost_price, se.total_cost, se.entry_datetime 
                                 FROM Stock_Entries se JOIN Materials m ON se.material_id = m.material_id JOIN Suppliers s ON se.supplier_id = s.supplier_id";
        $entries_export_result = $conn->query($entries_export_query);
        while ($row = $entries_export_result->fetch_assoc()) {
            echo implode("\t", [$row['entry_id'], $row['material_name'], $row['supplier_name'], $row['quantity'], $row['cost_price'], $row['total_cost'], date('d/m/Y H:i', strtotime($row['entry_datetime']))]) . "\n";
        }
    } elseif ($_GET['export'] == 'usages') {
        echo "ID\tNguyên Vật Liệu\tSố Lượng\tCa Làm Việc\tThời Gian\tGhi Chú\n";
        $usages_export_query = "SELECT su.usage_id, m.material_name, su.quantity_used, su.shift_id, su.usage_date, su.note 
                                FROM Stock_Usages su JOIN Materials m ON su.material_id = m.material_id";
        $usages_export_result = $conn->query($usages_export_query);
        while ($row = $usages_export_result->fetch_assoc()) {
            echo implode("\t", [$row['usage_id'], $row['material_name'], $row['quantity_used'], $row['shift_id'], date('d/m/Y H:i', strtotime($row['usage_date'])), $row['note']]) . "\n";
        }
    }
    exit;
}

// Process material form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_material'])) {
        $material_name = sanitize($_POST['material_name']);
        $supplier_id = (int)$_POST['supplier_id'];
        $unit = sanitize($_POST['unit']);
        $stock_quantity = (int)$_POST['stock_quantity'];
        $status = $stock_quantity > 0 ? 'Còn hàng' : 'Hết hàng';
        
        if (empty($material_name) || $supplier_id <= 0 || empty($unit) || $stock_quantity < 0) {
            $error = "Vui lòng điền đầy đủ thông tin nguyên vật liệu";
        } else {
            $insert_query = "INSERT INTO Materials (material_name, supplier_id, unit, stock_quantity, status) 
                           VALUES (?, ?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bind_param("sisis", $material_name, $supplier_id, $unit, $stock_quantity, $status);
            
            if ($insert_stmt->execute()) {
                $success = "Thêm nguyên vật liệu thành công";
                $materials_result = $conn->query($materials_query);
            } else {
                $error = "Lỗi khi thêm nguyên vật liệu: " . $conn->error;
            }
        }
    } elseif (isset($_POST['update_material'])) {
        $material_id = (int)$_POST['material_id'];
        $material_name = sanitize($_POST['material_name']);
        $supplier_id = (int)$_POST['supplier_id'];
        $unit = sanitize($_POST['unit']);
        
        if ($material_id <= 0 || empty($material_name) || $supplier_id <= 0 || empty($unit)) {
            $error = "Vui lòng điền đầy đủ thông tin nguyên vật liệu";
        } else {
            $update_query = "UPDATE Materials SET material_name = ?, supplier_id = ?, unit = ? 
                           WHERE material_id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("sissi", $material_name, $supplier_id, $unit, $material_id);
            
            if ($update_stmt->execute()) {
                $success = "Cập nhật nguyên vật liệu thành công";
                $materials_result = $conn->query($materials_query);
            } else {
                $error = "Lỗi khi cập nhật nguyên vật liệu: " . $conn->error;
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
    } elseif (isset($_POST['add_entry'])) {
        $supplier_id = (int)$_POST['supplier_id'];
        $material_id = (int)$_POST['material_id'];
        $quantity = (int)$_POST['quantity'];
        $cost_price = (float)$_POST['cost_price'];
        
        if ($supplier_id <= 0 || $material_id <= 0 || $quantity <= 0 || $cost_price <= 0) {
            $error = "Vui lòng điền đầy đủ thông tin phiếu nhập";
        } else {
            $insert_entry_query = "INSERT INTO Stock_Entries (supplier_id, material_id, quantity, cost_price) 
                                 VALUES (?, ?, ?, ?)";
            $insert_entry_stmt = $conn->prepare($insert_entry_query);
            $insert_entry_stmt->bind_param("iiid", $supplier_id, $material_id, $quantity, $cost_price);
            
            if ($insert_entry_stmt->execute()) {
                $update_stock_query = "UPDATE Materials SET stock_quantity = stock_quantity + ?, 
                                    status = IF(stock_quantity + ? > 0, 'Còn hàng', 'Hết hàng') 
                                    WHERE material_id = ?";
                $update_stock_stmt = $conn->prepare($update_stock_query);
                $update_stock_stmt->bind_param("iii", $quantity, $quantity, $material_id);
                $update_stock_stmt->execute();
                
                $success = "Thêm phiếu nhập kho thành công";
                $materials_result = $conn->query($materials_query);
            } else {
                $error = "Lỗi khi thêm phiếu nhập kho: " . $conn->error;
            }
        }
    } elseif (isset($_POST['add_usage'])) {
        $material_id = (int)$_POST['material_id'];
        $quantity_used = (int)$_POST['quantity_used'];
        $shift_id = (int)$_POST['shift_id'];
        $note = sanitize($_POST['note']);
        
        if ($material_id <= 0 || $quantity_used <= 0 || $shift_id <= 0) {
            $error = "Vui lòng điền đầy đủ thông tin phiếu xuất";
        } else {
            $stock_check_query = "SELECT stock_quantity FROM Materials WHERE material_id = ?";
            $stock_check_stmt = $conn->prepare($stock_check_query);
            $stock_check_stmt->bind_param("i", $material_id);
            $stock_check_stmt->execute();
            $stock_result = $stock_check_stmt->get_result()->fetch_assoc();
            
            if ($stock_result['stock_quantity'] < $quantity_used) {
                $error = "Số lượng xuất vượt quá tồn kho";
            } else {
                $insert_usage_query = "INSERT INTO Stock_Usages (material_id, quantity_used, shift_id, note) 
                                     VALUES (?, ?, ?, ?)";
                $insert_usage_stmt = $conn->prepare($insert_usage_query);
                $insert_usage_stmt->bind_param("iiis", $material_id, $quantity_used, $shift_id, $note);
                
                if ($insert_usage_stmt->execute()) {
                    $update_stock_query = "UPDATE Materials SET stock_quantity = stock_quantity - ?, 
                                        status = IF(stock_quantity - ? > 0, 'Còn hàng', 'Hết hàng') 
                                        WHERE material_id = ?";
                    $update_stock_stmt = $conn->prepare($update_stock_query);
                    $update_stock_stmt->bind_param("iii", $quantity_used, $quantity_used, $material_id);
                    $update_stock_stmt->execute();
                    
                    $success = "Thêm phiếu xuất kho thành công";
                    $materials_result = $conn->query($materials_query);
                } else {
                    $error = "Lỗi khi thêm phiếu xuất kho: " . $conn->error;
                }
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
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Kho Hàng - Hệ Thống Quản Lý Quán Cà Phê</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .tabs {
            overflow: hidden;
            border-bottom: 2px solid #ddd;
            margin-bottom: 20px;
        }
        .tabs button {
            background-color: #f1f1f1;
            float: left;
            border: none;
            outline: none;
            cursor: pointer;
            padding: 12px 20px;
            transition: 0.3s;
            font-size: 16px;
            border-radius: 5px 5px 0 0;
            margin-right: 5px;
        }
        .tabs button:hover {
            background-color: #ddd;
        }
        .tabs button.active {
            background-color: #4CAF50;
            color: white;
        }
        .tab-content {
            display: none;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 0 5px 5px 5px;
        }
        .tab-content.active {
            display: block;
        }
        .pagination {
            margin-top: 20px;
            text-align: center;
        }
        .pagination a {
            padding: 8px 16px;
            text-decoration: none;
            color: #4CAF50;
            border: 1px solid #ddd;
            margin: 0 4px;
        }
        .pagination a.active {
            background-color: #4CAF50;
            color: white;
        }
        .export-btn {
            float: right;
            margin-bottom: 10px;
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
            
            <div class="row">
                <!-- Left Column: Forms -->
                <div class="col-md-4">
                    <div class="card">
                        <h2><?php echo $edit_material ? 'Cập Nhật Nguyên Vật Liệu' : 'Thêm Nguyên Vật Liệu Mới'; ?></h2>
                        <form method="POST" action="inventory.php">
                            <?php if ($edit_material): ?>
                                <input type="hidden" name="material_id" value="<?php echo $edit_material['material_id']; ?>">
                            <?php endif; ?>
                            <div class="form-group">
                                <label for="material_name">Tên Nguyên Vật Liệu</label>
                                <input type="text" id="material_name" name="material_name" value="<?php echo $edit_material ? $edit_material['material_name'] : ''; ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="supplier_id">Nhà Cung Cấp</label>
                                <select id="supplier_id" name="supplier_id" required>
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
                                <input type="text" id="unit" name="unit" value="<?php echo $edit_material ? $edit_material['unit'] : ''; ?>" required>
                            </div>
                            <?php if (!$edit_material): ?>
                                <div class="form-group">
                                    <label for="stock_quantity">Số Lượng Tồn Ban Đầu</label>
                                    <input type="number" id="stock_quantity" name="stock_quantity" min="0" value="0" required>
                                </div>
                            <?php endif; ?>
                            <?php if ($edit_material): ?>
                                <button type="submit" name="update_material" class="btn btn-primary btn-block">Cập Nhật</button>
                                <a href="inventory.php" class="btn btn-secondary btn-block">Hủy</a>
                            <?php else: ?>
                                <button type="submit" name="add_material" class="btn btn-primary btn-block">Thêm Nguyên Vật Liệu</button>
                            <?php endif; ?>
                        </form>
                    </div>

                    <div class="card mt-3">
                        <h2>Nhập Kho</h2>
                        <form method="POST" action="inventory.php">
                            <div class="form-group">
                                <label for="entry_supplier_id">Nhà Cung Cấp</label>
                                <select id="entry_supplier_id" name="supplier_id" required>
                                    <option value="">-- Chọn Nhà Cung Cấp --</option>
                                    <?php $suppliers_result->data_seek(0); while ($supplier = $suppliers_result->fetch_assoc()): ?>
                                        <option value="<?php echo $supplier['supplier_id']; ?>"><?php echo $supplier['supplier_name']; ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="entry_material_id">Nguyên Vật Liệu</label>
                                <select id="entry_material_id" name="material_id" required>
                                    <option value="">-- Chọn Nguyên Vật Liệu --</option>
                                    <?php $materials_result->data_seek(0); while ($material = $materials_result->fetch_assoc()): ?>
                                        <option value="<?php echo $material['material_id']; ?>"><?php echo $material['material_name']; ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="quantity">Số Lượng</label>
                                <input type="number" id="quantity" name="quantity" min="1" required>
                            </div>
                            <div class="form-group">
                                <label for="cost_price">Đơn Giá</label>
                                <input type="number" id="cost_price" name="cost_price" min="0" step="1000" required>
                            </div>
                            <button type="submit" name="add_entry" class="btn btn-primary btn-block">Nhập Kho</button>
                        </form>
                    </div>

                    <div class="card mt-3">
                        <h2>Xuất Kho</h2>
                        <form method="POST" action="inventory.php">
                            <div class="form-group">
                                <label for="usage_material_id">Nguyên Vật Liệu</label>
                                <select id="usage_material_id" name="material_id" required>
                                    <option value="">-- Chọn Nguyên Vật Liệu --</option>
                                    <?php $materials_result->data_seek(0); while ($material = $materials_result->fetch_assoc()): ?>
                                        <option value="<?php echo $material['material_id']; ?>"><?php echo $material['material_name']; ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="quantity_used">Số Lượng Xuất</label>
                                <input type="number" id="quantity_used" name="quantity_used" min="1" required>
                            </div>
                            <div class="form-group">
                                <label for="shift_id">Ca Làm Việc</label>
                                <input type="number" id="shift_id" name="shift_id" min="1" required placeholder="Nhập ID ca làm việc">
                            </div>
                            <div class="form-group">
                                <label for="note">Ghi Chú</label>
                                <textarea id="note" name="note" rows="2"></textarea>
                            </div>
                            <button type="submit" name="add_usage" class="btn btn-primary btn-block">Xuất Kho</button>
                        </form>
                    </div>
                </div>
                
                <!-- Right Column: Tabs -->
                <div class="col-md-8">
                    <div class="tabs">
                        <button class="tablinks active" onclick="showTab('materials')">Nguyên Vật Liệu</button>
                        <button class="tablinks" onclick="showTab('history')">Lịch Sử Nhập/Xuất</button>
                    </div>

                    <!-- Materials Tab -->
                    <div id="materials" class="tab-content active">
                        <div class="card">
                            <h2>Danh Sách Nguyên Vật Liệu <a href="inventory.php?export=materials" class="btn btn-success btn-sm export-btn">Xuất Excel</a></h2>
                            <form method="GET" action="inventory.php" class="mb-3">
                                <div class="row">
                                    <div class="col-md-6">
                                        <label for="filter_supplier">Lọc theo Nhà Cung Cấp</label>
                                        <select id="filter_supplier" name="filter_supplier" onchange="this.form.submit()">
                                            <option value="0">-- Tất cả --</option>
                                            <?php $suppliers_result->data_seek(0); while ($supplier = $suppliers_result->fetch_assoc()): ?>
                                                <option value="<?php echo $supplier['supplier_id']; ?>" <?php echo $filter_supplier == $supplier['supplier_id'] ? 'selected' : ''; ?>>
                                                    <?php echo $supplier['supplier_name']; ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="filter_status">Lọc theo Trạng Thái</label>
                                        <select id="filter_status" name="filter_status" onchange="this.form.submit()">
                                            <option value="">-- Tất cả --</option>
                                            <option value="Còn hàng" <?php echo $filter_status == 'Còn hàng' ? 'selected' : ''; ?>>Còn hàng</option>
                                            <option value="Hết hàng" <?php echo $filter_status == 'Hết hàng' ? 'selected' : ''; ?>>Hết hàng</option>
                                        </select>
                                    </div>
                                </div>
                            </form>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Tên</th>
                                        <th>Nhà Cung Cấp</th>
                                        <th>Đơn Vị</th>
                                        <th>Tồn Kho</th>
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
                                                <td><?php echo $material['stock_quantity']; ?></td>
                                                <td><span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $material['status'])); ?>"><?php echo $material['status']; ?></span></td>
                                                <td>
                                                    <a href="inventory.php?edit=<?php echo $material['material_id']; ?>" class="btn btn-secondary btn-sm">Sửa</a>
                                                    <form method="POST" action="inventory.php" style="display:inline;" onsubmit="return confirm('Bạn có chắc muốn xóa nguyên vật liệu này?');">
                                                        <input type="hidden" name="material_id" value="<?php echo $material['material_id']; ?>">
                                                        <button type="submit" name="delete_material" class="btn btn-danger btn-sm">Xóa</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr><td colspan="7" class="text-center">Không có nguyên vật liệu nào</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- History Tab -->
                    <div id="history" class="tab-content">
                        <div class="card">
                            <h2>Lịch Sử Nhập Kho <a href="inventory.php?export=entries" class="btn btn-success btn-sm export-btn">Xuất Excel</a></h2>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nguyên Vật Liệu</th>
                                        <th>Nhà Cung Cấp</th>
                                        <th>Số Lượng</th>
                                        <th>Đơn Giá</th>
                                        <th>Tổng Chi Phí</th>
                                        <th>Thời Gian</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($entries_result->num_rows > 0): ?>
                                        <?php while ($entry = $entries_result->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo $entry['entry_id']; ?></td>
                                                <td><?php echo $entry['material_name']; ?></td>
                                                <td><?php echo $entry['supplier_name']; ?></td>
                                                <td><?php echo $entry['quantity']; ?></td>
                                                <td><?php echo formatCurrency($entry['cost_price']); ?></td>
                                                <td><?php echo formatCurrency($entry['total_cost']); ?></td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($entry['entry_datetime'])); ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr><td colspan="7" class="text-center">Không có lịch sử nhập kho</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                            <div class="pagination">
                                <?php for ($i = 1; $i <= $entries_total_pages; $i++): ?>
                                    <a href="inventory.php?entries_page=<?php echo $i; ?>" class="<?php echo $i == $entries_page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <div class="card mt-3">
                            <h2>Lịch Sử Xuất Kho <a href="inventory.php?export=usages" class="btn btn-success btn-sm export-btn">Xuất Excel</a></h2>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nguyên Vật Liệu</th>
                                        <th>Số Lượng</th>
                                        <th>Ca Làm Việc</th>
                                        <th>Thời Gian</th>
                                        <th>Ghi Chú</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($usages_result->num_rows > 0): ?>
                                        <?php while ($usage = $usages_result->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo $usage['usage_id']; ?></td>
                                                <td><?php echo $usage['material_name']; ?></td>
                                                <td><?php echo $usage['quantity_used']; ?></td>
                                                <td><?php echo $usage['shift_id']; ?></td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($usage['usage_date'])); ?></td>
                                                <td><?php echo $usage['note']; ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr><td colspan="6" class="text-center">Không có lịch sử xuất kho</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                            <div class="pagination">
                                <?php for ($i = 1; $i <= $usages_total_pages; $i++): ?>
                                    <a href="inventory.php?usages_page=<?php echo $i; ?>" class="<?php echo $i == $usages_page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.tablinks').forEach(btn => btn.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            document.querySelector(`button[onclick="showTab('${tabId}')"]`).classList.add('active');
        }
    </script>
</body>
</html>