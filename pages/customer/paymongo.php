<?php
$root_dir = dirname(dirname(__DIR__));
require_once $root_dir . '/includes/config.php';
require_once $root_dir . '/includes/functions.php';
require_once $root_dir . '/includes/database.php';

// Load environment variables from .env file if it exists
if (file_exists($root_dir . '/.env')) {
    $lines = file($root_dir . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

// Get API keys from environment
$secret_key = getenv('PAYMONGO_SECRET_KEY');
$public_key = getenv('PAYMONGO_PUBLIC_KEY');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Customer') {
    header("Location: " . SITE_URL . "index.php?page=login");
    exit();
}

// Check if PayMongo is configured
if (empty($secret_key) || empty($public_key)) {
    require_once $root_dir . '/partials/header.php';
    ?>
    <div class="payment-container" style="max-width: 800px; margin: 50px auto; padding: 20px;">
        <div style="background: rgba(241, 196, 15, 0.1); border: 2px solid #f39c12; border-radius: 20px; padding: 50px; text-align: center;">
            <i class="fas fa-tools" style="font-size: 5rem; color: #f39c12; margin-bottom: 20px;"></i>
            <h2 style="color: white; font-size: 2.2rem; margin-bottom: 15px; font-weight: 700;">Payment System Configuration</h2>
            <p style="color: var(--pale-red); font-size: 1.2rem; margin-bottom: 30px; max-width: 500px; margin-left: auto; margin-right: auto;">
                The payment system is being configured. Please try again later or contact support if this persists.
            </p>
            <div style="background: rgba(0,0,0,0.2); padding: 20px; border-radius: 10px; margin-bottom: 30px; text-align: left;">
                <p style="color: #f39c12; margin-bottom: 10px;"><i class="fas fa-info-circle"></i> For administrators:</p>
                <p style="color: var(--pale-red); margin-bottom: 5px;">1. Create a .env file in the root directory</p>
                <p style="color: var(--pale-red); margin-bottom: 5px;">2. Add your PayMongo API keys:</p>
                <pre style="background: rgba(0,0,0,0.3); padding: 10px; border-radius: 5px; color: #2ecc71; margin-top: 10px;">
PAYMONGO_SECRET_KEY=sk_test_your_secret_key
PAYMONGO_PUBLIC_KEY=pk_test_your_public_key</pre>
            </div>
            <a href="<?php echo SITE_URL; ?>index.php?page=customer/my-bookings" 
               class="btn btn-primary" style="padding: 15px 40px; font-size: 1.1rem;">
                <i class="fas fa-arrow-left"></i> Back to My Bookings
            </a>
        </div>
    </div>
    <?php
    require_once $root_dir . '/partials/footer.php';
    exit();
}

$conn = get_db();
$user_id = $_SESSION['user_id'];
$error = '';
$success = '';
$booking = null;

$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;

if ($booking_id <= 0) {
    header("Location: " . SITE_URL . "index.php?page=customer/my-bookings");
    exit();
}

// Get booking details with seat information
$booking_stmt = $conn->prepare("
    SELECT 
        b.*,
        GROUP_CONCAT(bs.seat_number ORDER BY bs.seat_number SEPARATOR ', ') as seat_list,
        COUNT(bs.id) as total_seats,
        m.title,
        m.poster_url,
        m.genre,
        m.duration,
        m.rating
    FROM tbl_booking b
    LEFT JOIN booked_seats bs ON b.b_id = bs.booking_id
    LEFT JOIN movies m ON b.movie_name = m.title
    WHERE b.b_id = ? AND b.u_id = ? AND b.payment_status = 'Pending'
    GROUP BY b.b_id
");
$booking_stmt->bind_param("ii", $booking_id, $user_id);
$booking_stmt->execute();
$booking_result = $booking_stmt->get_result();

if ($booking_result->num_rows === 0) {
    $conn->close();
    header("Location: " . SITE_URL . "index.php?page=customer/my-bookings");
    exit();
}

$booking = $booking_result->fetch_assoc();
$booking_stmt->close();
$conn->close();

// Convert amount to centavos (PayMongo requires amount in centavos)
$amount_in_centavos = intval($booking['booking_fee'] * 100);

// Create success and cancel URLs
$success_url = SITE_URL . "index.php?page=customer/paymongo-success&booking_id=" . $booking_id;
$cancel_url = SITE_URL . "index.php?page=customer/payment&booking_id=" . $booking_id;

// If form is submitted, create PayMongo checkout session
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_payment'])) {
    
    // Prepare data for PayMongo Checkout Session
    $data = [
        "data" => [
            "attributes" => [
                "amount" => $amount_in_centavos,
                "currency" => "PHP",
                "description" => "Movie Ticket: " . $booking['movie_name'],
                "statement_descriptor" => "Movie Booking",
                "payment_method_types" => ["card", "gcash", "paymaya"],
                "success_url" => $success_url,
                "cancel_url" => $cancel_url,
                "metadata" => [
                    "booking_id" => $booking_id,
                    "user_id" => $user_id,
                    "booking_reference" => $booking['booking_reference']
                ],
                "line_items" => [
                    [
                        "name" => $booking['movie_name'],
                        "quantity" => intval($booking['total_seats']),
                        "amount" => $amount_in_centavos,
                        "currency" => "PHP",
                        "description" => "Seats: " . $booking['seat_list']
                    ]
                ]
            ]
        ]
    ];

    // Call PayMongo API to create checkout session
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.paymongo.com/v1/checkout_sessions");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: Basic " . base64_encode($secret_key . ":")
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Remove in production
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($http_code === 200 || $http_code === 201) {
        $result = json_decode($response, true);
        
        if (isset($result['data']['attributes']['checkout_url'])) {
            // Redirect to PayMongo checkout page
            header("Location: " . $result['data']['attributes']['checkout_url']);
            exit();
        } else {
            $error = "Failed to create payment session. Please try again.";
            if (isset($result['errors'])) {
                $error_details = $result['errors'][0]['detail'] ?? 'Unknown error';
                error_log("PayMongo Error: " . $error_details);
            }
        }
    } else {
        $error = "Payment gateway error. Please try again later.";
        if (!empty($curl_error)) {
            error_log("cURL Error: " . $curl_error);
        }
    }
}

require_once $root_dir . '/partials/header.php';
?>

<div class="payment-container" style="max-width: 1200px; margin: 0 auto; padding: 20px;">
    <!-- Header -->
    <div style="background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%); 
         border-radius: 15px; padding: 25px; margin-bottom: 30px; 
         border: 1px solid rgba(226, 48, 32, 0.3);">
        <div style="display: flex; align-items: center; gap: 20px;">
            <div style="font-size: 2.5rem; color: var(--primary-red);">
                <i class="fas fa-bolt"></i>
            </div>
            <div>
                <h1 style="color: white; font-size: 2rem; margin-bottom: 5px; font-weight: 800;">
                    PayMongo Checkout
                </h1>
                <p style="color: var(--pale-red); font-size: 1rem;">
                    Booking Reference: <strong><?php echo $booking['booking_reference']; ?></strong>
                </p>
            </div>
        </div>
    </div>

    <?php if ($error): ?>
        <div style="background: rgba(226, 48, 32, 0.2); color: #ff9999; padding: 15px 20px; border-radius: 10px; margin-bottom: 25px; border: 1px solid rgba(226, 48, 32, 0.3); display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-exclamation-circle fa-lg"></i>
            <div><?php echo $error; ?></div>
        </div>
    <?php endif; ?>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
        <!-- Booking Summary -->
        <div style="background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%); 
             border-radius: 15px; padding: 30px; border: 1px solid rgba(226, 48, 32, 0.3);">
            <h2 style="color: white; font-size: 1.5rem; margin-bottom: 20px; font-weight: 700;">
                <i class="fas fa-receipt"></i> Booking Summary
            </h2>
            
            <div style="margin-bottom: 20px;">
                <?php if (!empty($booking['poster_url'])): ?>
                <img src="<?php echo $booking['poster_url']; ?>" 
                     alt="<?php echo htmlspecialchars($booking['movie_name']); ?>"
                     style="width: 100%; max-height: 200px; object-fit: cover; border-radius: 10px; margin-bottom: 15px;">
                <?php endif; ?>
                
                <h3 style="color: white; font-size: 1.3rem; margin-bottom: 10px;">
                    <?php echo htmlspecialchars($booking['movie_name']); ?>
                </h3>
                
                <div style="background: rgba(0,0,0,0.2); padding: 15px; border-radius: 8px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <span style="color: var(--pale-red);">Show Date:</span>
                        <span style="color: white;"><?php echo date('M d, Y', strtotime($booking['show_date'])); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <span style="color: var(--pale-red);">Show Time:</span>
                        <span style="color: white;"><?php echo date('h:i A', strtotime($booking['showtime'])); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <span style="color: var(--pale-red);">Seats:</span>
                        <span style="color: white;"><?php echo $booking['seat_list']; ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <span style="color: var(--pale-red);">Total Seats:</span>
                        <span style="color: white;"><?php echo $booking['total_seats']; ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; padding-top: 10px; border-top: 1px solid rgba(255,255,255,0.1);">
                        <span style="color: white; font-weight: 700;">Total Amount:</span>
                        <span style="color: var(--primary-red); font-weight: 900; font-size: 1.3rem;">
                            ₱<?php echo number_format($booking['booking_fee'], 2); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment Options -->
        <div style="background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%); 
             border-radius: 15px; padding: 30px; border: 1px solid rgba(226, 48, 32, 0.3);">
            <h2 style="color: white; font-size: 1.5rem; margin-bottom: 20px; font-weight: 700;">
                <i class="fas fa-credit-card"></i> Pay with PayMongo
            </h2>
            
            <div style="margin-bottom: 30px;">
                <p style="color: var(--pale-red); margin-bottom: 15px;">
                    You will be redirected to PayMongo's secure checkout page where you can pay using:
                </p>
                
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin-bottom: 20px;">
                    <div style="background: rgba(255,255,255,0.05); padding: 15px; border-radius: 8px; text-align: center;">
                        <i class="fas fa-credit-card" style="color: #3498db; font-size: 2rem; margin-bottom: 5px;"></i>
                        <div style="color: white;">Credit/Debit Card</div>
                    </div>
                    <div style="background: rgba(255,255,255,0.05); padding: 15px; border-radius: 8px; text-align: center;">
                        <i class="fas fa-mobile-alt" style="color: #00bfff; font-size: 2rem; margin-bottom: 5px;"></i>
                        <div style="color: white;">GCash</div>
                    </div>
                    <div style="background: rgba(255,255,255,0.05); padding: 15px; border-radius: 8px; text-align: center;">
                        <i class="fas fa-mobile-alt" style="color: #ff6b6b; font-size: 2rem; margin-bottom: 5px;"></i>
                        <div style="color: white;">PayMaya</div>
                    </div>
                    <div style="background: rgba(255,255,255,0.05); padding: 15px; border-radius: 8px; text-align: center;">
                        <i class="fas fa-university" style="color: #2ecc71; font-size: 2rem; margin-bottom: 5px;"></i>
                        <div style="color: white;">Online Banking</div>
                    </div>
                </div>
            </div>

            <div style="background: rgba(52, 152, 219, 0.1); padding: 20px; border-radius: 10px; margin-bottom: 30px;">
                <h3 style="color: white; font-size: 1.1rem; margin-bottom: 10px;">
                    <i class="fas fa-info-circle" style="color: #3498db;"></i> Test Payment
                </h3>
                <p style="color: var(--pale-red); font-size: 0.9rem; margin-bottom: 5px;">
                    For testing, use this card:
                </p>
                <p style="color: white; font-family: monospace; background: rgba(0,0,0,0.3); padding: 8px; border-radius: 5px;">
                    4242 4242 4242 4242
                </p>
                <p style="color: var(--pale-red); font-size: 0.85rem; margin-top: 5px;">
                    Any future expiry date • Any 3-digit CVC
                </p>
            </div>

            <form method="POST" action="">
                <input type="hidden" name="create_payment" value="1">
                
                <div style="margin-bottom: 20px;">
                    <label style="display: flex; align-items: center; gap: 10px; color: white; cursor: pointer;">
                        <input type="checkbox" name="terms" required style="width: 18px; height: 18px; accent-color: #e23020;">
                        <span>I agree to the <a href="<?php echo SITE_URL; ?>index.php?page=privacypolicy_termsservice&tab=terms" target="_blank" style="color: #3498db;">Terms of Service</a> and understand that this is a test payment.</span>
                    </label>
                </div>

                <div style="display: flex; gap: 15px;">
                    <button type="submit" class="btn btn-primary" style="flex: 1; padding: 15px;">
                        <i class="fas fa-bolt"></i> Proceed to PayMongo
                    </button>
                    <a href="<?php echo SITE_URL; ?>index.php?page=customer/payment&booking_id=<?php echo $booking_id; ?>" class="btn btn-secondary" style="padding: 15px 25px;">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.btn {
    padding: 12px 25px;
    text-decoration: none;
    border-radius: 10px;
    font-weight: 600;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    border: none;
    cursor: pointer;
    font-size: 1rem;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary-red) 0%, var(--dark-red) 100%);
    color: white;
    box-shadow: 0 4px 15px rgba(226, 48, 32, 0.3);
}

.btn-primary:hover {
    background: linear-gradient(135deg, var(--dark-red) 0%, var(--deep-red) 100%);
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(226, 48, 32, 0.4);
}

.btn-secondary {
    background: rgba(255, 255, 255, 0.1);
    color: white;
    border: 2px solid rgba(226, 48, 32, 0.3);
}

.btn-secondary:hover {
    background: rgba(226, 48, 32, 0.2);
    border-color: var(--primary-red);
    transform: translateY(-3px);
}

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

@media (max-width: 992px) {
    .payment-container > div > div {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .payment-container {
        padding: 15px;
    }
    
    h1 {
        font-size: 1.8rem !important;
    }
}
</style>

<?php
require_once $root_dir . '/partials/footer.php';
?>