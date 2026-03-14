<?php
$root_dir = dirname(dirname(__DIR__));
require_once $root_dir . '/includes/config.php';
require_once $root_dir . '/includes/functions.php';
require_once $root_dir . '/partials/admin-header.php';

$conn = get_db_connection();

$movie_count = 0;
$user_count = 0;
$booking_count = 0;
$schedule_count = 0;
$suggestion_count = 0;
$pending_payments = 0;

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

$result = $conn->query("SELECT COUNT(*) as count FROM manual_payments WHERE status = 'Pending'");
if ($result) $pending_payments = $result->fetch_assoc()['count'];
?>

<div class="admin-content" style="max-width: 1400px; margin: 0 auto; padding: 30px;">
    <div style="text-align: center; margin-bottom: 40px; padding: 30px; background: linear-gradient(135deg, rgba(52, 152, 219, 0.1), rgba(41, 128, 185, 0.2)); border-radius: 20px; border: 2px solid rgba(52, 152, 219, 0.3);">
        <h1 style="color: white; font-size: 2.5rem; margin-bottom: 10px; font-weight: 800;">
            Welcome, <?php echo $_SESSION['user_name']; ?>!
        </h1>
        <p style="color: rgba(255,255,255,0.8); font-size: 1.1rem;">
            Administrator Dashboard - Movie Ticket Booking
        </p>
    </div>
    
    <div class="admin-stats" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 40px;">
        <div class="stat-card" style="background: linear-gradient(135deg, rgba(52, 152, 219, 0.1), rgba(41, 128, 185, 0.2)); padding: 25px; border-radius: 15px; text-align: center; border: 2px solid rgba(52, 152, 219, 0.3); transition: all 0.3s ease;">
            <div style="font-size: 2.5rem; color: #3498db; margin-bottom: 10px;">
                <i class="fas fa-film"></i>
            </div>
            <div style="font-size: 2.5rem; font-weight: 800; color: white; margin-bottom: 5px;">
                <?php echo $movie_count; ?>
            </div>
            <div style="color: rgba(255,255,255,0.8); font-size: 1rem;">Active Movies</div>
        </div>
        
        <div class="stat-card" style="background: linear-gradient(135deg, rgba(52, 152, 219, 0.1), rgba(41, 128, 185, 0.2)); padding: 25px; border-radius: 15px; text-align: center; border: 2px solid rgba(52, 152, 219, 0.3); transition: all 0.3s ease;">
            <div style="font-size: 2.5rem; color: #3498db; margin-bottom: 10px;">
                <i class="fas fa-users"></i>
            </div>
            <div style="font-size: 2.5rem; font-weight: 800; color: white; margin-bottom: 5px;">
                <?php echo $user_count; ?>
            </div>
            <div style="color: rgba(255,255,255,0.8); font-size: 1rem;">Active Users</div>
        </div>
        
        <div class="stat-card" style="background: linear-gradient(135deg, rgba(52, 152, 219, 0.1), rgba(41, 128, 185, 0.2)); padding: 25px; border-radius: 15px; text-align: center; border: 2px solid rgba(52, 152, 219, 0.3); transition: all 0.3s ease;">
            <div style="font-size: 2.5rem; color: #3498db; margin-bottom: 10px;">
                <i class="fas fa-ticket-alt"></i>
            </div>
            <div style="font-size: 2.5rem; font-weight: 800; color: white; margin-bottom: 5px;">
                <?php echo $booking_count; ?>
            </div>
            <div style="color: rgba(255,255,255,0.8); font-size: 1rem;">Ongoing Bookings</div>
        </div>
        
        <div class="stat-card" style="background: linear-gradient(135deg, rgba(52, 152, 219, 0.1), rgba(41, 128, 185, 0.2)); padding: 25px; border-radius: 15px; text-align: center; border: 2px solid rgba(52, 152, 219, 0.3); transition: all 0.3s ease;">
            <div style="font-size: 2.5rem; color: #3498db; margin-bottom: 10px;">
                <i class="fas fa-calendar-alt"></i>
            </div>
            <div style="font-size: 2.5rem; font-weight: 800; color: white; margin-bottom: 5px;">
                <?php echo $schedule_count; ?>
            </div>
            <div style="color: rgba(255,255,255,0.8); font-size: 1rem;">Active Schedules</div>
        </div>
        
        <div class="stat-card" style="background: linear-gradient(135deg, rgba(52, 152, 219, 0.1), rgba(41, 128, 185, 0.2)); padding: 25px; border-radius: 15px; text-align: center; border: 2px solid rgba(52, 152, 219, 0.3); transition: all 0.3s ease;">
            <div style="font-size: 2.5rem; color: #3498db; margin-bottom: 10px;">
                <i class="fas fa-lightbulb"></i>
            </div>
            <div style="font-size: 2.5rem; font-weight: 800; color: white; margin-bottom: 5px;">
                <?php echo $suggestion_count; ?>
            </div>
            <div style="color: rgba(255,255,255,0.8); font-size: 1rem;">Pending Suggestions</div>
        </div>

        <div class="stat-card" style="background: linear-gradient(135deg, rgba(52, 152, 219, 0.1), rgba(41, 128, 185, 0.2)); padding: 25px; border-radius: 15px; text-align: center; border: 2px solid rgba(52, 152, 219, 0.3); transition: all 0.3s ease; position: relative;">
            <div style="font-size: 2.5rem; color: #3498db; margin-bottom: 10px;">
                <i class="fas fa-credit-card"></i>
            </div>
            <div style="font-size: 2.5rem; font-weight: 800; color: white; margin-bottom: 5px;">
                <?php echo $pending_payments; ?>
            </div>
            <div style="color: rgba(255,255,255,0.8); font-size: 1rem;">Pending Payments</div>
            <?php if ($pending_payments > 0): ?>
            <div style="position: absolute; top: 10px; right: 10px; background: #f39c12; color: white; padding: 5px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 700; animation: pulse 1.5s infinite;">
                <i class="fas fa-exclamation-circle"></i> Action Needed
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="admin-actions" style="margin-bottom: 50px;">
        <h2 style="color: white; margin-bottom: 25px; font-size: 1.8rem; font-weight: 700;">
            <i class="fas fa-bolt"></i> Quick Actions
        </h2>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
            <a href="<?php echo SITE_URL; ?>index.php?page=admin/manage-movies" 
               class="admin-action-btn" style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); padding: 25px; border-radius: 12px; text-decoration: none; text-align: center; color: white; font-weight: 600; transition: all 0.3s ease; display: flex; flex-direction: column; align-items: center; gap: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.2);">
                <i class="fas fa-film fa-2x"></i>
                <span>Manage Movies</span>
            </a>
            
            <a href="<?php echo SITE_URL; ?>index.php?page=admin/manage-schedules" 
               class="admin-action-btn" style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); padding: 25px; border-radius: 12px; text-decoration: none; text-align: center; color: white; font-weight: 600; transition: all 0.3s ease; display: flex; flex-direction: column; align-items: center; gap: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.2);">
                <i class="fas fa-calendar-alt fa-2x"></i>
                <span>Manage Schedules</span>
            </a>
            
            <a href="<?php echo SITE_URL; ?>index.php?page=admin/manage-users" 
               class="admin-action-btn" style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); padding: 25px; border-radius: 12px; text-decoration: none; text-align: center; color: white; font-weight: 600; transition: all 0.3s ease; display: flex; flex-direction: column; align-items: center; gap: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.2);">
                <i class="fas fa-users fa-2x"></i>
                <span>Manage Users</span>
            </a>

            <a href="<?php echo SITE_URL; ?>index.php?page=admin/manage-payments" 
               class="admin-action-btn" style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); padding: 25px; border-radius: 12px; text-decoration: none; text-align: center; color: white; font-weight: 600; transition: all 0.3s ease; display: flex; flex-direction: column; align-items: center; gap: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.2); position: relative;">
                <i class="fas fa-credit-card fa-2x"></i>
                <span>Manage Payments</span>
                <?php if ($pending_payments > 0): ?>
                <div style="position: absolute; top: 10px; right: 10px; background: #f39c12; color: white; padding: 5px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 700;">
                    <?php echo $pending_payments; ?> Pending
                </div>
                <?php endif; ?>
            </a>
            
            <a href="<?php echo SITE_URL; ?>index.php?page=admin/manage-suggestions" 
               class="admin-action-btn" style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); padding: 25px; border-radius: 12px; text-decoration: none; text-align: center; color: white; font-weight: 600; transition: all 0.3s ease; display: flex; flex-direction: column; align-items: center; gap: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.2);">
                <i class="fas fa-lightbulb fa-2x"></i>
                <span>Manage Suggestions</span>
                <?php if ($suggestion_count > 0): ?>
                <div style="position: absolute; top: 10px; right: 10px; background: #f39c12; color: white; padding: 5px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 700;">
                    <?php echo $suggestion_count; ?> New
                </div>
                <?php endif; ?>
            </a>
            
            <a href="<?php echo SITE_URL; ?>" 
               class="admin-action-btn" style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); padding: 25px; border-radius: 12px; text-decoration: none; text-align: center; color: white; font-weight: 600; transition: all 0.3s ease; display: flex; flex-direction: column; align-items: center; gap: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.2);">
                <i class="fas fa-home fa-2x"></i>
                <span>View Site</span>
            </a>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 30px;">
        <div style="background: rgba(255, 255, 255, 0.05); border-radius: 15px; padding: 25px; border: 1px solid rgba(52, 152, 219, 0.2);">
            <h3 style="color: white; font-size: 1.3rem; margin-bottom: 20px; font-weight: 600; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-info-circle" style="color: #3498db;"></i> System Information
            </h3>
            <div style="display: grid; gap: 15px;">
                <div style="background: rgba(0,0,0,0.2); padding: 15px; border-radius: 8px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <span style="color: rgba(255,255,255,0.7);">PHP Version:</span>
                        <span style="color: white; font-weight: 600;"><?php echo phpversion(); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <span style="color: rgba(255,255,255,0.7);">Server Time:</span>
                        <span style="color: white; font-weight: 600;"><?php echo date('Y-m-d H:i:s'); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: rgba(255,255,255,0.7);">Database:</span>
                        <span style="color: #2ecc71; font-weight: 600;">Connected</span>
                    </div>
                </div>
            </div>
        </div>

        <div style="background: rgba(255, 255, 255, 0.05); border-radius: 15px; padding: 25px; border: 1px solid rgba(52, 152, 219, 0.2);">
            <h3 style="color: white; font-size: 1.3rem; margin-bottom: 20px; font-weight: 600; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-chart-line" style="color: #3498db;"></i> Quick Stats
            </h3>
            <div style="display: grid; gap: 10px;">
                <div style="display: flex; justify-content: space-between; padding: 10px; background: rgba(0,0,0,0.2); border-radius: 5px;">
                    <span style="color: rgba(255,255,255,0.8);">Movies:</span>
                    <span style="color: #3498db; font-weight: 600;"><?php echo $movie_count; ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; padding: 10px; background: rgba(0,0,0,0.2); border-radius: 5px;">
                    <span style="color: rgba(255,255,255,0.8);">Users:</span>
                    <span style="color: #3498db; font-weight: 600;"><?php echo $user_count; ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; padding: 10px; background: rgba(0,0,0,0.2); border-radius: 5px;">
                    <span style="color: rgba(255,255,255,0.8);">Active Bookings:</span>
                    <span style="color: #3498db; font-weight: 600;"><?php echo $booking_count; ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; padding: 10px; background: rgba(0,0,0,0.2); border-radius: 5px;">
                    <span style="color: rgba(255,255,255,0.8);">Schedules:</span>
                    <span style="color: #3498db; font-weight: 600;"><?php echo $schedule_count; ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; padding: 10px; background: rgba(0,0,0,0.2); border-radius: 5px;">
                    <span style="color: rgba(255,255,255,0.8);">Pending Suggestions:</span>
                    <span style="color: #3498db; font-weight: 600;"><?php echo $suggestion_count; ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; padding: 10px; background: rgba(0,0,0,0.2); border-radius: 5px;">
                    <span style="color: rgba(255,255,255,0.8);">Pending Payments:</span>
                    <span style="color: #3498db; font-weight: 600;"><?php echo $pending_payments; ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 30px rgba(0,0,0,0.2) !important;
        border-color: #3498db !important;
    }
    
    .admin-action-btn:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 30px rgba(0,0,0,0.3) !important;
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
    
    @media (max-width: 768px) {
        .admin-stats {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .admin-actions > div {
            grid-template-columns: repeat(2, 1fr);
        }
        
        h1 {
            font-size: 2rem !important;
        }
    }
    
    @media (max-width: 576px) {
        .admin-stats {
            grid-template-columns: 1fr;
        }
        
        .admin-actions > div {
            grid-template-columns: 1fr;
        }
    }
</style>

</div>
</body>
</html>