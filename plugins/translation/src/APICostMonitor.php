<?php

namespace Wawoo\Plugin\Translation;

class APICostMonitor
{
    // 監視設定
    private const DEFAULT_DAILY_LIMIT = 10000; // 日次デフォルトリミット
    private const DEFAULT_MONTHLY_LIMIT = 300000; // 月次デフォルトリミット
    private const DEFAULT_COST_PER_REQUEST = 0.002; // 1リクエストあたりのコスト（USD）
    private const ALERT_THRESHOLD_PERCENT = 80; // アラート閾値（パーセント）
    private const CLEANUP_INTERVAL = 86400; // 1日

    private $config;
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
            'daily_limit' => self::DEFAULT_DAILY_LIMIT,
            'monthly_limit' => self::DEFAULT_MONTHLY_LIMIT,
            'cost_per_request' => self::DEFAULT_COST_PER_REQUEST,
            'alert_threshold' => self::ALERT_THRESHOLD_PERCENT,
            'currency' => 'USD',
            'timezone' => 'Asia/Tokyo'
        ], $config);

        $this->storage = new APICostStorage();
        $this->cleanupOldData();
    }

    /**
     * APIリクエストを記録する
     *
     * @param string $provider プロバイダ名
     * @param string $model モデル名
     * @param int $tokens 使用したトークン数
     * @param array $metadata メタデータ
     */
    public function recordRequest(string $provider, string $model, int $tokens = 0, array $metadata = []): void
    {
        $cost = $this->calculateCost($provider, $model, $tokens);
        $timestamp = time();
        
        $request = [
            'provider' => $provider,
            'model' => $model,
            'tokens' => $tokens,
            'cost' => $cost,
            'timestamp' => $timestamp,
            'metadata' => $metadata
        ];

        // ストレージに保存
        $this->storage->saveRequest($request);

        // アラートチェック
        $this->checkAlerts();
    }

    /**
     * コストを計算する
     *
     * @param string $provider プロバイダ名
     * @param string $model モデル名
     * @param int $tokens トークン数
     * @return float コスト
     */
    private function calculateCost(string $provider, string $model, int $tokens): float
    {
        // プロバイダとモデルに基づいたコスト計算
        $costs = [
            'openai' => [
                'gpt-4' => 0.03 / 1000, // $0.03 per 1K tokens
                'gpt-4-turbo' => 0.01 / 1000,
                'gpt-3.5-turbo' => 0.0015 / 1000,
                'gpt-3.5-turbo-16k' => 0.003 / 1000
            ],
            'anthropic' => [
                'claude-3-opus' => 0.015 / 1000,
                'claude-3-sonnet' => 0.003 / 1000,
                'claude-3-haiku' => 0.00025 / 1000
            ],
            'google' => [
                'gemini-pro' => 0.000125 / 1000,
                'gemini-ultra' => 0.0005 / 1000
            ]
        ];

        if (isset($costs[$provider][$model])) {
            return $tokens * $costs[$provider][$model];
        }

        // デフォルトコストを使用
        return $tokens * $this->config['cost_per_request'];
    }

    /**
     * 現在の使用状況を取得する
     *
     * @return array 使用状況データ
     */
    public function getCurrentUsage(): array
    {
        $now = time();
        $today = strtotime(date('Y-m-d', $now));
        $thisMonth = strtotime(date('Y-m-01', $now));

        $dailyUsage = $this->storage->getUsageByPeriod($today, $now);
        $monthlyUsage = $this->storage->getUsageByPeriod($thisMonth, $now);

        return [
            'daily' => [
                'requests' => $dailyUsage['requests'],
                'tokens' => $dailyUsage['tokens'],
                'cost' => $dailyUsage['cost'],
                'limit' => $this->config['daily_limit'],
                'percentage' => ($dailyUsage['requests'] / $this->config['daily_limit']) * 100,
                'remaining' => $this->config['daily_limit'] - $dailyUsage['requests']
            ],
            'monthly' => [
                'requests' => $monthlyUsage['requests'],
                'tokens' => $monthlyUsage['tokens'],
                'cost' => $monthlyUsage['cost'],
                'limit' => $this->config['monthly_limit'],
                'percentage' => ($monthlyUsage['requests'] / $this->config['monthly_limit']) * 100,
                'remaining' => $this->config['monthly_limit'] - $monthlyUsage['requests']
            ],
            'providers' => $this->getProviderUsage(),
            'models' => $this->getModelUsage()
        ];
    }

    /**
     * プロバイダ別の使用状況を取得
     *
     * @return array プロバイダ別使用状況
     */
    private function getProviderUsage(): array
    {
        $usage = $this->storage->getProviderUsage();
        $result = [];

        foreach ($usage as $provider => $data) {
            $result[$provider] = [
                'requests' => $data['requests'],
                'tokens' => $data['tokens'],
                'cost' => $data['cost'],
                'percentage' => ($data['requests'] / $this->config['monthly_limit']) * 100
            ];
        }

        return $result;
    }

    /**
     * モデル別の使用状況を取得
     *
     * @return array モデル別使用状況
     */
    private function getModelUsage(): array
    {
        $usage = $this->storage->getModelUsage();
        $result = [];

        foreach ($usage as $model => $data) {
            $result[$model] = [
                'requests' => $data['requests'],
                'tokens' => $data['tokens'],
                'cost' => $data['cost'],
                'percentage' => ($data['cost'] / $this->config['monthly_limit']) * 100
            ];
        }

        return $result;
    }

    /**
     * アラートをチェックする
     */
    private function checkAlerts(): void
    {
        $usage = $this->getCurrentUsage();
        
        // 日次リミットアラート
        if ($usage['daily']['percentage'] >= $this->config['alert_threshold']) {
            $this->sendAlert('daily_limit', $usage['daily']);
        }

        // 月次リミットアラート
        if ($usage['monthly']['percentage'] >= $this->config['alert_threshold']) {
            $this->sendAlert('monthly_limit', $usage['monthly']);
        }
    }

    /**
     * アラートを送信する
     *
     * @param string $type アラートタイプ
     * @param array $usage 使用状況
     */
    private function sendAlert(string $type, array $usage): void
    {
        $alert = [
            'type' => $type,
            'usage' => $usage,
            'timestamp' => time(),
            'message' => $this->generateAlertMessage($type, $usage)
        ];

        // ログに記録
        $logger = new Logger();
        $logger->warning('APIコストアラート', $alert);

        // 管理者に通知（メールまたはシステム通知）
        $this->notifyAdmin($alert);
    }

    /**
     * アラートメッセージを生成
     *
     * @param string $type アラートタイプ
     * @param array $usage 使用状況
     * @return string メッセージ
     */
    private function generateAlertMessage(string $type, array $usage): string
    {
        $limit = $usage['limit'];
        $used = $usage['requests'];
        $percentage = $usage['percentage'];
        $remaining = $usage['remaining'];

        if ($type === 'daily_limit') {
            return sprintf(
                '日次APIリクエスト制限に達しました。現在: %d/%d (%.1f%%) 残り: %d',
                $used,
                $limit,
                $percentage,
                $remaining
            );
        } else {
            return sprintf(
                '月次APIリクエスト制限に達しました。現在: %d/%d (%.1f%%) 残り: %d',
                $used,
                $limit,
                $percentage,
                $remaining
            );
        }
    }

    /**
     * 管理者に通知する
     *
     * @param array $alert アラートデータ
     */
    private function notifyAdmin(array $alert): void
    {
        // メール通知
        $this->sendEmailNotification($alert);
        
        // システム通知（管理画面など）
        $this->sendSystemNotification($alert);
    }

    /**
     * メール通知を送信
     *
     * @param array $alert アラートデータ
     */
    private function sendEmailNotification(array $alert): void
    {
        // メール設定の取得
        $config = new ConfigManager();
        $emailConfig = $config->getEmailConfig();
        
        if (empty($emailConfig['admin_email'])) {
            return;
        }

        $subject = 'wawoo-cms APIコストアラート';
        $message = $this->generateEmailContent($alert);

        // メール送信（実際の実装ではPHPMailerなどを使用）
        $headers = [
            'From' => 'noreply@wawoo-cms',
            'Content-Type' => 'text/plain; charset=UTF-8'
        ];

        mail($emailConfig['admin_email'], $subject, $message, implode("\r\n", $headers));
    }

    /**
     * システム通知を送信
     *
     * @param array $alert アラートデータ
     */
    private function sendSystemNotification(array $alert): void
    {
        // 通知システムへの記録
        $notification = new NotificationSystem();
        $notification->send('api_cost_alert', $alert);
    }

    /**
     * メール内容を生成
     *
     * @param array $alert アラートデータ
     * @return string メール内容
     */
    private function generateEmailContent(array $alert): string
    {
        $usage = $alert['usage'];
        $message = "wawoo-cms APIコストアラート\n\n";
        $message .= "アラートタイプ: " . $alert['type'] . "\n";
        $message .= "日時: " . date('Y-m-d H:i:s', $alert['timestamp']) . "\n\n";
        $message .= "使用状況:\n";
        $message .= sprintf("リクエスト数: %d / %d\n", $usage['requests'], $usage['limit']);
        $message .= sprintf("使用率: %.1f%%\n", $usage['percentage']);
        $message .= sprintf("残りリクエスト数: %d\n", $usage['remaining']);
        $message .= sprintf("コスト: $%.4f\n", $usage['cost']);
        
        if ($alert['type'] === 'monthly_limit') {
            $message .= "\nプロバイダ別使用状況:\n";
            foreach ($this->getProviderUsage() as $provider => $data) {
                $message .= sprintf("  %s: %dリクエスト ($%.4f)\n", $provider, $data['requests'], $data['cost']);
            }
        }
        
        return $message;
    }

    /**
     * 古いデータをクリーンアップする
     */
    private function cleanupOldData(): void
    {
        $cutoff = time() - (30 * 86400); // 30日前
        $this->storage->cleanupOldData($cutoff);
    }

    /**
     * コストレポートを生成する
     *
     * @param string $period 期間（daily, weekly, monthly）
     * @return array レポートデータ
     */
    public function generateReport(string $period = 'daily'): array
    {
        $now = time();
        $report = [];

        switch ($period) {
            case 'daily':
                $start = strtotime('today', $now);
                $end = strtotime('tomorrow', $now) - 1;
                break;
            case 'weekly':
                $start = strtotime('monday this week', $now);
                $end = strtotime('monday next week', $now) - 1;
                break;
            case 'monthly':
                $start = strtotime('first day of this month', $now);
                $end = strtotime('first day of next month', $now) - 1;
                break;
            default:
                $start = strtotime('today', $now);
                $end = strtotime('tomorrow', $now) - 1;
        }

        $usage = $this->storage->getUsageByPeriod($start, $end);
        $report = [
            'period' => $period,
            'start_date' => date('Y-m-d H:i:s', $start),
            'end_date' => date('Y-m-d H:i:s', $end),
            'requests' => $usage['requests'],
            'tokens' => $usage['tokens'],
            'cost' => $usage['cost'],
            'average_tokens_per_request' => $usage['requests'] > 0 ? $usage['tokens'] / $usage['requests'] : 0,
            'requests_per_hour' => $usage['requests'] / max(1, ($end - $start) / 3600),
            'top_providers' => $this->getTopProviders($start, $end),
            'top_models' => $this->getTopModels($start, $end)
        ];

        return $report;
    }

    /**
     * 上位プロバイダを取得
     *
     * @param int $start 開始時間
     * @param int $end 終了時間
     * @return array 上位プロバイダ
     */
    private function getTopProviders(int $start, int $end): array
    {
        $usage = $this->storage->getProviderUsageByPeriod($start, $end);
        $sorted = [];
        
        foreach ($usage as $provider => $data) {
            $sorted[$provider] = $data['requests'];
        }
        
        arsort($sorted);
        return array_slice($sorted, 0, 5, true);
    }

    /**
     * 上位モデルを取得
     *
     * @param int $start 開始時間
     * @param int $end 終了時間
     * @return array 上位モデル
     */
    private function getTopModels(int $start, int $end): array
    {
        $usage = $this->storage->getModelUsageByPeriod($start, $end);
        $sorted = [];
        
        foreach ($usage as $model => $data) {
            $sorted[$model] = $data['cost'];
        }
        
        arsort($sorted);
        return array_slice($sorted, 0, 5, true);
    }

    /**
     * 使用制限に達しているかチェック
     *
     * @param string $type チェックタイプ（daily, monthly）
     * @return bool 制限に達している場合はtrue
     */
    public function isLimitReached(string $type = 'daily'): bool
    {
        $usage = $this->getCurrentUsage();
        
        if ($type === 'daily') {
            return $usage['daily']['requests'] >= $this->config['daily_limit'];
        } else {
            return $usage['monthly']['requests'] >= $this->config['monthly_limit'];
        }
    }

    /**
     * リクエストを許可するかチェック
     *
     * @return bool 許可する場合はtrue
     */
    public function canMakeRequest(): bool
    {
        // 日次制限チェック
        if ($this->isLimitReached('daily')) {
            return false;
        }
        
        // 月次制限チェック
        if ($this->isLimitReached('monthly')) {
            return false;
        }
        
        return true;
    }

    /**
     * 設定を更新する
     *
     * @param array $config 新しい設定
     */
    public function updateConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * 設定を取得する
     *
     * @return array 設定
     */
    public function getConfig(): array
    {
        return $this->config;
    }
}

