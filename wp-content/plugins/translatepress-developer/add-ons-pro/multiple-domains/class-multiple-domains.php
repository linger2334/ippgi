<?php


// Exit if accessed directly
if ( !defined('ABSPATH' ) )
    exit();

class TRP_Multiple_Domains{

    protected $loader;
    protected $settings;
    protected $trp_languages;
    protected $sso;

    public function __construct() {

        define( 'TRP_MD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
        define( 'TRP_MD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

        // Check if TranslatePress free version is too old
        if( defined( 'TRP_PLUGIN_VERSION' ) && version_compare( '3.0.3', TRP_PLUGIN_VERSION, '>' ) ) {
            add_action( 'admin_init', array( $this, 'add_version_incompatibility_notification' ) );
            return; // Stop execution of constructor
        }

        $trp = TRP_Translate_Press::get_trp_instance();
        $this->loader = $trp->get_component( 'loader' );
        $trp_settings = $trp->get_component( 'settings' );
        $this->settings = $trp_settings->get_settings();
        $this->trp_languages = $trp->get_component( 'languages' );

        // Initialize SSO for cross-domain authentication
        require_once TRP_MD_PLUGIN_DIR . 'class-trp-language-domains-sso.php';
        $this->sso = new TRP_Language_Domains_SSO( $this->settings );
        $this->sso->init_hooks();

        // Add hooks and filters here when implementing functionality
        add_action( 'trp_language_selector_extend_table_row_middle', array( $this, 'add_domain_toggle' ), 10, 4 );
        add_action( 'trp_language_selector_extend_table_row_end', array( $this, 'add_domain_fields' ), 10, 4 );
        add_action( 'trp_language_selector_extend_table_heading', array( $this, 'add_table_headings' ), 10, 2 );
        add_action( 'trp_extend_settings', array( $this, 'add_multiple_domains_info_box' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_filter( 'trp_extra_sanitize_settings', array( $this, 'handle_settings_save' ) );
        add_action( 'admin_notices', array( $this, 'display_domain_required_notice' ) );
        add_action( 'admin_notices', array( $this, 'display_duplicate_domain_notice' ) );
        add_action( 'admin_notices', array( $this, 'display_main_domain_notice' ) );

        // Disable "Use a subdirectory for the default language" when Multiple Domains is active
        add_filter( 'trp_subdirectory_for_default_language_disabled', array( $this, 'disable_subdirectory_for_default_language' ) );
        add_filter( 'trp_subdirectory_for_default_language_note', array( $this, 'subdirectory_for_default_language_note' ) );
        add_action( 'init', array( $this, 'force_disable_subdirectory_setting' ) );
        add_action( 'wp_ajax_trp_check_domain_dns', array( $this, 'ajax_check_domain_dns' ) );

        // Set language from domain VERY early to ensure translate_page() and SEO Pack slug translation works
        // Use plugins_loaded priority 2 to run BEFORE SEO Pack's translate_request_uri (priority 3)
        // and BEFORE init (where start_output_buffer runs at priority 0)
        add_action( 'plugins_loaded', array( $this, 'set_language_from_current_domain' ), 2 );

        // Redirect language slug URLs to mapped domains (runs after language detection)
        add_action( 'plugins_loaded', array( $this, 'redirect_language_slug_to_mapped_domain' ), 3 );

        // Redirect admin on secondary domains to main domain (admin is not supported on secondary domains)
        // We need to run this early enough to catch the request before WordPress auth check
        add_action( 'plugins_loaded', array( $this, 'redirect_admin_to_main_domain' ), 4 );

        // Core WordPress URL filters - filter at priority 999 to run AFTER TranslatePress (priority 1)
        add_filter( 'home_url', array( $this, 'filter_wordpress_home_url' ), 999, 4 );
        add_filter( 'site_url', array( $this, 'filter_site_url' ), 999, 4 );
        add_filter( 'login_url', array( $this, 'filter_login_url' ), 10, 3 );

        // Asset URL filters - ensure scripts, styles, and content use the correct domain
        add_filter( 'content_url', array( $this, 'filter_content_url' ), 1, 2 );
        add_filter( 'plugins_url', array( $this, 'filter_plugins_url' ), 1, 3 );
        add_filter( 'includes_url', array( $this, 'filter_includes_url' ), 1, 2 );
        add_filter( 'template_directory_uri', array( $this, 'filter_template_directory_uri' ), 1, 3 );
        add_filter( 'stylesheet_directory_uri', array( $this, 'filter_stylesheet_directory_uri' ), 1, 3 );
        add_filter( 'script_loader_src', array( $this, 'filter_asset_url' ), 10, 2 );
        add_filter( 'style_loader_src', array( $this, 'filter_asset_url' ), 10, 2 );
        add_filter( 'wp_get_attachment_url', array( $this, 'filter_attachment_url' ), 1, 2 );
        add_filter( 'upload_dir', array( $this, 'filter_upload_dir' ), 1, 1 );
        add_filter( 'wp_calculate_image_srcset', array( $this, 'filter_image_srcset' ), 1, 5 );

        // TranslatePress-specific filters
        add_filter( 'trp_home_url', array( $this, 'filter_home_url_for_language_domains' ), 20, 5 );
        add_filter( 'trp_filter_absolute_home_result', array( $this, 'filter_absolute_home_for_language_domains' ) );
        add_filter( 'trp_get_lang_from_url_string', array( $this, 'get_language_from_domain' ), 1, 2 );
        add_filter( 'trp_needed_language', array( $this, 'determine_language_from_domain' ), 10, 4 );

        add_filter( 'trp_get_url_for_language', array( $this, 'filter_get_url_for_language_domains' ), 20, 6 ); // runs on 20 to not conflict with get_slug_translated_url_for_language() that runs on 10.

        add_filter( 'trp_curpageurl', array( $this, 'filter_current_page_url' ), 1, 1 );
        add_filter( 'trp_custom_ajax_url', array( $this, 'filter_ajax_url' ), 10, 1 );
        add_filter( 'trp_wp_ajax_url', array( $this, 'filter_ajax_url' ), 10, 1 );

        // Automatic Language Detection addon - ensure AJAX URL uses current domain to avoid CORS issues
        add_filter( 'trp_ald_ajax_url', array( $this, 'filter_ajax_url' ), 10, 1 );

        // SEO Pack compatibility - slug translation with multiple domains
        add_filter( 'trp_translate_slugs_on_internal_links', array( $this, 'filter_slugs_on_internal_links' ), 20, 2 );

        // SEO Pack compatibility - sitemap URL language detection for multiple domains
        add_filter( 'trp_sitemap_url_original_language', array( $this, 'filter_sitemap_url_original_language' ), 10, 3 );

        // Sitemap domain-filtering: only include URLs belonging to the current domain's language(s)
        if ( $this->has_active_domain_mappings() ) {
            // Yoast / RankMath / SEOPress: post-process the final XML output.
            // We let sitemap_add_language_urls() run unfiltered so $alternate (hreflang)
            // is built for ALL languages, then strip <url> blocks whose <loc> is on
            // a different host. This preserves complete cross-domain hreflang per Google spec.
            add_filter( 'trp_xml_sitemap_output_for_url', array( $this, 'filter_sitemap_xml_by_domain' ), 10, 6 );

            // AIOSEO: array-based, no hreflang support — use per-language filter + post-filter
            add_filter( 'trp_add_language_url_to_sitemap', array( $this, 'filter_sitemap_language_for_domain_aioseo' ), 10, 4 );
            add_filter( 'aioseo_sitemap_posts', array( $this, 'filter_aioseo_sitemap_entries_by_domain' ), 2, 1 );
            add_filter( 'aioseo_sitemap_terms', array( $this, 'filter_aioseo_sitemap_entries_by_domain' ), 2, 1 );
            add_filter( 'aiosp_sitemap_data', array( $this, 'filter_aiosp_sitemap_data_by_domain' ), 2, 4 );

            // Disable sitemap caching: Yoast/RankMath cache without varying by hostname
            add_filter( 'wpseo_enable_xml_sitemap_transient_caching', array( $this, 'disable_sitemap_cache_for_ddp' ) );
            add_filter( 'rank_math/sitemap/enable_caching', array( $this, 'disable_sitemap_cache_for_ddp' ) );
        }

        // Force custom links - recognize all configured domains as internal
        add_filter( 'trp_is_external_link', array( $this, 'filter_is_external_link' ), 10, 3 );

        // WooCommerce compatibility
        add_filter( 'pre_transient_woocommerce_blocks_asset_api_script_data', array( $this, 'filter_woocommerce_blocks_asset_cache' ) );
        add_filter( 'pre_transient_woocommerce_blocks_asset_api_script_data_ssl', array( $this, 'filter_woocommerce_blocks_asset_cache' ) );

        // WordPress core/file block compatibility - replace URLs with current domain to avoid CORS issues
        add_filter( 'render_block', array( $this, 'filter_file_block_urls' ), 10, 2 );

        // Fix CORS issues in inline styles (e.g. Divi's cached @font-face rules that may reference a different domain)
        add_action( 'wp_print_styles', array( $this, 'filter_inline_style_domains' ), 999 );

        // Machine translation referer - ensure license validation uses the main domain
        add_filter( 'trp_machine_translator_referer', array( $this, 'filter_machine_translator_referer' ) );

    }

    /**
     * Set the global $TRP_LANGUAGE based on the current domain
     *
     * This runs very early (init priority -1) to ensure that
     * translate_page() and other TranslatePress functions work correctly
     * with domain-based language detection.
     */
    public function set_language_from_current_domain() {
        // Don't run in admin
        if ( is_admin() ) {
            return;
        }

        global $TRP_LANGUAGE;

        $current_host = $this->get_current_host();
        $language_code = $this->get_language_for_domain( $current_host );

        if ( $language_code !== null ) {
            $TRP_LANGUAGE = $language_code;

            // Clear the get_abs_home and cur_page_url caches so that when SEO Pack calls cur_page_url(),
            // it gets the correct domain-mapped URL instead of a cached main domain URL
            wp_cache_delete( 'get_abs_home', 'trp' );
            wp_cache_delete( 'cur_page_url_translated_slugs', 'trp' );
            wp_cache_delete( 'cur_page_url_untranslated_slugs', 'trp' );
        }
    }

    /**
     * Redirect URLs with language slugs to their mapped domains
     *
     * If a user accesses a URL like https://translatepress.ddev.site/ro/page
     * and Romanian has a domain mapped (e.g., https://ro.translatepress.ddev.site),
     * redirect to https://ro.translatepress.ddev.site/page with a 301 redirect.
     *
     * This runs at plugins_loaded priority 3 (after set_language_from_current_domain).
     */
    public function redirect_language_slug_to_mapped_domain() {
        // Don't redirect in admin, AJAX requests, or CLI
        if ( is_admin() || wp_doing_ajax() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
            return;
        }

        // Don't redirect POST requests (forms, etc.)
        if ( isset( $_SERVER['REQUEST_METHOD'] ) && $_SERVER['REQUEST_METHOD'] === 'POST' ) {
            return;
        }

        // Get the current request URI
        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
        if ( empty( $request_uri ) ) {
            return;
        }

        // Parse the path from the request URI
        $parsed_uri = parse_url( $request_uri );
        $path = isset( $parsed_uri['path'] ) ? $parsed_uri['path'] : '';

        if ( empty( $path ) || $path === '/' ) {
            return;
        }

        // Extract the first path segment
        $path_segments = explode( '/', trim( $path, '/' ) );
        if ( empty( $path_segments[0] ) ) {
            return;
        }

        $first_segment = $path_segments[0];

        // Check if the first segment is a valid language slug
        if ( ! $this->is_valid_language_slug( $first_segment ) ) {
            return;
        }

        // Get the language code for this slug
        $url_slugs = isset( $this->settings['url-slugs'] ) ? $this->settings['url-slugs'] : array();
        $language_code = array_search( $first_segment, $url_slugs, true );

        if ( $language_code === false ) {
            return;
        }

        // Check if this language has a domain mapped
        $mapped_domain = $this->get_language_domain( $language_code );
        if ( $mapped_domain === false ) {
            return;
        }

        // Check if we're already on the mapped domain (avoid redirect loop)
        $current_host = $this->get_current_host();
        $parsed_mapped = parse_url( $mapped_domain );
        $mapped_host = isset( $parsed_mapped['host'] ) ? $parsed_mapped['host'] : '';

        if ( strcasecmp( $current_host, $mapped_host ) === 0 ) {
            // Already on the correct domain, no redirect needed
            return;
        }

        // Build the redirect URL
        // Remove the language slug from the path, preserving the original trailing slash
        // convention to avoid a double redirect when WordPress's redirect_canonical()
        // enforces the site's permalink structure.
        $had_trailing_slash = substr( $path, -1 ) === '/';
        array_shift( $path_segments );
        $remaining_path = '/' . implode( '/', $path_segments );
        if ( $had_trailing_slash ) {
            $remaining_path = trailingslashit( $remaining_path );
        }

        // Preserve query string if present
        $query_string = isset( $parsed_uri['query'] ) ? '?' . $parsed_uri['query'] : '';

        // Build the final redirect URL
        $redirect_url = rtrim( $mapped_domain, '/' ) . $remaining_path . $query_string;

        // Perform 301 redirect
        wp_redirect( $redirect_url, 301 );
        exit;
    }

    /**
     * Redirect admin requests on secondary domains to the main domain
     *
     * The WordPress admin is not supported on secondary (language-mapped) domains.
     * This ensures that when a user accesses wp-admin or wp-login.php on a secondary domain,
     * they are redirected to the main domain's equivalent page.
     *
     * This runs on plugins_loaded (priority 4) to catch requests before WordPress auth checks.
     */
    public function redirect_admin_to_main_domain() {
        // Don't redirect AJAX requests
        if ( wp_doing_ajax() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
            return;
        }

        // Don't redirect CLI requests
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            return;
        }

        // Don't redirect if this is a POST request (could break form submissions during redirect)
        if ( isset( $_SERVER['REQUEST_METHOD'] ) && $_SERVER['REQUEST_METHOD'] === 'POST' ) {
            return;
        }

        // Check if this is an admin or login request by examining the URL path only.
        // Use anchored regex on the path component to avoid false positives from slugs
        // like /wp-admin-tips/ or query strings like ?s=wp-admin.
        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
        $parsed_request = parse_url( $request_uri );
        $request_path   = isset( $parsed_request['path'] ) ? $parsed_request['path'] : '';
        $is_admin_request = (
            preg_match( '#/wp-admin(/|$)#', $request_path ) === 1 ||
            basename( $request_path ) === 'wp-login.php'
        );

        if ( ! $is_admin_request ) {
            return;
        }

        // Get the current request domain
        $current_host = $this->get_current_host();
        $language_for_current_domain = $this->get_language_for_domain( $current_host );

        // If we're not on a mapped domain, no redirect needed
        if ( $language_for_current_domain === null ) {
            return;
        }

        // We're on a secondary domain in admin - redirect to main domain
        $main_domain = get_option( 'home' );

        // Parse the main domain to get the base
        $parsed_main = parse_url( $main_domain );
        $redirect_url = $parsed_main['scheme'] . '://' . $parsed_main['host'];

        // Add port if present and non-standard
        if ( isset( $parsed_main['port'] ) && ! in_array( $parsed_main['port'], array( 80, 443 ), true ) ) {
            $redirect_url .= ':' . $parsed_main['port'];
        }

        // Safety net: if the main domain's host matches the current host, skip redirect to avoid loop
        $main_host = strtolower( $parsed_main['host'] );
        if ( strcasecmp( $main_host, $current_host ) === 0 ) {
            return; // Same domain — redirect would loop
        }

        // Append the request URI
        $redirect_url .= $request_uri;

        // Perform 302 redirect (temporary, as this is a behavioral redirect not a permanent URL change)
        wp_redirect( $redirect_url, 302 );
        exit;
    }

    /**
     * Add Multiple Domains information box
     */
    public function add_multiple_domains_info_box( $settings ) {
        ?>
        <div class="trp-settings-container">
            <div class="trp-multiple-domains-header">
                <h3 class="trp-settings-primary-heading"><span class="dashicons dashicons-admin-site-alt"></span><?php esc_html_e( 'Different Domain per Language', 'translatepress-multilingual' ); ?></h3>
                <a href="<?php echo esc_url( 'https://translatepress.com/docs/developers/different-domain-per-language/?utm_source=tp-website-languages&utm_medium=client-site&utm_campaign=different-domain' ); ?>"
                   class="trp-multiple-domains-docs-link trp-button-secondary" target="_blank">
                    <?php esc_html_e( 'View Docs', 'translatepress-multilingual' ); ?>
                    <span class="dashicons dashicons-external"></span>
                </a>
            </div>
            <div class="trp-settings-separator"></div>
            <div>
                <p class="trp-description-text">
                    <?php esc_html_e( 'Assign different domains or subdomains to each language. When visitors access these domains, TranslatePress loads the appropriate language translation directly without redirecting.', 'translatepress-multilingual' ); ?>
                </p>
                <p class="trp-description-text">
                    <?php
                    printf(
                        /* translators: %1$s, %2$s, %3$s are example domains */
                        esc_html__( 'Example: %1$s for English, %2$s for Spanish, %3$s for French.', 'translatepress-multilingual' ),
                        '<strong>mywebsite.com</strong>',
                        '<strong>mywebsite.es</strong>',
                        '<strong>mywebsite.fr</strong>'
                    );
                    ?>
                </p>
                <p class="trp-description-text">
                    <strong><?php esc_html_e( 'Before enabling:', 'translatepress-multilingual' ); ?></strong>
                    <?php esc_html_e( 'Ensure your domains are registered, pointed to your server, and have SSL certificates configured.', 'translatepress-multilingual' ); ?>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Add table heading for the Map to domain toggle
     */
    public function add_table_headings( $settings, $show_formality ) {
        ?>
        <div class="trp-language-field trp-field-multiple-domains-toggle">
            <div class="trp-languages-table-heading-item trp-primary-text-bold"><?php esc_html_e( 'Domain', 'translatepress-multilingual' ); ?>
                <div class="trp-settings-info-sign" data-tooltip="<?php echo wp_kses( __( 'Map this language to a different domain or sub-domain.', 'translatepress-multilingual' ), array() ); ?> "></div>
            </div>
        </div>
        <?php
    }

    /**
     * Add domain toggle after the Slug field
     */
    public function add_domain_toggle( $selected_language_code, $default_language, $settings, $show_formality ) {
        // Get saved domain mapping settings
        $domain_mappings = isset( $settings['trp-multiple-domains'] ) ? $settings['trp-multiple-domains'] : array();
        $enabled = isset( $domain_mappings[$selected_language_code]['enabled'] ) ? $domain_mappings[$selected_language_code]['enabled'] : false;

        // Check if this is the default language
        $is_default = ( $selected_language_code == $default_language );

        ?>
        <div class="trp-language-field trp-field-multiple-domains-toggle">
            <label class="trp-language-field-label"><?php esc_html_e( 'Domain', 'translatepress-multilingual' ); ?></label>
            <div class="trp-switch">
                <input
                    type="checkbox"
                    name="trp_settings[trp-multiple-domains][<?php echo esc_attr( $selected_language_code ); ?>][enabled]"
                    value="1"
                    class="trp-switch-input trp-multiple-domains-enable-toggle"
                    id="trp-multiple-domains-toggle-<?php echo esc_attr( $selected_language_code ); ?>"
                    data-language="<?php echo esc_attr( $selected_language_code ); ?>"
                    <?php checked( $enabled, true ); ?>
                    <?php disabled( $is_default, true ); ?>
                />
                <label class="trp-switch-label" for="trp-multiple-domains-toggle-<?php echo esc_attr( $selected_language_code ); ?>"></label>
            </div>
        </div>
        <?php
    }

    /**
     * Add domain fields inline below the language row
     */
    public function add_domain_fields( $selected_language_code, $default_language, $settings, $show_formality ) {
        // Get saved domain mapping settings
        $domain_mappings = isset( $settings['trp-multiple-domains'] ) ? $settings['trp-multiple-domains'] : array();
        //$enabled = isset( $domain_mappings[$selected_language_code]['enabled'] ) ? $domain_mappings[$selected_language_code]['enabled'] : false;
        $domain = isset( $domain_mappings[$selected_language_code]['domain'] ) ? $domain_mappings[$selected_language_code]['domain'] : '';

        ?>
        <div class="trp-language-field trp-multiple-domains-inline-section">
            <div class="trp-multiple-domains-input-group">
                <input
                    type="text"
                    name="trp_settings[trp-multiple-domains][<?php echo esc_attr( $selected_language_code ); ?>][domain]"
                    value="<?php echo esc_attr( $domain ); ?>"
                    class="trp-multiple-domains-input"
                    placeholder="<?php esc_attr_e( 'https://example.com', 'translatepress-multilingual' ); ?>"
                    data-language="<?php echo esc_attr( $selected_language_code ); ?>"
                />
                <button
                    type="button"
                    class="trp-multiple-domains-use-current dashicons dashicons-admin-site-alt"
                    data-language="<?php echo esc_attr( $selected_language_code ); ?>"
                    title="<?php esc_attr_e( 'Prefill with current domain', 'translatepress-multilingual' ); ?>"
                ></button>
                <button
                        type="button"
                        class="trp-multiple-domains-check-dns  trp-button-secondary"
                        data-language="<?php echo esc_attr( $selected_language_code ); ?>"
                >
                    <span class="dashicons dashicons-image-rotate"></span>
                    <?php esc_html_e( 'Check DNS', 'translatepress-multilingual' ); ?>
                </button>
            </div>
            <div class="trp-multiple-domains-notification" style="display:none;" data-language="<?php echo esc_attr( $selected_language_code ); ?>">
                <span class="trp-multiple-domains-notification-message"></span>
            </div>
        </div>
        <?php
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets( $hook ) {
        // Only load on TranslatePress settings page
        if( !in_array( $hook, [ 'settings_page_translate-press'] ) ){
            return;
        }

        wp_enqueue_style(
            'trp-multiple-domains-admin',
            TRP_MD_PLUGIN_URL . 'assets/css/trp-multiple-domains-admin.css',
            array(),
            TRP_PLUGIN_VERSION
        );

        wp_enqueue_script(
            'trp-multiple-domains-admin',
            TRP_MD_PLUGIN_URL . 'assets/js/trp-multiple-domains-admin.js',
            array( 'jquery' ),
            TRP_PLUGIN_VERSION,
            true
        );

        // Get current domain with protocol from home_url
        $home_url = home_url();
        $parsed_url = parse_url( $home_url );

        // Build full domain with protocol (e.g., 'https://example.com')
        $current_domain = '';
        if ( isset( $parsed_url['scheme'] ) && isset( $parsed_url['host'] ) ) {
            $current_domain = $parsed_url['scheme'] . '://' . $parsed_url['host'];

            // Add non-standard port if present
            if ( isset( $parsed_url['port'] ) && ! in_array( $parsed_url['port'], array( 80, 443 ), true ) ) {
                $current_domain .= ':' . $parsed_url['port'];
            }
        }

        // Build domain mappings for JS validation (only non-empty domains)
        $domain_mappings = array();
        if ( ! empty( $this->settings['trp-multiple-domains'] ) && is_array( $this->settings['trp-multiple-domains'] ) ) {
            foreach ( $this->settings['trp-multiple-domains'] as $lang_code => $mapping ) {
                if ( ! empty( $mapping['domain'] ) ) {
                    $domain_mappings[ $lang_code ] = strtolower( $mapping['domain'] );
                }
            }
        }

        wp_localize_script( 'trp-multiple-domains-admin', 'trpMultipleDomainsData', array(
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'trp-multiple-domains-nonce' ),
            'currentDomain' => $current_domain,
            'domainMappings' => $domain_mappings,
            'strings' => array(
                'checking' => __( 'Checking DNS...', 'translatepress-multilingual' ),
                'success' => __( 'DNS is correctly configured!', 'translatepress-multilingual' ),
                'error' => __( 'DNS check failed. Please verify your domain configuration.', 'translatepress-multilingual' ),
                'duplicateDomain' => __( 'This domain is already assigned to another language.', 'translatepress-multilingual' ),
            ),
        ) );
    }

    /**
     * Add notification when TranslatePress version is incompatible
     */
    public function add_version_incompatibility_notification() {
        $notifications = TRP_Plugin_Notifications::get_instance();

        $notification_id = 'trp_multiple_domains_version_incompatible';
        $required_version = '3.0.3';
        $current_version = defined( 'TRP_PLUGIN_VERSION' ) ? TRP_PLUGIN_VERSION : __( 'unknown', 'translatepress-multilingual' );

        $message = '<p style="padding-right:30px;">';
        $message .= sprintf(
            __( '<strong>Different Domain per Language add-on</strong> requires TranslatePress version %1$s or higher. You are currently using version %2$s. Please update TranslatePress to enable this feature.', 'translatepress-multilingual' ),
            '<strong>' . esc_html( $required_version ) . '</strong>',
            '<strong>' . esc_html( $current_version ) . '</strong>'
        );
        $message .= '</p>';

        // Add dismissible link only outside plugin pages
        if ( ! $notifications->is_plugin_page() ) {
            $message .= '<a style="text-decoration: none;z-index:100;" href="' . add_query_arg( array( 'trp_dismiss_admin_notification' => $notification_id ) ) . '" type="button" class="notice-dismiss"><span class="screen-reader-text">' . esc_html__( 'Dismiss this notice.', 'translatepress-multilingual' ) . '</span></a>';
            $force_show = false;
        } else {
            $force_show = true; // Force show on plugin pages (non-dismissible)
        }

        $notifications->add_notification(
            $notification_id,
            $message,
            'trp-notice notice error',
            true,
            array( 'translate-press' ),
            true, // Show in all backend
            $force_show
        );
    }

    /**
     * Handle settings save
     *
     * Normalizes domain entries to ensure they always include a protocol.
     * Defaults to https:// if no protocol is provided.
     * All domains are stored with protocol (e.g., 'https://example.com')
     *
     * @param array $settings The settings being saved
     * @return array The settings (passed through for filter chain)
     */
    public function handle_settings_save( $settings ) {
        // Validate and normalize domain mappings if they exist
        if ( ! empty( $settings['trp-multiple-domains'] ) && is_array( $settings['trp-multiple-domains'] ) ) {
            $has_validation_error   = false;
            $has_duplicate_error    = false;
            $has_main_domain_error  = false;
            $default_language       = isset( $settings['default-language'] ) ? $settings['default-language'] : '';
            $normalized_domains     = array(); // Track normalized domains to detect duplicates

            foreach ( $settings['trp-multiple-domains'] as $language_code => $mapping ) {
                $is_enabled = ! empty( $mapping['enabled'] );
                $domain = isset( $mapping['domain'] ) ? trim( $mapping['domain'] ) : '';

                // Clear domain for default language (shouldn't have one)
                if ( $language_code === $default_language ) {
                    $settings['trp-multiple-domains'][ $language_code ]['domain'] = '';
                    $settings['trp-multiple-domains'][ $language_code ]['enabled'] = false;
                    continue;
                }

                // Validate: if enabled, domain is required
                if ( $is_enabled && empty( $domain ) ) {
                    // Disable the toggle since domain is empty
                    $settings['trp-multiple-domains'][ $language_code ]['enabled'] = false;
                    $has_validation_error = true;
                }

                // Normalize the domain to ensure it has a protocol
                if ( ! empty( $domain ) ) {
                    $normalized = $this->normalize_domain_with_protocol( $domain );
                    $normalized_lower = strtolower( $normalized );

                    // Check if domain matches the main site URL
                    $main_site_url = get_option( 'home' );
                    $parsed_main   = parse_url( $main_site_url );
                    $main_host     = isset( $parsed_main['host'] ) ? strtolower( $parsed_main['host'] ) : '';
                    $parsed_norm   = parse_url( $normalized );
                    $norm_host     = isset( $parsed_norm['host'] ) ? strtolower( $parsed_norm['host'] ) : '';

                    if ( $main_host !== '' && $norm_host === $main_host ) {
                        $settings['trp-multiple-domains'][ $language_code ]['enabled'] = false;
                        $has_main_domain_error = true;
                    }

                    // Check for duplicate domains
                    if ( isset( $normalized_domains[ $normalized_lower ] ) ) {
                        // Duplicate found - disable this one
                        $settings['trp-multiple-domains'][ $language_code ]['enabled'] = false;
                        $has_duplicate_error = true;
                    } else {
                        $normalized_domains[ $normalized_lower ] = $language_code;
                    }

                    $settings['trp-multiple-domains'][ $language_code ]['domain'] = $normalized;
                }
            }

            // Show validation error notice
            if ( $has_validation_error ) {
                set_transient( 'trp_md_domain_required_error', true, 30 );
            }

            // Show duplicate domain error notice
            if ( $has_duplicate_error ) {
                set_transient( 'trp_md_duplicate_domain_error', true, 30 );
            }

            // Show main domain error notice
            if ( $has_main_domain_error ) {
                set_transient( 'trp_md_main_domain_error', true, 30 );
            }

            // Trigger SSO so the admin is authenticated on all enabled domains
            if ( $this->sso ) {
                $this->sso->trigger_sso( $settings );
            }
        }

        return $settings;
    }

    /**
     * Disable the "Use a subdirectory for the default language" checkbox when Multiple Domains addon is active
     *
     * @param bool $disabled Current disabled state
     * @return bool True to disable the checkbox
     */
    public function disable_subdirectory_for_default_language( $disabled ) {
        return true;
    }

    /**
     * Add a note to the "Use a subdirectory for the default language" checkbox when Multiple Domains addon is active
     *
     * @param string $note Current note
     * @return string Note message
     */
    public function subdirectory_for_default_language_note( $note ) {
        return __( 'Note: This option is disabled when Different Domain for Language addon is active.', 'translatepress-multilingual' );
    }

    /**
     * Force the "add-subdirectory-to-default-language" setting to 'no' in the database
     * This ensures the setting is always disabled when Multiple Domains addon is active
     * Called on init hook. update_option only writes if value changed, so reading is cached.
     */
    public function force_disable_subdirectory_setting() {
        $settings = get_option( 'trp_settings', array() );

        if ( is_array( $settings ) ) {
            $settings['add-subdirectory-to-default-language'] = 'no';
            update_option( 'trp_settings', $settings );
        }
    }

    /**
     * Display validation error notice when domain is missing
     */
    public function display_domain_required_notice() {
        if ( get_transient( 'trp_md_domain_required_error' ) ) {
            delete_transient( 'trp_md_domain_required_error' );
            ?>
            <div class="notice notice-error is-dismissible">
                <p><?php esc_html_e( 'Different Domain per Language: Domain is required when domain mapping is enabled. The toggle has been disabled for languages with empty domains.', 'translatepress-multilingual' ); ?></p>
            </div>
            <?php
        }
    }

    /**
     * Display validation error notice when duplicate domains are found
     */
    public function display_duplicate_domain_notice() {
        if ( get_transient( 'trp_md_duplicate_domain_error' ) ) {
            delete_transient( 'trp_md_duplicate_domain_error' );
            ?>
            <div class="notice notice-error is-dismissible">
                <p><?php esc_html_e( 'Different Domain per Language: The same domain cannot be assigned to multiple languages. The toggle has been disabled for duplicate domains.', 'translatepress-multilingual' ); ?></p>
            </div>
            <?php
        }
    }

    /**
     * Display validation error notice when a domain matches the main site URL
     */
    public function display_main_domain_notice() {
        if ( get_transient( 'trp_md_main_domain_error' ) ) {
            delete_transient( 'trp_md_main_domain_error' );
            ?>
            <div class="notice notice-error is-dismissible">
                <p><?php esc_html_e( 'Different Domain per Language: A language domain cannot be the same as the main site URL. The toggle has been disabled for the matching domain.', 'translatepress-multilingual' ); ?></p>
            </div>
            <?php
        }
    }

    /**
     * AJAX handler to check if a domain is reachable
     */
    public function ajax_check_domain_dns() {
        check_ajax_referer( 'trp-multiple-domains-nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'translatepress-multilingual' ) ) );
        }

        $domain = isset( $_POST['domain'] ) ? sanitize_text_field( wp_unslash( $_POST['domain'] ) ) : '';

        if ( empty( $domain ) ) {
            wp_send_json_error( array( 'message' => __( 'Please enter a domain.', 'translatepress-multilingual' ) ) );
        }

        // Normalize domain to ensure it has a protocol
        $url = $this->normalize_domain_with_protocol( $domain );

        if ( empty( $url ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid domain format.', 'translatepress-multilingual' ) ) );
        }

        // Make HTTP request to check if domain is reachable
        $response = wp_remote_get( $url, array(
            'timeout'     => 10,
            'sslverify'   => false,
            'redirection' => 5,
        ) );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( array(
                'message' => sprintf(
                    /* translators: %s is the error message */
                    __( 'Could not reach domain: %s', 'translatepress-multilingual' ),
                    $response->get_error_message()
                )
            ) );
        }

        $status_code = wp_remote_retrieve_response_code( $response );

        if ( $status_code >= 200 && $status_code < 400 ) {
            wp_send_json_success( array(
                'message' => __( 'Domain is reachable!', 'translatepress-multilingual' )
            ) );
        } else {
            wp_send_json_error( array(
                'message' => sprintf(
                    /* translators: %d is the HTTP status code */
                    __( 'Domain returned HTTP status %d.', 'translatepress-multilingual' ),
                    $status_code
                )
            ) );
        }
    }

    /**
     * Normalize a domain to ensure it has a protocol
     *
     * Ensures the domain includes a protocol, defaulting to https:// if none is present.
     * Strips any paths or query parameters, keeping only protocol + hostname + optional port.
     *
     * Examples:
     * - 'example.com' -> 'https://example.com'
     * - 'http://example.com' -> 'http://example.com'
     * - 'https://example.com/path' -> 'https://example.com'
     * - 'example.com:8080' -> 'https://example.com:8080'
     *
     * @param string $domain The domain to normalize
     * @return string The normalized domain with protocol
     */
    private function normalize_domain_with_protocol( $domain ) {
        // Trim whitespace
        $domain = trim( $domain );

        if ( empty( $domain ) ) {
            return '';
        }

        // Check if domain already has a protocol
        $has_protocol = preg_match( '~^https?://~i', $domain );

        // If no protocol, add https:// as default
        if ( ! $has_protocol ) {
            $domain = 'https://' . $domain;
        }

        // Parse the URL to extract components
        $parsed = parse_url( $domain );

        if ( ! isset( $parsed['scheme'] ) || ! isset( $parsed['host'] ) ) {
            // If parsing failed, return empty string (invalid domain)
            return '';
        }

        // Build normalized URL: protocol + host + optional port
        $normalized = strtolower( $parsed['scheme'] ) . '://' . strtolower( $parsed['host'] );

        // Preserve non-standard ports
        if ( isset( $parsed['port'] ) && ! in_array( $parsed['port'], array( 80, 443 ), true ) ) {
            $normalized .= ':' . $parsed['port'];
        }

        return $normalized;
    }


    /**
     * Get the current protocol (http or https)
     *
     * @return string Protocol ('http' or 'https')
     */
    private function get_current_protocol() {
        $proto = 'http';

        if ( !empty($_SERVER['HTTP_X_FORWARDED_PROTO']) ) {
            $proto = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) );
        } elseif ( !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ) {
            $proto = 'https';
        }

        return $proto;
    }

    /**
     * Get the current host from server variables
     *
     * @return string Current host
     */
    private function get_current_host() {
        $host = '';

        if ( !empty($_SERVER['HTTP_X_FORWARDED_HOST']) ) {
            $host = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_HOST'] ) );
        } elseif ( !empty($_SERVER['HTTP_HOST']) ) {
            $host = sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) );
        } elseif ( isset($_SERVER['SERVER_NAME']) ) {
            $host = sanitize_text_field( wp_unslash( $_SERVER['SERVER_NAME'] ) );
        }

        // Strip port if present (e.g. "example.com:8080" → "example.com") so the
        // value matches what parse_url()['host'] returns on the comparison side.
        $host = strtok( $host, ':' );

        return $host;
    }

