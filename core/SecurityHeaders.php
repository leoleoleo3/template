<?php

/**
 * Security Headers
 *
 * Generates a per-request CSP nonce and sends all OWASP-recommended
 * HTTP security headers. Call SecurityHeaders::init() once in bootstrap
 * before any output is sent.
 */
class SecurityHeaders
{
    private static bool $sent = false;
    private static string $nonce = '';

    /**
     * Generate nonce and send all security headers.
     * Must be called before any output.
     */
    public static function init(): void
    {
        if (self::$sent) {
            return;
        }

        // Generate a cryptographically secure, per-request nonce
        self::$nonce = base64_encode(random_bytes(16));

        self::sendHeaders();
        self::$sent = true;
    }

    /**
     * Return whether init() has already been called for this request.
     */
    public static function isInitialized(): bool
    {
        return self::$sent;
    }

    /**
     * Return the current request's CSP nonce.
     * Returns empty string if init() has not been called.
     */
    public static function nonce(): string
    {
        return self::$nonce;
    }

    /**
     * Send all security headers.
     */
    private static function sendHeaders(): void
    {
        $nonce = self::$nonce;

        // ----------------------------------------------------------------
        // Content-Security-Policy
        // ----------------------------------------------------------------
        // 'strict-dynamic' lets nonce-trusted scripts load other scripts,
        // which is needed by Bootstrap bundle and other libraries.
        // The 'unsafe-inline' fallback is ignored by browsers that support
        // nonces but allows very old browsers to function.
        // ----------------------------------------------------------------
        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}' 'strict-dynamic'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob:",
            "font-src 'self' data:",
            "connect-src 'self'",
            "frame-src 'none'",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "object-src 'none'",
            "worker-src 'none'",
        ]);

        header("Content-Security-Policy: {$csp}");

        // ----------------------------------------------------------------
        // Clickjacking protection
        // ----------------------------------------------------------------
        header('X-Frame-Options: DENY');

        // ----------------------------------------------------------------
        // MIME-type sniffing protection
        // ----------------------------------------------------------------
        header('X-Content-Type-Options: nosniff');

        // ----------------------------------------------------------------
        // Referrer information control
        // ----------------------------------------------------------------
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // ----------------------------------------------------------------
        // Disable sensitive browser features
        // ----------------------------------------------------------------
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()');

        // ----------------------------------------------------------------
        // Cross-Origin isolation (protects against Spectre-class attacks)
        // ----------------------------------------------------------------
        header('Cross-Origin-Resource-Policy: same-origin');

        // ----------------------------------------------------------------
        // Remove PHP fingerprint header
        // ----------------------------------------------------------------
        header_remove('X-Powered-By');
    }
}

/**
 * Global helper — returns the current CSP nonce for use in templates.
 * Usage: nonce="<?= csp_nonce() ?>"
 */
if (!function_exists('csp_nonce')) {
    function csp_nonce(): string
    {
        return SecurityHeaders::nonce();
    }
}
