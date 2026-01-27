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
    // Auto version based on latest CSS file modification time
    $css_files = glob(get_template_directory() . '/assets/css/*.css');
    $latest_time = 0;
    foreach ($css_files as $file) {
        $mtime = filemtime($file);
        if ($mtime > $latest_time) {
            $latest_time = $mtime;
        }
    }
    define('IPPGI_VERSION', $latest_time ?: '1.7.3');
} else {
    define('IPPGI_VERSION', '1.7.3');
}
define('IPPGI_THEME_DIR', get_template_directory());
define('IPPGI_THEME_URI', get_template_directory_uri());

/**
 * Development Mode
 * Set to true to simulate logged-in user with Plus membership (for local testing)
 * IMPORTANT: Set to false in production!
 */
define('IPPGI_DEV_MODE', false);
define('IPPGI_DEV_MEMBERSHIP_LEVEL', 'plus'); // Options: 'guest', 'basic', 'trial', 'plus', 'cancelled'

/**
 * Theme Setup
 */
function ippgi_setup() {
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

    // Set content width
    if (!isset($content_width)) {
        $content_width = 1200;
    }
}
add_action('after_setup_theme', 'ippgi_setup');

/**
 * Include theme files
 */
require_once IPPGI_THEME_DIR . '/inc/enqueue.php';
require_once IPPGI_THEME_DIR . '/inc/customizer.php';
require_once IPPGI_THEME_DIR . '/inc/template-functions.php';
require_once IPPGI_THEME_DIR . '/inc/membership.php';
require_once IPPGI_THEME_DIR . '/inc/announcement.php';

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
