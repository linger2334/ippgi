<?php
/**
 * API Client Class
 * Handles API requests to external price data service
 *
 * @package IPPGI_Prices
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class IPPGI_Prices_API_Client {

    /**
     * Price list API endpoint
     */
    const PRICE_LIST_URL = 'https://api.rendui.com/v1/jec/rendui/prices/daily';

    /**
     * Real-time price API endpoint
     */
    const REALTIME_PRICE_URL = 'https://api.rendui.com/v1/jec/rendui/daily/getByProductSpecAndDate';

    /**
     * Site ID (Location: 博兴 Boxing)
     */
    const SITE_ID = '1457210664971423746';

    /**
     * Category ID mapping
     */
    const CATEGORY_IDS = array(
        'GI'       => '1457211766760558593',  // 民用镀锌
        'GL'       => '1683315093109178369',  // 镀铝锌
        'PPGI'     => '1482328115005964290',  // 彩涂
        'HRC'      => '1457211813719986177',  // 热卷
        'CRC Hard' => '1457211766760558594',  // 轧硬
        'AL'       => '1457211893311098881',  // 光铝
    );

    /**
     * Category Chinese name mapping
     */
    const CATEGORY_NAMES_CN = array(
        'GI'       => '民用镀锌',
        'GL'       => '镀铝锌',
        'PPGI'     => '彩涂',
        'HRC'      => '热卷',
        'CRC Hard' => '轧硬',
        'AL'       => '光铝',
    );

    /**
     * Cache manager instance
     */
    private $cache_manager;

    /**
     * Constructor
     *
     * @param IPPGI_Prices_Cache_Manager $cache_manager Cache manager instance
     */
    public function __construct($cache_manager) {
        $this->cache_manager = $cache_manager;
    }

    /**
     * Fetch price list from API
     *
     * @param bool $force_refresh Force refresh even if cached
     * @return array|WP_Error Price list data or error
     */
    public function fetch_price_list($force_refresh = false) {
        // Check cache first unless force refresh
        if (!$force_refresh) {
            $cached = $this->cache_manager->get_price_list();
            if (false !== $cached) {
                return $cached;
            }
        }

        // Get date parameter
        $date = $this->get_api_date();

        // Fetch data for all categories
        $all_data = array();
        $errors = array();

        foreach (self::CATEGORY_IDS as $category_name => $category_id) {
            $category_data = $this->fetch_category_prices($category_id, $category_name, $date);

            if (is_wp_error($category_data)) {
                $errors[$category_name] = $category_data->get_error_message();
            } else {
                $all_data[$category_name] = $category_data;
            }
        }

        // If all requests failed, return error
        if (empty($all_data) && !empty($errors)) {
            return new WP_Error('api_error', 'Failed to fetch any category data: ' . implode(', ', $errors));
        }

        // Prepare combined result
        $result = array(
            'success' => true,
            'date' => $date,
            'categories' => $all_data,
            'errors' => $errors,
            'fetched_at' => current_time('Y-m-d H:i:s'),
        );

        // Cache the data
        $this->cache_manager->set_price_list($result);

        return $result;
    }

    /**
     * Fetch prices for a single category
     *
     * @param string $category_id Category ID
     * @param string $category_name Category name
     * @param string $date Date parameter
     * @return array|WP_Error Category data or error
     */
    private function fetch_category_prices($category_id, $category_name, $date) {
        // Build URL with query parameters
        $url = add_query_arg(array(
            'siteId' => self::SITE_ID,
            'categoryId' => $category_id,
            'date' => $date,
        ), self::PRICE_LIST_URL);

        // Make API request
        $response = wp_remote_get($url, array(
            'headers' => array(
                'userid' => '33249',
                'referer' => 'https://servicewechat.com/wxa11729a79b0e847e/623/page-frame.html',
            ),
            'timeout' => 30,
        ));

        // Check for errors
        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if (200 !== $status_code) {
            return new WP_Error(
                'api_error',
                sprintf('API returned status code %d for category %s', $status_code, $category_name)
            );
        }

        // Parse response
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_error', 'Failed to parse JSON response for category ' . $category_name);
        }

        // Check if API returned success
        if (isset($data['success']) && false === $data['success']) {
            return new WP_Error(
                'api_error',
                sprintf('API error for category %s: %s', $category_name, $data['message'] ?? 'Unknown error')
            );
        }

        // Apply currency conversion to all price records
        $data = $this->apply_currency_conversion_to_category($data);

        return $data;
    }

    /**
     * Apply currency conversion to category price data
     *
     * @param array $data Category data
     * @return array Data with USD prices
     */
    private function apply_currency_conversion_to_category($data) {
        if (!isset($data['result']) || !is_array($data['result'])) {
            return $data;
        }

        // Get exchange rate once
        $exchange_rate = IPPGI_Prices_Currency_Converter::get_exchange_rate();

        // Convert prices for each width group
        foreach ($data['result'] as $width => $items) {
            if (!is_array($items)) {
                continue;
            }

            foreach ($items as $index => $item) {
                $data['result'][$width][$index] = IPPGI_Prices_Currency_Converter::convert_price_data($item, $exchange_rate);
            }
        }

        return $data;
    }

    /**
     * Get date parameter for API request
     * Always use today's 00:00:00
     *
     * @return string Date in format 'YYYY-MM-DD 00:00:00'
     */
    private function get_api_date() {
        return current_time('Y-m-d') . ' 00:00:00';
    }

    /**
     * Fetch real-time price from API
     *
     * @param string $product_type Product type (e.g., 'PPGI', 'GI')
     * @param int    $width Width in mm
     * @param float  $thickness Thickness
     * @param string $date Date in format (optional, defaults to today)
     * @param bool   $force_refresh Force refresh even if cached
     * @return array|WP_Error Price data or error
     */
    public function fetch_realtime_price($product_type, $width, $thickness, $date = '', $force_refresh = false) {
        // Check cache first unless force refresh
        if (!$force_refresh) {
            $cached = $this->cache_manager->get_realtime_price($product_type, $width, $thickness);
            if (false !== $cached) {
                return $cached;
            }
        }

        // Validate product type
        if (!isset(self::CATEGORY_IDS[$product_type])) {
            return new WP_Error('invalid_product_type', 'Invalid product type: ' . $product_type);
        }

        // Get category ID and Chinese name
        $category_id = self::CATEGORY_IDS[$product_type];
        $category_name_cn = self::CATEGORY_NAMES_CN[$product_type];

        // Build productSpec: ${categoryId}_${width}_${thickness}_${中文名}
        $product_spec = sprintf(
            '%s_%d_%s_%s',
            $category_id,
            $width,
            $thickness,
            $category_name_cn
        );

        // Get date (yesterday if before 9am)
        if (empty($date)) {
            $date = $this->get_api_date_simple();
        }

        // Prepare request body
        $body = array(
            'productSpec' => $product_spec,
            'date' => $date,
            'siteId' => self::SITE_ID,
            'categoryId' => $category_id,
        );

        // Make API request
        $response = wp_remote_post(self::REALTIME_PRICE_URL, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'phone' => '18210056805',
            ),
            'body' => wp_json_encode($body),
            'timeout' => 30,
        ));

        // Check for errors
        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if (200 !== $status_code) {
            return new WP_Error(
                'api_error',
                sprintf('API returned status code %d', $status_code)
            );
        }

        // Parse response
        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_error', 'Failed to parse JSON response');
        }

        // Check if API returned success
        if (isset($data['success']) && false === $data['success']) {
            return new WP_Error(
                'api_error',
                sprintf('API error: %s', $data['message'] ?? 'Unknown error')
            );
        }

        // Apply currency conversion
        if (isset($data['result']) && is_array($data['result'])) {
            $exchange_rate = IPPGI_Prices_Currency_Converter::get_exchange_rate();
            $data['result'] = IPPGI_Prices_Currency_Converter::convert_price_data($data['result'], $exchange_rate);
        }

        // Cache the data
        $this->cache_manager->set_realtime_price($product_type, $width, $thickness, $data);

        return $data;
    }

    /**
     * Get date parameter for API request (simple format YYYY-MM-DD)
     * Always use today's date
     *
     * @return string Date in format 'YYYY-MM-DD'
     */
    private function get_api_date_simple() {
        return current_time('Y-m-d');
    }

    /**
     * Get price list (from cache or API)
     *
     * @return array|WP_Error Price list data or error
     */
    public function get_price_list() {
        return $this->fetch_price_list(false);
    }

    /**
     * Get real-time price (from cache or API)
     *
     * @param string $product_type Product type
     * @param int    $width Width in mm
     * @param float  $thickness Thickness
     * @param string $date Date (optional)
     * @return array|WP_Error Price data or error
     */
    public function get_realtime_price($product_type, $width, $thickness, $date = '') {
        return $this->fetch_realtime_price($product_type, $width, $thickness, $date, false);
    }
}
