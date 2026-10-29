<?php
namespace Wawoo\Plugin\Admin;

/**
 * Session-backed CSRF helper shared by the admin console and plugin
 * settings pages. Tokens are stored in the PHP session under one key and
 * rotated lazily; all destructive admin actions and every plugin settings
 * form must include the token and verify it before acting.
 */
final class Csrf
{
    private const KEY = 'wawoo_csrf_token';

    public static function token(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION[self::KEY]) || !is_string($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::KEY];
    }

    public static function verify(?string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $expected = $_SESSION[self::KEY] ?? '';
        return is_string($expected) && $expected !== ''
            && is_string($token) && $token !== ''
            && hash_equals($expected, $token);
    }

    /** Hidden input for forms posting to admin endpoints. */
    public static function field(): string
    {
        return '<input type="hidden" name="csrf" value="' . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    /** Read the CSRF token from $_POST, or null. */
    public static function posted(): ?string
    {
        $v = $_POST['csrf'] ?? null;
        return is_string($v) ? $v : null;
    }
}
