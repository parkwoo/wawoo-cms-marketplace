<?php
namespace Wawoo\Plugin\Rss;

/**
 * Injects the RSS <link rel="alternate"> into <head> via theme.head.after.
 */
final class HeadLink
{
    public function handle(?array $vars = null): void
    {
        $href = BASE_URL . 'rss';
        echo '<link rel="alternate" type="application/rss+xml" title="' . htmlspecialchars(SITE_TITLE) . ' RSS" href="' . htmlspecialchars($href) . '">' . "\n";
    }
}
