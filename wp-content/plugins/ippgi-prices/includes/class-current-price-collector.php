<?php
/**
 * Current Price Collector Class
 * Collects and saves current prices to database before cache refresh
 *
 * @package IPPGI_Prices
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class IPPGI_Prices_Current_Price_Collector {

    /**
     * Table name mapping for each material type
     */
    const TABLE_NAMES = array(
        'GI'       => 'prices_gi',
        'GL'       => 'prices_gl',
        'PPGI'     => 'prices_ppgi',
        'HRC'      => 'prices_hrc',
        'CRC Hard' => 'prices_crc_hard',
        'AL'       => 'prices_al',
    );

    /**
     * API client instance
     */
    private $api_client;

    /**
     * Constructor
     *
     * @param IPPGI_Prices_API_Client $api_client API client instance
     */
    public function __construct($api_client) {
        $this->api_client = $api_client;
    }

    /**
     * Collect and save current prices for all materials
     *
     * @param bool $force_refresh Force refresh from API
     * @return array Results summary
     */
    public function collect_all_current_prices($force_refresh = true) {
        global $wpdb;

        $start_time = microtime(true);
        $results = array(
            'success' => true,
            'materials' => array(),
            'total_saved' => 0,
            'total_failed' => 0,
            'errors' => array(),
        );

        // Fetch price list from API (or cache)
        $price_list = $this->api_client->fetch_price_list($force_refresh);

        if (is_wp_error($price_list)) {
            $results['success'] = false;
            $results['errors'][] = 'Failed to fetch price list: ' . $price_list->get_error_message();
            return $results;
        }

        // Extract exchange rate from cached price data
        // The exchange rate is already embedded in each price record during API fetch
        $exchange_rate = $this->extract_exchange_rate_from_price_list($price_list);

        if (false === $exchange_rate) {
            // Fallback: get current exchange rate if not found in cache
            $exchange_rate = IPPGI_Prices_Currency_Converter::get_exchange_rate();
            error_log('IPPGI Prices: Exchange rate not found in cache, using current rate: ' . $exchange_rate);
        } else {
            error_log('IPPGI Prices: Using cached exchange rate from price list: ' . $exchange_rate);
        }

        // Determine the rate date from the price list fetch time
        $rate_date = isset($price_list['fetched_at'])
            ? date('Y-m-d', strtotime($price_list['fetched_at']))
            : current_time('Y-m-d');

        // Save exchange rate to database
        $this->save_exchange_rate($rate_date, $exchange_rate);

        $current_time = current_time('mysql');
        $statistics_date = isset($price_list['date']) ? $price_list['date'] : current_time('Y-m-d H:i:s');

        // Process each category
        foreach ($price_list['categories'] as $material_type => $category_data) {
            $material_results = $this->save_material_prices(
                $material_type,
                $category_data,
                $exchange_rate,
                $statistics_date,
                $current_time
            );

            $results['materials'][$material_type] = $material_results;
            $results['total_saved'] += $material_results['saved'];
            $results['total_failed'] += $material_results['failed'];

            if (!empty($material_results['errors'])) {
                $results['errors'] = array_merge($results['errors'], $material_results['errors']);
            }
        }

        $duration = microtime(true) - $start_time;
        $results['duration'] = round($duration, 2);
        $results['exchange_rate'] = $exchange_rate;
        $results['rate_date'] = $rate_date;
        $results['statistics_date'] = $statistics_date;
        $results['collected_at'] = $current_time;

        return $results;
    }

    /**
     * Save exchange rate to database
     *
     * @param string $rate_date Date in YYYY-MM-DD format
     * @param float $exchange_rate Exchange rate
     * @return bool Success status
     */
    private function save_exchange_rate($rate_date, $exchange_rate) {
        global $wpdb;

        $table_name = $wpdb->prefix . IPPGI_Prices_Currency_Converter::HISTORICAL_RATES_TABLE;

        $result = $wpdb->replace(
            $table_name,
            array(
                'rate_date' => $rate_date,
                'rate' => $exchange_rate,
                'created_at' => current_time('mysql'),
            ),
            array('%s', '%f', '%s')
        );

        if (false === $result) {
            error_log(sprintf('IPPGI Prices: Failed to save exchange rate for %s: %s', $rate_date, $wpdb->last_error));
            return false;
        }

        error_log(sprintf('IPPGI Prices: Saved exchange rate for %s: %.6f', $rate_date, $exchange_rate));
        return true;
    }

    /**
     * Extract exchange rate from price list data
     * The exchange rate is embedded in each price record during API fetch
     *
     * @param array $price_list Price list data
     * @return float|false Exchange rate or false if not found
     */
    private function extract_exchange_rate_from_price_list($price_list) {
        if (!isset($price_list['categories']) || !is_array($price_list['categories'])) {
            return false;
        }

        // Loop through categories to find the first price record with exchange_rate
        foreach ($price_list['categories'] as $category_data) {
            if (!isset($category_data['result']) || !is_array($category_data['result'])) {
                continue;
            }

            // Loop through width groups
            foreach ($category_data['result'] as $items) {
                if (!is_array($items)) {
                    continue;
                }

                // Loop through items to find exchange_rate
                foreach ($items as $item) {
                    if (isset($item['exchange_rate']) && $item['exchange_rate'] > 0) {
                        return (float) $item['exchange_rate'];
                    }
                }
            }
        }

        return false;
    }

    /**
     * Save prices for a single material type
     *
     * @param string $material_type Material type (GI, GL, etc.)
     * @param array  $category_data Category data from API
     * @param float  $exchange_rate Exchange rate
     * @param string $statistics_date Statistics date
     * @param string $current_time Current timestamp
     * @return array Results for this material
     */
    private function save_material_prices($material_type, $category_data, $exchange_rate, $statistics_date, $current_time) {
        global $wpdb;

        $results = array(
            'saved' => 0,
            'failed' => 0,
            'skipped' => 0,
            'errors' => array(),
        );

        // Get table name
        if (!isset(self::TABLE_NAMES[$material_type])) {
            $results['errors'][] = "Unknown material type: {$material_type}";
            return $results;
        }

        $table_name = $wpdb->prefix . self::TABLE_NAMES[$material_type];

        // Check if result data exists
        if (!isset($category_data['result']) || !is_array($category_data['result'])) {
            $results['errors'][] = "No result data for {$material_type}";
            return $results;
        }

        // Process each width group
        foreach ($category_data['result'] as $width => $items) {
            if (!is_array($items)) {
                continue;
            }

            // Process each item (thickness variation)
            foreach ($items as $item) {
                // Validate required fields
                if (!isset($item['id']) || !isset($item['lastprice'])) {
                    $results['skipped']++;
                    continue;
                }

                // Skip invalid records
                if ($item['id'] == 0 || $item['lastprice'] == 0) {
                    $results['skipped']++;
                    continue;
                }

                // Prepare data for insertion
                $insert_data = $this->prepare_price_data($item, $material_type, $exchange_rate, $statistics_date, $current_time);

                // Use REPLACE to handle duplicates (will update if exists)
                $inserted = $wpdb->replace(
                    $table_name,
                    $insert_data,
                    array(
                        '%d',  // id
                        '%s',  // product_spec
                        '%s',  // statistics_time
                        '%d',  // timestamp
                        '%f',  // price_cny
                        '%f',  // price_usd
                        '%f',  // price_tax_cny
                        '%f',  // price_tax_usd
                        '%f',  // exchange_rate
                        '%s',  // site_id
                        '%s',  // category_id
                        '%s',  // width
                        '%s',  // thickness
                        '%s',  // created_at
                    )
                );

                if (false === $inserted) {
                    $results['failed']++;
                    $results['errors'][] = sprintf(
                        'Failed to insert %s record (ID: %d): %s',
                        $material_type,
                        $item['id'],
                        $wpdb->last_error
                    );
                } else {
                    $results['saved']++;
                }
            }
        }

        return $results;
    }

    /**
     * Prepare price data for database insertion
     *
     * @param array  $item API item data
     * @param string $material_type Material type
     * @param float  $exchange_rate Exchange rate
     * @param string $statistics_date Statistics date
     * @param string $current_time Current timestamp
     * @return array Prepared data
     */
    private function prepare_price_data($item, $material_type, $exchange_rate, $statistics_date, $current_time) {
        // Extract basic fields
        $id = isset($item['id']) ? intval($item['id']) : 0;
        $product_spec = isset($item['productSpec']) ? $item['productSpec'] : '';
        $width = isset($item['width']) ? strval($item['width']) : '0';
        $thickness = isset($item['thickness']) ? strval($item['thickness']) : '0';

        // Get site_id and category_id from API client constants
        $site_id = IPPGI_Prices_API_Client::SITE_ID;
        $category_id = isset(IPPGI_Prices_API_Client::CATEGORY_IDS[$material_type])
            ? IPPGI_Prices_API_Client::CATEGORY_IDS[$material_type]
            : '';

        // Get prices (note: API uses lastprice and lastpriceTax)
        $price_cny = isset($item['lastprice']) ? floatval($item['lastprice']) : 0;
        $price_tax_cny = isset($item['lastpriceTax']) ? floatval($item['lastpriceTax']) : 0;

        // Convert to USD
        $price_usd = IPPGI_Prices_Currency_Converter::cny_to_usd($price_cny, $exchange_rate);
        $price_tax_usd = IPPGI_Prices_Currency_Converter::cny_to_usd($price_tax_cny, $exchange_rate);

        // Convert statistics_date to timestamp
        $timestamp = strtotime($statistics_date);

        return array(
            'id' => $id,
            'product_spec' => $product_spec,
            'statistics_time' => $statistics_date,
            'timestamp' => $timestamp,
            'price_cny' => $price_cny,
            'price_usd' => $price_usd,
            'price_tax_cny' => $price_tax_cny,
            'price_tax_usd' => $price_tax_usd,
            'exchange_rate' => $exchange_rate,
            'site_id' => $site_id,
            'category_id' => $category_id,
            'width' => $width,
            'thickness' => $thickness,
            'created_at' => $current_time,
        );
    }

    /**
     * Get statistics for current prices in database
     *
     * @return array Statistics
     */
    public function get_statistics() {
        global $wpdb;

        $stats = array();

        foreach (self::TABLE_NAMES as $material_type => $table_suffix) {
            $table_name = $wpdb->prefix . $table_suffix;

            // Get total count
            $total = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");

            // Get latest record date
            $latest = $wpdb->get_var("SELECT MAX(statistics_time) FROM {$table_name}");

            // Get today's count
            $today = current_time('Y-m-d');
            $today_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} WHERE DATE(statistics_time) = %s",
                $today
            ));

            $stats[$material_type] = array(
                'total' => intval($total),
                'latest_date' => $latest,
                'today_count' => intval($today_count),
            );
        }

        return $stats;
    }
}
