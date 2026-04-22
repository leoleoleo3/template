<?php
/**
 * Template Login Page
 * Uses Session management system for authentication
 */

// Initialize session (includes database connection)
require_once 'include/session.php';

// Generate CSRF token for the form
$session->generateCSRFToken();

// Redirect to dashboard if already logged in
if ($session->isAuthenticated()) {
    header('Location: index.php');
    exit;
}

// Load web settings early for title
require_once __DIR__ . '/include/web_settings.php';
$title = 'Login | ' . ($siteName ?? 'Template');
$errors = [];
$success = '';
$email_value = '';

// Retrieve any stored messages
if (isset($_SESSION['login_error'])) {
    $errors[] = $_SESSION['login_error'];
    unset($_SESSION['login_error']);
}

if (isset($_SESSION['login_success'])) {
    $success = $_SESSION['login_success'];
    unset($_SESSION['login_success']);
}

if (isset($_SESSION['login_email'])) {
    $email_value = $_SESSION['login_email'];
    unset($_SESSION['login_email']);
}

if (isset($_GET['expired'])) {
    $errors[] = 'Your session has expired. Please login again.';
}

$currentYear = date('Y');
$displaySiteName = htmlspecialchars($siteName ?? 'Template');
$displayTagline = htmlspecialchars($siteTagline ?? 'Portal');
$color = htmlspecialchars($primaryColor ?? '#0d6efd');
$colorDark = htmlspecialchars($primaryColorDark ?? '#0a58ca');
$displayFooter = !empty($footerText) ? htmlspecialchars($footerText) : '&copy; ' . $currentYear . ' ' . $displaySiteName;

// Primary-color RGB for rgba()
$hexClean = ltrim($color, '#');
$colorR = hexdec(substr($hexClean, 0, 2));
$colorG = hexdec(substr($hexClean, 2, 2));
$colorB = hexdec(substr($hexClean, 4, 2));

