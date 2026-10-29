<?php
namespace Wawoo\Plugin\MediaPlayer;

/**
 * `theme.head.after`: emits the Plyr CSS/JS (served by this plugin's own
 * /media-player/assets route) exactly once, and only when the current
 * article actually carries media.
 */
final class Assets
{
    private static bool $emitted = false;

    public function handle(?array $vars = null): void
    {
        if (self::$emitted) {
            return;
        }
        $post = is_array($vars) ? ($vars['post'] ?? null) : null;
        if (!is_array($post)) {
            return;
        }
        $meta = (array)($post['meta'] ?? []);
        $has = false;
        foreach (['audio_url', 'audio_yt', 'video_yt', 'video_url'] as $k) {
            if (trim((string)($meta[$k] ?? '')) !== '') {
                $has = true;
                break;
            }
        }
        if (!$has) {
            return;
        }
        self::$emitted = true;
        echo '<link rel="stylesheet" href="' . htmlspecialchars(Assets::url('css/plyr.css'), ENT_QUOTES, 'UTF-8') . '">' . "\n";
        echo '<script' . \Wawoo\Core\Csp::attr() . ' src="' . htmlspecialchars(Assets::url('js/plyr.min.js'), ENT_QUOTES, 'UTF-8') . '" defer></script>' . "\n";
    }

    public static function url(string $rel): string
    {
        return (defined('BASE_PATH') ? rtrim(BASE_PATH, '/') : '') . '/media-player/assets/' . ltrim($rel, '/');
    }

    public static function reset(): void
    {
        self::$emitted = false;
    }
}
