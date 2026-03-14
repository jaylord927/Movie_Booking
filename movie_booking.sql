-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 14, 2026 at 02:33 PM
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
-- Database: `movie_booking`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_activity_log`
--

CREATE TABLE `admin_activity_log` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `details` text DEFAULT NULL,
  `full_details` text DEFAULT NULL,
  `target_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `booked_seats`
--

CREATE TABLE `booked_seats` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `seat_number` varchar(10) NOT NULL,
  `seat_type` varchar(20) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `booked_seats`
--

INSERT INTO `booked_seats` (`id`, `booking_id`, `seat_number`, `seat_type`, `price`, `created_at`) VALUES
(1, 18, 'C05', 'Standard', 350.00, '2026-03-13 14:05:12'),
(2, 18, 'C06', 'Standard', 350.00, '2026-03-13 14:05:12'),
(3, 18, 'C07', 'Standard', 350.00, '2026-03-13 14:05:12'),
(4, 18, 'C08', 'Standard', 350.00, '2026-03-13 14:05:12'),
(5, 19, 'E09', 'Standard', 350.00, '2026-03-13 14:23:15'),
(6, 19, 'E10', 'Standard', 350.00, '2026-03-13 14:23:15'),
(7, 20, 'E06', 'Standard', 350.00, '2026-03-13 14:38:54'),
(8, 20, 'E07', 'Standard', 350.00, '2026-03-13 14:38:54'),
(9, 20, 'E08', 'Standard', 350.00, '2026-03-13 14:38:54'),
(10, 20, 'E09', 'Standard', 350.00, '2026-03-13 14:38:54'),
(11, 20, 'E10', 'Standard', 350.00, '2026-03-13 14:38:54'),
(12, 21, 'A01', 'Premium', 450.00, '2026-03-13 15:08:01'),
(13, 21, 'A02', 'Premium', 450.00, '2026-03-13 15:08:01'),
(14, 22, 'D08', 'Sweet Spot', 550.00, '2026-03-14 13:12:51'),
(15, 22, 'D09', 'Sweet Spot', 550.00, '2026-03-14 13:12:51'),
(16, 22, 'D10', 'Sweet Spot', 550.00, '2026-03-14 13:12:51'),
(17, 23, 'B05', 'Standard', 350.00, '2026-03-14 13:14:11'),
(18, 23, 'B06', 'Standard', 350.00, '2026-03-14 13:14:11'),
(19, 23, 'B07', 'Standard', 350.00, '2026-03-14 13:14:11'),
(20, 23, 'B08', 'Standard', 350.00, '2026-03-14 13:14:11'),
(21, 23, 'B09', 'Standard', 350.00, '2026-03-14 13:14:11'),
(22, 23, 'B10', 'Standard', 350.00, '2026-03-14 13:14:11'),
(23, 24, 'B01', 'Standard', 350.00, '2026-03-14 13:35:55'),
(24, 24, 'B02', 'Standard', 350.00, '2026-03-14 13:35:55'),
(25, 24, 'B03', 'Standard', 350.00, '2026-03-14 13:35:55'),
(26, 24, 'B04', 'Standard', 350.00, '2026-03-14 13:35:55'),
(27, 25, 'A06', 'Premium', 450.00, '2026-03-14 14:02:39'),
(28, 25, 'A08', 'Premium', 450.00, '2026-03-14 14:02:39'),
(29, 26, 'A06', 'Premium', 450.00, '2026-03-14 14:05:02'),
(30, 26, 'A08', 'Premium', 450.00, '2026-03-14 14:05:02');

-- --------------------------------------------------------

--
-- Table structure for table `customer_activity_log`
--

CREATE TABLE `customer_activity_log` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `action_type` varchar(50) NOT NULL,
  `details` text DEFAULT NULL,
  `movie_id` int(11) DEFAULT NULL,
  `schedule_id` int(11) DEFAULT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `manual_payments`
--

CREATE TABLE `manual_payments` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `payment_method_id` int(11) NOT NULL,
  `reference_number` varchar(100) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `screenshot_path` varchar(255) NOT NULL,
  `status` enum('Pending','Verified','Rejected') DEFAULT 'Pending',
  `admin_notes` text DEFAULT NULL,
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `manual_payments`
--

INSERT INTO `manual_payments` (`id`, `booking_id`, `user_id`, `payment_method_id`, `reference_number`, `amount`, `screenshot_path`, `status`, `admin_notes`, `verified_by`, `verified_at`, `created_at`) VALUES
(1, 12, 1, 1, '0912112344', 1350.00, 'uploads/payments/payment_BK2026030812241074_1772972725.png', 'Verified', '', 2, '2026-03-08 12:26:27', '2026-03-08 12:25:25'),
(2, 20, 1, 1, '0912112344', 1750.00, 'uploads/payments/payment_BK2026031314385476_1773413023.jpg', 'Verified', 'Okay thank you sir', 2, '2026-03-13 15:12:49', '2026-03-13 14:43:43');

-- --------------------------------------------------------

--
-- Table structure for table `movies`
--

