<?php
$root_dir = dirname(__DIR__);
require_once $root_dir . '/includes/config.php';
require_once $root_dir . '/includes/functions.php';
require_once $root_dir . '/partials/header.php';

// Get active tab from URL parameter
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'terms';
?>

<div class="legal-container" style="max-width: 1200px; margin: 0 auto; padding: 40px 20px;">
    <!-- Header Section -->
    <div style="text-align: center; margin-bottom: 40px;">
        <h1 style="color: white; font-size: 2.8rem; margin-bottom: 15px; font-weight: 800;">Policies & Terms</h1>
        <p style="color: var(--pale-red); font-size: 1.2rem; max-width: 700px; margin: 0 auto; line-height: 1.6;">
            Please read our policies carefully. By using our service, you agree to these terms and conditions.
        </p>
    </div>

    <!-- Tab Buttons -->
    <div style="display: flex; justify-content: center; gap: 20px; margin-bottom: 40px;">
        <a href="?page=privacypolicy_termsservice&tab=terms" 
           style="padding: 15px 40px; background: <?php echo $active_tab == 'terms' ? 'linear-gradient(135deg, var(--primary-red) 0%, var(--dark-red) 100%)' : 'rgba(255,255,255,0.1)'; ?>; 
                  color: white; text-decoration: none; border-radius: 50px; font-weight: 700; font-size: 1.1rem; 
                  transition: all 0.3s ease; border: 2px solid <?php echo $active_tab == 'terms' ? 'transparent' : 'rgba(226, 48, 32, 0.3)'; ?>;
                  display: inline-flex; align-items: center; gap: 8px;"
           onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 10px 25px rgba(226,48,32,0.3)';"
           onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
            <i class="fas fa-file-contract"></i> Terms of Service
        </a>
        <a href="?page=privacypolicy_termsservice&tab=privacy" 
           style="padding: 15px 40px; background: <?php echo $active_tab == 'privacy' ? 'linear-gradient(135deg, var(--primary-red) 0%, var(--dark-red) 100%)' : 'rgba(255,255,255,0.1)'; ?>; 
                  color: white; text-decoration: none; border-radius: 50px; font-weight: 700; font-size: 1.1rem; 
                  transition: all 0.3s ease; border: 2px solid <?php echo $active_tab == 'privacy' ? 'transparent' : 'rgba(226, 48, 32, 0.3)'; ?>;
                  display: inline-flex; align-items: center; gap: 8px;"
           onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 10px 25px rgba(226,48,32,0.3)';"
           onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
            <i class="fas fa-shield-alt"></i> Privacy Policy
        </a>
    </div>

    <!-- Content Section -->
    <div style="background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%); 
                border-radius: 20px; padding: 40px; border: 1px solid rgba(226, 48, 32, 0.3);">
        
        <?php if ($active_tab == 'terms'): ?>
        <!-- Terms of Service Content -->
        <div>
            <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 2px solid rgba(226, 48, 32, 0.3);">
                <i class="fas fa-file-contract" style="font-size: 2.5rem; color: var(--primary-red);"></i>
                <h2 style="color: white; font-size: 2rem; font-weight: 700;">Terms of Service</h2>
            </div>
            
            <div style="color: rgba(255,255,255,0.9); line-height: 1.8; font-size: 1rem;">
                <p style="margin-bottom: 20px;">Last updated: <?php echo date('F d, Y'); ?></p>

                <!-- 1. Acceptance of Terms -->
                <div style="margin-bottom: 30px;">
                    <h3 style="color: white; font-size: 1.4rem; margin-bottom: 15px; font-weight: 700;">
                        <i class="fas fa-check-circle" style="color: var(--primary-red); margin-right: 10px;"></i>
                        1. Acceptance of Terms
                    </h3>
                    <p style="margin-bottom: 15px; color: var(--pale-red);">
                        By accessing and using MovieTicketBooking, you agree to be bound by these Terms of Service. 
                        If you do not agree to any part of these terms, please do not use our service.
                    </p>
                </div>

                <!-- 2. Booking and Payment -->
                <div style="margin-bottom: 30px;">
                    <h3 style="color: white; font-size: 1.4rem; margin-bottom: 15px; font-weight: 700;">
                        <i class="fas fa-credit-card" style="color: var(--primary-red); margin-right: 10px;"></i>
                        2. Booking and Payment
                    </h3>
                    <ul style="list-style: none; padding-left: 0;">
                        <li style="margin-bottom: 12px; display: flex; align-items: flex-start; gap: 10px;">
                            <i class="fas fa-circle" style="color: var(--primary-red); font-size: 0.5rem; margin-top: 8px;"></i>
                            <span>All bookings are confirmed only after successful payment. A booking reference will be sent to your email.</span>
                        </li>
                        <li style="margin-bottom: 12px; display: flex; align-items: flex-start; gap: 10px;">
                            <i class="fas fa-circle" style="color: var(--primary-red); font-size: 0.5rem; margin-top: 8px;"></i>
                            <span>You have 3 hours to complete your payment after booking. Unpaid bookings will be automatically cancelled.</span>
                        </li>
                        <li style="margin-bottom: 12px; display: flex; align-items: flex-start; gap: 10px;">
                            <i class="fas fa-circle" style="color: var(--primary-red); font-size: 0.5rem; margin-top: 8px;"></i>
                            <span>Payment can be made via GCash, PayMaya, bank transfer, or credit/debit cards through PayMongo.</span>
                        </li>
                        <li style="margin-bottom: 12px; display: flex; align-items: flex-start; gap: 10px;">
                            <i class="fas fa-circle" style="color: var(--primary-red); font-size: 0.5rem; margin-top: 8px;"></i>
                            <span>Ticket prices are final and include all applicable taxes. No hidden fees.</span>
                        </li>
                    </ul>
                </div>

                <!-- 3. Cancellation and Refund Policy -->
                <div style="margin-bottom: 30px;">
                    <h3 style="color: white; font-size: 1.4rem; margin-bottom: 15px; font-weight: 700;">
                        <i class="fas fa-undo-alt" style="color: var(--primary-red); margin-right: 10px;"></i>
                        3. Cancellation and Refund Policy
                    </h3>
                    <ul style="list-style: none; padding-left: 0;">
                        <li style="margin-bottom: 12px; display: flex; align-items: flex-start; gap: 10px;">
                            <i class="fas fa-circle" style="color: var(--primary-red); font-size: 0.5rem; margin-top: 8px;"></i>
                            <span>Bookings can be cancelled up to 2 hours before the showtime for a full refund.</span>
                        </li>
                        <li style="margin-bottom: 12px; display: flex; align-items: flex-start; gap: 10px;">
                            <i class="fas fa-circle" style="color: var(--primary-red); font-size: 0.5rem; margin-top: 8px;"></i>
                            <span>Cancellations made less than 2 hours before the showtime are non-refundable.</span>
                        </li>
                        <li style="margin-bottom: 12px; display: flex; align-items: flex-start; gap: 10px;">
                            <i class="fas fa-circle" style="color: var(--primary-red); font-size: 0.5rem; margin-top: 8px;"></i>
                            <span>Refunds will be processed within 3-5 business days and credited back to your original payment method.</span>
                        </li>
                        <li style="margin-bottom: 12px; display: flex; align-items: flex-start; gap: 10px;">
                            <i class="fas fa-circle" style="color: var(--primary-red); font-size: 0.5rem; margin-top: 8px;"></i>
                            <span>In case of movie cancellation by the cinema, you will receive a full refund automatically.</span>
                        </li>
                    </ul>
                </div>

                <!-- 4. Seat Selection -->
                <div style="margin-bottom: 30px;">
                    <h3 style="color: white; font-size: 1.4rem; margin-bottom: 15px; font-weight: 700;">
                        <i class="fas fa-chair" style="color: var(--primary-red); margin-right: 10px;"></i>
                        4. Seat Selection
                    </h3>
                    <ul style="list-style: none; padding-left: 0;">
                        <li style="margin-bottom: 12px; display: flex; align-items: flex-start; gap: 10px;">
                            <i class="fas fa-circle" style="color: var(--primary-red); font-size: 0.5rem; margin-top: 8px;"></i>
                            <span>Seats are assigned on a first-come, first-served basis during booking.</span>
                        </li>
                        <li style="margin-bottom: 12px; display: flex; align-items: flex-start; gap: 10px;">
                            <i class="fas fa-circle" style="color: var(--primary-red); font-size: 0.5rem; margin-top: 8px;"></i>
                            <span>Once seats are selected and payment is confirmed, they are locked and cannot be changed.</span>
                        </li>
                        <li style="margin-bottom: 12px; display: flex; align-items: flex-start; gap: 10px;">
                            <i class="fas fa-circle" style="color: var(--primary-red); font-size: 0.5rem; margin-top: 8px;"></i>
                            <span>If you need to change seats, you can use the "Rebook" feature for paid bookings, subject to availability.</span>
                        </li>
                    </ul>
                </div>

                <!-- 5. Customer Responsibilities -->
                <div style="margin-bottom: 30px;">
                    <h3 style="color: white; font-size: 1.4rem; margin-bottom: 15px; font-weight: 700;">
                        <i class="fas fa-user-check" style="color: var(--primary-red); margin-right: 10px;"></i>
                        5. Customer Responsibilities
                    </h3>
                    <ul style="list-style: none; padding-left: 0;">
                        <li style="margin-bottom: 12px; display: flex; align-items: flex-start; gap: 10px;">
                            <i class="fas fa-circle" style="color: var(--primary-red); font-size: 0.5rem; margin-top: 8px;"></i>
                            <span>You are responsible for providing accurate information during booking.</span>
                        </li>
                        <li style="margin-bottom: 12px; display: flex; align-items: flex-start; gap: 10px;">
                            <i class="fas fa-circle" style="color: var(--primary-red); font-size: 0.5rem; margin-top: 8px;"></i>
                            <span>Please arrive at least 30 minutes before the showtime. Latecomers may not be admitted.</span>
                        </li>
                        <li style="margin-bottom: 12px; display: flex; align-items: flex-start; gap: 10px;">
                            <i class="fas fa-circle" style="color: var(--primary-red); font-size: 0.5rem; margin-top: 8px;"></i>
                            <span>Bring a valid ID and your booking reference for verification at the cinema.</span>
                        </li>
                        <li style="margin-bottom: 12px; display: flex; align-items: flex-start; gap: 10px;">
                            <i class="fas fa-circle" style="color: var(--primary-red); font-size: 0.5rem; margin-top: 8px;"></i>
                            <span>Outside food and drinks are not allowed in the cinema premises.</span>
                        </li>
                    </ul>
                </div>

                <!-- 6. Mistakes and Errors -->
                <div style="margin-bottom: 30px;">
                    <h3 style="color: white; font-size: 1.4rem; margin-bottom: 15px; font-weight: 700;">
                        <i class="fas fa-exclamation-triangle" style="color: var(--primary-red); margin-right: 10px;"></i>
                        6. Mistakes and Errors
                    </h3>
                    <ul style="list-style: none; padding-left: 0;">
                        <li style="margin-bottom: 12px; display: flex; align-items: flex-start; gap: 10px;">
                            <i class="fas fa-circle" style="color: var(--primary-red); font-size: 0.5rem; margin-top: 8px;"></i>
                            <span>If you made a mistake in your booking, contact us immediately. We'll try to help if the show hasn't started.</span>
                        </li>
                        <li style="margin-bottom: 12px; display: flex; align-items: flex-start; gap: 10px;">
                            <i class="fas fa-circle" style="color: var(--primary-red); font-size: 0.5rem; margin-top: 8px;"></i>
                            <span>Technical errors during payment will be investigated. Duplicate charges will be refunded.</span>
                        </li>
                        <li style="margin-bottom: 12px; display: flex; align-items: flex-start; gap: 10px;">
                            <i class="fas fa-circle" style="color: var(--primary-red); font-size: 0.5rem; margin-top: 8px;"></i>
                            <span>We reserve the right to cancel any booking found to be fraudulent or made in error.</span>
                        </li>
                    </ul>
                </div>

                <!-- 7. Account Security -->
                <div style="margin-bottom: 30px;">
                    <h3 style="color: white; font-size: 1.4rem; margin-bottom: 15px; font-weight: 700;">
                        <i class="fas fa-lock" style="color: var(--primary-red); margin-right: 10px;"></i>
                        7. Account Security
                    </h3>
                    <ul style="list-style: none; padding-left: 0;">
                        <li style="margin-bottom: 12px; display: flex; align-items: flex-start; gap: 10px;">
                            <i class="fas fa-circle" style="color: var(--primary-red); font-size: 0.5rem; margin-top: 8px;"></i>
                            <span>You are responsible for keeping your account credentials secure.</span>
                        </li>
                        <li style="margin-bottom: 12px; display: flex; align-items: flex-start; gap: 10px;">
                            <i class="fas fa-circle" style="color: var(--primary-red); font-size: 0.5rem; margin-top: 8px;"></i>
                            <span>Notify us immediately if you suspect unauthorized use of your account.</span>
                        </li>
                        <li style="margin-bottom: 12px; display: flex; align-items: flex-start; gap: 10px;">
                            <i class="fas fa-circle" style="color: var(--primary-red); font-size: 0.5rem; margin-top: 8px;"></i>
                            <span>We are not liable for any loss or damage from unauthorized account access.</span>
                        </li>
                    </ul>
                </div>

                <!-- 8. Changes to Terms -->
                <div style="margin-bottom: 30px;">
                    <h3 style="color: white; font-size: 1.4rem; margin-bottom: 15px; font-weight: 700;">
                        <i class="fas fa-edit" style="color: var(--primary-red); margin-right: 10px;"></i>
                        8. Changes to Terms
                    </h3>
                    <p style="color: var(--pale-red);">
                        We may update these terms from time to time. Continued use of our service after changes means you accept the new terms.
                    </p>
                </div>
            </div>
        </div>

        <?php else: ?>
        <!-- Privacy Policy Content -->
        <div>
            <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 2px solid rgba(226, 48, 32, 0.3);">
                <i class="fas fa-shield-alt" style="font-size: 2.5rem; color: var(--primary-red);"></i>
                <h2 style="color: white; font-size: 2rem; font-weight: 700;">Privacy Policy</h2>
            </div>
            
            <div style="color: rgba(255,255,255,0.9); line-height: 1.8; font-size: 1rem;">
                <p style="margin-bottom: 20px;">Last updated: <?php echo date('F d, Y'); ?></p>

                <!-- 1. Information We Collect -->
                <div style="margin-bottom: 30px;">
                    <h3 style="color: white; font-size: 1.4rem; margin-bottom: 15px; font-weight: 700;">
                        <i class="fas fa-database" style="color: var(--primary-red); margin-right: 10px;"></i>
                        1. Information We Collect
                    </h3>
                    <ul style="list-style: none; padding-left: 0;">
                        <li style="margin-bottom: 12px; display: flex; align-items: flex-start; gap: 10px;">
                            <i class="fas fa-circle" style="color: var(--primary-red); font-size: 0.5rem; margin-top: 8px;"></i>
                            <span><strong>Personal Information:</strong> Name, email address, phone number, and username when you register.</span>
                        </li>
                        <li style="margin-bottom: 12px; display: flex; align-items: flex-start; gap: 10px;">
                            <i class="fas fa-circle" style="color: var(--primary-red); font-size: 0.5rem; margin-top: 8px;"></i>
                            <span><strong>Booking Information:</strong> Movie selections, seat preferences, and payment history.</span>
                        </li>
                        <li style="margin-bottom: 12px; display: flex; align-items: flex-start; gap: 10px;">
                            <i class="fas fa-circle" style="color: var(--primary-red); font-size: 0.5rem; margin-top: 8px;"></i>
                            <span><strong>Payment Information:</strong> We do not store credit card details. All payments are processed securely through PayMongo.</span>
                        </li>
                        <li style="margin-bottom: 12px; display: flex; align-items: flex-start; gap: 10px;">
                            <i class="fas fa-circle" style="color: var(--primary-red); font-size: 0.5rem; margin-top: 8px;"></i>
                            <span><strong>Technical Data:</strong> IP address, browser type, and device information for security and analytics.</span>
                        </li>
                    </ul>
                </div>

                <!-- 2. How We Use Your Information -->
                <div style="margin-bottom: 30px;">
                    <h3 style="color: white; font-size: 1.4rem; margin-bottom: 15px; font-weight: 700;">
                        <i class="fas fa-cogs" style="color: var(--primary-red); margin-right: 10px;"></i>
                        2. How We Use Your Information
                    </h3>
                    <ul style="list-style: none; padding-left: 0;">
                        <li style="margin-bottom: 12px; display: flex; align-items: flex-start; gap: 10px;">
                            <i class="fas fa-circle" style="color: var(--primary-red); font-size: 0.5rem; margin-top: 8px;"></i>
                            <span>To process your bookings and send booking confirmations.</span>
                        </li>
                        <li style="margin-bottom: 12px; display: flex; align-items: flex-start; gap: 10px;">
                            <i class="fas fa-circle" style="color: var(--primary-red); font-size: 0.5rem; margin-top: 8px;"></i>
                            <span>To communicate important updates about your bookings or changes to our service.</span>
                        </li>
                        <li style="margin-bottom: 12px; display: flex; align-items: flex-start; gap: 10px;">
                            <i class="fas fa-circle" style="color: var(--primary-red); font-size: 0.5rem; margin-top: 8px;"></i>
                            <span>To improve our website and personalize your experience.</span>
                        </li>
                        <li style="margin-bottom: 12px; display: flex; align-items: flex-start; gap: 10px;">
                            <i class="fas fa-circle" style="color: var(--primary-red); font-size: 0.5rem; margin-top: 8px;"></i>
                            <span>To prevent fraud and ensure the security of our platform.</span>
                        </li>
                    </ul>
                </div>

                <!-- 3. Information Sharing -->
                <div style="margin-bottom: 30px;">
                    <h3 style="color: white; font-size: 1.4rem; margin-bottom: 15px; font-weight: 700;">
                        <i class="fas fa-share-alt" style="color: var(--primary-red); margin-right: 10px;"></i>
                        3. Information Sharing
                    </h3>
                    <ul style="list-style: none; padding-left: 0;">
                        <li style="margin-bottom: 12px; display: flex; align-items: flex-start; gap: 10px;">
                            <i class="fas fa-circle" style="color: var(--primary-red); font-size: 0.5rem; margin-top: 8px;"></i>
                            <span>We do not sell your personal information to third parties.</span>
                        </li>
                        <li style="margin-bottom: 12px; display: flex; align-items: flex-start; gap: 10px;">
                            <i class="fas fa-circle" style="color: var(--primary-red); font-size: 0.5rem; margin-top: 8px;"></i>
                            <span>Booking information is shared with partner cinemas only for ticket verification.</span>
                        </li>
                        <li style="margin-bottom: 12px; display: flex; align-items: flex-start; gap: 10px;">
                            <i class="fas fa-circle" style="color: var(--primary-red); font-size: 0.5rem; margin-top: 8px;"></i>
                            <span>Payment processing is handled securely by PayMongo. We never see your full payment details.</span>
                        </li>
                        <li style="margin-bottom: 12px; display: flex; align-items: flex-start; gap: 10px;">
                            <i class="fas fa-circle" style="color: var(--primary-red); font-size: 0.5rem; margin-top: 8px;"></i>
                            <span>We may share information if required by law or to protect our rights.</span>
                        </li>
                    </ul>
                </div>

                <!-- 4. Data Security -->
                <div style="margin-bottom: 30px;">
                    <h3 style="color: white; font-size: 1.4rem; margin-bottom: 15px; font-weight: 700;">
                        <i class="fas fa-shield-alt" style="color: var(--primary-red); margin-right: 10px;"></i>
                        4. Data Security
                    </h3>
                    <ul style="list-style: none; padding-left: 0;">
                        <li style="margin-bottom: 12px; display: flex; align-items: flex-start; gap: 10px;">
                            <i class="fas fa-circle" style="color: var(--primary-red); font-size: 0.5rem; margin-top: 8px;"></i>
                            <span>Your data is encrypted and stored securely on our servers.</span>
                        </li>
                        <li style="margin-bottom: 12px; display: flex; align-items: flex-start; gap: 10px;">
                            <i class="fas fa-circle" style="color: var(--primary-red); font-size: 0.5rem; margin-top: 8px;"></i>
                            <span>We use industry-standard security measures to protect your information.</span>
                        </li>
                        <li style="margin-bottom: 12px; display: flex; align-items: flex-start; gap: 10px;">
                            <i class="fas fa-circle" style="color: var(--primary-red); font-size: 0.5rem; margin-top: 8px;"></i>
                            <span>Regular security audits are conducted to ensure your data stays safe.</span>
                        </li>
                    </ul>
                </div>

                <!-- 5. Your Rights -->
                <div style="margin-bottom: 30px;">
                    <h3 style="color: white; font-size: 1.4rem; margin-bottom: 15px; font-weight: 700;">
                        <i class="fas fa-user-check" style="color: var(--primary-red); margin-right: 10px;"></i>
                        5. Your Rights
                    </h3>
                    <ul style="list-style: none; padding-left: 0;">
                        <li style="margin-bottom: 12px; display: flex; align-items: flex-start; gap: 10px;">
                            <i class="fas fa-circle" style="color: var(--primary-red); font-size: 0.5rem; margin-top: 8px;"></i>
                            <span>You can access, update, or delete your personal information in your profile settings.</span>
                        </li>
                        <li style="margin-bottom: 12px; display: flex; align-items: flex-start; gap: 10px;">
                            <i class="fas fa-circle" style="color: var(--primary-red); font-size: 0.5rem; margin-top: 8px;"></i>
                            <span>You can request a copy of your data by contacting our support team.</span>
                        </li>
                        <li style="margin-bottom: 12px; display: flex; align-items: flex-start; gap: 10px;">
                            <i class="fas fa-circle" style="color: var(--primary-red); font-size: 0.5rem; margin-top: 8px;"></i>
                            <span>You may opt out of marketing communications at any time.</span>
                        </li>
                    </ul>
                </div>

                <!-- 6. Cookies -->
                <div style="margin-bottom: 30px;">
                    <h3 style="color: white; font-size: 1.4rem; margin-bottom: 15px; font-weight: 700;">
                        <i class="fas fa-cookie-bite" style="color: var(--primary-red); margin-right: 10px;"></i>
                        6. Cookies
                    </h3>
                    <p style="color: var(--pale-red);">
                        We use cookies to improve your experience on our site. Cookies help us remember your preferences and understand how you use our website. You can disable cookies in your browser settings, but some features may not work properly.
                    </p>
                </div>

                <!-- 7. Children's Privacy -->
                <div style="margin-bottom: 30px;">
                    <h3 style="color: white; font-size: 1.4rem; margin-bottom: 15px; font-weight: 700;">
                        <i class="fas fa-child" style="color: var(--primary-red); margin-right: 10px;"></i>
                        7. Children's Privacy
                    </h3>
                    <p style="color: var(--pale-red);">
                        Our service is not intended for children under 13. We do not knowingly collect information from children under 13. If you believe a child has provided us with personal information, please contact us.
                    </p>
                </div>

                <!-- 8. Changes to Privacy Policy -->
                <div style="margin-bottom: 30px;">
                    <h3 style="color: white; font-size: 1.4rem; margin-bottom: 15px; font-weight: 700;">
                        <i class="fas fa-edit" style="color: var(--primary-red); margin-right: 10px;"></i>
                        8. Changes to Privacy Policy
                    </h3>
                    <p style="color: var(--pale-red);">
                        We may update this privacy policy from time to time. We will notify you of any changes by posting the new policy on this page and updating the "Last updated" date.
                    </p>
                </div>

                <!-- 9. Contact Us -->
                <div style="margin-bottom: 30px;">
                    <h3 style="color: white; font-size: 1.4rem; margin-bottom: 15px; font-weight: 700;">
                        <i class="fas fa-envelope" style="color: var(--primary-red); margin-right: 10px;"></i>
                        9. Contact Us
                    </h3>
                    <p style="color: var(--pale-red);">
                        If you have questions about this privacy policy, please contact us at:
                    </p>
                    <div style="background: rgba(0,0,0,0.2); padding: 20px; border-radius: 10px; margin-top: 10px;">
                        <p style="color: white; margin-bottom: 5px;"><i class="fas fa-envelope" style="color: var(--primary-red); width: 25px;"></i> BSIT@movieticketing.com</p>
                        <p style="color: white; margin-bottom: 5px;"><i class="fas fa-phone" style="color: var(--primary-red); width: 25px;"></i> 0926 763 0945</p>
                        <p style="color: white;"><i class="fas fa-map-marker-alt" style="color: var(--primary-red); width: 25px;"></i> Ward II, Minglanilla, Cebu</p>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Acceptance Footer -->
    <div style="text-align: center; margin-top: 40px; padding: 20px; background: rgba(0,0,0,0.2); border-radius: 10px;">
        <p style="color: var(--pale-red); font-size: 0.95rem;">
            <i class="fas fa-info-circle" style="color: var(--primary-red); margin-right: 5px;"></i>
            By continuing to use MovieTicketBooking, you acknowledge that you have read and understood our 
            <a href="?page=privacypolicy_termsservice&tab=terms" style="color: var(--primary-red); text-decoration: none;">Terms of Service</a> 
            and <a href="?page=privacypolicy_termsservice&tab=privacy" style="color: var(--primary-red); text-decoration: none;">Privacy Policy</a>.
        </p>
    </div>
</div>

<style>
    .legal-container {
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

    .legal-container a:hover {
        text-decoration: underline !important;
    }

    .legal-container ul li {
        transition: transform 0.2s ease;
    }

    .legal-container ul li:hover {
        transform: translateX(5px);
    }

    @media (max-width: 768px) {
        .legal-container {
            padding: 20px 15px;
        }
        
        h1 {
            font-size: 2rem !important;
        }
        
        .legal-container > div > div:first-child {
            flex-direction: column;
            gap: 10px;
        }
        
        .legal-container > div > div:first-child a {
            width: 100%;
            justify-content: center;
        }
    }
</style>

<?php
require_once $root_dir . '/partials/footer.php';
?>