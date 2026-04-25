<?php
$root_dir = dirname(dirname(__DIR__));
require_once $root_dir . '/includes/config.php';
require_once $root_dir . '/includes/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Admin') {
    header("Location: " . SITE_URL . "index.php?page=login");
    exit();
}

require_once $root_dir . '/partials/admin-header.php';

$conn = get_db_connection();

// ============================================
// STATISTICS (Updated with venue separation)
// ============================================

// Movie statistics
$movie_count = 0;
$result = $conn->query("SELECT COUNT(*) as count FROM movies WHERE is_active = 1");
if ($result) $movie_count = $result->fetch_assoc()['count'];

// User statistics
$user_count = 0;
$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE u_status = 'Active'");
if ($result) $user_count = $result->fetch_assoc()['count'];

// Booking statistics
$booking_count = 0;
$result = $conn->query("SELECT COUNT(*) as count FROM tbl_booking WHERE status = 'Ongoing'");
if ($result) $booking_count = $result->fetch_assoc()['count'];

// Schedule statistics
$schedule_count = 0;
$result = $conn->query("SELECT COUNT(*) as count FROM movie_schedules WHERE is_active = 1");
if ($result) $schedule_count = $result->fetch_assoc()['count'];

// Suggestion statistics
$suggestion_count = 0;
$result = $conn->query("SELECT COUNT(*) as count FROM suggestions WHERE status = 'Pending'");
if ($result) $suggestion_count = $result->fetch_assoc()['count'];

// Payment statistics
$pending_payments = 0;
$result = $conn->query("SELECT COUNT(*) as count FROM manual_payments WHERE status = 'Pending'");
if ($result) $pending_payments = $result->fetch_assoc()['count'];

// ============================================
// NEW VENUE STATISTICS
// ============================================
$venue_count = 0;
$result = $conn->query("SELECT COUNT(*) as count FROM venues");
if ($result) $venue_count = $result->fetch_assoc()['count'];

// Venues with movies (active venues)
$active_venues_count = 0;
$result = $conn->query("
    SELECT COUNT(DISTINCT v.id) as count 
    FROM venues v
    INNER JOIN movies m ON v.id = m.venue_id 
    WHERE m.is_active = 1
");
if ($result) $active_venues_count = $result->fetch_assoc()['count'];

// ============================================
// RECENT MOVIES (with venue information)
// ============================================
$recent_movies = [];
$result = $conn->query("
    SELECT m.*, 
           v.venue_name, 
           v.venue_location,
           u.u_name as added_by_name
    FROM movies m
    LEFT JOIN venues v ON m.venue_id = v.id
    LEFT JOIN users u ON m.added_by = u.u_id
    WHERE m.is_active = 1
    ORDER BY m.created_at DESC
    LIMIT 5
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recent_movies[] = $row;
    }
}

// ============================================
// RECENT BOOKINGS (with venue information)
// ============================================
$recent_bookings = [];
$result = $conn->query("
    SELECT 
        b.*,
        u.u_name as customer_name,
        v.venue_name,
        v.venue_location,
        GROUP_CONCAT(bs.seat_number ORDER BY bs.seat_number SEPARATOR ', ') as seat_list,
        COUNT(bs.id) as total_seats
    FROM tbl_booking b
    LEFT JOIN users u ON b.u_id = u.u_id
    LEFT JOIN venues v ON b.venue_id = v.id
    LEFT JOIN booked_seats bs ON b.b_id = bs.booking_id
    WHERE b.status = 'Ongoing'
    GROUP BY b.b_id
    ORDER BY b.booking_date DESC
    LIMIT 5
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recent_bookings[] = $row;
    }
}

