<?php
/**
 * 回填历史价格表中的美元区间字段。
 *
 * 使用方法：
 * php backfill-historical-usd-ranges.php <开始日期> <结束日期>
 *
 * 示例：
 * php backfill-historical-usd-ranges.php 2026-03-20 2026-04-21
 *
 * 规则：
 * - 优先从当天已有区间恢复一组公共因子；当天没有区间时才随机生成
 * - 同一天的全部产品统一使用同一组因子
 * - 若区间值为空或与当天公共因子不一致，则重新计算并写入
 *
 * @package IPPGI
 * @since 1.0.0
 */

set_time_limit(600);
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 生产环境可通过 IPPGI_WP_ROOT 指向实际站点目录。
if (!defined('ABSPATH')) {
    $wp_root = getenv('IPPGI_WP_ROOT');
    $wp_load = $wp_root
        ? rtrim($wp_root, '/\\') . '/wp-load.php'
        : __DIR__ . '/wp-load.php';

    if (!is_readable($wp_load)) {
        die("错误: 找不到可读取的 wp-load.php，请设置 IPPGI_WP_ROOT\n");
    }

    require_once $wp_load;
}

echo "=== IPPGI 历史美元区间回填工具 ===\n\n";

if (isset($argv[1]) && in_array($argv[1], array('-h', '--help', 'help'), true)) {
    echo "用法: php backfill-historical-usd-ranges.php [开始日期] [结束日期]\n\n";
    echo "参数:\n";
    echo "  开始日期  格式 YYYY-MM-DD，默认为昨天\n";
    echo "  结束日期  格式 YYYY-MM-DD，默认为开始日期\n\n";
    echo "示例:\n";
    echo "  php backfill-historical-usd-ranges.php 2026-03-20 2026-04-21  # 回填指定日期范围\n";
    echo "  php backfill-historical-usd-ranges.php 2026-03-20             # 回填单天数据\n";
    echo "  php backfill-historical-usd-ranges.php                        # 回填昨天数据\n";
    exit(0);
}

$from_date = isset($argv[1]) ? $argv[1] : date('Y-m-d', strtotime('-1 day'));
$to_date = isset($argv[2]) ? $argv[2] : $from_date;

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to_date)) {
    die("错误: 日期格式不正确，请使用 YYYY-MM-DD 格式\n");
}

if (strcmp($from_date, $to_date) > 0) {
    die("错误: 开始日期不能晚于结束日期\n");
}

if (!class_exists('IPPGI_Prices_Database') || !class_exists('IPPGI_Prices_API_Client')) {
    die("错误: IPPGI Prices 插件未激活或未正确加载\n");
}

global $wpdb;

$timezone = wp_timezone();
$start = DateTimeImmutable::createFromFormat('Y-m-d', $from_date, $timezone);
$end = DateTimeImmutable::createFromFormat('Y-m-d', $to_date, $timezone);

if (!$start || !$end) {
    die("错误: 无法解析日期范围\n");
}

$lower_bps_min = IPPGI_Prices_API_Client::PRICE_RANGE_LOWER_BPS_MIN;
$lower_bps_max = IPPGI_Prices_API_Client::PRICE_RANGE_LOWER_BPS_MAX;
$upper_bps_min = IPPGI_Prices_API_Client::PRICE_RANGE_UPPER_BPS_MIN;
$upper_bps_max = IPPGI_Prices_API_Client::PRICE_RANGE_UPPER_BPS_MAX;

$summary = array(
    'total_rows' => 0,
    'updated_rows' => 0,
    'updated_fields' => 0,
    'skipped_rows' => 0,
    'failed_rows' => 0,
    'materials' => array(),
    'failures' => array(),
);

foreach (array_keys(IPPGI_Prices_Database::TABLES) as $material_type) {
    $summary['materials'][$material_type] = array(
        'total_rows' => 0,
        'updated_rows' => 0,
        'updated_fields' => 0,
        'skipped_rows' => 0,
        'failed_rows' => 0,
    );
}

