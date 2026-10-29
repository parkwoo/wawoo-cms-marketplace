<?php
namespace Wawoo\Plugin\MediaPlayer;

final class Config
{
    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $defaults = [
            'probe_timeout_ms' => 4000,
            'storage_key'      => 'yt-blocked',
            'preload'          => 'none',
            'allow_hosts'      => ['youtube.com', 'youtu.be', 'youtube-nocookie.com'],
            'media_base_url'   => '',
        ];
        $file = dirname(__DIR__) . '/plugin.json';
        if (is_file($file)) {
            $raw = json_decode((string)file_get_contents($file), true);
            if (is_array($raw) && is_array($raw['config'] ?? null)) {
                $defaults = array_replace($defaults, $raw['config']);
            }
        }
        $override = $GLOBALS['WAWOO_MEDIA_PLAYER'] ?? [];
        self::$cache = array_replace($defaults, is_array($override) ? $override : []);
        return self::$cache;
    }

    public static function reset(): void
    {
        self::$cache = null;
    }

    /** True if $url's host is on the allowlist (https only, exact or subdomain). */
    public static function allowedUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!is_string($host) || $host === '' || parse_url($url, PHP_URL_SCHEME) !== 'https') {
            return false;
        }
        $host = strtolower($host);
        foreach ((array)self::all()['allow_hosts'] as $allowed) {
            $allowed = strtolower((string)$allowed);
            if ($allowed === '' ) {
                continue;
            }
            if ($host === $allowed || str_ends_with($host, '.' . $allowed)) {
                return true;
            }
        }
        return false;
    }
}
