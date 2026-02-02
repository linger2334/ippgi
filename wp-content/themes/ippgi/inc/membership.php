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
 * Check if user has Plus membership (Level 4) or active bonus access
 */
function ippgi_user_has_plus($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }

    $level = ippgi_get_user_membership_level($user_id);

    // SWPM Level 4 = Plus membership
    $plus_levels = ['plus', '4', 4];

    if (in_array($level, $plus_levels, true)) {
        return true;
    }

    // Check for active bonus access
    $bonus_active = get_user_meta($user_id, 'ippgi_bonus_access_active', true);
    if ($bonus_active) {
        $end_date = get_user_meta($user_id, 'ippgi_bonus_access_end', true);
        if (!empty($end_date) && strtotime($end_date) > time()) {
            return true;
        }
    }

    return false;
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

    // Get WP user ID from SWPM member
    global $wpdb;
    $member = $wpdb->get_row($wpdb->prepare(
        "SELECT user_name FROM {$wpdb->prefix}swpm_members_tbl WHERE member_id = %d",
        $member_id
    ));

    if (!$member) {
        return;
    }

    $wp_user = get_user_by('login', $member->user_name);
    if (!$wp_user) {
        return;
    }

    $user_id = $wp_user->ID;

    // Plus level is 4
    $plus_levels = ['4', 4];

    // If upgraded to Plus, send welcome email and clear cancellation flag
    if (in_array($new_level, $plus_levels, true) && !in_array($old_level, $plus_levels, true)) {
        ippgi_send_plus_welcome_email($member_id);
        delete_user_meta($user_id, 'ippgi_subscription_cancelled');
        delete_user_meta($user_id, 'ippgi_subscription_cancelled_date');
    }

    // If downgraded from Plus (subscription expired), activate bonus days if available
    if (in_array($old_level, $plus_levels, true) && !in_array($new_level, $plus_levels, true)) {
        $bonus_days = ippgi_get_unused_bonus_days($user_id);
        if ($bonus_days > 0) {
            error_log(sprintf('IPPGI: User %d downgraded from Plus, activating %d bonus days', $user_id, $bonus_days));
            ippgi_activate_bonus_access($user_id);
        }
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
 * Award referral bonus - accumulate bonus days for user
 *
 * For subscription-based payments (PayPal/Stripe), we can't modify the billing dates.
 * Instead, we accumulate bonus days that will be used AFTER the subscription ends/cancels.
 *
 * How it works:
 * - Active subscription: Bonus days accumulate but don't affect billing
 * - Active bonus access: Extend the current bonus period
 * - Cancelled/expired subscription: Bonus days extend access beyond subscription end
 * - Non-subscriber: Bonus days give temporary Plus access
 *
 * @param int $user_id The WordPress user ID of the referrer
 * @param int $bonus_days Number of days to add (default: 3)
 * @return bool True on success, false on failure
 */
function ippgi_award_referral_bonus($user_id, $bonus_days = 3) {
    $wp_user = get_user_by('id', $user_id);
    if (!$wp_user) {
        error_log(sprintf('IPPGI: Cannot award referral bonus - WP user %d not found', $user_id));
        return false;
    }

    // Track the bonus award
    ippgi_track_referral_bonus($user_id, $bonus_days, 'accumulated');

    // Check current state
    $has_active_subscription = ippgi_has_active_subscription($user_id);
    $bonus_active = get_user_meta($user_id, 'ippgi_bonus_access_active', true);

    if ($has_active_subscription) {
        // User has active subscription - just accumulate bonus days for later use
        $current_bonus = (int) get_user_meta($user_id, 'ippgi_unused_bonus_days', true);
        $new_bonus = $current_bonus + $bonus_days;
        update_user_meta($user_id, 'ippgi_unused_bonus_days', $new_bonus);
        error_log(sprintf('IPPGI: Awarded %d bonus days to user %d (accumulated). Total unused: %d days', $bonus_days, $user_id, $new_bonus));
    } elseif ($bonus_active) {
        // User already has bonus access - extend the current bonus period
        $current_end = get_user_meta($user_id, 'ippgi_bonus_access_end', true);
        $new_end = date('Y-m-d H:i:s', strtotime($current_end . " +{$bonus_days} days"));
        update_user_meta($user_id, 'ippgi_bonus_access_end', $new_end);

        // Reschedule expiration check
        wp_clear_scheduled_hook('ippgi_check_bonus_access_expired', [$user_id]);
        wp_schedule_single_event(strtotime($new_end), 'ippgi_check_bonus_access_expired', [$user_id]);

        error_log(sprintf('IPPGI: Extended bonus access for user %d by %d days. New end: %s', $user_id, $bonus_days, $new_end));
    } else {
        // User has no active subscription and no bonus access - activate immediately
        $current_bonus = (int) get_user_meta($user_id, 'ippgi_unused_bonus_days', true);
        update_user_meta($user_id, 'ippgi_unused_bonus_days', $current_bonus + $bonus_days);
        ippgi_activate_bonus_access($user_id);
        error_log(sprintf('IPPGI: Activated %d bonus days for user %d', $bonus_days, $user_id));
    }

    return true;
}

/**
 * Check if user has an active PayPal/Stripe subscription
 *
 * @param int $user_id User ID
 * @return bool True if has active subscription
 */
function ippgi_has_active_subscription($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }

    if (!ippgi_is_swpm_active() || !class_exists('SwpmMemberUtils')) {
        return false;
    }

    $wp_user = get_user_by('id', $user_id);
    if (!$wp_user) {
        return false;
    }

    $swpm_member = SwpmMemberUtils::get_user_by_user_name($wp_user->user_login);
    if (!$swpm_member) {
        return false;
    }

    // Check if has subscription ID and is Plus level
    if (empty($swpm_member->subscr_id) || $swpm_member->membership_level != 4) {
        return false;
    }

    // Check if subscription is not cancelled
    $is_cancelled = get_user_meta($user_id, 'ippgi_subscription_cancelled', true);
    if ($is_cancelled) {
        return false;
    }

    return true;
}

