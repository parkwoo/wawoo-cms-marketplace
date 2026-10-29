<?php
namespace Wawoo\Plugin\Search;

use Wawoo\Core\Hook;
use Wawoo\Core\Posts;
use Wawoo\Core\Theme;

/**
 * Serves /search. Hooks `core.request` so it can short-circuit before
 * dispatch 404s.
 */
final class Page
{
    public function handle(?string $root = null): void
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        if ($path !== '/search') {
            return;
        }
        $q    = trim((string)($_GET['q'] ?? ''));
        $hits = $q === '' ? [] : Posts::search($q);

        $vars = [
            'pageTitle'       => $q === '' ? 'Search' : 'Search: ' . $q,
            'pageDescription' => 'Search results',
            'structuredType'  => 'SearchResultsPage',
            'canonical'       => \Wawoo\Core\Url::absolute('search?q=' . urlencode($q)),
            'q'               => $q,
            'posts'           => $hits,
            'stats'           => Posts::stats(),
        ];

        Hook::fire('search.before_render', $vars);

        Theme::render('search', $vars);
        exit;
    }
}
