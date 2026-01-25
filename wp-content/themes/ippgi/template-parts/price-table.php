<?php
/**
 * Price Table Template Part
 * Displays today's material prices
 *
 * @package IPPGI
 * @since 1.0.0
 */

// Product specifications (width in mm)
$product_specs = [
    'GI'       => [1000, 1200, 1219, 1250],  // 民用镀锌
    'GL'       => [1000, 1200],              // 镀铝锌
    'PPGI'     => [1000, 1200],              // 彩涂
    'HRC'      => [1010, 1500],              // 热卷
    'CRC Hard' => [1000, 1200],              // 轧硬
    'AL'       => [1000],                    // 光铝
];

// Sample data structure - will be replaced with real data in Phase 2
// Format: thickness * width (e.g., 0.09*1000)
$sample_prices = [
    [
        'product'    => 'PPGI',
        'dimensions' => '0.09*1000',
        'price'      => 4650,
        'change'     => -10,
    ],
    [
        'product'    => 'PPGI',
        'dimensions' => '0.10*1000',
        'price'      => 4650,
        'change'     => -10,
    ],
    [
        'product'    => 'PPGI',
        'dimensions' => '0.11*1000',
        'price'      => 4650,
        'change'     => -10,
    ],
    [
        'product'    => 'PPGI',
        'dimensions' => '0.12*1000',
        'price'      => 4650,
        'change'     => -10,
    ],
    [
        'product'    => 'PPGI',
        'dimensions' => '0.13*1000',
        'price'      => 4650,
        'change'     => -10,
    ],
    [
        'product'    => 'PPGI',
        'dimensions' => '0.14*1000',
        'price'      => 4650,
        'change'     => -10,
    ],
];

// Allow filtering of prices data (for future use)
$prices = apply_filters('ippgi_price_table_data', $sample_prices);
?>

<div class="price-table-wrapper">
    <table class="price-table">
        <thead>
            <tr>
                <th><?php esc_html_e('Products', 'ippgi'); ?></th>
                <th><?php esc_html_e('Dimensions(mm)', 'ippgi'); ?></th>
                <th><?php esc_html_e('Latest($)', 'ippgi'); ?></th>
                <th><?php esc_html_e('Change($)', 'ippgi'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($prices as $price) : ?>
                <?php
                $change_class = 'neutral';
                $change_prefix = '';
                if ($price['change'] > 0) {
                    $change_class = 'up';
                    $change_prefix = '+$';
                } elseif ($price['change'] < 0) {
                    $change_class = 'down';
                    $change_prefix = '$';
                } else {
                    $change_prefix = '$';
                }
                ?>
                <tr>
                    <td data-label="<?php esc_attr_e('Products', 'ippgi'); ?>">
                        <span class="price-table__product"><?php echo esc_html($price['product']); ?></span>
                    </td>
                    <td data-label="<?php esc_attr_e('Dimensions(mm)', 'ippgi'); ?>">
                        <span class="price-table__dimensions"><?php echo esc_html($price['dimensions']); ?></span>
                    </td>
                    <td data-label="<?php esc_attr_e('Latest($)', 'ippgi'); ?>">
                        <span class="price-table__price">$<?php echo esc_html(number_format($price['price'])); ?></span>
                    </td>
                    <td data-label="<?php esc_attr_e('Change($)', 'ippgi'); ?>">
                        <span class="price-table__change price-table__change--<?php echo esc_attr($change_class); ?>">
                            <?php echo esc_html($change_prefix . $price['change']); ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
