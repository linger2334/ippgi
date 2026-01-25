<?php
/**
 * Search Results Template
 *
 * @package IPPGI
 * @since 1.0.0
 */

get_header();

$search_query = get_search_query();
?>

<main id="main-content" class="site-main">
    <div class="container">
        <!-- Date Filter -->
        <div class="search-date-filter" role="button" tabindex="0">
            <svg class="search-date-filter__icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                <line x1="16" y1="2" x2="16" y2="6"></line>
                <line x1="8" y1="2" x2="8" y2="6"></line>
                <line x1="3" y1="10" x2="21" y2="10"></line>
            </svg>
            <span class="search-date-filter__text"><?php esc_html_e('Start Date ~ End Date', 'ippgi'); ?></span>
            <span class="search-date-filter__line"></span>
        </div>

        <?php if (have_posts()) : ?>
            <div class="search-results-list">
                <?php while (have_posts()) : the_post(); ?>
                    <article class="search-result-card">
                        <div class="search-result-card__image">
                            <?php if (has_post_thumbnail()) : ?>
                                <?php the_post_thumbnail('medium'); ?>
                            <?php else : ?>
                                <div class="placeholder-image">
                                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#ccc" stroke-width="1">
                                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                        <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                        <polyline points="21 15 16 10 5 21"></polyline>
                                    </svg>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="search-result-card__content">
                            <h2 class="search-result-card__title">
                                <a href="<?php the_permalink(); ?>">
                                    <?php echo ippgi_highlight_search_terms(get_the_title(), $search_query); ?>
                                </a>
                            </h2>
                            <div class="search-result-card__date">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <polyline points="12 6 12 12 16 14"></polyline>
                                </svg>
                                <time datetime="<?php echo esc_attr(get_the_date('c')); ?>">
                                    <?php echo esc_html(get_the_date('M d, Y')); ?>
                                </time>
                            </div>
                            <div class="search-result-card__excerpt">
                                <?php echo ippgi_highlight_search_terms(get_the_excerpt(), $search_query); ?>
                            </div>
                        </div>
                    </article>
                <?php endwhile; ?>
            </div>

            <?php the_posts_pagination([
                'mid_size'  => 2,
                'prev_text' => __('&laquo;', 'ippgi'),
                'next_text' => __('&raquo;', 'ippgi'),
            ]); ?>
        <?php else : ?>
            <div class="no-content">
                <h2><?php esc_html_e('No results found', 'ippgi'); ?></h2>
                <p><?php esc_html_e('Sorry, but nothing matched your search terms. Please try again with different keywords.', 'ippgi'); ?></p>
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
