<?php
/**
 * Audit Trail Page
 * View system audit trail and activity logs with filters, pagination, and export
 */

require_once __DIR__ . '/../core/bootstrap.php';
require_once 'include/rbac_init.php';
require_once __DIR__ . '/include/web_settings.php'; // provides $siteName for PDF export

$auditTrailManager = AuditTrailManager::getInstance($db);

// ── Guard ─────────────────────────────────────────────────────────────────────
$isAjax = $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']);
if ($isAjax) {
    startAjax(fn() => hasPagePermission('audit_trail', 'view'));
} else {
    requirePagePermission('audit_trail', 'view');
}

// ── AJAX handler ───────────────────────────────────────────────────────────────
if ($isAjax) {
    $action = $_POST['action'];

    switch ($action) {
        case 'filter':
            $filters = [];
            if (!empty($_POST['date_from']))    $filters['date_from']    = $_POST['date_from'];
            if (!empty($_POST['date_to']))      $filters['date_to']      = $_POST['date_to'];
            if (!empty($_POST['user_id']))       $filters['user_id']      = (int) $_POST['user_id'];
            if (!empty($_POST['action_type']))   $filters['action']       = $_POST['action_type'];
            if (!empty($_POST['entity_type']))   $filters['entity_type']  = $_POST['entity_type'];
            if (!empty($_POST['search']))        $filters['search']       = $_POST['search'];

            $page   = max(1, (int) ($_POST['page'] ?? 1));
            $limit  = 50;
            $offset = ($page - 1) * $limit;

            $rows   = $auditTrailManager->getAuditTrail($filters, $limit, $offset);
            $total  = $auditTrailManager->getAuditTrailCount($filters);
            $stats  = $auditTrailManager->getSummaryStats();

            echo json_encode([
                'success'     => true,
                'rows'        => $rows,
                'total'       => $total,
                'page'        => $page,
                'per_page'    => $limit,
                'total_pages' => (int) ceil($total / $limit),
                'stats'       => $stats
            ]);
            exit;

        case 'search_users':
            $term = trim($_POST['term'] ?? '');
            if (strlen($term) < 2) {
                echo json_encode(['results' => []]);
                exit;
            }
            $users = $auditTrailManager->searchUsers($term);
            echo json_encode(['results' => $users]);
            exit;

        case 'get_filters':
            echo json_encode([
                'success'      => true,
                'action_types' => $auditTrailManager->getActionTypes(),
                'entity_types' => $auditTrailManager->getEntityTypes()
            ]);
            exit;

        case 'get_detail':
            $rowId = (int) ($_POST['row_id'] ?? 0);
            if ($rowId <= 0) {
                echo json_encode(['success' => false, 'error' => 'Invalid row ID']);
                exit;
            }
            $verification = $auditTrailManager->verifyIntegrity($rowId);
            $row = $verification['row'];

            // Resolve IDs to labels for display (does not affect stored data)
            if ($row) {
                $row['old_value_resolved'] = $auditTrailManager->resolveIds($row['old_value'] ?? null);
                $row['new_value_resolved'] = $auditTrailManager->resolveIds($row['new_value'] ?? null);
            }

            echo json_encode([
                'success'   => true,
                'row'       => $row,
                'integrity' => $verification['valid']
            ]);
            exit;

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            exit;
    }
}

// Page metadata
$pageTitle = 'Audit Trail';
$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => 'index.php'],
    ['title' => 'Security', 'url' => '#'],
    ['title' => 'Audit Trail', 'url' => '']
];

// Page content
ob_start();
?>

