<?php
/**
 * Template Name: Price Detail Page
 *
 * @package IPPGI
 * @since 1.0.0
 */

// Get parameters from URL
// Support both ?type=ppgi&spec=0.11*1000 (from prices page) and ?material=gi (legacy)
$material_code = '';
if (isset($_GET['type'])) {
    $material_code = sanitize_text_field($_GET['type']);
} elseif (isset($_GET['material'])) {
    $material_code = sanitize_text_field($_GET['material']);
}
if (empty($material_code)) {
    $material_code = 'ppgi';
}

$material_code = ippgi_normalize_product_type($material_code);

$product_spec = isset($_GET['spec']) ? sanitize_text_field($_GET['spec']) : '';

// Material data mapping
// 'code' = display name (customizable), 'api_category' = fixed API category name
$materials = [
    'gi'       => ['name' => __('Galvanized Steel', 'ippgi'), 'code' => ippgi_get_product_display_name('gi'), 'api_category' => 'GI'],
    'gl'       => ['name' => __('Galvalume Steel', 'ippgi'), 'code' => ippgi_get_product_display_name('gl'), 'api_category' => 'GL'],
    'ppgi'     => ['name' => __('Pre-painted Galvanized Iron', 'ippgi'), 'code' => ippgi_get_product_display_name('ppgi'), 'api_category' => 'PPGI'],
    'crc'      => ['name' => __('Cold Rolled Hard Coil', 'ippgi'), 'code' => ippgi_get_product_display_name('crc'), 'api_category' => 'CRC Hard'],
    'aluminum' => ['name' => __('Aluminum Sheet', 'ippgi'), 'code' => ippgi_get_product_display_name('aluminum'), 'api_category' => 'AL'],
];

if (!isset($materials[$material_code]) || !ippgi_is_visible_product_type($material_code)) {
    global $wp_query;
    $wp_query->set_404();
    status_header(404);
    nocache_headers();
    require get_query_template('404');
    exit;
}

get_header();

$current_material = $materials[$material_code];

// Category ID mapping
$category_id_mapping = [
    'ppgi'     => '1482328115005964290',
    'gi'       => '1457211766760558593',
    'gl'       => '1683315093109178369',
    'crc'      => '1457211766760558594',
    'aluminum' => '1457211893311098881',
];
$category_id = $category_id_mapping[$material_code] ?? '';

// Parse productSpec to extract width, thickness, and product name
// Format: "categoryId_width_thickness_材料名称"
$matched_thickness = '';
$matched_width = '';
$product_name_from_spec = '';
if ($product_spec) {
    $spec_parts = explode('_', $product_spec);
    if (count($spec_parts) >= 4) {
        $matched_width = $spec_parts[1];
        $matched_thickness = $spec_parts[2];
        $product_name_from_spec = end($spec_parts);
    }
}

// Build display dimensions
// Only show product name for AL (it has consistent naming)
// For PPGI, GI, GL, CRC - only show dimensions (they have mixed Chinese/English names)
$short_spec = $matched_thickness && $matched_width ? ($matched_thickness . '*' . $matched_width) : $product_spec;
$display_dimensions = $short_spec;
if ($product_name_from_spec && in_array($material_code, ['aluminum'], true)) {
    $display_dimensions = $short_spec . ' ' . $product_name_from_spec;
}

// Map material_code to the favorite type key used by ippgi_get_user_favorites()
$favorite_type_mapping = [
    'ppgi'     => 'ppgi',
    'gi'       => 'gi',
    'gl'       => 'gl',
    'crc'      => 'crc_hard',
    'aluminum' => 'al',
];
$favorite_type = $favorite_type_mapping[$material_code] ?? $material_code;

// Favorite ID format: type-productSpec (e.g., "ppgi-1482328115005964290_1000_0.11_彩涂")
// Use full $product_spec as unique identifier
$favorite_id = $product_spec ? ($favorite_type . '-' . $product_spec) : $favorite_type;

$format_detail_price_range = static function($min, $max, $fallback = null) {
    $min_num = is_numeric($min) ? (float) $min : 0;
    $max_num = is_numeric($max) ? (float) $max : 0;
    $fallback_num = is_numeric($fallback) ? (float) $fallback : 0;

    $format_number = static function($value) {
        return number_format((float) $value, 2, '.', ',');
    };

    if ($min_num > 0 && $max_num > 0) {
        return '$' . $format_number($min_num) . '~$' . $format_number($max_num);
    }

    if ($fallback_num > 0) {
        return '$' . $format_number($fallback_num);
    }

    return '--';
};

$detail_price_display = '--';
$cache_manager = function_exists('ippgi_prices') ? ippgi_prices()->cache_manager : null;
$cached_category_data = $cache_manager ? $cache_manager->get_category_price_list($current_material['api_category']) : false;

if ($cached_category_data && isset($cached_category_data['result']) && is_array($cached_category_data['result'])) {
    foreach ($cached_category_data['result'] as $width => $items) {
        if (!is_array($items)) {
            continue;
        }

        foreach ($items as $item) {
            $candidate_spec = isset($item['productSpec']) ? sanitize_text_field($item['productSpec']) : '';
            $candidate_dimensions = trim((string) ($item['thickness'] ?? '')) . '*' . trim((string) $width);
            $is_matching_spec = $product_spec !== '' && $candidate_spec === $product_spec;
            $is_matching_dimensions = $product_spec === '' && $short_spec !== '' && $candidate_dimensions === $short_spec;

            if (!$is_matching_spec && !$is_matching_dimensions) {
                continue;
            }

            $detail_price_display = $format_detail_price_range(
                $item['lastpriceTax_range_min_usd'] ?? ($item['priceTax_range_min_usd'] ?? 0),
                $item['lastpriceTax_range_max_usd'] ?? ($item['priceTax_range_max_usd'] ?? 0),
                $item['lastpriceTax_usd'] ?? ($item['priceTax_usd'] ?? 0)
            );

            break 2;
        }
    }
}

