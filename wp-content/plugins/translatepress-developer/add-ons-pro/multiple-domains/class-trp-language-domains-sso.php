<?php

/**
 * Class TRP_Language_Domains_SSO
 *
 * Handles Single Sign-On across multiple language domains using iframe-based authentication.
 *
 * This class is based on WPML_Language_Per_Domain_SSO from WPML
 * (https://wpml.org/), licensed under the GNU General Public License v2
 * or later.
 *
 * Original source: sitepress-multilingual-cms/classes/request-handling/class-wpml-language-per-domain-sso.php
 * Copyright (c) OnTheGoSystems
 *
 * Modifications for TranslatePress:
 * - Adapted to work with TranslatePress settings structure
 * - Added logout propagation across domains
 * - Added explicit token validation before setting cookies
 * - Replaced wp_create_nonce() with cryptographically secure random_bytes()
 * - Custom cookie handling with SameSite=None for iframe support
 *
 * @license GPL-2.0-or-later
 */
class TRP_Language_Domains_SSO {

    const SSO_NONCE = 'trp_sso';

    const TRANSIENT_SSO_STARTED = 'trp_sso_started';
    const TRANSIENT_DOMAIN = 'trp_sso_domain_';
    const TRANSIENT_USER = 'trp_sso_user_';
    const TRANSIENT_SESSION_TOKEN = 'trp_sso_session_';

    const IFRAME_USER_TOKEN_KEY = 'trp_sso_token';
    const IFRAME_USER_TOKEN_KEY_FOR_DOMAIN = 'trp_sso_token_domain';
    const IFRAME_DOMAIN_HASH_KEY = 'trp_sso_iframe_hash';
    const IFRAME_USER_STATUS_KEY = 'trp_sso_user_status';

    const SSO_TIMEOUT = 60; // 60 seconds

    /** @var array */
    private $settings;

    /** @var array */
    private $domains;

    /** @var int */
    private $current_user_id;

    /** @var string */
    private $site_url;

    public function __construct( $settings ) {
        $this->settings = $settings;
        $this->site_url = get_home_url();
        $this->domains = $this->get_domains();
    }

    public function init_hooks() {
        if ( $this->is_sso_started() ) {
            add_action( 'init', array( $this, 'init_action' ) );

            add_action( 'wp_footer', array( $this, 'add_iframes_to_footer' ) );
            add_action( 'admin_footer', array( $this, 'add_iframes_to_footer' ) );
            add_action( 'login_footer', array( $this, 'add_iframes_to_footer' ) );
        }

        add_action( 'wp_login', array( $this, 'wp_login_action' ), 10, 2 );
        add_action( 'wp_logout', array( $this, 'wp_logout_action' ) );
        add_filter( 'logout_redirect', array( $this, 'add_redirect_user_token' ), 10, 3 );
    }

    public function init_action() {
        $this->send_headers();
        $this->set_current_user_id();

        if ( $this->is_iframe_request() ) {
            $this->process_iframe_request();
        }
    }

    /**
     * @param string $user_login
     * @param WP_User $user
     */
    public function wp_login_action( $user_login, $user ) {
        $this->init_sso_transients( (int) $user->ID );
    }

    /**
     * Initialize SSO transients on logout to propagate logout to other domains
     */
    public function wp_logout_action() {
        $user_id = get_current_user_id();
        if ( $user_id > 0 ) {
            $this->init_sso_transients( $user_id );
        }
    }

    /**
     * Trigger SSO for the current user
     *
     * This is used when settings are saved, so the admin user
     * gets authenticated on all enabled domains immediately.
     *
     * @param array $settings Optional settings to extract domains from (uses stored settings if not provided)
     */
    public function trigger_sso( $settings = null ) {
        $user_id = get_current_user_id();
        if ( $user_id <= 0 ) {
            return;
        }

        // Update domains from provided settings if given
        if ( $settings !== null ) {
            $this->settings = $settings;
            $this->domains = $this->get_domains();
        }

        $this->init_sso_transients( $user_id );
    }

    /**
     * @param string $redirect_to
     * @param string $requested_redirect_to
     * @param WP_User|WP_Error $user
     * @return string
     */
    public function add_redirect_user_token( $redirect_to, $requested_redirect_to, $user ) {
        // Add token to redirect URL if SSO is active and user is valid
        if ( ! is_wp_error( $user ) && $this->is_sso_started() ) {
            return add_query_arg( self::IFRAME_USER_TOKEN_KEY, $this->create_user_token( $user->ID ), $redirect_to );
        }

        return $redirect_to;
    }