    /**
     * Get the mapped domain for a specific language
     *
     * Returns the full domain URL including protocol (e.g., 'https://example.com')
     *
     * @param string $language_code The language code to check
     * @return string|false The mapped domain with protocol if enabled, false otherwise
     */
    private function get_language_domain( $language_code ) {
        $domain_mappings = isset( $this->settings['trp-multiple-domains'] ) ? $this->settings['trp-multiple-domains'] : array();

        if ( empty( $domain_mappings[$language_code]['enabled'] ) || empty( $domain_mappings[$language_code]['domain'] ) ) {
            return false;
        }

        return $domain_mappings[$language_code]['domain'];
    }

    /**
     * Get the language code mapped to a specific domain
     *
     * Compares the hostname portion of stored domains (which include protocol)
     * against the provided hostname.
     *
     * Only returns a language if the current user can access it (language is published
     * OR user has translation capability). This mirrors the behavior of
     * TRP_Url_Converter::get_lang_from_url_string() and prevents redirect loops
     * when an inactive language has a domain mapped.
     *
     * @param string $domain The domain hostname to check (e.g., 'example.com')
     * @return string|null The language code if found and accessible, null otherwise
     */
    private function get_language_for_domain( $domain ) {
        $domain_mappings = isset( $this->settings['trp-multiple-domains'] ) ? $this->settings['trp-multiple-domains'] : array();

        foreach ( $domain_mappings as $language_code => $mapping ) {
            if ( empty( $mapping['enabled'] ) || empty( $mapping['domain'] ) ) {
                continue;
            }

            // Extract hostname from stored domain (which includes protocol)
            $parsed_stored = parse_url( $mapping['domain'] );
            $stored_host = isset( $parsed_stored['host'] ) ? $parsed_stored['host'] : $mapping['domain'];

            // Compare hostnames (case-insensitive)
            if ( strcasecmp( $stored_host, $domain ) === 0 ) {
                // Check if the language is accessible to the current user
                // This mirrors the logic in TRP_Url_Converter::get_lang_from_url_string()
                $is_published = isset( $this->settings['publish-languages'] ) && in_array( $language_code, $this->settings['publish-languages'], true );
                $is_translation_language = isset( $this->settings['translation-languages'] ) && in_array( $language_code, $this->settings['translation-languages'], true );
                $user_can_translate = current_user_can( apply_filters( 'trp_translating_capability', 'manage_options' ) );

                if ( $is_published || ( $is_translation_language && $user_can_translate ) ) {
                    return $language_code;
                }

                // Language is mapped but not accessible to current user
                return null;
            }
        }

        return null;
    }

