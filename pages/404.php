<?php
// pages/404.php
$root_dir = dirname(__DIR__);
require_once $root_dir . '/includes/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found</title>
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
            text-align: center;
        }
        
        .error-container {
            padding: 40px;
            max-width: 600px;
        }
        
        .error-code {
            font-size: 6rem;
            color: #ffd700;
            font-weight: bold;
            margin-bottom: 20px;
        }
        
        .error-message {
            font-size: 1.5rem;
            margin-bottom: 30px;
            color: rgba(255, 255, 255, 0.8);
        }
        
        .home-link {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, #ffd700 0%, #ffaa00 100%);
            color: #333;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .home-link:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 215, 0, 0.3);
        }
        
        .sad-face {
            font-size: 4rem;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="sad-face">😞</div>
        <div class="error-code">404</div>
        <h1 class="error-message">Oops! The page you're looking for doesn't exist.</h1>
        <p style="color: rgba(255, 255, 255, 0.6); margin-bottom: 30px;">
            It might have been moved, deleted, or never existed in the first place.
        </p>
        <a href="<?php echo SITE_URL; ?>" class="home-link">Go to Homepage</a>
    </div>
</body>
</html>