<?php
/**
 * Template Name: Profile Page
 *
 * @package IPPGI
 * @since 1.0.0
 */

// Redirect if not logged in
if (!is_user_logged_in()) {
    wp_redirect(ippgi_get_login_url());
    exit;
}

$current_user = wp_get_current_user();

// Get user meta data
$user_country = get_user_meta($current_user->ID, 'country', true);
$user_company = get_user_meta($current_user->ID, 'company_name', true);
$user_phone = get_user_meta($current_user->ID, 'phone', true);

// Get subscription info
$subscription_status = ippgi_get_subscription_status($current_user->ID);
$subscription_end_date = ippgi_get_formatted_subscription_end_date($current_user->ID);
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php wp_head(); ?>
</head>

<body <?php body_class('profile-page-body'); ?>>
<?php wp_body_open(); ?>

<main id="main-content" class="site-main">
    <div class="container">
        <div class="profile-page">
            <!-- Profile Header -->
            <div class="profile-page__header">
                <h1 class="profile-page__title"><?php esc_html_e('My Profile', 'ippgi'); ?></h1>
            </div>

            <!-- Logo and Logout Row -->
            <div class="profile-page__actions">
                <div class="profile-page__logo">
                    <?php if (has_custom_logo()) : ?>
                        <a href="<?php echo esc_url(home_url('/')); ?>">
                            <?php
                            $custom_logo_id = get_theme_mod('custom_logo');
                            $logo = wp_get_attachment_image_src($custom_logo_id, 'full');
                            if ($logo) {
                                echo '<img src="' . esc_url($logo[0]) . '" alt="' . get_bloginfo('name') . '">';
                            }
                            ?>
                        </a>
                    <?php else : ?>
                        <a href="<?php echo esc_url(home_url('/')); ?>" class="profile-page__logo-text">
                            <?php bloginfo('name'); ?>
                        </a>
                    <?php endif; ?>
                </div>
                <a href="#" class="profile-page__logout" id="logout-btn" data-logout-url="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>">
                    <?php esc_html_e('Logout', 'ippgi'); ?>
                </a>
            </div>

            <!-- Subscription Information Section -->
            <div class="profile-section">
                <div class="profile-section__header">
                    <?php esc_html_e('Subscription information', 'ippgi'); ?>
                </div>
                <div class="profile-section__body">
                    <p class="profile-section__label"><?php esc_html_e('Subscription status:', 'ippgi'); ?></p>

                    <?php if ($subscription_status === 'active') : ?>
                        <!-- 1. Active Subscription (not cancelled) -->
                        <p class="profile-section__value"><?php esc_html_e('Active', 'ippgi'); ?></p>
                        <p class="profile-section__value">
                            <?php printf(esc_html__('Next billing date: %s', 'ippgi'), esc_html($subscription_end_date)); ?>
                        </p>
                        <div class="profile-section__action">
                            <a href="#" class="profile-btn" id="cancel-subscription-btn">
                                <?php esc_html_e('Cancel Subscription', 'ippgi'); ?>
                                <span>&gt;</span>
                            </a>
                        </div>

                    <?php elseif ($subscription_status === 'bonus') : ?>
                        <!-- 2. Active bonus access period -->
                        <p class="profile-section__value"><?php esc_html_e('Active', 'ippgi'); ?></p>
                        <p class="profile-section__value"><?php esc_html_e('You are currently using your bonus days.', 'ippgi'); ?></p>
                        <div class="profile-section__action">
                            <a href="<?php echo esc_url(home_url('/subscribe')); ?>" class="profile-btn">
                                <?php esc_html_e('Subscribe', 'ippgi'); ?>
                                <span>&gt;</span>
                            </a>
                        </div>

                    <?php elseif ($subscription_status === 'cancelled') : ?>
                        <!-- 3. Cancelled but not expired -->
                        <p class="profile-section__value"><?php esc_html_e('Cancelled', 'ippgi'); ?></p>
                        <p class="profile-section__value">
                            <?php printf(esc_html__('Your subscription ends on %s', 'ippgi'), esc_html($subscription_end_date)); ?>
                        </p>

                    <?php else : ?>
                        <!-- 4. No subscription or expired -->
                        <p class="profile-section__value"><?php esc_html_e('Terminated', 'ippgi'); ?></p>
                        <p class="profile-section__value">
                            <?php esc_html_e('Your subscription has ended. To continue access, please click the Subscribe button below.', 'ippgi'); ?>
                        </p>
                        <div class="profile-section__action">
                            <a href="<?php echo esc_url(home_url('/subscribe')); ?>" class="profile-btn">
                                <?php esc_html_e('Subscribe', 'ippgi'); ?>
                                <span>&gt;</span>
                            </a>
                        </div>
                    <?php endif; ?>

                    <?php
                    // Calculate remaining bonus days
                    // If using bonus access: calculate days from now to end date
                    // Otherwise: show unused accumulated bonus days
                    $remaining_bonus_days = 0;
                    if ($subscription_status === 'bonus') {
                        // Currently using bonus access - calculate remaining days
                        $bonus_end = get_user_meta($current_user->ID, 'ippgi_bonus_access_end', true);
                        if ($bonus_end) {
                            // Use WordPress timezone-aware functions
                            $end_time = strtotime($bonus_end . ' ' . wp_timezone_string());
                            $now = current_time('timestamp');
                            if ($end_time > $now) {
                                $remaining_bonus_days = ceil(($end_time - $now) / DAY_IN_SECONDS);
                            }
                        }
                    } else {
                        // Not using bonus - show accumulated unused days
                        $remaining_bonus_days = ippgi_get_unused_bonus_days($current_user->ID);
                    }
                    ?>
                    <div class="profile-bonus-section">
                        <div class="profile-bonus-section__divider"></div>
                        <h3 class="profile-bonus-section__title"><?php esc_html_e('Remaining Bonus Days', 'ippgi'); ?></h3>
                        <p class="profile-bonus-section__number"><?php echo intval($remaining_bonus_days); ?></p>
                        <p class="profile-bonus-section__desc">
                            <?php esc_html_e('Earn Bonus Days by signing up, participating in site activities, or inviting friends, They are automatically applied after your subscription ends to extend access to premium content.(e.g., 7 days remaining after a March 31 expiration extends access to April 7).', 'ippgi'); ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Member Information Section -->
            <div class="profile-section">
                <div class="profile-section__header">
                    <?php esc_html_e('Member information', 'ippgi'); ?>
                </div>
                <div class="profile-section__body">
                    <div class="profile-field">
                        <span class="profile-field__label"><?php esc_html_e('Name:', 'ippgi'); ?></span>
                        <span class="profile-field__value"><?php echo esc_html($current_user->display_name ?: '-'); ?></span>
                    </div>
                    <div class="profile-field profile-field--border">
                        <span class="profile-field__label"><?php esc_html_e('Country/Region:', 'ippgi'); ?></span>
                        <span class="profile-field__value"><?php echo esc_html($user_country ?: '-'); ?></span>
                    </div>
                    <div class="profile-field">
                        <span class="profile-field__label"><?php esc_html_e('Company Name:', 'ippgi'); ?></span>
                        <span class="profile-field__value"><?php echo esc_html($user_company ?: '-'); ?></span>
                    </div>
                    <div class="profile-field">
                        <span class="profile-field__label"><?php esc_html_e('Email:', 'ippgi'); ?></span>
                        <span class="profile-field__value"><?php echo esc_html($current_user->user_email ?: '-'); ?></span>
                    </div>
                    <div class="profile-field profile-field--border">
                        <span class="profile-field__label"><?php esc_html_e('Mobile Number:', 'ippgi'); ?></span>
                        <span class="profile-field__value"><?php echo esc_html($user_phone ?: '-'); ?></span>
                    </div>

                    <div class="profile-section__action">
                        <a href="<?php echo esc_url(ippgi_get_edit_profile_url()); ?>" class="profile-btn">
                            <?php esc_html_e('Edit Member Profile', 'ippgi'); ?>
                            <span>&gt;</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Simple Footer for Profile Page (without products section) -->
