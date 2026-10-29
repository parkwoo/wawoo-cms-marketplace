<?php
namespace Wawoo\Plugin\PasswordGate;

/**
 * Admin settings page for the password gate. Rendered by the admin plugin
 * (plugins/admin/src/Gate.php, action=settings&plugin=password-gate) when
 * plugin.json declares "settings": true. Saves into a JSON file under
 * CACHE_DIR/settings/ which Config.php merges with highest priority.
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
                $pw  = (string)($_POST['password'] ?? '');
                $pw2 = (string)($_POST['password2'] ?? '');
                if ($pw !== '' || $pw2 !== '') {
                    if ($pw === '' || $pw2 === '' || $pw !== $pw2) {
                        $notice = 'New password not saved: both fields are required and must match.';
                    }
                }
                self::saveFromPost($_POST);
                if ($notice === '') {
                    $notice = 'Settings saved.';
                }
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
        $file = self::file();

        $existing = [];
        if (is_file($file)) {
            $raw = json_decode((string)file_get_contents($file), true);
            if (is_array($raw)) {
                $existing = $raw;
            }
        }

        $out = ['enabled' => !empty($post['enabled'])];

        $pw  = (string)($post['password'] ?? '');
        $pw2 = (string)($post['password2'] ?? '');
        if ($pw !== '' && $pw === $pw2) {
            $out['password_hash'] = password_hash($pw, PASSWORD_BCRYPT);
        } elseif (isset($existing['password_hash'])) {
            $out['password_hash'] = (string)$existing['password_hash'];
        }

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
        return rtrim(\CACHE_DIR, '/\\') . '/settings/password-gate.json';
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
        $checked = !empty($cfg['enabled']) ? ' checked' : '';
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
<title>Password gate settings</title>
<style>
body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
       font-family: -apple-system, system-ui, "Segoe UI", "Helvetica Neue", sans-serif;
       background: #f5f5f7; color: #1d1d1f; }
.card { width: min(92vw, 420px); padding: 2rem 1.75rem;
        background: #fff; border-radius: 18px;
        box-shadow: 0 30px 60px rgba(0,0,0,.12), 0 2px 4px rgba(0,0,0,.04); }
.card h1 { margin: 0 0 .35rem; font-size: 1.4rem; }
.card .hint { margin: 0 0 1.25rem; color: #6e6e73; font-size: .9rem; }
.card label { display: block; margin: 0 0 1rem; font-size: .95rem; }
.card label.inline { display: flex; align-items: center; gap: .5rem; }
.card input[type="password"] { width: 100%; box-sizing: border-box; margin-top: .3rem;
        padding: .65rem .8rem; font-size: .95rem;
        border: 1px solid #d2d2d7; border-radius: 10px; }
.card input[type="password"]:focus { outline: none; border-color: #0071e3; }
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
    <h1>Password gate</h1>
    <p class="hint">Lock the whole site behind a single shared password.</p>
    {$note}
        <form method="post" action="/admin/?action=settings&amp;plugin=password-gate">
            {$csrf}
            <label class="inline"><input type="checkbox" name="enabled" value="1"{$checked}> Enable site-wide password gate</label>
        <label>New password
            <input type="password" name="password" autocomplete="new-password">
        </label>
        <label>Confirm new password
            <input type="password" name="password2" autocomplete="new-password">
        </label>
        <button type="submit">Save</button>
    </form>
    <p><a href="/admin/?action=plugins">Back to plugins</a></p>
</main>
</body>
</html>
HTML;
    }
}
