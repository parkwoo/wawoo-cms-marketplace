<?php
namespace Wawoo\Plugin\Translation;

/**
 * Logging system for translation operations
 */
class Logger
{
    /**
     * @var string
     */
    private static string $logDir = '';
    
    /**
     * @var string
     */
    private static string $logFile = '';
    
    /**
     * Initialize logger
     */
    public static function init(): void
    {
        $cacheDir = defined('CACHE_DIR') ? CACHE_DIR : __DIR__ . '/../../../cache';
        self::$logDir = $cacheDir . '/logs';
        self::$logFile = self::$logDir . '/translation.log';
        
        // Ensure log directory exists
        if (!is_dir(self::$logDir)) {
            mkdir(self::$logDir, 0755, true);
        }
    }
    
    /**
     * Log info message
     */
    public static function info(string $message, array $context = []): void
    {
        self::log('INFO', $message, $context);
    }
    
    /**
     * Log warning message
     */
    public static function warning(string $message, array $context = []): void
    {
        self::log('WARNING', $message, $context);
    }
    
    /**
     * Log error message
     */
    public static function error(string $message, array $context = []): void
    {
        self::log('ERROR', $message, $context);
    }
    
    /**
     * Log debug message
     */
    public static function debug(string $message, array $context = []): void
    {
        self::log('DEBUG', $message, $context);
    }
    
    /**
     * Log message
     */
    private static function log(string $level, string $message, array $context = []): void
    {
        if (!self::$logDir) {
            self::init();
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = [
            'timestamp' => $timestamp,
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true)
        ];
        
        $logLine = json_encode($logEntry, JSON_UNESCAPED_UNICODE) . PHP_EOL;
        
        // Write to log file
        file_put_contents(self::$logFile, $logLine, FILE_APPEND | LOCK_EX);
        
        // Also output to console if in debug mode
        if (ConfigManager::get('debug', false)) {
            echo "[{$timestamp}] {$level}: {$message}" . PHP_EOL;
        }
    }
    
    /**
     * Get recent log entries
     */
    public static function getRecentLogs(int $limit = 100): array
    {
        if (!file_exists(self::$logFile)) {
            return [];
        }
        
        $lines = file(self::$logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        if (!$lines) {
            return [];
        }
        
        // Get last N lines
        $recentLines = array_slice($lines, -$limit);
        
        $logs = [];
        foreach ($recentLines as $line) {
            $logEntry = json_decode($line, true);
            if ($logEntry) {
                $logs[] = $logEntry;
            }
        }
        
        return $logs;
    }
    
    /**
     * Get logs by level
     */
    public static function getLogsByLevel(string $level, int $limit = 50): array
    {
        $allLogs = self::getRecentLogs(1000);
        $filteredLogs = [];
        
        foreach ($allLogs as $log) {
            if (isset($log['level']) && $log['level'] === $level) {
                $filteredLogs[] = $log;
            }
            
            if (count($filteredLogs) >= $limit) {
                break;
            }
        }
        
        return $filteredLogs;
    }
    
    /**
     * Clear log file
     */
    public static function clearLogs(): void
    {
        if (file_exists(self::$logFile)) {
            file_put_contents(self::$logFile, '');
        }
    }
    
    /**
     * Get log file size
     */
    public static function getLogFileSize(): int
    {
        if (file_exists(self::$logFile)) {
            return filesize(self::$logFile);
        }
        
        return 0;
    }
    
    /**
     * Rotate log files
     */
    public static function rotateLogs(): void
    {
        $maxSize = ConfigManager::get('max_log_size', 10485760); // 10MB default
        
        if (self::getLogFileSize() > $maxSize) {
            $timestamp = date('Y-m-d_H-i-s');
            $backupFile = self::$logDir . '/translation_' . $timestamp . '.log';
            
            // Rename current log file
            rename(self::$logFile, $backupFile);
            
            // Create new log file
            file_put_contents(self::$logFile, '');
            
            Logger::info('Log file rotated', [
                'original_file' => self::$logFile,
                'backup_file' => $backupFile,
                'original_size' => self::getLogFileSize()
            ]);
        }
    }
    
    /**
     * Log translation operation
     */
    public static function logTranslation(string $operation, array $data): void
    {
        $context = [
            'operation' => $operation,
            'provider' => ConfigManager::get('provider'),
            'model' => ConfigManager::get('model'),
            'target_language' => ConfigManager::get('target_language'),
            'source_language' => ConfigManager::get('source_language')
        ];
        
        if (isset($data['file_path'])) {
            $context['file_path'] = $data['file_path'];
        }
        
        if (isset($data['word_count'])) {
            $context['word_count'] = $data['word_count'];
        }
        
        if (isset($data['translation_time'])) {
            $context['translation_time'] = $data['translation_time'];
        }
        
        if (isset($data['success'])) {
            $context['success'] = $data['success'];
        }
        
        if (isset($data['error'])) {
            $context['error'] = $data['error'];
        }
        
        self::info('Translation operation: ' . $operation, $context);
    }
    
    /**
     * Log API request
     */
    public static function logApiRequest(string $endpoint, array $request, array $response): void
    {
        $context = [
            'endpoint' => $endpoint,
            'request' => $request,
            'response' => $response,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        self::debug('API request made', $context);
    }
    
    /**
     * Log validation result
     */
    public static function logValidation(string $contentHash, array $result): void
    {
        $context = [
            'content_hash' => $contentHash,
            'valid' => $result['valid'],
            'score' => $result['score'],
            'issues' => $result['issues'],
            'checks' => $result['checks']
        ];
        
        $level = $result['valid'] ? 'INFO' : 'WARNING';
        self::$level('Validation result: ' . ($result['valid'] ? 'passed' : 'failed'), $context);
    }
}