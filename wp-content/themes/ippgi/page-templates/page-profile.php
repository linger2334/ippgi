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

                    <?php if ($subscription_status === 'trial') : ?>
                        <!-- Trial Period -->
                        <p class="profile-section__value"><?php esc_html_e('Trial Period', 'ippgi'); ?></p>
                        <p class="profile-section__value">
                            <?php printf(esc_html__('Your trial period ends on %s', 'ippgi'), esc_html($subscription_end_date)); ?>
                        </p>

                    <?php elseif ($subscription_status === 'active') : ?>
                        <!-- Active Subscription -->
                        <p class="profile-section__value"><?php esc_html_e('Active', 'ippgi'); ?></p>
                        <p class="profile-section__value">
                            <?php printf(esc_html__('Your subscription ends on %s', 'ippgi'), esc_html($subscription_end_date)); ?>
                        </p>
                        <div class="profile-section__action">
                            <a href="#" class="profile-btn" id="cancel-subscription-btn">
                                <?php esc_html_e('Cancel Subscription', 'ippgi'); ?>
                                <span>&gt;</span>
                            </a>
                        </div>

                    <?php elseif ($subscription_status === 'cancelled') : ?>
                        <!-- Cancelled but not expired -->
                        <p class="profile-section__value"><?php esc_html_e('Cancelled', 'ippgi'); ?></p>
                        <p class="profile-section__value">
                            <?php printf(esc_html__('Your subscription ends on %s', 'ippgi'), esc_html($subscription_end_date)); ?>
                        </p>

                    <?php else : ?>
                        <!-- Terminated / No subscription -->
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
                <a href="<?php echo esc_url(home_url('/contact')); ?>" class="site-footer__legal-link"><?php esc_html_e('Contact Us', 'ippgi'); ?></a>
            </div>
            <p class="site-footer__copyright">
                &copy; <?php echo esc_html(date('Y')); ?> <?php esc_html_e('AFO Group Pty Ltd, all rights reserved.', 'ippgi'); ?>
            </p>
        </div>

        <!-- Social Icons -->
        <div class="site-footer__social">
            <a href="#" class="social-icon" aria-label="Facebook">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                </svg>
            </a>
            <a href="#" class="social-icon" aria-label="LinkedIn">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
                </svg>
            </a>
            <a href="#" class="social-icon" aria-label="Twitter">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                </svg>
            </a>
            <a href="#" class="social-icon" aria-label="Telegram">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/>
                </svg>
            </a>
            <a href="#" class="social-icon" aria-label="YouTube">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
                </svg>
            </a>
            <a href="#" class="social-icon" aria-label="WhatsApp">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                </svg>
            </a>
        </div>
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
                    cancelModal.style.display = 'none';
                }
            })
            .catch(error => {
                alert('<?php echo esc_js(__('An error occurred. Please try again.', 'ippgi')); ?>');
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
