<?php
/**
 * Legacy maintenance script.
 * Historical price repricing is no longer supported because RMB source columns
 * are no longer stored in the history tables.
 *
 * Usage:
 *   php backfill-aliyun-rates-and-reprice.php
 *   php backfill-aliyun-rates-and-reprice.php 2025-09-23 2026-03-23
 *   php backfill-aliyun-rates-and-reprice.php --dry-run
 *   php backfill-aliyun-rates-and-reprice.php 2025-09-23 2026-03-23 --yes
 */

set_time_limit(0);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/wp-load.php';

if (!class_exists('IPPGI_Prices') || !class_exists('IPPGI_Prices_Currency_Converter')) {
    fwrite(STDERR, "错误: IPPGI Prices 插件未加载。\n");
    exit(1);
}

$timezone = wp_timezone();
$today = new DateTimeImmutable('today', $timezone);
$default_end = $today->format('Y-m-d');
$default_start = $today->modify('-6 months')->format('Y-m-d');

$args = array_slice($argv, 1);
$options = array(
    'dry_run' => false,
    'yes' => false,
);
$dates = array();

foreach ($args as $arg) {
    if ('--dry-run' === $arg) {
        $options['dry_run'] = true;
        continue;
    }

    if ('--yes' === $arg || '-y' === $arg) {
        $options['yes'] = true;
        continue;
    }

    if (in_array($arg, array('--help', '-h', 'help'), true)) {
        echo "用法:\n";
        echo "  php backfill-aliyun-rates-and-reprice.php [开始日期] [结束日期] [--dry-run] [--yes]\n\n";
        echo "说明:\n";
        echo "  - 该脚本已停用。\n";
        echo "  - 历史价格表不再保留人民币底稿字段，无法安全重算历史 USD 价格。\n";
        echo "  - 如需补历史价格，请使用 import-missing-days.php 重新按原始 API 数据补数。\n\n";
        echo "示例:\n";
        echo "  php backfill-aliyun-rates-and-reprice.php\n";
        echo "  php backfill-aliyun-rates-and-reprice.php 2025-09-23 2026-03-23\n";
        echo "  php backfill-aliyun-rates-and-reprice.php 2025-09-23 2026-03-23 --dry-run\n";
        exit(0);
    }

    $dates[] = $arg;
}

$start_date = isset($dates[0]) ? $dates[0] : $default_start;
$end_date = isset($dates[1]) ? $dates[1] : $default_end;

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
    fwrite(STDERR, "错误: 日期格式必须为 YYYY-MM-DD。\n");
    exit(1);
}

$start = DateTimeImmutable::createFromFormat('Y-m-d', $start_date, $timezone);
$end = DateTimeImmutable::createFromFormat('Y-m-d', $end_date, $timezone);

if (!$start || !$end) {
    fwrite(STDERR, "错误: 无法解析开始或结束日期。\n");
    exit(1);
}

if ($start > $end) {
    fwrite(STDERR, "错误: 开始日期不能晚于结束日期。\n");
    exit(1);
}

fwrite(STDERR, "错误: backfill-aliyun-rates-and-reprice.php 已停用。历史价格表不再保留人民币底稿字段，无法安全重算历史 USD 价格。请改用 php import-missing-days.php [开始日期] [结束日期] 重新补数。\n");
exit(1);

$total_days = (int) $start->diff($end)->format('%a') + 1;

echo "=== 阿里云汇率回填与价格重算工具 ===\n\n";
echo "站点时区: " . $timezone->getName() . "\n";
echo "开始日期: {$start_date}\n";
echo "结束日期: {$end_date}\n";
echo "总天数: {$total_days}\n";
echo "模式: " . ($options['dry_run'] ? "DRY RUN（只演练，不写库）" : "执行写库") . "\n\n";

echo "将执行以下操作：\n";
echo "1. 逐日从阿里云重新获取 USD/CNY 历史汇率\n";
echo "2. 更新 {$GLOBALS['wpdb']->prefix}" . IPPGI_Prices_Currency_Converter::HISTORICAL_RATES_TABLE . " 表\n";
echo "3. 按日期重算 6 张价格历史表中的 `price_usd`、`price_tax_usd`、`exchange_rate`\n\n";

