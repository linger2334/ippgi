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
    const STATISTICS_URL = IPPGI_Prices_API_Client::STATISTICS_URL;

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
     * @param bool $only_missing Import only product specs without a row in the requested range.
     * @return array Import results
     */
    public function import_all_materials($from = '2022-01-23 00:00:00', $to = '2026-01-23 00:00:00', $only_missing = false) {
        $results = array(
            'total_records' => 0,
            'successful' => 0,
            'failed' => 0,
            'skipped' => 0,
            'materials' => array(),
            'failures' => array(),
        );

        error_log(sprintf(
            'IPPGI Prices: Starting historical data import from %s to %s (using historical exchange rates)',
            $from,
            $to
        ));
        $this->output_progress(sprintf(
            '[补数进度] 开始导入历史价格: %s -> %s',
            $this->extract_date($from),
            $this->extract_date($to)
        ));

        // First, get price list to know what product specs exist
        $price_list = $this->api_client->get_price_list();

        if (is_wp_error($price_list)) {
            error_log('IPPGI Prices: Failed to get price list for import');
            $results['failed']++;
            $results['failures'][] = array(
                'material' => 'ALL',
                'product_spec' => '',
                'date' => '',
                'date_range' => $this->format_date_range($from, $to),
                'stage' => 'price_list',
                'message' => $price_list->get_error_message(),
            );
            return $results;
        }

        // Iterate through each material category
        $material_types = array_keys(IPPGI_Prices_API_Client::CATEGORY_IDS);
        $material_total = count($material_types);
        $material_index = 0;

        foreach (IPPGI_Prices_API_Client::CATEGORY_IDS as $material_type => $category_id) {
            $material_index++;
            $this->output_progress(sprintf(
                '[补数进度] 品类 %d/%d: %s',
                $material_index,
                $material_total,
                strtoupper($material_type)
            ));
            $material_results = $this->import_material_data(
                $material_type,
                $category_id,
                $price_list,
                $from,
                $to,
                $only_missing
            );

            $results['materials'][$material_type] = $material_results;
            $results['total_records'] += $material_results['total_records'];
            $results['successful'] += $material_results['successful'];
            $results['failed'] += $material_results['failed'];
            $results['skipped'] += $material_results['skipped'];
            $results['failures'] = array_merge($results['failures'], $material_results['failures']);
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
     * @param bool $only_missing Import only product specs missing in the requested range.
     * @return array Import results for this material
     */
    private function import_material_data($material_type, $category_id, $price_list, $from, $to, $only_missing = false) {
        $results = array(
            'total_records' => 0,
            'successful' => 0,
            'failed' => 0,
            'skipped' => 0,
            'product_specs' => array(),
            'failures' => array(),
        );

        // Get product specs for this material from price list
        $product_specs = $this->extract_product_specs($material_type, $price_list);

        if ($only_missing && !empty($product_specs)) {
            $original_count = count($product_specs);
            $product_specs = $this->filter_missing_product_specs(
                $material_type,
                $product_specs,
                $from,
                $to
            );
            $this->output_progress(sprintf(
                '[补数进度] %s 仅补缺失规格: %d/%d',
                strtoupper($material_type),
                count($product_specs),
                $original_count
            ));
        }

        if (empty($product_specs)) {
            error_log("IPPGI Prices: No product specs require import for {$material_type}");
            return $results;
        }

        error_log(sprintf(
            'IPPGI Prices: Importing %s - %d product specs',
            $material_type,
            count($product_specs)
        ));

        // Fetch historical data for each product spec
        $total_specs = count($product_specs);
        foreach ($product_specs as $index => $product_spec) {
            $this->output_progress(sprintf(
                '[补数进度] %s 规格 %d/%d: %s',
                strtoupper($material_type),
                $index + 1,
                $total_specs,
                $product_spec
            ));
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
            $results['failures'] = array_merge($results['failures'], $spec_results['failures']);

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

        if (isset($price_list['categories'][$material_type]['result'])
            && is_array($price_list['categories'][$material_type]['result'])) {
            $result = $price_list['categories'][$material_type]['result'];

            // Iterate through widths and items.
            foreach ($result as $width => $items) {
                if (!is_array($items)) {
                    continue;
                }

                foreach ($items as $item) {
                    if (!empty($item['productSpec'])) {
                        $product_specs[] = (string) $item['productSpec'];
                    }
                }
            }
        }

        $product_specs = array_values(array_unique($product_specs));
        if (!empty($product_specs)) {
            return $product_specs;
        }

        // A single upstream category can fail while other category caches remain usable.
        // Use the latest stored snapshot so the missing category can still be repaired.
        $fallback = $this->get_latest_stored_product_specs($material_type);
        if (!empty($fallback['product_specs'])) {
            error_log(sprintf(
                'IPPGI Prices: No cached product specs for %s; using %d specs from stored snapshot %s',
                $material_type,
                count($fallback['product_specs']),
                $fallback['date']
            ));
            $this->output_progress(sprintf(
                '[补数进度] %s 当前缓存无规格，改用历史表 %s 的 %d 个规格',
                strtoupper($material_type),
                $fallback['date'],
                count($fallback['product_specs'])
            ));
        }

        return $fallback['product_specs'];
    }

    /**
     * Get product specs from the most recent stored snapshot.
     *
     * @param string $material_type Material type.
     * @return array
     */
    private function get_latest_stored_product_specs($material_type) {
        global $wpdb;

        $result = array(
            'date' => '',
            'product_specs' => array(),
        );

        $table_name = IPPGI_Prices_Database::get_table_name($material_type);
        if (!$table_name) {
            return $result;
        }

        $latest_date = $wpdb->get_var("SELECT DATE(MAX(statistics_time)) FROM {$table_name}");
        if (empty($latest_date)) {
            return $result;
        }

        try {
            $day_start = new DateTimeImmutable($latest_date . ' 00:00:00', wp_timezone());
        } catch (Exception $e) {
            return $result;
        }

        $day_end = $day_start->modify('+1 day');
        $product_specs = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT product_spec
                 FROM {$table_name}
                 WHERE statistics_time >= %s
                   AND statistics_time < %s
                   AND product_spec <> ''
                 ORDER BY product_spec ASC",
                $day_start->format('Y-m-d H:i:s'),
                $day_end->format('Y-m-d H:i:s')
            )
        );

        $result['date'] = $day_start->format('Y-m-d');
        $result['product_specs'] = array_values(array_unique(array_filter(array_map(
            'strval',
            is_array($product_specs) ? $product_specs : array()
        ))));

        return $result;
    }

    /**
     * Keep only product specs that have no row inside the requested range.
     *
     * @param string $material_type Material type.
     * @param array  $product_specs Candidate product specs.
     * @param string $from Start datetime.
     * @param string $to End datetime.
     * @return array
     */
    private function filter_missing_product_specs($material_type, $product_specs, $from, $to) {
        global $wpdb;

        $table_name = IPPGI_Prices_Database::get_table_name($material_type);
        if (!$table_name) {
            return $product_specs;
        }

        $existing_specs = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT product_spec
                 FROM {$table_name}
                 WHERE statistics_time >= %s
                   AND statistics_time <= %s",
                $from,
                $to
            )
        );

        if (empty($existing_specs)) {
            return array_values($product_specs);
        }

        $existing_lookup = array_fill_keys(array_map('strval', $existing_specs), true);

        return array_values(array_filter(
            $product_specs,
            static function ($product_spec) use ($existing_lookup) {
                return !isset($existing_lookup[(string) $product_spec]);
            }
        ));
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
            'failures' => array(),
        );

        $error_message = '';
        $list = $this->fetch_historical_list($category_id, $product_spec, $from, $to, $error_message);

        if (false === $list) {
            if ($this->is_multi_day_range($from, $to)) {
                error_log(sprintf(
                    'IPPGI Prices: Falling back to daily import for %s after range request failed: %s',
                    $product_spec,
                    $error_message
                ));
                $this->output_progress(sprintf(
                    '[补数进度] 区间请求失败，切换按天补数: %s | 原因: %s',
                    $product_spec,
                    $error_message
                ));

                return $this->fetch_and_store_historical_data_by_day(
                    $material_type,
                    $category_id,
                    $product_spec,
                    $from,
                    $to
                );
            }

            $results['failed']++;
            $this->add_failure(
                $results,
                $material_type,
                $product_spec,
                $this->extract_date($from),
                '',
                'fetch',
                $error_message
            );
            return $results;
        }

        $list = $this->filter_historical_records_to_range($list, $from, $to);
        $results['total_records'] = count($list);
        $this->store_historical_records($material_type, $product_spec, $list, $results);

        return $results;
    }

    /**
     * Fetch and store historical data one day at a time.
     *
     * @param string $material_type Material type
     * @param string $category_id Category ID
     * @param string $product_spec Product specification
     * @param string $from Start date
     * @param string $to End date
     * @return array Results for this product spec
     */
    private function fetch_and_store_historical_data_by_day($material_type, $category_id, $product_spec, $from, $to) {
        $results = array(
            'total_records' => 0,
            'successful' => 0,
            'failed' => 0,
            'skipped' => 0,
            'failures' => array(),
        );

        $dates = $this->get_dates_in_range($from, $to);
        if (empty($dates)) {
            $results['failed']++;
            $this->add_failure(
                $results,
                $material_type,
                $product_spec,
                '',
                $this->format_date_range($from, $to),
                'date_parse',
                'Unable to split failed range into daily imports'
            );
            return $results;
        }

        $total_dates = count($dates);
        foreach ($dates as $index => $date) {
            $this->output_progress(sprintf(
                '[补数进度] %s 按天回退 %d/%d: %s',
                $product_spec,
                $index + 1,
                $total_dates,
                $date
            ));
            $day_start = $date . ' 00:00:00';
            $day_end = $date . ' 23:59:59';
            $error_message = '';
            $list = $this->fetch_historical_list($category_id, $product_spec, $day_start, $day_end, $error_message);

            if (false === $list) {
                $results['failed']++;
                $this->add_failure(
                    $results,
                    $material_type,
                    $product_spec,
                    $date,
                    '',
                    'fetch',
                    $error_message
                );
                usleep(100000);
                continue;
            }

            $list = $this->filter_historical_records_to_range($list, $day_start, $day_end);
            $results['total_records'] += count($list);
            $this->store_historical_records($material_type, $product_spec, $list, $results);
            usleep(100000);
        }

        return $results;
    }

    /**
     * Fetch historical list data from API.
     *
     * @param string $category_id Category ID
     * @param string $product_spec Product specification
     * @param string $from Start date
     * @param string $to End date
     * @param string $error_message Error message output
     * @return array|false Historical list or false on failure
     */
    private function fetch_historical_list($category_id, $product_spec, $from, $to, &$error_message) {
        $error_message = '';

        // Build URL with query parameters
        $url = add_query_arg(array(
            'siteId' => IPPGI_Prices_API_Client::SITE_ID,
            'productSpec' => $product_spec,
            'from' => $from,
            'to' => $to,
            'categoryId' => $category_id,
        ), self::STATISTICS_URL);

        $max_attempts = 3;
        $response = false;

        for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {
            // Make API request
            $response = wp_remote_get($url, array(
                'headers' => array(
                    'phone' => IPPGI_Prices_API_Client::API_PHONE,
                ),
                'timeout' => 60,
            ));

            if (!is_wp_error($response)) {
                break;
            }

            $error_message = $response->get_error_message();
            if (!$this->should_retry_historical_request($error_message) || $attempt === $max_attempts) {
                error_log("IPPGI Prices: Failed to fetch historical data for {$product_spec}: " . $error_message);
                return false;
            }

            $this->output_progress(sprintf(
                '[补数进度] 瞬时网络错误，准备重试 %d/%d: %s | 原因: %s',
                $attempt + 1,
                $max_attempts,
                $product_spec,
                $error_message
            ));
            usleep($attempt * 300000);
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!isset($data['success']) || !$data['success'] || !isset($data['result']['list'])) {
            $error_message = 'Invalid response';
            if (isset($data['message'])) {
                $error_message .= ': ' . (string) $data['message'];
            } elseif (isset($data['msg'])) {
                $error_message .= ': ' . (string) $data['msg'];
            }
            error_log("IPPGI Prices: Invalid response for {$product_spec}");
            return false;
        }

        return $data['result']['list'];
    }

    /**
     * Ignore records outside the requested range even if the upstream API returns them.
     *
     * Records without a usable time stay in the list so the normal storage path
     * can report them as normalization failures.
     *
     * @param array  $list Historical records.
     * @param string $from Start datetime.
     * @param string $to End datetime.
     * @return array
     */
    private function filter_historical_records_to_range($list, $from, $to) {
        if (!is_array($list)) {
            return array();
        }

        return array_values(array_filter(
            $list,
            function ($record) use ($from, $to) {
                $statistics_time = $this->resolve_statistics_time($record);
                if ('' === $statistics_time) {
                    return true;
                }

                return $statistics_time >= $from && $statistics_time <= $to;
            }
        ));
    }

    /**
     * Store historical records and update result counters.
     *
     * @param string $material_type Material type
     * @param string $product_spec Product specification
     * @param array $list Historical record list
     * @param array $results Results array passed by reference
     */
    private function store_historical_records($material_type, $product_spec, $list, &$results) {
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

            // Normalize statistics time because daily fallback responses may omit satisticsTime.
            $statistics_time = $this->resolve_statistics_time($record);
            if ('' === $statistics_time) {
                $results['failed']++;
                $this->add_failure(
                    $results,
                    $material_type,
                    $product_spec,
                    '',
                    '',
                    'normalize',
                    'Missing satisticsTime and timestamp in historical record'
                );
                continue;
            }

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
                'price_usd' => $price_usd,
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
                global $wpdb;
                $message = 'Database insert/update failed';
                if (!empty($wpdb->last_error)) {
                    $message .= ': ' . $wpdb->last_error;
                }
                $this->add_failure(
                    $results,
                    $material_type,
                    $product_spec,
                    $date,
                    '',
                    'database',
                    $message
                );
            }
        }
    }

    /**
     * Add a failure detail to the results array.
     *
     * @param array $results Results array passed by reference
     * @param string $material_type Material type
     * @param string $product_spec Product specification
     * @param string $date Failed date
     * @param string $date_range Failed date range
     * @param string $stage Failure stage
     * @param string $message Failure message
     */
    private function add_failure(&$results, $material_type, $product_spec, $date, $date_range, $stage, $message) {
        $results['failures'][] = array(
            'material' => $material_type,
            'product_spec' => $product_spec,
            'date' => $date,
            'date_range' => $date_range,
            'stage' => $stage,
            'message' => $message,
        );
    }

    /**
     * Extract YYYY-MM-DD from a datetime string.
     *
     * @param string $datetime Datetime string
     * @return string Date
     */
    private function extract_date($datetime) {
        return substr((string) $datetime, 0, 10);
    }

    /**
     * Print progress in CLI runs without affecting web requests.
     *
     * @param string $message Progress message
     * @return void
     */
    private function output_progress($message) {
        if (!$this->is_cli_context()) {
            return;
        }

        echo $message . PHP_EOL;
        if (function_exists('flush')) {
            flush();
        }
    }

    /**
     * Determine whether importer is running in CLI context.
     *
     * @return bool
     */
    private function is_cli_context() {
        return defined('STDIN') || 'cli' === PHP_SAPI || 'phpdbg' === PHP_SAPI;
    }

    /**
     * Determine whether a historical API error is worth retrying.
     *
     * @param string $error_message Remote error message
     * @return bool
     */
    private function should_retry_historical_request($error_message) {
        $message = strtolower((string) $error_message);

        return false !== strpos($message, 'curl error 35')
            || false !== strpos($message, 'curl error 28')
            || false !== strpos($message, 'tls connect error')
            || false !== strpos($message, 'connection timeout')
            || false !== strpos($message, 'timed out')
            || false !== strpos($message, 'unexpected eof while reading');
    }

    /**
     * Resolve a usable statistics datetime from API payload.
     *
     * @param array $record Historical record
     * @return string Datetime in Y-m-d H:i:s format, or empty string on failure
     */
    private function resolve_statistics_time($record) {
        if (!empty($record['satisticsTime'])) {
            try {
                return (new DateTimeImmutable((string) $record['satisticsTime'], wp_timezone()))
                    ->setTimezone(wp_timezone())
                    ->format('Y-m-d H:i:s');
            } catch (Exception $e) {
                return '';
            }
        }

        if (empty($record['timestamp']) || !is_numeric($record['timestamp'])) {
            return '';
        }

        $timestamp_seconds = (int) floor(((float) $record['timestamp']) / 1000);
        if ($timestamp_seconds <= 0) {
            return '';
        }

        try {
            return (new DateTimeImmutable('@' . $timestamp_seconds))
                ->setTimezone(wp_timezone())
                ->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            return '';
        }
    }

    /**
     * Format a date range for reporting.
     *
     * @param string $from Start datetime
     * @param string $to End datetime
     * @return string Date range
     */
    private function format_date_range($from, $to) {
        return $this->extract_date($from) . ' 至 ' . $this->extract_date($to);
    }

    /**
     * Check whether a date range spans more than one day.
     *
     * @param string $from Start datetime
     * @param string $to End datetime
     * @return bool True when range spans multiple dates
     */
    private function is_multi_day_range($from, $to) {
        return $this->extract_date($from) !== $this->extract_date($to);
    }

    /**
     * Get all dates in an inclusive date range.
     *
     * @param string $from Start datetime
     * @param string $to End datetime
     * @return array Dates in YYYY-MM-DD format
     */
    private function get_dates_in_range($from, $to) {
        $timezone = wp_timezone();
        $start_date = $this->extract_date($from);
        $end_date = $this->extract_date($to);

        $start = DateTimeImmutable::createFromFormat('Y-m-d', $start_date, $timezone);
        $end = DateTimeImmutable::createFromFormat('Y-m-d', $end_date, $timezone);

        if (!$start || !$end || $start > $end) {
            return array();
        }

        $dates = array();
        $current = $start;
        while ($current <= $end) {
            $dates[] = $current->format('Y-m-d');
            $current = $current->modify('+1 day');
        }

        return $dates;
    }
}
