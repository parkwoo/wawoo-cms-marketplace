<?php
namespace Wawoo\Plugin\Maintenance;

/**
 * Runtime configuration for the maintenance plugin.
 *
 * Merge order (later wins):
 *   1. plugin.json `config` block (defaults)
 *   2. $GLOBALS['WAWOO_MAINTENANCE'] override from config.local.php
 *   3. CACHE_DIR/settings/maintenance.json (written by the admin settings
 *      page so operators can toggle maintenance without editing PHP)
 *
 * Password handling mirrors the password-gate plugin: a plaintext
 * 'password' supplied without a hash is bcrypt-hashed and a stable HMAC
 * secret is derived from the plaintext; a bare 'password_hash' gets a
 * fallback secret derived from the hash itself.
 */
final class Config
{
    private static $cache = null;

    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $cfg = array_replace(self::defaults(), self::manifestDefaults());

        $override = $GLOBALS['WAWOO_MAINTENANCE'] ?? null;
        if (is_array($override)) {
            $cfg = self::applyLayer($cfg, $override);
        }

        $settings = self::settingsFile();
        if (is_array($settings)) {
            $cfg = self::applyLayer($cfg, $settings);
        }

        if (!isset($cfg['secret']) && ($cfg['password_hash'] ?? '') !== '') {
            // No explicit secret: derive a stable fallback from the hash so
            // changing the password invalidates existing cookies.
            $cfg['secret'] = hash_hmac('sha256', 'wawoo-maintenance', (string)$cfg['password_hash']);
        }

        self::$cache = $cfg;
        return self::$cache;
    }

    private static function defaults(): array
    {
        return [
            'enabled'        => false,
            'password_hash'  => '',
            'secret'         => '',
            'cookie_name'    => 'wawoo_maintenance',
            'cookie_ttl'     => 3600,
            'max_attempts'   => 5,
            'lockout_seconds'=> 300,
            'title'          => 'Maintenance',
            'message'        => 'We are performing scheduled maintenance. Please check back soon.',
            'button'         => 'Continue',
            'footer'         => '',
            'exempt_paths'   => ['/admin', '/gate.css'],
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

    /**
     * Merge one override layer on top of $cfg, keeping password_hash/secret
     * consistent after any password change.
     */
    private static function applyLayer(array $cfg, array $layer): array
    {
        $oldHash = (string)($cfg['password_hash'] ?? '');
        $cfg = array_replace($cfg, $layer);
        unset($cfg['password']);

        if (isset($layer['password']) && (string)$layer['password'] !== '' && ($cfg['password_hash'] ?? '') === '') {
            // Plaintext override without a hash: bcrypt it and derive a
            // stable secret from the plaintext (NOT the salted hash).
            $pw = (string)$layer['password'];
            $cfg['password_hash'] = password_hash($pw, PASSWORD_BCRYPT);
            $cfg['secret'] = hash_hmac('sha256', 'wawoo-maintenance', $pw);
            return $cfg;
        }

        if ((string)($cfg['password_hash'] ?? '') !== $oldHash) {
            // The hash changed under this layer, so any secret derived from
            // an earlier password is stale; fall back to hash-derived.
            $cfg['secret'] = hash_hmac('sha256', 'wawoo-maintenance', (string)$cfg['password_hash']);
        }

        return $cfg;
    }

    private static function settingsFile(): array
    {
        $f = rtrim(\CACHE_DIR, '/\\') . DIRECTORY_SEPARATOR . 'settings'
            . DIRECTORY_SEPARATOR . 'maintenance.json';
        if (!is_file($f)) {
            return [];
        }
        $data = json_decode((string)@file_get_contents($f), true);
        return is_array($data) ? $data : [];
    }
}
