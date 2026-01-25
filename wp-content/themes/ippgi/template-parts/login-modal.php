<?php
/**
 * Login Modal
 *
 * @package IPPGI
 * @since 1.0.0
 */
?>

<div class="login-modal" id="login-modal" role="dialog" aria-modal="true" aria-labelledby="login-modal-title" hidden>
    <div class="login-modal__backdrop"></div>
    <div class="login-modal__content">
        <button type="button" class="login-modal__close" aria-label="<?php esc_attr_e('Close', 'ippgi'); ?>">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        </button>

        <h2 id="login-modal-title" class="login-modal__title"><?php esc_html_e('Log into your account', 'ippgi'); ?></h2>

        <div class="login-modal__body">
            <?php
            // Only show Google login button
            // Check if Simple Membership Google Login addon is active
            if (function_exists('swpm_google_login_button')) {
                // Use the addon's Google login shortcode (only Google button, no form)
                echo do_shortcode('[swpm_google_login]');
            } else {
                // Placeholder Google login button (for development/preview)
                ?>
                <button type="button" class="btn btn--google btn--block" id="google-login-btn">
                    <svg width="20" height="20" viewBox="0 0 24 24">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                    </svg>
                    <?php esc_html_e('Login with Google', 'ippgi'); ?>
                </button>
                <?php
            }
            ?>

            <p class="login-modal__signup">
                <?php esc_html_e("Don't have an account?", 'ippgi'); ?>
                <a href="<?php echo esc_url(ippgi_get_register_url()); ?>"><?php esc_html_e('Sign up', 'ippgi'); ?></a>
            </p>
        </div>

        <p class="login-modal__terms">
            <?php
            printf(
                /* translators: %1$s: terms link, %2$s: privacy link */
                esc_html__('By continuing, you agree to PPGI Price\'s %1$s and %2$s.', 'ippgi'),
                '<a href="' . esc_url(home_url('/terms')) . '">' . esc_html__('Terms&Conditions', 'ippgi') . '</a>',
                '<a href="' . esc_url(home_url('/privacy')) . '">' . esc_html__('Privacy Policy', 'ippgi') . '</a>'
            );
            ?>
        </p>
    </div>
</div>
