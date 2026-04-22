<?php

/**
 * Error Handler Class
 * Provides centralized error and exception handling
 */
class ErrorHandler
{
    private static bool $initialized = false;
    private static bool $debug = false;
    private static ?string $logFile = null;

    /**
     * Initialize the error handler
     */
    public static function init(bool $debug = false, ?string $logFile = null): void
    {
        if (self::$initialized) {
            return;
        }

        self::$debug = $debug;
        self::$logFile = $logFile ?? __DIR__ . '/../logs/error.log';

        // Set error reporting
        if ($debug) {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
        } else {
            error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
            ini_set('display_errors', '0');
        }

        // Register handlers
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);

        self::$initialized = true;
    }

    /**
     * Strip absolute base path from a file path for safe logging.
     */
    private static function sanitizePath(string $path): string
    {
        if (defined('BASE_PATH')) {
            $path = str_replace(BASE_PATH, '[app]', $path);
        }
        return $path;
    }

    /**
     * Handle PHP errors
     */
    public static function handleError(int $errno, string $errstr, string $errfile, int $errline): bool
    {
        // Don't handle errors that are suppressed with @
        if (!(error_reporting() & $errno)) {
            return false;
        }

        $errorType = self::getErrorType($errno);
        $safeFile = self::sanitizePath($errfile);
        $message = "$errorType: $errstr in $safeFile on line $errline";

        self::log($message, $errorType);

        // Convert to exception for fatal errors
        if (in_array($errno, [E_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR])) {
            throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
        }

        return true;
    }

    /**
     * Handle uncaught exceptions
     */
    public static function handleException(\Throwable $exception): void
    {
        $message = sprintf(
            "Uncaught %s: %s in %s on line %d\nStack trace:\n%s",
            get_class($exception),
            $exception->getMessage(),
            self::sanitizePath($exception->getFile()),
            $exception->getLine(),
            self::sanitizePath($exception->getTraceAsString())
        );

        self::log($message, 'EXCEPTION');

        // Display error page
        self::displayErrorPage(500, $exception);
    }

    /**
     * Handle fatal errors on shutdown
     */
    public static function handleShutdown(): void
    {
        $error = error_get_last();

        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $message = sprintf(
                "Fatal %s: %s in %s on line %d",
                self::getErrorType($error['type']),
                $error['message'],
                self::sanitizePath($error['file']),
                $error['line']
            );

            self::log($message, 'FATAL');

            // Clear any output
            if (ob_get_level()) {
                ob_end_clean();
            }

            self::displayErrorPage(500);
        }
    }

    /**
     * Log error message
     */
    private static function log(string $message, string $type = 'ERROR'): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
        $uri = $_SERVER['REQUEST_URI'] ?? 'N/A';

        $logMessage = "[$timestamp] [$type] [$ip] [$uri] $message\n";

        // Ensure log directory exists
        $logDir = dirname(self::$logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        error_log($logMessage, 3, self::$logFile);
    }

    /**
     * Display error page
     */
    private static function displayErrorPage(int $code, ?\Throwable $exception = null): void
    {
        // Check if this is an AJAX request
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
                  strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        if ($isAjax || (isset($_POST['action']))) {
            http_response_code($code);
            header('Content-Type: application/json');

            $response = [
                'success' => false,
                'error' => self::$debug && $exception
                    ? $exception->getMessage()
                    : 'An unexpected error occurred. Please try again.'
            ];

            if (self::$debug && $exception) {
                $response['debug'] = [
                    'file' => self::sanitizePath($exception->getFile()),
                    'line' => $exception->getLine(),
                    'trace' => self::sanitizePath($exception->getTraceAsString())
                ];
            }

            echo json_encode($response);
            exit;
        }

        // Regular request - show error page
        http_response_code($code);

        $errorFile = __DIR__ . "/../public/errors/$code.php";
        if (file_exists($errorFile)) {
            include $errorFile;
        } else {
            // Fallback error page
            include __DIR__ . '/../public/errors/500.php';
        }

        exit;
    }

    /**
     * Get error type name
     */
    private static function getErrorType(int $type): string
    {
        $types = [
            E_ERROR             => 'E_ERROR',
            E_WARNING           => 'E_WARNING',
            E_PARSE             => 'E_PARSE',
            E_NOTICE            => 'E_NOTICE',
            E_CORE_ERROR        => 'E_CORE_ERROR',
            E_CORE_WARNING      => 'E_CORE_WARNING',
            E_COMPILE_ERROR     => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING   => 'E_COMPILE_WARNING',
            E_USER_ERROR        => 'E_USER_ERROR',
            E_USER_WARNING      => 'E_USER_WARNING',
            E_USER_NOTICE       => 'E_USER_NOTICE',
            E_STRICT            => 'E_STRICT',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED        => 'E_DEPRECATED',
            E_USER_DEPRECATED   => 'E_USER_DEPRECATED',
        ];

        return $types[$type] ?? 'UNKNOWN';
    }

    /**
     * Manually trigger an error page
     */
    public static function abort(int $code, ?string $message = null): void
    {
        http_response_code($code);

        // Check if AJAX
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
                  strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        if ($isAjax || isset($_POST['action'])) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => $message ?? self::getDefaultMessage($code)
            ]);
            exit;
        }

        $errorFile = __DIR__ . "/../public/errors/$code.php";
        if (file_exists($errorFile)) {
            include $errorFile;
        } else {
            include __DIR__ . '/../public/errors/500.php';
        }
        exit;
    }

    /**
     * Get default error message for code
     */
    private static function getDefaultMessage(int $code): string
    {
        $messages = [
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            408 => 'Request Timeout',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
        ];

        return $messages[$code] ?? 'An error occurred';
    }

    /**
     * Check if debug mode is enabled
     */
    public static function isDebug(): bool
    {
        return self::$debug;
    }
}
