<?php
namespace Wawoo\Plugin\Pagination;

final class Config
{
    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $file = dirname(__DIR__) . '/plugin.json';
        $defaults = [];
        if (is_file($file)) {
            $raw = json_decode((string)file_get_contents($file), true);
            if (is_array($raw) && isset($raw['config']) && is_array($raw['config'])) {
                $defaults = $raw['config'];
            }
        }
        $override = $GLOBALS['WAWOO_PAGINATION'] ?? [];
        self::$cache = array_replace($defaults, is_array($override) ? $override : []);
        return self::$cache;
    }

    public static function perPage(): int
    {
        $per = (int)(self::all()['per_page'] ?? 0);
        return $per > 0 ? $per : (int)POSTS_PER_PAGE;
    }
}
