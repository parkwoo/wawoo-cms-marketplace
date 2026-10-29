<?php
namespace Wawoo\Plugin\PasswordGate;

/**
 * Core gate logic. Registered as the `core.boot` hook so it runs early,
 * before any template output. If the visitor isn't authorised, the
 * gate renders its own minimal HTML and exits.
 *
 * Hook contract:
 *   Hook::fire('core.boot')  -> Guard::handle() returns void; halts on
 *   unauthorised requests via exit().
 */
final class Guard
{
    public static function handle(): void
    {
        self::check();
    }
    public static function check(): void
    {
        if (PHP_SAPI === 'cli' || PHP_SAPI === 'cli-server') {
            // Allow CLI invocations (cron, generators, tests) to bypass.
            if (PHP_SAPI === 'cli') {
                return;
            }
        }

        $cfg = Config::all();

        // Opt-in gate: never lock unless an admin enabled it explicitly.
        if (empty($cfg['enabled'])) {
            return;
        }

        $path = self::currentPath();

        // Always-allowed paths (the gate itself + static asset).
        foreach ((array)$cfg['exempt_paths'] as $ex) {
            if ($path === $ex || strpos($path, $ex) === 0) {
                return;
            }
        }

        // Health probe & RSS opt-out: callers can append to exempt_paths.

        // Already authorised?
        if (self::isAuthorised($cfg)) {
            return;
        }

        // Handle POST attempt to /password.
        if ($path === '/password' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            self::handleAttempt($cfg);
            return; // handleAttempt() exits on its own.
        }

        // Render the gate.
        self::render($cfg, '');
        exit;
    }

    public static function currentPath(): string
    {
        $req = $_SERVER['REQUEST_URI'] ?? '/';
        $p = parse_url($req, PHP_URL_PATH) ?: '/';
        if ($p === '' || $p === false) {
            return '/';
        }
        return $p;
    }

