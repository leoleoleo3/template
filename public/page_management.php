<?php
/**
 * Page / Resource Management
 * CRUD for sidebar pages and RBAC resources (superadmin only).
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

    $pageData = fn() => [
        'parent_id'    => $_POST['parent_id'] ?: null,
        'name'         => $_POST['name']         ?? '',
        'display_name' => $_POST['display_name'] ?? '',
        'description'  => $_POST['description']  ?? '',
        'route'        => $_POST['route']         ?? '',
        'icon'         => $_POST['icon']          ?? 'fas fa-file',
        'sort_order'   => $_POST['sort_order']    ?? 0,
        'is_menu_item' => isset($_POST['is_menu_item']) ? 1 : 0,
    ];

    switch ($_POST['action']) {

        case 'create':
            $data   = $pageData();
            $result = $pageManager->createPage($data);
            if ($result['success']) {
                $permissionManager->logPermissionChange($session->getUserId(), 'create', 'page', $result['insert_id'], null, $pageManager->getPageById($result['insert_id']));
            }
            echo json_encode($result);
            exit;

        case 'update':
            $pageId  = (int) $_POST['id'];
            $oldPage = $pageManager->getPageById($pageId);
            $data    = $pageData();
            $result  = $pageManager->updatePage($pageId, $data);
            if ($result['success']) {
                $permissionManager->logPermissionChange($session->getUserId(), 'update', 'page', $pageId, $oldPage, $pageManager->getPageById($pageId));
            }
            echo json_encode($result);
            exit;

        case 'delete':
            $pageId  = (int) $_POST['id'];
            $oldPage = $pageManager->getPageById($pageId);
            $result  = $pageManager->deletePage($pageId);
            if ($result['success']) {
                $permissionManager->logPermissionChange($session->getUserId(), 'delete', 'page', $pageId, $oldPage, null);
            }
            echo json_encode($result);
            exit;

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            exit;
    }
}

// ── View ───────────────────────────────────────────────────────────────────────
$pages    = $pageManager->getPagesTree();
$allPages = $pageManager->getAllPages();

$pageTitle   = 'Page Management';
$breadcrumbs = [
    ['title' => 'Dashboard',      'url' => 'index.php'],
    ['title' => 'Security',       'url' => '#'],
    ['title' => 'Page Management','url' => ''],
];

ob_start();

/**
 * Render page rows recursively for nested page tree.
 */
function renderPageRows(array $pages, int $level = 0): void
{
    foreach ($pages as $page) {
        $indent = $level * 30 + 10;
        $icon   = e($page['icon']);
        $color  = empty($page['children']) ? 'info' : 'warning';
        echo "<tr>";
        echo "<td>{$page['id']}</td>";
        echo "<td style=\"padding-left:{$indent}px;\"><i class=\"{$icon} text-{$color}\"></i> <strong>" . e($page['display_name']) . "</strong><br><small class=\"text-muted\"><code>" . e($page['name']) . "</code></small></td>";
        echo "<td><code>" . e($page['route'] ?? '-') . "</code></td>";
        echo "<td><span class=\"badge bg-" . ($page['is_menu_item'] ? 'success' : 'secondary') . "\">" . ($page['is_menu_item'] ? 'Yes' : 'No') . "</span></td>";
        echo "<td>{$page['sort_order']}</td>";
        echo "<td>";
        echo "<button class=\"btn btn-sm btn-primary\" data-action=\"openEditModal\" data-arg-json=\"" . e(json_encode($page)) . "\"><i class=\"fas fa-edit\"></i></button> ";
        echo "<button class=\"btn btn-sm btn-danger\" data-action=\"deletePage\" data-arg0=\"{$page['id']}\" data-arg1=\"" . e($page['display_name']) . "\"><i class=\"fas fa-trash\"></i></button>";
        echo "</td></tr>";
        if (!empty($page['children'])) {
            renderPageRows($page['children'], $level + 1);
        }
    }
}
?>