echo "回填日期范围: {$from_date} 至 {$to_date}\n\n";
echo "开始回填，请耐心等待...\n";
echo str_repeat('-', 50) . "\n";

$start_time = microtime(true);
$current = $start;

while ($current <= $end) {
    $date = $current->format('Y-m-d');
    $day_start = $date . ' 00:00:00';
    $day_end = $current->modify('+1 day')->format('Y-m-d') . ' 00:00:00';

    $existing_day_bps = ippgi_backfill_detect_day_bps(
        $wpdb,
        $day_start,
        $day_end,
        $lower_bps_min,
        $lower_bps_max,
        $upper_bps_min,
        $upper_bps_max
    );

    $day_lower_bps = null !== $existing_day_bps['lower']
        ? $existing_day_bps['lower']
        : random_int($lower_bps_min, $lower_bps_max);
    $day_upper_bps = null !== $existing_day_bps['upper']
        ? $existing_day_bps['upper']
        : random_int($upper_bps_min, $upper_bps_max);
    $day_lower_multiplier = 1 - ($day_lower_bps / 10000);
    $day_upper_multiplier = 1 + ($day_upper_bps / 10000);
    $factor_source = null !== $existing_day_bps['lower'] || null !== $existing_day_bps['upper']
        ? '恢复当天已有因子'
        : '生成新因子';

    echo sprintf(
        "%s: 下限因子 %.2f%%, 上限因子 %.2f%%（%s）\n",
        $date,
        $day_lower_bps / 100,
        $day_upper_bps / 100,
        $factor_source
    );

    foreach (IPPGI_Prices_Database::TABLES as $material_type => $table_suffix) {
        $table_name = $wpdb->prefix . $table_suffix;
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, product_spec, statistics_time, price_usd, price_usd_min, price_usd_max, price_tax_usd, price_tax_usd_min, price_tax_usd_max
                 FROM {$table_name}
                 WHERE statistics_time >= %s AND statistics_time < %s
                 ORDER BY statistics_time ASC, id ASC",
                $day_start,
                $day_end
            ),
            ARRAY_A
        );

        if (empty($rows)) {
            continue;
        }

        foreach ($rows as $row) {
            $summary['total_rows']++;
            $summary['materials'][$material_type]['total_rows']++;

            $update_data = array();
            $update_formats = array();

            $price_usd = ippgi_backfill_normalize_decimal($row['price_usd']);
            $price_tax_usd = ippgi_backfill_normalize_decimal($row['price_tax_usd']);

            if (null !== $price_usd && $price_usd > 0) {
                $expected_min = ippgi_backfill_round_price($price_usd * $day_lower_multiplier);
                $expected_max = ippgi_backfill_round_price($price_usd * $day_upper_multiplier);

                if (!ippgi_backfill_value_matches($row['price_usd_min'], $expected_min)) {
                    $update_data['price_usd_min'] = $expected_min;
                    $update_formats[] = '%f';
                }

                if (!ippgi_backfill_value_matches($row['price_usd_max'], $expected_max)) {
                    $update_data['price_usd_max'] = $expected_max;
                    $update_formats[] = '%f';
                }
            }

            if (null !== $price_tax_usd && $price_tax_usd > 0) {
                $expected_tax_min = ippgi_backfill_round_price($price_tax_usd * $day_lower_multiplier);
                $expected_tax_max = ippgi_backfill_round_price($price_tax_usd * $day_upper_multiplier);

                if (!ippgi_backfill_value_matches($row['price_tax_usd_min'], $expected_tax_min)) {
                    $update_data['price_tax_usd_min'] = $expected_tax_min;
                    $update_formats[] = '%f';
                }

                if (!ippgi_backfill_value_matches($row['price_tax_usd_max'], $expected_tax_max)) {
                    $update_data['price_tax_usd_max'] = $expected_tax_max;
                    $update_formats[] = '%f';
                }
            }

            if (empty($update_data)) {
                $summary['skipped_rows']++;
                $summary['materials'][$material_type]['skipped_rows']++;
                continue;
            }

            $updated = $wpdb->update(
                $table_name,
                $update_data,
                array('id' => intval($row['id'])),
                $update_formats,
                array('%d')
            );

            if (false === $updated) {
                $summary['failed_rows']++;
                $summary['materials'][$material_type]['failed_rows']++;
                $summary['failures'][] = array(
                    'date' => $date,
                    'material' => $material_type,
                    'product_spec' => $row['product_spec'],
                    'message' => $wpdb->last_error,
                );
                continue;
            }

            $updated_fields_count = count($update_data);
            $summary['updated_rows']++;
            $summary['updated_fields'] += $updated_fields_count;
            $summary['materials'][$material_type]['updated_rows']++;
            $summary['materials'][$material_type]['updated_fields'] += $updated_fields_count;
        }
    }

    $current = $current->modify('+1 day');
}

