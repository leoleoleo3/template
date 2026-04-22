<?php
/**
 * Mailer Management
 * SMTP account CRUD, mail queue, and delivery logs.
 */

require_once __DIR__ . '/../core/bootstrap.php';
require_once 'include/rbac_init.php';

$accountManager = MailerAccountManager::getInstance($db);
$queueManager   = MailQueueManager::getInstance($db);

// ── Guard ─────────────────────────────────────────────────────────────────────
$isAjax = $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']);
if ($isAjax) {
    startAjax(fn() => hasPagePermission('mailer_management', 'view'));
} else {
    requirePagePermission('mailer_management', 'view');
}

// ── AJAX handler ───────────────────────────────────────────────────────────────
if ($isAjax) {
    switch ($_POST['action']) {

        // ─── Account CRUD ───────────────────────────────────────────────────
        case 'create_account':
            requirePagePermission('mailer_management', 'create');
            $data   = acctData();
            $result = $accountManager->createAccount($data);
            if ($result['success']) {
                $auditManager->log('create', 'mailer_account', $data['name'], null, $data, "Created SMTP account: {$data['name']}");
            }
            echo json_encode($result);
            exit;

        case 'update_account':
            requirePagePermission('mailer_management', 'edit');
            $id  = (int) $_POST['id'];
            $old = $accountManager->getAccountById($id);
            // Don't overwrite password if field is empty
            $data = acctData();
            if (empty($data['smtp_password'])) unset($data['smtp_password']);
            $result = $accountManager->updateAccount($id, $data);
            if ($result['success']) {
                $auditManager->log('edit', 'mailer_account', $data['name'], $old, $data, "Updated SMTP account: {$data['name']}");
            }
            echo json_encode($result);
            exit;

        case 'delete_account':
            requirePagePermission('mailer_management', 'delete');
            $id  = (int) $_POST['id'];
            $old = $accountManager->getAccountById($id);
            $result = $accountManager->deleteAccount($id);
            if ($result['success'] && $old) {
                $auditManager->log('delete', 'mailer_account', $old['name'], $old, null, "Deleted SMTP account: {$old['name']}");
            }
            echo json_encode($result);
            exit;

        case 'test_account':
            requirePagePermission('mailer_management', 'edit');
            echo json_encode($accountManager->testConnection((int) $_POST['id']));
            exit;

        case 'send_test_email':
            requirePagePermission('mailer_management', 'create');
            $email = trim($_POST['test_email'] ?? '');
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['success' => false, 'error' => 'Invalid email address']);
                exit;
            }
            echo json_encode($queueManager->sendNow([
                'to_email'   => $email,
                'to_name'    => 'Test Recipient',
                'subject'    => 'Mailer Test Email',
                'body_html'  => '<div style="font-family:Arial,sans-serif;padding:20px"><h2 style="color:#0d6efd">Mailer Test</h2><p>If you received this, the email configuration is working correctly.</p><hr style="border:1px solid #eee"><small style="color:#999">Sent at: ' . date('Y-m-d H:i:s') . '</small></div>',
                'priority'   => 1,
                'context'    => ['type' => 'test'],
                'created_by' => $session->getUserId(),
            ]));
            exit;

        // ─── Queue ──────────────────────────────────────────────────────────
        case 'get_queue':
            echo json_encode($queueManager->getQueueItems([
                'status'    => $_POST['status']    ?? '',
                'search'    => $_POST['search']    ?? '',
                'date_from' => $_POST['date_from'] ?? '',
                'date_to'   => $_POST['date_to']   ?? '',
            ], max(1, (int) ($_POST['page'] ?? 1)), 15));
            exit;

        case 'cancel_queue':
            requirePagePermission('mailer_management', 'edit');
            echo json_encode($queueManager->cancelQueueItem((int) $_POST['id']));
            exit;

        case 'retry_queue':
            requirePagePermission('mailer_management', 'edit');
            echo json_encode($queueManager->retryQueueItem((int) $_POST['id']));
            exit;

        case 'reprocess_queue':
            requirePagePermission('mailer_management', 'edit');
            $batch = max(1, min(100, (int) ($_POST['batch_size'] ?? 10)));
            $queueManager->cleanupStaleJobs(30);
            $res = $queueManager->processQueue($batch, 'web_' . $session->getUserId() . '_' . time());
            echo json_encode(['success' => true, 'message' => "Processed: {$res['sent']} sent, {$res['failed']} failed, {$res['skipped']} skipped", 'data' => $res]);
            exit;

        // ─── Logs ───────────────────────────────────────────────────────────
        case 'get_logs':
            echo json_encode($queueManager->getLogs([
                'status'     => $_POST['status']     ?? '',
                'account_id' => $_POST['account_id'] ?? '',
                'search'     => $_POST['search']     ?? '',
                'date_from'  => $_POST['date_from']  ?? '',
                'date_to'    => $_POST['date_to']    ?? '',
            ], max(1, (int) ($_POST['page'] ?? 1)), 15));
            exit;

        case 'get_stats':
            echo json_encode([
                'success'  => true,
                'accounts' => $accountManager->getAccountStats(),
                'queue'    => $queueManager->getQueueStats(),
                'logs'     => $queueManager->getLogStats(),
            ]);
            exit;

        // ─── Report Recipients ───────────────────────────────────────────────
        case 'get_report_recipients':
            $key = $_POST['setting_key'] ?? '';
            if (!in_array($key, ['backup_email_recipients', 'receipt_report_recipients'], true)) {
                echo json_encode(['success' => false, 'error' => 'Invalid setting key']); exit;
            }
            $r = $settingsManager->get($key, '[]');
            echo json_encode(['success' => true, 'recipients' => json_decode(is_string($r) ? $r : '[]', true) ?? []]);
            exit;

        case 'save_report_recipients':
            requirePagePermission('mailer_management', 'edit');
            $key = $_POST['setting_key'] ?? '';
            $map = [
                'backup_email_recipients'  => ['group' => 'backup',  'label' => 'Backup Email Recipients',   'desc' => 'Email addresses that receive database backup files'],
                'receipt_report_recipients'=> ['group' => 'reports', 'label' => 'Receipt Report Recipients', 'desc' => 'Email addresses that receive the daily receipt report'],
            ];
            if (!isset($map[$key])) { echo json_encode(['success' => false, 'error' => 'Invalid setting key']); exit; }
            $emails  = [];
            $invalid = [];
            foreach (preg_split('/[\n,;]+/', trim($_POST['emails'] ?? '')) as $part) {
                $e = trim($part);
                if ($e === '') continue;
                filter_var($e, FILTER_VALIDATE_EMAIL) ? ($emails[] = strtolower($e)) : ($invalid[] = $e);
            }
            if ($invalid) { echo json_encode(['success' => false, 'error' => 'Invalid email(s): ' . implode(', ', $invalid)]); exit; }
            $emails   = array_values(array_unique($emails));
            $meta     = $map[$key];
            $oldR     = json_decode($settingsManager->get($key, '[]') ?: '[]', true) ?? [];
            $result   = $settingsManager->set($key, json_encode($emails), 'json', $meta['group'], $meta['label'], $meta['desc']);
            if ($result['success']) {
                $auditManager->log('edit', 'mailer_recipients', $meta['label'], ['recipients' => $oldR], ['recipients' => $emails], "Updated {$meta['label']}: " . count($emails) . " recipient(s)");
            }
            echo json_encode(['success' => $result['success'], 'count' => count($emails)]);
            exit;

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            exit;
    }
}

