<?php
namespace Wawoo\Plugin\AdminTotp;

use Wawoo\Core\Hook;

/**
 * Replaces the built-in admin/index.php gate with a password + optional
 * TOTP flow. Hooks `core.request` so it intercepts /admin/ requests
 * before any other handler fires.
 *
 * Hardening included here:
 *  - session id regeneration after every successful auth step,
 *  - absolute session lifetime enforced from `session_ttl`,
 *  - brute-force rate limiting (password, TOTP code and backup code)
 *    backed by Store::rate*() counters.
 */
final class Guard
{
    public function handle(?string $root = null): void
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        if (!self::isAdminPath($path)) {
            return;
        }
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $cfg = Config::all();

        // 1. Password must be configured.
        if (empty($cfg['password_hash'])) {
            http_response_code(503);
            echo '<h1>Admin disabled</h1><p>Set WAWOO_ADMIN["password_hash"] in config.local.php.</p>';
            exit;
        }

        $action = $_GET['action'] ?? 'dashboard';

        // Logout always allowed.
        if ($action === 'logout') {
            $_SESSION = [];
            session_destroy();
            header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
            exit;
        }

        // 2. Absolute session lifetime.
        if (!empty($_SESSION['wawoo_admin_authed'])) {
            $at  = (int)($_SESSION['wawoo_admin_at'] ?? 0);
            $ttl = max(1, (int)($cfg['session_ttl'] ?? 1800));
            if ($at > 0 && time() - $at > $ttl) {
                self::destroySession();
            }
        }

        $isAuthed = !empty($_SESSION['wawoo_admin_authed']);
        $totpOk   = !empty($_SESSION['wawoo_admin_totp_ok']);

        if (!$isAuthed) {
            self::renderPasswordPrompt($cfg);
            exit;
        }
        if (Store::hasSecret() && !$totpOk) {
            self::renderTotpPrompt($cfg);
            exit;
        }

