<?php
// pages/customer/profile.php

// Go up two levels from pages/customer/ to root
$root_dir = dirname(dirname(__DIR__));

// Include config and functions
require_once $root_dir . '/includes/config.php';
require_once $root_dir . '/includes/functions.php';
require_once $root_dir . '/includes/database.php';

// Check if user is logged in as customer
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Customer') {
    header("Location: " . SITE_URL . "index.php?page=login");
    exit();
}

// Get database connection
$conn = get_db();
$user_id = $_SESSION['user_id'];

// ============================================
// FETCH CURRENT USER DATA
// ============================================
$stmt = $conn->prepare("SELECT u_id, u_name, u_username, u_email, u_role, u_status, created_at FROM users WHERE u_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// ============================================
// FETCH BOOKING STATISTICS
// ============================================

// Total bookings count
$total_bookings_stmt = $conn->prepare("SELECT COUNT(*) as total FROM tbl_booking WHERE u_id = ?");
$total_bookings_stmt->bind_param("i", $user_id);
$total_bookings_stmt->execute();
$total_bookings_result = $total_bookings_stmt->get_result();
$total_bookings = $total_bookings_result->fetch_assoc()['total'];
$total_bookings_stmt->close();

// Active/Upcoming bookings
$active_bookings_stmt = $conn->prepare("
    SELECT COUNT(*) as total 
    FROM tbl_booking 
    WHERE u_id = ? AND status = 'Ongoing' 
    AND CONCAT(show_date, ' ', showtime) >= NOW()
");
$active_bookings_stmt->bind_param("i", $user_id);
$active_bookings_stmt->execute();
$active_bookings_result = $active_bookings_stmt->get_result();
$active_bookings = $active_bookings_result->fetch_assoc()['total'];
$active_bookings_stmt->close();

// Completed bookings
$completed_bookings_stmt = $conn->prepare("
    SELECT COUNT(*) as total 
    FROM tbl_booking 
    WHERE u_id = ? AND (status = 'Done' OR CONCAT(show_date, ' ', showtime) < NOW())
");
$completed_bookings_stmt->bind_param("i", $user_id);
$completed_bookings_stmt->execute();
$completed_bookings_result = $completed_bookings_stmt->get_result();
$completed_bookings = $completed_bookings_result->fetch_assoc()['total'];
$completed_bookings_stmt->close();

// Cancelled bookings
$cancelled_bookings_stmt = $conn->prepare("
    SELECT COUNT(*) as total 
    FROM tbl_booking 
    WHERE u_id = ? AND status = 'Cancelled'
");
$cancelled_bookings_stmt->bind_param("i", $user_id);
$cancelled_bookings_stmt->execute();
$cancelled_bookings_result = $cancelled_bookings_stmt->get_result();
$cancelled_bookings = $cancelled_bookings_result->fetch_assoc()['total'];
$cancelled_bookings_stmt->close();

// Total amount spent
$total_spent_stmt = $conn->prepare("
    SELECT SUM(booking_fee) as total 
    FROM tbl_booking 
    WHERE u_id = ? AND status != 'Cancelled'
");
$total_spent_stmt->bind_param("i", $user_id);
$total_spent_stmt->execute();
$total_spent_result = $total_spent_stmt->get_result();
$total_spent = $total_spent_result->fetch_assoc()['total'] ?? 0;
$total_spent_stmt->close();

// Total refunded amount (from cancelled bookings)
$refunded_stmt = $conn->prepare("
    SELECT SUM(booking_fee) as total 
    FROM tbl_booking 
    WHERE u_id = ? AND status = 'Cancelled'
");
$refunded_stmt->bind_param("i", $user_id);
$refunded_stmt->execute();
$refunded_result = $refunded_stmt->get_result();
$refunded_amount = $refunded_result->fetch_assoc()['total'] ?? 0;
$refunded_stmt->close();

// Total tickets purchased
$total_tickets_stmt = $conn->prepare("
    SELECT SUM(LENGTH(seat_no) - LENGTH(REPLACE(seat_no, ',', '')) + 1) as total 
    FROM tbl_booking 
    WHERE u_id = ?
");
$total_tickets_stmt->bind_param("i", $user_id);
$total_tickets_stmt->execute();
$total_tickets_result = $total_tickets_stmt->get_result();
$total_tickets = $total_tickets_result->fetch_assoc()['total'] ?? 0;
$total_tickets_stmt->close();

// Most watched genre
$favorite_genre_stmt = $conn->prepare("
    SELECT m.genre, COUNT(*) as count
    FROM tbl_booking b
    JOIN movies m ON b.movie_name = m.title
    WHERE b.u_id = ? AND b.status != 'Cancelled'
    GROUP BY m.genre
    ORDER BY count DESC
    LIMIT 1
");
$favorite_genre_stmt->bind_param("i", $user_id);
$favorite_genre_stmt->execute();
$favorite_genre_result = $favorite_genre_stmt->get_result();
$favorite_genre = $favorite_genre_result->fetch_assoc()['genre'] ?? 'N/A';
$favorite_genre_stmt->close();

// Last booking date
$last_booking_stmt = $conn->prepare("
    SELECT booking_date 
    FROM tbl_booking 
    WHERE u_id = ? 
    ORDER BY booking_date DESC 
    LIMIT 1
");
$last_booking_stmt->bind_param("i", $user_id);
$last_booking_stmt->execute();
$last_booking_result = $last_booking_stmt->get_result();
$last_booking = $last_booking_result->fetch_assoc()['booking_date'] ?? null;
$last_booking_stmt->close();

// Fetch recent 5 bookings for preview
$recent_stmt = $conn->prepare("
    SELECT b.* 
    FROM tbl_booking b
    WHERE b.u_id = ?
    ORDER BY b.booking_date DESC
    LIMIT 5
");
$recent_stmt->bind_param("i", $user_id);
$recent_stmt->execute();
$recent_result = $recent_stmt->get_result();
$recent_bookings = [];
while ($row = $recent_result->fetch_assoc()) {
    $recent_bookings[] = $row;
}
$recent_stmt->close();

// ============================================
// HANDLE FORM SUBMISSIONS
// ============================================
$error = '';
$success = '';
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'overview';

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = sanitize_input($_POST['name']);
    $username = sanitize_input($_POST['username']);
    $email = sanitize_input($_POST['email']);
    
    // Validation
    if (empty($name) || empty($username) || empty($email)) {
        $error = "All fields are required!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format!";
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) {
        $error = "Username must be 3-50 characters and can only contain letters, numbers, and underscores!";
    } else {
        // Check if username already exists (excluding current user)
        $check_username = $conn->prepare("SELECT u_id FROM users WHERE u_username = ? AND u_id != ?");
        $check_username->bind_param("si", $username, $user_id);
        $check_username->execute();
        $username_result = $check_username->get_result();
        
        if ($username_result->num_rows > 0) {
            $error = "Username already taken! Please choose a different username.";
        } else {
            $check_username->close();
            
            // Check if email already exists (excluding current user)
            $check_email = $conn->prepare("SELECT u_id FROM users WHERE u_email = ? AND u_id != ?");
            $check_email->bind_param("si", $email, $user_id);
            $check_email->execute();
            $email_result = $check_email->get_result();
            
            if ($email_result->num_rows > 0) {
                $error = "Email already registered! Please use a different email.";
            } else {
                $check_email->close();
                
                // Update user data
                $update_stmt = $conn->prepare("UPDATE users SET u_name = ?, u_username = ?, u_email = ? WHERE u_id = ?");
                $update_stmt->bind_param("sssi", $name, $username, $email, $user_id);
                
                if ($update_stmt->execute()) {
                    // Update session variables
                    $_SESSION['user_name'] = $name;
                    $_SESSION['user_username'] = $username;
                    $_SESSION['user_email'] = $email;
                    
                    $success = "Profile updated successfully!";
                    
                    // Refresh user data
                    $stmt = $conn->prepare("SELECT u_id, u_name, u_username, u_email, u_role, u_status, created_at FROM users WHERE u_id = ?");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $user = $result->fetch_assoc();
                    $stmt->close();
                } else {
                    $error = "Failed to update profile: " . $conn->error;
                }
                $update_stmt->close();
            }
        }
    }
}

// Handle Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = sanitize_input($_POST['current_password']);
    $new_password = sanitize_input($_POST['new_password']);
    $confirm_password = sanitize_input($_POST['confirm_password']);
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "All password fields are required!";
    } elseif ($new_password !== $confirm_password) {
        $error = "New passwords do not match!";
    } elseif (strlen($new_password) < 6) {
        $error = "New password must be at least 6 characters!";
    } else {
        // Verify current password
        $verify_stmt = $conn->prepare("SELECT u_pass FROM users WHERE u_id = ?");
        $verify_stmt->bind_param("i", $user_id);
        $verify_stmt->execute();
        $verify_result = $verify_stmt->get_result();
        $user_data = $verify_result->fetch_assoc();
        $verify_stmt->close();
        
        if (password_verify($current_password, $user_data['u_pass'])) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            $update_pass_stmt = $conn->prepare("UPDATE users SET u_pass = ? WHERE u_id = ?");
            $update_pass_stmt->bind_param("si", $hashed_password, $user_id);
            
            if ($update_pass_stmt->execute()) {
                $success = "Password changed successfully!";
            } else {
                $error = "Failed to change password: " . $conn->error;
            }
            $update_pass_stmt->close();
        } else {
            $error = "Current password is incorrect!";
        }
    }
}

// Don't close the connection yet - we'll close it after including the footer
// $conn->close(); - REMOVED THIS LINE

// Include header
require_once $root_dir . '/partials/header.php';
?>

<div class="profile-container" style="max-width: 1200px; margin: 0 auto; padding: 20px; min-height: calc(100vh - 200px);">
    <!-- Page Header -->
    <div style="background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%); 
         border-radius: 15px; padding: 25px; margin-bottom: 30px; 
         border: 1px solid rgba(226, 48, 32, 0.3);">
        <div style="display: flex; align-items: center; gap: 20px;">
            <div style="width: 80px; height: 80px; background: linear-gradient(135deg, var(--primary-red) 0%, var(--dark-red) 100%); 
                 border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; font-weight: 800; color: white;">
                <?php echo strtoupper(substr($user['u_name'], 0, 1)); ?>
            </div>
            <div>
                <h1 style="color: white; font-size: 2rem; margin-bottom: 5px; font-weight: 800;">
                    <?php echo htmlspecialchars($user['u_name']); ?>
                </h1>
                <p style="color: var(--pale-red); font-size: 1rem; display: flex; align-items: center; gap: 15px;">
                    <span><i class="fas fa-user"></i> @<?php echo htmlspecialchars($user['u_username']); ?></span>
                    <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['u_email']); ?></span>
                    <span><i class="fas fa-calendar"></i> Member since <?php echo date('M Y', strtotime($user['created_at'])); ?></span>
                </p>
            </div>
        </div>
    </div>
    
    <!-- Alerts -->
    <?php if ($error): ?>
        <div style="background: rgba(226, 48, 32, 0.2); color: #ff9999; padding: 15px 20px; border-radius: 10px; margin-bottom: 25px; border: 1px solid rgba(226, 48, 32, 0.3); display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-exclamation-circle fa-lg"></i>
            <div><?php echo $error; ?></div>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div style="background: rgba(46, 204, 113, 0.2); color: #2ecc71; padding: 15px 20px; border-radius: 10px; margin-bottom: 25px; border: 1px solid rgba(46, 204, 113, 0.3); display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-check-circle fa-lg"></i>
            <div><?php echo $success; ?></div>
        </div>
    <?php endif; ?>
    
    <!-- Tab Navigation -->
    <div style="display: flex; gap: 10px; margin-bottom: 30px; border-bottom: 2px solid rgba(226, 48, 32, 0.3); padding-bottom: 10px;">
        <a href="?page=customer/profile&tab=overview" 
           style="padding: 10px 25px; background: <?php echo $active_tab == 'overview' ? 'linear-gradient(135deg, var(--primary-red) 0%, var(--dark-red) 100%)' : 'rgba(255,255,255,0.1)'; ?>; 
                  color: white; text-decoration: none; border-radius: 8px; font-weight: 600; display: flex; align-items: center; gap: 8px;
                  transition: all 0.3s ease;">
            <i class="fas fa-chart-pie"></i> Overview
        </a>
        <a href="?page=customer/profile&tab=edit" 
           style="padding: 10px 25px; background: <?php echo $active_tab == 'edit' ? 'linear-gradient(135deg, var(--primary-red) 0%, var(--dark-red) 100%)' : 'rgba(255,255,255,0.1)'; ?>; 
                  color: white; text-decoration: none; border-radius: 8px; font-weight: 600; display: flex; align-items: center; gap: 8px;
                  transition: all 0.3s ease;">
            <i class="fas fa-user-edit"></i> Edit Profile
        </a>
        <a href="?page=customer/profile&tab=password" 
           style="padding: 10px 25px; background: <?php echo $active_tab == 'password' ? 'linear-gradient(135deg, var(--primary-red) 0%, var(--dark-red) 100%)' : 'rgba(255,255,255,0.1)'; ?>; 
                  color: white; text-decoration: none; border-radius: 8px; font-weight: 600; display: flex; align-items: center; gap: 8px;
                  transition: all 0.3s ease;">
            <i class="fas fa-lock"></i> Change Password
        </a>
    </div>
    
    <?php if ($active_tab == 'overview'): ?>
    <!-- Overview Tab -->
    <div>
        <!-- Statistics Cards -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
            <div style="background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); padding: 25px; border-radius: 12px; text-align: center; border: 1px solid rgba(52, 152, 219, 0.3);">
                <div style="font-size: 2rem; color: #3498db; margin-bottom: 10px;">
                    <i class="fas fa-ticket-alt"></i>
                </div>
                <div style="font-size: 2rem; font-weight: 800; color: white; margin-bottom: 5px;">
                    <?php echo $total_bookings; ?>
                </div>
                <div style="color: var(--pale-red); font-size: 0.9rem;">Total Bookings</div>
            </div>
            
            <div style="background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); padding: 25px; border-radius: 12px; text-align: center; border: 1px solid rgba(46, 204, 113, 0.3);">
                <div style="font-size: 2rem; color: #2ecc71; margin-bottom: 10px;">
                    <i class="fas fa-chair"></i>
                </div>
                <div style="font-size: 2rem; font-weight: 800; color: white; margin-bottom: 5px;">
                    <?php echo $total_tickets; ?>
                </div>
                <div style="color: var(--pale-red); font-size: 0.9rem;">Tickets Purchased</div>
            </div>
            
            <div style="background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); padding: 25px; border-radius: 12px; text-align: center; border: 1px solid rgba(241, 196, 15, 0.3);">
                <div style="font-size: 2rem; color: #f1c40f; margin-bottom: 10px;">
                    <i class="fas fa-clock"></i>
                </div>
                <div style="font-size: 2rem; font-weight: 800; color: white; margin-bottom: 5px;">
                    <?php echo $active_bookings; ?>
                </div>
                <div style="color: var(--pale-red); font-size: 0.9rem;">Active Bookings</div>
            </div>
            
            <div style="background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); padding: 25px; border-radius: 12px; text-align: center; border: 1px solid rgba(155, 89, 182, 0.3);">
                <div style="font-size: 2rem; color: #9b59b6; margin-bottom: 10px;">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div style="font-size: 2rem; font-weight: 800; color: white; margin-bottom: 5px;">
                    <?php echo $completed_bookings; ?>
                </div>
                <div style="color: var(--pale-red); font-size: 0.9rem;">Completed</div>
            </div>
            
            <div style="background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); padding: 25px; border-radius: 12px; text-align: center; border: 1px solid rgba(231, 76, 60, 0.3);">
                <div style="font-size: 2rem; color: #e74c3c; margin-bottom: 10px;">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div style="font-size: 2rem; font-weight: 800; color: white; margin-bottom: 5px;">
                    <?php echo $cancelled_bookings; ?>
                </div>
                <div style="color: var(--pale-red); font-size: 0.9rem;">Cancelled</div>
            </div>
        </div>
        
        <!-- Financial Overview -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px; margin-bottom: 30px;">
            <div style="background: rgba(255,255,255,0.05); border-radius: 15px; padding: 25px; border: 1px solid rgba(226, 48, 32, 0.2);">
                <h3 style="color: white; font-size: 1.3rem; margin-bottom: 20px; font-weight: 700; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-wallet" style="color: #2ecc71;"></i> Financial Summary
                </h3>
                
                <div style="margin-bottom: 15px; padding: 15px; background: rgba(46, 204, 113, 0.1); border-radius: 10px; border-left: 4px solid #2ecc71;">
                    <div style="color: var(--pale-red); font-size: 0.9rem; margin-bottom: 5px;">Total Amount Spent</div>
                    <div style="color: #2ecc71; font-size: 2.2rem; font-weight: 800;">₱<?php echo number_format($total_spent, 2); ?></div>
                </div>
                
                <?php if ($refunded_amount > 0): ?>
                <div style="margin-bottom: 15px; padding: 15px; background: rgba(231, 76, 60, 0.1); border-radius: 10px; border-left: 4px solid #e74c3c;">
                    <div style="color: var(--pale-red); font-size: 0.9rem; margin-bottom: 5px;">Refunded Amount</div>
                    <div style="color: #e74c3c; font-size: 1.5rem; font-weight: 700;">₱<?php echo number_format($refunded_amount, 2); ?></div>
                </div>
                <?php endif; ?>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 20px;">
                    <div style="background: rgba(255,255,255,0.05); padding: 15px; border-radius: 8px; text-align: center;">
                        <div style="color: #3498db; font-size: 1.2rem; font-weight: 700; margin-bottom: 5px;">
                            ₱<?php echo number_format(($total_spent / max($total_bookings, 1)), 2); ?>
                        </div>
                        <div style="color: var(--pale-red); font-size: 0.8rem;">Avg. per Booking</div>
                    </div>
                    
                    <div style="background: rgba(255,255,255,0.05); padding: 15px; border-radius: 8px; text-align: center;">
                        <div style="color: #f1c40f; font-size: 1.2rem; font-weight: 700; margin-bottom: 5px;">
                            ₱<?php echo number_format(($total_spent / max($total_tickets, 1)), 2); ?>
                        </div>
                        <div style="color: var(--pale-red); font-size: 0.8rem;">Avg. per Ticket</div>
                    </div>
                </div>
            </div>
            
            <!-- Booking Insights -->
            <div style="background: rgba(255,255,255,0.05); border-radius: 15px; padding: 25px; border: 1px solid rgba(226, 48, 32, 0.2);">
                <h3 style="color: white; font-size: 1.3rem; margin-bottom: 20px; font-weight: 700; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-chart-line" style="color: #3498db;"></i> Booking Insights
                </h3>
                
                <div style="margin-bottom: 20px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <span style="color: var(--pale-red);">Favorite Genre</span>
                        <span style="color: white; font-weight: 700;"><?php echo htmlspecialchars($favorite_genre); ?></span>
                    </div>
                    
                    <?php if ($last_booking): ?>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <span style="color: var(--pale-red);">Last Booking</span>
                        <span style="color: white; font-weight: 700;"><?php echo date('M d, Y', strtotime($last_booking)); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
                        <span style="color: var(--pale-red);">Membership Age</span>
                        <span style="color: white; font-weight: 700;">
                            <?php 
                            $join_date = new DateTime($user['created_at']);
                            $now = new DateTime();
                            $diff = $join_date->diff($now);
                            echo $diff->y > 0 ? $diff->y . ' year(s)' : ($diff->m > 0 ? $diff->m . ' month(s)' : $diff->d . ' day(s)');
                            ?>
                        </span>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 20px;">
                    <a href="<?php echo SITE_URL; ?>index.php?page=customer/booking" 
                       style="background: rgba(52, 152, 219, 0.2); color: #3498db; text-decoration: none; padding: 12px; border-radius: 8px; text-align: center; font-weight: 600; border: 1px solid rgba(52, 152, 219, 0.3); transition: all 0.3s ease;">
                        <i class="fas fa-ticket-alt"></i> Book Movie
                    </a>
                    <a href="<?php echo SITE_URL; ?>index.php?page=customer/my-bookings" 
                       style="background: rgba(46, 204, 113, 0.2); color: #2ecc71; text-decoration: none; padding: 12px; border-radius: 8px; text-align: center; font-weight: 600; border: 1px solid rgba(46, 204, 113, 0.3); transition: all 0.3s ease;">
                        <i class="fas fa-history"></i> View History
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Recent Bookings Preview -->
        <div style="background: rgba(255,255,255,0.05); border-radius: 15px; padding: 25px; border: 1px solid rgba(226, 48, 32, 0.2);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="color: white; font-size: 1.3rem; font-weight: 700; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-clock"></i> Recent Bookings
                </h3>
                <a href="<?php echo SITE_URL; ?>index.php?page=customer/my-bookings" 
                   style="color: var(--light-red); text-decoration: none; font-weight: 600;">
                    View All <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            
            <?php if (empty($recent_bookings)): ?>
                <div style="text-align: center; padding: 40px; color: var(--pale-red);">
                    <i class="fas fa-ticket-alt fa-3x" style="margin-bottom: 15px; opacity: 0.5;"></i>
                    <p>No bookings yet. Start your movie journey today!</p>
                    <a href="<?php echo SITE_URL; ?>index.php?page=customer/booking" class="btn btn-primary" style="margin-top: 15px; display: inline-block; padding: 12px 30px;">
                        <i class="fas fa-ticket-alt"></i> Book Your First Movie
                    </a>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="border-bottom: 2px solid rgba(226, 48, 32, 0.3);">
                                <th style="padding: 12px; text-align: left; color: var(--pale-red); font-weight: 600;">Movie</th>
                                <th style="padding: 12px; text-align: left; color: var(--pale-red); font-weight: 600;">Date & Time</th>
                                <th style="padding: 12px; text-align: left; color: var(--pale-red); font-weight: 600;">Seats</th>
                                <th style="padding: 12px; text-align: left; color: var(--pale-red); font-weight: 600;">Amount</th>
                                <th style="padding: 12px; text-align: left; color: var(--pale-red); font-weight: 600;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_bookings as $booking): 
                                $is_cancelled = $booking['status'] == 'Cancelled';
                                $is_ongoing = $booking['status'] == 'Ongoing' && strtotime($booking['show_date'] . ' ' . $booking['showtime']) > time();
                                $status_color = $is_cancelled ? '#e74c3c' : ($is_ongoing ? '#2ecc71' : '#95a5a6');
                                $status_text = $is_cancelled ? 'Cancelled' : ($is_ongoing ? 'Active' : 'Completed');
                            ?>
                            <tr style="border-bottom: 1px solid rgba(255,255,255,0.1);">
                                <td style="padding: 12px; color: white; font-weight: 600;"><?php echo htmlspecialchars($booking['movie_name']); ?></td>
                                <td style="padding: 12px; color: rgba(255,255,255,0.8);">
                                    <?php echo date('M d, Y', strtotime($booking['show_date'])); ?><br>
                                    <small><?php echo date('h:i A', strtotime($booking['showtime'])); ?></small>
                                </td>
                                <td style="padding: 12px; color: rgba(255,255,255,0.8);"><?php echo $booking['seat_no']; ?></td>
                                <td style="padding: 12px; color: white; font-weight: 600;">₱<?php echo number_format($booking['booking_fee'], 2); ?></td>
                                <td style="padding: 12px;">
                                    <span style="background: <?php echo $status_color; ?>20; color: <?php echo $status_color; ?>; 
                                         padding: 5px 10px; border-radius: 15px; font-size: 0.8rem; font-weight: 600;">
                                        <?php echo $status_text; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php elseif ($active_tab == 'edit'): ?>
    <!-- Edit Profile Tab -->
    <div style="background: rgba(255,255,255,0.05); border-radius: 15px; padding: 30px; border: 1px solid rgba(226, 48, 32, 0.2); max-width: 600px; margin: 0 auto;">
        <h2 style="color: white; font-size: 1.8rem; margin-bottom: 30px; text-align: center; font-weight: 700;">
            <i class="fas fa-user-edit"></i> Edit Profile
        </h2>
        
        <form method="POST" action="?page=customer/profile&tab=edit" id="profileForm">
            <div style="margin-bottom: 25px;">
                <label style="display: block; color: white; font-weight: 600; margin-bottom: 10px; font-size: 1rem;">
                    <i class="fas fa-user"></i> Full Name
                </label>
                <input type="text" name="name" value="<?php echo htmlspecialchars($user['u_name']); ?>" required
                       style="width: 100%; padding: 14px 16px; background: rgba(255,255,255,0.08); border: 2px solid rgba(226, 48, 32, 0.3); border-radius: 10px; color: white; font-size: 1rem;">
            </div>
            
            <div style="margin-bottom: 25px;">
                <label style="display: block; color: white; font-weight: 600; margin-bottom: 10px; font-size: 1rem;">
                    <i class="fas fa-at"></i> Username
                </label>
                <input type="text" name="username" value="<?php echo htmlspecialchars($user['u_username']); ?>" required
                       pattern="[a-zA-Z0-9_]+" title="Only letters, numbers, and underscores allowed"
                       style="width: 100%; padding: 14px 16px; background: rgba(255,255,255,0.08); border: 2px solid rgba(226, 48, 32, 0.3); border-radius: 10px; color: white; font-size: 1rem;">
                <div style="color: var(--pale-red); font-size: 0.8rem; margin-top: 5px;">
                    <i class="fas fa-info-circle"></i> 3-50 characters, letters, numbers, and underscores only
                </div>
            </div>
            
            <div style="margin-bottom: 25px;">
                <label style="display: block; color: white; font-weight: 600; margin-bottom: 10px; font-size: 1rem;">
                    <i class="fas fa-envelope"></i> Email Address
                </label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($user['u_email']); ?>" required
                       style="width: 100%; padding: 14px 16px; background: rgba(255,255,255,0.08); border: 2px solid rgba(226, 48, 32, 0.3); border-radius: 10px; color: white; font-size: 1rem;">
            </div>
            
            <div style="text-align: center; margin-top: 30px;">
                <button type="submit" name="update_profile" 
                        style="padding: 16px 45px; background: linear-gradient(135deg, var(--primary-red) 0%, var(--dark-red) 100%); color: white; border: none; border-radius: 10px; font-size: 1.1rem; font-weight: 700; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 6px 20px rgba(226, 48, 32, 0.3); display: inline-flex; align-items: center; gap: 10px;">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
    
    <?php elseif ($active_tab == 'password'): ?>
    <!-- Change Password Tab -->
    <div style="background: rgba(255,255,255,0.05); border-radius: 15px; padding: 30px; border: 1px solid rgba(226, 48, 32, 0.2); max-width: 500px; margin: 0 auto;">
        <h2 style="color: white; font-size: 1.8rem; margin-bottom: 30px; text-align: center; font-weight: 700;">
            <i class="fas fa-lock"></i> Change Password
        </h2>
        
        <form method="POST" action="?page=customer/profile&tab=password" id="passwordForm">
            <div style="margin-bottom: 25px;">
                <label style="display: block; color: white; font-weight: 600; margin-bottom: 10px; font-size: 1rem;">
                    <i class="fas fa-lock"></i> Current Password
                </label>
                <input type="password" name="current_password" required
                       style="width: 100%; padding: 14px 16px; background: rgba(255,255,255,0.08); border: 2px solid rgba(226, 48, 32, 0.3); border-radius: 10px; color: white; font-size: 1rem;">
            </div>
            
            <div style="margin-bottom: 25px;">
                <label style="display: block; color: white; font-weight: 600; margin-bottom: 10px; font-size: 1rem;">
                    <i class="fas fa-lock"></i> New Password
                </label>
                <input type="password" name="new_password" id="new_password" required
                       style="width: 100%; padding: 14px 16px; background: rgba(255,255,255,0.08); border: 2px solid rgba(226, 48, 32, 0.3); border-radius: 10px; color: white; font-size: 1rem;">
                <div style="margin-top: 5px; font-size: 0.85rem;">
                    <span id="passwordStrength" style="color: var(--pale-red);">Minimum 6 characters</span>
                </div>
            </div>
            
            <div style="margin-bottom: 25px;">
                <label style="display: block; color: white; font-weight: 600; margin-bottom: 10px; font-size: 1rem;">
                    <i class="fas fa-lock"></i> Confirm New Password
                </label>
                <input type="password" name="confirm_password" id="confirm_password" required
                       style="width: 100%; padding: 14px 16px; background: rgba(255,255,255,0.08); border: 2px solid rgba(226, 48, 32, 0.3); border-radius: 10px; color: white; font-size: 1rem;">
                <div id="passwordMatch" style="margin-top: 5px; font-size: 0.85rem;"></div>
            </div>
            
            <div style="text-align: center; margin-top: 30px;">
                <button type="submit" name="change_password" id="submitBtn"
                        style="padding: 16px 45px; background: linear-gradient(135deg, var(--primary-red) 0%, var(--dark-red) 100%); color: white; border: none; border-radius: 10px; font-size: 1.1rem; font-weight: 700; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 6px 20px rgba(226, 48, 32, 0.3); display: inline-flex; align-items: center; gap: 10px;">
                    <i class="fas fa-key"></i> Change Password
                </button>
            </div>
        </form>
    </div>
    <?php endif; ?>
</div>

<style>
    /* Additional styles */
    .btn {
        padding: 12px 25px;
        text-decoration: none;
        border-radius: 10px;
        font-weight: 600;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        border: none;
        cursor: pointer;
        font-size: 1rem;
    }
    
    .btn-primary {
        background: linear-gradient(135deg, var(--primary-red) 0%, var(--dark-red) 100%);
        color: white;
        box-shadow: 0 4px 15px rgba(226, 48, 32, 0.3);
    }
    
    .btn-primary:hover {
        background: linear-gradient(135deg, var(--dark-red) 0%, var(--deep-red) 100%);
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(226, 48, 32, 0.4);
    }
    
    .btn-secondary {
        background: rgba(255, 255, 255, 0.1);
        color: white;
        border: 2px solid rgba(226, 48, 32, 0.3);
    }
    
    .btn-secondary:hover {
        background: rgba(226, 48, 32, 0.2);
        border-color: var(--primary-red);
        transform: translateY(-3px);
    }
    
    :root {
        --primary-red: #e23020;
        --dark-red: #c11b18;
        --deep-red: #a80f0f;
        --light-red: #ff6b6b;
        --pale-red: #ff9999;
        --bg-dark: #0f0f23;
        --bg-darker: #1a1a2e;
        --bg-card: #3a0b07;
        --bg-card-light: #6b140e;
    }
    
    /* Animation */
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .profile-container {
        animation: fadeIn 0.5s ease;
    }
    
    /* Hover effects */
    .tab-link:hover {
        transform: translateY(-2px);
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .profile-container {
            padding: 15px;
        }
        
        .tab-navigation {
            flex-wrap: wrap;
        }
        
        .stat-grid {
            grid-template-columns: repeat(2, 1fr) !important;
        }
    }
    
    @media (max-width: 576px) {
        .stat-grid {
            grid-template-columns: 1fr !important;
        }
        
        .page-header > div {
            flex-direction: column;
            text-align: center;
        }
    }
</style>

<script>
// Password strength indicator
const passwordInput = document.getElementById('new_password');
const passwordStrength = document.getElementById('passwordStrength');
const confirmInput = document.getElementById('confirm_password');
const passwordMatch = document.getElementById('passwordMatch');
const submitBtn = document.getElementById('submitBtn');

if (passwordInput) {
    passwordInput.addEventListener('input', function() {
        const password = this.value;
        let strength = 0;
        let message = '';
        let color = '#ff9999';
        
        if (password.length >= 6) strength++;
        if (password.length >= 8) strength++;
        if (/[A-Z]/.test(password)) strength++;
        if (/[0-9]/.test(password)) strength++;
        if (/[^A-Za-z0-9]/.test(password)) strength++;
        
        switch(strength) {
            case 0:
            case 1:
                message = 'Weak';
                color = '#e74c3c';
                break;
            case 2:
                message = 'Fair';
                color = '#f39c12';
                break;
            case 3:
                message = 'Good';
                color = '#3498db';
                break;
            case 4:
            case 5:
                message = 'Strong';
                color = '#2ecc71';
                break;
        }
        
        passwordStrength.innerHTML = `<span style="color: ${color};">${message}</span>`;
        
        if (password.length < 6) {
            passwordStrength.innerHTML = '<span style="color: #e74c3c;">Minimum 6 characters required</span>';
        }
        
        if (confirmInput.value) {
            checkPasswordMatch();
        }
    });
}

if (confirmInput) {
    confirmInput.addEventListener('input', checkPasswordMatch);
}

function checkPasswordMatch() {
    const password = passwordInput.value;
    const confirm = confirmInput.value;
    
    if (confirm.length === 0) {
        passwordMatch.innerHTML = '';
        return;
    }
    
    if (password === confirm) {
        passwordMatch.innerHTML = '<span style="color: #2ecc71;"><i class="fas fa-check-circle"></i> Passwords match</span>';
    } else {
        passwordMatch.innerHTML = '<span style="color: #e74c3c;"><i class="fas fa-times-circle"></i> Passwords do not match</span>';
    }
}

// Form validation
document.getElementById('passwordForm')?.addEventListener('submit', function(e) {
    const password = document.getElementById('new_password').value;
    const confirm = document.getElementById('confirm_password').value;
    
    if (password !== confirm) {
        e.preventDefault();
        alert('Passwords do not match!');
        return false;
    }
    
    if (password.length < 6) {
        e.preventDefault();
        alert('Password must be at least 6 characters long!');
        return false;
    }
    
    return true;
});

document.getElementById('profileForm')?.addEventListener('submit', function(e) {
    const username = document.querySelector('input[name="username"]').value;
    const email = document.querySelector('input[name="email"]').value;
    
    if (!/^[a-zA-Z0-9_]{3,50}$/.test(username)) {
        e.preventDefault();
        alert('Username must be 3-50 characters and contain only letters, numbers, and underscores!');
        return false;
    }
    
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        e.preventDefault();
        alert('Please enter a valid email address!');
        return false;
    }
    
    return true;
});

// Auto-dismiss alerts
setTimeout(() => {
    const alerts = document.querySelectorAll('[style*="background: rgba(226, 48, 32, 0.2)"]');
    alerts.forEach(alert => {
        alert.style.transition = 'opacity 0.5s ease';
        alert.style.opacity = '0';
        setTimeout(() => {
            if (alert.parentNode) {
                alert.parentNode.removeChild(alert);
            }
        }, 500);
    });
}, 5000);

// Add animation to stats
document.addEventListener('DOMContentLoaded', function() {
    const statCards = document.querySelectorAll('[style*="padding: 25px"]');
    statCards.forEach((card, index) => {
        card.style.animation = `fadeIn 0.5s ease ${index * 0.1}s forwards`;
        card.style.opacity = '0';
    });
});
</script>

<?php
// Close database connection after all queries are done
if (isset($conn) && $conn) {
    $conn->close();
}

// Include footer
require_once $root_dir . '/partials/footer.php';
?>