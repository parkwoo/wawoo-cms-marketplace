<?php
namespace Wawoo\Plugin\Rss;

/**
 * Adds an RSS link to the header nav via theme.nav.
 */
final class NavLink
{
    public function handle(?array $vars = null): void
    {
        echo '<a href="' . htmlspecialchars(BASE_URL) . 'rss">RSS</a>';
    }
}
