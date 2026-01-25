<?php
/**
 * Breadcrumb Navigation
 *
 * @package IPPGI
 * @since 1.0.0
 */

// Get breadcrumb items from template or generate automatically
$breadcrumb_items = isset($args['items']) ? $args['items'] : [];

// If no items provided, try to generate automatically
if (empty($breadcrumb_items)) {
    $breadcrumb_items[] = [
        'label' => __('Home', 'ippgi'),
        'url'   => home_url('/'),
    ];

    if (is_singular('post')) {
        $breadcrumb_items[] = [
            'label' => __('Market Insights', 'ippgi'),
            'url'   => get_permalink(get_option('page_for_posts')),
        ];
        $breadcrumb_items[] = [
            'label' => get_the_title(),
            'url'   => '',
        ];
    } elseif (is_page()) {
        $breadcrumb_items[] = [
            'label' => get_the_title(),
            'url'   => '',
        ];
    } elseif (is_archive()) {
        $breadcrumb_items[] = [
            'label' => get_the_archive_title(),
            'url'   => '',
        ];
    } elseif (is_search()) {
        $breadcrumb_items[] = [
            'label' => __('Search Results', 'ippgi'),
            'url'   => '',
        ];
    }
}
?>

<nav class="breadcrumb" aria-label="<?php esc_attr_e('Breadcrumb', 'ippgi'); ?>">
    <ol class="breadcrumb__list">
        <?php foreach ($breadcrumb_items as $index => $item) : ?>
            <li class="breadcrumb__item">
                <?php if (!empty($item['url']) && $index < count($breadcrumb_items) - 1) : ?>
                    <a href="<?php echo esc_url($item['url']); ?>" class="breadcrumb__link">
                        <?php echo esc_html($item['label']); ?>
                    </a>
                <?php else : ?>
                    <span class="breadcrumb__current" aria-current="page">
                        <?php echo esc_html($item['label']); ?>
                    </span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
</nav>
