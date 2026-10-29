<?php
namespace Wawoo\Plugin\Publish;

use Wawoo\Core\Posts;

/**
 * Promotes scheduled posts that are due on every web request. This makes
 * time-based publishing work without cron: a due post becomes public the
 * first time anyone hits the site after publish_at.
 */
final class Due
{
    public function handle(?string $root = null): void
    {
        if (PHP_SAPI === 'cli') {
            return; // bin/wawoo publish:due / cron path
        }
        Posts::due();
    }
}
