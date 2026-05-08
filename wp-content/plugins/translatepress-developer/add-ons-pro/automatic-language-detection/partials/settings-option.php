<?php
    if ( !defined('ABSPATH' ) )
        exit();
?>

<div class="trp-settings-container trp-settings-container-ald_settings">
    <h2 class="trp-settings-primary-heading"><?php esc_html_e( 'User Language Detection Method', 'translatepress-multilingual' ); ?></h2>
    <div class="trp-settings-separator"></div>

    <div class='trp-settings-options__wrapper'>
        <div class="trp-radio__wrapper trp-settings-options-item">
            <?php foreach ( $detection_methods as $value => $label ) : ?>
                <label class="trp-primary-text">
                    <input type="radio" name="trp_ald_settings[detection-method]" value="<?php echo esc_attr( $value ); ?>"
                        <?php checked( $ald_settings['detection-method'], $value ); ?>>
                    <?php echo esc_html( $label ); ?>
                </label>
            <?php endforeach; ?>
        </div>

        <span class="trp-description-text">
            <?php echo wp_kses_post( __( "Select how the language should be detected for first time visitors.<br>The visitor's last displayed language will be remembered through cookies." , 'translatepress-multilingual' ) ); ?>
        </span>
        <?php if ( !empty( $ip_warning_message ) ) : ?>
            <div class="trp-settings-warning"><?php echo $ip_warning_message;//phpcs:ignore  ?></div>
        <?php endif; ?>
    </div>
</div>

<div class="trp-settings-container trp-settings-container-ald_settings">
    <h2 class="trp-settings-primary-heading"><?php esc_html_e( 'User Notification Popup', 'translatepress-multilingual' ); ?></h2>
    <div class="trp-settings-separator"></div>

    <div class='trp-settings-options__wrapper'>
        <span class="trp-description-text">
            <?php echo esc_html__( "A popup appears asking the user if they want to be redirected." , 'translatepress-multilingual' ); ?>
        </span>

        <div class="trp-radio__wrapper trp-settings-options-item">
            <span class="trp-primary-text-bold"><?php esc_html_e( 'Popup Type', 'translatepress-multilingual' ); ?></span>
            <?php foreach ( $popup_type as $value => $label ) : ?>
                <label class="trp-primary-text">
                    <input type="radio" name="trp_ald_settings[popup_type]" value="<?php echo esc_attr( $value ); ?>"
                        <?php checked( $ald_settings['popup_type'], $value ); ?>>
                    <?php echo esc_html( $label ); ?>
                </label>
            <?php endforeach; ?>
        </div>

        <div class="trp-settings-options-item trp-settings-options-item__column trp-option__wrapper">
            <span class="trp-primary-text-bold"><?php esc_html_e( 'Popup Text', 'translatepress-multilingual' ); ?></span>
            <textarea class="trp-textarea-small" name="trp_ald_settings[popup_textarea]"><?php echo $setting_option['popup_textarea'] // phpcs:ignore?></textarea>

            <span class="trp-description-text">
                <?php echo wp_kses_post( __( "The same text is displayed in all languages. <br>A selecting language switcher will be appended to the pop-up. The detected language is pre-selected." , 'translatepress-multilingual' ) ); ?>
            </span>
        </div>

        <div class="trp-settings-options-item trp-settings-options-item__column trp-option__wrapper">
            <span class="trp-primary-text-bold"><?php esc_html_e( 'Button Text', 'translatepress-multilingual' ); ?></span>
            <input type="text" id="trp-popup-textarea_button" name="trp_ald_settings[popup_textarea_button]" value="<?php echo $setting_option['popup_textarea_button'] // phpcs:ignore?>">

            <span class="trp-description-text">
                <?php echo esc_html__( "Write the text you wish to appear on the button.." , 'translatepress-multilingual' ); ?>
            </span>
        </div>

        <div class="trp-settings-options-item trp-settings-options-item__column trp-option__wrapper">
            <span class="trp-primary-text-bold"><?php esc_html_e( 'Close Button Text', 'translatepress-multilingual' ); ?></span>
            <input type="text" id="trp-popup-textarea_close_button" name="trp_ald_settings[popup_textarea_close_button]" value="<?php echo $setting_option['popup_textarea_close_button'] // phpcs:ignore?>">

            <span class="trp-description-text">
                <?php echo esc_html__( "Write the text you wish to appear on the close button. Leave empty for just the close button." , 'translatepress-multilingual' ); ?>
            </span>
        </div>
    </div>
</div>