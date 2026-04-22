<?php
/**
 * Error Page Template
 * Used by all error pages for consistent styling
 */

// Ensure SecurityHeaders are initialized.
// When Apache's ErrorDocument serves this file directly (e.g. 404),
// there is no bootstrap — so we initialize here as a fallback.
if (!class_exists('SecurityHeaders')) {
    $secFile = __DIR__ . '/../../core/SecurityHeaders.php';
    if (file_exists($secFile)) {
        require_once $secFile;
    }
}
if (class_exists('SecurityHeaders') && !headers_sent()) {
    SecurityHeaders::init();
}

// Load web settings for dynamic site name
require_once __DIR__ . '/../include/web_settings.php';

// Default values
$errorCode = $errorCode ?? 'Error';
$errorTitle = $errorTitle ?? 'An Error Occurred';
$errorMessage = $errorMessage ?? 'Something went wrong. Please try again.';
$errorIcon = $errorIcon ?? 'fa-exclamation-circle';
$showLoginButton = $showLoginButton ?? false;
$showLogoutButton = $showLogoutButton ?? false;
$isMaintenance = $isMaintenance ?? false;
$isOffline = $isOffline ?? false;

// Get the base URL - handle both direct access and includes
$baseUrl = $_SERVER['SERVER_HOST'] ?? '';

// Clean up the base URL
$baseUrl = rtrim($baseUrl, '/');

