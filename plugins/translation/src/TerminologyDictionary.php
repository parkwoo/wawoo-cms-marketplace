<?php

namespace Wawoo\Plugin\Translation;

class TerminologyDictionary
{
    // 辞書設定
    private const DEFAULT_CACHE_TTL = 86400; // 24時間
    private const DEFAULT_MAX_ENTRIES = 10000;
    private const DEFAULT_PRIORITY = 'normal';
    
    // 優先度レベル
    public const PRIORITY_LOW = 'low';
    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_CRITICAL = 'critical';

    private $config;
    private $cache;
    private $storage;
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
            'cache_ttl' => self::DEFAULT_CACHE_TTL,
            'max_entries' => self::DEFAULT_MAX_ENTRIES,
            'priority' => self::DEFAULT_PRIORITY,
            'cache_dir' => 'cache/translation/terminology',
            'dictionary_file' => 'cache/translation/terminology/dictionary.json',
            'style_guide_file' => 'cache/translation/terminology/style_guide.json'
        ], $config);

        $this->cache = new CacheManager();
        $this->storage = new TerminologyStorage();
        $this->ensureDirectories();
    }

    /**
     * ディレクトリを確保
     */
    private function ensureDirectories(): void
    {
        $dirs = [
            $this->config['cache_dir'],
            dirname($this->config['dictionary_file']),
            dirname($this->config['style_guide_file'])
        ];
        
        foreach ($dirs as $dir) {
            if (!file_exists($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }

    /**
     * 専門用語を追加
     *
     * @param string $term 用語
     * @param string $translation 翻訳
     * @param string $sourceLanguage ソース言語
     * @param string $targetLanguage 目的言語
     * @param array $options オプション
     * @return bool 追加結果
     */
    public function addTerm(string $term, string $translation, string $sourceLanguage, string $targetLanguage, array $options = []): bool
    {
        $options = array_merge([
            'priority' => self::PRIORITY_NORMAL,
            'context' => '',
            'domain' => '',
            'notes' => '',
            'created_by' => 'system',
            'approved' => false,
            'reviewed' => false,
            'reviewed_by' => null,
            'reviewed_at' => null
        ], $options);

        $termData = [
            'id' => $this->generateTermId($term, $sourceLanguage, $targetLanguage),
            'term' => $term,
            'translation' => $translation,
            'source_language' => $sourceLanguage,
            'target_language' => $targetLanguage,
            'priority' => $options['priority'],
            'context' => $options['context'],
            'domain' => $options['domain'],
            'notes' => $options['notes'],
            'created_by' => $options['created_by'],
            'created_at' => time(),
            'updated_at' => time(),
            'approved' => $options['approved'],
            'reviewed' => $options['reviewed'],
            'reviewed_by' => $options['reviewed_by'],
            'reviewed_at' => $options['reviewed_at'],
            'usage_count' => 0
        ];

        // 保存
        $result = $this->storage->saveTerm($termData);
        
        if ($result) {
            // キャッシュを更新
            $this->cache->set('dictionary_' . $sourceLanguage . '_' . $targetLanguage, $this->getDictionary($sourceLanguage, $targetLanguage));
            
            // ログに記録
            $this->logTermOperation('add', $termData);
        }
        
        return $result;
    }

    /**
     * 専門用語を更新
     *
     * @param string $termId 用語ID
     * @param array $updates 更新データ
     * @return bool 更新結果
     */
    public function updateTerm(string $termId, array $updates): bool
    {
        $term = $this->storage->getTerm($termId);
        
        if (!$term) {
            return false;
        }

        // 更新データのマージ
        $updatedTerm = array_merge($term, $updates);
        $updatedTerm['updated_at'] = time();

        // 保存
        $result = $this->storage->updateTerm($termId, $updatedTerm);
        
        if ($result) {
            // キャッシュを更新
            $this->cache->set('dictionary_' . $term['source_language'] . '_' . $term['target_language'], $this->getDictionary($term['source_language'], $term['target_language']));
            
            // ログに記録
            $this->logTermOperation('update', $updatedTerm);
        }
        
        return $result;
    }

    /**
     * 専門用語を削除
     *
     * @param string $termId 用語ID
     * @return bool 削除結果
     */
    public function deleteTerm(string $termId): bool
    {
        $term = $this->storage->getTerm($termId);
        
        if (!$term) {
            return false;
        }

        $result = $this->storage->deleteTerm($termId);
        
        if ($result) {
            // キャッシュを更新
            $this->cache->set('dictionary_' . $term['source_language'] . '_' . $term['target_language'], $this->getDictionary($term['source_language'], $term['target_language']));
            
            // ログに記録
            $this->logTermOperation('delete', $term);
        }
        
        return $result;
    }

    /**
     * 用語を検索
     *
     * @param string $query 検索クエリ
     * @param array $filters フィルタ
     * @return array 検索結果
     */
    public function searchTerms(string $query, array $filters = []): array
    {
        $filters = array_merge([
            'source_language' => null,
            'target_language' => null,
            'domain' => null,
            'priority' => null,
            'approved' => null,
            'limit' => 100,
            'offset' => 0
        ], $filters);

        // キャッシュチェック
        $cacheKey = 'search_terms_' . md5($query . json_encode($filters));
        $cached = $this->cache->get($cacheKey);
        
        if ($cached !== null) {
            return $cached;
        }

        // 検索実行
        $results = $this->storage->searchTerms($query, $filters);
        
        // キャッシュに保存
        $this->cache->set($cacheKey, $results, $this->config['cache_ttl']);
        
        return $results;
    }

    /**
     * 用語を取得
     *
     * @param string $termId 用語ID
     * @return array|null 用語データ
     */
    public function getTerm(string $termId): ?array
    {
        return $this->storage->getTerm($termId);
    }

    /**
     * 辞書を取得
     *
     * @param string $sourceLanguage ソース言語
     * @param string $targetLanguage 目的言語
     * @return array 辞書データ
     */
    public function getDictionary(string $sourceLanguage, string $targetLanguage): array
    {
        $cacheKey = 'dictionary_' . $sourceLanguage . '_' . $targetLanguage;
        $cached = $this->cache->get($cacheKey);
        
        if ($cached !== null) {
            return $cached;
        }

        $dictionary = $this->storage->getDictionary($sourceLanguage, $targetLanguage);
        
        // キャッシュに保存
        $this->cache->set($cacheKey, $dictionary, $this->config['cache_ttl']);
        
        return $dictionary;
    }

    /**
     * 用語を使用
     *
     * @param string $termId 用語ID
     * @return bool 更新結果
     */
    public function useTerm(string $termId): bool
    {
        $term = $this->storage->getTerm($termId);
        
        if (!$term) {
            return false;
        }

        $term['usage_count']++;
        $term['last_used_at'] = time();

        return $this->storage->updateTerm($termId, $term);
    }

    /**
     * 用語を承認
     *
     * @param string $termId 用語ID
     * @param string $reviewedBy 承認者
     * @return bool 承認結果
     */
    public function approveTerm(string $termId, string $reviewedBy): bool
    {
        return $this->updateTerm($termId, [
            'approved' => true,
            'reviewed' => true,
            'reviewed_by' => $reviewedBy,
            'reviewed_at' => time()
        ]);
    }

    /**
     * 用語を非承認
     *
     * @param string $termId 用語ID
     * @param string $reviewedBy 非承認者
     * @param string $reason 理由
     * @return bool 非承認結果
     */
    public function disapproveTerm(string $termId, string $reviewedBy, string $reason): bool
    {
        return $this->updateTerm($termId, [
            'approved' => false,
            'reviewed' => true,
            'reviewed_by' => $reviewedBy,
            'reviewed_at' => time(),
            'disapprove_reason' => $reason
        ]);
    }

    /**
     * バッチインポート
     *
     * @param array $terms 用語データ配列
     * @param array $options オプション
     * @return array インポート結果
     */
    public function batchImport(array $terms, array $options = []): array
    {
        $options = array_merge([
            'skip_duplicates' => true,
            'update_existing' => true,
            'priority' => self::PRIORITY_NORMAL,
            'created_by' => 'system'
        ], $options);

        $results = [
            'success' => 0,
            'failed' => 0,
            'duplicates' => 0,
            'errors' => []
        ];

        foreach ($terms as $term) {
            try {
                $termId = $this->generateTermId($term['term'], $term['source_language'], $term['target_language']);
                
                // 重複チェック
                if ($options['skip_duplicates'] && $this->storage->getTerm($termId)) {
                    $results['duplicates']++;
                    continue;
                }

                // 用語の追加
                $result = $this->addTerm(
                    $term['term'],
                    $term['translation'],
                    $term['source_language'],
                    $term['target_language'],
                    array_merge($options, $term)
                );

                if ($result) {
                    $results['success']++;
                } else {
                    $results['failed']++;
                    $results['errors'][] = "Failed to import term: {$term['term']}";
                }
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = "Error importing term {$term['term']}: " . $e->getMessage();
            }
        }

        return $results;
    }

    /**
     * バッチエクスポート
     *
     * @param array $filters フィルタ
     * @return array エクスポートデータ
     */
    public function batchExport(array $filters = []): array
    {
        $filters = array_merge([
            'source_language' => null,
            'target_language' => null,
            'domain' => null,
            'priority' => null,
            'approved' => null,
            'format' => 'json'
        ], $filters);

        $terms = $this->storage->searchTerms('', $filters);
        
        switch ($filters['format']) {
            case 'csv':
                return $this->exportToCSV($terms);
            case 'excel':
                return $this->exportToExcel($terms);
            default:
                return $terms;
        }
    }

    /**
     * CSV形式でエクスポート
     *
     * @param array $terms 用語データ
     * @return string CSVデータ
     */
    private function exportToCSV(array $terms): string
    {
        $output = fopen('php://memory', 'r+');
        
        // ヘッダー
        fputcsv($output, ['ID', 'Term', 'Translation', 'Source Language', 'Target Language', 'Priority', 'Domain', 'Context', 'Notes', 'Approved', 'Usage Count', 'Created At', 'Updated At']);
        
        // データ
        foreach ($terms as $term) {
            fputcsv($output, [
                $term['id'],
                $term['term'],
                $term['translation'],
                $term['source_language'],
                $term['target_language'],
                $term['priority'],
                $term['domain'],
                $term['context'],
                $term['notes'],
                $term['approved'] ? 'Yes' : 'No',
                $term['usage_count'],
                date('Y-m-d H:i:s', $term['created_at']),
                date('Y-m-d H:i:s', $term['updated_at'])
            ]);
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return $csv;
    }

    /**
     * Excel形式でエクスポート
     *
     * @param array $terms 用語データ
     * @return array Excelデータ
     */
    private function exportToExcel(array $terms): array
    {
        $excel = [];
        
        // ヘッダー
        $excel[] = ['ID', 'Term', 'Translation', 'Source Language', 'Target Language', 'Priority', 'Domain', 'Context', 'Notes', 'Approved', 'Usage Count', 'Created At', 'Updated At'];
        
        // データ
        foreach ($terms as $term) {
            $excel[] = [
                $term['id'],
                $term['term'],
                $term['translation'],
                $term['source_language'],
                $term['target_language'],
                $term['priority'],
                $term['domain'],
                $term['context'],
                $term['notes'],
                $term['approved'] ? 'Yes' : 'No',
                $term['usage_count'],
                date('Y-m-d H:i:s', $term['created_at']),
                date('Y-m-d H:i:s', $term['updated_at'])
            ];
        }
        
        return $excel;
    }

    /**
     * 用語を翻訳時に適用
     *
     * @param string $text テキスト
     * @param string $sourceLanguage ソース言語
     * @param string $targetLanguage 目的言語
     * @return array 翻訳結果
     */
    public function applyTerminology(string $text, string $sourceLanguage, string $targetLanguage): array
    {
        $dictionary = $this->getDictionary($sourceLanguage, $targetLanguage);
        $patterns = [];
        $replacements = [];
        
        // 辞書からパターンを作成
        foreach ($dictionary as $term) {
            if ($term['approved']) {
                $patterns[] = '/\b' . preg_quote($term['term'], '/') . '\b/i';
                $replacements[] = $term['translation'];
            }
        }
        
        // パターンがなければそのまま返す
        if (empty($patterns)) {
            return [
                'original' => $text,
                'translated' => $text,
                'applied_terms' => []
            ];
        }
        
        // 翻訳の適用
        $translated = $text;
        $appliedTerms = [];
        
        foreach ($patterns as $index => $pattern) {
            if (preg_match_all($pattern, $text, $matches)) {
                $term = $dictionary[$index];
                $translated = preg_replace($pattern, $replacements[$index], $translated);
                $appliedTerms[] = [
                    'term' => $term['term'],
                    'translation' => $term['translation'],
                    'usage_count' => count($matches[0])
                ];
                
                // 使用回数を更新
                $this->useTerm($term['id']);
            }
        }
        
        return [
            'original' => $text,
            'translated' => $translated,
            'applied_terms' => $appliedTerms
        ];
    }

    /**
     * スタイルガイドを取得
     *
     * @return array スタイルガイド
     */
    public function getStyleGuide(): array
    {
        $file = $this->config['style_guide_file'];
        
        if (file_exists($file)) {
            $content = file_get_contents($file);
            return json_decode($content, true) ?: [];
        }
        
        return [
            'tone' => 'professional',
            'formality' => 'formal',
            'style_notes' => [],
            'examples' => [],
            'forbidden_terms' => [],
            'preferred_terms' => []
        ];
    }

    /**
     * スタイルガイドを更新
     *
     * @param array $styleGuide スタイルガイド
     * @return bool 更新結果
     */
    public function updateStyleGuide(array $styleGuide): bool
    {
        $file = $this->config['style_guide_file'];
        $content = json_encode($styleGuide, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        return file_put_contents($file, $content) !== false;
    }

    /**
     * 用語統計を取得
     *
     * @param array $filters フィルタ
     * @return array 統計データ
     */
    public function getStatistics(array $filters = []): array
    {
        $filters = array_merge([
            'source_language' => null,
            'target_language' => null,
            'domain' => null,
            'priority' => null,
            'approved' => null
        ], $filters);

        $stats = $this->storage->getStatistics($filters);
        
        return [
            'total_terms' => $stats['total_terms'],
            'approved_terms' => $stats['approved_terms'],
            'pending_terms' => $stats['pending_terms'],
            'rejected_terms' => $stats['rejected_terms'],
            'top_domains' => $stats['top_domains'],
            'top_priorities' => $stats['top_priorities'],
            'most_used_terms' => $stats['most_used_terms'],
            'recently_added' => $stats['recently_added'],
            'recently_updated' => $stats['recently_updated']
        ];
    }

    /**
     * 用語IDを生成
     *
     * @param string $term 用語
     * @param string $sourceLanguage ソース言語
     * @param string $targetLanguage 目的言語
     * @return string 用語ID
     */
    private function generateTermId(string $term, string $sourceLanguage, string $targetLanguage): string
    {
        return md5($term . '_' . $sourceLanguage . '_' . $targetLanguage);
    }

    /**
     * 用語操作をログに記録
     *
     * @param string $operation 操作
     * @param array $term 用語データ
     */
    private function logTermOperation(string $operation, array $term): void
    {
        $logger = new Logger();
        $logger->info('Terminology operation', [
            'operation' => $operation,
            'term_id' => $term['id'],
            'term' => $term['term'],
            'source_language' => $term['source_language'],
            'target_language' => $term['target_language'],
            'priority' => $term['priority'],
            'created_by' => $term['created_by']
        ]);
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
 * 専門用語ストレージクラス
 */
class TerminologyStorage
{
    private $dictionaryFile = 'cache/translation/terminology/dictionary.json';
    private $indexFile = 'cache/translation/terminology/index.json';
    
    /**
     * 用語を保存
     *
     * @param array $termData 用語データ
     * @return bool 保存結果
     */
    public function saveTerm(array $termData): bool
    {
        $dictionary = $this->loadDictionary();
        
        // 重複チェック
        if (isset($dictionary[$termData['id']])) {
            return false;
        }
        
        $dictionary[$termData['id']] = $termData;
        $this->saveDictionary($dictionary);
        
        // インデックスを更新
        $this->updateIndex($termData);
        
        return true;
    }
    
    /**
     * 用語を更新
     *
     * @param string $termId 用語ID
     * @param array $termData 用語データ
     * @return bool 更新結果
     */
    public function updateTerm(string $termId, array $termData): bool
    {
        $dictionary = $this->loadDictionary();
        
        if (!isset($dictionary[$termId])) {
            return false;
        }
        
        $dictionary[$termId] = $termData;
        $this->saveDictionary($dictionary);
        
        // インデックスを更新
        $this->updateIndex($termData);
        
        return true;
    }
    
    /**
     * 用語を削除
     *
     * @param string $termId 用語ID
     * @return bool 削除結果
     */
    public function deleteTerm(string $termId): bool
    {
        $dictionary = $this->loadDictionary();
        
        if (!isset($dictionary[$termId])) {
            return false;
        }
        
        unset($dictionary[$termId]);
        $this->saveDictionary($dictionary);
        
        // インデックスから削除
        $this->removeFromIndex($termId);
        
        return true;
    }
    
    /**
     * 用語を取得
     *
     * @param string $termId 用語ID
     * @return array|null 用語データ
     */
    public function getTerm(string $termId): ?array
    {
        $dictionary = $this->loadDictionary();
        return $dictionary[$termId] ?? null;
    }
    
    /**
     * 辞書を取得
     *
     * @param string $sourceLanguage ソース言語
     * @param string $targetLanguage 目的言語
     * @return array 辞書データ
     */
    public function getDictionary(string $sourceLanguage, string $targetLanguage): array
    {
        $dictionary = $this->loadDictionary();
        
        return array_filter($dictionary, function($term) use ($sourceLanguage, $targetLanguage) {
            return $term['source_language'] === $sourceLanguage && $term['target_language'] === $targetLanguage;
        });
    }
    
    /**
     * 用語を検索
     *
     * @param string $query 検索クエリ
     * @param array $filters フィルタ
     * @return array 検索結果
     */
    public function searchTerms(string $query, array $filters): array
    {
        $dictionary = $this->loadDictionary();
        $results = [];
        
        foreach ($dictionary as $term) {
            // フィルタリング
            if ($filters['source_language'] && $term['source_language'] !== $filters['source_language']) {
                continue;
            }
            
            if ($filters['target_language'] && $term['target_language'] !== $filters['target_language']) {
                continue;
            }
            
            if ($filters['domain'] && $term['domain'] !== $filters['domain']) {
                continue;
            }
            
            if ($filters['priority'] && $term['priority'] !== $filters['priority']) {
                continue;
            }
            
            if ($filters['approved'] !== null && $term['approved'] !== $filters['approved']) {
                continue;
            }
            
            // 検索クエリ
            if (!empty($query)) {
                $searchText = strtolower($term['term'] . ' ' . $term['translation'] . ' ' . ($term['context'] ?? ''));
                if (strpos($searchText, strtolower($query)) === false) {
                    continue;
                }
            }
            
            $results[] = $term;
        }
        
        // ソート
        usort($results, function($a, $b) {
            return $b['created_at'] - $a['created_at'];
        });
        
        // ページネーション
        $limit = $filters['limit'] ?? 100;
        $offset = $filters['offset'] ?? 0;
        
        return array_slice($results, $offset, $limit);
    }
    
    /**
     * 統計を取得
     *
     * @param array $filters フィルタ
     * @return array 統計データ
     */
    public function getStatistics(array $filters): array
    {
        $dictionary = $this->loadDictionary();
        $filtered = $dictionary;
        
        // フィルタリング
        foreach ($filters as $key => $value) {
            if ($value !== null) {
                $filtered = array_filter($filtered, function($term) use ($key, $value) {
                    return $term[$key] === $value;
                });
            }
        }
        
        // 統計計算
        $stats = [
            'total_terms' => count($filtered),
            'approved_terms' => 0,
            'pending_terms' => 0,
            'rejected_terms' => 0,
            'top_domains' => [],
            'top_priorities' => [],
            'most_used_terms' => [],
            'recently_added' => [],
            'recently_updated' => []
        ];
        
        foreach ($filtered as $term) {
            // 承認状態
            if ($term['approved']) {
                $stats['approved_terms']++;
            } elseif ($term['reviewed']) {
                $stats['rejected_terms']++;
            } else {
                $stats['pending_terms']++;
            }
            
            // ドメイン
            if ($term['domain']) {
                $stats['top_domains'][$term['domain']] = ($stats['top_domains'][$term['domain']] ?? 0) + 1;
            }
            
            // 優先度
            $stats['top_priorities'][$term['priority']] = ($stats['top_priorities'][$term['priority']] ?? 0) + 1;
            
            // 使用回数
            if ($term['usage_count'] > 0) {
                $stats['most_used_terms'][] = [
                    'term' => $term['term'],
                    'usage_count' => $term['usage_count']
                ];
            }
            
            // 最近追加
            if ($term['created_at'] > time() - 7 * 86400) {
                $stats['recently_added'][] = $term;
            }
            
            // 最近更新
            if ($term['updated_at'] > time() - 7 * 86400) {
                $stats['recently_updated'][] = $term;
            }
        }
        
        // ソート
        arsort($stats['top_domains']);
        arsort($stats['top_priorities']);
        usort($stats['most_used_terms'], function($a, $b) {
            return $b['usage_count'] - $a['usage_count'];
        });
        
        return $stats;
    }
    
    /**
     * 辞書をロード
     *
     * @return array 辞書データ
     */
    private function loadDictionary(): array
    {
        if (!file_exists($this->dictionaryFile)) {
            return [];
        }
        
        $content = file_get_contents($this->dictionaryFile);
        return json_decode($content, true) ?: [];
    }
    
    /**
     * 辞書を保存
     *
     * @param array $dictionary 辞書データ
     */
    private function saveDictionary(array $dictionary): void
    {
        $content = json_encode($dictionary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        file_put_contents($this->dictionaryFile, $content);
    }
    
    /**
     * インデックスを更新
     *
     * @param array $termData 用語データ
     */
    private function updateIndex(array $termData): void
    {
        $index = $this->loadIndex();
        
        // ソース言語インデックス
        if (!isset($index['source_languages'][$termData['source_language']])) {
            $index['source_languages'][$termData['source_language']] = [];
        }
        $index['source_languages'][$termData['source_language']][] = $termData['id'];
        
        // 目的言語インデックス
        if (!isset($index['target_languages'][$termData['target_language']])) {
            $index['target_languages'][$termData['target_language']] = [];
        }
        $index['target_languages'][$termData['target_language']][] = $termData['id'];
        
        // ドメインインデックス
        if ($termData['domain']) {
            if (!isset($index['domains'][$termData['domain']])) {
                $index['domains'][$termData['domain']] = [];
            }
            $index['domains'][$termData['domain']][] = $termData['id'];
        }
        
        $this->saveIndex($index);
    }
    
    /**
     * インデックスから削除
     *
     * @param string $termId 用語ID
     */
    private function removeFromIndex(string $termId): void
    {
        $index = $this->loadIndex();
        
        // すべてのインデックスから削除
        foreach ($index as $category => $items) {
            foreach ($items as $key => $value) {
                if (is_array($value)) {
                    $index[$category][$key] = array_diff($value, [$termId]);
                }
            }
        }
        
        $this->saveIndex($index);
    }
    
    /**
     * インデックスをロード
     *
     * @return array インデックスデータ
     */
    private function loadIndex(): array
    {
        if (!file_exists($this->indexFile)) {
            return [
                'source_languages' => [],
                'target_languages' => [],
                'domains' => []
            ];
        }
        
        $content = file_get_contents($this->indexFile);
        return json_decode($content, true) ?: [
            'source_languages' => [],
            'target_languages' => [],
            'domains' => []
        ];
    }
    
    /**
     * インデックスを保存
     *
     * @param array $index インデックスデータ
     */
    private function saveIndex(array $index): void
    {
        $content = json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        file_put_contents($this->indexFile, $content);
    }
}