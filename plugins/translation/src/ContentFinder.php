<?php
namespace Wawoo\Plugin\Translation;

/**
 * Finds content that needs translation and manages the translation queue
 */
class ContentFinder
{
    /**
     * @var array<string>
     */
    private static array $translatedFiles = [];
    
    /**
     * Check if content needs translation
     */
    public function handle(array &$content): void
    {
        if (!$this->isTranslationEnabled()) {
            return;
        }
        
        $filePath = $content['file_path'] ?? '';
        $sourceLang = ConfigManager::get('source_language', 'ja');
        $targetLang = ConfigManager::get('target_language', 'en');
        
        // Skip if already translated
        if (in_array($filePath, self::$translatedFiles)) {
            return;
        }
        
        // Check if content needs translation
        if ($this->needsTranslation($content, $sourceLang, $targetLang)) {
            $this->queueForTranslation($content);
            self::$translatedFiles[] = $filePath;
        }
    }
    
    private function isTranslationEnabled(): bool
    {
        return ConfigManager::get('enabled', false) && 
               ConfigManager::get('auto_translate', false);
    }
    
    private function needsTranslation(array $content, string $sourceLang, string $targetLang): bool
    {
        // Check if content is in source language
        if (!isset($content['language']) || $content['language'] !== $sourceLang) {
            return false;
        }
        
        // Check if already has target language version
        if (isset($content['translated_' . $targetLang])) {
            return false;
        }
        
        // Check if content has translatable fields
        $excludedFields = ConfigManager::get('excluded_fields', []);
        foreach ($content as $key => $value) {
            if (is_string($value) && !in_array($key, $excludedFields)) {
                if ($this->containsJapanese($value)) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    private function queueForTranslation(array &$content): void
    {
        $translationQueue = $this->getTranslationQueue();
        $translationQueue[] = [
            'file_path' => $content['file_path'] ?? '',
            'content' => $content,
            'timestamp' => time()
        ];
        
        $this->saveTranslationQueue($translationQueue);
    }
    
    private function getTranslationQueue(): array
    {
        $queueFile = $this->getQueueFilePath();
        if (file_exists($queueFile)) {
            $data = file_get_contents($queueFile);
            return json_decode($data, true) ?? [];
        }
        return [];
    }
    
    private function saveTranslationQueue(array $queue): void
    {
        $queueFile = $this->getQueueFilePath();
        $data = json_encode($queue, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        file_put_contents($queueFile, $data);
    }
    
    private function getQueueFilePath(): string
    {
        $cacheDir = defined('CACHE_DIR') ? CACHE_DIR : __DIR__ . '/../../../cache';
        return $cacheDir . '/translation_queue.json';
    }
    
    /**
     * Check if text contains Japanese characters
     */
    public static function containsJapanese(?string $text): bool
    {
        if ($text === null) {
            return false;
        }
        return preg_match('/[\x{3040}-\x{309F}\x{30A0}-\x{30FF}\x{4E00}-\x{9FFF}]/u', $text) > 0;
    }
}