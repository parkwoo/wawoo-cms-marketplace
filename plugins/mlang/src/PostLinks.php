<?php
namespace Wawoo\Plugin\Mlang;

/**
 * Hooks `theme.post.after`: renders links to the other language versions
 * of the current post (payload $vars carries the loaded 'post').
 */
final class PostLinks
{
    public function handle(?array $vars): void
    {
        LanguageLinks::output($vars, 'posts');
    }
}
