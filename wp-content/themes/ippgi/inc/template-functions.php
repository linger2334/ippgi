<?php
/**
 * Template Functions
 * Helper functions for template files
 *
 * @package IPPGI
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Custom Nav Walker for desktop navigation
 */
class IPPGI_Nav_Walker extends Walker_Nav_Menu {
    /**
     * Start element output
     */
    public function start_el(&$output, $item, $depth = 0, $args = null, $id = 0) {
        $indent = ($depth) ? str_repeat("\t", $depth) : '';

        $classes   = empty($item->classes) ? [] : (array) $item->classes;
        $classes[] = 'header-nav__item';

        if (in_array('menu-item-has-children', $classes, true)) {
            $classes[] = 'has-dropdown';
        }

        $class_names = implode(' ', apply_filters('nav_menu_css_class', array_filter($classes), $item, $args, $depth));
        $class_names = $class_names ? ' class="' . esc_attr($class_names) . '"' : '';

        $output .= $indent . '<li' . $class_names . '>';

        $atts           = [];
        $atts['title']  = !empty($item->attr_title) ? $item->attr_title : '';
        $atts['target'] = !empty($item->target) ? $item->target : '';
        $atts['rel']    = !empty($item->xfn) ? $item->xfn : '';
        $atts['href']   = !empty($item->url) ? $item->url : '';
        $atts['class']  = 'header-nav__link';

        if (in_array('current-menu-item', $classes, true)) {
            $atts['class'] .= ' is-active';
        }

        $atts = apply_filters('nav_menu_link_attributes', $atts, $item, $args, $depth);

        $attributes = '';
        foreach ($atts as $attr => $value) {
            if (!empty($value)) {
                $value       = ('href' === $attr) ? esc_url($value) : esc_attr($value);
                $attributes .= ' ' . $attr . '="' . $value . '"';
            }
        }

        $item_output  = $args->before;
        $item_output .= '<a' . $attributes . '>';
        $item_output .= $args->link_before . apply_filters('the_title', $item->title, $item->ID) . $args->link_after;
        $item_output .= '</a>';
        $item_output .= $args->after;

        $output .= apply_filters('walker_nav_menu_start_el', $item_output, $item, $depth, $args);
    }
}

/**
 * Get login URL
 */
function ippgi_get_login_url() {
    $login_page = get_theme_mod('ippgi_login_page', 0);

    if ($login_page) {
        return get_permalink($login_page);
    }

    // Check if Simple Membership plugin is active
    if (function_exists('swpm_get_page_id_by_slug')) {
        return SwpmSettings::get_instance()->get_value('login-page-url');
    }

    return wp_login_url();
}

/**
 * Get register URL
 */
function ippgi_get_register_url() {
    $register_page = get_theme_mod('ippgi_register_page', 0);

    if ($register_page) {
        return get_permalink($register_page);
    }

    // Check if Simple Membership plugin is active
    if (class_exists('SwpmSettings')) {
        return SwpmSettings::get_instance()->get_value('registration-page-url');
    }

    return wp_registration_url();
}

/**
 * Get profile URL
 */
function ippgi_get_profile_url() {
    // First check theme customizer setting
    $profile_page = get_theme_mod('ippgi_profile_page', 0);

    if ($profile_page) {
        return get_permalink($profile_page);
    }

    // Default to custom profile page
    return home_url('/profile/');
}

/**
 * Get edit profile URL
 */
function ippgi_get_edit_profile_url() {
    // First check theme customizer setting
    $edit_profile_page = get_theme_mod('ippgi_edit_profile_page', 0);

    if ($edit_profile_page) {
        return get_permalink($edit_profile_page);
    }

    // Default to custom edit profile page
    return home_url('/edit-profile/');
}

/**
 * Get subscription end date for user
 */
function ippgi_get_subscription_end_date($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }

    // Check if Simple Membership plugin is active
    if (class_exists('SwpmMemberUtils')) {
        $wp_user = get_user_by('id', $user_id);
        if ($wp_user) {
            $swpm_member = SwpmMemberUtils::get_user_by_user_name($wp_user->user_login);
            if ($swpm_member && !empty($swpm_member->subscription_starts)) {
                // Calculate end date based on membership level duration
                $start_date = $swpm_member->subscription_starts;
                // Default to 1 year subscription
                $end_date = date('F j, Y', strtotime($start_date . ' +1 year'));
                return $end_date;
            }
        }
    }

    // Fallback
    return __('N/A', 'ippgi');
}

