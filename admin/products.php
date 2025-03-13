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

// Get categories for dropdown
$categories_query = "SELECT * FROM Categories WHERE status = 'Hoạt động' ORDER BY category_name";
$categories_result = $conn->query($categories_query);

// Get products with category name
$products_query = "SELECT p.*, c.category_name FROM Products p 
                  JOIN Categories c ON p.category_id = c.category_id 
                  ORDER BY p.category_id, p.product_name, p.size";
$products_result = $conn->query($products_query);

// Process product form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_product'])) {
        // Add new product
        $product_name = sanitize($_POST['product_name']);
        $category_id = (int)$_POST['category_id'];
        $size = sanitize($_POST['size']);
        $price = (float)$_POST['price'];
        $status = sanitize($_POST['status']);
        
        if (empty($product_name) || $category_id <= 0 || empty($size) || $price <= 0) {
            $error = "Vui lòng điền đầy đủ thông tin sản phẩm";
        } else {
            $insert_query = "INSERT INTO Products (product_name, category_id, size, price, status) 
                           VALUES (?, ?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bind_param("sisds", $product_name, $category_id, $size, $price, $status);
            
            if ($insert_stmt->execute()) {
                $success = "Thêm sản phẩm thành công";
                $products_result = $conn->query($products_query);
            } else {
                $error = "Lỗi khi thêm sản phẩm: " . $conn->error;
            }
        }
    } elseif (isset($_POST['update_product'])) {
        // Update product
        $product_id = (int)$_POST['product_id'];
        $product_name = sanitize($_POST['product_name']);
        $category_id = (int)$_POST['category_id'];
        $size = sanitize($_POST['size']);
        $price = (float)$_POST['price'];
        $status = sanitize($_POST['status']);
        
        if ($product_id <= 0 || empty($product_name) || $category_id <= 0 || empty($size) || $price <= 0) {
            $error = "Vui lòng điền đầy đủ thông tin sản phẩm";
        } else {
            $update_query = "UPDATE Products SET product_name = ?, category_id = ?, size = ?, price = ?, status = ? 
                           WHERE product_id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("sisdsi", $product_name, $category_id, $size, $price, $status, $product_id);
            
            if ($update_stmt->execute()) {
                $success = "Cập nhật sản phẩm thành công";
                $products_result = $conn->query($products_query);
            } else {
                $error = "Lỗi khi cập nhật sản phẩm: " . $conn->error;
            }
        }
    } elseif (isset($_POST['delete_product'])) {
        // Delete product
        $product_id = (int)$_POST['product_id'];
        
        if ($product_id > 0) {
            $delete_query = "DELETE FROM Products WHERE product_id = ?";
            $delete_stmt = $conn->prepare($delete_query);
            $delete_stmt->bind_param("i", $product_id);
            
            if ($delete_stmt->execute()) {
                $success = "Xóa sản phẩm thành công";
                $products_result = $conn->query($products_query);
            } else {
                $error = "Lỗi khi xóa sản phẩm: " . $conn->error;
            }
        }
    }
}

