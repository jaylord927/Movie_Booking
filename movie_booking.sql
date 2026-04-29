-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 29, 2026 at 06:58 PM
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

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `cancel_expired_pending_bookings` ()   BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_booking_id INT;
    DECLARE v_schedule_id INT;
    DECLARE cur CURSOR FOR 
        SELECT b.id, b.schedule_id 
        FROM bookings b
        WHERE b.payment_status = 'pending' 
        AND b.status = 'ongoing'
        AND TIMESTAMPDIFF(HOUR, b.booked_at, NOW()) >= 3;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    OPEN cur;
    
    read_loop: LOOP
        FETCH cur INTO v_booking_id, v_schedule_id;
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        UPDATE bookings 
        SET status = 'cancelled', payment_status = 'refunded' 
        WHERE id = v_booking_id;
        
        UPDATE seat_availability sa
        JOIN booked_seats bs ON sa.id = bs.seat_availability_id
        SET sa.status = 'available', sa.locked_by = NULL, sa.locked_at = NULL
        WHERE bs.booking_id = v_booking_id;
        
    END LOOP;
    
    CLOSE cur;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `create_schedule_with_seats` (IN `p_movie_id` INT, IN `p_screen_id` INT, IN `p_seat_plan_id` INT, IN `p_show_date` DATE, IN `p_showtime` TIME, IN `p_base_price` DECIMAL(10,2))   BEGIN
    DECLARE v_schedule_id INT;
    DECLARE v_exists INT;
    
    SELECT COUNT(*) INTO v_exists 
    FROM schedules 
    WHERE screen_id = p_screen_id AND show_date = p_show_date AND showtime = p_showtime;
    
    IF v_exists = 0 THEN
        INSERT INTO schedules (movie_id, screen_id, seat_plan_id, show_date, showtime, base_price, is_active)
        VALUES (p_movie_id, p_screen_id, p_seat_plan_id, p_show_date, p_showtime, p_base_price, 1);
        
        SET v_schedule_id = LAST_INSERT_ID();
        
        INSERT INTO seat_availability (
            schedule_id, seat_plan_detail_id, seat_number, seat_type_id, price, status
        )
        SELECT 
            v_schedule_id,
            spd.id,
            spd.seat_number,
            spd.seat_type_id,
            COALESCE(msp.price, spd.custom_price, st.default_price) as price,
            'available'
        FROM seat_plan_details spd
        JOIN seat_types st ON spd.seat_type_id = st.id
        LEFT JOIN movie_screen_prices msp ON p_movie_id = msp.movie_id 
            AND p_screen_id = msp.screen_id 
            AND spd.seat_type_id = msp.seat_type_id
            AND msp.is_active = 1
        WHERE spd.seat_plan_id = p_seat_plan_id AND spd.is_enabled = 1;
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `generate_seats_for_plan` (IN `p_plan_id` INT)   BEGIN
    DECLARE v_row INT DEFAULT 1;
    DECLARE v_col INT;
    DECLARE v_seat_number VARCHAR(10);
    DECLARE v_seat_type_id INT;
    DECLARE v_max_rows INT;
    DECLARE v_max_cols INT;
    DECLARE v_premium_type_id INT;
    DECLARE v_standard_type_id INT;
    DECLARE v_sweet_spot_type_id INT;
    DECLARE v_premium_rows INT DEFAULT 3;
    DECLARE v_sweet_spot_start INT;
    DECLARE v_sweet_spot_end INT;
    
    SELECT id INTO v_standard_type_id FROM seat_types WHERE name = 'Standard' LIMIT 1;
    SELECT id INTO v_premium_type_id FROM seat_types WHERE name = 'Premium' LIMIT 1;
    SELECT id INTO v_sweet_spot_type_id FROM seat_types WHERE name = 'Sweet Spot' LIMIT 1;
    
    SELECT total_rows, total_columns 
    INTO v_max_rows, v_max_cols
    FROM seat_plans WHERE id = p_plan_id;
    
    SET v_sweet_spot_start = FLOOR(v_max_rows * 0.35) + 1;
    SET v_sweet_spot_end = FLOOR(v_max_rows * 0.65);
    
    IF v_sweet_spot_start > v_max_rows THEN
        SET v_sweet_spot_start = v_max_rows;
    END IF;
    IF v_sweet_spot_end > v_max_rows THEN
        SET v_sweet_spot_end = v_max_rows;
    END IF;
    
    DELETE FROM seat_plan_details WHERE seat_plan_id = p_plan_id;
    
    WHILE v_row <= v_max_rows DO
        SET v_col = 1;
        WHILE v_col <= v_max_cols DO
            SET v_seat_number = CONCAT(CHAR(64 + v_row), LPAD(v_col, 2, '0'));
            
            IF v_row <= v_premium_rows THEN
                SET v_seat_type_id = v_premium_type_id;
            ELSEIF v_row >= v_sweet_spot_start AND v_row <= v_sweet_spot_end THEN
                SET v_seat_type_id = v_sweet_spot_type_id;
            ELSE
                SET v_seat_type_id = v_standard_type_id;
            END IF;
            
            INSERT INTO seat_plan_details (
                seat_plan_id, seat_row, seat_column, seat_number, seat_type_id, is_enabled
            ) VALUES (
                p_plan_id, CHAR(64 + v_row), v_col, v_seat_number, v_seat_type_id, 1
            );
            
            SET v_col = v_col + 1;
        END WHILE;
        SET v_row = v_row + 1;
    END WHILE;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `release_expired_seat_locks` ()   BEGIN
    UPDATE seat_availability 
    SET status = 'available', 
        locked_by = NULL, 
        locked_at = NULL
    WHERE status = 'reserved' 
    AND locked_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE);
END$$

--
-- Functions
--
CREATE DEFINER=`root`@`localhost` FUNCTION `generate_booking_reference` () RETURNS VARCHAR(20) CHARSET utf8mb4 COLLATE utf8mb4_general_ci DETERMINISTIC BEGIN
    DECLARE new_ref VARCHAR(20);
    DECLARE counter INT DEFAULT 0;
    
    REPEAT
        SET new_ref = CONCAT(
            'BK', 
            DATE_FORMAT(NOW(), '%y%m%d'),
            LPAD(FLOOR(RAND() * 10000), 4, '0')
        );
        SET counter = counter + 1;
    UNTIL (SELECT COUNT(*) FROM bookings WHERE booking_reference = new_ref) = 0 OR counter > 10
    END REPEAT;
    
    RETURN new_ref;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `admin_activity_log`
--

CREATE TABLE `admin_activity_log` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `details` text DEFAULT NULL,
  `target_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_activity_log`
--

INSERT INTO `admin_activity_log` (`id`, `admin_id`, `action`, `details`, `target_id`, `created_at`) VALUES
(1, 1, 'ADD_STAFF', 'Added new staff: test (test - test@gmail.com)', 3, '2026-04-29 08:49:54'),
(2, 1, 'DELETE_STAFF', 'Deleted staff: test', 3, '2026-04-29 08:55:01'),
(3, 1, 'ADD_ADMIN', 'Added new admin: testt (testt - testt@gmail.com)', 4, '2026-04-29 09:01:57'),
(4, 1, 'ADD_ADMIN', 'Added new admin: dexter3 (dexter3 - dext3r@gmail.com)', 5, '2026-04-29 09:02:15'),
(5, 1, 'DELETE_ADMIN', 'Deleted admin: dexter3', 5, '2026-04-29 09:02:31'),
(6, 1, 'DELETE_ADMIN', 'Deleted admin: testt', 4, '2026-04-29 09:02:35'),
(7, 1, 'ADD_CUSTOMER', 'Added new customer: test (test4 - test4@gmail.com)', 6, '2026-04-29 09:08:45'),
(8, 1, 'ADD_STAFF', 'Added new staff: jurist (jurist - jurist@gmail.com)', 8, '2026-04-29 12:55:14');

-- --------------------------------------------------------

--
-- Table structure for table `aisles`
--

