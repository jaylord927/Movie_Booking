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
$edit_movie = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_movie'])) {
    $title = htmlspecialchars(trim($_POST['title']));
    $genre = htmlspecialchars(trim($_POST['genre']));
    $duration = htmlspecialchars(trim($_POST['duration']));
    $rating = htmlspecialchars(trim($_POST['rating']));
    $description = htmlspecialchars(trim($_POST['description']));
    $poster_url = htmlspecialchars(trim($_POST['poster_url'] ?? ''));
    $trailer_url = htmlspecialchars(trim($_POST['trailer_url'] ?? ''));
    $venue_name = htmlspecialchars(trim($_POST['venue_name'] ?? ''));
    $venue_location = htmlspecialchars(trim($_POST['venue_location'] ?? ''));
    $google_maps_link = htmlspecialchars(trim($_POST['google_maps_link'] ?? ''));
    $standard_price = floatval($_POST['standard_price'] ?? 350);
    $premium_price = floatval($_POST['premium_price'] ?? 450);
    $sweet_spot_price = floatval($_POST['sweet_spot_price'] ?? 550);
    
    if (empty($title) || empty($genre) || empty($duration) || empty($rating) || empty($description)) {
        $error = "All required fields must be filled!";
    } else {
        $check_stmt = $conn->prepare("SELECT id FROM movies WHERE title = ? AND is_active = 1");
        $check_stmt->bind_param("s", $title);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error = "Movie with this title already exists!";
        } else {
            $stmt = $conn->prepare("INSERT INTO movies (title, genre, duration, rating, description, poster_url, trailer_url, venue_name, venue_location, google_maps_link, standard_price, premium_price, sweet_spot_price, is_active, added_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)");
            $stmt->bind_param("ssssssssssdddi", $title, $genre, $duration, $rating, $description, $poster_url, $trailer_url, $venue_name, $venue_location, $google_maps_link, $standard_price, $premium_price, $sweet_spot_price, $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                $new_movie_id = $stmt->insert_id;
                $success = "Movie added successfully! ID: " . $new_movie_id;
                $_POST = array();
            } else {
                $error = "Failed to add movie: " . $conn->error;
            }
            
            $stmt->close();
        }
        
        $check_stmt->close();
    }
}

elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_movie'])) {
    $id = intval($_POST['id']);
    $title = htmlspecialchars(trim($_POST['title']));
    $genre = htmlspecialchars(trim($_POST['genre']));
    $duration = htmlspecialchars(trim($_POST['duration']));
    $rating = htmlspecialchars(trim($_POST['rating']));
    $description = htmlspecialchars(trim($_POST['description']));
    $poster_url = htmlspecialchars(trim($_POST['poster_url'] ?? ''));
    $trailer_url = htmlspecialchars(trim($_POST['trailer_url'] ?? ''));
    $venue_name = htmlspecialchars(trim($_POST['venue_name'] ?? ''));
    $venue_location = htmlspecialchars(trim($_POST['venue_location'] ?? ''));
    $google_maps_link = htmlspecialchars(trim($_POST['google_maps_link'] ?? ''));
    $standard_price = floatval($_POST['standard_price'] ?? 350);
    $premium_price = floatval($_POST['premium_price'] ?? 450);
    $sweet_spot_price = floatval($_POST['sweet_spot_price'] ?? 550);
    
    $stmt = $conn->prepare("UPDATE movies SET title = ?, genre = ?, duration = ?, rating = ?, description = ?, poster_url = ?, trailer_url = ?, venue_name = ?, venue_location = ?, google_maps_link = ?, standard_price = ?, premium_price = ?, sweet_spot_price = ?, updated_by = ? WHERE id = ?");
    $stmt->bind_param("ssssssssssddiii", $title, $genre, $duration, $rating, $description, $poster_url, $trailer_url, $venue_name, $venue_location, $google_maps_link, $standard_price, $premium_price, $sweet_spot_price, $_SESSION['user_id'], $id);
    
    if ($stmt->execute()) {
        $success = "Movie updated successfully!";
    } else {
        $error = "Failed to update movie: " . $stmt->error;
    }
    $stmt->close();
}