    /**
     * Get the language code from a URL
     *
     * Detects language from:
     * 1. Domain mapping (if URL's domain is mapped to a language)
     * 2. Path slug (if URL contains a language slug like /fr/)
     * 3. Returns null if no language can be determined (caller should use default)
     *
     * @param string $url The URL to analyze
     * @return string|null Language code (e.g., 'fr_FR') or null if not determinable
     */
    private function get_language_for_url( $url ) {
        $parsed = parse_url( $url );

        // Try domain mapping first
        if ( ! empty( $parsed['host'] ) ) {
            $domain_language = $this->get_language_for_domain( $parsed['host'] );
            if ( $domain_language !== null ) {
                return $domain_language;
            }
        }

        // Try path-based language detection
        if ( ! empty( $parsed['path'] ) ) {
            $path_segments = explode( '/', trim( $parsed['path'], '/' ) );
            if ( ! empty( $path_segments[0] ) ) {
                $first_segment = $path_segments[0];

                // Check against configured URL slugs
                $url_slugs = isset( $this->settings['url-slugs'] )
                    ? $this->settings['url-slugs']
                    : array();
                $language_code = array_search( $first_segment, $url_slugs, true );

                if ( $language_code !== false ) {
                    return $language_code;
                }
            }
        }

        // Return null - caller decides whether to use default language
        return null;
    }

