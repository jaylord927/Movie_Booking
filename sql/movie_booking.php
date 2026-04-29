-- =====================================================
-- COMPLETE DATABASE: movie_booking 
-- FULLY NORMALIZED WITH SCREENS/AUDITORIUMS & AISLES
-- CORRECT PASSWORD HASH FOR '123123'
-- =====================================================

DROP DATABASE IF EXISTS movie_booking;
CREATE DATABASE movie_booking;
USE movie_booking;

-- =====================================================
-- 1. USERS TABLE
-- =====================================================
CREATE TABLE users (
    u_id INT AUTO_INCREMENT PRIMARY KEY,
    u_name VARCHAR(100) NOT NULL,
    u_username VARCHAR(50) UNIQUE NOT NULL,
    u_email VARCHAR(100) UNIQUE NOT NULL,
    u_pass VARCHAR(255) NOT NULL,
    u_role ENUM('Admin', 'Customer', 'Owner', 'Staff') DEFAULT 'Customer',
    u_status ENUM('Active', 'Inactive') DEFAULT 'Active',
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL DEFAULT NULL,
    is_visible TINYINT(1) DEFAULT 1,
    FOREIGN KEY (created_by) REFERENCES users(u_id) ON DELETE SET NULL,
    INDEX idx_email (u_email),
    INDEX idx_username (u_username),
    INDEX idx_role (u_role)
);

-- =====================================================
-- 2. VENUES TABLE (PHYSICAL LOCATIONS)
-- =====================================================
CREATE TABLE venues (
    id INT AUTO_INCREMENT PRIMARY KEY,
    venue_name VARCHAR(255) NOT NULL COMMENT 'e.g., SM Cinema – SM Seaside',
    venue_location VARCHAR(500) NOT NULL COMMENT 'Full address',
    google_maps_link VARCHAR(500) NULL,
    venue_photo_path VARCHAR(500) NULL,
    contact_number VARCHAR(20) NULL,
    operating_hours VARCHAR(255) NULL,
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_venue_name (venue_name),
    INDEX idx_is_active (is_active)
);

-- =====================================================
-- 3. SCREENS / AUDITORIUMS TABLE (PER VENUE)
-- =====================================================
CREATE TABLE screens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    venue_id INT NOT NULL,
    screen_name VARCHAR(100) NOT NULL COMMENT 'e.g., Screen 1, Cinema 1, Hall A',
    screen_number INT NOT NULL,
    capacity INT NOT NULL COMMENT 'Maximum seats',
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (venue_id) REFERENCES venues(id) ON DELETE CASCADE,
    UNIQUE KEY unique_screen_per_venue (venue_id, screen_number),
    INDEX idx_venue_id (venue_id),
    INDEX idx_is_active (is_active)
);

-- =====================================================
-- 4. SEAT_TYPES TABLE
-- =====================================================
CREATE TABLE seat_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    default_price DECIMAL(10,2) NOT NULL,
    color_code VARCHAR(7) DEFAULT '#3498db',
    description VARCHAR(255) NULL,
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_is_active (is_active),
    INDEX idx_sort_order (sort_order)
);

-- Insert default seat types
INSERT INTO seat_types (name, default_price, color_code, sort_order) VALUES
('Standard', 350.00, '#3498db', 1),
('Premium', 450.00, '#FFD700', 2),
('Sweet Spot', 550.00, '#e74c3c', 3);

-- =====================================================
-- 5. AISLE_TYPES TABLE
-- =====================================================
CREATE TABLE aisle_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    color_code VARCHAR(7) DEFAULT '#2c3e50',
    width INT DEFAULT 1 COMMENT 'Aisle width in columns/rows',
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_is_active (is_active)
);

-- Insert default aisle types
INSERT INTO aisle_types (name, description, color_code, width) VALUES
('Row Aisle', 'Horizontal aisle between rows', '#2c3e50', 1),
('Column Aisle', 'Vertical aisle between columns', '#2c3e50', 1),
('Cross Aisle', 'Both row and column aisle', '#2c3e50', 2);

-- =====================================================
-- 6. SEAT_PLANS TABLE (PER SCREEN)
-- =====================================================
CREATE TABLE seat_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    screen_id INT NOT NULL,
    plan_name VARCHAR(100) NOT NULL,
    total_rows INT NOT NULL,
    total_columns INT NOT NULL,
    total_seats INT GENERATED ALWAYS AS (total_rows * total_columns) STORED,
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (screen_id) REFERENCES screens(id) ON DELETE CASCADE,
    UNIQUE KEY unique_plan_per_screen (screen_id, plan_name),
    INDEX idx_screen_id (screen_id),
    INDEX idx_is_active (is_active)
);