/**
 * Calculate reading time for a post
 */
function ippgi_reading_time($post_id = null) {
    if (!$post_id) {
        $post_id = get_the_ID();
    }

    $content    = get_post_field('post_content', $post_id);
    $word_count = str_word_count(strip_tags($content));
    $minutes    = ceil($word_count / 200); // Average reading speed

    return sprintf(
        /* translators: %d: number of minutes */
        _n('%d min read', '%d min read', $minutes, 'ippgi'),
        $minutes
    );
}

/**
 * Get related posts
 */
function ippgi_get_related_posts($post_id, $count = 3) {
    $categories = get_the_category($post_id);

    if (empty($categories)) {
        return new WP_Query();
    }

    $category_ids = wp_list_pluck($categories, 'term_id');

    return new WP_Query([
        'category__in'        => $category_ids,
        'post__not_in'        => [$post_id],
        'posts_per_page'      => $count,
        'ignore_sticky_posts' => true,
    ]);
}

/**
 * Get user favorites
 */
function ippgi_get_user_favorites($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }

    if (!$user_id) {
        return [];
    }

    $favorites = get_user_meta($user_id, 'ippgi_favorites', true);

    if (empty($favorites) || !is_array($favorites)) {
        return [];
    }

    // Material type mapping
    $material_types = [
        'gi' => ['name' => __('GI', 'ippgi'), 'type' => 'gi'],
        'gl' => ['name' => __('GL', 'ippgi'), 'type' => 'gl'],
        'ppgi' => ['name' => __('PPGI', 'ippgi'), 'type' => 'ppgi'],
        'hrc' => ['name' => __('HRC', 'ippgi'), 'type' => 'hrc'],
        'crc_hard' => ['name' => __('CRC Hard', 'ippgi'), 'type' => 'crc_hard'],
        'al' => ['name' => __('Aluminum Sheet', 'ippgi'), 'type' => 'al'],
    ];

    $result = [];
    foreach ($favorites as $favorite_id) {
        // Parse favorite_id format: type-spec (e.g., "ppgi-0.09*1000")
        $parts = explode('-', $favorite_id, 2);
        $type = $parts[0] ?? '';
        $spec = $parts[1] ?? '';

        if (isset($material_types[$type])) {
            $result[] = [
                'id' => $favorite_id,
                'name' => $material_types[$type]['name'],
                'spec' => $spec,
                'type' => $material_types[$type]['type'],
            ];
        }
    }

    return $result;
}

/**
 * Get user's invite link
 */
function ippgi_get_user_invite_link($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }

    if (!$user_id) {
        return home_url('/');
    }

    $invite_code = get_user_meta($user_id, 'ippgi_invite_code', true);

    if (!$invite_code) {
        $invite_code = wp_generate_password(8, false);
        update_user_meta($user_id, 'ippgi_invite_code', $invite_code);
    }

    return add_query_arg('ref', $invite_code, home_url('/'));
}

/**
 * Get user referral count
 */
function ippgi_get_user_referral_count($user_id) {
    return (int) get_user_meta($user_id, 'ippgi_referral_count', true);
}

/**
 * Get user converted referrals count
 */
function ippgi_get_user_converted_referrals($user_id) {
    return (int) get_user_meta($user_id, 'ippgi_converted_referrals', true);
}

/**
 * Format price with currency
 */
function ippgi_format_price($amount, $currency = 'CNY') {
    $symbols = [
        'CNY' => '¥',
        'USD' => '$',
        'EUR' => '€',
    ];

    $symbol = isset($symbols[$currency]) ? $symbols[$currency] : $currency . ' ';

    return $symbol . number_format($amount, 0);
}

/**
 * Get next billing date (placeholder)
 */
function ippgi_get_next_billing_date() {
    // This will be implemented with Simple Membership integration
    return date_i18n(get_option('date_format'), strtotime('+1 month'));
}

/**
 * Get trial end date (placeholder)
 */
function ippgi_get_trial_end_date() {
    // This will be implemented with Simple Membership integration
    return date_i18n(get_option('date_format'), strtotime('+7 days'));
}

/**
 * Add custom body classes
 */
function ippgi_body_classes($classes) {
    // Add class if user is logged in
    if (is_user_logged_in()) {
        $classes[] = 'is-logged-in';

        if (ippgi_user_has_plus()) {
            $classes[] = 'is-plus-member';
        }
    }

    // Add class for page templates
    if (is_page_template()) {
        $template  = get_page_template_slug();
        $classes[] = 'page-template-' . sanitize_html_class(str_replace('.php', '', basename($template)));
    }

    return $classes;
}
add_filter('body_class', 'ippgi_body_classes');

