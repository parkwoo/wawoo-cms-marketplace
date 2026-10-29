<?php
namespace Wawoo\Plugin\I18n;

/**
 * Admin settings page for the i18n plugin. Rendered by the admin plugin
 * (plugins/admin/src/Gate.php, action=settings&plugin=i18n) when plugin.json
 * declares "settings": true. Saves into a JSON file under CACHE_DIR/settings/
 * which Config.php merges with highest priority.
 */
final class Settings
{
    public function handle(?string $root): void
    {
        $notice = '';
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!\Wawoo\Plugin\Admin\Csrf::verify(\Wawoo\Plugin\Admin\Csrf::posted())) {
                $notice = 'Invalid or missing CSRF token.';
            } else {
                self::saveFromPost($_POST);
                $notice = 'Settings saved.';
            }
        }
        $cfg = Config::all();
        echo self::page($cfg, $notice);
        exit;
    }

    /**
     * Validate and persist the settings form. Returns the resulting merged
     * config array (Config::all() view) so it can be unit-tested without
     * echoing or exiting.
     */
    public static function saveFromPost(array $post): array
    {
        $supported = [];
        foreach (['en', 'ja', 'zh'] as $code) {
            if (!empty($post['supported']) && is_array($post['supported'])
                && in_array($code, $post['supported'], true)) {
                $supported[] = $code;
            }
        }
        if ($supported === []) {
            $supported = ['en'];
        }

        $current = (string)Config::all()['default'];
        $default = (string)($post['default'] ?? '');
        if (!in_array($default, $supported, true)) {
            $default = in_array($current, $supported, true) ? $current : $supported[0];
        }

        $out = [
            'default'         => $default,
            'supported'       => $supported,
            'detect_browser'  => !empty($post['detect_browser']),
        ];

        $file = self::file();
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        self::lockedWrite($file, $out);
        self::resetConfigCache();

        return Config::all();
    }

    private static function file(): string
    {
        return rtrim(\CACHE_DIR, '/\\') . '/settings/i18n.json';
    }

    private static function lockedWrite(string $file, array $value): void
    {
        $lock = $file . '.lock';
        $tmp  = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        $fh   = @fopen($lock, 'c');
        if ($fh === false) {
            @file_put_contents($file, json_encode($value));
            return;
        }
        flock($fh, LOCK_EX);
        $ok = @file_put_contents($tmp, json_encode($value)) !== false;
        if ($ok) {
            $ok = @rename($tmp, $file);
        }
        if (!$ok) {
            @unlink($tmp);
        }
        flock($fh, LOCK_UN);
        fclose($fh);
        @unlink($lock);
    }

    private static function resetConfigCache(): void
    {
        $r = new \ReflectionClass(Config::class);
        if ($r->hasProperty('cache')) {
            $p = $r->getProperty('cache');
            $p->setAccessible(true);
            $p->setValue(null, null);
        }
    }

    private static function page(array $cfg, string $notice): string
    {
        $labels = ['en' => 'English', 'ja' => '日本語', 'zh' => '简体中文'];
        $supported = array_values((array)($cfg['supported'] ?? []));
        if ($supported === []) {
            $supported = ['en'];
        }
        $default = (string)($cfg['default'] ?? 'en');
        if (!in_array($default, $supported, true)) {
            $default = $supported[0];
        }

        $optHtml = '';
        foreach ($supported as $code) {
            $code = (string)$code;
            $sel = $code === $default ? ' selected' : '';
            $optHtml .= '<option value="' . htmlspecialchars($code) . '"' . $sel . '>'
                . htmlspecialchars(strtoupper($code)) . '</option>';
        }
        $cbHtml = '';
        foreach (['en', 'ja', 'zh'] as $code) {
            $checked = in_array($code, $supported, true) ? ' checked' : '';
            $cbHtml .= '<label class="inline"><input type="checkbox" name="supported[]" value="'
                . htmlspecialchars($code) . '"' . $checked . '> '
                . htmlspecialchars($labels[$code]) . '</label>';
        }
        $browser = !empty($cfg['detect_browser']) ? ' checked' : '';

        $note = $notice !== ''
            ? '<p class="note" role="status">' . htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') . '</p>'
            : '';
        $csrf = \Wawoo\Plugin\Admin\Csrf::field();
        return <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>i18n settings</title>
<style>
body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
       font-family: -apple-system, system-ui, "Segoe UI", "Helvetica Neue", sans-serif;
       background: #f5f5f7; color: #1d1d1f; }
.card { width: min(92vw, 440px); padding: 2rem 1.75rem;
        background: #fff; border-radius: 18px;
        box-shadow: 0 30px 60px rgba(0,0,0,.12), 0 2px 4px rgba(0,0,0,.04); }
.card h1 { margin: 0 0 .35rem; font-size: 1.4rem; }
.card .hint { margin: 0 0 1.25rem; color: #6e6e73; font-size: .9rem; }
.card label { display: block; margin: 0 0 1rem; font-size: .95rem; }
.card label.inline { display: flex; align-items: center; gap: .5rem; }
.card .fieldset-title { margin: 0 0 .35rem; font-weight: 600; font-size: .95rem; }
.card fieldset { border: 0; margin: 0 0 1rem; padding: 0; }
.card select, .card input[type="checkbox"] { margin-top: .3rem; }
.card select { width: 100%; box-sizing: border-box; padding: .6rem .8rem; font-size: .95rem;
        border: 1px solid #d2d2d7; border-radius: 10px; background: #fff; }
.card button { border: 0; background: #1d1d1f; color: #fff;
        padding: .65rem 1.4rem; font-size: .95rem; font-weight: 600; border-radius: 10px; cursor: pointer; }
.card button:hover { background: #000; }
.card .note { background: #eaf6ea; color: #1a6b1a; padding: .55rem .75rem; border-radius: 8px;
        font-size: .9rem; }
.card a { color: #0071e3; }
</style>
</head>
<body>
<main class="card" role="main">
    <h1>i18n</h1>
    <p class="hint">Language settings for the site UI (themes and admin console).</p>
    {$note}
        <form method="post" action="/admin/?action=settings&amp;plugin=i18n">
            {$csrf}
            <label>Default language
            <select name="default">{$optHtml}</select>
        </label>
        <p class="fieldset-title">Supported languages</p>
        <fieldset>{$cbHtml}</fieldset>
        <label class="inline"><input type="checkbox" name="detect_browser" value="1"{$browser}> Detect browser language</label>
        <button type="submit">Save</button>
    </form>
    <p><a href="/admin/?action=plugins">Back to plugins</a></p>
</main>
</body>
</html>
HTML;
    }
}
