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
$invitation_history = ippgi_get_invitation_history($current_user->ID);

get_header();
?>

<main id="main-content" class="site-main">
    <div class="container">
        <div class="invite-page">
            <!-- Main Title -->
            <h1 class="invite-page__title"><?php esc_html_e('Earn rewards for each friend you invite.', 'ippgi'); ?></h1>
            <p class="invite-page__subtitle"><?php esc_html_e('Share with your friends and get rewards.', 'ippgi'); ?></p>

            <!-- Referral Link Section -->
            <div class="invite-link-section">
                <p class="invite-link-section__intro">
                    <?php esc_html_e('Get Your Exclusive Referral Link! Share it with friends and earn rewards!', 'ippgi'); ?>
                </p>

                <div class="invite-link-box">
                    <input type="text" class="invite-link-box__input" value="<?php echo esc_attr($invite_link); ?>" readonly id="invite-link">
                    <button type="button" class="invite-link-box__btn" id="copy-invite-link">
                        <?php esc_html_e('Copy Link', 'ippgi'); ?>
                    </button>
                </div>

                <p class="invite-link-section__desc">
                    <?php esc_html_e('Share your exclusive referral link with friends! When they register, you\'ll earn 3 days of free subscription time.', 'ippgi'); ?>
                    <?php esc_html_e('Have questions?', 'ippgi'); ?>
                    <a href="<?php echo esc_url(home_url('/contact')); ?>" class="invite-link-section__contact">
                        <?php esc_html_e('Contact us', 'ippgi'); ?>
                    </a>.
                </p>
            </div>

            <!-- Invitation History Section -->
            <div class="invite-history">
                <h2 class="invite-history__title"><?php esc_html_e('Invitation History', 'ippgi'); ?></h2>

                <div class="invite-history__table-wrapper">
                    <table class="invite-history__table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Number', 'ippgi'); ?></th>
                                <th><?php esc_html_e('Timestamp', 'ippgi'); ?></th>
                                <th><?php esc_html_e('Referred User', 'ippgi'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($invitation_history)) : ?>
                                <?php foreach ($invitation_history as $index => $item) : ?>
                                    <tr>
                                        <td><?php echo esc_html($index + 1); ?></td>
                                        <td><?php echo esc_html($item['timestamp']); ?></td>
                                        <td><?php echo esc_html($item['email']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <tr>
                                    <td colspan="3" class="invite-history__empty">
                                        <?php esc_html_e('No referrals yet. Share your link to start earning rewards!', 'ippgi'); ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Toast Notification -->
<div class="toast" id="copy-toast">
    <?php esc_html_e('Copy Success!', 'ippgi'); ?>
</div>

<style>
.toast {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%) scale(0.8);
    background: rgba(0, 0, 0, 0.8);
    color: #fff;
    padding: 12px 24px;
    border-radius: 8px;
    font-size: 14px;
    z-index: 10000;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
}
.toast.show {
    opacity: 1;
    visibility: visible;
    transform: translate(-50%, -50%) scale(1);
}
</style>

<script>
// Auto-resize input to fit content
(function() {
    const input = document.getElementById('invite-link');
    if (input) {
        // Create a hidden span to measure text width
        const span = document.createElement('span');
        span.style.visibility = 'hidden';
        span.style.position = 'absolute';
        span.style.whiteSpace = 'pre';
        span.style.font = window.getComputedStyle(input).font;
        document.body.appendChild(span);

        span.textContent = input.value;
        input.style.width = (span.offsetWidth + 40) + 'px'; // Add padding

        document.body.removeChild(span);
    }
})();

document.getElementById('copy-invite-link')?.addEventListener('click', function() {
    const input = document.getElementById('invite-link');
    const toast = document.getElementById('copy-toast');

    function showToast() {
        toast.classList.add('show');
        setTimeout(() => {
            toast.classList.remove('show');
        }, 2000);
    }

    function copyFallback() {
        input.select();
        input.setSelectionRange(0, 99999);
        document.execCommand('copy');
        input.blur();
        if (window.getSelection) {
            window.getSelection().removeAllRanges();
        }
        showToast();
    }

    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(input.value).then(showToast).catch(copyFallback);
    } else {
        copyFallback();
    }
});
</script>

<?php
get_footer();