-- =====================================================
-- 7. SEAT_PLAN_DETAILS (INDIVIDUAL SEATS)
-- =====================================================
CREATE TABLE seat_plan_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seat_plan_id INT NOT NULL,
    seat_row CHAR(1) NOT NULL,
    seat_column INT NOT NULL,
    seat_number VARCHAR(10) NOT NULL,
    seat_type_id INT NULL COMMENT 'NULL means no seat type assigned (empty seat)',
    is_enabled BOOLEAN DEFAULT 1,
    custom_price DECIMAL(10,2) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (seat_plan_id) REFERENCES seat_plans(id) ON DELETE CASCADE,
    FOREIGN KEY (seat_type_id) REFERENCES seat_types(id) ON DELETE SET NULL,
    UNIQUE KEY unique_seat_in_plan (seat_plan_id, seat_number),
    INDEX idx_seat_plan_id (seat_plan_id),
    INDEX idx_seat_number (seat_number),
    INDEX idx_is_enabled (is_enabled),
    INDEX idx_seat_type (seat_type_id)
);

-- =====================================================
-- 8. AISLES TABLE (SEPARATE FROM SEATS - SPACING ELEMENTS)
-- =====================================================
CREATE TABLE aisles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seat_plan_id INT NOT NULL,
    aisle_type_id INT NOT NULL,
    position_value INT NOT NULL COMMENT 'Row number or Column number where aisle is placed',
    position_type ENUM('row', 'column') NOT NULL COMMENT 'Whether aisle is between rows or between columns',
    width INT DEFAULT 1 COMMENT 'Aisle width (number of spaces)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (seat_plan_id) REFERENCES seat_plans(id) ON DELETE CASCADE,
    FOREIGN KEY (aisle_type_id) REFERENCES aisle_types(id),
    UNIQUE KEY unique_aisle_per_plan (seat_plan_id, position_type, position_value),
    INDEX idx_seat_plan_id (seat_plan_id),
    INDEX idx_position_type (position_type)
);

-- =====================================================
-- 9. MOVIES TABLE
-- =====================================================
CREATE TABLE movies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    director VARCHAR(255) NULL,
    genre VARCHAR(100) NULL,
    duration VARCHAR(20) NULL,
    rating VARCHAR(10) NULL,
    description TEXT NULL,
    poster_url VARCHAR(500) NULL,
    trailer_url VARCHAR(500) NULL,
    is_active BOOLEAN DEFAULT 1,
    added_by INT NULL,
    updated_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_updated TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (added_by) REFERENCES users(u_id) ON DELETE SET NULL,
    FOREIGN KEY (updated_by) REFERENCES users(u_id) ON DELETE SET NULL,
    INDEX idx_is_active (is_active),
    INDEX idx_title (title),
    FULLTEXT INDEX idx_movie_search (title, description)
);

-- =====================================================
-- 10. MOVIE_SCREEN_PRICES (PRICING PER SCREEN + MOVIE + SEAT TYPE)
-- =====================================================
CREATE TABLE movie_screen_prices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    movie_id INT NOT NULL,
    screen_id INT NOT NULL,
    seat_type_id INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE,
    FOREIGN KEY (screen_id) REFERENCES screens(id) ON DELETE CASCADE,
    FOREIGN KEY (seat_type_id) REFERENCES seat_types(id),
    UNIQUE KEY unique_movie_screen_seat (movie_id, screen_id, seat_type_id),
    INDEX idx_movie_id (movie_id),
    INDEX idx_screen_id (screen_id),
    INDEX idx_is_active (is_active)
);

-- =====================================================
-- 11. SCHEDULES TABLE (LINKS MOVIE + SCREEN + DATE + TIME)
-- =====================================================
CREATE TABLE schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    movie_id INT NOT NULL,
    screen_id INT NOT NULL,
    seat_plan_id INT NOT NULL,
    show_date DATE NOT NULL,
    showtime TIME NOT NULL,
    base_price DECIMAL(10,2) NOT NULL COMMENT 'Base price for standard seats',
    available_seats INT NOT NULL DEFAULT 0 COMMENT 'Dynamically updated',
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE,
    FOREIGN KEY (screen_id) REFERENCES screens(id) ON DELETE CASCADE,
    FOREIGN KEY (seat_plan_id) REFERENCES seat_plans(id),
    UNIQUE KEY unique_schedule (screen_id, show_date, showtime),
    INDEX idx_show_date (show_date),
    INDEX idx_movie_id (movie_id),
    INDEX idx_screen_id (screen_id),
    INDEX idx_show_datetime (show_date, showtime),
    INDEX idx_is_active (is_active)
);