    public function add_iframes_to_footer() {
        $is_user_logged_in = is_user_logged_in();

        if ( $is_user_logged_in && $this->is_sso_started() ) {
            $this->save_session_token( wp_get_session_token(), $this->current_user_id );
        }

        foreach ( $this->domains as $domain ) {
            if ( $domain !== $this->get_current_domain() && $this->is_sso_started_for_domain( $domain ) ) {

                $iframe_url = add_query_arg(
                    array(
                        self::IFRAME_DOMAIN_HASH_KEY => $this->get_hash( $domain ),
                        self::IFRAME_USER_STATUS_KEY => $is_user_logged_in ? 'trp_user_signed_in' : 'trp_user_signed_out',
                        self::IFRAME_USER_TOKEN_KEY_FOR_DOMAIN => $this->create_user_token_for_domains( $this->current_user_id ),
                    ),
                    trailingslashit( $domain )
                );
                ?>
                <iframe class="trp-sso-iframe" style="display:none" src="<?php echo esc_url( $iframe_url ); ?>"></iframe>
                <?php
            }
        }
    }

    private function send_headers() {
        if ( headers_sent() ) {
            return;
        }
        header( sprintf( 'Content-Security-Policy: frame-ancestors %s', implode( ' ', $this->domains ) ) );
    }

    private function set_current_user_id( $user_id = null ) {
        if ( $user_id ) {
            $this->current_user_id = $user_id;
        } else {
            $this->current_user_id = $this->get_user_id_from_token() ?: get_current_user_id();
        }
    }

    private function process_iframe_request() {
        if ( $this->validate_user_sign_request() ) {
            nocache_headers();

            // Validate token was valid and mapped to a real user
            if ( $this->current_user_id <= 0 ) {
                exit();
            }

            if ( isset( $_GET[ self::IFRAME_USER_STATUS_KEY ] ) && $_GET[ self::IFRAME_USER_STATUS_KEY ] == 'trp_user_signed_in' ) {
                wp_clear_auth_cookie();
                $user = get_user_by( 'id', $this->current_user_id );

                if ( $user !== false ) {
                    wp_set_current_user( $this->current_user_id );

                    if ( is_ssl() ) {
                        $this->set_auth_cookie(
                            $this->current_user_id,
                            $this->get_session_token( $this->current_user_id )
                        );
                    } else {
                        wp_set_auth_cookie(
                            $this->current_user_id,
                            false,
                            '',
                            $this->get_session_token( $this->current_user_id )
                        );
                    }
                    do_action( 'wp_login', $user->user_login, $user );
                }
            } else {
                // Logout: clear cookies and destroy sessions
                $this->clear_auth_cookies();
                $sessions = WP_Session_Tokens::get_instance( $this->current_user_id );
                $sessions->destroy_all();
            }
            $this->finish_sso_for_domain( $this->get_current_domain() );
        }

        exit();
    }

    private function validate_user_sign_request() {
        return isset( $_GET[ self::IFRAME_USER_STATUS_KEY ] )
               && $this->is_sso_started_for_domain( $this->get_current_domain() );
    }

    private function get_user_id_from_token() {
        $user_id = 0;

        if ( isset( $_GET[ self::IFRAME_USER_TOKEN_KEY ] ) ) {
            $transient_key = $this->create_transient_key(
                self::TRANSIENT_USER,
                null,
                sanitize_text_field( wp_unslash( $_GET[ self::IFRAME_USER_TOKEN_KEY ] ) )
            );
            $user_id = (int) get_transient( $transient_key );
            delete_transient( $transient_key );
        } elseif ( isset( $_GET[ self::IFRAME_USER_TOKEN_KEY_FOR_DOMAIN ] ) ) {
            $transient_key = $this->create_transient_key(
                self::TRANSIENT_USER,
                $this->get_current_domain(),
                sanitize_text_field( wp_unslash( $_GET[ self::IFRAME_USER_TOKEN_KEY_FOR_DOMAIN ] ) )
            );
            $user_id = (int) get_transient( $transient_key );
            delete_transient( $transient_key );
        }

        return $user_id;
    }

    private function init_sso_transients( $user_id ) {
        set_transient( self::TRANSIENT_SSO_STARTED, true, self::SSO_TIMEOUT );

        foreach ( $this->domains as $domain ) {
            if ( $this->get_current_domain() !== $domain ) {
                set_transient(
                    $this->create_transient_key( self::TRANSIENT_DOMAIN, $domain, $user_id ),
                    $this->get_hash( $domain ),
                    self::SSO_TIMEOUT
                );
            }
        }
    }

