-- Movie Ticketing Database Schema - COMPLETE VERSION

CREATE DATABASE IF NOT EXISTS movie;
USE movie;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    u_id INT AUTO_INCREMENT PRIMARY KEY,
    u_name VARCHAR(100) NOT NULL,
    u_username VARCHAR(50) UNIQUE NOT NULL,
    u_email VARCHAR(100) UNIQUE NOT NULL,
    u_pass VARCHAR(255) NOT NULL,
    u_role ENUM('Admin', 'Customer') DEFAULT 'Customer',
    u_status ENUM('Active', 'Inactive') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Movies table with venue fields and seat pricing
CREATE TABLE IF NOT EXISTS movies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    genre VARCHAR(100),
    duration VARCHAR(20),
    rating VARCHAR(10),
    description TEXT,
    poster_url VARCHAR(500),
    trailer_url VARCHAR(500),
    venue_name VARCHAR(255),
    venue_location VARCHAR(500),
    google_maps_link VARCHAR(500),
    standard_price DECIMAL(10,2) DEFAULT 350.00,
    premium_price DECIMAL(10,2) DEFAULT 450.00,
    sweet_spot_price DECIMAL(10,2) DEFAULT 550.00,
    is_active BOOLEAN DEFAULT 1,
    added_by INT NULL,
    updated_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_updated TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
);

-- Movie schedules table
CREATE TABLE IF NOT EXISTS movie_schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    movie_id INT NOT NULL,
    movie_title VARCHAR(255) NOT NULL,
    show_date DATE NOT NULL,
    showtime TIME NOT NULL,
    total_seats INT DEFAULT 40,
    available_seats INT DEFAULT 40,
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE
);

-- Bookings table
CREATE TABLE IF NOT EXISTS tbl_booking (
    b_id INT AUTO_INCREMENT PRIMARY KEY,
    u_id INT NOT NULL,
    movie_name VARCHAR(255) NOT NULL,
    show_date DATE,
    showtime TIME NOT NULL,
    seat_no TEXT NOT NULL,
    booking_fee DECIMAL(10,2) DEFAULT 0,
    status ENUM('Ongoing', 'Done', 'Cancelled') DEFAULT 'Ongoing',
    booking_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    payment_status ENUM('Pending', 'Paid', 'Refunded') DEFAULT 'Pending',
    booking_reference VARCHAR(20),
    FOREIGN KEY (u_id) REFERENCES users(u_id) ON DELETE CASCADE
);

-- Seat availability table
CREATE TABLE IF NOT EXISTS seat_availability (
    id INT AUTO_INCREMENT PRIMARY KEY,
    schedule_id INT NOT NULL,
    movie_title VARCHAR(255) NOT NULL,
    show_date DATE NOT NULL,
    showtime TIME NOT NULL,
    seat_number VARCHAR(10) NOT NULL,
    seat_type VARCHAR(20) DEFAULT 'Standard',
    price DECIMAL(10,2) DEFAULT 350.00,
    is_available BOOLEAN DEFAULT 1,
    booking_id INT,
    FOREIGN KEY (schedule_id) REFERENCES movie_schedules(id) ON DELETE CASCADE
);

-- Create admin activity log table
CREATE TABLE IF NOT EXISTS admin_activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    details TEXT,
    full_details TEXT NULL,
    target_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(u_id) ON DELETE CASCADE
);

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
    FOREIGN KEY (booking_id) REFERENCES tbl_booking(b_id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS suggestions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    user_name VARCHAR(100),
    user_email VARCHAR(100),
    suggestion TEXT NOT NULL,
    status ENUM('Pending', 'Reviewed', 'Implemented') DEFAULT 'Pending',
    admin_notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(u_id) ON DELETE SET NULL
);