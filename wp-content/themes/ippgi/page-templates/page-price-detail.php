<?php
/**
 * Template Name: Price Detail Page
 *
 * @package IPPGI
 * @since 1.0.0
 */

get_header();

// Get material code from URL
$material_code = isset($_GET['material']) ? sanitize_text_field($_GET['material']) : 'gi';

// Material data mapping
$materials = [
    'gi'       => ['name' => __('Galvanized Steel', 'ippgi'), 'code' => 'GI'],
    'gl'       => ['name' => __('Galvalume Steel', 'ippgi'), 'code' => 'GL'],
    'ppgi'     => ['name' => __('Pre-painted Galvanized Iron', 'ippgi'), 'code' => 'PPGI'],
    'hrc'      => ['name' => __('Hot Rolled Coil', 'ippgi'), 'code' => 'HRC'],
    'crc'      => ['name' => __('Cold Rolled Hard Coil', 'ippgi'), 'code' => 'CRC Hard'],
    'aluminum' => ['name' => __('Aluminum Sheet', 'ippgi'), 'code' => 'AL'],
];

$current_material = isset($materials[$material_code]) ? $materials[$material_code] : $materials['gi'];
?>

<main id="main-content" class="site-main">
    <div class="container">
        <!-- Breadcrumb -->
        <nav class="breadcrumbs" aria-label="<?php esc_attr_e('Breadcrumb', 'ippgi'); ?>">
            <a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'ippgi'); ?></a>
            <span class="breadcrumbs__separator">/</span>
            <a href="<?php echo esc_url(home_url('/prices')); ?>"><?php esc_html_e('Prices', 'ippgi'); ?></a>
            <span class="breadcrumbs__separator">/</span>
            <span class="breadcrumbs__current"><?php echo esc_html($current_material['name']); ?></span>
        </nav>

        <header class="price-detail-header">
            <div class="price-detail-header__info">
                <span class="price-detail-header__code"><?php echo esc_html($current_material['code']); ?></span>
                <h1 class="price-detail-header__title"><?php echo esc_html($current_material['name']); ?></h1>
            </div>
            <?php if (is_user_logged_in()) : ?>
                <button type="button" class="favorite-btn favorite-btn--large" data-price-id="<?php echo esc_attr($material_code); ?>" aria-label="<?php esc_attr_e('Add to favorites', 'ippgi'); ?>">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                    </svg>
                </button>
            <?php endif; ?>
        </header>

        <!-- Current Price Card -->
        <div class="price-detail-current">
            <div class="price-detail-current__main">
                <span class="price-detail-current__label"><?php esc_html_e('Current Price', 'ippgi'); ?></span>
                <span class="price-detail-current__value">4,850</span>
                <span class="price-detail-current__unit">CNY/ton</span>
            </div>
            <div class="price-detail-current__change price-detail-current__change--up">
                <span class="price-detail-current__change-icon">&#9650;</span>
                <span class="price-detail-current__change-value">+25 (+0.52%)</span>
            </div>
            <div class="price-detail-current__updated">
                <?php
                printf(
                    /* translators: %s: update time */
                    esc_html__('Updated: %s', 'ippgi'),
                    esc_html(date_i18n('H:i'))
                );
                ?>
            </div>
        </div>

        <!-- Price Statistics -->
        <div class="price-detail-stats">
            <div class="price-detail-stat">
                <span class="price-detail-stat__label"><?php esc_html_e('Day High', 'ippgi'); ?></span>
                <span class="price-detail-stat__value">4,870</span>
            </div>
            <div class="price-detail-stat">
                <span class="price-detail-stat__label"><?php esc_html_e('Day Low', 'ippgi'); ?></span>
                <span class="price-detail-stat__value">4,825</span>
            </div>
            <div class="price-detail-stat">
                <span class="price-detail-stat__label"><?php esc_html_e('Week Avg', 'ippgi'); ?></span>
                <span class="price-detail-stat__value">4,842</span>
            </div>
            <div class="price-detail-stat">
                <span class="price-detail-stat__label"><?php esc_html_e('Month Avg', 'ippgi'); ?></span>
                <span class="price-detail-stat__value">4,815</span>
            </div>
        </div>

        <!-- Price Chart -->
        <section class="price-detail-section">
            <div class="price-detail-section__header">
                <h2 class="price-detail-section__title"><?php esc_html_e('Price Chart', 'ippgi'); ?></h2>
                <?php if (ippgi_user_can_view_history()) : ?>
                    <div class="price-detail-section__controls">
                        <button type="button" class="chart-range-btn is-active" data-range="1d">1D</button>
                        <button type="button" class="chart-range-btn" data-range="1w">1W</button>
                        <button type="button" class="chart-range-btn" data-range="1m">1M</button>
                        <button type="button" class="chart-range-btn" data-range="3m">3M</button>
                        <button type="button" class="chart-range-btn" data-range="1y">1Y</button>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (ippgi_user_can_view_history()) : ?>
                <div class="price-chart" id="material-chart">
                    <div class="price-chart__placeholder">
                        <p><?php esc_html_e('Interactive price chart will be displayed here.', 'ippgi'); ?></p>
                    </div>
                </div>
            <?php else : ?>
                <div class="premium-gate premium-gate--inline">
                    <svg class="premium-gate__icon" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                    <div>
                        <h3><?php esc_html_e('Unlock Historical Charts', 'ippgi'); ?></h3>
                        <p><?php esc_html_e('Subscribe to Plus to view detailed price history and trends.', 'ippgi'); ?></p>
                        <a href="<?php echo esc_url(home_url('/subscribe')); ?>" class="btn btn--primary btn--sm">
                            <?php esc_html_e('Upgrade', 'ippgi'); ?>
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </section>

        <!-- Today's Price Timeline -->
        <section class="price-detail-section">
            <h2 class="price-detail-section__title"><?php esc_html_e('Today\'s Price Updates', 'ippgi'); ?></h2>
            <div class="price-timeline">
                <div class="price-timeline__item">
                    <span class="price-timeline__time">09:00</span>
                    <span class="price-timeline__value">4,825</span>
                    <span class="price-timeline__change">--</span>
                </div>
                <div class="price-timeline__item">
                    <span class="price-timeline__time">10:00</span>
                    <span class="price-timeline__value">4,830</span>
                    <span class="price-timeline__change price-timeline__change--up">+5</span>
                </div>
                <div class="price-timeline__item">
                    <span class="price-timeline__time">11:00</span>
                    <span class="price-timeline__value">4,845</span>
                    <span class="price-timeline__change price-timeline__change--up">+15</span>
                </div>
                <div class="price-timeline__item price-timeline__item--current">
                    <span class="price-timeline__time">12:00</span>
                    <span class="price-timeline__value">4,850</span>
                    <span class="price-timeline__change price-timeline__change--up">+5</span>
                </div>
            </div>
        </section>
    </div>
</main>

<?php
get_footer();
