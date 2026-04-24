-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 24, 2026 at 09:33 AM
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
(2, 8, 'ADD_STAFF', 'Added new staff: kelly kenzie Cana torrefiel (kelly@gmail.com)', NULL, 9, '2026-04-23 10:11:18');

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
(30, 26, 'A08', 'Premium', 450.00, '2026-03-14 14:05:02'),
(31, 27, 'C09', 'Standard', 350.00, '2026-03-14 14:49:37'),
(32, 27, 'C10', 'Standard', 350.00, '2026-03-14 14:49:37'),
(33, 28, 'A09', 'Premium', 450.00, '2026-03-14 16:22:42'),
(34, 28, 'B10', 'Sweet Spot', 550.00, '2026-03-14 16:22:42'),
(35, 20, 'E01', 'Standard', 350.00, '2026-03-15 08:14:37'),
(36, 20, 'E02', 'Standard', 350.00, '2026-03-15 08:14:37'),
(37, 20, 'E03', 'Standard', 350.00, '2026-03-15 08:14:37'),
(38, 20, 'E04', 'Standard', 350.00, '2026-03-15 08:14:37'),
(39, 20, 'E05', 'Standard', 350.00, '2026-03-15 08:14:37'),
(42, 29, 'B03', 'Standard', 350.00, '2026-04-01 05:14:48'),
(43, 29, 'B04', 'Standard', 350.00, '2026-04-01 05:14:48'),
(48, 30, 'A05', 'Premium', 450.00, '2026-04-18 06:31:07'),
(49, 30, 'A06', 'Premium', 450.00, '2026-04-18 06:31:07'),
(50, 31, 'C01', 'Standard', 350.00, '2026-04-23 09:29:56'),
(51, 31, 'C02', 'Standard', 350.00, '2026-04-23 09:29:56'),
(52, 31, 'C03', 'Standard', 350.00, '2026-04-23 09:29:56'),
(53, 31, 'C04', 'Standard', 350.00, '2026-04-23 09:29:56'),
(54, 31, 'C05', 'Standard', 350.00, '2026-04-23 09:29:56'),
(55, 31, 'C06', 'Standard', 350.00, '2026-04-23 09:29:56'),
(56, 31, 'C07', 'Standard', 350.00, '2026-04-23 09:29:56'),
(57, 31, 'C08', 'Standard', 350.00, '2026-04-23 09:29:56'),
(58, 31, 'C09', 'Standard', 350.00, '2026-04-23 09:29:56'),
(59, 31, 'C10', 'Standard', 350.00, '2026-04-23 09:29:56'),
(60, 31, 'D01', 'Sweet Spot', 5500.00, '2026-04-23 09:29:56'),
(61, 31, 'D02', 'Sweet Spot', 5500.00, '2026-04-23 09:29:56'),
(62, 31, 'D03', 'Sweet Spot', 5500.00, '2026-04-23 09:29:56'),
(63, 31, 'D04', 'Sweet Spot', 5500.00, '2026-04-23 09:29:56'),
(64, 31, 'D05', 'Sweet Spot', 5500.00, '2026-04-23 09:29:56'),
(65, 31, 'D06', 'Sweet Spot', 5500.00, '2026-04-23 09:29:56'),
(66, 31, 'D07', 'Sweet Spot', 5500.00, '2026-04-23 09:29:56'),
(67, 31, 'D08', 'Sweet Spot', 5500.00, '2026-04-23 09:29:56'),
(68, 31, 'D09', 'Sweet Spot', 5500.00, '2026-04-23 09:29:56'),
(69, 31, 'D10', 'Sweet Spot', 5500.00, '2026-04-23 09:29:56'),
(70, 32, 'B05', 'Standard', 350.00, '2026-04-23 13:20:57'),
(71, 32, 'B06', 'Standard', 350.00, '2026-04-23 13:20:57'),
(72, 32, 'B07', 'Standard', 350.00, '2026-04-23 13:20:57'),
(73, 32, 'B08', 'Standard', 350.00, '2026-04-23 13:20:57'),
(74, 32, 'C05', 'Standard', 350.00, '2026-04-23 13:20:57'),
(75, 32, 'C06', 'Standard', 350.00, '2026-04-23 13:20:57'),
(76, 32, 'C07', 'Standard', 350.00, '2026-04-23 13:20:57'),
(77, 32, 'C08', 'Standard', 350.00, '2026-04-23 13:20:57'),
(78, 33, 'A03', 'Premium', 450.00, '2026-04-23 14:46:47'),
(79, 33, 'A04', 'Premium', 450.00, '2026-04-23 14:46:47'),
(80, 34, 'A07', 'Premium', 450.00, '2026-04-23 14:48:02'),
(81, 34, 'A08', 'Premium', 450.00, '2026-04-23 14:48:02'),
(82, 35, 'A09', 'Premium', 450.00, '2026-04-23 15:08:33'),
(83, 35, 'A10', 'Premium', 450.00, '2026-04-23 15:08:33'),
(84, 36, 'A09', 'Premium', 450.00, '2026-04-24 07:28:48'),
(85, 36, 'A10', 'Premium', 450.00, '2026-04-24 07:28:48'),
(86, 37, 'D09', 'Sweet Spot', 5500.00, '2026-04-24 07:29:55'),
(87, 37, 'D10', 'Sweet Spot', 5500.00, '2026-04-24 07:29:55'),
(88, 38, 'D07', 'Sweet Spot', 5500.00, '2026-04-24 07:37:50'),
(89, 38, 'D08', 'Sweet Spot', 5500.00, '2026-04-24 07:37:50'),
(90, 39, 'A01', 'Premium', 1500.00, '2026-04-24 07:41:30'),
(91, 39, 'A02', 'Premium', 1500.00, '2026-04-24 07:41:30'),
(92, 40, 'A06', 'Premium', 1500.00, '2026-04-24 09:32:04'),
(93, 40, 'A07', 'Premium', 1500.00, '2026-04-24 09:32:04'),
(94, 40, 'A08', 'Premium', 1500.00, '2026-04-24 09:32:04');

