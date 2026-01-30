<?php
/**
 * REST API Class
 * Exposes REST API endpoints for frontend consumption
 *
 * @package IPPGI_Prices
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class IPPGI_Prices_REST_API {

    /**
     * API namespace
     */
    const NAMESPACE = 'ippgi-prices/v1';

    /**
     * API client instance
     */
    private $api_client;

    /**
     * Cache manager instance
     */
    private $cache_manager;

    /**
     * Constructor
     *
     * @param IPPGI_Prices_API_Client $api_client API client instance
     * @param IPPGI_Prices_Cache_Manager $cache_manager Cache manager instance
     */
    public function __construct($api_client, $cache_manager) {
        $this->api_client = $api_client;
        $this->cache_manager = $cache_manager;

        // Register REST API routes
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    /**
     * Register REST API routes
     */
    public function register_routes() {
        // Get price list
        register_rest_route(self::NAMESPACE, '/prices', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_price_list'),
            'permission_callback' => '__return_true',
        ));

        // Get prices by category
        register_rest_route(self::NAMESPACE, '/prices/category', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_prices_by_category'),
            'permission_callback' => '__return_true',
            'args' => array(
                'category' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'description' => 'Product category: GI, GL, PPGI, HRC, CRC Hard, AL',
                ),
                'date' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'default' => '',
                    'description' => 'Date in YYYY-MM-DD or YYYY-MM-DD HH:MM:SS format',
                ),
            ),
        ));

        // Get real-time price
        register_rest_route(self::NAMESPACE, '/price', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_realtime_price'),
            'permission_callback' => '__return_true',
            'args' => array(
                'productSpec' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'description' => 'Full product spec (e.g., "1482328115005964290_1000_0.11_彩涂")',
                ),
                'categoryId' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'description' => 'Category ID',
                ),
                'date' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'default' => '',
                    'description' => 'Date in YYYY-MM-DD format (defaults to today)',
                ),
            ),
        ));

        // Get price statistics (for chart)
        register_rest_route(self::NAMESPACE, '/statistics', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_price_statistics'),
            'permission_callback' => '__return_true',
            'args' => array(
                'productSpec' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'description' => 'Full product spec',
                ),
                'categoryId' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'description' => 'Category ID',
                ),
                'from' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'description' => 'Start datetime (YYYY-MM-DD HH:MM:SS)',
                ),
                'to' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'description' => 'End datetime (YYYY-MM-DD HH:MM:SS)',
                ),
            ),
        ));

        // Get historical prices from database (for chart)
        register_rest_route(self::NAMESPACE, '/historical', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_historical_prices'),
            'permission_callback' => '__return_true',
            'args' => array(
                'productSpec' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'description' => 'Full product spec',
                ),
                'category' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'description' => 'Category name (GI, GL, PPGI, HRC, CRC Hard, AL)',
                ),
                'range' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'default' => '',
                    'description' => 'Time range (1m, 6m, 1y, 2y, 3y, 4y)',
                ),
                'from' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'default' => '',
                    'description' => 'Custom start date (YYYY-MM-DD)',
                ),
                'to' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'default' => '',
                    'description' => 'Custom end date (YYYY-MM-DD)',
                ),
            ),
        ));

        // Get cache statistics
        register_rest_route(self::NAMESPACE, '/cache-stats', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_cache_stats'),
            'permission_callback' => array($this, 'check_admin_permission'),
        ));

        // Clear all caches (admin only)
        register_rest_route(self::NAMESPACE, '/clear-cache', array(
            'methods' => 'POST',
            'callback' => array($this, 'clear_cache'),
            'permission_callback' => array($this, 'check_admin_permission'),
        ));

        // Trigger manual update (admin only)
        register_rest_route(self::NAMESPACE, '/manual-update', array(
            'methods' => 'POST',
            'callback' => array($this, 'trigger_manual_update'),
            'permission_callback' => array($this, 'check_admin_permission'),
        ));
    }

    /**
     * Get price list endpoint
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object
     */
    public function get_price_list($request) {
        $data = $this->api_client->get_price_list();

        if (is_wp_error($data)) {
            return new WP_Error(
                $data->get_error_code(),
                $data->get_error_message(),
                array('status' => 500)
            );
        }

        return new WP_REST_Response(array(
            'success' => true,
            'data' => $data,
            'cached' => (bool) $this->cache_manager->get_price_list(),
        ), 200);
    }

    /**
     * Get prices by category endpoint
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object
     */
    public function get_prices_by_category($request) {
        $category = strtoupper($request->get_param('category'));
        $date = $request->get_param('date');

        // Map category names
        $category_map = array(
            'GI' => 'GI',
            'GL' => 'GL',
            'PPGI' => 'PPGI',
            'HRC' => 'HRC',
            'CRC HARD' => 'CRC Hard',
            'CRC_HARD' => 'CRC Hard',
            'AL' => 'AL',
        );

        if (!isset($category_map[$category])) {
            return new WP_Error(
                'invalid_category',
                'Invalid category. Valid categories: GI, GL, PPGI, HRC, CRC Hard, AL',
                array('status' => 400)
            );
        }

        $category_name = $category_map[$category];

        // Get full price list (from cache or API, with optional date)
        $data = $this->api_client->get_price_list($date);

        if (is_wp_error($data)) {
            return new WP_Error(
                $data->get_error_code(),
                $data->get_error_message(),
                array('status' => 500)
            );
        }

        // Extract only the requested category
        $category_data = isset($data['categories'][$category_name]) ? $data['categories'][$category_name] : null;

        if (!$category_data) {
            return new WP_Error(
                'category_not_found',
                'Category data not found: ' . $category_name,
                array('status' => 404)
            );
        }

        return new WP_REST_Response(array(
            'success' => true,
            'category' => $category_name,
            'date' => $data['date'] ?? '',
            'fetched_at' => $data['fetched_at'] ?? '',
            'data' => $category_data,
        ), 200);
    }

    /**
     * Get real-time price endpoint
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object
     */
    public function get_realtime_price($request) {
        $product_spec = $request->get_param('productSpec');
        $category_id = $request->get_param('categoryId');
        $date = $request->get_param('date');

        $data = $this->api_client->get_realtime_price($product_spec, $category_id, $date);

        if (is_wp_error($data)) {
            return new WP_Error(
                $data->get_error_code(),
                $data->get_error_message(),
                array('status' => 500)
            );
        }

        return new WP_REST_Response(array(
            'success' => true,
            'data' => $data,
        ), 200);
    }

    /**
     * Get price statistics endpoint (for chart)
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object
     */
    public function get_price_statistics($request) {
        $product_spec = $request->get_param('productSpec');
        $category_id = $request->get_param('categoryId');
        $from = $request->get_param('from');
        $to = $request->get_param('to');

        // Generate cache key from productSpec, from, to
        $cache_key = 'ippgi_stats_' . md5($product_spec . '_' . $from . '_' . $to);

        // Check cache first
        $cached_data = get_transient($cache_key);
        if (false !== $cached_data) {
            return new WP_REST_Response(array(
                'success' => true,
                'data' => $cached_data,
                'cached' => true,
            ), 200);
        }

        // Build external API URL
        $api_url = add_query_arg(array(
            'productSpec' => $product_spec,
            'categoryId' => $category_id,
            'from' => $from,
            'to' => $to,
            'siteId' => '1457210664971423746',
        ), 'https://api.rendui.com/v1/jec/rendui/prices/statistics');

        // Make API request
        $response = wp_remote_get($api_url, array(
            'headers' => array(
                'phone' => '18210056805',
            ),
            'timeout' => 30,
        ));

        // Check for errors
        if (is_wp_error($response)) {
            return new WP_Error(
                'api_error',
                $response->get_error_message(),
                array('status' => 500)
            );
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if (200 !== $status_code) {
            return new WP_Error(
                'api_error',
                sprintf('API returned status code %d', $status_code),
                array('status' => 500)
            );
        }

        // Parse response
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_error', 'Failed to parse JSON response', array('status' => 500));
        }

        // Apply currency conversion to price list
        if (isset($data['result']['list']) && is_array($data['result']['list'])) {
            $exchange_rate = IPPGI_Prices_Currency_Converter::get_exchange_rate();
            foreach ($data['result']['list'] as $index => $item) {
                if (isset($item['price'])) {
                    $data['result']['list'][$index]['price_cny'] = $item['price'];
                    $data['result']['list'][$index]['price_usd'] = IPPGI_Prices_Currency_Converter::cny_to_usd($item['price'], $exchange_rate);
                }
                if (isset($item['priceTax'])) {
                    $data['result']['list'][$index]['priceTax_cny'] = $item['priceTax'];
                    $data['result']['list'][$index]['priceTax_usd'] = IPPGI_Prices_Currency_Converter::cny_to_usd($item['priceTax'], $exchange_rate);
                }
            }
            // Also convert rangeAvgPrice
            if (isset($data['result']['rangeAvgPrice'])) {
                $data['result']['rangeAvgPrice_cny'] = $data['result']['rangeAvgPrice'];
                $data['result']['rangeAvgPrice_usd'] = IPPGI_Prices_Currency_Converter::cny_to_usd($data['result']['rangeAvgPrice'], $exchange_rate);
            }
            $data['result']['exchange_rate'] = $exchange_rate;
        }

        // Cache the converted data for 1 hour (3600 seconds)
        set_transient($cache_key, $data, 3600);

        return new WP_REST_Response(array(
            'success' => true,
            'data' => $data,
            'cached' => false,
        ), 200);
    }

    /**
     * Get historical prices from database endpoint (for chart)
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object
     */
    public function get_historical_prices($request) {
        global $wpdb;

        $product_spec = $request->get_param('productSpec');
        $category = $request->get_param('category');
        $range = strtolower(trim($request->get_param('range')));
        $custom_from = trim($request->get_param('from'));
        $custom_to = trim($request->get_param('to'));

        // Map category to table name
        $table_map = IPPGI_Prices_Database::TABLES;
        if (!isset($table_map[$category])) {
            return new WP_Error(
                'invalid_category',
                'Invalid category: ' . $category,
                array('status' => 400)
            );
        }

        $table_name = $wpdb->prefix . $table_map[$category];

        // Calculate date range
        $today = new DateTime('now', new DateTimeZone('Asia/Shanghai'));

        if (!empty($custom_from) && !empty($custom_to)) {
            // Custom date range mode (from date picker)
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $custom_from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $custom_to)) {
                return new WP_Error(
                    'invalid_date',
                    'Invalid date format. Use YYYY-MM-DD.',
                    array('status' => 400)
                );
            }
            $from_date = new DateTime($custom_from, new DateTimeZone('Asia/Shanghai'));
            $to_date = new DateTime($custom_to, new DateTimeZone('Asia/Shanghai'));
            // Don't allow future end date
            if ($to_date > $today) {
                $to_date = clone $today;
            }
            $range = 'custom';
        } elseif (!empty($range)) {
            // Preset range mode
            $from_date = clone $today;

            switch ($range) {
                case '1m':
                    $from_date->modify('-1 month');
                    break;
                case '6m':
                    $from_date->modify('-6 months');
                    break;
                case '1y':
                    $from_date->modify('-1 year');
                    break;
                case '2y':
                    $from_date->modify('-2 years');
                    break;
                case '3y':
                    $from_date->modify('-3 years');
                    break;
                case '4y':
                    $from_date->modify('-4 years');
                    break;
                default:
                    return new WP_Error(
                        'invalid_range',
                        'Invalid range: ' . $range . '. Valid ranges: 1m, 6m, 1y, 2y, 3y, 4y',
                        array('status' => 400)
                    );
            }
            $to_date = clone $today;
        } else {
            return new WP_Error(
                'missing_params',
                'Either "range" or both "from" and "to" parameters are required.',
                array('status' => 400)
            );
        }

        // Check if from and to are the same day - forward to external statistics API
        if ($from_date->format('Y-m-d') === $to_date->format('Y-m-d')) {
            return $this->forward_to_statistics_api($product_spec, $category, $from_date);
        }

        $from_datetime = $from_date->format('Y-m-d') . ' 00:00:00';
        $to_datetime = $to_date->format('Y-m-d') . ' 00:00:00';

        // Generate cache key (include from/to for custom ranges)
        if ($range === 'custom') {
            $cache_key = 'ippgi_hist_' . md5($product_spec . '_' . $category . '_' . $from_datetime . '_' . $to_datetime);
        } else {
            $cache_key = 'ippgi_hist_' . md5($product_spec . '_' . $category . '_' . $range);
        }

        // Check cache first
        $cached_data = get_transient($cache_key);
        if (false !== $cached_data) {
            return new WP_REST_Response(array(
                'success' => true,
                'data' => $cached_data,
                'cached' => true,
            ), 200);
        }

        // Query historical prices from database
        $query = $wpdb->prepare(
            "SELECT statistics_time, price_usd, price_tax_usd, timestamp
             FROM {$table_name}
             WHERE product_spec = %s
             AND statistics_time >= %s
             AND statistics_time <= %s
             ORDER BY statistics_time ASC",
            $product_spec,
            $from_datetime,
            $to_datetime
        );

        $results = $wpdb->get_results($query, ARRAY_A);

        if ($wpdb->last_error) {
            return new WP_Error(
                'db_error',
                'Database error: ' . $wpdb->last_error,
                array('status' => 500)
            );
        }

        // Format data for chart - fill all dates in range
        // Build a map of date => row for quick lookup
        $date_map = array();
        foreach ($results as $row) {
            $date_key = substr($row['statistics_time'], 0, 10); // YYYY-MM-DD
            $date_map[$date_key] = $row;
        }

        // Fill all dates from from_date to to_date
        // Before first real data: price = 0 (product didn't exist yet)
        // After first real data but missing day (weekends/holidays): carry forward last price
        $list = array();
        $current_date = clone $from_date;
        $last_price_usd = 0.0;
        $last_price_tax_usd = 0.0;
        $has_real_data = false;

        while ($current_date <= $to_date) {
            $date_key = $current_date->format('Y-m-d');
            $datetime = $date_key . ' 00:00:00';

            if (isset($date_map[$date_key])) {
                // Real data exists for this date
                $row = $date_map[$date_key];
                $last_price_usd = (float) $row['price_usd'];
                $last_price_tax_usd = (float) $row['price_tax_usd'];
                $has_real_data = true;
                $list[] = array(
                    'statisticsTime' => $row['statistics_time'],
                    'timestamp' => (int) $row['timestamp'],
                    'price_usd' => $last_price_usd,
                    'priceTax_usd' => $last_price_tax_usd,
                );
            } else {
                // No data for this date
                $fill_price = $has_real_data ? $last_price_usd : 0.0;
                $fill_price_tax = $has_real_data ? $last_price_tax_usd : 0.0;
                $list[] = array(
                    'statisticsTime' => $datetime,
                    'timestamp' => $current_date->getTimestamp(),
                    'price_usd' => $fill_price,
                    'priceTax_usd' => $fill_price_tax,
                );
            }

            $current_date->modify('+1 day');
        }

        $data = array(
            'list' => $list,
            'range' => $range,
            'from' => $from_datetime,
            'to' => $to_datetime,
            'count' => count($list),
        );

        // Cache for 1 hour
        set_transient($cache_key, $data, 3600);

        return new WP_REST_Response(array(
            'success' => true,
            'data' => $data,
            'cached' => false,
        ), 200);
    }

    /**
     * Forward single-day request to external statistics API
     *
     * @param string $product_spec Product specification
     * @param string $category Category name (GI, GL, PPGI, etc.)
     * @param DateTime $date The date to query
     * @return WP_REST_Response|WP_Error Response object
     */
    private function forward_to_statistics_api($product_spec, $category, $date) {
        // Map category name to category ID
        $category_ids = IPPGI_Prices_API_Client::CATEGORY_IDS;
        if (!isset($category_ids[$category])) {
            return new WP_Error(
                'invalid_category',
                'Invalid category for statistics API: ' . $category,
                array('status' => 400)
            );
        }
        $category_id = $category_ids[$category];

        // Build from/to times (09:00 - 17:00)
        $date_str = $date->format('Y-m-d');
        $from = $date_str . ' 09:00:00';
        $to = $date_str . ' 17:00:00';

        // Generate cache key
        $cache_key = 'ippgi_stats_' . md5($product_spec . '_' . $from . '_' . $to);

        // Check cache first
        $cached_data = get_transient($cache_key);
        if (false !== $cached_data) {
            return new WP_REST_Response(array(
                'success' => true,
                'data' => $cached_data,
                'cached' => true,
            ), 200);
        }

        // Build external API URL
        $api_url = add_query_arg(array(
            'productSpec' => $product_spec,
            'categoryId' => $category_id,
            'from' => $from,
            'to' => $to,
            'siteId' => '1457210664971423746',
        ), 'https://api.rendui.com/v1/jec/rendui/prices/statistics');

        // Make API request
        $response = wp_remote_get($api_url, array(
            'headers' => array(
                'phone' => '18210056805',
            ),
            'timeout' => 30,
        ));

        // Check for errors
        if (is_wp_error($response)) {
            return new WP_Error(
                'api_error',
                $response->get_error_message(),
                array('status' => 500)
            );
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if (200 !== $status_code) {
            return new WP_Error(
                'api_error',
                sprintf('API returned status code %d', $status_code),
                array('status' => 500)
            );
        }

        // Parse response
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_error', 'Failed to parse JSON response', array('status' => 500));
        }

        // Apply currency conversion to price list
        if (isset($data['result']['list']) && is_array($data['result']['list'])) {
            $exchange_rate = IPPGI_Prices_Currency_Converter::get_exchange_rate();
            foreach ($data['result']['list'] as $index => $item) {
                if (isset($item['price'])) {
                    $data['result']['list'][$index]['price_cny'] = $item['price'];
                    $data['result']['list'][$index]['price_usd'] = IPPGI_Prices_Currency_Converter::cny_to_usd($item['price'], $exchange_rate);
                }
                if (isset($item['priceTax'])) {
                    $data['result']['list'][$index]['priceTax_cny'] = $item['priceTax'];
                    $data['result']['list'][$index]['priceTax_usd'] = IPPGI_Prices_Currency_Converter::cny_to_usd($item['priceTax'], $exchange_rate);
                }
            }
            // Also convert rangeAvgPrice
            if (isset($data['result']['rangeAvgPrice'])) {
                $data['result']['rangeAvgPrice_cny'] = $data['result']['rangeAvgPrice'];
                $data['result']['rangeAvgPrice_usd'] = IPPGI_Prices_Currency_Converter::cny_to_usd($data['result']['rangeAvgPrice'], $exchange_rate);
            }
            $data['result']['exchange_rate'] = $exchange_rate;
        }

        // Cache for 1 hour
        set_transient($cache_key, $data, 3600);

        return new WP_REST_Response(array(
            'success' => true,
            'data' => $data,
            'cached' => false,
        ), 200);
    }

    /**
     * Get cache statistics endpoint
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function get_cache_stats($request) {
        $stats = $this->cache_manager->get_cache_stats();

        // Get scheduler info
        $scheduler = ippgi_prices()->scheduler;
        $last_run = $scheduler->get_last_run_info();
        $next_runs = $scheduler->get_next_scheduled_times();

        return new WP_REST_Response(array(
            'success' => true,
            'cache' => $stats,
            'scheduler' => array(
                'last_run' => $last_run,
                'next_runs' => $next_runs,
            ),
        ), 200);
    }

    /**
     * Clear cache endpoint
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function clear_cache($request) {
        $results = $this->cache_manager->clear_all_caches();

        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'All caches cleared successfully',
            'results' => $results,
        ), 200);
    }

    /**
     * Trigger manual update endpoint
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function trigger_manual_update($request) {
        $scheduler = ippgi_prices()->scheduler;
        $scheduler->trigger_manual_run();

        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Manual update triggered successfully',
        ), 200);
    }

    /**
     * Check if user has admin permission
     *
     * @return bool True if user is admin
     */
    public function check_admin_permission() {
        return current_user_can('manage_options');
    }
}
