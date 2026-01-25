<?php
/**
 * Test and run historical data import
 */

// Load WordPress
define('WP_USE_THEMES', false);
require('/Users/linger3048/Sites/php81.test/ippgi/wp-load.php');

echo "=== IPPGI Historical Data Import ===\n\n";

// Step 1: Create database tables
echo "Step 1: Creating database tables...\n";
IPPGI_Prices_Database::create_tables();
echo "✓ Tables created\n\n";

// Step 2: Test currency converter
echo "Step 2: Testing currency converter...\n";
$exchange_rate = IPPGI_Prices_Currency_Converter::get_exchange_rate();
echo "Exchange rate (CNY to USD): 1 USD = {$exchange_rate} CNY\n";

$test_cny = 3450;
$test_usd = IPPGI_Prices_Currency_Converter::cny_to_usd($test_cny, $exchange_rate);
echo "Test conversion: {$test_cny} CNY = {$test_usd} USD\n\n";

// Step 3: Test historical data fetch for one product
echo "Step 3: Testing historical data fetch for one product...\n";
$plugin = ippgi_prices();
$importer = new IPPGI_Prices_Historical_Importer($plugin->api_client);

// Test with a single product spec
$test_url = add_query_arg(array(
    'siteId' => IPPGI_Prices_API_Client::SITE_ID,
    'productSpec' => '1457211766760558593_1200_0.4_民用镀锌',
    'from' => '2026-01-13 00:00:00',
    'to' => '2026-01-23 00:00:00',
    'categoryId' => '1457211766760558593',
), 'https://api.rendui.com/v1/jec/rendui/prices/statistics');

echo "Test URL: {$test_url}\n";

$response = wp_remote_get($test_url, array(
    'headers' => array('phone' => '18210056805'),
    'timeout' => 30,
));

if (is_wp_error($response)) {
    echo "ERROR: " . $response->get_error_message() . "\n";
} else {
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (isset($data['success']) && $data['success']) {
        $count = isset($data['result']['list']) ? count($data['result']['list']) : 0;
        echo "✓ Successfully fetched {$count} historical records\n";

        if ($count > 0) {
            $first_record = $data['result']['list'][0];
            echo "Sample record:\n";
            echo "  Date: {$first_record['satisticsTime']}\n";
            echo "  Price (CNY): {$first_record['price']}\n";
            echo "  Price (USD): " . IPPGI_Prices_Currency_Converter::cny_to_usd($first_record['price'], $exchange_rate) . "\n";
        }
    } else {
        echo "ERROR: API returned error\n";
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    }
}

echo "\n";

// Step 4: Ask user if they want to proceed with full import
echo "=== Ready to Import ===\n";
echo "This will import historical data for ALL materials from 2022-01-23 to 2026-01-23.\n";
echo "This may take several minutes and make many API requests.\n\n";
echo "Do you want to proceed? (yes/no): ";

$handle = fopen("php://stdin", "r");
$line = fgets($handle);
$answer = trim(strtolower($line));
fclose($handle);

if ($answer !== 'yes' && $answer !== 'y') {
    echo "\nImport cancelled.\n";
    exit(0);
}

echo "\n=== Starting Full Import ===\n\n";

$start_time = microtime(true);

$results = $importer->import_all_materials(
    '2022-01-23 00:00:00',
    '2026-01-23 00:00:00'
);

$end_time = microtime(true);
$duration = $end_time - $start_time;

echo "\n=== Import Complete ===\n\n";
echo "Duration: " . round($duration, 2) . " seconds\n";
echo "Total records processed: {$results['total_records']}\n";
echo "Successfully imported: {$results['successful']}\n";
echo "Failed: {$results['failed']}\n";
echo "Skipped (invalid data): {$results['skipped']}\n\n";

echo "Results by material:\n";
foreach ($results['materials'] as $material => $material_results) {
    echo "  {$material}: {$material_results['successful']} records\n";
}

echo "\n=== Done ===\n";
