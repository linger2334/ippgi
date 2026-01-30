<?php
/**
 * Template Name: Price Detail Page
 *
 * @package IPPGI
 * @since 1.0.0
 */

get_header();

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

$product_spec = isset($_GET['spec']) ? sanitize_text_field($_GET['spec']) : '';

// Material data mapping
$materials = [
    'gi'       => ['name' => __('Galvanized Steel', 'ippgi'), 'code' => 'GI'],
    'gl'       => ['name' => __('Galvalume Steel', 'ippgi'), 'code' => 'GL'],
    'ppgi'     => ['name' => __('Pre-painted Galvanized Iron', 'ippgi'), 'code' => 'PPGI'],
    'hrc'      => ['name' => __('Hot Rolled Coil', 'ippgi'), 'code' => 'HRC'],
    'crc'      => ['name' => __('Cold Rolled Hard Coil', 'ippgi'), 'code' => 'CRC Hard'],
    'aluminum' => ['name' => __('Aluminum Sheet', 'ippgi'), 'code' => 'AL'],
];

$current_material = isset($materials[$material_code]) ? $materials[$material_code] : $materials['ppgi'];

// Category ID mapping
$category_id_mapping = [
    'ppgi'     => '1482328115005964290',
    'gi'       => '1457211766760558593',
    'gl'       => '1683315093109178369',
    'hrc'      => '1457211813719986177',
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

// Build display dimensions: "厚度*宽度 材料名称" (consistent with prices page)
$short_spec = $matched_thickness && $matched_width ? ($matched_thickness . '*' . $matched_width) : $product_spec;
$display_dimensions = $short_spec;
if ($product_name_from_spec) {
    $display_dimensions = $short_spec . ' ' . $product_name_from_spec;
}

// Map material_code to the favorite type key used by ippgi_get_user_favorites()
$favorite_type_mapping = [
    'ppgi'     => 'ppgi',
    'gi'       => 'gi',
    'gl'       => 'gl',
    'hrc'      => 'hrc',
    'crc'      => 'crc_hard',
    'aluminum' => 'al',
];
$favorite_type = $favorite_type_mapping[$material_code] ?? $material_code;

// Favorite ID format: type-productSpec (e.g., "ppgi-1482328115005964290_1000_0.11_彩涂")
// Use full $product_spec as unique identifier
$favorite_id = $product_spec ? ($favorite_type . '-' . $product_spec) : $favorite_type;

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
                <?php
                printf(
                    esc_html__('Price charts and tables of China %s and commodities', 'ippgi'),
                    esc_html($current_material['code'])
                );
                ?>
            </h1>
            <p class="prices-page-header__disclaimer">
                <?php esc_html_e('*These prices reflect the transaction prices within China and do not include shipping costs.', 'ippgi'); ?>
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
                <span class="detail-realtime__label"><?php esc_html_e('Real-Time Data', 'ippgi'); ?></span>
                <label class="price-controls__toggle">
                    <input type="checkbox" id="detail-tax-toggle" class="price-controls__checkbox">
                    <span class="price-controls__toggle-indicator"></span>
                    <span class="price-controls__toggle-text"><?php esc_html_e('Tax-inclusive price', 'ippgi'); ?></span>
                </label>
            </div>

            <div class="detail-realtime__price-row">
                <div class="detail-realtime__main-price">
                    <span class="detail-realtime__value" id="detail-price">--</span>
                    <div class="detail-realtime__change" id="detail-change-wrap">
                        <span class="detail-realtime__change-value" id="detail-change">--</span>
                        <span class="detail-realtime__change-pct" id="detail-change-pct">--</span>
                    </div>
                </div>
                <div class="detail-realtime__stats">
                    <div class="detail-realtime__stat">
                        <span class="detail-realtime__stat-label">Avg:</span><span class="detail-realtime__stat-value" id="detail-avg">--</span>
                    </div>
                    <div class="detail-realtime__stat">
                        <span class="detail-realtime__stat-label">WoW:</span><span class="detail-realtime__stat-value" id="detail-wow">--</span>
                    </div>
                    <div class="detail-realtime__stat">
                        <span class="detail-realtime__stat-label">MoM:</span><span class="detail-realtime__stat-value" id="detail-mom">--</span>
                    </div>
                    <div class="detail-realtime__stat">
                        <span class="detail-realtime__stat-label">YoY:</span><span class="detail-realtime__stat-value" id="detail-yoy">--</span>
                    </div>
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
                    <button type="button" class="detail-chart__range-btn is-active" data-range="td">TD</button>
                    <button type="button" class="detail-chart__range-btn" data-range="1m">1M</button>
                    <button type="button" class="detail-chart__range-btn" data-range="6m">6M</button>
                    <button type="button" class="detail-chart__range-btn" data-range="1y">1Y</button>
                    <button type="button" class="detail-chart__range-btn" data-range="2y">2Y</button>
                    <button type="button" class="detail-chart__range-btn" data-range="3y">3Y</button>
                    <button type="button" class="detail-chart__range-btn" data-range="4y">4Y</button>
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

        <!-- Disclaimer -->
        <div class="prices-disclaimer">
            <p class="prices-disclaimer__text">
                <strong class="prices-disclaimer__label"><?php esc_html_e('Disclaimer:', 'ippgi'); ?></strong>
                <?php
                $product_name = esc_html($current_material['code']);
                printf(
                    esc_html__('%1$s Price strives to provide accurate and objective data, information, and opinions but does not guarantee its completeness or the need for updates. The information is intended to assist customers in decision-making, not as direct advice. Customers should rely on their own judgment, and %1$s Price is not liable for any consequences arising from the use of this data. This report is the copyrighted property of %1$s Price and is for the exclusive use of its customers. Sharing, publishing, or copying this report without %1$s Price\'s permission is strictly prohibited. %1$s Price reserves the right to pursue legal action for any copyright infringement or misuse of this report.', 'ippgi'),
                    $product_name
                );
                ?>
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
    category: <?php echo json_encode($current_material['code']); ?>,
    canViewHistory: <?php echo json_encode(ippgi_user_can_view_history()); ?>
};

