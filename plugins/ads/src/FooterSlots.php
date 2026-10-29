<?php
namespace Wawoo\Plugin\Ads;

/**
 * `theme.footer` event: reserved common-footer frame (site-wide unit).
 * Emits nothing unless an `index_bottom`/amazon unit is configured; kept as
 * the single shared drop point across pages per spec §3.
 */
final class FooterSlots
{
    public function handle(?array $vars = null): void
    {
        // No site-wide unit configured by default; hooks kept for parity with
        // the spec so per-site templates can extend without rewiring.
    }
}
