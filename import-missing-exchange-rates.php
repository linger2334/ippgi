<?php
/**
 * 补充指定日期范围内的历史汇率数据。
 *
 * 使用方法：
 * php import-missing-exchange-rates.php <开始日期> <结束日期>
 *
 * 示例：
 * php import-missing-exchange-rates.php 2026-03-20 2026-04-21
 *
 * 如果不指定日期，默认导入昨天的数据。
 *
 * @package IPPGI
 * @since 1.0.0
 */

set_time_limit(600);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/wp-load.php';

echo "=== IPPGI 历史汇率补数工具 ===\n\n";

if (isset($argv[1]) && in_array($argv[1], array('-h', '--help', 'help'), true)) {
    echo "用法: php import-missing-exchange-rates.php [开始日期] [结束日期]\n\n";
    echo "参数:\n";
    echo "  开始日期  格式 YYYY-MM-DD，默认为昨天\n";
    echo "  结束日期  格式 YYYY-MM-DD，默认为开始日期\n\n";
    echo "示例:\n";
    echo "  php import-missing-exchange-rates.php 2026-03-20 2026-04-21  # 补指定日期范围汇率\n";
    echo "  php import-missing-exchange-rates.php 2026-03-20             # 补单天汇率\n";
    echo "  php import-missing-exchange-rates.php                        # 补昨天汇率\n";
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

if (!class_exists('IPPGI_Prices_Currency_Converter')) {
    die("错误: IPPGI Prices 插件未激活或未正确加载\n");
}

global $wpdb;

$table_name = $wpdb->prefix . IPPGI_Prices_Currency_Converter::HISTORICAL_RATES_TABLE;
$timezone = wp_timezone();
$start = DateTimeImmutable::createFromFormat('Y-m-d', $from_date, $timezone);
$end = DateTimeImmutable::createFromFormat('Y-m-d', $to_date, $timezone);

if (!$start || !$end) {
    die("错误: 无法解析日期范围\n");
}

$summary = array(
    'total_days' => 0,
    'imported' => 0,
    'updated' => 0,
    'failed' => 0,
    'failures' => array(),
);

echo "补数日期范围: {$from_date} 至 {$to_date}\n\n";
echo "开始补充汇率，请耐心等待...\n";
echo str_repeat('-', 50) . "\n";

$start_time = microtime(true);
$current = $start;

while ($current <= $end) {
    $date = $current->format('Y-m-d');
    $summary['total_days']++;

    $existing_rate = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT rate FROM {$table_name} WHERE rate_date = %s",
            $date
        )
    );

    $rate = IPPGI_Prices_Currency_Converter::refresh_historical_rate_from_aliyun($date);

    if (false === $rate) {
        $summary['failed']++;
        $summary['failures'][] = array(
            'date' => $date,
            'message' => 'Aliyun 未返回有效历史汇率',
        );
        echo sprintf("[%s] 失败: Aliyun 未返回有效历史汇率\n", $date);
        $current = $current->modify('+1 day');
        continue;
    }

    if (null === $existing_rate) {
        $summary['imported']++;
        echo sprintf("[%s] 新增: %.6f CNY per USD\n", $date, $rate);
    } else {
        $summary['updated']++;
        echo sprintf("[%s] 更新: %.6f -> %.6f CNY per USD\n", $date, (float) $existing_rate, $rate);
    }

    $current = $current->modify('+1 day');
}

$duration = microtime(true) - $start_time;

echo "\n" . str_repeat('=', 50) . "\n";
echo "补数完成!\n";
echo str_repeat('=', 50) . "\n\n";

echo "扫描天数: {$summary['total_days']}\n";
echo "新增: {$summary['imported']}\n";
echo "更新: {$summary['updated']}\n";
echo "失败: {$summary['failed']}\n";
echo "耗时: " . round($duration, 2) . " 秒\n\n";

if (!empty($summary['failures'])) {
    echo "失败明细:\n";
    echo str_repeat('-', 50) . "\n";

    foreach ($summary['failures'] as $failure) {
        echo sprintf(
            "日期: %s | 原因: %s\n",
            $failure['date'],
            $failure['message']
        );
    }
}

echo "\n完成!\n";