    public static function isAuthorised(array $cfg): bool
    {
        $name = (string)$cfg['cookie_name'];
        $token = (string)($_COOKIE[$name] ?? '');
        if ($token === '' || strpos($token, '.') === false) {
            return false;
        }
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return false;
        }
        [$sig, $body] = $parts;
        $payload = self::unb64u($body);
        if (preg_match('~^exp:(\d+)$~', $payload, $m) !== 1) {
            return false;
        }
        $expected = hash_hmac('sha256', $payload, self::secret($cfg));
        if (!hash_equals($expected, $sig)) {
            return false;
        }
        return (int)$m[1] > time();
    }

    public static function authorise(array $cfg, bool $remember): void
    {
        $token = self::issueToken($cfg, $remember);
        $ttl   = $remember
            ? (int)$cfg['cookie_ttl']
            : (int)$cfg['session_ttl'];
        if (!headers_sent()) {
            setcookie((string)$cfg['cookie_name'], $token, [
                'expires'  => $ttl <= 0 ? 0 : time() + $ttl,
                'path'     => '/',
                'httponly' => true,
                'samesite' => 'Lax',
                'secure'   => !empty($_SERVER['HTTPS']),
            ]);
        }
        $_COOKIE[(string)$cfg['cookie_name']] = $token;
    }

    public static function clear(array $cfg): void
    {
        $name = (string)$cfg['cookie_name'];
        if (!headers_sent()) {
            setcookie($name, '', [
                'expires'  => time() - 3600,
                'path'     => '/',
                'httponly' => true,
                'samesite' => 'Lax',
                'secure'   => !empty($_SERVER['HTTPS']),
            ]);
        }
        unset($_COOKIE[$name]);
    }

    private static function secret(array $cfg): string
    {
        return (string)($cfg['secret'] ?? hash_hmac('sha256', 'wawoo-gate', (string)$cfg['password_hash']));
    }

    private static function issueToken(array $cfg, bool $remember): string
    {
        $ttl = $remember
            ? (int)$cfg['cookie_ttl']
            : (int)$cfg['session_ttl'];
        if ($ttl < 0) {
            $ttl = 0;
        }
        $payload = 'exp:' . (time() + $ttl);
        return hash_hmac('sha256', $payload, self::secret($cfg)) . '.' . self::b64u($payload);
    }

    private static function b64u(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private static function unb64u(string $enc): string
    {
        $pad = strlen($enc) % 4;
        if ($pad !== 0) {
            $enc .= str_repeat('=', 4 - $pad);
        }
        $dec = base64_decode(strtr($enc, '-_', '+/'), true);
        return $dec === false ? '' : $dec;
    }

    private static function clientIp(array $cfg): string
    {
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        if (!empty($cfg['trust_proxy'])) {
            $xff = (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
            if ($xff !== '') {
                $parts = explode(',', $xff);
                $first = trim($parts[0]);
                if ($first !== '') {
                    $ip = $first;
                }
            }
        }
        return preg_replace('/[^0-9a-fA-F.:]/', '', $ip);
    }

    private static function sanitizeNext(string $next): string
    {
        if ($next === '' || strpos($next, '//') === 0) {
            return '/';
        }
        if (preg_match('~^/[A-Za-z0-9_\-./?&=:%+#]*$~', $next) !== 1) {
            return '/';
        }
        return $next;
    }

    private static function handleAttempt(array $cfg): void
    {
        if (empty($cfg['enabled'])) {
            return;
        }
        $ipKey = 'attempts_' . self::clientIp($cfg);

        // Lockout check.
        $attempts = Store::get($ipKey, ['count' => 0, 'until' => 0]);
        if (!empty($attempts['until']) && $attempts['until'] > time()) {
            $wait = (int)$attempts['until'] - time();
            self::render($cfg, sprintf('Too many attempts. Try again in %d seconds.', $wait), 429);
            exit;
        }

        $pw      = (string)($_POST['pw'] ?? '');
        $hash    = (string)($cfg['password_hash'] ?? '');
        $remember = !empty($_POST['remember']);

        if ($hash === '') {
            self::render($cfg, 'Gate is not configured. Set PASSWORD_GATE["password"] in config.local.php.', 500);
            exit;
        }

        $ok = $pw !== '' && password_verify($pw, $hash);

        if (!$ok) {
            $attempts['count'] = (int)($attempts['count'] ?? 0) + 1;
            if ($attempts['count'] >= (int)$cfg['max_attempts']) {
                $attempts['count']  = 0;
                $attempts['until']  = time() + (int)$cfg['lockout_seconds'];
            }
            Store::set($ipKey, $attempts);
            // Constant-time-ish: re-hash on failure to avoid timing leak.
            password_verify($pw ?: 'x', $hash);
            self::render($cfg, 'Incorrect password.', 401);
            exit;
        }

        // Success: clear attempts, set cookie, redirect to original target.
        Store::forget($ipKey);
        self::authorise($cfg, $remember);

        $next = self::sanitizeNext((string)($_POST['next'] ?? '/'));
        if ($next === '/password') {
            $next = '/';
        }
        header('Location: ' . $next, true, 303);
        exit;
    }

    public static function render(array $cfg, string $error, int $status = 200): void
    {
        http_response_code($status);
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');

        $title       = htmlspecialchars((string)$cfg['title'], ENT_QUOTES, 'UTF-8');
        $message     = htmlspecialchars((string)$cfg['message'], ENT_QUOTES, 'UTF-8');
        $placeholder = htmlspecialchars((string)$cfg['placeholder'], ENT_QUOTES, 'UTF-8');
        $button      = htmlspecialchars((string)$cfg['button'], ENT_QUOTES, 'UTF-8');
        $footer      = htmlspecialchars((string)$cfg['footer'], ENT_QUOTES, 'UTF-8');
        $next        = htmlspecialchars(self::safeNext(), ENT_QUOTES, 'UTF-8');
        $errHtml     = $error !== ''
            ? '<div class="gate-error" role="alert">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</div>'
            : '';
        $rememberEnabled = !empty($cfg['remember_days']);

        echo self::html($title, $message, $placeholder, $button, $footer, $next, $errHtml, $rememberEnabled, $status);
    }

    private static function safeNext(): string
    {
        return self::sanitizeNext((string)($_GET['next'] ?? $_POST['next'] ?? '/'));
    }

    private static function html(
        string $title, string $message, string $placeholder, string $button,
        string $footer, string $next, string $errHtml, bool $rememberEnabled, int $status
    ): string {
        $rememberChecked = !empty($_POST['remember']) ? 'checked' : '';
        $rememberRow = $rememberEnabled
            ? '<label class="gate-remember"><input type="checkbox" name="remember" value="1" ' . $rememberChecked . '> Remember me</label>'
            : '';
        return <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>{$title}</title>
<style>
:root { color-scheme: light dark; }
body {
    margin: 0; min-height: 100vh;
    display: flex; align-items: center; justify-content: center;
    font-family: -apple-system, system-ui, "Segoe UI", "Helvetica Neue", sans-serif;
    background: #f5f5f7; color: #1d1d1f;
}
@media (prefers-color-scheme: dark) {
    body { background: #0b0b0d; color: #f5f5f7; }
}
.gate-card {
    width: min(92vw, 380px);
    padding: 2rem 1.75rem 1.5rem;
    background: #ffffff;
    border-radius: 18px;
    box-shadow: 0 30px 60px rgba(0,0,0,.12), 0 2px 4px rgba(0,0,0,.04);
    text-align: center;
}
@media (prefers-color-scheme: dark) {
    .gate-card { background: #161618; box-shadow: 0 30px 60px rgba(0,0,0,.5); }
}
.gate-card h1 {
    margin: 0 0 .35rem;
    font-size: 1.5rem;
    letter-spacing: -.01em;
}
.gate-card p.gate-msg {
    margin: 0 0 1.25rem;
    color: #6e6e73;
    font-size: .95rem;
}
.gate-form { display: flex; flex-direction: column; gap: .75rem; }
.gate-form input[type="password"] {
    appearance: none;
    width: 100%;
    padding: .75rem .9rem;
    font-size: 1rem;
    border: 1px solid #d2d2d7;
    border-radius: 10px;
    background: #fff;
    color: inherit;
    box-sizing: border-box;
}
.gate-form input[type="password"]:focus {
    outline: none;
    border-color: #0071e3;
    box-shadow: 0 0 0 4px rgba(0,113,227,.18);
}
.gate-form button {
    appearance: none;
    border: 0;
    background: #1d1d1f;
    color: #fff;
    padding: .75rem 1rem;
    font-size: 1rem;
    font-weight: 600;
    border-radius: 10px;
    cursor: pointer;
}
.gate-form button:hover { background: #000; }
.gate-remember {
    display: flex; align-items: center; justify-content: center;
    gap: .4rem; font-size: .85rem; color: #6e6e73;
    margin-top: .25rem;
}
.gate-error {
    background: #ffe9e9; color: #b00020;
    padding: .55rem .75rem; border-radius: 8px;
    font-size: .9rem; margin-bottom: .75rem;
}
@media (prefers-color-scheme: dark) {
    .gate-error { background: #3a1212; color: #ffb4b4; }
}
.gate-footer {
    margin-top: 1.25rem;
    color: #8e8e93;
    font-size: .75rem;
}
</style>
</head>
<body>
<main class="gate-card" role="main">
    <h1>{$title}</h1>
    <p class="gate-msg">{$message}</p>
    {$errHtml}
    <form class="gate-form" method="post" action="/password" autocomplete="on">
        <input type="hidden" name="next" value="{$next}">
        <input type="password" name="pw" placeholder="{$placeholder}" required autofocus autocomplete="current-password">
        {$rememberRow}
        <button type="submit">{$button}</button>
    </form>
    <div class="gate-footer">{$footer}</div>
</main>
</body>
</html>
HTML;
    }
}