if (!$options['yes']) {
    echo "是否继续？(yes/no): ";
    $handle = fopen('php://stdin', 'r');
    $answer = trim(strtolower((string) fgets($handle)));
    fclose($handle);

    if (!in_array($answer, array('yes', 'y'), true)) {
        echo "已取消。\n";
        exit(0);
    }
}

global $wpdb;

$price_tables = array();
foreach (IPPGI_Prices_Database::TABLES as $material_type => $table_suffix) {
    $price_tables[$material_type] = $wpdb->prefix . $table_suffix;
}

$summary = array(
    'rates_fetched' => 0,
    'rates_updated' => 0,
    'rates_failed' => 0,
    'price_rows_updated' => 0,
    'dates_processed' => 0,
    'dates_failed' => array(),
    'materials' => array(),
);

foreach (array_keys($price_tables) as $material_type) {
    $summary['materials'][$material_type] = 0;
}

$current = $start;

while ($current <= $end) {
    $date = $current->format('Y-m-d');
    $day_start = $date . ' 00:00:00';
    $day_end = $current->modify('+1 day')->format('Y-m-d') . ' 00:00:00';

    echo sprintf("[%s] 处理中...\n", $date);

    $existing_rate = $wpdb->get_var($wpdb->prepare(
        "SELECT rate FROM {$wpdb->prefix}" . IPPGI_Prices_Currency_Converter::HISTORICAL_RATES_TABLE . " WHERE rate_date = %s",
        $date
    ));

    $rate = $options['dry_run']
        ? IPPGI_Prices_Currency_Converter::get_historical_rate_from_aliyun($date)
        : IPPGI_Prices_Currency_Converter::refresh_historical_rate_from_aliyun($date);

    if (false === $rate) {
        echo "  ✗ 阿里云历史汇率获取失败，跳过当天价格重算\n";
        $summary['rates_failed']++;
        $summary['dates_failed'][] = $date;
        $current = $current->modify('+1 day');
        usleep(150000);
        continue;
    }

    $summary['rates_fetched']++;
    if (null !== $existing_rate) {
        $summary['rates_updated']++;
    }

    echo sprintf("  ✓ 汇率: 1 USD = %.6f CNY\n", $rate);

    foreach ($price_tables as $material_type => $table_name) {
        if ($options['dry_run']) {
            $rows = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} WHERE statistics_time >= %s AND statistics_time < %s",
                $day_start,
                $day_end
            ));

            $summary['materials'][$material_type] += $rows;
            $summary['price_rows_updated'] += $rows;

            if ($rows > 0) {
                echo sprintf("    - %s: 将更新 %d 条记录\n", $material_type, $rows);
            }

            continue;
        }

        $updated_rows = $wpdb->query($wpdb->prepare(
            "UPDATE {$table_name}
             SET price_usd = ROUND(price_cny / %f, 2),
                 price_tax_usd = ROUND(price_tax_cny / %f, 2),
                 exchange_rate = %f
             WHERE statistics_time >= %s AND statistics_time < %s",
            $rate,
            $rate,
            $rate,
            $day_start,
            $day_end
        ));

        if (false === $updated_rows) {
            echo sprintf("    ✗ %s 更新失败: %s\n", $material_type, $wpdb->last_error);
            continue;
        }

        $updated_rows = (int) $updated_rows;
        $summary['materials'][$material_type] += $updated_rows;
        $summary['price_rows_updated'] += $updated_rows;

        if ($updated_rows > 0) {
            echo sprintf("    ✓ %s: 更新 %d 条记录\n", $material_type, $updated_rows);
        }
    }

    $summary['dates_processed']++;
    $current = $current->modify('+1 day');
    usleep(150000);
}

echo "\n=== 处理完成 ===\n";
echo "汇率获取成功: {$summary['rates_fetched']} 天\n";
echo "汇率覆盖更新: {$summary['rates_updated']} 天\n";
echo "汇率获取失败: {$summary['rates_failed']} 天\n";
echo "价格记录更新总数: {$summary['price_rows_updated']}\n";
echo "成功处理日期数: {$summary['dates_processed']}\n\n";

echo "按材料更新统计:\n";
foreach ($summary['materials'] as $material_type => $count) {
    echo sprintf("  %s: %d 条\n", $material_type, $count);
}

if (!empty($summary['dates_failed'])) {
    echo "\n失败日期:\n";
    foreach ($summary['dates_failed'] as $failed_date) {
        echo "  - {$failed_date}\n";
    }
}

echo "\n完成。\n";
