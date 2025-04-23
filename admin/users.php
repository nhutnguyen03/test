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

// Check if active column exists in Users table
$check_column_query = "SHOW COLUMNS FROM Users LIKE 'active'";
$column_exists = $conn->query($check_column_query)->num_rows > 0;

// Add active column if it doesn't exist
if (!$column_exists) {
    $add_column_query = "ALTER TABLE Users ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1";
    $conn->query($add_column_query);
    // Update users_query to include the new column
    $users_query = "SELECT * FROM Users ORDER BY username";
    $users_result = $conn->query($users_query);
}

// Process user form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_user'])) {
        // Add new user
        $username = sanitize($_POST['username']);
        $password = sanitize($_POST['password']);
        $confirm_password = sanitize($_POST['confirm_password']);
        $role = sanitize($_POST['role']);
        
        // Validate inputs
        if (empty($username) || empty($password) || empty($confirm_password) || empty($role)) {
            $error = "Vui lòng điền đầy đủ thông tin người dùng";
        } elseif ($password !== $confirm_password) {
            $error = "Mật khẩu xác nhận không khớp";
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
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
                $insert_query = "INSERT INTO Users (username, password, role, active) VALUES (?, ?, ?, 1)";
                $insert_stmt = $conn->prepare($insert_query);
                $insert_stmt->bind_param("sss", $username, $hashed_password, $role);
                
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
        $password = sanitize($_POST['password']);
        $confirm_password = sanitize($_POST['confirm_password']);
        $role = sanitize($_POST['role']);
        $active = isset($_POST['active']) ? 1 : 0;
        
        // Validate inputs
        if ($user_id <= 0 || empty($username) || empty($role)) {
            $error = "Vui lòng điền đầy đủ thông tin người dùng";
        } elseif (!empty($password) && $password !== $confirm_password) {
            $error = "Mật khẩu xác nhận không khớp";
        } else {
            // Check user's role before updating
            $role_query = "SELECT role FROM Users WHERE user_id = ?";
            $role_stmt = $conn->prepare($role_query);
            $role_stmt->bind_param("i", $user_id);
            $role_stmt->execute();
            $role_result = $role_stmt->get_result();
            
            if ($role_result->num_rows > 0) {
                $current_role = $role_result->fetch_assoc()['role'];
                
                // Prevent deactivating admin account
                if ($current_role == 'Quản lý' && $active == 0) {
                    $error = "Không thể vô hiệu hóa tài khoản Quản lý";
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
                        if (!empty($password)) {
                            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                            $update_query = "UPDATE Users SET username = ?, password = ?, role = ?, active = ? WHERE user_id = ?";
                            $update_stmt = $conn->prepare($update_query);
                            $update_stmt->bind_param("sssii", $username, $hashed_password, $role, $active, $user_id);
                        } else {
                            $update_query = "UPDATE Users SET username = ?, role = ?, active = ? WHERE user_id = ?";
                            $update_stmt = $conn->prepare($update_query);
                            $update_stmt->bind_param("ssii", $username, $role, $active, $user_id);
                        }
                        
                        if ($update_stmt->execute()) {
                            $success = "Cập nhật người dùng thành công";
                            $users_result = $conn->query($users_query);
                        } else {
                            $error = "Lỗi khi cập nhật người dùng: " . $conn->error;
                        }
                    }
                }
            } else {
                $error = "Không tìm thấy người dùng";
            }
        }
    } elseif (isset($_POST['deactivate_user'])) {
        // Deactivate user instead of deleting
        $user_id = (int)$_POST['user_id'];
        
        if ($user_id > 0) {
            // Get user role
            $role_query = "SELECT role FROM Users WHERE user_id = ?";
            $role_stmt = $conn->prepare($role_query);
            $role_stmt->bind_param("i", $user_id);
            $role_stmt->execute();
            $role_result = $role_stmt->get_result();
            
            if ($role_result->num_rows > 0) {
                $user_role = $role_result->fetch_assoc()['role'];
                
                // Prevent deactivating current user
                if ($user_id == $_SESSION['user_id']) {
                    $error = "Không thể vô hiệu hóa tài khoản đang đăng nhập";
                } 
                // Prevent deactivating any admin account
                elseif ($user_role == 'Quản lý') {
                    $error = "Không thể vô hiệu hóa tài khoản Quản lý";
                } else {
                    $deactivate_query = "UPDATE Users SET active = 0 WHERE user_id = ?";
                    $deactivate_stmt = $conn->prepare($deactivate_query);
                    $deactivate_stmt->bind_param("i", $user_id);
                    
                    if ($deactivate_stmt->execute()) {
                        $success = "Vô hiệu hóa người dùng thành công";
                        $users_result = $conn->query($users_query);
                    } else {
                        $error = "Lỗi khi vô hiệu hóa người dùng: " . $conn->error;
                    }
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
    <?php include 'navbar.php'; ?>
    
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
                        <form method="POST" action="users.php" id="userForm">
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
                                <label for="confirm_password">Xác Nhận Mật Khẩu</label>
                                <input type="password" id="confirm_password" name="confirm_password" <?php echo $edit_user ? '' : 'required'; ?>>
                                <small class="text-danger" id="password-error" style="display: none;">Mật khẩu không khớp</small>
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
                            <div class="form-group">
                                <label for="active">Trạng thái hoạt động</label>
                                <div class="checkbox">
                                    <?php if ($edit_user['role'] == 'Quản lý'): ?>
                                        <input type="checkbox" id="active" name="active" checked disabled>
                                        <label for="active">Kích hoạt <span class="status-note">(Tài khoản quản lý không thể vô hiệu hóa)</span></label>
                                        <input type="hidden" name="active" value="1">
                                    <?php else: ?>
                                        <input type="checkbox" id="active" name="active" <?php echo (!isset($edit_user['active']) || $edit_user['active'] == 1) ? 'checked' : ''; ?>>
                                        <label for="active">Kích hoạt</label>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>
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
                                    <th>Trạng thái</th>
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
                                                <?php echo isset($user['active']) ? ($user['active'] ? '<span class="status-active">Hoạt động</span>' : '<span class="status-inactive">Không hoạt động</span>') : '<span class="status-active">Hoạt động</span>'; ?>
                                            </td>
                                            <td>
                                                <a href="users.php?edit=<?php echo $user['user_id']; ?>" class="btn btn-secondary btn-sm">Sửa</a>
                                                <?php if (
                                                    (!isset($user['active']) || $user['active'] == 1) && 
                                                    $user['user_id'] != $_SESSION['user_id'] && 
                                                    $user['role'] != 'Quản lý'
                                                ): ?>
                                                <form method="POST" action="users.php" style="display:inline;" onsubmit="return confirm('Bạn có chắc muốn vô hiệu hóa người dùng này? Điều này sẽ giữ lại lịch sử đơn hàng của họ.');">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                    <button type="submit" name="deactivate_user" class="btn btn-danger btn-sm">Vô hiệu hóa</button>
                                                </form>
                                                <?php endif; ?>
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
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const userForm = document.getElementById('userForm');
            const passwordField = document.getElementById('password');
            const confirmPasswordField = document.getElementById('confirm_password');
            const passwordError = document.getElementById('password-error');
            
            // Validate passwords match before submission
            userForm.addEventListener('submit', function(e) {
                if (passwordField.value || confirmPasswordField.value) {
                    if (passwordField.value !== confirmPasswordField.value) {
                        e.preventDefault();
                        passwordError.style.display = 'block';
                        return false;
                    } else {
                        passwordError.style.display = 'none';
                    }
                }
            });
            
            // Live validation
            confirmPasswordField.addEventListener('input', function() {
                if (passwordField.value || confirmPasswordField.value) {
                    if (passwordField.value !== confirmPasswordField.value) {
                        passwordError.style.display = 'block';
                    } else {
                        passwordError.style.display = 'none';
                    }
                }
            });
            
            // Also check when password field changes
            passwordField.addEventListener('input', function() {
                if (confirmPasswordField.value) {
                    if (passwordField.value !== confirmPasswordField.value) {
                        passwordError.style.display = 'block';
                    } else {
                        passwordError.style.display = 'none';
                    }
                }
            });
        });
    </script>
</body>
</html>