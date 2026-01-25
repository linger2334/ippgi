<?php
/**
 * Announcement System
 *
 * Custom post type for managing site announcements with visibility controls.
 *
 * @package IPPGI
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register announcement custom post type
 */
function ippgi_register_announcement_post_type() {
    $labels = [
        'name'                  => __('Announcements', 'ippgi'),
        'singular_name'         => __('Announcement', 'ippgi'),
        'menu_name'             => __('Announcements', 'ippgi'),
        'add_new'               => __('Add New', 'ippgi'),
        'add_new_item'          => __('Add New Announcement', 'ippgi'),
        'edit_item'             => __('Edit Announcement', 'ippgi'),
        'new_item'              => __('New Announcement', 'ippgi'),
        'view_item'             => __('View Announcement', 'ippgi'),
        'search_items'          => __('Search Announcements', 'ippgi'),
        'not_found'             => __('No announcements found', 'ippgi'),
        'not_found_in_trash'    => __('No announcements found in Trash', 'ippgi'),
        'all_items'             => __('All Announcements', 'ippgi'),
    ];

    $args = [
        'labels'              => $labels,
        'public'              => false,
        'publicly_queryable'  => false,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'query_var'           => false,
        'rewrite'             => false,
        'capability_type'     => 'post',
        'has_archive'         => false,
        'hierarchical'        => false,
        'menu_position'       => 25,
        'menu_icon'           => 'dashicons-megaphone',
        'supports'            => ['title', 'editor'],
        'show_in_rest'        => true,
    ];

    register_post_type('ippgi_announcement', $args);
}
add_action('init', 'ippgi_register_announcement_post_type');

/**
 * Add meta boxes for announcement settings
 */
function ippgi_announcement_meta_boxes() {
    add_meta_box(
        'ippgi_announcement_settings',
        __('Announcement Settings', 'ippgi'),
        'ippgi_announcement_settings_callback',
        'ippgi_announcement',
        'side',
        'high'
    );
}
add_action('add_meta_boxes', 'ippgi_announcement_meta_boxes');

/**
 * Render announcement settings meta box
 */
function ippgi_announcement_settings_callback($post) {
    wp_nonce_field('ippgi_announcement_settings', 'ippgi_announcement_nonce');

    $start_date   = get_post_meta($post->ID, '_ippgi_announcement_start', true);
    $end_date     = get_post_meta($post->ID, '_ippgi_announcement_end', true);
    $visibility   = get_post_meta($post->ID, '_ippgi_announcement_visibility', true) ?: 'all';
    $link_url     = get_post_meta($post->ID, '_ippgi_announcement_link', true);
    $dismissible  = get_post_meta($post->ID, '_ippgi_announcement_dismissible', true);

    // Default start date to now if empty
    if (empty($start_date)) {
        $start_date = current_time('Y-m-d\TH:i');
    }
    ?>
    <div class="ippgi-announcement-settings">
        <p>
            <label for="ippgi_announcement_start">
                <strong><?php esc_html_e('Start Date/Time', 'ippgi'); ?></strong>
            </label><br>
            <input type="datetime-local"
                   id="ippgi_announcement_start"
                   name="ippgi_announcement_start"
                   value="<?php echo esc_attr($start_date); ?>"
                   style="width: 100%;">
        </p>

        <p>
            <label for="ippgi_announcement_end">
                <strong><?php esc_html_e('End Date/Time', 'ippgi'); ?></strong>
            </label><br>
            <input type="datetime-local"
                   id="ippgi_announcement_end"
                   name="ippgi_announcement_end"
                   value="<?php echo esc_attr($end_date); ?>"
                   style="width: 100%;">
            <span class="description"><?php esc_html_e('Leave empty for no expiration.', 'ippgi'); ?></span>
        </p>

        <p>
            <label for="ippgi_announcement_visibility">
                <strong><?php esc_html_e('Visibility', 'ippgi'); ?></strong>
            </label><br>
            <select id="ippgi_announcement_visibility"
                    name="ippgi_announcement_visibility"
                    style="width: 100%;">
                <option value="all" <?php selected($visibility, 'all'); ?>>
                    <?php esc_html_e('All Users (Public)', 'ippgi'); ?>
                </option>
                <option value="logged_in" <?php selected($visibility, 'logged_in'); ?>>
                    <?php esc_html_e('Logged-in Users Only', 'ippgi'); ?>
                </option>
                <option value="subscriber" <?php selected($visibility, 'subscriber'); ?>>
                    <?php esc_html_e('Subscribers Only (Paid Members)', 'ippgi'); ?>
                </option>
            </select>
        </p>

        <p>
            <label for="ippgi_announcement_link">
                <strong><?php esc_html_e('Link URL (Optional)', 'ippgi'); ?></strong>
            </label><br>
            <input type="url"
                   id="ippgi_announcement_link"
                   name="ippgi_announcement_link"
                   value="<?php echo esc_url($link_url); ?>"
                   style="width: 100%;"
                   placeholder="https://">
        </p>

        <p>
            <label>
                <input type="checkbox"
                       name="ippgi_announcement_dismissible"
                       value="1"
                       <?php checked($dismissible, '1'); ?>>
                <?php esc_html_e('Allow users to dismiss', 'ippgi'); ?>
            </label>
        </p>
    </div>
    <?php
}

