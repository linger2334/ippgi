<?php
/**
 * Membership Functions
 * Simple Membership Plugin Integration
 *
 * SWPM Membership Levels:
 * - Level 2 = Basic (免费注册用户)
 * - Level 3 = Trial (试用会员)
 * - Level 4 = Plus (付费高级会员)
 * - Guest = 未登录用户 (不在SWPM中配置)
 *
 * @package IPPGI
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Check if Simple Membership plugin is active
 */
function ippgi_is_swpm_active() {
    return class_exists('SimpleWpMembership');
}

/**
 * Get current user's membership level
 */
function ippgi_get_user_membership_level($user_id = null) {
    // Development mode: return simulated membership level
    if (defined('IPPGI_DEV_MODE') && IPPGI_DEV_MODE) {
        return defined('IPPGI_DEV_MEMBERSHIP_LEVEL') ? IPPGI_DEV_MEMBERSHIP_LEVEL : 'plus';
    }

    if (!$user_id) {
        $user_id = get_current_user_id();
    }

    if (!$user_id) {
        return 'guest';
    }

    // Check if Simple Membership is active
    if (ippgi_is_swpm_active() && class_exists('SwpmMemberUtils')) {
        $member = SwpmMemberUtils::get_user_by_user_name(get_user_by('id', $user_id)->user_login);
        if ($member) {
            return $member->membership_level;
        }
    }

    // Fallback to user meta
    $level = get_user_meta($user_id, 'ippgi_membership_level', true);

    return $level ?: 'basic';
}

/**
 * Check if user has Plus membership (Level 4)
 */
function ippgi_user_has_plus($user_id = null) {
    $level = ippgi_get_user_membership_level($user_id);

    // SWPM Level 4 = Plus membership
    $plus_levels = ['plus', '4', 4];

    return in_array($level, $plus_levels, true);
}

/**
 * Check if user has Trial membership (Level 3)
 */
function ippgi_user_has_trial($user_id = null) {
    $level = ippgi_get_user_membership_level($user_id);

    // SWPM Level 3 = Trial membership
    $trial_levels = ['trial', '3', 3];

    return in_array($level, $trial_levels, true);
}

/**
 * Check if user has used their free trial
 */
function ippgi_user_has_used_trial($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }

    if (!$user_id) {
        return false;
    }

    return (bool) get_user_meta($user_id, 'ippgi_trial_used', true);
}

/**
 * Check if user can view historical price data
 */
function ippgi_user_can_view_history($user_id = null) {
    // Plus and Trial members can view history
    return ippgi_user_has_plus($user_id) || ippgi_user_has_trial($user_id);
}

/**
 * Check if content should be protected
 */
function ippgi_is_content_protected($content_type = 'history') {
    switch ($content_type) {
        case 'history':
            return !ippgi_user_can_view_history();
        case 'export':
            return !ippgi_user_has_plus();
        default:
            return !is_user_logged_in();
    }
}

/**
 * Protect content shortcode
 * Usage: [ippgi_protected level="plus"]Premium content here[/ippgi_protected]
 */
