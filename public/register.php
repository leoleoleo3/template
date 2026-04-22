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
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-7">
                    <div class="card shadow-lg border-0 rounded-lg mt-5">
                        <div class="card-header">
                            <h3 class="text-center font-weight-light my-4">Create Account</h3>
                        </div>
                        <div class="card-body">
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

                            <form method="POST" action="register_process.php">
                                <!-- CSRF Token -->
                                <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($session->getCSRFToken()); ?>">
                                
                                <div class="form-floating mb-3">
                                    <input class="form-control" id="inputFirstName" type="text" name="first_name" placeholder="Enter your first name" required />
                                    <label for="inputFirstName">First name</label>
                                </div>
                                <div class="form-floating mb-3">
                                    <input class="form-control" id="inputLastName" type="text" name="last_name" placeholder="Enter your last name" required />
                                    <label for="inputLastName">Last name</label>
                                </div>
                                <div class="form-floating mb-3">
                                    <input class="form-control" id="inputEmail" type="email" name="email" placeholder="name@example.com" required />
                                    <label for="inputEmail">Email address</label>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <div class="form-floating mb-3">
                                            <input class="form-control" id="inputPassword" type="password" name="password" placeholder="Password" required />
                                            <label for="inputPassword">Password</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-floating mb-3">
                                            <input class="form-control" id="inputConfirmPassword" type="password" name="confirm_password" placeholder="Confirm Password" required />
                                            <label for="inputConfirmPassword">Confirm Password</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-4 mb-0">
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary btn-block">Create Account</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="card-footer text-center py-3">
                            <div class="small">
                                <a href="login.php">Have an account? Login here</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
<?php include_once 'include/auth_footer.php'; ?>
