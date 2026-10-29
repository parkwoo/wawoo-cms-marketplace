<?php
namespace Wawoo\Plugin\AdminTotp;

/**
 * Persistent admin state (TOTP secret, backup codes, last-login time).
 * Stored as JSON at plugins/admin-totp/data/admin.json.
 *
 * Login rate-limit counters are persisted as small per-key JSON files in the
 * same data directory (`attempts_<ip>.json`), updated under flock() so
 * concurrent brute-force attempts cannot race each other.
 */
final class Store
{
    public static function load(): array
    {
        $f = Config::dataPath();
        if (!is_file($f)) {
            return [];
        }
        $data = json_decode((string)file_get_contents($f), true);
        return is_array($data) ? $data : [];
    }

    public static function save(array $data): void
    {
        $dir = dirname(Config::dataPath());
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $tmp = Config::dataPath() . '.tmp';
        file_put_contents($tmp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        rename($tmp, Config::dataPath());
    }

    public static function hasSecret(): bool
    {
        $s = self::load();
        return !empty($s['totp_secret']);
    }

    /**
     * Persist the given secret (or a freshly generated one) together with a
     * fresh set of backup codes. Returns the stored secret.
     */
    public static function enableTotp(?string $secret = null): string
    {
        $secret = is_string($secret) && $secret !== '' ? $secret : Totp::generateSecret();
        $codes = self::generateBackupCodes();
        self::save([
            'totp_secret'  => $secret,
            'backup_codes' => $codes,
            'enabled_at'   => time(),
        ]);
        return $secret;
    }

    public static function disableTotp(): void
    {
        $f = Config::dataPath();
        if (is_file($f)) {
            @unlink($f);
        }
    }

    public static function getSecret(): ?string
    {
        $s = self::load();
        return $s['totp_secret'] ?? null;
    }

    public static function consumeBackupCode(string $code): bool
    {
        $s = self::load();
        $codes = $s['backup_codes'] ?? [];
        foreach ($codes as $i => $c) {
            if (hash_equals($c, strtoupper(trim($code)))) {
                unset($codes[$i]);
                $s['backup_codes'] = array_values($codes);
                self::save($s);
                return true;
            }
        }
        return false;
    }

    public static function remainingBackupCodes(): int
    {
        return count(self::load()['backup_codes'] ?? []);
    }

    // ---- rate limiting ----

    public static function rateFile(string $key): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9_.:-]/', '_', $key);
        return dirname(Config::dataPath()) . '/' . $safe . '.json';
    }

    /**
     * Current attempt state: ['count' => int, 'until' => int (0 when not locked)].
     */
    public static function rateState(string $key): array
    {
        $f = self::rateFile($key);
        if (!is_file($f)) {
            return ['count' => 0, 'until' => 0];
        }
        $h = @fopen($f, 'rb');
        if ($h === false) {
            return ['count' => 0, 'until' => 0];
        }
        $locked = flock($h, LOCK_SH);
        $raw = stream_get_contents($h);
        if ($locked) {
            flock($h, LOCK_UN);
        }
        fclose($h);
        $data = json_decode((string)$raw, true);
        return is_array($data) ? $data : ['count' => 0, 'until' => 0];
    }

    public static function rateLocked(string $key, int $now = 0): bool
    {
        $s = self::rateState($key);
        return (int)($s['until'] ?? 0) > ($now > 0 ? $now : time());
    }

    /**
     * Record one failed attempt under flock(). When the attempt counter
     * reaches $maxAttempts the key is locked for $lockoutSeconds. Returns the
     * resulting state.
     */
    public static function rateHit(string $key, int $maxAttempts = 5, int $lockoutSeconds = 300): array
    {
        $f = self::rateFile($key);
        $dir = dirname($f);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $h = @fopen($f, 'c+b');
        if ($h === false) {
            return ['count' => 0, 'until' => 0];
        }
        if (!flock($h, LOCK_EX)) {
            fclose($h);
            return ['count' => 0, 'until' => 0];
        }
        $data = ['count' => 0, 'until' => 0];
        $raw = stream_get_contents($h);
        $decoded = json_decode((string)$raw, true);
        if (is_array($decoded)) {
            $data = $decoded;
        }
        $now = time();
        if ((int)($data['until'] ?? 0) > $now) {
            // Already locked out: do not extend the window on further attempts.
            $result = ['count' => (int)($data['count'] ?? 0), 'until' => (int)($data['until'] ?? 0)];
            flock($h, LOCK_UN);
            fclose($h);
            return $result;
        }
        $count = (int)($data['count'] ?? 0) + 1;
        if ($count >= $maxAttempts) {
            $result = ['count' => 0, 'until' => $now + $lockoutSeconds];
        } else {
            $result = ['count' => $count, 'until' => 0];
        }
        ftruncate($h, 0);
        rewind($h);
        fwrite($h, (string)json_encode($result));
        fflush($h);
        flock($h, LOCK_UN);
        fclose($h);
        return $result;
    }

    public static function rateClear(string $key): void
    {
        $f = self::rateFile($key);
        if (is_file($f)) {
            @unlink($f);
        }
    }

    private static function generateBackupCodes(): array
    {
        $n = (int)Config::all()['backup_codes'];
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $out[] = strtoupper(bin2hex(random_bytes(4))); // 8 hex chars
        }
        return $out;
    }
}
