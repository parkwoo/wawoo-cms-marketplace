<?php
namespace Wawoo\Plugin\Mermaid;

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
        $override = $GLOBALS['WAWOO_MERMAID'] ?? [];
        $runtime = self::runtimeSettings();
        self::$cache = array_replace([
            'enabled'        => true,
            'cdn_url'        => 'https://cdn.jsdelivr.net/npm/mermaid@11.17.2/dist/mermaid.min.js',
            'cdn_integrity'  => '',
            'diagram_bg'     => '#ffffff',
            'follow_scheme'  => false,
            'theme'          => 'default',
            'dark_theme'     => 'dark',
            'security_level' => 'strict',
            'start_on_load'  => false,
        ], $manifest, is_array($override) ? $override : [], $runtime);
        return self::$cache;
    }

    private static function runtimeSettings(): array
    {
        $f = rtrim(\CACHE_DIR, '/\\') . DIRECTORY_SEPARATOR . 'settings' . DIRECTORY_SEPARATOR . 'mermaid.json';
        if (!is_file($f)) {
            return [];
        }
        $data = json_decode((string)file_get_contents($f), true);
        return is_array($data) ? $data : [];
    }
}
