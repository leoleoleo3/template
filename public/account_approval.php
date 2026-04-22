<?php
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/AccountManager.php';
require_once __DIR__ . '/../core/RoleManager.php';
require_once __DIR__ . '/../core/PermissionManager.php';
require_once __DIR__ . '/../core/AuditTrailManager.php';

$config = require __DIR__ . '/../config/database.php';
$db = new DB($config['host'], $config['user'], $config['pass'], $config['name'], $config['port']);
$session = Session::getInstance($db);

// Check if this is an AJAX request
$isAjax = $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']);

// For AJAX requests, return JSON errors instead of redirecting
if ($isAjax) {
    header('Content-Type: application/json');

    // Check authentication for AJAX
    if (!$session->isAuthenticated()) {
        echo json_encode(['success' => false, 'error' => 'Session expired. Please refresh the page and log in again.', 'redirect' => 'login.php']);
        exit;
    }
}

$accountManager = AccountManager::getInstance($db);
$roleManager = RoleManager::getInstance($db);
$permissionManager = PermissionManager::getInstance($db);
$auditManager = AuditTrailManager::getInstance($db);

// Require authentication (for non-AJAX requests)
if (!$isAjax) {
    $session->requireLogin();
}

// Check permission
$userRoleId = $session->get('role_id', 1);
if (!$permissionManager->hasPermission($userRoleId, 'account_approval', 'view')) {
    if ($isAjax) {
        echo json_encode(['success' => false, 'error' => 'Permission denied']);
        exit;
    }
    http_response_code(403);
    include __DIR__ . '/errors/403.php';
    exit;
}

$canApprove = $permissionManager->hasPermission($userRoleId, 'account_approval', 'edit');

// Handle AJAX requests
if ($isAjax) {
    // Verify CSRF token
    if (!$session->verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token. Please refresh the page.']);
        exit;
    }

    if (!$canApprove) {
        echo json_encode(['success' => false, 'error' => 'Permission denied']);
        exit;
    }

    $action = $_POST['action'];
    $userId = (int)$_POST['id'];

    switch ($action) {
        case 'approve':
            $oldUser = $accountManager->getUserById($userId);
            $result = $accountManager->approveUser($userId, $session->getUserId());
            if ($result['success']) {
                $entityId = $oldUser['email'] ?? "User #{$userId}";
                $auditManager->log('approve', 'account_approval', $entityId,
                    ['status' => $oldUser['status'] ?? null],
                    ['status' => 'active'],
                    "Approved account: {$entityId}"
                );
            }
            echo json_encode($result);
            exit;

        case 'reject':
            $reason = $_POST['reason'] ?? '';
            $oldUser = $accountManager->getUserById($userId);
            $result = $accountManager->rejectUser($userId, $reason);
            if ($result['success']) {
                $entityId = $oldUser['email'] ?? "User #{$userId}";
                $auditManager->log('reject', 'account_approval', $entityId,
                    ['status' => $oldUser['status'] ?? null],
                    ['status' => 'rejected', 'reason' => $reason],
                    "Rejected account: {$entityId}" . ($reason ? " — Reason: {$reason}" : '')
                );
            }
            echo json_encode($result);
            exit;

        case 'approve_all':
            $ids = json_decode($_POST['ids'] ?? '[]', true);
            $successCount = 0;
            $errors = [];

            foreach ($ids as $id) {
                $result = $accountManager->approveUser((int)$id, $session->getUserId());
                if ($result['success']) {
                    $successCount++;
                } else {
                    $errors[] = "User $id: " . ($result['error'] ?? 'Unknown error');
                }
            }

            if ($successCount > 0) {
                $auditManager->log('approve', 'account_approval', 'Bulk Approval',
                    null,
                    ['approved_count' => $successCount],
                    "Bulk approved {$successCount} account(s)"
                );
            }
            echo json_encode([
                'success' => $successCount > 0,
                'approved_count' => $successCount,
                'errors' => $errors
            ]);
            exit;

        case 'update_role':
            $newRoleId = (int)$_POST['role_id'];
            $oldUser = $accountManager->getUserById($userId);
            $result = $accountManager->updateUser($userId, ['role_id' => $newRoleId]);
            if ($result['success']) {
                $entityId = $oldUser['email'] ?? "User #{$userId}";
                $auditManager->log('edit', 'account_approval', $entityId,
                    ['role_id' => (int)($oldUser['role_id'] ?? 0)],
                    ['role_id' => $newRoleId],
                    "Updated role for: {$entityId}"
                );
            }
            echo json_encode($result);
            exit;

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            exit;
    }
}

