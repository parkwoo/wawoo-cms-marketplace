# plugins/api — サイトAPI（PHP）

wawoo-cms の `core.request` フックで `/api/*` を提供する **PHP 実装**。

## 制約（重要）

- **従来の PHP 空間（php-fpm / Apache）で動作**。サイト側に **Node ランタイムは無い**。
- サイト向け API は必ず **PHP** で実装する（Node サービスをサイト空間に置かない）。
- 制作パイプライン（`ai-wawoo-jp-backend`）は taro 側で Node/Dagu として動く。**別環境**。

## 有効化

既定は無効。`config.local.php` の `ENABLED_PLUGINS` に `api` を追加し、設定を渡す:

```php
define('ENABLED_PLUGINS', ['mlang', 'pagination', 'search', 'seo', 'menu', 'rss', 'api']);
$GLOBALS['WAWOO_API'] = [
    'enabled'      => true,
    'base_path'    => '/api',
    'token_header' => 'X-Api-Token',
];
```

## 予定ルート

| Method | Path | 役割 |
|---|---|---|
| GET | `/api/health` | 死活（実装済み） |
| POST | `/api/publish` | パイプラインからの **draft** 受付（`status` 必須・fail-closed） |
| POST | `/api/sign` | R2 の署名 URL 発行（秘密はサーバ側のみ） |

認証: `token_header` の値で呼び出し元を限定する（実装時に必須）。