<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-file-alt me-1"></i> Pages &amp; Resources
        <button class="btn btn-primary btn-sm float-end" data-action="openAddModal">
            <i class="fas fa-plus"></i> Add Page
        </button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr><th>ID</th><th>Page Name</th><th>Route</th><th>Menu Item</th><th>Sort</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php renderPageRows($pages); ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="pageModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="pageModalTitle">Add Page</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="pageForm">
                <input type="hidden" name="csrf_token" value="<?= $session->getCSRFToken() ?>">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="id"     id="pageId">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Page Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="pageName" name="name" required>
                            <small class="text-muted">Lowercase, underscores (e.g. user_management)</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Display Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="pageDisplayName" name="display_name" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Route / URL</label>
                            <input type="text" class="form-control" id="pageRoute" name="route" placeholder="e.g. users.php">
                            <small class="text-muted">Leave empty for parent/group pages</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Parent Page</label>
                            <select class="form-select" id="pageParent" name="parent_id">
                                <option value="">None (Top Level)</option>
                                <?php foreach ($allPages as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= e($p['display_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Icon Class</label>
                            <input type="text" class="form-control" id="pageIcon" name="icon" value="fas fa-file">
                            <small class="text-muted">Font Awesome (e.g. fas fa-user)</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Sort Order</label>
                            <input type="number" class="form-control" id="pageSortOrder" name="sort_order" value="0">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" id="pageDescription" name="description" rows="2"></textarea>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="pageMenuItem" name="is_menu_item" checked>
                        <label class="form-check-label" for="pageMenuItem">Show in Navigation Menu</label>
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
<script nonce="<?= csp_nonce() ?>">
    const pageModal   = new bootstrap.Modal(document.getElementById('pageModal'));
    const pageModalEl = document.getElementById('pageModal');
    App.initSelect2InModal(pageModalEl, ['#pageParent'], { allowClear: true, placeholder: 'None (Top Level)' });

    function openAddModal() {
        document.getElementById('pageModalTitle').textContent = 'Add Page';
        document.getElementById('formAction').value = 'create';
        document.getElementById('pageForm').reset();
        document.getElementById('pageId').value    = '';
        document.getElementById('pageIcon').value  = 'fas fa-file';
        document.getElementById('pageMenuItem').checked = true;
        document.querySelector('[name="csrf_token"]').value = '<?= $session->getCSRFToken() ?>';
        pageModal.show();
    }

    function openEditModal(page) {
        document.getElementById('pageModalTitle').textContent = 'Edit Page';
        document.getElementById('formAction').value       = 'update';
        document.getElementById('pageId').value           = page.id;
        document.getElementById('pageName').value         = page.name;
        document.getElementById('pageDisplayName').value  = page.display_name;
        document.getElementById('pageRoute').value        = page.route        || '';
        document.getElementById('pageParent').value       = page.parent_id    || '';
        document.getElementById('pageIcon').value         = page.icon         || 'fas fa-file';
        document.getElementById('pageSortOrder').value    = page.sort_order;
        document.getElementById('pageDescription').value  = page.description  || '';
        document.getElementById('pageMenuItem').checked   = page.is_menu_item == 1;
        document.querySelector('[name="csrf_token"]').value = '<?= $session->getCSRFToken() ?>';
        pageModal.show();
    }

    function deletePage(id, name) {
        App.confirmDelete(name,
            { action: 'delete', id, csrf_token: '<?= $session->getCSRFToken() ?>' },
            'page_management.php', 'Page');
    }

    document.getElementById('pageForm').addEventListener('submit', function (e) {
        e.preventDefault();
        const isUpdate = document.getElementById('formAction').value === 'update';
        App.submitForm('page_management.php', this, {
            loadingMsg: 'Saving page...',
            onSuccess() { pageModal.hide(); Notify.actionSuccess(isUpdate ? 'updated' : 'added', 'Page'); setTimeout(() => location.reload(), 1500); },
            onError(msg) { Notify.error(msg); }
        });
    });
</script>
<?php
$pageScripts = ob_get_clean();
include 'include/layout.php';