/**
 * Activate bonus access for user (upgrade to Plus temporarily using bonus days)
 *
 * @param int $user_id User ID
 * @return bool True on success
 */
function ippgi_activate_bonus_access($user_id) {
    $bonus_days = (int) get_user_meta($user_id, 'ippgi_unused_bonus_days', true);
    if ($bonus_days <= 0) {
        return false;
    }

    // Set bonus access start and end dates
    $start_date = current_time('mysql');
    $end_date = date('Y-m-d H:i:s', strtotime("+{$bonus_days} days"));

    update_user_meta($user_id, 'ippgi_bonus_access_start', $start_date);
    update_user_meta($user_id, 'ippgi_bonus_access_end', $end_date);
    update_user_meta($user_id, 'ippgi_bonus_access_active', true);

    // Clear unused bonus days (they're now being used)
    update_user_meta($user_id, 'ippgi_unused_bonus_days', 0);

    // Upgrade to Plus level in SWPM
    if (ippgi_is_swpm_active() && class_exists('SwpmMemberUtils')) {
        $wp_user = get_user_by('id', $user_id);
        if ($wp_user) {
            $swpm_member = SwpmMemberUtils::get_user_by_user_name($wp_user->user_login);
            if ($swpm_member) {
                // Store original level for potential restoration
                update_user_meta($user_id, 'ippgi_original_membership_level', $swpm_member->membership_level);

                global $wpdb;
                $wpdb->update(
                    $wpdb->prefix . 'swpm_members_tbl',
                    ['membership_level' => 4], // Plus level
                    ['member_id' => $swpm_member->member_id],
                    ['%d'],
                    ['%d']
                );
            }
        }
    }

    // Schedule access expiration check
    wp_schedule_single_event(strtotime($end_date), 'ippgi_check_bonus_access_expired', [$user_id]);

    error_log(sprintf('IPPGI: Activated bonus access for user %d until %s', $user_id, $end_date));
    return true;
}

