<?php
/**
 * Template Name: Login Page
 *
 * @package IPPGI
 * @since 1.0.0
 */

// Redirect if already logged in
if (is_user_logged_in()) {
    wp_redirect(home_url('/'));
    exit;
}

// Use minimal layout without header/footer navigation
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php wp_head(); ?>
</head>
<body <?php body_class('login-page-body'); ?>>

<main class="login-page">
    <!-- Brand Logo at top -->
    <div class="login-page__brand">
        <span class="login-page__brand-text">PPGI.CN</span>
    </div>

    <!-- Login Card - large white panel -->
    <div class="login-card">
        <!-- Title at top of card -->
        <div class="login-card__header">
            <h1 class="login-card__title"><?php esc_html_e('Log into your account', 'ippgi'); ?></h1>
        </div>

        <!-- Google Login Button and signup link in middle -->
        <div class="login-card__body">
            <div class="login-card__actions">
                <?php
                // Generate Google login URL
                $google_login_url = add_query_arg('swpm_social_login', 'google', home_url('/'));
                ?>
                <a href="<?php echo esc_url($google_login_url); ?>" class="login-btn login-btn--google">
                    <svg width="30" height="30" viewBox="0 0 24 24">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                    </svg>
                    <span><?php esc_html_e('Login with google', 'ippgi'); ?></span>
                </a>

                <div class="login-card__signin">
                    <span class="login-card__signin-text"><?php esc_html_e("Don't have an account?", 'ippgi'); ?></span>
                    <a href="#" class="login-card__signin-link"><?php esc_html_e('Sign up', 'ippgi'); ?></a>
                </div>
            </div>
        </div>

        <!-- Terms at bottom -->
        <div class="login-card__footer">
            <div class="login-card__terms">
                <p>
                    <?php esc_html_e("By continuing, you agree to PPGI Price's", 'ippgi'); ?>
                    <a href="<?php echo esc_url(home_url('/terms')); ?>"><?php esc_html_e('Terms&Conditions', 'ippgi'); ?></a>
                    <?php esc_html_e('and', 'ippgi'); ?>
                    <a href="<?php echo esc_url(home_url('/privacy')); ?>"><?php esc_html_e('Privacy Policy.', 'ippgi'); ?></a>
                </p>
            </div>
        </div>
    </div>
</main>

<?php wp_footer(); ?>
</body>
</html>
