# 日本語→英語自動翻訳システム 技術設計

## 対象範囲・前提・ステータス

- **対象範囲**: wawoo-cmsプロジェクトの日本語コンテンツファイル(.md)を自動で翻訳し、英語版ファイルを生成・更新するシステム
- **前提**: 
  - wawoo-cmsはファイルベースのCMS（PHP 8.3、Markdownコンテンツ）
  - コンテンツは `posts/<slug>/index.ja.md` の形式で保存
  - 英語版は `posts/<slug>/index.md` として保存
- **ステータス**: 草案

## 背景 / 目的

wawoo-cmsは日本語コンテンツを中心に運用されていますが、グローバル展開に向けて英語コンテンツの必要性が高まっています。現在は手動で翻訳ファイルを作成・管理しているため、以下の問題があります：

1. **手動翻訳の非効率性**: 新規コンテンツ追加時、手動で翻訳ファイルを作成する必要がある
2. **更新の遅延**: 日本語コンテンツ更新時に英語版の更新が漏れやすい
3. **翻訳品質のばらつき**: 複数担当者による翻訳で品質が統一されない
4. **リソースの非効率**: 翻訳作業に多くの工数を費やす必要がある

自動翻訳システムを導入することで、コンテンツ更新を即座に英語版に反映し、翻訳工数を削減し、品質を統一することを目指します。

## 現状の問題一覧

| # | 重大度 | 場所 | 問題の説明 |
|---|--------|------|-----------|
| 1 | 高 | コンテンツ管理 | 日本語コンテンツ追加時に手動で英語版ファイルを作成する必要がある |
| 2 | 高 | コンテンツ更新 | 日本語コンテンツ更新時に英語版の更新が漏れやすい |
| 3 | 中 | 翻訳品質 | 複数担当者による翻訳で品質が統一されない |
| 4 | 中 | リソース | 翻訳作業に多くの工数を費やす必要がある |

## 方針

### 何を変えないか

- wawoo-cmsのファイルベースCMSアーキテクチャ
- コンテンツの保存形式（Markdown + Frontmatter）
- 既存のプラグインシステム
- セキュリティポリシー

### 何を変えるか

- 翻訳プロセスを自動化
- 外部LLM APIを統合
- 翻訳品質検証機能の追加
- 設定ファイルでのAPIキー管理

## アーキテクチャ図

```
┌─────────────────────────────────────────────────────────────┐
│                     wawoo-cms                              │
├─────────────────────────────────────────────────────────────┤
│  コンテンツ検出  │  翻訳実行  │  品質検証  │  ファイル生成  │
│  (ContentFinder) │ (Translator) │ (Validator) │ (FileGenerator) │
├─────────────────────────────────────────────────────────────┤
│                     翻訳プラグイン                           │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────┐ │
│  │  設定管理       │  │  APIクライアント │  │  ロギング   │ │
│  │ (ConfigManager) │  │ (APIClient)    │  │ (Logger)    │ │
│  └─────────────────┘  └─────────────────┘  └─────────────┘ │
├─────────────────────────────────────────────────────────────┤
│                  外部LLM API                                │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐       │
│  │  OpenAI     │  │  Claude    │  │  その他LLM  │       │
│  │  GPT-4/3.5  │  │  Opus/Sonnet│  │  Gemini等   │       │
│  └─────────────┘  └─────────────┘  └─────────────┘       │
└─────────────────────────────────────────────────────────────┘
```

## ファイル構造

```
wawoo-cms/
├── plugins/
│   └── translation/
│       ├── plugin.json              # プラグイン定義
│       ├── src/
│       │   ├── Plugin.php           # プラグインエントリーポイント
│       │   ├── ContentFinder.php    # 日本語コンテンツ検出
│       │   ├── Translator.php       # 翻訳実行
│       │   ├── Validator.php        # 翻訳品質検証
│       │   ├── FileGenerator.php    # 英語ファイル生成
│       │   ├── ConfigManager.php    # 設定管理
│       │   ├── APIClient.php        # APIクライアント
│       │   └── Logger.php           # ロギング
│       ├── config/
│       │   └── default.json         # デフォルト設定
│       └── templates/
│           └── admin/
│               ├── dashboard.php     # 管理ダッシュボード
│               └── settings.php      # 設定画面
├── bin/
│   └── wawoo
│       └── commands/
│           └── translation.php      # CLIコマンド
├── tests/
│   └── Plugin/
│       └── TranslationPluginTest.php # テスト
└── config.translation.example.php   # 設定例
```

