<?php
namespace Wawoo\Plugin\Translation;

/**
 * Handles routing for translation plugin
 */
class Router
{
    /**
     * Handle core.request hook
     */
    public function handle(): void
    {
        $path = $_SERVER['REQUEST_URI'] ?? '';
        
        // Handle translation-specific routes
        if (strpos($path, '/admin/translation') === 0) {
            $this->handleAdminRoutes();
        }
        
        // Handle API routes
        if (strpos($path, '/api/translation') === 0) {
            $this->handleApiRoutes();
        }
    }
    
    /**
     * Handle admin routes
     */
    private function handleAdminRoutes(): void
    {
        // Admin routes are handled by AdminPanel class
        // This is just a placeholder for the routing system
        return;
    }
    
    /**
     * Handle API routes
     */
    private function handleApiRoutes(): void
    {
        $action = $_GET['action'] ?? '';
        
        switch ($action) {
            case 'translate':
                $this->handleTranslateRequest();
                break;
                
            case 'validate':
                $this->handleValidateRequest();
                break;
                
            case 'queue':
                $this->handleQueueRequest();
                break;
                
            default:
                $this->sendError('Invalid API action');
        }
    }
    
    /**
     * Handle translate request
     */
    private function handleTranslateRequest(): void
    {
        try {
            $content = $_POST['content'] ?? '';
            $sourceLang = $_POST['source_language'] ?? 'ja';
            $targetLang = $_POST['target_language'] ?? 'en';
            
            if (empty($content)) {
                throw new \Exception('Content is required');
            }
            
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
            
            // Validate translation if enabled
            $translatedContent = '';
            if (isset($response['choices'][0]['message']['content'])) {
                $translatedContent = $response['choices'][0]['message']['content'];
                
                if (ConfigManager::get('validate_translation', true)) {
                    $validation = Validator::validate($content, $translatedContent);
                    
                    if (!$validation['valid']) {
                        Logger::warning('Translation validation failed', [
                            'content' => $content,
                            'translation' => $translatedContent,
                            'validation' => $validation
                        ]);
                    }
                }
            }
            
            // Log translation
            Logger::logTranslation('api_translate', [
                'content' => $content,
                'translated_content' => $translatedContent,
                'source_language' => $sourceLang,
                'target_language' => $targetLang,
                'success' => !empty($translatedContent)
            ]);
            
            $this->sendJson([
                'success' => true,
                'translation' => $translatedContent,
                'validation' => $validation ?? null
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Translation request failed', [
                'error' => $e->getMessage()
            ]);
            
            $this->sendError($e->getMessage());
        }
    }
    
    /**
     * Handle validate request
     */
    private function handleValidateRequest(): void
    {
        try {
            $original = $_POST['original'] ?? '';
            $translated = $_POST['translated'] ?? '';
            
            if (empty($original) || empty($translated)) {
                throw new \Exception('Both original and translated content are required');
            }
            
            // Validate translation
            $result = Validator::validate($original, $translated);
            
            // Log validation
            Logger::logValidation(md5($original), $result);
            
            $this->sendJson([
                'success' => true,
                'validation' => $result
            ]);
            
        } catch (\Exception $e) {
            $this->sendError($e->getMessage());
        }
    }
    
    /**
     * Handle queue request
     */
    private function handleQueueRequest(): void
    {
        try {
            $action = $_POST['action'] ?? '';
            
            switch ($action) {
                case 'get':
                    $queue = $this->getTranslationQueue();
                    $this->sendJson([
                        'success' => true,
                        'queue' => $queue
                    ]);
                    break;
                    
                case 'clear':
                    $this->clearTranslationQueue();
                    $this->sendJson([
                        'success' => true,
                        'message' => 'Queue cleared'
                    ]);
                    break;
                    
                case 'process':
                    $this->processTranslationQueue();
                    $this->sendJson([
                        'success' => true,
                        'message' => 'Processing started'
                    ]);
                    break;
                    
                default:
                    throw new \Exception('Invalid queue action');
            }
            
        } catch (\Exception $e) {
            $this->sendError($e->getMessage());
        }
    }
    
    /**
     * Get translation queue
     */
    private function getTranslationQueue(): array
    {
        $queueFile = $this->getQueueFilePath();
        
        if (file_exists($queueFile)) {
            $data = file_get_contents($queueFile);
            return json_decode($data, true) ?? [];
        }
        
        return [];
    }
    
    /**
     * Clear translation queue
     */
    private function clearTranslationQueue(): void
    {
        $queueFile = $this->getQueueFilePath();
        
        if (file_exists($queueFile)) {
            unlink($queueFile);
        }
    }
    
    /**
     * Process translation queue
     */
    private function processTranslationQueue(): void
    {
        $queue = $this->getTranslationQueue();
        
        foreach ($queue as $item) {
            try {
                $this->translateContentItem($item);
            } catch (\Exception $e) {
                Logger::error('Failed to process queue item', [
                    'error' => $e->getMessage(),
                    'item' => $item
                ]);
            }
        }
        
        // Clear queue after processing
        $this->clearTranslationQueue();
    }
    
    /**
     * Translate a single content item
     */
    private function translateContentItem(array $item): void
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
            Logger::logTranslation('queue_translate', [
                'content' => $content,
                'translated_content' => $translatedContent,
                'file_path' => $filePath,
                'success' => true
            ]);
        }
    }
    
    /**
     * Send JSON response
     */
    private function sendJson(array $data): void
    {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
    
    /**
     * Send error response
     */
    private function sendError(string $message): void
    {
        $this->sendJson([
            'success' => false,
            'error' => $message
        ]);
    }
    
    /**
     * Get queue file path
     */
    private function getQueueFilePath(): string
    {
        $cacheDir = defined('CACHE_DIR') ? CACHE_DIR : __DIR__ . '/../../../cache';
        return $cacheDir . '/translation_queue.json';
    }
}