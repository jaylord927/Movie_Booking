<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Staff') {
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
    <title>Staff Panel - Movie Ticketing</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --teal-primary: #008080;
            --teal-dark: #006666;
            --teal-light: #20b2aa;
            --teal-accent: #00ced1;
            --teal-soft: #e0f7fa;
            --teal-gradient-start: #008080;
            --teal-gradient-end: #006666;
            --bg-dark: #0f0f23;
            --bg-darker: #1a1a2e;
        }
        
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
        }
        
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--bg-dark) 0%, var(--bg-darker) 100%);
            color: white; 
            min-height: 100vh;
        }
        
        .staff-header {
            background: linear-gradient(135deg, var(--teal-gradient-start) 0%, var(--teal-gradient-end) 100%);
            padding: 15px 0;
            border-bottom: 3px solid var(--teal-accent);
            box-shadow: 0 4px 20px rgba(0, 128, 128, 0.3);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .staff-header-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .staff-logo {
            display: flex;
            align-items: center;
            gap: 15px;
            text-decoration: none;
        }
        
        .staff-logo-icon {
            font-size: 2.5rem;
            color: white;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }
        
        .staff-logo-text {
            display: flex;
            flex-direction: column;
        }
        
        .staff-logo-title {
            font-size: 1rem;
            font-weight: 500;
            color: rgba(255, 255, 255, 0.85);
            line-height: 1.3;
            letter-spacing: 0.5px;
        }
        
        .staff-logo-subtitle {
            font-size: 1.5rem;
            font-weight: 800;
            color: white;
            line-height: 1.2;
            letter-spacing: 1px;
        }
        
        .staff-nav {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .staff-nav-link {
            color: white;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            padding: 8px 16px;
            border-radius: 25px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.1);
        }
        
        .staff-nav-link:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }
        
        .staff-nav-link.active {
            background: white;
            color: var(--teal-primary);
            box-shadow: 0 4px 15px rgba(0, 128, 128, 0.3);
        }
        
        .staff-nav-link.active i {
            color: var(--teal-primary);
        }
        
        .staff-user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .staff-user-name {
            font-weight: 600;
            color: white;
            font-size: 0.95rem;
            background: rgba(255, 255, 255, 0.15);
            padding: 6px 15px;
            border-radius: 30px;
        }
        
        .staff-role-badge {
            padding: 6px 15px;
            border-radius: 30px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            background: white;
            color: var(--teal-primary);
            letter-spacing: 0.5px;
        }
        
        .staff-btn {
            padding: 8px 18px;
            text-decoration: none;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
            cursor: pointer;
            font-size: 0.85rem;
            background: rgba(0, 0, 0, 0.2);
            color: white;
        }
        
        .staff-btn-danger {
            background: rgba(231, 76, 60, 0.8);
            color: white;
        }
        
        .staff-btn-danger:hover {
            background: #e74c3c;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(231, 76, 60, 0.3);
        }
        
        .staff-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
            min-height: calc(100vh - 80px);
        }
        
        /* Payment badge for notifications */
        .nav-badge {
            background: #e74c3c;
            color: white;
            font-size: 0.7rem;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 20px;
            margin-left: 5px;
            animation: pulse 1.5s infinite;
        }
        
        @keyframes pulse {
            0% {
                transform: scale(1);
                opacity: 1;
            }
            50% {
                transform: scale(1.1);
                opacity: 0.8;
            }
            100% {
                transform: scale(1);
                opacity: 1;
            }
        }
        
        @media (max-width: 1024px) {
            .staff-nav-link {
                padding: 6px 12px;
                font-size: 0.85rem;
            }
            
            .staff-logo-subtitle {
                font-size: 1.3rem;
            }
        }
        
        @media (max-width: 768px) {
            .staff-header-container {
                flex-direction: column;
                text-align: center;
            }
            
            .staff-nav {
                justify-content: center;
            }
            
            .staff-user-info {
                justify-content: center;
                flex-wrap: wrap;
            }
            
            .staff-content {
                padding: 15px;
            }
            
            .staff-logo {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <header class="staff-header">
        <div class="staff-header-container">
            <a href="<?php echo SITE_URL; ?>index.php?page=staff/dashboard" class="staff-logo">
                <div class="staff-logo-icon">🎬</div>
                <div class="staff-logo-text">
                    <div class="staff-logo-title">MovieTicketBooking</div>
                    <div class="staff-logo-subtitle">Staff Portal</div>
                </div>
            </a>
            
            <nav class="staff-nav">
                <?php
                $current_page = isset($_GET['page']) ? $_GET['page'] : 'staff/dashboard';
                $staff_section = explode('/', $current_page)[1] ?? 'dashboard';
                
                // Get pending verification count for badge
                $conn = null;
                $pending_verification_count = 0;
                try {
                    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
                    if (!$conn->connect_error) {
                        $result = $conn->query("
                            SELECT COUNT(*) as count 
                            FROM bookings 
                            WHERE payment_status = 'pending_verification' 
                            AND status = 'ongoing'
                        ");
                        if ($result && $result->num_rows > 0) {
                            $pending_verification_count = $result->fetch_assoc()['count'];
                        }
                        $conn->close();
                    }
                } catch (Exception $e) {
                    // Silent fail - badge just won't show
                }
                ?>
                
                <!-- Dashboard -->
                <a href="<?php echo SITE_URL; ?>index.php?page=staff/dashboard" 
                   class="staff-nav-link <?php echo $staff_section == 'dashboard' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                
                <!-- Scan QR -->
                <a href="<?php echo SITE_URL; ?>index.php?page=staff/scan-qr" 
                   class="staff-nav-link <?php echo $staff_section == 'scan-qr' ? 'active' : ''; ?>">
                    <i class="fas fa-qrcode"></i> Scan QR
                </a>
                
                <!-- Verify Bookings -->
                <a href="<?php echo SITE_URL; ?>index.php?page=staff/verify-booking" 
                   class="staff-nav-link <?php echo $staff_section == 'verify-booking' ? 'active' : ''; ?>">
                    <i class="fas fa-search"></i> Verify
                    <?php if ($pending_verification_count > 0): ?>
                        <span class="nav-badge"><?php echo $pending_verification_count; ?></span>
                    <?php endif; ?>
                </a>
                
                <!-- Verify History -->
                <a href="<?php echo SITE_URL; ?>index.php?page=staff/verify-history" 
                   class="staff-nav-link <?php echo $staff_section == 'verify-history' ? 'active' : ''; ?>">
                    <i class="fas fa-history"></i> History
                </a>
                
                <!-- Print Tickets -->
                <a href="<?php echo SITE_URL; ?>index.php?page=staff/print-ticket" 
                   class="staff-nav-link <?php echo $staff_section == 'print-ticket' ? 'active' : ''; ?>">
                    <i class="fas fa-print"></i> Print Tickets
                </a>
                
                <!-- Payment Transactions (NEW) -->
                <a href="<?php echo SITE_URL; ?>index.php?page=staff/payment-transaction" 
                   class="staff-nav-link <?php echo $staff_section == 'payment-transaction' ? 'active' : ''; ?>">
                    <i class="fas fa-credit-card"></i> Payments
                </a>
            </nav>
            
            <div class="staff-user-info">
                <span class="staff-user-name">
                    <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                </span>
                <span class="staff-role-badge">
                    <i class="fas fa-id-card"></i> Staff
                </span>
                <a href="<?php echo SITE_URL; ?>index.php?page=logout" class="staff-btn staff-btn-danger">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </header>
    
    <div class="staff-content">