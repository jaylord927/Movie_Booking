<?php
$root_dir = dirname(__DIR__);
require_once $root_dir . '/includes/config.php';
require_once $root_dir . '/includes/functions.php';
require_once $root_dir . '/partials/header.php';
?>

<div class="contact-container" style="max-width: 1200px; margin: 0 auto; padding: 40px 20px;">
    <!-- Header Section -->
    <div style="text-align: center; margin-bottom: 50px;">
        <h1 style="color: white; font-size: 3rem; margin-bottom: 15px; font-weight: 800;">Contact Us</h1>
        <p style="color: var(--pale-red); font-size: 1.2rem; max-width: 700px; margin: 0 auto; line-height: 1.6;">
            Get in touch with our team. We're here to help with any questions or concerns about your movie booking experience.
        </p>
    </div>

    <!-- Contact Information Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px; margin-bottom: 50px;">
        <!-- Location Card -->
        <div style="background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%); 
                    border-radius: 15px; padding: 30px; text-align: center; 
                    border: 1px solid rgba(226, 48, 32, 0.3); transition: all 0.3s ease;"
             onmouseover="this.style.transform='translateY(-10px)'; this.style.borderColor='var(--primary-red)';"
             onmouseout="this.style.transform='translateY(0)'; this.style.borderColor='rgba(226, 48, 32, 0.3)';">
            <div style="font-size: 3rem; color: var(--primary-red); margin-bottom: 20px;">
                <i class="fas fa-map-marker-alt"></i>
            </div>
            <h3 style="color: white; font-size: 1.5rem; margin-bottom: 15px; font-weight: 700;">Location</h3>
            <p style="color: var(--pale-red); font-size: 1.1rem; line-height: 1.6;">
                Ward II, Minglanilla, Cebu<br>
                Philippines
            </p>
        </div>

        <!-- Phone Card -->
        <div style="background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%); 
                    border-radius: 15px; padding: 30px; text-align: center; 
                    border: 1px solid rgba(226, 48, 32, 0.3); transition: all 0.3s ease;"
             onmouseover="this.style.transform='translateY(-10px)'; this.style.borderColor='var(--primary-red)';"
             onmouseout="this.style.transform='translateY(0)'; this.style.borderColor='rgba(226, 48, 32, 0.3)';">
            <div style="font-size: 3rem; color: var(--primary-red); margin-bottom: 20px;">
                <i class="fas fa-phone-alt"></i>
            </div>
            <h3 style="color: white; font-size: 1.5rem; margin-bottom: 15px; font-weight: 700;">Phone</h3>
            <p style="color: var(--pale-red); font-size: 1.1rem; line-height: 1.6;">
                <a href="tel:09267630945" style="color: var(--pale-red); text-decoration: none;">0926 763 0945</a>
            </p>
        </div>

        <!-- Email Card -->
        <div style="background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%); 
                    border-radius: 15px; padding: 30px; text-align: center; 
                    border: 1px solid rgba(226, 48, 32, 0.3); transition: all 0.3s ease;"
             onmouseover="this.style.transform='translateY(-10px)'; this.style.borderColor='var(--primary-red)';"
             onmouseout="this.style.transform='translateY(0)'; this.style.borderColor='rgba(226, 48, 32, 0.3)';">
            <div style="font-size: 3rem; color: var(--primary-red); margin-bottom: 20px;">
                <i class="fas fa-envelope"></i>
            </div>
            <h3 style="color: white; font-size: 1.5rem; margin-bottom: 15px; font-weight: 700;">Email</h3>
            <p style="color: var(--pale-red); font-size: 1.1rem; line-height: 1.6;">
                <a href="mailto:BSIT@movieticketing.com" style="color: var(--pale-red); text-decoration: none;">BSIT@movieticketing.com</a>
            </p>
        </div>
    </div>

    <!-- Team Members Section -->
    <h2 style="color: white; font-size: 2.2rem; text-align: center; margin-bottom: 40px; font-weight: 800;">Meet Our Team</h2>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(500px, 1fr)); gap: 40px; margin-bottom: 50px;">
        <!-- Denise Kethley Caña -->
        <div style="background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%); 
                    border-radius: 20px; padding: 30px; border: 1px solid rgba(226, 48, 32, 0.3);">
            <div style="display: flex; gap: 30px; align-items: flex-start; flex-wrap: wrap;">
                <div style="width: 120px; height: 120px; background: linear-gradient(135deg, var(--primary-red) 0%, var(--dark-red) 100%); 
                            border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <span style="font-size: 2.5rem; color: white; font-weight: 700;">DC</span>
                </div>
                <div style="flex: 1;">
                    <h3 style="color: white; font-size: 1.8rem; margin-bottom: 5px; font-weight: 700;">Denise Kethley Caña</h3>
                    <p style="color: var(--primary-red); font-size: 1.1rem; margin-bottom: 15px; font-weight: 600;">
                        <i class="fas fa-user-tag"></i> Documenter and Database Designer
                    </p>
                    
                    <div style="margin-bottom: 15px;">
                        <p style="color: var(--pale-red); margin-bottom: 8px;">
                            <i class="fas fa-envelope" style="width: 25px; color: var(--primary-red);"></i> 
                            <a href="mailto:kethleycana15@gmail.com" style="color: var(--pale-red); text-decoration: none;">kethleycana15@gmail.com</a>
                        </p>
                        <p style="color: var(--pale-red); margin-bottom: 8px;">
                            <i class="fas fa-phone" style="width: 25px; color: var(--primary-red);"></i> 
                            <a href="tel:09948428822" style="color: var(--pale-red); text-decoration: none;">0994 842 8822</a>
                        </p>
                        <p style="color: var(--pale-red);">
                            <i class="fas fa-user-circle" style="width: 25px; color: var(--primary-red);"></i> 
                            <span style="color: var(--pale-red);">Denise Kethley Torrefiel</span>
                        </p>
                    </div>

                    <div style="display: flex; gap: 15px; margin-top: 15px;">
                        <a href="https://www.facebook.com/denisekethley.cana/" target="_blank" 
                           style="color: white; background: #1877f2; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: all 0.3s ease;"
                           onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 10px 20px rgba(0,0,0,0.3)';"
                           onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="https://www.instagram.com/lil.kettl/" target="_blank" 
                           style="color: white; background: #e4405f; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: all 0.3s ease;"
                           onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 10px 20px rgba(0,0,0,0.3)';"
                           onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <a href="https://www.youtube.com/@denays1013" target="_blank" 
                           style="color: white; background: #ff0000; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: all 0.3s ease;"
                           onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 10px 20px rgba(0,0,0,0.3)';"
                           onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                            <i class="fab fa-youtube"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Jaylord Laspuña -->
        <div style="background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%); 
                    border-radius: 20px; padding: 30px; border: 1px solid rgba(226, 48, 32, 0.3);">
            <div style="display: flex; gap: 30px; align-items: flex-start; flex-wrap: wrap;">
                <div style="width: 120px; height: 120px; background: linear-gradient(135deg, var(--primary-red) 0%, var(--dark-red) 100%); 
                            border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <span style="font-size: 2.5rem; color: white; font-weight: 700;">JL</span>
                </div>
                <div style="flex: 1;">
                    <h3 style="color: white; font-size: 1.8rem; margin-bottom: 5px; font-weight: 700;">Jaylord Laspuña</h3>
                    <p style="color: var(--primary-red); font-size: 1.1rem; margin-bottom: 15px; font-weight: 600;">
                        <i class="fas fa-user-tag"></i> Developer and UI Designer
                    </p>
                    
                    <div style="margin-bottom: 15px;">
                        <p style="color: var(--pale-red); margin-bottom: 8px;">
                            <i class="fas fa-envelope" style="width: 25px; color: var(--primary-red);"></i> 
                            <a href="mailto:jaylordlaspuna1@gmail.com" style="color: var(--pale-red); text-decoration: none;">jaylordlaspuna1@gmail.com</a>
                        </p>
                        <p style="color: var(--pale-red); margin-bottom: 8px;">
                            <i class="fas fa-phone" style="width: 25px; color: var(--primary-red);"></i> 
                            <a href="tel:09267630945" style="color: var(--pale-red); text-decoration: none;">0926 763 0945</a>
                        </p>
                        <p style="color: var(--pale-red);">
                            <i class="fas fa-user-circle" style="width: 25px; color: var(--primary-red);"></i> 
                            <span style="color: var(--pale-red);">Jaylord Billiones Laspuña</span>
                        </p>
                    </div>

                    <div style="display: flex; gap: 15px; margin-top: 15px;">
                        <a href="https://www.facebook.com" target="_blank" 
                           style="color: white; background: #1877f2; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: all 0.3s ease;"
                           onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 10px 20px rgba(0,0,0,0.3)';"
                           onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Social Media Links Section -->
    <div style="background: linear-gradient(135deg, rgba(226, 48, 32, 0.1), rgba(193, 27, 24, 0.2)); 
                border-radius: 20px; padding: 40px; margin-bottom: 50px; 
                border: 2px solid rgba(226, 48, 32, 0.3); text-align: center;">
        <h2 style="color: white; font-size: 2rem; margin-bottom: 30px; font-weight: 800;">Connect With Us</h2>
        
        <div style="display: flex; justify-content: center; gap: 30px; flex-wrap: wrap;">
            <!-- General Facebook -->
            <a href="https://www.facebook.com" target="_blank" 
               style="background: #1877f2; color: white; padding: 15px 30px; border-radius: 50px; text-decoration: none; 
                      display: inline-flex; align-items: center; gap: 10px; font-weight: 600; transition: all 0.3s ease;
                      box-shadow: 0 5px 15px rgba(0,0,0,0.2);"
               onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 10px 25px rgba(24,119,242,0.4)';"
               onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 5px 15px rgba(0,0,0,0.2)';">
                <i class="fab fa-facebook-f"></i>
                <span>www.facebook.com</span>
            </a>

            <!-- Denise's Facebook -->
            <a href="https://www.facebook.com/denisekethley.cana/" target="_blank" 
               style="background: #1877f2; color: white; padding: 15px 30px; border-radius: 50px; text-decoration: none; 
                      display: inline-flex; align-items: center; gap: 10px; font-weight: 600; transition: all 0.3s ease;
                      box-shadow: 0 5px 15px rgba(0,0,0,0.2);"
               onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 10px 25px rgba(24,119,242,0.4)';"
               onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 5px 15px rgba(0,0,0,0.2)';">
                <i class="fab fa-facebook-f"></i>
                <span>Denise Kethley Caña</span>
            </a>

            <!-- Denise's Instagram -->
            <a href="https://www.instagram.com/lil.kettl/" target="_blank" 
               style="background: #e4405f; color: white; padding: 15px 30px; border-radius: 50px; text-decoration: none; 
                      display: inline-flex; align-items: center; gap: 10px; font-weight: 600; transition: all 0.3s ease;
                      box-shadow: 0 5px 15px rgba(0,0,0,0.2);"
               onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 10px 25px rgba(228,64,95,0.4)';"
               onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 5px 15px rgba(0,0,0,0.2)';">
                <i class="fab fa-instagram"></i>
                <span>@lil.kettl</span>
            </a>

            <!-- Denise's YouTube -->
            <a href="https://www.youtube.com/@denays1013" target="_blank" 
               style="background: #ff0000; color: white; padding: 15px 30px; border-radius: 50px; text-decoration: none; 
                      display: inline-flex; align-items: center; gap: 10px; font-weight: 600; transition: all 0.3s ease;
                      box-shadow: 0 5px 15px rgba(0,0,0,0.2);"
               onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 10px 25px rgba(255,0,0,0.4)';"
               onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 5px 15px rgba(0,0,0,0.2)';">
                <i class="fab fa-youtube"></i>
                <span>@denays1013</span>
            </a>
        </div>
    </div>

    <!-- Quick Contact Form -->
    <div style="background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%); 
                border-radius: 20px; padding: 40px; border: 1px solid rgba(226, 48, 32, 0.3);">
        <h2 style="color: white; font-size: 2rem; margin-bottom: 20px; text-align: center; font-weight: 800;">Send Us a Message</h2>
        <p style="color: var(--pale-red); text-align: center; margin-bottom: 30px; max-width: 600px; margin-left: auto; margin-right: auto;">
            Have a question or concern? Feel free to reach out to us and we'll get back to you as soon as possible.
        </p>
        
        <form method="POST" action="" style="max-width: 600px; margin: 0 auto;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div>
                    <input type="text" placeholder="Your Name" required
                           style="width: 100%; padding: 15px; background: rgba(255,255,255,0.08); border: 2px solid rgba(226, 48, 32, 0.3); border-radius: 10px; color: white; font-size: 1rem;">
                </div>
                <div>
                    <input type="email" placeholder="Your Email" required
                           style="width: 100%; padding: 15px; background: rgba(255,255,255,0.08); border: 2px solid rgba(226, 48, 32, 0.3); border-radius: 10px; color: white; font-size: 1rem;">
                </div>
            </div>
            <div style="margin-bottom: 20px;">
                <input type="text" placeholder="Subject" required
                       style="width: 100%; padding: 15px; background: rgba(255,255,255,0.08); border: 2px solid rgba(226, 48, 32, 0.3); border-radius: 10px; color: white; font-size: 1rem;">
            </div>
            <div style="margin-bottom: 20px;">
                <textarea rows="5" placeholder="Your Message" required
                          style="width: 100%; padding: 15px; background: rgba(255,255,255,0.08); border: 2px solid rgba(226, 48, 32, 0.3); border-radius: 10px; color: white; font-size: 1rem; resize: vertical;"></textarea>
            </div>
            <div style="text-align: center;">
                <button type="submit" 
                        style="padding: 15px 45px; background: linear-gradient(135deg, var(--primary-red) 0%, var(--dark-red) 100%); color: white; border: none; border-radius: 50px; font-size: 1.1rem; font-weight: 700; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 6px 20px rgba(226, 48, 32, 0.3);">
                    <i class="fas fa-paper-plane"></i> Send Message
                </button>
            </div>
        </form>
    </div>
</div>

<style>
    .contact-container {
        animation: fadeIn 0.5s ease;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    input:focus, textarea:focus {
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
        .contact-container {
            padding: 20px 15px;
        }
        
        h1 {
            font-size: 2.2rem !important;
        }
        
        .contact-container > div > div {
            grid-template-columns: 1fr !important;
        }
        
        .team-member-card > div {
            flex-direction: column;
            text-align: center;
        }
        
        .team-member-card > div > div:first-child {
            margin: 0 auto 20px;
        }
    }

    @media (max-width: 576px) {
        .contact-container > div > div {
            grid-template-columns: 1fr;
        }
    }
</style>

<?php
require_once $root_dir . '/partials/footer.php';
?>