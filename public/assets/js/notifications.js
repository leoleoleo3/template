/**
 * Notification Utility Module
 * Uses SweetAlert2 for beautiful notifications and confirmations
 *
 * @requires SweetAlert2 (https://sweetalert2.github.io/)
 */

const Notify = (function() {
    'use strict';

    // Default configuration
    const defaults = {
        toast: {
            position: 'center',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer);
                toast.addEventListener('mouseleave', Swal.resumeTimer);
            }
        },
        confirm: {
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes',
            cancelButtonText: 'Cancel',
            reverseButtons: true
        }
    };

    // Toast instance for notifications
    const Toast = Swal.mixin(defaults.toast);

    /**
     * Show success notification
     * @param {string} message - Message to display
     * @param {string} title - Optional title
     */
    function success(message, title = 'Success') {
        const isHtml = /<[a-z][\s\S]*>/i.test(message);
        Toast.fire({
            icon: 'success',
            title: title,
            [isHtml ? 'html' : 'text']: message
        });
    }

    /**
     * Show error notification
     * @param {string} message - Message to display
     * @param {string} title - Optional title
     */
    function error(message, title = 'Error') {
        const isHtml = /<[a-z][\s\S]*>/i.test(message);
        Toast.fire({
            icon: 'error',
            title: title,
            [isHtml ? 'html' : 'text']: message
        });
    }

    /**
     * Show warning notification
     * @param {string} message - Message to display
     * @param {string} title - Optional title
     */
    function warning(message, title = 'Warning') {
        const isHtml = /<[a-z][\s\S]*>/i.test(message);
        Toast.fire({
            icon: 'warning',
            title: title,
            [isHtml ? 'html' : 'text']: message
        });
    }

    /**
     * Show info notification
     * @param {string} message - Message to display
     * @param {string} title - Optional title
     */
    function info(message, title = 'Info') {
        const isHtml = /<[a-z][\s\S]*>/i.test(message);
        Toast.fire({
            icon: 'info',
            title: title,
            [isHtml ? 'html' : 'text']: message
        });
    }

    /**
     * Show confirmation dialog
     * @param {Object} options - Configuration options
     * @returns {Promise}
     */
    function confirm(options = {}) {
        const config = {
            ...defaults.confirm,
            title: options.title || 'Are you sure?',
            text: options.text || '',
            html: options.html || null,
            icon: options.icon || 'warning',
            confirmButtonText: options.confirmText || 'Yes',
            cancelButtonText: options.cancelText || 'Cancel',
            confirmButtonColor: options.confirmColor || '#3085d6',
            cancelButtonColor: options.cancelColor || '#6c757d'
        };

        return Swal.fire(config);
    }

    /**
     * Show delete confirmation dialog
     * @param {string} itemName - Name of item being deleted
     * @param {Function} onConfirm - Callback when confirmed
     * @param {Function} onCancel - Optional callback when cancelled
     */
    function confirmDelete(itemName, onConfirm, onCancel = null) {
        Swal.fire({
            title: 'Delete ' + itemName + '?',
            text: 'This action cannot be undone!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-trash"></i> Yes, delete it',
            cancelButtonText: 'Cancel',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                if (typeof onConfirm === 'function') {
                    onConfirm();
                }
            } else if (onCancel && typeof onCancel === 'function') {
                onCancel();
            }
        });
    }

    /**
     * Show void/cancel confirmation dialog
     * @param {string} itemName - Name of item being voided
     * @param {Function} onConfirm - Callback when confirmed
     * @param {boolean} requireReason - Whether to require a reason
     */
    function confirmVoid(itemName, onConfirm, requireReason = true) {
        const config = {
            title: 'Void ' + itemName + '?',
            text: 'This action cannot be undone!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-ban"></i> Yes, void it',
            cancelButtonText: 'Cancel',
            reverseButtons: true
        };

        if (requireReason) {
            config.input = 'textarea';
            config.inputLabel = 'Reason for voiding';
            config.inputPlaceholder = 'Enter reason...';
            config.inputValidator = (value) => {
                if (!value || value.trim() === '') {
                    return 'Please provide a reason';
                }
            };
        }

        Swal.fire(config).then((result) => {
            if (result.isConfirmed) {
                if (typeof onConfirm === 'function') {
                    onConfirm(result.value || '');
                }
            }
        });
    }

    /**
     * Show duplicate/similar data warning
     * @param {string} message - Warning message
     * @param {Function} onProceed - Callback to proceed anyway
     * @param {Function} onCancel - Callback to cancel
     */
    function duplicateWarning(message, onProceed, onCancel = null) {
        Swal.fire({
            title: 'Possible Duplicate',
            html: message,
            icon: 'warning',
            showCancelButton: true,
            showDenyButton: true,
            confirmButtonColor: '#28a745',
            denyButtonColor: '#ffc107',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-check"></i> Save Anyway',
            denyButtonText: '<i class="fas fa-edit"></i> Edit Data',
            cancelButtonText: 'Cancel',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                if (typeof onProceed === 'function') {
                    onProceed();
                }
            } else if (result.isDenied) {
                // User wants to edit - do nothing, let them edit
            } else if (onCancel && typeof onCancel === 'function') {
                onCancel();
            }
        });
    }

    /**
     * Show loading indicator
     * @param {string} message - Loading message
     */
    function loading(message = 'Please wait...') {
        Swal.fire({
            title: message,
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
    }

    /**
     * Close any open Swal dialog
     */
    function close() {
        Swal.close();
    }

    /**
     * Show success with action completed
     * @param {string} action - Action performed (added, updated, deleted)
     * @param {string} itemName - Name of item
     */
    function actionSuccess(action, itemName) {
        const messages = {
            'added': { title: 'Added!', text: itemName + ' has been added successfully.' },
            'created': { title: 'Created!', text: itemName + ' has been created successfully.' },
            'updated': { title: 'Updated!', text: itemName + ' has been updated successfully.' },
            'edited': { title: 'Updated!', text: itemName + ' has been updated successfully.' },
            'deleted': { title: 'Deleted!', text: itemName + ' has been deleted.' },
            'removed': { title: 'Removed!', text: itemName + ' has been removed.' },
            'voided': { title: 'Voided!', text: itemName + ' has been voided.' },
            'saved': { title: 'Saved!', text: itemName + ' has been saved successfully.' },
            'enrolled': { title: 'Enrolled!', text: itemName + ' has been enrolled successfully.' },
            'processed': { title: 'Processed!', text: itemName + ' has been processed successfully.' }
        };

        const msg = messages[action.toLowerCase()] || { title: 'Success!', text: 'Operation completed successfully.' };

        Toast.fire({
            icon: action.toLowerCase() === 'deleted' || action.toLowerCase() === 'voided' ? 'info' : 'success',
            title: msg.title,
            text: msg.text
        });
    }

    /**
     * Show form validation error
     * @param {string|Array} errors - Error message(s)
     */
    function validationError(errors) {
        let html = '';
        if (Array.isArray(errors)) {
            html = '<ul class="text-start mb-0">';
            errors.forEach(err => {
                html += '<li>' + err + '</li>';
            });
            html += '</ul>';
        } else {
            html = errors;
        }

        Swal.fire({
            title: 'Validation Error',
            html: html,
            icon: 'error',
            confirmButtonColor: '#3085d6'
        });
    }

    /**
     * Show input prompt
     * @param {string|Object} titleOrOptions - Title string or configuration options
     * @param {string} defaultValue - Default value (if first param is string)
     * @returns {Promise} - Resolves with the input value or null if cancelled/empty
     */
    function prompt(titleOrOptions = {}, defaultValue = '') {
        // Handle simple string arguments for convenience
        let options = {};
        if (typeof titleOrOptions === 'string') {
            options = {
                title: titleOrOptions,
                value: defaultValue
            };
        } else {
            options = titleOrOptions;
        }

        return Swal.fire({
            title: options.title || 'Enter value',
            input: options.type || 'text',
            inputLabel: options.label || '',
            inputPlaceholder: options.placeholder || '',
            inputValue: options.value || '',
            showCancelButton: true,
            confirmButtonText: options.confirmText || 'Submit',
            cancelButtonText: options.cancelText || 'Cancel',
            inputValidator: options.validator || null,
            inputOptions: options.options || null
        }).then(result => {
            // Return null if cancelled or empty, otherwise return the value
            if (result.isConfirmed && result.value && result.value.trim() !== '') {
                return result.value.trim();
            }
            return null;
        });
    }

    /**
     * Handle AJAX response and show appropriate notification
     * @param {Object} response - AJAX response object
     * @param {Object} options - Configuration options
     */
    function handleResponse(response, options = {}) {
        const successAction = options.successAction || 'saved';
        const itemName = options.itemName || 'Record';
        const onSuccess = options.onSuccess || null;
        const onError = options.onError || null;

        if (response.success) {
            actionSuccess(successAction, itemName);
            if (typeof onSuccess === 'function') {
                onSuccess(response);
            }
        } else {
            if (response.duplicate) {
                duplicateWarning(
                    response.error || 'Similar record already exists.',
                    options.onProceedDuplicate || null
                );
            } else {
                error(response.error || 'An error occurred');
            }
            if (typeof onError === 'function') {
                onError(response);
            }
        }
    }

    // Public API
    return {
        success,
        error,
        warning,
        info,
        confirm,
        confirmDelete,
        confirmVoid,
        duplicateWarning,
        loading,
        close,
        actionSuccess,
        validationError,
        prompt,
        handleResponse,
        // Expose Swal for advanced usage
        Swal: Swal
    };
})();

