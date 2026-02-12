/**
 * Ajax Refresh Module
 *
 * Provides AJAX-based list refresh without full page reload.
 * Supports manual refresh (button) and auto-refresh (polling).
 *
 * Uses existing global functions:
 * - reinitializeSelect2(container) from select2-init.js
 * - initBulkActions(entityType) from bulk-actions-module.js
 */
const AjaxRefresh = (function () {
    let refreshInterval = null;
    let isRefreshing = false;
    let autoRefreshMs = 0;
    let entityType = '';

    function init(options) {
        entityType = options.entityType || '';
        autoRefreshMs = (options.autoRefreshSeconds || 0) * 1000;

        var btn = document.getElementById('btn-refresh-list');
        if (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                refresh();
            });
        }

        if (autoRefreshMs > 0) {
            startAutoRefresh();
            document.addEventListener('visibilitychange', function () {
                if (document.hidden) {
                    stopAutoRefresh();
                } else {
                    startAutoRefresh();
                    refresh();
                }
            });
        }
    }

    function refresh() {
        if (isRefreshing) return;

        // Don't refresh if user has selected items (bulk action in progress)
        if (document.querySelectorAll('.row-check:checked').length > 0) return;

        isRefreshing = true;
        var btn = document.getElementById('btn-refresh-list');
        var icon = btn ? btn.querySelector('i') : null;
        if (icon) icon.classList.add('spin-animation');

        // Preserve scroll position
        var tableContainer = document.querySelector('#entity-list-content .table-scroll');
        var scrollTop = tableContainer ? tableContainer.scrollTop : 0;

        fetch(window.location.href, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (r) { return r.text(); })
            .then(function (html) {
                var parser = new DOMParser();
                var doc = parser.parseFromString(html, 'text/html');
                var newContent = doc.getElementById('entity-list-content');
                var current = document.getElementById('entity-list-content');

                if (newContent && current) {
                    // Destroy existing Select2 instances before replacing HTML
                    if (typeof jQuery !== 'undefined' && jQuery.fn.select2) {
                        jQuery('#entity-list-content select.select2-hidden-accessible').select2('destroy');
                    }

                    current.innerHTML = newContent.innerHTML;

                    // Restore scroll position
                    var restored = document.querySelector('#entity-list-content .table-scroll');
                    if (restored) restored.scrollTop = scrollTop;

                    // Re-init Select2 using the global function from select2-init.js
                    if (typeof reinitializeSelect2 === 'function') {
                        reinitializeSelect2('#entity-list-content');
                    }

                    // Re-init bulk actions (checkboxes + table assignments with Select2 events)
                    if (typeof initBulkActions === 'function') {
                        initBulkActions(entityType);
                    }
                }
            })
            .catch(function (err) {
                console.error('AjaxRefresh error:', err);
            })
            .finally(function () {
                isRefreshing = false;
                if (icon) icon.classList.remove('spin-animation');
            });
    }

    function startAutoRefresh() {
        stopAutoRefresh();
        if (autoRefreshMs > 0) {
            refreshInterval = setInterval(refresh, autoRefreshMs);
        }
    }

    function stopAutoRefresh() {
        if (refreshInterval) {
            clearInterval(refreshInterval);
            refreshInterval = null;
        }
    }

    return {
        init: init,
        refresh: refresh,
        stop: stopAutoRefresh
    };
})();
