<?php
/**
 * Import daily exchange rates for recent 2 years (2024-2026)
 * This is faster and covers the most important recent data
 */

// Load WordPress
define('WP_USE_THEMES', false);
require('/Users/linger3048/Sites/php81.test/ippgi/wp-load.php');

echo "=== Import Daily Rates for Recent 2 Years ===\n";
echo "Date range: 2024-01-01 to 2026-01-23\n";
echo "Data source: European Central Bank\n\n";

$start_date = '2024-01-01';
$end_date = '2026-01-23';

$start = new DateTime($start_date);
$end = new DateTime($end_date);
$total_days = $start->diff($end)->days + 1;

echo "Total days: {$total_days}\n";
echo "Estimated time: " . round($total_days * 0.2 / 60, 1) . " minutes\n\n";

global $wpdb;
$table_name = $wpdb->prefix . IPPGI_Prices_Currency_Converter::HISTORICAL_RATES_TABLE;

$imported = 0;
$updated = 0;
$skipped = 0;

$current = clone $start;
$last_progress = 0;

while ($current <= $end) {
    $date = $current->format('Y-m-d');

    // Check if exists
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT rate FROM {$table_name} WHERE rate_date = %s",
        $date
    ));

    if ($existing) {
        $skipped++;
        $current->modify('+1 day');
        continue;
    }

    // Fetch from API
    $url = "https://api.frankfurter.app/{$date}?from=USD&to=CNY";
    $response = wp_remote_get($url, array('timeout' => 10));

    if (!is_wp_error($response)) {
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['rates']['CNY'])) {
            $rate = $data['rates']['CNY'];
            $actual_date = $data['date'];

            $wpdb->replace(
                $table_name,
                array(
                    'rate_date' => $actual_date,
                    'rate' => $rate,
                    'created_at' => current_time('mysql'),
                ),
                array('%s', '%f', '%s')
            );

            $imported++;

            // Show progress every 10%
            $progress = floor((($imported + $skipped) / $total_days) * 100);
            if ($progress >= $last_progress + 10) {
                echo "Progress: {$progress}% - Imported: {$imported}, Skipped: {$skipped}\n";
                $last_progress = $progress;
            }
        } else {
            $skipped++;
        }
    }

    usleep(200000); // 200ms delay
    $current->modify('+1 day');
}

echo "\n=== Complete ===\n";
echo "Imported: {$imported} daily rates\n";
echo "Skipped: {$skipped} (already exists or weekend/holiday)\n\n";

// Show statistics
$total_rates = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
echo "Total rates in database: {$total_rates}\n";

// Show sample
echo "\nSample daily rates:\n";
$samples = $wpdb->get_results("
    SELECT rate_date, rate
    FROM {$table_name}
    WHERE rate_date >= '2024-01-01'
    ORDER BY rate_date
    LIMIT 10
");

foreach ($samples as $row) {
    echo "  {$row->rate_date}: {$row->rate} CNY per USD\n";
}

echo "\n✅ Recent 2 years of daily rates imported!\n";
