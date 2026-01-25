<?php
/**
 * Mobile Menu Dropdown
 * Appears below header like search dropdown
 *
 * @package IPPGI
 * @since 1.0.0
 */
?>
<div class="mobile-menu-dropdown" id="mobile-menu-dropdown" hidden>
    <nav class="mobile-menu-dropdown__nav" role="navigation" aria-label="<?php esc_attr_e('Mobile Navigation', 'ippgi'); ?>">
        <?php if (has_nav_menu('mobile')) : ?>
            <?php
            wp_nav_menu([
                'theme_location' => 'mobile',
                'menu_class'     => 'mobile-menu-dropdown__list',
                'container'      => false,
                'depth'          => 1,
                'fallback_cb'    => false,
            ]);
            ?>
        <?php elseif (has_nav_menu('primary')) : ?>
            <?php
            wp_nav_menu([
                'theme_location' => 'primary',
                'menu_class'     => 'mobile-menu-dropdown__list',
                'container'      => false,
                'depth'          => 1,
                'fallback_cb'    => false,
            ]);
            ?>
        <?php else : ?>
            <ul class="mobile-menu-dropdown__list">
                <li class="mobile-menu-dropdown__item">
                    <a href="<?php echo esc_url(home_url('/prices')); ?>" class="mobile-menu-dropdown__link">
                        <?php esc_html_e('Prices & Trends', 'ippgi'); ?>
                    </a>
                </li>
                <li class="mobile-menu-dropdown__item">
                    <a href="<?php echo esc_url(get_permalink(get_option('page_for_posts'))); ?>" class="mobile-menu-dropdown__link">
                        <?php esc_html_e('Market Insights', 'ippgi'); ?>
                    </a>
                </li>
                <li class="mobile-menu-dropdown__item">
                    <a href="<?php echo esc_url(home_url('/favorites')); ?>" class="mobile-menu-dropdown__link">
                        <?php esc_html_e('My favorites', 'ippgi'); ?>
                    </a>
                </li>
                <li class="mobile-menu-dropdown__item">
                    <a href="<?php echo esc_url(home_url('/contact')); ?>" class="mobile-menu-dropdown__link">
                        <?php esc_html_e('Contact Us', 'ippgi'); ?>
                    </a>
                </li>
                <li class="mobile-menu-dropdown__item">
                    <a href="<?php echo esc_url(home_url('/invite')); ?>" class="mobile-menu-dropdown__link">
                        <?php esc_html_e('Share & Earn', 'ippgi'); ?>
                    </a>
                </li>
            </ul>
        <?php endif; ?>
    </nav>
</div>
