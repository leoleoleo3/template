<?php

/**
 * Account Manager Class
 * Handles user account CRUD operations, approval workflow, and status management
 */
class AccountManager
{
    private DB $db;
    private static ?AccountManager $instance = null;

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
     * Get all users with optional filtering
     */
    public function getAllUsers(array $filters = [], bool $includeHidden = false): array
    {
        $where = $includeHidden ? '1=1' : 'u.hidden = 0';
        $params = [];

        // Filter by status
        if (!empty($filters['status'])) {
            $where .= ' AND u.status = ?';
            $params[] = $filters['status'];
        }

        // Filter by role
        if (!empty($filters['role_id'])) {
            $where .= ' AND u.role_id = ?';
            $params[] = $filters['role_id'];
        }

        // Filter by search term
        if (!empty($filters['search'])) {
            $where .= ' AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)';
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        $result = $this->db->query("
            SELECT
                u.id,
                u.first_name,
                u.middle_name,
                u.last_name,
                u.display_name,
                u.email,
                u.phone,
                u.role_id,
                r.name AS role_name,
                r.display_name AS role_display_name,
                u.status,
                u.email_verified,
                u.is_active,
                u.last_login_at,
                u.created_at,
                u.updated_at
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            WHERE $where
            ORDER BY u.created_at DESC
        ", $params);

        return $result['success'] ? $result['result'] : [];
    }

    /**
     * Get user by ID
     */
    public function getUserById(int $id): ?array
    {
        $result = $this->db->query("
            SELECT
                u.*,
                r.name AS role_name,
                r.display_name AS role_display_name
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            WHERE u.id = ?
        ", [$id]);

        return $result['success'] && !empty($result['result']) ? $result['result'][0] : null;
    }

    /**
     * Get user by email
     */
    public function getUserByEmail(string $email): ?array
    {
        $result = $this->db->select('users', '*', ['email' => $email]);
        return $result['success'] && !empty($result['result']) ? $result['result'][0] : null;
    }

    /**
     * Create new user account
     */
    public function createUser(array $data): array
    {
        // Validate required fields
        if (empty($data['first_name']) || empty($data['last_name']) || empty($data['email'])) {
            return [
                'success' => false,
                'error' => 'First name, last name, and email are required'
            ];
        }

        // Validate email format
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'error' => 'Invalid email format'
            ];
        }

        // Check if email already exists
        $existing = $this->getUserByEmail($data['email']);
        if ($existing) {
            return [
                'success' => false,
                'error' => 'Email address already exists'
            ];
        }

        // Generate password if not provided
        $password = $data['password'] ?? $this->generateRandomPassword();
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

        // Determine initial status
        $status = $data['status'] ?? 'active';
        if (!in_array($status, ['active', 'inactive', 'suspended', 'pending'])) {
            $status = 'active';
        }

        // Check for soft-deleted user with same email — restore instead of duplicate
        $deletedUser = $this->db->select('users', '*', ['email' => strtolower(trim($data['email']))], '', true);
        if (!empty($deletedUser['result'])) {
            $oldUser = $deletedUser['result'][0];
            $updateResult = $this->db->update('users', [
                'first_name'     => trim($data['first_name']),
                'middle_name'    => !empty($data['middle_name']) ? trim($data['middle_name']) : null,
                'last_name'      => trim($data['last_name']),
                'password'       => $hashedPassword,
                'role_id'        => (int)($data['role_id'] ?? 3),
                'phone'          => $data['phone'] ?? null,
                'status'         => $status,
                'is_active'      => $status === 'active' ? 1 : 0,
                'email_verified' => isset($data['email_verified']) ? (int)$data['email_verified'] : 0,
                'hidden'         => 0,
                'deleted_at'     => null,
                'updated_at'     => date('Y-m-d H:i:s'),
            ], ['id' => $oldUser['id']]);

            if ($updateResult['success']) {
                $updateResult['insert_id'] = $oldUser['id'];
                $updateResult['generated_password'] = isset($data['password']) ? null : $password;
            }
            return $updateResult;
        }

        // Build user data
        $userData = [
            'first_name' => trim($data['first_name']),
            'middle_name' => !empty($data['middle_name']) ? trim($data['middle_name']) : null,
            'last_name' => trim($data['last_name']),
            'email' => strtolower(trim($data['email'])),
            'password' => $hashedPassword,
            'role_id' => (int)($data['role_id'] ?? 3),
            'phone' => $data['phone'] ?? null,
            'status' => $status,
            'is_active' => $status === 'active' ? 1 : 0,
            'email_verified' => isset($data['email_verified']) ? (int)$data['email_verified'] : 0,
            'hidden' => 0
        ];

        $result = $this->db->insert('users', $userData);

        if ($result['success']) {
            $result['generated_password'] = isset($data['password']) ? null : $password;
        }

        return $result;
    }

    /**
     * Update user account
     */
    public function updateUser(int $id, array $data): array
    {
        // Check if user exists
        $user = $this->getUserById($id);
        if (!$user) {
            return [
                'success' => false,
                'error' => 'User not found'
            ];
        }

        // If changing email, check for duplicates
        if (isset($data['email']) && strtolower($data['email']) !== strtolower($user['email'])) {
            $existing = $this->getUserByEmail($data['email']);
            if ($existing) {
                return [
                    'success' => false,
                    'error' => 'Email address already exists'
                ];
            }
        }

        // Build update data
        $updateData = [];
        if (isset($data['first_name'])) $updateData['first_name'] = trim($data['first_name']);
        if (isset($data['middle_name'])) $updateData['middle_name'] = trim($data['middle_name']) ?: null;
        if (isset($data['last_name'])) $updateData['last_name'] = trim($data['last_name']);
        if (isset($data['email'])) $updateData['email'] = strtolower(trim($data['email']));
        if (isset($data['phone'])) $updateData['phone'] = trim($data['phone']) ?: null;
        if (isset($data['role_id'])) $updateData['role_id'] = (int)$data['role_id'];
        if (isset($data['status'])) {
            $updateData['status'] = $data['status'];
            $updateData['is_active'] = ($data['status'] === 'active') ? 1 : 0;
        }
        if (isset($data['email_verified'])) $updateData['email_verified'] = (int)$data['email_verified'];

        // Update password if provided
        if (!empty($data['password'])) {
            $updateData['password'] = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        }

        if (empty($updateData)) {
            return [
                'success' => false,
                'error' => 'No data to update'
            ];
        }

        $updateData['updated_at'] = date('Y-m-d H:i:s');

        return $this->db->update('users', $updateData, ['id' => $id]);
    }

    /**
     * Delete user (soft delete)
     */
    public function deleteUser(int $id): array
    {
        $user = $this->getUserById($id);
        if (!$user) {
            return [
                'success' => false,
                'error' => 'User not found'
            ];
        }

        // Prevent deleting own account or superadmin (user ID 1)
        if ($id === 1) {
            return [
                'success' => false,
                'error' => 'Cannot delete the primary superadmin account'
            ];
        }

        return $this->db->softDelete('users', ['id' => $id]);
    }

    /**
     * Approve pending user
     */
    public function approveUser(int $id, int $approvedBy = null): array
    {
        $user = $this->getUserById($id);
        if (!$user) {
            return [
                'success' => false,
                'error' => 'User not found'
            ];
        }

        if ($user['status'] !== 'pending') {
            return [
                'success' => false,
                'error' => 'User is not in pending status'
            ];
        }

        $result = $this->db->update('users', [
            'status' => 'active',
            'is_active' => 1,
            'updated_at' => date('Y-m-d H:i:s')
        ], ['id' => $id]);

        if ($result['success']) {
            $result['message'] = 'User approved successfully';
        }

        return $result;
    }

    /**
     * Reject pending user (soft delete)
     */
    public function rejectUser(int $id, string $reason = null): array
    {
        $user = $this->getUserById($id);
        if (!$user) {
            return [
                'success' => false,
                'error' => 'User not found'
            ];
        }

        if ($user['status'] !== 'pending') {
            return [
                'success' => false,
                'error' => 'User is not in pending status'
            ];
        }

        // Soft delete the rejected user
        return $this->db->softDelete('users', ['id' => $id]);
    }

    /**
     * Suspend user account
     */
    public function suspendUser(int $id, string $reason = null): array
    {
        $user = $this->getUserById($id);
        if (!$user) {
            return [
                'success' => false,
                'error' => 'User not found'
            ];
        }

        // Prevent suspending superadmin
        if ($id === 1) {
            return [
                'success' => false,
                'error' => 'Cannot suspend the primary superadmin account'
            ];
        }

        return $this->db->update('users', [
            'status' => 'suspended',
            'is_active' => 0,
            'updated_at' => date('Y-m-d H:i:s')
        ], ['id' => $id]);
    }

    /**
     * Activate user account
     */
    public function activateUser(int $id): array
    {
        $user = $this->getUserById($id);
        if (!$user) {
            return [
                'success' => false,
                'error' => 'User not found'
            ];
        }

        return $this->db->update('users', [
            'status' => 'active',
            'is_active' => 1,
            'updated_at' => date('Y-m-d H:i:s')
        ], ['id' => $id]);
    }

    /**
     * Deactivate user account
     */
    public function deactivateUser(int $id): array
    {
        $user = $this->getUserById($id);
        if (!$user) {
            return [
                'success' => false,
                'error' => 'User not found'
            ];
        }

        // Prevent deactivating superadmin
        if ($id === 1) {
            return [
                'success' => false,
                'error' => 'Cannot deactivate the primary superadmin account'
            ];
        }

        return $this->db->update('users', [
            'status' => 'inactive',
            'is_active' => 0,
            'updated_at' => date('Y-m-d H:i:s')
        ], ['id' => $id]);
    }

    /**
     * Reset user password
     */
    public function resetPassword(int $id, string $newPassword = null): array
    {
        $user = $this->getUserById($id);
        if (!$user) {
            return [
                'success' => false,
                'error' => 'User not found'
            ];
        }

        // Generate new password if not provided
        $password = $newPassword ?? $this->generateRandomPassword();
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

        $result = $this->db->update('users', [
            'password' => $hashedPassword,
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'updated_at' => date('Y-m-d H:i:s')
        ], ['id' => $id]);

        if ($result['success']) {
            $result['new_password'] = $password;
        }

        return $result;
    }

    /**
     * Unlock user account
     */
    public function unlockAccount(int $id): array
    {
        $user = $this->getUserById($id);
        if (!$user) {
            return [
                'success' => false,
                'error' => 'User not found'
            ];
        }

        return $this->db->update('users', [
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'updated_at' => date('Y-m-d H:i:s')
        ], ['id' => $id]);
    }

    /**
     * Get pending users count
     */
    public function getPendingCount(): int
    {
        $result = $this->db->query(
            "SELECT COUNT(*) as count FROM users WHERE status = 'pending' AND hidden = 0"
        );
        return $result['success'] && !empty($result['result']) ? (int)$result['result'][0]['count'] : 0;
    }

    /**
     * Get user statistics
     */
    public function getUserStats(): array
    {
        $result = $this->db->query("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive,
                SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) as suspended,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN email_verified = 1 THEN 1 ELSE 0 END) as verified,
                SUM(CASE WHEN locked_until IS NOT NULL AND locked_until > NOW() THEN 1 ELSE 0 END) as locked
            FROM users
            WHERE hidden = 0
        ");

        return $result['success'] && !empty($result['result']) ? $result['result'][0] : [
            'total' => 0,
            'active' => 0,
            'inactive' => 0,
            'suspended' => 0,
            'pending' => 0,
            'verified' => 0,
            'locked' => 0
        ];
    }

