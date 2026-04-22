<?php
/**
 * Account Management
 * CRUD for user accounts with role assignment, status control, and audit logging.
 */

require_once __DIR__ . '/../core/bootstrap.php';
require_once 'include/rbac_init.php';
require_once __DIR__ . '/../core/AccountManager.php';
require_once __DIR__ . '/../core/AuditTrailManager.php';

$accountManager = AccountManager::getInstance($db);
$auditManager   = AuditTrailManager::getInstance($db);

// ── AJAX handler ───────────────────────────────────────────────────────────────
$isAjax = $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']);
if ($isAjax) {
    startAjax(fn() => hasPagePermission('account_management', 'view'));

    $canCreate = hasPagePermission('account_management', 'create');
    $canEdit   = hasPagePermission('account_management', 'edit');
    $canDelete = hasPagePermission('account_management', 'delete');

    switch ($_POST['action']) {

        case 'create':
            if (!$canCreate) { echo json_encode(['success' => false, 'error' => 'Permission denied']); exit; }
            $data = [
                'first_name'     => $_POST['first_name']  ?? '',
                'middle_name'    => $_POST['middle_name'] ?? '',
                'last_name'      => $_POST['last_name']   ?? '',
                'email'          => $_POST['email']       ?? '',
                'phone'          => $_POST['phone']       ?? '',
                'role_id'        => $_POST['role_id']     ?? 3,
                'status'         => $_POST['status']      ?? 'active',
                'email_verified' => isset($_POST['email_verified']) ? 1 : 0,
                'password'       => $_POST['password']    ?? null,
            ];
            echo json_encode(withAudit(
                $auditManager, 'create', 'user_account',
                $data['email'] ?: 'new user',
                null, array_diff_key($data, ['password' => 1]),
                fn() => $accountManager->createUser($data)
            ));
            exit;

        case 'update':
            if (!$canEdit) { echo json_encode(['success' => false, 'error' => 'Permission denied']); exit; }
            $userId  = (int) $_POST['id'];
            $oldUser = $accountManager->getUserById($userId);
            $data    = [
                'first_name'     => $_POST['first_name']  ?? '',
                'middle_name'    => $_POST['middle_name'] ?? '',
                'last_name'      => $_POST['last_name']   ?? '',
                'email'          => $_POST['email']       ?? '',
                'phone'          => $_POST['phone']       ?? '',
                'role_id'        => $_POST['role_id']     ?? 3,
                'status'         => $_POST['status']      ?? 'active',
                'email_verified' => isset($_POST['email_verified']) ? 1 : 0,
            ];
            if (!empty($_POST['password'])) $data['password'] = $_POST['password'];
            $keys    = ['first_name', 'middle_name', 'last_name', 'email', 'phone', 'role_id', 'status', 'email_verified'];
            $oldData = $oldUser ? array_intersect_key($oldUser, array_flip($keys)) : null;
            echo json_encode(withAudit(
                $auditManager, 'edit', 'user_account',
                $oldUser['email'] ?? "User #{$userId}",
                $oldData, array_diff_key($data, ['password' => 1]),
                fn() => $accountManager->updateUser($userId, $data)
            ));
            exit;

        case 'delete':
            if (!$canDelete) { echo json_encode(['success' => false, 'error' => 'Permission denied']); exit; }
            $userId = (int) $_POST['id'];
            if ($userId === $session->getUserId()) {
                echo json_encode(['success' => false, 'error' => 'You cannot delete your own account']);
                exit;
            }
            $oldUser = $accountManager->getUserById($userId);
            $keys    = ['first_name', 'middle_name', 'last_name', 'email', 'role_id', 'status'];
            $oldData = $oldUser ? array_intersect_key($oldUser, array_flip($keys)) : null;
            echo json_encode(withAudit(
                $auditManager, 'delete', 'user_account',
                $oldUser['email'] ?? "User #{$userId}",
                $oldData, null,
                fn() => $accountManager->deleteUser($userId)
            ));
            exit;

        case 'change_status':
            if (!$canEdit) { echo json_encode(['success' => false, 'error' => 'Permission denied']); exit; }
            $userId    = (int) $_POST['id'];
            $newStatus = $_POST['status'] ?? '';
            if ($userId === $session->getUserId() && in_array($newStatus, ['suspended', 'inactive'])) {
                echo json_encode(['success' => false, 'error' => 'You cannot deactivate or suspend your own account']);
                exit;
            }
            $oldUser = $accountManager->getUserById($userId);
            $result  = match ($newStatus) {
                'active'    => $accountManager->activateUser($userId),
                'inactive'  => $accountManager->deactivateUser($userId),
                'suspended' => $accountManager->suspendUser($userId),
                default     => ['success' => false, 'error' => 'Invalid status'],
            };
            if ($result['success'] ?? false) {
                $entityId = $oldUser['email'] ?? "User #{$userId}";
                $auditManager->log('edit', 'user_account', $entityId,
                    ['status' => $oldUser['status'] ?? null],
                    ['status' => $newStatus],
                    "Status changed: {$entityId} -> {$newStatus}"
                );
            }
            echo json_encode($result);
            exit;

        case 'reset_password':
            if (!$canEdit) { echo json_encode(['success' => false, 'error' => 'Permission denied']); exit; }
            $userId = (int) $_POST['id'];
            $user   = $accountManager->getUserById($userId);
            echo json_encode(withAudit(
                $auditManager, 'edit', 'user_account',
                $user['email'] ?? "User #{$userId}",
                null, ['password_reset' => true],
                fn() => $accountManager->resetPassword($userId)
            ));
            exit;

        case 'unlock':
            if (!$canEdit) { echo json_encode(['success' => false, 'error' => 'Permission denied']); exit; }
            $userId = (int) $_POST['id'];
            $user   = $accountManager->getUserById($userId);
            echo json_encode(withAudit(
                $auditManager, 'edit', 'user_account',
                $user['email'] ?? "User #{$userId}",
                ['locked' => true], ['locked' => false],
                fn() => $accountManager->unlockAccount($userId)
            ));
            exit;

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            exit;
    }
}

