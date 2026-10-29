<?php
namespace Wawoo\Plugin\Pagination;

final class Pagination
{
    /**
     * Page-number window: 0 marks an ellipsis gap.
     *
     * window(1, 20, 2)  -> [1, 2, 3, 0, 20]
     * window(10, 20, 2) -> [1, 0, 8, 9, 10, 11, 12, 0, 20]
     */
    public static function window(int $page, int $pages, int $adjacent): array
    {
        $pages = max(1, $pages);
        $page = max(1, min($page, $pages));
        $adjacent = max(0, $adjacent);
        $left = $page - $adjacent;
        $right = $page + $adjacent + 1;

        $range = [];
        for ($i = 1; $i <= $pages; $i++) {
            if ($i === 1 || $i === $pages || ($i >= $left && $i < $right)) {
                $range[] = $i;
            }
        }

        $out = [];
        $last = 0;
        foreach ($range as $i) {
            if ($last !== 0 && $i - $last > 1) {
                $out[] = $i - $last === 2 ? $last + 1 : 0;
            }
            $out[] = $i;
            $last = $i;
        }
        return $out;
    }

    public static function slice(array $posts, int $page, int $per): array
    {
        $per = max(1, $per);
        $total = count($posts);
        $pages = max(1, (int)ceil($total / $per));
        $page = max(1, min($page, $pages));
        return [array_slice($posts, ($page - 1) * $per, $per), $pages];
    }

    public static function render(array $vars, ?array $cfg = null): string
    {
        $cfg = is_array($cfg) ? $cfg : Config::all();
        $page = max(1, (int)($vars['page'] ?? 1));
        $pages = max(1, (int)($vars['pages'] ?? 1));
        if ($pages <= 1) {
            return '';
        }
        $page = min($page, $pages);
        $adjacent = max(0, (int)($cfg['adjacent'] ?? 2));
        $labels = $cfg['labels'] ?? [];
        $prev = is_array($labels) && isset($labels['prev']) ? (string)$labels['prev'] : '&laquo; Prev';
        $next = is_array($labels) && isset($labels['next']) ? (string)$labels['next'] : 'Next &raquo;';

        $html = '<nav class="pagination" role="navigation" aria-label="Pagination">';
        $html .= $page > 1
            ? '<a class="page-num" href="' . self::esc(self::pageUrl($page - 1)) . '">' . $prev . '</a>'
            : '<span class="page-num disabled">' . $prev . '</span>';
        foreach (self::window($page, $pages, $adjacent) as $n) {
            if ($n === 0) {
                $html .= '<span class="pagination-ellipsis">…</span>';
            } elseif ($n === $page) {
                $html .= '<span class="page-num current" aria-current="page">' . $n . '</span>';
            } else {
                $html .= '<a class="page-num" href="' . self::esc(self::pageUrl($n)) . '">' . $n . '</a>';
            }
        }
        $html .= $page < $pages
            ? '<a class="page-num" href="' . self::esc(self::pageUrl($page + 1)) . '">' . $next . '</a>'
            : '<span class="page-num disabled">' . $next . '</span>';
        return $html . '</nav>';
    }

    public static function pageUrl(int $n): string
    {
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
        $path = (string)parse_url($uri, PHP_URL_PATH);
        $query = (string)parse_url($uri, PHP_URL_QUERY);
        parse_str($query, $params);
        if (!is_array($params)) {
            $params = [];
        }
        if ($n > 1) {
            $params['page'] = $n;
        } else {
            unset($params['page']);
        }
        if ($path === '') {
            $path = '/';
        }
        return $params === [] ? $path : $path . '?' . http_build_query($params);
    }

    private static function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
