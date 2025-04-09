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

// Get all promotions
$promotions_query = "SELECT * FROM Promotions ORDER BY start_date DESC";
$promotions_result = $conn->query($promotions_query);

// Process promotion form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_promotion'])) {
        // Add new promotion
        $discount_code = sanitize($_POST['discount_code']);
        $discount_value = (float)$_POST['discount_value'];
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $status = sanitize($_POST['status']);
        
        // Validate inputs
        if (empty($discount_code) || $discount_value <= 0 || empty($start_date) || empty($end_date)) {
            $error = "Vui lòng điền đầy đủ thông tin khuyến mãi";
        } elseif (strtotime($end_date) < strtotime($start_date)) {
            $error = "Ngày kết thúc phải sau ngày bắt đầu";
        } else {
            // Insert promotion
            $insert_query = "INSERT INTO Promotions (discount_code, discount_value, start_date, end_date, status) 
                           VALUES (?, ?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bind_param("sdsss", $discount_code, $discount_value, $start_date, $end_date, $status);
            
            if ($insert_stmt->execute()) {
                $success = "Thêm khuyến mãi thành công";
                $promotions_result = $conn->query($promotions_query);
            } else {
                $error = "Lỗi khi thêm khuyến mãi: " . $conn->error;
            }
        }
    } elseif (isset($_POST['update_promotion'])) {
        // Update promotion
        $promo_id = (int)$_POST['promo_id'];
        $discount_code = sanitize($_POST['discount_code']);
        $discount_value = (float)$_POST['discount_value'];
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $status = sanitize($_POST['status']);
        
        // Validate inputs
        if ($promo_id <= 0 || empty($discount_code) || $discount_value <= 0 || empty($start_date) || empty($end_date)) {
            $error = "Vui lòng điền đầy đủ thông tin khuyến mãi";
        } elseif (strtotime($end_date) < strtotime($start_date)) {
            $error = "Ngày kết thúc phải sau ngày bắt đầu";
        } else {
            // Update promotion
            $update_query = "UPDATE Promotions SET discount_code = ?, discount_value = ?, start_date = ?, end_date = ?, status = ? 
                           WHERE promo_id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("sdsssi", $discount_code, $discount_value, $start_date, $end_date, $status, $promo_id);
            
            if ($update_stmt->execute()) {
                $success = "Cập nhật khuyến mãi thành công";
                $promotions_result = $conn->query($promotions_query);
            } else {
                $error = "Lỗi khi cập nhật khuyến mãi: " . $conn->error;
            }
        }
    } elseif (isset($_POST['delete_promotion'])) {
        // Delete promotion
        $promo_id = (int)$_POST['promo_id'];
        
        if ($promo_id > 0) {
            $delete_query = "DELETE FROM Promotions WHERE promo_id = ?";
            $delete_stmt = $conn->prepare($delete_query);
            $delete_stmt->bind_param("i", $promo_id);
            
            if ($delete_stmt->execute()) {
                $success = "Xóa khuyến mãi thành công";
                $promotions_result = $conn->query($promotions_query);
            } else {
                $error = "Lỗi khi xóa khuyến mãi: " . $conn->error;
            }
        }
    }
}

// Get promotion details for edit
$edit_promotion = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $promo_id = (int)$_GET['edit'];
    $edit_query = "SELECT * FROM Promotions WHERE promo_id = ?";
    $edit_stmt = $conn->prepare($edit_query);
    $edit_stmt->bind_param("i", $promo_id);
    $edit_stmt->execute();
    $edit_result = $edit_stmt->get_result();
    
    if ($edit_result->num_rows > 0) {
        $edit_promotion = $edit_result->fetch_assoc();
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Khuyến Mãi - Hệ Thống Quản Lý Quán Cà Phê</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <!-- Navigation -->
    <?php include 'navbar.php'; ?>
    
    <!-- Main Content -->
    <div class="container">
        <div class="dashboard">
            <div class="dashboard-header">
                <h1>Quản Lý Khuyến Mãi</h1>
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
                        <h2><?php echo $edit_promotion ? 'Cập Nhật Khuyến Mãi' : 'Thêm Khuyến Mãi Mới'; ?></h2>
                        <form method="POST" action="promotions.php">
                            <?php if ($edit_promotion): ?>
                                <input type="hidden" name="promo_id" value="<?php echo $edit_promotion['promo_id']; ?>">
                            <?php endif; ?>
                            <div class="form-group">
                                <label for="discount_code">Mã Khuyến Mãi</label>
                                <input type="text" id="discount_code" name="discount_code" value="<?php echo $edit_promotion ? $edit_promotion['discount_code'] : ''; ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="discount_value">Giá Trị Giảm</label>
                                <input type="number" id="discount_value" name="discount_value" min="0" step="1000" value="<?php echo $edit_promotion ? $edit_promotion['discount_value'] : ''; ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="start_date">Ngày Bắt Đầu</label>
                                <input type="date" id="start_date" name="start_date" value="<?php echo $edit_promotion ? $edit_promotion['start_date'] : ''; ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="end_date">Ngày Kết Thúc</label>
                                <input type="date" id="end_date" name="end_date" value="<?php echo $edit_promotion ? $edit_promotion['end_date'] : ''; ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="status">Trạng Thái</label>
                                <select id="status" name="status" required>
                                    <option value="Hoạt động" <?php echo ($edit_promotion && $edit_promotion['status'] == 'Hoạt động') ? 'selected' : ''; ?>>Hoạt động</option>
                                    <option value="Hết hạn" <?php echo ($edit_promotion && $edit_promotion['status'] == 'Hết hạn') ? 'selected' : ''; ?>>Hết hạn</option>
                                </select>
                            </div>
                            <?php if ($edit_promotion): ?>
                                <button type="submit" name="update_promotion" class="btn btn-primary btn-block">Cập Nhật Khuyến Mãi</button>
                                <a href="promotions.php" class="btn btn-secondary btn-block">Hủy</a>
                            <?php else: ?>
                                <button type="submit" name="add_promotion" class="btn btn-primary btn-block">Thêm Khuyến Mãi</button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
                
                <div class="col-md-8">
                    <div class="card">
                        <h2>Danh Sách Khuyến Mãi</h2>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Mã Khuyến Mãi</th>
                                    <th>Giá Trị Giảm</th>
                                    <th>Ngày Bắt Đầu</th>
                                    <th>Ngày Kết Thúc</th>
                                    <th>Trạng Thái</th>
                                    <th>Thao Tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($promotions_result->num_rows > 0): ?>
                                    <?php while ($promotion = $promotions_result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $promotion['promo_id']; ?></td>
                                            <td><?php echo $promotion['discount_code']; ?></td>
                                            <td><?php echo formatCurrency($promotion['discount_value']); ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($promotion['start_date'])); ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($promotion['end_date'])); ?></td>
                                            <td><span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $promotion['status'])); ?>"><?php echo $promotion['status']; ?></span></td>
                                            <td>
                                                <a href="promotions.php?edit=<?php echo $promotion['promo_id']; ?>" class="btn btn-secondary btn-sm">Sửa</a>
                                                <form method="POST" action="promotions.php" style="display:inline;" onsubmit="return confirm('Bạn có chắc muốn xóa khuyến mãi này?');">
                                                    <input type="hidden" name="promo_id" value="<?php echo $promotion['promo_id']; ?>">
                                                    <button type="submit" name="delete_promotion" class="btn btn-danger btn-sm">Xóa</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="7" class="text-center">Không có khuyến mãi nào</td></tr>
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