-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 25, 2026 at 04:50 PM
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

--
-- Dumping data for table `admin_activity_log`
--

INSERT INTO `admin_activity_log` (`id`, `admin_id`, `action`, `details`, `full_details`, `target_id`, `created_at`) VALUES
(1, 1, 'ADD_STAFF', 'Added new staff: kelly kenzie (kenz - kenz@gmail.com)', NULL, 2, '2026-04-25 13:07:31');

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
(1, 1, 'A01', 'Premium', 550.00, '2026-04-25 13:59:47'),
(2, 1, 'A02', 'Premium', 550.00, '2026-04-25 13:59:47'),
(3, 2, 'A05', 'Premium', 550.00, '2026-04-25 14:33:31'),
(4, 2, 'A06', 'Premium', 550.00, '2026-04-25 14:33:31');

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

--
-- Dumping data for table `customer_activity_log`
--

INSERT INTO `customer_activity_log` (`id`, `customer_id`, `action_type`, `details`, `movie_id`, `schedule_id`, `booking_id`, `created_at`) VALUES
(1, 3, 'LOGIN', 'User logged in: jaylord laspuna', NULL, NULL, NULL, '2026-04-25 13:58:50'),
(2, 3, 'LOGIN', 'User logged in: jaylord laspuna', NULL, NULL, NULL, '2026-04-25 14:14:21'),
(3, 3, 'LOGIN', 'User logged in: jaylord laspuna', NULL, NULL, NULL, '2026-04-25 14:17:42');

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
  `venue_id` int(11) DEFAULT NULL,
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

INSERT INTO `movies` (`id`, `title`, `director`, `genre`, `duration`, `rating`, `description`, `poster_url`, `trailer_url`, `venue_id`, `standard_price`, `premium_price`, `sweet_spot_price`, `is_active`, `added_by`, `updated_by`, `created_at`, `last_updated`) VALUES
(1, 'Sinners(2025)', 'Ryan Coogler', 'Horror, with elements of drama and historical themes', '2hours 15minutes', 'PG-13', 'Sinners is a 2025 horror film directed by Ryan Coogler, featuring Michael B. Jordan in dual roles as twin brothers confronting supernatural evils in the Mississippi Delta.', 'https://mlpnk72yciwc.i.optimole.com/cqhiHLc.IIZS~2ef73/w:auto/h:auto/q:75/https://bleedingcool.com/wp-content/uploads/2025/03/sinners_ver15_xxlg.jpg', 'https://www.youtube.com/watch?v=bKGxHflevuk', 1, 500.00, 550.00, 700.00, 1, 1, 1, '2026-04-25 13:32:56', '2026-04-25 13:36:00'),
(2, 'The Notebook', 'Nick Cassavetes', 'Drama, Romance', '2h 3m', 'G', 'The Notebook, based on Nicholas Sparks\' 1996 novel and adapted into a 2004 film directed by Nick Cassavetes, tells the story of Noah Calhoun, a working-class young man, and Allie Hamilton, a wealthy young woman, who fall in love during a summer in Seabrook Island, South Carolina, in the 1940s. Their romance is intense and passionate, but societal expectations and Allie’s parents’ disapproval force them apart. Noah writes to Allie every day, but her mother intercepts the letters, leaving them separated for years.', 'https://i.pinimg.com/736x/9b/b8/d0/9bb8d05b240ab06cd93e38d826ad4064.jpg', 'https://www.youtube.com/watch?v=BjJcYdEOI0k', 2, 500.00, 700.00, 800.00, 1, 1, 1, '2026-04-25 13:35:46', '2026-04-25 13:37:36'),
(3, 'Avengers: Endgame', 'Anthony and Joe Russo', 'Action, Adventure', '3hours 1minute', 'PG-13', 'Avengers: Endgame is a 2019 superhero film where the surviving Avengers unite to reverse Thanos\' catastrophic snap and restore the universe.', 'https://c8.alamy.com/comp/T7C69P/avengers-endgame-2019-directed-by-anthony-and-joe-russo-starring-bradley-cooper-brie-larson-and-chris-hemsworth-epic-conclusion-and-22nd-film-in-the-marvel-cinematic-universe-T7C69P.jpg', 'https://www.youtube.com/watch?v=TcMBFSGVi1c', 3, 1000.00, 1500.00, 2000.00, 1, 1, 1, '2026-04-25 13:53:45', '2026-04-25 14:12:51');

