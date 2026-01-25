<?php
/**
 * Import REAL historical exchange rates from Frankfurter API
 * Data source: European Central Bank via frankfurter.app
 */

// Load WordPress
define('WP_USE_THEMES', false);
require('/Users/linger3048/Sites/php81.test/ippgi/wp-load.php');

echo "=== Import REAL Historical Exchange Rates ===\n";
echo "Data source: European Central Bank (via frankfurter.app)\n\n";

// Generate dates for the first day of each month from 2022 to 2026
$dates = array();
for ($year = 2022; $year <= 2026; $year++) {
    $end_month = ($year == 2026) ? 1 : 12; // Only January for 2026
    for ($month = 1; $month <= $end_month; $month++) {
        $dates[] = sprintf('%04d-%02d-01', $year, $month);
    }
}

// Add some additional recent dates
$dates[] = '2026-01-15';
$dates[] = '2026-01-23';

echo "Fetching exchange rates for " . count($dates) . " dates...\n\n";

global $wpdb;
$table_name = $wpdb->prefix . IPPGI_Prices_Currency_Converter::HISTORICAL_RATES_TABLE;

$imported = 0;
$updated = 0;
$failed = 0;

foreach ($dates as $date) {
    // Fetch from Frankfurter API
    $url = "https://api.frankfurter.app/{$date}?from=USD&to=CNY";

    $response = wp_remote_get($url, array(
        'timeout' => 10,
    ));

    if (is_wp_error($response)) {
        echo "✗ Failed to fetch rate for {$date}: " . $response->get_error_message() . "\n";
        $failed++;
        continue;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (!isset($data['rates']['CNY'])) {
        echo "✗ No CNY rate found for {$date}\n";
        $failed++;
        continue;
    }

    $rate = $data['rates']['CNY'];
    $actual_date = $data['date']; // API might return nearest available date

    // Check if rate already exists
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT rate FROM {$table_name} WHERE rate_date = %s",
        $actual_date
    ));

    $result = $wpdb->replace(
        $table_name,
        array(
            'rate_date' => $actual_date,
            'rate' => $rate,
            'created_at' => current_time('mysql'),
        ),
        array('%s', '%f', '%s')
    );

    if ($result) {
        if ($existing) {
            echo "✓ Updated rate for {$actual_date}: {$rate} CNY per USD\n";
            $updated++;
        } else {
            echo "✓ Imported rate for {$actual_date}: {$rate} CNY per USD\n";
            $imported++;
        }
    } else {
        echo "✗ Failed to save rate for {$actual_date}\n";
        $failed++;
    }

    // Small delay to be nice to the API
    usleep(100000); // 100ms
}

echo "\n=== Import Complete ===\n";
echo "Imported: {$imported} new rates\n";
echo "Updated: {$updated} existing rates\n";
echo "Failed: {$failed} requests\n";
echo "Total: " . ($imported + $updated) . " rates\n\n";

// Verify import
$total_rates = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
echo "Total rates in database: {$total_rates}\n";

// Show date range
$min_date = $wpdb->get_var("SELECT MIN(rate_date) FROM {$table_name}");
$max_date = $wpdb->get_var("SELECT MAX(rate_date) FROM {$table_name}");
echo "Date range: {$min_date} to {$max_date}\n\n";

// Show some sample rates
echo "Sample rates:\n";
$sample_rates = $wpdb->get_results("
    SELECT rate_date, rate
    FROM {$table_name}
    WHERE rate_date IN ('2022-01-01', '2023-01-01', '2024-01-01', '2025-01-01', '2026-01-01')
    ORDER BY rate_date
");

foreach ($sample_rates as $rate) {
    echo "  {$rate->rate_date}: {$rate->rate} CNY per USD\n";
}

echo "\n=== Done ===\n";
echo "\nData source: European Central Bank\n";
echo "API: https://www.frankfurter.app/\n";
echo "These are REAL historical exchange rates!\n";
