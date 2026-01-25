<?php
/**
 * Single Post Template
 *
 * @package IPPGI
 * @since 1.0.0
 */

get_header();
?>

<main id="main-content" class="site-main">
    <div class="container">
        <?php if (have_posts()) : while (have_posts()) : the_post(); ?>
            <article id="post-<?php the_ID(); ?>" <?php post_class('single-post'); ?>>
                <header class="single-post__header">
                    <h1 class="single-post__title"><?php the_title(); ?></h1>

                    <time class="single-post__date" datetime="<?php echo esc_attr(get_the_date('c')); ?>">
                        <?php echo esc_html(get_the_date('M d, Y')); ?>
                    </time>
                </header>

                <div class="single-post__content layout-single">
                    <?php the_content(); ?>
                </div>
            </article>

        <?php endwhile; endif; ?>
    </div>
</main>

<?php
get_footer();
