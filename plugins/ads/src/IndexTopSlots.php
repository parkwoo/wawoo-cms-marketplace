<?php
namespace Wawoo\Plugin\Ads;

/**
 * `theme.index.before` event: 一覧の上部（index_top）の広告枠。
 * 実広告が未設定でも開発プレビューならプレースホルダを表示する。
 */
final class IndexTopSlots
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
        $out = Slots::slot('index_top');
        if ($out !== '') {
            echo '<div class="wrap">' . $out . '</div>';
        }
    }
}
