<?php
/**
 * Plugin Name: IPPGI Prices
 * Plugin URI: https://ippgi.com
 * Description: Fetches and caches material price data from external API with scheduled tasks
 * Version: 1.0.0
 * Author: IPPGI
 * Author URI: https://ippgi.com
 * License: GPL v2 or later
 * Text Domain: ippgi-prices
 *
 * @package IPPGI_Prices
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('IPPGI_PRICES_VERSION', '1.0.0');
define('IPPGI_PRICES_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('IPPGI_PRICES_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files
require_once IPPGI_PRICES_PLUGIN_DIR . 'includes/class-database.php';
require_once IPPGI_PRICES_PLUGIN_DIR . 'includes/class-currency-converter.php';
require_once IPPGI_PRICES_PLUGIN_DIR . 'includes/class-scheduler.php';
require_once IPPGI_PRICES_PLUGIN_DIR . 'includes/class-api-client.php';
require_once IPPGI_PRICES_PLUGIN_DIR . 'includes/class-cache-manager.php';
require_once IPPGI_PRICES_PLUGIN_DIR . 'includes/class-rest-api.php';
require_once IPPGI_PRICES_PLUGIN_DIR . 'includes/class-historical-importer.php';

/**
 * Main plugin class
 */
class IPPGI_Prices {

    /**
     * Single instance of the class
     */
    private static $instance = null;

    /**
     * Scheduler instance
     */
    public $scheduler;

    /**
     * API Client instance
     */
    public $api_client;

    /**
     * Cache Manager instance
     */
    public $cache_manager;

    /**
     * REST API instance
     */
    public $rest_api;

    /**
     * Get single instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->init();
    }

    /**
     * Initialize plugin
     */
    private function init() {
        // Initialize components
        $this->cache_manager = new IPPGI_Prices_Cache_Manager();
        $this->api_client = new IPPGI_Prices_API_Client($this->cache_manager);
        $this->scheduler = new IPPGI_Prices_Scheduler($this->api_client, $this->cache_manager);
        $this->rest_api = new IPPGI_Prices_REST_API($this->api_client, $this->cache_manager);

        // Register activation/deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables
        IPPGI_Prices_Database::create_tables();

        // Schedule cron events
        $this->scheduler->schedule_events();

        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        $this->scheduler->unschedule_events();
        flush_rewrite_rules();
    }
}

/**
 * Initialize the plugin
 */
function ippgi_prices() {
    return IPPGI_Prices::get_instance();
}

// Start the plugin
ippgi_prices();