    private function finish_sso_for_domain( $domain ) {
        delete_transient(
            $this->create_transient_key(
                self::TRANSIENT_DOMAIN,
                $domain,
                $this->current_user_id
            )
        );
    }

    private function is_sso_started_for_domain( $domain ) {
        return (bool) get_transient(
            $this->create_transient_key(
                self::TRANSIENT_DOMAIN,
                $domain,
                $this->current_user_id
            )
        );
    }

    private function get_current_domain() {
        $host = '';

        if ( isset( $_SERVER['HTTP_HOST'] ) ) {
            $host = sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) );
        }

        return $this->get_current_protocol() . $host;
    }

    private function get_current_protocol() {
        return is_ssl() ? 'https://' : 'http://';
    }

    private function get_domains() {
        $domain_mappings = isset( $this->settings['trp-multiple-domains'] ) ? $this->settings['trp-multiple-domains'] : array();

        $sso_domains = array( $this->site_url );

        foreach ( $domain_mappings as $language_code => $mapping ) {
            if ( ! empty( $mapping['enabled'] ) && ! empty( $mapping['domain'] ) ) {
                $domain = $mapping['domain'];
                // Check if domain already has protocol, if not add it
                if ( strpos( $domain, 'http://' ) !== 0 && strpos( $domain, 'https://' ) !== 0 ) {
                    $domain = $this->get_current_protocol() . $domain;
                }
                $sso_domains[] = $domain;
            }
        }

        return $sso_domains;
    }

    private function is_iframe_request() {
        return isset( $_GET[ self::IFRAME_DOMAIN_HASH_KEY ] )
               && ! wp_doing_ajax()
               && $this->is_sso_started_for_domain( $this->get_current_domain() )
               && isset( $_GET[ self::IFRAME_DOMAIN_HASH_KEY ] )
               && $this->get_hash( $this->get_current_domain() ) === sanitize_text_field( wp_unslash( $_GET[ self::IFRAME_DOMAIN_HASH_KEY ] ) );
    }

    private function is_sso_started() {
        return (bool) get_transient( self::TRANSIENT_SSO_STARTED );
    }

    private function create_user_token( $user_id ) {
        $token = bin2hex( random_bytes( 32 ) );
        set_transient(
            $this->create_transient_key( self::TRANSIENT_USER, null, $token ),
            $user_id,
            self::SSO_TIMEOUT
        );

        return $token;
    }

    private function create_user_token_for_domains( $user_id ) {
        $token = bin2hex( random_bytes( 32 ) );
        foreach ( $this->domains as $domain ) {
            if ( $this->get_current_domain() !== $domain ) {
                set_transient(
                    $this->create_transient_key( self::TRANSIENT_USER, $domain, $token ),
                    $user_id,
                    self::SSO_TIMEOUT
                );
            }
        }

        return $token;
    }

    private function save_session_token( $session_token, $user_id ) {
        set_transient(
            $this->create_transient_key( self::TRANSIENT_SESSION_TOKEN, null, $user_id ),
            $session_token,
            self::SSO_TIMEOUT
        );
    }

    private function get_session_token( $user_id ) {
        return (string) get_transient( $this->create_transient_key( self::TRANSIENT_SESSION_TOKEN, null, $user_id ) );
    }

    private function create_transient_key( $prefix, $domain = null, $token = null ) {
        return $prefix . ( $token ? (string) $token : '' ) . ( $domain ? '_' . $this->get_hash( $domain ) : '' );
    }

    private function get_hash( $value ) {
        return hash( 'sha256', self::SSO_NONCE . $value );
    }

    /**
     * Custom auth cookie setter with SameSite=None support for iframe authentication
     *
     * @param int $user_id
     * @param string $token
     */
    private function set_auth_cookie( $user_id, $token = '' ) {
        $expiration = time() + apply_filters( 'auth_cookie_expiration', 2 * DAY_IN_SECONDS, $user_id, false );
        $expire = 0;
        $secure = apply_filters( 'secure_auth_cookie', is_ssl(), $user_id );

        if ( $secure ) {
            $auth_cookie_name = SECURE_AUTH_COOKIE;
            $scheme = 'secure_auth';
        } else {
            $auth_cookie_name = AUTH_COOKIE;
            $scheme = 'auth';
        }

        if ( '' === $token ) {
            $manager = WP_Session_Tokens::get_instance( $user_id );
            $token = $manager->create( $expiration );
        }

        $auth_cookie = wp_generate_auth_cookie( $user_id, $expiration, $scheme, $token );
        $logged_in_cookie = wp_generate_auth_cookie( $user_id, $expiration, 'logged_in', $token );

        do_action( 'set_auth_cookie', $auth_cookie, $expire, $expiration, $user_id, $scheme, $token );
        do_action( 'set_logged_in_cookie', $logged_in_cookie, $expire, $expiration, $user_id, 'logged_in', $token );

        if ( ! apply_filters( 'send_auth_cookies', true ) ) {
            return;
        }

        // Set cookies with SameSite=None for cross-domain iframe support
        $this->set_cookie( $auth_cookie_name, $auth_cookie, $expire, PLUGINS_COOKIE_PATH, COOKIE_DOMAIN, true, 'None' );
        $this->set_cookie( $auth_cookie_name, $auth_cookie, $expire, ADMIN_COOKIE_PATH, COOKIE_DOMAIN, true, 'None' );
        $this->set_cookie( LOGGED_IN_COOKIE, $logged_in_cookie, $expire, COOKIEPATH, COOKIE_DOMAIN, true, 'None' );

        if ( COOKIEPATH != SITECOOKIEPATH ) {
            $this->set_cookie( LOGGED_IN_COOKIE, $logged_in_cookie, $expire, SITECOOKIEPATH, COOKIE_DOMAIN, true, 'None' );
        }
    }

    /**
     * Clear authentication cookies with SameSite=None support for iframe logout
     */
    private function clear_auth_cookies() {
        if ( headers_sent() ) {
            return;
        }

        $secure = apply_filters( 'secure_auth_cookie', is_ssl(), $this->current_user_id );

        if ( $secure ) {
            $auth_cookie_name = SECURE_AUTH_COOKIE;
        } else {
            $auth_cookie_name = AUTH_COOKIE;
        }

        // Clear auth cookies with SameSite=None on all paths
        $this->clear_cookie( $auth_cookie_name, PLUGINS_COOKIE_PATH, COOKIE_DOMAIN, true, 'None' );
        $this->clear_cookie( $auth_cookie_name, ADMIN_COOKIE_PATH, COOKIE_DOMAIN, true, 'None' );
        $this->clear_cookie( LOGGED_IN_COOKIE, COOKIEPATH, COOKIE_DOMAIN, true, 'None' );

        if ( COOKIEPATH != SITECOOKIEPATH ) {
            $this->clear_cookie( LOGGED_IN_COOKIE, SITECOOKIEPATH, COOKIE_DOMAIN, true, 'None' );
        }

        // Also clear using standard WordPress method as fallback
        wp_clear_auth_cookie();
    }

    /**
     * Custom cookie setter with SameSite attribute support
     *
     * @param string $name
     * @param string $value
     * @param int $expires
     * @param string $path
     * @param string $domain
     * @param bool $httponly
     * @param string $samesite
     */
    private function set_cookie( $name, $value, $expires, $path, $domain, $httponly = false, $samesite = 'Lax' ) {
        if ( headers_sent() ) {
            return;
        }

        $name = rawurlencode( $name );
        $value = rawurlencode( $value );

        $cookie_header = 'Set-Cookie: ' . $name . '=' . $value
            . ( $domain ? '; Domain=' . $domain : '' )
            . ( $expires ? '; expires=' . gmdate( 'D, d-M-Y H:i:s', $expires ) . ' GMT' : '' )
            . ( $path ? '; Path=' . $path : '' )
            . ( is_ssl() ? '; Secure' : '' )
            . ( $httponly ? '; HttpOnly' : '' )
            . '; SameSite=' . $samesite;

        header( $cookie_header, false );
    }

    /**
     * Custom cookie clearer with SameSite attribute support
     * Clears cookies by setting them to empty value with past expiration
     *
     * @param string $name
     * @param string $path
     * @param string $domain
     * @param bool $httponly
     * @param string $samesite
     */
    private function clear_cookie( $name, $path, $domain, $httponly = false, $samesite = 'Lax' ) {
        if ( headers_sent() ) {
            return;
        }

        $name = rawurlencode( $name );

        $cookie_header = 'Set-Cookie: ' . $name . '='
            . ( $domain ? '; Domain=' . $domain : '' )
            . '; expires=' . gmdate( 'D, d-M-Y H:i:s', time() - YEAR_IN_SECONDS ) . ' GMT'
            . ( $path ? '; Path=' . $path : '' )
            . ( is_ssl() ? '; Secure' : '' )
            . ( $httponly ? '; HttpOnly' : '' )
            . '; SameSite=' . $samesite;

        header( $cookie_header, false );
    }
}
