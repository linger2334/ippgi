<?php
/**
 * Product Display Names Helper Functions
 *
 * These functions retrieve product display names from the Customizer settings.
 * Settings are managed in: Appearance -> Customize -> IPPGI Settings -> Product Display Names
 *
 * @package IPPGI
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Default product display names
 */
function ippgi_get_default_product_names() {
    return [
        'ppgi'     => 'PPGI',
        'gi'       => 'GI',
        'gl'       => 'GL',
        'hrc'      => 'HRC',
        'crc'      => 'CRC Hard',
        'aluminum' => 'AL',
    ];
}

/**
 * Get product display name
 *
 * @param string $type Product type key (ppgi, gi, gl, hrc, crc, aluminum)
 * @return string Display name (returns default if empty or whitespace)
 */
function ippgi_get_product_display_name($type) {
    $defaults = ippgi_get_default_product_names();

    // Normalize type key
    $type = strtolower($type);
    if ($type === 'al') {
        $type = 'aluminum';
    }
    if ($type === 'crc_hard') {
        $type = 'crc';
    }

    $default_value = $defaults[$type] ?? $type;

    // Get from Customizer theme_mod
    $name = get_theme_mod("ippgi_product_name_{$type}", $default_value);

    // If empty or whitespace only, return default
    if (empty(trim($name))) {
        return $default_value;
    }

    return $name;
}

/**
 * Get all product display names
 *
 * @return array Associative array of type => display name (empty values use defaults)
 */
function ippgi_get_all_product_display_names() {
    $defaults = ippgi_get_default_product_names();

    $result = [];
    foreach ($defaults as $type => $default_name) {
        $name = get_theme_mod("ippgi_product_name_{$type}", $default_name);
        // If empty or whitespace only, use default
        $result[$type] = empty(trim($name)) ? $default_name : $name;
    }

    return $result;
}
