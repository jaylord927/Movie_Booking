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

if (isset($_GET['cancel']) && is_numeric($_GET['cancel'])) {
    $booking_id = intval($_GET['cancel']);
    
    $check_stmt = $conn->prepare("
        SELECT b.*, m.title as movie_title 
        FROM tbl_booking b
        LEFT JOIN movies m ON b.movie_name = m.title
        WHERE b.b_id = ? AND b.u_id = ? AND b.status = 'Ongoing'
    ");
    $check_stmt->bind_param("ii", $booking_id, $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        $error = "Booking not found or cannot be cancelled!";
    } else {
        $booking = $check_result->fetch_assoc();
        $movie_title = $booking['movie_name'];
        $seat_numbers = $booking['seat_no'];
        
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
            
            $seats = explode(', ', $seat_numbers);
            
            foreach ($seats as $seat_number) {
                $seat_update = $conn->prepare("
                    UPDATE seat_availability sa
                    JOIN movie_schedules ms ON sa.schedule_id = ms.id
                    SET sa.is_available = 1, sa.booking_id = NULL
                    WHERE sa.movie_title = ? 
                    AND sa.show_date = ? 
                    AND sa.showtime = ? 
                    AND sa.seat_number = ?
                ");
                $seat_update->bind_param(
                    "ssss",
                    $movie_title,
                    $booking['show_date'],
                    $booking['showtime'],
                    $seat_number
                );
                
                if (!$seat_update->execute()) {
                    throw new Exception("Failed to update seat availability!");
                }
                $seat_update->close();
            }
            
            $update_schedule = $conn->prepare("
                UPDATE movie_schedules 
                SET available_seats = available_seats + ?
                WHERE movie_title = ? 
                AND show_date = ? 
                AND showtime = ?
            ");
            $seat_count = count($seats);
            $update_schedule->bind_param(
                "isss",
                $seat_count,
                $movie_title,
                $booking['show_date'],
                $booking['showtime']
            );
            
            if (!$update_schedule->execute()) {
                throw new Exception("Failed to update schedule!");
            }
            $update_schedule->close();
            
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
        WHERE b_id = ? AND u_id = ? AND (status = 'Cancelled' OR CONCAT(show_date, ' ', showtime) < NOW())
    ");
    $check_stmt->bind_param("ii", $booking_id, $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        $error = "Booking cannot be removed!";
    } else {
        $delete_stmt = $conn->prepare("DELETE FROM tbl_booking WHERE b_id = ? AND u_id = ?");
        $delete_stmt->bind_param("ii", $booking_id, $user_id);
        
        if ($delete_stmt->execute()) {
            $success = "Booking removed successfully!";
        } else {
            $error = "Failed to remove booking: " . $conn->error;
        }
        $delete_stmt->close();
    }
    $check_stmt->close();
}

$bookings_stmt = $conn->prepare("
    SELECT 
        b.*,
        m.id as movie_id,
        m.poster_url,
        m.genre,
        m.duration,
        m.rating,
        TIMESTAMPDIFF(HOUR, NOW(), CONCAT(b.show_date, ' ', b.showtime)) as hours_until_show,
        CASE 
            WHEN b.status = 'Cancelled' THEN 'cancelled'
            WHEN b.status = 'Done' THEN 'completed'
            WHEN TIMESTAMPDIFF(HOUR, NOW(), CONCAT(b.show_date, ' ', b.showtime)) <= 0 THEN 'expired'
            WHEN TIMESTAMPDIFF(HOUR, NOW(), CONCAT(b.show_date, ' ', b.showtime)) <= 24 THEN 'upcoming'
            ELSE 'active'
        END as booking_status
    FROM tbl_booking b
    LEFT JOIN movies m ON b.movie_name = m.title
    WHERE b.u_id = ?
    ORDER BY 
        CASE 
            WHEN b.status = 'Ongoing' THEN 1
            WHEN b.status = 'Done' THEN 2
            WHEN b.status = 'Cancelled' THEN 3
            ELSE 4
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
    'completed' => 0,
    'cancelled' => 0,
    'expired' => 0
];

while ($row = $bookings_result->fetch_assoc()) {
    $bookings[] = $row;
    $booking_stats['total']++;
    
    if ($row['booking_status'] == 'active') $booking_stats['active']++;
    elseif ($row['booking_status'] == 'upcoming') $booking_stats['upcoming']++;
    elseif ($row['booking_status'] == 'completed') $booking_stats['completed']++;
    elseif ($row['booking_status'] == 'cancelled') $booking_stats['cancelled']++;
    elseif ($row['booking_status'] == 'expired') $booking_stats['expired']++;
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
        
        <div style="background: linear-gradient(135deg, rgba(155, 89, 182, 0.2), rgba(142, 68, 173, 0.3)); 
             border-radius: 12px; padding: 20px; border: 1px solid rgba(155, 89, 182, 0.3);">
            <div style="font-size: 2.5rem; font-weight: 800; color: #9b59b6; margin-bottom: 5px;">
                <?php echo $booking_stats['completed']; ?>
            </div>
            <div style="color: white; font-weight: 600; font-size: 1rem;">Completed</div>
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
                $is_completed = $booking['status'] == 'Done';
                $is_expired = $booking['booking_status'] == 'expired';
                $is_upcoming = $booking['booking_status'] == 'upcoming';
                $is_paid = $booking['payment_status'] == 'Paid';
                
                $status_bg = '';
                $status_color = '';
                $status_icon = '';
                
                if ($is_cancelled) {
                    $status_bg = 'rgba(231, 76, 60, 0.2)';
                    $status_color = '#e74c3c';
                    $status_icon = 'fa-times-circle';
                    $status_text = 'Cancelled';
                } elseif ($is_completed) {
                    $status_bg = 'rgba(46, 204, 113, 0.2)';
                    $status_color = '#2ecc71';
                    $status_icon = 'fa-check-circle';
                    $status_text = 'Completed';
                } elseif ($is_expired) {
                    $status_bg = 'rgba(149, 165, 166, 0.2)';
                    $status_color = '#95a5a6';
                    $status_icon = 'fa-clock';
                    $status_text = 'Expired';
                } elseif ($is_upcoming) {
                    $status_bg = 'rgba(241, 196, 15, 0.2)';
                    $status_color = '#f1c40f';
                    $status_icon = 'fa-clock';
                    $status_text = 'Upcoming';
                } else {
                    $status_bg = 'rgba(52, 152, 219, 0.2)';
                    $status_color = '#3498db';
                    $status_icon = 'fa-ticket-alt';
                    $status_text = 'Active';
                }
                
                $payment_bg = $is_paid ? 'rgba(46, 204, 113, 0.2)' : 'rgba(231, 76, 60, 0.2)';
                $payment_color = $is_paid ? '#2ecc71' : '#e74c3c';
                $payment_text = $is_paid ? 'Paid' : 'Not Paid';
                
                $hours = $booking['hours_until_show'];
                $time_remaining = '';
                if (!$is_cancelled && !$is_completed && !$is_expired) {
                    if ($hours > 24) {
                        $days = floor($hours / 24);
                        $time_remaining = "$days day" . ($days > 1 ? 's' : '') . ' left';
                    } elseif ($hours > 0) {
                        $time_remaining = "$hours hour" . ($hours > 1 ? 's' : '') . ' left';
                    }
                }
            ?>
            <div style="background: rgba(255, 255, 255, 0.05); border-radius: 12px; overflow: hidden;
                 border: 1px solid rgba(226, 48, 32, 0.2); transition: all 0.3s ease;">
                <div style="display: flex; gap: 25px; padding: 25px; 
                     <?php echo $is_cancelled ? 'opacity: 0.8;' : ''; ?>
                     <?php echo $is_expired ? 'opacity: 0.7;' : ''; ?>">
                    
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
                                    
                                    <?php if ($booking['genre']): ?>
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
                                
                                <?php if ($time_remaining && !$is_cancelled && !$is_completed): ?>
                                <span style="color: var(--pale-red); font-size: 0.8rem; font-weight: 600;">
                                    <i class="fas fa-hourglass-half"></i> <?php echo $time_remaining; ?>
                                </span>
                                <?php endif; ?>
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
                                    <?php echo htmlspecialchars($booking['seat_no']); ?>
                                </div>
                                <div style="color: var(--pale-red); font-size: 0.85rem;">
                                    <?php echo substr_count($booking['seat_no'], ',') + 1; ?> seat(s)
                                </div>
                            </div>
                            
                            <div>
                                <div style="color: var(--pale-red); font-size: 0.9rem; margin-bottom: 5px;">Booking Reference</div>
                                <div style="color: white; font-weight: 600; font-size: 1.1rem; letter-spacing: 1px;">
                                    <?php echo $booking['booking_reference']; ?>
                                </div>
                                <div style="color: var(--pale-red); font-size: 0.85rem;">
                                    Booked on <?php echo $booking_date; ?>
                                </div>
                            </div>
                            
                            <div>
                                <div style="color: var(--pale-red); font-size: 0.9rem; margin-bottom: 5px;">Total Amount</div>
                                <div style="color: var(--primary-red); font-weight: 800; font-size: 1.3rem;">
                                    ₱<?php echo number_format($booking['booking_fee'], 2); ?>
                                </div>
                            </div>
                        </div>
                        
                        <div style="display: flex; flex-wrap: wrap; gap: 12px; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.1);">
                            <a href="<?php echo SITE_URL; ?>index.php?page=customer/receipt&id=<?php echo $booking['b_id']; ?>" target="_blank" 
                               class="btn btn-secondary" style="padding: 10px 20px;">
                                <i class="fas fa-print"></i> Print Receipt
                            </a>
                            
                            <?php if (!$is_paid && !$is_cancelled && !$is_completed && !$is_expired): ?>
                            <a href="?page=customer/my-bookings&pay=<?php echo $booking['b_id']; ?>" 
                               class="btn btn-primary" style="padding: 10px 20px;"
                               onclick="return confirm('Proceed to payment for this booking? The payment status will remain Not Paid until payment is completed.')">
                                <i class="fas fa-credit-card"></i> Payment
                            </a>
                            <?php endif; ?>
                            
                            <?php if ($booking['movie_id']): ?>
                                <?php if ($is_cancelled): ?>
                                <a href="<?php echo SITE_URL; ?>index.php?page=customer/booking&movie=<?php echo $booking['movie_id']; ?>" 
                                   class="btn btn-primary" style="padding: 10px 20px;">
                                    <i class="fas fa-redo-alt"></i> Book Again This Movie
                                </a>
                                <?php elseif (!$is_cancelled && !$is_completed && !$is_expired && $booking['movie_id']): ?>
                                <a href="<?php echo SITE_URL; ?>index.php?page=customer/booking&movie=<?php echo $booking['movie_id']; ?>" 
                                   class="btn btn-primary" style="padding: 10px 20px;">
                                    <i class="fas fa-plus-circle"></i> Add Another Ticket
                                </a>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php if (!$is_cancelled && !$is_completed && !$is_expired && $booking['hours_until_show'] > 2): ?>
                            <a href="?page=customer/my-bookings&cancel=<?php echo $booking['b_id']; ?>" 
                               class="btn btn-danger" style="padding: 10px 20px;"
                               onclick="return confirm('Are you sure you want to cancel this booking?\n\nMovie: <?php echo addslashes($booking['movie_name']); ?>\nShow: <?php echo $show_date; ?> <?php echo $show_time; ?>\nSeats: <?php echo addslashes($booking['seat_no']); ?>\n\nA refund will be processed.')">
                                <i class="fas fa-times"></i> Cancel Booking
                            </a>
                            <?php endif; ?>
                            
                            <?php if ($is_cancelled || $is_expired): ?>
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