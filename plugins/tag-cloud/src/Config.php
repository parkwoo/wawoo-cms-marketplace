<?php
namespace Wawoo\Plugin\TagCloud;

final class Config
{
    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache !== null) return self::$cache;
        $defaults = self::fileDefaults();
        $override = $GLOBALS['WAWOO_TAG_CLOUD'] ?? [];
        self::$cache = array_replace($defaults, is_array($override) ? $override : []);
        if ((int)self::$cache['min_font_px'] > (int)self::$cache['max_font_px']) {
            $swap = self::$cache['min_font_px'];
            self::$cache['min_font_px'] = self::$cache['max_font_px'];
            self::$cache['max_font_px'] = $swap;
        }
        return self::$cache;
    }

    private static function fileDefaults(): array
    {
        $raw = json_decode((string)file_get_contents(dirname(__DIR__) . '/plugin.json'), true);
        return (is_array($raw) && isset($raw['config']) && is_array($raw['config'])) ? $raw['config'] : [];
    }
}
