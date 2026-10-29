# 翻訳プラグイン - 環境変数設定

## 概要

翻訳プラグインは、環境変数ファイル（.env）を使用してAPIキーなどの設定を管理できます。これにより、設定のセキュリティ向上と管理の容易化を実現します。

## .envファイルのサポート

### 基本的な使い方

1. **.envファイルの作成**
   ```bash
   php bin/wawoo translation:env:create
   ```

2. **環境変数の編集**
   ```bash
   nano .env
   ```

3. **環境変数の検証**
   ```bash
   php bin/wawoo translation:env:validate
   ```

4. **環境状態の確認**
   ```bash
   php bin/wawoo translation:env:status
   ```

### 環境変数の優先順位

設定値の優先順位は以下の通りです（高いほど優先）：

1. 環境変数（.envファイル）
2. グローバル変数（`$GLOBALS['WAWOO_TRANSLATION']`）
3. 設定ファイル（`plugin.json`）
4. デフォルト値

## .env.exampleファイル

`.env.example`ファイルは、設定するべき環境変数のテンプレートです。以下の変数がサポートされています：

### 必須変数

- `TRANSLATION_API_KEY`: 翻訳サービスのAPIキー

### オプション変数

- `TRANSLATION_PROVIDER`: 翻訳プロバイダー（デフォルト: `openai`）
- `TRANSLATION_MODEL`: 使用するモデル（デフォルト: `gpt-3.5-turbo`）
- `TRANSLATION_SOURCE_LANGUAGE`: 翻訳元の言語（デフォルト: `ja`）
- `TRANSLATION_TARGET_LANGUAGE`: 翻訳先の言語（デフォルト: `en`）
- `TRANSLATION_AUTO_TRANSLATE`: 自動翻訳の有無（デフォルト: `false`）
- `TRANSLATION_VALIDATE_TRANSLATION`: 翻訳の検証（デフォルト: `true`）
- `TRANSLATION_FALLBACK_TO_ORIGINAL`: 翻訳失敗時のフォールバック（デフォルト: `true`）
- `TRANSLATION_MAX_RETRIES`: 最大リトライ回数（デフォルト: `3`）
- `TRANSLATION_TIMEOUT`: タイムアウト秒数（デフォルト: `30`）
- `TRANSLATION_BATCH_SIZE`: バッチサイズ（デフォルト: `5`）

## セキュリティ対策

### .envファイルの保護

1. **.gitignoreへの追加**
   - `.env`ファイルは自動的に`.gitignore`に追加されます
   - コミットしないように注意してください

2. **ファイルパーミッション**
   - .envファイルのパーミッションを`600`に設定することを推奨します
   ```bash
   chmod 600 .env
   ```

3. **APIキーのマスキング**
   - ログやエラーメッセージでは、APIキーはマスクされて表示されます

### APIキーの形式

- **OpenAI**: `sk-[a-zA-Z0-9]{48}`
- **Anthropic**: `sk-ant-api03-[a-zA-Z0-9_-]{42}`

## 使用方法

### 基本的な設定

1. .envファイルを作成します：
   ```bash
   php bin/wawoo translation:env:create
   ```

2. .envファイルを編集します：
   ```bash
   # .env
   TRANSLATION_API_KEY=your-openai-api-key-here
   TRANSLATION_PROVIDER=openai
   TRANSLATION_MODEL=gpt-3.5-turbo
   ```

3. 翻訳をテストします：
   ```bash
   php bin/wawoo translation:config:test
   ```

### 開発環境での使用

開発環境では、`.env`ファイルをプロジェクトルートに配置します：

```
wawoo-cms/
├── .env
├── plugins/
│   └── translation/
└── ...
```

### 本番環境での使用

本番環境では、環境変数を直接サーバーに設定することを推奨します：

```bash
# Linux/macOS
export TRANSLATION_API_KEY="your-api-key"
export TRANSLATION_PROVIDER="openai"

# Windows
set TRANSLATION_API_KEY="your-api-key"
set TRANSLATION_PROVIDER="openai"
```

## 環境変数のバリデーション

プラグインは以下のバリデーションを実行します：

1. **必須変数のチェック**
   - `TRANSLATION_API_KEY`が設定されていること

2. **APIキーの形式チェック**
   - プロバイダーに応じた形式かを検証

3. **言語コードの検証**
   - ISO 639-1形式かを検証

4. **数値値の検証**
   - 数値フィールドが正しい形式かを検証

## トラブルシューティング

### 環境変数が読み込まれない場合

1. .envファイルのパスを確認
2. ファイルのパーミッションを確認
3. 環境変数の名前を確認（大文字・小文字を区別）

### APIキーが無効な場合

1. APIキーの形式を確認
2. プロバイダー設定を確認
3. APIキーが有効かを確認

### 設定が反映されない場合

1. 優先順位を確認
2. キャッシュをクリア：
   ```bash
   php bin/wawoo cache:clear
   ```

## CLIコマンド

| コマンド | 説明 |
|---------|------|
| `php bin/wawoo translation:env:create` | .envファイルを作成 |
| `php bin/wawoo translation:env:validate` | 環境変数を検証 |
| `php bin/wawoo translation:env:status` | 環境状態を表示 |
| `php bin/wawoo translation:config:test` | 設定をテスト |
| `php bin/wawoo translation:config:validate` | 設定を検証 |

## 注意事項

- .envファイルには機密情報が含まれるため、コミットしないでください
- 環境変数はアプリケーション起時に読み込まれます
- 設定変更後は、アプリケーションを再起動する必要があります