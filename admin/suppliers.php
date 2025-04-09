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

// Process supplier form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_supplier'])) {
        $supplier_name = sanitize($_POST['supplier_name']);
        $contact_name = sanitize($_POST['contact_name']);
        $phone = sanitize($_POST['phone']);
        $email = sanitize($_POST['email']);
        $address = sanitize($_POST['address']);
        $status = 'Hoạt động';
        
        if (empty($supplier_name) || empty($phone)) {
            $error = "Vui lòng điền tên nhà cung cấp và số điện thoại";
        } else {
            $insert_query = "INSERT INTO Suppliers (supplier_name, contact_name, phone, email, address, status) 
                           VALUES (?, ?, ?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bind_param("ssssss", $supplier_name, $contact_name, $phone, $email, $address, $status);
            
            if ($insert_stmt->execute()) {
                $success = "Thêm nhà cung cấp thành công";
            } else {
                $error = "Lỗi khi thêm nhà cung cấp: " . $conn->error;
            }
        }
    } elseif (isset($_POST['update_supplier'])) {
        $supplier_id = (int)$_POST['supplier_id'];
        $supplier_name = sanitize($_POST['supplier_name']);
        $contact_person = sanitize($_POST['contact_name']);
        $phone = sanitize($_POST['phone']);
        $email = sanitize($_POST['email']);
        $address = sanitize($_POST['address']);
        $status = sanitize($_POST['status']);
        
        if (empty($supplier_name) || empty($phone) || $supplier_id <= 0) {
            $error = "Vui lòng điền đầy đủ thông tin nhà cung cấp";
        } else {
            $update_query = "UPDATE Suppliers SET supplier_name = ?, contact_person = ?, phone = ?, 
                           email = ?, address = ?, status = ? WHERE supplier_id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("ssssssi", $supplier_name, $contact_person, $phone, $email, $address, $status, $supplier_id);
            
            if ($update_stmt->execute()) {
                $success = "Cập nhật nhà cung cấp thành công";
            } else {
                $error = "Lỗi khi cập nhật nhà cung cấp: " . $conn->error;
            }
        }
    } elseif (isset($_POST['delete_supplier'])) {
        $supplier_id = (int)$_POST['supplier_id'];
        
        // Check if supplier is used in materials
        $check_query = "SELECT COUNT(*) as count FROM Materials WHERE supplier_id = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("i", $supplier_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result()->fetch_assoc();
        
        if ($check_result['count'] > 0) {
            $error = "Không thể xóa nhà cung cấp này vì đang được sử dụng trong danh sách nguyên liệu";
        } else {
            $delete_query = "DELETE FROM Suppliers WHERE supplier_id = ?";
            $delete_stmt = $conn->prepare($delete_query);
            $delete_stmt->bind_param("i", $supplier_id);
            
            if ($delete_stmt->execute()) {
                $success = "Xóa nhà cung cấp thành công";
            } else {
                $error = "Lỗi khi xóa nhà cung cấp: " . $conn->error;
            }
        }
    }
}

// Get supplier list
$filter_status = isset($_GET['filter_status']) ? sanitize($_GET['filter_status']) : '';
$search_term = isset($_GET['search']) ? sanitize($_GET['search']) : '';

$suppliers_where = "WHERE 1=1";
if (!empty($filter_status)) {
    $suppliers_where .= " AND status = '$filter_status'";
}
if (!empty($search_term)) {
    $suppliers_where .= " AND (supplier_name LIKE '%$search_term%' OR contact_name LIKE '%$search_term%' OR phone LIKE '%$search_term%')";
}

$suppliers_query = "SELECT * FROM Suppliers $suppliers_where ORDER BY supplier_name";
$suppliers_result = $conn->query($suppliers_query);

// Get supplier details for edit
$edit_supplier = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $supplier_id = (int)$_GET['edit'];
    $edit_query = "SELECT * FROM Suppliers WHERE supplier_id = ?";
    $edit_stmt = $conn->prepare($edit_query);
    $edit_stmt->bind_param("i", $supplier_id);
    $edit_stmt->execute();
    $edit_result = $edit_stmt->get_result();
    
    if ($edit_result->num_rows > 0) {
        $edit_supplier = $edit_result->fetch_assoc();
    }
}

// Get some basic supplier statistics
$active_suppliers_query = "SELECT COUNT(*) as count FROM Suppliers WHERE status = 'Hoạt động'";
$active_suppliers_result = $conn->query($active_suppliers_query)->fetch_assoc();

$inactive_suppliers_query = "SELECT COUNT(*) as count FROM Suppliers WHERE status = 'Ngừng hoạt động'";
$inactive_suppliers_result = $conn->query($inactive_suppliers_query)->fetch_assoc();

