<?php
namespace Wawoo\Plugin\Review;

use Wawoo\Core\Posts;

/**
 * 審批コンソール — `/review`
 *
 * 記事ごとに **言語別の状態** を表示し、言語ごとに独立して 公開/非公開 できる。
 * 音声は各言語の `audio_url` で独立（本文の公開状態とは別に有無を表示）。
 * 修正指示（どこをどう直すか）は記録し、制作パイプライン（Dagu）が読んで revise 実行。
 *
 * 公開操作は「人がクリックする」= 人間ゲート。ワークフローは提案・実装・検証まで。
 */
final class Router
{
    private const STORE = 'cache/settings/reviews.json';
    private const LANGS = ['ja', 'en', 'zh-CN', 'zh-TW', 'es'];

    public function handle(?string $root = null): void
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        if ($path !== '/review' && strpos($path, '/review/') !== 0) {
            return;
        }
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['wawoo_admin'])) {
            header('Location: /admin/');
            exit;
        }
        $base = rtrim((string)($root ?? dirname(__DIR__, 3)), '/');

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $slug = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($_POST['slug'] ?? ''));
            $type = (string)($_POST['type'] ?? '');
            $lang = (string)($_POST['lang'] ?? '');
            if ($slug !== '' && in_array($type, ['publish', 'unpublish'], true) && in_array($lang, self::LANGS, true)) {
                $this->setStatus($base, $slug, $lang, $type === 'publish' ? 'published' : 'draft');
            } elseif ($slug !== '' && $type === 'revise') {
                $instruction = trim((string)($_POST['instruction'] ?? ''));
                if ($instruction !== '') {
                    $this->add($base . '/' . self::STORE, $slug, $instruction, trim((string)($_POST['langs'] ?? '')));
                }
            }
            header('Location: /review?ok=1');
            exit;
        }
        $this->render($base);
    }

    private function fileFor(string $base, string $slug, string $lang): string
    {
        return $lang === 'ja'
            ? $base . '/posts/' . $slug . '/index.md'
            : $base . '/posts/' . $slug . '/index.' . $lang . '.md';
    }

    private function statusOf(string $file): ?string
    {
        if (!is_file($file)) {
            return null; // その言語は未生成
        }
        $s = (string)file_get_contents($file);
        if (preg_match('/^status:\s*(\S+)/m', $s, $m)) {
            return trim($m[1], "\"'");
        }
        return 'draft'; // status 欠落は安全側=draft 扱い（表示のみ）
    }

    private function hasAudio(string $file): bool
    {
        if (!is_file($file)) {
            return false;
        }
        return (bool)preg_match('/^audio_url:\s*\S+/m', (string)file_get_contents($file));
    }

    private function setStatus(string $base, string $slug, string $lang, string $status): void
    {
        $file = $this->fileFor($base, $slug, $lang);
        if (!is_file($file)) {
            return;
        }
        $s = (string)file_get_contents($file);
        if (preg_match('/^status:.*$/m', $s)) {
            $s = preg_replace('/^status:.*$/m', 'status: ' . $status, $s, 1);
        } elseif (preg_match('/^---\r?\n/', $s)) {
            $s = preg_replace('/^(---\r?\n)/', '$1status: ' . $status . "\n", $s, 1);
        }
        $tmp = $file . '.tmp.' . getmypid();
        file_put_contents($tmp, $s);
        rename($tmp, $file);
    }

    private function add(string $file, string $slug, string $instruction, string $langs): void
    {
        @mkdir(dirname($file), 0775, true);
        $db = $this->load($file);
        $db['seq'] = (int)($db['seq'] ?? 0) + 1;
        $db['rows'][] = ['id' => $db['seq'], 'slug' => $slug, 'type' => 'revise', 'instruction' => $instruction, 'langs' => $langs, 'state' => 'open', 'at' => gmdate('c'), 'by' => 'human'];
        $tmp = $file . '.tmp.' . getmypid();
        file_put_contents($tmp, json_encode($db, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        rename($tmp, $file);
    }

    private function load(string $file): array
    {
        if (!is_file($file)) {
            return ['seq' => 0, 'rows' => []];
        }
        $db = json_decode((string)file_get_contents($file), true);
        return is_array($db) && isset($db['rows']) ? $db : ['seq' => 0, 'rows' => []];
    }

    private function render(string $base): void
    {
        $e = static fn ($s): string => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
        $db = $this->load($base . '/' . self::STORE);
        $open = array_values(array_filter($db['rows'], static fn ($r) => ($r['state'] ?? '') === 'open'));
        $posts = Posts::all(true);

        echo '<!doctype html><html lang="ja"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<meta name="robots" content="noindex,nofollow"><title>審批コンソール</title><style>'
            . 'body{font-family:system-ui,-apple-system,"Noto Sans JP",sans-serif;max-width:960px;margin:2rem auto;padding:0 1rem;line-height:1.75;color:#1f1e1d}'
            . 'table{border-collapse:collapse;width:100%}th,td{border:1px solid #ddd;padding:.45rem;vertical-align:top;text-align:left}'
            . '.badge{display:inline-block;padding:.05rem .5rem;border-radius:999px;font-size:.75rem}'
            . '.pub{background:#e6f4ea;color:#2e7d32}.dra{background:#fdecea;color:#8B2624}.none{background:#f0f0f0;color:#888}'
            . 'button{padding:.25rem .6rem;cursor:pointer}.bar{background:#f3f0ea;padding:.5rem .8rem;border-radius:6px;font-size:.85rem;margin-bottom:1rem}'
            . 'textarea{width:100%;min-height:3.5em}.req{background:#faf7f2;border:1px solid #e5ddd0;padding:.5rem;border-radius:6px;margin:.3rem 0}'
            . '</style></head><body>';
        echo '<div class="bar">審批コンソール — <strong>言語ごとに独立して公開/非公開</strong>できます（例: ja を公開したまま en は下書き、音声だけ後から追加）。修正指示はワークフローが読んで revise 実行。公開操作は人のみ。<a href="/admin/">admin</a></div>';
        echo '<h1>記事の審批（言語別）</h1>';
        foreach ($posts as $p) {
            $slug = (string)($p['slug'] ?? '');
            echo '<div style="border:1px solid #e5ddd0;border-radius:8px;padding:.6rem .8rem;margin:0 0 1rem">';
            echo '<div><a href="/admin/?action=preview&amp;slug=' . urlencode($slug) . '" target="_blank"><strong>' . $e($p['title'] ?? $slug) . '</strong></a> <small>' . $e($slug) . '</small></div>';
            $afile = $base . '/posts/' . $slug . '/audio.json';
            $adb = is_file($afile) ? json_decode((string)file_get_contents($afile), true) : [];
            if (!is_array($adb)) { $adb = []; }
            echo '<table style="margin:.4rem 0"><thead><tr><th>言語</th><th>本文</th><th>音声</th><th>操作</th></tr></thead><tbody>';
            foreach (self::LANGS as $l) {
                $f = $this->fileFor($base, $slug, $l);
                $st = $this->statusOf($f);
                $audioHas = !empty($adb[$l]['url']);
                $audio = $audioHas ? '<span class="badge pub">あり</span>' : '<span class="badge none">なし</span>';
                if ($st === null) {
                    echo '<tr><td>' . $e($l) . '</td><td><span class="badge none">未生成</span></td><td>' . $audio . '</td><td>-</td></tr>';
                    continue;
                }
                $cls = $st === 'published' ? 'pub' : 'dra';
                $next = $st === 'published' ? 'unpublish' : 'publish';
                $label = $st === 'published' ? '非公開にする' : '公開する';
                echo '<tr><td>' . $e($l) . '</td><td><span class="badge ' . $cls . '">' . $e($st) . '</span></td><td>' . $audio . '</td>'
                    . '<td><form method="post" action="/review" style="margin:0"><input type="hidden" name="slug" value="' . $e($slug) . '"><input type="hidden" name="type" value="' . $next . '"><input type="hidden" name="lang" value="' . $e($l) . '"><button type="submit">' . $e($label) . '</button></form></td></tr>';
            }
            echo '</tbody></table>';
            echo '<form method="post" action="/review"><input type="hidden" name="slug" value="' . $e($slug) . '"><input type="hidden" name="type" value="revise">'
                . '<textarea name="instruction" placeholder="修正指示（どこをどう直すか）"></textarea>'
                . '<input name="langs" placeholder="対象言語（空=全言語, 例: ja,en）" style="margin-top:.3rem;width:100%">'
                . '<div style="margin-top:.4rem"><button type="submit">修正を依頼</button></div></form>';
            echo '</div>';
        }
        echo '<h2>保留中の修正依頼（' . count($open) . '）</h2>';
        if (empty($open)) {
            echo '<p>なし</p>';
        } else {
            foreach ($open as $r) {
                echo '<div class="req">#' . (int)$r['id'] . ' <strong>' . $e($r['slug']) . '</strong>'
                    . (!empty($r['langs']) ? ' [' . $e($r['langs']) . ']' : '') . ' — ' . nl2br($e($r['instruction']))
                    . '<br><small>' . $e($r['at']) . '</small></div>';
            }
        }
        echo '</body></html>';
        exit;
    }
}