/**
 * Check and handle bonus access expiration
 *
 * @param int $user_id User ID
 */
function ippgi_check_bonus_access_expired($user_id) {
    $is_active = get_user_meta($user_id, 'ippgi_bonus_access_active', true);
    if (!$is_active) {
        return;
    }

    $end_date = get_user_meta($user_id, 'ippgi_bonus_access_end', true);
    if (empty($end_date) || strtotime($end_date) > time()) {
        return; // Not expired yet
    }

    // Check if user now has active subscription (they might have subscribed during bonus period)
    if (ippgi_has_active_subscription($user_id)) {
        // Clear bonus access flags but don't downgrade
        delete_user_meta($user_id, 'ippgi_bonus_access_active');
        delete_user_meta($user_id, 'ippgi_bonus_access_start');
        delete_user_meta($user_id, 'ippgi_bonus_access_end');
        error_log(sprintf('IPPGI: User %d subscribed during bonus period, clearing bonus flags', $user_id));
        return;
    }

    // Check if there are new accumulated bonus days (from referrals during bonus period)
    $new_bonus_days = (int) get_user_meta($user_id, 'ippgi_unused_bonus_days', true);
    if ($new_bonus_days > 0) {
        // Extend bonus access with new days
        $new_end = date('Y-m-d H:i:s', strtotime("+{$new_bonus_days} days"));
        update_user_meta($user_id, 'ippgi_bonus_access_start', current_time('mysql'));
        update_user_meta($user_id, 'ippgi_bonus_access_end', $new_end);
        update_user_meta($user_id, 'ippgi_unused_bonus_days', 0);

        // Schedule next expiration check
        wp_schedule_single_event(strtotime($new_end), 'ippgi_check_bonus_access_expired', [$user_id]);

        error_log(sprintf('IPPGI: User %d bonus extended with %d accumulated days until %s', $user_id, $new_bonus_days, $new_end));
        return;
    }

    // No subscription, no new bonus days - downgrade user to original level
    $original_level = get_user_meta($user_id, 'ippgi_original_membership_level', true);
    if (empty($original_level)) {
        $original_level = 2; // Default to Basic
    }

    if (ippgi_is_swpm_active() && class_exists('SwpmMemberUtils')) {
        $wp_user = get_user_by('id', $user_id);
        if ($wp_user) {
            $swpm_member = SwpmMemberUtils::get_user_by_user_name($wp_user->user_login);
            if ($swpm_member) {
                global $wpdb;
                $wpdb->update(
                    $wpdb->prefix . 'swpm_members_tbl',
                    ['membership_level' => $original_level],
                    ['member_id' => $swpm_member->member_id],
                    ['%d'],
                    ['%d']
                );
            }
        }
    }

    // Clear bonus access flags
    delete_user_meta($user_id, 'ippgi_bonus_access_active');
    delete_user_meta($user_id, 'ippgi_bonus_access_start');
    delete_user_meta($user_id, 'ippgi_bonus_access_end');
    delete_user_meta($user_id, 'ippgi_original_membership_level');

    error_log(sprintf('IPPGI: Bonus access expired for user %d, downgraded to level %d', $user_id, $original_level));
}
add_action('ippgi_check_bonus_access_expired', 'ippgi_check_bonus_access_expired');

/**
 * Get user's unused bonus days
 *
 * @param int $user_id User ID
 * @return int Number of unused bonus days
 */
function ippgi_get_unused_bonus_days($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    return (int) get_user_meta($user_id, 'ippgi_unused_bonus_days', true);
}

/**
 * Get user's bonus access end date (if currently using bonus access)
 *
 * @param int $user_id User ID
 * @return string|null Formatted date or null
 */
