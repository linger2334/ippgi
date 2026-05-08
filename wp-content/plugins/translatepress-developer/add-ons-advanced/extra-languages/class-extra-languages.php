<?php


// Exit if accessed directly
if ( !defined('ABSPATH' ) )
    exit();

class TRP_IN_Extra_Languages{

    protected $url_converter;
    protected $trp_languages;
    protected $settings;
    protected $loader;

    public function __construct() {

        define( 'TRP_IN_EL_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
        define( 'TRP_IN_EL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

        // Check if TranslatePress free version is too old
        if( defined( 'TRP_PLUGIN_VERSION' ) && version_compare( '3.0.3', TRP_PLUGIN_VERSION, '>' ) ) {
            add_action( 'admin_init', array( $this, 'add_version_incompatibility_notification' ) );
            return; // Stop execution of constructor
        }

        $trp = TRP_Translate_Press::get_trp_instance();
        $this->loader = $trp->get_component( 'loader' );

        // Check if license is valid before adding pro features
        $license_status = get_option( 'trp_license_status' );

        if ( $license_status === 'valid' ) {
            // Only load pro features with valid license
            // Happens after TP Version 3.0.3
            $this->loader->add_action( 'admin_enqueue_scripts', $this, 'enqueue_sortable_language_script_303' );

            // Hook into language selector to extend it with Active column instead of replacing it
            $this->loader->add_action( 'trp_language_selector_extend_table_heading', $this, 'add_active_column_heading', 10, 2 );
            $this->loader->add_action( 'trp_language_selector_extend_table_row_middle', $this, 'add_active_column_row', 10, 4 );

            // Remove the hidden publish input from free version (we'll add checkbox instead)
            $this->loader->add_filter( 'trp_language_selector_show_publish_hidden_input', $this, 'hide_publish_hidden_input', 10, 3 );

            // Hide upgrade notice when addon is active with valid license
            $this->loader->add_filter( 'trp_show_language_upgrade_notice', $this, 'hide_upgrade_notice', 10, 1 );

            // Allow unlimited languages with valid license
            $this->loader->add_filter( 'trp_secondary_languages', $this, 'extend_extra_languages', 10, 1 );
        }
        // Without valid license, users can still see and edit existing languages but can't add new ones
    }

    /**
     * Hide the default hidden publish input (we'll add checkbox instead)
     *
     * @param bool   $show                  Whether to show the hidden input
     * @param string $selected_language_code Current language code
     * @param bool   $default_language      Whether this is the default language
     * @return bool
     */
    public function hide_publish_hidden_input( $show, $selected_language_code, $default_language ) {
        return false; // Hide the hidden input, we'll add checkbox via extend_table_row
    }

    /**
     * Hide the upgrade notice when addon is active with valid license
     *
     * @param bool $show Whether to show the upgrade notice
     * @return bool
     */
    public function hide_upgrade_notice( $show ) {
        $status = get_option('trp_license_status');
        if( $status == 'valid' ) {
            return false; // Hide upgrade notice - user can add unlimited languages
        }
        return $show;
    }

    /**
     * Add Active column heading to the language selector table
     *
     * @param array $settings       TranslatePress settings
     * @param bool  $show_formality Whether formality column is shown
     */
    public function add_active_column_heading( $settings, $show_formality ) {
        ?>
        <div class="trp-language-field trp-field-active">
            <div class="trp-languages-table-heading-item trp-primary-text-bold">
                <span><?php esc_html_e( 'Active', 'translatepress-multilingual' ); ?></span>
                <div class="trp-settings-info-sign" data-tooltip="<?php echo wp_kses( __( 'The inactive languages will still be visible and active for the admin. For other users they won\'t be visible in the language switchers and won\'t be accessible either.', 'translatepress-multilingual' ), array() ); ?>"></div>
            </div>
        </div>
        <?php
    }

    /**
     * Add Active column row (checkbox) to each language in the selector table
     *
     * @param string $selected_language_code Current language code
     * @param bool   $default_language       Whether this is the default language
     * @param array  $settings               TranslatePress settings
     * @param bool   $show_formality         Whether formality column is shown
     */
    public function add_active_column_row( $selected_language_code, $default_language, $settings, $show_formality ) {
        if ( ! $this->settings ) {
            $this->settings = $settings;
        }
        ?>
        <div class="trp-language-field trp-field-active">
            <label class="trp-language-field-label"><?php esc_html_e( 'Active', 'translatepress-multilingual' ); ?></label>
            <div class="trp-switch">
                <input type="checkbox" id="switch-<?php echo esc_attr($selected_language_code); ?>"
                       class="trp-switch-input trp-translation-published"
                       name="trp_settings[publish-languages][]"
                       value="<?php echo esc_attr($selected_language_code); ?>"
                    <?php echo in_array($selected_language_code, $this->settings['publish-languages']) ? 'checked ' : ''; ?>
                    <?php echo $default_language ? 'disabled ' : ''; ?> />

                <label for="switch-<?php echo esc_attr($selected_language_code); ?>" class="trp-switch-label"></label>

                <?php if ($default_language) { ?>
                    <input type="hidden" class="trp-hidden-default-language"
                           name="trp_settings[publish-languages][]"
                           value="<?php echo esc_attr($selected_language_code); ?>" />
                <?php } ?>
            </div>
        </div>
        <?php
    }

    public function enqueue_sortable_language_script_303( ){
        if ( isset( $_GET['page'] ) && $_GET['page'] === 'translate-press' ){
            // Enqueue sortable script for drag-and-drop language ordering
            wp_enqueue_script( 'trp-sortable-languages', TRP_IN_EL_PLUGIN_URL . 'assets/js/trp-sortable-languages.js', array( 'jquery-ui-sortable' ), TRP_PLUGIN_VERSION );
        }
    }

    public function extend_extra_languages($number){
        $status = get_option('trp_license_status');
        if($status == 'valid'){
            return 1000;
        }

        return $number;
    }

    /**
     * Add notification when TranslatePress version is incompatible
     */
    public function add_version_incompatibility_notification() {
        $notifications = TRP_Plugin_Notifications::get_instance();

        $notification_id = 'trp_extra_languages_version_incompatible';
        $required_version = '3.0.3';
        $current_version = defined( 'TRP_PLUGIN_VERSION' ) ? TRP_PLUGIN_VERSION : __( 'unknown', 'translatepress-multilingual' );

        $message = '<p style="padding-right:30px;">';
        $message .= sprintf(
            __( '<strong>Extra Languages add-on</strong> requires TranslatePress version %1$s or higher. You are currently using version %2$s. Please update TranslatePress to enable this feature.', 'translatepress-multilingual' ),
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

}