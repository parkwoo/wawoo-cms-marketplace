<?php

namespace Wawoo\Plugin\Translation;

class PerformanceOptimizer
{
    // パフォーマンス設定
    private const DEFAULT_MEMORY_LIMIT = '256M';
    private const DEFAULT_MAX_CONCURRENT_REQUESTS = 5;
    private const DEFAULT_TIMEOUT = 30;
    private const DEFAULT_BATCH_SIZE = 10;
    private const DEFAULT_STREAM_CHUNK_SIZE = 8192;

    private $config;
    private $memoryMonitor;
    private $processPool;
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
            'memory_limit' => self::DEFAULT_MEMORY_LIMIT,
            'max_concurrent_requests' => self::DEFAULT_MAX_CONCURRENT_REQUESTS,
            'timeout' => self::DEFAULT_TIMEOUT,
            'batch_size' => self::DEFAULT_BATCH_SIZE,
            'stream_chunk_size' => self::DEFAULT_STREAM_CHUNK_SIZE
        ], $config);

        $this->memoryMonitor = new MemoryMonitor();
        $this->processPool = new ProcessPool($this->config['max_concurrent_requests']);
        
        // メモリ制限の設定
        $this->setMemoryLimit();
    }

    /**
     * メモリ制限を設定
     */
    private function setMemoryLimit(): void
    {
        $currentLimit = ini_get('memory_limit');
        
        if ($this->bytesToNumber($currentLimit) < $this->bytesToNumber($this->config['memory_limit'])) {
            ini_set('memory_limit', $this->config['memory_limit']);
        }
    }

    /**
     * バッチ処理を実行
     *
     * @param array $items 処理アイテム
     * @param callable $processor プロセッサ
     * @param array $options オプション
     * @return array 処理結果
     */
    public function processBatch(array $items, callable $processor, array $options = []): array
    {
        $options = array_merge([
            'batch_size' => $this->config['batch_size'],
            'delay' => 0,
            'memory_check' => true,
            'timeout' => $this->config['timeout']
        ], $options);

        $results = [];
        $batches = array_chunk($items, $options['batch_size']);
        
        foreach ($batches as $batchIndex => $batch) {
            // メモリチェック
            if ($options['memory_check']) {
                $this->memoryMonitor->checkMemoryUsage();
            }
            
            // バッチ処理
            $batchResults = $this->processBatchItems($batch, $processor, $options);
            $results = array_merge($results, $batchResults);
            
            // 遅延処理
            if ($options['delay'] > 0) {
                usleep($options['delay'] * 1000);
            }
        }
        
        return $results;
    }

    /**
     * バッチアイテムを処理
     *
     * @param array $items アイテム
     * @param callable $processor プロセッサ
     * @param array $options オプション
     * @return array 処理結果
     */
    private function processBatchItems(array $items, callable $processor, array $options): array
    {
        $results = [];
        $concurrentResults = [];
        
        // 並列処理の準備
        $promises = [];
        
        foreach ($items as $index => $item) {
            $promise = $this->processPool->execute(function() use ($processor, $item, $options) {
                try {
                    return [
                        'success' => true,
                        'result' => $processor($item),
                        'memory' => memory_get_usage(true)
                    ];
                } catch (\Exception $e) {
                    return [
                        'success' => false,
                        'error' => $e->getMessage(),
                        'memory' => memory_get_usage(true)
                    ];
                }
            });
            
            $promises[$index] = $promise;
        }
        
        // 結果の収集
        foreach ($promises as $index => $promise) {
            $result = $promise->wait();
            $concurrentResults[$index] = $result;
            
            if ($result['success']) {
                $results[$index] = $result['result'];
            } else {
                $results[$index] = null;
            }
        }
        
        return $results;
    }

    /**
     * ストリーミング処理を実行
     *
     * @param string $inputFile 入力ファイル
     * @param string $outputFile 出力ファイル
     * @param callable $processor プロセッサ
     * @param array $options オプション
     * @return bool 処理結果
     */
    public function processStream(string $inputFile, string $outputFile, callable $processor, array $options = []): bool
    {
        $options = array_merge([
            'chunk_size' => $this->config['stream_chunk_size'],
            'memory_check' => true,
            'progress_callback' => null
        ], $options);

        if (!file_exists($inputFile)) {
            throw new \RuntimeException("入力ファイルが存在しません: {$inputFile}");
        }

        $inputHandle = fopen($inputFile, 'r');
        $outputHandle = fopen($outputFile, 'w');
        
        if ($inputHandle === false || $outputHandle === false) {
            throw new \RuntimeException("ファイルのオープンに失敗しました");
        }

        $chunkIndex = 0;
        $totalSize = filesize($inputFile);
        $processedSize = 0;
        
        while (!feof($inputHandle)) {
            // メモリチェック
            if ($options['memory_check']) {
                $this->memoryMonitor->checkMemoryUsage();
            }
            
            // チャンクの読み込み
            $chunk = fread($inputHandle, $options['chunk_size']);
            if ($chunk === false) {
                break;
            }
            
            // チャンクの処理
            $processedChunk = $processor($chunk, $chunkIndex);
            
            // 結果の書き込み
            if (fwrite($outputHandle, $processedChunk) === false) {
                fclose($inputHandle);
                fclose($outputHandle);
                return false;
            }
            
            // プログレスコールバック
            $processedSize += strlen($chunk);
            if ($options['progress_callback']) {
                $progress = ($processedSize / $totalSize) * 100;
                call_user_func($options['progress_callback'], $progress, $chunkIndex);
            }
            
            $chunkIndex++;
        }
        
        fclose($inputHandle);
        fclose($outputHandle);
        
        return true;
    }

    /**
     * メモリ使用量を最適化
     */
    public function optimizeMemoryUsage(): void
    {
        // 大きな変数の解放
        $this->clearLargeVariables();
        
        // ガベージコレクションの強制実行
        gc_collect_cycles();
        
        // メモリ使用量のモニタリング
        $this->memoryMonitor->logMemoryUsage();
    }

    /**
     * 大きな変数をクリア
     */
    private function clearLargeVariables(): void
    {
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = $this->bytesToNumber(ini_get('memory_limit'));
        
        // メモリ使用量が制限の80%を超えた場合
        if ($memoryUsage > $memoryLimit * 0.8) {
            // キャッシュのクリア
            $cache = CacheManager::getInstance();
            $cache->clear();
            
            // セッションデータのクリーンアップ
            if (isset($_SESSION)) {
                $_SESSION = array_filter($_SESSION, function($key) {
                    return !in_array($key, ['translation_session', 'user_data'], true);
                }, ARRAY_FILTER_USE_KEY);
            }
        }
    }

    /**
     * リソース使用量を監視
     *
     * @return array リソース使用状況
     */
    public function monitorResourceUsage(): array
    {
        return [
            'memory' => [
                'usage' => memory_get_usage(true),
                'peak' => memory_get_peak_usage(true),
                'limit' => $this->bytesToNumber(ini_get('memory_limit')),
                'percentage' => (memory_get_usage(true) / $this->bytesToNumber(ini_get('memory_limit'))) * 100
            ],
            'cpu' => $this->getCpuUsage(),
            'time' => [
                'current' => time(),
                'execution' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
                'max_execution_time' => ini_get('max_execution_time')
            ],
            'files' => [
                'open' => $this->countOpenFiles(),
                'max' => $this->getMaxOpenFiles()
            ]
        ];
    }

    /**
     * CPU使用率を取得
     *
     * @return float CPU使用率
     */
    private function getCpuUsage(): float
    {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return $load[0]; // 1分間の負荷
        }
        
        return 0.0;
    }

    /**
     * 開いているファイル数を取得
     *
     * @return int ファイル数
     */
    private function countOpenFiles(): int
    {
        $files = glob('/proc/*/fd/*');
        return count($files);
    }

    /**
     * 最大ファイルディスクリプタ数を取得
     *
     * @return int 最大数
     */
    private function getMaxOpenFiles(): int
    {
        $limit = ini_get('max_file_uploads');
        return $limit > 0 ? $limit : 1024;
    }

    /**
     * バックグラウンドジョブをスケジュール
     *
     * @param string $jobId ジョブID
     * @param callable $job ジョブ
     * @param array $options オプション
     * @return bool スケジュール結果
     */
    public function scheduleBackgroundJob(string $jobId, callable $job, array $options = []): bool
    {
        $options = array_merge([
            'priority' => 'normal',
            'retry_count' => 3,
            'delay' => 0
        ], $options);

        $jobData = [
            'id' => $jobId,
            'job' => $job,
            'options' => $options,
            'status' => 'pending',
            'created_at' => time(),
            'started_at' => null,
            'completed_at' => null,
            'error' => null
        ];

        // ジョブを保存
        $jobQueue = new JobQueue();
        $jobQueue->addJob($jobData);

        // 遅延実行
        if ($options['delay'] > 0) {
            sleep($options['delay']);
        }

        // バックグラウンドで実行
        $this->executeBackgroundJob($jobData);

        return true;
    }

    /**
     * バックグラウンドジョブを実行
     *
     * @param array $jobData ジョブデータ
     */
    private function executeBackgroundJob(array $jobData): void
    {
        $jobQueue = new JobQueue();
        
        try {
            // ジョブの開始
            $jobData['status'] = 'running';
            $jobData['started_at'] = time();
            $jobQueue->updateJob($jobData['id'], $jobData);
            
            // ジョブの実行
            $result = call_user_func($jobData['job']);
            
            // ジョブの完了
            $jobData['status'] = 'completed';
            $jobData['completed_at'] = time();
            $jobData['result'] = $result;
            $jobQueue->updateJob($jobData['id'], $jobData);
            
        } catch (\Exception $e) {
            // ジョブの失敗
            $jobData['status'] = 'failed';
            $jobData['error'] = $e->getMessage();
            $jobQueue->updateJob($jobData['id'], $jobData);
            
            // リトライ
            if ($jobData['options']['retry_count'] > 0) {
                $jobData['options']['retry_count']--;
                $this->scheduleBackgroundJob($jobData['id'], $jobData['job'], $jobData['options']);
            }
        }
    }

    /**
     * パフォーマンステストを実行
     *
     * @param callable $testFunction テスト関数
     * @param int $iterations 反復回数
     * @return array テスト結果
     */
    public function runPerformanceTest(callable $testFunction, int $iterations = 100): array
    {
        $results = [];
        $times = [];
        $memoryUsages = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // メモリ使用量の記録
            $startMemory = memory_get_usage(true);
            
            // 実行時間の計測
            $startTime = microtime(true);
            $testFunction();
            $endTime = microtime(true);
            
            // 結果の記録
            $executionTime = $endTime - $startTime;
            $memoryUsage = memory_get_usage(true) - $startMemory;
            
            $times[] = $executionTime;
            $memoryUsages[] = $memoryUsage;
        }
        
        // 統計計算
        $stats = [
            'iterations' => $iterations,
            'average_time' => array_sum($times) / count($times),
            'min_time' => min($times),
            'max_time' => max($times),
            'total_time' => array_sum($times),
            'average_memory' => array_sum($memoryUsages) / count($memoryUsages),
            'peak_memory' => max($memoryUsages),
            'total_memory' => array_sum($memoryUsages)
        ];
        
        return $stats;
    }

    /**
     * バイナリデータを数値に変換
     *
     * @param string $value バイナリ値
     * @return int 数値
     */
    private function bytesToNumber(string $value): int
    {
        $value = trim($value);
        $unit = strtolower(substr($value, -1));
        $number = (int)$value;
        
        switch ($unit) {
            case 'g':
                $number *= 1024;
            case 'm':
                $number *= 1024;
            case 'k':
                $number *= 1024;
                break;
        }
        
        return $number;
    }

    /**
     * 設定を更新
     *
     * @param array $config 新しい設定
     */
    public function updateConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);
        $this->setMemoryLimit();
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
 * メモリモニタークラス
 */