    /**
     * Get the URL slug for a specific language
     *
     * Returns the URL slug if the language is accessible to the current user
     * (published OR user has translation capability for unpublished languages).
     *
     * @param string $language_code The language code
     * @return string The URL slug for the language (e.g., 'ro', 'es'), empty if not accessible
     */
    private function get_url_slug_for_language( $language_code ) {
        if ( ! $this->trp_languages ) {
            return '';
        }

        // Check if the language is accessible to the current user
        $published_languages = isset( $this->settings['publish-languages'] ) ? $this->settings['publish-languages'] : array();
        $translation_languages = isset( $this->settings['translation-languages'] ) ? $this->settings['translation-languages'] : array();

        $is_published = in_array( $language_code, $published_languages, true );
        $is_translation_language = in_array( $language_code, $translation_languages, true );
        $user_can_translate = current_user_can( apply_filters( 'trp_translating_capability', 'manage_options' ) );

        if ( ! $is_published && ! ( $is_translation_language && $user_can_translate ) ) {
            return '';
        }

        // The slug is typically stored in url-slugs setting
        if ( isset( $this->settings['url-slugs'][ $language_code ] ) ) {
            return $this->settings['url-slugs'][ $language_code ];
        }

        // Fallback to language code itself
        return $language_code;
    }

    /**
     * Replace the domain in a URL with a new domain, preserving path and query string
     *
     * Handles both cases:
     * 1. $new_domain WITH protocol: 'https://example.com' - uses that protocol
     * 2. $new_domain WITHOUT protocol: 'example.com' - uses $protocol param or current protocol
     *
     * @param string $url        The URL to modify
     * @param string $new_domain The new domain (with or without protocol)
     * @param string $protocol   Optional protocol to use if $new_domain lacks one (defaults to current)
     * @return string The modified URL
     */
    private function replace_url_domain( $url, $new_domain, $protocol = null ) {
        if ( empty( $url ) || empty( $new_domain ) ) {
            return $url;
        }

        $parsed_url = parse_url( $url );

        // URL must have both scheme and host to be modified
        if ( ! isset( $parsed_url['scheme'] ) || ! isset( $parsed_url['host'] ) ) {
            return $url;
        }

        // Check if $new_domain includes a protocol
        $parsed_new_domain = parse_url( $new_domain );

        if ( isset( $parsed_new_domain['scheme'] ) && isset( $parsed_new_domain['host'] ) ) {
            // $new_domain includes protocol (e.g., 'https://example.com')
            $new_protocol = $parsed_new_domain['scheme'];
            $new_host = $parsed_new_domain['host'];
            $new_port = isset( $parsed_new_domain['port'] ) ? $parsed_new_domain['port'] : null;
        } else {
            // $new_domain is just hostname (e.g., 'example.com')
            // Use provided protocol or detect current one
            $new_protocol = $protocol !== null ? $protocol : $this->get_current_protocol();
            $new_host = $new_domain;
            $new_port = null;
        }

        // Build the old base URL
        $old_base = $parsed_url['scheme'] . '://' . $parsed_url['host'];
        if ( isset( $parsed_url['port'] ) && ! in_array( $parsed_url['port'], array( 80, 443 ), true ) ) {
            $old_base .= ':' . $parsed_url['port'];
        }

        // Build the new base URL
        $new_base = $new_protocol . '://' . $new_host;

        // Add port if specified in new domain or preserve from original URL for non-standard ports
        if ( $new_port !== null && ! in_array( $new_port, array( 80, 443 ), true ) ) {
            $new_base .= ':' . $new_port;
        } elseif ( isset( $parsed_url['port'] ) && ! in_array( $parsed_url['port'], array( 80, 443 ), true ) ) {
            // Preserve original non-standard port if new domain doesn't specify one
            $new_base .= ':' . $parsed_url['port'];
        }

        return str_replace( $old_base, $new_base, $url );
    }

