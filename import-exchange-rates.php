<?php
/**
 * Manually import historical exchange rates
 * This script allows you to populate the exchange rates table with historical data
 */

// Load WordPress
define('WP_USE_THEMES', false);
require('/Users/linger3048/Sites/php81.test/ippgi/wp-load.php');

echo "=== Import Historical Exchange Rates ===\n\n";

// Historical USD/CNY exchange rates (monthly averages from 2022-2026)
// Source: You should replace these with actual historical rates from a reliable source
$historical_rates = array(
    // 2022
    '2022-01-01' => 6.3757,
    '2022-02-01' => 6.3521,
    '2022-03-01' => 6.3398,
    '2022-04-01' => 6.3856,
    '2022-05-01' => 6.6981,
    '2022-06-01' => 6.7123,
    '2022-07-01' => 6.7456,
    '2022-08-01' => 6.8234,
    '2022-09-01' => 7.0123,
    '2022-10-01' => 7.1234,
    '2022-11-01' => 7.0987,
    '2022-12-01' => 6.9876,

    // 2023
    '2023-01-01' => 6.8972,
    '2023-02-01' => 6.8765,
    '2023-03-01' => 6.8543,
    '2023-04-01' => 6.8912,
    '2023-05-01' => 7.0234,
    '2023-06-01' => 7.2258,
    '2023-07-01' => 7.2456,
    '2023-08-01' => 7.2789,
    '2023-09-01' => 7.2912,
    '2023-10-01' => 7.3123,
    '2023-11-01' => 7.2987,
    '2023-12-01' => 7.1876,

    // 2024
    '2024-01-01' => 7.0999,
    '2024-02-01' => 7.1234,
    '2024-03-01' => 7.1987,
    '2024-04-01' => 7.2345,
    '2024-05-01' => 7.2567,
    '2024-06-01' => 7.2672,
    '2024-07-01' => 7.2456,
    '2024-08-01' => 7.1987,
    '2024-09-01' => 7.0876,
    '2024-10-01' => 7.0234,
    '2024-11-01' => 6.9987,
    '2024-12-01' => 6.9765,

    // 2025
    '2025-01-01' => 7.3258,
    '2025-02-01' => 7.3123,
    '2025-03-01' => 7.2987,
    '2025-04-01' => 7.2765,
    '2025-05-01' => 7.2543,
    '2025-06-01' => 7.2456,
    '2025-07-01' => 7.2234,
    '2025-08-01' => 7.1987,
    '2025-09-01' => 7.1765,
    '2025-10-01' => 7.0987,
    '2025-11-01' => 7.0234,
    '2025-12-01' => 6.9876,

    // 2026
    '2026-01-01' => 6.9607,
    '2026-01-15' => 6.9607,
    '2026-01-23' => 6.9607,
);

global $wpdb;
$table_name = $wpdb->prefix . IPPGI_Prices_Currency_Converter::HISTORICAL_RATES_TABLE;

echo "Importing " . count($historical_rates) . " historical exchange rates...\n\n";

$imported = 0;
$updated = 0;

foreach ($historical_rates as $date => $rate) {
    // Check if rate already exists
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT rate FROM {$table_name} WHERE rate_date = %s",
        $date
    ));

    $result = $wpdb->replace(
        $table_name,
        array(
            'rate_date' => $date,
            'rate' => $rate,
            'created_at' => current_time('mysql'),
        ),
        array('%s', '%f', '%s')
    );

    if ($result) {
        if ($existing) {
            echo "✓ Updated rate for {$date}: {$rate} CNY per USD\n";
            $updated++;
        } else {
            echo "✓ Imported rate for {$date}: {$rate} CNY per USD\n";
            $imported++;
        }
    } else {
        echo "✗ Failed to import rate for {$date}\n";
    }
}

echo "\n=== Import Complete ===\n";
echo "Imported: {$imported} new rates\n";
echo "Updated: {$updated} existing rates\n";
echo "Total: " . ($imported + $updated) . " rates\n\n";

// Verify import
$total_rates = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
echo "Total rates in database: {$total_rates}\n";

// Show date range
$min_date = $wpdb->get_var("SELECT MIN(rate_date) FROM {$table_name}");
$max_date = $wpdb->get_var("SELECT MAX(rate_date) FROM {$table_name}");
echo "Date range: {$min_date} to {$max_date}\n";

echo "\n=== Done ===\n";
echo "\nNote: The rates in this script are examples. Please replace them with actual\n";
echo "historical rates from a reliable source such as:\n";
echo "- Bank of China historical data\n";
echo "- ExchangeRate-API.com\n";
echo "- Fixer.io\n";
echo "- Open Exchange Rates\n";
