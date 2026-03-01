<?php
/**
 * Shared social icons block for footer areas.
 *
 * @package IPPGI
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$ippgi_social_attrs = static function ($url) {
    $url = trim((string) $url);

    if ($url === '') {
        return 'href="#"';
    }

    return 'href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer"';
};
?>
<div class="site-footer__social">
    <a <?php echo $ippgi_social_attrs(get_theme_mod('ippgi_social_facebook', '')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> class="social-icon" aria-label="Facebook">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
            <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
        </svg>
    </a>
    <a <?php echo $ippgi_social_attrs(get_theme_mod('ippgi_social_linkedin', '')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> class="social-icon" aria-label="LinkedIn">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
            <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
        </svg>
    </a>
    <a <?php echo $ippgi_social_attrs(get_theme_mod('ippgi_social_twitter', '')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> class="social-icon" aria-label="Twitter">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
            <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
        </svg>
    </a>
    <a <?php echo $ippgi_social_attrs(get_theme_mod('ippgi_social_instagram', '')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> class="social-icon" aria-label="Instagram">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
            <path d="M7.75 2C4.578 2 2 4.578 2 7.75v8.5C2 19.422 4.578 22 7.75 22h8.5C19.422 22 22 19.422 22 16.25v-8.5C22 4.578 19.422 2 16.25 2h-8.5zm0 2h8.5A3.75 3.75 0 0 1 20 7.75v8.5A3.75 3.75 0 0 1 16.25 20h-8.5A3.75 3.75 0 0 1 4 16.25v-8.5A3.75 3.75 0 0 1 7.75 4zm9 1.5a1.25 1.25 0 1 0 0 2.5 1.25 1.25 0 0 0 0-2.5zM12 7a5 5 0 1 0 0 10 5 5 0 0 0 0-10zm0 2a3 3 0 1 1 0 6 3 3 0 0 1 0-6z"/>
        </svg>
    </a>
    <a <?php echo $ippgi_social_attrs(get_theme_mod('ippgi_social_pinterest', '')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> class="social-icon" aria-label="Pinterest">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
            <path d="M12 2C6.486 2 2 6.486 2 12c0 4.237 2.636 7.85 6.356 9.307-.088-.791-.167-2.005.035-2.869.183-.781 1.178-4.97 1.178-4.97s-.299-.599-.299-1.485c0-1.39.806-2.428 1.81-2.428.853 0 1.264.64 1.264 1.408 0 .857-.546 2.138-.827 3.327-.236.998.5 1.812 1.483 1.812 1.78 0 3.149-1.878 3.149-4.588 0-2.398-1.723-4.073-4.185-4.073-2.85 0-4.523 2.137-4.523 4.347 0 .861.332 1.785.747 2.286.082.1.094.188.07.29-.077.319-.247.998-.28 1.137-.044.184-.146.223-.337.134-1.258-.586-2.045-2.427-2.045-3.907 0-3.182 2.311-6.103 6.657-6.103 3.495 0 6.213 2.492 6.213 5.822 0 3.474-2.19 6.269-5.23 6.269-1.021 0-1.981-.53-2.308-1.156l-.627 2.39c-.226.868-.837 1.955-1.247 2.618.94.29 1.936.448 2.968.448 5.514 0 10-4.486 10-10S17.514 2 12 2z"/>
        </svg>
    </a>
</div>
