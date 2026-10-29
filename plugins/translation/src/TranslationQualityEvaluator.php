<?php

namespace Wawoo\Plugin\Translation;

class TranslationQualityEvaluator
{
    // 品質評価設定
    private const DEFAULT_MIN_CONFIDENCE = 0.8;
    private const DEFAULT_MIN_LENGTH_RATIO = 0.7;
    private const DEFAULT_MAX_LENGTH_RATIO = 1.5;
    private const DEFAULT_PUNCTUATION_RATIO = 0.9;
    private const DEFAULT_SPELLING_THRESHOLD = 0.95;
    
    // 品質レベル
    public const QUALITY_EXCELLENT = 5;
    public const QUALITY_GOOD = 4;
    public const QUALITY_FAIR = 3;
    public const QUALITY_POOR = 2;
    public const QUALITY_UNACCEPTABLE = 1;

    private $config;
    private $cache;
    private $terminology;
    private $spellingChecker;
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
            'min_confidence' => self::DEFAULT_MIN_CONFIDENCE,
            'min_length_ratio' => self::DEFAULT_MIN_LENGTH_RATIO,
            'max_length_ratio' => self::DEFAULT_MAX_LENGTH_RATIO,
            'punctuation_ratio' => self::DEFAULT_PUNCTUATION_RATIO,
            'spelling_threshold' => self::DEFAULT_SPELLING_THRESHOLD,
            'cache_dir' => 'cache/translation/quality',
            'enable_spelling_check' => true,
            'enable_grammar_check' => true,
            'enable_style_check' => true,
            'enable_consistency_check' => true
        ], $config);

        $this->cache = new CacheManager();
        $this->terminology = TerminologyDictionary::getInstance();
        $this->spellingChecker = new SpellingChecker();
        $this->ensureDirectories();
    }

    /**
     * ディレクトリを確保
     */
    private function ensureDirectories(): void
    {
        if (!file_exists($this->config['cache_dir'])) {
            mkdir($this->config['cache_dir'], 0755, true);
        }
    }

    /**
     * 翻訳品質を評価
     *
     * @param string $originalText 元のテキスト
     * @param string $translatedText 翻訳されたテキスト
     * @param array $options オプション
     * @return array 評価結果
     */
    public function evaluate(string $originalText, string $translatedText, array $options = []): array
    {
        $options = array_merge([
            'source_language' => 'ja',
            'target_language' => 'en',
            'confidence' => null,
            'context' => '',
            'domain' => '',
            'check_terminology' => true,
            'check_spelling' => $this->config['enable_spelling_check'],
            'check_grammar' => $this->config['enable_grammar_check'],
            'check_style' => $this->config['enable_style_check'],
            'check_consistency' => $this->config['enable_consistency_check']
        ], $options);

        // キャッシュチェック
        $cacheKey = 'quality_evaluation_' . md5($originalText . $translatedText . json_encode($options));
        $cached = $this->cache->get($cacheKey);
        
        if ($cached !== null) {
            return $cached;
        }

        // 基本的な品質チェック
        $basicChecks = $this->performBasicChecks($originalText, $translatedText, $options);
        
        // 専門用語チェック
        $terminologyCheck = [];
        if ($options['check_terminology']) {
            $terminologyCheck = $this->checkTerminology($originalText, $translatedText, $options);
        }
        
        // 拼写チェック
        $spellingCheck = [];
        if ($options['check_spelling']) {
            $spellingCheck = $this->checkSpelling($translatedText, $options['target_language']);
        }
        
        // 文法チェック
        $grammarCheck = [];
        if ($options['check_grammar']) {
            $grammarCheck = $this->checkGrammar($translatedText, $options['target_language']);
        }
        
        // スタイルチェック
        $styleCheck = [];
        if ($options['check_style']) {
            $styleCheck = $this->checkStyle($translatedText, $options);
        }
        
        // 一貫性チェック
        $consistencyCheck = [];
        if ($options['check_consistency']) {
            $consistencyCheck = $this->checkConsistency($originalText, $translatedText, $options);
        }
        
        // 総合評価
        $overallScore = $this->calculateOverallScore([
            'basic' => $basicChecks,
            'terminology' => $terminologyCheck,
            'spelling' => $spellingCheck,
            'grammar' => $grammarCheck,
            'style' => $styleCheck,
            'consistency' => $consistencyCheck
        ]);
        
        // 品質レベルの決定
        $qualityLevel = $this->determineQualityLevel($overallScore);
        
        // 評価結果の構築
        $result = [
            'overall_score' => $overallScore,
            'quality_level' => $qualityLevel,
            'quality_text' => $this->getQualityText($qualityLevel),
            'checks' => [
                'basic' => $basicChecks,
                'terminology' => $terminologyCheck,
                'spelling' => $spellingCheck,
                'grammar' => $grammarCheck,
                'style' => $styleCheck,
                'consistency' => $consistencyCheck
            ],
            'issues' => $this->collectIssues([
                $basicChecks,
                $terminologyCheck,
                $spellingCheck,
                $grammarCheck,
                $styleCheck,
                $consistencyCheck
            ]),
            'recommendations' => $this->generateRecommendations([
                $basicChecks,
                $terminologyCheck,
                $spellingCheck,
                $grammarCheck,
                $styleCheck,
                $consistencyCheck
            ]),
            'metadata' => [
                'source_language' => $options['source_language'],
                'target_language' => $options['target_language'],
                'domain' => $options['domain'],
                'context' => $options['context'],
                'confidence' => $options['confidence'],
                'evaluated_at' => time()
            ]
        ];
        
        // キャッシュに保存
        $this->cache->set($cacheKey, $result, 3600); // 1時間キャッシュ
        
        return $result;
    }

    /**
     * 基本的な品質チェックを実行
     *
     * @param string $originalText 元のテキスト
     * @param string $translatedText 翻訳されたテキスト
     * @param array $options オプション
     * @return array チェック結果
     */
    private function performBasicChecks(string $originalText, string $translatedText, array $options): array
    {
        $checks = [];
        $score = 0;
        $maxScore = 0;
        
        // 空チェック
        $maxScore += 10;
        if (empty($translatedText)) {
            $checks[] = [
                'type' => 'empty_translation',
                'severity' => 'critical',
                'message' => '翻訳結果が空です',
                'score' => 0
            ];
        } else {
            $score += 10;
        }
        
        // 長さ比チェック
        $maxScore += 20;
        $lengthRatio = strlen($translatedText) / strlen($originalText);
        
        if ($lengthRatio < $this->config['min_length_ratio']) {
            $checks[] = [
                'type' => 'length_too_short',
                'severity' => 'warning',
                'message' => sprintf('翻訳が短すぎます（長さ比: %.2f）', $lengthRatio),
                'score' => 10
            ];
            $score += 10;
        } elseif ($lengthRatio > $this->config['max_length_ratio']) {
            $checks[] = [
                'type' => 'length_too_long',
                'severity' => 'warning',
                'message' => sprintf('翻訳が長すぎます（長さ比: %.2f）', $lengthRatio),
                'score' => 10
            ];
            $score += 10;
        } else {
            $score += 20;
        }
        
        // 句読点比チェック
        $maxScore += 15;
        $originalPunctuation = $this->countPunctuation($originalText);
        $translatedPunctuation = $this->countPunctuation($translatedText);
        
        if ($originalPunctuation > 0) {
            $punctuationRatio = $translatedPunctuation / $originalPunctuation;
            
            if ($punctuationRatio < $this->config['punctuation_ratio']) {
                $checks[] = [
                    'type' => 'punctuation_missing',
                    'severity' => 'warning',
                    'message' => sprintf('句読点が不足しています（比: %.2f）', $punctuationRatio),
                    'score' => 10
                ];
                $score += 10;
            } else {
                $score += 15;
            }
        } else {
            $score += 15;
        }
        
        // 信頼度チェック
        $maxScore += 15;
        if ($options['confidence'] !== null) {
            if ($options['confidence'] < $this->config['min_confidence']) {
                $checks[] = [
                    'type' => 'low_confidence',
                    'severity' => 'warning',
                    'message' => sprintf('翻訳信頼度が低いです（%.2f）', $options['confidence']),
                    'score' => 8
                ];
                $score += 8;
            } else {
                $score += 15;
            }
        } else {
            $score += 10;
        }
        
        // HTML構造チェック
        $maxScore += 10;
        if ($this->hasHtmlStructure($originalText) && !$this->hasHtmlStructure($translatedText)) {
            $checks[] = [
                'type' => 'html_structure_missing',
                'severity' => 'warning',
                'message' => 'HTML構造が保持されていません',
                'score' => 5
            ];
            $score += 5;
        } else {
            $score += 10;
        }
        
        return [
            'score' => $score,
            'max_score' => $maxScore,
            'percentage' => $maxScore > 0 ? ($score / $maxScore) * 100 : 0,
            'issues' => $checks
        ];
    }

    /**
     * 専門用語チェック
     *
     * @param string $originalText 元のテキスト
     * @param string $translatedText 翻訳されたテキスト
     * @param array $options オプション
     * @return array チェック結果
     */
    private function checkTerminology(string $originalText, string $translatedText, array $options): array
    {
        $issues = [];
        $score = 0;
        $maxScore = 100;
        
        // 専門用語の適用チェック
        $terminologyResult = $this->terminology->applyTerminology($originalText, $options['source_language'], $options['target_language']);
        
        if (!empty($terminologyResult['applied_terms'])) {
            // 専門用語が適用された場合
            $score += 50;
            
            foreach ($terminologyResult['applied_terms'] as $term) {
                $issues[] = [
                    'type' => 'terminology_applied',
                    'severity' => 'info',
                    'message' => sprintf('専門用語が適用されました: %s -> %s', $term['term'], $term['translation']),
                    'score' => 10
                ];
            }
        } else {
            $score += 30;
        }
        
        // 専門用語の不使用チェック
        $dictionary = $this->terminology->getDictionary($options['source_language'], $options['target_language']);
        
        foreach ($dictionary as $term) {
            if ($term['approved'] && strpos($originalText, $term['term']) !== false) {
                if (strpos($translatedText, $term['translation']) === false) {
                    $issues[] = [
                        'type' => 'terminology_missing',
                        'severity' => 'error',
                        'message' => sprintf('専門用語が使用されていません: %s -> %s', $term['term'], $term['translation']),
                        'score' => 0
                    ];
                    $score -= 10;
                }
            }
        }
        
        return [
            'score' => max(0, $score),
            'max_score' => $maxScore,
            'percentage' => $maxScore > 0 ? ($score / $maxScore) * 100 : 0,
            'issues' => $issues
        ];
    }

    /**
     * 拼写チェック
     *
     * @param string $text テキスト
     * @param string $language 言語
     * @return array チェック結果
     */
    private function checkSpelling(string $text, string $language): array
    {
        $issues = [];
        $score = 100;
        $maxScore = 100;
        
        $spellingErrors = $this->spellingChecker->check($text, $language);
        
        foreach ($spellingErrors as $error) {
            $issues[] = [
                'type' => 'spelling_error',
                'severity' => 'error',
                'message' => sprintf('拼写错误: %s (提案: %s)', $error['word'], implode(', ', $error['suggestions'])),
                'score' => 10
            ];
            $score -= 10;
        }
        
        return [
            'score' => max(0, $score),
            'max_score' => $maxScore,
            'percentage' => $maxScore > 0 ? ($score / $maxScore) * 100 : 0,
            'issues' => $issues
        ];
    }

    /**
     * 文法チェック
     *
     * @param string $text テキスト
     * @param string $language 言語
     * @return array チェック結果
     */
    private function checkGrammar(string $text, string $language): array
    {
        $issues = [];
        $score = 100;
        $maxScore = 100;
        
        // 簡易的な文法チェック
        $grammarErrors = $this->detectGrammarErrors($text, $language);
        
        foreach ($grammarErrors as $error) {
            $issues[] = [
                'type' => 'grammar_error',
                'severity' => 'error',
                'message' => $error['message'],
                'score' => 15
            ];
            $score -= 15;
        }
        
        return [
            'score' => max(0, $score),
            'max_score' => $maxScore,
            'percentage' => $maxScore > 0 ? ($score / $maxScore) * 100 : 0,
            'issues' => $issues
        ];
    }

    /**
     * スタイルチェック
     *
     * @param string $text テキスト
     * @param array $options オプション
     * @return array チェック結果
     */
    private function checkStyle(string $text, array $options): array
    {
        $issues = [];
        $score = 100;
        $maxScore = 100;
        
        // スタイルガイドの取得
        $styleGuide = $this->terminology->getStyleGuide();
        
        // トーンチェック
        if ($styleGuide['tone'] === 'professional') {
            if ($this->hasInformalLanguage($text)) {
                $issues[] = [
                    'type' => 'informal_language',
                    'severity' => 'warning',
                    'message' => '非公式な表現が含まれています',
                    'score' => 10
                ];
                $score -= 10;
            }
        }
        
        // 形式レベルチェック
        if ($styleGuide['formality'] === 'formal') {
            if ($this->hasContractions($text)) {
                $issues[] = [
                    'type' => 'contractions',
                    'severity' => 'warning',
                    'message' => '縮約形が使用されています',
                    'score' => 5
                ];
                $score -= 5;
            }
        }
        
        // 禁止用語チェック
        foreach ($styleGuide['forbidden_terms'] as $term) {
            if (strpos($text, $term) !== false) {
                $issues[] = [
                    'type' => 'forbidden_term',
                    'severity' => 'error',
                    'message' => sprintf('禁止用語が使用されています: %s', $term),
                    'score' => 20
                ];
                $score -= 20;
            }
        }
        
        // 推奨用語チェック
        $preferredTermsUsed = 0;
        foreach ($styleGuide['preferred_terms'] as $term) {
            if (strpos($text, $term) !== false) {
                $preferredTermsUsed++;
            }
        }
        
        if ($preferredTermsUsed === 0 && !empty($styleGuide['preferred_terms'])) {
            $issues[] = [
                'type' => 'no_preferred_terms',
                'severity' => 'info',
                'message' => '推奨用語が使用されていません',
                'score' => 5
            ];
            $score -= 5;
        }
        
        return [
            'score' => max(0, $score),
            'max_score' => $maxScore,
            'percentage' => $maxScore > 0 ? ($score / $maxScore) * 100 : 0,
            'issues' => $issues
        ];
    }

    /**
     * 一貫性チェック
     *
     * @param string $originalText 元のテキスト
     * @param string $translatedText 翻訳されたテキスト
     * @param array $options オプション
     * @return array チェック結果
     */
    private function checkConsistency(string $originalText, string $translatedText, array $options): array
    {
        $issues = [];
        $score = 100;
        $maxScore = 100;
        
        // ターミノロジー一貫性チェック
        $dictionary = $this->terminology->getDictionary($options['source_language'], $options['target_language']);
        
        foreach ($dictionary as $term) {
            if ($term['approved']) {
                $originalCount = substr_count($originalText, $term['term']);
                $translatedCount = substr_count($translatedText, $term['translation']);
                
                if ($originalCount > 0 && $translatedCount === 0) {
                    $issues[] = [
                        'type' => 'terminology_inconsistency',
                        'severity' => 'error',
                        'message' => sprintf('専門用語が一貫して翻訳されていません: %s', $term['term']),
                        'score' => 15
                    ];
                    $score -= 15;
                }
            }
        }
        
        // 文章構造の一貫性チェック
        $originalSentences = $this->splitIntoSentences($originalText);
        $translatedSentences = $this->splitIntoSentences($translatedText);
        
        if (count($originalSentences) !== count($translatedSentences)) {
            $issues[] = [
                'type' => 'sentence_structure_inconsistency',
                'severity' => 'warning',
                'message' => '文の構造が元の文章と一致しません',
                'score' => 10
            ];
            $score -= 10;
        }
        
        // 大文字小文字の一貫性チェック
        if ($this->hasInconsistentCapitalization($translatedText)) {
            $issues[] = [
                'type' => 'capitalization_inconsistency',
                'severity' => 'warning',
                'message' => '大文字小文字の一貫性がありません',
                'score' => 5
            ];
            $score -= 5;
        }
        
        return [
            'score' => max(0, $score),
            'max_score' => $maxScore,
            'percentage' => $maxScore > 0 ? ($score / $maxScore) * 100 : 0,
            'issues' => $issues
        ];
    }

    /**
     * 総合スコアを計算
     *
     * @param array $results 各チェック結果
     * @return float 総合スコア
     */
    private function calculateOverallScore(array $results): float
    {
        $totalScore = 0;
        $totalMaxScore = 0;
        
        foreach ($results as $category => $result) {
            if (isset($result['score']) && isset($result['max_score'])) {
                $totalScore += $result['score'];
                $totalMaxScore += $result['max_score'];
            }
        }
        
        return $totalMaxScore > 0 ? ($totalScore / $totalMaxScore) * 100 : 0;
    }

    /**
     * 品質レベルを決定
     *
     * @param float $score スコア
     * @return int 品質レベル
     */
    private function determineQualityLevel(float $score): int
    {
        if ($score >= 90) return self::QUALITY_EXCELLENT;
        if ($score >= 75) return self::QUALITY_GOOD;
        if ($score >= 60) return self::QUALITY_FAIR;
        if ($score >= 40) return self::QUALITY_POOR;
        return self::QUALITY_UNACCEPTABLE;
    }

    /**
     * 品質レベルのテキストを取得
     *
     * @param int $level 品質レベル
     * @return string テキスト
     */
    private function getQualityText(int $level): string
    {
        switch ($level) {
            case self::QUALITY_EXCELLENT:
                return '優秀';
            case self::QUALITY_GOOD:
                return '良好';
            case self::QUALITY_FAIR:
                return '普通';
            case self::QUALITY_POOR:
                return '不良';
            case self::QUALITY_UNACCEPTABLE:
                return ' unacceptable';
            default:
                return '不明';
        }
    }

    /**
     * 問題点を収集
     *
     * @param array $results チェック結果
     * @return array 問題点
     */
    private function collectIssues(array $results): array
    {
        $issues = [];
        
        foreach ($results as $result) {
            if (isset($result['issues'])) {
                $issues = array_merge($issues, $result['issues']);
            }
        }
        
        return $issues;
    }

    /**
     * 推奨事項を生成
     *
     * @param array $results チェック結果
     * @return array 推奨事項
     */
    private function generateRecommendations(array $results): array
    {
        $recommendations = [];
        
        foreach ($results as $category => $result) {
            if (isset($result['issues']) && !empty($result['issues'])) {
                foreach ($result['issues'] as $issue) {
                    if ($issue['severity'] === 'error' || $issue['severity'] === 'warning') {
                        $recommendations[] = [
                            'category' => $category,
                            'message' => $issue['message'],
                            'priority' => $issue['severity']
                        ];
                    }
                }
            }
        }
        
        return $recommendations;
    }

    /**
     * 句読点を数える
     *
     * @param string $text テキスト
     * @return int 句読点数
     */
    private function countPunctuation(string $text): int
    {
        return preg_match_all('/[.,;:!?、。；：！？]/', $text);
    }

    /**
     * HTML構造を持つかチェック
     *
     * @param string $text テキスト
     * @return bool 持つ場合はtrue
     */
    private function hasHtmlStructure(string $text): bool
    {
        return preg_match('/<[a-zA-Z][^>]*>/', $text) === 1;
    }

    /**
     * 文法エラーを検出
     *
     * @param string $text テキスト
     * @param string $language 言語
     * @return array エラー配列
     */
    private function detectGrammarErrors(string $text, string $language): array
    {
        $errors = [];
        
        // 簡易的な文法チェック
        if ($language === 'en') {
            // 二重スペースチェック
            if (preg_match('/\s{2,}/', $text)) {
                $errors[] = [
                    'message' => '二重スペースが含まれています'
                ];
            }
            
            // 句読点の後にスペースがないチェック
            if (preg_match('/[.,;:!?][^ ]/', $text)) {
                $errors[] = [
                    'message' => '句読点の後にスペースがありません'
                ];
            }
        }
        
        return $errors;
    }

    /**
     * 非公式な言語をチェック
     *
     * @param string $text テキスト
     * @return bool 含む場合はtrue
     */
    private function hasInformalLanguage(string $text): bool
    {
        $informalWords = ['lol', 'omg', 'btw', 'imho', 'fyi', 'tbh'];
        
        foreach ($informalWords as $word) {
            if (stripos($text, $word) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * 縮約形をチェック
     *
     * @param string $text テキスト
     * @return bool 含む場合はtrue
     */
    private function hasContractions(string $text): bool
    {
        $contractions = ["can't", "won't", "don't", "doesn't", "didn't", "isn't", "aren't", "wasn't", "weren't", "haven't", "hasn't", "hadn't", "couldn't", "wouldn't", "shouldn't", "mightn't", "mustn't"];
        
        foreach ($contractions as $contraction) {
            if (stripos($text, $contraction) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * 文に分割
     *
     * @param string $text テキスト
     * @return array 文の配列
     */
    private function splitIntoSentences(string $text): array
    {
        return preg_split('/[.!?]+/', $text);
    }

    /**
     * 大文字小文字の一貫性をチェック
     *
     * @param string $text テキスト
     * @return bool 不一貫な場合はtrue
     */
    private function hasInconsistentCapitalization(string $text): bool
    {
        // 簡易的なチェック：文頭以外の大文字
        $sentences = $this->splitIntoSentences($text);
        
        foreach ($sentences as $sentence) {
            $trimmed = trim($sentence);
            if (!empty($trimmed) && strlen($trimmed) > 1) {
                if (ctype_upper($trimmed[0]) && ctype_upper($trimmed[1])) {
                    // 文頭が大文字で、次の文字も大文字の場合は問題あり
                    return true;
                }
            }
        }
        
        return false;
    }

    /**
     * 設定を更新
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
 * 拼写チェッククラス
 */
class SpellingChecker
{
    private $dictionary = [];
    
    public function __construct()
    {
        $this->loadDictionary();
    }
    
    /**
     * 拼写をチェック
     *
     * @param string $text テキスト
     * @param string $language 言語
     * @return array エラー配列
     */
    public function check(string $text, string $language): array
    {
        $errors = [];
        $words = $this->extractWords($text);
        
        foreach ($words as $word) {
            if (!$this->isValidWord($word, $language)) {
                $suggestions = $this->getSuggestions($word, $language);
                $errors[] = [
                    'word' => $word,
                    'suggestions' => $suggestions
                ];
            }
        }
        
        return $errors;
    }
    
    /**
     * 単語を抽出
     *
     * @param string $text テキスト
     * @return array 単語配列
     */
    private function extractWords(string $text): array
    {
        return preg_split('/\s+/', preg_replace('/[^a-zA-Z0-9\']/', ' ', $text));
    }
    
    /**
     * 単語が有効かチェック
     *
     * @param string $word 単語
     * @param string $language 言語
     * @return bool 有効な場合はtrue
     */
    private function isValidWord(string $word, string $language): bool
    {
        // 簡易的なチェック：3文字以上の単語のみチェック
        if (strlen($word) < 3) {
            return true;
        }
        
        return isset($this->dictionary[$language][$word]);
    }
    
    /**
     * 修正候補を取得
     *
     * @param string $word 単語
     * @param string $language 言語
     * @return array 候補配列
     */
    private function getSuggestions(string $word, string $language): array
    {
        // 簡易的な修正候補生成
        $suggestions = [];
        
        // 文字の置換
        $chars = str_split($word);
        for ($i = 0; $i < count($chars); $i++) {
            for ($j = 0; $j < 26; $j++) {
                $newWord = $chars;
                $newWord[$i] = chr(97 + $j); // a-z
                $newWordStr = implode('', $newWord);
                
                if ($this->isValidWord($newWordStr, $language)) {
                    $suggestions[] = $newWordStr;
                }
            }
        }
        
        return array_unique($suggestions);
    }
    
    /**
     * 辞書をロード
     */
    private function loadDictionary(): void
    {
        // 簡易的な辞書
        $this->dictionary = [
            'en' => [
                'the' => true,
                'be' => true,
                'to' => true,
                'of' => true,
                'and' => true,
                'a' => true,
                'in' => true,
                'that' => true,
                'have' => true,
                'I' => true,
                'it' => true,
                'for' => true,
                'not' => true,
                'on' => true,
                'with' => true,
                'he' => true,
                'as' => true,
                'you' => true,
                'do' => true,
                'at' => true,
                'this' => true,
                'but' => true,
                'his' => true,
                'by' => true,
                'from' => true,
                'they' => true,
                'we' => true,
                'say' => true,
                'her' => true,
                'she' => true,
                'or' => true,
                'an' => true,
                'will' => true,
                'my' => true,
                'one' => true,
                'all' => true,
                'would' => true,
                'there' => true,
                'their' => true,
                'what' => true,
                'so' => true,
                'up' => true,
                'out' => true,
                'if' => true,
                'about' => true,
                'who' => true,
                'get' => true,
                'which' => true,
                'go' => true,
                'me' => true,
                'when' => true,
                'make' => true,
                'can' => true,
                'like' => true,
                'time' => true,
                'no' => true,
                'just' => true,
                'him' => true,
                'know' => true,
                'take' => true,
                'people' => true,
                'into' => true,
                'year' => true,
                'your' => true,
                'good' => true,
                'some' => true,
                'could' => true,
                'them' => true,
                'see' => true,
                'other' => true,
                'than' => true,
                'then' => true,
                'now' => true,
                'look' => true,
                'only' => true,
                'come' => true,
                'its' => true,
                'over' => true,
                'think' => true,
                'also' => true,
                'back' => true,
                'after' => true,
                'use' => true,
                'two' => true,
                'how' => true,
                'our' => true,
                'work' => true,
                'first' => true,
                'well' => true,
                'way' => true,
                'even' => true,
                'new' => true,
                'want' => true,
                'because' => true,
                'any' => true,
                'these' => true,
                'give' => true,
                'day' => true,
                'most' => true,
                'us' => true
            ]
        ];
    }
}