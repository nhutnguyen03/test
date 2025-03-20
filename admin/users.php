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

// Get all users
$users_query = "SELECT * FROM Users ORDER BY username";
$users_result = $conn->query($users_query);

// Process user form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_user'])) {
        // Add new user
        $username = sanitize($_POST['username']);
        $password = password_hash(sanitize($_POST['password']), PASSWORD_DEFAULT); // Hash password
        $role = sanitize($_POST['role']);
        
        // Validate inputs
        if (empty($username) || empty($_POST['password']) || empty($role)) {
            $error = "Vui lòng điền đầy đủ thông tin người dùng";
        } else {
            // Check if username already exists
            $check_query = "SELECT COUNT(*) FROM Users WHERE username = ?";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bind_param("s", $username);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result()->fetch_row();
            
            if ($check_result[0] > 0) {
                $error = "Tên đăng nhập đã tồn tại";
            } else {
                // Insert user
                $insert_query = "INSERT INTO Users (username, password, role) VALUES (?, ?, ?)";
                $insert_stmt = $conn->prepare($insert_query);
                $insert_stmt->bind_param("sss", $username, $password, $role);
                
                if ($insert_stmt->execute()) {
                    $success = "Thêm người dùng thành công";
                    $users_result = $conn->query($users_query);
                } else {
                    $error = "Lỗi khi thêm người dùng: " . $conn->error;
                }
            }
        }
    } elseif (isset($_POST['update_user'])) {
        // Update user
        $user_id = (int)$_POST['user_id'];
        $username = sanitize($_POST['username']);
        $password = !empty($_POST['password']) ? password_hash(sanitize($_POST['password']), PASSWORD_DEFAULT) : null;
        $role = sanitize($_POST['role']);
        
        // Validate inputs
        if ($user_id <= 0 || empty($username) || empty($role)) {
            $error = "Vui lòng điền đầy đủ thông tin người dùng";
        } else {
            // Check if username exists for another user
            $check_query = "SELECT COUNT(*) FROM Users WHERE username = ? AND user_id != ?";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bind_param("si", $username, $user_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result()->fetch_row();
            
            if ($check_result[0] > 0) {
                $error = "Tên đăng nhập đã tồn tại";
            } else {
                // Update user (only update password if provided)
                if ($password) {
                    $update_query = "UPDATE Users SET username = ?, password = ?, role = ? WHERE user_id = ?";
                    $update_stmt = $conn->prepare($update_query);
                    $update_stmt->bind_param("sssi", $username, $password, $role, $user_id);
                } else {
                    $update_query = "UPDATE Users SET username = ?, role = ? WHERE user_id = ?";
                    $update_stmt = $conn->prepare($update_query);
                    $update_stmt->bind_param("ssi", $username, $role, $user_id);
                }
                
                if ($update_stmt->execute()) {
                    $success = "Cập nhật người dùng thành công";
                    $users_result = $conn->query($users_query);
                } else {
                    $error = "Lỗi khi cập nhật người dùng: " . $conn->error;
                }
            }
        }
    } elseif (isset($_POST['delete_user'])) {
        // Delete user
        $user_id = (int)$_POST['user_id'];
        
        if ($user_id > 0) {
            // Prevent deleting current user
            if ($user_id == $_SESSION['user_id']) {
                $error = "Không thể xóa tài khoản đang đăng nhập";
            } else {
                $delete_query = "DELETE FROM Users WHERE user_id = ?";
                $delete_stmt = $conn->prepare($delete_query);
                $delete_stmt->bind_param("i", $user_id);
                
                if ($delete_stmt->execute()) {
                    $success = "Xóa người dùng thành công";
                    $users_result = $conn->query($users_query);
                } else {
                    $error = "Lỗi khi xóa người dùng: " . $conn->error;
                }
            }
        }
    }
}

// Get user details for edit
$edit_user = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $user_id = (int)$_GET['edit'];
    $edit_query = "SELECT * FROM Users WHERE user_id = ?";
    $edit_stmt = $conn->prepare($edit_query);
    $edit_stmt->bind_param("i", $user_id);
    $edit_stmt->execute();
    $edit_result = $edit_stmt->get_result();
    
    if ($edit_result->num_rows > 0) {
        $edit_user = $edit_result->fetch_assoc();
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Người Dùng - Hệ Thống Quản Lý Quán Cà Phê</title>
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
                <li class="nav-item"><a href="users.php" class="nav-link active">Nhân Viên</a></li>
                <li class="nav-item"><a href="../logout.php" class="nav-link">Đăng Xuất</a></li>
            </ul>
        </div>
    </nav>
    
    <!-- Main Content -->
    <div class="container">
        <div class="dashboard">
            <div class="dashboard-header">
                <h1>Quản Lý Người Dùng</h1>
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
                        <h2><?php echo $edit_user ? 'Cập Nhật Người Dùng' : 'Thêm Người Dùng Mới'; ?></h2>
                        <form method="POST" action="users.php">
                            <?php if ($edit_user): ?>
                                <input type="hidden" name="user_id" value="<?php echo $edit_user['user_id']; ?>">
                            <?php endif; ?>
                            <div class="form-group">
                                <label for="username">Tên Đăng Nhập</label>
                                <input type="text" id="username" name="username" value="<?php echo $edit_user ? $edit_user['username'] : ''; ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="password">Mật Khẩu <?php echo $edit_user ? '(Để trống nếu không đổi)' : ''; ?></label>
                                <input type="password" id="password" name="password" <?php echo $edit_user ? '' : 'required'; ?>>
                            </div>
                            <div class="form-group">
                                <label for="role">Vai Trò</label>
                                <select id="role" name="role" required>
                                    <option value="Quản lý" <?php echo ($edit_user && $edit_user['role'] == 'Quản lý') ? 'selected' : ''; ?>>Quản lý</option>
                                    <option value="Ca sáng" <?php echo ($edit_user && $edit_user['role'] == 'Ca sáng') ? 'selected' : ''; ?>>Ca sáng</option>
                                    <option value="Ca chiều" <?php echo ($edit_user && $edit_user['role'] == 'Ca chiều') ? 'selected' : ''; ?>>Ca chiều</option>
                                </select>
                            </div>
                            <?php if ($edit_user): ?>
                                <button type="submit" name="update_user" class="btn btn-primary btn-block">Cập Nhật Người Dùng</button>
                                <a href="users.php" class="btn btn-secondary btn-block">Hủy</a>
                            <?php else: ?>
                                <button type="submit" name="add_user" class="btn btn-primary btn-block">Thêm Người Dùng</button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
                
                <div class="col-md-8">
                    <div class="card">
                        <h2>Danh Sách Người Dùng</h2>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Tên Đăng Nhập</th>
                                    <th>Vai Trò</th>
                                    <th>Thao Tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($users_result->num_rows > 0): ?>
                                    <?php while ($user = $users_result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $user['user_id']; ?></td>
                                            <td><?php echo $user['username']; ?></td>
                                            <td><?php echo $user['role']; ?></td>
                                            <td>
                                                <a href="users.php?edit=<?php echo $user['user_id']; ?>" class="btn btn-secondary btn-sm">Sửa</a>
                                                <form method="POST" action="users.php" style="display:inline;" onsubmit="return confirm('Bạn có chắc muốn xóa người dùng này?');">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                    <button type="submit" name="delete_user" class="btn btn-danger btn-sm">Xóa</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="text-center">Không có người dùng nào</td></tr>
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