## 主要コンポーネント

### 1. ContentFinder（コンテンツ検出）

**役割**: 日本語コンテンツファイルを検出し、翻訳対象を特定

**機能**:
- `posts/` ディレクトリ再帰検索
- `index.ja.md` ファイルの検出
- Frontmatterの解析（翻訳対象フラグの確認）
- 更新日時による差分検出

**メソッド**:
```php
public function findJapaneseContent(): array
public function getUpdatedContent(DateTime $since): array
public function isTranslationEnabled(array $frontmatter): bool
```

### 2. Translator（翻訳実行）

**役割**: 外部LLM APIを呼び出して日本語→英語翻訳を実行

**機能**:
- 複数LLM APIのサポート（OpenAI, Claude等）
- バッチ処理による効率的な翻訳
- レートリミット処理
- リトライロジック

**メソッド**:
```php
public function translate(string $japaneseText, array $context = []): string
public function batchTranslate(array $contents): array
public function getProvider(): string
public function setProvider(string $provider)
```

### 3. Validator（翻訳品質検証）

**役割**: 翻訳品質を検証し、問題を特定

**機能**:
- 基本的な品質チェック（空チェック、文字数比等）
- 構文チェック
- コンテンツの一貫性チェック
- 品質スコアリング

**メソッド**:
```php
public function validate(string $original, string $translated): ValidationResult
public function getQualityScore(string $original, string $translated): float
public function checkConsistency(array $contents): array
```

### 4. FileGenerator（ファイル生成）

**役割**: 英語版ファイルを生成・更新

**機能**:
- Frontmatterの自動更新
- 翻訳日時の記録
- バックアップ機能
- ファイル権限の維持

**メソッド**:
```php
public function generateEnglishFile(string $japanesePath, string $translatedContent): string
public function backupOriginal(string $path): string
public function updateFrontmatter(array $frontmatter): array
```

### 5. ConfigManager（設定管理）

**役割**: 設定ファイルの管理と取得

**機能**:
- APIキー管理
- 翻訳設定の管理
- プロバイダ設定の管理

**メソッド**:
```php
public function getAPIKey(): string
public function getProvider(): string
public function getSettings(): array
public function validateConfig(): bool
```

### 6. APIClient（APIクライアント）

**役割**: LLM APIとの通信を担当

**機能**:
- HTTPリクエストの送信
- レスポンスの解析
- エラーハンドリング
- レートリミット管理

**メソッド**:
```php
public function callAPI(array $request): array
public function handleRateLimit(): void
public function parseResponse(array $response): string
```

## API呼び出しロジック

### 基本的な流れ

1. **コンテンツ検出**:
   ```php
   $finder = new ContentFinder();
   $contents = $finder->findJapaneseContent();
   ```

2. **翻訳実行**:
   ```php
   $translator = new Translator();
   $results = $translator->batchTranslate($contents);
   ```

3. **品質検証**:
   ```php
   $validator = new Validator();
   $validationResults = [];
   foreach ($results as $result) {
       $validationResults[] = $validator->validate($result['original'], $result['translated']);
   }
   ```

4. **ファイル生成**:
   ```php
   $generator = new FileGenerator();
   foreach ($results as $result) {
       $generator->generateEnglishFile($result['path'], $result['translated']);
   }
   ```

### APIリクエスト例（OpenAI）

```json
{
  "model": "gpt-4",
  "messages": [
    {
      "role": "system",
      "content": "You are a professional translator. Translate Japanese content to natural English while preserving the original meaning and formatting."
    },
    {
      "role": "user",
      "content": "日本語のコンテンツを自然な英語に翻訳してください。元の意味と書式を保持してください。"
    },
    {
      "role": "assistant",
      "content": "Translate the Japanese content to natural English while preserving the original meaning and formatting."
    },
    {
      "role": "user",
      "content": "翻訳対象の日本語テキスト"
    }
  ],
  "temperature": 0.3,
  "max_tokens": 4000
}
```

