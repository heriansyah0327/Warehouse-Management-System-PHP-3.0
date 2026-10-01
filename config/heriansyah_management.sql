-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Oct 01, 2026 at 09:30 PM
-- Server version: 5.7.44-cll-lve
-- PHP Version: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `heriansyah_management`
--

-- --------------------------------------------------------

--
-- Table structure for table `jual_barang`
--

CREATE TABLE `jual_barang` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'Yang mengajukan jual barang',
  `catatan` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('menunggu','diacc','ditolak','dibatalkan') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'menunggu',
  `acc_by` int(11) DEFAULT NULL,
  `acc_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `jual_barang`
--

INSERT INTO `jual_barang` (`id`, `user_id`, `catatan`, `status`, `acc_by`, `acc_at`, `created_at`, `updated_at`) VALUES
(1, 6, '', 'diacc', 6, '2026-09-26 11:06:05', '2026-09-26 11:05:55', '2026-09-26 11:06:05');

-- --------------------------------------------------------

--
-- Table structure for table `jual_barang_item`
--

CREATE TABLE `jual_barang_item` (
  `id` int(11) NOT NULL,
  `jual_barang_id` int(11) NOT NULL,
  `produk_id` int(11) DEFAULT NULL,
  `nama_produk` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `qty` int(11) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `jual_barang_item`
--

INSERT INTO `jual_barang_item` (`id`, `jual_barang_id`, `produk_id`, `nama_produk`, `qty`) VALUES
(1, 1, 36, 'Metal Scrap', 100),
(2, 1, 37, 'Spesial Metal', 100);

-- --------------------------------------------------------

--
-- Table structure for table `jual_barang_whitelist`
--

CREATE TABLE `jual_barang_whitelist` (
  `produk_id` int(11) NOT NULL COMMENT 'Produk yang boleh dijual homies lewat Jual Barang',
  `added_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `jual_barang_whitelist`
--

INSERT INTO `jual_barang_whitelist` (`produk_id`, `added_by`, `created_at`) VALUES
(36, 6, '2026-09-26 11:05:10'),
(37, 6, '2026-09-26 11:05:10');

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_request`
--

CREATE TABLE `password_reset_request` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `discord_id_input` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `catatan` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('menunggu','disetujui','ditolak') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'menunggu',
  `processed_by` int(11) DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pemrosesan_whitelist`
--

