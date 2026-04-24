<?php
$root_dir = dirname(__DIR__);
require_once $root_dir . '/includes/config.php';
require_once $root_dir . '/includes/functions.php';
require_once $root_dir . '/partials/header.php';

$conn = get_db_connection();

// Get search term
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';

// Get all unique venues from movies table with their details
$venue_query = "SELECT DISTINCT venue_name, venue_location, google_maps_link, venue_photo_path 
                FROM movies 
                WHERE is_active = 1 
                AND venue_name IS NOT NULL 
                AND venue_name != ''";

if (!empty($search)) {
    $venue_query .= " AND venue_name LIKE '%$search%'";
}

$venue_query .= " ORDER BY venue_name";
$venues_result = $conn->query($venue_query);

$venues = [];
if ($venues_result) {
    while ($row = $venues_result->fetch_assoc()) {
        $venues[] = $row;
    }
}

$conn->close();
?>

<div class="venue-container" style="max-width: 1400px; margin: 0 auto; padding: 20px;">
    <div style="text-align: center; margin-bottom: 40px;">
        <h1 style="color: white; font-size: 2.5rem; margin-bottom: 15px; font-weight: 800;">
            <i class="fas fa-map-marker-alt"></i> Our Venues
        </h1>
        <p style="color: var(--pale-red); font-size: 1.1rem; max-width: 600px; margin: 0 auto;">
            Find your nearest cinema and enjoy the latest movies
        </p>
    </div>

    <!-- Search Bar -->
    <div style="background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%); border-radius: 15px; padding: 25px; margin-bottom: 40px; border: 1px solid rgba(226, 48, 32, 0.2);">
        <form method="GET" action="">
            <input type="hidden" name="page" value="venue">
            <div style="display: flex; gap: 10px; position: relative;">
                <i class="fas fa-search" style="position: absolute; left: 20px; top: 50%; transform: translateY(-50%); color: rgba(255,255,255,0.6); font-size: 1.2rem;"></i>
                <input type="text" name="search" placeholder="Search for a venue..." value="<?php echo htmlspecialchars($search); ?>" 
                       style="flex: 1; padding: 15px 20px 15px 50px; background: rgba(255,255,255,0.08); border: 2px solid rgba(226, 48, 32, 0.3); border-radius: 10px; color: white; font-size: 1rem;" 
                       autocomplete="off">
                <button type="submit" style="padding: 15px 30px; background: linear-gradient(135deg, var(--primary-red) 0%, var(--dark-red) 100%); color: white; border: none; border-radius: 10px; font-weight: 600; cursor: pointer; transition: all 0.3s ease;"
                        onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 20px rgba(226,48,32,0.4)';"
                        onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                    <i class="fas fa-search"></i> Search
                </button>
                <?php if ($search): ?>
                <a href="?page=venue" style="padding: 15px 20px; background: rgba(255,255,255,0.1); color: white; text-decoration: none; border-radius: 10px; border: 2px solid rgba(226, 48, 32, 0.3); display: flex; align-items: center; gap: 8px; font-weight: 600; transition: all 0.3s ease;"
                   onmouseover="this.style.background='rgba(226,48,32,0.2)'; this.style.borderColor='var(--primary-red)';"
                   onmouseout="this.style.background='rgba(255,255,255,0.1)'; this.style.borderColor='rgba(226,48,32,0.3)';">
                    <i class="fas fa-times"></i> Clear
                </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <?php if (empty($venues)): ?>
        <div style="text-align: center; padding: 60px; background: rgba(226, 48, 32, 0.05); border-radius: 15px; border: 2px dashed rgba(226, 48, 32, 0.3);">
            <i class="fas fa-map-marker-alt fa-3x" style="color: var(--primary-red); margin-bottom: 20px; opacity: 0.8;"></i>
            <h3 style="color: white; margin-bottom: 15px; font-size: 1.8rem;">No Venues Found</h3>
            <p style="color: var(--pale-red); margin-bottom: 25px;">
                <?php if ($search): ?>
                    No venues match your search criteria.
                <?php else: ?>
                    No venues have been added yet.
                <?php endif; ?>
            </p>
            <?php if ($search): ?>
            <a href="?page=venue" class="btn btn-primary" style="padding: 12px 30px;">
                <i class="fas fa-times"></i> Clear Search
            </a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(550px, 1fr)); gap: 30px;">
            <?php foreach ($venues as $venue): 
                // Generate embed URL from Google Maps link with full features
                $embed_url = '';
                $has_valid_map = false;
                
                if (!empty($venue['google_maps_link'])) {
                    if (preg_match('/q=([0-9.-]+),([0-9.-]+)/', $venue['google_maps_link'], $matches)) {
                        $lat = $matches[1];
                        $lng = $matches[2];
                        $embed_url = "https://maps.google.com/maps?q={$lat},{$lng}&z=15&output=embed";
                        $has_valid_map = true;
                    } elseif (preg_match('/@([0-9.-]+),([0-9.-]+)/', $venue['google_maps_link'], $matches)) {
                        $lat = $matches[1];
                        $lng = $matches[2];
                        $embed_url = "https://maps.google.com/maps?q={$lat},{$lng}&z=15&output=embed";
                        $has_valid_map = true;
                    } else {
                        $embed_url = "https://maps.google.com/maps?q=" . urlencode($venue['venue_location'] ?? $venue['venue_name'] ?? '') . "&z=15&output=embed";
                        $has_valid_map = true;
                    }
                }
                
                // Check if venue name and location are the same
                $is_same_location = !empty($venue['venue_name']) && !empty($venue['venue_location']) && 
                                     strcasecmp(trim($venue['venue_name']), trim($venue['venue_location'])) === 0;
            ?>
            <div class="venue-card" style="background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%); border-radius: 15px; overflow: hidden; transition: all 0.3s ease; border: 1px solid rgba(226, 48, 32, 0.2); display: flex; flex-direction: column; height: 100%;"
                 onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 15px 35px rgba(226,48,32,0.2)'; this.style.borderColor='#e23020';"
                 onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'; this.style.borderColor='rgba(226,48,32,0.2)';">
                
                <!-- Venue Header -->
                <div style="padding: 25px 25px 0 25px;">
                    <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 20px;">
                        <div style="width: 50px; height: 50px; background: linear-gradient(135deg, var(--primary-red) 0%, var(--dark-red) 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <i class="fas fa-building" style="color: white; font-size: 1.5rem;"></i>
                        </div>
                        <h2 style="color: white; font-size: 1.5rem; font-weight: 700; flex: 1;"><?php echo htmlspecialchars($venue['venue_name']); ?></h2>
                    </div>
                    
                    <?php if (!empty($venue['venue_location']) && !$is_same_location): ?>
                    <div style="margin-bottom: 20px; padding: 15px; background: rgba(255,255,255,0.05); border-radius: 10px;">
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 5px;">
                            <i class="fas fa-map-pin" style="color: var(--primary-red);"></i>
                            <span style="color: var(--pale-red); font-weight: 600;">Location:</span>
                        </div>
                        <p style="color: white; line-height: 1.6;"><?php echo htmlspecialchars($venue['venue_location']); ?></p>
                    </div>
                    <?php elseif (!empty($venue['venue_location']) && $is_same_location): ?>
                    <div style="margin-bottom: 20px; padding: 15px; background: rgba(255,255,255,0.05); border-radius: 10px;">
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 5px;">
                            <i class="fas fa-map-pin" style="color: var(--primary-red);"></i>
                            <span style="color: var(--pale-red); font-weight: 600;">Location (Same as Venue Name):</span>
                        </div>
                        <p style="color: white; line-height: 1.6;"><?php echo htmlspecialchars($venue['venue_location']); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Two Column Layout: Venue Photo + Map -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; padding: 0 25px 20px 25px; flex: 1;">
                    <!-- Venue Photo Column - MODIFIED with click-to-expand -->
                    <div style="display: flex; flex-direction: column;">
                        <?php if (!empty($venue['venue_photo_path']) && file_exists($root_dir . '/' . $venue['venue_photo_path'])): ?>
                        <div style="background: rgba(0,0,0,0.3); border-radius: 12px; padding: 15px; height: 100%; display: flex; flex-direction: column;">
                            <div style="color: white; font-weight: 600; margin-bottom: 12px; font-size: 0.9rem;">
                                <i class="fas fa-camera"></i> Venue Photo
                            </div>
                            <div style="text-align: center; overflow: hidden; border-radius: 10px; flex: 1; cursor: pointer;" 
                                 onclick="openFullImage('<?php echo SITE_URL . $venue['venue_photo_path']; ?>', '<?php echo htmlspecialchars($venue['venue_name']); ?>')">
                                <img src="<?php echo SITE_URL . $venue['venue_photo_path']; ?>" 
                                     alt="<?php echo htmlspecialchars($venue['venue_name']); ?> Photo"
                                     style="width: 100%; height: 200px; object-fit: cover; border-radius: 10px; border: 2px solid rgba(226, 48, 32, 0.3); transition: transform 0.3s ease; cursor: pointer;"
                                     onmouseover="this.style.transform='scale(1.02)';"
                                     onmouseout="this.style.transform='scale(1)';">
                            </div>
                            <div style="margin-top: 10px; text-align: center;">
                                <span style="color: var(--pale-red); font-size: 0.75rem; cursor: pointer;" onclick="openFullImage('<?php echo SITE_URL . $venue['venue_photo_path']; ?>', '<?php echo htmlspecialchars($venue['venue_name']); ?>')">
                                    <i class="fas fa-search-plus"></i> Click photo to view full size
                                </span>
                            </div>
                        </div>
                        <?php else: ?>
                        <div style="background: rgba(0,0,0,0.3); border-radius: 12px; padding: 15px; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 230px;">
                            <i class="fas fa-camera" style="font-size: 3rem; color: rgba(255,255,255,0.15); margin-bottom: 10px;"></i>
                            <p style="color: rgba(255,255,255,0.3); font-size: 0.9rem;">No venue photo available</p>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Google Maps Column -->
                    <div style="display: flex; flex-direction: column;">
                        <?php if ($has_valid_map && !empty($embed_url)): ?>
                        <div style="background: rgba(0,0,0,0.3); border-radius: 12px; padding: 15px; height: 100%; display: flex; flex-direction: column;">
                            <div style="color: white; font-weight: 600; margin-bottom: 12px; font-size: 0.9rem;">
                                <i class="fas fa-map-marked-alt"></i> Location Map
                            </div>
                            <div style="position: relative; border-radius: 10px; overflow: hidden; border: 2px solid rgba(226, 48, 32, 0.3); flex: 1;">
                                <iframe 
                                    src="<?php echo $embed_url; ?>"
                                    style="width: 100%; height: 200px; border: 0; display: block;"
                                    allowfullscreen="" 
                                    loading="lazy">
                                </iframe>
                            </div>
                            <div style="margin-top: 8px; text-align: center;">
                                <a href="<?php echo htmlspecialchars($venue['google_maps_link']); ?>" target="_blank" 
                                   style="color: var(--light-red); font-size: 0.8rem; text-decoration: none; display: inline-flex; align-items: center; gap: 5px;"
                                   onmouseover="this.style.textDecoration='underline';"
                                   onmouseout="this.style.textDecoration='none';">
                                    <i class="fas fa-external-link-alt"></i> Open in Google Maps
                                </a>
                            </div>
                        </div>
                        <?php else: ?>
                        <div style="background: rgba(0,0,0,0.3); border-radius: 12px; padding: 15px; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 230px;">
                            <i class="fas fa-map-marker-alt" style="font-size: 3rem; color: rgba(255,255,255,0.15); margin-bottom: 10px;"></i>
                            <p style="color: rgba(255,255,255,0.3); font-size: 0.9rem; text-align: center;">No map link available</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div style="padding: 0 25px 25px 25px; margin-top: auto;">
                    <div style="display: flex; gap: 15px; margin-top: 10px;">
                        <a href="<?php echo SITE_URL; ?>index.php?page=venue-movies&venue=<?php echo urlencode($venue['venue_name']); ?>" 
                           style="flex: 1; background: linear-gradient(135deg, var(--primary-red) 0%, var(--dark-red) 100%); color: white; border: none; padding: 14px; border-radius: 10px; font-weight: 700; cursor: pointer; transition: all 0.3s ease; display: flex; align-items: center; justify-content: center; gap: 10px; text-decoration: none;"
                           onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 5px 15px rgba(226,48,32,0.4)';"
                           onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                            <i class="fas fa-film"></i> View All Movies
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div style="text-align: center; margin-top: 40px; padding: 20px; background: rgba(226, 48, 32, 0.05); border-radius: 10px; border: 1px solid rgba(226, 48, 32, 0.2);">
            <p style="color: var(--pale-red); font-size: 1rem;">
                Showing <strong style="color: white;"><?php echo count($venues); ?></strong> venue(s)
                <?php if ($search): ?>
                    matching "<strong style="color: var(--primary-red);"><?php echo htmlspecialchars($search); ?></strong>"
                <?php endif; ?>
            </p>
        </div>
    <?php endif; ?>