    /**
     * Remove language slug from URL path
     *
     * Only removes the first path segment if it's a valid language slug defined in TranslatePress settings.
     *
     * @param string $url The URL to modify
     * @return string The URL with language slug removed if it was valid
     */
    private function remove_language_slug_from_url( $url ) {
        $parsed = parse_url( $url );

        // URL must have scheme, host, and path to be modified
        if ( ! isset( $parsed['scheme'] ) || ! isset( $parsed['host'] ) || ! isset( $parsed['path'] ) ) {
            return $url;
        }

        $path = $parsed['path'];

        // Extract the first path segment
        $path_segments = explode( '/', trim( $path, '/' ) );
        if ( empty( $path_segments[0] ) ) {
            return $url;
        }

        $first_segment = $path_segments[0];

        // Check if the first segment is a valid configured language slug
        if ( ! $this->is_valid_language_slug( $first_segment ) ) {
            // Not a language slug, return URL unchanged
            return $url;
        }

        // It's a valid language slug, remove it
        $slug_with_slashes = '/' . $first_segment . '/';
        $slug_without_trailing = '/' . $first_segment;

        // Check if path starts with the language slug
        if ( strpos( $path, $slug_with_slashes ) === 0 ) {
            // Path starts with /slug/, remove it
            $path = substr( $path, strlen( $slug_with_slashes ) - 1 ); // Keep one leading slash
        } elseif ( $path === $slug_without_trailing || $path === $slug_with_slashes ) {
            // Path IS just the language slug (e.g., /ro or /ro/)
            $path = '/';
        }

        // Rebuild the URL with the cleaned path
        $cleaned_url = $parsed['scheme'] . '://' . $parsed['host'];
        if ( isset( $parsed['port'] ) && ! in_array( $parsed['port'], array( 80, 443 ) ) ) {
            $cleaned_url .= ':' . $parsed['port'];
        }
        $cleaned_url .= $path;
        if ( isset( $parsed['query'] ) ) {
            $cleaned_url .= '?' . $parsed['query'];
        }
        if ( isset( $parsed['fragment'] ) ) {
            $cleaned_url .= '#' . $parsed['fragment'];
        }

        return $cleaned_url;
    }

    /**
     * Check if a slug is a valid configured language slug
     *
     * @param string $slug The slug to validate
     * @return bool True if the slug is configured for a language, false otherwise
     */
    private function is_valid_language_slug( $slug ) {
        if ( empty( $slug ) ) {
            return false;
        }

        // Get all configured URL slugs from settings
        $url_slugs = isset( $this->settings['url-slugs'] ) ? $this->settings['url-slugs'] : array();

        if ( empty( $url_slugs ) ) {
            return false;
        }

        // Check if this slug exists in the configured language slugs
        return in_array( $slug, $url_slugs, true );
    }

    /**
     * Add language slug to URL path
     *
     * Inserts the language slug after the domain, before the existing path.
     * Example: 'https://example.com/page/' + 'de' → 'https://example.com/de/page/'
     *
     * @param string $url           The URL to modify
     * @param string $language_code The language code to get the slug for
     * @return string The URL with language slug added
     */
    private function add_language_slug_to_url( $url, $language_code ) {
        $url_slug = $this->get_url_slug_for_language( $language_code );

        if ( empty( $url_slug ) ) {
            return $url;
        }

        $parsed = parse_url( $url );

        if ( ! isset( $parsed['scheme'] ) || ! isset( $parsed['host'] ) ) {
            return $url;
        }

        // Build base URL
        $base_url = $parsed['scheme'] . '://' . $parsed['host'];
        if ( isset( $parsed['port'] ) && ! in_array( $parsed['port'], array( 80, 443 ), true ) ) {
            $base_url .= ':' . $parsed['port'];
        }

        // Get existing path or default to /
        $path = isset( $parsed['path'] ) ? $parsed['path'] : '/';

        // Ensure path starts with /
        if ( substr( $path, 0, 1 ) !== '/' ) {
            $path = '/' . $path;
        }

        // Insert language slug at the beginning of the path
        $new_path = '/' . $url_slug . $path;

        // Clean up double slashes (but not after protocol)
        $new_path = preg_replace( '#/+#', '/', $new_path );

        // Build final URL
        $final_url = $base_url . $new_path;

        if ( isset( $parsed['query'] ) ) {
            $final_url .= '?' . $parsed['query'];
        }

        if ( isset( $parsed['fragment'] ) ) {
            $final_url .= '#' . $parsed['fragment'];
        }

        return $final_url;
    }

    /**
     * Generic URL filter for domain replacement
     *
     * @param string $url              The URL to filter
     * @param string|null $language    Optional language code (uses $TRP_LANGUAGE if null)
     * @param bool $check_admin        Whether to skip filtering in admin context
     * @return string The filtered URL
     */
    private function apply_domain_filter( $url, $language = null, $check_admin = true ) {
        if ( $check_admin && is_admin() ) {
            return $url;
        }

        if ( $language === null ) {
            global $TRP_LANGUAGE;
            if ( empty( $TRP_LANGUAGE ) ) {
                return $url;
            }
            $language = $TRP_LANGUAGE;
        }

        $mapped_domain = $this->get_language_domain( $language );

        if ( $mapped_domain === false ) {
            return $url;
        }

        return $this->replace_url_domain( $url, $mapped_domain );
    }

    /**
     * Get the language code from the domain in the URL
     *
     * This hooks into TranslatePress's trp_get_lang_from_url_string filter to detect
     * the language based on the domain when multiple domains are configured.
     *
     * @param string|null $lang The language slug detected from URL path (or null)
     * @param string      $url  The URL being analyzed
     * @return string|null The language code or null if not found
     */
    public function get_language_from_domain( $lang, $url ) {

        // Parse the URL to extract the domain
        $parsed_url = parse_url( $url );
        if ( empty( $parsed_url['host'] ) ) {
            return $lang;
        }

        $url_domain = $parsed_url['host'];

        // Get domain mappings from settings
        $domain_mappings = isset( $this->settings['trp-multiple-domains'] ) ? $this->settings['trp-multiple-domains'] : array();

        // Check if this domain matches any configured language domain
        // IMPORTANT: Check domain FIRST, even if language was detected from path
        // This ensures that domain-based detection takes precedence
        foreach ( $domain_mappings as $language_code => $mapping ) {
            if ( empty( $mapping['enabled'] ) || empty( $mapping['domain'] ) ) {
                continue;
            }

            // Extract hostname from stored domain (which includes protocol)
            $parsed_stored = parse_url( $mapping['domain'] );
            $stored_host = isset( $parsed_stored['host'] ) ? $parsed_stored['host'] : $mapping['domain'];

            // Compare hostnames (case-insensitive)
            if ( strcasecmp( $stored_host, $url_domain ) === 0 ) {
                // Found a matching domain, return the URL SLUG not the language code
                // The caller expects a slug (e.g., 'ro') which it will use to look up the code (e.g., 'ro_RO')
                $url_slug = $this->get_url_slug_for_language( $language_code );

                return $url_slug;
            }
        }

        // No domain mapping found, return the language detected from path (or null)
        return $lang;
    }

    /**
     * Determine the needed language based on the current domain
     *
     * This hooks into TranslatePress's trp_needed_language filter to ensure
     * the correct language is set when accessing a domain mapped to a specific language.
     *
     * @param string $needed_language The language that TranslatePress determined is needed
     * @param string|null $lang_from_url The language detected from the URL
     * @param array $settings TranslatePress settings
     * @param object $trp TranslatePress instance
     * @return string The needed language code
     */
    public function determine_language_from_domain( $needed_language, $lang_from_url, $settings, $trp ) {
        $current_host = $this->get_current_host();
        $language_code = $this->get_language_for_domain( $current_host );

        return $language_code !== null ? $language_code : $needed_language;
    }

    /**
     * Filter WordPress's native home_url to use the domain mapped to the current language
     *
     * This hooks into WordPress's home_url filter at priority 999 (after TranslatePress's
     * filter at priority 1) to replace the domain with the mapped domain for the current language.
     * This catches all home_url() calls, including those that build permalinks.
     *
     * @param string $url         The complete home URL including scheme and path
     * @param string $path        Path relative to the home URL
     * @param string $orig_scheme Scheme to give the home URL context
     * @param int    $blog_id     Blog ID, or null for the current blog
     * @return string The filtered URL with domain mapping applied
     */
    public function filter_wordpress_home_url( $url, $path, $orig_scheme, $blog_id ) {
        global $TRP_LANGUAGE;

        // Don't filter in admin
        if ( is_admin() ) {
            return $url;
        }

        // Check what domain the CURRENT REQUEST is on, regardless of $TRP_LANGUAGE
        $current_host = $this->get_current_host();
        $language_for_current_domain = $this->get_language_for_domain( $current_host );

        // If current request is not on a mapped domain, skip filtering
        if ( $language_for_current_domain === null ) {
            return $url;
        }

        // Use the language from the current domain, not $TRP_LANGUAGE
        $mapped_domain = $this->get_language_domain( $language_for_current_domain );

        if ( $mapped_domain === false ) {
            return $url;
        }

        // Replace the domain in the URL
        $url_with_domain = $this->replace_url_domain( $url, $mapped_domain );

        // Remove language slug from the path if it exists
        $url_with_domain = $this->remove_language_slug_from_url( $url_with_domain );

        return $url_with_domain;
    }

    /**
     * Filter home_url to use the domain mapped to the current language
     *
     * This hooks into TranslatePress's trp_home_url filter to replace the base URL
     * with the domain configured for the current language.
     *
     * @param string $new_url      The URL after TranslatePress added language slug
     * @param string $abs_home     The absolute home URL
     * @param string $TRP_LANGUAGE The current language code
     * @param string $path         The path being requested
     * @param string $url          The original URL
     * @return string The filtered URL with domain mapping applied
     */
    public function filter_home_url_for_language_domains( $new_url, $abs_home, $TRP_LANGUAGE, $path, $url ) {
        $mapped_domain = $this->get_language_domain( $TRP_LANGUAGE );

        if ( $mapped_domain === false ) {
            return $new_url;
        }

        return $this->replace_url_domain( $new_url, $mapped_domain );
    }

    /**
     * Filter get_url_for_language to use the domain mapped to the target language
     *
     * This hooks into TranslatePress's trp_get_url_for_language filter which is used
     * when converting URLs in the HTML output buffer (including permalinks).
     *
     * @param string      $new_url             The URL after TranslatePress converted it to the target language
     * @param string      $url                 The original URL
     * @param string      $language            The target language code
     * @param string      $abs_home            The absolute home URL
     * @param string|null $lang_from_url       The language detected from the original URL
     * @param string      $url_slug            The URL slug for the target language
     * @return string The filtered URL with domain mapping applied
     */
    public function filter_get_url_for_language_domains( $new_url, $url, $language, $abs_home, $lang_from_url, $url_slug ) {
        $target_domain = $this->get_language_domain( $language );

        // Detect source language from the original URL
        $source_language = $this->get_language_for_url( $url );
        if ( $source_language === null ) {
            $source_language = $this->settings['default-language'];
        }

        // CASE: Target language has NO domain mapping
        if ( $target_domain === false ) {
            // Check if source URL is on a domain-mapped language
            $parsed_url = parse_url( $url );
            $source_domain_language = null;
            if ( ! empty( $parsed_url['host'] ) ) {
                $source_domain_language = $this->get_language_for_domain( $parsed_url['host'] );
            }

            if ( $source_domain_language !== null ) {
                // Source is on mapped domain, target is not
                // We need to:
                // 1. Normalize domain to main
                // 2. Add target language slug to path (if not default)
                // 3. Translate page slugs from source to target

                $main_domain = get_option( 'home' );
                $result_url = $this->replace_url_domain( $url, $main_domain );

                // Add target language slug to URL path if target is not default language
                if ( $language !== $this->settings['default-language'] ) {
                    $result_url = $this->add_language_slug_to_url( $result_url, $language );
                }

                // Translate page slugs from source to target language
                return $this->filter_slug_translated_url_for_domains( $result_url, $result_url, $source_language, $language );
            }

            // Neither source nor target uses domain mapping
            // Let TranslatePress core handle it entirely
            return $new_url;
        }

        // CASE: Target language HAS domain mapping
        // Replace domain with target domain
        $result_url = $this->replace_url_domain( $url, $target_domain );

        // Remove language slug from path (domain identifies the language)
        $result_url = $this->remove_language_slug_from_url( $result_url );

        // Translate page slugs from source to target language
        return $this->filter_slug_translated_url_for_domains( $result_url, $result_url, $source_language, $language );
    }

