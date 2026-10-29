<?php
namespace Wawoo\Plugin\AdminTotp;

/**
 * Provides an admin page to:
 *  - enable TOTP (QR + manual secret, first-code verification before enabling)
 *  - disable TOTP (POST + CSRF)
 *  - regenerate backup codes / secret (POST + CSRF)
 *
 * Hooks `admin.authed` so it shows up next to other admin tools.
 *
 * Enabling never persists on a plain GET: a fresh secret is only held in the
 * session (`wawoo_totp_pending`) until the operator submits a valid TOTP code
 * (POST + CSRF). Only then is Store::enableTotp() called with that secret.
 */
final class Setup
{
    public function handle(...$args): void
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        if (strpos($path, '/admin') !== 0) {
            return;
        }
        $action = $_GET['action'] ?? 'dashboard';
        if (!in_array($action, ['totp', 'totp-disable', 'totp-regen'], true)) {
            return;
        }
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $cfg  = Config::all();
        $issuer = (string)$cfg['issuer'];
        $self = (string)strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
        if ($self === '') {
            $self = '/';
        }
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if ($action === 'totp-disable') {
            self::requirePost($method);
            self::requireCsrf();
            Store::disableTotp();
            unset(
                $_SESSION['wawoo_admin_totp_ok'],
                $_SESSION['wawoo_totp_pending'],
                $_SESSION['wawoo_admin_new_codes']
            );
            header('Location: ' . $self);
            exit;
        }

        if ($action === 'totp-regen') {
            self::requirePost($method);
            self::requireCsrf();
            Store::enableTotp(); // fresh secret + fresh backup codes
            $_SESSION['wawoo_admin_new_codes'] = Store::load()['backup_codes'] ?? [];
            header('Location: ' . $self . '?action=totp');
            exit;
        }

        // ---- action == totp ----
        $err = null;

        if ($method === 'POST') {
            $hasCode    = array_key_exists('code', $_POST);
            $hasConfirm = !empty($_POST['confirm']);
            if (!$hasCode && !$hasConfirm) {
                // Unverified POST (e.g. an old GET-style enable): no side effects.
                self::deny();
            }
            self::requireCsrf();

            if ($hasConfirm) {
                // Operator confirmed the one-time backup-code display.
                unset($_SESSION['wawoo_admin_new_codes']);
                header('Location: ' . $self . '?action=totp');
                exit;
            }

            $stored  = Store::getSecret();
            $pending = (string)($_SESSION['wawoo_totp_pending'] ?? '');
            if ($stored !== null || $pending === '') {
                // Nothing left to enable.
                header('Location: ' . $self . '?action=totp');
                exit;
            }
            $code = preg_replace('/\s+/', '', (string)($_POST['code'] ?? ''));
            if (Totp::verify($pending, (string)$code, (int)$cfg['totp_window'], (int)$cfg['totp_period'], (int)$cfg['totp_digits'])) {
                Store::enableTotp($pending);
                unset($_SESSION['wawoo_totp_pending']);
                $_SESSION['wawoo_admin_totp_ok'] = true;
                $_SESSION['wawoo_admin_new_codes'] = Store::load()['backup_codes'] ?? [];
                header('Location: ' . $self . '?action=totp');
                exit;
            }
            $err = 'Invalid code. Please try again.';
        }

        $stored = Store::getSecret();

        if ($stored === null) {
            // First-time setup: hold the secret in the session only.
            $pending = (string)($_SESSION['wawoo_totp_pending'] ?? '');
            if ($pending === '') {
                $pending = Totp::generateSecret();
                $_SESSION['wawoo_totp_pending'] = $pending;
            }
            self::renderEnable((string)$pending, $self, $issuer, $cfg, $err);
            exit;
        }

