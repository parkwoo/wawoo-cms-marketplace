<?php
namespace Wawoo\Plugin\Mlang;

/**
 * Plugin configuration: defaults come from plugin.json's config block and
 * can be overridden at runtime via $GLOBALS['WAWOO_MLANG'].
 */
final class Config
{
    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $defaults = [
            'default_lang' => 'en',
            'supported'    => ['en', 'ja', 'zh'],
        ];
        $file = dirname(__DIR__) . '/plugin.json';
        if (is_file($file)) {
            $raw = json_decode((string)file_get_contents($file), true);
            if (is_array($raw) && is_array($raw['config'] ?? null)) {
                $defaults = array_replace($defaults, $raw['config']);
            }
        }
        $override = $GLOBALS['WAWOO_MLANG'] ?? [];
        self::$cache = array_replace($defaults, is_array($override) ? $override : []);
        return self::$cache;
    }
}