/**
 * Save announcement meta data
 */
function ippgi_save_announcement_meta($post_id) {
    // Check nonce
    if (!isset($_POST['ippgi_announcement_nonce']) ||
        !wp_verify_nonce($_POST['ippgi_announcement_nonce'], 'ippgi_announcement_settings')) {
        return;
    }

    // Check autosave
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Check permissions
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Save start date
    if (isset($_POST['ippgi_announcement_start'])) {
        update_post_meta($post_id, '_ippgi_announcement_start', sanitize_text_field($_POST['ippgi_announcement_start']));
    }

    // Save end date
    if (isset($_POST['ippgi_announcement_end'])) {
        update_post_meta($post_id, '_ippgi_announcement_end', sanitize_text_field($_POST['ippgi_announcement_end']));
    }

    // Save visibility
    if (isset($_POST['ippgi_announcement_visibility'])) {
        $visibility = sanitize_text_field($_POST['ippgi_announcement_visibility']);
        if (in_array($visibility, ['all', 'logged_in', 'subscriber'])) {
            update_post_meta($post_id, '_ippgi_announcement_visibility', $visibility);
        }
    }

    // Save link URL
    if (isset($_POST['ippgi_announcement_link'])) {
        update_post_meta($post_id, '_ippgi_announcement_link', esc_url_raw($_POST['ippgi_announcement_link']));
    }

    // Save dismissible option
    $dismissible = isset($_POST['ippgi_announcement_dismissible']) ? '1' : '0';
    update_post_meta($post_id, '_ippgi_announcement_dismissible', $dismissible);
}
add_action('save_post_ippgi_announcement', 'ippgi_save_announcement_meta');

/**
 * Add custom columns to announcement list
 */
function ippgi_announcement_columns($columns) {
    $new_columns = [];
    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;
        if ($key === 'title') {
            $new_columns['visibility'] = __('Visibility', 'ippgi');
            $new_columns['schedule']   = __('Schedule', 'ippgi');
            $new_columns['status']     = __('Status', 'ippgi');
        }
    }
    return $new_columns;
}
add_filter('manage_ippgi_announcement_posts_columns', 'ippgi_announcement_columns');

/**
 * Populate custom columns
 */
function ippgi_announcement_column_content($column, $post_id) {
    switch ($column) {
        case 'visibility':
            $visibility = get_post_meta($post_id, '_ippgi_announcement_visibility', true) ?: 'all';
            $labels = [
                'all'        => __('All Users', 'ippgi'),
                'logged_in'  => __('Logged-in', 'ippgi'),
                'subscriber' => __('Subscribers', 'ippgi'),
            ];
            echo esc_html($labels[$visibility] ?? $visibility);
            break;

        case 'schedule':
            $start = get_post_meta($post_id, '_ippgi_announcement_start', true);
            $end   = get_post_meta($post_id, '_ippgi_announcement_end', true);

            if ($start) {
                $start_formatted = wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($start));
                echo esc_html($start_formatted);
            } else {
                echo '—';
            }

            echo '<br>';

            if ($end) {
                $end_formatted = wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($end));
                echo esc_html__('Until: ', 'ippgi') . esc_html($end_formatted);
            } else {
                echo esc_html__('No expiration', 'ippgi');
            }
            break;

        case 'status':
            $is_active = ippgi_is_announcement_active($post_id);
            if ($is_active) {
                echo '<span style="color: #46b450; font-weight: bold;">● ' . esc_html__('Active', 'ippgi') . '</span>';
            } else {
                echo '<span style="color: #999;">○ ' . esc_html__('Inactive', 'ippgi') . '</span>';
            }
            break;
    }
}
add_action('manage_ippgi_announcement_posts_custom_column', 'ippgi_announcement_column_content', 10, 2);

/**
 * Check if an announcement is currently active (within date range)
 */
function ippgi_is_announcement_active($post_id) {
    $post = get_post($post_id);

    // Check if post is published
    if ($post->post_status !== 'publish') {
        return false;
    }

    $now       = current_time('timestamp');
    $start     = get_post_meta($post_id, '_ippgi_announcement_start', true);
    $end       = get_post_meta($post_id, '_ippgi_announcement_end', true);

    // Check start date
    if (!empty($start) && strtotime($start) > $now) {
        return false;
    }

    // Check end date
    if (!empty($end) && strtotime($end) < $now) {
        return false;
    }

    return true;
}

