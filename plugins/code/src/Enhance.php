<?php
namespace Wawoo\Plugin\Code;

/**
 * Enhances fenced code blocks (theme.footer): sets pre[data-lang] so the
 * theme's language pill shows, and adds a copy button. Works on any theme
 * (a tiny fallback style is injected when the theme has none). Skips
 * mermaid-host blocks (handled by the mermaid plugin) and blocks that
 * already carry data-lang.
 */
final class Enhance
{
    public function handle(?array $vars = null): void
    {
        $cfg = Config::all();
        if (empty($cfg['enabled'])) {
            return;
        }
        $copy = !empty($cfg['copy_button']) ? 'true' : 'false';
        $label = trim((string)($cfg['copy_label'] ?? '')) ?: 'Copy';
        $copied = trim((string)($cfg['copied_label'] ?? '')) ?: 'Copied';
        $hl = !empty($cfg['highlight']);
        $hlCdn = trim((string)($cfg['hljs_cdn'] ?? ''));
        $hlIntegrity = trim((string)($cfg['hljs_integrity'] ?? ''));
        $hlTheme = trim((string)($cfg['hljs_theme_css'] ?? ''));
        // Colour tokens for the (always-light) code region.
        $css = static function (string $k, string $d) use ($cfg): string {
            $v = trim((string)($cfg[$k] ?? ''));
            return preg_match('/^#[0-9a-fA-F]{3,8}$/', $v) ? $v : $d;
        };
        $codeBg  = $css('code_bg', '#ffffff');
        $codeTxt = $css('code_text', '#1f2328');
        $codeBd  = $css('code_border', '#d8d8dc');
        $pillBg  = $css('pill_bg', '#f6f8fa');
        $pillTxt = $css('pill_text', '#57606a');

        // Syntax highlighting (highlight.js). Theme CSS and runtime JS are
        // injected only when the page actually contains a language block.
        if ($hl && $hlCdn !== '') {
            $okUrl = static function (string $u): bool {
                return preg_match('#^https://#i', $u) === 1
                    || (str_starts_with($u, '/') && !str_starts_with($u, '//')
                        && !str_starts_with($u, '/\\') && !str_starts_with($u, '/%5c'));
            };
            $themeCss = ($hlTheme !== '' && $okUrl($hlTheme)) ? json_encode($hlTheme) : 'null';
            if ($okUrl($hlCdn)) {
                $integrityAttr = $hlIntegrity !== ''
                    ? 's.integrity=' . json_encode($hlIntegrity) . ';s.crossOrigin="anonymous";'
                    : '';
                echo '<script>'
                    . '(function(){'
                    . 'var els=[].slice.call(document.querySelectorAll(".post-content pre code[class*=language-]:not(.language-mermaid), article pre code[class*=language-]:not(.language-mermaid)"));'
                    . 'if(!els.length){return;}'
                    // load theme CSS only now (code present)
                    . 'var theme=' . $themeCss . ';'
                    . 'if(theme){var l=document.createElement("link");l.rel="stylesheet";l.href=theme;document.head.appendChild(l);}'
                    . 'var s=document.createElement("script");'
                    . 's.src=' . json_encode($hlCdn) . ';'
                    . $integrityAttr
                    . 's.onload=function(){if(!window.hljs){return;}'
                    . 'els.forEach(function(c){'
                    . 'var pre=c.closest&&c.closest("pre");'
                    . 'if(pre){pre.setAttribute("translate","no");}'
                    . 'if(c.closest&&c.closest("pre.mermaid-host")){return;}'
                    . 'if(c.classList.contains("hljs")||c.getAttribute("data-highlighted")==="yes"){return;}'
                    . 'try{window.hljs.highlightElement(c);}catch(e){}'
                    . '});};'
                    . 'document.head.appendChild(s);'
                    . '})();'
                    . '</script>' . "\n";
            }
        }

        echo '<style>'
            // Code region is always light, with user-tunable colours,
            // regardless of the OS colour scheme.
            . '.post-content pre:not(.mermaid-host),article pre:not(.mermaid-host){'
            . 'background:' . $codeBg . '!important;color:' . $codeTxt . '!important;'
            . 'border-color:' . $codeBd . '!important;'
            . 'box-shadow:none!important;}'
            . '.post-content pre:not(.mermaid-host) code,article pre:not(.mermaid-host) code{'
            . 'background:transparent!important;color:' . $codeTxt . '!important;}'
            . '.post-content pre:not(.mermaid-host)::before,article pre:not(.mermaid-host)::before{'
            . 'color:' . $pillTxt . '!important;background:' . $pillBg . '!important;}'
            . '.wawoo-code-copy{position:absolute;top:.5rem;right:.5rem;z-index:5;'
            . 'font:inherit;font-size:.75rem;padding:.2rem .6rem;cursor:pointer;'
            . 'background:' . $pillBg . '!important;color:' . $pillTxt . '!important;'
            . 'border:1px solid ' . $codeBd . '!important;border-radius:6px;}'
            . '.wawoo-code-copy:hover{background:#0969da!important;color:#fff!important;}'
            . '.post-content pre,article pre{position:relative;}'
            // hljs palette (light-only, matches the code background).
            . '.hljs-keyword,.hljs-literal,.hljs-selector-tag{color:#cf222e!important}'
            . '.hljs-string,.hljs-regexp,.hljs-addition{color:#0a3069!important}'
            . '.hljs-title,.hljs-section,.hljs-name{color:#8250df!important}'
            . '.hljs-comment,.hljs-quote{color:#6e7781!important}'
            . '.hljs-meta,.hljs-number,.hljs-symbol{color:#0550ae!important}'
            . '.hljs-attr,.hljs-attribute,.hljs-variable{color:#953800!important}'
            . '.hljs-built_in,.hljs-type{color:#0550ae!important}'
            // Prism.js token classes (some setups mark code with .token.*
            // instead of hljs-*). Give them the same light palette so colours
            // survive whichever highlighter produced the markup.
            . '.token.keyword,.token.boolean,.token.important{color:#cf222e!important}'
            . '.token.string,.token.char,.token.url,.token.attr-value{color:#0a3069!important}'
            . '.token.comment,.token.prolog,.token.doctype,.token.cdata{color:#6e7781!important}'
            . '.token.function,.token.class-name{color:#8250df!important}'
            . '.token.number,.token.symbol,.token.constant,.token.hexcode{color:#0550ae!important}'
            . '.token.operator,.token.punctuation{color:#57606a!important}'
            . '.token.variable,.token.property,.token.attr-name{color:#953800!important}'
            . '.token.delimiter{color:#cf222e!important}'
            // Tables in article content: fixed light theme too, so code/tables
            // stay readable together on any OS colour scheme.
            . '.post-content table,article table{background:#ffffff!important;color:#1f2328!important;border-color:' . $codeBd . '!important;}'
            . '.post-content th,article th,.post-content td,article td{'
            . 'background:transparent!important;color:#1f2328!important;border-color:' . $codeBd . '!important;}'
            . '.post-content th,article th{background:' . $pillBg . '!important;color:' . $pillTxt . '!important;}'
            . '</style>' . "\n";

        echo '<script>'
            . '(function(){'
            . 'var copy=' . json_encode($copy) . ';'
            . 'var copyLabel=' . json_encode($label) . ';'
            . 'var copiedLabel=' . json_encode($copied) . ';'
            . 'var pres=document.querySelectorAll(".post-content pre, article pre");'
            . 'Array.prototype.forEach.call(pres,function(pre){'
            . 'if(pre.classList&&pre.classList.contains("mermaid-host")){return;}'
            . 'var code=pre.querySelector("code");if(!code){return;}'
            . 'if(!pre.hasAttribute("data-lang")){'
            . 'var lang="";'
            . 'Array.prototype.forEach.call(code.classList,function(c){'
            . 'if(c.indexOf("language-")===0&&c!=="language-mermaid"){lang=c.slice(9);}'
            . '});'
            . 'if(lang){pre.setAttribute("data-lang",lang);}'
            . '}'
            . 'if(copy==="true"&&!pre.querySelector(".wawoo-code-copy")){'
            . 'var btn=document.createElement("button");'
            . 'btn.type="button";btn.className="wawoo-code-copy";btn.textContent=copyLabel;'
            . 'btn.setAttribute("aria-label","Copy code to clipboard");'
            . 'btn.addEventListener("click",function(){'
            . 'var txt=code.textContent;'
            . 'function done(ok){btn.textContent=ok?copiedLabel:copyLabel;'
            . 'setTimeout(function(){btn.textContent=copyLabel;},1600);}'
            . 'if(navigator.clipboard&&navigator.clipboard.writeText){'
            . 'navigator.clipboard.writeText(txt).then(function(){done(true);},function(){done(false);});'
            . '}else{var ta=document.createElement("textarea");ta.value=txt;'
            . 'ta.style.position="fixed";ta.style.opacity="0";document.body.appendChild(ta);'
            . 'ta.select();var ok=false;try{ok=document.execCommand("copy");}catch(e){}'
            . 'document.body.removeChild(ta);done(ok);}'
            . '});'
            . 'pre.appendChild(btn);'
            . '}'
            . '});'
            . '})();'
            . '</script>' . "\n";
    }
}