CREATE TABLE `aisles` (
  `id` int(11) NOT NULL,
  `seat_plan_id` int(11) NOT NULL,
  `aisle_type_id` int(11) NOT NULL,
  `position_value` int(11) NOT NULL COMMENT 'Row number or Column number where aisle is placed',
  `position_type` enum('row','column') NOT NULL COMMENT 'Whether aisle is between rows or columns',
  `width` int(11) DEFAULT 1 COMMENT 'Aisle width',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `aisle_types`
--

CREATE TABLE `aisle_types` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `color_code` varchar(7) DEFAULT '#2c3e50',
  `width` int(11) DEFAULT 1 COMMENT 'Aisle width in columns/rows',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `aisle_types`
--

INSERT INTO `aisle_types` (`id`, `name`, `description`, `color_code`, `width`, `is_active`, `created_at`) VALUES
(1, 'Row Aisle', 'Horizontal aisle between rows', '#2c3e50', 1, 1, '2026-04-29 06:23:15'),
(2, 'Column Aisle', 'Vertical aisle between columns', '#2c3e50', 1, 1, '2026-04-29 06:23:15'),
(3, 'Cross Aisle', 'Both row and column aisle', '#2c3e50', 2, 1, '2026-04-29 06:23:15');

-- --------------------------------------------------------

--
-- Table structure for table `booked_seats`
--

CREATE TABLE `booked_seats` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `seat_availability_id` int(11) NOT NULL,
  `seat_number` varchar(10) NOT NULL,
  `seat_type_id` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `booked_seats`
--

INSERT INTO `booked_seats` (`id`, `booking_id`, `seat_availability_id`, `seat_number`, `seat_type_id`, `price`, `created_at`) VALUES
(1, 1, 55, 'A03', 2, 450.00, '2026-04-29 11:00:34'),
(2, 1, 56, 'A04', 2, 450.00, '2026-04-29 11:00:34'),
(3, 1, 59, 'A07', 2, 450.00, '2026-04-29 11:00:34'),
(4, 1, 60, 'A08', 2, 450.00, '2026-04-29 11:00:34'),
(5, 1, 62, 'A10', 2, 450.00, '2026-04-29 11:00:34'),
(6, 1, 73, 'B11', 2, 450.00, '2026-04-29 11:00:34'),
(7, 1, 107, 'E11', 3, 550.00, '2026-04-29 11:00:34'),
(8, 1, 109, 'F01', 3, 550.00, '2026-04-29 11:00:34'),
(9, 1, 110, 'F02', 3, 550.00, '2026-04-29 11:00:34'),
(10, 1, 113, 'F05', 3, 550.00, '2026-04-29 11:00:34'),
(11, 1, 114, 'F06', 3, 550.00, '2026-04-29 11:00:34'),
(12, 1, 115, 'F07', 3, 550.00, '2026-04-29 11:00:34'),
(13, 1, 116, 'F08', 3, 550.00, '2026-04-29 11:00:34'),
(14, 1, 117, 'F09', 3, 550.00, '2026-04-29 11:00:34'),
(15, 1, 118, 'F10', 3, 550.00, '2026-04-29 11:00:34'),
(16, 1, 119, 'F11', 3, 550.00, '2026-04-29 11:00:34'),
(17, 1, 120, 'F12', 3, 550.00, '2026-04-29 11:00:34'),
(18, 1, 45, 'J03', 1, 350.00, '2026-04-29 11:00:34'),
(19, 1, 48, 'J06', 1, 350.00, '2026-04-29 11:00:34'),
(20, 1, 49, 'J07', 1, 350.00, '2026-04-29 11:00:34'),
(21, 1, 54, 'J12', 1, 350.00, '2026-04-29 11:00:34'),
(22, 2, 5, 'C01', 1, 350.00, '2026-04-29 11:42:19'),
(23, 2, 75, 'C03', 2, 450.00, '2026-04-29 11:42:19'),
(24, 2, 76, 'C04', 2, 450.00, '2026-04-29 11:42:19'),
(25, 2, 77, 'C05', 2, 450.00, '2026-04-29 11:42:19'),
(26, 2, 81, 'C09', 2, 450.00, '2026-04-29 11:42:19'),
(27, 2, 82, 'C10', 2, 450.00, '2026-04-29 11:42:19'),
(28, 2, 84, 'C12', 2, 450.00, '2026-04-29 11:42:19'),
(29, 2, 85, 'D01', 3, 550.00, '2026-04-29 11:42:19'),
(30, 2, 87, 'D03', 3, 550.00, '2026-04-29 11:42:19'),
(31, 2, 89, 'D05', 3, 550.00, '2026-04-29 11:42:19'),
(32, 2, 90, 'D06', 3, 550.00, '2026-04-29 11:42:19'),
(33, 2, 94, 'D10', 3, 550.00, '2026-04-29 11:42:19'),
(40, 4, 57, 'A05', 2, 450.00, '2026-04-29 13:26:10'),
(41, 4, 58, 'A06', 2, 450.00, '2026-04-29 13:26:10'),
(42, 4, 68, 'B06', 2, 450.00, '2026-04-29 13:26:10'),
(43, 4, 69, 'B07', 2, 450.00, '2026-04-29 13:26:10'),
(44, 4, 52, 'J10', 1, 350.00, '2026-04-29 13:26:10'),
(45, 4, 53, 'J11', 1, 350.00, '2026-04-29 13:26:10'),
(46, 4, 42, 'I12', 1, 350.00, '2026-04-29 13:26:10'),
(47, 4, 40, 'I10', 1, 350.00, '2026-04-29 13:26:10'),
(48, 4, 39, 'I09', 1, 350.00, '2026-04-29 13:26:10'),
(49, 4, 38, 'I08', 1, 350.00, '2026-04-29 13:26:10'),
(50, 4, 34, 'I04', 1, 350.00, '2026-04-29 13:26:10'),
(51, 4, 27, 'H09', 1, 350.00, '2026-04-29 13:26:10'),
(52, 4, 51, 'J09', 1, 350.00, '2026-04-29 13:26:10'),
(53, 4, 29, 'H11', 1, 350.00, '2026-04-29 13:26:10'),
(54, 4, 17, 'G11', 1, 350.00, '2026-04-29 13:26:10'),
(55, 4, 18, 'G12', 1, 350.00, '2026-04-29 13:26:10'),
(56, 4, 30, 'H12', 1, 350.00, '2026-04-29 13:26:10'),
(57, 4, 41, 'I11', 1, 350.00, '2026-04-29 13:26:10'),
(58, 4, 28, 'H10', 1, 350.00, '2026-04-29 13:26:10'),
(59, 4, 16, 'G10', 1, 350.00, '2026-04-29 13:26:10'),
(60, 5, 65, 'B03', 2, 450.00, '2026-04-29 17:15:57'),
(61, 5, 66, 'B04', 2, 450.00, '2026-04-29 17:15:57'),
(62, 5, 57, 'A05', 2, 450.00, '2026-04-29 17:15:57'),
(63, 5, 58, 'A06', 2, 450.00, '2026-04-29 17:15:57'),
(64, 5, 67, 'B05', 2, 450.00, '2026-04-29 17:15:57');

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `booking_reference` varchar(20) NOT NULL,
  `user_id` int(11) NOT NULL,
  `schedule_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `payment_status` enum('pending','paid','refunded','pending_verification') DEFAULT 'pending',
  `attendance_status` enum('pending','present','completed') DEFAULT 'pending',
  `status` enum('ongoing','done','cancelled') DEFAULT 'ongoing',
  `qr_code` varchar(255) DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `verified_by` int(11) DEFAULT NULL,
  `booked_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_visible` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `booking_reference`, `user_id`, `schedule_id`, `total_amount`, `payment_status`, `attendance_status`, `status`, `qr_code`, `verified_at`, `verified_by`, `booked_at`, `is_visible`) VALUES
(1, 'BK26042920C88B15', 7, 11, 10150.00, 'paid', 'present', 'ongoing', NULL, '2026-04-29 13:44:37', 8, '2026-04-29 11:00:34', 1),
(2, 'BK260429B336EE76', 7, 11, 5800.00, 'paid', 'pending', 'ongoing', NULL, NULL, NULL, '2026-04-29 11:42:19', 1),
(4, 'BK2604292D6E7783', 7, 11, 7400.00, 'refunded', 'pending', 'cancelled', NULL, NULL, NULL, '2026-04-29 13:26:10', 1),
(5, 'BK260430D3AD7334', 7, 11, 2250.00, 'paid', 'pending', 'ongoing', NULL, NULL, NULL, '2026-04-29 17:15:57', 1);

--
-- Triggers `bookings`
--
DELIMITER $$
CREATE TRIGGER `after_booking_cancel` AFTER UPDATE ON `bookings` FOR EACH ROW BEGIN
    IF NEW.status = 'cancelled' AND OLD.status != 'cancelled' THEN
        UPDATE seat_availability sa
        JOIN booked_seats bs ON sa.id = bs.seat_availability_id
        SET sa.status = 'available'
        WHERE bs.booking_id = NEW.id;
    END IF;
END
$$
DELIMITER ;

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
(1, 7, 'LOGIN', 'User logged in: Sir Aries Vincent Dajay', NULL, NULL, NULL, '2026-04-29 09:44:34'),
(2, 7, 'LOGIN', 'User logged in: Sir Aries Vincent Dajay', NULL, NULL, NULL, '2026-04-29 10:06:13'),
(3, 7, 'LOGIN', 'User logged in: Sir Aries Vincent Dajay', NULL, NULL, NULL, '2026-04-29 17:08:32');

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
  `status` enum('pending','verified','rejected') DEFAULT 'pending',
  `admin_notes` text DEFAULT NULL,
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `manual_payments`
--

INSERT INTO `manual_payments` (`id`, `booking_id`, `user_id`, `payment_method_id`, `reference_number`, `amount`, `screenshot_path`, `status`, `admin_notes`, `verified_by`, `verified_at`, `created_at`) VALUES
(1, 1, 7, 1, 'aries/000000000', 10150.00, 'uploads/payments/payment_BK26042920C88B15_1777462143.png', 'verified', '✅ PAYMENT VERIFIED - Booking Confirmed!\n\nBooking Reference: BK26042920C88B15\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n📌 IMPORTANT REMINDERS:\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n• Please arrive at least 30 minutes before showtime\n• Present this QR code at the cinema entrance\n• Staff will scan your QR code for verification\n• After verification, you will receive a PHYSICAL TICKET\n• KEEP your physical ticket for the entire show duration\n• For RE-ENTRY, present your physical ticket or hand stamp\n• No re-entry without physical ticket or hand stamp\n\n🎬 ENJOY THE MOVIE!\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\nMovieTicketBooking Team', 1, '2026-04-29 12:26:43', '2026-04-29 11:29:03');

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
  `is_active` tinyint(1) DEFAULT 1,
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_updated` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `movies`
--

INSERT INTO `movies` (`id`, `title`, `director`, `genre`, `duration`, `rating`, `description`, `poster_url`, `trailer_url`, `is_active`, `added_by`, `updated_by`, `created_at`, `last_updated`) VALUES
(1, 'Inception', 'Christopher Nolan', 'Sci-Fi, Action', '2h 28min', 'PG-13', 'A thief who steals corporate secrets through the use of dream-sharing technology is given the inverse task of planting an idea into the mind of a C.E.O.', 'https://th.bing.com/th/id/OIP.e3zYJKkT5FmkudC48BunMQHaLH?o=7rm=3&amp;amp;amp;amp;rs=1&amp;amp;amp;amp;pid=ImgDetMain&amp;amp;amp;amp;o=7&amp;amp;amp;amp;rm=3', 'https://www.youtube.com/watch?v=YoHD9XEInc0', 1, 1, 1, '2026-04-29 04:07:01', '2026-04-29 10:00:38'),
(2, 'The Dark Knight', 'Christopher Nolan', 'Action, Crime, Drama', '2h 32min', 'PG-13', 'When the menace known as the Joker wreaks havoc and chaos on the people of Gotham, Batman must accept one of the greatest psychological and physical tests of his ability to fight injustice.', NULL, NULL, 1, 1, NULL, '2026-04-29 04:07:01', NULL),
(3, 'Interstellar', 'Christopher Nolan', 'Adventure, Drama, Sci-Fi', '2h 49min', 'PG-13', 'A team of explorers travel through a wormhole in space in an attempt to ensure humanity\'s survival.', NULL, NULL, 1, 1, NULL, '2026-04-29 04:07:01', NULL),
(4, 'Avatar: The Way of Water', 'James Cameron', 'Sci-Fi, Action', '3h 12min', 'PG-13', 'Jake Sully lives with his newfound family formed on the planet of Pandora.', NULL, NULL, 1, 1, NULL, '2026-04-29 04:07:01', NULL),
(5, 'Top Gun: Maverick', 'Joseph Kosinski', 'Action, Drama', '2h 11min', 'PG-13', 'After more than thirty years of service as one of the Navy\'s top aviators.', NULL, NULL, 1, 1, NULL, '2026-04-29 04:07:01', NULL),
(6, 'SInners', 'Christopher Nolan', 'Sci-Fi, Action', '2h 28min', 'PG-13', 'test', '', '', 1, 1, NULL, '2026-04-29 11:05:53', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `movie_screen_prices`
--

CREATE TABLE `movie_screen_prices` (
  `id` int(11) NOT NULL,
  `movie_id` int(11) NOT NULL,
  `screen_id` int(11) NOT NULL,
  `seat_type_id` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `movie_screen_prices`
--

INSERT INTO `movie_screen_prices` (`id`, `movie_id`, `screen_id`, `seat_type_id`, `price`, `is_active`, `created_at`, `updated_at`) VALUES
(7, 2, 1, 1, 390.00, 1, '2026-04-29 04:07:02', NULL),
(8, 2, 1, 2, 490.00, 1, '2026-04-29 04:07:02', NULL),
(9, 2, 1, 3, 590.00, 1, '2026-04-29 04:07:02', NULL),
(10, 3, 4, 1, 400.00, 1, '2026-04-29 04:07:02', NULL),
(11, 3, 4, 2, 500.00, 1, '2026-04-29 04:07:02', NULL),
(12, 3, 4, 3, 600.00, 1, '2026-04-29 04:07:02', NULL),
(13, 4, 2, 1, 420.00, 1, '2026-04-29 04:07:02', NULL),
(14, 4, 2, 2, 520.00, 1, '2026-04-29 04:07:02', NULL),
(15, 4, 2, 3, 620.00, 1, '2026-04-29 04:07:02', NULL),
(16, 5, 3, 1, 410.00, 1, '2026-04-29 04:07:02', NULL),
(17, 5, 3, 2, 510.00, 1, '2026-04-29 04:07:02', NULL),
(18, 5, 3, 3, 610.00, 1, '2026-04-29 04:07:02', NULL),
(37, 1, 6, 1, 350.00, 1, '2026-04-29 10:00:38', NULL),
(38, 1, 6, 2, 450.00, 1, '2026-04-29 10:00:38', NULL),
(39, 1, 6, 3, 550.00, 1, '2026-04-29 10:00:38', NULL),
(40, 1, 1, 1, 380.00, 1, '2026-04-29 10:00:38', NULL),
(41, 1, 1, 2, 480.00, 1, '2026-04-29 10:00:38', NULL),
(42, 1, 1, 3, 580.00, 1, '2026-04-29 10:00:38', NULL),
(43, 1, 2, 1, 380.00, 1, '2026-04-29 10:00:38', NULL),
(44, 1, 2, 2, 480.00, 1, '2026-04-29 10:00:38', NULL),
(45, 1, 2, 3, 580.00, 1, '2026-04-29 10:00:38', NULL),
(46, 6, 6, 1, 350.00, 1, '2026-04-29 11:05:54', NULL),
(47, 6, 6, 2, 450.00, 1, '2026-04-29 11:05:54', NULL),
(48, 6, 6, 3, 550.00, 1, '2026-04-29 11:05:54', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `movie_venues`
--

CREATE TABLE `movie_venues` (
  `id` int(11) NOT NULL,
  `movie_id` int(11) NOT NULL,
  `venue_id` int(11) NOT NULL,
  `is_primary_venue` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payment_methods`
--

INSERT INTO `payment_methods` (`id`, `method_name`, `account_name`, `account_number`, `qr_code_path`, `instructions`, `is_active`, `display_order`, `created_at`) VALUES
(1, 'GCash', 'Movie Ticket Booking', '09267630945', NULL, NULL, 1, 1, '2026-04-29 04:06:31'),
(2, 'PayMaya', 'Movie Ticket Booking', '09267630945', NULL, NULL, 1, 2, '2026-04-29 04:06:31');

-- --------------------------------------------------------

--
-- Table structure for table `revenue_tracking`
--

CREATE TABLE `revenue_tracking` (
  `id` int(11) NOT NULL,
  `venue_id` int(11) NOT NULL,
  `screen_id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('paymongo','manual') NOT NULL,
  `transaction_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `revenue_tracking`
--

INSERT INTO `revenue_tracking` (`id`, `venue_id`, `screen_id`, `booking_id`, `amount`, `payment_method`, `transaction_date`) VALUES
(1, 3, 6, 1, 10150.00, 'manual', '2026-04-29 12:26:43');

-- --------------------------------------------------------

--
-- Table structure for table `schedules`
--

CREATE TABLE `schedules` (
  `id` int(11) NOT NULL,
  `movie_id` int(11) NOT NULL,
  `screen_id` int(11) NOT NULL,
  `seat_plan_id` int(11) NOT NULL,
  `show_date` date NOT NULL,
  `showtime` time NOT NULL,
  `base_price` decimal(10,2) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `schedules`
--

INSERT INTO `schedules` (`id`, `movie_id`, `screen_id`, `seat_plan_id`, `show_date`, `showtime`, `base_price`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 6, 6, '2026-06-04', '14:00:00', 380.00, 1, '2026-04-29 04:07:02', '2026-04-29 11:21:20'),
(2, 1, 1, 1, '2026-04-30', '19:00:00', 380.00, 1, '2026-04-29 04:07:02', NULL),
(3, 2, 2, 2, '2026-05-01', '15:30:00', 390.00, 1, '2026-04-29 04:07:02', NULL),
(4, 2, 2, 2, '2026-05-01', '20:00:00', 390.00, 1, '2026-04-29 04:07:02', NULL),
(5, 3, 4, 4, '2026-05-02', '13:00:00', 400.00, 1, '2026-04-29 04:07:02', NULL),
(6, 3, 4, 4, '2026-05-02', '18:30:00', 400.00, 1, '2026-04-29 04:07:02', NULL),
(7, 4, 2, 2, '2026-05-03', '16:00:00', 420.00, 1, '2026-04-29 04:07:02', NULL),
(8, 4, 2, 2, '2026-05-03', '21:00:00', 420.00, 1, '2026-04-29 04:07:02', NULL),
(9, 5, 3, 3, '2026-05-04', '14:30:00', 410.00, 1, '2026-04-29 04:07:02', NULL),
(10, 5, 3, 3, '2026-05-04', '19:30:00', 410.00, 1, '2026-04-29 04:07:02', NULL),
(11, 4, 6, 6, '2026-05-01', '09:00:00', 350.00, 1, '2026-04-29 10:33:39', NULL),
(12, 1, 8, 9, '2026-04-30', '21:00:00', 350.00, 1, '2026-04-29 11:13:08', NULL),
(13, 1, 7, 7, '2026-05-09', '11:00:00', 350.00, 1, '2026-04-29 11:21:51', NULL);

-- --------------------------------------------------------

--
-- Stand-in structure for view `schedule_availability_summary`
-- (See below for the actual view)
--
CREATE TABLE `schedule_availability_summary` (
`schedule_id` int(11)
,`movie_id` int(11)
,`movie_title` varchar(255)
,`screen_id` int(11)
,`screen_name` varchar(100)
,`screen_number` int(11)
,`venue_name` varchar(255)
,`show_date` date
,`showtime` time
,`base_price` decimal(10,2)
,`total_seats` bigint(21)
,`available_seats` bigint(21)
,`booked_seats` bigint(21)
,`reserved_seats` bigint(21)
,`occupancy_rate` decimal(26,2)
,`schedule_status` varchar(8)
);

-- --------------------------------------------------------

--
-- Table structure for table `screens`
--

CREATE TABLE `screens` (
  `id` int(11) NOT NULL,
  `venue_id` int(11) NOT NULL,
  `screen_name` varchar(100) NOT NULL,
  `screen_number` int(11) NOT NULL,
  `capacity` int(11) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `screens`
--

INSERT INTO `screens` (`id`, `venue_id`, `screen_name`, `screen_number`, `capacity`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 'Cinema 1', 1, 120, 1, '2026-04-29 04:06:33', NULL),
(2, 1, 'Cinema 2', 2, 150, 1, '2026-04-29 04:06:33', NULL),
(3, 1, 'Cinema 3', 3, 100, 1, '2026-04-29 04:06:33', NULL),
(4, 2, 'Cinema 1', 1, 200, 1, '2026-04-29 04:06:33', NULL),
(5, 2, 'Cinema 2', 2, 180, 1, '2026-04-29 04:06:33', NULL),
(6, 3, 'Cinema A', 1, 120, 1, '2026-04-29 04:06:33', '2026-04-29 07:06:16'),
(7, 3, 'Cinema B', 2, 130, 1, '2026-04-29 04:06:33', NULL),
(8, 3, 'CINEMA C', 3, 70, 1, '2026-04-29 11:07:50', '2026-04-29 11:12:33');

-- --------------------------------------------------------

--
-- Table structure for table `seat_availability`
--

CREATE TABLE `seat_availability` (
  `id` int(11) NOT NULL,
  `schedule_id` int(11) NOT NULL,
  `seat_plan_detail_id` int(11) NOT NULL,
  `seat_number` varchar(10) NOT NULL,
  `seat_type_id` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `status` enum('available','reserved','booked') DEFAULT 'available',
  `locked_by` int(11) DEFAULT NULL,
  `locked_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `seat_availability`
--

INSERT INTO `seat_availability` (`id`, `schedule_id`, `seat_plan_detail_id`, `seat_number`, `seat_type_id`, `price`, `status`, `locked_by`, `locked_at`, `created_at`, `updated_at`) VALUES
(1, 11, 783, 'A01', 1, 350.00, 'available', NULL, NULL, '2026-04-29 10:33:39', NULL),
(2, 11, 784, 'A02', 1, 350.00, 'available', NULL, NULL, '2026-04-29 10:33:39', NULL),
(3, 11, 795, 'B01', 1, 350.00, 'available', NULL, NULL, '2026-04-29 10:33:39', NULL),
(4, 11, 796, 'B02', 1, 350.00, 'available', NULL, NULL, '2026-04-29 10:33:39', NULL),
(5, 11, 807, 'C01', 1, 350.00, 'booked', NULL, NULL, '2026-04-29 10:33:39', '2026-04-29 11:42:19'),
(6, 11, 808, 'C02', 1, 350.00, 'available', NULL, NULL, '2026-04-29 10:33:39', NULL),
(7, 11, 855, 'G01', 1, 350.00, 'available', NULL, NULL, '2026-04-29 10:33:39', NULL),
(8, 11, 856, 'G02', 1, 350.00, 'available', NULL, NULL, '2026-04-29 10:33:39', NULL),
(9, 11, 857, 'G03', 1, 350.00, 'available', NULL, NULL, '2026-04-29 10:33:39', NULL),
(10, 11, 858, 'G04', 1, 350.00, 'available', NULL, NULL, '2026-04-29 10:33:39', NULL),
(11, 11, 859, 'G05', 1, 350.00, 'available', NULL, NULL, '2026-04-29 10:33:39', NULL),
(12, 11, 860, 'G06', 1, 350.00, 'available', NULL, NULL, '2026-04-29 10:33:39', NULL),
(13, 11, 861, 'G07', 1, 350.00, 'available', NULL, NULL, '2026-04-29 10:33:39', NULL),
(14, 11, 862, 'G08', 1, 350.00, 'available', NULL, NULL, '2026-04-29 10:33:39', NULL),
(15, 11, 863, 'G09', 1, 350.00, 'available', NULL, NULL, '2026-04-29 10:33:39', NULL),
(16, 11, 864, 'G10', 1, 350.00, 'available', NULL, NULL, '2026-04-29 10:33:39', '2026-04-29 17:07:02'),
(17, 11, 865, 'G11', 1, 350.00, 'available', NULL, NULL, '2026-04-29 10:33:39', '2026-04-29 17:07:02'),
(18, 11, 866, 'G12', 1, 350.00, 'available', NULL, NULL, '2026-04-29 10:33:39', '2026-04-29 17:07:02'),
(19, 11, 867, 'H01', 1, 350.00, 'available', NULL, NULL, '2026-04-29 10:33:39', NULL),
(20, 11, 868, 'H02', 1, 350.00, 'available', NULL, NULL, '2026-04-29 10:33:39', NULL),
(21, 11, 869, 'H03', 1, 350.00, 'available', NULL, NULL, '2026-04-29 10:33:39', NULL),
(22, 11, 870, 'H04', 1, 350.00, 'available', NULL, NULL, '2026-04-29 10:33:39', NULL),
(23, 11, 871, 'H05', 1, 350.00, 'available', NULL, NULL, '2026-04-29 10:33:39', NULL),
(24, 11, 872, 'H06', 1, 350.00, 'available', NULL, NULL, '2026-04-29 10:33:39', NULL),
(25, 11, 873, 'H07', 1, 350.00, 'available', NULL, NULL, '2026-04-29 10:33:39', NULL),
(26, 11, 874, 'H08', 1, 350.00, 'available', NULL, NULL, '2026-04-29 10:33:39', NULL),
(27, 11, 875, 'H09', 1, 350.00, 'available', NULL, NULL, '2026-04-29 10:33:39', '2026-04-29 17:07:02'),
(28, 11, 876, 'H10', 1, 350.00, 'available', NULL, NULL, '2026-04-29 10:33:39', '2026-04-29 17:07:02'),
(29, 11, 877, 'H11', 1, 350.00, 'available', NULL, NULL, '2026-04-29 10:33:39', '2026-04-29 17:07:02'),
(30, 11, 878, 'H12', 1, 350.00, 'available', NULL, NULL, '2026-04-29 10:33:39', '2026-04-29 17:07:02'),
(31, 11, 879, 'I01', 1, 350.00, 'available', NULL, NULL, '2026-04-29 10:33:39', NULL),
(32, 11, 880, 'I02', 1, 350.00, 'available', NULL, NULL, '2026-04-29 10:33:39', NULL),
(33, 11, 881, 'I03', 1, 350.00, 'available', NULL, NULL, '2026-04-29 10:33:39', NULL),
(34, 11, 882, 'I04', 1, 350.00, 'available', NULL, NULL, '2026-04-29 10:33:39', '2026-04-29 17:07:02'),
(35, 11, 883, 'I05', 1, 350.00, 'available', NULL, NULL, '2026-04-29 10:33:39', NULL),
(36, 11, 884, 'I06', 1, 350.00, 'available', NULL, NULL, '2026-04-29 10:33:39', NULL),
(37, 11, 885, 'I07', 1, 350.00, 'available', NULL, NULL, '2026-04-29 10:33:39', NULL),
(38, 11, 886, 'I08', 1, 350.00, 'available', NULL, NULL, '2026-04-29 10:33:39', '2026-04-29 17:07:02'),
(39, 11, 887, 'I09', 1, 350.00, 'available', NULL, NULL, '2026-04-29 10:33:39', '2026-04-29 17:07:02'),
(40, 11, 888, 'I10', 1, 350.00, 'available', NULL, NULL, '2026-04-29 10:33:39', '2026-04-29 17:07:02'),
(41, 11, 889, 'I11', 1, 350.00, 'available', NULL, NULL, '2026-04-29 10:33:39', '2026-04-29 17:07:02'),
(42, 11, 890, 'I12', 1, 350.00, 'available', NULL, NULL, '2026-04-29 10:33:39', '2026-04-29 17:07:02'),
(43, 11, 891, 'J01', 1, 350.00, 'available', NULL, NULL, '2026-04-29 10:33:39', NULL),
(44, 11, 892, 'J02', 1, 350.00, 'available', NULL, NULL, '2026-04-29 10:33:39', NULL),
(45, 11, 893, 'J03', 1, 350.00, 'booked', NULL, NULL, '2026-04-29 10:33:39', '2026-04-29 11:00:34'),
(46, 11, 894, 'J04', 1, 350.00, 'available', NULL, NULL, '2026-04-29 10:33:39', NULL),
(47, 11, 895, 'J05', 1, 350.00, 'available', NULL, NULL, '2026-04-29 10:33:39', NULL),
(48, 11, 896, 'J06', 1, 350.00, 'booked', NULL, NULL, '2026-04-29 10:33:39', '2026-04-29 11:00:34'),
(49, 11, 897, 'J07', 1, 350.00, 'booked', NULL, NULL, '2026-04-29 10:33:39', '2026-04-29 11:00:34'),
(50, 11, 898, 'J08', 1, 350.00, 'available', NULL, NULL, '2026-04-29 10:33:39', NULL),
(51, 11, 899, 'J09', 1, 350.00, 'available', NULL, NULL, '2026-04-29 10:33:39', '2026-04-29 17:07:02'),
(52, 11, 900, 'J10', 1, 350.00, 'available', NULL, NULL, '2026-04-29 10:33:39', '2026-04-29 17:07:02'),
(53, 11, 901, 'J11', 1, 350.00, 'available', NULL, NULL, '2026-04-29 10:33:39', '2026-04-29 17:07:02'),
(54, 11, 902, 'J12', 1, 350.00, 'booked', NULL, NULL, '2026-04-29 10:33:39', '2026-04-29 11:00:34'),
(55, 11, 785, 'A03', 2, 450.00, 'booked', NULL, NULL, '2026-04-29 10:33:39', '2026-04-29 11:00:34'),
(56, 11, 786, 'A04', 2, 450.00, 'booked', NULL, NULL, '2026-04-29 10:33:39', '2026-04-29 11:00:34'),
(57, 11, 787, 'A05', 2, 450.00, 'booked', NULL, NULL, '2026-04-29 10:33:39', '2026-04-29 17:15:57'),
(58, 11, 788, 'A06', 2, 450.00, 'booked', NULL, NULL, '2026-04-29 10:33:39', '2026-04-29 17:15:57'),
(59, 11, 789, 'A07', 2, 450.00, 'booked', NULL, NULL, '2026-04-29 10:33:39', '2026-04-29 11:00:34'),
(60, 11, 790, 'A08', 2, 450.00, 'booked', NULL, NULL, '2026-04-29 10:33:39', '2026-04-29 11:00:34'),
(61, 11, 791, 'A09', 2, 450.00, 'available', NULL, NULL, '2026-04-29 10:33:39', NULL),
(62, 11, 792, 'A10', 2, 450.00, 'booked', NULL, NULL, '2026-04-29 10:33:39', '2026-04-29 11:00:34'),
(63, 11, 793, 'A11', 2, 450.00, 'available', NULL, NULL, '2026-04-29 10:33:39', NULL),
(64, 11, 794, 'A12', 2, 450.00, 'available', NULL, NULL, '2026-04-29 10:33:39', NULL),
(65, 11, 797, 'B03', 2, 450.00, 'booked', NULL, NULL, '2026-04-29 10:33:39', '2026-04-29 17:15:57'),
(66, 11, 798, 'B04', 2, 450.00, 'booked', NULL, NULL, '2026-04-29 10:33:39', '2026-04-29 17:15:57'),
(67, 11, 799, 'B05', 2, 450.00, 'booked', NULL, NULL, '2026-04-29 10:33:39', '2026-04-29 17:15:57'),
(68, 11, 800, 'B06', 2, 450.00, 'available', NULL, NULL, '2026-04-29 10:33:39', '2026-04-29 17:07:02'),
(69, 11, 801, 'B07', 2, 450.00, 'available', NULL, NULL, '2026-04-29 10:33:39', '2026-04-29 17:07:02'),
(70, 11, 802, 'B08', 2, 450.00, 'available', NULL, NULL, '2026-04-29 10:33:39', NULL),
(71, 11, 803, 'B09', 2, 450.00, 'available', NULL, NULL, '2026-04-29 10:33:39', NULL),
(72, 11, 804, 'B10', 2, 450.00, 'available', NULL, NULL, '2026-04-29 10:33:39', NULL),
(73, 11, 805, 'B11', 2, 450.00, 'booked', NULL, NULL, '2026-04-29 10:33:39', '2026-04-29 11:00:34'),
(74, 11, 806, 'B12', 2, 450.00, 'available', NULL, NULL, '2026-04-29 10:33:39', NULL),
(75, 11, 809, 'C03', 2, 450.00, 'booked', NULL, NULL, '2026-04-29 10:33:39', '2026-04-29 11:42:19'),
(76, 11, 810, 'C04', 2, 450.00, 'booked', NULL, NULL, '2026-04-29 10:33:39', '2026-04-29 11:42:19'),
(77, 11, 811, 'C05', 2, 450.00, 'booked', NULL, NULL, '2026-04-29 10:33:39', '2026-04-29 11:42:19'),
(78, 11, 812, 'C06', 2, 450.00, 'available', NULL, NULL, '2026-04-29 10:33:39', NULL),
(79, 11, 813, 'C07', 2, 450.00, 'available', NULL, NULL, '2026-04-29 10:33:39', NULL),
(80, 11, 814, 'C08', 2, 450.00, 'available', NULL, NULL, '2026-04-29 10:33:39', NULL),
(81, 11, 815, 'C09', 2, 450.00, 'booked', NULL, NULL, '2026-04-29 10:33:39', '2026-04-29 11:42:19'),
(82, 11, 816, 'C10', 2, 450.00, 'booked', NULL, NULL, '2026-04-29 10:33:39', '2026-04-29 11:42:19'),
(83, 11, 817, 'C11', 2, 450.00, 'available', NULL, NULL, '2026-04-29 10:33:39', NULL),
(84, 11, 818, 'C12', 2, 450.00, 'booked', NULL, NULL, '2026-04-29 10:33:39', '2026-04-29 11:42:19'),
(85, 11, 819, 'D01', 3, 550.00, 'booked', NULL, NULL, '2026-04-29 10:33:39', '2026-04-29 11:42:19'),
(86, 11, 820, 'D02', 3, 550.00, 'available', NULL, NULL, '2026-04-29 10:33:39', NULL),
(87, 11, 821, 'D03', 3, 550.00, 'booked', NULL, NULL, '2026-04-29 10:33:39', '2026-04-29 11:42:19'),
(88, 11, 822, 'D04', 3, 550.00, 'available', NULL, NULL, '2026-04-29 10:33:39', NULL),
(89, 11, 823, 'D05', 3, 550.00, 'booked', NULL, NULL, '2026-04-29 10:33:39', '2026-04-29 11:42:19'),
(90, 11, 824, 'D06', 3, 550.00, 'booked', NULL, NULL, '2026-04-29 10:33:39', '2026-04-29 11:42:19'),
(91, 11, 825, 'D07', 3, 550.00, 'available', NULL, NULL, '2026-04-29 10:33:39', NULL),
(92, 11, 826, 'D08', 3, 550.00, 'available', NULL, NULL, '2026-04-29 10:33:39', NULL),
(93, 11, 827, 'D09', 3, 550.00, 'available', NULL, NULL, '2026-04-29 10:33:39', NULL),
(94, 11, 828, 'D10', 3, 550.00, 'booked', NULL, NULL, '2026-04-29 10:33:39', '2026-04-29 11:42:19'),
(95, 11, 829, 'D11', 3, 550.00, 'available', NULL, NULL, '2026-04-29 10:33:39', NULL),
(96, 11, 830, 'D12', 3, 550.00, 'available', NULL, NULL, '2026-04-29 10:33:39', NULL),
(97, 11, 831, 'E01', 3, 550.00, 'available', NULL, NULL, '2026-04-29 10:33:39', NULL),
(98, 11, 832, 'E02', 3, 550.00, 'available', NULL, NULL, '2026-04-29 10:33:39', NULL),
(99, 11, 833, 'E03', 3, 550.00, 'available', NULL, NULL, '2026-04-29 10:33:39', NULL),
(100, 11, 834, 'E04', 3, 550.00, 'available', NULL, NULL, '2026-04-29 10:33:39', NULL),
(101, 11, 835, 'E05', 3, 550.00, 'available', NULL, NULL, '2026-04-29 10:33:39', NULL),
(102, 11, 836, 'E06', 3, 550.00, 'available', NULL, NULL, '2026-04-29 10:33:39', NULL),
(103, 11, 837, 'E07', 3, 550.00, 'available', NULL, NULL, '2026-04-29 10:33:39', NULL),
(104, 11, 838, 'E08', 3, 550.00, 'available', NULL, NULL, '2026-04-29 10:33:39', NULL),
(105, 11, 839, 'E09', 3, 550.00, 'available', NULL, NULL, '2026-04-29 10:33:39', NULL),
(106, 11, 840, 'E10', 3, 550.00, 'available', NULL, NULL, '2026-04-29 10:33:39', NULL),
(107, 11, 841, 'E11', 3, 550.00, 'booked', NULL, NULL, '2026-04-29 10:33:39', '2026-04-29 11:00:34'),
(108, 11, 842, 'E12', 3, 550.00, 'available', NULL, NULL, '2026-04-29 10:33:39', NULL),
(109, 11, 843, 'F01', 3, 550.00, 'booked', NULL, NULL, '2026-04-29 10:33:39', '2026-04-29 11:00:34'),
(110, 11, 844, 'F02', 3, 550.00, 'booked', NULL, NULL, '2026-04-29 10:33:39', '2026-04-29 11:00:34'),
(111, 11, 845, 'F03', 3, 550.00, 'available', NULL, NULL, '2026-04-29 10:33:39', NULL),
(112, 11, 846, 'F04', 3, 550.00, 'available', NULL, NULL, '2026-04-29 10:33:39', NULL),
(113, 11, 847, 'F05', 3, 550.00, 'booked', NULL, NULL, '2026-04-29 10:33:39', '2026-04-29 11:00:34'),
(114, 11, 848, 'F06', 3, 550.00, 'booked', NULL, NULL, '2026-04-29 10:33:39', '2026-04-29 11:00:34'),
(115, 11, 849, 'F07', 3, 550.00, 'booked', NULL, NULL, '2026-04-29 10:33:39', '2026-04-29 11:00:34'),
(116, 11, 850, 'F08', 3, 550.00, 'booked', NULL, NULL, '2026-04-29 10:33:39', '2026-04-29 11:00:34'),
(117, 11, 851, 'F09', 3, 550.00, 'booked', NULL, NULL, '2026-04-29 10:33:39', '2026-04-29 11:00:34'),
(118, 11, 852, 'F10', 3, 550.00, 'booked', NULL, NULL, '2026-04-29 10:33:39', '2026-04-29 11:00:34'),
(119, 11, 853, 'F11', 3, 550.00, 'booked', NULL, NULL, '2026-04-29 10:33:39', '2026-04-29 11:00:34'),
(120, 11, 854, 'F12', 3, 550.00, 'booked', NULL, NULL, '2026-04-29 10:33:39', '2026-04-29 11:00:34'),
(128, 12, 933, 'D01', 1, 350.00, 'available', NULL, NULL, '2026-04-29 11:13:08', NULL),
(129, 12, 934, 'D02', 1, 350.00, 'available', NULL, NULL, '2026-04-29 11:13:08', NULL),
(130, 12, 935, 'D03', 1, 350.00, 'available', NULL, NULL, '2026-04-29 11:13:08', NULL),
(131, 12, 936, 'D04', 1, 350.00, 'available', NULL, NULL, '2026-04-29 11:13:08', NULL),
(132, 12, 937, 'D05', 1, 350.00, 'available', NULL, NULL, '2026-04-29 11:13:08', NULL),
(133, 12, 938, 'D06', 1, 350.00, 'available', NULL, NULL, '2026-04-29 11:13:08', NULL),
(134, 12, 939, 'D07', 1, 350.00, 'available', NULL, NULL, '2026-04-29 11:13:08', NULL),
(135, 12, 940, 'D08', 1, 350.00, 'available', NULL, NULL, '2026-04-29 11:13:08', NULL),
(136, 12, 941, 'D09', 1, 350.00, 'available', NULL, NULL, '2026-04-29 11:13:08', NULL),
(137, 12, 942, 'D10', 1, 350.00, 'available', NULL, NULL, '2026-04-29 11:13:08', NULL),
(138, 12, 903, 'A01', 2, 450.00, 'available', NULL, NULL, '2026-04-29 11:13:08', NULL),
(139, 12, 904, 'A02', 2, 450.00, 'available', NULL, NULL, '2026-04-29 11:13:08', NULL),
(140, 12, 905, 'A03', 2, 450.00, 'available', NULL, NULL, '2026-04-29 11:13:08', NULL),
(141, 12, 906, 'A04', 2, 450.00, 'available', NULL, NULL, '2026-04-29 11:13:08', NULL),
(142, 12, 907, 'A05', 2, 450.00, 'available', NULL, NULL, '2026-04-29 11:13:08', NULL),
(143, 12, 908, 'A06', 2, 450.00, 'available', NULL, NULL, '2026-04-29 11:13:08', NULL),
(144, 12, 909, 'A07', 2, 450.00, 'available', NULL, NULL, '2026-04-29 11:13:08', NULL),
(145, 12, 910, 'A08', 2, 450.00, 'available', NULL, NULL, '2026-04-29 11:13:08', NULL),
(146, 12, 911, 'A09', 2, 450.00, 'available', NULL, NULL, '2026-04-29 11:13:08', NULL),
(147, 12, 912, 'A10', 2, 450.00, 'available', NULL, NULL, '2026-04-29 11:13:08', NULL),
(148, 12, 913, 'B01', 2, 450.00, 'available', NULL, NULL, '2026-04-29 11:13:08', NULL),
(149, 12, 914, 'B02', 2, 450.00, 'available', NULL, NULL, '2026-04-29 11:13:08', NULL),
(150, 12, 915, 'B03', 2, 450.00, 'available', NULL, NULL, '2026-04-29 11:13:08', NULL),
(151, 12, 916, 'B04', 2, 450.00, 'available', NULL, NULL, '2026-04-29 11:13:08', NULL),
(152, 12, 917, 'B05', 2, 450.00, 'available', NULL, NULL, '2026-04-29 11:13:08', NULL),
(153, 12, 918, 'B06', 2, 450.00, 'available', NULL, NULL, '2026-04-29 11:13:08', NULL),
(154, 12, 919, 'B07', 2, 450.00, 'available', NULL, NULL, '2026-04-29 11:13:08', NULL),
(155, 12, 920, 'B08', 2, 450.00, 'available', NULL, NULL, '2026-04-29 11:13:08', NULL),
(156, 12, 921, 'B09', 2, 450.00, 'available', NULL, NULL, '2026-04-29 11:13:08', NULL),
(157, 12, 922, 'B10', 2, 450.00, 'available', NULL, NULL, '2026-04-29 11:13:08', NULL),
(158, 12, 923, 'C01', 2, 450.00, 'available', NULL, NULL, '2026-04-29 11:13:08', NULL),
(159, 12, 924, 'C02', 2, 450.00, 'available', NULL, NULL, '2026-04-29 11:13:08', NULL),
(160, 12, 925, 'C03', 2, 450.00, 'available', NULL, NULL, '2026-04-29 11:13:08', NULL),
(161, 12, 926, 'C04', 2, 450.00, 'available', NULL, NULL, '2026-04-29 11:13:08', NULL),
(162, 12, 927, 'C05', 2, 450.00, 'available', NULL, NULL, '2026-04-29 11:13:08', NULL),
(163, 12, 928, 'C06', 2, 450.00, 'available', NULL, NULL, '2026-04-29 11:13:08', NULL),
(164, 12, 929, 'C07', 2, 450.00, 'available', NULL, NULL, '2026-04-29 11:13:08', NULL),
(165, 12, 930, 'C08', 2, 450.00, 'available', NULL, NULL, '2026-04-29 11:13:08', NULL),
(166, 12, 931, 'C09', 2, 450.00, 'available', NULL, NULL, '2026-04-29 11:13:08', NULL),
(167, 12, 932, 'C10', 2, 450.00, 'available', NULL, NULL, '2026-04-29 11:13:08', NULL),
(168, 12, 943, 'E01', 3, 550.00, 'available', NULL, NULL, '2026-04-29 11:13:08', NULL),
(169, 12, 944, 'E02', 3, 550.00, 'available', NULL, NULL, '2026-04-29 11:13:08', NULL),
(170, 12, 945, 'E03', 3, 550.00, 'available', NULL, NULL, '2026-04-29 11:13:08', NULL),
(171, 12, 946, 'E04', 3, 550.00, 'available', NULL, NULL, '2026-04-29 11:13:08', NULL),
(172, 12, 947, 'E05', 3, 550.00, 'available', NULL, NULL, '2026-04-29 11:13:08', NULL),
(173, 12, 948, 'E06', 3, 550.00, 'available', NULL, NULL, '2026-04-29 11:13:08', NULL),
(174, 12, 949, 'E07', 3, 550.00, 'available', NULL, NULL, '2026-04-29 11:13:08', NULL),
(175, 12, 950, 'E08', 3, 550.00, 'available', NULL, NULL, '2026-04-29 11:13:08', NULL),
(176, 12, 951, 'E09', 3, 550.00, 'available', NULL, NULL, '2026-04-29 11:13:08', NULL),
(177, 12, 952, 'E10', 3, 550.00, 'available', NULL, NULL, '2026-04-29 11:13:08', NULL),
(178, 12, 953, 'F01', 3, 550.00, 'available', NULL, NULL, '2026-04-29 11:13:08', NULL),
(179, 12, 954, 'F02', 3, 550.00, 'available', NULL, NULL, '2026-04-29 11:13:08', NULL),
(180, 12, 955, 'F03', 3, 550.00, 'available', NULL, NULL, '2026-04-29 11:13:08', NULL),
(181, 12, 956, 'F04', 3, 550.00, 'available', NULL, NULL, '2026-04-29 11:13:08', NULL),
(182, 12, 957, 'F05', 3, 550.00, 'available', NULL, NULL, '2026-04-29 11:13:08', NULL),
(183, 12, 958, 'F06', 3, 550.00, 'available', NULL, NULL, '2026-04-29 11:13:08', NULL),
(184, 12, 959, 'F07', 3, 550.00, 'available', NULL, NULL, '2026-04-29 11:13:08', NULL),
(185, 12, 960, 'F08', 3, 550.00, 'available', NULL, NULL, '2026-04-29 11:13:08', NULL),
(186, 12, 961, 'F09', 3, 550.00, 'available', NULL, NULL, '2026-04-29 11:13:08', NULL),
(187, 12, 962, 'F10', 3, 550.00, 'available', NULL, NULL, '2026-04-29 11:13:08', NULL),
(188, 12, 963, 'G01', 3, 550.00, 'available', NULL, NULL, '2026-04-29 11:13:08', NULL),
(189, 12, 964, 'G02', 3, 550.00, 'available', NULL, NULL, '2026-04-29 11:13:08', NULL),
(190, 12, 965, 'G03', 3, 550.00, 'available', NULL, NULL, '2026-04-29 11:13:08', NULL),
(191, 12, 966, 'G04', 3, 550.00, 'available', NULL, NULL, '2026-04-29 11:13:08', NULL),
(192, 12, 967, 'G05', 3, 550.00, 'available', NULL, NULL, '2026-04-29 11:13:08', NULL),
(193, 12, 968, 'G06', 3, 550.00, 'available', NULL, NULL, '2026-04-29 11:13:08', NULL),
(194, 12, 969, 'G07', 3, 550.00, 'available', NULL, NULL, '2026-04-29 11:13:08', NULL),
(195, 12, 970, 'G08', 3, 550.00, 'available', NULL, NULL, '2026-04-29 11:13:08', NULL),
(196, 12, 971, 'G09', 3, 550.00, 'available', NULL, NULL, '2026-04-29 11:13:08', NULL),
(197, 12, 972, 'G10', 3, 550.00, 'available', NULL, NULL, '2026-04-29 11:13:08', NULL),
(255, 13, 753, 'F01', 1, 350.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(256, 13, 754, 'F02', 1, 350.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(257, 13, 755, 'F03', 1, 350.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(258, 13, 756, 'F04', 1, 350.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(259, 13, 757, 'F05', 1, 350.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(260, 13, 758, 'F06', 1, 350.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(261, 13, 759, 'F07', 1, 350.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(262, 13, 760, 'F08', 1, 350.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(263, 13, 761, 'F09', 1, 350.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(264, 13, 762, 'F10', 1, 350.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(265, 13, 763, 'G01', 1, 350.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(266, 13, 764, 'G02', 1, 350.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(267, 13, 765, 'G03', 1, 350.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(268, 13, 766, 'G04', 1, 350.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(269, 13, 767, 'G05', 1, 350.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(270, 13, 768, 'G06', 1, 350.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(271, 13, 769, 'G07', 1, 350.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(272, 13, 770, 'G08', 1, 350.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(273, 13, 771, 'G09', 1, 350.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(274, 13, 772, 'G10', 1, 350.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(275, 13, 773, 'H01', 1, 350.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(276, 13, 774, 'H02', 1, 350.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(277, 13, 775, 'H03', 1, 350.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(278, 13, 776, 'H04', 1, 350.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(279, 13, 777, 'H05', 1, 350.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(280, 13, 778, 'H06', 1, 350.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(281, 13, 779, 'H07', 1, 350.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(282, 13, 780, 'H08', 1, 350.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(283, 13, 781, 'H09', 1, 350.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(284, 13, 782, 'H10', 1, 350.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(285, 13, 703, 'A01', 2, 450.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(286, 13, 704, 'A02', 2, 450.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(287, 13, 705, 'A03', 2, 450.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(288, 13, 706, 'A04', 2, 450.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(289, 13, 707, 'A05', 2, 450.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(290, 13, 708, 'A06', 2, 450.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(291, 13, 709, 'A07', 2, 450.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(292, 13, 710, 'A08', 2, 450.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(293, 13, 711, 'A09', 2, 450.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(294, 13, 712, 'A10', 2, 450.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(295, 13, 713, 'B01', 2, 450.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(296, 13, 714, 'B02', 2, 450.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(297, 13, 715, 'B03', 2, 450.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(298, 13, 716, 'B04', 2, 450.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(299, 13, 717, 'B05', 2, 450.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(300, 13, 718, 'B06', 2, 450.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(301, 13, 719, 'B07', 2, 450.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(302, 13, 720, 'B08', 2, 450.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(303, 13, 721, 'B09', 2, 450.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(304, 13, 722, 'B10', 2, 450.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(305, 13, 723, 'C01', 2, 450.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(306, 13, 724, 'C02', 2, 450.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(307, 13, 725, 'C03', 2, 450.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(308, 13, 726, 'C04', 2, 450.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(309, 13, 727, 'C05', 2, 450.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(310, 13, 728, 'C06', 2, 450.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(311, 13, 729, 'C07', 2, 450.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(312, 13, 730, 'C08', 2, 450.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(313, 13, 731, 'C09', 2, 450.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(314, 13, 732, 'C10', 2, 450.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(315, 13, 733, 'D01', 3, 550.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(316, 13, 734, 'D02', 3, 550.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(317, 13, 735, 'D03', 3, 550.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(318, 13, 736, 'D04', 3, 550.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(319, 13, 737, 'D05', 3, 550.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(320, 13, 738, 'D06', 3, 550.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(321, 13, 739, 'D07', 3, 550.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(322, 13, 740, 'D08', 3, 550.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(323, 13, 741, 'D09', 3, 550.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(324, 13, 742, 'D10', 3, 550.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(325, 13, 743, 'E01', 3, 550.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(326, 13, 744, 'E02', 3, 550.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(327, 13, 745, 'E03', 3, 550.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(328, 13, 746, 'E04', 3, 550.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(329, 13, 747, 'E05', 3, 550.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(330, 13, 748, 'E06', 3, 550.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(331, 13, 749, 'E07', 3, 550.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(332, 13, 750, 'E08', 3, 550.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(333, 13, 751, 'E09', 3, 550.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL),
(334, 13, 752, 'E10', 3, 550.00, 'available', NULL, NULL, '2026-04-29 11:21:51', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `seat_plans`
--

CREATE TABLE `seat_plans` (
  `id` int(11) NOT NULL,
  `screen_id` int(11) NOT NULL,
  `plan_name` varchar(100) NOT NULL,
  `total_rows` int(11) NOT NULL,
  `total_columns` int(11) NOT NULL,
  `total_seats` int(11) GENERATED ALWAYS AS (`total_rows` * `total_columns`) STORED,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `seat_plans`
--

INSERT INTO `seat_plans` (`id`, `screen_id`, `plan_name`, `total_rows`, `total_columns`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 'Standard Layout', 8, 10, 1, '2026-04-29 04:06:33', NULL),
(2, 2, 'Standard Layout', 10, 12, 1, '2026-04-29 04:06:33', NULL),
(3, 3, 'Standard Layout', 8, 8, 1, '2026-04-29 04:06:33', NULL),
(4, 4, 'Large Hall Layout', 12, 14, 1, '2026-04-29 04:06:33', NULL),
(5, 5, 'Wide Layout', 10, 15, 1, '2026-04-29 04:06:33', NULL),
(6, 6, 'Premium Layout', 10, 12, 1, '2026-04-29 04:06:33', NULL),
(7, 7, 'Intimate Layout', 8, 10, 1, '2026-04-29 04:06:33', NULL),
(9, 8, 'SCREEN 3', 7, 10, 1, '2026-04-29 11:12:33', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `seat_plan_details`
--

CREATE TABLE `seat_plan_details` (
  `id` int(11) NOT NULL,
  `seat_plan_id` int(11) NOT NULL,
  `seat_row` char(1) NOT NULL,
  `seat_column` int(11) NOT NULL,
  `seat_number` varchar(10) NOT NULL,
  `seat_type_id` int(11) NOT NULL,
  `is_enabled` tinyint(1) DEFAULT 1,
  `custom_price` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `seat_plan_details`
--

INSERT INTO `seat_plan_details` (`id`, `seat_plan_id`, `seat_row`, `seat_column`, `seat_number`, `seat_type_id`, `is_enabled`, `custom_price`, `created_at`) VALUES
(1, 1, 'A', 1, 'A01', 2, 1, NULL, '2026-04-29 04:06:33'),
(2, 1, 'A', 2, 'A02', 2, 1, NULL, '2026-04-29 04:06:33'),
(3, 1, 'A', 3, 'A03', 2, 1, NULL, '2026-04-29 04:06:33'),
(4, 1, 'A', 4, 'A04', 2, 1, NULL, '2026-04-29 04:06:33'),
(5, 1, 'A', 5, 'A05', 2, 1, NULL, '2026-04-29 04:06:33'),
(6, 1, 'A', 6, 'A06', 2, 1, NULL, '2026-04-29 04:06:33'),
(7, 1, 'A', 7, 'A07', 2, 1, NULL, '2026-04-29 04:06:33'),
(8, 1, 'A', 8, 'A08', 2, 1, NULL, '2026-04-29 04:06:33'),
(9, 1, 'A', 9, 'A09', 2, 1, NULL, '2026-04-29 04:06:33'),
(10, 1, 'A', 10, 'A10', 2, 1, NULL, '2026-04-29 04:06:33'),
(11, 1, 'B', 1, 'B01', 2, 1, NULL, '2026-04-29 04:06:33'),
(12, 1, 'B', 2, 'B02', 2, 1, NULL, '2026-04-29 04:06:34'),
(13, 1, 'B', 3, 'B03', 2, 1, NULL, '2026-04-29 04:06:34'),
(14, 1, 'B', 4, 'B04', 2, 1, NULL, '2026-04-29 04:06:34'),
(15, 1, 'B', 5, 'B05', 2, 1, NULL, '2026-04-29 04:06:34'),
(16, 1, 'B', 6, 'B06', 2, 1, NULL, '2026-04-29 04:06:34'),
(17, 1, 'B', 7, 'B07', 2, 1, NULL, '2026-04-29 04:06:34'),
(18, 1, 'B', 8, 'B08', 2, 1, NULL, '2026-04-29 04:06:34'),
(19, 1, 'B', 9, 'B09', 2, 1, NULL, '2026-04-29 04:06:34'),
(20, 1, 'B', 10, 'B10', 2, 1, NULL, '2026-04-29 04:06:34'),
(21, 1, 'C', 1, 'C01', 2, 1, NULL, '2026-04-29 04:06:34'),
(22, 1, 'C', 2, 'C02', 2, 1, NULL, '2026-04-29 04:06:34'),
(23, 1, 'C', 3, 'C03', 2, 1, NULL, '2026-04-29 04:06:34'),
(24, 1, 'C', 4, 'C04', 2, 1, NULL, '2026-04-29 04:06:34'),
(25, 1, 'C', 5, 'C05', 2, 1, NULL, '2026-04-29 04:06:34'),
(26, 1, 'C', 6, 'C06', 2, 1, NULL, '2026-04-29 04:06:34'),
(27, 1, 'C', 7, 'C07', 2, 1, NULL, '2026-04-29 04:06:34'),
(28, 1, 'C', 8, 'C08', 2, 1, NULL, '2026-04-29 04:06:34'),
(29, 1, 'C', 9, 'C09', 2, 1, NULL, '2026-04-29 04:06:34'),
(30, 1, 'C', 10, 'C10', 2, 1, NULL, '2026-04-29 04:06:34'),
(31, 1, 'D', 1, 'D01', 3, 1, NULL, '2026-04-29 04:06:34'),
(32, 1, 'D', 2, 'D02', 3, 1, NULL, '2026-04-29 04:06:34'),
(33, 1, 'D', 3, 'D03', 3, 1, NULL, '2026-04-29 04:06:34'),
(34, 1, 'D', 4, 'D04', 3, 1, NULL, '2026-04-29 04:06:34'),
(35, 1, 'D', 5, 'D05', 3, 1, NULL, '2026-04-29 04:06:34'),
(36, 1, 'D', 6, 'D06', 3, 1, NULL, '2026-04-29 04:06:34'),
(37, 1, 'D', 7, 'D07', 3, 1, NULL, '2026-04-29 04:06:34'),
(38, 1, 'D', 8, 'D08', 3, 1, NULL, '2026-04-29 04:06:34'),
(39, 1, 'D', 9, 'D09', 3, 1, NULL, '2026-04-29 04:06:34'),
(40, 1, 'D', 10, 'D10', 3, 1, NULL, '2026-04-29 04:06:34'),
(41, 1, 'E', 1, 'E01', 3, 1, NULL, '2026-04-29 04:06:34'),
(42, 1, 'E', 2, 'E02', 3, 1, NULL, '2026-04-29 04:06:34'),
(43, 1, 'E', 3, 'E03', 3, 1, NULL, '2026-04-29 04:06:34'),
(44, 1, 'E', 4, 'E04', 3, 1, NULL, '2026-04-29 04:06:34'),
(45, 1, 'E', 5, 'E05', 3, 1, NULL, '2026-04-29 04:06:34'),
(46, 1, 'E', 6, 'E06', 3, 1, NULL, '2026-04-29 04:06:34'),
(47, 1, 'E', 7, 'E07', 3, 1, NULL, '2026-04-29 04:06:34'),
(48, 1, 'E', 8, 'E08', 3, 1, NULL, '2026-04-29 04:06:34'),
(49, 1, 'E', 9, 'E09', 3, 1, NULL, '2026-04-29 04:06:34'),
(50, 1, 'E', 10, 'E10', 3, 1, NULL, '2026-04-29 04:06:34'),
(51, 1, 'F', 1, 'F01', 1, 1, NULL, '2026-04-29 04:06:35'),
(52, 1, 'F', 2, 'F02', 1, 1, NULL, '2026-04-29 04:06:35'),
(53, 1, 'F', 3, 'F03', 1, 1, NULL, '2026-04-29 04:06:35'),
(54, 1, 'F', 4, 'F04', 1, 1, NULL, '2026-04-29 04:06:35'),
(55, 1, 'F', 5, 'F05', 1, 1, NULL, '2026-04-29 04:06:35'),
(56, 1, 'F', 6, 'F06', 1, 1, NULL, '2026-04-29 04:06:35'),
(57, 1, 'F', 7, 'F07', 1, 1, NULL, '2026-04-29 04:06:35'),
(58, 1, 'F', 8, 'F08', 1, 1, NULL, '2026-04-29 04:06:35'),
(59, 1, 'F', 9, 'F09', 1, 1, NULL, '2026-04-29 04:06:35'),
(60, 1, 'F', 10, 'F10', 1, 1, NULL, '2026-04-29 04:06:35'),
(61, 1, 'G', 1, 'G01', 1, 1, NULL, '2026-04-29 04:06:35'),
(62, 1, 'G', 2, 'G02', 1, 1, NULL, '2026-04-29 04:06:35'),
(63, 1, 'G', 3, 'G03', 1, 1, NULL, '2026-04-29 04:06:35'),
(64, 1, 'G', 4, 'G04', 1, 1, NULL, '2026-04-29 04:06:35'),
(65, 1, 'G', 5, 'G05', 1, 1, NULL, '2026-04-29 04:06:35'),
(66, 1, 'G', 6, 'G06', 1, 1, NULL, '2026-04-29 04:06:35'),
(67, 1, 'G', 7, 'G07', 1, 1, NULL, '2026-04-29 04:06:35'),
(68, 1, 'G', 8, 'G08', 1, 1, NULL, '2026-04-29 04:06:35'),
(69, 1, 'G', 9, 'G09', 1, 1, NULL, '2026-04-29 04:06:35'),
(70, 1, 'G', 10, 'G10', 1, 1, NULL, '2026-04-29 04:06:35'),
(71, 1, 'H', 1, 'H01', 1, 1, NULL, '2026-04-29 04:06:35'),
(72, 1, 'H', 2, 'H02', 1, 1, NULL, '2026-04-29 04:06:35'),
(73, 1, 'H', 3, 'H03', 1, 1, NULL, '2026-04-29 04:06:35'),
(74, 1, 'H', 4, 'H04', 1, 1, NULL, '2026-04-29 04:06:35'),
(75, 1, 'H', 5, 'H05', 1, 1, NULL, '2026-04-29 04:06:35'),
(76, 1, 'H', 6, 'H06', 1, 1, NULL, '2026-04-29 04:06:36'),
(77, 1, 'H', 7, 'H07', 1, 1, NULL, '2026-04-29 04:06:36'),
(78, 1, 'H', 8, 'H08', 1, 1, NULL, '2026-04-29 04:06:36'),
(79, 1, 'H', 9, 'H09', 1, 1, NULL, '2026-04-29 04:06:36'),
(80, 1, 'H', 10, 'H10', 1, 1, NULL, '2026-04-29 04:06:36'),
(81, 2, 'A', 1, 'A01', 2, 1, NULL, '2026-04-29 04:06:36'),
(82, 2, 'A', 2, 'A02', 2, 1, NULL, '2026-04-29 04:06:36'),
(83, 2, 'A', 3, 'A03', 2, 1, NULL, '2026-04-29 04:06:36'),
(84, 2, 'A', 4, 'A04', 2, 1, NULL, '2026-04-29 04:06:36'),
(85, 2, 'A', 5, 'A05', 2, 1, NULL, '2026-04-29 04:06:36'),
(86, 2, 'A', 6, 'A06', 2, 1, NULL, '2026-04-29 04:06:36'),
(87, 2, 'A', 7, 'A07', 2, 1, NULL, '2026-04-29 04:06:36'),
(88, 2, 'A', 8, 'A08', 2, 1, NULL, '2026-04-29 04:06:36'),
(89, 2, 'A', 9, 'A09', 2, 1, NULL, '2026-04-29 04:06:36'),
(90, 2, 'A', 10, 'A10', 2, 1, NULL, '2026-04-29 04:06:36'),
(91, 2, 'A', 11, 'A11', 2, 1, NULL, '2026-04-29 04:06:36'),
(92, 2, 'A', 12, 'A12', 2, 1, NULL, '2026-04-29 04:06:36'),
(93, 2, 'B', 1, 'B01', 2, 1, NULL, '2026-04-29 04:06:36'),
(94, 2, 'B', 2, 'B02', 2, 1, NULL, '2026-04-29 04:06:36'),
(95, 2, 'B', 3, 'B03', 2, 1, NULL, '2026-04-29 04:06:36'),
(96, 2, 'B', 4, 'B04', 2, 1, NULL, '2026-04-29 04:06:36'),
(97, 2, 'B', 5, 'B05', 2, 1, NULL, '2026-04-29 04:06:36'),
(98, 2, 'B', 6, 'B06', 2, 1, NULL, '2026-04-29 04:06:36'),
(99, 2, 'B', 7, 'B07', 2, 1, NULL, '2026-04-29 04:06:36'),
(100, 2, 'B', 8, 'B08', 2, 1, NULL, '2026-04-29 04:06:36'),
(101, 2, 'B', 9, 'B09', 2, 1, NULL, '2026-04-29 04:06:36'),
(102, 2, 'B', 10, 'B10', 2, 1, NULL, '2026-04-29 04:06:36'),
(103, 2, 'B', 11, 'B11', 2, 1, NULL, '2026-04-29 04:06:36'),
(104, 2, 'B', 12, 'B12', 2, 1, NULL, '2026-04-29 04:06:36'),
(105, 2, 'C', 1, 'C01', 2, 1, NULL, '2026-04-29 04:06:36'),
(106, 2, 'C', 2, 'C02', 2, 1, NULL, '2026-04-29 04:06:37'),
(107, 2, 'C', 3, 'C03', 2, 1, NULL, '2026-04-29 04:06:37'),
(108, 2, 'C', 4, 'C04', 2, 1, NULL, '2026-04-29 04:06:37'),
(109, 2, 'C', 5, 'C05', 2, 1, NULL, '2026-04-29 04:06:37'),
(110, 2, 'C', 6, 'C06', 2, 1, NULL, '2026-04-29 04:06:37'),
(111, 2, 'C', 7, 'C07', 2, 1, NULL, '2026-04-29 04:06:37'),
(112, 2, 'C', 8, 'C08', 2, 1, NULL, '2026-04-29 04:06:37'),
(113, 2, 'C', 9, 'C09', 2, 1, NULL, '2026-04-29 04:06:37'),
(114, 2, 'C', 10, 'C10', 2, 1, NULL, '2026-04-29 04:06:37'),
(115, 2, 'C', 11, 'C11', 2, 1, NULL, '2026-04-29 04:06:37'),
(116, 2, 'C', 12, 'C12', 2, 1, NULL, '2026-04-29 04:06:37'),
(117, 2, 'D', 1, 'D01', 3, 1, NULL, '2026-04-29 04:06:37'),
(118, 2, 'D', 2, 'D02', 3, 1, NULL, '2026-04-29 04:06:37'),
(119, 2, 'D', 3, 'D03', 3, 1, NULL, '2026-04-29 04:06:37'),
(120, 2, 'D', 4, 'D04', 3, 1, NULL, '2026-04-29 04:06:37'),
(121, 2, 'D', 5, 'D05', 3, 1, NULL, '2026-04-29 04:06:37'),
(122, 2, 'D', 6, 'D06', 3, 1, NULL, '2026-04-29 04:06:37'),
(123, 2, 'D', 7, 'D07', 3, 1, NULL, '2026-04-29 04:06:37'),
(124, 2, 'D', 8, 'D08', 3, 1, NULL, '2026-04-29 04:06:37'),
(125, 2, 'D', 9, 'D09', 3, 1, NULL, '2026-04-29 04:06:37'),
(126, 2, 'D', 10, 'D10', 3, 1, NULL, '2026-04-29 04:06:37'),
(127, 2, 'D', 11, 'D11', 3, 1, NULL, '2026-04-29 04:06:37'),
(128, 2, 'D', 12, 'D12', 3, 1, NULL, '2026-04-29 04:06:37'),
(129, 2, 'E', 1, 'E01', 3, 1, NULL, '2026-04-29 04:06:37'),
(130, 2, 'E', 2, 'E02', 3, 1, NULL, '2026-04-29 04:06:37'),
(131, 2, 'E', 3, 'E03', 3, 1, NULL, '2026-04-29 04:06:37'),
(132, 2, 'E', 4, 'E04', 3, 1, NULL, '2026-04-29 04:06:37'),
(133, 2, 'E', 5, 'E05', 3, 1, NULL, '2026-04-29 04:06:37'),
(134, 2, 'E', 6, 'E06', 3, 1, NULL, '2026-04-29 04:06:37'),
(135, 2, 'E', 7, 'E07', 3, 1, NULL, '2026-04-29 04:06:37'),
(136, 2, 'E', 8, 'E08', 3, 1, NULL, '2026-04-29 04:06:37'),
(137, 2, 'E', 9, 'E09', 3, 1, NULL, '2026-04-29 04:06:37'),
(138, 2, 'E', 10, 'E10', 3, 1, NULL, '2026-04-29 04:06:37'),
(139, 2, 'E', 11, 'E11', 3, 1, NULL, '2026-04-29 04:06:37'),
(140, 2, 'E', 12, 'E12', 3, 1, NULL, '2026-04-29 04:06:37'),
(141, 2, 'F', 1, 'F01', 3, 1, NULL, '2026-04-29 04:06:37'),
(142, 2, 'F', 2, 'F02', 3, 1, NULL, '2026-04-29 04:06:37'),
(143, 2, 'F', 3, 'F03', 3, 1, NULL, '2026-04-29 04:06:38'),
(144, 2, 'F', 4, 'F04', 3, 1, NULL, '2026-04-29 04:06:38'),
(145, 2, 'F', 5, 'F05', 3, 1, NULL, '2026-04-29 04:06:38'),
(146, 2, 'F', 6, 'F06', 3, 1, NULL, '2026-04-29 04:06:38'),
(147, 2, 'F', 7, 'F07', 3, 1, NULL, '2026-04-29 04:06:38'),
(148, 2, 'F', 8, 'F08', 3, 1, NULL, '2026-04-29 04:06:38'),
(149, 2, 'F', 9, 'F09', 3, 1, NULL, '2026-04-29 04:06:38'),
(150, 2, 'F', 10, 'F10', 3, 1, NULL, '2026-04-29 04:06:38'),
(151, 2, 'F', 11, 'F11', 3, 1, NULL, '2026-04-29 04:06:38'),
(152, 2, 'F', 12, 'F12', 3, 1, NULL, '2026-04-29 04:06:38'),
(153, 2, 'G', 1, 'G01', 1, 1, NULL, '2026-04-29 04:06:38'),
(154, 2, 'G', 2, 'G02', 1, 1, NULL, '2026-04-29 04:06:38'),
(155, 2, 'G', 3, 'G03', 1, 1, NULL, '2026-04-29 04:06:38'),
(156, 2, 'G', 4, 'G04', 1, 1, NULL, '2026-04-29 04:06:38'),
(157, 2, 'G', 5, 'G05', 1, 1, NULL, '2026-04-29 04:06:38'),
(158, 2, 'G', 6, 'G06', 1, 1, NULL, '2026-04-29 04:06:38'),
(159, 2, 'G', 7, 'G07', 1, 1, NULL, '2026-04-29 04:06:38'),
(160, 2, 'G', 8, 'G08', 1, 1, NULL, '2026-04-29 04:06:38'),
(161, 2, 'G', 9, 'G09', 1, 1, NULL, '2026-04-29 04:06:39'),
(162, 2, 'G', 10, 'G10', 1, 1, NULL, '2026-04-29 04:06:39'),
(163, 2, 'G', 11, 'G11', 1, 1, NULL, '2026-04-29 04:06:39'),
(164, 2, 'G', 12, 'G12', 1, 1, NULL, '2026-04-29 04:06:39'),
(165, 2, 'H', 1, 'H01', 1, 1, NULL, '2026-04-29 04:06:39'),
(166, 2, 'H', 2, 'H02', 1, 1, NULL, '2026-04-29 04:06:39'),
(167, 2, 'H', 3, 'H03', 1, 1, NULL, '2026-04-29 04:06:39'),
(168, 2, 'H', 4, 'H04', 1, 1, NULL, '2026-04-29 04:06:39'),
(169, 2, 'H', 5, 'H05', 1, 1, NULL, '2026-04-29 04:06:39'),
(170, 2, 'H', 6, 'H06', 1, 1, NULL, '2026-04-29 04:06:39'),
(171, 2, 'H', 7, 'H07', 1, 1, NULL, '2026-04-29 04:06:39'),
(172, 2, 'H', 8, 'H08', 1, 1, NULL, '2026-04-29 04:06:39'),
(173, 2, 'H', 9, 'H09', 1, 1, NULL, '2026-04-29 04:06:39'),
(174, 2, 'H', 10, 'H10', 1, 1, NULL, '2026-04-29 04:06:39'),
(175, 2, 'H', 11, 'H11', 1, 1, NULL, '2026-04-29 04:06:39'),
(176, 2, 'H', 12, 'H12', 1, 1, NULL, '2026-04-29 04:06:39'),
(177, 2, 'I', 1, 'I01', 1, 1, NULL, '2026-04-29 04:06:39'),
(178, 2, 'I', 2, 'I02', 1, 1, NULL, '2026-04-29 04:06:39'),
(179, 2, 'I', 3, 'I03', 1, 1, NULL, '2026-04-29 04:06:39'),
(180, 2, 'I', 4, 'I04', 1, 1, NULL, '2026-04-29 04:06:39'),
(181, 2, 'I', 5, 'I05', 1, 1, NULL, '2026-04-29 04:06:39'),
(182, 2, 'I', 6, 'I06', 1, 1, NULL, '2026-04-29 04:06:39'),
(183, 2, 'I', 7, 'I07', 1, 1, NULL, '2026-04-29 04:06:39'),
(184, 2, 'I', 8, 'I08', 1, 1, NULL, '2026-04-29 04:06:39'),
(185, 2, 'I', 9, 'I09', 1, 1, NULL, '2026-04-29 04:06:39'),
(186, 2, 'I', 10, 'I10', 1, 1, NULL, '2026-04-29 04:06:39'),
(187, 2, 'I', 11, 'I11', 1, 1, NULL, '2026-04-29 04:06:39'),
(188, 2, 'I', 12, 'I12', 1, 1, NULL, '2026-04-29 04:06:39'),
(189, 2, 'J', 1, 'J01', 1, 1, NULL, '2026-04-29 04:06:39'),
(190, 2, 'J', 2, 'J02', 1, 1, NULL, '2026-04-29 04:06:39'),
(191, 2, 'J', 3, 'J03', 1, 1, NULL, '2026-04-29 04:06:39'),
(192, 2, 'J', 4, 'J04', 1, 1, NULL, '2026-04-29 04:06:39'),
(193, 2, 'J', 5, 'J05', 1, 1, NULL, '2026-04-29 04:06:39'),
(194, 2, 'J', 6, 'J06', 1, 1, NULL, '2026-04-29 04:06:39'),
(195, 2, 'J', 7, 'J07', 1, 1, NULL, '2026-04-29 04:06:39'),
(196, 2, 'J', 8, 'J08', 1, 1, NULL, '2026-04-29 04:06:40'),
(197, 2, 'J', 9, 'J09', 1, 1, NULL, '2026-04-29 04:06:40'),
(198, 2, 'J', 10, 'J10', 1, 1, NULL, '2026-04-29 04:06:40'),
(199, 2, 'J', 11, 'J11', 1, 1, NULL, '2026-04-29 04:06:40'),
(200, 2, 'J', 12, 'J12', 1, 1, NULL, '2026-04-29 04:06:40'),
(201, 3, 'A', 1, 'A01', 2, 1, NULL, '2026-04-29 04:06:40'),
(202, 3, 'A', 2, 'A02', 2, 1, NULL, '2026-04-29 04:06:40'),
(203, 3, 'A', 3, 'A03', 2, 1, NULL, '2026-04-29 04:06:40'),
(204, 3, 'A', 4, 'A04', 2, 1, NULL, '2026-04-29 04:06:40'),
(205, 3, 'A', 5, 'A05', 2, 1, NULL, '2026-04-29 04:06:40'),
(206, 3, 'A', 6, 'A06', 2, 1, NULL, '2026-04-29 04:06:40'),
(207, 3, 'A', 7, 'A07', 2, 1, NULL, '2026-04-29 04:06:40'),
(208, 3, 'A', 8, 'A08', 2, 1, NULL, '2026-04-29 04:06:40'),
(209, 3, 'B', 1, 'B01', 2, 1, NULL, '2026-04-29 04:06:40'),
(210, 3, 'B', 2, 'B02', 2, 1, NULL, '2026-04-29 04:06:40'),
(211, 3, 'B', 3, 'B03', 2, 1, NULL, '2026-04-29 04:06:40'),
(212, 3, 'B', 4, 'B04', 2, 1, NULL, '2026-04-29 04:06:40'),
(213, 3, 'B', 5, 'B05', 2, 1, NULL, '2026-04-29 04:06:40'),
(214, 3, 'B', 6, 'B06', 2, 1, NULL, '2026-04-29 04:06:40'),
(215, 3, 'B', 7, 'B07', 2, 1, NULL, '2026-04-29 04:06:40'),
(216, 3, 'B', 8, 'B08', 2, 1, NULL, '2026-04-29 04:06:40'),
(217, 3, 'C', 1, 'C01', 2, 1, NULL, '2026-04-29 04:06:40'),
(218, 3, 'C', 2, 'C02', 2, 1, NULL, '2026-04-29 04:06:41'),
(219, 3, 'C', 3, 'C03', 2, 1, NULL, '2026-04-29 04:06:41'),
(220, 3, 'C', 4, 'C04', 2, 1, NULL, '2026-04-29 04:06:41'),
(221, 3, 'C', 5, 'C05', 2, 1, NULL, '2026-04-29 04:06:41'),
(222, 3, 'C', 6, 'C06', 2, 1, NULL, '2026-04-29 04:06:41'),
(223, 3, 'C', 7, 'C07', 2, 1, NULL, '2026-04-29 04:06:41'),
(224, 3, 'C', 8, 'C08', 2, 1, NULL, '2026-04-29 04:06:41'),
(225, 3, 'D', 1, 'D01', 3, 1, NULL, '2026-04-29 04:06:41'),
(226, 3, 'D', 2, 'D02', 3, 1, NULL, '2026-04-29 04:06:41'),
(227, 3, 'D', 3, 'D03', 3, 1, NULL, '2026-04-29 04:06:41'),
(228, 3, 'D', 4, 'D04', 3, 1, NULL, '2026-04-29 04:06:41'),
(229, 3, 'D', 5, 'D05', 3, 1, NULL, '2026-04-29 04:06:41'),
(230, 3, 'D', 6, 'D06', 3, 1, NULL, '2026-04-29 04:06:41'),
(231, 3, 'D', 7, 'D07', 3, 1, NULL, '2026-04-29 04:06:41'),
(232, 3, 'D', 8, 'D08', 3, 1, NULL, '2026-04-29 04:06:41'),
(233, 3, 'E', 1, 'E01', 3, 1, NULL, '2026-04-29 04:06:41'),
(234, 3, 'E', 2, 'E02', 3, 1, NULL, '2026-04-29 04:06:41'),
(235, 3, 'E', 3, 'E03', 3, 1, NULL, '2026-04-29 04:06:41'),
(236, 3, 'E', 4, 'E04', 3, 1, NULL, '2026-04-29 04:06:41'),
(237, 3, 'E', 5, 'E05', 3, 1, NULL, '2026-04-29 04:06:41'),
(238, 3, 'E', 6, 'E06', 3, 1, NULL, '2026-04-29 04:06:41'),
(239, 3, 'E', 7, 'E07', 3, 1, NULL, '2026-04-29 04:06:41'),
(240, 3, 'E', 8, 'E08', 3, 1, NULL, '2026-04-29 04:06:41'),
(241, 3, 'F', 1, 'F01', 1, 1, NULL, '2026-04-29 04:06:41'),
(242, 3, 'F', 2, 'F02', 1, 1, NULL, '2026-04-29 04:06:41'),
(243, 3, 'F', 3, 'F03', 1, 1, NULL, '2026-04-29 04:06:41'),
(244, 3, 'F', 4, 'F04', 1, 1, NULL, '2026-04-29 04:06:41'),
(245, 3, 'F', 5, 'F05', 1, 1, NULL, '2026-04-29 04:06:41'),
(246, 3, 'F', 6, 'F06', 1, 1, NULL, '2026-04-29 04:06:41'),
(247, 3, 'F', 7, 'F07', 1, 1, NULL, '2026-04-29 04:06:41'),
(248, 3, 'F', 8, 'F08', 1, 1, NULL, '2026-04-29 04:06:41'),
(249, 3, 'G', 1, 'G01', 1, 1, NULL, '2026-04-29 04:06:41'),
(250, 3, 'G', 2, 'G02', 1, 1, NULL, '2026-04-29 04:06:41'),
(251, 3, 'G', 3, 'G03', 1, 1, NULL, '2026-04-29 04:06:41'),
(252, 3, 'G', 4, 'G04', 1, 1, NULL, '2026-04-29 04:06:41'),
(253, 3, 'G', 5, 'G05', 1, 1, NULL, '2026-04-29 04:06:41'),
(254, 3, 'G', 6, 'G06', 1, 1, NULL, '2026-04-29 04:06:41'),
(255, 3, 'G', 7, 'G07', 1, 1, NULL, '2026-04-29 04:06:42'),
(256, 3, 'G', 8, 'G08', 1, 1, NULL, '2026-04-29 04:06:42'),
(257, 3, 'H', 1, 'H01', 1, 1, NULL, '2026-04-29 04:06:42'),
(258, 3, 'H', 2, 'H02', 1, 1, NULL, '2026-04-29 04:06:42'),
(259, 3, 'H', 3, 'H03', 1, 1, NULL, '2026-04-29 04:06:42'),
(260, 3, 'H', 4, 'H04', 1, 1, NULL, '2026-04-29 04:06:42'),
(261, 3, 'H', 5, 'H05', 1, 1, NULL, '2026-04-29 04:06:42'),
(262, 3, 'H', 6, 'H06', 1, 1, NULL, '2026-04-29 04:06:42'),
(263, 3, 'H', 7, 'H07', 1, 1, NULL, '2026-04-29 04:06:42'),
(264, 3, 'H', 8, 'H08', 1, 1, NULL, '2026-04-29 04:06:42'),
(265, 4, 'A', 1, 'A01', 2, 1, NULL, '2026-04-29 04:06:42'),
(266, 4, 'A', 2, 'A02', 2, 1, NULL, '2026-04-29 04:06:42'),
(267, 4, 'A', 3, 'A03', 2, 1, NULL, '2026-04-29 04:06:42'),
(268, 4, 'A', 4, 'A04', 2, 1, NULL, '2026-04-29 04:06:42'),
(269, 4, 'A', 5, 'A05', 2, 1, NULL, '2026-04-29 04:06:42'),
(270, 4, 'A', 6, 'A06', 2, 1, NULL, '2026-04-29 04:06:42'),
(271, 4, 'A', 7, 'A07', 2, 1, NULL, '2026-04-29 04:06:42'),
(272, 4, 'A', 8, 'A08', 2, 1, NULL, '2026-04-29 04:06:42'),
(273, 4, 'A', 9, 'A09', 2, 1, NULL, '2026-04-29 04:06:42'),
(274, 4, 'A', 10, 'A10', 2, 1, NULL, '2026-04-29 04:06:42'),
(275, 4, 'A', 11, 'A11', 2, 1, NULL, '2026-04-29 04:06:42'),
(276, 4, 'A', 12, 'A12', 2, 1, NULL, '2026-04-29 04:06:42'),
(277, 4, 'A', 13, 'A13', 2, 1, NULL, '2026-04-29 04:06:42'),
(278, 4, 'A', 14, 'A14', 2, 1, NULL, '2026-04-29 04:06:42'),
(279, 4, 'B', 1, 'B01', 2, 1, NULL, '2026-04-29 04:06:42'),
(280, 4, 'B', 2, 'B02', 2, 1, NULL, '2026-04-29 04:06:43'),
(281, 4, 'B', 3, 'B03', 2, 1, NULL, '2026-04-29 04:06:43'),
(282, 4, 'B', 4, 'B04', 2, 1, NULL, '2026-04-29 04:06:43'),
(283, 4, 'B', 5, 'B05', 2, 1, NULL, '2026-04-29 04:06:43'),
(284, 4, 'B', 6, 'B06', 2, 1, NULL, '2026-04-29 04:06:43'),
(285, 4, 'B', 7, 'B07', 2, 1, NULL, '2026-04-29 04:06:43'),
(286, 4, 'B', 8, 'B08', 2, 1, NULL, '2026-04-29 04:06:43'),
(287, 4, 'B', 9, 'B09', 2, 1, NULL, '2026-04-29 04:06:43'),
(288, 4, 'B', 10, 'B10', 2, 1, NULL, '2026-04-29 04:06:43'),
(289, 4, 'B', 11, 'B11', 2, 1, NULL, '2026-04-29 04:06:43'),
(290, 4, 'B', 12, 'B12', 2, 1, NULL, '2026-04-29 04:06:43'),
(291, 4, 'B', 13, 'B13', 2, 1, NULL, '2026-04-29 04:06:43'),
(292, 4, 'B', 14, 'B14', 2, 1, NULL, '2026-04-29 04:06:43'),
(293, 4, 'C', 1, 'C01', 2, 1, NULL, '2026-04-29 04:06:43'),
(294, 4, 'C', 2, 'C02', 2, 1, NULL, '2026-04-29 04:06:43'),
(295, 4, 'C', 3, 'C03', 2, 1, NULL, '2026-04-29 04:06:43'),
(296, 4, 'C', 4, 'C04', 2, 1, NULL, '2026-04-29 04:06:43'),
(297, 4, 'C', 5, 'C05', 2, 1, NULL, '2026-04-29 04:06:43'),
(298, 4, 'C', 6, 'C06', 2, 1, NULL, '2026-04-29 04:06:43'),
(299, 4, 'C', 7, 'C07', 2, 1, NULL, '2026-04-29 04:06:43'),
(300, 4, 'C', 8, 'C08', 2, 1, NULL, '2026-04-29 04:06:43'),
(301, 4, 'C', 9, 'C09', 2, 1, NULL, '2026-04-29 04:06:43'),
(302, 4, 'C', 10, 'C10', 2, 1, NULL, '2026-04-29 04:06:43'),
(303, 4, 'C', 11, 'C11', 2, 1, NULL, '2026-04-29 04:06:43'),
(304, 4, 'C', 12, 'C12', 2, 1, NULL, '2026-04-29 04:06:43'),
(305, 4, 'C', 13, 'C13', 2, 1, NULL, '2026-04-29 04:06:43'),
(306, 4, 'C', 14, 'C14', 2, 1, NULL, '2026-04-29 04:06:43'),
(307, 4, 'D', 1, 'D01', 1, 1, NULL, '2026-04-29 04:06:43'),
(308, 4, 'D', 2, 'D02', 1, 1, NULL, '2026-04-29 04:06:43'),
(309, 4, 'D', 3, 'D03', 1, 1, NULL, '2026-04-29 04:06:43'),
(310, 4, 'D', 4, 'D04', 1, 1, NULL, '2026-04-29 04:06:43'),
(311, 4, 'D', 5, 'D05', 1, 1, NULL, '2026-04-29 04:06:43'),
(312, 4, 'D', 6, 'D06', 1, 1, NULL, '2026-04-29 04:06:43'),
(313, 4, 'D', 7, 'D07', 1, 1, NULL, '2026-04-29 04:06:43'),
(314, 4, 'D', 8, 'D08', 1, 1, NULL, '2026-04-29 04:06:43'),
(315, 4, 'D', 9, 'D09', 1, 1, NULL, '2026-04-29 04:06:43'),
(316, 4, 'D', 10, 'D10', 1, 1, NULL, '2026-04-29 04:06:44'),
(317, 4, 'D', 11, 'D11', 1, 1, NULL, '2026-04-29 04:06:44'),
(318, 4, 'D', 12, 'D12', 1, 1, NULL, '2026-04-29 04:06:44'),
(319, 4, 'D', 13, 'D13', 1, 1, NULL, '2026-04-29 04:06:44'),
(320, 4, 'D', 14, 'D14', 1, 1, NULL, '2026-04-29 04:06:44'),
(321, 4, 'E', 1, 'E01', 3, 1, NULL, '2026-04-29 04:06:44'),
(322, 4, 'E', 2, 'E02', 3, 1, NULL, '2026-04-29 04:06:44'),
(323, 4, 'E', 3, 'E03', 3, 1, NULL, '2026-04-29 04:06:44'),
(324, 4, 'E', 4, 'E04', 3, 1, NULL, '2026-04-29 04:06:44'),
(325, 4, 'E', 5, 'E05', 3, 1, NULL, '2026-04-29 04:06:44'),
(326, 4, 'E', 6, 'E06', 3, 1, NULL, '2026-04-29 04:06:44'),
(327, 4, 'E', 7, 'E07', 3, 1, NULL, '2026-04-29 04:06:44'),
(328, 4, 'E', 8, 'E08', 3, 1, NULL, '2026-04-29 04:06:44'),
(329, 4, 'E', 9, 'E09', 3, 1, NULL, '2026-04-29 04:06:44'),
(330, 4, 'E', 10, 'E10', 3, 1, NULL, '2026-04-29 04:06:44'),
(331, 4, 'E', 11, 'E11', 3, 1, NULL, '2026-04-29 04:06:44'),
(332, 4, 'E', 12, 'E12', 3, 1, NULL, '2026-04-29 04:06:44'),
(333, 4, 'E', 13, 'E13', 3, 1, NULL, '2026-04-29 04:06:44'),
(334, 4, 'E', 14, 'E14', 3, 1, NULL, '2026-04-29 04:06:44'),
(335, 4, 'F', 1, 'F01', 3, 1, NULL, '2026-04-29 04:06:44'),
(336, 4, 'F', 2, 'F02', 3, 1, NULL, '2026-04-29 04:06:45'),
(337, 4, 'F', 3, 'F03', 3, 1, NULL, '2026-04-29 04:06:45'),
(338, 4, 'F', 4, 'F04', 3, 1, NULL, '2026-04-29 04:06:45'),
(339, 4, 'F', 5, 'F05', 3, 1, NULL, '2026-04-29 04:06:45'),
(340, 4, 'F', 6, 'F06', 3, 1, NULL, '2026-04-29 04:06:45'),
(341, 4, 'F', 7, 'F07', 3, 1, NULL, '2026-04-29 04:06:45'),
(342, 4, 'F', 8, 'F08', 3, 1, NULL, '2026-04-29 04:06:45'),
(343, 4, 'F', 9, 'F09', 3, 1, NULL, '2026-04-29 04:06:45'),
(344, 4, 'F', 10, 'F10', 3, 1, NULL, '2026-04-29 04:06:45'),
(345, 4, 'F', 11, 'F11', 3, 1, NULL, '2026-04-29 04:06:45'),
(346, 4, 'F', 12, 'F12', 3, 1, NULL, '2026-04-29 04:06:45'),
(347, 4, 'F', 13, 'F13', 3, 1, NULL, '2026-04-29 04:06:45'),
(348, 4, 'F', 14, 'F14', 3, 1, NULL, '2026-04-29 04:06:45'),
(349, 4, 'G', 1, 'G01', 3, 1, NULL, '2026-04-29 04:06:45'),
(350, 4, 'G', 2, 'G02', 3, 1, NULL, '2026-04-29 04:06:45'),
(351, 4, 'G', 3, 'G03', 3, 1, NULL, '2026-04-29 04:06:45'),
(352, 4, 'G', 4, 'G04', 3, 1, NULL, '2026-04-29 04:06:45'),
(353, 4, 'G', 5, 'G05', 3, 1, NULL, '2026-04-29 04:06:45'),
(354, 4, 'G', 6, 'G06', 3, 1, NULL, '2026-04-29 04:06:45'),
(355, 4, 'G', 7, 'G07', 3, 1, NULL, '2026-04-29 04:06:45'),
(356, 4, 'G', 8, 'G08', 3, 1, NULL, '2026-04-29 04:06:45'),
(357, 4, 'G', 9, 'G09', 3, 1, NULL, '2026-04-29 04:06:45'),
(358, 4, 'G', 10, 'G10', 3, 1, NULL, '2026-04-29 04:06:45'),
(359, 4, 'G', 11, 'G11', 3, 1, NULL, '2026-04-29 04:06:45'),
(360, 4, 'G', 12, 'G12', 3, 1, NULL, '2026-04-29 04:06:45'),
(361, 4, 'G', 13, 'G13', 3, 1, NULL, '2026-04-29 04:06:45'),
(362, 4, 'G', 14, 'G14', 3, 1, NULL, '2026-04-29 04:06:45'),
(363, 4, 'H', 1, 'H01', 1, 1, NULL, '2026-04-29 04:06:45'),
(364, 4, 'H', 2, 'H02', 1, 1, NULL, '2026-04-29 04:06:45'),
(365, 4, 'H', 3, 'H03', 1, 1, NULL, '2026-04-29 04:06:45'),
(366, 4, 'H', 4, 'H04', 1, 1, NULL, '2026-04-29 04:06:45'),
(367, 4, 'H', 5, 'H05', 1, 1, NULL, '2026-04-29 04:06:45'),
(368, 4, 'H', 6, 'H06', 1, 1, NULL, '2026-04-29 04:06:46'),
(369, 4, 'H', 7, 'H07', 1, 1, NULL, '2026-04-29 04:06:46'),
(370, 4, 'H', 8, 'H08', 1, 1, NULL, '2026-04-29 04:06:46'),
(371, 4, 'H', 9, 'H09', 1, 1, NULL, '2026-04-29 04:06:46'),
(372, 4, 'H', 10, 'H10', 1, 1, NULL, '2026-04-29 04:06:46'),
(373, 4, 'H', 11, 'H11', 1, 1, NULL, '2026-04-29 04:06:46'),
(374, 4, 'H', 12, 'H12', 1, 1, NULL, '2026-04-29 04:06:46'),
(375, 4, 'H', 13, 'H13', 1, 1, NULL, '2026-04-29 04:06:46'),
(376, 4, 'H', 14, 'H14', 1, 1, NULL, '2026-04-29 04:06:46'),
(377, 4, 'I', 1, 'I01', 1, 1, NULL, '2026-04-29 04:06:46'),
(378, 4, 'I', 2, 'I02', 1, 1, NULL, '2026-04-29 04:06:46'),
(379, 4, 'I', 3, 'I03', 1, 1, NULL, '2026-04-29 04:06:46'),
(380, 4, 'I', 4, 'I04', 1, 1, NULL, '2026-04-29 04:06:46'),
(381, 4, 'I', 5, 'I05', 1, 1, NULL, '2026-04-29 04:06:46'),
(382, 4, 'I', 6, 'I06', 1, 1, NULL, '2026-04-29 04:06:46'),
(383, 4, 'I', 7, 'I07', 1, 1, NULL, '2026-04-29 04:06:46'),
(384, 4, 'I', 8, 'I08', 1, 1, NULL, '2026-04-29 04:06:46'),
(385, 4, 'I', 9, 'I09', 1, 1, NULL, '2026-04-29 04:06:46'),
(386, 4, 'I', 10, 'I10', 1, 1, NULL, '2026-04-29 04:06:46'),
(387, 4, 'I', 11, 'I11', 1, 1, NULL, '2026-04-29 04:06:47'),
(388, 4, 'I', 12, 'I12', 1, 1, NULL, '2026-04-29 04:06:47'),
(389, 4, 'I', 13, 'I13', 1, 1, NULL, '2026-04-29 04:06:47'),
(390, 4, 'I', 14, 'I14', 1, 1, NULL, '2026-04-29 04:06:47'),
(391, 4, 'J', 1, 'J01', 1, 1, NULL, '2026-04-29 04:06:47'),
(392, 4, 'J', 2, 'J02', 1, 1, NULL, '2026-04-29 04:06:47'),
(393, 4, 'J', 3, 'J03', 1, 1, NULL, '2026-04-29 04:06:47'),
(394, 4, 'J', 4, 'J04', 1, 1, NULL, '2026-04-29 04:06:47'),
(395, 4, 'J', 5, 'J05', 1, 1, NULL, '2026-04-29 04:06:47'),
(396, 4, 'J', 6, 'J06', 1, 1, NULL, '2026-04-29 04:06:47'),
(397, 4, 'J', 7, 'J07', 1, 1, NULL, '2026-04-29 04:06:47'),
(398, 4, 'J', 8, 'J08', 1, 1, NULL, '2026-04-29 04:06:47'),
(399, 4, 'J', 9, 'J09', 1, 1, NULL, '2026-04-29 04:06:47'),
(400, 4, 'J', 10, 'J10', 1, 1, NULL, '2026-04-29 04:06:47'),
(401, 4, 'J', 11, 'J11', 1, 1, NULL, '2026-04-29 04:06:47'),
(402, 4, 'J', 12, 'J12', 1, 1, NULL, '2026-04-29 04:06:47'),
(403, 4, 'J', 13, 'J13', 1, 1, NULL, '2026-04-29 04:06:47'),
(404, 4, 'J', 14, 'J14', 1, 1, NULL, '2026-04-29 04:06:47'),
(405, 4, 'K', 1, 'K01', 1, 1, NULL, '2026-04-29 04:06:47'),
(406, 4, 'K', 2, 'K02', 1, 1, NULL, '2026-04-29 04:06:47'),
(407, 4, 'K', 3, 'K03', 1, 1, NULL, '2026-04-29 04:06:47'),
(408, 4, 'K', 4, 'K04', 1, 1, NULL, '2026-04-29 04:06:47'),
(409, 4, 'K', 5, 'K05', 1, 1, NULL, '2026-04-29 04:06:47'),
(410, 4, 'K', 6, 'K06', 1, 1, NULL, '2026-04-29 04:06:47'),
(411, 4, 'K', 7, 'K07', 1, 1, NULL, '2026-04-29 04:06:47'),
(412, 4, 'K', 8, 'K08', 1, 1, NULL, '2026-04-29 04:06:47'),
(413, 4, 'K', 9, 'K09', 1, 1, NULL, '2026-04-29 04:06:47'),
(414, 4, 'K', 10, 'K10', 1, 1, NULL, '2026-04-29 04:06:47'),
(415, 4, 'K', 11, 'K11', 1, 1, NULL, '2026-04-29 04:06:47'),
(416, 4, 'K', 12, 'K12', 1, 1, NULL, '2026-04-29 04:06:47'),
(417, 4, 'K', 13, 'K13', 1, 1, NULL, '2026-04-29 04:06:48'),
(418, 4, 'K', 14, 'K14', 1, 1, NULL, '2026-04-29 04:06:48'),
(419, 4, 'L', 1, 'L01', 1, 1, NULL, '2026-04-29 04:06:48'),
(420, 4, 'L', 2, 'L02', 1, 1, NULL, '2026-04-29 04:06:48'),
(421, 4, 'L', 3, 'L03', 1, 1, NULL, '2026-04-29 04:06:48'),
(422, 4, 'L', 4, 'L04', 1, 1, NULL, '2026-04-29 04:06:48'),
(423, 4, 'L', 5, 'L05', 1, 1, NULL, '2026-04-29 04:06:48'),
(424, 4, 'L', 6, 'L06', 1, 1, NULL, '2026-04-29 04:06:48'),
(425, 4, 'L', 7, 'L07', 1, 1, NULL, '2026-04-29 04:06:48'),
(426, 4, 'L', 8, 'L08', 1, 1, NULL, '2026-04-29 04:06:48'),
(427, 4, 'L', 9, 'L09', 1, 1, NULL, '2026-04-29 04:06:48'),
(428, 4, 'L', 10, 'L10', 1, 1, NULL, '2026-04-29 04:06:48'),
(429, 4, 'L', 11, 'L11', 1, 1, NULL, '2026-04-29 04:06:48'),
(430, 4, 'L', 12, 'L12', 1, 1, NULL, '2026-04-29 04:06:48'),
(431, 4, 'L', 13, 'L13', 1, 1, NULL, '2026-04-29 04:06:48'),
(432, 4, 'L', 14, 'L14', 1, 1, NULL, '2026-04-29 04:06:48'),
(433, 5, 'A', 1, 'A01', 2, 1, NULL, '2026-04-29 04:06:48'),
(434, 5, 'A', 2, 'A02', 2, 1, NULL, '2026-04-29 04:06:48'),
(435, 5, 'A', 3, 'A03', 2, 1, NULL, '2026-04-29 04:06:48'),
(436, 5, 'A', 4, 'A04', 2, 1, NULL, '2026-04-29 04:06:48'),
(437, 5, 'A', 5, 'A05', 2, 1, NULL, '2026-04-29 04:06:48'),
(438, 5, 'A', 6, 'A06', 2, 1, NULL, '2026-04-29 04:06:48'),
(439, 5, 'A', 7, 'A07', 2, 1, NULL, '2026-04-29 04:06:48'),
(440, 5, 'A', 8, 'A08', 2, 1, NULL, '2026-04-29 04:06:48'),
(441, 5, 'A', 9, 'A09', 2, 1, NULL, '2026-04-29 04:06:48'),
(442, 5, 'A', 10, 'A10', 2, 1, NULL, '2026-04-29 04:06:48'),
(443, 5, 'A', 11, 'A11', 2, 1, NULL, '2026-04-29 04:06:48'),
(444, 5, 'A', 12, 'A12', 2, 1, NULL, '2026-04-29 04:06:49'),
(445, 5, 'A', 13, 'A13', 2, 1, NULL, '2026-04-29 04:06:49'),
(446, 5, 'A', 14, 'A14', 2, 1, NULL, '2026-04-29 04:06:49'),
(447, 5, 'A', 15, 'A15', 2, 1, NULL, '2026-04-29 04:06:49'),
(448, 5, 'B', 1, 'B01', 2, 1, NULL, '2026-04-29 04:06:49'),
(449, 5, 'B', 2, 'B02', 2, 1, NULL, '2026-04-29 04:06:49'),
(450, 5, 'B', 3, 'B03', 2, 1, NULL, '2026-04-29 04:06:49'),
(451, 5, 'B', 4, 'B04', 2, 1, NULL, '2026-04-29 04:06:49'),
(452, 5, 'B', 5, 'B05', 2, 1, NULL, '2026-04-29 04:06:49'),
(453, 5, 'B', 6, 'B06', 2, 1, NULL, '2026-04-29 04:06:49'),
(454, 5, 'B', 7, 'B07', 2, 1, NULL, '2026-04-29 04:06:49'),
(455, 5, 'B', 8, 'B08', 2, 1, NULL, '2026-04-29 04:06:49'),
(456, 5, 'B', 9, 'B09', 2, 1, NULL, '2026-04-29 04:06:49'),
(457, 5, 'B', 10, 'B10', 2, 1, NULL, '2026-04-29 04:06:49'),
(458, 5, 'B', 11, 'B11', 2, 1, NULL, '2026-04-29 04:06:49'),
(459, 5, 'B', 12, 'B12', 2, 1, NULL, '2026-04-29 04:06:49'),
(460, 5, 'B', 13, 'B13', 2, 1, NULL, '2026-04-29 04:06:49'),
(461, 5, 'B', 14, 'B14', 2, 1, NULL, '2026-04-29 04:06:49'),
(462, 5, 'B', 15, 'B15', 2, 1, NULL, '2026-04-29 04:06:49'),
(463, 5, 'C', 1, 'C01', 2, 1, NULL, '2026-04-29 04:06:49'),
(464, 5, 'C', 2, 'C02', 2, 1, NULL, '2026-04-29 04:06:49'),
(465, 5, 'C', 3, 'C03', 2, 1, NULL, '2026-04-29 04:06:49'),
(466, 5, 'C', 4, 'C04', 2, 1, NULL, '2026-04-29 04:06:49'),
(467, 5, 'C', 5, 'C05', 2, 1, NULL, '2026-04-29 04:06:49'),
(468, 5, 'C', 6, 'C06', 2, 1, NULL, '2026-04-29 04:06:49'),
(469, 5, 'C', 7, 'C07', 2, 1, NULL, '2026-04-29 04:06:49'),
(470, 5, 'C', 8, 'C08', 2, 1, NULL, '2026-04-29 04:06:49'),
(471, 5, 'C', 9, 'C09', 2, 1, NULL, '2026-04-29 04:06:49'),
(472, 5, 'C', 10, 'C10', 2, 1, NULL, '2026-04-29 04:06:49'),
(473, 5, 'C', 11, 'C11', 2, 1, NULL, '2026-04-29 04:06:49'),
(474, 5, 'C', 12, 'C12', 2, 1, NULL, '2026-04-29 04:06:50'),
(475, 5, 'C', 13, 'C13', 2, 1, NULL, '2026-04-29 04:06:50'),
(476, 5, 'C', 14, 'C14', 2, 1, NULL, '2026-04-29 04:06:50'),
(477, 5, 'C', 15, 'C15', 2, 1, NULL, '2026-04-29 04:06:50'),
(478, 5, 'D', 1, 'D01', 3, 1, NULL, '2026-04-29 04:06:50'),
(479, 5, 'D', 2, 'D02', 3, 1, NULL, '2026-04-29 04:06:50'),
(480, 5, 'D', 3, 'D03', 3, 1, NULL, '2026-04-29 04:06:50'),
(481, 5, 'D', 4, 'D04', 3, 1, NULL, '2026-04-29 04:06:50'),
(482, 5, 'D', 5, 'D05', 3, 1, NULL, '2026-04-29 04:06:50'),
(483, 5, 'D', 6, 'D06', 3, 1, NULL, '2026-04-29 04:06:50'),
(484, 5, 'D', 7, 'D07', 3, 1, NULL, '2026-04-29 04:06:50'),
(485, 5, 'D', 8, 'D08', 3, 1, NULL, '2026-04-29 04:06:50'),
(486, 5, 'D', 9, 'D09', 3, 1, NULL, '2026-04-29 04:06:50'),
(487, 5, 'D', 10, 'D10', 3, 1, NULL, '2026-04-29 04:06:50'),
(488, 5, 'D', 11, 'D11', 3, 1, NULL, '2026-04-29 04:06:50'),
(489, 5, 'D', 12, 'D12', 3, 1, NULL, '2026-04-29 04:06:50'),
(490, 5, 'D', 13, 'D13', 3, 1, NULL, '2026-04-29 04:06:50'),
(491, 5, 'D', 14, 'D14', 3, 1, NULL, '2026-04-29 04:06:50'),
(492, 5, 'D', 15, 'D15', 3, 1, NULL, '2026-04-29 04:06:50'),
(493, 5, 'E', 1, 'E01', 3, 1, NULL, '2026-04-29 04:06:50'),
(494, 5, 'E', 2, 'E02', 3, 1, NULL, '2026-04-29 04:06:50'),
(495, 5, 'E', 3, 'E03', 3, 1, NULL, '2026-04-29 04:06:50'),
(496, 5, 'E', 4, 'E04', 3, 1, NULL, '2026-04-29 04:06:50'),
(497, 5, 'E', 5, 'E05', 3, 1, NULL, '2026-04-29 04:06:50'),
(498, 5, 'E', 6, 'E06', 3, 1, NULL, '2026-04-29 04:06:50'),
(499, 5, 'E', 7, 'E07', 3, 1, NULL, '2026-04-29 04:06:50'),
(500, 5, 'E', 8, 'E08', 3, 1, NULL, '2026-04-29 04:06:50'),
(501, 5, 'E', 9, 'E09', 3, 1, NULL, '2026-04-29 04:06:50'),
(502, 5, 'E', 10, 'E10', 3, 1, NULL, '2026-04-29 04:06:50'),
(503, 5, 'E', 11, 'E11', 3, 1, NULL, '2026-04-29 04:06:51'),
(504, 5, 'E', 12, 'E12', 3, 1, NULL, '2026-04-29 04:06:51'),
(505, 5, 'E', 13, 'E13', 3, 1, NULL, '2026-04-29 04:06:51'),
(506, 5, 'E', 14, 'E14', 3, 1, NULL, '2026-04-29 04:06:51'),
(507, 5, 'E', 15, 'E15', 3, 1, NULL, '2026-04-29 04:06:51'),
(508, 5, 'F', 1, 'F01', 3, 1, NULL, '2026-04-29 04:06:51'),
(509, 5, 'F', 2, 'F02', 3, 1, NULL, '2026-04-29 04:06:51'),
(510, 5, 'F', 3, 'F03', 3, 1, NULL, '2026-04-29 04:06:51'),
(511, 5, 'F', 4, 'F04', 3, 1, NULL, '2026-04-29 04:06:51'),
(512, 5, 'F', 5, 'F05', 3, 1, NULL, '2026-04-29 04:06:51'),
(513, 5, 'F', 6, 'F06', 3, 1, NULL, '2026-04-29 04:06:51'),
(514, 5, 'F', 7, 'F07', 3, 1, NULL, '2026-04-29 04:06:51'),
(515, 5, 'F', 8, 'F08', 3, 1, NULL, '2026-04-29 04:06:51'),
(516, 5, 'F', 9, 'F09', 3, 1, NULL, '2026-04-29 04:06:51'),
(517, 5, 'F', 10, 'F10', 3, 1, NULL, '2026-04-29 04:06:51'),
(518, 5, 'F', 11, 'F11', 3, 1, NULL, '2026-04-29 04:06:51'),
(519, 5, 'F', 12, 'F12', 3, 1, NULL, '2026-04-29 04:06:51'),
(520, 5, 'F', 13, 'F13', 3, 1, NULL, '2026-04-29 04:06:51'),
(521, 5, 'F', 14, 'F14', 3, 1, NULL, '2026-04-29 04:06:51'),
(522, 5, 'F', 15, 'F15', 3, 1, NULL, '2026-04-29 04:06:51'),
(523, 5, 'G', 1, 'G01', 1, 1, NULL, '2026-04-29 04:06:52'),
(524, 5, 'G', 2, 'G02', 1, 1, NULL, '2026-04-29 04:06:52'),
(525, 5, 'G', 3, 'G03', 1, 1, NULL, '2026-04-29 04:06:52'),
(526, 5, 'G', 4, 'G04', 1, 1, NULL, '2026-04-29 04:06:52'),
(527, 5, 'G', 5, 'G05', 1, 1, NULL, '2026-04-29 04:06:52'),
(528, 5, 'G', 6, 'G06', 1, 1, NULL, '2026-04-29 04:06:52'),
(529, 5, 'G', 7, 'G07', 1, 1, NULL, '2026-04-29 04:06:52'),
(530, 5, 'G', 8, 'G08', 1, 1, NULL, '2026-04-29 04:06:52'),
(531, 5, 'G', 9, 'G09', 1, 1, NULL, '2026-04-29 04:06:52'),
(532, 5, 'G', 10, 'G10', 1, 1, NULL, '2026-04-29 04:06:52'),
(533, 5, 'G', 11, 'G11', 1, 1, NULL, '2026-04-29 04:06:52'),
(534, 5, 'G', 12, 'G12', 1, 1, NULL, '2026-04-29 04:06:52'),
(535, 5, 'G', 13, 'G13', 1, 1, NULL, '2026-04-29 04:06:52'),
(536, 5, 'G', 14, 'G14', 1, 1, NULL, '2026-04-29 04:06:52'),
(537, 5, 'G', 15, 'G15', 1, 1, NULL, '2026-04-29 04:06:52'),
(538, 5, 'H', 1, 'H01', 1, 1, NULL, '2026-04-29 04:06:52'),
(539, 5, 'H', 2, 'H02', 1, 1, NULL, '2026-04-29 04:06:52'),
(540, 5, 'H', 3, 'H03', 1, 1, NULL, '2026-04-29 04:06:52'),
(541, 5, 'H', 4, 'H04', 1, 1, NULL, '2026-04-29 04:06:52'),
(542, 5, 'H', 5, 'H05', 1, 1, NULL, '2026-04-29 04:06:52'),
(543, 5, 'H', 6, 'H06', 1, 1, NULL, '2026-04-29 04:06:52'),
(544, 5, 'H', 7, 'H07', 1, 1, NULL, '2026-04-29 04:06:52'),
(545, 5, 'H', 8, 'H08', 1, 1, NULL, '2026-04-29 04:06:53'),
(546, 5, 'H', 9, 'H09', 1, 1, NULL, '2026-04-29 04:06:53'),
(547, 5, 'H', 10, 'H10', 1, 1, NULL, '2026-04-29 04:06:53'),
(548, 5, 'H', 11, 'H11', 1, 1, NULL, '2026-04-29 04:06:53'),
(549, 5, 'H', 12, 'H12', 1, 1, NULL, '2026-04-29 04:06:53'),
(550, 5, 'H', 13, 'H13', 1, 1, NULL, '2026-04-29 04:06:53'),
(551, 5, 'H', 14, 'H14', 1, 1, NULL, '2026-04-29 04:06:53'),
(552, 5, 'H', 15, 'H15', 1, 1, NULL, '2026-04-29 04:06:53'),
(553, 5, 'I', 1, 'I01', 1, 1, NULL, '2026-04-29 04:06:53'),
(554, 5, 'I', 2, 'I02', 1, 1, NULL, '2026-04-29 04:06:53'),
(555, 5, 'I', 3, 'I03', 1, 1, NULL, '2026-04-29 04:06:53'),
(556, 5, 'I', 4, 'I04', 1, 1, NULL, '2026-04-29 04:06:53'),
(557, 5, 'I', 5, 'I05', 1, 1, NULL, '2026-04-29 04:06:53'),
(558, 5, 'I', 6, 'I06', 1, 1, NULL, '2026-04-29 04:06:53'),
(559, 5, 'I', 7, 'I07', 1, 1, NULL, '2026-04-29 04:06:53'),
(560, 5, 'I', 8, 'I08', 1, 1, NULL, '2026-04-29 04:06:53'),
(561, 5, 'I', 9, 'I09', 1, 1, NULL, '2026-04-29 04:06:53'),
(562, 5, 'I', 10, 'I10', 1, 1, NULL, '2026-04-29 04:06:53'),
(563, 5, 'I', 11, 'I11', 1, 1, NULL, '2026-04-29 04:06:53'),
(564, 5, 'I', 12, 'I12', 1, 1, NULL, '2026-04-29 04:06:53'),
(565, 5, 'I', 13, 'I13', 1, 1, NULL, '2026-04-29 04:06:53'),
(566, 5, 'I', 14, 'I14', 1, 1, NULL, '2026-04-29 04:06:53'),
(567, 5, 'I', 15, 'I15', 1, 1, NULL, '2026-04-29 04:06:53'),
(568, 5, 'J', 1, 'J01', 1, 1, NULL, '2026-04-29 04:06:53'),
(569, 5, 'J', 2, 'J02', 1, 1, NULL, '2026-04-29 04:06:53'),
(570, 5, 'J', 3, 'J03', 1, 1, NULL, '2026-04-29 04:06:53'),
(571, 5, 'J', 4, 'J04', 1, 1, NULL, '2026-04-29 04:06:53'),
(572, 5, 'J', 5, 'J05', 1, 1, NULL, '2026-04-29 04:06:53'),
(573, 5, 'J', 6, 'J06', 1, 1, NULL, '2026-04-29 04:06:53'),
(574, 5, 'J', 7, 'J07', 1, 1, NULL, '2026-04-29 04:06:54'),
(575, 5, 'J', 8, 'J08', 1, 1, NULL, '2026-04-29 04:06:54'),
(576, 5, 'J', 9, 'J09', 1, 1, NULL, '2026-04-29 04:06:54'),
(577, 5, 'J', 10, 'J10', 1, 1, NULL, '2026-04-29 04:06:54'),
(578, 5, 'J', 11, 'J11', 1, 1, NULL, '2026-04-29 04:06:54'),
(579, 5, 'J', 12, 'J12', 1, 1, NULL, '2026-04-29 04:06:54'),
(580, 5, 'J', 13, 'J13', 1, 1, NULL, '2026-04-29 04:06:54'),
(581, 5, 'J', 14, 'J14', 1, 1, NULL, '2026-04-29 04:06:54'),
(582, 5, 'J', 15, 'J15', 1, 1, NULL, '2026-04-29 04:06:54'),
(703, 7, 'A', 1, 'A01', 2, 1, NULL, '2026-04-29 04:06:58'),
(704, 7, 'A', 2, 'A02', 2, 1, NULL, '2026-04-29 04:06:58'),
(705, 7, 'A', 3, 'A03', 2, 1, NULL, '2026-04-29 04:06:58'),
(706, 7, 'A', 4, 'A04', 2, 1, NULL, '2026-04-29 04:06:58'),
(707, 7, 'A', 5, 'A05', 2, 1, NULL, '2026-04-29 04:06:58'),
(708, 7, 'A', 6, 'A06', 2, 1, NULL, '2026-04-29 04:06:58'),
(709, 7, 'A', 7, 'A07', 2, 1, NULL, '2026-04-29 04:06:59'),
(710, 7, 'A', 8, 'A08', 2, 1, NULL, '2026-04-29 04:06:59'),
(711, 7, 'A', 9, 'A09', 2, 1, NULL, '2026-04-29 04:06:59'),
(712, 7, 'A', 10, 'A10', 2, 1, NULL, '2026-04-29 04:06:59'),
(713, 7, 'B', 1, 'B01', 2, 1, NULL, '2026-04-29 04:06:59'),
(714, 7, 'B', 2, 'B02', 2, 1, NULL, '2026-04-29 04:06:59'),
(715, 7, 'B', 3, 'B03', 2, 1, NULL, '2026-04-29 04:06:59'),
(716, 7, 'B', 4, 'B04', 2, 1, NULL, '2026-04-29 04:06:59'),
(717, 7, 'B', 5, 'B05', 2, 1, NULL, '2026-04-29 04:06:59'),
(718, 7, 'B', 6, 'B06', 2, 1, NULL, '2026-04-29 04:06:59'),
(719, 7, 'B', 7, 'B07', 2, 1, NULL, '2026-04-29 04:06:59'),
(720, 7, 'B', 8, 'B08', 2, 1, NULL, '2026-04-29 04:06:59'),
(721, 7, 'B', 9, 'B09', 2, 1, NULL, '2026-04-29 04:06:59'),
(722, 7, 'B', 10, 'B10', 2, 1, NULL, '2026-04-29 04:06:59'),
(723, 7, 'C', 1, 'C01', 2, 1, NULL, '2026-04-29 04:06:59'),
(724, 7, 'C', 2, 'C02', 2, 1, NULL, '2026-04-29 04:06:59'),
(725, 7, 'C', 3, 'C03', 2, 1, NULL, '2026-04-29 04:06:59'),
(726, 7, 'C', 4, 'C04', 2, 1, NULL, '2026-04-29 04:06:59'),
(727, 7, 'C', 5, 'C05', 2, 1, NULL, '2026-04-29 04:06:59'),
(728, 7, 'C', 6, 'C06', 2, 1, NULL, '2026-04-29 04:06:59'),
(729, 7, 'C', 7, 'C07', 2, 1, NULL, '2026-04-29 04:06:59'),
(730, 7, 'C', 8, 'C08', 2, 1, NULL, '2026-04-29 04:06:59'),
(731, 7, 'C', 9, 'C09', 2, 1, NULL, '2026-04-29 04:06:59'),
(732, 7, 'C', 10, 'C10', 2, 1, NULL, '2026-04-29 04:06:59'),
(733, 7, 'D', 1, 'D01', 3, 1, NULL, '2026-04-29 04:06:59'),
(734, 7, 'D', 2, 'D02', 3, 1, NULL, '2026-04-29 04:06:59'),
(735, 7, 'D', 3, 'D03', 3, 1, NULL, '2026-04-29 04:07:00'),
(736, 7, 'D', 4, 'D04', 3, 1, NULL, '2026-04-29 04:07:00'),
(737, 7, 'D', 5, 'D05', 3, 1, NULL, '2026-04-29 04:07:00'),
(738, 7, 'D', 6, 'D06', 3, 1, NULL, '2026-04-29 04:07:00'),
(739, 7, 'D', 7, 'D07', 3, 1, NULL, '2026-04-29 04:07:00'),
(740, 7, 'D', 8, 'D08', 3, 1, NULL, '2026-04-29 04:07:00'),
(741, 7, 'D', 9, 'D09', 3, 1, NULL, '2026-04-29 04:07:00'),
(742, 7, 'D', 10, 'D10', 3, 1, NULL, '2026-04-29 04:07:00'),
(743, 7, 'E', 1, 'E01', 3, 1, NULL, '2026-04-29 04:07:00'),
(744, 7, 'E', 2, 'E02', 3, 1, NULL, '2026-04-29 04:07:00'),
(745, 7, 'E', 3, 'E03', 3, 1, NULL, '2026-04-29 04:07:00'),
(746, 7, 'E', 4, 'E04', 3, 1, NULL, '2026-04-29 04:07:00'),
(747, 7, 'E', 5, 'E05', 3, 1, NULL, '2026-04-29 04:07:00'),
(748, 7, 'E', 6, 'E06', 3, 1, NULL, '2026-04-29 04:07:00'),
(749, 7, 'E', 7, 'E07', 3, 1, NULL, '2026-04-29 04:07:00'),
(750, 7, 'E', 8, 'E08', 3, 1, NULL, '2026-04-29 04:07:00'),
(751, 7, 'E', 9, 'E09', 3, 1, NULL, '2026-04-29 04:07:00'),
(752, 7, 'E', 10, 'E10', 3, 1, NULL, '2026-04-29 04:07:00'),
(753, 7, 'F', 1, 'F01', 1, 1, NULL, '2026-04-29 04:07:00'),
(754, 7, 'F', 2, 'F02', 1, 1, NULL, '2026-04-29 04:07:00'),
(755, 7, 'F', 3, 'F03', 1, 1, NULL, '2026-04-29 04:07:00'),
(756, 7, 'F', 4, 'F04', 1, 1, NULL, '2026-04-29 04:07:00'),
(757, 7, 'F', 5, 'F05', 1, 1, NULL, '2026-04-29 04:07:00'),
(758, 7, 'F', 6, 'F06', 1, 1, NULL, '2026-04-29 04:07:00'),
(759, 7, 'F', 7, 'F07', 1, 1, NULL, '2026-04-29 04:07:00'),
(760, 7, 'F', 8, 'F08', 1, 1, NULL, '2026-04-29 04:07:00'),
(761, 7, 'F', 9, 'F09', 1, 1, NULL, '2026-04-29 04:07:00'),
(762, 7, 'F', 10, 'F10', 1, 1, NULL, '2026-04-29 04:07:00'),
(763, 7, 'G', 1, 'G01', 1, 1, NULL, '2026-04-29 04:07:00'),
(764, 7, 'G', 2, 'G02', 1, 1, NULL, '2026-04-29 04:07:00'),
(765, 7, 'G', 3, 'G03', 1, 1, NULL, '2026-04-29 04:07:01'),
(766, 7, 'G', 4, 'G04', 1, 1, NULL, '2026-04-29 04:07:01'),
(767, 7, 'G', 5, 'G05', 1, 1, NULL, '2026-04-29 04:07:01'),
(768, 7, 'G', 6, 'G06', 1, 1, NULL, '2026-04-29 04:07:01'),
(769, 7, 'G', 7, 'G07', 1, 1, NULL, '2026-04-29 04:07:01'),
(770, 7, 'G', 8, 'G08', 1, 1, NULL, '2026-04-29 04:07:01'),
(771, 7, 'G', 9, 'G09', 1, 1, NULL, '2026-04-29 04:07:01'),
(772, 7, 'G', 10, 'G10', 1, 1, NULL, '2026-04-29 04:07:01'),
(773, 7, 'H', 1, 'H01', 1, 1, NULL, '2026-04-29 04:07:01'),
(774, 7, 'H', 2, 'H02', 1, 1, NULL, '2026-04-29 04:07:01'),
(775, 7, 'H', 3, 'H03', 1, 1, NULL, '2026-04-29 04:07:01'),
(776, 7, 'H', 4, 'H04', 1, 1, NULL, '2026-04-29 04:07:01'),
(777, 7, 'H', 5, 'H05', 1, 1, NULL, '2026-04-29 04:07:01'),
(778, 7, 'H', 6, 'H06', 1, 1, NULL, '2026-04-29 04:07:01'),
(779, 7, 'H', 7, 'H07', 1, 1, NULL, '2026-04-29 04:07:01'),
(780, 7, 'H', 8, 'H08', 1, 1, NULL, '2026-04-29 04:07:01'),
(781, 7, 'H', 9, 'H09', 1, 1, NULL, '2026-04-29 04:07:01'),
(782, 7, 'H', 10, 'H10', 1, 1, NULL, '2026-04-29 04:07:01'),
(783, 6, 'A', 1, 'A01', 1, 1, NULL, '2026-04-29 07:06:16'),
(784, 6, 'A', 2, 'A02', 1, 1, NULL, '2026-04-29 07:06:16'),
(785, 6, 'A', 3, 'A03', 2, 1, NULL, '2026-04-29 07:06:16'),
(786, 6, 'A', 4, 'A04', 2, 1, NULL, '2026-04-29 07:06:16'),
(787, 6, 'A', 5, 'A05', 2, 1, NULL, '2026-04-29 07:06:16'),
(788, 6, 'A', 6, 'A06', 2, 1, NULL, '2026-04-29 07:06:16'),
(789, 6, 'A', 7, 'A07', 2, 1, NULL, '2026-04-29 07:06:16'),
(790, 6, 'A', 8, 'A08', 2, 1, NULL, '2026-04-29 07:06:16'),
(791, 6, 'A', 9, 'A09', 2, 1, NULL, '2026-04-29 07:06:16'),
(792, 6, 'A', 10, 'A10', 2, 1, NULL, '2026-04-29 07:06:16'),
(793, 6, 'A', 11, 'A11', 2, 1, NULL, '2026-04-29 07:06:16'),
(794, 6, 'A', 12, 'A12', 2, 1, NULL, '2026-04-29 07:06:16'),
(795, 6, 'B', 1, 'B01', 1, 1, NULL, '2026-04-29 07:06:16'),
(796, 6, 'B', 2, 'B02', 1, 1, NULL, '2026-04-29 07:06:16'),
(797, 6, 'B', 3, 'B03', 2, 1, NULL, '2026-04-29 07:06:16'),
(798, 6, 'B', 4, 'B04', 2, 1, NULL, '2026-04-29 07:06:16'),
(799, 6, 'B', 5, 'B05', 2, 1, NULL, '2026-04-29 07:06:16'),
(800, 6, 'B', 6, 'B06', 2, 1, NULL, '2026-04-29 07:06:16'),
(801, 6, 'B', 7, 'B07', 2, 1, NULL, '2026-04-29 07:06:16'),
(802, 6, 'B', 8, 'B08', 2, 1, NULL, '2026-04-29 07:06:16'),
(803, 6, 'B', 9, 'B09', 2, 1, NULL, '2026-04-29 07:06:16'),
(804, 6, 'B', 10, 'B10', 2, 1, NULL, '2026-04-29 07:06:16'),
(805, 6, 'B', 11, 'B11', 2, 1, NULL, '2026-04-29 07:06:16'),
(806, 6, 'B', 12, 'B12', 2, 1, NULL, '2026-04-29 07:06:16'),
(807, 6, 'C', 1, 'C01', 1, 1, NULL, '2026-04-29 07:06:16'),
(808, 6, 'C', 2, 'C02', 1, 1, NULL, '2026-04-29 07:06:16'),
(809, 6, 'C', 3, 'C03', 2, 1, NULL, '2026-04-29 07:06:16'),
(810, 6, 'C', 4, 'C04', 2, 1, NULL, '2026-04-29 07:06:16'),
(811, 6, 'C', 5, 'C05', 2, 1, NULL, '2026-04-29 07:06:16'),
(812, 6, 'C', 6, 'C06', 2, 1, NULL, '2026-04-29 07:06:16'),
(813, 6, 'C', 7, 'C07', 2, 1, NULL, '2026-04-29 07:06:16'),
(814, 6, 'C', 8, 'C08', 2, 1, NULL, '2026-04-29 07:06:16'),
(815, 6, 'C', 9, 'C09', 2, 1, NULL, '2026-04-29 07:06:16'),
(816, 6, 'C', 10, 'C10', 2, 1, NULL, '2026-04-29 07:06:16'),
(817, 6, 'C', 11, 'C11', 2, 1, NULL, '2026-04-29 07:06:16'),
(818, 6, 'C', 12, 'C12', 2, 1, NULL, '2026-04-29 07:06:16'),
(819, 6, 'D', 1, 'D01', 3, 1, NULL, '2026-04-29 07:06:16'),
(820, 6, 'D', 2, 'D02', 3, 1, NULL, '2026-04-29 07:06:16'),
(821, 6, 'D', 3, 'D03', 3, 1, NULL, '2026-04-29 07:06:16'),
(822, 6, 'D', 4, 'D04', 3, 1, NULL, '2026-04-29 07:06:16'),
(823, 6, 'D', 5, 'D05', 3, 1, NULL, '2026-04-29 07:06:16'),
(824, 6, 'D', 6, 'D06', 3, 1, NULL, '2026-04-29 07:06:16'),
(825, 6, 'D', 7, 'D07', 3, 1, NULL, '2026-04-29 07:06:16'),
(826, 6, 'D', 8, 'D08', 3, 1, NULL, '2026-04-29 07:06:16'),
(827, 6, 'D', 9, 'D09', 3, 1, NULL, '2026-04-29 07:06:16'),
(828, 6, 'D', 10, 'D10', 3, 1, NULL, '2026-04-29 07:06:16'),
(829, 6, 'D', 11, 'D11', 3, 1, NULL, '2026-04-29 07:06:16'),
(830, 6, 'D', 12, 'D12', 3, 1, NULL, '2026-04-29 07:06:16'),
(831, 6, 'E', 1, 'E01', 3, 1, NULL, '2026-04-29 07:06:16'),
(832, 6, 'E', 2, 'E02', 3, 1, NULL, '2026-04-29 07:06:16'),
(833, 6, 'E', 3, 'E03', 3, 1, NULL, '2026-04-29 07:06:16'),
(834, 6, 'E', 4, 'E04', 3, 1, NULL, '2026-04-29 07:06:16'),
(835, 6, 'E', 5, 'E05', 3, 1, NULL, '2026-04-29 07:06:16'),
(836, 6, 'E', 6, 'E06', 3, 1, NULL, '2026-04-29 07:06:16'),
(837, 6, 'E', 7, 'E07', 3, 1, NULL, '2026-04-29 07:06:16'),
(838, 6, 'E', 8, 'E08', 3, 1, NULL, '2026-04-29 07:06:16'),
(839, 6, 'E', 9, 'E09', 3, 1, NULL, '2026-04-29 07:06:16'),
(840, 6, 'E', 10, 'E10', 3, 1, NULL, '2026-04-29 07:06:16'),
(841, 6, 'E', 11, 'E11', 3, 1, NULL, '2026-04-29 07:06:16'),
(842, 6, 'E', 12, 'E12', 3, 1, NULL, '2026-04-29 07:06:16'),
(843, 6, 'F', 1, 'F01', 3, 1, NULL, '2026-04-29 07:06:16'),
(844, 6, 'F', 2, 'F02', 3, 1, NULL, '2026-04-29 07:06:16'),
(845, 6, 'F', 3, 'F03', 3, 1, NULL, '2026-04-29 07:06:16'),
(846, 6, 'F', 4, 'F04', 3, 1, NULL, '2026-04-29 07:06:16'),
(847, 6, 'F', 5, 'F05', 3, 1, NULL, '2026-04-29 07:06:16'),
(848, 6, 'F', 6, 'F06', 3, 1, NULL, '2026-04-29 07:06:16'),
(849, 6, 'F', 7, 'F07', 3, 1, NULL, '2026-04-29 07:06:16'),
(850, 6, 'F', 8, 'F08', 3, 1, NULL, '2026-04-29 07:06:16'),
(851, 6, 'F', 9, 'F09', 3, 1, NULL, '2026-04-29 07:06:16'),
(852, 6, 'F', 10, 'F10', 3, 1, NULL, '2026-04-29 07:06:16'),
(853, 6, 'F', 11, 'F11', 3, 1, NULL, '2026-04-29 07:06:16'),
(854, 6, 'F', 12, 'F12', 3, 1, NULL, '2026-04-29 07:06:16'),
(855, 6, 'G', 1, 'G01', 1, 1, NULL, '2026-04-29 07:06:16'),
(856, 6, 'G', 2, 'G02', 1, 1, NULL, '2026-04-29 07:06:16'),
(857, 6, 'G', 3, 'G03', 1, 1, NULL, '2026-04-29 07:06:16'),
(858, 6, 'G', 4, 'G04', 1, 1, NULL, '2026-04-29 07:06:16'),
(859, 6, 'G', 5, 'G05', 1, 1, NULL, '2026-04-29 07:06:16'),
(860, 6, 'G', 6, 'G06', 1, 1, NULL, '2026-04-29 07:06:16'),
(861, 6, 'G', 7, 'G07', 1, 1, NULL, '2026-04-29 07:06:16'),
(862, 6, 'G', 8, 'G08', 1, 1, NULL, '2026-04-29 07:06:16'),
(863, 6, 'G', 9, 'G09', 1, 1, NULL, '2026-04-29 07:06:16'),
(864, 6, 'G', 10, 'G10', 1, 1, NULL, '2026-04-29 07:06:16'),
(865, 6, 'G', 11, 'G11', 1, 1, NULL, '2026-04-29 07:06:16'),
(866, 6, 'G', 12, 'G12', 1, 1, NULL, '2026-04-29 07:06:16'),
(867, 6, 'H', 1, 'H01', 1, 1, NULL, '2026-04-29 07:06:16'),
(868, 6, 'H', 2, 'H02', 1, 1, NULL, '2026-04-29 07:06:16'),
(869, 6, 'H', 3, 'H03', 1, 1, NULL, '2026-04-29 07:06:16'),
(870, 6, 'H', 4, 'H04', 1, 1, NULL, '2026-04-29 07:06:16'),
(871, 6, 'H', 5, 'H05', 1, 1, NULL, '2026-04-29 07:06:16'),
(872, 6, 'H', 6, 'H06', 1, 1, NULL, '2026-04-29 07:06:16'),
(873, 6, 'H', 7, 'H07', 1, 1, NULL, '2026-04-29 07:06:16'),
(874, 6, 'H', 8, 'H08', 1, 1, NULL, '2026-04-29 07:06:16'),
(875, 6, 'H', 9, 'H09', 1, 1, NULL, '2026-04-29 07:06:16'),
(876, 6, 'H', 10, 'H10', 1, 1, NULL, '2026-04-29 07:06:16'),
(877, 6, 'H', 11, 'H11', 1, 1, NULL, '2026-04-29 07:06:16'),
(878, 6, 'H', 12, 'H12', 1, 1, NULL, '2026-04-29 07:06:16'),
(879, 6, 'I', 1, 'I01', 1, 1, NULL, '2026-04-29 07:06:16'),
(880, 6, 'I', 2, 'I02', 1, 1, NULL, '2026-04-29 07:06:16'),
(881, 6, 'I', 3, 'I03', 1, 1, NULL, '2026-04-29 07:06:16'),
(882, 6, 'I', 4, 'I04', 1, 1, NULL, '2026-04-29 07:06:16'),
(883, 6, 'I', 5, 'I05', 1, 1, NULL, '2026-04-29 07:06:16'),
(884, 6, 'I', 6, 'I06', 1, 1, NULL, '2026-04-29 07:06:16'),
(885, 6, 'I', 7, 'I07', 1, 1, NULL, '2026-04-29 07:06:16'),
(886, 6, 'I', 8, 'I08', 1, 1, NULL, '2026-04-29 07:06:16'),
(887, 6, 'I', 9, 'I09', 1, 1, NULL, '2026-04-29 07:06:16'),
(888, 6, 'I', 10, 'I10', 1, 1, NULL, '2026-04-29 07:06:16'),
(889, 6, 'I', 11, 'I11', 1, 1, NULL, '2026-04-29 07:06:16'),
(890, 6, 'I', 12, 'I12', 1, 1, NULL, '2026-04-29 07:06:16'),
(891, 6, 'J', 1, 'J01', 1, 1, NULL, '2026-04-29 07:06:16'),
(892, 6, 'J', 2, 'J02', 1, 1, NULL, '2026-04-29 07:06:16'),
(893, 6, 'J', 3, 'J03', 1, 1, NULL, '2026-04-29 07:06:16'),
(894, 6, 'J', 4, 'J04', 1, 1, NULL, '2026-04-29 07:06:16'),
(895, 6, 'J', 5, 'J05', 1, 1, NULL, '2026-04-29 07:06:16'),
(896, 6, 'J', 6, 'J06', 1, 1, NULL, '2026-04-29 07:06:16'),
(897, 6, 'J', 7, 'J07', 1, 1, NULL, '2026-04-29 07:06:16'),
(898, 6, 'J', 8, 'J08', 1, 1, NULL, '2026-04-29 07:06:16'),
(899, 6, 'J', 9, 'J09', 1, 1, NULL, '2026-04-29 07:06:16'),
(900, 6, 'J', 10, 'J10', 1, 1, NULL, '2026-04-29 07:06:16'),
(901, 6, 'J', 11, 'J11', 1, 1, NULL, '2026-04-29 07:06:16'),
(902, 6, 'J', 12, 'J12', 1, 1, NULL, '2026-04-29 07:06:16'),
(903, 9, 'A', 1, 'A01', 2, 1, NULL, '2026-04-29 11:12:33'),
(904, 9, 'A', 2, 'A02', 2, 1, NULL, '2026-04-29 11:12:33'),
(905, 9, 'A', 3, 'A03', 2, 1, NULL, '2026-04-29 11:12:33'),
(906, 9, 'A', 4, 'A04', 2, 1, NULL, '2026-04-29 11:12:33'),
(907, 9, 'A', 5, 'A05', 2, 1, NULL, '2026-04-29 11:12:33'),
(908, 9, 'A', 6, 'A06', 2, 1, NULL, '2026-04-29 11:12:33'),
(909, 9, 'A', 7, 'A07', 2, 1, NULL, '2026-04-29 11:12:33'),
(910, 9, 'A', 8, 'A08', 2, 1, NULL, '2026-04-29 11:12:33'),
(911, 9, 'A', 9, 'A09', 2, 1, NULL, '2026-04-29 11:12:33'),
(912, 9, 'A', 10, 'A10', 2, 1, NULL, '2026-04-29 11:12:33'),
(913, 9, 'B', 1, 'B01', 2, 1, NULL, '2026-04-29 11:12:33'),
(914, 9, 'B', 2, 'B02', 2, 1, NULL, '2026-04-29 11:12:33'),
(915, 9, 'B', 3, 'B03', 2, 1, NULL, '2026-04-29 11:12:33'),
(916, 9, 'B', 4, 'B04', 2, 1, NULL, '2026-04-29 11:12:33'),
(917, 9, 'B', 5, 'B05', 2, 1, NULL, '2026-04-29 11:12:33'),
(918, 9, 'B', 6, 'B06', 2, 1, NULL, '2026-04-29 11:12:33'),
(919, 9, 'B', 7, 'B07', 2, 1, NULL, '2026-04-29 11:12:33'),
(920, 9, 'B', 8, 'B08', 2, 1, NULL, '2026-04-29 11:12:33'),
(921, 9, 'B', 9, 'B09', 2, 1, NULL, '2026-04-29 11:12:33'),
(922, 9, 'B', 10, 'B10', 2, 1, NULL, '2026-04-29 11:12:33'),
(923, 9, 'C', 1, 'C01', 2, 1, NULL, '2026-04-29 11:12:33'),
(924, 9, 'C', 2, 'C02', 2, 1, NULL, '2026-04-29 11:12:33'),
(925, 9, 'C', 3, 'C03', 2, 1, NULL, '2026-04-29 11:12:33'),
(926, 9, 'C', 4, 'C04', 2, 1, NULL, '2026-04-29 11:12:33'),
(927, 9, 'C', 5, 'C05', 2, 1, NULL, '2026-04-29 11:12:33'),
(928, 9, 'C', 6, 'C06', 2, 1, NULL, '2026-04-29 11:12:33'),
(929, 9, 'C', 7, 'C07', 2, 1, NULL, '2026-04-29 11:12:33'),
(930, 9, 'C', 8, 'C08', 2, 1, NULL, '2026-04-29 11:12:33'),
(931, 9, 'C', 9, 'C09', 2, 1, NULL, '2026-04-29 11:12:33'),
(932, 9, 'C', 10, 'C10', 2, 1, NULL, '2026-04-29 11:12:33'),
(933, 9, 'D', 1, 'D01', 1, 1, NULL, '2026-04-29 11:12:33'),
(934, 9, 'D', 2, 'D02', 1, 1, NULL, '2026-04-29 11:12:33'),
(935, 9, 'D', 3, 'D03', 1, 1, NULL, '2026-04-29 11:12:33'),
(936, 9, 'D', 4, 'D04', 1, 1, NULL, '2026-04-29 11:12:33'),
(937, 9, 'D', 5, 'D05', 1, 1, NULL, '2026-04-29 11:12:33'),
(938, 9, 'D', 6, 'D06', 1, 1, NULL, '2026-04-29 11:12:33'),
(939, 9, 'D', 7, 'D07', 1, 1, NULL, '2026-04-29 11:12:33'),
(940, 9, 'D', 8, 'D08', 1, 1, NULL, '2026-04-29 11:12:33'),
(941, 9, 'D', 9, 'D09', 1, 1, NULL, '2026-04-29 11:12:33'),
(942, 9, 'D', 10, 'D10', 1, 1, NULL, '2026-04-29 11:12:33'),
(943, 9, 'E', 1, 'E01', 3, 1, NULL, '2026-04-29 11:12:33'),
(944, 9, 'E', 2, 'E02', 3, 1, NULL, '2026-04-29 11:12:33'),
(945, 9, 'E', 3, 'E03', 3, 1, NULL, '2026-04-29 11:12:33'),
(946, 9, 'E', 4, 'E04', 3, 1, NULL, '2026-04-29 11:12:33'),
(947, 9, 'E', 5, 'E05', 3, 1, NULL, '2026-04-29 11:12:33'),
(948, 9, 'E', 6, 'E06', 3, 1, NULL, '2026-04-29 11:12:33'),
(949, 9, 'E', 7, 'E07', 3, 1, NULL, '2026-04-29 11:12:33'),
(950, 9, 'E', 8, 'E08', 3, 1, NULL, '2026-04-29 11:12:33'),
(951, 9, 'E', 9, 'E09', 3, 1, NULL, '2026-04-29 11:12:33'),
(952, 9, 'E', 10, 'E10', 3, 1, NULL, '2026-04-29 11:12:33'),
(953, 9, 'F', 1, 'F01', 3, 1, NULL, '2026-04-29 11:12:33'),
(954, 9, 'F', 2, 'F02', 3, 1, NULL, '2026-04-29 11:12:33'),
(955, 9, 'F', 3, 'F03', 3, 1, NULL, '2026-04-29 11:12:33'),
(956, 9, 'F', 4, 'F04', 3, 1, NULL, '2026-04-29 11:12:33'),
(957, 9, 'F', 5, 'F05', 3, 1, NULL, '2026-04-29 11:12:33'),
(958, 9, 'F', 6, 'F06', 3, 1, NULL, '2026-04-29 11:12:33'),
(959, 9, 'F', 7, 'F07', 3, 1, NULL, '2026-04-29 11:12:33'),
(960, 9, 'F', 8, 'F08', 3, 1, NULL, '2026-04-29 11:12:33'),
(961, 9, 'F', 9, 'F09', 3, 1, NULL, '2026-04-29 11:12:33'),
(962, 9, 'F', 10, 'F10', 3, 1, NULL, '2026-04-29 11:12:33'),
(963, 9, 'G', 1, 'G01', 3, 1, NULL, '2026-04-29 11:12:33'),
(964, 9, 'G', 2, 'G02', 3, 1, NULL, '2026-04-29 11:12:33'),
(965, 9, 'G', 3, 'G03', 3, 1, NULL, '2026-04-29 11:12:33'),
(966, 9, 'G', 4, 'G04', 3, 1, NULL, '2026-04-29 11:12:33'),
(967, 9, 'G', 5, 'G05', 3, 1, NULL, '2026-04-29 11:12:33'),
(968, 9, 'G', 6, 'G06', 3, 1, NULL, '2026-04-29 11:12:33'),
(969, 9, 'G', 7, 'G07', 3, 1, NULL, '2026-04-29 11:12:33'),
(970, 9, 'G', 8, 'G08', 3, 1, NULL, '2026-04-29 11:12:33'),
(971, 9, 'G', 9, 'G09', 3, 1, NULL, '2026-04-29 11:12:33'),
(972, 9, 'G', 10, 'G10', 3, 1, NULL, '2026-04-29 11:12:33');

-- --------------------------------------------------------

--
-- Table structure for table `seat_types`
--

CREATE TABLE `seat_types` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `default_price` decimal(10,2) NOT NULL,
  `color_code` varchar(7) DEFAULT '#3498db',
  `description` varchar(255) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `seat_types`
--

INSERT INTO `seat_types` (`id`, `name`, `default_price`, `color_code`, `description`, `sort_order`, `is_active`, `created_at`) VALUES
(1, 'Standard', 350.00, '#3498db', NULL, 1, 1, '2026-04-29 04:06:26'),
(2, 'Premium', 450.00, '#FFD700', NULL, 2, 1, '2026-04-29 04:06:26'),
(3, 'Sweet Spot', 550.00, '#e74c3c', NULL, 3, 1, '2026-04-29 04:06:26');

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
(1, 8, 'CHECK_IN', 1, 'Checked in customer via verify booking page', '2026-04-29 13:44:37');

-- --------------------------------------------------------

--
-- Table structure for table `suggestions`
--

CREATE TABLE `suggestions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `user_name` varchar(100) NOT NULL,
  `user_email` varchar(100) NOT NULL,
  `suggestion` text NOT NULL,
  `status` enum('Pending','Reviewed','Implemented') DEFAULT 'Pending',
  `admin_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `is_visible` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`u_id`, `u_name`, `u_username`, `u_email`, `u_pass`, `u_role`, `u_status`, `created_by`, `created_at`, `last_login`, `is_visible`) VALUES
(1, 'Jaylord Laspuna', 'jaylord', 'jaylord@gmail.com', '$2y$10$MjqJr8ZRNVRurbp.DMvg3ee1jJ.5mW/32Fov9nFkalhc.oJ7LL6Vm', 'Admin', 'Active', NULL, '2026-04-29 04:06:33', '2026-04-29 16:15:01', 1),
(2, 'Denise Cana', 'denise', 'denisecana@gmail.com', '$2y$10$MjqJr8ZRNVRurbp.DMvg3ee1jJ.5mW/32Fov9nFkalhc.oJ7LL6Vm', 'Owner', 'Active', NULL, '2026-04-29 04:06:33', '2026-04-29 04:07:43', 1),
(3, 'test', 'test', 'test@gmail.com', '$2y$10$vLbkiTvM.fXe8MPhRzofC.gtIgASIFvd1ujeil8gPO26BPhY1FTzC', 'Staff', 'Inactive', 1, '2026-04-29 08:49:54', NULL, 0),
(4, 'testt', 'testt', 'testt@gmail.com', '$2y$10$DnPwhLE1E2PJUDYqmyFxQOHyM.yoKju/qEpf4IM10A.uHRz9gYF9q', 'Admin', 'Inactive', 1, '2026-04-29 09:01:57', NULL, 0),
(5, 'dexter3', 'dexter3', 'dext3r@gmail.com', '$2y$10$IC6RW2eujV63bpakzRI/ge/TMPKliAOxbNdYhY/KUEAhaEgCUqxNK', 'Admin', 'Inactive', 1, '2026-04-29 09:02:15', NULL, 0),
(6, 'test', 'test4', 'test4@gmail.com', '$2y$10$eHuJGwTD9cOeHOzGSqweiezVDoZO4aBroBmFzBPNvE8/sw52BIHnm', 'Customer', 'Active', 1, '2026-04-29 09:08:45', NULL, 1),
(7, 'Sir Aries Vincent Dajay', 'aries', 'aries@gmail.com', '$2y$10$TH.4LCtcH1Bm/xVLk6rdOutIIg4XyBzxJNS8wajCFD7RLKvuXAhse', 'Customer', 'Active', NULL, '2026-04-29 09:44:23', '2026-04-29 17:08:32', 1),
(8, 'jurist', 'jurist', 'jurist@gmail.com', '$2y$10$Y0NoPViUt14dc5Oy36RyNerYP1KWmqi.9ZhRJek3n7uikBEwGKUnK', 'Staff', 'Active', 1, '2026-04-29 12:55:13', '2026-04-29 16:55:51', 1);

-- --------------------------------------------------------

--
-- Stand-in structure for view `users_with_creators`
-- (See below for the actual view)
--
CREATE TABLE `users_with_creators` (
`u_id` int(11)
,`u_name` varchar(100)
,`u_username` varchar(50)
,`u_email` varchar(100)
,`u_role` enum('Admin','Customer','Owner','Staff')
,`u_status` enum('Active','Inactive')
,`created_at` timestamp
,`last_login` timestamp
,`created_by_name` varchar(100)
);

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
  `contact_number` varchar(20) DEFAULT NULL,
  `operating_hours` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `venues`
--

INSERT INTO `venues` (`id`, `venue_name`, `venue_location`, `google_maps_link`, `venue_photo_path`, `contact_number`, `operating_hours`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'SM Cinema – SM Seaside', 'SM Seaside Complex, F. Vestil St, Cebu City', NULL, NULL, '(032) 888-1234', '10:00 AM - 9:00 PM', 1, '2026-04-29 04:06:33', NULL),
(2, 'SM Cinema – SM City Cebu', 'North Reclamation Area, Cebu City', NULL, NULL, '(032) 888-5678', '10:00 AM - 9:00 PM', 1, '2026-04-29 04:06:33', NULL),
(3, 'Ayala Cinema – Ayala Center Cebu', 'Ayala Center, Cebu Business Park, Cebu City', 'https://www.google.com/maps/place/Second+Floor,+The+Terraces,+ayala+center+cebu,+Cebu+City,+6000+Cebu/@10.3185467,123.9057502,3a,75y,48.94h,107.33t/data=!3m8!1e1!3m6!1sCIHM0ogKEICAgIDqxcrg9QE!2e10!3e11!6shttps:%2F%2Flh3.googleusercontent.com%2Fgpms-cs-s%2FABJJf52ggRcFuzYlJHiw93x-ePO4bsYELKyn06VHFnrjyb_-uAKkdTXV0_ONDMpbhpCcgsbQxCd2fBb3XbGtgFqmurKqZ79i3L21RQLV3fsfHZODLB4wnzEZF0neLzp6SM0hGyHWsuRe%3Dw900-h600-k-no-pi-17.329913311618398-ya175.9360777875529-ro0-fo100!7i10240!8i5120!4m9!1m2!2m1!1sAyala', 'uploads/venue/venue_1777436750_9882.jpeg', '(032) 888-9012', '11:00 AM - 10:00 PM', 1, '2026-04-29 04:06:33', '2026-04-29 09:58:47');

-- --------------------------------------------------------

--
-- Stand-in structure for view `venue_screens_summary`
-- (See below for the actual view)
--
CREATE TABLE `venue_screens_summary` (
`venue_id` int(11)
,`venue_name` varchar(255)
,`venue_location` varchar(500)
,`contact_number` varchar(20)
,`operating_hours` varchar(255)
,`total_screens` bigint(21)
,`total_capacity` decimal(32,0)
,`total_seat_plans` bigint(21)
,`total_seats_configured` decimal(32,0)
);

-- --------------------------------------------------------

--
-- Structure for view `schedule_availability_summary`
--
DROP TABLE IF EXISTS `schedule_availability_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `schedule_availability_summary`  AS SELECT `sch`.`id` AS `schedule_id`, `sch`.`movie_id` AS `movie_id`, `m`.`title` AS `movie_title`, `sch`.`screen_id` AS `screen_id`, `s`.`screen_name` AS `screen_name`, `s`.`screen_number` AS `screen_number`, `v`.`venue_name` AS `venue_name`, `sch`.`show_date` AS `show_date`, `sch`.`showtime` AS `showtime`, `sch`.`base_price` AS `base_price`, count(`sa`.`id`) AS `total_seats`, count(case when `sa`.`status` = 'available' then 1 end) AS `available_seats`, count(case when `sa`.`status` = 'booked' then 1 end) AS `booked_seats`, count(case when `sa`.`status` = 'reserved' then 1 end) AS `reserved_seats`, round(count(case when `sa`.`status` = 'booked' then 1 end) / nullif(count(`sa`.`id`),0) * 100,2) AS `occupancy_rate`, CASE WHEN count(case when `sa`.`status` = 'available' then 1 end) = 0 THEN 'sold_out' WHEN `sch`.`show_date` < curdate() THEN 'expired' WHEN `sch`.`show_date` = curdate() AND `sch`.`showtime` < curtime() THEN 'expired' ELSE 'active' END AS `schedule_status` FROM ((((`schedules` `sch` join `movies` `m` on(`sch`.`movie_id` = `m`.`id` and `m`.`is_active` = 1)) join `screens` `s` on(`sch`.`screen_id` = `s`.`id`)) join `venues` `v` on(`s`.`venue_id` = `v`.`id`)) join `seat_availability` `sa` on(`sch`.`id` = `sa`.`schedule_id`)) WHERE `sch`.`is_active` = 1 GROUP BY `sch`.`id`, `sch`.`movie_id`, `m`.`title`, `sch`.`screen_id`, `s`.`screen_name`, `s`.`screen_number`, `v`.`venue_name`, `sch`.`show_date`, `sch`.`showtime`, `sch`.`base_price` ;

-- --------------------------------------------------------

--
-- Structure for view `users_with_creators`
--
DROP TABLE IF EXISTS `users_with_creators`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `users_with_creators`  AS SELECT `u`.`u_id` AS `u_id`, `u`.`u_name` AS `u_name`, `u`.`u_username` AS `u_username`, `u`.`u_email` AS `u_email`, `u`.`u_role` AS `u_role`, `u`.`u_status` AS `u_status`, `u`.`created_at` AS `created_at`, `u`.`last_login` AS `last_login`, `creator`.`u_name` AS `created_by_name` FROM (`users` `u` left join `users` `creator` on(`u`.`created_by` = `creator`.`u_id`)) ;

-- --------------------------------------------------------

--
-- Structure for view `venue_screens_summary`
--
DROP TABLE IF EXISTS `venue_screens_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `venue_screens_summary`  AS SELECT `v`.`id` AS `venue_id`, `v`.`venue_name` AS `venue_name`, `v`.`venue_location` AS `venue_location`, `v`.`contact_number` AS `contact_number`, `v`.`operating_hours` AS `operating_hours`, count(distinct `s`.`id`) AS `total_screens`, sum(`s`.`capacity`) AS `total_capacity`, count(distinct `sp`.`id`) AS `total_seat_plans`, sum(`sp`.`total_seats`) AS `total_seats_configured` FROM ((`venues` `v` left join `screens` `s` on(`v`.`id` = `s`.`venue_id` and `s`.`is_active` = 1)) left join `seat_plans` `sp` on(`s`.`id` = `sp`.`screen_id` and `sp`.`is_active` = 1)) WHERE `v`.`is_active` = 1 GROUP BY `v`.`id`, `v`.`venue_name`, `v`.`venue_location` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_activity_log`
--
ALTER TABLE `admin_activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_admin_id` (`admin_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `aisles`
--
ALTER TABLE `aisles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_aisle_per_plan` (`seat_plan_id`,`position_type`,`position_value`),
  ADD KEY `aisle_type_id` (`aisle_type_id`),
  ADD KEY `idx_seat_plan_id` (`seat_plan_id`),
  ADD KEY `idx_position_type` (`position_type`);

--
-- Indexes for table `aisle_types`
--
ALTER TABLE `aisle_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `booked_seats`
--
ALTER TABLE `booked_seats`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_booking_seat` (`booking_id`,`seat_availability_id`),
  ADD KEY `seat_type_id` (`seat_type_id`),
  ADD KEY `idx_booking_id` (`booking_id`),
  ADD KEY `idx_seat_availability` (`seat_availability_id`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `booking_reference` (`booking_reference`),
  ADD KEY `verified_by` (`verified_by`),
  ADD KEY `idx_booking_reference` (`booking_reference`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_schedule_id` (`schedule_id`),
  ADD KEY `idx_payment_status` (`payment_status`),
  ADD KEY `idx_attendance_status` (`attendance_status`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `customer_activity_log`
--
ALTER TABLE `customer_activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `movie_id` (`movie_id`),
  ADD KEY `schedule_id` (`schedule_id`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `idx_customer_id` (`customer_id`),
  ADD KEY `idx_action_type` (`action_type`);

--
-- Indexes for table `manual_payments`
--
ALTER TABLE `manual_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `payment_method_id` (`payment_method_id`),
  ADD KEY `verified_by` (`verified_by`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_booking_id` (`booking_id`);

--
-- Indexes for table `movies`
--
ALTER TABLE `movies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `added_by` (`added_by`),
  ADD KEY `updated_by` (`updated_by`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_title` (`title`);
ALTER TABLE `movies` ADD FULLTEXT KEY `idx_movie_search` (`title`,`description`);

--
-- Indexes for table `movie_screen_prices`
--
ALTER TABLE `movie_screen_prices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_movie_screen_seat` (`movie_id`,`screen_id`,`seat_type_id`),
  ADD KEY `seat_type_id` (`seat_type_id`),
  ADD KEY `idx_movie_id` (`movie_id`),
  ADD KEY `idx_screen_id` (`screen_id`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- Indexes for table `movie_venues`
--
ALTER TABLE `movie_venues`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_movie_venue` (`movie_id`,`venue_id`),
  ADD KEY `idx_movie_id` (`movie_id`),
  ADD KEY `idx_venue_id` (`venue_id`);

--
-- Indexes for table `payment_methods`
--
ALTER TABLE `payment_methods`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- Indexes for table `revenue_tracking`
--
ALTER TABLE `revenue_tracking`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `idx_venue_id` (`venue_id`),
  ADD KEY `idx_screen_id` (`screen_id`),
  ADD KEY `idx_transaction_date` (`transaction_date`);

--
-- Indexes for table `schedules`
--
ALTER TABLE `schedules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_schedule` (`screen_id`,`show_date`,`showtime`),
  ADD KEY `seat_plan_id` (`seat_plan_id`),
  ADD KEY `idx_show_date` (`show_date`),
  ADD KEY `idx_movie_id` (`movie_id`),
  ADD KEY `idx_screen_id` (`screen_id`),
  ADD KEY `idx_show_datetime` (`show_date`,`showtime`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- Indexes for table `screens`
--
ALTER TABLE `screens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_screen_per_venue` (`venue_id`,`screen_number`),
  ADD KEY `idx_venue_id` (`venue_id`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- Indexes for table `seat_availability`
--
ALTER TABLE `seat_availability`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_seat_per_schedule` (`schedule_id`,`seat_number`),
  ADD KEY `seat_plan_detail_id` (`seat_plan_detail_id`),
  ADD KEY `seat_type_id` (`seat_type_id`),
  ADD KEY `idx_schedule_id` (`schedule_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_locked` (`locked_by`,`locked_at`);

--
-- Indexes for table `seat_plans`
--
ALTER TABLE `seat_plans`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_plan_per_screen` (`screen_id`,`plan_name`),
  ADD KEY `idx_screen_id` (`screen_id`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- Indexes for table `seat_plan_details`
--
ALTER TABLE `seat_plan_details`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_seat_in_plan` (`seat_plan_id`,`seat_number`),
  ADD KEY `idx_seat_plan_id` (`seat_plan_id`),
  ADD KEY `idx_seat_number` (`seat_number`),
  ADD KEY `idx_is_enabled` (`is_enabled`),
  ADD KEY `idx_seat_type` (`seat_type_id`);

--
-- Indexes for table `seat_types`
--
ALTER TABLE `seat_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_sort_order` (`sort_order`);

--
-- Indexes for table `staff_activity_log`
--
ALTER TABLE `staff_activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_staff_id` (`staff_id`),
  ADD KEY `idx_booking_id` (`booking_id`);

--
-- Indexes for table `suggestions`
--
ALTER TABLE `suggestions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`u_id`),
  ADD UNIQUE KEY `u_username` (`u_username`),
  ADD UNIQUE KEY `u_email` (`u_email`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_email` (`u_email`),
  ADD KEY `idx_username` (`u_username`),
  ADD KEY `idx_role` (`u_role`);

--
-- Indexes for table `venues`
--
ALTER TABLE `venues`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_venue_name` (`venue_name`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_activity_log`
--
ALTER TABLE `admin_activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `aisles`
--
ALTER TABLE `aisles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `aisle_types`
--
ALTER TABLE `aisle_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `booked_seats`
--
ALTER TABLE `booked_seats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=65;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `customer_activity_log`
--
ALTER TABLE `customer_activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `manual_payments`
--
ALTER TABLE `manual_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `movies`
--
ALTER TABLE `movies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `movie_screen_prices`
--
ALTER TABLE `movie_screen_prices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT for table `movie_venues`
--
ALTER TABLE `movie_venues`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment_methods`
--
ALTER TABLE `payment_methods`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `revenue_tracking`
--
ALTER TABLE `revenue_tracking`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `schedules`
--
ALTER TABLE `schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `screens`
--
ALTER TABLE `screens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `seat_availability`
--
ALTER TABLE `seat_availability`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=335;

--
-- AUTO_INCREMENT for table `seat_plans`
--
ALTER TABLE `seat_plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `seat_plan_details`
--
ALTER TABLE `seat_plan_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=973;

--
-- AUTO_INCREMENT for table `seat_types`
--
ALTER TABLE `seat_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

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
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `u_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

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
-- Constraints for table `aisles`
--
ALTER TABLE `aisles`
  ADD CONSTRAINT `aisles_ibfk_1` FOREIGN KEY (`seat_plan_id`) REFERENCES `seat_plans` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `aisles_ibfk_2` FOREIGN KEY (`aisle_type_id`) REFERENCES `aisle_types` (`id`);

--
-- Constraints for table `booked_seats`
--
ALTER TABLE `booked_seats`
  ADD CONSTRAINT `booked_seats_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `booked_seats_ibfk_2` FOREIGN KEY (`seat_availability_id`) REFERENCES `seat_availability` (`id`),
  ADD CONSTRAINT `booked_seats_ibfk_3` FOREIGN KEY (`seat_type_id`) REFERENCES `seat_types` (`id`);

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`u_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`schedule_id`) REFERENCES `schedules` (`id`),
  ADD CONSTRAINT `bookings_ibfk_3` FOREIGN KEY (`verified_by`) REFERENCES `users` (`u_id`) ON DELETE SET NULL;

--
-- Constraints for table `customer_activity_log`
--
ALTER TABLE `customer_activity_log`
  ADD CONSTRAINT `customer_activity_log_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `users` (`u_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `customer_activity_log_ibfk_2` FOREIGN KEY (`movie_id`) REFERENCES `movies` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `customer_activity_log_ibfk_3` FOREIGN KEY (`schedule_id`) REFERENCES `schedules` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `customer_activity_log_ibfk_4` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `manual_payments`
--
ALTER TABLE `manual_payments`
  ADD CONSTRAINT `manual_payments_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `manual_payments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`u_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `manual_payments_ibfk_3` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods` (`id`),
  ADD CONSTRAINT `manual_payments_ibfk_4` FOREIGN KEY (`verified_by`) REFERENCES `users` (`u_id`) ON DELETE SET NULL;

--
-- Constraints for table `movies`
--
ALTER TABLE `movies`
  ADD CONSTRAINT `movies_ibfk_1` FOREIGN KEY (`added_by`) REFERENCES `users` (`u_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `movies_ibfk_2` FOREIGN KEY (`updated_by`) REFERENCES `users` (`u_id`) ON DELETE SET NULL;

--
-- Constraints for table `movie_screen_prices`
--
ALTER TABLE `movie_screen_prices`
  ADD CONSTRAINT `movie_screen_prices_ibfk_1` FOREIGN KEY (`movie_id`) REFERENCES `movies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `movie_screen_prices_ibfk_2` FOREIGN KEY (`screen_id`) REFERENCES `screens` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `movie_screen_prices_ibfk_3` FOREIGN KEY (`seat_type_id`) REFERENCES `seat_types` (`id`);

--
-- Constraints for table `movie_venues`
--
ALTER TABLE `movie_venues`
  ADD CONSTRAINT `movie_venues_ibfk_1` FOREIGN KEY (`movie_id`) REFERENCES `movies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `movie_venues_ibfk_2` FOREIGN KEY (`venue_id`) REFERENCES `venues` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `revenue_tracking`
--
ALTER TABLE `revenue_tracking`
  ADD CONSTRAINT `revenue_tracking_ibfk_1` FOREIGN KEY (`venue_id`) REFERENCES `venues` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `revenue_tracking_ibfk_2` FOREIGN KEY (`screen_id`) REFERENCES `screens` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `revenue_tracking_ibfk_3` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `schedules`
--
ALTER TABLE `schedules`
  ADD CONSTRAINT `schedules_ibfk_1` FOREIGN KEY (`movie_id`) REFERENCES `movies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `schedules_ibfk_2` FOREIGN KEY (`screen_id`) REFERENCES `screens` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `schedules_ibfk_3` FOREIGN KEY (`seat_plan_id`) REFERENCES `seat_plans` (`id`);

--
-- Constraints for table `screens`
--
ALTER TABLE `screens`
  ADD CONSTRAINT `screens_ibfk_1` FOREIGN KEY (`venue_id`) REFERENCES `venues` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `seat_availability`
--
ALTER TABLE `seat_availability`
  ADD CONSTRAINT `seat_availability_ibfk_1` FOREIGN KEY (`schedule_id`) REFERENCES `schedules` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `seat_availability_ibfk_2` FOREIGN KEY (`seat_plan_detail_id`) REFERENCES `seat_plan_details` (`id`),
  ADD CONSTRAINT `seat_availability_ibfk_3` FOREIGN KEY (`seat_type_id`) REFERENCES `seat_types` (`id`),
  ADD CONSTRAINT `seat_availability_ibfk_4` FOREIGN KEY (`locked_by`) REFERENCES `users` (`u_id`) ON DELETE SET NULL;

--
-- Constraints for table `seat_plans`
--
ALTER TABLE `seat_plans`
  ADD CONSTRAINT `seat_plans_ibfk_1` FOREIGN KEY (`screen_id`) REFERENCES `screens` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `seat_plan_details`
--
ALTER TABLE `seat_plan_details`
  ADD CONSTRAINT `seat_plan_details_ibfk_1` FOREIGN KEY (`seat_plan_id`) REFERENCES `seat_plans` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `seat_plan_details_ibfk_2` FOREIGN KEY (`seat_type_id`) REFERENCES `seat_types` (`id`);

--
-- Constraints for table `staff_activity_log`
--
ALTER TABLE `staff_activity_log`
  ADD CONSTRAINT `staff_activity_log_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `users` (`u_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `staff_activity_log_ibfk_2` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `suggestions`
--
ALTER TABLE `suggestions`
  ADD CONSTRAINT `suggestions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`u_id`) ON DELETE SET NULL;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`u_id`) ON DELETE SET NULL;

DELIMITER $$
--
-- Events
--
CREATE DEFINER=`root`@`localhost` EVENT `release_expired_locks_event` ON SCHEDULE EVERY 1 MINUTE STARTS '2026-04-29 12:07:02' ON COMPLETION NOT PRESERVE ENABLE DO BEGIN
    CALL release_expired_seat_locks();
END$$

CREATE DEFINER=`root`@`localhost` EVENT `cancel_expired_bookings_event` ON SCHEDULE EVERY 1 HOUR STARTS '2026-04-29 12:07:02' ON COMPLETION NOT PRESERVE ENABLE DO BEGIN
    CALL cancel_expired_pending_bookings();
END$$

DELIMITER ;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
