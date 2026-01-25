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
            ),
        ));

        // Get real-time price
        register_rest_route(self::NAMESPACE, '/price', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_realtime_price'),
            'permission_callback' => '__return_true',
            'args' => array(
                'product_type' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'width' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
                'thickness' => array(
                    'required' => true,
                    'type' => 'number',
                    'sanitize_callback' => function($value) {
                        return (float) $value;
                    },
                ),
                'date' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'default' => '',
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

        // Get full price list (from cache)
        $data = $this->api_client->get_price_list();

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
        $product_type = $request->get_param('product_type');
        $width = $request->get_param('width');
        $thickness = $request->get_param('thickness');
        $date = $request->get_param('date');

        $data = $this->api_client->get_realtime_price($product_type, $width, $thickness, $date);

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
            'cached' => (bool) $this->cache_manager->get_realtime_price($product_type, $width, $thickness),
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