function ippgi_protected_content_shortcode($atts, $content = null) {
    $atts = shortcode_atts([
        'level'   => 'plus',
        'message' => '',
    ], $atts, 'ippgi_protected');

    $can_view = false;

    switch ($atts['level']) {
        case 'plus':
            $can_view = ippgi_user_has_plus();
            break;
        case 'trial':
            $can_view = ippgi_user_has_trial() || ippgi_user_has_plus();
            break;
        case 'member':
            $can_view = is_user_logged_in();
            break;
        default:
            $can_view = ippgi_user_has_plus();
    }

    if ($can_view) {
        return do_shortcode($content);
    }

    // Return upgrade prompt
    $message = $atts['message'] ?: __('This content is available to Plus members only.', 'ippgi');

    ob_start();
    ?>
    <div class="protected-content-notice">
        <p><?php echo esc_html($message); ?></p>
        <a href="<?php echo esc_url(home_url('/subscribe')); ?>" class="btn btn--primary btn--sm">
            <?php esc_html_e('Upgrade to Plus', 'ippgi'); ?>
        </a>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('ippgi_protected', 'ippgi_protected_content_shortcode');

/**
 * Handle membership level change
 */
function ippgi_on_membership_level_change($member_id, $old_level, $new_level) {
    // Log the change
    error_log(sprintf('IPPGI: Member %d changed from level %s to %s', $member_id, $old_level, $new_level));

    // If upgraded to Plus, send welcome email
    $plus_levels = ['plus', 'plus_monthly', 'plus_yearly', '2'];
    if (in_array($new_level, $plus_levels, true) && !in_array($old_level, $plus_levels, true)) {
        ippgi_send_plus_welcome_email($member_id);
    }
}

/**
 * Send Plus welcome email
 */
function ippgi_send_plus_welcome_email($member_id) {
    // This will be implemented with actual email functionality
    // For now, just log it
    error_log(sprintf('IPPGI: Should send Plus welcome email to member %d', $member_id));
}

/**
 * Register SWPM hooks when plugin is active
 */
function ippgi_register_swpm_hooks() {
    if (!ippgi_is_swpm_active()) {
        return;
    }

    // Hook into membership level change
    add_action('swpm_membership_level_changed', 'ippgi_on_membership_level_change', 10, 3);

    // Hook into registration complete
    add_action('swpm_registration_complete', 'ippgi_on_swpm_registration', 10, 1);
}
add_action('init', 'ippgi_register_swpm_hooks');

/**
 * Handle new SWPM registration
 */
function ippgi_on_swpm_registration($member_data) {
    // Check if referred
    if (isset($_COOKIE['ippgi_referral'])) {
        $referral_code = sanitize_text_field($_COOKIE['ippgi_referral']);
        ippgi_process_referral($referral_code, $member_data);
    }
}

/**
 * Process referral
 */
function ippgi_process_referral($referral_code, $member_data) {
    global $wpdb;

    // Find user with this referral code
    $referrer = $wpdb->get_var($wpdb->prepare(
        "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'ippgi_invite_code' AND meta_value = %s",
        $referral_code
    ));

    if ($referrer) {
        // Increment referral count
        $current_count = (int) get_user_meta($referrer, 'ippgi_referral_count', true);
        update_user_meta($referrer, 'ippgi_referral_count', $current_count + 1);

        // Store who referred the new user
        if (isset($member_data['user_name'])) {
            $new_user = get_user_by('login', $member_data['user_name']);
            if ($new_user) {
                update_user_meta($new_user->ID, 'ippgi_referred_by', $referrer);
            }
        }

        // Award 3 days of Plus membership to the referrer
        ippgi_award_referral_bonus($referrer, 3);

        // Log the referral
        error_log(sprintf('IPPGI: Referral processed. Referrer ID: %d, New user: %s', $referrer, $member_data['user_name'] ?? 'unknown'));
    }
}

/**
 * Save referral code in cookie
 */
function ippgi_save_referral_cookie() {
    if (isset($_GET['ref']) && !isset($_COOKIE['ippgi_referral'])) {
        $referral_code = sanitize_text_field($_GET['ref']);
        setcookie('ippgi_referral', $referral_code, time() + (30 * DAY_IN_SECONDS), '/');
    }
}
add_action('init', 'ippgi_save_referral_cookie');

/**
 * Award referral bonus - extend Plus membership by specified days
 *
 * @param int $user_id The WordPress user ID of the referrer
 * @param int $bonus_days Number of days to add (default: 3)
 * @return bool True on success, false on failure
 */
function ippgi_award_referral_bonus($user_id, $bonus_days = 3) {
    if (!ippgi_is_swpm_active() || !class_exists('SwpmMemberUtils')) {
        error_log('IPPGI: Cannot award referral bonus - SWPM not active');
        return false;
    }

    // Get the WP user
    $wp_user = get_user_by('id', $user_id);
    if (!$wp_user) {
        error_log(sprintf('IPPGI: Cannot award referral bonus - WP user %d not found', $user_id));
        return false;
    }

    // Get the SWPM member record
    $swpm_member = SwpmMemberUtils::get_user_by_user_name($wp_user->user_login);
    if (!$swpm_member) {
        error_log(sprintf('IPPGI: Cannot award referral bonus - SWPM member not found for user %s', $wp_user->user_login));
        return false;
    }

    $member_id = $swpm_member->member_id;
    $current_level = $swpm_member->membership_level;
    $subscription_starts = $swpm_member->subscription_starts;

    // Define Plus level IDs (configure these based on your SWPM setup)
    // Level 4 = Plus membership in SWPM
    $plus_level_id = apply_filters('ippgi_plus_membership_level_id', 4);

    // Check if user already has Plus membership
    if (ippgi_user_has_plus($user_id)) {
        // User has Plus - extend their subscription
        $new_start_date = ippgi_extend_subscription_date($subscription_starts, $bonus_days);

        global $wpdb;
        $result = $wpdb->update(
            $wpdb->prefix . 'swpm_members_tbl',
            ['subscription_starts' => $new_start_date],
            ['member_id' => $member_id],
            ['%s'],
            ['%d']
        );

        if ($result !== false) {
            // Track the bonus
            ippgi_track_referral_bonus($user_id, $bonus_days, 'extended');
            error_log(sprintf('IPPGI: Extended Plus membership for user %d by %d days. New start date: %s', $user_id, $bonus_days, $new_start_date));
            return true;
        }
    } else {
        // User doesn't have Plus - give them a temporary Plus upgrade
        // Store their original level so we can restore it later if needed
        update_user_meta($user_id, 'ippgi_original_membership_level', $current_level);

        // Set subscription start to today
        $today = date('Y-m-d');

        global $wpdb;
        $result = $wpdb->update(
            $wpdb->prefix . 'swpm_members_tbl',
            [
                'membership_level' => $plus_level_id,
                'subscription_starts' => $today,
            ],
            ['member_id' => $member_id],
            ['%s', '%s'],
            ['%d']
        );

        if ($result !== false) {
            // Schedule the downgrade after bonus days
            $downgrade_time = strtotime("+{$bonus_days} days");
            wp_schedule_single_event($downgrade_time, 'ippgi_referral_bonus_expired', [$user_id, $current_level]);

            // Track the bonus
            ippgi_track_referral_bonus($user_id, $bonus_days, 'upgraded');
            error_log(sprintf('IPPGI: Upgraded user %d to Plus for %d days', $user_id, $bonus_days));
            return true;
        }
    }

    return false;
}

/**
 * Extend subscription start date by specified days
 *
 * @param string $current_start Current subscription start date (Y-m-d)
 * @param int $days Number of days to add
 * @return string New subscription start date
 */
function ippgi_extend_subscription_date($current_start, $days) {
    $start_timestamp = strtotime($current_start);
    $new_timestamp = $start_timestamp - ($days * DAY_IN_SECONDS); // Subtract to extend expiry
    return date('Y-m-d', $new_timestamp);
}

/**
 * Track referral bonus for user
 *
 * @param int $user_id User ID
 * @param int $days Bonus days awarded
 * @param string $type Type of bonus ('extended' or 'upgraded')
 */
function ippgi_track_referral_bonus($user_id, $days, $type) {
    $bonuses = get_user_meta($user_id, 'ippgi_referral_bonuses', true);
    if (!is_array($bonuses)) {
        $bonuses = [];
    }

    $bonuses[] = [
        'days' => $days,
        'type' => $type,
        'date' => current_time('mysql'),
    ];

    update_user_meta($user_id, 'ippgi_referral_bonuses', $bonuses);

    // Update total bonus days
    $total_bonus = (int) get_user_meta($user_id, 'ippgi_total_referral_bonus_days', true);
    update_user_meta($user_id, 'ippgi_total_referral_bonus_days', $total_bonus + $days);

    // Increment converted referrals count
    $converted = (int) get_user_meta($user_id, 'ippgi_converted_referrals', true);
    update_user_meta($user_id, 'ippgi_converted_referrals', $converted + 1);
}

/**
 * Handle referral bonus expiration - downgrade user back to original level
 *
 * @param int $user_id User ID
 * @param int $original_level Original membership level ID
 */
function ippgi_handle_referral_bonus_expired($user_id, $original_level) {
    if (!ippgi_is_swpm_active() || !class_exists('SwpmMemberUtils')) {
        return;
    }

    $wp_user = get_user_by('id', $user_id);
    if (!$wp_user) {
        return;
    }

    $swpm_member = SwpmMemberUtils::get_user_by_user_name($wp_user->user_login);
    if (!$swpm_member) {
        return;
    }

    // Only downgrade if they haven't purchased Plus in the meantime
    // Check if they still have the bonus level and haven't made a payment
    $has_paid = get_user_meta($user_id, 'ippgi_has_paid_subscription', true);
    if ($has_paid) {
        error_log(sprintf('IPPGI: User %d has paid subscription, not downgrading', $user_id));
        return;
    }

    global $wpdb;
    $wpdb->update(
        $wpdb->prefix . 'swpm_members_tbl',
        ['membership_level' => $original_level],
        ['member_id' => $swpm_member->member_id],
        ['%d'],
        ['%d']
    );

    // Clean up
    delete_user_meta($user_id, 'ippgi_original_membership_level');

    error_log(sprintf('IPPGI: Referral bonus expired for user %d, downgraded to level %d', $user_id, $original_level));
}
add_action('ippgi_referral_bonus_expired', 'ippgi_handle_referral_bonus_expired', 10, 2);

/**
 * Get user's total referral bonus days
 *
 * @param int $user_id User ID
 * @return int Total bonus days earned
 */
function ippgi_get_user_total_bonus_days($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    return (int) get_user_meta($user_id, 'ippgi_total_referral_bonus_days', true);
}

/**
 * Get user's subscription status
 * Returns one of: 'trial', 'active', 'cancelled', 'terminated'
 *
 * @param int $user_id User ID
 * @return string Subscription status
 */
function ippgi_get_subscription_status($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }

    if (!$user_id) {
        return 'terminated';
    }

    // Development mode
    if (defined('IPPGI_DEV_MODE') && IPPGI_DEV_MODE) {
        $dev_level = defined('IPPGI_DEV_MEMBERSHIP_LEVEL') ? IPPGI_DEV_MEMBERSHIP_LEVEL : 'plus';
        if ($dev_level === 'trial') {
            return 'trial';
        } elseif ($dev_level === 'plus') {
            return 'active';
        } elseif ($dev_level === 'cancelled') {
            return 'cancelled';
        }
        return 'terminated';
    }

    // Check Trial status (Level 3)
    if (ippgi_user_has_trial($user_id)) {
        return 'trial';
    }

    // Check Plus status (Level 4)
    if (ippgi_user_has_plus($user_id)) {
        // Check if subscription is cancelled
        $is_cancelled = ippgi_is_subscription_cancelled($user_id);
        return $is_cancelled ? 'cancelled' : 'active';
    }

    // No active subscription
    return 'terminated';
}

