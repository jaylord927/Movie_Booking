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
// STATISTICS QUERIES
// ============================================

// Active Movies
$movie_count = 0;
$result = $conn->query("SELECT COUNT(*) as count FROM movies WHERE is_active = 1");
if ($result) $movie_count = $result->fetch_assoc()['count'];

// Active Users
$user_count = 0;
$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE u_status = 'Active'");
if ($result) $user_count = $result->fetch_assoc()['count'];

// Ongoing Bookings (not cancelled/expired)
$booking_count = 0;
$result = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE status = 'ongoing' AND payment_status = 'paid'");
if ($result) $booking_count = $result->fetch_assoc()['count'];

// Active Schedules
$schedule_count = 0;
$result = $conn->query("SELECT COUNT(*) as count FROM schedules WHERE is_active = 1 AND show_date >= CURDATE()");
if ($result) $schedule_count = $result->fetch_assoc()['count'];

// Pending Payments
$pending_payments = 0;
$result = $conn->query("SELECT COUNT(*) as count FROM manual_payments WHERE status = 'pending'");
if ($result) $pending_payments = $result->fetch_assoc()['count'];

// Total Venues
$venue_count = 0;
$result = $conn->query("SELECT COUNT(*) as count FROM venues WHERE is_active = 1");
if ($result) $venue_count = $result->fetch_assoc()['count'];

// Total Screens
$screen_count = 0;
$result = $conn->query("SELECT COUNT(*) as count FROM screens WHERE is_active = 1");
if ($result) $screen_count = $result->fetch_assoc()['count'];

// Total Seats Configured
$seats_count = 0;
$result = $conn->query("SELECT COUNT(*) as count FROM seat_plan_details WHERE is_enabled = 1");
if ($result) $seats_count = $result->fetch_assoc()['count'];

// Pending Suggestions
$suggestion_count = 0;
$result = $conn->query("SELECT COUNT(*) as count FROM suggestions WHERE status = 'Pending'");
if ($result) $suggestion_count = $result->fetch_assoc()['count'];

// Total Suggestions
$total_suggestions = 0;
$result = $conn->query("SELECT COUNT(*) as count FROM suggestions");
if ($result) $total_suggestions = $result->fetch_assoc()['count'];

// ============================================
// RECENT MOVIES
// ============================================
$recent_movies = [];
$result = $conn->query("
    SELECT m.*, u.u_name as added_by_name
    FROM movies m
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
// RECENT SCHEDULES
// ============================================
$recent_schedules = [];
$result = $conn->query("
    SELECT 
        s.id,
        s.show_date,
        s.showtime,
        s.base_price,
        m.title as movie_title,
        sc.screen_name,
        sc.screen_number,
        v.venue_name,
        COUNT(sa.id) as total_seats,
        COUNT(CASE WHEN sa.status = 'available' THEN 1 END) as available_seats,
        COUNT(CASE WHEN sa.status = 'booked' THEN 1 END) as booked_seats
    FROM schedules s
    JOIN movies m ON s.movie_id = m.id
    JOIN screens sc ON s.screen_id = sc.id
    JOIN venues v ON sc.venue_id = v.id
    LEFT JOIN seat_availability sa ON s.id = sa.schedule_id
    WHERE s.is_active = 1 AND s.show_date >= CURDATE()
    GROUP BY s.id, s.show_date, s.showtime, s.base_price, m.title, sc.screen_name, sc.screen_number, v.venue_name
    ORDER BY s.show_date ASC, s.showtime ASC
    LIMIT 5
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recent_schedules[] = $row;
    }
}

// ============================================
// RECENT BOOKINGS
// ============================================
$recent_bookings = [];
$result = $conn->query("
    SELECT 
        b.id,
        b.booking_reference,
        b.total_amount,
        b.payment_status,
        b.attendance_status,
        b.status,
        b.booked_at,
        u.u_name as customer_name,
        m.title as movie_title,
        s.show_date,
        s.showtime,
        sc.screen_name,
        v.venue_name,
        GROUP_CONCAT(DISTINCT bs.seat_number ORDER BY bs.seat_number SEPARATOR ', ') as seat_list,
        COUNT(DISTINCT bs.id) as total_seats
    FROM bookings b
    JOIN users u ON b.user_id = u.u_id
    JOIN schedules s ON b.schedule_id = s.id
    JOIN movies m ON s.movie_id = m.id
    JOIN screens sc ON s.screen_id = sc.id
    JOIN venues v ON sc.venue_id = v.id
    LEFT JOIN booked_seats bs ON b.id = bs.booking_id
    WHERE b.is_visible = 1
    GROUP BY b.id, b.booking_reference, b.total_amount, b.payment_status, b.attendance_status, b.status, b.booked_at, u.u_name, m.title, s.show_date, s.showtime, sc.screen_name, v.venue_name
    ORDER BY b.booked_at DESC
    LIMIT 5
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recent_bookings[] = $row;
    }
}

