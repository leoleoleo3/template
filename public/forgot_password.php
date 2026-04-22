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
                    <div class="container">
                        <div class="row justify-content-center">
                            <div class="col-lg-5">
                                <div class="card shadow-lg border-0 rounded-lg mt-5">
                                    <div class="card-header">
                                        <h3 class="text-center font-weight-light my-4">Forgot Password</h3>
                                    </div>
                                    <div class="card-body">
                                        <div class="small mb-3 text-muted">
                                            Enter your email address and we will send you a link to reset your password.
                                        </div>

                                        <?php if (!empty($success)): ?>
                                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                                <i class="fas fa-check-circle me-2"></i>
                                                <?php echo htmlspecialchars($success); ?>
                                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($errors)): ?>
                                            <?php foreach ($errors as $error): ?>
                                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                                    <i class="fas fa-exclamation-circle me-2"></i>
                                                    <?php echo htmlspecialchars($error); ?>
                                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>

                                        <form method="POST" action="forgot_password_process.php">
                                            <!-- CSRF Token -->
                                            <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($session->getCSRFToken()); ?>">
                                            
                                            <div class="form-floating mb-3">
                                                <input class="form-control" id="inputEmail" type="email" name="email" placeholder="name@example.com" required />
                                                <label for="inputEmail">Email address</label>
                                            </div>
                                            <div class="d-flex align-items-center justify-content-between mt-4 mb-0">
                                                <a class="small" href="login.php">
                                                    <i class="fas fa-arrow-left"></i> Return to login
                                                </a>
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="fas fa-paper-plane"></i> Send Reset Link
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                    <div class="card-footer text-center py-3">
                                        <div class="small">
                                            <a href="register.php">
                                                <i class="fas fa-user-plus"></i> Need an account? Sign up!
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
<?php include_once 'include/auth_footer.php'; ?>