        self::renderManage((string)$stored, $self, $issuer, $cfg);
        exit;
    }

    private static function renderEnable(string $pending, string $self, string $issuer, array $cfg, ?string $err): void
    {
        $uri = Totp::provisioningUri($pending, 'admin', $issuer, (int)$cfg['totp_period'], (int)$cfg['totp_digits']);
        $qr  = Totp::qrDataUri($uri, 200);
        ?>
        <div class="clay-card" style="margin-top:1rem;max-width:560px">
            <h2>Two-factor authentication (TOTP)</h2>
            <p>Scan this QR code with Google Authenticator, Authy, 1Password, Bitwarden, etc., or add the account manually using the secret / URI below.</p>
            <p style="text-align:center"><img src="<?= htmlspecialchars($qr) ?>" alt="TOTP setup QR" style="width:200px;height:200px"></p>
            <p><strong>Secret:</strong> <code style="background:#FFF8E5;padding:.25rem .5rem;border-radius:.5rem"><?= htmlspecialchars($pending) ?></code></p>
            <p><strong>otpauth URI:</strong> <code style="background:#FFF8E5;padding:.25rem .5rem;border-radius:.5rem;word-break:break-all"><?= htmlspecialchars($uri) ?></code></p>
            <p><strong>Next step:</strong> 2FA is only enabled once you prove you have the secret. Enter the current 6-digit code from your authenticator app below.</p>
            <?php if ($err): ?><p style="color:#b00020"><?= htmlspecialchars($err) ?></p><?php endif; ?>
            <form method="post" action="<?= htmlspecialchars($self) ?>?action=totp">
                <?= Csrf::field() ?>
                <input type="text" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                       placeholder="6-digit code" required autofocus
                       style="width:100%;padding:.75rem;font-size:1rem;box-sizing:border-box;border:1px solid #d2d2d7;border-radius:10px">
                <button type="submit" style="margin-top:.75rem">Enable 2FA</button>
            </form>
            <p style="margin-top:1rem"><a href="<?= htmlspecialchars($self) ?>">&larr; Back to dashboard</a></p>
        </div>
        <?php
    }

    private static function renderManage(string $secret, string $self, string $issuer, array $cfg): void
    {
        $newCodes = $_SESSION['wawoo_admin_new_codes'] ?? null;
        if (is_array($newCodes)) {
            $codes = $newCodes;
        } else {
            $codes = Store::load()['backup_codes'] ?? [];
        }
        $uri = Totp::provisioningUri($secret, 'admin', $issuer, (int)$cfg['totp_period'], (int)$cfg['totp_digits']);
        $qr  = Totp::qrDataUri($uri, 160);
        $remaining = Store::remainingBackupCodes();
        ?>
        <div class="clay-card" style="margin-top:1rem;max-width:560px">
            <h2>Two-factor authentication (TOTP)</h2>
            <p style="text-align:center"><img src="<?= htmlspecialchars($qr) ?>" alt="TOTP QR" style="width:160px;height:160px"></p>
            <p><strong>Secret:</strong> <code style="background:#FFF8E5;padding:.25rem .5rem;border-radius:.5rem"><?= htmlspecialchars($secret) ?></code></p>
            <p><strong>otpauth URI:</strong> <code style="background:#FFF8E5;padding:.25rem .5rem;border-radius:.5rem;word-break:break-all"><?= htmlspecialchars($uri) ?></code></p>

            <?php if (is_array($newCodes)): ?>
                <p style="color:#006600"><strong>Write these backup codes down now.</strong> Each can be used once to sign in if you lose your authenticator.</p>
                <ul style="columns:2;list-style:none;padding:0">
                    <?php foreach ($codes as $c): ?>
                        <li><code><?= htmlspecialchars((string)$c) ?></code></li>
                    <?php endforeach; ?>
                </ul>
                <form method="post" action="<?= htmlspecialchars($self) ?>?action=totp">
                    <?= Csrf::field() ?>
                    <button type="submit" name="confirm" value="1">I have saved my backup codes</button>
                </form>
            <?php else: ?>
                <p><strong>Remaining backup codes: <?= (int)$remaining ?></strong></p>
                <?php if ($remaining > 0): ?>
                    <ul style="columns:2;list-style:none;padding:0">
                        <?php foreach ($codes as $c): ?>
                            <li><code><?= htmlspecialchars((string)$c) ?></code></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <form method="post" action="<?= htmlspecialchars($self) ?>?action=totp-disable" style="display:inline" onsubmit="return confirm('Disable 2FA?')">
                    <?= Csrf::field() ?>
                    <button type="submit">Disable 2FA</button>
                </form>
                <form method="post" action="<?= htmlspecialchars($self) ?>?action=totp-regen" style="display:inline" onsubmit="return confirm('Generate a new secret and backup codes? Your current authenticator entry will stop working.')">
                    <?= Csrf::field() ?>
                    <button type="submit">Regenerate secret &amp; backup codes</button>
                </form>
            <?php endif; ?>
            <p style="margin-top:1rem"><a href="<?= htmlspecialchars($self) ?>">&larr; Back to dashboard</a></p>
        </div>
        <?php
    }

    private static function requirePost(string $method): void
    {
        if ($method !== 'POST') {
            self::deny();
        }
    }

    private static function requireCsrf(): void
    {
        if (!Csrf::check(isset($_POST['csrf']) ? (string)$_POST['csrf'] : null)) {
            self::deny();
        }
    }

    private static function deny(): void
    {
        http_response_code(403);
        echo '<!doctype html><meta charset="utf-8"><title>Forbidden</title><h1 style="font-family:sans-serif">403 Forbidden</h1><p style="font-family:sans-serif">Invalid or missing CSRF token.</p>';
        exit;
    }
}
