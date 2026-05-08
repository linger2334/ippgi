/**
 * TranslatePress Multiple Domains Admin JavaScript
 */
(function($) {
    'use strict';

    $(document).ready(function() {

        /**
         * Handle use current domain button click (using event delegation for dynamic elements)
         */
        $(document).on('click', '.trp-multiple-domains-use-current', function() {
            const $input = $(this).siblings('.trp-multiple-domains-input');

            // Only populate if input is empty
            if (!$input.val().trim() && trpMultipleDomainsData.currentDomain) {
                $input.val(trpMultipleDomainsData.currentDomain).trigger('input');
            }
        });

        /**
         * Handle DNS check button clicks (using event delegation for dynamic elements)
         */
        $(document).on('click', '.trp-multiple-domains-check-dns', function() {
            const $button = $(this);
            const $inlineSection = $button.closest('.trp-multiple-domains-inline-section');
            const $input = $inlineSection.find('.trp-multiple-domains-input');
            const domain = $input.val().trim().replace(/\/+$/, '');
            const $notification = $inlineSection.find('.trp-multiple-domains-notification');
            const $notificationMessage = $notification.find('.trp-multiple-domains-notification-message');

            // Validate domain input
            if (!domain) {
                showNotification($notification, $notificationMessage, 'error', 'Please enter a domain name.');
                return;
            }

            // Basic domain validation - accepts domain with or without protocol
            // Examples: 'https://example.com', 'http://example.com:8080', 'example.com'
            const domainRegex = /^(https?:\/\/)?([a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}(:\d{1,5})?$/;
            if (!domainRegex.test(domain)) {
                showNotification($notification, $notificationMessage, 'error', 'Please enter a valid domain name (e.g., https://example.com).');
                return;
            }

            // Check for duplicate domains - compare against other inputs on the page
            const currentLanguage = $input.data('language');
            const normalizedDomain = normalizeDomainForComparison(domain);
            const duplicateLanguage = findDuplicateDomain(normalizedDomain, currentLanguage);
            if (duplicateLanguage) {
                showNotification($notification, $notificationMessage, 'error', trpMultipleDomainsData.strings.duplicateDomain);
                return;
            }

            // Show checking state
            $button.prop('disabled', true).addClass('checking');
            showNotification($notification, $notificationMessage, 'info', trpMultipleDomainsData.strings.checking);

            // AJAX call to check if domain is reachable
            $.ajax({
                url: trpMultipleDomainsData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'trp_check_domain_dns',
                    nonce: trpMultipleDomainsData.nonce,
                    domain: domain,
                    language: $input.data('language')
                },
                success: function(response) {
                    $button.prop('disabled', false).removeClass('checking');

                    if (response.success) {
                        showNotification($notification, $notificationMessage, 'success', response.data.message);
                    } else {
                        showNotification($notification, $notificationMessage, 'error', response.data.message || trpMultipleDomainsData.strings.error);
                    }
                },
                error: function() {
                    $button.prop('disabled', false).removeClass('checking');
                    showNotification($notification, $notificationMessage, 'error', trpMultipleDomainsData.strings.error);
                }
            });
        });

        /**
         * Show notification with specific type
         */
        function showNotification($notification, $message, type, text) {
            // Remove all type classes
            $notification.removeClass('trp-multiple-domains-success trp-multiple-domains-error trp-multiple-domains-info');

            // Add the new type class
            $notification.addClass('trp-multiple-domains-' + type);

            // Set the message
            $message.text(text);

            // Show the notification with animation
            if (!$notification.is(':visible')) {
                $notification.slideDown(200);
            }
        }

        /**
         * Normalize domain for comparison (lowercase, ensure protocol)
         */
        function normalizeDomainForComparison(domain) {
            let normalized = domain.toLowerCase().trim().replace(/\/+$/, '');
            // Add https:// if no protocol
            if (!normalized.match(/^https?:\/\//)) {
                normalized = 'https://' + normalized;
            }
            return normalized;
        }

        /**
         * Find if domain is already used by another language
         * Checks both current page inputs and saved mappings
         * Returns the language code if duplicate found, null otherwise
         */
        function findDuplicateDomain(normalizedDomain, currentLanguage) {
            // Check against the main site domain (default language)
            if (trpMultipleDomainsData.currentDomain) {
                const normalizedMain = normalizeDomainForComparison(trpMultipleDomainsData.currentDomain);
                if (normalizedDomain === normalizedMain) {
                    return 'main';
                }
            }

            // Check all domain inputs on the page (handles unsaved changes)
            let duplicateFound = null;
            $('.trp-multiple-domains-input').each(function() {
                const $otherInput = $(this);
                const otherLanguage = $otherInput.data('language');
                const otherDomain = $otherInput.val().trim().replace(/\/+$/, '');

                if (otherLanguage !== currentLanguage && otherDomain) {
                    const normalizedOther = normalizeDomainForComparison(otherDomain);
                    if (normalizedOther === normalizedDomain) {
                        duplicateFound = otherLanguage;
                        return false; // Break the each loop
                    }
                }
            });

            return duplicateFound;
        }

        /**
         * Clear notification on domain input change (using event delegation for dynamic elements)
         */
        $(document).on('input', '.trp-multiple-domains-input', function() {
            const $inlineSection = $(this).closest('.trp-multiple-domains-inline-section');
            const $notification = $inlineSection.find('.trp-multiple-domains-notification');

            // Hide notification when user starts typing
            if ($notification.is(':visible')) {
                $notification.slideUp(200);
            }
        });

        /**
         * Update Multiple Domains fields when a new language is added
         * Listens for the "Add Language" button click and updates the cloned row's fields
         */
        $(document).on('click', '#trp-add-language', function() {
            // Use setTimeout to allow the core script to create the new row first
            setTimeout(function() {
                updateNewLanguageRow();
            }, 100);
        });

        /**
         * Update the Multiple Domains fields in the most recently added language row
         */
        function updateNewLanguageRow() {
            // Get the last language row (the newly added one)
            const $newRow = $('#trp-sortable-languages .trp-language').last();
            if (!$newRow.length) {
                return;
            }

            // Get the new language code from the row
            const newLanguage = $newRow.find('.trp-language-code').val();
            if (!newLanguage) {
                return;
            }

            // Update domain toggle input
            const $domainToggle = $newRow.find('.trp-multiple-domains-enable-toggle');
            if ($domainToggle.length) {
                const newToggleId = 'trp-multiple-domains-toggle-' + newLanguage;

                $domainToggle.attr('name', 'trp_settings[trp-multiple-domains][' + newLanguage + '][enabled]');
                $domainToggle.attr('data-language', newLanguage);
                $domainToggle.attr('id', newToggleId);
                $domainToggle.val('1'); // Reset value to 1 (core script changes it to language code)
                $domainToggle.prop('checked', false);
                $domainToggle.prop('disabled', false);
                $domainToggle.removeAttr('disabled');

                // Update the label's for attribute - find it as sibling within .trp-switch
                $domainToggle.siblings('label.trp-switch-label').attr('for', newToggleId);
            }

            // Update domain input
            const $domainInput = $newRow.find('.trp-multiple-domains-input');
            if ($domainInput.length) {
                $domainInput.attr('name', 'trp_settings[trp-multiple-domains][' + newLanguage + '][domain]');
                $domainInput.attr('data-language', newLanguage);
                $domainInput.val('');
            }

            // Update data-language on buttons and notification
            $newRow.find('.trp-multiple-domains-use-current').attr('data-language', newLanguage);
            $newRow.find('.trp-multiple-domains-check-dns').attr('data-language', newLanguage);
            $newRow.find('.trp-multiple-domains-notification').attr('data-language', newLanguage);
        }

    });

})(jQuery);