CREATE TABLE `movies` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `director` varchar(255) DEFAULT NULL,
  `genre` varchar(100) DEFAULT NULL,
  `duration` varchar(20) DEFAULT NULL,
  `rating` varchar(10) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `poster_url` varchar(500) DEFAULT NULL,
  `trailer_url` varchar(500) DEFAULT NULL,
  `venue_name` varchar(255) DEFAULT NULL,
  `venue_location` varchar(500) DEFAULT NULL,
  `google_maps_link` varchar(500) DEFAULT NULL,
  `standard_price` decimal(10,2) DEFAULT 350.00,
  `premium_price` decimal(10,2) DEFAULT 450.00,
  `sweet_spot_price` decimal(10,2) DEFAULT 550.00,
  `is_active` tinyint(1) DEFAULT 1,
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_updated` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `movies`
--

INSERT INTO `movies` (`id`, `title`, `director`, `genre`, `duration`, `rating`, `description`, `poster_url`, `trailer_url`, `venue_name`, `venue_location`, `google_maps_link`, `standard_price`, `premium_price`, `sweet_spot_price`, `is_active`, `added_by`, `updated_by`, `created_at`, `last_updated`) VALUES
(1, 'Sinners', NULL, 'Supernatural horror', '2hours', 'PG', 'Sinners (2025) is a supernatural horror-period film directed by Ryan Coogler, set in the 1930s Mississippi Delta. The story follows twin brothers, the Smokestack Twins, who return to their hometown seeking a fresh start after working for the Chicago Mafia. They buy a sawmill and a juke joint, but soon face supernatural troubles that challenge their quest for redemption amidst themes of racism and evil.', 'https://i.pinimg.com/originals/93/b2/ec/93b2ec2431ea0ae34a2dbafc81a1d72e.jpg', 'https://www.youtube.com/watch?v=bKGxHflevuk', 'SM Cinema', 'Purok 13 Cadulawan minglanilla Cebu', 'https://www.google.com/maps/place//@10.2699325,123.7746523,20.83z/data=!4m9!1m8!3m7!1s0x33a977e4598c638d:0xd2016057b1f9cd28!2sMinglanilla,+Cebu!3b1!8m2!3d10.2454293!4d123.7959894!16zL20vMDZoNGhs?entry=ttu&amp;g_ep=EgoyMDI2MDIxOC4wIKXMDSoASAFQAw%3D%3D', 350.00, 450.00, 550.00, 1, 2, NULL, '2026-03-06 15:03:50', NULL),
(2, 'The Notebook', 'Denise', 'Drama/Romance', '123 minutes', 'PG', 'The Notebook&amp;amp;quot; is a romantic drama that tells the enduring love story of a young couple from different social backgrounds, recounted decades later by an elderly man from a cherished notebook.', 'https://www.themoviedb.org/t/p/original/wbvboxr6xmdSbMEBKzXVWgAwJ1Q.jpg', 'https://www.youtube.com/watch?v=BjJcYdEOI0k', 'SM Cinema', 'Purok 13 Cadulawan minglanilla Cebu', 'https://www.google.com/maps/place//@10.2699325,123.7746523,20.83z/data=!4m9!1m8!3m7!1s0x33a977e4598c638d:0xd2016057b1f9cd28!2sMinglanilla,+Cebu!3b1!8m2!3d10.2454293!4d123.7959894!16zL20vMDZoNGhs?entry=ttu&amp;amp;g_ep=EgoyMDI2MDIxOC4wIKXMDSoASAFQAw%3D%3D', 350.00, 500.00, 550.00, 1, 2, 2, '2026-03-06 15:14:24', '2026-03-07 05:19:04'),
(3, 'test', NULL, 'test', 'test', 'G', 'test', '', '', 'test', 'test', '', 350.00, 450.00, 550.00, 0, 2, NULL, '2026-03-06 15:48:47', '2026-03-06 15:50:47'),
(4, 'test', NULL, 'test', 'test', 'G', 'test', '', '', '', '', '', 350.00, 450.00, 550.00, 0, 2, NULL, '2026-03-07 03:09:27', '2026-03-07 03:56:05'),
(5, 'testt', NULL, 'testt', 'testt', 'G', 'testt', '', '', '', '', '', 350.00, 450.00, 550.00, 0, 2, NULL, '2026-03-07 03:54:12', '2026-03-07 03:56:01'),
(6, 'testt', NULL, 'testt', 'testt', 'G', 'test', '', '', '', '', '', 350.00, 450.00, 550.00, 1, 2, NULL, '2026-03-07 04:31:42', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `movie_schedules`
--

CREATE TABLE `movie_schedules` (
  `id` int(11) NOT NULL,
  `movie_id` int(11) NOT NULL,
  `movie_title` varchar(255) NOT NULL,
  `show_date` date NOT NULL,
  `showtime` time NOT NULL,
  `total_seats` int(11) DEFAULT 40,
  `available_seats` int(11) DEFAULT 40,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `movie_schedules`
--

INSERT INTO `movie_schedules` (`id`, `movie_id`, `movie_title`, `show_date`, `showtime`, `total_seats`, `available_seats`, `is_active`, `created_at`) VALUES
(1, 1, 'Sinners', '2026-03-31', '13:00:00', 50, 30, 1, '2026-03-06 15:04:42'),
(2, 2, 'The Notebook', '2026-03-12', '17:00:00', 40, 40, 1, '2026-03-06 15:36:32'),
(3, 3, 'test', '2026-03-11', '10:00:00', 40, 40, 0, '2026-03-06 15:49:29'),
(4, 5, 'testt', '2026-03-11', '18:01:00', 40, 40, 1, '2026-03-07 03:09:55'),
(5, 6, 'testt', '2026-03-14', '18:01:00', 40, 31, 1, '2026-03-13 15:07:34');

-- --------------------------------------------------------

--
-- Table structure for table `payment_methods`
--

CREATE TABLE `payment_methods` (
  `id` int(11) NOT NULL,
  `method_name` varchar(50) NOT NULL,
  `account_name` varchar(100) NOT NULL,
  `account_number` varchar(50) NOT NULL,
  `qr_code_path` varchar(255) DEFAULT NULL,
  `instructions` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `display_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payment_methods`
--

INSERT INTO `payment_methods` (`id`, `method_name`, `account_name`, `account_number`, `qr_code_path`, `instructions`, `is_active`, `display_order`, `created_at`, `updated_at`) VALUES
(1, 'Gcash', 'Jaylord B. Laspuna', '09267630945', NULL, 'Please always check', 1, 0, '2026-03-07 16:18:03', NULL),
(2, 'Paymaya', 'Jaylord B. Laspuna', '09267630945', NULL, '', 1, 0, '2026-03-07 16:21:02', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `paymongo_payments`
--

CREATE TABLE `paymongo_payments` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `paymongo_payment_id` varchar(100) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `status` enum('Pending','Paid','Failed') DEFAULT 'Pending',
  `payment_intent_id` varchar(100) DEFAULT NULL,
  `payment_method_id` varchar(100) DEFAULT NULL,
  `client_key` varchar(255) DEFAULT NULL,
  `redirect_url` varchar(255) DEFAULT NULL,
  `webhook_received` tinyint(1) DEFAULT 0,
  `response_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`response_data`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `seat_availability`
--

