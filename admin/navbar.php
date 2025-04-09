<!-- Navigation -->
<nav class="navbar">
    <div class="container">
        <a href="dashboard.php" class="navbar-brand">Quản Lý Quán Cà Phê Nhựt Thịnh</a>
        <ul class="navbar-nav">
            <li class="nav-item">
                <a href="products.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'products.php') ? 'active' : ''; ?>">Sản Phẩm</a>
            </li>
            <li class="nav-item">
                <a href="categories.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'categories.php') ? 'active' : ''; ?>">Danh Mục</a>
            </li>
            <li class="nav-item">
                <a href="inventory.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'inventory.php') ? 'active' : ''; ?>">Kho Hàng</a>
            </li>
            <li class="nav-item">
                <a href="promotions.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'promotions.php') ? 'active' : ''; ?>">Khuyến Mãi</a>
            </li>
            <li class="nav-item">
                <a href="reports.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'reports.php') ? 'active' : ''; ?>">Báo Cáo</a>
            </li>
            <li class="nav-item">
                <a href="users.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'users.php') ? 'active' : ''; ?>">Nhân Viên</a>
            </li>
            <li class="nav-item">
                <a href="../logout.php" class="nav-link">Đăng Xuất</a>
            </li>
        </ul>
    </div>
</nav> 