    /**
     * Get users by status
     */
    public function getUsersByStatus(string $status): array
    {
        return $this->getAllUsers(['status' => $status]);
    }

    /**
     * Generate random password
     */
    private function generateRandomPassword(int $length = 12): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';
        $charsLength = strlen($chars);

        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, $charsLength - 1)];
        }

        return $password;
    }

    /**
     * Restore soft-deleted user
     */
    public function restoreUser(int $id): array
    {
        return $this->db->update('users', [
            'hidden' => 0,
            'deleted_at' => null,
            'updated_at' => date('Y-m-d H:i:s')
        ], ['id' => $id]);
    }

    /**
     * Get users with role statistics
     */
    public function getUsersWithRoleStats(): array
    {
        $result = $this->db->query("
            SELECT
                r.id AS role_id,
                r.display_name AS role_name,
                COUNT(u.id) AS user_count,
                SUM(CASE WHEN u.status = 'active' THEN 1 ELSE 0 END) AS active_count,
                SUM(CASE WHEN u.status = 'pending' THEN 1 ELSE 0 END) AS pending_count
            FROM roles r
            LEFT JOIN users u ON r.id = u.role_id AND u.hidden = 0
            WHERE r.hidden = 0
            GROUP BY r.id, r.display_name
            ORDER BY r.is_superadmin DESC, r.name ASC
        ");

        return $result['success'] ? $result['result'] : [];
    }
}
