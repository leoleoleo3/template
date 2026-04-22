<?php
/**
 * Permission Management
 * Matrix view to assign/revoke page permissions per role (superadmin only).
 */

require_once __DIR__ . '/../core/bootstrap.php';
require_once 'include/rbac_init.php';

// ── Guard: superadmin only ─────────────────────────────────────────────────────
$isAjax = $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']);
if (!$isSuperAdmin) {
    if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success' => false, 'error' => 'Permission denied']); exit; }
    http_response_code(403); include __DIR__ . '/errors/403.php'; exit;
}

// ── AJAX handler ───────────────────────────────────────────────────────────────
if ($isAjax) {
    startAjax();

    switch ($_POST['action']) {

        case 'update_permissions':
            $roleId      = (int) $_POST['role_id'];
            $permissions = json_decode($_POST['permissions'] ?? '', true);
            if (!$permissions) { echo json_encode(['success' => false, 'error' => 'Invalid permissions data']); exit; }
            $oldPerms = $permissionManager->getRolePermissions($roleId);
            $result   = $permissionManager->setPermissions($roleId, $permissions);
            if ($result['success']) {
                $permissionManager->logPermissionChange($session->getUserId(), 'update', 'permissions', $roleId, $oldPerms, $permissionManager->getRolePermissions($roleId));
            }
            echo json_encode($result);
            exit;

        case 'get_permissions':
            $roleId = (int) $_POST['role_id'];
            echo json_encode(['success' => true, 'permissions' => $permissionManager->getRolePermissions($roleId)]);
            exit;

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            exit;
    }
}

// ── View ───────────────────────────────────────────────────────────────────────
$roles           = $roleManager->getAllRoles();
$pages           = $pageManager->getPagesTree();
$permissionTypes = $permissionManager->getPermissionTypes();

$selectedRoleId  = isset($_GET['role_id']) ? (int) $_GET['role_id'] : ($roles[1]['id'] ?? 2);
$selectedRole    = $roleManager->getRoleById($selectedRoleId);
$rolePermissions = $permissionManager->getRolePermissions($selectedRoleId);

// Index permissions by page for O(1) lookup in the matrix
$permsByPage = [];
foreach ($rolePermissions as $perm) {
    $permsByPage[$perm['page_id']][$perm['permission_name']] = $perm['granted'];
}

$pageTitle   = 'Permission Management';
$breadcrumbs = [
    ['title' => 'Dashboard',            'url' => 'index.php'],
    ['title' => 'Security',             'url' => '#'],
    ['title' => 'Permission Management','url' => ''],
];

ob_start();

/**
 * Render permission matrix rows recursively for nested pages.
 */
function renderPagePermissions(array $pages, array $permTypes, array $permsByPage, int $level = 0): void
{
    foreach ($pages as $page) {
        $indent  = $level * 30 + 10;
        $icon    = empty($page['children']) ? 'fas fa-file text-info' : 'fas fa-folder text-warning';
        $route   = $page['route'] ? '<br><small class="text-muted">' . e($page['route']) . '</small>' : '';
        echo "<tr><td style=\"padding-left:{$indent}px;\"><i class=\"{$icon}\"></i> <strong>" . e($page['display_name']) . "</strong>{$route}</td>";
        foreach ($permTypes as $pt) {
            $checked = !empty($permsByPage[$page['id']][$pt['name']]) ? ' checked' : '';
            echo "<td class=\"text-center\"><input type=\"checkbox\" class=\"form-check-input permission-checkbox\""
               . " data-page-id=\"{$page['id']}\""
               . " data-permission-id=\"{$pt['id']}\""
               . " data-permission-name=\"{$pt['name']}\"{$checked}></td>";
        }
        echo "</tr>";
        if (!empty($page['children'])) {
            renderPagePermissions($page['children'], $permTypes, $permsByPage, $level + 1);
        }
    }
}
?>

<!-- Role Selection -->
<div class="card mb-4">
    <div class="card-header"><i class="fas fa-lock me-1"></i> Manage Permissions for Role</div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Select Role:</label>
                <select class="form-select select2-role" name="role_id" data-auto-submit>
                    <?php foreach ($roles as $role): ?>
                        <?php if ($role['id'] != 1): ?>
                        <option value="<?= $role['id'] ?>" <?= $role['id'] == $selectedRoleId ? 'selected' : '' ?>>
                            <?= e($role['display_name']) ?>
                        </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
        <?php if ($selectedRole): ?>
        <div class="alert alert-info mt-3">
            <strong><?= e($selectedRole['display_name']) ?></strong>
            <p class="mb-0"><?= e($selectedRole['description'] ?? 'No description') ?></p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Permissions Matrix -->
