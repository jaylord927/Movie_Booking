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

$conn = get_db_connection();

$error = '';
$success = '';
$edit_mode = false;
$edit_movie = null;

// ============================================
// ADD MOVIE
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_movie'])) {
    $title = htmlspecialchars(trim($_POST['title']), ENT_QUOTES, 'UTF-8');
    $director = htmlspecialchars(trim($_POST['director'] ?? ''), ENT_QUOTES, 'UTF-8');
    $genre = htmlspecialchars(trim($_POST['genre']), ENT_QUOTES, 'UTF-8');
    $duration = htmlspecialchars(trim($_POST['duration']), ENT_QUOTES, 'UTF-8');
    $rating = htmlspecialchars(trim($_POST['rating']), ENT_QUOTES, 'UTF-8');
    $description = trim($_POST['description']);
    $poster_url = htmlspecialchars(trim($_POST['poster_url'] ?? ''), ENT_QUOTES, 'UTF-8');
    $trailer_url = htmlspecialchars(trim($_POST['trailer_url'] ?? ''), ENT_QUOTES, 'UTF-8');
    $selected_screens = isset($_POST['screen_ids']) ? $_POST['screen_ids'] : [];
    
    if (empty($title) || empty($genre) || empty($duration) || empty($rating) || empty($description)) {
        $error = "All required fields must be filled!";
    } elseif (empty($selected_screens)) {
        $error = "Please select at least one screen!";
    } else {
        $conn->begin_transaction();
        
        try {
            // Insert movie
            $stmt = $conn->prepare("INSERT INTO movies (title, director, genre, duration, rating, description, poster_url, trailer_url, is_active, added_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?)");
            $stmt->bind_param("ssssssssi", $title, $director, $genre, $duration, $rating, $description, $poster_url, $trailer_url, $_SESSION['user_id']);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to add movie: " . $stmt->error);
            }
            
            $new_movie_id = $stmt->insert_id;
            $stmt->close();
            
            // Insert movie-screen prices
            $price_stmt = $conn->prepare("INSERT INTO movie_screen_prices (movie_id, screen_id, seat_type_id, price, is_active) VALUES (?, ?, ?, ?, 1)");
            
            foreach ($selected_screens as $screen_id) {
                $standard_price = floatval($_POST['standard_price_' . $screen_id] ?? 350);
                $premium_price = floatval($_POST['premium_price_' . $screen_id] ?? 450);
                $sweet_spot_price = floatval($_POST['sweet_spot_price_' . $screen_id] ?? 550);
                
                // Get seat type IDs
                $seat_types = $conn->query("SELECT id, name FROM seat_types WHERE is_active = 1");
                $seat_type_ids = [];
                while ($st = $seat_types->fetch_assoc()) {
                    $seat_type_ids[$st['name']] = $st['id'];
                }
                
                // Standard seat price
                if (isset($seat_type_ids['Standard'])) {
                    $price_stmt->bind_param("iiid", $new_movie_id, $screen_id, $seat_type_ids['Standard'], $standard_price);
                    $price_stmt->execute();
                }
                
                // Premium seat price
                if (isset($seat_type_ids['Premium'])) {
                    $price_stmt->bind_param("iiid", $new_movie_id, $screen_id, $seat_type_ids['Premium'], $premium_price);
                    $price_stmt->execute();
                }
                
                // Sweet Spot seat price
                if (isset($seat_type_ids['Sweet Spot'])) {
                    $price_stmt->bind_param("iiid", $new_movie_id, $screen_id, $seat_type_ids['Sweet Spot'], $sweet_spot_price);
                    $price_stmt->execute();
                }
            }
            $price_stmt->close();
            
            $conn->commit();
            $success = "Movie added successfully!";
            $_POST = array();
            
        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
    }
}