// ============================================
// TOP VENUES BY MOVIE COUNT (NEW)
// ============================================
$top_venues = [];
$result = $conn->query("
    SELECT 
        v.id,
        v.venue_name,
        v.venue_location,
        COUNT(m.id) as movie_count,
        SUM(CASE WHEN m.is_active = 1 THEN 1 ELSE 0 END) as active_movies
    FROM venues v
    LEFT JOIN movies m ON v.id = m.venue_id
    GROUP BY v.id
    ORDER BY movie_count DESC
    LIMIT 5
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $top_venues[] = $row;
    }
}

// ============================================
// VENUE ACTIVITY (Fixed - removed invalid schedule_id reference)
// Shows per venue using proper joins with tbl_booking
// ============================================
$venue_activity = [];
$result = $conn->query("
    SELECT 
        v.id,
        v.venue_name,
        COUNT(DISTINCT ms.id) as total_schedules,
        COUNT(DISTINCT b.b_id) as total_bookings,
        COUNT(DISTINCT CASE WHEN b.payment_status = 'Paid' THEN b.b_id END) as paid_bookings
    FROM venues v
    LEFT JOIN movies m ON v.id = m.venue_id
    LEFT JOIN movie_schedules ms ON m.id = ms.movie_id AND ms.is_active = 1
    LEFT JOIN tbl_booking b ON b.movie_name = ms.movie_title 
        AND b.show_date = ms.show_date 
        AND b.showtime = ms.showtime
    GROUP BY v.id
    ORDER BY total_bookings DESC
    LIMIT 5
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $venue_activity[] = $row;
    }
}

$conn->close();
?>

