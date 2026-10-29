<?php
namespace Wawoo\Plugin\Mermaid;

final class Settings
{
    /** @var string */
    private static $notice = '';
    /** @var string */
    private static $error = '';

    public function handle(?string $root = null): void
    {
        $cfg = Config::all();
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!\Wawoo\Plugin\Admin\Csrf::verify(\Wawoo\Plugin\Admin\Csrf::posted())) {
                self::$error = 'Invalid or missing CSRF token.';
            } else {
                $r = self::saveFromPost($_POST);
                if ($r['error'] !== '') {
                    self::$error = $r['error'];
                } else {
                    self::$notice = 'Settings saved.';
                    $cfg = $r['config'];
                }
            }
        }
        echo self::html($cfg, self::$error, self::$notice);
        exit;
    }

    public const THEMES = ['default', 'neutral', 'dark', 'forest', 'base'];
    public const SECURITY_LEVELS = ['strict', 'sandbox'];

    /** Validate + persist runtime settings; returns [config, error]. */
    public static function saveFromPost(array $post): array
    {
        $cdn = trim((string)($post['cdn_url'] ?? ''));
        $integrity = trim((string)($post['cdn_integrity'] ?? ''));
        $enabled = !empty($post['enabled']);
        $theme = trim((string)($post['theme'] ?? '')) ?: 'default';
        $dark = trim((string)($post['dark_theme'] ?? '')) ?: $theme;
        $security = trim((string)($post['security_level'] ?? '')) ?: 'strict';
        $bg = '';
        if (!empty($post['use_bg'])) {
            $bg = trim((string)($post['diagram_bg'] ?? ''));
            if (preg_match('/^#[0-9a-fA-F]{3,8}$/', $bg) !== 1) {
                return ['error' => 'diagram_bg must be a hex colour like #ffffff.', 'config' => []];
            }
        }

        // CDN must be HTTPS (empty = disabled remote loading). Same-origin
        // self-hosted paths are allowed (start with '/') but protocol-
        // relative URLs ('//host/...') and backslash trickery are refused.
        if ($cdn !== '') {
            $isHttps = preg_match('#^https://#i', $cdn) === 1;
            $isSameOrigin = str_starts_with($cdn, '/')
                && !str_starts_with($cdn, '//')
                && !str_starts_with($cdn, '/\\')
                && !str_starts_with($cdn, '/%5c');
            if (!$isHttps && !$isSameOrigin) {
                return ['error' => 'cdn_url must be https:// or a same-origin absolute path (/…).', 'config' => []];
            }
        }
        if ($integrity !== '' && !preg_match('#^sha(?:256|384|512)-[A-Za-z0-9+/=]+$#', $integrity)) {
            return ['error' => 'cdn_integrity must be a valid SRI hash (sha256-/sha384-/sha512-…).', 'config' => []];
        }
        if (!in_array($theme, self::THEMES, true) || !in_array($dark, self::THEMES, true)) {
            return ['error' => 'theme must be one of: ' . implode(', ', self::THEMES) . '.', 'config' => []];
        }
        if (!in_array($security, self::SECURITY_LEVELS, true)) {
            return ['error' => 'security_level must be strict or sandbox.', 'config' => []];
        }
        $config = [
            'enabled'        => $enabled,
            'cdn_url'        => $cdn,
            'cdn_integrity'  => $integrity,
            'diagram_bg'     => $bg,
            'follow_scheme'  => !empty($post['follow_scheme']),
            'theme'          => $theme,
            'dark_theme'     => $dark,
            'security_level' => $security,
            'start_on_load'  => !empty($post['start_on_load']),
        ];
        $dir = rtrim(\CACHE_DIR, '/\\') . DIRECTORY_SEPARATOR . 'settings';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $file = $dir . DIRECTORY_SEPARATOR . 'mermaid.json';
        $tmp  = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($tmp, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
            return ['error' => 'Could not write settings file.', 'config' => []];
        }
        @rename($tmp, $file);
        return ['error' => '', 'config' => $config];
    }

    private static function e($s): string
    {
        return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    }

    private static function html(array $cfg, string $error, string $notice): string
    {
        $checked = !empty($cfg['enabled']) ? ' checked' : '';
        $start   = !empty($cfg['start_on_load']) ? ' checked' : '';
        $themeSel = function (string $name, string $current): string {
            $s = '<select name="' . $name . '">';
            foreach (self::THEMES as $o) {
                $s .= '<option value="' . $o . '"' . ($current === $o ? ' selected' : '') . '>' . $o . '</option>';
            }
            return $s . '</select>';
        };
        $secSel = '<select name="security_level">';
        foreach (self::SECURITY_LEVELS as $o) {
            $secSel .= '<option value="' . $o . '"' . ($cfg['security_level'] === $o ? ' selected' : '') . '>' . $o . '</option>';
        }
        $secSel .= '</select>';
        $err = $error !== '' ? '<p style="color:#b00020;background:#ffe9e9;padding:.6rem;border-radius:8px">' . self::e($error) . '</p>' : '';
        $ok  = $notice !== '' ? '<p style="color:#0a7d32;background:#e7f7ee;padding:.6rem;border-radius:8px">' . self::e($notice) . '</p>' : '';
        return '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Mermaid - Admin</title>'
            . '<style>body{font-family:system-ui,sans-serif;background:#f5f5f7;margin:0;padding:2rem;color:#1d1d1f}'
            . '.card{background:#fff;border-radius:14px;padding:1.5rem;max-width:640px;margin:0 auto;box-shadow:0 10px 30px rgba(0,0,0,.06)}'
            . 'label{display:block;margin:.75rem 0}input[type=text],select{padding:.4rem;border:1px solid #d2d2d7;border-radius:8px;width:100%;box-sizing:border-box}'
            . 'button{background:#1d1d1f;color:#fff;border:0;border-radius:8px;padding:.6rem 1.2rem;margin-top:.5rem;cursor:pointer}'
            . 'code{background:#f0f0f2;padding:.1rem .3rem;border-radius:4px}</style></head><body>'
            . '<div class="card"><h1>Mermaid</h1><p><a href="/admin/?action=plugins">&larr; Back to plugins</a></p>'
            . $err . $ok
            . '<form method="post" action="/admin/?action=settings&plugin=mermaid">'
            . \Wawoo\Plugin\Admin\Csrf::field()
            . '<label><input type="checkbox" name="enabled" value="1"' . $checked . '> Enabled</label>'
            . '<label>Mermaid runtime URL (https:// or same-origin /…; empty = disabled)<br><input type="text" name="cdn_url" value="' . self::e($cfg['cdn_url']) . '" placeholder="https://cdn.jsdelivr.net/npm/mermaid@11.17.2/dist/mermaid.min.js"></label>'
            . '<label>Diagram background colour<br><input type="checkbox" name="use_bg" value="1"'
            . ((string)$cfg['diagram_bg'] !== '' ? ' checked' : '') . '> Use a solid background'
            . ' <input type="color" name="diagram_bg" value="' . self::e((string)($cfg['diagram_bg'] !== '' ? $cfg['diagram_bg'] : '#ffffff')) . '"></label>'
            . '<label>SRI integrity (optional, e.g. sha384-…)<br><input type="text" name="cdn_integrity" value="' . self::e($cfg['cdn_integrity']) . '" placeholder="sha384-…"></label>'
            . '<label>Light theme<br>' . $themeSel('theme', (string)$cfg['theme']) . '</label>'
            . '<label>Dark theme (used under prefers-color-scheme: dark)<br>' . $themeSel('dark_theme', (string)$cfg['dark_theme']) . '</label>'
            . '<label>Security level (strict recommended)<br>' . $secSel . '</label>'
            . '<label><input type="checkbox" name="start_on_load" value="1"' . $start . '> startOnLoad (mermaid auto-runs all diagrams)</label>'
            . '<button type="submit">Save</button>'
            . '</form></div></body></html>';
    }
}
