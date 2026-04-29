<?php
$root_dir = dirname(dirname(__DIR__));
require_once $root_dir . '/includes/config.php';
require_once $root_dir . '/includes/functions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || ($_SESSION['user_role'] !== 'Admin' && $_SESSION['user_role'] !== 'Owner')) {
    header("Location: " . SITE_URL . "index.php?page=login");
    exit();
}

require_once $root_dir . '/partials/admin-header.php';

// Open database connection
$conn = get_db_connection();

$error = '';
$success = '';
$edit_mode = false;
$edit_schedule = null;

// ============================================
// ADD SCHEDULE
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_schedule'])) {
    $movie_id = intval($_POST['movie_id']);
    $screen_id = intval($_POST['screen_id']);
    $seat_plan_id = intval($_POST['seat_plan_id']);
    $show_date = htmlspecialchars(trim($_POST['show_date']));
    $showtime = htmlspecialchars(trim($_POST['showtime']));
    $base_price = floatval($_POST['base_price']);
    
    if ($movie_id <= 0 || $screen_id <= 0 || $seat_plan_id <= 0) {
        $error = "Please select a movie, screen, and seat plan!";
    } elseif (empty($show_date)) {
        $error = "Please select a show date!";
    } elseif (empty($showtime)) {
        $error = "Please select a show time!";
    } elseif ($base_price <= 0) {
        $error = "Please enter a valid ticket price!";
    } else {
        $check_stmt = $conn->prepare("SELECT id FROM schedules WHERE screen_id = ? AND show_date = ? AND showtime = ?");
        $check_stmt->bind_param("iss", $screen_id, $show_date, $showtime);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error = "Schedule already exists for this screen at the selected date and time!";
        } else {
            $conn->begin_transaction();
            
            try {
                $stmt = $conn->prepare("
                    INSERT INTO schedules (movie_id, screen_id, seat_plan_id, show_date, showtime, base_price, is_active) 
                    VALUES (?, ?, ?, ?, ?, ?, 1)
                ");
                $stmt->bind_param("iiissd", $movie_id, $screen_id, $seat_plan_id, $show_date, $showtime, $base_price);
                
                if (!$stmt->execute()) {
                    throw new Exception("Failed to add schedule: " . $stmt->error);
                }
                
                $schedule_id = $stmt->insert_id;
                $stmt->close();
                
                $create_seats_stmt = $conn->prepare("
                    INSERT INTO seat_availability (schedule_id, seat_plan_detail_id, seat_number, seat_type_id, price, status)
                    SELECT 
                        ?, 
                        spd.id, 
                        spd.seat_number, 
                        spd.seat_type_id,
                        COALESCE(msp.price, spd.custom_price, st.default_price) as price,
                        'available'
                    FROM seat_plan_details spd
                    JOIN seat_types st ON spd.seat_type_id = st.id
                    LEFT JOIN movie_screen_prices msp ON ? = msp.movie_id 
                        AND ? = msp.screen_id 
                        AND spd.seat_type_id = msp.seat_type_id
                        AND msp.is_active = 1
                    WHERE spd.seat_plan_id = ? AND spd.is_enabled = 1
                ");
                $create_seats_stmt->bind_param("iiii", $schedule_id, $movie_id, $screen_id, $seat_plan_id);
                
                if (!$create_seats_stmt->execute()) {
                    throw new Exception("Failed to create seat availability: " . $create_seats_stmt->error);
                }
                
                $create_seats_stmt->close();
                $conn->commit();
                
                $success = "Schedule added successfully!";
                $_POST = array();
                
            } catch (Exception $e) {
                $conn->rollback();
                $error = $e->getMessage();
            }
        }
        $check_stmt->close();
    }
}

