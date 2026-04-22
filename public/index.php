<?php
/**
 * Dashboard
 * System overview with key stats from the core managers.
 */

require_once __DIR__ . '/../core/bootstrap.php';
require_once 'include/rbac_init.php';
require_once __DIR__ . '/../core/AccountManager.php';
require_once __DIR__ . '/../core/AuditTrailManager.php';

// Redirect to first accessible page if user can't view dashboard
if (!hasPagePermission('dashboard', 'view')) {
    $redirect = $pageManager->getFirstAccessibleRoute($userRoleId);
    if ($redirect && $redirect !== 'index.php') {
        header('Location: ' . $redirect);
        exit;
    }
    requirePagePermission('dashboard', 'view');
}

$accountManager = AccountManager::getInstance($db);
$auditManager   = AuditTrailManager::getInstance($db);

// ── Stats ──────────────────────────────────────────────────────────────────────
$totalUsers  = $accountManager->getUserStats()['total'] ?? 0;
$totalRoles  = count($roleManager->getAllRoles());
$totalPages  = count($pageManager->getAllPages());
$auditStats  = $auditManager->getSummaryStats();
$eventsToday = $auditStats['events_today'] ?? 0;

// ── View ───────────────────────────────────────────────────────────────────────
$pageTitle   = 'Dashboard';
$breadcrumbs = [['title' => 'Dashboard', 'url' => '#']];

ob_start();
?>

<!-- Stats -->
<div class="row">
    <?= statCard('Total Users',    $totalUsers,  'primary', 'fas fa-users',         'account_management.php') ?>
    <?= statCard('Roles',          $totalRoles,  'success', 'fas fa-user-shield',   'role_management.php') ?>
    <?= statCard('Pages / Routes', $totalPages,  'info',    'fas fa-file-alt',      'page_management.php') ?>
    <?= statCard('Events Today',   $eventsToday, 'warning', 'fas fa-history',       'audit_trail.php') ?>
</div>

<!-- Welcome -->
<div class="card mb-4">
    <div class="card-header"><i class="fas fa-tachometer-alt me-1"></i> Welcome</div>
    <div class="card-body">
        <p>Hello, <strong><?= e($session->get('full_name') ?: $session->get('email', 'User')) ?></strong>!</p>
        <p class="text-muted mb-0">
            This is the base web development template. Navigate using the sidebar to manage
            accounts, roles, permissions, pages, web settings, mailer configuration, and the audit trail.
        </p>
    </div>
</div>

<?php
$pageContent = ob_get_clean();
$pageScripts = '';
include 'include/layout.php';
