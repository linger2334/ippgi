<?php
/**
 * Page Template
 *
 * @package IPPGI
 * @since 1.0.0
 */

get_header();
?>

<main id="main-content" class="site-main">
    <div class="container">
        <?php if (have_posts()) : while (have_posts()) : the_post(); ?>
            <article id="page-<?php the_ID(); ?>" <?php post_class('page-content'); ?>>
                <header class="page-header">
                    <h1 class="page-title"><?php the_title(); ?></h1>
                </header>

                <div class="page-content__body layout-single">
                    <?php the_content(); ?>
                </div>
            </article>
        <?php endwhile; endif; ?>
    </div>
</main>

<?php
get_footer();
