<?php
/**
 * Database Schema for Historical Prices
 * Creates tables for storing historical price data
 *
 * @package IPPGI_Prices
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class IPPGI_Prices_Database {

    /**
     * Table names (without prefix)
     */
    const TABLES = array(
        'GI'       => 'prices_gi',
        'GL'       => 'prices_gl',
        'PPGI'     => 'prices_ppgi',
        'HRC'      => 'prices_hrc',
        'CRC Hard' => 'prices_crc_hard',
        'AL'       => 'prices_al',
    );

    /**
     * Create all price history tables
     */
    public static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Create price history tables
        foreach (self::TABLES as $material => $table_name) {
            $table_full_name = $wpdb->prefix . $table_name;

            $sql = "CREATE TABLE IF NOT EXISTS {$table_full_name} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                product_spec varchar(255) NOT NULL,
                statistics_time datetime NOT NULL,
                timestamp bigint(20) NOT NULL,
                price_cny decimal(10,2) NOT NULL,
                price_usd decimal(10,2) NOT NULL,
                price_tax_cny decimal(10,2) NOT NULL,
                price_tax_usd decimal(10,2) NOT NULL,
                exchange_rate decimal(10,6) NOT NULL,
                site_id varchar(50) NOT NULL,
                category_id varchar(50) NOT NULL,
                width varchar(20) NOT NULL,
                thickness varchar(20) NOT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY unique_spec_time (product_spec, statistics_time),
                KEY idx_statistics_time (statistics_time),
                KEY idx_product_spec (product_spec),
                KEY idx_timestamp (timestamp)
            ) {$charset_collate};";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }

        // Create exchange rates table
        self::create_exchange_rates_table();

        // Log table creation
        error_log('IPPGI Prices: Created ' . count(self::TABLES) . ' historical price tables and exchange rates table');
    }

    /**
     * Create exchange rates table
     */
    public static function create_exchange_rates_table() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . IPPGI_Prices_Currency_Converter::HISTORICAL_RATES_TABLE;

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            rate_date date NOT NULL,
            rate decimal(10,6) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_date (rate_date),
            KEY idx_rate_date (rate_date)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Get table name for a material type
     *
     * @param string $material_type Material type (GI, GL, PPGI, etc.)
     * @return string|false Full table name or false if invalid
     */
    public static function get_table_name($material_type) {
        global $wpdb;

        if (!isset(self::TABLES[$material_type])) {
            return false;
        }

        return $wpdb->prefix . self::TABLES[$material_type];
    }

    /**
     * Insert or update historical price record
     *
     * @param string $material_type Material type
     * @param array $data Price data
     * @return int|false Insert ID or false on failure
     */
    public static function insert_price_record($material_type, $data) {
        global $wpdb;

        $table_name = self::get_table_name($material_type);
        if (!$table_name) {
            return false;
        }

        // Check if record already exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table_name} WHERE product_spec = %s AND statistics_time = %s",
            $data['product_spec'],
            $data['statistics_time']
        ));

        if ($existing) {
            // Update existing record
            $result = $wpdb->update(
                $table_name,
                $data,
                array(
                    'product_spec' => $data['product_spec'],
                    'statistics_time' => $data['statistics_time'],
                )
            );
            return $existing;
        } else {
            // Insert new record
            $result = $wpdb->insert($table_name, $data);
            return $result ? $wpdb->insert_id : false;
        }
    }

    /**
     * Get historical prices for a product spec
     *
     * @param string $material_type Material type
     * @param string $product_spec Product specification
     * @param string $from Start date
     * @param string $to End date
     * @return array|false Price records or false on failure
     */
    public static function get_historical_prices($material_type, $product_spec, $from = '', $to = '') {
        global $wpdb;

        $table_name = self::get_table_name($material_type);
        if (!$table_name) {
            return false;
        }

        $where = array('product_spec = %s');
        $params = array($product_spec);

        if (!empty($from)) {
            $where[] = 'statistics_time >= %s';
            $params[] = $from;
        }

        if (!empty($to)) {
            $where[] = 'statistics_time <= %s';
            $params[] = $to;
        }

        $where_clause = implode(' AND ', $where);

        $query = "SELECT * FROM {$table_name} WHERE {$where_clause} ORDER BY statistics_time ASC";
        $prepared = $wpdb->prepare($query, $params);

        return $wpdb->get_results($prepared, ARRAY_A);
    }

    /**
     * Drop all price history tables (for cleanup)
     */
    public static function drop_tables() {
        global $wpdb;

        foreach (self::TABLES as $table_name) {
            $table_full_name = $wpdb->prefix . $table_name;
            $wpdb->query("DROP TABLE IF EXISTS {$table_full_name}");
        }

        error_log('IPPGI Prices: Dropped all historical price tables');
    }
}
