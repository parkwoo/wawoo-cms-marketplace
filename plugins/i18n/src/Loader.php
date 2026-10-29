<?php
namespace Wawoo\Plugin\I18n {

/**
 * Detects the active language and loads translations. Exposes:
 * - `Loader::t()` static helper
 * - namespaced `Wawoo\Plugin\I18n\t()` for plugin code
 * - a global `t()` fallback for theme templates (defined only when no
 *   global `t()` exists yet)
 */
final class Loader
{
    private static array $translations = [];
    private static ?string $current = null;

    public static function detect(): string
    {
        $cfg = Config::all();
        $supported = (array)$cfg['supported'];
        $default   = (string)$cfg['default'];
        $param     = (string)$cfg['url_param'];

        // 1) URL parameter
        if (isset($_GET[$param]) && in_array($_GET[$param], $supported, true)) {
            self::setCookie($cfg, (string)$_GET[$param]);
            return (string)$_GET[$param];
        }
        // 2) Cookie
        $cookie = $_COOKIE[$cfg['cookie_name']] ?? '';
        if ($cookie !== '' && in_array($cookie, $supported, true)) {
            return $cookie;
        }
        // 3) Browser Accept-Language
        if (!empty($cfg['detect_browser']) && !empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            foreach (explode(',', (string)$_SERVER['HTTP_ACCEPT_LANGUAGE']) as $tag) {
                $code = strtolower(substr(trim($tag), 0, 2));
                if (in_array($code, $supported, true)) {
                    return $code;
                }
            }
        }
        return $default;
    }

    public static function load(string $lang): array
    {
        $dir = dirname(__DIR__) . '/lang';
        $file = $dir . '/' . $lang . '.php';
        if (is_file($file)) {
            $trans = require $file;
            if (is_array($trans)) {
                return $trans;
            }
        }
        // Fallback to default
        $default = (string)Config::all()['default'];
        $file = $dir . '/' . $default . '.php';
        if (is_file($file)) {
            $trans = require $file;
            if (is_array($trans)) {
                return $trans;
            }
        }
        return [];
    }

    public static function init(): void
    {
        $lang = self::detect();
        self::$current = $lang;
        self::$translations = self::load($lang);

        // Expose globally for templates that prefer globals over function calls.
        $GLOBALS['__WAWOO_LANG'] = $lang;
        $GLOBALS['__WAWOO_T']    = self::$translations;
    }

    /**
     * Test helper: load a specific language without performing browser
     * detection. Bypasses session/cookie side effects.
     */
    public static function setLanguage(string $lang): void
    {
        self::$current = $lang;
        self::$translations = self::load($lang);
        $GLOBALS['__WAWOO_LANG'] = $lang;
        $GLOBALS['__WAWOO_T']    = self::$translations;
    }

    public static function current(): ?string
    {
        return self::$current;
    }

    public static function all(): array
    {
        return self::$translations;
    }

    public static function t(string $key, string $default = ''): string
    {
        $v = self::$translations[$key] ?? null;
        if (is_string($v)) {
            return $v;
        }
        return $default !== '' ? $default : $key;
    }

    /**
     * Persist the chosen language. Skips setcookie entirely when no cookie
     * name is configured and avoids needless Set-Cookie headers when the
     * stored value already matches.
     */
    private static function setCookie(array $cfg, string $lang): void
    {
        $name = (string)($cfg['cookie_name'] ?? '');
        if ($name === '') {
            return;
        }
        if (($_COOKIE[$name] ?? '') === $lang) {
            return;
        }
        setcookie($name, $lang, [
            'expires'  => time() + 86400 * 365,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

/**
 * Namespaced helper for plugin code: t('site.name') or t('nav.home').
 * Falls back to the key itself when not translated.
 */
if (!function_exists(__NAMESPACE__ . '\\t')) {
    function t(string $key, string $default = ''): string
    {
        return Loader::t($key, $default);
    }
}

}

namespace {

/**
 * Global fallback so theme templates can call t('site.name'). Defined only
 * when no global t() already exists. Delegates to the namespaced loader.
 */
if (!function_exists('t')) {
    function t(string $key): string
    {
        return \Wawoo\Plugin\I18n\Loader::t($key);
    }
}

}