/**
 * Check if user's subscription is cancelled (but not yet expired)
 *
 * @param int $user_id User ID
 * @return bool True if cancelled
 */
function ippgi_is_subscription_cancelled($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }

    // Check user meta for cancellation flag
    $is_cancelled = get_user_meta($user_id, 'ippgi_subscription_cancelled', true);

    // Also check SWPM if available
    if (ippgi_is_swpm_active() && class_exists('SwpmMemberUtils')) {
        $wp_user = get_user_by('id', $user_id);
        if ($wp_user) {
            $swpm_member = SwpmMemberUtils::get_user_by_user_name($wp_user->user_login);
            if ($swpm_member && isset($swpm_member->account_state)) {
                // Check if account state indicates cancellation
                if ($swpm_member->account_state === 'inactive' || $swpm_member->account_state === 'pending') {
                    return true;
                }
            }
        }
    }

    return (bool) $is_cancelled;
}

/**
 * Get subscription end date formatted
 *
 * @param int $user_id User ID
 * @return string Formatted date or empty string
 */
function ippgi_get_formatted_subscription_end_date($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }

    // Development mode - return a sample date
    if (defined('IPPGI_DEV_MODE') && IPPGI_DEV_MODE) {
        return date('F j, Y', strtotime('+30 days'));
    }

    if (ippgi_is_swpm_active() && class_exists('SwpmMemberUtils')) {
        $wp_user = get_user_by('id', $user_id);
        if ($wp_user) {
            $swpm_member = SwpmMemberUtils::get_user_by_user_name($wp_user->user_login);
            if ($swpm_member && !empty($swpm_member->subscription_starts)) {
                // Calculate end date based on membership level duration
                $start_date = $swpm_member->subscription_starts;
                // Default to 1 year subscription for Plus, 7 days for Trial
                $duration = ippgi_user_has_trial($user_id) ? '+7 days' : '+1 year';
                return date('F j, Y', strtotime($start_date . ' ' . $duration));
            }
        }
    }

    return '';
}