-- =====================================================
-- 12. SEAT_AVAILABILITY (PER SCHEDULE)
-- =====================================================
CREATE TABLE seat_availability (
    id INT AUTO_INCREMENT PRIMARY KEY,
    schedule_id INT NOT NULL,
    seat_plan_detail_id INT NOT NULL,
    seat_number VARCHAR(10) NOT NULL,
    seat_type_id INT NULL,
    price DECIMAL(10,2) NOT NULL,
    status ENUM('available', 'reserved', 'booked') DEFAULT 'available',
    locked_by INT NULL,
    locked_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (schedule_id) REFERENCES schedules(id) ON DELETE CASCADE,
    FOREIGN KEY (seat_plan_detail_id) REFERENCES seat_plan_details(id),
    FOREIGN KEY (seat_type_id) REFERENCES seat_types(id) ON DELETE SET NULL,
    FOREIGN KEY (locked_by) REFERENCES users(u_id) ON DELETE SET NULL,
    UNIQUE KEY unique_seat_per_schedule (schedule_id, seat_number),
    INDEX idx_schedule_id (schedule_id),
    INDEX idx_status (status),
    INDEX idx_locked (locked_by, locked_at)
);

-- =====================================================
-- 13. BOOKINGS TABLE
-- =====================================================
CREATE TABLE bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_reference VARCHAR(20) UNIQUE NOT NULL,
    user_id INT NOT NULL,
    schedule_id INT NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    payment_status ENUM('pending', 'paid', 'refunded', 'pending_verification') DEFAULT 'pending',
    attendance_status ENUM('pending', 'present', 'completed') DEFAULT 'pending',
    status ENUM('ongoing', 'done', 'cancelled') DEFAULT 'ongoing',
    qr_code VARCHAR(255) NULL,
    verified_at TIMESTAMP NULL,
    verified_by INT NULL,
    booked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_visible TINYINT(1) DEFAULT 1,
    FOREIGN KEY (user_id) REFERENCES users(u_id) ON DELETE CASCADE,
    FOREIGN KEY (schedule_id) REFERENCES schedules(id),
    FOREIGN KEY (verified_by) REFERENCES users(u_id) ON DELETE SET NULL,
    INDEX idx_booking_reference (booking_reference),
    INDEX idx_user_id (user_id),
    INDEX idx_schedule_id (schedule_id),
    INDEX idx_payment_status (payment_status),
    INDEX idx_attendance_status (attendance_status),
    INDEX idx_status (status)
);

-- =====================================================
-- 14. BOOKED_SEATS
-- =====================================================
CREATE TABLE booked_seats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    seat_availability_id INT NOT NULL,
    seat_number VARCHAR(10) NOT NULL,
    seat_type_id INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (seat_availability_id) REFERENCES seat_availability(id),
    FOREIGN KEY (seat_type_id) REFERENCES seat_types(id),
    UNIQUE KEY unique_booking_seat (booking_id, seat_availability_id),
    INDEX idx_booking_id (booking_id),
    INDEX idx_seat_availability (seat_availability_id)
);

-- =====================================================
-- 15. MOVIE_VENUES (FOR BACKWARD COMPATIBILITY - OPTIONAL)
-- =====================================================
CREATE TABLE movie_venues (
    id INT AUTO_INCREMENT PRIMARY KEY,
    movie_id INT NOT NULL,
    venue_id INT NOT NULL,
    is_primary_venue BOOLEAN DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE,
    FOREIGN KEY (venue_id) REFERENCES venues(id) ON DELETE CASCADE,
    UNIQUE KEY unique_movie_venue (movie_id, venue_id),
    INDEX idx_movie_id (movie_id),
    INDEX idx_venue_id (venue_id)
);

-- =====================================================
-- 16. ADMIN ACTIVITY LOG
-- =====================================================
CREATE TABLE admin_activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    details TEXT,
    target_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(u_id) ON DELETE CASCADE,
    INDEX idx_admin_id (admin_id),
    INDEX idx_created_at (created_at)
);

-- =====================================================
-- 17. CUSTOMER ACTIVITY LOG
-- =====================================================
CREATE TABLE customer_activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    details TEXT,
    movie_id INT NULL,
    schedule_id INT NULL,
    booking_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES users(u_id) ON DELETE CASCADE,
    FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE SET NULL,
    FOREIGN KEY (schedule_id) REFERENCES schedules(id) ON DELETE SET NULL,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL,
    INDEX idx_customer_id (customer_id),
    INDEX idx_action_type (action_type)
);