/**
 * Modify archive titles
 */
function ippgi_archive_title($title) {
    if (is_category()) {
        $title = single_cat_title('', false);
    } elseif (is_tag()) {
        $title = single_tag_title('', false);
    } elseif (is_author()) {
        $title = get_the_author();
    } elseif (is_post_type_archive()) {
        $title = post_type_archive_title('', false);
    }

    return $title;
}
add_filter('get_the_archive_title', 'ippgi_archive_title');

/**
 * Add custom excerpt length
 */
function ippgi_excerpt_length($length) {
    return 30;
}
add_filter('excerpt_length', 'ippgi_excerpt_length');

/**
 * Add custom excerpt more
 */
function ippgi_excerpt_more($more) {
    return '...';
}
add_filter('excerpt_more', 'ippgi_excerpt_more');

/**
 * Get subscribe page URL
 */
function ippgi_get_subscribe_url() {
    $subscribe_page = get_theme_mod('ippgi_subscribe_page', 0);

    if ($subscribe_page) {
        return get_permalink($subscribe_page);
    }

    return home_url('/subscribe');
}

/**
 * Check if user is subscribed (has Plus or Trial membership)
 * Note: ippgi_user_has_plus() is defined in inc/membership.php
 */
function ippgi_is_user_subscribed($user_id = null) {
    if (function_exists('ippgi_user_has_plus')) {
        return ippgi_user_has_plus($user_id);
    }
    if (function_exists('ippgi_user_has_trial')) {
        return ippgi_user_has_trial($user_id);
    }
    return false;
}

/**
 * Check if user is on trial
 */
function ippgi_user_is_trial($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }

    if (!$user_id) {
        return false;
    }

    // Check Simple Membership Plugin
    if (class_exists('SwpmMemberUtils')) {
        $member = SwpmMemberUtils::get_user_by_user_name(get_userdata($user_id)->user_login);
        if ($member) {
            $membership_level = SwpmMemberUtils::get_member_field_by_id($member->member_id, 'membership_level');
            // Assuming level 3 is Trial
            return intval($membership_level) === 3;
        }
    }

    // Fallback: check user meta
    $subscription_level = get_user_meta($user_id, 'ippgi_subscription_level', true);
    return $subscription_level === 'trial';
}

/**
 * Highlight search terms in text
 *
 * @param string $text The text to search in
 * @param string $search_query The search query
 * @return string Text with highlighted terms
 */
function ippgi_highlight_search_terms($text, $search_query) {
    if (empty($search_query) || empty($text)) {
        return $text;
    }

    // Split search query into individual words
    $terms = preg_split('/\s+/', $search_query);
    $terms = array_filter($terms); // Remove empty strings

    foreach ($terms as $term) {
        // Escape special regex characters
        $term = preg_quote($term, '/');

        // Case-insensitive replacement with highlight span
        $text = preg_replace(
            '/(' . $term . ')/i',
            '<mark class="search-highlight">$1</mark>',
            $text
        );
    }

    return $text;
}

/**
 * Limit search to posts only (exclude pages) and filter by date range
 *
 * @param WP_Query $query The query object
 */
function ippgi_search_filter($query) {
    if (!is_admin() && $query->is_main_query() && $query->is_search()) {
        $query->set('post_type', 'post');

        // Handle date range filtering
        ippgi_apply_date_filter($query);
    }
}
add_action('pre_get_posts', 'ippgi_search_filter');

/**
 * Filter blog posts by date range
 *
 * @param WP_Query $query The query object
 */
function ippgi_blog_date_filter($query) {
    if (!is_admin() && $query->is_main_query() && $query->is_home()) {
        // Handle date range filtering
        ippgi_apply_date_filter($query);
    }
}
add_action('pre_get_posts', 'ippgi_blog_date_filter');

/**
 * Apply date filter to query
 *
 * @param WP_Query $query The query object
 */
function ippgi_apply_date_filter($query) {
    $date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
    $date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';

    if ($date_from || $date_to) {
        $date_query = [];

        if ($date_from) {
            $date_query['after'] = $date_from;
        }

        if ($date_to) {
            $date_query['before'] = $date_to . ' 23:59:59';
        }

        $date_query['inclusive'] = true;

        $query->set('date_query', [$date_query]);
    }
}
