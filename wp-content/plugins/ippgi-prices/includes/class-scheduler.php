<?php
/**
 * Scheduler Class
 * Manages WP-Cron scheduled tasks for price data updates
 *
 * @package IPPGI_Prices
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class IPPGI_Prices_Scheduler {

    /**
     * Cron hook name for hourly updates
     */
    const CRON_HOOK = 'ippgi_prices_hourly_update';

    /**
     * Cron hook name for midnight price collection
     */
    const CRON_HOOK_MIDNIGHT = 'ippgi_prices_midnight_collection';

    /**
     * API client instance
     */
    private $api_client;

    /**
     * Cache manager instance
     */
    private $cache_manager;

    /**
     * Current price collector instance
     */
    private $price_collector;

    /**
     * Hours to run the task (9:00 - 17:00)
     */
    private $schedule_hours = array(9, 10, 11, 12, 13, 14, 15, 16, 17);

    /**
     * Constructor
     *
     * @param IPPGI_Prices_API_Client $api_client API client instance
     * @param IPPGI_Prices_Cache_Manager $cache_manager Cache manager instance
     */
    public function __construct($api_client, $cache_manager) {
        $this->api_client = $api_client;
        $this->cache_manager = $cache_manager;

        // Initialize price collector
        require_once(plugin_dir_path(__FILE__) . 'class-current-price-collector.php');
        $this->price_collector = new IPPGI_Prices_Current_Price_Collector($api_client);

        // Register cron hooks
        add_action(self::CRON_HOOK, array($this, 'run_scheduled_task'));
        add_action(self::CRON_HOOK_MIDNIGHT, array($this, 'run_midnight_collection'));

        // Add custom cron schedule
        add_filter('cron_schedules', array($this, 'add_cron_schedules'));
    }

    /**
     * Add custom cron schedules
     *
     * @param array $schedules Existing schedules
     * @return array Modified schedules
     */
    public function add_cron_schedules($schedules) {
        // Add hourly schedule (WordPress has this by default, but we define it explicitly)
        if (!isset($schedules['hourly'])) {
            $schedules['hourly'] = array(
                'interval' => 3600,
                'display' => __('Once Hourly', 'ippgi-prices'),
            );
        }

        return $schedules;
    }

    /**
     * Schedule all events (9:00 - 17:00 + midnight) in Beijing time (Asia/Shanghai)
     */
    public function schedule_events() {
        // Clear any existing schedules first
        $this->unschedule_events();

        // Use WordPress timezone (should be set to Asia/Shanghai or UTC+8)
        $timezone = wp_timezone();
        $now = new DateTime('now', $timezone);
        $today = $now->format('Y-m-d');
        $current_timestamp = time(); // Real Unix timestamp

        // Schedule midnight task (00:00 Beijing time) for price collection
        $midnight = new DateTime("{$today} 00:00:00", $timezone);
        $midnight_time = $midnight->getTimestamp();
        if ($midnight_time < $current_timestamp) {
            $midnight->modify('+1 day');
            $midnight_time = $midnight->getTimestamp();
        }
        wp_schedule_event($midnight_time, 'daily', self::CRON_HOOK_MIDNIGHT);
        error_log('IPPGI Prices: Scheduled midnight price collection at ' . $midnight->format('Y-m-d H:i:s T') . ' (timestamp: ' . $midnight_time . ')');

        // Schedule events for each hour (9:00 - 17:00 Beijing time)
        foreach ($this->schedule_hours as $hour) {
            // Calculate timestamp for this hour today in Beijing time
            $schedule_dt = new DateTime("{$today} {$hour}:00:00", $timezone);
            $schedule_time = $schedule_dt->getTimestamp();

            // If the time has already passed today, schedule for tomorrow
            if ($schedule_time < $current_timestamp) {
                $schedule_dt->modify('+1 day');
                $schedule_time = $schedule_dt->getTimestamp();
            }

            // Schedule the event
            wp_schedule_event($schedule_time, 'daily', self::CRON_HOOK, array($hour));
        }

        // Log scheduling
        error_log('IPPGI Prices: Scheduled ' . count($this->schedule_hours) . ' daily events (9:00-17:00 Beijing time)');
    }

    /**
     * Unschedule all events
     */
    public function unschedule_events() {
        // Get all scheduled events for our hooks
        $scheduled = _get_cron_array();

        if (empty($scheduled)) {
            return;
        }

        // Loop through and unschedule all instances of our hooks
        foreach ($scheduled as $timestamp => $cron) {
            // Unschedule hourly update hook
            if (isset($cron[self::CRON_HOOK])) {
                foreach ($cron[self::CRON_HOOK] as $key => $event) {
                    wp_unschedule_event($timestamp, self::CRON_HOOK, $event['args']);
                }
            }
            // Unschedule midnight collection hook
            if (isset($cron[self::CRON_HOOK_MIDNIGHT])) {
                foreach ($cron[self::CRON_HOOK_MIDNIGHT] as $key => $event) {
                    wp_unschedule_event($timestamp, self::CRON_HOOK_MIDNIGHT, $event['args']);
                }
            }
        }

        error_log('IPPGI Prices: Unscheduled all events');
    }

    /**
     * Run the scheduled task
     * This is called by WP-Cron at each scheduled time (9:00 - 17:00)
     *
     * @param int $hour The hour this task is running for (9-17)
     */
    public function run_scheduled_task($hour = null) {
        $start_time = microtime(true);

        error_log(sprintf(
            'IPPGI Prices: Starting scheduled task at %s (hour: %d)',
            current_time('Y-m-d H:i:s'),
            $hour
        ));

        // Step 1: Clear all caches
        $clear_results = $this->cache_manager->clear_all_caches();

        error_log(sprintf(
            'IPPGI Prices: Cleared caches - Price list: %s, Real-time prices: %d',
            $clear_results['price_list'] ? 'yes' : 'no',
            $clear_results['realtime_prices_count']
        ));

        // Step 2: Actively fetch new price list data
        $price_list_result = $this->api_client->fetch_price_list(true);

        if (is_wp_error($price_list_result)) {
            error_log(sprintf(
                'IPPGI Prices: Failed to fetch price list - %s: %s',
                $price_list_result->get_error_code(),
                $price_list_result->get_error_message()
            ));
        } else {
            error_log('IPPGI Prices: Successfully fetched and cached new price list');
        }

        // Calculate execution time
        $execution_time = microtime(true) - $start_time;

        error_log(sprintf(
            'IPPGI Prices: Completed scheduled task in %.2f seconds',
            $execution_time
        ));

        // Store last run info
        update_option('ippgi_prices_last_run', array(
            'timestamp' => current_time('timestamp'),
            'datetime' => current_time('Y-m-d H:i:s'),
            'hour' => $hour,
            'execution_time' => $execution_time,
            'cache_cleared' => $clear_results,
            'price_list_fetched' => !is_wp_error($price_list_result),
        ));
    }

    /**
     * Run midnight price collection task
     * This is called by WP-Cron at 00:00 to save yesterday's prices
     */
    public function run_midnight_collection() {
        $start_time = microtime(true);

        error_log(sprintf(
            'IPPGI Prices: Starting midnight price collection at %s',
            current_time('Y-m-d H:i:s')
        ));

        // Step 1: Collect and save current prices to database
        // At midnight, this saves yesterday's data (cached from 17:00)
        // The exchange rate is extracted from the cached price list data
        $collection_results = $this->price_collector->collect_all_current_prices(false);

        if ($collection_results['success']) {
            error_log(sprintf(
                'IPPGI Prices: Midnight collection saved %d price records (%.2f seconds)',
                $collection_results['total_saved'],
                $collection_results['duration']
            ));
        } else {
            error_log(sprintf(
                'IPPGI Prices: Midnight collection failed - %d errors',
                count($collection_results['errors'])
            ));
        }

        // Step 2: Clear all caches after saving data
        $clear_results = $this->cache_manager->clear_all_caches();
        error_log(sprintf(
            'IPPGI Prices: Cleared caches after midnight collection - Price list: %s, Real-time prices: %d',
            $clear_results['price_list'] ? 'yes' : 'no',
            $clear_results['realtime_prices_count']
        ));

        // Step 3: Fetch new price list data (for today)
        $price_list_result = $this->api_client->fetch_price_list(true);
        if (is_wp_error($price_list_result)) {
            error_log(sprintf(
                'IPPGI Prices: Failed to fetch new price list after midnight - %s',
                $price_list_result->get_error_message()
            ));
        } else {
            error_log('IPPGI Prices: Successfully fetched and cached new price list for today');
        }

        // Calculate execution time
        $execution_time = microtime(true) - $start_time;

        error_log(sprintf(
            'IPPGI Prices: Completed midnight collection in %.2f seconds',
            $execution_time
        ));

        // Store last midnight run info
        update_option('ippgi_prices_last_midnight_run', array(
            'timestamp' => current_time('timestamp'),
            'datetime' => current_time('Y-m-d H:i:s'),
            'execution_time' => $execution_time,
            'prices_collected' => $collection_results['success'],
            'prices_saved' => $collection_results['total_saved'],
            'cache_cleared' => $clear_results,
            'new_price_list_fetched' => !is_wp_error($price_list_result),
            'errors' => $collection_results['errors'],
        ));
    }

    /**
     * Get next scheduled run times
     *
     * @return array Array of next scheduled times
     */
    public function get_next_scheduled_times() {
        $scheduled = _get_cron_array();
        $next_runs = array();

        if (empty($scheduled)) {
            return $next_runs;
        }

        foreach ($scheduled as $timestamp => $cron) {
            // Check hourly update hook
            if (isset($cron[self::CRON_HOOK])) {
                foreach ($cron[self::CRON_HOOK] as $event) {
                    $hour = isset($event['args'][0]) ? $event['args'][0] : null;
                    $next_runs[] = array(
                        'timestamp' => $timestamp,
                        'datetime' => date('Y-m-d H:i:s', $timestamp),
                        'hour' => $hour,
                        'type' => 'hourly_update',
                    );
                }
            }
            // Check midnight collection hook
            if (isset($cron[self::CRON_HOOK_MIDNIGHT])) {
                foreach ($cron[self::CRON_HOOK_MIDNIGHT] as $event) {
                    $next_runs[] = array(
                        'timestamp' => $timestamp,
                        'datetime' => date('Y-m-d H:i:s', $timestamp),
                        'hour' => 0,
                        'type' => 'midnight_collection',
                    );
                }
            }
        }

        // Sort by timestamp
        usort($next_runs, function($a, $b) {
            return $a['timestamp'] - $b['timestamp'];
        });

        return $next_runs;
    }

    /**
     * Get last run info
     *
     * @return array|false Last run info or false if never run
     */
    public function get_last_run_info() {
        return get_option('ippgi_prices_last_run', false);
    }

    /**
     * Get last midnight run info
     *
     * @return array|false Last midnight run info or false if never run
     */
    public function get_last_midnight_run_info() {
        return get_option('ippgi_prices_last_midnight_run', false);
    }

    /**
     * Manually trigger the scheduled task (for testing)
     */
    public function trigger_manual_run() {
        $current_hour = (int) current_time('H');
        $this->run_scheduled_task($current_hour);
    }

    /**
     * Manually trigger midnight collection (for testing)
     */
    public function trigger_midnight_collection() {
        $this->run_midnight_collection();
    }
}
