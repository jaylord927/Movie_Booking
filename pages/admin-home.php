<?php
$root_dir = dirname(__DIR__);
require_once $root_dir . '/includes/config.php';
require_once $root_dir . '/includes/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Admin') {
    header("Location: " . SITE_URL . "index.php?page=login");
    exit();
}

require_once $root_dir . '/partials/admin-header.php';

$conn = get_db_connection();

$movie_count = $user_count = $booking_count = $schedule_count = 0;
$recent_movies = [];
$recent_bookings = [];

$result = $conn->query("SELECT COUNT(*) as count FROM movies WHERE is_active = 1");
if ($result) $movie_count = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE u_status = 'Active'");
if ($result) $user_count = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM tbl_booking WHERE status = 'Ongoing'");
if ($result) $booking_count = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM movie_schedules WHERE is_active = 1");
if ($result) $schedule_count = $result->fetch_assoc()['count'];

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

$result = $conn->query("
    SELECT 
        b.*,
        u.u_name as customer_name,
        GROUP_CONCAT(bs.seat_number ORDER BY bs.seat_number SEPARATOR ', ') as seat_list,
        COUNT(bs.id) as total_seats
    FROM tbl_booking b
    LEFT JOIN users u ON b.u_id = u.u_id
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

$conn->close();
?>

<div class="admin-home-container" style="max-width: 1400px; margin: 0 auto; padding: 30px;">
    <div class="admin-welcome-section" style="text-align: center; margin-bottom: 50px; padding: 30px;
          background: linear-gradient(135deg, rgba(52, 152, 219, 0.1), rgba(41, 128, 185, 0.2));
          border-radius: 20px; border: 2px solid rgba(52, 152, 219, 0.3);">
        <h1 style="color: white; font-size: 2.8rem; margin-bottom: 15px; font-weight: 800;">
            Welcome Back, <?php echo $_SESSION['user_name']; ?>!
        </h1>
        <p style="color: rgba(255, 255, 255, 0.8); font-size: 1.2rem; max-width: 600px; margin: 0 auto;">
            Administrator Dashboard - Manage your movie ticketing system
        </p>
    </div>

    <div class="admin-stats" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 25px; margin-bottom: 50px;">
        <div class="stat-card" style="background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); 
              padding: 25px; border-radius: 15px; text-align: center; border: 1px solid rgba(52, 152, 219, 0.3);
              transition: all 0.3s ease;">
            <div style="font-size: 2.5rem; color: #3498db; margin-bottom: 10px;">
                <i class="fas fa-film"></i>
            </div>
            <div style="font-size: 2.5rem; font-weight: bold; color: white; margin-bottom: 5px;">
                <?php echo $movie_count; ?>
            </div>
            <div style="color: #ecf0f1; font-size: 1rem;">Active Movies</div>
        </div>
        
        <div class="stat-card" style="background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); 
              padding: 25px; border-radius: 15px; text-align: center; border: 1px solid rgba(52, 152, 219, 0.3);
              transition: all 0.3s ease;">
            <div style="font-size: 2.5rem; color: #3498db; margin-bottom: 10px;">
                <i class="fas fa-users"></i>
            </div>
            <div style="font-size: 2.5rem; font-weight: bold; color: white; margin-bottom: 5px;">
                <?php echo $user_count; ?>
            </div>
            <div style="color: #ecf0f1; font-size: 1rem;">Active Users</div>
        </div>
        
        <div class="stat-card" style="background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); 
              padding: 25px; border-radius: 15px; text-align: center; border: 1px solid rgba(52, 152, 219, 0.3);
              transition: all 0.3s ease;">
            <div style="font-size: 2.5rem; color: #3498db; margin-bottom: 10px;">
                <i class="fas fa-ticket-alt"></i>
            </div>
            <div style="font-size: 2.5rem; font-weight: bold; color: white; margin-bottom: 5px;">
                <?php echo $booking_count; ?>
            </div>
            <div style="color: #ecf0f1; font-size: 1rem;">Ongoing Bookings</div>
        </div>
        
        <div class="stat-card" style="background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); 
              padding: 25px; border-radius: 15px; text-align: center; border: 1px solid rgba(52, 152, 219, 0.3);
              transition: all 0.3s ease;">
            <div style="font-size: 2.5rem; color: #3498db; margin-bottom: 10px;">
                <i class="fas fa-calendar-alt"></i>
            </div>
            <div style="font-size: 2.5rem; font-weight: bold; color: white; margin-bottom: 5px;">
                <?php echo $schedule_count; ?>
            </div>
            <div style="color: #ecf0f1; font-size: 1rem;">Active Schedules</div>
        </div>
    </div>

    <div class="admin-actions" style="margin-bottom: 50px;">
        <h2 style="color: white; margin-bottom: 25px; font-size: 1.8rem; font-weight: 700;">
            <i class="fas fa-bolt"></i> Quick Actions
        </h2>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
            <a href="<?php echo SITE_URL; ?>index.php?page=admin/manage-movies" 
               class="admin-action-btn" style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); 
                      padding: 25px; border-radius: 12px; text-decoration: none; text-align: center;
                      color: white; font-weight: 600; transition: all 0.3s ease; display: flex;
                      flex-direction: column; align-items: center; gap: 15px;">
                <i class="fas fa-film fa-2x"></i>
                <span>Manage Movies</span>
            </a>
            
            <a href="<?php echo SITE_URL; ?>index.php?page=admin/manage-schedules" 
               class="admin-action-btn" style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); 
                      padding: 25px; border-radius: 12px; text-decoration: none; text-align: center;
                      color: white; font-weight: 600; transition: all 0.3s ease; display: flex;
                      flex-direction: column; align-items: center; gap: 15px;">
                <i class="fas fa-calendar-alt fa-2x"></i>
                <span>Manage Schedules</span>
            </a>
            
            <a href="<?php echo SITE_URL; ?>index.php?page=admin/manage-users" 
               class="admin-action-btn" style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); 
                      padding: 25px; border-radius: 12px; text-decoration: none; text-align: center;
                      color: white; font-weight: 600; transition: all 0.3s ease; display: flex;
                      flex-direction: column; align-items: center; gap: 15px;">
                <i class="fas fa-users fa-2x"></i>
                <span>Manage Users</span>
            </a>
            
            <a href="<?php echo SITE_URL; ?>index.php?page=admin/manage-payments" 
               class="admin-action-btn" style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); 
                      padding: 25px; border-radius: 12px; text-decoration: none; text-align: center;
                      color: white; font-weight: 600; transition: all 0.3s ease; display: flex;
                      flex-direction: column; align-items: center; gap: 15px;">
                <i class="fas fa-credit-card fa-2x"></i>
                <span>Manage Payments</span>
            </a>
            
            <a href="<?php echo SITE_URL; ?>" target="_blank"
               class="admin-action-btn" style="background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%); 
                      padding: 25px; border-radius: 12px; text-decoration: none; text-align: center;
                      color: white; font-weight: 600; transition: all 0.3s ease; display: flex;
                      flex-direction: column; align-items: center; gap: 15px;">
                <i class="fas fa-globe fa-2x"></i>
                <span>View Site</span>
            </a>
        </div>
    </div>

    <div class="recent-activity" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(500px, 1fr)); gap: 30px; margin-bottom: 50px;">
        <div class="recent-movies" style="background: rgba(255, 255, 255, 0.05); border-radius: 15px; padding: 25px;
              border: 1px solid rgba(52, 152, 219, 0.2);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="color: white; font-size: 1.5rem; font-weight: 700;">
                    <i class="fas fa-film"></i> Recent Movies
                </h3>
                <a href="<?php echo SITE_URL; ?>index.php?page=admin/manage-movies" 
                   style="color: #3498db; text-decoration: none; font-weight: 600; font-size: 0.9rem;">
                    View All <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            
            <?php if (empty($recent_movies)): ?>
                <div style="text-align: center; padding: 20px; color: rgba(255, 255, 255, 0.6);">
                    <i class="fas fa-film fa-2x" style="margin-bottom: 10px; opacity: 0.5;"></i>
                    <p>No movies added yet</p>
                </div>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 15px;">
                    <?php foreach ($recent_movies as $movie): ?>
                    <div style="display: flex; align-items: center; gap: 15px; padding: 15px; 
                          background: rgba(255, 255, 255, 0.03); border-radius: 10px;
                          border: 1px solid rgba(255, 255, 255, 0.1); transition: all 0.3s ease;">
                        <?php if (!empty($movie['poster_url'])): ?>
                        <img src="<?php echo $movie['poster_url']; ?>" 
                             alt="<?php echo htmlspecialchars($movie['title']); ?>"
                             style="width: 60px; height: 80px; object-fit: cover; border-radius: 5px;">
                        <?php else: ?>
                        <div style="width: 60px; height: 80px; background: rgba(52, 152, 219, 0.1); 
                              border-radius: 5px; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-film" style="color: rgba(255, 255, 255, 0.3); font-size: 1.5rem;"></i>
                        </div>
                        <?php endif; ?>
                        
                        <div style="flex: 1;">
                            <div style="color: white; font-weight: 600; margin-bottom: 5px;">
                                <?php echo htmlspecialchars($movie['title']); ?>
                            </div>
                            <div style="display: flex; gap: 15px; font-size: 0.85rem;">
                                <span style="color: rgba(255, 255, 255, 0.7);">
                                    <i class="fas fa-tag"></i> <?php echo $movie['genre'] ?? 'N/A'; ?>
                                </span>
                                <span style="color: rgba(255, 255, 255, 0.7);">
                                    <i class="fas fa-star"></i> <?php echo $movie['rating'] ?? 'PG'; ?>
                                </span>
                            </div>
                        </div>
                        
                        <div style="text-align: right;">
                            <div style="font-size: 0.8rem; color: rgba(255, 255, 255, 0.6);">
                                Added by: <?php echo $movie['added_by_name'] ?? 'Admin'; ?>
                            </div>
                            <a href="<?php echo SITE_URL; ?>index.php?page=admin/manage-movies&edit=<?php echo $movie['id']; ?>" 
                               style="color: #3498db; text-decoration: none; font-size: 0.85rem; margin-top: 5px; display: inline-block;">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="recent-bookings" style="background: rgba(255, 255, 255, 0.05); border-radius: 15px; padding: 25px;
              border: 1px solid rgba(52, 152, 219, 0.2);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="color: white; font-size: 1.5rem; font-weight: 700;">
                    <i class="fas fa-ticket-alt"></i> Recent Bookings
                </h3>
                <a href="<?php echo SITE_URL; ?>index.php?page=admin/manage-payments" 
                   style="color: #3498db; text-decoration: none; font-weight: 600; font-size: 0.9rem;">
                    View All <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            
            <?php if (empty($recent_bookings)): ?>
                <div style="text-align: center; padding: 20px; color: rgba(255, 255, 255, 0.6);">
                    <i class="fas fa-ticket-alt fa-2x" style="margin-bottom: 10px; opacity: 0.5;"></i>
                    <p>No recent bookings</p>
                </div>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 15px;">
                    <?php foreach ($recent_bookings as $booking): 
                        $seat_list = $booking['seat_list'] ?? 'No seats';
                        $total_seats = $booking['total_seats'] ?? 0;
                    ?>
                    <div style="padding: 15px; background: rgba(255, 255, 255, 0.03); border-radius: 10px;
                          border: 1px solid rgba(255, 255, 255, 0.1);">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <div style="color: white; font-weight: 600;">
                                <?php echo htmlspecialchars($booking['movie_name'] ?? 'Unknown Movie'); ?>
                            </div>
                            <span style="background: #2ecc71; color: white; padding: 3px 10px; border-radius: 15px;
                                  font-size: 0.75rem; font-weight: 600;">
                                <?php echo $booking['status'] ?? 'Ongoing'; ?>
                            </span>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; font-size: 0.85rem;">
                            <div style="color: rgba(255, 255, 255, 0.7);">
                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($booking['customer_name'] ?? 'Unknown'); ?>
                            </div>
                            <div style="color: rgba(255, 255, 255, 0.7);">
                                <i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($booking['show_date'] ?? 'now')); ?>
                            </div>
                            <div style="color: rgba(255, 255, 255, 0.7);">
                                <i class="fas fa-clock"></i> <?php echo date('h:i A', strtotime($booking['showtime'] ?? '00:00:00')); ?>
                            </div>
                            <div style="color: rgba(255, 255, 255, 0.7);">
                                <i class="fas fa-chair"></i> Seats: <?php echo htmlspecialchars($seat_list); ?> (<?php echo $total_seats; ?>)
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="system-info" style="background: rgba(255, 255, 255, 0.05); border-radius: 15px; padding: 25px;
          border: 1px solid rgba(52, 152, 219, 0.2);">
        <h3 style="color: white; margin-bottom: 20px; font-size: 1.5rem; font-weight: 700;">
            <i class="fas fa-info-circle"></i> System Information
        </h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
            <div>
                <h4 style="color: #3498db; margin-bottom: 10px; font-size: 1rem;">Server Information</h4>
                <div style="background: rgba(255, 255, 255, 0.03); padding: 15px; border-radius: 10px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <span style="color: rgba(255, 255, 255, 0.7);">PHP Version:</span>
                        <span style="color: white; font-weight: 600;"><?php echo phpversion(); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <span style="color: rgba(255, 255, 255, 0.7);">Server Time:</span>
                        <span style="color: white; font-weight: 600;"><?php echo date('Y-m-d H:i:s'); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: rgba(255, 255, 255, 0.7);">Session:</span>
                        <span style="color: white; font-weight: 600;">Active</span>
                    </div>
                </div>
            </div>
            
            <div>
                <h4 style="color: #3498db; margin-bottom: 10px; font-size: 1rem;">Quick Links</h4>
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                    <a href="<?php echo SITE_URL; ?>" target="_blank"
                       style="background: rgba(255, 255, 255, 0.1); color: white; text-decoration: none;
                              padding: 12px; border-radius: 8px; text-align: center; font-size: 0.9rem;
                              transition: all 0.3s ease;">
                        <i class="fas fa-globe"></i> Public Site
                    </a>
                    <a href="<?php echo SITE_URL; ?>index.php?page=logout" 
                       style="background: rgba(231, 76, 60, 0.2); color: #e74c3c; text-decoration: none;
                              padding: 12px; border-radius: 8px; text-align: center; font-size: 0.9rem;
                              transition: all 0.3s ease;">
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
    
    .admin-action-btn:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(52, 152, 219, 0.3);
    }
    
    .recent-movies div:hover,
    .recent-bookings div:hover {
        transform: translateX(5px);
        background: rgba(255, 255, 255, 0.05);
        border-color: rgba(52, 152, 219, 0.3);
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
        }
        
        .admin-actions > div {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .admin-welcome-section h1 {
            font-size: 2.2rem;
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
        
        .admin-home-container {
            padding: 15px;
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
        
        const actionBtns = document.querySelectorAll('.admin-action-btn');
        actionBtns.forEach((btn, index) => {
            btn.style.animationDelay = `${index * 0.1}s`;
            btn.style.animation = 'fadeInUp 0.5s ease forwards';
            btn.style.opacity = '0';
        });
        
        const recentItems = document.querySelectorAll('.recent-movies > div > div, .recent-bookings > div > div');
        recentItems.forEach((item, index) => {
            item.style.animationDelay = `${index * 0.05}s`;
            item.style.animation = 'fadeInUp 0.3s ease forwards';
            item.style.opacity = '0';
        });
        
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
            .admin-action-btn,
            .recent-movies > div > div,
            .recent-bookings > div > div {
                transition: all 0.3s ease;
            }
        `;
        document.head.appendChild(style);
        
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
                window.location.href = "<?php echo SITE_URL; ?>index.php?page=admin/manage-users";
            }
            
            if (e.ctrlKey && e.key === '4') {
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