<?php

/**
 * Class TRP_ALD_Cookie_Sync
 *
 * Handles cross-domain synchronization of the trp_language cookie
 * when the Multiple Domains addon is active.
 *
 * Uses iframe-based approach similar to SSO to set cookies with SameSite=None
 * which is required for cross-domain cookie access.
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit();
}

class TRP_ALD_Cookie_Sync {

    /**
     * Query parameter for cookie value in AJAX requests
     */
    const COOKIE_VALUE_PARAM = 'trp_ald_cv';

    /**
     * @var array TranslatePress settings
     */
    private $settings;

    /**
     * @var array Cached list of configured domains
     */
    private $domains = null;

    /**
     * @var string Site URL
     */
    private $site_url;

    /**
     * Constructor
     *
     * @param array $settings TranslatePress settings
     */
    public function __construct( $settings ) {
        $this->settings = $settings;
        $this->site_url = get_option('home');
    }

    /**
     * REST API namespace
     */
    const REST_NAMESPACE = 'trp-ald/v1';

    /**
     * REST API route
     */
    const REST_ROUTE = 'cookie-sync';

    /**
     * Initialize hooks
     *
     * Only initializes if Multiple Domains is configured with at least one domain
     */
    public function init_hooks() {
        // Check if Multiple Domains has any domains configured
        if ( ! $this->has_configured_domains() ) {
            return;
        }

        // Register REST API endpoint for cookie sync
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
        // Add sync data to the cookie data passed to JavaScript
        add_filter( 'trp_language_cookie_data', array( $this, 'add_sync_data_to_cookie_data' ) );
    }

    /**
     * Register REST API routes for cookie sync
     */
    public function register_rest_routes() {
        register_rest_route(
            self::REST_NAMESPACE,
            '/' . self::REST_ROUTE,
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'rest_sync_cookie' ),
                'permission_callback' => '__return_true', // Public endpoint
                'args'                => array(
                    self::COOKIE_VALUE_PARAM => array(
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                        'validate_callback' => array( $this, 'validate_language_code' ),
                    ),
                ),
            )
        );
    }

    /**
     * Validate that the language code is a published language
     *
     * @param string $value The language code to validate
     * @return bool|WP_Error True if valid, WP_Error if not
     */
    public function validate_language_code( $value ) {
        $published_languages = isset( $this->settings['publish-languages'] )
            ? $this->settings['publish-languages']
            : array();

        if ( ! in_array( $value, $published_languages, true ) ) {
            return new WP_Error(
                'invalid_language',
                __( 'Invalid language code', 'translatepress-multilingual' ),
                array( 'status' => 400 )
            );
        }

        return true;
    }

    /**
     * Check if Multiple Domains has any domains configured
     *
     * @return bool
     */
    private function has_configured_domains() {
        if (!class_exists('TRP_Multiple_Domains')){
            return false;
        }

        $domains = $this->get_sync_domains();
        // Need at least 2 domains (main + at least one mapped)
        return count( $domains ) > 1;
    }

    /**
     * Get all configured domains for sync
     *
     * Includes the main site URL and all enabled language domains.
     *
     * @return array Array of domain URLs with protocol
     */
    private function get_sync_domains() {
        if ( $this->domains !== null ) {
            return $this->domains;
        }

        $domain_mappings = isset( $this->settings['trp-multiple-domains'] )
            ? $this->settings['trp-multiple-domains']
            : array();

        $this->domains = array( $this->site_url );

        foreach ( $domain_mappings as $language_code => $mapping ) {
            if ( ! empty( $mapping['enabled'] ) && ! empty( $mapping['domain'] ) ) {
                $domain = $mapping['domain'];
                // Ensure domain has protocol
                if ( strpos( $domain, 'http://' ) !== 0 && strpos( $domain, 'https://' ) !== 0 ) {
                    $domain = ( is_ssl() ? 'https://' : 'http://' ) . $domain;
                }
                $this->domains[] = $domain;
            }
        }

        return $this->domains;
    }

    /**
     * Get domain to language code mapping
     *
     * Used by JavaScript to determine language from URL domain.
     *
     * @return array Hostname to language code mapping
     */
    private function get_domain_language_map() {
        $domain_mappings = isset( $this->settings['trp-multiple-domains'] )
            ? $this->settings['trp-multiple-domains']
            : array();

        $map = array();

        // Add mapped domains
        foreach ( $domain_mappings as $language_code => $mapping ) {
            if ( ! empty( $mapping['enabled'] ) && ! empty( $mapping['domain'] ) ) {
                $hostname = $this->get_hostname_from_url( $mapping['domain'] );
                if ( $hostname ) {
                    $map[ $hostname ] = $language_code;
                }
            }
        }

        return $map;
    }

    /**
     * Extract hostname from URL
     *
     * @param string $url URL with or without protocol
     * @return string|null Hostname or null on failure
     */
    private function get_hostname_from_url( $url ) {
        // Add protocol if missing for parse_url to work
        if ( strpos( $url, 'http://' ) !== 0 && strpos( $url, 'https://' ) !== 0 ) {
            $url = 'https://' . $url;
        }

        $parsed = parse_url( $url );
        return isset( $parsed['host'] ) ? $parsed['host'] : null;
    }

    /**
     * Get current domain with protocol
     *
     * @return string Current domain URL
     */
    private function get_current_domain() {
        $protocol = is_ssl() ? 'https://' : 'http://';
        $host = isset( $_SERVER['HTTP_HOST'] )
            ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) )
            : '';

        return $protocol . $host;
    }

    /**
     * Add sync data to cookie data passed to JavaScript
     *
     * @param array $data Existing cookie data
     * @return array Modified cookie data with sync information
     */
    public function add_sync_data_to_cookie_data( $data ) {
        $data['sync_enabled']        = true;
        $data['sync_ajax_urls']      = $this->get_sync_ajax_urls();
        $data['current_domain']      = $this->get_current_domain();
        $data['domain_language_map'] = $this->get_domain_language_map();

        return $data;
    }

    /**
     * Get REST API URLs for each configured language domain
     *
     * @return array Language code to REST API URL mapping
     */
    private function get_sync_ajax_urls() {
        $rest_path = 'wp-json/' . self::REST_NAMESPACE . '/' . self::REST_ROUTE;
        $sync_urls = array();
        $home_url = get_option('home'); // Non-filtered domain

        $domain_mappings = isset( $this->settings['trp-multiple-domains'] )
            ? $this->settings['trp-multiple-domains']
            : array();

        $published_languages = isset( $this->settings['publish-languages'] )
            ? $this->settings['publish-languages']
            : array();

        foreach ( $published_languages as $language ) {
            if ( ! empty( $domain_mappings[ $language ]['enabled'] ) && ! empty( $domain_mappings[ $language ]['domain'] ) ) {
                // Language has a mapped domain - use that domain for REST URL
                $mapped_domain = $domain_mappings[ $language ]['domain'];
                $sync_urls[ $language ] = trailingslashit( $mapped_domain ) . $rest_path;
            } else {
                // No domain mapping - use default home URL
                $sync_urls[ $language ] = trailingslashit( $home_url ) . $rest_path;
            }
        }

        return $sync_urls;
    }

    /**
     * REST API handler for cookie sync
     *
     * Sets the cookie with SameSite=None for cross-domain access.
     * Returns minimal response for iframe loading.
     *
     * @param WP_REST_Request $request The REST request object
     * @return WP_REST_Response|WP_Error
     */
    public function rest_sync_cookie( $request ) {
        $cookie_value = $request->get_param( self::COOKIE_VALUE_PARAM );

        // Set the cookie with SameSite=None for cross-domain iframe support
        $cookie_name = 'trp_language';
        $cookie_path = COOKIEPATH;
        $cookie_age  = 30; // days
        $expires     = time() + ( $cookie_age * DAY_IN_SECONDS );

        $this->set_cookie_with_samesite( $cookie_name, $cookie_value, $expires, $cookie_path );

        // Create response
        $response = new WP_REST_Response(
            array(
                'success' => true,
                'cookie'  => $cookie_value,
            ),
            200
        );

        // Add CSP header to the response to allow iframe loading
        $this->get_sync_domains(); // Ensure domains are populated
        if ( ! empty( $this->domains ) ) {
            $frame_ancestors = array_merge( array( "'self'" ), $this->domains );
            $response->header( 'Content-Security-Policy', 'frame-ancestors ' . implode( ' ', $frame_ancestors ) );
        }

        return $response;
    }

    /**
     * Set cookie with SameSite=None attribute
     *
     * PHP's setcookie() doesn't support SameSite in older versions,
     * so we use header() directly.
     *
     * @param string $name    Cookie name
     * @param string $value   Cookie value
     * @param int    $expires Expiration timestamp
     * @param string $path    Cookie path
     */
    private function set_cookie_with_samesite( $name, $value, $expires, $path ) {
        if ( headers_sent() ) {
            return;
        }

        $name  = rawurlencode( $name );
        $value = rawurlencode( $value );

        $cookie_header = sprintf(
            'Set-Cookie: %s=%s; expires=%s; path=%s; SameSite=None%s',
            $name,
            $value,
            gmdate( 'D, d-M-Y H:i:s', $expires ) . ' GMT',
            $path,
            is_ssl() ? '; Secure' : ''
        );

        header( $cookie_header, false );
    }

    /**
     * Send Content-Security-Policy headers
     *
     * Allows iframes from all configured domains.
     * Must be called early in WordPress lifecycle before any output.
     */
    public function send_headers() {
        if ( headers_sent() ) {
            return;
        }

        // Ensure domains are populated
        if ( empty( $this->domains ) ) {
            $this->get_sync_domains();
        }

        if ( empty( $this->domains ) ) {
            return;
        }

        // Build frame-ancestors value with 'self' and all configured domains
        $frame_ancestors = array_merge( array( "'self'" ), $this->domains );

        // Remove any existing CSP header and set our own
        header_remove( 'Content-Security-Policy' );
        header( sprintf( 'Content-Security-Policy: frame-ancestors %s', implode( ' ', $frame_ancestors ) ) );
    }

}
