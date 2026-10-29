<?php
namespace Wawoo\Plugin\MediaPlayer;

/**
 * `post.html` filter: prepends the 1-article-1-audio player (plus optional
 * video) to an already-rendered article. Markup is generated here — raw HTML
 * in markdown is never trusted (Parsedown safe mode stays on).
 *
 * YouTube is the primary source; the OOS/mp3 frame is the fallback. When
 * audio_yt is empty no probing happens and only the local player renders.
 */
final class Player
{
    public function handle($html, $post = null): string
    {
        $html = (string)$html;
        if (!is_array($post) || empty($post['slug'])) {
            return $html;
        }
        $meta = (array)($post['meta'] ?? []);
        $block = self::build($meta, (string)$post['slug']);
        return $block === '' ? $html : $block . $html;
    }

    /** Pure builder (unit-testable): returns the player block or ''. */
    public static function build(array $meta, string $slug): string
    {
        $audioUrl = trim((string)($meta['audio_url'] ?? ''));
        $audioYt  = trim((string)($meta['audio_yt'] ?? ''));
        $videoYt  = trim((string)($meta['video_yt'] ?? ''));
        $videoUrl = trim((string)($meta['video_url'] ?? ''));
        $duration = trim((string)($meta['duration'] ?? ''));

        $hasAudio = $audioUrl !== '' || $audioYt !== '';
        $hasVideo = $videoUrl !== '' || $videoYt !== '';
        if (!$hasAudio && !$hasVideo) {
            return ''; // no media -> player hidden entirely
        }

        $cfg = Config::all();
        $preload = (string)($cfg['preload'] ?? 'none');
        $uid = 'mp-' . preg_replace('/[^a-zA-Z0-9_-]/', '', $slug);
        $parts = [];
        $label = $duration !== '' ? '▶ ' . htmlspecialchars($duration, ENT_QUOTES, 'UTF-8')
                                  : (function_exists('t') ? t('media.listen', 'Listen') : 'Listen');
        $parts[] = '<div class="hb-player">';
        $parts[] = '<p class="hb-player-label">' . $label . '</p>';

        $audioLocalId = '';
        if ($hasAudio) {
            $ytId = $audioYt !== '' ? self::youtubeId($audioYt) : '';
            $localOk = $audioUrl !== '' && Config::allowedUrl($audioUrl);
            $localSrc = $localOk ? htmlspecialchars($audioUrl, ENT_QUOTES, 'UTF-8') : '';

            if ($ytId !== '' && $localSrc !== '') {
                // two-layer: YouTube box (probed) + hidden local box
                $parts[] = '<div id="ytBox-' . $uid . '"><div id="yt-' . $uid . '" data-plyr-provider="youtube" data-plyr-embed-id="'
                    . htmlspecialchars($ytId, ENT_QUOTES, 'UTF-8') . '"></div></div>';
                $parts[] = '<div id="loBox-' . $uid . '" class="hidden"><audio id="lo-' . $uid . '" controls preload="'
                    . htmlspecialchars($preload, ENT_QUOTES, 'UTF-8') . '"><source src="' . $localSrc . '" type="audio/mpeg"></audio></div>';
                $parts[] = self::probeScript($uid, (int)$cfg['probe_timeout_ms'], (string)$cfg['storage_key']);
            } elseif ($ytId !== '') {
                $parts[] = '<div id="ytBox-' . $uid . '"><div id="yt-' . $uid . '" data-plyr-provider="youtube" data-plyr-embed-id="'
                    . htmlspecialchars($ytId, ENT_QUOTES, 'UTF-8') . '"></div></div>';
                $parts[] = '<script' . \Wawoo\Core\Csp::attr() . '>(function(){if(window.Plyr){new Plyr(' . json_encode('#yt-' . $uid)
                    . ',{youtube:{noCookie:true,rel:0,modestbranding:1}});}})();</script>';
            } elseif ($localSrc !== '') {
                $parts[] = '<audio id="lo-' . $uid . '" controls preload="' . htmlspecialchars($preload, ENT_QUOTES, 'UTF-8') . '"'
                    . ' style="width:100%"><source src="' . $localSrc . '" type="audio/mpeg"></audio>';
                $audioLocalId = '#lo-' . $uid;
            } else {
                $url = $audioYt !== '' ? $audioYt : $audioUrl;
                $parts[] = '<p><a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">'
                    . (function_exists('t') ? t('media.open', 'Open media') : 'Open media') . '</a></p>';
            }
        }
        if ($hasVideo) {
            $vId = $videoYt !== '' ? self::youtubeId($videoYt) : '';
            if ($vId !== '') {
                $parts[] = '<div class="hb-player-video"><div id="vid-' . $uid . '" data-plyr-provider="youtube" data-plyr-embed-id="'
                    . htmlspecialchars($vId, ENT_QUOTES, 'UTF-8') . '"></div></div>';
            } elseif ($videoUrl !== '' && Config::allowedUrl($videoUrl)) {
                $parts[] = '<video controls preload="' . htmlspecialchars($preload, ENT_QUOTES, 'UTF-8') . '" style="width:100%">'
                    . '<source src="' . htmlspecialchars($videoUrl, ENT_QUOTES, 'UTF-8') . '" type="video/mp4"></video>';
            }
        }
        if ($audioLocalId !== '') {
            $parts[] = '<script' . \Wawoo\Core\Csp::attr() . '>(function(){if(window.Plyr){new Plyr(' . json_encode($audioLocalId) . ','
                . '{controls:[\'play-large\',\'play\',\'progress\',\'current-time\',\'mute\',\'volume\']});}})();</script>';
        }
        $parts[] = '</div>';
        return implode("\n", $parts) . "\n";
    }

