<?php
/**
 * Template Registration Page
 * Uses Session management system
 */

require_once 'include/session.php';

// Load web settings for title
require_once __DIR__ . '/include/web_settings.php';

// Redirect to dashboard if already logged in
if ($session->isAuthenticated()) {
    header('Location: index.php');
    exit;
}

$title = 'Register | ' . ($siteName ?? 'Template');
$errors = [];
$success = '';

if ($session->has('register_error')) {
    $errors[] = $session->get('register_error');
    $session->remove('register_error');
}

if ($session->has('register_success')) {
    $success = $session->get('register_success');
    $session->remove('register_success');
}
?>
<?php include_once 'include/auth_header.php'; ?>
<div class="auth-card" style="max-width: 520px;">
    <div class="auth-logo">
        <?php if (!empty($siteLogoUrl)): ?>
            <img src="<?= htmlspecialchars($siteLogoUrl) ?>" alt="<?= htmlspecialchars($siteName ?? 'Template') ?>">
        <?php else: ?>
            <span class="auth-logo-fallback"><?= htmlspecialchars(mb_substr($siteName ?? 'A', 0, 1)) ?></span>
        <?php endif; ?>
    </div>
    <div class="auth-card-bar"></div>
    <div class="auth-card-body">
        <h1 class="auth-brand"><?= htmlspecialchars($siteName ?? 'Template') ?></h1>
        <p class="auth-brand-tag">Create your account</p>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php foreach ($errors as $error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endforeach; ?>

        <form method="POST" action="register_process.php">
            <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($session->getCSRFToken()); ?>">

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="inputFirstName">First name</label>
                    <input class="form-control" id="inputFirstName" type="text" name="first_name" placeholder="Jane" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="inputLastName">Last name</label>
                    <input class="form-control" id="inputLastName" type="text" name="last_name" placeholder="Doe" required>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="inputEmail">Email address</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                    <input class="form-control" id="inputEmail" type="email" name="email" placeholder="you@example.com" required>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="inputPassword">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input class="form-control" id="inputPassword" type="password" name="password" placeholder="Password" required>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="inputConfirmPassword">Confirm password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input class="form-control" id="inputConfirmPassword" type="password" name="confirm_password" placeholder="Confirm Password" required>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100">Create Account</button>
        </form>

        <div class="auth-footer-links">
            Have an account? <a href="login.php">Sign in</a>
        </div>
    </div>
</div>
    </main>
</div>
<?php include_once 'include/auth_footer.php'; ?>
