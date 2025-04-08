-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Máy chủ: 127.0.0.1
-- Thời gian đã tạo: Th4 06, 2025 lúc 02:47 PM
-- Phiên bản máy phục vụ: 10.4.32-MariaDB
-- Phiên bản PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Cơ sở dữ liệu: `coffee_shop`
--

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `categories`
--

CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `status` enum('Hoạt động','Không hoạt động') DEFAULT 'Hoạt động'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `categories`
--

INSERT INTO `categories` (`category_id`, `category_name`, `status`) VALUES
(1, 'Trà', 'Hoạt động'),
(2, 'Cà phê', 'Hoạt động'),
(3, 'Bánh', 'Hoạt động'),
(4, 'Khác', 'Hoạt động');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `materials`
--

CREATE TABLE `materials` (
  `material_id` int(11) NOT NULL,
  `material_name` varchar(100) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `unit` varchar(50) NOT NULL,
  `stock_quantity` int(11) NOT NULL DEFAULT 0,
  `low_stock_threshold` int(11) NOT NULL DEFAULT 10,
  `status` enum('Còn hàng','Hết hàng') DEFAULT 'Còn hàng'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `materials`
--

INSERT INTO `materials` (`material_id`, `material_name`, `supplier_id`, `unit`, `stock_quantity`, `low_stock_threshold`, `status`) VALUES
(1, 'Trà ô long', 1, 'túi', 50, 50, 'Còn hàng'),
(2, 'Hạt cà phê robusta', 2, 'kg', 30, 10, 'Còn hàng'),
(3, 'Sữa đặc', 2, 'lít', 20, 10, 'Còn hàng'),
(4, 'Bánh cookies', 3, 'cái', 20, 10, 'Còn hàng'),
(5, 'Bánh chuối', 3, 'cái', 10, 10, 'Còn hàng'),
(6, 'Bột cacao', 3, 'kg', 6, 1, 'Còn hàng'),
(7, 'Nước suối đóng chai', 4, 'chai', 100, 30, 'Còn hàng'),
(8, 'Coca lon', 4, 'lon', 100, 20, 'Còn hàng'),
(9, 'Trân châu khô', 5, 'kg', 25, 5, 'Còn hàng'),
(10, 'Thạch rau câu', 5, 'kg', 20, 5, 'Còn hàng');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `orders`
--

CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `table_id` varchar(10) NOT NULL,
  `shift_id` int(11) NOT NULL,
  `promo_id` int(11) DEFAULT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `order_time` datetime DEFAULT current_timestamp(),
  `status` enum('Chờ chế biến','Hoàn thành','Đã thanh toán','Đã hủy') DEFAULT 'Chờ chế biến',
  `notes` text DEFAULT NULL,
  `completed` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `orders`
--

INSERT INTO `orders` (`order_id`, `user_id`, `table_id`, `shift_id`, `promo_id`, `total_price`, `order_time`, `status`, `notes`, `completed`) VALUES
(16, 1, 'TB2', 1, NULL, 25000.00, '2025-03-20 10:04:22', 'Hoàn thành', ' | ', 0),
(17, 1, 'TB2', 1, 1, 65000.00, '2025-03-21 13:37:27', 'Hoàn thành', ' | ', 0),
(18, 1, 'TB3', 1, NULL, 25000.00, '2025-03-21 13:38:03', 'Hoàn thành', ' | ', 0),
(19, NULL, 'TB6', 1, NULL, 115000.00, '2025-03-23 08:13:49', 'Hoàn thành', NULL, 0),
(20, NULL, 'TB4', 1, NULL, 60000.00, '2025-03-23 09:26:30', 'Hoàn thành', NULL, 0),
(21, NULL, 'TB2', 2, NULL, 60000.00, '2025-03-23 17:45:43', 'Hoàn thành', NULL, 0),
(22, NULL, 'TB6', 2, NULL, 140000.00, '2025-03-23 16:08:29', 'Hoàn thành', NULL, 0),
(23, NULL, 'TB6', 1, NULL, 45000.00, '2025-03-24 13:39:28', 'Hoàn thành', NULL, 0),
(24, NULL, 'TB5', 1, NULL, 90000.00, '2025-03-24 07:48:11', 'Hoàn thành', NULL, 0),
(25, NULL, 'TB2', 2, NULL, 90000.00, '2025-03-24 17:40:51', 'Hoàn thành', NULL, 0),
(26, NULL, 'TB4', 2, NULL, 195000.00, '2025-03-24 14:39:15', 'Hoàn thành', NULL, 0),
(27, NULL, 'TB5', 1, NULL, 55000.00, '2025-03-25 13:49:14', 'Hoàn thành', NULL, 0),
(28, NULL, 'TB7', 1, NULL, 105000.00, '2025-03-25 12:46:08', 'Hoàn thành', NULL, 0),
(29, NULL, 'TB2', 2, NULL, 25000.00, '2025-03-25 20:14:43', 'Hoàn thành', NULL, 0),
(30, NULL, 'TB4', 2, NULL, 150000.00, '2025-03-25 15:45:21', 'Hoàn thành', NULL, 0),
(31, NULL, 'TB5', 1, NULL, 210000.00, '2025-03-26 12:31:47', 'Hoàn thành', NULL, 0),
(32, NULL, 'TB3', 1, NULL, 90000.00, '2025-03-26 10:12:54', 'Hoàn thành', NULL, 0),
(33, NULL, 'TB1', 2, NULL, 15000.00, '2025-03-26 16:13:21', 'Hoàn thành', NULL, 0),
(34, NULL, 'TB5', 2, NULL, 100000.00, '2025-03-26 14:44:50', 'Hoàn thành', NULL, 0),
(35, 1, 'TB7', 1, 1, 90000.00, '2025-03-26 08:26:13', 'Hoàn thành', 'ít đường | ', 0),
(36, NULL, 'TB5', 1, NULL, 50000.00, '2025-03-24 13:59:59', 'Hoàn thành', NULL, 0),
(37, NULL, 'TB7', 1, NULL, 115000.00, '2025-03-24 09:05:50', 'Hoàn thành', NULL, 0),
(38, NULL, 'TB6', 2, NULL, 30000.00, '2025-03-24 18:48:29', 'Hoàn thành', NULL, 0),
(39, NULL, 'TB4', 2, NULL, 45000.00, '2025-03-24 18:01:39', 'Hoàn thành', NULL, 0),
(40, NULL, 'TB2', 1, NULL, 110000.00, '2025-03-25 13:16:53', 'Hoàn thành', NULL, 0),
(41, NULL, 'TB1', 1, NULL, 70000.00, '2025-03-25 12:33:57', 'Hoàn thành', NULL, 0),
(42, NULL, 'TB3', 2, NULL, 5000.00, '2025-03-25 17:24:56', 'Hoàn thành', NULL, 0),
(43, NULL, 'TB6', 2, NULL, 10000.00, '2025-03-25 15:52:13', 'Hoàn thành', NULL, 0),
(44, NULL, 'TB1', 1, NULL, 125000.00, '2025-03-26 11:32:26', 'Hoàn thành', NULL, 0),
(45, NULL, 'TB7', 1, NULL, 105000.00, '2025-03-26 11:10:06', 'Hoàn thành', NULL, 0),
(46, NULL, 'TB3', 2, NULL, 15000.00, '2025-03-26 15:06:36', 'Hoàn thành', NULL, 0),
(47, NULL, 'TB4', 2, NULL, 75000.00, '2025-03-26 17:40:50', 'Hoàn thành', NULL, 0),
(48, 1, 'TB1', 1, NULL, 25000.00, '2025-03-26 08:34:11', 'Hoàn thành', ' | ', 0),
(49, 1, 'TB1', 1, NULL, 155000.00, '2025-03-26 08:47:46', 'Hoàn thành', ' | ', 0),
(50, 1, 'TB1', 2, NULL, 115000.00, '2025-04-06 19:37:06', 'Hoàn thành', ' | ', 0);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `order_details`
--

CREATE TABLE `order_details` (
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `note` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `order_details`
--

INSERT INTO `order_details` (`order_id`, `product_id`, `quantity`, `price`, `note`) VALUES
(16, 1, 1, 25000.00, NULL),
(17, 1, 1, 25000.00, NULL),
(17, 5, 1, 25000.00, NULL),
(17, 9, 1, 35000.00, NULL),
(18, 12, 1, 10000.00, NULL),
(18, 13, 1, 15000.00, NULL),
(19, 4, 2, 20000.00, NULL),
(19, 10, 2, 15000.00, NULL),
(19, 13, 3, 15000.00, NULL),
(20, 1, 1, 25000.00, NULL),
(20, 6, 1, 30000.00, NULL),
(20, 14, 1, 5000.00, NULL),
(21, 11, 3, 20000.00, NULL),
(22, 3, 3, 35000.00, NULL),
(22, 7, 1, 25000.00, NULL),
(22, 15, 2, 5000.00, NULL),
(23, 10, 3, 15000.00, NULL),
(24, 2, 3, 30000.00, NULL),
(25, 4, 3, 20000.00, NULL),
(25, 5, 1, 25000.00, NULL),
(25, 15, 1, 5000.00, NULL),
(26, 2, 1, 30000.00, NULL),
(26, 8, 2, 30000.00, NULL),
(26, 9, 3, 35000.00, NULL),
(27, 7, 1, 25000.00, NULL),
(27, 12, 3, 10000.00, NULL),
(28, 3, 2, 35000.00, NULL),
(28, 9, 1, 35000.00, NULL),
(29, 7, 1, 25000.00, NULL),
(30, 2, 2, 30000.00, NULL),
(30, 6, 3, 30000.00, NULL),
(31, 1, 3, 25000.00, NULL),
(31, 6, 3, 30000.00, NULL),
(31, 13, 3, 15000.00, NULL),
(32, 8, 3, 30000.00, NULL),
(33, 10, 1, 15000.00, NULL),
(34, 4, 2, 20000.00, NULL),
(34, 6, 2, 30000.00, NULL),
(35, 4, 1, 20000.00, NULL),
(35, 5, 1, 25000.00, NULL),
(35, 8, 1, 30000.00, NULL),
(35, 9, 1, 35000.00, NULL),
(36, 1, 2, 25000.00, NULL),
(37, 1, 3, 25000.00, NULL),
(37, 11, 2, 20000.00, NULL),
(38, 7, 1, 25000.00, NULL),
(38, 14, 1, 5000.00, NULL),
(39, 13, 3, 15000.00, NULL),
(40, 5, 1, 25000.00, NULL),
(40, 7, 1, 25000.00, NULL),
(40, 8, 2, 30000.00, NULL),
(41, 9, 2, 35000.00, NULL),
(42, 15, 1, 5000.00, NULL),
(43, 12, 1, 10000.00, NULL),
(44, 1, 3, 25000.00, NULL),
(44, 3, 1, 35000.00, NULL),
(44, 13, 1, 15000.00, NULL),
(45, 8, 3, 30000.00, NULL),
(45, 10, 1, 15000.00, NULL),
(46, 10, 1, 15000.00, NULL),
(47, 10, 3, 15000.00, NULL),
(47, 14, 3, 5000.00, NULL),
(47, 15, 3, 5000.00, NULL),
(48, 1, 1, 25000.00, NULL),
(49, 1, 1, 25000.00, NULL),
(49, 3, 2, 35000.00, NULL),
(49, 7, 1, 25000.00, NULL),
(49, 9, 1, 35000.00, NULL),
(50, 1, 1, 25000.00, NULL),
(50, 2, 1, 30000.00, NULL),
(50, 3, 1, 35000.00, NULL),
(50, 7, 1, 25000.00, NULL);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `payment_method` enum('Tiền mặt','Chuyển khoản','Thẻ','MoMo') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `transaction_code` varchar(50) DEFAULT NULL,
  `status` enum('Thành công','Thất bại','Chờ xử lý') DEFAULT 'Chờ xử lý',
  `payment_time` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `payments`
--

INSERT INTO `payments` (`payment_id`, `order_id`, `payment_method`, `amount`, `transaction_code`, `status`, `payment_time`) VALUES
(16, 16, 'Tiền mặt', 25000.00, '', 'Thành công', '2025-03-20 10:04:22'),
(17, 17, 'Tiền mặt', 65000.00, '', 'Thành công', '2025-03-21 13:37:27'),
(18, 18, 'Tiền mặt', 25000.00, '', 'Thành công', '2025-03-21 13:38:03'),
(19, 19, 'Thẻ', 115000.00, NULL, 'Thành công', '2025-03-26 08:21:52'),
(20, 20, 'MoMo', 60000.00, NULL, 'Thành công', '2025-03-26 08:21:52'),
(21, 21, 'Thẻ', 60000.00, NULL, 'Thành công', '2025-03-26 08:21:52'),
(22, 22, 'Thẻ', 140000.00, NULL, 'Thành công', '2025-03-26 08:21:52'),
(23, 23, 'Thẻ', 45000.00, NULL, 'Thành công', '2025-03-26 08:21:52'),
(24, 24, 'Thẻ', 90000.00, NULL, 'Thành công', '2025-03-26 08:21:52'),
(25, 25, 'Thẻ', 90000.00, NULL, 'Thành công', '2025-03-26 08:21:52'),
(26, 26, 'Thẻ', 195000.00, NULL, 'Thành công', '2025-03-26 08:21:52'),
(27, 27, 'Tiền mặt', 55000.00, NULL, 'Thành công', '2025-03-26 08:21:52'),
(28, 28, 'Thẻ', 105000.00, NULL, 'Thành công', '2025-03-26 08:21:52'),
(29, 29, 'MoMo', 25000.00, NULL, 'Thành công', '2025-03-26 08:21:52'),
(30, 30, 'Thẻ', 150000.00, NULL, 'Thành công', '2025-03-26 08:21:52'),
(31, 31, 'MoMo', 210000.00, NULL, 'Thành công', '2025-03-26 08:21:52'),
(32, 32, 'Thẻ', 90000.00, NULL, 'Thành công', '2025-03-26 08:21:52'),
(33, 33, 'MoMo', 15000.00, NULL, 'Thành công', '2025-03-26 08:21:52'),
(34, 34, 'Thẻ', 100000.00, NULL, 'Thành công', '2025-03-26 08:21:52'),
(35, 35, 'Tiền mặt', 90000.00, '', 'Thành công', '2025-03-26 08:26:13'),
(36, 36, 'Thẻ', 50000.00, NULL, 'Thành công', '2025-03-26 08:31:37'),
(37, 37, 'MoMo', 115000.00, NULL, 'Thành công', '2025-03-26 08:31:37'),
(38, 38, 'Tiền mặt', 30000.00, NULL, 'Thành công', '2025-03-26 08:31:37'),
(39, 39, 'Thẻ', 45000.00, NULL, 'Thành công', '2025-03-26 08:31:37'),
(40, 40, 'MoMo', 110000.00, NULL, 'Thành công', '2025-03-26 08:31:37'),
(41, 41, 'Thẻ', 70000.00, NULL, 'Thành công', '2025-03-26 08:31:37'),
(42, 42, 'Tiền mặt', 5000.00, NULL, 'Thành công', '2025-03-26 08:31:37'),
(43, 43, 'Thẻ', 10000.00, NULL, 'Thành công', '2025-03-26 08:31:37'),
(44, 44, 'MoMo', 125000.00, NULL, 'Thành công', '2025-03-26 08:31:37'),
(45, 45, 'Tiền mặt', 105000.00, NULL, 'Thành công', '2025-03-26 08:31:37'),
(46, 46, 'Tiền mặt', 15000.00, NULL, 'Thành công', '2025-03-26 08:31:37'),
(47, 47, 'MoMo', 75000.00, NULL, 'Thành công', '2025-03-26 08:31:37'),
(48, 48, 'Tiền mặt', 25000.00, '', 'Thành công', '2025-03-26 08:34:11'),
(49, 49, 'Tiền mặt', 155000.00, '', 'Thành công', '2025-03-26 08:47:46'),
(50, 50, 'Tiền mặt', 115000.00, '', 'Thành công', '2025-04-06 19:37:06');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `products`
--

CREATE TABLE `products` (
  `product_id` int(11) NOT NULL,
  `product_name` varchar(100) NOT NULL,
  `category_id` int(11) NOT NULL,
  `size` enum('Nhỏ','Vừa','Lớn') NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `status` enum('Hoạt động','Ngừng bán') DEFAULT 'Hoạt động'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `products`
--

INSERT INTO `products` (`product_id`, `product_name`, `category_id`, `size`, `price`, `status`) VALUES
(1, 'Trà sen vàng', 1, 'Nhỏ', 25000.00, 'Hoạt động'),
(2, 'Trà sen vàng', 1, 'Vừa', 30000.00, 'Hoạt động'),
(3, 'Trà sen vàng', 1, 'Lớn', 35000.00, 'Hoạt động'),
(4, 'Cà phê đen', 2, 'Nhỏ', 20000.00, 'Hoạt động'),
(5, 'Cà phê đen', 2, 'Vừa', 25000.00, 'Hoạt động'),
(6, 'Cà phê đen', 2, 'Lớn', 30000.00, 'Hoạt động'),
(7, 'Cà phê sữa', 2, 'Nhỏ', 25000.00, 'Hoạt động'),
(8, 'Cà phê sữa', 2, 'Vừa', 30000.00, 'Hoạt động'),
(9, 'Cà phê sữa', 2, 'Lớn', 35000.00, 'Hoạt động'),
(10, 'Bánh chuối', 3, 'Vừa', 15000.00, 'Hoạt động'),
(11, 'Bánh cookies', 3, 'Vừa', 20000.00, 'Hoạt động'),
(12, 'Nước suối', 4, 'Vừa', 10000.00, 'Hoạt động'),
(13, 'Coca', 4, 'Vừa', 15000.00, 'Hoạt động'),
(14, 'Topping (Trân châu)', 4, 'Nhỏ', 5000.00, 'Hoạt động'),
(15, 'Topping (Thạch)', 4, 'Nhỏ', 5000.00, 'Hoạt động');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `profit_reports`
--

CREATE TABLE `profit_reports` (
  `report_id` int(11) NOT NULL,
  `month` int(11) NOT NULL,
  `year` int(11) NOT NULL,
  `total_revenue` decimal(10,2) NOT NULL,
  `total_cost` decimal(10,2) NOT NULL,
  `total_orders` int(11) NOT NULL DEFAULT 0,
  `net_profit` decimal(10,2) GENERATED ALWAYS AS (`total_revenue` - `total_cost`) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `profit_reports`
--

INSERT INTO `profit_reports` (`report_id`, `month`, `year`, `total_revenue`, `total_cost`, `total_orders`) VALUES
(1, 3, 2025, 2685000.00, 504000.00, 34);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `promotions`
--

CREATE TABLE `promotions` (
  `promo_id` int(11) NOT NULL,
  `discount_code` varchar(50) NOT NULL,
  `discount_value` decimal(10,2) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('Hoạt động','Hết hạn') DEFAULT 'Hoạt động'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `promotions`
--

INSERT INTO `promotions` (`promo_id`, `discount_code`, `discount_value`, `start_date`, `end_date`, `status`) VALUES
(1, 'mnhutdeptrai', 20000.00, '2025-03-18', '2025-04-30', 'Hoạt động'),
(2, 'mnhutngu', 30000.00, '2025-03-18', '2025-04-30', 'Hoạt động');


-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `revenue_reports`
--

CREATE TABLE `revenue_reports` (
  `report_id` int(11) NOT NULL,
  `shift_id` int(11) NOT NULL,
  `total_revenue` decimal(10,2) NOT NULL,
  `total_orders` int(11) NOT NULL,
  `report_date` date DEFAULT curdate(),
  `cash_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `card_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `momo_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `other_amount` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `revenue_reports`
--

INSERT INTO `revenue_reports` (`report_id`, `shift_id`, `total_revenue`, `total_orders`, `report_date`, `cash_amount`, `card_amount`, `momo_amount`, `other_amount`) VALUES
(1, 1, 800000.00, 7, '2025-03-26', 0.00, 0.00, 0.00, 0.00),
(2, 2, 205000.00, 4, '2025-03-26', 0.00, 0.00, 0.00, 0.00);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `shifts`
--

CREATE TABLE `shifts` (
  `shift_id` int(11) NOT NULL,
  `shift_name` varchar(20) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `shifts`
--

INSERT INTO `shifts` (`shift_id`, `shift_name`, `start_time`, `end_time`) VALUES
(1, 'Ca sáng', '07:00:00', '14:00:00'),
(2, 'Ca chiều', '14:00:00', '22:00:00');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `stock_entries`
--

CREATE TABLE `stock_entries` (
  `entry_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_cost` decimal(10,2) NOT NULL,
  `total_cost` decimal(10,2) NOT NULL,
  `entry_datetime` datetime DEFAULT current_timestamp(),
  `entry_type` enum('nhập','xuất') NOT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `stock_transactions`
--

CREATE TABLE `stock_transactions` (
  `transaction_id` int(11) NOT NULL,
  `material_id` int(11) NOT NULL,
  `transaction_type` enum('Nhập','Xuất') NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) DEFAULT NULL,
  `total_amount` decimal(10,2) GENERATED ALWAYS AS (`quantity` * `unit_price`) STORED,
  `transaction_datetime` datetime DEFAULT current_timestamp(),
  `note` text DEFAULT NULL
) ;

--
-- Đang đổ dữ liệu cho bảng `stock_transactions`
--

INSERT INTO `stock_transactions` (`transaction_id`, `material_id`, `transaction_type`, `quantity`, `unit_price`, `transaction_datetime`, `note`) VALUES
(1, 5, 'Xuất', 2, 0.00, '2025-03-27 16:17:48', 'bán'),
(2, 5, 'Nhập', 2, 7000.00, '2025-03-29 11:04:36', ''),
(3, 4, 'Nhập', 5, 10000.00, '2025-03-29 11:04:36', ''),
(4, 8, 'Nhập', 50, 8000.00, '2025-03-29 11:31:13', ''),
(5, 6, 'Nhập', 1, 40000.00, '2025-03-29 11:38:00', '');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `suppliers`
--

CREATE TABLE `suppliers` (
  `supplier_id` int(11) NOT NULL,
  `supplier_name` varchar(100) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `status` enum('Hoạt động','Ngừng hợp tác') DEFAULT 'Hoạt động'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `suppliers`
--

INSERT INTO `suppliers` (`supplier_id`, `supplier_name`, `contact_person`, `phone`, `email`, `address`, `status`) VALUES
(1, 'Công ty Trà Việt', 'Nguyễn Văn A', '0901234567', 'tra.viet@gmail.com', '123 Đường Trà, Quận 1, TP.HCM', 'Hoạt động'),
(2, 'Nhà cung cấp Cà phê Nguyên Chất', 'Trần Thị B', '0912345678', 'caphe.nc@gmail.com', '456 Đường Cà Phê, Đà Lạt', 'Hoạt động'),
(3, 'Công ty Bánh Ngọt ABC', 'Lê Văn C', '0933456789', 'banhngot.abc@gmail.com', '789 Đường Bánh, Quận 3, TP.HCM', 'Hoạt động'),
(4, 'Đại lý Nước Giải Khát XYZ', 'Phạm Thị D', '0944567890', 'nuocgk.xyz@gmail.com', '101 Đường Nước, Quận 7, TP.HCM', 'Hoạt động'),
(5, 'Nhà cung cấp Topping HN', 'Hoàng Văn E', '0955678901', 'topping.hn@gmail.com', '202 Đường Topping, Hà Nội', 'Hoạt động');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `tables`
--

CREATE TABLE `tables` (
  `table_id` varchar(10) NOT NULL,
  `table_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `tables`
--

INSERT INTO `tables` (`table_id`, `table_name`) VALUES
('TB1', 'Bàn 1'),
('TB2', 'Bàn 2'),
('TB3', 'Bàn 3'),
('TB4', 'Bàn 4'),
('TB5', 'Bàn 5'),
('TB6', 'Bàn 6'),
('TB7', 'Bàn 7');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('Quản lý','Ca sáng','Ca chiều') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `users`
--

INSERT INTO `users` (`user_id`, `username`, `password`, `role`) VALUES
(1, 'S01', '$2y$10$NT2sr9ZNevlfYlibWvWTmO2ZueBqY7ff..zowh0t1F./x0TK16YTq', 'Ca sáng'),
(2, 'C01', '$2y$10$6N2MxFeRUJIs0GDnNLcJ0OUj8EoMstYpIs4YHfwYSTOTbk3jtXtxm', 'Ca chiều'),
(3, 'quanly1', '$2y$10$7rFUZ76VKVPseACp6eHDIuLCJyL9C5m66uDHsV9VAxsfGSNvHyS32', 'Quản lý'),
(5, 'S02', '$2y$10$91bYXJkJB5w94fW6wwd9peddQCVJ3TdTPgRTyyqIoFpcbtVWy/F8K', 'Ca sáng');

--
-- Chỉ mục cho các bảng đã đổ
--

--
-- Chỉ mục cho bảng `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`);

--
-- Chỉ mục cho bảng `materials`
--
ALTER TABLE `materials`
  ADD PRIMARY KEY (`material_id`),
  ADD KEY `supplier_id` (`supplier_id`);

--
-- Chỉ mục cho bảng `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `table_id` (`table_id`),
  ADD KEY `shift_id` (`shift_id`),
  ADD KEY `promo_id` (`promo_id`);

--
-- Chỉ mục cho bảng `order_details`
--
ALTER TABLE `order_details`
  ADD PRIMARY KEY (`order_id`,`product_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Chỉ mục cho bảng `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `order_id` (`order_id`);

--
-- Chỉ mục cho bảng `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`product_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Chỉ mục cho bảng `profit_reports`
--
ALTER TABLE `profit_reports`
  ADD PRIMARY KEY (`report_id`),
  ADD UNIQUE KEY `month` (`month`,`year`);

--
-- Chỉ mục cho bảng `promotions`
--
ALTER TABLE `promotions`
  ADD PRIMARY KEY (`promo_id`),
  ADD UNIQUE KEY `discount_code` (`discount_code`);

--
-- Chỉ mục cho bảng `revenue_reports`
--
ALTER TABLE `revenue_reports`
  ADD PRIMARY KEY (`report_id`),
  ADD KEY `shift_id` (`shift_id`);

--
-- Chỉ mục cho bảng `shifts`
--
ALTER TABLE `shifts`
  ADD PRIMARY KEY (`shift_id`);

--
-- Chỉ mục cho bảng `stock_entries`
--
ALTER TABLE `stock_entries`
  ADD PRIMARY KEY (`entry_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Chỉ mục cho bảng `stock_transactions`
--
ALTER TABLE `stock_transactions`
  ADD PRIMARY KEY (`transaction_id`),
  ADD KEY `material_id` (`material_id`);

--
-- Chỉ mục cho bảng `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`supplier_id`);

--
-- Chỉ mục cho bảng `tables`
--
ALTER TABLE `tables`
  ADD PRIMARY KEY (`table_id`);

--
-- Chỉ mục cho bảng `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT cho các bảng đã đổ
--

--
-- AUTO_INCREMENT cho bảng `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT cho bảng `materials`
--
ALTER TABLE `materials`
  MODIFY `material_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT cho bảng `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT cho bảng `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT cho bảng `products`
--
ALTER TABLE `products`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT cho bảng `profit_reports`
--
ALTER TABLE `profit_reports`
  MODIFY `report_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT cho bảng `promotions`
--
ALTER TABLE `promotions`
  MODIFY `promo_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT cho bảng `revenue_reports`
--
ALTER TABLE `revenue_reports`
  MODIFY `report_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT cho bảng `shifts`
--
ALTER TABLE `shifts`
  MODIFY `shift_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT cho bảng `stock_entries`
--
ALTER TABLE `stock_entries`
  MODIFY `entry_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `stock_transactions`
--
ALTER TABLE `stock_transactions`
  MODIFY `transaction_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `supplier_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT cho bảng `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Các ràng buộc cho các bảng đã đổ
--

--
-- Các ràng buộc cho bảng `materials`
--
ALTER TABLE `materials`
  ADD CONSTRAINT `materials_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`);

--
-- Các ràng buộc cho bảng `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`table_id`) REFERENCES `tables` (`table_id`),
  ADD CONSTRAINT `orders_ibfk_3` FOREIGN KEY (`shift_id`) REFERENCES `shifts` (`shift_id`),
  ADD CONSTRAINT `orders_ibfk_4` FOREIGN KEY (`promo_id`) REFERENCES `promotions` (`promo_id`);

--
-- Các ràng buộc cho bảng `order_details`
--
ALTER TABLE `order_details`
  ADD CONSTRAINT `order_details_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_details_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`);

--
-- Các ràng buộc cho bảng `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`);

--
-- Các ràng buộc cho bảng `revenue_reports`
--
ALTER TABLE `revenue_reports`
  ADD CONSTRAINT `revenue_reports_ibfk_1` FOREIGN KEY (`shift_id`) REFERENCES `shifts` (`shift_id`);

--
-- Các ràng buộc cho bảng `stock_entries`
--
ALTER TABLE `stock_entries`
  ADD CONSTRAINT `stock_entries_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`);

--
-- Các ràng buộc cho bảng `stock_transactions`
--
ALTER TABLE `stock_transactions`
  ADD CONSTRAINT `stock_transactions_ibfk_1` FOREIGN KEY (`material_id`) REFERENCES `materials` (`material_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
