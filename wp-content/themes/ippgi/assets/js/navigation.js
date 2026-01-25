/**
 * IPPGI Navigation JavaScript
 * Handles navigation-specific interactions
 *
 * @package IPPGI
 */

(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        initDropdownMenus();
        initSearchToggle();
        initActiveNavLinks();
    });

    /**
     * Initialize dropdown menus
     */
    function initDropdownMenus() {
        const dropdowns = document.querySelectorAll('.has-dropdown');

        dropdowns.forEach(function(dropdown) {
            const trigger = dropdown.querySelector('.dropdown-trigger');
            const menu = dropdown.querySelector('.dropdown-menu');

            if (!trigger || !menu) return;

            // Desktop: hover behavior
            if (window.matchMedia('(min-width: 1024px)').matches) {
                dropdown.addEventListener('mouseenter', function() {
                    menu.classList.add('is-active');
                });

                dropdown.addEventListener('mouseleave', function() {
                    menu.classList.remove('is-active');
                });
            }

            // Mobile/Touch: click behavior
            trigger.addEventListener('click', function(e) {
                e.preventDefault();
                menu.classList.toggle('is-active');

                // Close other dropdowns
                dropdowns.forEach(function(otherDropdown) {
                    if (otherDropdown !== dropdown) {
                        otherDropdown.querySelector('.dropdown-menu')?.classList.remove('is-active');
                    }
                });
            });
        });

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.has-dropdown')) {
                document.querySelectorAll('.dropdown-menu').forEach(function(menu) {
                    menu.classList.remove('is-active');
                });
            }
        });
    }

    /**
     * Initialize search toggle
     * Note: Main search functionality is handled by initSearchOverlay in main.js
     * This function is kept for backward compatibility with inline search forms
     */
    function initSearchToggle() {
        const searchBtn = document.querySelector('.header-search-btn');
        const searchForm = document.querySelector('.header-search-form');
        const searchInput = document.querySelector('.header-search-input');

        if (!searchBtn || !searchForm) return;

        searchBtn.addEventListener('click', function(e) {
            e.preventDefault();

            searchForm.classList.toggle('is-active');

            if (searchForm.classList.contains('is-active')) {
                searchInput?.focus();
            }
        });

        // Close search on escape
        searchInput?.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                searchForm?.classList.remove('is-active');
            }
        });
    }

    /**
     * Highlight active navigation links based on current URL
     */
    function initActiveNavLinks() {
        const currentPath = window.location.pathname;
        const navLinks = document.querySelectorAll('.header-nav__link, .mobile-menu__link');

        navLinks.forEach(function(link) {
            const linkPath = link.getAttribute('href');

            if (linkPath === currentPath ||
                (linkPath !== '/' && currentPath.startsWith(linkPath))) {
                link.classList.add('is-active');
            }
        });
    }

    /**
     * Tab navigation for price categories
     */
    window.initPriceTabs = function() {
        const tabs = document.querySelectorAll('.price-tab');
        const panels = document.querySelectorAll('.price-panel');

        tabs.forEach(function(tab) {
            tab.addEventListener('click', function(e) {
                e.preventDefault();

                const targetId = this.dataset.target;

                // Update tabs
                tabs.forEach(function(t) {
                    t.classList.remove('is-active');
                    t.setAttribute('aria-selected', 'false');
                });
                this.classList.add('is-active');
                this.setAttribute('aria-selected', 'true');

                // Update panels
                panels.forEach(function(panel) {
                    if (panel.id === targetId) {
                        panel.classList.add('is-active');
                        panel.removeAttribute('hidden');
                    } else {
                        panel.classList.remove('is-active');
                        panel.setAttribute('hidden', '');
                    }
                });
            });

            // Keyboard navigation
            tab.addEventListener('keydown', function(e) {
                let newIndex;
                const tabArray = Array.from(tabs);
                const currentIndex = tabArray.indexOf(this);

                if (e.key === 'ArrowRight') {
                    newIndex = (currentIndex + 1) % tabs.length;
                } else if (e.key === 'ArrowLeft') {
                    newIndex = (currentIndex - 1 + tabs.length) % tabs.length;
                } else if (e.key === 'Home') {
                    newIndex = 0;
                } else if (e.key === 'End') {
                    newIndex = tabs.length - 1;
                }

                if (newIndex !== undefined) {
                    e.preventDefault();
                    tabArray[newIndex].click();
                    tabArray[newIndex].focus();
                }
            });
        });
    };

    /**
     * Breadcrumb navigation
     */
    window.initBreadcrumbs = function() {
        const breadcrumbs = document.querySelector('.breadcrumbs');
        if (!breadcrumbs) return;

        // Check if breadcrumbs overflow and add scroll indicator
        if (breadcrumbs.scrollWidth > breadcrumbs.clientWidth) {
            breadcrumbs.classList.add('is-scrollable');

            // Scroll to end to show current page
            breadcrumbs.scrollLeft = breadcrumbs.scrollWidth;
        }
    };

})();
