<?php
/**
 * Master Layout Template
 *
 * This is the main layout framework. Pages only need to define their content.
 * The layout handles header, sidebar, footer, and all HTML structure.
 *
 * Required variables (set before including this file):
 * - $pageTitle: Page title
 * - $pageContent: Page content (use output buffering)
 * - Optional: $breadcrumbs (array), $pageScripts, $pageStyles
 */

require_once __DIR__ . '/../../core/FlashMessage.php';

// Load web settings (site name, logo, favicon, etc.)
require_once __DIR__ . '/web_settings.php';

if (!isset($pageTitle)) {
    $pageTitle = $siteName ?? 'Template';
}

if (!isset($pageContent)) {
    die('Error: $pageContent is required for layout.php');
}
?>
<!DOCTYPE html>
<html lang="en">
    <?php include_once __DIR__ . '/header.php'; ?>
    <body class="sb-nav-fixed">
        <!-- Loading Overlay -->
        <div id="loadingOverlay" class="loading-overlay">
            <div class="loading-spinner">
                <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-3 text-white">Loading...</p>
            </div>
        </div>

        <style nonce="<?= csp_nonce() ?>">
            .loading-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.7);
                display: flex;
                justify-content: center;
                align-items: center;
                z-index: 9999;
                transition: opacity 0.3s ease;
            }
            .loading-overlay.hidden {
                opacity: 0;
                pointer-events: none;
            }
            .loading-spinner {
                text-align: center;
            }
            .loading-spinner .spinner-border {
                border-width: 0.3em;
            }
        </style>

        <?php include_once __DIR__ . '/topbar.php'; ?>
        <div id="layoutSidenav">
            <?php include_once __DIR__ . '/sidebar.php'; ?>
            <div id="layoutSidenav_content">
                <main>
                    <div class="container-fluid px-4">
                        <!-- Page Title -->
                        <h1 class="mt-4"><?= htmlspecialchars($pageTitle) ?></h1>

                        <!-- Breadcrumbs (optional) -->
                        <?php if (isset($breadcrumbs) && !empty($breadcrumbs)): ?>
                        <ol class="breadcrumb mb-4">
                            <?php foreach ($breadcrumbs as $index => $crumb): ?>
                                <?php if ($index === count($breadcrumbs) - 1): ?>
                                    <li class="breadcrumb-item active"><?= htmlspecialchars($crumb['title']) ?></li>
                                <?php else: ?>
                                    <li class="breadcrumb-item">
                                        <a href="<?= htmlspecialchars($crumb['url']) ?>">
                                            <?= htmlspecialchars($crumb['title']) ?>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </ol>
                        <?php endif; ?>

                        <!-- Page Content -->
                        <?= $pageContent ?>
                    </div>
                </main>
                <?php include_once __DIR__ . '/auth_footer.php'; ?>
            </div>
        </div>

        <!-- Core Scripts -->
        <script nonce="<?= csp_nonce() ?>" src="js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
        <script nonce="<?= csp_nonce() ?>" src="js/scripts.js"></script>

        <!-- Loading Overlay Control -->
        <script nonce="<?= csp_nonce() ?>">
            // Global loading overlay functions
            window.showLoading = function(message = 'Loading...') {
                const overlay = document.getElementById('loadingOverlay');
                const text = overlay.querySelector('p');
                if (text) text.textContent = message;
                overlay.classList.remove('hidden');
            };

            window.hideLoading = function() {
                const overlay = document.getElementById('loadingOverlay');
                overlay.classList.add('hidden');
            };

            // Hide loading overlay when page is fully loaded
            window.addEventListener('load', function() {
                setTimeout(hideLoading, 300);
            });

            // Show loading on page unload (navigation)
            window.addEventListener('beforeunload', function() {
                showLoading('Loading...');
            });
        </script>

        <!-- Event Delegation Layer (CSP-safe replacement for all inline onclick/onchange) -->
        <script nonce="<?= csp_nonce() ?>">
        (function () {
            'use strict';
            function coerce(v) {
                if (v === '' || v == null) return v;
                if (/^-?\d+$/.test(v)) return parseInt(v, 10);
                if (/^-?\d+\.\d+$/.test(v)) return parseFloat(v);
                return v;
            }
            function collectArgs(el) {
                var a = [], i = 0;
                while (el.dataset['arg' + i] !== undefined) { a.push(coerce(el.dataset['arg' + i])); i++; }
                return a;
            }
            function find(el, attr) {
                while (el && el !== document.body) {
                    if (el.dataset && el.dataset[attr] !== undefined) return el;
                    el = el.parentElement;
                }
                return null;
            }
            document.addEventListener('click', function (e) {
                var n = find(e.target, 'native');
                if (n) {
                    e.preventDefault();
                    var v = n.dataset.native;
                    if (v === 'window.print') { window.print(); return; }
                    if (v === 'history.back') { history.back(); return; }
                    return;
                }
                var el = find(e.target, 'action');
                if (!el) return;
                if (el.tagName === 'A' || el.dataset.preventDefault !== undefined) e.preventDefault();
                var fn = window[el.dataset.action];
                if (typeof fn !== 'function') { console.warn('[delegate] No function:', el.dataset.action); return; }
                if (el.dataset.argJson !== undefined) {
                    try { fn(JSON.parse(el.dataset.argJson)); } catch(err) { console.error('[delegate] JSON:', err); }
                    return;
                }
                fn.apply(null, collectArgs(el));
            });
            document.addEventListener('change', function (e) {
                var el = e.target;
                if (!el || !el.dataset) return;
                if (el.dataset.autoSubmit !== undefined) { var f = el.closest('form'); if (f) f.submit(); return; }
                if (el.dataset.changeElement !== undefined) {
                    var fn = window[el.dataset.changeElement];
                    if (typeof fn === 'function') fn(coerce(el.dataset.arg0), el);
                    return;
                }
                if (el.dataset.changeSelf !== undefined) { var fn = window[el.dataset.changeSelf]; if (typeof fn === 'function') fn(); return; }
                if (el.dataset.change !== undefined) { var fn = window[el.dataset.change]; if (typeof fn === 'function') fn(el.value); return; }
            });
        })();
        </script>

        <!-- Page-specific Scripts (optional) -->
        <?php if (isset($pageScripts)): ?>
            <?php
            // Auto-inject CSP nonce into all <script> and <style> tags
            // so page-specific inline code is whitelisted by the CSP.
            $nonce = csp_nonce();
            $pageScripts = preg_replace(
                '/<(script|style)(?![^>]*\bnonce\b)([^>]*)>/i',
                '<$1$2 nonce="' . $nonce . '">',
                $pageScripts
            );
            ?>
            <?= $pageScripts ?>
        <?php endif; ?>

        <!-- Flash Messages (auto-rendered from PHP session) -->
        <?= FlashMessage::render() ?>
    </body>
</html>
