<?php
/**
 * Theme SEO metadata.
 *
 * Provides automatic meta descriptions and keywords for public pages while
 * allowing editors to override either value per post or page.
 *
 * @package IPPGI
 * @since 1.8.2
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clean text before using it in a meta tag.
 *
 * @param string $text Raw text.
 * @return string
 */
function ippgi_seo_clean_text($text) {
    $text = strip_shortcodes((string) $text);
    $text = preg_replace('/\[[^\]]+\]/u', ' ', $text);
    $text = preg_replace('/<!--.*?-->/s', ' ', $text);
    $text = wp_strip_all_tags($text, true);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\s+/u', ' ', $text);

    return trim((string) $text);
}

/**
 * Trim text to a character limit without cutting the final word when possible.
 *
 * @param string $text  Text to trim.
 * @param int    $limit Character limit.
 * @return string
 */
function ippgi_seo_trim_text($text, $limit = 160) {
    $text = ippgi_seo_clean_text($text);
    $limit = max(1, absint($limit));
    $length = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);

    if ($length <= $limit) {
        return $text;
    }

    $trimmed = wp_html_excerpt($text, $limit - 3, '');
    $last_space = function_exists('mb_strrpos')
        ? mb_strrpos($trimmed, ' ', 0, 'UTF-8')
        : strrpos($trimmed, ' ');

    if (false !== $last_space && $last_space > (int) floor($limit * 0.6)) {
        $trimmed = function_exists('mb_substr')
            ? mb_substr($trimmed, 0, $last_space, 'UTF-8')
            : substr($trimmed, 0, $last_space);
    }

    return rtrim($trimmed, " \t\n\r\0\x0B,.;:-") . '...';
}

/**
 * Normalize a comma-separated keyword list.
 *
 * @param string|array $keywords Raw keywords.
 * @param int          $limit    Maximum number of keywords.
 * @return string
 */
function ippgi_seo_normalize_keywords($keywords, $limit = 12) {
    if (!is_array($keywords)) {
        $keywords = preg_split('/[,;]+/u', (string) $keywords);
    }

    $normalized = [];
    $seen = [];

    foreach ((array) $keywords as $keyword) {
        $keyword = ippgi_seo_trim_text($keyword, 80);
        if ('' === $keyword) {
            continue;
        }

        $key = function_exists('mb_strtolower')
            ? mb_strtolower($keyword, 'UTF-8')
            : strtolower($keyword);

        if (isset($seen[$key])) {
            continue;
        }

        $seen[$key] = true;
        $normalized[] = $keyword;

        if (count($normalized) >= $limit) {
            break;
        }
    }

    return implode(', ', $normalized);
}

/**
 * Get default descriptions for template-driven pages whose post content is empty.
 *
 * @return array<string, string>
 */
function ippgi_seo_page_descriptions() {
    return [
        'prices'          => __('Compare current USD prices for PPGI, GI, GL, HRC, CRC hard, and aluminum products from the China market.', 'ippgi'),
        'price-detail'    => __('Review product specifications, historical price trends, and USD price data for steel and aluminum products.', 'ippgi'),
        'blog'            => __('Read steel and aluminum market insights, price analysis, and international industry updates from iPPGI.', 'ippgi'),
        'about'           => __('Learn about iPPGI and our international steel and aluminum price information services.', 'ippgi'),
        'subscribe'       => __('Choose an iPPGI membership plan for access to steel and aluminum price data and market insights.', 'ippgi'),
        'privacy'         => __('Read the iPPGI Privacy Policy and learn how personal information is collected, used, and protected.', 'ippgi'),
        'terms'           => __('Read the terms and conditions that apply when using the iPPGI website and services.', 'ippgi'),
        'login'           => __('Sign in to your iPPGI account.', 'ippgi'),
        'membership-login' => __('Sign in to your iPPGI account.', 'ippgi'),
        'profile'         => __('View your iPPGI account and membership information.', 'ippgi'),
        'edit-profile'    => __('Update your iPPGI account profile and contact information.', 'ippgi'),
        'favorites'       => __('View the steel and aluminum price datasets saved to your iPPGI favorites.', 'ippgi'),
        'invite'          => __('Invite colleagues to iPPGI and view your invitation benefits.', 'ippgi'),
        'payment'         => __('Complete your iPPGI Plus membership subscription.', 'ippgi'),
        'checkout-result' => __('View the result of your iPPGI membership payment.', 'ippgi'),
        'stripe-checkout-result' => __('View the result of your iPPGI membership payment.', 'ippgi'),
        'products'        => __('Explore steel and aluminum products covered by iPPGI price data and market information.', 'ippgi'),
    ];
}