class MemoryMonitor
{
    private $threshold = 80; // メモリ使用率の閾値（パーセント）
    private $logFile = 'cache/memory_monitor.log';
    
    /**
     * メモリ使用量をチェック
     */
    public function checkMemoryUsage(): void
    {
        $usage = memory_get_usage(true);
        $limit = $this->getBytesFromIni('memory_limit');
        $percentage = ($usage / $limit) * 100;
        
        if ($percentage > $this->threshold) {
            $this->logMemoryUsage();
            $this->optimizeMemory();
        }
    }
    
    /**
     * メモリ使用量をログに記録
     */
    public function logMemoryUsage(): void
    {
        $usage = memory_get_usage(true);
        $peak = memory_get_peak_usage(true);
        $limit = $this->getBytesFromIni('memory_limit');
        
        $log = sprintf(
            "[%s] Memory Usage: %.2fMB / %.2fMB (%.1f%%), Peak: %.2fMB\n",
            date('Y-m-d H:i:s'),
            $usage / 1024 / 1024,
            $limit / 1024 / 1024,
            ($usage / $limit) * 100,
            $peak / 1024 / 1024
        );
        
        file_put_contents($this->logFile, $log, FILE_APPEND);
    }
    
    /**
     * メモリを最適化
     */
    private function optimizeMemory(): void
    {
        // 大きな変数の解放
        foreach ($GLOBALS as $key => $value) {
            if (strpos($key, '_') !== 0 && is_array($value) && count($value) > 1000) {
                unset($GLOBALS[$key]);
            }
        }
        
        // ガベージコレクション
        gc_collect_cycles();
    }
    
