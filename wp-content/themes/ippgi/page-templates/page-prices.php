<?php
/**
 * Template Name: Prices Page
 *
 * @package IPPGI
 * @since 1.0.0
 */

get_header();

// Get filter from URL
// Support both ?type=ppgi and ?category=PPGI (from homepage redirect)
$current_type = '';
if (isset($_GET['type'])) {
    $current_type = sanitize_text_field($_GET['type']);
} elseif (isset($_GET['category'])) {
    // Map category name (e.g. "GI", "PPGI", "CRC Hard") to type key (e.g. "gi", "ppgi", "crc")
    $category_to_type = [
        'PPGI'     => 'ppgi',
        'GI'       => 'gi',
        'GL'       => 'gl',
        'HRC'      => 'hrc',
        'CRC Hard' => 'crc',
        'CRC_HARD' => 'crc',
        'AL'       => 'aluminum',
    ];
    $raw_category = sanitize_text_field($_GET['category']);
    $current_type = $category_to_type[$raw_category] ?? strtolower($raw_category);
}
if (empty($current_type)) {
    $current_type = 'ppgi';
}
$current_width = isset($_GET['width']) ? sanitize_text_field($_GET['width']) : '';

// Product types with their display names
// Note: attributes_template uses %s placeholders for thickness and width ranges
// Note: widths are fallback only, actual widths come from cached price data
$product_types = [
    'ppgi' => [
        'name' => ippgi_get_product_display_name('ppgi'),
        'full_name' => 'Pre-painted Galvanized Iron',
        'widths' => [1000, 1200],
        'attributes_template' => 'Thickness: %s; Width: %s; Coating Weight: Z80-180g/m2 (for PPGI) or AZ50-150g/m2 (for PPGL); Coating Type: PE/PVDF, 10-25um topcoat + 5-10um primer; Grade: DX51D; Surface: Color coated (RAL colors), 2/1 coating structure; Coil ID: 508mm; Weight: 3-8 tons/coil. Applications: Roofing, cladding.',
    ],
    'gi' => [
        'name' => ippgi_get_product_display_name('gi'),
        'full_name' => 'Galvanized Steel',
        'widths' => [1000, 1200, 1219, 1250],
        'attributes_template' => 'Thickness: %s; Width: %s; Coating Weight: Z40-275g/m2; Grade: DX51D+Z, SGCC; Surface: Regular spangle, minimum spangle, zero spangle; Coil ID: 508mm; Weight: 3-8 tons/coil. Applications: Construction, appliances, automotive.',
    ],
    'gl' => [
        'name' => ippgi_get_product_display_name('gl'),
        'full_name' => 'Galvalume Steel',
        'widths' => [1000, 1200],
        'attributes_template' => 'Thickness: %s; Width: %s; Coating Weight: AZ50-150g/m2 (55%% Al, 43.4%% Zn, 1.6%% Si); Grade: DX51D+AZ; Surface: Anti-fingerprint available; Coil ID: 508mm; Weight: 3-8 tons/coil. Applications: Roofing, cladding, HVAC.',
    ],
    'hrc' => [
        'name' => ippgi_get_product_display_name('hrc'),
        'full_name' => 'Hot Rolled Coil',
        'widths' => [1010, 1500],
        'attributes_template' => 'Thickness: %s; Width: %s; Grade: Q235B, SS400, SPHC; Surface: Pickled and oiled (P&O) available; Coil ID: 508/610mm; Weight: 5-25 tons/coil. Applications: Structural, machinery, tubes.',
    ],
    'crc' => [
        'name' => ippgi_get_product_display_name('crc'),
        'full_name' => 'Cold Rolled Hard Coil',
        'widths' => [1000, 1200],
        'attributes_template' => 'Thickness: %s; Width: %s; Grade: SPCC, DC01, ST12; Surface: Bright finish; Hardness: Full hard; Coil ID: 508mm; Weight: 3-8 tons/coil. Applications: Forming, stamping, appliances.',
    ],
    'aluminum' => [
        'name' => ippgi_get_product_display_name('aluminum'),
        'full_name' => 'Aluminum Sheet',
        'widths' => [1000],
        'attributes_template' => 'Thickness: %s; Width: %s; Alloy: 1050, 1060, 1100, 3003, 5052; Temper: H14, H24, O; Surface: Mill finish, brushed; Coil ID: 508mm; Weight: 1-3 tons/coil. Applications: Decoration, insulation, packaging.',
    ],
];

// Get current product info
$current_product = $product_types[$current_type] ?? $product_types['ppgi'];

