<?php
/**
 * Inactive Records Page
 * View and restore subjects, rooms, sections, teachers, and schedules
 * that have been set to is_active = 0 (not deleted).
 */

require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/PermissionManager.php';
require_once __DIR__ . '/../core/SubjectManager.php';
require_once __DIR__ . '/../core/RoomManager.php';
require_once __DIR__ . '/../core/SectionManager.php';
require_once __DIR__ . '/../core/TeacherManager.php';
require_once __DIR__ . '/../core/ScheduleManager.php';

$config = require __DIR__ . '/../config/database.php';
$db = new DB($config['host'], $config['user'], $config['pass'], $config['name'], $config['port']);

$session           = Session::getInstance($db);
$permissionManager = PermissionManager::getInstance($db);
$subjectManager    = SubjectManager::getInstance($db);
$roomManager       = RoomManager::getInstance($db);
$sectionManager    = SectionManager::getInstance($db);
$teacherManager    = TeacherManager::getInstance($db);
$scheduleManager   = ScheduleManager::getInstance($db);

$session->requireLogin();
$userRoleId = $session->get('role_id', 1);

$isAjax = $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']);

if (!$permissionManager->hasPermission($userRoleId, 'inactive_records', 'view')) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Permission denied']);
        exit;
    }
    http_response_code(403);
    include __DIR__ . '/errors/403.php';
    exit;
}

