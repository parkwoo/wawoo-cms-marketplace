<?php
namespace Wawoo\Plugin\AudioMeta;

/**
 * 音声メタの注入 — `posts/<slug>/audio.json`（または pages/）を読み、当該言語の
 * `audio_url` を post/page に注入する。**本文ファイル（status を持つ）を触らない**ため、
 * 音声の追加・差し替え・削除が本文の公開状態に影響しない（言語ごとに独立）。
 *
 * audio.json 例: { "ja": {"url":"https://.../ja.mp3","duration":123}, "en": {...} }
 */
final class Inject
{
    public function handle(&$post = null): void
    {
        if (!is_array($post) || empty($post['slug'])) {
            return;
        }
        $root = dirname(__DIR__, 3);
        $slug = (string)$post['slug'];
        $lang = (string)($post['lang'] ?? '');
        if ($lang === '') {
            $lang = defined('DEFAULT_LANGUAGE') ? (string)DEFAULT_LANGUAGE : 'ja';
        }
        foreach (['posts', 'pages'] as $dir) {
            $file = $root . '/' . $dir . '/' . $slug . '/audio.json';
            if (!is_file($file)) {
                continue;
            }
            $data = json_decode((string)file_get_contents($file), true);
            if (!is_array($data)) {
                return;
            }
            // 独立性: 他言語へフォールバックしない（当該言語のエントリのみ）
            $entry = $data[$lang] ?? null;
            if (is_array($entry) && !empty($entry['url'])) {
                $post['audio_url'] = (string)$entry['url'];
                $post['meta']['audio_url'] = (string)$entry['url'];
                if (isset($entry['duration'])) {
                    $post['meta']['audio_duration'] = $entry['duration'];
                }
            }
            return;
        }
    }
}
