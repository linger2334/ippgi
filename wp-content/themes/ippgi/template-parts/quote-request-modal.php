<?php
/**
 * Quote Request Modal
 *
 * @package IPPGI
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="quote-modal" id="quote-request-modal" role="dialog" aria-modal="true" aria-labelledby="quote-request-title" hidden>
    <div class="quote-modal__backdrop" data-quote-modal-close></div>

    <div class="quote-modal__content">
        <button type="button" class="quote-modal__close" data-quote-modal-close aria-label="<?php esc_attr_e('Close', 'ippgi'); ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        </button>

        <h2 id="quote-request-title" class="quote-modal__title"><?php esc_html_e('Request a Quote', 'ippgi'); ?></h2>
        <p class="quote-modal__description">
            <?php esc_html_e('Submit your sourcing request and get free access to the latest market pricing for steel coils, aluminum coils, roofing sheets, plate sheets, and wall panels.', 'ippgi'); ?><br>
            <?php esc_html_e('We provide timely pricing insights to support your procurement decisions.', 'ippgi'); ?>
        </p>

        <?php
        get_template_part('template-parts/quote-request-form', null, array(
            'form_id' => 'quote-request-form',
            'source' => 'homepage',
        ));
        ?>
    </div>
</div>
