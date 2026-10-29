<?php
namespace Wawoo\Plugin\SitemapHtml;

use Wawoo\Core\Posts;
use Wawoo\Core\Theme;
use Wawoo\Core\Hook;

/**
 * Serves /sitemap-page (an HTML paginated sitemap of all posts).
 * Hooks `core.request` so it can short-circuit before any other handler.
 */
final class Handler
{
    public function handle(?string $root = null): void
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        if ($path !== '/sitemap-page' && $path !== '/sitemap-page/') {
            return;
        }
        $cfg  = Config::all();
        $page = max(1, (int)($_GET['page'] ?? 1));
        $per  = Config::perPage();

        $posts = Posts::all();
        $total = count($posts);
        $pages = (int)ceil($total / $per);
        $posts = array_slice($posts, ($page - 1) * $per, $per);

        // Group by year for readability.
        $byYear = [];
        foreach ($posts as $p) {
            $y = substr((string)$p['date'], 0, 4);
            $byYear[$y][] = $p;
        }
        krsort($byYear);

        $title = trim((string)($cfg['title'] ?? ''));
        if ($title === '') {
            $title = SITE_TITLE;
        }

        $vars = [
            'pageTitle'       => $title . ' - ' . SITE_TITLE,
            'pageDescription' => 'All posts, paginated.',
            'structuredType'  => 'CollectionPage',
            'canonical'       => BASE_URL . 'sitemap-page' . ($page > 1 ? '?page=' . $page : ''),
            'posts'           => $posts,
            'byYear'          => $byYear,
            'total'           => $total,
            'page'            => $page,
            'pages'           => $pages,
        ];
        Hook::fire('sitemap.before_render', $vars);
        Theme::render('sitemap', $vars);
        exit;
    }
}
