<?php
namespace Wawoo\Plugin\Mlang;

/**
 * Hooks `post.load` (payload by reference). Records the language that was
 * actually served (set by Posts::bySlug) and the list of translations that
 * exist for the bundle. Translated files are never added to Posts::all(),
 * so lists and feeds keep exactly one entry per post.
 */
final class PostLoader
{
    public function handle(?array &$post): void
    {
        if (!is_array($post) || empty($post['slug'])) {
            return;
        }
        $post['lang'] = (string)($post['lang'] ?? '');
        $post['translations'] = Languages::available('posts', (string)$post['slug']);
    }
}
