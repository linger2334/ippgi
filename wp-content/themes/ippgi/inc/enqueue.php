<?php
/**
 * Enqueue scripts and styles
 *
 * @package IPPGI
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enqueue theme styles
 */
function ippgi_enqueue_styles() {
    // CSS Variables
    wp_enqueue_style(
        'ippgi-variables',
        IPPGI_THEME_URI . '/assets/css/variables.css',
        [],
        IPPGI_VERSION
    );

    // Base styles
    wp_enqueue_style(
        'ippgi-base',
        IPPGI_THEME_URI . '/assets/css/base.css',
        ['ippgi-variables'],
        IPPGI_VERSION
    );

    // Component styles
    wp_enqueue_style(
        'ippgi-components',
        IPPGI_THEME_URI . '/assets/css/components.css',
        ['ippgi-base'],
        IPPGI_VERSION
    );

    // Layout styles
    wp_enqueue_style(
        'ippgi-layout',
        IPPGI_THEME_URI . '/assets/css/layout.css',
        ['ippgi-components'],
        IPPGI_VERSION
    );

    // Responsive styles
    wp_enqueue_style(
        'ippgi-responsive',
        IPPGI_THEME_URI . '/assets/css/responsive.css',
        ['ippgi-layout'],
        IPPGI_VERSION
    );

    // Main theme stylesheet (contains @import declarations as fallback)
    wp_enqueue_style(
        'ippgi-style',
        get_stylesheet_uri(),
        ['ippgi-responsive'],
        IPPGI_VERSION
    );
}
add_action('wp_enqueue_scripts', 'ippgi_enqueue_styles');

/**
 * Enqueue theme scripts
 */
function ippgi_enqueue_scripts() {
    // Navigation script
    wp_enqueue_script(
        'ippgi-navigation',
        IPPGI_THEME_URI . '/assets/js/navigation.js',
        [],
        IPPGI_VERSION,
        true
    );

    // Main script
    wp_enqueue_script(
        'ippgi-main',
        IPPGI_THEME_URI . '/assets/js/main.js',
        ['ippgi-navigation'],
        IPPGI_VERSION,
        true
    );

    // Localize script with data
    wp_localize_script('ippgi-main', 'ippgiData', [
        'ajaxUrl'       => admin_url('admin-ajax.php'),
        'restUrl'       => rest_url('ippgi-prices/v1/'),
        'nonce'         => wp_create_nonce('ippgi_nonce'),
        'homeUrl'       => home_url('/'),
        'loginUrl'      => ippgi_get_login_url(),
        'subscribeUrl'  => home_url('/subscribe'),
        'pricesUrl'     => home_url('/prices'),
        'isLoggedIn'    => is_user_logged_in(),
        'hasPremium'    => ippgi_user_can_view_history(),
        'isFrontPage'   => is_front_page(),
        'strings'       => [
            'loading'   => __('Loading...', 'ippgi'),
            'error'     => __('An error occurred. Please try again.', 'ippgi'),
            'copied'    => __('Copied!', 'ippgi'),
            'added'     => __('Added to favorites', 'ippgi'),
            'removed'   => __('Removed from favorites', 'ippgi'),
        ],
    ]);

    // Comment reply script (only on single posts)
    if (is_singular() && comments_open() && get_option('thread_comments')) {
        wp_enqueue_script('comment-reply');
    }
}
add_action('wp_enqueue_scripts', 'ippgi_enqueue_scripts');

/**
 * Add preconnect for external resources
 */
function ippgi_resource_hints($urls, $relation_type) {
    if ($relation_type === 'preconnect') {
        // Add Google Fonts if used
        // $urls[] = ['href' => 'https://fonts.googleapis.com', 'crossorigin' => true];
        // $urls[] = ['href' => 'https://fonts.gstatic.com', 'crossorigin' => true];
    }
    return $urls;
}
add_filter('wp_resource_hints', 'ippgi_resource_hints', 10, 2);

/**
 * Add async/defer to scripts
 */
function ippgi_script_loader_tag($tag, $handle, $src) {
    // Add defer to theme scripts
    $defer_scripts = ['ippgi-navigation', 'ippgi-main'];

    if (in_array($handle, $defer_scripts, true)) {
        return str_replace(' src', ' defer src', $tag);
    }

    return $tag;
}
add_filter('script_loader_tag', 'ippgi_script_loader_tag', 10, 3);

/**
 * Remove jQuery migrate (optional, for performance)
 */
function ippgi_remove_jquery_migrate($scripts) {
    if (!is_admin() && isset($scripts->registered['jquery'])) {
        $script = $scripts->registered['jquery'];
        if ($script->deps) {
            $script->deps = array_diff($script->deps, ['jquery-migrate']);
        }
    }
}
add_action('wp_default_scripts', 'ippgi_remove_jquery_migrate');

/**
 * Enqueue admin styles
 */
function ippgi_admin_enqueue_styles() {
    wp_enqueue_style(
        'ippgi-admin',
        IPPGI_THEME_URI . '/assets/css/admin.css',
        [],
        IPPGI_VERSION
    );
}
add_action('admin_enqueue_scripts', 'ippgi_admin_enqueue_styles');
