<?php
namespace Wawoo\Plugin\Api;

/**
 * サイトAPI の設定。`plugin.json` の config 既定 → `$GLOBALS['WAWOO_API']` の順でマージ。
 */
final class Config
{
    public static function defaults(): array
    {
        return [
            'enabled'      => false,
            'base_path'    => '/api',
            'token_header' => 'X-Api-Token',
            'token'        => '',
            'allowed_methods' => ['GET', 'PUT'],
            'key_prefixes'    => ['audio/'],
            'default_expires' => 600,
            'r2'           => [
                'endpoint'          => '',
                'bucket'            => '',
                'access_key_id'     => '',
                'secret_access_key' => '',
                'region'            => 'auto',
            ],
        ];
    }

    public static function all(): array
    {
        $g = $GLOBALS['WAWOO_API'] ?? [];
        if (!is_array($g)) {
            $g = [];
        }
        $cfg = array_replace(self::defaults(), $g);
        $cfg['r2'] = array_replace(self::defaults()['r2'], is_array($g['r2'] ?? null) ? $g['r2'] : []);
        return $cfg;
    }
}