    /**
     * バイナリを数値に変換
     */
    private function getBytesFromIni(string $key): int
    {
        $value = ini_get($key);
        return (int)$value;
    }
}

/**
 * プロセスプールクラス
 */
class ProcessPool
{
    private $maxProcesses;
    private $activeProcesses = [];
    
    public function __construct(int $maxProcesses)
    {
        $this->maxProcesses = $maxProcesses;
    }
    
    /**
     * プロセスを実行
     */
    public function execute(callable $task)
    {
        // プロセスプールの実装（実際にはReactPHPまたはAMPを使用）
        // ここではシンプルな実装
        
        while (count($this->activeProcesses) >= $this->maxProcesses) {
            // 完了したプロセスをクリーンアップ
            $this->cleanupCompletedProcesses();
            usleep(100000); // 0.1秒待機
        }
        
        // プロセスを作成
        $process = new Promise();
        $this->activeProcesses[] = $process;
        
        // 非同期で実行
        $this->executeAsync($task, $process);
        
        return $process;
    }
    
    /**
     * 非同期で実行
     */
    private function executeAsync(callable $task, Promise $promise): void
    {
        // シンプルな非同期実装
        try {
            $result = $task();
            $promise->resolve($result);
        } catch (\Exception $e) {
            $promise->reject($e);
        }
    }
    