### APIリクエスト例（Claude）

```json
{
  "model": "claude-3-opus-20240229",
  "max_tokens": 4000,
  "messages": [
    {
      "role": "user",
      "content": "以下の日本語コンテンツを自然な英語に翻訳してください。元の意味と書式を保持してください。\n\n---\n翻訳対象の日本語テキスト\n---"
    }
  ]
}
```

## エラーハンドリング

### 1. APIエラー

**エラーの種類**:
- 認証エラー（APIキー無効）
- レートリミット超過
- ネットワークエラー
- サーバーエラー

**対応策**:
```php
try {
    $response = $this->client->callAPI($request);
} catch (AuthenticationException $e) {
    // 認証エラー：ログに記録し、管理画面に通知
    $this->logger->error('API認証エラー: ' . $e->getMessage());
    $this->notifyAdmin('API認証エラーが発生しました');
} catch (RateLimitException $e) {
    // レートリミット：待機してリトライ
    $this->handleRateLimit();
    return $this->translate($text, $context);
} catch (NetworkException $e) {
    // ネットワークエラー：リトライ
    return $this->retryWithBackoff($text, $context);
} catch (ServerException $e) {
    // サーバーエラー：別のプロバイダにフォールバック
    return $this->fallbackProvider($text, $context);
}
```

### 2. ファイル操作エラー

**エラーの種類**:
- 書き込み権限エラー
- ディスク容量不足
- ファイルロック

**対応策**:
```php
try {
    $this->fileGenerator->generateEnglishFile($path, $content);
} catch (PermissionException $e) {
    // 権限エラー：管理者に通知
    $this->notifyAdmin('ファイル書き込み権限エラー: ' . $path);
} catch (DiskSpaceException $e) {
    // ディスク容量不足：ログに記録
    $this->logger->error('ディスク容量不足');
} catch (LockException $e) {
    // ファイルロック：待機してリトライ
    $this->waitForLock($path);
}
```

### 3. 翻訳品質エラー

**エラーの種類**:
- 翻訳失敗（空出力等）
- 品質基準未達

**対応策**:
```php
$validation = $this->validator->validate($original, $translated);
if (!$validation->isValid()) {
    // 品質問題：ログに記録し、手動修正を依頼
    $this->logger->warning('翻訳品質問題: ' . $validation->getIssues());
    $this->requestManualReview($path, $validation);
}
```

## セキュリティ対策

### 1. APIキー管理

**対策**:
- 設定ファイルでの管理（`config.translation.php`）
- 環境変数のサポート
- 暗号化オプション
- アクセス権限の制限

**実装例**:
```php
// config.translation.php
return [
    'api_keys' => [
        'openai' => getenv('OPENAI_API_KEY') ?? '',
        'claude' => getenv('CLAUDE_API_KEY') ?? '',
    ],
    'encryption' => [
        'enabled' => true,
        'key' => getenv('ENCRYPTION_KEY') ?? ''
    ]
];
```

### 2. 入力検証

**対策**:
- Markdownのサニタイズ
- ファイル名の検証
- パスのトラバーサル攻撃防止

**実装例**:
```php
public function sanitizeMarkdown(string $content): string
{
    // 危険なタグの除去
    $content = strip_tags($content, '<p><h1><h2><h3><h4><h5><h6><ul><ol><li><blockquote><code><pre><strong><em>');
    // XSS対策
    $content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
    return $content;
}
```

### 3. アクセス制御

**対策**:
- 管理画面へのアクセス制限
- CLIコマンドの認証
- ログイン必須機能

**実装例**:
```php
public function checkPermission(): bool
{
    if (!$this->auth->isAdmin()) {
        throw new PermissionException('管理者権限が必要です');
    }
    return true;
}
```

### 4. ロギング監視

**対策**:
- 操作ログの記録
- エラーログの監視
- 異常検知

