<?php
/**
 * Template Name: Invite Page
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
$invite_link = ippgi_get_user_invite_link($current_user->ID);

get_header();
?>

<main id="main-content" class="site-main">
    <div class="container">
        <div class="invite-page">
            <header class="invite-header">
                <h1 class="invite-header__title"><?php esc_html_e('Invite Friends', 'ippgi'); ?></h1>
                <p class="invite-header__subtitle"><?php esc_html_e('Share IPPGI with your colleagues and earn rewards.', 'ippgi'); ?></p>
            </header>

            <div class="invite-content">
                <!-- Share Link Card -->
                <div class="invite-card">
                    <h2 class="invite-card__title"><?php esc_html_e('Your Invite Link', 'ippgi'); ?></h2>
                    <div class="invite-link-box">
                        <input type="text" class="form-input invite-link-input" value="<?php echo esc_attr($invite_link); ?>" readonly id="invite-link">
                        <button type="button" class="btn btn--primary" id="copy-invite-link" data-clipboard-target="#invite-link">
                            <?php esc_html_e('Copy', 'ippgi'); ?>
                        </button>
                    </div>
                    <p class="invite-card__hint"><?php esc_html_e('Share this link with your friends and colleagues.', 'ippgi'); ?></p>

                    <!-- Social Share Buttons -->
                    <div class="invite-share">
                        <span class="invite-share__label"><?php esc_html_e('Or share via:', 'ippgi'); ?></span>
                        <div class="invite-share__buttons">
                            <a href="https://twitter.com/intent/tweet?text=<?php echo urlencode(__('Check out IPPGI for real-time material price information!', 'ippgi')); ?>&url=<?php echo urlencode($invite_link); ?>" class="btn btn--sm btn--outline" target="_blank" rel="noopener">
                                Twitter
                            </a>
                            <a href="https://www.linkedin.com/shareArticle?mini=true&url=<?php echo urlencode($invite_link); ?>&title=<?php echo urlencode(__('IPPGI - Material Price Information', 'ippgi')); ?>" class="btn btn--sm btn--outline" target="_blank" rel="noopener">
                                LinkedIn
                            </a>
                            <a href="mailto:?subject=<?php echo rawurlencode(__('Check out IPPGI', 'ippgi')); ?>&body=<?php echo rawurlencode(__('I thought you might find this useful: ', 'ippgi') . $invite_link); ?>" class="btn btn--sm btn--outline">
                                Email
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Rewards Info -->
                <div class="invite-rewards">
                    <h2 class="invite-rewards__title"><?php esc_html_e('How It Works', 'ippgi'); ?></h2>
                    <div class="invite-steps">
                        <div class="invite-step">
                            <div class="invite-step__number">1</div>
                            <h3 class="invite-step__title"><?php esc_html_e('Share Your Link', 'ippgi'); ?></h3>
                            <p class="invite-step__text"><?php esc_html_e('Send your unique invite link to friends and colleagues.', 'ippgi'); ?></p>
                        </div>
                        <div class="invite-step">
                            <div class="invite-step__number">2</div>
                            <h3 class="invite-step__title"><?php esc_html_e('They Sign Up', 'ippgi'); ?></h3>
                            <p class="invite-step__text"><?php esc_html_e('When they register using your link, they become a member.', 'ippgi'); ?></p>
                        </div>
                        <div class="invite-step">
                            <div class="invite-step__number">3</div>
                            <h3 class="invite-step__title"><?php esc_html_e('Earn Rewards', 'ippgi'); ?></h3>
                            <p class="invite-step__text"><?php esc_html_e('You get 3 days of Plus membership for each successful referral!', 'ippgi'); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Referral Stats -->
                <div class="invite-stats">
                    <h2 class="invite-stats__title"><?php esc_html_e('Your Referrals', 'ippgi'); ?></h2>
                    <div class="invite-stats__grid">
                        <div class="invite-stat">
                            <span class="invite-stat__value"><?php echo esc_html(ippgi_get_user_referral_count($current_user->ID)); ?></span>
                            <span class="invite-stat__label"><?php esc_html_e('Total Invites', 'ippgi'); ?></span>
                        </div>
                        <div class="invite-stat">
                            <span class="invite-stat__value"><?php echo esc_html(ippgi_get_user_converted_referrals($current_user->ID)); ?></span>
                            <span class="invite-stat__label"><?php esc_html_e('Successful Referrals', 'ippgi'); ?></span>
                        </div>
                        <div class="invite-stat">
                            <span class="invite-stat__value"><?php echo esc_html(ippgi_get_user_total_bonus_days($current_user->ID)); ?></span>
                            <span class="invite-stat__label"><?php esc_html_e('Bonus Days Earned', 'ippgi'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
document.getElementById('copy-invite-link')?.addEventListener('click', function() {
    const input = document.getElementById('invite-link');
    input.select();
    document.execCommand('copy');
    this.textContent = '<?php echo esc_js(__('Copied!', 'ippgi')); ?>';
    setTimeout(() => {
        this.textContent = '<?php echo esc_js(__('Copy', 'ippgi')); ?>';
    }, 2000);
});
</script>

<?php
get_footer();
