document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    /*
    |--------------------------------------------------------------------------
    | ELEMENTS
    |--------------------------------------------------------------------------
    */

    var sidebar = document.getElementById('sidebar');
    var backdrop = document.getElementById('sidebarBackdrop');
    var toggleButton = document.getElementById('sidebarToggle');

    var navLinks = document.querySelectorAll('[data-nav-link]');
    var tabPanels = document.querySelectorAll('[data-tab-panel]');

    var confirmForms = document.querySelectorAll('form[data-confirm]');

    var pageTitleHeading = document.getElementById('pageTitleHeading');
    var pageTitleDescription = document.getElementById('pageTitleDescription');

    var animatedElements = document.querySelectorAll('[data-animate]');

    var prefersReducedMotion = window.matchMedia
        ? window.matchMedia('(prefers-reduced-motion: reduce)').matches
        : false;

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    function getNavLinkByTabId(tabId) {
        var matchedLink = null;

        navLinks.forEach(function (link) {
            if (link.getAttribute('data-tab-target') === tabId) {
                matchedLink = link;
            }
        });

        return matchedLink;
    }

    function getPanelByTabId(tabId) {
        var matchedPanel = null;

        tabPanels.forEach(function (panel) {
            if (panel.id === tabId) {
                matchedPanel = panel;
            }
        });

        return matchedPanel;
    }

    function tabExists(tabId) {
        if (!tabId) {
            return false;
        }

        return Boolean(
            getNavLinkByTabId(tabId) &&
            getPanelByTabId(tabId)
        );
    }

    function getDefaultTabId() {
        if (navLinks.length === 0) {
            return '';
        }

        return navLinks[0].getAttribute('data-tab-target') || '';
    }

    /*
    |--------------------------------------------------------------------------
    | MOBILE SIDEBAR
    |--------------------------------------------------------------------------
    */

    function updateToggleState(isOpen) {
        if (!toggleButton) {
            return;
        }

        toggleButton.setAttribute(
            'aria-expanded',
            isOpen ? 'true' : 'false'
        );

        toggleButton.setAttribute(
            'aria-label',
            isOpen ? 'Tutup menu' : 'Buka menu'
        );
    }

    function closeSidebar() {
        if (sidebar) {
            sidebar.classList.remove('is-open');
        }

        if (backdrop) {
            backdrop.classList.remove('is-visible');
            backdrop.setAttribute('aria-hidden', 'true');
        }

        document.body.classList.remove('sidebar-open');

        updateToggleState(false);
    }

    function openSidebar() {
        if (!sidebar) {
            return;
        }

        sidebar.classList.add('is-open');

        if (backdrop) {
            backdrop.classList.add('is-visible');
            backdrop.setAttribute('aria-hidden', 'false');
        }

        document.body.classList.add('sidebar-open');

        updateToggleState(true);
    }

    function toggleSidebar() {
        if (!sidebar) {
            return;
        }

        if (sidebar.classList.contains('is-open')) {
            closeSidebar();
            return;
        }

        openSidebar();
    }

    if (toggleButton) {
        toggleButton.setAttribute('aria-expanded', 'false');

        toggleButton.addEventListener('click', function () {
            toggleSidebar();
        });
    }

    if (backdrop) {
        backdrop.setAttribute('aria-hidden', 'true');

        backdrop.addEventListener('click', function () {
            closeSidebar();
        });
    }

    /*
    |--------------------------------------------------------------------------
    | ESCAPE CLOSE SIDEBAR
    |--------------------------------------------------------------------------
    */

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeSidebar();
        }
    });

    /*
    |--------------------------------------------------------------------------
    | CLOSE MOBILE SIDEBAR WHEN ENTERING DESKTOP
    |--------------------------------------------------------------------------
    */

    window.addEventListener('resize', function () {
        if (window.innerWidth >= 1024) {
            closeSidebar();
        }
    });

    /*
    |--------------------------------------------------------------------------
    | TAB NAVIGATION
    |--------------------------------------------------------------------------
    */

    function activateTab(tabId, updateUrl) {
        if (!tabExists(tabId)) {
            return false;
        }

        /*
         * Update sidebar navigation.
         */
        navLinks.forEach(function (link) {
            var targetId = link.getAttribute('data-tab-target');
            var isActive = targetId === tabId;

            link.classList.toggle('is-active', isActive);

            if (isActive) {
                link.setAttribute('aria-current', 'page');

                if (pageTitleHeading) {
                    pageTitleHeading.textContent =
                        link.getAttribute('data-tab-title') || '';
                }

                if (pageTitleDescription) {
                    pageTitleDescription.textContent =
                        link.getAttribute('data-tab-description') || '';
                }
            } else {
                link.removeAttribute('aria-current');
            }
        });

        /*
         * Update panel.
         */
        tabPanels.forEach(function (panel) {
            var isActive = panel.id === tabId;

            panel.classList.toggle('is-active', isActive);
            panel.hidden = !isActive;

            if (isActive) {
                panel.setAttribute('aria-hidden', 'false');
            } else {
                panel.setAttribute('aria-hidden', 'true');
            }
        });

        /*
         * Update URL tanpa membuat browser meloncat
         * ke posisi element berdasarkan hash.
         */
        if (updateUrl) {
            var currentUrl = new URL(window.location.href);

            currentUrl.hash = tabId;

            window.history.pushState(
                {
                    tab: tabId
                },
                '',
                currentUrl.toString()
            );
        }

        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | SIDEBAR LINK CLICK
    |--------------------------------------------------------------------------
    */

    navLinks.forEach(function (link) {
        link.addEventListener('click', function (event) {
            var tabId = link.getAttribute('data-tab-target');

            if (!tabExists(tabId)) {
                return;
            }

            event.preventDefault();

            activateTab(tabId, true);

            closeSidebar();
        });
    });

    /*
    |--------------------------------------------------------------------------
    | INITIAL TAB
    |--------------------------------------------------------------------------
    |
    | Priority:
    |
    | 1. URL hash
    |    /#peta-jalan
    |
    | 2. Query ?tab=
    |    /?tab=peta-jalan
    |
    | 3. Tab pertama
    |
    */

    function determineInitialTab() {
        var hashTab = '';

        if (window.location.hash) {
            try {
                hashTab = decodeURIComponent(
                    window.location.hash.substring(1)
                );
            } catch (error) {
                hashTab = window.location.hash.substring(1);
            }
        }

        var searchParams = new URLSearchParams(
            window.location.search
        );

        var queryTab = searchParams.get('tab') || '';

        /*
         * Contoh:
         *
         * /?status=red#dashboard
         *
         * Harus tetap membuka Dashboard.
         */
        if (tabExists(hashTab)) {
            return hashTab;
        }

        if (tabExists(queryTab)) {
            return queryTab;
        }

        return getDefaultTabId();
    }

    if (tabPanels.length > 0 && navLinks.length > 0) {
        var initialTabId = determineInitialTab();

        activateTab(initialTabId, false);
    }

    /*
    |--------------------------------------------------------------------------
    | BROWSER BACK / FORWARD
    |--------------------------------------------------------------------------
    */

    window.addEventListener('popstate', function () {
        var tabId = determineInitialTab();

        if (tabId) {
            activateTab(tabId, false);
        }

        closeSidebar();
    });

    /*
    |--------------------------------------------------------------------------
    | HASH CHANGE
    |--------------------------------------------------------------------------
    |
    | Berguna jika hash berubah dari luar JS,
    | misalnya user mengetik URL secara manual.
    |
    */

    window.addEventListener('hashchange', function () {
        var hashTab = '';

        if (window.location.hash) {
            try {
                hashTab = decodeURIComponent(
                    window.location.hash.substring(1)
                );
            } catch (error) {
                hashTab = window.location.hash.substring(1);
            }
        }

        if (tabExists(hashTab)) {
            activateTab(hashTab, false);
        }
    });

    /*
    |--------------------------------------------------------------------------
    | CONFIRM ACTION
    |--------------------------------------------------------------------------
    |
    | Tetap mempertahankan confirmation untuk delete
    | atau action penting pada CRUD lama.
    |
    */

    confirmForms.forEach(function (form) {
        form.addEventListener('submit', function (event) {
            var message =
                form.getAttribute('data-confirm') ||
                'Lanjutkan aksi ini?';

            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });

    /*
    |--------------------------------------------------------------------------
    | DASHBOARD DRILLDOWN
    |--------------------------------------------------------------------------
    */

    var dashboardDetail = document.getElementById('dashboardStatusDetail');
    var dashboardDetailTriggers = document.querySelectorAll(
        '[data-dashboard-detail-trigger]'
    );
    var dashboardDetailRows = document.querySelectorAll(
        '[data-dashboard-detail-row]'
    );
    var dashboardDetailTitle = document.querySelector(
        '[data-dashboard-detail-title]'
    );
    var dashboardDetailSummary = document.querySelector(
        '[data-dashboard-detail-summary]'
    );
    var dashboardDetailClose = document.querySelector(
        '[data-dashboard-detail-close]'
    );

    function showDashboardDetail(filter, label) {
        if (!dashboardDetail) {
            return;
        }

        var visibleCount = 0;
        var isStreamFilter = filter.indexOf('stream:') === 0;
        var filterValue = isStreamFilter
            ? filter.substring(7)
            : filter;

        dashboardDetailRows.forEach(function (row) {
            var isVisible = isStreamFilter
                ? row.getAttribute('data-stream') === filterValue
                : row.getAttribute('data-status') === filterValue;

            row.hidden = !isVisible;

            if (isVisible) {
                visibleCount++;
            }
        });

        if (dashboardDetailTitle) {
            dashboardDetailTitle.textContent = label;
        }

        if (dashboardDetailSummary) {
            dashboardDetailSummary.textContent =
                visibleCount + ' aktivitas ditampilkan beserta progres dan PIC.';
        }

        dashboardDetail.hidden = false;

        dashboardDetailTriggers.forEach(function (trigger) {
            trigger.setAttribute(
                'aria-pressed',
                trigger.getAttribute('data-dashboard-detail-trigger') === filter
                    ? 'true'
                    : 'false'
            );
        });

        dashboardDetail.scrollIntoView({
            behavior: prefersReducedMotion ? 'auto' : 'smooth',
            block: 'nearest'
        });
    }

    dashboardDetailTriggers.forEach(function (trigger) {
        trigger.addEventListener('click', function () {
            var filter = trigger.getAttribute('data-dashboard-detail-trigger') || '';
            var labelElement = trigger.querySelector('span:not(.traffic-dot)');
            var label = labelElement
                ? labelElement.textContent.trim()
                : 'Detail aktivitas';

            showDashboardDetail(filter, label);
        });
    });

    if (dashboardDetailClose) {
        dashboardDetailClose.addEventListener('click', function () {
            if (dashboardDetail) {
                dashboardDetail.hidden = true;
            }

            dashboardDetailTriggers.forEach(function (trigger) {
                trigger.setAttribute('aria-pressed', 'false');
            });
        });
    }

    /*
    |--------------------------------------------------------------------------
    | ANIMATION
    |--------------------------------------------------------------------------
    */

    function showAllAnimatedElements() {
        animatedElements.forEach(function (element) {
            element.classList.add('is-visible');
            element.style.transitionDelay = '0ms';
        });
    }

    /*
     * Jika user meminta reduced motion,
     * tampilkan semua elemen langsung.
     */
    if (prefersReducedMotion) {
        showAllAnimatedElements();
        return;
    }

    /*
     * Fallback browser lama.
     */
    if (!('IntersectionObserver' in window)) {
        showAllAnimatedElements();
        return;
    }

    /*
     * Intersection animation.
     */
    var observer = new IntersectionObserver(
        function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) {
                    return;
                }

                entry.target.classList.add('is-visible');

                observer.unobserve(entry.target);
            });
        },
        {
            threshold: 0.12,
            rootMargin: '0px 0px -30px 0px'
        }
    );

    animatedElements.forEach(function (element, index) {
        /*
         * Delay dibatasi agar halaman dengan banyak row/card
         * tidak membutuhkan waktu terlalu lama.
         */
        var delayIndex = Math.min(index, 8);

        element.style.transitionDelay =
            String(delayIndex * 40) + 'ms';

        observer.observe(element);
    });
});
