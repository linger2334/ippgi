<?php
/**
 * Admin settings for IPPGI Prices.
 *
 * @package IPPGI_Prices
 */

if (!defined('ABSPATH')) {
    exit;
}

class IPPGI_Prices_Admin {

    /**
     * Option names.
     */
    const OPTION_APP_KEY = 'ippgi_prices_aliyun_app_key';
    const OPTION_APP_SECRET = 'ippgi_prices_aliyun_app_secret';

    /**
     * Constructor.
     */
    public function __construct() {
        add_action('customize_register', array($this, 'register_customizer_settings'), 20);
    }

    /**
     * Register API credential controls under Appearance -> Customize.
     *
     * @param WP_Customize_Manager $wp_customize Customize manager instance.
     */
    public function register_customizer_settings($wp_customize) {
        $section_args = array(
            'title' => __('API Credentials', 'ippgi-prices'),
            'description' => __('Configure Aliyun signature credentials for exchange-rate requests.', 'ippgi-prices'),
            'priority' => 80,
            'capability' => 'manage_options',
        );

        if (method_exists($wp_customize, 'get_panel') && $wp_customize->get_panel('ippgi_settings')) {
            $section_args['panel'] = 'ippgi_settings';
        }

        $wp_customize->add_section('ippgi_prices_api_credentials_section', $section_args);

        $wp_customize->add_setting(
            self::OPTION_APP_KEY,
            array(
                'type' => 'option',
                'sanitize_callback' => array($this, 'sanitize_app_key'),
                'default' => '',
                'capability' => 'manage_options',
            )
        );

        $wp_customize->add_control(
            self::OPTION_APP_KEY,
            array(
                'label' => __('Aliyun APP Key', 'ippgi-prices'),
                'section' => 'ippgi_prices_api_credentials_section',
                'type' => 'text',
                'input_attrs' => array(
                    'autocomplete' => 'off',
                ),
            )
        );

        $wp_customize->add_setting(
            self::OPTION_APP_SECRET,
            array(
                'type' => 'option',
                'sanitize_callback' => array($this, 'sanitize_app_secret'),
                'default' => '',
                'capability' => 'manage_options',
            )
        );

        $wp_customize->add_control(
            self::OPTION_APP_SECRET,
            array(
                'label' => __('Aliyun APP Secret', 'ippgi-prices'),
                'description' => __('Leave empty to keep current secret unchanged.', 'ippgi-prices'),
                'section' => 'ippgi_prices_api_credentials_section',
                'type' => 'password',
                'input_attrs' => array(
                    'autocomplete' => 'new-password',
                ),
            )
        );
    }

    /**
     * Sanitize APP Key.
     *
     * @param string $value Input value.
     * @return string
     */
    public function sanitize_app_key($value) {
        return $this->sanitize_credential_value($value);
    }

    /**
     * Sanitize APP Secret.
     * Keep existing value when submitted empty.
     *
     * @param string $value Input value.
     * @return string
     */
    public function sanitize_app_secret($value) {
        $value = trim((string) $value);
        if ('' === $value) {
            $existing = get_option(self::OPTION_APP_SECRET, '');
            if (!empty($existing)) {
                return (string) $existing;
            }
        }

        return $this->sanitize_credential_value($value);
    }

    /**
     * Sanitize credential value without altering valid secret characters.
     *
     * @param string $value Input value.
     * @return string
     */
    private function sanitize_credential_value($value) {
        $value = trim((string) $value);
        $sanitized = preg_replace('/[\x00-\x1F\x7F]/', '', $value);
        return null === $sanitized ? '' : $sanitized;
    }
}
