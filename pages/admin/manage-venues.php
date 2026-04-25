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
$edit_venue = null;

// Create uploads/venue directory if it doesn't exist
$venue_upload_dir = $root_dir . "/uploads/venue/";
if (!file_exists($venue_upload_dir)) {
    mkdir($venue_upload_dir, 0777, true);
}

// ============================================
// ADD VENUE
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_venue'])) {
    $venue_name = htmlspecialchars(trim($_POST['venue_name']), ENT_QUOTES, 'UTF-8');
    $venue_location = htmlspecialchars(trim($_POST['venue_location']), ENT_QUOTES, 'UTF-8');
    $google_maps_link = trim($_POST['google_maps_link'] ?? '');
    
    // Handle venue photo upload
    $venue_photo_path = '';
    if (isset($_FILES['venue_photo']) && $_FILES['venue_photo']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp'];
        $file_type = $_FILES['venue_photo']['type'];
        
        if (!in_array($file_type, $allowed_types)) {
            $error = "Only JPG, PNG, GIF, and WEBP files are allowed for venue photo.";
        } elseif ($_FILES['venue_photo']['size'] > 5000000) {
            $error = "Venue photo file size must be less than 5MB.";
        } else {
            $extension = pathinfo($_FILES['venue_photo']['name'], PATHINFO_EXTENSION);
            $filename = 'venue_' . time() . '_' . rand(1000, 9999) . '.' . $extension;
            $target_file = $venue_upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['venue_photo']['tmp_name'], $target_file)) {
                $venue_photo_path = 'uploads/venue/' . $filename;
            } else {
                $error = "Failed to upload venue photo. Please try again.";
            }
        }
    }
    
    if (empty($venue_name) || empty($venue_location)) {
        $error = "Venue name and location are required!";
    } else {
        // Check if venue already exists
        $check_stmt = $conn->prepare("SELECT id FROM venues WHERE venue_name = ?");
        $check_stmt->bind_param("s", $venue_name);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error = "A venue with this name already exists!";
        } else {
            $stmt = $conn->prepare("INSERT INTO venues (venue_name, venue_location, google_maps_link, venue_photo_path) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $venue_name, $venue_location, $google_maps_link, $venue_photo_path);
            
            if ($stmt->execute()) {
                $new_venue_id = $stmt->insert_id;
                $success = "Venue added successfully!";
                $_POST = array();
            } else {
                $error = "Failed to add venue: " . $conn->error;
            }
            $stmt->close();
        }
        $check_stmt->close();
    }
}

