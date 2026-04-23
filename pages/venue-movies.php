<?php
$root_dir = dirname(__DIR__);
require_once $root_dir . '/includes/config.php';
require_once $root_dir . '/includes/functions.php';
require_once $root_dir . '/partials/header.php';

$conn = get_db_connection();

// Get venue name from URL
$venue_name = isset($_GET['venue']) ? urldecode($_GET['venue']) : '';

if (empty($venue_name)) {
    header("Location: " . SITE_URL . "index.php?page=venue");
    exit();
}

// Get venue details
$venue_stmt = $conn->prepare("
    SELECT DISTINCT venue_name, venue_location, google_maps_link 
    FROM movies 
    WHERE venue_name = ? AND is_active = 1 
    LIMIT 1
");
$venue_stmt->bind_param("s", $venue_name);
$venue_stmt->execute();
$venue_result = $venue_stmt->get_result();
$venue = $venue_result->fetch_assoc();
$venue_stmt->close();

if (!$venue) {
    header("Location: " . SITE_URL . "index.php?page=venue");
    exit();
}

// Get all movies for this venue
$movies_stmt = $conn->prepare("
    SELECT m.* 
    FROM movies m
    WHERE m.venue_name = ? AND m.is_active = 1
    ORDER BY m.created_at DESC
");
$movies_stmt->bind_param("s", $venue_name);
$movies_stmt->execute();
$movies_result = $movies_stmt->get_result();

$movies = [];
while ($row = $movies_result->fetch_assoc()) {
    $movies[] = $row;
}
$movies_stmt->close();

// Fetch schedules for movies
$schedules = [];
foreach ($movies as $movie) {
    $schedule_stmt = $conn->prepare("
        SELECT * FROM movie_schedules 
        WHERE movie_id = ? AND is_active = 1 AND show_date >= CURDATE() 
        ORDER BY show_date, showtime
    ");
    $schedule_stmt->bind_param("i", $movie['id']);
    $schedule_stmt->execute();
    $schedule_result = $schedule_stmt->get_result();
    $movie_schedules = [];
    while ($row = $schedule_result->fetch_assoc()) {
        $movie_schedules[] = $row;
    }
    $schedules[$movie['id']] = $movie_schedules;
    $schedule_stmt->close();
}

// Check which movies have schedules (Now Showing)
$now_showing_ids = [];
foreach ($movies as $movie) {
    if (!empty($schedules[$movie['id']])) {
        $now_showing_ids[] = $movie['id'];
    }
}

$conn->close();
?>

<div class="venue-movies-container" style="max-width: 1200px; margin: 0 auto; padding: 20px;">
    <!-- Back Button -->
    <div style="margin-bottom: 20px;">
        <a href="javascript:history.back()" style="color: var(--light-red); text-decoration: none; display: inline-flex; align-items: center; gap: 8px; font-weight: 600;">
            <i class="fas fa-arrow-left"></i> Back to Venues
        </a>
    </div>

    <!-- Venue Header -->
    <div style="background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%); border-radius: 20px; padding: 30px; margin-bottom: 30px; border: 1px solid rgba(226, 48, 32, 0.3);">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 20px;">
            <div>
                <h1 style="color: white; font-size: 2rem; margin-bottom: 10px; font-weight: 800;">
                    <i class="fas fa-building" style="color: var(--primary-red);"></i> 
                    <?php echo htmlspecialchars($venue['venue_name']); ?>
                </h1>
                <?php if (!empty($venue['venue_location'])): ?>
                <p style="color: var(--pale-red); font-size: 1rem; margin-bottom: 10px;">
                    <i class="fas fa-map-pin"></i> <?php echo htmlspecialchars($venue['venue_location']); ?>
                </p>
                <?php endif; ?>
            </div>
            <?php if (!empty($venue['google_maps_link'])): ?>
            <a href="<?php echo htmlspecialchars($venue['google_maps_link']); ?>" target="_blank" 
               style="background: #e74c3c; color: white; padding: 12px 25px; border-radius: 10px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 10px; transition: all 0.3s ease;"
               onmouseover="this.style.background='#c0392b'; this.style.transform='translateY(-2px)';"
               onmouseout="this.style.background='#e74c3c'; this.style.transform='translateY(0)';">
                <i class="fas fa-map-marked-alt"></i> Open in Google Maps
            </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (empty($movies)): ?>
        <div style="text-align: center; padding: 60px; background: rgba(226, 48, 32, 0.05); border-radius: 15px; border: 2px dashed rgba(226, 48, 32, 0.3);">
            <i class="fas fa-film fa-3x" style="color: var(--primary-red); margin-bottom: 20px; opacity: 0.8;"></i>
            <h3 style="color: white; margin-bottom: 15px; font-size: 1.8rem;">No Movies Available</h3>
            <p style="color: var(--pale-red); margin-bottom: 25px;">
                No movies are currently available at this venue.
            </p>
            <a href="<?php echo SITE_URL; ?>index.php?page=venue" class="btn btn-primary" style="padding: 12px 30px;">
                <i class="fas fa-arrow-left"></i> Back to Venues
            </a>
        </div>
    <?php else: ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 30px;">
            <?php foreach ($movies as $movie): 
                $is_now_showing = in_array($movie['id'], $now_showing_ids);
            ?>
            <div class="movie-card" style="background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%); border-radius: 15px; overflow: hidden; transition: all 0.3s ease; border: 1px solid rgba(226, 48, 32, 0.2); position: relative; display: flex; flex-direction: column; height: 100%;"
                 onmouseover="this.style.transform='translateY(-10px)'; this.style.boxShadow='0 20px 40px rgba(226,48,32,0.2)'; this.style.borderColor='#e23020';"
                 onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'; this.style.borderColor='rgba(226,48,32,0.2)';">
                
                <?php if (!empty($movie['poster_url'])): ?>
                    <img src="<?php echo $movie['poster_url']; ?>" alt="<?php echo htmlspecialchars($movie['title']); ?>" style="width: 100%; height: 320px; object-fit: cover;">
                <?php else: ?>
                    <div style="width: 100%; height: 320px; background: linear-gradient(135deg, rgba(226, 48, 32, 0.1), rgba(193, 27, 24, 0.2)); display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-film" style="font-size: 3rem; color: rgba(255, 255, 255, 0.3);"></i>
                    </div>
                <?php endif; ?>
                
                <div style="position: absolute; top: 15px; right: 15px; display: flex; flex-direction: column; gap: 8px;">
                    <span style="background: var(--primary-red); color: white; font-weight: 700; font-size: 0.8rem; padding: 6px 12px; border-radius: 20px; box-shadow: 0 4px 8px rgba(0,0,0,0.2); text-align: center; min-width: 40px;"><?php echo $movie['rating'] ?: 'PG'; ?></span>
                    <?php if ($movie['genre']): ?>
                    <span style="background: rgba(0,0,0,0.7); color: white; font-weight: 600; font-size: 0.75rem; padding: 5px 10px; border-radius: 15px; display: flex; align-items: center; gap: 5px;"><i class="fas fa-tag"></i> <?php echo explode(',', $movie['genre'])[0]; ?></span>
                    <?php endif; ?>
                    <?php if (!$is_now_showing): ?>
                    <span style="background: rgba(241, 196, 15, 0.2); color: #f1c40f; padding: 5px 10px; border-radius: 15px; font-size: 0.75rem; font-weight: 700; border: 1px solid rgba(241, 196, 15, 0.3);">
                        <i class="fas fa-hourglass-half"></i> Coming Soon
                    </span>
                    <?php endif; ?>
                </div>
                
                <div style="padding: 25px; flex: 1; display: flex; flex-direction: column;">
                    <h3 style="color: white; font-size: 1.3rem; font-weight: 800; margin-bottom: 10px; line-height: 1.4;"><?php echo htmlspecialchars($movie['title']); ?></h3>
                    
                    <div style="margin-bottom: 15px;">
                        <?php if ($movie['duration']): ?>
                        <div style="display: flex; align-items: center; gap: 8px; color: rgba(255,255,255,0.8); font-size: 0.9rem; margin-bottom: 5px;"><i class="fas fa-clock"></i> <?php echo $movie['duration']; ?></div>
                        <?php endif; ?>
                        <?php if ($movie['genre']): ?>
                        <div style="display: flex; align-items: center; gap: 8px; color: rgba(255,255,255,0.8); font-size: 0.9rem;"><i class="fas fa-film"></i> <?php echo htmlspecialchars($movie['genre']); ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($movie['description']): ?>
                    <div style="margin-bottom: 15px; flex: 1;">
                        <p style="color: rgba(255,255,255,0.7); font-size: 0.9rem; line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;">
                            <?php echo htmlspecialchars($movie['description']); ?>
                        </p>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Schedule Dropdown -->
                    <?php if ($is_now_showing && !empty($schedules[$movie['id']])): ?>
                    <div style="margin-bottom: 15px; width: 100%;">
                        <select class="schedule-select" data-movie-id="<?php echo $movie['id']; ?>" style="width: 100%; padding: 12px 14px; background: rgba(255, 255, 255, 0.15); border: 2px solid rgba(226, 48, 32, 0.4); border-radius: 8px; color: white; font-size: 0.95rem; font-weight: 500; cursor: pointer; transition: all 0.3s ease; appearance: none; background-image: url('data:image/svg+xml;utf8,<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"20\" height=\"20\" fill=\"white\" viewBox=\"0 0 20 20\"><path d=\"M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z\"/></svg>'); background-repeat: no-repeat; background-position: right 14px center; background-size: 14px;">
                            <option value="" style="background: var(--bg-card); color: white;">Select a showtime</option>
                            <?php foreach ($schedules[$movie['id']] as $schedule): 
                                $show_date = date('D, M d', strtotime($schedule['show_date']));
                                $show_time = date('h:i A', strtotime($schedule['showtime']));
                                $is_today = date('Y-m-d') == $schedule['show_date'] ? ' (Today)' : '';
                            ?>
                            <option value="<?php echo $schedule['id']; ?>" style="background: var(--bg-card); color: white;">
                                <?php echo $show_date . $is_today . ' • ' . $show_time . ' • ' . $schedule['available_seats'] . ' seats'; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Buttons -->
                    <div style="display: flex; gap: 5px; margin-top: auto; min-height: 45px;">
                        <?php if (!empty($movie['trailer_url'])): ?>
                        <a href="<?php echo $movie['trailer_url']; ?>" target="_blank" style="background: rgba(255, 0, 0, 0.2); color: #ff0000; border: 2px solid rgba(255, 0, 0, 0.3); padding: 8px; border-radius: 8px; font-weight: 600; transition: all 0.3s ease; display: flex; align-items: center; justify-content: center; width: 45px; text-decoration: none;" title="Watch Trailer"
                           onmouseover="this.style.background='rgba(255,0,0,0.3)'; this.style.transform='translateY(-2px)';"
                           onmouseout="this.style.background='rgba(255,0,0,0.2)'; this.style.transform='translateY(0)';">
                            <i class="fab fa-youtube"></i>
                        </a>
                        <?php endif; ?>
                        <a href="<?php echo SITE_URL; ?>index.php?page=customer/movie-details&id=<?php echo $movie['id']; ?>" style="flex: 1; background: rgba(255,255,255,0.1); color: white; border: 2px solid rgba(226, 48, 32, 0.3); padding: 8px; border-radius: 8px; font-weight: 600; transition: all 0.3s ease; display: flex; align-items: center; justify-content: center; gap: 5px; text-decoration: none; font-size: 0.9rem; height: 45px;"
                           onmouseover="this.style.background='rgba(226,48,32,0.2)'; this.style.borderColor='var(--primary-red)'; this.style.transform='translateY(-2px)';"
                           onmouseout="this.style.background='rgba(255,255,255,0.1)'; this.style.borderColor='rgba(226,48,32,0.3)'; this.style.transform='translateY(0)';">
                            <i class="fas fa-info-circle"></i> Details
                        </a>
                        <?php if ($is_now_showing && !empty($schedules[$movie['id']])): ?>
                            <?php if (isset($_SESSION['user_id']) && $_SESSION['user_role'] === 'Customer'): ?>
                                <a href="<?php echo SITE_URL; ?>index.php?page=customer/booking&movie=<?php echo $movie['id']; ?>" class="book-now-btn" data-movie-id="<?php echo $movie['id']; ?>" style="flex: 1; background: linear-gradient(135deg, var(--primary-red) 0%, var(--dark-red) 100%); color: white; border: none; padding: 8px; border-radius: 8px; font-weight: 600; transition: all 0.3s ease; display: flex; align-items: center; justify-content: center; gap: 5px; text-decoration: none; font-size: 0.9rem; height: 45px;"
                                   onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 5px 15px rgba(226,48,32,0.4)';"
                                   onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                                    <i class="fas fa-ticket-alt"></i> Book Now
                                </a>
                            <?php elseif (!isset($_SESSION['user_id'])): ?>
                                <a href="<?php echo SITE_URL; ?>index.php?page=login" style="flex: 1; background: linear-gradient(135deg, var(--primary-red) 0%, var(--dark-red) 100%); color: white; border: none; padding: 8px; border-radius: 8px; font-weight: 600; transition: all 0.3s ease; display: flex; align-items: center; justify-content: center; gap: 5px; text-decoration: none; font-size: 0.9rem; height: 45px;"
                                   onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 5px 15px rgba(226,48,32,0.4)';"
                                   onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                                    <i class="fas fa-sign-in-alt"></i> Login to Book
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
.movie-card {
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

.schedule-select:hover {
    background: rgba(255, 255, 255, 0.2);
    border-color: var(--primary-red);
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(226, 48, 32, 0.2);
}

.schedule-select:focus {
    outline: none;
    background: rgba(255, 255, 255, 0.2);
    border-color: var(--primary-red);
    box-shadow: 0 0 0 3px rgba(226, 48, 32, 0.3);
}

.schedule-select option {
    padding: 12px;
    font-size: 0.95rem;
    background: var(--bg-card);
    color: white;
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

@media (max-width: 768px) {
    .venue-movies-container {
        padding: 15px;
    }
    
    .venue-movies-container > div:last-child {
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 20px;
    }
}

@media (max-width: 576px) {
    .venue-movies-container > div:last-child {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const movieCards = document.querySelectorAll('.movie-card');
    movieCards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
    });
    
    // Handle schedule selection
    const scheduleSelects = document.querySelectorAll('.schedule-select');
    scheduleSelects.forEach(select => {
        select.addEventListener('change', function() {
            const movieId = this.dataset.movieId;
            const scheduleId = this.value;
            const bookBtn = document.querySelector(`.book-now-btn[data-movie-id="${movieId}"]`);
            
            if (bookBtn && scheduleId) {
                bookBtn.href = '<?php echo SITE_URL; ?>index.php?page=customer/booking&movie=' + movieId + '&schedule=' + scheduleId;
            }
        });
    });
});
</script>

<?php require_once $root_dir . '/partials/footer.php'; ?>