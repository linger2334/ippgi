<?php
/**
 * IPPGI Theme Functions
 *
 * @package IPPGI
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Theme version - increment to bust cache
// Development: use file modification time for auto cache busting
// Production: use fixed version number
if (defined('WP_DEBUG') && WP_DEBUG) {
    // Auto version based on latest asset file modification time
    $css_files = glob(get_template_directory() . '/assets/css/*.css');
    $js_files = glob(get_template_directory() . '/assets/js/*.js');
    $latest_time = 0;
    foreach (array_merge($css_files ?: [], $js_files ?: []) as $file) {
        $mtime = filemtime($file);
        if ($mtime > $latest_time) {
            $latest_time = $mtime;
        }
    }
    define('IPPGI_VERSION', $latest_time ?: '1.8.2');
} else {
    define('IPPGI_VERSION', '1.8.2');
}
define('IPPGI_THEME_DIR', get_template_directory());
define('IPPGI_THEME_URI', get_template_directory_uri());

/**
 * Development Mode
 * Set to true to simulate logged-in user with Plus membership (for local testing)
 * IMPORTANT: Set to false in production!
 */
define('IPPGI_DEV_MODE', false);
define('IPPGI_DEV_MEMBERSHIP_LEVEL', 'plus'); // Options: 'guest', 'basic', 'bonus', 'plus', 'cancelled'

/**
 * Theme Setup
 */
function ippgi_setup() {
    load_theme_textdomain('ippgi', IPPGI_THEME_DIR . '/languages');

    // Add default posts and comments RSS feed links to head
    add_theme_support('automatic-feed-links');

    // Let WordPress manage the document title
    add_theme_support('title-tag');

    // Enable support for Post Thumbnails
    add_theme_support('post-thumbnails');

    // Register navigation menus
    register_nav_menus([
        'primary'   => __('Primary Menu', 'ippgi'),
        'mobile'    => __('Mobile Menu', 'ippgi'),
        'footer'    => __('Footer Menu', 'ippgi'),
    ]);

    // Switch default core markup to output valid HTML5
    add_theme_support('html5', [
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
        'style',
        'script',
    ]);

    // Add support for responsive embeds
    add_theme_support('responsive-embeds');

    // Add support for custom logo
    add_theme_support('custom-logo', [
        'height'      => 60,
        'width'       => 200,
        'flex-height' => true,
        'flex-width'  => true,
    ]);

    // Load theme styles inside Gutenberg editor to align preview with frontend typography.
    add_theme_support('editor-styles');
    add_editor_style('assets/css/editor-style.css');

    // Set content width
    if (!isset($content_width)) {
        $content_width = 1200;
    }
}
add_action('after_setup_theme', 'ippgi_setup');

/**
 * Include theme files
 */
require_once IPPGI_THEME_DIR . '/inc/country-calling-codes.php';
require_once IPPGI_THEME_DIR . '/inc/enqueue.php';
require_once IPPGI_THEME_DIR . '/inc/customizer.php';
require_once IPPGI_THEME_DIR . '/inc/template-functions.php';
require_once IPPGI_THEME_DIR . '/inc/seo.php';
require_once IPPGI_THEME_DIR . '/inc/membership.php';
require_once IPPGI_THEME_DIR . '/inc/quote-requests.php';
require_once IPPGI_THEME_DIR . '/inc/announcement.php';
require_once IPPGI_THEME_DIR . '/inc/admin-settings.php';

/**
 * Register widget areas
 */
function ippgi_widgets_init() {
    register_sidebar([
        'name'          => __('Sidebar', 'ippgi'),
        'id'            => 'sidebar-1',
        'description'   => __('Add widgets here.', 'ippgi'),
        'before_widget' => '<section id="%1$s" class="widget %2$s">',
        'after_widget'  => '</section>',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ]);

    register_sidebar([
        'name'          => __('Footer Widget Area', 'ippgi'),
        'id'            => 'footer-1',
        'description'   => __('Footer widget area.', 'ippgi'),
        'before_widget' => '<div id="%1$s" class="footer-widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h4 class="footer-widget-title">',
        'after_title'   => '</h4>',
    ]);
}
add_action('widgets_init', 'ippgi_widgets_init');

/**
 * Fix image upload issues
 * Disable big image scaling and increase memory for image processing
 */
