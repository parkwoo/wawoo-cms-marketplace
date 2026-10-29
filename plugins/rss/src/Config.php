<?php
namespace Wawoo\Plugin\Rss;

final class Config
{
    /** @var array<string,mixed>|null */
    private static $cache = null;

    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $manifest = [];
        $file = dirname(__DIR__) . '/plugin.json';
        if (is_file($file)) {
            $raw = json_decode((string)file_get_contents($file), true);
            if (is_array($raw) && isset($raw['config']) && is_array($raw['config'])) {
                $manifest = $raw['config'];
            }
        }
        $override = $GLOBALS['WAWOO_RSS'] ?? [];
        self::$cache = array_replace([
            'max_items' => 20,
        ], $manifest, is_array($override) ? $override : []);
        return self::$cache;
    }
}