    /**
     * 完了したプロセスをクリーンアップ
     */
    private function cleanupCompletedProcesses(): void
    {
        $this->activeProcesses = array_filter($this->activeProcesses, function($process) {
            return !$process->isCompleted();
        });
    }
}

/**
 * プロミスクラス（簡略化版）
 */
class Promise
{
    private $value = null;
    private $error = null;
    private $resolved = false;
    private $rejected = false;
    
    public function resolve($value): void
    {
        $this->value = $value;
        $this->resolved = true;
    }
    
    public function reject(\Exception $error): void
    {
        $this->error = $error;
        $this->rejected = true;
    }
    
    public function wait()
    {
        if ($this->resolved) {
            return $this->value;
        }
        
        if ($this->rejected) {
            throw $this->error;
        }
        
        // 完了を待機
        while (!$this->resolved && !$this->rejected) {
            usleep(10000);
        }
        
        return $this->wait();
    }
    
    public function isCompleted(): bool
    {
        return $this->resolved || $this->rejected;
    }
}

/**
 * ジョブキュークラス
 */
class JobQueue
{
    private $queueFile = 'cache/job_queue.json';
    
    /**
     * ジョブを追加
     */
    public function addJob(array $jobData): void
    {
        $queue = $this->loadQueue();
        $queue[] = $jobData;
        $this->saveQueue($queue);
    }
    
    /**
     * ジョブを更新
     */
    public function updateJob(string $jobId, array $jobData): void
    {
        $queue = $this->loadQueue();
        
        foreach ($queue as &$job) {
            if ($job['id'] === $jobId) {
                $job = $jobData;
                break;
            }
        }
        
        $this->saveQueue($queue);
    }
    
    /**
     * ジョブを取得
     */
    public function getJob(string $jobId): ?array
    {
        $queue = $this->loadQueue();
        
        foreach ($queue as $job) {
            if ($job['id'] === $jobId) {
                return $job;
            }
        }
        
        return null;
    }
    
    /**
     * すべてのジョブを取得
     */
    public function getAllJobs(): array
    {
        return $this->loadQueue();
    }
    
    /**
     * ジョブを削除
     */
    public function removeJob(string $jobId): bool
    {
        $queue = $this->loadQueue();
        $originalCount = count($queue);
        
        $queue = array_filter($queue, function($job) use ($jobId) {
            return $job['id'] !== $jobId;
        });
        
        if (count($queue) < $originalCount) {
            $this->saveQueue($queue);
            return true;
        }
        
        return false;
    }
    
    /**
     * キューをロード
     */
    private function loadQueue(): array
    {
        if (!file_exists($this->queueFile)) {
            return [];
        }
        
        $content = file_get_contents($this->queueFile);
        return json_decode($content, true) ?: [];
    }
    
    /**
     * キューを保存
     */
    private function saveQueue(array $queue): void
    {
        $content = json_encode($queue, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        file_put_contents($this->queueFile, $content);
    }
}