<?php
/**
 * Collect and save current prices to database
 * Run this script before the 9:00 AM cache clear
 */

// Load WordPress
define('WP_USE_THEMES', false);
require('/Users/linger3048/Sites/php81.test/ippgi/wp-load.php');

echo "=== Collect Current Prices ===\n\n";
echo "This script will fetch current prices from API and save them to database.\n";
echo "Run this before the 9:00 AM cache clear to preserve current prices.\n\n";

// Load required classes
require_once(WP_PLUGIN_DIR . '/ippgi-prices/includes/class-cache-manager.php');
require_once(WP_PLUGIN_DIR . '/ippgi-prices/includes/class-api-client.php');
require_once(WP_PLUGIN_DIR . '/ippgi-prices/includes/class-currency-converter.php');
require_once(WP_PLUGIN_DIR . '/ippgi-prices/includes/class-current-price-collector.php');

// Initialize components
$cache_manager = new IPPGI_Prices_Cache_Manager();
$api_client = new IPPGI_Prices_API_Client($cache_manager);
$collector = new IPPGI_Prices_Current_Price_Collector($api_client);

echo "Fetching current prices from API...\n\n";

// Collect all current prices
$results = $collector->collect_all_current_prices(true);

// Display results
echo str_repeat("=", 80) . "\n";
echo "Collection Results\n";
echo str_repeat("=", 80) . "\n\n";

if ($results['success']) {
    echo "✅ Collection completed successfully!\n\n";
} else {
    echo "⚠️  Collection completed with errors\n\n";
}

echo "Summary:\n";
echo "  Total saved: " . number_format($results['total_saved']) . " records\n";
echo "  Total failed: " . number_format($results['total_failed']) . " records\n";
echo "  Duration: {$results['duration']} seconds\n";
echo "  Exchange rate: {$results['exchange_rate']} CNY per USD\n";
echo "  Statistics date: {$results['statistics_date']}\n";
echo "  Collected at: {$results['collected_at']}\n\n";

// Display results by material
echo "Results by material:\n";
echo str_repeat("-", 80) . "\n";
foreach ($results['materials'] as $material_type => $material_results) {
    $status = $material_results['failed'] > 0 ? '⚠️ ' : '✅';
    echo sprintf(
        "%s %s: %d saved, %d failed, %d skipped\n",
        $status,
        $material_type,
        $material_results['saved'],
        $material_results['failed'],
        $material_results['skipped']
    );
}
echo "\n";

// Display errors if any
if (!empty($results['errors'])) {
    echo "Errors:\n";
    echo str_repeat("-", 80) . "\n";
    foreach ($results['errors'] as $error) {
        echo "  ❌ {$error}\n";
    }
    echo "\n";
}

// Display database statistics
echo str_repeat("=", 80) . "\n";
echo "Database Statistics\n";
echo str_repeat("=", 80) . "\n\n";

$stats = $collector->get_statistics();
foreach ($stats as $material_type => $material_stats) {
    echo "{$material_type}:\n";
    echo "  Total records: " . number_format($material_stats['total']) . "\n";
    echo "  Latest date: {$material_stats['latest_date']}\n";
    echo "  Today's records: " . number_format($material_stats['today_count']) . "\n";
    echo "\n";
}

echo str_repeat("=", 80) . "\n";
echo "Done!\n\n";

echo "💡 Tip: Schedule this script to run daily before 9:00 AM:\n";
echo "   0 8 * * * cd /path/to/wordpress && php collect-current-prices.php\n\n";
