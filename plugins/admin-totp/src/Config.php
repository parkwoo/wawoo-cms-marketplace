<?php
namespace Wawoo\Plugin\AdminTotp;

/**
 * Merges plugin.json `config` defaults with the optional $GLOBALS['WAWOO_ADMIN']
 * override defined in config.local.php. The plugin manifest is the source of
 * truth for defaults; runtime overrides always win.
 */
final class Config
{
    /**
     * Reflection-resettable cache (see tests). Kept as a class property so
     * tests can `setValue(null, null)` between cases.
     */
    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $defaults = [];
        $manifest = dirname(__DIR__) . '/plugin.json';
        if (is_file($manifest)) {
            $raw = json_decode((string)file_get_contents($manifest), true);
            if (is_array($raw) && isset($raw['config']) && is_array($raw['config'])) {
                $defaults = $raw['config'];
            }
        }
        $override = $GLOBALS['WAWOO_ADMIN'] ?? [];
        self::$cache = array_replace($defaults, is_array($override) ? $override : []);
        return self::$cache;
    }

    public static function dataPath(): string
    {
        $file = (string)self::all()['data_file'];
        // Absolute overrides (used by the test-suite) bypass the state dir.
        if ($file !== '' && $file[0] === '/') {
            return $file;
        }
        // Runtime state lives under the writable, web-denied cache volume
        // (plugins/admin-totp/data is not writable under a read-only prod
        // root filesystem).
        return rtrim(\CACHE_DIR, '/\\') . '/admin-totp/' . $file;
    }
}
