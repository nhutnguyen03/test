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

// Get all categories
$categories_query = "SELECT * FROM Categories ORDER BY category_name";
$categories_result = $conn->query($categories_query);

// Process category form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_category'])) {
        // Add new category
        $category_name = sanitize($_POST['category_name']);
        $description = sanitize($_POST['description']);
        $status = sanitize($_POST['status']);
        
        // Validate inputs
        if (empty($category_name)) {
            $error = "Vui lòng nhập tên danh mục";
        } else {
            // Insert category
            $insert_query = "INSERT INTO Categories (category_name, description, status) 
                           VALUES (?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bind_param("sss", $category_name, $description, $status);
            
            if ($insert_stmt->execute()) {
                $success = "Thêm danh mục thành công";
                $categories_result = $conn->query($categories_query);
            } else {
                $error = "Lỗi khi thêm danh mục: " . $conn->error;
            }
        }
    } elseif (isset($_POST['update_category'])) {
        // Update category
        $category_id = (int)$_POST['category_id'];
        $category_name = sanitize($_POST['category_name']);
        $description = sanitize($_POST['description']);
        $status = sanitize($_POST['status']);
        
        // Validate inputs
        if ($category_id <= 0 || empty($category_name)) {
            $error = "Vui lòng điền đầy đủ thông tin danh mục";
        } else {
            // Update category
            $update_query = "UPDATE Categories SET category_name = ?, description = ?, status = ? 
                           WHERE category_id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("sssi", $category_name, $description, $status, $category_id);
            
            if ($update_stmt->execute()) {
                $success = "Cập nhật danh mục thành công";
                $categories_result = $conn->query($categories_query);
            } else {
                $error = "Lỗi khi cập nhật danh mục: " . $conn->error;
            }
        }
    }
}

// Get category details for edit
$edit_category = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $category_id = (int)$_GET['edit'];
    $edit_query = "SELECT * FROM Categories WHERE category_id = ?";
    $edit_stmt = $conn->prepare($edit_query);
    $edit_stmt->bind_param("i", $category_id);
    $edit_stmt->execute();
    $edit_result = $edit_stmt->get_result();
    
    if ($edit_result->num_rows > 0) {
        $edit_category = $edit_result->fetch_assoc();
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Danh Mục - Hệ Thống Quản Lý Quán Cà Phê</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="container">
            <a href="dashboard.php" class="navbar-brand">Quản Lý Quán Cà Phê</a>
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a href="products.php" class="nav-link">Sản Phẩm</a>
                </li>
                <li class="nav-item">
                    <a href="categories.php" class="nav-link active">Danh Mục</a>
                </li>
                <li class="nav-item">
                    <a href="inventory.php" class="nav-link">Kho Hàng</a>
                </li>
                <li class="nav-item">
                    <a href="promotions.php" class="nav-link">Khuyến Mãi</a>
                </li>
                <li class="nav-item">
                    <a href="reports.php" class="nav-link">Báo Cáo</a>
                </li>
                <li class="nav-item">
                    <a href="users.php" class="nav-link">Nhân Viên</a>
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
                <h1>Quản Lý Danh Mục</h1>
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
                        <h2><?php echo $edit_category ? 'Cập Nhật Danh Mục' : 'Thêm Danh Mục Mới'; ?></h2>
                        
                        <form method="POST" action="categories.php">
                            <?php if ($edit_category): ?>
                                <input type="hidden" name="category_id" value="<?php echo $edit_category['category_id']; ?>">
                            <?php endif; ?>
                            
                            <div class="form-group">
                                <label for="category_name">Tên Danh Mục</label>
                                <input type="text" id="category_name" name="category_name" 
                                       value="<?php echo $edit_category ? $edit_category['category_name'] : ''; ?>" required>
                            </div>
                            
                            
                            <div class="form-group">
                                <label for="status">Trạng Thái</label>
                                <select id="status" name="status" required>
                                    <option value="Hoạt động" <?php echo ($edit_category && $edit_category['status'] == 'Hoạt động') ? 'selected' : ''; ?>>Hoạt động</option>
                                    <option value="Ngừng hoạt động" <?php echo ($edit_category && $edit_category['status'] == 'Ngừng hoạt động') ? 'selected' : ''; ?>>Ngừng hoạt động</option>
                                </select>
                            </div>
                            
                            <?php if ($edit_category): ?>
                                <button type="submit" name="update_category" class="btn btn-primary btn-block">Cập Nhật Danh Mục</button>
                                <a href="categories.php" class="btn btn-secondary btn-block">Hủy</a>
                            <?php else: ?>
                                <button type="submit" name="add_category" class="btn btn-primary btn-block">Thêm Danh Mục</button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
                
                <div class="col-md-8">
                    <div class="card">
                        <h2>Danh Sách Danh Mục</h2>
                        
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Tên Danh Mục</th>
                                    <th>Trạng Thái</th>
                                    <th>Thao Tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($categories_result->num_rows > 0): ?>
                                    <?php while ($category = $categories_result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $category['category_id']; ?></td>
                                            <td><?php echo $category['category_name']; ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $category['status'])); ?>">
                                                    <?php echo $category['status']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="categories.php?edit=<?php echo $category['category_id']; ?>" 
                                                   class="btn btn-secondary btn-sm">Sửa</a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center">Không có danh mục nào</td>
                                    </tr>
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