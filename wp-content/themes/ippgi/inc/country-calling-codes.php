<?php
/**
 * Country and region calling codes used by phone collection forms.
 *
 * @package IPPGI
 * @since 1.8.1
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Return supported countries and regions keyed by ISO-style code.
 *
 * @return array<string, array{name: string, dial_code: string}>
 */
function ippgi_get_country_calling_codes() {
    static $countries = null;

    if (null !== $countries) {
        return $countries;
    }

    $countries = [
        'AF' => ['name' => 'Afghanistan', 'dial_code' => '+93'],
        'AL' => ['name' => 'Albania', 'dial_code' => '+355'],
        'DZ' => ['name' => 'Algeria', 'dial_code' => '+213'],
        'AD' => ['name' => 'Andorra', 'dial_code' => '+376'],
        'AO' => ['name' => 'Angola', 'dial_code' => '+244'],
        'AG' => ['name' => 'Antigua and Barbuda', 'dial_code' => '+1'],
        'AR' => ['name' => 'Argentina', 'dial_code' => '+54'],
        'AM' => ['name' => 'Armenia', 'dial_code' => '+374'],
        'AU' => ['name' => 'Australia', 'dial_code' => '+61'],
        'AT' => ['name' => 'Austria', 'dial_code' => '+43'],
        'AZ' => ['name' => 'Azerbaijan', 'dial_code' => '+994'],
        'BS' => ['name' => 'Bahamas', 'dial_code' => '+1'],
        'BH' => ['name' => 'Bahrain', 'dial_code' => '+973'],
        'BD' => ['name' => 'Bangladesh', 'dial_code' => '+880'],
        'BB' => ['name' => 'Barbados', 'dial_code' => '+1'],
        'BY' => ['name' => 'Belarus', 'dial_code' => '+375'],
        'BE' => ['name' => 'Belgium', 'dial_code' => '+32'],
        'BZ' => ['name' => 'Belize', 'dial_code' => '+501'],
        'BJ' => ['name' => 'Benin', 'dial_code' => '+229'],
        'BT' => ['name' => 'Bhutan', 'dial_code' => '+975'],
        'BO' => ['name' => 'Bolivia', 'dial_code' => '+591'],
        'BA' => ['name' => 'Bosnia and Herzegovina', 'dial_code' => '+387'],
        'BW' => ['name' => 'Botswana', 'dial_code' => '+267'],
        'BR' => ['name' => 'Brazil', 'dial_code' => '+55'],
        'BN' => ['name' => 'Brunei', 'dial_code' => '+673'],
        'BG' => ['name' => 'Bulgaria', 'dial_code' => '+359'],
        'BF' => ['name' => 'Burkina Faso', 'dial_code' => '+226'],
        'BI' => ['name' => 'Burundi', 'dial_code' => '+257'],
        'CV' => ['name' => 'Cabo Verde', 'dial_code' => '+238'],
        'KH' => ['name' => 'Cambodia', 'dial_code' => '+855'],
        'CM' => ['name' => 'Cameroon', 'dial_code' => '+237'],
        'CA' => ['name' => 'Canada', 'dial_code' => '+1'],
        'CF' => ['name' => 'Central African Republic', 'dial_code' => '+236'],
        'TD' => ['name' => 'Chad', 'dial_code' => '+235'],
        'CL' => ['name' => 'Chile', 'dial_code' => '+56'],
        'CN' => ['name' => 'China', 'dial_code' => '+86'],
        'CI' => ['name' => "Cote d'Ivoire", 'dial_code' => '+225'],
        'CO' => ['name' => 'Colombia', 'dial_code' => '+57'],
        'KM' => ['name' => 'Comoros', 'dial_code' => '+269'],
        'CG' => ['name' => 'Congo', 'dial_code' => '+242'],
        'CR' => ['name' => 'Costa Rica', 'dial_code' => '+506'],
        'HR' => ['name' => 'Croatia', 'dial_code' => '+385'],
        'CU' => ['name' => 'Cuba', 'dial_code' => '+53'],
        'CY' => ['name' => 'Cyprus', 'dial_code' => '+357'],
        'CZ' => ['name' => 'Czech Republic', 'dial_code' => '+420'],
        'CD' => ['name' => 'Democratic Republic of the Congo', 'dial_code' => '+243'],
        'DK' => ['name' => 'Denmark', 'dial_code' => '+45'],
        'DJ' => ['name' => 'Djibouti', 'dial_code' => '+253'],
        'DM' => ['name' => 'Dominica', 'dial_code' => '+1'],
        'DO' => ['name' => 'Dominican Republic', 'dial_code' => '+1'],
        'EC' => ['name' => 'Ecuador', 'dial_code' => '+593'],
        'EG' => ['name' => 'Egypt', 'dial_code' => '+20'],
        'SV' => ['name' => 'El Salvador', 'dial_code' => '+503'],
        'GQ' => ['name' => 'Equatorial Guinea', 'dial_code' => '+240'],
        'ER' => ['name' => 'Eritrea', 'dial_code' => '+291'],
        'EE' => ['name' => 'Estonia', 'dial_code' => '+372'],
        'SZ' => ['name' => 'Eswatini', 'dial_code' => '+268'],
        'ET' => ['name' => 'Ethiopia', 'dial_code' => '+251'],
        'FJ' => ['name' => 'Fiji', 'dial_code' => '+679'],
        'FI' => ['name' => 'Finland', 'dial_code' => '+358'],
        'FR' => ['name' => 'France', 'dial_code' => '+33'],
        'GA' => ['name' => 'Gabon', 'dial_code' => '+241'],
        'GM' => ['name' => 'Gambia', 'dial_code' => '+220'],
        'GE' => ['name' => 'Georgia', 'dial_code' => '+995'],
        'DE' => ['name' => 'Germany', 'dial_code' => '+49'],
        'GH' => ['name' => 'Ghana', 'dial_code' => '+233'],
        'GR' => ['name' => 'Greece', 'dial_code' => '+30'],
        'GD' => ['name' => 'Grenada', 'dial_code' => '+1'],
        'GT' => ['name' => 'Guatemala', 'dial_code' => '+502'],
        'GN' => ['name' => 'Guinea', 'dial_code' => '+224'],
        'GW' => ['name' => 'Guinea-Bissau', 'dial_code' => '+245'],
        'GY' => ['name' => 'Guyana', 'dial_code' => '+592'],
        'HT' => ['name' => 'Haiti', 'dial_code' => '+509'],
        'HN' => ['name' => 'Honduras', 'dial_code' => '+504'],
        'HK' => ['name' => 'Hong Kong', 'dial_code' => '+852'],
        'HU' => ['name' => 'Hungary', 'dial_code' => '+36'],
        'IS' => ['name' => 'Iceland', 'dial_code' => '+354'],
        'IN' => ['name' => 'India', 'dial_code' => '+91'],
        'ID' => ['name' => 'Indonesia', 'dial_code' => '+62'],
        'IR' => ['name' => 'Iran', 'dial_code' => '+98'],
        'IQ' => ['name' => 'Iraq', 'dial_code' => '+964'],
        'IE' => ['name' => 'Ireland', 'dial_code' => '+353'],
        'IL' => ['name' => 'Israel', 'dial_code' => '+972'],
        'IT' => ['name' => 'Italy', 'dial_code' => '+39'],
        'JM' => ['name' => 'Jamaica', 'dial_code' => '+1'],
        'JP' => ['name' => 'Japan', 'dial_code' => '+81'],
        'JO' => ['name' => 'Jordan', 'dial_code' => '+962'],
        'KZ' => ['name' => 'Kazakhstan', 'dial_code' => '+7'],
        'KE' => ['name' => 'Kenya', 'dial_code' => '+254'],
        'KI' => ['name' => 'Kiribati', 'dial_code' => '+686'],
        'XK' => ['name' => 'Kosovo', 'dial_code' => '+383'],
        'KW' => ['name' => 'Kuwait', 'dial_code' => '+965'],
        'KG' => ['name' => 'Kyrgyzstan', 'dial_code' => '+996'],
        'LA' => ['name' => 'Laos', 'dial_code' => '+856'],
        'LV' => ['name' => 'Latvia', 'dial_code' => '+371'],
        'LB' => ['name' => 'Lebanon', 'dial_code' => '+961'],
        'LS' => ['name' => 'Lesotho', 'dial_code' => '+266'],
        'LR' => ['name' => 'Liberia', 'dial_code' => '+231'],
        'LY' => ['name' => 'Libya', 'dial_code' => '+218'],
        'LI' => ['name' => 'Liechtenstein', 'dial_code' => '+423'],
        'LT' => ['name' => 'Lithuania', 'dial_code' => '+370'],
        'LU' => ['name' => 'Luxembourg', 'dial_code' => '+352'],
        'MO' => ['name' => 'Macao', 'dial_code' => '+853'],
        'MG' => ['name' => 'Madagascar', 'dial_code' => '+261'],
        'MW' => ['name' => 'Malawi', 'dial_code' => '+265'],
        'MY' => ['name' => 'Malaysia', 'dial_code' => '+60'],
        'MV' => ['name' => 'Maldives', 'dial_code' => '+960'],
        'ML' => ['name' => 'Mali', 'dial_code' => '+223'],
        'MT' => ['name' => 'Malta', 'dial_code' => '+356'],
        'MH' => ['name' => 'Marshall Islands', 'dial_code' => '+692'],
        'MR' => ['name' => 'Mauritania', 'dial_code' => '+222'],
        'MU' => ['name' => 'Mauritius', 'dial_code' => '+230'],
        'MX' => ['name' => 'Mexico', 'dial_code' => '+52'],
        'FM' => ['name' => 'Micronesia', 'dial_code' => '+691'],
        'MD' => ['name' => 'Moldova', 'dial_code' => '+373'],
        'MC' => ['name' => 'Monaco', 'dial_code' => '+377'],
        'MN' => ['name' => 'Mongolia', 'dial_code' => '+976'],
        'ME' => ['name' => 'Montenegro', 'dial_code' => '+382'],
        'MA' => ['name' => 'Morocco', 'dial_code' => '+212'],
        'MZ' => ['name' => 'Mozambique', 'dial_code' => '+258'],
        'MM' => ['name' => 'Myanmar', 'dial_code' => '+95'],
        'NA' => ['name' => 'Namibia', 'dial_code' => '+264'],
        'NR' => ['name' => 'Nauru', 'dial_code' => '+674'],
        'NP' => ['name' => 'Nepal', 'dial_code' => '+977'],
        'NL' => ['name' => 'Netherlands', 'dial_code' => '+31'],
        'NZ' => ['name' => 'New Zealand', 'dial_code' => '+64'],
        'NI' => ['name' => 'Nicaragua', 'dial_code' => '+505'],
        'NE' => ['name' => 'Niger', 'dial_code' => '+227'],
        'NG' => ['name' => 'Nigeria', 'dial_code' => '+234'],
        'KP' => ['name' => 'North Korea', 'dial_code' => '+850'],
        'MK' => ['name' => 'North Macedonia', 'dial_code' => '+389'],
        'NO' => ['name' => 'Norway', 'dial_code' => '+47'],
        'OM' => ['name' => 'Oman', 'dial_code' => '+968'],
        'PK' => ['name' => 'Pakistan', 'dial_code' => '+92'],
        'PW' => ['name' => 'Palau', 'dial_code' => '+680'],
        'PS' => ['name' => 'Palestine', 'dial_code' => '+970'],
        'PA' => ['name' => 'Panama', 'dial_code' => '+507'],
        'PG' => ['name' => 'Papua New Guinea', 'dial_code' => '+675'],
        'PY' => ['name' => 'Paraguay', 'dial_code' => '+595'],
        'PE' => ['name' => 'Peru', 'dial_code' => '+51'],
        'PH' => ['name' => 'Philippines', 'dial_code' => '+63'],
        'PL' => ['name' => 'Poland', 'dial_code' => '+48'],
        'PT' => ['name' => 'Portugal', 'dial_code' => '+351'],
        'PR' => ['name' => 'Puerto Rico', 'dial_code' => '+1'],
        'QA' => ['name' => 'Qatar', 'dial_code' => '+974'],
        'RO' => ['name' => 'Romania', 'dial_code' => '+40'],
        'RU' => ['name' => 'Russia', 'dial_code' => '+7'],
        'RW' => ['name' => 'Rwanda', 'dial_code' => '+250'],
        'KN' => ['name' => 'Saint Kitts and Nevis', 'dial_code' => '+1'],
        'LC' => ['name' => 'Saint Lucia', 'dial_code' => '+1'],
        'VC' => ['name' => 'Saint Vincent and the Grenadines', 'dial_code' => '+1'],
        'WS' => ['name' => 'Samoa', 'dial_code' => '+685'],
        'SM' => ['name' => 'San Marino', 'dial_code' => '+378'],
        'ST' => ['name' => 'Sao Tome and Principe', 'dial_code' => '+239'],
        'SA' => ['name' => 'Saudi Arabia', 'dial_code' => '+966'],
        'SN' => ['name' => 'Senegal', 'dial_code' => '+221'],
        'RS' => ['name' => 'Serbia', 'dial_code' => '+381'],
        'SC' => ['name' => 'Seychelles', 'dial_code' => '+248'],
        'SL' => ['name' => 'Sierra Leone', 'dial_code' => '+232'],
        'SG' => ['name' => 'Singapore', 'dial_code' => '+65'],
        'SK' => ['name' => 'Slovakia', 'dial_code' => '+421'],
        'SI' => ['name' => 'Slovenia', 'dial_code' => '+386'],
        'SB' => ['name' => 'Solomon Islands', 'dial_code' => '+677'],
        'SO' => ['name' => 'Somalia', 'dial_code' => '+252'],
        'ZA' => ['name' => 'South Africa', 'dial_code' => '+27'],
        'KR' => ['name' => 'South Korea', 'dial_code' => '+82'],
        'SS' => ['name' => 'South Sudan', 'dial_code' => '+211'],
        'ES' => ['name' => 'Spain', 'dial_code' => '+34'],
        'LK' => ['name' => 'Sri Lanka', 'dial_code' => '+94'],
        'SD' => ['name' => 'Sudan', 'dial_code' => '+249'],
        'SR' => ['name' => 'Suriname', 'dial_code' => '+597'],
        'SE' => ['name' => 'Sweden', 'dial_code' => '+46'],
        'CH' => ['name' => 'Switzerland', 'dial_code' => '+41'],
        'SY' => ['name' => 'Syria', 'dial_code' => '+963'],
        'TW' => ['name' => 'Taiwan', 'dial_code' => '+886'],
        'TJ' => ['name' => 'Tajikistan', 'dial_code' => '+992'],
        'TZ' => ['name' => 'Tanzania', 'dial_code' => '+255'],
        'TH' => ['name' => 'Thailand', 'dial_code' => '+66'],
        'TL' => ['name' => 'Timor-Leste', 'dial_code' => '+670'],
        'TG' => ['name' => 'Togo', 'dial_code' => '+228'],
        'TO' => ['name' => 'Tonga', 'dial_code' => '+676'],
        'TT' => ['name' => 'Trinidad and Tobago', 'dial_code' => '+1'],
        'TN' => ['name' => 'Tunisia', 'dial_code' => '+216'],
        'TR' => ['name' => 'Turkey', 'dial_code' => '+90'],
        'TM' => ['name' => 'Turkmenistan', 'dial_code' => '+993'],
        'TV' => ['name' => 'Tuvalu', 'dial_code' => '+688'],
        'UG' => ['name' => 'Uganda', 'dial_code' => '+256'],
        'UA' => ['name' => 'Ukraine', 'dial_code' => '+380'],
        'AE' => ['name' => 'United Arab Emirates', 'dial_code' => '+971'],
        'GB' => ['name' => 'United Kingdom', 'dial_code' => '+44'],
        'US' => ['name' => 'United States', 'dial_code' => '+1'],
        'UY' => ['name' => 'Uruguay', 'dial_code' => '+598'],
        'UZ' => ['name' => 'Uzbekistan', 'dial_code' => '+998'],
        'VU' => ['name' => 'Vanuatu', 'dial_code' => '+678'],
        'VA' => ['name' => 'Vatican City', 'dial_code' => '+39'],
        'VE' => ['name' => 'Venezuela', 'dial_code' => '+58'],
        'VN' => ['name' => 'Vietnam', 'dial_code' => '+84'],
        'YE' => ['name' => 'Yemen', 'dial_code' => '+967'],
        'ZM' => ['name' => 'Zambia', 'dial_code' => '+260'],
        'ZW' => ['name' => 'Zimbabwe', 'dial_code' => '+263'],
    ];

    return $countries;
}