<!-- Summary Cards -->
<div class="row mb-4" id="summaryCards">
    <div class="col-xl-3 col-md-6">
        <div class="card bg-primary text-white mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-xs font-weight-bold text-uppercase mb-1">Total Events</div>
                        <div class="h5 mb-0" id="statTotalEvents">0</div>
                    </div>
                    <div class="fa-2x"><i class="fas fa-database"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card bg-success text-white mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-xs font-weight-bold text-uppercase mb-1">Events Today</div>
                        <div class="h5 mb-0" id="statEventsToday">0</div>
                    </div>
                    <div class="fa-2x"><i class="fas fa-calendar-day"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card bg-info text-white mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-xs font-weight-bold text-uppercase mb-1">Active Users Today</div>
                        <div class="h5 mb-0" id="statUniqueUsers">0</div>
                    </div>
                    <div class="fa-2x"><i class="fas fa-users"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card bg-warning text-white mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-xs font-weight-bold text-uppercase mb-1">Most Active Entity</div>
                        <div class="h5 mb-0 text-capitalize" id="statMostActive">N/A</div>
                    </div>
                    <div class="fa-2x"><i class="fas fa-fire"></i></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-filter me-1"></i> Filters
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-3 mb-3">
                <label for="filterDateFrom" class="form-label">Date From</label>
                <input type="text" class="form-control datepicker" id="filterDateFrom"
                       placeholder="YYYY-MM-DD" autocomplete="off">
            </div>
            <div class="col-md-3 mb-3">
                <label for="filterDateTo" class="form-label">Date To</label>
                <input type="text" class="form-control datepicker" id="filterDateTo"
                       placeholder="YYYY-MM-DD" autocomplete="off">
            </div>
            <div class="col-md-3 mb-3">
                <label for="filterUser" class="form-label">User</label>
                <select class="form-select" id="filterUser"></select>
            </div>
            <div class="col-md-3 mb-3">
                <label for="filterSearch" class="form-label">Search</label>
                <input type="text" class="form-control" id="filterSearch"
                       placeholder="Description, entity ID, user...">
            </div>
        </div>
        <div class="row">
            <div class="col-md-3 mb-3">
                <label for="filterAction" class="form-label">Action Type</label>
                <select class="form-select" id="filterAction">
                    <option value="">All Actions</option>
                </select>
            </div>
            <div class="col-md-3 mb-3">
                <label for="filterEntity" class="form-label">Entity Type</label>
                <select class="form-select" id="filterEntity">
                    <option value="">All Entities</option>
                </select>
            </div>
            <div class="col-md-6 mb-3 d-flex align-items-end gap-2">
                <button type="button" class="btn btn-primary" data-action="applyFilter" data-arg0="1">
                    <i class="fas fa-search"></i> Apply Filter
                </button>
                <button type="button" class="btn btn-secondary" data-action="resetFilter">
                    <i class="fas fa-undo"></i> Reset
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Audit Trail Table -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <i class="fas fa-history me-1"></i>
            Audit Trail
            <span class="badge bg-secondary ms-2" id="recordCount">0 records</span>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-success btn-sm" data-action="exportExcel" title="Export to Excel">
                <i class="fas fa-file-excel"></i> Excel
            </button>
            <button class="btn btn-danger btn-sm" data-action="exportPDF" title="Export to PDF">
                <i class="fas fa-file-pdf"></i> PDF
            </button>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover table-sm" id="auditTable">
                <thead class="table-light">
                    <tr>
                        <th style="width:160px;">Timestamp</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Entity Type</th>
                        <th>Entity ID</th>
                        <th>Description</th>
                        <th>IP Address</th>
                        <th style="width:80px;">Details</th>
                    </tr>
                </thead>
                <tbody id="auditTableBody">
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            <i class="fas fa-info-circle me-1"></i>
                            Click "Apply Filter" to load audit trail data
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <!-- Pagination -->
        <nav id="paginationNav" style="display:none;">
            <ul class="pagination justify-content-center mb-0" id="paginationList"></ul>
        </nav>
    </div>
</div>

<!-- Detail Modal -->
<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-info-circle me-1"></i> Audit Trail Detail
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailModalBody"></div>
            <div class="modal-footer">
                <span id="integrityBadge"></span>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php
$pageContent = ob_get_clean();

// Page scripts
ob_start();
?>
<script nonce="<?= csp_nonce() ?>" src="js/xlsx.mini.min.js"></script>
<script nonce="<?= csp_nonce() ?>">
const csrfToken = '<?= $session->getCSRFToken() ?>';
const auditSiteName = <?= json_encode($siteName) ?>;
let currentData = [];
let currentPage = 1;
let totalPages = 1;
const detailModal = new bootstrap.Modal(document.getElementById('detailModal'));