/**
 * APIコストストレージクラス
 */
class APICostStorage
{
    private const STORAGE_FILE = 'cache/api_cost_storage.json';
    private const INDEX_FILE = 'cache/api_cost_index.json';
    
    /**
     * リクエストを保存
     *
     * @param array $request リクエストデータ
     */
    public function saveRequest(array $request): void
    {
        $requests = $this->loadAllRequests();
        $requests[] = $request;
        
        // ファイルに書き込む
        $this->writeRequests($requests);
        
        // インデックスを更新
        $this->updateIndex($request);
    }
    
    /**
     * 期間内の使用状況を取得
     *
     * @param int $start 開始時間
     * @param int $end 終了時間
     * @return array 使用状況
     */
    public function getUsageByPeriod(int $start, int $end): array
    {
        $requests = $this->loadRequestsByPeriod($start, $end);
        
        $usage = [
            'requests' => 0,
            'tokens' => 0,
            'cost' => 0
        ];
        
        foreach ($requests as $request) {
            $usage['requests']++;
            $usage['tokens'] += $request['tokens'];
            $usage['cost'] += $request['cost'];
        }
        
        return $usage;
    }
    
    /**
     * プロバイダ別使用状況を取得
     *
     * @return array 使用状況
     */
    public function getProviderUsage(): array
    {
        $index = $this->loadIndex();
        return $index['providers'] ?? [];
    }
    
