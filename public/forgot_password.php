<?php
/**
 * Template Forgot Password Page
 * Uses Session management system
 */

require_once 'include/session.php';

// Load web settings for title
require_once __DIR__ . '/include/web_settings.php';

$title = 'Forgot Password | ' . ($siteName ?? 'Template');
$errors = [];
$success = '';

if ($session->has('reset_success')) {
    $success = $session->get('reset_success');
    $session->remove('reset_success');
}
?>
<?php include_once 'include/auth_header.php'; ?>
<div class="auth-card">
    <div class="auth-logo">
        <?php if (!empty($siteLogoUrl)): ?>
            <img src="<?= htmlspecialchars($siteLogoUrl) ?>" alt="<?= htmlspecialchars($siteName ?? 'Template') ?>">
        <?php else: ?>
            <span class="auth-logo-fallback"><?= htmlspecialchars(mb_substr($siteName ?? 'A', 0, 1)) ?></span>
        <?php endif; ?>
    </div>
    <div class="auth-card-bar"></div>
    <div class="auth-card-body">
        <h1 class="auth-brand">Forgot Password</h1>
        <p class="auth-brand-tag">We'll send you a reset link</p>

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

        <form method="POST" action="forgot_password_process.php">
            <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($session->getCSRFToken()); ?>">

            <div class="mb-3">
                <label class="form-label" for="inputEmail">Email address</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                    <input class="form-control" id="inputEmail" type="email" name="email" placeholder="you@example.com" required>
                </div>
                <small class="text-muted d-block mt-1">
                    Enter the email tied to your account and we'll send a reset link.
                </small>
            </div>

            <button type="submit" class="btn btn-primary w-100 mb-3">
                <i class="fas fa-paper-plane me-1"></i> Send Reset Link
            </button>
        </form>

        <div class="auth-footer-links">
            <a href="login.php"><i class="fas fa-arrow-left me-1"></i>Back to sign in</a>
            &middot;
            <a href="register.php">Create an account</a>
        </div>
    </div>
</div>
    </main>
</div>
<?php include_once 'include/auth_footer.php'; ?>
