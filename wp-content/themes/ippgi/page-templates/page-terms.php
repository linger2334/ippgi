<?php
/**
 * Template Name: Terms Page
 *
 * @package IPPGI
 * @since 1.0.0
 */

get_header();
?>

<main id="main-content" class="site-main">
    <div class="container">
        <article class="legal-page">
            <header class="legal-header">
                <h1 class="legal-header__title"><?php esc_html_e('Terms & Conditions', 'ippgi'); ?></h1>
            </header>

            <div class="legal-content">
                <?php
                if (have_posts()) {
                    while (have_posts()) {
                        the_post();
                        the_content();
                    }
                } else {
                    // Default content if page has no content
                    ?>
                    <h2><?php esc_html_e('1. Acceptance of Terms', 'ippgi'); ?></h2>
                    <p><?php esc_html_e('By accessing and using IPPGI ("the Service"), you accept and agree to be bound by the terms and provision of this agreement.', 'ippgi'); ?></p>

                    <h2><?php esc_html_e('2. Description of Service', 'ippgi'); ?></h2>
                    <p><?php esc_html_e('IPPGI provides raw material price information and market insights. The Service includes both free and premium subscription options.', 'ippgi'); ?></p>

                    <h2><?php esc_html_e('3. User Accounts', 'ippgi'); ?></h2>
                    <p><?php esc_html_e('To access certain features of the Service, you may be required to create an account. You are responsible for maintaining the confidentiality of your account credentials.', 'ippgi'); ?></p>

                    <h2><?php esc_html_e('4. Subscription and Payment', 'ippgi'); ?></h2>
                    <p><?php esc_html_e('Premium features require a paid subscription. Subscription fees are billed in advance on a monthly or annual basis. You may cancel your subscription at any time.', 'ippgi'); ?></p>

                    <h2><?php esc_html_e('5. Data Accuracy', 'ippgi'); ?></h2>
                    <p><?php esc_html_e('While we strive to provide accurate price information, IPPGI makes no warranties regarding the accuracy, completeness, or reliability of any data presented. Price information is for reference only and should not be used as the sole basis for business decisions.', 'ippgi'); ?></p>

                    <h2><?php esc_html_e('6. Limitation of Liability', 'ippgi'); ?></h2>
                    <p><?php esc_html_e('IPPGI shall not be liable for any indirect, incidental, special, consequential, or punitive damages resulting from your use of the Service.', 'ippgi'); ?></p>

                    <h2><?php esc_html_e('7. Changes to Terms', 'ippgi'); ?></h2>
                    <p><?php esc_html_e('We reserve the right to modify these terms at any time. We will notify users of any material changes via email or through the Service.', 'ippgi'); ?></p>

                    <h2><?php esc_html_e('8. Contact', 'ippgi'); ?></h2>
                    <p><?php esc_html_e('If you have any questions about these Terms, please contact us at support@ippgi.com.', 'ippgi'); ?></p>
                    <?php
                }
                ?>
            </div>
        </article>
    </div>
</main>

<?php
get_footer();
