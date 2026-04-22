<?php

/**
 * Role Manager Class
 * Handles role CRUD operations and role-related queries
 */
class RoleManager
{
    private DB $db;
    private static ?RoleManager $instance = null;

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
     * Get all roles
     */
    public function getAllRoles(bool $includeHidden = false): array
    {
        $result = $this->db->select('roles', '*', [], 'ORDER BY is_superadmin DESC, name ASC', $includeHidden);
        return $result['success'] ? $result['result'] : [];
    }

    /**
     * Get role by ID
     */
    public function getRoleById(int $id): ?array
    {
        $result = $this->db->select('roles', '*', ['id' => $id]);
        return $result['success'] && !empty($result['result']) ? $result['result'][0] : null;
    }

    /**
     * Get role by name
     */
    public function getRoleByName(string $name): ?array
    {
        $result = $this->db->select('roles', '*', ['name' => $name]);
        return $result['success'] && !empty($result['result']) ? $result['result'][0] : null;
    }

    /**
     * Create new role
     */
    public function createRole(array $data): array
    {
        // Validate required fields
        if (empty($data['name']) || empty($data['display_name'])) {
            return [
                'success' => false,
                'error' => 'Role name and display name are required'
            ];
        }

        // Check if role name already exists
        $existing = $this->getRoleByName($data['name']);
        if ($existing) {
            return [
                'success' => false,
                'error' => 'Role name already exists'
            ];
        }

        // Insert role
        $roleData = [
            'name' => strtolower(trim($data['name'])),
            'display_name' => trim($data['display_name']),
            'description' => $data['description'] ?? null,
            'is_superadmin' => isset($data['is_superadmin']) ? (int)$data['is_superadmin'] : 0,
            'hidden' => 0
        ];

        return $this->db->insert('roles', $roleData);
    }

    /**
     * Update role
     */
    public function updateRole(int $id, array $data): array
    {
        // Check if role exists
        $role = $this->getRoleById($id);
        if (!$role) {
            return [
                'success' => false,
                'error' => 'Role not found'
            ];
        }

        // Prevent modifying superadmin role (ID 1)
        if ($id === 1 && isset($data['is_superadmin']) && $data['is_superadmin'] == 0) {
            return [
                'success' => false,
                'error' => 'Cannot modify superadmin role permissions'
            ];
        }

        // If changing name, check for duplicates
        if (isset($data['name']) && $data['name'] !== $role['name']) {
            $existing = $this->getRoleByName($data['name']);
            if ($existing) {
                return [
                    'success' => false,
                    'error' => 'Role name already exists'
                ];
            }
        }

        // Build update data
        $updateData = [];
        if (isset($data['name'])) $updateData['name'] = strtolower(trim($data['name']));
        if (isset($data['display_name'])) $updateData['display_name'] = trim($data['display_name']);
        if (isset($data['description'])) $updateData['description'] = trim($data['description']);
        if (isset($data['is_superadmin'])) $updateData['is_superadmin'] = (int)$data['is_superadmin'];

        if (empty($updateData)) {
            return [
                'success' => false,
                'error' => 'No data to update'
            ];
        }

        $updateData['updated_at'] = date('Y-m-d H:i:s');

        return $this->db->update('roles', $updateData, ['id' => $id]);
    }

    /**
     * Delete role (soft delete)
     */
    public function deleteRole(int $id): array
    {
        // Check if role exists
        $role = $this->getRoleById($id);
        if (!$role) {
            return [
                'success' => false,
                'error' => 'Role not found'
            ];
        }

        // Prevent deleting superadmin role (ID 1)
        if ($id === 1) {
            return [
                'success' => false,
                'error' => 'Cannot delete superadmin role'
            ];
        }

        // Check if any users have this role
        $usersResult = $this->db->query(
            "SELECT COUNT(*) as count FROM users WHERE role_id = ? AND hidden = 0",
            [$id]
        );

        if ($usersResult['success'] && !empty($usersResult['result']) && $usersResult['result'][0]['count'] > 0) {
            return [
                'success' => false,
                'error' => 'Cannot delete role that is assigned to users. Please reassign users first.'
            ];
        }

        // Soft delete
        return $this->db->softDelete('roles', ['id' => $id]);
    }

