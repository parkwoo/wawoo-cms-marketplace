<?php
namespace Wawoo\Plugin\PasswordGate;

/**
 * Tiny file-backed store for rate-limit counters. Persists across requests
 * so brute-force attempts are throttled even with stateless cookies.
 */
final class Store
{
    private static function dir(): string
    {
        $dir = \Wawoo\Core\Cache::class;
        // We piggy-back on the core cache dir; gate keys are namespaced.
        return rtrim(\CACHE_DIR, '/\\') . DIRECTORY_SEPARATOR;
    }

    private static function file(string $key): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);
        return self::dir() . 'gate_' . $safe . '.json';
    }

    public static function get(string $key, $default = null)
    {
        $f = self::file($key);
        if (!is_file($f)) {
            return $default;
        }
        $data = json_decode((string)file_get_contents($f), true);
        return $data ?? $default;
    }

    public static function set(string $key, $value): void
    {
        $f    = self::file($key);
        $lock = $f . '.lock';
        $tmp  = $f . '.' . bin2hex(random_bytes(4)) . '.tmp';
        $fh   = @fopen($lock, 'c');
        if ($fh === false) {
            @file_put_contents($f, json_encode($value));
            return;
        }
        flock($fh, LOCK_EX);
        $ok = @file_put_contents($tmp, json_encode($value)) !== false;
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

    public static function forget(string $key): void
    {
        $f = self::file($key);
        if (is_file($f)) {
            @unlink($f);
        }
    }
}
