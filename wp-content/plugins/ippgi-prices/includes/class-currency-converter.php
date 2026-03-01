<?php
/**
 * Currency Converter Class
 * Handles CNY to USD conversion using Aliyun exchange rate API
 *
 * @package IPPGI_Prices
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class IPPGI_Prices_Currency_Converter {

    /**
     * Aliyun exchange API base URL
     */
    const ALIYUN_API_BASE_URL = 'https://tysjhlcx.market.alicloudapi.com';

    /**
     * Aliyun convert API path
     */
    const ALIYUN_CONVERT_PATH = '/exchange_rate/convert';

    /**
     * Aliyun historical API path
     */
    const ALIYUN_HISTORY_PATH = '/exchange_rate/history';

    /**
     * Cache key for exchange rate
     */
    const CACHE_KEY = 'ippgi_prices_exchange_rate_cny_usd';

    /**
     * Cache expiration (0 = never expires)
     * Exchange rate is refreshed by the scheduled price refresh task.
     */
    const CACHE_EXPIRATION = 0;

    /**
     * Fallback exchange rate (CNY per 1 USD)
     */
    const FALLBACK_RATE = 7.2;

    /**
     * Historical rates table name (without prefix)
     */
    const HISTORICAL_RATES_TABLE = 'prices_exchange_rates';

    /**
     * Get CNY to USD exchange rate for a specific date
     *
     * @param string|null $date Date in YYYY-MM-DD format (null for current rate)
     * @param bool $force_refresh Force refresh from Aliyun
     * @return float Exchange rate
     */
    public static function get_exchange_rate($date = null, $force_refresh = false) {
        // If no date specified, get current rate
        if (null === $date) {
            return self::get_current_rate($force_refresh);
        }

        // Get historical rate for specific date
        return self::get_historical_rate($date, $force_refresh);
    }

    /**
     * Get current CNY to USD exchange rate
     *
     * @param bool $force_refresh Force refresh from Aliyun
     * @return float Exchange rate
     */
    private static function get_current_rate($force_refresh = false) {
        // Check cache first
        if (!$force_refresh) {
            $cached = get_transient(self::CACHE_KEY);
            if (false !== $cached) {
                return (float) $cached;
            }
        }

        // Fetch from Aliyun API
        $rate = self::fetch_aliyun_rate();

        if (false === $rate) {
            // Fallback to a default rate if fetch fails
            error_log('IPPGI Prices: Failed to fetch Aliyun exchange rate, using fallback');
            $rate = self::FALLBACK_RATE;
        }

        // Cache the rate
        set_transient(self::CACHE_KEY, $rate, self::CACHE_EXPIRATION);

        return $rate;
    }

    /**
     * Get historical exchange rate for a specific date
     *
     * @param string $date Date in YYYY-MM-DD format
     * @param bool $force_refresh Force refresh from Aliyun
     * @return float Exchange rate
     */
    private static function get_historical_rate($date, $force_refresh = false) {
        global $wpdb;
        $table_name = $wpdb->prefix . self::HISTORICAL_RATES_TABLE;

        // Check database first
        if (!$force_refresh) {
            $cached_rate = $wpdb->get_var($wpdb->prepare(
                "SELECT rate FROM {$table_name} WHERE rate_date = %s",
                $date
            ));

            if (null !== $cached_rate) {
                return (float) $cached_rate;
            }
        }

        // Fetch from Aliyun historical data
        $rate = self::fetch_aliyun_historical_rate($date);

        if (false === $rate) {
            // If historical rate not available, use current rate as fallback
            error_log("IPPGI Prices: Failed to fetch historical rate for {$date}, using current rate");
            $rate = self::get_current_rate();
        }

        // Store in database
        $wpdb->replace(
            $table_name,
            array(
                'rate_date' => $date,
                'rate' => $rate,
                'created_at' => current_time('mysql'),
            ),
            array('%s', '%f', '%s')
        );

        return $rate;
    }

    /**
     * Get Aliyun APP Key from constant/option/env
     *
     * @return string APP Key or empty string
     */
    private static function get_aliyun_app_key() {
        if (defined('IPPGI_ALIYUN_APP_KEY') && !empty(IPPGI_ALIYUN_APP_KEY)) {
            return trim((string) IPPGI_ALIYUN_APP_KEY);
        }

        $option_value = get_option('ippgi_prices_aliyun_app_key', '');
        if (!empty($option_value)) {
            return trim((string) $option_value);
        }

        $env_value = getenv('IPPGI_ALIYUN_APP_KEY');
        if (!empty($env_value)) {
            return trim((string) $env_value);
        }

        return '';
    }

    /**
     * Get Aliyun APP Secret from constant/option/env
     *
     * @return string APP Secret or empty string
     */
    private static function get_aliyun_app_secret() {
        if (defined('IPPGI_ALIYUN_APP_SECRET') && !empty(IPPGI_ALIYUN_APP_SECRET)) {
            return trim((string) IPPGI_ALIYUN_APP_SECRET);
        }

        $option_value = get_option('ippgi_prices_aliyun_app_secret', '');
        if (!empty($option_value)) {
            return trim((string) $option_value);
        }

        $env_value = getenv('IPPGI_ALIYUN_APP_SECRET');
        if (!empty($env_value)) {
            return trim((string) $env_value);
        }

        return '';
    }

    /**
     * Build Aliyun canonical resource: path + sorted query string.
     *
     * @param string $path  API path
     * @param array  $query Query parameters
     * @return string
     */
    private static function build_aliyun_canonical_resource($path, $query) {
        if (empty($query) || !is_array($query)) {
            return $path;
        }

        ksort($query);

        $pairs = array();
        foreach ($query as $key => $value) {
            $key = (string) $key;
            if ('' === $key) {
                continue;
            }

            if (is_array($value)) {
                $value = reset($value);
            }

            if (null === $value || '' === $value) {
                $pairs[] = $key;
                continue;
            }

            $pairs[] = $key . '=' . (string) $value;
        }

        if (empty($pairs)) {
            return $path;
        }

        return $path . '?' . implode('&', $pairs);
    }

    /**
     * Build headers string for Aliyun signature.
     *
     * @param array $headers     Request headers
     * @param array $signed_keys Header names included in signature
     * @return string
     */
    private static function build_aliyun_signed_headers_string($headers, $signed_keys) {
        sort($signed_keys, SORT_STRING);

        $lines = array();
        foreach ($signed_keys as $header_name) {
            if (!isset($headers[$header_name])) {
                continue;
            }
            $lines[] = $header_name . ':' . (string) $headers[$header_name];
        }

        if (empty($lines)) {
            return '';
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * Build Aliyun signature string (StringToSign).
     *
     * @param string $method      HTTP method
     * @param string $path        API path
     * @param array  $headers     Request headers
     * @param array  $query       Query parameters
     * @param array  $signed_keys Header names included in signature
     * @return string
     */
    private static function build_aliyun_string_to_sign($method, $path, $headers, $query, $signed_keys) {
        $accept = isset($headers['Accept']) ? (string) $headers['Accept'] : '';
        $content_md5 = isset($headers['Content-MD5']) ? (string) $headers['Content-MD5'] : '';
        $content_type = isset($headers['Content-Type']) ? (string) $headers['Content-Type'] : '';
        $date = isset($headers['Date']) ? (string) $headers['Date'] : '';

        return strtoupper($method) . "\n"
            . $accept . "\n"
            . $content_md5 . "\n"
            . $content_type . "\n"
            . $date . "\n"
            . self::build_aliyun_signed_headers_string($headers, $signed_keys)
            . self::build_aliyun_canonical_resource($path, $query);
    }

    /**
     * Send Aliyun signed request using APP Key + APP Secret.
     *
     * @param string $path       API path
     * @param array  $query      Query parameters
     * @param string $app_key    APP Key
     * @param string $app_secret APP Secret
     * @return array|false
     */
    private static function request_aliyun_api_signed($path, $query, $app_key, $app_secret) {
        $method = 'GET';
        $headers = array(
            'Accept' => 'application/json',
            'X-Ca-Key' => $app_key,
            'X-Ca-Nonce' => wp_generate_uuid4(),
            'X-Ca-Timestamp' => (string) round(microtime(true) * 1000),
            'X-Ca-Signature-Method' => 'HmacSHA256',
        );

        $signed_headers = array(
            'X-Ca-Key',
            'X-Ca-Nonce',
            'X-Ca-Signature-Method',
            'X-Ca-Timestamp',
        );

        sort($signed_headers, SORT_STRING);
        $headers['X-Ca-Signature-Headers'] = implode(',', $signed_headers);

        $string_to_sign = self::build_aliyun_string_to_sign($method, $path, $headers, $query, $signed_headers);
        $headers['X-Ca-Signature'] = base64_encode(hash_hmac('sha256', $string_to_sign, $app_secret, true));

        $url = add_query_arg($query, self::ALIYUN_API_BASE_URL . $path);
        $response = wp_remote_get($url, array(
            'timeout' => 15,
            'headers' => $headers,
        ));

        return self::handle_aliyun_response($response);
    }

    /**
     * Parse and validate Aliyun HTTP response.
     *
     * @param array|WP_Error $response HTTP response
     * @return array|false Data field or false on failure
     */
    private static function handle_aliyun_response($response) {
        if (is_wp_error($response)) {
            error_log('IPPGI Prices: Aliyun API request error - ' . $response->get_error_message());
            return false;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if (200 !== $status_code) {
            $headers = wp_remote_retrieve_headers($response);
            $error_message = '';
            if (isset($headers['x-ca-error-message'])) {
                $error_message = (string) $headers['x-ca-error-message'];
            }
            error_log(sprintf('IPPGI Prices: Aliyun API returned status %d. %s', $status_code, $error_message));
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            error_log('IPPGI Prices: Failed to parse Aliyun API JSON response');
            return false;
        }

        if (isset($data['success']) && false === $data['success']) {
            error_log('IPPGI Prices: Aliyun API response indicates failure');
            return false;
        }

        if (isset($data['code']) && 200 !== (int) $data['code']) {
            $message = isset($data['msg']) ? (string) $data['msg'] : 'Unknown error';
            error_log(sprintf('IPPGI Prices: Aliyun API business error %s - %s', $data['code'], $message));
            return false;
        }

        if (!isset($data['data']) || !is_array($data['data'])) {
            error_log('IPPGI Prices: Aliyun API missing data field');
            return false;
        }

        if (isset($data['data']['ret_code']) && '0' !== (string) $data['data']['ret_code']) {
            $message = isset($data['data']['remark']) ? (string) $data['data']['remark'] : 'Aliyun ret_code not 0';
            error_log('IPPGI Prices: Aliyun API ret_code failure - ' . $message);
            return false;
        }

        return $data['data'];
    }

    /**
     * Request Aliyun exchange rate API
     *
     * @param string $path API path
     * @param array  $query Query parameters
     * @return array|false Response data (data field) or false on failure
     */
    private static function request_aliyun_api($path, $query = array()) {
        $filtered_query = array();
        foreach ($query as $key => $value) {
            if (null === $value || '' === $value) {
                continue;
            }
            $filtered_query[$key] = $value;
        }

        $app_key = self::get_aliyun_app_key();
        $app_secret = self::get_aliyun_app_secret();
        if ('' === $app_key || '' === $app_secret) {
            error_log('IPPGI Prices: Aliyun APP Key/Secret are not configured');
            return false;
        }

        return self::request_aliyun_api_signed($path, $filtered_query, $app_key, $app_secret);
    }

    /**
     * Convert numeric strings to float safely
     *
     * @param mixed $value Raw numeric value
     * @return float|false
     */
    private static function to_float($value) {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (!is_string($value)) {
            return false;
        }

        $normalized = str_replace(',', '', trim($value));
        if ('' === $normalized || !is_numeric($normalized)) {
            return false;
        }

        return (float) $normalized;
    }

    /**
     * Fetch current exchange rate from Aliyun convert API
     *
     * @return float|false Exchange rate (CNY per 1 USD) or false on failure
     */
    private static function fetch_aliyun_rate() {
        $data = self::request_aliyun_api(self::ALIYUN_CONVERT_PATH, array(
            'fromCode' => 'USD',
            'toCode' => 'CNY',
            'money' => 1,
        ));

        if (false === $data || !isset($data['money'])) {
            error_log('IPPGI Prices: Aliyun convert API missing money field');
            return false;
        }

        $rate = self::to_float($data['money']);
        if (false === $rate || $rate <= 0) {
            error_log('IPPGI Prices: Aliyun convert API returned invalid rate');
            return false;
        }

        error_log(sprintf('IPPGI Prices: Fetched Aliyun rate: 1 USD = %.4f CNY', $rate));
        return $rate;
    }

    /**
     * Extract historical rate from Aliyun history API result list
     *
     * @param array  $list History list
     * @param string $date Target date (YYYY-MM-DD)
     * @return float|false
     */
    private static function extract_historical_rate_from_list($list, $date) {
        if (!is_array($list) || empty($list)) {
            return false;
        }

        $target = str_replace('-', '', $date);
        $best_item = null;

        foreach ($list as $item) {
            if (!is_array($item) || empty($item['publish_time'])) {
                continue;
            }

            $publish = preg_replace('/[^0-9]/', '', (string) $item['publish_time']);
            if (strlen($publish) < 8) {
                continue;
            }
            $publish = substr($publish, 0, 8);

            if ($publish <= $target) {
                if (null === $best_item || $publish > preg_replace('/[^0-9]/', '', (string) $best_item['publish_time'])) {
                    $best_item = $item;
                }
            }
        }

        if (null === $best_item) {
            // If target date has no earlier row (e.g. month boundary), use first row as last resort.
            $best_item = reset($list);
            if (false === $best_item || !is_array($best_item)) {
                return false;
            }
        }

        if (!isset($best_item['middle_rate'])) {
            return false;
        }

        $rate_per_100_usd = self::to_float($best_item['middle_rate']);
        if (false === $rate_per_100_usd || $rate_per_100_usd <= 0) {
            return false;
        }

        return $rate_per_100_usd / 100;
    }

    /**
     * Fetch historical exchange rate from Aliyun
     *
     * @param string $date Date in YYYY-MM-DD format
     * @return float|false Exchange rate or false on failure
     */
    private static function fetch_aliyun_historical_rate($date) {
        $date_key = str_replace('-', '', $date);

        // First try exact day range query.
        $data = self::request_aliyun_api(self::ALIYUN_HISTORY_PATH, array(
            'code' => 'USD',
            'startDate' => $date_key,
            'endDate' => $date_key,
        ));

        if (false !== $data && !empty($data['list'])) {
            $rate = self::extract_historical_rate_from_list($data['list'], $date);
            if (false !== $rate) {
                error_log(sprintf('IPPGI Prices: Fetched Aliyun historical rate for %s: 1 USD = %.4f CNY', $date, $rate));
                return $rate;
            }
        }

        // Fallback to month query to cover holidays/weekends.
        $month = substr($date_key, 0, 6);
        $month_data = self::request_aliyun_api(self::ALIYUN_HISTORY_PATH, array(
            'code' => 'USD',
            'month' => $month,
        ));

        if (false === $month_data || empty($month_data['list'])) {
            error_log("IPPGI Prices: Aliyun historical API returned empty list for {$date}");
            return false;
        }

        $rate = self::extract_historical_rate_from_list($month_data['list'], $date);
        if (false === $rate) {
            error_log("IPPGI Prices: Could not parse Aliyun historical rate for {$date}");
            return false;
        }

        error_log(sprintf('IPPGI Prices: Fetched Aliyun historical rate for %s: 1 USD = %.4f CNY', $date, $rate));
        return $rate;
    }

    /**
     * Convert CNY to USD
     *
     * @param float $cny_amount Amount in CNY
     * @param float|null $exchange_rate Exchange rate (if null, will fetch current rate)
     * @return float Amount in USD
     */
    public static function cny_to_usd($cny_amount, $exchange_rate = null) {
        if (null === $exchange_rate) {
            $exchange_rate = self::get_exchange_rate();
        }

        if (false === $exchange_rate || $exchange_rate <= 0) {
            error_log('IPPGI Prices: Invalid exchange rate, using fallback');
            $exchange_rate = self::FALLBACK_RATE;
        }

        return round($cny_amount / $exchange_rate, 2);
    }

    /**
     * Convert USD to CNY
     *
     * @param float $usd_amount Amount in USD
     * @param float|null $exchange_rate Exchange rate (if null, will fetch current rate)
     * @return float Amount in CNY
     */
    public static function usd_to_cny($usd_amount, $exchange_rate = null) {
        if (null === $exchange_rate) {
            $exchange_rate = self::get_exchange_rate();
        }

        if (false === $exchange_rate || $exchange_rate <= 0) {
            error_log('IPPGI Prices: Invalid exchange rate, using fallback');
            $exchange_rate = self::FALLBACK_RATE;
        }

        return round($usd_amount * $exchange_rate, 2);
    }

    /**
     * Convert price data from CNY to USD
     *
     * @param array $price_data Price data with CNY prices
     * @param float|null $exchange_rate Exchange rate (if null, will fetch current rate)
     * @return array Price data with both CNY and USD prices
     */
    public static function convert_price_data($price_data, $exchange_rate = null) {
        if (null === $exchange_rate) {
            $exchange_rate = self::get_exchange_rate();
        }

        // Convert price field
        if (isset($price_data['price'])) {
            $price_data['price_cny'] = $price_data['price'];
            $price_data['price_usd'] = self::cny_to_usd($price_data['price'], $exchange_rate);
            $price_data['price'] = $price_data['price_usd']; // Default to USD
        }

        // Convert taxPrice field
        if (isset($price_data['taxPrice'])) {
            $price_data['taxPrice_cny'] = $price_data['taxPrice'];
            $price_data['taxPrice_usd'] = self::cny_to_usd($price_data['taxPrice'], $exchange_rate);
            $price_data['taxPrice'] = $price_data['taxPrice_usd']; // Default to USD
        }

        // Convert priceTax field (alternative name)
        if (isset($price_data['priceTax'])) {
            $price_data['priceTax_cny'] = $price_data['priceTax'];
            $price_data['priceTax_usd'] = self::cny_to_usd($price_data['priceTax'], $exchange_rate);
            $price_data['priceTax'] = $price_data['priceTax_usd']; // Default to USD
        }

        // Convert other price fields
        $price_fields = array(
            'lastprice', 'lastpriceTax', 'openingPrice', 'openingPriceTax',
            'closePrice', 'closePriceTax', 'priceMin', 'priceMax',
            'priceMinTax', 'priceMaxTax', 'riseAndFall', 'riseAndFallTax',
            'lastWeekDiff', 'lastWeekDiffTax', 'lastMonthDiff', 'lastMonthDiffTax',
            'lastYearsDiff', 'lastYearsDiffTax'
        );

        foreach ($price_fields as $field) {
            if (isset($price_data[$field]) && $price_data[$field] !== null && is_numeric($price_data[$field])) {
                $price_data[$field . '_cny'] = $price_data[$field];
                $price_data[$field . '_usd'] = self::cny_to_usd($price_data[$field], $exchange_rate);
                $price_data[$field] = $price_data[$field . '_usd']; // Default to USD
            }
        }

        // Add exchange rate info
        $price_data['exchange_rate'] = $exchange_rate;
        $price_data['currency'] = 'USD';

        return $price_data;
    }

    /**
     * Clear exchange rate cache
     *
     * @return bool True on success, false on failure
     */
    public static function clear_cache() {
        return delete_transient(self::CACHE_KEY);
    }
}
