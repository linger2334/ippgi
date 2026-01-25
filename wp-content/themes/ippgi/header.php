<?php
/**
 * The header template
 *
 * @package IPPGI
 * @since 1.0.0
 */
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="profile" href="https://gmpg.org/xfn/11">
    <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="skip-link" href="#main-content"><?php esc_html_e('Skip to content', 'ippgi'); ?></a>

<!-- Top Utility Bar -->
<div class="top-bar">
    <div class="container">
        <div class="top-bar__inner">
            <a href="<?php echo esc_url(home_url('/contact-us')); ?>" class="top-bar__support">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                    <polyline points="22,6 12,13 2,6"></polyline>
                </svg>
                <span><?php esc_html_e('Support', 'ippgi'); ?></span>
            </a>

            <div class="top-bar__right">
                <!-- Language Selector -->
                <div class="language-selector">
                    <button type="button" class="language-selector__trigger" aria-expanded="false">
                        <img src="<?php echo esc_url(IPPGI_THEME_URI . '/assets/images/flag-uk.svg'); ?>" alt="English" width="20" height="14" class="language-selector__flag">
                        <span><?php esc_html_e('English', 'ippgi'); ?></span>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </button>
                </div>

                <!-- Login/User -->
                <?php if (!is_user_logged_in()) : ?>
                    <button type="button" class="top-bar__login" id="login-trigger-mobile" aria-haspopup="dialog">
                        <?php esc_html_e('LOGIN', 'ippgi'); ?>
                    </button>
                <?php else : ?>
                    <a href="<?php echo esc_url(ippgi_get_profile_url()); ?>" class="top-bar__user">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                        <span><?php echo esc_html(wp_get_current_user()->display_name ?: wp_get_current_user()->user_login); ?>...</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<header class="site-header" role="banner">
    <div class="site-header__inner container">
        <!-- Logo -->
        <div class="site-logo">
            <?php if (has_custom_logo()) : ?>
                <?php the_custom_logo(); ?>
            <?php else : ?>
                <a href="<?php echo esc_url(home_url('/')); ?>" class="site-logo__text">
                    <?php bloginfo('name'); ?>
                </a>
            <?php endif; ?>
        </div>

        <!-- Inline Search Form - PC only (shown when search is active) -->
        <form class="header-search-inline" id="header-search-inline" onsubmit="return false;" hidden>
            <input type="search"
                   class="header-search-inline__input"
                   name="s"
                   placeholder="<?php esc_attr_e('Search...', 'ippgi'); ?>"
                   autocomplete="off">
            <button type="button" class="header-search-inline__clear" aria-label="<?php esc_attr_e('Clear', 'ippgi'); ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                    <circle cx="12" cy="12" r="10" fill="#ccc"></circle>
                    <line x1="15" y1="9" x2="9" y2="15" stroke="#fff" stroke-width="2.5"></line>
                    <line x1="9" y1="9" x2="15" y2="15" stroke="#fff" stroke-width="2.5"></line>
                </svg>
            </button>
            <button type="button" class="header-search-inline__submit" aria-label="<?php esc_attr_e('Search', 'ippgi'); ?>">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#4b4b4b" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="10" cy="10" r="7"></circle>
                    <line x1="20" y1="20" x2="15.5" y2="15.5"></line>
                </svg>
            </button>
        </form>

        <!-- Desktop Navigation -->
        <?php get_template_part('template-parts/header', 'desktop'); ?>

        <!-- Header Actions -->
        <div class="header-actions">
            <!-- Search Close Button (shown when search is active) -->
            <button type="button" class="header-search-close" id="header-search-close" aria-label="<?php esc_attr_e('Close search', 'ippgi'); ?>" hidden>
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>

            <!-- Search Button -->
            <button type="button" class="header-search-btn" id="header-search-btn" aria-label="<?php esc_attr_e('Search', 'ippgi'); ?>">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="10" cy="10" r="7"></circle>
                    <line x1="20" y1="20" x2="15.5" y2="15.5"></line>
                </svg>
            </button>

            <!-- Login Button (Desktop) -->
            <?php if (!is_user_logged_in()) : ?>
                <button type="button" class="header-login-btn" id="login-trigger" aria-haspopup="dialog">
                    <?php esc_html_e('LOGIN', 'ippgi'); ?>
                </button>
            <?php else : ?>
                <a href="<?php echo esc_url(ippgi_get_profile_url()); ?>" class="header-login-btn header-login-btn--user">
                    <?php echo esc_html(wp_get_current_user()->user_email); ?>
                </a>
            <?php endif; ?>

            <!-- Mobile Menu Button -->
            <button type="button" class="header-menu-btn" aria-label="<?php esc_attr_e('Open menu', 'ippgi'); ?>" aria-expanded="false">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="3" y1="12" x2="21" y2="12"></line>
                    <line x1="3" y1="6" x2="21" y2="6"></line>
                    <line x1="3" y1="18" x2="21" y2="18"></line>
                </svg>
            </button>
        </div>
    </div>

    <!-- Mobile Search Dropdown (appears below header on mobile) -->
    <div class="header-search-dropdown" id="header-search-dropdown" hidden>
        <div class="container">
            <form class="header-search-dropdown__form" onsubmit="return false;">
                <input type="search"
                       class="header-search-dropdown__input"
                       name="s"
                       placeholder="<?php esc_attr_e('Search...', 'ippgi'); ?>"
                       autocomplete="off">
                <button type="button" class="header-search-dropdown__clear" aria-label="<?php esc_attr_e('Clear', 'ippgi'); ?>">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <circle cx="12" cy="12" r="10" fill="#ccc"></circle>
                        <line x1="15" y1="9" x2="9" y2="15" stroke="#fff" stroke-width="2.5"></line>
                        <line x1="9" y1="9" x2="15" y2="15" stroke="#fff" stroke-width="2.5"></line>
                    </svg>
                </button>
                <button type="button" class="header-search-dropdown__submit" aria-label="<?php esc_attr_e('Search', 'ippgi'); ?>">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#4b4b4b" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="10" cy="10" r="7"></circle>
                        <line x1="20" y1="20" x2="15.5" y2="15.5"></line>
                    </svg>
                </button>
            </form>
        </div>
    </div>

    <!-- Mobile Menu Dropdown (appears below header on mobile) -->
    <?php get_template_part('template-parts/header', 'mobile'); ?>
</header>

<!-- Announcement Banner -->
<?php get_template_part('template-parts/announcement', 'banner'); ?>
