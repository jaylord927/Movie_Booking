<?php
// pages/register.php

// Go up one level from pages/ to root
$root_dir = dirname(__DIR__);

// Include config and functions
require_once $root_dir . '/includes/config.php';
require_once $root_dir . '/includes/functions.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize_input($_POST['name']);
    $username = sanitize_input($_POST['username']);
    $email = sanitize_input($_POST['email']);
    $password = sanitize_input($_POST['password']);
    $confirm_password = sanitize_input($_POST['confirm_password']);
    $role = 'Customer'; // Default role
    
    // Validation
    if (empty($name) || empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "All fields are required!";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match!";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format!";
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) {
        $error = "Username must be 3-50 characters and can only contain letters, numbers, and underscores!";
    } else {
        $conn = get_db_connection();
        
        // Check if username already exists
        $check_username_stmt = $conn->prepare("SELECT u_id FROM users WHERE u_username = ?");
        $check_username_stmt->bind_param("s", $username);
        $check_username_stmt->execute();
        $check_username_result = $check_username_stmt->get_result();
        
        if ($check_username_result->num_rows > 0) {
            $error = "Username already taken! Please choose a different username.";
            $check_username_stmt->close();
        } else {
            $check_username_stmt->close();
            
            // Check if email already exists
            $check_email_stmt = $conn->prepare("SELECT u_id FROM users WHERE u_email = ?");
            $check_email_stmt->bind_param("s", $email);
            $check_email_stmt->execute();
            $check_email_result = $check_email_stmt->get_result();
            
            if ($check_email_result->num_rows > 0) {
                $error = "Email already registered!";
            } else {
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert user with username
                $stmt = $conn->prepare("INSERT INTO users (u_name, u_username, u_email, u_pass, u_role) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss", $name, $username, $email, $hashed_password, $role);
                
                if ($stmt->execute()) {
                    $user_id = $stmt->insert_id;
                    $success = "Registration successful! You can now login.";
                    
                    // Auto-login after registration (optional)
                    // $_SESSION['user_id'] = $user_id;
                    // $_SESSION['user_name'] = $name;
                    // $_SESSION['user_username'] = $username;
                    // $_SESSION['user_email'] = $email;
                    // $_SESSION['user_role'] = $role;
                    // header("Location: " . SITE_URL . "index.php?page=customer/browse-movies");
                    // exit();
                    
                    // Clear form
                    $_POST = array();
                } else {
                    $error = "Registration failed: " . $conn->error;
                }
                
                $stmt->close();
            }
            
            $check_email_stmt->close();
        }
        
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Movie Ticketing System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
        }
        
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0f0f23 0%, #1a1a2e 100%);
            color: white; 
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .register-wrapper {
            display: flex;
            width: 100%;
            max-width: 1200px;
            background: rgba(26, 26, 46, 0.95);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 15px 50px rgba(226, 48, 32, 0.15);
            border: 1px solid rgba(226, 48, 32, 0.2);
            min-height: 600px;
        }
        
        /* Left Side - Register Form */
        .register-form-side {
            flex: 1;
            padding: 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
        }
        
        .back-home {
            position: absolute;
            top: 30px;
            left: 50px;
        }
        
        .back-home a {
            color: #ff6b6b;
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        
        .back-home a:hover {
            color: #e23020;
            transform: translateX(-5px);
        }
        
        .register-header {
            margin-bottom: 40px;
        }
        
        .create-account {
            color: #ff6b6b;
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .register-title {
            color: white;
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 10px;
            line-height: 1.2;
        }
        
        .register-subtitle {
            color: rgba(255, 255, 255, 0.7);
            font-size: 1.1rem;
        }
        
        /* Form Styles */
        .form-group {
            margin-bottom: 25px;
            position: relative;
        }
        
        .form-label {
            display: block;
            color: white;
            font-weight: 600;
            margin-bottom: 10px;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .form-control {
            width: 100%;
            padding: 16px 50px 16px 20px;
            background: rgba(255, 255, 255, 0.08);
            border: 2px solid rgba(226, 48, 32, 0.3);
            border-radius: 12px;
            color: white;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #e23020;
            background: rgba(255, 255, 255, 0.12);
            box-shadow: 0 0 0 4px rgba(226, 48, 32, 0.2);
        }
        
        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }
        
        .input-icon {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.6);
            font-size: 1.2rem;
        }
        
        .form-control.has-icon {
            padding-left: 50px;
        }
        
        .password-toggle {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: rgba(255, 255, 255, 0.6);
            cursor: pointer;
            font-size: 1.2rem;
            transition: all 0.3s ease;
            padding: 5px;
            z-index: 2;
        }
        
        .password-toggle:hover {
            color: #ff6b6b;
        }
        
        .username-check {
            margin-top: 5px;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .username-check.available {
            color: #2ecc71;
        }
        
        .username-check.taken {
            color: #e74c3c;
        }
        
        .username-check.loading {
            color: #f39c12;
        }
        
        .password-strength {
            margin-top: 10px;
            height: 4px;
            border-radius: 2px;
            overflow: hidden;
            background: rgba(255, 255, 255, 0.1);
        }
        
        .password-strength-bar {
            height: 100%;
            width: 0%;
            transition: width 0.3s ease, background-color 0.3s ease;
        }
        
        .password-strength-text {
            margin-top: 5px;
            font-size: 0.85rem;
            text-align: right;
        }
        
        .terms-agreement {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 30px;
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.95rem;
        }
        
        .terms-agreement input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #e23020;
            margin-top: 3px;
            flex-shrink: 0;
        }
        
        .terms-agreement a {
            color: #ff6b6b;
            text-decoration: none;
            font-weight: 600;
        }
        
        .terms-agreement a:hover {
            color: #e23020;
            text-decoration: underline;
        }
        
        .register-button {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, #e23020 0%, #c11b18 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 6px 20px rgba(226, 48, 32, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 25px;
        }
        
        .register-button:hover {
            background: linear-gradient(135deg, #c11b18 0%, #a80f0f 100%);
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(226, 48, 32, 0.4);
        }
        
        .register-button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
        }
        
        .login-link {
            text-align: center;
            color: rgba(255, 255, 255, 0.8);
            font-size: 1rem;
        }
        
        .login-link a {
            color: #ff6b6b;
            text-decoration: none;
            font-weight: 700;
            margin-left: 5px;
            transition: all 0.3s ease;
        }
        
        .login-link a:hover {
            color: #e23020;
            text-decoration: underline;
        }
        
        /* Right Side - Benefits */
        .benefits-side {
            flex: 1;
            background: linear-gradient(135deg, rgba(226, 48, 32, 0.1), rgba(193, 27, 24, 0.2));
            padding: 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            border-left: 1px solid rgba(226, 48, 32, 0.2);
            position: relative;
            overflow: hidden;
        }
        
        .benefits-side::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 200px;
            height: 200px;
            background: linear-gradient(135deg, rgba(226, 48, 32, 0.1), transparent);
            border-radius: 0 0 0 200px;
        }
        
        .benefits-header {
            margin-bottom: 40px;
        }
        
        .benefits-title {
            color: white;
            font-size: 2.2rem;
            font-weight: 800;
            margin-bottom: 15px;
            line-height: 1.3;
        }
        
        .benefits-subtitle {
            color: rgba(255, 255, 255, 0.8);
            font-size: 1.1rem;
            line-height: 1.6;
        }
        
        .benefits-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
            margin-top: 30px;
        }
        
        .benefit-item {
            background: rgba(255, 255, 255, 0.05);
            padding: 25px;
            border-radius: 15px;
            border: 1px solid rgba(226, 48, 32, 0.1);
            transition: all 0.3s ease;
        }
        
        .benefit-item:hover {
            transform: translateY(-5px);
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(226, 48, 32, 0.3);
            box-shadow: 0 10px 25px rgba(226, 48, 32, 0.1);
        }
        
        .benefit-icon {
            font-size: 2.5rem;
            color: #e23020;
            margin-bottom: 15px;
        }
        
        .benefit-title {
            color: white;
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .benefit-desc {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.95rem;
            line-height: 1.5;
        }
        
        /* Alerts */
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 600;
            animation: slideIn 0.5s ease;
        }
        
        .alert-danger {
            background: rgba(226, 48, 32, 0.2);
            color: #ff9999;
            border: 1px solid rgba(226, 48, 32, 0.3);
        }
        
        .alert-success {
            background: rgba(46, 204, 113, 0.2);
            color: #2ecc71;
            border: 1px solid rgba(46, 204, 113, 0.3);
        }
        
        /* Animations */
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        /* Responsive Design */
        @media (max-width: 992px) {
            .register-wrapper {
                flex-direction: column;
                max-width: 600px;
            }
            
            .benefits-side {
                border-left: none;
                border-top: 1px solid rgba(226, 48, 32, 0.2);
            }
            
            .benefits-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 576px) {
            .register-wrapper {
                margin: 10px;
                border-radius: 15px;
            }
            
            .register-form-side,
            .benefits-side {
                padding: 30px;
            }
            
            .register-title {
                font-size: 2rem;
            }
            
            .benefits-title {
                font-size: 1.8rem;
            }
            
            .back-home {
                left: 30px;
                top: 20px;
            }
            
            .form-group {
                margin-bottom: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="register-wrapper">
        <!-- Left Side: Register Form -->
        <div class="register-form-side">
            <div class="back-home">
                <a href="<?php echo SITE_URL; ?>index.php?page=home">
                    <i class="fas fa-arrow-left"></i> Back to Home
                </a>
            </div>
            
            <div class="register-header">
                <div class="create-account">
                    <i class="fas fa-user-plus"></i> Create Account
                </div>
                <h1 class="register-title">Join Movie Ticketing Today!</h1>
                <p class="register-subtitle">Create your account to start booking your favorite movies</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="registerForm">
                <!-- Full Name -->
                <div class="form-group">
                    <label for="name" class="form-label">
                        <i class="fas fa-user"></i> Full Name
                    </label>
                    <div style="position: relative;">
                        <i class="fas fa-user input-icon"></i>
                        <input type="text" 
                               id="name" 
                               name="name" 
                               class="form-control has-icon" 
                               placeholder="Enter your full name"
                               autocomplete="name"
                               autofocus
                               required
                               value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                    </div>
                </div>
                
                <!-- Username -->
                <div class="form-group">
                    <label for="username" class="form-label">
                        <i class="fas fa-at"></i> Username
                    </label>
                    <div style="position: relative;">
                        <i class="fas fa-at input-icon"></i>
                        <input type="text" 
                               id="username" 
                               name="username" 
                               class="form-control has-icon" 
                               placeholder="Choose a username (3-50 characters)"
                               autocomplete="username"
                               required
                               minlength="3"
                               maxlength="50"
                               pattern="[a-zA-Z0-9_]+"
                               title="Only letters, numbers, and underscores allowed"
                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                    </div>
                    <div id="usernameCheck" class="username-check">
                        <i class="fas fa-info-circle"></i> Choose a unique username
                    </div>
                </div>
                
                <!-- Email -->
                <div class="form-group">
                    <label for="email" class="form-label">
                        <i class="fas fa-envelope"></i> Email Address
                    </label>
                    <div style="position: relative;">
                        <i class="fas fa-envelope input-icon"></i>
                        <input type="email" 
                               id="email" 
                               name="email" 
                               class="form-control has-icon" 
                               placeholder="Enter your email address"
                               autocomplete="email"
                               required
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>
                </div>
                
                <!-- Password -->
                <div class="form-group">
                    <label for="password" class="form-label">
                        <i class="fas fa-lock"></i> Password
                    </label>
                    <div style="position: relative;">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" 
                               id="password" 
                               name="password" 
                               class="form-control has-icon" 
                               placeholder="Create a password (min. 6 characters)"
                               autocomplete="new-password"
                               required
                               minlength="6">
                        <button type="button" class="password-toggle" id="togglePassword">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="password-strength">
                        <div id="passwordStrengthBar" class="password-strength-bar"></div>
                    </div>
                    <div id="passwordStrengthText" class="password-strength-text"></div>
                </div>
                
                <!-- Confirm Password -->
                <div class="form-group">
                    <label for="confirm_password" class="form-label">
                        <i class="fas fa-lock"></i> Confirm Password
                    </label>
                    <div style="position: relative;">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" 
                               id="confirm_password" 
                               name="confirm_password" 
                               class="form-control has-icon" 
                               placeholder="Confirm your password"
                               autocomplete="new-password"
                               required
                               minlength="6">
                        <button type="button" class="password-toggle" id="toggleConfirmPassword">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div id="passwordMatch" style="margin-top: 5px; font-size: 0.85rem;"></div>
                </div>
                
                <div class="terms-agreement">
                    <input type="checkbox" name="terms" id="terms" required>
                    <label for="terms">
                        I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a>
                    </label>
                </div>
                
                <button type="submit" class="register-button" id="submitButton">
                    <i class="fas fa-user-plus"></i> Create Account
                </button>
                
                <div class="login-link">
                    Already have an account? 
                    <a href="<?php echo SITE_URL; ?>index.php?page=login">Login here</a>
                </div>
            </form>
        </div>
        
        <!-- Right Side: Benefits -->
        <div class="benefits-side">
            <div class="benefits-header">
                <h2 class="benefits-title">Why Register With Us?</h2>
                <p class="benefits-subtitle">
                    Experience the best movie booking platform. Register now to unlock exclusive features 
                    and enjoy seamless ticket booking for all your favorite movies.
                </p>
            </div>
            
            <div class="benefits-grid">
                <div class="benefit-item">
                    <div class="benefit-icon">
                        <i class="fas fa-ticket-alt"></i>
                    </div>
                    <h3 class="benefit-title">Easy Booking</h3>
                    <p class="benefit-desc">
                        Book movie tickets in just a few clicks. Select your preferred seats and showtimes effortlessly.
                    </p>
                </div>
                
                <div class="benefit-item">
                    <div class="benefit-icon">
                        <i class="fas fa-chair"></i>
                    </div>
                    <h3 class="benefit-title">Seat Selection</h3>
                    <p class="benefit-desc">
                        Choose your perfect seats with our interactive seating chart. Get the best view every time.
                    </p>
                </div>
                
                <div class="benefit-item">
                    <div class="benefit-icon">
                        <i class="fas fa-bolt"></i>
                    </div>
                    <h3 class="benefit-title">Quick Checkout</h3>
                    <p class="benefit-desc">
                        Fast and secure payment processing. Receive your e-tickets instantly after booking.
                    </p>
                </div>
                
                <div class="benefit-item">
                    <div class="benefit-icon">
                        <i class="fas fa-history"></i>
                    </div>
                    <h3 class="benefit-title">Booking History</h3>
                    <p class="benefit-desc">
                        Keep track of all your past and upcoming bookings in one convenient place.
                    </p>
                </div>
                
                <div class="benefit-item">
                    <div class="benefit-icon">
                        <i class="fas fa-gift"></i>
                    </div>
                    <h3 class="benefit-title">Special Offers</h3>
                    <p class="benefit-desc">
                        Get access to exclusive discounts, promotions, and member-only deals.
                    </p>
                </div>
                
                <div class="benefit-item">
                    <div class="benefit-icon">
                        <i class="fas fa-mobile-alt"></i>
                    </div>
                    <h3 class="benefit-title">Mobile Friendly</h3>
                    <p class="benefit-desc">
                        Book tickets from any device. Our platform works perfectly on mobile, tablet, and desktop.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Password toggle functionality
    const togglePassword = document.getElementById('togglePassword');
    const passwordField = document.getElementById('password');
    
    const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
    const confirmPasswordField = document.getElementById('confirm_password');
    
    togglePassword.addEventListener('click', function() {
        const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordField.setAttribute('type', type);
        this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
    });
    
    toggleConfirmPassword.addEventListener('click', function() {
        const type = confirmPasswordField.getAttribute('type') === 'password' ? 'text' : 'password';
        confirmPasswordField.setAttribute('type', type);
        this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
    });
    
    // Username availability check
    const usernameField = document.getElementById('username');
    const usernameCheck = document.getElementById('usernameCheck');
    let usernameCheckTimeout;
    
    usernameField.addEventListener('input', function() {
        const username = this.value.trim();
        
        // Clear previous timeout
        clearTimeout(usernameCheckTimeout);
        
        if (username.length < 3) {
            usernameCheck.innerHTML = '<i class="fas fa-info-circle"></i> Username must be at least 3 characters';
            usernameCheck.className = 'username-check';
            return;
        }
        
        if (!/^[a-zA-Z0-9_]+$/.test(username)) {
            usernameCheck.innerHTML = '<i class="fas fa-exclamation-circle"></i> Only letters, numbers, and underscores allowed';
            usernameCheck.className = 'username-check taken';
            return;
        }
        
        // Show loading
        usernameCheck.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking username availability...';
        usernameCheck.className = 'username-check loading';
        
        // Debounce the check
        usernameCheckTimeout = setTimeout(() => {
            checkUsernameAvailability(username);
        }, 500);
    });
    
    function checkUsernameAvailability(username) {
        // In a real implementation, this would make an AJAX call to the server
        // For this demo, we'll simulate with a timeout
        
        // Simulate API call delay
        setTimeout(() => {
            // This is a demo - in reality, you would check against your database
            const takenUsernames = ['admin', 'user', 'test', 'john', 'jane']; // Example taken usernames
            
            if (takenUsernames.includes(username.toLowerCase())) {
                usernameCheck.innerHTML = '<i class="fas fa-times-circle"></i> Username already taken';
                usernameCheck.className = 'username-check taken';
            } else {
                usernameCheck.innerHTML = '<i class="fas fa-check-circle"></i> Username available';
                usernameCheck.className = 'username-check available';
            }
        }, 300);
    }
    
    // Password strength indicator
    const passwordField = document.getElementById('password');
    const passwordStrengthBar = document.getElementById('passwordStrengthBar');
    const passwordStrengthText = document.getElementById('passwordStrengthText');
    
    passwordField.addEventListener('input', function() {
        const password = this.value;
        let strength = 0;
        let color = '#e74c3c';
        let text = 'Very Weak';
        
        if (password.length >= 6) strength++;
        if (password.length >= 8) strength++;
        if (/[A-Z]/.test(password)) strength++;
        if (/[0-9]/.test(password)) strength++;
        if (/[^A-Za-z0-9]/.test(password)) strength++;
        
        switch(strength) {
            case 0:
            case 1:
                color = '#e74c3c';
                text = 'Very Weak';
                break;
            case 2:
                color = '#e74c3c';
                text = 'Weak';
                break;
            case 3:
                color = '#f39c12';
                text = 'Fair';
                break;
            case 4:
                color = '#2ecc71';
                text = 'Good';
                break;
            case 5:
                color = '#2ecc71';
                text = 'Strong';
                break;
        }
        
        const percentage = (strength / 5) * 100;
        passwordStrengthBar.style.width = percentage + '%';
        passwordStrengthBar.style.background = color;
        passwordStrengthText.textContent = text;
        passwordStrengthText.style.color = color;
    });
    
    // Password match check
    const confirmPasswordField = document.getElementById('confirm_password');
    const passwordMatch = document.getElementById('passwordMatch');
    
    function checkPasswordMatch() {
        const password = passwordField.value;
        const confirmPassword = confirmPasswordField.value;
        
        if (confirmPassword.length === 0) {
            passwordMatch.innerHTML = '';
            return;
        }
        
        if (password === confirmPassword) {
            passwordMatch.innerHTML = '<i class="fas fa-check-circle" style="color: #2ecc71;"></i> Passwords match';
            passwordMatch.style.color = '#2ecc71';
        } else {
            passwordMatch.innerHTML = '<i class="fas fa-times-circle" style="color: #e74c3c;"></i> Passwords do not match';
            passwordMatch.style.color = '#e74c3c';
        }
    }
    
    passwordField.addEventListener('input', checkPasswordMatch);
    confirmPasswordField.addEventListener('input', checkPasswordMatch);
    
    // Form validation
    document.getElementById('registerForm').addEventListener('submit', function(e) {
        const name = document.getElementById('name').value.trim();
        const username = document.getElementById('username').value.trim();
        const email = document.getElementById('email').value.trim();
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        const terms = document.getElementById('terms').checked;
        
        // Check required fields
        if (!name || !username || !email || !password || !confirmPassword) {
            e.preventDefault();
            showAlert('Please fill in all required fields!', 'error');
            return false;
        }
        
        // Check username format
        if (!/^[a-zA-Z0-9_]{3,50}$/.test(username)) {
            e.preventDefault();
            showAlert('Username must be 3-50 characters and can only contain letters, numbers, and underscores!', 'error');
            return false;
        }
        
        // Check password length
        if (password.length < 6) {
            e.preventDefault();
            showAlert('Password must be at least 6 characters long!', 'error');
            return false;
        }
        
        // Check password match
        if (password !== confirmPassword) {
            e.preventDefault();
            showAlert('Passwords do not match!', 'error');
            return false;
        }
        
        // Check email format
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            e.preventDefault();
            showAlert('Please enter a valid email address!', 'error');
            return false;
        }
        
        // Check terms agreement
        if (!terms) {
            e.preventDefault();
            showAlert('You must agree to the Terms of Service and Privacy Policy!', 'error');
            return false;
        }
        
        // Check if username is marked as taken
        if (usernameCheck.classList.contains('taken')) {
            e.preventDefault();
            showAlert('Please choose a different username. This one is already taken!', 'error');
            return false;
        }
        
        // Disable submit button to prevent double submission
        const submitButton = document.getElementById('submitButton');
        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating Account...';
        
        return true;
    });
    
    // Auto-focus name field
    document.addEventListener('DOMContentLoaded', function() {
        const nameField = document.getElementById('name');
        if (nameField && !nameField.value) {
            nameField.focus();
        }
    });
    
    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            document.getElementById('registerForm').submit();
        }
        
        // Escape key to go back
        if (e.key === 'Escape') {
            window.history.back();
        }
    });
    
    // Alert function
    function showAlert(message, type) {
        // Remove existing alerts
        const existingAlerts = document.querySelectorAll('.alert');
        existingAlerts.forEach(alert => {
            if (!alert.classList.contains('alert-danger') && !alert.classList.contains('alert-success')) {
                alert.remove();
            }
        });
        
        // Create new alert
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type === 'error' ? 'danger' : 'success'}`;
        alertDiv.innerHTML = `<i class="fas fa-${type === 'error' ? 'exclamation-circle' : 'check-circle'}"></i> ${message}`;
        alertDiv.style.animation = 'none';
        alertDiv.style.opacity = '0';
        
        // Insert after register header
        const registerHeader = document.querySelector('.register-header');
        registerHeader.parentNode.insertBefore(alertDiv, registerHeader.nextSibling);
        
        // Trigger animation
        setTimeout(() => {
            alertDiv.style.animation = 'slideIn 0.5s ease';
            alertDiv.style.opacity = '1';
        }, 10);
        
        // Remove alert after 5 seconds
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.style.opacity = '0';
                alertDiv.style.transform = 'translateY(-20px)';
                setTimeout(() => {
                    if (alertDiv.parentNode) {
                        alertDiv.parentNode.removeChild(alertDiv);
                    }
                }, 300);
            }
        }, 5000);
    }
    
    // Add animation to benefit items
    const benefitItems = document.querySelectorAll('.benefit-item');
    benefitItems.forEach((item, index) => {
        item.style.animationDelay = `${index * 0.1}s`;
        item.style.animation = 'slideIn 0.5s ease forwards';
        item.style.opacity = '0';
    });
    
    // Real-time form validation
    function validateForm() {
        const name = document.getElementById('name').value.trim();
        const username = document.getElementById('username').value.trim();
        const email = document.getElementById('email').value.trim();
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        const terms = document.getElementById('terms').checked;
        
        const submitButton = document.getElementById('submitButton');
        let isValid = true;
        
        // Basic validation
        if (!name || !username || !email || !password || !confirmPassword || !terms) {
            isValid = false;
        }
        
        // Username validation
        if (!/^[a-zA-Z0-9_]{3,50}$/.test(username)) {
            isValid = false;
        }
        
        // Password validation
        if (password.length < 6 || password !== confirmPassword) {
            isValid = false;
        }
        
        // Email validation
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            isValid = false;
        }
        
        // Update button state
        submitButton.disabled = !isValid;
        
        return isValid;
    }
    
    // Add event listeners for real-time validation
    const formInputs = document.querySelectorAll('#registerForm input');
    formInputs.forEach(input => {
        input.addEventListener('input', validateForm);
        input.addEventListener('change', validateForm);
    });
    
    // Initial validation
    validateForm();
    </script>
</body>
</html>