-- =====================================================
-- 18. STAFF ACTIVITY LOG
-- =====================================================
CREATE TABLE staff_activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    booking_id INT NULL,
    details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES users(u_id) ON DELETE CASCADE,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL,
    INDEX idx_staff_id (staff_id),
    INDEX idx_booking_id (booking_id)
);

-- =====================================================
-- 19. SUGGESTIONS TABLE
-- =====================================================
CREATE TABLE suggestions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    user_name VARCHAR(100) NOT NULL,
    user_email VARCHAR(100) NOT NULL,
    suggestion TEXT NOT NULL,
    status ENUM('Pending', 'Reviewed', 'Implemented') DEFAULT 'Pending',
    admin_notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(u_id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);

-- =====================================================
-- 20. PAYMENT METHODS TABLE
-- =====================================================
CREATE TABLE payment_methods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    method_name VARCHAR(50) NOT NULL,
    account_name VARCHAR(100) NOT NULL,
    account_number VARCHAR(50) NOT NULL,
    qr_code_path VARCHAR(255) NULL,
    instructions TEXT NULL,
    is_active BOOLEAN DEFAULT 1,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_is_active (is_active)
);

INSERT INTO payment_methods (method_name, account_name, account_number, display_order) VALUES
('GCash', 'Movie Ticket Booking', '09267630945', 1),
('PayMaya', 'Movie Ticket Booking', '09267630945', 2);

-- =====================================================
-- 21. MANUAL PAYMENTS TABLE
-- =====================================================
CREATE TABLE manual_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    user_id INT NOT NULL,
    payment_method_id INT NOT NULL,
    reference_number VARCHAR(100) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    screenshot_path VARCHAR(255) NOT NULL,
    status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
    admin_notes TEXT NULL,
    verified_by INT NULL,
    verified_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(u_id) ON DELETE CASCADE,
    FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id),
    FOREIGN KEY (verified_by) REFERENCES users(u_id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_booking_id (booking_id)
);

-- =====================================================
-- 22. REVENUE_TRACKING TABLE
-- =====================================================
CREATE TABLE revenue_tracking (
    id INT AUTO_INCREMENT PRIMARY KEY,
    venue_id INT NOT NULL,
    screen_id INT NOT NULL,
    booking_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('paymongo', 'manual') NOT NULL,
    transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (venue_id) REFERENCES venues(id) ON DELETE CASCADE,
    FOREIGN KEY (screen_id) REFERENCES screens(id) ON DELETE CASCADE,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    INDEX idx_venue_id (venue_id),
    INDEX idx_screen_id (screen_id),
    INDEX idx_transaction_date (transaction_date)
);

-- =====================================================
-- STORED PROCEDURES
-- =====================================================

-- Release expired seat locks (5 minutes)
DELIMITER //
CREATE PROCEDURE release_expired_seat_locks()
BEGIN
    UPDATE seat_availability 
    SET status = 'available', 
        locked_by = NULL, 
        locked_at = NULL
    WHERE status = 'reserved' 
    AND locked_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE);
END //
DELIMITER ;

-- Generate seats for a seat plan
DELIMITER //
CREATE PROCEDURE generate_seats_for_plan(IN p_plan_id INT)
BEGIN
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
    
    -- Calculate sweet spot rows (middle 30%)
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
END //
DELIMITER ;

-- Create schedule with seat availability
DELIMITER //
CREATE PROCEDURE create_schedule_with_seats(
    IN p_movie_id INT,
    IN p_screen_id INT,
    IN p_seat_plan_id INT,
    IN p_show_date DATE,
    IN p_showtime TIME,
    IN p_base_price DECIMAL(10,2)
)
BEGIN
    DECLARE v_schedule_id INT;
    DECLARE v_exists INT;
    DECLARE v_total_seats INT;
    
    -- Check if schedule already exists
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
        
        -- Update available seats count
        SELECT COUNT(*) INTO v_total_seats FROM seat_availability WHERE schedule_id = v_schedule_id;
        UPDATE schedules SET available_seats = v_total_seats WHERE id = v_schedule_id;
    END IF;
END //
DELIMITER ;

