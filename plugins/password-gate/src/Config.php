<?php
namespace Wawoo\Plugin\PasswordGate;

/**
 * Merges plugin.json defaults with the optional $PASSWORD_GATE override
 * defined in config.local.php, then applies any settings saved by the
 * admin UI (CACHE_DIR/settings/password-gate.json) on top.
 *
 * Priority: settings file > $PASSWORD_GATE > manifest defaults.
 */
final class Config
{
    private static $cache = null;

    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $defaults = array_replace(self::defaults(), self::manifestDefaults());
        $override = [];
        if (isset($GLOBALS['PASSWORD_GATE']) && is_array($GLOBALS['PASSWORD_GATE'])) {
            $override = $GLOBALS['PASSWORD_GATE'];
        }
        // Hash/algorithm helpers.
        if (isset($override['password_hash'])) {
            $override['password_hash'] = (string)$override['password_hash'];
        }
        if (isset($override['password']) && !isset($override['password_hash'])) {
            $pw = (string)$override['password'];
            $override['password_hash'] = password_hash($pw, PASSWORD_BCRYPT);
            // Stable HMAC key derived from the plaintext password, NOT the
            // bcrypt hash (which has a random salt and would invalidate all
            // cookies between requests).
            $override['secret'] = hash_hmac('sha256', 'wawoo-gate', $pw);
            // Drop plaintext from runtime config.
            unset($override['password']);
        }
        $settings = self::settingsOverrides();
        $cache = array_replace($defaults, $override, $settings);
        $cache['password_hash'] = (string)($cache['password_hash'] ?? '');
        // For users that only supply password_hash, derive a stable
        // secret from a fixed string + the hash itself so changing the
        // password invalidates existing cookies. Also recompute when the
        // settings file swaps the hash without supplying its own secret.
        if (!isset($cache['secret']) || (isset($settings['password_hash']) && !isset($settings['secret']))) {
            $cache['secret'] = hash_hmac('sha256', 'wawoo-gate', $cache['password_hash']);
        }
        self::$cache = $cache;
        return self::$cache;
    }

    private static function settingsOverrides(): array
    {
        $file = rtrim(\CACHE_DIR, '/\\') . '/settings/password-gate.json';
        if (!is_file($file)) {
            return [];
        }
        $raw = json_decode((string)@file_get_contents($file), true);
        return is_array($raw) ? $raw : [];
    }

    private static function defaults(): array
    {
        return [
            'enabled'        => false,
            'password_hash'  => '',
            'cookie_name'    => 'wawoo_gate',
            'cookie_ttl'     => 86400,
            'session_ttl'    => 7200,
            'max_attempts'   => 5,
            'lockout_seconds'=> 900,
            'title'          => 'Members only',
            'message'        => 'This site is private. Enter the password to continue.',
            'placeholder'    => 'Password',
            'button'         => 'Enter',
            'footer'         => 'Authorized access only.',
            'remember_days'  => 7,
            'exempt_paths'   => ['/gate.css'],
            'exempt_post'    => true,
            'theme'          => 'default',
            'trust_proxy'    => false,
        ];
    }

    private static function manifestDefaults(): array
    {
        $raw = @file_get_contents(dirname(__DIR__) . '/plugin.json');
        if ($raw === false) {
            return [];
        }
        $manifest = json_decode($raw, true);
        if (!is_array($manifest) || !is_array($manifest['config'] ?? null)) {
            return [];
        }
        return $manifest['config'];
    }
}