// Hero gradient: user overrides > primary fallback
$_heroStart = !empty($loginHeroStart) ? $loginHeroStart : ($primaryColor ?? '#0d6efd');
$_heroEnd   = !empty($loginHeroEnd)   ? $loginHeroEnd   : ($primaryColorDark ?? '#0a58ca');
$_heroEnabled = !isset($loginHeroEnabled) ? true : (bool)$loginHeroEnabled;
$_darkDefault = !empty($darkModeEnabled) ? 'dark' : 'light';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="<?= $displaySiteName ?> - <?= $displayTagline ?>">
    <meta name="theme-color" content="<?= $color ?>">
    <title><?= htmlspecialchars($title) ?></title>

    <?php if (!empty($siteFaviconUrl)): ?>
    <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($siteFaviconUrl) ?>">
    <?php else: ?>
    <link rel="icon" type="image/x-icon" href="assets/img/favicon.ico">
    <?php endif; ?>

    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/colors_and_type.css" rel="stylesheet">
    <link href="css/theme-overrides.css" rel="stylesheet">

    <script nonce="<?= csp_nonce() ?>" src="js/all.js" crossorigin="anonymous"></script>

    <style nonce="<?= csp_nonce() ?>">
        :root {
            --primary:      <?= $color ?>;
            --primary-dark: <?= $colorDark ?>;
            --primary-rgb:  <?= $colorR ?>, <?= $colorG ?>, <?= $colorB ?>;
            --login-hero-start: <?= htmlspecialchars($_heroStart) ?>;
            --login-hero-end:   <?= htmlspecialchars($_heroEnd) ?>;
            --bs-primary: <?= $color ?>;
            --bs-primary-rgb: <?= $colorR ?>, <?= $colorG ?>, <?= $colorB ?>;
        }
        .btn-primary {
            --bs-btn-bg: <?= $color ?>;
            --bs-btn-border-color: <?= $color ?>;
            --bs-btn-hover-bg: <?= $colorDark ?>;
            --bs-btn-hover-border-color: <?= $colorDark ?>;
            --bs-btn-active-bg: <?= $colorDark ?>;
        }
        .alert { font-size: 0.85rem; border-radius: 8px; margin-bottom: 14px; }
        .btn-login {
            background: linear-gradient(135deg, <?= $color ?> 0%, <?= $colorDark ?> 100%);
            border: 0;
            color: #fff;
        }
        .btn-login:hover { opacity: 0.92; color: #fff; }
        .password-wrapper { position: relative; }
        .password-toggle {
            position: absolute; right: 12px; top: 50%;
            transform: translateY(-50%);
            background: none; border: 0;
            color: var(--fg-subtle); cursor: pointer;
            padding: 0; font-size: 1rem;
        }
        .password-toggle:hover { color: var(--fg-muted); }

        /* Login variant: logo medallion sits on the very top-center of the card.
           Matches Ashe/public/login.php: logo is a direct child of the card,
           positioned absolute at top: -55px so it straddles the card's top edge. */
        .auth-card .auth-card-bar { display: none; }
        .auth-card {
            position: relative;
            overflow: visible !important;
        }
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

<div class="auth-card">
    <div class="auth-logo">
        <?php if (!empty($siteLogoUrl)): ?>
            <img src="<?= htmlspecialchars($siteLogoUrl) ?>" alt="<?= $displaySiteName ?>">
        <?php else: ?>
            <span class="auth-logo-fallback"><?= htmlspecialchars(mb_substr($siteName ?? 'A', 0, 1)) ?></span>
        <?php endif; ?>
    </div>
    <div class="auth-card-bar"></div>
    <div class="auth-card-body">
        <h1 class="auth-brand"><?= $displaySiteName ?></h1>
        <p class="auth-brand-tag"><?= $displayTagline ?></p>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-1"></i>
                <?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php foreach ($errors as $error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-1"></i>
                <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endforeach; ?>

        <form method="POST" action="login_process.php">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($session->getCSRFToken()) ?>">

            <div class="mb-3">
                <label class="form-label" for="inputEmail">Email</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                    <input
                        class="form-control<?= !empty($errors) ? ' is-invalid' : '' ?>"
                        id="inputEmail"
                        type="email"
                        name="email"
                        placeholder="you@example.com"
                        value="<?= htmlspecialchars($email_value) ?>"
                        required
                        autofocus>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="inputPassword">Password</label>
                <div class="input-group password-wrapper">
                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                    <input
                        class="form-control<?= !empty($errors) ? ' is-invalid' : '' ?>"
                        id="inputPassword"
                        type="password"
                        name="password"
                        placeholder="Enter your password"
                        required>
                    <button type="button" class="password-toggle" data-action="togglePassword" tabindex="-1" aria-label="Show password">
                        <i class="fas fa-eye" id="toggleIcon"></i>
                    </button>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="rememberMe" name="remember">
                    <label class="form-check-label small text-muted" for="rememberMe">Remember me</label>
                </div>
                <a href="forgot_password.php" class="small">Forgot password?</a>
            </div>

            <button type="submit" class="btn btn-primary btn-login w-100">Sign In</button>
        </form>

        <div class="auth-footer-links">
            New user? <a href="register.php">Create an account</a>
        </div>

        <div class="text-center text-muted small mt-3">
            <?= $displayFooter ?>
        </div>
    </div>
</div>

<script nonce="<?= csp_nonce() ?>" src="js/bootstrap.bundle.min.js"></script>
<script nonce="<?= csp_nonce() ?>">
function togglePassword() {
    const input = document.getElementById('inputPassword');
    const icon = document.getElementById('toggleIcon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'fas fa-eye';
    }
}
document.addEventListener('click', function(e) {
    var el = e.target.closest('[data-action]');
    if (!el) return;
    var fn = window[el.dataset.action];
    if (typeof fn === 'function') fn();
});
</script>
</body>
</html>
