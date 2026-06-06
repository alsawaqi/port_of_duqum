/**
 * portal_shared.js
 * ─────────────────────────────────────────────────────────────────────────────
 * Shared UI utilities for Gate Pass, PTW, and Vendor portals.
 *
 *   PortalUI.toast(message, type, duration)
 *       Shows an animated toast notification.
 *       type: 'success' | 'error' | 'warning' | 'info'  (default: 'success')
 *       duration (ms, default 3500; 0 = persistent)
 *
 *   PortalUI.confirm(options, onConfirm, onCancel)
 *       Shows a styled confirmation dialog.
 *       options: { title, message, btnOk, btnNo, type }
 *       type: 'danger' | 'info' | 'warning'  (default: 'danger')
 *
 *   PortalUI.safeFeather()
 *       Calls feather.replace() only when feather is available.
 *
 *   PortalUI.animateRows(tableSelector)
 *       Applies staggered fade-up animation to DataTable rows.
 *
 *   PortalUI.tabLoadingHtml()
 *       Returns the standard "Loading…" HTML used in tab panes.
 *
 *   PortalUI.tabErrorHtml(message)
 *       Returns the standard error HTML used when a tab fails to load.
 * ─────────────────────────────────────────────────────────────────────────────
 */
var PortalUI = (function ($) {
    'use strict';

    /* ── Internal helpers ──────────────────────────────────────────── */
    function _esc(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    /* ── Toast ─────────────────────────────────────────────────────── */
    var _$container = null;

    function _ensureContainer() {
        if (!_$container || !_$container.parent().length) {
            if (!document.getElementById('portal-toast-container')) {
                $('body').append('<div id="portal-toast-container" aria-live="polite" aria-atomic="false"></div>');
            }
            _$container = $('#portal-toast-container');
        }
    }

    var _iconMap = {
        success: 'check-circle',
        error:   'x-circle',
        warning: 'alert-triangle',
        info:    'info'
    };

    /**
     * Show an animated toast notification.
     * @param {string} message
     * @param {string} [type='success']  'success' | 'error' | 'warning' | 'info'
     * @param {number} [duration=3500]   milliseconds; 0 = persistent
     * @returns {string} toast element id
     */
    function toast(message, type, duration) {
        type     = type     || 'success';
        duration = (duration === undefined || duration === null) ? 3500 : duration;

        _ensureContainer();

        var id   = 'pt-' + Math.random().toString(36).slice(2, 7);
        var icon = _iconMap[type] || 'info';

        var $t = $(
            '<div id="' + id + '" class="portal-toast portal-toast-' + _esc(type) + '" role="alert">' +
                '<i data-feather="' + icon + '" class="pt-icon icon-16"></i>' +
                '<span class="pt-message">' + message + '</span>' +
                '<button type="button" class="pt-close" aria-label="Close">&times;</button>' +
            '</div>'
        );

        _$container.append($t);

        if (typeof feather !== 'undefined') feather.replace();

        /* Trigger animation on next frame */
        requestAnimationFrame(function () {
            $t.addClass('portal-toast-in');
        });

        $t.find('.pt-close').on('click', function () { _dismissToast($t); });

        if (duration > 0) {
            setTimeout(function () { _dismissToast($t); }, duration);
        }

        return id;
    }

    function _dismissToast($t) {
        if (!$t.length) return;
        $t.removeClass('portal-toast-in').addClass('portal-toast-out');
        setTimeout(function () { $t.remove(); }, 380);
    }

    /* ── Confirm dialog ────────────────────────────────────────────── */
    /**
     * Show a branded confirmation dialog.
     * @param {string|Object} options  String = message text; or options object:
     *   {
     *     title:   string  (default "Are you sure?")
     *     message: string
     *     btnOk:   string  (default "Confirm")
     *     btnNo:   string  (default "Cancel")
     *     type:    'danger'|'info'|'warning'  (default 'danger')
     *   }
     * @param {Function} onConfirm  Called when OK is clicked
     * @param {Function} [onCancel] Called when Cancel / backdrop / Escape is used
     */
    function confirm(options, onConfirm, onCancel) {
        if (typeof options === 'string') {
            options = { message: options };
        }
        options = options || {};

        var title   = options.title   || 'Are you sure?';
        var message = options.message || '';
        var btnOk   = options.btnOk   || 'Confirm';
        var btnNo   = options.btnNo   || 'Cancel';
        var type    = options.type    || 'danger';

        var iconName = type === 'info' ? 'help-circle' : 'alert-triangle';

        /* Remove any existing dialog */
        $('#portal-confirm-dialog').remove();

        var $modal = $(
            '<div id="portal-confirm-dialog" class="portal-dialog-backdrop" role="dialog" aria-modal="true">' +
                '<div class="portal-dialog-box">' +
                    '<div class="portal-dialog-icon portal-dialog-icon-' + _esc(type) + '">' +
                        '<i data-feather="' + iconName + '"></i>' +
                    '</div>' +
                    '<h4 class="portal-dialog-title">' + _esc(title) + '</h4>' +
                    (message ? '<p class="portal-dialog-message">' + _esc(message) + '</p>' : '') +
                    '<div class="portal-dialog-actions">' +
                        '<button type="button" class="btn portal-dialog-btn-cancel">' + _esc(btnNo) + '</button>' +
                        '<button type="button" class="btn portal-dialog-btn-ok portal-dialog-btn-' + _esc(type) + '">' + _esc(btnOk) + '</button>' +
                    '</div>' +
                '</div>' +
            '</div>'
        );

        $('body').append($modal);

        if (typeof feather !== 'undefined') feather.replace();

        /* Animate in */
        requestAnimationFrame(function () {
            $modal.addClass('portal-dialog-in');
        });

        function _close() {
            /* Unbind keyboard handler before removing modal */
            $(document).off('keydown.portalConfirm');
            $modal.removeClass('portal-dialog-in').addClass('portal-dialog-out');
            setTimeout(function () { $modal.remove(); }, 300);
        }

        $modal.find('.portal-dialog-btn-ok').on('click', function () {
            _close();
            if (typeof onConfirm === 'function') onConfirm();
        });

        $modal.find('.portal-dialog-btn-cancel').on('click', function () {
            _close();
            if (typeof onCancel === 'function') onCancel();
        });

        /* Click outside box */
        $modal.on('click', function (e) {
            if ($(e.target).is('#portal-confirm-dialog')) {
                _close();
                if (typeof onCancel === 'function') onCancel();
            }
        });

        /* Keyboard: Escape = cancel, Enter = confirm */
        $(document).on('keydown.portalConfirm', function (e) {
            if (!$('#portal-confirm-dialog').length) {
                $(document).off('keydown.portalConfirm');
                return;
            }
            if (e.key === 'Escape') {
                _close();
                if (typeof onCancel === 'function') onCancel();
            } else if (e.key === 'Enter') {
                $modal.find('.portal-dialog-btn-ok').trigger('click');
            }
        });
    }

    /* ── Helpers ───────────────────────────────────────────────────── */
    /**
     * Safely call feather.replace() (no-op if feather is not loaded).
     */
    function safeFeather() {
        if (typeof feather !== 'undefined') feather.replace();
    }

    var _lightThemeColor = 'F2F2F2';
    var _darkThemeColor = '1E202D';

    function _isDarkTheme() {
        return $('body').attr('data-color') === _darkThemeColor;
    }

    function updateThemeToggle() {
        var $toggle = $('#pod-theme-toggle');
        if (!$toggle.length) return;

        var isDark = _isDarkTheme();
        var nextLabel = isDark ? 'Switch to light mode' : 'Switch to dark mode';

        $toggle
            .toggleClass('is-dark', isDark)
            .attr('aria-pressed', isDark ? 'true' : 'false')
            .attr('aria-label', nextLabel)
            .attr('title', nextLabel)
            .attr('data-bs-original-title', nextLabel);

        $toggle.find('.pod-theme-toggle-label').text(isDark ? 'Dark mode' : 'Light mode');
    }

    function setPortalTheme(color) {
        if (typeof setCookie !== 'function' || typeof setThemeColor !== 'function') return;

        $('.custom-theme-color').remove();
        setCookie('theme_color', color);
        setThemeColor();
        $('#theme-color-meta-tag').attr('content', $('body').css('background-color'));
        updateThemeToggle();
        safeFeather();
    }

    function initThemeToggle() {
        var $toggle = $('#pod-theme-toggle');
        if (!$toggle.length) return;

        updateThemeToggle();

        $toggle.off('click.portalThemeToggle').on('click.portalThemeToggle', function (e) {
            e.preventDefault();
            setPortalTheme(_isDarkTheme() ? _lightThemeColor : _darkThemeColor);
        });

        $(document)
            .off('click.portalThemeToggleSync', '.change-theme')
            .on('click.portalThemeToggleSync', '.change-theme', function () {
                setTimeout(updateThemeToggle, 40);
            });
    }

    /**
     * Apply a staggered fade-up animation to DataTable rows.
     * @param {string} tableSelector  e.g. '#my-table'
     */
    function animateRows(tableSelector) {
        $(tableSelector + ' tbody tr').each(function (idx, row) {
            var $row = $(row);
            $row.css({ opacity: 0, transform: 'translateY(4px)' });
            setTimeout(function () {
                $row.css({
                    opacity:    1,
                    transform:  'translateY(0)',
                    transition: 'opacity .18s ease, transform .18s ease'
                });
            }, 30 * idx);
        });
    }

    /**
     * Returns the standard loading HTML shown while a tab pane is fetching data.
     */
    function tabLoadingHtml() {
        return '<div class="portal-tab-loading"><span class="portal-spinner"></span> Loading…</div>';
    }

    /**
     * Returns the standard error HTML shown when a tab pane fails to load.
     * @param {string} [msg]
     */
    function tabErrorHtml(msg) {
        var text = msg || 'Failed to load. Please try again.';
        return '<div class="portal-tab-error"><i data-feather="alert-circle" class="icon-16"></i> ' + _esc(text) + '</div>';
    }

    /* TailAdmin-inspired automatic scoping */
    var _scopeTimer = null;
    var _observerStarted = false;

    function _domainFromText(text) {
        text = String(text || '').toLowerCase();
        if (text.indexOf('gate_pass') !== -1 || text.indexOf('gate-pass') !== -1 || text.indexOf('gp-') !== -1) {
            return 'gate';
        }
        if (text.indexOf('ptw') !== -1) {
            return 'ptw';
        }
        if (text.indexOf('vendor') !== -1 || text.indexOf('vendors') !== -1) {
            return 'vendor';
        }
        if (text.indexOf('tender') !== -1) {
            return 'tender';
        }
        return '';
    }

    function _markDomain($el, domain) {
        if (!$el || !$el.length || !domain) return;
        $el.addClass('pod-domain-' + domain);
    }

    function _nearestModuleRoot($el) {
        var selector = [
            '#page-content',
            '.gp-portal-home',
            '.gp-requests',
            '.vp-overview',
            '.vp-contacts',
            '.vp-bank',
            '.vp-branches',
            '.vp-credentials',
            '.vp-specialties',
            '.vp-documents',
            '.vp-tenders',
            '.ptw-apps',
            '.ptw-wizard-wrap',
            '.ptw-detail-shell',
            '.tender-wizard-page',
            '.tender-manager-review-page'
        ].join(', ');

        var $root = $el.closest(selector);
        if ($root.length) return $root;

        var $pane = $el.closest('.tab-pane');
        if ($pane.length) {
            var $child = $pane.children('div').first();
            if ($child.length) return $child;
            return $pane;
        }

        return $();
    }

    function _markRoot($root, domain) {
        if (!$root.length) return;

        if ($root.is('#page-content') || $root.hasClass('page-wrapper') || $root.hasClass('ptw-wizard-wrap') || $root.hasClass('ptw-detail-shell')) {
            $root.addClass('pod-focus-page');
        } else {
            $root.addClass('pod-focus-fragment');
        }

        _markDomain($root, domain);
        setTimeout(function () {
            $root.addClass('pod-focus-ready ps-ready');
        }, 30);
    }

    function _markTables() {
        var tableSelector = [
            'table[id^="tender-"]',
            'table[id^="ptw-"]',
            'table[id^="gate-pass-"]',
            'table[id^="gp-"]',
            'table[id^="vendor-"]',
            '#vendors-table',
            '#rfq-items-table',
            '.ptw-table'
        ].join(', ');

        $(tableSelector).each(function () {
            var $table = $(this);
            var domain = _domainFromText($table.attr('id') || $table.attr('class') || window.location.pathname);
            var $root = _nearestModuleRoot($table);
            _markRoot($root, domain);

            $table.closest('.table-responsive, .dataTables_wrapper').addClass('pod-table-shell');
        });
    }

    function _markTenderModals() {
        $('.modal-content').each(function () {
            var $modal = $(this);
            if ($modal.hasClass('pod-tender-modal')) return;

            var tenderSelector = [
                'form[action*="tender_"]',
                'form[id*="tender-"]',
                'form[id*="technical-clarification"]',
                'form[id*="commercial-clarification"]',
                '[id*="tender-"]',
                '[class*="tender-"]',
                'a[href*="tender_"]',
                'a[href*="tender/"]'
            ].join(', ');

            if ($modal.find(tenderSelector).length || _domainFromText($modal.text().slice(0, 1200)) === 'tender') {
                $modal.addClass('pod-tender-modal');
                $modal.closest('.modal-dialog').addClass('pod-tender-modal-dialog');
                $modal.find('.table-responsive').addClass('pod-table-shell');
            }
        });
    }

    function _markPtwModals() {
        $('.modal-content').each(function () {
            var $modal = $(this);
            if ($modal.hasClass('pod-ptw-modal')) return;

            var ptwSelector = [
                'form[action*="ptw_"]',
                'form[action*="ptw/"]',
                'form[id*="ptw-"]',
                '[id*="ptw-"]',
                '[class*="ptw-"]',
                'a[href*="ptw_"]',
                'a[href*="ptw/"]'
            ].join(', ');

            if ($modal.find(ptwSelector).length || _domainFromText($modal.text().slice(0, 1200)) === 'ptw') {
                $modal.addClass('pod-ptw-modal');
                $modal.closest('.modal-dialog').addClass('pod-ptw-modal-dialog');
                $modal.find('.table-responsive').addClass('pod-table-shell');
            }
        });
    }

    function _markStaticScreens() {
        var pathDomain = _domainFromText(window.location.pathname);

        $('#page-content').each(function () {
            var $page = $(this);
            var domain = pathDomain || _domainFromText($page.attr('class') || '');
            if (!domain && $page.find('[id*="tender"], [id*="ptw"], [id*="gate-pass"], [id*="vendor"], [class*="tender-"], [class*="ptw-"], [class*="vendor"], [class*="gp-"]').length) {
                domain = _domainFromText(($page.html() || '').slice(0, 5000));
            }
            if (domain) {
                _markRoot($page, domain);
            }
        });

        $([
            '.gp-portal-home',
            '.gp-requests',
            '.vp-overview',
            '.vp-contacts',
            '.vp-bank',
            '.vp-branches',
            '.vp-credentials',
            '.vp-specialties',
            '.vp-documents',
            '.vp-tenders',
            '.ptw-apps',
            '.ptw-wizard-wrap',
            '.ptw-detail-shell',
            '.tender-wizard-page',
            '.tender-manager-review-page'
        ].join(', ')).each(function () {
            var $root = $(this);
            var domain = _domainFromText($root.attr('class') || '') || pathDomain;
            _markRoot($root, domain);
        });
    }

    function _polishFocusedUi() {
        _markStaticScreens();
        _markTables();
        _markTenderModals();
        _markPtwModals();

        $('[data-feather]').each(function () {
            var $icon = $(this);
            if (!$icon.attr('aria-hidden')) {
                $icon.attr('aria-hidden', 'true');
            }
        });

        safeFeather();
    }

    function scopeFocusedModules() {
        clearTimeout(_scopeTimer);
        _scopeTimer = setTimeout(_polishFocusedUi, 60);
    }

    var _podDashboardCharts = {};

    function initDashboardCharts(context) {
        if (typeof Chart === 'undefined') return;

        var $context = context ? $(context) : $(document);
        var $charts = $context.find('[data-pod-chart-config]');
        if ($context.is && $context.is('[data-pod-chart-config]')) {
            $charts = $charts.add($context);
        }

        if (!$charts.length) return;

        Chart.defaults.global.defaultFontFamily = "'Outfit', 'Inter', 'Segoe UI', sans-serif";
        Chart.defaults.global.defaultFontColor = '#667085';

        $charts.each(function () {
            var canvas = this;
            var id = canvas.id || ('pod-chart-' + Math.random().toString(36).slice(2));
            var configId = canvas.getAttribute('data-pod-chart-config');
            var configNode = configId ? document.getElementById(configId) : null;

            if (!configNode || !configNode.textContent) return;

            var config;
            try {
                config = JSON.parse(configNode.textContent);
            } catch (e) {
                return;
            }

            if (!config || !config.type || !config.data) return;

            if (_podDashboardCharts[id]) {
                _podDashboardCharts[id].destroy();
            }

            config.options = $.extend(true, {
                responsive: true,
                maintainAspectRatio: false,
                animation: {
                    duration: 950,
                    easing: 'easeOutQuart'
                },
                legend: {
                    display: false,
                    labels: {
                        usePointStyle: true,
                        boxWidth: 8,
                        fontColor: '#667085'
                    }
                },
                tooltips: {
                    backgroundColor: 'rgba(16, 24, 40, .92)',
                    titleFontFamily: "'Outfit', 'Inter', 'Segoe UI', sans-serif",
                    bodyFontFamily: "'Outfit', 'Inter', 'Segoe UI', sans-serif",
                    cornerRadius: 8,
                    xPadding: 10,
                    yPadding: 8,
                    displayColors: true
                }
            }, config.options || {});

            if (!config.options.legend || config.options.legend.display === false) {
                config.options.legend = $.extend(true, { display: false }, config.options.legend || {});
            }

            _podDashboardCharts[id] = new Chart(canvas.getContext('2d'), config);
            $(canvas).attr('data-pod-chart-ready', '1');
        });
    }

    function startFocusedModuleObserver() {
        if (_observerStarted || !window.MutationObserver || !document.body) return;
        _observerStarted = true;

        var observer = new MutationObserver(function (mutations) {
            var shouldScope = false;
            for (var i = 0; i < mutations.length; i++) {
                if (mutations[i].addedNodes && mutations[i].addedNodes.length) {
                    shouldScope = true;
                    break;
                }
            }
            if (shouldScope) {
                scopeFocusedModules();
            }
        });

        observer.observe(document.body, { childList: true, subtree: true });
    }

    $(function () {
        initThemeToggle();
        initDashboardCharts(document);
        scopeFocusedModules();
        startFocusedModuleObserver();
        $(document).ajaxComplete(function () {
            initDashboardCharts(document);
            scopeFocusedModules();
        });
    });

    /* ── Public API ────────────────────────────────────────────────── */
    return {
        toast:          toast,
        confirm:        confirm,
        safeFeather:    safeFeather,
        animateRows:    animateRows,
        tabLoadingHtml: tabLoadingHtml,
        tabErrorHtml:   tabErrorHtml,
        scopeFocusedModules: scopeFocusedModules,
        initDashboardCharts: initDashboardCharts,
        updateThemeToggle: updateThemeToggle
    };

}(window.jQuery));