// Get product details for edit
$edit_product = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $product_id = (int)$_GET['edit'];
    $edit_query = "SELECT * FROM Products WHERE product_id = ?";
    $edit_stmt = $conn->prepare($edit_query);
    $edit_stmt->bind_param("i", $product_id);
    $edit_stmt->execute();
    $edit_result = $edit_stmt->get_result();
    
    if ($edit_result->num_rows > 0) {
        $edit_product = $edit_result->fetch_assoc();
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Sản Phẩm - Hệ Thống Quản Lý Quán Cà Phê</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="container">
            <a href="dashboard.php" class="navbar-brand">Quản Lý Quán Cà Phê</a>
            <ul class="navbar-nav">
                <li class="nav-item"><a href="products.php" class="nav-link">Sản Phẩm</a></li>
                <li class="nav-item"><a href="categories.php" class="nav-link">Danh Mục</a></li>
                <li class="nav-item"><a href="inventory.php" class="nav-link">Kho Hàng</a></li>
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
                <h1>Quản Lý Sản Phẩm</h1>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-md-4">
                    <div class="card">
                        <h2><?php echo $edit_product ? 'Cập Nhật Sản Phẩm' : 'Thêm Sản Phẩm Mới'; ?></h2>
                        <form method="POST" action="products.php">
                            <?php if ($edit_product): ?>
                                <input type="hidden" name="product_id" value="<?php echo $edit_product['product_id']; ?>">
                            <?php endif; ?>
                            <div class="form-group">
                                <label for="product_name">Tên Sản Phẩm</label>
                                <input type="text" id="product_name" name="product_name" value="<?php echo $edit_product ? $edit_product['product_name'] : ''; ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="category_id">Danh Mục</label>
                                <select id="category_id" name="category_id" required>
                                    <option value="">-- Chọn Danh Mục --</option>
                                    <?php $categories_result->data_seek(0); while ($category = $categories_result->fetch_assoc()): ?>
                                        <option value="<?php echo $category['category_id']; ?>" <?php echo ($edit_product && $edit_product['category_id'] == $category['category_id']) ? 'selected' : ''; ?>>
                                            <?php echo $category['category_name']; ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="size">Kích Cỡ</label>
                                <select id="size" name="size" required>
                                    <option value="">-- Chọn Kích Cỡ --</option>
                                    <option value="Nhỏ" <?php echo ($edit_product && $edit_product['size'] == 'Nhỏ') ? 'selected' : ''; ?>>Nhỏ</option>
                                    <option value="Vừa" <?php echo ($edit_product && $edit_product['size'] == 'Vừa') ? 'selected' : ''; ?>>Vừa</option>
                                    <option value="Lớn" <?php echo ($edit_product && $edit_product['size'] == 'Lớn') ? 'selected' : ''; ?>>Lớn</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="price">Giá</label>
                                <input type="number" id="price" name="price" min="0" step="1000" value="<?php echo $edit_product ? $edit_product['price'] : ''; ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="status">Trạng Thái</label>
                                <select id="status" name="status" required>
                                    <option value="Hoạt động" <?php echo ($edit_product && $edit_product['status'] == 'Hoạt động') ? 'selected' : ''; ?>>Hoạt động</option>
                                    <option value="Ngừng bán" <?php echo ($edit_product && $edit_product['status'] == 'Ngừng bán') ? 'selected' : ''; ?>>Ngừng bán</option>
                                </select>
                            </div>
                            <?php if ($edit_product): ?>
                                <button type="submit" name="update_product" class="btn btn-primary btn-block">Cập Nhật Sản Phẩm</button>
                                <a href="products.php" class="btn btn-secondary btn-block">Hủy</a>
                            <?php else: ?>
                                <button type="submit" name="add_product" class="btn btn-primary btn-block">Thêm Sản Phẩm</button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
                
                <div class="col-md-8">
                    <div class="card">
                        <h2>Danh Sách Sản Phẩm</h2>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Tên Sản Phẩm</th>
                                    <th>Danh Mục</th>
                                    <th>Kích Cỡ</th>
                                    <th>Giá</th>
                                    <th>Trạng Thái</th>
                                    <th>Thao Tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($products_result->num_rows > 0): ?>
                                    <?php while ($product = $products_result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $product['product_id']; ?></td>
                                            <td><?php echo $product['product_name']; ?></td>
                                            <td><?php echo $product['category_name']; ?></td>
                                            <td><?php echo $product['size']; ?></td>
                                            <td><?php echo formatCurrency($product['price']); ?></td>
                                            <td><span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $product['status'])); ?>"><?php echo $product['status']; ?></span></td>
                                            <td>
                                                <a href="products.php?edit=<?php echo $product['product_id']; ?>" class="btn btn-secondary btn-sm">Sửa</a>
                                                <form method="POST" action="products.php" style="display:inline;" onsubmit="return confirm('Bạn có chắc muốn xóa sản phẩm này?');">
                                                    <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                                    <button type="submit" name="delete_product" class="btn btn-danger btn-sm" onsubmit="return confirm(...)">Xóa</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="7" class="text-center">Không có sản phẩm nào</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>