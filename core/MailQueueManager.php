<?php
/**
 * MailQueueManager Class
 *
 * Manages the email queue: enqueue, process, send via PHPMailer, retry with
 * exponential backoff, and immutable delivery logging.
 *
 * @package TEMPLATE
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
require_once 'MailerAccountManager.php';

class MailQueueManager
{
    private DB $db;
    private MailerAccountManager $accountManager;
    private static ?MailQueueManager $instance = null;

    /** Persistent storage for queued attachment files (outside public/) */
    private const ATTACHMENT_DIR = __DIR__ . '/../storage/mail_attachments/';

    private function __construct(DB $db)
    {
        $this->db = $db;
        $this->accountManager = MailerAccountManager::getInstance($db);
    }

    public static function getInstance(DB $db = null): self
    {
        if (self::$instance === null) {
            if ($db === null) {
                throw new Exception('Database instance required for first instantiation');
            }
            self::$instance = new self($db);
        }
        return self::$instance;
    }

    // ─── Enqueue ────────────────────────────────────────────────────

    /**
     * Add an email to the queue
     *
     * @param array $data [to_email, to_name, subject, body_html, body_text,
     *                      cc, bcc, reply_to_email, reply_to_name,
     *                      priority (1-5, lower=higher), context, created_by, max_attempts]
     */
    public function enqueue(array $data): array
    {
        if (empty($data['to_email']) || empty($data['subject']) || empty($data['body_html'])) {
            return ['success' => false, 'error' => 'Required: to_email, subject, body_html'];
        }

        if (!filter_var($data['to_email'], FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Invalid email address'];
        }

        // Copy temp attachment files to persistent storage so they survive until the queue processes
        if (!empty($data['attachments'])) {
            if (!is_dir(self::ATTACHMENT_DIR)) {
                mkdir(self::ATTACHMENT_DIR, 0755, true);
            }
            $persistedAttachments = [];
            foreach ($data['attachments'] as $att) {
                if (isset($att['path']) && file_exists($att['path'])) {
                    $ext     = strtolower(pathinfo($att['path'], PATHINFO_EXTENSION));
                    $newName = uniqid('mq_', true) . ($ext ? '.' . $ext : '');
                    $newPath = self::ATTACHMENT_DIR . $newName;
                    if (copy($att['path'], $newPath)) {
                        $att['path']     = $newPath;
                        $att['_managed'] = true; // flag for cleanup after successful send
                    }
                }
                $persistedAttachments[] = $att;
            }
            $data['attachments'] = $persistedAttachments;
        }

        $queueData = [
            'to_email' => trim($data['to_email']),
            'to_name' => !empty($data['to_name']) ? trim($data['to_name']) : null,
            'subject' => trim($data['subject']),
            'body_html' => $data['body_html'],
            'body_text' => $data['body_text'] ?? strip_tags($data['body_html']),
            'cc' => !empty($data['cc']) ? json_encode($data['cc']) : null,
            'bcc' => !empty($data['bcc']) ? json_encode($data['bcc']) : null,
            'reply_to_email' => !empty($data['reply_to_email']) ? trim($data['reply_to_email']) : null,
            'reply_to_name' => !empty($data['reply_to_name']) ? trim($data['reply_to_name']) : null,
            'attachments' => !empty($data['attachments']) ? json_encode($data['attachments']) : null,
            'priority' => (int)($data['priority'] ?? 3),
            'status' => 'queued',
            'max_attempts' => (int)($data['max_attempts'] ?? 3),
            'context' => !empty($data['context']) ? json_encode($data['context']) : null,
            'created_by' => isset($data['created_by']) ? (int)$data['created_by'] : null,
            'hidden' => 0
        ];

        return $this->db->insert('mailer_queue', $queueData);
    }

    /**
     * Send an email immediately (bypass queue).
     * Enqueues the email and processes it right away.
     * Use for critical emails like password resets.
     */
    public function sendNow(array $data): array
    {
        $data['priority'] = 1; // Highest priority
        $enqueueResult = $this->enqueue($data);

        if (!$enqueueResult['success']) {
            return $enqueueResult;
        }

        $queueId = $enqueueResult['insert_id'];

        // Process this single item immediately
        $result = $this->db->select('mailer_queue', '*', ['id' => $queueId]);
        if (!$result['success'] || empty($result['result'])) {
            return ['success' => false, 'error' => 'Failed to retrieve queued email'];
        }

        $queueItem = $result['result'][0];
        return $this->processSingleItem($queueItem);
    }

    // ─── Queue Processing ───────────────────────────────────────────

    /**
     * Process the queue: lock, send, log, update status
     *
     * @param int $batchSize Max items to process in this run
     * @param string|null $lockId Unique ID for this processor instance
     * @return array Results summary
     */
    public function processQueue(int $batchSize = 10, ?string $lockId = null): array
    {
        $lockId = $lockId ?? (gethostname() . '_' . getmypid());
        $results = ['sent' => 0, 'failed' => 0, 'skipped' => 0];

        // Lock next batch of queued items ready for processing
        $this->db->query(
            "UPDATE mailer_queue
             SET status = 'processing', locked_at = NOW(), locked_by = ?
             WHERE status = 'queued'
               AND (next_attempt_at IS NULL OR next_attempt_at <= NOW())
               AND hidden = 0
             ORDER BY priority ASC, created_at ASC
             LIMIT ?",
            [$lockId, $batchSize]
        );

        // Fetch locked items
        $lockedItems = $this->db->query(
            "SELECT * FROM mailer_queue WHERE locked_by = ? AND status = 'processing'",
            [$lockId]
        );

        if (!$lockedItems['success'] || empty($lockedItems['result'])) {
            return $results;
        }

        foreach ($lockedItems['result'] as $item) {
            $sendResult = $this->processSingleItem($item);

            if ($sendResult['success']) {
                $results['sent']++;
            } else {
                $results['failed']++;
            }

            // Throttle between sends (use the account's throttle setting)
            if (isset($sendResult['throttle_ms']) && $sendResult['throttle_ms'] > 0) {
                usleep($sendResult['throttle_ms'] * 1000);
            }
        }

        return $results;
    }

    /**
     * Process a single queue item: select account, send, log result
     */
    private function processSingleItem(array $item): array
    {
        $account = $this->accountManager->getAvailableAccount();
        if (!$account) {
            // No available account — put back in queue for later
            $this->db->update('mailer_queue', [
                'status' => 'queued',
                'locked_at' => null,
                'locked_by' => null,
                'last_error' => 'No SMTP account available (all at rate limit)',
                'next_attempt_at' => date('Y-m-d H:i:s', strtotime('+2 minutes'))
            ], ['id' => $item['id']]);

            return ['success' => false, 'error' => 'No available SMTP account', 'throttle_ms' => 0];
        }

        $startTime = microtime(true);
        $sendResult = $this->doSend($item, $account);
        $durationMs = (int)((microtime(true) - $startTime) * 1000);

        if ($sendResult['success']) {
            // Success: update queue, increment counters, log
            $this->db->update('mailer_queue', [
                'status' => 'sent',
                'mailer_account_id' => $account['id'],
                'sent_at' => date('Y-m-d H:i:s'),
                'attempts' => (int)$item['attempts'] + 1,
                'locked_at' => null,
                'locked_by' => null
            ], ['id' => $item['id']]);

            $this->accountManager->incrementSentCount($account['id']);

            $this->logDelivery([
                'queue_id' => $item['id'],
                'mailer_account_id' => $account['id'],
                'to_email' => $item['to_email'],
                'subject' => $item['subject'],
                'status' => 'sent',
                'smtp_response' => $sendResult['smtp_response'] ?? null,
                'duration_ms' => $durationMs
            ]);

            return ['success' => true, 'throttle_ms' => (int)$account['throttle_ms']];
        } else {
            // Failure: increment attempts, schedule retry or mark as permanently failed
            $attempts = (int)$item['attempts'] + 1;
            $maxAttempts = (int)$item['max_attempts'];
            $error = $sendResult['error'] ?? 'Unknown error';

            if ($attempts >= $maxAttempts) {
                // Permanently failed
                $this->db->update('mailer_queue', [
                    'status' => 'failed',
                    'mailer_account_id' => $account['id'],
                    'attempts' => $attempts,
                    'last_error' => $error,
                    'locked_at' => null,
                    'locked_by' => null
                ], ['id' => $item['id']]);
            } else {
                // Schedule retry with exponential backoff
                $delays = [60, 300, 1800]; // 1min, 5min, 30min
                $delay = $delays[min($attempts - 1, count($delays) - 1)];
                $nextAttempt = date('Y-m-d H:i:s', time() + $delay);

                $this->db->update('mailer_queue', [
                    'status' => 'queued',
                    'mailer_account_id' => $account['id'],
                    'attempts' => $attempts,
                    'last_error' => $error,
                    'next_attempt_at' => $nextAttempt,
                    'locked_at' => null,
                    'locked_by' => null
                ], ['id' => $item['id']]);
            }

            $this->logDelivery([
                'queue_id' => $item['id'],
                'mailer_account_id' => $account['id'],
                'to_email' => $item['to_email'],
                'subject' => $item['subject'],
                'status' => 'failed',
                'error_message' => $error,
                'duration_ms' => $durationMs
            ]);

            return ['success' => false, 'error' => $error, 'throttle_ms' => (int)$account['throttle_ms']];
        }
    }

    /**
     * Build PHPMailer instance and send the email
     */
    private function doSend(array $queueItem, array $account): array
    {
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $account['smtp_host'];
            $mail->Port = (int)$account['smtp_port'];
            $mail->SMTPAuth = true;
            $mail->Username = $account['smtp_username'];
            $mail->Password = $account['smtp_password'];

            $encryption = $account['smtp_encryption'];
            if ($encryption === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($encryption === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = '';
                $mail->SMTPAutoTLS = false;
            }

            $mail->Timeout = 30;
            $mail->CharSet = 'UTF-8';
            $mail->XMailer = 'TEMPLATE Mailer';

            // Sender
            $mail->setFrom($account['from_email'], $account['from_name']);

            // Reply-to: use queue item's reply-to, fall back to account's
            $replyEmail = $queueItem['reply_to_email'] ?? $account['reply_to_email'] ?? null;
            $replyName = $queueItem['reply_to_name'] ?? $account['reply_to_name'] ?? '';
            if ($replyEmail) {
                $mail->addReplyTo($replyEmail, $replyName);
            }

            // Recipient
            $mail->addAddress($queueItem['to_email'], $queueItem['to_name'] ?? '');

            // CC
            if (!empty($queueItem['cc'])) {
                $ccList = json_decode($queueItem['cc'], true) ?? [];
                foreach ($ccList as $cc) {
                    $mail->addCC($cc['email'], $cc['name'] ?? '');
                }
            }

            // BCC
            if (!empty($queueItem['bcc'])) {
                $bccList = json_decode($queueItem['bcc'], true) ?? [];
                foreach ($bccList as $bcc) {
                    $mail->addBCC($bcc['email'], $bcc['name'] ?? '');
                }
            }

            // Content
            $mail->isHTML(true);
            $mail->Subject = $queueItem['subject'];
            $mail->Body = $queueItem['body_html'];
            if (!empty($queueItem['body_text'])) {
                $mail->AltBody = $queueItem['body_text'];
            }

            // Attachments
            if (!empty($queueItem['attachments'])) {
                $attachments = json_decode($queueItem['attachments'], true) ?? [];
                foreach ($attachments as $att) {
                    if (isset($att['path']) && file_exists($att['path'])) {
                        $mail->addAttachment($att['path'], $att['name'] ?? '');
                    }
                }
            }

            $mail->send();

            $smtpResponse = '';
            try {
                $smtpResponse = $mail->getSMTPInstance()->getLastReply();
            } catch (Exception $e) {
                // Not critical
            }

            // Clean up managed attachment files now that the email was sent successfully
            if (!empty($queueItem['attachments'])) {
                $atts = json_decode($queueItem['attachments'], true) ?? [];
                foreach ($atts as $att) {
                    if (!empty($att['_managed']) && !empty($att['path']) && file_exists($att['path'])) {
                        @unlink($att['path']);
                    }
                }
            }

            return ['success' => true, 'smtp_response' => $smtpResponse];
        } catch (PHPMailerException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // ─── Queue Management ───────────────────────────────────────────

    /**
     * Cancel a queued email
     */
    public function cancelQueueItem(int $id): array
    {
        $item = $this->getQueueItemById($id);
        if (!$item) {
            return ['success' => false, 'error' => 'Queue item not found'];
        }
        if ($item['status'] !== 'queued') {
            return ['success' => false, 'error' => 'Can only cancel queued items'];
        }
        return $this->db->update('mailer_queue', ['status' => 'cancelled'], ['id' => $id]);
    }

    /**
     * Retry a failed email
     */
    public function retryQueueItem(int $id): array
    {
        $item = $this->getQueueItemById($id);
        if (!$item) {
            return ['success' => false, 'error' => 'Queue item not found'];
        }
        if ($item['status'] !== 'failed') {
            return ['success' => false, 'error' => 'Can only retry failed items'];
        }
        return $this->db->update('mailer_queue', [
            'status' => 'queued',
            'attempts' => 0,
            'next_attempt_at' => null,
            'locked_at' => null,
            'locked_by' => null,
            'last_error' => null
        ], ['id' => $id]);
    }

    /**
     * Get a single queue item by ID
     */
    public function getQueueItemById(int $id): ?array
    {
        $result = $this->db->select('mailer_queue', '*', ['id' => $id]);
        return $result['success'] && !empty($result['result']) ? $result['result'][0] : null;
    }

    /**
     * Get queue items with filters and pagination
     */
    public function getQueueItems(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $conditions = ['mq.hidden = 0'];
        $params = [];

        if (!empty($filters['status'])) {
            $conditions[] = 'mq.status = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $conditions[] = '(mq.to_email LIKE ? OR mq.subject LIKE ?)';
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
        }

        if (!empty($filters['date_from'])) {
            $conditions[] = 'mq.created_at >= ?';
            $params[] = $filters['date_from'] . ' 00:00:00';
        }

        if (!empty($filters['date_to'])) {
            $conditions[] = 'mq.created_at <= ?';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        $where = implode(' AND ', $conditions);
        $offset = ($page - 1) * $perPage;

        // Count total
        $countResult = $this->db->query(
            "SELECT COUNT(*) as total FROM mailer_queue mq WHERE $where",
            $params
        );
        $total = $countResult['success'] && !empty($countResult['result']) ? (int)$countResult['result'][0]['total'] : 0;

        // Fetch items
        $itemsResult = $this->db->query(
            "SELECT mq.*, ma.name as account_name
             FROM mailer_queue mq
             LEFT JOIN mailer_account ma ON mq.mailer_account_id = ma.id
             WHERE $where
             ORDER BY mq.created_at DESC
             LIMIT ? OFFSET ?",
            array_merge($params, [$perPage, $offset])
        );

        return [
            'success' => true,
            'result' => $itemsResult['success'] ? $itemsResult['result'] : [],
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $perPage > 0 ? ceil($total / $perPage) : 0
        ];
    }

    /**
     * Get queue statistics by status
     */
    public function getQueueStats(): array
    {
        $stats = ['queued' => 0, 'processing' => 0, 'sent' => 0, 'failed' => 0, 'cancelled' => 0];
        $result = $this->db->query(
            "SELECT status, COUNT(*) as count FROM mailer_queue WHERE hidden = 0 GROUP BY status"
        );
        if ($result['success'] && !empty($result['result'])) {
            foreach ($result['result'] as $row) {
                $stats[$row['status']] = (int)$row['count'];
            }
        }
        return $stats;
    }

    // ─── Delivery Logs ──────────────────────────────────────────────

    /**
     * Log a delivery attempt (immutable)
     */
    private function logDelivery(array $data): array
    {
        return $this->db->query(
            "INSERT INTO mailer_log (queue_id, mailer_account_id, to_email, subject, status, smtp_response, error_message, duration_ms, sent_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            [
                $data['queue_id'] ?? null,
                $data['mailer_account_id'] ?? null,
                $data['to_email'],
                $data['subject'],
                $data['status'],
                $data['smtp_response'] ?? null,
                $data['error_message'] ?? null,
                $data['duration_ms'] ?? null
            ]
        );
    }

    /**
     * Get delivery logs with filters and pagination
     */
    public function getLogs(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $conditions = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) {
            $conditions[] = 'ml.status = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['account_id'])) {
            $conditions[] = 'ml.mailer_account_id = ?';
            $params[] = (int)$filters['account_id'];
        }

        if (!empty($filters['search'])) {
            $conditions[] = '(ml.to_email LIKE ? OR ml.subject LIKE ?)';
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
        }

        if (!empty($filters['date_from'])) {
            $conditions[] = 'ml.sent_at >= ?';
            $params[] = $filters['date_from'] . ' 00:00:00';
        }

        if (!empty($filters['date_to'])) {
            $conditions[] = 'ml.sent_at <= ?';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        $where = implode(' AND ', $conditions);
        $offset = ($page - 1) * $perPage;

        // Count
        $countResult = $this->db->query(
            "SELECT COUNT(*) as total FROM mailer_log ml WHERE $where",
            $params
        );
        $total = $countResult['success'] && !empty($countResult['result']) ? (int)$countResult['result'][0]['total'] : 0;

        // Fetch
        $logsResult = $this->db->query(
            "SELECT ml.*, ma.name as account_name
             FROM mailer_log ml
             LEFT JOIN mailer_account ma ON ml.mailer_account_id = ma.id
             WHERE $where
             ORDER BY ml.sent_at DESC
             LIMIT ? OFFSET ?",
            array_merge($params, [$perPage, $offset])
        );

        return [
            'success' => true,
            'result' => $logsResult['success'] ? $logsResult['result'] : [],
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $perPage > 0 ? ceil($total / $perPage) : 0
        ];
    }

    /**
     * Get log statistics
     */
    public function getLogStats(): array
    {
        $stats = [
            'sent_today' => 0,
            'failed_today' => 0,
            'sent_this_week' => 0,
            'sent_this_month' => 0
        ];

        $result = $this->db->query(
            "SELECT
                SUM(CASE WHEN status = 'sent' AND DATE(sent_at) = CURDATE() THEN 1 ELSE 0 END) as sent_today,
                SUM(CASE WHEN status = 'failed' AND DATE(sent_at) = CURDATE() THEN 1 ELSE 0 END) as failed_today,
                SUM(CASE WHEN status = 'sent' AND sent_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as sent_this_week,
                SUM(CASE WHEN status = 'sent' AND sent_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as sent_this_month
             FROM mailer_log"
        );

        if ($result['success'] && !empty($result['result'])) {
            $row = $result['result'][0];
            $stats['sent_today'] = (int)($row['sent_today'] ?? 0);
            $stats['failed_today'] = (int)($row['failed_today'] ?? 0);
            $stats['sent_this_week'] = (int)($row['sent_this_week'] ?? 0);
            $stats['sent_this_month'] = (int)($row['sent_this_month'] ?? 0);
        }

        return $stats;
    }

    /**
     * Purge old log entries
     */
    public function purgeOldLogs(int $days = 90): array
    {
        return $this->db->query(
            "DELETE FROM mailer_log WHERE sent_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$days]
        );
    }

    // ─── Maintenance ────────────────────────────────────────────────

    /**
     * Unlock stale processing jobs (stuck items from crashed processors)
     */
    public function cleanupStaleJobs(int $minutes = 30): array
    {
        return $this->db->query(
            "UPDATE mailer_queue
             SET status = 'queued', locked_at = NULL, locked_by = NULL
             WHERE status = 'processing'
               AND locked_at < DATE_SUB(NOW(), INTERVAL ? MINUTE)",
            [$minutes]
        );
    }
}
