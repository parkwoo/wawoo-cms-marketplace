<?php
namespace Wawoo\Plugin\SitemapHtml;

final class Config
{
    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache !== null) return self::$cache;
        $defaults = self::fileDefaults();
        $override = $GLOBALS['WAWOO_SITEMAP_HTML'] ?? [];
        self::$cache = array_replace($defaults, is_array($override) ? $override : []);
        return self::$cache;
    }

    public static function perPage(): int
    {
        return max(1, (int)(self::all()['per_page'] ?? 0));
    }

    private static function fileDefaults(): array
    {
        $raw = json_decode((string)file_get_contents(dirname(__DIR__) . '/plugin.json'), true);
        return (is_array($raw) && isset($raw['config']) && is_array($raw['config'])) ? $raw['config'] : [];
    }
}
