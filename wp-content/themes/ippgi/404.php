<?php
/**
 * 404 Page Template
 *
 * @package IPPGI
 * @since 1.0.0
 */

get_header();
?>

<main id="main-content" class="site-main">
    <div class="container">
        <div class="error-404">
            <div class="error-404__content">
                <h1 class="error-404__title">404</h1>
                <h2 class="error-404__subtitle"><?php esc_html_e('Page Not Found', 'ippgi'); ?></h2>
                <p class="error-404__text">
                    <?php esc_html_e('Sorry, the page you\'re looking for doesn\'t exist or has been moved.', 'ippgi'); ?>
                </p>

                <div class="error-404__actions">
                    <a href="<?php echo esc_url(home_url('/')); ?>" class="btn btn--primary">
                        <?php esc_html_e('Go to Homepage', 'ippgi'); ?>
                    </a>
                </div>

                <div class="error-404__search">
                    <p><?php esc_html_e('Or try searching:', 'ippgi'); ?></p>
                    <?php get_search_form(); ?>
                </div>

                <div class="error-404__links">
                    <h3><?php esc_html_e('Popular Links', 'ippgi'); ?></h3>
                    <ul>
                        <li><a href="<?php echo esc_url(home_url('/prices')); ?>"><?php esc_html_e('View Prices', 'ippgi'); ?></a></li>
                        <li><a href="<?php echo esc_url(get_permalink(get_option('page_for_posts'))); ?>"><?php esc_html_e('Market Insights', 'ippgi'); ?></a></li>
                        <li><a href="<?php echo esc_url(home_url('/contact')); ?>"><?php esc_html_e('Contact Us', 'ippgi'); ?></a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</main>

<?php
get_footer();