// Get cached price data for current product type
$category_mapping = [
    'ppgi'     => 'PPGI',
    'gi'       => 'GI',
    'gl'       => 'GL',
    'hrc'      => 'HRC',
    'crc'      => 'CRC Hard',
    'aluminum' => 'AL',
];
$category_name = $category_mapping[$current_type] ?? 'PPGI';
$cached_data = get_transient('ippgi_prices_price_list');
$category_prices = [];
$fetched_at = '';

if ($cached_data && isset($cached_data['categories'][$category_name]['result'])) {
    $result = $cached_data['categories'][$category_name]['result'];
    // result: { "1000": [ {thickness, price_usd, ...}, ... ], "1200": [...] }
    foreach ($result as $width => $items) {
        $width_items = [];
        if (is_array($items)) {
            foreach ($items as $item) {
                $width_items[] = [
                    'thickness'    => $item['thickness'] ?? '',
                    'width'        => $width,
                    'dimensions'   => ($item['thickness'] ?? '') . '*' . $width,
                    // Keep /prices "Latest" aligned with homepage: use Rendui lastprice fields.
                    'price'        => $item['lastprice_usd'] ?? ($item['price_usd'] ?? 0),
                    'price_tax'    => $item['lastpriceTax_usd'] ?? ($item['priceTax_usd'] ?? 0),
                    'change'       => $item['riseAndFall_usd'] ?? 0,
                    'change_tax'   => $item['riseAndFallTax_usd'] ?? 0,
                    'product_spec' => $item['productSpec'] ?? '',
                ];
            }
        }
        $category_prices[$width] = $width_items;
    }
    $fetched_at = $cached_data['fetched_at'] ?? '';
}

// Derive available widths from actual cached data (keys of category_prices)
// Fall back to hardcoded widths only if cache is empty
if (!empty($category_prices)) {
    $available_widths = array_map('intval', array_keys($category_prices));
    sort($available_widths);
} else {
    $available_widths = $current_product['widths'];
}

// Set default width if not specified or invalid
if (empty($current_width) || !in_array((int)$current_width, $available_widths)) {
    $current_width = (string)$available_widths[0];
}

// Get dynamic dimensions range from cached price list
$dimensions_range = ippgi_get_product_dimensions_range($current_type);
$formatted_range = ippgi_format_dimensions_range($dimensions_range);

// Generate attributes text with dynamic thickness and width
$attributes_text = sprintf(
    $current_product['attributes_template'],
    $formatted_range['thickness'],
    $formatted_range['width']
);
?>

