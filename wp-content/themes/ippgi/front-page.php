<?php
/**
 * Front Page Template
 *
 * @package IPPGI
 * @since 1.0.0
 */

get_header();
?>

<main id="main-content" class="site-main">
    <!-- My Prices Section -->
    <section class="section section--prices">
        <div class="container">
            <div class="my-prices">
                <div class="my-prices__header">
                    <h2 class="my-prices__title">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                        </svg>
                        <?php esc_html_e('MyPrices', 'ippgi'); ?>
                        <span class="my-prices__category" id="current-category">PPGI</span>
                    </h2>
                </div>
                <p class="my-prices__disclaimer">
                    <?php esc_html_e('*These prices reflect the transaction prices within China and do not include shipping costs.', 'ippgi'); ?>
                </p>
                <p class="my-prices__updated" id="prices-updated">
                    <?php
                    printf(
                        /* translators: %s: date and time */
                        esc_html__('Updated: %s (UTC+8)', 'ippgi'),
                        date_i18n('M d, Y, h:i A')
                    );
                    ?>
                </p>

                <!-- Dynamic Price Table Carousel -->
                <div class="price-carousel">
                    <div class="price-carousel__viewport" id="price-table-container" role="button" tabindex="0" aria-label="<?php esc_attr_e('View price details', 'ippgi'); ?>">
                        <div class="price-carousel__track">
                            <div class="price-table-loading">
                                <div class="spinner"></div>
                                <span><?php esc_html_e('Loading prices...', 'ippgi'); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="price-carousel__dots" id="price-carousel-dots"></div>
                </div>

                <div class="my-prices__footer">
                    <a href="#" class="my-prices__more" id="prices-read-more">
                        <?php esc_html_e('Read More', 'ippgi'); ?>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="9 18 15 12 9 6"></polyline>
                        </svg>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Banner Carousel Section -->
    <section class="section section--banner">
        <div class="container">
            <?php
            // Get banner slides from customizer
            $banners = [];
            for ($i = 1; $i <= 5; $i++) {
                $image = get_theme_mod("ippgi_banner_{$i}_image", '');
                if ($image) {
                    $banners[] = [
                        'image' => $image,
                        'link'  => get_theme_mod("ippgi_banner_{$i}_link", ''),
                        'title' => get_theme_mod("ippgi_banner_{$i}_title", ''),
                    ];
                }
            }
            $interval = get_theme_mod('ippgi_banner_interval', 5000);

            if (!empty($banners)) :
            ?>
            <div class="banner-carousel" data-interval="<?php echo esc_attr($interval); ?>">
                <div class="banner-carousel__slides">
                    <?php foreach ($banners as $index => $banner) : ?>
                    <div class="banner-carousel__slide <?php echo $index === 0 ? 'is-active' : ''; ?>">
                        <?php if ($banner['link']) : ?>
                        <a href="<?php echo esc_url($banner['link']); ?>" class="banner-carousel__link">
                        <?php endif; ?>
                            <img src="<?php echo esc_url($banner['image']); ?>"
                                 alt="<?php echo esc_attr($banner['title'] ?: sprintf(__('Banner %d', 'ippgi'), $index + 1)); ?>"
                                 class="banner-carousel__image"
                                 loading="<?php echo $index === 0 ? 'eager' : 'lazy'; ?>">
                            <?php if ($banner['title']) : ?>
                            <span class="banner-carousel__title"><?php echo esc_html($banner['title']); ?></span>
                            <?php endif; ?>
                        <?php if ($banner['link']) : ?>
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php if (count($banners) > 1) : ?>
                <div class="banner-carousel__dots">
                    <?php foreach ($banners as $index => $banner) : ?>
                    <button type="button"
                            class="banner-carousel__dot <?php echo $index === 0 ? 'is-active' : ''; ?>"
                            data-index="<?php echo esc_attr($index); ?>"
                            aria-label="<?php echo esc_attr(sprintf(__('Go to slide %d', 'ippgi'), $index + 1)); ?>">
                    </button>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php else : ?>
            <!-- Fallback promo banner when no images configured -->
            <div class="promo-banner">
                <div class="promo-banner__content">
                    <div class="promo-banner__icon">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M12 2L2 7l10 5 10-5-10-5z"></path>
                            <path d="M2 17l10 5 10-5"></path>
                            <path d="M2 12l10 5 10-5"></path>
                        </svg>
                    </div>
                    <div class="promo-banner__text">
                        <h3 class="promo-banner__title"><?php esc_html_e('Real-Time Steel Price Tracking', 'ippgi'); ?></h3>
                        <p class="promo-banner__desc"><?php esc_html_e('Access live prices for PPGI, GI, GL, HRC, CRC and Aluminum. Updated hourly from China\'s largest steel trading hub.', 'ippgi'); ?></p>
                    </div>
                </div>
                <div class="promo-banner__action">
                    <a href="<?php echo esc_url(home_url('/subscribe')); ?>" class="btn btn--primary btn--lg">
                        <?php esc_html_e('Start Free Trial', 'ippgi'); ?>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="9 18 15 12 9 6"></polyline>
                        </svg>
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Market Insights Section -->
    <section class="section section--insights">
        <div class="container">
            <div class="section__header">
                <h2 class="section__title">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polygon points="10 8 16 12 10 16 10 8"></polygon>
                    </svg>
                    <?php esc_html_e('Market Insights', 'ippgi'); ?>
                </h2>
            </div>

            <div class="insights-list">
                <?php
                $insights_query = new WP_Query([
                    'posts_per_page' => 3,
                    'post_status'    => 'publish',
                ]);

                if ($insights_query->have_posts()) :
                    while ($insights_query->have_posts()) : $insights_query->the_post();
                        get_template_part('template-parts/article-card');
                    endwhile;
                    wp_reset_postdata();
                else :
                    ?>
                    <div class="no-content">
                        <p><?php esc_html_e('No market insights available yet.', 'ippgi'); ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="section__footer">
                <?php
                $blog_url = get_option('page_for_posts') ? get_permalink(get_option('page_for_posts')) : home_url('/');
                ?>
                <a href="<?php echo esc_url($blog_url); ?>" class="section__more">
                    <?php esc_html_e('Read More', 'ippgi'); ?>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </a>
            </div>
        </div>
    </section>
</main>

<?php
get_footer();
