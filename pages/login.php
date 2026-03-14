<?php
$root_dir = dirname(__DIR__);
require_once $root_dir . '/includes/config.php';
require_once $root_dir . '/includes/functions.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = sanitize_input($_POST['email']);
    $password = sanitize_input($_POST['password']);
    
    $conn = get_db_connection();
    
    $stmt = $conn->prepare("SELECT u_id, u_name, u_username, u_email, u_pass, u_role, u_status FROM users WHERE u_email = ? OR u_username = ?");
    $stmt->bind_param("ss", $login, $login);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        if (password_verify($password, $user['u_pass'])) {
            if ($user['u_status'] === 'Active') {
                $_SESSION['user_id'] = $user['u_id'];
                $_SESSION['user_name'] = $user['u_name'];
                $_SESSION['user_username'] = $user['u_username'];
                $_SESSION['user_email'] = $user['u_email'];
                $_SESSION['user_role'] = $user['u_role'];
                
                if ($user['u_role'] === 'Admin') {
                    header("Location: " . SITE_URL . "index.php?page=admin/dashboard");
                    exit();
                } else {
                    header("Location: " . SITE_URL . "index.php?page=home");
                    exit();
                }
            } else {
                $error = "Your account is inactive. Please contact administrator.";
            }
        } else {
            $error = "Invalid username/email or password!";
        }
    } else {
        $error = "Invalid username/email or password!";
    }
    
    $stmt->close();
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Movie Ticketing System</title>
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
        
        .login-wrapper {
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
        
        .login-form-side {
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
        
        .login-header {
            margin-bottom: 40px;
        }
        
        .welcome-back {
            color: #ff6b6b;
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .login-title {
            color: white;
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 10px;
            line-height: 1.2;
        }
        
        .login-subtitle {
            color: rgba(255, 255, 255, 0.7);
            font-size: 1.1rem;
        }
        
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
            padding: 16px 20px;
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
        
        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.95rem;
        }
        
        .remember-me input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #e23020;
        }
        
        .forgot-password {
            color: #ff6b6b;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }
        
        .forgot-password:hover {
            color: #e23020;
            text-decoration: underline;
        }
        
        .login-button {
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
        
        .login-button:hover {
            background: linear-gradient(135deg, #c11b18 0%, #a80f0f 100%);
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(226, 48, 32, 0.4);
        }
        
        .register-link {
            text-align: center;
            color: rgba(255, 255, 255, 0.8);
            font-size: 1rem;
        }
        
        .register-link a {
            color: #ff6b6b;
            text-decoration: none;
            font-weight: 700;
            margin-left: 5px;
            transition: all 0.3s ease;
        }
        
        .register-link a:hover {
            color: #e23020;
            text-decoration: underline;
        }
        
        .features-side {
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
        
        .features-side::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 200px;
            height: 200px;
            background: linear-gradient(135deg, rgba(226, 48, 32, 0.1), transparent);
            border-radius: 0 0 0 200px;
        }
        
        .features-header {
            margin-bottom: 40px;
        }
        
        .features-title {
            color: white;
            font-size: 2.2rem;
            font-weight: 800;
            margin-bottom: 15px;
            line-height: 1.3;
        }
        
        .features-subtitle {
            color: rgba(255, 255, 255, 0.8);
            font-size: 1.1rem;
            line-height: 1.6;
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
            margin-top: 30px;
        }
        
        .feature-item {
            background: rgba(255, 255, 255, 0.05);
            padding: 25px;
            border-radius: 15px;
            border: 1px solid rgba(226, 48, 32, 0.1);
            transition: all 0.3s ease;
        }
        
        .feature-item:hover {
            transform: translateY(-5px);
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(226, 48, 32, 0.3);
            box-shadow: 0 10px 25px rgba(226, 48, 32, 0.1);
        }
        
        .feature-icon {
            font-size: 2.5rem;
            color: #e23020;
            margin-bottom: 15px;
        }
        
        .feature-title {
            color: white;
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .feature-desc {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.95rem;
            line-height: 1.5;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
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
        }
        
        .password-toggle:hover {
            color: #ff6b6b;
        }
        
        @media (max-width: 992px) {
            .login-wrapper {
                flex-direction: column;
                max-width: 600px;
            }
            
            .features-side {
                border-left: none;
                border-top: 1px solid rgba(226, 48, 32, 0.2);
            }
            
            .features-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 576px) {
            .login-wrapper {
                margin: 10px;
                border-radius: 15px;
            }
            
            .login-form-side,
            .features-side {
                padding: 30px;
            }
            
            .login-title {
                font-size: 2rem;
            }
            
            .features-title {
                font-size: 1.8rem;
            }
            
            .form-options {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .back-home {
                left: 30px;
                top: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-form-side">
            <div class="back-home">
                <a href="<?php echo SITE_URL; ?>index.php?page=home">
                    <i class="fas fa-arrow-left"></i> Back to Home
                </a>
            </div>
            
            <div class="login-header">
                <div class="welcome-back">
                    <i class="fas fa-ticket-alt"></i> Welcome Back!
                </div>
                <h1 class="login-title">Login to Book Your Favorite Movies!</h1>
                <p class="login-subtitle">Access your account to continue your movie booking experience</p>
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
            
            <form method="POST" action="" id="loginForm">
                <div class="form-group">
                    <label for="email" class="form-label">
                        <i class="fas fa-user"></i> Username or Email
                    </label>
                    <input type="text" 
                           id="email" 
                           name="email" 
                           class="form-control" 
                           placeholder="Enter your username or email"
                           autocomplete="email"
                           autofocus
                           required>
                </div>
                
                <div class="form-group">
                    <label for="password" class="form-label">
                        <i class="fas fa-lock"></i> Password
                    </label>
                    <input type="password" 
                           id="password" 
                           name="password" 
                           class="form-control" 
                           placeholder="Enter your password"
                           autocomplete="current-password"
                           required>
                    <button type="button" class="password-toggle" id="togglePassword">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                
                <div class="form-options">
                    <label class="remember-me">
                        <input type="checkbox" name="remember" id="remember">
                        Remember me
                    </label>
                    <a href="#" class="forgot-password">
                        Forgot password?
                    </a>
                </div>
                
                <button type="submit" class="login-button">
                    <i class="fas fa-sign-in-alt"></i> Login to Account
                </button>
                
                <div class="register-link">
                    Don't have an account? 
                    <a href="<?php echo SITE_URL; ?>index.php?page=register">Register here</a>
                </div>
            </form>
        </div>
        
        <div class="features-side">
            <div class="features-header">
                <h2 class="features-title">Movie Ticket Booking</h2>
                <p class="features-subtitle">
                    Experience the Best Movie Booking. Book tickets for the latest blockbusters 
                    with ease and convenience. Enjoy seamless booking from any device.
                </p>
            </div>
            
            <div class="features-grid">
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="fas fa-bolt"></i>
                    </div>
                    <h3 class="feature-title">Easy Booking</h3>
                    <p class="feature-desc">
                        Simple and intuitive booking process. Find movies, select seats, and book in minutes.
                    </p>
                </div>
                
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="fas fa-chair"></i>
                    </div>
                    <h3 class="feature-title">Seat Selection</h3>
                    <p class="feature-desc">
                        Choose your preferred seats with our interactive seat map. Get the best view in the house.
                    </p>
                </div>
                
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h3 class="feature-title">Secure Payments</h3>
                    <p class="feature-desc">
                        Your payments are safe with us. We use industry-standard encryption for all transactions.
                    </p>
                </div>
                
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="fas fa-rocket"></i>
                    </div>
                    <h3 class="feature-title">Quick Checkout</h3>
                    <p class="feature-desc">
                        Fast and efficient checkout process. Get your tickets instantly after payment.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script>
        const togglePassword = document.getElementById('togglePassword');
        const passwordField = document.getElementById('password');
        
        togglePassword.addEventListener('click', function() {
            const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordField.setAttribute('type', type);
            this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
        });
        
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            
            if (!email || !password) {
                e.preventDefault();
                showAlert('Please fill in both username/email and password fields!', 'error');
                return false;
            }
            
            return true;
        });
        
        document.addEventListener('DOMContentLoaded', function() {
            const emailField = document.getElementById('email');
            if (emailField && !emailField.value) {
                emailField.focus();
            }
        });
        
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                document.getElementById('loginForm').submit();
            }
            
            if (e.key === 'Escape') {
                window.history.back();
            }
        });
        
        const rememberCheckbox = document.getElementById('remember');
        const emailField = document.getElementById('email');
        
        const savedEmail = localStorage.getItem('rememberedEmail');
        if (savedEmail && rememberCheckbox) {
            emailField.value = savedEmail;
            rememberCheckbox.checked = true;
        }
        
        document.getElementById('loginForm').addEventListener('submit', function() {
            if (rememberCheckbox.checked) {
                localStorage.setItem('rememberedEmail', emailField.value);
            } else {
                localStorage.removeItem('rememberedEmail');
            }
        });
        
        const forgotPasswordLink = document.querySelector('.forgot-password');
        forgotPasswordLink.addEventListener('click', function(e) {
            e.preventDefault();
            const email = prompt('Please enter your email address to reset your password:');
            if (email) {
                showAlert('Password reset instructions have been sent to ' + email, 'success');
            }
        });
        
        function showAlert(message, type) {
            const existingAlerts = document.querySelectorAll('.alert');
            existingAlerts.forEach(alert => alert.remove());
            
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type === 'error' ? 'danger' : 'success'}`;
            alertDiv.innerHTML = `<i class="fas fa-${type === 'error' ? 'exclamation-circle' : 'check-circle'}"></i> ${message}`;
            
            const loginHeader = document.querySelector('.login-header');
            loginHeader.parentNode.insertBefore(alertDiv, loginHeader.nextSibling);
            
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
        
        const featureItems = document.querySelectorAll('.feature-item');
        featureItems.forEach((item, index) => {
            item.style.animationDelay = `${index * 0.1}s`;
            item.style.animation = 'slideIn 0.5s ease forwards';
            item.style.opacity = '0';
        });
    </script>
</body>
</html>