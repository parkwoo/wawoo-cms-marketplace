<?php
namespace Wawoo\Plugin\Api;

/**
 * サイトAPI: publish 受付の検証（fail-closed）。
 *
 * 公開状態の正準は frontmatter `status`。wawoo-cms は status 欠落を published 扱いにするため、
 * 欠落は必ず拒否する。agent/パイプラインは `draft` のみ、公開 flip は人。`scheduled`/`publish_at` は拒否。
 */
final class Publish
{
    public const STATUSES = ['draft', 'published', 'scheduled'];
    public const SITE_LANGS = ['ja', 'en', 'zh-CN', 'zh-TW', 'es'];

    /**
     * @param array<string,mixed> $input
     * @return array{ok:bool,errors:list<string>,normalized?:array<string,mixed>}
     */
    public static function validate(array $input): array
    {
        $errors = [];

        $slug = $input['slug'] ?? null;
        if (!is_string($slug) || !preg_match('/^[a-z0-9][a-z0-9-]*$/', $slug)) {
            $errors[] = 'slug is required and must be kebab-case ([a-z0-9-])';
        }

        $status = $input['status'] ?? null;
        if ($status === null || $status === '') {
            $errors[] = 'status is required (missing status would default to published = fail-open)';
        } elseif (!in_array($status, self::STATUSES, true)) {
            $errors[] = 'status must be one of draft|published|scheduled';
        } elseif ($status !== 'draft') {
            $errors[] = 'agent writes must use status=draft; the published flip is human-only';
        }

        if (!empty($input['publish_at'])) {
            $errors[] = 'publish_at is not allowed (scheduled auto-flip bypasses the human gate)';
        }

        $langs = $input['langs'] ?? null;
        if (!is_array($langs) || $langs === []) {
            $errors[] = 'langs is required (array)';
        } else {
            $missing = array_values(array_diff(self::SITE_LANGS, $langs));
            if ($missing !== []) {
                $errors[] = 'missing langs: ' . implode(', ', $missing);
            }
            $unknown = array_values(array_diff($langs, self::SITE_LANGS));
            if ($unknown !== []) {
                $errors[] = 'unknown langs: ' . implode(', ', $unknown);
            }
        }

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }
        return [
            'ok' => true,
            'errors' => [],
            'normalized' => ['slug' => $slug, 'status' => 'draft', 'langs' => $langs],
        ];
    }
}
