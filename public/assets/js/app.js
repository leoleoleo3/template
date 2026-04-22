/**
 * App — Shared AJAX & UI Utilities
 *
 * Centralises patterns that every page used to repeat:
 *   - handleAjaxResponse  : parse fetch() response, handle session expiry
 *   - submitForm          : showLoading → fetch → Notify → reload
 *   - deleteAction        : confirm → fetch → Notify → reload
 *   - initDataTable       : wrap simpleDatatables with defaults
 *   - initSelect2InModal  : attach/destroy Select2 on modal show/hide
 *
 * Requires: SweetAlert2, Notify (notifications.js), simpleDatatables,
 *           jQuery (for Select2), showLoading/hideLoading (layout.php)
 */

const App = (() => {
    'use strict';

    // ── AJAX Response Handler ─────────────────────────────────────────────────

    /**
     * Parse a fetch() Response; handle session-expiry redirects gracefully.
     * Returns a Promise that resolves with the parsed JSON object.
     */
    function handleAjaxResponse(response) {
        return response.text().then(text => {
            let data;
            try {
                data = JSON.parse(text);
            } catch (_) {
                // Non-JSON means the server returned an HTML page (e.g. login redirect)
                Notify.error('Session expired. Please log in again.');
                setTimeout(() => { window.location.href = 'login.php'; }, 2000);
                return Promise.reject(new Error('Non-JSON response'));
            }

            if (data.redirect) {
                Notify.error(data.error || 'Session expired.');
                setTimeout(() => { window.location.href = data.redirect; }, 2000);
                return Promise.reject(new Error('Session expired'));
            }

            return data;
        });
    }

    // ── Form Submission ───────────────────────────────────────────────────────

    /**
     * Submit a form via fetch and handle the response.
     *
     * @param {string}      url        - Endpoint URL (usually the current page)
     * @param {HTMLElement} formEl     - The <form> element
     * @param {object}      options
     *   onSuccess(data)  - Called when data.success === true
     *   onError(message) - Called when data.success === false
     *   loadingMsg       - Text shown in the loading overlay (default: 'Saving...')
     */
    function submitForm(url, formEl, { onSuccess, onError, loadingMsg = 'Saving...' } = {}) {
        showLoading(loadingMsg);
        fetch(url, { method: 'POST', body: new FormData(formEl) })
            .then(handleAjaxResponse)
            .then(data => {
                if (data.success) {
                    onSuccess?.(data);
                } else {
                    hideLoading();
                    onError ? onError(data.error) : Notify.error(data.error || 'An error occurred.');
                }
            })
            .catch(err => {
                hideLoading();
                if (err.message !== 'Session expired' && err.message !== 'Non-JSON response') {
                    Notify.error(err.message || 'An unexpected error occurred.');
                }
            });
    }

    /**
     * Send a simple POST action (non-form), e.g. delete or status change.
     *
     * @param {string} url
     * @param {object} fields    - Key/value pairs appended to FormData (must include csrf_token)
     * @param {object} options   - Same as submitForm options
     */
    function postAction(url, fields, options = {}) {
        const form = document.createElement('form');
        Object.entries(fields).forEach(([k, v]) => {
            const input = document.createElement('input');
            input.name  = k;
            input.value = v;
            form.appendChild(input);
        });
        submitForm(url, form, options);
    }

    // ── Delete Confirmation ───────────────────────────────────────────────────

    /**
     * Confirm deletion with SweetAlert2, then POST and reload on success.
     *
     * @param {string} name        - Human-readable name of the item being deleted
     * @param {object} fields      - POST fields (action, id, csrf_token, …)
     * @param {string} url         - Endpoint (defaults to current page)
     * @param {string} entityLabel - Label used in success message (e.g. 'User')
     */
    function confirmDelete(name, fields, url = window.location.pathname, entityLabel = 'Item') {
        Notify.confirmDelete(name, () => {
            postAction(url, fields, {
                loadingMsg: `Deleting ${entityLabel.toLowerCase()}...`,
                onSuccess() {
                    Notify.actionSuccess('deleted', entityLabel);
                    setTimeout(() => location.reload(), 1500);
                },
                onError(msg) { Notify.error(msg); }
            });
        });
    }

    // ── DataTable ─────────────────────────────────────────────────────────────

    /**
     * Initialise a simpleDatatables DataTable with sensible defaults.
     *
     * @param {string} selector - CSS selector for the <table> element
     * @param {object} options  - Merged with defaults
     * @returns DataTable instance or null if element not found
     */
    function initDataTable(selector, options = {}) {
        const el = document.querySelector(selector);
        if (!el) return null;
        return new simpleDatatables.DataTable(el, {
            searchable: true,
            fixedHeight: false,
            ...options
        });
    }

    // ── Select2 in Modal ──────────────────────────────────────────────────────

    /**
     * Attach Select2 to elements inside a Bootstrap modal.
     * Automatically destroys instances when the modal closes to prevent leaks.
     *
     * @param {HTMLElement}  modalEl        - The modal root element
     * @param {string[]}     selectors      - CSS selectors of <select> elements inside the modal
     * @param {object}       select2Options - Extra options merged into each Select2 call
     */
    function initSelect2InModal(modalEl, selectors, select2Options = {}) {
        const defaults = {
            theme: 'bootstrap-5',
            dropdownParent: $(modalEl),
            width: '100%'
        };

        modalEl.addEventListener('shown.bs.modal', () => {
            selectors.forEach(sel => {
                $(sel).select2({ ...defaults, ...select2Options });
            });
        });

        modalEl.addEventListener('hidden.bs.modal', () => {
            selectors.forEach(sel => {
                if ($(sel).hasClass('select2-hidden-accessible')) {
                    $(sel).select2('destroy');
                }
            });
        });
    }

    // ── Public API ────────────────────────────────────────────────────────────
    return {
        handleAjaxResponse,
        submitForm,
        postAction,
        confirmDelete,
        initDataTable,
        initSelect2InModal
    };
})();