// Check if this product is already in user's favorites
$is_favorited = false;
if (is_user_logged_in()) {
    $user_favorites = get_user_meta(get_current_user_id(), 'ippgi_favorites', true);
    if (is_array($user_favorites) && in_array($favorite_id, $user_favorites, true)) {
        $is_favorited = true;
    }
}
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

        <!-- Product Info Table -->
        <div class="detail-product-info">
            <table class="detail-product-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Product', 'ippgi'); ?></th>
                        <th><?php esc_html_e('Dimensions(mm)', 'ippgi'); ?></th>
                        <th><?php esc_html_e('Favorite', 'ippgi'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="detail-product-table__product"><?php echo esc_html($current_material['code']); ?></td>
                        <td><?php echo esc_html($display_dimensions); ?></td>
                        <td>
                            <?php if (is_user_logged_in()) : ?>
                            <button type="button" class="favorite-btn <?php echo $is_favorited ? 'is-active' : ''; ?>" data-price-id="<?php echo esc_attr($favorite_id); ?>" aria-label="<?php esc_attr_e('Toggle favorite', 'ippgi'); ?>">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="<?php echo $is_favorited ? 'currentColor' : 'none'; ?>" stroke="currentColor" stroke-width="2">
                                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                                </svg>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Real-Time Data Section -->
        <div class="detail-realtime">
            <div class="detail-realtime__header">
                <span class="detail-realtime__label"><?php esc_html_e('Real-Time Price', 'ippgi'); ?></span>
            </div>

            <div class="detail-realtime__price-row">
                <div class="detail-realtime__main-price">
                    <span class="detail-realtime__value" id="detail-price"><?php echo esc_html($detail_price_display); ?></span>
                </div>
            </div>
        </div>

        <!-- Price Chart Section -->
        <div class="detail-chart-section">
            <!-- Date Range Picker -->
            <div class="detail-chart__date-picker" id="detail-date-picker">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                    <!-- Calendar body outline -->
                    <rect x="2" y="4" width="20" height="18" rx="2" stroke="#333" stroke-width="1.5" fill="none"/>
                    <!-- Calendar header (solid black) -->
                    <rect x="2" y="4" width="20" height="5" rx="2" fill="#333"/>
                    <rect x="2" y="7" width="20" height="2" fill="#333"/>
                    <!-- Calendar pins (white hollow) -->
                    <rect x="6" y="1" width="3" height="5" rx="1.5" fill="#fff" stroke="#333" stroke-width="1"/>
                    <rect x="15" y="1" width="3" height="5" rx="1.5" fill="#fff" stroke="#333" stroke-width="1"/>
                    <!-- Horizontal grid lines -->
                    <line x1="2" y1="13" x2="22" y2="13" stroke="#333" stroke-width="1"/>
                    <line x1="2" y1="17" x2="22" y2="17" stroke="#333" stroke-width="1"/>
                    <!-- Vertical grid lines -->
                    <line x1="9" y1="9" x2="9" y2="22" stroke="#333" stroke-width="1"/>
                    <line x1="15" y1="9" x2="15" y2="22" stroke="#333" stroke-width="1"/>
                </svg>
                <span><?php esc_html_e('Start Date ~ End Date', 'ippgi'); ?></span>
            </div>

            <!-- Time Range Tabs -->
            <div class="detail-chart__range-tabs">
                <div class="detail-chart__range-track" id="detail-range-track">
                    <div class="detail-chart__range-slider" id="detail-range-slider"></div>
                    <button type="button" class="detail-chart__range-btn is-active" data-range="7d">7D</button>
                    <button type="button" class="detail-chart__range-btn" data-range="15d">15D</button>
                    <button type="button" class="detail-chart__range-btn" data-range="30d">1M</button>
                </div>
            </div>

            <!-- Chart Title -->
            <div class="detail-chart__title">
                <?php echo esc_html($current_material['code']); ?> Dimensions(mm):<?php echo esc_html($display_dimensions); ?>
            </div>

            <!-- Chart Container -->
            <div class="detail-chart__container">
                <!-- Y-Axis Labels -->
                <div class="detail-chart__y-axis" id="detail-chart-y-axis">
                    <span>4710</span>
                    <span>4690</span>
                    <span>4670</span>
                    <span>4650</span>
                    <span>4630</span>
                    <span>4610</span>
                    <span>4590</span>
                </div>

                <!-- Chart Area with Grid -->
                <div class="detail-chart__main">
                    <div class="detail-chart__area" id="detail-chart">
                        <div class="detail-chart__grid">
                            <div class="detail-chart__grid-line"></div>
                            <div class="detail-chart__grid-line"></div>
                            <div class="detail-chart__grid-line"></div>
                            <div class="detail-chart__grid-line"></div>
                            <div class="detail-chart__grid-line"></div>
                            <div class="detail-chart__grid-line"></div>
                            <div class="detail-chart__grid-line"></div>
                        </div>
                        <canvas id="detail-chart-canvas"></canvas>
                        <div class="detail-chart__balloons" id="detail-chart-balloons"></div>
                        <!-- Touch Crosshair -->
                        <div class="chart-crosshair" id="chart-crosshair">
                            <div class="chart-crosshair__dot chart-crosshair__dot--lower" id="chart-crosshair-dot-lower"></div>
                            <div class="chart-crosshair__dot chart-crosshair__dot--upper" id="chart-crosshair-dot-upper"></div>
                        </div>
                        <!-- Crosshair Info Box -->
                        <div class="chart-infobox" id="chart-infobox">
                            <div class="chart-infobox__time" id="chart-infobox-time"></div>
                            <div class="chart-infobox__row">
                                <span class="chart-infobox__name" id="chart-infobox-name"></span>
                                <span class="chart-infobox__spec" id="chart-infobox-spec"></span>
                                <span class="chart-infobox__price" id="chart-infobox-price"></span>
                            </div>
                        </div>
                        <div class="detail-chart__placeholder" id="detail-chart-placeholder">
                            <p><?php esc_html_e('Loading chart data...', 'ippgi'); ?></p>
                        </div>
                    </div>

                    <!-- X-Axis Labels -->
                    <div class="detail-chart__x-axis" id="detail-chart-x-axis">
                        <span>09:00</span>
                        <span>10:00</span>
                        <span>11:00</span>
                        <span>12:00</span>
                        <span>13:00</span>
                        <span>14:00</span>
                        <span>15:00</span>
                        <span>16:00</span>
                        <span>17:00</span>
                        <span>18:00</span>
                    </div>
                </div>
            </div>
        </div>

        <?php
        $quote_product_interest = trim($current_material['code'] . ' ' . $display_dimensions);
        ?>
        <section class="quote-card quote-card--price-detail" aria-labelledby="quote-card-title">
            <h2 id="quote-card-title" class="quote-card__title"><?php esc_html_e('Request a Quote', 'ippgi'); ?></h2>
            <p class="quote-card__description">
                <?php esc_html_e('Submit your sourcing request and get free access to the latest market pricing for steel coils, aluminum coils, roofing sheets, plate sheets, and wall panels.', 'ippgi'); ?><br>
                <?php esc_html_e('We provide timely pricing insights to support your procurement decisions.', 'ippgi'); ?>
            </p>

            <?php
            get_template_part('template-parts/quote-request-form', null, array(
                'form_id' => 'quote-request-form-detail',
                'source' => 'price_detail',
                'product_interest' => $quote_product_interest,
            ));
            ?>
        </section>

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

<!-- Date Picker Bottom Sheet -->
<div class="date-picker-backdrop" id="detail-date-picker-backdrop"></div>
<div class="date-picker-sheet" id="detail-date-picker-sheet" data-current-year="<?php echo date('Y'); ?>" data-current-month="<?php echo date('n'); ?>">
    <div class="date-picker-sheet__header">
        <span class="date-picker-sheet__title"><?php esc_html_e('Select Date Range', 'ippgi'); ?></span>
        <button type="button" class="date-picker-sheet__close" id="detail-date-picker-close" aria-label="<?php esc_attr_e('Close', 'ippgi'); ?>">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        </button>
    </div>
    <div class="date-picker-sheet__range">
        <div class="date-picker-sheet__range-item">
            <span class="date-picker-sheet__range-label"><?php esc_html_e('Start', 'ippgi'); ?></span>
            <span class="date-picker-sheet__range-value" id="detail-date-range-start">--</span>
        </div>
        <span class="date-picker-sheet__range-separator">~</span>
        <div class="date-picker-sheet__range-item">
            <span class="date-picker-sheet__range-label"><?php esc_html_e('End', 'ippgi'); ?></span>
            <span class="date-picker-sheet__range-value" id="detail-date-range-end">--</span>
        </div>
    </div>
    <div class="date-picker-sheet__body">
        <div class="date-picker-sheet__nav">
            <button type="button" class="date-picker-sheet__nav-btn" id="detail-date-picker-prev" aria-label="<?php esc_attr_e('Previous month', 'ippgi'); ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="15 18 9 12 15 6"></polyline>
                </svg>
            </button>
            <span class="date-picker-sheet__month" id="detail-date-picker-month"><?php echo esc_html(date_i18n('F Y')); ?></span>
            <button type="button" class="date-picker-sheet__nav-btn" id="detail-date-picker-next" aria-label="<?php esc_attr_e('Next month', 'ippgi'); ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
            </button>
        </div>
        <div class="date-picker-sheet__weekdays">
            <span class="date-picker-sheet__weekday"><?php esc_html_e('S', 'ippgi'); ?></span>
            <span class="date-picker-sheet__weekday"><?php esc_html_e('M', 'ippgi'); ?></span>
            <span class="date-picker-sheet__weekday"><?php esc_html_e('T', 'ippgi'); ?></span>
            <span class="date-picker-sheet__weekday"><?php esc_html_e('W', 'ippgi'); ?></span>
            <span class="date-picker-sheet__weekday"><?php esc_html_e('T', 'ippgi'); ?></span>
            <span class="date-picker-sheet__weekday"><?php esc_html_e('F', 'ippgi'); ?></span>
            <span class="date-picker-sheet__weekday"><?php esc_html_e('S', 'ippgi'); ?></span>
        </div>
        <div class="date-picker-sheet__days" id="detail-date-picker-days">
            <!-- Days will be generated by JavaScript -->
        </div>
    </div>
    <div class="date-picker-sheet__footer">
        <button type="button" class="date-picker-sheet__btn date-picker-sheet__btn--clear" id="detail-date-picker-clear">
            <?php esc_html_e('Clear', 'ippgi'); ?>
        </button>
        <button type="button" class="date-picker-sheet__btn date-picker-sheet__btn--confirm" id="detail-date-picker-confirm">
            <?php esc_html_e('Confirm', 'ippgi'); ?>
        </button>
    </div>
</div>

<script>
window.ippgiPriceDetail = {
    materialCode: <?php echo json_encode($material_code); ?>,
    productSpec: <?php echo json_encode($product_spec); ?>,
    categoryId: <?php echo json_encode($category_id); ?>,
    materialName: <?php echo json_encode($current_material['code']); ?>,
    category: <?php echo json_encode($current_material['api_category']); ?>,
    canViewHistory: <?php echo json_encode(ippgi_user_can_view_history()); ?>
};

// Fetch real-time price data from server
(function() {
    var detail = window.ippgiPriceDetail;
    if (!detail.productSpec || !detail.categoryId) return;

    var restUrl = <?php echo json_encode(rest_url('ippgi-prices/v1/')); ?>;

    // Format price with 2 decimal places
    function formatPrice(num) {
        if (typeof num !== 'number' || isNaN(num)) return '0.00';
        return num.toFixed(2);
    }

    // ========== Chart Functions ==========

    // Get current Beijing time info
    function getBeijingTime() {
        var now = new Date();
        var utcTime = now.getTime() + now.getTimezoneOffset() * 60 * 1000;
        var beijingTime = new Date(utcTime + 8 * 60 * 60 * 1000);
        return beijingTime;
    }

    // Check if current Beijing time is 9:00 or later
    function isAfter9AM() {
        var beijing = getBeijingTime();
        return beijing.getHours() >= 9;
    }

    // Format date as YYYY-MM-DD HH:MM:SS
    function formatDateTime(date, hours, minutes, seconds) {
        var year = date.getFullYear();
        var month = String(date.getMonth() + 1).padStart(2, '0');
        var day = String(date.getDate()).padStart(2, '0');
        var h = String(hours).padStart(2, '0');
        var m = String(minutes).padStart(2, '0');
        var s = String(seconds).padStart(2, '0');
        return year + '-' + month + '-' + day + ' ' + h + ':' + m + ':' + s;
    }

    // Format date as YYYY-MM-DD
    function formatDateOnly(date) {
        var year = date.getFullYear();
        var month = String(date.getMonth() + 1).padStart(2, '0');
        var day = String(date.getDate()).padStart(2, '0');
        return year + '-' + month + '-' + day;
    }

    // Store chart data
    var chartData = null;
    var currentRange = '7d';
    // Store rendered chart state for touch crosshair
    var chartRenderState = null;

    // Fetch TD (today) chart data - uses unified /historical endpoint
    function fetchTodayData() {
        if (!isAfter9AM()) {
            showChartMessage('No data available before 9:00 AM (UTC+8)');
            return;
        }

        var beijing = getBeijingTime();
        var todayStr = formatDateOnly(beijing);

        // Use /historical endpoint with from=to=today (server forwards to statistics API)
        var url = restUrl + 'historical?' +
            'productSpec=' + encodeURIComponent(detail.productSpec) +
            '&category=' + encodeURIComponent(detail.category) +
            '&from=' + encodeURIComponent(todayStr) +
            '&to=' + encodeURIComponent(todayStr);

        showChartMessage('<?php echo esc_js(__('Loading chart data...', 'ippgi')); ?>');

        fetch(url)
            .then(function(res) {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            })
            .then(function(resp) {
                if (!resp.success || !resp.data || !resp.data.result || !resp.data.result.list) {
                    showChartMessage('No data available');
                    return;
                }
                chartData = resp.data.result;
                drawChart(chartData.list, false); // false = today's data (show times)
            })
            .catch(function(err) {
                console.error('Failed to fetch chart data:', err);
                showChartMessage('<?php echo esc_js(__('Failed to load chart data', 'ippgi')); ?>');
            });
    }

    // Show message in chart area
    function showChartMessage(msg) {
        var placeholder = document.getElementById('detail-chart-placeholder');
        var canvas = document.getElementById('detail-chart-canvas');
        var balloonsContainer = document.getElementById('detail-chart-balloons');
        if (placeholder) {
            placeholder.style.display = 'flex';
            placeholder.querySelector('p').textContent = msg;
        }
        if (canvas) {
            var ctx = canvas.getContext('2d');
            ctx.clearRect(0, 0, canvas.width, canvas.height);
        }
        if (balloonsContainer) {
            balloonsContainer.innerHTML = '';
        }
    }

    // Draw price chart on canvas
    // isHistorical: if true, show dates on X-axis instead of times
    function drawChart(list, isHistorical) {
        var canvas = document.getElementById('detail-chart-canvas');
        var placeholder = document.getElementById('detail-chart-placeholder');
        var yAxisEl = document.getElementById('detail-chart-y-axis');
        var xAxisEl = document.getElementById('detail-chart-x-axis');

        if (!canvas || !list || list.length === 0) {
            showChartMessage('No data available');
            return;
        }

        // Hide placeholder
        if (placeholder) placeholder.style.display = 'none';

        var ctx = canvas.getContext('2d');
        var container = canvas.parentElement;

        // Set canvas size
        canvas.width = container.offsetWidth;
        canvas.height = container.offsetHeight;

        // Downsample data if too many points for smooth rendering
        // Target ~300 points max for clean chart lines
        var maxPoints = 300;
        var sampledList = list;
        if (list.length > maxPoints) {
            var step = Math.ceil(list.length / maxPoints);
            sampledList = [];
            for (var i = 0; i < list.length; i += step) {
                sampledList.push(list[i]);
            }
            // Always include the last point
            if (sampledList[sampledList.length - 1] !== list[list.length - 1]) {
                sampledList.push(list[list.length - 1]);
            }
        }

        function getNumericValue(item, keys) {
            for (var keyIndex = 0; keyIndex < keys.length; keyIndex++) {
                var value = item[keys[keyIndex]];
                var numericValue = Number(value);
                if (!isNaN(numericValue) && numericValue > 0) {
                    return numericValue;
                }
            }

            return 0;
        }

        var lowerPrices = sampledList.map(function(item) {
            return getNumericValue(item, ['price_tax_usd_min', 'priceTax_usd_min', 'priceTax_usd', 'price_tax_usd', 'price_usd', 'price']);
        });
        var upperPrices = sampledList.map(function(item) {
            return getNumericValue(item, ['price_tax_usd_max', 'priceTax_usd_max', 'priceTax_usd', 'price_tax_usd', 'price_usd', 'price']);
        });
        var midPrices = lowerPrices.map(function(lowerPrice, index) {
            return (lowerPrice + upperPrices[index]) / 2;
        });

        // Calculate min/max for Y axis
        var allPrices = lowerPrices.concat(upperPrices);
        var minPrice = Math.min.apply(null, allPrices);
        var maxPrice = Math.max.apply(null, allPrices);
        var priceRange = maxPrice - minPrice;

        // Add padding to price range
        if (priceRange === 0) {
            priceRange = maxPrice * 0.1 || 100;
            minPrice -= priceRange / 2;
            maxPrice += priceRange / 2;
        } else {
            var padding = priceRange * 0.1;
            minPrice -= padding;
            maxPrice += padding; // Top padding
            priceRange = maxPrice - minPrice;
        }

        // Update Y-axis labels
        if (yAxisEl) {
            var labels = yAxisEl.querySelectorAll('span');
            var step = priceRange / (labels.length - 1);
            for (var i = 0; i < labels.length; i++) {
                var value = maxPrice - (step * i);
                labels[i].textContent = Math.round(value);
            }
        }

        // Update X-axis labels
        if (xAxisEl) {
            updateXAxisLabels(xAxisEl, list, isHistorical);
        }

        // Clear canvas
        ctx.clearRect(0, 0, canvas.width, canvas.height);

        var w = canvas.width;
        var h = canvas.height;

        function getPointX(index, totalPoints) {
            if (totalPoints <= 1) {
                return w / 2;
            }

            return (index / (totalPoints - 1)) * w;
        }

        function getPointY(price) {
            return h - ((price - minPrice) / priceRange) * h;
        }

        function drawSeries(prices, color, lineWidth) {
            ctx.beginPath();
            ctx.strokeStyle = color;
            ctx.lineWidth = lineWidth;

            for (var i = 0; i < prices.length; i++) {
                var x = getPointX(i, prices.length);
                var y = getPointY(prices[i]);

                if (i === 0) {
                    ctx.moveTo(x, y);
                } else {
                    ctx.lineTo(x, y);
                }
            }

            ctx.stroke();
        }

        var lowerLineColor = '#8FB8E0';
        var upperLineColor = '#6E9FD0';
        var rangeFillColor = 'rgba(143, 184, 224, 0.36)';

        function fillRangeArea(lowerSeries, upperSeries, fillColor) {
            if (!lowerSeries.length || !upperSeries.length || lowerSeries.length !== upperSeries.length) {
                return;
            }

            ctx.beginPath();

            for (var i = 0; i < upperSeries.length; i++) {
                var upperX = getPointX(i, upperSeries.length);
                var upperY = getPointY(upperSeries[i]);

                if (i === 0) {
                    ctx.moveTo(upperX, upperY);
                } else {
                    ctx.lineTo(upperX, upperY);
                }
            }

            for (var j = lowerSeries.length - 1; j >= 0; j--) {
                var lowerX = getPointX(j, lowerSeries.length);
                var lowerY = getPointY(lowerSeries[j]);
                ctx.lineTo(lowerX, lowerY);
            }

            ctx.closePath();
            ctx.fillStyle = fillColor;
            ctx.fill();
        }

        fillRangeArea(lowerPrices, upperPrices, rangeFillColor);
        drawSeries(lowerPrices, lowerLineColor, 2);
        drawSeries(upperPrices, upperLineColor, 2);

        // Store render state for touch crosshair
        chartRenderState = {
            list: sampledList,
            lowerPrices: lowerPrices,
            upperPrices: upperPrices,
            midPrices: midPrices,
            minPrice: minPrice,
            maxPrice: maxPrice,
            priceRange: priceRange,
            canvasWidth: w,
            canvasHeight: h,
            isHistorical: isHistorical
        };
    }

    // Update X-axis labels based on data type
    function updateXAxisLabels(xAxisEl, list, isHistorical) {
        var labels = [];

        if (isHistorical && list.length > 0) {
            // For historical data, show dates
            var labelCount = Math.min(9, list.length);
            var maxIndex = list.length - 1;

            for (var i = 0; i < labelCount; i++) {
                var idx;
                if (labelCount === 1) {
                    idx = 0;
                } else {
                    idx = Math.round((i * maxIndex) / (labelCount - 1));
                }
                var item = list[idx];
                // Note: external API uses 'satisticsTime' (typo), DB uses 'statisticsTime'
                var dateStr = item.statisticsTime || item.satisticsTime || '';
                if (dateStr) {
                    // Extract month and day: "MM-DD"
                    var parts = dateStr.split(' ')[0].split('-');
                    if (parts.length >= 3) {
                        labels.push(parts[1] + '-' + parts[2]);
                    } else {
                        labels.push('');
                    }
                } else {
                    labels.push('');
                }
            }
        } else {
            // For today's data, show fixed hourly time labels (09:00 - 17:00)
            // 9 labels for 8-hour range, evenly distributed
            labels = ['09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00'];
        }

        // Update DOM
        xAxisEl.innerHTML = '';
        labels.forEach(function(label) {
            var span = document.createElement('span');
            span.textContent = label;
            xAxisEl.appendChild(span);
        });
    }

    // Fetch historical data from database
    function fetchHistoricalData(range) {
        var url = restUrl + 'historical?' +
            'productSpec=' + encodeURIComponent(detail.productSpec) +
            '&category=' + encodeURIComponent(detail.category) +
            '&range=' + encodeURIComponent(range);

        showChartMessage('<?php echo esc_js(__('Loading chart data...', 'ippgi')); ?>');

        fetch(url)
            .then(function(res) {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            })
            .then(function(resp) {
                if (!resp.success || !resp.data || !resp.data.list || resp.data.list.length === 0) {
                    showChartMessage('No data available');
                    return;
                }
                chartData = resp.data;
                drawChart(chartData.list, true); // true = historical data (show dates)
            })
            .catch(function(err) {
                console.error('Failed to fetch historical data:', err);
                showChartMessage('<?php echo esc_js(__('Failed to load chart data', 'ippgi')); ?>');
            });
    }

    // Load data based on selected range
    function loadChartData(range) {
        currentRange = range;
        fetchHistoricalData(range);
    }

    // ========== Time Range Tabs ==========

    var rangeBtns = document.querySelectorAll('.detail-chart__range-btn');
    var rangeSlider = document.getElementById('detail-range-slider');
    var rangeTrack = document.getElementById('detail-range-track');

    function moveSlider(btn) {
        if (!rangeSlider || !rangeTrack) return;
        rangeSlider.style.left = btn.offsetLeft + 'px';
        rangeSlider.style.width = btn.offsetWidth + 'px';
    }

    // Set initial slider position
    var activeBtn = document.querySelector('.detail-chart__range-btn.is-active');
    if (activeBtn) {
        if (rangeSlider) rangeSlider.style.transition = 'none';
        moveSlider(activeBtn);
        requestAnimationFrame(function() {
            requestAnimationFrame(function() {
                if (rangeSlider) rangeSlider.style.transition = '';
            });
        });
    }

    rangeBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            rangeBtns.forEach(function(b) {
                b.classList.remove('is-active');
            });
            this.classList.add('is-active');
            moveSlider(this);

            // Reset date picker trigger text when selecting a preset range
            var dpTriggerText = document.querySelector('#detail-date-picker span');
            if (dpTriggerText) dpTriggerText.textContent = '<?php echo esc_js(__('Start Date ~ End Date', 'ippgi')); ?>';

            var range = this.dataset.range;
            loadChartData(range);
        });
    });

    window.addEventListener('resize', function() {
        var activeRangeBtn = document.querySelector('.detail-chart__range-btn.is-active');
        if (activeRangeBtn) {
            moveSlider(activeRangeBtn);
        }
    });

    // Load initial chart data (7D)
    loadChartData('7d');

    // ========== Date Picker ==========

    var datePickerTrigger = document.getElementById('detail-date-picker');
    var datePickerSheet = document.getElementById('detail-date-picker-sheet');
    var datePickerBackdrop = document.getElementById('detail-date-picker-backdrop');

    if (datePickerTrigger && datePickerSheet) {
        var closeBtn = document.getElementById('detail-date-picker-close');
        var clearBtn = document.getElementById('detail-date-picker-clear');
        var confirmBtn = document.getElementById('detail-date-picker-confirm');
        var prevBtn = document.getElementById('detail-date-picker-prev');
        var nextBtn = document.getElementById('detail-date-picker-next');
        var monthDisplay = document.getElementById('detail-date-picker-month');
        var daysContainer = document.getElementById('detail-date-picker-days');
        var startDisplay = document.getElementById('detail-date-range-start');
        var endDisplay = document.getElementById('detail-date-range-end');
        var triggerText = datePickerTrigger.querySelector('span');

        // State
        var dpCurrentYear = parseInt(datePickerSheet.dataset.currentYear) || new Date().getFullYear();
        var dpCurrentMonth = parseInt(datePickerSheet.dataset.currentMonth) || new Date().getMonth() + 1;
        var dpStartDate = null;
        var dpEndDate = null;
        var dpSelectingStart = true;

        // Month names — read from i18n dict (TP-translated), fall back to English
        var monthNames = (window.ippgiData && window.ippgiData.strings && Array.isArray(window.ippgiData.strings.months) && window.ippgiData.strings.months.length === 12)
            ? window.ippgiData.strings.months
            : ['January', 'February', 'March', 'April', 'May', 'June',
               'July', 'August', 'September', 'October', 'November', 'December'];

        function openDatePicker() {
            datePickerSheet.classList.add('is-active');
            if (datePickerBackdrop) datePickerBackdrop.classList.add('is-active');
            document.body.style.overflow = 'hidden';
            renderCalendarDP();
        }

        function closeDatePicker() {
            datePickerSheet.classList.remove('is-active');
            if (datePickerBackdrop) datePickerBackdrop.classList.remove('is-active');
            document.body.style.overflow = '';
        }

        function getDaysInMonthDP(year, month) {
            return new Date(year, month, 0).getDate();
        }

        function getFirstDayOfMonthDP(year, month) {
            return new Date(year, month - 1, 1).getDay();
        }

        function formatDateDP(date) {
            if (!date) return '--';
            var year = date.getFullYear();
            var month = String(date.getMonth() + 1).padStart(2, '0');
            var day = String(date.getDate()).padStart(2, '0');
            return year + '-' + month + '-' + day;
        }

        function formatDateShortDP(date) {
            if (!date) return '--';
            var month = String(date.getMonth() + 1).padStart(2, '0');
            var day = String(date.getDate()).padStart(2, '0');
            return month + '/' + day;
        }

        function isSameDateDP(date1, date2) {
            if (!date1 || !date2) return false;
            return date1.getFullYear() === date2.getFullYear() &&
                   date1.getMonth() === date2.getMonth() &&
                   date1.getDate() === date2.getDate();
        }

        function isInRangeDP(date) {
            if (!dpStartDate || !dpEndDate || !date) return false;
            return date > dpStartDate && date < dpEndDate;
        }

        function updateRangeDisplayDP() {
            if (startDisplay) {
                startDisplay.textContent = dpStartDate ? formatDateShortDP(dpStartDate) : '--';
            }
            if (endDisplay) {
                endDisplay.textContent = dpEndDate ? formatDateShortDP(dpEndDate) : '--';
            }
        }

        function renderCalendarDP() {
            if (!daysContainer || !monthDisplay) return;

            // Update month display
            monthDisplay.textContent = monthNames[dpCurrentMonth - 1] + ' ' + dpCurrentYear;

            // Clear existing days
            daysContainer.innerHTML = '';

            var daysInMonth = getDaysInMonthDP(dpCurrentYear, dpCurrentMonth);
            var firstDay = getFirstDayOfMonthDP(dpCurrentYear, dpCurrentMonth);
            var today = new Date();
            today.setHours(0, 0, 0, 0);

            // Add empty cells for days before the first day
            for (var i = 0; i < firstDay; i++) {
                var emptyDay = document.createElement('span');
                emptyDay.className = 'date-picker-sheet__day is-empty';
                daysContainer.appendChild(emptyDay);
            }

            // Add days
            for (var day = 1; day <= daysInMonth; day++) {
                var dayEl = document.createElement('span');
                dayEl.className = 'date-picker-sheet__day';
                dayEl.textContent = day;

                var date = new Date(dpCurrentYear, dpCurrentMonth - 1, day);
                date.setHours(0, 0, 0, 0);

                // Disable future dates
                if (date > today) {
                    dayEl.classList.add('is-disabled');
                } else {
                    // Check if this is start or end date
                    if (isSameDateDP(date, dpStartDate)) {
                        dayEl.classList.add('is-range-start');
                        if (isSameDateDP(dpStartDate, dpEndDate) || !dpEndDate) {
                            dayEl.classList.add('is-range-end');
                        }
                    } else if (isSameDateDP(date, dpEndDate)) {
                        dayEl.classList.add('is-range-end');
                    } else if (isInRangeDP(date)) {
                        dayEl.classList.add('is-in-range');
                    }

                    // Add click handler
                    (function(clickedDate) {
                        dayEl.addEventListener('click', function() {
                            handleDateClickDP(clickedDate);
                        });
                    })(date);
                }

                daysContainer.appendChild(dayEl);
            }
        }

        function handleDateClickDP(date) {
            if (dpSelectingStart || !dpStartDate) {
                // Selecting start date
                dpStartDate = date;
                dpEndDate = null;
                dpSelectingStart = false;
            } else {
                // Selecting end date
                if (date < dpStartDate) {
                    // If clicked date is before start, swap them
                    dpEndDate = dpStartDate;
                    dpStartDate = date;
                } else if (isSameDateDP(date, dpStartDate)) {
                    // Disallow single-day custom range selection so we always use historical DB queries.
                    dpEndDate = null;
                    dpSelectingStart = false;
                    updateRangeDisplayDP();
                    renderCalendarDP();
                    return;
                } else {
                    dpEndDate = date;
                }
                dpSelectingStart = true;
            }

            updateRangeDisplayDP();
            renderCalendarDP();
        }

        function clearSelectionDP() {
            dpStartDate = null;
            dpEndDate = null;
            dpSelectingStart = true;
            updateRangeDisplayDP();
            renderCalendarDP();
        }

        function confirmSelectionDP() {
            var hasValidRange = dpStartDate && dpEndDate && !isSameDateDP(dpStartDate, dpEndDate);

            // Update the trigger text
            if (triggerText) {
                if (hasValidRange) {
                    triggerText.textContent = formatDateShortDP(dpStartDate) + ' ~ ' + formatDateShortDP(dpEndDate);
                } else if (dpStartDate) {
                    triggerText.textContent = formatDateShortDP(dpStartDate) + ' ~';
                } else {
                    triggerText.textContent = '<?php echo esc_js(__('Start Date ~ End Date', 'ippgi')); ?>';
                }
            }

            closeDatePicker();

            // If we have a valid date range, fetch custom historical data
            if (hasValidRange) {
                // Deselect all range buttons
                rangeBtns.forEach(function(b) {
                    b.classList.remove('is-active');
                });
                if (rangeSlider) rangeSlider.style.width = '0';

                // Fetch custom range data
                fetchCustomRangeData(dpStartDate, dpEndDate);
            }
        }

        function fetchCustomRangeData(startDate, endDate) {
            var fromStr = formatDateDP(startDate);
            var toStr = formatDateDP(endDate);

            var url = restUrl + 'historical?' +
                'productSpec=' + encodeURIComponent(detail.productSpec) +
                '&category=' + encodeURIComponent(detail.category) +
                '&from=' + encodeURIComponent(fromStr) +
                '&to=' + encodeURIComponent(toStr);

            showChartMessage('<?php echo esc_js(__('Loading chart data...', 'ippgi')); ?>');

            fetch(url)
                .then(function(res) {
                    if (!res.ok) throw new Error('HTTP ' + res.status);
                    return res.json();
                })
                .then(function(resp) {
                    if (!resp.success || !resp.data) {
                        showChartMessage('No data available');
                        return;
                    }
                    var list = resp.data.list;

                    if (!list || list.length === 0) {
                        showChartMessage('No data available');
                        return;
                    }
                    chartData = resp.data;
                    drawChart(list, true);
                })
                .catch(function(err) {
                    console.error('Failed to fetch custom range data:', err);
                    showChartMessage('<?php echo esc_js(__('Failed to load chart data', 'ippgi')); ?>');
                });
        }

        function goToPrevMonthDP() {
            dpCurrentMonth--;
            if (dpCurrentMonth < 1) {
                dpCurrentMonth = 12;
                dpCurrentYear--;
            }
            renderCalendarDP();
        }

        function goToNextMonthDP() {
            dpCurrentMonth++;
            if (dpCurrentMonth > 12) {
                dpCurrentMonth = 1;
                dpCurrentYear++;
            }
            renderCalendarDP();
        }

        // Event listeners
        datePickerTrigger.addEventListener('click', openDatePicker);
        if (closeBtn) closeBtn.addEventListener('click', closeDatePicker);
        if (datePickerBackdrop) datePickerBackdrop.addEventListener('click', closeDatePicker);
        if (clearBtn) clearBtn.addEventListener('click', clearSelectionDP);
        if (confirmBtn) confirmBtn.addEventListener('click', confirmSelectionDP);
        if (prevBtn) prevBtn.addEventListener('click', goToPrevMonthDP);
        if (nextBtn) nextBtn.addEventListener('click', goToNextMonthDP);
    }

    // ========== Touch Crosshair for Chart ==========

    var chartArea = document.getElementById('detail-chart');
    var crosshair = document.getElementById('chart-crosshair');
    var infobox = document.getElementById('chart-infobox');

    if (chartArea && crosshair && infobox) {
        var crosshairDotLower = document.getElementById('chart-crosshair-dot-lower');
        var crosshairDotUpper = document.getElementById('chart-crosshair-dot-upper');
        var infoboxTime = document.getElementById('chart-infobox-time');
        var infoboxName = document.getElementById('chart-infobox-name');
        var infoboxSpec = document.getElementById('chart-infobox-spec');
        var infoboxPrice = document.getElementById('chart-infobox-price');

        // Get product info from page data
        var productSpec = detail.productSpec || '';
        var materialCode = detail.materialCode || '';
        var specParts = productSpec.split('_');
        // Only show product name for AL (it has consistent naming)
        // For PPGI, GI, GL, CRC - hide product name (they have mixed Chinese/English names)
        var productName = '';
        if (materialCode === 'aluminum' && specParts.length >= 4) {
            productName = specParts[specParts.length - 1];
        }
        var thickness = specParts.length >= 3 ? specParts[2] : '';
        var width = specParts.length >= 2 ? specParts[1] : '';
        var dimensionStr = thickness && width ? (thickness + '*' + width) : '';

        // Month names for date formatting (PHP-injected so TP can translate)
        var monthNames = [
            '<?php echo esc_js(__('Jan', 'ippgi')); ?>',
            '<?php echo esc_js(__('Feb', 'ippgi')); ?>',
            '<?php echo esc_js(__('Mar', 'ippgi')); ?>',
            '<?php echo esc_js(__('Apr', 'ippgi')); ?>',
            '<?php echo esc_js(__('May', 'ippgi')); ?>',
            '<?php echo esc_js(__('Jun', 'ippgi')); ?>',
            '<?php echo esc_js(__('Jul', 'ippgi')); ?>',
            '<?php echo esc_js(__('Aug', 'ippgi')); ?>',
            '<?php echo esc_js(__('Sep', 'ippgi')); ?>',
            '<?php echo esc_js(__('Oct', 'ippgi')); ?>',
            '<?php echo esc_js(__('Nov', 'ippgi')); ?>',
            '<?php echo esc_js(__('Dec', 'ippgi')); ?>'
        ];

        function showCrosshair(touchX) {
            if (!chartRenderState || !chartRenderState.list.length) return;

            var rect = chartArea.getBoundingClientRect();
            var x = touchX - rect.left;
            var w = rect.width;
            var h = rect.height;

            // Clamp x within chart bounds
            x = Math.max(0, Math.min(x, w));

            // Calculate index based on touch position
            var ratio = x / w;
            var idx = Math.round(ratio * (chartRenderState.midPrices.length - 1));
            idx = Math.max(0, Math.min(idx, chartRenderState.midPrices.length - 1));

            var item = chartRenderState.list[idx];
            var lowerPrice = chartRenderState.lowerPrices[idx];
            var upperPrice = chartRenderState.upperPrices[idx];
            var midPrice = chartRenderState.midPrices[idx];

            // Snap to the actual data point X position
            var snapX = chartRenderState.midPrices.length <= 1
                ? (w / 2)
                : ((idx / (chartRenderState.midPrices.length - 1)) * w);

            var lowerY = h - ((lowerPrice - chartRenderState.minPrice) / chartRenderState.priceRange) * h;
            var upperY = h - ((upperPrice - chartRenderState.minPrice) / chartRenderState.priceRange) * h;
            var midY = h - ((midPrice - chartRenderState.minPrice) / chartRenderState.priceRange) * h;

            // Position crosshair at snapped data point X
            crosshair.style.left = snapX + 'px';
            crosshair.classList.add('is-active');

            // Position dots at lower / upper bounds
            if (crosshairDotLower) {
                crosshairDotLower.style.top = lowerY + 'px';
            }
            if (crosshairDotUpper) {
                crosshairDotUpper.style.top = upperY + 'px';
            }

            // Format time/date string
            var timeStr = '';
            if (chartRenderState.isHistorical) {
                // Historical: show date like "Oct 02, 2025"
                // Note: external API uses 'satisticsTime' (typo), DB uses 'statisticsTime'
                var dateStr = item.statisticsTime || item.satisticsTime || '';
                if (dateStr) {
                    var parts = dateStr.split(' ')[0].split('-');
                    if (parts.length >= 3) {
                        var monthIdx = parseInt(parts[1], 10) - 1;
                        timeStr = monthNames[monthIdx] + ' ' + parts[2] + ', ' + parts[0];
                    }
                }
            } else {
                // Today: calculate time based on index position (09:00 - 17:00 range)
                var totalPoints = chartRenderState.midPrices.length;
                var timeRangeMinutes = 8 * 60; // 8 hours = 480 minutes
                var startMinutes = 9 * 60; // 09:00 = 540 minutes from midnight
                var pointMinutes = startMinutes + (idx / Math.max(1, totalPoints - 1)) * timeRangeMinutes;
                var hours = Math.floor(pointMinutes / 60);
                var minutes = Math.floor(pointMinutes % 60);
                timeStr = String(hours).padStart(2, '0') + ':' +
                          String(minutes).padStart(2, '0') + ':00 (UTC+8)';
            }

            // Update infobox content
            if (infoboxTime) infoboxTime.textContent = timeStr;
            if (infoboxName) infoboxName.textContent = productName;
            if (infoboxSpec) infoboxSpec.textContent = dimensionStr;
            if (infoboxPrice) infoboxPrice.textContent = '$' + formatPrice(lowerPrice) + '~$' + formatPrice(upperPrice);

            // Position infobox - keep within chart bounds
            var infoboxWidth = infobox.offsetWidth || 200;
            var infoboxHeight = infobox.offsetHeight || 50;
            var infoboxX = snapX + 10; // 10px to the right of crosshair
            var infoboxY = midY - infoboxHeight / 2; // Center vertically between two lines

            // Keep within horizontal bounds
            if (infoboxX + infoboxWidth > w) {
                infoboxX = snapX - infoboxWidth - 10; // Flip to left side
            }
            if (infoboxX < 0) {
                infoboxX = 5;
            }

            // Keep within vertical bounds
            if (infoboxY < 0) {
                infoboxY = 5;
            }
            if (infoboxY + infoboxHeight > h) {
                infoboxY = h - infoboxHeight - 5;
            }

            infobox.style.left = infoboxX + 'px';
            infobox.style.top = infoboxY + 'px';
            infobox.classList.add('is-active');
        }

        function hideCrosshair() {
            crosshair.classList.remove('is-active');
            infobox.classList.remove('is-active');
        }

        // Touch events (mobile)
        chartArea.addEventListener('touchstart', function(e) {
            if (e.touches.length === 1) {
                e.preventDefault();
                showCrosshair(e.touches[0].clientX);
            }
        }, { passive: false });

        chartArea.addEventListener('touchmove', function(e) {
            if (e.touches.length === 1) {
                e.preventDefault();
                showCrosshair(e.touches[0].clientX);
            }
        }, { passive: false });

        chartArea.addEventListener('touchend', function(e) {
            hideCrosshair();
        });

        chartArea.addEventListener('touchcancel', function(e) {
            hideCrosshair();
        });

        // Mouse events (desktop)
        chartArea.addEventListener('mouseenter', function(e) {
            showCrosshair(e.clientX);
        });

        chartArea.addEventListener('mousemove', function(e) {
            showCrosshair(e.clientX);
        });

        chartArea.addEventListener('mouseleave', function(e) {
            hideCrosshair();
        });
    }
})();
</script>

<?php
get_footer();
