<?php

/**
 * AuditTrailManager Class
 *
 * Provides a unified, immutable audit trail for all system actions.
 * INSERT-only design — no update or delete methods are exposed.
 * Includes SHA-256 row hashing for tamper detection.
 */
class AuditTrailManager
{
    private DB $db;
    private static ?AuditTrailManager $instance = null;

    private function __construct(DB $db)
    {
        $this->db = $db;
    }

    /**
     * Get singleton instance
     */
    public static function getInstance(DB $db = null): self
    {
        if (self::$instance === null) {
            if ($db === null) {
                throw new Exception('Database instance required for first initialization');
            }
            self::$instance = new self($db);
        }
        return self::$instance;
    }

    /**
     * Log an audit trail event (INSERT only — immutable)
     */
    public function log(
        string  $action,
        string  $entityType,
        ?string $entityId = null,
        ?array  $oldValue = null,
        ?array  $newValue = null,
        ?string $description = null
    ): array {
        try {
            $userId    = $_SESSION['user_id'] ?? null;
            $userName  = $_SESSION['name'] ?? null;
            $userEmail = $_SESSION['email'] ?? null;
            $roleId    = $_SESSION['role_id'] ?? null;
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            $requestUri    = $_SERVER['REQUEST_URI'] ?? null;
            $requestMethod = $_SERVER['REQUEST_METHOD'] ?? null;
            $createdAt = date('Y-m-d H:i:s');

            $oldValueJson = $oldValue ? json_encode($oldValue, JSON_UNESCAPED_UNICODE) : null;
            $newValueJson = $newValue ? json_encode($newValue, JSON_UNESCAPED_UNICODE) : null;

            // Compute tamper-detection hash (cast all to string for consistency)
            $rowHash = self::computeHash(
                $userId, $action, $entityType, $entityId,
                $oldValueJson, $newValueJson, $createdAt
            );

            return $this->db->insert('audit_trail', [
                'user_id'        => $userId,
                'user_name'      => $userName,
                'user_email'     => $userEmail,
                'role_id'        => $roleId,
                'action'         => $action,
                'entity_type'    => $entityType,
                'entity_id'      => $entityId,
                'old_value'      => $oldValueJson,
                'new_value'      => $newValueJson,
                'description'    => $description,
                'ip_address'     => $ipAddress,
                'user_agent'     => $userAgent,
                'request_uri'    => $requestUri,
                'request_method' => $requestMethod,
                'row_hash'       => $rowHash,
                'created_at'     => $createdAt
            ]);
        } catch (Exception $e) {
            error_log("AuditTrail log failed: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Build WHERE clause from filters (shared between getAuditTrail and getAuditTrailCount)
     */
    private function buildFilterWhere(array $filters): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['date_from'])) {
            $where[] = 'at.created_at >= ?';
            $params[] = $filters['date_from'] . ' 00:00:00';
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'at.created_at <= ?';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        if (!empty($filters['user_id'])) {
            $where[] = 'at.user_id = ?';
            $params[] = (int) $filters['user_id'];
        }

        if (!empty($filters['action'])) {
            $where[] = 'at.action = ?';
            $params[] = $filters['action'];
        }

        if (!empty($filters['entity_type'])) {
            $where[] = 'at.entity_type = ?';
            $params[] = $filters['entity_type'];
        }

        if (!empty($filters['search'])) {
            $where[] = '(at.description LIKE ? OR at.entity_id LIKE ? OR at.user_name LIKE ? OR at.user_email LIKE ?)';
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        return ['clause' => $whereClause, 'params' => $params];
    }

    /**
     * Get paginated audit trail entries with filters
     */
    public function getAuditTrail(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $filter = $this->buildFilterWhere($filters);
        $params = $filter['params'];

        $sql = "
            SELECT at.*
            FROM audit_trail at
            {$filter['clause']}
            ORDER BY at.created_at DESC
            LIMIT ? OFFSET ?
        ";
        $params[] = $limit;
        $params[] = $offset;

        $result = $this->db->query($sql, $params);
        return $result['success'] ? $result['result'] : [];
    }

    /**
     * Count audit trail entries matching filters (for pagination)
     */
    public function getAuditTrailCount(array $filters = []): int
    {
        $filter = $this->buildFilterWhere($filters);

        $result = $this->db->query(
            "SELECT COUNT(*) as total FROM audit_trail at {$filter['clause']}",
            $filter['params']
        );
        return $result['success'] && !empty($result['result']) ? (int) $result['result'][0]['total'] : 0;
    }

    /**
     * Get distinct entity types for filter dropdown
     */
    public function getEntityTypes(): array
    {
        $result = $this->db->query("SELECT DISTINCT entity_type FROM audit_trail ORDER BY entity_type ASC");
        if ($result['success'] && !empty($result['result'])) {
            return array_column($result['result'], 'entity_type');
        }
        return [];
    }

    /**
     * Get distinct action types for filter dropdown
     */
    public function getActionTypes(): array
    {
        $result = $this->db->query("SELECT DISTINCT action FROM audit_trail ORDER BY action ASC");
        if ($result['success'] && !empty($result['result'])) {
            return array_column($result['result'], 'action');
        }
        return [];
    }

    /**
     * Get summary statistics for the dashboard cards
     */
    public function getSummaryStats(): array
    {
        $today = date('Y-m-d');

        $totalResult = $this->db->query("SELECT COUNT(*) as cnt FROM audit_trail");
        $totalEvents = ($totalResult['success'] && !empty($totalResult['result']))
            ? (int) $totalResult['result'][0]['cnt'] : 0;

        $todayResult = $this->db->query(
            "SELECT COUNT(*) as cnt FROM audit_trail WHERE DATE(created_at) = ?",
            [$today]
        );
        $eventsToday = ($todayResult['success'] && !empty($todayResult['result']))
            ? (int) $todayResult['result'][0]['cnt'] : 0;

        $usersResult = $this->db->query(
            "SELECT COUNT(DISTINCT user_id) as cnt FROM audit_trail WHERE DATE(created_at) = ? AND user_id IS NOT NULL",
            [$today]
        );
        $uniqueUsers = ($usersResult['success'] && !empty($usersResult['result']))
            ? (int) $usersResult['result'][0]['cnt'] : 0;

        $entityResult = $this->db->query(
            "SELECT entity_type, COUNT(*) as cnt FROM audit_trail
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY entity_type ORDER BY cnt DESC LIMIT 1"
        );
        $mostActiveEntity = ($entityResult['success'] && !empty($entityResult['result']))
            ? $entityResult['result'][0]['entity_type'] : 'N/A';

        return [
            'total_events'       => $totalEvents,
            'events_today'       => $eventsToday,
            'unique_users_today' => $uniqueUsers,
            'most_active_entity' => $mostActiveEntity
        ];
    }

    /**
     * Search users for Select2 AJAX dropdown
     */
    public function searchUsers(string $term): array
    {
        $result = $this->db->query(
            "SELECT DISTINCT at.user_id as id, at.user_name as name, at.user_email as email
             FROM audit_trail at
             WHERE at.user_id IS NOT NULL
               AND (at.user_name LIKE ? OR at.user_email LIKE ?)
             ORDER BY at.user_name ASC
             LIMIT 20",
            ['%' . $term . '%', '%' . $term . '%']
        );

        $users = [];
        if ($result['success'] && !empty($result['result'])) {
            foreach ($result['result'] as $row) {
                $users[] = [
                    'id' => $row['id'],
                    'text' => $row['name'] . ' (' . $row['email'] . ')'
                ];
            }
        }
        return $users;
    }

    /**
     * Resolve foreign key IDs in audit trail JSON data to human-readable labels.
     * Does NOT modify stored data — only transforms for display.
     */
    public function resolveIds(?string $jsonString): ?array
    {
        if (!$jsonString) return null;

        $data = json_decode($jsonString, true);
        if (!is_array($data)) return null;

        // Collect all IDs to resolve in bulk
        $branchIds = [];
        $programIds = [];
        $planIds = [];
        $userIds = [];
        $discountIds = [];
        $roleIds = [];
        $pageIds = [];
        $sectionIds = [];
        $subjectIds = [];

        $idFields = [
            'branch_id'       => &$branchIds,
            'program_id'      => &$programIds,
            'payment_plan_id' => &$planIds,
            'received_by'     => &$userIds,
            'voided_by'       => &$userIds,
            'enrolled_by'     => &$userIds,
            'discount_id'     => &$discountIds,
            'role_id'         => &$roleIds,
            'parent_id'       => &$pageIds,
            'section_id'      => &$sectionIds,
            'subject_id'      => &$subjectIds,
        ];

        foreach ($idFields as $field => $ref) {
            if (isset($data[$field]) && $data[$field] !== null && $data[$field] !== '') {
                $ref[] = $data[$field];
            }
        }

        // Bulk resolve each type
        $labels = [];

        if (!empty($branchIds)) {
            $result = $this->db->query("SELECT id, CONCAT(branch_name, ' (', branch_code, ')') AS label FROM set_branch WHERE id IN (" . implode(',', array_map('intval', $branchIds)) . ")");
            if ($result['success']) foreach ($result['result'] as $r) $labels['branch'][$r['id']] = $r['label'];
        }

        if (!empty($programIds)) {
            $result = $this->db->query("SELECT id, CONCAT(name, ' (', code, ')') AS label FROM set_program WHERE id IN (" . implode(',', array_map('intval', $programIds)) . ")");
            if ($result['success']) foreach ($result['result'] as $r) $labels['program'][$r['id']] = $r['label'];
        }

        if (!empty($planIds)) {
            $result = $this->db->query("SELECT id, name AS label FROM set_payment_plan WHERE id IN (" . implode(',', array_map('intval', $planIds)) . ")");
            if ($result['success']) foreach ($result['result'] as $r) $labels['plan'][$r['id']] = $r['label'];
        }

        if (!empty($userIds)) {
            $result = $this->db->query("SELECT id, display_name AS label FROM users WHERE id IN (" . implode(',', array_map('intval', $userIds)) . ")");
            if ($result['success']) foreach ($result['result'] as $r) $labels['user'][$r['id']] = $r['label'];
        }

        if (!empty($discountIds)) {
            $result = $this->db->query("SELECT id, name AS label FROM set_discount WHERE id IN (" . implode(',', array_map('intval', $discountIds)) . ")");
            if ($result['success']) foreach ($result['result'] as $r) $labels['discount'][$r['id']] = $r['label'];
        }

        if (!empty($roleIds)) {
            $result = $this->db->query("SELECT id, display_name AS label FROM roles WHERE id IN (" . implode(',', array_map('intval', $roleIds)) . ")");
            if ($result['success']) foreach ($result['result'] as $r) $labels['role'][$r['id']] = $r['label'];
        }

        if (!empty($pageIds)) {
            $result = $this->db->query("SELECT id, CONCAT(display_name, ' (', name, ')') AS label FROM pages WHERE id IN (" . implode(',', array_map('intval', $pageIds)) . ")");
            if ($result['success']) foreach ($result['result'] as $r) $labels['page'][$r['id']] = $r['label'];
        }

        if (!empty($sectionIds)) {
            $result = $this->db->query("SELECT id, name AS label FROM set_section WHERE id IN (" . implode(',', array_map('intval', $sectionIds)) . ")");
            if ($result['success']) foreach ($result['result'] as $r) $labels['section'][$r['id']] = $r['label'];
        }

        if (!empty($subjectIds)) {
            $result = $this->db->query("SELECT id, CONCAT(code, ' - ', name) AS label FROM set_subject WHERE id IN (" . implode(',', array_map('intval', $subjectIds)) . ")");
            if ($result['success']) foreach ($result['result'] as $r) $labels['subject'][$r['id']] = $r['label'];
        }

        // Apply labels — format as "Label (ID)"
        $fieldToCategory = [
            'branch_id'       => 'branch',
            'program_id'      => 'program',
            'payment_plan_id' => 'plan',
            'received_by'     => 'user',
            'voided_by'       => 'user',
            'enrolled_by'     => 'user',
            'discount_id'     => 'discount',
            'role_id'         => 'role',
            'parent_id'       => 'page',
            'section_id'      => 'section',
            'subject_id'      => 'subject',
        ];

        foreach ($fieldToCategory as $field => $category) {
            if (isset($data[$field]) && $data[$field] !== null && $data[$field] !== '') {
                $id = $data[$field];
                if (isset($labels[$category][$id])) {
                    $data[$field] = $labels[$category][$id] . ' (' . $id . ')';
                }
            }
        }

        // School year fields — convert integer year to "SY YYYY-YYYY+1"
        if (isset($data['school_year_start']) && $data['school_year_start']) {
            $yr = (int)$data['school_year_start'];
            $data['school_year_start'] = 'SY ' . $yr . '-' . ($yr + 1) . ' (' . $yr . ')';
        }
        if (isset($data['school_year_end']) && $data['school_year_end']) {
            $yr = (int)$data['school_year_end'];
            $data['school_year_end'] = 'SY ' . $yr . '-' . ($yr + 1) . ' (' . $yr . ')';
        }

        return $data;
    }

    /**
     * Compute a consistent SHA-256 hash for tamper detection.
     * All values are explicitly cast to string to avoid type-coercion mismatches
     * between write-time (PHP session types) and read-time (MySQL return types).
     */
    private static function computeHash(
        $userId, $action, $entityType, $entityId,
        $oldValueJson, $newValueJson, $createdAt
    ): string {
        $hashPayload = implode('|', [
            (string)($userId ?? ''),
            (string)$action,
            (string)$entityType,
            (string)($entityId ?? ''),
            (string)($oldValueJson ?? ''),
            (string)($newValueJson ?? ''),
            (string)$createdAt
        ]);
        return hash('sha256', $hashPayload);
    }

    /**
     * Verify integrity of a single audit trail row
     */
    public function verifyIntegrity(int $id): array
    {
        $result = $this->db->query("SELECT * FROM audit_trail WHERE id = ? LIMIT 1", [$id]);
        if (!$result['success'] || empty($result['result'])) {
            return ['valid' => false, 'row' => null, 'error' => 'Row not found'];
        }

        $row = $result['result'][0];

        $expectedHash = self::computeHash(
            $row['user_id'], $row['action'], $row['entity_type'], $row['entity_id'],
            $row['old_value'], $row['new_value'], $row['created_at']
        );

        // Also try legacy hash (without explicit string casts) for older records
        $legacyPayload = implode('|', [
            $row['user_id'] ?? '',
            $row['action'],
            $row['entity_type'],
            $row['entity_id'] ?? '',
            $row['old_value'] ?? '',
            $row['new_value'] ?? '',
            $row['created_at']
        ]);
        $legacyHash = hash('sha256', $legacyPayload);

        return [
            'valid' => ($row['row_hash'] === $expectedHash || $row['row_hash'] === $legacyHash),
            'row' => $row
        ];
    }
}
