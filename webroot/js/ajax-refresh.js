/**
 * Ajax Refresh Module
 *
 * Provides AJAX-based list refresh without full page reload.
 * Supports manual refresh (button) and auto-refresh (polling).
 */
const AjaxRefresh = (function () {
    let refreshInterval = null;
    let isRefreshing = false;
    let autoRefreshMs = 0;
    let entityType = '';

    /**
     * Initialize the refresh module
     * @param {object} options
     * @param {string} options.entityType - 'ticket', 'compra', or 'pqrs'
     * @param {number} [options.autoRefreshSeconds=0] - Auto-refresh interval (0 = disabled)
     */
    function init(options = {}) {
        entityType = options.entityType || '';
        autoRefreshMs = (options.autoRefreshSeconds || 0) * 1000;

        // Bind reload button
        const btn = document.getElementById('btn-refresh-list');
        if (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                refresh();
            });
        }

        // Start auto-refresh if configured
        if (autoRefreshMs > 0) {
            startAutoRefresh();
            // Pause when tab is hidden, resume when visible
            document.addEventListener('visibilitychange', function () {
                if (document.hidden) {
                    stopAutoRefresh();
                } else {
                    startAutoRefresh();
                    refresh(); // Refresh immediately when tab becomes visible
                }
            });
        }
    }

    /**
     * Perform an AJAX refresh of the list content
     */
    function refresh() {
        if (isRefreshing) return;

        // Don't refresh if user has selected items (bulk action in progress)
        const checked = document.querySelectorAll('.row-check:checked');
        if (checked.length > 0) return;

        isRefreshing = true;
        const btn = document.getElementById('btn-refresh-list');
        const icon = btn ? btn.querySelector('i') : null;

        // Animate the button
        if (icon) icon.classList.add('spin-animation');

        // Preserve scroll position
        const container = document.querySelector('.table-scroll');
        const scrollTop = container ? container.scrollTop : 0;

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
                    current.innerHTML = newContent.innerHTML;

                    // Restore scroll position
                    var restoredContainer = document.querySelector('.table-scroll');
                    if (restoredContainer) restoredContainer.scrollTop = scrollTop;

                    // Re-init bulk actions checkboxes
                    if (typeof initBulkActions === 'function') {
                        initBulkActions(entityType);
                    }

                    // Re-init Select2 if present
                    if (typeof jQuery !== 'undefined' && jQuery.fn.select2) {
                        jQuery('#entity-list-content .select2').select2({
                            theme: 'bootstrap-5',
                            width: '100%',
                            allowClear: true,
                            placeholder: 'Sin asignar'
                        });
                    }

                    // Re-bind table assignment forms
                    rebindAssignForms();
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

    /**
     * Re-bind Select2 change events on table assignment forms after refresh
     */
    function rebindAssignForms() {
        var selects = document.querySelectorAll('#entity-list-content .table-agent-select');
        selects.forEach(function (select) {
            if (typeof jQuery !== 'undefined' && jQuery.fn.select2) {
                var $select = jQuery(select);
                $select.off('select2:select select2:clear');
                $select.on('select2:select select2:clear', function () {
                    if (typeof LoadingSpinner !== 'undefined') {
                        LoadingSpinner.show('Asignando...');
                    }
                    select.closest('form').submit();
                });
            } else {
                select.removeEventListener('change', handleAssignChange);
                select.addEventListener('change', handleAssignChange);
            }
        });
    }

    function handleAssignChange() {
        if (typeof LoadingSpinner !== 'undefined') {
            LoadingSpinner.show('Asignando...');
        }
        this.closest('form').submit();
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
