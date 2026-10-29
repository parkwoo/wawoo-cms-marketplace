<?php
namespace Wawoo\Plugin\Maintenance;

/**
 * Maintenance mode guard. Registered as the `core.boot` hook so it runs
 * before any template output. When maintenance is enabled and the visitor
 * is not authorised, the guard answers with an SEO-friendly HTTP 503 page
 * (with Retry-After) and exits.
 *
 * Hook contract:
 *   Hook::fire('core.boot')  -> Guard::handle() returns void; halts on
 *   unauthorised requests via exit().
 *
 * Maintenance is OFF unless both `enabled` and a `password_hash` are set.
 */
final class Guard
{
    public static function handle(): void
    {
        self::check();
    }

    public static function check(): void
    {
        if (PHP_SAPI === 'cli') {
            // Allow CLI invocations (cron, generators, tests) to bypass.
            // cli-server (php -S) still proceeds so real requests hit the page.
            return;
        }

        $cfg = Config::all();

        // Maintenance is OFF unless enabled AND a password is configured.
        if (empty($cfg['enabled']) || empty($cfg['password_hash'])) {
            return;
        }

        $path = self::currentPath();

        // Always-allowed paths (admin console, shared gate stylesheet).
        foreach ((array)$cfg['exempt_paths'] as $ex) {
            if ($ex !== '' && ($path === $ex || strpos($path, (string)$ex) === 0)) {
                return;
            }
        }

        // Already authorised via the maintenance cookie?
        if (self::isAuthorised($cfg)) {
            return;
        }

        // Handle a password POST to /maintenance.
        if ($path === '/maintenance' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            self::handleAttempt($cfg);
            return; // handleAttempt() exits on its own.
        }

        // Otherwise answer with a 503 maintenance page.
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

    public static function authorise(array $cfg): void
    {
        $name = (string)$cfg['cookie_name'];
        $ttl  = (int)$cfg['cookie_ttl'];
        $token = self::issueToken($cfg, $ttl);
        if (!headers_sent()) {
            setcookie($name, $token, [
                'expires'  => $ttl <= 0 ? 0 : time() + $ttl,
                'path'     => '/',
                'httponly' => true,
                'samesite' => 'Lax',
                'secure'   => !empty($_SERVER['HTTPS']),
            ]);
        }
        $_COOKIE[$name] = $token;
    }

    /**
     * Render the maintenance page. 503 + Retry-After tells crawlers the
     * outage is temporary (SEO friendly); robots noindex keeps the page out
     * of indexes while it is up.
     */
    public static function render(array $cfg, string $error, int $status = 503): void
    {
        http_response_code($status);
        header('Retry-After: 3600');
        header('Cache-Control: no-store');

        $title   = htmlspecialchars((string)$cfg['title'], ENT_QUOTES, 'UTF-8');
        $message = htmlspecialchars((string)$cfg['message'], ENT_QUOTES, 'UTF-8');
        $button  = htmlspecialchars((string)$cfg['button'], ENT_QUOTES, 'UTF-8');
        $footer  = htmlspecialchars((string)$cfg['footer'], ENT_QUOTES, 'UTF-8');
        $errHtml = $error !== ''
            ? '<div class="mntn-error" role="alert">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</div>'
            : '';

        echo self::html($title, $message, $button, $footer, $errHtml);
    }

    private static function handleAttempt(array $cfg): void
    {
        $ipKey = 'attempts_' . self::clientIp($cfg);

        // Lockout check.
        $attempts = Store::get($ipKey, ['count' => 0, 'until' => 0]);
        if (!empty($attempts['until']) && $attempts['until'] > time()) {
            $wait = (int)$attempts['until'] - time();
            self::render($cfg, sprintf('Too many attempts. Try again in %d seconds.', $wait), 429);
            exit;
        }

        $pw   = (string)($_POST['pw'] ?? '');
        $hash = (string)$cfg['password_hash'];
        $ok   = $pw !== '' && password_verify($pw, $hash);

        if (!$ok) {
            $attempts['count'] = (int)($attempts['count'] ?? 0) + 1;
            if ($attempts['count'] >= (int)$cfg['max_attempts']) {
                $attempts['count'] = 0;
                $attempts['until'] = time() + (int)$cfg['lockout_seconds'];
            }
            Store::set($ipKey, $attempts);
            // Constant-time-ish: re-hash on failure to avoid timing leaks.
            password_verify($pw ?: 'x', $hash);
            self::render($cfg, 'Incorrect password.', 401);
            exit;
        }

        // Success: clear attempts, set cookie, bounce to the homepage.
        Store::forget($ipKey);
        self::authorise($cfg);
        header('Location: /', true, 303);
        exit;
    }

    private static function secret(array $cfg): string
    {
        return (string)($cfg['secret'] ?? hash_hmac('sha256', 'wawoo-maintenance', (string)$cfg['password_hash']));
    }

    private static function issueToken(array $cfg, int $ttl): string
    {
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

    private static function html(
        string $title, string $message, string $button, string $footer, string $errHtml
    ): string {
        $footerHtml = $footer !== ''
            ? '<div class="mntn-footer">' . $footer . '</div>'
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
    background: #f6f7f9; color: #14161a;
}
@media (prefers-color-scheme: dark) {
    body { background: #0d0f12; color: #f2f4f8; }
}
.mntn-card {
    width: min(92vw, 420px);
    padding: 2.25rem 2rem 1.75rem;
    background: #ffffff;
    border-radius: 16px;
    box-shadow: 0 24px 60px rgba(15, 23, 42, .12), 0 2px 4px rgba(15, 23, 42, .05);
    text-align: center;
    border-top: 4px solid #f59e0b;
}
@media (prefers-color-scheme: dark) {
    .mntn-card { background: #171a20; box-shadow: 0 24px 60px rgba(0,0,0,.55); }
}
.mntn-icon {
    width: 56px; height: 56px;
    margin: 0 auto 1rem;
    display: flex; align-items: center; justify-content: center;
    background: #fef3c7; color: #b45309;
    border-radius: 50%;
}
@media (prefers-color-scheme: dark) {
    .mntn-icon { background: #3a2f14; color: #fbbf24; }
}
.mntn-icon svg { width: 28px; height: 28px; }
.mntn-card h1 {
    margin: 0 0 .4rem;
    font-size: 1.45rem;
    letter-spacing: -.01em;
}
.mntn-card p.mntn-msg {
    margin: 0 0 1.4rem;
    color: #5b6472;
    font-size: .95rem;
    line-height: 1.5;
}
@media (prefers-color-scheme: dark) {
    .mntn-card p.mntn-msg { color: #9aa3b2; }
}
.mntn-form { display: flex; flex-direction: column; gap: .75rem; }
.mntn-form input[type="password"] {
    appearance: none;
    width: 100%;
    padding: .75rem .9rem;
    font-size: 1rem;
    border: 1px solid #d3d9e1;
    border-radius: 10px;
    background: #fff;
    color: inherit;
    box-sizing: border-box;
}
@media (prefers-color-scheme: dark) {
    .mntn-form input[type="password"] { background: #10131a; border-color: #2b313c; }
}
.mntn-form input[type="password"]:focus {
    outline: none;
    border-color: #f59e0b;
    box-shadow: 0 0 0 4px rgba(245, 158, 11, .2);
}
.mntn-form button {
    appearance: none;
    border: 0;
    background: #b45309;
    color: #fff;
    padding: .75rem 1rem;
    font-size: 1rem;
    font-weight: 600;
    border-radius: 10px;
    cursor: pointer;
}
.mntn-form button:hover { background: #92400e; }
@media (prefers-color-scheme: dark) {
    .mntn-form button { background: #f59e0b; color: #1c1200; }
    .mntn-form button:hover { background: #fbbf24; }
}
.mntn-error {
    background: #ffe9e9; color: #b00020;
    padding: .55rem .75rem; border-radius: 8px;
    font-size: .9rem; margin-bottom: .85rem;
}
@media (prefers-color-scheme: dark) {
    .mntn-error { background: #3a1212; color: #ffb4b4; }
}
.mntn-note {
    margin-top: 1.1rem;
    font-size: .78rem;
    color: #8a93a3;
}
.mntn-footer {
    margin-top: 1.25rem;
    color: #8a93a3;
    font-size: .78rem;
}
</style>
</head>
<body>
<main class="mntn-card" role="main">
    <div class="mntn-icon" aria-hidden="true">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
    </div>
    <h1>{$title}</h1>
    <p class="mntn-msg">{$message}</p>
    {$errHtml}
    <form class="mntn-form" method="post" action="/maintenance" autocomplete="on">
        <input type="password" name="pw" placeholder="Password" required autofocus autocomplete="current-password">
        <button type="submit">{$button}</button>
    </form>
    <p class="mntn-note">This page requires a password. Authorised visitors will be redirected automatically.</p>
    {$footerHtml}
</main>
</body>
</html>
HTML;
    }
}
