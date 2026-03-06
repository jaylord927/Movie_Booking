-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 02, 2026 at 03:27 AM
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
-- Database: `movie`
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
-- Table structure for table `movies`
--

CREATE TABLE `movies` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
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

INSERT INTO `movies` (`id`, `title`, `genre`, `duration`, `rating`, `description`, `poster_url`, `trailer_url`, `venue_name`, `venue_location`, `google_maps_link`, `standard_price`, `premium_price`, `sweet_spot_price`, `is_active`, `added_by`, `updated_by`, `created_at`, `last_updated`) VALUES
(1, 'Sinner', 'Thriller/Horror', '138 minutes', 'PG-13', 'ongo ongo', 'https://upload.wikimedia.org/wikipedia/en/5/5f/Sinners_%282025_film%29_poster.jpg', 'https://www.youtube.com/watch?v=bKGxHflevuk', 'Gaisano Grand Mall', 'Cadulawan Minglanilla Cebu', 'https://www.google.com/maps/@10.260859,123.7758066,3a,90y,188.02h,89.35t/data=!3m7!1e1!3m5!1sgLtVF1t5VRQCEBjDW6q6qw!2e0!6shttps:%2F%2Fstreetviewpixels-pa.googleapis.com%2Fv1%2Fthumbnail%3Fcb_client%3Dmaps_sv.tactile%26w%3D900%26h%3D600%26pitch%3D0.6548110679390078%26panoid%3DgLtVF1t5VRQCEBjDW6q6qw%26yaw%3D188.01899679618293!7i16384!8i8192?entry=ttu&amp;g_ep=EgoyMDI2MDIyNS4wIKXMDSoASAFQAw%3D%3D', 500.00, 600.00, 10000.00, 1, 1, NULL, '2026-03-02 02:11:13', NULL),
(2, 'Frankenstein', 'Horror/Gothic', '2h 20m', 'R', 'project', 'https://image.tmdb.org/t/p/original/g4JtvGlQO7DByTI6frUobqvSL3R.jpg', 'https://youtu.be/x--N03NO130', 'Gaisano Grand Mall', 'Cadulawan Minglanilla Cebu', 'https://www.google.com/maps/place/16Francesca&#039;s+Studio,+Riala+Tower+3/@10.3333817,123.9078637,3a,75y,70.48h,90t/data=!3m7!1e1!3m5!1sQf8O9w2LhLQ8N3P7JBpsrw!2e0!6shttps:%2F%2Fstreetviewpixels-pa.googleapis.com%2Fv1%2Fthumbnail%3Fcb_client%3Dmaps_sv.tactile%26w%3D900%26h%3D600%26pitch%3D0%26panoid%3DQf8O9w2LhLQ8N3P7JBpsrw%26yaw%3D70.48362854982693!7i16384!8i8192!4m17!1m7!3m6!1s0x33a999ba46f851e1:0x5b057b836416a1a3!2sGoogle+eBloc+4!8m2!3d10.332755!4d123.90953!16s%2Fg%2F11hsbdj4nb!3m8!1s0x33a999', 500.00, 800.00, 1500.00, 1, 1, NULL, '2026-03-02 02:23:31', NULL);

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
(1, 1, 'Sinner', '2026-03-18', '10:00:00', 50, 49, 1, '2026-03-02 02:13:23'),
(2, 2, 'Frankenstein', '2026-03-04', '13:00:00', 50, 50, 1, '2026-03-02 02:24:11');

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
(1, 1, 'Sinner', '2026-03-18', '10:00:00', 'A01', 'Premium', 600.00, 1, NULL),
(2, 1, 'Sinner', '2026-03-18', '10:00:00', 'A02', 'Premium', 600.00, 1, NULL),
(3, 1, 'Sinner', '2026-03-18', '10:00:00', 'A03', 'Premium', 600.00, 1, NULL),
(4, 1, 'Sinner', '2026-03-18', '10:00:00', 'A04', 'Premium', 600.00, 1, NULL),
(5, 1, 'Sinner', '2026-03-18', '10:00:00', 'A05', 'Premium', 600.00, 1, NULL),
(6, 1, 'Sinner', '2026-03-18', '10:00:00', 'A06', 'Premium', 600.00, 1, NULL),
(7, 1, 'Sinner', '2026-03-18', '10:00:00', 'A07', 'Premium', 600.00, 1, NULL),
(8, 1, 'Sinner', '2026-03-18', '10:00:00', 'A08', 'Premium', 600.00, 1, NULL),
(9, 1, 'Sinner', '2026-03-18', '10:00:00', 'A09', 'Premium', 600.00, 1, NULL),
(10, 1, 'Sinner', '2026-03-18', '10:00:00', 'A10', 'Premium', 600.00, 1, NULL),
(11, 1, 'Sinner', '2026-03-18', '10:00:00', 'B01', 'Standard', 500.00, 1, NULL),
(12, 1, 'Sinner', '2026-03-18', '10:00:00', 'B02', 'Standard', 500.00, 1, NULL),
(13, 1, 'Sinner', '2026-03-18', '10:00:00', 'B03', 'Standard', 500.00, 1, NULL),
(14, 1, 'Sinner', '2026-03-18', '10:00:00', 'B04', 'Standard', 500.00, 1, NULL),
(15, 1, 'Sinner', '2026-03-18', '10:00:00', 'B05', 'Standard', 500.00, 1, NULL),
(16, 1, 'Sinner', '2026-03-18', '10:00:00', 'B06', 'Standard', 500.00, 1, NULL),
(17, 1, 'Sinner', '2026-03-18', '10:00:00', 'B07', 'Standard', 500.00, 1, NULL),
(18, 1, 'Sinner', '2026-03-18', '10:00:00', 'B08', 'Standard', 500.00, 1, NULL),
(19, 1, 'Sinner', '2026-03-18', '10:00:00', 'B09', 'Standard', 500.00, 1, NULL),
(20, 1, 'Sinner', '2026-03-18', '10:00:00', 'B10', 'Standard', 500.00, 1, NULL),
(21, 1, 'Sinner', '2026-03-18', '10:00:00', 'C01', 'Standard', 500.00, 1, NULL),
(22, 1, 'Sinner', '2026-03-18', '10:00:00', 'C02', 'Standard', 500.00, 1, NULL),
(23, 1, 'Sinner', '2026-03-18', '10:00:00', 'C03', 'Standard', 500.00, 1, NULL),
(24, 1, 'Sinner', '2026-03-18', '10:00:00', 'C04', 'Standard', 500.00, 1, NULL),
(25, 1, 'Sinner', '2026-03-18', '10:00:00', 'C05', 'Standard', 500.00, 1, NULL),
(26, 1, 'Sinner', '2026-03-18', '10:00:00', 'C06', 'Standard', 500.00, 1, NULL),
(27, 1, 'Sinner', '2026-03-18', '10:00:00', 'C07', 'Standard', 500.00, 1, NULL),
(28, 1, 'Sinner', '2026-03-18', '10:00:00', 'C08', 'Standard', 500.00, 1, NULL),
(29, 1, 'Sinner', '2026-03-18', '10:00:00', 'C09', 'Standard', 500.00, 1, NULL),
(30, 1, 'Sinner', '2026-03-18', '10:00:00', 'C10', 'Standard', 500.00, 1, NULL),
(31, 1, 'Sinner', '2026-03-18', '10:00:00', 'D01', 'Standard', 500.00, 1, NULL),
(32, 1, 'Sinner', '2026-03-18', '10:00:00', 'D02', 'Standard', 500.00, 1, NULL),
(33, 1, 'Sinner', '2026-03-18', '10:00:00', 'D03', 'Standard', 500.00, 1, NULL),
(34, 1, 'Sinner', '2026-03-18', '10:00:00', 'D04', 'Standard', 500.00, 1, NULL),
(35, 1, 'Sinner', '2026-03-18', '10:00:00', 'D05', 'Standard', 500.00, 1, NULL),
(36, 1, 'Sinner', '2026-03-18', '10:00:00', 'D06', 'Standard', 500.00, 1, NULL),
(37, 1, 'Sinner', '2026-03-18', '10:00:00', 'D07', 'Standard', 500.00, 1, NULL),
(38, 1, 'Sinner', '2026-03-18', '10:00:00', 'D08', 'Standard', 500.00, 1, NULL),
(39, 1, 'Sinner', '2026-03-18', '10:00:00', 'D09', 'Standard', 500.00, 1, NULL),
(40, 1, 'Sinner', '2026-03-18', '10:00:00', 'D10', 'Standard', 500.00, 1, NULL),
(41, 1, 'Sinner', '2026-03-18', '10:00:00', 'E01', 'Sweet Spot', 10000.00, 0, 1),
(42, 1, 'Sinner', '2026-03-18', '10:00:00', 'E02', 'Sweet Spot', 10000.00, 1, NULL),
(43, 1, 'Sinner', '2026-03-18', '10:00:00', 'E03', 'Sweet Spot', 10000.00, 1, NULL),
(44, 1, 'Sinner', '2026-03-18', '10:00:00', 'E04', 'Sweet Spot', 10000.00, 1, NULL),
(45, 1, 'Sinner', '2026-03-18', '10:00:00', 'E05', 'Sweet Spot', 10000.00, 1, NULL),
(46, 1, 'Sinner', '2026-03-18', '10:00:00', 'E06', 'Sweet Spot', 10000.00, 1, NULL),
(47, 1, 'Sinner', '2026-03-18', '10:00:00', 'E07', 'Sweet Spot', 10000.00, 1, NULL),
(48, 1, 'Sinner', '2026-03-18', '10:00:00', 'E08', 'Sweet Spot', 10000.00, 1, NULL),
(49, 1, 'Sinner', '2026-03-18', '10:00:00', 'E09', 'Sweet Spot', 10000.00, 1, NULL),
(50, 1, 'Sinner', '2026-03-18', '10:00:00', 'E10', 'Sweet Spot', 10000.00, 1, NULL),
(51, 2, 'Frankenstein', '2026-03-04', '13:00:00', 'A01', 'Premium', 800.00, 1, NULL),
(52, 2, 'Frankenstein', '2026-03-04', '13:00:00', 'A02', 'Premium', 800.00, 1, NULL),
(53, 2, 'Frankenstein', '2026-03-04', '13:00:00', 'A03', 'Premium', 800.00, 1, NULL),
(54, 2, 'Frankenstein', '2026-03-04', '13:00:00', 'A04', 'Premium', 800.00, 1, NULL),
(55, 2, 'Frankenstein', '2026-03-04', '13:00:00', 'A05', 'Premium', 800.00, 1, NULL),
(56, 2, 'Frankenstein', '2026-03-04', '13:00:00', 'A06', 'Premium', 800.00, 1, NULL),
(57, 2, 'Frankenstein', '2026-03-04', '13:00:00', 'A07', 'Premium', 800.00, 1, NULL),
(58, 2, 'Frankenstein', '2026-03-04', '13:00:00', 'A08', 'Premium', 800.00, 1, NULL),
(59, 2, 'Frankenstein', '2026-03-04', '13:00:00', 'A09', 'Premium', 800.00, 1, NULL),
(60, 2, 'Frankenstein', '2026-03-04', '13:00:00', 'A10', 'Premium', 800.00, 1, NULL),
(61, 2, 'Frankenstein', '2026-03-04', '13:00:00', 'B01', 'Standard', 500.00, 1, NULL),
(62, 2, 'Frankenstein', '2026-03-04', '13:00:00', 'B02', 'Standard', 500.00, 1, NULL),
(63, 2, 'Frankenstein', '2026-03-04', '13:00:00', 'B03', 'Standard', 500.00, 1, NULL),
(64, 2, 'Frankenstein', '2026-03-04', '13:00:00', 'B04', 'Standard', 500.00, 1, NULL),
(65, 2, 'Frankenstein', '2026-03-04', '13:00:00', 'B05', 'Standard', 500.00, 1, NULL),
(66, 2, 'Frankenstein', '2026-03-04', '13:00:00', 'B06', 'Standard', 500.00, 1, NULL),
(67, 2, 'Frankenstein', '2026-03-04', '13:00:00', 'B07', 'Standard', 500.00, 1, NULL),
(68, 2, 'Frankenstein', '2026-03-04', '13:00:00', 'B08', 'Standard', 500.00, 1, NULL),
(69, 2, 'Frankenstein', '2026-03-04', '13:00:00', 'B09', 'Standard', 500.00, 1, NULL),
(70, 2, 'Frankenstein', '2026-03-04', '13:00:00', 'B10', 'Standard', 500.00, 1, NULL),
(71, 2, 'Frankenstein', '2026-03-04', '13:00:00', 'C01', 'Standard', 500.00, 1, NULL),
(72, 2, 'Frankenstein', '2026-03-04', '13:00:00', 'C02', 'Standard', 500.00, 1, NULL),
(73, 2, 'Frankenstein', '2026-03-04', '13:00:00', 'C03', 'Standard', 500.00, 1, NULL),
(74, 2, 'Frankenstein', '2026-03-04', '13:00:00', 'C04', 'Standard', 500.00, 1, NULL),
(75, 2, 'Frankenstein', '2026-03-04', '13:00:00', 'C05', 'Standard', 500.00, 1, NULL),
(76, 2, 'Frankenstein', '2026-03-04', '13:00:00', 'C06', 'Standard', 500.00, 1, NULL),
(77, 2, 'Frankenstein', '2026-03-04', '13:00:00', 'C07', 'Standard', 500.00, 1, NULL),
(78, 2, 'Frankenstein', '2026-03-04', '13:00:00', 'C08', 'Standard', 500.00, 1, NULL),
(79, 2, 'Frankenstein', '2026-03-04', '13:00:00', 'C09', 'Standard', 500.00, 1, NULL),
(80, 2, 'Frankenstein', '2026-03-04', '13:00:00', 'C10', 'Standard', 500.00, 1, NULL),
(81, 2, 'Frankenstein', '2026-03-04', '13:00:00', 'D01', 'Sweet Spot', 1500.00, 1, NULL),
(82, 2, 'Frankenstein', '2026-03-04', '13:00:00', 'D02', 'Sweet Spot', 1500.00, 1, NULL),
(83, 2, 'Frankenstein', '2026-03-04', '13:00:00', 'D03', 'Sweet Spot', 1500.00, 1, NULL),
(84, 2, 'Frankenstein', '2026-03-04', '13:00:00', 'D04', 'Sweet Spot', 1500.00, 1, NULL),
(85, 2, 'Frankenstein', '2026-03-04', '13:00:00', 'D05', 'Sweet Spot', 1500.00, 1, NULL),
(86, 2, 'Frankenstein', '2026-03-04', '13:00:00', 'D06', 'Sweet Spot', 1500.00, 1, NULL),
(87, 2, 'Frankenstein', '2026-03-04', '13:00:00', 'D07', 'Sweet Spot', 1500.00, 1, NULL),
(88, 2, 'Frankenstein', '2026-03-04', '13:00:00', 'D08', 'Sweet Spot', 1500.00, 1, NULL),
(89, 2, 'Frankenstein', '2026-03-04', '13:00:00', 'D09', 'Sweet Spot', 1500.00, 1, NULL),
(90, 2, 'Frankenstein', '2026-03-04', '13:00:00', 'D10', 'Sweet Spot', 1500.00, 1, NULL),
(91, 2, 'Frankenstein', '2026-03-04', '13:00:00', 'E01', 'Standard', 500.00, 1, NULL),
(92, 2, 'Frankenstein', '2026-03-04', '13:00:00', 'E02', 'Standard', 500.00, 1, NULL),
(93, 2, 'Frankenstein', '2026-03-04', '13:00:00', 'E03', 'Standard', 500.00, 1, NULL),
(94, 2, 'Frankenstein', '2026-03-04', '13:00:00', 'E04', 'Standard', 500.00, 1, NULL),
(95, 2, 'Frankenstein', '2026-03-04', '13:00:00', 'E05', 'Standard', 500.00, 1, NULL),
(96, 2, 'Frankenstein', '2026-03-04', '13:00:00', 'E06', 'Standard', 500.00, 1, NULL),
(97, 2, 'Frankenstein', '2026-03-04', '13:00:00', 'E07', 'Standard', 500.00, 1, NULL),
(98, 2, 'Frankenstein', '2026-03-04', '13:00:00', 'E08', 'Standard', 500.00, 1, NULL),
(99, 2, 'Frankenstein', '2026-03-04', '13:00:00', 'E09', 'Standard', 500.00, 1, NULL),
(100, 2, 'Frankenstein', '2026-03-04', '13:00:00', 'E10', 'Standard', 500.00, 1, NULL);

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
(1, NULL, 'jaylord', 'jaylord@gmail.com', 'I hope the payment will implement soon.', 'Pending', NULL, '2026-03-02 02:16:21');

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
  `seat_no` text NOT NULL,
  `booking_fee` decimal(10,2) DEFAULT 0.00,
  `status` enum('Ongoing','Done','Cancelled') DEFAULT 'Ongoing',
  `booking_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `payment_status` enum('Pending','Paid','Refunded') DEFAULT 'Pending',
  `booking_reference` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_booking`
