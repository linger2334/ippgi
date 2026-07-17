<?php
/**
 * Template Name: Edit Profile Page
 *
 * @package IPPGI
 * @since 1.0.0
 */

// Redirect if not logged in
if (!is_user_logged_in()) {
    wp_redirect(ippgi_get_login_url());
    exit;
}

$current_user = wp_get_current_user();

// Get user meta data
$user_country = get_user_meta($current_user->ID, 'country', true);
$user_company = get_user_meta($current_user->ID, 'company_name', true);
$user_phone = get_user_meta($current_user->ID, 'phone', true);
$phone_countries = ippgi_get_country_calling_codes();
$phone_fields = ippgi_split_phone_number($user_phone, $user_country);
$form_phone_iso = $phone_fields['country_iso'];
$form_phone_number = $phone_fields['phone_number'];

// Handle form submission
$message = '';
$message_type = '';

if ('POST' === $_SERVER['REQUEST_METHOD'] && isset($_POST['ippgi_edit_profile_nonce'])) {
    $submitted_nonce = sanitize_text_field(wp_unslash($_POST['ippgi_edit_profile_nonce']));
    if (wp_verify_nonce($submitted_nonce, 'ippgi_edit_profile')) {
        // Sanitize and update user data
        $display_name = sanitize_text_field(wp_unslash($_POST['display_name'] ?? ''));
        $country = sanitize_text_field(wp_unslash($_POST['country'] ?? ''));
        $company = sanitize_text_field(wp_unslash($_POST['company_name'] ?? ''));
        $form_phone_iso = strtoupper(sanitize_text_field(wp_unslash($_POST['phone_country_iso'] ?? '')));
        $form_phone_number = sanitize_text_field(wp_unslash($_POST['phone_number'] ?? ''));
        $phone = ippgi_normalize_phone_number($form_phone_iso, $form_phone_number, true);

        if (is_wp_error($phone)) {
            $message = $phone->get_error_message();
            $message_type = 'error';
        } else {
            // Update display name
            if (!empty($display_name)) {
                wp_update_user([
                    'ID' => $current_user->ID,
                    'display_name' => $display_name,
                ]);
            }

            // Update user meta
            update_user_meta($current_user->ID, 'country', $country);
            update_user_meta($current_user->ID, 'company_name', $company);
            update_user_meta($current_user->ID, 'phone', $phone);

            // Refresh user data and normalized phone fields.
            $current_user = wp_get_current_user();
            $user_country = $country;
            $user_company = $company;
            $user_phone = $phone;
            $phone_fields = ippgi_split_phone_number($user_phone, $user_country);
            $form_phone_iso = $phone_fields['country_iso'];
            $form_phone_number = $phone_fields['phone_number'];

            $message = __('Profile updated successfully.', 'ippgi');
            $message_type = 'success';
        }
    } else {
        $message = __('Security check failed. Please try again.', 'ippgi');
        $message_type = 'error';
    }
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php wp_head(); ?>
</head>

<body <?php body_class('edit-profile-page-body'); ?>>
<?php wp_body_open(); ?>

<main id="main-content" class="site-main">
    <div class="container">
        <div class="edit-profile-page">
            <!-- Page Title -->
            <h1 class="edit-profile-page__title"><?php esc_html_e('Edit Member Profile', 'ippgi'); ?></h1>

            <!-- Edit Profile Form -->
            <form method="post" class="edit-profile-form">
                <?php wp_nonce_field('ippgi_edit_profile', 'ippgi_edit_profile_nonce'); ?>

                <!-- Name Field -->
                <div class="edit-profile-field">
                    <label class="edit-profile-field__label" for="display_name">
                        <?php esc_html_e('Name:', 'ippgi'); ?>
                    </label>
                    <input type="text" id="display_name" name="display_name"
                           class="edit-profile-field__input"
                           value="<?php echo esc_attr($current_user->display_name); ?>">
                </div>

                <!-- Country/Region Field -->
                <div class="edit-profile-field">
                    <label class="edit-profile-field__label">
                        <?php esc_html_e('Country/Region:', 'ippgi'); ?>
                    </label>
                    <input type="hidden" id="country" name="country" value="<?php echo esc_attr($user_country); ?>">
                    <div class="country-selector" id="country-selector">
                        <span class="country-selector__value" id="country-display">
                            <?php echo $user_country ? esc_html($user_country) : ''; ?>
                        </span>
                        <span class="country-selector__arrow">v</span>
                    </div>
                </div>

                <!-- Company Name Field -->
                <div class="edit-profile-field">
                    <label class="edit-profile-field__label" for="company_name">
                        <?php esc_html_e('Company Name:', 'ippgi'); ?>
                    </label>
                    <input type="text" id="company_name" name="company_name"
                           class="edit-profile-field__input"
                           value="<?php echo esc_attr($user_company); ?>">
                </div>

                <!-- Email Field (Read-only) -->
                <div class="edit-profile-field">
                    <label class="edit-profile-field__label" for="email">
                        <?php esc_html_e('Email:', 'ippgi'); ?>
                    </label>
                    <div class="edit-profile-field__value">
                        <?php echo esc_html($current_user->user_email); ?>
                    </div>
                </div>

                <!-- Mobile Number Field -->
                <div class="edit-profile-field">
                    <div class="edit-profile-phone-row">
                        <div class="edit-profile-phone-field edit-profile-phone-field--country">
                            <label class="edit-profile-field__label" for="phone_country_iso">
                                <?php esc_html_e('Country code:', 'ippgi'); ?>
                            </label>
                            <div class="edit-profile-field__select-wrapper">
                                <select
                                    id="phone_country_iso"
                                    name="phone_country_iso"
                                    class="edit-profile-field__select"
                                    autocomplete="country"
                                    aria-describedby="phone-error">
                                    <option value=""><?php esc_html_e('Select code', 'ippgi'); ?></option>
                                    <?php foreach ($phone_countries as $iso_code => $phone_country) : ?>
                                        <option
                                            value="<?php echo esc_attr($iso_code); ?>"
                                            data-dial-code="<?php echo esc_attr($phone_country['dial_code']); ?>"
                                            <?php selected($form_phone_iso, $iso_code); ?>>
                                            <?php echo esc_html($phone_country['dial_code'] . ' ' . $phone_country['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <span class="edit-profile-field__select-arrow" aria-hidden="true">v</span>
                            </div>
                        </div>

                        <div class="edit-profile-phone-field edit-profile-phone-field--number">
                            <label class="edit-profile-field__label" for="phone_number">
                                <?php esc_html_e('Mobile Number:', 'ippgi'); ?>
                            </label>
                            <input
                                type="tel"
                                id="phone_number"
                                name="phone_number"
                                class="edit-profile-field__input"
                                value="<?php echo esc_attr($form_phone_number); ?>"
                                inputmode="tel"
                                autocomplete="tel-national"
                                maxlength="20"
                                pattern="^[0-9\s\-()]{4,20}$"
                                aria-describedby="phone-error"
                                title="<?php esc_attr_e('Please enter a valid mobile number.', 'ippgi'); ?>">
                        </div>
                    </div>
                    <span class="edit-profile-field__error" id="phone-error" hidden>
                        <?php esc_html_e('Please select a country code and enter a valid mobile number.', 'ippgi'); ?>
                    </span>
                </div>

                <!-- Submit Button -->
                <div class="edit-profile-form__submit">
                    <button type="submit" class="edit-profile-submit-btn" id="submit-btn" disabled>
                        <?php esc_html_e('Submit', 'ippgi'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>

<!-- Country Selector Modal -->
<div class="country-modal" id="country-modal" style="display: none;">
    <div class="country-modal__content">
        <div class="country-modal__search">
            <input type="text" id="country-search" class="country-modal__search-input" placeholder="<?php esc_attr_e('Please enter the keywords', 'ippgi'); ?>">
            <svg class="country-modal__search-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"></circle>
                <path d="m21 21-4.35-4.35"></path>
            </svg>
        </div>
        <div class="country-modal__list" id="country-list">
            <?php foreach ($phone_countries as $profile_country) : ?>
                <div class="country-modal__item" data-country="<?php echo esc_attr($profile_country['name']); ?>">
                    <?php echo esc_html($profile_country['name']); ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
(function() {
    const countrySelector = document.getElementById('country-selector');
    const countryModal = document.getElementById('country-modal');
    const countrySearch = document.getElementById('country-search');
    const countryList = document.getElementById('country-list');
    const countryInput = document.getElementById('country');
    const countryDisplay = document.getElementById('country-display');
    const countryItems = Array.from(countryList.querySelectorAll('.country-modal__item'));

    // Filter the server-rendered country list.
    function renderCountries(filter = '') {
        const query = filter.trim().toLowerCase();
        countryItems.forEach(item => {
            const searchableText = `${item.dataset.country || ''} ${item.textContent || ''}`.toLowerCase();
            item.hidden = query !== '' && !searchableText.includes(query);
        });
    }

    countryItems.forEach(item => {
        item.addEventListener('click', function() {
            countryInput.value = this.dataset.country || '';
            countryDisplay.textContent = (this.textContent || '').trim();
            closeModal();
        });
    });

    // Open modal
    function openModal() {
        countryModal.style.display = 'flex';
        countrySearch.value = '';
        renderCountries();
        countrySearch.focus();
    }

    // Close modal
    function closeModal() {
        countryModal.style.display = 'none';
    }

    // Event listeners
    countrySelector.addEventListener('click', openModal);

    countryModal.addEventListener('click', function(e) {
        if (e.target === countryModal) {
            closeModal();
        }
    });

    countrySearch.addEventListener('input', function() {
        renderCountries(this.value);
    });

    // Initial render
    renderCountries();

    // ========== Form Change Detection ==========
    const form = document.querySelector('.edit-profile-form');
    const submitBtn = document.getElementById('submit-btn');
    const phoneCountrySelect = document.getElementById('phone_country_iso');
    const phoneInput = document.getElementById('phone_number');
    const phoneError = document.getElementById('phone-error');

    function getFormValues() {
        const phoneNumber = phoneInput?.value || '';
        return {
            display_name: document.getElementById('display_name')?.value || '',
            country: document.getElementById('country')?.value || '',
            company_name: document.getElementById('company_name')?.value || '',
            phone_country_iso: phoneNumber.trim() ? (phoneCountrySelect?.value || '') : '',
            phone_number: phoneNumber
        };
    }

    // Store initial values
    const initialValues = getFormValues();

    // Check if form has changes
    function checkFormChanges() {
        const currentValues = getFormValues();

        const hasChanges = Object.keys(initialValues).some(key =>
            initialValues[key] !== currentValues[key]
        );

        const isPhoneValid = validatePhone(currentValues.phone_country_iso, currentValues.phone_number);

        submitBtn.disabled = !hasChanges || !isPhoneValid;
    }

    function validatePhone(countryIso, phoneNumber) {
        const normalized = phoneNumber.trim();
        if (!normalized) {
            return true;
        }

        if (!countryIso || !phoneCountrySelect || phoneCountrySelect.selectedIndex < 0) {
            return false;
        }

        const selectedOption = phoneCountrySelect.options[phoneCountrySelect.selectedIndex];
        const dialCode = selectedOption.dataset.dialCode || '';
        const digitsOnly = (dialCode + normalized).replace(/\D/g, '');
        return /^[\d\s\-()]{4,20}$/.test(normalized)
            && digitsOnly.length >= 6
            && digitsOnly.length <= 15;
    }

    function updatePhoneError() {
        const phoneNumber = phoneInput?.value || '';
        const isValid = validatePhone(phoneCountrySelect?.value || '', phoneNumber);
        phoneError.hidden = !phoneNumber || isValid;
        phoneInput?.classList.toggle('is-invalid', !!phoneNumber && !isValid);
        phoneCountrySelect?.classList.toggle('is-invalid', !!phoneNumber && !isValid);
    }

    phoneInput?.addEventListener('blur', updatePhoneError);

    phoneInput?.addEventListener('input', function() {
        this.value = this.value.replace(/[^\d\s\-()]/g, '');
        if (!phoneError.hidden) {
            updatePhoneError();
        }
        checkFormChanges();
    });

    phoneCountrySelect?.addEventListener('change', function() {
        updatePhoneError();
        checkFormChanges();
    });

    // Add event listeners to form fields
    document.getElementById('display_name')?.addEventListener('input', checkFormChanges);
    document.getElementById('company_name')?.addEventListener('input', checkFormChanges);

    // Watch for country changes (since it's updated via modal)
    const countryObserver = new MutationObserver(checkFormChanges);
    if (countryInput) {
        countryObserver.observe(countryInput, { attributes: true, attributeFilter: ['value'] });
    }

    // Also check when country is selected from modal
    const originalCloseModal = closeModal;
    closeModal = function() {
        originalCloseModal();
        checkFormChanges();
    };
})();
</script>

<?php wp_footer(); ?>

<?php get_template_part('template-parts/toast'); ?>

<?php if ($message) : ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof ippgiToast !== 'undefined') {
        ippgiToast.show('<?php echo esc_js($message); ?>', '<?php echo esc_js($message_type); ?>');
    }
});
</script>
<?php endif; ?>

</body>
</html>
