<?php
namespace Wawoo\Plugin\Code;

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

    private static function colorOk(string $v): bool
    {
        return $v === '' || preg_match('/^#[0-9a-fA-F]{3,8}$/', $v) === 1;
    }

    public static function saveFromPost(array $post): array
    {
        $pick = static function (string $key, string $def) use ($post): string {
            $v = trim((string)($post[$key] ?? ''));
            return $v === '' ? $def : $v;
        };
        $colors = ['code_bg', 'code_text', 'code_border', 'pill_bg', 'pill_text'];
        $config = [
            'enabled'        => !empty($post['enabled']),
            'copy_button'    => !empty($post['copy_button']),
            'highlight'      => !empty($post['highlight']),
            'code_bg'        => $pick('code_bg', '#ffffff'),
            'code_text'      => $pick('code_text', '#1f2328'),
            'code_border'    => $pick('code_border', '#d8d8dc'),
            'pill_bg'        => $pick('pill_bg', '#f6f8fa'),
            'pill_text'      => $pick('pill_text', '#57606a'),
        ];
        foreach ($colors as $k) {
            if (!self::colorOk((string)$config[$k])) {
                return ['error' => "$k must be a hex colour like #ffffff.", 'config' => []];
            }
        }
        $dir = rtrim(\CACHE_DIR, '/\\') . DIRECTORY_SEPARATOR . 'settings';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $file = $dir . DIRECTORY_SEPARATOR . 'code.json';
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
        $err = $error !== '' ? '<p style="color:#b00020;background:#ffe9e9;padding:.6rem;border-radius:8px">' . self::e($error) . '</p>' : '';
        $ok  = $notice !== '' ? '<p style="color:#0a7d32;background:#e7f7ee;padding:.6rem;border-radius:8px">' . self::e($notice) . '</p>' : '';
        $row = static function (string $name, string $label, string $val): string {
            return '<label style="display:flex;align-items:center;gap:.6rem;margin:.5rem 0">'
                . '<span style="width:9rem">' . self::e($label) . '</span>'
                . '<input type="color" name="' . self::e($name) . '" value="' . self::e($val) . '" style="width:3rem;padding:0;border:1px solid #ccc">'
                . '<code>' . self::e($val) . '</code></label>';
        };
        return '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Code blocks - Admin</title>'
            . '<style>body{font-family:system-ui,sans-serif;background:#f5f5f7;margin:0;padding:2rem;color:#1d1d1f}'
            . '.card{background:#fff;border-radius:14px;padding:1.5rem;max-width:560px;margin:0 auto;box-shadow:0 10px 30px rgba(0,0,0,.06)}'
            . 'button{background:#1d1d1f;color:#fff;border:0;border-radius:8px;padding:.6rem 1.2rem;margin-top:1rem;cursor:pointer}</style></head><body>'
            . '<div class="card"><h1>Code blocks</h1><p><a href="/admin/?action=plugins">&larr; Back to plugins</a></p>'
            . $err . $ok
            . '<form method="post" action="/admin/?action=settings&plugin=code">'
            . \Wawoo\Plugin\Admin\Csrf::field()
            . '<label style="display:block;margin:.5rem 0"><input type="checkbox" name="enabled" value="1"'
            . (!empty($cfg['enabled']) ? ' checked' : '') . '> Enabled</label>'
            . '<label style="display:block;margin:.5rem 0"><input type="checkbox" name="copy_button" value="1"'
            . (!empty($cfg['copy_button']) ? ' checked' : '') . '> Copy button</label>'
            . '<label style="display:block;margin:.5rem 0"><input type="checkbox" name="highlight" value="1"'
            . (!empty($cfg['highlight']) ? ' checked' : '') . '> Syntax highlighting</label>'
            . '<h2 style="font-size:1rem;margin:1rem 0 .25rem">Code block colours (fixed light palette)</h2>'
            . $row('code_bg', 'Background', (string)$cfg['code_bg'])
            . $row('code_text', 'Text', (string)$cfg['code_text'])
            . $row('code_border', 'Border', (string)$cfg['code_border'])
            . $row('pill_bg', 'Pill / button bg', (string)$cfg['pill_bg'])
            . $row('pill_text', 'Pill / button text', (string)$cfg['pill_text'])
            . '<button type="submit">Save</button>'
            . '</form></div></body></html>';
    }
}
