<?php
namespace Wawoo\Plugin\AudioInline;
use Wawoo\Core\Hook;
/**
 * Five-language article audio inline player (convention path: assets/<slug>/audio-<lang>.wav)
 * Inserted via the post.html filter. Zero-cost existence check: hide <audio> on onerror.
 */
class AudioInline
{
    public static function handle(&$html, $post = []): void
    {
        $slug = (string)($post['slug'] ?? '');
        if ($slug === '' || !empty($post['is_page'])) { return; }
        $lang = (string)($post['lang'] ?? (defined('DEFAULT_LANGUAGE') ? DEFAULT_LANGUAGE : 'ja'));
        $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') . '/' : '/';
        $langs = array_merge([\Wawoo\Core\Langs::default()], \Wawoo\Core\Langs::supported());
        $opts = '';
        foreach ($langs as $l) { $sel = $l === $lang ? ' selected' : ''; $opts .= "<option value=\"$l\" data-src=\"{$base}assets/$slug/audio-{$l}.wav\"$sel>$l</option>"; }
        $src = "{$base}assets/$slug/audio-{$lang}.wav";
        $ui = '<section class="hb-audio" style="margin:1.2rem 0;padding:.6rem .8rem;border:1px solid #ddd5;border-radius:10px;background:green">'
            . '<label style="margin-right:.6rem;font-weight:600">Audio</label>'
            . '<select id="hb-audio-lang" style="margin-right:.6rem">' . $opts . '</select>'
            . '<audio id="hb-audio-el" controls preload="none" src="' . $src . '"></audio>'
            . '<script' . \Wawoo\Core\Csp::attr() . '>(function(){var s=document.getElementById("hb-audio-lang"),a=document.getElementById("hb-audio-el");'
            . 'if(!s||!a)return;a.onerror=function(){e=0;document.querySelector(".hb-audio").style.display="none";};'
            . 's.addEventListener("change",function(){var o=s.selectedOptions[0];a.src=o.getAttribute("data-src");a.play().catch(function(){});});})();</script></section>';
        $html = $ui . "\n" . $html;
    }
}
