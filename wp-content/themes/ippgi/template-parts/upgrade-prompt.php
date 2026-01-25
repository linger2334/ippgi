<?php
/**
 * Upgrade Prompt
 *
 * Bottom floating prompt encouraging users to upgrade to Plus.
 *
 * @package IPPGI
 * @since 1.0.0
 */

$subscribe_url = ippgi_get_subscribe_url();
?>

<div class="upgrade-prompt" id="upgrade-prompt">
    <button type="button" class="upgrade-prompt__close" aria-label="<?php esc_attr_e('Dismiss', 'ippgi'); ?>">×</button>
    <div class="upgrade-prompt__content">
        <div class="upgrade-prompt__title"><?php esc_html_e('Plus', 'ippgi'); ?></div>
        <div class="upgrade-prompt__text"><?php esc_html_e('Full access to all product prices.', 'ippgi'); ?></div>
    </div>
    <button type="button" class="upgrade-prompt__action" data-subscribe-url="<?php echo esc_url($subscribe_url); ?>">
        <?php esc_html_e('Upgrade', 'ippgi'); ?>
    </button>
</div>
