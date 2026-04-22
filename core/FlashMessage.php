<?php
/**
 * Flash Message Helper
 *
 * Manages flash messages that display once and are automatically cleared.
 * Works with the SweetAlert2-based notification system.
 */

class FlashMessage
{
    private const SESSION_KEY = 'flash_messages';

    /**
     * Set a flash message
     *
     * @param string $type Message type: success, error, warning, info
     * @param string $message Message content
     * @param string|null $title Optional title
     */
    public static function set(string $type, string $message, ?string $title = null): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = [];
        }

        $_SESSION[self::SESSION_KEY][] = [
            'type' => $type,
            'message' => $message,
            'title' => $title
        ];
    }

    /**
     * Set a success message
     */
    public static function success(string $message, ?string $title = null): void
    {
        self::set('success', $message, $title);
    }

    /**
     * Set an error message
     */
    public static function error(string $message, ?string $title = null): void
    {
        self::set('error', $message, $title);
    }

    /**
     * Set a warning message
     */
    public static function warning(string $message, ?string $title = null): void
    {
        self::set('warning', $message, $title);
    }

    /**
     * Set an info message
     */
    public static function info(string $message, ?string $title = null): void
    {
        self::set('info', $message, $title);
    }

    /**
     * Set an action success message
     *
     * @param string $action Action performed: added, updated, deleted, etc.
     * @param string $itemName Name of the item
     */
    public static function actionSuccess(string $action, string $itemName): void
    {
        self::set('action', $itemName, $action);
    }

    /**
     * Check if there are any flash messages
     */
    public static function has(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        return !empty($_SESSION[self::SESSION_KEY]);
    }

    /**
     * Get all flash messages and clear them
     *
     * @return array
     */
    public static function getAll(): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $messages = $_SESSION[self::SESSION_KEY] ?? [];
        $_SESSION[self::SESSION_KEY] = [];

        return $messages;
    }

    /**
     * Render flash messages as JavaScript
     * Call this at the end of your page to display any pending flash messages
     *
     * @return string JavaScript code
     */
    public static function render(): string
    {
        $messages = self::getAll();

        if (empty($messages)) {
            return '';
        }

        $js = '<script nonce="'. csp_nonce() . '">';
        $js .= 'document.addEventListener("DOMContentLoaded", function() {';

        foreach ($messages as $msg) {
            $type = self::escapeJs($msg['type']);
            $message = self::escapeJs($msg['message']);
            $title = $msg['title'] ? self::escapeJs($msg['title']) : '';

            if ($type === 'action') {
                // Action success message
                $js .= "Notify.actionSuccess('{$title}', '{$message}');";
            } else {
                // Regular notification
                if ($title) {
                    $js .= "Notify.{$type}('{$message}', '{$title}');";
                } else {
                    $js .= "Notify.{$type}('{$message}');";
                }
            }
        }

        $js .= '});';
        $js .= '</script>';

        return $js;
    }

    /**
     * Escape string for JavaScript
     */
    private static function escapeJs(string $str): string
    {
        return addslashes(htmlspecialchars($str, ENT_QUOTES, 'UTF-8'));
    }

    /**
     * Clear all flash messages
     */
    public static function clear(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION[self::SESSION_KEY] = [];
    }
}
