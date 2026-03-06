<?php
$root_dir = dirname(dirname(__DIR__));
require_once $root_dir . '/includes/config.php';
require_once $root_dir . '/includes/functions.php';
require_once $root_dir . '/includes/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Customer') {
    header("Location: " . SITE_URL . "index.php?page=login");
    exit();
}

$conn = get_db();
$user_id = $_SESSION['user_id'];

$error = '';
$success = '';
$selected_seats = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['confirm_booking'])) {
        $schedule_id = isset($_POST['schedule_id']) ? intval($_POST['schedule_id']) : 0;
        $selected_seats = isset($_POST['selected_seats']) ? $_POST['selected_seats'] : [];
        
        if ($schedule_id <= 0) {
            $error = "Please select a showtime!";
        } elseif (empty($selected_seats)) {
            $error = "Please select at least one seat!";
        } else {
            $schedule_stmt = $conn->prepare("SELECT * FROM movie_schedules WHERE id = ? AND is_active = 1");
            $schedule_stmt->bind_param("i", $schedule_id);
            $schedule_stmt->execute();
            $schedule_result = $schedule_stmt->get_result();
            
            if ($schedule_result->num_rows === 0) {
                $error = "Invalid schedule selected!";
            } else {
                $schedule = $schedule_result->fetch_assoc();
                $schedule_stmt->close();
                
                if (count($selected_seats) > $schedule['available_seats']) {
                    $error = "Only {$schedule['available_seats']} seats available. You cannot book more than available seats.";
                } else {
                    $seat_check_failed = false;
                    $unavailable_seats = [];
                    
                    foreach ($selected_seats as $seat_number) {
                        $seat_check = $conn->prepare("
                            SELECT is_available, price, seat_type 
                            FROM seat_availability 
                            WHERE schedule_id = ? 
                            AND seat_number = ? 
                            AND is_available = 1
                        ");
                        $seat_check->bind_param("is", $schedule_id, $seat_number);
                        $seat_check->execute();
                        $seat_result = $seat_check->get_result();
                        
                        if ($seat_result->num_rows === 0) {
                            $seat_check_failed = true;
                            $unavailable_seats[] = $seat_number;
                        }
                        $seat_check->close();
                    }
                    
                    if ($seat_check_failed) {
                        $error = "Seats " . implode(", ", $unavailable_seats) . " are no longer available!";
                    } else {
                        $conn->begin_transaction();
                        
                        try {
                            $booking_reference = 'BK' . date('Ymd') . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
                            $seat_numbers = implode(', ', $selected_seats);
                            
                            $total_fee = 0;
                            foreach ($selected_seats as $seat_number) {
                                $price_stmt = $conn->prepare("SELECT price FROM seat_availability WHERE schedule_id = ? AND seat_number = ?");
                                $price_stmt->bind_param("is", $schedule_id, $seat_number);
                                $price_stmt->execute();
                                $price_result = $price_stmt->get_result();
                                $seat_price = $price_result->fetch_assoc();
                                $total_fee += $seat_price['price'];
                                $price_stmt->close();
                            }
                            
                            $booking_stmt = $conn->prepare("
                                INSERT INTO tbl_booking (
                                    u_id, movie_name, show_date, showtime, 
                                    seat_no, booking_fee, status, booking_reference
                                ) VALUES (?, ?, ?, ?, ?, ?, 'Ongoing', ?)
                            ");
                            $booking_stmt->bind_param(
                                "issssds",
                                $user_id,
                                $schedule['movie_title'],
                                $schedule['show_date'],
                                $schedule['showtime'],
                                $seat_numbers,
                                $total_fee,
                                $booking_reference
                            );
                            
                            if (!$booking_stmt->execute()) {
                                throw new Exception("Failed to create booking: " . $booking_stmt->error);
                            }
                            
                            $booking_id = $booking_stmt->insert_id;
                            $booking_stmt->close();
                            
                            foreach ($selected_seats as $seat_number) {
                                $seat_update = $conn->prepare("
                                    UPDATE seat_availability 
                                    SET is_available = 0, booking_id = ?
                                    WHERE schedule_id = ? 
                                    AND seat_number = ?
                                ");
                                $seat_update->bind_param("iis", $booking_id, $schedule_id, $seat_number);
                                
                                if (!$seat_update->execute()) {
                                    throw new Exception("Failed to update seat availability: " . $seat_update->error);
                                }
                                $seat_update->close();
                            }
                            
                            $seat_count = count($selected_seats);
                            $update_schedule = $conn->prepare("
                                UPDATE movie_schedules 
                                SET available_seats = available_seats - ?
                                WHERE id = ?
                            ");
                            $update_schedule->bind_param("ii", $seat_count, $schedule_id);
                            
                            if (!$update_schedule->execute()) {
                                throw new Exception("Failed to update schedule: " . $update_schedule->error);
                            }
                            $update_schedule->close();
                            
                            $conn->commit();
                            
                            $success = "Booking confirmed! Reference: <strong>$booking_reference</strong>";
                            $selected_seats = [];
                            
                        } catch (Exception $e) {
                            $conn->rollback();
                            $error = "Booking failed: " . $e->getMessage();
                        }
                    }
                }
            }
        }
    }
    
    if (isset($_POST['select_movie'])) {
        $selected_movie_id = intval($_POST['movie_id']);
        header("Location: " . SITE_URL . "index.php?page=customer/booking&movie=" . $selected_movie_id);
        exit();
    }
}

$movie_id = isset($_GET['movie']) ? intval($_GET['movie']) : 0;

$movies_stmt = $conn->prepare("
    SELECT id, title, rating, duration, standard_price, premium_price, sweet_spot_price 
    FROM movies 
    WHERE is_active = 1 
    ORDER BY title
");
$movies_stmt->execute();
$movies_result = $movies_stmt->get_result();
$all_movies = [];
$current_movie = null;

while ($row = $movies_result->fetch_assoc()) {
    $all_movies[] = $row;
    if ($row['id'] == $movie_id) {
        $current_movie = $row;
    }
}
$movies_stmt->close();

if (!$current_movie && !empty($all_movies)) {
    $current_movie = $all_movies[0];
    $movie_id = $current_movie['id'];
}

if ($current_movie) {
    $movie_details_stmt = $conn->prepare("
        SELECT * FROM movies 
        WHERE id = ? AND is_active = 1
    ");
    $movie_details_stmt->bind_param("i", $movie_id);
    $movie_details_stmt->execute();
    $movie_details_result = $movie_details_stmt->get_result();
    $movie = $movie_details_result->fetch_assoc();
    $movie_details_stmt->close();
}

$schedules = [];
if ($movie_id > 0) {
    $schedules_stmt = $conn->prepare("
        SELECT * FROM movie_schedules 
        WHERE movie_id = ? 
        AND is_active = 1 
        AND show_date >= CURDATE()
        AND available_seats > 0
        ORDER BY show_date, showtime
    ");
    $schedules_stmt->bind_param("i", $movie_id);
    $schedules_stmt->execute();
    $schedules_result = $schedules_stmt->get_result();
    
    while ($row = $schedules_result->fetch_assoc()) {
        $schedules[] = $row;
    }
    $schedules_stmt->close();
}

$selected_schedule_id = isset($_POST['schedule_id']) ? intval($_POST['schedule_id']) : 
                       (isset($_GET['schedule']) ? intval($_GET['schedule']) : 0);

$seats = [];
$total_seats = 0;
$available_seats = 0;
$selected_schedule_data = null;
$seat_prices = [];

if ($selected_schedule_id > 0) {
    $schedule_info = $conn->prepare("
        SELECT s.*, m.standard_price, m.premium_price, m.sweet_spot_price 
        FROM movie_schedules s
        JOIN movies m ON s.movie_id = m.id
        WHERE s.id = ? AND s.is_active = 1
    ");
    $schedule_info->bind_param("i", $selected_schedule_id);
    $schedule_info->execute();
    $schedule_info_result = $schedule_info->get_result();
    
    if ($schedule_info_result->num_rows > 0) {
        $selected_schedule_data = $schedule_info_result->fetch_assoc();
        $total_seats = $selected_schedule_data['total_seats'];
        $available_seats = $selected_schedule_data['available_seats'];
        $seat_prices = [
            'Standard' => $selected_schedule_data['standard_price'] ?? 350,
            'Premium' => $selected_schedule_data['premium_price'] ?? 450,
            'Sweet Spot' => $selected_schedule_data['sweet_spot_price'] ?? 550
        ];
    }
    $schedule_info->close();
    
    $seats_stmt = $conn->prepare("
        SELECT seat_number, is_available, seat_type, price 
        FROM seat_availability 
        WHERE schedule_id = ? 
        ORDER BY seat_number
    ");
    $seats_stmt->bind_param("i", $selected_schedule_id);
    $seats_stmt->execute();
    $seats_result = $seats_stmt->get_result();
    
    while ($row = $seats_result->fetch_assoc()) {
        $seats[] = $row;
    }
    $seats_stmt->close();
}

$conn->close();

require_once $root_dir . '/partials/header.php';
?>

<div class="main-container" style="max-width: 1400px; margin: 0 auto; padding: 20px;">
    <div style="background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%); 
         border-radius: 15px; padding: 25px; margin-bottom: 30px; 
         border: 1px solid rgba(226, 48, 32, 0.3);">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
            <div>
                <h1 style="color: white; font-size: 2rem; margin-bottom: 10px; font-weight: 800;">
                    <i class="fas fa-ticket-alt"></i> Book Movie Tickets
                </h1>
                <p style="color: var(--pale-red); font-size: 1.1rem;">
                    Select your movie, showtime, and preferred seats
                </p>
            </div>
            
            <div style="min-width: 300px;">
                <form method="POST" action="" id="movieSelectForm">
                    <label style="display: block; color: white; font-weight: 600; margin-bottom: 8px; font-size: 0.95rem;">
                        <i class="fas fa-film"></i> Select Movie:
                    </label>
                    <div style="display: flex; gap: 10px;">
                        <select name="movie_id" id="movieSelect" 
                                style="flex: 1; padding: 12px 15px; background: rgba(255, 255, 255, 0.15); border: 2px solid rgba(226, 48, 32, 0.4); border-radius: 8px; color: white; font-size: 1rem; cursor: pointer; transition: all 0.3s ease; appearance: none; background-image: url('data:image/svg+xml;utf8,<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"20\" height=\"20\" fill=\"white\" viewBox=\"0 0 20 20\"><path d=\"M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z\"/></svg>'); background-repeat: no-repeat; background-position: right 16px center; background-size: 16px;">
                            <?php foreach ($all_movies as $movie_option): ?>
                            <option value="<?php echo $movie_option['id']; ?>" 
                                <?php echo $movie_option['id'] == $movie_id ? 'selected' : ''; ?>
                                style="background: var(--bg-card); color: white;">
                                <?php echo htmlspecialchars($movie_option['title']); ?> 
                                (<?php echo $movie_option['rating']; ?> • <?php echo $movie_option['duration']; ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" name="select_movie" class="btn btn-primary" style="padding: 12px 20px;">
                            <i class="fas fa-sync-alt"></i> Load
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger" style="background: rgba(226, 48, 32, 0.2); color: #ff9999; 
             padding: 15px 20px; border-radius: 10px; margin-bottom: 25px; border: 1px solid rgba(226, 48, 32, 0.3);
             display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-exclamation-circle fa-lg"></i>
            <div><?php echo $error; ?></div>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success" style="background: rgba(46, 204, 113, 0.2); color: #2ecc71; 
             padding: 15px 20px; border-radius: 10px; margin-bottom: 25px; border: 1px solid rgba(46, 204, 113, 0.3);
             display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-check-circle fa-lg"></i>
            <div><?php echo $success; ?></div>
            <a href="<?php echo SITE_URL; ?>index.php?page=customer/my-bookings" 
               class="btn btn-secondary" style="margin-left: auto; padding: 8px 15px; font-size: 0.9rem;">
                <i class="fas fa-ticket-alt"></i> View My Bookings
            </a>
        </div>
    <?php endif; ?>
    
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 40px; min-height: 600px;">
        <div>
            <div style="background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%); 
                 border-radius: 15px; padding: 25px; margin-bottom: 30px; 
                 border: 1px solid rgba(226, 48, 32, 0.3);">
                <?php if ($movie): ?>
                <div style="display: flex; gap: 25px; align-items: flex-start;">
                    <?php if (!empty($movie['poster_url'])): ?>
                    <img src="<?php echo $movie['poster_url']; ?>" 
                         alt="<?php echo htmlspecialchars($movie['title']); ?>"
                         style="width: 180px; height: 240px; object-fit: cover; border-radius: 10px; flex-shrink: 0;">
                    <?php else: ?>
                    <div style="width: 180px; height: 240px; background: linear-gradient(135deg, rgba(226, 48, 32, 0.1), rgba(193, 27, 24, 0.2)); 
                         border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <i class="fas fa-film" style="font-size: 3rem; color: rgba(255, 255, 255, 0.3);"></i>
                    </div>
                    <?php endif; ?>
                    
                    <div style="flex: 1;">
                        <h2 style="color: white; font-size: 1.8rem; margin-bottom: 15px; font-weight: 700; line-height: 1.3;">
                            <?php echo htmlspecialchars($movie['title']); ?>
                        </h2>
                        
                        <div style="display: flex; flex-wrap: wrap; gap: 15px; margin-bottom: 20px;">
                            <div style="background: var(--primary-red); color: white; padding: 6px 15px; 
                                 border-radius: 20px; font-weight: 700; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 5px;">
                                <i class="fas fa-star"></i> <?php echo $movie['rating'] ?: 'PG'; ?>
                            </div>
                            
                            <div style="background: rgba(255,255,255,0.1); color: var(--pale-red); padding: 6px 15px; 
                                 border-radius: 20px; font-weight: 600; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 5px;">
                                <i class="fas fa-clock"></i> <?php echo $movie['duration']; ?>
                            </div>
                            
                            <?php if ($movie['genre']): ?>
                            <div style="background: rgba(255,255,255,0.1); color: var(--pale-red); padding: 6px 15px; 
                                 border-radius: 20px; font-weight: 600; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 5px;">
                                <i class="fas fa-film"></i> <?php echo htmlspecialchars($movie['genre']); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($movie['description']): ?>
                        <div style="background: rgba(255,255,255,0.05); padding: 20px; border-radius: 10px; margin-bottom: 20px;">
                            <h4 style="color: white; font-size: 1.1rem; margin-bottom: 10px; font-weight: 600;">
                                <i class="fas fa-info-circle"></i> Synopsis
                            </h4>
                            <p style="color: rgba(255, 255, 255, 0.8); line-height: 1.6; font-size: 0.95rem;">
                                <?php echo htmlspecialchars($movie['description']); ?>
                            </p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php else: ?>
                <div style="text-align: center; padding: 40px; color: var(--pale-red);">
                    <i class="fas fa-film fa-3x" style="margin-bottom: 15px; opacity: 0.5;"></i>
                    <p>Please select a movie to view details.</p>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if ($movie): ?>
            <div style="background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%); 
                 border-radius: 15px; padding: 25px; margin-bottom: 30px; 
                 border: 1px solid rgba(226, 48, 32, 0.3);">
                <h3 style="color: white; font-size: 1.4rem; margin-bottom: 20px; font-weight: 700; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-calendar-alt"></i> Available Showtimes
                </h3>
                
                <?php if (empty($schedules)): ?>
                <div style="text-align: center; padding: 30px; color: var(--pale-red);">
                    <i class="fas fa-calendar-times fa-2x" style="margin-bottom: 15px; opacity: 0.7;"></i>
                    <h4 style="color: white; margin-bottom: 10px;">No Showtimes Available</h4>
                    <p>There are no available showtimes for this movie at the moment.</p>
                    <p style="font-size: 0.9rem; margin-top: 10px; color: rgba(255,255,255,0.6);">
                        Please check back later or select another movie.
                    </p>
                </div>
                <?php else: ?>
                <form method="POST" action="" id="scheduleForm">
                    <input type="hidden" name="movie" value="<?php echo $movie_id; ?>">
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 15px;">
                        <?php foreach ($schedules as $schedule): 
                            $is_selected = $selected_schedule_id == $schedule['id'];
                            $is_today = date('Y-m-d') == $schedule['show_date'];
                            $is_tomorrow = date('Y-m-d', strtotime('+1 day')) == $schedule['show_date'];
                            $show_date = date('D, M d, Y', strtotime($schedule['show_date']));
                            $show_time = date('h:i A', strtotime($schedule['showtime']));
                            $available_seats_percentage = ($schedule['available_seats'] / $schedule['total_seats']) * 100;
                            $seats_left_text = $schedule['available_seats'] <= 10 ? "{$schedule['available_seats']} seats left" : '';
                        ?>
                        <label style="cursor: pointer;">
                            <input type="radio" name="schedule_id" value="<?php echo $schedule['id']; ?>" 
                                   <?php echo $is_selected ? 'checked' : ''; ?> 
                                   class="schedule-radio" style="display: none;"
                                   onchange="this.form.submit()">
                            <div style="background: <?php echo $is_selected ? 'rgba(226, 48, 32, 0.2)' : 'rgba(255, 255, 255, 0.05)'; ?>; 
                                 border: 2px solid <?php echo $is_selected ? 'var(--primary-red)' : 'rgba(226, 48, 32, 0.3)'; ?>; 
                                 border-radius: 10px; padding: 15px; transition: all 0.3s ease; height: 100%;">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                                    <div style="font-size: 1.2rem; color: white; font-weight: 700;">
                                        <?php echo $show_time; ?>
                                    </div>
                                    <?php if ($is_today): ?>
                                    <span style="background: var(--primary-red); color: white; padding: 3px 8px; 
                                          border-radius: 12px; font-size: 0.7rem; font-weight: 600;">TODAY</span>
                                    <?php elseif ($is_tomorrow): ?>
                                    <span style="background: #3498db; color: white; padding: 3px 8px; 
                                          border-radius: 12px; font-size: 0.7rem; font-weight: 600;">TOMORROW</span>
                                    <?php endif; ?>
                                </div>
                                
                                <div style="color: var(--pale-red); font-size: 0.9rem; margin-bottom: 15px;">
                                    <i class="far fa-calendar"></i> <?php echo $show_date; ?>
                                </div>
                                
                                <div style="margin-bottom: 10px;">
                                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 5px;">
                                        <i class="fas fa-chair" style="color: <?php echo $schedule['available_seats'] < 10 ? '#ff6b6b' : '#2ecc71'; ?>;"></i>
                                        <span style="color: <?php echo $schedule['available_seats'] < 10 ? '#ff6b6b' : '#2ecc71'; ?>; font-weight: 600; font-size: 0.95rem;">
                                            <?php echo $schedule['available_seats']; ?> seats available
                                            <?php if ($seats_left_text): ?>
                                            <span style="color: #ff6b6b; font-weight: 700;"> (<?php echo $seats_left_text; ?>)</span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <div style="background: rgba(255,255,255,0.1); height: 6px; border-radius: 3px; overflow: hidden;">
                                        <div style="background: <?php echo $available_seats_percentage > 50 ? '#2ecc71' : ($available_seats_percentage > 20 ? '#f39c12' : '#e74c3c'); ?>; 
                                             height: 100%; width: <?php echo $available_seats_percentage; ?>%;"></div>
                                    </div>
                                </div>
                                
                                <div style="font-size: 0.8rem; color: rgba(255,255,255,0.6);">
                                    <?php echo $schedule['total_seats']; ?> total seats
                                </div>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </form>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <?php if ($selected_schedule_id > 0 && !empty($seats)): ?>
            <div style="background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%); 
                 border-radius: 15px; padding: 30px; 
                 border: 1px solid rgba(226, 48, 32, 0.3);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;">
                    <div>
                        <h3 style="color: white; font-size: 1.4rem; font-weight: 700; display: flex; align-items: center; gap: 10px;">
                            <i class="fas fa-chair"></i> Select Your Seats
                        </h3>
                        <p style="color: var(--pale-red); font-size: 0.9rem; margin-top: 5px;">
                            Click on available seats to select them
                        </p>
                    </div>
                    <div style="color: white; font-weight: 700; font-size: 1.1rem; background: rgba(255,255,255,0.1); 
                         padding: 10px 20px; border-radius: 10px;">
                        <span id="selectedCount">0</span> seat(s) selected
                    </div>
                </div>
                
                <div style="display: flex; gap: 20px; margin-bottom: 30px; flex-wrap: wrap; padding: 15px; 
                     background: rgba(255,255,255,0.05); border-radius: 10px;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div style="width: 25px; height: 25px; background: #3498db; border-radius: 5px; border: 2px solid rgba(255,255,255,0.3);"></div>
                        <span style="color: rgba(255,255,255,0.9); font-size: 0.9rem; font-weight: 600;">Standard (₱<?php echo number_format($seat_prices['Standard'], 2); ?>)</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div style="width: 25px; height: 25px; background: #FFD700; border-radius: 5px; border: 2px solid rgba(255,255,255,0.3);"></div>
                        <span style="color: rgba(255,255,255,0.9); font-size: 0.9rem; font-weight: 600;">Premium (₱<?php echo number_format($seat_prices['Premium'], 2); ?>)</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div style="width: 25px; height: 25px; background: #e74c3c; border-radius: 5px; border: 2px solid rgba(255,255,255,0.3);"></div>
                        <span style="color: rgba(255,255,255,0.9); font-size: 0.9rem; font-weight: 600;">Sweet Spot (₱<?php echo number_format($seat_prices['Sweet Spot'], 2); ?>)</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div style="width: 25px; height: 25px; background: #28a745; border-radius: 5px; border: 2px solid rgba(255,255,255,0.3);"></div>
                        <span style="color: rgba(255,255,255,0.9); font-size: 0.9rem; font-weight: 600;">Selected</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div style="width: 25px; height: 25px; background: #6c757d; border-radius: 5px; border: 2px solid rgba(255,255,255,0.3);"></div>
                        <span style="color: rgba(255,255,255,0.9); font-size: 0.9rem; font-weight: 600;">Booked</span>
                    </div>
                </div>
                
                <?php if ($available_seats == 0): ?>
                <div style="text-align: center; padding: 40px; color: var(--pale-red);">
                    <i class="fas fa-times-circle fa-3x" style="margin-bottom: 20px; color: #e74c3c;"></i>
                    <h4 style="color: white; margin-bottom: 10px; font-size: 1.3rem;">No Seats Available</h4>
                    <p>This showtime is fully booked. Please select another showtime.</p>
                </div>
                <?php else: ?>
                <div style="background: linear-gradient(to bottom, rgba(52, 152, 219, 0.3), rgba(41, 128, 185, 0.2)); 
                     padding: 20px; border-radius: 12px; margin-bottom: 40px; text-align: center;
                     box-shadow: 0 5px 15px rgba(0,0,0,0.3); position: relative;">
                    <div style="color: white; font-weight: 800; font-size: 1.3rem; margin-bottom: 8px; letter-spacing: 2px;">
                        <i class="fas fa-tv"></i> SCREEN
                    </div>
                    <div style="height: 8px; background: linear-gradient(to right, #3498db, #2ecc71, #3498db); 
                         border-radius: 4px; margin: 0 auto; width: 85%;"></div>
                    <div style="position: absolute; bottom: -10px; left: 50%; transform: translateX(-50%);
                         color: rgba(255,255,255,0.6); font-size: 0.8rem; font-weight: 600;">
                        All eyes here
                    </div>
                </div>
                
                <form method="POST" action="" id="bookingForm">
                    <input type="hidden" name="movie" value="<?php echo $movie_id; ?>">
                    <input type="hidden" name="schedule_id" value="<?php echo $selected_schedule_id; ?>">
                    
                    <div style="margin-bottom: 40px;">
                        <?php 
                        $rows = [];
                        $premium_rows = ['A', 'B', 'C'];
                        
                        foreach ($seats as $seat) {
                            $seat_number = $seat['seat_number'];
                            $row = $seat_number[0];
                            $col = intval(substr($seat_number, 1));
                            $rows[$row][$col] = $seat;
                        }
                        
                        ksort($rows);
                        
                        foreach ($rows as $row_letter => $row_seats): 
                            $is_premium = in_array($row_letter, $premium_rows);
                        ?>
                        <div style="margin-bottom: 25px;">
                            <div style="display: flex; align-items: center; gap: 20px; margin-bottom: 15px;">
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <div style="color: <?php echo $is_premium ? '#FFD700' : 'white'; ?>; 
                                         font-weight: 700; font-size: 1.1rem; width: 40px; text-align: center;">
                                        <?php echo $row_letter; ?>
                                    </div>
                                    <?php if ($is_premium): ?>
                                    <span style="background: rgba(255, 215, 0, 0.2); color: #FFD700; padding: 3px 10px; 
                                          border-radius: 12px; font-size: 0.7rem; font-weight: 600;">PREMIUM</span>
                                    <?php endif; ?>
                                </div>
                                <div style="height: 2px; background: rgba(255,255,255,0.1); flex: 1;"></div>
                            </div>
                            
                            <div style="display: flex; justify-content: center; gap: 12px; flex-wrap: wrap;">
                                <?php 
                                ksort($row_seats);
                                $col_count = 0;
                                
                                foreach ($row_seats as $col => $seat): 
                                    $seat_number = $seat['seat_number'];
                                    $is_available = $seat['is_available'] == 1;
                                    $is_selected = in_array($seat_number, $selected_seats);
                                    $seat_type = $seat['seat_type'];
                                    $price = $seat['price'];
                                    
                                    if ($is_selected) {
                                        $seat_bg = '#28a745';
                                        $seat_color = 'white';
                                    } elseif (!$is_available) {
                                        $seat_bg = '#6c757d';
                                        $seat_color = 'rgba(255,255,255,0.5)';
                                    } elseif ($seat_type === 'Premium') {
                                        $seat_bg = '#FFD700';
                                        $seat_color = '#333';
                                    } elseif ($seat_type === 'Sweet Spot') {
                                        $seat_bg = '#e74c3c';
                                        $seat_color = 'white';
                                    } else {
                                        $seat_bg = '#3498db';
                                        $seat_color = 'white';
                                    }
                                    
                                    $seat_cursor = $is_available ? 'pointer' : 'not-allowed';
                                    $seat_opacity = $is_available ? '1' : '0.6';
                                ?>
                                <div style="position: relative;">
                                    <div style="color: rgba(255,255,255,0.5); font-size: 0.7rem; text-align: center; 
                                         margin-bottom: 5px; font-weight: 600; min-width: 40px;">
                                        <?php echo $col; ?>
                                    </div>
                                    
                                    <label style="cursor: <?php echo $seat_cursor; ?>; opacity: <?php echo $seat_opacity; ?>; display: block;">
                                        <input type="checkbox" name="selected_seats[]" value="<?php echo $seat_number; ?>" 
                                               <?php echo $is_selected ? 'checked' : ''; ?> 
                                               <?php echo !$is_available ? 'disabled' : ''; ?>
                                               class="seat-checkbox" style="display: none;"
                                               data-seat-type="<?php echo strtolower($seat_type); ?>"
                                               data-price="<?php echo $price; ?>">
                                        <div style="width: 40px; height: 45px; background: <?php echo $seat_bg; ?>; 
                                             border-radius: 8px 8px 4px 4px; display: flex; flex-direction: column; 
                                             align-items: center; justify-content: center; color: <?php echo $seat_color; ?>; 
                                             font-weight: 700; font-size: 0.9rem; transition: all 0.3s ease;
                                             box-shadow: 0 3px 6px rgba(0,0,0,0.2); position: relative;">
                                            <div style="font-size: 0.8rem;"><?php echo $row_letter; ?></div>
                                            <div style="font-size: 1rem;"><?php echo str_pad($col, 2, '0', STR_PAD_LEFT); ?></div>
                                            <div style="position: absolute; bottom: -8px; font-size: 0.6rem; color: rgba(255,255,255,0.8);">
                                                ₱<?php echo number_format($price, 0); ?>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                                
                                <?php 
                                $col_count++;
                                if ($col_count % 4 == 0 && $col_count < count($row_seats)): 
                                ?>
                                <div style="width: 40px; display: flex; align-items: center; justify-content: center;">
                                    <div style="color: rgba(255,255,255,0.3); font-size: 0.8rem; font-weight: 600;">AISLE</div>
                                </div>
                                <?php endif; ?>
                                
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div style="text-align: center; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.1);">
                        <button type="button" onclick="clearSelection()" class="btn btn-secondary" style="margin-right: 15px;">
                            <i class="fas fa-undo"></i> Clear Selection
                        </button>
                        <button type="submit" name="confirm_booking" class="btn btn-primary" style="padding: 15px 50px; font-size: 1.1rem;">
                            <i class="fas fa-ticket-alt"></i> Confirm Booking
                        </button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <div>
            <div style="position: sticky; top: 100px;">
                <div style="background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%); 
                     border-radius: 15px; padding: 25px; margin-bottom: 20px; 
                     border: 1px solid rgba(226, 48, 32, 0.3); box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
                    <h3 style="color: white; font-size: 1.4rem; margin-bottom: 25px; font-weight: 700; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-receipt"></i> Booking Summary
                    </h3>
                    
                    <?php if ($movie): ?>
                    <div style="margin-bottom: 20px;">
                        <div style="color: var(--pale-red); font-size: 0.9rem; margin-bottom: 5px; font-weight: 600;">Movie</div>
                        <div style="color: white; font-weight: 700; font-size: 1.1rem; line-height: 1.4;">
                            <?php echo htmlspecialchars($movie['title']); ?>
                        </div>
                    </div>
                    
                    <?php if ($selected_schedule_data): ?>
                    <div style="margin-bottom: 20px;">
                        <div style="color: var(--pale-red); font-size: 0.9rem; margin-bottom: 5px; font-weight: 600;">Showtime</div>
                        <div style="color: white; font-weight: 700; font-size: 1.1rem;">
                            <?php echo date('h:i A', strtotime($selected_schedule_data['showtime'])); ?>
                        </div>
                        <div style="color: var(--pale-red); font-size: 0.9rem; margin-top: 3px;">
                            <?php echo date('D, M d, Y', strtotime($selected_schedule_data['show_date'])); ?>
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <div style="color: var(--pale-red); font-size: 0.9rem; margin-bottom: 5px; font-weight: 600;">Selected Seats</div>
                        <div id="summarySeats" style="color: white; font-weight: 700; min-height: 25px; font-size: 1.1rem;">
                            <?php if (!empty($selected_seats)): ?>
                            <div style="display: flex; flex-wrap: wrap; gap: 5px;">
                                <?php foreach ($selected_seats as $seat): ?>
                                <span style="background: rgba(40, 167, 69, 0.2); padding: 5px 10px; border-radius: 6px; color: #28a745;">
                                    <?php echo $seat; ?>
                                </span>
                                <?php endforeach; ?>
                            </div>
                            <?php else: ?>
                            <span style="color: var(--pale-red); font-style: italic;">Not selected</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div style="border-top: 1px solid rgba(226, 48, 32, 0.3); padding-top: 20px;">
                        <div style="margin-bottom: 15px;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                <div style="color: var(--pale-red); font-size: 0.95rem;">Standard Seats</div>
                                <div style="color: white; font-weight: 600;" id="summaryStandardCount">0</div>
                            </div>
                            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                <div style="color: var(--pale-red); font-size: 0.95rem;">Premium Seats</div>
                                <div style="color: white; font-weight: 600;" id="summaryPremiumCount">0</div>
                            </div>
                            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                <div style="color: var(--pale-red); font-size: 0.95rem;">Sweet Spot Seats</div>
                                <div style="color: white; font-weight: 600;" id="summarySweetSpotCount">0</div>
                            </div>
                        </div>
                        
                        <div style="border-top: 2px solid rgba(226, 48, 32, 0.5); padding-top: 15px;">
                            <div style="display: flex; justify-content: space-between; font-size: 1.1rem;">
                                <div style="color: white; font-weight: 800;">Total Amount</div>
                                <div style="color: var(--primary-red); font-weight: 900; font-size: 1.4rem;">
                                    ₱<span id="summaryTotal">0</span>.00
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div style="text-align: center; padding: 30px; color: var(--pale-red);">
                        <i class="fas fa-clock fa-2x" style="margin-bottom: 15px; opacity: 0.5;"></i>
                        <p>Select a showtime to see booking details</p>
                    </div>
                    <?php endif; ?>
                    <?php else: ?>
                    <div style="text-align: center; padding: 30px; color: var(--pale-red);">
                        <i class="fas fa-film fa-2x" style="margin-bottom: 15px; opacity: 0.5;"></i>
                        <p>Select a movie to begin booking</p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div style="background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%); 
                     border-radius: 15px; padding: 20px; 
                     border: 1px solid rgba(226, 48, 32, 0.3);">
                    <h4 style="color: white; font-size: 1.1rem; margin-bottom: 15px; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-user-circle"></i> Booking For
                    </h4>
                    <div style="color: white; font-weight: 700; margin-bottom: 5px; font-size: 1.1rem;">
                        <?php echo $_SESSION['user_name']; ?>
                    </div>
                    <div style="color: var(--pale-red); font-size: 0.9rem; margin-bottom: 5px;">
                        <?php echo $_SESSION['user_email']; ?>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px; margin-top: 10px;">
                        <i class="fas fa-shield-alt" style="color: #2ecc71;"></i>
                        <span style="color: rgba(255,255,255,0.7); font-size: 0.85rem;">Secure Booking</span>
                    </div>
                </div>
            </div>
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

.btn-primary:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none !important;
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

#movieSelect:hover {
    background: rgba(255, 255, 255, 0.2);
    border-color: var(--primary-red);
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(226, 48, 32, 0.2);
}

#movieSelect:focus {
    outline: none;
    background: rgba(255, 255, 255, 0.2);
    border-color: var(--primary-red);
    box-shadow: 0 0 0 3px rgba(226, 48, 32, 0.3);
}

#movieSelect option {
    padding: 12px;
    font-size: 1rem;
    background: var(--bg-card);
    color: white;
}

#movieSelect option:hover {
    background: rgba(226, 48, 32, 0.3);
}

@media (max-width: 1200px) {
    .main-container > div {
        grid-template-columns: 1fr;
    }
    
    .main-container > div > div:last-child {
        position: static;
    }
}

@media (max-width: 768px) {
    .main-container {
        padding: 15px;
    }
    
    .schedule-grid {
        grid-template-columns: 1fr;
    }
    
    .page-header > div {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .movie-select-form {
        width: 100%;
    }
}

@media (max-width: 576px) {
    .seat-container {
        transform: scale(0.9);
        transform-origin: top left;
    }
    
    .btn {
        padding: 10px 20px;
        font-size: 0.9rem;
    }
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

@keyframes bounce {
    0%, 20%, 50%, 80%, 100% {transform: translateY(0);}
    40% {transform: translateY(-10px);}
    60% {transform: translateY(-5px);}
}

.alert {
    animation: fadeIn 0.5s ease;
}

.pulse {
    animation: pulse 0.5s ease;
}

.seat-standard:hover {
    transform: translateY(-5px) !important;
    box-shadow: 0 8px 20px rgba(52, 152, 219, 0.4) !important;
}

.seat-premium:hover {
    transform: translateY(-5px) !important;
    box-shadow: 0 8px 20px rgba(255, 215, 0, 0.4) !important;
}

.seat-sweet-spot:hover {
    transform: translateY(-5px) !important;
    box-shadow: 0 8px 20px rgba(231, 76, 60, 0.4) !important;
}

.seat-selected {
    animation: bounce 0.5s ease;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const seatCheckboxes = document.querySelectorAll('.seat-checkbox');
    const selectedCount = document.getElementById('selectedCount');
    const summarySeats = document.getElementById('summarySeats');
    const summaryStandardCount = document.getElementById('summaryStandardCount');
    const summaryPremiumCount = document.getElementById('summaryPremiumCount');
    const summarySweetSpotCount = document.getElementById('summarySweetSpotCount');
    const summaryTotal = document.getElementById('summaryTotal');
    
    const prices = {
        'standard': <?php echo $seat_prices['Standard'] ?? 350; ?>,
        'premium': <?php echo $seat_prices['Premium'] ?? 450; ?>,
        'sweet spot': <?php echo $seat_prices['Sweet Spot'] ?? 550; ?>
    };
    
    function updateBookingSummary() {
        const selectedSeats = Array.from(seatCheckboxes)
            .filter(cb => cb.checked)
            .map(cb => ({
                number: cb.value,
                type: cb.dataset.seatType,
                price: parseFloat(cb.dataset.price)
            }));
        
        const counts = {
            'standard': 0,
            'premium': 0,
            'sweet spot': 0
        };
        
        let total = 0;
        
        selectedSeats.forEach(seat => {
            counts[seat.type]++;
            total += seat.price;
        });
        
        selectedCount.textContent = selectedSeats.length;
        summaryStandardCount.textContent = counts.standard;
        summaryPremiumCount.textContent = counts.premium;
        summarySweetSpotCount.textContent = counts['sweet spot'];
        summaryTotal.textContent = total.toFixed(2);
        
        if (selectedSeats.length > 0) {
            const seatNumbers = selectedSeats.map(seat => seat.number);
            summarySeats.innerHTML = '<div style="display: flex; flex-wrap: wrap; gap: 5px;">' +
                seatNumbers.map(seat => 
                    `<span style="background: rgba(40, 167, 69, 0.2); padding: 5px 10px; border-radius: 6px; color: #28a745;">${seat}</span>`
                ).join('') + '</div>';
        } else {
            summarySeats.innerHTML = '<span style="color: var(--pale-red); font-style: italic;">Not selected</span>';
        }
    }
    
    seatCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const seatDiv = this.parentElement.querySelector('div');
            
            if (this.checked) {
                seatDiv.style.background = '#28a745';
                seatDiv.style.color = 'white';
                seatDiv.classList.add('seat-selected');
                
                setTimeout(() => {
                    seatDiv.classList.remove('seat-selected');
                }, 500);
            } else {
                const seatType = this.dataset.seatType;
                let bgColor;
                
                if (seatType === 'premium') {
                    bgColor = '#FFD700';
                } else if (seatType === 'sweet spot') {
                    bgColor = '#e74c3c';
                } else {
                    bgColor = '#3498db';
                }
                
                seatDiv.style.background = bgColor;
                if (seatType === 'premium') {
                    seatDiv.style.color = '#333';
                } else {
                    seatDiv.style.color = 'white';
                }
                seatDiv.style.transform = 'none';
            }
            
            updateBookingSummary();
        });
    });
    
    const scheduleRadios = document.querySelectorAll('.schedule-radio');
    scheduleRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            document.querySelectorAll('.schedule-radio').forEach(r => {
                const card = r.parentElement.querySelector('div');
                if (r.checked) {
                    card.style.background = 'rgba(226, 48, 32, 0.2)';
                    card.style.borderColor = 'var(--primary-red)';
                    card.style.boxShadow = '0 5px 15px rgba(226, 48, 32, 0.2)';
                } else {
                    card.style.background = 'rgba(255, 255, 255, 0.05)';
                    card.style.borderColor = 'rgba(226, 48, 32, 0.3)';
                    card.style.boxShadow = 'none';
                }
            });
        });
        
        if (radio.checked) {
            const card = radio.parentElement.querySelector('div');
            card.style.background = 'rgba(226, 48, 32, 0.2)';
            card.style.borderColor = 'var(--primary-red)';
            card.style.boxShadow = '0 5px 15px rgba(226, 48, 32, 0.2)';
        }
    });
    
    const bookingForm = document.getElementById('bookingForm');
    if (bookingForm) {
        bookingForm.addEventListener('submit', function(e) {
            const selectedSeats = Array.from(seatCheckboxes)
                .filter(cb => cb.checked)
                .map(cb => cb.value);
            
            if (selectedSeats.length === 0) {
                e.preventDefault();
                showAlert('Please select at least one seat!', 'error');
                return false;
            }
            
            return true;
        });
    }
    
    window.clearSelection = function() {
        const selectedSeats = Array.from(seatCheckboxes).filter(cb => cb.checked);
        
        if (selectedSeats.length === 0) {
            showAlert('No seats selected to clear!', 'info');
            return;
        }
        
        if (confirm(`Clear ${selectedSeats.length} selected seat(s)?`)) {
            seatCheckboxes.forEach(cb => {
                if (cb.checked) {
                    cb.checked = false;
                    cb.dispatchEvent(new Event('change'));
                }
            });
            showAlert('Selection cleared successfully!', 'success');
        }
    };
    
    window.hoverSeat = function(element, isHover) {
        const checkbox = element.parentElement.querySelector('.seat-checkbox');
        
        if (checkbox.disabled || checkbox.checked) {
            return;
        }
        
        if (isHover) {
            element.style.transform = 'translateY(-3px)';
            element.style.boxShadow = '0 6px 15px rgba(0,0,0,0.3)';
        } else {
            element.style.transform = 'none';
            element.style.boxShadow = '0 3px 6px rgba(0,0,0,0.2)';
        }
    };
    
    updateBookingSummary();
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            clearSelection();
        }
        
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            if (bookingForm) {
                bookingForm.submit();
            }
        }
    });
    
    document.getElementById('scheduleForm')?.addEventListener('change', function(e) {
        if (e.target.classList.contains('schedule-radio')) {
            const submitBtn = document.createElement('button');
            submitBtn.type = 'submit';
            submitBtn.name = 'load_seats';
            submitBtn.style.display = 'none';
            this.appendChild(submitBtn);
            submitBtn.click();
        }
    });
    
    const movieSelect = document.getElementById('movieSelect');
    if (movieSelect) {
        movieSelect.addEventListener('change', function() {
            const form = document.getElementById('movieSelectForm');
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
            submitBtn.disabled = true;
            
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 1000);
        });
    }
    
    function showAlert(message, type) {
        const existingAlerts = document.querySelectorAll('.custom-alert');
        existingAlerts.forEach(alert => alert.remove());
        
        const alertDiv = document.createElement('div');
        alertDiv.className = 'alert custom-alert';
        alertDiv.style.cssText = `
            position: fixed;
            top: 100px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
            padding: 15px 20px;
            border-radius: 10px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: fadeIn 0.3s ease;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        `;
        
        switch(type) {
            case 'error':
                alertDiv.style.background = 'rgba(226, 48, 32, 0.9)';
                alertDiv.style.color = 'white';
                alertDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${message}`;
                break;
            case 'success':
                alertDiv.style.background = 'rgba(46, 204, 113, 0.9)';
                alertDiv.style.color = 'white';
                alertDiv.innerHTML = `<i class="fas fa-check-circle"></i> ${message}`;
                break;
            case 'info':
                alertDiv.style.background = 'rgba(52, 152, 219, 0.9)';
                alertDiv.style.color = 'white';
                alertDiv.innerHTML = `<i class="fas fa-info-circle"></i> ${message}`;
                break;
            default:
                alertDiv.style.background = 'rgba(255, 193, 7, 0.9)';
                alertDiv.style.color = '#333';
                alertDiv.innerHTML = `<i class="fas fa-exclamation-triangle"></i> ${message}`;
        }
        
        document.body.appendChild(alertDiv);
        
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.style.opacity = '0';
                alertDiv.style.transform = 'translateX(100px)';
                setTimeout(() => {
                    if (alertDiv.parentNode) {
                        alertDiv.parentNode.removeChild(alertDiv);
                    }
                }, 300);
            }
        }, 5000);
    }
    
    const animatedElements = document.querySelectorAll('.schedule-radio + div, .movie-details-card, .seat-layout');
    animatedElements.forEach((el, index) => {
        el.style.animation = `fadeIn 0.5s ease ${index * 0.1}s forwards`;
        el.style.opacity = '0';
    });
});

function adjustSeatLayout() {
    const container = document.querySelector('.seat-layout-container');
    if (!container) return;
    
    const viewportWidth = window.innerWidth;
    let scale = 1;
    
    if (viewportWidth < 768) {
        scale = 0.85;
    } else if (viewportWidth < 992) {
        scale = 0.9;
    } else if (viewportWidth > 1400) {
        scale = 1.05;
    }
    
    container.style.transform = `scale(${scale})`;
    container.style.transformOrigin = 'top center';
}

window.addEventListener('load', adjustSeatLayout);
window.addEventListener('resize', adjustSeatLayout);
</script>

<?php
require_once $root_dir . '/partials/footer.php';
?>