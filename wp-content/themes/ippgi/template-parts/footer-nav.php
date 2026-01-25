<?php
/**
 * Footer Navigation and Copyright
 *
 * @package IPPGI
 * @since 1.0.0
 */
?>
<div class="site-footer__bottom">
    <p class="site-footer__copyright">
        &copy; <?php echo esc_html(date('Y')); ?> <?php bloginfo('name'); ?>. <?php esc_html_e('All rights reserved.', 'ippgi'); ?>
    </p>
    <nav class="site-footer__legal" aria-label="<?php esc_attr_e('Legal Links', 'ippgi'); ?>">
        <a href="<?php echo esc_url(home_url('/terms')); ?>" class="site-footer__legal-link">
            <?php esc_html_e('Terms of Service', 'ippgi'); ?>
        </a>
        <a href="<?php echo esc_url(home_url('/privacy')); ?>" class="site-footer__legal-link">
            <?php esc_html_e('Privacy Policy', 'ippgi'); ?>
        </a>
        <a href="<?php echo esc_url(home_url('/cookies')); ?>" class="site-footer__legal-link">
            <?php esc_html_e('Cookie Policy', 'ippgi'); ?>
        </a>
    </nav>
</div>
