<?php
/**
 * 补充缺失的价格数据
 *
 * 使用方法：
 * php import-missing-days.php <开始日期> <结束日期>
 *
 * 示例：
 * php import-missing-days.php 2026-01-24 2026-01-27
 *
 * 如果不指定日期，默认导入昨天的数据
 *
 * @package IPPGI
 * @since 1.0.0
 */

// 设置执行时间限制（10分钟）
set_time_limit(600);
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 加载 WordPress
require_once(__DIR__ . '/wp-load.php');

echo "=== IPPGI 历史价格数据导入工具 ===\n\n";

// 显示帮助信息
if (isset($argv[1]) && in_array($argv[1], ['-h', '--help', 'help'])) {
    echo "用法: php import-missing-days.php [开始日期] [结束日期]\n\n";
    echo "参数:\n";
    echo "  开始日期  格式 YYYY-MM-DD，默认为昨天\n";
    echo "  结束日期  格式 YYYY-MM-DD，默认为开始日期\n\n";
    echo "示例:\n";
    echo "  php import-missing-days.php 2026-01-24 2026-01-27  # 导入指定日期范围\n";
    echo "  php import-missing-days.php 2026-01-24             # 导入单天数据\n";
    echo "  php import-missing-days.php                        # 导入昨天数据\n";
    exit(0);
}

// 获取日期参数
$from_date = isset($argv[1]) ? $argv[1] : date('Y-m-d', strtotime('-1 day'));
$to_date = isset($argv[2]) ? $argv[2] : $from_date;

// 验证日期格式
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to_date)) {
    die("错误: 日期格式不正确，请使用 YYYY-MM-DD 格式\n");
}

// 转换为 API 需要的格式
$from = $from_date . ' 00:00:00';
$to = $to_date . ' 00:00:00';

echo "导入日期范围: {$from_date} 至 {$to_date}\n\n";

// 获取插件实例
if (!class_exists('IPPGI_Prices')) {
    die("错误: IPPGI Prices 插件未激活\n");
}

$plugin = IPPGI_Prices::get_instance();
$api_client = $plugin->api_client;

echo "插件已加载\n";

// 创建历史数据导入器
$importer = new IPPGI_Prices_Historical_Importer($api_client);

// 开始导入
$start_time = microtime(true);

echo "开始导入，请耐心等待...\n";
echo str_repeat('-', 50) . "\n";

$results = $importer->import_all_materials($from, $to);

$duration = microtime(true) - $start_time;

// 输出结果
echo "\n" . str_repeat('=', 50) . "\n";
echo "导入完成!\n";
echo str_repeat('=', 50) . "\n\n";

echo "总记录数: {$results['total_records']}\n";
echo "成功: {$results['successful']}\n";
echo "失败: {$results['failed']}\n";
echo "跳过: {$results['skipped']}\n";
echo "耗时: " . round($duration, 2) . " 秒\n\n";

echo "各材料详情:\n";
echo str_repeat('-', 50) . "\n";

foreach ($results['materials'] as $material => $material_results) {
    echo sprintf(
        "%s: 成功 %d, 失败 %d, 跳过 %d\n",
        strtoupper($material),
        $material_results['successful'],
        $material_results['failed'],
        $material_results['skipped']
    );
}

echo "\n完成!\n";
