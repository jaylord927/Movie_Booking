<?php
// partials/header.php

// Start session if not started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Define SITE_URL if not defined
if (!defined('SITE_URL')) {
    define('SITE_URL', 'http://localhost/Movie/');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movie Ticketing System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-red: #e23020;
            --dark-red: #c11b18;
            --deep-red: #a80f0f;
            --light-red: #ff6b6b;
            --pale-red: #ff9999;
            --bg-dark: #0f0f23;
            --bg-darker: #1a1a2e;
            --bg-card: #3a0b07;
            --bg-card-light: #6b140e;
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
            display: flex;
            flex-direction: column;
        }
        
        .header {
            background: linear-gradient(135deg, var(--dark-red) 0%, var(--deep-red) 100%);
            padding: 15px 0;
            border-bottom: 3px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .header-container {
            max-width: 1300px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 15px;
            text-decoration: none;
        }
        
        .logo-icon {
            font-size: 2.2rem;
            color: white;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }
        
        .logo-text {
            display: flex;
            flex-direction: column;
        }
        
        .logo-title {
            font-size: 1.6rem;
            font-weight: 800;
            color: white;
            line-height: 1.2;
        }
        
        .logo-tagline {
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.8);
            font-weight: 500;
        }
        
        .nav-links {
            display: flex;
            gap: 5px;
            align-items: center;
        }
        
        .nav-link {
            color: white;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
            padding: 8px 16px;
            border-radius: 20px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .nav-link:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }
        
        .nav-link.active {
            background: rgba(255, 255, 255, 0.3);
            box-shadow: 0 4px 15px rgba(255, 255, 255, 0.2);
        }
        
        .user-section {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-profile-link {
            text-decoration: none;
            color: white;
        }
        
        .user-profile {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.15);
            padding: 5px 15px 5px 10px;
            border-radius: 30px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .user-profile:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
            border-color: rgba(255, 255, 255, 0.4);
            box-shadow: 0 4px 12px rgba(255, 255, 255, 0.15);
        }
        
        .user-profile.active {
            background: rgba(255, 255, 255, 0.3);
            border-color: rgba(255, 255, 255, 0.5);
            box-shadow: 0 4px 15px rgba(255, 255, 255, 0.2);
        }
        
        .user-avatar {
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, var(--primary-red) 0%, var(--dark-red) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1rem;
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.3);
            transition: all 0.3s ease;
        }
        
        .user-profile:hover .user-avatar {
            border-color: rgba(255, 255, 255, 0.6);
            transform: scale(1.05);
        }
        
        .user-info {
            display: flex;
            flex-direction: column;
        }
        
        .user-name {
            font-weight: 700;
            color: white;
            font-size: 0.9rem;
            line-height: 1.3;
        }
        
        .user-role {
            font-size: 0.7rem;
            color: rgba(255, 255, 255, 0.8);
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .btn {
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
        
        .btn-primary {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }
        
        .btn-primary:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
            border-color: rgba(255, 255, 255, 0.5);
        }
        
        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }
        
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.5);
            transform: translateY(-2px);
        }
        
        .btn-danger {
            background: rgba(226, 48, 32, 0.2);
            color: white;
            border: 2px solid rgba(226, 48, 32, 0.3);
        }
        
        .btn-danger:hover {
            background: rgba(226, 48, 32, 0.3);
            transform: translateY(-2px);
            border-color: rgba(226, 48, 32, 0.5);
        }
        
        /* Responsive Design */
        @media (max-width: 1000px) {
            .header-container {
                flex-direction: column;
                gap: 15px;
            }
            
            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .user-section {
                flex-wrap: wrap;
                justify-content: center;
            }
        }
        
        @media (max-width: 576px) {
            .user-section {
                flex-direction: column;
                width: 100%;
            }
            
            .user-profile-link {
                width: 100%;
            }
            
            .user-profile {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-container">
            <a href="<?php echo SITE_URL; ?>" class="logo">
                <div class="logo-icon">🎬</div>
                <div class="logo-text">
                    <div class="logo-title">MovieTicketBooking</div>
                    <div class="logo-tagline">Book Your Favorite Movies</div>
                </div>
            </a>
            
            <div style="display: flex; align-items: center; gap: 20px;">
                <nav class="nav-links">
                    <?php
                    // Determine current page for active state
                    $current_page = isset($_GET['page']) ? $_GET['page'] : 'home';
                    ?>
                    
                    <a href="<?php echo SITE_URL; ?>" 
                       class="nav-link <?php echo $current_page == 'home' ? 'active' : ''; ?>">
                        <i class="fas fa-home"></i> Home
                    </a>
                    
                    <a href="<?php echo SITE_URL; ?>index.php?page=movies" 
                       class="nav-link <?php echo $current_page == 'movies' ? 'active' : ''; ?>">
                        <i class="fas fa-film"></i> Movies
                    </a>
                    
                    <?php if (isset($_SESSION['user_id']) && $_SESSION['user_role'] === 'Customer'): ?>
                        <a href="<?php echo SITE_URL; ?>index.php?page=customer/booking" 
                           class="nav-link <?php echo $current_page == 'customer/booking' ? 'active' : ''; ?>">
                            <i class="fas fa-ticket-alt"></i> Book Movies
                        </a>
                        
                        <a href="<?php echo SITE_URL; ?>index.php?page=customer/my-bookings" 
                           class="nav-link <?php echo $current_page == 'customer/my-bookings' ? 'active' : ''; ?>">
                            <i class="fas fa-receipt"></i> My Bookings
                        </a>
                    <?php endif; ?>
                </nav>
                
                <div class="user-section">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <!-- User Profile - Clickable link to profile.php with hover effect -->
                        <a href="<?php echo SITE_URL; ?>index.php?page=customer/profile" class="user-profile-link">
                            <div class="user-profile <?php echo $current_page == 'customer/profile' ? 'active' : ''; ?>">
                                <div class="user-avatar">
                                    <?php echo strtoupper(substr($_SESSION['user_name'], 0, 1)); ?>
                                </div>
                                <div class="user-info">
                                    <span class="user-name"><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                                    <span class="user-role"><?php echo $_SESSION['user_role']; ?></span>
                                </div>
                            </div>
                        </a>
                        
                        <!-- Logout -->
                        <a href="<?php echo SITE_URL; ?>index.php?page=logout" class="btn btn-danger">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                        
                    <?php else: ?>
                        <!-- Guest users -->
                        <a href="<?php echo SITE_URL; ?>index.php?page=login" class="btn btn-primary">
                            <i class="fas fa-sign-in-alt"></i> Login
                        </a>
                        <a href="<?php echo SITE_URL; ?>index.php?page=register" class="btn btn-secondary">
                            <i class="fas fa-user-plus"></i> Register
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>
    
    <main style="flex: 1;"> 