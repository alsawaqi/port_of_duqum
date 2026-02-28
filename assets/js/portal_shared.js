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

    /* ── Public API ────────────────────────────────────────────────── */
    return {
        toast:          toast,
        confirm:        confirm,
        safeFeather:    safeFeather,
        animateRows:    animateRows,
        tabLoadingHtml: tabLoadingHtml,
        tabErrorHtml:   tabErrorHtml
    };

}(window.jQuery));
