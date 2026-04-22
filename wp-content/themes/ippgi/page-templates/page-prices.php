<?php
/**
 * Template Name: Prices Page
 *
 * @package IPPGI
 * @since 1.0.0
 */

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

$current_type = ippgi_normalize_product_type($current_type);

if (empty($current_type)) {
    $current_type = 'ppgi';
}

if (!ippgi_is_visible_product_type($current_type)) {
    wp_safe_redirect(add_query_arg('type', 'ppgi', home_url('/prices/')));
    exit;
}

$current_width = isset($_GET['width']) ? sanitize_text_field($_GET['width']) : '';

get_header();

// Product types with their display names
// Note: attributes_template uses %s placeholders for thickness and width ranges
// Note: widths are fallback only, actual widths come from cached price data
$product_types = [
    'ppgi' => [
        'name' => ippgi_get_product_display_name('ppgi'),
        'full_name' => 'Pre-painted Galvanized Iron',
        'widths' => [1000, 1200],
        'attributes_template' => 'Thickness: 0.11–0.80 mm; Width: 1000–1200 mm; Color: Blue, Grey (RAL Color); Coating Type: Polyester (PE); Surface: Color Coated; Coil ID: 508 mm / 610 mm; Coil Weight: 3–8 MT per coil; Application: Construction, Home Appliances.',
    ],
    'gi' => [
        'name' => ippgi_get_product_display_name('gi'),
        'full_name' => 'Galvanized Steel',
        'widths' => [1000, 1200, 1219, 1250],
        'attributes_template' => 'Thickness: 0.40–2.00 mm; Width: 1000–1250 mm; Surface: Regular Spangle / Small Spangle; Coil ID: 508 mm / 610 mm; Coil Weight: 5–8 MT per coil; Applications: Construction, Home Appliances, Automotive.',
    ],
    'gl' => [
        'name' => ippgi_get_product_display_name('gl'),
        'full_name' => 'Galvalume Steel',
        'widths' => [1000, 1200],
        'attributes_template' => 'Thickness: 0.13–0.60 mm; Width: 1000–1200 mm; Coating Weight: AZ20–AZ170 g/m² (55% Al, 43.4% Zn, 1.6% Si); Surface Treatment: Anti-fingerprint / Passivation; Coil ID: 508 mm; Coil Weight: 3–5 MT per coil; Applications: Construction Materials, Home Appliances.',
    ],
    'crc' => [
        'name' => ippgi_get_product_display_name('crc'),
        'full_name' => 'Cold Rolled Hard Coil',
        'widths' => [1000, 1200],
        'attributes_template' => 'Thickness: 0.13–0.60 mm; Width: 1000–1200 mm; Steel Grade: SPCC, DC01, ST12; Surface: Bright; Hardness: ≥100 HB (Brinell); Coil ID: 508 mm / 610 mm; Coil Weight: 15–22 MT per coil; Applications: Automotive parts, metal fabrication.',
    ],
    'aluminum' => [
        'name' => ippgi_get_product_display_name('aluminum'),
        'full_name' => 'Aluminum Sheet',
        'widths' => [1000],
        'attributes_template' => 'Thickness: 0.25–0.41 mm; Width: 1000 mm; Alloy / Temper: 1060H18, 1060H24, 1100H24, 3003H24, 3004H24; Surface: Bright; Coil ID: 508 mm / 610 mm; Coil Weight: 1–3 MT per coil; Applications: Construction materials, roofing, cladding.',
    ],
];

// Get current product info
$current_product = $product_types[$current_type] ?? $product_types['ppgi'];

// Get cached price data for current product type
$category_mapping = [
    'ppgi'     => 'PPGI',
    'gi'       => 'GI',
    'gl'       => 'GL',
    'crc'      => 'CRC Hard',
    'aluminum' => 'AL',
];
$category_name = $category_mapping[$current_type] ?? 'PPGI';
$cache_manager = function_exists('ippgi_prices') ? ippgi_prices()->cache_manager : null;
$cached_data = $cache_manager ? $cache_manager->get_category_price_list($category_name) : false;
$category_prices = [];
$fetched_at = ippgi_get_latest_prices_fetched_at();

