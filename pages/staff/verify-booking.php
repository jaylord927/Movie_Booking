<?php
$root_dir = dirname(dirname(__DIR__));
require_once $root_dir . '/includes/config.php';
require_once $root_dir . '/includes/functions.php';
require_once $root_dir . '/includes/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Staff') {
    header("Location: " . SITE_URL . "index.php?page=login");
    exit();
}

require_once $root_dir . '/partials/staff-header.php';

$conn = get_db_connection();
$staff_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get selected filters
$selected_movie_id = isset($_GET['movie_id']) ? intval($_GET['movie_id']) : 0;
$selected_venue_id = isset($_GET['venue_id']) ? intval($_GET['venue_id']) : 0;
$selected_screen_id = isset($_GET['screen_id']) ? intval($_GET['screen_id']) : 0;

// ============================================
// Get all movies that have at least ONE paid booking (not cancelled)
// ============================================
$movies_stmt = $conn->prepare("
    SELECT DISTINCT 
        m.id,
        m.title,
        m.rating,
        m.duration,
        m.poster_url
    FROM movies m
    INNER JOIN schedules s ON m.id = s.movie_id
    INNER JOIN bookings b ON s.id = b.schedule_id
    WHERE b.payment_status = 'paid'
    AND b.status = 'ongoing'
    AND b.attendance_status = 'pending'
    AND m.is_active = 1
    AND s.is_active = 1
    AND s.show_date >= CURDATE()
    ORDER BY m.title ASC
");
$movies_stmt->execute();
$movies_result = $movies_stmt->get_result();
$movies = [];
while ($row = $movies_result->fetch_assoc()) {
    $movies[] = $row;
}
$movies_stmt->close();

// ============================================
// Get venues that have paid transactions for the selected movie
// ============================================
$venues = [];
if ($selected_movie_id > 0) {
    $venues_stmt = $conn->prepare("
        SELECT DISTINCT 
            v.id,
            v.venue_name,
            v.venue_location
        FROM venues v
        INNER JOIN screens sc ON v.id = sc.venue_id
        INNER JOIN schedules s ON sc.id = s.screen_id
        INNER JOIN bookings b ON s.id = b.schedule_id
        WHERE s.movie_id = ?
        AND b.payment_status = 'paid'
        AND b.status = 'ongoing'
        AND b.attendance_status = 'pending'
        AND v.is_active = 1
        AND sc.is_active = 1
        AND s.is_active = 1
        AND s.show_date >= CURDATE()
        ORDER BY v.venue_name ASC
    ");
    $venues_stmt->bind_param("i", $selected_movie_id);
    $venues_stmt->execute();
    $venues_result = $venues_stmt->get_result();
    while ($row = $venues_result->fetch_assoc()) {
        $venues[] = $row;
    }
    $venues_stmt->close();
}

// ============================================
// Get screens that have paid transactions for the selected movie and venue
// ============================================
$screens = [];
if ($selected_movie_id > 0 && $selected_venue_id > 0) {
    $screens_stmt = $conn->prepare("
        SELECT DISTINCT 
            sc.id,
            sc.screen_name,
            sc.screen_number,
            sc.capacity
        FROM screens sc
        INNER JOIN schedules s ON sc.id = s.screen_id
        INNER JOIN bookings b ON s.id = b.schedule_id
        WHERE s.movie_id = ?
        AND sc.venue_id = ?
        AND b.payment_status = 'paid'
        AND b.status = 'ongoing'
        AND b.attendance_status = 'pending'
        AND sc.is_active = 1
        AND s.is_active = 1
        AND s.show_date >= CURDATE()
        ORDER BY sc.screen_number ASC
    ");
    $screens_stmt->bind_param("ii", $selected_movie_id, $selected_venue_id);
    $screens_stmt->execute();
    $screens_result = $screens_stmt->get_result();
    while ($row = $screens_result->fetch_assoc()) {
        $screens[] = $row;
    }
    $screens_stmt->close();
}

// ============================================
// Get paid bookings for selected movie, venue, and screen
// ============================================
$bookings = [];
if ($selected_movie_id > 0 && $selected_venue_id > 0 && $selected_screen_id > 0) {
    $bookings_stmt = $conn->prepare("
        SELECT 
            b.id as booking_id,
            b.booking_reference,
            b.total_amount,
            b.payment_status,
            b.attendance_status,
            b.booked_at,
            b.verified_at,
            u.u_id as customer_id,
            u.u_name as customer_name,
            u.u_email as customer_email,
            m.id as movie_id,
            m.title as movie_title,
            m.poster_url,
            s.show_date,
            s.showtime,
            sc.screen_name,
            sc.screen_number,
            v.venue_name,
            v.venue_location,
            GROUP_CONCAT(DISTINCT bs.seat_number ORDER BY bs.seat_number SEPARATOR ', ') as seat_list,
            COUNT(DISTINCT bs.id) as total_seats,
            SUM(DISTINCT bs.price) as calculated_total
        FROM bookings b
        INNER JOIN users u ON b.user_id = u.u_id
        INNER JOIN schedules s ON b.schedule_id = s.id
        INNER JOIN movies m ON s.movie_id = m.id
        INNER JOIN screens sc ON s.screen_id = sc.id
        INNER JOIN venues v ON sc.venue_id = v.id
        LEFT JOIN booked_seats bs ON b.id = bs.booking_id
        WHERE s.movie_id = ?
        AND sc.venue_id = ?
        AND sc.id = ?
        AND b.payment_status = 'paid'
        AND b.status = 'ongoing'
        AND b.attendance_status = 'pending'
        AND s.is_active = 1
        AND s.show_date >= CURDATE()
        GROUP BY b.id, b.booking_reference, b.total_amount, b.payment_status, b.attendance_status, 
                 b.booked_at, b.verified_at, u.u_id, u.u_name, u.u_email, m.id, m.title, m.poster_url,
                 s.show_date, s.showtime, sc.screen_name, sc.screen_number, v.venue_name, v.venue_location
        ORDER BY s.show_date ASC, s.showtime ASC, b.booking_reference ASC
    ");
    $bookings_stmt->bind_param("iii", $selected_movie_id, $selected_venue_id, $selected_screen_id);
    $bookings_stmt->execute();
    $bookings_result = $bookings_stmt->get_result();
    
    while ($row = $bookings_result->fetch_assoc()) {
        $bookings[] = $row;
    }
    $bookings_stmt->close();
}

// ============================================
// Handle AJAX check-in request
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    header('Content-Type: application/json');
    
    if (isset($_POST['action']) && $_POST['action'] === 'check_in' && isset($_POST['booking_id'])) {
        $booking_id = intval($_POST['booking_id']);
        
        // Check booking status
        $check_stmt = $conn->prepare("
            SELECT attendance_status, payment_status, booking_reference 
            FROM bookings 
            WHERE id = ?
        ");
        $check_stmt->bind_param("i", $booking_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $booking_check = $check_result->fetch_assoc();
        $check_stmt->close();
        
        if ($booking_check && $booking_check['payment_status'] == 'paid' && $booking_check['attendance_status'] == 'pending') {
            $update_stmt = $conn->prepare("
                UPDATE bookings 
                SET attendance_status = 'present', verified_at = NOW(), verified_by = ?
                WHERE id = ?
            ");
            $update_stmt->bind_param("ii", $staff_id, $booking_id);
            
            if ($update_stmt->execute()) {
                // Log staff activity
                $log_stmt = $conn->prepare("
                    INSERT INTO staff_activity_log (staff_id, action, booking_id, details)
                    VALUES (?, 'CHECK_IN', ?, ?)
                ");
                $details = "Checked in customer via verify booking page";
                $log_stmt->bind_param("iis", $staff_id, $booking_id, $details);
                $log_stmt->execute();
                $log_stmt->close();
                
                echo json_encode([
                    'success' => true, 
                    'message' => '✓ Successfully verified! Customer can now enter the cinema.',
                    'booking_ref' => $booking_check['booking_reference']
                ]);
                exit();
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update booking status. Please try again.']);
                exit();
            }
            $update_stmt->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'Booking already verified or payment not confirmed.']);
            exit();
        }
    }
    exit();
}

$conn->close();
?>

<div class="staff-container" style="max-width: 1400px; margin: 0 auto; padding: 30px;">
    <div style="background: rgba(255, 255, 255, 0.05); border-radius: 15px; padding: 30px; margin-bottom: 30px; border: 1px solid rgba(52, 152, 219, 0.2);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;">
            <div>
                <h2 style="color: white; font-size: 1.8rem; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-search"></i> Verify Paid Bookings
                </h2>
                <p style="color: rgba(255, 255, 255, 0.7); margin-top: 5px;">
                    Select a movie, venue, and screen to view and verify paid transactions
                </p>
                <p style="color: #2ecc71; font-size: 0.85rem; margin-top: 8px;">
                    <i class="fas fa-info-circle"></i> Only shows paid, pending check-in bookings
                </p>
            </div>
            <button onclick="refreshPage()" style="background: #3498db; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-sync-alt"></i> Refresh Page
            </button>
        </div>

        <?php if ($error): ?>
            <div style="background: rgba(231, 76, 60, 0.2); color: #ff9999; padding: 15px 20px; border-radius: 10px; margin-bottom: 25px; border: 1px solid rgba(231, 76, 60, 0.3);">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div style="background: rgba(46, 204, 113, 0.2); color: #2ecc71; padding: 15px 20px; border-radius: 10px; margin-bottom: 25px; border: 1px solid rgba(46, 204, 113, 0.3);">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <!-- Cascading Dropdowns Section -->
        <div style="background: rgba(0, 0, 0, 0.2); border-radius: 10px; padding: 25px; margin-bottom: 25px;">
            <h3 style="color: white; font-size: 1.2rem; margin-bottom: 20px;">
                <i class="fas fa-filter"></i> Filter Options
            </h3>
            
            <form method="GET" action="" id="filterForm">
                <input type="hidden" name="page" value="staff/verify-booking">
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                    <!-- Dropdown 1: Movie -->
                    <div>
                        <label style="display: block; color: white; font-weight: 600; margin-bottom: 8px;">
                            <i class="fas fa-film"></i> Select Movie *
                        </label>
                        <select name="movie_id" id="movieSelect" required 
                                style="width: 100%; padding: 14px 16px; background: rgba(255,255,255,0.08); border: 2px solid rgba(52,152,219,0.3); border-radius: 10px; color: white; font-size: 1rem; cursor: pointer;">
                            <option value="" style="background: #2c3e50;">-- Select a movie --</option>
                            <?php foreach ($movies as $movie): ?>
                                <option value="<?php echo $movie['id']; ?>" 
                                        data-rating="<?php echo $movie['rating']; ?>"
                                        data-duration="<?php echo $movie['duration']; ?>"
                                        <?php echo $selected_movie_id == $movie['id'] ? 'selected' : ''; ?>
                                        style="background: #2c3e50; color: white;">
                                    <?php echo htmlspecialchars($movie['title']); ?> 
                                    (<?php echo $movie['rating']; ?> • <?php echo $movie['duration']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($movies)): ?>
                            <p style="color: #f39c12; font-size: 0.8rem; margin-top: 5px;">
                                <i class="fas fa-exclamation-triangle"></i> No movies with paid pending bookings available
                            </p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Dropdown 2: Venue (auto-filtered based on movie) -->
                    <div>
                        <label style="display: block; color: white; font-weight: 600; margin-bottom: 8px;">
                            <i class="fas fa-building"></i> Select Venue
                        </label>
                        <select name="venue_id" id="venueSelect" <?php echo empty($venues) ? 'disabled' : ''; ?>
                                style="width: 100%; padding: 14px 16px; background: rgba(255,255,255,0.08); border: 2px solid rgba(52,152,219,0.3); border-radius: 10px; color: white; font-size: 1rem; cursor: pointer;">
                            <option value="" style="background: #2c3e50;">-- Select a venue --</option>
                            <?php foreach ($venues as $venue): ?>
                                <option value="<?php echo $venue['id']; ?>" 
                                        <?php echo $selected_venue_id == $venue['id'] ? 'selected' : ''; ?>
                                        style="background: #2c3e50; color: white;">
                                    <?php echo htmlspecialchars($venue['venue_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($selected_movie_id > 0 && empty($venues)): ?>
                            <p style="color: #f39c12; font-size: 0.8rem; margin-top: 5px;">
                                <i class="fas fa-exclamation-triangle"></i> No venues with paid bookings for this movie
                            </p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Dropdown 3: Screen (auto-filtered based on movie + venue) -->
                    <div>
                        <label style="display: block; color: white; font-weight: 600; margin-bottom: 8px;">
                            <i class="fas fa-tv"></i> Select Screen
                        </label>
                        <select name="screen_id" id="screenSelect" <?php echo empty($screens) ? 'disabled' : ''; ?>
                                style="width: 100%; padding: 14px 16px; background: rgba(255,255,255,0.08); border: 2px solid rgba(52,152,219,0.3); border-radius: 10px; color: white; font-size: 1rem; cursor: pointer;">
                            <option value="" style="background: #2c3e50;">-- Select a screen --</option>
                            <?php foreach ($screens as $screen): ?>
                                <option value="<?php echo $screen['id']; ?>" 
                                        <?php echo $selected_screen_id == $screen['id'] ? 'selected' : ''; ?>
                                        style="background: #2c3e50; color: white;">
                                    <?php echo htmlspecialchars($screen['screen_name']); ?> (Screen #<?php echo $screen['screen_number']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($selected_venue_id > 0 && empty($screens)): ?>
                            <p style="color: #f39c12; font-size: 0.8rem; margin-top: 5px;">
                                <i class="fas fa-exclamation-triangle"></i> No screens with paid bookings for this venue
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div style="margin-top: 20px; display: flex; justify-content: center;">
                    <button type="submit" class="btn btn-primary" style="padding: 12px 30px;">
                        <i class="fas fa-search"></i> Load Bookings
                    </button>
                    <?php if ($selected_movie_id > 0 || $selected_venue_id > 0 || $selected_screen_id > 0): ?>
                        <a href="?page=staff/verify-booking" style="margin-left: 10px; padding: 12px 25px; background: rgba(255,255,255,0.1); color: white; text-decoration: none; border-radius: 8px;">
                            <i class="fas fa-times"></i> Clear Filters
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Bookings Display Section -->
        <?php if ($selected_movie_id > 0 && $selected_venue_id > 0 && $selected_screen_id > 0): ?>
            <div style="margin-top: 25px;">
                <?php 
                // Get selected movie, venue, screen names for display
                $display_movie = '';
                $display_venue = '';
                $display_screen = '';
                
                foreach ($movies as $m) {
                    if ($m['id'] == $selected_movie_id) {
                        $display_movie = $m['title'];
                        break;
                    }
                }
                foreach ($venues as $v) {
                    if ($v['id'] == $selected_venue_id) {
                        $display_venue = $v['venue_name'];
                        break;
                    }
                }
                foreach ($screens as $sc) {
                    if ($sc['id'] == $selected_screen_id) {
                        $display_screen = $sc['screen_name'] . ' (Screen #' . $sc['screen_number'] . ')';
                        break;
                    }
                }
                ?>
                
                <h3 style="color: white; font-size: 1.3rem; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #2ecc71;">
                    <i class="fas fa-ticket-alt"></i> Paid Bookings
                    <span style="font-size: 0.9rem; color: #2ecc71; margin-left: 10px;">
                        <?php echo htmlspecialchars($display_movie); ?> @ <?php echo htmlspecialchars($display_venue); ?> - <?php echo htmlspecialchars($display_screen); ?>
                    </span>
                </h3>
                
                <!-- Live Search -->
                <div style="margin-bottom: 20px;">
                    <div style="position: relative;">
                        <i class="fas fa-search" style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: rgba(255,255,255,0.5);"></i>
                        <input type="text" id="liveSearch" placeholder="Search by booking reference or customer name..." 
                               style="width: 100%; padding: 12px 15px 12px 45px; background: rgba(255,255,255,0.08); border: 2px solid rgba(46,204,113,0.3); border-radius: 10px; color: white; font-size: 1rem;">
                    </div>
                </div>
                
                <?php if (empty($bookings)): ?>
                    <div style="text-align: center; padding: 60px; background: rgba(0,0,0,0.2); border-radius: 10px;">
                        <i class="fas fa-ticket-alt fa-3x" style="color: rgba(46,204,113,0.3); margin-bottom: 15px;"></i>
                        <p style="color: rgba(255,255,255,0.6); font-size: 1.1rem;">No paid transactions available for this selection.</p>
                        <p style="color: rgba(255,255,255,0.4); font-size: 0.9rem; margin-top: 10px;">
                            All bookings for this movie at this venue/screen have already been verified or are pending payment.
                        </p>
                    </div>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);">
                                    <th style="padding: 14px; text-align: left; color: white;">Booking Ref</th>
                                    <th style="padding: 14px; text-align: left; color: white;">Customer</th>
                                    <th style="padding: 14px; text-align: left; color: white;">Show Date & Time</th>
                                    <th style="padding: 14px; text-align: left; color: white;">Seats</th>
                                    <th style="padding: 14px; text-align: left; color: white;">Amount</th>
                                    <th style="padding: 14px; text-align: left; color: white;">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="bookingsTableBody">
                                <?php foreach ($bookings as $booking): ?>
                                    <tr class="booking-row" 
                                        data-booking-id="<?php echo $booking['booking_id']; ?>" 
                                        data-booking-ref="<?php echo $booking['booking_reference']; ?>"
                                        data-customer-name="<?php echo htmlspecialchars($booking['customer_name']); ?>"
                                        data-customer-email="<?php echo htmlspecialchars($booking['customer_email']); ?>"
                                        data-movie-name="<?php echo htmlspecialchars($booking['movie_title']); ?>"
                                        data-show-date="<?php echo $booking['show_date']; ?>"
                                        data-show-time="<?php echo date('h:i A', strtotime($booking['showtime'])); ?>"
                                        data-venue-name="<?php echo htmlspecialchars($booking['venue_name']); ?>"
                                        data-screen-name="<?php echo htmlspecialchars($booking['screen_name']); ?>"
                                        data-seat-list="<?php echo htmlspecialchars($booking['seat_list'] ?? 'N/A'); ?>"
                                        data-total-seats="<?php echo $booking['total_seats']; ?>"
                                        data-total-amount="<?php echo number_format($booking['total_amount'], 2); ?>"
                                        style="border-bottom: 1px solid rgba(255,255,255,0.1);">
                                        <td style="padding: 12px;">
                                            <span style="color: white; font-weight: 600; font-family: monospace;"><?php echo $booking['booking_reference']; ?></span>
                                         </div>
                                        </td>
                                        <td style="padding: 12px;">
                                            <div style="color: white; font-weight: 600;"><?php echo htmlspecialchars($booking['customer_name']); ?></div>
                                            <div style="color: rgba(255,255,255,0.6); font-size: 0.75rem;"><?php echo htmlspecialchars($booking['customer_email']); ?></div>
                                         </div>
                                        </td>
                                        <td style="padding: 12px;">
                                            <div style="color: white;"><?php echo date('M d, Y', strtotime($booking['show_date'])); ?></div>
                                            <div style="color: #2ecc71; font-size: 0.85rem;"><?php echo date('h:i A', strtotime($booking['showtime'])); ?></div>
                                         </div>
                                        </td>
                                        <td style="padding: 12px;">
                                            <span style="color: #3498db; font-weight: 500;"><?php echo htmlspecialchars($booking['seat_list'] ?? 'N/A'); ?></span>
                                            <div style="color: rgba(255,255,255,0.5); font-size: 0.7rem;"><?php echo $booking['total_seats']; ?> seat(s)</div>
                                         </div>
                                        </td>
                                        <td style="padding: 12px;">
                                            <span style="color: #2ecc71; font-size: 1.1rem; font-weight: 700;">₱<?php echo number_format($booking['total_amount'], 2); ?></span>
                                         </div>
                                        </td>
                                        <td style="padding: 12px;">
                                            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                                <button onclick="viewBookingDetails(this)" class="btn-view-details" 
                                                        style="background: #3498db; color: white; border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer; font-size: 0.8rem;">
                                                    <i class="fas fa-eye"></i> View Details
                                                </button>
                                                <button onclick="markAsPresent(this)" class="btn-mark-present" 
                                                        style="background: #2ecc71; color: white; border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer; font-size: 0.8rem;">
                                                    <i class="fas fa-check"></i> Mark Present
                                                </button>
                                            </div>
                                         </div>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div style="margin-top: 15px; text-align: center; color: rgba(255,255,255,0.5); font-size: 0.85rem;">
                        <span id="filterCount"><?php echo count($bookings); ?></span> paid booking(s) ready for verification
                    </div>
                <?php endif; ?>
            </div>
        <?php elseif ($selected_movie_id > 0 && ($selected_venue_id == 0 || $selected_screen_id == 0)): ?>
            <div style="text-align: center; padding: 50px; background: rgba(0,0,0,0.2); border-radius: 10px;">
                <i class="fas fa-hand-pointer fa-3x" style="color: #3498db; margin-bottom: 15px;"></i>
                <p style="color: rgba(255,255,255,0.7); font-size: 1.1rem;">Please select a venue and screen to view paid bookings.</p>
            </div>
        <?php elseif (!empty($movies)): ?>
            <div style="text-align: center; padding: 50px; background: rgba(0,0,0,0.2); border-radius: 10px;">
                <i class="fas fa-film fa-3x" style="color: #3498db; margin-bottom: 15px;"></i>
                <p style="color: rgba(255,255,255,0.7); font-size: 1.1rem;">Select a movie from the dropdown above to view paid bookings.</p>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 50px; background: rgba(0,0,0,0.2); border-radius: 10px;">
                <i class="fas fa-ticket-alt fa-3x" style="color: #f39c12; margin-bottom: 15px;"></i>
                <p style="color: rgba(255,255,255,0.7); font-size: 1.1rem;">No pending paid bookings available.</p>
                <p style="color: rgba(255,255,255,0.4); font-size: 0.9rem; margin-top: 10px;">
                    All paid bookings have been verified or no new bookings exist.
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- View Details Modal -->
<div id="detailsModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.95); z-index: 1000; justify-content: center; align-items: center; padding: 20px; overflow-y: auto;">
    <div style="background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); border-radius: 20px; padding: 30px; max-width: 700px; width: 100%; border: 2px solid #2ecc71;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 style="color: #2ecc71;"><i class="fas fa-receipt"></i> Booking Receipt Details</h2>
            <button onclick="closeDetailsModal()" style="background: none; border: none; color: white; font-size: 2rem; cursor: pointer;">&times;</button>
        </div>
        <div id="detailsModalContent"></div>
    </div>
</div>

<!-- Toast Notification -->
<div id="toastNotification" style="display: none; position: fixed; bottom: 30px; right: 30px; padding: 15px 25px; border-radius: 10px; color: white; font-weight: 600; z-index: 1001; animation: slideIn 0.3s ease;"></div>

<style>
@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes slideOut {
    from {
        transform: translateX(0);
        opacity: 1;
    }
    to {
        transform: translateX(100%);
        opacity: 0;
    }
}

.booking-row {
    transition: background 0.2s ease;
}

.booking-row:hover {
    background: rgba(46, 204, 113, 0.1);
}

#liveSearch {
    transition: all 0.3s ease;
}

#liveSearch:focus {
    outline: none;
    border-color: #2ecc71;
    box-shadow: 0 0 0 3px rgba(46, 204, 113, 0.2);
}

select, input {
    transition: all 0.3s ease;
}

select:focus, input:focus {
    outline: none;
    border-color: #2ecc71;
    box-shadow: 0 0 0 3px rgba(46, 204, 113, 0.2);
}

select:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.receipt-detail-row {
    padding: 10px 0;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}

.receipt-label {
    color: #2ecc71;
    font-weight: 600;
    min-width: 140px;
    display: inline-block;
}

.receipt-value {
    color: white;
}

.btn-primary {
    background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
}

.btn-primary:hover {
    transform: translateY(-2px);
    opacity: 0.9;
}

@media (max-width: 768px) {
    .staff-container {
        padding: 15px;
    }
    
    table {
        font-size: 0.8rem;
    }
    
    th, td {
        padding: 8px !important;
    }
    
    .receipt-label {
        min-width: 100px;
    }
}
</style>

<script>
// Refresh page function
function refreshPage() {
    location.reload();
}

// ============================================
// AJAX Cascading Dropdowns
// ============================================

// Get venues when movie changes
document.getElementById('movieSelect')?.addEventListener('change', function() {
    const movieId = this.value;
    if (movieId) {
        // Submit the form to load venues
        document.getElementById('filterForm').submit();
    }
});

// Get screens when venue changes
document.getElementById('venueSelect')?.addEventListener('change', function() {
    if (this.value) {
        document.getElementById('filterForm').submit();
    }
});

// Live search functionality
const liveSearch = document.getElementById('liveSearch');
const bookingRows = document.querySelectorAll('.booking-row');
const filterCount = document.getElementById('filterCount');

if (liveSearch) {
    liveSearch.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase().trim();
        let visibleCount = 0;
        
        bookingRows.forEach(row => {
            const ref = row.querySelector('td:first-child')?.innerText.toLowerCase() || '';
            const customer = row.querySelector('td:nth-child(2)')?.innerText.toLowerCase() || '';
            
            if (ref.includes(searchTerm) || customer.includes(searchTerm) || searchTerm === '') {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        
        if (filterCount) {
            filterCount.textContent = visibleCount;
        }
    });
}

// View Booking Details
function viewBookingDetails(button) {
    const row = button.closest('tr');
    
    const bookingData = {
        booking_ref: row.dataset.bookingRef,
        customer_name: row.dataset.customerName,
        customer_email: row.dataset.customerEmail,
        movie_name: row.dataset.movieName,
        show_date: row.dataset.showDate,
        show_time: row.dataset.showTime,
        venue_name: row.dataset.venueName,
        screen_name: row.dataset.screenName,
        seat_list: row.dataset.seatList,
        total_seats: row.dataset.totalSeats,
        total_amount: row.dataset.totalAmount
    };
    
    const modalContent = document.getElementById('detailsModalContent');
    modalContent.innerHTML = `
        <div style="background: rgba(0,0,0,0.3); border-radius: 10px; padding: 20px;">
            <div class="receipt-detail-row">
                <span class="receipt-label">Booking Reference:</span>
                <span class="receipt-value" style="font-family: monospace; font-weight: 600;">${escapeHtml(bookingData.booking_ref)}</span>
            </div>
            <div class="receipt-detail-row">
                <span class="receipt-label">Customer Name:</span>
                <span class="receipt-value">${escapeHtml(bookingData.customer_name)}</span>
            </div>
            <div class="receipt-detail-row">
                <span class="receipt-label">Customer Email:</span>
                <span class="receipt-value">${escapeHtml(bookingData.customer_email)}</span>
            </div>
            <div class="receipt-detail-row">
                <span class="receipt-label">Movie:</span>
                <span class="receipt-value">${escapeHtml(bookingData.movie_name)}</span>
            </div>
            <div class="receipt-detail-row">
                <span class="receipt-label">Show Date:</span>
                <span class="receipt-value">${new Date(bookingData.show_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</span>
            </div>
            <div class="receipt-detail-row">
                <span class="receipt-label">Show Time:</span>
                <span class="receipt-value">${bookingData.show_time}</span>
            </div>
            <div class="receipt-detail-row">
                <span class="receipt-label">Venue:</span>
                <span class="receipt-value">${escapeHtml(bookingData.venue_name)}</span>
            </div>
            <div class="receipt-detail-row">
                <span class="receipt-label">Screen:</span>
                <span class="receipt-value">${escapeHtml(bookingData.screen_name)}</span>
            </div>
            <div class="receipt-detail-row">
                <span class="receipt-label">Selected Seats:</span>
                <span class="receipt-value" style="color: #2ecc71; font-weight: 600;">${escapeHtml(bookingData.seat_list)}</span>
            </div>
            <div class="receipt-detail-row">
                <span class="receipt-label">Total Seats:</span>
                <span class="receipt-value">${bookingData.total_seats} seat(s)</span>
            </div>
            <div class="receipt-detail-row">
                <span class="receipt-label">Total Amount Paid:</span>
                <span class="receipt-value" style="color: #2ecc71; font-weight: 800; font-size: 1.2rem;">₱${bookingData.total_amount}</span>
            </div>
            <div class="receipt-detail-row">
                <span class="receipt-label">Payment Status:</span>
                <span class="receipt-value" style="color: #2ecc71;">✓ Paid</span>
            </div>
        </div>
    `;
    
    document.getElementById('detailsModal').style.display = 'flex';
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function closeDetailsModal() {
    document.getElementById('detailsModal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const detailsModal = document.getElementById('detailsModal');
    if (event.target == detailsModal) {
        closeDetailsModal();
    }
}

// Mark as Present with AJAX
async function markAsPresent(button) {
    const row = button.closest('tr');
    const bookingId = row.dataset.bookingId;
    const bookingRef = row.dataset.bookingRef;
    const customerName = row.dataset.customerName;
    
    if (!confirm(`Check in customer "${customerName}" (${bookingRef})? This will issue a physical ticket.`)) {
        return;
    }
    
    // Disable button to prevent double submission
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    
    try {
        const formData = new FormData();
        formData.append('action', 'check_in');
        formData.append('booking_id', bookingId);
        
        const response = await fetch(window.location.href, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        });
        
        const text = await response.text();
        let result;
        
        try {
            result = JSON.parse(text);
        } catch (e) {
            console.error('Parse error:', text);
            showToast('error', 'An error occurred. Please try again or use the refresh button.');
            button.disabled = false;
            button.innerHTML = '<i class="fas fa-check"></i> Mark Present';
            return;
        }
        
        if (result.success) {
            // Remove the row with fade out animation
            row.style.transition = 'opacity 0.3s ease';
            row.style.opacity = '0';
            
            setTimeout(() => {
                row.remove();
                
                // Update count
                const remainingRows = document.querySelectorAll('.booking-row').length;
                if (filterCount) {
                    filterCount.textContent = remainingRows;
                }
                
                // Show success message
                showToast('success', result.message || `✓ Successfully verified ${customerName}!`);
                
                // Show empty message if no rows left
                if (remainingRows === 0) {
                    const tableBody = document.getElementById('bookingsTableBody');
                    if (tableBody) {
                        tableBody.innerHTML = `
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 50px;">
                                    <i class="fas fa-ticket-alt fa-2x" style="color: rgba(46,204,113,0.3);"></i>
                                    <p style="color: rgba(255,255,255,0.6); margin-top: 10px;">All bookings have been verified!</p>
                                </td>
                            </tr>
                        `;
                    }
                }
            }, 300);
        } else {
            showToast('error', result.message || 'Verification failed. Please try again.');
            button.disabled = false;
            button.innerHTML = '<i class="fas fa-check"></i> Mark Present';
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('error', 'Connection error. Please check your internet and try again.');
        button.disabled = false;
        button.innerHTML = '<i class="fas fa-check"></i> Mark Present';
    }
}

function showToast(type, message) {
    const toast = document.getElementById('toastNotification');
    toast.style.backgroundColor = type === 'success' ? '#2ecc71' : '#e74c3c';
    toast.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i> ${message}`;
    toast.style.display = 'block';
    
    setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => {
            toast.style.display = 'none';
            toast.style.animation = '';
        }, 300);
    }, 3000);
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
        e.preventDefault();
        refreshPage();
    }
    if (e.key === 'Escape') {
        closeDetailsModal();
    }
});
</script>

