<?php
/**
 * Template Name: Profile Page
 *
 * @package IPPGI
 * @since 1.0.0
 */

// Redirect if not logged in
if (!is_user_logged_in()) {
    wp_redirect(ippgi_get_login_url());
    exit;
}

$current_user = wp_get_current_user();

get_header();
?>

<main id="main-content" class="site-main">
    <div class="container">
        <div class="profile-page">
            <!-- Profile Sidebar -->
            <aside class="profile-sidebar">
                <div class="profile-card">
                    <div class="profile-card__avatar">
                        <?php echo get_avatar($current_user->ID, 80); ?>
                    </div>
                    <h2 class="profile-card__name"><?php echo esc_html($current_user->display_name); ?></h2>
                    <p class="profile-card__email"><?php echo esc_html($current_user->user_email); ?></p>
                    <div class="profile-card__membership">
                        <?php if (ippgi_user_has_plus()) : ?>
                            <span class="badge badge--primary"><?php esc_html_e('Plus Member', 'ippgi'); ?></span>
                        <?php elseif (ippgi_user_has_trial()) : ?>
                            <span class="badge badge--info"><?php esc_html_e('Trial', 'ippgi'); ?></span>
                        <?php else : ?>
                            <span class="badge badge--secondary"><?php esc_html_e('Basic', 'ippgi'); ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <nav class="profile-nav">
                    <a href="#account" class="profile-nav__link is-active"><?php esc_html_e('Account', 'ippgi'); ?></a>
                    <a href="#subscription" class="profile-nav__link"><?php esc_html_e('Subscription', 'ippgi'); ?></a>
                    <a href="<?php echo esc_url(home_url('/favorites')); ?>" class="profile-nav__link"><?php esc_html_e('Favorites', 'ippgi'); ?></a>
                    <a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>" class="profile-nav__link profile-nav__link--logout"><?php esc_html_e('Log Out', 'ippgi'); ?></a>
                </nav>
            </aside>

            <!-- Profile Content -->
            <div class="profile-content">
                <!-- Account Section -->
                <section id="account" class="profile-section">
                    <h2 class="profile-section__title"><?php esc_html_e('Account Settings', 'ippgi'); ?></h2>

                    <?php
                    // Check if Simple Membership is active
                    if (function_exists('swpm_profile_form')) {
                        echo do_shortcode('[swpm_profile_form]');
                    } else {
                        // Fallback form
                        ?>
                        <form class="profile-form" method="post">
                            <?php wp_nonce_field('ippgi_profile_update', 'profile_nonce'); ?>

                            <div class="form-group">
                                <label class="form-label" for="display_name"><?php esc_html_e('Display Name', 'ippgi'); ?></label>
                                <input type="text" id="display_name" name="display_name" class="form-input" value="<?php echo esc_attr($current_user->display_name); ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="user_email"><?php esc_html_e('Email Address', 'ippgi'); ?></label>
                                <input type="email" id="user_email" name="user_email" class="form-input" value="<?php echo esc_attr($current_user->user_email); ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="new_password"><?php esc_html_e('New Password', 'ippgi'); ?></label>
                                <input type="password" id="new_password" name="new_password" class="form-input" placeholder="<?php esc_attr_e('Leave blank to keep current password', 'ippgi'); ?>">
                            </div>

                            <button type="submit" class="btn btn--primary"><?php esc_html_e('Save Changes', 'ippgi'); ?></button>
                        </form>
                        <?php
                    }
                    ?>
                </section>

                <!-- Subscription Section -->
                <section id="subscription" class="profile-section">
                    <h2 class="profile-section__title"><?php esc_html_e('Subscription', 'ippgi'); ?></h2>

                    <?php if (ippgi_user_has_plus()) : ?>
                        <div class="subscription-info">
                            <div class="subscription-info__plan">
                                <h3><?php esc_html_e('IPPGI Plus', 'ippgi'); ?></h3>
                                <p><?php esc_html_e('You have full access to all premium features.', 'ippgi'); ?></p>
                            </div>
                            <div class="subscription-info__details">
                                <p><strong><?php esc_html_e('Next billing date:', 'ippgi'); ?></strong> <?php echo esc_html(ippgi_get_next_billing_date()); ?></p>
                                <p><strong><?php esc_html_e('Payment method:', 'ippgi'); ?></strong> PayPal</p>
                            </div>
                            <div class="subscription-info__actions">
                                <a href="#" class="btn btn--outline"><?php esc_html_e('Manage Subscription', 'ippgi'); ?></a>
                            </div>
                        </div>
                    <?php elseif (ippgi_user_has_trial()) : ?>
                        <div class="subscription-info subscription-info--trial">
                            <div class="subscription-info__plan">
                                <h3><?php esc_html_e('Free Trial', 'ippgi'); ?></h3>
                                <p><?php esc_html_e('You\'re currently on a 7-day free trial.', 'ippgi'); ?></p>
                            </div>
                            <div class="subscription-info__details">
                                <p><strong><?php esc_html_e('Trial ends:', 'ippgi'); ?></strong> <?php echo esc_html(ippgi_get_trial_end_date()); ?></p>
                            </div>
                            <div class="subscription-info__actions">
                                <a href="<?php echo esc_url(home_url('/subscribe')); ?>" class="btn btn--primary"><?php esc_html_e('Upgrade to Plus', 'ippgi'); ?></a>
                            </div>
                        </div>
                    <?php else : ?>
                        <div class="subscription-info subscription-info--basic">
                            <div class="subscription-info__plan">
                                <h3><?php esc_html_e('Basic (Free)', 'ippgi'); ?></h3>
                                <p><?php esc_html_e('Upgrade to Plus for complete price history and advanced features.', 'ippgi'); ?></p>
                            </div>
                            <div class="subscription-info__actions">
                                <a href="<?php echo esc_url(home_url('/subscribe')); ?>" class="btn btn--primary"><?php esc_html_e('Upgrade to Plus', 'ippgi'); ?></a>
                            </div>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        </div>
    </div>
</main>

<?php
get_footer();
