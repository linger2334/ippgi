<?php
/**
 * Mobile number collection modal for logged-in users.
 *
 * @package IPPGI
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$phone_countries = ippgi_get_country_calling_codes();
$profile_country = is_user_logged_in()
    ? (string) get_user_meta(get_current_user_id(), 'country', true)
    : '';
$selected_country_iso = ippgi_get_country_iso_by_name($profile_country);
?>

<div class="phone-collection-modal" id="phone-collection-modal" role="dialog" aria-modal="true" aria-labelledby="phone-collection-title" hidden>
    <div class="phone-collection-modal__backdrop" data-phone-modal-close></div>

    <div class="phone-collection-modal__content">
        <button type="button" class="phone-collection-modal__close" data-phone-modal-close aria-label="<?php esc_attr_e('Close', 'ippgi'); ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        </button>

        <h2 id="phone-collection-title" class="phone-collection-modal__title">
            <?php esc_html_e('Add your mobile number', 'ippgi'); ?>
        </h2>
        <p class="phone-collection-modal__description">
            <?php esc_html_e('Please enter your mobile number to continue to detailed pricing.', 'ippgi'); ?>
        </p>

        <form class="phone-collection-form" id="phone-collection-form" novalidate>
            <div class="phone-collection-form__phone-row">
                <div class="phone-collection-form__field phone-collection-form__field--country">
                    <label class="phone-collection-form__label" for="phone-collection-country">
                        <?php esc_html_e('Country code', 'ippgi'); ?>
                    </label>
                    <select
                        id="phone-collection-country"
                        class="phone-collection-form__select"
                        name="country_iso"
                        autocomplete="country"
                        aria-describedby="phone-collection-help phone-collection-error"
                        required>
                        <option value=""><?php esc_html_e('Select code', 'ippgi'); ?></option>
                        <?php foreach ($phone_countries as $iso_code => $country) : ?>
                            <option
                                value="<?php echo esc_attr($iso_code); ?>"
                                data-dial-code="<?php echo esc_attr($country['dial_code']); ?>"
                                <?php selected($selected_country_iso, $iso_code); ?>>
                                <?php echo esc_html($country['dial_code'] . ' ' . $country['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="phone-collection-form__field phone-collection-form__field--number">
                    <label class="phone-collection-form__label" for="phone-collection-input">
                        <?php esc_html_e('Mobile Number', 'ippgi'); ?>
                    </label>
                    <input
                        type="tel"
                        id="phone-collection-input"
                        class="phone-collection-form__input"
                        name="phone_number"
                        inputmode="tel"
                        autocomplete="tel-national"
                        maxlength="20"
                        placeholder="13812345678"
                        aria-describedby="phone-collection-help phone-collection-error"
                        required>
                </div>
            </div>
            <p class="phone-collection-form__help" id="phone-collection-help">
                <?php esc_html_e('Your number may be used for price inquiries and customer support.', 'ippgi'); ?>
                <a href="<?php echo esc_url(home_url('/privacy')); ?>"><?php esc_html_e('Privacy Policy', 'ippgi'); ?></a>
            </p>
            <p class="phone-collection-form__error" id="phone-collection-error" role="alert" hidden></p>

            <div class="phone-collection-form__actions">
                <button type="button" class="phone-collection-form__button phone-collection-form__button--cancel" data-phone-modal-close>
                    <?php esc_html_e('Not now', 'ippgi'); ?>
                </button>
                <button type="submit" class="phone-collection-form__button phone-collection-form__button--submit">
                    <?php esc_html_e('Continue', 'ippgi'); ?>
                </button>
            </div>
        </form>
    </div>
</div>
