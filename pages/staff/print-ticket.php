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
$selected_movie = isset($_GET['movie']) ? urldecode($_GET['movie']) : '';
$selected_schedule_id = isset($_GET['schedule']) ? intval($_GET['schedule']) : 0;
$selected_movie_title = '';
$selected_show_date = '';
$selected_show_time = '';

// ============================================
// Get movies that have verified (Present) bookings
// Grouped by movie AND showtime using normalized schema
// ============================================
$movies_stmt = $conn->prepare("
    SELECT DISTINCT 
        m.title as movie_name,
        s.show_date,
        s.showtime,
        s.id as schedule_id,
        CONCAT(m.title, '|', s.show_date, '|', s.showtime) as unique_key
    FROM bookings b
    JOIN schedules s ON b.schedule_id = s.id
    JOIN movies m ON s.movie_id = m.id
    WHERE b.attendance_status = 'present'
    AND b.payment_status = 'paid'
    AND b.status = 'ongoing'
    AND m.is_active = 1
    AND s.is_active = 1
    ORDER BY s.show_date, s.showtime, m.title
");
$movies_stmt->execute();
$movies_result = $movies_stmt->get_result();
$movies = [];
while ($row = $movies_result->fetch_assoc()) {
    $unique_key = $row['unique_key'];
    $movies[$unique_key] = [
        'movie_name' => $row['movie_name'],
        'show_date' => $row['show_date'],
        'showtime' => $row['showtime'],
        'schedule_id' => $row['schedule_id']
    ];
}
$movies_stmt->close();

// ============================================
// If schedule_id is provided directly, get that specific schedule
// ============================================
$selected_schedule_data = null;
if ($selected_schedule_id > 0) {
    $schedule_stmt = $conn->prepare("
        SELECT DISTINCT 
            m.title as movie_name,
            s.show_date,
            s.showtime,
            s.id as schedule_id,
            CONCAT(m.title, '|', s.show_date, '|', s.showtime) as unique_key
        FROM schedules s
        JOIN movies m ON s.movie_id = m.id
        WHERE s.id = ? AND s.is_active = 1 AND s.show_date >= CURDATE()
    ");
    $schedule_stmt->bind_param("i", $selected_schedule_id);
    $schedule_stmt->execute();
    $schedule_result = $schedule_stmt->get_result();
    if ($schedule_result->num_rows > 0) {
        $row = $schedule_result->fetch_assoc();
        $selected_schedule_data = $row;
        $selected_movie = $row['unique_key'];
        $selected_movie_title = $row['movie_name'];
        $selected_show_date = $row['show_date'];
        $selected_show_time = $row['showtime'];
    }
    $schedule_stmt->close();
}

// ============================================
// Get verified bookings for selected movie/showtime
// Using normalized schema with proper joins
// ============================================
$grouped_bookings = [];
$schedule_info = null;

if ($selected_movie) {
    // Parse selected movie to get name, date, and time
    $selected_parts = explode('|', $selected_movie);
    $selected_movie_title = $selected_parts[0] ?? '';
    $selected_show_date = $selected_parts[1] ?? '';
    $selected_show_time = $selected_parts[2] ?? '';
    
    // Get schedule info first (venue, screen, etc.)
    $schedule_info_stmt = $conn->prepare("
        SELECT DISTINCT
            s.id as schedule_id,
            s.show_date,
            s.showtime,
            v.id as venue_id,
            v.venue_name,
            v.venue_location,
            sc.id as screen_id,
            sc.screen_name,
            sc.screen_number,
            m.id as movie_id,
            m.title as movie_title,
            m.poster_url,
            m.rating
        FROM schedules s
        JOIN movies m ON s.movie_id = m.id
        JOIN screens sc ON s.screen_id = sc.id
        JOIN venues v ON sc.venue_id = v.id
        WHERE m.title = ? 
        AND s.show_date = ? 
        AND s.showtime = ?
        AND s.is_active = 1
    ");
    $schedule_info_stmt->bind_param("sss", $selected_movie_title, $selected_show_date, $selected_show_time);
    $schedule_info_stmt->execute();
    $schedule_info_result = $schedule_info_stmt->get_result();
    $schedule_info = $schedule_info_result->fetch_assoc();
    $schedule_info_stmt->close();
    
    // Get all verified bookings for this specific movie showtime
    $bookings_stmt = $conn->prepare("
        SELECT 
            b.id as booking_id,
            b.booking_reference,
            b.total_amount,
            b.payment_status,
            b.attendance_status,
            b.verified_at,
            u.u_id as customer_id,
            u.u_name as customer_name,
            u.u_email as customer_email,
            bs.seat_number,
            bs.seat_type_id,
            bs.price as seat_price,
            st.name as seat_type,
            st.color_code as seat_color
        FROM bookings b
        JOIN users u ON b.user_id = u.u_id
        JOIN schedules s ON b.schedule_id = s.id
        JOIN movies m ON s.movie_id = m.id
        JOIN booked_seats bs ON b.id = bs.booking_id
        JOIN seat_types st ON bs.seat_type_id = st.id
        WHERE m.title = ? 
        AND s.show_date = ? 
        AND s.showtime = ? 
        AND b.attendance_status = 'present'
        AND b.payment_status = 'paid'
        AND b.status = 'ongoing'
        ORDER BY b.booking_reference, bs.seat_number
    ");
    $bookings_stmt->bind_param("sss", $selected_movie_title, $selected_show_date, $selected_show_time);
    $bookings_stmt->execute();
    $bookings_result = $bookings_stmt->get_result();
    
    // Group bookings by customer
    while ($row = $bookings_result->fetch_assoc()) {
        $key = $row['booking_reference'];
        if (!isset($grouped_bookings[$key])) {
            $grouped_bookings[$key] = [
                'booking_id' => $row['booking_id'],
                'booking_reference' => $row['booking_reference'],
                'customer_name' => $row['customer_name'],
                'customer_email' => $row['customer_email'],
                'verified_at' => $row['verified_at'],
                'total_amount' => $row['total_amount'],
                'seats' => []
            ];
        }
        $grouped_bookings[$key]['seats'][] = [
            'seat_number' => $row['seat_number'],
            'seat_type' => $row['seat_type'],
            'price' => $row['seat_price']
        ];
    }
    $bookings_stmt->close();
}

$conn->close();
?>

<div class="staff-container" style="max-width: 1400px; margin: 0 auto; padding: 30px;">
    <div style="background: rgba(255, 255, 255, 0.05); border-radius: 15px; padding: 30px; margin-bottom: 30px; border: 1px solid rgba(52, 152, 219, 0.2);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;">
            <div>
                <h2 style="color: white; font-size: 1.8rem; margin-bottom: 10px; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-print"></i> Print Tickets
                </h2>
                <p style="color: rgba(255, 255, 255, 0.7);">Select a movie to print verified tickets (customers who have checked in)</p>
            </div>
            <button onclick="refreshPage()" style="background: #3498db; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-sync-alt"></i> Refresh Page
            </button>
        </div>

        <!-- Movie Selection -->
        <div style="background: rgba(0, 0, 0, 0.2); border-radius: 10px; padding: 20px; margin-bottom: 25px;">
            <label style="display: block; color: white; font-weight: 600; margin-bottom: 10px;">
                <i class="fas fa-film"></i> Select Movie & Showtime
            </label>
            <form method="GET" action="" id="movieSelectForm">
                <input type="hidden" name="page" value="staff/print-ticket">
                <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                    <select name="movie" required id="movieSelect" style="flex: 1; padding: 14px 16px; background: rgba(255,255,255,0.08); border: 2px solid rgba(46,204,113,0.3); border-radius: 10px; color: white; font-size: 1rem; cursor: pointer;">
                        <option value="" style="background: #2c3e50;">-- Select a movie --</option>
                        <?php 
                        // Sort movies by date and time (earliest first)
                        uasort($movies, function($a, $b) {
                            $dateTimeA = strtotime($a['show_date'] . ' ' . $a['showtime']);
                            $dateTimeB = strtotime($b['show_date'] . ' ' . $b['showtime']);
                            return $dateTimeA - $dateTimeB;
                        });
                        
                        foreach ($movies as $unique_key => $movie): 
                            $is_today = date('Y-m-d') == $movie['show_date'];
                            $display_key = $movie['movie_name'] . '|' . $movie['show_date'] . '|' . $movie['showtime'];
                        ?>
                            <option value="<?php echo htmlspecialchars($display_key); ?>" style="background: #2c3e50;" <?php echo $selected_movie == $display_key ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($movie['movie_name']); ?> - <?php echo date('h:i A', strtotime($movie['showtime'])); ?> (<?php echo date('M d, Y', strtotime($movie['show_date'])); ?>)
                                <?php if ($is_today): ?>(Today)<?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" style="padding: 14px 30px; background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%); color: white; border: none; border-radius: 10px; font-weight: 600; cursor: pointer;">
                        <i class="fas fa-search"></i> Load Tickets
                    </button>
                </div>
            </form>
            <?php if (empty($movies)): ?>
                <div style="margin-top: 15px; padding: 12px; background: rgba(241, 196, 15, 0.1); border-left: 4px solid #f39c12; border-radius: 5px;">
                    <p style="color: #f39c12; font-size: 0.9rem;">
                        <i class="fas fa-info-circle"></i> No verified bookings found. Customers need to check in first before tickets can be printed.
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Tickets Display -->
        <?php if ($selected_movie && !empty($grouped_bookings) && $schedule_info): ?>
            <div style="margin-top: 25px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
                    <h3 style="color: #2ecc71; font-size: 1.3rem;">
                        <i class="fas fa-check-circle"></i> Verified Tickets - <?php echo htmlspecialchars($selected_movie_title); ?>
                        <span style="font-size: 0.9rem; color: #3498db; margin-left: 10px;"><?php echo date('F d, Y', strtotime($selected_show_date)); ?> | <?php echo date('h:i A', strtotime($selected_show_time)); ?></span>
                    </h3>
                    <button onclick="printAllTickets()" style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer;">
                        <i class="fas fa-print"></i> Print All Tickets
                    </button>
                </div>
                
                <!-- Hand Mark Sample / Visual Guide -->
                <div style="background: rgba(46, 204, 113, 0.1); border: 2px solid #2ecc71; border-radius: 15px; padding: 20px; margin-bottom: 25px;">
                    <div style="display: flex; align-items: center; gap: 25px; flex-wrap: wrap;">
                        <!-- Hand Mark Stamp Sample -->
                        <div style="text-align: center;">
                            <div style="background: #2ecc71; color: #1a1a2e; padding: 15px 25px; border-radius: 12px; font-weight: 800; text-align: center; min-width: 180px; box-shadow: 0 4px 15px rgba(46,204,113,0.3);">
                                <i class="fas fa-hand-peace" style="font-size: 2rem; display: block; margin-bottom: 8px;"></i>
                                <span style="font-size: 1.2rem;">VERIFIED</span>
                                <div style="font-size: 0.75rem; margin-top: 5px; border-top: 1px solid rgba(0,0,0,0.1); padding-top: 5px;">
                                    <?php echo date('M d, Y', strtotime($selected_show_date)); ?> | <?php echo date('h:i A', strtotime($selected_show_time)); ?>
                                </div>
                                <div style="font-size: 0.7rem; margin-top: 3px; font-style: italic;">
                                    <?php echo substr(htmlspecialchars($selected_movie_title), 0, 25); ?>
                                </div>
                            </div>
                            <p style="color: #2ecc71; font-size: 0.8rem; margin-top: 8px;">
                                <i class="fas fa-hand-peace"></i> Hand Mark Sample
                            </p>
                        </div>
                        
                        <!-- Description -->
                        <div style="flex: 1; color: rgba(255,255,255,0.85); font-size: 0.95rem; line-height: 1.5;">
                            <i class="fas fa-info-circle" style="color: #2ecc71; margin-right: 8px;"></i> 
                            <strong>Staff should stamp/mark the hand with this symbol after ticket verification.</strong><br>
                            This serves as proof for re-entry if the customer needs to step out temporarily.
                            <div style="margin-top: 10px; display: flex; gap: 15px; flex-wrap: wrap;">
                                <span style="background: rgba(46,204,113,0.2); padding: 4px 10px; border-radius: 20px; font-size: 0.8rem;">
                                    <i class="fas fa-film"></i> <?php echo htmlspecialchars($selected_movie_title); ?>
                                </span>
                                <span style="background: rgba(46,204,113,0.2); padding: 4px 10px; border-radius: 20px; font-size: 0.8rem;">
                                    <i class="fas fa-clock"></i> <?php echo date('h:i A', strtotime($selected_show_time)); ?>
                                </span>
                                <span style="background: rgba(46,204,113,0.2); padding: 4px 10px; border-radius: 20px; font-size: 0.8rem;">
                                    <i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($selected_show_date)); ?>
                                </span>
                                <span style="background: rgba(46,204,113,0.2); padding: 4px 10px; border-radius: 20px; font-size: 0.8rem;">
                                    <i class="fas fa-building"></i> <?php echo htmlspecialchars($schedule_info['venue_name']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php foreach ($grouped_bookings as $booking): ?>
                    <div class="ticket-group" style="margin-bottom: 30px; background: rgba(0,0,0,0.2); border-radius: 12px; padding: 20px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #2ecc71;">
                            <div>
                                <strong style="color: #2ecc71;">Customer:</strong> <?php echo htmlspecialchars($booking['customer_name']); ?><br>
                                <strong style="color: #2ecc71;">Booking Ref:</strong> <span style="font-family: monospace;"><?php echo htmlspecialchars($booking['booking_reference']); ?></span>
                            </div>
                            <div style="text-align: right;">
                                <strong style="color: #2ecc71;">Verified At:</strong> <?php echo date('h:i A', strtotime($booking['verified_at'])); ?><br>
                                <strong style="color: #2ecc71;">Total Amount:</strong> <span style="color: #2ecc71;">₱<?php echo number_format($booking['total_amount'], 2); ?></span>
                            </div>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;">
                            <?php foreach ($booking['seats'] as $index => $seat): ?>
                                <div class="ticket-card" id="ticket-<?php echo $booking['booking_reference'] . '-' . $index; ?>" style="background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
                                    <!-- Ticket Stub (Left side - perforated style) -->
                                    <div style="display: flex; min-height: 200px;">
                                        <!-- Stub / Tear-off section -->
                                        <div style="width: 80px; background: linear-gradient(135deg, #e23020 0%, #c11b18 100%); color: white; padding: 15px 10px; text-align: center; display: flex; flex-direction: column; justify-content: space-between;">
                                            <div>
                                                <i class="fas fa-ticket-alt" style="font-size: 1.5rem;"></i>
                                                <div style="font-size: 0.7rem; margin-top: 5px;">ADMIT ONE</div>
                                            </div>
                                            <div style="border-top: 2px dashed rgba(255,255,255,0.3); margin-top: 10px; padding-top: 10px;">
                                                <div style="font-size: 0.7rem;">Seat</div>
                                                <div style="font-size: 1.2rem; font-weight: 800;"><?php echo htmlspecialchars($seat['seat_number']); ?></div>
                                            </div>
                                        </div>
                                        
                                        <!-- Main Ticket Body -->
                                        <div style="flex: 1; padding: 15px;">
                                            <!-- Hand Mark Stamp Area -->
                                            <div style="background: #f0f0f0; border-radius: 8px; padding: 8px; margin-bottom: 12px; text-align: center; border: 2px solid #2ecc71;">
                                                <div style="background: #2ecc71; color: #1a1a2e; padding: 5px 10px; border-radius: 5px; display: inline-block; font-weight: 800; font-size: 0.8rem;">
                                                    <i class="fas fa-hand-peace"></i> VERIFIED
                                                </div>
                                                <div style="font-size: 0.7rem; color: #666; margin-top: 5px;">
                                                    <?php echo date('M d, Y', strtotime($selected_show_date)) . ' | ' . date('h:i A', strtotime($selected_show_time)); ?>
                                                </div>
                                                <div style="font-size: 0.65rem; color: #888; margin-top: 3px;">
                                                    <?php echo htmlspecialchars($selected_movie_title); ?>
                                                </div>
                                            </div>
                                            
                                            <div style="text-align: center;">
                                                <h3 style="color: #e23020; font-size: 1rem; margin-bottom: 5px;"><?php echo htmlspecialchars($selected_movie_title); ?></h3>
                                                <div style="display: flex; justify-content: center; gap: 10px; margin-bottom: 8px; flex-wrap: wrap;">
                                                    <span style="background: <?php 
                                                        echo $seat['seat_type'] == 'Premium' ? '#FFD700' : ($seat['seat_type'] == 'Sweet Spot' ? '#e74c3c' : '#3498db'); 
                                                    ?>; color: white; padding: 3px 12px; border-radius: 15px; font-size: 0.7rem; font-weight: 600;">
                                                        <?php echo htmlspecialchars($seat['seat_type']); ?>
                                                    </span>
                                                    <span style="background: #3498db; color: white; padding: 3px 12px; border-radius: 15px; font-size: 0.7rem; font-weight: 600;">
                                                        Seat: <?php echo htmlspecialchars($seat['seat_number']); ?>
                                                    </span>
                                                </div>
                                                <div style="color: #666; font-size: 0.75rem;">
                                                    <div><?php echo date('l, F d, Y', strtotime($selected_show_date)); ?></div>
                                                    <div><?php echo date('h:i A', strtotime($selected_show_time)); ?></div>
                                                    <div><?php echo htmlspecialchars($schedule_info['venue_name']); ?> - <?php echo htmlspecialchars($schedule_info['screen_name']); ?></div>
                                                </div>
                                                <div style="margin-top: 8px; padding-top: 5px; border-top: 1px dashed #ddd; font-size: 0.65rem; color: #999;">
                                                    <i class="fas fa-qrcode"></i> <?php echo htmlspecialchars($booking['booking_reference']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Ticket Footer / Terms -->
                                    <div style="background: #f8f9fa; padding: 6px; text-align: center; font-size: 0.55rem; color: #666; border-top: 1px solid #ddd;">
                                        <i class="fas fa-hand-peace"></i> Hand stamp required for re-entry • No refunds after show starts • Keep ticket for duration
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Print button for this customer's tickets -->
                        <div style="margin-top: 15px; text-align: right;">
                            <button onclick="printCustomerTickets('<?php echo htmlspecialchars($booking['booking_reference']); ?>')" style="background: #3498db; color: white; border: none; padding: 8px 20px; border-radius: 6px; cursor: pointer;">
                                <i class="fas fa-print"></i> Print Tickets for <?php echo htmlspecialchars($booking['customer_name']); ?>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <!-- Venue Information Footer -->
                <div style="margin-top: 30px; padding: 20px; background: rgba(0,0,0,0.2); border-radius: 10px; text-align: center;">
                    <p style="color: rgba(255,255,255,0.6); font-size: 0.8rem;">
                        <i class="fas fa-building"></i> <strong><?php echo htmlspecialchars($schedule_info['venue_name']); ?></strong><br>
                        <?php if (!empty($schedule_info['venue_location'])): ?>
                            <?php echo htmlspecialchars($schedule_info['venue_location']); ?><br>
                        <?php endif; ?>
                        Screen: <?php echo htmlspecialchars($schedule_info['screen_name']); ?> (Screen #<?php echo $schedule_info['screen_number']; ?>)
                    </p>
                </div>
            </div>
        <?php elseif ($selected_movie): ?>
            <div style="text-align: center; padding: 50px; background: rgba(0,0,0,0.2); border-radius: 10px;">
                <i class="fas fa-check-circle fa-3x" style="color: rgba(46,204,113,0.3); margin-bottom: 15px;"></i>
                <p style="color: rgba(255,255,255,0.6);">No verified tickets found for this movie showtime.</p>
                <p style="color: rgba(255,255,255,0.4); font-size: 0.9rem;">Only customers who have checked in (Present status) will appear here.</p>
            </div>
        <?php elseif (!empty($movies)): ?>
            <div style="text-align: center; padding: 40px; background: rgba(0,0,0,0.2); border-radius: 10px;">
                <i class="fas fa-hand-pointer fa-3x" style="color: #2ecc71; margin-bottom: 15px;"></i>
                <p style="color: rgba(255,255,255,0.7);">Please select a movie from the dropdown above to view verified tickets.</p>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 40px; background: rgba(0,0,0,0.2); border-radius: 10px;">
                <i class="fas fa-ticket-alt fa-3x" style="color: #f39c12; margin-bottom: 15px;"></i>
                <p style="color: rgba(255,255,255,0.7);">No verified tickets available.</p>
                <p style="color: rgba(255,255,255,0.4); font-size: 0.9rem;">Customers need to check in first before tickets can be printed.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<style media="print">
    @page {
        size: A4;
        margin: 10mm;
    }
    
    body * {
        visibility: hidden;
    }
    
    .ticket-card, .ticket-card * {
        visibility: visible;
    }
    
    .ticket-card {
        page-break-inside: avoid;
        break-inside: avoid;
        margin-bottom: 15px;
    }
    
    .staff-header, .staff-content > div > div:first-child, 
    .staff-content > div > div:nth-child(2), 
    .staff-content > div > div:last-child > div:first-child,
    .staff-content > div > div:last-child > div:last-child > button,
    .staff-content > div > div:last-child > div:first-child > div:first-child > div:first-child,
    .staff-content > div > div:last-child > div:first-child > button {
        display: none;
    }
    
    .ticket-group {
        margin-bottom: 20px;
        background: none !important;
        padding: 0 !important;
    }
    
    .ticket-group > div:first-child {
        display: none;
    }
    
    .ticket-group > div:last-child {
        display: none;
    }
    
    .staff-content > div > div:last-child > div:last-child {
        display: none;
    }
</style>

<style>
.ticket-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.ticket-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.3);
}

.btn-print-all {
    transition: all 0.3s ease;
}

.btn-print-all:hover {
    transform: translateY(-2px);
}

@media (max-width: 768px) {
    .staff-container {
        padding: 15px;
    }
    
    .ticket-card {
        min-width: 100%;
    }
    
    .ticket-card > div {
        flex-direction: column;
    }
    
    .ticket-card > div > div:first-child {
        width: 100%;
        flex-direction: row;
        padding: 10px;
    }
    
    .ticket-card > div > div:first-child > div:last-child {
        border-top: none;
        border-left: 2px dashed rgba(255,255,255,0.3);
        margin-top: 0;
        margin-left: 10px;
        padding-top: 0;
        padding-left: 10px;
    }
}

select, button {
    transition: all 0.3s ease;
}

select:focus, button:focus {
    outline: none;
}

select:hover, button:hover {
    transform: translateY(-2px);
}
</style>

<script>
// Refresh page function
function refreshPage() {
    location.reload();
}

function printAllTickets() {
    window.print();
}

function printCustomerTickets(bookingRef) {
    // Hide all other ticket groups
    const allGroups = document.querySelectorAll('.ticket-group');
    let targetGroup = null;
    
    allGroups.forEach(group => {
        if (group.innerHTML.includes(bookingRef)) {
            targetGroup = group;
        }
    });
    
    if (targetGroup) {
        // Create a print-only clone
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Print Tickets - ${escapeHtml(bookingRef)}</title>
                <style>
                    @page {
                        size: A4;
                        margin: 10mm;
                    }
                    body {
                        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                        padding: 20px;
                        background: white;
                    }
                    .ticket-card {
                        margin-bottom: 20px;
                        page-break-inside: avoid;
                        break-inside: avoid;
                        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                    }
                    .ticket-card > div {
                        display: flex;
                    }
                    .ticket-card .stub {
                        width: 80px;
                        background: linear-gradient(135deg, #e23020 0%, #c11b18 100%);
                        color: white;
                        padding: 15px 10px;
                        text-align: center;
                    }
                    .ticket-card .main-body {
                        flex: 1;
                        padding: 15px;
                    }
                    .hand-mark {
                        background: #f0f0f0;
                        border-radius: 8px;
                        padding: 8px;
                        margin-bottom: 12px;
                        text-align: center;
                        border: 2px solid #2ecc71;
                    }
                    .hand-mark-inner {
                        background: #2ecc71;
                        color: #1a1a2e;
                        padding: 5px 10px;
                        border-radius: 5px;
                        display: inline-block;
                        font-weight: 800;
                        font-size: 0.8rem;
                    }
                    @media (max-width: 600px) {
                        .ticket-card > div {
                            flex-direction: column;
                        }
                        .ticket-card .stub {
                            width: 100%;
                            flex-direction: row;
                        }
                    }
                </style>
            </head>
            <body>
                ${targetGroup.outerHTML}
                <script>
                    window.onload = function() {
                        window.print();
                        setTimeout(() => { window.close(); }, 500);
                    }
                <\/script>
            </body>
            </html>
        `);
        printWindow.document.close();
    }
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

document.getElementById('movieSelect')?.addEventListener('change', function() {
    if (this.value) {
        this.form.submit();
    }
});

// Keyboard shortcut for refresh (Ctrl+R)
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
        e.preventDefault();
        refreshPage();
    }
});
</script>