// ============================================
// UPDATE VENUE
// ============================================
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_venue'])) {
    $id = intval($_POST['id']);
    $venue_name = htmlspecialchars(trim($_POST['venue_name']), ENT_QUOTES, 'UTF-8');
    $venue_location = htmlspecialchars(trim($_POST['venue_location']), ENT_QUOTES, 'UTF-8');
    $google_maps_link = trim($_POST['google_maps_link'] ?? '');
    
    // Check if venue is used by any movies
    $movie_check = $conn->prepare("SELECT COUNT(*) as count FROM movies WHERE venue_id = ?");
    $movie_check->bind_param("i", $id);
    $movie_check->execute();
    $movie_result = $movie_check->get_result();
    $movie_count = $movie_result->fetch_assoc()['count'];
    $movie_check->close();
    
    // Handle venue photo upload for update
    $venue_photo_path = '';
    $has_new_photo = false;
    
    if (isset($_FILES['venue_photo']) && $_FILES['venue_photo']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp'];
        $file_type = $_FILES['venue_photo']['type'];
        
        if (!in_array($file_type, $allowed_types)) {
            $error = "Only JPG, PNG, GIF, and WEBP files are allowed for venue photo.";
        } elseif ($_FILES['venue_photo']['size'] > 5000000) {
            $error = "Venue photo file size must be less than 5MB.";
        } else {
            $extension = pathinfo($_FILES['venue_photo']['name'], PATHINFO_EXTENSION);
            $filename = 'venue_' . time() . '_' . rand(1000, 9999) . '.' . $extension;
            $target_file = $venue_upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['venue_photo']['tmp_name'], $target_file)) {
                $venue_photo_path = 'uploads/venue/' . $filename;
                $has_new_photo = true;
            } else {
                $error = "Failed to upload venue photo. Please try again.";
            }
        }
    }
    
    // Remove photo if checkbox is checked
    $remove_photo = isset($_POST['remove_photo']) && $_POST['remove_photo'] == '1';
    
    if (empty($error)) {
        // Get old photo path if we need to delete it
        if ($has_new_photo || $remove_photo) {
            $old_photo_stmt = $conn->prepare("SELECT venue_photo_path FROM venues WHERE id = ?");
            $old_photo_stmt->bind_param("i", $id);
            $old_photo_stmt->execute();
            $old_photo_result = $old_photo_stmt->get_result();
            $old_photo_data = $old_photo_result->fetch_assoc();
            $old_photo_stmt->close();
            
            if ($old_photo_data && !empty($old_photo_data['venue_photo_path']) && file_exists($root_dir . '/' . $old_photo_data['venue_photo_path'])) {
                unlink($root_dir . '/' . $old_photo_data['venue_photo_path']);
            }
        }
        
        if ($has_new_photo) {
            $stmt = $conn->prepare("UPDATE venues SET venue_name = ?, venue_location = ?, google_maps_link = ?, venue_photo_path = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $venue_name, $venue_location, $google_maps_link, $venue_photo_path, $id);
        } elseif ($remove_photo) {
            $stmt = $conn->prepare("UPDATE venues SET venue_name = ?, venue_location = ?, google_maps_link = ?, venue_photo_path = NULL WHERE id = ?");
            $stmt->bind_param("sssi", $venue_name, $venue_location, $google_maps_link, $id);
        } else {
            $stmt = $conn->prepare("UPDATE venues SET venue_name = ?, venue_location = ?, google_maps_link = ? WHERE id = ?");
            $stmt->bind_param("sssi", $venue_name, $venue_location, $google_maps_link, $id);
        }
        
        if ($stmt->execute()) {
            $success = "Venue updated successfully!";
            if ($movie_count > 0) {
                $success .= " Note: This venue is used by $movie_count movie(s).";
            }
        } else {
            $error = "Failed to update venue: " . $stmt->error;
        }
        $stmt->close();
    }
}

// ============================================
// DELETE VENUE
// ============================================
elseif (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    // Check if venue is used by any movies
    $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM movies WHERE venue_id = ? AND is_active = 1");
    $check_stmt->bind_param("i", $id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $movie_count = $check_result->fetch_assoc()['count'];
    $check_stmt->close();
    
    if ($movie_count > 0) {
        $error = "Cannot delete this venue because it is used by $movie_count movie(s). Please reassign those movies first.";
    } else {
        // Get photo path to delete the file
        $photo_stmt = $conn->prepare("SELECT venue_photo_path FROM venues WHERE id = ?");
        $photo_stmt->bind_param("i", $id);
        $photo_stmt->execute();
        $photo_result = $photo_stmt->get_result();
        $photo_data = $photo_result->fetch_assoc();
        $photo_stmt->close();
        
        if ($photo_data && !empty($photo_data['venue_photo_path']) && file_exists($root_dir . '/' . $photo_data['venue_photo_path'])) {
            unlink($root_dir . '/' . $photo_data['venue_photo_path']);
        }
        
        $stmt = $conn->prepare("DELETE FROM venues WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $success = "Venue deleted successfully!";
        } else {
            $error = "Failed to delete venue: " . $stmt->error;
        }
        $stmt->close();
    }
}

// ============================================
// GET EDIT VENUE DATA
// ============================================
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM venues WHERE id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_venue = $result->fetch_assoc();
    $edit_mode = !empty($edit_venue);
    $stmt->close();
}