document.addEventListener('DOMContentLoaded', function() {
    // Initialize datepickers
    $('.datepicker').datepicker({
        format: 'yyyy-mm-dd',
        autoclose: true,
        todayHighlight: true,
        clearBtn: true
    });

    // User filter - Select2 AJAX typeahead
    $('#filterUser').select2({
        theme: 'bootstrap-5',
        placeholder: 'Search user by name or email...',
        allowClear: true,
        minimumInputLength: 2,
        ajax: {
            url: 'audit_trail.php',
            type: 'POST',
            dataType: 'json',
            delay: 300,
            data: function(params) {
                return {
                    action: 'search_users',
                    term: params.term,
                    csrf_token: csrfToken
                };
            },
            processResults: function(data) {
                return { results: data.results || [] };
            },
            cache: true
        }
    });

    // Load dynamic filter options (action types, entity types)
    loadFilterOptions();
});

function loadFilterOptions() {
    const formData = new FormData();
    formData.append('action', 'get_filters');
    formData.append('csrf_token', csrfToken);

    fetch('audit_trail.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            const actionSelect = document.getElementById('filterAction');
            data.action_types.forEach(a => {
                const opt = document.createElement('option');
                opt.value = a;
                opt.textContent = a.charAt(0).toUpperCase() + a.slice(1);
                actionSelect.appendChild(opt);
            });

            const entitySelect = document.getElementById('filterEntity');
            data.entity_types.forEach(e => {
                const opt = document.createElement('option');
                opt.value = e;
                opt.textContent = e.charAt(0).toUpperCase() + e.slice(1).replace(/_/g, ' ');
                entitySelect.appendChild(opt);
            });
        }
    });
}

function applyFilter(page) {
    currentPage = page || 1;
    showLoading('Loading audit trail...');

    const formData = new FormData();
    formData.append('action', 'filter');
    formData.append('csrf_token', csrfToken);
    formData.append('page', currentPage);

    const dateFrom = document.getElementById('filterDateFrom').value;
    const dateTo = document.getElementById('filterDateTo').value;
    const userId = $('#filterUser').val();
    const actionType = document.getElementById('filterAction').value;
    const entityType = document.getElementById('filterEntity').value;
    const search = document.getElementById('filterSearch').value;

    if (dateFrom)    formData.append('date_from', dateFrom);
    if (dateTo)      formData.append('date_to', dateTo);
    if (userId)      formData.append('user_id', userId);
    if (actionType)  formData.append('action_type', actionType);
    if (entityType)  formData.append('entity_type', entityType);
    if (search)      formData.append('search', search);

    fetch('audit_trail.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            currentData = data.rows;
            totalPages = data.total_pages;
            renderTable(data.rows);
            renderPagination(data.page, data.total_pages, data.total);
            updateStats(data.stats);
        } else {
            Notify.error(data.error || 'Failed to load data');
        }
    })
    .catch(err => {
        hideLoading();
        Notify.error('Error: ' + err.message);
    });
}

function resetFilter() {
    document.getElementById('filterDateFrom').value = '';
    document.getElementById('filterDateTo').value = '';
    $('#filterUser').val(null).trigger('change');
    document.getElementById('filterAction').value = '';
    document.getElementById('filterEntity').value = '';
    document.getElementById('filterSearch').value = '';

    currentData = [];
    currentPage = 1;
    document.getElementById('auditTableBody').innerHTML =
        '<tr><td colspan="8" class="text-center text-muted py-4">' +
        '<i class="fas fa-info-circle me-1"></i>Click "Apply Filter" to load audit trail data</td></tr>';
    document.getElementById('paginationNav').style.display = 'none';
    document.getElementById('recordCount').textContent = '0 records';
    updateStats({ total_events: 0, events_today: 0, unique_users_today: 0, most_active_entity: 'N/A' });
}

// Hidden fields to exclude from audit detail display
const hiddenAuditFields = ['id', 'hidden', 'created_at', 'updated_at', 'row_hash', 'csrf_token', 'action'];

