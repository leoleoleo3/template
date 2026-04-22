<?php
/**
 * Role Management
 * CRUD for user roles (superadmin only).
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
    startAjax();   // auth + CSRF (permission already checked above)

    switch ($_POST['action']) {

        case 'create':
            $data   = ['name' => $_POST['name'] ?? '', 'display_name' => $_POST['display_name'] ?? '', 'description' => $_POST['description'] ?? '', 'is_superadmin' => isset($_POST['is_superadmin']) ? 1 : 0];
            $result = $roleManager->createRole($data);
            if ($result['success']) {
                $permissionManager->logPermissionChange($session->getUserId(), 'create', 'role', $result['insert_id'], null, $roleManager->getRoleById($result['insert_id']));
            }
            echo json_encode($result);
            exit;

        case 'update':
            $roleId  = (int) $_POST['id'];
            $oldRole = $roleManager->getRoleById($roleId);
            $data    = ['name' => $_POST['name'] ?? '', 'display_name' => $_POST['display_name'] ?? '', 'description' => $_POST['description'] ?? '', 'is_superadmin' => isset($_POST['is_superadmin']) ? 1 : 0];
            $result  = $roleManager->updateRole($roleId, $data);
            if ($result['success']) {
                $permissionManager->logPermissionChange($session->getUserId(), 'update', 'role', $roleId, $oldRole, $roleManager->getRoleById($roleId));
            }
            echo json_encode($result);
            exit;

        case 'delete':
            $roleId  = (int) $_POST['id'];
            $oldRole = $roleManager->getRoleById($roleId);
            $result  = $roleManager->deleteRole($roleId);
            if ($result['success']) {
                $permissionManager->logPermissionChange($session->getUserId(), 'delete', 'role', $roleId, $oldRole, null);
            }
            echo json_encode($result);
            exit;

        case 'duplicate':
            echo json_encode($roleManager->duplicateRole((int) $_POST['source_id'], $_POST['name'] ?? '', $_POST['display_name'] ?? ''));
            exit;

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            exit;
    }
}

// ── View ───────────────────────────────────────────────────────────────────────
$roleStats   = $roleManager->getRoleStats();
$pageTitle   = 'Role Management';
$breadcrumbs = [
    ['title' => 'Dashboard',       'url' => 'index.php'],
    ['title' => 'Security',        'url' => '#'],
    ['title' => 'Role Management', 'url' => ''],
];

ob_start();
?>

<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-user-shield me-1"></i> Roles
        <button class="btn btn-primary btn-sm float-end" data-action="openAddModal">
            <i class="fas fa-plus"></i> Add Role
        </button>
    </div>
    <div class="card-body">
        <table class="table table-striped table-hover" id="rolesTable">
            <thead>
                <tr>
                    <th>ID</th><th>Name</th><th>Display Name</th><th>Description</th>
                    <th>Superadmin</th><th>Users</th><th>Permissions</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($roleStats as $role): ?>
                <tr>
                    <td><?= $role['id'] ?></td>
                    <td><code><?= e($role['name']) ?></code></td>
                    <td><?= e($role['display_name']) ?></td>
                    <td><?= e(mb_strimwidth($role['description'] ?? '', 0, 50, '...')) ?></td>
                    <td><span class="badge bg-<?= $role['is_superadmin'] ? 'danger' : 'secondary' ?>"><?= $role['is_superadmin'] ? 'Yes' : 'No' ?></span></td>
                    <td><span class="badge bg-info"><?= $role['user_count'] ?></span></td>
                    <td><span class="badge bg-success"><?= $role['permission_count'] ?></span></td>
                    <td>
                        <button class="btn btn-sm btn-primary" data-action="openEditModal" data-arg-json="<?= e(json_encode($role)) ?>"><i class="fas fa-edit"></i></button>
                        <button class="btn btn-sm btn-info"    data-action="duplicateRole" data-arg0="<?= $role['id'] ?>" data-arg1="<?= e($role['name']) ?>"><i class="fas fa-copy"></i></button>
                        <?php if ($role['id'] != 1): ?>
                        <button class="btn btn-sm btn-danger"  data-action="deleteRole"    data-arg0="<?= $role['id'] ?>" data-arg1="<?= e($role['display_name']) ?>"><i class="fas fa-trash"></i></button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="roleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="roleModalTitle">Add Role</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="roleForm">
                <input type="hidden" name="csrf_token" value="<?= $session->getCSRFToken() ?>">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="id"     id="roleId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Role Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="roleName" name="name" required>
                        <small class="text-muted">Lowercase, underscores only (e.g. system_admin)</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Display Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="roleDisplayName" name="display_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" id="roleDescription" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="roleSuperadmin" name="is_superadmin">
                        <label class="form-check-label" for="roleSuperadmin">Superadmin (Full Access)</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$pageContent = ob_get_clean();

ob_start();
?>
<script nonce="<?= csp_nonce() ?>" src="js/simple-datatables.min.js"></script>
<script nonce="<?= csp_nonce() ?>">
    App.initDataTable('#rolesTable');

    const roleModal = new bootstrap.Modal(document.getElementById('roleModal'));

    function openAddModal() {
        document.getElementById('roleModalTitle').textContent = 'Add Role';
        document.getElementById('formAction').value = 'create';
        document.getElementById('roleForm').reset();
        document.getElementById('roleId').value = '';
        document.querySelector('[name="csrf_token"]').value = '<?= $session->getCSRFToken() ?>';
        roleModal.show();
    }

    function openEditModal(role) {
        document.getElementById('roleModalTitle').textContent = 'Edit Role';
        document.getElementById('formAction').value = 'update';
        document.getElementById('roleId').value          = role.id;
        document.getElementById('roleName').value        = role.name;
        document.getElementById('roleDisplayName').value = role.display_name;
        document.getElementById('roleDescription').value = role.description || '';
        document.getElementById('roleSuperadmin').checked = role.is_superadmin == 1;
        document.querySelector('[name="csrf_token"]').value = '<?= $session->getCSRFToken() ?>';
        roleModal.show();
    }

    function deleteRole(id, name) {
        App.confirmDelete(name,
            { action: 'delete', id, csrf_token: '<?= $session->getCSRFToken() ?>' },
            'role_management.php', 'Role');
    }

    function duplicateRole(id, name) {
        Notify.prompt('Enter name for the duplicated role:', name + '_copy').then(newName => {
            if (!newName) return;
            Notify.prompt('Enter display name for the duplicated role:', name + ' Copy').then(newDisplayName => {
                if (!newDisplayName) return;
                App.postAction('role_management.php',
                    { action: 'duplicate', source_id: id, name: newName, display_name: newDisplayName, csrf_token: '<?= $session->getCSRFToken() ?>' },
                    {
                        loadingMsg: 'Duplicating role...',
                        onSuccess() { Notify.actionSuccess('duplicated', 'Role'); setTimeout(() => location.reload(), 1500); },
                        onError(msg) { Notify.error(msg); }
                    });
            });
        });
    }

    document.getElementById('roleForm').addEventListener('submit', function (e) {
        e.preventDefault();
        const isUpdate = document.getElementById('formAction').value === 'update';
        App.submitForm('role_management.php', this, {
            loadingMsg: 'Saving role...',
            onSuccess() { roleModal.hide(); Notify.actionSuccess(isUpdate ? 'updated' : 'added', 'Role'); setTimeout(() => location.reload(), 1500); },
            onError(msg) { Notify.error(msg); }
        });
    });
</script>
<?php
$pageScripts = ob_get_clean();
include 'include/layout.php';