// ============================================
// FETCH ALL VENUES
// ============================================
$venues_result = $conn->query("
    SELECT v.*, 
           COUNT(m.id) as movie_count 
    FROM venues v
    LEFT JOIN movies m ON v.id = m.venue_id AND m.is_active = 1
    GROUP BY v.id
    ORDER BY v.venue_name
");

$venues = [];
if ($venues_result) {
    while ($row = $venues_result->fetch_assoc()) {
        $venues[] = $row;
    }
}

$venue_count = count($venues);
$conn->close();
?>

<div class="admin-content" style="max-width: 1400px; margin: 0 auto; padding: 30px;">
    <div style="text-align: center; margin-bottom: 40px; padding: 30px; background: linear-gradient(135deg, rgba(52, 152, 219, 0.1), rgba(41, 128, 185, 0.2)); border-radius: 20px; border: 2px solid rgba(52, 152, 219, 0.3);">
        <h1 style="color: white; font-size: 2.5rem; margin-bottom: 15px; font-weight: 800;">Manage Venues</h1>
        <p style="color: rgba(255, 255, 255, 0.8); font-size: 1.1rem;">Add, edit, or remove cinema venues</p>
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

    <!-- Add/Edit Venue Form -->
    <div style="background: rgba(255, 255, 255, 0.05); border-radius: 15px; padding: 30px; margin-bottom: 40px; border: 1px solid rgba(52, 152, 219, 0.2);">
        <h2 style="color: white; font-size: 1.8rem; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid #3498db; display: flex; align-items: center; gap: 10px;">
            <i class="<?php echo $edit_mode ? 'fas fa-edit' : 'fas fa-plus-circle'; ?>"></i>
            <?php echo $edit_mode ? 'Edit Venue' : 'Add New Venue'; ?>
        </h2>
        
        <?php if ($edit_mode): ?>
        <div style="background: rgba(23, 162, 184, 0.2); color: #17a2b8; padding: 15px 20px; border-radius: 10px; margin-bottom: 25px; font-weight: 600; border: 1px solid rgba(23, 162, 184, 0.3);">
            <i class="fas fa-info-circle"></i> 
            Editing venue: <strong><?php echo htmlspecialchars($edit_venue['venue_name']); ?></strong>
        </div>
        <?php endif; ?>
        
        <form method="POST" action="" enctype="multipart/form-data" id="venueForm">
            <?php if ($edit_mode): ?>
            <input type="hidden" name="id" value="<?php echo $edit_venue['id']; ?>">
            <?php endif; ?>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 25px; margin-bottom: 30px;">
                <div>
                    <label style="display: block; color: white; font-weight: 600; margin-bottom: 10px; font-size: 1rem;">
                        <i class="fas fa-building"></i> Venue Name *
                    </label>
                    <input type="text" id="venue_name" name="venue_name" required 
                           value="<?php echo $edit_mode ? htmlspecialchars($edit_venue['venue_name'], ENT_QUOTES, 'UTF-8') : (isset($_POST['venue_name']) ? htmlspecialchars($_POST['venue_name'], ENT_QUOTES, 'UTF-8') : ''); ?>"
                           style="width: 100%; padding: 14px 16px; background: rgba(255, 255, 255, 0.08); border: 2px solid rgba(52, 152, 219, 0.3); border-radius: 10px; color: white; font-size: 1rem;"
                           placeholder="e.g., SM Cinema Cebu">
                </div>
                
                <div>
                    <label style="display: block; color: white; font-weight: 600; margin-bottom: 10px; font-size: 1rem;">
                        <i class="fas fa-map-marker-alt"></i> Venue Location *
                    </label>
                    <input type="text" id="venue_location" name="venue_location" required 
                           value="<?php echo $edit_mode ? htmlspecialchars($edit_venue['venue_location'], ENT_QUOTES, 'UTF-8') : (isset($_POST['venue_location']) ? htmlspecialchars($_POST['venue_location'], ENT_QUOTES, 'UTF-8') : ''); ?>"
                           style="width: 100%; padding: 14px 16px; background: rgba(255, 255, 255, 0.08); border: 2px solid rgba(52, 152, 219, 0.3); border-radius: 10px; color: white; font-size: 1rem;"
                           placeholder="e.g., SM City Cebu, North Wing, 3rd Floor">
                </div>
            </div>

            <div style="margin-bottom: 30px;">
                <label style="display: block; color: white; font-weight: 600; margin-bottom: 10px; font-size: 1rem;">
                    <i class="fas fa-map"></i> Google Maps Link
                </label>
                <input type="text" id="google_maps_link" name="google_maps_link" 
                       value="<?php echo $edit_mode ? ($edit_venue['google_maps_link'] ?? '') : (isset($_POST['google_maps_link']) ? $_POST['google_maps_link'] : ''); ?>"
                       style="width: 100%; padding: 14px 16px; background: rgba(255, 255, 255, 0.08); border: 2px solid rgba(52, 152, 219, 0.3); border-radius: 10px; color: white; font-size: 1rem;"
                       placeholder="https://www.google.com/maps/@10.2701001,123.7749591,3a,75y...">
                <div style="margin-top: 8px; font-size: 0.85rem; color: rgba(255,255,255,0.6);">
                    <i class="fas fa-info-circle"></i> 
                    Paste the full Google Maps link here. Customers will be able to view the location.
                </div>
            </div>

            <!-- Venue Photo Upload Section -->
            <div style="margin-bottom: 30px;">
                <label style="display: block; color: white; font-weight: 600; margin-bottom: 10px; font-size: 1rem;">
                    <i class="fas fa-camera"></i> Venue Photo
                </label>
                
                <?php if ($edit_mode && !empty($edit_venue['venue_photo_path'])): ?>
                <div style="margin-bottom: 15px; padding: 15px; background: rgba(0,0,0,0.3); border-radius: 10px;">
                    <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                        <img src="<?php echo SITE_URL . $edit_venue['venue_photo_path']; ?>" 
                             alt="Current Venue Photo"
                             style="max-width: 200px; max-height: 150px; border-radius: 8px; border: 2px solid rgba(52,152,219,0.3);">
                        <div>
                            <p style="color: rgba(255,255,255,0.8); margin-bottom: 5px;">
                                <i class="fas fa-image"></i> Current venue photo
                            </p>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin-top: 8px;">
                                <input type="checkbox" name="remove_photo" value="1" 
                                       style="width: 18px; height: 18px; accent-color: #e74c3c;">
                                <span style="color: #ff9999;">Remove current photo</span>
                            </label>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div style="border: 2px dashed rgba(52,152,219,0.3); border-radius: 10px; padding: 25px; text-align: center; background: rgba(255, 255, 255, 0.02);">
                    <i class="fas fa-cloud-upload-alt" style="font-size: 2.5rem; color: var(--pale-red); margin-bottom: 10px;"></i>
                    <p style="color: white; margin-bottom: 10px;">Upload venue photo (optional)</p>
                    <p style="color: rgba(255,255,255,0.6); font-size: 0.85rem; margin-bottom: 15px;">JPG, PNG, GIF, WEBP (Max 5MB)</p>
                    <input type="file" name="venue_photo" accept="image/*" style="display: none;" id="venuePhotoInput">
                    <button type="button" onclick="document.getElementById('venuePhotoInput').click()" class="btn btn-secondary" style="padding: 10px 20px;">
                        <i class="fas fa-folder-open"></i> Choose Photo
                    </button>
                    <div id="venuePhotoName" style="margin-top: 10px; color: #2ecc71; font-size: 0.9rem;"></div>
                </div>
                <div style="margin-top: 8px; font-size: 0.85rem; color: rgba(255,255,255,0.5);">
                    <i class="fas fa-info-circle"></i> 
                    This photo will be displayed to customers on the venue page
                </div>
            </div>
            
            <div style="text-align: center; margin-top: 30px;">
                <?php if ($edit_mode): ?>
                <button type="submit" name="update_venue" style="padding: 16px 45px; background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; border: none; border-radius: 12px; font-size: 1.1rem; font-weight: 700; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 6px 20px rgba(52, 152, 219, 0.3); display: inline-flex; align-items: center; justify-content: center; gap: 10px;">
                    <i class="fas fa-save"></i> Update Venue
                </button>
                <a href="index.php?page=admin/manage-venues" style="padding: 16px 30px; background: rgba(255, 255, 255, 0.1); color: white; text-decoration: none; border-radius: 12px; font-size: 1.1rem; font-weight: 600; border: 2px solid rgba(52, 152, 219, 0.3); margin-left: 15px; display: inline-flex; align-items: center; justify-content: center; gap: 10px;">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <?php else: ?>
                <button type="submit" name="add_venue" style="padding: 16px 45px; background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; border: none; border-radius: 12px; font-size: 1.1rem; font-weight: 700; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 6px 20px rgba(52, 152, 219, 0.3); display: inline-flex; align-items: center; justify-content: center; gap: 10px;">
                    <i class="fas fa-plus"></i> Add Venue
                </button>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Venues List Table -->
    <div style="background: rgba(255, 255, 255, 0.05); border-radius: 15px; padding: 30px; border: 1px solid rgba(52, 152, 219, 0.2);">
        <h2 style="color: white; font-size: 1.8rem; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid #3498db; display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-building"></i> All Venues (<?php echo $venue_count; ?>)
        </h2>
        
        <?php if (empty($venues)): ?>
        <div style="text-align: center; padding: 50px; color: rgba(255, 255, 255, 0.6);">
            <i class="fas fa-building fa-3x" style="margin-bottom: 20px; opacity: 0.5;"></i>
            <p style="font-size: 1.1rem;">No venues found. Add your first venue!</p>
        </div>
        <?php else: ?>
        <div style="overflow-x: auto; border-radius: 10px; border: 1px solid rgba(52, 152, 219, 0.2);">
            <table style="width: 100%; border-collapse: collapse; min-width: 1000px;">
                <thead>
                    <tr style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);">
                        <th style="color: white; padding: 16px; text-align: left; font-weight: 700; font-size: 1rem;">ID</th>
                        <th style="color: white; padding: 16px; text-align: left; font-weight: 700; font-size: 1rem;">Venue Photo</th>
                        <th style="color: white; padding: 16px; text-align: left; font-weight: 700; font-size: 1rem;">Venue Details</th>
                        <th style="color: white; padding: 16px; text-align: left; font-weight: 700; font-size: 1rem;">Associated Movies</th>
                        <th style="color: white; padding: 16px; text-align: left; font-weight: 700; font-size: 1rem;">Google Maps</th>
                        <th style="color: white; padding: 16px; text-align: left; font-weight: 700; font-size: 1rem;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($venues as $venue): ?>
                    <tr style="border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
                        <td style="padding: 16px; color: rgba(255, 255, 255, 0.9); font-weight: 700;"><?php echo $venue['id']; ?></td>
                        <td style="padding: 16px;">
                            <?php if (!empty($venue['venue_photo_path'])): ?>
                            <img src="<?php echo SITE_URL . $venue['venue_photo_path']; ?>" 
                                 alt="<?php echo htmlspecialchars($venue['venue_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                 style="width: 70px; height: 70px; object-fit: cover; border-radius: 8px; border: 2px solid rgba(52,152,219,0.3);"
                                 onerror="this.src='data:image/svg+xml,<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 70 70\"><rect width=\"70\" height=\"70\" fill=\"%232c3e50\"/></svg>'">
                            <?php else: ?>
                            <div style="width: 70px; height: 70px; background: rgba(52,152,219,0.1); border-radius: 8px; display: flex; align-items: center; justify-content: center; border: 2px solid rgba(52,152,219,0.2);">
                                <i class="fas fa-building" style="color: rgba(52,152,219,0.5); font-size: 1.8rem;"></i>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 16px;">
                            <div style="color: white; font-size: 1.1rem; font-weight: 700; margin-bottom: 5px;">
                                <?php echo htmlspecialchars($venue['venue_name'], ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                            <div style="color: rgba(255, 255, 255, 0.7); font-size: 0.9rem;">
                                <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($venue['venue_location'], ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        </td>
                        <td style="padding: 16px; text-align: center;">
                            <?php if ($venue['movie_count'] > 0): ?>
                            <span style="background: rgba(46, 204, 113, 0.2); color: #2ecc71; padding: 5px 12px; border-radius: 20px; font-weight: 600;">
                                <?php echo $venue['movie_count']; ?> movie(s)
                            </span>
                            <?php else: ?>
                            <span style="background: rgba(149, 165, 166, 0.2); color: #95a5a6; padding: 5px 12px; border-radius: 20px;">
                                No movies
                            </span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 16px;">
                            <?php if (!empty($venue['google_maps_link'])): ?>
                            <a href="<?php echo htmlspecialchars($venue['google_maps_link'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" 
                               style="color: #3498db; text-decoration: none; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 8px; padding: 8px 14px; background: rgba(52, 152, 219, 0.1); border-radius: 6px; border: 1px solid rgba(52, 152, 219, 0.3);">
                                <i class="fas fa-map-marked-alt"></i> View Map
                            </a>
                            <?php else: ?>
                            <span style="color: rgba(255, 255, 255, 0.5); font-size: 0.9rem;">
                                <i class="fas fa-times-circle"></i> No Map
                            </span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 16px;">
                            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                <a href="index.php?page=admin/manage-venues&edit=<?php echo $venue['id']; ?>" 
                                   style="padding: 8px 16px; background: rgba(52, 152, 219, 0.2); color: #3498db; text-decoration: none; border-radius: 6px; font-size: 0.85rem; font-weight: 600; border: 1px solid rgba(52, 152, 219, 0.3); display: inline-flex; align-items: center; gap: 5px;">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <?php if ($venue['movie_count'] == 0): ?>
                                <a href="index.php?page=admin/manage-venues&delete=<?php echo $venue['id']; ?>" 
                                   style="padding: 8px 16px; background: rgba(231, 76, 60, 0.2); color: #e74c3c; text-decoration: none; border-radius: 6px; font-size: 0.85rem; font-weight: 600; border: 1px solid rgba(231, 76, 60, 0.3); display: inline-flex; align-items: center; gap: 5px;"
                                   onclick="return confirm('Are you sure you want to delete venue \'<?php echo addslashes($venue['venue_name']); ?>\'?\nThis will permanently remove this venue from the system.')">
                                    <i class="fas fa-trash"></i> Delete
                                </a>
                                <?php else: ?>
                                <span style="padding: 8px 16px; background: rgba(108, 117, 125, 0.2); color: #6c757d; border-radius: 6px; font-size: 0.85rem; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; cursor: not-allowed;" title="Cannot delete - venue has <?php echo $venue['movie_count']; ?> movie(s) assigned">
                                    <i class="fas fa-lock"></i> Locked
                                </span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div style="margin-top: 20px; padding: 15px; background: rgba(52, 152, 219, 0.05); border-radius: 10px; text-align: center;">
            <p style="color: rgba(255, 255, 255, 0.6); font-size: 0.85rem;">
                <i class="fas fa-info-circle"></i> 
                <strong>Note:</strong> Venues with associated movies cannot be deleted. To delete a venue, first reassign its movies to another venue.
            </p>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Quick Navigation Back to Movies -->
    <div style="margin-top: 30px; text-align: center;">
        <a href="<?php echo SITE_URL; ?>index.php?page=admin/manage-movies" 
           style="display: inline-flex; align-items: center; gap: 10px; padding: 12px 25px; background: rgba(255, 255, 255, 0.1); color: white; text-decoration: none; border-radius: 10px; font-weight: 600; border: 2px solid rgba(52, 152, 219, 0.3); transition: all 0.3s ease;"
           onmouseover="this.style.background='rgba(52,152,219,0.2)'; this.style.borderColor='#3498db';"
           onmouseout="this.style.background='rgba(255,255,255,0.1)'; this.style.borderColor='rgba(52,152,219,0.3)';">
            <i class="fas fa-arrow-left"></i> Back to Manage Movies
        </a>
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
// Handle venue photo file selection display
document.getElementById('venuePhotoInput')?.addEventListener('change', function(e) {
    const fileName = e.target.files[0]?.name;
    const fileNameDiv = document.getElementById('venuePhotoName');
    if (fileName) {
        fileNameDiv.innerHTML = '<i class="fas fa-check-circle"></i> Selected: ' + fileName;
    } else {
        fileNameDiv.innerHTML = '';
    }
});

document.getElementById('venueForm')?.addEventListener('submit', function(e) {
    const venueName = document.getElementById('venue_name').value.trim();
    const venueLocation = document.getElementById('venue_location').value.trim();
    
    if (!venueName || !venueLocation) {
        e.preventDefault();
        alert('Please fill in both venue name and location!');
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