<?php
$root_dir = dirname(dirname(__DIR__));
require_once $root_dir . '/includes/config.php';
require_once $root_dir . '/includes/functions.php';
require_once $root_dir . '/includes/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Customer') {
    header("Location: " . SITE_URL . "index.php?page=login");
    exit();
}

$conn = get_db();
$user_id = $_SESSION['user_id'];
$error = '';
$success = '';
$booking = null;

$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;

if ($booking_id <= 0) {
    header("Location: " . SITE_URL . "index.php?page=customer/my-bookings");
    exit();
}

$booking_stmt = $conn->prepare("
    SELECT 
        b.*,
        GROUP_CONCAT(bs.seat_number ORDER BY bs.seat_number SEPARATOR ', ') as seat_numbers,
        GROUP_CONCAT(bs.seat_type ORDER BY bs.seat_number SEPARATOR ', ') as seat_types,
        COUNT(bs.id) as total_seats,
        SUM(bs.price) as total_price,
        m.id as movie_id,
        m.title,
        m.poster_url,
        m.genre,
        m.duration,
        m.rating,
        m.standard_price,
        m.premium_price,
        m.sweet_spot_price,
        ms.id as current_schedule_id
    FROM tbl_booking b
    JOIN movies m ON b.movie_name = m.title
    LEFT JOIN movie_schedules ms ON b.movie_name = ms.movie_title 
        AND b.show_date = ms.show_date 
        AND b.showtime = ms.showtime
    LEFT JOIN booked_seats bs ON b.b_id = bs.booking_id
    WHERE b.b_id = ? AND b.u_id = ? AND b.payment_status = 'Paid'
    GROUP BY b.b_id
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

$original_seats_query = $conn->prepare("
    SELECT seat_number, seat_type, price
    FROM booked_seats
    WHERE booking_id = ?
");
$original_seats_query->bind_param("i", $booking_id);
$original_seats_query->execute();
$original_seats_result = $original_seats_query->get_result();
$original_seat_counts = [];
$original_seat_details = [];
$current_seats_array = [];

while ($row = $original_seats_result->fetch_assoc()) {
    $original_seat_counts[$row['seat_type']] = ($original_seat_counts[$row['seat_type']] ?? 0) + 1;
    $original_seat_details[] = $row;
    $current_seats_array[] = $row['seat_number'];
}
$original_seats_query->close();

$current_seats = implode(', ', $current_seats_array);
$total_original_seats = count($current_seats_array);

$schedules_stmt = $conn->prepare("
    SELECT s.*, m.standard_price, m.premium_price, m.sweet_spot_price
    FROM movie_schedules s
    JOIN movies m ON s.movie_id = m.id
    WHERE s.movie_id = ? AND s.is_active = 1 
    AND s.show_date >= CURDATE() AND s.available_seats > 0
    ORDER BY s.show_date, s.showtime
");
$schedules_stmt->bind_param("i", $booking['movie_id']);
$schedules_stmt->execute();
$schedules_result = $schedules_stmt->get_result();
$schedules = [];
while ($row = $schedules_result->fetch_assoc()) {
    $schedules[] = $row;
}
$schedules_stmt->close();

$selected_schedule_id = isset($_POST['schedule_id']) ? intval($_POST['schedule_id']) : 
                       (isset($_GET['schedule']) ? intval($_GET['schedule']) : $booking['current_schedule_id']);

$selected_seats = isset($_POST['selected_seats']) ? $_POST['selected_seats'] : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_rebooking'])) {
    $schedule_id = isset($_POST['schedule_id']) ? intval($_POST['schedule_id']) : 0;
    $selected_seats = isset($_POST['selected_seats']) ? $_POST['selected_seats'] : [];
    
    if ($schedule_id <= 0) {
        $error = "Please select a showtime!";
    } elseif (empty($selected_seats)) {
        $error = "Please select at least one seat!";
    } else {
        $schedule_stmt = $conn->prepare("SELECT * FROM movie_schedules WHERE id = ? AND is_active = 1");
        $schedule_stmt->bind_param("i", $schedule_id);
        $schedule_stmt->execute();
        $schedule_result = $schedule_stmt->get_result();
        
        if ($schedule_result->num_rows === 0) {
            $error = "Invalid schedule selected!";
        } else {
            $schedule = $schedule_result->fetch_assoc();
            $schedule_stmt->close();
            
            $selected_seat_counts = [];
            $seat_check_failed = false;
            $unavailable_seats = [];
            $new_seat_details = [];
            
            foreach ($selected_seats as $seat_number) {
                $seat_check = $conn->prepare("
                    SELECT is_available, price, seat_type 
                    FROM seat_availability 
                    WHERE schedule_id = ? 
                    AND seat_number = ? 
                    AND is_available = 1
                ");
                $seat_check->bind_param("is", $schedule_id, $seat_number);
                $seat_check->execute();
                $seat_result = $seat_check->get_result();
                
                if ($seat_result->num_rows === 0) {
                    if (!in_array($seat_number, $current_seats_array)) {
                        $seat_check_failed = true;
                        $unavailable_seats[] = $seat_number;
                    } else {
                        $seat_data = [
                            'number' => $seat_number,
                            'type' => $this_seat_type ?? 'Standard',
                            'price' => 0
                        ];
                        foreach ($original_seat_details as $orig) {
                            if ($orig['seat_number'] == $seat_number) {
                                $seat_data['type'] = $orig['seat_type'];
                                $seat_data['price'] = $orig['price'];
                                break;
                            }
                        }
                        $new_seat_details[] = $seat_data;
                        $selected_seat_counts[$seat_data['type']] = ($selected_seat_counts[$seat_data['type']] ?? 0) + 1;
                    }
                } else {
                    $seat_data = $seat_result->fetch_assoc();
                    $new_seat_details[] = [
                        'number' => $seat_number,
                        'type' => $seat_data['seat_type'],
                        'price' => $seat_data['price']
                    ];
                    $selected_seat_counts[$seat_data['seat_type']] = ($selected_seat_counts[$seat_data['seat_type']] ?? 0) + 1;
                }
                $seat_check->close();
            }
            
            if ($seat_check_failed) {
                $error = "Seats " . implode(", ", $unavailable_seats) . " are not available!";
            } else {
                $count_mismatch = false;
                $mismatch_message = [];
                
                foreach ($original_seat_counts as $type => $count) {
                    $selected_count = $selected_seat_counts[$type] ?? 0;
                    if ($selected_count != $count) {
                        $count_mismatch = true;
                        $mismatch_message[] = "$type: need $count, selected $selected_count";
                    }
                }
                
                foreach ($selected_seat_counts as $type => $count) {
                    if (!isset($original_seat_counts[$type])) {
                        $count_mismatch = true;
                        $mismatch_message[] = "$type: should not select this seat type";
                    }
                }
                
                if ($count_mismatch) {
                    $error = "Seat count mismatch! Your original booking had: " . implode(", ", $mismatch_message);
                } elseif (count($selected_seats) != $total_original_seats) {
                    $error = "You must select exactly $total_original_seats seat(s) to match your original booking.";
                } else {
                    $conn->begin_transaction();
                    
                    try {
                        $total_fee = 0;
                        foreach ($new_seat_details as $seat) {
                            $total_fee += $seat['price'];
                        }
                        
                        if (abs($total_fee - $booking['booking_fee']) > 0.01) {
                            throw new Exception("Total price must match your original payment of ₱" . number_format($booking['booking_fee'], 2));
                        }
                        
                        $seats_to_remove = array_diff($current_seats_array, $selected_seats);
                        $seats_to_add = array_diff($selected_seats, $current_seats_array);
                        
                        if ($schedule_id != $booking['current_schedule_id']) {
                            foreach ($current_seats_array as $seat_number) {
                                $seat_update = $conn->prepare("
                                    UPDATE seat_availability 
                                    SET is_available = 1, booking_id = NULL
                                    WHERE schedule_id = ? 
                                    AND seat_number = ?
                                ");
                                $seat_update->bind_param("is", $booking['current_schedule_id'], $seat_number);
                                if (!$seat_update->execute()) {
                                    throw new Exception("Failed to release old seats from old schedule!");
                                }
                                $seat_update->close();
                            }
                            
                            $old_seat_count = count($current_seats_array);
                            $update_old_schedule = $conn->prepare("
                                UPDATE movie_schedules 
                                SET available_seats = available_seats + ?
                                WHERE id = ?
                            ");
                            $update_old_schedule->bind_param("ii", $old_seat_count, $booking['current_schedule_id']);
                            if (!$update_old_schedule->execute()) {
                                throw new Exception("Failed to update old schedule!");
                            }
                            $update_old_schedule->close();
                            
                            $update_booking = $conn->prepare("
                                UPDATE tbl_booking 
                                SET show_date = ?, showtime = ?
                                WHERE b_id = ?
                            ");
                            $update_booking->bind_param("ssi", $schedule['show_date'], $schedule['showtime'], $booking_id);
                            if (!$update_booking->execute()) {
                                throw new Exception("Failed to update booking showtime!");
                            }
                            $update_booking->close();
                        }
                        
                        foreach ($seats_to_remove as $seat_number) {
                            $seat_update = $conn->prepare("
                                UPDATE seat_availability 
                                SET is_available = 1, booking_id = NULL
                                WHERE schedule_id = ? 
                                AND seat_number = ?
                            ");
                            $seat_update->bind_param("is", $schedule_id, $seat_number);
                            if (!$seat_update->execute()) {
                                throw new Exception("Failed to release seat: $seat_number");
                            }
                            $seat_update->close();
                        }
                        
                        foreach ($seats_to_add as $seat_number) {
                            $seat_update = $conn->prepare("
                                UPDATE seat_availability 
                                SET is_available = 0, booking_id = ?
                                WHERE schedule_id = ? 
                                AND seat_number = ?
                            ");
                            $seat_update->bind_param("iis", $booking_id, $schedule_id, $seat_number);
                            if (!$seat_update->execute()) {
                                throw new Exception("Failed to book new seat: $seat_number");
                            }
                            $seat_update->close();
                        }
                        
                        $delete_old_seats = $conn->prepare("
                            DELETE FROM booked_seats 
                            WHERE booking_id = ?
                        ");
                        $delete_old_seats->bind_param("i", $booking_id);
                        if (!$delete_old_seats->execute()) {
                            throw new Exception("Failed to update booked seats record");
                        }
                        $delete_old_seats->close();
                        
                        $insert_new_seats = $conn->prepare("
                            INSERT INTO booked_seats (booking_id, seat_number, seat_type, price)
                            VALUES (?, ?, ?, ?)
                        ");
                        
                        foreach ($new_seat_details as $seat) {
                            $seat_type = $seat['type'];
                            $seat_price = $seat['price'];
                            $insert_new_seats->bind_param("issd", $booking_id, $seat['number'], $seat_type, $seat_price);
                            if (!$insert_new_seats->execute()) {
                                throw new Exception("Failed to insert booked seat: " . $seat['number']);
                            }
                        }
                        $insert_new_seats->close();
                        
                        $net_change = count($seats_to_add) - count($seats_to_remove);
                        if ($net_change != 0 && $schedule_id == $booking['current_schedule_id']) {
                            $update_schedule = $conn->prepare("
                                UPDATE movie_schedules 
                                SET available_seats = available_seats - ?
                                WHERE id = ?
                            ");
                            $update_schedule->bind_param("ii", $net_change, $schedule_id);
                            if (!$update_schedule->execute()) {
                                throw new Exception("Failed to update schedule availability!");
                            }
                            $update_schedule->close();
                        } elseif ($schedule_id != $booking['current_schedule_id']) {
                            $update_schedule = $conn->prepare("
                                UPDATE movie_schedules 
                                SET available_seats = available_seats - ?
                                WHERE id = ?
                            ");
                            $new_seat_count = count($seats_to_add);
                            $update_schedule->bind_param("ii", $new_seat_count, $schedule_id);
                            if (!$update_schedule->execute()) {
                                throw new Exception("Failed to update new schedule!");
                            }
                            $update_schedule->close();
                        }
                        
                        $conn->commit();
                        
                        $success = "Rebooking successful! Your seats have been updated.";
                        $selected_seats = [];
                        
                        header("Refresh:2; url=" . SITE_URL . "index.php?page=customer/my-bookings");
                        exit();
                        
                    } catch (Exception $e) {
                        $conn->rollback();
                        $error = "Rebooking failed: " . $e->getMessage();
                    }
                }
            }
        }
    }
}

$seats = [];
$selected_schedule_data = null;
$seat_prices = [];

if ($selected_schedule_id > 0) {
    $schedule_info = $conn->prepare("
        SELECT s.*, m.standard_price, m.premium_price, m.sweet_spot_price 
        FROM movie_schedules s
        JOIN movies m ON s.movie_id = m.id
        WHERE s.id = ? AND s.is_active = 1
    ");
    $schedule_info->bind_param("i", $selected_schedule_id);
    $schedule_info->execute();
    $schedule_info_result = $schedule_info->get_result();
    
    if ($schedule_info_result->num_rows > 0) {
        $selected_schedule_data = $schedule_info_result->fetch_assoc();
        $seat_prices = [
            'Standard' => $selected_schedule_data['standard_price'] ?? 350,
            'Premium' => $selected_schedule_data['premium_price'] ?? 450,
            'Sweet Spot' => $selected_schedule_data['sweet_spot_price'] ?? 550
        ];
    }
    $schedule_info->close();
    
    $seats_stmt = $conn->prepare("
        SELECT seat_number, is_available, seat_type, price 
        FROM seat_availability 
        WHERE schedule_id = ? 
        ORDER BY seat_number
    ");
    $seats_stmt->bind_param("i", $selected_schedule_id);
    $seats_stmt->execute();
    $seats_result = $seats_stmt->get_result();
    
    while ($row = $seats_result->fetch_assoc()) {
        $seats[] = $row;
    }
    $seats_stmt->close();
}

$type_counts = [];
if (!empty($schedules)) {
    foreach ($schedules as $schedule) {
        $type_check = $conn->prepare("
            SELECT seat_type, COUNT(*) as count 
            FROM seat_availability 
            WHERE schedule_id = ? AND is_available = 1
            GROUP BY seat_type
        ");
        $type_check->bind_param("i", $schedule['id']);
        $type_check->execute();
        $type_result = $type_check->get_result();
        $type_counts[$schedule['id']] = [];
        while ($row = $type_result->fetch_assoc()) {
            $type_counts[$schedule['id']][$row['seat_type']] = $row['count'];
        }
        $type_check->close();
    }
}

$conn->close();

require_once $root_dir . '/partials/header.php';
?>

<div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 999; pointer-events: none;"></div>

<div class="rebook-modal" style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 95%; max-width: 1400px; max-height: 90vh; overflow-y: auto; background: linear-gradient(135deg, var(--bg-dark) 0%, var(--bg-darker) 100%); border-radius: 20px; border: 2px solid var(--primary-red); box-shadow: 0 20px 50px rgba(0,0,0,0.5); z-index: 1000; padding: 30px;">
    
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid var(--primary-red);">
        <div>
            <h1 style="color: white; font-size: 2rem; font-weight: 800;">
                <i class="fas fa-redo-alt" style="color: var(--primary-red);"></i> Change Your Seats
            </h1>
            <p style="color: var(--pale-red); font-size: 1rem;">
                Booking Reference: <strong><?php echo htmlspecialchars($booking['booking_reference'] ?? ''); ?></strong> • 
                Current Seats: <strong style="color: #f1c40f;"><?php echo htmlspecialchars($current_seats ?: 'None'); ?></strong>
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

    <div style="background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%); 
         border-radius: 15px; padding: 25px; margin-bottom: 30px; 
         border: 1px solid rgba(226, 48, 32, 0.3);">
        <div style="display: flex; gap: 25px; align-items: flex-start;">
            <?php if (!empty($booking['poster_url'])): ?>
            <img src="<?php echo $booking['poster_url']; ?>" 
                 alt="<?php echo htmlspecialchars($booking['title'] ?? ''); ?>"
                 style="width: 120px; height: 160px; object-fit: cover; border-radius: 10px; flex-shrink: 0;">
            <?php else: ?>
            <div style="width: 120px; height: 160px; background: linear-gradient(135deg, rgba(226, 48, 32, 0.1), rgba(193, 27, 24, 0.2)); 
                 border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                <i class="fas fa-film" style="font-size: 3rem; color: rgba(255, 255, 255, 0.3);"></i>
            </div>
            <?php endif; ?>
            
            <div style="flex: 1;">
                <h2 style="color: white; font-size: 1.8rem; margin-bottom: 10px; font-weight: 700;">
                    <?php echo htmlspecialchars($booking['title'] ?? ''); ?>
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
                        Total: <strong>₱<?php echo number_format($booking['booking_fee'] ?? 0, 2); ?></strong>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div style="background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%); 
         border-radius: 15px; padding: 25px; margin-bottom: 30px; 
         border: 1px solid rgba(226, 48, 32, 0.3);">
        <h3 style="color: white; font-size: 1.4rem; margin-bottom: 20px; font-weight: 700;">
            <i class="fas fa-calendar-alt"></i> Select New Showtime (Optional)
        </h3>
        <p style="color: var(--pale-red); font-size: 0.9rem; margin-bottom: 15px;">
            You can keep the same showtime or choose a different one.
        </p>
        
        <?php if (empty($schedules)): ?>
        <div style="text-align: center; padding: 30px; color: var(--pale-red);">
            <i class="fas fa-calendar-times fa-2x" style="margin-bottom: 15px; opacity: 0.7;"></i>
            <p>No available showtimes for this movie.</p>
        </div>
        <?php else: ?>
        <form method="POST" action="" id="scheduleForm">
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 15px;">
                <?php foreach ($schedules as $schedule): 
                    $is_selected = $selected_schedule_id == $schedule['id'];
                    $is_today = date('Y-m-d') == $schedule['show_date'];
                    $show_date = date('D, M d, Y', strtotime($schedule['show_date']));
                    $show_time = date('h:i A', strtotime($schedule['showtime']));
                ?>
                <label style="cursor: pointer;">
                    <input type="radio" name="schedule_id" value="<?php echo $schedule['id']; ?>" 
                           <?php echo $is_selected ? 'checked' : ''; ?> 
                           class="schedule-radio" style="display: none;"
                           onchange="this.form.submit()">
                    <div style="background: <?php echo $is_selected ? 'rgba(226, 48, 32, 0.2)' : 'rgba(255, 255, 255, 0.05)'; ?>; 
                         border: 2px solid <?php echo $is_selected ? 'var(--primary-red)' : 'rgba(226, 48, 32, 0.3)'; ?>; 
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
                        <div style="color: #2ecc71; font-size: 0.9rem; font-weight: 600;">
                            <?php 
                            $available_text = [];
                            if (isset($type_counts[$schedule['id']])) {
                                foreach ($type_counts[$schedule['id']] as $type => $count) {
                                    $available_text[] = "$count $type";
                                }
                            }
                            echo !empty($available_text) ? implode(', ', $available_text) : 'No seats available';
                            ?>
                        </div>
                        <?php if ($schedule['id'] == $booking['current_schedule_id']): ?>
                        <div style="color: #f1c40f; font-size: 0.8rem; margin-top: 5px;">
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

    <?php if ($selected_schedule_id > 0 && !empty($seats)): ?>
    <div style="background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%); 
         border-radius: 15px; padding: 30px; 
         border: 1px solid rgba(226, 48, 32, 0.3);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
            <div>
                <h3 style="color: white; font-size: 1.4rem; font-weight: 700;">
                    <i class="fas fa-chair"></i> Select Your New Seats
                </h3>
                <p style="color: var(--pale-red); font-size: 0.9rem;">
                    Select <strong><?php echo $total_original_seats; ?> seat(s)</strong> matching your original seat types:
                    <?php 
                    $seat_type_requirements = [];
                    foreach ($original_seat_counts as $type => $count) {
                        $seat_type_requirements[] = "$count $type";
                    }
                    echo implode(', ', $seat_type_requirements);
                    ?>
                </p>
            </div>
            <div style="color: white; font-weight: 700; font-size: 1.1rem; background: rgba(255,255,255,0.1); 
                 padding: 10px 20px; border-radius: 10px;">
                <span id="selectedCount">0</span>/<?php echo $total_original_seats; ?> selected
            </div>
        </div>

        <div style="display: flex; gap: 20px; margin-bottom: 30px; flex-wrap: wrap; padding: 15px; 
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
            
            <div style="margin-bottom: 30px;">
                <?php 
                $rows = [];
                foreach ($seats as $seat) {
                    $seat_number = $seat['seat_number'];
                    $row = $seat_number[0];
                    $col = intval(substr($seat_number, 1));
                    $rows[$row][$col] = $seat;
                }
                ksort($rows);
                
                foreach ($rows as $row_letter => $row_seats): 
                ?>
                <div style="margin-bottom: 25px;">
                    <div style="display: flex; align-items: center; gap: 20px; margin-bottom: 15px;">
                        <div style="color: white; font-weight: 700; font-size: 1.1rem; width: 40px;"><?php echo $row_letter; ?></div>
                        <div style="height: 2px; background: rgba(255,255,255,0.1); flex: 1;"></div>
                    </div>
                    
                    <div style="display: flex; justify-content: center; gap: 10px; flex-wrap: wrap;">
                        <?php 
                        ksort($row_seats);
                        foreach ($row_seats as $col => $seat): 
                            $seat_number = $seat['seat_number'];
                            $is_current = in_array($seat_number, $current_seats_array);
                            $is_available = $seat['is_available'] == 1;
                            $is_selected = in_array($seat_number, $selected_seats);
                            
                            if ($is_current) {
                                $seat_color = '#f1c40f';
                                $seat_status = 'current';
                            } elseif ($is_selected) {
                                $seat_color = '#28a745';
                                $seat_status = 'selected';
                            } elseif ($is_available) {
                                $seat_color = $seat['seat_type'] == 'Premium' ? '#FFD700' : ($seat['seat_type'] == 'Sweet Spot' ? '#e74c3c' : '#3498db');
                                $seat_status = 'available';
                            } else {
                                $seat_color = '#6c757d';
                                $seat_status = 'booked';
                            }
                            
                            $can_select = $is_available || $is_current;
                        ?>
                        <div style="text-align: center;">
                            <div style="color: rgba(255,255,255,0.5); font-size: 0.7rem; margin-bottom: 3px;"><?php echo $col; ?></div>
                            <label style="cursor: <?php echo $can_select ? 'pointer' : 'not-allowed'; ?>; opacity: <?php echo $can_select ? '1' : '0.5'; ?>;">
                                <input type="checkbox" name="selected_seats[]" value="<?php echo $seat_number; ?>" 
                                       <?php echo $is_selected ? 'checked' : ''; ?> 
                                       <?php echo !$can_select ? 'disabled' : ''; ?>
                                       class="seat-checkbox" style="display: none;"
                                       data-seat-type="<?php echo $seat['seat_type']; ?>"
                                       data-current="<?php echo $is_current ? '1' : '0'; ?>"
                                       data-price="<?php echo $seat['price']; ?>">
                                <div style="width: 40px; height: 45px; background: <?php echo $seat_color; ?>; 
                                     border-radius: 8px 8px 4px 4px; display: flex; flex-direction: column; 
                                     align-items: center; justify-content: center; color: <?php echo $is_current ? '#333' : 'white'; ?>; 
                                     font-weight: 700; transition: all 0.3s ease; box-shadow: 0 3px 6px rgba(0,0,0,0.2);
                                     border: <?php echo $is_current ? '2px solid white' : 'none'; ?>;">
                                    <div style="font-size: 0.8rem;"><?php echo $row_letter; ?></div>
                                    <div style="font-size: 1rem;"><?php echo str_pad($col, 2, '0', STR_PAD_LEFT); ?></div>
                                    <div style="font-size: 0.6rem; margin-top: 2px;"><?php echo $seat['seat_type']; ?></div>
                                </div>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div style="display: flex; gap: 20px; justify-content: center; margin-bottom: 20px;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 20px; height: 20px; background: #f1c40f; border-radius: 4px;"></div>
                    <span style="color: white;">Current Seat</span>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 20px; height: 20px; background: #3498db; border-radius: 4px;"></div>
                    <span style="color: white;">Standard</span>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 20px; height: 20px; background: #FFD700; border-radius: 4px;"></div>
                    <span style="color: white;">Premium</span>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 20px; height: 20px; background: #e74c3c; border-radius: 4px;"></div>
                    <span style="color: white;">Sweet Spot</span>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 20px; height: 20px; background: #28a745; border-radius: 4px;"></div>
                    <span style="color: white;">Selected</span>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 20px; height: 20px; background: #6c757d; border-radius: 4px;"></div>
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

.schedule-radio:checked + div {
    background: rgba(226, 48, 32, 0.2) !important;
    border-color: var(--primary-red) !important;
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
    }
    
    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-20px); }
        to { opacity: 1; transform: translateY(0); }
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const seatCheckboxes = document.querySelectorAll('.seat-checkbox');
    const selectedCount = document.getElementById('selectedCount');
    const maxSeats = <?php echo $total_original_seats; ?>;
    const originalSeatCounts = <?php echo json_encode($original_seat_counts); ?>;
    
    function getSelectedCounts() {
        const counts = {};
        const selected = Array.from(seatCheckboxes).filter(cb => cb.checked);
        
        selected.forEach(cb => {
            const type = cb.dataset.seatType;
            counts[type] = (counts[type] || 0) + 1;
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
        
        if (selectedCount) {
            selectedCount.textContent = selected.length;
            
            let allMatch = true;
            for (let type in originalSeatCounts) {
                if ((counts[type] || 0) !== originalSeatCounts[type]) {
                    allMatch = false;
                    break;
                }
            }
            
            if (selected.length === maxSeats && allMatch) {
                selectedCount.style.color = '#2ecc71';
            } else if (selected.length > maxSeats) {
                selectedCount.style.color = '#ff6b6b';
            } else {
                selectedCount.style.color = 'white';
            }
        }
    }
    
    seatCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const selected = Array.from(seatCheckboxes).filter(cb => cb.checked);
            const isCurrent = this.dataset.current === '1';
            
            if (selected.length > maxSeats) {
                this.checked = false;
                alert(`You can only select up to ${maxSeats} seat(s).`);
            } else {
                const seatDiv = this.parentElement.querySelector('div');
                if (this.checked) {
                    seatDiv.style.background = '#28a745';
                    seatDiv.style.color = 'white';
                } else {
                    if (isCurrent) {
                        seatDiv.style.background = '#f1c40f';
                        seatDiv.style.color = '#333';
                    } else {
                        const seatType = this.dataset.seatType;
                        if (seatType === 'Premium') {
                            seatDiv.style.background = '#FFD700';
                        } else if (seatType === 'Sweet Spot') {
                            seatDiv.style.background = '#e74c3c';
                        } else {
                            seatDiv.style.background = '#3498db';
                        }
                        seatDiv.style.color = 'white';
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
                
            if (allCurrent) {
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