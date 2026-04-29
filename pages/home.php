<?php
$root_dir = dirname(__DIR__);
require_once $root_dir . '/includes/config.php';
require_once $root_dir . '/includes/functions.php';
require_once $root_dir . '/partials/header.php';

$conn = get_db_connection();

// Get filter from URL
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'now-showing';

// Get all active movies
$movies_result = $conn->query("
    SELECT DISTINCT m.* 
    FROM movies m 
    WHERE m.is_active = 1 
    ORDER BY m.created_at DESC
");

$all_movies = [];
$movies_with_posters = [];

if ($movies_result) {
    while ($row = $movies_result->fetch_assoc()) {
        $all_movies[] = $row;
        if (!empty($row['poster_url'])) {
            $movies_with_posters[] = $row;
        }
    }
}

// Separate movies into Now Showing and Coming Soon
$now_showing = [];
$coming_soon = [];

foreach ($all_movies as $movie) {
    // Check if movie has any active schedule (using schedules table only - no available_seats column)
    $schedule_check = $conn->prepare("
        SELECT s.id 
        FROM schedules s
        WHERE s.movie_id = ? 
        AND s.is_active = 1 
        AND s.show_date >= CURDATE()
        LIMIT 1
    ");
    $schedule_check->bind_param("i", $movie['id']);
    $schedule_check->execute();
    $schedule_result = $schedule_check->get_result();
    
    if ($schedule_result->num_rows > 0) {
        $now_showing[] = $movie;
    } else {
        $coming_soon[] = $movie;
    }
    $schedule_check->close();
}

// Determine which movies to display based on filter
if ($filter === 'now-showing') {
    $display_movies = $now_showing;
    $display_title = "Now Showing";
    $display_message = "Movies currently playing in cinemas";
} else {
    $display_movies = $coming_soon;
    $display_title = "Coming Soon";
    $display_message = "Upcoming movies - stay tuned for showtimes";
}

// Get movie IDs for display
$display_movie_ids = array_column($display_movies, 'id');

// Fetch schedules for now showing movies (NO available_seats column - just check if schedule exists)
$schedules = [];
if ($filter === 'now-showing' && !empty($display_movie_ids)) {
    $placeholders = implode(',', array_fill(0, count($display_movie_ids), '?'));
    $schedule_sql = "
        SELECT s.*, sc.screen_name, sc.screen_number, v.venue_name, v.venue_location
        FROM schedules s
        JOIN screens sc ON s.screen_id = sc.id
        JOIN venues v ON sc.venue_id = v.id
        WHERE s.movie_id IN ($placeholders) 
        AND s.is_active = 1 
        AND s.show_date >= CURDATE()
        ORDER BY s.show_date, s.showtime
        LIMIT 20
    ";
    
    $schedule_stmt = $conn->prepare($schedule_sql);
    $types = str_repeat('i', count($display_movie_ids));
    $schedule_stmt->bind_param($types, ...$display_movie_ids);
    $schedule_stmt->execute();
    $schedules_result = $schedule_stmt->get_result();
    
    while ($row = $schedules_result->fetch_assoc()) {
        if (!isset($schedules[$row['movie_id']])) {
            $schedules[$row['movie_id']] = [];
        }
        $schedules[$row['movie_id']][] = $row;
    }
    $schedule_stmt->close();
}

// Get total counts
$total_movies = count($display_movies);
$movies_to_show = [];
if ($total_movies <= 5) {
    $movies_to_show = $display_movies;
} elseif ($total_movies <= 10) {
    $movies_to_show = array_slice($display_movies, 0, 4);
} else {
    $movies_to_show = array_slice($display_movies, 0, 5);
}

$conn->close();
?>

<div class="main-container">
    <!-- Slider Section -->
    <?php if (!empty($movies_with_posters)): ?>
    <div class="slider-container">
        <div class="slider" id="movieSlider">
            <?php 
            $display_posters = $movies_with_posters;
            if (count($display_posters) < 6) {
                $display_posters = array_merge($display_posters, $display_posters, $display_posters);
            }
            foreach ($display_posters as $movie): 
            ?>
            <div class="slide">
                <img src="<?php echo $movie['poster_url']; ?>" alt="<?php echo htmlspecialchars($movie['title']); ?>">
                <div class="slide-overlay">
                    <h3><?php echo htmlspecialchars($movie['title']); ?></h3>
                    <div class="slide-buttons">
                        <a href="<?php echo SITE_URL; ?>index.php?page=customer/movie-details&id=<?php echo $movie['id']; ?>" class="btn-slide">
                            <i class="fas fa-info-circle"></i> Details
                        </a>
                        <?php if (in_array($movie['id'], array_column($now_showing, 'id'))): ?>
                            <?php if (isset($_SESSION['user_id']) && $_SESSION['user_role'] === 'Customer'): ?>
                                <a href="<?php echo SITE_URL; ?>index.php?page=customer/booking&movie=<?php echo $movie['id']; ?>" class="btn-slide btn-slide-primary">
                                    <i class="fas fa-ticket-alt"></i> Book
                                </a>
                            <?php elseif (!isset($_SESSION['user_id'])): ?>
                                <a href="<?php echo SITE_URL; ?>index.php?page=login" class="btn-slide btn-slide-primary">
                                    <i class="fas fa-sign-in-alt"></i> Login
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <button class="slider-btn slider-btn-prev" onclick="moveSlide(-1)">❮</button>
        <button class="slider-btn slider-btn-next" onclick="moveSlide(1)">❯</button>
        <div class="slider-dots" id="sliderDots"></div>
    </div>
    <?php endif; ?>

    <!-- Filter Section -->
    <div class="filter-section">
        <a href="?filter=now-showing" class="filter-btn <?php echo $filter === 'now-showing' ? 'active' : ''; ?>">
            <i class="fas fa-play-circle"></i> Now Showing
        </a>
        <a href="?filter=coming-soon" class="filter-btn <?php echo $filter === 'coming-soon' ? 'active' : ''; ?>">
            <i class="fas fa-clock"></i> Coming Soon
        </a>
        <a href="<?php echo SITE_URL; ?>index.php?page=movies" class="filter-btn">
            <i class="fas fa-film"></i> All Movies
        </a>
    </div>

    <!-- Movies Section -->
    <div class="movies-section">
        <div class="section-header">
            <div>
                <h2><?php echo $display_title; ?></h2>
                <p class="section-subtitle"><?php echo $display_message; ?></p>
            </div>
            <?php if (!empty($display_movies) && $total_movies > count($movies_to_show)): ?>
                <a href="<?php echo SITE_URL; ?>index.php?page=movies&filter=<?php echo $filter; ?>" class="btn btn-secondary">
                    <i class="fas fa-film"></i> View All
                </a>
            <?php endif; ?>
        </div>
        
        <?php if (empty($display_movies)): ?>
            <div class="empty-movies">
                <i class="fas fa-film fa-3x"></i>
                <h3>No Movies Available</h3>
                <p>
                    <?php if ($filter === 'now-showing'): ?>
                        No movies are currently showing. Check out coming soon!
                    <?php else: ?>
                        No upcoming movies announced yet. Stay tuned for new releases!
                    <?php endif; ?>
                </p>
                <?php if ($filter === 'now-showing' && !empty($coming_soon)): ?>
                <a href="?filter=coming-soon" class="btn btn-primary" style="margin-top: 20px;">
                    <i class="fas fa-clock"></i> View Coming Soon
                </a>
                <?php elseif ($filter === 'coming-soon' && !empty($now_showing)): ?>
                <a href="?filter=now-showing" class="btn btn-primary" style="margin-top: 20px;">
                    <i class="fas fa-play-circle"></i> View Now Showing
                </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="movies-grid">
                <?php foreach ($movies_to_show as $movie): ?>
                <div class="movie-card">
                    <?php if (!empty($movie['poster_url'])): ?>
                        <img src="<?php echo $movie['poster_url']; ?>" 
                             alt="<?php echo htmlspecialchars($movie['title']); ?>">
                    <?php else: ?>
                        <div class="movie-poster-placeholder">
                            <i class="fas fa-film"></i>
                        </div>
                    <?php endif; ?>
                    
                    <div class="movie-badges">
                        <span class="rating-badge"><?php echo $movie['rating'] ?: 'PG'; ?></span>
                        <?php if ($movie['genre']): ?>
                        <span class="genre-badge">
                            <i class="fas fa-tag"></i> <?php echo explode(',', $movie['genre'])[0]; ?>
                        </span>
                        <?php endif; ?>
                        <?php if ($filter === 'coming-soon'): ?>
                        <span class="coming-soon-badge">
                            <i class="fas fa-hourglass-half"></i> Coming Soon
                        </span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="movie-content">
                        <h3><?php echo htmlspecialchars($movie['title']); ?></h3>
                        
                        <div class="movie-info">
                            <?php if ($movie['duration']): ?>
                            <div class="info-item">
                                <i class="fas fa-clock"></i> <?php echo $movie['duration']; ?>
                            </div>
                            <?php endif; ?>
                            <?php if ($movie['genre']): ?>
                            <div class="info-item">
                                <i class="fas fa-film"></i> <?php echo htmlspecialchars($movie['genre']); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($movie['description']): ?>
                        <div class="movie-description">
                            <p><?php echo substr(htmlspecialchars($movie['description']), 0, 100); ?><?php if (strlen($movie['description']) > 100): ?>...<?php endif; ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($filter === 'now-showing' && isset($schedules[$movie['id']]) && !empty($schedules[$movie['id']])): ?>
                        <div class="showtime-preview">
                            <i class="fas fa-clock"></i> 
                            <?php 
                            $first_schedule = $schedules[$movie['id']][0];
                            echo date('M d', strtotime($first_schedule['show_date'])) . ' • ' . date('h:i A', strtotime($first_schedule['showtime']));
                            ?>
                            <?php if (count($schedules[$movie['id']]) > 1): ?>
                            <span class="more-showtimes">+<?php echo count($schedules[$movie['id']]) - 1; ?> more</span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="movie-buttons">
                            <?php if (!empty($movie['trailer_url'])): ?>
                            <a href="<?php echo $movie['trailer_url']; ?>" target="_blank" class="btn btn-trailer" title="Watch Trailer">
                                <i class="fab fa-youtube"></i>
                            </a>
                            <?php endif; ?>
                            <a href="<?php echo SITE_URL; ?>index.php?page=customer/movie-details&id=<?php echo $movie['id']; ?>" class="btn btn-secondary" title="View Full Movie Details">
                                <i class="fas fa-info-circle"></i> Details
                            </a>
                            <?php if (isset($_SESSION['user_id']) && $_SESSION['user_role'] === 'Customer' && $filter === 'now-showing'): ?>
                                <a href="<?php echo SITE_URL; ?>index.php?page=customer/booking&movie=<?php echo $movie['id']; ?>" class="btn btn-primary">
                                    <i class="fas fa-ticket-alt"></i> Book
                                </a>
                            <?php elseif (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'Admin'): ?>
                                <a href="<?php echo SITE_URL; ?>index.php?page=admin/dashboard" class="btn btn-primary">
                                    <i class="fas fa-shield-alt"></i> Admin
                                </a>
                            <?php elseif (!isset($_SESSION['user_id']) && $filter === 'now-showing'): ?>
                                <a href="<?php echo SITE_URL; ?>index.php?page=login" class="btn btn-primary">
                                    <i class="fas fa-sign-in-alt"></i> Login
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php if ($total_movies > count($movies_to_show)): ?>
            <div class="movies-count">
                <p>Showing <?php echo count($movies_to_show); ?> of <?php echo $total_movies; ?> movies</p>
                <a href="<?php echo SITE_URL; ?>index.php?page=movies&filter=<?php echo $filter; ?>">
                    <i class="fas fa-arrow-right"></i> View all <?php echo $total_movies; ?> movies
                </a>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Features Section -->
    <div class="features-section">
        <h2>Why Choose Us?</h2>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-film"></i></div>
                <h3>Latest Movies</h3>
                <p>Get access to the newest releases and blockbuster hits</p>
                <div class="feature-info">
                    <i class="fas fa-info-circle"></i> Click <strong>Details</strong> on any movie to see full information including synopsis, cast, duration, and trailer
                </div>
                <?php if (!empty($all_movies)): ?>
                <a href="<?php echo SITE_URL; ?>index.php?page=movies" class="feature-link">
                    Browse All Movies <i class="fas fa-arrow-right"></i>
                </a>
                <?php endif; ?>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-ticket-alt"></i></div>
                <h3>Easy Booking</h3>
                <p>Simple and fast ticket booking process in just a few clicks</p>
                <div class="feature-info">
                    <i class="fas fa-info-circle"></i> Select your movie, choose showtime, pick seats, and confirm - all in under 2 minutes!
                </div>
                <?php if (isset($_SESSION['user_id']) && $_SESSION['user_role'] === 'Customer'): ?>
                <a href="<?php echo SITE_URL; ?>index.php?page=customer/booking" class="feature-link">
                    Book Now <i class="fas fa-arrow-right"></i>
                </a>
                <?php elseif (!isset($_SESSION['user_id'])): ?>
                <a href="<?php echo SITE_URL; ?>index.php?page=register" class="feature-link">
                    Get Started <i class="fas fa-arrow-right"></i>
                </a>
                <?php endif; ?>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-chair"></i></div>
                <h3>Seat Selection</h3>
                <p>Choose your preferred seats with our interactive seat map</p>
                <div class="feature-info">
                    <i class="fas fa-info-circle"></i> View real-time seat availability and select from Standard, Premium, or Sweet Spot options
                </div>
                <?php if (isset($_SESSION['user_id']) && $_SESSION['user_role'] === 'Customer'): ?>
                <a href="<?php echo SITE_URL; ?>index.php?page=customer/booking" class="feature-link">
                    Select Seats <i class="fas fa-arrow-right"></i>
                </a>
                <?php endif; ?>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-shield-alt"></i></div>
                <h3>Secure Payment</h3>
                <p>Safe and secure payment processing for peace of mind</p>
                <div class="feature-info">
                    <i class="fas fa-info-circle"></i> All transactions are encrypted and protected. Multiple payment options available
                </div>
                <?php if (isset($_SESSION['user_id']) && $_SESSION['user_role'] === 'Customer'): ?>
                <a href="<?php echo SITE_URL; ?>index.php?page=customer/my-bookings" class="feature-link">
                    View Bookings <i class="fas fa-arrow-right"></i>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- CTA Section for Guests -->
    <?php if (!isset($_SESSION['user_id'])): ?>
    <div class="cta-section">
        <h2>Ready to Book Your Movie?</h2>
        <p>Join thousands of movie lovers who book their tickets with us. Create an account and start your cinematic journey today!</p>
        <div class="cta-buttons">
            <a href="<?php echo SITE_URL; ?>index.php?page=register" class="btn btn-primary">
                <i class="fas fa-user-plus"></i> Sign Up Now
            </a>
            <a href="<?php echo SITE_URL; ?>index.php?page=login" class="btn btn-secondary">
                <i class="fas fa-sign-in-alt"></i> Login to Account
            </a>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
.main-container { max-width: 1200px; margin: 0 auto; padding: 20px; }

/* Slider Styles */
.slider-container {
    position: relative;
    width: 100%;
    height: 500px;
    overflow: hidden;
    border-radius: 20px;
    margin-bottom: 40px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
}

.slider {
    display: flex;
    width: 100%;
    height: 100%;
    transition: transform 0.5s ease-in-out;
}

.slide {
    min-width: 100%;
    height: 100%;
    position: relative;
}

.slide img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.slide-overlay {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: linear-gradient(to top, rgba(0, 0, 0, 0.9), transparent);
    color: white;
    padding: 40px;
    transform: translateY(100%);
    transition: transform 0.3s ease;
}

.slide:hover .slide-overlay {
    transform: translateY(0);
}

.slide-overlay h3 {
    font-size: 1.8rem;
    font-weight: 800;
    margin-bottom: 15px;
    color: white;
}

.slide-buttons {
    display: flex;
    gap: 15px;
}

.btn-slide {
    padding: 10px 20px;
    background: rgba(255, 255, 255, 0.2);
    color: white;
    text-decoration: none;
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border: 2px solid rgba(255, 255, 255, 0.3);
}

.btn-slide:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: translateY(-2px);
}

.btn-slide-primary {
    background: linear-gradient(135deg, var(--primary-red) 0%, var(--dark-red) 100%);
    border: none;
}

.btn-slide-primary:hover {
    background: linear-gradient(135deg, var(--dark-red) 0%, var(--deep-red) 100%);
}

.slider-btn {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(0, 0, 0, 0.5);
    color: white;
    border: none;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    font-size: 1.5rem;
    cursor: pointer;
    transition: all 0.3s ease;
    z-index: 10;
    display: flex;
    align-items: center;
    justify-content: center;
}

.slider-btn:hover {
    background: rgba(226, 48, 32, 0.8);
    transform: translateY(-50%) scale(1.1);
}

.slider-btn-prev {
    left: 20px;
}

.slider-btn-next {
    right: 20px;
}

.slider-dots {
    position: absolute;
    bottom: 20px;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    gap: 10px;
    z-index: 10;
}

.dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.5);
    cursor: pointer;
    transition: all 0.3s ease;
}

