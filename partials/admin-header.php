<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || ($_SESSION['user_role'] !== 'Admin' && $_SESSION['user_role'] !== 'Owner')) {
    header("Location: " . SITE_URL . "index.php?page=login");
    exit();
}

if (!defined('SITE_URL')) {
    define('SITE_URL', 'http://localhost/Movie_Booking/');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Movie Ticketing</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
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
        
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
        }
        
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--admin-dark) 0%, var(--admin-primary) 100%);
            color: white; 
            min-height: 100vh;
        }
        
        .admin-header {
            background: linear-gradient(135deg, var(--admin-primary) 0%, var(--admin-secondary) 100%);
            padding: 15px 0;
            border-bottom: 2px solid var(--admin-accent);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .admin-header-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .admin-logo {
            display: flex;
            align-items: center;
            gap: 15px;
            text-decoration: none;
        }
        
        .admin-logo-icon {
            font-size: 2.2rem;
            color: var(--admin-accent);
        }
        
        .admin-logo-text {
            display: flex;
            flex-direction: column;
        }
        
        .admin-logo-title {
            font-size: 1.6rem;
            font-weight: 800;
            color: white;
            line-height: 1.2;
        }
        
        .admin-logo-subtitle {
            font-size: 0.85rem;
            color: var(--admin-light);
            font-weight: 500;
        }
        
        .admin-nav {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .admin-nav-link {
            color: white;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            padding: 8px 16px;
            border-radius: 8px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .admin-nav-link:hover {
            background: rgba(52, 152, 219, 0.2);
            transform: translateY(-2px);
        }
        
        .admin-nav-link.active {
            background: linear-gradient(135deg, var(--admin-accent) 0%, #2980b9 100%);
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
        }

        .payment-badge {
            background: var(--admin-warning);
            color: #333;
            font-size: 0.7rem;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 10px;
            margin-left: 5px;
        }
        
        .admin-user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .admin-user-name {
            font-weight: 600;
            color: white;
            font-size: 0.95rem;
        }
        
        .admin-role-badge {
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            background: linear-gradient(135deg, var(--admin-accent) 0%, #2980b9 100%);
            color: white;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .admin-btn {
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
        }
        
        .admin-btn-primary {
            background: linear-gradient(135deg, var(--admin-accent) 0%, #2980b9 100%);
            color: white;
            box-shadow: 0 4px 10px rgba(52, 152, 219, 0.3);
        }
        
        .admin-btn-primary:hover {
            background: linear-gradient(135deg, #2980b9 0%, #1f639b 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(52, 152, 219, 0.4);
        }
        
        .admin-btn-danger {
            background: linear-gradient(135deg, var(--admin-danger) 0%, #c0392b 100%);
            color: white;
            box-shadow: 0 4px 10px rgba(231, 76, 60, 0.3);
        }
        
        .admin-btn-danger:hover {
            background: linear-gradient(135deg, #c0392b 0%, #a93226 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(231, 76, 60, 0.4);
        }
        
        .admin-btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: 2px solid rgba(52, 152, 219, 0.3);
        }
        
        .admin-btn-secondary:hover {
            background: rgba(52, 152, 219, 0.2);
            border-color: var(--admin-accent);
            transform: translateY(-2px);
        }
        
        @media (max-width: 992px) {
            .admin-header-container {
                flex-direction: column;
                text-align: center;
            }
            
            .admin-nav {
                justify-content: center;
            }
            
            .admin-user-info {
                justify-content: center;
            }
        }
        
        @media (max-width: 768px) {
            .admin-nav-link {
                padding: 6px 12px;
                font-size: 0.85rem;
            }
            
            .admin-logo-title {
                font-size: 1.4rem;
            }
            
            .admin-user-info {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <header class="admin-header">
        <div class="admin-header-container">
            <a href="<?php echo SITE_URL; ?>index.php?page=admin/dashboard" class="admin-logo">
                <div class="admin-logo-icon">🎬</div>
                <div class="admin-logo-text">
                    <div class="admin-logo-title">ADMIN PANEL</div>
                    <div class="admin-logo-subtitle">Movie Ticket Booking</div>
                </div>
            </a>
            
            <nav class="admin-nav">
                <?php
                $current_page = isset($_GET['page']) ? $_GET['page'] : 'admin/dashboard';
                $admin_section = explode('/', $current_page)[1] ?? 'dashboard';
                
                $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
                $pending_count = 0;
                if (!$conn->connect_error) {
                    $result = $conn->query("SELECT COUNT(*) as count FROM manual_payments WHERE status = 'pending'");
                    if ($result) {
                        $pending_count = $result->fetch_assoc()['count'];
                    }
                    $conn->close();
                }
                ?>
                
                <!-- Step 1: Add Venue -->
                <a href="<?php echo SITE_URL; ?>index.php?page=admin/manage-venues" 
                   class="admin-nav-link <?php echo $admin_section == 'manage-venues' ? 'active' : ''; ?>">
                    <i class="fas fa-building"></i> Add Venue
                </a>
                
                <!-- Step 2: Add Movie -->
                <a href="<?php echo SITE_URL; ?>index.php?page=admin/manage-movies" 
                   class="admin-nav-link <?php echo $admin_section == 'manage-movies' ? 'active' : ''; ?>">
                    <i class="fas fa-film"></i> Add Movie
                </a>
                
                <!-- Step 3: Add Seats (Customizable) -->
                <a href="<?php echo SITE_URL; ?>index.php?page=admin/manage-seats" 
                   class="admin-nav-link <?php echo $admin_section == 'manage-seats' ? 'active' : ''; ?>">
                    <i class="fas fa-chair"></i> Add Seats
                </a>
                
                <!-- Step 4: Add Schedules -->
                <a href="<?php echo SITE_URL; ?>index.php?page=admin/manage-schedules" 
                   class="admin-nav-link <?php echo $admin_section == 'manage-schedules' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-alt"></i> Add Schedules
                </a>
                
                <!-- Manage Users -->
                <a href="<?php echo SITE_URL; ?>index.php?page=admin/manage-users&type=customer" 
                   class="admin-nav-link <?php echo $admin_section == 'manage-users' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i> Manage Users
                </a>

                <!-- Manage Payments -->
                <a href="<?php echo SITE_URL; ?>index.php?page=admin/manage-payments" 
                   class="admin-nav-link <?php echo $admin_section == 'manage-payments' ? 'active' : ''; ?>">
                    <i class="fas fa-credit-card"></i> Payments
                    <?php if ($pending_count > 0): ?>
                    <span class="payment-badge"><?php echo $pending_count; ?></span>
                    <?php endif; ?>
                </a>
                
                <!-- View Site -->
                <a href="<?php echo SITE_URL; ?>" 
                   class="admin-nav-link">
                    <i class="fas fa-home"></i> View Site
                </a>
            </nav>
            
            <div class="admin-user-info">
                <span class="admin-user-name"><?php echo $_SESSION['user_name']; ?></span>
                <span class="admin-role-badge" style="<?php echo (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'Owner') ? 'background: linear-gradient(135deg, #ffd700 0%, #ffaa00 100%); color: #333;' : ''; ?>">
                    <?php echo $_SESSION['user_role'] ?? 'Admin'; ?>
                    <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'Owner'): ?>
                    <i class="fas fa-crown" style="margin-left: 5px;"></i>
                    <?php endif; ?>
                </span>
                <a href="<?php echo SITE_URL; ?>index.php?page=logout" class="admin-btn admin-btn-danger">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </header>
    
    <div class="admin-main-container" style="max-width: 1400px; margin: 0 auto; padding: 20px;">