function ippgi_get_bonus_access_end_date($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }

    $is_active = get_user_meta($user_id, 'ippgi_bonus_access_active', true);
    if (!$is_active) {
        return null;
    }

    $end_date = get_user_meta($user_id, 'ippgi_bonus_access_end', true);
    if (empty($end_date)) {
        return null;
    }

    return date('F j, Y', strtotime($end_date));
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
 * Get user's invitation history (list of referred users)
 *
 * @param int $user_id User ID
 * @return array Array of referred users with timestamp and masked email
 */
function ippgi_get_invitation_history($user_id = null) {
    global $wpdb;

    if (!$user_id) {
        $user_id = get_current_user_id();
    }

    if (!$user_id) {
        return [];
    }

    // Get users who were referred by this user
    $referred_users = $wpdb->get_results($wpdb->prepare(
        "SELECT u.ID, u.user_email, u.user_registered
         FROM {$wpdb->users} u
         INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
         WHERE um.meta_key = 'ippgi_referred_by' AND um.meta_value = %d
         ORDER BY u.user_registered DESC",
        $user_id
    ));

    $history = [];
    foreach ($referred_users as $user) {
        $history[] = [
            'timestamp' => date('M d, Y', strtotime($user->user_registered)),
            'email' => ippgi_mask_email($user->user_email),
        ];
    }

    return $history;
}

/**
 * Mask email address for privacy
 *
 * @param string $email Email address
 * @return string Masked email (e.g., "jo***@example.com")
 */
function ippgi_mask_email($email) {
    $parts = explode('@', $email);
    if (count($parts) !== 2) {
        return '***@***.***';
    }

    $name = $parts[0];
    $domain = $parts[1];

    // Show first 2 characters, mask the rest
    if (strlen($name) <= 2) {
        $masked_name = $name . '***';
    } else {
        $masked_name = substr($name, 0, 2) . '***';
    }

    return $masked_name . '@' . $domain;
}

/**
 * Get user's subscription status
 * Returns one of: 'trial', 'active', 'cancelled', 'bonus', 'terminated'
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

    // Check for bonus access first
    $bonus_active = get_user_meta($user_id, 'ippgi_bonus_access_active', true);
    if ($bonus_active) {
        $end_date = get_user_meta($user_id, 'ippgi_bonus_access_end', true);
        if (!empty($end_date) && strtotime($end_date) > time()) {
            return 'bonus';
        }
    }

    // Check for active subscription
    if (ippgi_has_active_subscription($user_id)) {
        return 'active';
    }

    // Check Plus status without active subscription (cancelled but not expired)
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
            if ($swpm_member && !empty($swpm_member->subscr_id)) {
                // Try to get next billing date from PayPal/Stripe API
                $next_billing_date = ippgi_get_subscription_next_billing_date($swpm_member->subscr_id, $swpm_member->member_id);
                if ($next_billing_date) {
                    return $next_billing_date;
                }
            }
        }
    }

    return '';
}

/**
 * Get next billing date from PayPal or Stripe API
 *
 * @param string $subscr_id Subscription ID
 * @param int $member_id SWPM Member ID
 * @return string|null Formatted date or null
 */
function ippgi_get_subscription_next_billing_date($subscr_id, $member_id) {
    if (empty($subscr_id)) {
        return null;
    }

    // Check cache first (cache for 1 hour)
    $cache_key = 'ippgi_next_billing_' . md5($subscr_id);
    $cached = get_transient($cache_key);
    if ($cached !== false) {
        return $cached;
    }

    // Determine if PayPal or Stripe subscription
    // PayPal subscription IDs start with "I-"
    // Stripe subscription IDs start with "sub_"
    if (strpos($subscr_id, 'I-') === 0) {
        $next_date = ippgi_get_paypal_next_billing_date($subscr_id);
    } elseif (strpos($subscr_id, 'sub_') === 0) {
        $next_date = ippgi_get_stripe_next_billing_date($subscr_id);
    } else {
        // Unknown subscription type, try to get from payment record
        $next_date = ippgi_estimate_next_billing_date($member_id);
    }

    if ($next_date) {
        set_transient($cache_key, $next_date, HOUR_IN_SECONDS);
    }

    return $next_date;
}