/**
 * Check if user can view announcement based on visibility settings
 */
function ippgi_can_user_view_announcement($post_id) {
    $visibility = get_post_meta($post_id, '_ippgi_announcement_visibility', true) ?: 'all';

    // Check dev mode - simulate logged in user
    $is_dev_mode = defined('IPPGI_DEV_MODE') && IPPGI_DEV_MODE;
    $dev_level = defined('IPPGI_DEV_MEMBERSHIP_LEVEL') ? IPPGI_DEV_MEMBERSHIP_LEVEL : 'plus';

    switch ($visibility) {
        case 'all':
            return true;

        case 'logged_in':
            // In dev mode, any level except 'guest' is considered logged in
            if ($is_dev_mode) {
                return $dev_level !== 'guest';
            }
            return is_user_logged_in();

        case 'subscriber':
            // In dev mode, check if level is trial or plus
            if ($is_dev_mode) {
                return in_array($dev_level, ['trial', 'plus']);
            }

            // Check if user is a paid member using Simple Membership
            if (!is_user_logged_in()) {
                return false;
            }

            // Check for Simple Membership Plugin
            if (class_exists('SwpmMemberUtils')) {
                $user_id = get_current_user_id();
                $member = SwpmMemberUtils::get_user_by_user_name(wp_get_current_user()->user_login);
                if ($member) {
                    // Get membership level - assuming Plus level (level 2) is the paid subscription
                    // Adjust this logic based on your membership level IDs
                    $membership_level = SwpmMemberUtils::get_member_field_by_id($member->member_id, 'membership_level');
                    // You can customize which levels are considered "subscribers"
                    // For now, any membership level > 1 is considered a paid subscriber
                    return intval($membership_level) > 1;
                }
            }

            // Fallback: check WordPress role
            $user = wp_get_current_user();
            return in_array('subscriber', $user->roles) ||
                   in_array('administrator', $user->roles) ||
                   in_array('editor', $user->roles);

        default:
            return true;
    }
}

/**
 * Get active announcements for current user
 *
 * @param int $limit Maximum number of announcements to return
 * @return array Array of announcement posts
 */
function ippgi_get_active_announcements($limit = 5) {
    $announcements = get_posts([
        'post_type'      => 'ippgi_announcement',
        'post_status'    => 'publish',
        'posts_per_page' => $limit * 2, // Get extra to account for filtering
        'orderby'        => 'meta_value',
        'meta_key'       => '_ippgi_announcement_start',
        'order'          => 'DESC',
    ]);

    $active = [];
    foreach ($announcements as $announcement) {
        // Check if within date range
        if (!ippgi_is_announcement_active($announcement->ID)) {
            continue;
        }

        // Check visibility permissions
        if (!ippgi_can_user_view_announcement($announcement->ID)) {
            continue;
        }

        // Check if user has dismissed this announcement
        $dismissible = get_post_meta($announcement->ID, '_ippgi_announcement_dismissible', true);
        if ($dismissible === '1') {
            $announcement_hash = md5($announcement->ID . $announcement->post_modified);
            if (isset($_COOKIE['ippgi_dismissed_' . $announcement->ID]) &&
                $_COOKIE['ippgi_dismissed_' . $announcement->ID] === $announcement_hash) {
                continue;
            }
        }

        $active[] = $announcement;

        if (count($active) >= $limit) {
            break;
        }
    }

    return $active;
}

/**
 * Get announcement data with all meta
 */
function ippgi_get_announcement_data($post_id) {
    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'ippgi_announcement') {
        return null;
    }

    return [
        'id'          => $post->ID,
        'title'       => $post->post_title,
        'content'     => $post->post_content,
        'start'       => get_post_meta($post_id, '_ippgi_announcement_start', true),
        'end'         => get_post_meta($post_id, '_ippgi_announcement_end', true),
        'visibility'  => get_post_meta($post_id, '_ippgi_announcement_visibility', true) ?: 'all',
        'link'        => get_post_meta($post_id, '_ippgi_announcement_link', true),
        'dismissible' => get_post_meta($post_id, '_ippgi_announcement_dismissible', true) === '1',
        'hash'        => md5($post->ID . $post->post_modified),
    ];
}

/**
 * Add body class when announcement is active
 */
function ippgi_announcement_body_class($classes) {
    // Only add class on homepage
    if (!is_front_page()) {
        return $classes;
    }

    $announcements = ippgi_get_active_announcements(1);
    if (!empty($announcements)) {
        $classes[] = 'has-announcement';
    }
    return $classes;
}
add_filter('body_class', 'ippgi_announcement_body_class');
