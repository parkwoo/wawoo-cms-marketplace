<?php

namespace Wawoo\Plugin\Translation;

class MultiLanguageManager
{
    // 言語設定
    private const DEFAULT_SOURCE_LANGUAGE = 'ja';
    private const DEFAULT_TARGET_LANGUAGES = ['en'];
    private const DEFAULT_AUTO_DETECT = true;
    private const DEFAULT_FALLBACK_LANGUAGE = 'en';
    
    // サポート言語（addLanguage/removeLanguage で変更できるよう static プロパティ）
    private static $supportedLanguages = [
        'ja' => ['name' => 'Japanese', 'locale' => 'ja_JP', 'flag' => '🇯🇵'],
        'en' => ['name' => 'English', 'locale' => 'en_US', 'flag' => '🇺🇸'],
        'zh' => ['name' => 'Chinese', 'locale' => 'zh_CN', 'flag' => '🇨🇳'],
        'ko' => ['name' => 'Korean', 'locale' => 'ko_KR', 'flag' => '🇰🇷'],
        'fr' => ['name' => 'French', 'locale' => 'fr_FR', 'flag' => '🇫🇷'],
        'de' => ['name' => 'German', 'locale' => 'de_DE', 'flag' => '🇩🇪'],
        'es' => ['name' => 'Spanish', 'locale' => 'es_ES', 'flag' => '🇪🇸'],
        'it' => ['name' => 'Italian', 'locale' => 'it_IT', 'flag' => '🇮🇹'],
        'pt' => ['name' => 'Portuguese', 'locale' => 'pt_BR', 'flag' => '🇵🇹'],
        'ru' => ['name' => 'Russian', 'locale' => 'ru_RU', 'flag' => '🇷🇺'],
        'ar' => ['name' => 'Arabic', 'locale' => 'ar_SA', 'flag' => '🇸🇦'],
        'hi' => ['name' => 'Hindi', 'locale' => 'hi_IN', 'flag' => '🇮🇳']
    ];

    private $config;
    private $translator;
    private $cache;
    private $languageDetector;
    private static $instance = null;

