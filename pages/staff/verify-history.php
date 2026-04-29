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
$selected_movie_title = '';
$selected_show_date = '';
$selected_show_time = '';

// ============================================
// Get all movies that have verified bookings (Present or Completed)
// Grouped by movie AND showtime using normalized schema
// ============================================
$movies_list = [];

$movies_stmt = $conn->prepare("
    SELECT DISTINCT 
        m.title as movie_name,
        s.showtime,
        s.show_date,
        CONCAT(m.title, '|', s.show_date, '|', s.showtime) as unique_key
    FROM bookings b
    JOIN schedules s ON b.schedule_id = s.id
    JOIN movies m ON s.movie_id = m.id
    WHERE b.attendance_status IN ('present', 'completed') 
    AND b.payment_status = 'paid'
    AND b.status IN ('ongoing', 'done')
    AND m.is_active = 1
    ORDER BY s.show_date DESC, s.showtime DESC, m.title
");
$movies_stmt->execute();
$movies_result = $movies_stmt->get_result();

while ($row = $movies_result->fetch_assoc()) {
    $unique_key = $row['unique_key'];
    $movies_list[$unique_key] = [
        'movie_name' => $row['movie_name'],
        'showtime' => $row['showtime'],
        'show_date' => $row['show_date']
    ];
}
$movies_stmt->close();

// ============================================
// Get verified bookings for selected movie
// Using normalized schema with proper joins
// ============================================
$verified_bookings = [];
if ($selected_movie) {
    // Parse selected movie to get name, date, and time
    $selected_parts = explode('|', $selected_movie);
    $selected_movie_title = $selected_parts[0] ?? '';
    $selected_show_date = $selected_parts[1] ?? '';
    $selected_show_time = $selected_parts[2] ?? '';
    
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
            m.genre,
            m.duration,
            m.rating,
            s.show_date,
            s.showtime,
            sc.id as screen_id,
            sc.screen_name,
            sc.screen_number,
            v.id as venue_id,
            v.venue_name,
            v.venue_location,
            a.u_name as verified_by_name,
            GROUP_CONCAT(DISTINCT bs.seat_number ORDER BY bs.seat_number SEPARATOR ', ') as seat_list,
            COUNT(DISTINCT bs.id) as total_seats,
            SUM(DISTINCT bs.price) as calculated_total
        FROM bookings b
        JOIN users u ON b.user_id = u.u_id
        JOIN schedules s ON b.schedule_id = s.id
        JOIN movies m ON s.movie_id = m.id
        JOIN screens sc ON s.screen_id = sc.id
        JOIN venues v ON sc.venue_id = v.id
        LEFT JOIN booked_seats bs ON b.id = bs.booking_id
        LEFT JOIN users a ON b.verified_by = a.u_id
        WHERE m.title = ? 
        AND s.show_date = ? 
        AND s.showtime = ? 
        AND b.attendance_status IN ('present', 'completed')
        AND b.payment_status = 'paid'
        AND b.status IN ('ongoing', 'done')
        GROUP BY b.id, b.booking_reference, b.total_amount, b.payment_status, b.attendance_status,
                 b.booked_at, b.verified_at, u.u_id, u.u_name, u.u_email,
                 m.id, m.title, m.poster_url, m.genre, m.duration, m.rating,
                 s.show_date, s.showtime, sc.id, sc.screen_name, sc.screen_number,
                 v.id, v.venue_name, v.venue_location, a.u_name
        ORDER BY b.verified_at DESC, b.booking_reference
    ");
    $bookings_stmt->bind_param("sss", $selected_movie_title, $selected_show_date, $selected_show_time);
    $bookings_stmt->execute();
    $bookings_result = $bookings_stmt->get_result();
    
    while ($row = $bookings_result->fetch_assoc()) {
        $verified_bookings[] = $row;
    }
    $bookings_stmt->close();
}

// Get statistics
$stats_stmt = $conn->prepare("
    SELECT 
        COUNT(CASE WHEN attendance_status = 'present' THEN 1 END) as total_present,
        COUNT(CASE WHEN attendance_status = 'completed' THEN 1 END) as total_completed,
        COUNT(*) as total_verified,
        COALESCE(SUM(total_amount), 0) as total_revenue
    FROM bookings 
    WHERE attendance_status IN ('present', 'completed')
    AND payment_status = 'paid'
");
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result->fetch_assoc();
$stats_stmt->close();

$conn->close();
?>

<div class="staff-container" style="max-width: 1400px; margin: 0 auto; padding: 30px;">
    <div style="background: rgba(255, 255, 255, 0.05); border-radius: 15px; padding: 30px; margin-bottom: 30px; border: 1px solid rgba(52, 152, 219, 0.2);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;">
            <div>
                <h2 style="color: white; font-size: 1.8rem; margin-bottom: 10px; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-history"></i> Verified Bookings History
                </h2>
                <p style="color: rgba(255, 255, 255, 0.7);">View all checked-in and completed paid bookings</p>
            </div>
            <button onclick="refreshPage()" style="background: #3498db; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-sync-alt"></i> Refresh Page
            </button>
        </div>

        <!-- Statistics Cards -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
            <div style="background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); padding: 20px; border-radius: 12px; text-align: center; border: 1px solid rgba(52, 152, 219, 0.3);">
                <div style="font-size: 2rem; color: #3498db; margin-bottom: 8px;"><i class="fas fa-ticket-alt"></i></div>
                <div style="font-size: 1.8rem; font-weight: 800; color: white;"><?php echo intval($stats['total_verified'] ?? 0); ?></div>
                <div style="color: rgba(255,255,255,0.8);">Total Verified</div>
            </div>
            
            <div style="background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); padding: 20px; border-radius: 12px; text-align: center; border: 1px solid rgba(46, 204, 113, 0.3);">
                <div style="font-size: 2rem; color: #2ecc71; margin-bottom: 8px;"><i class="fas fa-check-circle"></i></div>
                <div style="font-size: 1.8rem; font-weight: 800; color: white;"><?php echo intval($stats['total_present'] ?? 0); ?></div>
                <div style="color: rgba(255,255,255,0.8);">Checked In</div>
            </div>
            
            <div style="background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); padding: 20px; border-radius: 12px; text-align: center; border: 1px solid rgba(155, 89, 182, 0.3);">
                <div style="font-size: 2rem; color: #9b59b6; margin-bottom: 8px;"><i class="fas fa-check-double"></i></div>
                <div style="font-size: 1.8rem; font-weight: 800; color: white;"><?php echo intval($stats['total_completed'] ?? 0); ?></div>
                <div style="color: rgba(255,255,255,0.8);">Completed</div>
            </div>
            
            <div style="background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); padding: 20px; border-radius: 12px; text-align: center; border: 1px solid rgba(241, 196, 15, 0.3);">
                <div style="font-size: 2rem; color: #f1c40f; margin-bottom: 8px;"><i class="fas fa-chart-line"></i></div>
                <div style="font-size: 1.8rem; font-weight: 800; color: white;">₱<?php echo number_format($stats['total_revenue'] ?? 0, 2); ?></div>
                <div style="color: rgba(255,255,255,0.8);">Total Revenue</div>
            </div>
        </div>

        <!-- Movie Selection -->
        <div style="background: rgba(0, 0, 0, 0.2); border-radius: 10px; padding: 20px; margin-bottom: 25px;">
            <label style="display: block; color: white; font-weight: 600; margin-bottom: 10px;">
                <i class="fas fa-film"></i> Select Movie & Showtime
            </label>
            <form method="GET" action="" id="movieSelectForm">
                <input type="hidden" name="page" value="staff/verify-history">
                <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                    <select name="movie" required id="movieSelect" style="flex: 1; padding: 14px 16px; background: rgba(255,255,255,0.08); border: 2px solid rgba(52,152,219,0.3); border-radius: 10px; color: white; font-size: 1rem; cursor: pointer;">
                        <option value="" style="background: #2c3e50;">-- Select a movie --</option>
                        <?php 
                        // Sort movies by date and time (newest first)
                        uasort($movies_list, function($a, $b) {
                            $dateTimeA = strtotime($a['show_date'] . ' ' . $a['showtime']);
                            $dateTimeB = strtotime($b['show_date'] . ' ' . $b['showtime']);
                            return $dateTimeB - $dateTimeA;
                        });
                        
                        foreach ($movies_list as $unique_key => $movie): 
                            $display_key = $movie['movie_name'] . '|' . $movie['show_date'] . '|' . $movie['showtime'];
                            $is_today = date('Y-m-d') == $movie['show_date'];
                            $show_datetime = strtotime($movie['show_date'] . ' ' . $movie['showtime']);
                            $is_past = $show_datetime < time();
                        ?>
                            <option value="<?php echo htmlspecialchars($display_key); ?>" style="background: #2c3e50;" <?php echo $selected_movie == $display_key ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($movie['movie_name']); ?> - <?php echo date('h:i A', strtotime($movie['showtime'])); ?> (<?php echo date('M d, Y', strtotime($movie['show_date'])); ?>)
                                <?php if ($is_today): ?>(Today)<?php elseif ($is_past): ?>(Past)<?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" style="padding: 14px 30px; background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; border: none; border-radius: 10px; font-weight: 600; cursor: pointer;">
                        <i class="fas fa-search"></i> Load History
                    </button>
                </div>
            </form>
            
            <?php if (empty($movies_list)): ?>
                <div style="margin-top: 15px; padding: 12px; background: rgba(241, 196, 15, 0.1); border-left: 4px solid #f39c12; border-radius: 5px;">
                    <p style="color: #f39c12; font-size: 0.9rem;">
                        <i class="fas fa-info-circle"></i> No verified bookings found in the history.
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Verified Bookings Display -->
        <?php if ($selected_movie): ?>
            <div style="margin-top: 25px;">
                <h3 style="color: white; font-size: 1.3rem; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #2ecc71;">
                    <i class="fas fa-check-circle"></i> Verified Bookings - <?php echo htmlspecialchars($selected_movie_title); ?>
                    <span style="font-size: 0.9rem; color: #2ecc71; margin-left: 10px;"><?php echo date('h:i A', strtotime($selected_show_time)); ?> | <?php echo date('F d, Y', strtotime($selected_show_date)); ?></span>
                </h3>
                
                <!-- Live Search -->
                <div style="margin-bottom: 20px;">
                    <div style="position: relative;">
                        <i class="fas fa-search" style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: rgba(255,255,255,0.5);"></i>
                        <input type="text" id="liveSearch" placeholder="Search by booking reference, customer name, or seat number..." 
                               style="width: 100%; padding: 12px 15px 12px 45px; background: rgba(255,255,255,0.08); border: 2px solid rgba(46,204,113,0.3); border-radius: 10px; color: white; font-size: 1rem;">
                    </div>
                    <p style="color: rgba(255,255,255,0.5); font-size: 0.8rem; margin-top: 8px;">
                        <i class="fas fa-info-circle"></i> Type to filter bookings in real-time
                    </p>
                </div>
                
                <?php if (empty($verified_bookings)): ?>
                    <div style="text-align: center; padding: 50px; background: rgba(0,0,0,0.2); border-radius: 10px;">
                        <i class="fas fa-check-circle fa-3x" style="color: rgba(46,204,113,0.3); margin-bottom: 15px;"></i>
                        <p style="color: rgba(255,255,255,0.6);">No verified bookings found for this movie showtime.</p>
                    </div>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);">
                                    <th style="padding: 14px; text-align: left; color: white;">Booking Ref</th>
                                    <th style="padding: 14px; text-align: left; color: white;">Customer</th>
                                    <th style="padding: 14px; text-align: left; color: white;">Venue / Screen</th>
                                    <th style="padding: 14px; text-align: left; color: white;">Seats</th>
                                    <th style="padding: 14px; text-align: left; color: white;">Amount</th>
                                    <th style="padding: 14px; text-align: left; color: white;">Status</th>
                                    <th style="padding: 14px; text-align: left; color: white;">Verified At</th>
                                    <th style="padding: 14px; text-align: left; color: white;">Verified By</th>
                                    <th style="padding: 14px; text-align: left; color: white;">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="historyTableBody">
                                <?php foreach ($verified_bookings as $booking): 
                                    $status_text = $booking['attendance_status'] == 'present' ? 'Present' : 'Completed';
                                    $status_color = $booking['attendance_status'] == 'present' ? '#2ecc71' : '#3498db';
                                    $status_icon = $booking['attendance_status'] == 'present' ? 'fa-check-circle' : 'fa-check-double';
                                ?>
                                    <tr class="booking-row" 
                                        data-booking-id="<?php echo intval($booking['booking_id']); ?>"
                                        data-booking-ref="<?php echo htmlspecialchars($booking['booking_reference']); ?>"
                                        data-customer-name="<?php echo htmlspecialchars($booking['customer_name']); ?>"
                                        data-customer-email="<?php echo htmlspecialchars($booking['customer_email']); ?>"
                                        data-movie-name="<?php echo htmlspecialchars($booking['movie_title']); ?>"
                                        data-movie-genre="<?php echo htmlspecialchars($booking['genre'] ?? 'N/A'); ?>"
                                        data-movie-duration="<?php echo htmlspecialchars($booking['duration'] ?? 'N/A'); ?>"
                                        data-movie-rating="<?php echo htmlspecialchars($booking['rating'] ?? 'PG'); ?>"
                                        data-show-date="<?php echo htmlspecialchars($booking['show_date']); ?>"
                                        data-show-time="<?php echo date('h:i A', strtotime($booking['showtime'])); ?>"
                                        data-venue-name="<?php echo htmlspecialchars($booking['venue_name']); ?>"
                                        data-venue-location="<?php echo htmlspecialchars($booking['venue_location'] ?? ''); ?>"
                                        data-screen-name="<?php echo htmlspecialchars($booking['screen_name']); ?>"
                                        data-screen-number="<?php echo intval($booking['screen_number']); ?>"
                                        data-seat-list="<?php echo htmlspecialchars($booking['seat_list'] ?? 'N/A'); ?>"
                                        data-total-seats="<?php echo intval($booking['total_seats']); ?>"
                                        data-total-amount="<?php echo number_format($booking['total_amount'], 2); ?>"
                                        data-verified-at="<?php echo htmlspecialchars($booking['verified_at']); ?>"
                                        data-verified-by="<?php echo htmlspecialchars($booking['verified_by_name'] ?? 'System'); ?>"
                                        style="border-bottom: 1px solid rgba(255,255,255,0.1);">
                                        <td style="padding: 12px;">
                                            <span style="color: white; font-weight: 600; font-family: monospace;"><?php echo htmlspecialchars($booking['booking_reference']); ?></span>
                                         </div>
                                        </td>
                                        <td style="padding: 12px;">
                                            <div style="color: white; font-weight: 600;"><?php echo htmlspecialchars($booking['customer_name']); ?></div>
                                            <div style="color: rgba(255,255,255,0.6); font-size: 0.75rem;"><?php echo htmlspecialchars($booking['customer_email']); ?></div>
                                         </div>
                                        </td>
                                        <td style="padding: 12px;">
                                            <div style="color: white;"><?php echo htmlspecialchars($booking['venue_name']); ?></div>
                                            <div style="color: rgba(255,255,255,0.5); font-size: 0.7rem;"><?php echo htmlspecialchars($booking['screen_name']); ?> (#<?php echo $booking['screen_number']; ?>)</div>
                                         </div>
                                        </td>
                                        <td style="padding: 12px;">
                                            <span style="color: #3498db; font-size: 0.85rem;"><?php echo htmlspecialchars($booking['seat_list'] ?? 'N/A'); ?></span>
                                            <div style="color: rgba(255,255,255,0.5); font-size: 0.7rem;"><?php echo $booking['total_seats']; ?> seat(s)</div>
                                         </div>
                                        </td>
                                        <td style="padding: 12px;">
                                            <span style="color: #2ecc71; font-size: 1rem; font-weight: 700;">₱<?php echo number_format($booking['total_amount'], 2); ?></span>
                                         </div>
                                        </td>
                                        <td style="padding: 12px;">
                                            <span style="background: <?php echo $status_color; ?>20; color: <?php echo $status_color; ?>; padding: 5px 12px; border-radius: 15px; font-size: 0.75rem; font-weight: 600;">
                                                <i class="fas <?php echo $status_icon; ?>"></i> <?php echo $status_text; ?>
                                            </span>
                                         </div>
                                        </td>
                                        <td style="padding: 12px; color: rgba(255,255,255,0.7); font-size: 0.85rem;">
                                            <?php echo date('M d, h:i A', strtotime($booking['verified_at'])); ?>
                                         </div>
                                        </td>
                                        <td style="padding: 12px;">
                                            <span style="color: #2ecc71; font-size: 0.85rem;">
                                                <i class="fas fa-user-check"></i> <?php echo htmlspecialchars($booking['verified_by_name'] ?? 'System'); ?>
                                            </span>
                                         </div>
                                        </td>
                                        <td style="padding: 12px;">
                                            <button onclick="viewBookingDetails(this)" class="btn-view-details" 
                                                    style="background: #3498db; color: white; border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer; font-size: 0.75rem;">
                                                <i class="fas fa-eye"></i> View Details
                                            </button>
                                         </div>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div style="margin-top: 15px; text-align: center; color: rgba(255,255,255,0.5); font-size: 0.85rem;">
                        <span id="filterCount"><?php echo count($verified_bookings); ?></span> verified booking(s) displayed
                    </div>
                <?php endif; ?>
            </div>
        <?php elseif (!empty($movies_list)): ?>
            <div style="text-align: center; padding: 40px; background: rgba(0,0,0,0.2); border-radius: 10px;">
                <i class="fas fa-hand-pointer fa-3x" style="color: #2ecc71; margin-bottom: 15px;"></i>
                <p style="color: rgba(255,255,255,0.7);">Please select a movie from the dropdown above to view verified bookings.</p>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 40px; background: rgba(0,0,0,0.2); border-radius: 10px;">
                <i class="fas fa-history fa-3x" style="color: #3498db; margin-bottom: 15px;"></i>
                <p style="color: rgba(255,255,255,0.7);">No verified bookings found in the history.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- View Details Modal -->
<div id="detailsModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.95); z-index: 1000; justify-content: center; align-items: center; padding: 20px; overflow-y: auto;">
    <div style="background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); border-radius: 20px; padding: 30px; max-width: 750px; width: 100%; border: 2px solid #3498db;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #3498db;">
            <h2 style="color: #3498db;"><i class="fas fa-receipt"></i> Verified Booking Receipt</h2>
            <button onclick="closeDetailsModal()" style="background: none; border: none; color: white; font-size: 2rem; cursor: pointer;">&times;</button>
        </div>
        <div id="detailsModalContent"></div>
        <div style="text-align: center; margin-top: 25px;">
            <button onclick="closeDetailsModal()" class="btn btn-secondary" style="padding: 10px 25px; background: rgba(255,255,255,0.1); color: white; border: none; border-radius: 8px; cursor: pointer;">
                Close
            </button>
        </div>
    </div>
</div>

<style>
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.staff-container {
    animation: fadeIn 0.3s ease;
}

.booking-row {
    transition: background 0.2s ease;
}

.booking-row:hover {
    background: rgba(46, 204, 113, 0.05);
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

.receipt-detail-row {
    padding: 10px 0;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}

.receipt-label {
    color: #3498db;
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

.btn-secondary:hover {
    background: rgba(52, 152, 219, 0.3);
    transform: translateY(-2px);
}

@media (max-width: 992px) {
    .receipt-label {
        min-width: 120px;
    }
}

@media (max-width: 768px) {
    .staff-container {
        padding: 15px;
    }
    
    table {
        font-size: 0.75rem;
    }
    
    th, td {
        padding: 8px !important;
    }
    
    .receipt-label {
        min-width: 100px;
        display: block;
        margin-bottom: 5px;
    }
    
    .receipt-detail-row {
        margin-bottom: 10px;
    }
}
</style>

<script>
// Refresh page function
function refreshPage() {
    location.reload();
}

// Auto-submit when movie selection changes
document.getElementById('movieSelect')?.addEventListener('change', function() {
    if (this.value) {
        this.form.submit();
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
            const seats = row.querySelector('td:nth-child(4)')?.innerText.toLowerCase() || '';
            
            if (ref.includes(searchTerm) || customer.includes(searchTerm) || seats.includes(searchTerm) || searchTerm === '') {
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

// View Booking Details - Shows receipt-like information
function viewBookingDetails(button) {
    const row = button.closest('tr');
    
    const bookingData = {
        booking_ref: row.dataset.bookingRef,
        customer_name: row.dataset.customerName,
        customer_email: row.dataset.customerEmail,
        movie_name: row.dataset.movieName,
        movie_genre: row.dataset.movieGenre,
        movie_duration: row.dataset.movieDuration,
        movie_rating: row.dataset.movieRating,
        show_date: row.dataset.showDate,
        show_time: row.dataset.showTime,
        venue_name: row.dataset.venueName,
        venue_location: row.dataset.venueLocation,
        screen_name: row.dataset.screenName,
        screen_number: row.dataset.screenNumber,
        seat_list: row.dataset.seatList,
        total_seats: row.dataset.totalSeats,
        total_amount: row.dataset.totalAmount,
        verified_at: row.dataset.verifiedAt,
        verified_by: row.dataset.verifiedBy
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
            
            <div style="margin: 15px 0; border-top: 2px dashed rgba(255,255,255,0.1);"></div>
            
            <div class="receipt-detail-row">
                <span class="receipt-label">Movie:</span>
                <span class="receipt-value">${escapeHtml(bookingData.movie_name)}</span>
            </div>
            <div class="receipt-detail-row">
                <span class="receipt-label">Movie Details:</span>
                <span class="receipt-value">${escapeHtml(bookingData.movie_rating)} • ${escapeHtml(bookingData.movie_duration)} • ${escapeHtml(bookingData.movie_genre)}</span>
            </div>
            <div class="receipt-detail-row">
                <span class="receipt-label">Show Date:</span>
                <span class="receipt-value">${new Date(bookingData.show_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</span>
            </div>
            <div class="receipt-detail-row">
                <span class="receipt-label">Show Time:</span>
                <span class="receipt-value">${bookingData.show_time}</span>
            </div>
            
            <div style="margin: 15px 0; border-top: 2px dashed rgba(255,255,255,0.1);"></div>
            
            <div class="receipt-detail-row">
                <span class="receipt-label">Venue:</span>
                <span class="receipt-value">${escapeHtml(bookingData.venue_name)}</span>
            </div>
            ${bookingData.venue_location ? `
            <div class="receipt-detail-row">
                <span class="receipt-label">Venue Location:</span>
                <span class="receipt-value">${escapeHtml(bookingData.venue_location)}</span>
            </div>
            ` : ''}
            <div class="receipt-detail-row">
                <span class="receipt-label">Screen:</span>
                <span class="receipt-value">${escapeHtml(bookingData.screen_name)} (Screen #${bookingData.screen_number})</span>
            </div>
            
            <div style="margin: 15px 0; border-top: 2px dashed rgba(255,255,255,0.1);"></div>
            
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
                <span class="receipt-value" style="color: #2ecc71; font-weight: 800; font-size: 1.3rem;">₱${bookingData.total_amount}</span>
            </div>
            
            <div style="margin: 15px 0; border-top: 2px dashed rgba(255,255,255,0.1);"></div>
            
            <div class="receipt-detail-row">
                <span class="receipt-label">Payment Status:</span>
                <span class="receipt-value" style="color: #2ecc71;">✓ Paid</span>
            </div>
            <div class="receipt-detail-row">
                <span class="receipt-label">Attendance Status:</span>
                <span class="receipt-value" style="color: #2ecc71;">✓ Verified</span>
            </div>
            <div class="receipt-detail-row">
                <span class="receipt-label">Verified At:</span>
                <span class="receipt-value">${new Date(bookingData.verified_at).toLocaleString()}</span>
            </div>
            <div class="receipt-detail-row">
                <span class="receipt-label">Verified By:</span>
                <span class="receipt-value" style="color: #2ecc71;">${escapeHtml(bookingData.verified_by)}</span>
            </div>
        </div>
    `;
    
    document.getElementById('detailsModal').style.display = 'flex';
}

// Escape HTML to prevent XSS
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

