<?php
/**
 * The footer template
 *
 * @package IPPGI
 * @since 1.0.0
 */
?>

<footer class="site-footer" role="contentinfo">
    <div class="container">
        <!-- Products Section with Back to Top -->
        <div class="site-footer__products-section">
            <div class="site-footer__products">
                <a href="#" class="site-footer__product-link" data-category="PPGI"><?php echo esc_html(ippgi_get_product_display_name('ppgi')); ?></a>
                <a href="#" class="site-footer__product-link" data-category="GI"><?php echo esc_html(ippgi_get_product_display_name('gi')); ?></a>
                <a href="#" class="site-footer__product-link" data-category="GL"><?php echo esc_html(ippgi_get_product_display_name('gl')); ?></a>
                <a href="#" class="site-footer__product-link" data-category="AL"><?php echo esc_html(ippgi_get_product_display_name('aluminum')); ?></a>
                <a href="#" class="site-footer__product-link" data-category="CRC_HARD"><?php echo esc_html(ippgi_get_product_display_name('crc')); ?></a>
            </div>
            <button type="button" class="site-footer__back-to-top" aria-label="<?php esc_attr_e('Back to top', 'ippgi'); ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <polyline points="17 19 12 6 7 19"></polyline>
                </svg>
            </button>
        </div>

        <!-- Footer Bottom -->
        <div class="site-footer__bottom">
            <div class="site-footer__legal">
                <a href="<?php echo esc_url(home_url('/terms')); ?>" class="site-footer__legal-link"><?php esc_html_e('Terms&Conditions', 'ippgi'); ?></a>
                <a href="<?php echo esc_url(home_url('/privacy')); ?>" class="site-footer__legal-link"><?php esc_html_e('Privacy Policy', 'ippgi'); ?></a>
                <a href="<?php echo esc_url(home_url('/about')); ?>" class="site-footer__legal-link"><?php esc_html_e('About Us', 'ippgi'); ?></a>
            </div>
            <p class="site-footer__copyright">
                &copy; <?php esc_html_e('2026 ANT LIMITED, all rights reserved.', 'ippgi'); ?>
            </p>
        </div>

        <?php get_template_part('template-parts/social-icons'); ?>
    </div>
</footer>

<?php
// Include the relevant account prompt for the current visitor.
if (!is_user_logged_in()) {
    get_template_part('template-parts/login-modal');
} elseif ('' === trim((string) get_user_meta(get_current_user_id(), 'phone', true))) {
    get_template_part('template-parts/phone-collection-modal');
}
?>

<?php
// Include global toast component
get_template_part('template-parts/toast');
?>

<?php wp_footer(); ?>
</body>
</html>
