<?php
/**
 * 补充缺失的价格数据
 *
 * 使用方法：
 * cd /home/html/www/ippgi && php import-missing-days.php
 *
 * 或指定日期范围：
 * cd /home/html/www/ippgi && php import-missing-days.php 2026-01-24 2026-01-27
 */

// 设置执行时间限制
set_time_limit(600);

// 加载 WordPress
require_once(__DIR__ . '/wp-load.php');

echo "=== 补充缺失的价格数据 ===\n\n";

// 获取日期参数
$from_date = isset($argv[1]) ? $argv[1] : '2026-01-24';
$to_date = isset($argv[2]) ? $argv[2] : date('Y-m-d');

// 转换为 API 需要的格式
$from = $from_date . ' 00:00:00';
$to = $to_date . ' 00:00:00';

echo "导入日期范围: {$from_date} 至 {$to_date}\n\n";

// 检查插件是否激活
if (!class_exists('IPPGI_Prices_API_Client')) {
    die("错误: IPPGI Prices 插件未激活\n");
}

// 获取 API 客户端实例
$api_client = new IPPGI_Prices_API_Client();

// 创建历史数据导入器
require_once(WP_PLUGIN_DIR . '/ippgi-prices/includes/class-historical-importer.php');
$importer = new IPPGI_Prices_Historical_Importer($api_client);

// 开始导入
$start_time = microtime(true);

echo "开始导入...\n";
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
        "%s: 总计 %d, 成功 %d, 失败 %d, 跳过 %d\n",
        strtoupper($material),
        $material_results['total_records'],
        $material_results['successful'],
        $material_results['failed'],
        $material_results['skipped']
    );
}

echo "\n完成!\n";
