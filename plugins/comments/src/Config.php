<?php
namespace Wawoo\Plugin\Comments;

final class Config
{
    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $defaults = [];
        $file = dirname(__DIR__) . '/plugin.json';
        if (is_file($file)) {
            $raw = json_decode((string)file_get_contents($file), true);
            if (is_array($raw) && isset($raw['config']) && is_array($raw['config'])) {
                $defaults = $raw['config'];
            }
        }
        $override = $GLOBALS['WAWOO_COMMENTS'] ?? [];
        self::$cache = array_replace($defaults, is_array($override) ? $override : []);
        return self::$cache;
    }
}
