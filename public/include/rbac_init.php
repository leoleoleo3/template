<?php
/**
 * RBAC System Initialization
 * Include this file at the top of any page that needs permission checking.
 * Safe to call after bootstrap.php — reuses $db and $session if already set.
 *
 * Usage:
 *   require_once __DIR__ . '/../core/bootstrap.php'; // optional but recommended
 *   require_once 'include/rbac_init.php';
 *   requirePagePermission('page_name', 'view');
 */

// ── Core requires ──────────────────────────────────────────────────────────────
require_once __DIR__ . '/../../core/db.php';
require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/SecurityHeaders.php';
require_once __DIR__ . '/../../core/PermissionManager.php';
require_once __DIR__ . '/../../core/RoleManager.php';
require_once __DIR__ . '/../../core/PageManager.php';

// ── DB — reuse bootstrap instance if already created ──────────────────────────
if (!isset($db)) {
    $config = require __DIR__ . '/../../config/database.php';
    $db = new DB($config['host'], $config['user'], $config['pass'], $config['name'], $config['port'] ?? 3306);
}

// ── Security headers — only init once ─────────────────────────────────────────
if (!SecurityHeaders::isInitialized()) {
    SecurityHeaders::init();
}

// ── Session — reuse bootstrap instance if already created ─────────────────────
if (!isset($session)) {
    $session = Session::getInstance($db);
}

// ── Require authentication ─────────────────────────────────────────────────────
$session->requireLogin();

// ── RBAC managers ─────────────────────────────────────────────────────────────
$permissionManager = PermissionManager::getInstance($db);
$roleManager       = RoleManager::getInstance($db);
$pageManager       = PageManager::getInstance($db);

// ── User context ──────────────────────────────────────────────────────────────
$userRoleId      = $session->get('role_id', 1);
$userRole        = $roleManager->getRoleById($userRoleId);
$isSuperAdmin    = $permissionManager->isSuperAdmin($userRoleId);
$accessiblePages = $pageManager->getMenuTree($userRoleId);

// ── Permission helpers ─────────────────────────────────────────────────────────

/**
 * Require a page permission; handles both AJAX (JSON error) and HTML (403 page).
 */
function requirePagePermission(string $pageName, string $permission = 'view'): void
{
    global $permissionManager, $userRoleId;

    if ($permissionManager->hasPermission($userRoleId, $pageName, $permission)) {
        return;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Permission denied.']);
    } else {
        http_response_code(403);
        include __DIR__ . '/../errors/403.php';
    }
    exit;
}

/**
 * Non-fatal permission check — returns true/false.
 */
function hasPagePermission(string $pageName, string $permission = 'view'): bool
{
    global $permissionManager, $userRoleId;
    return $permissionManager->hasPermission($userRoleId, $pageName, $permission);
}

/**
 * Require permission based on the current file's route (basename of PHP_SELF).
 */
function requireCurrentPagePermission(string $permission = 'view'): void
{
    global $permissionManager, $userRoleId;
    $permissionManager->requirePermissionByRoute($userRoleId, basename($_SERVER['PHP_SELF']), $permission);
}

/**
 * Return true if the current user is a superadmin.
 */
function isSuperAdmin(): bool
{
    global $isSuperAdmin;
    return $isSuperAdmin;
}
