<?php
$root_dir = dirname(dirname(__DIR__));
require_once $root_dir . '/includes/config.php';
require_once $root_dir . '/includes/functions.php';
require_once $root_dir . '/includes/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Customer') {
    header("Location: " . SITE_URL . "index.php?page=login");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

$conn = get_db();

$error = '';
$success = '';

// Auto-cancel bookings that are pending payment for more than 3 hours
$auto_cancel_stmt = $conn->prepare("
    SELECT b.b_id, b.movie_name, b.show_date, b.showtime,
           ms.id as schedule_id
    FROM tbl_booking b
    LEFT JOIN movie_schedules ms ON b.movie_name = ms.movie_title 
        AND b.show_date = ms.show_date 
        AND b.showtime = ms.showtime
    WHERE b.u_id = ? AND b.payment_status = 'Pending' AND b.status = 'Ongoing'
    AND TIMESTAMPDIFF(HOUR, b.booking_date, NOW()) >= 3
");
$auto_cancel_stmt->bind_param("i", $user_id);
$auto_cancel_stmt->execute();
$auto_cancel_result = $auto_cancel_stmt->get_result();

if ($auto_cancel_result->num_rows > 0) {
    $conn->begin_transaction();
    
    try {
        while ($expired_booking = $auto_cancel_result->fetch_assoc()) {
            $booking_id = $expired_booking['b_id'];
            $schedule_id = $expired_booking['schedule_id'];
            
            $get_seats_stmt = $conn->prepare("
                SELECT DISTINCT seat_number 
                FROM booked_seats 
                WHERE booking_id = ?
            ");
            $get_seats_stmt->bind_param("i", $booking_id);
            $get_seats_stmt->execute();
            $seats_result = $get_seats_stmt->get_result();
            $seat_numbers = [];
            while ($seat_row = $seats_result->fetch_assoc()) {
                $seat_numbers[] = $seat_row['seat_number'];
            }
            $get_seats_stmt->close();
            
            $update_booking = $conn->prepare("
                UPDATE tbl_booking 
                SET status = 'Cancelled', payment_status = 'Refunded' 
                WHERE b_id = ?
            ");
            $update_booking->bind_param("i", $booking_id);
            
            if (!$update_booking->execute()) {
                throw new Exception("Failed to cancel expired booking!");
            }
            $update_booking->close();
            
            if (!empty($seat_numbers)) {
                foreach ($seat_numbers as $seat_number) {
                    $seat_update = $conn->prepare("
                        UPDATE seat_availability 
                        SET is_available = 1, booking_id = NULL
                        WHERE schedule_id = ? 
                        AND seat_number = ?
                    ");
                    $seat_update->bind_param("is", $schedule_id, $seat_number);
                    
                    if (!$seat_update->execute()) {
                        throw new Exception("Failed to update seat availability for expired booking!");
                    }
                    $seat_update->close();
                }
                
                $seat_count = count($seat_numbers);
                $update_schedule = $conn->prepare("
                    UPDATE movie_schedules 
                    SET available_seats = available_seats + ?
                    WHERE id = ?
                ");
                $update_schedule->bind_param("ii", $seat_count, $schedule_id);
                
                if (!$update_schedule->execute()) {
                    throw new Exception("Failed to update schedule for expired booking!");
                }
                $update_schedule->close();
            }
        }
        
        $conn->commit();
        
    } catch (Exception $e) {
        $conn->rollback();
    }
}
$auto_cancel_stmt->close();

// Mark shows as expired if showtime has passed
$expire_shows_stmt = $conn->prepare("
    UPDATE tbl_booking 
    SET status = 'Done' 
    WHERE u_id = ? AND status = 'Ongoing' 
    AND CONCAT(show_date, ' ', showtime) < NOW()
");
$expire_shows_stmt->bind_param("i", $user_id);
$expire_shows_stmt->execute();
$expire_shows_stmt->close();

if (isset($_GET['cancel']) && is_numeric($_GET['cancel'])) {
    $booking_id = intval($_GET['cancel']);
    
    $check_stmt = $conn->prepare("
        SELECT b.*, ms.id as schedule_id,
               TIMESTAMPDIFF(HOUR, NOW(), CONCAT(b.show_date, ' ', b.showtime)) as hours_until_show
        FROM tbl_booking b
        LEFT JOIN movie_schedules ms ON b.movie_name = ms.movie_title 
            AND b.show_date = ms.show_date 
            AND b.showtime = ms.showtime
        WHERE b.b_id = ? AND b.u_id = ? AND b.status = 'Ongoing' AND b.payment_status != 'Paid'
        AND TIMESTAMPDIFF(HOUR, NOW(), CONCAT(b.show_date, ' ', b.showtime)) > 2
    ");
    $check_stmt->bind_param("ii", $booking_id, $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        $error = "Booking cannot be cancelled within 2 hours of showtime or after show has started!";
    } else {
        $booking = $check_result->fetch_assoc();
        $schedule_id = $booking['schedule_id'];
        
        $get_seats_stmt = $conn->prepare("
            SELECT DISTINCT seat_number 
            FROM booked_seats 
            WHERE booking_id = ?
        ");
        $get_seats_stmt->bind_param("i", $booking_id);
        $get_seats_stmt->execute();
        $seats_result = $get_seats_stmt->get_result();
        $seat_numbers = [];
        while ($seat_row = $seats_result->fetch_assoc()) {
            $seat_numbers[] = $seat_row['seat_number'];
        }
        $get_seats_stmt->close();
        
        $conn->begin_transaction();
        
        try {
            $update_booking = $conn->prepare("
                UPDATE tbl_booking 
                SET status = 'Cancelled', payment_status = 'Refunded' 
                WHERE b_id = ?
            ");
            $update_booking->bind_param("i", $booking_id);
            
            if (!$update_booking->execute()) {
                throw new Exception("Failed to cancel booking!");
            }
            $update_booking->close();
            
            if (!empty($seat_numbers)) {
                foreach ($seat_numbers as $seat_number) {
                    $seat_update = $conn->prepare("
                        UPDATE seat_availability 
                        SET is_available = 1, booking_id = NULL
                        WHERE schedule_id = ? 
                        AND seat_number = ?
                    ");
                    $seat_update->bind_param("is", $schedule_id, $seat_number);
                    
                    if (!$seat_update->execute()) {
                        throw new Exception("Failed to update seat availability!");
                    }
                    $seat_update->close();
                }
                
                $seat_count = count($seat_numbers);
                $update_schedule = $conn->prepare("
                    UPDATE movie_schedules 
                    SET available_seats = available_seats + ?
                    WHERE id = ?
                ");
                $update_schedule->bind_param("ii", $seat_count, $schedule_id);
                
                if (!$update_schedule->execute()) {
                    throw new Exception("Failed to update schedule!");
                }
                $update_schedule->close();
            }
            
            $conn->commit();
            
            $success = "Booking cancelled successfully! Refund has been processed.";
            
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Cancellation failed: " . $e->getMessage();
        }
    }
    $check_stmt->close();
}

if (isset($_GET['remove']) && is_numeric($_GET['remove'])) {
    $booking_id = intval($_GET['remove']);
    
    $check_stmt = $conn->prepare("
        SELECT status FROM tbl_booking 
        WHERE b_id = ? AND u_id = ? AND (status = 'Cancelled' OR status = 'Done' OR CONCAT(show_date, ' ', showtime) < NOW())
    ");
    $check_stmt->bind_param("ii", $booking_id, $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        $error = "Booking cannot be removed!";
    } else {
        $update_visibility = $conn->prepare("UPDATE tbl_booking SET is_visible = 0 WHERE b_id = ? AND u_id = ?");
        $update_visibility->bind_param("ii", $booking_id, $user_id);
        
        if ($update_visibility->execute()) {
            $success = "Booking removed from view successfully!";
        } else {
            $error = "Failed to remove booking: " . $conn->error;
        }
        $update_visibility->close();
    }
    $check_stmt->close();
}

$bookings_stmt = $conn->prepare("
    SELECT 
        b.*,
        GROUP_CONCAT(DISTINCT bs.seat_number ORDER BY bs.seat_number SEPARATOR ', ') as seat_numbers,
        GROUP_CONCAT(DISTINCT bs.seat_type ORDER BY bs.seat_number SEPARATOR ', ') as seat_types,
        COUNT(DISTINCT bs.id) as total_seats,
        SUM(DISTINCT bs.price) as calculated_total,
        m.id as movie_id,
        m.poster_url,
        m.genre,
        m.duration,
        m.rating,
        m.standard_price,
        m.premium_price,
        m.sweet_spot_price,
        TIMESTAMPDIFF(HOUR, b.booking_date, NOW()) as hours_since_booking,
        TIMESTAMPDIFF(HOUR, NOW(), CONCAT(b.show_date, ' ', b.showtime)) as hours_until_show,
        TIMESTAMPDIFF(MINUTE, NOW(), CONCAT(b.show_date, ' ', b.showtime)) as minutes_until_show,
        CASE 
            WHEN b.status = 'Cancelled' THEN 'cancelled'
            WHEN b.status = 'Done' THEN 'expired'
            WHEN CONCAT(b.show_date, ' ', b.showtime) < NOW() THEN 'expired'
            WHEN b.payment_status = 'Pending' AND TIMESTAMPDIFF(HOUR, b.booking_date, NOW()) >= 3 THEN 'payment_expired'
            WHEN b.payment_status = 'Pending' AND TIMESTAMPDIFF(HOUR, NOW(), CONCAT(b.show_date, ' ', b.showtime)) <= 0 THEN 'expired'
            WHEN TIMESTAMPDIFF(HOUR, NOW(), CONCAT(b.show_date, ' ', b.showtime)) <= 24 THEN 'upcoming'
            ELSE 'active'
        END as booking_status
    FROM tbl_booking b
    LEFT JOIN booked_seats bs ON b.b_id = bs.booking_id
    LEFT JOIN movies m ON b.movie_name = m.title
    WHERE b.u_id = ? AND (b.is_visible = 1 OR b.is_visible IS NULL)
    GROUP BY b.b_id
    ORDER BY 
        CASE 
            WHEN b.status = 'Ongoing' AND b.payment_status = 'Pending' AND TIMESTAMPDIFF(HOUR, NOW(), CONCAT(b.show_date, ' ', b.showtime)) > 0 THEN 1
            WHEN b.status = 'Ongoing' AND b.payment_status = 'Paid' THEN 2
            WHEN b.status = 'Done' THEN 3
            WHEN b.status = 'Cancelled' THEN 4
            ELSE 5
        END,
        b.show_date DESC,
        b.showtime DESC
");
$bookings_stmt->bind_param("i", $user_id);
$bookings_stmt->execute();
$bookings_result = $bookings_stmt->get_result();

$bookings = [];
$booking_stats = [
    'total' => 0,
    'active' => 0,
    'upcoming' => 0,
    'expired' => 0,
    'cancelled' => 0,
    'payment_expired' => 0
];

while ($row = $bookings_result->fetch_assoc()) {
    $bookings[] = $row;
    $booking_stats['total']++;
    
    if ($row['booking_status'] == 'active') $booking_stats['active']++;
    elseif ($row['booking_status'] == 'upcoming') $booking_stats['upcoming']++;
    elseif ($row['booking_status'] == 'expired') $booking_stats['expired']++;
    elseif ($row['booking_status'] == 'cancelled') $booking_stats['cancelled']++;
    elseif ($row['booking_status'] == 'payment_expired') $booking_stats['payment_expired']++;
}
$bookings_stmt->close();

$conn->close();

require_once $root_dir . '/partials/header.php';
?>

<div class="main-container" style="max-width: 1400px; margin: 0 auto; padding: 20px;">
    <div style="background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%); 
         border-radius: 15px; padding: 25px; margin-bottom: 30px; 
         border: 1px solid rgba(226, 48, 32, 0.3);">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
            <div>
                <h1 style="color: white; font-size: 2.2rem; margin-bottom: 10px; font-weight: 800;">
                    <i class="fas fa-receipt"></i> My Bookings
                </h1>
                <p style="color: var(--pale-red); font-size: 1.1rem;">
                    Manage your movie tickets and view booking history
                </p>
            </div>
            
            <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                <a href="<?php echo SITE_URL; ?>index.php?page=customer/booking" 
                   class="btn btn-primary" style="padding: 12px 25px;">
                    <i class="fas fa-ticket-alt"></i> Book New Movie
                </a>
                <a href="<?php echo SITE_URL; ?>index.php?page=movies" 
                   class="btn btn-secondary" style="padding: 12px 25px;">
                    <i class="fas fa-film"></i> Browse Movies
                </a>
            </div>
        </div>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger" style="background: rgba(226, 48, 32, 0.2); color: #ff9999; 
             padding: 15px 20px; border-radius: 10px; margin-bottom: 25px; border: 1px solid rgba(226, 48, 32, 0.3);
             display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-exclamation-circle fa-lg"></i>
            <div><?php echo $error; ?></div>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success" style="background: rgba(46, 204, 113, 0.2); color: #2ecc71; 
             padding: 15px 20px; border-radius: 10px; margin-bottom: 25px; border: 1px solid rgba(46, 204, 113, 0.3);
             display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-check-circle fa-lg"></i>
            <div><?php echo $success; ?></div>
        </div>
    <?php endif; ?>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
        <div style="background: linear-gradient(135deg, rgba(52, 152, 219, 0.2), rgba(41, 128, 185, 0.3)); 
             border-radius: 12px; padding: 20px; border: 1px solid rgba(52, 152, 219, 0.3);">
            <div style="font-size: 2.5rem; font-weight: 800; color: #3498db; margin-bottom: 5px;">
                <?php echo $booking_stats['total']; ?>
            </div>
            <div style="color: white; font-weight: 600; font-size: 1rem;">Total Bookings</div>
        </div>
        
        <div style="background: linear-gradient(135deg, rgba(46, 204, 113, 0.2), rgba(39, 174, 96, 0.3)); 
             border-radius: 12px; padding: 20px; border: 1px solid rgba(46, 204, 113, 0.3);">
            <div style="font-size: 2.5rem; font-weight: 800; color: #2ecc71; margin-bottom: 5px;">
                <?php echo $booking_stats['active']; ?>
            </div>
            <div style="color: white; font-weight: 600; font-size: 1rem;">Active</div>
        </div>
        
        <div style="background: linear-gradient(135deg, rgba(241, 196, 15, 0.2), rgba(243, 156, 18, 0.3)); 
             border-radius: 12px; padding: 20px; border: 1px solid rgba(241, 196, 15, 0.3);">
            <div style="font-size: 2.5rem; font-weight: 800; color: #f1c40f; margin-bottom: 5px;">
                <?php echo $booking_stats['upcoming']; ?>
            </div>
            <div style="color: white; font-weight: 600; font-size: 1rem;">Upcoming</div>
        </div>
        
        <div style="background: linear-gradient(135deg, rgba(149, 165, 166, 0.2), rgba(127, 140, 141, 0.3)); 
             border-radius: 12px; padding: 20px; border: 1px solid rgba(149, 165, 166, 0.3);">
            <div style="font-size: 2.5rem; font-weight: 800; color: #95a5a6; margin-bottom: 5px;">
                <?php echo $booking_stats['expired']; ?>
            </div>
            <div style="color: white; font-weight: 600; font-size: 1rem;">Expired</div>
        </div>
        
        <div style="background: linear-gradient(135deg, rgba(231, 76, 60, 0.2), rgba(192, 57, 43, 0.3)); 
             border-radius: 12px; padding: 20px; border: 1px solid rgba(231, 76, 60, 0.3);">
            <div style="font-size: 2.5rem; font-weight: 800; color: #e74c3c; margin-bottom: 5px;">
                <?php echo $booking_stats['cancelled']; ?>
            </div>
            <div style="color: white; font-weight: 600; font-size: 1rem;">Cancelled</div>
        </div>
        
        <div style="background: linear-gradient(135deg, rgba(231, 76, 60, 0.2), rgba(192, 57, 43, 0.3)); 
             border-radius: 12px; padding: 20px; border: 1px solid rgba(231, 76, 60, 0.3);">
            <div style="font-size: 2.5rem; font-weight: 800; color: #e74c3c; margin-bottom: 5px;">
                <?php echo $booking_stats['payment_expired']; ?>
            </div>
            <div style="color: white; font-weight: 600; font-size: 1rem;">Payment Expired</div>
        </div>
    </div>
    
    <div style="background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%); 
         border-radius: 15px; padding: 25px; margin-bottom: 30px; 
         border: 1px solid rgba(226, 48, 32, 0.3);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
            <h2 style="color: white; font-size: 1.6rem; font-weight: 700;">
                <i class="fas fa-ticket-alt"></i> Booking History
            </h2>
            
            <?php if (!empty($bookings)): ?>
            <div style="color: var(--pale-red); font-size: 0.95rem;">
                Showing <?php echo count($bookings); ?> booking(s)
            </div>
            <?php endif; ?>
        </div>
        
        <?php if (empty($bookings)): ?>
        <div style="text-align: center; padding: 60px 30px;">
            <i class="fas fa-ticket-alt fa-4x" style="color: rgba(255,255,255,0.1); margin-bottom: 20px;"></i>
            <h3 style="color: white; margin-bottom: 15px; font-size: 1.5rem;">No Bookings Yet</h3>
            <p style="color: var(--pale-red); margin-bottom: 30px; max-width: 500px; margin-left: auto; margin-right: auto;">
                You haven't booked any movies yet. Start your cinematic journey by booking your first movie!
            </p>
            <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                <a href="<?php echo SITE_URL; ?>index.php?page=customer/booking" 
                   class="btn btn-primary" style="padding: 15px 30px; font-size: 1.1rem;">
                    <i class="fas fa-ticket-alt"></i> Book Your First Movie
                </a>
                <a href="<?php echo SITE_URL; ?>index.php?page=movies" 
                   class="btn btn-secondary" style="padding: 15px 30px; font-size: 1.1rem;">
                    <i class="fas fa-film"></i> Browse Movies
                </a>
            </div>
        </div>
        <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: 25px;">
            <?php foreach ($bookings as $booking): 
                $booking_date = date('M d, Y', strtotime($booking['booking_date']));
                $show_date = date('D, M d, Y', strtotime($booking['show_date']));
                $show_time = date('h:i A', strtotime($booking['showtime']));
                $is_cancelled = $booking['status'] == 'Cancelled';
                $is_paid = $booking['payment_status'] == 'Paid';
                $is_pending = $booking['payment_status'] == 'Pending' && $booking['status'] == 'Ongoing';
                
                $hours_until_show = $booking['hours_until_show'] ?? 0;
                $minutes_until_show = $booking['minutes_until_show'] ?? 0;
                $hours_since_booking = $booking['hours_since_booking'] ?? 0;
                
                // Determine status and border color
                $border_color = '#e74c3c'; // Default red for expired/cancelled
                $status_bg = '';
                $status_color = '';
                $status_icon = '';
                $status_text = '';
                $show_pay_button = false;
                $show_cancel_button = false;
                $status_message = '';
                
                if ($booking['booking_status'] == 'cancelled') {
                    $border_color = '#e74c3c';
                    $status_bg = 'rgba(231, 76, 60, 0.2)';
                    $status_color = '#e74c3c';
                    $status_icon = 'fa-times-circle';
                    $status_text = 'Cancelled';
                } elseif ($booking['booking_status'] == 'expired') {
                    $border_color = '#e74c3c';
                    $status_bg = 'rgba(149, 165, 166, 0.2)';
                    $status_color = '#95a5a6';
                    $status_icon = 'fa-clock';
                    $status_text = 'Expired';
                } elseif ($booking['booking_status'] == 'payment_expired') {
                    $border_color = '#e74c3c';
                    $status_bg = 'rgba(231, 76, 60, 0.2)';
                    $status_color = '#e74c3c';
                    $status_icon = 'fa-exclamation-circle';
                    $status_text = 'Payment Expired';
                    $status_message = 'Payment not received. Please book again and pay within 3 hours.';
                } elseif ($booking['booking_status'] == 'upcoming') {
                    $border_color = '#f1c40f';
                    $status_bg = 'rgba(241, 196, 15, 0.2)';
                    $status_color = '#f1c40f';
                    $status_icon = 'fa-clock';
                    $status_text = 'Upcoming';
                    
                    if ($is_pending) {
                        if ($hours_until_show <= 4 && $hours_until_show > 0) {
                            $status_message = 'Pay now before showing!';
                        }
                        $show_pay_button = true;
                        $show_cancel_button = ($hours_until_show > 2);
                    }
                } else { // active
                    $border_color = '#2ecc71';
                    $status_bg = 'rgba(46, 204, 113, 0.2)';
                    $status_color = '#2ecc71';
                    $status_icon = 'fa-ticket-alt';
                    $status_text = 'Active';
                    
                    if ($is_pending) {
                        $show_pay_button = true;
                        $show_cancel_button = ($hours_until_show > 2);
                        if ($hours_until_show <= 4 && $hours_until_show > 0) {
                            $status_message = 'Pay now before showing!';
                        }
                    }
                }
                
                $payment_bg = $is_paid ? 'rgba(46, 204, 113, 0.2)' : 'rgba(231, 76, 60, 0.2)';
                $payment_color = $is_paid ? '#2ecc71' : '#e74c3c';
                $payment_text = $is_paid ? 'Paid' : 'Not Paid';
                
                $time_remaining = '';
                if (!$is_cancelled && $booking['booking_status'] != 'expired' && $booking['booking_status'] != 'payment_expired') {
                    if ($hours_until_show > 24) {
                        $days = floor($hours_until_show / 24);
                        $time_remaining = "$days day" . ($days > 1 ? 's' : '') . ' left – available at the cinema';
                    } elseif ($hours_until_show > 0) {
                        $time_remaining = "$hours_until_show hour" . ($hours_until_show > 1 ? 's' : '') . ' left – available at the cinema';
                    }
                }
                
                $payment_hours_left = 3 - $hours_since_booking;
                $payment_time_remaining = '';
                if ($is_pending && $payment_hours_left > 0 && $booking['booking_status'] != 'payment_expired') {
                    if ($payment_hours_left >= 1) {
                        $payment_time_remaining = floor($payment_hours_left) . ' hour' . (floor($payment_hours_left) > 1 ? 's' : '') . ' left to pay';
                    } else {
                        $minutes_left = round($payment_hours_left * 60);
                        $payment_time_remaining = $minutes_left . ' minute' . ($minutes_left > 1 ? 's' : '') . ' left to pay';
                    }
                }
                
                $seat_numbers_display = $booking['seat_numbers'] ?? '';
                $total_seats = $booking['total_seats'] ?? 0;
                
                $seat_types = [];
                $seats = $seat_numbers_display ? explode(', ', $seat_numbers_display) : [];
                $total_price_per_seat = $total_seats > 0 ? ($booking['booking_fee'] / $total_seats) : 0;
                
                if (abs($total_price_per_seat - ($booking['standard_price'] ?? 350)) < 0.01) {
                    $seat_type_for_rebook = 'Standard';
                } elseif (abs($total_price_per_seat - ($booking['premium_price'] ?? 450)) < 0.01) {
                    $seat_type_for_rebook = 'Premium';
                } elseif (abs($total_price_per_seat - ($booking['sweet_spot_price'] ?? 550)) < 0.01) {
                    $seat_type_for_rebook = 'Sweet Spot';
                } else {
                    $seat_type_for_rebook = 'Standard';
                }
            ?>
            <div style="background: rgba(255, 255, 255, 0.05); border-radius: 12px; overflow: hidden;
                 border: 2px solid <?php echo $border_color; ?>; transition: all 0.3s ease;"
                 onmouseover="this.style.borderColor='<?php echo $border_color; ?>'"
                 onmouseout="this.style.borderColor='<?php echo $border_color; ?>'">
                <div style="display: flex; gap: 25px; padding: 25px; 
                     <?php echo ($booking['booking_status'] == 'cancelled' || $booking['booking_status'] == 'expired' || $booking['booking_status'] == 'payment_expired') ? 'opacity: 0.8;' : ''; ?>">
                    
                    <div style="flex-shrink: 0;">
                        <?php if (!empty($booking['poster_url'])): ?>
                        <img src="<?php echo $booking['poster_url']; ?>" 
                             alt="<?php echo htmlspecialchars($booking['movie_name']); ?>"
                             style="width: 120px; height: 160px; object-fit: cover; border-radius: 8px;">
                        <?php else: ?>
                        <div style="width: 120px; height: 160px; background: linear-gradient(135deg, rgba(226, 48, 32, 0.1), rgba(193, 27, 24, 0.2)); 
                             border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-film" style="font-size: 2.5rem; color: rgba(255, 255, 255, 0.3);"></i>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div style="flex: 1;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
                            <div>
                                <h3 style="color: white; font-size: 1.4rem; margin-bottom: 5px; font-weight: 700;">
                                    <?php echo htmlspecialchars($booking['movie_name']); ?>
                                </h3>
                                
                                <div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap; margin-bottom: 10px;">
                                    <span style="background: rgba(226, 48, 32, 0.2); color: var(--light-red); 
                                          padding: 4px 10px; border-radius: 15px; font-size: 0.8rem; font-weight: 700;">
                                        <?php echo $booking['rating'] ?: 'PG'; ?>
                                    </span>
                                    
                                    <span style="color: var(--pale-red); font-size: 0.9rem; display: flex; align-items: center; gap: 5px;">
                                        <i class="fas fa-clock"></i> <?php echo $booking['duration']; ?>
                                    </span>
                                    
                                    <?php if (!empty($booking['genre'])): ?>
                                    <span style="color: var(--pale-red); font-size: 0.9rem; display: flex; align-items: center; gap: 5px;">
                                        <i class="fas fa-film"></i> <?php echo htmlspecialchars($booking['genre']); ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 8px;">
                                <span style="background: <?php echo $status_bg; ?>; color: <?php echo $status_color; ?>; 
                                      padding: 6px 15px; border-radius: 20px; font-size: 0.85rem; font-weight: 700;
                                      display: flex; align-items: center; gap: 8px;">
                                    <i class="fas <?php echo $status_icon; ?>"></i> <?php echo $status_text; ?>
                                </span>
                                
                                <span style="background: <?php echo $payment_bg; ?>; color: <?php echo $payment_color; ?>; 
                                      padding: 6px 15px; border-radius: 20px; font-size: 0.85rem; font-weight: 700;
                                      display: flex; align-items: center; gap: 8px;">
                                    <i class="fas <?php echo $is_paid ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i> 
                                    <?php echo $payment_text; ?>
                                </span>
                            </div>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px;">
                            <div>
                                <div style="color: var(--pale-red); font-size: 0.9rem; margin-bottom: 5px;">Show Date & Time</div>
                                <div style="color: white; font-weight: 600; font-size: 1.1rem;">
                                    <?php echo $show_time; ?>
                                </div>
                                <div style="color: var(--pale-red); font-size: 0.9rem;"><?php echo $show_date; ?></div>
                            </div>
                            
                            <div>
                                <div style="color: var(--pale-red); font-size: 0.9rem; margin-bottom: 5px;">Seats</div>
                                <div style="color: white; font-weight: 600; font-size: 1.1rem;">
                                    <?php echo htmlspecialchars($seat_numbers_display ?: 'No seats assigned'); ?>
                                </div>
                                <div style="color: var(--pale-red); font-size: 0.85rem;">
                                    <?php echo $total_seats ?: 0; ?> seat(s)
                                </div>
                            </div>
                            
                            <div>
                                <div style="color: var(--pale-red); font-size: 0.9rem; margin-bottom: 5px;">Booking Reference</div>
                                <div style="color: white; font-weight: 600; font-size: 1.1rem; letter-spacing: 1px;">
                                    <?php echo htmlspecialchars($booking['booking_reference'] ?? ''); ?>
                                </div>
                                <div style="color: var(--pale-red); font-size: 0.85rem;">
                                    Booked on <?php echo $booking_date; ?>
                                </div>
                            </div>
                            
                            <div>
                                <div style="color: var(--pale-red); font-size: 0.9rem; margin-bottom: 5px;">Total Amount</div>
                                <div style="color: var(--primary-red); font-weight: 800; font-size: 1.3rem;">
                                    ₱<?php echo number_format($booking['booking_fee'] ?? 0, 2); ?>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($status_message): ?>
                        <div style="margin-bottom: 15px; padding: 10px; background: rgba(241, 196, 15, 0.1); border-radius: 8px; border-left: 4px solid #f1c40f;">
                            <p style="color: #f1c40f; font-size: 0.9rem; font-weight: 600;">
                                <i class="fas fa-exclamation-circle"></i> <?php echo $status_message; ?>
                            </p>
                        </div>
                        <?php endif; ?>
                        
                        <div style="display: flex; flex-wrap: wrap; gap: 15px; padding-top: 15px; border-top: 1px solid rgba(255,255,255,0.1); align-items: center;">
                            <div style="display: flex; flex-wrap: wrap; gap: 12px; flex: 1;">
                                <?php if ($payment_time_remaining): ?>
                                <span style="background: rgba(241, 196, 15, 0.2); color: #f1c40f; padding: 8px 15px; border-radius: 20px; font-size: 0.85rem; font-weight: 700; display: inline-flex; align-items: center; gap: 8px;">
                                    <i class="fas fa-hourglass-half"></i> <?php echo $payment_time_remaining; ?>
                                </span>
                                <?php endif; ?>
                                
                                <?php if ($time_remaining && $booking['booking_status'] != 'expired' && $booking['booking_status'] != 'payment_expired'): ?>
                                <span style="color: var(--pale-red); font-size: 0.9rem; font-weight: 600; display: inline-flex; align-items: center; gap: 8px;">
                                    <i class="fas fa-hourglass-half"></i> <?php echo $time_remaining; ?>
                                </span>
                                <?php endif; ?>
                            </div>
                            
                            <div style="display: flex; flex-wrap: wrap; gap: 12px;">
                                <?php if (!empty($booking['b_id'])): ?>
                                <a href="<?php echo SITE_URL; ?>index.php?page=customer/receipt&id=<?php echo $booking['b_id']; ?>" target="_blank" 
                                   class="btn btn-secondary" style="padding: 10px 20px;">
                                    <i class="fas fa-print"></i> Print Receipt
                                </a>
                                <?php endif; ?>
                                
                                <?php if ($show_pay_button && !empty($booking['b_id'])): ?>
                                <a href="<?php echo SITE_URL; ?>index.php?page=customer/payment&booking_id=<?php echo $booking['b_id']; ?>" 
                                   class="btn btn-primary" style="padding: 10px 20px;">
                                    <i class="fas fa-credit-card"></i> Pay Now
                                </a>
                                <?php endif; ?>
                                
                                <?php if ($is_paid && !empty($booking['movie_id']) && $booking['booking_status'] != 'expired'): ?>
                                <a href="<?php echo SITE_URL; ?>index.php?page=customer/rebook&booking_id=<?php echo $booking['b_id']; ?>&seat_type=<?php echo $seat_type_for_rebook; ?>" 
                                   class="btn btn-success" style="padding: 10px 20px; background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%); color: white; box-shadow: 0 4px 15px rgba(46, 204, 113, 0.3); text-decoration: none; display: inline-flex; align-items: center; gap: 8px; border: none; border-radius: 10px; font-weight: 600; transition: all 0.3s ease; cursor: pointer; font-size: 1rem;"
                                   onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 25px rgba(46, 204, 113, 0.4)';"
                                   onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 15px rgba(46, 204, 113, 0.3)';">
                                    <i class="fas fa-redo-alt"></i> Rebook
                                </a>
                                <?php endif; ?>
                                
                                <?php if (!empty($booking['movie_id']) && ($booking['booking_status'] == 'expired' || $booking['booking_status'] == 'payment_expired' || $booking['booking_status'] == 'cancelled')): ?>
                                <a href="<?php echo SITE_URL; ?>index.php?page=customer/booking&movie=<?php echo $booking['movie_id']; ?>" 
                                   class="btn btn-primary" style="padding: 10px 20px;">
                                    <i class="fas fa-redo-alt"></i> Book Again
                                </a>
                                <?php endif; ?>
                                
                                <?php if ($show_cancel_button && !empty($booking['b_id'])): ?>
                                <a href="?page=customer/my-bookings&cancel=<?php echo $booking['b_id']; ?>" 
                                   class="btn btn-danger" style="padding: 10px 20px;"
                                   onclick="return confirm('Are you sure you want to cancel this booking?\n\nMovie: <?php echo addslashes($booking['movie_name'] ?? ''); ?>\nShow: <?php echo $show_date; ?> <?php echo $show_time; ?>\nSeats: <?php echo addslashes($seat_numbers_display); ?>\n\nA refund will be processed.')">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                                <?php endif; ?>
                                
                                <?php if ($booking['booking_status'] == 'cancelled' || $booking['booking_status'] == 'expired' || $booking['booking_status'] == 'payment_expired'): ?>
                                <a href="?page=customer/my-bookings&remove=<?php echo $booking['b_id']; ?>" 
                                   class="btn btn-danger" style="padding: 10px 20px;"
                                   onclick="return confirm('Are you sure you want to remove this booking from your history?')">
                                    <i class="fas fa-trash"></i> Remove
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <?php if (empty($bookings)): ?>
    <div style="background: linear-gradient(135deg, rgba(52, 152, 219, 0.1), rgba(41, 128, 185, 0.2)); 
         border-radius: 15px; padding: 40px; text-align: center; margin-top: 30px;
         border: 2px dashed rgba(52, 152, 219, 0.3);">
        <h3 style="color: white; margin-bottom: 20px; font-size: 1.5rem;">
            <i class="fas fa-lightbulb"></i> How to Book a Movie
        </h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 25px; margin-top: 30px;">
            <div style="background: rgba(255,255,255,0.05); padding: 25px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.1);">
                <div style="font-size: 2.5rem; color: #3498db; margin-bottom: 15px;">
                    <i class="fas fa-search"></i>
                </div>
                <h4 style="color: white; margin-bottom: 10px; font-size: 1.2rem;">Browse Movies</h4>
                <p style="color: rgba(255,255,255,0.7); font-size: 0.95rem; line-height: 1.5;">
                    Explore our collection of movies, read synopses, and check ratings
                </p>
            </div>
            
            <div style="background: rgba(255,255,255,0.05); padding: 25px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.1);">
                <div style="font-size: 2.5rem; color: #3498db; margin-bottom: 15px;">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <h4 style="color: white; margin-bottom: 10px; font-size: 1.2rem;">Select Showtime</h4>
                <p style="color: rgba(255,255,255,0.7); font-size: 0.95rem; line-height: 1.5;">
                    Choose your preferred date and time from available showtimes
                </p>
            </div>
            
            <div style="background: rgba(255,255,255,0.05); padding: 25px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.1);">
                <div style="font-size: 2.5rem; color: #3498db; margin-bottom: 15px;">
                    <i class="fas fa-chair"></i>
                </div>
                <h4 style="color: white; margin-bottom: 10px; font-size: 1.2rem;">Choose Seats</h4>
                <p style="color: rgba(255,255,255,0.7); font-size: 0.95rem; line-height: 1.5;">
                    Select your preferred seats from our interactive seat map
                </p>
            </div>
            
            <div style="background: rgba(255,255,255,0.05); padding: 25px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.1);">
                <div style="font-size: 2.5rem; color: #3498db; margin-bottom: 15px;">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h4 style="color: white; margin-bottom: 10px; font-size: 1.2rem;">Confirm Booking</h4>
                <p style="color: rgba(255,255,255,0.7); font-size: 0.95rem; line-height: 1.5;">
                    Review your selection and confirm to complete your booking
                </p>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
.btn {
    padding: 12px 25px;
    text-decoration: none;
    border-radius: 10px;
    font-weight: 600;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
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

.btn-success {
    background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
    color: white;
    box-shadow: 0 4px 15px rgba(46, 204, 113, 0.3);
}

.btn-success:hover {
    background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(46, 204, 113, 0.4);
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

.btn-danger {
    background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
    color: white;
    box-shadow: 0 4px 15px rgba(231, 76, 60, 0.3);
}

.btn-danger:hover {
    background: linear-gradient(135deg, #c0392b 0%, #a93226 100%);
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(231, 76, 60, 0.4);
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

.booking-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(226, 48, 32, 0.15);
    border-color: rgba(226, 48, 32, 0.4);
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes slideIn {
    from { transform: translateX(100px); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}

.alert {
    animation: fadeIn 0.5s ease;
}

.booking-card {
    animation: slideIn 0.5s ease;
}

@media (max-width: 992px) {
    .main-container {
        padding: 15px;
    }
    
    .booking-card > div {
        flex-direction: column;
        gap: 20px;
    }
    
    .booking-actions {
        flex-direction: column;
        align-items: stretch;
    }
    
    .booking-actions .btn {
        width: 100%;
        justify-content: center;
    }
}

@media (max-width: 576px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .booking-details-grid {
        grid-template-columns: 1fr;
        gap: 15px;
    }
    
    .page-header > div {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .page-header .btn {
        width: 100%;
        justify-content: center;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const bookingCards = document.querySelectorAll('.booking-card');
    bookingCards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
        card.style.animation = 'slideIn 0.5s ease forwards';
        card.style.opacity = '0';
    });
    
    document.querySelectorAll('div[style*="background: rgba(255, 255, 255, 0.05)"]').forEach(card => {
        card.classList.add('booking-card');
    });
});
</script>

<?php
require_once $root_dir . '/partials/footer.php';
?>