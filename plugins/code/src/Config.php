<?php
namespace Wawoo\Plugin\Code;

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
        $override = $GLOBALS['WAWOO_CODE'] ?? [];
        $runtime = self::runtimeSettings();
        self::$cache = array_replace([
            'enabled'             => true,
            'copy_button'         => true,
            'copy_label'          => 'Copy',
            'copied_label'        => 'Copied',
            'highlight'           => true,
            'code_bg'             => '#ffffff',
            'code_text'           => '#1f2328',
            'code_border'         => '#d8d8dc',
            'pill_bg'             => '#f6f8fa',
            'pill_text'           => '#57606a',
            'hljs_cdn'            => 'https://cdn.jsdelivr.net/gh/highlightjs/cdn-release@11.12.0/build/highlight.min.js',
            'hljs_integrity'      => '',
            'hljs_theme_css'      => 'https://cdn.jsdelivr.net/gh/highlightjs/cdn-release@11.12.0/build/styles/github.min.css',
        ], $manifest, is_array($override) ? $override : [], $runtime);
        return self::$cache;
    }

    private static function runtimeSettings(): array
    {
        $f = rtrim(\CACHE_DIR, '/\\') . DIRECTORY_SEPARATOR . 'settings' . DIRECTORY_SEPARATOR . 'code.json';
        if (!is_file($f)) {
            return [];
        }
        $data = json_decode((string)file_get_contents($f), true);
        return is_array($data) ? $data : [];
    }
}