/**
 * Find a country or region by its code.
 *
 * @param string $iso_code ISO-style country code.
 * @return array{name: string, dial_code: string}|null
 */
function ippgi_get_country_calling_code($iso_code) {
    $iso_code = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) $iso_code));
    $countries = ippgi_get_country_calling_codes();

    return $countries[$iso_code] ?? null;
}

/**
 * Find the selector code that matches an existing profile country name.
 *
 * @param string $country_name Existing profile country name.
 * @return string
 */
function ippgi_get_country_iso_by_name($country_name) {
    $country_name = strtolower(trim((string) $country_name));
    if ('' === $country_name) {
        return '';
    }

    $aliases = [
        'dr congo'              => 'CD',
        'ivory coast'           => 'CI',
        'uae'                   => 'AE',
        'uk'                    => 'GB',
        'us'                    => 'US',
        'united arab emirates' => 'AE',
        'united kingdom'       => 'GB',
        'united states'        => 'US',
        'united states of america' => 'US',
    ];
    if (isset($aliases[$country_name])) {
        return $aliases[$country_name];
    }

    foreach (ippgi_get_country_calling_codes() as $iso_code => $country) {
        if (strtolower($country['name']) === $country_name) {
            return $iso_code;
        }
    }

    return '';
}

/**
 * Split a stored phone number into selector and local-number values.
 *
 * An international prefix wins over the profile country. The profile country
 * is used to disambiguate shared calling codes and as a fallback for legacy
 * local numbers without a prefix.
 *
 * @param string $phone           Stored phone number.
 * @param string $profile_country Existing profile country name.
 * @return array{country_iso: string, phone_number: string}
 */