-- Cancel expired pending bookings (3 hours)
DELIMITER //
CREATE PROCEDURE cancel_expired_pending_bookings()
BEGIN
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
        
        -- Update booking status
        UPDATE bookings 
        SET status = 'cancelled', payment_status = 'refunded' 
        WHERE id = v_booking_id;
        
        -- Release seats back to availability
        UPDATE seat_availability sa
        JOIN booked_seats bs ON sa.id = bs.seat_availability_id
        SET sa.status = 'available', sa.locked_by = NULL, sa.locked_at = NULL
        WHERE bs.booking_id = v_booking_id;
        
        -- Update schedule available seats
        UPDATE schedules 
        SET available_seats = available_seats + (SELECT COUNT(*) FROM booked_seats WHERE booking_id = v_booking_id)
        WHERE id = v_schedule_id;
        
    END LOOP;
    
    CLOSE cur;
END //
DELIMITER ;

-- Generate booking reference
DELIMITER //
CREATE FUNCTION generate_booking_reference() 
RETURNS VARCHAR(20)
DETERMINISTIC
BEGIN
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
END //
DELIMITER ;

-- =====================================================
-- TRIGGERS
-- =====================================================

-- Auto-cancel bookings when schedule passes
DELIMITER //
CREATE TRIGGER after_booking_cancel
AFTER UPDATE ON bookings
FOR EACH ROW
BEGIN
    IF NEW.status = 'cancelled' AND OLD.status != 'cancelled' THEN
        UPDATE seat_availability sa
        JOIN booked_seats bs ON sa.id = bs.seat_availability_id
        SET sa.status = 'available'
        WHERE bs.booking_id = NEW.id;
    END IF;
END //
DELIMITER ;

-- Update schedule available seats on booking
DELIMITER //
CREATE TRIGGER after_booking_insert
AFTER INSERT ON bookings
FOR EACH ROW
BEGIN
    DECLARE v_seat_count INT;
    SELECT COUNT(*) INTO v_seat_count FROM booked_seats WHERE booking_id = NEW.id;
    UPDATE schedules SET available_seats = available_seats - v_seat_count WHERE id = NEW.schedule_id;
END //
DELIMITER ;

-- =====================================================
-- VIEWS FOR EASY REPORTING
-- =====================================================

-- Users with creator names
CREATE VIEW users_with_creators AS
SELECT 
    u.u_id,
    u.u_name,
    u.u_username,
    u.u_email,
    u.u_role,
    u.u_status,
    u.created_at,
    u.last_login,
    creator.u_name AS created_by_name
FROM users u
LEFT JOIN users creator ON u.created_by = creator.u_id;

-- Venues with screen summary
CREATE VIEW venue_screens_summary AS
SELECT 
    v.id AS venue_id,
    v.venue_name,
    v.venue_location,
    v.contact_number,
    v.operating_hours,
    COUNT(DISTINCT s.id) AS total_screens,
    SUM(s.capacity) AS total_capacity,
    COUNT(DISTINCT sp.id) AS total_seat_plans,
    SUM(sp.total_seats) AS total_seats_configured
FROM venues v
LEFT JOIN screens s ON v.id = s.venue_id AND s.is_active = 1
LEFT JOIN seat_plans sp ON s.id = sp.screen_id AND sp.is_active = 1
WHERE v.is_active = 1
GROUP BY v.id, v.venue_name, v.venue_location;

-- Schedule availability summary
CREATE VIEW schedule_availability_summary AS
SELECT 
    sch.id AS schedule_id,
    sch.movie_id,
    m.title AS movie_title,
    sch.screen_id,
    s.screen_name,
    s.screen_number,
    v.venue_name,
    sch.show_date,
    sch.showtime,
    sch.base_price,
    sch.available_seats,
    COUNT(sa.id) AS total_seats,
    COUNT(CASE WHEN sa.status = 'available' THEN 1 END) AS available_seats,
    COUNT(CASE WHEN sa.status = 'booked' THEN 1 END) AS booked_seats,
    COUNT(CASE WHEN sa.status = 'reserved' THEN 1 END) AS reserved_seats,
    ROUND(COUNT(CASE WHEN sa.status = 'booked' THEN 1 END) / NULLIF(COUNT(sa.id), 0) * 100, 2) AS occupancy_rate,
    CASE 
        WHEN COUNT(CASE WHEN sa.status = 'available' THEN 1 END) = 0 THEN 'sold_out'
        WHEN sch.show_date < CURDATE() THEN 'expired'
        WHEN sch.show_date = CURDATE() AND sch.showtime < CURTIME() THEN 'expired'
        ELSE 'active'
    END AS schedule_status
FROM schedules sch
JOIN movies m ON sch.movie_id = m.id AND m.is_active = 1
JOIN screens s ON sch.screen_id = s.id
JOIN venues v ON s.venue_id = v.id
JOIN seat_availability sa ON sch.id = sa.schedule_id
WHERE sch.is_active = 1
GROUP BY sch.id, sch.movie_id, m.title, sch.screen_id, s.screen_name, s.screen_number, v.venue_name, sch.show_date, sch.showtime, sch.base_price, sch.available_seats;