// Fetch real-time price data from server
(function() {
    var detail = window.ippgiPriceDetail;
    if (!detail.productSpec || !detail.categoryId) return;

    var restUrl = <?php echo json_encode(rest_url('ippgi-prices/v1/')); ?>;

    // Get appropriate date based on Beijing time (UTC+8)
    // Before 9:00 AM: use yesterday's date
    // 9:00 AM and after: use today's date
    function getApiDate() {
        var now = new Date();
        var utcHours = now.getUTCHours();
        var beijingHours = (utcHours + 8) % 24;

        // Calculate Beijing date
        var beijingDate = new Date(now.getTime() + 8 * 60 * 60 * 1000);

        // If before 9:00 AM Beijing time, use yesterday's date
        if (beijingHours < 9) {
            beijingDate.setDate(beijingDate.getDate() - 1);
        }

        // Format as YYYY-MM-DD
        var year = beijingDate.getUTCFullYear();
        var month = String(beijingDate.getUTCMonth() + 1).padStart(2, '0');
        var day = String(beijingDate.getUTCDate()).padStart(2, '0');

        return year + '-' + month + '-' + day;
    }

    var dateStr = getApiDate();

    var url = restUrl + 'price?' +
        'productSpec=' + encodeURIComponent(detail.productSpec) +
        '&categoryId=' + encodeURIComponent(detail.categoryId) +
        '&date=' + encodeURIComponent(dateStr);

    fetch(url)
        .then(function(res) {
            if (!res.ok) {
                throw new Error('HTTP ' + res.status);
            }
            return res.text();
        })
        .then(function(text) {
            if (!text) {
                throw new Error('Empty response');
            }
            var resp = JSON.parse(text);
            if (!resp.success || !resp.data || !resp.data.result) return;
            var r = resp.data.result;

            // Store data for tax toggle
            detail.priceData = r;

            // Update Real-Time Data section
            updateRealtimeDisplay(r, false);
        })
        .catch(function(err) {
            console.error('Failed to fetch realtime price:', err);
        });

    // Tax toggle
    var taxToggle = document.getElementById('detail-tax-toggle');
    if (taxToggle) {
        taxToggle.addEventListener('change', function() {
            if (detail.priceData) {
                updateRealtimeDisplay(detail.priceData, this.checked);
            }
        });
    }

    function updateRealtimeDisplay(r, isTax) {
        var price = isTax ? (r.lastpriceTax_usd || 0) : (r.lastprice_usd || 0);
        var avgPrice = isTax ? (r.priceTax_usd || 0) : (r.price_usd || 0);
        var change = isTax ? (r.riseAndFallTax_usd || 0) : (r.riseAndFall_usd || 0);
        // Use riseRange/riseRangeTax directly from API (already a percentage)
        var changePct = isTax ? r.riseRangeTax : r.riseRange;
        changePct = (typeof changePct === 'number') ? changePct : 0;

        var wow = numOrNull(isTax ? r.lastWeekDiffTax_usd : r.lastWeekDiff_usd);
        var mom = numOrNull(isTax ? r.lastMonthDiffTax_usd : r.lastMonthDiff_usd);
        var yoy = numOrNull(isTax ? r.lastYearsDiffTax_usd : r.lastYearsDiff_usd);

        // Main price
        var priceEl = document.getElementById('detail-price');
        if (priceEl) priceEl.textContent = '$' + Math.round(price).toLocaleString();

        // Change
        var changeWrap = document.getElementById('detail-change-wrap');
        var changeEl = document.getElementById('detail-change');
        var changePctEl = document.getElementById('detail-change-pct');
        if (changeWrap) {
            changeWrap.className = 'detail-realtime__change' +
                (change < 0 ? ' is-down' : (change > 0 ? ' is-up' : ''));
        }
        if (changeEl) changeEl.textContent = '$' + Math.round(change).toLocaleString();
        if (changePctEl) changePctEl.textContent = changePct + '%';

        // Avg
        var avgEl = document.getElementById('detail-avg');
        if (avgEl) avgEl.textContent = '$' + Math.round(avgPrice).toLocaleString();

        // WoW / MoM / YoY
        updateStatValue('detail-wow', wow);
        updateStatValue('detail-mom', mom);
        updateStatValue('detail-yoy', yoy);
    }

    // Return number or null (handles "-" string and undefined)
    function numOrNull(val) {
        if (typeof val === 'number' && !isNaN(val)) return val;
        return null;
    }

    function updateStatValue(id, val) {
        var el = document.getElementById(id);
        if (!el) return;
        if (val !== null && val !== 0) {
            el.textContent = '$' + Math.round(val).toLocaleString();
            el.className = 'detail-realtime__stat-value' +
                (val < 0 ? ' is-down' : (val > 0 ? ' is-up' : ''));
        } else {
            el.textContent = '--';
            el.className = 'detail-realtime__stat-value';
        }
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
    var currentRange = 'td';

    // Fetch TD (today) chart data - uses unified /historical endpoint
    function fetchTodayData() {
        if (!isAfter9AM()) {
            showChartMessage('No data available before 9:00 AM');
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

        showChartMessage('Loading chart data...');

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
                showChartMessage('Failed to load chart data');
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

        // Extract prices (use price_usd)
        var prices = sampledList.map(function(item) {
            return item.price_usd || item.price || 0;
        });
        var timestamps = sampledList.map(function(item) {
            return item.timestamp;
        });

        // Calculate min/max for Y axis
        var minPrice = Math.min.apply(null, prices);
        var maxPrice = Math.max.apply(null, prices);
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

        // Draw line chart
        var w = canvas.width;
        var h = canvas.height;

        ctx.beginPath();
        ctx.strokeStyle = '#2196F3';
        ctx.lineWidth = 2;

        for (var i = 0; i < prices.length; i++) {
            var x = (i / (prices.length - 1)) * w;
            var y = h - ((prices[i] - minPrice) / priceRange) * h;

            if (i === 0) {
                ctx.moveTo(x, y);
            } else {
                ctx.lineTo(x, y);
            }
        }
        ctx.stroke();

        // Find max and min point positions
        var maxIdx = 0, minIdx = 0;
        for (var i = 1; i < prices.length; i++) {
            if (prices[i] > prices[maxIdx]) maxIdx = i;
            if (prices[i] < prices[minIdx]) minIdx = i;
        }

        // Create HTML balloon element
        var balloonsContainer = document.getElementById('detail-chart-balloons');
        balloonsContainer.innerHTML = ''; // Clear existing balloons

        function createBalloon(idx) {
            var px = (idx / (prices.length - 1)) * w;
            var py = h - ((prices[idx] - minPrice) / priceRange) * h;
            var label = Math.round(prices[idx]);

            // Create balloon element
            var balloon = document.createElement('div');
            balloon.className = 'chart-balloon';
            balloon.innerHTML = '<span class="chart-balloon__text">' + label + '</span><span class="chart-balloon__arrow"></span>';

            // Position balloon (arrow tip at data point)
            balloon.style.left = px + 'px';
            balloon.style.top = py + 'px';

            balloonsContainer.appendChild(balloon);
        }

        if (prices.length > 1) {
            createBalloon(maxIdx);
            createBalloon(minIdx);
        }
    }

    // Update X-axis labels based on data type
    function updateXAxisLabels(xAxisEl, list, isHistorical) {
        var labelCount = 10; // Number of labels to show
        var labels = [];

        if (isHistorical && list.length > 0) {
            // For historical data, show dates
            var step = Math.max(1, Math.floor(list.length / (labelCount - 1)));
            for (var i = 0; i < labelCount; i++) {
                var idx = Math.min(i * step, list.length - 1);
                var item = list[idx];
                // statisticsTime format: "YYYY-MM-DD HH:MM:SS"
                var dateStr = item.statisticsTime || '';
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
            // For today's data, show times (09:00 - 18:00)
            labels = ['09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00'];
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

        showChartMessage('Loading chart data...');

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
                showChartMessage('Failed to load chart data');
            });
    }

    // Load data based on selected range
    function loadChartData(range) {
        currentRange = range;

        if (range === 'td') {
            fetchTodayData();
        } else {
            // Fetch historical data for 1m, 6m, 1y, 2y, 3y, 4y
            fetchHistoricalData(range);
        }
    }

    // ========== Time Range Tabs ==========

    var rangeBtns = document.querySelectorAll('.detail-chart__range-btn');
    var rangeSlider = document.getElementById('detail-range-slider');
    var rangeTrack = document.getElementById('detail-range-track');

    function moveSlider(btn) {
        if (!rangeSlider || !rangeTrack) return;
        var trackRect = rangeTrack.getBoundingClientRect();
        var btnRect = btn.getBoundingClientRect();
        rangeSlider.style.left = (btnRect.left - trackRect.left) + 'px';
        rangeSlider.style.width = btnRect.width + 'px';
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
            if (dpTriggerText) dpTriggerText.textContent = 'Start Date ~ End Date';

            var range = this.dataset.range;
            loadChartData(range);
        });
    });

    // Load initial chart data (TD)
    loadChartData('td');

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

        // Month names
        var monthNames = [
            'January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December'
        ];

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
            // Update the trigger text
            if (triggerText) {
                if (dpStartDate && dpEndDate) {
                    triggerText.textContent = formatDateShortDP(dpStartDate) + ' ~ ' + formatDateShortDP(dpEndDate);
                } else if (dpStartDate) {
                    triggerText.textContent = formatDateShortDP(dpStartDate) + ' ~';
                } else {
                    triggerText.textContent = 'Start Date ~ End Date';
                }
            }

            closeDatePicker();

            // If we have a valid date range, fetch custom historical data
            if (dpStartDate && dpEndDate) {
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
            var isSameDay = (fromStr === toStr);

            var url = restUrl + 'historical?' +
                'productSpec=' + encodeURIComponent(detail.productSpec) +
                '&category=' + encodeURIComponent(detail.category) +
                '&from=' + encodeURIComponent(fromStr) +
                '&to=' + encodeURIComponent(toStr);

            showChartMessage('Loading chart data...');

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
                    // Same-day returns statistics API format: data.result.list
                    // Multi-day returns historical format: data.list
                    var list = isSameDay
                        ? (resp.data.result && resp.data.result.list)
                        : resp.data.list;

                    if (!list || list.length === 0) {
                        showChartMessage('No data available');
                        return;
                    }
                    chartData = resp.data;
                    // Same-day shows times (like TD), multi-day shows dates
                    drawChart(list, !isSameDay);
                })
                .catch(function(err) {
                    console.error('Failed to fetch custom range data:', err);
                    showChartMessage('Failed to load chart data');
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
})();
</script>

<?php
get_footer();
