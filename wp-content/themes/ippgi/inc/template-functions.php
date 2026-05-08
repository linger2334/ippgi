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
 * Normalize product type aliases used across templates, favorites, and URLs.
 *
 * @param string $product_type Raw product type.
 * @return string
 */
function ippgi_normalize_product_type($product_type) {
    $product_type = strtolower((string) $product_type);

    $aliases = array(
        'crc_hard' => 'crc',
        'al'       => 'aluminum',
    );

    return isset($aliases[$product_type]) ? $aliases[$product_type] : $product_type;
}

/**
 * Get product types that are allowed to appear on the frontend.
 *
 * HRC remains available in backend data collection, but is intentionally hidden from the site UI.
 *
 * @return string[]
 */
function ippgi_get_visible_product_types() {
    return array(
        'ppgi',
        'gi',
        'gl',
        'crc',
        'aluminum',
    );
}

/**
 * Check whether a product type is visible on the frontend.
 *
 * @param string $product_type Product type or alias.
 * @return bool
 */
function ippgi_is_visible_product_type($product_type) {
    return in_array(
        ippgi_normalize_product_type($product_type),
        ippgi_get_visible_product_types(),
        true
    );
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
        'gi' => ['name' => ippgi_get_product_display_name('gi'), 'type' => 'gi'],
        'gl' => ['name' => ippgi_get_product_display_name('gl'), 'type' => 'gl'],
        'ppgi' => ['name' => ippgi_get_product_display_name('ppgi'), 'type' => 'ppgi'],
        'hrc' => ['name' => ippgi_get_product_display_name('hrc'), 'type' => 'hrc'],
        'crc_hard' => ['name' => ippgi_get_product_display_name('crc'), 'type' => 'crc_hard'],
        'crc' => ['name' => ippgi_get_product_display_name('crc'), 'type' => 'crc_hard'],
        'al' => ['name' => ippgi_get_product_display_name('aluminum'), 'type' => 'al'],
        'aluminum' => ['name' => ippgi_get_product_display_name('aluminum'), 'type' => 'al'],
    ];

    $result = [];
    foreach ($favorites as $favorite_id) {
        // Parse favorite_id format: type-spec (e.g., "ppgi-1482328115005964290_1000_0.11_彩涂")
        $parts = explode('-', $favorite_id, 2);
        $type = $parts[0] ?? '';
        $spec = $parts[1] ?? '';

        if (!ippgi_is_visible_product_type($type)) {
            continue;
        }

        if (isset($material_types[$type])) {
            // Parse spec into human-readable display
            // productSpec format: "categoryId_width_thickness_名称"
            $display_spec = $spec;
            $spec_parts = explode('_', $spec);
            if (count($spec_parts) >= 4) {
                $width = $spec_parts[1];
                $thickness = $spec_parts[2];
                // Only show product name for HRC and AL (they have consistent naming)
                // For PPGI, GI, GL, CRC - only show dimensions (they have mixed Chinese/English names)
                if (in_array($type, ['hrc', 'al', 'aluminum'], true)) {
                    $product_name = end($spec_parts);
                    $display_spec = $thickness . '*' . $width . ' ' . $product_name;
                } else {
                    $display_spec = $thickness . '*' . $width;
                }
            }

            $result[] = [
                'id' => $favorite_id,
                'name' => $material_types[$type]['name'],
                'spec' => $display_spec,
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
 * Get the latest cached price-list fetched time.
 *
 * @return string
 */
function ippgi_get_latest_prices_fetched_at() {
    if (!function_exists('ippgi_prices') || !isset(ippgi_prices()->cache_manager)) {
        return '';
    }

    $price_list = ippgi_prices()->cache_manager->get_price_list();

    return is_array($price_list) ? ($price_list['fetched_at'] ?? '') : '';
}

/**
 * Format the cached price-list fetched time for UI display.
 *
 * @param string $fetched_at Cached fetched_at value in site timezone.
 * @param string $format     Output format.
 * @return string
 */
function ippgi_format_prices_fetched_at($fetched_at = '', $format = 'Y-m-d H:i:s') {
    $timezone = wp_timezone();

    if (!empty($fetched_at)) {
        $datetime = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $fetched_at, $timezone);
        if ($datetime instanceof DateTimeImmutable) {
            return $datetime->format($format);
        }
    }

    return wp_date($format, time(), $timezone);
}

/**
 * Get next billing date (placeholder)
 */
function ippgi_get_next_billing_date() {
    // This will be implemented with Simple Membership integration
    return date_i18n(get_option('date_format'), strtotime('+1 month'));
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
 * Check if user is subscribed (has Plus membership)
 * Note: ippgi_user_has_plus() is defined in inc/membership.php
 */
function ippgi_is_user_subscribed($user_id = null) {
    if (function_exists('ippgi_user_has_plus')) {
        return ippgi_user_has_plus($user_id);
    }
    return false;
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

/**
 * Get product dimensions range (thickness and width) from cached price list
 *
 * @param string $product_type Product type key (ppgi, gi, gl, hrc, crc, aluminum)
 * @return array|false Array with min/max thickness and width, or false on failure
 */
function ippgi_get_product_dimensions_range($product_type) {
    // Map product type key to category name used in cache
    $category_mapping = [
        'ppgi'     => 'PPGI',
        'gi'       => 'GI',
        'gl'       => 'GL',
        'hrc'      => 'HRC',
        'crc'      => 'CRC Hard',
        'aluminum' => 'AL',
    ];

    if (!isset($category_mapping[$product_type])) {
        return false;
    }

    $category_name = $category_mapping[$product_type];

    $cache_manager = function_exists('ippgi_prices') ? ippgi_prices()->cache_manager : null;
    $category_data = $cache_manager ? $cache_manager->get_category_price_list($category_name) : false;

    if (!$category_data) {
        return false;
    }

    // Check if data has result structure (直接在 category_data 下，不是 data.result)
    if (!isset($category_data['result']) || !is_array($category_data['result'])) {
        return false;
    }

    $result = $category_data['result'];

    // Collect all thicknesses and widths
    $thicknesses = [];
    $widths = [];

    foreach ($result as $width => $items) {
        // Width is the key
        $widths[] = intval($width);

        // Items contain thickness
        if (is_array($items)) {
            foreach ($items as $item) {
                if (isset($item['thickness']) && $item['thickness'] !== '') {
                    $thicknesses[] = floatval($item['thickness']);
                }
            }
        }
    }

    if (empty($thicknesses) || empty($widths)) {
        return false;
    }

    return [
        'min_thickness' => min($thicknesses),
        'max_thickness' => max($thicknesses),
        'min_width'     => min($widths),
        'max_width'     => max($widths),
    ];
}

/**
 * Format dimensions range for display
 *
 * @param array $range Dimensions range from ippgi_get_product_dimensions_range()
 * @return array Formatted strings for thickness and width
 */
function ippgi_format_dimensions_range($range) {
    if (!$range) {
        return [
            'thickness' => 'N/A',
            'width'     => 'N/A',
        ];
    }

    // Format thickness (remove trailing zeros)
    $min_thickness = rtrim(rtrim(number_format($range['min_thickness'], 2), '0'), '.');
    $max_thickness = rtrim(rtrim(number_format($range['max_thickness'], 2), '0'), '.');

    return [
        'thickness' => $min_thickness . '-' . $max_thickness . 'mm',
        'width'     => $range['min_width'] . '-' . $range['max_width'] . 'mm',
    ];
}

/**
 * Build the i18n strings dictionary used by main.js.
 *
 * Strings are pre-translated server-side via TranslatePress because TP skips
 * JSON content inside <script> tags when scanning HTML for translation.
 *
 * Result is cached in a transient per language to avoid running the full TP
 * pipeline (and any synchronous Google Translate calls) on every page load.
 * Cache lives 12 hours; on default language we skip translation entirely.
 *
 * @return array
 */
function ippgi_get_js_i18n_strings() {
    static $memo = [];

    $current_lang = !empty($GLOBALS['TRP_LANGUAGE']) ? $GLOBALS['TRP_LANGUAGE'] : 'default';
    if (isset($memo[$current_lang])) {
        return $memo[$current_lang];
    }

    $tp_active   = function_exists('trp_translate');
    $default_lng = $tp_active ? null : 'default';
    if ($tp_active) {
        $trp = TRP_Translate_Press::get_trp_instance();
        $tp_settings = $trp->get_component('settings')->get_settings();
        $default_lng = isset($tp_settings['default-language']) ? $tp_settings['default-language'] : 'en_US';
    }

    // Default language never needs TP processing — return raw __() output
    $needs_translation = $tp_active && ($current_lang !== $default_lng) && ($current_lang !== 'default');

    // Bump the version segment to invalidate stale caches when this code changes.
    $cache_key = 'ippgi_js_i18n_v5_' . md5($current_lang);
    if ($needs_translation) {
        $cached = get_transient($cache_key);
        if (is_array($cached)) {
            $memo[$current_lang] = $cached;
            return $cached;
        }
    }

    // Translates an English source string. Tries gettext first (controlled by
    // user via "Translate Strings → Gettext"); only falls back to TP regular
    // string translation when gettext returned the original unchanged.
    // This avoids double-translation bugs where a gettext result like "Mai"
    // would otherwise be re-fed to TP/Google as if it were English.
    //
    // Both branches pass through preg_replace + html_entity_decode because TP
    // can return values like S&#039;orienter or <translate-press>...</translate-press>
    // wrappings, which would corrupt JSON output / cause double-encoding in JS.
    $sanitize = function ($s) {
        $s = preg_replace('#<translate-press[^>]*>(.*?)</translate-press>#s', '$1', $s);
        return html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    };
    $tr = function ($english) use ($needs_translation, $sanitize) {
        $gettext = __($english, 'ippgi');
        if (!$needs_translation || $gettext !== $english) {
            return $sanitize($gettext);
        }
        return $sanitize(trp_translate($english, null, false));
    };

    $strings = [
        'loading'             => $tr('Loading...'),
        'loadingPrices'       => $tr('Loading prices...'),
        'error'               => $tr('An error occurred. Please try again.'),
        'copied'              => $tr('Copied!'),
        'added'               => $tr('Added to favorites'),
        'removed'             => $tr('Removed from favorites'),
        'favoriteAddedFull'   => $tr('The dataset has been added to your favorites.'),
        'favoriteRemovedFull' => $tr('The dataset has been removed from your favorites.'),
        'submitting'          => $tr('Submitting...'),
        'noPriceData'         => $tr('No price data available'),
        'noPriceDataWidth'    => $tr('No price data available for this width.'),
        'failedLoadPrices'    => $tr('Failed to load prices'),
        'updatedLabel'        => $tr('Updated:'),
        'timezoneSuffix'      => $tr('(UTC+8)'),
        'trend'               => $tr('Trend'),
        'startDateEndDate'    => $tr('Start Date ~ End Date'),
        'thProducts'          => $tr('Products'),
        'thDimensions'        => $tr('Dimensions(mm)'),
        'thLatest'            => $tr('Latest($)'),
        'months'              => [
            $tr('January'),
            $tr('February'),
            $tr('March'),
            $tr('April'),
            $tr('May'),
            $tr('June'),
            $tr('July'),
            $tr('August'),
            $tr('September'),
            $tr('October'),
            $tr('November'),
            $tr('December'),
        ],
    ];

    if ($needs_translation) {
        set_transient($cache_key, $strings, 12 * HOUR_IN_SECONDS);
    }

    $memo[$current_lang] = $strings;
    return $strings;
}

/**
 * Bust JS i18n strings cache when TP saves/edits translations.
 */
function ippgi_invalidate_js_i18n_cache() {
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ippgi_js_i18n_%' OR option_name LIKE '_transient_timeout_ippgi_js_i18n_%'");
    // Also flush object cache (Redis/Memcached) if active — transients route through it
    if (function_exists('wp_cache_flush_group')) {
        @wp_cache_flush_group('ippgi_i18n');
    }
}
add_action('trp_save_editor_translations_regular_strings', 'ippgi_invalidate_js_i18n_cache');
add_action('trp_save_editor_translations_gettext_strings', 'ippgi_invalidate_js_i18n_cache');
add_action('trp_machine_translated_strings', 'ippgi_invalidate_js_i18n_cache');
