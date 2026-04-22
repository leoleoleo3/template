<?php
require_once 'include/session.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: register.php');
    exit;
}

if (!$session->verifyCSRFToken($_POST['_csrf_token'] ?? '')) {
    $_SESSION['register_error'] = 'Security token verification failed. Please try again.';
    header('Location: register.php');
    exit;
}

// Get form data
$first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
$last_name = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$password = isset($_POST['password']) ? trim($_POST['password']) : '';
$confirm_password = isset($_POST['confirm_password']) ? trim($_POST['confirm_password']) : '';

// Validation
if (empty($first_name)) {
    $_SESSION['register_error'] = 'First name is required.';
    header('Location: register.php');
    exit;
}

if (empty($email)) {
    $_SESSION['register_error'] = 'Email address is required.';
    header('Location: register.php');
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['register_error'] = 'Invalid email address format.';
    header('Location: register.php');
    exit;
}

if (empty($password)) {
    $_SESSION['register_error'] = 'Password is required.';
    header('Location: register.php');
    exit;
}

if (strlen($password) < 8) {
    $_SESSION['register_error'] = 'Password must be at least 8 characters long.';
    header('Location: register.php');
    exit;
}

if ($password !== $confirm_password) {
    $_SESSION['register_error'] = 'Passwords do not match.';
    header('Location: register.php');
    exit;
}

// Check if email already exists
$result = $db->query(
    "SELECT id FROM users WHERE email = ? LIMIT 1",
    [$email]
);

if (!$result['success']) {
    $_SESSION['register_error'] = 'Database error occurred. Please try again later.';
    header('Location: register.php');
    exit;
}

if (!empty($result['result'])) {
    $_SESSION['register_error'] = 'Email address is already registered.';
    header('Location: register.php');
    exit;
}

// Hash password with stronger cost factor (OWASP recommends ≥ 10, 12 is safer)
$hashed_password = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

// Insert new user using DB helper
// Status is set to 'pending' for approval workflow
$insert_result = $db->insert('users', [
    'first_name' => $first_name,
    'last_name' => $last_name,
    'email' => $email,
    'password' => $hashed_password,
    'role_id' => 1, // Default to regular user role
    'status' => 'active' // Requires admin approval
]);

if (!$insert_result['success']) {
    $_SESSION['register_error'] = 'Registration failed. Please try again.';
    header('Location: register.php');
    exit;
}

$_SESSION['register_success'] = 'Registration successful! Your account is pending approval. You will be notified once an administrator reviews your request.';
header('Location: login.php');
exit;
?>
