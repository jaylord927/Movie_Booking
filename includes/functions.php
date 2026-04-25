<?php
if (!defined('SITE_URL')) {
    require_once __DIR__ . '/config.php';
}

function sanitize_input($data) {
    if (is_array($data)) {
        return array_map('sanitize_input', $data);
    }
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function is_logged_in() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
}

function is_admin() {
    return is_logged_in() && $_SESSION['user_role'] === 'Admin';
}

function get_db_connection() {
    static $conn = null;
    
    if ($conn === null || !$conn->ping()) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            if (DEBUG) {
                die("<div style='background: #f8d7da; color: #721c24; padding: 20px; border-radius: 5px; margin: 20px;'>
                        <h3>Database Connection Error</h3>
                        <p><strong>Error:</strong> " . $conn->connect_error . "</p>
                        <p><strong>Database:</strong> " . DB_NAME . "</p>
                        <p><strong>Host:</strong> " . DB_HOST . "</p>
                     </div>");
            } else {
                die("Database connection failed.");
            }
        }
        
        $conn->set_charset("utf8mb4");
    }
    
    return $conn;
}

function debug_log($message, $data = null) {
    if (DEBUG) {
        echo "<script>console.log('PHP: " . addslashes($message) . "');</script>";
        if ($data) {
            echo "<script>console.log('PHP Data:', " . json_encode($data) . ");</script>";
        }
    }
}



/**
 * Get all venues from the database
 * @param bool $only_active - If true, only return venues that have active movies
 * @return array Array of venues
 */
function getVenueList($only_active = false) {
    $conn = get_db_connection();
    
    if ($only_active) {
        $query = "
            SELECT DISTINCT v.* 
            FROM venues v
            INNER JOIN movies m ON v.id = m.venue_id
            WHERE m.is_active = 1
            ORDER BY v.venue_name
        ";
        $result = $conn->query($query);
    } else {
        $query = "SELECT * FROM venues ORDER BY venue_name";
        $result = $conn->query($query);
    }
    
    $venues = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $venues[] = $row;
        }
    }
    
    return $venues;
}

/**
 * Get a single venue by ID
 * @param int $id Venue ID
 * @return array|null Venue data or null if not found
 */
function getVenueById($id) {
    if (!$id || !is_numeric($id)) {
        return null;
    }
    
    $conn = get_db_connection();
    $stmt = $conn->prepare("SELECT * FROM venues WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $venue = $result->fetch_assoc();
        $stmt->close();
        return $venue;
    }
    
    $stmt->close();
    return null;
}

/**
 * Get a single venue by name
 * @param string $name Venue name
 * @return array|null Venue data or null if not found
 */
function getVenueByName($name) {
    if (empty($name)) {
        return null;
    }
    
    $conn = get_db_connection();
    $stmt = $conn->prepare("SELECT * FROM venues WHERE venue_name = ?");
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $venue = $result->fetch_assoc();
        $stmt->close();
        return $venue;
    }
    
    $stmt->close();
    return null;
}

/**
 * Get movie count for a venue
 * @param int $venue_id Venue ID
 * @return int Number of active movies at the venue
 */
