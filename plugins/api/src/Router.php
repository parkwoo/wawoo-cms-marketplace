<?php
namespace Wawoo\Plugin\Api;

/**
 * サイトAPI — wawoo-cms の `core.request` フックで提供する。
 *
 * 制約: 従来の PHP 空間（php-fpm / Apache）。Node なし。サイト向け API は PHP で実装する。
 * 既定は無効（config.enabled = false）。有効化は `ENABLED_PLUGINS` + `$GLOBALS['WAWOO_API']`。
 *
 * セキュリティ（レビュー #3/#7/#8）:
 *  - `/api/sign` は method 許可制・key プレフィックス許可制・expires 上限。
 *  - base_path は非空かつ '/' 始まりを必須（空だと全パスを専有してしまう）。
 *  - リクエストボディはサイズ上限つきで読む。
 *
 * ルート:
 *   GET  /api/health   -> 死活（トークン不要）
 *   POST /api/publish  -> draft 受付（要トークン・fail-closed）
 *   POST /api/sign     -> R2 署名 URL 発行（要トークン・制限つき）
 */
final class Router
{
    private const MAX_BODY_BYTES = 200000;      // 200KB
    private const ABSOLUTE_MAX_EXPIRES = 604800; // 7日

    public function handle(?string $root = null): void
    {
        $cfg = Config::all();
        if (empty($cfg['enabled'])) {
            return;
        }

        $base = (string) $cfg['base_path'];
        // base_path 検証（空/不正なら何も専有しない = fail-closed）
        if ($base === '' || $base[0] !== '/') {
            return;
        }
        $base = rtrim($base, '/');

        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        if ($path !== $base && strncmp($path, $base . '/', strlen($base) + 1) !== 0) {
            return;
        }
        $route = substr($path, strlen($base));
        if ($route === '' || $route === false) {
            $route = '/';
        }
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if ($method === 'GET' && $route === '/health') {
            $this->json(200, ['ok' => true, 'service' => 'ai-wawoo-jp-site-api']);
        }

        if ($method === 'POST' && ($route === '/publish' || $route === '/sign')) {
            if (!$this->authorized($cfg)) {
                $this->json(401, ['ok' => false, 'errors' => ['unauthorized']]);
            }
            $body = $this->readJson();
            if ($body === null) {
                $this->json(400, ['ok' => false, 'errors' => ['invalid JSON body']]);
            }

            if ($route === '/publish') {
                $result = Publish::validate($body);
                if (!$result['ok']) {
                    $this->json(400, $result);
                }
                $this->json(202, [
                    'ok' => true,
                    'accepted' => true,
                    'slug' => $result['normalized']['slug'],
                    'status' => 'draft',
                    'langs' => $result['normalized']['langs'],
                    'note' => 'draft staged; publish (status=published) is a human-only step',
                ]);
            }

            // /sign — 制限つき署名
            $r2 = is_array($cfg['r2']) ? $cfg['r2'] : [];
            if (empty($r2['endpoint']) || empty($r2['access_key_id']) || empty($r2['secret_access_key']) || empty($r2['bucket'])) {
                $this->json(503, ['ok' => false, 'errors' => ['storage signing is not configured']]);
            }
            $key = $body['key'] ?? '';
            if (!is_string($key) || $key === '') {
                $this->json(400, ['ok' => false, 'errors' => ['key is required']]);
            }
            $signMethod = strtoupper((string) ($body['method'] ?? 'GET'));
            $allowedMethods = array_map('strtoupper', (array) ($cfg['allowed_methods'] ?? ['GET', 'PUT']));
            if (!in_array($signMethod, $allowedMethods, true)) {
                $this->json(400, ['ok' => false, 'errors' => ['method not allowed: ' . $signMethod]]);
            }
            $prefixes = (array) ($cfg['key_prefixes'] ?? ['audio/']);
            $ok = false;
            foreach ($prefixes as $p) {
                if (strncmp($key, (string) $p, strlen((string) $p)) === 0) { $ok = true; break; }
            }
            if (!$ok) {
                $this->json(400, ['ok' => false, 'errors' => ['key prefix not allowed']]);
            }
            $defaultExpires = (int) ($cfg['default_expires'] ?? 600);
            $expires = (int) ($body['expires'] ?? $defaultExpires);
            $expires = max(60, min(self::ABSOLUTE_MAX_EXPIRES, $expires));

            $signed = Sign::presign([
                'endpoint' => $r2['endpoint'],
                'bucket' => $r2['bucket'],
                'key' => $key,
                'access_key_id' => $r2['access_key_id'],
                'secret_access_key' => $r2['secret_access_key'],
                'region' => $r2['region'] ?? 'auto',
                'method' => $signMethod,
                'expires' => $expires,
            ]);
            $this->json(200, ['ok' => true, 'url' => $signed, 'method' => $signMethod, 'expires' => $expires]);
        }

        $this->json(404, ['ok' => false, 'errors' => ['not found']]);
    }

    private function authorized(array $cfg): bool
    {
        $expected = (string) ($cfg['token'] ?? '');
        if ($expected === '') {
            return false; // fail-closed: トークン未設定なら拒否
        }
        $header = 'HTTP_' . str_replace('-', '_', strtoupper((string) $cfg['token_header']));
        $got = $_SERVER[$header] ?? '';
        return is_string($got) && hash_equals($expected, $got);
    }

    /** @return array<string,mixed>|null null on parse error or oversize */
    private function readJson(): ?array
    {
        $raw = '';
        $fh = fopen('php://input', 'rb');
        if ($fh !== false) {
            $raw = (string) stream_get_contents($fh, self::MAX_BODY_BYTES);
            fclose($fh);
        }
        if (strlen($raw) >= self::MAX_BODY_BYTES) {
            return null; // 大きすぎるボディは拒否
        }
        if ($raw === '') {
            return [];
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }

    private function json(int $code, array $body): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
