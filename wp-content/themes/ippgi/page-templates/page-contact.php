<?php
/**
 * Template Name: Contact Page
 *
 * @package IPPGI
 * @since 1.0.0
 */

get_header();
?>

<main id="main-content" class="site-main">
    <div class="container">
        <article class="contact-page">
            <header class="legal-header">
                <h1 class="legal-header__title"><?php esc_html_e('Contact Us', 'ippgi'); ?></h1>
            </header>

            <div class="contact-content">
                <!-- Australia Office -->
                <div class="contact-office">
                    <div class="contact-office__header">
                        <?php esc_html_e('Australia Office', 'ippgi'); ?>
                    </div>

                    <div class="contact-office__body">
                        <div class="contact-info-item">
                            <span class="contact-info-item__label"><?php esc_html_e('Address:', 'ippgi'); ?></span>
                            <p class="contact-info-item__value">'1910' 160 GROTE STREET ADELAIDE SA 5000</p>
                        </div>

                        <div class="contact-info-item">
                            <span class="contact-info-item__label"><?php esc_html_e('Email:', 'ippgi'); ?></span>
                            <p class="contact-info-item__value contact-info-item__value--email">
                                <a href="mailto:inquiries@ppgi.cn">inquiries@ppgi.cn</a>
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="2" y="4" width="20" height="16" rx="2"></rect>
                                    <path d="M22 6l-10 7L2 6"></path>
                                </svg>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </article>
    </div>
</main>

<?php
get_footer();