// Primary color + hero gradient (fall back to design defaults when DB unavailable)
$_ep_primary      = $primaryColor      ?? '#0d6efd';
$_ep_primary_dark = $primaryColorDark  ?? '#0a58ca';
$_ep_hero_start   = !empty($loginHeroStart) ? $loginHeroStart : $_ep_primary;
$_ep_hero_end     = !empty($loginHeroEnd)   ? $loginHeroEnd   : $_ep_primary_dark;
$_ep_dark_default = !empty($darkModeEnabled) ? 'dark' : 'light';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $errorCode ? "$errorCode - " : '' ?><?= htmlspecialchars($errorTitle) ?> | <?= htmlspecialchars($siteName ?? 'Template') ?></title>
    <?php if (!empty($siteFaviconUrl)): ?>
    <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($siteFaviconUrl) ?>" />
    <link rel="shortcut icon" href="<?= htmlspecialchars($siteFaviconUrl) ?>" />
    <?php endif; ?>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/all.min.css" rel="stylesheet">
    <link href="css/colors_and_type.css" rel="stylesheet">
    <link href="css/theme-overrides.css" rel="stylesheet">

    <style nonce="<?= csp_nonce() ?>">
        :root {
            --primary:          <?= htmlspecialchars($_ep_primary) ?>;
            --primary-dark:     <?= htmlspecialchars($_ep_primary_dark) ?>;
            --login-hero-start: <?= htmlspecialchars($_ep_hero_start) ?>;
            --login-hero-end:   <?= htmlspecialchars($_ep_hero_end) ?>;
        }

        body.error-body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: linear-gradient(135deg, var(--login-hero-start), var(--login-hero-end));
            position: relative;
            overflow-x: hidden;
        }

        body.error-body::before {
            content: "";
            position: absolute;
            inset: 0;
            background-image: repeating-linear-gradient(45deg,
                rgba(255,255,255,0.04) 0 2px, transparent 2px 24px);
            pointer-events: none;
        }

        .error-container {
            position: relative;
            z-index: 1;
            background: var(--surface);
            border-radius: var(--radius-xl);
            box-shadow: 0 1rem 3rem rgba(0, 0, 0, 0.25);
            max-width: 500px;
            width: 100%;
            text-align: center;
            padding: 50px 40px;
            animation: fadeInUp 0.5s ease-out;
            color: var(--fg);
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(24px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .error-icon {
            width: 112px;
            height: 112px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 28px;
            font-size: 44px;
            color: #fff;
            animation: pulse 2.4s infinite;
        }

        .error-icon.error-401,
        .error-icon.error-503,
        .error-icon.error-session       { background: var(--warning); color: #212529; }
        .error-icon.error-403,
        .error-icon.error-500           { background: var(--danger); }
        .error-icon.error-404           { background: var(--primary); }
        .error-icon.error-maintenance   { background: var(--info); }
        .error-icon.error-offline       { background: var(--secondary); }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.04); }
        }

        .error-code {
            font-size: 72px;
            font-weight: 800;
            line-height: 1;
            margin-bottom: 10px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .error-title {
            font-size: 28px;
            font-weight: 700;
            color: var(--fg);
            margin-bottom: 15px;
        }

        .error-message {
            font-size: 16px;
            color: var(--fg-muted);
            line-height: 1.6;
            margin-bottom: 30px;
        }

        .error-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-custom {
            padding: 10px 24px;
            border-radius: var(--radius-pill);
            font-weight: 600;
            text-decoration: none;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary-custom {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff;
            border: 0;
        }

        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow);
            color: #fff;
        }

        .btn-outline-custom {
            background: transparent;
            color: var(--fg);
            border: 2px solid var(--border);
        }

        .btn-outline-custom:hover {
            background: var(--surface-alt);
            border-color: var(--gray-400);
            color: var(--fg);
        }

        .error-details {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
        }

        .error-details p {
            font-size: 13px;
            color: var(--fg-muted);
            margin: 0;
        }

        .error-details code {
            background: var(--surface-alt);
            padding: 2px 8px;
            border-radius: var(--radius-sm);
            font-size: 12px;
            color: var(--fg);
        }

        .maintenance-progress { margin: 20px 0; }
        .progress-bar-animated { animation: progress-bar-stripes 1s linear infinite; }
        .retry-countdown { font-size: 14px; color: var(--fg-muted); margin-top: 15px; }

        @media (max-width: 576px) {
            .error-container { padding: 40px 25px; }
            .error-code { font-size: 56px; }
            .error-title { font-size: 22px; }
            .error-icon { width: 96px; height: 96px; font-size: 38px; }
            .error-actions { flex-direction: column; }
            .btn-custom { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body class="template error-body">
    <div class="error-container">
        <?php
        $iconClass = 'error-500'; // default
        if ($errorCode == 401) $iconClass = 'error-401';
        elseif ($errorCode == 403) $iconClass = 'error-403';
        elseif ($errorCode == 404) $iconClass = 'error-404';
        elseif ($errorCode == 500) $iconClass = 'error-500';
        elseif ($errorCode == 503) $iconClass = 'error-503';
        elseif ($isMaintenance) $iconClass = 'error-maintenance';
        elseif ($isOffline) $iconClass = 'error-offline';
        elseif ($showLoginButton && !$errorCode) $iconClass = 'error-session';
        ?>

        <div class="error-icon <?= $iconClass ?>">
            <i class="fas <?= htmlspecialchars($errorIcon) ?>"></i>
        </div>

        <?php if ($errorCode): ?>
        <div class="error-code"><?= htmlspecialchars($errorCode) ?></div>
        <?php endif; ?>

        <h1 class="error-title"><?= htmlspecialchars($errorTitle) ?></h1>
        <p class="error-message"><?= htmlspecialchars($errorMessage) ?></p>

        <?php if ($isMaintenance): ?>
        <div class="maintenance-progress">
            <div class="progress" style="height: 8px;">
                <div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 75%; background: linear-gradient(135deg, var(--info), var(--primary));"></div>
            </div>
            <small class="text-muted mt-2 d-block">We'll be back soon!</small>
        </div>
        <?php endif; ?>

        <div class="error-actions">
            <?php if ($showLoginButton): ?>
            <a href="<?= $baseUrl ?>/login.php" class="btn-custom btn-primary-custom">
                <i class="fas fa-sign-in-alt"></i> Log In
            </a>
            <?php endif; ?>

            <?php if ($showLogoutButton): ?>
            <a href="<?= $baseUrl ?>/logout.php" class="btn-custom btn-primary-custom">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
            <?php endif; ?>

            <?php if (!$showLogoutButton): ?>
            <a href="<?= $baseUrl ?>/index.php" class="btn-custom <?= $showLoginButton ? 'btn-outline-custom' : 'btn-primary-custom' ?>">
                <i class="fas fa-home"></i> Go Home
            </a>
            <?php endif; ?>

            <button data-native="history.back" class="btn-custom btn-outline-custom">
                <i class="fas fa-arrow-left"></i> Go Back
            </button>
        </div>

        <?php if ($isOffline): ?>
        <div class="retry-countdown">
            <i class="fas fa-sync-alt fa-spin"></i> Checking connection...
        </div>
        <script nonce="<?= csp_nonce() ?>">
            // Auto-retry connection
            setInterval(function() {
                fetch('<?= $baseUrl ?>/index.php', { method: 'HEAD', cache: 'no-cache' })
                    .then(function() {
                        window.location.reload();
                    })
                    .catch(function() {
                        // Still offline
                    });
            }, 5000);
        </script>
        <?php endif; ?>

        <div class="error-details">
            <p>
                <i class="fas fa-clock"></i>
                <?= date('F j, Y \a\t g:i A') ?>
                <?php if (isset($_SERVER['REQUEST_URI'])): ?>
                <br><code><?= htmlspecialchars($_SERVER['REQUEST_URI']) ?></code>
                <?php endif; ?>
            </p>
        </div>
    </div>

    <script nonce="<?= csp_nonce() ?>" src="js/bootstrap.bundle.min.js"></script>
    <script nonce="<?= csp_nonce() ?>">
    document.addEventListener('click', function(e) {
        var el = e.target.closest('[data-native]');
        if (!el) return;
        e.preventDefault();
        if (el.dataset.native === 'history.back') history.back();
    });
    </script>
</body>
</html>