function renderResolvedValue(obj) {
    if (!obj || typeof obj !== 'object') return '';
    let html = '<table class="table table-sm table-bordered mt-1 mb-0" style="font-size:0.8rem;">';
    html += '<thead><tr><th style="width:40%;">Field</th><th>Value</th></tr></thead><tbody>';
    for (const [key, value] of Object.entries(obj)) {
        if (hiddenAuditFields.includes(key)) continue;
        if (value === null || value === '') continue;
        const label = key.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
        let displayValue = value;
        if (typeof value === 'object') {
            displayValue = JSON.stringify(value, null, 2);
        }
        html += '<tr><td class="text-muted">' + escapeHtml(label) + '</td><td>' + escapeHtml(String(displayValue)) + '</td></tr>';
    }
    html += '</tbody></table>';
    return html;
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.appendChild(document.createTextNode(text));
    return div.innerHTML;
}

function getActionBadge(action) {
    const colors = {
        'create': 'success', 'update': 'warning', 'edit': 'warning',
        'delete': 'danger', 'login': 'info', 'logout': 'secondary',
        'view': 'primary', 'export': 'dark', 'security': 'danger'
    };
    const color = colors[action] || 'secondary';
    return '<span class="badge bg-' + color + '">' + escapeHtml(action) + '</span>';
}

function renderTable(rows) {
    const tbody = document.getElementById('auditTableBody');

    if (!rows || rows.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No records found</td></tr>';
        return;
    }

    let html = '';
    rows.forEach(row => {
        html += '<tr>' +
            '<td><small>' + escapeHtml(row.created_at) + '</small></td>' +
            '<td>' + escapeHtml(row.user_name || 'System') +
                (row.user_email ? '<br><small class="text-muted">' + escapeHtml(row.user_email) + '</small>' : '') + '</td>' +
            '<td>' + getActionBadge(row.action) + '</td>' +
            '<td><span class="text-capitalize">' + escapeHtml((row.entity_type || '').replace(/_/g, ' ')) + '</span></td>' +
            '<td>' + escapeHtml(row.entity_id || '-') + '</td>' +
            '<td><small>' + escapeHtml(row.description || '-') + '</small></td>' +
            '<td><small>' + escapeHtml(row.ip_address || '-') + '</small></td>' +
            '<td><button class="btn btn-outline-info btn-sm" data-action="viewDetail" data-arg0="' + row.id + '" title="View Details">' +
                '<i class="fas fa-eye"></i></button></td>' +
            '</tr>';
    });

    tbody.innerHTML = html;
}

function renderPagination(page, totalPgs, totalRecords) {
    const nav = document.getElementById('paginationNav');
    const list = document.getElementById('paginationList');
    document.getElementById('recordCount').textContent = totalRecords.toLocaleString() + ' record' + (totalRecords !== 1 ? 's' : '');

    if (totalPgs <= 1) {
        nav.style.display = 'none';
        return;
    }

    nav.style.display = 'block';
    let html = '';

    // Previous
    html += '<li class="page-item ' + (page <= 1 ? 'disabled' : '') + '">';
    html += '<a class="page-link" href="#" data-action="applyFilter" data-arg0="' + (page - 1) + '">Previous</a></li>';

    // Page numbers (max 7 centered around current)
    let startPage = Math.max(1, page - 3);
    let endPage = Math.min(totalPgs, startPage + 6);
    if (endPage - startPage < 6) startPage = Math.max(1, endPage - 6);

    if (startPage > 1) {
        html += '<li class="page-item"><a class="page-link" href="#" data-action="applyFilter" data-arg0="1">1</a></li>';
        if (startPage > 2) html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
    }

    for (let i = startPage; i <= endPage; i++) {
        html += '<li class="page-item ' + (i === page ? 'active' : '') + '">';
        html += '<a class="page-link" href="#" data-action="applyFilter" data-arg0="' + i + '">' + i + '</a></li>';
    }

    if (endPage < totalPgs) {
        if (endPage < totalPgs - 1) html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
        html += '<li class="page-item"><a class="page-link" href="#" data-action="applyFilter" data-arg0="' + totalPgs + '">' + totalPgs + '</a></li>';
    }

    // Next
    html += '<li class="page-item ' + (page >= totalPgs ? 'disabled' : '') + '">';
    html += '<a class="page-link" href="#" data-action="applyFilter" data-arg0="' + (page + 1) + '">Next</a></li>';

    list.innerHTML = html;
}