**実装例**:
```php
public function logOperation(string $operation, array $details): void
{
    $this->logger->info('Translation operation', [
        'operation' => $operation,
        'details' => $details,
        'user' => $this->auth->getCurrentUser(),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'CLI'
    ]);
}
```

## 実施ステップ

| 項番 | Phase | 対象ファイル | 変更内容 |
|------|-------|-------------|----------|
| 1 | Phase 1: 基礎構築 | plugins/translation/plugin.json | プラグイン定義の作成 |
| 2 | Phase 1: 基礎構築 | plugins/translation/src/ | 各コンポーネントの実装 |
| 3 | Phase 1: 基礎構築 | plugins/translation/config/default.json | デフォルト設定の作成 |
| 4 | Phase 1: 基礎構築 | config.translation.example.php | 設定例の作成 |
| 5 | Phase 2: 管理画面 | plugins/translation/templates/admin/ | 管理画面の実装 |
| 6 | Phase 2: 管理画面 | bin/wawoo/commands/translation.php | CLIコマンドの実装 |
| 7 | Phase 3: テスト | tests/Plugin/TranslationPluginTest.php | テストの実装 |
| 8 | Phase 3: ドキュメント | docs/translation-system.md | ドキュメントの作成 |

## 検証方法・完了条件

### 機能検証

1. **自動翻訳機能**:
   - 日本語ファイル検出が正しく動作すること
   - LLM APIを呼び出して翻訳が実行されること
   - 英語ファイルが正しく生成されること

2. **品質検証機能**:
   - 品質チェックが実行されること
   - 問題がある場合に警告が表示されること

3. **管理画面**:
   - 翻訳状況の確認ができること
   - 設定変更ができること
   - エラーログの確認ができること

### パフォーマンス検証

1. **翻訳速度**:
   - 単体翻訳：10秒以内
   - バッチ翻訳：100件で5分以内

2. **リソース使用量**:
   - メモリ使用量：100MB以内
   - CPU使用率：50%以下

3. **エラーハンドリング**:
   - 各種エラーが適切に処理されること
   - リトライが正しく動作すること

### セキュリティ検証

1. **APIキー管理**:
   - 設定ファイルが正しく読み込まれること
   - キーが漏洩しないこと

2. **入力検証**:
   - 危険な入力がフィルタリングされること
   - パストラバーサル攻撃が防げること

## リスク・注意点・ロールバック手順

### リスク

1. **APIコスト**:
   - 大量の翻訳でAPIコストが増加するリスク
   - **対策**: 月次コスト制限の設定、翻訳ジョブのバッチ処理

2. **品質問題**:
   - 自動翻訳の品質が不安定なリスク
   - **対策**: 品質検証機能の強化、人間によるレビュー

3. **パフォーマンス**:
   - 大量コンテンツの翻訳でシステム負荷が高まるリスク
   - **対策**: キャッシュの導入、非同期処理の検討

4. **セキュリティ**:
   - APIキーの漏洩リスク
   - **対策**: 暗号化、アクセス制限、監査ログ

### ロールバック手順

1. **プラグイン無効化**:
   ```bash
   php bin/wawoo plugin:unlink plugins/translation
   ```

2. **ファイル削除**:
   ```bash
   rm -rf plugins/translation
   rm -f config.translation.php
   ```

3. **データベースクリーンアップ**（必要に応じて）:
   ```sql
   DELETE FROM cache/settings WHERE plugin = 'translation';
   ```

4. **キャッシュクリア**:
   ```bash
   php bin/wawoo cache:clear
   ```

## 実施手順

1. **レビュー**:
   - 設計ドキュメントのレビュー
   - セキュリティレビュー
   - パフォーマンスレビュー

2. **承認**:
   - プロジェクトマネージャーによる承認
   - 運営チームによる承認

3. **実装**:
   - プラグインの実装
   - テストの実装
   - ドキュメントの作成

4. **検証**:
   - 単体テスト
   - 結合テスト
   - ストレステスト

5. **本番投入**:
   - ステージング環境での動作確認
   - 本番環境へのデプロイ
   - モニタリング

## 更新履歴

| 日付 | 内容 |
|------|------|
| 2026-10-11 | 初版作成 |