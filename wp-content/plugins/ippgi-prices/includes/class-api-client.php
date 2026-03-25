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
     * Phone header required by selected Rendui endpoints
     */
    const API_PHONE = '13792171909';

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
     * @param string $date Optional date in YYYY-MM-DD or YYYY-MM-DD HH:MM:SS format
     * @return array|WP_Error Price list data or error
     */
    public function fetch_price_list($force_refresh = false, $date = '') {
        // Check cache first unless force refresh
        if (!$force_refresh) {
            $cached = $this->cache_manager->get_price_list();
            if (false !== $cached) {
                return $cached;
            }
        }

        // Get date parameter (use provided date or default to today)
        $api_date = !empty($date) ? $this->normalize_date($date) : $this->get_api_date();

        // Fetch data for all categories
        $all_data = array();
        $errors = array();

        foreach (self::CATEGORY_IDS as $category_name => $category_id) {
            $category_data = $this->fetch_category_prices($category_id, $category_name, $api_date);

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
            'date' => $api_date,
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
     * Business rule: before 09:00 use yesterday, otherwise use today.
     *
     * @return string Date in format 'YYYY-MM-DD 00:00:00'
     */
    private function get_api_date() {
        return $this->get_business_date()->format('Y-m-d') . ' 00:00:00';
    }

    /**
     * Normalize date string to 'YYYY-MM-DD 00:00:00' format
     *
     * @param string $date Date in YYYY-MM-DD or YYYY-MM-DD HH:MM:SS format
     * @return string Normalized date in 'YYYY-MM-DD 00:00:00' format
     */
    private function normalize_date($date) {
        // Extract YYYY-MM-DD part
        $date_part = substr(trim($date), 0, 10);
        // Validate format
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_part)) {
            return $date_part . ' 00:00:00';
        }
        // Fallback to business date
        return $this->get_api_date();
    }

    /**
     * Fetch real-time price from API
     *
     * @param string $product_spec Full productSpec from client (e.g., "1482328115005964290_1000_0.11_彩涂")
     * @param string $category_id Category ID
     * @param string $date Date in format YYYY-MM-DD (optional, defaults to business date)
     * @param bool   $force_refresh Force refresh even if cached
     * @return array|WP_Error Price data or error
     */
    public function fetch_realtime_price($product_spec, $category_id, $date = '', $force_refresh = false) {
        $use_latest_cache = empty($date);

        // Check cache first unless force refresh
        if (!$force_refresh && $use_latest_cache) {
            $cached = $this->cache_manager->get_realtime_price($product_spec);
            if (false !== $cached) {
                return $cached;
            }
        }

        // Get date (default to today)
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
                'phone' => self::API_PHONE,
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

        // Cache only "latest" requests; custom-date queries should not overwrite latest cache.
        if ($use_latest_cache) {
            $this->cache_manager->set_realtime_price($product_spec, $data);
        }

        return $data;
    }

    /**
     * Get date parameter for API request (simple format YYYY-MM-DD)
     * Business rule: before 09:00 use yesterday, otherwise use today.
     *
     * @return string Date in format 'YYYY-MM-DD'
     */
    private function get_api_date_simple() {
        return $this->get_business_date()->format('Y-m-d');
    }

    /**
     * Get business date in WP timezone.
     * Before 09:00, use previous day to align with data availability.
     *
     * @return DateTimeImmutable
     */
    private function get_business_date() {
        $timezone = wp_timezone();
        $now = new DateTimeImmutable('now', $timezone);

        if ((int) $now->format('H') < 9) {
            return $now->modify('-1 day');
        }

        return $now;
    }

    /**
     * Get price list (from cache or API)
     *
     * @param string $date Optional date in YYYY-MM-DD or YYYY-MM-DD HH:MM:SS format
     * @return array|WP_Error Price list data or error
     */
    public function get_price_list($date = '') {
        return $this->fetch_price_list(false, $date);
    }

    /**
     * Reprice the cached price list with a fresh exchange rate while keeping CNY values unchanged.
     *
     * @param float|null $exchange_rate Exchange rate (CNY per USD).
     * @return array|WP_Error Updated cached payload or error.
     */
    public function reprice_cached_price_list($exchange_rate = null) {
        if (null === $exchange_rate) {
            $exchange_rate = IPPGI_Prices_Currency_Converter::get_exchange_rate();
        }

        $current_cached = $this->cache_manager->get_price_list();
        if (false === $current_cached || empty($current_cached['categories']) || !is_array($current_cached['categories'])) {
            return new WP_Error('cache_miss', 'No cached price list available to reprice');
        }

        $repriced_categories = array();

        foreach ($current_cached['categories'] as $category_name => $category_data) {
            $current_cached['categories'][$category_name] = $this->recalculate_category_prices($category_data, $exchange_rate);
            $repriced_categories[] = $category_name;
        }

        $current_cached['repriced_categories'] = $repriced_categories;
        $current_cached['repriced_at'] = current_time('Y-m-d H:i:s');
        $current_cached['exchange_rate'] = $exchange_rate;

        $this->cache_manager->set_price_list($current_cached);

        return $current_cached;
    }

    /**
     * Refresh price list incrementally (Category by Category)
     * If a category fails to fetch, it keeps the previously cached data for that category
     * BUT recalculates all prices with the latest exchange rate for consistency.
     *
     * @return array|WP_Error The updated price list or error
     */
    public function refresh_price_list_incrementally() {
        // 1. Get current cached price list and latest exchange rate
        $current_cached = $this->cache_manager->get_price_list();
        $api_date = $this->get_api_date();
        $latest_exchange_rate = IPPGI_Prices_Currency_Converter::get_exchange_rate();

        $all_data = array();
        $errors = array();
        $updated_categories = array();
        $recalculated_categories = array();

        // 2. Process each category
        foreach (self::CATEGORY_IDS as $category_name => $category_id) {
            $category_data = $this->fetch_category_prices($category_id, $category_name, $api_date);

            if (!is_wp_error($category_data) && isset($category_data['result']) && !empty($category_data['result'])) {
                // Success: New data fetched and already converted with latest rate inside fetch_category_prices
                $all_data[$category_name] = $category_data;
                $updated_categories[] = $category_name;
            } else {
                // Failure: Try to use old cached data but RECALCULATE with latest exchange rate
                if ($current_cached && isset($current_cached['categories'][$category_name])) {
                    $old_category_data = $current_cached['categories'][$category_name];
                    
                    // Recalculate prices with latest rate to ensure consistency
                    $all_data[$category_name] = $this->recalculate_category_prices($old_category_data, $latest_exchange_rate);
                    $recalculated_categories[] = $category_name;
                    $errors[$category_name] = is_wp_error($category_data) ? $category_data->get_error_message() : 'API returned empty';
                } else {
                    $errors[$category_name] = 'No cached data available and API fetch failed';
                }
            }
        }

        // Prepare combined result
        $result = array(
            'success' => true,
            'date' => $api_date,
            'categories' => $all_data,
            'errors' => $errors,
            'updated_categories' => $updated_categories,
            'recalculated_categories' => $recalculated_categories,
            'fetched_at' => current_time('Y-m-d H:i:s'),
        );

        // 3. Cache the updated data
        $this->cache_manager->set_price_list($result);

        return $result;
    }

    /**
     * Reprice all cached real-time price payloads with the latest exchange rate.
     *
     * @param float|null $exchange_rate Exchange rate (CNY per USD).
     * @return array Summary of repriced entries.
     */
    public function reprice_cached_realtime_prices($exchange_rate = null) {
        if (null === $exchange_rate) {
            $exchange_rate = IPPGI_Prices_Currency_Converter::get_exchange_rate();
        }

        $entries = $this->cache_manager->get_all_realtime_price_entries();
        $results = array(
            'updated' => 0,
            'skipped' => 0,
            'errors' => array(),
            'exchange_rate' => $exchange_rate,
        );

        foreach ($entries as $entry) {
            $payload = $entry['value'];

            if (!is_array($payload) || !isset($payload['result']) || !is_array($payload['result'])) {
                $results['skipped']++;
                continue;
            }

            $payload['result'] = IPPGI_Prices_Currency_Converter::convert_price_data($payload['result'], $exchange_rate);
            $payload['repriced_at'] = current_time('Y-m-d H:i:s');

            if (!$this->cache_manager->update_realtime_price_entry($entry['option_name'], $payload)) {
                $results['errors'][] = sprintf('Failed to update cached realtime payload: %s', $entry['option_name']);
                continue;
            }

            $results['updated']++;
        }

        return $results;
    }

    /**
     * Recalculate category prices with a specific exchange rate
     *
     * @param array $category_data Category data
     * @param float $exchange_rate New exchange rate
     * @return array Updated category data
     */
    private function recalculate_category_prices($category_data, $exchange_rate) {
        if (!isset($category_data['result']) || !is_array($category_data['result'])) {
            return $category_data;
        }

        foreach ($category_data['result'] as $width => $items) {
            if (!is_array($items)) {
                continue;
            }

            foreach ($items as $index => $item) {
                // Use the converter which handles using _cny fields if they exist
                $category_data['result'][$width][$index] = IPPGI_Prices_Currency_Converter::convert_price_data($item, $exchange_rate);
            }
        }

        return $category_data;
    }

    /**
     * Get real-time price (from cache or API)
     *
     * @param string $product_spec Full productSpec
     * @param string $category_id Category ID
     * @param string $date Date (optional)
     * @return array|WP_Error Price data or error
     */
    public function get_realtime_price($product_spec, $category_id, $date = '') {
        return $this->fetch_realtime_price($product_spec, $category_id, $date, false);
    }
}
