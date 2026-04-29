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
$selected_seats = [];
$pending_booking = null;

// Helper function to generate unique booking reference
function generate_booking_reference() {
    return 'BK' . date('ymd') . strtoupper(substr(uniqid(), -6)) . rand(10, 99);
}

// ============================================
// AUTO-CANCEL EXPIRED PENDING BOOKINGS
// ============================================
$auto_cancel_stmt = $conn->prepare("
    SELECT b.id, b.schedule_id, bs.seat_availability_id
    FROM bookings b
    JOIN booked_seats bs ON b.id = bs.booking_id
    WHERE b.user_id = ? AND b.payment_status = 'pending' AND b.status = 'ongoing'
    AND TIMESTAMPDIFF(HOUR, b.booked_at, NOW()) >= 3
");
$auto_cancel_stmt->bind_param("i", $user_id);
$auto_cancel_stmt->execute();
$auto_cancel_result = $auto_cancel_stmt->get_result();

if ($auto_cancel_result->num_rows > 0) {
    $conn->begin_transaction();
    
    try {
        while ($expired = $auto_cancel_result->fetch_assoc()) {
            $booking_id = $expired['id'];
            $schedule_id = $expired['schedule_id'];
            $seat_avail_id = $expired['seat_availability_id'];
            
            // Update booking status
            $cancel_stmt = $conn->prepare("UPDATE bookings SET status = 'cancelled', payment_status = 'refunded' WHERE id = ?");
            $cancel_stmt->bind_param("i", $booking_id);
            $cancel_stmt->execute();
            $cancel_stmt->close();
            
            // Release seat back to availability
            if ($seat_avail_id) {
                $release_stmt = $conn->prepare("
                    UPDATE seat_availability 
                    SET status = 'available', locked_by = NULL, locked_at = NULL
                    WHERE id = ?
                ");
                $release_stmt->bind_param("i", $seat_avail_id);
                $release_stmt->execute();
                $release_stmt->close();
            }
        }
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
    }
}
$auto_cancel_stmt->close();

// ============================================
// CHECK FOR PENDING BOOKING
// ============================================
$pending_stmt = $conn->prepare("
    SELECT b.*, m.title as movie_title
    FROM bookings b
    JOIN schedules s ON b.schedule_id = s.id
    JOIN movies m ON s.movie_id = m.id
    WHERE b.user_id = ? AND b.payment_status = 'pending' AND b.status = 'ongoing'
    AND TIMESTAMPDIFF(HOUR, b.booked_at, NOW()) < 3
    ORDER BY b.booked_at DESC LIMIT 1
");
$pending_stmt->bind_param("i", $user_id);
$pending_stmt->execute();
$pending_result = $pending_stmt->get_result();
if ($pending_result->num_rows > 0) {
    $pending_booking = $pending_result->fetch_assoc();
}
$pending_stmt->close();

// ============================================
// PROCESS BOOKING CONFIRMATION
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_booking'])) {
    $schedule_id = isset($_POST['schedule_id']) ? intval($_POST['schedule_id']) : 0;
    
    // FIX: Handle selected_seats properly - it could be string or array
    if (isset($_POST['selected_seats'])) {
        if (is_array($_POST['selected_seats'])) {
            $selected_seats = $_POST['selected_seats'];
        } elseif (is_string($_POST['selected_seats'])) {
            // Try to decode JSON if it's a JSON string
            $decoded = json_decode($_POST['selected_seats'], true);
            if (is_array($decoded)) {
                $selected_seats = $decoded;
            } else {
                $selected_seats = [];
            }
        }
    }
    
    if ($schedule_id <= 0) {
        $error = "Please select a showtime!";
    } elseif (empty($selected_seats)) {
        $error = "Please select at least one seat!";
    } else {
        // Get schedule details and calculate available seats from seat_availability
        $schedule_stmt = $conn->prepare("
            SELECT 
                s.id,
                s.movie_id,
                s.show_date,
                s.showtime,
                s.base_price,
                m.title as movie_title,
                COUNT(sa.id) as total_seats,
                COUNT(CASE WHEN sa.status = 'available' THEN 1 END) as available_seats
            FROM schedules s
            JOIN movies m ON s.movie_id = m.id
            LEFT JOIN seat_availability sa ON s.id = sa.schedule_id
            WHERE s.id = ? AND s.is_active = 1
            GROUP BY s.id
        ");
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
                // Verify each selected seat is available
                $seat_check_failed = false;
                $unavailable_seats = [];
                $seat_details = [];
                
                foreach ($selected_seats as $seat_number) {
                    $seat_check = $conn->prepare("
                        SELECT sa.id as seat_availability_id, sa.seat_number, sa.price, st.name as seat_type
                        FROM seat_availability sa
                        JOIN seat_types st ON sa.seat_type_id = st.id
                        WHERE sa.schedule_id = ? AND sa.seat_number = ? AND sa.status = 'available'
                    ");
                    $seat_check->bind_param("is", $schedule_id, $seat_number);
                    $seat_check->execute();
                    $seat_result = $seat_check->get_result();
                    
                    if ($seat_result->num_rows === 0) {
                        $seat_check_failed = true;
                        $unavailable_seats[] = $seat_number;
                    } else {
                        $seat = $seat_result->fetch_assoc();
                        $seat_details[] = [
                            'seat_availability_id' => $seat['seat_availability_id'],
                            'seat_number' => $seat['seat_number'],
                            'seat_type' => $seat['seat_type'],
                            'price' => $seat['price']
                        ];
                    }
                    $seat_check->close();
                }
                
                if ($seat_check_failed) {
                    $error = "Seats " . implode(", ", $unavailable_seats) . " are no longer available!";
                } else {
                    $conn->begin_transaction();
                    
                    try {
                        $booking_reference = generate_booking_reference();
                        $total_amount = array_sum(array_column($seat_details, 'price'));
                        
                        $booking_stmt = $conn->prepare("
                            INSERT INTO bookings (booking_reference, user_id, schedule_id, total_amount, payment_status, attendance_status, status, booked_at)
                            VALUES (?, ?, ?, ?, 'pending', 'pending', 'ongoing', NOW())
                        ");
                        $booking_stmt->bind_param("siid", $booking_reference, $user_id, $schedule_id, $total_amount);
                        
                        if (!$booking_stmt->execute()) {
                            throw new Exception("Failed to create booking: " . $booking_stmt->error);
                        }
                        
                        $booking_id = $booking_stmt->insert_id;
                        $booking_stmt->close();
                        
                        $booked_seat_stmt = $conn->prepare("
                            INSERT INTO booked_seats (booking_id, seat_availability_id, seat_number, seat_type_id, price)
                            SELECT ?, ?, ?, st.id, ?
                            FROM seat_types st
                            WHERE st.name = ?
                        ");
                        
                        foreach ($seat_details as $seat) {
                            $booked_seat_stmt->bind_param("iisds", $booking_id, $seat['seat_availability_id'], $seat['seat_number'], $seat['price'], $seat['seat_type']);
                            if (!$booked_seat_stmt->execute()) {
                                throw new Exception("Failed to book seat: " . $seat['seat_number']);
                            }
                        }
                        $booked_seat_stmt->close();
                        
                        // Update seat availability status
                        foreach ($seat_details as $seat) {
                            $update_seat_stmt = $conn->prepare("
                                UPDATE seat_availability SET status = 'booked', locked_by = NULL, locked_at = NULL WHERE id = ?
                            ");
                            $update_seat_stmt->bind_param("i", $seat['seat_availability_id']);
                            $update_seat_stmt->execute();
                            $update_seat_stmt->close();
                        }
                        
                        $conn->commit();
                        
                        $success = "Booking confirmed! Reference: <strong>$booking_reference</strong>";
                        $selected_seats = [];
                        
                        // Refresh pending booking
                        $pending_booking = [
                            'id' => $booking_id,
                            'booking_reference' => $booking_reference,
                            'total_amount' => $total_amount,
                            'movie_title' => $schedule['movie_title']
                        ];
                        
                    } catch (Exception $e) {
                        $conn->rollback();
                        $error = "Booking failed: " . $e->getMessage();
                    }
                }
            }
        }
    }
}

// ============================================
// HANDLE MOVIE SELECTION
// ============================================
$selected_movie_id = isset($_GET['movie']) ? intval($_GET['movie']) : 0;
$selected_schedule_id = isset($_GET['schedule']) ? intval($_GET['schedule']) : 0;

// If schedule is selected via POST, prioritize that
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['schedule_id']) && !isset($_POST['confirm_booking'])) {
    $selected_schedule_id = intval($_POST['schedule_id']);
}

// ============================================
// GET MOVIE LIST
// ============================================
$movies_stmt = $conn->prepare("
    SELECT id, title, rating, duration, genre, poster_url
    FROM movies 
    WHERE is_active = 1 
    ORDER BY title
");
$movies_stmt->execute();
$movies_result = $movies_stmt->get_result();
$all_movies = [];
$current_movie = null;

while ($row = $movies_result->fetch_assoc()) {
    $all_movies[] = $row;
    if ($row['id'] == $selected_movie_id) {
        $current_movie = $row;
    }
}
$movies_stmt->close();

// If no movie selected, pick the first one
if (!$current_movie && !empty($all_movies)) {
    $current_movie = $all_movies[0];
    $selected_movie_id = $current_movie['id'];
}

// Get full movie details
$movie = null;
if ($selected_movie_id > 0) {
    $movie_details_stmt = $conn->prepare("SELECT * FROM movies WHERE id = ? AND is_active = 1");
    $movie_details_stmt->bind_param("i", $selected_movie_id);
    $movie_details_stmt->execute();
    $movie_details_result = $movie_details_stmt->get_result();
    $movie = $movie_details_result->fetch_assoc();
    $movie_details_stmt->close();
}

// ============================================
// GET AVAILABLE SCHEDULES WITH AVAILABLE SEATS CALCULATED
// ============================================
$schedules = [];
if ($selected_movie_id > 0) {
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
            v.google_maps_link,
            sp.id as seat_plan_id,
            sp.plan_name,
            COUNT(sa.id) as total_seats,
            COUNT(CASE WHEN sa.status = 'available' THEN 1 END) as available_seats
        FROM schedules s
        JOIN screens sc ON s.screen_id = sc.id
        JOIN venues v ON sc.venue_id = v.id
        JOIN seat_plans sp ON s.seat_plan_id = sp.id
        LEFT JOIN seat_availability sa ON s.id = sa.schedule_id
        WHERE s.movie_id = ? AND s.is_active = 1 AND s.show_date >= CURDATE()
        GROUP BY s.id
        HAVING available_seats > 0
        ORDER BY s.show_date, s.showtime
    ");
    $schedules_stmt->bind_param("i", $selected_movie_id);
    $schedules_stmt->execute();
    $schedules_result = $schedules_stmt->get_result();
    
    while ($row = $schedules_result->fetch_assoc()) {
        $schedules[] = $row;
    }
    $schedules_stmt->close();
}

// ============================================
// GET SELECTED SCHEDULE AND SEATS (WITH LAYOUT FROM ADMIN)
// ============================================
$seats = [];
$available_seats = 0;
$selected_schedule_data = null;
$seat_layout = [];

if ($selected_schedule_id > 0) {
    // Get schedule details with venue, screen, and seat plan info
    $schedule_info_stmt = $conn->prepare("
        SELECT 
            s.id,
            s.show_date,
            s.showtime,
            s.base_price,
            sc.id as screen_id,
            sc.screen_name,
            sc.screen_number,
            sc.capacity,
            v.id as venue_id,
            v.venue_name,
            v.venue_location,
            v.google_maps_link,
            v.venue_photo_path,
            v.operating_hours,
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
        
        // Get available seat count
        $seat_count_stmt = $conn->prepare("
            SELECT COUNT(*) as available
            FROM seat_availability 
            WHERE schedule_id = ? AND status = 'available'
        ");
        $seat_count_stmt->bind_param("i", $selected_schedule_id);
        $seat_count_stmt->execute();
        $seat_count_result = $seat_count_stmt->get_result();
        $available_seats = $seat_count_result->fetch_assoc()['available'];
        $seat_count_stmt->close();
        
        // Get all seats with their details for layout
        $seats_stmt = $conn->prepare("
            SELECT 
                sa.id as seat_availability_id,
                sa.seat_number,
                sa.status,
                sa.price,
                st.id as seat_type_id,
                st.name as seat_type,
                st.color_code,
                spd.seat_row,
                spd.seat_column
            FROM seat_availability sa
            JOIN seat_plan_details spd ON sa.seat_plan_detail_id = spd.id
            JOIN seat_types st ON sa.seat_type_id = st.id
            WHERE sa.schedule_id = ?
            ORDER BY spd.seat_row, spd.seat_column
        ");
        $seats_stmt->bind_param("i", $selected_schedule_id);
        $seats_stmt->execute();
        $seats_result = $seats_stmt->get_result();
        
        // Organize seats by row and column for proper layout
        $seat_layout = [];
        while ($row = $seats_result->fetch_assoc()) {
            $row_letter = $row['seat_row'];
            $col = $row['seat_column'];
            if (!isset($seat_layout[$row_letter])) {
                $seat_layout[$row_letter] = [];
            }
            $seat_layout[$row_letter][$col] = $row;
        }
        $seats_stmt->close();
        
        // Get aisles for this seat plan
        $aisles_stmt = $conn->prepare("
            SELECT a.position_value, a.position_type, a.width, at.name as aisle_type
            FROM aisles a
            JOIN aisle_types at ON a.aisle_type_id = at.id
            WHERE a.seat_plan_id = ? AND at.is_active = 1
            ORDER BY a.position_type, a.position_value
        ");
        $aisles_stmt->bind_param("i", $selected_schedule_data['seat_plan_id']);
        $aisles_stmt->execute();
        $aisles_result = $aisles_stmt->get_result();
        $aisles = [];
        while ($row = $aisles_result->fetch_assoc()) {
            $aisles[] = $row;
        }
        $aisles_stmt->close();
        
        $selected_schedule_data['aisles'] = $aisles;
    }
    $schedule_info_stmt->close();
}

$conn->close();
require_once $root_dir . '/partials/header.php';
?>

<style>
/* ============================================
   BOOKING PAGE STYLES
   ============================================ */
.booking-step {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 15px 20px;
    background: rgba(255,255,255,0.05);
    border-radius: 12px;
    margin-bottom: 20px;
}

.step-number {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, var(--primary-red) 0%, var(--dark-red) 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 1.2rem;
    color: white;
    flex-shrink: 0;
}

.step-title {
    color: white;
    font-weight: 600;
    font-size: 1rem;
}

.step-desc {
    color: var(--pale-red);
    font-size: 0.8rem;
}

.schedule-card {
    transition: all 0.3s ease;
    cursor: pointer;
}

.schedule-card:hover {
    transform: translateY(-3px);
}

.schedule-card.selected {
    background: rgba(226,48,32,0.2) !important;
    border-color: var(--primary-red) !important;
}

/* Remove X button style */
.remove-schedule-btn {
    background: rgba(231, 76, 60, 0.8);
    border: none;
    color: white;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    cursor: pointer;
    font-size: 0.8rem;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.remove-schedule-btn:hover {
    background: #e74c3c;
    transform: scale(1.1);
}

/* Seat Grid Styles */
.seat-grid-container {
    overflow-x: auto;
    margin-bottom: 30px;
}

.seat-grid {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    min-width: 500px;
}

.seat-row {
    display: flex;
    align-items: center;
    gap: 12px;
    justify-content: center;
}

.seat-row-label {
    width: 40px;
    text-align: center;
    font-weight: 800;
    color: #9b59b6;
    font-size: 0.9rem;
}

.seat-cells {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    justify-content: center;
}

.seat-cell {
    width: 55px;
    height: 55px;
    border-radius: 10px 10px 6px 6px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    transition: all 0.15s ease;
    cursor: pointer;
    position: relative;
}

.seat-cell.standard {
    background: #3498db;
    color: white;
}

.seat-cell.premium {
    background: #FFD700;
    color: #333;
}

.seat-cell.sweet-spot {
    background: #e74c3c;
    color: white;
}

.seat-cell.selected {
    background: #28a745 !important;
    color: white !important;
    border: 3px solid #f39c12 !important;
    transform: scale(1.02);
}

.seat-cell.booked {
    background: #6c757d !important;
    color: rgba(255,255,255,0.5) !important;
    cursor: not-allowed;
    opacity: 0.7;
}

.seat-cell.aisle-gap {
    background: #2c3e50 !important;
    border: 2px dashed #e74c3c !important;
    cursor: default;
    color: rgba(255,255,255,0.5);
}

.seat-cell.aisle-gap:hover {
    transform: none;
}

.seat-cell:hover:not(.booked):not(.aisle-gap) {
    transform: scale(1.05);
    filter: brightness(1.05);
}

.seat-number {
    font-size: 0.7rem;
}

.seat-price {
    font-size: 0.6rem;
    margin-top: 2px;
}

/* Right Sidebar - Selection Summary */
.selection-sidebar {
    position: fixed;
    right: 20px;
    top: 100px;
    width: 280px;
    z-index: 1000;
}

.selection-sidebar .summary-card {
    background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    border: 1px solid rgba(226,48,32,0.3);
}

.summary-section {
    background: rgba(0,0,0,0.2);
    border-radius: 10px;
    padding: 12px;
    margin-bottom: 12px;
}

.summary-label {
    color: var(--pale-red);
    font-size: 0.7rem;
    margin-bottom: 5px;
}

.summary-value {
    color: white;
    font-size: 0.9rem;
    font-weight: 600;
    word-break: break-word;
}

.selected-seat-badge {
    display: inline-block;
    background: rgba(40,167,69,0.2);
    color: #28a745;
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
    margin: 2px;
}

/* Buttons */
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
    font-size: 0.9rem;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary-red) 0%, var(--dark-red) 100%);
    color: white;
    box-shadow: 0 4px 15px rgba(226, 48, 32, 0.3);
}

.btn-primary:hover {
    background: linear-gradient(135deg, var(--dark-red) 0%, var(--deep-red) 100%);
    transform: translateY(-2px);
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
    transform: translateY(-2px);
}

.btn-success {
    background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
    color: white;
}

.btn-danger {
    background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
    color: white;
}

/* Form Controls */
.form-control {
    background: rgba(255,255,255,0.08);
    border: 2px solid rgba(226,48,32,0.3);
    border-radius: 10px;
    color: white;
    padding: 12px 15px;
    width: 100%;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary-red);
    box-shadow: 0 0 0 3px rgba(226,48,32,0.2);
}

.form-control option {
    background: #2c3e50;
    color: white;
}

/* Alert Messages */
.alert {
    padding: 15px 20px;
    border-radius: 10px;
    margin-bottom: 25px;
    font-weight: 600;
    animation: fadeIn 0.5s ease;
}

.alert-danger {
    background: rgba(226, 48, 32, 0.2);
    color: #ff9999;
    border: 1px solid rgba(226, 48, 32, 0.3);
}

.alert-success {
    background: rgba(46, 204, 113, 0.2);
    color: #2ecc71;
    border: 1px solid rgba(46, 204, 113, 0.3);
}

.alert-warning {
    background: rgba(241, 196, 15, 0.2);
    border: 2px solid #f1c40f;
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 30px;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-20px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Responsive */
@media (max-width: 1200px) {
    .selection-sidebar {
        display: none;
    }
}

@media (max-width: 768px) {
    .seat-cell {
        width: 45px !important;
        height: 45px !important;
    }
    
    .seat-cell .seat-number {
        font-size: 0.6rem;
    }
    
    .seat-cell .seat-price {
        font-size: 0.5rem;
    }
    
    .seat-row-label {
        width: 30px;
        font-size: 0.7rem;
    }
}

@media (max-width: 576px) {
    .seat-cell {
        width: 38px !important;
        height: 45px !important;
    }
}

:root {
    --primary-red: #e23020;
    --dark-red: #c11b18;
    --deep-red: #a80f0f;
    --light-red: #ff6b6b;
    --pale-red: #ff9999;
    --bg-card: #3a0b07;
    --bg-card-light: #6b140e;
}
</style>

<div class="booking-container" style="max-width: 1400px; margin: 0 auto; padding: 20px;">
    
    <!-- Pending Payment Alert -->
    <?php if ($pending_booking): ?>
    <div class="alert alert-warning">
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px;">
            <div style="display: flex; align-items: center; gap: 15px;">
                <i class="fas fa-clock" style="font-size: 2rem; color: #f1c40f;"></i>
                <div>
                    <h3 style="color: white; margin-bottom: 5px;">Pending Payment</h3>
                    <p style="color: var(--pale-red);">You have a pending booking: <strong><?php echo htmlspecialchars($pending_booking['movie_title']); ?></strong></p>
                    <p style="color: #f1c40f; font-size: 0.85rem;">Complete payment within 3 hours or booking will be cancelled.</p>
                </div>
            </div>
            <div style="display: flex; gap: 10px;">
                <a href="<?php echo SITE_URL; ?>index.php?page=customer/payment&booking_id=<?php echo $pending_booking['id']; ?>" class="btn btn-primary">
                    <i class="fas fa-credit-card"></i> Pay Now
                </a>
                <a href="<?php echo SITE_URL; ?>index.php?page=customer/my-bookings" class="btn btn-secondary">
                    <i class="fas fa-ticket-alt"></i> My Bookings
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Step 1: Select Movie -->
    <div class="booking-step">
        <div class="step-number">1</div>
        <div>
            <div class="step-title">Select Movie</div>
            <div class="step-desc">Choose the movie you want to watch</div>
        </div>
    </div>
    
    <div style="background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%); border-radius: 15px; padding: 25px; margin-bottom: 30px; border: 1px solid rgba(226,48,32,0.3);">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
            <div style="flex: 1; min-width: 280px;">
                <form method="GET" action="" id="movieSelectForm">
                    <div style="display: flex; gap: 10px;">
                        <select name="movie" id="movieSelect" class="form-control" style="flex: 1;">
                            <?php foreach ($all_movies as $movie_option): ?>
                            <option value="<?php echo $movie_option['id']; ?>" <?php echo $movie_option['id'] == $selected_movie_id ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($movie_option['title']); ?> (<?php echo $movie_option['rating']; ?> • <?php echo $movie_option['duration']; ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-sync-alt"></i> Load Movie
                        </button>
                    </div>
                </form>
            </div>
            <?php if ($movie): ?>
            <div style="display: flex; gap: 15px; align-items: center;">
                <?php if (!empty($movie['poster_url'])): ?>
                <img src="<?php echo $movie['poster_url']; ?>" alt="<?php echo htmlspecialchars($movie['title']); ?>" style="width: 50px; height: 70px; object-fit: cover; border-radius: 8px;">
                <?php endif; ?>
                <div>
                    <div style="color: white; font-weight: 700; font-size: 1.1rem;"><?php echo htmlspecialchars($movie['title']); ?></div>
                    <div style="color: var(--pale-red); font-size: 0.8rem;">
                        <i class="fas fa-star"></i> <?php echo $movie['rating']; ?> • 
                        <i class="fas fa-clock"></i> <?php echo $movie['duration']; ?> • 
                        <i class="fas fa-tag"></i> <?php echo htmlspecialchars($movie['genre']); ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Step 2: Select Showtime -->
    <?php if ($movie): ?>
    <div class="booking-step">
        <div class="step-number">2</div>
        <div>
            <div class="step-title">Select Showtime</div>
            <div class="step-desc">Choose your preferred date, time, venue, and screen</div>
        </div>
    </div>
    
    <div style="background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%); border-radius: 15px; padding: 25px; margin-bottom: 30px; border: 1px solid rgba(226,48,32,0.3);">
        <?php if (empty($schedules)): ?>
        <div style="text-align: center; padding: 40px; color: var(--pale-red);">
            <i class="fas fa-calendar-times fa-2x" style="margin-bottom: 15px;"></i>
            <p>No available showtimes for this movie.</p>
        </div>
        <?php else: ?>
        <form method="POST" action="" id="scheduleForm">
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 15px;">
                <?php foreach ($schedules as $schedule): 
                    $is_selected = $selected_schedule_id == $schedule['id'];
                    $is_today = date('Y-m-d') == $schedule['show_date'];
                    $is_tomorrow = date('Y-m-d', strtotime('+1 day')) == $schedule['show_date'];
                    $available_percent = ($schedule['available_seats'] / $schedule['total_seats']) * 100;
                ?>
                <div class="schedule-card" style="position: relative;">
                    <input type="radio" name="schedule_id" value="<?php echo $schedule['id']; ?>" 
                           <?php echo $is_selected ? 'checked' : ''; ?> 
                           class="schedule-radio" style="display: none;">
                    <div style="background: <?php echo $is_selected ? 'rgba(226,48,32,0.15)' : 'rgba(255,255,255,0.05)'; ?>; 
                         border: 2px solid <?php echo $is_selected ? 'var(--primary-red)' : 'rgba(226,48,32,0.3)'; ?>; 
                         border-radius: 12px; padding: 18px; transition: all 0.3s ease;">
                        
                        <!-- Date & Time Header -->
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                            <div>
                                <span style="font-size: 1.3rem; font-weight: 800; color: white;">
                                    <?php echo date('h:i A', strtotime($schedule['showtime'])); ?>
                                </span>
                                <?php if ($is_today): ?>
                                <span style="background: var(--primary-red); color: white; padding: 3px 10px; border-radius: 20px; font-size: 0.7rem; margin-left: 10px;">TODAY</span>
                                <?php elseif ($is_tomorrow): ?>
                                <span style="background: #3498db; color: white; padding: 3px 10px; border-radius: 20px; font-size: 0.7rem; margin-left: 10px;">TOMORROW</span>
                                <?php endif; ?>
                            </div>
                            <?php if ($is_selected): ?>
                            <button type="button" class="remove-schedule-btn" onclick="clearSelectedSchedule()" title="Remove selected showtime">
                                <i class="fas fa-times"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Date -->
                        <div style="color: var(--pale-red); font-size: 0.85rem; margin-bottom: 12px;">
                            <i class="far fa-calendar"></i> <?php echo date('l, F d, Y', strtotime($schedule['show_date'])); ?>
                        </div>
                        
                        <!-- Venue & Screen -->
                        <div style="display: flex; gap: 12px; margin-bottom: 15px; flex-wrap: wrap;">
                            <div style="display: inline-flex; align-items: center; gap: 6px; background: rgba(52,152,219,0.2); color: #3498db; padding: 4px 10px; border-radius: 15px; font-size: 0.7rem; font-weight: 600;">
                                <i class="fas fa-building"></i> <?php echo htmlspecialchars($schedule['venue_name']); ?>
                            </div>
                            <div style="display: inline-flex; align-items: center; gap: 6px; background: rgba(46,204,113,0.2); color: #2ecc71; padding: 4px 10px; border-radius: 15px; font-size: 0.7rem; font-weight: 600;">
                                <i class="fas fa-tv"></i> <?php echo htmlspecialchars($schedule['screen_name']); ?> (Screen #<?php echo $schedule['screen_number']; ?>)
                            </div>
                        </div>
                        
                        <!-- Seat Availability Bar -->
                        <div style="margin-top: 10px;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                <span style="color: rgba(255,255,255,0.6); font-size: 0.75rem;">Available Seats</span>
                                <span style="color: <?php echo $schedule['available_seats'] < 10 ? '#ff6b6b' : '#2ecc71'; ?>; font-weight: 600; font-size: 0.8rem;">
                                    <?php echo $schedule['available_seats']; ?>/<?php echo $schedule['total_seats']; ?>
                                </span>
                            </div>
                            <div style="background: rgba(255,255,255,0.1); height: 6px; border-radius: 3px; overflow: hidden;">
                                <div style="background: <?php echo $available_percent > 50 ? '#2ecc71' : ($available_percent > 20 ? '#f39c12' : '#e74c3c'); ?>; 
                                     height: 100%; width: <?php echo $available_percent; ?>%;"></div>
                            </div>
                        </div>
                        
                        <!-- Price -->
                        <div style="margin-top: 12px; padding-top: 10px; border-top: 1px solid rgba(255,255,255,0.1);">
                            <span style="color: #2ecc71; font-size: 1rem; font-weight: 700;">
                                ₱<?php echo number_format($schedule['base_price'], 2); ?>
                            </span>
                            <span style="color: rgba(255,255,255,0.5); font-size: 0.7rem;"> per seat</span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div style="text-align: center; margin-top: 20px;">
                <button type="submit" class="btn btn-primary" id="selectScheduleBtn">
                    <i class="fas fa-check-circle"></i> Select This Showtime
                </button>
            </div>
        </form>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <!-- Step 3: Select Seats -->
    <?php if ($selected_schedule_id > 0 && !empty($seat_layout)): ?>
    <div class="booking-step">
        <div class="step-number">3</div>
        <div>
            <div class="step-title">Select Your Seats</div>
            <div class="step-desc">Click on available seats to select them</div>
        </div>
    </div>
    
    <div style="background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%); border-radius: 15px; padding: 25px; border: 1px solid rgba(226,48,32,0.3); margin-bottom: 30px;">
        
        <!-- Selected Schedule Summary with Remove Button -->
        <?php if ($selected_schedule_data): ?>
        <div style="background: rgba(0,0,0,0.3); border-radius: 12px; padding: 15px; margin-bottom: 25px;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 15px;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; flex: 1;">
                    <div>
                        <div style="color: var(--pale-red); font-size: 0.7rem;">Venue</div>
                        <div style="color: white; font-weight: 600;"><?php echo htmlspecialchars($selected_schedule_data['venue_name']); ?></div>
                    </div>
                    <div>
                        <div style="color: var(--pale-red); font-size: 0.7rem;">Screen</div>
                        <div style="color: white; font-weight: 600;"><?php echo htmlspecialchars($selected_schedule_data['screen_name']); ?> (#<?php echo $selected_schedule_data['screen_number']; ?>)</div>
                    </div>
                    <div>
                        <div style="color: var(--pale-red); font-size: 0.7rem;">Date & Time</div>
                        <div style="color: white; font-weight: 600;"><?php echo date('D, M d, Y', strtotime($selected_schedule_data['show_date'])); ?> at <?php echo date('h:i A', strtotime($selected_schedule_data['showtime'])); ?></div>
                    </div>
                    <?php if (!empty($selected_schedule_data['venue_location'])): ?>
                    <div>
                        <div style="color: var(--pale-red); font-size: 0.7rem;">Location</div>
                        <div style="color: white; font-size: 0.8rem;"><?php echo htmlspecialchars(substr($selected_schedule_data['venue_location'], 0, 60)); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
                <button type="button" class="btn btn-danger" id="clearScheduleBtn" style="padding: 8px 16px; font-size: 0.8rem;" title="Clear selected showtime and reset seats">
                    <i class="fas fa-times-circle"></i> Clear Showtime
                </button>
            </div>
            <?php if (!empty($selected_schedule_data['google_maps_link'])): ?>
            <div style="margin-top: 10px;">
                <a href="<?php echo $selected_schedule_data['google_maps_link']; ?>" target="_blank" style="color: #3498db; font-size: 0.8rem;">
                    <i class="fas fa-map-marked-alt"></i> View on Google Maps
                </a>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <?php if ($available_seats == 0): ?>
        <div style="text-align: center; padding: 50px; color: var(--pale-red);">
            <i class="fas fa-times-circle fa-3x" style="margin-bottom: 15px; color: #e74c3c;"></i>
            <h3 style="color: white;">No Seats Available</h3>
            <p>This showtime is fully booked. Please select another showtime.</p>
        </div>
        <?php else: ?>
        
        <!-- Seat Legend -->
        <div style="display: flex; gap: 20px; justify-content: center; flex-wrap: wrap; margin-bottom: 25px; padding: 15px; background: rgba(0,0,0,0.2); border-radius: 10px;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <div style="width: 25px; height: 25px; background: #3498db; border-radius: 6px;"></div>
                <span style="color: white; font-size: 0.8rem;">Standard</span>
            </div>
            <div style="display: flex; align-items: center; gap: 8px;">
                <div style="width: 25px; height: 25px; background: #FFD700; border-radius: 6px;"></div>
                <span style="color: white; font-size: 0.8rem;">Premium</span>
            </div>
            <div style="display: flex; align-items: center; gap: 8px;">
                <div style="width: 25px; height: 25px; background: #e74c3c; border-radius: 6px;"></div>
                <span style="color: white; font-size: 0.8rem;">Sweet Spot</span>
            </div>
            <div style="display: flex; align-items: center; gap: 8px;">
                <div style="width: 25px; height: 25px; background: #28a745; border-radius: 6px; border: 2px solid #f39c12;"></div>
                <span style="color: white; font-size: 0.8rem;">Selected</span>
            </div>
            <div style="display: flex; align-items: center; gap: 8px;">
                <div style="width: 25px; height: 25px; background: #6c757d; border-radius: 6px;"></div>
                <span style="color: white; font-size: 0.8rem;">Booked</span>
            </div>
            <div style="display: flex; align-items: center; gap: 8px;">
                <div style="width: 25px; height: 25px; background: #2c3e50; border: 2px dashed #e74c3c; border-radius: 6px;"></div>
                <span style="color: white; font-size: 0.8rem;">Aisle / Gap</span>
            </div>
        </div>
        
        <!-- Screen Visual -->
        <div style="text-align: center; margin-bottom: 35px;">
            <div style="background: linear-gradient(135deg, #3498db, #2980b9); display: inline-block; padding: 12px 50px; border-radius: 10px; color: white; font-weight: 700; letter-spacing: 2px;">
                <i class="fas fa-tv"></i> S C R E E N
            </div>
            <div style="height: 4px; background: linear-gradient(to right, #3498db, #2ecc71, #3498db); width: 70%; margin: 10px auto 0; border-radius: 2px;"></div>
        </div>
        
        <!-- Seat Grid - Rendered from Database -->
        <div class="seat-grid-container">
            <div class="seat-grid" id="seatGrid">
                <?php 
                // Sort rows alphabetically
                ksort($seat_layout);
                $aisles = $selected_schedule_data['aisles'] ?? [];
                $row_aisles = [];
                $col_aisles = [];
                
                foreach ($aisles as $aisle) {
                    if ($aisle['position_type'] == 'row') {
                        $row_aisles[] = $aisle['position_value'];
                    } else {
                        $col_aisles[] = $aisle['position_value'];
                    }
                }
                
                $row_counter = 0;
                foreach ($seat_layout as $row_letter => $columns):
                    $row_counter++;
                    // Check if there should be a row aisle BEFORE this row
                    if (in_array($row_counter - 1, $row_aisles) && $row_counter > 1):
                ?>
                <div class="seat-row aisle-row" style="justify-content: center; margin: 5px 0;">
                    <div class="seat-cell aisle-gap" style="width: 100%; text-align: center; background: #2c3e50; border: 2px dashed #e74c3c; padding: 5px;">
                        <i class="fas fa-grip-vertical"></i> AISLE
                    </div>
                </div>
                <?php 
                    endif;
                    ksort($columns);
                ?>
                <div class="seat-row">
                    <div class="seat-row-label"><?php echo $row_letter; ?></div>
                    <div class="seat-cells">
                        <?php 
                        $col_counter = 0;
                        foreach ($columns as $col => $seat):
                            $col_counter++;
                            // Check if there should be a column aisle BEFORE this column
                            if (in_array($col_counter - 1, $col_aisles) && $col_counter > 1):
                        ?>
                        <div class="seat-cell aisle-gap" style="width: 55px; height: 55px; background: #2c3e50; border: 2px dashed #e74c3c;">
                            <div class="seat-number">GAP</div>
                        </div>
                        <?php 
                            endif;
                            $is_available = $seat['status'] == 'available';
                            $seat_type_class = strtolower(str_replace(' ', '-', $seat['seat_type']));
                            $price = $seat['price'];
                        ?>
                        <div class="seat-cell <?php echo $seat_type_class; ?> <?php echo !$is_available ? 'booked' : ''; ?>" 
                             data-seat-number="<?php echo $seat['seat_number']; ?>"
                             data-seat-type="<?php echo $seat_type_class; ?>"
                             data-price="<?php echo $price; ?>"
                             data-availability-id="<?php echo $seat['seat_availability_id']; ?>"
                             data-available="<?php echo $is_available ? 'true' : 'false'; ?>">
                            <div class="seat-number"><?php echo $row_letter . str_pad($col, 2, '0', STR_PAD_LEFT); ?></div>
                            <div class="seat-price">₱<?php echo number_format($price, 0); ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Action Buttons -->
        <div style="display: flex; justify-content: center; gap: 15px; margin-top: 30px; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.1);">
            <button type="button" id="clearSelectionsBtn" class="btn btn-secondary">
                <i class="fas fa-undo"></i> Clear All Selections
            </button>
            <button type="button" id="confirmBookingBtn" class="btn btn-primary" style="padding: 14px 40px; font-size: 1rem;">
                <i class="fas fa-ticket-alt"></i> Confirm Booking (₱<span id="confirmTotal">0</span>)
            </button>
        </div>
        
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <!-- Error/Success Messages -->
    <?php if ($error): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
    </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
    <div class="alert alert-success">
        <div style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 15px;">
            <div><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
            <div style="display: flex; gap: 10px;">
                <a href="<?php echo SITE_URL; ?>index.php?page=customer/payment&booking_id=<?php echo $pending_booking['id']; ?>" class="btn btn-primary">Pay Now</a>
                <a href="<?php echo SITE_URL; ?>index.php?page=customer/my-bookings" class="btn btn-secondary">My Bookings</a>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Hidden Form for Booking Submission -->
<form method="POST" action="" id="bookingForm" style="display: none;">
    <input type="hidden" name="schedule_id" id="bookingScheduleId">
    <input type="hidden" name="selected_seats" id="bookingSelectedSeats">
    <input type="hidden" name="confirm_booking" value="1">
</form>

<!-- Right Sidebar - Selection Summary -->
<?php if ($selected_schedule_id > 0): ?>
<div class="selection-sidebar">
    <div class="summary-card">
        <h4 style="color: white; margin-bottom: 15px; font-size: 1rem;"><i class="fas fa-receipt"></i> Your Selection</h4>
        
        <div class="summary-section">
            <div class="summary-label">Movie</div>
            <div class="summary-value" id="sidebarMovie"><?php echo htmlspecialchars($movie['title'] ?? ''); ?></div>
        </div>
        
        <?php if ($selected_schedule_data): ?>
        <div class="summary-section">
            <div class="summary-label">Showtime</div>
            <div class="summary-value" id="sidebarTime"><?php echo date('h:i A', strtotime($selected_schedule_data['showtime'])); ?></div>
            <div class="summary-label" style="margin-top: 5px;">Date</div>
            <div class="summary-value" id="sidebarDate"><?php echo date('M d, Y', strtotime($selected_schedule_data['show_date'])); ?></div>
        </div>
        
        <div class="summary-section">
            <div class="summary-label">Venue & Screen</div>
            <div class="summary-value" id="sidebarVenue"><?php echo htmlspecialchars($selected_schedule_data['venue_name']); ?></div>
            <div class="summary-value" style="font-size: 0.8rem;"><?php echo htmlspecialchars($selected_schedule_data['screen_name']); ?> (#<?php echo $selected_schedule_data['screen_number']; ?>)</div>
        </div>
        <?php endif; ?>
        
        <div class="summary-section">
            <div class="summary-label">Selected Seats (<span id="sidebarSeatCount">0</span>)</div>
            <div id="sidebarSeats" style="display: flex; flex-wrap: wrap; gap: 5px; min-height: 30px;">
                <span style="color: var(--pale-red); font-style: italic;">None selected</span>
            </div>
        </div>
        
        <div class="summary-section">
            <div class="summary-label">Total Amount</div>
            <div class="summary-value" style="color: #2ecc71; font-size: 1.2rem;">₱<span id="sidebarTotal">0</span>.00</div>
        </div>
        
        <div style="margin-top: 10px;">
            <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                <div style="background: #3498db; width: 20px; height: 20px; border-radius: 4px;"></div>
                <span style="color: white; font-size: 0.7rem;">Standard</span>
                <div style="background: #FFD700; width: 20px; height: 20px; border-radius: 4px; margin-left: 10px;"></div>
                <span style="color: white; font-size: 0.7rem;">Premium</span>
                <div style="background: #e74c3c; width: 20px; height: 20px; border-radius: 4px; margin-left: 10px;"></div>
                <span style="color: white; font-size: 0.7rem;">SS</span>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// ============================================
// GLOBAL VARIABLES
// ============================================
let selectedSeats = new Set();
let currentScheduleId = <?php echo $selected_schedule_id > 0 ? $selected_schedule_id : 0; ?>;
let seatPriceMap = new Map();

// ============================================
// UPDATE UI (Sidebar + Total)
// ============================================
function updateSeatSelection() {
    const sidebarSeatCount = document.getElementById('sidebarSeatCount');
    const sidebarSeats = document.getElementById('sidebarSeats');
    const sidebarTotal = document.getElementById('sidebarTotal');
    const confirmTotal = document.getElementById('confirmTotal');
    
    let total = 0;
    let selectedSeatsArray = [];
    
    selectedSeats.forEach(seatNumber => {
        const price = seatPriceMap.get(seatNumber) || 0;
        total += price;
        selectedSeatsArray.push(seatNumber);
    });
    
    if (sidebarSeatCount) sidebarSeatCount.textContent = selectedSeats.size;
    if (sidebarTotal) sidebarTotal.textContent = total.toFixed(2);
    if (confirmTotal) confirmTotal.textContent = total.toFixed(2);
    
    if (sidebarSeats) {
        if (selectedSeats.size > 0) {
            sidebarSeats.innerHTML = selectedSeatsArray.map(s => `<span class="selected-seat-badge">${s}</span>`).join('');
        } else {
            sidebarSeats.innerHTML = '<span style="color: var(--pale-red); font-style: italic;">None selected</span>';
        }
    }
    
    // Update hidden form
    const bookingSelectedSeats = document.getElementById('bookingSelectedSeats');
    if (bookingSelectedSeats) {
        bookingSelectedSeats.value = JSON.stringify(selectedSeatsArray);
    }
}

// ============================================
// HANDLE SEAT CLICK (IMMEDIATE RESPONSE)
// ============================================
function handleSeatClick(seatElement) {
    const isAvailable = seatElement.dataset.available === 'true';
    const seatNumber = seatElement.dataset.seatNumber;
    const price = parseFloat(seatElement.dataset.price);
    
    if (!isAvailable) {
        alert('This seat is already booked. Please select another seat.');
        return;
    }
    
    if (selectedSeats.has(seatNumber)) {
        // Unselect
        selectedSeats.delete(seatNumber);
        seatPriceMap.delete(seatNumber);
        seatElement.classList.remove('selected');
        // Restore original class
        const seatType = seatElement.dataset.seatType;
        seatElement.classList.remove('selected');
        seatElement.classList.add(seatType);
    } else {
        // Select
        selectedSeats.add(seatNumber);
        seatPriceMap.set(seatNumber, price);
        // Change to selected style immediately
        seatElement.classList.remove('standard', 'premium', 'sweet-spot');
        seatElement.classList.add('selected');
    }
    
    updateSeatSelection();
}

// ============================================
// CLEAR ALL SELECTIONS
// ============================================
function clearAllSelections() {
    if (selectedSeats.size === 0) {
        alert('No seats selected to clear');
        return;
    }
    
    if (confirm(`Clear ${selectedSeats.size} selected seat(s)?`)) {
        // Reset all seat elements
        document.querySelectorAll('.seat-cell').forEach(seat => {
            if (seat.classList.contains('selected')) {
                seat.classList.remove('selected');
                const seatType = seat.dataset.seatType;
                seat.classList.add(seatType);
            }
        });
        selectedSeats.clear();
        seatPriceMap.clear();
        updateSeatSelection();
    }
}

// ============================================
// CLEAR SCHEDULE AND RESET
// ============================================
function clearSelectedSchedule() {
    if (confirm('Are you sure you want to clear the selected showtime? Your seat selections will be lost.')) {
        // Create form to go back to movie selection with same movie
        const form = document.createElement('form');
        form.method = 'GET';
        form.action = window.location.pathname + '?page=customer/booking';
        
        const movieInput = document.createElement('input');
        movieInput.type = 'hidden';
        movieInput.name = 'movie';
        movieInput.value = '<?php echo $selected_movie_id; ?>';
        form.appendChild(movieInput);
        
        document.body.appendChild(form);
        form.submit();
    }
}

// ============================================
// CONFIRM BOOKING
// ============================================
function confirmBooking() {
    if (selectedSeats.size === 0) {
        alert('Please select at least one seat to continue.');
        return;
    }
    
    const scheduleIdInput = document.getElementById('bookingScheduleId');
    if (scheduleIdInput) scheduleIdInput.value = currentScheduleId;
    
    const bookingForm = document.getElementById('bookingForm');
    if (bookingForm) {
        bookingForm.submit();
    }
}

// ============================================
// SETUP SEAT CLICK HANDLERS
// ============================================
function setupSeatClickHandlers() {
    const seatCells = document.querySelectorAll('.seat-cell:not(.aisle-gap)');
    seatCells.forEach(seat => {
        const isAvailable = seat.dataset.available === 'true';
        if (isAvailable) {
            seat.style.cursor = 'pointer';
            // Remove any existing listener to avoid duplicates
            seat.removeEventListener('click', () => {});
            seat.addEventListener('click', () => handleSeatClick(seat));
        } else if (!seat.classList.contains('aisle-gap')) {
            seat.style.cursor = 'not-allowed';
        }
    });
}

// ============================================
// SETUP SCHEDULE CARDS
// ============================================
function setupScheduleCards() {
    const scheduleCards = document.querySelectorAll('.schedule-card');
    const selectBtn = document.getElementById('selectScheduleBtn');
    
    if (selectBtn) {
        // Remove existing listener to avoid duplicates
        const newSelectBtn = selectBtn.cloneNode(true);
        selectBtn.parentNode.replaceChild(newSelectBtn, selectBtn);
        
        newSelectBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const selectedRadio = document.querySelector('input[name="schedule_id"]:checked');
            if (selectedRadio) {
                document.getElementById('scheduleForm').submit();
            } else {
                alert('Please select a showtime first.');
            }
        });
    }
    
    // Make schedule cards clickable
    scheduleCards.forEach(card => {
        const radio = card.querySelector('.schedule-radio');
        card.style.cursor = 'pointer';
        
        // Remove existing listener
        const newCard = card.cloneNode(true);
        card.parentNode.replaceChild(newCard, card);
        
        newCard.addEventListener('click', function(e) {
            if (e.target.closest('.remove-schedule-btn')) return;
            
            const newRadio = this.querySelector('.schedule-radio');
            if (newRadio) {
                newRadio.checked = true;
                // Visual feedback
                document.querySelectorAll('.schedule-card').forEach(c => {
                    const div = c.querySelector('div');
                    if (div) {
                        div.style.background = 'rgba(255,255,255,0.05)';
                        div.style.borderColor = 'rgba(226,48,32,0.3)';
                    }
                });
                const div = this.querySelector('div');
                if (div) {
                    div.style.background = 'rgba(226,48,32,0.15)';
                    div.style.borderColor = 'var(--primary-red)';
                }
            }
        });
    });
}

// ============================================
// SETUP MOVIE SELECT
// ============================================
function setupMovieSelect() {
    const movieSelect = document.getElementById('movieSelect');
    if (movieSelect) {
        movieSelect.addEventListener('change', function() {
            document.getElementById('movieSelectForm').submit();
        });
    }
}

// ============================================
// SETUP CLEAR SCHEDULE BUTTON
// ============================================
function setupClearScheduleButton() {
    const clearBtn = document.getElementById('clearScheduleBtn');
    if (clearBtn) {
        // Remove existing listener
        const newClearBtn = clearBtn.cloneNode(true);
        clearBtn.parentNode.replaceChild(newClearBtn, clearBtn);
        newClearBtn.addEventListener('click', clearSelectedSchedule);
    }
}

// ============================================
// SETUP CLEAR SELECTIONS BUTTON
// ============================================
function setupClearSelectionsButton() {
    const clearSelectionsBtn = document.getElementById('clearSelectionsBtn');
    if (clearSelectionsBtn) {
        const newClearBtn = clearSelectionsBtn.cloneNode(true);
        clearSelectionsBtn.parentNode.replaceChild(newClearBtn, clearSelectionsBtn);
        newClearBtn.addEventListener('click', clearAllSelections);
    }
}

// ============================================
// SETUP CONFIRM BOOKING BUTTON
// ============================================
function setupConfirmBookingButton() {
    const confirmBtn = document.getElementById('confirmBookingBtn');
    if (confirmBtn) {
        const newConfirmBtn = confirmBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
        newConfirmBtn.addEventListener('click', confirmBooking);
    }
}

// ============================================
// INITIALIZATION
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    // Setup all handlers
    setupSeatClickHandlers();
    setupScheduleCards();
    setupMovieSelect();
    setupClearScheduleButton();
    setupClearSelectionsButton();
    setupConfirmBookingButton();
    
    // Initialize selected seats from any pre-selected (if any)
    const preSelectedSeats = <?php echo json_encode($selected_seats); ?>;
    if (preSelectedSeats && preSelectedSeats.length > 0) {
        preSelectedSeats.forEach(seatNumber => {
            selectedSeats.add(seatNumber);
            const seatElement = document.querySelector(`.seat-cell[data-seat-number="${seatNumber}"]`);
            if (seatElement && !seatElement.classList.contains('selected')) {
                const seatType = seatElement.dataset.seatType;
                seatElement.classList.remove('standard', 'premium', 'sweet-spot');
                seatElement.classList.add('selected');
                const price = parseFloat(seatElement.dataset.price);
                seatPriceMap.set(seatNumber, price);
            }
        });
        updateSeatSelection();
    }
    
    // Update booking schedule ID
    const bookingScheduleId = document.getElementById('bookingScheduleId');
    if (bookingScheduleId) {
        bookingScheduleId.value = currentScheduleId;
    }
    
    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl + Enter to confirm booking
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            e.preventDefault();
            if (document.getElementById('confirmBookingBtn')) {
                confirmBooking();
            }
        }
        // Escape to clear selections
        if (e.key === 'Escape' && selectedSeats.size > 0) {
            e.preventDefault();
            if (confirm('Clear all seat selections?')) {
                clearAllSelections();
            }
        }
    });
});
</script>

<?php require_once $root_dir . '/partials/footer.php'; ?>