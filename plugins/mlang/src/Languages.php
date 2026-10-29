<?php
namespace Wawoo\Plugin\Mlang;

/**
 * Language helpers shared by the content load hooks and the link hooks:
 * which languages the site supports, their display names, and which
 * suffixed translation files actually exist for a bundle.
 */
final class Languages
{
    public static function default(): string
    {
        return (string)(Config::all()['default_lang'] ?? 'en');
    }

    public static function supported(): array
    {
        $supported = array_values((array)(Config::all()['supported'] ?? []));
        $default = self::default();
        if (!in_array($default, $supported, true)) {
            array_unshift($supported, $default);
        }
        return $supported;
    }

    public static function name(string $code): string
    {
        $names = [
            'en' => 'English',
            'ja' => '日本語',
            'zh' => '中文',
        ];
        return $names[$code] ?? $code;
    }

    /**
     * Language codes for which the bundle really has content: the default
     * language (index.md) plus each index.<lang>.md present. Only languages
     * listed in the plugin config are reported.
     */
    public static function available(string $kind, string $slug): array
    {
        $safe = preg_replace('/[^a-zA-Z0-9_-]/', '', $slug);
        if ($safe === '') {
            return [];
        }
        if ($kind === 'pages') {
            if (!defined('PAGES_DIR')) {
                return [];
            }
            $dir = \PAGES_DIR;
        } else {
            if (!defined('POSTS_DIR')) {
                return [];
            }
            $dir = \POSTS_DIR;
        }
        if ($dir === '' || !is_dir($dir)) {
            return [];
        }
        $bundle = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $safe;
        if (!is_dir($bundle)) {
            return [];
        }
        $found = [];
        if (is_file($bundle . DIRECTORY_SEPARATOR . 'index.md')) {
            $found[] = self::default();
        }
        foreach (glob($bundle . DIRECTORY_SEPARATOR . 'index.*.md') as $file) {
            if (preg_match('/^index\.([a-zA-Z0-9_-]+)\.md$/', basename($file), $m)) {
                $found[] = $m[1];
            }
        }
        $supported = self::supported();
        $out = [];
        foreach ($found as $code) {
            if (in_array($code, $supported, true) && !in_array($code, $out, true)) {
                $out[] = $code;
            }
        }
        return $out;
    }

    /**
     * Site-relative-to-absolute URL for a content item in the given
     * language. The default language is unprefixed.
     */
    public static function url(string $kind, string $slug, string $code): string
    {
        $base = defined('BASE_URL') ? BASE_URL : '/';
        $prefix = $code === self::default() ? '' : $code . '/';
        return $kind === 'pages'
            ? $base . $prefix . $slug
            : $base . $prefix . 'post/' . $slug;
    }
}