add_filter('big_image_size_threshold', '__return_false');
add_filter('wp_image_editors', function($editors) {
    // Prefer GD over Imagick to avoid memory issues
    return ['WP_Image_Editor_GD', 'WP_Image_Editor_Imagick'];
});

/**
 * Force TranslatePress to register its gettext filter early (on wp_loaded)
 * instead of the default wp_head priority 100 — otherwise __('May', 'ippgi')
 * called from wp_enqueue_scripts (e.g., wp_localize_script) returns the
 * original English because TP's filter isn't loaded yet at that point.
 */
add_filter('trp_apply_gettext_early', '__return_true');

/**
 * Redirect logged-in users from login pages to home
 * This handles the redirect after Google/social login
 */
add_action('template_redirect', function() {
    if ((is_page('membership-login') || is_page('login')) && is_user_logged_in()) {
        wp_redirect(home_url('/'));
        exit;
    }
});

/**
 * Protect prices pages:
 * - /prices requires login only
 * - /price-detail requires login only
 */
add_action('template_redirect', function() {
    $is_prices_page = is_page_template('page-templates/page-prices.php') || is_page('prices');
    $is_price_detail_page = is_page_template('page-templates/page-price-detail.php') || is_page('price-detail');

    if (!$is_prices_page && !$is_price_detail_page) {
        return;
    }

    if (!is_user_logged_in()) {
        global $wp;
        $request_path = isset($wp->request) ? ('/' . ltrim($wp->request, '/')) : '/';
        $current_url = home_url($request_path);
        if (!empty($_SERVER['QUERY_STRING'])) {
            $current_url .= '?' . wp_unslash($_SERVER['QUERY_STRING']);
        }
        $login_url = add_query_arg('redirect_to', $current_url, ippgi_get_login_url());
        wp_redirect($login_url);
        exit;
    }
}, 5);

/**
 * Set PayPal SDK locale based on WordPress language setting
 * This ensures PayPal buttons display in the same language as the site
 */
add_filter('swpm_generate_paypal_js_sdk_args', function($sdk_args) {
    // Get WordPress locale (e.g., 'en_US', 'zh_CN', 'fr_FR')
    $wp_locale = get_locale();

    // PayPal SDK uses the same format, so we can use it directly
    // Fallback to en_US if locale is not set
    $sdk_args['locale'] = $wp_locale ?: 'en_US';

    return $sdk_args;
});

/**
 * Remove Stripe's default button CSS so we can use our own styling
 */
add_action('wp_enqueue_scripts', function() {
    wp_deregister_style('swpm.stripe.style');
}, 20);

/**
 * Detect payment success return and show success modal
 *
 * Uses a user meta flag set by ippgi_on_membership_level_change() when user upgrades to Plus.
 * The flag is checked on page load and cleared after showing the modal (one-time).
 */
add_action('wp_footer', function() {
    if (!is_user_logged_in()) {
        return;
    }

    $user_id = get_current_user_id();
    $payment_success = get_user_meta($user_id, 'ippgi_payment_just_completed', true);

    if (!$payment_success) {
        return;
    }

    // Clear the flag so it only shows once
    delete_user_meta($user_id, 'ippgi_payment_just_completed');
    ?>
    <div class="payment-success-overlay" id="payment-success-modal">
        <div class="payment-success-card">
            <div class="payment-success-card__icon">
                <svg width="30" height="30" viewBox="0 0 30 30" fill="none">
                    <circle cx="15" cy="15" r="15" fill="#6abf40"/>
                    <polyline points="9,15 13,20 21,10" fill="none" stroke="#fff" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <h2 class="payment-success-card__title"><?php esc_html_e("You're all set!", 'ippgi'); ?></h2>
            <p class="payment-success-card__desc">
                <?php esc_html_e('Subscription successful!', 'ippgi'); ?><br>
                <?php esc_html_e('Full access to all pricing data.', 'ippgi'); ?>
            </p>
            <button type="button" class="payment-success-card__btn" id="payment-success-continue">
                <?php esc_html_e('Continue to iPPGI', 'ippgi'); ?>
            </button>
        </div>
    </div>
    <script>
    document.getElementById('payment-success-continue').addEventListener('click', function() {
        document.getElementById('payment-success-modal').style.display = 'none';
    });
    </script>
    <?php
}, 99);