    /**
     * Filter absolute home URL to use the domain mapped to the current language
     *
     * This hooks into TranslatePress's trp_filter_absolute_home_result filter
     *
     * @param string $absolute_home The absolute home URL
     * @return string The filtered absolute home URL with domain mapping applied
     */
    public function filter_absolute_home_for_language_domains( $absolute_home ) {
        global $TRP_LANGUAGE;

        if ( empty( $TRP_LANGUAGE ) ) {
            return $absolute_home;
        }

        $mapped_domain = $this->get_language_domain( $TRP_LANGUAGE );

        if ( $mapped_domain === false ) {
            return $absolute_home;
        }

        return $this->replace_url_domain( $absolute_home, $mapped_domain );
    }

    /**
     * Filter site_url to reflect the current domain
     *
     * @param string $url     The complete site URL including scheme and path.
     * @param string $path    Path relative to the site URL.
     * @param string $scheme  Scheme to give the site URL context (e.g. 'http', 'https', 'login', 'admin').
     * @param int    $blog_id Blog ID, or null for the current blog.
     * @return string The filtered site URL
     */
    public function filter_site_url( $url, $path, $scheme, $blog_id ) {
        return $this->apply_domain_filter( $url );
    }

    /**
     * Filter content_url to use the current domain
     *
     * @param string $url  The complete URL to the content directory
     * @param string $path Path relative to the content URL
     * @return string The filtered content URL
     */
    public function filter_content_url( $url, $path ) {
        return $this->apply_domain_filter( $url );
    }

    /**
     * Filter plugins_url to use the current domain
     *
     * @param string $url    The complete URL to the plugins directory
     * @param string $path   Path relative to the plugins URL
     * @param string $plugin The plugin file path
     * @return string The filtered plugins URL
     */
    public function filter_plugins_url( $url, $path, $plugin ) {
        return $this->apply_domain_filter( $url );
    }

    /**
     * Filter includes_url to use the current domain
     *
     * @param string $url  The complete URL to the includes directory
     * @param string $path Path relative to the includes URL
     * @return string The filtered includes URL
     */
    public function filter_includes_url( $url, $path ) {
        return $this->apply_domain_filter( $url );
    }

    /**
     * Filter template_directory_uri to use the current domain
     *
     * This ensures that theme asset URLs (fonts, images, CSS) generated via
     * get_template_directory_uri() use the correct domain. Fixes CORS issues
     * with fonts loaded from inline @font-face rules (e.g. Divi ETmodules font).
     *
     * @param string $template_dir_uri The URI of the current theme's template directory
     * @param string $template         The directory name of the current theme
     * @param string $theme_root_uri   The themes root URI
     * @return string The filtered template directory URI
     */
    public function filter_template_directory_uri( $template_dir_uri, $template, $theme_root_uri ) {
        return $this->apply_domain_filter( $template_dir_uri );
    }

    /**
     * Filter stylesheet_directory_uri to use the current domain
     *
     * This ensures that child theme asset URLs use the correct domain when
     * accessing translated pages on secondary domains.
     *
     * @param string $stylesheet_dir_uri The URI of the current theme's stylesheet directory
     * @param string $stylesheet         The directory name of the current theme's stylesheet
     * @param string $theme_root_uri     The themes root URI
     * @return string The filtered stylesheet directory URI
     */
    public function filter_stylesheet_directory_uri( $stylesheet_dir_uri, $stylesheet, $theme_root_uri ) {
        return $this->apply_domain_filter( $stylesheet_dir_uri );
    }

    /**
     * Filter wp_get_attachment_url to use the current domain
     *
     * This ensures that attachment URLs (images, media files) use the correct
     * domain when accessing translated pages on secondary domains. Fixes CORS
     * issues with product images in WooCommerce and other plugins.
     *
     * @param string $url           The attachment URL
     * @param int    $attachment_id The attachment post ID
     * @return string The filtered attachment URL
     */
    public function filter_attachment_url( $url, $attachment_id ) {
        return $this->apply_domain_filter( $url );
    }

    /**
     * Filter upload directory URLs to use the current domain
     *
     * This ensures that wp_upload_dir() returns URLs with the correct domain
     * when on a secondary (language-mapped) domain. This fixes compatibility
     * with image resizing libraries (like Aqua Resizer) that compare image URLs
     * against the upload base URL.
     *
     * @param array $uploads Array containing upload directory information
     * @return array Modified uploads array with domain-mapped URLs
     */
    public function filter_upload_dir( $uploads ) {
        if ( is_admin() ) {
            return $uploads;
        }

        global $TRP_LANGUAGE;
        if ( empty( $TRP_LANGUAGE ) ) {
            return $uploads;
        }

        $mapped_domain = $this->get_language_domain( $TRP_LANGUAGE );
        if ( $mapped_domain === false ) {
            return $uploads;
        }

        // Filter the URL fields to use the mapped domain
        if ( ! empty( $uploads['url'] ) ) {
            $uploads['url'] = $this->replace_url_domain( $uploads['url'], $mapped_domain );
        }
        if ( ! empty( $uploads['baseurl'] ) ) {
            $uploads['baseurl'] = $this->replace_url_domain( $uploads['baseurl'], $mapped_domain );
        }

        return $uploads;
    }

    /**
     * Filter image srcset URLs to use the current domain
     *
     * This ensures that all image sizes in srcset attributes use the correct
     * domain when accessing translated pages on secondary domains.
     *
     * @param array  $sources       Array of image sources data
     * @param array  $size_array    Array of width and height values
     * @param string $image_src     The 'src' of the image
     * @param array  $image_meta    The image meta data
     * @param int    $attachment_id Image attachment ID
     * @return array The filtered sources array
     */
    public function filter_image_srcset( $sources, $size_array, $image_src, $image_meta, $attachment_id ) {
        if ( is_admin() || empty( $sources ) || ! is_array( $sources ) ) {
            return $sources;
        }

        foreach ( $sources as $width => $source ) {
            if ( ! empty( $source['url'] ) ) {
                $sources[ $width ]['url'] = $this->apply_domain_filter( $source['url'], null, false );
            }
        }

        return $sources;
    }

    /**
     * Filter asset URLs (scripts and styles) to use the current domain
     *
     * @param string $src    The source URL of the enqueued script/style
     * @param string $handle The script/style handle
     * @return string The filtered asset URL
     */
    public function filter_asset_url( $src, $handle ) {
        global $TRP_LANGUAGE;

        if ( is_admin() || empty( $TRP_LANGUAGE ) || empty( $src ) ) {
            return $src;
        }

        $mapped_domain = $this->get_language_domain( $TRP_LANGUAGE );

        if ( $mapped_domain === false ) {
            return $src;
        }

        // Only filter URLs that belong to this site
        $site_url = get_option( 'siteurl' );
        $parsed_site = parse_url( $site_url );
        $parsed_src = parse_url( $src );

        if ( ! isset( $parsed_src['host'] ) || ! isset( $parsed_site['host'] ) ) {
            return $src;
        }

        // Only replace if it's the same domain (or subdomain)
        if ( $parsed_src['host'] === $parsed_site['host'] ||
             strpos( $parsed_src['host'], $parsed_site['host'] ) !== false ) {
            return $this->replace_url_domain( $src, $mapped_domain );
        }

        return $src;
    }

    /**
     * Filter current page URL to use the domain mapped to the current language
     *
     * @param string $url The current page URL
     * @return string The filtered URL
     */
    public function filter_current_page_url( $url ) {
        return $this->apply_domain_filter( $url, null, false );
    }

    /**
     * Filter AJAX URLs to use the domain mapped to the current language
     *
     * @param string $url The AJAX URL
     * @return string The filtered AJAX URL
     */
    public function filter_ajax_url( $url ) {
        return $this->apply_domain_filter( $url, null, false );
    }

    /**
     * Filter login URL to use the domain mapped to the current request's domain
     *
     * This ensures that when accessing wp-admin from a mapped domain (e.g., ro.translatepress.ddev.site),
     * the login redirect URL stays on that domain instead of redirecting to the main domain.
     *
     * @param string $login_url The login URL
     * @param string $redirect  Path to redirect to on login
     * @param bool   $force_reauth Whether to force reauth
     * @return string The filtered login URL
     */
    public function filter_login_url( $login_url, $redirect, $force_reauth ) {
        // Get the current request domain
        $current_host = $this->get_current_host();
        $language_for_current_domain = $this->get_language_for_domain( $current_host );

        // If we're on a mapped domain, replace the login URL domain
        if ( $language_for_current_domain !== null ) {
            $mapped_domain = $this->get_language_domain( $language_for_current_domain );

            if ( $mapped_domain !== false ) {
                $login_url = $this->replace_url_domain( $login_url, $mapped_domain );
            }
        }

        return $login_url;
    }

    /**
     * Filter the sitemap URL's original language based on the URL's domain
     *
     * When accessing a sitemap from a secondary domain (e.g., de.translatepress.ddev.site),
     * Yoast generates URLs with that domain. This filter detects the language from the URL's
     * domain and returns it, ensuring the default language gets its own <url> entry.
     *
     * @param string $original_language The language that the URL is considered to be for
     * @param string $url_loc           The URL being processed
     * @param array  $settings          TranslatePress settings
     * @return string The detected language code, or original if no domain mapping found
     */
    public function filter_sitemap_url_original_language( $original_language, $url_loc, $settings ) {
        // Parse the URL to get the domain
        $parsed_url = parse_url( $url_loc );
        if ( empty( $parsed_url['host'] ) ) {
            return $original_language;
        }

        // Check if this domain is mapped to a language
        $url_domain_language = $this->get_language_for_domain( $parsed_url['host'] );

        if ( $url_domain_language !== null ) {
            // The URL's domain is mapped to a specific language
            // Return that language so it won't get a duplicate <url> entry
            return $url_domain_language;
        }

        return $original_language;
    }

