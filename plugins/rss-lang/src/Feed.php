<?php
namespace Wawoo\Plugin\RssLang;

use Wawoo\Core\Langs;
use Wawoo\Core\Posts;
use Wawoo\Core\Url;

/**
 * `core.request`: multilingual RSS feeds.
 *
 *   /rss                 -> default language (back-compat with the rss plugin)
 *   /rss/<lang>          -> per-language feed
 *   /feed.<lang>.xml     -> alias for /rss/<lang>
 *
 * Each channel advertises its own <language>, every item link carries the
 * language prefix, and each item cross-links all other language versions via
 * xhtml:link rel=alternate hreflang. Unknown language -> 404.
 */
final class Feed
{
    public function handle(?string $root = null): void
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $lang = $this->resolve($path);
        if ($lang === null) {
            return; // not a feed route; let dispatch continue
        }
        if ($lang === false) {
            http_response_code(404);
            exit;
        }
        header('Content-Type: application/rss+xml; charset=utf-8');
        header('Cache-Control: no-store');
        echo self::render($lang, Posts::all(false));
        exit;
    }

    /** string lang | false (known-bad) | null (not a feed path). */
    private function resolve(string $path): string|false|null
    {
        $supported = Config::langs();
        if ($path === '/rss' || $path === '/feed.xml') {
            return Config::defaultLang();
        }
        if (preg_match('#^/rss/([A-Za-z0-9-]{1,12})$#', $path, $m)) {
            return in_array($m[1], $supported, true) ? $m[1] : false;
        }
        if (preg_match('#^/feed\.([A-Za-z0-9-]{1,12})\.xml$#', $path, $m)) {
            return in_array($m[1], $supported, true) ? $m[1] : false;
        }
        return null;
    }

    /** Pure XML builder (unit-testable). $posts come from Posts::all(). */
    public static function render(string $lang, array $posts): string
    {
        $self = Url::absolute('rss/' . rawurlencode($lang));
        $title = $lang === Config::defaultLang() ? SITE_TITLE : SITE_TITLE . ' — ' . $lang;
        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";
        $xml .= '<channel>' . "\n";
        $xml .= '<title>' . self::esc($title) . '</title>' . "\n";
        $xml .= '<link>' . self::esc(Url::absolute(self::prefix($lang))) . '</link>' . "\n";
        $xml .= '<description>' . self::esc(SITE_DESCRIPTION) . '</description>' . "\n";
        $xml .= '<language>' . self::esc($lang) . '</language>' . "\n";
        $xml .= '<atom:link href="' . self::esc($self) . '" rel="self" type="application/rss+xml"/>' . "\n";

        $max = (int)(Config::all()['max_items'] ?? 20);
        foreach (array_slice($posts, 0, $max) as $p) {
            $xml .= self::item($lang, $p);
        }
        $xml .= '</channel>' . "\n</rss>";
        return $xml;
    }

    private static function item(string $lang, array $p): string
    {
        $slug = (string)$p['slug'];
        $url = Url::absolute(self::prefix($lang) . 'post/' . rawurlencode($slug));
        $title = Langs::pick($p, 'title', $lang);
        $excerpt = Langs::pick($p, 'excerpt', $lang);
        if ($excerpt === '' && !empty($p['content'])) {
            $excerpt = mb_substr(strip_tags((string)$p['content']), 0, 200);
        }
        $out = '<item>';
        $out .= '<title>' . self::esc($title) . '</title>';
        $out .= '<link>' . self::esc($url) . '</link>';
        $out .= '<guid isPermaLink="true">' . self::esc($url) . '</guid>';
        $out .= '<pubDate>' . date(DATE_RSS, strtotime((string)($p['date'] ?: 'now'))) . '</pubDate>';
        $out .= '<category>' . self::esc((string)($p['category'] ?? 'General')) . '</category>';
        $out .= '<description><![CDATA[' . str_replace(']]>', ']]]]><![CDATA[>', $excerpt) . ']]></description>';
        foreach (self::alternates($lang, $p) as $alt) {
            [$code, $href] = $alt;
            $out .= '<xhtml:link rel="alternate" hreflang="' . self::esc($code) . '" href="' . self::esc($href) . '"/>';
        }
        $out .= '</item>' . "\n";
        return $out;
    }

    /**
     * hreflang alternates for one post across available languages, plus an
     * x-default pointing at the default language version.
     *
     * @return array<int,array{0:string,1:string}>
     */
    public static function alternates(string $lang, array $p): array
    {
        $slug = (string)$p['slug'];
        $available = array_values(array_filter((array)($p['langs'] ?? [])));
        if ($available === []) {
            $available = [Config::defaultLang()];
        }
        $out = [];
        foreach ($available as $code) {
            $out[] = [(string)$code, Url::absolute(self::prefix((string)$code) . 'post/' . rawurlencode($slug))];
        }
        $out[] = ['x-default', Url::absolute(self::prefix(Config::defaultLang()) . 'post/' . rawurlencode($slug))];
        return $out;
    }

    private static function prefix(string $lang): string
    {
        // Every language carries its URL prefix — no prefix-less default.
        return rawurlencode($lang) . '/';
    }

    private static function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