// ── View ───────────────────────────────────────────────────────────────────────
requirePagePermission('account_management', 'view');

$canCreate = hasPagePermission('account_management', 'create');
$canEdit   = hasPagePermission('account_management', 'edit');
$canDelete = hasPagePermission('account_management', 'delete');

require_once __DIR__ . '/../core/RoleManager.php';
$roleManager = RoleManager::getInstance($db);
$users       = $accountManager->getAllUsers();
$userStats   = $accountManager->getUserStats();
$roles       = $roleManager->getAllRoles();

$pageTitle   = 'Account Management';
$breadcrumbs = [
    ['title' => 'Dashboard',          'url' => 'index.php'],
    ['title' => 'Security',           'url' => '#'],
    ['title' => 'Account Management', 'url' => ''],
];

ob_start();
?>

<!-- Statistics Cards -->
<div class="row mb-4">
    <?= statCard('Total Users',      $userStats['total'],     'primary', 'fas fa-users') ?>
    <?= statCard('Active',           $userStats['active'],    'success', 'fas fa-user-check') ?>
    <?= statCard('Pending Approval', $userStats['pending'],   'warning', 'fas fa-user-clock', $userStats['pending'] > 0 ? 'account_approval.php' : '') ?>
    <?= statCard('Suspended',        $userStats['suspended'], 'danger',  'fas fa-user-slash') ?>
</div>

