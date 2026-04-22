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

// Check for session expiration message
if (isset($_GET['expired'])) {
    $errors[] = 'Your session has expired. Please login again.';
}

$currentYear = date('Y');
$displaySiteName = htmlspecialchars($siteName ?? 'Template');
$displayTagline = htmlspecialchars($siteTagline ?? 'Student & Admin Portal');
$color = htmlspecialchars($primaryColor ?? '#0d6efd');
$colorDark = htmlspecialchars($primaryColorDark ?? '#0a58ca');
$displayFooter = !empty($footerText) ? htmlspecialchars($footerText) : '&copy; ' . $currentYear . ' ' . $displaySiteName;

// Convert primary color hex to RGB for dynamic rgba() usage
$hexClean = ltrim($color, '#');
$colorR = hexdec(substr($hexClean, 0, 2));
$colorG = hexdec(substr($hexClean, 2, 2));
$colorB = hexdec(substr($hexClean, 4, 2));
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
    <script nonce="<?= csp_nonce() ?>" src="js/all.js" crossorigin="anonymous"></script>

    <style nonce="<?= csp_nonce() ?>">
        *, *::before, *::after { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: <?= $color ?>;
            background: linear-gradient(135deg, <?= $color ?> 0%, <?= $colorDark ?> 100%);
            position: relative;
            overflow: hidden;
        }

        /* Diagonal stripe pattern overlay */
        body::before {
            content: '';
            position: fixed;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: repeating-linear-gradient(
                45deg,
                transparent,
                transparent 35px,
                rgba(255, 255, 255, 0.03) 35px,
                rgba(255, 255, 255, 0.03) 70px
            );
            pointer-events: none;
            z-index: 0;
        }

        /* Subtle wave shapes */
        /* body::after {
            content: '';
            position: fixed;
            bottom: -20%;
            right: -10%;
            width: 60%;
            height: 60%;
            background: radial-gradient(ellipse, rgba(255,255,255,0.05) 0%, transparent 70%);
            pointer-events: none;
            z-index: 0;
        } */

        .login-wrapper {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 420px;
            padding: 60px 20px 20px;
        }

        .login-card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
            padding: 70px 36px 36px;
            position: relative;
        }

        /* Logo circle */
        .logo-wrapper {
            position: absolute;
            top: -55px;
            left: 50%;
            transform: translateX(-50%);
            width: 110px;
            height: 110px;
            border-radius: 50%;
            overflow: hidden;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logo-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .logo-wrapper .logo-placeholder {
            width: 100%;
            height: 100%;
            background: <?= $color ?>;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 32px;
            font-weight: 700;
        }

        .school-name {
            text-align: center;
            font-size: 1.4rem;
            font-weight: 800;
            color: #1a1a2e;
            margin: 0 0 2px;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        .school-tagline {
            text-align: center;
            font-size: 0.85rem;
            color: #666;
            margin: 0 0 14px;
        }

        .divider {
            height: 2px;
            background: linear-gradient(90deg, transparent, <?= $color ?>, transparent);
            margin: 0 auto 18px;
            width: 70%;
            border: none;
        }

        .cert-text {
            text-align: center;
            font-size: 0.8rem;
            color: #777;
            line-height: 1.5;
            margin-bottom: 20px;
            padding: 0 10px;
        }

        .field-label {
            font-size: 0.82rem;
            font-weight: 600;
            color: #444;
            margin-bottom: 6px;
        }

        .form-control.login-input {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 11px 14px;
            font-size: 0.9rem;
            transition: border-color 0.2s, box-shadow 0.2s;
            background: #fafafa;
        }

        .form-control.login-input:focus {
            border-color: <?= $color ?>;
            box-shadow: 0 0 0 3px rgba(<?= $colorR ?>, <?= $colorG ?>, <?= $colorB ?>, 0.1);
            background: #fff;
        }

        .form-control.login-input.is-invalid {
            border-color: #dc3545;
        }

        .password-wrapper {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #aaa;
            cursor: pointer;
            padding: 0;
            font-size: 1rem;
        }

        .password-toggle:hover {
            color: #555;
        }

        .btn-login {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, <?= $color ?> 0%, <?= $colorDark ?> 100%);
            border: none;
            border-radius: 8px;
            color: #fff;
            font-size: 1rem;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            cursor: pointer;
            transition: opacity 0.2s, transform 0.1s;
        }

        .btn-login:hover {
            opacity: 0.92;
        }

        .btn-login:active {
            transform: scale(0.99);
        }

        .login-footer {
            text-align: center;
            font-size: 0.78rem;
            color: #999;
            margin-top: 22px;
            padding-bottom: 4px;
        }

        .alert {
            font-size: 0.82rem;
            border-radius: 8px;
            margin-bottom: 14px;
        }

        @media (max-width: 480px) {
            .login-card {
                padding: 60px 24px 28px;
                border-radius: 16px;
            }
            .login-wrapper {
                padding: 50px 12px 12px;
            }
        }
    </style>
</head>
<body>

<div class="login-wrapper">
    <div class="login-card">
        <!-- Logo -->
        <div class="logo-wrapper">
            <?php if (!empty($siteLogoUrl)): ?>
                <img src="<?= htmlspecialchars($siteLogoUrl) ?>" alt="<?= $displaySiteName ?>">
            <?php else: ?>
                <div class="logo-placeholder">
                    <?= mb_substr($siteName ?? 'A', 0, 1) ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- School Name & Tagline -->
        <h1 class="school-name"><?= $displaySiteName ?></h1>
        <p class="school-tagline"><?= $displayTagline ?></p>

        <hr class="divider">

        <!-- Alerts -->
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

        <!-- Login Form -->
        <form method="POST" action="login_process.php">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($session->getCSRFToken()) ?>">

            <div class="mb-3">
                <input
                    class="form-control login-input<?= !empty($errors) ? ' is-invalid' : '' ?>"
                    id="inputEmail"
                    type="email"
                    name="email"
                    placeholder="Username / Email"
                    value="<?= htmlspecialchars($email_value) ?>"
                    required
                    autofocus
                >
            </div>

            <div class="mb-1">
                <div class="password-wrapper">
                    <input
                        class="form-control login-input<?= !empty($errors) ? ' is-invalid' : '' ?>"
                        id="inputPassword"
                        type="password"
                        name="password"
                        placeholder="Password"
                        required
                    >
                    <button type="button" class="password-toggle" data-action="togglePassword" tabindex="-1">
                        <i class="fas fa-lock" id="toggleIcon"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-login">LOGIN</button>
        </form>

        <div class="login-footer">
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
        icon.className = 'fas fa-lock-open';
    } else {
        input.type = 'password';
        icon.className = 'fas fa-lock';
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