    /**
     * モデル別使用状況を取得
     *
     * @return array 使用状況
     */
    public function getModelUsage(): array
    {
        $index = $this->loadIndex();
        return $index['models'] ?? [];
    }
    
    /**
     * 古いデータをクリーンアップ
     *
     * @param int $cutoff キャットオフ時間
     */
    public function cleanupOldData(int $cutoff): void
    {
        $requests = $this->loadAllRequests();
        $filtered = array_filter($requests, function($request) use ($cutoff) {
            return $request['timestamp'] >= $cutoff;
        });
        
        if (count($filtered) !== count($requests)) {
            $this->writeRequests($filtered);
            $this->rebuildIndex();
        }
    }
    
    /**
     * プロバイダ別期間内使用状況を取得
     *
     * @param int $start 開始時間
     * @param int $end 終了時間
     * @return array 使用状況
     */
    public function getProviderUsageByPeriod(int $start, int $end): array
    {
        $index = $this->loadIndex();
        $usage = [];
        
        foreach ($index['providers'] ?? [] as $provider => $data) {
            $usage[$provider] = [
                'requests' => 0,
                'tokens' => 0,
                'cost' => 0
            ];
            
            foreach ($data['requests'] ?? [] as $timestamp) {
                if ($timestamp >= $start && $timestamp <= $end) {
                    $usage[$provider]['requests']++;
                    $usage[$provider]['tokens'] += $data['tokens_per_request'] ?? 0;
                    $usage[$provider]['cost'] += $data['cost_per_request'] ?? 0;
                }
            }
        }
        
        return $usage;
    }
    