-- Seat plan details with aisle information
CREATE VIEW seat_plan_details_with_aisles AS
SELECT 
    sp.id AS seat_plan_id,
    sp.plan_name,
    sp.total_rows,
    sp.total_columns,
    sp.total_seats,
    s.id AS screen_id,
    s.screen_name,
    v.venue_name,
    COUNT(spd.id) AS total_configured_seats,
    COUNT(CASE WHEN spd.seat_type_id IS NOT NULL AND spd.is_enabled = 1 THEN 1 END) AS assigned_seats,
    COUNT(CASE WHEN spd.seat_type_id IS NULL AND spd.is_enabled = 1 THEN 1 END) AS empty_seats,
    COUNT(CASE WHEN st.name = 'Standard' THEN 1 END) AS standard_seats,
    COUNT(CASE WHEN st.name = 'Premium' THEN 1 END) AS premium_seats,
    COUNT(CASE WHEN st.name = 'Sweet Spot' THEN 1 END) AS sweet_spot_seats,
    (SELECT COUNT(*) FROM aisles WHERE seat_plan_id = sp.id AND position_type = 'row') AS row_aisles,
    (SELECT COUNT(*) FROM aisles WHERE seat_plan_id = sp.id AND position_type = 'column') AS column_aisles
FROM seat_plans sp
JOIN screens s ON sp.screen_id = s.id
JOIN venues v ON s.venue_id = v.id
LEFT JOIN seat_plan_details spd ON sp.id = spd.seat_plan_id
LEFT JOIN seat_types st ON spd.seat_type_id = st.id
WHERE sp.is_active = 1
GROUP BY sp.id, sp.plan_name, sp.total_rows, sp.total_columns, sp.total_seats, s.id, s.screen_name, v.venue_name;

-- Customer booking summary
CREATE VIEW customer_booking_summary AS
SELECT 
    u.u_id,
    u.u_name,
    u.u_email,
    COUNT(DISTINCT b.id) AS total_bookings,
    COUNT(DISTINCT CASE WHEN b.payment_status = 'paid' THEN b.id END) AS paid_bookings,
    COUNT(DISTINCT CASE WHEN b.status = 'ongoing' AND b.payment_status = 'paid' THEN b.id END) AS active_bookings,
    COALESCE(SUM(CASE WHEN b.payment_status = 'paid' THEN b.total_amount ELSE 0 END), 0) AS total_spent,
    MAX(b.booked_at) AS last_booking_date
FROM users u
LEFT JOIN bookings b ON u.u_id = b.user_id AND b.is_visible = 1
WHERE u.u_role = 'Customer'
GROUP BY u.u_id, u.u_name, u.u_email;

-- Movie performance summary
CREATE VIEW movie_performance_summary AS
SELECT 
    m.id AS movie_id,
    m.title,
    m.genre,
    m.rating,
    COUNT(DISTINCT sch.id) AS total_schedules,
    COUNT(DISTINCT b.id) AS total_bookings,
    COUNT(DISTINCT CASE WHEN b.payment_status = 'paid' THEN b.id END) AS paid_bookings,
    COALESCE(SUM(CASE WHEN b.payment_status = 'paid' THEN b.total_amount ELSE 0 END), 0) AS total_revenue,
    AVG(CASE WHEN b.payment_status = 'paid' THEN b.total_amount END) AS avg_ticket_price
FROM movies m
LEFT JOIN schedules sch ON m.id = sch.movie_id AND sch.is_active = 1
LEFT JOIN bookings b ON sch.id = b.schedule_id AND b.payment_status = 'paid'
WHERE m.is_active = 1
GROUP BY m.id, m.title, m.genre, m.rating
ORDER BY total_revenue DESC;

-- =====================================================
-- SAMPLE DATA (ONLY ADMIN AND OWNER - NO CUSTOMERS)
-- =====================================================

-- CORRECT PASSWORD HASH FOR '123123'
-- Generated using password_hash('123123', PASSWORD_DEFAULT)

INSERT INTO users (u_name, u_username, u_email, u_pass, u_role, u_status, created_at, is_visible) VALUES
('Jaylord Laspuna', 'jaylord', 'jaylord@gmail.com', '$2y$10$MjqJr8ZRNVRurbp.DMvg3ee1jJ.5mW/32Fov9nFkalhc.oJ7LL6Vm', 'Admin', 'Active', NOW(), 1),
('Denise Cana', 'denise', 'denisecana@gmail.com', '$2y$10$MjqJr8ZRNVRurbp.DMvg3ee1jJ.5mW/32Fov9nFkalhc.oJ7LL6Vm', 'Owner', 'Active', NOW(), 1);