function ippgi_split_phone_number($phone, $profile_country = '') {
    $phone = trim((string) $phone);
    $profile_iso = ippgi_get_country_iso_by_name($profile_country);
    $result = [
        'country_iso' => $profile_iso,
        'phone_number' => preg_replace('/\D+/', '', $phone),
    ];

    if ('' === $phone || '+' !== substr($phone, 0, 1)) {
        return $result;
    }

    $phone_digits = preg_replace('/\D+/', '', $phone);
    $dial_code_candidates = [];
    foreach (ippgi_get_country_calling_codes() as $iso_code => $country) {
        $dial_digits = preg_replace('/\D+/', '', $country['dial_code']);
        $dial_code_candidates[$dial_digits][] = $iso_code;
    }

    uksort($dial_code_candidates, function ($left, $right) {
        return strlen($right) <=> strlen($left);
    });

    foreach ($dial_code_candidates as $dial_digits => $iso_codes) {
        if (0 !== strpos($phone_digits, $dial_digits)) {
            continue;
        }

        $primary_iso_codes = [
            '1'  => 'US',
            '7'  => 'RU',
            '39' => 'IT',
        ];
        if ($profile_iso && in_array($profile_iso, $iso_codes, true)) {
            $selected_iso = $profile_iso;
        } elseif (isset($primary_iso_codes[$dial_digits]) && in_array($primary_iso_codes[$dial_digits], $iso_codes, true)) {
            $selected_iso = $primary_iso_codes[$dial_digits];
        } else {
            $selected_iso = reset($iso_codes);
        }

        return [
            'country_iso' => $selected_iso,
            'phone_number' => substr($phone_digits, strlen($dial_digits)),
        ];
    }

    return $result;
}