    /**
     * シングルトンインスタンスを取得
     *
     * @param array $config 設定
     * @return self
     */
    public static function getInstance(array $config = []): self
    {
        if (self::$instance === null) {
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    /**
     * コンストラクタ
     *
     * @param array $config 設定
     */
    private function __construct(array $config = [])
    {
        $this->config = array_merge([
            'source_language' => self::DEFAULT_SOURCE_LANGUAGE,
            'target_languages' => self::DEFAULT_TARGET_LANGUAGES,
            'auto_detect' => self::DEFAULT_AUTO_DETECT,
            'fallback_language' => self::DEFAULT_FALLBACK_LANGUAGE,
            'cache_dir' => 'cache/translation/languages',
            'translation_service' => 'google' // google, microsoft, deepl
        ], $config);

        $this->translator = new Translator();
        $this->cache = new CacheManager();
        $this->languageDetector = new LanguageDetector();
        
        $this->ensureCacheDirectory();
    }

    /**
     * キャッシュディレクトリを確保
     */
    private function ensureCacheDirectory(): void
    {
        if (!file_exists($this->config['cache_dir'])) {
            mkdir($this->config['cache_dir'], 0755, true);
        }
    }

    /**
     * 言語を自動検出
     *
     * @param string $text テキスト
     * @return string 検出された言語コード
     */
    public function detectLanguage(string $text): string
    {
        if (!$this->config['auto_detect']) {
            return $this->config['source_language'];
        }

        $cacheKey = 'language_detection_' . md5($text);
        $cached = $this->cache->get($cacheKey);
        
        if ($cached !== null) {
            return $cached;
        }

        $detected = $this->languageDetector->detect($text);
        $this->cache->set($cacheKey, $detected, 3600); // 1時間キャッシュ
        
        return $detected;
    }

    /**
     * テキストを翻訳
     *
     * @param string $text 翻訳するテキスト
     * @param string $targetLanguage 目的言語
     * @param string $sourceLanguage ソース言語（自動検出の場合はnull）
     * @return array 翻訳結果
     */
    public function translateText(string $text, string $targetLanguage, ?string $sourceLanguage = null): array
    {
        // ソース言語の検出
        if ($sourceLanguage === null) {
            $sourceLanguage = $this->detectLanguage($text);
        }

        // サポート言語チェック
        if (!isset(self::$supportedLanguages[$sourceLanguage])) {
            throw new \InvalidArgumentException("Unsupported source language: {$sourceLanguage}");
        }

        if (!isset(self::$supportedLanguages[$targetLanguage])) {
            throw new \InvalidArgumentException("Unsupported target language: {$targetLanguage}");
        }

        // ソース言語と目的言語が同じ場合はそのまま返す
        if ($sourceLanguage === $targetLanguage) {
            return [
                'success' => true,
                'original' => $text,
                'translated' => $text,
                'source_language' => $sourceLanguage,
                'target_language' => $targetLanguage,
                'confidence' => 1.0
            ];
        }

        // キャッシュチェック
        $cacheKey = "translation_{$sourceLanguage}_{$targetLanguage}_" . md5($text);
        $cached = $this->cache->get($cacheKey);
        
        if ($cached !== null) {
            return $cached;
        }

        // 翻訳実行
        $result = $this->translator->translate($text, [
            'source_language' => $sourceLanguage,
            'target_language' => $targetLanguage,
            'service' => $this->config['translation_service']
        ]);

        // キャッシュに保存
        $this->cache->set($cacheKey, $result, 86400); // 24時間キャッシュ

        return $result;
    }

    /**
     * コンテンツを多言語で翻訳
     *
     * @param array $content コンテンツ配列
     * @param array $targetLanguages 目的言語配列
     * @return array 翻訳されたコンテンツ
     */
    public function translateContent(array $content, array $targetLanguages = null): array
    {
        if ($targetLanguages === null) {
            $targetLanguages = $this->config['target_languages'];
        }

        $translatedContent = [];
        $sourceLanguage = $content['language'] ?? $this->config['source_language'];

        // 各言語への翻訳
        foreach ($targetLanguages as $targetLanguage) {
            if ($sourceLanguage === $targetLanguage) {
                continue; // 同じ言語はスキップ
            }

            $translatedContent[$targetLanguage] = $this->translateContentToLanguage($content, $targetLanguage, $sourceLanguage);
        }

        return $translatedContent;
    }

    /**
     * 特定の言語にコンテンツを翻訳
     *
     * @param array $content コンテンツ
     * @param string $targetLanguage 目的言語
     * @param string $sourceLanguage ソース言語
     * @return array 翻訳されたコンテンツ
     */
    private function translateContentToLanguage(array $content, string $targetLanguage, string $sourceLanguage): array
    {
        $translated = $content;
        $translated['language'] = $targetLanguage;
        $translated['translated_at'] = time();
        $translated['translator'] = $this->config['translation_service'];

        // タイトルの翻訳
        if (isset($content['title'])) {
            $translation = $this->translateText($content['title'], $targetLanguage, $sourceLanguage);
            $translated['title'] = $translation['translated'];
            $translated['title_confidence'] = $translation['confidence'];
        }

        // 説明の翻訳
        if (isset($content['description'])) {
            $translation = $this->translateText($content['description'], $targetLanguage, $sourceLanguage);
            $translated['description'] = $translation['translated'];
            $translated['description_confidence'] = $translation['confidence'];
        }

        // 本文の翻訳
        if (isset($content['content'])) {
            $translation = $this->translateText($content['content'], $targetLanguage, $sourceLanguage);
            $translated['content'] = $translation['translated'];
            $translated['content_confidence'] = $translation['confidence'];
        }

        // タグの翻訳
        if (isset($content['tags'])) {
            $translated['tags'] = $this->translateTags($content['tags'], $targetLanguage, $sourceLanguage);
        }

        return $translated;
    }

    /**
     * タグを翻訳
     *
     * @param array $tags タグ配列
     * @param string $targetLanguage 目的言語
     * @param string $sourceLanguage ソース言語
     * @return array 翻訳されたタグ
     */
    private function translateTags(array $tags, string $targetLanguage, string $sourceLanguage): array
    {
        $translatedTags = [];
        
        foreach ($tags as $tag) {
            $translation = $this->translateText($tag, $targetLanguage, $sourceLanguage);
            $translatedTags[] = [
                'original' => $tag,
                'translated' => $translation['translated'],
                'confidence' => $translation['confidence']
            ];
        }
        
        return $translatedTags;
    }

    /**
     * 言語切り替えインターフェースのHTMLを生成
     *
     * @param string $currentLanguage 現在の言語
     * @param string $currentUrl 現在のURL
     * @return string HTML
     */
    public function generateLanguageSwitcher(string $currentLanguage, string $currentUrl): string
    {
        $html = '<div class="language-switcher">';
        $html .= '<select id="language-select" onchange="switchLanguage(this.value)">';
        
        foreach (self::$supportedLanguages as $code => $info) {
            $selected = $code === $currentLanguage ? 'selected' : '';
            $html .= sprintf(
                '<option value="%s" %s>%s %s</option>',
                $code,
                $selected,
                $info['flag'],
                $info['name']
            );
        }
        
        $html .= '</select>';
        $html .= '<script>';
        $html .= 'function switchLanguage(lang) {';
        $html .= '    const url = new URL(window.location.href);';
        $html .= '    url.searchParams.set("lang", lang);';
        $html .= '    window.location.href = url.toString();';
        $html .= '}';
        $html .= '</script>';
        $html .= '</div>';
        
        return $html;
    }

    /**
     * 言語間の一貫性をチェック
     *
     * @param array $contents コンテンツ配列（言語ごとの配列）
     * @return array チェック結果
     */
    public function checkLanguageConsistency(array $contents): array
    {
        $issues = [];
        $languages = array_keys($contents);
        
        // 基本情報の一貫性チェック
        $baseInfo = ['title', 'slug', 'date', 'author'];
        
        foreach ($baseInfo as $field) {
            $values = [];
            foreach ($languages as $lang) {
                if (isset($contents[$lang][$field])) {
                    $values[$lang] = $contents[$lang][$field];
                }
            }
            
            // 一貫性チェック
            if (count(array_unique($values)) > 1) {
                $issues[] = [
                    'type' => 'inconsistent_field',
                    'field' => $field,
                    'values' => $values,
                    'severity' => 'warning'
                ];
            }
        }
        
        // リンクの一貫性チェック
        foreach ($languages as $lang) {
            if (isset($contents[$lang]['content'])) {
                $links = $this->extractLinks($contents[$lang]['content']);
                foreach ($links as $link) {
                    if (!$this->isValidLanguageLink($link, $languages)) {
                        $issues[] = [
                            'type' => 'broken_link',
                            'link' => $link,
                            'language' => $lang,
                            'severity' => 'error'
                        ];
                    }
                }
            }
        }
        
        return [
            'issues' => $issues,
            'total_issues' => count($issues),
            'languages_checked' => $languages,
            'consistency_score' => $this->calculateConsistencyScore($issues)
        ];
    }

    /**
     * リンクを抽出
     *
     * @param string $content コンテンツ
     * @return array リンク配列
     */
    private function extractLinks(string $content): array
    {
        $links = [];
        $pattern = '/\[(.*?)\]\((.*?)\)/';
        preg_match_all($pattern, $content, $matches);
        
        for ($i = 0; $i < count($matches[0]); $i++) {
            $links[] = $matches[2][$i];
        }
        
        return $links;
    }

    /**
     * 言語リンクが有効かチェック
     *
     * @param string $link リンク
     * @param array $languages サポート言語
     * @return bool 有効な場合はtrue
     */
    private function isValidLanguageLink(string $link, array $languages): bool
    {
        // 現在のドメイン内のリンクチェック
        if (strpos($link, '://') === false) {
            // 相対パスチェック
            return true;
        }
        
        // 外部リンクチェック
        if (filter_var($link, FILTER_VALIDATE_URL)) {
            return true;
        }
        
        return false;
    }

    /**
     * 一貫性スコアを計算
     *
     * @param array $issues 問題点
     * @return float スコア（0-100）
     */
    private function calculateConsistencyScore(array $issues): float
    {
        if (empty($issues)) {
            return 100.0;
        }
        
        $errorCount = count(array_filter($issues, function($issue) {
            return $issue['severity'] === 'error';
        }));
        
        $warningCount = count(array_filter($issues, function($issue) {
            return $issue['severity'] === 'warning';
        }));
        
        $totalIssues = count($issues);
        $score = 100 - (($errorCount * 10) + ($warningCount * 5));
        
        return max(0, min(100, $score));
    }

    /**
     * 言語ごとのコンテンツを管理
     *
     * @param string $slug スラッグ
     * @param array $contents コンテンツ配列
     * @return bool 保存結果
     */
    public function saveMultiLanguageContent(string $slug, array $contents): bool
    {
        $dataDir = $this->config['cache_dir'] . '/contents';
        if (!file_exists($dataDir)) {
            mkdir($dataDir, 0755, true);
        }
        
        $file = $dataDir . '/' . $slug . '.json';
        $data = [
            'slug' => $slug,
            'contents' => $contents,
            'created_at' => time(),
            'updated_at' => time()
        ];
        
        return file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false;
    }

    /**
     * 言語ごとのコンテンツを取得
     *
     * @param string $slug スラッグ
     * @param string $language 言語
     * @return array|null コンテンツ
     */
    public function getMultiLanguageContent(string $slug, string $language = null): ?array
    {
        $file = $this->config['cache_dir'] . '/contents/' . $slug . '.json';
        
        if (!file_exists($file)) {
            return null;
        }
        
        $content = file_get_contents($file);
        $data = json_decode($content, true);
        
        if ($language === null) {
            return $data;
        }
        
        return $data['contents'][$language] ?? null;
    }

    /**
     * サポート言語リストを取得
     *
     * @return array サポート言語
     */
    public function getSupportedLanguages(): array
    {
        return self::$supportedLanguages;
    }

    /**
     * 言語情報を取得
     *
     * @param string $languageCode 言語コード
     * @return array|null 言語情報
     */
    public function getLanguageInfo(string $languageCode): ?array
    {
        return self::$supportedLanguages[$languageCode] ?? null;
    }

    /**
     * 言語を追加
     *
     * @param string $languageCode 言語コード
     * @param string $languageName 言語名
     * @param string $locale ロケール
     * @param string $flag フラグ
     * @return bool 追加結果
     */
    public function addLanguage(string $languageCode, string $languageName, string $locale, string $flag): bool
    {
        if (isset(self::$supportedLanguages[$languageCode])) {
            return false;
        }
        
        self::$supportedLanguages[$languageCode] = [
            'name' => $languageName,
            'locale' => $locale,
            'flag' => $flag
        ];
        
        return true;
    }

    /**
     * 言語を削除
     *
     * @param string $languageCode 言語コード
     * @return bool 削除結果
     */
    public function removeLanguage(string $languageCode): bool
    {
        if (!isset(self::$supportedLanguages[$languageCode])) {
            return false;
        }
        
        unset(self::$supportedLanguages[$languageCode]);
        
        // キャッシュをクリア
        $this->cache->clear();
        
        return true;
    }

    /**
     * 言語設定を更新
     *
     * @param array $config 新しい設定
     */
    public function updateConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * 設定を取得
     *
     * @return array 設定
     */
    public function getConfig(): array
    {
        return $this->config;
    }
}

/**
 * 言語検出クラス
 */
class LanguageDetector
{
    private $languageProfiles = [];
    
    public function __construct()
    {
        $this->loadLanguageProfiles();
    }
    
    /**
     * 言語を検出
     *
     * @param string $text テキスト
     * @return string 検出された言語
     */
    public function detect(string $text): string
    {
        // 単純な文字頻度分析
        $charFreq = $this->getCharacterFrequency($text);
        
        // 各言語との類似度を計算
        $similarities = [];
        foreach ($this->languageProfiles as $lang => $profile) {
            $similarity = $this->calculateSimilarity($charFreq, $profile);
            $similarities[$lang] = $similarity;
        }
        
        // 最も類似度の高い言語を返す
        arsort($similarities);
        return key($similarities);
    }
    
    /**
     * 言語プロファイルをロード
     */
    private function loadLanguageProfiles(): void
    {
        // 簡易的な言語プロファイル
        $this->languageProfiles = [
            'ja' => [
                'あ' => 0.05, 'い' => 0.05, 'う' => 0.05, 'え' => 0.05, 'お' => 0.05,
                'か' => 0.03, 'き' => 0.03, 'く' => 0.03, 'け' => 0.03, 'こ' => 0.03,
                'が' => 0.02, 'ぎ' => 0.02, 'ぐ' => 0.02, 'げ' => 0.02, 'ご' => 0.02,
                'さ' => 0.03, 'し' => 0.03, 'す' => 0.03, 'せ' => 0.03, 'そ' => 0.03,
                'ざ' => 0.02, 'じ' => 0.02, 'ず' => 0.02, 'ぜ' => 0.02, 'ぞ' => 0.02,
                'た' => 0.03, 'ち' => 0.03, 'つ' => 0.03, 'て' => 0.03, 'と' => 0.03,
                'だ' => 0.02, 'ぢ' => 0.02, 'づ' => 0.02, 'で' => 0.02, 'ど' => 0.02,
                'な' => 0.03, 'に' => 0.03, 'ぬ' => 0.03, 'ね' => 0.03, 'の' => 0.03,
                'は' => 0.03, 'ひ' => 0.03, 'ふ' => 0.03, 'へ' => 0.03, 'ほ' => 0.03,
                'ば' => 0.02, 'び' => 0.02, 'ぶ' => 0.02, 'べ' => 0.02, 'ぼ' => 0.02,
                'ま' => 0.03, 'み' => 0.03, 'む' => 0.03, 'め' => 0.03, 'も' => 0.03,
                'や' => 0.02, 'ゆ' => 0.02, 'よ' => 0.02,
                'ら' => 0.03, 'り' => 0.03, 'る' => 0.03, 'れ' => 0.03, 'ろ' => 0.03,
                'わ' => 0.02, 'を' => 0.02, 'ん' => 0.02,
                'ー' => 0.02, '、' => 0.03, '。' => 0.03
            ],
            'en' => [
                'e' => 0.127, 't' => 0.091, 'a' => 0.082, 'o' => 0.075, 'i' => 0.070,
                'n' => 0.067, 's' => 0.063, 'h' => 0.061, 'r' => 0.060, 'd' => 0.043,
                'l' => 0.040, 'c' => 0.028, 'u' => 0.028, 'm' => 0.024, 'w' => 0.023,
                'f' => 0.022, 'g' => 0.020, 'y' => 0.019, 'p' => 0.019, 'b' => 0.015,
                'v' => 0.009, 'k' => 0.007, 'j' => 0.001, 'x' => 0.001, 'q' => 0.001,
                'z' => 0.001
            ]
        ];
    }
    
    /**
     * 文字頻度を取得
     *
     * @param string $text テキスト
     * @return array 文字頻度
     */
    private function getCharacterFrequency(string $text): array
    {
        $freq = [];
        $total = mb_strlen($text);
        
        for ($i = 0; $i < $total; $i++) {
            $char = mb_substr($text, $i, 1);
            $freq[$char] = ($freq[$char] ?? 0) + 1;
        }
        
        // 正規化
        foreach ($freq as $char => $count) {
            $freq[$char] = $count / $total;
        }
        
        return $freq;
    }
    
    /**
     * 類似度を計算
     *
     * @param array $freq1 文字頻度1
     * @param array $freq2 文字頻度2
     * @return float 類似度
     */
    private function calculateSimilarity(array $freq1, array $freq2): float
    {
        $similarity = 0;
        $allChars = array_unique(array_merge(array_keys($freq1), array_keys($freq2)));
        
        foreach ($allChars as $char) {
            $f1 = $freq1[$char] ?? 0;
            $f2 = $freq2[$char] ?? 0;
            $similarity += min($f1, $f2);
        }
        
        return $similarity;
    }
}