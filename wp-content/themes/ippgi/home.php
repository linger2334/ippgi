<?php
/**
 * Blog Home Template
 * Used when a static page is set as the "Posts page"
 *
 * @package IPPGI
 * @since 1.0.0
 */

get_header();
?>

<main id="main-content" class="site-main">
    <div class="container">
        <!-- Page Title Section -->
        <section class="blog-header">
            <h1 class="blog-header__title">
                <?php esc_html_e('Top news on China steel and other commodity markets', 'ippgi'); ?>
            </h1>
            <p class="blog-header__subtitle">
                <?php esc_html_e('Latest Chinese commodities market news', 'ippgi'); ?>
            </p>
        </section>

        <!-- Date Filter -->
        <div class="search-date-filter" role="button" tabindex="0">
            <svg class="search-date-filter__icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <!-- Calendar outline -->
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                <!-- Top header (filled) -->
                <rect x="3" y="4" width="18" height="6" rx="2" ry="2" fill="currentColor" stroke="none"></rect>
                <!-- Top binding rings -->
                <line x1="8" y1="2" x2="8" y2="5" stroke="currentColor"></line>
                <line x1="16" y1="2" x2="16" y2="5" stroke="currentColor"></line>
                <!-- Horizontal grid lines -->
                <line x1="3" y1="14" x2="21" y2="14"></line>
                <line x1="3" y1="18" x2="21" y2="18"></line>
                <!-- Vertical grid lines -->
                <line x1="8" y1="10" x2="8" y2="22"></line>
                <line x1="12" y1="10" x2="12" y2="22"></line>
                <line x1="16" y1="10" x2="16" y2="22"></line>
            </svg>
            <span class="search-date-filter__text"><?php esc_html_e('Start Date ~ End Date', 'ippgi'); ?></span>
            <span class="search-date-filter__line"></span>
        </div>

        <!-- Articles List -->
        <?php if (have_posts()) : ?>
            <div class="blog-list">
                <?php while (have_posts()) : the_post(); ?>
                    <article <?php post_class('blog-card'); ?>>
                        <a href="<?php the_permalink(); ?>" class="blog-card__link">
                            <div class="blog-card__top">
                                <div class="blog-card__image">
                                    <?php if (has_post_thumbnail()) : ?>
                                        <?php the_post_thumbnail('medium', ['class' => 'blog-card__img']); ?>
                                    <?php else : ?>
                                        <div class="blog-card__placeholder">
                                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                                <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                                <polyline points="21 15 16 10 5 21"></polyline>
                                            </svg>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="blog-card__info">
                                    <h2 class="blog-card__title"><?php the_title(); ?></h2>
                                    <div class="blog-card__date">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <circle cx="12" cy="12" r="10"></circle>
                                            <polyline points="12 6 12 12 16 14"></polyline>
                                        </svg>
                                        <time datetime="<?php echo esc_attr(get_the_date('c')); ?>">
                                            <?php echo esc_html(get_the_date('M d, Y')); ?>
                                        </time>
                                    </div>
                                </div>
                            </div>
                            <div class="blog-card__excerpt">
                                <?php echo wp_trim_words(get_the_excerpt(), 30, '...'); ?>
                            </div>
                        </a>
                    </article>
                <?php endwhile; ?>
            </div>

            <?php the_posts_pagination([
                'mid_size'  => 2,
                'prev_text' => __('&laquo; Previous', 'ippgi'),
                'next_text' => __('Next &raquo;', 'ippgi'),
            ]); ?>
        <?php else : ?>
            <div class="no-content">
                <h2><?php esc_html_e('No posts found', 'ippgi'); ?></h2>
                <p><?php esc_html_e('It seems we can\'t find what you\'re looking for.', 'ippgi'); ?></p>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- Date Picker Bottom Sheet -->
<div class="date-picker-backdrop"></div>
<div class="date-picker-sheet" data-current-year="<?php echo date('Y'); ?>" data-current-month="<?php echo date('n'); ?>">
    <div class="date-picker-sheet__header">
        <span class="date-picker-sheet__title"><?php esc_html_e('Select Date Range', 'ippgi'); ?></span>
        <button type="button" class="date-picker-sheet__close" aria-label="<?php esc_attr_e('Close', 'ippgi'); ?>">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        </button>
    </div>
    <div class="date-picker-sheet__range">
        <div class="date-picker-sheet__range-item">
            <span class="date-picker-sheet__range-label"><?php esc_html_e('Start', 'ippgi'); ?></span>
            <span class="date-picker-sheet__range-value" id="date-range-start">--</span>
        </div>
        <span class="date-picker-sheet__range-separator">~</span>
        <div class="date-picker-sheet__range-item">
            <span class="date-picker-sheet__range-label"><?php esc_html_e('End', 'ippgi'); ?></span>
            <span class="date-picker-sheet__range-value" id="date-range-end">--</span>
        </div>
    </div>
    <div class="date-picker-sheet__body">
        <div class="date-picker-sheet__nav">
            <button type="button" class="date-picker-sheet__nav-btn" id="date-picker-prev" aria-label="<?php esc_attr_e('Previous month', 'ippgi'); ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="15 18 9 12 15 6"></polyline>
                </svg>
            </button>
            <span class="date-picker-sheet__month" id="date-picker-month"><?php echo esc_html(date_i18n('F Y')); ?></span>
            <button type="button" class="date-picker-sheet__nav-btn" id="date-picker-next" aria-label="<?php esc_attr_e('Next month', 'ippgi'); ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
            </button>
        </div>
        <div class="date-picker-sheet__weekdays">
            <span class="date-picker-sheet__weekday"><?php esc_html_e('S', 'ippgi'); ?></span>
            <span class="date-picker-sheet__weekday"><?php esc_html_e('M', 'ippgi'); ?></span>
            <span class="date-picker-sheet__weekday"><?php esc_html_e('T', 'ippgi'); ?></span>
            <span class="date-picker-sheet__weekday"><?php esc_html_e('W', 'ippgi'); ?></span>
            <span class="date-picker-sheet__weekday"><?php esc_html_e('T', 'ippgi'); ?></span>
            <span class="date-picker-sheet__weekday"><?php esc_html_e('F', 'ippgi'); ?></span>
            <span class="date-picker-sheet__weekday"><?php esc_html_e('S', 'ippgi'); ?></span>
        </div>
        <div class="date-picker-sheet__days" id="date-picker-days">
            <!-- Days will be generated by JavaScript -->
        </div>
    </div>
    <div class="date-picker-sheet__footer">
        <button type="button" class="date-picker-sheet__btn date-picker-sheet__btn--clear" id="date-picker-clear">
            <?php esc_html_e('Clear', 'ippgi'); ?>
        </button>
        <button type="button" class="date-picker-sheet__btn date-picker-sheet__btn--confirm" id="date-picker-confirm">
            <?php esc_html_e('Confirm', 'ippgi'); ?>
        </button>
    </div>
</div>

<?php
get_footer();
