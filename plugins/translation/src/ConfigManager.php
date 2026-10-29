<?php
namespace Wawoo\Plugin\Translation;

/**
 * Manages plugin configuration
 */
class ConfigManager
{
    /**
     * @var array<string, mixed>
     */
    private static array $config = [];
    
    /**
     * Initialize configuration
     */
    public static function init(): void
    {
        // Load environment variables first
        EnvLoader::load();
        
        // Merge priority (low -> high):
        //   default < plugin.json < cache settings < $GLOBALS < environment
        $defaultConfig = self::getDefaultConfig();
        $pluginConfig  = self::loadPluginConfig();
        $settings      = self::readSettings();
        $runtime       = self::readRuntimeConfig();
        $envConfig     = EnvLoader::loadAndMerge();

        self::$config = array_merge($defaultConfig, $pluginConfig, $settings, $runtime, $envConfig);
    }
    
    /**
     * Get configuration value
     */
    public static function get(string $key, $default = null)
    {
        return self::$config[$key] ?? $default;
    }
    
    /**
     * Set configuration value
     */
    public static function set(string $key, $value): void
    {
        self::$config[$key] = $value;
        self::saveSettings();
    }
    
    /**
     * Get all configuration
     */
    public static function getAll(): array
    {
        return self::$config;
    }
    
    /**
     * Get default configuration
     */
    public static function getDefaultConfig(): array
    {
        return [
            'enabled' => false,
            'target_language' => 'en',
            'source_language' => 'ja',
            'provider' => 'openai',
            'model' => 'gpt-3.5-turbo',
            'api_key' => '',
            'auto_translate' => false,
            'validate_translation' => true,
            'fallback_to_original' => true,
            'max_retries' => 3,
            'timeout' => 30,
            'batch_size' => 5,
            'excluded_fields' => ['title', 'slug', 'date', 'author'],
            'custom_prompts' => [
                'default' => 'Translate the following Japanese content to natural-sounding English. Maintain the original meaning and formatting.',
                'technical' => 'Translate technical Japanese content to English with precise terminology.',
                'casual' => 'Translate casual Japanese content to natural, conversational English.'
            ]
        ];
    }
    
    /**
     * Load plugin configuration from plugin.json
     */
    private static function loadPluginConfig(): array
    {
        $pluginJson = __DIR__ . '/../../plugin.json';
        
        if (file_exists($pluginJson)) {
            $content = file_get_contents($pluginJson);
            $data = json_decode($content, true);
            
            if (isset($data['config'])) {
                return $data['config'];
            }
        }
        
        return [];
    }
    
    /**
     * Load runtime configuration from globals
     */
    private static function readRuntimeConfig(): array
    {
        $globalKey = 'WAWOO_TRANSLATION';
        if (isset($GLOBALS[$globalKey]) && is_array($GLOBALS[$globalKey])) {
            return $GLOBALS[$globalKey];
        }
        return [];
    }
    
    /**
     * Load settings from cache
     */
    private static function readSettings(): array
    {
        $settingsFile = self::getSettingsFile();
        if (file_exists($settingsFile)) {
            $settings = json_decode((string)file_get_contents($settingsFile), true);
            if (is_array($settings)) {
                return $settings;
            }
        }
        return [];
    }
    
    /**
     * Save settings to cache
     */
    private static function saveSettings(): void
    {
        $settingsFile = self::getSettingsFile();
        
        // Ensure settings directory exists
        $settingsDir = dirname($settingsFile);
        if (!is_dir($settingsDir)) {
            mkdir($settingsDir, 0755, true);
        }
        
        // Save settings
        $content = json_encode(self::$config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        file_put_contents($settingsFile, $content);
    }
    
    /**
     * Get settings file path
     */
    private static function getSettingsFile(): string
    {
        $cacheDir = defined('CACHE_DIR') ? CACHE_DIR : __DIR__ . '/../../../cache';
        return $cacheDir . '/settings/translation.json';
    }
    
    /**
     * Validate configuration
     */
    public static function validate(): array
    {
        $errors = [];
        
        // Check required fields
        $requiredFields = ['api_key', 'provider', 'model'];
        
        foreach ($requiredFields as $field) {
            if (empty(self::$config[$field])) {
                $errors[] = "Required field '{$field}' is missing or empty";
            }
        }
        
        // Validate API key format based on provider
        $provider = self::$config['provider'];
        $apiKey = self::$config['api_key'];
        
        switch ($provider) {
            case 'openai':
                if (!preg_match('/^sk-[a-zA-Z0-9]{40,}$/', $apiKey)) {
                    $errors[] = 'OpenAI API key format is invalid';
                }
                break;
                
            case 'anthropic':
                if (!preg_match('/^sk-ant-api03-[a-zA-Z0-9_-]{40,}$/', $apiKey)) {
                    $errors[] = 'Anthropic API key format is invalid';
                }
                break;
        }
        
        // Validate language codes
        $targetLang = self::$config['target_language'];
        $sourceLang = self::$config['source_language'];
        
        if (!preg_match('/^[a-z]{2,3}(-[a-z]{2,3})?$/', $targetLang)) {
            $errors[] = 'Target language code is invalid';
        }
        
        if (!preg_match('/^[a-z]{2,3}(-[a-z]{2,3})?$/', $sourceLang)) {
            $errors[] = 'Source language code is invalid';
        }
        
        // Validate numeric values
        $numericFields = ['max_retries', 'timeout', 'batch_size'];
        
        foreach ($numericFields as $field) {
            $value = self::$config[$field];
            if (!is_numeric($value) || $value < 1) {
                $errors[] = "'{$field}' must be a positive number";
            }
        }
        
        return $errors;
    }
    
    /**
     * Reset configuration to defaults
     */
    public static function resetState(): void
    {
        self::$config = [];
    }

    public static function reset(): void
    {
        self::$config = self::getDefaultConfig();
        self::saveSettings();
    }
    
    /**
     * Export configuration
     */
    public static function export(): string
    {
        return json_encode(self::$config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    
    /**
     * Import configuration
     */
    public static function import(string $json): bool
    {
        try {
            $config = json_decode($json, true);
            
            if (!is_array($config)) {
                throw new \Exception('Invalid configuration format');
            }
            
            // Validate imported config
            $tempConfig = array_merge(self::getDefaultConfig(), $config);
            self::$config = $tempConfig;
            
            // Only validate if required fields are present
            if (!empty($config['api_key']) && isset($config['provider'])) {
                $errors = self::validate();
                if (!empty($errors)) {
                    throw new \Exception('Configuration validation failed: ' . implode(', ', $errors));
                }
            }
            
            self::saveSettings();
            return true;
            
        } catch (\Exception $e) {
            Logger::error('Configuration import failed: ' . $e->getMessage());
            return false;
        }
    }
}