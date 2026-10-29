<?php
namespace Wawoo\Plugin\Ads;

/**
 * `theme.index.after` event: the mid/after-list index ad frames. Echoes only
 * on unfiltered home pages (a filter/cat/tag view is treated as content).
 */
final class IndexSlots
{
    public function handle(?array $vars = null): void
    {
        if (!is_array($vars)) {
            return;
        }
        if (!Config::anyEnabled() && !Config::preview()) {
            return;
        }
        if (($vars['filterCat'] ?? '') !== '' || ($vars['filterTag'] ?? '') !== '') {
            return;
        }
        $out = Slots::slot('index_bottom');
        if ($out !== '') {
            echo '<div class="wrap">' . $out . '</div>';
        }
    }
}