/**
 * Form Utilities Module
 * Handles form enhancements, validation, and submission
 */
const FormUtils = (function() {
    'use strict';

    /**
     * Initialize Select2 on all select elements with .select2 class
     */
    function initSelect2() {
        if (typeof $.fn.select2 === 'undefined') {
            console.warn('Select2 not loaded');
            return;
        }

        $('.select2').select2({
            theme: 'bootstrap-5',
            width: '100%',
            allowClear: true,
            placeholder: function() {
                return $(this).data('placeholder') || 'Select an option';
            }
        });

        // Multiple select
        $('.select2-multiple').select2({
            theme: 'bootstrap-5',
            width: '100%',
            allowClear: true,
            closeOnSelect: false,
            placeholder: function() {
                return $(this).data('placeholder') || 'Select options';
            }
        });

        // Tags/tagging
        $('.select2-tags').select2({
            theme: 'bootstrap-5',
            width: '100%',
            tags: true,
            tokenSeparators: [','],
            placeholder: function() {
                return $(this).data('placeholder') || 'Enter tags';
            }
        });
    }

    /**
     * Initialize form with AJAX submission
     * @param {string} formSelector - Form selector
     * @param {Object} options - Configuration options
     */
    function initAjaxForm(formSelector, options = {}) {
        const form = document.querySelector(formSelector);
        if (!form) return;

        form.addEventListener('submit', function(e) {
            e.preventDefault();

            const submitBtn = form.querySelector('[type="submit"]');
            const originalText = submitBtn ? submitBtn.innerHTML : '';

            // Disable submit button
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + (options.loadingText || 'Processing...');
            }

            // Show loading if specified
            if (options.showLoading) {
                Notify.loading(options.loadingText || 'Processing...');
            }

            const formData = new FormData(form);
            const url = options.url || form.action;

            fetch(url, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (options.showLoading) {
                    Notify.close();
                }

                Notify.handleResponse(data, {
                    successAction: options.successAction || 'saved',
                    itemName: options.itemName || 'Record',
                    onSuccess: (response) => {
                        if (options.resetOnSuccess) {
                            form.reset();
                            // Reset Select2 if present
                            $(form).find('.select2, .select2-multiple').val(null).trigger('change');
                        }
                        if (options.closeModalOnSuccess && options.modal) {
                            options.modal.hide();
                        }
                        if (typeof options.onSuccess === 'function') {
                            options.onSuccess(response);
                        }
                    },
                    onError: options.onError,
                    onProceedDuplicate: options.onProceedDuplicate
                });
            })
            .catch(err => {
                if (options.showLoading) {
                    Notify.close();
                }
                Notify.error(err.message || 'An error occurred');
                if (typeof options.onError === 'function') {
                    options.onError(err);
                }
            })
            .finally(() => {
                // Re-enable submit button
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            });
        });
    }

    /**
     * Check for duplicate before form submission
     * @param {Object} options - Configuration options
     */
    function checkDuplicate(options) {
        const { url, data, onUnique, onDuplicate, onError } = options;

        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        })
        .then(res => res.json())
        .then(response => {
            if (response.duplicate) {
                if (typeof onDuplicate === 'function') {
                    onDuplicate(response);
                } else {
                    Notify.duplicateWarning(
                        response.message || 'Similar record found: ' + (response.match || ''),
                        onUnique
                    );
                }
            } else {
                if (typeof onUnique === 'function') {
                    onUnique();
                }
            }
        })
        .catch(err => {
            if (typeof onError === 'function') {
                onError(err);
            } else {
                console.error('Duplicate check error:', err);
                // Proceed anyway on error
                if (typeof onUnique === 'function') {
                    onUnique();
                }
            }
        });
    }

    /**
     * Add delete button handler to table rows
     * @param {string} tableSelector - Table selector
     * @param {Object} options - Configuration options
     */
    function initDeleteButtons(tableSelector, options = {}) {
        const table = document.querySelector(tableSelector);
        if (!table) return;

        table.addEventListener('click', function(e) {
            const btn = e.target.closest('[data-delete]');
            if (!btn) return;

            e.preventDefault();

            const id = btn.dataset.delete;
            const name = btn.dataset.name || 'this item';
            const url = options.url || btn.dataset.url;

            Notify.confirmDelete(name, () => {
                Notify.loading('Deleting...');

                const formData = new FormData();
                formData.append('action', options.action || 'delete');
                formData.append('id', id);
                if (options.csrfToken) {
                    formData.append('csrf_token', options.csrfToken);
                }

                fetch(url, {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    Notify.close();
                    if (data.success) {
                        Notify.actionSuccess('deleted', name);
                        if (typeof options.onSuccess === 'function') {
                            options.onSuccess(id, data);
                        }
                        // Remove row if specified
                        if (options.removeRow) {
                            const row = btn.closest('tr');
                            if (row) {
                                row.remove();
                            }
                        }
                    } else {
                        Notify.error(data.error || 'Failed to delete');
                    }
                })
                .catch(err => {
                    Notify.close();
                    Notify.error(err.message || 'An error occurred');
                });
            });
        });
    }

    /**
     * Initialize all form utilities
     */
    function init() {
        // Initialize Select2 when DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initSelect2);
        } else {
            initSelect2();
        }
    }

    // Public API
    return {
        init,
        initSelect2,
        initAjaxForm,
        checkDuplicate,
        initDeleteButtons
    };
})();

// Auto-initialize on DOM ready
document.addEventListener('DOMContentLoaded', function() {
    FormUtils.init();
});