// ============================================
// UPDATE MOVIE
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_movie'])) {
    $id = intval($_POST['id']);
    $title = htmlspecialchars(trim($_POST['title']), ENT_QUOTES, 'UTF-8');
    $director = htmlspecialchars(trim($_POST['director'] ?? ''), ENT_QUOTES, 'UTF-8');
    $genre = htmlspecialchars(trim($_POST['genre']), ENT_QUOTES, 'UTF-8');
    $duration = htmlspecialchars(trim($_POST['duration']), ENT_QUOTES, 'UTF-8');
    $rating = htmlspecialchars(trim($_POST['rating']), ENT_QUOTES, 'UTF-8');
    $description = trim($_POST['description']);
    $poster_url = htmlspecialchars(trim($_POST['poster_url'] ?? ''), ENT_QUOTES, 'UTF-8');
    $trailer_url = htmlspecialchars(trim($_POST['trailer_url'] ?? ''), ENT_QUOTES, 'UTF-8');
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $selected_screens = isset($_POST['screen_ids']) ? $_POST['screen_ids'] : [];
    
    if (empty($title) || empty($genre) || empty($duration) || empty($rating) || empty($description)) {
        $error = "All required fields must be filled!";
    } elseif (empty($selected_screens)) {
        $error = "Please select at least one screen!";
    } else {
        $conn->begin_transaction();
        
        try {
            // Update movie
            $stmt = $conn->prepare("UPDATE movies SET title = ?, director = ?, genre = ?, duration = ?, rating = ?, description = ?, poster_url = ?, trailer_url = ?, is_active = ?, updated_by = ? WHERE id = ?");
            $stmt->bind_param("sssssssssii", $title, $director, $genre, $duration, $rating, $description, $poster_url, $trailer_url, $is_active, $_SESSION['user_id'], $id);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to update movie: " . $stmt->error);
            }
            $stmt->close();
            
            // Delete existing prices
            $delete_stmt = $conn->prepare("DELETE FROM movie_screen_prices WHERE movie_id = ?");
            $delete_stmt->bind_param("i", $id);
            if (!$delete_stmt->execute()) {
                throw new Exception("Failed to remove existing prices: " . $delete_stmt->error);
            }
            $delete_stmt->close();
            
            // Insert updated prices
            $price_stmt = $conn->prepare("INSERT INTO movie_screen_prices (movie_id, screen_id, seat_type_id, price, is_active) VALUES (?, ?, ?, ?, 1)");
            
            foreach ($selected_screens as $screen_id) {
                $standard_price = floatval($_POST['standard_price_' . $screen_id] ?? 350);
                $premium_price = floatval($_POST['premium_price_' . $screen_id] ?? 450);
                $sweet_spot_price = floatval($_POST['sweet_spot_price_' . $screen_id] ?? 550);
                
                // Get seat type IDs
                $seat_types = $conn->query("SELECT id, name FROM seat_types WHERE is_active = 1");
                $seat_type_ids = [];
                while ($st = $seat_types->fetch_assoc()) {
                    $seat_type_ids[$st['name']] = $st['id'];
                }
                
                // Standard seat price
                if (isset($seat_type_ids['Standard'])) {
                    $price_stmt->bind_param("iiid", $id, $screen_id, $seat_type_ids['Standard'], $standard_price);
                    $price_stmt->execute();
                }
                
                // Premium seat price
                if (isset($seat_type_ids['Premium'])) {
                    $price_stmt->bind_param("iiid", $id, $screen_id, $seat_type_ids['Premium'], $premium_price);
                    $price_stmt->execute();
                }
                
                // Sweet Spot seat price
                if (isset($seat_type_ids['Sweet Spot'])) {
                    $price_stmt->bind_param("iiid", $id, $screen_id, $seat_type_ids['Sweet Spot'], $sweet_spot_price);
                    $price_stmt->execute();
                }
            }
            $price_stmt->close();
            
            $conn->commit();
            $success = "Movie updated successfully!";
            $edit_mode = false;
            
        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
    }
}

// ============================================
// DELETE MOVIE (Soft Delete)
// ============================================
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    // Check if movie has schedules
    $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM schedules WHERE movie_id = ? AND is_active = 1");
    $check_stmt->bind_param("i", $id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $schedule_count = $result->fetch_assoc()['count'];
    $check_stmt->close();
    
    if ($schedule_count > 0) {
        $error = "Cannot delete movie with $schedule_count active schedule(s). Remove the schedules first.";
    } else {
        $stmt = $conn->prepare("UPDATE movies SET is_active = 0 WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $success = "Movie deleted successfully!";
        } else {
            $error = "Failed to delete movie: " . $stmt->error;
        }
        $stmt->close();
    }
}