-- --------------------------------------------------------

--
-- Table structure for table `customer_activity_log`
--

CREATE TABLE `customer_activity_log` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `action_type` enum('BOOKING','MOVIE_VIEW','LOGIN') NOT NULL,
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
(1, 1, 'LOGIN', 'User logged in: jaylord', NULL, NULL, NULL, '2026-04-18 11:22:01'),
(2, 1, 'LOGIN', 'User logged in: jaylord', NULL, NULL, NULL, '2026-04-21 06:03:56'),
(3, 1, 'LOGIN', 'User logged in: jaylord', NULL, NULL, NULL, '2026-04-23 07:37:02'),
(4, 1, 'LOGIN', 'User logged in: jaylord', NULL, NULL, NULL, '2026-04-23 07:50:07'),
(5, 1, 'LOGIN', 'User logged in: jaylord', NULL, NULL, NULL, '2026-04-23 08:03:08'),
(6, 1, 'LOGIN', 'User logged in: jaylord', NULL, NULL, NULL, '2026-04-23 08:05:53'),
(7, 1, 'LOGIN', 'User logged in: jaylord', NULL, NULL, NULL, '2026-04-23 09:11:02'),
(8, 1, 'LOGIN', 'User logged in: jaylord', NULL, NULL, NULL, '2026-04-23 10:00:00'),
(9, 1, 'LOGIN', 'User logged in: jaylord', NULL, NULL, NULL, '2026-04-23 10:20:46'),
(10, 1, 'LOGIN', 'User logged in: jaylord', NULL, NULL, NULL, '2026-04-23 13:20:21'),
(11, 1, 'LOGIN', 'User logged in: jaylord', NULL, NULL, NULL, '2026-04-23 13:22:23'),
(12, 1, 'LOGIN', 'User logged in: jaylord', NULL, NULL, NULL, '2026-04-23 14:14:13'),
(13, 1, 'LOGIN', 'User logged in: jaylord', NULL, NULL, NULL, '2026-04-23 14:16:52'),
(14, 1, 'LOGIN', 'User logged in: jaylord', NULL, NULL, NULL, '2026-04-23 14:47:30'),
(15, 1, 'LOGIN', 'User logged in: jaylord', NULL, NULL, NULL, '2026-04-23 14:48:54'),
(16, 1, 'LOGIN', 'User logged in: jaylord', NULL, NULL, NULL, '2026-04-23 14:49:49'),
(17, 1, 'LOGIN', 'User logged in: jaylord', NULL, NULL, NULL, '2026-04-23 15:41:30'),
(18, 1, 'LOGIN', 'User logged in: jaylord', NULL, NULL, NULL, '2026-04-24 07:27:31'),
(19, 1, 'LOGIN', 'User logged in: jaylord', NULL, NULL, NULL, '2026-04-24 07:32:03'),
(20, 1, 'LOGIN', 'User logged in: jaylord', NULL, NULL, NULL, '2026-04-24 07:41:10'),
(21, 1, 'LOGIN', 'User logged in: jaylord', NULL, NULL, NULL, '2026-04-24 08:41:59'),
(22, 1, 'LOGIN', 'User logged in: jaylord', NULL, NULL, NULL, '2026-04-24 09:03:03'),
(23, 1, 'LOGIN', 'User logged in: jaylord', NULL, NULL, NULL, '2026-04-24 09:06:41'),
(24, 1, 'LOGIN', 'User logged in: jaylord', NULL, NULL, NULL, '2026-04-24 09:15:32');

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
(1, 12, 1, 1, '0912112344', 1350.00, 'uploads/payments/payment_BK2026030812241074_1772972725.png', 'Verified', '', NULL, '2026-03-08 12:26:27', '2026-03-08 12:25:25'),
(2, 20, 1, 1, '0912112344', 1750.00, 'uploads/payments/payment_BK2026031314385476_1773413023.jpg', 'Verified', 'Okay thank you sir', NULL, '2026-03-13 15:12:49', '2026-03-13 14:43:43'),
(3, 32, 1, 1, '09267630945', 2800.00, 'uploads/payments/payment_BK2026042313205762_1776950506.jpg', 'Verified', 'thank you', 8, '2026-04-23 13:22:16', '2026-04-23 13:21:46'),
(4, 33, 1, 1, '09267630945/jaylord laspuna', 900.00, 'uploads/payments/payment_BK2026042314464768_1776955638.jpg', 'Verified', '', 8, '2026-04-23 14:49:33', '2026-04-23 14:47:18'),
(5, 34, 1, 1, '09267630945', 900.00, 'uploads/payments/payment_BK2026042314480248_1776955709.png', 'Verified', '', 8, '2026-04-23 14:49:38', '2026-04-23 14:48:29');

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
  `venue_photo_path` varchar(500) DEFAULT NULL,
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