.dot.active {
    background: var(--primary-red);
    transform: scale(1.2);
}

.dot:hover {
    background: white;
}

/* Filter Section */
.filter-section {
    display: flex;
    justify-content: center;
    gap: 15px;
    margin-bottom: 40px;
    flex-wrap: wrap;
}

.filter-btn {
    padding: 12px 30px;
    border-radius: 30px;
    text-decoration: none;
    font-weight: 700;
    font-size: 1.1rem;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(255, 255, 255, 0.1);
    color: white;
    border: 2px solid rgba(226, 48, 32, 0.3);
}

.filter-btn:hover {
    background: rgba(226, 48, 32, 0.2);
    border-color: var(--primary-red);
    transform: translateY(-2px);
}

.filter-btn.active {
    background: linear-gradient(135deg, var(--primary-red) 0%, var(--dark-red) 100%);
    border-color: transparent;
    box-shadow: 0 4px 15px rgba(226, 48, 32, 0.3);
}

/* Movies Section */
.movies-section { margin-bottom: 60px; }

.section-header { 
    display: flex; 
    justify-content: space-between; 
    align-items: flex-end; 
    margin-bottom: 30px; 
}

.section-header h2 { 
    color: white; 
    font-size: 2rem; 
    font-weight: 800; 
    margin-bottom: 5px; 
}

