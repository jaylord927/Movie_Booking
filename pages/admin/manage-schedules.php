<?php
$root_dir = dirname(dirname(__DIR__));
require_once $root_dir . '/includes/config.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Admin') {
    header("Location: " . SITE_URL . "index.php?page=login");
    exit();
}

require_once $root_dir . '/partials/admin-header.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$error = '';
$success = '';
$edit_mode = false;
$edit_schedule = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_schedule'])) {
    $movie_id = intval($_POST['movie_id']);
    $show_date = htmlspecialchars(trim($_POST['show_date']));
    $showtime = htmlspecialchars(trim($_POST['showtime']));
    $total_seats = intval($_POST['total_seats']);
    
    $movie_stmt = $conn->prepare("SELECT id, title, standard_price, premium_price, sweet_spot_price FROM movies WHERE id = ? AND is_active = 1");
    $movie_stmt->bind_param("i", $movie_id);
    $movie_stmt->execute();
    $movie_result = $movie_stmt->get_result();
    
    if ($movie_result->num_rows === 0) {
        $error = "Selected movie not found or inactive!";
        $movie_stmt->close();
    } else {
        $movie = $movie_result->fetch_assoc();
        $movie_title = $movie['title'];
        $standard_price = $movie['standard_price'] ?? 350.00;
        $premium_price = $movie['premium_price'] ?? 450.00;
        $sweet_spot_price = $movie['sweet_spot_price'] ?? 550.00;
        $movie_stmt->close();
        
        $check_stmt = $conn->prepare("SELECT id FROM movie_schedules WHERE movie_id = ? AND show_date = ? AND showtime = ? AND is_active = 1");
        $check_stmt->bind_param("iss", $movie_id, $show_date, $showtime);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error = "Schedule already exists for this movie at the same date and time!";
        } else {
            $stmt = $conn->prepare("INSERT INTO movie_schedules (movie_id, movie_title, show_date, showtime, total_seats, available_seats, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)");
            $available_seats = $total_seats;
            $stmt->bind_param("isssii", $movie_id, $movie_title, $show_date, $showtime, $total_seats, $available_seats);
            
            if ($stmt->execute()) {
                $new_schedule_id = $stmt->insert_id;
                
                $seat_stmt = $conn->prepare("INSERT INTO seat_availability (schedule_id, movie_title, show_date, showtime, seat_number, seat_type, is_available, price) VALUES (?, ?, ?, ?, ?, ?, 1, ?)");
                
                for ($i = 1; $i <= $total_seats; $i++) {
                    $seat_number = chr(64 + ceil($i / 10)) . str_pad((($i - 1) % 10) + 1, 2, '0', STR_PAD_LEFT);
                    $seat_type = 'Standard';
                    $price = $standard_price;
                    
                    if ($i >= 1 && $i <= 10) {
                        $seat_type = 'Premium';
                        $price = $premium_price;
                    } elseif ($i >= 31 && $i <= 40) {
                        $seat_type = 'Sweet Spot';
                        $price = $sweet_spot_price;
                    }
                    
                    $seat_stmt->bind_param("isssssd", $new_schedule_id, $movie_title, $show_date, $showtime, $seat_number, $seat_type, $price);
                    $seat_stmt->execute();
                }
                
                $seat_stmt->close();
                $success = "Schedule added successfully! " . $total_seats . " seats created with prices from movie settings.";
                $_POST = array();
            } else {
                $error = "Failed to add schedule: " . $conn->error;
            }
            
            $stmt->close();
        }
        $check_stmt->close();
    }
}

elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_schedule'])) {
    $id = intval($_POST['id']);
    $movie_id = intval($_POST['movie_id']);
    $show_date = htmlspecialchars(trim($_POST['show_date']));
    $showtime = htmlspecialchars(trim($_POST['showtime']));
    $total_seats = intval($_POST['total_seats']);
    
    $current_stmt = $conn->prepare("SELECT movie_title FROM movie_schedules WHERE id = ?");
    $current_stmt->bind_param("i", $id);
    $current_stmt->execute();
    $current_result = $current_stmt->get_result();
    $current_schedule = $current_result->fetch_assoc();
    $current_stmt->close();
    
    $movie_stmt = $conn->prepare("SELECT title FROM movies WHERE id = ?");
    $movie_stmt->bind_param("i", $movie_id);
    $movie_stmt->execute();
    $movie_result = $movie_stmt->get_result();
    $movie = $movie_result->fetch_assoc();
    $movie_title = $movie['title'];
    $movie_stmt->close();
    
    $stmt = $conn->prepare("UPDATE movie_schedules SET movie_id = ?, movie_title = ?, show_date = ?, showtime = ?, total_seats = ? WHERE id = ?");
    $stmt->bind_param("isssii", $movie_id, $movie_title, $show_date, $showtime, $total_seats, $id);
    
    if ($stmt->execute()) {
        if ($current_schedule['movie_title'] !== $movie_title) {
            $update_seat_stmt = $conn->prepare("UPDATE seat_availability SET movie_title = ? WHERE schedule_id = ?");
            $update_seat_stmt->bind_param("si", $movie_title, $id);
            $update_seat_stmt->execute();
            $update_seat_stmt->close();
        }
        
        $success = "Schedule updated successfully!";
    } else {
        $error = "Failed to update schedule: " . $stmt->error;
    }
    $stmt->close();
}

