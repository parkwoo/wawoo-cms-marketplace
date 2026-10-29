<?php
namespace Wawoo\Plugin\Pagination;

final class Render
{
    public function handle($html = null, ?array $vars = null)
    {
        $vars = is_array($vars) ? $vars : [];
        $posts = $vars['posts'] ?? [];
        $page = max(1, (int)($vars['page'] ?? ($_GET['page'] ?? 1)));
        $givenPages = (int)($vars['pages'] ?? 0);
        $total = is_array($posts) ? count($posts) : 0;
        $pages = max($givenPages, $total > 0 ? (int)ceil($total / Config::perPage()) : 0);
        if ($pages <= 1) {
            return null;
        }
        return Pagination::render(['page' => $page, 'pages' => $pages], Config::all());
    }
}