<div class="admin-content" style="max-width: 1400px; margin: 0 auto; padding: 30px;">
    <!-- Welcome Section -->
    <div style="text-align: center; margin-bottom: 40px; padding: 30px; background: linear-gradient(135deg, rgba(52, 152, 219, 0.1), rgba(41, 128, 185, 0.2)); border-radius: 20px; border: 2px solid rgba(52, 152, 219, 0.3);">
        <h1 style="color: white; font-size: 2.5rem; margin-bottom: 10px; font-weight: 800;">
            Welcome, <?php echo $_SESSION['user_name']; ?>!
        </h1>
        <p style="color: rgba(255,255,255,0.8); font-size: 1.1rem;">
            Administrator Dashboard - Movie Ticket Booking System
        </p>
    </div>

    <!-- Main Statistics Cards -->
    <div class="admin-stats" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 20px; margin-bottom: 40px;">
        <div class="stat-card" style="background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); padding: 20px; border-radius: 12px; text-align: center; border: 1px solid rgba(52, 152, 219, 0.3); transition: all 0.3s ease;">
            <div style="font-size: 2rem; color: #3498db; margin-bottom: 10px;"><i class="fas fa-film"></i></div>
            <div style="font-size: 2rem; font-weight: 800; color: white;"><?php echo $movie_count; ?></div>
            <div style="color: rgba(255,255,255,0.8); font-size: 0.9rem;">Active Movies</div>
        </div>
        
        <div class="stat-card" style="background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); padding: 20px; border-radius: 12px; text-align: center; border: 1px solid rgba(52, 152, 219, 0.3); transition: all 0.3s ease;">
            <div style="font-size: 2rem; color: #3498db; margin-bottom: 10px;"><i class="fas fa-users"></i></div>
            <div style="font-size: 2rem; font-weight: 800; color: white;"><?php echo $user_count; ?></div>
            <div style="color: rgba(255,255,255,0.8); font-size: 0.9rem;">Active Users</div>
        </div>
        
        <div class="stat-card" style="background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); padding: 20px; border-radius: 12px; text-align: center; border: 1px solid rgba(52, 152, 219, 0.3); transition: all 0.3s ease;">
            <div style="font-size: 2rem; color: #3498db; margin-bottom: 10px;"><i class="fas fa-ticket-alt"></i></div>
            <div style="font-size: 2rem; font-weight: 800; color: white;"><?php echo $booking_count; ?></div>
            <div style="color: rgba(255,255,255,0.8); font-size: 0.9rem;">Ongoing Bookings</div>
        </div>
        
        <div class="stat-card" style="background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); padding: 20px; border-radius: 12px; text-align: center; border: 1px solid rgba(52, 152, 219, 0.3); transition: all 0.3s ease;">
            <div style="font-size: 2rem; color: #3498db; margin-bottom: 10px;"><i class="fas fa-calendar-alt"></i></div>
            <div style="font-size: 2rem; font-weight: 800; color: white;"><?php echo $schedule_count; ?></div>
            <div style="color: rgba(255,255,255,0.8); font-size: 0.9rem;">Active Schedules</div>
        </div>
        
        <!-- NEW: Venue Statistics Card -->
        <div class="stat-card" style="background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); padding: 20px; border-radius: 12px; text-align: center; border: 1px solid rgba(52, 152, 219, 0.3); transition: all 0.3s ease;">
            <div style="font-size: 2rem; color: #3498db; margin-bottom: 10px;"><i class="fas fa-building"></i></div>
            <div style="font-size: 2rem; font-weight: 800; color: white;"><?php echo $venue_count; ?></div>
            <div style="color: rgba(255,255,255,0.8); font-size: 0.9rem;">Total Venues</div>
        </div>
        
        <div class="stat-card" style="background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); padding: 20px; border-radius: 12px; text-align: center; border: 1px solid rgba(52, 152, 219, 0.3); transition: all 0.3s ease;">
            <div style="font-size: 2rem; color: #3498db; margin-bottom: 10px;"><i class="fas fa-credit-card"></i></div>
            <div style="font-size: 2rem; font-weight: 800; color: white;"><?php echo $pending_payments; ?></div>
            <div style="color: rgba(255,255,255,0.8); font-size: 0.9rem;">Pending Payments</div>
            <?php if ($pending_payments > 0): ?>
            <div style="position: absolute; top: 5px; right: 5px; background: #f39c12; color: white; padding: 2px 8px; border-radius: 20px; font-size: 0.7rem; font-weight: 700; animation: pulse 1.5s infinite;">
                <?php echo $pending_payments; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="admin-actions" style="margin-bottom: 40px;">
        <h2 style="color: white; margin-bottom: 20px; font-size: 1.5rem; font-weight: 700;">
            <i class="fas fa-bolt"></i> Quick Actions
        </h2>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px;">
            <a href="<?php echo SITE_URL; ?>index.php?page=admin/manage-movies" 
               class="admin-action-btn" style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); padding: 20px; border-radius: 10px; text-decoration: none; text-align: center; color: white; font-weight: 600; transition: all 0.3s ease; display: flex; flex-direction: column; align-items: center; gap: 10px;">
                <i class="fas fa-film fa-2x"></i>
                <span>Manage Movies</span>
            </a>
            
            <a href="<?php echo SITE_URL; ?>index.php?page=admin/manage-schedules" 
               class="admin-action-btn" style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); padding: 20px; border-radius: 10px; text-decoration: none; text-align: center; color: white; font-weight: 600; transition: all 0.3s ease; display: flex; flex-direction: column; align-items: center; gap: 10px;">
                <i class="fas fa-calendar-alt fa-2x"></i>
                <span>Manage Schedules</span>
            </a>
            
            <!-- NEW: Manage Venues Button -->
            <a href="<?php echo SITE_URL; ?>index.php?page=admin/manage-venues" 
               class="admin-action-btn" style="background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%); padding: 20px; border-radius: 10px; text-decoration: none; text-align: center; color: white; font-weight: 600; transition: all 0.3s ease; display: flex; flex-direction: column; align-items: center; gap: 10px;">
                <i class="fas fa-building fa-2x"></i>
                <span>Manage Venues</span>
                <?php if ($venue_count > 0): ?>
                <span style="background: white; color: #2ecc71; padding: 2px 8px; border-radius: 12px; font-size: 0.7rem; margin-top: 5px;"><?php echo $venue_count; ?> venues</span>
                <?php endif; ?>
            </a>
            
            <a href="<?php echo SITE_URL; ?>index.php?page=admin/manage-users" 
               class="admin-action-btn" style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); padding: 20px; border-radius: 10px; text-decoration: none; text-align: center; color: white; font-weight: 600; transition: all 0.3s ease; display: flex; flex-direction: column; align-items: center; gap: 10px;">
                <i class="fas fa-users fa-2x"></i>
                <span>Manage Users</span>
            </a>

            <a href="<?php echo SITE_URL; ?>index.php?page=admin/manage-payments" 
               class="admin-action-btn" style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); padding: 20px; border-radius: 10px; text-decoration: none; text-align: center; color: white; font-weight: 600; transition: all 0.3s ease; display: flex; flex-direction: column; align-items: center; gap: 10px; position: relative;">
                <i class="fas fa-credit-card fa-2x"></i>
                <span>Manage Payments</span>
                <?php if ($pending_payments > 0): ?>
                <div style="position: absolute; top: -5px; right: -5px; background: #f39c12; color: white; padding: 2px 8px; border-radius: 20px; font-size: 0.7rem; font-weight: 700;">
                    <?php echo $pending_payments; ?>
                </div>
                <?php endif; ?>
            </a>
            
            <a href="<?php echo SITE_URL; ?>" 
               class="admin-action-btn" style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); padding: 20px; border-radius: 10px; text-decoration: none; text-align: center; color: white; font-weight: 600; transition: all 0.3s ease; display: flex; flex-direction: column; align-items: center; gap: 10px;">
                <i class="fas fa-globe fa-2x"></i>
                <span>View Site</span>
            </a>
        </div>
    </div>

    <!-- Recent Activity & Venue Stats Section -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(500px, 1fr)); gap: 30px; margin-bottom: 40px;">
        
        <!-- Recent Movies -->
        <div class="recent-movies" style="background: rgba(255, 255, 255, 0.05); border-radius: 12px; padding: 20px; border: 1px solid rgba(52, 152, 219, 0.2);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h3 style="color: white; font-size: 1.2rem; font-weight: 700;">
                    <i class="fas fa-film"></i> Recent Movies
                </h3>
                <a href="<?php echo SITE_URL; ?>index.php?page=admin/manage-movies" 
                   style="color: #3498db; text-decoration: none; font-weight: 600; font-size: 0.85rem;">
                    View All <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            
            <?php if (empty($recent_movies)): ?>
                <div style="text-align: center; padding: 30px; color: rgba(255, 255, 255, 0.5);">
                    <i class="fas fa-film fa-2x" style="margin-bottom: 10px; opacity: 0.5;"></i>
                    <p>No movies added yet</p>
                </div>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <?php foreach ($recent_movies as $movie): ?>
                    <div style="display: flex; align-items: center; gap: 15px; padding: 12px; background: rgba(255, 255, 255, 0.03); border-radius: 8px; border: 1px solid rgba(255, 255, 255, 0.05); transition: all 0.2s ease;">
                        <?php if (!empty($movie['poster_url'])): ?>
                        <img src="<?php echo $movie['poster_url']; ?>" 
                             alt="<?php echo htmlspecialchars($movie['title']); ?>"
                             style="width: 50px; height: 70px; object-fit: cover; border-radius: 5px;">
                        <?php else: ?>
                        <div style="width: 50px; height: 70px; background: rgba(52, 152, 219, 0.1); border-radius: 5px; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-film" style="color: rgba(255, 255, 255, 0.3);"></i>
                        </div>
                        <?php endif; ?>
                        
                        <div style="flex: 1;">
                            <div style="color: white; font-weight: 600; margin-bottom: 3px;">
                                <?php echo htmlspecialchars($movie['title']); ?>
                            </div>
                            <div style="display: flex; gap: 12px; font-size: 0.75rem;">
                                <span style="color: rgba(255, 255, 255, 0.6);">
                                    <i class="fas fa-tag"></i> <?php echo $movie['genre'] ?? 'N/A'; ?>
                                </span>
                                <!-- NEW: Show venue name -->
                                <span style="color: #3498db;">
                                    <i class="fas fa-building"></i> <?php echo htmlspecialchars($movie['venue_name'] ?? 'No venue'); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div style="text-align: right;">
                            <a href="<?php echo SITE_URL; ?>index.php?page=admin/manage-movies&edit=<?php echo $movie['id']; ?>" 
                               style="color: #3498db; text-decoration: none; font-size: 0.8rem;">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- NEW: Top Venues by Movie Count -->
        <div class="top-venues" style="background: rgba(255, 255, 255, 0.05); border-radius: 12px; padding: 20px; border: 1px solid rgba(46, 204, 113, 0.2);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h3 style="color: white; font-size: 1.2rem; font-weight: 700;">
                    <i class="fas fa-building"></i> Top Venues by Movies
                </h3>
                <a href="<?php echo SITE_URL; ?>index.php?page=admin/manage-venues" 
                   style="color: #2ecc71; text-decoration: none; font-weight: 600; font-size: 0.85rem;">
                    Manage Venues <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            
            <?php if (empty($top_venues)): ?>
                <div style="text-align: center; padding: 30px; color: rgba(255, 255, 255, 0.5);">
                    <i class="fas fa-building fa-2x" style="margin-bottom: 10px; opacity: 0.5;"></i>
                    <p>No venues added yet</p>
                    <a href="<?php echo SITE_URL; ?>index.php?page=admin/manage-venues" style="color: #2ecc71; font-size: 0.85rem;">
                        Add your first venue
                    </a>
                </div>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <?php foreach ($top_venues as $venue): ?>
                    <div style="display: flex; align-items: center; gap: 15px; padding: 12px; background: rgba(255, 255, 255, 0.03); border-radius: 8px; border: 1px solid rgba(255, 255, 255, 0.05);">
                        <div style="width: 45px; height: 45px; background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%); border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-building" style="color: white; font-size: 1.2rem;"></i>
                        </div>
                        
                        <div style="flex: 1;">
                            <div style="color: white; font-weight: 600; margin-bottom: 3px;">
                                <?php echo htmlspecialchars($venue['venue_name']); ?>
                            </div>
                            <div style="font-size: 0.75rem; color: rgba(255, 255, 255, 0.6);">
                                <?php echo htmlspecialchars(substr($venue['venue_location'], 0, 50)); ?>...
                            </div>
                        </div>
                        
                        <div style="text-align: right;">
                            <div style="background: rgba(46, 204, 113, 0.2); color: #2ecc71; padding: 5px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 700;">
                                <?php echo $venue['movie_count']; ?> movie<?php echo $venue['movie_count'] != 1 ? 's' : ''; ?>
                            </div>
                            <?php if ($venue['active_movies'] > 0): ?>
                            <div style="font-size: 0.65rem; color: #2ecc71; margin-top: 3px;"><?php echo $venue['active_movies']; ?> active</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Second Row: Recent Bookings & Venue Activity -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(500px, 1fr)); gap: 30px; margin-bottom: 40px;">
        
        <!-- Recent Bookings (Updated with venue) -->
        <div class="recent-bookings" style="background: rgba(255, 255, 255, 0.05); border-radius: 12px; padding: 20px; border: 1px solid rgba(52, 152, 219, 0.2);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h3 style="color: white; font-size: 1.2rem; font-weight: 700;">
                    <i class="fas fa-ticket-alt"></i> Recent Bookings
                </h3>
                <a href="<?php echo SITE_URL; ?>index.php?page=admin/manage-payments" 
                   style="color: #3498db; text-decoration: none; font-weight: 600; font-size: 0.85rem;">
                    View All <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            
            <?php if (empty($recent_bookings)): ?>
                <div style="text-align: center; padding: 30px; color: rgba(255, 255, 255, 0.5);">
                    <i class="fas fa-ticket-alt fa-2x" style="margin-bottom: 10px; opacity: 0.5;"></i>
                    <p>No recent bookings</p>
                </div>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <?php foreach ($recent_bookings as $booking): 
                        $seat_list = $booking['seat_list'] ?? 'No seats';
                    ?>
                    <div style="padding: 12px; background: rgba(255, 255, 255, 0.03); border-radius: 8px; border: 1px solid rgba(255, 255, 255, 0.05);">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                            <div style="color: white; font-weight: 600;">
                                <?php echo htmlspecialchars($booking['movie_name'] ?? 'Unknown Movie'); ?>
                            </div>
                            <span style="background: #2ecc71; color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.7rem; font-weight: 600;">
                                <?php echo $booking['status'] ?? 'Ongoing'; ?>
                            </span>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; font-size: 0.75rem;">
                            <div style="color: rgba(255, 255, 255, 0.6);">
                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($booking['customer_name'] ?? 'Unknown'); ?>
                            </div>
                            <div style="color: rgba(255, 255, 255, 0.6);">
                                <i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($booking['show_date'] ?? 'now')); ?>
                            </div>
                            <div style="color: rgba(255, 255, 255, 0.6);">
                                <i class="fas fa-clock"></i> <?php echo date('h:i A', strtotime($booking['showtime'] ?? '00:00:00')); ?>
                            </div>
                            <!-- NEW: Show venue in booking -->
                            <div style="color: #3498db;">
                                <i class="fas fa-building"></i> <?php echo htmlspecialchars($booking['venue_name'] ?? 'No venue'); ?>
                            </div>
                            <div style="color: #2ecc71; grid-column: span 2;">
                                <i class="fas fa-chair"></i> Seats: <?php echo htmlspecialchars($seat_list); ?> (<?php echo $booking['total_seats'] ?? 0; ?>)
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- NEW: Venue Activity (Schedules & Bookings per Venue) - FIXED QUERY -->
        <div class="venue-activity" style="background: rgba(255, 255, 255, 0.05); border-radius: 12px; padding: 20px; border: 1px solid rgba(241, 196, 15, 0.2);">
            <h3 style="color: white; font-size: 1.2rem; margin-bottom: 15px; font-weight: 700;">
                <i class="fas fa-chart-line"></i> Venue Activity
            </h3>
            
            <?php if (empty($venue_activity)): ?>
                <div style="text-align: center; padding: 30px; color: rgba(255, 255, 255, 0.5);">
                    <i class="fas fa-chart-line fa-2x" style="margin-bottom: 10px; opacity: 0.5;"></i>
                    <p>No venue activity data</p>
                </div>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 15px;">
                    <?php foreach ($venue_activity as $venue): ?>
                    <div style="padding: 12px; background: rgba(255, 255, 255, 0.03); border-radius: 8px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <div style="color: white; font-weight: 600;">
                                <?php echo htmlspecialchars($venue['venue_name']); ?>
                            </div>
                            <span style="background: rgba(241, 196, 15, 0.2); color: #f39c12; padding: 2px 8px; border-radius: 12px; font-size: 0.7rem; font-weight: 600;">
                                <?php echo $venue['total_schedules']; ?> schedules
                            </span>
                        </div>
                        
                        <div style="display: flex; gap: 15px; margin-top: 8px;">
                            <div style="flex: 1; text-align: center; padding: 8px; background: rgba(52, 152, 219, 0.1); border-radius: 6px;">
                                <div style="font-size: 1.1rem; font-weight: 700; color: #3498db;"><?php echo $venue['total_bookings']; ?></div>
                                <div style="font-size: 0.7rem; color: rgba(255,255,255,0.6);">Total Bookings</div>
                            </div>
                            <div style="flex: 1; text-align: center; padding: 8px; background: rgba(46, 204, 113, 0.1); border-radius: 6px;">
                                <div style="font-size: 1.1rem; font-weight: 700; color: #2ecc71;"><?php echo $venue['paid_bookings']; ?></div>
                                <div style="font-size: 0.7rem; color: rgba(255,255,255,0.6);">Paid Bookings</div>
                            </div>
                        </div>
                        
                        <?php 
                        $booking_percentage = $venue['total_bookings'] > 0 ? round(($venue['paid_bookings'] / $venue['total_bookings']) * 100) : 0;
                        ?>
                        <div style="margin-top: 8px;">
                            <div style="background: rgba(255,255,255,0.1); height: 4px; border-radius: 2px; overflow: hidden;">
                                <div style="background: #2ecc71; height: 100%; width: <?php echo $booking_percentage; ?>%;"></div>
                            </div>
                            <div style="font-size: 0.65rem; color: rgba(255,255,255,0.5); text-align: right; margin-top: 3px;">
                                <?php echo $booking_percentage; ?>% paid
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- System Information Section -->
    <div class="system-info" style="background: rgba(255, 255, 255, 0.05); border-radius: 12px; padding: 20px; border: 1px solid rgba(52, 152, 219, 0.2);">
        <h3 style="color: white; margin-bottom: 15px; font-size: 1.2rem; font-weight: 700;">
            <i class="fas fa-info-circle"></i> System Information
        </h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
            <div>
                <h4 style="color: #3498db; margin-bottom: 8px; font-size: 0.9rem;">Server Information</h4>
                <div style="background: rgba(255, 255, 255, 0.03); padding: 12px; border-radius: 8px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                        <span style="color: rgba(255, 255, 255, 0.6);">PHP Version:</span>
                        <span style="color: white; font-weight: 600;"><?php echo phpversion(); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                        <span style="color: rgba(255, 255, 255, 0.6);">Server Time:</span>
                        <span style="color: white; font-weight: 600;"><?php echo date('Y-m-d H:i:s'); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: rgba(255, 255, 255, 0.6);">Session:</span>
                        <span style="color: #2ecc71; font-weight: 600;">Active</span>
                    </div>
                </div>
            </div>
            
            <!-- NEW: Database Statistics -->
            <div>
                <h4 style="color: #3498db; margin-bottom: 8px; font-size: 0.9rem;">Database Statistics</h4>
                <div style="background: rgba(255, 255, 255, 0.03); padding: 12px; border-radius: 8px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                        <span style="color: rgba(255, 255, 255, 0.6);">Movies:</span>
                        <span style="color: white; font-weight: 600;"><?php echo $movie_count; ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                        <span style="color: rgba(255, 255, 255, 0.6);">Venues:</span>
                        <span style="color: white; font-weight: 600;"><?php echo $venue_count; ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: rgba(255, 255, 255, 0.6);">Active Venues:</span>
                        <span style="color: #2ecc71; font-weight: 600;"><?php echo $active_venues_count; ?></span>
                    </div>
                </div>
            </div>
            
            <div>
                <h4 style="color: #3498db; margin-bottom: 8px; font-size: 0.9rem;">Quick Links</h4>
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px;">
                    <a href="<?php echo SITE_URL; ?>" target="_blank"
                       style="background: rgba(255, 255, 255, 0.1); color: white; text-decoration: none; padding: 8px; border-radius: 6px; text-align: center; font-size: 0.8rem; transition: all 0.2s ease;">
                        <i class="fas fa-globe"></i> Public Site
                    </a>
                    <a href="<?php echo SITE_URL; ?>index.php?page=admin/manage-venues" 
                       style="background: rgba(46, 204, 113, 0.2); color: #2ecc71; text-decoration: none; padding: 8px; border-radius: 6px; text-align: center; font-size: 0.8rem; transition: all 0.2s ease;">
                        <i class="fas fa-building"></i> Manage Venues
                    </a>
                    <a href="<?php echo SITE_URL; ?>index.php?page=admin/manage-movies" 
                       style="background: rgba(52, 152, 219, 0.2); color: #3498db; text-decoration: none; padding: 8px; border-radius: 6px; text-align: center; font-size: 0.8rem; transition: all 0.2s ease;">
                        <i class="fas fa-film"></i> Manage Movies
                    </a>
                    <a href="<?php echo SITE_URL; ?>index.php?page=logout" 
                       style="background: rgba(231, 76, 60, 0.2); color: #e74c3c; text-decoration: none; padding: 8px; border-radius: 6px; text-align: center; font-size: 0.8rem; transition: all 0.2s ease;">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .stat-card {
        position: relative;
        transition: all 0.3s ease;
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(52, 152, 219, 0.2);
        border-color: #3498db;
    }
    
    .admin-action-btn:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(52, 152, 219, 0.3);
    }
    
    .recent-movies div:hover,
    .recent-bookings div:hover,
    .top-venues div:hover,
    .venue-activity div:hover {
        background: rgba(255, 255, 255, 0.08);
        border-color: rgba(52, 152, 219, 0.3);
    }
    
    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.05); }
        100% { transform: scale(1); }
    }
    
    :root {
        --admin-primary: #2c3e50;
        --admin-secondary: #34495e;
        --admin-accent: #3498db;
        --admin-success: #2ecc71;
        --admin-danger: #e74c3c;
        --admin-warning: #f39c12;
        --admin-light: #ecf0f1;
        --admin-dark: #1a252f;
    }
    
    @media (max-width: 1100px) {
        .recent-activity {
            grid-template-columns: 1fr;
        }
    }
    
    @media (max-width: 768px) {
        .admin-stats {
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        
        .admin-actions > div {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .admin-content {
            padding: 15px;
        }
        
        h1 {
            font-size: 1.8rem !important;
        }
    }
    
    @media (max-width: 576px) {
        .admin-stats {
            grid-template-columns: 1fr;
        }
        
        .admin-actions > div {
            grid-template-columns: 1fr;
        }
        
        .system-info > div {
            grid-template-columns: 1fr;
        }
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Animate stat cards
        const statCards = document.querySelectorAll('.stat-card');
        statCards.forEach((card, index) => {
            card.style.animationDelay = `${index * 0.1}s`;
            card.style.animation = 'fadeInUp 0.5s ease forwards';
            card.style.opacity = '0';
        });
        
        // Animate action buttons
        const actionBtns = document.querySelectorAll('.admin-action-btn');
        actionBtns.forEach((btn, index) => {
            btn.style.animationDelay = `${index * 0.1}s`;
            btn.style.animation = 'fadeInUp 0.5s ease forwards';
            btn.style.opacity = '0';
        });
        
        // Add animation style
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fadeInUp {
                from {
                    opacity: 0;
                    transform: translateY(20px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            
            .stat-card,
            .admin-action-btn {
                transition: all 0.3s ease;
            }
        `;
        document.head.appendChild(style);
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === '1') {
                e.preventDefault();
                window.location.href = "<?php echo SITE_URL; ?>index.php?page=admin/manage-movies";
            }
            
            if (e.ctrlKey && e.key === '2') {
                e.preventDefault();
                window.location.href = "<?php echo SITE_URL; ?>index.php?page=admin/manage-schedules";
            }
            
            if (e.ctrlKey && e.key === '3') {
                e.preventDefault();
                window.location.href = "<?php echo SITE_URL; ?>index.php?page=admin/manage-venues";
            }
            
            if (e.ctrlKey && e.key === '4') {
                e.preventDefault();
                window.location.href = "<?php echo SITE_URL; ?>index.php?page=admin/manage-users";
            }
            
            if (e.ctrlKey && e.key === '5') {
                e.preventDefault();
                window.location.href = "<?php echo SITE_URL; ?>index.php?page=admin/manage-payments";
            }
            
            if (e.ctrlKey && e.key === 'l') {
                e.preventDefault();
                window.location.href = "<?php echo SITE_URL; ?>index.php?page=logout";
            }
        });
    });
</script>

</div>
</body>
</html>