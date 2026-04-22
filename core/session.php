<?php

/**
 * Session Management Class
 * Handles user authentication, session lifecycle, and security
 */
class Session
{
    private static ?Session $instance = null;
    private DB $db;
    private const SESSION_TIMEOUT = 1800; // 30 minutes
    private const CSRF_TOKEN_NAME = '_csrf_token';

    /**
     * Private constructor - use getInstance() instead
     */
    private function __construct(DB $db)
    {
        $this->db = $db;
        $this->initializeSession();
    }

    /**
     * Get or create singleton instance
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
     * Initialize session with security settings
     */
    private function initializeSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            // Session security configuration — must be set BEFORE session_start()
            ini_set('session.use_only_cookies', '1');
            ini_set('session.use_strict_mode', '1');
            ini_set('session.cookie_httponly', '1');
            ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? '1' : '0');
            ini_set('session.cookie_samesite', 'Strict');
            ini_set('session.use_trans_sid', '0');
            ini_set('session.sid_length', '48');
            ini_set('session.sid_bits_per_character', '6');
            session_start();
        }

        // Validate session integrity
        $this->validateSessionIntegrity();
    }

    /**
     * Check if user is authenticated
     */
    public function isAuthenticated(): bool
    {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }

    /**
     * Get current user ID
     */
    public function getUserId(): ?int
    {
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Get current user data
     */
    public function getUser(): ?array
    {
        if (!$this->isAuthenticated()) {
            return null;
        }

        return [
            'id' => $_SESSION['user_id'] ?? null,
            'name' => $_SESSION['name'] ?? null,
            'email' => $_SESSION['email'] ?? null,
            'role_id' => $_SESSION['role_id'] ?? null,
        ];
    }

    /**
     * Get user by specific key
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Set session value
     */
    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    /**
     * Check if session key exists
     */
    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    /**
     * Remove session key
     */
    public function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /**
     * Authenticate user and start session
     */
    public function login(array $user): void
    {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['role_id'] = $user['role_id'] ?? 1;
        $_SESSION['login_time'] = time();
        $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];

        // Regenerate session ID for security
        session_regenerate_id(true);

        // Generate CSRF token
        $this->generateCSRFToken();

        // Log login activity
        $this->logActivity('login', $user['id'], "User logged in");
    }

    /**
     * Logout user
     */
    public function logout(): void
    {
        $user_id = $_SESSION['user_id'] ?? null;

        if ($user_id) {
            $this->logActivity('logout', $user_id, "User logged out");
        }

        // Destroy session
        $_SESSION = [];
        session_destroy();

        // Regenerate session ID after destroy
        session_start();
        session_regenerate_id(true);
    }

    /**
     * Check if session has timed out
     */
    public function hasTimedOut(): bool
    {
        if (!isset($_SESSION['login_time'])) {
            return false;
        }

        return (time() - $_SESSION['login_time']) > self::SESSION_TIMEOUT;
    }

    /**
     * Extend session timeout
     */
    public function extendTimeout(): void
    {
        if ($this->isAuthenticated()) {
            $_SESSION['login_time'] = time();
        }
    }

    /**
     * Check if current request is an AJAX request
     */
    public function isAjaxRequest(): bool
    {
        // Check for XMLHttpRequest header
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            return true;
        }

        // Check for common AJAX patterns (POST with action parameter)
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
            return true;
        }

        // Check Accept header for JSON
        if (!empty($_SERVER['HTTP_ACCEPT']) &&
            strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
            return true;
        }

        return false;
    }

    /**
     * Send JSON response and exit (for AJAX error handling)
     */
    public function sendJsonResponse(array $data, int $httpCode = 200): void
    {
        http_response_code($httpCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * Send authentication error (handles both AJAX and regular requests)
     */
    public function sendAuthError(string $message = 'Session expired', string $redirect_to = 'login.php'): void
    {
        if ($this->isAjaxRequest()) {
            $this->sendJsonResponse([
                'success' => false,
                'error' => $message . '. Please refresh the page and log in again.',
                'redirect' => $redirect_to
            ], 401);
        } else {
            http_response_code(401);
            $errorFile = __DIR__ . '/../public/errors/401.php';
            if (file_exists($errorFile)) {
                include $errorFile;
            } else {
                die('401 Unauthorized');
            }
            exit;
        }
    }

    /**
     * Validate session integrity
     */
    private function validateSessionIntegrity(): void
    {
        if (!$this->isAuthenticated()) {
            return;
        }

        // Check if session has timed out
        if ($this->hasTimedOut()) {
            $this->logout();
            $this->sendAuthError('Session expired', 'login.php?expired=1');
        }

        // Validate IP address (optional - can be strict)
        if (isset($_SESSION['ip_address']) && $_SESSION['ip_address'] !== $_SERVER['REMOTE_ADDR']) {
            // Log suspicious activity
            $this->logActivity('security', $_SESSION['user_id'] ?? null, "IP address mismatch detected");
            // Optionally logout: $this->logout();
        }

        // Validate User Agent (optional - can be disabled for development)
        if (isset($_SESSION['user_agent']) && $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
            // Log suspicious activity
            $this->logActivity('security', $_SESSION['user_id'] ?? null, "User agent mismatch detected");
        }
    }

    /**
     * Generate CSRF token
     */
    public function generateCSRFToken(): string
    {
        if (!isset($_SESSION[self::CSRF_TOKEN_NAME])) {
            $_SESSION[self::CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::CSRF_TOKEN_NAME];
    }

    /**
     * Get CSRF token (generates if not exists)
     */
    public function getCSRFToken(): string
    {
        if (!isset($_SESSION[self::CSRF_TOKEN_NAME])) {
            $this->generateCSRFToken();
        }
        return $_SESSION[self::CSRF_TOKEN_NAME];
    }

    /**
     * Verify CSRF token
     */
    public function verifyCSRFToken(string $token): bool
    {
        if (!isset($_SESSION[self::CSRF_TOKEN_NAME])) {
            return false;
        }

        return hash_equals($_SESSION[self::CSRF_TOKEN_NAME], $token);
    }

    /**
     * Check if user has specific role
     */
    public function hasRole(int|array $role_id): bool
    {
        if (!$this->isAuthenticated()) {
            return false;
        }

        if (is_array($role_id)) {
            return in_array($_SESSION['role_id'] ?? null, $role_id);
        }

        return ($_SESSION['role_id'] ?? null) === $role_id;
    }

    /**
     * Check if user has permission
     */
    public function hasPermission(string $permission): bool
    {
        if (!$this->isAuthenticated()) {
            return false;
        }

        $result = $this->db->query(
            "SELECT 1 FROM role_permissions WHERE role_id = ? AND permission = ? LIMIT 1",
            [$_SESSION['role_id'], $permission]
        );

        return $result['success'] && !empty($result['result']);
    }



    /**
     * Log activity
     */
    private function logActivity(string $activity_type, ?int $user_id, string $description): void
    {
        try {
            // Existing: insert into user_activity_logs (kept for backward compatibility)
            $this->db->insert('user_activity_logs', [
                'user_id' => $user_id,
                'activity_type' => $activity_type,
                'description' => $description,
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'created_at' => date('Y-m-d H:i:s')
            ]);

            // Bridge to unified audit trail
            require_once __DIR__ . '/AuditTrailManager.php';
            $auditManager = AuditTrailManager::getInstance($this->db);
            $auditManager->log(
                $activity_type,
                'session',
                $user_id ? (string) $user_id : null,
                null,
                null,
                $description
            );
        } catch (Exception $e) {
            // Log silently to avoid breaking application
        }
    }

    /**
     * Get all session data (for debugging)
     */
    public function getAllData(): array
    {
        return $_SESSION;
    }

    /**
     * Clear all session data
     */
    public function clear(): void
    {
        $_SESSION = [];
    }

    /**
     * Require authentication - redirect to login if not authenticated
     * Automatically handles AJAX requests by returning JSON
     */
    public function requireLogin(string $redirect_to = 'login.php'): void
    {
        if (!$this->isAuthenticated()) {
            $this->sendAuthError('Authentication required', $redirect_to);
        }
        // Extend session timeout on each authenticated request
        $this->extendTimeout();
    }

    /**
     * Require specific role - show 401 error page if user doesn't have role
     * Automatically handles AJAX requests by returning JSON
     */
    public function requireRole(int|array $role_id): void
    {
        if (!$this->hasRole($role_id)) {
            if ($this->isAjaxRequest()) {
                $this->sendJsonResponse([
                    'success' => false,
                    'error' => 'You do not have the required role to perform this action.'
                ], 401);
            }
            http_response_code(401);
            $errorFile = __DIR__ . '/../public/errors/401.php';
            if (file_exists($errorFile)) {
                include $errorFile;
            } else {
                die('401 Unauthorized');
            }
            exit;
        }
    }

    /**
     * Require specific permission - show 403 error page if not permitted
     * Automatically handles AJAX requests by returning JSON
     */
    public function requirePermission(string $permission): void
    {
        if (!$this->hasPermission($permission)) {
            if ($this->isAjaxRequest()) {
                $this->sendJsonResponse([
                    'success' => false,
                    'error' => 'You do not have permission to perform this action.'
                ], 403);
            }
            http_response_code(403);
            $errorFile = __DIR__ . '/../public/errors/403.php';
            if (file_exists($errorFile)) {
                include $errorFile;
            } else {
                die('403 Forbidden');
            }
            exit;
        }
    }

    /**
     * Require CSRF token validation
     * Automatically handles AJAX requests by returning JSON
     */
    public function requireCSRFToken(?string $token = null): void
    {
        $token = $token ?? ($_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '');

        if (!$this->verifyCSRFToken($token)) {
            if ($this->isAjaxRequest()) {
                $this->sendJsonResponse([
                    'success' => false,
                    'error' => 'Invalid CSRF token. Please refresh the page and try again.'
                ], 403);
            }
            http_response_code(403);
            die('403 Forbidden - Invalid CSRF token');
        }
    }
}