// ── Helpers ────────────────────────────────────────────────────────────────────
function acctData(): array {
    return [
        'name'             => $_POST['name']             ?? '',
        'smtp_host'        => $_POST['smtp_host']        ?? '',
        'smtp_port'        => (int) ($_POST['smtp_port'] ?? 587),
        'smtp_encryption'  => $_POST['smtp_encryption']  ?? 'tls',
        'smtp_username'    => $_POST['smtp_username']    ?? '',
        'smtp_password'    => $_POST['smtp_password']    ?? '',
        'from_email'       => $_POST['from_email']       ?? '',
        'from_name'        => $_POST['from_name']        ?? '',
        'reply_to_email'   => $_POST['reply_to_email']   ?? '',
        'reply_to_name'    => $_POST['reply_to_name']    ?? '',
        'daily_limit'      => (int) ($_POST['daily_limit']  ?? 500),
        'hourly_limit'     => (int) ($_POST['hourly_limit'] ?? 50),
        'throttle_ms'      => (int) ($_POST['throttle_ms']  ?? 1000),
        'priority'         => (int) ($_POST['priority']     ?? 10),
        'is_active'        => isset($_POST['is_active']) ? 1 : 0,
    ];
}

// ── View ───────────────────────────────────────────────────────────────────────
$accounts        = $accountManager->getAllAccounts();
$accountStats    = $accountManager->getAccountStats();
$queueStats      = $queueManager->getQueueStats();
$logStats        = $queueManager->getLogStats();
$accountsDropdown = $accountManager->getAccountsForDropdown();

$pageTitle   = 'Mailer Management';
$breadcrumbs = [
    ['title' => 'Dashboard',         'url' => 'index.php'],
    ['title' => 'System',            'url' => '#'],
    ['title' => 'Mailer Management', 'url' => ''],
];

ob_start();
?>

<!-- Statistics -->
<div class="row mb-4">
    <?= statCard('SMTP Accounts', $accountStats['active_accounts'], 'primary', 'fas fa-server') ?>
    <?= statCard('Sent Today',    $logStats['sent_today'],          'success', 'fas fa-paper-plane') ?>
    <?= statCard('Failed Today',  $logStats['failed_today'],        'danger',  'fas fa-exclamation-triangle') ?>
    <?= statCard('In Queue',      $queueStats['queued'] + $queueStats['processing'], 'info', 'fas fa-inbox') ?>
</div>