/**
 * Get next billing date from PayPal Subscriptions API
 *
 * @param string $subscr_id PayPal Subscription ID
 * @return string|null Formatted date or null
 */
function ippgi_get_paypal_next_billing_date($subscr_id) {
    // Get PayPal API credentials from SWPM settings
    $settings = get_option('swpm-settings');
    $is_sandbox = !empty($settings['enable-sandbox-testing']);

    $client_id = $is_sandbox
        ? ($settings['paypal-sandbox-client-id'] ?? '')
        : ($settings['paypal-live-client-id'] ?? '');
    $client_secret = $is_sandbox
        ? ($settings['paypal-sandbox-secret-key'] ?? '')
        : ($settings['paypal-live-secret-key'] ?? '');

    if (empty($client_id) || empty($client_secret)) {
        return null;
    }

    // PayPal API base URL
    $api_base = $is_sandbox
        ? 'https://api-m.sandbox.paypal.com'
        : 'https://api-m.paypal.com';

    // Get access token
    $token_response = wp_remote_post($api_base . '/v1/oauth2/token', [
        'headers' => [
            'Authorization' => 'Basic ' . base64_encode($client_id . ':' . $client_secret),
            'Content-Type' => 'application/x-www-form-urlencoded',
        ],
        'body' => 'grant_type=client_credentials',
        'timeout' => 30,
    ]);

    if (is_wp_error($token_response)) {
        error_log('PayPal token error: ' . $token_response->get_error_message());
        return null;
    }

    $token_data = json_decode(wp_remote_retrieve_body($token_response), true);
    if (empty($token_data['access_token'])) {
        error_log('PayPal token error: No access token');
        return null;
    }

    // Get subscription details
    $sub_response = wp_remote_get($api_base . '/v1/billing/subscriptions/' . $subscr_id, [
        'headers' => [
            'Authorization' => 'Bearer ' . $token_data['access_token'],
            'Content-Type' => 'application/json',
        ],
        'timeout' => 30,
    ]);

    if (is_wp_error($sub_response)) {
        error_log('PayPal subscription error: ' . $sub_response->get_error_message());
        return null;
    }

    $sub_data = json_decode(wp_remote_retrieve_body($sub_response), true);

    // Get next billing time
    if (!empty($sub_data['billing_info']['next_billing_time'])) {
        $next_billing_time = $sub_data['billing_info']['next_billing_time'];
        // Format: 2024-02-02T00:00:00Z
        $date = new DateTime($next_billing_time);
        return $date->format('F j, Y');
    }

    return null;
}

/**
 * Get next billing date from Stripe Subscriptions API
 *
 * @param string $subscr_id Stripe Subscription ID
 * @return string|null Formatted date or null
 */
function ippgi_get_stripe_next_billing_date($subscr_id) {
    // Get Stripe API key from SWPM settings
    $settings = get_option('swpm-settings');
    $is_sandbox = !empty($settings['enable-sandbox-testing']);

    $secret_key = $is_sandbox
        ? ($settings['stripe-test-secret-key'] ?? '')
        : ($settings['stripe-live-secret-key'] ?? '');

    if (empty($secret_key)) {
        return null;
    }

    // Get subscription from Stripe API
    $response = wp_remote_get('https://api.stripe.com/v1/subscriptions/' . $subscr_id, [
        'headers' => [
            'Authorization' => 'Bearer ' . $secret_key,
        ],
        'timeout' => 30,
    ]);

    if (is_wp_error($response)) {
        error_log('Stripe subscription error: ' . $response->get_error_message());
        return null;
    }

    $sub_data = json_decode(wp_remote_retrieve_body($response), true);

    // Get current_period_end (Unix timestamp)
    // First try top-level (older API versions), then try items.data[0] (newer API versions)
    $period_end = null;

    if (!empty($sub_data['current_period_end'])) {
        $period_end = $sub_data['current_period_end'];
    } elseif (!empty($sub_data['items']['data'][0]['current_period_end'])) {
        $period_end = $sub_data['items']['data'][0]['current_period_end'];
    }

    if ($period_end) {
        return date('F j, Y', $period_end);
    }

    return null;
}

