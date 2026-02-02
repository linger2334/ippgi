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

// Get plan type from URL parameter (from subscribe page)
$plan_type = isset($_GET['plan']) ? sanitize_text_field($_GET['plan']) : 'monthly';
$is_yearly = ($plan_type === 'yearly');

// Get current user info
$current_user = wp_get_current_user();
$user_email = $current_user->user_email;

// Price configuration
$monthly_price = 10;
$yearly_price = 100;
$price = $is_yearly ? $yearly_price : $monthly_price;
$period = $is_yearly ? 'year' : 'month';

// Payment button IDs (SWPM shortcode IDs)
// TODO: Update these IDs after creating PayPal buttons in SWPM admin
$stripe_monthly_id = 126;
$stripe_yearly_id = 127;
$paypal_monthly_id = 123; // Update after creating in SWPM
$paypal_yearly_id = 124;  // Update after creating in SWPM
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php wp_head(); ?>
</head>
<body <?php body_class('payment-page-body'); ?>>

<main id="main-content" class="site-main payment-page-main">
    <!-- Header Section with Gradient Background -->
    <div class="payment-header">
        <div class="payment-header__content">
            <p class="payment-header__subtitle"><?php esc_html_e('Subscribe to Plus', 'ippgi'); ?></p>
            <div class="payment-header__price">
                <span class="payment-header__amount">US$<?php echo esc_html(number_format($price, 2)); ?></span>
                <span class="payment-header__period">/<?php echo esc_html($period); ?></span>
            </div>
        </div>
    </div>

    <!-- White Card Section -->
    <div class="payment-card-wrapper">
        <div class="payment-card">
            <!-- Contact Information -->
            <section class="payment-section">
                <h2 class="payment-section__title"><?php esc_html_e('Contact Information', 'ippgi'); ?></h2>
                <div class="payment-email-field">
                    <span class="payment-email-field__label"><?php esc_html_e('Email', 'ippgi'); ?></span>
                    <span class="payment-email-field__value"><?php echo esc_html($user_email); ?></span>
                </div>
            </section>

            <!-- Payment Method -->
            <section class="payment-section">
                <h2 class="payment-section__title"><?php esc_html_e('Payment Method', 'ippgi'); ?></h2>

                <div class="payment-methods">
                    <!-- PayPal Option -->
                    <label class="payment-method">
                        <input type="radio" name="payment_method" value="paypal" checked>
                        <span class="payment-method__radio"></span>
                        <span class="payment-method__icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M19.554 9.488c.121.563.106 1.246-.04 2.051-.582 2.978-2.477 4.466-5.683 4.466h-.442a.666.666 0 0 0-.659.559l-.498 3.156-.212 1.345a.357.357 0 0 1-.353.3H8.612a.342.342 0 0 1-.34-.391l.035-.212.498-3.156.031-.168a.666.666 0 0 1 .659-.559h.442c2.631 0 4.688-.891 5.29-3.467.252-1.076.119-1.975-.277-2.605a2.27 2.27 0 0 0-.396-.319z" fill="#179BD7"/>
                                <path d="M18.706 9.044a4.03 4.03 0 0 0-.496-.136 6.224 6.224 0 0 0-.996-.074h-3.017a.666.666 0 0 0-.659.559l-.772 4.89-.022.141a.666.666 0 0 1 .659-.559h1.37c2.695 0 4.805-.891 5.422-3.467.183-.762.242-1.395.101-1.927a1.63 1.63 0 0 0-.59-.427z" fill="#222D65"/>
                                <path d="M13.539 9.393a.666.666 0 0 1 .659-.559h3.017c.357 0 .69.024.996.074.179.029.348.066.506.112.157.046.303.1.438.162.192-.977.001-1.642-.66-2.244-.726-.662-2.04-1.014-3.718-1.014H9.73a.666.666 0 0 0-.659.559l-1.816 11.51a.4.4 0 0 0 .395.462h2.874l.722-4.575.793-5.027-.5 3.54z" fill="#253B80"/>
                            </svg>
                        </span>
                        <span class="payment-method__name"><?php esc_html_e('PayPal', 'ippgi'); ?></span>
                    </label>

                    <!-- Stripe/Card Option -->
                    <label class="payment-method">
                        <input type="radio" name="payment_method" value="stripe">
                        <span class="payment-method__radio"></span>
                        <span class="payment-method__icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect x="2" y="5" width="20" height="14" rx="2" stroke="#666" stroke-width="1.5"/>
                                <path d="M2 10h20" stroke="#666" stroke-width="1.5"/>
                                <path d="M6 15h4" stroke="#666" stroke-width="1.5" stroke-linecap="round"/>
                            </svg>
                        </span>
                        <span class="payment-method__name"><?php esc_html_e('Credit Card', 'ippgi'); ?></span>
                    </label>
                </div>
            </section>

            <!-- SWPM Payment Buttons Area -->
            <div class="payment-buttons-area">
                <?php if ($is_yearly) : ?>
                    <!-- Yearly Buttons -->
                    <div class="swpm-button-container" id="paypal-btn" data-method="paypal">
                        <?php echo do_shortcode('[swpm_payment_button id="' . $paypal_yearly_id . '"]'); ?>
                    </div>
                    <div class="swpm-button-container" id="stripe-btn" data-method="stripe" style="display: none;">
                        <?php echo do_shortcode('[swpm_payment_button id="' . $stripe_yearly_id . '"]'); ?>
                    </div>
                <?php else : ?>
                    <!-- Monthly Buttons -->
                    <div class="swpm-button-container" id="paypal-btn" data-method="paypal">
                        <?php echo do_shortcode('[swpm_payment_button id="' . $paypal_monthly_id . '"]'); ?>
                    </div>
                    <div class="swpm-button-container" id="stripe-btn" data-method="stripe" style="display: none;">
                        <?php echo do_shortcode('[swpm_payment_button id="' . $stripe_monthly_id . '"]'); ?>
                    </div>
                <?php endif; ?>

            <!-- Terms Text -->
            <div class="payment-terms">
                <p class="payment-terms__text">
                    <?php
                    printf(
                        esc_html__('Subscribing means you authorize PPGI.CN to charge you according to the terms until you cancel your subscription.', 'ippgi')
                    );
                    ?>
                </p>
                <p class="payment-terms__links">
                    <a href="<?php echo esc_url(home_url('/terms')); ?>"><?php esc_html_e('Terms & Conditions', 'ippgi'); ?></a>
                    <a href="<?php echo esc_url(home_url('/privacy')); ?>"><?php esc_html_e('Privacy Policy', 'ippgi'); ?></a>
                    <a href="<?php echo esc_url(home_url('/contact')); ?>"><?php esc_html_e('Contact Us', 'ippgi'); ?></a>
                </p>
            </div>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const paymentMethods = document.querySelectorAll('input[name="payment_method"]');
    const paypalBtn = document.getElementById('paypal-btn');
    const stripeBtn = document.getElementById('stripe-btn');

    // Switch payment button based on selected method
    function updatePaymentButton() {
        const selected = document.querySelector('input[name="payment_method"]:checked');
        if (!selected) return;

        if (selected.value === 'paypal') {
            paypalBtn.style.display = 'block';
            stripeBtn.style.display = 'none';
        } else {
            paypalBtn.style.display = 'none';
            stripeBtn.style.display = 'block';
        }
    }

    // Visual feedback on payment method change
    paymentMethods.forEach(function(radio) {
        radio.addEventListener('change', function() {
            // Update visual state
            document.querySelectorAll('.payment-method').forEach(function(method) {
                method.classList.remove('payment-method--selected');
            });
            this.closest('.payment-method').classList.add('payment-method--selected');

            // Update visible payment button
            updatePaymentButton();
        });
    });

    // Set initial selected state
    const initialSelected = document.querySelector('input[name="payment_method"]:checked');
    if (initialSelected) {
        initialSelected.closest('.payment-method').classList.add('payment-method--selected');
    }
});
</script>

<?php wp_footer(); ?>
</body>
</html>
