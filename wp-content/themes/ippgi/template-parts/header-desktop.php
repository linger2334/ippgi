<?php
/**
 * Desktop Header Navigation
 *
 * @package IPPGI
 * @since 1.0.0
 */
?>
<nav class="header-nav" role="navigation" aria-label="<?php esc_attr_e('Primary Navigation', 'ippgi'); ?>">
    <?php if (has_nav_menu('primary')) : ?>
        <?php
        wp_nav_menu([
            'theme_location' => 'primary',
            'menu_class'     => 'header-nav__list',
            'container'      => false,
            'depth'          => 2,
            'fallback_cb'    => false,
            'link_before'    => '<span class="header-nav__link-text">',
            'link_after'     => '</span>',
            'walker'         => new IPPGI_Nav_Walker(),
        ]);
        ?>
    <?php else : ?>
        <ul class="header-nav__list">
            <li class="header-nav__item">
                <a href="<?php echo esc_url(home_url('/prices')); ?>" class="header-nav__link">
                    <?php esc_html_e('Prices & Trends', 'ippgi'); ?>
                </a>
            </li>
            <li class="header-nav__item">
                <a href="<?php echo esc_url(get_permalink(get_option('page_for_posts'))); ?>" class="header-nav__link">
                    <?php esc_html_e('Market Insights', 'ippgi'); ?>
                </a>
            </li>
            <li class="header-nav__item">
                <a href="<?php echo esc_url(home_url('/favorites')); ?>" class="header-nav__link">
                    <?php esc_html_e('My favorites', 'ippgi'); ?>
                </a>
            </li>
            <li class="header-nav__item">
                <a href="<?php echo esc_url(home_url('/invite')); ?>" class="header-nav__link">
                    <?php esc_html_e('Share & Earn', 'ippgi'); ?>
                </a>
            </li>
            <li class="header-nav__item">
                <a href="<?php echo esc_url(home_url('/contact')); ?>" class="header-nav__link">
                    <?php esc_html_e('Contact Us', 'ippgi'); ?>
                </a>
            </li>
        </ul>
    <?php endif; ?>
</nav>
