<?php
/**
 * Authentication Header Include
 * Shared head content for all authentication pages (login, register, forgot password)
 */

// Load web settings if not already loaded
if (!isset($siteName)) {
    require_once __DIR__ . '/web_settings.php';
}

// Build full page title
$fullTitle = isset($title) ? $title : ($siteName ?? 'Template');

// Primary-color components for token interpolation
$_pc = ltrim($primaryColor ?? '#0d6efd', '#');
$_pcR = hexdec(substr($_pc, 0, 2));
$_pcG = hexdec(substr($_pc, 2, 2));
$_pcB = hexdec(substr($_pc, 4, 2));
$_pcDark = $primaryColorDark ?? '#0a58ca';
$_heroStart = !empty($loginHeroStart) ? $loginHeroStart : ($primaryColor ?? '#0d6efd');
$_heroEnd   = !empty($loginHeroEnd)   ? $loginHeroEnd   : $_pcDark;
$_heroEnabled = !isset($loginHeroEnabled) || $loginHeroEnabled === null ? true : (bool)$loginHeroEnabled;
$_darkDefault = !empty($darkModeEnabled) ? 'dark' : 'light';
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <meta name="description" content="<?= htmlspecialchars(($siteName ?? 'Template') . ' - ' . ($siteTagline ?? 'Enrollment System')) ?>" />
        <meta name="author" content="<?= htmlspecialchars($siteName ?? 'Template') ?>" />
        <meta name="theme-color" content="<?= htmlspecialchars($primaryColor ?? '#0d6efd') ?>" />
        <title><?= htmlspecialchars($fullTitle) ?></title>

        <!-- Favicon -->
        <?php if (!empty($siteFaviconUrl)): ?>
        <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($siteFaviconUrl) ?>" />
        <link rel="shortcut icon" href="<?= htmlspecialchars($siteFaviconUrl) ?>" />
        <?php else: ?>
        <link rel="icon" type="image/x-icon" href="assets/img/favicon.ico" />
        <?php endif; ?>

        <link href="css/bootstrap.min.css" rel="stylesheet" />
        <link href="css/styles.css" rel="stylesheet" />

        <!-- Template Design System -->
        <link href="css/colors_and_type.css" rel="stylesheet" />
        <link href="css/theme-overrides.css" rel="stylesheet" />

        <script nonce="<?= csp_nonce() ?>" src="js/all.js" crossorigin="anonymous"></script>

        <style nonce="<?= csp_nonce() ?>">
            :root {
                --primary:      <?= htmlspecialchars($primaryColor ?? '#0d6efd') ?>;
                --primary-dark: <?= htmlspecialchars($_pcDark) ?>;
                --primary-rgb:  <?= $_pcR ?>, <?= $_pcG ?>, <?= $_pcB ?>;
                --login-hero-start: <?= htmlspecialchars($_heroStart) ?>;
                --login-hero-end:   <?= htmlspecialchars($_heroEnd) ?>;
                --bs-primary: <?= htmlspecialchars($primaryColor ?? '#0d6efd') ?>;
                --bs-primary-rgb: <?= $_pcR ?>, <?= $_pcG ?>, <?= $_pcB ?>;
            }
            .btn-primary {
                --bs-btn-bg: <?= htmlspecialchars($primaryColor ?? '#0d6efd') ?>;
                --bs-btn-border-color: <?= htmlspecialchars($primaryColor ?? '#0d6efd') ?>;
                --bs-btn-hover-bg: <?= htmlspecialchars($_pcDark) ?>;
                --bs-btn-hover-border-color: <?= htmlspecialchars($_pcDark) ?>;
                --bs-btn-active-bg: <?= htmlspecialchars($_pcDark) ?>;
                --bs-btn-active-border-color: <?= htmlspecialchars($_pcDark) ?>;
            }
            a { color: <?= htmlspecialchars($primaryColor ?? '#0d6efd') ?>; }
            a:hover { color: <?= htmlspecialchars($_pcDark) ?>; }

            /* Logo medallion — straddles the card's top edge */
            .auth-card {
                position: relative;
                overflow: visible !important;
            }
            .auth-card .auth-card-bar { display: none; }
            .auth-card .auth-card-body { padding-top: 0; }
            .auth-card > .auth-logo {
                position: absolute;
                top: -55px;
                left: 50%;
                transform: translateX(-50%);
                width: 110px;
                height: 110px;
                border-radius: 50%;
                overflow: hidden;
                background: #fff;
                box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 2;
            }
            .auth-card > .auth-logo img {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }
        </style>
    </head>
    <body class="template auth-page<?= $_heroEnabled ? '' : ' hero-off' ?>">
        <div id="layoutAuthentication">
            <div id="layoutAuthentication_content">
                <main>