elseif (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    $booking_check = $conn->prepare("
        SELECT COUNT(*) as booking_count 
        FROM tbl_booking b
        WHERE b.movie_name = (SELECT movie_title FROM movie_schedules WHERE id = ?)
        AND b.show_date = (SELECT show_date FROM movie_schedules WHERE id = ?)
        AND b.showtime = (SELECT showtime FROM movie_schedules WHERE id = ?)
        AND b.status != 'Cancelled'
    ");
    $booking_check->bind_param("iii", $id, $id, $id);
    $booking_check->execute();
    $booking_result = $booking_check->get_result();
    $booking_data = $booking_result->fetch_assoc();
    $booking_check->close();
    
    if ($booking_data['booking_count'] > 0) {
        $error = "Cannot delete schedule. There are active bookings for this schedule.";
    } else {
        $stmt = $conn->prepare("UPDATE movie_schedules SET is_active = 0 WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $success = "Schedule deleted successfully!";
        } else {
            $error = "Failed to delete schedule: " . $stmt->error;
        }
        $stmt->close();
    }
}

elseif (isset($_GET['manage_seats']) && is_numeric($_GET['manage_seats'])) {
    $schedule_id = intval($_GET['manage_seats']);
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_seat_types'])) {
        foreach ($_POST['seat_type'] as $seat_id => $seat_type) {
            $movie_prices = $conn->prepare("
                SELECT m.standard_price, m.premium_price, m.sweet_spot_price 
                FROM seat_availability sa
                JOIN movie_schedules ms ON sa.schedule_id = ms.id
                JOIN movies m ON ms.movie_id = m.id
                WHERE sa.id = ?
            ");
            $movie_prices->bind_param("i", $seat_id);
            $movie_prices->execute();
            $prices_result = $movie_prices->get_result();
            $prices = $prices_result->fetch_assoc();
            $movie_prices->close();
            
            $price = 350.00;
            if ($seat_type === 'Premium') {
                $price = $prices['premium_price'] ?? 450.00;
            } elseif ($seat_type === 'Sweet Spot') {
                $price = $prices['sweet_spot_price'] ?? 550.00;
            } else {
                $price = $prices['standard_price'] ?? 350.00;
            }
            
            $update_stmt = $conn->prepare("UPDATE seat_availability SET seat_type = ?, price = ? WHERE id = ?");
            $update_stmt->bind_param("sdi", $seat_type, $price, $seat_id);
            $update_stmt->execute();
            $update_stmt->close();
        }
        
        $success = "Seat types updated successfully!";
    }
}

$movies_result = $conn->query("SELECT id, title, standard_price, premium_price, sweet_spot_price FROM movies WHERE is_active = 1 ORDER BY title");
$movies = [];
if ($movies_result) {
    while ($row = $movies_result->fetch_assoc()) {
        $movies[] = $row;
    }
}

$schedules_result = $conn->query("
    SELECT s.*, m.title as movie_title_full, m.standard_price, m.premium_price, m.sweet_spot_price,
           (SELECT COUNT(*) FROM tbl_booking b 
            WHERE b.movie_name = s.movie_title 
            AND b.show_date = s.show_date 
            AND b.showtime = s.showtime 
            AND b.status != 'Cancelled') as booking_count
    FROM movie_schedules s
    LEFT JOIN movies m ON s.movie_id = m.id
    WHERE s.is_active = 1 
    ORDER BY s.show_date DESC, s.showtime
");

$schedules = [];
if ($schedules_result) {
    while ($row = $schedules_result->fetch_assoc()) {
        $schedules[] = $row;
    }
}

if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $conn->prepare("
        SELECT s.*, m.title as movie_title_full, m.standard_price, m.premium_price, m.sweet_spot_price
        FROM movie_schedules s
        LEFT JOIN movies m ON s.movie_id = m.id
        WHERE s.id = ? AND s.is_active = 1
    ");
    if ($stmt) {
        $stmt->bind_param("i", $edit_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $edit_schedule = $result->fetch_assoc();
        $edit_mode = !empty($edit_schedule);
        $stmt->close();
    }
}

if (isset($_GET['manage_seats']) && is_numeric($_GET['manage_seats'])) {
    $manage_id = intval($_GET['manage_seats']);
    $seat_stmt = $conn->prepare("
        SELECT sa.*, m.standard_price, m.premium_price, m.sweet_spot_price
        FROM seat_availability sa
        JOIN movie_schedules ms ON sa.schedule_id = ms.id
        JOIN movies m ON ms.movie_id = m.id
        WHERE sa.schedule_id = ? 
        ORDER BY sa.seat_number
    ");
    $seat_stmt->bind_param("i", $manage_id);
    $seat_stmt->execute();
    $seat_result = $seat_stmt->get_result();
    $seats = [];
    while ($row = $seat_result->fetch_assoc()) {
        $seats[] = $row;
    }
    $seat_stmt->close();
    
    $schedule_info = $conn->prepare("
        SELECT s.*, m.title as movie_title_full, m.standard_price, m.premium_price, m.sweet_spot_price
        FROM movie_schedules s
        LEFT JOIN movies m ON s.movie_id = m.id
        WHERE s.id = ?
    ");
    $schedule_info->bind_param("i", $manage_id);
    $schedule_info->execute();
    $schedule_result = $schedule_info->get_result();
    $current_schedule = $schedule_result->fetch_assoc();
    $schedule_info->close();
}

$count_result = $conn->query("SELECT COUNT(*) as total FROM movie_schedules WHERE is_active = 1");
$schedule_count = $count_result ? $count_result->fetch_assoc()['total'] : 0;

$conn->close();
?>

<div class="admin-content" style="max-width: 1400px; margin: 0 auto; padding: 30px;">
    <div style="text-align: center; margin-bottom: 40px; padding: 30px; background: linear-gradient(135deg, rgba(52, 152, 219, 0.1), rgba(41, 128, 185, 0.2)); border-radius: 20px; border: 2px solid rgba(52, 152, 219, 0.3);">
        <h1 style="color: white; font-size: 2.5rem; margin-bottom: 15px; font-weight: 800;">Manage Schedules</h1>
        <p style="color: rgba(255, 255, 255, 0.8); font-size: 1.1rem;">Add, edit, or remove movie showtimes and manage seat types</p>
    </div>

    <?php if ($error): ?>
        <div style="background: rgba(231, 76, 60, 0.2); color: #ff9999; padding: 15px 20px; border-radius: 10px; margin-bottom: 25px; font-weight: 600; text-align: center; border: 1px solid rgba(231, 76, 60, 0.3);">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div style="background: rgba(46, 204, 113, 0.2); color: #2ecc71; padding: 15px 20px; border-radius: 10px; margin-bottom: 25px; font-weight: 600; text-align: center; border: 1px solid rgba(46, 204, 113, 0.3);">
            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['manage_seats']) && isset($current_schedule)): ?>
    <div style="background: rgba(255, 255, 255, 0.05); border-radius: 15px; padding: 30px; margin-bottom: 40px; border: 1px solid rgba(52, 152, 219, 0.2);">
        <h2 style="color: white; font-size: 1.8rem; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid #3498db; display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-chair"></i> Manage Seat Types for: <?php echo htmlspecialchars($current_schedule['movie_title_full']); ?>
        </h2>
        
        <div style="background: rgba(23, 162, 184, 0.2); color: #17a2b8; padding: 15px 20px; border-radius: 10px; margin-bottom: 25px; font-weight: 600; border: 1px solid rgba(23, 162, 184, 0.3);">
            <i class="fas fa-info-circle"></i> 
            Show Time: <?php echo date('M d, Y', strtotime($current_schedule['show_date'])); ?> at <?php echo date('h:i A', strtotime($current_schedule['showtime'])); ?>
        </div>
        
        <div style="background: rgba(52, 152, 219, 0.1); border-radius: 10px; padding: 20px; margin-bottom: 30px;">
            <h3 style="color: white; font-size: 1.2rem; margin-bottom: 15px; font-weight: 600;">Price Settings from Movie</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                <div style="background: rgba(52, 152, 219, 0.2); padding: 15px; border-radius: 8px; text-align: center;">
                    <div style="color: #3498db; font-size: 0.9rem; margin-bottom: 5px;">Standard Price</div>
                    <div style="color: white; font-size: 1.3rem; font-weight: 700;">₱<?php echo number_format($current_schedule['standard_price'] ?? 350, 2); ?></div>
                </div>
                <div style="background: rgba(46, 204, 113, 0.2); padding: 15px; border-radius: 8px; text-align: center;">
                    <div style="color: #2ecc71; font-size: 0.9rem; margin-bottom: 5px;">Premium Price</div>
                    <div style="color: white; font-size: 1.3rem; font-weight: 700;">₱<?php echo number_format($current_schedule['premium_price'] ?? 450, 2); ?></div>
                </div>
                <div style="background: rgba(231, 76, 60, 0.2); padding: 15px; border-radius: 8px; text-align: center;">
                    <div style="color: #e74c3c; font-size: 0.9rem; margin-bottom: 5px;">Sweet Spot Price</div>
                    <div style="color: white; font-size: 1.3rem; font-weight: 700;">₱<?php echo number_format($current_schedule['sweet_spot_price'] ?? 550, 2); ?></div>
                </div>
            </div>
        </div>
        
        <form method="POST" action="">
            <input type="hidden" name="update_seat_types" value="1">
            
            <div style="display: flex; gap: 30px; flex-wrap: wrap; margin-bottom: 30px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 30px; height: 30px; background: #3498db; border-radius: 4px;"></div>
                    <span style="color: white; font-weight: 600;">Standard (₱<?php echo number_format($current_schedule['standard_price'] ?? 350, 2); ?>)</span>
                </div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 30px; height: 30px; background: #2ecc71; border-radius: 4px;"></div>
                    <span style="color: white; font-weight: 600;">Premium (₱<?php echo number_format($current_schedule['premium_price'] ?? 450, 2); ?>)</span>
                </div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 30px; height: 30px; background: #e74c3c; border-radius: 4px;"></div>
                    <span style="color: white; font-weight: 600;">Sweet Spot (₱<?php echo number_format($current_schedule['sweet_spot_price'] ?? 550, 2); ?>)</span>
                </div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 30px; height: 30px; background: #95a5a6; border-radius: 4px;"></div>
                    <span style="color: white; font-weight: 600;">Booked/Unavailable</span>
                </div>
            </div>
            
            <div style="background: rgba(0, 0, 0, 0.3); padding: 30px; border-radius: 10px; margin-bottom: 30px;">
                <div style="text-align: center; margin-bottom: 30px; color: white; font-size: 1.5rem; font-weight: 700;">
                    <i class="fas fa-film"></i> SCREEN
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(10, 1fr); gap: 15px; max-width: 800px; margin: 0 auto;">
                    <?php foreach ($seats as $seat): 
                        $seat_color = '#3498db';
                        if ($seat['seat_type'] === 'Premium') $seat_color = '#2ecc71';
                        if ($seat['seat_type'] === 'Sweet Spot') $seat_color = '#e74c3c';
                        if (!$seat['is_available']) $seat_color = '#95a5a6';
                        
                        $display_price = $seat['price'];
                        if ($seat['seat_type'] === 'Premium') {
                            $display_price = $current_schedule['premium_price'] ?? 450;
                        } elseif ($seat['seat_type'] === 'Sweet Spot') {
                            $display_price = $current_schedule['sweet_spot_price'] ?? 550;
                        } else {
                            $display_price = $current_schedule['standard_price'] ?? 350;
                        }
                    ?>
                    <div style="text-align: center;">
                        <div style="margin-bottom: 5px; color: white; font-size: 0.9rem; font-weight: 600;"><?php echo $seat['seat_number']; ?></div>
                        <select name="seat_type[<?php echo $seat['id']; ?>]" style="width: 100%; padding: 10px; background: <?php echo $seat_color; ?>; border: 2px solid rgba(255, 255, 255, 0.3); border-radius: 6px; color: white; font-weight: 600; cursor: pointer; text-align: center;" <?php echo !$seat['is_available'] ? 'disabled' : ''; ?>>
                            <option value="Standard" <?php echo $seat['seat_type'] === 'Standard' ? 'selected' : ''; ?> style="background: #2c3e50; color: white;">Standard</option>
                            <option value="Premium" <?php echo $seat['seat_type'] === 'Premium' ? 'selected' : ''; ?> style="background: #2c3e50; color: white;">Premium</option>
                            <option value="Sweet Spot" <?php echo $seat['seat_type'] === 'Sweet Spot' ? 'selected' : ''; ?> style="background: #2c3e50; color: white;">Sweet Spot</option>
                        </select>
                        <div style="margin-top: 5px; color: white; font-size: 0.8rem;">₱<?php echo number_format($display_price, 2); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div style="text-align: center; margin-top: 30px;">
                <button type="submit" style="padding: 16px 45px; background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; border: none; border-radius: 12px; font-size: 1.1rem; font-weight: 700; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 6px 20px rgba(52, 152, 219, 0.3); display: inline-flex; align-items: center; justify-content: center; gap: 10px;">
                    <i class="fas fa-save"></i> Update Seat Types
                </button>
                <a href="index.php?page=admin/manage-schedules" style="padding: 16px 30px; background: rgba(255, 255, 255, 0.1); color: white; text-decoration: none; border-radius: 12px; font-size: 1.1rem; font-weight: 600; border: 2px solid rgba(52, 152, 219, 0.3); margin-left: 15px; display: inline-flex; align-items: center; justify-content: center; gap: 10px;">
                    <i class="fas fa-arrow-left"></i> Back to Schedules
                </a>
            </div>
        </form>
    </div>
    
    <?php else: ?>

    <div style="background: rgba(255, 255, 255, 0.05); border-radius: 15px; padding: 30px; margin-bottom: 40px; border: 1px solid rgba(52, 152, 219, 0.2);">
        <h2 style="color: white; font-size: 1.8rem; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid #3498db; display: flex; align-items: center; gap: 10px;">
            <i class="<?php echo $edit_mode ? 'fas fa-edit' : 'fas fa-plus-circle'; ?>"></i>
            <?php echo $edit_mode ? 'Edit Schedule' : 'Add New Schedule'; ?>
        </h2>
        
        <?php if ($edit_mode): ?>
        <div style="background: rgba(23, 162, 184, 0.2); color: #17a2b8; padding: 15px 20px; border-radius: 10px; margin-bottom: 25px; font-weight: 600; border: 1px solid rgba(23, 162, 184, 0.3);">
            <i class="fas fa-info-circle"></i> 
            Editing schedule for: <strong><?php echo htmlspecialchars($edit_schedule['movie_title_full']); ?></strong>
        </div>
        <?php endif; ?>
        
        <form method="POST" action="" id="scheduleForm">
            <?php if ($edit_mode): ?>
            <input type="hidden" name="id" value="<?php echo $edit_schedule['id']; ?>">
            <?php endif; ?>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 25px; margin-bottom: 30px;">
                <div>
                    <label style="display: block; color: white; font-weight: 600; margin-bottom: 10px; font-size: 1rem;"><i class="fas fa-film"></i> Movie *</label>
                    <select id="movie_id" name="movie_id" required style="width: 100%; padding: 14px 16px; background: rgba(255, 255, 255, 0.08); border: 2px solid rgba(52, 152, 219, 0.3); border-radius: 10px; color: white; font-size: 1rem; cursor: pointer; appearance: none; background-image: url('data:image/svg+xml;utf8,<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"20\" height=\"20\" fill=\"white\" viewBox=\"0 0 20 20\"><path d=\"M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z\"/></svg>'); background-repeat: no-repeat; background-position: right 16px center; background-size: 16px;">
                        <option value="">Select Movie</option>
                        <?php foreach ($movies as $movie): ?>
                        <option value="<?php echo $movie['id']; ?>" 
                                data-standard="<?php echo $movie['standard_price'] ?? 350; ?>"
                                data-premium="<?php echo $movie['premium_price'] ?? 450; ?>"
                                data-sweet="<?php echo $movie['sweet_spot_price'] ?? 550; ?>"
                                <?php echo ($edit_mode && $edit_schedule['movie_id'] == $movie['id']) || (isset($_POST['movie_id']) && $_POST['movie_id'] == $movie['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($movie['title']); ?> 
                            (Std: ₱<?php echo number_format($movie['standard_price'] ?? 350, 0); ?> | 
                            Prem: ₱<?php echo number_format($movie['premium_price'] ?? 450, 0); ?> | 
                            Sweet: ₱<?php echo number_format($movie['sweet_spot_price'] ?? 550, 0); ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label style="display: block; color: white; font-weight: 600; margin-bottom: 10px; font-size: 1rem;"><i class="fas fa-calendar"></i> Show Date *</label>
                    <input type="date" id="show_date" name="show_date" required 
                           value="<?php echo $edit_mode ? htmlspecialchars($edit_schedule['show_date']) : (isset($_POST['show_date']) ? htmlspecialchars($_POST['show_date']) : ''); ?>"
                           style="width: 100%; padding: 14px 16px; background: rgba(255, 255, 255, 0.08); border: 2px solid rgba(52, 152, 219, 0.3); border-radius: 10px; color: white; font-size: 1rem;"
                           min="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <div>
                    <label style="display: block; color: white; font-weight: 600; margin-bottom: 10px; font-size: 1rem;"><i class="fas fa-clock"></i> Show Time *</label>
                    <input type="time" id="showtime" name="showtime" required
                           value="<?php echo $edit_mode ? htmlspecialchars($edit_schedule['showtime']) : (isset($_POST['showtime']) ? htmlspecialchars($_POST['showtime']) : ''); ?>"
                           style="width: 100%; padding: 14px 16px; background: rgba(255, 255, 255, 0.08); border: 2px solid rgba(52, 152, 219, 0.3); border-radius: 10px; color: white; font-size: 1rem;"
                           min="09:00" max="23:00">
                </div>
                
                <div>
                    <label style="display: block; color: white; font-weight: 600; margin-bottom: 10px; font-size: 1rem;"><i class="fas fa-chair"></i> Total Seats *</label>
                    <input type="number" id="total_seats" name="total_seats" required
                           value="<?php echo $edit_mode ? $edit_schedule['total_seats'] : (isset($_POST['total_seats']) ? $_POST['total_seats'] : '40'); ?>"
                           style="width: 100%; padding: 14px 16px; background: rgba(255, 255, 255, 0.08); border: 2px solid rgba(52, 152, 219, 0.3); border-radius: 10px; color: white; font-size: 1rem;"
                           min="1" max="100" placeholder="Maximum seats">
                    <div style="color: rgba(255, 255, 255, 0.6); font-size: 0.9rem; margin-top: 5px;">Standard: 40 seats (A01-A40)</div>
                </div>
            </div>
            
            <div id="pricePreview" style="background: rgba(52, 152, 219, 0.1); border-radius: 10px; padding: 20px; margin-bottom: 30px; display: none;">
                <h3 style="color: white; font-size: 1.1rem; margin-bottom: 15px; font-weight: 600;">Price Settings for Selected Movie</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px;">
                    <div style="background: rgba(52, 152, 219, 0.2); padding: 12px; border-radius: 6px; text-align: center;">
                        <div style="color: #3498db; font-size: 0.9rem;">Standard</div>
                        <div style="color: white; font-size: 1.2rem; font-weight: 700;" id="previewStandard">₱350.00</div>
                    </div>
                    <div style="background: rgba(46, 204, 113, 0.2); padding: 12px; border-radius: 6px; text-align: center;">
                        <div style="color: #2ecc71; font-size: 0.9rem;">Premium</div>
                        <div style="color: white; font-size: 1.2rem; font-weight: 700;" id="previewPremium">₱450.00</div>
                    </div>
                    <div style="background: rgba(231, 76, 60, 0.2); padding: 12px; border-radius: 6px; text-align: center;">
                        <div style="color: #e74c3c; font-size: 0.9rem;">Sweet Spot</div>
                        <div style="color: white; font-size: 1.2rem; font-weight: 700;" id="previewSweet">₱550.00</div>
                    </div>
                </div>
            </div>
            
            <div style="text-align: center; margin-top: 30px;">
                <?php if ($edit_mode): ?>
                <button type="submit" name="update_schedule" style="padding: 16px 45px; background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; border: none; border-radius: 12px; font-size: 1.1rem; font-weight: 700; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 6px 20px rgba(52, 152, 219, 0.3); display: inline-flex; align-items: center; justify-content: center; gap: 10px;">
                    <i class="fas fa-save"></i> Update Schedule
                </button>
                <a href="index.php?page=admin/manage-schedules" style="padding: 16px 30px; background: rgba(255, 255, 255, 0.1); color: white; text-decoration: none; border-radius: 12px; font-size: 1.1rem; font-weight: 600; border: 2px solid rgba(52, 152, 219, 0.3); margin-left: 15px; display: inline-flex; align-items: center; justify-content: center; gap: 10px;">
                    <i class="fas fa-times"></i> Cancel
                </a>
                <?php else: ?>
                <button type="submit" name="add_schedule" style="padding: 16px 45px; background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; border: none; border-radius: 12px; font-size: 1.1rem; font-weight: 700; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 6px 20px rgba(52, 152, 219, 0.3); display: inline-flex; align-items: center; justify-content: center; gap: 10px;">
                    <i class="fas fa-plus"></i> Add Schedule
                </button>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div style="background: rgba(255, 255, 255, 0.05); border-radius: 15px; padding: 30px; border: 1px solid rgba(52, 152, 219, 0.2);">
        <h2 style="color: white; font-size: 1.8rem; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid #3498db; display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-calendar-alt"></i> All Schedules (<?php echo $schedule_count; ?>)
        </h2>
        
        <?php if (empty($schedules)): ?>
        <div style="text-align: center; padding: 50px; color: rgba(255, 255, 255, 0.6);">
            <i class="fas fa-calendar-alt fa-3x" style="margin-bottom: 20px; opacity: 0.5;"></i>
            <p style="font-size: 1.1rem;">No schedules found. Add your first schedule!</p>
        </div>
        <?php else: ?>
        <div style="overflow-x: auto; border-radius: 10px; border: 1px solid rgba(52, 152, 219, 0.2);">
            <table style="width: 100%; border-collapse: collapse; min-width: 1200px;">
                <thead>
                    <tr>
                        <th style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; padding: 16px; text-align: left; font-weight: 700; font-size: 1rem;">ID</th>
                        <th style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; padding: 16px; text-align: left; font-weight: 700; font-size: 1rem;">Movie Details</th>
                        <th style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; padding: 16px; text-align: left; font-weight: 700; font-size: 1rem;">Price Settings</th>
                        <th style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; padding: 16px; text-align: left; font-weight: 700; font-size: 1rem;">Show Time</th>
                        <th style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; padding: 16px; text-align: left; font-weight: 700; font-size: 1rem;">Seats</th>
                        <th style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; padding: 16px; text-align: left; font-weight: 700; font-size: 1rem;">Bookings</th>
                        <th style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; padding: 16px; text-align: left; font-weight: 700; font-size: 1rem;">Status</th>
                        <th style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; padding: 16px; text-align: left; font-weight: 700; font-size: 1rem;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($schedules as $schedule): 
                        $available_percentage = ($schedule['available_seats'] / $schedule['total_seats']) * 100;
                        $is_today = date('Y-m-d') == $schedule['show_date'];
                        $is_past = strtotime($schedule['show_date'] . ' ' . $schedule['showtime']) < time();
                    ?>
                    <tr style="border-bottom: 1px solid rgba(255, 255, 255, 0.1); <?php echo $is_today ? 'background: rgba(52, 152, 219, 0.05);' : ''; ?>">
                        <td style="padding: 16px; color: rgba(255, 255, 255, 0.9); font-weight: 700;"><?php echo $schedule['id']; ?></td>
                        <td style="padding: 16px;">
                            <div style="color: white; font-size: 1.1rem; font-weight: 700; margin-bottom: 5px;"><?php echo htmlspecialchars($schedule['movie_title_full']); ?></div>
                            <div style="color: rgba(255, 255, 255, 0.7); font-size: 0.9rem;">Movie ID: <?php echo $schedule['movie_id']; ?></div>
                        </td>
                        <td style="padding: 16px;">
                            <div style="background: rgba(52, 152, 219, 0.1); padding: 10px; border-radius: 6px;">
                                <div style="color: #3498db; font-size: 0.85rem;">Standard: ₱<?php echo number_format($schedule['standard_price'] ?? 350, 2); ?></div>
                                <div style="color: #2ecc71; font-size: 0.85rem;">Premium: ₱<?php echo number_format($schedule['premium_price'] ?? 450, 2); ?></div>
                                <div style="color: #e74c3c; font-size: 0.85rem;">Sweet Spot: ₱<?php echo number_format($schedule['sweet_spot_price'] ?? 550, 2); ?></div>
                            </div>
                        </td>
                        <td style="padding: 16px;">
                            <div style="color: #3498db; font-size: 1.1rem; font-weight: 700;"><?php echo date('h:i A', strtotime($schedule['showtime'])); ?></div>
                            <div style="color: rgba(255, 255, 255, 0.8); margin-top: 5px;">
                                <i class="far fa-calendar"></i> <?php echo date('M d, Y', strtotime($schedule['show_date'])); ?>
                                <?php if ($is_today): ?>
                                <span style="background: #3498db; color: white; padding: 3px 8px; border-radius: 4px; font-size: 0.8rem; margin-left: 5px;">TODAY</span>
                                <?php endif; ?>
                            </div>
                            <?php if ($is_past): ?>
                            <div style="color: #e74c3c; font-size: 0.8rem; margin-top: 3px;">
                                <i class="fas fa-exclamation-triangle"></i> Past show
                            </div>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 16px;">
                            <div style="background: rgba(52, 152, 219, 0.1); padding: 12px; border-radius: 8px;">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                    <span style="color: rgba(255, 255, 255, 0.8);">Available:</span>
                                    <span style="color: white; font-weight: 700;"><?php echo $schedule['available_seats']; ?></span>
                                </div>
                                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                    <span style="color: rgba(255, 255, 255, 0.8);">Total:</span>
                                    <span style="color: white;"><?php echo $schedule['total_seats']; ?></span>
                                </div>
                                <div style="background: rgba(255, 255, 255, 0.1); height: 8px; border-radius: 4px; overflow: hidden;">
                                    <div style="background: <?php echo $available_percentage > 50 ? '#2ecc71' : ($available_percentage > 20 ? '#f39c12' : '#e74c3c'); ?>; height: 100%; width: <?php echo $available_percentage; ?>%;"></div>
                                </div>
                            </div>
                        </td>
                        <td style="padding: 16px;">
                            <div style="text-align: center;">
                                <div style="font-size: 1.5rem; font-weight: 700; color: <?php echo $schedule['booking_count'] > 0 ? '#3498db' : 'rgba(255,255,255,0.5)'; ?>;">
                                    <?php echo $schedule['booking_count']; ?>
                                </div>
                                <div style="font-size: 0.9rem; color: rgba(255, 255, 255, 0.6);">Bookings</div>
                            </div>
                        </td>
                        <td style="padding: 16px;">
                            <?php if ($is_past): ?>
                            <span style="background: rgba(231, 76, 60, 0.2); color: #e74c3c; padding: 8px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; display: inline-flex; align-items: center; gap: 5px;">
                                <i class="fas fa-clock"></i> Expired
                            </span>
                            <?php elseif ($schedule['available_seats'] == 0): ?>
                            <span style="background: rgba(231, 76, 60, 0.2); color: #e74c3c; padding: 8px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; display: inline-flex; align-items: center; gap: 5px;">
                                <i class="fas fa-times-circle"></i> Sold Out
                            </span>
                            <?php elseif ($schedule['available_seats'] < 10): ?>
                            <span style="background: rgba(243, 156, 18, 0.2); color: #f39c12; padding: 8px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; display: inline-flex; align-items: center; gap: 5px;">
                                <i class="fas fa-exclamation"></i> Few Seats
                            </span>
                            <?php else: ?>
                            <span style="background: rgba(46, 204, 113, 0.2); color: #2ecc71; padding: 8px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; display: inline-flex; align-items: center; gap: 5px;">
                                <i class="fas fa-check-circle"></i> Active
                            </span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 16px;">
                            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                <a href="index.php?page=admin/manage-schedules&edit=<?php echo $schedule['id']; ?>" 
                                   style="padding: 8px 16px; background: rgba(52, 152, 219, 0.2); color: #3498db; text-decoration: none; border-radius: 6px; font-size: 0.85rem; font-weight: 600; border: 1px solid rgba(52, 152, 219, 0.3); display: inline-flex; align-items: center; gap: 5px;">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <a href="index.php?page=admin/manage-schedules&manage_seats=<?php echo $schedule['id']; ?>" 
                                   style="padding: 8px 16px; background: rgba(155, 89, 182, 0.2); color: #9b59b6; text-decoration: none; border-radius: 6px; font-size: 0.85rem; font-weight: 600; border: 1px solid rgba(155, 89, 182, 0.3); display: inline-flex; align-items: center; gap: 5px;">
                                    <i class="fas fa-chair"></i> Seats
                                </a>
                                <a href="index.php?page=admin/manage-schedules&delete=<?php echo $schedule['id']; ?>" 
                                   style="padding: 8px 16px; background: rgba(231, 76, 60, 0.2); color: #e74c3c; text-decoration: none; border-radius: 6px; font-size: 0.85rem; font-weight: 600; border: 1px solid rgba(231, 76, 60, 0.3); display: inline-flex; align-items: center; gap: 5px;"
                                   onclick="return confirm('Are you sure you want to delete this schedule?\nMovie: <?php echo addslashes($schedule['movie_title_full']); ?>\nDate: <?php echo date('M d, Y', strtotime($schedule['show_date'])); ?> <?php echo date('h:i A', strtotime($schedule['showtime'])); ?>')">
                                    <i class="fas fa-trash"></i> Delete
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    
    <?php endif; ?>
</div>

<style>
    input:focus, select:focus, textarea:focus {
        outline: none;
        background: rgba(255, 255, 255, 0.12);
        border-color: #3498db;
        box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.2);
    }
    
    button:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 30px rgba(52, 152, 219, 0.4);
    }
    
    a:hover {
        transform: translateY(-2px);
        opacity: 0.9;
    }
    
    tr:hover {
        background: rgba(255, 255, 255, 0.03) !important;
    }
    
    select:hover {
        background: rgba(255, 255, 255, 0.12);
        border-color: #3498db;
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
        .admin-content {
            padding: 15px;
        }
        
        div > div {
            padding: 20px;
        }
        
        table {
            font-size: 0.9rem;
        }
        
        .seat-grid {
            grid-template-columns: repeat(5, 1fr) !important;
            gap: 10px !important;
        }
    }
    
    @media (max-width: 480px) {
        .seat-grid {
            grid-template-columns: repeat(4, 1fr) !important;
        }
    }
</style>

<script>
document.getElementById('scheduleForm')?.addEventListener('submit', function(e) {
    const movieId = document.getElementById('movie_id').value;
    const showDate = document.getElementById('show_date').value;
    const showtime = document.getElementById('showtime').value;
    const totalSeats = document.getElementById('total_seats').value;
    
    if (!movieId || !showDate || !showtime || !totalSeats || parseInt(totalSeats) < 1) {
        e.preventDefault();
        alert('Please fill in all required fields correctly!');
        return false;
    }
    
    const selectedDate = new Date(showDate + 'T' + showtime);
    if (selectedDate < new Date()) {
        e.preventDefault();
        if (!confirm('Warning: You are scheduling a show in the past. Continue anyway?')) {
            return false;
        }
    }
    
    return true;
});

document.getElementById('show_date').min = new Date().toISOString().split('T')[0];

document.getElementById('showtime')?.addEventListener('focus', function() {
    if (!this.value) {
        this.value = '18:00';
    }
});

const seatSelects = document.querySelectorAll('select[name^="seat_type"]');
seatSelects.forEach(select => {
    select.addEventListener('change', function() {
        const colorMap = {
            'Standard': '#3498db',
            'Premium': '#2ecc71',
            'Sweet Spot': '#e74c3c'
        };
        this.style.background = colorMap[this.value] || '#3498db';
    });
});

document.getElementById('movie_id')?.addEventListener('change', function() {
    const selected = this.options[this.selectedIndex];
    const standard = selected.dataset.standard || '350';
    const premium = selected.dataset.premium || '450';
    const sweet = selected.dataset.sweet || '550';
    
    document.getElementById('previewStandard').textContent = '₱' + parseFloat(standard).toFixed(2);
    document.getElementById('previewPremium').textContent = '₱' + parseFloat(premium).toFixed(2);
    document.getElementById('previewSweet').textContent = '₱' + parseFloat(sweet).toFixed(2);
    
    document.getElementById('pricePreview').style.display = 'block';
});

window.addEventListener('load', function() {
    const movieSelect = document.getElementById('movie_id');
    if (movieSelect && movieSelect.value) {
        movieSelect.dispatchEvent(new Event('change'));
    }
});
</script>

</div>
</body>
</html>