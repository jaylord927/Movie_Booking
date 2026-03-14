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
$schedule_id = isset($_GET['schedule_id']) ? intval($_GET['schedule_id']) : 0;

if ($schedule_id <= 0) {
    header("Location: " . SITE_URL . "index.php?page=admin/manage-schedules");
    exit();
}

// Get schedule and movie information
$schedule_info = $conn->prepare("
    SELECT s.*, m.title as movie_title_full, m.standard_price, m.premium_price, m.sweet_spot_price,
           m.id as movie_id
    FROM movie_schedules s
    LEFT JOIN movies m ON s.movie_id = m.id
    WHERE s.id = ? AND s.is_active = 1
");
$schedule_info->bind_param("i", $schedule_id);
$schedule_info->execute();
$schedule_result = $schedule_info->get_result();
$current_schedule = $schedule_result->fetch_assoc();
$schedule_info->close();

if (!$current_schedule) {
    header("Location: " . SITE_URL . "index.php?page=admin/manage-schedules");
    exit();
}

// Handle seat count update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_seat_count'])) {
    $new_total_seats = intval($_POST['new_total_seats']);
    $movie_id = $current_schedule['movie_id'];
    
    // Get current seat count
    $count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM seat_availability WHERE schedule_id = ?");
    $count_stmt->bind_param("i", $schedule_id);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $current_count = $count_result->fetch_assoc()['count'];
    $count_stmt->close();
    
    if ($new_total_seats < 1 || $new_total_seats > 100000) {
        $error = "Seat count must be between 1 and 100,000!";
    } elseif ($new_total_seats < $current_count) {
        // Check if seats are booked before reducing
        $booked_stmt = $conn->prepare("
            SELECT COUNT(*) as booked 
            FROM seat_availability 
            WHERE schedule_id = ? AND is_available = 0
        ");
        $booked_stmt->bind_param("i", $schedule_id);
        $booked_stmt->execute();
        $booked_result = $booked_stmt->get_result();
        $booked_count = $booked_result->fetch_assoc()['booked'];
        $booked_stmt->close();
        
        if ($booked_count > 0) {
            $error = "Cannot reduce seats. There are $booked_count booked seats for this schedule.";
        } else {
            // Delete excess seats
            $delete_stmt = $conn->prepare("
                DELETE FROM seat_availability 
                WHERE schedule_id = ? 
                ORDER BY seat_number DESC 
                LIMIT ?
            ");
            $delete_count = $current_count - $new_total_seats;
            $delete_stmt->bind_param("ii", $schedule_id, $delete_count);
            
            if ($delete_stmt->execute()) {
                // Update total seats in movie_schedules
                $update_schedule = $conn->prepare("UPDATE movie_schedules SET total_seats = ?, available_seats = ? WHERE id = ?");
                $available_seats = $new_total_seats;
                $update_schedule->bind_param("iii", $new_total_seats, $available_seats, $schedule_id);
                $update_schedule->execute();
                $update_schedule->close();
                
                $success = "Seats reduced to $new_total_seats successfully!";
                $current_schedule['total_seats'] = $new_total_seats;
            } else {
                $error = "Failed to update seats: " . $conn->error;
            }
            $delete_stmt->close();
        }
    } elseif ($new_total_seats > $current_count) {
        // Add new seats
        $conn->begin_transaction();
        
        try {
            // Update total seats in movie_schedules first
            $update_schedule = $conn->prepare("UPDATE movie_schedules SET total_seats = ?, available_seats = available_seats + ? WHERE id = ?");
            $additional_seats = $new_total_seats - $current_count;
            $update_schedule->bind_param("iii", $new_total_seats, $additional_seats, $schedule_id);
            
            if (!$update_schedule->execute()) {
                throw new Exception("Failed to update schedule: " . $update_schedule->error);
            }
            $update_schedule->close();
            
            // Get movie prices
            $movie_stmt = $conn->prepare("SELECT standard_price, premium_price, sweet_spot_price FROM movies WHERE id = ?");
            $movie_stmt->bind_param("i", $movie_id);
            $movie_stmt->execute();
            $movie_result = $movie_stmt->get_result();
            $movie_prices = $movie_result->fetch_assoc();
            $movie_stmt->close();
            
            $standard_price = $movie_prices['standard_price'] ?? 350.00;
            $premium_price = $movie_prices['premium_price'] ?? 450.00;
            $sweet_spot_price = $movie_prices['sweet_spot_price'] ?? 550.00;
            
            // Add new seats
            $seat_stmt = $conn->prepare("INSERT INTO seat_availability (schedule_id, movie_title, show_date, showtime, seat_number, seat_type, is_available, price) VALUES (?, ?, ?, ?, ?, ?, 1, ?)");
            
            for ($i = $current_count + 1; $i <= $new_total_seats; $i++) {
                $row_number = ceil($i / 10);
                $row_letter = chr(64 + $row_number);
                $seat_in_row = (($i - 1) % 10) + 1;
                $seat_number = $row_letter . str_pad($seat_in_row, 2, '0', STR_PAD_LEFT);
                
                $seat_type = 'Standard';
                $price = $standard_price;
                
                if ($i >= 1 && $i <= 10) {
                    $seat_type = 'Premium';
                    $price = $premium_price;
                } elseif ($i >= 31 && $i <= 40) {
                    $seat_type = 'Sweet Spot';
                    $price = $sweet_spot_price;
                }
                
                $seat_stmt->bind_param("isssssd", $schedule_id, $current_schedule['movie_title'], $current_schedule['show_date'], $current_schedule['showtime'], $seat_number, $seat_type, $price);
                
                if (!$seat_stmt->execute()) {
                    throw new Exception("Failed to add seat $seat_number: " . $seat_stmt->error);
                }
            }
            
            $seat_stmt->close();
            $conn->commit();
            
            $success = "Seats increased to $new_total_seats successfully! Added $additional_seats new seats.";
            $current_schedule['total_seats'] = $new_total_seats;
            
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Failed to update seats: " . $e->getMessage();
        }
    } else {
        $success = "Seat count is already $new_total_seats.";
    }
}

// Handle seat type updates
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

// Get all seats for this schedule
$seat_stmt = $conn->prepare("
    SELECT sa.*, m.standard_price, m.premium_price, m.sweet_spot_price
    FROM seat_availability sa
    JOIN movie_schedules ms ON sa.schedule_id = ms.id
    JOIN movies m ON ms.movie_id = m.id
    WHERE sa.schedule_id = ? 
    ORDER BY sa.seat_number
");
$seat_stmt->bind_param("i", $schedule_id);
$seat_stmt->execute();
$seat_result = $seat_stmt->get_result();
$seats = [];
while ($row = $seat_result->fetch_assoc()) {
    $seats[] = $row;
}
$seat_stmt->close();

$conn->close();
?>

<div class="admin-content" style="max-width: 1400px; margin: 0 auto; padding: 30px;">
    <div style="text-align: center; margin-bottom: 40px; padding: 30px; background: linear-gradient(135deg, rgba(52, 152, 219, 0.1), rgba(41, 128, 185, 0.2)); border-radius: 20px; border: 2px solid rgba(52, 152, 219, 0.3);">
        <h1 style="color: white; font-size: 2.5rem; margin-bottom: 15px; font-weight: 800;">Manage Seats</h1>
        <p style="color: rgba(255, 255, 255, 0.8); font-size: 1.1rem;">Add, remove, or modify seats for movie schedules</p>
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

    <div style="background: rgba(255, 255, 255, 0.05); border-radius: 15px; padding: 30px; margin-bottom: 40px; border: 1px solid rgba(52, 152, 219, 0.2);">
        <h2 style="color: white; font-size: 1.8rem; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid #3498db; display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-chair"></i> Seat Management for: <?php echo htmlspecialchars($current_schedule['movie_title_full']); ?>
        </h2>
        
        <div style="background: rgba(23, 162, 184, 0.2); color: #17a2b8; padding: 15px 20px; border-radius: 10px; margin-bottom: 25px; font-weight: 600; border: 1px solid rgba(23, 162, 184, 0.3);">
            <i class="fas fa-info-circle"></i> 
            Show Time: <?php echo date('M d, Y', strtotime($current_schedule['show_date'])); ?> at <?php echo date('h:i A', strtotime($current_schedule['showtime'])); ?> | 
            Current Seats in Database: <strong><?php echo count($seats); ?></strong>
        </div>
        
        <!-- Seat Count Update Form -->
        <div style="background: rgba(52, 152, 219, 0.1); border-radius: 10px; padding: 20px; margin-bottom: 30px;">
            <h3 style="color: white; font-size: 1.2rem; margin-bottom: 15px; font-weight: 600;">Update Seat Count</h3>
            <form method="POST" action="" onsubmit="return confirm('Warning: Changing seat count will add or remove seats from the database. Continue?')">
                <input type="hidden" name="update_seat_count" value="1">
                <div style="display: flex; gap: 20px; align-items: flex-end; flex-wrap: wrap;">
                    <div style="flex: 1; min-width: 250px;">
                        <label style="display: block; color: white; font-weight: 600; margin-bottom: 8px;">New Total Seats (1 - 100,000)</label>
                        <input type="number" name="new_total_seats" min="1" max="100000" 
                               value="<?php echo $current_schedule['total_seats']; ?>" required
                               style="width: 100%; padding: 12px 16px; background: rgba(255, 255, 255, 0.08); border: 2px solid #3498db; border-radius: 8px; color: white; font-size: 1rem;">
                    </div>
                    <div>
                        <button type="submit" style="padding: 12px 30px; background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: all 0.3s ease;">
                            <i class="fas fa-sync-alt"></i> Update Seat Count
                        </button>
                    </div>
                </div>
                <div style="color: rgba(255, 255, 255, 0.6); font-size: 0.9rem; margin-top: 10px;">
                    <i class="fas fa-info-circle"></i> Increasing seats will add new seats at the end. Decreasing seats will remove seats from the end (only if no bookings exist).
                </div>
            </form>
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
            
            <div style="background: rgba(0, 0, 0, 0.3); padding: 30px; border-radius: 10px; margin-bottom: 30px; overflow-x: auto;">
                <div style="text-align: center; margin-bottom: 30px; color: white; font-size: 1.5rem; font-weight: 700;">
                    <i class="fas fa-film"></i> SCREEN
                </div>
                
                <?php if (empty($seats)): ?>
                    <div style="text-align: center; padding: 50px; color: rgba(255, 255, 255, 0.6);">
                        <i class="fas fa-chair fa-3x" style="margin-bottom: 20px; opacity: 0.5;"></i>
                        <p style="font-size: 1.1rem;">No seats found. Add seats using the form above.</p>
                    </div>
                <?php else: ?>
                    <div style="display: flex; flex-wrap: wrap; justify-content: center; gap: 10px;">
                        <?php 
                        $current_row = '';
                        $row_count = 0;
                        foreach ($seats as $index => $seat): 
                            $seat_number = $seat['seat_number'];
                            $row_letter = substr($seat_number, 0, 1);
                            
                            if ($current_row != $row_letter) {
                                if ($current_row != '') {
                                    echo '</div><div style="margin-bottom: 20px;"></div>';
                                }
                                $current_row = $row_letter;
                                $row_count++;
                                echo '<div style="width: 100%; margin-bottom: 15px;"><div style="color: #3498db; font-size: 1.2rem; font-weight: 700; margin-bottom: 10px;">Row ' . $row_letter . '</div><div style="display: flex; flex-wrap: wrap; gap: 10px; justify-content: center;">';
                            }
                            
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
                        <div style="text-align: center; min-width: 80px;">
                            <div style="margin-bottom: 5px; color: white; font-size: 0.9rem; font-weight: 600;"><?php echo $seat['seat_number']; ?></div>
                            <select name="seat_type[<?php echo $seat['id']; ?>]" style="width: 100%; padding: 8px; background: <?php echo $seat_color; ?>; border: 2px solid rgba(255, 255, 255, 0.3); border-radius: 6px; color: white; font-weight: 600; cursor: pointer; text-align: center;" <?php echo !$seat['is_available'] ? 'disabled' : ''; ?>>
                                <option value="Standard" <?php echo $seat['seat_type'] === 'Standard' ? 'selected' : ''; ?> style="background: #2c3e50; color: white;">Standard</option>
                                <option value="Premium" <?php echo $seat['seat_type'] === 'Premium' ? 'selected' : ''; ?> style="background: #2c3e50; color: white;">Premium</option>
                                <option value="Sweet Spot" <?php echo $seat['seat_type'] === 'Sweet Spot' ? 'selected' : ''; ?> style="background: #2c3e50; color: white;">Sweet Spot</option>
                            </select>
                            <div style="margin-top: 5px; color: white; font-size: 0.75rem;">₱<?php echo number_format($display_price, 0); ?></div>
                        </div>
                        <?php 
                            if (($index + 1) % 10 == 0 && $index + 1 < count($seats)) {
                                echo '</div><div style="margin: 10px 0;"></div><div style="display: flex; flex-wrap: wrap; gap: 10px; justify-content: center;">';
                            }
                        endforeach; 
                        echo '</div>';
                        ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($seats)): ?>
            <div style="text-align: center; margin-top: 30px;">
                <button type="submit" style="padding: 16px 45px; background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; border: none; border-radius: 12px; font-size: 1.1rem; font-weight: 700; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 6px 20px rgba(52, 152, 219, 0.3); display: inline-flex; align-items: center; justify-content: center; gap: 10px;">
                    <i class="fas fa-save"></i> Update Seat Types
                </button>
            </div>
            <?php endif; ?>
        </form>
        
        <div style="text-align: center; margin-top: 30px;">
            <a href="index.php?page=admin/manage-schedules" style="padding: 16px 30px; background: rgba(255, 255, 255, 0.1); color: white; text-decoration: none; border-radius: 12px; font-size: 1.1rem; font-weight: 600; border: 2px solid rgba(52, 152, 219, 0.3); display: inline-flex; align-items: center; justify-content: center; gap: 10px;">
                <i class="fas fa-arrow-left"></i> Back to Schedules
            </a>
        </div>
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
    }
</style>

<script>
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
</script>

</div>
</body>
</html>