CREATE TABLE `seat_availability` (
  `id` int(11) NOT NULL,
  `schedule_id` int(11) NOT NULL,
  `movie_title` varchar(255) NOT NULL,
  `show_date` date NOT NULL,
  `showtime` time NOT NULL,
  `seat_number` varchar(10) NOT NULL,
  `seat_type` varchar(20) DEFAULT 'Standard',
  `price` decimal(10,2) DEFAULT 350.00,
  `is_available` tinyint(1) DEFAULT 1,
  `booking_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `seat_availability`
--

INSERT INTO `seat_availability` (`id`, `schedule_id`, `movie_title`, `show_date`, `showtime`, `seat_number`, `seat_type`, `price`, `is_available`, `booking_id`) VALUES
(1, 1, 'Sinners', '2026-03-31', '13:00:00', 'A01', 'Premium', 450.00, 0, 6),
(2, 1, 'Sinners', '2026-03-31', '13:00:00', 'A02', 'Premium', 450.00, 0, 6),
(3, 1, 'Sinners', '2026-03-31', '13:00:00', 'A03', 'Premium', 450.00, 0, 9),
(4, 1, 'Sinners', '2026-03-31', '13:00:00', 'A04', 'Premium', 450.00, 0, 9),
(5, 1, 'Sinners', '2026-03-31', '13:00:00', 'A05', 'Premium', 450.00, 0, 12),
(6, 1, 'Sinners', '2026-03-31', '13:00:00', 'A06', 'Premium', 450.00, 0, 26),
(7, 1, 'Sinners', '2026-03-31', '13:00:00', 'A07', 'Premium', 450.00, 0, 12),
(8, 1, 'Sinners', '2026-03-31', '13:00:00', 'A08', 'Premium', 450.00, 0, 26),
(9, 1, 'Sinners', '2026-03-31', '13:00:00', 'A09', 'Premium', 450.00, 1, NULL),
(10, 1, 'Sinners', '2026-03-31', '13:00:00', 'A10', 'Premium', 450.00, 0, 12),
(11, 1, 'Sinners', '2026-03-31', '13:00:00', 'B01', 'Standard', 350.00, 1, NULL),
(12, 1, 'Sinners', '2026-03-31', '13:00:00', 'B02', 'Standard', 350.00, 1, NULL),
(13, 1, 'Sinners', '2026-03-31', '13:00:00', 'B03', 'Standard', 350.00, 1, NULL),
(14, 1, 'Sinners', '2026-03-31', '13:00:00', 'B04', 'Standard', 350.00, 1, NULL),
(15, 1, 'Sinners', '2026-03-31', '13:00:00', 'B05', 'Standard', 350.00, 1, NULL),
(16, 1, 'Sinners', '2026-03-31', '13:00:00', 'B06', 'Standard', 350.00, 1, NULL),
(17, 1, 'Sinners', '2026-03-31', '13:00:00', 'B07', 'Standard', 350.00, 1, NULL),
(18, 1, 'Sinners', '2026-03-31', '13:00:00', 'B08', 'Standard', 350.00, 1, NULL),
(19, 1, 'Sinners', '2026-03-31', '13:00:00', 'B09', 'Standard', 350.00, 1, NULL),
(20, 1, 'Sinners', '2026-03-31', '13:00:00', 'B10', 'Sweet Spot', 550.00, 1, NULL),
(21, 1, 'Sinners', '2026-03-31', '13:00:00', 'C01', 'Standard', 350.00, 0, 17),
(22, 1, 'Sinners', '2026-03-31', '13:00:00', 'C02', 'Standard', 350.00, 0, 17),
(23, 1, 'Sinners', '2026-03-31', '13:00:00', 'C03', 'Standard', 350.00, 0, 17),
(24, 1, 'Sinners', '2026-03-31', '13:00:00', 'C04', 'Standard', 350.00, 1, NULL),
(25, 1, 'Sinners', '2026-03-31', '13:00:00', 'C05', 'Standard', 350.00, 1, NULL),
(26, 1, 'Sinners', '2026-03-31', '13:00:00', 'C06', 'Standard', 350.00, 1, NULL),
(27, 1, 'Sinners', '2026-03-31', '13:00:00', 'C07', 'Standard', 350.00, 1, NULL),
(28, 1, 'Sinners', '2026-03-31', '13:00:00', 'C08', 'Standard', 350.00, 1, NULL),
(29, 1, 'Sinners', '2026-03-31', '13:00:00', 'C09', 'Standard', 350.00, 1, NULL),
(30, 1, 'Sinners', '2026-03-31', '13:00:00', 'C10', 'Standard', 350.00, 1, NULL),
(31, 1, 'Sinners', '2026-03-31', '13:00:00', 'D01', 'Sweet Spot', 550.00, 1, NULL),
(32, 1, 'Sinners', '2026-03-31', '13:00:00', 'D02', 'Sweet Spot', 550.00, 1, NULL),
(33, 1, 'Sinners', '2026-03-31', '13:00:00', 'D03', 'Sweet Spot', 550.00, 1, NULL),
(34, 1, 'Sinners', '2026-03-31', '13:00:00', 'D04', 'Sweet Spot', 550.00, 1, NULL),
(35, 1, 'Sinners', '2026-03-31', '13:00:00', 'D05', 'Sweet Spot', 550.00, 1, NULL),
(36, 1, 'Sinners', '2026-03-31', '13:00:00', 'D06', 'Sweet Spot', 550.00, 1, NULL),
(37, 1, 'Sinners', '2026-03-31', '13:00:00', 'D07', 'Sweet Spot', 550.00, 1, NULL),
(38, 1, 'Sinners', '2026-03-31', '13:00:00', 'D08', 'Sweet Spot', 550.00, 1, NULL),
(39, 1, 'Sinners', '2026-03-31', '13:00:00', 'D09', 'Sweet Spot', 550.00, 1, NULL),
(40, 1, 'Sinners', '2026-03-31', '13:00:00', 'D10', 'Sweet Spot', 550.00, 1, NULL),
(41, 2, 'The Notebook', '2026-03-12', '17:00:00', 'A01', 'Premium', 450.00, 1, NULL),
(42, 2, 'The Notebook', '2026-03-12', '17:00:00', 'A02', 'Premium', 450.00, 1, NULL),
(43, 2, 'The Notebook', '2026-03-12', '17:00:00', 'A03', 'Premium', 450.00, 1, NULL),
(44, 2, 'The Notebook', '2026-03-12', '17:00:00', 'A04', 'Premium', 450.00, 1, NULL),
(45, 2, 'The Notebook', '2026-03-12', '17:00:00', 'A05', 'Premium', 450.00, 1, NULL),
(46, 2, 'The Notebook', '2026-03-12', '17:00:00', 'A06', 'Premium', 450.00, 1, NULL),
(47, 2, 'The Notebook', '2026-03-12', '17:00:00', 'A07', 'Premium', 450.00, 1, NULL),
(48, 2, 'The Notebook', '2026-03-12', '17:00:00', 'A08', 'Premium', 450.00, 1, NULL),
(49, 2, 'The Notebook', '2026-03-12', '17:00:00', 'A09', 'Premium', 450.00, 1, NULL),
(50, 2, 'The Notebook', '2026-03-12', '17:00:00', 'A10', 'Premium', 450.00, 1, NULL),
(51, 2, 'The Notebook', '2026-03-12', '17:00:00', 'B01', 'Standard', 350.00, 1, NULL),
(52, 2, 'The Notebook', '2026-03-12', '17:00:00', 'B02', 'Standard', 350.00, 1, NULL),
(53, 2, 'The Notebook', '2026-03-12', '17:00:00', 'B03', 'Standard', 350.00, 1, NULL),
(54, 2, 'The Notebook', '2026-03-12', '17:00:00', 'B04', 'Standard', 350.00, 1, NULL),
(55, 2, 'The Notebook', '2026-03-12', '17:00:00', 'B05', 'Standard', 350.00, 1, NULL),
(56, 2, 'The Notebook', '2026-03-12', '17:00:00', 'B06', 'Standard', 350.00, 1, NULL),
(57, 2, 'The Notebook', '2026-03-12', '17:00:00', 'B07', 'Standard', 350.00, 1, NULL),
(58, 2, 'The Notebook', '2026-03-12', '17:00:00', 'B08', 'Standard', 350.00, 1, NULL),
(59, 2, 'The Notebook', '2026-03-12', '17:00:00', 'B09', 'Standard', 350.00, 1, NULL),
(60, 2, 'The Notebook', '2026-03-12', '17:00:00', 'B10', 'Standard', 350.00, 1, NULL),
(61, 2, 'The Notebook', '2026-03-12', '17:00:00', 'C01', 'Standard', 350.00, 1, NULL),
(62, 2, 'The Notebook', '2026-03-12', '17:00:00', 'C02', 'Standard', 350.00, 1, NULL),
(63, 2, 'The Notebook', '2026-03-12', '17:00:00', 'C03', 'Standard', 350.00, 1, NULL),
(64, 2, 'The Notebook', '2026-03-12', '17:00:00', 'C04', 'Standard', 350.00, 1, NULL),
(65, 2, 'The Notebook', '2026-03-12', '17:00:00', 'C05', 'Standard', 350.00, 1, NULL),
(66, 2, 'The Notebook', '2026-03-12', '17:00:00', 'C06', 'Standard', 350.00, 1, NULL),
(67, 2, 'The Notebook', '2026-03-12', '17:00:00', 'C07', 'Standard', 350.00, 1, NULL),
(68, 2, 'The Notebook', '2026-03-12', '17:00:00', 'C08', 'Standard', 350.00, 1, NULL),
(69, 2, 'The Notebook', '2026-03-12', '17:00:00', 'C09', 'Standard', 350.00, 1, NULL),
(70, 2, 'The Notebook', '2026-03-12', '17:00:00', 'C10', 'Standard', 350.00, 1, NULL),
(71, 2, 'The Notebook', '2026-03-12', '17:00:00', 'D01', 'Sweet Spot', 550.00, 1, NULL),
(72, 2, 'The Notebook', '2026-03-12', '17:00:00', 'D02', 'Sweet Spot', 550.00, 1, NULL),
(73, 2, 'The Notebook', '2026-03-12', '17:00:00', 'D03', 'Sweet Spot', 550.00, 1, NULL),
(74, 2, 'The Notebook', '2026-03-12', '17:00:00', 'D04', 'Sweet Spot', 550.00, 1, NULL),
(75, 2, 'The Notebook', '2026-03-12', '17:00:00', 'D05', 'Sweet Spot', 550.00, 1, NULL),
(76, 2, 'The Notebook', '2026-03-12', '17:00:00', 'D06', 'Sweet Spot', 550.00, 1, NULL),
(77, 2, 'The Notebook', '2026-03-12', '17:00:00', 'D07', 'Sweet Spot', 550.00, 1, NULL),
(78, 2, 'The Notebook', '2026-03-12', '17:00:00', 'D08', 'Sweet Spot', 550.00, 1, NULL),
(79, 2, 'The Notebook', '2026-03-12', '17:00:00', 'D09', 'Sweet Spot', 550.00, 1, NULL),
(80, 2, 'The Notebook', '2026-03-12', '17:00:00', 'D10', 'Sweet Spot', 550.00, 1, NULL),
(81, 3, 'test', '2026-03-11', '10:00:00', 'A01', 'Premium', 450.00, 1, NULL),
(82, 3, 'test', '2026-03-11', '10:00:00', 'A02', 'Premium', 450.00, 1, NULL),
(83, 3, 'test', '2026-03-11', '10:00:00', 'A03', 'Premium', 450.00, 1, NULL),
(84, 3, 'test', '2026-03-11', '10:00:00', 'A04', 'Premium', 450.00, 1, NULL),
(85, 3, 'test', '2026-03-11', '10:00:00', 'A05', 'Premium', 450.00, 1, NULL),
(86, 3, 'test', '2026-03-11', '10:00:00', 'A06', 'Premium', 450.00, 1, NULL),
(87, 3, 'test', '2026-03-11', '10:00:00', 'A07', 'Premium', 450.00, 1, NULL),
(88, 3, 'test', '2026-03-11', '10:00:00', 'A08', 'Premium', 450.00, 1, NULL),
(89, 3, 'test', '2026-03-11', '10:00:00', 'A09', 'Premium', 450.00, 1, NULL),
(90, 3, 'test', '2026-03-11', '10:00:00', 'A10', 'Premium', 450.00, 1, NULL),
(91, 3, 'test', '2026-03-11', '10:00:00', 'B01', 'Standard', 350.00, 1, NULL),
(92, 3, 'test', '2026-03-11', '10:00:00', 'B02', 'Standard', 350.00, 1, NULL),
(93, 3, 'test', '2026-03-11', '10:00:00', 'B03', 'Standard', 350.00, 1, NULL),
(94, 3, 'test', '2026-03-11', '10:00:00', 'B04', 'Standard', 350.00, 1, NULL),
(95, 3, 'test', '2026-03-11', '10:00:00', 'B05', 'Standard', 350.00, 1, NULL),
(96, 3, 'test', '2026-03-11', '10:00:00', 'B06', 'Standard', 350.00, 1, NULL),
(97, 3, 'test', '2026-03-11', '10:00:00', 'B07', 'Standard', 350.00, 1, NULL),
(98, 3, 'test', '2026-03-11', '10:00:00', 'B08', 'Standard', 350.00, 1, NULL),
(99, 3, 'test', '2026-03-11', '10:00:00', 'B09', 'Standard', 350.00, 1, NULL),
(100, 3, 'test', '2026-03-11', '10:00:00', 'B10', 'Standard', 350.00, 1, NULL),
(101, 3, 'test', '2026-03-11', '10:00:00', 'C01', 'Standard', 350.00, 1, NULL),
(102, 3, 'test', '2026-03-11', '10:00:00', 'C02', 'Standard', 350.00, 1, NULL),
(103, 3, 'test', '2026-03-11', '10:00:00', 'C03', 'Standard', 350.00, 1, NULL),
(104, 3, 'test', '2026-03-11', '10:00:00', 'C04', 'Standard', 350.00, 1, NULL),
(105, 3, 'test', '2026-03-11', '10:00:00', 'C05', 'Standard', 350.00, 1, NULL),
(106, 3, 'test', '2026-03-11', '10:00:00', 'C06', 'Standard', 350.00, 1, NULL),
(107, 3, 'test', '2026-03-11', '10:00:00', 'C07', 'Standard', 350.00, 1, NULL),
(108, 3, 'test', '2026-03-11', '10:00:00', 'C08', 'Standard', 350.00, 1, NULL),
(109, 3, 'test', '2026-03-11', '10:00:00', 'C09', 'Standard', 350.00, 1, NULL),
(110, 3, 'test', '2026-03-11', '10:00:00', 'C10', 'Standard', 350.00, 1, NULL),
(111, 3, 'test', '2026-03-11', '10:00:00', 'D01', 'Sweet Spot', 550.00, 1, NULL),
(112, 3, 'test', '2026-03-11', '10:00:00', 'D02', 'Sweet Spot', 550.00, 1, NULL),
(113, 3, 'test', '2026-03-11', '10:00:00', 'D03', 'Sweet Spot', 550.00, 1, NULL),
(114, 3, 'test', '2026-03-11', '10:00:00', 'D04', 'Sweet Spot', 550.00, 1, NULL),
(115, 3, 'test', '2026-03-11', '10:00:00', 'D05', 'Sweet Spot', 550.00, 1, NULL),
(116, 3, 'test', '2026-03-11', '10:00:00', 'D06', 'Sweet Spot', 550.00, 1, NULL),
(117, 3, 'test', '2026-03-11', '10:00:00', 'D07', 'Sweet Spot', 550.00, 1, NULL),
(118, 3, 'test', '2026-03-11', '10:00:00', 'D08', 'Sweet Spot', 550.00, 1, NULL),
(119, 3, 'test', '2026-03-11', '10:00:00', 'D09', 'Sweet Spot', 550.00, 1, NULL),
(120, 3, 'test', '2026-03-11', '10:00:00', 'D10', 'Sweet Spot', 550.00, 1, NULL),
(121, 4, 'testt', '2026-03-11', '18:01:00', 'A01', 'Premium', 450.00, 1, NULL),
(122, 4, 'testt', '2026-03-11', '18:01:00', 'A02', 'Premium', 450.00, 1, NULL),
(123, 4, 'testt', '2026-03-11', '18:01:00', 'A03', 'Premium', 450.00, 1, NULL),
(124, 4, 'testt', '2026-03-11', '18:01:00', 'A04', 'Premium', 450.00, 1, NULL),
(125, 4, 'testt', '2026-03-11', '18:01:00', 'A05', 'Premium', 450.00, 1, NULL),
(126, 4, 'testt', '2026-03-11', '18:01:00', 'A06', 'Premium', 450.00, 1, NULL),
(127, 4, 'testt', '2026-03-11', '18:01:00', 'A07', 'Premium', 450.00, 1, NULL),
(128, 4, 'testt', '2026-03-11', '18:01:00', 'A08', 'Premium', 450.00, 1, NULL),
(129, 4, 'testt', '2026-03-11', '18:01:00', 'A09', 'Premium', 450.00, 1, NULL),
(130, 4, 'testt', '2026-03-11', '18:01:00', 'A10', 'Premium', 450.00, 1, NULL),
(131, 4, 'testt', '2026-03-11', '18:01:00', 'B01', 'Standard', 350.00, 1, NULL),
(132, 4, 'testt', '2026-03-11', '18:01:00', 'B02', 'Standard', 350.00, 1, NULL),
(133, 4, 'testt', '2026-03-11', '18:01:00', 'B03', 'Standard', 350.00, 1, NULL),
(134, 4, 'testt', '2026-03-11', '18:01:00', 'B04', 'Standard', 350.00, 1, NULL),
(135, 4, 'testt', '2026-03-11', '18:01:00', 'B05', 'Standard', 350.00, 1, NULL),
(136, 4, 'testt', '2026-03-11', '18:01:00', 'B06', 'Standard', 350.00, 1, NULL),
(137, 4, 'testt', '2026-03-11', '18:01:00', 'B07', 'Standard', 350.00, 1, NULL),
(138, 4, 'testt', '2026-03-11', '18:01:00', 'B08', 'Standard', 350.00, 1, NULL),
(139, 4, 'testt', '2026-03-11', '18:01:00', 'B09', 'Standard', 350.00, 1, NULL),
(140, 4, 'testt', '2026-03-11', '18:01:00', 'B10', 'Standard', 350.00, 1, NULL),
(141, 4, 'testt', '2026-03-11', '18:01:00', 'C01', 'Standard', 350.00, 1, NULL),
(142, 4, 'testt', '2026-03-11', '18:01:00', 'C02', 'Standard', 350.00, 1, NULL),
(143, 4, 'testt', '2026-03-11', '18:01:00', 'C03', 'Standard', 350.00, 1, NULL),
(144, 4, 'testt', '2026-03-11', '18:01:00', 'C04', 'Standard', 350.00, 1, NULL),
(145, 4, 'testt', '2026-03-11', '18:01:00', 'C05', 'Standard', 350.00, 1, NULL),
(146, 4, 'testt', '2026-03-11', '18:01:00', 'C06', 'Standard', 350.00, 1, NULL),
(147, 4, 'testt', '2026-03-11', '18:01:00', 'C07', 'Standard', 350.00, 1, NULL),
(148, 4, 'testt', '2026-03-11', '18:01:00', 'C08', 'Standard', 350.00, 1, NULL),
(149, 4, 'testt', '2026-03-11', '18:01:00', 'C09', 'Standard', 350.00, 1, NULL),
(150, 4, 'testt', '2026-03-11', '18:01:00', 'C10', 'Standard', 350.00, 1, NULL),
(151, 4, 'testt', '2026-03-11', '18:01:00', 'D01', 'Sweet Spot', 550.00, 1, NULL),
(152, 4, 'testt', '2026-03-11', '18:01:00', 'D02', 'Sweet Spot', 550.00, 1, NULL),
(153, 4, 'testt', '2026-03-11', '18:01:00', 'D03', 'Sweet Spot', 550.00, 1, NULL),
(154, 4, 'testt', '2026-03-11', '18:01:00', 'D04', 'Sweet Spot', 550.00, 1, NULL),
(155, 4, 'testt', '2026-03-11', '18:01:00', 'D05', 'Sweet Spot', 550.00, 1, NULL),
(156, 4, 'testt', '2026-03-11', '18:01:00', 'D06', 'Sweet Spot', 550.00, 1, NULL),
(157, 4, 'testt', '2026-03-11', '18:01:00', 'D07', 'Sweet Spot', 550.00, 1, NULL),
(158, 4, 'testt', '2026-03-11', '18:01:00', 'D08', 'Sweet Spot', 550.00, 1, NULL),
(159, 4, 'testt', '2026-03-11', '18:01:00', 'D09', 'Sweet Spot', 550.00, 1, NULL),
(160, 4, 'testt', '2026-03-11', '18:01:00', 'D10', 'Sweet Spot', 550.00, 1, NULL),
(181, 1, 'Sinners', '2026-03-31', '13:00:00', 'E01', 'Standard', 350.00, 1, NULL),
(182, 1, 'Sinners', '2026-03-31', '13:00:00', 'E02', 'Standard', 350.00, 1, NULL),
(183, 1, 'Sinners', '2026-03-31', '13:00:00', 'E03', 'Standard', 350.00, 1, NULL),
(184, 1, 'Sinners', '2026-03-31', '13:00:00', 'E04', 'Standard', 350.00, 1, NULL),
(185, 1, 'Sinners', '2026-03-31', '13:00:00', 'E05', 'Standard', 350.00, 1, NULL),
(186, 1, 'Sinners', '2026-03-31', '13:00:00', 'E06', 'Standard', 350.00, 0, 20),
(187, 1, 'Sinners', '2026-03-31', '13:00:00', 'E07', 'Standard', 350.00, 0, 20),
(188, 1, 'Sinners', '2026-03-31', '13:00:00', 'E08', 'Standard', 350.00, 0, 20),
(189, 1, 'Sinners', '2026-03-31', '13:00:00', 'E09', 'Standard', 350.00, 0, 20),
(190, 1, 'Sinners', '2026-03-31', '13:00:00', 'E10', 'Standard', 350.00, 0, 20),
(201, 5, 'testt', '2026-03-14', '18:01:00', 'A01', 'Premium', 450.00, 1, NULL),
(202, 5, 'testt', '2026-03-14', '18:01:00', 'A02', 'Premium', 450.00, 1, NULL),
(203, 5, 'testt', '2026-03-14', '18:01:00', 'A03', 'Premium', 450.00, 1, NULL),
(204, 5, 'testt', '2026-03-14', '18:01:00', 'A04', 'Premium', 450.00, 1, NULL),
(205, 5, 'testt', '2026-03-14', '18:01:00', 'A05', 'Premium', 450.00, 1, NULL),
(206, 5, 'testt', '2026-03-14', '18:01:00', 'A06', 'Premium', 450.00, 1, NULL),
(207, 5, 'testt', '2026-03-14', '18:01:00', 'A07', 'Premium', 450.00, 1, NULL),
(208, 5, 'testt', '2026-03-14', '18:01:00', 'A08', 'Premium', 450.00, 1, NULL),
(209, 5, 'testt', '2026-03-14', '18:01:00', 'A09', 'Premium', 450.00, 1, NULL),
(210, 5, 'testt', '2026-03-14', '18:01:00', 'A10', 'Premium', 450.00, 1, NULL),
(211, 5, 'testt', '2026-03-14', '18:01:00', 'B01', 'Standard', 350.00, 1, NULL),
(212, 5, 'testt', '2026-03-14', '18:01:00', 'B02', 'Standard', 350.00, 1, NULL),
(213, 5, 'testt', '2026-03-14', '18:01:00', 'B03', 'Standard', 350.00, 1, NULL),
(214, 5, 'testt', '2026-03-14', '18:01:00', 'B04', 'Standard', 350.00, 1, NULL),
(215, 5, 'testt', '2026-03-14', '18:01:00', 'B05', 'Standard', 350.00, 0, 23),
(216, 5, 'testt', '2026-03-14', '18:01:00', 'B06', 'Standard', 350.00, 0, 23),
(217, 5, 'testt', '2026-03-14', '18:01:00', 'B07', 'Standard', 350.00, 0, 23),
(218, 5, 'testt', '2026-03-14', '18:01:00', 'B08', 'Standard', 350.00, 0, 23),
(219, 5, 'testt', '2026-03-14', '18:01:00', 'B09', 'Standard', 350.00, 0, 23),
(220, 5, 'testt', '2026-03-14', '18:01:00', 'B10', 'Standard', 350.00, 0, 23),
(221, 5, 'testt', '2026-03-14', '18:01:00', 'C01', 'Standard', 350.00, 1, NULL),
(222, 5, 'testt', '2026-03-14', '18:01:00', 'C02', 'Standard', 350.00, 1, NULL),
(223, 5, 'testt', '2026-03-14', '18:01:00', 'C03', 'Standard', 350.00, 1, NULL),
(224, 5, 'testt', '2026-03-14', '18:01:00', 'C04', 'Standard', 350.00, 1, NULL),
(225, 5, 'testt', '2026-03-14', '18:01:00', 'C05', 'Standard', 350.00, 1, NULL),
(226, 5, 'testt', '2026-03-14', '18:01:00', 'C06', 'Standard', 350.00, 1, NULL),
(227, 5, 'testt', '2026-03-14', '18:01:00', 'C07', 'Standard', 350.00, 1, NULL),
(228, 5, 'testt', '2026-03-14', '18:01:00', 'C08', 'Standard', 350.00, 1, NULL),
(229, 5, 'testt', '2026-03-14', '18:01:00', 'C09', 'Standard', 350.00, 1, NULL),
(230, 5, 'testt', '2026-03-14', '18:01:00', 'C10', 'Standard', 350.00, 1, NULL),
(231, 5, 'testt', '2026-03-14', '18:01:00', 'D01', 'Sweet Spot', 550.00, 1, NULL),
(232, 5, 'testt', '2026-03-14', '18:01:00', 'D02', 'Sweet Spot', 550.00, 1, NULL),
(233, 5, 'testt', '2026-03-14', '18:01:00', 'D03', 'Sweet Spot', 550.00, 1, NULL),
(234, 5, 'testt', '2026-03-14', '18:01:00', 'D04', 'Sweet Spot', 550.00, 1, NULL),
(235, 5, 'testt', '2026-03-14', '18:01:00', 'D05', 'Sweet Spot', 550.00, 1, NULL),
(236, 5, 'testt', '2026-03-14', '18:01:00', 'D06', 'Sweet Spot', 550.00, 1, NULL),
(237, 5, 'testt', '2026-03-14', '18:01:00', 'D07', 'Sweet Spot', 550.00, 1, NULL),
(238, 5, 'testt', '2026-03-14', '18:01:00', 'D08', 'Sweet Spot', 550.00, 0, 22),
(239, 5, 'testt', '2026-03-14', '18:01:00', 'D09', 'Sweet Spot', 550.00, 0, 22),
(240, 5, 'testt', '2026-03-14', '18:01:00', 'D10', 'Sweet Spot', 550.00, 0, 22);

-- --------------------------------------------------------

--
-- Table structure for table `suggestions`
--

CREATE TABLE `suggestions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `user_name` varchar(100) DEFAULT NULL,
  `user_email` varchar(100) DEFAULT NULL,
  `suggestion` text NOT NULL,
  `status` enum('Pending','Reviewed','Implemented') DEFAULT 'Pending',
  `admin_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `suggestions`
--

INSERT INTO `suggestions` (`id`, `user_id`, `user_name`, `user_email`, `suggestion`, `status`, `admin_notes`, `created_at`) VALUES
(1, 1, 'jaylord', 'jaylord@gmail.com', 'fasd', 'Pending', NULL, '2026-03-08 12:40:22');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_booking`
--

CREATE TABLE `tbl_booking` (
  `b_id` int(11) NOT NULL,
  `u_id` int(11) NOT NULL,
  `movie_name` varchar(255) NOT NULL,
  `show_date` date DEFAULT NULL,
  `showtime` time NOT NULL,
  `booking_fee` decimal(10,2) DEFAULT 0.00,
  `status` enum('Ongoing','Done','Cancelled') DEFAULT 'Ongoing',
  `booking_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `payment_status` enum('Pending','Paid','Refunded') DEFAULT 'Pending',
  `is_visible` tinyint(1) DEFAULT 1,
  `booking_reference` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_booking`
--

INSERT INTO `tbl_booking` (`b_id`, `u_id`, `movie_name`, `show_date`, `showtime`, `booking_fee`, `status`, `booking_date`, `payment_status`, `is_visible`, `booking_reference`) VALUES
(12, 1, 'Sinners', '2026-03-31', '13:00:00', 1350.00, 'Cancelled', '2026-03-08 12:24:10', 'Refunded', 0, 'BK2026030812241074'),
(16, 1, 'Sinners', '2026-03-31', '13:00:00', 1050.00, 'Cancelled', '2026-03-10 14:53:41', 'Refunded', 0, 'BK2026031014534152'),
(17, 1, 'Sinners', '2026-03-31', '13:00:00', 1050.00, 'Cancelled', '2026-03-13 13:34:21', 'Refunded', 0, 'BK2026031313342189'),
(18, 1, 'Sinners', '2026-03-31', '13:00:00', 1400.00, 'Cancelled', '2026-03-13 14:05:12', 'Refunded', 0, 'BK2026031314051248'),
(19, 1, 'Sinners', '2026-03-31', '13:00:00', 700.00, 'Cancelled', '2026-03-13 14:23:15', 'Refunded', 1, 'BK2026031314231504'),
(20, 1, 'Sinners', '2026-03-31', '13:00:00', 1750.00, 'Ongoing', '2026-03-13 14:38:54', 'Paid', 1, 'BK2026031314385476'),
(21, 1, 'testt', '2026-03-14', '18:01:00', 900.00, 'Cancelled', '2026-03-13 15:08:01', 'Refunded', 0, 'BK2026031315080111'),
(22, 1, 'testt', '2026-03-14', '18:01:00', 1650.00, 'Done', '2026-03-14 13:12:51', 'Pending', 0, 'BK2026031413125188'),
(23, 1, 'testt', '2026-03-14', '18:01:00', 2100.00, 'Done', '2026-03-14 13:14:11', 'Pending', 1, 'BK2026031413141123'),
(24, 1, 'Sinners', '2026-03-31', '13:00:00', 1400.00, 'Cancelled', '2026-03-14 13:35:55', 'Refunded', 1, 'BK2026031413355584'),
(25, 1, 'Sinners', '2026-03-31', '13:00:00', 900.00, 'Cancelled', '2026-03-14 14:02:39', 'Refunded', 1, 'BK2026031414023992'),
(26, 1, 'Sinners', '2026-03-31', '13:00:00', 900.00, 'Ongoing', '2026-03-14 14:05:02', 'Pending', 1, 'BK2026031414050246');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `u_id` int(11) NOT NULL,
  `u_name` varchar(100) NOT NULL,
  `u_username` varchar(50) NOT NULL,
  `u_email` varchar(100) NOT NULL,
  `u_pass` varchar(255) NOT NULL,
  `u_role` enum('Admin','Customer') DEFAULT 'Customer',
  `u_status` enum('Active','Inactive') DEFAULT 'Active',
  `created_by` int(11) DEFAULT NULL,
  `created_by_name` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`u_id`, `u_name`, `u_username`, `u_email`, `u_pass`, `u_role`, `u_status`, `created_by`, `created_by_name`, `created_at`) VALUES
(1, 'jaylord', 'jaylord', 'jaylord@gmail.com', '$2y$10$9Z2H5XmtJWLLYtc5mQaMYu1fUAEnKWMeBuDNl9MyyI3lByZrjh99O', 'Customer', 'Active', NULL, NULL, '2026-03-06 14:55:55'),
(2, 'denise', 'denise', 'denise@gmail.com', '$2y$10$tfaB1ojxrQ2bOdWMH9MveOVBtuyeK8Eo7XPGvSuHRzvngWEg2Ar6y', 'Admin', 'Active', NULL, NULL, '2026-03-06 14:56:22'),
(3, 'kenz', 'kenz', 'kenz@gmail.com', '$2y$10$svqh5E6JWT1ExKcMENWMIeEIQ3hTyGFi6Sr23J1SF9em8i8FYUVrS', 'Customer', 'Active', NULL, NULL, '2026-03-06 15:16:25');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_activity_log`
--
ALTER TABLE `admin_activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `booked_seats`
--
ALTER TABLE `booked_seats`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_booking_id` (`booking_id`),
  ADD KEY `idx_seat_number` (`seat_number`);

--
-- Indexes for table `customer_activity_log`
--
ALTER TABLE `customer_activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `movie_id` (`movie_id`),
  ADD KEY `schedule_id` (`schedule_id`),
  ADD KEY `booking_id` (`booking_id`);

--
-- Indexes for table `manual_payments`
--
ALTER TABLE `manual_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `payment_method_id` (`payment_method_id`),
  ADD KEY `verified_by` (`verified_by`);

--
-- Indexes for table `movies`
--
ALTER TABLE `movies`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `movie_schedules`
--
ALTER TABLE `movie_schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `movie_id` (`movie_id`);

--
-- Indexes for table `payment_methods`
--
ALTER TABLE `payment_methods`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `paymongo_payments`
--
ALTER TABLE `paymongo_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `seat_availability`
--
ALTER TABLE `seat_availability`
  ADD PRIMARY KEY (`id`),
  ADD KEY `schedule_id` (`schedule_id`);

--
-- Indexes for table `suggestions`
--
ALTER TABLE `suggestions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `tbl_booking`
--
ALTER TABLE `tbl_booking`
  ADD PRIMARY KEY (`b_id`),
  ADD KEY `u_id` (`u_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`u_id`),
  ADD UNIQUE KEY `u_username` (`u_username`),
  ADD UNIQUE KEY `u_email` (`u_email`),
  ADD KEY `fk_users_created_by` (`created_by`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_activity_log`
--
ALTER TABLE `admin_activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `booked_seats`
--
ALTER TABLE `booked_seats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `customer_activity_log`
--
ALTER TABLE `customer_activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `manual_payments`
--
ALTER TABLE `manual_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `movies`
--
ALTER TABLE `movies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `movie_schedules`
--
ALTER TABLE `movie_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `payment_methods`
--
ALTER TABLE `payment_methods`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `paymongo_payments`
--
ALTER TABLE `paymongo_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `seat_availability`
--
ALTER TABLE `seat_availability`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=241;

--
-- AUTO_INCREMENT for table `suggestions`
--
ALTER TABLE `suggestions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tbl_booking`
--
ALTER TABLE `tbl_booking`
  MODIFY `b_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `u_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_activity_log`
--
ALTER TABLE `admin_activity_log`
  ADD CONSTRAINT `admin_activity_log_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `users` (`u_id`) ON DELETE CASCADE;

--
-- Constraints for table `booked_seats`
--
ALTER TABLE `booked_seats`
  ADD CONSTRAINT `booked_seats_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `tbl_booking` (`b_id`) ON DELETE CASCADE;

--
-- Constraints for table `customer_activity_log`
--
ALTER TABLE `customer_activity_log`
  ADD CONSTRAINT `customer_activity_log_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `users` (`u_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `customer_activity_log_ibfk_2` FOREIGN KEY (`movie_id`) REFERENCES `movies` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `customer_activity_log_ibfk_3` FOREIGN KEY (`schedule_id`) REFERENCES `movie_schedules` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `customer_activity_log_ibfk_4` FOREIGN KEY (`booking_id`) REFERENCES `tbl_booking` (`b_id`) ON DELETE SET NULL;

--
-- Constraints for table `manual_payments`
--
ALTER TABLE `manual_payments`
  ADD CONSTRAINT `manual_payments_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `tbl_booking` (`b_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `manual_payments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`u_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `manual_payments_ibfk_3` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `manual_payments_ibfk_4` FOREIGN KEY (`verified_by`) REFERENCES `users` (`u_id`) ON DELETE SET NULL;

--
-- Constraints for table `movie_schedules`
--
ALTER TABLE `movie_schedules`
  ADD CONSTRAINT `movie_schedules_ibfk_1` FOREIGN KEY (`movie_id`) REFERENCES `movies` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `paymongo_payments`
--
ALTER TABLE `paymongo_payments`
  ADD CONSTRAINT `paymongo_payments_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `tbl_booking` (`b_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `paymongo_payments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`u_id`) ON DELETE CASCADE;

--
-- Constraints for table `seat_availability`
--
ALTER TABLE `seat_availability`
  ADD CONSTRAINT `seat_availability_ibfk_1` FOREIGN KEY (`schedule_id`) REFERENCES `movie_schedules` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `suggestions`
--
ALTER TABLE `suggestions`
  ADD CONSTRAINT `suggestions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`u_id`) ON DELETE SET NULL;

--
-- Constraints for table `tbl_booking`
--
ALTER TABLE `tbl_booking`
  ADD CONSTRAINT `tbl_booking_ibfk_1` FOREIGN KEY (`u_id`) REFERENCES `users` (`u_id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`u_id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