    /**
     * Check if any active domain mappings exist
     *
     * @return bool True if at least one language has an enabled domain mapping
     */
    private function has_active_domain_mappings() {
        $domain_mappings = isset( $this->settings['trp-multiple-domains'] ) ? $this->settings['trp-multiple-domains'] : array();

        foreach ( $domain_mappings as $mapping ) {
            if ( ! empty( $mapping['enabled'] ) && ! empty( $mapping['domain'] ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the list of language codes that should appear in sitemaps for the current domain
     *
     * - Main domain: default language + languages WITHOUT domain mapping (e.g. Spanish with /es/ slug)
     * - Secondary domain (e.g. fr.translatepress.ddev.site): only the language mapped to that domain
     * - Unknown domain: all published languages (safe fallback)
     *
     * @return array Array of language codes allowed in the current domain's sitemap
     */
    private function get_languages_for_current_domain_sitemap() {
        $current_host       = $this->get_current_host();
        $domain_mappings    = isset( $this->settings['trp-multiple-domains'] ) ? $this->settings['trp-multiple-domains'] : array();
        $publish_languages  = isset( $this->settings['publish-languages'] ) ? $this->settings['publish-languages'] : array();
        $default_language   = isset( $this->settings['default-language'] ) ? $this->settings['default-language'] : '';

        // Check if current host matches a secondary (mapped) domain
        // Uses get_language_for_domain() which gates on published/capability status,
        // preventing empty sitemaps when a domain is mapped to an unpublished language.
        $domain_language = $this->get_language_for_domain( $current_host );
        if ( $domain_language !== null ) {
            // We're on a secondary domain with a published language — only this language belongs here
            return array( $domain_language );
        }

        // If the domain IS mapped but the language is unpublished/inaccessible,
        // get_language_for_domain() returns null. Check if we matched a mapping at all
        // to avoid falling through to the main-domain logic for unmapped domains.
        foreach ( $domain_mappings as $language_code => $mapping ) {
            if ( empty( $mapping['enabled'] ) || empty( $mapping['domain'] ) ) {
                continue;
            }
            $parsed      = parse_url( $mapping['domain'] );
            $mapped_host = isset( $parsed['host'] ) ? $parsed['host'] : '';
            if ( strcasecmp( $mapped_host, $current_host ) === 0 ) {
                // Domain is mapped but language is not accessible — return empty to avoid
                // serving another domain's URLs on this host
                return array();
            }
        }

        // Check if current host is the main domain
        $main_site_url  = get_option( 'home' );
        $parsed_main    = parse_url( $main_site_url );
        $main_host      = isset( $parsed_main['host'] ) ? strtolower( $parsed_main['host'] ) : '';

        if ( strcasecmp( $main_host, $current_host ) === 0 ) {
            // Main domain: default language + languages WITHOUT an active domain mapping
            $allowed = array();
            foreach ( $publish_languages as $language ) {
                $has_domain = ! empty( $domain_mappings[ $language ]['enabled'] ) && ! empty( $domain_mappings[ $language ]['domain'] );
                if ( ! $has_domain ) {
                    $allowed[] = $language;
                }
            }
            return $allowed;
        }

        // Unknown domain: return all languages (safe fallback)
        return $publish_languages;
    }

    /**
     * Filter whether a language URL should be added to the sitemap — AIOSEO only
     *
     * For Yoast/RankMath/SEOPress we use filter_sitemap_xml_by_domain() instead,
     * which post-processes the XML to preserve cross-domain hreflang alternates.
     *
     * AIOSEO is array-based with no hreflang support, so per-language filtering is fine.
     * We detect the AIOSEO context by checking the $output parameter: sitemap_add_language_urls()
     * passes the XML string, while aiosp_sitemap_add_language_urls() passes '' (empty string).
     *
     * @param bool   $add_language Whether to add this language (default true)
     * @param string $language     The language code being processed
     * @param array  $url          The URL data array
     * @param string $output       The current sitemap XML output ('' for AIOSEO)
     * @return bool Whether to include this language in the sitemap
     */
    public function filter_sitemap_language_for_domain_aioseo( $add_language, $language, $url, $output ) {
        // Only apply to AIOSEO context (empty $output).
        // Yoast/RankMath/SEOPress pass an XML string as $output — they are handled
        // by filter_sitemap_xml_by_domain() via the trp_xml_sitemap_output_for_url filter.
        if ( $output !== '' ) {
            return $add_language;
        }

        // Cache the allowed languages list per request
        static $allowed_languages = null;

        if ( $allowed_languages === null ) {
            $allowed_languages = $this->get_languages_for_current_domain_sitemap();

            if ( defined( 'TRP_DEBUG_SITEMAP' ) && TRP_DEBUG_SITEMAP ) {
                error_log( 'TRP DDP Sitemap (AIOSEO): Allowed languages for current domain: ' . implode( ', ', $allowed_languages ) );
            }
        }

        if ( ! in_array( $language, $allowed_languages, true ) ) {
            if ( defined( 'TRP_DEBUG_SITEMAP' ) && TRP_DEBUG_SITEMAP ) {
                error_log( 'TRP DDP Sitemap (AIOSEO): Excluding language ' . $language );
            }
            return false;
        }

        return $add_language;
    }

    /**
     * Post-process sitemap XML to remove cross-domain <url> blocks while preserving hreflang
     *
     * Used for Yoast, RankMath, and SEOPress (all go through sitemap_add_language_urls()).
     * By hooking trp_xml_sitemap_output_for_url AFTER the full output is built, we get
     * complete hreflang alternates for all languages in every <url> block. We then strip
     * only the <url> blocks whose <loc> resolves to a different domain.
     *
     * Result: each domain's sitemap contains only its own URLs, but each <url> has
     * <xhtml:link rel="alternate"> hreflang pointing to ALL language domains — per Google spec.
     *
     * Hook: trp_xml_sitemap_output_for_url (priority 10)
     *
     * @param string $new_output     The complete XML output for this URL (all languages)
     * @param string $output         The original SEO plugin output for the base URL
     * @param array  $settings       TranslatePress settings
     * @param string $alternate      The hreflang alternate tags
     * @param string $all_lang_urls  All other-language <url> blocks concatenated
     * @param array  $url            The original URL data array
     * @return string Filtered XML with only same-domain <url> blocks
     */
    public function filter_sitemap_xml_by_domain( $new_output, $output, $settings, $alternate, $all_lang_urls, $url ) {
        $current_host = $this->get_current_host();

        if ( empty( $current_host ) ) {
            return $new_output;
        }

        // Split the output into individual <url>...</url> blocks
        // The output contains multiple <url> blocks concatenated
        if ( preg_match_all( '/<url\b[^>]*>.*?<\/url>/s', $new_output, $matches ) ) {
            $filtered_blocks = array();

            foreach ( $matches[0] as $url_block ) {
                // Extract <loc> from this block
                if ( preg_match( '/<loc>([^<]+)<\/loc>/', $url_block, $loc_match ) ) {
                    $loc_host = parse_url( $loc_match[1], PHP_URL_HOST );

                    if ( is_string( $loc_host ) && strcasecmp( $loc_host, $current_host ) !== 0 ) {
                        // Cross-domain <url> block — skip it
                        if ( defined( 'TRP_DEBUG_SITEMAP' ) && TRP_DEBUG_SITEMAP ) {
                            error_log( 'TRP DDP Sitemap: Stripped cross-domain <url> block: ' . $loc_match[1] );
                        }
                        continue;
                    }
                }

                $filtered_blocks[] = $url_block;
            }

            return implode( "\n", $filtered_blocks );
        }

        return $new_output;
    }

    /**
     * Post-filter AIOSEO sitemap entries to remove cross-domain URLs
     *
     * AIOSEO's aiosp_sitemap_add_language_urls() unconditionally adds the original URL entry
     * before the language loop. When on a secondary domain, this may create a cross-domain entry.
     * This filter runs at priority 2 (after SEO Pack's priority 1) to clean up.
     *
     * Hooks: aioseo_sitemap_posts, aioseo_sitemap_terms
     *
     * @param array $entries Array of sitemap entries with 'loc' keys
     * @return array Filtered entries with only same-domain URLs
     */
    public function filter_aioseo_sitemap_entries_by_domain( $entries ) {
        if ( ! is_array( $entries ) ) {
            return $entries;
        }

        $current_host = $this->get_current_host();
        $filtered     = array();

        foreach ( $entries as $entry ) {
            $loc = '';
            if ( is_array( $entry ) && isset( $entry['loc'] ) ) {
                $loc = $entry['loc'];
            } elseif ( is_object( $entry ) && isset( $entry->loc ) ) {
                $loc = $entry->loc;
            }

            if ( empty( $loc ) ) {
                $filtered[] = $entry;
                continue;
            }

            $entry_host = parse_url( $loc, PHP_URL_HOST );

            if ( ! is_string( $entry_host ) || strcasecmp( $entry_host, $current_host ) === 0 ) {
                $filtered[] = $entry;
            } elseif ( defined( 'TRP_DEBUG_SITEMAP' ) && TRP_DEBUG_SITEMAP ) {
                error_log( 'TRP DDP Sitemap: Removed AIOSEO entry with cross-domain URL: ' . $loc );
            }
        }

        return $filtered;
    }

    /**
     * Post-filter legacy AIOSEO (aiosp) sitemap data to remove cross-domain URLs
     *
     * Hook: aiosp_sitemap_data (priority 2, after SEO Pack's priority 1)
     *
     * @param array  $data          Sitemap data array
     * @param string $sitemap_type  Sitemap type (e.g., 'root', 'post', etc.)
     * @param int    $page_number   Page number
     * @param array  $aioseop_opts  AIOSEO options
     * @return array Filtered data
     */
    public function filter_aiosp_sitemap_data_by_domain( $data, $sitemap_type, $page_number, $aioseop_opts ) {
        if ( $sitemap_type === 'root' || ! is_array( $data ) ) {
            return $data;
        }

        return $this->filter_aioseo_sitemap_entries_by_domain( $data );
    }

    /**
     * Disable sitemap caching when DDP is active
     *
     * Yoast and RankMath cache sitemaps in transients without varying by hostname,
     * so the same cached sitemap would be served to all domains. Disabling the cache
     * ensures each domain generates its own sitemap.
     *
     * Hooks: wpseo_enable_xml_sitemap_transient_caching, rank_math/sitemap/enable_caching
     *
     * @param bool $enabled Whether caching is enabled
     * @return false Always false to disable caching
     */
    public function disable_sitemap_cache_for_ddp( $enabled ) {
        return false;
    }

    /**
     * Filter slugs on internal links for Multiple Domains compatibility
     *
     * Wrapper that calls filter_slug_translated_url_for_domains with proper parameters.
     *
     * @param string $url      The URL to filter
     * @param string $language The target language code
     * @return string Filtered URL with translated slugs and correct domain
     */
    public function filter_slugs_on_internal_links( $url, $language ) {
        // Use unified detection that handles both domain mapping and path-based slugs
        $source_language = $this->get_language_for_url( $url );
        if ( $source_language === null ) {
            $source_language = $this->settings['default-language'];
        }

        // Reuse the main slug translation method
        return $this->filter_slug_translated_url_for_domains( $url, $url, $source_language, $language );
    }

    /**
     * Filter slug-translated URLs to apply domain mapping and translate slugs
     *
     * The slug manager's detection gets confused with Multiple Domains, so we:
     * 1. Start from $original_url (correct source domain + source slugs)
     * 2. Apply domain mapping to get target domain
     * 3. Translate slugs from source to target language
     *
     * @param string $final_url       The URL after slug translation (ignore - it's wrong)
     * @param string $original_url    The original URL
     * @param string $source_language Source language code
     * @param string $target_language Target language code
     * @return string Filtered URL
     */
    public function filter_slug_translated_url_for_domains( $final_url, $original_url, $source_language, $target_language ) {
        // Determine if we need to translate slugs
        // We need to translate slugs when:
        // 1. Target language has domain mapping, OR
        // 2. Source language has domain mapping (even if target doesn't)
        $target_has_domain = $this->get_language_domain( $target_language ) !== false;
        $source_has_domain = $this->get_language_domain( $source_language ) !== false;

        if ( ! $target_has_domain && ! $source_has_domain ) {
            return $final_url; // Neither has domain mapping, use original result
        }

        // Start from original URL and apply domain mapping using existing filter
        // This gives us: correct target domain + source slugs + no language slug in path
        //$url_with_target_domain = $this->filter_get_url_for_language_domains( $original_url, $original_url, $target_language, '', '', '' );

        // Now translate the slugs in the path
        // We need to access the slug manager's translation methods
        // Get SEO Pack slug manager if available
        if(class_exists('TRP_IN_Seo_Pack')){
            $seo_pack = TRP_IN_Seo_Pack::get_seo_pack_instance();
        } else {
            $seo_pack = false;
        }

        if ( ! $seo_pack || ! isset( $seo_pack->slug_manager ) ) {
            // SEO Pack not available - use fallback for WooCommerce slug translation
            return $this->translate_woocommerce_slugs_fallback( $final_url, $source_language, $target_language );
        }

        $slug_manager = $seo_pack->slug_manager;

        // Get translatable slugs from the URL (these are in source language)
        $translatable_slugs = $slug_manager->get_translatable_slugs_from_url( $final_url );
        if ( empty( $translatable_slugs ) ) {
            return $final_url; // No slugs to translate
        }

        // Get slug translation pairs from source to target
        $slug_pairs = $slug_manager->get_slugs_pairs_based_on_language( $translatable_slugs, $source_language, $target_language );

        // Add WooCommerce base slug translations using gettext (handles case where WooCommerce permalink uses English slug)
        $slug_pairs = $slug_manager->add_woocommerce_gettext_slug_pairs( $slug_pairs, $translatable_slugs, $target_language );

        if ( empty( $slug_pairs ) ) {
            return $final_url; // No translations found
        }

        // Get path without language slug
        $path_no_lang_slug = $slug_manager->get_path_no_lang_slug_from_url( $final_url );

        // Replace slugs in the path
        $translated_path = $slug_manager->replace_slugs_in_url_path( $path_no_lang_slug, $slug_pairs );

        // Build final URL by replacing the path
        $final_url = str_replace( $path_no_lang_slug, $translated_path, $final_url );

        return $final_url;
    }

    /**
     * Fallback method to translate WooCommerce slugs when SEO Pack is not available
     *
     * This translates WooCommerce-specific slugs (product, product-category, product-tag)
     * from the source language to the target language using WordPress's translation system.
     *
     * @param string $url             The URL to translate slugs in
     * @param string $source_language The source language code
     * @param string $target_language The target language code
     * @return string The URL with translated WooCommerce slugs
     */
    private function translate_woocommerce_slugs_fallback( $url, $source_language, $target_language ) {
        // Only process if WooCommerce is active
        if ( ! class_exists( 'WooCommerce' ) ) {
            return $url;
        }

        // No translation needed if source and target are the same
        if ( $source_language === $target_language ) {
            return $url;
        }

        $woocommerce_slugs = array( 'product-category', 'product-tag', 'product' );

        foreach ( $woocommerce_slugs as $english_slug ) {
            // Get the slug in source language
            $source_slug = trp_get_transient( 'tp_' . $english_slug . '_' . $source_language );
            if ( $source_slug === false ) {
                $source_slug = trp_x( $english_slug, 'slug', 'woocommerce', $source_language );
                set_transient( 'tp_' . $english_slug . '_' . $source_language, $source_slug, 12 * HOUR_IN_SECONDS );
            }

            // Get the slug in target language
            $target_slug = trp_get_transient( 'tp_' . $english_slug . '_' . $target_language );
            if ( $target_slug === false ) {
                $target_slug = trp_x( $english_slug, 'slug', 'woocommerce', $target_language );
                set_transient( 'tp_' . $english_slug . '_' . $target_language, $target_slug, 12 * HOUR_IN_SECONDS );
            }

            // Replace source slug with target slug in the URL
            if ( $source_slug !== $target_slug ) {
                $url = str_replace( '/' . $source_slug . '/', '/' . $target_slug . '/', $url );
            }
        }

        return $url;
    }

    /**
     * Filter WooCommerce Blocks asset cache to replace URLs with current domain
     *
     * This intercepts the transient read and modifies all cached script URLs to use
     * the current domain instead of the cached domain.
     *
     * @param mixed $pre_transient The value to return instead of the transient value.
     * @return mixed Modified transient data or false to let WordPress retrieve the original transient
     */
    public function filter_woocommerce_blocks_asset_cache( $pre_transient ) {
        // Get the actual transient value
        $transient_key = current_filter() === 'pre_transient_woocommerce_blocks_asset_api_script_data'
            ? 'woocommerce_blocks_asset_api_script_data'
            : 'woocommerce_blocks_asset_api_script_data_ssl';

        $cached_data = get_option( '_transient_' . $transient_key );

        if ( empty( $cached_data ) ) {
            return false; // Let WordPress handle it normally
        }

        // Decode JSON so we can replace plain URLs (JSON escapes slashes)
        $decoded_data = json_decode( $cached_data, true );

        if ( json_last_error() !== JSON_ERROR_NONE || empty( $decoded_data['script_data'] ) ) {
            return false;
        }

        $changed = false;
        foreach ( $decoded_data['script_data'] as $key => $script ) {
            if ( ! empty( $script['src'] ) ) {
                $filtered_src = $this->replace_configured_domain_in_content( $script['src'] );
                if ( $filtered_src !== $script['src'] ) {
                    $decoded_data['script_data'][ $key ]['src'] = $filtered_src;
                    $changed = true;
                }
            }
        }

        return $changed ? json_encode( $decoded_data ) : false;
    }

    /**
     * Filter machine translator referer to use the main domain
     *
     * When Multiple Domains is active, the referer URL used for license validation
     * should always be the main domain (where the license is registered), not
     * the secondary language-specific domains.
     *
     * @param string $referer The current referer URL
     * @return string The main domain URL
     */
    public function filter_machine_translator_referer( $referer ) {
        return get_option( 'home' );
    }

    /**
     * Filter to recognize all configured domains (main and mapped) as internal links
     *
     * This hooks into the trp_is_external_link filter to ensure that absolute URLs
     * pointing to the main domain or any mapped domain are treated as internal links.
     *
     * @param bool   $is_external Whether the URL is considered external
     * @param string $url         The URL being checked
     * @param string $home_url    The home URL used for comparison
     * @return bool False if URL belongs to any configured domain, original value otherwise
     */
    public function filter_is_external_link( $is_external, $url, $home_url ) {
        if ( ! $is_external ) {
            return $is_external;
        }

        $parsed_url = parse_url( $url );
        if ( empty( $parsed_url['host'] ) ) {
            return $is_external;
        }

        $url_host = $parsed_url['host'];

        // Check if URL belongs to the main domain
        $main_domain = get_option( 'home' );
        $parsed_main = parse_url( $main_domain );
        if ( ! empty( $parsed_main['host'] ) && strcasecmp( $url_host, $parsed_main['host'] ) === 0 ) {
            return false;
        }

        // Check if URL belongs to any mapped domain (reuse existing method)
        if ( $this->get_language_for_domain( $url_host ) !== null ) {
            return false;
        }

        return $is_external;
    }

    /**
     * Filter core/file block URLs to use the current domain
     *
     * The WordPress core/file block embeds PDF files using the main domain URL.
     * On secondary domains, this causes CORS issues in certain hosting environments.
     * This filter replaces URLs in the block content with the current domain.
     *
     * @param string $block_content The block content
     * @param array  $block         The block array
     * @return string Modified block content
     */
    public function filter_file_block_urls( $block_content, $block ) {
        // Only process core/file blocks
        if ( empty( $block['blockName'] ) || $block['blockName'] !== 'core/file' ) {
            return $block_content;
        }

        if ( is_admin() ) {
            return $block_content;
        }

        return $this->replace_configured_domain_in_content( $block_content );
    }

    /**
     * Filter inline style domains to prevent CORS issues with cached CSS
     *
     * Themes like Divi generate CSS with @font-face rules using absolute URLs and
     * cache the result to files shared across all domains. When the cache is generated
     * on one domain, pages served from a different domain get font URLs pointing to
     * the wrong origin, causing CORS errors.
     *
     * This method runs just before styles are printed and replaces any configured
     * domain hostname with the current request's hostname in inline CSS content.
     */
    public function filter_inline_style_domains() {
        if ( is_admin() ) {
            return;
        }

        global $wp_styles;
        if ( empty( $wp_styles ) || ! ( $wp_styles instanceof WP_Styles ) ) {
            return;
        }

        foreach ( $wp_styles->registered as $handle => $style ) {
            if ( empty( $style->extra['after'] ) || ! is_array( $style->extra['after'] ) ) {
                continue;
            }

            foreach ( $style->extra['after'] as $key => $inline_css ) {
                if ( empty( $inline_css ) || ! is_string( $inline_css ) ) {
                    continue;
                }

                $wp_styles->registered[ $handle ]->extra['after'][ $key ] = $this->replace_configured_domain_in_content( $inline_css );
            }
        }
    }

    /**
     * Replace any configured domain with the current request's domain in a content string
     *
     * Handles protocol-relative (//host/), https, and http URLs. Used to prevent CORS
     * issues when cached or pre-rendered content contains URLs from a different domain
     * than the one currently being served.
     *
     * @param string $content The content string to process
     * @return string The content with domains replaced, or original content if no changes needed
     */
    private function replace_configured_domain_in_content( $content ) {
        if ( empty( $content ) || ! is_string( $content ) ) {
            return $content;
        }

        $current_host = $this->get_current_host();
        if ( empty( $current_host ) ) {
            return $content;
        }

        // Collect all configured domain hostnames (main site + all secondary language domains)
        $all_hosts = array();

        $main_site_url = get_option( 'siteurl' );
        $parsed_main   = parse_url( $main_site_url );
        if ( ! empty( $parsed_main['host'] ) ) {
            $all_hosts[] = $parsed_main['host'];
        }

        $domain_mappings = isset( $this->settings['trp-multiple-domains'] ) ? $this->settings['trp-multiple-domains'] : array();
        foreach ( $domain_mappings as $mapping ) {
            if ( ! empty( $mapping['enabled'] ) && ! empty( $mapping['domain'] ) ) {
                $parsed = parse_url( $mapping['domain'] );
                if ( ! empty( $parsed['host'] ) ) {
                    $all_hosts[] = $parsed['host'];
                }
            }
        }

        // Only replace domains that differ from the current one
        $other_hosts = array_unique( array_filter( $all_hosts, function( $host ) use ( $current_host ) {
            return strcasecmp( $host, $current_host ) !== 0;
        } ) );

        if ( empty( $other_hosts ) ) {
            return $content;
        }

        // Build search/replace pairs for protocol-relative and absolute URLs
        $search  = array();
        $replace = array();
        foreach ( $other_hosts as $host ) {
            $search[]  = '//' . $host . '/';
            $replace[] = '//' . $current_host . '/';
            $search[]  = 'https://' . $host . '/';
            $replace[] = 'https://' . $current_host . '/';
            $search[]  = 'http://' . $host . '/';
            $replace[] = 'http://' . $current_host . '/';
        }

        return str_replace( $search, $replace, $content );
    }

}
