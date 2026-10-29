<?php
namespace Wawoo\Plugin\AdminTotp;

/**
 * RFC 6238 TOTP (Time-based One-Time Password).
 *
 * - No external dependencies; uses hash_hmac and pack.
 * - Supports configurable digits, period, and clock-skew window.
 * - 2FA QR provisioning URI is generated via otpauth:// (compatible
 *   with Google Authenticator, Authy, 1Password, Bitwarden, etc.).
 */
final class Totp
{
    /**
     * Generate a TOTP code for the given secret at the given timestamp.
     */
    public static function code(string $secret, ?int $timestamp = null, int $period = 30, int $digits = 6): string
    {
        $t = (int)floor(($timestamp ?? time()) / $period);
        return self::hotp($secret, $t, $digits);
    }

    /**
     * HOTP per RFC 4226.
     */
    public static function hotp(string $secret, int $counter, int $digits = 6): string
    {
        $bin = self::base32Decode($secret);
        $hash = hash_hmac('sha1', pack('N*', 0) . pack('N*', $counter), $bin, true);
        $offset = ord($hash[19]) & 0x0F;
        $code = (
            ((ord($hash[$offset])     & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) <<  8) |
            ((ord($hash[$offset + 3]) & 0xFF))
        );
        $mod = 10 ** $digits;
        return str_pad((string)($code % $mod), $digits, '0', STR_PAD_LEFT);
    }

    /**
     * Verify a code, allowing a small clock-skew window.
     */
    public static function verify(string $secret, string $code, int $window = 1, int $period = 30, int $digits = 6): bool
    {
        $code = preg_replace('/\s+/', '', $code);
        if ($code === '' || strlen($code) !== $digits) {
            return false;
        }
        $now = time();
        for ($i = -$window; $i <= $window; $i++) {
            $expected = self::code($secret, $now + $i * $period, $period, $digits);
            if (hash_equals($expected, $code)) {
                return true;
            }
        }
        return false;
    }

    public static function generateSecret(int $bytes = 20): string
    {
        return self::base32Encode(random_bytes($bytes));
    }

    public static function provisioningUri(string $secret, string $account, string $issuer = 'wawoo-cms', int $period = 30, int $digits = 6): string
    {
        $label = rawurlencode($issuer . ':' . $account);
        $params = http_build_query([
            'secret'    => $secret,
            'issuer'    => $issuer,
            'period'    => $period,
            'digits'    => $digits,
            'algorithm' => 'SHA1',
        ]);
        return "otpauth://totp/{$label}?{$params}";
    }

    /**
     * QR code as an inline `data:` URI rendered entirely on the server by the
     * vendored pure-PHP encoder in Qr.php (error-correction level M). The
     * provisioning URI never leaves the process, so no third-party chart
     * endpoint ever sees the secret.
     */
    public static function qrDataUri(string $provisioningUri, int $size = 200): string
    {
        return Qr::svgDataUri($provisioningUri, max(1, $size), 'M');
    }

    // ---- base32 ----

    public static function base32Encode(string $bytes): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split($bytes) as $b) {
            $bits .= str_pad(decbin(ord($b)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            $out .= $alphabet[bindec($chunk)];
        }
        return $out;
    }

    public static function base32Decode(string $b32): string
    {
        $b32 = strtoupper(rtrim($b32, '='));
        $map = array_flip(str_split('ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'));
        $bits = '';
        foreach (str_split($b32) as $c) {
            if (!isset($map[$c])) {
                continue;
            }
            $bits .= str_pad(decbin($map[$c]), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $out .= chr(bindec($chunk));
            }
        }
        return $out;
    }
}