$duration = microtime(true) - $start_time;

echo "\n" . str_repeat('=', 50) . "\n";
echo "回填完成!\n";
echo str_repeat('=', 50) . "\n\n";

echo "扫描记录数: {$summary['total_rows']}\n";
echo "更新记录数: {$summary['updated_rows']}\n";
echo "更新字段数: {$summary['updated_fields']}\n";
echo "无需更新: {$summary['skipped_rows']}\n";
echo "失败: {$summary['failed_rows']}\n";
echo "耗时: " . round($duration, 2) . " 秒\n\n";

echo "各材料详情:\n";
echo str_repeat('-', 50) . "\n";

foreach ($summary['materials'] as $material => $material_summary) {
    echo sprintf(
        "%s: 扫描 %d, 更新记录 %d, 更新字段 %d, 无需更新 %d, 失败 %d\n",
        strtoupper($material),
        $material_summary['total_rows'],
        $material_summary['updated_rows'],
        $material_summary['updated_fields'],
        $material_summary['skipped_rows'],
        $material_summary['failed_rows']
    );
}

if (!empty($summary['failures'])) {
    echo "\n失败明细:\n";
    echo str_repeat('-', 50) . "\n";

    foreach ($summary['failures'] as $failure) {
        echo sprintf(
            "[%s] 日期: %s | 规格: %s | 原因: %s\n",
            strtoupper($failure['material']),
            $failure['date'],
            $failure['product_spec'],
            $failure['message']
        );
    }
}

echo "\n完成!\n";

/**
 * Normalize database decimal value to float or null.
 *
 * @param mixed $value Raw DB value.
 * @return float|null
 */
function ippgi_backfill_normalize_decimal($value) {
    if (null === $value || '' === $value) {
        return null;
    }

    return floatval($value);
}

/**
 * Round price for storage in decimal(10,2).
 *
 * @param float $value Price value.
 * @return float
 */
function ippgi_backfill_round_price($value) {
    return round((float) $value, 2);
}

/**
 * Detect the most common range factors already stored for a business day.
 *
 * @param wpdb   $wpdb WordPress database instance.
 * @param string $day_start Inclusive day start.
 * @param string $day_end Exclusive day end.
 * @param int    $lower_bps_min Minimum lower factor.
 * @param int    $lower_bps_max Maximum lower factor.
 * @param int    $upper_bps_min Minimum upper factor.
 * @param int    $upper_bps_max Maximum upper factor.
 * @return array{lower:int|null,upper:int|null}
 */
