<?php
namespace Wawoo\Plugin\RssLang;

use Wawoo\Core\Langs;

final class Config
{
    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $defaults = ['max_items' => 20, 'langs' => 'auto', 'default_lang' => ''];
        $file = dirname(__DIR__) . '/plugin.json';
        if (is_file($file)) {
            $raw = json_decode((string)file_get_contents($file), true);
            if (is_array($raw) && is_array($raw['config'] ?? null)) {
                $defaults = array_replace($defaults, $raw['config']);
            }
        }
        $override = $GLOBALS['WAWOO_RSS_LANG'] ?? [];
        self::$cache = array_replace($defaults, is_array($override) ? $override : []);
        return self::$cache;
    }

    public static function reset(): void
    {
        self::$cache = null;
    }

    /** Languages that get their own feed. */
    public static function langs(): array
    {
        $cfg = self::all();
        if (is_array($cfg['langs'] ?? null)) {
            return array_values(array_map('strval', $cfg['langs']));
        }
        return Langs::supported();
    }

    public static function defaultLang(): string
    {
        $cfg = self::all();
        return (string)($cfg['default_lang'] !== '' ? $cfg['default_lang'] : Langs::default());
    }
}
