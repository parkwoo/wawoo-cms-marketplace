<?php
namespace Wawoo\Plugin\Ads;

final class Config
{
    private static ?array $cache = null;

    /** @return array<string,mixed> */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $defaults = [
            'adsense_client' => '',
            'adsense_slots'  => [],
            'amazon_tag_id'  => '',
            'amazon_units'   => [],
            'lazy'           => true,
            'consent_cmp'    => true,
            'disable_key'    => 'ads',
            'preview'        => false,
        ];
        $file = dirname(__DIR__) . '/plugin.json';
        if (is_file($file)) {
            $raw = json_decode((string)file_get_contents($file), true);
            if (is_array($raw) && is_array($raw['config'] ?? null)) {
                $defaults = array_replace($defaults, $raw['config']);
            }
        }
        $override = $GLOBALS['WAWOO_ADS'] ?? [];
        self::$cache = array_replace($defaults, is_array($override) ? $override : []);
        return self::$cache;
    }

    public static function reset(): void
    {
        self::$cache = null;
    }

    public static function slot(string $name): string
    {
        $slots = (array)(self::all()['adsense_slots'] ?? []);
        return (string)($slots[$name] ?? '');
    }

    public static function amazonUnit(string $name): string
    {
        $units = (array)(self::all()['amazon_units'] ?? []);
        return (string)($units[$name] ?? '');
    }

    /** True when at least one ad network is configured for this site. */
    public static function anyEnabled(): bool
    {
        $cfg = self::all();
        if ((string)($cfg['adsense_client'] ?? '') !== '') {
            return true;
        }
        foreach ((array)($cfg['adsense_slots'] ?? []) as $s) {
            if ((string)$s !== '') {
                return true;
            }
        }
        if ((string)($cfg['amazon_tag_id'] ?? '') !== '') {
            return true;
        }
        return false;
    }

    /**
     * 開発プレビュー: config.preview=true のとき、広告 ID 未設定でも枠（プレースホルダ）を
     * 表示する。**本番では false（既定）**にすること（実広告のみを出す）。
     */
    public static function preview(): bool
    {
        if (empty(self::all()['preview'])) {
            return false;
        }
        // 開発（DEBUG）または CLI（テスト）でのみ有効。本番 Web では常に false。
        return (defined('DEBUG') && DEBUG) || PHP_SAPI === 'cli';
    }

    /**
     * Editorial per-post override: `ads: false` in frontmatter (read from the
     * loaded post meta) hides ad slots for that article only.
     */
    public static function disabledFor(?array $post): bool
    {
        if (!is_array($post)) {
            return false;
        }
        $key = (string)(self::all()['disable_key'] ?? 'ads');
        $val = $post['meta'][$key] ?? null;
        return $val === false || (is_string($val) && strtolower(trim($val)) === 'false');
    }
}
