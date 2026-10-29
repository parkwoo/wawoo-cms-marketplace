<?php
namespace Wawoo\Plugin\Api;

/**
 * サイトAPI: R2（S3 互換）の SigV4 署名 URL を発行（PHP 実装・依存なし）。
 *
 * サイト空間は Node を持たないため、サイト側で署名が必要な場合は PHP で行う。
 * 代替としてパイプライン（taro・Node）側で署名し URL を渡す運用もある（秘密をサイト空間に置かない）。
 */
final class Sign
{
    private const MAX_EXPIRES = 604800; // 7日（SigV4 上限）

    /**
     * @param array<string,mixed> $o endpoint/bucket/key/access_key_id/secret_access_key
     *                             method(既定GET)/region(既定auto)/expires/now(\DateTimeInterface)
     */
    public static function presign(array $o): string
    {
        foreach (['endpoint', 'bucket', 'key', 'access_key_id', 'secret_access_key'] as $k) {
            if (empty($o[$k]) || !is_string($o[$k])) {
                throw new \InvalidArgumentException("$k is required");
            }
        }
        $method = strtoupper((string) ($o['method'] ?? 'GET'));
        $region = (string) ($o['region'] ?? 'auto');
        $expires = min(self::MAX_EXPIRES, max(1, (int) ($o['expires'] ?? 3600)));
        $now = $o['now'] ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $amzDate = $now->format('Ymd\THis\Z');
        $dateStamp = $now->format('Ymd');

        $u = parse_url($o['endpoint']);
        if (!isset($u['scheme'], $u['host'])) {
            throw new \InvalidArgumentException('endpoint must be an absolute URL');
        }
        $host = $u['host'] . (isset($u['port']) ? ':' . $u['port'] : '');
        $origin = $u['scheme'] . '://' . $host;

        $scope = "$dateStamp/$region/s3/aws4_request";
        $segments = array_map([self::class, 'enc'], explode('/', (string) $o['key']));
        $canonicalUri = '/' . self::enc($o['bucket']) . '/' . implode('/', $segments);

        $query = [
            'X-Amz-Algorithm' => 'AWS4-HMAC-SHA256',
            'X-Amz-Credential' => $o['access_key_id'] . '/' . $scope,
            'X-Amz-Date' => $amzDate,
            'X-Amz-Expires' => (string) $expires,
            'X-Amz-SignedHeaders' => 'host',
        ];
        ksort($query);
        $canonicalQuery = implode('&', array_map(
            static fn (string $k): string => self::enc($k) . '=' . self::enc($query[$k]),
            array_keys($query)
        ));

        $canonicalHeaders = "host:$host\n";
        $canonicalRequest = implode("\n", [
            $method, $canonicalUri, $canonicalQuery, $canonicalHeaders, 'host', 'UNSIGNED-PAYLOAD',
        ]);
        $stringToSign = implode("\n", [
            'AWS4-HMAC-SHA256', $amzDate, $scope, hash('sha256', $canonicalRequest),
        ]);

        $kDate = hash_hmac('sha256', $dateStamp, 'AWS4' . $o['secret_access_key'], true);
        $kRegion = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', 's3', $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        return $origin . $canonicalUri . '?' . $canonicalQuery . '&X-Amz-Signature=' . $signature;
    }

    private static function enc(string $s): string
    {
        return rawurlencode($s);
    }
}