function getVenueMovieCount($venue_id) {
    if (!$venue_id || !is_numeric($venue_id)) {
        return 0;
    }
    
    $conn = get_db_connection();
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM movies WHERE venue_id = ? AND is_active = 1");
    $stmt->bind_param("i", $venue_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['count'];
    $stmt->close();
    
    return $count;
}

/**
 * Get all movies for a specific venue
 * @param int $venue_id Venue ID
 * @param bool $only_active If true, only return active movies
 * @return array Array of movies
 */
function getVenueMovies($venue_id, $only_active = true) {
    if (!$venue_id || !is_numeric($venue_id)) {
        return [];
    }
    
    $conn = get_db_connection();
    
    $active_condition = $only_active ? "AND is_active = 1" : "";
    $stmt = $conn->prepare("
        SELECT m.* 
        FROM movies m
        WHERE m.venue_id = ? $active_condition
        ORDER BY m.created_at DESC
    ");
    $stmt->bind_param("i", $venue_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $movies = [];
    while ($row = $result->fetch_assoc()) {
        $movies[] = $row;
    }
    $stmt->close();
    
    return $movies;
}

/**
 * Get venue dropdown HTML for forms
 * @param int $selected_id Currently selected venue ID
 * @param string $name Select element name attribute
 * @param string $class CSS class for the select
 * @param bool $show_empty_option Whether to show an empty option
 * @return string HTML select dropdown
 */
function getVenueDropdown($selected_id = null, $name = 'venue_id', $class = '', $show_empty_option = true) {
    $venues = getVenueList();
    
    if (empty($venues)) {
        return '<p class="text-muted">No venues available. Please add a venue first.</p>';
    }
    
    $html = '<select name="' . htmlspecialchars($name) . '" id="' . htmlspecialchars($name) . '" class="' . htmlspecialchars($class) . '">';
    
    if ($show_empty_option) {
        $html .= '<option value="">-- Select Venue --</option>';
    }
    
    foreach ($venues as $venue) {
        $selected = ($selected_id == $venue['id']) ? 'selected' : '';
        $html .= '<option value="' . $venue['id'] . '" ' . $selected . '>';
        $html .= htmlspecialchars($venue['venue_name']);
        if (!empty($venue['venue_location'])) {
            $html .= ' (' . htmlspecialchars(substr($venue['venue_location'], 0, 40)) . ')';
        }
        $html .= '</option>';
    }
    
    $html .= '</select>';
    
    return $html;
}

/**
 * Display venue information in a consistent format
 * @param array $venue Venue data array
 * @param bool $show_map_link Whether to show Google Maps link
 * @param bool $show_photo Whether to show venue photo
 * @return string HTML for venue display
 */
function displayVenueInfo($venue, $show_map_link = true, $show_photo = false) {
    if (!$venue || empty($venue)) {
        return '';
    }
    
    $html = '<div class="venue-info">';
    
    // Venue Name
    $html .= '<div class="venue-name">';
    $html .= '<i class="fas fa-building"></i> <strong>' . htmlspecialchars($venue['venue_name']) . '</strong>';
    $html .= '</div>';
    
    // Venue Location
    if (!empty($venue['venue_location'])) {
        $html .= '<div class="venue-location">';
        $html .= '<i class="fas fa-map-pin"></i> ' . htmlspecialchars($venue['venue_location']);
        $html .= '</div>';
    }
    
    // Google Maps Link
    if ($show_map_link && !empty($venue['google_maps_link'])) {
        $html .= '<div class="venue-map-link">';
        $html .= '<a href="' . htmlspecialchars($venue['google_maps_link']) . '" target="_blank">';
        $html .= '<i class="fas fa-map-marked-alt"></i> View on Google Maps';
        $html .= '</a>';
        $html .= '</div>';
    }
    
    // Venue Photo
    if ($show_photo && !empty($venue['venue_photo_path'])) {
        $html .= '<div class="venue-photo">';
        $html .= '<img src="' . SITE_URL . $venue['venue_photo_path'] . '" alt="' . htmlspecialchars($venue['venue_name']) . ' Photo" style="max-width: 100%; border-radius: 8px; margin-top: 10px;">';
        $html .= '</div>';
    }
    
    $html .= '</div>';
    
    return $html;
}

/**
 * Validate if a venue exists
 * @param int $venue_id Venue ID to validate
 * @return bool True if venue exists, false otherwise
 */
function validateVenueExists($venue_id) {
    if (!$venue_id || !is_numeric($venue_id)) {
        return false;
    }
    
    $conn = get_db_connection();
    $stmt = $conn->prepare("SELECT id FROM venues WHERE id = ?");
    $stmt->bind_param("i", $venue_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result->num_rows > 0;
    $stmt->close();
    
    return $exists;
}

/**
 * Get venue statistics for dashboard
 * @return array Venue statistics
 */
function getVenueStatistics() {
    $conn = get_db_connection();
    
    $stats = [];
    
    // Total venues
    $result = $conn->query("SELECT COUNT(*) as total FROM venues");
    $stats['total_venues'] = $result->fetch_assoc()['total'];
    
    // Venues with movies
    $result = $conn->query("
        SELECT COUNT(DISTINCT v.id) as count 
        FROM venues v
        INNER JOIN movies m ON v.id = m.venue_id
        WHERE m.is_active = 1
    ");
    $stats['active_venues'] = $result->fetch_assoc()['count'];
    
    // Venues without movies
    $stats['inactive_venues'] = $stats['total_venues'] - $stats['active_venues'];
    
    // Top venue by movie count
    $result = $conn->query("
        SELECT v.venue_name, COUNT(m.id) as movie_count
        FROM venues v
        LEFT JOIN movies m ON v.id = m.venue_id AND m.is_active = 1
        GROUP BY v.id
        ORDER BY movie_count DESC
        LIMIT 1
    ");
    if ($result && $result->num_rows > 0) {
        $stats['top_venue'] = $result->fetch_assoc();
    } else {
        $stats['top_venue'] = null;
    }
    
    return $stats;
}

/**
 * Get all venues with their movie counts
 * @return array Venues with movie counts
 */
function getVenuesWithMovieCount() {
    $conn = get_db_connection();
    
    $query = "
        SELECT v.*, COUNT(m.id) as movie_count
        FROM venues v
        LEFT JOIN movies m ON v.id = m.venue_id AND m.is_active = 1
        GROUP BY v.id
        ORDER BY v.venue_name
    ";
    $result = $conn->query($query);
    
    $venues = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $venues[] = $row;
        }
    }
    
    return $venues;
}

/**
 * Create a venue URL slug from venue name
 * @param string $venue_name Venue name
 * @return string URL-friendly slug
 */
function venueSlug($venue_name) {
    $slug = strtolower($venue_name);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');
    return $slug;
}

/**
 * Get venue URL
 * @param int|array $venue Venue ID or venue data array
 * @return string URL to venue page
 */
function getVenueUrl($venue) {
    if (is_array($venue) && isset($venue['venue_name'])) {
        $venue_name = $venue['venue_name'];
    } elseif (is_numeric($venue)) {
        $venue_data = getVenueById($venue);
        $venue_name = $venue_data['venue_name'] ?? '';
    } else {
        return SITE_URL . 'index.php?page=venue';
    }
    
    return SITE_URL . 'index.php?page=venue-movies&venue=' . urlencode($venue_name);
}

/**
 * Get venue name from ID (cached for performance)
 * @param int $venue_id Venue ID
 * @return string Venue name or empty string
 */
function getVenueName($venue_id) {
    static $venue_cache = [];
    
    if (!$venue_id || !is_numeric($venue_id)) {
        return '';
    }
    
    if (isset($venue_cache[$venue_id])) {
        return $venue_cache[$venue_id];
    }
    
    $conn = get_db_connection();
    $stmt = $conn->prepare("SELECT venue_name FROM venues WHERE id = ?");
    $stmt->bind_param("i", $venue_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $venue_name = $result->fetch_assoc()['venue_name'];
        $venue_cache[$venue_id] = $venue_name;
        $stmt->close();
        return $venue_name;
    }
    
    $stmt->close();
    return '';
}

/**
 * Check if a venue can be deleted (no movies attached)
 * @param int $venue_id Venue ID
 * @return bool True if deletable, false otherwise
 */
function isVenueDeletable($venue_id) {
    $conn = get_db_connection();
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM movies WHERE venue_id = ? AND is_active = 1");
    $stmt->bind_param("i", $venue_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $movie_count = $result->fetch_assoc()['count'];
    $stmt->close();
    
    return $movie_count == 0;
}
?>