<?php
/**
 * Template Name: Prices Page
 *
 * @package IPPGI
 * @since 1.0.0
 */

get_header();

// Get filter from URL
$material_type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : '';
?>

<main id="main-content" class="site-main">
    <div class="container">
        <header class="prices-header">
            <h1 class="prices-header__title"><?php esc_html_e('Material Prices', 'ippgi'); ?></h1>
            <p class="prices-header__date">
                <?php
                printf(
                    /* translators: %s: current date */
                    esc_html__('Last updated: %s', 'ippgi'),
                    esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format')))
                );
                ?>
            </p>
        </header>

        <!-- Material Type Filter -->
        <nav class="prices-filter" role="tablist" aria-label="<?php esc_attr_e('Filter by material type', 'ippgi'); ?>">
            <a href="<?php echo esc_url(home_url('/prices')); ?>" class="prices-filter__btn <?php echo empty($material_type) ? 'is-active' : ''; ?>" role="tab">
                <?php esc_html_e('All', 'ippgi'); ?>
            </a>
            <a href="<?php echo esc_url(add_query_arg('type', 'gi', home_url('/prices'))); ?>" class="prices-filter__btn <?php echo $material_type === 'gi' ? 'is-active' : ''; ?>" role="tab">
                <?php esc_html_e('GI', 'ippgi'); ?>
            </a>
            <a href="<?php echo esc_url(add_query_arg('type', 'gl', home_url('/prices'))); ?>" class="prices-filter__btn <?php echo $material_type === 'gl' ? 'is-active' : ''; ?>" role="tab">
                <?php esc_html_e('GL', 'ippgi'); ?>
            </a>
            <a href="<?php echo esc_url(add_query_arg('type', 'ppgi', home_url('/prices'))); ?>" class="prices-filter__btn <?php echo $material_type === 'ppgi' ? 'is-active' : ''; ?>" role="tab">
                <?php esc_html_e('PPGI', 'ippgi'); ?>
            </a>
            <a href="<?php echo esc_url(add_query_arg('type', 'hrc', home_url('/prices'))); ?>" class="prices-filter__btn <?php echo $material_type === 'hrc' ? 'is-active' : ''; ?>" role="tab">
                <?php esc_html_e('HRC', 'ippgi'); ?>
            </a>
            <a href="<?php echo esc_url(add_query_arg('type', 'crc', home_url('/prices'))); ?>" class="prices-filter__btn <?php echo $material_type === 'crc' ? 'is-active' : ''; ?>" role="tab">
                <?php esc_html_e('CRC', 'ippgi'); ?>
            </a>
            <a href="<?php echo esc_url(add_query_arg('type', 'aluminum', home_url('/prices'))); ?>" class="prices-filter__btn <?php echo $material_type === 'aluminum' ? 'is-active' : ''; ?>" role="tab">
                <?php esc_html_e('Aluminum', 'ippgi'); ?>
            </a>
        </nav>

        <!-- Today's Prices Section -->
        <section class="prices-section">
            <h2 class="prices-section__title"><?php esc_html_e('Today\'s Prices', 'ippgi'); ?></h2>
            <?php get_template_part('template-parts/price-table'); ?>
        </section>

        <!-- Historical Data Section (Premium Content) -->
        <section class="prices-section prices-section--history">
            <div class="prices-section__header">
                <h2 class="prices-section__title"><?php esc_html_e('Price History', 'ippgi'); ?></h2>
                <?php if (ippgi_user_can_view_history()) : ?>
                    <div class="prices-section__controls">
                        <select class="form-select" id="history-range">
                            <option value="7"><?php esc_html_e('Last 7 days', 'ippgi'); ?></option>
                            <option value="30"><?php esc_html_e('Last 30 days', 'ippgi'); ?></option>
                            <option value="90"><?php esc_html_e('Last 90 days', 'ippgi'); ?></option>
                            <option value="365"><?php esc_html_e('Last year', 'ippgi'); ?></option>
                        </select>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (ippgi_user_can_view_history()) : ?>
                <!-- Chart Placeholder - will be implemented in Phase 2 -->
                <div class="price-chart" id="price-chart">
                    <div class="price-chart__placeholder">
                        <p><?php esc_html_e('Price chart will be displayed here.', 'ippgi'); ?></p>
                        <p class="text-muted"><?php esc_html_e('(Chart functionality will be implemented in Phase 2)', 'ippgi'); ?></p>
                    </div>
                </div>

                <!-- Historical Data Table -->
                <div class="history-table-wrapper">
                    <p class="text-muted"><?php esc_html_e('Historical data table will be loaded from the database in Phase 2.', 'ippgi'); ?></p>
                </div>
            <?php else : ?>
                <!-- Upgrade Prompt for Non-Premium Users -->
                <div class="premium-gate">
                    <div class="premium-gate__content">
                        <svg class="premium-gate__icon" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                        <h3 class="premium-gate__title"><?php esc_html_e('Unlock Price History', 'ippgi'); ?></h3>
                        <p class="premium-gate__text">
                            <?php esc_html_e('Subscribe to IPPGI Plus to access complete historical price data, interactive charts, and trend analysis.', 'ippgi'); ?>
                        </p>
                        <a href="<?php echo esc_url(home_url('/subscribe')); ?>" class="btn btn--primary">
                            <?php esc_html_e('Upgrade to Plus', 'ippgi'); ?>
                        </a>
                        <?php if (!is_user_logged_in()) : ?>
                            <p class="premium-gate__login">
                                <?php esc_html_e('Already have an account?', 'ippgi'); ?>
                                <a href="<?php echo esc_url(ippgi_get_login_url()); ?>"><?php esc_html_e('Log in', 'ippgi'); ?></a>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </section>
    </div>
</main>

<?php
get_footer();
