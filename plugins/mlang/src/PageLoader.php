<?php
namespace Wawoo\Plugin\Mlang;

/**
 * Hooks `page.load` (payload by reference). Records the language that was
 * actually served (set by Pages::get) and the list of translations that
 * exist for the bundle.
 */
final class PageLoader
{
    public function handle(?array &$page): void
    {
        if (!is_array($page) || empty($page['slug'])) {
            return;
        }
        $page['lang'] = (string)($page['lang'] ?? '');
        $page['translations'] = Languages::available('pages', (string)$page['slug']);
    }
}