// ============================================
// GET MOVIE FOR EDITING
// ============================================
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    
    $stmt = $conn->prepare("
        SELECT m.*, u1.u_name as added_by_name, u2.u_name as updated_by_name
        FROM movies m
        LEFT JOIN users u1 ON m.added_by = u1.u_id
        LEFT JOIN users u2 ON m.updated_by = u2.u_id
        WHERE m.id = ? AND m.is_active = 1
    ");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_movie = $result->fetch_assoc();
    $stmt->close();
    
    if ($edit_movie) {
        // Get selected screens for this movie
        $screens_stmt = $conn->prepare("
            SELECT msp.screen_id, msp.seat_type_id, msp.price
            FROM movie_screen_prices msp
            WHERE msp.movie_id = ? AND msp.is_active = 1
        ");
        $screens_stmt->bind_param("i", $edit_id);
        $screens_stmt->execute();
        $screens_result = $screens_stmt->get_result();
        
        $edit_movie['selected_screens'] = [];
        $edit_movie['screen_prices'] = [];
        
        while ($row = $screens_result->fetch_assoc()) {
            if (!in_array($row['screen_id'], $edit_movie['selected_screens'])) {
                $edit_movie['selected_screens'][] = $row['screen_id'];
            }
            
            // Get seat type name
            $seat_type_stmt = $conn->prepare("SELECT name FROM seat_types WHERE id = ?");
            $seat_type_stmt->bind_param("i", $row['seat_type_id']);
            $seat_type_stmt->execute();
            $seat_type_result = $seat_type_stmt->get_result();
            $seat_type = $seat_type_result->fetch_assoc();
            $seat_type_stmt->close();
            
            $edit_movie['screen_prices'][$row['screen_id']][$seat_type['name']] = $row['price'];
        }
        $screens_stmt->close();
        $edit_mode = true;
    }
}

// ============================================
// FETCH DATA FOR DISPLAY
// ============================================

// Get all screens with venue information
$screens_result = $conn->query("
    SELECT 
        s.id as screen_id,
        s.screen_name,
        s.screen_number,
        s.capacity,
        v.id as venue_id,
        v.venue_name,
        v.venue_location,
        CONCAT(v.venue_name, ' - ', s.screen_name, ' (Screen ', s.screen_number, ')') as display_name
    FROM screens s
    JOIN venues v ON s.venue_id = v.id
    WHERE s.is_active = 1 AND v.is_active = 1
    ORDER BY v.venue_name, s.screen_number
");

$screens = [];
if ($screens_result) {
    while ($row = $screens_result->fetch_assoc()) {
        $screens[] = $row;
    }
}

// Get all movies with their screen assignments and prices
$movies_result = $conn->query("
    SELECT 
        m.*,
        GROUP_CONCAT(DISTINCT CONCAT(v.venue_name, ' - ', s.screen_name) ORDER BY v.venue_name SEPARATOR '<br>') as screen_names,
        COUNT(DISTINCT msp.screen_id) as screen_count,
        a.u_name as added_by_name,
        u.u_name as updated_by_name
    FROM movies m
    LEFT JOIN movie_screen_prices msp ON m.id = msp.movie_id AND msp.is_active = 1
    LEFT JOIN screens s ON msp.screen_id = s.id
    LEFT JOIN venues v ON s.venue_id = v.id
    LEFT JOIN users a ON m.added_by = a.u_id
    LEFT JOIN users u ON m.updated_by = u.u_id
    WHERE m.is_active = 1
    GROUP BY m.id
    ORDER BY m.created_at DESC
");

$movies = [];
if ($movies_result) {
    while ($row = $movies_result->fetch_assoc()) {
        $movies[] = $row;
    }
}

$count_result = $conn->query("SELECT COUNT(*) as total FROM movies WHERE is_active = 1");
$movie_count = $count_result ? $count_result->fetch_assoc()['total'] : 0;

// Get seat types for price display
$seat_types_result = $conn->query("SELECT id, name, default_price, color_code FROM seat_types WHERE is_active = 1 ORDER BY sort_order");
$seat_types = [];
if ($seat_types_result) {
    while ($row = $seat_types_result->fetch_assoc()) {
        $seat_types[] = $row;
    }
}
?>

<div class="admin-content" style="max-width: 1400px; margin: 0 auto; padding: 30px;">
    <div style="text-align: center; margin-bottom: 40px; padding: 30px; background: linear-gradient(135deg, rgba(52, 152, 219, 0.1), rgba(41, 128, 185, 0.2)); border-radius: 20px; border: 2px solid rgba(52, 152, 219, 0.3);">
        <h1 style="color: white; font-size: 2.5rem; margin-bottom: 15px; font-weight: 800;">Manage Movies</h1>
        <p style="color: rgba(255, 255, 255, 0.8); font-size: 1.1rem;">Add, edit, or remove movies from the system</p>
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

    <!-- Add/Edit Movie Form -->
    <div style="background: rgba(255, 255, 255, 0.05); border-radius: 15px; padding: 30px; margin-bottom: 40px; border: 1px solid rgba(52, 152, 219, 0.2);">
        <h2 style="color: white; font-size: 1.8rem; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid #3498db; display: flex; align-items: center; gap: 10px;">
            <i class="<?php echo $edit_mode ? 'fas fa-edit' : 'fas fa-plus-circle'; ?>"></i>
            <?php echo $edit_mode ? 'Edit Movie' : 'Add New Movie'; ?>
        </h2>
        
        <?php if ($edit_mode): ?>
        <div style="background: rgba(23, 162, 184, 0.2); color: #17a2b8; padding: 15px 20px; border-radius: 10px; margin-bottom: 25px; font-weight: 600; border: 1px solid rgba(23, 162, 184, 0.3);">
            <i class="fas fa-info-circle"></i> Editing: <strong><?php echo htmlspecialchars($edit_movie['title']); ?></strong>
        </div>
        <?php endif; ?>
        
        <form method="POST" action="" id="movieForm">
            <?php if ($edit_mode): ?>
            <input type="hidden" name="id" value="<?php echo $edit_movie['id']; ?>">
            <?php endif; ?>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 25px; margin-bottom: 30px;">
                <div>
                    <label style="display: block; color: white; font-weight: 600; margin-bottom: 10px; font-size: 1rem;">
                        <i class="fas fa-film"></i> Movie Title *
                    </label>
                    <input type="text" id="title" name="title" required 
                           value="<?php echo $edit_mode ? htmlspecialchars($edit_movie['title']) : (isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''); ?>"
                           style="width: 100%; padding: 14px 16px; background: rgba(255, 255, 255, 0.08); border: 2px solid rgba(52, 152, 219, 0.3); border-radius: 10px; color: white; font-size: 1rem;">
                </div>
                
                <div>
                    <label style="display: block; color: white; font-weight: 600; margin-bottom: 10px; font-size: 1rem;">
                        <i class="fas fa-user"></i> Director
                    </label>
                    <input type="text" id="director" name="director" 
                           value="<?php echo $edit_mode ? htmlspecialchars($edit_movie['director'] ?? '') : (isset($_POST['director']) ? htmlspecialchars($_POST['director']) : ''); ?>"
                           style="width: 100%; padding: 14px 16px; background: rgba(255, 255, 255, 0.08); border: 2px solid rgba(52, 152, 219, 0.3); border-radius: 10px; color: white; font-size: 1rem;">
                </div>
                
                <div>
                    <label style="display: block; color: white; font-weight: 600; margin-bottom: 10px; font-size: 1rem;">
                        <i class="fas fa-tag"></i> Genre *
                    </label>
                    <input type="text" id="genre" name="genre" required
                           value="<?php echo $edit_mode ? htmlspecialchars($edit_movie['genre']) : (isset($_POST['genre']) ? htmlspecialchars($_POST['genre']) : ''); ?>"
                           style="width: 100%; padding: 14px 16px; background: rgba(255, 255, 255, 0.08); border: 2px solid rgba(52, 152, 219, 0.3); border-radius: 10px; color: white; font-size: 1rem;">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 25px; margin-bottom: 30px;">
                <div>
                    <label style="display: block; color: white; font-weight: 600; margin-bottom: 10px; font-size: 1rem;">
                        <i class="fas fa-clock"></i> Duration *
                    </label>
                    <input type="text" id="duration" name="duration" required placeholder="e.g., 2h 28min"
                           value="<?php echo $edit_mode ? htmlspecialchars($edit_movie['duration']) : (isset($_POST['duration']) ? htmlspecialchars($_POST['duration']) : ''); ?>"
                           style="width: 100%; padding: 14px 16px; background: rgba(255, 255, 255, 0.08); border: 2px solid rgba(52, 152, 219, 0.3); border-radius: 10px; color: white; font-size: 1rem;">
                </div>
                
                <div>
                    <label style="display: block; color: white; font-weight: 600; margin-bottom: 10px; font-size: 1rem;">
                        <i class="fas fa-star"></i> Rating *
                    </label>
                    <select id="rating" name="rating" required style="width: 100%; padding: 14px 16px; background: rgba(255, 255, 255, 0.08); border: 2px solid rgba(52, 152, 219, 0.3); border-radius: 10px; color: white; font-size: 1rem; cursor: pointer;">
                        <option value="" style="background: #2c3e50; color: white;">Select Rating</option>
                        <option value="G" style="background: #2c3e50; color: white;" <?php echo ($edit_mode && $edit_movie['rating'] == 'G') || (isset($_POST['rating']) && $_POST['rating'] == 'G') ? 'selected' : ''; ?>>G - General Audiences</option>
                        <option value="PG" style="background: #2c3e50; color: white;" <?php echo ($edit_mode && $edit_movie['rating'] == 'PG') || (isset($_POST['rating']) && $_POST['rating'] == 'PG') ? 'selected' : ''; ?>>PG - Parental Guidance</option>
                        <option value="PG-13" style="background: #2c3e50; color: white;" <?php echo ($edit_mode && $edit_movie['rating'] == 'PG-13') || (isset($_POST['rating']) && $_POST['rating'] == 'PG-13') ? 'selected' : ''; ?>>PG-13 - Parents Strongly Cautioned</option>
                        <option value="R" style="background: #2c3e50; color: white;" <?php echo ($edit_mode && $edit_movie['rating'] == 'R') || (isset($_POST['rating']) && $_POST['rating'] == 'R') ? 'selected' : ''; ?>>R - Restricted</option>
                        <option value="NC-17" style="background: #2c3e50; color: white;" <?php echo ($edit_mode && $edit_movie['rating'] == 'NC-17') || (isset($_POST['rating']) && $_POST['rating'] == 'NC-17') ? 'selected' : ''; ?>>NC-17 - Adults Only</option>
                    </select>
                </div>
            </div>

            <div style="margin-bottom: 30px;">
                <label style="display: block; color: white; font-weight: 600; margin-bottom: 10px; font-size: 1rem;">
                    <i class="fas fa-align-left"></i> Description *
                </label>
                <textarea id="description" name="description" rows="5" style="width: 100%; padding: 14px 16px; background: rgba(255, 255, 255, 0.08); border: 2px solid rgba(52, 152, 219, 0.3); border-radius: 10px; color: white; font-size: 1rem; resize: vertical;"
                          placeholder="Enter movie description" required><?php 
                    if ($edit_mode) {
                        echo htmlspecialchars($edit_movie['description']);
                    } elseif (isset($_POST['description'])) {
                        echo htmlspecialchars($_POST['description']);
                    }
                ?></textarea>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 25px; margin-bottom: 30px;">
                <div>
                    <label style="display: block; color: white; font-weight: 600; margin-bottom: 10px; font-size: 1rem;">
                        <i class="fas fa-image"></i> Poster Image URL
                    </label>
                    <input type="url" id="poster_url" name="poster_url" 
                           value="<?php echo $edit_mode ? htmlspecialchars($edit_movie['poster_url'] ?? '') : (isset($_POST['poster_url']) ? htmlspecialchars($_POST['poster_url']) : ''); ?>"
                           placeholder="https://example.com/poster.jpg"
                           style="width: 100%; padding: 14px 16px; background: rgba(255, 255, 255, 0.08); border: 2px solid rgba(52, 152, 219, 0.3); border-radius: 10px; color: white; font-size: 1rem;">
                    <?php if ($edit_mode && !empty($edit_movie['poster_url'])): ?>
                    <div style="margin-top: 8px;">
                        <img src="<?php echo $edit_movie['poster_url']; ?>" alt="Current Poster" style="max-height: 100px; border-radius: 5px;">
                    </div>
                    <?php endif; ?>
                </div>
                
                <div>
                    <label style="display: block; color: white; font-weight: 600; margin-bottom: 10px; font-size: 1rem;">
                        <i class="fas fa-video"></i> Trailer URL (YouTube)
                    </label>
                    <input type="url" id="trailer_url" name="trailer_url" 
                           value="<?php echo $edit_mode ? htmlspecialchars($edit_movie['trailer_url'] ?? '') : (isset($_POST['trailer_url']) ? htmlspecialchars($_POST['trailer_url']) : ''); ?>"
                           placeholder="https://www.youtube.com/watch?v=..."
                           style="width: 100%; padding: 14px 16px; background: rgba(255, 255, 255, 0.08); border: 2px solid rgba(52, 152, 219, 0.3); border-radius: 10px; color: white; font-size: 1rem;">
                </div>
            </div>
            
            <?php if ($edit_mode): ?>
            <div style="margin-bottom: 30px;">
                <label style="display: block; color: white; font-weight: 600; margin-bottom: 10px; font-size: 1rem;">
                    <i class="fas fa-toggle-on"></i> Status
                </label>
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                    <input type="checkbox" name="is_active" value="1" <?php echo $edit_movie['is_active'] ? 'checked' : ''; ?>>
                    <span style="color: white;">Active (available for scheduling)</span>
                </label>
            </div>
            <?php endif; ?>

            <!-- Screen Selection and Pricing -->
            <div style="background: rgba(52, 152, 219, 0.1); border-radius: 15px; padding: 25px; margin-bottom: 30px; border: 1px solid rgba(52, 152, 219, 0.3);">
                <h3 style="color: white; font-size: 1.4rem; margin-bottom: 20px; font-weight: 700; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-tv" style="color: #e74c3c;"></i> Select Screens & Set Prices *
                </h3>
                <p style="color: rgba(255, 255, 255, 0.7); margin-bottom: 20px; font-size: 0.95rem;">
                    Select all screens where this movie will be shown. Each screen can have different ticket prices.
                </p>
                
                <?php if (empty($screens)): ?>
                    <div style="background: rgba(241, 196, 15, 0.2); color: #f39c12; padding: 15px; border-radius: 10px; margin-bottom: 15px;">
                        <i class="fas fa-exclamation-triangle"></i> 
                        No screens available. Please <a href="<?php echo SITE_URL; ?>index.php?page=admin/manage-venues" style="color: #f39c12; text-decoration: underline;">add a venue and screen first</a> before adding movies.
                    </div>
                <?php else: ?>
                    <div id="screensContainer">
                        <?php foreach ($screens as $screen): 
                            $is_checked = false;
                            $standard_price = 350;
                            $premium_price = 450;
                            $sweet_spot_price = 550;
                            
                            if ($edit_mode && isset($edit_movie['selected_screens'])) {
                                $is_checked = in_array($screen['screen_id'], $edit_movie['selected_screens']);
                                if ($is_checked && isset($edit_movie['screen_prices'][$screen['screen_id']])) {
                                    $standard_price = $edit_movie['screen_prices'][$screen['screen_id']]['Standard'] ?? 350;
                                    $premium_price = $edit_movie['screen_prices'][$screen['screen_id']]['Premium'] ?? 450;
                                    $sweet_spot_price = $edit_movie['screen_prices'][$screen['screen_id']]['Sweet Spot'] ?? 550;
                                }
                            } elseif (isset($_POST['screen_ids'])) {
                                $is_checked = in_array($screen['screen_id'], $_POST['screen_ids']);
                                $standard_price = isset($_POST['standard_price_' . $screen['screen_id']]) ? floatval($_POST['standard_price_' . $screen['screen_id']]) : 350;
                                $premium_price = isset($_POST['premium_price_' . $screen['screen_id']]) ? floatval($_POST['premium_price_' . $screen['screen_id']]) : 450;
                                $sweet_spot_price = isset($_POST['sweet_spot_price_' . $screen['screen_id']]) ? floatval($_POST['sweet_spot_price_' . $screen['screen_id']]) : 550;
                            }
                        ?>
                        <div class="screen-item" data-screen-id="<?php echo $screen['screen_id']; ?>" style="margin-bottom: 20px; border: 1px solid <?php echo $is_checked ? '#3498db' : 'rgba(255,255,255,0.1)'; ?>; border-radius: 12px; padding: 15px; background: rgba(255,255,255,0.03); transition: all 0.3s ease;">
                            <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 15px; flex-wrap: wrap;">
                                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; flex: 2; min-width: 250px;">
                                    <input type="checkbox" name="screen_ids[]" value="<?php echo $screen['screen_id']; ?>" 
                                           class="screen-checkbox" data-screen-id="<?php echo $screen['screen_id']; ?>"
                                           <?php echo $is_checked ? 'checked' : ''; ?>
                                           style="width: 20px; height: 20px; cursor: pointer; accent-color: #3498db;">
                                    <div>
                                        <div style="color: white; font-weight: 700; font-size: 1.1rem;">
                                            <?php echo htmlspecialchars($screen['venue_name']); ?> - <?php echo htmlspecialchars($screen['screen_name']); ?>
                                        </div>
                                        <div style="color: rgba(255,255,255,0.5); font-size: 0.8rem;">
                                            Screen #<?php echo $screen['screen_number']; ?> | Capacity: <?php echo number_format($screen['capacity']); ?> seats
                                        </div>
                                    </div>
                                </label>
                            </div>
                            
                            <div class="screen-prices" style="display: <?php echo $is_checked ? 'grid' : 'none'; ?>; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-top: 15px; padding-top: 15px; border-top: 1px solid rgba(255,255,255,0.1);">
                                <div>
                                    <label style="display: block; color: #3498db; font-size: 0.85rem; font-weight: 600; margin-bottom: 5px;">
                                        <i class="fas fa-chair"></i> Standard Price (₱)
                                    </label>
                                    <input type="number" name="standard_price_<?php echo $screen['screen_id']; ?>" 
                                           value="<?php echo $standard_price; ?>" step="0.01" min="0"
                                           class="price-input" data-screen-id="<?php echo $screen['screen_id']; ?>"
                                           style="width: 100%; padding: 10px 12px; background: rgba(255,255,255,0.08); border: 2px solid #3498db; border-radius: 8px; color: white; font-size: 0.9rem;">
                                </div>
                                <div>
                                    <label style="display: block; color: #2ecc71; font-size: 0.85rem; font-weight: 600; margin-bottom: 5px;">
                                        <i class="fas fa-crown"></i> Premium Price (₱)
                                    </label>
                                    <input type="number" name="premium_price_<?php echo $screen['screen_id']; ?>" 
                                           value="<?php echo $premium_price; ?>" step="0.01" min="0"
                                           class="price-input" data-screen-id="<?php echo $screen['screen_id']; ?>"
                                           style="width: 100%; padding: 10px 12px; background: rgba(255,255,255,0.08); border: 2px solid #2ecc71; border-radius: 8px; color: white; font-size: 0.9rem;">
                                </div>
                                <div>
                                    <label style="display: block; color: #e74c3c; font-size: 0.85rem; font-weight: 600; margin-bottom: 5px;">
                                        <i class="fas fa-star"></i> Sweet Spot Price (₱)
                                    </label>
                                    <input type="number" name="sweet_spot_price_<?php echo $screen['screen_id']; ?>" 
                                           value="<?php echo $sweet_spot_price; ?>" step="0.01" min="0"
                                           class="price-input" data-screen-id="<?php echo $screen['screen_id']; ?>"
                                           style="width: 100%; padding: 10px 12px; background: rgba(255,255,255,0.08); border: 2px solid #e74c3c; border-radius: 8px; color: white; font-size: 0.9rem;">
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div style="margin-top: 15px;">
                        <a href="<?php echo SITE_URL; ?>index.php?page=admin/manage-venues" target="_blank" 
                           style="color: #3498db; text-decoration: none; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 5px;">
                            <i class="fas fa-plus-circle"></i> + Add New Venue/Screen
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            
            <div style="text-align: center; margin-top: 30px;">
                <?php if ($edit_mode): ?>
                <button type="submit" name="update_movie" style="padding: 16px 45px; background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; border: none; border-radius: 12px; font-size: 1.1rem; font-weight: 700; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 6px 20px rgba(52, 152, 219, 0.3); display: inline-flex; align-items: center; justify-content: center; gap: 10px;">
                    <i class="fas fa-save"></i> Update Movie
                </button>
                <a href="index.php?page=admin/manage-movies" style="padding: 16px 30px; background: rgba(255, 255, 255, 0.1); color: white; text-decoration: none; border-radius: 12px; font-size: 1.1rem; font-weight: 600; border: 2px solid rgba(52, 152, 219, 0.3); margin-left: 15px; display: inline-flex; align-items: center; justify-content: center; gap: 10px;">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <?php else: ?>
                <button type="submit" name="add_movie" style="padding: 16px 45px; background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; border: none; border-radius: 12px; font-size: 1.1rem; font-weight: 700; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 6px 20px rgba(52, 152, 219, 0.3); display: inline-flex; align-items: center; justify-content: center; gap: 10px;">
                    <i class="fas fa-plus"></i> Add Movie
                </button>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Movies List -->
    <div style="background: rgba(255, 255, 255, 0.05); border-radius: 15px; padding: 30px; border: 1px solid rgba(52, 152, 219, 0.2);">
        <h2 style="color: white; font-size: 1.8rem; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid #3498db; display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-film"></i> All Movies (<?php echo $movie_count; ?>)
        </h2>
        
        <?php if (empty($movies)): ?>
        <div style="text-align: center; padding: 50px; color: rgba(255, 255, 255, 0.6);">
            <i class="fas fa-film fa-3x" style="margin-bottom: 20px; opacity: 0.5;"></i>
            <p style="font-size: 1.1rem;">No movies found. Add your first movie!</p>
        </div>
        <?php else: ?>
        <div style="overflow-x: auto; border-radius: 10px; border: 1px solid rgba(52, 152, 219, 0.2);">
            <table style="width: 100%; border-collapse: collapse; min-width: 1200px;">
                <thead>
                    <tr style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);">
                        <th style="color: white; padding: 16px; text-align: left; font-weight: 700; font-size: 1rem;">ID</th>
                        <th style="color: white; padding: 16px; text-align: left; font-weight: 700; font-size: 1rem;">Poster</th>
                        <th style="color: white; padding: 16px; text-align: left; font-weight: 700; font-size: 1rem;">Movie Details</th>
                        <th style="color: white; padding: 16px; text-align: left; font-weight: 700; font-size: 1rem;">Director</th>
                        <th style="color: white; padding: 16px; text-align: left; font-weight: 700; font-size: 1rem;">Screens</th>
                        <th style="color: white; padding: 16px; text-align: left; font-weight: 700; font-size: 1rem;">Trailer</th>
                        <th style="color: white; padding: 16px; text-align: left; font-weight: 700; font-size: 1rem;">Status</th>
                        <th style="color: white; padding: 16px; text-align: left; font-weight: 700; font-size: 1rem;">Actions</th>
                    </td>
                </thead>
                <tbody>
                    <?php foreach ($movies as $movie): ?>
                    <tr style="border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
                        <td style="padding: 16px; color: rgba(255, 255, 255, 0.9); font-weight: 700;"><?php echo $movie['id']; ?></td>
                        <td style="padding: 16px;">
                            <?php if (!empty($movie['poster_url'])): ?>
                            <img src="<?php echo $movie['poster_url']; ?>" 
                                 alt="<?php echo htmlspecialchars($movie['title']); ?>"
                                 style="width: 70px; height: 100px; object-fit: cover; border-radius: 8px; border: 2px solid rgba(52, 152, 219, 0.3);">
                            <?php else: ?>
                            <div style="width: 70px; height: 100px; background: rgba(52, 152, 219, 0.1); border-radius: 8px; display: flex; align-items: center; justify-content: center; border: 2px solid rgba(52, 152, 219, 0.2);">
                                <i class="fas fa-film" style="color: rgba(52, 152, 219, 0.5); font-size: 1.8rem;"></i>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 16px;">
                            <div style="color: white; font-size: 1.1rem; font-weight: 700; margin-bottom: 8px;"><?php echo htmlspecialchars($movie['title']); ?></div>
                            <div style="margin-bottom: 8px;">
                                <span style="background: #3498db; color: white; padding: 4px 10px; border-radius: 4px; font-size: 0.85rem; font-weight: 700; margin-right: 5px;">
                                    <?php echo $movie['rating']; ?>
                                </span>
                                <span style="color: rgba(255, 255, 255, 0.8); font-size: 0.9rem;">
                                    <?php echo htmlspecialchars($movie['genre'] ?? ''); ?> • <?php echo $movie['duration']; ?>
                                </span>
                            </div>
                            <?php if (!empty($movie['description'])): ?>
                            <p style="font-size: 0.9rem; color: rgba(255, 255, 255, 0.7); margin-top: 8px; max-width: 400px;">
                                <?php echo substr(htmlspecialchars($movie['description']), 0, 120); ?>...
                            </p>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 16px; color: rgba(255, 255, 255, 0.8);">
                            <?php echo htmlspecialchars($movie['director'] ?? 'N/A'); ?>
                        </td>
                        <td style="padding: 16px;">
                            <?php if (!empty($movie['screen_names']) && $movie['screen_count'] > 0): ?>
                                <div style="font-size: 0.85rem; line-height: 1.5;">
                                    <?php echo $movie['screen_names']; ?>
                                </div>
                                <div style="color: rgba(255, 255, 255, 0.5); font-size: 0.7rem; margin-top: 5px;">
                                    <?php echo $movie['screen_count']; ?> screen(s)
                                </div>
                            <?php else: ?>
                                <span style="color: rgba(255, 255, 255, 0.5);">No screens assigned</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 16px;">
                            <?php if (!empty($movie['trailer_url'])): ?>
                            <a href="<?php echo htmlspecialchars($movie['trailer_url']); ?>" target="_blank" 
                               style="color: #3498db; text-decoration: none; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 8px; padding: 8px 14px; background: rgba(52, 152, 219, 0.1); border-radius: 6px; border: 1px solid rgba(52, 152, 219, 0.3);">
                                <i class="fas fa-play"></i> Watch
                            </a>
                            <?php else: ?>
                            <span style="color: rgba(255, 255, 255, 0.5); font-size: 0.9rem;">
                                <i class="fas fa-times-circle"></i> No Trailer
                            </span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 16px;">
                            <span style="background: <?php echo $movie['is_active'] ? 'rgba(46, 204, 113, 0.2)' : 'rgba(231, 76, 60, 0.2)'; ?>; 
                                  color: <?php echo $movie['is_active'] ? '#2ecc71' : '#e74c3c'; ?>; 
                                  padding: 5px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600;">
                                <i class="fas <?php echo $movie['is_active'] ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                                <?php echo $movie['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>
                        <td style="padding: 16px;">
                            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                <a href="index.php?page=admin/manage-movies&edit=<?php echo $movie['id']; ?>" 
                                   style="padding: 8px 16px; background: rgba(52, 152, 219, 0.2); color: #3498db; text-decoration: none; border-radius: 6px; font-size: 0.85rem; font-weight: 600; border: 1px solid rgba(52, 152, 219, 0.3); display: inline-flex; align-items: center; gap: 5px;">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <a href="index.php?page=admin/manage-movies&delete=<?php echo $movie['id']; ?>" 
                                   style="padding: 8px 16px; background: rgba(231, 76, 60, 0.2); color: #e74c3c; text-decoration: none; border-radius: 6px; font-size: 0.85rem; font-weight: 600; border: 1px solid rgba(231, 76, 60, 0.3); display: inline-flex; align-items: center; gap: 5px;"
                                   onclick="return confirm('Are you sure you want to delete \'<?php echo addslashes($movie['title']); ?>\'? This will soft delete the movie.')">
                                    <i class="fas fa-trash"></i> Delete
                                </a>
                            </div>
                         </div>
                        </td>
                     </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
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
        background: rgba(255, 255, 255, 0.03);
    }
    
    .screen-item {
        transition: all 0.3s ease;
    }
    
    .screen-item:hover {
        background: rgba(52, 152, 219, 0.1);
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
    }
</style>

<script>
document.getElementById('movieForm')?.addEventListener('submit', function(e) {
    const title = document.getElementById('title')?.value.trim();
    const genre = document.getElementById('genre')?.value.trim();
    const duration = document.getElementById('duration')?.value.trim();
    const rating = document.getElementById('rating')?.value;
    const description = document.getElementById('description')?.value.trim();
    const checkedScreens = document.querySelectorAll('.screen-checkbox:checked');
    
    if (!title || !genre || !duration || !rating || !description) {
        e.preventDefault();
        alert('Please fill in all required fields!');
        return false;
    }
    
    if (checkedScreens.length === 0) {
        e.preventDefault();
        alert('Please select at least one screen!');
        return false;
    }
    
    return true;
});

// Screen checkbox toggle
const screenCheckboxes = document.querySelectorAll('.screen-checkbox');
screenCheckboxes.forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        const screenItem = this.closest('.screen-item');
        const pricesDiv = screenItem.querySelector('.screen-prices');
        
        if (this.checked) {
            pricesDiv.style.display = 'grid';
            screenItem.style.borderColor = '#3498db';
        } else {
            pricesDiv.style.display = 'none';
            screenItem.style.borderColor = 'rgba(255,255,255,0.1)';
        }
    });
});

// Price input validation
const priceInputs = document.querySelectorAll('.price-input');
priceInputs.forEach(input => {
    input.addEventListener('change', function() {
        let value = parseFloat(this.value);
        if (isNaN(value) || value < 0) {
            this.value = 0;
        }
    });
});

// Auto-format duration input
const durationInput = document.getElementById('duration');
if (durationInput) {
    durationInput.addEventListener('blur', function() {
        let value = this.value.trim();
        if (value && !value.includes('min') && !value.includes('hour')) {
            if (value.includes('h')) {
                // Already has format like "2h 28min"
            } else if (value.match(/^\d+$/)) {
                this.value = value + ' min';
            }
        }
    });
}
</script>

<?php
if (isset($conn) && $conn) {
    $conn->close();
}
?>