<main id="main-content" class="site-main">
    <div class="container">
        <!-- Page Header -->
        <header class="prices-page-header">
            <h1 class="prices-page-header__title">
                <?php
                printf(
                    /* translators: %s: product type name (e.g., PPGI, GI, GL) */
                    esc_html__('Price charts and tables of China %s and commodities', 'ippgi'),
                    esc_html($current_product['name'])
                );
                ?>
            </h1>
            <p class="prices-page-header__disclaimer">
                <?php esc_html_e('*These prices reflect the transaction prices within China and do not include shipping costs.', 'ippgi'); ?>
            </p>
        </header>

        <!-- Product Selector -->
        <div class="product-selector">
            <button type="button" class="product-selector__trigger" id="product-selector-trigger" aria-haspopup="listbox" aria-expanded="false">
                <span class="product-selector__label"><?php esc_html_e('Product', 'ippgi'); ?></span>
                <svg class="product-selector__arrow" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="6 9 12 15 18 9"></polyline>
                </svg>
            </button>

            <div class="product-selector__dropdown" id="product-selector-dropdown" role="listbox" aria-label="<?php esc_attr_e('Select product type', 'ippgi'); ?>">
                <?php foreach ($product_types as $type_key => $type_info) : ?>
                <button type="button"
                        class="product-selector__option <?php echo $type_key === $current_type ? 'is-selected' : ''; ?>"
                        data-type="<?php echo esc_attr($type_key); ?>"
                        role="option"
                        aria-selected="<?php echo $type_key === $current_type ? 'true' : 'false'; ?>">
                    <?php echo esc_html($type_info['name']); ?>
                    <svg class="product-selector__check" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                </button>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Selected Product Display -->
        <div class="selected-product">
            <span class="selected-product__name" id="selected-product-name"><?php echo esc_html($current_product['name']); ?></span>
        </div>

        <!-- Key Attributes -->
        <div class="key-attributes">
            <h3 class="key-attributes__title"><?php esc_html_e('Key attributes', 'ippgi'); ?></h3>
            <p class="key-attributes__text" id="key-attributes-text">
                <?php echo esc_html($attributes_text); ?>
            </p>
        </div>

        <!-- Width Filter Tabs -->
        <div class="width-filter" id="width-filter">
            <div class="width-filter__scroll">
                <div class="width-filter__track">
                    <?php foreach ($available_widths as $width) : ?>
                    <button type="button"
                            class="width-filter__tab <?php echo (int)$current_width === $width ? 'is-active' : ''; ?>"
                            data-width="<?php echo esc_attr($width); ?>">
                        <?php echo esc_html($width); ?>mm
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Update Info & Tax Toggle -->
        <div class="price-controls">
            <div class="price-controls__updated" id="prices-updated">
                <?php
                printf(
                    /* translators: %s: date and time */
                    esc_html__('Updated: %s (UTC+8)', 'ippgi'),
                    date_i18n('M d, Y, h:i A')
                );
                ?>
            </div>
            <label class="price-controls__toggle">
                <input type="checkbox" id="tax-inclusive-toggle" class="price-controls__checkbox">
                <span class="price-controls__toggle-indicator"></span>
                <span class="price-controls__toggle-text"><?php esc_html_e('Tax-inclusive price', 'ippgi'); ?></span>
            </label>
        </div>

        <!-- Price Table -->
        <div class="prices-table-section">
            <div class="prices-table-wrapper" id="prices-table-container">
                <table class="prices-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Dimensions(mm)', 'ippgi'); ?></th>
                            <th><?php esc_html_e('Latest($)', 'ippgi'); ?></th>
                            <th><?php esc_html_e('Change($)', 'ippgi'); ?></th>
                            <th><?php esc_html_e('Historical', 'ippgi'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="prices-table-body">
                        <tr>
                            <td colspan="4" class="prices-table__loading">
                                <div class="spinner"></div>
                                <span><?php esc_html_e('Loading prices...', 'ippgi'); ?></span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if (!ippgi_user_can_view_history()) : ?>
        <!-- Upgrade Prompt for Non-Premium Users -->
        <div class="premium-gate">
            <div class="premium-gate__content">
                <svg class="premium-gate__icon" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                </svg>
                <h3 class="premium-gate__title"><?php esc_html_e('Unlock Price History', 'ippgi'); ?></h3>
                <p class="premium-gate__text">
                    <?php esc_html_e('Subscribe to IPPGI Plus to access complete historical price data, interactive charts, and trend analysis.', 'ippgi'); ?>
                </p>
                <a href="<?php echo esc_url(home_url('/subscribe')); ?>" class="btn btn--primary">
                    <?php esc_html_e('Upgrade to Plus', 'ippgi'); ?>
                </a>
                <?php if (!is_user_logged_in()) : ?>
                    <p class="premium-gate__login">
                        <?php esc_html_e('Already have an account?', 'ippgi'); ?>
                        <a href="<?php echo esc_url(ippgi_get_login_url()); ?>"><?php esc_html_e('Log in', 'ippgi'); ?></a>
                    </p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Disclaimer -->
        <div class="prices-disclaimer">
            <p class="prices-disclaimer__text">
                <strong class="prices-disclaimer__label"><?php esc_html_e('Disclaimer:', 'ippgi'); ?></strong>
                <?php
                $product_name = esc_html($current_product['name']);
                printf(
                    /* translators: %s: product type name (e.g., PPGI, GI, GL) */
                    esc_html__('%1$s Price strives to provide accurate and objective data, information, and opinions but does not guarantee its completeness or the need for updates. The information is intended to assist customers in decision-making, not as direct advice. Customers should rely on their own judgment, and %1$s Price is not liable for any consequences arising from the use of this data. This report is the copyrighted property of %1$s Price and is for the exclusive use of its customers. Sharing, publishing, or copying this report without %1$s Price\'s permission is strictly prohibited. %1$s Price reserves the right to pursue legal action for any copyright infringement or misuse of this report.', 'ippgi'),
                    $product_name
                );
                ?>
            </p>
        </div>
    </div>
</main>

<script>
// Prices page data (from cached price list)
window.ippgiPricesPage = {
    productTypes: <?php echo json_encode($product_types); ?>,
    currentType: <?php echo json_encode($current_type); ?>,
    currentWidth: <?php echo json_encode((string)$current_width); ?>,
    canViewHistory: <?php echo json_encode(ippgi_user_can_view_history()); ?>,
    categoryPrices: <?php echo json_encode($category_prices); ?>,
    fetchedAt: <?php echo json_encode($fetched_at); ?>
};
</script>

<?php
get_footer();
