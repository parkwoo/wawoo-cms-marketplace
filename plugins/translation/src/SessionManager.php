<?php

namespace Wawoo\Plugin\Translation;

class SessionManager
{
    // セッション設定の定数
    private const DEFAULT_IDLE_TIMEOUT = 1800; // 30分
    private const DEFAULT_ABSOLUTE_TIMEOUT = 7200; // 2時間
    private const DEFAULT_ACTIVITY_CHECK_INTERVAL = 300; // 5分
    private const DEFAULT_REMEMBER_ME_TIMEOUT = 604800; // 7日間

    private $config;
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
            'idle_timeout' => self::DEFAULT_IDLE_TIMEOUT,
            'absolute_timeout' => self::DEFAULT_ABSOLUTE_TIMEOUT,
            'activity_check_interval' => self::DEFAULT_ACTIVITY_CHECK_INTERVAL,
            'remember_me_timeout' => self::DEFAULT_REMEMBER_ME_TIMEOUT,
            'session_name' => 'wawoo_translation_session',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict'
        ], $config);

        // セッションの初期化
        $this->initializeSession();
    }

    /**
     * セッションを初期化する
     */
    private function initializeSession(): void
    {
        // セッション設定
        ini_set('session.cookie_lifetime', 0);
        ini_set('session.cookie_httponly', $this->config['httponly'] ? 1 : 0);
        ini_set('session.cookie_secure', $this->config['secure'] ? 1 : 0);
        ini_set('session.cookie_samesite', $this->config['samesite']);
        ini_set('session.gc_maxlifetime', $this->config['absolute_timeout']);
        ini_set('session.gc_probability', 1);
        ini_set('session.gc_divisor', 100);

        // セッション名の設定
        session_name($this->config['session_name']);

        // 既存セッションの開始
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // セッションデータの初期化
        $this->initializeSessionData();

        // タイムアウトのチェック
        $this->checkTimeouts();
    }

    /**
     * セッションデータを初期化する
     */
    private function initializeSessionData(): void
    {
        if (!isset($_SESSION['translation_session'])) {
            $_SESSION['translation_session'] = [
                'created_at' => time(),
                'last_activity' => time(),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'remember_me' => false,
                'activity_count' => 0,
                'timeout_warnings' => []
            ];
        }
    }

    /**
     * タイムアウトをチェックする
     */
    private function checkTimeouts(): void
    {
        if (!isset($_SESSION['translation_session'])) {
            return;
        }

        $session = &$_SESSION['translation_session'];
        $now = time();
        $lastActivity = $session['last_activity'];
        $idleTime = $now - $lastActivity;
        $absoluteTime = $now - $session['created_at'];

        // アイドルタイムアウトチェック
        if ($idleTime > $this->config['idle_timeout']) {
            $this->terminateSession('セッションがアイドル状態でタイムアウトしました');
            return;
        }

        // 絶対タイムアウトチェック
        if ($absoluteTime > $this->config['absolute_timeout']) {
            $this->terminateSession('セッションが最大時間を超過しました');
            return;
        }

        // タイムアウト警告
        $idleWarningTime = $this->config['idle_timeout'] - 300; // 5分前
        if ($idleTime > $idleWarningTime && !in_array($idleWarningTime, $session['timeout_warnings'])) {
            $session['timeout_warnings'][] = $idleWarningTime;
            // ここで警告をログに記録またはフロントエンドに通知
            $this->logTimeoutWarning('idle', $idleTime);
        }
    }

    /**
     * セッションを更新する（アクティビティを記録）
     */
    public function updateActivity(): void
    {
        if (!isset($_SESSION['translation_session'])) {
            return;
        }

        $session = &$_SESSION['translation_session'];
        $now = time();
        $lastActivity = $session['last_activity'];
        $timeSinceLastUpdate = $now - $lastActivity;

        // 定期的なアクティビティチェック
        if ($timeSinceLastUpdate > $this->config['activity_check_interval']) {
            $session['last_activity'] = $now;
            $session['activity_count']++;
            
            // IPアドレスまたはUser-Agentの変更を検知
            if ($session['ip_address'] !== ($_SERVER['REMOTE_ADDR'] ?? 'unknown')) {
                $this->logSecurityEvent('IPアドレスの変更を検知', [
                    'old_ip' => $session['ip_address'],
                    'new_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
                $session['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            }

            if ($session['user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown')) {
                $this->logSecurityEvent('User-Agentの変更を検知', [
                    'old_ua' => $session['user_agent'],
                    'new_ua' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
                ]);
                $session['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            }
        }
    }

    /**
     * Remember Me機能を有効にする
     */
    public function enableRememberMe(): void
    {
        if (!isset($_SESSION['translation_session'])) {
            return;
        }

        $_SESSION['translation_session']['remember_me'] = true;
        $_SESSION['translation_session']['last_activity'] = time();
        
        // クッキーの有効期限を延長
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            session_id(),
            time() + $this->config['remember_me_timeout'],
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    /**
     * セッションを延長する
     */
    public function extendSession(): void
    {
        if (!isset($_SESSION['translation_session'])) {
            return;
        }

        $_SESSION['translation_session']['last_activity'] = time();
        $_SESSION['translation_session']['activity_count']++;
    }

    /**
     * セッションを破棄する
     *
     * @param string $reason 破棄理由
     */
    public function terminateSession(string $reason = ''): void
    {
        // セッションデータの保存
        $sessionData = $_SESSION['translation_session'] ?? [];
        
        // セッションを破棄
        $_SESSION = [];
        
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        
        session_destroy();
        
        // ログに記録
        $this->logSessionTermination($reason, $sessionData);
    }

    /**
     * セッション情報を取得する
     *
     * @return array セッション情報
     */
    public function getSessionInfo(): array
    {
        if (!isset($_SESSION['translation_session'])) {
            return [];
        }

        $session = $_SESSION['translation_session'];
        $now = time();
        
        return [
            'created_at' => $session['created_at'],
            'last_activity' => $session['last_activity'],
            'idle_time' => $now - $session['last_activity'],
            'absolute_time' => $now - $session['created_at'],
            'activity_count' => $session['activity_count'],
            'remember_me' => $session['remember_me'],
            'ip_address' => $session['ip_address'],
            'user_agent' => $session['user_agent'],
            'timeout_warnings' => $session['timeout_warnings']
        ];
    }

    /**
     * セッションが有効かどうかを確認
     *
     * @return bool 有効な場合はtrue
     */
    public function isValid(): bool
    {
        if (!isset($_SESSION['translation_session'])) {
            return false;
        }

        $session = $_SESSION['translation_session'];
        $now = time();
        
        // アイドルタイムアウトチェック
        if (($now - $session['last_activity']) > $this->config['idle_timeout']) {
            return false;
        }
        
        // 絶対タイムアウトチェック
        if (($now - $session['created_at']) > $this->config['absolute_timeout']) {
            return false;
        }
        
        return true;
    }

    /**
     * セッションIDを再生成する
     */
    public function regenerateId(): void
    {
        session_regenerate_id(true);
        
        if (isset($_SESSION['translation_session'])) {
            $_SESSION['translation_session']['regenerated_at'] = time();
        }
    }

    /**
     * セッション設定を取得
     *
     * @return array 設定
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * セッション設定を更新
     *
     * @param array $config 新しい設定
     */
    public function updateConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);
        
        // 即座に適用可能な設定を更新
        ini_set('session.gc_maxlifetime', $this->config['absolute_timeout']);
    }

    /**
     * セキュリティイベントをログに記録
     *
     * @param string $event イベント名
     * @param array $context コンテキスト
     */
    private function logSecurityEvent(string $event, array $context = []): void
    {
        $logger = new Logger();
        $logger->warning('セキュリティイベント: ' . $event, $context);
    }

    /**
     * タイムアウト警告をログに記録
     *
     * @param string $type タイプ
     * @param int $time 時間
     */
    private function logTimeoutWarning(string $type, int $time): void
    {
        $logger = new Logger();
        $logger->info('セッションタイムアウト警告', [
            'type' => $type,
            'time' => $time,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    }

    /**
     * セッション終了をログに記録
     *
     * @param string $reason 理由
     * @param array $sessionData セッションデータ
     */
    private function logSessionTermination(string $reason, array $sessionData): void
    {
        $logger = new Logger();
        $logger->info('セッション終了', [
            'reason' => $reason,
            'session_duration' => time() - ($sessionData['created_at'] ?? 0),
            'activity_count' => $sessionData['activity_count'] ?? 0,
            'ip' => $sessionData['ip_address'] ?? 'unknown'
        ]);
    }
}