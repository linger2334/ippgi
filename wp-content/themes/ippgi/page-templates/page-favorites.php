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

// Get user's favorite materials
$favorites = ippgi_get_user_favorites();

// Material types for filter
$material_types = [
    'ppgi' => 'PPGI',
    'gi' => 'GI',
    'gl' => 'GL',
    'al' => __('Aluminum Sheet', 'ippgi'),
    'crc_hard' => 'CRC Hard',
    'hrc' => 'HRC',
];

get_header();
?>

<main id="main-content" class="site-main">
    <div class="container">
        <div class="favorites-page">
            <!-- Header Section -->
            <div class="favorites-header">
                <h1 class="favorites-header__title">
                    <?php esc_html_e('My Favorites selection of China steel and commodities.', 'ippgi'); ?>
                </h1>
                <p class="favorites-header__subtitle">
                    <?php esc_html_e('Shortlist your favorite price curve, indices, daily prices.', 'ippgi'); ?>
                </p>
            </div>

            <!-- Product Filter Section -->
            <div class="favorites-product">
                <button type="button" class="favorites-filter" id="favorites-filter-btn" aria-expanded="false">
                    <span class="favorites-filter__label" id="filter-label"><?php esc_html_e('Product', 'ippgi'); ?></span>
                    <svg class="favorites-filter__arrow" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#333" stroke-width="3">
                        <polyline points="6 4 12 16 18 4"></polyline>
                    </svg>
                </button>

                <div class="favorites-list" id="favorites-list">
                    <?php if (!empty($favorites)) : ?>
                        <?php foreach ($favorites as $favorite) : ?>
                            <div class="favorites-item" data-type="<?php echo esc_attr($favorite['type'] ?? ''); ?>">
                                <span class="favorites-item__name"><?php echo esc_html($favorite['name']); ?></span>
                                <span class="favorites-item__spec"><?php echo esc_html($favorite['spec'] ?? ''); ?></span>
                                <button type="button" class="favorites-item__heart" data-price-id="<?php echo esc_attr($favorite['id']); ?>" aria-label="<?php esc_attr_e('Remove from favorites', 'ippgi'); ?>">
                                    <svg width="28" height="28" viewBox="0 0 24 24" fill="#333" stroke="#333" stroke-width="1">
                                        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                                    </svg>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <div class="favorites-empty">
                            <p><?php esc_html_e('No favorites yet. Start adding materials to your favorites to track them here.', 'ippgi'); ?></p>
                            <a href="<?php echo esc_url(home_url('/')); ?>" class="btn btn--primary">
                                <?php esc_html_e('Browse Prices', 'ippgi'); ?>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Material Type Selector (Bottom Sheet) -->
<div class="material-selector-backdrop" id="material-selector-backdrop"></div>
<div class="material-selector" id="material-selector" aria-hidden="true">
    <div class="material-selector__header">
        <h3 class="material-selector__title"><?php esc_html_e('Product', 'ippgi'); ?></h3>
    </div>
    <div class="material-selector__list">
        <?php foreach ($material_types as $type => $label) : ?>
            <button type="button" class="material-selector__item" data-type="<?php echo esc_attr($type); ?>">
                <span><?php echo esc_html($label); ?></span>
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#333" stroke-width="3">
                    <polyline points="7 4 16 12 7 20"></polyline>
                </svg>
            </button>
        <?php endforeach; ?>
    </div>
</div>

<script>
(function() {
    const filterBtn = document.getElementById('favorites-filter-btn');
    const filterLabel = document.getElementById('filter-label');
    const selector = document.getElementById('material-selector');
    const backdrop = document.getElementById('material-selector-backdrop');
    const favoritesList = document.getElementById('favorites-list');
    const items = selector.querySelectorAll('.material-selector__item');

    let currentFilter = null; // Track current filter state

    function openSelector() {
        selector.classList.add('is-active');
        backdrop.classList.add('is-active');
        filterBtn.setAttribute('aria-expanded', 'true');
        selector.setAttribute('aria-hidden', 'false');
    }

    function closeSelector() {
        selector.classList.remove('is-active');
        backdrop.classList.remove('is-active');
        filterBtn.setAttribute('aria-expanded', 'false');
        selector.setAttribute('aria-hidden', 'true');
    }

    function filterFavorites(type) {
        const favoriteItems = favoritesList.querySelectorAll('.favorites-item');
        favoriteItems.forEach(item => {
            if (type === null || item.dataset.type === type) {
                item.style.display = '';
            } else {
                item.style.display = 'none';
            }
        });
    }

    function resetToDefault() {
        currentFilter = null;
        filterLabel.textContent = '<?php esc_html_e('Product', 'ippgi'); ?>';
        items.forEach(i => i.classList.remove('is-active'));
        filterFavorites(null);
    }

    filterBtn?.addEventListener('click', openSelector);
    backdrop?.addEventListener('click', closeSelector);

    items.forEach(item => {
        item.addEventListener('click', function() {
            const type = this.dataset.type;
            const label = this.querySelector('span').textContent.trim();
            const isCurrentlyActive = this.classList.contains('is-active');

            if (isCurrentlyActive) {
                // Deselect - reset to default
                resetToDefault();
            } else {
                // Select this item
                items.forEach(i => i.classList.remove('is-active'));
                this.classList.add('is-active');
                currentFilter = type;
                filterLabel.textContent = label;
                filterFavorites(type);
            }

            // Close selector
            closeSelector();
        });
    });

    // Heart button click - toggle favorite
    favoritesList?.addEventListener('click', function(e) {
        const heartBtn = e.target.closest('.favorites-item__heart');
        if (!heartBtn) return;

        const priceId = heartBtn.dataset.priceId;
        const favoriteItem = heartBtn.closest('.favorites-item');

        // Send AJAX request to toggle favorite
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'ippgi_toggle_favorite',
                price_id: priceId,
                nonce: '<?php echo wp_create_nonce('ippgi_nonce'); ?>'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Remove item from list with animation
                favoriteItem.style.opacity = '0';
                favoriteItem.style.transform = 'translateX(100%)';
                favoriteItem.style.transition = 'all 0.3s ease';
                setTimeout(() => {
                    favoriteItem.remove();
                    // Check if list is empty
                    if (!favoritesList.querySelector('.favorites-item')) {
                        location.reload();
                    }
                }, 300);
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
    });
})();
</script>

<?php
get_footer();
