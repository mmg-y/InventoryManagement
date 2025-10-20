-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 20, 2025 at 07:43 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `super_market`
--

-- --------------------------------------------------------

--
-- Table structure for table `carts`
--

CREATE TABLE `carts` (
  `cart_id` int(11) NOT NULL,
  `seller` int(11) NOT NULL,
  `status` varchar(100) NOT NULL,
  `order_code` varchar(255) NOT NULL,
  `total` float NOT NULL,
  `failed_desc` varchar(255) DEFAULT NULL,
  `total_earning` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `carts`
--

INSERT INTO `carts` (`cart_id`, `seller`, `status`, `order_code`, `total`, `failed_desc`, `total_earning`, `created_at`, `updated_at`) VALUES
(9, 2, 'pending', '20251019-0001', 0, NULL, 0.00, '2025-10-19 13:03:47', '2025-10-19 13:03:47');

-- --------------------------------------------------------

--
-- Table structure for table `cart_items`
--

CREATE TABLE `cart_items` (
  `cart_items_id` int(11) NOT NULL,
  `cart_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `qty` int(11) NOT NULL,
  `price` float NOT NULL,
  `batch_id` int(11) NOT NULL,
  `cost_price` decimal(10,2) NOT NULL,
  `earning` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `category`
--

CREATE TABLE `category` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(255) NOT NULL,
  `retail_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `category`
--

INSERT INTO `category` (`category_id`, `category_name`, `retail_id`, `created_at`, `updated_at`) VALUES
(1, 'Grains', 1, '2025-10-13 10:31:39', '2025-10-13 10:31:39'),
(2, 'Beverages', 1, '2025-10-13 10:31:39', '2025-10-13 10:31:39'),
(3, 'Snacks', 2, '2025-10-13 10:31:39', '2025-10-13 10:31:39');

-- --------------------------------------------------------

--
-- Table structure for table `product`
--

CREATE TABLE `product` (
  `product_id` int(11) NOT NULL,
  `product_code` varchar(100) NOT NULL,
  `product_name` varchar(100) NOT NULL,
  `category` int(11) DEFAULT NULL,
  `retail_id` int(11) DEFAULT NULL,
  `total_quantity` int(11) DEFAULT NULL,
  `reserved_qty` int(11) DEFAULT NULL,
  `cost_price` decimal(10,0) NOT NULL,
  `threshold_id` int(11) DEFAULT NULL,
  `threshold` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product`
--

INSERT INTO `product` (`product_id`, `product_code`, `product_name`, `category`, `retail_id`, `total_quantity`, `reserved_qty`, `cost_price`, `threshold_id`, `threshold`, `created_at`, `updated_at`) VALUES
(7, 'prod-001', 'rc', 2, 1, 54, 0, 58, 3, 10, '2025-10-19 10:49:39', '2025-10-20 05:24:04'),
(8, 'prod-002', 'chicken skin', 3, 2, 19, 0, 58, 3, 20, '2025-10-19 10:49:39', '2025-10-20 05:24:11');

-- --------------------------------------------------------

--
-- Table structure for table `product_stocks`
--

CREATE TABLE `product_stocks` (
  `product_stock_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `batch_number` varchar(255) NOT NULL,
  `quantity` int(11) NOT NULL,
  `remaining_qty` int(11) DEFAULT NULL,
  `cost_price` decimal(10,2) NOT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `supplier_id` int(11) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `cancel_details` varchar(255) DEFAULT NULL,
  `status` enum('pulled out','active','cancelled','ordered','received') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_stocks`
--

INSERT INTO `product_stocks` (`product_stock_id`, `product_id`, `batch_number`, `quantity`, `remaining_qty`, `cost_price`, `remarks`, `created_at`, `supplier_id`, `updated_at`, `cancel_details`, `status`) VALUES
(12, 8, 'BATCH-20251019-134256-566', 100, 100, 50.00, 'uvusbid', '2025-10-19 19:42:56', 3, '2025-10-19 19:58:54', NULL, 'pulled out'),
(13, 8, 'BATCH-20251019-135804-739', 19, 19, 54.00, 'jdjdjcjc', '2025-10-19 19:58:04', 3, '2025-10-20 13:24:11', NULL, 'active'),
(14, 7, 'BATCH-20251020-071119-561', 54, 54, 50.00, 'jdjdjd', '2025-10-20 13:11:19', 2, '2025-10-20 13:24:04', NULL, 'active');

-- --------------------------------------------------------

--
-- Table structure for table `product_suppliers`
--

CREATE TABLE `product_suppliers` (
  `product_supplier_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `default_cost_price` decimal(10,2) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_suppliers`
--

INSERT INTO `product_suppliers` (`product_supplier_id`, `product_id`, `supplier_id`, `default_cost_price`, `created_at`, `updated_at`) VALUES
(16, 7, 2, 50.00, '2025-10-19 18:50:57', '0000-00-00 00:00:00'),
(17, 8, 3, 54.00, '2025-10-19 18:50:57', '2025-10-19 19:57:46');

-- --------------------------------------------------------

--
-- Table structure for table `retail_variables`
--

CREATE TABLE `retail_variables` (
  `retail_id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `percent` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `retail_variables`
--

INSERT INTO `retail_variables` (`retail_id`, `name`, `percent`, `created_at`, `updated_at`) VALUES
(1, 'Standard Retail', 15, '2025-10-13 10:31:26', '2025-10-13 10:31:26'),
(2, 'Wholesale', 8, '2025-10-13 10:31:26', '2025-10-13 10:31:26');

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `sales_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `total_earning` decimal(10,2) NOT NULL,
  `sale_date` datetime NOT NULL DEFAULT current_timestamp(),
  `cashier_id` int(11) NOT NULL,
  `remarks` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sales`
--

INSERT INTO `sales` (`sales_id`, `total_amount`, `total_earning`, `sale_date`, `cashier_id`, `remarks`) VALUES
(1, 3500.00, 500.00, '2025-10-13 10:32:44', 1, 'Morning sale batch'),
(2, 1200.00, 180.00, '2025-10-13 10:32:44', 1, 'Afternoon sale batch');

-- --------------------------------------------------------

--
-- Table structure for table `sales_items`
--

CREATE TABLE `sales_items` (
  `sales_items_id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `cost_price` decimal(10,2) NOT NULL,
  `earning` decimal(10,2) NOT NULL,
  `remarks` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `status`
--

CREATE TABLE `status` (
  `status_id` int(11) NOT NULL,
  `status_label` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `status`
--

INSERT INTO `status` (`status_id`, `status_label`, `created_at`, `updated_at`) VALUES
(1, 'sufficient', '2025-10-03 12:11:22', '2025-10-03 12:11:22'),
(2, 'low', '2025-10-03 12:11:39', '2025-10-03 12:11:39'),
(3, 'critical', '2025-10-03 12:11:45', '2025-10-03 12:11:45'),
(4, 'out of stock', '2025-10-03 12:12:11', '2025-10-03 12:12:11');

-- --------------------------------------------------------

--
-- Table structure for table `supplier`
--

CREATE TABLE `supplier` (
  `supplier_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `contact` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `supplier_type` varchar(250) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `supplier`
--

INSERT INTO `supplier` (`supplier_id`, `name`, `contact`, `email`, `supplier_type`, `created_at`, `updated_at`) VALUES
(1, 'Golden Harvest Trading', '09171234567', 'goldenharvest@gmail.com', 'Distributor', '2025-10-13 02:31:54', '2025-10-13 02:31:54'),
(2, 'AquaPure Distributors', '09181234567', 'aquapure@gmail.com', 'Beverage Supplier', '2025-10-13 02:31:54', '2025-10-13 02:31:54'),
(3, 'SnackTime Wholesale', '09281234567', 'snacktime@gmail.com', 'Snack Supplier', '2025-10-13 02:31:54', '2025-10-13 02:31:54');

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `id` int(11) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `contact` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(100) NOT NULL,
  `profile_pic` varchar(255) DEFAULT NULL,
  `type` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`id`, `first_name`, `last_name`, `contact`, `email`, `username`, `password`, `profile_pic`, `type`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'admin', '09123456789', 'admin@gmail.com', 'admin', '$2y$10$7KZF.CeusYS08Ba/lF2NzuyvfEo/m/b8J1SuLhZnxNcQ/fq0wNFw.', NULL, 'admin', '2025-10-01 22:02:12', '2025-10-02 04:02:12'),
(2, 'staff', 'staff', '09876543212', 'staff@gmail.com', 'staff', '$2y$10$pIF../PxTP3ZZRR4UoejDeegFgcgmIXmCARsvctiFjVcj0kN2Ok4q', NULL, 'cashier', '2025-10-02 04:18:24', '2025-10-02 10:18:24'),
(3, 'budegero', 'budegero', '09876543212', 'budegero@gmail.com', 'budegero', '$2y$10$iWuREL.D13UxiWfDdjTQ/OSwaD47tXz.ixKiaE7b28zi7PgWdOVJW', NULL, 'bodegero', '2025-10-02 22:02:03', '2025-10-03 04:02:03'),
(4, 'budegero', 'budegero', '09876543212', 'budegero@gmail.com', 'budegero', 'budegero', NULL, 'warehouse_man', '2025-10-09 09:18:59', '2025-10-09 09:18:59'),
(5, 'bodegero', 'bodegero', '09876543212', 'bodegero@gmail.com', 'bodegero', '$2y$10$SMSR7R9BURRw99cdP5l/auRc4bEJV9XWQ6BLQ/AFdd0HjvlbZi7ey', NULL, 'warehouse_man', '2025-10-09 06:29:46', '2025-10-09 12:29:46');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `carts`
--
ALTER TABLE `carts`
  ADD PRIMARY KEY (`cart_id`),
  ADD KEY `seller` (`seller`);

--
-- Indexes for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD PRIMARY KEY (`cart_items_id`),
  ADD KEY `cart_id` (`cart_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `category`
--
ALTER TABLE `category`
  ADD PRIMARY KEY (`category_id`);

--
-- Indexes for table `product`
--
ALTER TABLE `product`
  ADD PRIMARY KEY (`product_id`),
  ADD KEY `category` (`category`),
  ADD KEY `retail_id` (`retail_id`),
  ADD KEY `threshold_id` (`threshold_id`);

--
-- Indexes for table `product_stocks`
--
ALTER TABLE `product_stocks`
  ADD PRIMARY KEY (`product_stock_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `supplier_id` (`supplier_id`);

--
-- Indexes for table `product_suppliers`
--
ALTER TABLE `product_suppliers`
  ADD PRIMARY KEY (`product_supplier_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `supplier_id` (`supplier_id`);

--
-- Indexes for table `retail_variables`
--
ALTER TABLE `retail_variables`
  ADD PRIMARY KEY (`retail_id`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`sales_id`),
  ADD KEY `cashier_id` (`cashier_id`);

--
-- Indexes for table `sales_items`
--
ALTER TABLE `sales_items`
  ADD PRIMARY KEY (`sales_items_id`),
  ADD KEY `sale_id` (`sale_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `batch_id` (`batch_id`);

--
-- Indexes for table `status`
--
ALTER TABLE `status`
  ADD PRIMARY KEY (`status_id`);

--
-- Indexes for table `supplier`
--
ALTER TABLE `supplier`
  ADD PRIMARY KEY (`supplier_id`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `carts`
--
ALTER TABLE `carts`
  MODIFY `cart_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `cart_items`
--
ALTER TABLE `cart_items`
  MODIFY `cart_items_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `category`
--
ALTER TABLE `category`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `product`
--
ALTER TABLE `product`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `product_stocks`
--
ALTER TABLE `product_stocks`
  MODIFY `product_stock_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `product_suppliers`
--
ALTER TABLE `product_suppliers`
  MODIFY `product_supplier_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `retail_variables`
--
ALTER TABLE `retail_variables`
  MODIFY `retail_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `sales_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `sales_items`
--
ALTER TABLE `sales_items`
  MODIFY `sales_items_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `status`
--
ALTER TABLE `status`
  MODIFY `status_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `supplier`
--
ALTER TABLE `supplier`
  MODIFY `supplier_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `carts`
--
ALTER TABLE `carts`
  ADD CONSTRAINT `carts_ibfk_1` FOREIGN KEY (`seller`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD CONSTRAINT `cart_items_ibfk_1` FOREIGN KEY (`cart_id`) REFERENCES `carts` (`cart_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `cart_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `product` (`product_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `product`
--
ALTER TABLE `product`
  ADD CONSTRAINT `product_ibfk_1` FOREIGN KEY (`category`) REFERENCES `category` (`category_id`),
  ADD CONSTRAINT `product_ibfk_2` FOREIGN KEY (`retail_id`) REFERENCES `retail_variables` (`retail_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `product_ibfk_3` FOREIGN KEY (`threshold_id`) REFERENCES `status` (`status_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `product_stocks`
--
ALTER TABLE `product_stocks`
  ADD CONSTRAINT `product_stocks_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `product` (`product_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `product_stocks_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `supplier` (`supplier_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `product_suppliers`
--
ALTER TABLE `product_suppliers`
  ADD CONSTRAINT `product_suppliers_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `product` (`product_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_suppliers_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `supplier` (`supplier_id`) ON DELETE CASCADE;

--
-- Constraints for table `sales`
--
ALTER TABLE `sales`
  ADD CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`cashier_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `sales_items`
--
ALTER TABLE `sales_items`
  ADD CONSTRAINT `sales_items_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`sales_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `sales_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `product` (`product_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `sales_items_ibfk_3` FOREIGN KEY (`batch_id`) REFERENCES `product_stocks` (`product_stock_id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