/**
 * Generate a description for a post or page.
 *
 * @param int $post_id Post ID.
 * @return string
 */
function ippgi_seo_generate_post_description($post_id) {
    $post = get_post($post_id);
    if (!$post) {
        return '';
    }

    $page_descriptions = ippgi_seo_page_descriptions();
    if ('page' === $post->post_type && isset($page_descriptions[$post->post_name])) {
        return ippgi_seo_trim_text($page_descriptions[$post->post_name]);
    }

    if (has_excerpt($post)) {
        return ippgi_seo_trim_text($post->post_excerpt);
    }

    $content = ippgi_seo_clean_text($post->post_content);
    if ('' !== $content) {
        return ippgi_seo_trim_text($content);
    }

    $title = ippgi_seo_clean_text(get_the_title($post));
    if ('' !== $title) {
        return ippgi_seo_trim_text(
            sprintf(
                /* translators: %s: page or post title. */
                __('Explore %s on iPPGI for steel and aluminum price data and international market information.', 'ippgi'),
                $title
            )
        );
    }

    return '';
}

/**
 * Get the current request's meta description.
 *
 * @return string
 */
function ippgi_seo_get_description() {
    $post_id = (is_singular() || is_front_page() || is_home()) ? get_queried_object_id() : 0;

    if ($post_id) {
        $manual = get_post_meta($post_id, '_ippgi_seo_description', true);
        if ('' !== trim((string) $manual)) {
            return ippgi_seo_trim_text($manual);
        }
    }

    if (is_front_page()) {
        return ippgi_seo_trim_text(
            __('Track China steel and aluminum prices, historical trends, and market insights for PPGI, GI, GL, HRC, CRC, and aluminum products.', 'ippgi')
        );
    }

    if (is_home()) {
        if ($post_id) {
            $description = ippgi_seo_generate_post_description($post_id);
            if ('' !== $description) {
                return $description;
            }
        }

        return ippgi_seo_trim_text(
            __('Read steel and aluminum market insights, price analysis, and international industry updates from iPPGI.', 'ippgi')
        );
    }

    if (is_singular() && $post_id) {
        return ippgi_seo_generate_post_description($post_id);
    }

    if (is_category() || is_tag() || is_tax()) {
        $term_description = term_description();
        if ('' !== ippgi_seo_clean_text($term_description)) {
            return ippgi_seo_trim_text($term_description);
        }
    }

    if (is_archive()) {
        return ippgi_seo_trim_text(
            sprintf(
                /* translators: %s: archive title. */
                __('Browse %s articles and market information from iPPGI.', 'ippgi'),
                wp_strip_all_tags(get_the_archive_title())
            )
        );
    }

    if (is_search()) {
        return ippgi_seo_trim_text(
            sprintf(
                /* translators: %s: search query. */
                __('Search results for %s on iPPGI.', 'ippgi'),
                get_search_query()
            )
        );
    }

    if (is_404()) {
        return __('The requested iPPGI page could not be found.', 'ippgi');
    }

    $site_description = get_bloginfo('description', 'display');
    if ('' !== ippgi_seo_clean_text($site_description)) {
        return ippgi_seo_trim_text($site_description);
    }

    return ippgi_seo_trim_text(
        __('International steel and aluminum prices, historical trends, and market insights from iPPGI.', 'ippgi')
    );
}

/**
 * Generate keywords for a post or page.
 *
 * @param int $post_id Post ID.
 * @return string
 */
function ippgi_seo_generate_post_keywords($post_id) {
    $post = get_post($post_id);
    if (!$post) {
        return '';
    }

    $keywords = [];
    $title = ippgi_seo_clean_text(get_the_title($post));
    if ('' !== $title) {
        $keywords[] = $title;
    }

    if ('post' === $post->post_type) {
        $tags = wp_get_post_terms($post_id, 'post_tag', ['fields' => 'names']);
        $categories = wp_get_post_terms($post_id, 'category', ['fields' => 'names']);

        $tags = is_wp_error($tags) ? [] : $tags;
        $categories = is_wp_error($categories) ? [] : $categories;

        $keywords = array_merge(
            $keywords,
            $tags,
            $categories
        );
    }

    $keywords = array_merge(
        $keywords,
        ['iPPGI', __('steel prices', 'ippgi'), __('China steel market', 'ippgi')]
    );

    return ippgi_seo_normalize_keywords($keywords);
}

