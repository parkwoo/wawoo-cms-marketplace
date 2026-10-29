<?php
namespace Wawoo\Plugin\Ads;

/**
 * Shared ad-frame builders. Client/slot IDs come from config only — never
 * hard-coded — and nothing is emitted when the site has no IDs configured.
 */
final class Slots
{
    /**
     * 枠1つ分を返す: 実広告（AdSense/Amazon）が設定されていればそれを、
     * 未設定でも開発プレビューならプレースホルダを、どちらも無ければ ''。
     */
    public static function slot(string $slotKey): string
    {
        $real = self::adsense($slotKey) . self::amazon($slotKey);
        if ($real !== '') {
            return $real;
        }
        if (Config::preview()) {
            return self::placeholder($slotKey);
        }
        return '';
    }

    /** 開発プレビューの枠（広告は配信しない・位置確認用）。 */
    public static function placeholder(string $slotKey): string
    {
        $label = function_exists('t') ? t('ads.label', 'Advertisement') : 'Advertisement';
        $minh = self::minHeight($slotKey);
        $out  = '<div class="hb-ad hb-ad--preview hb-ad--' . htmlspecialchars($slotKey, ENT_QUOTES, 'UTF-8') . '" data-ad-slot="' . htmlspecialchars($slotKey, ENT_QUOTES, 'UTF-8') . '" role="complementary" aria-label="広告 / PR 枠（開発プレビュー）">';
        $out .= '<div class="hb-ad-label"><span>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . ' / PR</span><span>本ページはプロモーションを含みます</span></div>';
        $out .= '<div class="hb-ad-frame hb-ad-frame--preview" style="min-height:' . $minh . '">';
        $out .= '<span class="hb-ad-badge">未接続</span>';
        $out .= '<strong>Google AdSense 広告エリア（' . htmlspecialchars($slotKey, ENT_QUOTES, 'UTF-8') . '）</strong>';
        $out .= '<p>位置の確認用です。広告の配信・クリック・計測は行いません。</p>';
        $out .= '</div></div>';
        return $out;
    }

    /** Render one AdSense frame for a configured slot key, or ''. */
    public static function adsense(string $slotKey): string
    {
        if (!Config::anyEnabled()) {
            return '';
        }
        $client = (string)Config::all()['adsense_client'];
        $slot   = Config::slot($slotKey);
        if ($client === '' || $slot === '') {
            return '';
        }
        $minh = self::minHeight($slotKey);
        $out  = '<div class="hb-ad hb-ad--' . htmlspecialchars($slotKey, ENT_QUOTES, 'UTF-8') . '">';
        $label = function_exists('t') ? t('ads.label', 'Advertisement') : 'Advertisement';
        $out .= '<p class="hb-ad-label">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</p>';
        $out .= '<div class="hb-ad-frame" style="min-height:' . $minh . '">';
        $out .= '<ins class="adsbygoogle" style="display:block" '
            . 'data-ad-client="' . htmlspecialchars($client, ENT_QUOTES, 'UTF-8') . '" '
            . 'data-ad-slot="' . htmlspecialchars($slot, ENT_QUOTES, 'UTF-8') . '" '
            . 'data-ad-format="auto" data-full-width-responsive="true"></ins>';
        $out .= '<script' . \Wawoo\Core\Csp::attr() . '>(adsbygoogle=window.adsbygoogle||[]).push({});</script>';
        $out .= '</div></div>';
        return $out;
    }

    /** Static Amazon banner by unit id + tag, or ''. */
    public static function amazon(string $slotKey): string
    {
        $tag   = (string)(Config::all()['amazon_tag_id'] ?? '');
        $unit  = Config::amazonUnit($slotKey);
        if ($tag === '' || $unit === '') {
            return '';
        }
        $minh = self::minHeight($slotKey);
        $out  = '<div class="hb-ad hb-ad--amazon-' . htmlspecialchars($slotKey, ENT_QUOTES, 'UTF-8') . '">';
        $out .= '<div class="hb-ad-frame" style="min-height:' . $minh . '">';
        $out .= '<img src="https://ws-eu.amazon-adsystem.com/widgets/q?ServiceVersion=20070822'
            . '&OneServer=1&Marketplace=JP&Operation=GetAdHtml&adId='
            . htmlspecialchars(rawurlencode($unit), ENT_QUOTES, 'UTF-8')
            . '&tag=' . htmlspecialchars(rawurlencode($tag), ENT_QUOTES, 'UTF-8')
            . '" alt="" loading="lazy" />';
        $out .= '</div></div>';
        return $out;
    }

    private static function minHeight(string $slotKey): string
    {
        return str_contains($slotKey, 'index') ? '250px' : '120px';
    }
}
