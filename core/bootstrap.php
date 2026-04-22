<?php
/**
 * Application Bootstrap
 * Include this file at the top of all pages for consistent initialization
 */

// Define base path
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

// Load Composer autoloader (PHPMailer, etc.)
$autoloader = BASE_PATH . '/vendor/autoload.php';
if (file_exists($autoloader)) {
    require_once $autoloader;
}

if (!defined('CORE_PATH')) {
    define('CORE_PATH', BASE_PATH . '/core');
}

if (!defined('PUBLIC_PATH')) {
    define('PUBLIC_PATH', BASE_PATH . '/public');
}

if (!defined('CONFIG_PATH')) {
    define('CONFIG_PATH', BASE_PATH . '/config');
}

// Load configuration
$config = require CONFIG_PATH . '/database.php';

// Determine debug mode from environment or config
$debug = $config['debug'] ?? false;
if (file_exists(BASE_PATH . '/.env')) {
    $env = parse_ini_file(BASE_PATH . '/.env');
    $debug = ($env['APP_DEBUG'] ?? 'false') === 'true';
}

// Initialize error handler
require_once CORE_PATH . '/ErrorHandler.php';
ErrorHandler::init($debug, BASE_PATH . '/logs/error.log');

// Send HTTP security headers and generate CSP nonce for this request
// (must be called before any output — ob_start() in each page keeps output buffered)
require_once CORE_PATH . '/SecurityHeaders.php';
SecurityHeaders::init();

// Load core classes
require_once CORE_PATH . '/db.php';
require_once CORE_PATH . '/session.php';

// Initialize database
$db = new DB(
    $config['host'],
    $config['user'],
    $config['pass'],
    $config['name'],
    $config['port'] ?? 3306
);

// Initialize session
$session = Session::getInstance($db);

/**
 * Helper function to abort with error page
 */
function abort(int $code, ?string $message = null): void
{
    ErrorHandler::abort($code, $message);
}

/**
 * Helper function to redirect
 */
function redirect(string $url): void
{
    header("Location: $url");
    exit;
}

/**
 * Helper function to check if request is AJAX
 */
function isAjax(): bool
{
    global $session;
    return $session->isAjaxRequest();
}

/**
 * Helper function to send JSON response
 */
function jsonResponse(array $data, int $code = 200): void
{
    global $session;
    $session->sendJsonResponse($data, $code);
}

/**
 * Helper function to escape HTML
 */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Helper function to get old form input
 */
function old(string $key, string $default = ''): string
{
    return $_POST[$key] ?? $_GET[$key] ?? $default;
}

/**
 * Start an AJAX response: set JSON header, verify authentication and CSRF token.
 * Optionally accepts a permission check callable; exits with JSON error on any failure.
 *
 * Usage:
 *   $isAjax = $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']);
 *   if ($isAjax) startAjax(fn() => hasPagePermission('page_name', 'view'));
 */
function startAjax(?callable $permissionCheck = null): void
{
    global $session;
    header('Content-Type: application/json');

    if (!$session->isAuthenticated()) {
        echo json_encode(['success' => false, 'error' => 'Session expired. Please log in again.', 'redirect' => 'login.php']);
        exit;
    }
    if (!$session->verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token. Please refresh the page.']);
        exit;
    }
    if ($permissionCheck !== null && !$permissionCheck()) {
        echo json_encode(['success' => false, 'error' => 'Permission denied.']);
        exit;
    }
}

/**
 * Execute an action callable and log the result to the audit trail on success.
 * Returns the result array from the action.
 *
 * Usage:
 *   $result = withAudit($auditManager, 'create', 'user_account', $email, null, $data, fn() => $mgr->createUser($data));
 */
function withAudit(
    object $audit,
    string $event,
    string $entity,
    string $entityId,
    ?array $old,
    ?array $new,
    callable $action
): array {
    $result = $action();
    if ($result['success'] ?? false) {
        $audit->log($event, $entity, $entityId, $old, $new, ucfirst($event) . " {$entity}: {$entityId}");
    }
    return $result;
}

/**
 * Return a Bootstrap badge <span> for a status string.
 * Extend the $map array to support custom statuses.
 *
 * Usage:  echo statusBadge($user['status']);
 */
function statusBadge(string $status): string
{
    $map = [
        'active'    => 'success',
        'inactive'  => 'secondary',
        'suspended' => 'danger',
        'pending'   => 'warning',
        'locked'    => 'dark',
    ];
    $color = $map[$status] ?? 'secondary';
    return '<span class="badge bg-' . $color . '">' . ucfirst(e($status)) . '</span>';
}

/**
 * Return a Bootstrap 4-column stat card column HTML.
 * Pass a non-empty $link to add a "View Details" card footer.
 *
 * Usage:  echo statCard('Total Users', $count, 'primary', 'fas fa-users', 'account_management.php');
 */
function statCard(string $label, $value, string $color, string $icon, string $link = ''): string
{
    $footer = $link
        ? '<div class="card-footer d-flex align-items-center justify-content-between">'
          . '<a class="small text-white stretched-link" href="' . e($link) . '">View Details</a>'
          . '<div class="small text-white"><i class="fas fa-angle-right"></i></div>'
          . '</div>'
        : '';

    return <<<HTML
<div class="col-xl-3 col-md-6">
  <div class="card bg-{$color} text-white mb-4">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <div class="small text-white-50">{$label}</div>
          <div class="h3 mb-0">{$value}</div>
        </div>
        <i class="{$icon} fa-2x text-white-50"></i>
      </div>
    </div>
    {$footer}
  </div>
</div>
HTML;
}
