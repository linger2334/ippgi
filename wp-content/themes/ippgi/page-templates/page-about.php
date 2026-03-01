<?php
/**
 * Template Name: About Us Page
 *
 * @package IPPGI
 * @since 1.0.0
 */

get_header();
?>

<main id="main-content" class="site-main">
    <div class="container">
        <article class="legal-page">
            <header class="legal-header">
                <h1 class="legal-header__title"><?php esc_html_e('About Us', 'ippgi'); ?></h1>
            </header>

            <div class="legal-content">
                <?php
                if (have_posts()) {
                    while (have_posts()) {
                        the_post();
                        the_content();
                    }
                } else {
                    ?>
                    <p><?php esc_html_e('IPPGI provides steel and raw material pricing insights for global buyers and industry professionals.', 'ippgi'); ?></p>
                    <?php
                }
                ?>
            </div>
        </article>
    </div>
</main>

<?php
get_footer();
