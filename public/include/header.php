<?php
global $title, $siteName, $siteTagline, $siteFaviconUrl, $primaryColor, $primaryColorDark, $pageTitle;

// Build page title
$fullTitle = isset($pageTitle) && $pageTitle ? $pageTitle . ' | ' . ($siteName ?? 'Template') : ($siteName ?? 'Template');
if (isset($title) && $title) {
    $fullTitle = $title;
}

// Compute RGB components of primary color for rgba() usage
$_pc = ltrim($primaryColor ?? '#0d6efd', '#');
$_pcR = hexdec(substr($_pc, 0, 2));
$_pcG = hexdec(substr($_pc, 2, 2));
$_pcB = hexdec(substr($_pc, 4, 2));
$_pcDark = $primaryColorDark ?? '#0a58ca';
?>
<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="description" content="<?= htmlspecialchars($siteTagline ?? '') ?>" />
    <meta name="author" content="<?= htmlspecialchars($siteName ?? 'Template') ?>" />
    <meta name="theme-color" content="<?= htmlspecialchars($primaryColor ?? '#0d6efd') ?>" />
    <title><?= htmlspecialchars($fullTitle) ?></title>

    <!-- Favicon -->
    <?php if (!empty($siteFaviconUrl)): ?>
    <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($siteFaviconUrl) ?>" />
    <link rel="shortcut icon" href="<?= htmlspecialchars($siteFaviconUrl) ?>" />
    <link rel="apple-touch-icon" href="<?= htmlspecialchars($siteFaviconUrl) ?>" />
    <?php else: ?>
    <link rel="icon" type="image/x-icon" href="assets/img/favicon.ico" />
    <?php endif; ?>

    <!-- Bootstrap CSS -->
    <link href="css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">

    <!-- Custom CSS -->
    <link href="css/styles.css" rel="stylesheet" />
    <link href="css/notification.css" rel="stylesheet" />

    <!-- Select2 CSS (MIT License) -->
    <link href="css/select2.min.css" rel="stylesheet" />
    <link href="css/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

    <!-- SweetAlert2 CSS (MIT License) -->
    <link href="css/sweetalert2.min.css" rel="stylesheet" />

    <!-- Bootstrap Datepicker CSS -->
    <link href="css/bootstrap-datepicker.min.css" rel="stylesheet">

    <!-- Dynamic Theme (from Web Settings) -->
    <style nonce="<?= csp_nonce() ?>">
        :root {
            --bs-primary: <?= htmlspecialchars($primaryColor ?? '#0d6efd') ?>;
            --bs-primary-rgb: <?= $_pcR ?>, <?= $_pcG ?>, <?= $_pcB ?>;
            --bs-link-color: <?= htmlspecialchars($primaryColor ?? '#0d6efd') ?>;
            --bs-link-hover-color: <?= htmlspecialchars($_pcDark) ?>;
        }
        /* Primary buttons */
        .btn-primary {
            --bs-btn-bg: <?= htmlspecialchars($primaryColor ?? '#0d6efd') ?>;
            --bs-btn-border-color: <?= htmlspecialchars($primaryColor ?? '#0d6efd') ?>;
            --bs-btn-hover-bg: <?= htmlspecialchars($_pcDark) ?>;
            --bs-btn-hover-border-color: <?= htmlspecialchars($_pcDark) ?>;
            --bs-btn-active-bg: <?= htmlspecialchars($_pcDark) ?>;
            --bs-btn-active-border-color: <?= htmlspecialchars($_pcDark) ?>;
            --bs-btn-disabled-bg: <?= htmlspecialchars($primaryColor ?? '#0d6efd') ?>;
            --bs-btn-disabled-border-color: <?= htmlspecialchars($primaryColor ?? '#0d6efd') ?>;
        }
        .btn-outline-primary {
            --bs-btn-color: <?= htmlspecialchars($primaryColor ?? '#0d6efd') ?>;
            --bs-btn-border-color: <?= htmlspecialchars($primaryColor ?? '#0d6efd') ?>;
            --bs-btn-hover-bg: <?= htmlspecialchars($primaryColor ?? '#0d6efd') ?>;
            --bs-btn-hover-border-color: <?= htmlspecialchars($primaryColor ?? '#0d6efd') ?>;
            --bs-btn-active-bg: <?= htmlspecialchars($primaryColor ?? '#0d6efd') ?>;
            --bs-btn-active-border-color: <?= htmlspecialchars($primaryColor ?? '#0d6efd') ?>;
        }
        /* Primary backgrounds */
        .bg-primary {
            background-color: <?= htmlspecialchars($primaryColor ?? '#0d6efd') ?> !important;
        }
        .text-primary {
            color: <?= htmlspecialchars($primaryColor ?? '#0d6efd') ?> !important;
        }
        /* Links */
        a { color: <?= htmlspecialchars($primaryColor ?? '#0d6efd') ?>; }
        a:hover { color: <?= htmlspecialchars($_pcDark) ?>; }
        /* Form focus */
        .form-control:focus, .form-select:focus {
            border-color: <?= htmlspecialchars($primaryColor ?? '#0d6efd') ?>;
            box-shadow: 0 0 0 0.25rem rgba(<?= $_pcR ?>, <?= $_pcG ?>, <?= $_pcB ?>, 0.25);
        }
        .form-check-input:checked {
            background-color: <?= htmlspecialchars($primaryColor ?? '#0d6efd') ?>;
            border-color: <?= htmlspecialchars($primaryColor ?? '#0d6efd') ?>;
        }
        /* Pagination */
        .page-link { color: <?= htmlspecialchars($primaryColor ?? '#0d6efd') ?>; }
        .page-item.active .page-link {
            background-color: <?= htmlspecialchars($primaryColor ?? '#0d6efd') ?>;
            border-color: <?= htmlspecialchars($primaryColor ?? '#0d6efd') ?>;
        }
        /* Nav tabs/pills */
        .nav-link.active, .nav-pills .nav-link.active {
            color: <?= htmlspecialchars($primaryColor ?? '#0d6efd') ?>;
        }
        .nav-pills .nav-link.active {
            background-color: <?= htmlspecialchars($primaryColor ?? '#0d6efd') ?>;
            color: #fff;
        }
        /* Spinner */
        .spinner-border.text-primary {
            color: <?= htmlspecialchars($primaryColor ?? '#0d6efd') ?> !important;
        }
        /* Sidebar active link accent */
        .sb-sidenav-dark .sb-sidenav-menu .nav-link.active {
            color: <?= htmlspecialchars($primaryColor ?? '#0d6efd') ?>;
        }
        .sb-sidenav-dark .sb-sidenav-menu .nav-link.active .sb-nav-link-icon {
            color: <?= htmlspecialchars($primaryColor ?? '#0d6efd') ?>;
        }
        .sb-sidenav-dark .sb-sidenav-menu .nav-link:hover {
            color: <?= htmlspecialchars($primaryColor ?? '#0d6efd') ?>;
        }
        /* Breadcrumb active */
        .breadcrumb-item.active { color: <?= htmlspecialchars($primaryColor ?? '#0d6efd') ?>; }
        /* Badge primary */
        .badge.bg-primary {
            background-color: <?= htmlspecialchars($primaryColor ?? '#0d6efd') ?> !important;
        }
        /* Progress bar */
        .progress-bar {
            background-color: <?= htmlspecialchars($primaryColor ?? '#0d6efd') ?>;
        }
        /* Dropdown active */
        .dropdown-item.active, .dropdown-item:active {
            background-color: <?= htmlspecialchars($primaryColor ?? '#0d6efd') ?>;
        }
        /* Card primary */
        .card.bg-primary, .card.border-primary {
            border-color: <?= htmlspecialchars($primaryColor ?? '#0d6efd') ?> !important;
        }
        /* Select2 focus */
        .select2-container--bootstrap-5 .select2-selection--single:focus,
        .select2-container--bootstrap-5 .select2-selection--multiple:focus,
        .select2-container--bootstrap-5.select2-container--focus .select2-selection {
            border-color: <?= htmlspecialchars($primaryColor ?? '#0d6efd') ?>;
            box-shadow: 0 0 0 0.25rem rgba(<?= $_pcR ?>, <?= $_pcG ?>, <?= $_pcB ?>, 0.25);
        }
    </style>

    <!-- Font Awesome -->
    <script nonce="<?= csp_nonce() ?>" src="js/all.js" crossorigin="anonymous"></script>

    <!-- jQuery -->
    <script nonce="<?= csp_nonce() ?>" src="js/jquery-3.6.0.min.js"></script>

    <!-- Bootstrap Bundle -->
    <script nonce="<?= csp_nonce() ?>" src="js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>

    <!-- Bootstrap Datepicker -->
    <script nonce="<?= csp_nonce() ?>" src="js/bootstrap-datepicker.min.js"></script>

    <!-- Select2 JS (MIT License) -->
    <script nonce="<?= csp_nonce() ?>" src="js/select2.min.js"></script>

    <!-- SweetAlert2 JS (MIT License) -->
    <script nonce="<?= csp_nonce() ?>" src="js/sweetalert2.min.js"></script>

    <!-- Notification Utilities (uses SweetAlert2) -->
    <script nonce="<?= csp_nonce() ?>" src="assets/js/notifications.js"></script>

    <!-- App Utilities: handleAjaxResponse, submitForm, initDataTable, initSelect2InModal -->
    <script nonce="<?= csp_nonce() ?>" src="assets/js/app.js"></script>

    <!-- Custom JS -->
    <script nonce="<?= csp_nonce() ?>" src="js/notification.js"></script>
    <script nonce="<?= csp_nonce() ?>" src="js/scripts.js"></script>
</head>