INSERT INTO `movies` (`id`, `title`, `director`, `genre`, `duration`, `rating`, `description`, `poster_url`, `trailer_url`, `venue_name`, `venue_location`, `google_maps_link`, `venue_photo_path`, `standard_price`, `premium_price`, `sweet_spot_price`, `is_active`, `added_by`, `updated_by`, `created_at`, `last_updated`) VALUES
(1, 'Sinners', '', 'Supernatural horror', '2hours', 'PG', 'Sinners (2025) is a supernatural horror-period film directed by Ryan Coogler, set in the 1930s Mississippi Delta. The story follows twin brothers, the Smokestack Twins, who return to their hometown seeking a fresh start after working for the Chicago Mafia. They buy a sawmill and a juke joint, but soon face supernatural troubles that challenge their quest for redemption amidst themes of racism and evil.', 'https://i.pinimg.com/originals/93/b2/ec/93b2ec2431ea0ae34a2dbafc81a1d72e.jpg', 'https://www.youtube.com/watch?v=bKGxHflevuk', 'SM Cinema', 'Purok 13 Cadulawan minglanilla Cebu', 'https://www.google.com/maps/place//@10.2699325,123.7746523,20.83z/data=!4m9!1m8!3m7!1s0x33a977e4598c638d:0xd2016057b1f9cd28!2sMinglanilla,+Cebu!3b1!8m2!3d10.2454293!4d123.7959894!16zL20vMDZoNGhs?entry=ttu&g_ep=EgoyMDI2MDIxOC4wIKXMDSoASAFQAw%3D%3D', NULL, 350.00, 800.00, 5500.00, 1, 2, 2, '2026-03-06 15:03:50', '2026-04-16 13:40:18'),
(2, 'The Notebook', 'Denise', 'Drama/Romance', '123 minutes', 'PG', 'The Notebook is a romantic drama that tells the enduring love story of a young couple from different social backgrounds, recounted decades later by an elderly man from a cherished notebook.', 'https://www.themoviedb.org/t/p/original/wbvboxr6xmdSbMEBKzXVWgAwJ1Q.jpg', 'https://www.youtube.com/watch?v=BjJcYdEOI0k', 'SM Cinema', 'Purok 13 Cadulawan minglanilla Cebu', 'https://www.google.com/maps/place//@10.2699325,123.7746523,20.83z/data=!4m9!1m8!3m7!1s0x33a977e4598c638d:0xd2016057b1f9cd28!2sMinglanilla,+Cebu!3b1!8m2!3d10.2454293!4d123.7959894!16zL20vMDZoNGhs?entry=ttu&amp;amp;g_ep=EgoyMDI2MDIxOC4wIKXMDSoASAFQAw%3D%3D', NULL, 350.00, 500.00, 550.00, 1, 2, 2, '2026-03-06 15:14:24', '2026-04-18 11:19:27'),
(6, 'jay1', '', 'testt', 'testt', 'G', 'test', '', '', '', '', 'https://www.google.com/maps/@10.2701892,123.7749737,3a,75y,61.31h,78.1t/data=!3m7!1e1!3m5!1ss76j3A-wBBsmXsq_bpiIcA!2e0!6shttps:%2F%2Fstreetviewpixels-pa.googleapis.com%2Fv1%2Fthumbnail%3Fcb_client%3Dmaps_sv.tactile%26w%3D900%26h%3D600%26pitch%3D11.90046187165521%26panoid%3Ds76j3A-wBBsmXsq_bpiIcA%26yaw%3D61.30689665955039!7i16384!8i8192?entry=ttu&g_ep=EgoyMDI2MDQxMy4wIKXMDSoASAFQAw%3D%3D', NULL, 350.00, 450.00, 550.00, 0, 2, 2, '2026-03-07 04:31:42', '2026-04-21 06:27:33'),
(7, 'The Lord of the Rings: The Fellowship of the Ring (2001)', 'Peter Jackson', 'Epic fantasy film', '2 hours, 58 minutes', 'PG', 'The Lord of the Rings: The Fellowship of the Ring (2001) is the first film in Peter Jackson’s epic fantasy trilogy, following Frodo Baggins and his companions on a perilous quest to destroy the One Ring and save Middle-earth.', 'https://i.pinimg.com/originals/b2/07/ab/b207abc102679d8aaa52f30386fd7582.jpg', 'https://www.youtube.com/watch?v=V75dMMIW2B4', 'St. Cecilia College', 'Ward ||, Minglanilla', '', NULL, 350.00, 450.00, 550.00, 0, 8, NULL, '2026-04-21 06:35:32', '2026-04-21 06:38:02'),
(8, 'The Lord of the Rings: The Fellowship of the Ring (2001)', 'Peter Jackson', 'Epic fantasy film', '2 hours, 58 minutes', 'PG', 'The Lord of the Rings: The Fellowship of the Ring (2001) is the first film in Peter Jackson’s epic fantasy trilogy, following Frodo Baggins and his companions on a perilous quest to destroy the One Ring and save Middle-earth.', 'https://i.pinimg.com/originals/b2/07/ab/b207abc102679d8aaa52f30386fd7582.jpg', 'https://www.youtube.com/watch?v=V75dMMIW2B4', 'St. Cecilia College', 'Ward || Minglanilla Cebu City', 'https://www.google.com/maps/place/St.+Cecilia\'s+College+-+Cebu,+Inc./@10.2447327,123.7943992,3a,75y/data=!3m8!1e2!3m6!1sCIHM0ogKEICAgIDBxoOXAg!2e10!3e12!6shttps:%2F%2Flh3.googleusercontent.com%2Fgps-cs-s%2FAPNQkAEAmML6SGBd8CkHl_t_otjagCRH6H184b8r1zb9jdRhy8WmUORDM2i-x3kipkfMl-fEyhds-Lx-y49YcVJGWsaHz-P63cL-exmHGqIM6QsRafqDXb0v0pUD68Z5Q4s1IueHaZ2P%3Dw86-h114-k-no!7i3024!8i4032!4m7!3m6!1s0x33a977e250bd286d:0x377f6ed9ed966fe7!8m2!3d10.2446906!4d123.7944106!10e5!16s%2Fg%2F1tfksvw4?entry=ttu&g_ep=EgoyM', NULL, 400.00, 500.00, 700.00, 1, 8, NULL, '2026-04-21 06:36:50', NULL),
(9, 'Avengers: Endgame', 'Anthony Russo, Joe Russo', 'Superhero film that blends science fiction, action-adventure, drama, and fantasy elements.', '3hours 1minute', 'PG', 'Avengers: Endgame is a 2019 superhero film that concludes the Infinity Saga of the Marvel Cinematic Universe, featuring the Avengers’ final battle against Thanos.', 'https://tse1.mm.bing.net/th/id/OIP.DVYDQX85lQdcPGsSxAd-rgHaKy?rs=1&amp;amp;pid=ImgDetMain&amp;amp;o=7&amp;amp;rm=3', 'https://www.youtube.com/watch?v=TcMBFSGVi1c', 'SM Cinema', '3F, North Reclamation Area, Cebu City', 'https://www.google.com/maps/@10.3115586,123.9210049,3a,75y,33.08h,121.44t/data=!3m7!1e1!3m5!1s5TZxZ7XNorPgSADXduLTBw!2e0!6shttps:%2F%2Fstreetviewpixels-pa.googleapis.com%2Fv1%2Fthumbnail%3Fcb_client%3Dmaps_sv.tactile%26w%3D900%26h%3D600%26pitch%3D-31.435296103195043%26panoid%3D5TZxZ7XNorPgSADXduLTBw%26yaw%3D33.083140167466894!7i16384!8i8192?entry=ttu&g_ep=EgoyMDI2MDQxNS4wIKXMDSoASAFQAw%3D%3D', 'uploads/venue/venue_1776933661_8133.jpg', 1000.00, 1500.00, 2000.00, 1, 8, 8, '2026-04-21 06:50:05', '2026-04-23 08:41:01'),
(10, 'Avengers: Endgame', 'Anthony Russo, Joe Russo', 'Superhero film that blends science fiction, action-adventure, drama, and fantasy elements.', '3hours 1minute', 'PG', 'Avengers: Endgame is a 2019 superhero film that concludes the Infinity Saga of the Marvel Cinematic Universe, featuring the Avengers’ final battle against Thanos.', 'https://tse1.mm.bing.net/th/id/OIP.DVYDQX85lQdcPGsSxAd-rgHaKy?rs=1&amp;pid=ImgDetMain&amp;o=7&amp;rm=3', 'https://www.youtube.com/watch?v=TcMBFSGVi1c', 'SM Cinema', '3F, North Reclamation Area, Cebu City', 'https://www.google.com/maps/@10.3115586,123.9210049,3a,75y,33.08h,121.44t/data=!3m7!1e1!3m5!1s5TZxZ7XNorPgSADXduLTBw!2e0!6shttps:%2F%2Fstreetviewpixels-pa.googleapis.com%2Fv1%2Fthumbnail%3Fcb_client%3Dmaps_sv.tactile%26w%3D900%26h%3D600%26pitch%3D-31.435296103195043%26panoid%3D5TZxZ7XNorPgSADXduLTBw%26yaw%3D33.083140167466894!7i16384!8i8192?entry=ttu&g_ep=EgoyMDI2MDQxNS4wIKXMDSoASAFQAw%3D%3D', NULL, 1000.00, 1500.00, 2000.00, 0, 8, NULL, '2026-04-21 06:50:11', '2026-04-21 06:50:44'),
(11, 'test', 'test', 'test', 'test', 'PG', 'test', 'http://localhost/phpmyadmin/index.php?route=/sql&amp;pos=0&amp;db=movie_booking&amp;table=admin_activity_log', 'http://youtube.com', 'testtest', 'test', 'test', NULL, 350.00, 450.00, 550.00, 0, 8, NULL, '2026-04-23 08:18:39', '2026-04-23 08:18:48'),
(12, 'test', 'test', 'tstet', 'test', 'G', 'testt', '', '', '', '', '', NULL, 3505.00, 450.00, 5505.00, 0, 8, NULL, '2026-04-23 08:20:46', '2026-04-23 08:20:54');

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
(1, 1, 'Sinners', '2026-03-31', '13:00:00', 50, 26, 1, '2026-03-06 15:04:42'),
(2, 2, 'The Notebook', '2026-03-12', '17:00:00', 40, 40, 1, '2026-03-06 15:36:32'),
(5, 6, 'testt', '2026-03-14', '18:01:00', 40, 31, 1, '2026-03-13 15:07:34'),
(6, 1, 'Sinners', '2026-04-24', '10:00:00', 40, 18, 1, '2026-04-01 05:07:51'),
(7, 9, 'Avengers: Endgame', '2026-04-25', '18:01:00', 40, 35, 1, '2026-04-24 07:40:16');

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
(9, 1, 'Sinners', '2026-03-31', '13:00:00', 'A09', 'Premium', 450.00, 0, 28),
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
(20, 1, 'Sinners', '2026-03-31', '13:00:00', 'B10', 'Sweet Spot', 550.00, 0, 28),
(21, 1, 'Sinners', '2026-03-31', '13:00:00', 'C01', 'Standard', 350.00, 0, 17),
(22, 1, 'Sinners', '2026-03-31', '13:00:00', 'C02', 'Standard', 350.00, 0, 17),
(23, 1, 'Sinners', '2026-03-31', '13:00:00', 'C03', 'Standard', 350.00, 0, 17),
(24, 1, 'Sinners', '2026-03-31', '13:00:00', 'C04', 'Standard', 350.00, 1, NULL),
(25, 1, 'Sinners', '2026-03-31', '13:00:00', 'C05', 'Standard', 350.00, 1, NULL),
(26, 1, 'Sinners', '2026-03-31', '13:00:00', 'C06', 'Standard', 350.00, 1, NULL),
(27, 1, 'Sinners', '2026-03-31', '13:00:00', 'C07', 'Standard', 350.00, 1, NULL),
(28, 1, 'Sinners', '2026-03-31', '13:00:00', 'C08', 'Standard', 350.00, 1, NULL),
(29, 1, 'Sinners', '2026-03-31', '13:00:00', 'C09', 'Standard', 350.00, 0, 27),
(30, 1, 'Sinners', '2026-03-31', '13:00:00', 'C10', 'Standard', 350.00, 0, 27),
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
(181, 1, 'Sinners', '2026-03-31', '13:00:00', 'E01', 'Standard', 350.00, 0, 20),
(182, 1, 'Sinners', '2026-03-31', '13:00:00', 'E02', 'Standard', 350.00, 0, 20),
(183, 1, 'Sinners', '2026-03-31', '13:00:00', 'E03', 'Standard', 350.00, 0, 20),
(184, 1, 'Sinners', '2026-03-31', '13:00:00', 'E04', 'Standard', 350.00, 0, 20),
(185, 1, 'Sinners', '2026-03-31', '13:00:00', 'E05', 'Standard', 350.00, 0, 20),
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
(240, 5, 'testt', '2026-03-14', '18:01:00', 'D10', 'Sweet Spot', 550.00, 0, 22),
(241, 6, 'Sinners', '2026-04-11', '10:00:00', 'A01', 'Premium', 450.00, 1, NULL),
(242, 6, 'Sinners', '2026-04-11', '10:00:00', 'A02', 'Premium', 450.00, 1, NULL),
(243, 6, 'Sinners', '2026-04-11', '10:00:00', 'A03', 'Premium', 450.00, 0, 33),
(244, 6, 'Sinners', '2026-04-11', '10:00:00', 'A04', 'Premium', 450.00, 0, 33),
(245, 6, 'Sinners', '2026-04-11', '10:00:00', 'A05', 'Premium', 450.00, 0, 30),
(246, 6, 'Sinners', '2026-04-11', '10:00:00', 'A06', 'Premium', 450.00, 0, 30),
(247, 6, 'Sinners', '2026-04-11', '10:00:00', 'A07', 'Premium', 450.00, 0, 34),
(248, 6, 'Sinners', '2026-04-11', '10:00:00', 'A08', 'Premium', 450.00, 0, 34),
(249, 6, 'Sinners', '2026-04-11', '10:00:00', 'A09', 'Premium', 450.00, 0, 36),
(250, 6, 'Sinners', '2026-04-11', '10:00:00', 'A10', 'Premium', 450.00, 0, 36),
(251, 6, 'Sinners', '2026-04-11', '10:00:00', 'B01', 'Standard', 350.00, 1, NULL),
(252, 6, 'Sinners', '2026-04-11', '10:00:00', 'B02', 'Standard', 350.00, 1, NULL),
(253, 6, 'Sinners', '2026-04-11', '10:00:00', 'B03', 'Standard', 350.00, 0, 29),
(254, 6, 'Sinners', '2026-04-11', '10:00:00', 'B04', 'Standard', 350.00, 0, 29),
(255, 6, 'Sinners', '2026-04-11', '10:00:00', 'B05', 'Standard', 350.00, 0, 32),
(256, 6, 'Sinners', '2026-04-11', '10:00:00', 'B06', 'Standard', 350.00, 0, 32),
(257, 6, 'Sinners', '2026-04-11', '10:00:00', 'B07', 'Standard', 350.00, 0, 32),
(258, 6, 'Sinners', '2026-04-11', '10:00:00', 'B08', 'Standard', 350.00, 0, 32),
(259, 6, 'Sinners', '2026-04-11', '10:00:00', 'B09', 'Standard', 350.00, 1, NULL),
(260, 6, 'Sinners', '2026-04-11', '10:00:00', 'B10', 'Standard', 350.00, 1, NULL),
(261, 6, 'Sinners', '2026-04-11', '10:00:00', 'C01', 'Standard', 350.00, 1, NULL),
(262, 6, 'Sinners', '2026-04-11', '10:00:00', 'C02', 'Standard', 350.00, 1, NULL),
(263, 6, 'Sinners', '2026-04-11', '10:00:00', 'C03', 'Standard', 350.00, 1, NULL),
(264, 6, 'Sinners', '2026-04-11', '10:00:00', 'C04', 'Standard', 350.00, 1, NULL),
(265, 6, 'Sinners', '2026-04-11', '10:00:00', 'C05', 'Standard', 350.00, 0, 32),
(266, 6, 'Sinners', '2026-04-11', '10:00:00', 'C06', 'Standard', 350.00, 0, 32),
(267, 6, 'Sinners', '2026-04-11', '10:00:00', 'C07', 'Standard', 350.00, 0, 32),
(268, 6, 'Sinners', '2026-04-11', '10:00:00', 'C08', 'Standard', 350.00, 0, 32),
(269, 6, 'Sinners', '2026-04-11', '10:00:00', 'C09', 'Standard', 350.00, 1, NULL),
(270, 6, 'Sinners', '2026-04-11', '10:00:00', 'C10', 'Standard', 350.00, 1, NULL),
(271, 6, 'Sinners', '2026-04-11', '10:00:00', 'D01', 'Sweet Spot', 5500.00, 1, NULL),
(272, 6, 'Sinners', '2026-04-11', '10:00:00', 'D02', 'Sweet Spot', 5500.00, 1, NULL),
(273, 6, 'Sinners', '2026-04-11', '10:00:00', 'D03', 'Sweet Spot', 5500.00, 1, NULL),
(274, 6, 'Sinners', '2026-04-11', '10:00:00', 'D04', 'Sweet Spot', 5500.00, 1, NULL),
(275, 6, 'Sinners', '2026-04-11', '10:00:00', 'D05', 'Sweet Spot', 5500.00, 1, NULL),
(276, 6, 'Sinners', '2026-04-11', '10:00:00', 'D06', 'Sweet Spot', 5500.00, 1, NULL),
(277, 6, 'Sinners', '2026-04-11', '10:00:00', 'D07', 'Sweet Spot', 5500.00, 0, 38),
(278, 6, 'Sinners', '2026-04-11', '10:00:00', 'D08', 'Sweet Spot', 5500.00, 0, 38),
(279, 6, 'Sinners', '2026-04-11', '10:00:00', 'D09', 'Sweet Spot', 5500.00, 0, 37),
(280, 6, 'Sinners', '2026-04-11', '10:00:00', 'D10', 'Sweet Spot', 5500.00, 0, 37),
(281, 7, 'Avengers: Endgame', '2026-04-25', '18:01:00', 'A01', 'Premium', 1500.00, 0, 39),
(282, 7, 'Avengers: Endgame', '2026-04-25', '18:01:00', 'A02', 'Premium', 1500.00, 0, 39),
(283, 7, 'Avengers: Endgame', '2026-04-25', '18:01:00', 'A03', 'Premium', 1500.00, 1, NULL),
(284, 7, 'Avengers: Endgame', '2026-04-25', '18:01:00', 'A04', 'Premium', 1500.00, 1, NULL),
(285, 7, 'Avengers: Endgame', '2026-04-25', '18:01:00', 'A05', 'Premium', 1500.00, 1, NULL),
(286, 7, 'Avengers: Endgame', '2026-04-25', '18:01:00', 'A06', 'Premium', 1500.00, 0, 40),
(287, 7, 'Avengers: Endgame', '2026-04-25', '18:01:00', 'A07', 'Premium', 1500.00, 0, 40),
(288, 7, 'Avengers: Endgame', '2026-04-25', '18:01:00', 'A08', 'Premium', 1500.00, 0, 40),
(289, 7, 'Avengers: Endgame', '2026-04-25', '18:01:00', 'A09', 'Premium', 1500.00, 1, NULL),
(290, 7, 'Avengers: Endgame', '2026-04-25', '18:01:00', 'A10', 'Premium', 1500.00, 1, NULL),
(291, 7, 'Avengers: Endgame', '2026-04-25', '18:01:00', 'B01', 'Standard', 1000.00, 1, NULL),
(292, 7, 'Avengers: Endgame', '2026-04-25', '18:01:00', 'B02', 'Standard', 1000.00, 1, NULL),
(293, 7, 'Avengers: Endgame', '2026-04-25', '18:01:00', 'B03', 'Standard', 1000.00, 1, NULL),
(294, 7, 'Avengers: Endgame', '2026-04-25', '18:01:00', 'B04', 'Standard', 1000.00, 1, NULL),
(295, 7, 'Avengers: Endgame', '2026-04-25', '18:01:00', 'B05', 'Standard', 1000.00, 1, NULL),
(296, 7, 'Avengers: Endgame', '2026-04-25', '18:01:00', 'B06', 'Standard', 1000.00, 1, NULL),
(297, 7, 'Avengers: Endgame', '2026-04-25', '18:01:00', 'B07', 'Standard', 1000.00, 1, NULL),
(298, 7, 'Avengers: Endgame', '2026-04-25', '18:01:00', 'B08', 'Standard', 1000.00, 1, NULL),
(299, 7, 'Avengers: Endgame', '2026-04-25', '18:01:00', 'B09', 'Standard', 1000.00, 1, NULL),
(300, 7, 'Avengers: Endgame', '2026-04-25', '18:01:00', 'B10', 'Standard', 1000.00, 1, NULL),
(301, 7, 'Avengers: Endgame', '2026-04-25', '18:01:00', 'C01', 'Standard', 1000.00, 1, NULL),
(302, 7, 'Avengers: Endgame', '2026-04-25', '18:01:00', 'C02', 'Standard', 1000.00, 1, NULL),
(303, 7, 'Avengers: Endgame', '2026-04-25', '18:01:00', 'C03', 'Standard', 1000.00, 1, NULL),
(304, 7, 'Avengers: Endgame', '2026-04-25', '18:01:00', 'C04', 'Standard', 1000.00, 1, NULL),
(305, 7, 'Avengers: Endgame', '2026-04-25', '18:01:00', 'C05', 'Standard', 1000.00, 1, NULL),
(306, 7, 'Avengers: Endgame', '2026-04-25', '18:01:00', 'C06', 'Standard', 1000.00, 1, NULL),
(307, 7, 'Avengers: Endgame', '2026-04-25', '18:01:00', 'C07', 'Standard', 1000.00, 1, NULL),
(308, 7, 'Avengers: Endgame', '2026-04-25', '18:01:00', 'C08', 'Standard', 1000.00, 1, NULL),
(309, 7, 'Avengers: Endgame', '2026-04-25', '18:01:00', 'C09', 'Standard', 1000.00, 1, NULL),
(310, 7, 'Avengers: Endgame', '2026-04-25', '18:01:00', 'C10', 'Standard', 1000.00, 1, NULL),
(311, 7, 'Avengers: Endgame', '2026-04-25', '18:01:00', 'D01', 'Sweet Spot', 2000.00, 1, NULL),
(312, 7, 'Avengers: Endgame', '2026-04-25', '18:01:00', 'D02', 'Sweet Spot', 2000.00, 1, NULL),
(313, 7, 'Avengers: Endgame', '2026-04-25', '18:01:00', 'D03', 'Sweet Spot', 2000.00, 1, NULL),
(314, 7, 'Avengers: Endgame', '2026-04-25', '18:01:00', 'D04', 'Sweet Spot', 2000.00, 1, NULL),
(315, 7, 'Avengers: Endgame', '2026-04-25', '18:01:00', 'D05', 'Sweet Spot', 2000.00, 1, NULL),
(316, 7, 'Avengers: Endgame', '2026-04-25', '18:01:00', 'D06', 'Sweet Spot', 2000.00, 1, NULL),
(317, 7, 'Avengers: Endgame', '2026-04-25', '18:01:00', 'D07', 'Sweet Spot', 2000.00, 1, NULL),
(318, 7, 'Avengers: Endgame', '2026-04-25', '18:01:00', 'D08', 'Sweet Spot', 2000.00, 1, NULL),
(319, 7, 'Avengers: Endgame', '2026-04-25', '18:01:00', 'D09', 'Sweet Spot', 2000.00, 1, NULL),
(320, 7, 'Avengers: Endgame', '2026-04-25', '18:01:00', 'D10', 'Sweet Spot', 2000.00, 1, NULL);

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
(1, 9, 'CHECK_IN', 30, 'Checked in customer for booking ID: 30', '2026-04-23 10:21:22'),
(2, 9, 'CHECK_IN', 32, 'Checked in customer for movie: Sinners at 10:00:00', '2026-04-23 16:02:25'),
(3, 9, 'CHECK_IN', 33, 'Checked in customer for movie: Sinners at 10:00:00', '2026-04-23 16:02:45'),
(4, 9, 'CHECK_IN', 34, 'Checked in customer for movie: Sinners at 10:00:00', '2026-04-23 16:03:02');

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
  `attendance_status` enum('Pending','Present','Completed') DEFAULT 'Pending',
  `verified_at` timestamp NULL DEFAULT NULL,
  `verified_by` int(11) DEFAULT NULL,
  `is_visible` tinyint(1) DEFAULT 1,
  `booking_reference` varchar(20) DEFAULT NULL,
  `qr_code` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_booking`
--

INSERT INTO `tbl_booking` (`b_id`, `u_id`, `movie_name`, `show_date`, `showtime`, `booking_fee`, `status`, `booking_date`, `payment_status`, `attendance_status`, `verified_at`, `verified_by`, `is_visible`, `booking_reference`, `qr_code`) VALUES
(12, 1, 'Sinners', '2026-03-31', '13:00:00', 1350.00, 'Cancelled', '2026-03-08 12:24:10', 'Refunded', 'Pending', NULL, NULL, 0, 'BK2026030812241074', NULL),
(16, 1, 'Sinners', '2026-03-31', '13:00:00', 1050.00, 'Cancelled', '2026-03-10 14:53:41', 'Refunded', 'Pending', NULL, NULL, 0, 'BK2026031014534152', NULL),
(17, 1, 'Sinners', '2026-03-31', '13:00:00', 1050.00, 'Cancelled', '2026-03-13 13:34:21', 'Refunded', 'Pending', NULL, NULL, 0, 'BK2026031313342189', NULL),
(18, 1, 'Sinners', '2026-03-31', '13:00:00', 1400.00, 'Cancelled', '2026-03-13 14:05:12', 'Refunded', 'Pending', NULL, NULL, 0, 'BK2026031314051248', NULL),
(19, 1, 'Sinners', '2026-03-31', '13:00:00', 700.00, 'Cancelled', '2026-03-13 14:23:15', 'Refunded', 'Pending', NULL, NULL, 0, 'BK2026031314231504', NULL),
(20, 1, 'Sinners', '2026-03-31', '13:00:00', 1750.00, 'Done', '2026-03-13 14:38:54', 'Paid', 'Pending', NULL, NULL, 1, 'BK2026031314385476', NULL),
(21, 1, 'testt', '2026-03-14', '18:01:00', 900.00, 'Cancelled', '2026-03-13 15:08:01', 'Refunded', 'Pending', NULL, NULL, 0, 'BK2026031315080111', NULL),
(22, 1, 'testt', '2026-03-14', '18:01:00', 1650.00, 'Done', '2026-03-14 13:12:51', 'Pending', 'Pending', NULL, NULL, 0, 'BK2026031413125188', NULL),
(23, 1, 'testt', '2026-03-14', '18:01:00', 2100.00, 'Done', '2026-03-14 13:14:11', 'Pending', 'Pending', NULL, NULL, 0, 'BK2026031413141123', NULL),
(24, 1, 'Sinners', '2026-03-31', '13:00:00', 1400.00, 'Cancelled', '2026-03-14 13:35:55', 'Refunded', 'Pending', NULL, NULL, 0, 'BK2026031413355584', NULL),
(25, 1, 'Sinners', '2026-03-31', '13:00:00', 900.00, 'Cancelled', '2026-03-14 14:02:39', 'Refunded', 'Pending', NULL, NULL, 0, 'BK2026031414023992', NULL),
(26, 1, 'Sinners', '2026-03-31', '13:00:00', 900.00, 'Done', '2026-03-14 14:05:02', 'Paid', 'Pending', NULL, NULL, 1, 'BK2026031414050246', NULL),
(27, 1, 'Sinners', '2026-03-31', '13:00:00', 700.00, 'Done', '2026-03-14 14:49:37', 'Paid', 'Pending', NULL, NULL, 1, 'BK2026031414493770', NULL),
(28, 1, 'Sinners', '2026-03-31', '13:00:00', 1000.00, 'Done', '2026-03-14 16:22:42', 'Paid', 'Pending', NULL, NULL, 1, 'BK2026031416224214', NULL),
(29, 1, 'Sinners', '2026-04-11', '10:00:00', 700.00, 'Done', '2026-04-01 05:09:41', 'Paid', 'Pending', NULL, NULL, 1, 'BK2026040105094144', NULL),
(30, 1, 'Sinners', '2026-04-24', '10:00:00', 900.00, 'Done', '2026-04-18 06:24:29', 'Paid', 'Present', '2026-04-23 10:21:22', 9, 1, 'BK2026041806242941', 'https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl=%7B%22booking_ref%22%3A%22BK2026041806242941%22%2C%22booking_id%22%3A30%2C%22timestamp%22%3A1776954058%7D&choe=UTF-8'),
(31, 1, 'Sinners', '2026-04-24', '10:00:00', 58500.00, 'Cancelled', '2026-04-23 09:29:56', 'Refunded', 'Pending', NULL, NULL, 0, 'BK2026042309295603', NULL),
(32, 1, 'Sinners', '2026-04-24', '10:00:00', 2800.00, 'Done', '2026-04-23 13:20:57', 'Paid', 'Present', '2026-04-23 16:02:25', 9, 1, 'BK2026042313205762', NULL),
(33, 1, 'Sinners', '2026-04-24', '10:00:00', 900.00, 'Done', '2026-04-23 14:46:47', 'Paid', 'Present', '2026-04-23 16:02:45', 9, 1, 'BK2026042314464768', NULL),
(34, 1, 'Sinners', '2026-04-24', '10:00:00', 900.00, 'Done', '2026-04-23 14:48:02', 'Paid', 'Present', '2026-04-23 16:03:02', 9, 1, 'BK2026042314480248', NULL),
(35, 1, 'Sinners', '2026-04-24', '10:00:00', 900.00, 'Cancelled', '2026-04-23 15:08:33', 'Refunded', 'Pending', NULL, NULL, 0, 'BK2026042315083320', NULL),
(36, 1, 'Sinners', '2026-04-24', '10:00:00', 900.00, 'Done', '2026-04-24 07:28:48', 'Pending', 'Pending', NULL, NULL, 0, 'BK2026042407284890', NULL),
(37, 1, 'Sinners', '2026-04-24', '10:00:00', 11000.00, 'Done', '2026-04-24 07:29:55', 'Pending', 'Pending', NULL, NULL, 0, 'BK2026042407295516', NULL),
(38, 1, 'Sinners', '2026-04-24', '10:00:00', 11000.00, 'Done', '2026-04-24 07:37:50', 'Pending', 'Pending', NULL, NULL, 1, 'BK2026042407375027', NULL),
(39, 1, 'Avengers: Endgame', '2026-04-25', '18:01:00', 3000.00, 'Ongoing', '2026-04-24 07:41:30', 'Pending', 'Pending', NULL, NULL, 1, 'BK2026042407413050', NULL),
(40, 1, 'Avengers: Endgame', '2026-04-25', '18:01:00', 4500.00, 'Ongoing', '2026-04-24 09:32:04', 'Pending', 'Pending', NULL, NULL, 1, 'BK2026042417320485', NULL);

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
  `is_visible` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_by_name` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`u_id`, `u_name`, `u_username`, `u_email`, `u_pass`, `u_role`, `u_status`, `is_visible`, `created_by`, `created_by_name`, `created_at`, `last_login`) VALUES
