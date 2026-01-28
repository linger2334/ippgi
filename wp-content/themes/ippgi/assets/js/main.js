/**
 * IPPGI Main JavaScript
 *
 * @package IPPGI
 */

(function() {
    'use strict';

    // Global close functions for mutual exclusion
    let closeMobileMenu = null;
    let closeSearchOverlay = null;

    // Current displayed category for price table
    let currentPriceCategory = 'PPGI';

    // DOM Ready
    document.addEventListener('DOMContentLoaded', function() {
        initMobileMenu();
        initStickyHeader();
        initSmoothScroll();
        initFavoriteButtons();
        initLoginModal();
        initAnnouncementBanner();
        initSearchOverlay();
        initDatePicker();
        initBackToTop();
        initUpgradePrompt();
        initPriceCarousel();
        initPriceTableClick();
        initBannerCarousel();
    });

    /**
     * Initialize mobile menu functionality
     * Menu dropdown appears below header like search dropdown
     */
    function initMobileMenu() {
        const menuBtn = document.querySelector('.header-menu-btn');
        const mobileMenuDropdown = document.getElementById('mobile-menu-dropdown');
        const siteHeader = document.querySelector('.site-header');

        if (!menuBtn || !mobileMenuDropdown) {
            return;
        }

        let isMenuOpen = false;

        function openMenu() {
            // Close search if open
            if (closeSearchOverlay) {
                closeSearchOverlay();
            }

            isMenuOpen = true;
            mobileMenuDropdown.hidden = false;
            mobileMenuDropdown.style.display = 'block';
            siteHeader?.classList.add('is-menu-active');
            menuBtn.setAttribute('aria-expanded', 'true');

            // Change hamburger icon to X
            menuBtn.innerHTML = `
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            `;
        }

        function closeMenu() {
            isMenuOpen = false;
            mobileMenuDropdown.hidden = true;
            mobileMenuDropdown.style.display = '';
            siteHeader?.classList.remove('is-menu-active');
            menuBtn.setAttribute('aria-expanded', 'false');

            // Change X icon back to hamburger
            menuBtn.innerHTML = `
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="3" y1="12" x2="21" y2="12"></line>
                    <line x1="3" y1="6" x2="21" y2="6"></line>
                    <line x1="3" y1="18" x2="21" y2="18"></line>
                </svg>
            `;
        }

        // Expose close function globally
        closeMobileMenu = closeMenu;

        function toggleMenu() {
            if (isMenuOpen) {
                closeMenu();
            } else {
                openMenu();
            }
        }

        menuBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            toggleMenu();
        });

        // Close on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && isMenuOpen) {
                closeMenu();
            }
        });

        // Close when clicking outside
        document.addEventListener('click', function(e) {
            if (isMenuOpen && !e.target.closest('.mobile-menu-dropdown') && !e.target.closest('.header-menu-btn')) {
                closeMenu();
            }
        });
    }

    /**
     * Initialize sticky header behavior
     */
    function initStickyHeader() {
        const header = document.querySelector('.site-header');
        if (!header) return;

        let lastScrollY = window.scrollY;
        let ticking = false;

        function updateHeader() {
            const currentScrollY = window.scrollY;

            if (currentScrollY > 100) {
                header.classList.add('is-scrolled');
            } else {
                header.classList.remove('is-scrolled');
            }

            // Hide/show header on scroll direction
            if (currentScrollY > lastScrollY && currentScrollY > 200) {
                header.classList.add('is-hidden');
            } else {
                header.classList.remove('is-hidden');
            }

            lastScrollY = currentScrollY;
            ticking = false;
        }

        window.addEventListener('scroll', function() {
            if (!ticking) {
                window.requestAnimationFrame(updateHeader);
                ticking = true;
            }
        });
    }

    /**
     * Initialize smooth scroll for anchor links
     */
    function initSmoothScroll() {
        document.querySelectorAll('a[href^="#"]').forEach(function(anchor) {
            anchor.addEventListener('click', function(e) {
                const targetId = this.getAttribute('href');
                if (targetId === '#') return;

                const target = document.querySelector(targetId);
                if (target) {
                    e.preventDefault();
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    }

    /**
     * Initialize favorite buttons
     */
    function initFavoriteButtons() {
        document.querySelectorAll('.favorite-btn').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();

                const priceId = this.dataset.priceId;
                if (!priceId) return;

                // Toggle active state
                this.classList.toggle('is-active');

                // TODO: Send AJAX request to save favorite
                console.log('Toggle favorite for price:', priceId);
            });
        });
    }

    /**
     * Initialize login modal
     */
    function initLoginModal() {
        const loginTriggers = document.querySelectorAll('#login-trigger, #login-trigger-mobile');
        const loginModal = document.getElementById('login-modal');

        if (loginTriggers.length === 0) return;

        const backdrop = loginModal?.querySelector('.login-modal__backdrop');
        const closeBtn = loginModal?.querySelector('.login-modal__close');

        // Breakpoint for mobile/desktop (matches CSS media query)
        const DESKTOP_BREAKPOINT = 1024;

        function isMobile() {
            return window.innerWidth < DESKTOP_BREAKPOINT;
        }

        function getLoginUrl() {
            return (typeof ippgiData !== 'undefined' && ippgiData.loginUrl)
                ? ippgiData.loginUrl
                : '/login';
        }

        function openModal() {
            if (!loginModal) return;
            loginModal.hidden = false;
            document.body.style.overflow = 'hidden';
            // Focus first focusable element
            const firstInput = loginModal.querySelector('button, input, a');
            if (firstInput) firstInput.focus();
        }

        function closeModal() {
            if (!loginModal) return;
            loginModal.hidden = true;
            document.body.style.overflow = '';
        }

        function handleLoginClick(e) {
            if (isMobile()) {
                // Mobile: redirect to login page
                window.location.href = getLoginUrl();
            } else {
                // Desktop: open modal
                if (loginModal) {
                    openModal();
                } else {
                    // Fallback: redirect if no modal
                    window.location.href = getLoginUrl();
                }
            }
        }

        loginTriggers.forEach(function(trigger) {
            trigger.addEventListener('click', handleLoginClick);
        });
        closeBtn?.addEventListener('click', closeModal);
        backdrop?.addEventListener('click', closeModal);

        // Close on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && loginModal && !loginModal.hidden) {
                closeModal();
            }
        });
    }

    /**
     * Initialize announcement banner
     */
    function initAnnouncementBanner() {
        // Find all announcement banners (supports multiple announcements in future)
        const banners = document.querySelectorAll('[class^="announcement-banner"][data-announcement-id]');

        banners.forEach(function(banner) {
            const closeBtn = banner.querySelector('.announcement-banner__close');

            if (closeBtn) {
                const announcementId = closeBtn.dataset.dismissId;
                const announcementHash = closeBtn.dataset.dismissHash;

                closeBtn.addEventListener('click', function() {
                    // Hide the banner with animation
                    banner.style.transition = 'opacity 0.3s, max-height 0.3s';
                    banner.style.opacity = '0';
                    banner.style.maxHeight = '0';
                    banner.style.overflow = 'hidden';

                    setTimeout(function() {
                        banner.style.display = 'none';
                    }, 300);

                    // Set cookie to remember dismissal for 30 days
                    // Use both ID and hash so updating the announcement resets dismissal
                    document.cookie = `ippgi_dismissed_${announcementId}=${announcementHash};path=/;max-age=${30 * 24 * 60 * 60}`;
                });
            }
        });
    }

    /**
     * Initialize search (inline for PC, dropdown for mobile)
     */
    function initSearchOverlay() {
        const header = document.querySelector('.site-header');
        const searchBtn = document.getElementById('header-search-btn');
        const searchClose = document.getElementById('header-search-close');

        // PC inline search
        const searchInline = document.getElementById('header-search-inline');
        const searchInlineInput = searchInline?.querySelector('.header-search-inline__input');
        const searchInlineClear = searchInline?.querySelector('.header-search-inline__clear');

        // Mobile dropdown search
        const searchDropdown = document.getElementById('header-search-dropdown');
        const searchDropdownInput = searchDropdown?.querySelector('.header-search-dropdown__input');
        const searchDropdownClear = searchDropdown?.querySelector('.header-search-dropdown__clear');

        if (!header || !searchBtn) return;

        function isDesktop() {
            return window.matchMedia('(min-width: 1024px)').matches;
        }

        function openSearch() {
            // Close mobile menu if open
            if (closeMobileMenu) {
                closeMobileMenu();
            }

            header.classList.add('is-search-active');
            document.body.classList.add('search-active');
            searchClose.hidden = false;

            if (isDesktop() && searchInline) {
                searchInline.hidden = false;
                setTimeout(function() {
                    searchInlineInput?.focus();
                }, 100);
            } else if (searchDropdown) {
                searchDropdown.hidden = false;
                setTimeout(function() {
                    searchDropdownInput?.focus();
                }, 100);
            }
        }

        function closeSearch() {
            header.classList.remove('is-search-active');
            document.body.classList.remove('search-active');
            searchClose.hidden = true;

            if (searchInline) {
                searchInline.hidden = true;
                if (searchInlineInput) searchInlineInput.value = '';
            }
            if (searchDropdown) {
                searchDropdown.hidden = true;
                if (searchDropdownInput) searchDropdownInput.value = '';
            }

            searchBtn.focus();
        }

        // Expose close function globally
        closeSearchOverlay = closeSearch;

        searchBtn.addEventListener('click', openSearch);
        searchClose?.addEventListener('click', closeSearch);

        // Clear buttons
        searchInlineClear?.addEventListener('click', function() {
            if (searchInlineInput) {
                searchInlineInput.value = '';
                searchInlineInput.focus();
            }
        });

        searchDropdownClear?.addEventListener('click', function() {
            if (searchDropdownInput) {
                searchDropdownInput.value = '';
                searchDropdownInput.focus();
            }
        });

        // Close on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && header.classList.contains('is-search-active')) {
                closeSearch();
            }
        });

        // Search submit function
        function performSearch(query) {
            if (query && query.trim()) {
                var baseUrl = (typeof ippgiData !== 'undefined' && ippgiData.homeUrl) ? ippgiData.homeUrl : '/';
                window.location.href = baseUrl + '?s=' + encodeURIComponent(query.trim());
            }
        }

        // Submit buttons
        const searchInlineSubmit = searchInline?.querySelector('.header-search-inline__submit');
        const searchDropdownSubmit = searchDropdown?.querySelector('.header-search-dropdown__submit');

        searchInlineSubmit?.addEventListener('click', function() {
            performSearch(searchInlineInput?.value);
        });

        searchDropdownSubmit?.addEventListener('click', function() {
            performSearch(searchDropdownInput?.value);
        });

        // Enter key to submit
        searchInlineInput?.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                performSearch(this.value);
            }
        });

        searchDropdownInput?.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                performSearch(this.value);
            }
        });

        // Handle resize - switch between inline and dropdown
        window.addEventListener('resize', function() {
            if (header.classList.contains('is-search-active')) {
                if (isDesktop()) {
                    if (searchDropdown) searchDropdown.hidden = true;
                    if (searchInline) {
                        searchInline.hidden = false;
                        searchInlineInput?.focus();
                    }
                } else {
                    if (searchInline) searchInline.hidden = true;
                    if (searchDropdown) {
                        searchDropdown.hidden = false;
                        searchDropdownInput?.focus();
                    }
                }
            }
        });

        // Auto-open search and fill query on search results page
        var urlParams = new URLSearchParams(window.location.search);
        var searchQuery = urlParams.get('s');
        if (searchQuery) {
            // Fill the search inputs with the query
            if (searchInlineInput) searchInlineInput.value = searchQuery;
            if (searchDropdownInput) searchDropdownInput.value = searchQuery;
            // Open the search UI
            openSearch();
        }
    }

    /**
     * Initialize date picker for search results
     */
    function initDatePicker() {
        const dateFilter = document.querySelector('.search-date-filter');
        const dateSheet = document.querySelector('.date-picker-sheet');
        const dateBackdrop = document.querySelector('.date-picker-backdrop');

        if (!dateFilter || !dateSheet) return;

        // Get elements
        const closeBtn = dateSheet.querySelector('.date-picker-sheet__close');
        const clearBtn = document.getElementById('date-picker-clear');
        const confirmBtn = document.getElementById('date-picker-confirm');
        const prevBtn = document.getElementById('date-picker-prev');
        const nextBtn = document.getElementById('date-picker-next');
        const monthDisplay = document.getElementById('date-picker-month');
        const daysContainer = document.getElementById('date-picker-days');
        const startDisplay = document.getElementById('date-range-start');
        const endDisplay = document.getElementById('date-range-end');
        const filterText = dateFilter.querySelector('.search-date-filter__text');

        // State
        let currentYear = parseInt(dateSheet.dataset.currentYear) || new Date().getFullYear();
        let currentMonth = parseInt(dateSheet.dataset.currentMonth) || new Date().getMonth() + 1;
        let startDate = null;
        let endDate = null;
        let selectingStart = true;

        // Month names
        const monthNames = [
            'January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December'
        ];

        function openDatePicker() {
            dateSheet.classList.add('is-active');
            dateBackdrop?.classList.add('is-active');
            document.body.style.overflow = 'hidden';
            renderCalendar();
        }

        function closeDatePicker() {
            dateSheet.classList.remove('is-active');
            dateBackdrop?.classList.remove('is-active');
            document.body.style.overflow = '';
        }

        function getDaysInMonth(year, month) {
            return new Date(year, month, 0).getDate();
        }

        function getFirstDayOfMonth(year, month) {
            return new Date(year, month - 1, 1).getDay();
        }

        function formatDate(date) {
            if (!date) return '--';
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return year + '-' + month + '-' + day;
        }

        function formatDateShort(date) {
            if (!date) return '--';
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return month + '/' + day;
        }

        function isSameDate(date1, date2) {
            if (!date1 || !date2) return false;
            return date1.getFullYear() === date2.getFullYear() &&
                   date1.getMonth() === date2.getMonth() &&
                   date1.getDate() === date2.getDate();
        }

        function isInRange(date) {
            if (!startDate || !endDate || !date) return false;
            return date > startDate && date < endDate;
        }

        function updateRangeDisplay() {
            if (startDisplay) {
                startDisplay.textContent = startDate ? formatDateShort(startDate) : '--';
            }
            if (endDisplay) {
                endDisplay.textContent = endDate ? formatDateShort(endDate) : '--';
            }
        }

        function renderCalendar() {
            if (!daysContainer || !monthDisplay) return;

            // Update month display
            monthDisplay.textContent = monthNames[currentMonth - 1] + ' ' + currentYear;

            // Clear existing days
            daysContainer.innerHTML = '';

            const daysInMonth = getDaysInMonth(currentYear, currentMonth);
            const firstDay = getFirstDayOfMonth(currentYear, currentMonth);
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            // Add empty cells for days before the first day
            for (let i = 0; i < firstDay; i++) {
                const emptyDay = document.createElement('span');
                emptyDay.className = 'date-picker-sheet__day is-empty';
                daysContainer.appendChild(emptyDay);
            }

            // Add days
            for (let day = 1; day <= daysInMonth; day++) {
                const dayEl = document.createElement('span');
                dayEl.className = 'date-picker-sheet__day';
                dayEl.textContent = day;

                const date = new Date(currentYear, currentMonth - 1, day);
                date.setHours(0, 0, 0, 0);

                // Disable future dates
                if (date > today) {
                    dayEl.classList.add('is-disabled');
                } else {
                    // Check if this is start or end date
                    if (isSameDate(date, startDate)) {
                        dayEl.classList.add('is-range-start');
                        if (isSameDate(startDate, endDate) || !endDate) {
                            dayEl.classList.add('is-range-end');
                        }
                    } else if (isSameDate(date, endDate)) {
                        dayEl.classList.add('is-range-end');
                    } else if (isInRange(date)) {
                        dayEl.classList.add('is-in-range');
                    }

                    // Add click handler
                    dayEl.addEventListener('click', function() {
                        handleDateClick(date);
                    });
                }

                daysContainer.appendChild(dayEl);
            }
        }

        function handleDateClick(date) {
            if (selectingStart || !startDate) {
                // Selecting start date
                startDate = date;
                endDate = null;
                selectingStart = false;
            } else {
                // Selecting end date
                if (date < startDate) {
                    // If clicked date is before start, swap them
                    endDate = startDate;
                    startDate = date;
                } else {
                    endDate = date;
                }
                selectingStart = true;
            }

            updateRangeDisplay();
            renderCalendar();
        }

        function clearSelection() {
            startDate = null;
            endDate = null;
            selectingStart = true;
            updateRangeDisplay();
            renderCalendar();
        }

        function confirmSelection() {
            // Update the filter text
            if (filterText) {
                if (startDate && endDate) {
                    filterText.textContent = formatDateShort(startDate) + ' ~ ' + formatDateShort(endDate);
                } else if (startDate) {
                    filterText.textContent = formatDateShort(startDate) + ' ~';
                } else {
                    filterText.textContent = 'Start Date ~ End Date';
                }
            }

            // Update URL with date parameters
            const url = new URL(window.location.href);
            if (startDate) {
                url.searchParams.set('date_from', formatDate(startDate));
            } else {
                url.searchParams.delete('date_from');
            }
            if (endDate) {
                url.searchParams.set('date_to', formatDate(endDate));
            } else {
                url.searchParams.delete('date_to');
            }

            closeDatePicker();

            // Reload with new parameters
            if (startDate || endDate) {
                window.location.href = url.toString();
            }
        }

        function goToPrevMonth() {
            currentMonth--;
            if (currentMonth < 1) {
                currentMonth = 12;
                currentYear--;
            }
            renderCalendar();
        }

        function goToNextMonth() {
            const today = new Date();
            const nextMonth = currentMonth === 12 ? 1 : currentMonth + 1;
            const nextYear = currentMonth === 12 ? currentYear + 1 : currentYear;

            // Don't go beyond current month
            if (nextYear > today.getFullYear() ||
                (nextYear === today.getFullYear() && nextMonth > today.getMonth() + 1)) {
                return;
            }

            currentMonth = nextMonth;
            currentYear = nextYear;
            renderCalendar();
        }

        // Initialize from URL parameters
        function initFromUrl() {
            const url = new URL(window.location.href);
            const dateFrom = url.searchParams.get('date_from');
            const dateTo = url.searchParams.get('date_to');

            if (dateFrom) {
                startDate = new Date(dateFrom);
                startDate.setHours(0, 0, 0, 0);
            }
            if (dateTo) {
                endDate = new Date(dateTo);
                endDate.setHours(0, 0, 0, 0);
            }

            if (startDate || endDate) {
                selectingStart = true;
                updateRangeDisplay();
                if (filterText) {
                    if (startDate && endDate) {
                        filterText.textContent = formatDateShort(startDate) + ' ~ ' + formatDateShort(endDate);
                    } else if (startDate) {
                        filterText.textContent = formatDateShort(startDate) + ' ~';
                    }
                }
            }
        }

        // Event listeners
        dateFilter.addEventListener('click', openDatePicker);
        dateFilter.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                openDatePicker();
            }
        });
        dateBackdrop?.addEventListener('click', closeDatePicker);
        closeBtn?.addEventListener('click', closeDatePicker);
        clearBtn?.addEventListener('click', clearSelection);
        confirmBtn?.addEventListener('click', confirmSelection);
        prevBtn?.addEventListener('click', goToPrevMonth);
        nextBtn?.addEventListener('click', goToNextMonth);

        // Close on escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && dateSheet.classList.contains('is-active')) {
                closeDatePicker();
            }
        });

        // Initialize
        initFromUrl();
    }

    /**
     * Initialize back to top button
     */
    function initBackToTop() {
        const backToTop = document.querySelector('.site-footer__back-to-top');
        if (!backToTop) return;

        backToTop.addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    }

    /**
     * Initialize upgrade prompt
     */
    function initUpgradePrompt() {
        const upgradePrompt = document.querySelector('.upgrade-prompt');
        if (!upgradePrompt) return;

        const closeBtn = upgradePrompt.querySelector('.upgrade-prompt__close');
        const actionBtn = upgradePrompt.querySelector('.upgrade-prompt__action');

        closeBtn?.addEventListener('click', function() {
            upgradePrompt.hidden = true;
            // Remember dismissal for this session
            sessionStorage.setItem('ippgi_upgrade_dismissed', '1');
        });

        actionBtn?.addEventListener('click', function() {
            const subscribeUrl = this.dataset.subscribeUrl;
            if (subscribeUrl) {
                window.location.href = subscribeUrl;
            }
        });

        // Check if already dismissed this session
        if (sessionStorage.getItem('ippgi_upgrade_dismissed')) {
            upgradePrompt.hidden = true;
        }
    }

    /**
     * Show a toast notification
     * @param {string} message - The message to display
     * @param {string} type - Type of toast: 'success', 'info', 'warning', 'error'
     * @param {number} duration - How long to show in ms (default 3000)
     */
    window.ippgiShowToast = function(message, type = 'info', duration = 3000) {
        // Create container if it doesn't exist
        let container = document.querySelector('.toast-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'toast-container';
            document.body.appendChild(container);
        }

        // Create toast element
        const toast = document.createElement('div');
        toast.className = `toast toast--${type}`;

        // Icon based on type
        const icons = {
            success: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>',
            info: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>',
            warning: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>',
            error: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>'
        };

        toast.innerHTML = `
            <span class="toast__icon">${icons[type] || icons.info}</span>
            <span class="toast__message">${message}</span>
            <button type="button" class="toast__close" aria-label="Close">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        `;

        // Add to container
        container.appendChild(toast);

        // Close button
        const closeBtn = toast.querySelector('.toast__close');
        closeBtn?.addEventListener('click', function() {
            removeToast(toast);
        });

        // Auto remove
        if (duration > 0) {
            setTimeout(function() {
                removeToast(toast);
            }, duration);
        }

        function removeToast(el) {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            setTimeout(function() {
                el.remove();
            }, 300);
        }

        return toast;
    };

    /**
     * Helper: Debounce function
     */
    window.ippgiDebounce = function(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    };

    /**
     * Helper: Format number with commas
     */
    window.ippgiFormatNumber = function(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    };

    /**
     * Helper: Format currency
     */
    window.ippgiFormatCurrency = function(amount, currency = 'USD') {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: currency
        }).format(amount);
    };

    /**
     * Initialize price carousel for homepage
     * Fetches all 6 categories and displays PPGI, GI, GL in an infinite loop carousel
     */
    function initPriceCarousel() {
        // Only run on front page
        if (typeof ippgiData === 'undefined' || !ippgiData.isFrontPage) {
            return;
        }

        const container = document.getElementById('price-table-container');
        const categoryLabel = document.getElementById('current-category');
        const updatedLabel = document.getElementById('prices-updated');
        const dotsContainer = document.getElementById('price-carousel-dots');

        if (!container) return;

        // All 6 categories to fetch
        const allCategories = ['PPGI', 'GI', 'GL', 'HRC', 'CRC_HARD', 'AL'];
        // Categories to display in carousel
        const displayCategories = ['PPGI', 'GI', 'GL'];
        let pricesData = {};
        // currentIndex is for the actual slides (0, 1, 2), trackIndex includes clones
        let currentIndex = 0;
        let trackIndex = 1; // Start at 1 because index 0 is the clone of last slide
        let carouselInterval = null;
        let track = null;
        let isTransitioning = false;

        /**
         * Fetch prices for all categories
         */
        async function fetchAllPrices() {
            const restUrl = ippgiData.restUrl || '/wp-json/ippgi-prices/v1/';

            try {
                // Fetch all 6 categories in parallel
                const promises = allCategories.map(category =>
                    fetch(restUrl + 'prices/category?category=' + category)
                        .then(response => response.json())
                );

                const results = await Promise.all(promises);

                results.forEach((result, index) => {
                    if (result.success && result.data) {
                        pricesData[allCategories[index]] = {
                            data: result.data,
                            fetchedAt: result.fetched_at
                        };
                    }
                });

                // Store in global for price detail page
                window.ippgiPricesData = pricesData;

                // Render all slides
                if (Object.keys(pricesData).length > 0) {
                    renderAllSlides();
                    renderDots();
                    updateLabels();
                    startCarousel();
                } else {
                    showError('No price data available');
                }
            } catch (error) {
                console.error('Failed to fetch prices:', error);
                showError('Failed to load prices');
            }
        }

        /**
         * Build price table HTML for a category
         */
        function buildPriceTableHTML(category) {
            const categoryData = pricesData[category];
            if (!categoryData || !categoryData.data || !categoryData.data.result) {
                return '';
            }

            // Build table HTML
            const result = categoryData.data.result;
            let rows = [];

            // Iterate through widths and their items
            Object.keys(result).forEach(width => {
                const items = result[width];
                if (!Array.isArray(items)) return;

                items.forEach(item => {
                    const thickness = item.thickness || '';
                    const dimensions = thickness + '*' + width;
                    const priceUsd = item.lastprice || item.lastprice_usd || item.price_usd || item.price || 0;
                    const change = item.riseAndFall || item.riseAndFall_usd || item.change || 0;

                    rows.push({
                        product: category,
                        dimensions: dimensions,
                        price: priceUsd,
                        change: change
                    });
                });
            });

            // Limit to 6 rows total
            rows = rows.slice(0, 6);

            // Generate HTML
            let html = '<table class="price-table"><thead><tr>';
            html += '<th>Products</th>';
            html += '<th>Dimensions(mm)</th>';
            html += '<th>Latest($)</th>';
            html += '<th>Change($)</th>';
            html += '</tr></thead><tbody>';

            rows.forEach(row => {
                const changeClass = row.change > 0 ? 'up' : (row.change < 0 ? 'down' : 'neutral');
                const changeSign = row.change > 0 ? '+' : '';
                const changeDisplay = row.change !== 0 ? changeSign + Math.round(row.change) : '0';

                html += '<tr>';
                html += '<td><span class="price-table__product">' + row.product + '</span></td>';
                html += '<td><span class="price-table__dimensions">' + row.dimensions + '</span></td>';
                html += '<td><span class="price-table__price">$' + Math.round(row.price).toLocaleString() + '</span></td>';
                html += '<td><span class="price-table__change price-table__change--' + changeClass + '">' + changeDisplay + '</span></td>';
                html += '</tr>';
            });

            html += '</tbody></table>';
            return html;
        }

        /**
         * Create a slide element
         */
        function createSlide(category) {
            const slide = document.createElement('div');
            slide.className = 'price-carousel__slide';
            slide.dataset.category = category;
            slide.innerHTML = buildPriceTableHTML(category);
            return slide;
        }

        /**
         * Render all slides with clones for infinite loop
         * Structure: [clone-last, slide-0, slide-1, slide-2, clone-first]
         */
        function renderAllSlides() {
            // Create track
            track = document.createElement('div');
            track.className = 'price-carousel__track';

            // Add clone of last slide at the beginning
            const lastCategory = displayCategories[displayCategories.length - 1];
            const cloneFirst = createSlide(lastCategory);
            cloneFirst.classList.add('is-clone');
            track.appendChild(cloneFirst);

            // Add real slides
            displayCategories.forEach(category => {
                track.appendChild(createSlide(category));
            });

            // Add clone of first slide at the end
            const firstCategory = displayCategories[0];
            const cloneLast = createSlide(firstCategory);
            cloneLast.classList.add('is-clone');
            track.appendChild(cloneLast);

            // Replace loading indicator with track
            container.innerHTML = '';
            container.appendChild(track);

            // Set initial position (no animation)
            track.style.transition = 'none';
            track.style.transform = 'translateX(-100%)'; // Start at trackIndex 1
            // Force reflow
            track.offsetHeight;
            track.style.transition = '';

            // Listen for transition end to handle infinite loop
            track.addEventListener('transitionend', handleTransitionEnd);
        }

        /**
         * Handle transition end for infinite loop
         */
        function handleTransitionEnd() {
            isTransitioning = false;

            // If we're at a clone, jump to the real slide
            if (trackIndex === 0) {
                // At clone of last slide, jump to real last slide
                track.style.transition = 'none';
                trackIndex = displayCategories.length;
                track.style.transform = 'translateX(' + (-trackIndex * 100) + '%)';
                track.offsetHeight; // Force reflow
                track.style.transition = '';
            } else if (trackIndex === displayCategories.length + 1) {
                // At clone of first slide, jump to real first slide
                track.style.transition = 'none';
                trackIndex = 1;
                track.style.transform = 'translateX(' + (-trackIndex * 100) + '%)';
                track.offsetHeight; // Force reflow
                track.style.transition = '';
            }
        }

        /**
         * Render carousel dots
         */
        function renderDots() {
            if (!dotsContainer) return;

            dotsContainer.innerHTML = '';
            displayCategories.forEach((category, index) => {
                const dot = document.createElement('button');
                dot.className = 'price-carousel__dot' + (index === 0 ? ' is-active' : '');
                dot.setAttribute('type', 'button');
                dot.setAttribute('aria-label', 'Go to ' + category);
                dot.dataset.index = index;

                dot.addEventListener('click', function(e) {
                    e.stopPropagation();
                    goToSlide(index);
                });

                dotsContainer.appendChild(dot);
            });
        }

        /**
         * Update labels and dots based on currentIndex
         */
        function updateLabels() {
            // Update global current category
            currentPriceCategory = displayCategories[currentIndex];

            // Update category label
            if (categoryLabel) {
                categoryLabel.textContent = displayCategories[currentIndex];
            }

            // Update timestamp
            const categoryData = pricesData[displayCategories[currentIndex]];
            if (updatedLabel && categoryData && categoryData.fetchedAt) {
                updatedLabel.textContent = 'Updated: ' + categoryData.fetchedAt + ' (UTC+8)';
            }

            // Update dots
            if (dotsContainer) {
                const dots = dotsContainer.querySelectorAll('.price-carousel__dot');
                dots.forEach((dot, index) => {
                    if (index === currentIndex) {
                        dot.classList.add('is-active');
                    } else {
                        dot.classList.remove('is-active');
                    }
                });
            }
        }

        /**
         * Move to next slide
         */
        function nextSlide() {
            if (isTransitioning) return;

            isTransitioning = true;
            trackIndex++;
            currentIndex = (currentIndex + 1) % displayCategories.length;

            track.style.transform = 'translateX(' + (-trackIndex * 100) + '%)';
            updateLabels();
        }

        /**
         * Go to specific slide
         */
        function goToSlide(index) {
            if (isTransitioning || index === currentIndex) return;

            isTransitioning = true;
            currentIndex = index;
            trackIndex = index + 1; // +1 because of the clone at the beginning

            track.style.transform = 'translateX(' + (-trackIndex * 100) + '%)';
            updateLabels();

            // Restart auto-play timer
            startCarousel();

            // Reset transitioning flag after a short delay (in case transitionend doesn't fire)
            setTimeout(function() {
                isTransitioning = false;
            }, 600);
        }

        /**
         * Start carousel auto-rotation
         */
        function startCarousel() {
            if (carouselInterval) {
                clearInterval(carouselInterval);
            }

            carouselInterval = setInterval(nextSlide, 5000);
        }

        /**
         * Show error message
         */
        function showError(message) {
            container.innerHTML = '<div class="price-table-loading"><p>' + message + '</p></div>';
        }

        // Start fetching prices
        fetchAllPrices();
    }

    /**
     * Initialize price table and Read More click handlers
     * Handles navigation based on login and membership status
     */
    function initPriceTableClick() {
        const priceTableContainer = document.getElementById('price-table-container');
        const readMoreBtn = document.getElementById('prices-read-more');
        const footerProductLinks = document.querySelectorAll('.site-footer__product-link[data-category]');

        /**
         * Navigate to appropriate page based on user status
         * @param {string} category - The category to display on prices page
         */
        function navigateToPrices(category) {
            category = category || currentPriceCategory || 'PPGI';

            // Check if user is logged in
            if (!ippgiData.isLoggedIn) {
                // Not logged in - redirect to login page
                window.location.href = ippgiData.loginUrl;
                return;
            }

            // Check if user has premium membership (Trial or Plus)
            if (!ippgiData.hasPremium) {
                // Logged in but no premium - redirect to subscribe page
                window.location.href = ippgiData.subscribeUrl;
                return;
            }

            // Has premium - redirect to prices page with category
            window.location.href = ippgiData.pricesUrl + '?category=' + encodeURIComponent(category);
        }

        // Price table click handler
        if (priceTableContainer) {
            priceTableContainer.addEventListener('click', function(e) {
                e.preventDefault();
                navigateToPrices(currentPriceCategory);
            });

            // Keyboard accessibility
            priceTableContainer.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    navigateToPrices(currentPriceCategory);
                }
            });
        }

        // Read More button click handler
        if (readMoreBtn) {
            readMoreBtn.addEventListener('click', function(e) {
                e.preventDefault();
                navigateToPrices(currentPriceCategory);
            });
        }

        // Footer product links click handler
        footerProductLinks.forEach(function(link) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                var category = this.getAttribute('data-category');
                navigateToPrices(category);
            });
        });
    }

    /**
     * Initialize banner carousel
     */
    function initBannerCarousel() {
        const carousel = document.querySelector('.banner-carousel');
        if (!carousel) return;

        const slides = carousel.querySelectorAll('.banner-carousel__slide');
        const dots = carousel.querySelectorAll('.banner-carousel__dot');
        const interval = parseInt(carousel.dataset.interval, 10) || 5000;

        if (slides.length <= 1) return;

        let currentIndex = 0;
        let autoplayTimer = null;

        function goToSlide(index) {
            // Remove active class from current slide and dot
            slides[currentIndex].classList.remove('is-active');
            if (dots[currentIndex]) {
                dots[currentIndex].classList.remove('is-active');
            }

            // Update index
            currentIndex = index;
            if (currentIndex >= slides.length) currentIndex = 0;
            if (currentIndex < 0) currentIndex = slides.length - 1;

            // Add active class to new slide and dot
            slides[currentIndex].classList.add('is-active');
            if (dots[currentIndex]) {
                dots[currentIndex].classList.add('is-active');
            }
        }

        function nextSlide() {
            goToSlide(currentIndex + 1);
        }

        function startAutoplay() {
            stopAutoplay();
            autoplayTimer = setInterval(nextSlide, interval);
        }

        function stopAutoplay() {
            if (autoplayTimer) {
                clearInterval(autoplayTimer);
                autoplayTimer = null;
            }
        }

        // Dot click handlers
        dots.forEach(function(dot, index) {
            dot.addEventListener('click', function() {
                goToSlide(index);
                startAutoplay(); // Reset autoplay timer
            });
        });

        // Pause on hover
        carousel.addEventListener('mouseenter', stopAutoplay);
        carousel.addEventListener('mouseleave', startAutoplay);

        // Touch swipe support
        let touchStartX = 0;
        let touchEndX = 0;

        carousel.addEventListener('touchstart', function(e) {
            touchStartX = e.changedTouches[0].screenX;
            stopAutoplay();
        }, { passive: true });

        carousel.addEventListener('touchend', function(e) {
            touchEndX = e.changedTouches[0].screenX;
            handleSwipe();
            startAutoplay();
        }, { passive: true });

        function handleSwipe() {
            const swipeThreshold = 50;
            const diff = touchStartX - touchEndX;

            if (Math.abs(diff) > swipeThreshold) {
                if (diff > 0) {
                    // Swipe left - next slide
                    goToSlide(currentIndex + 1);
                } else {
                    // Swipe right - previous slide
                    goToSlide(currentIndex - 1);
                }
            }
        }

        // Start autoplay
        startAutoplay();
    }

})();