function updateStats(stats) {
    document.getElementById('statTotalEvents').textContent = (stats.total_events || 0).toLocaleString();
    document.getElementById('statEventsToday').textContent = (stats.events_today || 0).toLocaleString();
    document.getElementById('statUniqueUsers').textContent = (stats.unique_users_today || 0).toLocaleString();
    document.getElementById('statMostActive').textContent = (stats.most_active_entity || 'N/A').replace(/_/g, ' ');
}

function viewDetail(rowId) {
    showLoading('Loading details...');

    const formData = new FormData();
    formData.append('action', 'get_detail');
    formData.append('row_id', rowId);
    formData.append('csrf_token', csrfToken);

    fetch('audit_trail.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        hideLoading();
        if (data.success && data.row) {
            renderDetailModal(data.row, data.integrity);
            detailModal.show();
        } else {
            Notify.error(data.error || 'Failed to load detail');
        }
    })
    .catch(err => {
        hideLoading();
        Notify.error('Error: ' + err.message);
    });
}

function renderDetailModal(row, integrityValid) {
    let html = '<div class="row mb-3">';
    html += '<div class="col-md-6"><strong>Timestamp:</strong><br>' + escapeHtml(row.created_at) + '</div>';
    html += '<div class="col-md-6"><strong>User:</strong><br>' + escapeHtml(row.user_name || 'System') +
            (row.user_email ? ' (' + escapeHtml(row.user_email) + ')' : '') + '</div>';
    html += '</div>';

    html += '<div class="row mb-3">';
    html += '<div class="col-md-4"><strong>Action:</strong><br>' + getActionBadge(row.action) + '</div>';
    html += '<div class="col-md-4"><strong>Entity Type:</strong><br><span class="text-capitalize">' +
            escapeHtml((row.entity_type || '').replace(/_/g, ' ')) + '</span></div>';
    html += '<div class="col-md-4"><strong>Entity ID:</strong><br>' + escapeHtml(row.entity_id || '-') + '</div>';
    html += '</div>';

    html += '<div class="mb-3"><strong>Description:</strong><br>' + escapeHtml(row.description || 'No description') + '</div>';

    // Old Value vs New Value (use resolved versions for display)
    if (row.old_value || row.new_value) {
        html += '<div class="row mb-3">';
        if (row.old_value) {
            const colSize = row.new_value ? '6' : '12';
            html += '<div class="col-md-' + colSize + '"><strong>Old Value:</strong>';
            if (row.old_value_resolved && typeof row.old_value_resolved === 'object') {
                html += renderResolvedValue(row.old_value_resolved);
            } else {
                try {
                    html += '<pre class="bg-light p-2 rounded mt-1" style="max-height:300px;overflow:auto;font-size:0.8rem;">' +
                            escapeHtml(JSON.stringify(JSON.parse(row.old_value), null, 2)) + '</pre>';
                } catch(e) {
                    html += '<pre class="bg-light p-2 rounded mt-1" style="max-height:300px;overflow:auto;font-size:0.8rem;">' +
                            escapeHtml(row.old_value) + '</pre>';
                }
            }
            html += '</div>';
        }
        if (row.new_value) {
            const colSize = row.old_value ? '6' : '12';
            html += '<div class="col-md-' + colSize + '"><strong>New Value:</strong>';
            if (row.new_value_resolved && typeof row.new_value_resolved === 'object') {
                html += renderResolvedValue(row.new_value_resolved);
            } else {
                try {
                    html += '<pre class="bg-light p-2 rounded mt-1" style="max-height:300px;overflow:auto;font-size:0.8rem;">' +
                            escapeHtml(JSON.stringify(JSON.parse(row.new_value), null, 2)) + '</pre>';
                } catch(e) {
                    html += '<pre class="bg-light p-2 rounded mt-1" style="max-height:300px;overflow:auto;font-size:0.8rem;">' +
                            escapeHtml(row.new_value) + '</pre>';
                }
            }
            html += '</div>';
        }
        html += '</div>';
    }

    // Metadata
    html += '<hr>';
    html += '<div class="row">';
    html += '<div class="col-md-4"><strong>IP Address:</strong><br><small>' + escapeHtml(row.ip_address || '-') + '</small></div>';
    html += '<div class="col-md-4"><strong>Request:</strong><br><small>' + escapeHtml(row.request_method || '') + ' ' + escapeHtml(row.request_uri || '-') + '</small></div>';
    html += '<div class="col-md-4"><strong>User Agent:</strong><br><small class="text-break">' + escapeHtml(row.user_agent || '-') + '</small></div>';
    html += '</div>';

    document.getElementById('detailModalBody').innerHTML = html;

    // Integrity badge
    const badge = document.getElementById('integrityBadge');
    if (integrityValid) {
        badge.innerHTML = '<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Integrity Verified</span>';
    } else {
        badge.innerHTML = '<span class="badge bg-danger"><i class="fas fa-exclamation-triangle me-1"></i>Integrity Check Failed</span>';
    }
}

