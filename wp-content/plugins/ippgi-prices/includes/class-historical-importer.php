<?php
/**
 * Historical Data Importer Class
 * Fetches and stores historical price data
 *
 * @package IPPGI_Prices
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class IPPGI_Prices_Historical_Importer {

    /**
     * Statistics API endpoint
     */
    const STATISTICS_URL = 'https://api.rendui.com/v1/jec/rendui/prices/statistics';

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
     * Import historical data for all materials
     *
     * @param string $from Start date (YYYY-MM-DD HH:MM:SS)
     * @param string $to End date (YYYY-MM-DD HH:MM:SS)
     * @return array Import results
     */
    public function import_all_materials($from = '2022-01-23 00:00:00', $to = '2026-01-23 00:00:00') {
        $results = array(
            'total_records' => 0,
            'successful' => 0,
            'failed' => 0,
            'skipped' => 0,
            'materials' => array(),
        );

        error_log(sprintf(
            'IPPGI Prices: Starting historical data import from %s to %s (using historical exchange rates)',
            $from,
            $to
        ));

        // First, get price list to know what product specs exist
        $price_list = $this->api_client->get_price_list();

        if (is_wp_error($price_list)) {
            error_log('IPPGI Prices: Failed to get price list for import');
            return $results;
        }

        // Iterate through each material category
        foreach (IPPGI_Prices_API_Client::CATEGORY_IDS as $material_type => $category_id) {
            $material_results = $this->import_material_data(
                $material_type,
                $category_id,
                $price_list,
                $from,
                $to
            );

            $results['materials'][$material_type] = $material_results;
            $results['total_records'] += $material_results['total_records'];
            $results['successful'] += $material_results['successful'];
            $results['failed'] += $material_results['failed'];
            $results['skipped'] += $material_results['skipped'];
        }

        error_log(sprintf(
            'IPPGI Prices: Import complete - Total: %d, Success: %d, Failed: %d, Skipped: %d',
            $results['total_records'],
            $results['successful'],
            $results['failed'],
            $results['skipped']
        ));

        return $results;
    }

    /**
     * Import historical data for a single material
     *
     * @param string $material_type Material type
     * @param string $category_id Category ID
     * @param array $price_list Price list data
     * @param string $from Start date
     * @param string $to End date
     * @return array Import results for this material
     */
    private function import_material_data($material_type, $category_id, $price_list, $from, $to) {
        $results = array(
            'total_records' => 0,
            'successful' => 0,
            'failed' => 0,
            'skipped' => 0,
            'product_specs' => array(),
        );

        // Get product specs for this material from price list
        $product_specs = $this->extract_product_specs($material_type, $price_list);

        if (empty($product_specs)) {
            error_log("IPPGI Prices: No product specs found for {$material_type}");
            return $results;
        }

        error_log(sprintf(
            'IPPGI Prices: Importing %s - %d product specs',
            $material_type,
            count($product_specs)
        ));

        // Fetch historical data for each product spec
        foreach ($product_specs as $product_spec) {
            $spec_results = $this->fetch_and_store_historical_data(
                $material_type,
                $category_id,
                $product_spec,
                $from,
                $to
            );

            $results['product_specs'][$product_spec] = $spec_results;
            $results['total_records'] += $spec_results['total_records'];
            $results['successful'] += $spec_results['successful'];
            $results['failed'] += $spec_results['failed'];
            $results['skipped'] += $spec_results['skipped'];

            // Add small delay to avoid overwhelming the API
            usleep(100000); // 100ms delay
        }

        return $results;
    }

    /**
     * Extract product specs from price list for a material
     *
     * @param string $material_type Material type
     * @param array $price_list Price list data
     * @return array Array of product specs
     */
    private function extract_product_specs($material_type, $price_list) {
        $product_specs = array();

        if (!isset($price_list['categories'][$material_type]['result'])) {
            return $product_specs;
        }

        $result = $price_list['categories'][$material_type]['result'];

        // Iterate through widths and items
        foreach ($result as $width => $items) {
            foreach ($items as $item) {
                if (isset($item['productSpec'])) {
                    $product_specs[] = $item['productSpec'];
                }
            }
        }

        return array_unique($product_specs);
    }

    /**
     * Fetch and store historical data for a product spec
     *
     * @param string $material_type Material type
     * @param string $category_id Category ID
     * @param string $product_spec Product specification
     * @param string $from Start date
     * @param string $to End date
     * @return array Results for this product spec
     */
    private function fetch_and_store_historical_data($material_type, $category_id, $product_spec, $from, $to) {
        $results = array(
            'total_records' => 0,
            'successful' => 0,
            'failed' => 0,
            'skipped' => 0,
        );

        // Build URL with query parameters
        $url = add_query_arg(array(
            'siteId' => IPPGI_Prices_API_Client::SITE_ID,
            'productSpec' => $product_spec,
            'from' => $from,
            'to' => $to,
            'categoryId' => $category_id,
        ), self::STATISTICS_URL);

        // Make API request
        $response = wp_remote_get($url, array(
            'headers' => array(
                'phone' => '18210056805',
            ),
            'timeout' => 60,
        ));

        if (is_wp_error($response)) {
            error_log("IPPGI Prices: Failed to fetch historical data for {$product_spec}: " . $response->get_error_message());
            $results['failed']++;
            return $results;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!isset($data['success']) || !$data['success'] || !isset($data['result']['list'])) {
            error_log("IPPGI Prices: Invalid response for {$product_spec}");
            $results['failed']++;
            return $results;
        }

        $list = $data['result']['list'];
        $results['total_records'] = count($list);

        // Parse product spec to extract width and thickness
        $spec_parts = explode('_', $product_spec);
        $width = isset($spec_parts[1]) ? $spec_parts[1] : '';
        $thickness = isset($spec_parts[2]) ? $spec_parts[2] : '';

        // Store each record
        foreach ($list as $record) {
            // Skip invalid records
            if (empty($record['id']) || $record['id'] == 0 ||
                empty($record['price']) || $record['price'] == 0) {
                $results['skipped']++;
                continue;
            }

            // Extract date from satisticsTime (format: YYYY-MM-DD HH:MM:SS)
            $statistics_time = $record['satisticsTime'];
            $date = substr($statistics_time, 0, 10); // Extract YYYY-MM-DD

            // Get exchange rate for this specific date
            $exchange_rate = IPPGI_Prices_Currency_Converter::get_exchange_rate($date);

            // Convert prices to USD using the historical exchange rate
            $price_usd = IPPGI_Prices_Currency_Converter::cny_to_usd($record['price'], $exchange_rate);
            $price_tax_usd = IPPGI_Prices_Currency_Converter::cny_to_usd($record['priceTax'], $exchange_rate);

            // Prepare data for insertion
            $insert_data = array(
                'product_spec' => $record['productSpec'],
                'statistics_time' => $statistics_time,
                'timestamp' => $record['timestamp'],
                'price_cny' => $record['price'],
                'price_usd' => $price_usd,
                'price_tax_cny' => $record['priceTax'],
                'price_tax_usd' => $price_tax_usd,
                'exchange_rate' => $exchange_rate,
                'site_id' => $record['siteId'],
                'category_id' => $record['categoryId'],
                'width' => $width,
                'thickness' => $thickness,
            );

            // Insert into database
            $insert_id = IPPGI_Prices_Database::insert_price_record($material_type, $insert_data);

            if ($insert_id) {
                $results['successful']++;
            } else {
                $results['failed']++;
            }
        }

        return $results;
    }
}
