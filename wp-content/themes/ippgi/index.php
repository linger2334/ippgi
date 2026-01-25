<?php
/**
 * The main template file
 *
 * @package IPPGI
 * @since 1.0.0
 */

get_header();
?>

<main id="main-content" class="site-main">
    <div class="container">
        <?php if (have_posts()) : ?>
            <div class="posts-list">
                <?php while (have_posts()) : the_post(); ?>
                    <?php get_template_part('template-parts/article-card'); ?>
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

<?php
get_footer();