        // Authed. TOTP verified (or not configured).
        Hook::fire('admin.authed', $_SESSION);
    }

    private static function isAdminPath(string $path): bool
    {
        // Anything under /admin
        return strpos($path, '/admin') === 0;
    }

    private static function destroySession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
            @session_start();
        } else {
            $_SESSION = [];
        }
    }

    private static function rateKey(): string
    {
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        $id = $ip === '' ? 'local' : preg_replace('/[^a-zA-Z0-9_.:-]/', '_', $ip);
        return 'attempts_' . $id;
    }

    /** Remaining lockout seconds for the current client, or 0 when unlocked. */
    private static function lockoutWait(array $cfg): int
    {
        $rl = Store::rateState(self::rateKey());
        return (int)($rl['until'] ?? 0) - time();
    }

    private static function renderPasswordPrompt(array $cfg): void
    {
        $err = $_SESSION['wawoo_admin_err'] ?? null;
        unset($_SESSION['wawoo_admin_err']);

        $wait = self::lockoutWait($cfg);
        if ($wait > 0) {
            self::renderForm('Password', 'password', 'pw', sprintf('Too many attempts. Try again in %d seconds.', $wait), 429);
            exit;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['wawoo_admin_step'] ?? '') === 'password') {
            $pw   = (string)($_POST['pw'] ?? '');
            $hash = (string)$cfg['password_hash'];
            if ($pw !== '' && password_verify($pw, $hash)) {
                Store::rateClear(self::rateKey());
                self::markPasswordAuthed();
                header('Location: ' . $_SERVER['REQUEST_URI']);
                exit;
            }
            // Constant-time-ish: keep verifying.
            password_verify($pw ?: 'x', $hash);
            Store::rateHit(self::rateKey(), (int)($cfg['max_attempts'] ?? 5), (int)($cfg['lockout_seconds'] ?? 300));
            $err = 'Incorrect password.';
            $_SESSION['wawoo_admin_err'] = $err;
        }
        self::renderForm('Password', 'password', 'pw', $err);
    }

    private static function renderTotpPrompt(array $cfg): void
    {
        $err = $_SESSION['wawoo_admin_err'] ?? null;
        unset($_SESSION['wawoo_admin_err']);

        $wait = self::lockoutWait($cfg);
        if ($wait > 0) {
            self::renderForm('Authenticator code (or backup code)', 'totp', 'code', sprintf('Too many attempts. Try again in %d seconds.', $wait), 429);
            exit;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['wawoo_admin_step'] ?? '') === 'totp') {
            $code = (string)($_POST['code'] ?? '');
            $secret = Store::getSecret();
            $validTotp = $secret !== null && Totp::verify($secret, $code, (int)$cfg['totp_window'], (int)$cfg['totp_period'], (int)$cfg['totp_digits']);
            $validBackup = !$validTotp && Store::consumeBackupCode($code);
            if ($validTotp || $validBackup) {
                Store::rateClear(self::rateKey());
                $_SESSION['wawoo_admin_totp_ok'] = true;
                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_regenerate_id(true);
                }
                header('Location: ' . $_SERVER['REQUEST_URI']);
                exit;
            }
            Store::rateHit(self::rateKey(), (int)($cfg['max_attempts'] ?? 5), (int)($cfg['lockout_seconds'] ?? 300));
            $err = 'Invalid code.';
            $_SESSION['wawoo_admin_err'] = $err;
        }
        self::renderForm('Authenticator code (or backup code)', 'totp', 'code', $err);
    }

    private static function markPasswordAuthed(): void
    {
        $_SESSION['wawoo_admin_authed'] = true;
        $_SESSION['wawoo_admin_at'] = time();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    private static function renderForm(string $label, string $step, string $field, ?string $err, int $status = 401): void
    {
        http_response_code($status);
        ?>
        <!doctype html><meta charset="utf-8"><title>Admin sign-in</title>
        <style>
        body { font-family: system-ui, sans-serif; background: #f5f5f7; min-height: 100vh; margin: 0; display: flex; align-items: center; justify-content: center; }
        .card { background: #fff; padding: 2rem; border-radius: 18px; width: min(92vw, 360px); box-shadow: 0 20px 40px rgba(0,0,0,.08); }
        h1 { margin: 0 0 .25rem; font-size: 1.4rem; }
        p { color: #6e6e73; margin: 0 0 1.25rem; font-size: .9rem; }
        input[type="password"], input[type="text"] { width: 100%; padding: .75rem; font-size: 1rem; border: 1px solid #d2d2d7; border-radius: 10px; box-sizing: border-box; }
        input:focus { outline: none; border-color: #0071e3; box-shadow: 0 0 0 4px rgba(0,113,227,.15); }
        button { width: 100%; padding: .75rem; background: #1d1d1f; color: #fff; border: 0; border-radius: 10px; font-weight: 600; cursor: pointer; margin-top: 1rem; }
        .err { color: #b00020; background: #ffe9e9; padding: .5rem .75rem; border-radius: 8px; margin-bottom: 1rem; font-size: .9rem; }
        </style>
        <form class="card" method="post" autocomplete="on">
            <h1>Admin sign-in</h1>
            <p><?= htmlspecialchars($label) ?></p>
            <?php if ($err): ?><div class="err"><?= htmlspecialchars($err) ?></div><?php endif; ?>
            <input type="hidden" name="wawoo_admin_step" value="<?= htmlspecialchars($step) ?>">
            <input type="<?= $field === 'pw' ? 'password' : 'text' ?>" name="<?= htmlspecialchars($field) ?>" required autofocus
                   autocomplete="<?= $field === 'pw' ? 'current-password' : 'one-time-code' ?>"
                   inputmode="numeric" pattern="[0-9A-Za-z\s]*">
            <button type="submit">Continue</button>
        </form>
        <?php
    }
}
