<?php
namespace Wawoo\Plugin\Mermaid;

/**
 * Injects a small Mermaid loader at the end of the body (theme.footer).
 *
 * The Mermaid runtime is only fetched when the page actually contains a
 * ```mermaid fenced block (rendered by Parsedown as
 * <pre><code class="language-mermaid">). Blocks are rendered to SVG via
 * mermaid.run; on load failure the original text block is kept intact, so
 * the content stays readable without JavaScript.
 */
final class Assets
{
    private const THEMES = ['default', 'neutral', 'dark', 'forest', 'base'];
    private const SECURITY_LEVELS = ['strict', 'sandbox'];

    public function handle(?array $vars = null): void
    {
        $cfg = Config::all();
        if (empty($cfg['enabled']) || trim((string)$cfg['cdn_url']) === '') {
            return;
        }
        $cdn = trim((string)$cfg['cdn_url']);
        // Output-side hardening: even if the settings JSON was hand-edited,
        // never emit an http:// URL or a non-whitelisted theme/security value.
        $isHttps = preg_match('#^https://#i', $cdn) === 1;
        $isSameOrigin = str_starts_with($cdn, '/')
            && !str_starts_with($cdn, '//')
            && !str_starts_with($cdn, '/\\')
            && !str_starts_with($cdn, '/%5c');
        if (!$isHttps && !$isSameOrigin) {
            return;
        }
        $theme  = in_array((string)$cfg['theme'], self::THEMES, true) ? (string)$cfg['theme'] : 'default';
        $dark   = in_array((string)$cfg['dark_theme'], self::THEMES, true) ? (string)$cfg['dark_theme'] : $theme;
        $security = in_array((string)$cfg['security_level'], self::SECURITY_LEVELS, true)
            ? (string)$cfg['security_level'] : 'strict';
        $integrity = trim((string)($cfg['cdn_integrity'] ?? ''));
        $diagramBg = trim((string)($cfg['diagram_bg'] ?? ''));
        if ($diagramBg !== '' && preg_match('/^#[0-9a-fA-F]{3,8}$/', $diagramBg) !== 1) {
            $diagramBg = '';
        }
        $followScheme = !empty($cfg['follow_scheme']);
        $startOnLoad = !empty($cfg['start_on_load']);

        // Theme: by default the diagram uses the configured (light) theme so
        // node colours stay readable on a white diagram background regardless
        // of the OS colour scheme. follow_scheme opts into switching to
        // dark_theme under prefers-color-scheme: dark.
        $themeExpr = $followScheme
            ? '(window.matchMedia&&window.matchMedia("(prefers-color-scheme: dark)").matches?'
                . json_encode($dark) . ':' . json_encode($theme) . ')'
            : json_encode($theme);
        $sol = $startOnLoad ? 'true' : 'false';
        // promoteAll moves any rendered <svg> out of the inline <code> into a
        // block-level pre.mermaid-host (keeps real aspect ratio).
        $promote = 'var promoteAll=function(){'
            . 'Array.prototype.forEach.call(document.querySelectorAll("code.language-mermaid"),function(b){'
            . 'var s=b.querySelector("svg");var pre=b.closest("pre");'
            . 'if(s&&pre){s.style.display="block";s.style.width="100%";s.style.height="auto";s.style.maxWidth="100%";s.style.margin="0 auto";s.removeAttribute("width");'
            . 'pre.appendChild(s);pre.classList.add("mermaid-host");pre.removeAttribute("data-lang");'
            . 'if(b.parentNode===pre){pre.removeChild(b);}'
            . '}});};';
        if ($startOnLoad) {
            // Auto path: mermaid renders on its own once initialized; we poll
            // shortly after to promote SVGs.
            $runCode = 'mermaid.initialize({startOnLoad:true,securityLevel:' . json_encode($security)
                . ',theme:' . $themeExpr . '});'
                . 'setTimeout(promoteAll,400);setTimeout(promoteAll,1500);';
        } else {
            // Manual path: explicit run with per-block error handling.
            $runCode = 'mermaid.initialize({startOnLoad:false,securityLevel:' . json_encode($security)
                . ',theme:' . $themeExpr . '});'
                . 'var blocks=[].slice.call(document.querySelectorAll(".post-content code.language-mermaid, article code.language-mermaid, .post-content pre > code.language-mermaid"));'
                . 'var saved=blocks.map(function(b){return b.textContent;});'
                . 'mermaid.run({querySelector:".post-content code.language-mermaid, article code.language-mermaid, .post-content pre > code.language-mermaid"}).then(function(){'
                . 'promoteAll();'
                . '},function(){'
                . 'blocks.forEach(function(b,i){if(!b.querySelector("svg")){b.textContent=saved[i];}});'
                . '});';
        }
        $runCode = $promote . $runCode;

        $integrityAttr = $integrity !== ''
            ? 's.integrity=' . json_encode($integrity) . ';s.crossOrigin="anonymous";'
            : '';

        $bgRule = $diagramBg !== ''
            ? 'background:' . $diagramBg . '!important;padding:.8rem;'
            : 'background:transparent;padding:0;';
        echo '<style>pre.mermaid-host{'
            . $bgRule
            . 'border:0;overflow-x:auto;margin:2em 0;}'
            . 'pre.mermaid-host svg{max-width:100%;height:auto;display:block;margin-inline:auto;}</style>' . "\n";
        echo '<script>'
            . '(function(){'
            . 'var nodes=document.querySelectorAll(".post-content code.language-mermaid, article code.language-mermaid, .post-content pre > code.language-mermaid");'
            . 'if(!nodes.length){return;}'
            . 'var s=document.createElement("script");'
            . 's.src=' . json_encode($cdn) . ';s.defer=true;'
            . $integrityAttr
            . 's.onload=function(){if(!window.mermaid){return;}'
            . $runCode
            . '};'
            . 'document.head.appendChild(s);'
            . '})();'
            . '</script>' . "\n";
    }
}

