<?php
/**
 * Currency Converter Class
 * Handles CNY to USD conversion using Bank of China exchange rates
 *
 * @package IPPGI_Prices
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class IPPGI_Prices_Currency_Converter {

    /**
     * Bank of China exchange rate URL
     */
    const BOC_URL = 'https://www.boc.cn/sourcedb/whpj/';

    /**
     * Cache key for exchange rate
     */
    const CACHE_KEY = 'ippgi_prices_exchange_rate_cny_usd';

    /**
     * Cache expiration (24 hours)
     */
    const CACHE_EXPIRATION = 86400;

    /**
     * Historical rates table name (without prefix)
     */
    const HISTORICAL_RATES_TABLE = 'prices_exchange_rates';

    /**
     * Get CNY to USD exchange rate for a specific date
     *
     * @param string|null $date Date in YYYY-MM-DD format (null for current rate)
     * @param bool $force_refresh Force refresh from BOC
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
     * @param bool $force_refresh Force refresh from BOC
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

        // Fetch from Bank of China
        $rate = self::fetch_boc_rate();

        if (false === $rate) {
            // Fallback to a default rate if fetch fails
            error_log('IPPGI Prices: Failed to fetch BOC exchange rate, using fallback');
            $rate = 7.2; // Fallback rate
        }

        // Cache the rate
        set_transient(self::CACHE_KEY, $rate, self::CACHE_EXPIRATION);

        return $rate;
    }

    /**
     * Get historical exchange rate for a specific date
     *
     * @param string $date Date in YYYY-MM-DD format
     * @param bool $force_refresh Force refresh from BOC
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

        // Fetch from Bank of China historical data
        $rate = self::fetch_boc_historical_rate($date);

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
     * Fetch exchange rate from Bank of China website
     *
     * @return float|false Exchange rate or false on failure
     */
    private static function fetch_boc_rate() {
        // Note: BOC website may require special handling or API access
        // For now, we'll use a simplified approach

        $response = wp_remote_get(self::BOC_URL, array(
            'timeout' => 15,
            'headers' => array(
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            ),
        ));

        if (is_wp_error($response)) {
            error_log('IPPGI Prices: BOC fetch error - ' . $response->get_error_message());
            return false;
        }

        $body = wp_remote_retrieve_body($response);

        // Parse HTML to extract USD/CNY rate
        // BOC shows rates in format: 100 USD = XXX CNY
        // We need to extract and calculate the rate

        // Look for USD exchange rate pattern
        if (preg_match('/美元.*?<td[^>]*>([\d.]+)<\/td>/s', $body, $matches)) {
            // This is the selling rate for 100 USD in CNY
            $rate_per_100_usd = floatval($matches[1]);
            if ($rate_per_100_usd > 0) {
                $rate = $rate_per_100_usd / 100; // Convert to rate per 1 USD
                error_log(sprintf('IPPGI Prices: Fetched BOC rate: 1 USD = %.4f CNY', $rate));
                return $rate;
            }
        }

        // Alternative: Try to find the rate in JSON data if available
        if (preg_match('/"currency":"美元".*?"sellPrice":"([\d.]+)"/s', $body, $matches)) {
            $rate_per_100_usd = floatval($matches[1]);
            if ($rate_per_100_usd > 0) {
                $rate = $rate_per_100_usd / 100;
                error_log(sprintf('IPPGI Prices: Fetched BOC rate (JSON): 1 USD = %.4f CNY', $rate));
                return $rate;
            }
        }

        return false;
    }

    /**
     * Fetch historical exchange rate from Bank of China
     *
     * @param string $date Date in YYYY-MM-DD format
     * @return float|false Exchange rate or false on failure
     */
    private static function fetch_boc_historical_rate($date) {
        // BOC historical data query
        // The BOC website has a search interface for historical rates
        // URL: https://www.boc.cn/sourcedb/whpj/index.html
        // This requires POST with date parameters

        // Convert date format for BOC query (YYYY-MM-DD to YYYY-MM-DD)
        $query_date = $date;

        // Build query URL with date parameter
        $query_url = 'https://srh.bankofchina.com/search/whpj/search_cn.jsp';

        $response = wp_remote_post($query_url, array(
            'timeout' => 15,
            'headers' => array(
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Content-Type' => 'application/x-www-form-urlencoded',
            ),
            'body' => array(
                'erectDate' => $query_date,
                'nothing' => $query_date,
                'pjname' => '美元', // USD in Chinese
            ),
        ));

        if (is_wp_error($response)) {
            error_log('IPPGI Prices: BOC historical fetch error - ' . $response->get_error_message());
            return false;
        }

        $body = wp_remote_retrieve_body($response);

        // Parse the response to extract USD rate
        // The response contains a table with exchange rates
        // Look for the selling price (卖出价) for USD

        // Pattern to match the selling price in the table
        if (preg_match('/<td[^>]*>美元<\/td>.*?<td[^>]*>([\d.]+)<\/td>/s', $body, $matches)) {
            $rate_per_100_usd = floatval($matches[1]);
            if ($rate_per_100_usd > 0) {
                $rate = $rate_per_100_usd / 100;
                error_log(sprintf('IPPGI Prices: Fetched BOC historical rate for %s: 1 USD = %.4f CNY', $date, $rate));
                return $rate;
            }
        }

        // Alternative pattern for different table structure
        if (preg_match('/美元.*?<td[^>]*>([\d.]+)<\/td>.*?<td[^>]*>([\d.]+)<\/td>/s', $body, $matches)) {
            // Try the second match (selling price is usually the second column)
            $rate_per_100_usd = floatval($matches[2]);
            if ($rate_per_100_usd > 0) {
                $rate = $rate_per_100_usd / 100;
                error_log(sprintf('IPPGI Prices: Fetched BOC historical rate for %s: 1 USD = %.4f CNY', $date, $rate));
                return $rate;
            }
        }

        error_log("IPPGI Prices: Could not parse historical rate for {$date}");
        return false;
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
            $exchange_rate = 7.2;
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
            $exchange_rate = 7.2;
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
            if (isset($price_data[$field]) && $price_data[$field] !== null) {
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
     */
    public static function clear_cache() {
        delete_transient(self::CACHE_KEY);
    }
}