// ============================================
// TOP VENUES BY REVENUE
// ============================================
$top_venues = [];
$result = $conn->query("
    SELECT 
        v.id,
        v.venue_name,
        v.venue_location,
        COUNT(DISTINCT sc.id) as screen_count,
        COUNT(DISTINCT b.id) as total_bookings,
        COALESCE(SUM(b.total_amount), 0) as total_revenue,
        COUNT(DISTINCT CASE WHEN b.payment_status = 'paid' THEN b.id END) as paid_bookings
    FROM venues v
    LEFT JOIN screens sc ON v.id = sc.venue_id AND sc.is_active = 1
    LEFT JOIN schedules s ON sc.id = s.screen_id AND s.is_active = 1
    LEFT JOIN bookings b ON s.id = b.schedule_id AND b.payment_status = 'paid'
    WHERE v.is_active = 1
    GROUP BY v.id, v.venue_name, v.venue_location
    ORDER BY total_revenue DESC
    LIMIT 5
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $top_venues[] = $row;
    }
}

// ============================================
// RECENT SUGGESTIONS
// ============================================
$recent_suggestions = [];
$result = $conn->query("
    SELECT s.*, u.u_name as user_name 
    FROM suggestions s
    LEFT JOIN users u ON s.user_id = u.u_id
    ORDER BY 
        CASE s.status
            WHEN 'Pending' THEN 1
            WHEN 'Reviewed' THEN 2
            WHEN 'Implemented' THEN 3
            ELSE 4
        END,
        s.created_at DESC
    LIMIT 5
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recent_suggestions[] = $row;
    }
}

// ============================================
// RECENT CUSTOMER ACTIVITY
// ============================================
$recent_activity = [];
$result = $conn->query("
    SELECT 
        cal.id,
        cal.action_type,
        cal.details,
        cal.created_at,
        u.u_name as customer_name,
        m.title as movie_title
    FROM customer_activity_log cal
    LEFT JOIN users u ON cal.customer_id = u.u_id
    LEFT JOIN movies m ON cal.movie_id = m.id
    ORDER BY cal.created_at DESC
    LIMIT 10
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recent_activity[] = $row;
    }
}

$conn->close();
?>