    /**
     * モデル別期間内使用状況を取得
     *
     * @param int $start 開始時間
     * @param int $end 終了時間
     * @return array 使用状況
     */
    public function getModelUsageByPeriod(int $start, int $end): array
    {
        $index = $this->loadIndex();
        $usage = [];
        
        foreach ($index['models'] ?? [] as $model => $data) {
            $usage[$model] = [
                'requests' => 0,
                'tokens' => 0,
                'cost' => 0
            ];
            
            foreach ($data['requests'] ?? [] as $timestamp) {
                if ($timestamp >= $start && $timestamp <= $end) {
                    $usage[$model]['requests']++;
                    $usage[$model]['tokens'] += $data['tokens_per_request'] ?? 0;
                    $usage[$model]['cost'] += $data['cost_per_request'] ?? 0;
                }
            }
        }
        
        return $usage;
    }
    
    /**
     * すべてのリクエストをロード
     *
     * @return array リクエスト
     */
    private function loadAllRequests(): array
    {
        if (!file_exists(self::STORAGE_FILE)) {
            return [];
        }
        
        $content = file_get_contents(self::STORAGE_FILE);
        if ($content === false) {
            return [];
        }
        
        $data = json_decode($content, true);
        return $data ?: [];
    }
    
    /**
     * 期間内のリクエストをロード
     *
     * @param int $start 開始時間
     * @param int $end 終了時間
     * @return array リクエスト
     */
    private function loadRequestsByPeriod(int $start, int $end): array
    {
        $allRequests = $this->loadAllRequests();
        return array_filter($allRequests, function($request) use ($start, $end) {
            return $request['timestamp'] >= $start && $request['timestamp'] <= $end;
        });
    }
    
