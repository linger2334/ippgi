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
     * Shared lower-bound random factor range in basis points (0.1% - 0.5%).
     */
    const PRICE_RANGE_LOWER_BPS_MIN = 10;
    const PRICE_RANGE_LOWER_BPS_MAX = 50;

    /**
     * Shared upper-bound random factor range in basis points (1% - 2%).
     */
    const PRICE_RANGE_UPPER_BPS_MIN = 100;
    const PRICE_RANGE_UPPER_BPS_MAX = 200;

    /**
     * Rendui API base endpoint
     */
    const API_BASE_URL = 'https://www.rendui.com/api/v1/jec/rendui';

    /**
     * Price list API endpoint
     */
    const PRICE_LIST_URL = self::API_BASE_URL . '/prices/daily';

    /**
     * Real-time price API endpoint
     */
    const REALTIME_PRICE_URL = self::API_BASE_URL . '/daily/getByProductSpecAndDate';

    /**
     * Statistics API endpoint
     */
    const STATISTICS_URL = self::API_BASE_URL . '/prices/statistics';

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
        $previous_payload = $this->cache_manager->get_price_list();
        $previous_categories = (is_array($previous_payload) && isset($previous_payload['categories']) && is_array($previous_payload['categories']))
            ? $previous_payload['categories']
            : array();

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

        $range_context = $this->generate_price_range_context();
        $all_data = $this->apply_shared_price_ranges_to_categories($all_data, $range_context, $previous_categories);

        // Prepare combined result
        $result = array(
            'success' => true,
            'date' => $api_date,
            'categories' => $all_data,
            'errors' => $errors,
            'price_range_context' => $range_context,
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
        return new WP_Error(
            'realtime_price_disabled',
            'Realtime single-spec price requests have been disabled because the price-detail API is no longer in use.'
        );
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
     * Get cached price list only.
     *
     * @return array|false Cached price list or false if cache is missing
     */
    public function get_cached_price_list() {
        return $this->cache_manager->get_price_list();
    }

    /**
     * Legacy helper retained for backwards compatibility.
     * Cached price-list repricing is no longer supported because RMB source fields
     * are not retained after conversion.
     *
     * @param float|null $exchange_rate Exchange rate (CNY per USD).
     * @return array|WP_Error Updated cached payload or error.
     */
    public function reprice_cached_price_list($exchange_rate = null) {
        return new WP_Error(
            'unsupported_reprice',
            'Cached price-list repricing is unavailable because RMB source fields are no longer stored.'
        );
    }

    /**
     * Refresh price list incrementally (Category by Category).
     * If a category fails to fetch, keep the previously cached USD data for that category.
     *
     * @return array|WP_Error The updated price list or error
     */
    public function refresh_price_list_incrementally() {
        // 1. Get current cached price list and latest exchange rate
        $current_cached = $this->cache_manager->get_price_list();
        $api_date = $this->get_api_date();
        $previous_categories = ($current_cached && isset($current_cached['categories']) && is_array($current_cached['categories']))
            ? $current_cached['categories']
            : array();

        $range_context = $this->generate_price_range_context();
        $all_data = array();
        $errors = array();
        $updated_categories = array();
        $preserved_categories = array();

        // 2. Process each category
        foreach (self::CATEGORY_IDS as $category_name => $category_id) {
            $category_data = $this->fetch_category_prices($category_id, $category_name, $api_date);

            if (!is_wp_error($category_data) && isset($category_data['result']) && !empty($category_data['result'])) {
                // Success: new data fetched and already converted with the latest rate inside fetch_category_prices.
                $all_data[$category_name] = $category_data;
                $updated_categories[] = $category_name;
            } else {
                // Failure: keep the cached USD payload as-is because RMB source values are not retained anymore.
                if ($current_cached && isset($current_cached['categories'][$category_name])) {
                    $all_data[$category_name] = $current_cached['categories'][$category_name];
                    $preserved_categories[] = $category_name;
                    $errors[$category_name] = is_wp_error($category_data) ? $category_data->get_error_message() : 'API returned empty';
                } else {
                    $errors[$category_name] = 'No cached data available and API fetch failed';
                }
            }
        }

        $all_data = $this->apply_shared_price_ranges_to_categories($all_data, $range_context, $previous_categories);

        // Prepare combined result
        $result = array(
            'success' => true,
            'date' => $api_date,
            'categories' => $all_data,
            'errors' => $errors,
            'updated_categories' => $updated_categories,
            'preserved_categories' => $preserved_categories,
            'price_range_context' => $range_context,
            'fetched_at' => current_time('Y-m-d H:i:s'),
        );

        // 3. Cache the updated data
        $this->cache_manager->set_price_list($result);

        return $result;
    }

    /**
     * Legacy helper retained for backwards compatibility.
     * Cached realtime repricing is no longer supported because RMB source fields
     * are not retained after conversion.
     *
     * @param float|null $exchange_rate Exchange rate (CNY per USD).
     * @return array Summary of repriced entries.
     */
    public function reprice_cached_realtime_prices($exchange_rate = null) {
        $results = array(
            'updated' => 0,
            'skipped' => 0,
            'errors' => array(
                'Cached realtime repricing is unavailable because RMB source fields are no longer stored.',
            ),
            'exchange_rate' => null,
        );

        return $results;
    }

    /**
     * Generate shared lower/upper range factors for one price-list refresh pass.
     *
     * @return array
     */
    private function generate_price_range_context() {
        $lower_bps = random_int(self::PRICE_RANGE_LOWER_BPS_MIN, self::PRICE_RANGE_LOWER_BPS_MAX);
        $upper_bps = random_int(self::PRICE_RANGE_UPPER_BPS_MIN, self::PRICE_RANGE_UPPER_BPS_MAX);

        return array(
            'lower_bps' => $lower_bps,
            'upper_bps' => $upper_bps,
            'lower_percent' => $lower_bps / 100,
            'upper_percent' => $upper_bps / 100,
            'lower_multiplier' => 1 - ($lower_bps / 10000),
            'upper_multiplier' => 1 + ($upper_bps / 10000),
        );
    }

    /**
     * Apply one shared range context to all cached category entries in a refresh batch.
     *
     * @param array $categories Category payloads keyed by category name.
     * @param array $range_context Shared random factors for this refresh.
     * @return array
     */
    private function apply_shared_price_ranges_to_categories($categories, $range_context, $previous_categories = array()) {
        if (!is_array($categories)) {
            return $categories;
        }

        foreach ($categories as $category_name => $category_data) {
            $previous_category_data = isset($previous_categories[$category_name]) && is_array($previous_categories[$category_name])
                ? $previous_categories[$category_name]
                : null;
            $categories[$category_name] = $this->apply_shared_price_ranges_to_category($category_data, $range_context, $previous_category_data);
        }

        return $categories;
    }

    /**
     * Apply one shared range context to every item in a category payload.
     *
     * @param array $category_data Category payload.
     * @param array $range_context Shared random factors for this refresh.
     * @return array
     */
    private function apply_shared_price_ranges_to_category($category_data, $range_context, $previous_category_data = null) {
        if (!isset($category_data['result']) || !is_array($category_data['result'])) {
            return $category_data;
        }

        $previous_items_by_key = $this->index_category_items_by_lookup_key($previous_category_data);

        foreach ($category_data['result'] as $width => $items) {
            if (!is_array($items)) {
                continue;
            }

            foreach ($items as $index => $item) {
                $lookup_key = $this->get_category_item_lookup_key($item, $width);
                $previous_item = ($lookup_key && isset($previous_items_by_key[$lookup_key]) && is_array($previous_items_by_key[$lookup_key]))
                    ? $previous_items_by_key[$lookup_key]
                    : null;
                $category_data['result'][$width][$index] = $this->apply_price_ranges_to_item($item, $range_context, $previous_item);
            }
        }

        $category_data['price_range_context'] = $range_context;

        return $category_data;
    }

    /**
     * Add USD range fields to a single price-list item.
     *
     * @param array $item Converted USD item payload.
     * @param array $range_context Shared random factors for this refresh.
     * @return array
     */
    private function apply_price_ranges_to_item($item, $range_context, $previous_item = null) {
        $range_map = array(
            'lastprice' => array('lastprice_usd', 'lastprice', 'price_usd', 'price'),
            'lastpriceTax' => array('lastpriceTax_usd', 'lastpriceTax', 'priceTax_usd', 'priceTax'),
            'price' => array('price_usd', 'price'),
            'priceTax' => array('priceTax_usd', 'priceTax', 'taxPrice_usd', 'taxPrice'),
        );

        foreach ($range_map as $prefix => $candidates) {
            $amount = $this->find_first_numeric_value($item, $candidates);
            if (null === $amount || $amount <= 0) {
                continue;
            }

            $current_min = round($amount * $range_context['lower_multiplier'], 2);
            $current_max = round($amount * $range_context['upper_multiplier'], 2);
            $current_average = ($current_min + $current_max) / 2;
            $previous_average = $this->get_previous_range_average($previous_item, $prefix, $candidates);

            $item[$prefix . '_range_min_usd'] = $current_min;
            $item[$prefix . '_range_max_usd'] = $current_max;
            if ('lastprice' === $prefix || 'lastpriceTax' === $prefix) {
                $item[$prefix . '_range_direction_usd'] = $this->get_range_direction($current_average, $previous_average);
            }
        }

        return $item;
    }

    /**
     * Index category items by stable lookup key.
     *
     * @param array|null $category_data Category payload.
     * @return array
     */
    private function index_category_items_by_lookup_key($category_data) {
        if (!is_array($category_data) || !isset($category_data['result']) || !is_array($category_data['result'])) {
            return array();
        }

        $indexed = array();

        foreach ($category_data['result'] as $width => $items) {
            if (!is_array($items)) {
                continue;
            }

            foreach ($items as $item) {
                $lookup_key = $this->get_category_item_lookup_key($item, $width);
                if ($lookup_key) {
                    $indexed[$lookup_key] = $item;
                }
            }
        }

        return $indexed;
    }

    /**
     * Build a stable lookup key for category items.
     *
     * @param array      $item  Category item.
     * @param string|int $width Width bucket.
     * @return string
     */
    private function get_category_item_lookup_key($item, $width = '') {
        if (isset($item['productSpec']) && '' !== (string) $item['productSpec']) {
            return 'spec:' . (string) $item['productSpec'];
        }

        $thickness = isset($item['thickness']) ? (string) $item['thickness'] : '';
        $normalized_width = isset($item['width']) ? (string) $item['width'] : (string) $width;

        if ('' === $thickness && '' === $normalized_width) {
            return '';
        }

        return 'dim:' . $normalized_width . '|' . $thickness;
    }

    /**
     * Get previous average price for one prefix.
     *
     * @param array|null $previous_item Previous cached item.
     * @param string     $prefix        Price prefix.
     * @param array      $candidates    Fallback amount candidates.
     * @return float|null
     */
    private function get_previous_range_average($previous_item, $prefix, $candidates) {
        if (!is_array($previous_item)) {
            return null;
        }

        $min_key = $prefix . '_range_min_usd';
        $max_key = $prefix . '_range_max_usd';

        if (
            isset($previous_item[$min_key], $previous_item[$max_key]) &&
            is_numeric($previous_item[$min_key]) &&
            is_numeric($previous_item[$max_key])
        ) {
            $previous_min = (float) $previous_item[$min_key];
            $previous_max = (float) $previous_item[$max_key];

            if ($previous_min > 0 && $previous_max > 0) {
                return ($previous_min + $previous_max) / 2;
            }
        }

        $previous_amount = $this->find_first_numeric_value($previous_item, $candidates);
        if (null === $previous_amount || $previous_amount <= 0) {
            return null;
        }

        return (float) $previous_amount;
    }

    /**
     * Convert average-price comparison into a direction token.
     *
     * @param float      $current_average  Current average.
     * @param float|null $previous_average Previous average.
     * @return string
     */
    private function get_range_direction($current_average, $previous_average) {
        if (null === $previous_average) {
            return 'neutral';
        }

        $epsilon = 0.00001;

        if ($current_average > ($previous_average + $epsilon)) {
            return 'up';
        }

        if ($current_average < ($previous_average - $epsilon)) {
            return 'down';
        }

        return 'neutral';
    }

    /**
     * Find the first numeric value from a list of candidate keys.
     *
     * @param array $item Item payload.
     * @param array $candidates Candidate keys in priority order.
     * @return float|null
     */
    private function find_first_numeric_value($item, $candidates) {
        foreach ($candidates as $candidate) {
            if (isset($item[$candidate]) && is_numeric($item[$candidate])) {
                return (float) $item[$candidate];
            }
        }

        return null;
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
