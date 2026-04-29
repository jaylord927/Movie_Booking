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

// Get today's date
$today = date('Y-m-d');
$current_time = date('H:i:s');

// ============================================
// FIXED: Get upcoming shows within next 2 hours
// ============================================
$upcoming_shows = [];
$upcoming_stmt = $conn->prepare("
    SELECT 
        s.id as schedule_id,
        s.show_date,
        s.showtime,
        m.title as movie_title,
        sc.screen_name,
        v.venue_name,
        TIMESTAMPDIFF(MINUTE, NOW(), CONCAT(s.show_date, ' ', s.showtime)) as minutes_until_show
    FROM schedules s
    JOIN movies m ON s.movie_id = m.id
    JOIN screens sc ON s.screen_id = sc.id
    JOIN venues v ON sc.venue_id = v.id
    WHERE s.show_date >= CURDATE()
    AND CONCAT(s.show_date, ' ', s.showtime) > NOW()
    AND TIMESTAMPDIFF(MINUTE, NOW(), CONCAT(s.show_date, ' ', s.showtime)) <= 120
    AND s.is_active = 1
    AND m.is_active = 1
    ORDER BY s.show_date, s.showtime
    LIMIT 10
");
$upcoming_stmt->execute();
$upcoming_result = $upcoming_stmt->get_result();
while ($row = $upcoming_result->fetch_assoc()) {
    $upcoming_shows[] = $row;
}
$upcoming_stmt->close();

// ============================================
// FIXED: Get statistics using normalized schema
// ============================================
$stats = [
    'pending_checkins' => 0,
    'checked_in' => 0,
    'completed' => 0,
    'total_today' => 0
];

// Today's bookings that are paid
$today_bookings_stmt = $conn->prepare("
    SELECT 
        COUNT(CASE WHEN b.attendance_status = 'pending' AND b.payment_status = 'paid' THEN 1 END) as pending_checkins,
        COUNT(CASE WHEN b.attendance_status = 'present' THEN 1 END) as checked_in,
        COUNT(CASE WHEN b.attendance_status = 'completed' THEN 1 END) as completed,
        COUNT(CASE WHEN b.payment_status = 'paid' THEN 1 END) as total_today
    FROM bookings b
    JOIN schedules s ON b.schedule_id = s.id
    WHERE s.show_date = CURDATE() AND b.payment_status = 'paid'
");
$today_bookings_stmt->execute();
$stats_result = $today_bookings_stmt->get_result();
if ($stats_result && $stats_result->num_rows > 0) {
    $stats = $stats_result->fetch_assoc();
}
$today_bookings_stmt->close();

// Get recent check-ins (last 10)
$recent_checkins = [];
$checkins_stmt = $conn->prepare("
    SELECT 
        b.booking_reference,
        b.attendance_status,
        b.verified_at,
        u.u_name as customer_name,
        m.title as movie_title,
        s.showtime,
        a.u_name as verified_by_name
    FROM bookings b
    JOIN users u ON b.user_id = u.u_id
    JOIN schedules s ON b.schedule_id = s.id
    JOIN movies m ON s.movie_id = m.id
    LEFT JOIN users a ON b.verified_by = a.u_id
    WHERE b.attendance_status IN ('present', 'completed')
    AND s.show_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ORDER BY b.verified_at DESC
    LIMIT 10
");
$checkins_stmt->execute();
$checkins_result = $checkins_stmt->get_result();
while ($row = $checkins_result->fetch_assoc()) {
    $recent_checkins[] = $row;
}
$checkins_stmt->close();

$conn->close();
?>

<div class="staff-container" style="max-width: 1400px; margin: 0 auto; padding: 30px;">
    <!-- Welcome Section -->
    <div style="text-align: center; margin-bottom: 40px; padding: 30px; background: linear-gradient(135deg, rgba(46, 204, 113, 0.1), rgba(39, 174, 96, 0.2)); border-radius: 20px; border: 2px solid rgba(46, 204, 113, 0.3);">
        <h1 style="color: white; font-size: 2.5rem; margin-bottom: 15px; font-weight: 800;">
            Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!
        </h1>
        <p style="color: rgba(255, 255, 255, 0.8); font-size: 1.1rem;">
            Staff Dashboard - Manage Customer Check-ins
        </p>
        <p style="color: rgba(255, 255, 255, 0.6); font-size: 0.9rem; margin-top: 10px;">
            Current Time: <?php echo date('F d, Y h:i A'); ?>
        </p>
    </div>

    <!-- Statistics Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 40px;">
        <div style="background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); padding: 25px; border-radius: 15px; text-align: center; border: 1px solid rgba(241, 196, 15, 0.3);">
            <div style="font-size: 2rem; color: #f39c12; margin-bottom: 10px;"><i class="fas fa-clock"></i></div>
            <div style="font-size: 2rem; font-weight: 800; color: white;"><?php echo intval($stats['pending_checkins']); ?></div>
            <div style="color: rgba(255,255,255,0.8); font-size: 0.9rem;">Pending Check-ins</div>
            <div style="font-size: 0.7rem; color: rgba(255,255,255,0.5); margin-top: 5px;">Today's shows</div>
        </div>
        
        <div style="background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); padding: 25px; border-radius: 15px; text-align: center; border: 1px solid rgba(46, 204, 113, 0.3);">
            <div style="font-size: 2rem; color: #2ecc71; margin-bottom: 10px;"><i class="fas fa-check-circle"></i></div>
            <div style="font-size: 2rem; font-weight: 800; color: white;"><?php echo intval($stats['checked_in']); ?></div>
            <div style="color: rgba(255,255,255,0.8); font-size: 0.9rem;">Checked In</div>
            <div style="font-size: 0.7rem; color: rgba(255,255,255,0.5); margin-top: 5px;">Today's verified</div>
        </div>
        
        <div style="background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); padding: 25px; border-radius: 15px; text-align: center; border: 1px solid rgba(52, 152, 219, 0.3);">
            <div style="font-size: 2rem; color: #3498db; margin-bottom: 10px;"><i class="fas fa-ticket-alt"></i></div>
            <div style="font-size: 2rem; font-weight: 800; color: white;"><?php echo intval($stats['total_today']); ?></div>
            <div style="color: rgba(255,255,255,0.8); font-size: 0.9rem;">Total Today</div>
            <div style="font-size: 0.7rem; color: rgba(255,255,255,0.5); margin-top: 5px;">Paid tickets</div>
        </div>
        
        <div style="background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); padding: 25px; border-radius: 15px; text-align: center; border: 1px solid rgba(155, 89, 182, 0.3);">
            <div style="font-size: 2rem; color: #9b59b6; margin-bottom: 10px;"><i class="fas fa-film"></i></div>
            <div style="font-size: 2rem; font-weight: 800; color: white;"><?php echo count($upcoming_shows); ?></div>
            <div style="color: rgba(255,255,255,0.8); font-size: 0.9rem;">Upcoming Shows</div>
            <div style="font-size: 0.7rem; color: rgba(255,255,255,0.5); margin-top: 5px;">Next 2 hours</div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 40px;">
        <a href="?page=staff/scan-qr" class="staff-action-btn" style="background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%); padding: 30px; border-radius: 15px; text-decoration: none; text-align: center; color: white; transition: all 0.3s ease;">
            <i class="fas fa-qrcode fa-3x" style="margin-bottom: 15px; display: block;"></i>
            <span style="font-size: 1.2rem; font-weight: 700;">Scan QR Code</span>
            <p style="font-size: 0.85rem; margin-top: 10px; opacity: 0.9;">Verify customer booking</p>
        </a>
        
        <a href="?page=staff/verify-booking" class="staff-action-btn" style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); padding: 30px; border-radius: 15px; text-decoration: none; text-align: center; color: white; transition: all 0.3s ease;">
            <i class="fas fa-search fa-3x" style="margin-bottom: 15px; display: block;"></i>
            <span style="font-size: 1.2rem; font-weight: 700;">Verify by Reference</span>
            <p style="font-size: 0.85rem; margin-top: 10px; opacity: 0.9;">Enter booking reference</p>
        </a>
        
        <a href="?page=staff/payment-transaction" class="staff-action-btn" style="background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%); padding: 30px; border-radius: 15px; text-decoration: none; text-align: center; color: white; transition: all 0.3s ease;">
            <i class="fas fa-credit-card fa-3x" style="margin-bottom: 15px; display: block;"></i>
            <span style="font-size: 1.2rem; font-weight: 700;">Payment Transactions</span>
            <p style="font-size: 0.85rem; margin-top: 10px; opacity: 0.9;">View all payments</p>
        </a>
        
        <a href="?page=staff/print-ticket" class="staff-action-btn" style="background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); padding: 30px; border-radius: 15px; text-decoration: none; text-align: center; color: white; transition: all 0.3s ease;">
            <i class="fas fa-print fa-3x" style="margin-bottom: 15px; display: block;"></i>
            <span style="font-size: 1.2rem; font-weight: 700;">Print Tickets</span>
            <p style="font-size: 0.85rem; margin-top: 10px; opacity: 0.9;">Today's show tickets</p>
        </a>
    </div>

    <!-- Two Column Layout for Upcoming Shows and Recent Check-ins -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(500px, 1fr)); gap: 30px;">
        
        <!-- Upcoming Shows -->
        <div style="background: rgba(255, 255, 255, 0.05); border-radius: 15px; padding: 25px; border: 1px solid rgba(46, 204, 113, 0.2);">
            <h2 style="color: white; font-size: 1.5rem; margin-bottom: 20px;">
                <i class="fas fa-clock"></i> Upcoming Shows (Next 2 Hours)
            </h2>
            
            <?php if (empty($upcoming_shows)): ?>
                <div style="text-align: center; padding: 40px; color: rgba(255,255,255,0.6);">
                    <i class="fas fa-calendar-alt fa-2x" style="margin-bottom: 10px; opacity: 0.5;"></i>
                    <p>No upcoming shows in the next 2 hours</p>
                </div>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 15px;">
                    <?php foreach ($upcoming_shows as $show): 
                        $hours = floor($show['minutes_until_show'] / 60);
                        $minutes = $show['minutes_until_show'] % 60;
                    ?>
                        <div style="background: rgba(255,255,255,0.05); border-radius: 10px; padding: 20px; border-left: 4px solid #2ecc71; transition: all 0.3s ease;">
                            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 10px;">
                                <strong style="color: white; font-size: 1.1rem;"><?php echo htmlspecialchars($show['movie_title']); ?></strong>
                                <span style="background: #2ecc71; color: white; padding: 5px 15px; border-radius: 20px; font-size: 0.85rem; font-weight: 600;">
                                    <?php echo date('h:i A', strtotime($show['showtime'])); ?>
                                </span>
                            </div>
                            <div style="color: rgba(255,255,255,0.7); font-size: 0.85rem; margin-bottom: 5px;">
                                <i class="fas fa-building"></i> <?php echo htmlspecialchars($show['venue_name']); ?> • 
                                <i class="fas fa-tv"></i> <?php echo htmlspecialchars($show['screen_name']); ?>
                            </div>
                            <div style="color: #f39c12; font-size: 0.85rem;">
                                <i class="fas fa-hourglass-half"></i> 
                                <?php if ($hours > 0): ?>
                                    In <?php echo $hours; ?> hour(s) 
                                <?php endif; ?>
                                <?php if ($minutes > 0): ?>
                                    <?php echo $minutes; ?> minute(s)
                                <?php endif; ?>
                            </div>
                            <div style="margin-top: 12px;">
                                <a href="?page=staff/print-ticket&schedule=<?php echo $show['schedule_id']; ?>" 
                                   style="display: inline-flex; align-items: center; gap: 8px; background: rgba(46,204,113,0.2); color: #2ecc71; padding: 8px 15px; border-radius: 8px; text-decoration: none; font-size: 0.85rem; font-weight: 600;">
                                    <i class="fas fa-print"></i> Print Tickets
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Recent Check-ins -->
        <div style="background: rgba(255, 255, 255, 0.05); border-radius: 15px; padding: 25px; border: 1px solid rgba(52, 152, 219, 0.2);">
            <h2 style="color: white; font-size: 1.5rem; margin-bottom: 20px;">
                <i class="fas fa-history"></i> Recent Check-ins
            </h2>
            
            <?php if (empty($recent_checkins)): ?>
                <div style="text-align: center; padding: 40px; color: rgba(255,255,255,0.6);">
                    <i class="fas fa-user-check fa-2x" style="margin-bottom: 10px; opacity: 0.5;"></i>
                    <p>No recent check-ins</p>
                </div>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <?php foreach ($recent_checkins as $checkin): ?>
                        <div style="background: rgba(255,255,255,0.03); border-radius: 10px; padding: 15px; border: 1px solid rgba(255,255,255,0.05); transition: all 0.3s ease;">
                            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 8px;">
                                <span style="color: white; font-weight: 600;"><?php echo htmlspecialchars($checkin['customer_name']); ?></span>
                                <span style="background: <?php echo $checkin['attendance_status'] == 'present' ? 'rgba(46,204,113,0.2)' : 'rgba(52,152,219,0.2)'; ?>; 
                                      color: <?php echo $checkin['attendance_status'] == 'present' ? '#2ecc71' : '#3498db'; ?>; 
                                      padding: 5px 12px; border-radius: 15px; font-size: 0.75rem; font-weight: 600;">
                                    <i class="fas <?php echo $checkin['attendance_status'] == 'present' ? 'fa-check-circle' : 'fa-check-double'; ?>"></i>
                                    <?php echo ucfirst($checkin['attendance_status']); ?>
                                </span>
                            </div>
                            <div style="color: rgba(255,255,255,0.7); font-size: 0.8rem;">
                                <div><i class="fas fa-film"></i> <?php echo htmlspecialchars($checkin['movie_title']); ?></div>
                                <div><i class="fas fa-hashtag"></i> Ref: <?php echo $checkin['booking_reference']; ?></div>
                                <?php if ($checkin['verified_at']): ?>
                                <div><i class="fas fa-clock"></i> <?php echo date('M d, h:i A', strtotime($checkin['verified_at'])); ?></div>
                                <?php endif; ?>
                                <?php if ($checkin['verified_by_name']): ?>
                                <div><i class="fas fa-user-check"></i> Verified by: <?php echo htmlspecialchars($checkin['verified_by_name']); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div style="text-align: center; margin-top: 15px;">
                    <a href="?page=staff/verify-history" style="color: #3498db; text-decoration: none; font-size: 0.85rem;">
                        View All History <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.staff-action-btn {
    transition: all 0.3s ease;
    display: block;
}

.staff-action-btn:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.3);
}

@media (max-width: 768px) {
    .staff-container {
        padding: 15px;
    }
    
    .staff-container > div > div {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
// Auto-refresh dashboard every 60 seconds
setTimeout(function() {
    location.reload();
}, 60000);
</script>