function exportExcel() {
    if (!currentData || currentData.length === 0) {
        Notify.warning('No data to export. Please apply a filter first.');
        return;
    }

    const wsData = [
        ['Timestamp', 'User', 'Email', 'Action', 'Entity Type', 'Entity ID', 'Description', 'IP Address']
    ];

    currentData.forEach(row => {
        wsData.push([
            row.created_at,
            row.user_name || 'System',
            row.user_email || '',
            row.action,
            row.entity_type,
            row.entity_id || '',
            row.description || '',
            row.ip_address || ''
        ]);
    });

    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.aoa_to_sheet(wsData);
    ws['!cols'] = [
        { wch: 20 }, { wch: 25 }, { wch: 30 }, { wch: 12 },
        { wch: 18 }, { wch: 15 }, { wch: 40 }, { wch: 16 }
    ];
    XLSX.utils.book_append_sheet(wb, ws, 'Audit Trail');
    XLSX.writeFile(wb, 'Audit_Trail_' + new Date().toISOString().slice(0, 10) + '.xlsx');
}

function exportPDF() {
    if (!currentData || currentData.length === 0) {
        Notify.warning('No data to export. Please apply a filter first.');
        return;
    }

    const printWindow = window.open('', '_blank');

    let tableHtml = '<table border="1" cellpadding="6" cellspacing="0" style="width:100%;border-collapse:collapse;font-size:11px;">';
    tableHtml += '<thead><tr style="background:#f0f0f0;font-weight:bold;">' +
        '<th>Timestamp</th><th>User</th><th>Action</th><th>Entity Type</th>' +
        '<th>Entity ID</th><th>Description</th><th>IP Address</th></tr></thead><tbody>';

    currentData.forEach(row => {
        tableHtml += '<tr>' +
            '<td>' + escapeHtml(row.created_at) + '</td>' +
            '<td>' + escapeHtml(row.user_name || 'System') + '</td>' +
            '<td>' + escapeHtml(row.action) + '</td>' +
            '<td>' + escapeHtml(row.entity_type) + '</td>' +
            '<td>' + escapeHtml(row.entity_id || '') + '</td>' +
            '<td>' + escapeHtml(row.description || '') + '</td>' +
            '<td>' + escapeHtml(row.ip_address || '') + '</td>' +
            '</tr>';
    });
    tableHtml += '</tbody></table>';

    const html = '<!DOCTYPE html><html><head><title>Audit Trail Report</title>' +
        '<style nonce="<?= csp_nonce() ?>">body{font-family:Arial,sans-serif;padding:20px;}h2{margin-bottom:5px;}.meta{color:#666;margin-bottom:15px;}@media print{.no-print{display:none;}}</style>' +
        '</head><body>' +
        '<h2>' + escapeHtml(auditSiteName) + ' - Audit Trail Report</h2>' +
        '<p class="meta">Generated: ' + new Date().toLocaleString() + ' | Records: ' + currentData.length + '</p>' +
        tableHtml +
        '<br><button id="__pw_btn" class="no-print" style="padding:8px 16px;cursor:pointer;">Print / Save as PDF</button>' +
        '</body></html>';

    printWindow.document.write(html);
    printWindow.document.close();
    printWindow.document.getElementById('__pw_btn').addEventListener('click', function() { printWindow.print(); });
}
</script>
<?php
$pageScripts = ob_get_clean();

include 'include/layout.php';
