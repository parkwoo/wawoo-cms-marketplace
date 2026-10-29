<?php
namespace Wawoo\Plugin\Rss;

use Wawoo\Core\Posts;

/**
 * Serves the RSS 2.0 feed at /rss (also /feed.xml as an alias).
 * Hooks `core.request` so it can short-circuit before dispatch 404s.
 */
final class Feed
{
    public function handle(?string $root = null): void
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        if ($path !== '/rss' && $path !== '/feed.xml') {
            return;
        }
        header('Content-Type: application/rss+xml; charset=utf-8');

        $posts = Posts::all();
        $posts = array_slice($posts, 0, (int)Config::all()['max_items']);

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<rss version="2.0"><channel>' . "\n";
        echo '<title>' . htmlspecialchars(SITE_TITLE) . '</title>' . "\n";
        echo '<link>' . htmlspecialchars(BASE_URL) . '</link>' . "\n";
        echo '<description>' . htmlspecialchars(SITE_DESCRIPTION) . '</description>' . "\n";
        echo '<language>' . DEFAULT_LANGUAGE . '</language>' . "\n";

        foreach ($posts as $p) {
            $url = \Wawoo\Core\Url::absolute('post/' . urlencode($p['slug']));
            echo '<item>';
            echo '<title>' . htmlspecialchars($p['title']) . '</title>';
            echo '<link>' . htmlspecialchars($url) . '</link>';
            echo '<guid isPermaLink="true">' . htmlspecialchars($url) . '</guid>';
            echo '<pubDate>' . date(DATE_RSS, strtotime($p['date'])) . '</pubDate>';
            echo '<category>' . htmlspecialchars($p['category']) . '</category>';
            $summary = $p['excerpt'] !== '' ? $p['excerpt'] : mb_substr(strip_tags($p['content']), 0, 200);
            echo '<description><![CDATA[' . $summary . ']]></description>';
            echo '</item>' . "\n";
        }
        echo '</channel></rss>';
        exit;
    }
}
