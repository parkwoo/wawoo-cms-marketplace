<?php
namespace Wawoo\Plugin\RssLang;

use Wawoo\Core\Url;

/**
 * `theme.head.after`: one <link rel="alternate" type="application/rss+xml">
 * per language feed (with hreflang), replacing the single-language rss link.
 */
final class HeadLinks
{
    public function handle(?array $vars = null): void
    {
        foreach (Config::langs() as $lang) {
            $href = Url::absolute('rss/' . rawurlencode((string)$lang));
            echo '<link rel="alternate" type="application/rss+xml" title="'
                . htmlspecialchars(SITE_TITLE . ' ' . $lang . ' RSS', ENT_QUOTES, 'UTF-8')
                . '" hreflang="' . htmlspecialchars((string)$lang, ENT_QUOTES, 'UTF-8')
                . '" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        }
    }
}