if ($isAjax) {
    header('Content-Type: application/json');

    if (!$session->verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }

    $action = $_POST['action'];

    switch ($action) {
        case 'get_inactive':
            $type = $_POST['type'] ?? '';
            switch ($type) {
                case 'subject':
                    $result = $subjectManager->getInactiveSubjects();
                    break;
                case 'room':
                    $result = $roomManager->getInactiveRooms();
                    break;
                case 'section':
                    $result = $sectionManager->getInactiveSections();
                    break;
                case 'teacher':
                    $result = $teacherManager->getInactiveTeachers();
                    break;
                case 'schedule':
                    $result = $scheduleManager->getInactiveSchedules();
                    break;
                default:
                    echo json_encode(['success' => false, 'error' => 'Unknown type']);
                    exit;
            }
            echo json_encode([
                'success' => $result['success'] ?? false,
                'rows'    => $result['result'] ?? [],
            ]);
            exit;

        case 'reactivate':
            $type = $_POST['type'] ?? '';
            $id   = (int)($_POST['id'] ?? 0);

            if ($id <= 0) {
                echo json_encode(['success' => false, 'error' => 'Invalid ID']);
                exit;
            }

            // Map type → required page permission + update method
            $typeMap = [
                'subject'  => ['page' => 'settings_subjects',  'manager' => $subjectManager,  'method' => 'updateSubject'],
                'room'     => ['page' => 'settings_rooms',     'manager' => $roomManager,     'method' => 'updateRoom'],
                'section'  => ['page' => 'settings_sections',  'manager' => $sectionManager,  'method' => 'updateSection'],
                'teacher'  => ['page' => 'teacher_management', 'manager' => $teacherManager,  'method' => 'updateTeacher'],
                'schedule' => ['page' => 'schedule_calendar',  'manager' => $scheduleManager, 'method' => 'reactivateSchedule'],
            ];

            if (!isset($typeMap[$type])) {
                echo json_encode(['success' => false, 'error' => 'Unknown type']);
                exit;
            }

            $cfg = $typeMap[$type];
            if (!$permissionManager->hasPermission($userRoleId, $cfg['page'], 'edit')) {
                echo json_encode(['success' => false, 'error' => 'Permission denied']);
                exit;
            }

            $old    = $cfg['manager']->getById($id);
            $result = $cfg['manager']->{$cfg['method']}($id, ['is_active' => 1]);

            if ($result['success'] && $old) {
                $new = $cfg['manager']->getById($id);
                $permissionManager->logPermissionChange(
                    $session->getUserId(), 'edit', $type, $id, $old, $new
                );
            }

            echo json_encode($result);
            exit;
    }

    echo json_encode(['success' => false, 'error' => 'Unknown action']);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// Page render
// ─────────────────────────────────────────────────────────────────────────────
require_once 'include/rbac_init.php';

$pageTitle   = 'Inactive Records';
$breadcrumbs = [
    ['title' => 'Dashboard',        'url' => 'index.php'],
    ['title' => 'Reports',          'url' => '#'],
    ['title' => 'Inactive Records', 'url' => ''],
];

ob_start();
?>

<!-- Tab Navigation -->
<ul class="nav nav-tabs mb-3" id="inactiveTabs" role="tablist">
    <?php
    $tabs = [
        ['key' => 'subject',  'label' => 'Subjects',  'icon' => 'fas fa-book'],
        ['key' => 'room',     'label' => 'Rooms',     'icon' => 'fas fa-door-open'],
        ['key' => 'section',  'label' => 'Sections',  'icon' => 'fas fa-layer-group'],
        ['key' => 'teacher',  'label' => 'Teachers',  'icon' => 'fas fa-chalkboard-teacher'],
        ['key' => 'schedule', 'label' => 'Schedules', 'icon' => 'fas fa-calendar-alt'],
    ];
    foreach ($tabs as $i => $tab):
    ?>
    <li class="nav-item" role="presentation">
        <button class="nav-link <?= $i === 0 ? 'active' : '' ?>"
                id="tab-<?= $tab['key'] ?>-btn"
                data-bs-toggle="tab"
                data-bs-target="#tab-<?= $tab['key'] ?>"
                type="button" role="tab"
                data-action="loadInactiveTab"
                data-arg0="<?= $tab['key'] ?>">
            <i class="<?= $tab['icon'] ?> me-1"></i><?= $tab['label'] ?>
        </button>
    </li>
    <?php endforeach; ?>
</ul>

<div class="tab-content">
    <?php foreach ($tabs as $i => $tab): ?>
    <div class="tab-pane fade <?= $i === 0 ? 'show active' : '' ?>"
         id="tab-<?= $tab['key'] ?>" role="tabpanel">
        <div class="card">
            <div class="card-header">
                <i class="<?= $tab['icon'] ?> me-1"></i>
                Inactive <?= $tab['label'] ?>
            </div>
            <div class="card-body p-0">
                <div id="table-<?= $tab['key'] ?>" class="table-responsive">
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-spinner fa-spin fa-2x mb-3"></i>
                        <p>Loading...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php
$pageContent = ob_get_clean();

ob_start();
?>
<script nonce="<?= csp_nonce() ?>">
const csrfToken = '<?= $session->getCSRFToken() ?>';
const dayNames  = <?= json_encode(ScheduleManager::getDayNames()) ?>;

const canReactivate = {
    subject:  <?= hasPagePermission('settings_subjects',  'edit') ? 'true' : 'false' ?>,
    room:     <?= hasPagePermission('settings_rooms',     'edit') ? 'true' : 'false' ?>,
    section:  <?= hasPagePermission('settings_sections',  'edit') ? 'true' : 'false' ?>,
    teacher:  <?= hasPagePermission('teacher_management', 'edit') ? 'true' : 'false' ?>,
    schedule: <?= hasPagePermission('schedule_calendar',  'edit') ? 'true' : 'false' ?>,
};

// ─────────────────────────────────────────────
// Tab loading
// ─────────────────────────────────────────────
function loadInactiveTab(type) {
    const container = document.getElementById('table-' + type);
    if (!container) return;

    container.innerHTML = '<div class="text-center text-muted py-5"><i class="fas fa-spinner fa-spin fa-2x mb-3"></i><p>Loading...</p></div>';

    const fd = new FormData();
    fd.append('action',     'get_inactive');
    fd.append('csrf_token', csrfToken);
    fd.append('type',       type);

    fetch(window.location.href, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (!data.success) { container.innerHTML = '<div class="alert alert-danger m-3">' + escHtml(data.error || 'Failed to load') + '</div>'; return; }
            renderInactiveTable(type, data.rows, container);
        })
        .catch(err => { container.innerHTML = '<div class="alert alert-danger m-3">' + escHtml(err.message) + '</div>'; });
}