CREATE TABLE `pemrosesan_whitelist` (
  `produk_id` int(11) NOT NULL COMMENT 'Produk Spesial yang boleh diproses homies lewat Ajukan Pemrosesan',
  `added_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pemrosesan_whitelist`
--

INSERT INTO `pemrosesan_whitelist` (`produk_id`, `added_by`, `created_at`) VALUES
(29, 6, '2026-09-26 11:40:02'),
(30, 6, '2026-09-26 11:40:02'),
(31, 6, '2026-09-26 11:40:02'),
(32, 6, '2026-09-26 11:40:02'),
(33, 6, '2026-09-26 11:40:02'),
(34, 6, '2026-09-26 11:40:02'),
(35, 6, '2026-09-26 11:40:02');

-- --------------------------------------------------------

--
-- Table structure for table `pesanan`
--

CREATE TABLE `pesanan` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `total` decimal(14,2) NOT NULL DEFAULT '0.00',
  `catatan` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('menunggu','selesai','dibatalkan','ditolak') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'menunggu',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pesanan`
--

INSERT INTO `pesanan` (`id`, `user_id`, `total`, `catatan`, `status`, `created_at`, `updated_at`) VALUES
(5, 6, 242500.00, 'Abis badai ya bang', 'ditolak', '2026-09-25 11:39:48', '2026-09-25 17:10:08'),
(6, 25, 42000.00, '', 'selesai', '2026-09-27 15:37:02', '2026-09-27 15:49:09'),
(7, 26, 29500.00, '', 'selesai', '2026-09-27 16:22:51', '2026-09-27 16:44:20'),
(8, 26, 10000.00, '', 'selesai', '2026-09-27 16:25:44', '2026-09-27 16:44:17'),
(9, 19, 55000.00, '', 'selesai', '2026-09-27 17:22:37', '2026-09-28 16:44:09'),
(10, 14, 390000.00, '', 'selesai', '2026-09-28 16:52:38', '2026-09-28 16:58:41'),
(11, 19, 550000.00, '', 'ditolak', '2026-09-28 16:58:24', '2026-09-28 16:59:04'),
(12, 19, 70000.00, '', 'ditolak', '2026-09-28 16:59:41', '2026-09-28 17:05:40'),
(13, 19, 240000.00, '', 'ditolak', '2026-09-28 17:00:07', '2026-09-28 17:05:48'),
(14, 19, 275000.00, '', 'selesai', '2026-09-28 17:01:30', '2026-09-28 17:23:18'),
(15, 11, 210000.00, '', 'selesai', '2026-09-29 22:11:04', '2026-09-29 22:14:22'),
(16, 30, 132000.00, '', 'dibatalkan', '2026-09-29 22:11:37', '2026-09-29 22:12:25'),
(17, 11, 4000.00, '', 'selesai', '2026-09-29 22:12:33', '2026-09-29 22:14:27'),
(18, 30, 133000.00, '', 'selesai', '2026-09-29 22:14:02', '2026-09-29 22:17:04'),
(19, 20, 150000.00, '', 'selesai', '2026-09-30 10:45:33', '2026-09-30 10:58:32'),
(20, 27, 40000.00, '', 'menunggu', '2026-09-30 15:30:51', '2026-09-30 15:30:51'),
(21, 25, 40000.00, '', 'selesai', '2026-09-30 18:18:23', '2026-09-30 18:24:30'),
(22, 12, 145000.00, '', 'selesai', '2026-09-30 20:22:40', '2026-09-30 20:25:09'),
(23, 31, 173900.00, '', 'selesai', '2026-10-01 08:12:49', '2026-10-01 08:19:04'),
(24, 14, 70000.00, '', 'selesai', '2026-10-01 08:41:49', '2026-10-01 08:43:40');

-- --------------------------------------------------------

--
-- Table structure for table `pesanan_item`
--

CREATE TABLE `pesanan_item` (
  `id` int(11) NOT NULL,
  `pesanan_id` int(11) NOT NULL,
  `produk_id` int(11) DEFAULT NULL,
  `nama_produk` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `harga` decimal(14,2) NOT NULL DEFAULT '0.00',
  `qty` int(11) NOT NULL DEFAULT '1',
  `subtotal` decimal(14,2) NOT NULL DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pesanan_item`
--

INSERT INTO `pesanan_item` (`id`, `pesanan_id`, `produk_id`, `nama_produk`, `harga`, `qty`, `subtotal`) VALUES
(6, 5, 1, 'Virtus', 242500.00, 1, 242500.00),
(7, 6, 13, 'SMG', 42000.00, 1, 42000.00),
(8, 7, 23, 'Cocaine Bag', 800.00, 5, 4000.00),
(9, 7, 28, 'Vest Biru', 4000.00, 5, 20000.00),
(10, 7, 22, 'Weed Bag', 500.00, 5, 2500.00),
(11, 7, 25, 'Opium Bag', 600.00, 5, 3000.00),
(12, 8, 22, 'Weed Bag', 500.00, 20, 10000.00),
(13, 9, 17, 'Box Ammo .45 ACP', 5500.00, 10, 55000.00),
(14, 10, 1, 'Virtus', 240000.00, 1, 240000.00),
(15, 10, 28, 'Vest Biru', 4000.00, 10, 40000.00),
(16, 10, 19, 'Box Ammo Rifle 762', 7000.00, 10, 70000.00),
(17, 10, 18, 'Box Ammo 9MM', 4000.00, 10, 40000.00),
(18, 11, 1, 'Virtus', 240000.00, 2, 480000.00),
(19, 11, 20, 'Box Ammo Rifle 556', 7000.00, 10, 70000.00),
(20, 12, 20, 'Box Ammo Rifle 556', 7000.00, 10, 70000.00),
(21, 13, 1, 'Virtus', 240000.00, 1, 240000.00),
(22, 14, 19, 'Box Ammo Rifle 762', 7000.00, 5, 35000.00),
(23, 14, 1, 'Virtus', 240000.00, 1, 240000.00),
(24, 15, 19, 'Box Ammo Rifle 762', 7000.00, 30, 210000.00),
(25, 16, 22, 'Weed Bag', 500.00, 50, 25000.00),
(26, 16, 23, 'Cocaine Bag', 800.00, 10, 8000.00),
(27, 16, 24, 'Meth Bag', 600.00, 15, 9000.00),
(28, 16, 25, 'Opium Bag', 600.00, 10, 6000.00),
(29, 16, 28, 'Vest Biru', 4000.00, 10, 40000.00),
(30, 16, 18, 'Box Ammo 9MM', 4000.00, 11, 44000.00),
(31, 17, 23, 'Cocaine Bag', 800.00, 5, 4000.00),
(32, 18, 28, 'Vest Biru', 4000.00, 10, 40000.00),
(33, 18, 23, 'Cocaine Bag', 800.00, 5, 4000.00),
(34, 18, 24, 'Meth Bag', 600.00, 10, 6000.00),
(35, 18, 25, 'Opium Bag', 600.00, 5, 3000.00),
(36, 18, 22, 'Weed Bag', 500.00, 40, 20000.00),
(37, 18, 18, 'Box Ammo 9MM', 4000.00, 15, 60000.00),
(38, 19, 18, 'Box Ammo 9MM', 4000.00, 20, 80000.00),
(39, 19, 19, 'Box Ammo Rifle 762', 7000.00, 10, 70000.00),
(40, 20, 28, 'Vest Biru', 4000.00, 10, 40000.00),
(41, 21, 28, 'Vest Biru', 4000.00, 10, 40000.00),
(42, 22, 28, 'Vest Biru', 4000.00, 10, 40000.00),
(43, 22, 19, 'Box Ammo Rifle 762', 7000.00, 15, 105000.00),
(44, 23, 9, 'Mini SMG', 32900.00, 1, 32900.00),
(45, 23, 18, 'Box Ammo 9MM', 4000.00, 20, 80000.00),
(46, 23, 28, 'Vest Biru', 4000.00, 10, 40000.00),
(47, 23, 22, 'Weed Bag', 500.00, 10, 5000.00),
(48, 23, 23, 'Cocaine Bag', 800.00, 5, 4000.00),
(49, 23, 24, 'Meth Bag', 600.00, 10, 6000.00),
(50, 23, 25, 'Opium Bag', 600.00, 10, 6000.00),
(51, 24, 19, 'Box Ammo Rifle 762', 7000.00, 10, 70000.00);

-- --------------------------------------------------------

--
-- Table structure for table `produk`
--

CREATE TABLE `produk` (
  `id` int(11) NOT NULL,
  `nama_produk` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kategori` enum('Senjata','Ammo','Attachment','Narko','Lainnya','Spesial') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Lainnya',
  `foto` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stok` int(11) NOT NULL DEFAULT '0',
  `harga_beli` decimal(14,2) NOT NULL DEFAULT '0.00',
  `harga_jual` decimal(14,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `produk`
--

INSERT INTO `produk` (`id`, `nama_produk`, `kategori`, `foto`, `stok`, `harga_beli`, `harga_jual`, `created_at`) VALUES
(1, 'Virtus', 'Senjata', 'produk_1790275932_5619.png', 8, 227500.00, 240000.00, '2026-09-24 12:32:17'),
(2, 'Assault Rifle', 'Senjata', 'produk_1790256086_7073.png', 10, 195000.00, 210000.00, '2026-09-24 13:05:40'),
(3, 'Black Revolver', 'Senjata', 'produk_1790275923_5544.png', 10, 91000.00, 93000.00, '2026-09-24 13:05:53'),
(4, 'Carbine Rifle', 'Senjata', 'produk_1790256043_4979.png', 0, 260000.00, 999999999.00, '2026-09-24 13:06:06'),
(5, 'Ceramic Pistol', 'Senjata', 'produk_1790256038_7922.png', 10, 26000.00, 28000.00, '2026-09-24 13:06:15'),
(6, 'KVR', 'Senjata', 'produk_1790275916_4582.png', 10, 78000.00, 81000.00, '2026-09-24 13:06:29'),
(7, 'Machine pistol', 'Senjata', 'produk_1790256031_3831.png', 10, 26000.00, 30000.00, '2026-09-24 13:06:35'),
(8, 'Micro SMG', 'Senjata', 'produk_1790255983_7408.png', 10, 29900.00, 32900.00, '2026-09-24 13:06:42'),
(9, 'Mini SMG', 'Senjata', 'produk_1790255977_9277.png', 9, 29900.00, 32900.00, '2026-09-24 13:06:49'),
(10, 'Navy revolver', 'Senjata', 'produk_1790255971_1089.png', 10, 71500.00, 73500.00, '2026-09-24 13:06:56'),
(11, 'Pistol.50', 'Senjata', 'produk_1790255965_8876.png', 10, 9100.00, 12000.00, '2026-09-24 13:07:05'),
(12, 'Pump Shotgun', 'Senjata', 'produk_1790255959_6045.png', 10, 65000.00, 68000.00, '2026-09-24 13:07:15'),
(13, 'SMG', 'Senjata', 'produk_1790255954_6173.png', 9, 39000.00, 42000.00, '2026-09-24 13:07:23'),
(14, 'X17 Modular', 'Senjata', 'produk_1790275883_6697.png', 10, 32500.00, 34500.00, '2026-09-24 13:07:34'),
(15, 'Box Ammo .50', 'Ammo', 'produk_1790255947_4719.png', 500, 1300.00, 1500.00, '2026-09-24 13:10:22'),
(16, 'Box Ammo .44 Magnum', 'Ammo', 'produk_1790255940_8756.png', 300, 5200.00, 5500.00, '2026-09-24 13:10:46'),
(17, 'Box Ammo .45 ACP', 'Ammo', 'produk_1790255934_5353.png', 290, 5200.00, 5500.00, '2026-09-24 13:11:07'),
(18, 'Box Ammo 9MM', 'Ammo', 'produk_1790255928_9689.png', 435, 3900.00, 4000.00, '2026-09-24 13:11:15'),
(19, 'Box Ammo Rifle 762', 'Ammo', 'produk_1790255922_8138.png', 420, 6500.00, 7000.00, '2026-09-24 13:11:36'),
(20, 'Box Ammo Rifle 556', 'Ammo', 'produk_1790255916_2556.png', 500, 6500.00, 7000.00, '2026-09-24 13:12:37'),
(21, 'Box Ammo Shotgun', 'Ammo', 'produk_1790255912_4649.png', 170, 6500.00, 7000.00, '2026-09-24 13:16:38'),
(22, 'Weed Bag', 'Narko', 'produk_1790256144_8656.png', 6925, 0.00, 500.00, '2026-09-24 13:22:24'),
(23, 'Cocaine Bag', 'Narko', 'produk_1790256153_9975.png', 130, 700.00, 800.00, '2026-09-24 13:22:33'),
(24, 'Meth Bag', 'Narko', 'produk_1790256161_1695.png', 5380, 0.00, 600.00, '2026-09-24 13:22:41'),
(25, 'Opium Bag', 'Narko', 'produk_1790256247_2072.png', 5980, 0.00, 600.00, '2026-09-24 13:24:07'),
(26, 'Lockpick', 'Lainnya', 'produk_1790256273_3470.png', 100, 3000.00, 4000.00, '2026-09-24 13:24:33'),
(27, 'Vest Merah', 'Lainnya', 'produk_1790258478_7138.png', 75, 1300.00, 1800.00, '2026-09-24 14:01:18'),
(28, 'Vest Biru', 'Lainnya', 'produk_1790258489_7182.png', 435, 2600.00, 4000.00, '2026-09-24 14:01:29'),
(29, 'Baggy', 'Spesial', 'produk_1790420553_1031.png', 7000, 0.00, 0.00, '2026-09-25 19:05:20'),
(30, 'Meth Cooking Table', 'Spesial', 'produk_1790420548_8666.png', 10, 0.00, 0.00, '2026-09-25 19:10:06'),
(31, 'Meth Oven', 'Spesial', 'produk_1790420539_8275.png', 10, 0.00, 0.00, '2026-09-25 19:11:44'),
(32, 'Bagging Table', 'Spesial', 'produk_1790420534_2156.png', 9, 0.00, 0.00, '2026-09-25 19:12:22'),
(33, 'Weed Seed', 'Spesial', 'produk_1790420530_1593.png', 900, 0.00, 0.00, '2026-09-25 19:13:34'),
(34, 'Meth Pax', 'Spesial', 'produk_1790420524_4509.png', 0, 0.00, 0.00, '2026-09-25 19:15:03'),
(35, 'Weed Daun', 'Spesial', 'produk_1790420519_6136.png', 2000, 0.00, 0.00, '2026-09-25 19:22:41'),
(36, 'Metal Scrap', 'Spesial', 'produk_1790420656_3518.png', 100, 220.00, 0.00, '2026-09-26 11:04:16'),
(37, 'Spesial Metal', 'Spesial', 'produk_1790505643_9022.png', 0, 15000.00, 0.00, '2026-09-26 11:04:59'),
(38, 'Uang Merah', 'Spesial', 'produk_1790848531_7513.png', 56277364, 0.00, 0.00, '2026-10-01 09:28:09'),
(39, 'Uang Putih', 'Spesial', 'produk_1790848525_2013.png', 999999999, 0.00, 0.00, '2026-10-01 09:28:53');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `full_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discord_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` enum('admin','staff','homies') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'homies',
  `status` enum('aktif','nonaktif') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'aktif',
  `must_change_password` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `phone`, `discord_id`, `role`, `status`, `must_change_password`, `created_at`) VALUES
(5, 'ilham', '$2y$10$r1kACAgaEPukEptlfehW3evcmC09n4Azq161DB.KCW5ZErScPtg/6', 'ilham', '', '539845830568443904', 'homies', 'aktif', 0, '2026-09-24 13:35:26'),
(6, 'baba', '$2y$10$nNCmTvHKDzbLdFPZ/3EZnezgmfLZByyxvlyZmYkDbAL0UUtMNAiHK', 'Baba C', NULL, NULL, 'admin', 'aktif', 0, '2026-09-25 11:11:20'),
(7, 'dupan', '$2y$10$ckvEgcORMG.WDDwrPK.u2.0qs6nb746MeMJDzRGBYbpqPHk2IoZem', 'Dupan T', NULL, NULL, 'admin', 'aktif', 0, '2026-09-25 11:11:29'),
(10, 'igor', '$2y$10$z7RnOKL2/tZwfXZVaTQONe4wElJUEsqcZ04oZUwhN.rh4sQJlODJe', 'Brookz Butcher Carwyn', '89136338', '397827492960010244', 'homies', 'aktif', 0, '2026-09-26 07:28:09'),
(11, 'jacky', '$2y$10$QwMcv.iuFM9SQR5sRRxjre2LdmKXt3yHoKnJp5AGbSTJlj.RCcjXa', 'Koo Wong', '88163647', '940445026805252096', 'homies', 'aktif', 0, '2026-09-26 07:30:37'),
(12, 'alan', '$2y$10$d112OmeWS5LtLjb/zMoVAOIl1KoGbpRp4nE848w5V8/Uygev/0V1S', 'Alan Addison', '11423940', '727453186213806140', 'homies', 'aktif', 0, '2026-09-26 07:32:54'),
(13, 'bob', '$2y$10$YFkTAGoTVEUqBtKaJmxfveI9Qs7eDDRVI/lU7GqIxPQFzx.PStay.', 'BoB Finn', '77362128', '1426641038402916412', 'homies', 'aktif', 0, '2026-09-26 07:34:01'),
(14, 'celz', '$2y$10$9VD2dpzXKDz/Wzuv7NwBN.5Paqn7wYFQgISeZKw.jEM/E9bKcb8Z.', 'Celz Phil', '44998268', '559650720463454236', 'homies', 'aktif', 0, '2026-09-26 07:35:18'),
(15, 'sella', '$2y$10$n20MR589bqgb9FH2Vkefku7KZKyWf/eW1sK9F9xshKwq79L6/KbXK', 'Sella Quinn', '21008215', '1218586984537260113', 'homies', 'aktif', 0, '2026-09-26 07:36:18'),
(16, 'abi', '$2y$10$AvpHQhEuxnMjYoXaXNFAb.hpqS8wC1iSB0Uog.1Xj66bsaxfGM.ae', 'Abigail Florencia Carwyn', '33862212', '1060919733832134687', 'homies', 'aktif', 0, '2026-09-26 07:37:19'),
(17, 'toprak', '$2y$10$pnoiTv79JymtCOrqFxc5FOYSQdd7uW2VsLVqPbqwH3.0Vp/5..pQ.', 'Glow Parto', '33470848', '1441964225298825262', 'homies', 'aktif', 0, '2026-09-26 07:38:42'),
(18, 'julian', '$2y$10$CVGsV0G//MGb3TpjM3Q5cOK7wXFQgD18xhgsa3trwu/oDaZeoPTsG', 'Enzo Julian', '57446886', '998206413211963512', 'homies', 'aktif', 0, '2026-09-26 07:40:41'),
(19, 'asep', '$2y$10$4LGbKUcS6YRC7IQ751vgteZzkVBLXUC9NrGAHsCMVo.kGQ/J.87k6', 'asep air', '88869729', '1452969435911557263', 'homies', 'aktif', 0, '2026-09-26 07:41:47'),
(20, 'fery', '$2y$10$yJVSWk5bmlRrGroTJjS8x.7811oldmLKTayQHPQgkpskLwO2Lf4pC', 'FERYFADLI FADLI', '88182841', '1491711027782357052', 'homies', 'aktif', 0, '2026-09-27 02:09:49'),
(21, 'ahsan', '$2y$10$qyVZnUFI8twlsuGLXewR6.msrZux8qkAlJZCRICAwLZrXFE.051tu', 'Jayvon', '', '1409769623167172722', 'homies', 'aktif', 0, '2026-09-27 09:06:03'),
(22, 'kenz', '$2y$10$Qy.oWWqnPax2xI.tas2NgOT/xgogBDXBTS4WtEn7H6YVoCPNmKjdm', 'Kenz', NULL, NULL, 'admin', 'aktif', 0, '2026-09-27 10:44:43'),
(23, 'vannxi', '$2y$10$hzPmGvC/ZoPZb6F0DW3mxeWmtf3VrlEeXLF3kAvvmtEdjxp/UybsW', 'Vannxi Ashford', '21580344', '1505896471235526757', 'homies', 'aktif', 0, '2026-09-27 10:50:20'),
(24, 'vituy', '$2y$10$Hd0YCcPmG7Gd4yZMix.u2.Ute25LuwDwLBNHfmEWCZh3DQqHU4zjy', 'Brian Miles', '88587385', '438617227525095424', 'homies', 'aktif', 0, '2026-09-27 10:52:01'),
(25, 'david', '$2y$10$JtfQ7KPUulRhygCMxKyG6.97tPfkE0cXQlC3jLRPNewRCVsYpYy2u', 'DAVID D', '99275104', '808891861745401878', 'homies', 'aktif', 0, '2026-09-27 15:33:16'),
(26, 'fiks', '$2y$10$icm62oFDl3dVE4n/YPoL5OS9cRNdaUaLngy4K.KddZaWkt2tiTIcG', 'FIKS RIVAI', '44669101', '1125453379800412200', 'homies', 'aktif', 0, '2026-09-27 16:19:52'),
(27, 'nik', '$2y$10$RYM5yM1Ef6G9j32FOTepGOh4VCerO5.guzjFoK3QsQy5Mdamolr2S', 'Niklaus Vyacheslav', '57366566', '996355531868483594', 'homies', 'aktif', 0, '2026-09-28 16:54:35'),
(28, 'maul', '$2y$10$YTdJ9xkUhFnrCgr/OfCjpOoMSRvJa42oO4XDz.PO7arTikn36KNfC', 'Maul RYOHEI', '66625487', '340112196597972992', 'homies', 'aktif', 0, '2026-09-28 16:55:35'),
(29, 'hen', '$2y$10$gy0n3eqFUBwxKKJXc8rcfu.BS05x3cLYgIZ5ul4SraYJ/fjuQwd2O', 'Hen Lee Huang', '88856770', '705803592816263278', 'homies', 'aktif', 0, '2026-09-28 17:08:33'),
(30, 'centa', '$2y$10$iip2VTZj7GjEN3huH4OSdOB4o.fyH9XGb3L.RK8u2WkMML30BSYYe', 'CENTA MORGAN', '55988195', '1049317479945613312', 'homies', 'aktif', 0, '2026-09-29 21:21:47'),
(31, 'bim', '$2y$10$Sel2uyibNfTBKC4l9M1VP.QImS5EjGXTaJtR5Flm9LJoZHi2R2PPq', 'Bimbim Crowley', '22665361', '1448196162057277531', 'homies', 'aktif', 0, '2026-10-01 08:07:11');

-- --------------------------------------------------------

--
-- Table structure for table `work_claim_result_items`
--

CREATE TABLE `work_claim_result_items` (
  `id` int(11) NOT NULL,
  `claim_id` int(11) NOT NULL,
  `produk_id` int(11) DEFAULT NULL,
  `nama_produk` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `qty` int(11) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `work_claim_result_items`
--

INSERT INTO `work_claim_result_items` (`id`, `claim_id`, `produk_id`, `nama_produk`, `qty`, `created_at`) VALUES
(1, 1, 35, 'Weed Daun', 1000, '2026-10-01 14:23:43');

-- --------------------------------------------------------

--
-- Table structure for table `work_claim_stock_log`
--

CREATE TABLE `work_claim_stock_log` (
  `id` int(11) NOT NULL,
  `claim_id` int(11) NOT NULL,
  `produk_id` int(11) NOT NULL,
  `qty` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `work_claim_stock_log`
--

INSERT INTO `work_claim_stock_log` (`id`, `claim_id`, `produk_id`, `qty`, `created_at`) VALUES
(1, 1, 32, 1, '2026-10-01 14:22:59'),
(2, 1, 33, 100, '2026-10-01 14:22:59');

-- --------------------------------------------------------

--
-- Table structure for table `work_tasks`
--

CREATE TABLE `work_tasks` (
  `id` int(11) NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('penjualan','pemrosesan') COLLATE utf8mb4_unicode_ci NOT NULL,
  `quota_total` int(11) NOT NULL,
  `quota_remaining` int(11) NOT NULL,
  `min_qty` int(11) NOT NULL DEFAULT '1',
  `deadline` datetime NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `status` enum('aktif','selesai','dibatalkan') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'aktif',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `work_tasks`
--

INSERT INTO `work_tasks` (`id`, `title`, `type`, `quota_total`, `quota_remaining`, `min_qty`, `deadline`, `created_by`, `status`, `created_at`, `updated_at`) VALUES
(1, 'NANEM WEED SEED', 'pemrosesan', 1000, 900, 100, '2026-10-05 23:59:59', 6, 'dibatalkan', '2026-10-01 14:22:41', '2026-10-01 14:24:55'),
(2, 'NANEM 100 WEED SEED', 'pemrosesan', 900, 900, 100, '2026-10-05 23:59:59', 6, 'aktif', '2026-10-01 14:25:21', '2026-10-01 14:26:11');

-- --------------------------------------------------------

--
-- Table structure for table `work_task_claims`
--

CREATE TABLE `work_task_claims` (
  `id` int(11) NOT NULL,
  `task_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `qty` int(11) NOT NULL,
  `status` enum('menunggu','diacc','dilaporkan','selesai','ditolak','dibatalkan') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'menunggu',
  `acc_by` int(11) DEFAULT NULL,
  `acc_at` timestamp NULL DEFAULT NULL,
  `hasil_uang` decimal(14,2) DEFAULT NULL,
  `bukti_link` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lapor_at` timestamp NULL DEFAULT NULL,
  `selesai_by` int(11) DEFAULT NULL,
  `selesai_at` timestamp NULL DEFAULT NULL,
  `catatan` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `work_task_claims`
--

INSERT INTO `work_task_claims` (`id`, `task_id`, `user_id`, `qty`, `status`, `acc_by`, `acc_at`, `hasil_uang`, `bukti_link`, `lapor_at`, `selesai_by`, `selesai_at`, `catatan`, `created_at`, `updated_at`) VALUES
(1, 1, 6, 100, 'selesai', 6, '2026-10-01 14:23:04', NULL, 'https://avatars.githubusercontent.com/u/194490253?v=4&size=64', '2026-10-01 14:23:43', 6, '2026-10-01 14:23:55', NULL, '2026-10-01 14:22:59', '2026-10-01 14:23:55'),
(3, 2, 6, 100, 'ditolak', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-10-01 14:25:54', '2026-10-01 14:26:01'),
(4, 2, 6, 100, 'dibatalkan', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-10-01 14:26:08', '2026-10-01 14:26:11');

-- --------------------------------------------------------

--
-- Table structure for table `work_task_requirements`
--

CREATE TABLE `work_task_requirements` (
  `id` int(11) NOT NULL,
  `task_id` int(11) NOT NULL,
  `section` enum('penjualan','tools','bahan_baku') COLLATE utf8mb4_unicode_ci NOT NULL,
  `produk_id` int(11) DEFAULT NULL,
  `nama_produk` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `qty` int(11) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `work_task_requirements`
--

INSERT INTO `work_task_requirements` (`id`, `task_id`, `section`, `produk_id`, `nama_produk`, `qty`, `created_at`) VALUES
(1, 1, 'tools', 32, 'Bagging Table', 1, '2026-10-01 14:22:41'),
(2, 1, 'bahan_baku', 33, 'Weed Seed', 1, '2026-10-01 14:22:41'),
(3, 2, 'tools', 39, 'Uang Putih', 1, '2026-10-01 14:25:21'),
(4, 2, 'bahan_baku', 33, 'Weed Seed', 1, '2026-10-01 14:25:21');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `jual_barang`
--
ALTER TABLE `jual_barang`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_jb_user` (`user_id`),
  ADD KEY `idx_jb_status` (`status`),
  ADD KEY `fk_jb_acc` (`acc_by`);

--
-- Indexes for table `jual_barang_item`
--
ALTER TABLE `jual_barang_item`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_jbi` (`jual_barang_id`),
  ADD KEY `fk_jbi_produk` (`produk_id`);

--
-- Indexes for table `jual_barang_whitelist`
--
ALTER TABLE `jual_barang_whitelist`
  ADD PRIMARY KEY (`produk_id`),
  ADD KEY `fk_jbw_added_by` (`added_by`);

--
-- Indexes for table `password_reset_request`
--
ALTER TABLE `password_reset_request`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `fk_reset_admin` (`processed_by`);

--
-- Indexes for table `pemrosesan_whitelist`
--
ALTER TABLE `pemrosesan_whitelist`
  ADD PRIMARY KEY (`produk_id`);

--
-- Indexes for table `pesanan`
--
ALTER TABLE `pesanan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `pesanan_item`
--
ALTER TABLE `pesanan_item`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pesanan` (`pesanan_id`),
  ADD KEY `fk_item_produk` (`produk_id`);

--
-- Indexes for table `produk`
--
ALTER TABLE `produk`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `work_claim_result_items`
--
ALTER TABLE `work_claim_result_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_work_result_claim` (`claim_id`),
  ADD KEY `idx_work_result_product` (`produk_id`);

--
-- Indexes for table `work_claim_stock_log`
--
ALTER TABLE `work_claim_stock_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_work_stocklog_claim` (`claim_id`),
  ADD KEY `fk_work_stocklog_product` (`produk_id`);

--
-- Indexes for table `work_tasks`
--
ALTER TABLE `work_tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_work_tasks_status_deadline` (`status`,`deadline`),
  ADD KEY `idx_work_tasks_creator` (`created_by`);

--
-- Indexes for table `work_task_claims`
--
ALTER TABLE `work_task_claims`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_work_claim_task` (`task_id`),
  ADD KEY `idx_work_claim_user` (`user_id`),
  ADD KEY `idx_work_claim_status` (`status`),
  ADD KEY `fk_work_claim_acc` (`acc_by`),
  ADD KEY `fk_work_claim_done` (`selesai_by`);

--
-- Indexes for table `work_task_requirements`
--
ALTER TABLE `work_task_requirements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_work_req_task` (`task_id`),
  ADD KEY `idx_work_req_product` (`produk_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `jual_barang`
--
ALTER TABLE `jual_barang`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `jual_barang_item`
--
ALTER TABLE `jual_barang_item`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `password_reset_request`
--
ALTER TABLE `password_reset_request`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pesanan`
--
ALTER TABLE `pesanan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `pesanan_item`
--
ALTER TABLE `pesanan_item`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT for table `produk`
--
ALTER TABLE `produk`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `work_claim_result_items`
--
ALTER TABLE `work_claim_result_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `work_claim_stock_log`
--
ALTER TABLE `work_claim_stock_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `work_tasks`
--
ALTER TABLE `work_tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `work_task_claims`
--
ALTER TABLE `work_task_claims`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `work_task_requirements`
--
ALTER TABLE `work_task_requirements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `jual_barang`
--
ALTER TABLE `jual_barang`
  ADD CONSTRAINT `fk_jb_acc` FOREIGN KEY (`acc_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_jb_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `jual_barang_item`
--
ALTER TABLE `jual_barang_item`
  ADD CONSTRAINT `fk_jbi_jual_barang` FOREIGN KEY (`jual_barang_id`) REFERENCES `jual_barang` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_jbi_produk` FOREIGN KEY (`produk_id`) REFERENCES `produk` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `jual_barang_whitelist`
--
ALTER TABLE `jual_barang_whitelist`
  ADD CONSTRAINT `fk_jbw_added_by` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_jbw_produk` FOREIGN KEY (`produk_id`) REFERENCES `produk` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `password_reset_request`
--
ALTER TABLE `password_reset_request`
  ADD CONSTRAINT `fk_reset_admin` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_reset_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pesanan`
--
ALTER TABLE `pesanan`
  ADD CONSTRAINT `fk_pesanan_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pesanan_item`
--
ALTER TABLE `pesanan_item`
  ADD CONSTRAINT `fk_item_pesanan` FOREIGN KEY (`pesanan_id`) REFERENCES `pesanan` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_item_produk` FOREIGN KEY (`produk_id`) REFERENCES `produk` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `work_claim_result_items`
--
ALTER TABLE `work_claim_result_items`
  ADD CONSTRAINT `fk_work_result_claim` FOREIGN KEY (`claim_id`) REFERENCES `work_task_claims` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_work_result_product` FOREIGN KEY (`produk_id`) REFERENCES `produk` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `work_claim_stock_log`
--
ALTER TABLE `work_claim_stock_log`
  ADD CONSTRAINT `fk_work_stocklog_claim` FOREIGN KEY (`claim_id`) REFERENCES `work_task_claims` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_work_stocklog_product` FOREIGN KEY (`produk_id`) REFERENCES `produk` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `work_tasks`
--
ALTER TABLE `work_tasks`
  ADD CONSTRAINT `fk_work_tasks_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `work_task_claims`
--
ALTER TABLE `work_task_claims`
  ADD CONSTRAINT `fk_work_claim_acc` FOREIGN KEY (`acc_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_work_claim_done` FOREIGN KEY (`selesai_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_work_claim_task` FOREIGN KEY (`task_id`) REFERENCES `work_tasks` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_work_claim_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `work_task_requirements`
--
ALTER TABLE `work_task_requirements`
  ADD CONSTRAINT `fk_work_req_product` FOREIGN KEY (`produk_id`) REFERENCES `produk` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_work_req_task` FOREIGN KEY (`task_id`) REFERENCES `work_tasks` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