(1, 'jaylord', 'jaylord', 'jaylord@gmail.com', '$2y$10$9Z2H5XmtJWLLYtc5mQaMYu1fUAEnKWMeBuDNl9MyyI3lByZrjh99O', 'Customer', 'Active', 1, NULL, NULL, '2026-03-06 14:55:55', '2026-04-24 09:15:32'),
(3, 'kenz', 'kenz', 'kenz@gmail.com', '$2y$10$svqh5E6JWT1ExKcMENWMIeEIQ3hTyGFi6Sr23J1SF9em8i8FYUVrS', 'Customer', 'Active', 1, NULL, NULL, '2026-03-06 15:16:25', NULL),
(4, 'test', 'test', 'test@gmail.com', '$2y$10$DFVjJg47KnHQncqSGBpKU.0ealBTYUP6w8xLm8c57zZ1secAbNqp.', 'Customer', 'Active', 1, NULL, 'denise', '2026-04-16 13:35:17', NULL),
(7, 'Denise Kethley Cana', 'denise', 'jaylordlaspuna1@gmail.com', '$2y$10$ddfQJoomyC8IwTQZxR6/IuvhByLjHZYSzBkAAvSaPJ/nzuGonTI.W', 'Owner', 'Active', 1, NULL, NULL, '2026-04-18 10:39:32', '2026-04-21 06:26:24'),
(8, 'jurist', 'jurist', 'jurist@gmail.com', '$2y$10$KbXO5FiiV3lpeHoSwYea3.Vn/Hqjwrx/jtLPe0I9wc573cn4puCu2', 'Admin', 'Active', 1, NULL, NULL, '2026-04-18 10:50:08', '2026-04-24 07:42:55'),
(9, 'kelly kenzie Cana torrefiel', 'kelly', 'kelly@gmail.com', '$2y$10$OyvsP6l.Iyt8hhIrThY45u40qBVX6/cBX6aw0ipa0acSp2nbuuVam', 'Staff', 'Active', 1, 8, 'jurist', '2026-04-23 10:11:18', '2026-04-24 09:03:25');

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
-- Indexes for table `staff_activity_log`
--
ALTER TABLE `staff_activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `staff_id` (`staff_id`),
  ADD KEY `booking_id` (`booking_id`);

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
  ADD KEY `u_id` (`u_id`),
  ADD KEY `idx_qr_code` (`qr_code`),
  ADD KEY `idx_attendance_status` (`attendance_status`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `booked_seats`
--
ALTER TABLE `booked_seats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=95;

--
-- AUTO_INCREMENT for table `customer_activity_log`
--
ALTER TABLE `customer_activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `manual_payments`
--
ALTER TABLE `manual_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `movies`
--
ALTER TABLE `movies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `movie_schedules`
--
ALTER TABLE `movie_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=321;

--
-- AUTO_INCREMENT for table `staff_activity_log`
--
ALTER TABLE `staff_activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `suggestions`
--
ALTER TABLE `suggestions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tbl_booking`
--
ALTER TABLE `tbl_booking`
  MODIFY `b_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `u_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

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
