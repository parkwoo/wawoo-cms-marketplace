<?php
namespace Wawoo\Plugin\Translation\Tests;

use PHPUnit\Framework\TestCase;
use Wawoo\Plugin\Translation\ConfigManager;
use Wawoo\Plugin\Translation\Validator;
use Wawoo\Plugin\Translation\Logger;
use Wawoo\Plugin\Translation\FileGenerator;
use Wawoo\Plugin\Translation\APIClient;
use Wawoo\Plugin\Translation\ContentFinder;
use Wawoo\Plugin\Translation\Translator;
use Wawoo\Plugin\Translation\EnvLoader;

/**
 * Translation plugin unit tests
 */
class TranslationPluginTest extends TestCase
{
    protected $testDir;
    
    protected function setUp(): void
    {
        // Initialize configuration for tests
        ConfigManager::init();
        
        // Create temporary directory for tests
        $this->testDir = __DIR__ . '/_tmp';
        if (!is_dir($this->testDir)) {
            mkdir($this->testDir, 0755, true);
        }
    }
    
    protected function tearDown(): void
    {
        // Clean up temporary files
        if (is_dir($this->testDir)) {
            $files = glob($this->testDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            if (is_dir($this->testDir)) {
                @rmdir($this->testDir);
            }
        }
    }
    
    /**
     * Test configuration management
     */
    public function testConfiguration(): void
    {
        // Test default configuration
        $defaultConfig = ConfigManager::getDefaultConfig();
        $this->assertArrayHasKey('enabled', $defaultConfig);
        $this->assertArrayHasKey('target_language', $defaultConfig);
        $this->assertTrue(is_bool($defaultConfig['enabled']));
        $this->assertEquals('en', $defaultConfig['target_language']);
        
        // Test configuration validation
        $errors = ConfigManager::validate();
        $this->assertIsArray($errors);
        
        // Test configuration import/export
        $exported = ConfigManager::export();
        $this->assertIsString($exported);
        $this->assertJson($exported);
        
        $importSuccess = ConfigManager::import($exported);
        $this->assertTrue($importSuccess);
    }
    
    /**
     * Test translation validator
     */
    public function testValidator(): void
    {
        // Test basic validation
        $original = 'こんにちは世界';
        $translated = 'Hello World';
        
        $validation = Validator::validate($original, $translated);
        
        $this->assertTrue($validation['valid']);
        $this->assertArrayHasKey('score', $validation);
        $this->assertIsNumeric($validation['score']);
        $this->assertGreaterThanOrEqual(0, $validation['score']);
        $this->assertLessThanOrEqual(100, $validation['score']);
        
        // Test validation with invalid translation
        $invalidTranslation = 'こんにちは世界'; // Same as original
        $invalidValidation = Validator::validate($original, $invalidTranslation);
        
        $this->assertFalse($invalidValidation['valid']);
        
        // Test validation scoring
        $this->assertGreaterThan(70, $validation['score']);
    }
    
    /**
     * Test logger
     */
    public function testLogger(): void
    {
        // Test logging
        Logger::info('Test message', ['test' => true]);
        
        // Test log retrieval
        $logs = Logger::getRecentLogs(1);
        $this->assertIsArray($logs);
        $this->assertNotEmpty($logs);
        
        // Test log level filtering
        $errorLogs = Logger::getLogsByLevel('ERROR');
        $infoLogs = Logger::getLogsByLevel('INFO');
        
        $this->assertIsArray($errorLogs);
        $this->assertIsArray($infoLogs);
        
        // Test log clearing
        Logger::clearLogs();
        $clearedLogs = Logger::getRecentLogs(1);
        $this->assertEmpty($clearedLogs);
    }
    
    /**
     * Test file generator
     */
    public function testFileGenerator(): void
    {
        // Create test file
        $testFile = $this->testDir . '/test.md';
        $testContent = "---\ntitle: Test Post\ndate: 2024-01-01\n---\n\nこれはテスト投稿です。";
        file_put_contents($testFile, $testContent);
        
        // Test file generation
        $translatedContent = [
            ['original' => 'これはテスト投稿です。', 'translated' => 'This is a test post.']
        ];
        
        $targetLang = 'en';
        $resultPath = FileGenerator::generateTranslatedFile($testFile, $targetLang, $translatedContent);
        
        $this->assertFileExists($resultPath);
        
        // Test file content
        $resultContent = file_get_contents($resultPath);
        $this->assertStringContainsString('This is a test post.', $resultContent);
        
        // Test metadata update
        $metadataFile = FileGenerator::getMetadataPath($testFile);
        $this->assertFileExists($metadataFile);
        
        // Cleanup
        unlink($testFile);
        unlink($resultPath);
        if (file_exists($metadataFile)) {
            unlink($metadataFile);
        }
    }
    
    /**
     * Test API client
     */
    public function testApiClient(): void
    {
        // Test supported providers
        $supported = APIClient::getSupportedProviders();
        $this->assertContains('openai', $supported);
        $this->assertContains('anthropic', $supported);
        
        // Test provider checking
        $this->assertTrue(APIClient::isProviderSupported('openai'));
        $this->assertFalse(APIClient::isProviderSupported('invalid'));
        
        // Test connection test (will fail without API key, but should not throw exception)
        $this->assertFalse(APIClient::testConnection('openai'));
    }
    
    /**
     * Test content finder
     */
    public function testContentFinder(): void
    {
        // Test Japanese detection
        $japaneseText = 'これは日本語のテキストです。';
        $englishText = 'This is English text.';
        
        $this->assertTrue(ContentFinder::containsJapanese($japaneseText));
        $this->assertFalse(ContentFinder::containsJapanese($englishText));
        
        // Test empty string
        $this->assertFalse(ContentFinder::containsJapanese(''));
        $this->assertFalse(ContentFinder::containsJapanese(null));
    }
    
    /**
     * Test translator
     */
    public function testTranslator(): void
    {
        $translator = new Translator();
        
        // Test HTML content extraction
        $html = '<html><body><h1>こんにちは</h1><p>これはテストです。</p></body></html>';
        $content = $translator->extractContent($html);
        
        // This will be empty without proper configuration
        $this->assertIsArray($content);
        
        // Test shouldTranslate method
        ConfigManager::set('enabled', false);
        $this->assertFalse($translator->shouldTranslate());
    }
    
    /**
     * Test translation batch processing
     */
    public function testTranslationBatchProcessing(): void
    {
        ConfigManager::set('enabled', true);
        ConfigManager::set('batch_size', 2);
        
        $translator = new Translator();
        
        // Test batch processing
        $content = [
            ['text' => 'こんにちは', 'context' => 'heading', 'type' => 'heading'],
            ['text' => '世界', 'context' => 'paragraph', 'type' => 'paragraph'],
            ['text' => 'ありがとう', 'context' => 'paragraph', 'type' => 'paragraph']
        ];
        
        // This will fail without API key, but should not throw exception
        try {
            $translatedContent = $translator->translateContent($content);
            // If it succeeds, check structure
            $this->assertIsArray($translatedContent);
        } catch (\Exception $e) {
            // Expected without API key
            $this->assertStringContainsString('API key', $e->getMessage());
        }
    }
    
    /**
     * Test error handling
     */
    public function testErrorHandling(): void
    {
        // Test configuration validation with invalid data
        ConfigManager::set('api_key', 'invalid-key');
        ConfigManager::set('provider', 'openai');
        
        $errors = ConfigManager::validate();
        $this->assertNotEmpty($errors);
        
        // Test logger error handling
        Logger::error('Test error message');
        $errorLogs = Logger::getLogsByLevel('ERROR');
        $this->assertNotEmpty($errorLogs);
        
        // Test file generator with invalid input
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('file_get_contents');
        FileGenerator::generateTranslatedFile('/nonexistent/file.md', 'en', []);
    }
    
    /**
     * Test configuration reset
     */
    public function testConfigurationReset(): void
    {
        // Modify configuration
        ConfigManager::set('enabled', true);
        ConfigManager::set('target_language', 'fr');
        
        // Reset to defaults
        ConfigManager::reset();
        
        // Check if reset to defaults
        $this->assertFalse(ConfigManager::get('enabled'));
        $this->assertEquals('en', ConfigManager::get('target_language'));
    }
    
    /**
     * Test configuration merge priority
     */
    public function testConfigurationMergePriority(): void
    {
        // Test runtime config override
        $GLOBALS['WAWOO_TRANSLATION'] = ['enabled' => true, 'target_language' => 'fr'];
        
        // Reinitialize to load runtime config
        ConfigManager::init();
        
        $this->assertTrue(ConfigManager::get('enabled'));
        $this->assertEquals('fr', ConfigManager::get('target_language'));
        
        // Clean up
        unset($GLOBALS['WAWOO_TRANSLATION']);
        // Reinitialize to clean up
        ConfigManager::init();
        ConfigManager::reset();
    }
    
    /**
     * Test environment variable integration
     */
    public function testEnvironmentVariableIntegration(): void
    {
        // Set environment variables
        putenv('TRANSLATION_API_KEY=test-env-api-key');
        putenv('TRANSLATION_PROVIDER=anthropic');
        putenv('TRANSLATION_TARGET_LANGUAGE=fr');
        putenv('TRANSLATION_AUTO_TRANSLATE=true');
        putenv('TRANSLATION_MAX_RETRIES=5');
        
        // Reinitialize to load environment variables
        ConfigManager::init();
        
        // Test environment variable override
        $this->assertEquals('test-env-api-key', ConfigManager::get('api_key'));
        $this->assertEquals('anthropic', ConfigManager::get('provider'));
        $this->assertEquals('fr', ConfigManager::get('target_language'));
        $this->assertTrue(ConfigManager::get('auto_translate'));
        $this->assertEquals(5, ConfigManager::get('max_retries'));
        
        // Clean up
        putenv('TRANSLATION_API_KEY');
        putenv('TRANSLATION_PROVIDER');
        putenv('TRANSLATION_TARGET_LANGUAGE');
        putenv('TRANSLATION_AUTO_TRANSLATE');
        putenv('TRANSLATION_MAX_RETRIES');
        
        // Reinitialize to clean up
        ConfigManager::init();
        ConfigManager::reset();
    }
    
    /**
     * Test environment variable priority
     */
    public function testEnvironmentVariablePriority(): void
    {
        // Set environment variable
        putenv('TRANSLATION_TARGET_LANGUAGE=de');
        
        // Set runtime config
        $GLOBALS['WAWOO_TRANSLATION'] = ['target_language' => 'es'];
        
        // Reinitialize to load both
        ConfigManager::init();
        
        // Environment should override runtime
        $this->assertEquals('de', ConfigManager::get('target_language'));
        
        // Clean up
        putenv('TRANSLATION_TARGET_LANGUAGE');
        unset($GLOBALS['WAWOO_TRANSLATION']);
        
        // Reinitialize to clean up
        ConfigManager::init();
        ConfigManager::reset();
    }
    
    /**
     * Test .env file loading
     */
    public function testEnvFileLoading(): void
    {
        // Create test .env file
        $testEnv = $this->testDir . '/test.env';
        $envContent = "TRANSLATION_API_KEY=file-api-key\nTRANSLATION_PROVIDER=openai\nTRANSLATION_TARGET_LANGUAGE=it";
        file_put_contents($testEnv, $envContent);
        
        // Load specific .env file
        EnvLoader::load($testEnv);
        
        // Test loaded values
        $this->assertEquals('file-api-key', EnvLoader::get('TRANSLATION_API_KEY'));
        $this->assertEquals('openai', EnvLoader::get('TRANSLATION_PROVIDER'));
        $this->assertEquals('it', EnvLoader::get('TRANSLATION_TARGET_LANGUAGE'));
        
        // Test merge with config
        $merged = EnvLoader::loadAndMerge();
        $this->assertEquals('file-api-key', $merged['api_key']);
        $this->assertEquals('openai', $merged['provider']);
        $this->assertEquals('it', $merged['target_language']);
        
        // Clean up
        unlink($testEnv);
    }
}