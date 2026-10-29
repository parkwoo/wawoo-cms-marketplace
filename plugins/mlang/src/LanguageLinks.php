<?php
namespace Wawoo\Plugin\Mlang;

/**
 * Renders the "other languages" block shared by the theme content hooks.
 * No-ops when fewer than two translations actually exist for the bundle.
 */
final class LanguageLinks
{
    public static function output(?array $vars, string $kind): void
    {
        $content = $kind === 'pages' ? ($vars['page'] ?? null) : ($vars['post'] ?? null);
        if (!is_array($content) || empty($content['slug'])) {
            return;
        }
        $current = (string)($content['lang'] ?? '');
        if ($current === '') {
            $current = Languages::default();
        }
        $present = array_values((array)($content['translations'] ?? []));
        if (count($present) < 2) {
            return;
        }
        $others = [];
        foreach (Languages::supported() as $code) {
            if ((string)$code !== $current && in_array((string)$code, $present, true)) {
                $others[] = (string)$code;
            }
        }
        if ($others === []) {
            return;
        }
        $slug = (string)$content['slug'];
        $links = [];
        foreach ($others as $code) {
            $links[] = '<a href="' . htmlspecialchars(Languages::url($kind, $slug, $code), ENT_QUOTES, 'UTF-8')
                . '" lang="' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8')
                . '" hreflang="' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '">'
                . htmlspecialchars(Languages::name($code), ENT_QUOTES, 'UTF-8') . '</a>';
        }
        echo '<style>.mlang-links{margin:2rem 0 0;display:flex;flex-wrap:wrap;gap:.5rem}'
            . '.mlang-links a{padding:.2rem .7rem;border:1px solid #d5d5d5;border-radius:999px;text-decoration:none;font-size:.9rem}'
            . '.mlang-links a:hover{border-color:#888}</style>' . "\n";
        echo '<p class="mlang-links">' . implode('', $links) . "</p>\n";
    }
}