/**
 * Estimate next billing date from payment record (fallback)
 *
 * @param int $member_id SWPM Member ID
 * @return string|null Formatted date or null
 */
function ippgi_estimate_next_billing_date($member_id) {
    global $wpdb;

    $payment = $wpdb->get_row($wpdb->prepare(
        "SELECT payment_amount, txn_date FROM {$wpdb->prefix}swpm_payments_tbl
         WHERE member_id = %d ORDER BY id DESC LIMIT 1",
        $member_id
    ));

    if ($payment && !empty($payment->txn_date)) {
        // Estimate based on payment amount: $100 = yearly, $10 = monthly
        $duration = ($payment->payment_amount >= 100) ? '+1 year' : '+1 month';
        return date('F j, Y', strtotime($payment->txn_date . ' ' . $duration));
    }

    return null;
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

    // Get SWPM member to find subscription ID
    if (!ippgi_is_swpm_active() || !class_exists('SwpmMemberUtils')) {
        wp_send_json_error(['message' => __('Membership system not available.', 'ippgi')]);
    }

    $wp_user = get_user_by('id', $user_id);
    if (!$wp_user) {
        wp_send_json_error(['message' => __('User not found.', 'ippgi')]);
    }

    $swpm_member = SwpmMemberUtils::get_user_by_user_name($wp_user->user_login);
    if (!$swpm_member || empty($swpm_member->subscr_id)) {
        wp_send_json_error(['message' => __('No active subscription found.', 'ippgi')]);
    }

    $subscr_id = $swpm_member->subscr_id;

    // Cancel subscription via PayPal or Stripe API
    if (strpos($subscr_id, 'I-') === 0) {
        // PayPal subscription
        $result = ippgi_cancel_paypal_subscription($subscr_id);
    } elseif (strpos($subscr_id, 'sub_') === 0) {
        // Stripe subscription
        $result = ippgi_cancel_stripe_subscription($subscr_id);
    } else {
        wp_send_json_error(['message' => __('Unknown subscription type.', 'ippgi')]);
    }

    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()]);
    }

    // Set local cancellation flag
    update_user_meta($user_id, 'ippgi_subscription_cancelled', true);
    update_user_meta($user_id, 'ippgi_subscription_cancelled_date', current_time('mysql'));

    // Clear next billing date cache
    $cache_key = 'ippgi_next_billing_' . md5($subscr_id);
    delete_transient($cache_key);

    // Check if user has bonus days to activate after subscription ends
    $bonus_days = ippgi_get_unused_bonus_days($user_id);
    if ($bonus_days > 0) {
        error_log(sprintf('IPPGI: User %d has %d bonus days that will be activated after subscription ends', $user_id, $bonus_days));
    }

    // Log the cancellation
    error_log(sprintf('IPPGI: User %d cancelled their subscription %s', $user_id, $subscr_id));

    wp_send_json_success([
        'message' => __('Your subscription has been cancelled.', 'ippgi'),
    ]);
}
add_action('wp_ajax_ippgi_cancel_subscription', 'ippgi_ajax_cancel_subscription');

/**
 * Cancel PayPal subscription via API
 *
 * @param string $subscr_id PayPal Subscription ID
 * @return true|WP_Error
 */
