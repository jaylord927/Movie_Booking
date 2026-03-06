<?php
$root_dir = dirname(__DIR__);
require_once $root_dir . '/includes/config.php';
require_once $root_dir . '/includes/functions.php';
require_once $root_dir . '/includes/database.php';

require_once $root_dir . '/partials/header.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_suggestion'])) {
    $suggestion = sanitize_input($_POST['suggestion']);
    $name = isset($_POST['name']) ? sanitize_input($_POST['name']) : '';
    $email = isset($_POST['email']) ? sanitize_input($_POST['email']) : '';
    
    if (empty($suggestion)) {
        $error = "Please enter your suggestion!";
    } else {
        $conn = get_db_connection();
        
        $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
        $user_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : $name;
        $user_email = isset($_SESSION['user_email']) ? $_SESSION['user_email'] : $email;
        
        $stmt = $conn->prepare("INSERT INTO suggestions (user_id, user_name, user_email, suggestion) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $user_id, $user_name, $user_email, $suggestion);
        
        if ($stmt->execute()) {
            $success = "Thank you for your suggestion! We'll review it soon.";
            $_POST = array();
        } else {
            $error = "Failed to submit suggestion. Please try again.";
        }
        
        $stmt->close();
        $conn->close();
    }
}
?>

<div class="about-container" style="max-width: 1200px; margin: 0 auto; padding: 40px 20px;">
    <div style="text-align: center; margin-bottom: 60px;">
        <h1 style="color: white; font-size: 3rem; margin-bottom: 20px; font-weight: 800;">About MovieTicketBooking</h1>
        <p style="color: var(--pale-red); font-size: 1.2rem; max-width: 700px; margin: 0 auto; line-height: 1.6;">
            Your premier destination for seamless movie ticket booking. We're passionate about bringing the magic of cinema to your fingertips.
        </p>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 40px; margin-bottom: 60px;">
        <div style="background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%); padding: 40px; border-radius: 20px; border: 1px solid rgba(226, 48, 32, 0.3); text-align: center;">
            <div style="font-size: 3rem; color: var(--primary-red); margin-bottom: 20px;">🎯</div>
            <h2 style="color: white; font-size: 1.8rem; margin-bottom: 15px; font-weight: 700;">Our Mission</h2>
            <p style="color: rgba(255,255,255,0.9); line-height: 1.8; font-size: 1rem;">
                To provide movie enthusiasts with the easiest, fastest, and most reliable platform for booking movie tickets, while ensuring a seamless and enjoyable experience from selection to seat.
            </p>
        </div>

        <div style="background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%); padding: 40px; border-radius: 20px; border: 1px solid rgba(226, 48, 32, 0.3); text-align: center;">
            <div style="font-size: 3rem; color: var(--primary-red); margin-bottom: 20px;">👁️</div>
            <h2 style="color: white; font-size: 1.8rem; margin-bottom: 15px; font-weight: 700;">Our Vision</h2>
            <p style="color: rgba(255,255,255,0.9); line-height: 1.8; font-size: 1rem;">
                To become the go-to movie ticketing platform in the Philippines, connecting movie lovers with their favorite films and creating unforgettable cinematic experiences.
            </p>
        </div>
    </div>

    <div style="margin-bottom: 60px;">
        <h2 style="color: white; font-size: 2.2rem; text-align: center; margin-bottom: 40px; font-weight: 800;">Why Choose Us?</h2>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 30px;">
            <div style="background: rgba(255,255,255,0.05); padding: 30px; border-radius: 15px; border: 1px solid rgba(226, 48, 32, 0.2);">
                <div style="font-size: 2.5rem; color: var(--primary-red); margin-bottom: 15px;">🎬</div>
                <h3 style="color: white; font-size: 1.3rem; margin-bottom: 10px; font-weight: 700;">Latest Movies</h3>
                <p style="color: var(--pale-red); line-height: 1.6;">Access to the newest releases and blockbuster hits with complete movie information.</p>
            </div>

            <div style="background: rgba(255,255,255,0.05); padding: 30px; border-radius: 15px; border: 1px solid rgba(226, 48, 32, 0.2);">
                <div style="font-size: 2.5rem; color: var(--primary-red); margin-bottom: 15px;">💺</div>
                <h3 style="color: white; font-size: 1.3rem; margin-bottom: 10px; font-weight: 700;">Easy Seat Selection</h3>
                <p style="color: var(--pale-red); line-height: 1.6;">Interactive seat maps with real-time availability for Standard, Premium, and Sweet Spot options.</p>
            </div>

            <div style="background: rgba(255,255,255,0.05); padding: 30px; border-radius: 15px; border: 1px solid rgba(226, 48, 32, 0.2);">
                <div style="font-size: 2.5rem; color: var(--primary-red); margin-bottom: 15px;">⚡</div>
                <h3 style="color: white; font-size: 1.3rem; margin-bottom: 10px; font-weight: 700;">Quick Booking</h3>
                <p style="color: var(--pale-red); line-height: 1.6;">Book your tickets in under 2 minutes with our streamlined booking process.</p>
            </div>

            <div style="background: rgba(255,255,255,0.05); padding: 30px; border-radius: 15px; border: 1px solid rgba(226, 48, 32, 0.2);">
                <div style="font-size: 2.5rem; color: var(--primary-red); margin-bottom: 15px;">🔒</div>
                <h3 style="color: white; font-size: 1.3rem; margin-bottom: 10px; font-weight: 700;">Secure Payments</h3>
                <p style="color: var(--pale-red); line-height: 1.6;">Your transactions are safe with our encrypted payment processing system.</p>
            </div>
        </div>
    </div>

    <!-- How Our Scheduling Works -->
    <div style="background: linear-gradient(135deg, rgba(226, 48, 32, 0.1), rgba(193, 27, 24, 0.2)); border-radius: 20px; padding: 50px; margin-bottom: 60px; border: 2px solid rgba(226, 48, 32, 0.3);">
        <h2 style="color: white; font-size: 2rem; text-align: center; margin-bottom: 40px; font-weight: 800;">How Our Cinema Scheduling Works</h2>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px;">
            <div style="background: rgba(0,0,0,0.3); padding: 30px; border-radius: 15px; border: 1px solid rgba(226, 48, 32, 0.3);">
                <div style="font-size: 2.5rem; color: var(--primary-red); margin-bottom: 15px; text-align: center;">🎪</div>
                <h3 style="color: white; font-size: 1.3rem; margin-bottom: 15px; font-weight: 700; text-align: center;">One Movie Per Cinema</h3>
                <p style="color: var(--pale-red); line-height: 1.6; margin-bottom: 15px;">
                    Each cinema screens only one movie at a time to ensure the best viewing experience. Our scheduling system carefully plans showtimes to avoid overlaps.
                </p>
                <div style="background: rgba(255,255,255,0.05); padding: 15px; border-radius: 8px; margin-top: 10px;">
                    <div style="display: flex; justify-content: space-between; color: white; font-size: 0.9rem; margin-bottom: 5px;">
                        <span>Cinema 1:</span>
                        <span style="color: var(--primary-red);">Movie A - 8:00 AM</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; color: white; font-size: 0.9rem;">
                        <span>Cinema 1:</span>
                        <span style="color: var(--primary-red);">Movie B - 12:30 PM</span>
                    </div>
                </div>
            </div>

            <div style="background: rgba(0,0,0,0.3); padding: 30px; border-radius: 15px; border: 1px solid rgba(226, 48, 32, 0.3);">
                <div style="font-size: 2.5rem; color: var(--primary-red); margin-bottom: 15px; text-align: center;">⏰</div>
                <h3 style="color: white; font-size: 1.3rem; margin-bottom: 15px; font-weight: 700; text-align: center;">2-Hour Break Between Shows</h3>
                <p style="color: var(--pale-red); line-height: 1.6; margin-bottom: 15px;">
                    After each movie ends, we schedule a 2-hour break for cleaning, preparation, and to give our staff time to ensure the cinema is perfect for the next audience.
                </p>
                <div style="background: rgba(255,255,255,0.05); padding: 15px; border-radius: 8px;">
                    <div style="display: flex; align-items: center; gap: 10px; color: white; font-size: 0.9rem;">
                        <i class="fas fa-film"></i>
                        <span>8:00 AM - 10:30 AM (Movie)</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px; color: #f39c12; margin: 5px 0;">
                        <i class="fas fa-clock"></i>
                        <span>10:30 AM - 12:30 PM (Break)</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px; color: white;">
                        <i class="fas fa-film"></i>
                        <span>12:30 PM - 3:00 PM (Next Movie)</span>
                    </div>
                </div>
            </div>

            <div style="background: rgba(0,0,0,0.3); padding: 30px; border-radius: 15px; border: 1px solid rgba(226, 48, 32, 0.3);">
                <div style="font-size: 2.5rem; color: var(--primary-red); margin-bottom: 15px; text-align: center;">🪑</div>
                <h3 style="color: white; font-size: 1.3rem; margin-bottom: 15px; font-weight: 700; text-align: center;">Limited Seats Per Cinema</h3>
                <p style="color: var(--pale-red); line-height: 1.6; margin-bottom: 15px;">
                    Each cinema has a fixed number of seats. Once all seats are booked, the showtime is marked as "Sold Out" and no further bookings are accepted.
                </p>
                <div style="background: rgba(255,255,255,0.05); padding: 15px; border-radius: 8px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <span style="color: white;">Total Seats:</span>
                        <span style="color: white; font-weight: 700;">40</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <span style="color: white;">Available:</span>
                        <span style="color: #2ecc71; font-weight: 700;">12</span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: white;">Status:</span>
                        <span style="background: #2ecc71; color: white; padding: 2px 10px; border-radius: 15px; font-size: 0.8rem;">Available</span>
                    </div>
                </div>
            </div>

            <div style="background: rgba(0,0,0,0.3); padding: 30px; border-radius: 15px; border: 1px solid rgba(226, 48, 32, 0.3);">
                <div style="font-size: 2.5rem; color: var(--primary-red); margin-bottom: 15px; text-align: center;">✅</div>
                <h3 style="color: white; font-size: 1.3rem; margin-bottom: 15px; font-weight: 700; text-align: center;">Real-Time Availability Check</h3>
                <p style="color: var(--pale-red); line-height: 1.6; margin-bottom: 15px;">
                    Our system checks seat availability in real-time. If you try to book more seats than available, your booking will not proceed.
                </p>
                <div style="background: rgba(255,255,255,0.05); padding: 15px; border-radius: 8px;">
                    <div style="background: rgba(231, 76, 60, 0.2); padding: 10px; border-radius: 6px; border-left: 3px solid #e74c3c;">
                        <div style="color: #e74c3c; font-weight: 600; margin-bottom: 5px;">
                            <i class="fas fa-exclamation-triangle"></i> Booking Failed
                        </div>
                        <div style="color: white; font-size: 0.9rem;">
                            Only 2 seats left. Cannot book 4 seats.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div style="margin-top: 40px; text-align: center;">
            <div style="display: inline-block; background: rgba(0,0,0,0.3); padding: 20px 30px; border-radius: 50px; border: 1px solid rgba(226, 48, 32, 0.3);">
                <div style="display: flex; gap: 30px; flex-wrap: wrap; justify-content: center;">
                    <div>
                        <span style="background: #2ecc71; width: 20px; height: 20px; display: inline-block; border-radius: 4px; margin-right: 8px;"></span>
                        <span style="color: white;">Available</span>
                    </div>
                    <div>
                        <span style="background: #e74c3c; width: 20px; height: 20px; display: inline-block; border-radius: 4px; margin-right: 8px;"></span>
                        <span style="color: white;">Sold Out</span>
                    </div>
                    <div>
                        <span style="background: #f39c12; width: 20px; height: 20px; display: inline-block; border-radius: 4px; margin-right: 8px;"></span>
                        <span style="color: white;">Few Seats Left</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div style="background: linear-gradient(135deg, rgba(226, 48, 32, 0.1), rgba(193, 27, 24, 0.2)); border-radius: 20px; padding: 50px; margin-bottom: 60px; border: 2px solid rgba(226, 48, 32, 0.3);">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 50px; align-items: center;">
            <div>
                <h2 style="color: white; font-size: 2rem; margin-bottom: 20px; font-weight: 800;">Our Story</h2>
                <p style="color: rgba(255,255,255,0.9); line-height: 1.8; margin-bottom: 20px;">
                    Founded in 2026 in the heart of Ward II, Minglanilla, Cebu City, Philippines, MovieTicketBooking was born from a simple yet powerful idea: making movie ticket booking as enjoyable as watching the film itself. It all started as a class project assigned by our maestro. With four members in our group—all of us passionate movie lovers—we knew right away what we wanted to create. Since we were all huge fans of films, we decided to build something close to our hearts: a movie ticket booking platform. What began as a simple academic requirement quickly became something more meaningful.
                </p>
                <p style="color: rgba(255,255,255,0.9); line-height: 1.8;">
                    Today, MovieTicketBooking has grown from a small school project into a trusted platform serving thousands of movie enthusiasts across the Philippines. We partner with major cinema chains to bring you the best selection of movies, showtimes, and seats. Our team works tirelessly to ensure that every booking experience is smooth, secure, and satisfying—because for us, it's not just about booking tickets, it's about sharing the magic of cinema.
                </p>
            </div>
            <div style="text-align: center;">
                <div style="background: linear-gradient(135deg, var(--primary-red) 0%, var(--dark-red) 100%); width: 300px; height: 300px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto; box-shadow: 0 20px 40px rgba(226, 48, 32, 0.3);">
                    <span style="font-size: 5rem;">🎬</span>
                </div>
            </div>
        </div>
    </div>

    <div style="margin-bottom: 60px;">
        <h2 style="color: white; font-size: 2.2rem; text-align: center; margin-bottom: 40px; font-weight: 800;">Meet Our Team</h2>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 30px;">
            <div style="text-align: center;">
                <div style="width: 150px; height: 150px; background: linear-gradient(135deg, var(--primary-red) 0%, var(--dark-red) 100%); border-radius: 50%; margin: 0 auto 20px; display: flex; align-items: center; justify-content: center; border: 4px solid rgba(255,255,255,0.2);">
                    <span style="font-size: 3rem; color: white;">JL</span>
                </div>
                <h3 style="color: white; font-size: 1.2rem; font-weight: 700;">Jaylord Lapuña</h3>
                <p style="color: var(--pale-red);">Lead Developer</p>
            </div>

            <div style="text-align: center;">
                <div style="width: 150px; height: 150px; background: linear-gradient(135deg, var(--primary-red) 0%, var(--dark-red) 100%); border-radius: 50%; margin: 0 auto 20px; display: flex; align-items: center; justify-content: center; border: 4px solid rgba(255,255,255,0.2);">
                    <span style="font-size: 3rem; color: white;">DC</span>
                </div>
                <h3 style="color: white; font-size: 1.2rem; font-weight: 700;">Denise Caña</h3>
                <p style="color: var(--pale-red);">UI Designer</p>
            </div>

            <div style="text-align: center;">
                <div style="width: 150px; height: 150px; background: linear-gradient(135deg, var(--primary-red) 0%, var(--dark-red) 100%); border-radius: 50%; margin: 0 auto 20px; display: flex; align-items: center; justify-content: center; border: 4px solid rgba(255,255,255,0.2);">
                    <span style="font-size: 3rem; color: white;">MP</span>
                </div>
                <h3 style="color: white; font-size: 1.2rem; font-weight: 700;">Marilyn Papellero</h3>
                <p style="color: var(--pale-red);">UI/UX Designer</p>
            </div>

            <div style="text-align: center;">
                <div style="width: 150px; height: 150px; background: linear-gradient(135deg, var(--primary-red) 0%, var(--dark-red) 100%); border-radius: 50%; margin: 0 auto 20px; display: flex; align-items: center; justify-content: center; border: 4px solid rgba(255,255,255,0.2);">
                    <span style="font-size: 3rem; color: white;">MC</span>
                </div>
                <h3 style="color: white; font-size: 1.2rem; font-weight: 700;">Martin Contreras</h3>
                <p style="color: var(--pale-red);">Database Handler</p>
            </div>
        </div>
    </div>

    <div style="background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%); border-radius: 20px; padding: 50px; border: 2px solid rgba(226, 48, 32, 0.3);">
        <div style="text-align: center; margin-bottom: 30px;">
            <div style="font-size: 4rem; color: var(--primary-red); margin-bottom: 20px;">💡</div>
            <h2 style="color: white; font-size: 2.5rem; margin-bottom: 15px; font-weight: 800;">Have an Idea? We'd Love to Hear It!</h2>
            <p style="color: var(--pale-red); font-size: 1.1rem; max-width: 600px; margin: 0 auto;">
                Your suggestions help us improve and bring new features to life. Share your ideas with us!
            </p>
        </div>

        <?php if ($success): ?>
        <div style="background: rgba(46, 204, 113, 0.2); color: #2ecc71; padding: 15px 20px; border-radius: 10px; margin-bottom: 25px; text-align: center; border: 1px solid rgba(46, 204, 113, 0.3);">
            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div style="background: rgba(226, 48, 32, 0.2); color: #ff9999; padding: 15px 20px; border-radius: 10px; margin-bottom: 25px; text-align: center; border: 1px solid rgba(226, 48, 32, 0.3);">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="" style="max-width: 600px; margin: 0 auto;" id="suggestionForm">
            <?php if (!isset($_SESSION['user_id'])): ?>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div>
                    <label style="display: block; color: white; font-weight: 600; margin-bottom: 8px;">Your Name</label>
                    <input type="text" name="name" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" 
                           style="width: 100%; padding: 14px 16px; background: rgba(255,255,255,0.08); border: 2px solid rgba(226, 48, 32, 0.3); border-radius: 10px; color: white; font-size: 1rem;">
                </div>
                <div>
                    <label style="display: block; color: white; font-weight: 600; margin-bottom: 8px;">Your Email</label>
                    <input type="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                           style="width: 100%; padding: 14px 16px; background: rgba(255,255,255,0.08); border: 2px solid rgba(226, 48, 32, 0.3); border-radius: 10px; color: white; font-size: 1rem;">
                </div>
            </div>
            <?php endif; ?>

            <div style="margin-bottom: 25px;">
                <label style="display: block; color: white; font-weight: 600; margin-bottom: 8px;">Your Suggestion *</label>
                <textarea name="suggestion" rows="5" required 
                          style="width: 100%; padding: 14px 16px; background: rgba(255,255,255,0.08); border: 2px solid rgba(226, 48, 32, 0.3); border-radius: 10px; color: white; font-size: 1rem; resize: vertical;"
                          placeholder="Tell us your idea..."><?php echo isset($_POST['suggestion']) ? htmlspecialchars($_POST['suggestion']) : ''; ?></textarea>
            </div>

            <div style="text-align: center;">
                <button type="submit" name="submit_suggestion" 
                        style="padding: 16px 40px; background: linear-gradient(135deg, var(--primary-red) 0%, var(--dark-red) 100%); color: white; border: none; border-radius: 50px; font-size: 1.1rem; font-weight: 700; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 6px 20px rgba(226, 48, 32, 0.3); display: inline-flex; align-items: center; gap: 10px;">
                    <i class="fas fa-lightbulb"></i> Submit a Suggestion
                </button>
            </div>
        </form>

        <div style="margin-top: 30px; text-align: center; color: var(--pale-red); font-size: 0.9rem;">
            <i class="fas fa-info-circle"></i> All suggestions are reviewed by our team. We may contact you for follow-up questions.
        </div>
    </div>
</div>

<style>
    .about-container {
        animation: fadeIn 0.5s ease;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    input:focus, textarea:focus, select:focus {
        outline: none;
        background: rgba(255, 255, 255, 0.12);
        border-color: var(--primary-red);
        box-shadow: 0 0 0 4px rgba(226, 48, 32, 0.2);
    }

    button:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 30px rgba(226, 48, 32, 0.4) !important;
    }

    @media (max-width: 768px) {
        .about-container {
            padding: 20px 15px;
        }
        
        h1 {
            font-size: 2.2rem !important;
        }
        
        .grid-2-col {
            grid-template-columns: 1fr !important;
        }
    }
</style>

<script>
document.getElementById('suggestionForm')?.addEventListener('submit', function(e) {
    const suggestion = document.querySelector('textarea[name="suggestion"]').value.trim();
    
    if (!suggestion) {
        e.preventDefault();
        alert('Please enter your suggestion!');
        return false;
    }
    
    return true;
});

setTimeout(() => {
    const alerts = document.querySelectorAll('[style*="background: rgba(46, 204, 113, 0.2)"], [style*="background: rgba(226, 48, 32, 0.2)"]');
    alerts.forEach(alert => {
        alert.style.transition = 'opacity 0.5s ease';
        alert.style.opacity = '0';
        setTimeout(() => {
            if (alert.parentNode) {
                alert.parentNode.removeChild(alert);
            }
        }, 500);
    });
}, 5000);
</script>

<?php
require_once $root_dir . '/partials/footer.php';
?>