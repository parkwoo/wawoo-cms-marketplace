<?php
namespace Wawoo\Plugin\Comments;

/**
 * JSON-file storage for comments. One file per post:
 * plugins/comments/data/<post-slug>.json
 *
 * Schema:
 *   {
 *     "post": "<slug>",
 *     "comments": [
 *       {
 *         "id": "c_xxx",
 *         "author": "name",
 *         "email": "optional@x",
 *         "url":   "https://optional",
 *         "body":  "raw text (markdown escaped at render time)",
 *         "created_at": 1730000000,
 *         "status": "pending|approved|spam|rejected",
 *         "ip":    "1.2.3.4",
 *         "ua":    "User-Agent"
 *       }
 *     ]
 *   }
 */
final class Store
{
    public static function dataDir(): string
    {
        // Runtime comments live under the writable, web-denied cache volume
        // (plugins/comments/data is not writable under a read-only prod
        // root filesystem).
        return rtrim(\CACHE_DIR, '/\\') . '/comments';
    }

    public static function file(string $slug): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $slug);
        return self::dataDir() . '/' . $safe . '.json';
    }

    public static function load(string $slug): array
    {
        $f = self::file($slug);
        if (!is_file($f)) {
            return ['post' => $slug, 'comments' => []];
        }
        $data = json_decode((string)file_get_contents($f), true);
        if (!is_array($data) || !isset($data['comments'])) {
            return ['post' => $slug, 'comments' => []];
        }
        return $data;
    }

    public static function save(string $slug, array $data): void
    {
        $dir = self::dataDir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $file = self::file($slug);
        $tmp  = $file . '.tmp';
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $fp   = @fopen($tmp, 'c');
        if ($fp === false) {
            return;
        }
        if (flock($fp, LOCK_EX)) {
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, $json);
            fflush($fp);
            flock($fp, LOCK_UN);
        }
        fclose($fp);
        rename($tmp, $file);
    }

    public static function add(string $slug, array $comment): string
    {
        $data = self::load($slug);
        $comment['id'] = 'c_' . bin2hex(random_bytes(6));
        $data['comments'][] = $comment;
        self::save($slug, $data);
        return $comment['id'];
    }

    public static function updateStatus(string $slug, string $id, string $status): bool
    {
        $data = self::load($slug);
        foreach ($data['comments'] as $i => $c) {
            if (($c['id'] ?? '') === $id) {
                $data['comments'][$i]['status'] = $status;
                self::save($slug, $data);
                return true;
            }
        }
        return false;
    }

    public static function delete(string $slug, string $id): bool
    {
        $data = self::load($slug);
        $out = [];
        foreach ($data['comments'] as $c) {
            if (($c['id'] ?? '') !== $id) {
                $out[] = $c;
            }
        }
        if (count($out) === count($data['comments'])) {
            return false;
        }
        $data['comments'] = $out;
        self::save($slug, $data);
        return true;
    }

    /** Approved comments for a post, oldest first. */
    public static function approved(string $slug): array
    {
        $c = array_filter(self::load($slug)['comments'], fn($x) => ($x['status'] ?? '') === 'approved');
        usort($c, fn($a, $b) => ($a['created_at'] ?? 0) - ($b['created_at'] ?? 0));
        return array_values($c);
    }

    /** All comments across all posts, newest first. */
    public static function allPending(): array
    {
        $all = [];
        $dir = self::dataDir();
        if (!is_dir($dir)) {
            return [];
        }
        foreach (glob($dir . '/*.json') as $f) {
            $slug = basename($f, '.json');
            $data = json_decode((string)file_get_contents($f), true);
            if (!is_array($data) || !isset($data['comments'])) {
                continue;
            }
            foreach ($data['comments'] as $c) {
                $c['post_slug'] = $slug;
                $all[] = $c;
            }
        }
        usort($all, fn($a, $b) => ($b['created_at'] ?? 0) - ($a['created_at'] ?? 0));
        return $all;
    }
}