/**
 * Validate and normalize a country code and local phone number.
 *
 * @param string $country_iso Country or region selector code.
 * @param string $phone_number Local phone number entered by the user.
 * @param bool   $allow_empty Whether an empty number is allowed.
 * @return string|WP_Error
 */
function ippgi_normalize_phone_number($country_iso, $phone_number, $allow_empty = false) {
    $country_iso = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) $country_iso));
    $phone_number = preg_replace('/\s+/', ' ', trim((string) $phone_number));

    if ('' === $phone_number) {
        return $allow_empty
            ? ''
            : new WP_Error('phone_required', __('Please enter your mobile number.', 'ippgi'));
    }

    $country = ippgi_get_country_calling_code($country_iso);
    if (!$country) {
        return new WP_Error('phone_country_required', __('Please select a country/region code.', 'ippgi'));
    }

    $local_digits = preg_replace('/\D+/', '', $phone_number);
    $all_digits = preg_replace('/\D+/', '', $country['dial_code'] . $local_digits);
    $valid_format = (bool) preg_match('/^[0-9\s\-()]{4,20}$/', $phone_number);
    if (!$valid_format || strlen($all_digits) < 6 || strlen($all_digits) > 15) {
        return new WP_Error('invalid_phone', __('Please enter a valid mobile number.', 'ippgi'));
    }

    return $country['dial_code'] . ' ' . $local_digits;
}