if ($cached_data && isset($cached_data['result'])) {
    $result = $cached_data['result'];
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
                    'price_min'    => $item['lastprice_range_min_usd'] ?? ($item['price_range_min_usd'] ?? 0),
                    'price_max'    => $item['lastprice_range_max_usd'] ?? ($item['price_range_max_usd'] ?? 0),
                    'price_tax_min'=> $item['lastpriceTax_range_min_usd'] ?? ($item['priceTax_range_min_usd'] ?? 0),
                    'price_tax_max'=> $item['lastpriceTax_range_max_usd'] ?? ($item['priceTax_range_max_usd'] ?? 0),
                    'trend'        => $item['lastprice_range_direction_usd'] ?? 'neutral',
                    'trend_tax'    => $item['lastpriceTax_range_direction_usd'] ?? 'neutral',
                    'change'       => $item['riseAndFall_usd'] ?? 0,
                    'change_tax'   => $item['riseAndFallTax_usd'] ?? 0,
                    'product_spec' => $item['productSpec'] ?? '',
                ];
            }
        }
        $category_prices[$width] = $width_items;
    }
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

// Generate attributes text for current product type.
$attributes_text = $current_product['attributes_template'];
$attributes_html = preg_replace(
    '/(^|;\s)([^:;]+:)/u',
    '$1<strong class="key-attributes__label">$2</strong>',
    $attributes_text
);
$attributes_html = str_replace(
    '55% Al, 43.4% Zn, 1.6% Si',
    '<strong class="key-attributes__label">55% Al, 43.4% Zn, 1.6% Si</strong>',
    $attributes_html
);
?>

<main id="main-content" class="site-main">
    <div class="container">
        <!-- Page Header -->
        <header class="prices-page-header">
            <h1 class="prices-page-header__title">
                <?php esc_html_e('Price charts and tables of China steel and commodities', 'ippgi'); ?>
            </h1>
            <p class="prices-page-header__disclaimer">
                <?php esc_html_e('Prices are quoted on an ex works (EXW) basis in China and exclude freight costs.', 'ippgi'); ?>
            </p>
        </header>

        <!-- Product Selector -->
        <div class="product-selector">
            <button type="button" class="product-selector__trigger" id="product-selector-trigger" aria-haspopup="listbox" aria-expanded="false">
                <span class="product-selector__label"><?php esc_html_e('Product', 'ippgi'); ?></span>
                <svg class="product-selector__arrow" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#333" stroke-width="3">
                    <polyline points="6 4 12 16 18 4"></polyline>
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
                <?php echo wp_kses($attributes_html, ['strong' => ['class' => []]]); ?>
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
                    ippgi_format_prices_fetched_at($fetched_at, 'M d, Y, h:i A')
                );
                ?>
            </div>
        </div>

        <!-- Price Table -->
        <div class="prices-table-section">
            <div class="prices-table-wrapper" id="prices-table-container">
                <table class="prices-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Dimensions(mm)', 'ippgi'); ?></th>
                            <th><?php esc_html_e('Latest($)', 'ippgi'); ?></th>
                            <th><?php esc_html_e('Historical', 'ippgi'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="prices-table-body">
                        <tr>
                            <td colspan="3" class="prices-table__loading">
                                <div class="spinner"></div>
                                <span><?php esc_html_e('Loading prices...', 'ippgi'); ?></span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Disclaimer -->
        <div class="prices-disclaimer">
            <p class="prices-disclaimer__text">
                <strong class="prices-disclaimer__label"><?php esc_html_e('Disclaimer:', 'ippgi'); ?></strong>
                <?php esc_html_e('iPPGI strives to provide accurate and objective data, information, and opinions; however, we make no representations or warranties regarding their accuracy, completeness, or timeliness. Prices are derived from multiple market sources, including public market data, supplier quotations, and internal estimation models. All information is for informational purposes only and does not constitute financial, investment, trading, or professional advice.', 'ippgi'); ?>
                <?php esc_html_e('Prices are subject to change without notice.', 'ippgi'); ?>
                <?php esc_html_e('Users should exercise independent judgment and conduct their own due diligence; iPPGI shall not be held liable for any loss or damage arising from the use of this information. All content is the exclusive intellectual property of iPPGI. Any unauthorized reproduction, distribution, or copying without prior written consent is strictly prohibited. iPPGI reserves all rights to pursue legal action for any infringement.', 'ippgi'); ?>
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
