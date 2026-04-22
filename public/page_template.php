<?php
/**
 * PAGE TEMPLATE — Copy this file to create a new page.
 *
 * Quick start:
 *   1. Copy this file and rename it (e.g. products.php)
 *   2. Replace every occurrence of 'page_name' with your page identifier
 *   3. Set $pageTitle and $breadcrumbs
 *   4. Put your HTML inside the ob_start() / ob_get_clean() block ($pageContent)
 *   5. Put page-specific <script> tags inside the second ob_start() block ($pageScripts)
 *      — scripts are injected just before </body> so CSP nonces are valid
 */

require_once __DIR__ . '/../core/bootstrap.php';
require_once 'include/rbac_init.php';

// ── Guard ─────────────────────────────────────────────────────────────────────
// For AJAX-capable pages, detect early and call startAjax().
// For view-only pages, requirePagePermission() is enough.
$isAjax = $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']);
if ($isAjax) {
    startAjax(fn() => hasPagePermission('page_name', 'view'));
} else {
    requirePagePermission('page_name', 'view');
}

// ── AJAX handler (remove if not needed) ──────────────────────────────────────
if ($isAjax) {
    switch ($_POST['action']) {

        case 'create':
            // $result = $someManager->create([...]);
            echo json_encode(['success' => true]);
            exit;

        case 'update':
            echo json_encode(['success' => true]);
            exit;

        case 'delete':
            echo json_encode(['success' => true]);
            exit;

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            exit;
    }
}

// ── View ───────────────────────────────────────────────────────────────────────
$pageTitle   = 'Your Page Title';
$breadcrumbs = [
    ['title' => 'Dashboard',      'url' => 'index.php'],
    ['title' => 'Your Page Title','url' => ''],
];

ob_start();
?>

<!-- ── Page content ─────────────────────────────────────────────────────────── -->
<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-table me-1"></i> <?= e($pageTitle) ?>
        <?php if (hasPagePermission('page_name', 'create')): ?>
        <button class="btn btn-primary btn-sm float-end" data-action="openAddModal">
            <i class="fas fa-plus"></i> Add New
        </button>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <p class="text-muted">Replace this content with your own.</p>
    </div>
</div>

<!-- ── Example modal ────────────────────────────────────────────────────────── -->
<div class="modal fade" id="itemModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="itemModalTitle">Add Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="itemForm">
                <input type="hidden" name="csrf_token" value="<?= $session->getCSRFToken() ?>">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="id"     id="itemId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" required>
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

// ── Page scripts (injected before </body>) ────────────────────────────────────
ob_start();
?>
<script nonce="<?= csp_nonce() ?>" src="js/simple-datatables.min.js"></script>
<script nonce="<?= csp_nonce() ?>">
    const itemModal = new bootstrap.Modal(document.getElementById('itemModal'));

    // App.initDataTable('#myTable');                        // simpleDatatables wrapper
    // App.initSelect2InModal(modalEl, ['#sel'], opts);      // Select2 in modal

    function openAddModal() {
        document.getElementById('itemModalTitle').textContent = 'Add Item';
        document.getElementById('formAction').value = 'create';
        document.getElementById('itemId').value = '';
        document.getElementById('itemForm').reset();
        document.querySelector('#itemForm [name="csrf_token"]').value = '<?= $session->getCSRFToken() ?>';
        itemModal.show();
    }

    function openEditModal(item) {
        document.getElementById('itemModalTitle').textContent = 'Edit Item';
        document.getElementById('formAction').value = 'update';
        document.getElementById('itemId').value = item.id;
        // populate other fields…
        document.querySelector('#itemForm [name="csrf_token"]').value = '<?= $session->getCSRFToken() ?>';
        itemModal.show();
    }

    function deleteItem(id, name) {
        App.confirmDelete(name,
            { action: 'delete', id, csrf_token: '<?= $session->getCSRFToken() ?>' },
            'page_template.php', 'Item');
    }

    document.getElementById('itemForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const isUpdate = document.getElementById('formAction').value === 'update';
        App.submitForm('page_template.php', this, {
            loadingMsg: 'Saving...',
            onSuccess() { itemModal.hide(); Notify.actionSuccess(isUpdate ? 'updated' : 'added', 'Item'); setTimeout(() => location.reload(), 1500); },
            onError(msg) { Notify.error(msg); }
        });
    });
</script>
<?php
$pageScripts = ob_get_clean();
include 'include/layout.php';
