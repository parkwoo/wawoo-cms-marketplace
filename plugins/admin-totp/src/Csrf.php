<?php
namespace Wawoo\Plugin\AdminTotp;

/**
 * Session-scoped CSRF tokens. The token is generated once per session and
 * compared with hash_equals() on verification.
 */
final class Csrf
{
    public static function token(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        if (empty($_SESSION['wawoo_admin_csrf']) || !is_string($_SESSION['wawoo_admin_csrf'])) {
            $_SESSION['wawoo_admin_csrf'] = bin2hex(random_bytes(16));
        }
        return $_SESSION['wawoo_admin_csrf'];
    }

    public static function check(?string $token): bool
    {
        $expected = $_SESSION['wawoo_admin_csrf'] ?? '';
        return is_string($expected)
            && $expected !== ''
            && is_string($token)
            && $token !== ''
            && hash_equals($expected, $token);
    }

    public static function field(): string
    {
        $t = self::token();
        return '<input type="hidden" name="csrf" value="' . htmlspecialchars($t, ENT_QUOTES, 'UTF-8') . '">';
    }
}
