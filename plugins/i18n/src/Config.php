<?php
namespace Wawoo\Plugin\I18n;

final class Config
{
    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache !== null) return self::$cache;
        $base = self::pluginDefaults();
        $override = $GLOBALS['WAWOO_I18N'] ?? [];
        $merged = is_array($override)
            ? array_replace($base, $override)
            : $base;
        self::$cache = array_replace($merged, self::settingsOverrides());
        return self::$cache;
    }

    /** Runtime overrides saved by the admin Settings page win over everything. */
    private static function settingsOverrides(): array
    {
        $file = rtrim(\CACHE_DIR, '/\\') . '/settings/i18n.json';
        if (!is_file($file)) {
            return [];
        }
        $raw = json_decode((string)@file_get_contents($file), true);
        return is_array($raw) ? $raw : [];
    }

    /** plugin.json `config` block wins over the hard-coded fallback. */
    private static function pluginDefaults(): array
    {
        $defaults = [
            'default'        => 'en',
            'supported'      => ['en', 'ja', 'zh'],
            'cookie_name'    => 'wawoo_lang',
            'detect_browser' => true,
            'url_param'      => 'lang',
        ];
        $file = dirname(__DIR__) . '/plugin.json';
        $raw = json_decode((string)@file_get_contents($file), true);
        if (is_array($raw) && is_array($raw['config'] ?? null)) {
            return array_replace($defaults, $raw['config']);
        }
        return $defaults;
    }
}