/**
 * Get the current request's meta keywords.
 *
 * Keywords are retained for requested compatibility even though modern search
 * engines generally do not use them as a ranking signal.
 *
 * @return string
 */
function ippgi_seo_get_keywords() {
    $post_id = (is_singular() || is_front_page() || is_home()) ? get_queried_object_id() : 0;

    if ($post_id) {
        $manual = get_post_meta($post_id, '_ippgi_seo_keywords', true);
        if ('' !== trim((string) $manual)) {
            return ippgi_seo_normalize_keywords($manual);
        }
    }

    if (is_front_page()) {
        return ippgi_seo_normalize_keywords([
            'iPPGI',
            __('steel prices', 'ippgi'),
            __('PPGI prices', 'ippgi'),
            __('GI prices', 'ippgi'),
            __('GL prices', 'ippgi'),
            __('HRC prices', 'ippgi'),
            __('CRC prices', 'ippgi'),
            __('aluminum prices', 'ippgi'),
            __('China steel market', 'ippgi'),
        ]);
    }

    if ($post_id) {
        return ippgi_seo_generate_post_keywords($post_id);
    }

    $keywords = [
        'iPPGI',
        __('steel prices', 'ippgi'),
        __('China steel market', 'ippgi'),
    ];

    if (is_category() || is_tag() || is_tax()) {
        array_unshift($keywords, single_term_title('', false));
    } elseif (is_archive()) {
        array_unshift($keywords, wp_strip_all_tags(get_the_archive_title()));
    }

    return ippgi_seo_normalize_keywords($keywords);
}

/**
 * Detect a third-party SEO plugin that already owns metadata output.
 *
 * @return bool
 */
function ippgi_seo_plugin_is_active() {
    return defined('WPSEO_VERSION')
        || defined('RANK_MATH_VERSION')
        || defined('SEOPRESS_VERSION')
        || defined('AIOSEO_VERSION');
}

/**
 * Output SEO metadata in the document head.
 */
function ippgi_seo_output_meta() {
    if (is_admin() || is_feed() || is_trackback() || ippgi_seo_plugin_is_active()) {
        return;
    }

    $description = ippgi_seo_get_description();
    $keywords = ippgi_seo_get_keywords();

    if ('' !== $description) {
        echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
    }

    if ('' !== $keywords) {
        echo '<meta name="keywords" content="' . esc_attr($keywords) . '">' . "\n";
    }
}
add_action('wp_head', 'ippgi_seo_output_meta', 2);

/**
 * Mark account, payment, search, and error pages as non-indexable.
 *
 * @param array<string, bool|string> $robots Existing robots directives.
 * @return array<string, bool|string>
 */
function ippgi_seo_filter_robots($robots) {
    $private_pages = [
        'login',
        'membership-login',
        'profile',
        'edit-profile',
        'favorites',
        'invite',
        'payment',
        'checkout-result',
        'stripe-checkout-result',
        'prices',
        'price-detail',
    ];

    if (is_search() || is_404() || is_page($private_pages)) {
        $robots['noindex'] = true;
        $robots['noarchive'] = true;
        unset($robots['index']);
    }

    return $robots;
}
add_filter('wp_robots', 'ippgi_seo_filter_robots');

/**
 * Let TranslatePress SEO Pack translate the keywords content attribute too.
 *
 * @param array<string, array<string, mixed>> $node_accessors Node accessors.
 * @return array<string, array<string, mixed>>
 */
function ippgi_seo_translatepress_node_accessors($node_accessors) {
    $node_accessors['ippgi_meta_keywords'] = [
        'selector'  => 'meta[name="keywords"]',
        'accessor'  => 'content',
        'attribute' => true,
    ];

    return $node_accessors;
}
add_filter('trp_node_accessors', 'ippgi_seo_translatepress_node_accessors', 20);

/**
 * Register the per-content SEO fields.
 */
