<?php
/**
 * Template Name: Subscribe Page
 *
 * @package IPPGI
 * @since 1.0.0
 */

get_header();
?>

<main id="main-content" class="site-main">
    <!-- Mobile Header with gradient (visible on mobile only) -->
    <div class="subscribe-hero">
        <div class="container">
            <div class="subscribe-hero__brand">PPGI.CN</div>
            <h1 class="subscribe-hero__title"><?php esc_html_e('Subscriptions & Services', 'ippgi'); ?></h1>

            <!-- Billing Toggle -->
            <div class="billing-toggle">
                <button type="button" class="billing-toggle__option billing-toggle__option--yearly" data-billing="yearly">
                    <?php esc_html_e('Pay yearly', 'ippgi'); ?>
                    <span class="billing-toggle__save"><?php esc_html_e('save 17%', 'ippgi'); ?></span>
                </button>
                <button type="button" class="billing-toggle__option billing-toggle__option--monthly is-active" data-billing="monthly">
                    <?php esc_html_e('Pay monthly', 'ippgi'); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Desktop Header (visible on desktop only) -->
    <div class="subscribe-header-desktop">
        <div class="container">
            <h1 class="subscribe-header__title"><?php esc_html_e('Subscriptions & Services', 'ippgi'); ?></h1>

            <!-- Billing Toggle -->
            <div class="billing-toggle">
                <button type="button" class="billing-toggle__option billing-toggle__option--yearly" data-billing="yearly">
                    <?php esc_html_e('Pay yearly', 'ippgi'); ?>
                    <span class="billing-toggle__save"><?php esc_html_e('save 17%', 'ippgi'); ?></span>
                </button>
                <button type="button" class="billing-toggle__option billing-toggle__option--monthly is-active" data-billing="monthly">
                    <?php esc_html_e('Pay monthly', 'ippgi'); ?>
                </button>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="pricing-cards pricing-cards--two-col">
            <!-- Basic Plan (Free) -->
            <div class="pricing-card">
                <div class="pricing-card__header">
                    <h2 class="pricing-card__title"><?php esc_html_e('Basic', 'ippgi'); ?></h2>
                    <div class="pricing-card__price">
                        <span class="pricing-card__amount-text"><?php esc_html_e('Free', 'ippgi'); ?></span>
                    </div>
                </div>
                <div class="pricing-card__footer pricing-card__footer--top">
                    <span class="btn btn--secondary btn--block"><?php esc_html_e('Current Plan', 'ippgi'); ?></span>
                </div>
                <ul class="pricing-card__features">
                    <li class="pricing-card__feature">
                        <svg class="pricing-card__icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="8" x2="12" y2="12"></line>
                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                        </svg>
                        <?php esc_html_e('Unlimited access to Market Insights.', 'ippgi'); ?>
                    </li>
                    <li class="pricing-card__feature">
                        <svg class="pricing-card__icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                        </svg>
                        <?php esc_html_e('Unlimited access to Events.', 'ippgi'); ?>
                    </li>
                    <li class="pricing-card__feature">
                        <svg class="pricing-card__icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                        </svg>
                        <?php esc_html_e('Full access to My Favorites.', 'ippgi'); ?>
                    </li>
                </ul>
            </div>

            <!-- Plus Plan (Paid) -->
            <div class="pricing-card pricing-card--featured">
                <div class="pricing-card__header">
                    <h2 class="pricing-card__title"><?php esc_html_e('Plus', 'ippgi'); ?></h2>
                    <div class="pricing-card__price">
                        <span class="pricing-card__currency">$</span>
                        <span class="pricing-card__amount pricing-card__amount--monthly">10.00</span>
                        <span class="pricing-card__amount pricing-card__amount--yearly" style="display: none;">100.00</span>
                        <span class="pricing-card__period pricing-card__period--monthly">/<?php esc_html_e('month', 'ippgi'); ?></span>
                        <span class="pricing-card__period pricing-card__period--yearly" style="display: none;">/<?php esc_html_e('year', 'ippgi'); ?></span>
                    </div>
                </div>
                <div class="pricing-card__footer pricing-card__footer--top">
                    <?php if (ippgi_user_has_plus()) : ?>
                        <span class="btn btn--dark btn--block"><?php esc_html_e('Current Plan', 'ippgi'); ?></span>
                    <?php else : ?>
                        <a href="<?php echo esc_url(home_url('/payment?plan=monthly')); ?>" class="btn btn--dark btn--block pricing-card__cta pricing-card__cta--monthly">
                            <?php esc_html_e('Upgrade to Plus', 'ippgi'); ?>
                        </a>
                        <a href="<?php echo esc_url(home_url('/payment?plan=yearly')); ?>" class="btn btn--dark btn--block pricing-card__cta pricing-card__cta--yearly" style="display: none;">
                            <?php esc_html_e('Upgrade to Plus', 'ippgi'); ?>
                        </a>
                    <?php endif; ?>
                </div>
                <ul class="pricing-card__features">
                    <li class="pricing-card__feature pricing-card__feature--highlight">
                        <svg class="pricing-card__icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                        </svg>
                        <?php esc_html_e('Full access to all product prices.', 'ippgi'); ?>
                    </li>
                    <li class="pricing-card__feature">
                        <svg class="pricing-card__icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="8" x2="12" y2="12"></line>
                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                        </svg>
                        <?php esc_html_e('Unlimited access to Market Insights.', 'ippgi'); ?>
                    </li>
                    <li class="pricing-card__feature">
                        <svg class="pricing-card__icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                        </svg>
                        <?php esc_html_e('Unlimited access to Events.', 'ippgi'); ?>
                    </li>
                    <li class="pricing-card__feature">
                        <svg class="pricing-card__icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                        </svg>
                        <?php esc_html_e('Full access to My Favorites.', 'ippgi'); ?>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const yearlyBtns = document.querySelectorAll('.billing-toggle__option--yearly');
    const monthlyBtns = document.querySelectorAll('.billing-toggle__option--monthly');

    const monthlyAmounts = document.querySelectorAll('.pricing-card__amount--monthly');
    const yearlyAmounts = document.querySelectorAll('.pricing-card__amount--yearly');
    const monthlyPeriods = document.querySelectorAll('.pricing-card__period--monthly');
    const yearlyPeriods = document.querySelectorAll('.pricing-card__period--yearly');
    const monthlyCtas = document.querySelectorAll('.pricing-card__cta--monthly');
    const yearlyCtas = document.querySelectorAll('.pricing-card__cta--yearly');

    function switchToYearly() {
        yearlyBtns.forEach(btn => btn.classList.add('is-active'));
        monthlyBtns.forEach(btn => btn.classList.remove('is-active'));

        monthlyAmounts.forEach(el => el.style.display = 'none');
        yearlyAmounts.forEach(el => el.style.display = '');
        monthlyPeriods.forEach(el => el.style.display = 'none');
        yearlyPeriods.forEach(el => el.style.display = '');
        monthlyCtas.forEach(el => el.style.display = 'none');
        yearlyCtas.forEach(el => el.style.display = '');
    }

    function switchToMonthly() {
        monthlyBtns.forEach(btn => btn.classList.add('is-active'));
        yearlyBtns.forEach(btn => btn.classList.remove('is-active'));

        monthlyAmounts.forEach(el => el.style.display = '');
        yearlyAmounts.forEach(el => el.style.display = 'none');
        monthlyPeriods.forEach(el => el.style.display = '');
        yearlyPeriods.forEach(el => el.style.display = 'none');
        monthlyCtas.forEach(el => el.style.display = '');
        yearlyCtas.forEach(el => el.style.display = 'none');
    }

    yearlyBtns.forEach(btn => btn.addEventListener('click', switchToYearly));
    monthlyBtns.forEach(btn => btn.addEventListener('click', switchToMonthly));
});
</script>

<?php
get_footer();
