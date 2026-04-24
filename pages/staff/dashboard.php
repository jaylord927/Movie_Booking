<?php
$root_dir = dirname(dirname(__DIR__));
require_once $root_dir . '/includes/config.php';
require_once $root_dir . '/includes/functions.php';

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

// Get upcoming shows within next 2 hours
$upcoming_shows = $conn->prepare("
    SELECT DISTINCT s.movie_title, s.show_date, s.showtime, s.id as schedule_id,
           TIMEDIFF(CONCAT(s.show_date, ' ', s.showtime), NOW()) as time_until_show
    FROM movie_schedules s
    WHERE s.show_date = CURDATE() 
    AND s.showtime > NOW()
    AND TIME_TO_SEC(TIMEDIFF(s.showtime, NOW())) <= 7200
    AND s.is_active = 1
    ORDER BY s.showtime
");
$upcoming_shows->execute();
$upcoming_result = $upcoming_shows->get_result();
$upcoming_shows_list = [];
while ($row = $upcoming_result->fetch_assoc()) {
    $upcoming_shows_list[] = $row;
}
$upcoming_shows->close();

// Get statistics
$stats = [];
$stats_stmt = $conn->prepare("
    SELECT 
        COUNT(CASE WHEN payment_status = 'Paid' AND attendance_status = 'Pending' AND show_date = CURDATE() THEN 1 END) as pending_checkins,
        COUNT(CASE WHEN attendance_status = 'Present' AND show_date = CURDATE() THEN 1 END) as checked_in,
        COUNT(CASE WHEN attendance_status = 'Completed' AND show_date = CURDATE() THEN 1 END) as completed,
        COUNT(CASE WHEN payment_status = 'Paid' AND show_date = CURDATE() THEN 1 END) as total_today
    FROM tbl_booking
    WHERE show_date = CURDATE()
");
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result->fetch_assoc();
$stats_stmt->close();

$conn->close();
?>

<div class="staff-container" style="max-width: 1400px; margin: 0 auto; padding: 30px;">
    <div style="text-align: center; margin-bottom: 40px; padding: 30px; background: linear-gradient(135deg, rgba(46, 204, 113, 0.1), rgba(39, 174, 96, 0.2)); border-radius: 20px; border: 2px solid rgba(46, 204, 113, 0.3);">
        <h1 style="color: white; font-size: 2.5rem; margin-bottom: 15px; font-weight: 800;">
            Welcome, <?php echo $_SESSION['user_name']; ?>!
        </h1>
        <p style="color: rgba(255, 255, 255, 0.8); font-size: 1.1rem;">
            Staff Dashboard - Manage Customer Check-ins
        </p>
    </div>

    <!-- Statistics Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 40px;">
        <div style="background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); padding: 25px; border-radius: 15px; text-align: center; border: 1px solid rgba(46, 204, 113, 0.3);">
            <div style="font-size: 2rem; color: #f39c12; margin-bottom: 10px;"><i class="fas fa-clock"></i></div>
            <div style="font-size: 2rem; font-weight: 800; color: white;"><?php echo $stats['pending_checkins'] ?? 0; ?></div>
            <div style="color: rgba(255,255,255,0.8);">Pending Check-ins</div>
        </div>
        <div style="background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); padding: 25px; border-radius: 15px; text-align: center; border: 1px solid rgba(46, 204, 113, 0.3);">
            <div style="font-size: 2rem; color: #2ecc71; margin-bottom: 10px;"><i class="fas fa-check-circle"></i></div>
            <div style="font-size: 2rem; font-weight: 800; color: white;"><?php echo $stats['checked_in'] ?? 0; ?></div>
            <div style="color: rgba(255,255,255,0.8);">Checked In</div>
        </div>
        <div style="background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); padding: 25px; border-radius: 15px; text-align: center; border: 1px solid rgba(46, 204, 113, 0.3);">
            <div style="font-size: 2rem; color: #3498db; margin-bottom: 10px;"><i class="fas fa-ticket-alt"></i></div>
            <div style="font-size: 2rem; font-weight: 800; color: white;"><?php echo $stats['total_today'] ?? 0; ?></div>
            <div style="color: rgba(255,255,255,0.8);">Total Today</div>
        </div>
        <div style="background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); padding: 25px; border-radius: 15px; text-align: center; border: 1px solid rgba(46, 204, 113, 0.3);">
            <div style="font-size: 2rem; color: #9b59b6; margin-bottom: 10px;"><i class="fas fa-film"></i></div>
            <div style="font-size: 2rem; font-weight: 800; color: white;"><?php echo count($upcoming_shows_list); ?></div>
            <div style="color: rgba(255,255,255,0.8);">Upcoming Shows (2hrs)</div>
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
        <a href="?page=staff/print-ticket" class="staff-action-btn" style="background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); padding: 30px; border-radius: 15px; text-decoration: none; text-align: center; color: white; transition: all 0.3s ease;">
            <i class="fas fa-print fa-3x" style="margin-bottom: 15px; display: block;"></i>
            <span style="font-size: 1.2rem; font-weight: 700;">Print Tickets</span>
            <p style="font-size: 0.85rem; margin-top: 10px; opacity: 0.9;">Today's show tickets</p>
        </a>
    </div>

    <!-- Upcoming Shows -->
    <div style="background: rgba(255, 255, 255, 0.05); border-radius: 15px; padding: 25px; border: 1px solid rgba(46, 204, 113, 0.2);">
        <h2 style="color: white; font-size: 1.5rem; margin-bottom: 20px;">
            <i class="fas fa-clock"></i> Upcoming Shows (Next 2 Hours)
        </h2>
        
        <?php if (empty($upcoming_shows_list)): ?>
        <div style="text-align: center; padding: 40px; color: rgba(255,255,255,0.6);">
            <i class="fas fa-calendar-alt fa-2x" style="margin-bottom: 10px; opacity: 0.5;"></i>
            <p>No upcoming shows in the next 2 hours</p>
        </div>
        <?php else: ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 15px;">
            <?php foreach ($upcoming_shows_list as $show): 
                $time_until = $show['time_until_show'];
                $hours = floor(strtotime($time_until) / 3600);
                $minutes = floor((strtotime($time_until) % 3600) / 60);
            ?>
            <div style="background: rgba(255,255,255,0.05); border-radius: 10px; padding: 20px; border-left: 4px solid #2ecc71;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <strong style="color: white; font-size: 1.1rem;"><?php echo htmlspecialchars($show['movie_title']); ?></strong>
                    <span style="background: #2ecc71; color: white; padding: 5px 10px; border-radius: 20px; font-size: 0.8rem;">
                        <?php echo date('h:i A', strtotime($show['showtime'])); ?>
                    </span>
                </div>
                <div style="color: rgba(255,255,255,0.7); font-size: 0.85rem;">
                    <i class="fas fa-hourglass-half"></i> In <?php echo $hours > 0 ? $hours . 'h ' : ''; ?><?php echo $minutes; ?> minutes
                </div>
                <a href="?page=staff/print-ticket&schedule=<?php echo $show['schedule_id']; ?>" style="margin-top: 15px; display: inline-block; background: rgba(46,204,113,0.2); color: #2ecc71; padding: 8px 15px; border-radius: 8px; text-decoration: none; font-size: 0.85rem;">
                    <i class="fas fa-print"></i> Print Tickets
                </a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.staff-action-btn:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.3);
}

@media (max-width: 768px) {
    .staff-container {
        padding: 15px;
    }
}
</style>

