<?php
// partials/footer.php

// Define SITE_URL if not defined
if (!defined('SITE_URL')) {
    define('SITE_URL', 'http://localhost/Movie/');
}
?>
    </main>
    
    <footer class="footer">
        <div class="footer-container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>Movie Ticketing</h3>
                    <p style="color: var(--pale-red); line-height: 1.6; font-size: 0.9rem;">
                        Your one-stop destination for booking movie tickets online. 
                        Experience the magic of cinema with our easy booking system.
                    </p>
                </div>
                
                <div class="footer-section">
                    <h3>Quick Links</h3>
                    <ul class="footer-links">
                        <li><a href="<?php echo SITE_URL; ?>"><i class="fas fa-home"></i> Home</a></li>
                        <?php if (isset($_SESSION['user_id']) && $_SESSION['user_role'] === 'Customer'): ?>
                            <li><a href="<?php echo SITE_URL; ?>index.php?page=customer/browse-movies"><i class="fas fa-film"></i> Movies</a></li>
                            <li><a href="<?php echo SITE_URL; ?>index.php?page=customer/my-bookings"><i class="fas fa-ticket-alt"></i> My Bookings</a></li>
                        <?php endif; ?>
                        <li><a href="<?php echo SITE_URL; ?>index.php?page=aboutus"><i class="fas fa-info-circle"></i> About Us</a></li>
                        <li><a href="#"><i class="fas fa-phone"></i> Contact Us</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h3>Contact Info</h3>
                    <ul class="footer-links">
                        <li><a href="#"><i class="fas fa-map-marker-alt"></i> Ward II, Minglanilla, Cebu</a></li>
                        <li><a href="tel:+1234567890"><i class="fas fa-phone"></i> 09267630945</a></li>
                        <li><a href="mailto:info@movieticketing.com"><i class="fas fa-envelope"></i> BSIT@movieticketing.com</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h3>Follow Us</h3>
                    <ul class="footer-links">
                        <li><a href="#"><i class="fab fa-facebook"></i> Facebook</a></li>
                        <li><a href="#"><i class="fab fa-twitter"></i> Twitter</a></li>
                        <li><a href="#"><i class="fab fa-instagram"></i> Instagram</a></li>
                        <li><a href="#"><i class="fab fa-youtube"></i> YouTube</a></li>
                    </ul>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> Movie TIcket Booking. All rights reserved. | 
                    <a href="#">Privacy Policy</a> | 
                    <a href="#">Terms of Service</a>
                </p>
            </div>
        </div>
    </footer>
    
    <style>
        .footer {
            background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%);
            padding: 30px 0 15px;
            border-top: 2px solid var(--primary-red);
            margin-top: auto;
        }
        
        .footer-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 30px;
            margin-bottom: 25px;
        }
        
        .footer-section h3 {
            color: white;
            font-size: 1.1rem;
            margin-bottom: 15px;
            font-weight: 700;
        }
        
        .footer-links {
            list-style: none;
        }
        
        .footer-links li {
            margin-bottom: 8px;
        }
        
        .footer-links a {
            color: var(--pale-red);
            text-decoration: none;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
        }
        
        .footer-links a:hover {
            color: white;
            transform: translateX(5px);
        }
        
        .footer-bottom {
            text-align: center;
            padding-top: 15px;
            border-top: 1px solid rgba(226, 48, 32, 0.3);
            color: var(--pale-red);
            font-size: 0.85rem;
        }
        
        .footer-bottom a {
            color: var(--light-red);
            text-decoration: none;
            font-weight: 600;
        }
        
        .footer-bottom a:hover {
            color: white;
            text-decoration: underline;
        }
        
        @media (max-width: 768px) {
            .footer-content {
                grid-template-columns: repeat(2, 1fr);
                gap: 20px;
            }
        }
        
        @media (max-width: 576px) {
            .footer-content {
                grid-template-columns: 1fr;
            }
        }
    </style>
</body>
</html>