<!-- Users Table -->
<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-users me-1"></i> User Accounts
        <?php if ($canCreate): ?>
        <button class="btn btn-primary btn-sm float-end" data-action="openAddModal">
            <i class="fas fa-plus"></i> Add User
        </button>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <table class="table table-striped table-hover" id="usersTable">
            <thead>
                <tr>
                    <th>ID</th><th>Name</th><th>Email</th><th>Role</th>
                    <th>Status</th><th>Verified</th><th>Last Login</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= $user['id'] ?></td>
                    <td>
                        <?= e($user['display_name'] ?? $user['first_name'] . ' ' . $user['last_name']) ?>
                        <?php if ($user['id'] === $session->getUserId()): ?>
                            <span class="badge bg-info">You</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e($user['email']) ?></td>
                    <td><span class="badge bg-secondary"><?= e($user['role_display_name'] ?? $user['role_name'] ?? '') ?></span></td>
                    <td><?= statusBadge($user['status']) ?></td>
                    <td>
                        <span class="badge bg-<?= $user['email_verified'] ? 'success' : 'secondary' ?>">
                            <i class="fas fa-<?= $user['email_verified'] ? 'check' : 'times' ?>"></i>
                        </span>
                    </td>
                    <td><?= $user['last_login_at'] ? date('M d, Y H:i', strtotime($user['last_login_at'])) : '<span class="text-muted">Never</span>' ?></td>
                    <td>
                        <?php if ($canEdit): ?>
                        <button class="btn btn-sm btn-primary"
                            data-action="openEditModal"
                            data-arg-json="<?= e(json_encode($user)) ?>"
                            title="Edit"><i class="fas fa-edit"></i></button>
                        <?php endif; ?>

                        <?php if ($canEdit && $user['id'] !== $session->getUserId()): ?>
                        <div class="btn-group">
                            <button type="button" class="btn btn-sm btn-secondary dropdown-toggle" data-bs-toggle="dropdown"><i class="fas fa-cog"></i></button>
                            <ul class="dropdown-menu">
                                <?php if ($user['status'] !== 'active'): ?><li><a class="dropdown-item" href="#" data-action="changeStatus" data-arg0="<?= $user['id'] ?>" data-arg1="active"><i class="fas fa-user-check text-success"></i> Activate</a></li><?php endif; ?>
                                <?php if ($user['status'] !== 'inactive'): ?><li><a class="dropdown-item" href="#" data-action="changeStatus" data-arg0="<?= $user['id'] ?>" data-arg1="inactive"><i class="fas fa-user-minus text-secondary"></i> Deactivate</a></li><?php endif; ?>
                                <?php if ($user['status'] !== 'suspended'): ?><li><a class="dropdown-item" href="#" data-action="changeStatus" data-arg0="<?= $user['id'] ?>" data-arg1="suspended"><i class="fas fa-user-slash text-danger"></i> Suspend</a></li><?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="#" data-action="resetPassword" data-arg0="<?= $user['id'] ?>" data-arg1="<?= e($user['display_name'] ?? $user['first_name'] ?? '') ?>"><i class="fas fa-key text-warning"></i> Reset Password</a></li>
                                <li><a class="dropdown-item" href="#" data-action="unlockAccount" data-arg0="<?= $user['id'] ?>"><i class="fas fa-unlock text-info"></i> Unlock Account</a></li>
                            </ul>
                        </div>
                        <?php endif; ?>

                        <?php if ($canDelete && $user['id'] !== $session->getUserId() && $user['id'] !== 1): ?>
                        <button class="btn btn-sm btn-danger"
                            data-action="deleteUser"
                            data-arg0="<?= $user['id'] ?>"
                            data-arg1="<?= e($user['display_name'] ?? $user['first_name'] ?? '') ?>"
                            title="Delete"><i class="fas fa-trash"></i></button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="userModalTitle">Add User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="userForm">
                <input type="hidden" name="csrf_token" value="<?= $session->getCSRFToken() ?>">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="id"     id="userId">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="firstName" name="first_name" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Middle Name</label>
                            <input type="text" class="form-control" id="middleName" name="middle_name">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="lastName" name="last_name" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" class="form-control" id="phone" name="phone">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Password <span class="text-danger password-required">*</span></label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="password" name="password">
                                <button class="btn btn-outline-secondary" type="button" data-action="togglePassword"><i class="fas fa-eye" id="toggleIcon"></i></button>
                                <button class="btn btn-outline-primary"   type="button" data-action="generatePassword"><i class="fas fa-random"></i></button>
                            </div>
                            <small class="text-muted edit-hint d-none">Leave blank to keep current password</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Role <span class="text-danger">*</span></label>
                            <select class="form-select" id="roleId" name="role_id" required>
                                <?php foreach ($roles as $role): ?>
                                <option value="<?= $role['id'] ?>"><?= e($role['display_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="pending">Pending Approval</option>
                                <option value="suspended">Suspended</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3 d-flex align-items-end">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="emailVerified" name="email_verified">
                                <label class="form-check-label" for="emailVerified">Email Verified</label>
                            </div>
                        </div>
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
    App.initDataTable('#usersTable');

    const userModal   = new bootstrap.Modal(document.getElementById('userModal'));
    const userModalEl = document.getElementById('userModal');
    App.initSelect2InModal(userModalEl, ['#roleId', '#status']);

    function openAddModal() {
        document.getElementById('userModalTitle').textContent = 'Add User';
        document.getElementById('formAction').value = 'create';
        document.getElementById('userForm').reset();
        document.getElementById('userId').value = '';
        document.querySelector('[name="csrf_token"]').value = '<?= $session->getCSRFToken() ?>';
        document.querySelector('.password-required').classList.remove('d-none');
        document.querySelector('.edit-hint').classList.add('d-none');
        document.getElementById('password').required = true;
        $('#roleId').val(3).trigger('change');
        $('#status').val('active').trigger('change');
        userModal.show();
    }

    function openEditModal(user) {
        document.getElementById('userModalTitle').textContent = 'Edit User';
        document.getElementById('formAction').value = 'update';
        document.getElementById('userId').value     = user.id;
        document.getElementById('firstName').value  = user.first_name  || '';
        document.getElementById('middleName').value = user.middle_name || '';
        document.getElementById('lastName').value   = user.last_name   || '';
        document.getElementById('email').value      = user.email       || '';
        document.getElementById('phone').value      = user.phone       || '';
        document.getElementById('password').value   = '';
        document.getElementById('emailVerified').checked = user.email_verified == 1;
        document.querySelector('[name="csrf_token"]').value = '<?= $session->getCSRFToken() ?>';
        document.querySelector('.password-required').classList.add('d-none');
        document.querySelector('.edit-hint').classList.remove('d-none');
        document.getElementById('password').required = false;
        userModal.show();
        setTimeout(() => {
            $('#roleId').val(user.role_id).trigger('change');
            $('#status').val(user.status).trigger('change');
        }, 100);
    }

    function deleteUser(id, name) {
        App.confirmDelete(name,
            { action: 'delete', id, csrf_token: '<?= $session->getCSRFToken() ?>' },
            'account_management.php', 'User');
    }

    function changeStatus(id, status) {
        const labels = { active: 'activate', inactive: 'deactivate', suspended: 'suspend' };
        Notify.confirm({ title: 'Confirm Status Change', text: `Are you sure you want to ${labels[status]} this user?`, confirmText: 'Yes, proceed' })
            .then(r => {
                if (!r.isConfirmed) return;
                App.postAction('account_management.php',
                    { action: 'change_status', id, status, csrf_token: '<?= $session->getCSRFToken() ?>' },
                    {
                        loadingMsg: 'Updating status...',
                        onSuccess() { Notify.actionSuccess('updated', 'User status'); setTimeout(() => location.reload(), 1500); },
                        onError(msg)  { Notify.error(msg); }
                    });
            });
    }

    function resetPassword(id, name) {
        Notify.confirm({ title: 'Reset Password', text: `Generate a new password for ${name}?`, confirmText: 'Yes, reset it', icon: 'warning' })
            .then(r => {
                if (!r.isConfirmed) return;
                App.postAction('account_management.php',
                    { action: 'reset_password', id, csrf_token: '<?= $session->getCSRFToken() ?>' },
                    {
                        loadingMsg: 'Resetting password...',
                        onSuccess(data) {
                            hideLoading();
                            Swal.fire({ title: 'Password Reset', html: `New password: <code class="user-select-all">${data.new_password}</code><br><small class="text-muted">Copy this — it will not be shown again.</small>`, icon: 'success' });
                        },
                        onError(msg) { Notify.error(msg); }
                    });
            });
    }

    function unlockAccount(id) {
        App.postAction('account_management.php',
            { action: 'unlock', id, csrf_token: '<?= $session->getCSRFToken() ?>' },
            {
                loadingMsg: 'Unlocking account...',
                onSuccess() { Notify.success('Account unlocked successfully'); setTimeout(() => location.reload(), 1500); },
                onError(msg) { Notify.error(msg); }
            });
    }

    function togglePassword() {
        const input = document.getElementById('password');
        const icon  = document.getElementById('toggleIcon');
        const show  = input.type === 'password';
        input.type  = show ? 'text' : 'password';
        icon.classList.toggle('fa-eye',       !show);
        icon.classList.toggle('fa-eye-slash',  show);
    }

    function generatePassword() {
        const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        const pwd   = Array.from({ length: 12 }, () => chars[Math.floor(Math.random() * chars.length)]).join('');
        document.getElementById('password').value = pwd;
        document.getElementById('password').type  = 'text';
        document.getElementById('toggleIcon').className = 'fas fa-eye-slash';
    }

    document.getElementById('userForm').addEventListener('submit', function (e) {
        e.preventDefault();
        const isUpdate = document.getElementById('formAction').value === 'update';
        App.submitForm('account_management.php', this, {
            loadingMsg: 'Saving user...',
            onSuccess(data) {
                userModal.hide();
                if (data.generated_password) {
                    Swal.fire({ title: 'User Created', html: `Generated password: <code class="user-select-all">${data.generated_password}</code><br><small class="text-muted">Copy this — it will not be shown again.</small>`, icon: 'success' })
                        .then(() => location.reload());
                } else {
                    Notify.actionSuccess(isUpdate ? 'updated' : 'added', 'User');
                    setTimeout(() => location.reload(), 1500);
                }
            },
            onError(msg) { Notify.error(msg); }
        });
    });
</script>
<?php
$pageScripts = ob_get_clean();
include 'include/layout.php';