-- --------------------------------------------------------

--
-- Table structure for table `movie_schedules`
--

CREATE TABLE `movie_schedules` (
  `id` int(11) NOT NULL,
  `movie_id` int(11) NOT NULL,
  `venue_id` int(11) DEFAULT NULL,
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

INSERT INTO `movie_schedules` (`id`, `movie_id`, `venue_id`, `movie_title`, `show_date`, `showtime`, `total_seats`, `available_seats`, `is_active`, `created_at`) VALUES
(1, 1, NULL, 'Sinners(2025)', '2026-04-26', '09:00:00', 40, 36, 1, '2026-04-25 13:39:07');

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
  `venue_id` int(11) DEFAULT NULL,
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

INSERT INTO `seat_availability` (`id`, `schedule_id`, `venue_id`, `movie_title`, `show_date`, `showtime`, `seat_number`, `seat_type`, `price`, `is_available`, `booking_id`) VALUES
(1, 1, NULL, 'Sinners(2025)', '2026-04-26', '09:00:00', 'A01', 'Premium', 550.00, 0, 1),
(2, 1, NULL, 'Sinners(2025)', '2026-04-26', '09:00:00', 'A02', 'Premium', 550.00, 0, 1),
(3, 1, NULL, 'Sinners(2025)', '2026-04-26', '09:00:00', 'A03', 'Premium', 550.00, 1, NULL),
(4, 1, NULL, 'Sinners(2025)', '2026-04-26', '09:00:00', 'A04', 'Premium', 550.00, 1, NULL),
(5, 1, NULL, 'Sinners(2025)', '2026-04-26', '09:00:00', 'A05', 'Premium', 550.00, 0, 2),
(6, 1, NULL, 'Sinners(2025)', '2026-04-26', '09:00:00', 'A06', 'Premium', 550.00, 0, 2),
(7, 1, NULL, 'Sinners(2025)', '2026-04-26', '09:00:00', 'A07', 'Premium', 550.00, 1, NULL),
(8, 1, NULL, 'Sinners(2025)', '2026-04-26', '09:00:00', 'A08', 'Premium', 550.00, 1, NULL),
(9, 1, NULL, 'Sinners(2025)', '2026-04-26', '09:00:00', 'A09', 'Premium', 550.00, 1, NULL),
(10, 1, NULL, 'Sinners(2025)', '2026-04-26', '09:00:00', 'A10', 'Premium', 550.00, 1, NULL),
(11, 1, NULL, 'Sinners(2025)', '2026-04-26', '09:00:00', 'B01', 'Sweet Spot', 700.00, 1, NULL),
(12, 1, NULL, 'Sinners(2025)', '2026-04-26', '09:00:00', 'B02', 'Standard', 500.00, 1, NULL),
(13, 1, NULL, 'Sinners(2025)', '2026-04-26', '09:00:00', 'B03', 'Standard', 500.00, 1, NULL),
(14, 1, NULL, 'Sinners(2025)', '2026-04-26', '09:00:00', 'B04', 'Standard', 500.00, 1, NULL),
(15, 1, NULL, 'Sinners(2025)', '2026-04-26', '09:00:00', 'B05', 'Standard', 500.00, 1, NULL),
(16, 1, NULL, 'Sinners(2025)', '2026-04-26', '09:00:00', 'B06', 'Standard', 500.00, 1, NULL),
(17, 1, NULL, 'Sinners(2025)', '2026-04-26', '09:00:00', 'B07', 'Standard', 500.00, 1, NULL),
(18, 1, NULL, 'Sinners(2025)', '2026-04-26', '09:00:00', 'B08', 'Standard', 500.00, 1, NULL),
(19, 1, NULL, 'Sinners(2025)', '2026-04-26', '09:00:00', 'B09', 'Standard', 500.00, 1, NULL),
(20, 1, NULL, 'Sinners(2025)', '2026-04-26', '09:00:00', 'B10', 'Sweet Spot', 700.00, 1, NULL),
(21, 1, NULL, 'Sinners(2025)', '2026-04-26', '09:00:00', 'C01', 'Sweet Spot', 700.00, 1, NULL),
(22, 1, NULL, 'Sinners(2025)', '2026-04-26', '09:00:00', 'C02', 'Standard', 500.00, 1, NULL),
(23, 1, NULL, 'Sinners(2025)', '2026-04-26', '09:00:00', 'C03', 'Standard', 500.00, 1, NULL),
(24, 1, NULL, 'Sinners(2025)', '2026-04-26', '09:00:00', 'C04', 'Standard', 500.00, 1, NULL),
(25, 1, NULL, 'Sinners(2025)', '2026-04-26', '09:00:00', 'C05', 'Standard', 500.00, 1, NULL),
(26, 1, NULL, 'Sinners(2025)', '2026-04-26', '09:00:00', 'C06', 'Standard', 500.00, 1, NULL),
(27, 1, NULL, 'Sinners(2025)', '2026-04-26', '09:00:00', 'C07', 'Standard', 500.00, 1, NULL),
(28, 1, NULL, 'Sinners(2025)', '2026-04-26', '09:00:00', 'C08', 'Standard', 500.00, 1, NULL),
(29, 1, NULL, 'Sinners(2025)', '2026-04-26', '09:00:00', 'C09', 'Standard', 500.00, 1, NULL),
(30, 1, NULL, 'Sinners(2025)', '2026-04-26', '09:00:00', 'C10', 'Sweet Spot', 700.00, 1, NULL),
(31, 1, NULL, 'Sinners(2025)', '2026-04-26', '09:00:00', 'D01', 'Sweet Spot', 700.00, 1, NULL),
(32, 1, NULL, 'Sinners(2025)', '2026-04-26', '09:00:00', 'D02', 'Sweet Spot', 700.00, 1, NULL),
(33, 1, NULL, 'Sinners(2025)', '2026-04-26', '09:00:00', 'D03', 'Sweet Spot', 700.00, 1, NULL),
(34, 1, NULL, 'Sinners(2025)', '2026-04-26', '09:00:00', 'D04', 'Sweet Spot', 700.00, 1, NULL),
(35, 1, NULL, 'Sinners(2025)', '2026-04-26', '09:00:00', 'D05', 'Sweet Spot', 700.00, 1, NULL),
(36, 1, NULL, 'Sinners(2025)', '2026-04-26', '09:00:00', 'D06', 'Sweet Spot', 700.00, 1, NULL),
(37, 1, NULL, 'Sinners(2025)', '2026-04-26', '09:00:00', 'D07', 'Sweet Spot', 700.00, 1, NULL),
(38, 1, NULL, 'Sinners(2025)', '2026-04-26', '09:00:00', 'D08', 'Sweet Spot', 700.00, 1, NULL),
(39, 1, NULL, 'Sinners(2025)', '2026-04-26', '09:00:00', 'D09', 'Sweet Spot', 700.00, 1, NULL),
(40, 1, NULL, 'Sinners(2025)', '2026-04-26', '09:00:00', 'D10', 'Sweet Spot', 700.00, 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `staff_activity_log`
--

CREATE TABLE `staff_activity_log` (
  `id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff_activity_log`
--

INSERT INTO `staff_activity_log` (`id`, `staff_id`, `action`, `booking_id`, `details`, `created_at`) VALUES
(1, 2, 'CHECK_IN', 1, 'Checked in customer', '2026-04-25 14:40:08');

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

-- --------------------------------------------------------

--
-- Table structure for table `tbl_booking`
--

CREATE TABLE `tbl_booking` (
  `b_id` int(11) NOT NULL,
  `u_id` int(11) NOT NULL,
  `movie_name` varchar(255) NOT NULL,
  `venue_id` int(11) DEFAULT NULL,
  `show_date` date DEFAULT NULL,
  `showtime` time NOT NULL,
  `booking_fee` decimal(10,2) DEFAULT 0.00,
  `status` enum('Ongoing','Done','Cancelled') DEFAULT 'Ongoing',
  `booking_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `payment_status` enum('Pending','Paid','Refunded','Pending Verification') DEFAULT 'Pending',
  `attendance_status` enum('Pending','Present','Completed') DEFAULT 'Pending',
  `is_visible` tinyint(1) DEFAULT 1,
  `booking_reference` varchar(20) DEFAULT NULL,
  `qr_code` varchar(255) DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `verified_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_booking`
--

INSERT INTO `tbl_booking` (`b_id`, `u_id`, `movie_name`, `venue_id`, `show_date`, `showtime`, `booking_fee`, `status`, `booking_date`, `payment_status`, `attendance_status`, `is_visible`, `booking_reference`, `qr_code`, `verified_at`, `verified_by`) VALUES
(1, 3, 'Sinners(2025)', NULL, '2026-04-26', '09:00:00', 1100.00, 'Ongoing', '2026-04-25 13:59:47', 'Paid', 'Present', 1, 'BK2026042521594790', NULL, '2026-04-25 14:40:08', 2),
(2, 3, 'Sinners(2025)', NULL, '2026-04-26', '09:00:00', 1100.00, 'Ongoing', '2026-04-25 14:33:31', 'Pending', 'Pending', 1, 'BK2026042522333136', NULL, NULL, NULL);

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
  `u_role` enum('Admin','Customer','Owner','Staff') DEFAULT 'Customer',
  `u_status` enum('Active','Inactive') DEFAULT 'Active',
  `created_by` int(11) DEFAULT NULL,
  `created_by_name` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `is_visible` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`u_id`, `u_name`, `u_username`, `u_email`, `u_pass`, `u_role`, `u_status`, `created_by`, `created_by_name`, `created_at`, `last_login`, `is_visible`) VALUES
(1, 'dexter juirst', 'dexter', 'dexter@gmail.com', '$2y$10$zvexvbrFyttJXcGeukoveOxZvgEx3mwmcnaKpXJdmT1mXM4YGgQK2', 'Admin', 'Active', NULL, NULL, '2026-04-25 12:54:01', '2026-04-25 14:07:59', 1),
(2, 'kelly kenzie', 'kenz', 'kenz@gmail.com', '$2y$10$k.354F4lRA6oMpMMw7TCZeRfZaT8.Nl3ZEwhKPirzI6SczpKATBce', 'Staff', 'Active', 1, 'dexter juirst', '2026-04-25 13:07:31', '2026-04-25 14:04:13', 1),
(3, 'jaylord laspuna', 'jaylord', 'jaylord@gmail.com', '$2y$10$HWbwuqM4nHA/HG1BE1CCRuK8fgzC7fM94Xy/C5gVjt7L61fWsNdIu', 'Customer', 'Active', NULL, NULL, '2026-04-25 13:08:16', '2026-04-25 14:17:42', 1),
(4, 'Denise Kethley Cana', 'denise', 'denise@gmail.com', '$2y$10$LDpV032frJ9MN.bjvhqSRuB3V3/3Wz6SxBq9NewCniYuAUKKM11fq', 'Admin', 'Active', NULL, NULL, '2026-04-25 13:08:36', '2026-04-25 13:10:10', 1);

-- --------------------------------------------------------

--
-- Table structure for table `venues`
--

CREATE TABLE `venues` (
  `id` int(11) NOT NULL,
  `venue_name` varchar(255) NOT NULL,
  `venue_location` varchar(500) NOT NULL,
  `google_maps_link` varchar(500) DEFAULT NULL,
  `venue_photo_path` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `venues`
--

INSERT INTO `venues` (`id`, `venue_name`, `venue_location`, `google_maps_link`, `venue_photo_path`, `created_at`, `updated_at`) VALUES
(1, 'St.Cecilia College', 'Ward II Minglanilla Cebu', 'https://www.google.com/maps/place/St.+Cecilia\'s+College+-+Cebu,+Inc./@10.2447327,123.7943992,3a,75y/data=!3m8!1e2!3m6!1sCIHM0ogKEICAgIDBxoOXAg!2e10!3e12!6shttps:%2F%2Flh3.googleusercontent.com%2Fgps-cs-s%2FAPNQkAF6SS8GTNPRFUJt5xkZyhlGzARyZFIUFTiRS6lxfNsCaQRz2qRUChcJde8NLH9jm24-hG0SfitgP_rbz3MkzplrPRX2wwutyAowdugcuhsidNHbbpTV0aza3w6YUCZoGz2ue82i%3Dw86-h114-k-no!7i3024!8i4032!4m7!3m6!1s0x33a977e250bd286d:0x377f6ed9ed966fe7!8m2!3d10.2446906!4d123.7944106!10e5!16s%2Fg%2F1tfksvw4?entry=ttu&g_ep=EgoyM', 'uploads/venue/venue_1777123633_6578.webp', '2026-04-25 13:27:13', NULL),
(2, 'SM CINEMA', 'Purok 13 cadulawan minglanilla cebu', 'https://www.google.com/maps/place/Cadulawan,+Minglanilla,+Cebu/@10.2701001,123.7749591,3a,75y,38.68h,73.15t/data=!3m7!1e1!3m5!1sevD9mV_wf05akytvLeXazQ!2e0!6shttps:%2F%2Fstreetviewpixels-pa.googleapis.com%2Fv1%2Fthumbnail%3Fcb_client%3Dmaps_sv.tactile%26w%3D900%26h%3D600%26pitch%3D16.85183500971155%26panoid%3DevD9mV_wf05akytvLeXazQ%26yaw%3D38.679952396201784!7i16384!8i8192!4m6!3m5!1s0x33a977c908967b4f:0xe6d3367b7633da0a!8m2!3d10.2656255!4d123.7769125!16s%2Fg%2F11gbfnsnlq?entry=ttu&g_ep=EgoyMDI2MD', 'uploads/venue/venue_1777123776_7783.jpeg', '2026-04-25 13:29:36', NULL),
(3, 'GAISANO GRAND MALL', 'Minglanilla Cebu', 'https://www.google.com/maps/place/Gaisano+Grand+Mall+Minglanilla/@10.2445465,123.7927863,3a,75y,156.83h,101.85t/data=!3m7!1e1!3m5!1sKa4xGpvlt_c_MMnDmCoqBA!2e0!6shttps:%2F%2Fstreetviewpixels-pa.googleapis.com%2Fv1%2Fthumbnail%3Fcb_client%3Dmaps_sv.tactile%26w%3D900%26h%3D600%26pitch%3D-11.850218235375763%26panoid%3DKa4xGpvlt_c_MMnDmCoqBA%26yaw%3D156.83303171951826!7i16384!8i8192!4m6!3m5!1s0x33a977e30f47aa91:0xbd0961c41aa63123!8m2!3d10.2441355!4d123.7929127!16s%2Fg%2F1pp2vc3yx?entry=ttu&g_ep=EgoyM', 'uploads/venue/venue_1777125356_4922.jpg', '2026-04-25 13:55:56', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_activity_log`
--
ALTER TABLE `admin_activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_admin_id` (`admin_id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_action` (`action`);

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
  ADD KEY `movie_id` (`movie_id`),
  ADD KEY `schedule_id` (`schedule_id`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `idx_customer_id` (`customer_id`),
  ADD KEY `idx_action_type` (`action_type`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `manual_payments`
--
ALTER TABLE `manual_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `payment_method_id` (`payment_method_id`),
  ADD KEY `verified_by` (`verified_by`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_booking_id` (`booking_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `movies`
--
ALTER TABLE `movies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `added_by` (`added_by`),
  ADD KEY `updated_by` (`updated_by`),
  ADD KEY `idx_venue_id` (`venue_id`),
  ADD KEY `idx_is_active` (`is_active`);
ALTER TABLE `movies` ADD FULLTEXT KEY `idx_movie_search` (`title`,`description`);

--
-- Indexes for table `movie_schedules`
--
ALTER TABLE `movie_schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_show_date` (`show_date`),
  ADD KEY `idx_movie_id` (`movie_id`),
  ADD KEY `idx_venue_id` (`venue_id`),
  ADD KEY `idx_show_datetime` (`show_date`,`showtime`);

--
-- Indexes for table `payment_methods`
--
ALTER TABLE `payment_methods`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_display_order` (`display_order`);

--
-- Indexes for table `paymongo_payments`
--
ALTER TABLE `paymongo_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_booking_id` (`booking_id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indexes for table `seat_availability`
--
ALTER TABLE `seat_availability`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_schedule_id` (`schedule_id`),
  ADD KEY `idx_venue_id` (`venue_id`),
  ADD KEY `idx_availability` (`schedule_id`,`seat_number`,`is_available`);

--
-- Indexes for table `staff_activity_log`
--
ALTER TABLE `staff_activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_staff_id` (`staff_id`),
  ADD KEY `idx_booking_id` (`booking_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `suggestions`
--
ALTER TABLE `suggestions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `tbl_booking`
--
ALTER TABLE `tbl_booking`
  ADD PRIMARY KEY (`b_id`),
  ADD UNIQUE KEY `booking_reference` (`booking_reference`),
  ADD KEY `verified_by` (`verified_by`),
  ADD KEY `idx_user_id` (`u_id`),
  ADD KEY `idx_booking_reference` (`booking_reference`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_payment_status` (`payment_status`),
  ADD KEY `idx_attendance_status` (`attendance_status`),
  ADD KEY `idx_show_date` (`show_date`),
  ADD KEY `idx_is_visible` (`is_visible`),
  ADD KEY `idx_qr_code` (`qr_code`),
  ADD KEY `idx_venue_id` (`venue_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`u_id`),
  ADD UNIQUE KEY `u_username` (`u_username`),
  ADD UNIQUE KEY `u_email` (`u_email`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `venues`
--
ALTER TABLE `venues`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `venue_name` (`venue_name`),
  ADD KEY `idx_venue_name` (`venue_name`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_activity_log`
--
ALTER TABLE `admin_activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `booked_seats`
--
ALTER TABLE `booked_seats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `customer_activity_log`
--
ALTER TABLE `customer_activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `manual_payments`
--
ALTER TABLE `manual_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `movies`
--
ALTER TABLE `movies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `movie_schedules`
--
ALTER TABLE `movie_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `payment_methods`
--
ALTER TABLE `payment_methods`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `paymongo_payments`
--
ALTER TABLE `paymongo_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `seat_availability`
--
ALTER TABLE `seat_availability`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `staff_activity_log`
--
ALTER TABLE `staff_activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `suggestions`
--
ALTER TABLE `suggestions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_booking`
--
ALTER TABLE `tbl_booking`
  MODIFY `b_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `u_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `venues`
--
ALTER TABLE `venues`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

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
-- Constraints for table `movies`
--
ALTER TABLE `movies`
  ADD CONSTRAINT `movies_ibfk_1` FOREIGN KEY (`venue_id`) REFERENCES `venues` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `movies_ibfk_2` FOREIGN KEY (`added_by`) REFERENCES `users` (`u_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `movies_ibfk_3` FOREIGN KEY (`updated_by`) REFERENCES `users` (`u_id`) ON DELETE SET NULL;

--
-- Constraints for table `movie_schedules`
--
ALTER TABLE `movie_schedules`
  ADD CONSTRAINT `movie_schedules_ibfk_1` FOREIGN KEY (`movie_id`) REFERENCES `movies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `movie_schedules_ibfk_2` FOREIGN KEY (`venue_id`) REFERENCES `venues` (`id`) ON DELETE SET NULL;

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
  ADD CONSTRAINT `seat_availability_ibfk_1` FOREIGN KEY (`schedule_id`) REFERENCES `movie_schedules` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `seat_availability_ibfk_2` FOREIGN KEY (`venue_id`) REFERENCES `venues` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `staff_activity_log`
--
ALTER TABLE `staff_activity_log`
  ADD CONSTRAINT `staff_activity_log_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `users` (`u_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `staff_activity_log_ibfk_2` FOREIGN KEY (`booking_id`) REFERENCES `tbl_booking` (`b_id`) ON DELETE SET NULL;

--
-- Constraints for table `suggestions`
--
ALTER TABLE `suggestions`
  ADD CONSTRAINT `suggestions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`u_id`) ON DELETE SET NULL;

--
-- Constraints for table `tbl_booking`
--
ALTER TABLE `tbl_booking`
  ADD CONSTRAINT `tbl_booking_ibfk_1` FOREIGN KEY (`u_id`) REFERENCES `users` (`u_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tbl_booking_ibfk_2` FOREIGN KEY (`verified_by`) REFERENCES `users` (`u_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `tbl_booking_ibfk_3` FOREIGN KEY (`venue_id`) REFERENCES `venues` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`u_id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
