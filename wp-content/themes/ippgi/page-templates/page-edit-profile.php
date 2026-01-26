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

// Handle form submission
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ippgi_edit_profile_nonce'])) {
    if (wp_verify_nonce($_POST['ippgi_edit_profile_nonce'], 'ippgi_edit_profile')) {
        // Sanitize and update user data
        $display_name = sanitize_text_field($_POST['display_name'] ?? '');
        $country = sanitize_text_field($_POST['country'] ?? '');
        $company = sanitize_text_field($_POST['company_name'] ?? '');
        $phone = sanitize_text_field($_POST['phone'] ?? '');

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

        // Refresh user data
        $current_user = wp_get_current_user();
        $user_country = $country;
        $user_company = $company;
        $user_phone = $phone;

        $message = __('Profile updated successfully.', 'ippgi');
        $message_type = 'success';
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
                    <label class="edit-profile-field__label" for="phone">
                        <?php esc_html_e('Mobile Number:', 'ippgi'); ?>
                    </label>
                    <input type="tel" id="phone" name="phone"
                           class="edit-profile-field__input"
                           value="<?php echo esc_attr($user_phone); ?>"
                           pattern="^\+?[0-9\s\-]{6,20}$"
                           title="<?php esc_attr_e('Please enter a valid phone number (e.g., +86 13812345678 or 13812345678)', 'ippgi'); ?>">
                    <span class="edit-profile-field__error" id="phone-error" style="display: none; color: #e53935; font-size: 12px; margin-top: 4px;">
                        <?php esc_html_e('Please enter a valid phone number', 'ippgi'); ?>
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
            <!-- Countries will be populated by JavaScript -->
        </div>
    </div>
</div>

<script>
(function() {
    // Complete list of countries
    const countries = [
        "Afghanistan", "Albania", "Algeria", "Andorra", "Angola", "Antigua and Barbuda",
        "Argentina", "Armenia", "Australia", "Austria", "Azerbaijan", "Bahamas", "Bahrain",
        "Bangladesh", "Barbados", "Belarus", "Belgium", "Belize", "Benin", "Bhutan",
        "Bolivia", "Bosnia and Herzegovina", "Botswana", "Brazil", "Brunei", "Bulgaria",
        "Burkina Faso", "Burundi", "Cabo Verde", "Cambodia", "Cameroon", "Canada",
        "Central African Republic", "Chad", "Chile", "China", "Colombia", "Comoros",
        "Congo", "Costa Rica", "Croatia", "Cuba", "Cyprus", "Czech Republic",
        "Denmark", "Djibouti", "Dominica", "Dominican Republic", "Ecuador", "Egypt",
        "El Salvador", "Equatorial Guinea", "Eritrea", "Estonia", "Eswatini", "Ethiopia",
        "Fiji", "Finland", "France", "Gabon", "Gambia", "Georgia", "Germany", "Ghana",
        "Greece", "Grenada", "Guatemala", "Guinea", "Guinea-Bissau", "Guyana", "Haiti",
        "Honduras", "Hungary", "Iceland", "India", "Indonesia", "Iran", "Iraq", "Ireland",
        "Israel", "Italy", "Jamaica", "Japan", "Jordan", "Kazakhstan", "Kenya", "Kiribati",
        "Kuwait", "Kyrgyzstan", "Laos", "Latvia", "Lebanon", "Lesotho", "Liberia", "Libya",
        "Liechtenstein", "Lithuania", "Luxembourg", "Madagascar", "Malawi", "Malaysia",
        "Maldives", "Mali", "Malta", "Marshall Islands", "Mauritania", "Mauritius", "Mexico",
        "Micronesia", "Moldova", "Monaco", "Mongolia", "Montenegro", "Morocco", "Mozambique",
        "Myanmar", "Namibia", "Nauru", "Nepal", "Netherlands", "New Zealand", "Nicaragua",
        "Niger", "Nigeria", "North Korea", "North Macedonia", "Norway", "Oman", "Pakistan",
        "Palau", "Palestine", "Panama", "Papua New Guinea", "Paraguay", "Peru", "Philippines",
        "Poland", "Portugal", "Qatar", "Romania", "Russia", "Rwanda", "Saint Kitts and Nevis",
        "Saint Lucia", "Saint Vincent and the Grenadines", "Samoa", "San Marino",
        "Sao Tome and Principe", "Saudi Arabia", "Senegal", "Serbia", "Seychelles",
        "Sierra Leone", "Singapore", "Slovakia", "Slovenia", "Solomon Islands", "Somalia",
        "South Africa", "South Korea", "South Sudan", "Spain", "Sri Lanka", "Sudan",
        "Suriname", "Sweden", "Switzerland", "Syria", "Taiwan", "Tajikistan", "Tanzania",
        "Thailand", "Timor-Leste", "Togo", "Tonga", "Trinidad and Tobago", "Tunisia",
        "Turkey", "Turkmenistan", "Tuvalu", "Uganda", "Ukraine", "United Arab Emirates",
        "United Kingdom", "United States", "Uruguay", "Uzbekistan", "Vanuatu", "Vatican City",
        "Venezuela", "Vietnam", "Yemen", "Zambia", "Zimbabwe"
    ];

    const countrySelector = document.getElementById('country-selector');
    const countryModal = document.getElementById('country-modal');
    const countrySearch = document.getElementById('country-search');
    const countryList = document.getElementById('country-list');
    const countryInput = document.getElementById('country');
    const countryDisplay = document.getElementById('country-display');

    // Render country list
    function renderCountries(filter = '') {
        const filtered = filter
            ? countries.filter(c => c.toLowerCase().includes(filter.toLowerCase()))
            : countries;

        countryList.innerHTML = filtered.map(country =>
            `<div class="country-modal__item" data-country="${country}">${country}</div>`
        ).join('');

        // Add click handlers
        countryList.querySelectorAll('.country-modal__item').forEach(item => {
            item.addEventListener('click', function() {
                const selected = this.dataset.country;
                countryInput.value = selected;
                countryDisplay.textContent = selected;
                closeModal();
            });
        });
    }

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

    // Store initial values
    const initialValues = {
        display_name: document.getElementById('display_name')?.value || '',
        country: document.getElementById('country')?.value || '',
        company_name: document.getElementById('company_name')?.value || '',
        phone: document.getElementById('phone')?.value || ''
    };

    // Check if form has changes
    function checkFormChanges() {
        const currentValues = {
            display_name: document.getElementById('display_name')?.value || '',
            country: document.getElementById('country')?.value || '',
            company_name: document.getElementById('company_name')?.value || '',
            phone: document.getElementById('phone')?.value || ''
        };

        const hasChanges = Object.keys(initialValues).some(key =>
            initialValues[key] !== currentValues[key]
        );

        // Also check if phone is valid (if not empty)
        const isPhoneValid = validatePhone(currentValues.phone);

        submitBtn.disabled = !hasChanges || !isPhoneValid;
    }

    // Phone validation function - supports international formats
    function validatePhone(phone) {
        if (!phone || phone.trim() === '') {
            return true; // Empty is allowed
        }
        // Allow: +, digits, spaces, hyphens, parentheses
        // Minimum 6 digits, maximum 20 characters
        const phoneRegex = /^\+?[\d\s\-()]{6,20}$/;
        // Also check that there are at least 6 actual digits
        const digitsOnly = phone.replace(/\D/g, '');
        return phoneRegex.test(phone) && digitsOnly.length >= 6 && digitsOnly.length <= 15;
    }

    // Show/hide phone error
    const phoneInput = document.getElementById('phone');
    const phoneError = document.getElementById('phone-error');

    phoneInput?.addEventListener('blur', function() {
        const isValid = validatePhone(this.value);
        if (this.value && !isValid) {
            phoneError.style.display = 'block';
            this.classList.add('is-invalid');
        } else {
            phoneError.style.display = 'none';
            this.classList.remove('is-invalid');
        }
    });

    phoneInput?.addEventListener('input', function() {
        // Only allow valid characters while typing
        this.value = this.value.replace(/[^\d\s\-+()]/g, '');
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