/**
 * Add membership info to admin user list
 */
function ippgi_add_user_membership_column($columns) {
    $columns['ippgi_membership'] = __('Membership', 'ippgi');
    return $columns;
}
add_filter('manage_users_columns', 'ippgi_add_user_membership_column');

/**
 * Display membership info in admin user list
 */
function ippgi_show_user_membership_column($value, $column_name, $user_id) {
    if ($column_name === 'ippgi_membership') {
        $level = ippgi_get_user_membership_level($user_id);
        return ucfirst($level);
    }
    return $value;
}
add_filter('manage_users_custom_column', 'ippgi_show_user_membership_column', 10, 3);

/**
 * AJAX handler for toggling favorites
 */
function ippgi_ajax_toggle_favorite() {
    check_ajax_referer('ippgi_nonce', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => __('Please log in to save favorites.', 'ippgi')]);
    }

    $price_id = isset($_POST['price_id']) ? sanitize_text_field($_POST['price_id']) : '';

    if (empty($price_id)) {
        wp_send_json_error(['message' => __('Invalid price ID.', 'ippgi')]);
    }

    $user_id   = get_current_user_id();
    $favorites = get_user_meta($user_id, 'ippgi_favorites', true);

    if (!is_array($favorites)) {
        $favorites = [];
    }

    $is_favorite = in_array($price_id, $favorites, true);

    if ($is_favorite) {
        // Remove from favorites
        $favorites = array_diff($favorites, [$price_id]);
        $action    = 'removed';
    } else {
        // Add to favorites
        $favorites[] = $price_id;
        $action      = 'added';
    }

    update_user_meta($user_id, 'ippgi_favorites', array_values($favorites));

    wp_send_json_success([
        'action'  => $action,
        'message' => $action === 'added'
            ? __('Added to favorites', 'ippgi')
            : __('Removed from favorites', 'ippgi'),
    ]);
}
add_action('wp_ajax_ippgi_toggle_favorite', 'ippgi_ajax_toggle_favorite');