function ippgi_seo_register_meta() {
    foreach (['post', 'page'] as $post_type) {
        register_post_meta($post_type, '_ippgi_seo_description', [
            'type'              => 'string',
            'single'            => true,
            'show_in_rest'      => false,
            'sanitize_callback' => function ($value) {
                return ippgi_seo_trim_text($value);
            },
            'auth_callback'     => function ($allowed, $meta_key, $post_id) {
                return current_user_can('edit_post', $post_id);
            },
        ]);

        register_post_meta($post_type, '_ippgi_seo_keywords', [
            'type'              => 'string',
            'single'            => true,
            'show_in_rest'      => false,
            'sanitize_callback' => function ($value) {
                return ippgi_seo_normalize_keywords($value);
            },
            'auth_callback'     => function ($allowed, $meta_key, $post_id) {
                return current_user_can('edit_post', $post_id);
            },
        ]);
    }
}
add_action('init', 'ippgi_seo_register_meta');

/**
 * Add SEO fields to posts and pages.
 */
function ippgi_seo_add_meta_boxes() {
    foreach (['post', 'page'] as $post_type) {
        add_meta_box(
            'ippgi_seo_metadata',
            __('SEO Metadata', 'ippgi'),
            'ippgi_seo_meta_box_callback',
            $post_type,
            'normal',
            'default'
        );
    }
}
add_action('add_meta_boxes', 'ippgi_seo_add_meta_boxes');

/**
 * Render the SEO metadata fields.
 *
 * @param WP_Post $post Current post.
 */
function ippgi_seo_meta_box_callback($post) {
    $description = get_post_meta($post->ID, '_ippgi_seo_description', true);
    $keywords = get_post_meta($post->ID, '_ippgi_seo_keywords', true);
    $automatic_description = ippgi_seo_generate_post_description($post->ID);
    $automatic_keywords = ippgi_seo_generate_post_keywords($post->ID);

    wp_nonce_field('ippgi_save_seo_metadata', 'ippgi_seo_nonce');
    ?>
    <p>
        <label for="ippgi_seo_description"><strong><?php esc_html_e('Meta Description', 'ippgi'); ?></strong></label>
    </p>
    <textarea
        id="ippgi_seo_description"
        name="ippgi_seo_description"
        rows="3"
        maxlength="160"
        class="widefat"
        placeholder="<?php echo esc_attr($automatic_description); ?>"
    ><?php echo esc_textarea($description); ?></textarea>
    <p class="description">
        <?php esc_html_e('Optional. Leave blank to generate it automatically from the page excerpt or content. Maximum 160 characters.', 'ippgi'); ?>
    </p>

    <p>
        <label for="ippgi_seo_keywords"><strong><?php esc_html_e('Meta Keywords', 'ippgi'); ?></strong></label>
    </p>
    <input
        type="text"
        id="ippgi_seo_keywords"
        name="ippgi_seo_keywords"
        value="<?php echo esc_attr($keywords); ?>"
        class="widefat"
        placeholder="<?php echo esc_attr($automatic_keywords); ?>"
    >
    <p class="description">
        <?php esc_html_e('Optional. Separate keywords with commas. Leave blank to use the page title, article taxonomy, and site keywords.', 'ippgi'); ?>
    </p>
    <?php
}

/**
 * Save SEO metadata fields.
 *
 * @param int $post_id Post ID.
 */
function ippgi_seo_save_meta($post_id) {
    if (!isset($_POST['ippgi_seo_nonce'])) {
        return;
    }

    $nonce = sanitize_text_field(wp_unslash($_POST['ippgi_seo_nonce']));
    if (!wp_verify_nonce($nonce, 'ippgi_save_seo_metadata')) {
        return;
    }

    if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($post_id)) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $description = isset($_POST['ippgi_seo_description'])
        ? ippgi_seo_trim_text(wp_unslash($_POST['ippgi_seo_description']))
        : '';
    $keywords = isset($_POST['ippgi_seo_keywords'])
        ? ippgi_seo_normalize_keywords(wp_unslash($_POST['ippgi_seo_keywords']))
        : '';

    if ('' !== $description) {
        update_post_meta($post_id, '_ippgi_seo_description', $description);
    } else {
        delete_post_meta($post_id, '_ippgi_seo_description');
    }

    if ('' !== $keywords) {
        update_post_meta($post_id, '_ippgi_seo_keywords', $keywords);
    } else {
        delete_post_meta($post_id, '_ippgi_seo_keywords');
    }
}
add_action('save_post_post', 'ippgi_seo_save_meta');
add_action('save_post_page', 'ippgi_seo_save_meta');
