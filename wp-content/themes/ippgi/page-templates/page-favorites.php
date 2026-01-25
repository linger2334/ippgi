<?php
/**
 * Template Name: Favorites Page
 *
 * @package IPPGI
 * @since 1.0.0
 */

// Redirect if not logged in
if (!is_user_logged_in()) {
    wp_redirect(ippgi_get_login_url());
    exit;
}

get_header();
?>

<main id="main-content" class="site-main">
    <div class="container">
        <header class="page-header">
            <h1 class="page-title"><?php esc_html_e('My Favorites', 'ippgi'); ?></h1>
            <p class="page-subtitle"><?php esc_html_e('Track your favorite materials in one place.', 'ippgi'); ?></p>
        </header>

        <?php
        // Get user's favorite materials (placeholder - will be implemented with database)
        $favorites = ippgi_get_user_favorites();

        if (!empty($favorites)) :
        ?>
            <div class="favorites-grid">
                <?php foreach ($favorites as $favorite) : ?>
                    <div class="favorite-card">
                        <div class="favorite-card__header">
                            <span class="favorite-card__code"><?php echo esc_html($favorite['code']); ?></span>
                            <button type="button" class="favorite-btn is-active" data-price-id="<?php echo esc_attr($favorite['id']); ?>" aria-label="<?php esc_attr_e('Remove from favorites', 'ippgi'); ?>">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2">
                                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                                </svg>
                            </button>
                        </div>
                        <h3 class="favorite-card__name"><?php echo esc_html($favorite['name']); ?></h3>
                        <div class="favorite-card__price">
                            <span class="favorite-card__value"><?php echo esc_html(number_format($favorite['price'])); ?></span>
                            <span class="favorite-card__unit">CNY/ton</span>
                        </div>
                        <div class="favorite-card__change <?php echo $favorite['change'] >= 0 ? 'favorite-card__change--up' : 'favorite-card__change--down'; ?>">
                            <?php echo $favorite['change'] >= 0 ? '&#9650;' : '&#9660;'; ?>
                            <?php echo esc_html(abs($favorite['change'])); ?>
                        </div>
                        <a href="<?php echo esc_url(home_url('/price-detail?material=' . $favorite['id'])); ?>" class="favorite-card__link">
                            <?php esc_html_e('View Details', 'ippgi'); ?> &rarr;
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else : ?>
            <div class="empty-state">
                <svg class="empty-state__icon" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                </svg>
                <h2 class="empty-state__title"><?php esc_html_e('No favorites yet', 'ippgi'); ?></h2>
                <p class="empty-state__text"><?php esc_html_e('Start adding materials to your favorites to track them here.', 'ippgi'); ?></p>
                <a href="<?php echo esc_url(home_url('/prices')); ?>" class="btn btn--primary">
                    <?php esc_html_e('Browse Prices', 'ippgi'); ?>
                </a>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php
get_footer();
