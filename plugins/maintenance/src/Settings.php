<?php
namespace Wawoo\Plugin\Maintenance;

/**
 * Maintenance plugin settings page, dispatched by the admin console at
 * /admin/?action=settings&plugin=maintenance (requires "settings": true in
 * plugin.json). Persists runtime settings to
 * CACHE_DIR/settings/maintenance.json which Config merges last, so the page
 * overrides both plugin.json defaults and $WAWOO_MAINTENANCE globals.
 */
final class Settings
{
    private static ?string $lastError = null;

    /**
     * Last validation error from saveFromPost(), or null on success.
     */
    public static function lastError(): ?string
    {
        return self::$lastError;
    }

    public function handle(?string $root = null): void
    {
        $cfg    = Config::all();
        $notice = '';
        $error  = '';

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!\Wawoo\Plugin\Admin\Csrf::verify(\Wawoo\Plugin\Admin\Csrf::posted())) {
                $error = 'Invalid or missing CSRF token.';
            } else {
                $cfg = self::saveFromPost($_POST);
                $error = self::lastError();
                if ($error === null || $error === '') {
                    $notice = 'Settings saved.';
                }
            }
        }

        $enabled = !empty($cfg['enabled']);
        $title   = (string)($cfg['title'] ?? '');
        $message = (string)($cfg['message'] ?? '');
        $button  = (string)($cfg['button'] ?? '');
        $footer  = (string)($cfg['footer'] ?? '');

        echo self::html($enabled, $title, $message, $button, $footer, $notice, (string)$error);
    }

    /**
     * Validate a settings form POST and persist it. Returns the resulting
     * config array (new values merged over the old). When the password pair
     * is invalid the previously stored hash is kept, nothing is written, and
     * lastError() reports why.
     *
     * @param array $post Form fields: enabled, title, message, button,
     *                    footer, password, password2.
     */
    public static function saveFromPost(array $post): array
    {
        self::$lastError = null;

        $cfg = Config::all();
        $settings = self::read();

        $settings['enabled'] = !empty($post['enabled']);
        foreach (['title', 'message', 'button', 'footer'] as $key) {
            $value = trim((string)($post[$key] ?? ''));
            $settings[$key] = $value !== '' ? $value : (string)($cfg[$key] ?? '');
        }

        $pw   = (string)($post['password'] ?? '');
        $pw2  = (string)($post['password2'] ?? '');
        // Persist the currently effective hash unless a new password is set,
        // so the runtime file tracks globals overrides instead of going stale.
        $hash = (string)($cfg['password_hash'] ?? '');
        $settings['password_hash'] = $hash;

        if ($pw !== '' || $pw2 !== '') {
            if ($pw === '') {
                self::$lastError = 'Enter a new password.';
            } elseif ($pw !== $pw2) {
                self::$lastError = 'Passwords do not match; existing password kept.';
            } else {
                $settings['password_hash'] = password_hash($pw, PASSWORD_BCRYPT);
            }
        } elseif ($settings['enabled'] && $hash === '') {
            self::$lastError = 'A password is required to enable maintenance mode.';
        }

        if (self::$lastError !== null) {
            return $cfg;
        }

        self::write($settings);

        return array_replace($cfg, $settings);
    }

    private static function file(): string
    {
        return rtrim(\CACHE_DIR, '/\\') . DIRECTORY_SEPARATOR . 'settings'
            . DIRECTORY_SEPARATOR . 'maintenance.json';
    }

    private static function read(): array
    {
        $f = self::file();
        if (!is_file($f)) {
            return [];
        }
        $data = json_decode((string)@file_get_contents($f), true);
        return is_array($data) ? $data : [];
    }

    private static function write(array $settings): void
    {
        $dir = dirname(self::file());
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $f    = self::file();
        $lock = $f . '.lock';
        $tmp  = $f . '.' . bin2hex(random_bytes(4)) . '.tmp';
        $fh   = @fopen($lock, 'c');
        if ($fh === false) {
            @file_put_contents($f, json_encode($settings));
            return;
        }
        flock($fh, LOCK_EX);
        $ok = @file_put_contents($tmp, json_encode($settings)) !== false;
        if ($ok) {
            $ok = @rename($tmp, $f);
        }
        if (!$ok) {
            @unlink($tmp);
        }
        flock($fh, LOCK_UN);
        fclose($fh);
        @unlink($lock);
    }

    private static function html(
        bool $enabled, string $title, string $message, string $button,
        string $footer, string $notice, string $error
    ): string {
        $e = function (string $v): string {
            return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
        };
        $checked = $enabled ? ' checked' : '';
        $noticeHtml = $notice !== ''
            ? '<div class="mntn-notice" role="status">' . $e($notice) . '</div>'
            : '';
        $errorHtml = $error !== ''
            ? '<div class="mntn-error" role="alert">' . $e($error) . '</div>'
            : '';
        $csrf = \Wawoo\Plugin\Admin\Csrf::field();
        return <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Maintenance settings - Admin</title>
<style>
:root { color-scheme: light dark; }
body {
    margin: 0; padding: 2rem 1rem;
    font-family: -apple-system, system-ui, "Segoe UI", "Helvetica Neue", sans-serif;
    background: #f6f7f9; color: #14161a;
}
@media (prefers-color-scheme: dark) {
    body { background: #0d0f12; color: #f2f4f8; }
}
.wrap { max-width: 620px; margin: 0 auto; }
h1 { font-size: 1.4rem; margin: 0 0 .25rem; }
a.back { font-size: .85rem; color: #b45309; text-decoration: none; }
.card {
    background: #fff; border-radius: 14px; padding: 1.6rem 1.8rem;
    box-shadow: 0 12px 34px rgba(15,23,42,.08);
}
@media (prefers-color-scheme: dark) { .card { background: #171a20; } }
label { display: block; font-size: .85rem; font-weight: 600; margin: 1rem 0 .3rem; }
label.inline { display: flex; align-items: center; gap: .5rem; font-size: .95rem; margin-top: 0; }
input[type="text"], input[type="password"], textarea {
    width: 100%; box-sizing: border-box;
    padding: .55rem .7rem; font-size: .95rem;
    border: 1px solid #d3d9e1; border-radius: 8px;
    background: #fff; color: inherit; font-family: inherit;
}
@media (prefers-color-scheme: dark) {
    input[type="text"], input[type="password"], textarea { background: #10131a; border-color: #2b313c; }
}
textarea { min-height: 80px; resize: vertical; }
button {
    margin-top: 1.4rem; border: 0; cursor: pointer;
    background: #b45309; color: #fff; font-weight: 600;
    padding: .65rem 1.4rem; font-size: .95rem; border-radius: 8px;
}
@media (prefers-color-scheme: dark) { button { background: #f59e0b; color: #1c1200; } }
.mntn-notice {
    background: #ecfdf5; color: #065f46;
    border: 1px solid #a7f3d0; padding: .6rem .8rem; border-radius: 8px;
    margin-bottom: 1rem; font-size: .9rem;
}
@media (prefers-color-scheme: dark) { .mntn-notice { background: #12251c; color: #6ee7b7; border-color: #14532d; } }
.mntn-error {
    background: #ffe9e9; color: #b00020;
    border: 1px solid #ffb4b4; padding: .6rem .8rem; border-radius: 8px;
    margin-bottom: 1rem; font-size: .9rem;
}
@media (prefers-color-scheme: dark) { .mntn-error { background: #3a1212; color: #ffb4b4; border-color: #7f1d1d; } }
.hint { font-size: .78rem; color: #8a93a3; margin-top: .25rem; }
</style>
</head>
<body>
<div class="wrap">
    <a class="back" href="/admin/?action=plugins">&larr; Back to plugins</a>
    <h1>Maintenance mode</h1>
    <p class="hint">Site-wide maintenance page. When enabled, visitors see an HTTP 503 page until an authorised cookie is set.</p>
    <div class="card">
        {$noticeHtml}
        {$errorHtml}
        <form method="post" action="/admin/?action=settings&amp;plugin=maintenance">
            {$csrf}
            <label class="inline"><input type="checkbox" name="enabled" value="1"{$checked}> Maintenance mode is ON</label>

            <label for="m-password">New password</label>
            <input type="password" id="m-password" name="password" autocomplete="new-password">
            <label for="m-password2">Confirm password</label>
            <input type="password" id="m-password2" name="password2" autocomplete="new-password">
            <p class="hint">Leave blank to keep the current password.</p>

            <label for="m-title">Page title</label>
            <input type="text" id="m-title" name="title" value="{$e($title)}">
            <label for="m-message">Message</label>
            <textarea id="m-message" name="message">{$e($message)}</textarea>
            <label for="m-button">Button text</label>
            <input type="text" id="m-button" name="button" value="{$e($button)}">
            <label for="m-footer">Footer</label>
            <input type="text" id="m-footer" name="footer" value="{$e($footer)}">

            <button type="submit">Save settings</button>
        </form>
    </div>
</div>
</body>
</html>
HTML;
    }
}
