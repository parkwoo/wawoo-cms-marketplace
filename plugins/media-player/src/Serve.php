<?php
namespace Wawoo\Plugin\MediaPlayer;

/**
 * `core.request`: serves the vendored Plyr assets. /plugins/** is denied by
 * the core PublicAssets allow-list, so the plugin exposes its own read-only
 * route with a strict two-file allow-list (no traversal, immutable cache).
 */
final class Serve
{
    private const MAP = [
        'css/plyr.css'    => ['file' => 'css/plyr.css',    'type' => 'text/css; charset=utf-8'],
        'js/plyr.min.js'  => ['file' => 'js/plyr.min.js',  'type' => 'application/javascript; charset=utf-8'],
    ];

    public function handle(?string $root = null): void
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $prefix = '/media-player/assets/';
        if (!str_starts_with($path, $prefix)) {
            return;
        }
        $rel = substr($path, strlen($prefix));
        $rel = preg_replace('#[^a-zA-Z0-9._/-]#', '', $rel) ?? '';
        $rel = str_replace('../', '', $rel);
        if (!isset(self::MAP[$rel])) {
            http_response_code(404);
            exit;
        }
        $file = dirname(__DIR__) . '/assets/' . self::MAP[$rel]['file'];
        if (!is_file($file)) {
            http_response_code(404);
            exit;
        }
        $ver = substr((string)@md5_file($file), 0, 12);
        header('Content-Type: ' . self::MAP[$rel]['type']);
        header('Cache-Control: public, max-age=31536000, immutable');
        header('ETag: "mp-' . $ver . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($file);
        exit;
    }
}
