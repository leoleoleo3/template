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
    <style nonce="<?= csp_nonce() ?>">
        :root {
            --primary-color: #4e73df;
            --secondary-color: #858796;
            --success-color: #1cc88a;
            --danger-color: #e74a3b;
            --warning-color: #f6c23e;
            --info-color: #36b9cc;
            --dark-color: #5a5c69;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .error-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 500px;
            width: 100%;
            text-align: center;
            padding: 50px 40px;
            animation: fadeInUp 0.6s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .error-icon {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            font-size: 50px;
            animation: pulse 2s infinite;
        }

        .error-icon.error-401 { background: linear-gradient(135deg, #f6c23e, #f4b619); color: white; }
        .error-icon.error-403 { background: linear-gradient(135deg, #e74a3b, #c0392b); color: white; }
        .error-icon.error-404 { background: linear-gradient(135deg, #4e73df, #224abe); color: white; }
        .error-icon.error-500 { background: linear-gradient(135deg, #e74a3b, #c0392b); color: white; }
        .error-icon.error-503 { background: linear-gradient(135deg, #f6c23e, #f4b619); color: white; }
        .error-icon.error-maintenance { background: linear-gradient(135deg, #36b9cc, #1a8a9a); color: white; }
        .error-icon.error-offline { background: linear-gradient(135deg, #858796, #5a5c69); color: white; }
        .error-icon.error-session { background: linear-gradient(135deg, #f6c23e, #f4b619); color: white; }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .error-code {
            font-size: 72px;
            font-weight: 800;
            color: var(--dark-color);
            line-height: 1;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .error-title {
            font-size: 28px;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 15px;
        }

        .error-message {
            font-size: 16px;
            color: var(--secondary-color);
            line-height: 1.6;
            margin-bottom: 30px;
        }

        .error-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-custom {
            padding: 12px 30px;
            border-radius: 50px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary-custom {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
        }

        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
            color: white;
        }

        .btn-outline-custom {
            background: transparent;
            color: var(--dark-color);
            border: 2px solid #e3e6f0;
        }

        .btn-outline-custom:hover {
            background: #f8f9fc;
            border-color: #d1d3e2;
            color: var(--dark-color);
        }

        .error-details {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e3e6f0;
        }

        .error-details p {
            font-size: 13px;
            color: var(--secondary-color);
            margin: 0;
        }

        .error-details code {
            background: #f8f9fc;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 12px;
        }

        /* Maintenance specific */
        .maintenance-progress {
            margin: 20px 0;
        }

        .progress-bar-animated {
            animation: progress-bar-stripes 1s linear infinite;
        }

        /* Offline specific */
        .retry-countdown {
            font-size: 14px;
            color: var(--secondary-color);
            margin-top: 15px;
        }

        /* Responsive */
        @media (max-width: 576px) {
            .error-container {
                padding: 40px 25px;
            }

            .error-code {
                font-size: 56px;
            }

            .error-title {
                font-size: 22px;
            }

            .error-icon {
                width: 100px;
                height: 100px;
                font-size: 40px;
            }

            .error-actions {
                flex-direction: column;
            }

            .btn-custom {
                width: 100%;
                justify-content: center;
            }
        }

        /* Dark mode support */
        @media (prefers-color-scheme: dark) {
            body {
                background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            }

            .error-container {
                background: #1e1e2d;
            }

            .error-title {
                color: #e0e0e0;
            }

            .error-message {
                color: #a0a0a0;
            }

            .btn-outline-custom {
                border-color: #3a3a4a;
                color: #e0e0e0;
            }

            .btn-outline-custom:hover {
                background: #2a2a3a;
                border-color: #4a4a5a;
                color: #e0e0e0;
            }

            .error-details {
                border-top-color: #3a3a4a;
            }

            .error-details p {
                color: #a0a0a0;
            }

            .error-details code {
                background: #2a2a3a;
                color: #e0e0e0;
            }
        }
    </style>
</head>
<body>
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
                <div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 75%; background: linear-gradient(135deg, #36b9cc, #1a8a9a);"></div>
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