-- Sample Venues (Physical Locations)
INSERT INTO venues (venue_name, venue_location, contact_number, operating_hours, is_active) VALUES
('SM Cinema – SM Seaside', 'SM Seaside Complex, F. Vestil St, Cebu City', '(032) 888-1234', '10:00 AM - 9:00 PM', 1),
('SM Cinema – SM City Cebu', 'North Reclamation Area, Cebu City', '(032) 888-5678', '10:00 AM - 9:00 PM', 1),
('Ayala Cinema – Ayala Center Cebu', 'Ayala Center, Cebu Business Park, Cebu City', '(032) 888-9012', '11:00 AM - 10:00 PM', 1);

-- Sample Screens per venue
INSERT INTO screens (venue_id, screen_name, screen_number, capacity, is_active) VALUES
(1, 'Cinema 1', 1, 120, 1),
(1, 'Cinema 2', 2, 150, 1),
(1, 'Cinema 3', 3, 100, 1),
(2, 'Cinema 1', 1, 200, 1),
(2, 'Cinema 2', 2, 180, 1),
(3, 'Cinema A', 1, 150, 1),
(3, 'Cinema B', 2, 130, 1);

-- Sample Seat Plans for each screen
INSERT INTO seat_plans (screen_id, plan_name, total_rows, total_columns, is_active) VALUES
(1, 'Standard Layout', 8, 10, 1),
(2, 'Standard Layout', 10, 12, 1),
(3, 'Standard Layout', 8, 8, 1),
(4, 'Large Hall Layout', 12, 14, 1),
(5, 'Wide Layout', 10, 15, 1),
(6, 'Premium Layout', 10, 12, 1),
(7, 'Intimate Layout', 8, 10, 1);

-- Generate seats for each seat plan
CALL generate_seats_for_plan(1);
CALL generate_seats_for_plan(2);
CALL generate_seats_for_plan(3);
CALL generate_seats_for_plan(4);
CALL generate_seats_for_plan(5);
CALL generate_seats_for_plan(6);
CALL generate_seats_for_plan(7);

-- Sample Movies
INSERT INTO movies (title, director, genre, duration, rating, description, is_active, added_by) VALUES
('Inception', 'Christopher Nolan', 'Sci-Fi, Action', '2h 28min', 'PG-13', 'A thief who steals corporate secrets through the use of dream-sharing technology is given the inverse task of planting an idea into the mind of a C.E.O.', 1, 1),
('The Dark Knight', 'Christopher Nolan', 'Action, Crime, Drama', '2h 32min', 'PG-13', 'When the menace known as the Joker wreaks havoc and chaos on the people of Gotham, Batman must accept one of the greatest psychological and physical tests of his ability to fight injustice.', 1, 1),
('Interstellar', 'Christopher Nolan', 'Adventure, Drama, Sci-Fi', '2h 49min', 'PG-13', 'A team of explorers travel through a wormhole in space in an attempt to ensure humanity\'s survival.', 1, 1),
('Avatar: The Way of Water', 'James Cameron', 'Sci-Fi, Action', '3h 12min', 'PG-13', 'Jake Sully lives with his newfound family formed on the planet of Pandora.', 1, 1),
('Top Gun: Maverick', 'Joseph Kosinski', 'Action, Drama', '2h 11min', 'PG-13', 'After more than thirty years of service as one of the Navy\'s top aviators.', 1, 1);

-- Sample Movie Screen Prices
INSERT INTO movie_screen_prices (movie_id, screen_id, seat_type_id, price, is_active) VALUES
(1, 1, 1, 380, 1), (1, 1, 2, 480, 1), (1, 1, 3, 580, 1),
(1, 2, 1, 380, 1), (1, 2, 2, 480, 1), (1, 2, 3, 580, 1),
(2, 1, 1, 390, 1), (2, 1, 2, 490, 1), (2, 1, 3, 590, 1),
(3, 4, 1, 400, 1), (3, 4, 2, 500, 1), (3, 4, 3, 600, 1),
(4, 2, 1, 420, 1), (4, 2, 2, 520, 1), (4, 2, 3, 620, 1),
(5, 3, 1, 410, 1), (5, 3, 2, 510, 1), (5, 3, 3, 610, 1);