--

INSERT INTO `tbl_booking` (`b_id`, `u_id`, `movie_name`, `show_date`, `showtime`, `seat_no`, `booking_fee`, `status`, `booking_date`, `payment_status`, `booking_reference`) VALUES
(1, 2, 'Sinner', '2026-03-18', '10:00:00', 'E01', 10000.00, 'Ongoing', '2026-03-02 02:17:34', 'Pending', 'BK202603023104');

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`u_id`, `u_name`, `u_username`, `u_email`, `u_pass`, `u_role`, `u_status`, `created_at`) VALUES
(1, 'jaylord', 'jaylord', 'jaylord@gmail.com', '$2y$10$bclDfHX63PTpRYy7aD8Em.oRbB3LOOKbeWdF3JtDYq2k0kCbjbN1u', 'Admin', 'Active', '2026-03-02 02:07:28'),
(2, 'denise', 'denise', 'denise@gmail.com', '$2y$10$DVSCy4dHO7ptX3ArzgvD/OyZS6nW.GibXw0wkzOs.Twsc2Dm4AsI6', 'Customer', 'Active', '2026-03-02 02:14:35');

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
-- Indexes for table `customer_activity_log`
--
ALTER TABLE `customer_activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `movie_id` (`movie_id`),
  ADD KEY `schedule_id` (`schedule_id`),
  ADD KEY `booking_id` (`booking_id`);

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
  ADD UNIQUE KEY `u_email` (`u_email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_activity_log`
--
ALTER TABLE `admin_activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customer_activity_log`
--
ALTER TABLE `customer_activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `movies`
--
ALTER TABLE `movies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `movie_schedules`
--
ALTER TABLE `movie_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `seat_availability`
--
ALTER TABLE `seat_availability`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=101;

--
-- AUTO_INCREMENT for table `suggestions`
--
ALTER TABLE `suggestions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tbl_booking`
--
ALTER TABLE `tbl_booking`
  MODIFY `b_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `u_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_activity_log`
--
ALTER TABLE `admin_activity_log`
  ADD CONSTRAINT `admin_activity_log_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `users` (`u_id`) ON DELETE CASCADE;

--
-- Constraints for table `customer_activity_log`
--
ALTER TABLE `customer_activity_log`
  ADD CONSTRAINT `customer_activity_log_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `users` (`u_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `customer_activity_log_ibfk_2` FOREIGN KEY (`movie_id`) REFERENCES `movies` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `customer_activity_log_ibfk_3` FOREIGN KEY (`schedule_id`) REFERENCES `movie_schedules` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `customer_activity_log_ibfk_4` FOREIGN KEY (`booking_id`) REFERENCES `tbl_booking` (`b_id`) ON DELETE SET NULL;

--
-- Constraints for table `movie_schedules`
--
ALTER TABLE `movie_schedules`
  ADD CONSTRAINT `movie_schedules_ibfk_1` FOREIGN KEY (`movie_id`) REFERENCES `movies` (`id`) ON DELETE CASCADE;

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
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
