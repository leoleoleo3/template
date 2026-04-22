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
        <script nonce="<?= csp_nonce() ?>" src="js/all.js" crossorigin="anonymous"></script>
        <style nonce="<?= csp_nonce() ?>">
            body.bg-primary {
                background-color: <?= htmlspecialchars($primaryColor ?? '#0d6efd') ?> !important;
            }
        </style>
    </head>
    <body class="bg-primary">
        <div id="layoutAuthentication">
            <div id="layoutAuthentication_content">
                <main>