    /**
     * Hard delete role (permanent)
     */
    public function hardDeleteRole(int $id): array
    {
        // Prevent deleting superadmin role
        if ($id === 1) {
            return [
                'success' => false,
                'error' => 'Cannot delete superadmin role'
            ];
        }

        // Delete role and cascade will delete permissions
        return $this->db->delete('roles', ['id' => $id]);
    }

    /**
     * Get role count
     */
    public function getRoleCount(bool $includeHidden = false): int
    {
        $where = $includeHidden ? '' : 'WHERE hidden = 0';
        $result = $this->db->query("SELECT COUNT(*) as count FROM roles $where");
        return $result['success'] && !empty($result['result']) ? (int)$result['result'][0]['count'] : 0;
    }

    /**
     * Get users by role
     */
    public function getUsersByRole(int $roleId): array
    {
        $result = $this->db->query("
            SELECT id, name, email, created_at
            FROM users
            WHERE role_id = ? AND hidden = 0
            ORDER BY name ASC
        ", [$roleId]);

        return $result['success'] ? $result['result'] : [];
    }

    /**
     * Get user count by role
     */
    public function getUserCountByRole(int $roleId): int
    {
        $result = $this->db->query(
            "SELECT COUNT(*) as count FROM users WHERE role_id = ? AND hidden = 0",
            [$roleId]
        );
        return $result['success'] && !empty($result['result']) ? (int)$result['result'][0]['count'] : 0;
    }

    /**
     * Duplicate role with permissions
     */
    public function duplicateRole(int $sourceRoleId, string $newName, string $newDisplayName): array
    {
        // Get source role
        $sourceRole = $this->getRoleById($sourceRoleId);
        if (!$sourceRole) {
            return [
                'success' => false,
                'error' => 'Source role not found'
            ];
        }

        // Create new role
        $newRoleResult = $this->createRole([
            'name' => $newName,
            'display_name' => $newDisplayName,
            'description' => "Copy of {$sourceRole['display_name']}",
            'is_superadmin' => 0
        ]);

        if (!$newRoleResult['success']) {
            return $newRoleResult;
        }

        $newRoleId = $newRoleResult['insert_id'];

        // Copy permissions
        $copyResult = $this->db->query("
            INSERT INTO role_page_permissions (role_id, page_id, permission_type_id, granted)
            SELECT ?, page_id, permission_type_id, granted
            FROM role_page_permissions
            WHERE role_id = ?
        ", [$newRoleId, $sourceRoleId]);

        if (!$copyResult['success']) {
            // Rollback: delete the created role
            $this->hardDeleteRole($newRoleId);
            return [
                'success' => false,
                'error' => 'Failed to copy permissions'
            ];
        }

        return [
            'success' => true,
            'role_id' => $newRoleId,
            'message' => 'Role duplicated successfully'
        ];
    }

    /**
     * Restore soft-deleted role
     */
    public function restoreRole(int $id): array
    {
        return $this->db->update('roles', ['hidden' => 0], ['id' => $id]);
    }

    /**
     * Get role statistics
     */
    public function getRoleStats(): array
    {
        $result = $this->db->query("
            SELECT
                r.id,
                r.name,
                r.display_name,
                r.is_superadmin,
                COUNT(DISTINCT u.id) as user_count,
                COUNT(DISTINCT rpp.id) as permission_count
            FROM roles r
            LEFT JOIN users u ON r.id = u.role_id AND u.hidden = 0
            LEFT JOIN role_page_permissions rpp ON r.id = rpp.role_id
            WHERE r.hidden = 0
            GROUP BY r.id
            ORDER BY r.is_superadmin DESC, r.name ASC
        ");

        return $result['success'] ? $result['result'] : [];
    }
}
