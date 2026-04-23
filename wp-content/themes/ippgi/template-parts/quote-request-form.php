<?php
/**
 * Reusable Quote Request Form
 *
 * @package IPPGI
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$quote_args = isset($args) && is_array($args) ? $args : array();
$form_id = isset($quote_args['form_id']) ? sanitize_html_class((string) $quote_args['form_id']) : 'quote-request-form';
$form_source = isset($quote_args['source']) ? sanitize_key((string) $quote_args['source']) : 'homepage';
?>

<form
    class="quote-form js-quote-request-form"
    id="<?php echo esc_attr($form_id); ?>"
    novalidate
    data-success-message="<?php echo esc_attr__('Thanks, your quote request has been received. Our team will contact you soon.', 'ippgi'); ?>"
    data-error-message="<?php echo esc_attr__('Unable to submit your request right now. Please try again in a moment.', 'ippgi'); ?>"
    data-submitting-text="<?php echo esc_attr__('Submitting...', 'ippgi'); ?>"
>
    <input type="hidden" name="action" value="ippgi_submit_quote_request">
    <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('ippgi_quote_request')); ?>">
    <input type="hidden" name="source" value="<?php echo esc_attr($form_source); ?>">

    <div class="quote-form__field quote-form__field--honeypot" aria-hidden="true">
        <label for="<?php echo esc_attr($form_id); ?>_website"><?php esc_html_e('Website', 'ippgi'); ?></label>
        <input type="text" id="<?php echo esc_attr($form_id); ?>_website" name="website" tabindex="-1" autocomplete="off">
    </div>

    <div class="quote-form__field">
        <label class="screen-reader-text" for="<?php echo esc_attr($form_id); ?>_name"><?php esc_html_e('Name', 'ippgi'); ?></label>
        <input type="text" id="<?php echo esc_attr($form_id); ?>_name" name="name" class="quote-form__input" placeholder="<?php esc_attr_e('Name', 'ippgi'); ?>" value="" required>
        <span class="quote-form__required" aria-hidden="true">*</span>
    </div>

    <div class="quote-form__field">
        <label class="screen-reader-text" for="<?php echo esc_attr($form_id); ?>_contact"><?php esc_html_e('Email / WhatsApp', 'ippgi'); ?></label>
        <input type="text" id="<?php echo esc_attr($form_id); ?>_contact" name="contact" class="quote-form__input" placeholder="<?php esc_attr_e('Email / WhatsApp', 'ippgi'); ?>" value="" required>
        <span class="quote-form__required" aria-hidden="true">*</span>
    </div>

    <div class="quote-form__field">
        <label class="screen-reader-text" for="<?php echo esc_attr($form_id); ?>_company"><?php esc_html_e('Company', 'ippgi'); ?></label>
        <input type="text" id="<?php echo esc_attr($form_id); ?>_company" name="company" class="quote-form__input" placeholder="<?php esc_attr_e('Company', 'ippgi'); ?>" value="" required>
        <span class="quote-form__required" aria-hidden="true">*</span>
    </div>

    <div class="quote-form__field">
        <label class="screen-reader-text" for="<?php echo esc_attr($form_id); ?>_product"><?php esc_html_e('Steel Product of Interest', 'ippgi'); ?></label>
        <input type="text" id="<?php echo esc_attr($form_id); ?>_product" name="product_interest" class="quote-form__input" placeholder="<?php esc_attr_e('Steel Product of Interest', 'ippgi'); ?>" value="" required>
        <span class="quote-form__required" aria-hidden="true">*</span>
    </div>

    <div class="quote-form__field quote-form__field--textarea">
        <label class="screen-reader-text" for="<?php echo esc_attr($form_id); ?>_details"><?php esc_html_e('Additional Details (Optional)', 'ippgi'); ?></label>
        <textarea id="<?php echo esc_attr($form_id); ?>_details" name="details" class="quote-form__textarea" rows="5" placeholder="<?php esc_attr_e('Additional Details (Optional)', 'ippgi'); ?>"></textarea>
    </div>

    <div class="quote-form__actions">
        <button type="submit" class="quote-form__submit">
            <?php esc_html_e('Get Quote', 'ippgi'); ?>
        </button>
    </div>
</form>