elseif (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    $stmt = $conn->prepare("UPDATE movies SET is_active = 0 WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $success = "Movie deleted successfully!";
    } else {
        $error = "Failed to delete movie: " . $stmt->error;
    }
    $stmt->close();
}

$movies_result = $conn->query("
    SELECT m.*, 
           a.u_name as added_by_name,
           u.u_name as updated_by_name
    FROM movies m
    LEFT JOIN users a ON m.added_by = a.u_id
    LEFT JOIN users u ON m.updated_by = u.u_id
    WHERE m.is_active = 1 
    ORDER BY m.created_at DESC
");

$movies = [];
if ($movies_result) {
    while ($row = $movies_result->fetch_assoc()) {
        $movies[] = $row;
    }
}

if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $conn->prepare("
        SELECT m.*, 
               a.u_name as added_by_name,
               u.u_name as updated_by_name
        FROM movies m
        LEFT JOIN users a ON m.added_by = a.u_id
        LEFT JOIN users u ON m.updated_by = u.u_id
        WHERE m.id = ? AND m.is_active = 1
    ");
    if ($stmt) {
        $stmt->bind_param("i", $edit_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $edit_movie = $result->fetch_assoc();
        $edit_mode = !empty($edit_movie);
        $stmt->close();
    }
}

$count_result = $conn->query("SELECT COUNT(*) as total FROM movies WHERE is_active = 1");
$movie_count = $count_result ? $count_result->fetch_assoc()['total'] : 0;

$conn->close();
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

    <div style="background: rgba(255, 255, 255, 0.05); border-radius: 15px; padding: 30px; margin-bottom: 40px; border: 1px solid rgba(52, 152, 219, 0.2);">
        <h2 style="color: white; font-size: 1.8rem; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid #3498db; display: flex; align-items: center; gap: 10px;">
            <i class="<?php echo $edit_mode ? 'fas fa-edit' : 'fas fa-plus-circle'; ?>"></i>
            <?php echo $edit_mode ? 'Edit Movie' : 'Add New Movie'; ?>
        </h2>
        
        <?php if ($edit_mode): ?>
        <div style="background: rgba(23, 162, 184, 0.2); color: #17a2b8; padding: 15px 20px; border-radius: 10px; margin-bottom: 25px; font-weight: 600; border: 1px solid rgba(23, 162, 184, 0.3);">
            <i class="fas fa-info-circle"></i> 
            Editing: <strong><?php echo htmlspecialchars($edit_movie['title']); ?></strong>
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
                           style="width: 100%; padding: 14px 16px; background: rgba(255, 255, 255, 0.08); border: 2px solid rgba(52, 152, 219, 0.3); border-radius: 10px; color: white; font-size: 1rem;"
                           placeholder="Enter movie title">
                </div>
                
                <div>
                    <label style="display: block; color: white; font-weight: 600; margin-bottom: 10px; font-size: 1rem;">
                        <i class="fas fa-tag"></i> Genre *
                    </label>
                    <input type="text" id="genre" name="genre" required
                           value="<?php echo $edit_mode ? htmlspecialchars($edit_movie['genre']) : (isset($_POST['genre']) ? htmlspecialchars($_POST['genre']) : ''); ?>"
                           style="width: 100%; padding: 14px 16px; background: rgba(255, 255, 255, 0.08); border: 2px solid rgba(52, 152, 219, 0.3); border-radius: 10px; color: white; font-size: 1rem;"
                           placeholder="e.g., Action, Comedy, Drama">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 25px; margin-bottom: 30px;">
                <div>
                    <label style="display: block; color: white; font-weight: 600; margin-bottom: 10px; font-size: 1rem;">
                        <i class="fas fa-clock"></i> Duration *
                    </label>
                    <input type="text" id="duration" name="duration" required
                           value="<?php echo $edit_mode ? htmlspecialchars($edit_movie['duration']) : (isset($_POST['duration']) ? htmlspecialchars($_POST['duration']) : ''); ?>"
                           style="width: 100%; padding: 14px 16px; background: rgba(255, 255, 255, 0.08); border: 2px solid rgba(52, 152, 219, 0.3); border-radius: 10px; color: white; font-size: 1rem;"
                           placeholder="e.g., 2h 15m">
                </div>
                
                <div>
                    <label style="display: block; color: white; font-weight: 600; margin-bottom: 10px; font-size: 1rem;">
                        <i class="fas fa-star"></i> Rating *
                    </label>
                    <select id="rating" name="rating" required style="width: 100%; padding: 14px 16px; background: rgba(255, 255, 255, 0.08); border: 2px solid rgba(52, 152, 219, 0.3); border-radius: 10px; color: white; font-size: 1rem; cursor: pointer; appearance: none; background-image: url('data:image/svg+xml;utf8,<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"20\" height=\"20\" fill=\"white\" viewBox=\"0 0 20 20\"><path d=\"M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z\"/></svg>'); background-repeat: no-repeat; background-position: right 16px center; background-size: 16px;">
                        <option value="">Select Rating</option>
                        <option value="G" <?php echo ($edit_mode && $edit_movie['rating'] == 'G') || (isset($_POST['rating']) && $_POST['rating'] == 'G') ? 'selected' : ''; ?>>G - General Audiences</option>
                        <option value="PG" <?php echo ($edit_mode && $edit_movie['rating'] == 'PG') || (isset($_POST['rating']) && $_POST['rating'] == 'PG') ? 'selected' : ''; ?>>PG - Parental Guidance</option>
                        <option value="PG-13" <?php echo ($edit_mode && $edit_movie['rating'] == 'PG-13') || (isset($_POST['rating']) && $_POST['rating'] == 'PG-13') ? 'selected' : ''; ?>>PG-13 - Parents Strongly Cautioned</option>
                        <option value="R" <?php echo ($edit_mode && $edit_movie['rating'] == 'R') || (isset($_POST['rating']) && $_POST['rating'] == 'R') ? 'selected' : ''; ?>>R - Restricted</option>
                        <option value="NC-17" <?php echo ($edit_mode && $edit_movie['rating'] == 'NC-17') || (isset($_POST['rating']) && $_POST['rating'] == 'NC-17') ? 'selected' : ''; ?>>NC-17 - Adults Only</option>
                    </select>
                </div>
            </div>

            <div style="margin-bottom: 30px;">
                <label style="display: block; color: white; font-weight: 600; margin-bottom: 10px; font-size: 1rem;">
                    <i class="fas fa-align-left"></i> Description *
                </label>
                <textarea id="description" name="description" rows="5" style="width: 100%; padding: 14px 16px; background: rgba(255, 255, 255, 0.08); border: 2px solid rgba(52, 152, 219, 0.3); border-radius: 10px; color: white; font-size: 1rem; resize: vertical;"
                          placeholder="Enter movie description" required><?php echo $edit_mode ? htmlspecialchars($edit_movie['description']) : (isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''); ?></textarea>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 25px; margin-bottom: 30px;">
                <div>
                    <label style="display: block; color: white; font-weight: 600; margin-bottom: 10px; font-size: 1rem;">
                        <i class="fas fa-image"></i> Poster Image URL
                    </label>
                    <input type="url" id="poster_url" name="poster_url" 
                           value="<?php echo $edit_mode ? htmlspecialchars($edit_movie['poster_url'] ?? '') : (isset($_POST['poster_url']) ? htmlspecialchars($_POST['poster_url']) : ''); ?>"
                           style="width: 100%; padding: 14px 16px; background: rgba(255, 255, 255, 0.08); border: 2px solid rgba(52, 152, 219, 0.3); border-radius: 10px; color: white; font-size: 1rem;"
                           placeholder="https://example.com/image.jpg">
                </div>
                
                <div>
                    <label style="display: block; color: white; font-weight: 600; margin-bottom: 10px; font-size: 1rem;">
                        <i class="fas fa-video"></i> Trailer URL (YouTube)
                    </label>
                    <input type="url" id="trailer_url" name="trailer_url" 
                           value="<?php echo $edit_mode ? htmlspecialchars($edit_movie['trailer_url'] ?? '') : (isset($_POST['trailer_url']) ? htmlspecialchars($_POST['trailer_url']) : ''); ?>"
                           style="width: 100%; padding: 14px 16px; background: rgba(255, 255, 255, 0.08); border: 2px solid rgba(52, 152, 219, 0.3); border-radius: 10px; color: white; font-size: 1rem;"
                           placeholder="https://youtube.com/watch?v=...">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 25px; margin-bottom: 30px;">
                <div>
                    <label style="display: block; color: white; font-weight: 600; margin-bottom: 10px; font-size: 1rem;">
                        <i class="fas fa-building"></i> Venue Name
                    </label>
                    <input type="text" id="venue_name" name="venue_name" 
                           value="<?php echo $edit_mode ? htmlspecialchars($edit_movie['venue_name'] ?? '') : (isset($_POST['venue_name']) ? htmlspecialchars($_POST['venue_name']) : ''); ?>"
                           style="width: 100%; padding: 14px 16px; background: rgba(255, 255, 255, 0.08); border: 2px solid rgba(52, 152, 219, 0.3); border-radius: 10px; color: white; font-size: 1rem;"
                           placeholder="e.g., SM Cinema, Ayala Malls Cinemas">
                </div>
                
                <div>
                    <label style="display: block; color: white; font-weight: 600; margin-bottom: 10px; font-size: 1rem;">
                        <i class="fas fa-map-marker-alt"></i> Venue Location
                    </label>
                    <input type="text" id="venue_location" name="venue_location" 
                           value="<?php echo $edit_mode ? htmlspecialchars($edit_movie['venue_location'] ?? '') : (isset($_POST['venue_location']) ? htmlspecialchars($_POST['venue_location']) : ''); ?>"
                           style="width: 100%; padding: 14px 16px; background: rgba(255, 255, 255, 0.08); border: 2px solid rgba(52, 152, 219, 0.3); border-radius: 10px; color: white; font-size: 1rem;"
                           placeholder="e.g., SM City Cebu, North Wing, 3rd Floor">
                </div>
            </div>

            <div style="margin-bottom: 30px;">
                <label style="display: block; color: white; font-weight: 600; margin-bottom: 10px; font-size: 1rem;">
                    <i class="fas fa-map"></i> Google Maps Link
                </label>
                <input type="url" id="google_maps_link" name="google_maps_link" 
                       value="<?php echo $edit_mode ? htmlspecialchars($edit_movie['google_maps_link'] ?? '') : (isset($_POST['google_maps_link']) ? htmlspecialchars($_POST['google_maps_link']) : ''); ?>"
                       style="width: 100%; padding: 14px 16px; background: rgba(255, 255, 255, 0.08); border: 2px solid rgba(52, 152, 219, 0.3); border-radius: 10px; color: white; font-size: 1rem;"
                       placeholder="https://maps.google.com/?q=10.273055307646723,123.7611768131498">
                <div style="color: rgba(255, 255, 255, 0.6); font-size: 0.9rem; margin-top: 5px;">
                    <i class="fas fa-info-circle"></i> Example: https://maps.google.com/?q=10.273055307646723,123.7611768131498
                </div>
            </div>

            <div style="margin-bottom: 30px;">
                <h3 style="color: white; font-size: 1.3rem; margin-bottom: 20px; font-weight: 700; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-tags"></i> Seat Pricing
                </h3>
                <p style="color: rgba(255, 255, 255, 0.7); margin-bottom: 20px; font-size: 0.95rem;">
                    Set custom prices for each seat type. Leave default values if no changes needed.
                </p>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 25px;">
                    <div>
                        <label style="display: block; color: white; font-weight: 600; margin-bottom: 10px; font-size: 1rem;">
                            <i class="fas fa-chair" style="color: #3498db;"></i> Standard Price (₱)
                        </label>
                        <input type="number" id="standard_price" name="standard_price" step="0.01" min="0" required
                               value="<?php echo $edit_mode ? ($edit_movie['standard_price'] ?? 350) : (isset($_POST['standard_price']) ? $_POST['standard_price'] : 350); ?>"
                               style="width: 100%; padding: 14px 16px; background: rgba(255, 255, 255, 0.08); border: 2px solid #3498db; border-radius: 10px; color: white; font-size: 1rem;"
                               placeholder="350.00">
                        <div style="color: #3498db; font-size: 0.85rem; margin-top: 5px;">Default: ₱350.00</div>
                    </div>
                    
                    <div>
                        <label style="display: block; color: white; font-weight: 600; margin-bottom: 10px; font-size: 1rem;">
                            <i class="fas fa-crown" style="color: #2ecc71;"></i> Premium Price (₱)
                        </label>
                        <input type="number" id="premium_price" name="premium_price" step="0.01" min="0" required
                               value="<?php echo $edit_mode ? ($edit_movie['premium_price'] ?? 450) : (isset($_POST['premium_price']) ? $_POST['premium_price'] : 450); ?>"
                               style="width: 100%; padding: 14px 16px; background: rgba(255, 255, 255, 0.08); border: 2px solid #2ecc71; border-radius: 10px; color: white; font-size: 1rem;"
                               placeholder="450.00">
                        <div style="color: #2ecc71; font-size: 0.85rem; margin-top: 5px;">Default: ₱450.00</div>
                    </div>
                    
                    <div>
                        <label style="display: block; color: white; font-weight: 600; margin-bottom: 10px; font-size: 1rem;">
                            <i class="fas fa-star" style="color: #e74c3c;"></i> Sweet Spot Price (₱)
                        </label>
                        <input type="number" id="sweet_spot_price" name="sweet_spot_price" step="0.01" min="0" required
                               value="<?php echo $edit_mode ? ($edit_movie['sweet_spot_price'] ?? 550) : (isset($_POST['sweet_spot_price']) ? $_POST['sweet_spot_price'] : 550); ?>"
                               style="width: 100%; padding: 14px 16px; background: rgba(255, 255, 255, 0.08); border: 2px solid #e74c3c; border-radius: 10px; color: white; font-size: 1rem;"
                               placeholder="550.00">
                        <div style="color: #e74c3c; font-size: 0.85rem; margin-top: 5px;">Default: ₱550.00</div>
                    </div>
                </div>
            </div>
            
            <div style="text-align: center; margin-top: 30px;">
                <?php if ($edit_mode): ?>
                <button type="submit" name="update_movie" style="padding: 16px 45px; background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; border: none; border-radius: 12px; font-size: 1.1rem; font-weight: 700; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 6px 20px rgba(52, 152, 219, 0.3); display: inline-flex; align-items: center; justify-content: center; gap: 10px;">
                    <i class="fas fa-save"></i> Update Movie
                </button>
                <a href="index.php?page=admin/manage-movies" style="padding: 16px 30px; background: rgba(255, 255, 255, 0.1); color: white; text-decoration: none; border-radius: 12px; font-size: 1.1rem; font-weight: 600; border: 2px solid rgba(52, 152, 219, 0.3); margin-left: 15px; display: inline-flex; align-items: center; justify-content: center; gap: 10px;">
                    <i class="fas fa-times"></i> Cancel
                </a>
                <?php else: ?>
                <button type="submit" name="add_movie" style="padding: 16px 45px; background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; border: none; border-radius: 12px; font-size: 1.1rem; font-weight: 700; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 6px 20px rgba(52, 152, 219, 0.3); display: inline-flex; align-items: center; justify-content: center; gap: 10px;">
                    <i class="fas fa-plus"></i> Add Movie
                </button>
                <?php endif; ?>
            </div>
        </form>
    </div>

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
            <table style="width: 100%; border-collapse: collapse; min-width: 1400px;">
                <thead>
                    <tr>
                        <th style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; padding: 16px; text-align: left; font-weight: 700; font-size: 1rem;">ID</th>
                        <th style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; padding: 16px; text-align: left; font-weight: 700; font-size: 1rem;">Poster</th>
                        <th style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; padding: 16px; text-align: left; font-weight: 700; font-size: 1rem;">Movie Details</th>
                        <th style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; padding: 16px; text-align: left; font-weight: 700; font-size: 1rem;">Seat Prices</th>
                        <th style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; padding: 16px; text-align: left; font-weight: 700; font-size: 1rem;">Venue</th>
                        <th style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; padding: 16px; text-align: left; font-weight: 700; font-size: 1rem;">Google Maps</th>
                        <th style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; padding: 16px; text-align: left; font-weight: 700; font-size: 1rem;">Trailer</th>
                        <th style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; padding: 16px; text-align: left; font-weight: 700; font-size: 1rem;">Admin Info</th>
                        <th style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; padding: 16px; text-align: left; font-weight: 700; font-size: 1rem;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($movies as $movie): ?>
                    <tr style="border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
                        <td style="padding: 16px; color: rgba(255, 255, 255, 0.9); font-weight: 700;"><?php echo $movie['id']; ?></td>
                        <td style="padding: 16px;">
                            <?php if (!empty($movie['poster_url'])): ?>
                            <img src="<?php echo $movie['poster_url']; ?>" 
                                 alt="<?php echo htmlspecialchars($movie['title']); ?>"
                                 style="width: 70px; height: 100px; object-fit: cover; border-radius: 8px; border: 2px solid rgba(52, 152, 219, 0.3);"
                                 onerror="this.src='data:image/svg+xml,<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 70 100\"><rect width=\"70\" height=\"100\" fill=\"%232c3e50\"/><text x=\"50%\" y=\"50%\" font-family=\"Arial\" font-size=\"14\" fill=\"%23ecf0f1\" text-anchor=\"middle\" dy=\".3em\">No Image</text></svg>'">
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
                                    <?php echo $movie['genre']; ?> • <?php echo $movie['duration']; ?>
                                </span>
                            </div>
                            <?php if (!empty($movie['description'])): ?>
                            <p style="font-size: 0.9rem; color: rgba(255, 255, 255, 0.7); margin-top: 8px; max-width: 400px;">
                                <?php echo substr(htmlspecialchars($movie['description']), 0, 120); ?>...
                            </p>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 16px;">
                            <div style="background: rgba(52, 152, 219, 0.1); padding: 12px; border-radius: 8px;">
                                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                                    <i class="fas fa-chair" style="color: #3498db; width: 20px;"></i>
                                    <span style="color: rgba(255,255,255,0.8);">Standard:</span>
                                    <span style="color: white; font-weight: 700; margin-left: auto;">₱<?php echo number_format($movie['standard_price'] ?? 350, 2); ?></span>
                                </div>
                                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                                    <i class="fas fa-crown" style="color: #2ecc71; width: 20px;"></i>
                                    <span style="color: rgba(255,255,255,0.8);">Premium:</span>
                                    <span style="color: white; font-weight: 700; margin-left: auto;">₱<?php echo number_format($movie['premium_price'] ?? 450, 2); ?></span>
                                </div>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <i class="fas fa-star" style="color: #e74c3c; width: 20px;"></i>
                                    <span style="color: rgba(255,255,255,0.8);">Sweet Spot:</span>
                                    <span style="color: white; font-weight: 700; margin-left: auto;">₱<?php echo number_format($movie['sweet_spot_price'] ?? 550, 2); ?></span>
                                </div>
                            </div>
                        </td>
                        <td style="padding: 16px;">
                            <?php if (!empty($movie['venue_name']) || !empty($movie['venue_location'])): ?>
                                <div style="color: white; font-weight: 600; margin-bottom: 5px;">
                                    <?php echo htmlspecialchars($movie['venue_name'] ?? 'N/A'); ?>
                                </div>
                                <div style="color: rgba(255, 255, 255, 0.7); font-size: 0.85rem;">
                                    <?php echo htmlspecialchars($movie['venue_location'] ?? ''); ?>
                                </div>
                            <?php else: ?>
                                <span style="color: rgba(255, 255, 255, 0.5);">Not specified</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 16px;">
                            <?php if (!empty($movie['google_maps_link'])): ?>
                            <a href="<?php echo htmlspecialchars($movie['google_maps_link']); ?>" target="_blank" 
                               style="color: #3498db; text-decoration: none; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 8px; padding: 8px 14px; background: rgba(52, 152, 219, 0.1); border-radius: 6px; border: 1px solid rgba(52, 152, 219, 0.3);">
                                <i class="fas fa-map-marker-alt"></i> View Map
                            </a>
                            <?php else: ?>
                            <span style="color: rgba(255, 255, 255, 0.5); font-size: 0.9rem;">
                                <i class="fas fa-times-circle"></i> No Map
                            </span>
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
                            <div style="font-size: 0.9rem; color: rgba(255, 255, 255, 0.8);">
                                <div style="margin-bottom: 5px;"><strong>Added by:</strong> <?php echo $movie['added_by_name'] ?? 'Unknown'; ?></div>
                                <div><strong>Date:</strong> <?php echo date('M d, Y', strtotime($movie['created_at'])); ?></div>
                            </div>
                        </td>
                        <td style="padding: 16px;">
                            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                <a href="index.php?page=admin/manage-movies&edit=<?php echo $movie['id']; ?>" 
                                   style="padding: 8px 16px; background: rgba(52, 152, 219, 0.2); color: #3498db; text-decoration: none; border-radius: 6px; font-size: 0.85rem; font-weight: 600; border: 1px solid rgba(52, 152, 219, 0.3); display: inline-flex; align-items: center; gap: 5px;">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <a href="index.php?page=admin/manage-movies&delete=<?php echo $movie['id']; ?>" 
                                   style="padding: 8px 16px; background: rgba(231, 76, 60, 0.2); color: #e74c3c; text-decoration: none; border-radius: 6px; font-size: 0.85rem; font-weight: 600; border: 1px solid rgba(231, 76, 60, 0.3); display: inline-flex; align-items: center; gap: 5px;"
                                   onclick="return confirm('Are you sure you want to delete \'<?php echo addslashes($movie['title']); ?>\'?')">
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
    }
</style>

<script>
document.getElementById('movieForm')?.addEventListener('submit', function(e) {
    const title = document.getElementById('title').value.trim();
    const genre = document.getElementById('genre').value.trim();
    const duration = document.getElementById('duration').value.trim();
    const rating = document.getElementById('rating').value;
    const description = document.getElementById('description').value.trim();
    
    if (!title || !genre || !duration || !rating || !description) {
        e.preventDefault();
        alert('Please fill in all required fields!');
        return false;
    }
    
    return true;
});

const inputs = document.querySelectorAll('input, select, textarea');
inputs.forEach(input => {
    input.addEventListener('focus', function() {
        this.style.transition = 'all 0.3s ease';
    });
    
    input.addEventListener('blur', function() {
        this.style.transition = 'none';
    });
});
</script>

</div>
</body>
</html>