// ============================================
// UPDATE SCHEDULE
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_schedule'])) {
    $id = intval($_POST['id']);
    $movie_id = intval($_POST['movie_id']);
    $screen_id = intval($_POST['screen_id']);
    $seat_plan_id = intval($_POST['seat_plan_id']);
    $show_date = htmlspecialchars(trim($_POST['show_date']));
    $showtime = htmlspecialchars(trim($_POST['showtime']));
    $base_price = floatval($_POST['base_price']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    $check_stmt = $conn->prepare("SELECT id FROM schedules WHERE screen_id = ? AND show_date = ? AND showtime = ? AND id != ?");
    $check_stmt->bind_param("issi", $screen_id, $show_date, $showtime, $id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $error = "Another schedule already exists for this screen at the selected date and time!";
    } else {
        $stmt = $conn->prepare("
            UPDATE schedules 
            SET movie_id = ?, screen_id = ?, seat_plan_id = ?, show_date = ?, showtime = ?, base_price = ?, is_active = ?
            WHERE id = ?
        ");
        $stmt->bind_param("iiissdii", $movie_id, $screen_id, $seat_plan_id, $show_date, $showtime, $base_price, $is_active, $id);
        
        if ($stmt->execute()) {
            $success = "Schedule updated successfully!";
        } else {
            $error = "Failed to update schedule: " . $conn->error;
        }
        $stmt->close();
    }
    $check_stmt->close();
}

// ============================================
// DELETE SCHEDULE
// ============================================
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM bookings WHERE schedule_id = ?");
    $check_stmt->bind_param("i", $id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $booking_count = $result->fetch_assoc()['count'];
    $check_stmt->close();
    
    if ($booking_count > 0) {
        $error = "Cannot delete schedule with $booking_count active booking(s).";
    } else {
        $conn->begin_transaction();
        
        try {
            $delete_seats = $conn->prepare("DELETE FROM seat_availability WHERE schedule_id = ?");
            $delete_seats->bind_param("i", $id);
            
            if (!$delete_seats->execute()) {
                throw new Exception("Failed to delete seat availability");
            }
            $delete_seats->close();
            
            $stmt = $conn->prepare("UPDATE schedules SET is_active = 0 WHERE id = ?");
            $stmt->bind_param("i", $id);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to delete schedule");
            }
            $stmt->close();
            
            $conn->commit();
            $success = "Schedule deleted successfully!";
            
        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
    }
}

// ============================================
// GET SCHEDULE FOR EDITING
// ============================================
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $conn->prepare("
        SELECT s.*, 
               m.title as movie_title,
               m.rating,
               m.duration,
               sc.screen_name,
               sc.screen_number,
               sc.capacity,
               v.venue_name,
               v.id as venue_id,
               sp.plan_name as seat_plan_name,
               sp.total_seats
        FROM schedules s
        JOIN movies m ON s.movie_id = m.id
        JOIN screens sc ON s.screen_id = sc.id
        JOIN venues v ON sc.venue_id = v.id
        JOIN seat_plans sp ON s.seat_plan_id = sp.id
        WHERE s.id = ?
    ");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_schedule = $result->fetch_assoc();
    $edit_mode = !empty($edit_schedule);
    $stmt->close();
}

// ============================================
// FETCH DATA FOR DISPLAY
// ============================================

// Get all active movies
$movies = [];
$movies_result = $conn->query("
    SELECT id, title, rating, duration, genre 
    FROM movies 
    WHERE is_active = 1 
    ORDER BY title
");
if ($movies_result) {
    while ($row = $movies_result->fetch_assoc()) {
        $movies[] = $row;
    }
}

// Get all screens with venue info
$screens = [];
$screens_result = $conn->query("
    SELECT 
        s.id as screen_id,
        s.screen_name,
        s.screen_number,
        s.capacity,
        v.id as venue_id,
        v.venue_name,
        CONCAT(v.venue_name, ' - ', s.screen_name, ' (Screen ', s.screen_number, ')') as display_name
    FROM screens s
    JOIN venues v ON s.venue_id = v.id
    WHERE s.is_active = 1 AND v.is_active = 1
    ORDER BY v.venue_name, s.screen_number
");
if ($screens_result) {
    while ($row = $screens_result->fetch_assoc()) {
        $screens[] = $row;
    }
}

// Get all seat plans with screen info
$seat_plans = [];
$seat_plans_result = $conn->query("
    SELECT 
        sp.id as plan_id,
        sp.plan_name,
        sp.total_rows,
        sp.total_columns,
        sp.total_seats,
        s.id as screen_id,
        s.screen_name,
        v.venue_name,
        CONCAT(v.venue_name, ' - ', s.screen_name, ' (', sp.plan_name, ')') as display_name
    FROM seat_plans sp
    JOIN screens s ON sp.screen_id = s.id
    JOIN venues v ON s.venue_id = v.id
    WHERE sp.is_active = 1 AND s.is_active = 1
    ORDER BY v.venue_name, s.screen_number, sp.plan_name
");
if ($seat_plans_result) {
    while ($row = $seat_plans_result->fetch_assoc()) {
        $seat_plans[] = $row;
    }
}

// Get all schedules with details
$schedules = [];
$schedules_result = $conn->query("
    SELECT 
        sch.id,
        sch.show_date,
        sch.showtime,
        sch.base_price,
        sch.is_active,
        sch.created_at,
        m.id as movie_id,
        m.title as movie_title,
        m.rating,
        m.duration,
        s.id as screen_id,
        s.screen_name,
        s.screen_number,
        v.id as venue_id,
        v.venue_name,
        sp.id as plan_id,
        sp.plan_name,
        COUNT(sa.id) as total_seats,
        COUNT(CASE WHEN sa.status = 'available' THEN 1 END) as available_seats,
        COUNT(CASE WHEN sa.status = 'booked' THEN 1 END) as booked_seats,
        COUNT(CASE WHEN sa.status = 'reserved' THEN 1 END) as reserved_seats,
        (SELECT COUNT(*) FROM bookings WHERE schedule_id = sch.id) as booking_count
    FROM schedules sch
    JOIN movies m ON sch.movie_id = m.id
    JOIN screens s ON sch.screen_id = s.id
    JOIN venues v ON s.venue_id = v.id
    JOIN seat_plans sp ON sch.seat_plan_id = sp.id
    LEFT JOIN seat_availability sa ON sch.id = sa.schedule_id
    WHERE sch.is_active = 1
    GROUP BY sch.id, sch.show_date, sch.showtime, sch.base_price, sch.is_active, sch.created_at,
             m.id, m.title, m.rating, m.duration,
             s.id, s.screen_name, s.screen_number,
             v.id, v.venue_name,
             sp.id, sp.plan_name
    ORDER BY sch.show_date ASC, sch.showtime ASC
");
if ($schedules_result) {
    while ($row = $schedules_result->fetch_assoc()) {
        $schedules[] = $row;
    }
}

$count_result = $conn->query("SELECT COUNT(*) as total FROM schedules WHERE is_active = 1 AND show_date >= CURDATE()");
$schedule_count = $count_result ? $count_result->fetch_assoc()['total'] : 0;

// DO NOT CLOSE CONNECTION HERE - will close after footer
?>

<div class="admin-content" style="max-width: 1400px; margin: 0 auto; padding: 30px;">
    <div style="text-align: center; margin-bottom: 40px; padding: 30px; background: linear-gradient(135deg, rgba(52, 152, 219, 0.1), rgba(41, 128, 185, 0.2)); border-radius: 20px; border: 2px solid rgba(52, 152, 219, 0.3);">
        <h1 style="color: white; font-size: 2.5rem; margin-bottom: 15px; font-weight: 800;">Manage Schedules</h1>
        <p style="color: rgba(255, 255, 255, 0.8); font-size: 1.1rem;">Add, edit, or remove movie showtimes</p>
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

    <!-- Add/Edit Schedule Form -->
    <div style="background: rgba(255, 255, 255, 0.05); border-radius: 15px; padding: 30px; margin-bottom: 40px; border: 1px solid rgba(52, 152, 219, 0.2);">
        <h2 style="color: white; font-size: 1.8rem; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid #3498db; display: flex; align-items: center; gap: 10px;">
            <i class="<?php echo $edit_mode ? 'fas fa-edit' : 'fas fa-plus-circle'; ?>"></i>
            <?php echo $edit_mode ? 'Edit Schedule' : 'Add New Schedule'; ?>
        </h2>
        
        <?php if ($edit_mode): ?>
        <div style="background: rgba(23, 162, 184, 0.2); color: #17a2b8; padding: 15px 20px; border-radius: 10px; margin-bottom: 25px; font-weight: 600; border: 1px solid rgba(23, 162, 184, 0.3);">
            <i class="fas fa-info-circle"></i> 
            Editing schedule for: <strong><?php echo htmlspecialchars($edit_schedule['movie_title']); ?></strong> 
            at <strong><?php echo htmlspecialchars($edit_schedule['venue_name']); ?> - <?php echo htmlspecialchars($edit_schedule['screen_name']); ?></strong>
        </div>
        <?php endif; ?>
        
        <form method="POST" action="" id="scheduleForm">
            <?php if ($edit_mode): ?>
            <input type="hidden" name="id" value="<?php echo $edit_schedule['id']; ?>">
            <?php endif; ?>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 25px; margin-bottom: 30px;">
                <div>
                    <label style="display: block; color: white; font-weight: 600; margin-bottom: 10px; font-size: 1rem;">
                        <i class="fas fa-film"></i> Select Movie *
                    </label>
                    <select id="movie_id" name="movie_id" required style="width: 100%; padding: 14px 16px; background: rgba(255, 255, 255, 0.08); border: 2px solid rgba(52, 152, 219, 0.3); border-radius: 10px; color: white; font-size: 1rem; cursor: pointer;">
                        <option value="" style="background: #2c3e50;">-- Select Movie --</option>
                        <?php foreach ($movies as $movie): ?>
                        <option value="<?php echo $movie['id']; ?>" 
                                <?php echo ($edit_mode && $edit_schedule['movie_id'] == $movie['id']) ? 'selected' : ''; ?>
                                style="background: #2c3e50; color: white;">
                            <?php echo htmlspecialchars($movie['title']); ?> (<?php echo $movie['rating']; ?> • <?php echo $movie['duration']; ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label style="display: block; color: white; font-weight: 600; margin-bottom: 10px; font-size: 1rem;">
                        <i class="fas fa-tv"></i> Select Screen *
                    </label>
                    <select id="screen_id" name="screen_id" required style="width: 100%; padding: 14px 16px; background: rgba(255, 255, 255, 0.08); border: 2px solid rgba(52, 152, 219, 0.3); border-radius: 10px; color: white; font-size: 1rem; cursor: pointer;">
                        <option value="" style="background: #2c3e50;">-- Select Screen --</option>
                        <?php foreach ($screens as $screen): ?>
                        <option value="<?php echo $screen['screen_id']; ?>" 
                                <?php echo ($edit_mode && $edit_schedule['screen_id'] == $screen['screen_id']) ? 'selected' : ''; ?>
                                data-venue-id="<?php echo $screen['venue_id']; ?>"
                                style="background: #2c3e50; color: white;">
                            <?php echo htmlspecialchars($screen['display_name']); ?> (Capacity: <?php echo number_format($screen['capacity']); ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label style="display: block; color: white; font-weight: 600; margin-bottom: 10px; font-size: 1rem;">
                        <i class="fas fa-chair"></i> Select Seat Plan *
                    </label>
                    <select id="seat_plan_id" name="seat_plan_id" required style="width: 100%; padding: 14px 16px; background: rgba(255, 255, 255, 0.08); border: 2px solid rgba(52, 152, 219, 0.3); border-radius: 10px; color: white; font-size: 1rem; cursor: pointer;">
                        <option value="" style="background: #2c3e50;">-- Select Seat Plan --</option>
                        <?php foreach ($seat_plans as $plan): ?>
                        <option value="<?php echo $plan['plan_id']; ?>" 
                                <?php echo ($edit_mode && $edit_schedule['seat_plan_id'] == $plan['plan_id']) ? 'selected' : ''; ?>
                                data-screen-id="<?php echo $plan['screen_id']; ?>"
                                style="background: #2c3e50; color: white;">
                            <?php echo htmlspecialchars($plan['display_name']); ?> (<?php echo number_format($plan['total_seats']); ?> seats)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 25px; margin-bottom: 30px;">
                <div>
                    <label style="display: block; color: white; font-weight: 600; margin-bottom: 10px; font-size: 1rem;">
                        <i class="fas fa-calendar"></i> Show Date *
                    </label>
                    <input type="date" id="show_date" name="show_date" required 
                           value="<?php echo $edit_mode ? $edit_schedule['show_date'] : (isset($_POST['show_date']) ? $_POST['show_date'] : ''); ?>"
                           min="<?php echo date('Y-m-d'); ?>"
                           style="width: 100%; padding: 14px 16px; background: rgba(255, 255, 255, 0.08); border: 2px solid rgba(52, 152, 219, 0.3); border-radius: 10px; color: white; font-size: 1rem;">
                </div>
                
                <div>
                    <label style="display: block; color: white; font-weight: 600; margin-bottom: 10px; font-size: 1rem;">
                        <i class="fas fa-clock"></i> Show Time *
                    </label>
                    <input type="time" id="showtime" name="showtime" required
                           value="<?php echo $edit_mode ? $edit_schedule['showtime'] : (isset($_POST['showtime']) ? $_POST['showtime'] : ''); ?>"
                           min="09:00" max="23:00"
                           style="width: 100%; padding: 14px 16px; background: rgba(255, 255, 255, 0.08); border: 2px solid rgba(52, 152, 219, 0.3); border-radius: 10px; color: white; font-size: 1rem;">
                </div>
                
                <div>
                    <label style="display: block; color: white; font-weight: 600; margin-bottom: 10px; font-size: 1rem;">
                        <i class="fas fa-tag"></i> Base Ticket Price (₱) *
                    </label>
                    <input type="number" id="base_price" name="base_price" required step="0.01" min="0"
                           value="<?php echo $edit_mode ? $edit_schedule['base_price'] : (isset($_POST['base_price']) ? $_POST['base_price'] : '350'); ?>"
                           style="width: 100%; padding: 14px 16px; background: rgba(255, 255, 255, 0.08); border: 2px solid rgba(52, 152, 219, 0.3); border-radius: 10px; color: white; font-size: 1rem;">
                </div>
            </div>
            
            <div id="scheduleInfo" style="background: rgba(52, 152, 219, 0.1); border-radius: 10px; padding: 20px; margin-bottom: 25px; display: none;">
                <h3 style="color: white; font-size: 1.1rem; margin-bottom: 15px;">Schedule Information</h3>
                <div id="scheduleInfoContent" style="color: rgba(255,255,255,0.8);"></div>
            </div>
            
            <?php if ($edit_mode): ?>
            <div style="margin-bottom: 25px;">
                <label style="display: block; color: white; font-weight: 600; margin-bottom: 10px; font-size: 1rem;">
                    <i class="fas fa-toggle-on"></i> Status
                </label>
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                    <input type="checkbox" name="is_active" value="1" <?php echo $edit_schedule['is_active'] ? 'checked' : ''; ?>>
                    <span style="color: white;">Active (available for booking)</span>
                </label>
            </div>
            <?php endif; ?>
            
            <div style="text-align: center; margin-top: 30px;">
                <?php if ($edit_mode): ?>
                <button type="submit" name="update_schedule" style="padding: 16px 45px; background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; border: none; border-radius: 12px; font-size: 1.1rem; font-weight: 700; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 6px 20px rgba(52, 152, 219, 0.3); display: inline-flex; align-items: center; justify-content: center; gap: 10px;">
                    <i class="fas fa-save"></i> Update Schedule
                </button>
                <a href="index.php?page=admin/manage-schedules" style="padding: 16px 30px; background: rgba(255, 255, 255, 0.1); color: white; text-decoration: none; border-radius: 12px; font-size: 1.1rem; font-weight: 600; border: 2px solid rgba(52, 152, 219, 0.3); margin-left: 15px; display: inline-flex; align-items: center; justify-content: center; gap: 10px;">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <?php else: ?>
                <button type="submit" name="add_schedule" style="padding: 16px 45px; background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; border: none; border-radius: 12px; font-size: 1.1rem; font-weight: 700; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 6px 20px rgba(52, 152, 219, 0.3); display: inline-flex; align-items: center; justify-content; center; gap: 10px;">
                    <i class="fas fa-plus"></i> Add Schedule
                </button>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Schedules List -->
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
                    <tr style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);">
                        <th style="color: white; padding: 16px; text-align: left; font-weight: 700; font-size: 1rem;">ID</th>
                        <th style="color: white; padding: 16px; text-align: left; font-weight: 700; font-size: 1rem;">Movie</th>
                        <th style="color: white; padding: 16px; text-align: left; font-weight: 700; font-size: 1rem;">Venue</th>
                        <th style="color: white; padding: 16px; text-align: left; font-weight: 700; font-size: 1rem;">Screen</th>
                        <th style="color: white; padding: 16px; text-align: left; font-weight: 700; font-size: 1rem;">Show Time</th>
                        <th style="color: white; padding: 16px; text-align: left; font-weight: 700; font-size: 1rem;">Seats</th>
                        <th style="color: white; padding: 16px; text-align: left; font-weight: 700; font-size: 1rem;">Availability</th>
                        <th style="color: white; padding: 16px; text-align: left; font-weight: 700; font-size: 1rem;">Price</th>
                        <th style="color: white; padding: 16px; text-align: left; font-weight: 700; font-size: 1rem;">Status</th>
                        <th style="color: white; padding: 16px; text-align: left; font-weight: 700; font-size: 1rem;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($schedules as $schedule): 
                        $is_today = date('Y-m-d') == $schedule['show_date'];
                        $is_past = strtotime($schedule['show_date'] . ' ' . $schedule['showtime']) < time();
                        $available_percentage = ($schedule['total_seats'] > 0) ? ($schedule['available_seats'] / $schedule['total_seats']) * 100 : 0;
                        $availability_color = $available_percentage > 50 ? '#2ecc71' : ($available_percentage > 10 ? '#f39c12' : '#e74c3c');
                    ?>
                    <tr style="border-bottom: 1px solid rgba(255, 255, 255, 0.1); <?php echo $is_today ? 'background: rgba(52, 152, 219, 0.05);' : ''; ?>">
                        <td style="padding: 16px; color: rgba(255, 255, 255, 0.9); font-weight: 700;"><?php echo $schedule['id']; ?></td>
                        <td style="padding: 16px;">
                            <div style="color: white; font-size: 1rem; font-weight: 700;"><?php echo htmlspecialchars($schedule['movie_title']); ?></div>
                            <div style="color: rgba(255, 255, 255, 0.6); font-size: 0.75rem;">
                                <?php echo $schedule['rating']; ?> • <?php echo $schedule['duration']; ?>
                            </div>
                        </td>
                        <td style="padding: 16px; color: white;"><?php echo htmlspecialchars($schedule['venue_name']); ?></td>
                        <td style="padding: 16px;">
                            <span style="color: #2ecc71;"><?php echo htmlspecialchars($schedule['screen_name']); ?></span>
                            <div style="font-size: 0.7rem; color: rgba(255,255,255,0.5);">Screen #<?php echo $schedule['screen_number']; ?></div>
                        </td>
                        <td style="padding: 16px;">
                            <div style="color: #3498db; font-size: 1.1rem; font-weight: 700;"><?php echo date('h:i A', strtotime($schedule['showtime'])); ?></div>
                            <div style="color: rgba(255, 255, 255, 0.8); margin-top: 5px;">
                                <i class="far fa-calendar"></i> <?php echo date('M d, Y', strtotime($schedule['show_date'])); ?>
                                <?php if ($is_today): ?>
                                <span style="background: #3498db; color: white; padding: 3px 8px; border-radius: 4px; font-size: 0.7rem; margin-left: 5px;">TODAY</span>
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
                                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                    <span style="color: rgba(255, 255, 255, 0.6);">Total:</span>
                                    <span style="color: white; font-weight: 600;"><?php echo number_format($schedule['total_seats']); ?></span>
                                </div>
                                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                    <span style="color: rgba(255, 255, 255, 0.6);">Available:</span>
                                    <span style="color: #2ecc71; font-weight: 600;"><?php echo number_format($schedule['available_seats']); ?></span>
                                </div>
                                <div style="display: flex; justify-content: space-between;">
                                    <span style="color: rgba(255, 255, 255, 0.6);">Booked:</span>
                                    <span style="color: #e74c3c; font-weight: 600;"><?php echo number_format($schedule['booked_seats']); ?></span>
                                </div>
                            </div>
                         </div>
                        </td>
                        <td style="padding: 16px;">
                            <div style="background: rgba(255,255,255,0.1); height: 8px; border-radius: 4px; overflow: hidden; width: 100%; margin-bottom: 5px;">
                                <div style="background: <?php echo $availability_color; ?>; height: 100%; width: <?php echo $available_percentage; ?>%;"></div>
                            </div>
                            <div style="color: rgba(255,255,255,0.6); font-size: 0.75rem; text-align: center;">
                                <?php echo round($available_percentage); ?>% available
                            </div>
                            <?php if ($schedule['available_seats'] <= 10 && $schedule['available_seats'] > 0): ?>
                            <div style="color: #e74c3c; font-size: 0.7rem; text-align: center; margin-top: 5px;">
                                <i class="fas fa-exclamation-triangle"></i> Only <?php echo $schedule['available_seats']; ?> seats left!
                            </div>
                            <?php endif; ?>
                         </div>
                        </td>
                        <td style="padding: 16px;">
                            <span style="color: #2ecc71; font-weight: 700; font-size: 1.1rem;">
                                ₱<?php echo number_format($schedule['base_price'], 2); ?>
                            </span>
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
                            <?php elseif ($schedule['is_active']): ?>
                            <span style="background: rgba(46, 204, 113, 0.2); color: #2ecc71; padding: 8px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; display: inline-flex; align-items: center; gap: 5px;">
                                <i class="fas fa-check-circle"></i> Active
                            </span>
                            <?php else: ?>
                            <span style="background: rgba(108, 117, 125, 0.2); color: #6c757d; padding: 8px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; display: inline-flex; align-items: center; gap: 5px;">
                                <i class="fas fa-pause-circle"></i> Inactive
                            </span>
                            <?php endif; ?>
                         </div>
                        <tr>
                        <td style="padding: 16px;">
                            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                <a href="index.php?page=admin/manage-schedules&edit=<?php echo $schedule['id']; ?>" 
                                   style="padding: 8px 16px; background: rgba(52, 152, 219, 0.2); color: #3498db; text-decoration: none; border-radius: 6px; font-size: 0.85rem; font-weight: 600; border: 1px solid rgba(52, 152, 219, 0.3); display: inline-flex; align-items: center; gap: 5px;">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <?php if ($schedule['booking_count'] == 0 && !$is_past): ?>
                                <a href="index.php?page=admin/manage-schedules&delete=<?php echo $schedule['id']; ?>" 
                                   style="padding: 8px 16px; background: rgba(231, 76, 60, 0.2); color: #e74c3c; text-decoration: none; border-radius: 6px; font-size: 0.85rem; font-weight: 600; border: 1px solid rgba(231, 76, 60, 0.3); display: inline-flex; align-items: center; gap: 5px;"
                                   onclick="return confirm('Are you sure you want to delete this schedule?\nMovie: <?php echo addslashes($schedule['movie_title']); ?>\nVenue: <?php echo addslashes($schedule['venue_name']); ?>\nScreen: <?php echo addslashes($schedule['screen_name']); ?>\nDate: <?php echo date('M d, Y', strtotime($schedule['show_date'])); ?> <?php echo date('h:i A', strtotime($schedule['showtime'])); ?>')">
                                    <i class="fas fa-trash"></i> Delete
                                </a>
                                <?php else: ?>
                                <span style="padding: 8px 16px; background: rgba(108, 117, 125, 0.2); color: #6c757d; border-radius: 6px; font-size: 0.85rem; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; cursor: not-allowed;" title="Cannot delete - has <?php echo $schedule['booking_count']; ?> booking(s)">
                                    <i class="fas fa-lock"></i> Locked
                                </span>
                                <?php endif; ?>
                            </div>
                         </div>
                     </div>
                    <?php endforeach; ?>
                </tbody>
             </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Note about schedules -->
    <div style="margin-top: 30px; padding: 20px; background: rgba(52, 152, 219, 0.05); border-radius: 10px; border-left: 4px solid #3498db;">
        <p style="color: rgba(255, 255, 255, 0.7); font-size: 0.9rem;">
            <i class="fas fa-info-circle" style="color: #3498db;"></i> 
            <strong>How it works:</strong> 
            1. Select a Movie → 2. Select a Screen → 3. Select a Seat Plan for that screen → 4. Set Date, Time, and Price
        </p>
        <p style="color: rgba(255, 255, 255, 0.5); font-size: 0.85rem; margin-top: 10px;">
            <i class="fas fa-star" style="color: #f39c12;"></i> 
            When you create a schedule, seat availability is automatically generated from the selected seat plan.
            Different seat types (Standard, Premium, Sweet Spot) will have prices based on the movie's screen price settings.
        </p>
    </div>
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
    
    @media (max-width: 768px) {
        .admin-content {
            padding: 15px;
        }
        
        div > div {
            padding: 20px;
        }
        
        table {
            font-size: 0.85rem;
        }
    }
</style>

<script>
document.getElementById('scheduleForm')?.addEventListener('submit', function(e) {
    const movieId = document.getElementById('movie_id')?.value;
    const screenId = document.getElementById('screen_id')?.value;
    const seatPlanId = document.getElementById('seat_plan_id')?.value;
    const showDate = document.getElementById('show_date')?.value;
    const showtime = document.getElementById('showtime')?.value;
    const basePrice = document.getElementById('base_price')?.value;
    
    if (!movieId || !screenId || !seatPlanId) {
        e.preventDefault();
        alert('Please select a movie, screen, and seat plan!');
        return false;
    }
    
    if (!showDate || !showtime) {
        e.preventDefault();
        alert('Please select a show date and time!');
        return false;
    }
    
    if (!basePrice || parseFloat(basePrice) <= 0) {
        e.preventDefault();
        alert('Please enter a valid ticket price!');
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

// Set min date to today
document.getElementById('show_date').min = new Date().toISOString().split('T')[0];

// Set default time if empty
document.getElementById('showtime')?.addEventListener('focus', function() {
    if (!this.value) {
        this.value = '18:00';
    }
});

// Filter seat plans based on selected screen
function filterSeatPlans() {
    const screenId = document.getElementById('screen_id')?.value;
    const seatPlanSelect = document.getElementById('seat_plan_id');
    const allOptions = seatPlanSelect.options;
    
    if (screenId) {
        for (let i = 0; i < allOptions.length; i++) {
            const option = allOptions[i];
            if (option.value === "") continue;
            
            const optionScreenId = option.getAttribute('data-screen-id');
            if (optionScreenId && optionScreenId == screenId) {
                option.style.display = '';
            } else {
                option.style.display = 'none';
            }
        }
        seatPlanSelect.value = "";
    }
}

// Filter seat plans when screen changes
document.getElementById('screen_id')?.addEventListener('change', filterSeatPlans);

// Load schedule info on selection change
async function loadScheduleInfo() {
    const movieId = document.getElementById('movie_id')?.value;
    const screenId = document.getElementById('screen_id')?.value;
    const infoDiv = document.getElementById('scheduleInfo');
    const contentDiv = document.getElementById('scheduleInfoContent');
    
    if (movieId && screenId) {
        try {
            const response = await fetch(`<?php echo SITE_URL; ?>ajax/get-schedule-info.php?movie_id=${movieId}&screen_id=${screenId}`);
            const data = await response.json();
            
            if (data.success) {
                infoDiv.style.display = 'block';
                contentDiv.innerHTML = `
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                        <div>
                            <strong style="color: #3498db;">Movie:</strong> ${escapeHtml(data.movie_title)}<br>
                            <strong style="color: #3498db;">Duration:</strong> ${data.duration}<br>
                            <strong style="color: #3498db;">Rating:</strong> ${data.rating}
                        </div>
                        <div>
                            <strong style="color: #3498db;">Venue:</strong> ${escapeHtml(data.venue_name)}<br>
                            <strong style="color: #3498db;">Screen:</strong> ${escapeHtml(data.screen_name)}<br>
                            <strong style="color: #3498db;">Capacity:</strong> ${data.capacity} seats
                        </div>
                    </div>
                `;
            } else {
                infoDiv.style.display = 'none';
            }
        } catch (error) {
            console.error('Error loading schedule info:', error);
            infoDiv.style.display = 'none';
        }
    } else {
        infoDiv.style.display = 'none';
    }
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

document.getElementById('movie_id')?.addEventListener('change', loadScheduleInfo);
document.getElementById('screen_id')?.addEventListener('change', loadScheduleInfo);

// Initial filter and load
filterSeatPlans();
<?php if ($edit_mode): ?>
loadScheduleInfo();
<?php endif; ?>

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
        document.getElementById('scheduleForm')?.submit();
    }
});
</script>

<?php
// Close the database connection here - at the very end
if (isset($conn) && $conn) {
    $conn->close();
}
?>
</body>
</html>