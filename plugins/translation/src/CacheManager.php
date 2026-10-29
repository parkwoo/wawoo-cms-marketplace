<?php

namespace Wawoo\Plugin\Translation;

class CacheManager
{
    // キャッシュ設定
    private const DEFAULT_TTL = 3600; // 1時間
    private const DEFAULT_MAX_SIZE = 1000; // 最大キャッシュエントリ数
    private const DEFAULT_GC_PROBABILITY = 1;
    private const DEFAULT_GC_DIVISOR = 100;
    private const CACHE_VERSION = '1.0';

    private $config;
    private $cacheDir;
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
            'ttl' => self::DEFAULT_TTL,
            'max_size' => self::DEFAULT_MAX_SIZE,
            'gc_probability' => self::DEFAULT_GC_PROBABILITY,
            'gc_divisor' => self::DEFAULT_GC_DIVISOR,
            'cache_dir' => 'cache/translation',
            'compression' => true,
            'serialization' => 'json'
        ], $config);

        $this->cacheDir = $this->config['cache_dir'];
        $this->ensureCacheDirectory();
        $this->initializeGarbageCollection();
    }

    /**
     * キャッシュディレクトリを確保
     */
    private function ensureCacheDirectory(): void
    {
        if (!file_exists($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    /**
     * キャッシュに保存
     *
     * @param string $key キー
     * @param mixed $value 値
     * @param int|null $ttl 有効期間（秒）
     * @return bool 保存結果
     */
    public function set(string $key, $value, ?int $ttl = null): bool
    {
        if ($ttl === null) {
            $ttl = $this->config['ttl'];
        }

        $cacheFile = $this->getCacheFile($key);
        $data = [
            'value' => $value,
            'expires' => time() + $ttl,
            'created' => time(),
            'version' => self::CACHE_VERSION
        ];

        $content = $this->serialize($data);
        if ($this->config['compression']) {
            $content = gzencode($content);
        }

        $tempFile = $cacheFile . '.tmp';
        $result = file_put_contents($tempFile, $content);
        
        if ($result === false) {
            return false;
        }

        // アトミックなファイル書き込み
        $result = rename($tempFile, $cacheFile);
        
        if ($result) {
            $this->updateCacheIndex($key, $cacheFile);
            $this->checkCacheSize();
        }

        return $result;
    }

    /**
     * キャッシュから取得
     *
     * @param string $key キー
     * @param mixed $default デフォルト値
     * @return mixed キャッシュされた値
     */
    public function get(string $key, $default = null)
    {
        $cacheFile = $this->getCacheFile($key);
        
        if (!file_exists($cacheFile)) {
            return $default;
        }

        // ファイルの存在チェック
        $fileInfo = stat($cacheFile);
        if ($fileInfo === false) {
            return $default;
        }

        // 有効期限チェック
        $expires = $this->getCacheExpiration($cacheFile);
        if ($expires !== null && $expires < time()) {
            $this->delete($key);
            return $default;
        }

        // ファイル読み込み
        $content = file_get_contents($cacheFile);
        if ($content === false) {
            return $default;
        }

        // 圧縮解除
        if ($this->config['compression']) {
            $content = gzdecode($content);
        }

        $data = $this->unserialize($content);
        
        if ($data === null || $data['version'] !== self::CACHE_VERSION) {
            $this->delete($key);
            return $default;
        }

        return $data['value'];
    }

    /**
     * キャッシュが存在するかチェック
     *
     * @param string $key キー
     * @return bool 存在する場合はtrue
     */
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * キャッシュを削除
     *
     * @param string $key キー
     * @return bool 削除結果
     */
    public function delete(string $key): bool
    {
        $cacheFile = $this->getCacheFile($key);
        
        if (!file_exists($cacheFile)) {
            return true;
        }

        $result = unlink($cacheFile);
        
        if ($result) {
            $this->removeFromCacheIndex($key);
        }

        return $result;
    }

    /**
     * キャッシュをクリア
     *
     * @return bool クリア結果
     */
    public function clear(): bool
    {
        $files = glob($this->cacheDir . '/*.cache');
        $success = true;
        
        foreach ($files as $file) {
            if (!unlink($file)) {
                $success = false;
            }
        }

        // インデックスファイルも削除
        $indexFile = $this->cacheDir . '/cache_index.json';
        if (file_exists($indexFile)) {
            unlink($indexFile);
        }

        return $success;
    }

    /**
     * キャッシュの有効期限を延長
     *
     * @param string $key キー
     * @param int $ttl 新しい有効期間
     * @return bool 延長結果
     */
    public function extend(string $key, int $ttl): bool
    {
        $value = $this->get($key);
        
        if ($value === null) {
            return false;
        }

        return $this->set($key, $value, $ttl);
    }

    /**
     * キャッシュの有効期限を取得
     *
     * @param string $key キー
     * @return int|null 有効期限、存在しない場合はnull
     */
    public function getExpiration(string $key): ?int
    {
        $cacheFile = $this->getCacheFile($key);
        
        if (!file_exists($cacheFile)) {
            return null;
        }

        return $this->getCacheExpiration($cacheFile);
    }

    /**
     * キャッシュ統計を取得
     *
     * @return array 統計情報
     */
    public function getStats(): array
    {
        $files = glob($this->cacheDir . '/*.cache');
        $totalSize = 0;
        $validEntries = 0;
        $expiredEntries = 0;
        $entries = [];

        foreach ($files as $file) {
            $fileInfo = stat($file);
            if ($fileInfo === false) {
                continue;
            }

            $size = $fileInfo['size'];
            $totalSize += $size;

            $expires = $this->getCacheExpiration($file);
            
            if ($expires !== null && $expires < time()) {
                $expiredEntries++;
            } else {
                $validEntries++;
            }

            $key = $this->getKeyFromFile($file);
            $entries[$key] = [
                'size' => $size,
                'expires' => $expires,
                'created' => $this->getCacheCreated($file)
            ];
        }

        return [
            'total_entries' => count($files),
            'valid_entries' => $validEntries,
            'expired_entries' => $expiredEntries,
            'total_size' => $totalSize,
            'average_size' => count($files) > 0 ? $totalSize / count($files) : 0,
            'entries' => $entries
        ];
    }

    /**
     * メモリキャッシュを取得
     *
     * @param string $key キー
     * @param mixed $default デフォルト値
     * @return mixed キャッシュされた値
     */
    public function memoryGet(string $key, $default = null)
    {
        static $memoryCache = [];

        if (isset($memoryCache[$key])) {
            $data = $memoryCache[$key];
            
            // 有効期限チェック
            if ($data['expires'] < time()) {
                unset($memoryCache[$key]);
                return $default;
            }
            
            return $data['value'];
        }

        // ディスクキャッシュから読み込み
        $value = $this->get($key, $default);
        
        if ($value !== $default) {
            $memoryCache[$key] = [
                'value' => $value,
                'expires' => time() + $this->config['ttl']
            ];
        }

        return $value;
    }

    /**
     * メモリキャッシュに保存
     *
     * @param string $key キー
     * @param mixed $value 値
     * @param int|null $ttl 有効期間
     * @return bool 保存結果
     */
    public function memorySet(string $key, $value, ?int $ttl = null): bool
    {
        static $memoryCache = [];

        if ($ttl === null) {
            $ttl = $this->config['ttl'];
        }

        $memoryCache[$key] = [
            'value' => $value,
            'expires' => time() + $ttl
        ];

        return $this->set($key, $value, $ttl);
    }

    /**
     * タグベースのキャッシュ操作
     *
     * @param string $key キー
     * @param array $tags タグ配列
     * @param mixed $value 値
     * @param int|null $ttl 有効期間
     * @return bool 保存結果
     */
    public function setWithTags(string $key, array $tags, $value, ?int $ttl = null): bool
    {
        if (!$this->set($key, $value, $ttl)) {
            return false;
        }

        // タグインデックスを更新
        foreach ($tags as $tag) {
            $taggedKeys = $this->getTaggedKeys($tag);
            $taggedKeys[] = $key;
            $this->setTaggedKeys($tag, array_unique($taggedKeys));
        }

        return true;
    }

    /**
     * タグでフィルタリングしたキーを取得
     *
     * @param string $tag タグ
     * @return array キーの配列
     */
    public function getKeysByTag(string $tag): array
    {
        return $this->getTaggedKeys($tag) ?? [];
    }

    /**
     * タグでフィルタリングしたキャッシュを削除
     *
     * @param string $tag タグ
     * @return bool 削除結果
     */
    public function deleteByTag(string $tag): bool
    {
        $keys = $this->getKeysByTag($tag);
        $success = true;

        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                $success = false;
            }
        }

        // タグインデックスを削除
        $this->deleteTaggedKeys($tag);

        return $success;
    }

    /**
     * パターンマッチングによるキャッシュ削除
     *
     * @param string $pattern パターン（ワイルドカード使用可能）
     * @return bool 削除結果
     */
    public function deleteByPattern(string $pattern): bool
    {
        $files = glob($this->cacheDir . '/*.cache');
        $success = true;
        
        foreach ($files as $file) {
            $key = $this->getKeyFromFile($file);
            
            if ($this->matchPattern($key, $pattern)) {
                if (!unlink($file)) {
                    $success = false;
                }
                $this->removeFromCacheIndex($key);
            }
        }

        return $success;
    }

    /**
     * キャッシュのヒット率を取得
     *
     * @return array ヒット率情報
     */
    public function getHitRate(): array
    {
        $stats = $this->getStats();
        $total = $stats['total_entries'];
        $valid = $stats['valid_entries'];
        
        return [
            'hit_rate' => $total > 0 ? ($valid / $total) * 100 : 0,
            'total_entries' => $total,
            'valid_entries' => $valid,
            'expired_entries' => $stats['expired_entries']
        ];
    }

    /**
     * キャッシュファイルのパスを取得
     *
     * @param string $key キー
     * @return string ファイルパス
     */
    private function getCacheFile(string $key): string
    {
        $hash = md5($key);
        return $this->cacheDir . '/' . $hash . '.cache';
    }

    /**
     * キャッシュの有効期限を取得
     *
     * @param string $file キャッシュファイル
     * @return int|null 有効期限
     */
    private function getCacheExpiration(string $file): ?int
    {
        $content = file_get_contents($file);
        if ($content === false) {
            return null;
        }

        if ($this->config['compression']) {
            $content = gzdecode($content);
        }

        $data = $this->unserialize($content);
        return $data['expires'] ?? null;
    }

    /**
     * キャッシュの作成時間を取得
     *
     * @param string $file キャッシュファイル
     * @return int|null 作成時間
     */
    private function getCacheCreated(string $file): ?int
    {
        $content = file_get_contents($file);
        if ($content === false) {
            return null;
        }

        if ($this->config['compression']) {
            $content = gzdecode($content);
        }

        $data = $this->unserialize($content);
        return $data['created'] ?? null;
    }

    /**
     * キーからファイルパスを取得
     *
     * @param string $file キャッシュファイル
     * @return string キー
     */
    private function getKeyFromFile(string $file): string
    {
        $filename = basename($file, '.cache');
        return $filename;
    }

    /**
     * キャッシュインデックスを更新
     *
     * @param string $key キー
     * @param string $file ファイルパス
     */
    private function updateCacheIndex(string $key, string $file): void
    {
        $indexFile = $this->cacheDir . '/cache_index.json';
        $index = [];
        
        if (file_exists($indexFile)) {
            $content = file_get_contents($indexFile);
            $index = json_decode($content, true) ?: [];
        }
        
        $index[$key] = [
            'file' => $file,
            'expires' => time() + $this->config['ttl'],
            'created' => time()
        ];
        
        file_put_contents($indexFile, json_encode($index, JSON_PRETTY_PRINT));
    }

    /**
     * キャッシュインデックスから削除
     *
     * @param string $key キー
     */
    private function removeFromCacheIndex(string $key): void
    {
        $indexFile = $this->cacheDir . '/cache_index.json';
        
        if (!file_exists($indexFile)) {
            return;
        }
        
        $content = file_get_contents($indexFile);
        $index = json_decode($content, true) ?: [];
        
        unset($index[$key]);
        
        file_put_contents($indexFile, json_encode($index, JSON_PRETTY_PRINT));
    }

    /**
     * キャッシュサイズをチェック
     */
    private function checkCacheSize(): void
    {
        $stats = $this->getStats();
        
        if ($stats['total_entries'] > $this->config['max_size']) {
            $this->cleanOldestEntries();
        }
    }

    /**
     * 最も古いエントリをクリーンアップ
     */
    private function cleanOldestEntries(): void
    {
        $files = glob($this->cacheDir . '/*.cache');
        $entries = [];
        
        foreach ($files as $file) {
            $created = $this->getCacheCreated($file);
            if ($created !== null) {
                $entries[$file] = $created;
            }
        }
        
        // 作成時間でソート
        asort($entries);
        
        // 削除するエントリ数を計算
        $toRemove = count($entries) - $this->config['max_size'];
        
        if ($toRemove > 0) {
            $filesToRemove = array_slice(array_keys($entries), 0, $toRemove);
            
            foreach ($filesToRemove as $file) {
                unlink($file);
                $key = $this->getKeyFromFile($file);
                $this->removeFromCacheIndex($key);
            }
        }
    }

    /**
     * ガベージコレクションを初期化
     */
    private function initializeGarbageCollection(): void
    {
        ini_set('session.gc_probability', $this->config['gc_probability']);
        ini_set('session.gc_divisor', $this->config['gc_divisor']);
    }

    /**
     * タグ付きキーを取得
     *
     * @param string $tag タグ
     * @return array キーの配列
     */
    private function getTaggedKeys(string $tag): ?array
    {
        $indexFile = $this->cacheDir . '/tag_index.json';
        
        if (!file_exists($indexFile)) {
            return null;
        }
        
        $content = file_get_contents($indexFile);
        $index = json_decode($content, true) ?: [];
        
        return $index[$tag] ?? null;
    }

    /**
     * タグ付きキーを保存
     *
     * @param string $tag タグ
     * @param array $keys キーの配列
     */
    private function setTaggedKeys(string $tag, array $keys): void
    {
        $indexFile = $this->cacheDir . '/tag_index.json';
        $index = [];
        
        if (file_exists($indexFile)) {
            $content = file_get_contents($indexFile);
            $index = json_decode($content, true) ?: [];
        }
        
        $index[$tag] = $keys;
        
        file_put_contents($indexFile, json_encode($index, JSON_PRETTY_PRINT));
    }

    /**
     * タグ付きキーを削除
     *
     * @param string $tag タグ
     */
    private function deleteTaggedKeys(string $tag): void
    {
        $indexFile = $this->cacheDir . '/tag_index.json';
        
        if (!file_exists($indexFile)) {
            return;
        }
        
        $content = file_get_contents($indexFile);
        $index = json_decode($content, true) ?: [];
        
        unset($index[$tag]);
        
        file_put_contents($indexFile, json_encode($index, JSON_PRETTY_PRINT));
    }

    /**
     * パターンマッチング
     *
     * @param string $string 文字列
     * @param string $pattern パターン
     * @return bool マッチした場合はtrue
     */
    private function matchPattern(string $string, string $pattern): bool
    {
        $pattern = preg_quote($pattern, '/');
        $pattern = str_replace('\*', '.*', $pattern);
        $pattern = '/^' . $pattern . '$/';
        
        return preg_match($pattern, $string) === 1;
    }

    /**
     * シリアライズ
     *
     * @param mixed $data データ
     * @return string シリアライズされた文字列
     */
    private function serialize($data): string
    {
        switch ($this->config['serialization']) {
            case 'json':
                return json_encode($data, JSON_UNESCAPED_UNICODE);
            case 'php':
                return serialize($data);
            default:
                return json_encode($data, JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * アンシリアライズ
     *
     * @param string $data シリアライズされた文字列
     * @return mixed アンシリアライズされたデータ
     */
    private function unserialize(string $data)
    {
        switch ($this->config['serialization']) {
            case 'json':
                return json_decode($data, true);
            case 'php':
                return unserialize($data);
            default:
                return json_decode($data, true);
        }
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