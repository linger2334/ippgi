<?php
/**
 * Cache Manager Class
 * Manages transient cache for price data
 *
 * @package IPPGI_Prices
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class IPPGI_Prices_Cache_Manager {

    /**
     * Cache key prefix
     */
    const CACHE_PREFIX = 'ippgi_prices_';

    /**
     * Cache expiration time (0 = never expires)
     * Cache is cleared by scheduled tasks at 00:00 and 09:00-17:00
     */
    const CACHE_EXPIRATION = 0;

    /**
     * Price list cache key
     */
    const PRICE_LIST_KEY = 'price_list';

    /**
     * Real-time price cache key prefix
     */
    const REALTIME_PRICE_PREFIX = 'realtime_';

    /**
     * Get price list from cache
     *
     * @return array|false Price list data or false if not cached
     */
    public function get_price_list() {
        return get_transient(self::CACHE_PREFIX . self::PRICE_LIST_KEY);
    }

    /**
     * Set price list cache
     *
     * @param array $data Price list data
     * @return bool True on success, false on failure
     */
    public function set_price_list($data) {
        return set_transient(
            self::CACHE_PREFIX . self::PRICE_LIST_KEY,
            $data,
            self::CACHE_EXPIRATION
        );
    }

    /**
     * Clear price list cache
     *
     * @return bool True on success, false on failure
     */
    public function clear_price_list() {
        return delete_transient(self::CACHE_PREFIX . self::PRICE_LIST_KEY);
    }

    /**
     * Get real-time price from cache
     *
     * @param string $product_spec Full productSpec (e.g., "1482328115005964290_1000_0.11_彩涂")
     * @param string $date Date in YYYY-MM-DD format
     * @return array|false Price data or false if not cached
     */
    public function get_realtime_price($product_spec, $date) {
        $cache_key = $this->get_realtime_cache_key($product_spec, $date);
        return get_transient($cache_key);
    }

    /**
     * Set real-time price cache
     *
     * @param string $product_spec Full productSpec
     * @param string $date Date in YYYY-MM-DD format
     * @param array  $data Price data
     * @return bool True on success, false on failure
     */
    public function set_realtime_price($product_spec, $date, $data) {
        $cache_key = $this->get_realtime_cache_key($product_spec, $date);
        return set_transient($cache_key, $data, self::CACHE_EXPIRATION);
    }

    /**
     * Clear real-time price cache
     *
     * @param string $product_spec Full productSpec
     * @param string $date Date in YYYY-MM-DD format
     * @return bool True on success, false on failure
     */
    public function clear_realtime_price($product_spec, $date) {
        $cache_key = $this->get_realtime_cache_key($product_spec, $date);
        return delete_transient($cache_key);
    }

    /**
     * Clear all real-time price caches
     *
     * @return int Number of caches cleared
     */
    public function clear_all_realtime_prices() {
        global $wpdb;

        // Delete all transients with realtime price prefix
        $pattern = '_transient_' . self::CACHE_PREFIX . self::REALTIME_PRICE_PREFIX . '%';
        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                $pattern,
                '_transient_timeout_' . self::CACHE_PREFIX . self::REALTIME_PRICE_PREFIX . '%'
            )
        );

        return $deleted;
    }

    /**
     * Clear all caches (price list + all real-time prices)
     *
     * @return array Results of clearing operations
     */
    public function clear_all_caches() {
        $results = array(
            'price_list' => $this->clear_price_list(),
            'realtime_prices_count' => $this->clear_all_realtime_prices(),
        );

        return $results;
    }

    /**
     * Generate cache key for real-time price
     *
     * @param string $product_spec Full productSpec
     * @param string $date Date in YYYY-MM-DD format
     * @return string Cache key
     */
    private function get_realtime_cache_key($product_spec, $date) {
        return self::CACHE_PREFIX . self::REALTIME_PRICE_PREFIX . md5($product_spec . '_' . $date);
    }

    /**
     * Get cache statistics
     *
     * @return array Cache statistics
     */
    public function get_cache_stats() {
        global $wpdb;

        $price_list_cached = (bool) $this->get_price_list();

        // Count real-time price caches
        $pattern = '_transient_' . self::CACHE_PREFIX . self::REALTIME_PRICE_PREFIX . '%';
        $realtime_count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
                $pattern
            )
        );

        return array(
            'price_list_cached' => $price_list_cached,
            'realtime_prices_count' => (int) $realtime_count,
        );
    }
}
