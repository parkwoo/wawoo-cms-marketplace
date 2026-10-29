<?php
namespace Wawoo\Plugin\Mlang;

/**
 * Hooks `theme.page.after`: renders links to the other language versions
 * of the current page (payload $vars carries the loaded 'page').
 */
final class PageLinks
{
    public function handle(?array $vars): void
    {
        LanguageLinks::output($vars, 'pages');
    }
}
