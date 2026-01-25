<?php
/**
 * Template Name: Privacy Policy Page
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
                <h1 class="legal-header__title"><?php esc_html_e('Privacy Policy', 'ippgi'); ?></h1>
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
                    <h2><?php esc_html_e('1. Information We Collect', 'ippgi'); ?></h2>
                    <p><?php esc_html_e('We collect information you provide directly to us, such as when you create an account, subscribe to our service, or contact us for support.', 'ippgi'); ?></p>
                    <ul>
                        <li><?php esc_html_e('Account information (name, email address)', 'ippgi'); ?></li>
                        <li><?php esc_html_e('Payment information (processed securely by PayPal)', 'ippgi'); ?></li>
                        <li><?php esc_html_e('Usage data (pages visited, features used)', 'ippgi'); ?></li>
                    </ul>

                    <h2><?php esc_html_e('2. How We Use Your Information', 'ippgi'); ?></h2>
                    <p><?php esc_html_e('We use the information we collect to:', 'ippgi'); ?></p>
                    <ul>
                        <li><?php esc_html_e('Provide, maintain, and improve our services', 'ippgi'); ?></li>
                        <li><?php esc_html_e('Process transactions and send related information', 'ippgi'); ?></li>
                        <li><?php esc_html_e('Send you technical notices and support messages', 'ippgi'); ?></li>
                        <li><?php esc_html_e('Respond to your comments and questions', 'ippgi'); ?></li>
                    </ul>

                    <h2><?php esc_html_e('3. Information Sharing', 'ippgi'); ?></h2>
                    <p><?php esc_html_e('We do not sell, trade, or otherwise transfer your personal information to third parties except as described in this policy. We may share information with:', 'ippgi'); ?></p>
                    <ul>
                        <li><?php esc_html_e('Service providers who assist in our operations', 'ippgi'); ?></li>
                        <li><?php esc_html_e('Legal authorities when required by law', 'ippgi'); ?></li>
                    </ul>

                    <h2><?php esc_html_e('4. Data Security', 'ippgi'); ?></h2>
                    <p><?php esc_html_e('We implement appropriate security measures to protect your personal information. However, no method of transmission over the Internet is 100% secure.', 'ippgi'); ?></p>

                    <h2><?php esc_html_e('5. Cookies', 'ippgi'); ?></h2>
                    <p><?php esc_html_e('We use cookies and similar technologies to collect information about your browsing activities. You can control cookies through your browser settings.', 'ippgi'); ?></p>

                    <h2><?php esc_html_e('6. Your Rights', 'ippgi'); ?></h2>
                    <p><?php esc_html_e('You have the right to:', 'ippgi'); ?></p>
                    <ul>
                        <li><?php esc_html_e('Access your personal data', 'ippgi'); ?></li>
                        <li><?php esc_html_e('Correct inaccurate data', 'ippgi'); ?></li>
                        <li><?php esc_html_e('Request deletion of your data', 'ippgi'); ?></li>
                        <li><?php esc_html_e('Export your data', 'ippgi'); ?></li>
                    </ul>

                    <h2><?php esc_html_e('7. Contact Us', 'ippgi'); ?></h2>
                    <p><?php esc_html_e('If you have any questions about this Privacy Policy, please contact us at privacy@ippgi.com.', 'ippgi'); ?></p>
                    <?php
                }
                ?>
            </div>
        </article>
    </div>
</main>

<?php
get_footer();