/**
 * AJAX handler for cancelling subscription
 */
function ippgi_ajax_cancel_subscription() {
    check_ajax_referer('ippgi_cancel_subscription', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => __('Please log in to cancel subscription.', 'ippgi')]);
    }

    $user_id = get_current_user_id();

    // Set cancellation flag
    update_user_meta($user_id, 'ippgi_subscription_cancelled', true);
    update_user_meta($user_id, 'ippgi_subscription_cancelled_date', current_time('mysql'));

    // Log the cancellation
    error_log(sprintf('IPPGI: User %d cancelled their subscription', $user_id));

    wp_send_json_success([
        'message' => __('Your subscription has been cancelled.', 'ippgi'),
    ]);
}
add_action('wp_ajax_ippgi_cancel_subscription', 'ippgi_ajax_cancel_subscription');

/**
 * Add Simple Membership settings notice
 */
function ippgi_admin_notices() {
    if (!ippgi_is_swpm_active()) {
        $screen = get_current_screen();
        if ($screen && $screen->id === 'themes') {
            ?>
            <div class="notice notice-warning">
                <p>
                    <strong><?php esc_html_e('IPPGI Theme:', 'ippgi'); ?></strong>
                    <?php
                    printf(
                        /* translators: %s: plugin name */
                        esc_html__('For full membership functionality, please install and activate the %s plugin.', 'ippgi'),
                        '<a href="' . esc_url(admin_url('plugin-install.php?s=simple+membership&tab=search&type=term')) . '">Simple Membership</a>'
                    );
                    ?>
                </p>
            </div>
            <?php
        }
    }
}
add_action('admin_notices', 'ippgi_admin_notices');