</div>

<!-- Full Image Modal -->
<div id="imageModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.95); z-index: 10000; justify-content: center; align-items: center; cursor: pointer; padding: 20px;"
     onclick="closeFullImage()">
    <div style="max-width: 90%; max-height: 90%; text-align: center;">
        <img id="fullImage" src="" alt="" style="max-width: 100%; max-height: 80vh; border-radius: 10px; border: 3px solid var(--primary-red);">
        <div style="margin-top: 20px; color: white;">
            <p id="imageCaption" style="margin-bottom: 10px;"></p>
            <span style="background: rgba(255,255,255,0.2); padding: 8px 20px; border-radius: 30px; font-size: 0.9rem;">
                <i class="fas fa-times-circle"></i> Click anywhere to close
            </span>
        </div>
    </div>
</div>

<style>
.venue-card {
    animation: fadeInUp 0.5s ease forwards;
    opacity: 0;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.btn {
    padding: 12px 25px;
    text-decoration: none;
    border-radius: 10px;
    font-weight: 600;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
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

/* Fix for flex columns to ensure equal height */
.venue-card > div:nth-child(2) {
    flex: 1;
}

/* Ensure map iframe has proper controls */
iframe {
    pointer-events: auto;
}

/* Responsive Design */
@media (max-width: 1200px) {
    .venue-container > div:last-child {
        grid-template-columns: repeat(auto-fill, minmax(500px, 1fr));
    }
}

@media (max-width: 768px) {
    .venue-container {
        padding: 15px;
    }
    
    .venue-container > div:last-child {
        grid-template-columns: 1fr;
    }
    
    .venue-card > div:first-child {
        padding: 20px 20px 0 20px;
    }
    
    .venue-card > div:nth-child(2) {
        padding: 0 20px 20px 20px;
        grid-template-columns: 1fr;
        gap: 15px;
    }
    
    .venue-card > div:last-child {
        padding: 0 20px 20px 20px;
    }
}

@media (max-width: 576px) {
    .venue-card > div:nth-child(2) {
        grid-template-columns: 1fr;
    }
    
    .search-container {
        flex-direction: column;
    }
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

#imageModal {
    animation: fadeIn 0.3s ease;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const venueCards = document.querySelectorAll('.venue-card');
    venueCards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
    });
});

function openFullImage(imageUrl, venueName) {
    const modal = document.getElementById('imageModal');
    const fullImage = document.getElementById('fullImage');
    const caption = document.getElementById('imageCaption');
    
    fullImage.src = imageUrl;
    caption.innerHTML = `<i class="fas fa-building"></i> ${escapeHtml(venueName)} - Venue Photo`;
    modal.style.display = 'flex';
}

function closeFullImage() {
    const modal = document.getElementById('imageModal');
    modal.style.display = 'none';
}

// Escape HTML to prevent XSS
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeFullImage();
    }
});
</script>

<?php require_once $root_dir . '/partials/footer.php'; ?>