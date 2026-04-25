-- =====================================================
-- DATABASE: movie_booking (WITH VENUE SEPARATION)
-- =====================================================

CREATE DATABASE IF NOT EXISTS movie_booking;
USE movie_booking;

-- =====================================================
-- 1. USERS TABLE (Updated with Staff role)
-- =====================================================
CREATE TABLE IF NOT EXISTS users (
    u_id INT AUTO_INCREMENT PRIMARY KEY,
    u_name VARCHAR(100) NOT NULL,
    u_username VARCHAR(50) UNIQUE NOT NULL,
    u_email VARCHAR(100) UNIQUE NOT NULL,
    u_pass VARCHAR(255) NOT NULL,
    u_role ENUM('Admin', 'Customer', 'Owner', 'Staff') DEFAULT 'Customer',
    u_status ENUM('Active', 'Inactive') DEFAULT 'Active',
    created_by INT NULL,
    created_by_name VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL DEFAULT NULL,
    is_visible TINYINT(1) DEFAULT 1,
    FOREIGN KEY (created_by) REFERENCES users(u_id) ON DELETE SET NULL
);

-- =====================================================
-- 2. VENUES TABLE (NEW - Separated from movies)
-- =====================================================
CREATE TABLE IF NOT EXISTS venues (
    id INT AUTO_INCREMENT PRIMARY KEY,
    venue_name VARCHAR(255) NOT NULL UNIQUE,
    venue_location VARCHAR(500) NOT NULL,
    google_maps_link VARCHAR(500) NULL,
    venue_photo_path VARCHAR(500) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_venue_name (venue_name)
);

-- =====================================================
-- 3. MOVIES TABLE (Updated with venue_id instead of venue columns)
-- =====================================================
CREATE TABLE IF NOT EXISTS movies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    director VARCHAR(255),
    genre VARCHAR(100),
    duration VARCHAR(20),
    rating VARCHAR(10),
    description TEXT,
    poster_url VARCHAR(500),
    trailer_url VARCHAR(500),
    venue_id INT NULL,                              -- Foreign key to venues table
    standard_price DECIMAL(10,2) DEFAULT 350.00,
    premium_price DECIMAL(10,2) DEFAULT 450.00,
    sweet_spot_price DECIMAL(10,2) DEFAULT 550.00,
    is_active BOOLEAN DEFAULT 1,
    added_by INT NULL,
    updated_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_updated TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (venue_id) REFERENCES venues(id) ON DELETE SET NULL,
    FOREIGN KEY (added_by) REFERENCES users(u_id) ON DELETE SET NULL,
    FOREIGN KEY (updated_by) REFERENCES users(u_id) ON DELETE SET NULL,
    INDEX idx_venue_id (venue_id),
    INDEX idx_is_active (is_active),
    FULLTEXT INDEX idx_movie_search (title, description)
);

-- =====================================================
-- 4. MOVIE SCHEDULES TABLE (Updated with venue_id)
-- =====================================================
CREATE TABLE IF NOT EXISTS movie_schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    movie_id INT NOT NULL,
    venue_id INT NULL,                              
    movie_title VARCHAR(255) NOT NULL,
    show_date DATE NOT NULL,
    showtime TIME NOT NULL,
    total_seats INT DEFAULT 40,
    available_seats INT DEFAULT 40,
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE,
    FOREIGN KEY (venue_id) REFERENCES venues(id) ON DELETE SET NULL,
    INDEX idx_show_date (show_date),
    INDEX idx_movie_id (movie_id),
    INDEX idx_venue_id (venue_id),
    INDEX idx_show_datetime (show_date, showtime)
);

-- =====================================================
-- 5. BOOKINGS TABLE (Updated with venue_id)
-- =====================================================
CREATE TABLE IF NOT EXISTS tbl_booking (
    b_id INT AUTO_INCREMENT PRIMARY KEY,
    u_id INT NOT NULL,
    movie_name VARCHAR(255) NOT NULL,
    venue_id INT NULL,                              
    show_date DATE,
    showtime TIME NOT NULL,
    booking_fee DECIMAL(10,2) DEFAULT 0,
    status ENUM('Ongoing', 'Done', 'Cancelled') DEFAULT 'Ongoing',
    booking_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    payment_status ENUM('Pending', 'Paid', 'Refunded', 'Pending Verification') DEFAULT 'Pending',
    attendance_status ENUM('Pending', 'Present', 'Completed') DEFAULT 'Pending',
    is_visible TINYINT(1) DEFAULT 1,
    booking_reference VARCHAR(20) UNIQUE,
    qr_code VARCHAR(255) NULL,
    verified_at TIMESTAMP NULL,
    verified_by INT NULL,
    FOREIGN KEY (u_id) REFERENCES users(u_id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(u_id) ON DELETE SET NULL,
    FOREIGN KEY (venue_id) REFERENCES venues(id) ON DELETE SET NULL,
    INDEX idx_user_id (u_id),
    INDEX idx_booking_reference (booking_reference),
    INDEX idx_status (status),
    INDEX idx_payment_status (payment_status),
    INDEX idx_attendance_status (attendance_status),
    INDEX idx_show_date (show_date),
    INDEX idx_is_visible (is_visible),
    INDEX idx_qr_code (qr_code),
    INDEX idx_venue_id (venue_id)
);

-- =====================================================
-- 6. BOOKED SEATS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS booked_seats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    seat_number VARCHAR(10) NOT NULL,
    seat_type VARCHAR(20) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES tbl_booking(b_id) ON DELETE CASCADE,
    INDEX idx_booking_id (booking_id),
    INDEX idx_seat_number (seat_number)
);