<!-- SMTP Accounts -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-server me-1"></i> SMTP Provider Accounts</span>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-info btn-sm" data-action="openQueueModal">
                <i class="fas fa-inbox me-1"></i> Mail Queue
                <?php if ($queueStats['queued'] > 0): ?>
                <span class="badge bg-warning text-dark ms-1"><?= $queueStats['queued'] ?></span>
                <?php endif; ?>
            </button>
            <button class="btn btn-outline-secondary btn-sm" data-action="openLogsModal">
                <i class="fas fa-history me-1"></i> Delivery Logs
            </button>
            <?php if (hasPagePermission('mailer_management', 'edit')): ?>
            <button class="btn btn-outline-warning btn-sm" data-action="openRecipientsModal"
                    data-arg0="backup_email_recipients"
                    data-arg1="Backup Email Recipients"
                    data-arg2="These addresses receive the database backup file when scheduled backups run.">
                <i class="fas fa-database me-1"></i> Backup Email
            </button>
            <button class="btn btn-outline-success btn-sm" data-action="openRecipientsModal"
                    data-arg0="receipt_report_recipients"
                    data-arg1="Receipt Report Recipients"
                    data-arg2="These addresses receive the daily receipt report when the scheduled task runs.">
                <i class="fas fa-receipt me-1"></i> Receipt Report
            </button>
            <?php endif; ?>
            <?php if (hasPagePermission('mailer_management', 'create')): ?>
            <button class="btn btn-success btn-sm" data-action="openTestEmailModal">
                <i class="fas fa-paper-plane me-1"></i> Send Test
            </button>
            <button class="btn btn-primary btn-sm" data-action="openAddAccountModal">
                <i class="fas fa-plus me-1"></i> Add Account
            </button>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover" id="accountsTable">
                <thead>
                    <tr><th>Name</th><th>SMTP Host</th><th>From</th><th>Daily</th><th>Hourly</th><th>Priority</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php if ($accounts['success'] && !empty($accounts['result'])): ?>
                        <?php foreach ($accounts['result'] as $acct): ?>
                        <tr>
                            <td><strong><?= e($acct['name']) ?></strong></td>
                            <td><?= e($acct['smtp_host']) ?>:<?= $acct['smtp_port'] ?> <span class="badge bg-secondary"><?= strtoupper($acct['smtp_encryption']) ?></span></td>
                            <td><small><?= e($acct['from_name']) ?></small><br><small class="text-muted"><?= e($acct['from_email']) ?></small></td>
                            <td><span class="<?= $acct['sent_today'] >= $acct['daily_limit'] ? 'text-danger fw-bold' : '' ?>"><?= $acct['sent_today'] ?>/<?= $acct['daily_limit'] ?></span></td>
                            <td><span class="<?= $acct['sent_this_hour'] >= $acct['hourly_limit'] ? 'text-danger fw-bold' : '' ?>"><?= $acct['sent_this_hour'] ?>/<?= $acct['hourly_limit'] ?></span></td>
                            <td><span class="badge bg-primary"><?= $acct['priority'] ?></span></td>
                            <td><?= $acct['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?></td>
                            <td>
                                <?php if (hasPagePermission('mailer_management', 'edit')): ?>
                                <button class="btn btn-sm btn-outline-success" data-action="testAccount" data-arg0="<?= $acct['id'] ?>" title="Test"><i class="fas fa-plug"></i></button>
                                <button class="btn btn-sm btn-primary" data-action="editAccount" data-arg-json="<?= e(json_encode($acct)) ?>" title="Edit"><i class="fas fa-edit"></i></button>
                                <?php endif; ?>
                                <?php if (hasPagePermission('mailer_management', 'delete')): ?>
                                <button class="btn btn-sm btn-danger" data-action="deleteAccount" data-arg0="<?= $acct['id'] ?>" data-arg1="<?= e($acct['name']) ?>" title="Delete"><i class="fas fa-trash"></i></button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="8" class="text-center text-muted py-4"><i class="fas fa-server fa-2x mb-2 d-block"></i>No SMTP accounts configured.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ═══ Mail Queue Modal ═══ -->
<div class="modal fade" id="queueModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-inbox me-2"></i> Mail Queue</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <?php if (hasPagePermission('mailer_management', 'edit')): ?>
                <div class="card bg-light mb-3"><div class="card-body py-2">
                    <div class="d-flex align-items-center gap-2">
                        <strong class="text-nowrap"><i class="fas fa-sync-alt me-1"></i> Batch Reprocess:</strong>
                        <input type="number" class="form-control form-control-sm" id="reprocessBatchSize" value="10" min="1" max="100" style="width:80px">
                        <button class="btn btn-primary btn-sm text-nowrap" id="reprocessBtn" data-action="reprocessQueue"><i class="fas fa-play"></i> Process Now</button>
                        <small class="text-muted ms-2">Process unsent queue items now</small>
                    </div>
                </div></div>
                <?php endif; ?>
                <div class="row mb-3 g-2">
                    <div class="col-md-3"><select class="form-select form-select-sm" id="queueStatusFilter"><option value="">All Statuses</option><option value="queued">Queued</option><option value="processing">Processing</option><option value="sent">Sent</option><option value="failed">Failed</option><option value="cancelled">Cancelled</option></select></div>
                    <div class="col-md-3"><input type="text" class="form-control form-control-sm" id="queueSearchFilter" placeholder="Search email or subject..."></div>
                    <div class="col-md-2"><input type="date" class="form-control form-control-sm" id="queueDateFrom"></div>
                    <div class="col-md-2"><input type="date" class="form-control form-control-sm" id="queueDateTo"></div>
                    <div class="col-md-2"><button class="btn btn-sm btn-primary w-100" data-action="loadQueue"><i class="fas fa-search"></i> Filter</button></div>
                </div>
                <div class="table-responsive">
                    <table class="table table-striped table-hover table-sm">
                        <thead><tr><th>ID</th><th>To</th><th>Subject</th><th>Status</th><th>Attempts</th><th>Priority</th><th>Created</th><th>Actions</th></tr></thead>
                        <tbody id="queueTableBody"><tr><td colspan="8" class="text-center text-muted py-4"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr></tbody>
                    </table>
                </div>
                <div id="queuePagination" class="d-flex justify-content-between align-items-center">
                    <small class="text-muted" id="queuePaginationInfo"></small>
                    <nav><ul class="pagination pagination-sm mb-0" id="queuePaginationNav"></ul></nav>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══ Delivery Logs Modal ═══ -->
<div class="modal fade" id="logsModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-history me-2"></i> Delivery Logs</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3 g-2">
                    <div class="col-md-2"><select class="form-select form-select-sm" id="logStatusFilter"><option value="">All Statuses</option><option value="sent">Sent</option><option value="failed">Failed</option></select></div>
                    <div class="col-md-2"><select class="form-select form-select-sm" id="logAccountFilter"><option value="">All Accounts</option><?php foreach ($accountsDropdown as $opt): ?><option value="<?= $opt['id'] ?>"><?= e($opt['name']) ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-2"><input type="text" class="form-control form-control-sm" id="logSearchFilter" placeholder="Search..."></div>
                    <div class="col-md-2"><input type="date" class="form-control form-control-sm" id="logDateFrom"></div>
                    <div class="col-md-2"><input type="date" class="form-control form-control-sm" id="logDateTo"></div>
                    <div class="col-md-2"><button class="btn btn-sm btn-primary w-100" data-action="loadLogs"><i class="fas fa-search"></i> Filter</button></div>
                </div>
                <div class="table-responsive">
                    <table class="table table-striped table-hover table-sm">
                        <thead><tr><th>ID</th><th>To</th><th>Subject</th><th>Account</th><th>Status</th><th>Duration</th><th>Sent At</th><th>Actions</th></tr></thead>
                        <tbody id="logsTableBody"><tr><td colspan="8" class="text-center text-muted py-4"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr></tbody>
                    </table>
                </div>
                <div id="logsPagination" class="d-flex justify-content-between align-items-center">
                    <small class="text-muted" id="logsPaginationInfo"></small>
                    <nav><ul class="pagination pagination-sm mb-0" id="logsPaginationNav"></ul></nav>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══ Email Recipients Modal ═══ -->
<div class="modal fade" id="recipientsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="recipientsModalTitle"><i class="fas fa-envelope me-2"></i> Email Recipients</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-2" id="recipientsModalDesc">Enter one email per line.</p>
                <textarea class="form-control" id="recipientsList" rows="5" placeholder="admin@example.com&#10;it@example.com"></textarea>
                <div id="recipientsStatus" class="mt-2"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveRecipientsBtn" data-action="saveRecipients"><i class="fas fa-save"></i> Save Recipients</button>
            </div>
        </div>
    </div>
</div>

<!-- ═══ SMTP Account Modal ═══ -->
<div class="modal fade" id="accountModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="accountModalLabel">Add SMTP Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="accountForm">
                <input type="hidden" name="csrf_token" value="<?= $session->getCSRFToken() ?>">
                <input type="hidden" name="action" id="accountFormAction" value="create_account">
                <input type="hidden" name="id" id="accountId">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Account Name <span class="text-danger">*</span></label><input type="text" class="form-control" name="name" id="acctName" required placeholder="e.g., Gmail Primary"></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Priority</label><input type="number" class="form-control" name="priority" id="acctPriority" value="10" min="1" max="99"><small class="text-muted">Lower = higher priority</small></div>
                    </div>
                    <hr class="my-2"><h6 class="text-muted mb-3">SMTP Configuration</h6>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">SMTP Host <span class="text-danger">*</span></label><input type="text" class="form-control" name="smtp_host" id="acctSmtpHost" required placeholder="smtp.gmail.com"></div>
                        <div class="col-md-3 mb-3"><label class="form-label">Port <span class="text-danger">*</span></label><input type="number" class="form-control" name="smtp_port" id="acctSmtpPort" value="587" required></div>
                        <div class="col-md-3 mb-3"><label class="form-label">Encryption</label><select class="form-select" name="smtp_encryption" id="acctSmtpEncryption"><option value="tls">TLS (587)</option><option value="ssl">SSL (465)</option><option value="none">None</option></select></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Username <span class="text-danger">*</span></label><input type="text" class="form-control" name="smtp_username" id="acctSmtpUser" required></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Password <span class="text-danger">*</span></label><input type="password" class="form-control" name="smtp_password" id="acctSmtpPass" required></div>
                    </div>
                    <hr class="my-2"><h6 class="text-muted mb-3">Sender Information</h6>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">From Email <span class="text-danger">*</span></label><input type="email" class="form-control" name="from_email" id="acctFromEmail" required placeholder="noreply@example.com"></div>
                        <div class="col-md-6 mb-3"><label class="form-label">From Name <span class="text-danger">*</span></label><input type="text" class="form-control" name="from_name" id="acctFromName" required placeholder="My App"></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Reply-To Email</label><input type="email" class="form-control" name="reply_to_email" id="acctReplyEmail" placeholder="Optional"></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Reply-To Name</label><input type="text" class="form-control" name="reply_to_name" id="acctReplyName" placeholder="Optional"></div>
                    </div>
                    <hr class="my-2"><h6 class="text-muted mb-3">Rate Limits</h6>
                    <div class="row">
                        <div class="col-md-4 mb-3"><label class="form-label">Daily Limit</label><input type="number" class="form-control" name="daily_limit" id="acctDailyLimit" value="500" min="1"></div>
                        <div class="col-md-4 mb-3"><label class="form-label">Hourly Limit</label><input type="number" class="form-control" name="hourly_limit" id="acctHourlyLimit" value="50" min="1"></div>
                        <div class="col-md-4 mb-3"><label class="form-label">Throttle (ms)</label><input type="number" class="form-control" name="throttle_ms" id="acctThrottle" value="1000" min="0"><small class="text-muted">Delay between sends</small></div>
                    </div>
                    <div class="mb-3"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_active" id="acctIsActive" value="1" checked><label class="form-check-label" for="acctIsActive">Account Active</label></div></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ═══ Test Email Modal ═══ -->
<div class="modal fade" id="testEmailModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Send Test Email</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="testEmailForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Recipient Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="testEmailAddress" required placeholder="test@example.com">
                        <small class="text-muted">Sent via the highest-priority active account.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-paper-plane"></i> Send</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ═══ Queue Detail Modal ═══ -->
<div class="modal fade" id="queueDetailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Queue Item Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body" id="queueDetailContent"></div>
        </div>
    </div>
</div>

<!-- ═══ Log Detail Modal ═══ -->
<div class="modal fade" id="logDetailModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Delivery Log Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body" id="logDetailContent"></div>
        </div>
    </div>
</div>

<?php
$pageContent = ob_get_clean();

ob_start();
?>
<script nonce="<?= csp_nonce() ?>">
const csrfToken       = '<?= $session->getCSRFToken() ?>';
const accountModal    = new bootstrap.Modal(document.getElementById('accountModal'));
const testEmailModal  = new bootstrap.Modal(document.getElementById('testEmailModal'));
const queueModal      = new bootstrap.Modal(document.getElementById('queueModal'));
const logsModal       = new bootstrap.Modal(document.getElementById('logsModal'));
const queueDetailModal = new bootstrap.Modal(document.getElementById('queueDetailModal'));
const logDetailModal  = new bootstrap.Modal(document.getElementById('logDetailModal'));
const recipientsModal = new bootstrap.Modal(document.getElementById('recipientsModal'));
let currentRecipientsKey = '';
let queueCurrentPage = 1;
let logsCurrentPage  = 1;

App.initDataTable('#accountsTable');

// Local AJAX helper (used for queue/logs inline updates that don't need loading overlay)
function ajaxPost(action, data = {}) {
    const fd = new FormData();
    fd.append('action', action);
    fd.append('csrf_token', csrfToken);
    for (const [k, v] of Object.entries(data)) fd.append(k, v);
    return fetch('mailer_management.php', { method: 'POST', body: fd }).then(r => r.json());
}

// Status badge helper
function statusBadge(status) {
    const map = { queued: 'bg-warning text-dark', processing: 'bg-info', sent: 'bg-success', failed: 'bg-danger', cancelled: 'bg-secondary' };
    return `<span class="badge ${map[status] || 'bg-secondary'}">${status}</span>`;
}

function dataJson(obj) { return JSON.stringify(obj).replace(/"/g, '&quot;'); }

// Fix Bootstrap nested-modal body scroll
['queueDetailModal', 'logDetailModal'].forEach(id => {
    document.getElementById(id).addEventListener('hidden.bs.modal', function() {
        const parent = id === 'queueDetailModal' ? 'queueModal' : 'logsModal';
        if (document.getElementById(parent).classList.contains('show'))
            document.body.classList.add('modal-open');
    });
});

// ═══ SMTP Accounts ═══
function openAddAccountModal() {
    document.getElementById('accountModalLabel').textContent = 'Add SMTP Account';
    document.getElementById('accountFormAction').value = 'create_account';
    document.getElementById('accountForm').reset();
    document.getElementById('accountId').value = '';
    document.getElementById('acctSmtpPort').value  = 587;
    document.getElementById('acctPriority').value  = 10;
    document.getElementById('acctDailyLimit').value = 500;
    document.getElementById('acctHourlyLimit').value = 50;
    document.getElementById('acctThrottle').value  = 1000;
    document.getElementById('acctIsActive').checked = true;
    document.getElementById('acctSmtpPass').required = true;
    document.getElementById('acctSmtpPass').placeholder = '';
    document.querySelector('#accountForm [name="csrf_token"]').value = csrfToken;
    accountModal.show();
}

function editAccount(acct) {
    document.getElementById('accountModalLabel').textContent = 'Edit SMTP Account';
    document.getElementById('accountFormAction').value = 'update_account';
    document.getElementById('accountId').value         = acct.id;
    document.getElementById('acctName').value          = acct.name;
    document.getElementById('acctSmtpHost').value      = acct.smtp_host;
    document.getElementById('acctSmtpPort').value      = acct.smtp_port;
    document.getElementById('acctSmtpEncryption').value = acct.smtp_encryption;
    document.getElementById('acctSmtpUser').value      = acct.smtp_username;
    document.getElementById('acctSmtpPass').value      = '';
    document.getElementById('acctSmtpPass').required   = false;
    document.getElementById('acctSmtpPass').placeholder = 'Leave blank to keep current';
    document.getElementById('acctFromEmail').value     = acct.from_email;
    document.getElementById('acctFromName').value      = acct.from_name;
    document.getElementById('acctReplyEmail').value    = acct.reply_to_email || '';
    document.getElementById('acctReplyName').value     = acct.reply_to_name  || '';
    document.getElementById('acctDailyLimit').value    = acct.daily_limit;
    document.getElementById('acctHourlyLimit').value   = acct.hourly_limit;
    document.getElementById('acctThrottle').value      = acct.throttle_ms;
    document.getElementById('acctPriority').value      = acct.priority;
    document.getElementById('acctIsActive').checked    = acct.is_active == 1;
    document.querySelector('#accountForm [name="csrf_token"]').value = csrfToken;
    accountModal.show();
}

function deleteAccount(id, name) {
    App.confirmDelete(name, { action: 'delete_account', id, csrf_token: csrfToken }, 'mailer_management.php', 'SMTP Account');
}

function testAccount(id) {
    showLoading('Testing SMTP connection...');
    ajaxPost('test_account', { id }).then(data => {
        hideLoading();
        data.success ? Notify.success(data.message || 'Connection successful!') : Notify.error(data.error || 'Connection failed');
    }).catch(err => { hideLoading(); Notify.error('Error: ' + err.message); });
}

// Account form: needs pre-processing for is_active and password before submit
document.getElementById('accountForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    if (!document.getElementById('acctIsActive').checked) fd.set('is_active', '0');
    if (document.getElementById('accountFormAction').value === 'update_account' && !fd.get('smtp_password')) fd.delete('smtp_password');

    const isUpdate = fd.get('action') === 'update_account';
    showLoading('Saving account...');
    fetch('mailer_management.php', { method: 'POST', body: fd })
        .then(App.handleAjaxResponse)
        .then(data => {
            if (data.success) {
                accountModal.hide();
                Notify.actionSuccess(isUpdate ? 'updated' : 'added', 'SMTP Account');
                setTimeout(() => location.reload(), 1500);
            } else {
                hideLoading();
                Notify.error(data.error);
            }
        })
        .catch(err => { hideLoading(); Notify.error(err.message); });
});

// ═══ Test Email ═══
function openTestEmailModal() {
    document.getElementById('testEmailAddress').value = '';
    testEmailModal.show();
}

document.getElementById('testEmailForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const email = document.getElementById('testEmailAddress').value;
    testEmailModal.hide();
    showLoading('Sending test email...');
    ajaxPost('send_test_email', { test_email: email }).then(data => {
        hideLoading();
        data.success ? Notify.success('Test email sent successfully!') : Notify.error(data.error || 'Failed to send');
    }).catch(err => { hideLoading(); Notify.error('Error: ' + err.message); });
});

// ═══ Queue ═══
function openQueueModal() { queueModal.show(); loadQueue(); }

function reprocessQueue() {
    const batch = Math.max(1, Math.min(100, parseInt(document.getElementById('reprocessBatchSize').value) || 10));
    const btn = document.getElementById('reprocessBtn');
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    showLoading(`Processing ${batch} queue items...`);
    ajaxPost('reprocess_queue', { batch_size: batch }).then(data => {
        hideLoading(); btn.disabled = false; btn.innerHTML = '<i class="fas fa-play"></i> Process Now';
        if (data.success) {
            const d = data.data;
            const msg = `<strong>Batch Complete</strong><br><span class="text-success">${d.sent} sent</span> &bull; <span class="text-danger">${d.failed} failed</span> &bull; <span class="text-muted">${d.skipped} skipped</span>`;
            d.failed > 0 && d.sent === 0 ? Notify.error(msg, 'Send Failed') : d.failed > 0 ? Notify.warning(msg, 'Partial Success') : Notify.success(msg);
            loadQueue(queueCurrentPage);
        } else { Notify.error(data.error || 'Reprocess failed'); }
    }).catch(err => { hideLoading(); btn.disabled = false; btn.innerHTML = '<i class="fas fa-play"></i> Process Now'; Notify.error('Error: ' + err.message); });
}

function loadQueue(page = 1) {
    queueCurrentPage = page;
    const tbody = document.getElementById('queueTableBody');
    tbody.innerHTML = '<tr><td colspan="8" class="text-center py-3"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>';
    ajaxPost('get_queue', {
        status: document.getElementById('queueStatusFilter').value,
        search: document.getElementById('queueSearchFilter').value,
        date_from: document.getElementById('queueDateFrom').value,
        date_to: document.getElementById('queueDateTo').value,
        page
    }).then(res => {
        if (!res.success || !res.result?.length) {
            tbody.innerHTML = `<tr><td colspan="8" class="text-center text-muted py-4">${res.success ? 'No queue items found' : 'Error loading queue'}</td></tr>`;
            document.getElementById('queuePaginationInfo').textContent = '';
            document.getElementById('queuePaginationNav').innerHTML = '';
            return;
        }
        tbody.innerHTML = res.result.map(item => `<tr>
            <td>${item.id}</td>
            <td>${item.to_name ? item.to_name + '<br>' : ''}<small class="text-muted">${item.to_email}</small></td>
            <td title="${item.subject}">${item.subject.length > 40 ? item.subject.substring(0, 40) + '...' : item.subject}</td>
            <td>${statusBadge(item.status)}</td>
            <td>${item.attempts}/${item.max_attempts}</td>
            <td>${item.priority}</td>
            <td><small>${item.created_at}</small></td>
            <td>
                <button class="btn btn-sm btn-outline-info" data-action="viewQueueItem" data-arg-json="${dataJson(item)}"><i class="fas fa-eye"></i></button>
                ${item.status === 'queued'  ? `<button class="btn btn-sm btn-outline-warning" data-action="cancelQueueItem" data-arg0="${item.id}"><i class="fas fa-ban"></i></button>` : ''}
                ${item.status === 'failed'  ? `<button class="btn btn-sm btn-outline-success" data-action="retryQueueItem"  data-arg0="${item.id}"><i class="fas fa-redo"></i></button>` : ''}
            </td>
        </tr>`).join('');
        const start = (res.page - 1) * res.per_page + 1;
        document.getElementById('queuePaginationInfo').textContent = `Showing ${start}-${Math.min(res.page * res.per_page, res.total)} of ${res.total}`;
        renderPagination('queuePaginationNav', res.page, res.total_pages, loadQueue);
    }).catch(err => { tbody.innerHTML = `<tr><td colspan="8" class="text-center text-danger">Error: ${err.message}</td></tr>`; });
}

function viewQueueItem(item) {
    document.getElementById('queueDetailContent').innerHTML = `
        <table class="table table-sm table-bordered">
            <tr><th>ID</th><td>${item.id}</td></tr>
            <tr><th>To</th><td>${item.to_name || ''} &lt;${item.to_email}&gt;</td></tr>
            <tr><th>Subject</th><td>${item.subject}</td></tr>
            <tr><th>Status</th><td>${statusBadge(item.status)}</td></tr>
            <tr><th>Account</th><td>${item.account_name || 'Not assigned'}</td></tr>
            <tr><th>Priority</th><td>${item.priority}</td></tr>
            <tr><th>Attempts</th><td>${item.attempts} / ${item.max_attempts}</td></tr>
            <tr><th>Created</th><td>${item.created_at}</td></tr>
            ${item.sent_at ? `<tr><th>Sent At</th><td>${item.sent_at}</td></tr>` : ''}
            ${item.next_attempt_at ? `<tr><th>Next Retry</th><td>${item.next_attempt_at}</td></tr>` : ''}
            ${item.last_error ? `<tr><th>Last Error</th><td class="text-danger">${item.last_error}</td></tr>` : ''}
        </table>
        <h6>HTML Body Preview</h6>
        <div class="border rounded p-2" style="max-height:300px;overflow-y:auto;background:#f8f9fa">${item.body_html}</div>`;
    queueDetailModal.show();
}

function cancelQueueItem(id) {
    Notify.confirmDelete('this queue item', function() {
        ajaxPost('cancel_queue', { id }).then(data => {
            data.success ? (Notify.success('Queue item cancelled'), loadQueue(queueCurrentPage)) : Notify.error(data.error);
        }).catch(err => Notify.error('Error: ' + err.message));
    });
}

function retryQueueItem(id) {
    ajaxPost('retry_queue', { id }).then(data => {
        data.success ? (Notify.success('Queue item queued for retry'), loadQueue(queueCurrentPage)) : Notify.error(data.error);
    }).catch(err => Notify.error('Error: ' + err.message));
}

// ═══ Delivery Logs ═══
function openLogsModal() { logsModal.show(); loadLogs(); }

function loadLogs(page = 1) {
    logsCurrentPage = page;
    const tbody = document.getElementById('logsTableBody');
    tbody.innerHTML = '<tr><td colspan="8" class="text-center py-3"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>';
    ajaxPost('get_logs', {
        status: document.getElementById('logStatusFilter').value,
        account_id: document.getElementById('logAccountFilter').value,
        search: document.getElementById('logSearchFilter').value,
        date_from: document.getElementById('logDateFrom').value,
        date_to: document.getElementById('logDateTo').value,
        page
    }).then(res => {
        if (!res.success || !res.result?.length) {
            tbody.innerHTML = `<tr><td colspan="8" class="text-center text-muted py-4">${res.success ? 'No log entries found' : 'Error loading logs'}</td></tr>`;
            document.getElementById('logsPaginationInfo').textContent = '';
            document.getElementById('logsPaginationNav').innerHTML = '';
            return;
        }
        tbody.innerHTML = res.result.map(log => `<tr>
            <td>${log.id}</td>
            <td><small>${log.to_email}</small></td>
            <td title="${log.subject}">${log.subject.length > 40 ? log.subject.substring(0, 40) + '...' : log.subject}</td>
            <td><small>${log.account_name || '-'}</small></td>
            <td>${statusBadge(log.status)}</td>
            <td>${log.duration_ms ? log.duration_ms + 'ms' : '-'}</td>
            <td><small>${log.sent_at}</small></td>
            <td><button class="btn btn-sm btn-outline-info" data-action="viewLog" data-arg-json="${dataJson(log)}"><i class="fas fa-eye"></i></button></td>
        </tr>`).join('');
        const start = (res.page - 1) * res.per_page + 1;
        document.getElementById('logsPaginationInfo').textContent = `Showing ${start}-${Math.min(res.page * res.per_page, res.total)} of ${res.total}`;
        renderPagination('logsPaginationNav', res.page, res.total_pages, loadLogs);
    }).catch(err => { tbody.innerHTML = `<tr><td colspan="8" class="text-center text-danger">Error: ${err.message}</td></tr>`; });
}

function viewLog(log) {
    document.getElementById('logDetailContent').innerHTML = `
        <table class="table table-sm table-bordered">
            <tr><th>ID</th><td>${log.id}</td></tr>
            <tr><th>Queue ID</th><td>${log.queue_id || '-'}</td></tr>
            <tr><th>To</th><td>${log.to_email}</td></tr>
            <tr><th>Subject</th><td>${log.subject}</td></tr>
            <tr><th>Account</th><td>${log.account_name || '-'}</td></tr>
            <tr><th>Status</th><td>${statusBadge(log.status)}</td></tr>
            <tr><th>Duration</th><td>${log.duration_ms ? log.duration_ms + 'ms' : '-'}</td></tr>
            <tr><th>Sent At</th><td>${log.sent_at}</td></tr>
            ${log.smtp_response  ? `<tr><th>SMTP Response</th><td><code>${log.smtp_response}</code></td></tr>` : ''}
            ${log.error_message  ? `<tr><th>Error</th><td class="text-danger">${log.error_message}</td></tr>` : ''}
        </table>`;
    logDetailModal.show();
}

// ═══ Email Recipients ═══
function openRecipientsModal(settingKey, title, description) {
    currentRecipientsKey = settingKey;
    document.getElementById('recipientsModalTitle').innerHTML = '<i class="fas fa-envelope me-2"></i> ' + title;
    document.getElementById('recipientsModalDesc').textContent = description + ' Enter one email per line.';
    document.getElementById('recipientsList').value = '';
    document.getElementById('recipientsStatus').innerHTML = '';
    recipientsModal.show();
    ajaxPost('get_report_recipients', { setting_key: settingKey }).then(data => {
        if (data.success && data.recipients?.length)
            document.getElementById('recipientsList').value = data.recipients.join('\n');
    }).catch(() => {});
}

function saveRecipients() {
    const btn = document.getElementById('saveRecipientsBtn');
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    ajaxPost('save_report_recipients', { setting_key: currentRecipientsKey, emails: document.getElementById('recipientsList').value.trim() })
    .then(data => {
        btn.disabled = false; btn.innerHTML = '<i class="fas fa-save"></i> Save Recipients';
        if (data.success) {
            Notify.success(data.count > 0 ? `Saved ${data.count} recipient(s).` : 'Recipients cleared.');
            recipientsModal.hide();
        } else {
            document.getElementById('recipientsStatus').innerHTML = `<div class="alert alert-danger py-1 small">${data.error}</div>`;
        }
    }).catch(err => { btn.disabled = false; btn.innerHTML = '<i class="fas fa-save"></i> Save Recipients'; Notify.error('Error: ' + err.message); });
}

// ═══ Pagination ═══
function renderPagination(containerId, currentPage, totalPages, callback) {
    const nav = document.getElementById(containerId);
    if (totalPages <= 1) { nav.innerHTML = ''; return; }
    const sp = Math.max(1, currentPage - 2), ep = Math.min(totalPages, currentPage + 2);
    let html = `<li class="page-item ${currentPage <= 1 ? 'disabled' : ''}"><a class="page-link" href="#" data-action="${callback.name}" data-arg0="${currentPage - 1}">&laquo;</a></li>`;
    if (sp > 1) { html += `<li class="page-item"><a class="page-link" href="#" data-action="${callback.name}" data-arg0="1">1</a></li>`; if (sp > 2) html += '<li class="page-item disabled"><span class="page-link">...</span></li>'; }
    for (let i = sp; i <= ep; i++) html += `<li class="page-item ${i === currentPage ? 'active' : ''}"><a class="page-link" href="#" data-action="${callback.name}" data-arg0="${i}">${i}</a></li>`;
    if (ep < totalPages) { if (ep < totalPages - 1) html += '<li class="page-item disabled"><span class="page-link">...</span></li>'; html += `<li class="page-item"><a class="page-link" href="#" data-action="${callback.name}" data-arg0="${totalPages}">${totalPages}</a></li>`; }
    html += `<li class="page-item ${currentPage >= totalPages ? 'disabled' : ''}"><a class="page-link" href="#" data-action="${callback.name}" data-arg0="${currentPage + 1}">&raquo;</a></li>`;
    nav.innerHTML = html;
}
</script>
<?php
$pageScripts = ob_get_clean();
include 'include/layout.php';
