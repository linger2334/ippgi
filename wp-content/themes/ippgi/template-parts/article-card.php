<?php
/**
 * Article Card Template Part
 * Displays a single article in card format
 *
 * @package IPPGI
 * @since 1.0.0
 */
?>
<article id="post-<?php the_ID(); ?>" <?php post_class('article-card'); ?>>
    <div class="article-card__header">
        <a href="<?php the_permalink(); ?>" class="article-card__image-link">
            <?php if (has_post_thumbnail()) : ?>
                <?php the_post_thumbnail('medium', ['class' => 'article-card__image']); ?>
            <?php else : ?>
                <div class="article-card__image article-card__image--placeholder">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#ccc" stroke-width="1">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                        <circle cx="8.5" cy="8.5" r="1.5"></circle>
                        <polyline points="21 15 16 10 5 21"></polyline>
                    </svg>
                </div>
            <?php endif; ?>
        </a>

        <div class="article-card__info">
            <h3 class="article-card__title">
                <a href="<?php the_permalink(); ?>">
                    <?php the_title(); ?>
                </a>
            </h3>

            <div class="article-card__meta">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
                <time class="article-card__date" datetime="<?php echo esc_attr(get_the_date('c')); ?>">
                    <?php echo esc_html(get_the_date('M d, Y')); ?>
                </time>
            </div>
        </div>
    </div>

    <?php if (has_excerpt() || get_the_content()) : ?>
        <a href="<?php the_permalink(); ?>" class="article-card__excerpt-link">
            <p class="article-card__excerpt">
                <?php echo esc_html(wp_trim_words(get_the_excerpt(), 40, '...')); ?>
            </p>
        </a>
    <?php endif; ?>
</article>
