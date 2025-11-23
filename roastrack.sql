-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 22, 2025 at 06:18 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `roastrack`
--

-- --------------------------------------------------------

--
-- Table structure for table `backups`
--

CREATE TABLE `backups` (
  `id` int(11) NOT NULL,
  `backup_file` varchar(255) NOT NULL,
  `backup_size` bigint(20) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `deliveries`
--

CREATE TABLE `deliveries` (
  `id` int(11) NOT NULL,
  `delivery_number` varchar(50) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `status` enum('ORDERED','IN_TRANSIT','DELIVERED','CANCELLED') DEFAULT 'ORDERED',
  `expected_date` date DEFAULT NULL,
  `actual_date` date DEFAULT NULL,
  `total_cost` decimal(10,2) DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `delivery_items`
--

CREATE TABLE `delivery_items` (
  `id` int(11) NOT NULL,
  `delivery_id` int(11) NOT NULL,
  `inventory_item_id` int(11) NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit_cost` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `gcash_accounts`
--

CREATE TABLE `gcash_accounts` (
  `id` int(11) NOT NULL,
  `label` varchar(100) NOT NULL,
  `account_name` varchar(150) NOT NULL,
  `account_number` varchar(50) NOT NULL,
  `instructions` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_verified` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_items`
--

CREATE TABLE `inventory_items` (
  `id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `category` enum('MEAT','SAUCE','VEGETABLE','SUPPLIES','FINISHED') NOT NULL,
  `description` text DEFAULT NULL,
  `unit` varchar(50) DEFAULT 'kg',
  `quantity` decimal(10,2) DEFAULT 0.00,
  `reorder_level` decimal(10,2) DEFAULT 10.00,
  `unit_price` decimal(10,2) DEFAULT 0.00,
  `supplier_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `image_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inventory_items`
--

INSERT INTO `inventory_items` (`id`, `name`, `category`, `description`, `unit`, `quantity`, `reorder_level`, `unit_price`, `supplier_id`, `created_at`, `updated_at`, `image_path`) VALUES
(5, 'Isaw (3 for ₱20)', 'FINISHED', 'is a popular Filipino street food made from grilled chicken or pork intestines. The intestines are thoroughly cleaned, boiled, and then skewered before being grilled over charcoal until slightly charred. It is typically served with a spicy vinegar-based dipping sauce and is often enjoyed as a snack, especially when dipped in chili vinegar with onions.', 'set', 30.00, 10.00, 20.00, 1, '2025-11-07 07:31:33', '2025-11-22 07:14:03', '/uploads/items/item_1763795643_03d4ee5c.png'),
(8, 'Chicken Skin', 'FINISHED', 'a cooking technique that leaves the skin on chicken to be grilled, with the goal of achieving a crispy, smoky exterior and juicy meat inside. The process involves drying the skin, salting it, and cooking it over indirect heat before finishing with direct heat and a sauce, which caramelizes and adds crispiness.', 'pcs', 30.00, 10.00, 20.00, 1, '2025-11-07 07:31:33', '2025-11-22 07:13:13', '/uploads/items/item_1763795593_97414d9b.png'),
(9, 'Pakpak', 'FINISHED', 'typically marinated in a sweet and savory mixture and then grilled over charcoal.', 'pcs', 40.00, 10.00, 30.00, NULL, '2025-11-08 08:01:32', '2025-11-22 13:40:01', '/uploads/items/item_1763818801_684470af.png'),
(10, 'Paa', 'FINISHED', 'chicken feet are skewered, marinated, and grilled. They are an acquired taste, known for their unique collagen-rich texture. In local colloquialism, they might also be referred to as "chicken paws".', 'pcs', 40.00, 10.00, 40.00, NULL, '2025-11-08 08:01:32', '2025-11-22 13:38:57', '/uploads/items/item_1763818737_11de67c6.png'),
(11, 'Atay', 'FINISHED', 'Atay is a popular street food often seasoned with a mixture of banana ketchup, soy sauce, and other spices, and is commonly served with a vinegar-based dipping sauce.', 'pcs', 40.00, 10.00, 20.00, NULL, '2025-11-08 08:01:32', '2025-11-22 05:31:10', '/uploads/items/item_1763789470_eba6eafc.jpg'),
(12, 'Balunbalunan', 'FINISHED', 'It is a common ingredient in Filipino cuisine, prepared in various ways, often grilled on a skewer or stewed in dishes like adobo or ginataan', 'pcs', 40.00, 10.00, 20.00, NULL, '2025-11-08 08:01:32', '2025-11-22 05:42:50', '/uploads/items/item_1763790170_10979f9d.png'),
(13, 'Isol', 'FINISHED', 'The chicken tail meat is typically marinated in a mixture of vinegar and spices (similar to the style of chicken inasal) and then skewered and grilled over an open flame. The resulting dish is known for its tender, juicy, and fatty texture, and is often served with a side of garlic rice and dipping sauces.', 'pcs', 30.00, 10.00, 20.00, NULL, '2025-11-08 08:01:32', '2025-11-22 13:35:16', '/uploads/items/item_1763818516_61cfea1c.png');

-- --------------------------------------------------------

--
-- Table structure for table `reservations`
--

CREATE TABLE `reservations` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `customer_name` varchar(150) DEFAULT NULL,
  `customer_phone` varchar(50) DEFAULT NULL,
  `status` enum('PENDING','CONFIRMED','READY','COMPLETED','CANCELLED') DEFAULT 'PENDING',
  `pickup_at` datetime NOT NULL,
  `notes` text DEFAULT NULL,
  `payment_method` enum('PICKUP','GCASH') DEFAULT 'PICKUP',
  `gcash_proof_path` varchar(255) DEFAULT NULL,
  `gcash_account_label` varchar(150) DEFAULT NULL,
  `gcash_account_number` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `reservations`
--

INSERT INTO `reservations` (`id`, `user_id`, `customer_name`, `customer_phone`, `status`, `pickup_at`, `notes`, `payment_method`, `gcash_proof_path`, `gcash_account_label`, `gcash_account_number`, `created_at`, `updated_at`) VALUES
(1, 3, 'Fredrex Salac', '09856183523', 'COMPLETED', '2025-11-22 23:25:00', 'extra sauce', 'PICKUP', NULL, 'Main GCash', '09515019614', '2025-11-22 15:25:57', '2025-11-22 15:58:30'),
(2, 3, 'Fredrex Salac', '09856183523', 'CANCELLED', '2025-11-24 15:09:00', 'Extra Sauce', 'PICKUP', NULL, 'Main GCash', '09515019614', '2025-11-22 16:43:12', '2025-11-22 17:00:58'),
(3, 3, 'Fredrex Salac', '09856183523', 'COMPLETED', '2025-11-25 18:01:00', 'Sauce', 'PICKUP', NULL, 'Main GCash', '09515019614', '2025-11-22 17:01:57', '2025-11-22 17:03:52');

-- --------------------------------------------------------

--
-- Table structure for table `reservation_items`
--

CREATE TABLE `reservation_items` (
  `id` int(11) NOT NULL,
  `reservation_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `item_name` varchar(200) NOT NULL,
  `qty` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `reservation_items`
--

INSERT INTO `reservation_items` (`id`, `reservation_id`, `item_id`, `item_name`, `qty`, `unit_price`, `created_at`) VALUES
(1, 1, 12, 'Balunbalunan', 1, 20.00, '2025-11-22 15:25:57'),
(2, 1, 0, 'Free Sabaw', 1, 0.00, '2025-11-22 15:25:57'),
(3, 2, 11, 'Atay', 1, 20.00, '2025-11-22 16:43:12'),
(4, 2, 12, 'Balunbalunan', 1, 20.00, '2025-11-22 16:43:12'),
(5, 3, 8, 'Chicken Skin', 1, 20.00, '2025-11-22 17:01:57'),
(6, 3, 11, 'Atay', 1, 20.00, '2025-11-22 17:01:57'),
(7, 3, 12, 'Balunbalunan', 1, 20.00, '2025-11-22 17:01:57');

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `id` int(11) NOT NULL,
  `sale_number` varchar(50) NOT NULL,
  `customer_name` varchar(200) DEFAULT NULL,
  `customer_phone` varchar(50) DEFAULT NULL,
  `status` enum('PENDING','COMPLETED','CANCELLED') DEFAULT 'PENDING',
  `total_amount` decimal(10,2) DEFAULT 0.00,
  `payment_method` varchar(50) DEFAULT 'Cash',
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sale_items`
--

CREATE TABLE `sale_items` (
  `id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `inventory_item_id` int(11) NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `contact_person` varchar(200) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `name`, `contact_person`, `phone`, `email`, `address`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Default Grill', 'Owner', NULL, NULL, NULL, 1, '2025-11-07 07:31:33', '2025-11-07 07:31:33');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','staff','customer') NOT NULL DEFAULT 'customer',
  `full_name` varchar(150) NOT NULL,
  `phone` varchar(50) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `backups`
--
ALTER TABLE `backups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `deliveries`
--
ALTER TABLE `deliveries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `delivery_number` (`delivery_number`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `delivery_items`
--
ALTER TABLE `delivery_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `delivery_id` (`delivery_id`),
  ADD KEY `inventory_item_id` (`inventory_item_id`);

--
-- Indexes for table `gcash_accounts`
--
ALTER TABLE `gcash_accounts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `inventory_items`
--
ALTER TABLE `inventory_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `supplier_id` (`supplier_id`);

--
-- Indexes for table `reservations`
--
ALTER TABLE `reservations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_res_pickup` (`pickup_at`),
  ADD KEY `idx_res_status_pickup` (`status`,`pickup_at`);

--
-- Indexes for table `reservation_items`
--
ALTER TABLE `reservation_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_res_items_res` (`reservation_id`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sale_number` (`sale_number`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sale_id` (`sale_id`),
  ADD KEY `inventory_item_id` (`inventory_item_id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_users_phone` (`phone`),
  ADD KEY `idx_users_email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `backups`
--
ALTER TABLE `backups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `deliveries`
--
ALTER TABLE `deliveries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `delivery_items`
--
ALTER TABLE `delivery_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `gcash_accounts`
--
ALTER TABLE `gcash_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory_items`
--
ALTER TABLE `inventory_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `reservations`
--
ALTER TABLE `reservations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `reservation_items`
--
ALTER TABLE `reservation_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sale_items`
--
ALTER TABLE `sale_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `backups`
--
ALTER TABLE `backups`
  ADD CONSTRAINT `backups_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `deliveries`
--
ALTER TABLE `deliveries`
  ADD CONSTRAINT `deliveries_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  ADD CONSTRAINT `deliveries_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `delivery_items`
--
ALTER TABLE `delivery_items`
  ADD CONSTRAINT `delivery_items_ibfk_1` FOREIGN KEY (`delivery_id`) REFERENCES `deliveries` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `delivery_items_ibfk_2` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`);

--
-- Constraints for table `inventory_items`
--
ALTER TABLE `inventory_items`
  ADD CONSTRAINT `inventory_items_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`);

--
-- Constraints for table `reservation_items`
--
ALTER TABLE `reservation_items`
  ADD CONSTRAINT `fk_res_items_res` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sales`
--
ALTER TABLE `sales`
  ADD CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD CONSTRAINT `sale_items_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sale_items_ibfk_2` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