<footer class="site-footer site-footer--simple" role="contentinfo">
    <div class="container">
        <!-- Footer Bottom -->
        <div class="site-footer__bottom">
            <div class="site-footer__legal">
                <a href="<?php echo esc_url(home_url('/terms')); ?>" class="site-footer__legal-link"><?php esc_html_e('Terms&Conditions', 'ippgi'); ?></a>
                <a href="<?php echo esc_url(home_url('/privacy')); ?>" class="site-footer__legal-link"><?php esc_html_e('Privacy Policy', 'ippgi'); ?></a>
                <a href="<?php echo esc_url(home_url('/about')); ?>" class="site-footer__legal-link"><?php esc_html_e('About Us', 'ippgi'); ?></a>
            </div>
            <p class="site-footer__copyright">
                &copy; <?php echo esc_html(date('Y')); ?> <?php esc_html_e('AFO Group Pty Ltd, all rights reserved.', 'ippgi'); ?>
            </p>
        </div>

        <?php get_template_part('template-parts/social-icons'); ?>
    </div>
</footer>

<!-- Cancel Subscription Modal -->
<div class="modal-overlay" id="cancel-modal" style="display: none;">
    <div class="modal-dialog">
        <h3 class="modal-dialog__title"><?php esc_html_e('Cancel Subscription', 'ippgi'); ?></h3>
        <p class="modal-dialog__content"><?php esc_html_e('Are you sure you want to cancel your subscription?', 'ippgi'); ?></p>
        <div class="modal-dialog__actions">
            <button type="button" class="modal-dialog__btn modal-dialog__btn--cancel" id="modal-cancel-btn">
                <?php esc_html_e('cancel', 'ippgi'); ?>
            </button>
            <button type="button" class="modal-dialog__btn modal-dialog__btn--confirm" id="modal-confirm-btn">
                <?php esc_html_e('confirm', 'ippgi'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Logout Modal -->
<div class="modal-overlay" id="logout-modal" style="display: none;">
    <div class="modal-dialog">
        <h3 class="modal-dialog__title"><?php esc_html_e('Logout', 'ippgi'); ?></h3>
        <p class="modal-dialog__content"><?php esc_html_e('Are you sure you want to logout?', 'ippgi'); ?></p>
        <div class="modal-dialog__actions">
            <button type="button" class="modal-dialog__btn modal-dialog__btn--cancel" id="logout-cancel-btn">
                <?php esc_html_e('cancel', 'ippgi'); ?>
            </button>
            <button type="button" class="modal-dialog__btn modal-dialog__btn--confirm" id="logout-confirm-btn">
                <?php esc_html_e('confirm', 'ippgi'); ?>
            </button>
        </div>
    </div>
</div>

<script>
(function() {
    // Cancel Subscription Modal
    const cancelBtn = document.getElementById('cancel-subscription-btn');
    const cancelModal = document.getElementById('cancel-modal');
    const modalCancelBtn = document.getElementById('modal-cancel-btn');
    const modalConfirmBtn = document.getElementById('modal-confirm-btn');

    if (cancelBtn && cancelModal) {
        // Show modal when cancel subscription button is clicked
        cancelBtn.addEventListener('click', function(e) {
            e.preventDefault();
            cancelModal.style.display = 'flex';
        });

        // Hide modal when cancel button is clicked
        modalCancelBtn.addEventListener('click', function() {
            cancelModal.style.display = 'none';
        });

        // Hide modal when clicking outside the dialog
        cancelModal.addEventListener('click', function(e) {
            if (e.target === cancelModal) {
                cancelModal.style.display = 'none';
            }
        });

        // Confirm cancellation
        modalConfirmBtn.addEventListener('click', function() {
            // Prevent multiple clicks
            if (modalConfirmBtn.disabled) return;
            modalConfirmBtn.disabled = true;
            modalConfirmBtn.textContent = '<?php echo esc_js(__('Cancelling...', 'ippgi')); ?>';

            const formData = new FormData();
            formData.append('action', 'ippgi_cancel_subscription');
            formData.append('nonce', '<?php echo wp_create_nonce('ippgi_cancel_subscription'); ?>');

            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.data?.message || '<?php echo esc_js(__('Failed to cancel subscription. Please try again.', 'ippgi')); ?>');
                    modalConfirmBtn.disabled = false;
                    modalConfirmBtn.textContent = '<?php echo esc_js(__('Confirm', 'ippgi')); ?>';
                    cancelModal.style.display = 'none';
                }
            })
            .catch(error => {
                alert('<?php echo esc_js(__('An error occurred. Please try again.', 'ippgi')); ?>');
                modalConfirmBtn.disabled = false;
                modalConfirmBtn.textContent = '<?php echo esc_js(__('Confirm', 'ippgi')); ?>';
                cancelModal.style.display = 'none';
            });
        });
    }

    // Logout Modal
    const logoutBtn = document.getElementById('logout-btn');
    const logoutModal = document.getElementById('logout-modal');
    const logoutCancelBtn = document.getElementById('logout-cancel-btn');
    const logoutConfirmBtn = document.getElementById('logout-confirm-btn');

    if (logoutBtn && logoutModal) {
        // Show modal when logout button is clicked
        logoutBtn.addEventListener('click', function(e) {
            e.preventDefault();
            logoutModal.style.display = 'flex';
        });

        // Hide modal when cancel button is clicked
        logoutCancelBtn.addEventListener('click', function() {
            logoutModal.style.display = 'none';
        });

        // Hide modal when clicking outside the dialog
        logoutModal.addEventListener('click', function(e) {
            if (e.target === logoutModal) {
                logoutModal.style.display = 'none';
            }
        });

        // Confirm logout
        logoutConfirmBtn.addEventListener('click', function() {
            window.location.href = logoutBtn.dataset.logoutUrl;
        });
    }
})();
</script>

<?php wp_footer(); ?>
</body>
</html>
