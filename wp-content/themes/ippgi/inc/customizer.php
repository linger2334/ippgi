<?php
/**
 * Theme Customizer
 *
 * @package IPPGI
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register customizer settings
 */
function ippgi_customize_register($wp_customize) {
    // Add IPPGI panel
    $wp_customize->add_panel('ippgi_settings', [
        'title'       => __('IPPGI Settings', 'ippgi'),
        'description' => __('Customize IPPGI theme settings.', 'ippgi'),
        'priority'    => 30,
    ]);

    // ========================================
    // Header Section
    // ========================================
    $wp_customize->add_section('ippgi_header_section', [
        'title'    => __('Header', 'ippgi'),
        'panel'    => 'ippgi_settings',
        'priority' => 10,
    ]);

    // Show search in header
    $wp_customize->add_setting('ippgi_header_search', [
        'default'           => true,
        'sanitize_callback' => 'ippgi_sanitize_checkbox',
    ]);

    $wp_customize->add_control('ippgi_header_search', [
        'label'   => __('Show search button in header', 'ippgi'),
        'section' => 'ippgi_header_section',
        'type'    => 'checkbox',
    ]);

    // ========================================
    // Footer Section
    // ========================================
    $wp_customize->add_section('ippgi_footer_section', [
        'title'    => __('Footer', 'ippgi'),
        'panel'    => 'ippgi_settings',
        'priority' => 20,
    ]);

    // Footer text
    $wp_customize->add_setting('ippgi_footer_text', [
        'default'           => '',
        'sanitize_callback' => 'wp_kses_post',
    ]);

    $wp_customize->add_control('ippgi_footer_text', [
        'label'       => __('Footer Description', 'ippgi'),
        'description' => __('Text displayed below the logo in the footer.', 'ippgi'),
        'section'     => 'ippgi_footer_section',
        'type'        => 'textarea',
    ]);

    // Social links
    $social_networks = [
        'facebook' => __('Facebook URL', 'ippgi'),
        'linkedin' => __('LinkedIn URL', 'ippgi'),
        'twitter'  => __('Twitter/X URL', 'ippgi'),
        'instagram' => __('Instagram URL', 'ippgi'),
        'pinterest' => __('Pinterest URL', 'ippgi'),
    ];

    foreach ($social_networks as $network => $label) {
        $wp_customize->add_setting("ippgi_social_{$network}", [
            'default'           => '',
            'sanitize_callback' => 'esc_url_raw',
        ]);

        $wp_customize->add_control("ippgi_social_{$network}", [
            'label'   => $label,
            'section' => 'ippgi_footer_section',
            'type'    => 'url',
        ]);
    }

    // ========================================
    // Colors Section
    // ========================================
    $wp_customize->add_section('ippgi_colors_section', [
        'title'    => __('Colors', 'ippgi'),
        'panel'    => 'ippgi_settings',
        'priority' => 30,
    ]);

    // Primary color
    $wp_customize->add_setting('ippgi_primary_color', [
        'default'           => '#00B4D8',
        'sanitize_callback' => 'sanitize_hex_color',
    ]);

    $wp_customize->add_control(new WP_Customize_Color_Control($wp_customize, 'ippgi_primary_color', [
        'label'   => __('Primary Color', 'ippgi'),
        'section' => 'ippgi_colors_section',
    ]));

    // Secondary primary color (for gradient)
    $wp_customize->add_setting('ippgi_primary_color_dark', [
        'default'           => '#0077B6',
        'sanitize_callback' => 'sanitize_hex_color',
    ]);

    $wp_customize->add_control(new WP_Customize_Color_Control($wp_customize, 'ippgi_primary_color_dark', [
        'label'       => __('Primary Color (Dark)', 'ippgi'),
        'description' => __('Used for header gradient end color.', 'ippgi'),
        'section'     => 'ippgi_colors_section',
    ]));

    // ========================================
    // Membership Section
    // ========================================
    $wp_customize->add_section('ippgi_membership_section', [
        'title'    => __('Membership Pages', 'ippgi'),
        'panel'    => 'ippgi_settings',
        'priority' => 40,
    ]);

    // Login page
    $wp_customize->add_setting('ippgi_login_page', [
        'default'           => 0,
        'sanitize_callback' => 'absint',
    ]);

    $wp_customize->add_control('ippgi_login_page', [
        'label'   => __('Login Page', 'ippgi'),
        'section' => 'ippgi_membership_section',
        'type'    => 'dropdown-pages',
    ]);

    // Register page
    $wp_customize->add_setting('ippgi_register_page', [
        'default'           => 0,
        'sanitize_callback' => 'absint',
    ]);

    $wp_customize->add_control('ippgi_register_page', [
        'label'   => __('Register Page', 'ippgi'),
        'section' => 'ippgi_membership_section',
        'type'    => 'dropdown-pages',
    ]);

    // Profile page
    $wp_customize->add_setting('ippgi_profile_page', [
        'default'           => 0,
        'sanitize_callback' => 'absint',
    ]);

    $wp_customize->add_control('ippgi_profile_page', [
        'label'   => __('Profile Page', 'ippgi'),
        'section' => 'ippgi_membership_section',
        'type'    => 'dropdown-pages',
    ]);

    // Subscribe page
    $wp_customize->add_setting('ippgi_subscribe_page', [
        'default'           => 0,
        'sanitize_callback' => 'absint',
    ]);

    $wp_customize->add_control('ippgi_subscribe_page', [
        'label'   => __('Subscribe Page', 'ippgi'),
        'section' => 'ippgi_membership_section',
        'type'    => 'dropdown-pages',
    ]);

    // ========================================
    // Quote Requests Section
    // ========================================
    $wp_customize->add_section('ippgi_quote_requests_section', [
        'title'       => __('Quote Requests', 'ippgi'),
        'description' => __('Configure where homepage quote request notifications are sent.', 'ippgi'),
        'panel'       => 'ippgi_settings',
        'priority'    => 45,
    ]);

    $wp_customize->add_setting('ippgi_quote_request_recipient_email', [
        'default'           => '',
        'sanitize_callback' => 'ippgi_sanitize_optional_email',
        'validate_callback' => 'ippgi_validate_quote_request_recipient_emails',
    ]);

    $wp_customize->add_control('ippgi_quote_request_recipient_email', [
        'label'       => __('Quote Notification Emails', 'ippgi'),
        'description' => __('Enter one or more email addresses, separated by commas. Example: sales@example.com, quote@example.com. Leave empty to use the site admin email.', 'ippgi'),
        'section'     => 'ippgi_quote_requests_section',
        'type'        => 'text',
        'input_attrs' => [
            'placeholder' => 'sales@example.com, quote@example.com',
        ],
    ]);

    // ========================================
    // Homepage Banner Section
    // ========================================
    $wp_customize->add_section('ippgi_banner_section', [
        'title'       => __('Homepage Banner', 'ippgi'),
        'description' => __('Configure up to 5 banner slides for the homepage carousel.', 'ippgi'),
        'panel'       => 'ippgi_settings',
        'priority'    => 15,
    ]);

    // Banner autoplay interval
    $wp_customize->add_setting('ippgi_banner_interval', [
        'default'           => 5000,
        'sanitize_callback' => 'absint',
    ]);

    $wp_customize->add_control('ippgi_banner_interval', [
        'label'       => __('Autoplay Interval (ms)', 'ippgi'),
        'description' => __('Time between slides in milliseconds. Default: 5000 (5 seconds)', 'ippgi'),
        'section'     => 'ippgi_banner_section',
        'type'        => 'number',
        'input_attrs' => [
            'min'  => 1000,
            'max'  => 20000,
            'step' => 500,
        ],
    ]);

    // Add 5 banner slides
    for ($i = 1; $i <= 5; $i++) {
        // Banner image
        $wp_customize->add_setting("ippgi_banner_{$i}_image", [
            'default'           => '',
            'sanitize_callback' => 'esc_url_raw',
        ]);

        $wp_customize->add_control(new WP_Customize_Image_Control($wp_customize, "ippgi_banner_{$i}_image", [
            'label'   => sprintf(__('Banner %d Image', 'ippgi'), $i),
            'section' => 'ippgi_banner_section',
        ]));

        // Banner link
        $wp_customize->add_setting("ippgi_banner_{$i}_link", [
            'default'           => '',
            'sanitize_callback' => 'esc_url_raw',
        ]);

        $wp_customize->add_control("ippgi_banner_{$i}_link", [
            'label'   => sprintf(__('Banner %d Link', 'ippgi'), $i),
            'section' => 'ippgi_banner_section',
            'type'    => 'url',
        ]);

        // Banner title (optional)
        $wp_customize->add_setting("ippgi_banner_{$i}_title", [
            'default'           => '',
            'sanitize_callback' => 'sanitize_text_field',
        ]);

        $wp_customize->add_control("ippgi_banner_{$i}_title", [
            'label'   => sprintf(__('Banner %d Title (optional)', 'ippgi'), $i),
            'section' => 'ippgi_banner_section',
            'type'    => 'text',
        ]);
    }

    // ========================================
    // Product Display Names Section
    // ========================================
    $wp_customize->add_section('ippgi_product_names_section', [
        'title'       => __('Product Display Names', 'ippgi'),
        'description' => __('Customize the product names shown throughout the site. Leave empty to use the default value.', 'ippgi'),
        'panel'       => 'ippgi_settings',
        'priority'    => 50,
    ]);

    // Product names with defaults and full names
    $product_settings = [
        'ppgi'     => ['default' => 'PPGI', 'full' => 'Pre-painted Galvanized Iron'],
        'gi'       => ['default' => 'GI', 'full' => 'Galvanized Steel'],
        'gl'       => ['default' => 'GL', 'full' => 'Galvalume Steel'],
        'hrc'      => ['default' => 'HRC', 'full' => 'Hot Rolled Coil'],
        'crc'      => ['default' => 'CRC Hard', 'full' => 'Cold Rolled Hard Coil'],
        'aluminum' => ['default' => 'AL', 'full' => 'Aluminum Sheet'],
    ];

    foreach ($product_settings as $type => $info) {
        $wp_customize->add_setting("ippgi_product_name_{$type}", [
            'default'           => $info['default'],
            'sanitize_callback' => 'sanitize_text_field',
        ]);

        $wp_customize->add_control("ippgi_product_name_{$type}", [
            'label'       => $info['full'],
            'description' => sprintf(__('Default: %s', 'ippgi'), $info['default']),
            'section'     => 'ippgi_product_names_section',
            'type'        => 'text',
        ]);
    }

}
add_action('customize_register', 'ippgi_customize_register');

