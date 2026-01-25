<?php
/**
 * Template Name: Payment Page
 *
 * @package IPPGI
 * @since 1.0.0
 */

// Redirect if not logged in
if (!is_user_logged_in()) {
    wp_redirect(ippgi_get_login_url());
    exit;
}

// Redirect if already has Plus
if (ippgi_user_has_plus()) {
    wp_redirect(home_url('/profile'));
    exit;
}

get_header();
?>

<main id="main-content" class="site-main">
    <div class="container">
        <div class="payment-page">
            <div class="payment-card">
                <header class="payment-card__header">
                    <h1 class="payment-card__title"><?php esc_html_e('Complete Your Subscription', 'ippgi'); ?></h1>
                </header>

                <div class="payment-card__summary">
                    <h2 class="payment-card__plan-name"><?php esc_html_e('IPPGI Plus', 'ippgi'); ?></h2>
                    <div class="payment-card__plan-options">
                        <label class="payment-option">
                            <input type="radio" name="plan_type" value="monthly" checked>
                            <span class="payment-option__content">
                                <span class="payment-option__name"><?php esc_html_e('Monthly', 'ippgi'); ?></span>
                                <span class="payment-option__price">$10/<?php esc_html_e('month', 'ippgi'); ?></span>
                            </span>
                        </label>
                        <label class="payment-option">
                            <input type="radio" name="plan_type" value="yearly">
                            <span class="payment-option__content">
                                <span class="payment-option__name"><?php esc_html_e('Yearly', 'ippgi'); ?></span>
                                <span class="payment-option__price">$100/<?php esc_html_e('year', 'ippgi'); ?></span>
                                <span class="payment-option__save"><?php esc_html_e('Save $20', 'ippgi'); ?></span>
                            </span>
                        </label>
                    </div>
                </div>

                <div class="payment-card__methods">
                    <h3><?php esc_html_e('Payment Method', 'ippgi'); ?></h3>

                    <!-- Stripe Payment Buttons -->
                    <div class="payment-method payment-method--stripe">
                        <div class="stripe-button-wrapper" id="stripe-monthly-btn">
                            <?php echo do_shortcode('[swpm_payment_button id="32"]'); ?>
                        </div>
                        <div class="stripe-button-wrapper" id="stripe-yearly-btn" style="display: none;">
                            <?php echo do_shortcode('[swpm_payment_button id="33"]'); ?>
                        </div>
                    </div>
                </div>

                <div class="payment-card__terms">
                    <p>
                        <?php
                        printf(
                            /* translators: %1$s: terms link, %2$s: privacy link */
                            esc_html__('By subscribing, you agree to our %1$s and %2$s.', 'ippgi'),
                            '<a href="' . esc_url(home_url('/terms')) . '">' . esc_html__('Terms of Service', 'ippgi') . '</a>',
                            '<a href="' . esc_url(home_url('/privacy')) . '">' . esc_html__('Privacy Policy', 'ippgi') . '</a>'
                        );
                        ?>
                    </p>
                </div>

                <footer class="payment-card__footer">
                    <div class="payment-security">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                        <span><?php esc_html_e('Secure payment processed by Stripe', 'ippgi'); ?></span>
                    </div>
                </footer>
            </div>

            <div class="payment-benefits">
                <h3><?php esc_html_e('What you\'ll get:', 'ippgi'); ?></h3>
                <ul>
                    <li><?php esc_html_e('Complete historical price data', 'ippgi'); ?></li>
                    <li><?php esc_html_e('Interactive price charts', 'ippgi'); ?></li>
                    <li><?php esc_html_e('Trend analysis tools', 'ippgi'); ?></li>
                    <li><?php esc_html_e('Data export capabilities', 'ippgi'); ?></li>
                    <li><?php esc_html_e('Priority customer support', 'ippgi'); ?></li>
                </ul>
            </div>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const planRadios = document.querySelectorAll('input[name="plan_type"]');
    const monthlyBtn = document.getElementById('stripe-monthly-btn');
    const yearlyBtn = document.getElementById('stripe-yearly-btn');

    planRadios.forEach(function(radio) {
        radio.addEventListener('change', function() {
            if (this.value === 'monthly') {
                monthlyBtn.style.display = 'block';
                yearlyBtn.style.display = 'none';
            } else {
                monthlyBtn.style.display = 'none';
                yearlyBtn.style.display = 'block';
            }
        });
    });
});
</script>

<?php
get_footer();