-- Sample Schedules (Next 7 days)
INSERT IGNORE INTO schedules (movie_id, screen_id, seat_plan_id, show_date, showtime, base_price, is_active) VALUES
(1, 1, 1, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '14:00:00', 380, 1),
(1, 1, 1, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '19:00:00', 380, 1),
(2, 2, 2, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '15:30:00', 390, 1),
(2, 2, 2, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '20:00:00', 390, 1),
(3, 4, 4, DATE_ADD(CURDATE(), INTERVAL 3 DAY), '13:00:00', 400, 1),
(3, 4, 4, DATE_ADD(CURDATE(), INTERVAL 3 DAY), '18:30:00', 400, 1),
(4, 2, 2, DATE_ADD(CURDATE(), INTERVAL 4 DAY), '16:00:00', 420, 1),
(4, 2, 2, DATE_ADD(CURDATE(), INTERVAL 4 DAY), '21:00:00', 420, 1),
(5, 3, 3, DATE_ADD(CURDATE(), INTERVAL 5 DAY), '14:30:00', 410, 1),
(5, 3, 3, DATE_ADD(CURDATE(), INTERVAL 5 DAY), '19:30:00', 410, 1);

-- Create seat availability for schedules
CALL create_schedule_with_seats(1, 1, 1, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '14:00:00', 380);
CALL create_schedule_with_seats(1, 1, 1, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '19:00:00', 380);
CALL create_schedule_with_seats(2, 2, 2, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '15:30:00', 390);
CALL create_schedule_with_seats(2, 2, 2, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '20:00:00', 390);
CALL create_schedule_with_seats(3, 4, 4, DATE_ADD(CURDATE(), INTERVAL 3 DAY), '13:00:00', 400);
CALL create_schedule_with_seats(3, 4, 4, DATE_ADD(CURDATE(), INTERVAL 3 DAY), '18:30:00', 400);
CALL create_schedule_with_seats(4, 2, 2, DATE_ADD(CURDATE(), INTERVAL 4 DAY), '16:00:00', 420);
CALL create_schedule_with_seats(4, 2, 2, DATE_ADD(CURDATE(), INTERVAL 4 DAY), '21:00:00', 420);
CALL create_schedule_with_seats(5, 3, 3, DATE_ADD(CURDATE(), INTERVAL 5 DAY), '14:30:00', 410);
CALL create_schedule_with_seats(5, 3, 3, DATE_ADD(CURDATE(), INTERVAL 5 DAY), '19:30:00', 410);

-- =====================================================
-- EVENTS (Schedule automatic tasks)
-- =====================================================

-- Enable event scheduler
SET GLOBAL event_scheduler = ON;

-- Release expired seat locks every minute
DELIMITER //
CREATE EVENT IF NOT EXISTS release_expired_locks_event
ON SCHEDULE EVERY 1 MINUTE
DO
BEGIN
    CALL release_expired_seat_locks();
END //
DELIMITER ;

-- Cancel expired pending bookings every hour
DELIMITER //
CREATE EVENT IF NOT EXISTS cancel_expired_bookings_event
ON SCHEDULE EVERY 1 HOUR
DO
BEGIN
    CALL cancel_expired_pending_bookings();
END //
DELIMITER ;

-- =====================================================
-- FINAL VERIFICATION QUERIES
-- =====================================================

-- Check all tables exist
SELECT 'DATABASE CREATED SUCCESSFULLY!' AS status;

-- Show table counts
SELECT 
    (SELECT COUNT(*) FROM users) AS total_users,
    (SELECT COUNT(*) FROM venues) AS total_venues,
    (SELECT COUNT(*) FROM screens) AS total_screens,
    (SELECT COUNT(*) FROM movies) AS total_movies,
    (SELECT COUNT(*) FROM seat_plans) AS total_seat_plans,
    (SELECT COUNT(*) FROM seat_plan_details) AS total_seats_configured,
    (SELECT COUNT(*) FROM aisles) AS total_aisles,
    (SELECT COUNT(*) FROM schedules) AS total_schedules,
    (SELECT COUNT(*) FROM seat_availability) AS total_seats_available;

-- Show users (should only show Admin and Owner)
SELECT u_id, u_name, u_username, u_email, u_role, u_status FROM users;

-- Display login credentials
SELECT '=========================================' AS '';
SELECT '           LOGIN CREDENTIALS             ' AS '';
SELECT '=========================================' AS '';
SELECT 'Admin Username: jaylord' AS '';
SELECT 'Owner Username: denise' AS '';
SELECT 'Password for BOTH: 123123' AS '';
SELECT '=========================================' AS '';