// Get pending users
$pendingUsers = $accountManager->getUsersByStatus('pending');
$pendingCount = count($pendingUsers);
$roles = $roleManager->getAllRoles();

// Include RBAC init for sidebar
require_once 'include/rbac_init.php';

// Set page variables
$pageTitle = 'Account Approval';
$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => 'index.php'],
    ['title' => 'Security', 'url' => '#'],
    ['title' => 'Account Approval', 'url' => '']
];

// Start output buffering for page content
ob_start();
?>

<!-- Pending Count Alert -->
<?php if ($pendingCount > 0): ?>
<div class="alert alert-warning alert-dismissible fade show" role="alert">
    <i class="fas fa-user-clock me-2"></i>
    <strong><?= $pendingCount ?></strong> user<?= $pendingCount > 1 ? 's are' : ' is' ?> waiting for approval.
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<!-- Pending Users Table -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <i class="fas fa-user-clock me-1"></i>
            Pending Account Approvals
            <span class="badge bg-warning ms-2"><?= $pendingCount ?></span>
        </div>
        <?php if ($canApprove && $pendingCount > 0): ?>
        <div>
            <button class="btn btn-success btn-sm" data-action="approveSelected" id="approveSelectedBtn" disabled>
                <i class="fas fa-check-double"></i> Approve Selected
            </button>
            <button class="btn btn-success btn-sm ms-2" data-action="approveAll">
                <i class="fas fa-check-circle"></i> Approve All
            </button>
        </div>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if ($pendingCount === 0): ?>
        <div class="text-center py-5">
            <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
            <h4>No Pending Approvals</h4>
            <p class="text-muted">All account requests have been processed.</p>
            <a href="account_management.php" class="btn btn-primary">
                <i class="fas fa-users"></i> View All Accounts
            </a>
        </div>
        <?php else: ?>
        <table class="table table-striped table-hover" id="pendingTable">
            <thead>
                <tr>
                    <th>
                        <input type="checkbox" class="form-check-input" id="selectAll" data-change-self="toggleSelectAll">
                    </th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Requested Role</th>
                    <th>Registered On</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pendingUsers as $user): ?>
                <tr data-user-id="<?= $user['id'] ?>">
                    <td>
                        <input type="checkbox" class="form-check-input user-checkbox" value="<?= $user['id'] ?>" data-change-self="updateSelectedCount">
                    </td>
                    <td>
                        <strong><?= htmlspecialchars($user['display_name'] ?? $user['first_name'] . ' ' . $user['last_name']) ?></strong>
                    </td>
                    <td>
                        <a href="mailto:<?= htmlspecialchars($user['email']) ?>"><?= htmlspecialchars($user['email']) ?></a>
                        <?php if ($user['email_verified']): ?>
                            <span class="badge bg-success" title="Email Verified"><i class="fas fa-check"></i></span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($user['phone'] ?? '-') ?></td>
                    <td>
                        <select class="form-select form-select-sm select2-role-inline" data-user-id="<?= $user['id'] ?>" style="width: 150px;">
                            <?php foreach ($roles as $role): ?>
                            <option value="<?= $role['id'] ?>" <?= $user['role_id'] == $role['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($role['display_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <span title="<?= date('Y-m-d H:i:s', strtotime($user['created_at'])) ?>">
                            <?= date('M d, Y', strtotime($user['created_at'])) ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($canApprove): ?>
                        <button class="btn btn-sm btn-success" data-action="approveUser" data-arg0="<?= $user['id'] ?>" data-arg1="<?= htmlspecialchars($user['display_name'] ?? $user['first_name'], ENT_QUOTES) ?>" title="Approve">
                            <i class="fas fa-check"></i> Approve
                        </button>
                        <button class="btn btn-sm btn-danger" data-action="rejectUser" data-arg0="<?= $user['id'] ?>" data-arg1="<?= htmlspecialchars($user['display_name'] ?? $user['first_name'], ENT_QUOTES) ?>" title="Reject">
                            <i class="fas fa-times"></i> Reject
                        </button>
                        <?php else: ?>
                        <span class="text-muted">View Only</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- Quick Stats -->
<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-chart-pie me-1"></i>
                Registration Statistics
            </div>
            <div class="card-body">
                <?php
                $stats = $accountManager->getUserStats();
                ?>
                <table class="table table-sm">
                    <tr>
                        <td>Total Registered Users</td>
                        <td class="text-end"><strong><?= $stats['total'] ?></strong></td>
                    </tr>
                    <tr>
                        <td>Active Users</td>
                        <td class="text-end"><span class="badge bg-success"><?= $stats['active'] ?></span></td>
                    </tr>
                    <tr>
                        <td>Pending Approval</td>
                        <td class="text-end"><span class="badge bg-warning"><?= $stats['pending'] ?></span></td>
                    </tr>
                    <tr>
                        <td>Suspended</td>
                        <td class="text-end"><span class="badge bg-danger"><?= $stats['suspended'] ?></span></td>
                    </tr>
                    <tr>
                        <td>Inactive</td>
                        <td class="text-end"><span class="badge bg-secondary"><?= $stats['inactive'] ?></span></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-info-circle me-1"></i>
                About Account Approval
            </div>
            <div class="card-body">
                <p class="mb-2"><i class="fas fa-check-circle text-success me-2"></i> <strong>Approve</strong> - Activates the account and allows user login</p>
                <p class="mb-2"><i class="fas fa-times-circle text-danger me-2"></i> <strong>Reject</strong> - Removes the pending registration</p>
                <p class="mb-0"><i class="fas fa-user-tag text-primary me-2"></i> <strong>Role</strong> - Change the user's role before approving</p>
                <hr>
                <small class="text-muted">
                    <i class="fas fa-lightbulb me-1"></i>
                    Tip: You can change a user's role before approving to assign them proper permissions.
                </small>
            </div>
        </div>
    </div>
</div>

<?php
// Get page content
$pageContent = ob_get_clean();

// Page-specific scripts
ob_start();
?>
<script nonce="<?= csp_nonce() ?>" src="js/simple-datatables.min.js"></script>
<script nonce="<?= csp_nonce() ?>">
    // Helper function to handle AJAX responses with potential redirects
    function handleAjaxResponse(response) {
        return response.text().then(text => {
            try {
                const data = JSON.parse(text);
                // Check if session expired
                if (data.redirect) {
                    Notify.error(data.error || 'Session expired');
                    setTimeout(() => window.location.href = data.redirect, 2000);
                    return Promise.reject(new Error('Session expired'));
                }
                return data;
            } catch (e) {
                // Response is not JSON (likely HTML redirect page)
                Notify.error('Session expired. Please log in again.');
                setTimeout(() => window.location.href = 'login.php', 2000);
                return Promise.reject(new Error('Invalid response'));
            }
        });
    }

    <?php if ($pendingCount > 0): ?>
    // Initialize DataTable
    const dataTable = new simpleDatatables.DataTable("#pendingTable", {
        columns: [
            { select: 0, sortable: false },
            { select: 6, sortable: false }
        ]
    });

    // Initialize Select2 for role dropdowns
    $(document).ready(function() {
        $('.select2-role-inline').select2({
            theme: 'bootstrap-5',
            width: '150px',
            minimumResultsForSearch: Infinity
        });

        // Handle role change
        $('.select2-role-inline').on('change', function() {
            const userId = $(this).data('user-id');
            const roleId = $(this).val();
            updateUserRole(userId, roleId);
        });
    });
    <?php endif; ?>

    function toggleSelectAll() {
        const selectAll = document.getElementById('selectAll');
        const checkboxes = document.querySelectorAll('.user-checkbox');
        checkboxes.forEach(cb => cb.checked = selectAll.checked);
        updateSelectedCount();
    }

    function updateSelectedCount() {
        const checkboxes = document.querySelectorAll('.user-checkbox:checked');
        const btn = document.getElementById('approveSelectedBtn');
        if (btn) {
            btn.disabled = checkboxes.length === 0;
            btn.innerHTML = `<i class="fas fa-check-double"></i> Approve Selected (${checkboxes.length})`;
        }
    }

    function getSelectedIds() {
        const checkboxes = document.querySelectorAll('.user-checkbox:checked');
        return Array.from(checkboxes).map(cb => parseInt(cb.value));
    }

    function approveUser(id, name) {
        Notify.confirm({
            title: 'Approve Account',
            text: `Approve ${name}'s account? They will be able to log in after approval.`,
            confirmText: 'Yes, approve',
            icon: 'question'
        }).then(result => {
            if (result.isConfirmed) {
                showLoading('Approving account...');

                const formData = new FormData();
                formData.append('action', 'approve');
                formData.append('id', id);
                formData.append('csrf_token', '<?= $session->getCSRFToken() ?>');

                fetch('account_approval.php', {
                    method: 'POST',
                    body: formData
                })
                .then(handleAjaxResponse)
                .then(data => {
                    if (data.success) {
                        Notify.success('Account approved successfully');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        hideLoading();
                        Notify.error(data.error);
                    }
                })
                .catch(err => {
                    hideLoading();
                    Notify.error(err.message);
                });
            }
        });
    }

    function rejectUser(id, name) {
        Swal.fire({
            title: 'Reject Account',
            text: `Are you sure you want to reject ${name}'s registration?`,
            icon: 'warning',
            input: 'textarea',
            inputLabel: 'Reason (optional)',
            inputPlaceholder: 'Enter reason for rejection...',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: '<i class="fas fa-times"></i> Reject',
            cancelButtonText: 'Cancel',
            reverseButtons: true
        }).then(result => {
            if (result.isConfirmed) {
                showLoading('Rejecting account...');

                const formData = new FormData();
                formData.append('action', 'reject');
                formData.append('id', id);
                formData.append('reason', result.value || '');
                formData.append('csrf_token', '<?= $session->getCSRFToken() ?>');

                fetch('account_approval.php', {
                    method: 'POST',
                    body: formData
                })
                .then(handleAjaxResponse)
                .then(data => {
                    if (data.success) {
                        Notify.success('Account rejected');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        hideLoading();
                        Notify.error(data.error);
                    }
                })
                .catch(err => {
                    hideLoading();
                    Notify.error(err.message);
                });
            }
        });
    }

    function approveSelected() {
        const ids = getSelectedIds();
        if (ids.length === 0) {
            Notify.warning('Please select at least one user to approve');
            return;
        }

        Notify.confirm({
            title: 'Approve Selected',
            text: `Approve ${ids.length} selected account(s)?`,
            confirmText: 'Yes, approve all',
            icon: 'question'
        }).then(result => {
            if (result.isConfirmed) {
                showLoading('Approving accounts...');

                const formData = new FormData();
                formData.append('action', 'approve_all');
                formData.append('ids', JSON.stringify(ids));
                formData.append('csrf_token', '<?= $session->getCSRFToken() ?>');

                fetch('account_approval.php', {
                    method: 'POST',
                    body: formData
                })
                .then(handleAjaxResponse)
                .then(data => {
                    if (data.success) {
                        Notify.success(`${data.approved_count} account(s) approved`);
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        hideLoading();
                        Notify.error(data.errors.join('<br>'));
                    }
                })
                .catch(err => {
                    hideLoading();
                    Notify.error(err.message);
                });
            }
        });
    }

    function approveAll() {
        const allIds = [];
        document.querySelectorAll('.user-checkbox').forEach(cb => allIds.push(parseInt(cb.value)));

        if (allIds.length === 0) {
            Notify.info('No pending accounts to approve');
            return;
        }

        Notify.confirm({
            title: 'Approve All Accounts',
            text: `This will approve all ${allIds.length} pending account(s). Continue?`,
            confirmText: 'Yes, approve all',
            icon: 'question'
        }).then(result => {
            if (result.isConfirmed) {
                showLoading('Approving all accounts...');

                const formData = new FormData();
                formData.append('action', 'approve_all');
                formData.append('ids', JSON.stringify(allIds));
                formData.append('csrf_token', '<?= $session->getCSRFToken() ?>');

                fetch('account_approval.php', {
                    method: 'POST',
                    body: formData
                })
                .then(handleAjaxResponse)
                .then(data => {
                    if (data.success) {
                        Notify.success(`${data.approved_count} account(s) approved`);
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        hideLoading();
                        Notify.error(data.errors.join('<br>'));
                    }
                })
                .catch(err => {
                    hideLoading();
                    Notify.error(err.message);
                });
            }
        });
    }

    function updateUserRole(userId, roleId) {
        const formData = new FormData();
        formData.append('action', 'update_role');
        formData.append('id', userId);
        formData.append('role_id', roleId);
        formData.append('csrf_token', '<?= $session->getCSRFToken() ?>');

        fetch('account_approval.php', {
            method: 'POST',
            body: formData
        })
        .then(handleAjaxResponse)
        .then(data => {
            if (data.success) {
                Notify.success('Role updated');
            } else {
                Notify.error(data.error);
            }
        })
        .catch(err => {
            Notify.error(err.message);
        });
    }
</script>
<?php
$pageScripts = ob_get_clean();

// Render layout
include 'include/layout.php';
?>
