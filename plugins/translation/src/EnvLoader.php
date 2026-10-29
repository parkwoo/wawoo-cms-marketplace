<?php
namespace Wawoo\Plugin\Translation;

/**
 * Environment variables loader for .env files
 */
class EnvLoader
{
    /**
     * @var array<string, mixed>
     */
    private static array $env = [];
    
    /**
     * Load environment variables from .env file and system environment
     */
    public static function load(string $path = null): void
    {
        // First, load from .env file if it exists
        if ($path === null) {
            $path = self::findEnvFile();
        }
        
        if ($path !== null && file_exists($path)) {
            $content = file_get_contents($path);
            $lines = explode("\n", $content);
            
            foreach ($lines as $line) {
                $line = trim($line);
                
                // Skip empty lines and comments
                if (empty($line) || str_starts_with($line, '#')) {
                    continue;
                }
                
                // Parse variable assignment
                if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)$/', $line, $matches)) {
                    $key = $matches[1];
                    $value = $matches[2];
                    
                    // Remove quotes if present
                    if (preg_match('/^["\'](.*)["\']$/', $value, $quoteMatches)) {
                        $value = $quoteMatches[1];
                    }
                    
                    self::$env[$key] = $value;
                    putenv("$key=$value");
                    $_ENV[$key] = $value;
                    $_SERVER[$key] = $value;
                }
            }
        }
        
        // Then, load from system environment variables
        foreach ($_ENV as $key => $value) {
            if (str_starts_with($key, 'TRANSLATION_')) {
                self::$env[$key] = $value;
            }
        }
        
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'TRANSLATION_')) {
                self::$env[$key] = $value;
            }
        }
    }
    
    /**
     * Get environment variable value
     */
    public static function get(string $key, $default = null)
    {
        return self::$env[$key] ?? $_ENV[$key] ?? $_SERVER[$key] ?? $default;
    }
    
    /**
     * Set environment variable value
     */
    public static function set(string $key, $value): void
    {
        self::$env[$key] = $value;
        putenv("$key=$value");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
    
    /**
     * Check if environment variable exists
     */
    public static function has(string $key): bool
    {
        return isset(self::$env[$key]) || isset($_ENV[$key]) || isset($_SERVER[$key]);
    }
    
    /**
     * Get all environment variables
     */
    public static function getAll(): array
    {
        return array_merge(self::$env, $_ENV, $_SERVER);
    }
    
    /**
     * Find .env file in possible locations
     */
    private static function findEnvFile(): ?string
    {
        $possiblePaths = [
            __DIR__ . '/../../../../.env',
            __DIR__ . '/../../../.env',
            __DIR__ . '/../../.env',
            __DIR__ . '/../.env',
            __DIR__ . '/.env',
            '.env'
        ];
        
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        
        return null;
    }
    
    /**
     * Validate environment variables
     */
    public static function validate(): array
    {
        $errors = [];
        
        // Check required environment variables
        $requiredVars = ['TRANSLATION_API_KEY'];
        
        foreach ($requiredVars as $var) {
            if (!self::has($var)) {
                $errors[] = "Required environment variable '{$var}' is missing";
            }
        }
        
        // Validate API key format
        $apiKey = self::get('TRANSLATION_API_KEY');
        if ($apiKey && !self::validateApiKey($apiKey)) {
            $errors[] = 'Translation API key format is invalid';
        }
        
        return $errors;
    }
    
    /**
     * Validate API key format
     */
    private static function validateApiKey(string $apiKey): bool
    {
        // Check for OpenAI format
        if (preg_match('/^sk-[a-zA-Z0-9]{48}$/', $apiKey)) {
            return true;
        }
        
        // Check for Anthropic format
        if (preg_match('/^sk-ant-api03-[a-zA-Z0-9_-]{42}$/', $apiKey)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Create .env file from template
     */
    public static function createFromTemplate(string $templatePath, string $targetPath): bool
    {
        if (!file_exists($templatePath)) {
            return false;
        }
        
        $template = file_get_contents($templatePath);
        
        // Replace template placeholders with actual values
        $template = str_replace('{{TRANSLATION_API_KEY}}', self::get('TRANSLATION_API_KEY', 'your-api-key-here'), $template);
        
        // Ensure target directory exists
        $targetDir = dirname($targetPath);
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }
        
        return file_put_contents($targetPath, $template) !== false;
    }
    
    /**
     * Load environment variables and merge with existing config
     */
    public static function loadAndMerge(): array
    {
        // Load environment variables
        self::load();
        
        // Map environment variables to config keys
        $envMappings = [
            'TRANSLATION_API_KEY' => 'api_key',
            'TRANSLATION_PROVIDER' => 'provider',
            'TRANSLATION_MODEL' => 'model',
            'TRANSLATION_TARGET_LANGUAGE' => 'target_language',
            'TRANSLATION_SOURCE_LANGUAGE' => 'source_language',
            'TRANSLATION_AUTO_TRANSLATE' => 'auto_translate',
            'TRANSLATION_VALIDATE_TRANSLATION' => 'validate_translation',
            'TRANSLATION_FALLBACK_TO_ORIGINAL' => 'fallback_to_original',
            'TRANSLATION_MAX_RETRIES' => 'max_retries',
            'TRANSLATION_TIMEOUT' => 'timeout',
            'TRANSLATION_BATCH_SIZE' => 'batch_size',
        ];
        
        $mergedConfig = [];
        
        foreach ($envMappings as $envKey => $configKey) {
            $value = self::get($envKey);
            
            if ($value !== null) {
                // Convert string values to appropriate types
                if ($configKey === 'auto_translate' || $configKey === 'validate_translation' || $configKey === 'fallback_to_original') {
                    $mergedConfig[$configKey] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                } elseif (in_array($configKey, ['max_retries', 'timeout', 'batch_size'])) {
                    $mergedConfig[$configKey] = (int)$value;
                } else {
                    $mergedConfig[$configKey] = $value;
                }
            }
        }
        
        return $mergedConfig;
    }
}