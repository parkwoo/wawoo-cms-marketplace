<?php
namespace Wawoo\Plugin\Pagination;

final class SearchPaginator
{
    public function handle(?array &$vars = null): void
    {
        if (!is_array($vars) || (string)($vars['q'] ?? '') === '' || empty($vars['posts'])) {
            return;
        }
        $posts = $vars['posts'];
        if (!is_array($posts)) {
            return;
        }
        $total = count($posts);
        $per = Config::perPage();
        if ($total <= $per) {
            return;
        }
        $page = max(1, (int)($_GET['page'] ?? 1));
        [$posts, $pages] = Pagination::slice($posts, $page, $per);
        $vars['posts'] = $posts;
        $vars['page'] = min($page, $pages);
        $vars['pages'] = $pages;
        $vars['total'] = $total;
    }
}