function ippgi_cancel_paypal_subscription($subscr_id) {
    $settings = get_option('swpm-settings');
    $is_sandbox = !empty($settings['enable-sandbox-testing']);

    $client_id = $is_sandbox
        ? ($settings['paypal-sandbox-client-id'] ?? '')
        : ($settings['paypal-live-client-id'] ?? '');
    $client_secret = $is_sandbox
        ? ($settings['paypal-sandbox-secret-key'] ?? '')
        : ($settings['paypal-live-secret-key'] ?? '');

    if (empty($client_id) || empty($client_secret)) {
        return new WP_Error('config_error', __('PayPal API credentials not configured.', 'ippgi'));
    }

    $api_base = $is_sandbox
        ? 'https://api-m.sandbox.paypal.com'
        : 'https://api-m.paypal.com';

    // Get access token
    $token_response = wp_remote_post($api_base . '/v1/oauth2/token', [
        'headers' => [
            'Authorization' => 'Basic ' . base64_encode($client_id . ':' . $client_secret),
            'Content-Type' => 'application/x-www-form-urlencoded',
        ],
        'body' => 'grant_type=client_credentials',
        'timeout' => 30,
    ]);

    if (is_wp_error($token_response)) {
        return new WP_Error('api_error', __('Failed to connect to PayPal.', 'ippgi'));
    }

    $token_data = json_decode(wp_remote_retrieve_body($token_response), true);
    if (empty($token_data['access_token'])) {
        return new WP_Error('auth_error', __('Failed to authenticate with PayPal.', 'ippgi'));
    }

    // Cancel subscription
    $cancel_response = wp_remote_post($api_base . '/v1/billing/subscriptions/' . $subscr_id . '/cancel', [
        'headers' => [
            'Authorization' => 'Bearer ' . $token_data['access_token'],
            'Content-Type' => 'application/json',
        ],
        'body' => json_encode([
            'reason' => 'Customer requested cancellation'
        ]),
        'timeout' => 30,
    ]);

    if (is_wp_error($cancel_response)) {
        return new WP_Error('api_error', __('Failed to cancel subscription.', 'ippgi'));
    }

    $status_code = wp_remote_retrieve_response_code($cancel_response);
    if ($status_code !== 204 && $status_code !== 200) {
        $body = json_decode(wp_remote_retrieve_body($cancel_response), true);
        $error_msg = $body['message'] ?? __('Failed to cancel subscription.', 'ippgi');
        return new WP_Error('cancel_error', $error_msg);
    }

    return true;
}

/**
 * Cancel Stripe subscription via API
 *
 * @param string $subscr_id Stripe Subscription ID
 * @return true|WP_Error
 */
function ippgi_cancel_stripe_subscription($subscr_id) {
    $settings = get_option('swpm-settings');
    $is_sandbox = !empty($settings['enable-sandbox-testing']);

    $secret_key = $is_sandbox
        ? ($settings['stripe-test-secret-key'] ?? '')
        : ($settings['stripe-live-secret-key'] ?? '');

    if (empty($secret_key)) {
        return new WP_Error('config_error', __('Stripe API key not configured.', 'ippgi'));
    }

    // Cancel subscription (cancel at period end to let user keep access until billing period ends)
    $response = wp_remote_post('https://api.stripe.com/v1/subscriptions/' . $subscr_id, [
        'headers' => [
            'Authorization' => 'Bearer ' . $secret_key,
            'Content-Type' => 'application/x-www-form-urlencoded',
        ],
        'body' => [
            'cancel_at_period_end' => 'true',
        ],
        'timeout' => 30,
    ]);

    if (is_wp_error($response)) {
        return new WP_Error('api_error', __('Failed to connect to Stripe.', 'ippgi'));
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);

    if ($status_code !== 200) {
        $error_msg = $body['error']['message'] ?? __('Failed to cancel subscription.', 'ippgi');
        return new WP_Error('cancel_error', $error_msg);
    }

    return true;
}

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
