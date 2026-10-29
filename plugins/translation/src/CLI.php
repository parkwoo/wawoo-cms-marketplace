<?php
namespace Wawoo\Plugin\Translation;

/**
 * CLI commands for translation plugin
 */
class CLI
{
    /**
     * Available commands
     */
    private static array $commands = [
        'translate' => 'Translate content',
        'translate:post' => 'Translate a specific post',
        'translate:page' => 'Translate a specific page',
        'translate:all' => 'Translate all content',
        'config:test' => 'Test API configuration',
        'config:validate' => 'Validate configuration',
        'config:export' => 'Export configuration',
        'config:import' => 'Import configuration',
        'env:create' => 'Create .env file from template',
        'env:validate' => 'Validate environment variables',
        'env:status' => 'Show environment status',
        'queue:process' => 'Process translation queue',
        'queue:clear' => 'Clear translation queue',
        'logs:show' => 'Show recent logs',
        'logs:clear' => 'Clear logs',
        'stats' => 'Show translation statistics'
    ];
    
    /**
     * Handle CLI command
     */
    public static function handle(array $args): void
    {
        $command = $args[0] ?? 'help';
        
        switch ($command) {
            case 'help':
                self::showHelp();
                break;
                
            case 'translate':
                self::translateContent();
                break;
                
            case 'translate:post':
                self::translatePost($args[1] ?? '');
                break;
                
            case 'translate:page':
                self::translatePage($args[1] ?? '');
                break;
                
            case 'translate:all':
                self::translateAll();
                break;
                
            case 'config:test':
                self::testConfig();
                break;
                
            case 'config:validate':
                self::validateConfig();
                break;
                
            case 'config:export':
                self::exportConfig();
                break;
                
            case 'config:import':
                self::importConfig($args[1] ?? '');
                break;
                
            case 'env:create':
                EnvCreator::create();
                break;
                
            case 'env:validate':
                $errors = EnvCreator::validate();
                if (empty($errors)) {
                    echo "✓ Environment variables are valid\n";
                } else {
                    echo "✗ Environment validation failed:\n";
                    foreach ($errors as $error) {
                        echo "  - {$error}\n";
                    }
                }
                break;
                
            case 'env:status':
                EnvCreator::status();
                break;
                
            case 'queue:process':
                self::processQueue();
                break;
                
            case 'queue:clear':
                self::clearQueue();
                break;
                
            case 'logs:show':
                self::showLogs();
                break;
                
            case 'logs:clear':
                self::clearLogs();
                break;
                
            case 'stats':
                self::showStats();
                break;
                
            default:
                echo "Unknown command: {$command}\n";
                self::showHelp();
        }
    }
    
    /**
     * Show help
     */
    private static function showHelp(): void
    {
        echo "Translation Plugin CLI Commands\n";
        echo "==============================\n\n";
        
        foreach (self::$commands as $command => $description) {
            echo "  {$command}\n    {$description}\n\n";
        }
        
        echo "Examples:\n";
        echo "  php bin/wawoo translation:post my-post\n";
        echo "  php bin/wawoo config:test\n";
        echo "  php bin/wawoo translate:all\n";
        echo "  php bin/wawoo env:create\n";
        echo "  php bin/wawoo env:status\n";
    }
    