    /**
     * リクエストを書き込む
     *
     * @param array $requests リクエスト
     */
    private function writeRequests(array $requests): void
    {
        $content = json_encode($requests, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        file_put_contents(self::STORAGE_FILE, $content);
    }
    
    /**
     * インデックスを更新
     *
     * @param array $request リクエスト
     */
    private function updateIndex(array $request): void
    {
        $index = $this->loadIndex();
        
        // プロバイダインデックス更新
        if (!isset($index['providers'][$request['provider']])) {
            $index['providers'][$request['provider']] = [
                'requests' => [],
                'tokens_per_request' => 0,
                'cost_per_request' => 0,
                'total_requests' => 0
            ];
        }
        
        $providerIndex = &$index['providers'][$request['provider']];
        $providerIndex['requests'][] = $request['timestamp'];
        $providerIndex['tokens_per_request'] = $request['tokens'];
        $providerIndex['cost_per_request'] = $request['cost'];
        $providerIndex['total_requests']++;
        
        // モデルインデックス更新
        if (!isset($index['models'][$request['model']])) {
            $index['models'][$request['model']] = [
                'requests' => [],
                'tokens_per_request' => 0,
                'cost_per_request' => 0,
                'total_requests' => 0
            ];
        }
        
        $modelIndex = &$index['models'][$request['model']];
        $modelIndex['requests'][] = $request['timestamp'];
        $modelIndex['tokens_per_request'] = $request['tokens'];
        $modelIndex['cost_per_request'] = $request['cost'];
        $modelIndex['total_requests']++;
        
        $this->writeIndex($index);
    }
    
    /**
     * インデックスを再構築
     */
    private function rebuildIndex(): void
    {
        $requests = $this->loadAllRequests();
        $index = ['providers' => [], 'models' => []];
        
        foreach ($requests as $request) {
            // プロバイダインデックス更新
            if (!isset($index['providers'][$request['provider']])) {
                $index['providers'][$request['provider']] = [
                    'requests' => [],
                    'tokens_per_request' => 0,
                    'cost_per_request' => 0,
                    'total_requests' => 0
                ];
            }
            
            $providerIndex = &$index['providers'][$request['provider']];
            $providerIndex['requests'][] = $request['timestamp'];
            $providerIndex['total_requests']++;
            
            // モデルインデックス更新
            if (!isset($index['models'][$request['model']])) {
                $index['models'][$request['model']] = [
                    'requests' => [],
                    'tokens_per_request' => 0,
                    'cost_per_request' => 0,
                    'total_requests' => 0
                ];
            }
            
            $modelIndex = &$index['models'][$request['model']];
            $modelIndex['requests'][] = $request['timestamp'];
            $modelIndex['total_requests']++;
        }
        
        $this->writeIndex($index);
    }
    
    /**
     * インデックスをロード
     *
     * @return array インデックス
     */
    private function loadIndex(): array
    {
        if (!file_exists(self::INDEX_FILE)) {
            return ['providers' => [], 'models' => []];
        }
        
        $content = file_get_contents(self::INDEX_FILE);
        if ($content === false) {
            return ['providers' => [], 'models' => []];
        }
        
        $data = json_decode($content, true);
        return $data ?: ['providers' => [], 'models' => []];
    }
    
    /**
     * インデックスを書き込む
     *
     * @param array $index インデックス
     */
    private function writeIndex(array $index): void
    {
        $content = json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        file_put_contents(self::INDEX_FILE, $content);
    }
}

/**
 * 通知システムクラス
 */
class NotificationSystem
{
    /**
     * 通知を送信
     *
     * @param string $type 通知タイプ
     * @param array $data 通知データ
     */
    public function send(string $type, array $data): void
    {
        // 通知システムの実装
        // ここではログに記録するだけ
        $logger = new Logger();
        $logger->info('システム通知', [
            'type' => $type,
            'data' => $data
        ]);
    }
}