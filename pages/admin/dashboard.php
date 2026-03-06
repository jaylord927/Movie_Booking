<?php
$root_dir = dirname(dirname(__DIR__));
require_once $root_dir . '/includes/config.php';
require_once $root_dir . '/includes/functions.php';
require_once $root_dir . '/partials/admin-header.php';

$conn = get_db_connection();

$movie_count = $user_count = $booking_count = $schedule_count = $suggestion_count = 0;

$result = $conn->query("SELECT COUNT(*) as count FROM movies WHERE is_active = 1");
if ($result) $movie_count = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE u_status = 'Active'");
if ($result) $user_count = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM tbl_booking WHERE status = 'Ongoing'");
if ($result) $booking_count = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM movie_schedules WHERE is_active = 1");
if ($result) $schedule_count = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM suggestions WHERE status = 'Pending'");
if ($result) $suggestion_count = $result->fetch_assoc()['count'];

$conn->close();
?>

<div class="admin-content">
    <div class="admin-welcome" style="text-align: center; margin-bottom: 40px;">
        <h1 style="color: white; font-size: 2.5rem; margin-bottom: 10px;">
            Welcome, <?php echo $_SESSION['user_name']; ?>!
        </h1>
        <p style="color: var(--admin-light); font-size: 1.1rem;">
            Admin Dashboard - Movie Ticket Booking
        </p>
    </div>
    
    <div class="admin-stats" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 40px;">
        <div class="stat-card" style="background: linear-gradient(135deg, var(--admin-primary) 0%, var(--admin-secondary) 100%); padding: 25px; border-radius: 15px; text-align: center; border: 1px solid rgba(52, 152, 219, 0.3);">
            <div style="font-size: 2.5rem; color: var(--admin-accent); margin-bottom: 10px;">
                <i class="fas fa-film"></i>
            </div>
            <div style="font-size: 2.5rem; font-weight: bold; color: white; margin-bottom: 5px;">
                <?php echo $movie_count; ?>
            </div>
            <div style="color: var(--admin-light); font-size: 1rem;">Active Movies</div>
        </div>
        
        <div class="stat-card" style="background: linear-gradient(135deg, var(--admin-primary) 0%, var(--admin-secondary) 100%); padding: 25px; border-radius: 15px; text-align: center; border: 1px solid rgba(52, 152, 219, 0.3);">
            <div style="font-size: 2.5rem; color: var(--admin-accent); margin-bottom: 10px;">
                <i class="fas fa-users"></i>
            </div>
            <div style="font-size: 2.5rem; font-weight: bold; color: white; margin-bottom: 5px;">
                <?php echo $user_count; ?>
            </div>
            <div style="color: var(--admin-light); font-size: 1rem;">Active Users</div>
        </div>
        
        <div class="stat-card" style="background: linear-gradient(135deg, var(--admin-primary) 0%, var(--admin-secondary) 100%); padding: 25px; border-radius: 15px; text-align: center; border: 1px solid rgba(52, 152, 219, 0.3);">
            <div style="font-size: 2.5rem; color: var(--admin-accent); margin-bottom: 10px;">
                <i class="fas fa-ticket-alt"></i>
            </div>
            <div style="font-size: 2.5rem; font-weight: bold; color: white; margin-bottom: 5px;">
                <?php echo $booking_count; ?>
            </div>
            <div style="color: var(--admin-light); font-size: 1rem;">Ongoing Bookings</div>
        </div>
        
        <div class="stat-card" style="background: linear-gradient(135deg, var(--admin-primary) 0%, var(--admin-secondary) 100%); padding: 25px; border-radius: 15px; text-align: center; border: 1px solid rgba(52, 152, 219, 0.3);">
            <div style="font-size: 2.5rem; color: var(--admin-accent); margin-bottom: 10px;">
                <i class="fas fa-calendar-alt"></i>
            </div>
            <div style="font-size: 2.5rem; font-weight: bold; color: white; margin-bottom: 5px;">
                <?php echo $schedule_count; ?>
            </div>
            <div style="color: var(--admin-light); font-size: 1rem;">Active Schedules</div>
        </div>
        
        <div class="stat-card" style="background: linear-gradient(135deg, var(--admin-primary) 0%, var(--admin-secondary) 100%); padding: 25px; border-radius: 15px; text-align: center; border: 1px solid rgba(52, 152, 219, 0.3);">
            <div style="font-size: 2.5rem; color: var(--admin-accent); margin-bottom: 10px;">
                <i class="fas fa-lightbulb"></i>
            </div>
            <div style="font-size: 2.5rem; font-weight: bold; color: white; margin-bottom: 5px;">
                <?php echo $suggestion_count; ?>
            </div>
            <div style="color: var(--admin-light); font-size: 1rem;">Pending Suggestions</div>
        </div>
    </div>
    
    <div class="admin-actions" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
        <a href="<?php echo SITE_URL; ?>index.php?page=admin/manage-movies" 
           class="admin-btn admin-btn-primary" style="padding: 20px; text-align: center; text-decoration: none; display: flex; flex-direction: column; align-items: center; gap: 10px; background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; border-radius: 12px; transition: all 0.3s ease;">
            <i class="fas fa-film fa-2x"></i>
            <span>Manage Movies</span>
        </a>
        
        <a href="<?php echo SITE_URL; ?>index.php?page=admin/manage-schedules" 
           class="admin-btn admin-btn-primary" style="padding: 20px; text-align: center; text-decoration: none; display: flex; flex-direction: column; align-items: center; gap: 10px; background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; border-radius: 12px; transition: all 0.3s ease;">
            <i class="fas fa-calendar-alt fa-2x"></i>
            <span>Manage Schedules</span>
        </a>
        
        <a href="<?php echo SITE_URL; ?>index.php?page=admin/manage-users" 
           class="admin-btn admin-btn-primary" style="padding: 20px; text-align: center; text-decoration: none; display: flex; flex-direction: column; align-items: center; gap: 10px; background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; border-radius: 12px; transition: all 0.3s ease;">
            <i class="fas fa-users fa-2x"></i>
            <span>Manage Users</span>
        </a>
        
        <a href="<?php echo SITE_URL; ?>index.php?page=admin/manage-suggestions" 
           class="admin-btn admin-btn-primary" style="padding: 20px; text-align: center; text-decoration: none; display: flex; flex-direction: column; align-items: center; gap: 10px; background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; border-radius: 12px; transition: all 0.3s ease;">
            <i class="fas fa-lightbulb fa-2x"></i>
            <span>Manage Suggestions</span>
            <?php if ($suggestion_count > 0): ?>
            <span style="background: white; color: #2980b9; padding: 2px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 700; margin-top: 5px;">
                <?php echo $suggestion_count; ?> Pending
            </span>
            <?php endif; ?>
        </a>
        
        <a href="<?php echo SITE_URL; ?>" 
           class="admin-btn admin-btn-secondary" style="padding: 20px; text-align: center; text-decoration: none; display: flex; flex-direction: column; align-items: center; gap: 10px; background: rgba(255, 255, 255, 0.1); color: white; border: 2px solid rgba(52, 152, 219, 0.3); border-radius: 12px; transition: all 0.3s ease;">
            <i class="fas fa-home fa-2x"></i>
            <span>View Site</span>
        </a>
    </div>
</div>

<style>
.admin-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(52, 152, 219, 0.3);
}

.admin-btn-secondary:hover {
    background: rgba(52, 152, 219, 0.2);
    border-color: var(--admin-accent);
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
</style>

</div>
</body> 
</html>         