.section-subtitle { 
    color: var(--pale-red); 
    font-size: 1rem; 
}

.movies-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 30px;
}

.movie-card {
    background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%);
    border-radius: 15px;
    overflow: hidden;
    transition: all 0.3s ease;
    border: 1px solid rgba(226, 48, 32, 0.2);
    display: flex;
    flex-direction: column;
    height: 100%;
    position: relative;
}

.movie-card:hover { 
    transform: translateY(-10px); 
    box-shadow: 0 20px 40px rgba(226, 48, 32, 0.2); 
    border-color: #e23020; 
}

.movie-card img { 
    width: 100%; 
    height: 320px; 
    object-fit: cover; 
}

.movie-poster-placeholder {
    width: 100%;
    height: 320px;
    background: linear-gradient(135deg, rgba(226, 48, 32, 0.1), rgba(193, 27, 24, 0.2));
    display: flex;
    align-items: center;
    justify-content: center;
}

.movie-poster-placeholder i { 
    font-size: 3rem; 
    color: rgba(255, 255, 255, 0.3); 
}

.movie-badges {
    position: absolute;
    top: 15px;
    right: 15px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.rating-badge {
    background: var(--primary-red);
    color: white;
    font-weight: 700;
    font-size: 0.8rem;
    padding: 6px 12px;
    border-radius: 20px;
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
    text-align: center;
    min-width: 40px;
}

.genre-badge {
    background: rgba(0,0,0,0.7);
    color: white;
    font-weight: 600;
    font-size: 0.75rem;
    padding: 5px 10px;
    border-radius: 15px;
    display: flex;
    align-items: center;
    gap: 5px;
}

.coming-soon-badge {
    background: rgba(241, 196, 15, 0.2);
    color: #f1c40f;
    padding: 5px 10px;
    border-radius: 15px;
    font-size: 0.7rem;
    font-weight: 700;
    border: 1px solid rgba(241, 196, 15, 0.3);
}

.movie-content {
    padding: 25px;
    flex: 1;
    display: flex;
    flex-direction: column;
}

.movie-content h3 {
    color: white;
    font-size: 1.3rem;
    font-weight: 800;
    margin-bottom: 15px;
    line-height: 1.4;
    min-height: 3.2em;
}

.movie-info { 
    margin-bottom: 15px; 
}

.info-item {
    display: flex;
    align-items: center;
    gap: 8px;
    color: rgba(255,255,255,0.8);
    font-size: 0.9rem;
    margin-bottom: 8px;
}

.movie-description { 
    flex: 1; 
    margin-bottom: 15px; 
}

.movie-description p {
    color: rgba(255,255,255,0.7);
    font-size: 0.95rem;
    line-height: 1.6;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.showtime-preview {
    background: rgba(52, 152, 219, 0.2);
    padding: 8px 12px;
    border-radius: 8px;
    margin-bottom: 15px;
    font-size: 0.8rem;
    color: #3498db;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.more-showtimes {
    background: rgba(52, 152, 219, 0.3);
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.7rem;
}

.movie-buttons {
    display: flex;
    gap: 5px;
    margin-top: auto;
}

.movie-buttons .btn {
    padding: 8px;
    text-align: center;
    font-size: 0.8rem;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex: 1;
}

.movie-buttons .btn-trailer {
    background: rgba(255, 0, 0, 0.2);
    color: #ff0000;
    border: 2px solid rgba(255, 0, 0, 0.3);
    max-width: 45px;
}

.movie-buttons .btn-trailer:hover {
    background: rgba(255, 0, 0, 0.3);
    border-color: #ff0000;
}

/* Empty State */
.empty-movies {
    text-align: center;
    padding: 60px;
    background: rgba(226, 48, 32, 0.05);
    border-radius: 15px;
    border: 2px dashed rgba(226, 48, 32, 0.3);
}

.empty-movies i { 
    color: var(--primary-red); 
    margin-bottom: 20px; 
    opacity: 0.8; 
}

.empty-movies h3 { 
    color: white; 
    margin-bottom: 10px; 
    font-size: 1.5rem; 
}

.empty-movies p { 
    color: var(--pale-red); 
    max-width: 400px; 
    margin: 0 auto; 
}

.movies-count {
    text-align: center;
    margin-top: 30px;
    padding: 15px;
    background: rgba(226, 48, 32, 0.05);
    border-radius: 10px;
    border: 1px solid rgba(226, 48, 32, 0.2);
}

.movies-count p { 
    color: var(--pale-red); 
    margin-bottom: 5px; 
}

.movies-count a {
    color: var(--light-red);
    text-decoration: none;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

/* Features Section */
.features-section { 
    margin-top: 60px; 
    text-align: center; 
}

.features-section h2 { 
    color: white; 
    margin-bottom: 40px; 
    font-size: 2rem; 
    font-weight: 800; 
}

.features-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 30px;
    margin-top: 30px;
}

.feature-card {
    background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%);
    padding: 30px;
    border-radius: 15px;
    border: 1px solid rgba(226, 48, 32, 0.2);
    transition: all 0.3s ease;
    display: flex;
    flex-direction: column;
    height: 100%;
}

.feature-card:hover { 
    transform: translateY(-5px); 
    border-color: var(--primary-red); 
}

.feature-icon { 
    font-size: 2.5rem; 
    color: var(--primary-red); 
    margin-bottom: 15px; 
}

.feature-card h3 { 
    color: white; 
    margin-bottom: 10px; 
    font-size: 1.3rem; 
}

.feature-card p { 
    color: var(--pale-red); 
    line-height: 1.6; 
    margin-bottom: 15px; 
}

.feature-info {
    background: rgba(255, 255, 255, 0.05);
    padding: 12px;
    border-radius: 8px;
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.9rem;
    line-height: 1.5;
    margin-bottom: 20px;
    border-left: 3px solid var(--primary-red);
    text-align: left;
}

.feature-info i { 
    color: var(--primary-red); 
    margin-right: 5px; 
}

.feature-link {
    color: var(--light-red);
    text-decoration: none;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    margin-top: auto;
    padding: 10px 0;
    border-top: 1px solid rgba(226, 48, 32, 0.2);
}

.feature-link:hover { 
    color: white; 
    gap: 10px; 
}

/* CTA Section */
.cta-section {
    text-align: center;
    margin-top: 80px;
    padding: 50px;
    background: linear-gradient(135deg, rgba(226, 48, 32, 0.1), rgba(193, 27, 24, 0.2));
    border-radius: 20px;
    border: 2px solid rgba(226, 48, 32, 0.3);
}

.cta-section h2 { 
    color: white; 
    margin-bottom: 20px; 
    font-size: 2.2rem; 
    font-weight: 800; 
}

.cta-section p { 
    color: var(--pale-red); 
    font-size: 1.1rem; 
    margin-bottom: 30px; 
    max-width: 600px; 
    margin-left: auto; 
    margin-right: auto; 
}

.cta-buttons { 
    display: flex; 
    gap: 15px; 
    justify-content: center; 
    flex-wrap: wrap; 
}

/* Buttons */
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

/* Responsive */
@media (max-width: 768px) {
    .slider-container { height: 350px; }
    .slide-overlay { padding: 20px; }
    .slide-overlay h3 { font-size: 1.3rem; }
    .movies-grid { grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px; }
    .features-grid { grid-template-columns: 1fr; }
    .filter-section { flex-direction: column; align-items: center; }
    .filter-btn { width: 200px; justify-content: center; }
    .section-header { flex-direction: column; gap: 15px; align-items: flex-start; }
}

@media (max-width: 576px) {
    .slider-container { height: 250px; }
    .slider-btn { width: 35px; height: 35px; font-size: 1rem; }
    .movies-grid { grid-template-columns: 1fr; }
    .movie-buttons .btn { padding: 6px; font-size: 0.75rem; }
}
</style>

<script>
let currentSlide = 0;
const slides = document.querySelectorAll('.slide');
const totalSlides = slides.length;
let autoSlideInterval;

function showSlide(index) {
    if (index >= totalSlides) {
        currentSlide = 0;
    } else if (index < 0) {
        currentSlide = totalSlides - 1;
    } else {
        currentSlide = index;
    }
    
    const slider = document.getElementById('movieSlider');
    if (slider) {
        slider.style.transform = `translateX(-${currentSlide * 100}%)`;
    }
    
    const dots = document.querySelectorAll('.dot');
    dots.forEach((dot, i) => {
        dot.classList.toggle('active', i === currentSlide % (Math.max(1, Math.floor(totalSlides / 3))));
    });
}

function moveSlide(direction) {
    showSlide(currentSlide + direction);
    resetAutoSlide();
}

function createDots() {
    const dotsContainer = document.getElementById('sliderDots');
    if (!dotsContainer || totalSlides === 0) return;
    
    const uniqueSlides = Math.min(6, totalSlides);
    dotsContainer.innerHTML = '';
    for (let i = 0; i < uniqueSlides; i++) {
        const dot = document.createElement('span');
        dot.classList.add('dot');
        dot.onclick = () => {
            showSlide(i);
            resetAutoSlide();
        };
        dotsContainer.appendChild(dot);
    }
    showSlide(0);
}

function startAutoSlide() {
    if (totalSlides === 0) return;
    autoSlideInterval = setInterval(() => {
        moveSlide(1);
    }, 5000);
}

function resetAutoSlide() {
    clearInterval(autoSlideInterval);
    startAutoSlide();
}

document.addEventListener('DOMContentLoaded', function() {
    if (slides.length > 0) {
        createDots();
        startAutoSlide();
    }
    
    const movieCards = document.querySelectorAll('.movie-card');
    movieCards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
        card.style.animation = 'fadeInUp 0.5s ease forwards';
        card.style.opacity = '0';
    });
    
    const featureCards = document.querySelectorAll('.feature-card');
    featureCards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
        card.style.animation = 'fadeInUp 0.5s ease forwards';
        card.style.opacity = '0';
    });
    
    const style = document.createElement('style');
    style.textContent = `
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .movie-card, .feature-card { transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
    `;
    document.head.appendChild(style);
});
</script>

<?php require_once $root_dir . '/partials/footer.php'; ?>