<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-table me-1"></i> Permissions Matrix
        <button class="btn btn-secondary btn-sm float-end me-2" data-action="resetPermissions"><i class="fas fa-undo"></i> Reset</button>
        <button class="btn btn-success btn-sm  float-end me-2" data-action="savePermissions"><i class="fas fa-save"></i> Save All Changes</button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead class="table-light">
                    <tr>
                        <th style="width:30%;">Page / Resource</th>
                        <?php foreach ($permissionTypes as $pt): ?>
                        <th class="text-center" style="width:<?= 70 / count($permissionTypes) ?>%;">
                            <?= e($pt['display_name']) ?><br><small class="text-muted"><?= e($pt['name']) ?></small>
                        </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php renderPagePermissions($pages, $permissionTypes, $permsByPage); ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="card mb-4">
    <div class="card-header"><i class="fas fa-bolt me-1"></i> Quick Actions</div>
    <div class="card-body">
        <button class="btn btn-outline-primary   btn-sm" data-action="selectAll" data-arg0="view">  <i class="fas fa-eye"></i>    Grant All View</button>
        <button class="btn btn-outline-success   btn-sm" data-action="selectAll" data-arg0="create"><i class="fas fa-plus"></i>   Grant All Create</button>
        <button class="btn btn-outline-warning   btn-sm" data-action="selectAll" data-arg0="edit">  <i class="fas fa-edit"></i>   Grant All Edit</button>
        <button class="btn btn-outline-danger    btn-sm" data-action="selectAll" data-arg0="delete"><i class="fas fa-trash"></i>  Grant All Delete</button>
        <button class="btn btn-outline-secondary btn-sm" data-action="clearAll">                     <i class="fas fa-times"></i>  Clear All</button>
    </div>
</div>

<?php
$pageContent = ob_get_clean();

ob_start();
?>
<script nonce="<?= csp_nonce() ?>">
    const roleId    = <?= $selectedRoleId ?>;
    const csrfToken = '<?= $session->getCSRFToken() ?>';

    // Auto-submit role selector
    $('.select2-role').select2({ theme: 'bootstrap-5', minimumResultsForSearch: Infinity })
        .on('change', function() { this.closest('form').submit(); });

    // Track original state for reset
    const originalState = {};
    document.querySelectorAll('.permission-checkbox').forEach(cb => {
        originalState[`${cb.dataset.pageId}-${cb.dataset.permissionId}`] = cb.checked;
    });

    let hasChanges = false;
    document.querySelectorAll('.permission-checkbox').forEach(cb => cb.addEventListener('change', () => { hasChanges = true; }));
    window.addEventListener('beforeunload', e => { if (hasChanges) { e.preventDefault(); e.returnValue = ''; } });

    function savePermissions() {
        const permissions = [...document.querySelectorAll('.permission-checkbox')].map(cb => ({
            page_id:           parseInt(cb.dataset.pageId),
            permission_type_id: parseInt(cb.dataset.permissionId),
            granted:            cb.checked ? 1 : 0
        }));
        App.postAction('permission_management.php',
            { action: 'update_permissions', role_id: roleId, permissions: JSON.stringify(permissions), csrf_token: csrfToken },
            {
                loadingMsg: 'Saving permissions...',
                onSuccess() { Notify.success('Permissions saved!'); hasChanges = false; setTimeout(() => location.reload(), 1500); },
                onError(msg) { Notify.error(msg); }
            });
    }

    function resetPermissions() {
        document.querySelectorAll('.permission-checkbox').forEach(cb => {
            cb.checked = originalState[`${cb.dataset.pageId}-${cb.dataset.permissionId}`];
        });
        hasChanges = false;
    }

    function selectAll(permName) {
        document.querySelectorAll(`.permission-checkbox[data-permission-name="${permName}"]`).forEach(cb => { cb.checked = true; });
        hasChanges = true;
    }

    function clearAll() {
        document.querySelectorAll('.permission-checkbox').forEach(cb => { cb.checked = false; });
        hasChanges = true;
    }
</script>
<?php
$pageScripts = ob_get_clean();
include 'include/layout.php';
