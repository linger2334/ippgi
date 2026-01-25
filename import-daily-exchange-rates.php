<?php
/**
 * Import DAILY historical exchange rates from Frankfurter API
 * This provides much higher precision than monthly rates
 *
 * Data source: European Central Bank via frankfurter.app
 */

// Load WordPress
define('WP_USE_THEMES', false);
require('/Users/linger3048/Sites/php81.test/ippgi/wp-load.php');

echo "=== Import DAILY Historical Exchange Rates ===\n";
echo "Data source: European Central Bank (via frankfurter.app)\n";
echo "Precision: Daily rates for accurate historical conversion\n\n";

// Date range: 2022-01-01 to 2026-01-23
$start_date = '2022-01-01';
$end_date = '2026-01-23';

echo "Date range: {$start_date} to {$end_date}\n";

// Calculate total days
$start = new DateTime($start_date);
$end = new DateTime($end_date);
$interval = $start->diff($end);
$total_days = $interval->days + 1;

echo "Total days: {$total_days}\n";
echo "This will make {$total_days} API requests with delays to be respectful.\n";
echo "Estimated time: " . round($total_days * 0.2 / 60, 1) . " minutes\n\n";

// Ask for confirmation
echo "Do you want to proceed? (yes/no): ";
$handle = fopen("php://stdin", "r");
$line = fgets($handle);
$answer = trim(strtolower($line));
fclose($handle);

if ($answer !== 'yes' && $answer !== 'y') {
    echo "\nImport cancelled.\n";
    exit(0);
}

echo "\n=== Starting Import ===\n\n";

global $wpdb;
$table_name = $wpdb->prefix . IPPGI_Prices_Currency_Converter::HISTORICAL_RATES_TABLE;

$imported = 0;
$updated = 0;
$failed = 0;
$skipped = 0;

$current = clone $start;
$progress_interval = 50; // Show progress every 50 days

while ($current <= $end) {
    $date = $current->format('Y-m-d');

    // Check if rate already exists
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT rate FROM {$table_name} WHERE rate_date = %s",
        $date
    ));

    if ($existing && $answer !== 'force') {
        $skipped++;
        $current->modify('+1 day');
        continue;
    }

    // Fetch from Frankfurter API
    $url = "https://api.frankfurter.app/{$date}?from=USD&to=CNY";

    $response = wp_remote_get($url, array(
        'timeout' => 10,
    ));

    if (is_wp_error($response)) {
        echo "✗ Failed to fetch rate for {$date}: " . $response->get_error_message() . "\n";
        $failed++;
        $current->modify('+1 day');
        continue;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (!isset($data['rates']['CNY'])) {
        // Weekend or holiday - no data available
        $skipped++;
        $current->modify('+1 day');
        continue;
    }

    $rate = $data['rates']['CNY'];
    $actual_date = $data['date']; // API might return nearest available date

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
            $updated++;
        } else {
            $imported++;
        }

        // Show progress periodically
        $total_processed = $imported + $updated + $failed + $skipped;
        if ($total_processed % $progress_interval == 0) {
            $percentage = round(($total_processed / $total_days) * 100, 1);
            echo "Progress: {$total_processed}/{$total_days} ({$percentage}%) - ";
            echo "Imported: {$imported}, Updated: {$updated}, Skipped: {$skipped}, Failed: {$failed}\n";
        }
    } else {
        echo "✗ Failed to save rate for {$actual_date}\n";
        $failed++;
    }

    // Add delay to be respectful to the API (200ms = 5 requests per second)
    usleep(200000);

    $current->modify('+1 day');
}

echo "\n=== Import Complete ===\n";
echo "Imported: {$imported} new rates\n";
echo "Updated: {$updated} existing rates\n";
echo "Skipped: {$skipped} (weekends/holidays or already exists)\n";
echo "Failed: {$failed} requests\n";
echo "Total processed: " . ($imported + $updated) . " rates\n\n";

// Verify import
$total_rates = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
echo "Total rates in database: {$total_rates}\n";

// Show date range
$min_date = $wpdb->get_var("SELECT MIN(rate_date) FROM {$table_name}");
$max_date = $wpdb->get_var("SELECT MAX(rate_date) FROM {$table_name}");
echo "Date range: {$min_date} to {$max_date}\n\n";

// Show sample rates from different months
echo "Sample daily rates:\n";
$sample_dates = array(
    '2024-06-01', '2024-06-15', '2024-06-30',
    '2023-01-01', '2023-01-15', '2023-01-31'
);

foreach ($sample_dates as $date) {
    $rate = $wpdb->get_var($wpdb->prepare(
        "SELECT rate FROM {$table_name} WHERE rate_date = %s",
        $date
    ));
    if ($rate) {
        echo "  {$date}: {$rate} CNY per USD\n";
    }
}

echo "\n=== Done ===\n";
echo "\nNow you have DAILY exchange rates for maximum precision!\n";
echo "Each historical price record will use the exact rate from its date.\n";