<div class="admin-dashboard" style="max-width: 1400px; margin: 0 auto; padding: 20px;">
    <!-- Welcome Section -->
    <div class="welcome-section" style="text-align: center; margin-bottom: 40px; padding: 30px;
          background: linear-gradient(135deg, rgba(52, 152, 219, 0.1), rgba(41, 128, 185, 0.2));
          border-radius: 20px; border: 2px solid rgba(52, 152, 219, 0.3);">
        <h1 style="color: white; font-size: 2.5rem; margin-bottom: 15px; font-weight: 800;">
            Welcome Back, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!
        </h1>
        <p style="color: rgba(255, 255, 255, 0.8); font-size: 1.1rem; max-width: 600px; margin: 0 auto;">
            Administrator Dashboard - Manage your movie ticketing system
        </p>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 40px;">
        <div class="stat-card" style="background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); 
              padding: 25px; border-radius: 15px; text-align: center; border: 1px solid rgba(52, 152, 219, 0.3);
              transition: all 0.3s ease;">
            <div style="font-size: 2rem; color: #3498db; margin-bottom: 10px;">
                <i class="fas fa-film"></i>
            </div>
            <div style="font-size: 2rem; font-weight: bold; color: white; margin-bottom: 5px;">
                <?php echo $movie_count; ?>
            </div>
            <div style="color: #ecf0f1; font-size: 0.9rem;">Active Movies</div>
        </div>
        
        <div class="stat-card" style="background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); 
              padding: 25px; border-radius: 15px; text-align: center; border: 1px solid rgba(52, 152, 219, 0.3);
              transition: all 0.3s ease;">
            <div style="font-size: 2rem; color: #3498db; margin-bottom: 10px;">
                <i class="fas fa-users"></i>
            </div>
            <div style="font-size: 2rem; font-weight: bold; color: white; margin-bottom: 5px;">
                <?php echo $user_count; ?>
            </div>
            <div style="color: #ecf0f1; font-size: 0.9rem;">Active Users</div>
        </div>
        
        <div class="stat-card" style="background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); 
              padding: 25px; border-radius: 15px; text-align: center; border: 1px solid rgba(52, 152, 219, 0.3);
              transition: all 0.3s ease;">
            <div style="font-size: 2rem; color: #3498db; margin-bottom: 10px;">
                <i class="fas fa-ticket-alt"></i>
            </div>
            <div style="font-size: 2rem; font-weight: bold; color: white; margin-bottom: 5px;">
                <?php echo $booking_count; ?>
            </div>
            <div style="color: #ecf0f1; font-size: 0.9rem;">Paid Bookings</div>
        </div>
        
        <div class="stat-card" style="background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); 
              padding: 25px; border-radius: 15px; text-align: center; border: 1px solid rgba(52, 152, 219, 0.3);
              transition: all 0.3s ease;">
            <div style="font-size: 2rem; color: #3498db; margin-bottom: 10px;">
                <i class="fas fa-calendar-alt"></i>
            </div>
            <div style="font-size: 2rem; font-weight: bold; color: white; margin-bottom: 5px;">
                <?php echo $schedule_count; ?>
            </div>
            <div style="color: #ecf0f1; font-size: 0.9rem;">Upcoming Shows</div>
        </div>
        
        <div class="stat-card" style="background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); 
              padding: 25px; border-radius: 15px; text-align: center; border: 1px solid rgba(52, 152, 219, 0.3);
              transition: all 0.3s ease;">
            <div style="font-size: 2rem; color: #3498db; margin-bottom: 10px;">
                <i class="fas fa-building"></i>
            </div>
            <div style="font-size: 2rem; font-weight: bold; color: white; margin-bottom: 5px;">
                <?php echo $venue_count; ?>
            </div>
            <div style="color: #ecf0f1; font-size: 0.9rem;">Venues</div>
        </div>
        
        <div class="stat-card" style="background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); 
              padding: 25px; border-radius: 15px; text-align: center; border: 1px solid rgba(52, 152, 219, 0.3);
              transition: all 0.3s ease;">
            <div style="font-size: 2rem; color: #f39c12; margin-bottom: 10px;">
                <i class="fas fa-credit-card"></i>
            </div>
            <div style="font-size: 2rem; font-weight: bold; color: white; margin-bottom: 5px;">
                <?php echo $pending_payments; ?>
            </div>
            <div style="color: #ecf0f1; font-size: 0.9rem;">Pending Payments</div>
        </div>
        
        <!-- Suggestions Card -->
        <div class="stat-card" style="background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); 
              padding: 25px; border-radius: 15px; text-align: center; border: 1px solid rgba(155, 89, 182, 0.3);
              transition: all 0.3s ease;">
            <div style="font-size: 2rem; color: #9b59b6; margin-bottom: 10px;">
                <i class="fas fa-lightbulb"></i>
            </div>
            <div style="font-size: 2rem; font-weight: bold; color: white; margin-bottom: 5px;">
                <?php echo $suggestion_count; ?>
            </div>
            <div style="color: #ecf0f1; font-size: 0.9rem;">Pending Suggestions</div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions" style="margin-bottom: 40px;">
        <h2 style="color: white; margin-bottom: 20px; font-size: 1.5rem; font-weight: 700;">
            <i class="fas fa-bolt"></i> Quick Actions
        </h2>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px;">
            <a href="<?php echo SITE_URL; ?>index.php?page=admin/manage-venues" 
               class="action-btn" style="background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%); 
                      padding: 20px; border-radius: 12px; text-decoration: none; text-align: center;
                      color: white; font-weight: 600; transition: all 0.3s ease; display: flex;
                      flex-direction: column; align-items: center; gap: 10px;">
                <i class="fas fa-building fa-2x"></i>
                <span>Add Venue</span>
            </a>
            
            <a href="<?php echo SITE_URL; ?>index.php?page=admin/manage-movies" 
               class="action-btn" style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); 
                      padding: 20px; border-radius: 12px; text-decoration: none; text-align: center;
                      color: white; font-weight: 600; transition: all 0.3s ease; display: flex;
                      flex-direction: column; align-items: center; gap: 10px;">
                <i class="fas fa-film fa-2x"></i>
                <span>Add Movie</span>
            </a>
            
            <a href="<?php echo SITE_URL; ?>index.php?page=admin/manage-seats" 
               class="action-btn" style="background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%); 
                      padding: 20px; border-radius: 12px; text-decoration: none; text-align: center;
                      color: white; font-weight: 600; transition: all 0.3s ease; display: flex;
                      flex-direction: column; align-items: center; gap: 10px;">
                <i class="fas fa-chair fa-2x"></i>
                <span>Manage Seats</span>
            </a>
            
            <a href="<?php echo SITE_URL; ?>index.php?page=admin/manage-schedules" 
               class="action-btn" style="background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); 
                      padding: 20px; border-radius: 12px; text-decoration: none; text-align: center;
                      color: white; font-weight: 600; transition: all 0.3s ease; display: flex;
                      flex-direction: column; align-items: center; gap: 10px;">
                <i class="fas fa-calendar-alt fa-2x"></i>
                <span>Add Schedule</span>
            </a>
            
            <a href="<?php echo SITE_URL; ?>index.php?page=admin/manage-users&type=customer" 
               class="action-btn" style="background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%); 
                      padding: 20px; border-radius: 12px; text-decoration: none; text-align: center;
                      color: white; font-weight: 600; transition: all 0.3s ease; display: flex;
                      flex-direction: column; align-items: center; gap: 10px;">
                <i class="fas fa-users fa-2x"></i>
                <span>Manage Users</span>
            </a>
            
            <a href="<?php echo SITE_URL; ?>index.php?page=admin/manage-payments" 
               class="action-btn" style="background: linear-gradient(135deg, #1abc9c 0%, #16a085 100%); 
                      padding: 20px; border-radius: 12px; text-decoration: none; text-align: center;
                      color: white; font-weight: 600; transition: all 0.3s ease; display: flex;
                      flex-direction: column; align-items: center; gap: 10px;">
                <i class="fas fa-credit-card fa-2x"></i>
                <span>Payments</span>
                <?php if ($pending_payments > 0): ?>
                <span style="background: #e74c3c; color: white; padding: 2px 8px; border-radius: 20px; font-size: 0.7rem;">
                    <?php echo $pending_payments; ?>
                </span>
                <?php endif; ?>
            </a>
            
            <!-- Manage Suggestions Button -->
            <a href="<?php echo SITE_URL; ?>index.php?page=admin/manage-suggestions" 
               class="action-btn" style="background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%); 
                      padding: 20px; border-radius: 12px; text-decoration: none; text-align: center;
                      color: white; font-weight: 600; transition: all 0.3s ease; display: flex;
                      flex-direction: column; align-items: center; gap: 10px;">
                <i class="fas fa-lightbulb fa-2x"></i>
                <span>Suggestions</span>
                <?php if ($suggestion_count > 0): ?>
                <span style="background: #e74c3c; color: white; padding: 2px 8px; border-radius: 20px; font-size: 0.7rem;">
                    <?php echo $suggestion_count; ?>
                </span>
                <?php endif; ?>
            </a>
        </div>
    </div>

    <!-- Recent Activity Section -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(500px, 1fr)); gap: 30px; margin-bottom: 40px;">
        
        <!-- Recent Movies -->
        <div class="recent-movies" style="background: rgba(255, 255, 255, 0.05); border-radius: 15px; padding: 20px;
              border: 1px solid rgba(52, 152, 219, 0.2);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
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
                    <div style="display: flex; align-items: center; gap: 15px; padding: 12px; background: rgba(255, 255, 255, 0.03); 
                          border-radius: 10px; border: 1px solid rgba(255, 255, 255, 0.05); transition: all 0.2s ease;">
                        <?php if (!empty($movie['poster_url'])): ?>
                        <img src="<?php echo $movie['poster_url']; ?>" 
                             alt="<?php echo htmlspecialchars($movie['title']); ?>"
                             style="width: 50px; height: 70px; object-fit: cover; border-radius: 5px;">
                        <?php else: ?>
                        <div style="width: 50px; height: 70px; background: rgba(52, 152, 219, 0.1); border-radius: 5px; 
                             display: flex; align-items: center; justify-content: center;">
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
                                <span style="color: rgba(255, 255, 255, 0.6);">
                                    <i class="fas fa-star"></i> <?php echo $movie['rating'] ?? 'PG'; ?>
                                </span>
                            </div>
                        </div>
                        
                        <a href="<?php echo SITE_URL; ?>index.php?page=admin/manage-movies&edit=<?php echo $movie['id']; ?>" 
                           style="color: #3498db; text-decoration: none; font-size: 0.8rem;">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Recent Schedules -->
        <div class="recent-schedules" style="background: rgba(255, 255, 255, 0.05); border-radius: 15px; padding: 20px;
              border: 1px solid rgba(46, 204, 113, 0.2);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="color: white; font-size: 1.2rem; font-weight: 700;">
                    <i class="fas fa-calendar-alt"></i> Upcoming Shows
                </h3>
                <a href="<?php echo SITE_URL; ?>index.php?page=admin/manage-schedules" 
                   style="color: #2ecc71; text-decoration: none; font-weight: 600; font-size: 0.85rem;">
                    View All <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            
            <?php if (empty($recent_schedules)): ?>
                <div style="text-align: center; padding: 30px; color: rgba(255, 255, 255, 0.5);">
                    <i class="fas fa-calendar-alt fa-2x" style="margin-bottom: 10px; opacity: 0.5;"></i>
                    <p>No upcoming shows</p>
                </div>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <?php foreach ($recent_schedules as $schedule): 
                        $availability_class = '';
                        $availability_color = '#2ecc71';
                        $available_percentage = $schedule['total_seats'] > 0 ? ($schedule['available_seats'] / $schedule['total_seats']) * 100 : 0;
                        
                        if ($available_percentage <= 10 && $available_percentage > 0) {
                            $availability_color = '#e74c3c';
                        } elseif ($available_percentage <= 30) {
                            $availability_color = '#f39c12';
                        }
                    ?>
                    <div style="display: flex; align-items: center; gap: 15px; padding: 12px; background: rgba(255, 255, 255, 0.03); 
                          border-radius: 10px; border: 1px solid rgba(255, 255, 255, 0.05);">
                        <div style="width: 50px; height: 50px; background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%); 
                             border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-tv" style="color: white; font-size: 1.2rem;"></i>
                        </div>
                        
                        <div style="flex: 1;">
                            <div style="color: white; font-weight: 600; margin-bottom: 3px;">
                                <?php echo htmlspecialchars($schedule['movie_title']); ?>
                            </div>
                            <div style="display: flex; flex-wrap: wrap; gap: 12px; font-size: 0.75rem;">
                                <span style="color: #3498db;">
                                    <i class="fas fa-building"></i> <?php echo htmlspecialchars($schedule['venue_name']); ?>
                                </span>
                                <span style="color: #2ecc71;">
                                    <i class="fas fa-tv"></i> <?php echo htmlspecialchars($schedule['screen_name']); ?>
                                </span>
                                <span style="color: #f39c12;">
                                    <i class="fas fa-calendar"></i> <?php echo date('M d, h:i A', strtotime($schedule['show_date'] . ' ' . $schedule['showtime'])); ?>
                                </span>
                            </div>
                            <div style="margin-top: 5px;">
                                <div style="display: flex; justify-content: space-between; font-size: 0.7rem;">
                                    <span style="color: rgba(255,255,255,0.6);">Available Seats:</span>
                                    <span style="color: <?php echo $availability_color; ?>; font-weight: 600;">
                                        <?php echo $schedule['available_seats']; ?>/<?php echo $schedule['total_seats']; ?>
                                    </span>
                                </div>
                                <div style="background: rgba(255,255,255,0.1); height: 4px; border-radius: 2px; overflow: hidden; margin-top: 3px;">
                                    <div style="background: <?php echo $availability_color; ?>; height: 100%; width: <?php echo $available_percentage; ?>%;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Bookings & Suggestions -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(500px, 1fr)); gap: 30px; margin-bottom: 40px;">
        
        <!-- Recent Bookings -->
        <div class="recent-bookings" style="background: rgba(255, 255, 255, 0.05); border-radius: 15px; padding: 20px;
              border: 1px solid rgba(241, 196, 15, 0.2);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="color: white; font-size: 1.2rem; font-weight: 700;">
                    <i class="fas fa-ticket-alt"></i> Recent Bookings
                </h3>
                <a href="<?php echo SITE_URL; ?>index.php?page=admin/manage-payments" 
                   style="color: #f39c12; text-decoration: none; font-weight: 600; font-size: 0.85rem;">
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
                        $payment_color = $booking['payment_status'] == 'paid' ? '#2ecc71' : '#e74c3c';
                        $attendance_color = $booking['attendance_status'] == 'pending' ? '#f39c12' : ($booking['attendance_status'] == 'present' ? '#2ecc71' : '#3498db');
                    ?>
                    <div style="padding: 12px; background: rgba(255, 255, 255, 0.03); border-radius: 10px; border: 1px solid rgba(255, 255, 255, 0.05);">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                            <div style="color: white; font-weight: 600;">
                                <?php echo htmlspecialchars($booking['movie_title'] ?? 'Unknown Movie'); ?>
                            </div>
                            <div style="display: flex; gap: 8px;">
                                <span style="background: <?php echo $payment_color; ?>20; color: <?php echo $payment_color; ?>; padding: 2px 8px; border-radius: 12px; font-size: 0.7rem; font-weight: 600;">
                                    <?php echo ucfirst($booking['payment_status']); ?>
                                </span>
                                <span style="background: <?php echo $attendance_color; ?>20; color: <?php echo $attendance_color; ?>; padding: 2px 8px; border-radius: 12px; font-size: 0.7rem; font-weight: 600;">
                                    <?php echo ucfirst($booking['attendance_status']); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; font-size: 0.75rem;">
                            <div style="color: rgba(255, 255, 255, 0.6);">
                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($booking['customer_name']); ?>
                            </div>
                            <div style="color: rgba(255, 255, 255, 0.6);">
                                <i class="fas fa-hashtag"></i> <?php echo $booking['booking_reference']; ?>
                            </div>
                            <div style="color: rgba(255, 255, 255, 0.6);">
                                <i class="fas fa-calendar"></i> <?php echo date('M d', strtotime($booking['show_date'] ?? 'now')); ?>
                            </div>
                            <div style="color: #2ecc71;">
                                <i class="fas fa-tag"></i> ₱<?php echo number_format($booking['total_amount'] ?? 0, 2); ?>
                            </div>
                            <?php if (!empty($booking['seat_list'])): ?>
                            <div style="color: #3498db; grid-column: span 2;">
                                <i class="fas fa-chair"></i> Seats: <?php echo htmlspecialchars(substr($booking['seat_list'], 0, 40)); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Recent Suggestions -->
        <div class="recent-suggestions" style="background: rgba(255, 255, 255, 0.05); border-radius: 15px; padding: 20px;
              border: 1px solid rgba(155, 89, 182, 0.2);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="color: white; font-size: 1.2rem; font-weight: 700;">
                    <i class="fas fa-lightbulb"></i> Customer Suggestions
                    <?php if ($suggestion_count > 0): ?>
                    <span style="background: #e74c3c; color: white; padding: 2px 8px; border-radius: 20px; font-size: 0.7rem; margin-left: 5px;">
                        <?php echo $suggestion_count; ?> new
                    </span>
                    <?php endif; ?>
                </h3>
                <a href="<?php echo SITE_URL; ?>index.php?page=admin/manage-suggestions" 
                   style="color: #9b59b6; text-decoration: none; font-weight: 600; font-size: 0.85rem;">
                    Manage <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            
            <?php if (empty($recent_suggestions)): ?>
                <div style="text-align: center; padding: 30px; color: rgba(255, 255, 255, 0.5);">
                    <i class="fas fa-lightbulb fa-2x" style="margin-bottom: 10px; opacity: 0.5;"></i>
                    <p>No suggestions yet. Customer feedback will appear here.</p>
                </div>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <?php foreach ($recent_suggestions as $suggestion): 
                        $status_color = '';
                        $status_bg = '';
                        
                        switch($suggestion['status']) {
                            case 'Pending':
                                $status_color = '#f39c12';
                                $status_bg = 'rgba(243, 156, 18, 0.2)';
                                break;
                            case 'Reviewed':
                                $status_color = '#3498db';
                                $status_bg = 'rgba(52, 152, 219, 0.2)';
                                break;
                            case 'Implemented':
                                $status_color = '#2ecc71';
                                $status_bg = 'rgba(46, 204, 113, 0.2)';
                                break;
                        }
                    ?>
                    <div style="padding: 12px; background: rgba(255, 255, 255, 0.03); border-radius: 10px; border-left: 4px solid <?php echo $status_color; ?>;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                            <div style="color: white; font-weight: 600;">
                                <?php echo htmlspecialchars($suggestion['user_name'] ?? 'Guest'); ?>
                            </div>
                            <span style="background: <?php echo $status_bg; ?>; color: <?php echo $status_color; ?>; padding: 2px 8px; border-radius: 12px; font-size: 0.7rem; font-weight: 600;">
                                <?php echo $suggestion['status']; ?>
                            </span>
                        </div>
                        <p style="color: rgba(255, 255, 255, 0.8); font-size: 0.85rem; line-height: 1.4; margin-bottom: 8px;">
                            "<?php echo htmlspecialchars(substr($suggestion['suggestion'], 0, 100)); ?><?php echo strlen($suggestion['suggestion']) > 100 ? '...' : ''; ?>"
                        </p>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="color: rgba(255, 255, 255, 0.4); font-size: 0.65rem;">
                                <?php echo date('M d, Y', strtotime($suggestion['created_at'])); ?>
                            </span>
                            <a href="<?php echo SITE_URL; ?>index.php?page=admin/manage-suggestions&edit=<?php echo $suggestion['id']; ?>" 
                               style="color: #9b59b6; text-decoration: none; font-size: 0.7rem;">
                                <i class="fas fa-reply"></i> Respond
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php if ($total_suggestions > 5): ?>
                <div style="text-align: center; margin-top: 15px;">
                    <a href="<?php echo SITE_URL; ?>index.php?page=admin/manage-suggestions" 
                       style="color: #9b59b6; text-decoration: none; font-size: 0.8rem;">
                        View all <?php echo $total_suggestions; ?> suggestions <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Top Venues -->
    <div style="background: rgba(255, 255, 255, 0.05); border-radius: 15px; padding: 20px; margin-bottom: 30px;
          border: 1px solid rgba(52, 152, 219, 0.2);">
        <h3 style="color: white; font-size: 1.2rem; margin-bottom: 20px; font-weight: 700;">
            <i class="fas fa-chart-line"></i> Top Venues by Revenue
        </h3>
        
        <?php if (empty($top_venues)): ?>
            <div style="text-align: center; padding: 30px; color: rgba(255, 255, 255, 0.5);">
                <i class="fas fa-chart-line fa-2x" style="margin-bottom: 10px; opacity: 0.5;"></i>
                <p>No venue data available</p>
            </div>
        <?php else: ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px;">
                <?php 
                $max_revenue = !empty($top_venues) ? max(array_column($top_venues, 'total_revenue')) : 1;
                if ($max_revenue == 0) $max_revenue = 1; // Prevent division by zero
                ?>
                <?php foreach ($top_venues as $venue): ?>
                <div style="background: rgba(255, 255, 255, 0.03); border-radius: 10px; padding: 15px; border: 1px solid rgba(255, 255, 255, 0.05);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <div style="color: white; font-weight: 700;">
                            <?php echo htmlspecialchars($venue['venue_name']); ?>
                        </div>
                        <div style="color: #2ecc71; font-weight: 800;">
                            ₱<?php echo number_format($venue['total_revenue'], 2); ?>
                        </div>
                    </div>
                    <div style="color: rgba(255, 255, 255, 0.6); font-size: 0.8rem;">
                        <i class="fas fa-tv"></i> <?php echo $venue['screen_count']; ?> screens • 
                        <i class="fas fa-ticket-alt"></i> <?php echo $venue['paid_bookings']; ?> paid bookings
                    </div>
                    <?php 
                    $revenue_percentage = ($venue['total_revenue'] / $max_revenue) * 100;
                    ?>
                    <div style="margin-top: 10px;">
                        <div style="background: rgba(255,255,255,0.1); height: 6px; border-radius: 3px; overflow: hidden;">
                            <div style="background: #2ecc71; height: 100%; width: <?php echo $revenue_percentage; ?>%;"></div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- System Information -->
    <div class="system-info" style="background: rgba(255, 255, 255, 0.05); border-radius: 15px; padding: 20px;
          border: 1px solid rgba(52, 152, 219, 0.2);">
        <h3 style="color: white; margin-bottom: 15px; font-size: 1.2rem; font-weight: 700;">
            <i class="fas fa-info-circle"></i> System Information
        </h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
            <div>
                <h4 style="color: #3498db; margin-bottom: 10px; font-size: 0.9rem;">Server Information</h4>
                <div style="background: rgba(255, 255, 255, 0.03); padding: 15px; border-radius: 10px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <span style="color: rgba(255, 255, 255, 0.6);">PHP Version:</span>
                        <span style="color: white; font-weight: 600;"><?php echo phpversion(); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <span style="color: rgba(255, 255, 255, 0.6);">Server Time:</span>
                        <span style="color: white; font-weight: 600;"><?php echo date('Y-m-d H:i:s'); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: rgba(255, 255, 255, 0.6);">Session:</span>
                        <span style="color: #2ecc71; font-weight: 600;">Active</span>
                    </div>
                </div>
            </div>
            
            <div>
                <h4 style="color: #3498db; margin-bottom: 10px; font-size: 0.9rem;">Database Statistics</h4>
                <div style="background: rgba(255, 255, 255, 0.03); padding: 15px; border-radius: 10px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <span style="color: rgba(255, 255, 255, 0.6);">Total Screens:</span>
                        <span style="color: white; font-weight: 600;"><?php echo number_format($screen_count); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <span style="color: rgba(255, 255, 255, 0.6);">Total Seats:</span>
                        <span style="color: white; font-weight: 600;"><?php echo number_format($seats_count); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: rgba(255, 255, 255, 0.6);">Total Venues:</span>
                        <span style="color: #2ecc71; font-weight: 600;"><?php echo $venue_count; ?></span>
                    </div>
                </div>
            </div>
            
            <div>
                <h4 style="color: #3498db; margin-bottom: 10px; font-size: 0.9rem;">Quick Links</h4>
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px;">
                    <a href="<?php echo SITE_URL; ?>" target="_blank"
                       style="background: rgba(255, 255, 255, 0.1); color: white; text-decoration: none; padding: 8px; border-radius: 6px; text-align: center; font-size: 0.8rem; transition: all 0.2s ease;">
                        <i class="fas fa-globe"></i> Public Site
                    </a>
                    <a href="<?php echo SITE_URL; ?>index.php?page=admin/manage-suggestions" 
                       style="background: rgba(155, 89, 182, 0.2); color: #9b59b6; text-decoration: none; padding: 8px; border-radius: 6px; text-align: center; font-size: 0.8rem; transition: all 0.2s ease;">
                        <i class="fas fa-lightbulb"></i> Suggestions
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
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(52, 152, 219, 0.2);
        border-color: #3498db;
    }
    
    .action-btn:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(52, 152, 219, 0.3);
    }
    
    .recent-movies div:hover,
    .recent-schedules div:hover,
    .recent-bookings div:hover,
    .recent-suggestions div:hover {
        background: rgba(255, 255, 255, 0.08);
        border-color: rgba(52, 152, 219, 0.3);
    }
    
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
    
    @media (max-width: 1100px) {
        .stats-grid {
            grid-template-columns: repeat(3, 1fr);
        }
    }
    
    @media (max-width: 768px) {
        .admin-dashboard {
            padding: 15px;
        }
        
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .welcome-section h1 {
            font-size: 1.8rem !important;
        }
    }
    
    @media (max-width: 576px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
        
        .quick-actions > div {
            grid-template-columns: repeat(2, 1fr);
        }
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const statCards = document.querySelectorAll('.stat-card');
        statCards.forEach((card, index) => {
            card.style.animationDelay = `${index * 0.1}s`;
            card.style.animation = 'fadeInUp 0.5s ease forwards';
            card.style.opacity = '0';
        });
        
        const actionBtns = document.querySelectorAll('.action-btn');
        actionBtns.forEach((btn, index) => {
            btn.style.animationDelay = `${index * 0.1}s`;
            btn.style.animation = 'fadeInUp 0.5s ease forwards';
            btn.style.opacity = '0';
        });
        
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
            if (e.ctrlKey && e.key === '6') {
                e.preventDefault();
                window.location.href = "<?php echo SITE_URL; ?>index.php?page=admin/manage-suggestions";
            }
            if (e.ctrlKey && e.key === 'l') {
                e.preventDefault();
                window.location.href = "<?php echo SITE_URL; ?>index.php?page=logout";
            }
        });
    });
</script>

</body>
</html>