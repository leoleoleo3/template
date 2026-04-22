<?php

require_once __DIR__ . '/AuditTrailManager.php';

/**
 * Permission Manager Class
 * Handles all permission checking and management operations
 */
class PermissionManager
{
    private DB $db;
    private static ?PermissionManager $instance = null;

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
     * Check if role is superadmin
     */
    public function isSuperAdmin(int $roleId): bool
    {
        $result = $this->db->query(
            "SELECT is_superadmin FROM roles WHERE id = ? AND hidden = 0 LIMIT 1",
            [$roleId]
        );

        return $result['success'] && !empty($result['result']) && $result['result'][0]['is_superadmin'] == 1;
    }

    /**
     * Check if user/role has specific permission for a page
     */
    public function hasPermission(int $roleId, string $pageName, string $permissionName): bool
    {
        // Superadmin has all permissions
        if ($this->isSuperAdmin($roleId)) {
            return true;
        }

        // Check specific permission
        $result = $this->db->query("
            SELECT 1
            FROM role_page_permissions rpp
            JOIN pages p ON rpp.page_id = p.id
            JOIN permission_types pt ON rpp.permission_type_id = pt.id
            WHERE rpp.role_id = ?
              AND p.name = ?
              AND pt.name = ?
              AND rpp.granted = 1
              AND p.hidden = 0
              AND pt.hidden = 0
            LIMIT 1
        ", [$roleId, $pageName, $permissionName]);

        return $result['success'] && !empty($result['result']);
    }

    /**
     * Check if user has permission by route/file
     */
    public function hasPermissionByRoute(int $roleId, string $route, string $permissionName): bool
    {
        // Superadmin has all permissions
        if ($this->isSuperAdmin($roleId)) {
            return true;
        }

        // Check permission by route
        $result = $this->db->query("
            SELECT 1
            FROM role_page_permissions rpp
            JOIN pages p ON rpp.page_id = p.id
            JOIN permission_types pt ON rpp.permission_type_id = pt.id
            WHERE rpp.role_id = ?
              AND p.route = ?
              AND pt.name = ?
              AND rpp.granted = 1
              AND p.hidden = 0
              AND pt.hidden = 0
            LIMIT 1
        ", [$roleId, $route, $permissionName]);

        return $result['success'] && !empty($result['result']);
    }

    /**
     * Get all permissions for a role and page
     */
    public function getPagePermissions(int $roleId, int $pageId): array
    {
        // Superadmin has all permissions
        if ($this->isSuperAdmin($roleId)) {
            $result = $this->db->query("
                SELECT pt.name, pt.display_name, 1 as granted
                FROM permission_types pt
                WHERE pt.hidden = 0
            ");
            return $result['success'] ? $result['result'] : [];
        }

        // Get specific permissions
        $result = $this->db->query("
            SELECT pt.name, pt.display_name, rpp.granted
            FROM permission_types pt
            LEFT JOIN role_page_permissions rpp ON (
                rpp.permission_type_id = pt.id
                AND rpp.role_id = ?
                AND rpp.page_id = ?
            )
            WHERE pt.hidden = 0
            ORDER BY pt.sort_order
        ", [$roleId, $pageId]);

        return $result['success'] ? $result['result'] : [];
    }

    /**
     * Get all permissions for a role (all pages)
     */
    public function getRolePermissions(int $roleId): array
    {
        $result = $this->db->query("
            SELECT
                p.id AS page_id,
                p.name AS page_name,
                p.display_name AS page_display_name,
                pt.id AS permission_type_id,
                pt.name AS permission_name,
                pt.display_name AS permission_display_name,
                COALESCE(rpp.granted, 0) AS granted
            FROM pages p
            CROSS JOIN permission_types pt
            LEFT JOIN role_page_permissions rpp ON (
                rpp.role_id = ?
                AND rpp.page_id = p.id
                AND rpp.permission_type_id = pt.id
            )
            WHERE p.hidden = 0 AND pt.hidden = 0
            ORDER BY p.sort_order, pt.sort_order
        ", [$roleId]);

        return $result['success'] ? $result['result'] : [];
    }

    /**
     * Set permission for a role and page
     */
    public function setPermission(int $roleId, int $pageId, int $permissionTypeId, bool $granted): array
    {
        // Check if permission already exists
        $existing = $this->db->query("
            SELECT id FROM role_page_permissions
            WHERE role_id = ? AND page_id = ? AND permission_type_id = ?
            LIMIT 1
        ", [$roleId, $pageId, $permissionTypeId]);

        if ($existing['success'] && !empty($existing['result'])) {
            // Update existing permission
            return $this->db->update('role_page_permissions', [
                'granted' => $granted ? 1 : 0,
                'updated_at' => date('Y-m-d H:i:s')
            ], [
                'role_id' => $roleId,
                'page_id' => $pageId,
                'permission_type_id' => $permissionTypeId
            ]);
        } else {
            // Insert new permission
            return $this->db->insert('role_page_permissions', [
                'role_id' => $roleId,
                'page_id' => $pageId,
                'permission_type_id' => $permissionTypeId,
                'granted' => $granted ? 1 : 0
            ]);
        }
    }

    /**
     * Set multiple permissions at once
     */
    public function setPermissions(int $roleId, array $permissions): array
    {
        $errors = [];
        $success = 0;

        foreach ($permissions as $permission) {
            $result = $this->setPermission(
                $roleId,
                $permission['page_id'],
                $permission['permission_type_id'],
                $permission['granted']
            );

            if ($result['success']) {
                $success++;
            } else {
                $errors[] = $result['error'] ?? 'Unknown error';
            }
        }

        return [
            'success' => empty($errors),
            'updated' => $success,
            'errors' => $errors
        ];
    }

    /**
     * Remove all permissions for a role
     */
    public function clearRolePermissions(int $roleId): array
    {
        return $this->db->delete('role_page_permissions', ['role_id' => $roleId]);
    }

    /**
     * Get all permission types
     */
    public function getPermissionTypes(): array
    {
        $result = $this->db->select('permission_types', '*', [], 'ORDER BY sort_order');
        return $result['success'] ? $result['result'] : [];
    }

    /**
     * Get permission type by name
     */
    public function getPermissionTypeByName(string $name): ?array
    {
        $result = $this->db->select('permission_types', '*', ['name' => $name]);
        return $result['success'] && !empty($result['result']) ? $result['result'][0] : null;
    }

    /**
     * Require permission or exit with 403
     * Handles AJAX requests by returning JSON
     */
    public function requirePermission(int $roleId, string $pageName, string $permissionName): void
    {
        if (!$this->hasPermission($roleId, $pageName, $permissionName)) {
            $this->denyAccess("You don't have permission to $permissionName this page");
        }
    }

    /**
     * Require permission by route or exit with 403
     * Handles AJAX requests by returning JSON
     */
    public function requirePermissionByRoute(int $roleId, string $route, string $permissionName): void
    {
        if (!$this->hasPermissionByRoute($roleId, $route, $permissionName)) {
            $this->denyAccess("You don't have permission to access this page");
        }
    }

    /**
     * Deny access with proper error handling
     * Shows 403 page for regular requests, JSON for AJAX
     */
    private function denyAccess(string $message = "Access denied"): void
    {
        // Check if this is an AJAX request
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
                   strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
                  ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']));

        if ($isAjax) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => $message
            ]);
            exit;
        }

        // Regular request - show 403 error page
        http_response_code(403);
        $errorFile = __DIR__ . '/../public/errors/403.php';
        if (file_exists($errorFile)) {
            include $errorFile;
        } else {
            die("403 Forbidden - $message");
        }
        exit;
    }

    /**
     * Log permission change for audit
     */
    public function logPermissionChange(int $userId, string $action, string $entityType, int $entityId, ?array $oldValue = null, ?array $newValue = null): void
    {
        try {
            // Existing: insert into permission_audit_log (kept for backward compatibility)
            $this->db->insert('permission_audit_log', [
                'user_id' => $userId,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'old_value' => $oldValue ? json_encode($oldValue) : null,
                'new_value' => $newValue ? json_encode($newValue) : null,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);

            // Bridge to unified audit trail
            $auditManager = AuditTrailManager::getInstance($this->db);
            $auditManager->log(
                $action,
                $entityType,
                (string) $entityId,
                $oldValue,
                $newValue,
                ucfirst($action) . ' ' . $entityType . ' #' . $entityId
            );
        } catch (Exception $e) {
            // Log silently to avoid breaking application
            error_log("Permission audit log failed: " . $e->getMessage());
        }
    }

    /**
     * Get audit logs
     */
    public function getAuditLogs(int $limit = 100, int $offset = 0): array
    {
        $result = $this->db->query("
            SELECT
                pal.*,
                u.name AS user_name,
                u.email AS user_email
            FROM permission_audit_log pal
            LEFT JOIN users u ON pal.user_id = u.id
            ORDER BY pal.created_at DESC
            LIMIT ? OFFSET ?
        ", [$limit, $offset]);

        return $result['success'] ? $result['result'] : [];
    }
}