/**
 * Sanitize checkbox
 */
function ippgi_sanitize_checkbox($checked) {
    return (isset($checked) && true === $checked) ? true : false;
}

/**
 * Sanitize optional email recipients.
 *
 * @param string $value Raw email string.
 * @return string Comma-separated sanitized emails or empty string.
 */
function ippgi_sanitize_optional_email($value) {
    $value = trim((string) $value);

    if ($value === '') {
        return '';
    }

    $parts = preg_split('/[\s,;]+/', $value);
    $emails = [];

    foreach ($parts as $part) {
        $email = sanitize_email($part);
        if ($email !== '' && is_email($email)) {
            $emails[] = $email;
        }
    }

    $emails = array_values(array_unique($emails));

    return implode(', ', $emails);
}

/**
 * Validate optional quote request recipient emails.
 *
 * @param WP_Error $validity Validation state.
 * @param string   $value    Raw email string.
 * @return WP_Error
 */
function ippgi_validate_quote_request_recipient_emails($validity, $value) {
    $value = trim((string) $value);

    if ($value === '') {
        return $validity;
    }

    if (strpos($value, '，') !== false) {
        $validity->add(
            'ippgi_quote_request_recipient_email_invalid_separator',
            __('Please use English commas to separate multiple email addresses.', 'ippgi')
        );
        return $validity;
    }

    $parts = preg_split('/[\s,;]+/', $value);
    $invalid_emails = [];

    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }

        $email = sanitize_email($part);
        if ($email === '' || !is_email($email)) {
            $invalid_emails[] = $part;
        }
    }

    if (!empty($invalid_emails)) {
        $validity->add(
            'ippgi_quote_request_recipient_email_invalid',
            sprintf(
                /* translators: %s: invalid email list */
                __('These email addresses are invalid: %s', 'ippgi'),
                implode(', ', $invalid_emails)
            )
        );
    }

    return $validity;
}

/**
 * Output customizer CSS
 */
function ippgi_customizer_css() {
    $primary_color      = get_theme_mod('ippgi_primary_color', '#00B4D8');
    $primary_color_dark = get_theme_mod('ippgi_primary_color_dark', '#0077B6');

    // Only output if colors are different from defaults
    if ($primary_color !== '#00B4D8' || $primary_color_dark !== '#0077B6') {
        ?>
        <style id="ippgi-customizer-css">
            :root {
                --color-primary: <?php echo esc_attr($primary_color); ?>;
                --color-primary-dark: <?php echo esc_attr($primary_color_dark); ?>;
                --color-primary-gradient: linear-gradient(135deg, <?php echo esc_attr($primary_color); ?> 0%, <?php echo esc_attr($primary_color_dark); ?> 100%);
            }
        </style>
        <?php
    }
}
add_action('wp_head', 'ippgi_customizer_css');
