<?php
$root_dir = dirname(dirname(__DIR__));
require_once $root_dir . '/includes/config.php';
require_once $root_dir . '/includes/functions.php';
require_once $root_dir . '/includes/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Customer') {
    header("Location: " . SITE_URL . "index.php?page=login");
    exit();
}

$conn = get_db_connection();
$user_id = $_SESSION['user_id'];
$error = '';
$success = '';
$booking = null;

$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;

if ($booking_id <= 0) {
    header("Location: " . SITE_URL . "index.php?page=customer/my-bookings");
    exit();
}

// ============================================
// FETCH BOOKING DETAILS WITH NORMALIZED SCHEMA
// ============================================
$booking_stmt = $conn->prepare("
    SELECT 
        b.id,
        b.booking_reference,
        b.total_amount,
        b.payment_status,
        b.status,
        b.booked_at,
        s.id as schedule_id,
        s.show_date,
        s.showtime,
        s.base_price,
        m.id as movie_id,
        m.title as movie_title,
        m.poster_url,
        m.genre,
        m.duration,
        m.rating,
        m.description,
        sc.id as screen_id,
        sc.screen_name,
        sc.screen_number,
        v.id as venue_id,
        v.venue_name,
        v.venue_location,
        sp.id as seat_plan_id,
        sp.plan_name,
        GROUP_CONCAT(DISTINCT bs.seat_number ORDER BY bs.seat_number SEPARATOR ', ') as seat_numbers,
        GROUP_CONCAT(DISTINCT st.name ORDER BY bs.seat_number SEPARATOR ', ') as seat_types,
        COUNT(DISTINCT bs.id) as total_seats,
        SUM(DISTINCT bs.price) as total_price,
        TIMESTAMPDIFF(HOUR, b.booked_at, NOW()) as hours_since_booking
    FROM bookings b
    JOIN schedules s ON b.schedule_id = s.id
    JOIN movies m ON s.movie_id = m.id
    JOIN screens sc ON s.screen_id = sc.id
    JOIN venues v ON sc.venue_id = v.id
    JOIN seat_plans sp ON s.seat_plan_id = sp.id
    LEFT JOIN booked_seats bs ON b.id = bs.booking_id
    LEFT JOIN seat_types st ON bs.seat_type_id = st.id
    WHERE b.id = ? AND b.user_id = ? AND b.payment_status = 'paid'
    GROUP BY b.id
");
$booking_stmt->bind_param("ii", $booking_id, $user_id);
$booking_stmt->execute();
$booking_result = $booking_stmt->get_result();

if ($booking_result->num_rows === 0) {
    $conn->close();
    header("Location: " . SITE_URL . "index.php?page=customer/my-bookings");
    exit();
}

$booking = $booking_result->fetch_assoc();
$booking_stmt->close();

// Parse original seats
$original_seat_numbers = !empty($booking['seat_numbers']) ? array_map('trim', explode(',', $booking['seat_numbers'])) : [];
$original_seat_types = !empty($booking['seat_types']) ? array_map('trim', explode(',', $booking['seat_types'])) : [];

// Build original seat counts by type
$original_seat_counts = [];
$original_seat_details = [];

if (!empty($original_seat_numbers) && !empty($original_seat_types)) {
    for ($i = 0; $i < count($original_seat_numbers); $i++) {
        $seat_num = $original_seat_numbers[$i];
        $seat_type = $original_seat_types[$i] ?? 'Standard';
        $original_seat_counts[$seat_type] = ($original_seat_counts[$seat_type] ?? 0) + 1;
        $original_seat_details[] = [
            'seat_number' => $seat_num,
            'seat_type' => $seat_type
        ];
    }
}

$total_original_seats = count($original_seat_numbers);

// ============================================
// GET AVAILABLE SCHEDULES FOR REBOOKING
// ============================================
$schedules_stmt = $conn->prepare("
    SELECT 
        s.id,
        s.show_date,
        s.showtime,
        s.base_price,
        sc.id as screen_id,
        sc.screen_name,
        sc.screen_number,
        v.id as venue_id,
        v.venue_name,
        v.venue_location,
        sp.id as seat_plan_id,
        sp.plan_name,
        sp.total_seats as plan_total_seats,
        COUNT(sa.id) as total_seats,
        COUNT(CASE WHEN sa.status = 'available' THEN 1 END) as available_seats
    FROM schedules s
    JOIN screens sc ON s.screen_id = sc.id
    JOIN venues v ON sc.venue_id = v.id
    JOIN seat_plans sp ON s.seat_plan_id = sp.id
    LEFT JOIN seat_availability sa ON s.id = sa.schedule_id
    WHERE s.movie_id = ? 
    AND s.is_active = 1 
    AND s.show_date >= CURDATE()
    AND s.id != ?
    GROUP BY s.id, s.show_date, s.showtime, s.base_price, sc.id, sc.screen_name, sc.screen_number, v.id, v.venue_name, v.venue_location, sp.id, sp.plan_name, sp.total_seats
    HAVING available_seats >= ?
    ORDER BY s.show_date, s.showtime
");
$schedules_stmt->bind_param("iii", $booking['movie_id'], $booking['schedule_id'], $total_original_seats);
$schedules_stmt->execute();
$schedules_result = $schedules_stmt->get_result();
$schedules = [];
while ($row = $schedules_result->fetch_assoc()) {
    $schedules[] = $row;
}
$schedules_stmt->close();

// Include current schedule as option (keep same showtime)
$current_schedule_stmt = $conn->prepare("
    SELECT 
        s.id,
        s.show_date,
        s.showtime,
        s.base_price,
        sc.id as screen_id,
        sc.screen_name,
        sc.screen_number,
        v.id as venue_id,
        v.venue_name,
        v.venue_location,
        sp.id as seat_plan_id,
        sp.plan_name,
        COUNT(sa.id) as total_seats,
        COUNT(CASE WHEN sa.status = 'available' THEN 1 END) as available_seats,
        (SELECT COUNT(*) FROM booked_seats WHERE booking_id = ?) as current_booked_seats
    FROM schedules s
    JOIN screens sc ON s.screen_id = sc.id
    JOIN venues v ON sc.venue_id = v.id
    JOIN seat_plans sp ON s.seat_plan_id = sp.id
    LEFT JOIN seat_availability sa ON s.id = sa.schedule_id
    WHERE s.id = ?
    GROUP BY s.id
");
$current_schedule_stmt->bind_param("ii", $booking_id, $booking['schedule_id']);
$current_schedule_stmt->execute();
$current_result = $current_schedule_stmt->get_result();
$current_schedule = $current_result->fetch_assoc();
$current_schedule_stmt->close();

if ($current_schedule) {
    // For current schedule, available seats includes seats that are not booked
    // But since this is a rebooking, we need to show seats that are either:
    // - Available OR currently booked by this user
    $current_schedule['is_current'] = true;
    array_unshift($schedules, $current_schedule);
}

// ============================================
// GET SEAT TYPES AND PRICES
// ============================================
$seat_types_stmt = $conn->query("SELECT id, name, default_price, color_code FROM seat_types WHERE is_active = 1");
$seat_types = [];
while ($row = $seat_types_stmt->fetch_assoc()) {
    $seat_types[$row['name']] = $row;
}

// ============================================
// SELECTED SCHEDULE AND SEATS
// ============================================
$selected_schedule_id = isset($_POST['schedule_id']) ? intval($_POST['schedule_id']) : 
                       (isset($_GET['schedule']) ? intval($_GET['schedule']) : $booking['schedule_id']);

$selected_seats = isset($_POST['selected_seats']) ? $_POST['selected_seats'] : [];
$seats = [];
$selected_schedule_data = null;

if ($selected_schedule_id > 0) {
    $schedule_info_stmt = $conn->prepare("
        SELECT 
            s.*,
            sc.id as screen_id,
            sc.screen_name,
            sc.screen_number,
            sc.capacity,
            v.id as venue_id,
            v.venue_name,
            v.venue_location,
            v.google_maps_link,
            sp.id as seat_plan_id,
            sp.plan_name,
            sp.total_rows,
            sp.total_columns
        FROM schedules s
        JOIN screens sc ON s.screen_id = sc.id
        JOIN venues v ON sc.venue_id = v.id
        JOIN seat_plans sp ON s.seat_plan_id = sp.id
        WHERE s.id = ? AND s.is_active = 1
    ");
    $schedule_info_stmt->bind_param("i", $selected_schedule_id);
    $schedule_info_stmt->execute();
    $schedule_info_result = $schedule_info_stmt->get_result();
    
    if ($schedule_info_result->num_rows > 0) {
        $selected_schedule_data = $schedule_info_result->fetch_assoc();
        
        // Get seat availability with pricing
        $seats_stmt = $conn->prepare("
            SELECT 
                sa.id as seat_availability_id,
                sa.seat_number,
                sa.status,
                sa.price,
                st.id as seat_type_id,
                st.name as seat_type,
                st.color_code,
                CASE 
                    WHEN bs.booking_id = ? THEN 1 
                    ELSE 0 
                END as is_current_booking
            FROM seat_availability sa
            JOIN seat_types st ON sa.seat_type_id = st.id
            LEFT JOIN booked_seats bs ON sa.id = bs.seat_availability_id AND bs.booking_id = ?
            WHERE sa.schedule_id = ?
            ORDER BY sa.seat_number
        ");
        $seats_stmt->bind_param("iii", $booking_id, $booking_id, $selected_schedule_id);
        $seats_stmt->execute();
        $seats_result = $seats_stmt->get_result();
        
        while ($row = $seats_result->fetch_assoc()) {
            // Determine if seat is considered "available" for rebooking
            if ($row['status'] === 'available') {
                $row['can_select'] = true;
                $row['is_current'] = false;
            } elseif ($row['is_current_booking'] == 1) {
                $row['can_select'] = true;
                $row['is_current'] = true;
                $row['status'] = 'current';
            } else {
                $row['can_select'] = false;
                $row['is_current'] = false;
            }
            $seats[] = $row;
        }
        $seats_stmt->close();
    }
    $schedule_info_stmt->close();
}

// ============================================
// PROCESS REBOOKING
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_rebooking'])) {
    $schedule_id = intval($_POST['schedule_id']);
    $selected_seats = isset($_POST['selected_seats']) ? $_POST['selected_seats'] : [];
    
    if ($schedule_id <= 0) {
        $error = "Please select a showtime!";
    } elseif (empty($selected_seats)) {
        $error = "Please select at least one seat!";
    } elseif (count($selected_seats) != $total_original_seats) {
        $error = "You must select exactly $total_original_seats seat(s) to match your original booking.";
    } else {
        // Get schedule details
        $schedule_stmt = $conn->prepare("
            SELECT s.*, sc.venue_id, sc.id as screen_id
            FROM schedules s
            JOIN screens sc ON s.screen_id = sc.id
            WHERE s.id = ? AND s.is_active = 1
        ");
        $schedule_stmt->bind_param("i", $schedule_id);
        $schedule_stmt->execute();
        $schedule_result = $schedule_stmt->get_result();
        
        if ($schedule_result->num_rows === 0) {
            $error = "Invalid schedule selected!";
        } else {
            $new_schedule = $schedule_result->fetch_assoc();
            $schedule_stmt->close();
            
            // Verify seat availability and collect prices
            $selected_seat_details = [];
            $seat_check_failed = false;
            $unavailable_seats = [];
            $new_seat_counts = [];
            
            $conn->begin_transaction();
            
            try {
                foreach ($selected_seats as $seat_number) {
                    $seat_check = $conn->prepare("
                        SELECT sa.id as seat_availability_id, sa.status, sa.price, st.name as seat_type
                        FROM seat_availability sa
                        JOIN seat_types st ON sa.seat_type_id = st.id
                        WHERE sa.schedule_id = ? AND sa.seat_number = ?
                    ");
                    $seat_check->bind_param("is", $schedule_id, $seat_number);
                    $seat_check->execute();
                    $seat_result = $seat_check->get_result();
                    
                    if ($seat_result->num_rows === 0) {
                        $seat_check_failed = true;
                        $unavailable_seats[] = $seat_number;
                    } else {
                        $seat = $seat_result->fetch_assoc();
                        
                        // Check if seat is available OR is currently booked by this user on old schedule
                        $is_current_seat = false;
                        if ($schedule_id == $booking['schedule_id']) {
                            // Check if this seat is currently booked by this user
                            $current_check = $conn->prepare("
                                SELECT 1 FROM booked_seats bs
                                JOIN seat_availability sa ON bs.seat_availability_id = sa.id
                                WHERE bs.booking_id = ? AND sa.seat_number = ?
                            ");
                            $current_check->bind_param("is", $booking_id, $seat_number);
                            $current_check->execute();
                            $is_current_seat = $current_check->get_result()->num_rows > 0;
                            $current_check->close();
                        }
                        
                        if ($seat['status'] === 'available' || $is_current_seat) {
                            $selected_seat_details[] = [
                                'seat_availability_id' => $seat['seat_availability_id'],
                                'seat_number' => $seat_number,
                                'seat_type' => $seat['seat_type'],
                                'price' => $seat['price']
                            ];
                            $new_seat_counts[$seat['seat_type']] = ($new_seat_counts[$seat['seat_type']] ?? 0) + 1;
                        } else {
                            $seat_check_failed = true;
                            $unavailable_seats[] = $seat_number;
                        }
                    }
                    $seat_check->close();
                }
                
                if ($seat_check_failed) {
                    throw new Exception("Seats " . implode(", ", $unavailable_seats) . " are not available!");
                }
                
                // Verify seat type counts match original
                foreach ($original_seat_counts as $type => $count) {
                    $selected_count = $new_seat_counts[$type] ?? 0;
                    if ($selected_count != $count) {
                        throw new Exception("Seat type mismatch: Need $count $type seat(s), but selected $selected_count.");
                    }
                }
                
                // Calculate total price
                $total_price = array_sum(array_column($selected_seat_details, 'price'));
                
                // If same schedule, just update seats
                if ($schedule_id == $booking['schedule_id']) {
                    // Get current seat availability IDs from old booking
                    $old_seats_stmt = $conn->prepare("
                        SELECT sa.id as seat_availability_id, sa.seat_number
                        FROM booked_seats bs
                        JOIN seat_availability sa ON bs.seat_availability_id = sa.id
                        WHERE bs.booking_id = ?
                    ");
                    $old_seats_stmt->bind_param("i", $booking_id);
                    $old_seats_stmt->execute();
                    $old_seats_result = $old_seats_stmt->get_result();
                    $old_seat_avail_ids = [];
                    while ($old = $old_seats_result->fetch_assoc()) {
                        $old_seat_avail_ids[$old['seat_number']] = $old['seat_availability_id'];
                    }
                    $old_seats_stmt->close();
                    
                    // Remove old booked seats
                    $delete_old = $conn->prepare("DELETE FROM booked_seats WHERE booking_id = ?");
                    $delete_old->bind_param("i", $booking_id);
                    $delete_old->execute();
                    $delete_old->close();
                    
                    // Release old seats back to available
                    foreach ($old_seat_avail_ids as $old_seat_avail_id) {
                        $release_seat = $conn->prepare("
                            UPDATE seat_availability 
                            SET status = 'available', locked_by = NULL, locked_at = NULL 
                            WHERE id = ?
                        ");
                        $release_seat->bind_param("i", $old_seat_avail_id);
                        $release_seat->execute();
                        $release_seat->close();
                    }
                    
                    // Book new seats
                    $insert_seat = $conn->prepare("
                        INSERT INTO booked_seats (booking_id, seat_availability_id, seat_number, seat_type_id, price)
                        SELECT ?, ?, ?, st.id, ?
                        FROM seat_types st
                        WHERE st.name = ?
                    ");
                    
                    foreach ($selected_seat_details as $seat) {
                        // Lock the seat
                        $lock_seat = $conn->prepare("
                            UPDATE seat_availability 
                            SET status = 'booked' 
                            WHERE id = ?
                        ");
                        $lock_seat->bind_param("i", $seat['seat_availability_id']);
                        $lock_seat->execute();
                        $lock_seat->close();
                        
                        $insert_seat->bind_param("iisds", $booking_id, $seat['seat_availability_id'], $seat['seat_number'], $seat['price'], $seat['seat_type']);
                        $insert_seat->execute();
                    }
                    $insert_seat->close();
                    
                    // Update total amount if changed
                    if (abs($total_price - $booking['total_amount']) > 0.01) {
                        $update_amount = $conn->prepare("UPDATE bookings SET total_amount = ? WHERE id = ?");
                        $update_amount->bind_param("di", $total_price, $booking_id);
                        $update_amount->execute();
                        $update_amount->close();
                    }
                    
                } else {
                    // Different schedule - need to cancel old and create new booking
                    
                    // Get old seat availability IDs
                    $old_seats_stmt = $conn->prepare("
                        SELECT sa.id as seat_availability_id
                        FROM booked_seats bs
                        JOIN seat_availability sa ON bs.seat_availability_id = sa.id
                        WHERE bs.booking_id = ?
                    ");
                    $old_seats_stmt->bind_param("i", $booking_id);
                    $old_seats_stmt->execute();
                    $old_seats_result = $old_seats_stmt->get_result();
                    $old_seat_avail_ids = [];
                    while ($old = $old_seats_result->fetch_assoc()) {
                        $old_seat_avail_ids[] = $old['seat_availability_id'];
                    }
                    $old_seats_stmt->close();
                    
                    // Release old seats
                    foreach ($old_seat_avail_ids as $old_seat_avail_id) {
                        $release_seat = $conn->prepare("
                            UPDATE seat_availability 
                            SET status = 'available', locked_by = NULL, locked_at = NULL 
                            WHERE id = ?
                        ");
                        $release_seat->bind_param("i", $old_seat_avail_id);
                        $release_seat->execute();
                        $release_seat->close();
                    }
                    
                    // Update old schedule's available seats count
                    $old_schedule_update = $conn->prepare("
                        UPDATE schedules 
                        SET available_seats = available_seats + ?
                        WHERE id = ?
                    ");
                    $old_schedule_update->bind_param("ii", $total_original_seats, $booking['schedule_id']);
                    $old_schedule_update->execute();
                    $old_schedule_update->close();
                    
                    // Update booking with new schedule
                    $update_booking = $conn->prepare("
                        UPDATE bookings 
                        SET schedule_id = ?, total_amount = ?
                        WHERE id = ?
                    ");
                    $update_booking->bind_param("idi", $schedule_id, $total_price, $booking_id);
                    $update_booking->execute();
                    $update_booking->close();
                    
                    // Remove old booked seats
                    $delete_old = $conn->prepare("DELETE FROM booked_seats WHERE booking_id = ?");
                    $delete_old->bind_param("i", $booking_id);
                    $delete_old->execute();
                    $delete_old->close();
                    
                    // Book new seats
                    $insert_seat = $conn->prepare("
                        INSERT INTO booked_seats (booking_id, seat_availability_id, seat_number, seat_type_id, price)
                        SELECT ?, ?, ?, st.id, ?
                        FROM seat_types st
                        WHERE st.name = ?
                    ");
                    
                    foreach ($selected_seat_details as $seat) {
                        // Lock the seat
                        $lock_seat = $conn->prepare("
                            UPDATE seat_availability 
                            SET status = 'booked' 
                            WHERE id = ?
                        ");
                        $lock_seat->bind_param("i", $seat['seat_availability_id']);
                        $lock_seat->execute();
                        $lock_seat->close();
                        
                        $insert_seat->bind_param("iisds", $booking_id, $seat['seat_availability_id'], $seat['seat_number'], $seat['price'], $seat['seat_type']);
                        $insert_seat->execute();
                    }
                    $insert_seat->close();
                    
                    // Update new schedule's available seats count
                    $new_schedule_update = $conn->prepare("
                        UPDATE schedules 
                        SET available_seats = available_seats - ?
                        WHERE id = ?
                    ");
                    $new_schedule_update->bind_param("ii", $total_original_seats, $schedule_id);
                    $new_schedule_update->execute();
                    $new_schedule_update->close();
                }
                
                $conn->commit();
                $success = "Rebooking successful! Your seats have been updated.";
                $selected_seats = [];
                
                // Redirect after 2 seconds
                header("Refresh:2; url=" . SITE_URL . "index.php?page=customer/my-bookings");
                exit();
                
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Rebooking failed: " . $e->getMessage();
            }
        }
    }
}

$conn->close();
require_once $root_dir . '/partials/header.php';
?>

<!-- Rebooking Modal -->
<div class="rebook-modal" style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 95%; max-width: 1400px; max-height: 90vh; overflow-y: auto; background: linear-gradient(135deg, #0f0f23 0%, #1a1a2e 100%); border-radius: 20px; border: 2px solid var(--primary-red); box-shadow: 0 20px 50px rgba(0,0,0,0.5); z-index: 1000; padding: 30px;">
    
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid var(--primary-red);">
        <div>
            <h1 style="color: white; font-size: 2rem; font-weight: 800;">
                <i class="fas fa-redo-alt" style="color: var(--primary-red);"></i> Change Your Seats
            </h1>
            <p style="color: var(--pale-red); font-size: 1rem;">
                Booking Reference: <strong><?php echo htmlspecialchars($booking['booking_reference'] ?? ''); ?></strong> • 
                Current Seats: <strong style="color: #f1c40f;"><?php echo htmlspecialchars($booking['seat_numbers'] ?: 'None'); ?></strong>
            </p>
        </div>
        <a href="<?php echo SITE_URL; ?>index.php?page=customer/my-bookings" style="background: rgba(255,255,255,0.1); color: white; text-decoration: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; display: flex; align-items: center; gap: 8px; border: 2px solid rgba(226,48,32,0.3); transition: all 0.3s ease;"
           onmouseover="this.style.background='rgba(226,48,32,0.2)'; this.style.borderColor='var(--primary-red)';"
           onmouseout="this.style.background='rgba(255,255,255,0.1)'; this.style.borderColor='rgba(226,48,32,0.3)';">
            <i class="fas fa-times"></i> Close
        </a>
    </div>

    <?php if ($error): ?>
        <div style="background: rgba(226, 48, 32, 0.2); color: #ff9999; padding: 15px 20px; border-radius: 10px; margin-bottom: 25px; border: 1px solid rgba(226, 48, 32, 0.3); display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-exclamation-circle fa-lg"></i>
            <div><?php echo $error; ?></div>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div style="background: rgba(46, 204, 113, 0.2); color: #2ecc71; padding: 20px; border-radius: 10px; margin-bottom: 25px; border: 1px solid rgba(46, 204, 113, 0.3);">
            <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 15px;">
                <i class="fas fa-check-circle fa-2x"></i>
                <div style="font-size: 1.1rem; font-weight: 600;"><?php echo $success; ?></div>
            </div>
            <div style="text-align: center; margin-top: 20px;">
                <a href="<?php echo SITE_URL; ?>index.php?page=customer/my-bookings" class="btn btn-primary" style="padding: 12px 30px;">
                    <i class="fas fa-ticket-alt"></i> Back to My Bookings
                </a>
            </div>
        </div>
    <?php endif; ?>

    <?php if (empty($success)): ?>

    <!-- Movie Info -->
    <div style="background: linear-gradient(135deg, rgba(226,48,32,0.1), rgba(193,27,24,0.2)); border-radius: 15px; padding: 25px; margin-bottom: 30px; border: 1px solid rgba(226, 48, 32, 0.3);">
        <div style="display: flex; gap: 25px; align-items: flex-start; flex-wrap: wrap;">
            <?php if (!empty($booking['poster_url'])): ?>
            <img src="<?php echo $booking['poster_url']; ?>" 
                 alt="<?php echo htmlspecialchars($booking['movie_title'] ?? ''); ?>"
                 style="width: 100px; height: 140px; object-fit: cover; border-radius: 10px; flex-shrink: 0;">
            <?php else: ?>
            <div style="width: 100px; height: 140px; background: linear-gradient(135deg, rgba(226, 48, 32, 0.1), rgba(193, 27, 24, 0.2)); 
                 border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                <i class="fas fa-film" style="font-size: 3rem; color: rgba(255, 255, 255, 0.3);"></i>
            </div>
            <?php endif; ?>
            
            <div style="flex: 1;">
                <h2 style="color: white; font-size: 1.8rem; margin-bottom: 10px; font-weight: 700;">
                    <?php echo htmlspecialchars($booking['movie_title'] ?? ''); ?>
                </h2>
                
                <div style="display: flex; flex-wrap: wrap; gap: 15px; margin-bottom: 15px;">
                    <span style="background: var(--primary-red); color: white; padding: 4px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 600;">
                        <i class="fas fa-star"></i> <?php echo $booking['rating'] ?: 'PG'; ?>
                    </span>
                    <span style="background: rgba(255,255,255,0.1); color: var(--pale-red); padding: 4px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 600;">
                        <i class="fas fa-clock"></i> <?php echo $booking['duration'] ?? ''; ?>
                    </span>
                    <span style="background: rgba(255,255,255,0.1); color: var(--pale-red); padding: 4px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 600;">
                        <i class="fas fa-tag"></i> <?php echo htmlspecialchars($booking['genre'] ?? ''); ?>
                    </span>
                </div>
                
                <div style="background: rgba(52, 152, 219, 0.1); padding: 15px; border-radius: 8px; border-left: 4px solid #3498db;">
                    <p style="color: white; margin-bottom: 5px;">
                        <i class="fas fa-info-circle" style="color: #3498db;"></i> 
                        Original booking: <strong><?php echo $total_original_seats; ?> seat(s)</strong>
                    </p>
                    <p style="color: var(--pale-red); font-size: 0.9rem;">
                        <?php 
                        $seat_type_display = [];
                        foreach ($original_seat_counts as $type => $count) {
                            $seat_type_display[] = "$count $type";
                        }
                        echo implode(', ', $seat_type_display);
                        ?> • 
                        Total: <strong>₱<?php echo number_format($booking['total_amount'] ?? 0, 2); ?></strong>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Step 1: Select Showtime -->
    <div style="background: linear-gradient(135deg, rgba(226,48,32,0.1), rgba(193,27,24,0.2)); border-radius: 15px; padding: 25px; margin-bottom: 30px; border: 1px solid rgba(226, 48, 32, 0.3);">
        <h3 style="color: white; font-size: 1.4rem; margin-bottom: 20px; font-weight: 700;">
            <i class="fas fa-calendar-alt"></i> Select Showtime (Optional)
        </h3>
        <p style="color: var(--pale-red); font-size: 0.9rem; margin-bottom: 15px;">
            You can keep the same showtime or choose a different one. Current showtime is highlighted.
        </p>
        
        <?php if (empty($schedules)): ?>
        <div style="text-align: center; padding: 30px; color: var(--pale-red);">
            <i class="fas fa-calendar-times fa-2x" style="margin-bottom: 15px; opacity: 0.7;"></i>
            <p>No available showtimes for this movie.</p>
        </div>
        <?php else: ?>
        <form method="POST" action="" id="scheduleForm">
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 15px;">
                <?php foreach ($schedules as $schedule): 
                    $is_selected = $selected_schedule_id == $schedule['id'];
                    $is_today = date('Y-m-d') == $schedule['show_date'];
                    $show_date = date('D, M d, Y', strtotime($schedule['show_date']));
                    $show_time = date('h:i A', strtotime($schedule['showtime']));
                    $is_current = isset($schedule['is_current']) && $schedule['is_current'] === true;
                ?>
                <label style="cursor: pointer;">
                    <input type="radio" name="schedule_id" value="<?php echo $schedule['id']; ?>" 
                           <?php echo $is_selected ? 'checked' : ''; ?> 
                           class="schedule-radio" style="display: none;"
                           onchange="this.form.submit()">
                    <div style="background: <?php echo $is_selected ? 'rgba(226, 48, 32, 0.2)' : 'rgba(255, 255, 255, 0.05)'; ?>; 
                         border: 2px solid <?php echo $is_current ? '#2ecc71' : ($is_selected ? 'var(--primary-red)' : 'rgba(226, 48, 32, 0.3)'); ?>; 
                         border-radius: 10px; padding: 15px; transition: all 0.3s ease;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                            <span style="font-size: 1.2rem; color: white; font-weight: 700;"><?php echo $show_time; ?></span>
                            <?php if ($is_today): ?>
                            <span style="background: var(--primary-red); color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.7rem;">TODAY</span>
                            <?php endif; ?>
                        </div>
                        <div style="color: var(--pale-red); font-size: 0.9rem; margin-bottom: 10px;">
                            <?php echo $show_date; ?>
                        </div>
                        <div style="color: #3498db; font-size: 0.85rem; margin-bottom: 5px;">
                            <i class="fas fa-building"></i> <?php echo htmlspecialchars($schedule['venue_name']); ?> - <?php echo htmlspecialchars($schedule['screen_name']); ?>
                        </div>
                        <div style="color: #2ecc71; font-size: 0.85rem; font-weight: 600;">
                            <i class="fas fa-chair"></i> <?php echo $schedule['available_seats']; ?> seats available
                        </div>
                        <?php if ($is_current): ?>
                        <div style="color: #2ecc71; font-size: 0.8rem; margin-top: 5px;">
                            <i class="fas fa-check-circle"></i> Current showtime
                        </div>
                        <?php endif; ?>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>
        </form>
        <?php endif; ?>
    </div>

    <!-- Step 2: Select Seats -->
    <?php if ($selected_schedule_id > 0 && !empty($seats)): ?>
    <div style="background: linear-gradient(135deg, rgba(226,48,32,0.1), rgba(193,27,24,0.2)); border-radius: 15px; padding: 30px; border: 1px solid rgba(226, 48, 32, 0.3);">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;">
            <div>
                <h3 style="color: white; font-size: 1.4rem; font-weight: 700;">
                    <i class="fas fa-chair"></i> Select Your New Seats
                </h3>
                <p style="color: var(--pale-red); font-size: 0.9rem;">
                    Select <strong><?php echo $total_original_seats; ?> seat(s)</strong> matching your original seat types:
                </p>
            </div>
            <div style="color: white; font-weight: 700; font-size: 1.1rem; background: rgba(255,255,255,0.1); 
                 padding: 10px 20px; border-radius: 10px;">
                <span id="selectedCount">0</span>/<?php echo $total_original_seats; ?> selected
            </div>
        </div>

        <!-- Seat Type Requirements -->
        <div style="display: flex; gap: 20px; margin-bottom: 25px; flex-wrap: wrap; padding: 15px; 
             background: rgba(255,255,255,0.05); border-radius: 10px;">
            <?php foreach ($original_seat_counts as $type => $count): ?>
            <div style="display: flex; align-items: center; gap: 10px;">
                <div style="width: 25px; height: 25px; background: <?php 
                    echo $type == 'Premium' ? '#FFD700' : ($type == 'Sweet Spot' ? '#e74c3c' : '#3498db'); 
                ?>; border-radius: 5px;"></div>
                <span style="color: white;">Need <strong><?php echo $count; ?> <?php echo $type; ?></strong></span>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Screen Visual -->
        <div style="background: linear-gradient(to bottom, rgba(52, 152, 219, 0.3), rgba(41, 128, 185, 0.2)); 
             padding: 20px; border-radius: 12px; margin-bottom: 30px; text-align: center;">
            <div style="color: white; font-weight: 800; font-size: 1.3rem; margin-bottom: 8px;">
                <i class="fas fa-tv"></i> SCREEN
            </div>
            <div style="height: 8px; background: linear-gradient(to right, #3498db, #2ecc71, #3498db); 
                 border-radius: 4px; width: 85%; margin: 0 auto;"></div>
        </div>

        <form method="POST" action="" id="rebookForm">
            <input type="hidden" name="schedule_id" value="<?php echo $selected_schedule_id; ?>">
            
            <div id="seatsContainer" style="margin-bottom: 30px; overflow-x: auto;">
                <?php 
                // Organize seats by row
                $rows = [];
                foreach ($seats as $seat) {
                    $seat_num = $seat['seat_number'];
                    $row_letter = $seat_num[0];
                    $col = intval(substr($seat_num, 1));
                    $rows[$row_letter][$col] = $seat;
                }
                ksort($rows);
                ?>
                
                <div style="display: flex; flex-direction: column; align-items: center; gap: 12px; min-width: 500px;">
                    <?php foreach ($rows as $row_letter => $row_seats):
                        ksort($row_seats);
                    ?>
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <div style="font-weight: 800; color: #9b59b6; width: 40px; text-align: center;"><?php echo $row_letter; ?></div>
                        <div style="display: flex; gap: 8px; flex-wrap: wrap; justify-content: center;">
                            <?php foreach ($row_seats as $col => $seat):
                                $is_available = $seat['can_select'];
                                $is_current = $seat['is_current'] ?? false;
                                $is_selected = in_array($seat['seat_number'], $selected_seats);
                                $seat_type = $seat['seat_type'];
                                $price = $seat['price'];
                                
                                if ($is_selected) {
                                    $bg_color = '#28a745';
                                    $text_color = 'white';
                                    $border = '2px solid #f39c12';
                                } elseif ($is_current) {
                                    $bg_color = '#f1c40f';
                                    $text_color = '#333';
                                    $border = '2px solid #fff';
                                } elseif ($is_available && $seat['status'] === 'available') {
                                    if ($seat_type == 'Premium') {
                                        $bg_color = '#FFD700';
                                    } elseif ($seat_type == 'Sweet Spot') {
                                        $bg_color = '#e74c3c';
                                    } else {
                                        $bg_color = '#3498db';
                                    }
                                    $text_color = 'white';
                                    $border = 'none';
                                } else {
                                    $bg_color = '#6c757d';
                                    $text_color = 'rgba(255,255,255,0.5)';
                                    $border = 'none';
                                }
                            ?>
                            <div style="text-align: center;">
                                <div class="seat-number-label" style="font-size: 0.65rem; color: rgba(255,255,255,0.5); margin-bottom: 3px;"><?php echo $col; ?></div>
                                <label style="cursor: <?php echo $is_available ? 'pointer' : 'not-allowed'; ?>;">
                                    <input type="checkbox" name="selected_seats[]" value="<?php echo $seat['seat_number']; ?>" 
                                           <?php echo $is_selected ? 'checked' : ''; ?> 
                                           <?php echo !$is_available ? 'disabled' : ''; ?>
                                           class="seat-checkbox" style="display: none;"
                                           data-seat-type="<?php echo strtolower($seat_type); ?>"
                                           data-price="<?php echo $price; ?>"
                                           data-current="<?php echo $is_current ? '1' : '0'; ?>"
                                           data-seat-number="<?php echo $seat['seat_number']; ?>">
                                    <div class="seat-cell <?php echo $is_selected ? 'selected' : ''; ?> <?php echo !$is_available ? 'booked' : 'available'; ?>"
                                         style="width: 55px; height: 55px; background: <?php echo $bg_color; ?>; border-radius: 10px 10px 6px 6px; display: flex; flex-direction: column; align-items: center; justify-content: center; color: <?php echo $text_color; ?>; font-weight: 700; box-shadow: 0 3px 8px rgba(0,0,0,0.2); <?php echo $border; ?>">
                                        <div style="font-size: 0.7rem;"><?php echo $row_letter; ?></div>
                                        <div style="font-size: 0.9rem;"><?php echo str_pad($col, 2, '0', STR_PAD_LEFT); ?></div>
                                        <div style="font-size: 0.6rem; margin-top: 3px;">₱<?php echo number_format($price, 0); ?></div>
                                    </div>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Legend -->
            <div style="display: flex; gap: 20px; justify-content: center; flex-wrap: wrap; margin-bottom: 25px; padding: 15px; background: rgba(0,0,0,0.2); border-radius: 10px;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 25px; height: 25px; background: #f1c40f; border-radius: 6px; border: 2px solid white;"></div>
                    <span style="color: white;">Current Seat</span>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 25px; height: 25px; background: #3498db; border-radius: 6px;"></div>
                    <span style="color: white;">Standard</span>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 25px; height: 25px; background: #FFD700; border-radius: 6px;"></div>
                    <span style="color: white;">Premium</span>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 25px; height: 25px; background: #e74c3c; border-radius: 6px;"></div>
                    <span style="color: white;">Sweet Spot</span>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 25px; height: 25px; background: #28a745; border-radius: 6px; border: 2px solid #f39c12;"></div>
                    <span style="color: white;">Selected</span>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 25px; height: 25px; background: #6c757d; border-radius: 6px;"></div>
                    <span style="color: white;">Booked</span>
                </div>
            </div>

            <div style="text-align: center; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.1);">
                <button type="submit" name="confirm_rebooking" class="btn btn-success" style="padding: 15px 50px; font-size: 1.1rem;">
                    <i class="fas fa-redo-alt"></i> Confirm Seat Changes
                </button>
            </div>
        </form>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<style>
.rebook-modal {
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translate(-50%, -60%);
    }
    to {
        opacity: 1;
        transform: translate(-50%, -50%);
    }
}

.seat-cell {
    transition: all 0.2s ease;
    cursor: pointer;
}

.seat-cell.selected {
    transform: scale(1.05);
    box-shadow: 0 0 0 3px #f39c12;
}

.seat-cell.booked {
    cursor: not-allowed;
    opacity: 0.6;
}

.seat-cell.available:hover {
    transform: scale(1.1);
    filter: brightness(1.1);
}

.schedule-radio:checked + div {
    background: rgba(226, 48, 32, 0.2) !important;
    border-color: var(--primary-red) !important;
}

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

:root {
    --primary-red: #e23020;
    --dark-red: #c11b18;
    --deep-red: #a80f0f;
    --light-red: #ff6b6b;
    --pale-red: #ff9999;
}

@media (max-width: 768px) {
    .rebook-modal {
        width: 100%;
        height: 100%;
        max-height: 100vh;
        border-radius: 0;
        top: 0;
        left: 0;
        transform: none;
        padding: 15px;
    }
    
    .seat-cell {
        width: 45px !important;
        height: 45px !important;
    }
    
    .seat-number-label {
        font-size: 0.55rem !important;
    }
}

@media (max-width: 576px) {
    .seat-cell {
        width: 38px !important;
        height: 45px !important;
    }
    
    .seat-cell div {
        font-size: 0.6rem !important;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const seatCheckboxes = document.querySelectorAll('.seat-checkbox');
    const selectedCountSpan = document.getElementById('selectedCount');
    const maxSeats = <?php echo $total_original_seats; ?>;

    // Original seat counts from PHP
    const originalSeatCounts = <?php echo json_encode($original_seat_counts); ?>;

    function getSelectedCounts() {
        const counts = {};
        const selected = Array.from(seatCheckboxes).filter(cb => cb.checked);
        
        selected.forEach(cb => {
            const type = cb.dataset.seatType;
            if (type) {
                // Normalize type case
                const normalizedType = type.charAt(0).toUpperCase() + type.slice(1).toLowerCase();
                counts[normalizedType] = (counts[normalizedType] || 0) + 1;
            }
        });
        
        return counts;
    }

    function validateSeatSelection() {
        const selected = Array.from(seatCheckboxes).filter(cb => cb.checked);
        const counts = getSelectedCounts();
        let isValid = true;
        let message = [];

        if (selected.length !== maxSeats) {
            return { valid: false, message: `Please select exactly ${maxSeats} seat(s).` };
        }

        for (let type in originalSeatCounts) {
            if ((counts[type] || 0) !== originalSeatCounts[type]) {
                isValid = false;
                message.push(`${type}: need ${originalSeatCounts[type]}, selected ${counts[type] || 0}`);
            }
        }

        for (let type in counts) {
            if (!originalSeatCounts[type]) {
                isValid = false;
                message.push(`${type}: should not select this seat type`);
            }
        }

        if (!isValid) {
            return { valid: false, message: 'Seat type mismatch: ' + message.join(', ') };
        }

        return { valid: true };
    }

    function updateSelectedCount() {
        const selected = Array.from(seatCheckboxes).filter(cb => cb.checked);
        const counts = getSelectedCounts();
        
        if (selectedCountSpan) {
            selectedCountSpan.textContent = selected.length;
            
            let allMatch = true;
            for (let type in originalSeatCounts) {
                if ((counts[type] || 0) !== originalSeatCounts[type]) {
                    allMatch = false;
                    break;
                }
            }
            
            if (selected.length === maxSeats && allMatch) {
                selectedCountSpan.style.color = '#2ecc71';
            } else if (selected.length > maxSeats) {
                selectedCountSpan.style.color = '#ff6b6b';
            } else {
                selectedCountSpan.style.color = 'white';
            }
        }
    }

    // Add click handlers for seat cells
    document.querySelectorAll('.seat-cell.available, .seat-cell.selected').forEach(cell => {
        cell.addEventListener('click', function(e) {
            e.stopPropagation();
            const parentLabel = this.closest('label');
            const checkbox = parentLabel?.querySelector('.seat-checkbox');
            if (checkbox && !checkbox.disabled) {
                checkbox.checked = !checkbox.checked;
                // Trigger change event
                checkbox.dispatchEvent(new Event('change'));
                
                // Update visual
                if (checkbox.checked) {
                    this.classList.add('selected');
                    this.style.border = '2px solid #f39c12';
                } else {
                    this.classList.remove('selected');
                    // Restore original color based on seat type
                    const seatType = checkbox.dataset.seatType;
                    const isCurrent = checkbox.dataset.current === '1';
                    
                    if (isCurrent) {
                        this.style.background = '#f1c40f';
                        this.style.border = '2px solid white';
                    } else if (seatType === 'premium') {
                        this.style.background = '#FFD700';
                        this.style.border = 'none';
                    } else if (seatType === 'sweet spot') {
                        this.style.background = '#e74c3c';
                        this.style.border = 'none';
                    } else {
                        this.style.background = '#3498db';
                        this.style.border = 'none';
                    }
                }
            }
        });
    });

    seatCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const selected = Array.from(seatCheckboxes).filter(cb => cb.checked);
            const isCurrent = this.dataset.current === '1';
            
            if (selected.length > maxSeats) {
                this.checked = false;
                alert(`You can only select up to ${maxSeats} seat(s).`);
            } else {
                const seatDiv = this.parentElement.querySelector('.seat-cell');
                if (this.checked) {
                    seatDiv.style.background = '#28a745';
                    seatDiv.style.border = '2px solid #f39c12';
                } else {
                    if (isCurrent) {
                        seatDiv.style.background = '#f1c40f';
                        seatDiv.style.border = '2px solid white';
                    } else {
                        const seatType = this.dataset.seatType;
                        if (seatType === 'premium') {
                            seatDiv.style.background = '#FFD700';
                        } else if (seatType === 'sweet spot') {
                            seatDiv.style.background = '#e74c3c';
                        } else {
                            seatDiv.style.background = '#3498db';
                        }
                        seatDiv.style.border = 'none';
                    }
                }
            }
            
            updateSelectedCount();
        });
    });
    
    const rebookForm = document.getElementById('rebookForm');
    if (rebookForm) {
        rebookForm.addEventListener('submit', function(e) {
            const validation = validateSeatSelection();
            
            if (!validation.valid) {
                e.preventDefault();
                alert(validation.message);
                return false;
            }
            
            const allCurrent = Array.from(seatCheckboxes)
                .filter(cb => cb.checked)
                .every(cb => cb.dataset.current === '1');
                
            if (allCurrent && <?php echo $selected_schedule_id == $booking['schedule_id'] ? 'true' : 'false'; ?>) {
                e.preventDefault();
                alert('You must select different seats to rebook.');
                return false;
            }
            
            return true;
        });
    }
    
    updateSelectedCount();
});
</script>

<?php
require_once $root_dir . '/partials/footer.php';
?>