    /** Extract an 11-char YouTube id from youtu.be/ID or watch?v=ID (else ''). */
    public static function youtubeId(string $url): string
    {
        $host = strtolower((string)parse_url($url, PHP_URL_HOST));
        if (!in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtube-nocookie.com', 'www.youtube-nocookie.com', 'youtu.be', 'www.youtu.be'], true)) {
            return '';
        }
        if (str_contains($host, 'youtu.be') && !str_contains($host, 'youtube')) {
            $id = basename((string)parse_url($url, PHP_URL_PATH));
        } else {
            parse_str((string)parse_url($url, PHP_URL_QUERY), $q);
            $id = (string)($q['v'] ?? '');
        }
        return preg_match('/^[0-9A-Za-z_-]{11}$/', $id) ? $id : '';
    }

    /**
     * Two-layer probe: keep YouTube unless a previous visit or a 4s favicon
     * probe says it is blocked; then swap to the local frame and remember.
     */
    public static function probeScript(string $uid, int $timeoutMs, string $storageKey): string
    {
        $sk = json_encode($storageKey . ':media-player');
        $cspAttr = \Wawoo\Core\Csp::attr();
        return <<<JS
<script{$cspAttr}>
(function () {
  var yt = document.getElementById('ytBox-{$uid}'), lo = document.getElementById('loBox-{$uid}');
  function showLocal() {
    if (yt) yt.classList.add('hidden');
    if (lo) lo.classList.remove('hidden');
    try { localStorage.setItem({$sk}, '1'); } catch (e) {}
    if (window.Plyr && lo) { new Plyr(lo.querySelector('audio'), {controls:['play-large','play','progress','current-time','mute','volume']}); }
  }
  try { if (localStorage.getItem({$sk})) { showLocal(); return; } } catch (e) {}
  var to = setTimeout(showLocal, {$timeoutMs});
  var img = new Image();
  img.onload = function () {
    clearTimeout(to);
    if (window.Plyr) { new Plyr('#yt-{$uid}', {youtube:{noCookie:true, rel:0, modestbranding:1}}); }
  };
  img.onerror = showLocal;
  img.src = 'https://www.youtube.com/favicon.ico?' + Date.now();
})();
</script>
JS;
    }
}
