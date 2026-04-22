<?php
require_once 'include/session.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: forgot_password.php');
    exit;
}

if (!$session->verifyCSRFToken($_POST['_csrf_token'] ?? '')) {
    $_SESSION['login_error'] = 'Security token verification failed. Please try again.';
    header('Location: forgot_password.php');
    exit;
}

$email = isset($_POST['email']) ? trim($_POST['email']) : '';

if (empty($email)) {
    $_SESSION['login_error'] = 'Email address is required.';
    header('Location: forgot_password.php');
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['login_error'] = 'Invalid email address format.';
    header('Location: forgot_password.php');
    exit;
}

// Check if user exists
$result = $db->query(
    "SELECT id FROM users WHERE email = ? LIMIT 1",
    [$email]
);

if (!$result['success']) {
    $_SESSION['login_error'] = 'Database error occurred.';
    header('Location: forgot_password.php');
    exit;
}

if (empty($result['result'])) {
    // Don't reveal if email exists for security
    $_SESSION['reset_success'] = 'If the email address is registered, you will receive a password reset link shortly.';
    header('Location: forgot_password.php');
    exit;
}

$user = $result['result'][0];

// Generate reset token
$reset_token = bin2hex(random_bytes(32));
$reset_token_hash = hash('sha256', $reset_token);
$expiry = date('Y-m-d H:i:s', time() + 3600); // 1 hour expiry

// Store reset token in database
$db->query(
    "UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE id = ?",
    [$reset_token_hash, $expiry, $user['id']]
);

// TODO: Send email with reset link
// $reset_link = "https://yoursite.com/reset_password.php?token=$reset_token";
// Send email to user with reset link

$_SESSION['reset_success'] = 'If the email address is registered, you will receive a password reset link shortly.';
header('Location: forgot_password.php');
exit;
?>
