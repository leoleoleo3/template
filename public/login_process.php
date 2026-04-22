<?php
/**
 * Login Process Handler
 * Handles authentication with brute-force protection, account locking, and status checks.
 * POST only — redirects back to login.php with error/success messages via session.
 */

require_once 'include/session.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

// Verify CSRF token
if (!$session->verifyCSRFToken($_POST['_csrf_token'] ?? '')) {
    $_SESSION['login_error'] = 'Security token verification failed. Please try again.';
    header('Location: login.php');
    exit;
}

// Get form data (do NOT trim password — spaces may be intentional)
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$password = $_POST['password'] ?? '';

// Preserve email for re-display on error
$_SESSION['login_email'] = $email;

// Validation
if (empty($email)) {
    $_SESSION['login_error'] = 'Email address is required.';
    header('Location: login.php');
    exit;
}

if (empty($password)) {
    $_SESSION['login_error'] = 'Password is required.';
    header('Location: login.php');
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['login_error'] = 'Invalid email address format.';
    header('Location: login.php');
    exit;
}

// Query for user (include status fields, exclude soft-deleted)
$result = $db->query(
    "SELECT id, email, password, first_name, last_name, display_name, role_id, status, is_active, locked_until, failed_login_attempts FROM users WHERE email = ? AND hidden = 0 LIMIT 1",
    [$email]
);

if (!$result['success']) {
    $_SESSION['login_error'] = 'Database error occurred. Please try again later.';
    header('Location: login.php');
    exit;
}

if (empty($result['result'])) {
    $_SESSION['login_error'] = 'Invalid email or password.';
    header('Location: login.php');
    exit;
}

$user = $result['result'][0];

// Check if account is locked
if (!empty($user['locked_until']) && strtotime($user['locked_until']) > time()) {
    $_SESSION['login_error'] = 'Your account is temporarily locked due to too many failed login attempts. Please try again later.';
    header('Location: login.php');
    exit;
}

// Verify password
if (!password_verify($password, $user['password'])) {
    // Record failed login attempt
    $db->query("UPDATE users SET failed_login_attempts = failed_login_attempts + 1 WHERE id = ?", [$user['id']]);

    // Lock account after 5 failed attempts (30 minute lockout)
    $db->query("UPDATE users SET locked_until = DATE_ADD(NOW(), INTERVAL 30 MINUTE) WHERE id = ? AND failed_login_attempts >= 5", [$user['id']]);

    $_SESSION['login_error'] = 'Invalid email or password.';
    header('Location: login.php');
    exit;
}

// Check account status
switch ($user['status'] ?? 'active') {
    case 'pending':
        $_SESSION['login_error'] = 'Your account is pending approval. Please wait for an administrator to review your registration.';
        header('Location: login.php');
        exit;
    case 'suspended':
        $_SESSION['login_error'] = 'Your account has been suspended. Please contact the administrator.';
        header('Location: login.php');
        exit;
    case 'inactive':
        $_SESSION['login_error'] = 'Your account is inactive. Please contact the administrator.';
        header('Location: login.php');
        exit;
}

// Reset failed login attempts on successful login
$db->query("UPDATE users SET failed_login_attempts = 0, locked_until = NULL, last_login_at = NOW(), last_login_ip = ? WHERE id = ?", [$_SERVER['REMOTE_ADDR'], $user['id']]);

// Set display name for session
$user['name'] = $user['display_name'] ?? trim($user['first_name'] . ' ' . $user['last_name']);

// Use Session class to login
$session->login($user);

// Clear preserved email
unset($_SESSION['login_email']);

$_SESSION['login_success'] = 'Login successful! Redirecting...';

// Redirect to first accessible page based on role
require_once __DIR__ . '/../core/PageManager.php';
$pageManager = PageManager::getInstance($db);
$redirectUrl = $pageManager->getFirstAccessibleRoute($user['role_id']) ?? 'index.php';

header('Location: ' . $redirectUrl);
exit;
