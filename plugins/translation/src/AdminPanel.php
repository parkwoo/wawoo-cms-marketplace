<?php

namespace Wawoo\Plugin\Translation;

class AdminPanel
{
    private $plugin;
    private $config;
    private $cache;
    private $logger;
    private $sessionManager;
    private $csrfTokenManager;
    private static $instance = null;

    /**
     * シングルトンインスタンスを取得
     *
     * @return self
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * コンストラクタ
     */
    private function __construct()
    {
        $this->plugin = new Plugin();
        $this->config = new ConfigManager();
        $this->cache = new CacheManager();
        $this->logger = new Logger();
        $this->sessionManager = SessionManager::getInstance();
        $this->csrfTokenManager = new CSRFTokenManager();
        
        // 管理画面の初期化
        $this->initializeAdminPanel();
    }

    /**
     * 管理画面を初期化
     */
    private function initializeAdminPanel(): void
    {
        // セッションの確認
        if (!$this->sessionManager->isValid()) {
            header('Location: /admin/login');
            exit;
        }
        
        // CSRFトークンの検証
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST['csrf_token'] ?? '';
            if (!$this->csrfTokenManager->validate($token)) {
                die('CSRFトークンが無効です');
            }
        }
        
        // アクセス権限の確認
        if (!$this->checkAdminPermission()) {
            die('管理者権限が必要です');
        }
    }

    /**
     * 管理画面を表示
     */
    public function render(): void
    {
        $action = $_GET['action'] ?? 'dashboard';
        
        switch ($action) {
            case 'dashboard':
                $this->renderDashboard();
                break;
            case 'settings':
                $this->renderSettings();
                break;
            case 'translation':
                $this->renderTranslation();
                break;
            case 'terminology':
                $this->renderTerminology();
                break;
            case 'quality':
                $this->renderQuality();
                break;
            case 'security':
                $this->renderSecurity();
                break;
            case 'performance':
                $this->renderPerformance();
                break;
            case 'multilanguage':
                $this->renderMultilanguage();
                break;
            case 'api_monitor':
                $this->renderAPIMonitor();
                break;
            default:
                $this->renderDashboard();
        }
    }

    /**
     * ダッシュボードを表示
     */
    private function renderDashboard(): void
    {
        $stats = $this->getDashboardStats();
        $recentActivities = $this->getRecentActivities();
        $systemStatus = $this->getSystemStatus();
        
        $this->renderTemplate('admin/dashboard', [
            'stats' => $stats,
            'recent_activities' => $recentActivities,
            'system_status' => $systemStatus,
            'csrf_token' => $this->csrfTokenManager->generate()
        ]);
    }

    /**
     * 設定画面を表示
     */
    private function renderSettings(): void
    {
        $settings = $this->config->getAllSettings();
        $formAction = $_POST['action'] ?? '';
        
        if ($formAction === 'save') {
            $this->saveSettings();
        }
        
        $this->renderTemplate('admin/settings', [
            'settings' => $settings,
            'csrf_token' => $this->csrfTokenManager->generate()
        ]);
    }

    /**
     * 翻訳管理画面を表示
     */
    private function renderTranslation(): void
    {
        $action = $_GET['subaction'] ?? 'list';
        
        switch ($action) {
            case 'list':
                $this->renderTranslationList();
                break;
            case 'add':
                $this->renderTranslationForm();
                break;
            case 'edit':
                $this->renderTranslationForm($_GET['id'] ?? '');
                break;
            case 'delete':
                $this->deleteTranslation($_GET['id'] ?? '');
                break;
            case 'batch':
                $this->renderBatchTranslation();
                break;
            default:
                $this->renderTranslationList();
        }
    }

    /**
     * 専門用語管理画面を表示
     */
    private function renderTerminology(): void
    {
        $action = $_GET['subaction'] ?? 'list';
        
        switch ($action) {
            case 'list':
                $this->renderTerminologyList();
                break;
            case 'add':
                $this->renderTerminologyForm();
                break;
            case 'edit':
                $this->renderTerminologyForm($_GET['id'] ?? '');
                break;
            case 'delete':
                $this->deleteTerminology($_GET['id'] ?? '');
                break;
            case 'import':
                $this->renderImportForm();
                break;
            case 'export':
                $this->exportTerminology();
                break;
            default:
                $this->renderTerminologyList();
        }
    }

    /**
     * 品質管理画面を表示
     */
    private function renderQuality(): void
    {
        $action = $_GET['subaction'] ?? 'reports';
        
        switch ($action) {
            case 'reports':
                $this->renderQualityReports();
                break;
            case 'settings':
                $this->renderQualitySettings();
                break;
            case 'history':
                $this->renderQualityHistory();
                break;
            default:
                $this->renderQualityReports();
        }
    }

    /**
     * セキュリティ管理画面を表示
     */
    private function renderSecurity(): void
    {
        $action = $_GET['subaction'] ?? 'overview';
        
        switch ($action) {
            case 'overview':
                $this->renderSecurityOverview();
                break;
            case 'csrf':
                $this->renderCSRFSettings();
                break;
            case 'auth':
                $this->renderAuthSettings();
                case 'totp':
                $this->renderTOTPSettings();
                break;
            case 'logs':
                $this->renderSecurityLogs();
                break;
            default:
                $this->renderSecurityOverview();
        }
    }

    /**
     * パフォーマンス管理画面を表示
     */
    private function renderPerformance(): void
    {
        $action = $_GET['subaction'] ?? 'overview';
        
        switch ($action) {
            case 'overview':
                $this->renderPerformanceOverview();
                break;
            case 'cache':
                $this->renderCacheManagement();
                break;
            case 'optimization':
                $this->renderOptimizationSettings();
                break;
            case 'monitoring':
                $this->renderPerformanceMonitoring();
                break;
            default:
                $this->renderPerformanceOverview();
        }
    }

    /**
     * 多言語管理画面を表示
     */
    private function renderMultilanguage(): void
    {
        $action = $_GET['subaction'] ?? 'languages';
        
        switch ($action) {
            case 'languages':
                $this->renderLanguageList();
                break;
            case 'add':
                $this->renderLanguageForm();
                break;
            case 'edit':
                $this->renderLanguageForm($_GET['id'] ?? '');
                break;
            case 'delete':
                $this->deleteLanguage($_GET['id'] ?? '');
                break;
            case 'content':
                $this->renderMultilanguageContent();
                break;
            default:
                $this->renderLanguageList();
        }
    }

    /**
     * API監視画面を表示
     */
    private function renderAPIMonitor(): void
    {
        $action = $_GET['subaction'] ?? 'overview';
        
        switch ($action) {
            case 'overview':
                $this->renderAPIOverview();
                break;
            case 'usage':
                $this->renderAPIUsage();
                break;
            case 'costs':
                $this->renderAPICosts();
                break;
            case 'providers':
                $this->renderAPIProviders();
                break;
            case 'reports':
                $this->renderAPIReports();
                break;
            default:
                $this->renderAPIOverview();
        }
    }

    /**
     * ダッシュボード統計を取得
     *
     * @return array 統計データ
     */
    private function getDashboardStats(): array
    {
        return [
            'total_translations' => $this->getTotalTranslations(),
            'pending_reviews' => $this->getPendingReviews(),
            'quality_score' => $this->getAverageQualityScore(),
            'api_usage_today' => $this->getTodayAPIUsage(),
            'cache_hit_rate' => $this->getCacheHitRate(),
            'active_sessions' => $this->getActiveSessions(),
            'security_alerts' => $this->getSecurityAlerts()
        ];
    }

    /**
     * 最近の活動を取得
     *
     * @return array 活動データ
     */
    private function getRecentActivities(): array
    {
        $activities = [];
        $logFile = 'cache/translation/activities.log';
        
        if (file_exists($logFile)) {
            $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $activities = array_slice($lines, -10); // 最新10件
        }
        
        return $activities;
    }

    /**
     * システム状態を取得
     *
     * @return array システム状態
     */
    private function getSystemStatus(): array
    {
        return [
            'status' => 'healthy',
            'uptime' => $this->getUptime(),
            'memory_usage' => $this->getMemoryUsage(),
            'disk_usage' => $this->getDiskUsage(),
            'api_status' => $this->getAPIStatus(),
            'database_status' => 'connected'
        ];
    }

    /**
     * 翻訳リストを表示
     */
    private function renderTranslationList(): void
    {
        $page = $_GET['page'] ?? 1;
        $limit = 20;
        $offset = ($page - 1) * $limit;
        
        $translations = $this->getTranslations($offset, $limit);
        $total = $this->getTotalTranslations();
        
        $this->renderTemplate('admin/translation/list', [
            'translations' => $translations,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'csrf_token' => $this->csrfTokenManager->generate()
        ]);
    }

    /**
     * 翻訳フォームを表示
     */
    private function renderTranslationForm(string $id = ''): void
    {
        $translation = null;
        
        if (!empty($id)) {
            $translation = $this->getTranslationById($id);
        }
        
        $this->renderTemplate('admin/translation/form', [
            'translation' => $translation,
            'csrf_token' => $this->csrfTokenManager->generate()
        ]);
    }

    /**
     * 専門用語リストを表示
     */
    private function renderTerminologyList(): void
    {
        $page = $_GET['page'] ?? 1;
        $limit = 20;
        $offset = ($page - 1) * $limit;
        
        $terms = $this->getTerminology($offset, $limit);
        $total = $this->getTotalTerminology();
        
        $this->renderTemplate('admin/terminology/list', [
            'terms' => $terms,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'csrf_token' => $this->csrfTokenManager->generate()
        ]);
    }

    /**
     * 専門用語フォームを表示
     */
    private function renderTerminologyForm(string $id = ''): void
    {
        $term = null;
        
        if (!empty($id)) {
            $term = $this->getTerminologyById($id);
        }
        
        $this->renderTemplate('admin/terminology/form', [
            'term' => $term,
            'csrf_token' => $this->csrfTokenManager->generate()
        ]);
    }

    /**
     * 品質レポートを表示
     */
    private function renderQualityReports(): void
    {
        $reports = $this->getQualityReports();
        $filters = $_GET;
        
        $this->renderTemplate('admin/quality/reports', [
            'reports' => $reports,
            'filters' => $filters,
            'csrf_token' => $this->csrfTokenManager->generate()
        ]);
    }

    /**
     * セキュリティ概要を表示
     */
    private function renderSecurityOverview(): void
    {
        $overview = $this->getSecurityOverview();
        
        $this->renderTemplate('admin/security/overview', [
            'overview' => $overview,
            'csrf_token' => $this->csrfTokenManager->generate()
        ]);
    }

    /**
     * パフォーマンス概要を表示
     */
    private function renderPerformanceOverview(): void
    {
        $overview = $this->getPerformanceOverview();
        
        $this->renderTemplate('admin/performance/overview', [
            'overview' => $overview,
            'csrf_token' => $this->csrfTokenManager->generate()
        ]);
    }

    /**
     * 設定を保存
     */
    private function saveSettings(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }
        
        $settings = $_POST['settings'] ?? [];
        $this->config->saveSettings($settings);
        
        // 成功メッセージ
        $_SESSION['success_message'] = '設定が保存されました';
        
        // リダイレクト
        header('Location: /admin/translation/settings');
        exit;
    }

    /**
     * テンプレートをレンダリング
     */
    private function renderTemplate(string $template, array $data = []): void
    {
        $templatePath = __DIR__ . '/../../templates/admin/' . $template . '.php';
        
        if (!file_exists($templatePath)) {
            die('テンプレートが見つかりません: ' . $template);
        }
        
        // データを変数として展開
        extract($data);
        
        // ヘッダー
        include __DIR__ . '/../../templates/admin/_header.php';
        
        // コンテンツ
        include $templatePath;
        
        // フッター
        include __DIR__ . '/../../templates/admin/_footer.php';
    }

    /**
     * 管理者権限をチェック
     *
     * @return bool 権限がある場合はtrue
     */
    private function checkAdminPermission(): bool
    {
        // 実際の実装ではセッションやデータベースをチェック
        return isset($_SESSION['admin']) && $_SESSION['admin'] === true;
    }

    // 以下はダミーメソッド（実際の実装ではデータベースやファイルシステムから取得）
    private function getTotalTranslations(): int { return 1000; }
    private function getPendingReviews(): int { return 50; }
    private function getAverageQualityScore(): float { return 85.5; }
    private function getTodayAPIUsage(): int { return 500; }
    private function getCacheHitRate(): float { return 75.3; }
    private function getActiveSessions(): int { return 25; }
    private function getSecurityAlerts(): int { return 3; }
    private function getUptime(): string { return '7d 12h 30m'; }
    private function getMemoryUsage(): string { return '45% (256MB/512MB)'; }
    private function getDiskUsage(): string { return '65% (650GB/1TB)'; }
    private function getAPIStatus(): string { return 'healthy'; }
    private function getTranslations(int $offset, int $limit): array { return []; }
    private function getTerminology(int $offset, int $limit): array { return []; }
    private function getTotalTerminology(): int { return 500; }
    private function getTranslationById(string $id): ?array { return null; }
    private function getTerminologyById(string $id): ?array { return null; }
    private function getQualityReports(): array { return []; }
    private function getSecurityOverview(): array { return []; }
    private function getPerformanceOverview(): array { return []; }
}