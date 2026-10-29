<?php
namespace Wawoo\Plugin\Ads;

/**
 * `post.html` filter: injects the top/inline/bottom article ad frames after
 * markdown has already been rendered (safe-mode proof — the frames are added
 * downstream of Parsedown, so <ins>/<script> survive).
 */
final class PostSlots
{
    public function handle($html, $post = null): string
    {
        $html = (string)$html;
        if (Config::disabledFor(is_array($post) ? $post : null)) {
            return $html;
        }
        if (!Config::anyEnabled() && !Config::preview()) {
            return $html;
        }
        $top    = Slots::slot('post_top');
        $inline = Slots::slot('post_inline');
        $bottom = Slots::slot('post_bottom');

        if ($top !== '') {
            $html = $top . $html;
        }
        if ($inline !== '') {
            // place after the first closing paragraph (or prepend if none).
            $pos = strpos($html, '</p>');
            $html = $pos !== false
                ? substr($html, 0, $pos + 4) . $inline . substr($html, $pos + 4)
                : $inline . $html;
        }
        if ($bottom !== '') {
            $html = $html . $bottom;
        }
        return $html;
    }
}
