<?php
namespace Wawoo\Plugin\I18n;

/**
 * Renders a language picker into the theme navigation. Hooks `theme.nav`
 * (fired by theme headers inside the <nav> element). No-ops when fewer
 * than two languages are supported.
 */
final class Switcher
{
    public function handle(?array $vars = null): void
    {
        $cfg = Config::all();
        $supported = array_values((array)$cfg['supported']);
        if (count($supported) < 2) {
            return;
        }
        $current = Loader::current() ?: (string)$cfg['default'];
        $param   = (string)$cfg['url_param'];

        // Carry over the current query string (minus the language param and
        // non-scalar values) as hidden inputs.
        $path = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
        echo '<form method="get" action="' . htmlspecialchars($path ?: '/') . '" class="wawoo-lang-switcher" style="display:inline">';
        foreach ($_GET as $k => $v) {
            if ((string)$k === $param || !is_scalar($v)) {
                continue;
            }
            echo '<input type="hidden" name="' . htmlspecialchars((string)$k) . '" value="' . htmlspecialchars((string)$v) . '">';
        }
        echo '<select name="' . htmlspecialchars($param) . '" onchange="this.form.submit()" style="font-size:.85rem;padding:.25rem .5rem;border-radius:8px">';
        foreach ($supported as $code) {
            $code = (string)$code;
            $sel  = $code === $current ? ' selected' : '';
            echo '<option value="' . htmlspecialchars($code) . '"' . $sel . '>' . htmlspecialchars(strtoupper($code)) . '</option>';
        }
        echo '</select></form>';
    }
}