    /**
     * Translate content
     */
    private static function translateContent(): void
    {
        echo "Translation Content CLI\n";
        echo "======================\n\n";
        
        // Get content from stdin or file
        $content = self::getContentFromInput();
        
        if (empty($content)) {
            echo "Error: No content provided\n";
            return;
        }
        
        try {
            // Translate content
            $translator = new Translator();
            $translated = $translator->handle($content);
            
            if ($translated) {
                echo "Translation successful:\n\n";
                echo $translated . "\n";
            } else {
                echo "Translation failed\n";
            }
            
        } catch (\Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * Translate a specific post
     */
    private static function translatePost(string $postSlug): void
    {
        if (empty($postSlug)) {
            echo "Error: Post slug is required\n";
            echo "Usage: php bin/wawoo translation:post <slug>\n";
            return;
        }
        
        $postPath = __DIR__ . '/../../../posts/' . $postSlug . '/index.md';
        
        if (!file_exists($postPath)) {
            echo "Error: Post not found at {$postPath}\n";
            return;
        }
        
        echo "Translating post: {$postSlug}\n";
        echo "=========================\n\n";
        
        try {
            // Read post content
            $content = file_get_contents($postPath);
            
            // Translate content
            $translator = new Translator();
            $translated = $translator->handle($content);
            
            if ($translated) {
                // Save translated content
                $targetLang = ConfigManager::get('target_language', 'en');
                $targetPath = __DIR__ . '/../../../posts/' . $postSlug . '/' . $targetLang . '/index.md';
                
                // Ensure directory exists
                $targetDir = dirname($targetPath);
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0755, true);
                }
                
                file_put_contents($targetPath, $translated);
                
                echo "Post translated successfully to: {$targetPath}\n";
            } else {
                echo "Translation failed\n";
            }
            
        } catch (\Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * Translate a specific page
     */
    private static function translatePage(string $pageSlug): void
    {
        if (empty($pageSlug)) {
            echo "Error: Page slug is required\n";
            echo "Usage: php bin/wawoo translation:page <slug>\n";
            return;
        }
        
        $pagePath = __DIR__ . '/../../../pages/' . $pageSlug . '/index.md';
        
        if (!file_exists($pagePath)) {
            echo "Error: Page not found at {$pagePath}\n";
            return;
        }
        
        echo "Translating page: {$pageSlug}\n";
        echo "=========================\n\n";
        
        try {
            // Read page content
            $content = file_get_contents($pagePath);
            
            // Translate content
            $translator = new Translator();
            $translated = $translator->handle($content);
            
            if ($translated) {
                // Save translated content
                $targetLang = ConfigManager::get('target_language', 'en');
                $targetPath = __DIR__ . '/../../../pages/' . $pageSlug . '/' . $targetLang . '/index.md';
                
                // Ensure directory exists
                $targetDir = dirname($targetPath);
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0755, true);
                }
                
                file_put_contents($targetPath, $translated);
                
                echo "Page translated successfully to: {$targetPath}\n";
            } else {
                echo "Translation failed\n";
            }
            
        } catch (\Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * Translate all content
     */
    private static function translateAll(): void
    {
        echo "Translating all content\n";
        echo "======================\n\n";
        
        try {
            // Find all posts
            $postsDir = __DIR__ . '/../../../posts';
            $posts = glob($postsDir . '/*/index.md');
            
            if (empty($posts)) {
                echo "No posts found\n";
                return;
            }
            
            echo "Found " . count($posts) . " posts to translate\n\n";
            
            foreach ($posts as $postPath) {
                $postSlug = basename(dirname($postPath));
                echo "Translating: {$postSlug}\n";
                
                try {
                    self::translatePost($postSlug);
                } catch (\Exception $e) {
                    echo "Error translating {$postSlug}: " . $e->getMessage() . "\n";
                }
                
                echo "\n";
            }
            
            // Find all pages
            $pagesDir = __DIR__ . '/../../../pages';
            $pages = glob($pagesDir . '/*/index.md');
            
            if (!empty($pages)) {
                echo "Found " . count($pages) . " pages to translate\n\n";
                
                foreach ($pages as $pagePath) {
                    $pageSlug = basename(dirname($pagePath));
                    echo "Translating: {$pageSlug}\n";
                    
                    try {
                        self::translatePage($pageSlug);
                    } catch (\Exception $e) {
                        echo "Error translating {$pageSlug}: " . $e->getMessage() . "\n";
                    }
                    
                    echo "\n";
                }
            }
            
            echo "Translation completed\n";
            
        } catch (\Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * Test configuration
     */
    private static function testConfig(): void
    {
        echo "Testing Configuration\n";
        echo "====================\n\n";
        
        try {
            // Test API connection
            $apiClient = new APIClient();
            $provider = ConfigManager::get('provider', 'openai');
            $success = $apiClient->testConnection($provider);
            
            if ($success) {
                echo "✓ API connection successful\n";
            } else {
                echo "✗ API connection failed\n";
            }
            
            // Validate configuration
            $errors = ConfigManager::validate();
            
            if (empty($errors)) {
                echo "✓ Configuration is valid\n";
            } else {
                echo "✗ Configuration errors:\n";
                foreach ($errors as $error) {
                    echo "  - {$error}\n";
                }
            }
            
            // Show current config
            echo "\nCurrent Configuration:\n";
            echo "=====================\n";
            $config = ConfigManager::getAll();
            foreach ($config as $key => $value) {
                if ($key === 'api_key') {
                    $value = str_repeat('*', strlen($value));
                }
                echo "{$key}: {$value}\n";
            }
            
        } catch (\Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * Validate configuration
     */
    private static function validateConfig(): void
    {
        echo "Validating Configuration\n";
        echo "======================\n\n";
        
        try {
            $errors = ConfigManager::validate();
            
            if (empty($errors)) {
                echo "✓ Configuration is valid\n";
            } else {
                echo "✗ Configuration errors:\n";
                foreach ($errors as $error) {
                    echo "  - {$error}\n";
                }
            }
            
        } catch (\Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * Export configuration
     */
    private static function exportConfig(): void
    {
        echo "Exporting Configuration\n";
        echo "======================\n\n";
        
        try {
            $config = ConfigManager::export();
            echo $config . "\n";
            
        } catch (\Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * Import configuration
     */
    private static function importConfig(string $configFile): void
    {
        if (empty($configFile)) {
            echo "Error: Configuration file is required\n";
            echo "Usage: php bin/wawoo config:import <file>\n";
            return;
        }
        
        echo "Importing Configuration\n";
        echo "======================\n\n";
        
        try {
            if (!file_exists($configFile)) {
                echo "Error: Configuration file not found: {$configFile}\n";
                return;
            }
            
            $config = file_get_contents($configFile);
            $success = ConfigManager::import($config);
            
            if ($success) {
                echo "✓ Configuration imported successfully\n";
            } else {
                echo "✗ Configuration import failed\n";
            }
            
        } catch (\Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * Process translation queue
     */
    private static function processQueue(): void
    {
        echo "Processing Translation Queue\n";
        echo "==========================\n\n";
        
        try {
            $queueFile = self::getQueueFilePath();
            
            if (!file_exists($queueFile)) {
                echo "Queue is empty\n";
                return;
            }
            
            $data = file_get_contents($queueFile);
            $queue = json_decode($data, true) ?? [];
            
            if (empty($queue)) {
                echo "Queue is empty\n";
                return;
            }
            
            echo "Processing " . count($queue) . " items\n\n";
            
            foreach ($queue as $item) {
                echo "Processing: {$item['file_path']}\n";
                
                try {
                    self::translateContentItem($item);
                    echo "✓ Translated successfully\n";
                } catch (\Exception $e) {
                    echo "✗ Translation failed: " . $e->getMessage() . "\n";
                }
                
                echo "\n";
            }
            
            // Clear queue
            unlink($queueFile);
            echo "Queue processing completed\n";
            
        } catch (\Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * Clear translation queue
     */
    private static function clearQueue(): void
    {
        echo "Clearing Translation Queue\n";
        echo "=========================\n\n";
        
        try {
            $queueFile = self::getQueueFilePath();
            
            if (file_exists($queueFile)) {
                unlink($queueFile);
                echo "Queue cleared successfully\n";
            } else {
                echo "Queue is already empty\n";
            }
            
        } catch (\Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * Show recent logs
     */
    private static function showLogs(): void
    {
        echo "Recent Logs\n";
        echo "===========\n\n";
        
        try {
            $logs = Logger::getRecentLogs(50);
            
            if (empty($logs)) {
                echo "No logs found\n";
                return;
            }
            
            foreach ($logs as $log) {
                echo "[{$log['timestamp']}] {$log['level']}: {$log['message']}\n";
                
                if (!empty($log['context'])) {
                    echo "  Context: " . json_encode($log['context'], JSON_UNESCAPED_UNICODE) . "\n";
                }
                
                echo "\n";
            }
            
        } catch (\Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * Clear logs
     */
    private static function clearLogs(): void
    {
        echo "Clearing Logs\n";
        echo "=============\n\n";
        
        try {
            Logger::clearLogs();
            echo "Logs cleared successfully\n";
            
        } catch (\Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * Show translation statistics
     */
    private static function showStats(): void
    {
        echo "Translation Statistics\n";
        echo "=====================\n\n";
        
        try {
            // Get log statistics
            $logs = Logger::getRecentLogs(1000);
            
            $totalTranslations = 0;
            $successfulTranslations = 0;
            $failedTranslations = 0;
            $recentTranslations = [];
            
            foreach ($logs as $log) {
                if (strpos($log['message'], 'Translation operation') !== false) {
                    $totalTranslations++;
                    
                    if (isset($log['context']['success']) && $log['context']['success']) {
                        $successfulTranslations++;
                    } else {
                        $failedTranslations++;
                    }
                    
                    // Keep recent translations
                    if (count($recentTranslations) < 10) {
                        $recentTranslations[] = $log;
                    }
                }
            }
            
            echo "Total Translations: {$totalTranslations}\n";
            echo "Successful: {$successfulTranslations}\n";
            echo "Failed: {$failedTranslations}\n";
            echo "Success Rate: " . ($totalTranslations > 0 ? round(($successfulTranslations / $totalTranslations) * 100, 2) : 0) . "%\n\n";
            
            echo "Recent Translations:\n";
            echo "===================\n";
            
            foreach ($recentTranslations as $log) {
                $timestamp = $log['timestamp'];
                $message = $log['message'];
                $context = $log['context'] ?? [];
                
                echo "[{$timestamp}] {$message}\n";
                if (!empty($context)) {
                    echo "  Context: " . json_encode($context, JSON_UNESCAPED_UNICODE) . "\n";
                }
                echo "\n";
            }
            
            // Show API usage if available
            $provider = ConfigManager::get('provider', 'openai');
            $apiClient = new APIClient();
            $usageStats = $apiClient->getUsageStats($provider);
            
            if (!empty($usageStats)) {
                echo "API Usage Statistics:\n";
                echo "=====================\n";
                
                if (isset($usageStats['total_usage'])) {
                    echo "Total Usage: {$usageStats['total_usage']}\n";
                }
                
                if (isset($usageStats['daily_usage'])) {
                    echo "Daily Usage:\n";
                    foreach ($usageStats['daily_usage'] as $date => $usage) {
                        echo "  {$date}: {$usage}\n";
                    }
                }
            }
            
        } catch (\Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * Get content from input
     */
    private static function getContentFromInput(): string
    {
        // Check if content is provided via stdin
        if (!defined('STDIN')) {
            define('STDIN', fopen('php://stdin', 'r'));
        }
        
        $content = '';
        while ($line = fgets(STDIN)) {
            $content .= $line;
        }
        
        return trim($content);
    }
    
    /**
     * Translate a single content item
     */
    private static function translateContentItem(array $item): void
    {
        $content = $item['content'];
        $filePath = $item['file_path'] ?? '';
        
        // Create translation request
        $request = [
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $content
                ]
            ]
        ];
        
        // Get provider from config
        $provider = ConfigManager::get('provider', 'openai');
        
        // Translate content
        $apiClient = new APIClient();
        $response = $apiClient->translate($request, $provider);
        
        // Process response
        if (isset($response['choices'][0]['message']['content'])) {
            $translatedContent = $response['choices'][0]['message']['content'];
            
            // Generate translated file
            if (!empty($filePath)) {
                $targetLang = ConfigManager::get('target_language', 'en');
                $result = FileGenerator::createTranslatedContent($filePath, $targetLang, [
                    ['original' => $content, 'translated' => $translatedContent]
                ]);
                
                if (!$result['success']) {
                    throw new \Exception('Failed to generate translated file: ' . $result['error']);
                }
            }
            
            // Log translation
            Logger::logTranslation('cli_translate', [
                'content' => $content,
                'translated_content' => $translatedContent,
                'file_path' => $filePath,
                'success' => true
            ]);
        }
    }
    
    /**
     * Get queue file path
     */
    private static function getQueueFilePath(): string
    {
        $cacheDir = defined('CACHE_DIR') ? CACHE_DIR : __DIR__ . '/../../../cache';
        return $cacheDir . '/translation_queue.json';
    }
}