function renderInactiveTable(type, rows, container) {
    if (!rows || rows.length === 0) {
        container.innerHTML = '<div class="text-center text-muted py-5"><i class="fas fa-check-circle fa-2x mb-3 text-success"></i><p>No inactive records found.</p></div>';
        return;
    }

    let html = '<table class="table table-hover table-sm mb-0">';

    if (type === 'subject') {
        html += '<thead class="table-light"><tr><th>Code</th><th>Name</th><th>Credits</th><th>Description</th><th class="text-end">Action</th></tr></thead><tbody>';
        rows.forEach(r => {
            html += `<tr>
                <td><code>${escHtml(r.code)}</code></td>
                <td>${escHtml(r.name)}</td>
                <td>${r.credits !== null && r.credits !== '' ? escHtml(r.credits) : '<span class="text-muted">—</span>'}</td>
                <td class="text-muted small">${escHtml(r.description || '')}</td>
                <td class="text-end">${reactivateBtn(type, r.id)}</td>
            </tr>`;
        });
    } else if (type === 'room') {
        html += '<thead class="table-light"><tr><th>Code</th><th>Name</th><th>Capacity</th><th class="text-end">Action</th></tr></thead><tbody>';
        rows.forEach(r => {
            html += `<tr>
                <td><code>${escHtml(r.code)}</code></td>
                <td>${escHtml(r.name)}</td>
                <td>${r.capacity !== null && r.capacity !== '' ? escHtml(r.capacity) + ' seats' : '<span class="text-muted">—</span>'}</td>
                <td class="text-end">${reactivateBtn(type, r.id)}</td>
            </tr>`;
        });
    } else if (type === 'section') {
        html += '<thead class="table-light"><tr><th>Name</th><th>Description</th><th class="text-end">Action</th></tr></thead><tbody>';
        rows.forEach(r => {
            html += `<tr>
                <td>${escHtml(r.name)}</td>
                <td class="text-muted small">${escHtml(r.description || '')}</td>
                <td class="text-end">${reactivateBtn(type, r.id)}</td>
            </tr>`;
        });
    } else if (type === 'teacher') {
        html += '<thead class="table-light"><tr><th>Employee ID</th><th>Full Name</th><th>Email</th><th>Phone</th><th class="text-end">Action</th></tr></thead><tbody>';
        rows.forEach(r => {
            html += `<tr>
                <td class="text-muted small">${escHtml(r.employee_id || '—')}</td>
                <td>${escHtml(r.first_name + ' ' + r.last_name)}</td>
                <td class="text-muted small">${escHtml(r.email || '—')}</td>
                <td class="text-muted small">${escHtml(r.phone || '—')}</td>
                <td class="text-end">${reactivateBtn(type, r.id)}</td>
            </tr>`;
        });
    } else if (type === 'schedule') {
        html += '<thead class="table-light"><tr><th>Day</th><th>Time</th><th>Subject</th><th>Room</th><th>Section</th><th>Teacher</th><th>Effective</th><th class="text-end">Action</th></tr></thead><tbody>';
        rows.forEach(r => {
            const day   = dayNames[parseInt(r.day_of_week)] || '—';
            const start = r.start_time ? r.start_time.substring(0, 5) : '—';
            const end   = r.end_time   ? r.end_time.substring(0, 5)   : '—';
            html += `<tr>
                <td>${escHtml(day)}</td>
                <td class="text-nowrap">${escHtml(start)}–${escHtml(end)}</td>
                <td><code>${escHtml(r.subject_code)}</code> ${escHtml(r.subject_name)}</td>
                <td>${escHtml(r.room_name)}</td>
                <td>${escHtml(r.section_name)}</td>
                <td>${escHtml(r.teacher_name)}</td>
                <td class="text-muted small text-nowrap">${escHtml(r.effective_start)} → ${escHtml(r.effective_end)}</td>
                <td class="text-end">${reactivateBtn(type, r.id)}</td>
            </tr>`;
        });
    }

    html += '</tbody></table>';
    container.innerHTML = html;
}

function reactivateBtn(type, id) {
    if (!canReactivate[type]) return '';
    return `<button class="btn btn-success btn-sm"
                data-action="reactivate"
                data-arg0="${escHtml(type)}"
                data-arg1="${parseInt(id)}">
        <i class="fas fa-redo-alt me-1"></i>Reactivate
    </button>`;
}

// ─────────────────────────────────────────────
// Reactivate
// ─────────────────────────────────────────────
function reactivate(type, id) {
    Notify.confirm({
        title:       'Reactivate ' + type.charAt(0).toUpperCase() + type.slice(1),
        text:        'This record will become active and visible in the system again.',
        confirmText: 'Reactivate',
    }).then(function (result) {
        if (!result.isConfirmed) return;

        const fd = new FormData();
        fd.append('action',     'reactivate');
        fd.append('csrf_token', csrfToken);
        fd.append('type',       type);
        fd.append('id',         id);

        fetch(window.location.href, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    Notify.actionSuccess('reactivated', type.charAt(0).toUpperCase() + type.slice(1));
                    loadInactiveTab(type);
                } else {
                    Notify.error(data.error || 'Failed to reactivate');
                }
            })
            .catch(err => Notify.error('Error: ' + err.message));
    });
}

// ─────────────────────────────────────────────
// Init — load first tab
// ─────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    loadInactiveTab('subject');
});

// ─────────────────────────────────────────────
// Utility
// ─────────────────────────────────────────────
function escHtml(str) {
    if (str == null) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}
</script>
<?php
$pageScripts = ob_get_clean();

include 'include/layout.php';