-- =====================================================
-- 7. SEAT AVAILABILITY TABLE (Updated with venue_id)
-- =====================================================
CREATE TABLE IF NOT EXISTS seat_availability (
    id INT AUTO_INCREMENT PRIMARY KEY,
    schedule_id INT NOT NULL,
    venue_id INT NULL,                              
    movie_title VARCHAR(255) NOT NULL,
    show_date DATE NOT NULL,
    showtime TIME NOT NULL,
    seat_number VARCHAR(10) NOT NULL,
    seat_type VARCHAR(20) DEFAULT 'Standard',
    price DECIMAL(10,2) DEFAULT 350.00,
    is_available BOOLEAN DEFAULT 1,
    booking_id INT,
    FOREIGN KEY (schedule_id) REFERENCES movie_schedules(id) ON DELETE CASCADE,
    FOREIGN KEY (venue_id) REFERENCES venues(id) ON DELETE SET NULL,
    INDEX idx_schedule_id (schedule_id),
    INDEX idx_venue_id (venue_id),
    INDEX idx_availability (schedule_id, seat_number, is_available)
);

-- =====================================================
-- 8. ADMIN ACTIVITY LOG
-- =====================================================
CREATE TABLE IF NOT EXISTS admin_activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    details TEXT,
    full_details TEXT NULL,
    target_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(u_id) ON DELETE CASCADE,
    INDEX idx_admin_id (admin_id),
    INDEX idx_created_at (created_at),
    INDEX idx_action (action)
);

-- =====================================================
-- 9. CUSTOMER ACTIVITY LOG
-- =====================================================
CREATE TABLE IF NOT EXISTS customer_activity_log (
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
    FOREIGN KEY (schedule_id) REFERENCES movie_schedules(id) ON DELETE SET NULL,
    FOREIGN KEY (booking_id) REFERENCES tbl_booking(b_id) ON DELETE SET NULL,
    INDEX idx_customer_id (customer_id),
    INDEX idx_action_type (action_type),
    INDEX idx_created_at (created_at)
);

-- =====================================================
-- 10. STAFF ACTIVITY LOG
-- =====================================================
CREATE TABLE IF NOT EXISTS staff_activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    booking_id INT NULL,
    details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES users(u_id) ON DELETE CASCADE,
    FOREIGN KEY (booking_id) REFERENCES tbl_booking(b_id) ON DELETE SET NULL,
    INDEX idx_staff_id (staff_id),
    INDEX idx_booking_id (booking_id),
    INDEX idx_created_at (created_at)
);

-- =====================================================
-- 11. SUGGESTIONS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS suggestions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    user_name VARCHAR(100),
    user_email VARCHAR(100),
    suggestion TEXT NOT NULL,
    status ENUM('Pending', 'Reviewed', 'Implemented') DEFAULT 'Pending',
    admin_notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(u_id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);

-- =====================================================
-- 12. PAYMENT METHODS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS payment_methods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    method_name VARCHAR(50) NOT NULL,
    account_name VARCHAR(100) NOT NULL,
    account_number VARCHAR(50) NOT NULL,
    qr_code_path VARCHAR(255),
    instructions TEXT,
    is_active BOOLEAN DEFAULT 1,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_is_active (is_active),
    INDEX idx_display_order (display_order)
);

-- =====================================================
-- 13. MANUAL PAYMENTS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS manual_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    user_id INT NOT NULL,
    payment_method_id INT NOT NULL,
    reference_number VARCHAR(100) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    screenshot_path VARCHAR(255) NOT NULL,
    status ENUM('Pending', 'Verified', 'Rejected') DEFAULT 'Pending',
    admin_notes TEXT,
    verified_by INT NULL,
    verified_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES tbl_booking(b_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(u_id) ON DELETE CASCADE,
    FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(u_id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_booking_id (booking_id),
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
);

-- =====================================================
-- 14. PAYMONGO PAYMENTS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS paymongo_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    user_id INT NOT NULL,
    paymongo_payment_id VARCHAR(100),
    amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(50),
    status ENUM('Pending', 'Paid', 'Failed') DEFAULT 'Pending',
    payment_intent_id VARCHAR(100),
    payment_method_id VARCHAR(100),
    client_key VARCHAR(255),
    redirect_url VARCHAR(255),
    webhook_received BOOLEAN DEFAULT 0,
    response_data JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES tbl_booking(b_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(u_id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_booking_id (booking_id),
    INDEX idx_user_id (user_id)
);