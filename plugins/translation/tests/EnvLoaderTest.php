<?php
namespace Wawoo\Plugin\Translation\Tests;

use PHPUnit\Framework\TestCase;
use Wawoo\Plugin\Translation\EnvLoader;

/**
 * Test environment variable loading
 */
class EnvLoaderTest extends TestCase
{
    private $testEnvFile;
    
    protected function setUp(): void
    {
        // Create a temporary .env file for testing
        $this->testEnvFile = sys_get_temp_dir() . '/test.env';
        
        $envContent = <<<'ENV'
# Test environment variables
TRANSLATION_API_KEY=test-api-key-1234567890123456789012345678901234567890
TRANSLATION_PROVIDER=openai
TRANSLATION_MODEL=gpt-3.5-turbo
TRANSLATION_TARGET_LANGUAGE=en
TRANSLATION_SOURCE_LANGUAGE=ja
TRANSLATION_AUTO_TRANSLATE=true
TRANSLATION_VALIDATE_TRANSLATION=false
TRANSLATION_MAX_RETRIES=5
TRANSLATION_TIMEOUT=60
TRANSLATION_BATCH_SIZE=10

# Comment line
EMPTY_VAR=
QUOTED_VAR="quoted value"
SINGLE_QUOTED_VAR='single quoted'
ENV;
        
        file_put_contents($this->testEnvFile, $envContent);
    }
    
    protected function tearDown(): void
    {
        // Clean up test file
        if (file_exists($this->testEnvFile)) {
            unlink($this->testEnvFile);
        }
    }
    
    public function testLoadEnvironmentFile(): void
    {
        // Load environment file
        EnvLoader::load($this->testEnvFile);
        
        // Test loaded values
        $this->assertEquals('test-api-key-1234567890123456789012345678901234567890', EnvLoader::get('TRANSLATION_API_KEY'));
        $this->assertEquals('openai', EnvLoader::get('TRANSLATION_PROVIDER'));
        $this->assertEquals('gpt-3.5-turbo', EnvLoader::get('TRANSLATION_MODEL'));
        $this->assertEquals('en', EnvLoader::get('TRANSLATION_TARGET_LANGUAGE'));
        $this->assertEquals('ja', EnvLoader::get('TRANSLATION_SOURCE_LANGUAGE'));
        $this->assertEquals(true, EnvLoader::get('TRANSLATION_AUTO_TRANSLATE'));
        $this->assertEquals(false, EnvLoader::get('TRANSLATION_VALIDATE_TRANSLATION'));
        $this->assertEquals(5, EnvLoader::get('TRANSLATION_MAX_RETRIES'));
        $this->assertEquals(60, EnvLoader::get('TRANSLATION_TIMEOUT'));
        $this->assertEquals(10, EnvLoader::get('TRANSLATION_BATCH_SIZE'));
    }
    
    public function testGetEnvironmentVariable(): void
    {
        EnvLoader::load($this->testEnvFile);
        
        // Test existing variable
        $this->assertEquals('test-api-key-1234567890123456789012345678901234567890', EnvLoader::get('TRANSLATION_API_KEY'));
        
        // Test non-existing variable with default
        $this->assertEquals('default', EnvLoader::get('NON_EXISTING_VAR', 'default'));
    }
    
    public function testSetEnvironmentVariable(): void
    {
        EnvLoader::set('TEST_VAR', 'test_value');
        
        $this->assertEquals('test_value', EnvLoader::get('TEST_VAR'));
    }
    
    public function testHasEnvironmentVariable(): void
    {
        EnvLoader::load($this->testEnvFile);
        
        $this->assertTrue(EnvLoader::has('TRANSLATION_API_KEY'));
        $this->assertFalse(EnvLoader::has('NON_EXISTING_VAR'));
    }
    
    public function testGetAllEnvironmentVariables(): void
    {
        EnvLoader::load($this->testEnvFile);
        
        $all = EnvLoader::getAll();
        
        $this->assertArrayHasKey('TRANSLATION_API_KEY', $all);
        $this->assertArrayHasKey('TRANSLATION_PROVIDER', $all);
        $this->assertArrayHasKey('TRANSLATION_MODEL', $all);
    }
    
    public function testValidateEnvironmentVariables(): void
    {
        EnvLoader::load($this->testEnvFile);
        
        $errors = EnvLoader::validate();
        
        // Should pass validation with test data
        $this->assertEmpty($errors);
    }
    
    public function testValidateApiKey(): void
    {
        // Test OpenAI format
        $this->assertTrue(EnvLoader::validateApiKey('sk-1234567890123456789012345678901234567890'));
        
        // Test Anthropic format
        $this->assertTrue(EnvLoader::validateApiKey('sk-ant-api03-1234567890123456789012345678901234567890'));
        
        // Test invalid format
        $this->assertFalse(EnvLoader::validateApiKey('invalid-key'));
        $this->assertFalse(EnvLoader::validateApiKey(''));
    }
    
    public function testLoadAndMerge(): void
    {
        EnvLoader::load($this->testEnvFile);
        
        $merged = EnvLoader::loadAndMerge();
        
        $this->assertEquals('test-api-key-1234567890123456789012345678901234567890', $merged['api_key']);
        $this->assertEquals('openai', $merged['provider']);
        $this->assertEquals('gpt-3.5-turbo', $merged['model']);
        $this->assertEquals('en', $merged['target_language']);
        $this->assertEquals('ja', $merged['source_language']);
        $this->assertEquals(true, $merged['auto_translate']);
        $this->assertEquals(false, $merged['validate_translation']);
        $this->assertEquals(5, $merged['max_retries']);
        $this->assertEquals(60, $merged['timeout']);
        $this->assertEquals(10, $merged['batch_size']);
    }
    
    public function testTypeConversion(): void
    {
        EnvLoader::load($this->testEnvFile);
        
        $merged = EnvLoader::loadAndMerge();
        
        // Test boolean conversion
        $this->assertIsBool($merged['auto_translate']);
        $this->assertIsBool($merged['validate_translation']);
        
        // Test integer conversion
        $this->assertIsInt($merged['max_retries']);
        $this->assertIsInt($merged['timeout']);
        $this->assertIsInt($merged['batch_size']);
    }
}