function ippgi_backfill_detect_day_bps(
    $wpdb,
    $day_start,
    $day_end,
    $lower_bps_min,
    $lower_bps_max,
    $upper_bps_min,
    $upper_bps_max
) {
    $lower_votes = array();
    $upper_votes = array();

    foreach (IPPGI_Prices_Database::TABLES as $table_suffix) {
        $table_name = $wpdb->prefix . $table_suffix;
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT price_usd, price_usd_min, price_usd_max,
                        price_tax_usd, price_tax_usd_min, price_tax_usd_max
                 FROM {$table_name}
                 WHERE statistics_time >= %s
                   AND statistics_time < %s
                   AND (
                       (price_usd > 0 AND price_usd_min > 0 AND price_usd_max > 0)
                       OR
                       (price_tax_usd > 0 AND price_tax_usd_min > 0 AND price_tax_usd_max > 0)
                   )
                 ORDER BY id ASC
                 LIMIT 200",
                $day_start,
                $day_end
            ),
            ARRAY_A
        );

        foreach ($rows as $row) {
            ippgi_backfill_record_factor_votes(
                $lower_votes,
                $upper_votes,
                $row['price_usd'],
                $row['price_usd_min'],
                $row['price_usd_max'],
                $lower_bps_min,
                $lower_bps_max,
                $upper_bps_min,
                $upper_bps_max
            );
            ippgi_backfill_record_factor_votes(
                $lower_votes,
                $upper_votes,
                $row['price_tax_usd'],
                $row['price_tax_usd_min'],
                $row['price_tax_usd_max'],
                $lower_bps_min,
                $lower_bps_max,
                $upper_bps_min,
                $upper_bps_max
            );
        }
    }

    arsort($lower_votes);
    arsort($upper_votes);

    return array(
        'lower' => !empty($lower_votes) ? intval(array_key_first($lower_votes)) : null,
        'upper' => !empty($upper_votes) ? intval(array_key_first($upper_votes)) : null,
    );
}

/**
 * Add inferred factors from one stored price range to the vote counts.
 *
 * @param array $lower_votes Lower-factor vote counts.
 * @param array $upper_votes Upper-factor vote counts.
 * @param mixed $price Point price.
 * @param mixed $price_min Stored minimum.
 * @param mixed $price_max Stored maximum.
 * @param int   $lower_bps_min Minimum lower factor.
 * @param int   $lower_bps_max Maximum lower factor.
 * @param int   $upper_bps_min Minimum upper factor.
 * @param int   $upper_bps_max Maximum upper factor.
 * @return void
 */
function ippgi_backfill_record_factor_votes(
    &$lower_votes,
    &$upper_votes,
    $price,
    $price_min,
    $price_max,
    $lower_bps_min,
    $lower_bps_max,
    $upper_bps_min,
    $upper_bps_max
) {
    $price = ippgi_backfill_normalize_decimal($price);
    $price_min = ippgi_backfill_normalize_decimal($price_min);
    $price_max = ippgi_backfill_normalize_decimal($price_max);

    if (null === $price || $price <= 0) {
        return;
    }

    if (null !== $price_min && $price_min < $price) {
        $lower_bps = (int) round((1 - ($price_min / $price)) * 10000);
        if ($lower_bps >= $lower_bps_min && $lower_bps <= $lower_bps_max) {
            $lower_votes[$lower_bps] = isset($lower_votes[$lower_bps])
                ? $lower_votes[$lower_bps] + 1
                : 1;
        }
    }

    if (null !== $price_max && $price_max > $price) {
        $upper_bps = (int) round((($price_max / $price) - 1) * 10000);
        if ($upper_bps >= $upper_bps_min && $upper_bps <= $upper_bps_max) {
            $upper_votes[$upper_bps] = isset($upper_votes[$upper_bps])
                ? $upper_votes[$upper_bps] + 1
                : 1;
        }
    }
}

/**
 * Check whether an existing decimal matches the expected stored value.
 *
 * @param mixed $value Raw DB value.
 * @param float $expected Expected value.
 * @return bool
 */
function ippgi_backfill_value_matches($value, $expected) {
    $normalized = ippgi_backfill_normalize_decimal($value);

    if (null === $normalized) {
        return false;
    }

    return abs($normalized - $expected) < 0.00001;
}
