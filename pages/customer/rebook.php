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
$available_seats = [];

$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;
$required_seat_type = isset($_GET['seat_type']) ? $_GET['seat_type'] : '';

if ($booking_id <= 0 || empty($required_seat_type)) {
    header("Location: " . SITE_URL . "index.php?page=customer/my-bookings");
    exit();
}

$booking_stmt = $conn->prepare("
    SELECT b.*, m.id as movie_id, m.title, m.poster_url, m.genre, m.duration, m.rating,
           m.standard_price, m.premium_price, m.sweet_spot_price
    FROM tbl_booking b
    JOIN movies m ON b.movie_name = m.title
    WHERE b.b_id = ? AND b.u_id = ? AND b.payment_status = 'Paid'
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

$price_map = [
    'Standard' => $booking['standard_price'] ?? 350,
    'Premium' => $booking['premium_price'] ?? 450,
    'Sweet Spot' => $booking['sweet_spot_price'] ?? 550
];

$target_price = $price_map[$required_seat_type] ?? 0;

if ($target_price == 0) {
    header("Location: " . SITE_URL . "index.php?page=customer/my-bookings");
    exit();
}

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
                       (isset($_GET['schedule']) ? intval($_GET['schedule']) : 0);

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
            
            if (count($selected_seats) > $schedule['available_seats']) {
                $error = "Only {$schedule['available_seats']} seats available. You cannot book more than available seats.";
            } else {
                $seat_check_failed = false;
                $unavailable_seats = [];
                $seat_prices = [];
                
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
                        $seat_check_failed = true;
                        $unavailable_seats[] = $seat_number;
                    } else {
                        $seat_data = $seat_result->fetch_assoc();
                        if ($seat_data['seat_type'] !== $required_seat_type) {
                            $seat_check_failed = true;
                            $unavailable_seats[] = "$seat_number (wrong seat type)";
                        } else {
                            $seat_prices[$seat_number] = $seat_data['price'];
                        }
                    }
                    $seat_check->close();
                }
                
                if ($seat_check_failed) {
                    $error = "Seats " . implode(", ", $unavailable_seats) . " are not available or don't match your seat type!";
                } else {
                    $conn->begin_transaction();
                    
                    try {
                        $original_seat_count = substr_count($booking['seat_no'], ',') + 1;
                        $new_seat_count = count($selected_seats);
                        
                        if ($new_seat_count != $original_seat_count) {
                            throw new Exception("You must select exactly $original_seat_count seat(s) to match your original booking.");
                        }
                        
                        $booking_reference = 'RB' . date('YmdHis') . str_pad(rand(0, 99), 2, '0', STR_PAD_LEFT);
                        $seat_numbers = implode(', ', $selected_seats);
                        
                        $total_fee = array_sum($seat_prices);
                        
                        if (abs($total_fee - $booking['booking_fee']) > 0.01) {
                            throw new Exception("Total price must match your original payment of ₱" . number_format($booking['booking_fee'], 2));
                        }
                        
                        $booking_stmt = $conn->prepare("
                            INSERT INTO tbl_booking (
                                u_id, movie_name, show_date, showtime, 
                                seat_no, booking_fee, status, booking_reference, payment_status
                            ) VALUES (?, ?, ?, ?, ?, ?, 'Ongoing', ?, 'Paid')
                        ");
                        $booking_stmt->bind_param(
                            "issssds",
                            $user_id,
                            $schedule['movie_title'],
                            $schedule['show_date'],
                            $schedule['showtime'],
                            $seat_numbers,
                            $total_fee,
                            $booking_reference
                        );
                        
                        if (!$booking_stmt->execute()) {
                            throw new Exception("Failed to create rebooking: " . $booking_stmt->error);
                        }
                        
                        $new_booking_id = $booking_stmt->insert_id;
                        $booking_stmt->close();
                        
                        foreach ($selected_seats as $seat_number) {
                            $seat_update = $conn->prepare("
                                UPDATE seat_availability 
                                SET is_available = 0, booking_id = ?
                                WHERE schedule_id = ? 
                                AND seat_number = ?
                            ");
                            $seat_update->bind_param("iis", $new_booking_id, $schedule_id, $seat_number);
                            
                            if (!$seat_update->execute()) {
                                throw new Exception("Failed to update seat availability: " . $seat_update->error);
                            }
                            $seat_update->close();
                        }
                        
                        $seat_count = count($selected_seats);
                        $update_schedule = $conn->prepare("
                            UPDATE movie_schedules 
                            SET available_seats = available_seats - ?
                            WHERE id = ?
                        ");
                        $update_schedule->bind_param("ii", $seat_count, $schedule_id);
                        
                        if (!$update_schedule->execute()) {
                            throw new Exception("Failed to update schedule: " . $update_schedule->error);
                        }
                        $update_schedule->close();
                        
                        $conn->commit();
                        
                        $success = "Rebooking successful! New booking reference: <strong>$booking_reference</strong>";
                        $selected_seats = [];
                        
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
$total_seats = 0;
$available_seats = 0;
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
        $total_seats = $selected_schedule_data['total_seats'];
        $available_seats = $selected_schedule_data['available_seats'];
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
        WHERE schedule_id = ? AND seat_type = ?
        ORDER BY seat_number
    ");
    $seats_stmt->bind_param("is", $selected_schedule_id, $required_seat_type);
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
        $type_check = $conn->prepare("SELECT COUNT(*) as count FROM seat_availability WHERE schedule_id = ? AND seat_type = ? AND is_available = 1");
        $type_check->bind_param("is", $schedule['id'], $required_seat_type);
        $type_check->execute();
        $type_result = $type_check->get_result();
        $type_data = $type_result->fetch_assoc();
        $type_counts[$schedule['id']] = $type_data['count'];
        $type_check->close();
    }
}

$original_seat_count = substr_count($booking['seat_no'], ',') + 1;

require_once $root_dir . '/partials/header.php';
?>

<div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 999; pointer-events: none;"></div>

<div class="rebook-modal" style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 95%; max-width: 1400px; max-height: 90vh; overflow-y: auto; background: linear-gradient(135deg, var(--bg-dark) 0%, var(--bg-darker) 100%); border-radius: 20px; border: 2px solid var(--primary-red); box-shadow: 0 20px 50px rgba(0,0,0,0.5); z-index: 1000; padding: 30px;">
    
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid var(--primary-red);">
        <div>
            <h1 style="color: white; font-size: 2rem; font-weight: 800;">
                <i class="fas fa-redo-alt" style="color: var(--primary-red);"></i> Rebook Your Movie
            </h1>
            <p style="color: var(--pale-red); font-size: 1rem;">
                Original Booking: <strong><?php echo $booking['booking_reference']; ?></strong> • 
                Seat Type: <strong style="color: #2ecc71;"><?php echo $required_seat_type; ?></strong> • 
                Seats Needed: <strong><?php echo $original_seat_count; ?></strong>
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
                    <i class="fas fa-ticket-alt"></i> View My Bookings
                </a>
                <a href="<?php echo SITE_URL; ?>index.php?page=customer/receipt&id=<?php echo $new_booking_id ?? 0; ?>" class="btn btn-secondary" style="padding: 12px 30px; margin-left: 10px;">
                    <i class="fas fa-print"></i> View Receipt
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
                 alt="<?php echo htmlspecialchars($booking['title']); ?>"
                 style="width: 120px; height: 160px; object-fit: cover; border-radius: 10px; flex-shrink: 0;">
            <?php else: ?>
            <div style="width: 120px; height: 160px; background: linear-gradient(135deg, rgba(226, 48, 32, 0.1), rgba(193, 27, 24, 0.2)); 
                 border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                <i class="fas fa-film" style="font-size: 3rem; color: rgba(255, 255, 255, 0.3);"></i>
            </div>
            <?php endif; ?>
            
            <div style="flex: 1;">
                <h2 style="color: white; font-size: 1.8rem; margin-bottom: 10px; font-weight: 700;">
                    <?php echo htmlspecialchars($booking['title']); ?>
                </h2>
                
                <div style="display: flex; flex-wrap: wrap; gap: 15px; margin-bottom: 15px;">
                    <span style="background: var(--primary-red); color: white; padding: 4px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 600;">
                        <i class="fas fa-star"></i> <?php echo $booking['rating'] ?: 'PG'; ?>
                    </span>
                    <span style="background: rgba(255,255,255,0.1); color: var(--pale-red); padding: 4px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 600;">
                        <i class="fas fa-clock"></i> <?php echo $booking['duration']; ?>
                    </span>
                    <span style="background: rgba(255,255,255,0.1); color: var(--pale-red); padding: 4px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 600;">
                        <i class="fas fa-tag"></i> <?php echo htmlspecialchars($booking['genre']); ?>
                    </span>
                    <span style="background: #2ecc71; color: white; padding: 4px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 600;">
                        <i class="fas fa-chair"></i> <?php echo $required_seat_type; ?>
                    </span>
                </div>
                
                <div style="background: rgba(52, 152, 219, 0.1); padding: 15px; border-radius: 8px; border-left: 4px solid #3498db;">
                    <p style="color: white; margin-bottom: 5px;">
                        <i class="fas fa-info-circle" style="color: #3498db;"></i> 
                        You can only select <strong><?php echo $required_seat_type; ?></strong> seats to match your original payment.
                    </p>
                    <p style="color: var(--pale-red); font-size: 0.9rem;">
                        Original payment: <strong>₱<?php echo number_format($booking['booking_fee'], 2); ?></strong> for <strong><?php echo $original_seat_count; ?> seat(s)</strong>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div style="background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%); 
         border-radius: 15px; padding: 25px; margin-bottom: 30px; 
         border: 1px solid rgba(226, 48, 32, 0.3);">
        <h3 style="color: white; font-size: 1.4rem; margin-bottom: 20px; font-weight: 700;">
            <i class="fas fa-calendar-alt"></i> Select New Showtime
        </h3>
        
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
                    
                    $type_specific_seats = $type_counts[$schedule['id']] ?? 0;
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
                            <i class="fas fa-chair"></i> <?php echo $type_specific_seats; ?> <?php echo $required_seat_type; ?> seats available
                        </div>
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
                    Choose <strong><?php echo $original_seat_count; ?> seat(s)</strong> - Only <?php echo $required_seat_type; ?> seats are available
                </p>
            </div>
            <div style="color: white; font-weight: 700; font-size: 1.1rem; background: rgba(255,255,255,0.1); 
                 padding: 10px 20px; border-radius: 10px;">
                <span id="selectedCount">0</span>/<?php echo $original_seat_count; ?> selected
            </div>
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
                            $is_available = $seat['is_available'] == 1;
                            $is_selected = in_array($seat['seat_number'], $selected_seats);
                            $seat_color = $is_selected ? '#28a745' : ($is_available ? '#3498db' : '#6c757d');
                        ?>
                        <div style="text-align: center;">
                            <div style="color: rgba(255,255,255,0.5); font-size: 0.7rem; margin-bottom: 3px;"><?php echo $col; ?></div>
                            <label style="cursor: <?php echo $is_available ? 'pointer' : 'not-allowed'; ?>; opacity: <?php echo $is_available ? '1' : '0.5'; ?>;">
                                <input type="checkbox" name="selected_seats[]" value="<?php echo $seat['seat_number']; ?>" 
                                       <?php echo $is_selected ? 'checked' : ''; ?> 
                                       <?php echo !$is_available ? 'disabled' : ''; ?>
                                       class="seat-checkbox" style="display: none;"
                                       data-price="<?php echo $seat['price']; ?>">
                                <div style="width: 40px; height: 45px; background: <?php echo $seat_color; ?>; 
                                     border-radius: 8px 8px 4px 4px; display: flex; flex-direction: column; 
                                     align-items: center; justify-content: center; color: white; font-weight: 700;
                                     transition: all 0.3s ease; box-shadow: 0 3px 6px rgba(0,0,0,0.2);">
                                    <div style="font-size: 0.8rem;"><?php echo $row_letter; ?></div>
                                    <div style="font-size: 1rem;"><?php echo str_pad($col, 2, '0', STR_PAD_LEFT); ?></div>
                                </div>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div style="text-align: center; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.1);">
                <button type="submit" name="confirm_rebooking" class="btn btn-success" style="padding: 15px 50px; font-size: 1.1rem;">
                    <i class="fas fa-redo-alt"></i> Confirm Rebooking
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
    const maxSeats = <?php echo $original_seat_count; ?>;
    
    function updateSelectedCount() {
        const selected = Array.from(seatCheckboxes).filter(cb => cb.checked).length;
        if (selectedCount) {
            selectedCount.textContent = selected;
            
            if (selected > maxSeats) {
                selectedCount.style.color = '#ff6b6b';
            } else {
                selectedCount.style.color = 'white';
            }
        }
    }
    
    seatCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const selected = Array.from(seatCheckboxes).filter(cb => cb.checked);
            
            if (selected.length > maxSeats) {
                this.checked = false;
                alert('You can only select up to ' + maxSeats + ' seat(s).');
            } else {
                const seatDiv = this.parentElement.querySelector('div');
                if (this.checked) {
                    seatDiv.style.background = '#28a745';
                } else {
                    seatDiv.style.background = '#3498db';
                }
            }
            
            updateSelectedCount();
        });
    });
    
    const rebookForm = document.getElementById('rebookForm');
    if (rebookForm) {
        rebookForm.addEventListener('submit', function(e) {
            const selected = Array.from(seatCheckboxes).filter(cb => cb.checked);
            
            if (selected.length === 0) {
                e.preventDefault();
                alert('Please select at least one seat!');
                return false;
            }
            
            if (selected.length !== maxSeats) {
                e.preventDefault();
                alert('You must select exactly ' + maxSeats + ' seat(s).');
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