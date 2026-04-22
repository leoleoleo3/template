<?php
/**
 * MailerAccountManager Class
 *
 * Manages SMTP account CRUD operations and rate limiting.
 * Supports multiple SMTP providers with priority-based selection
 * and automatic failover when rate limits are reached.
 *
 * @package TEMPLATE
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

class MailerAccountManager
{
    private DB $db;
    private static ?MailerAccountManager $instance = null;

    private function __construct(DB $db)
    {
        $this->db = $db;
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

    /**
     * Get all mailer accounts
     */
    public function getAllAccounts(bool $includeHidden = false): array
    {
        return $this->db->select(
            'mailer_account',
            '*',
            [],
            'ORDER BY priority ASC, name ASC',
            $includeHidden
        );
    }

    /**
     * Get account by ID
     */
    public function getAccountById(int $id): ?array
    {
        $result = $this->db->select('mailer_account', '*', ['id' => $id]);
        return $result['success'] && !empty($result['result']) ? $result['result'][0] : null;
    }

    /**
     * Create a new SMTP account
     */
    public function createAccount(array $data): array
    {
        $required = ['name', 'smtp_host', 'smtp_username', 'smtp_password', 'from_email', 'from_name'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return ['success' => false, 'error' => "Required field missing: $field"];
            }
        }

        $accountData = [
            'name' => trim($data['name']),
            'smtp_host' => trim($data['smtp_host']),
            'smtp_port' => (int)($data['smtp_port'] ?? 587),
            'smtp_encryption' => in_array($data['smtp_encryption'] ?? 'tls', ['tls', 'ssl', 'none']) ? $data['smtp_encryption'] : 'tls',
            'smtp_username' => trim($data['smtp_username']),
            'smtp_password' => trim($data['smtp_password']),
            'from_email' => trim($data['from_email']),
            'from_name' => trim($data['from_name']),
            'reply_to_email' => !empty($data['reply_to_email']) ? trim($data['reply_to_email']) : null,
            'reply_to_name' => !empty($data['reply_to_name']) ? trim($data['reply_to_name']) : null,
            'daily_limit' => (int)($data['daily_limit'] ?? 500),
            'hourly_limit' => (int)($data['hourly_limit'] ?? 50),
            'priority' => (int)($data['priority'] ?? 10),
            'throttle_ms' => (int)($data['throttle_ms'] ?? 1000),
            'is_active' => isset($data['is_active']) ? (int)$data['is_active'] : 1,
            'hidden' => 0
        ];

        return $this->db->insert('mailer_account', $accountData);
    }

    /**
     * Update an existing SMTP account
     */
    public function updateAccount(int $id, array $data): array
    {
        $account = $this->getAccountById($id);
        if (!$account) {
            return ['success' => false, 'error' => 'Account not found'];
        }

        $allowedFields = [
            'name', 'smtp_host', 'smtp_port', 'smtp_encryption',
            'smtp_username', 'smtp_password', 'from_email', 'from_name',
            'reply_to_email', 'reply_to_name', 'daily_limit', 'hourly_limit',
            'priority', 'throttle_ms', 'is_active'
        ];

        $updateData = [];
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $value = $data[$field];
                if (in_array($field, ['smtp_port', 'daily_limit', 'hourly_limit', 'priority', 'throttle_ms', 'is_active'])) {
                    $updateData[$field] = (int)$value;
                } elseif (in_array($field, ['reply_to_email', 'reply_to_name'])) {
                    $updateData[$field] = !empty($value) ? trim($value) : null;
                } elseif ($field === 'smtp_encryption') {
                    $updateData[$field] = in_array($value, ['tls', 'ssl', 'none']) ? $value : 'tls';
                } else {
                    $updateData[$field] = trim($value);
                }
            }
        }

        if (empty($updateData)) {
            return ['success' => false, 'error' => 'No data to update'];
        }

        $updateData['updated_at'] = date('Y-m-d H:i:s');
        return $this->db->update('mailer_account', $updateData, ['id' => $id]);
    }

    /**
     * Soft delete an account
     */
    public function deleteAccount(int $id): array
    {
        $account = $this->getAccountById($id);
        if (!$account) {
            return ['success' => false, 'error' => 'Account not found'];
        }
        return $this->db->softDelete('mailer_account', ['id' => $id]);
    }

    /**
     * Get the best available SMTP account for sending.
     * Selects based on priority, respects hourly/daily rate limits,
     * and auto-resets counters when the time window has passed.
     */
    public function getAvailableAccount(): ?array
    {
        // First, reset counters for any accounts where the hour/day has rolled over
        $this->db->query(
            "UPDATE mailer_account SET sent_this_hour = 0, hour_reset_at = DATE_ADD(NOW(), INTERVAL 1 HOUR)
             WHERE is_active = 1 AND hidden = 0 AND (hour_reset_at IS NULL OR hour_reset_at <= NOW())"
        );
        $this->db->query(
            "UPDATE mailer_account SET sent_today = 0, day_reset_at = DATE_ADD(CURDATE(), INTERVAL 1 DAY)
             WHERE is_active = 1 AND hidden = 0 AND (day_reset_at IS NULL OR day_reset_at <= CURDATE())"
        );

        // Now find the best available account
        $result = $this->db->query(
            "SELECT * FROM mailer_account
             WHERE is_active = 1 AND hidden = 0
               AND sent_this_hour < hourly_limit
               AND sent_today < daily_limit
             ORDER BY priority ASC, sent_today ASC
             LIMIT 1"
        );

        if ($result['success'] && !empty($result['result'])) {
            return $result['result'][0];
        }
        return null;
    }

    /**
     * Increment the sent counters for an account after a successful send
     */
    public function incrementSentCount(int $id): array
    {
        return $this->db->query(
            "UPDATE mailer_account SET sent_today = sent_today + 1, sent_this_hour = sent_this_hour + 1 WHERE id = ?",
            [$id]
        );
    }

    /**
     * Test SMTP connection for an account without sending an email
     */
    public function testConnection(int $id): array
    {
        $account = $this->getAccountById($id);
        if (!$account) {
            return ['success' => false, 'error' => 'Account not found'];
        }

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

            $mail->Timeout = 10;
            $mail->SMTPDebug = SMTP::DEBUG_OFF;

            $smtp = $mail->getSMTPInstance();
            $smtp->setTimeout(10);

            $connected = $smtp->connect($account['smtp_host'], (int)$account['smtp_port']);
            if (!$connected) {
                return ['success' => false, 'error' => 'Could not connect to SMTP server'];
            }

            $hello = $smtp->hello(gethostname() ?: 'localhost');
            if (!$hello) {
                $smtp->close();
                return ['success' => false, 'error' => 'SMTP EHLO/HELO failed'];
            }

            // Start TLS if needed
            if ($encryption === 'tls') {
                $smtp->startTLS();
                $smtp->hello(gethostname() ?: 'localhost');
            }

            $authenticated = $smtp->authenticate($account['smtp_username'], $account['smtp_password']);
            $smtp->close();

            if ($authenticated) {
                return ['success' => true, 'message' => 'SMTP connection and authentication successful'];
            } else {
                return ['success' => false, 'error' => 'SMTP authentication failed. Check username/password.'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Connection test failed: ' . $e->getMessage()];
        }
    }

    /**
     * Get account statistics for dashboard
     */
    public function getAccountStats(): array
    {
        $stats = [
            'total_accounts' => 0,
            'active_accounts' => 0,
            'sent_today' => 0,
            'failed_today' => 0
        ];

        // Active accounts
        $result = $this->db->select('mailer_account', 'COUNT(*) as count');
        if ($result['success'] && !empty($result['result'])) {
            $stats['active_accounts'] = (int)$result['result'][0]['count'];
        }

        // Total (including hidden)
        $result = $this->db->select('mailer_account', 'COUNT(*) as count', [], '', true);
        if ($result['success'] && !empty($result['result'])) {
            $stats['total_accounts'] = (int)$result['result'][0]['count'];
        }

        // Total sent today across all accounts
        $result = $this->db->select('mailer_account', 'SUM(sent_today) as total');
        if ($result['success'] && !empty($result['result'])) {
            $stats['sent_today'] = (int)($result['result'][0]['total'] ?? 0);
        }

        // Failed today from logs
        $result = $this->db->query(
            "SELECT COUNT(*) as count FROM mailer_log
             WHERE status = 'failed' AND DATE(sent_at) = CURDATE()"
        );
        if ($result['success'] && !empty($result['result'])) {
            $stats['failed_today'] = (int)$result['result'][0]['count'];
        }

        return $stats;
    }

    /**
     * Get accounts formatted for dropdown selection
     */
    public function getAccountsForDropdown(): array
    {
        $result = $this->db->select(
            'mailer_account',
            ['id', 'name', 'from_email'],
            [],
            'ORDER BY priority ASC, name ASC'
        );
        return $result['success'] ? $result['result'] : [];
    }
}