$total_suppliers_query = "SELECT COUNT(*) as count FROM Suppliers";
$total_suppliers_result = $conn->query($total_suppliers_query)->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Nhà Cung Cấp - Hệ Thống Quản Lý Quán Cà Phê</title>
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
        .supplier-stats {
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
        .stat-card.inactive p {
            color: #f44336;
        }
        .search-bar {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .search-bar input[type="text"] {
            flex: 1;
            padding: 8px 16px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .search-bar select {
            padding: 8px 16px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .search-bar button {
            padding: 8px 16px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
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
                <h1>Quản Lý Nhà Cung Cấp</h1>
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
                <a href="suppliers.php" class="active">Nhà Cung Cấp</a>
                <a href="stock_in.php">Nhập Kho</a>
                <a href="stock_out.php">Xuất Kho</a>
            </div>
            
            <!-- Supplier Statistics -->
            <div class="supplier-stats">
                <div class="stat-card">
                    <h3>Tổng Số Nhà Cung Cấp</h3>
                    <p><?php echo $total_suppliers_result['count']; ?></p>
                </div>
                <div class="stat-card">
                    <h3>Đang Hoạt Động</h3>
                    <p><?php echo $active_suppliers_result['count']; ?></p>
                </div>
                <div class="stat-card inactive">
                    <h3>Ngừng Hoạt Động</h3>
                    <p><?php echo $inactive_suppliers_result['count']; ?></p>
                </div>
            </div>
            
            <div class="row">
                <!-- Suppliers List -->
                <div class="col-md-<?php echo $edit_supplier ? '8' : '12'; ?>">
                    <div class="card">
                        <div class="card-header">
                            <h2>Danh Sách Nhà Cung Cấp</h2>
                            <a href="?quick_action=add_supplier" class="btn btn-primary">Thêm Nhà Cung Cấp</a>
                        </div>
                        
                        <!-- Search and Filter -->
                        <form method="GET" action="suppliers.php" class="search-bar">
                            <input type="text" name="search" placeholder="Tìm kiếm..." value="<?php echo $search_term; ?>">
                            <select name="filter_status">
                                <option value="">-- Tất cả trạng thái --</option>
                                <option value="Hoạt động" <?php echo $filter_status == 'Hoạt động' ? 'selected' : ''; ?>>Hoạt động</option>
                                <option value="Ngừng hoạt động" <?php echo $filter_status == 'Ngừng hoạt động' ? 'selected' : ''; ?>>Ngừng hoạt động</option>
                            </select>
                            <button type="submit">Lọc</button>
                        </form>
                        
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Tên Nhà Cung Cấp</th>
                                    <th>Người Liên Hệ</th>
                                    <th>Điện Thoại</th>
                                    <th>Email</th>
                                    <th>Địa Chỉ</th>
                                    <th>Trạng Thái</th>
                                    <th>Thao Tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($suppliers_result->num_rows > 0): ?>
                                    <?php while ($supplier = $suppliers_result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $supplier['supplier_id']; ?></td>
                                            <td><?php echo $supplier['supplier_name']; ?></td>
                                            <td><?php echo $supplier['contact_person']; ?></td>
                                            <td><?php echo $supplier['phone']; ?></td>
                                            <td><?php echo $supplier['email']; ?></td>
                                            <td><?php echo $supplier['address']; ?></td>
                                            <td>
                                                <span class="status-badge-supplier status-<?php echo strtolower(str_replace(' ', '-', $supplier['status'])); ?>">
                                                    <?php echo $supplier['status']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="?edit=<?php echo $supplier['supplier_id']; ?>" class="btn btn-warning btn-sm">Sửa</a>
                                                <form method="POST" action="suppliers.php" style="display:inline;" onsubmit="return confirm('Bạn có chắc muốn xóa nhà cung cấp này?');">
                                                    <input type="hidden" name="supplier_id" value="<?php echo $supplier['supplier_id']; ?>">
                                                    <button type="submit" name="delete_supplier" class="btn btn-danger btn-sm">Xóa</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="8" class="text-center">Không có nhà cung cấp nào</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Edit/Add Supplier Form -->
                <?php if ($edit_supplier || isset($_GET['quick_action']) && $_GET['quick_action'] == 'add_supplier'): ?>
                <div class="col-md-4">
                    <div class="card">
                        <h2><?php echo $edit_supplier ? 'Cập Nhật Nhà Cung Cấp' : 'Thêm Nhà Cung Cấp Mới'; ?></h2>
                        <form method="POST" action="suppliers.php">
                            <?php if ($edit_supplier): ?>
                                <input type="hidden" name="supplier_id" value="<?php echo $edit_supplier['supplier_id']; ?>">
                            <?php endif; ?>
                            
                            <div class="form-group">
                                <label for="supplier_name">Tên Nhà Cung Cấp</label>
                                <input type="text" id="supplier_name" name="supplier_name" class="form-control" 
                                    value="<?php echo $edit_supplier ? $edit_supplier['supplier_name'] : ''; ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="contact_name">Người Liên Hệ</label>
                                <input type="text" id="contact_name" name="contact_name" class="form-control"
                                    value="<?php echo $edit_supplier ? $edit_supplier['contact_person'] : ''; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="phone">Điện Thoại</label>
                                <input type="text" id="phone" name="phone" class="form-control"
                                    value="<?php echo $edit_supplier ? $edit_supplier['phone'] : ''; ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" class="form-control"
                                    value="<?php echo $edit_supplier ? $edit_supplier['email'] : ''; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="address">Địa Chỉ</label>
                                <textarea id="address" name="address" class="form-control" rows="3"><?php echo $edit_supplier ? $edit_supplier['address'] : ''; ?></textarea>
                            </div>
                            
                            <?php if ($edit_supplier): ?>
                                <div class="form-group">
                                    <label for="status">Trạng Thái</label>
                                    <select id="status" name="status" class="form-control" required>
                                        <option value="Hoạt động" <?php echo $edit_supplier['status'] == 'Hoạt động' ? 'selected' : ''; ?>>Hoạt động</option>
                                        <option value="Ngừng hoạt động" <?php echo $edit_supplier['status'] == 'Ngừng hoạt động' ? 'selected' : ''; ?>>Ngừng hoạt động</option>
                                    </select>
                                </div>
                                <button type="submit" name="update_supplier" class="btn btn-primary btn-block">Cập Nhật</button>
                            <?php else: ?>
                                <button type="submit" name="add_supplier" class="btn btn-primary btn-block">Thêm Nhà Cung Cấp</button>
                            <?php endif; ?>
                            <a href="suppliers.php" class="btn btn-secondary btn-block">Hủy</a>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html> 