<?php
namespace Wawoo\Plugin\Translation;

/**
 * API client for external LLM services
 */
class APIClient
{
    /**
     * @var array<string>
     */
    private static array $supportedProviders = ['openai', 'anthropic'];
    
    /**
     * Translate content using external API
     */
    public function translate(array $request, string $provider = 'openai'): array
    {
        switch ($provider) {
            case 'openai':
                return $this->translateWithOpenAI($request);
                
            case 'anthropic':
                return $this->translateWithAnthropic($request);
                
            default:
                throw new \Exception("Unsupported provider: {$provider}");
        }
    }
    
    /**
     * Translate with OpenAI API
     */
    private function translateWithOpenAI(array $request): array
    {
        $apiKey = ConfigManager::get('api_key');
        $model = ConfigManager::get('model', 'gpt-3.5-turbo');
        $timeout = ConfigManager::get('timeout', 30);
        $maxRetries = ConfigManager::get('max_retries', 3);
        
        if (empty($apiKey)) {
            throw new \Exception('OpenAI API key is not configured');
        }
        
        $url = 'https://api.openai.com/v1/chat/completions';
        
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ];
        
        $request['model'] = $model;
        $request['max_tokens'] = 2000;
        $request['temperature'] = 0.3;
        
        $body = json_encode($request);
        
        $response = $this->makeRequest($url, $headers, $body, $timeout, $maxRetries);
        
        return $this->processOpenAIResponse($response);
    }
    
    /**
     * Translate with Anthropic Claude API
     */
    private function translateWithAnthropic(array $request): array
    {
        $apiKey = ConfigManager::get('api_key');
        $model = ConfigManager::get('model', 'claude-3-haiku-20240307');
        $timeout = ConfigManager::get('timeout', 30);
        $maxRetries = ConfigManager::get('max_retries', 3);
        
        if (empty($apiKey)) {
            throw new \Exception('Anthropic API key is not configured');
        }
        
        $url = 'https://api.anthropic.com/v1/messages';
        
        $headers = [
            'Content-Type: application/json',
            'x-api-key: ' . $apiKey,
            'anthropic-version: 2023-06-01'
        ];
        
        $anthropicRequest = [
            'model' => $model,
            'max_tokens' => 2000,
            'temperature' => 0.3,
            'messages' => $request['messages']
        ];
        
        $body = json_encode($anthropicRequest);
        
        $response = $this->makeRequest($url, $headers, $body, $timeout, $maxRetries);
        
        return $this->processAnthropicResponse($response);
    }
    
    /**
     * Make HTTP request with retry logic
     */
    private function makeRequest(string $url, array $headers, string $body, int $timeout, int $maxRetries): array
    {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ]);
        
        $lastError = null;
        $response = null;
        
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $response = curl_exec($ch);
                
                if ($response === false) {
                    $lastError = curl_error($ch);
                    throw new \Exception('CURL error: ' . $lastError);
                }
                
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                
                if ($httpCode === 200) {
                    break;
                }
                
                $errorData = json_decode($response, true);
                $errorMessage = $errorData['error']['message'] ?? 'HTTP error: ' . $httpCode;
                
                if ($attempt < $maxRetries) {
                    sleep(2 ** $attempt); // Exponential backoff
                }
                
                throw new \Exception('API error: ' . $errorMessage);
                
            } catch (\Exception $e) {
                $lastError = $e->getMessage();
                
                if ($attempt < $maxRetries) {
                    Logger::warning("Translation attempt {$attempt} failed: {$lastError}");
                    sleep(2 ** $attempt);
                }
            }
        }
        
        curl_close($ch);
        
        if ($response === false) {
            throw new \Exception('Translation failed after ' . $maxRetries . ' attempts: ' . $lastError);
        }
        
        return json_decode($response, true) ?? [];
    }
    
    /**
     * Process OpenAI response
     */
    private function processOpenAIResponse(array $response): array
    {
        if (!isset($response['choices']) || !is_array($response['choices'])) {
            throw new \Exception('Invalid OpenAI response format');
        }
        
        return $response;
    }
    
    /**
     * Process Anthropic response
     */
    private function processAnthropicResponse(array $response): array
    {
        if (!isset($response['content']) || !is_array($response['content'])) {
            throw new \Exception('Invalid Anthropic response format');
        }
        
        // Convert Anthropic format to OpenAI-like format
        $choices = [];
        
        foreach ($response['content'] as $content) {
            $choices[] = [
                'message' => [
                    'content' => $content['text']
                ]
            ];
        }
        
        return ['choices' => $choices];
    }
    
    /**
     * Test API connection
     */
    public static function testConnection(string $provider = 'openai'): bool
    {
        try {
            $testRequest = [
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => 'Hello, please respond with "Connection test successful"'
                    ]
                ]
            ];
            
            $client = new self();
            $response = $client->translate($testRequest, $provider);
            
            if (isset($response['choices']) && count($response['choices']) > 0) {
                $content = $response['choices'][0]['message']['content'] ?? '';
                return strpos($content, 'Connection test successful') !== false;
            }
            
            return false;
            
        } catch (\Exception $e) {
            Logger::error('API connection test failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get API usage statistics
     */
    public function getUsageStats(string $provider = 'openai'): array
    {
        try {
            switch ($provider) {
                case 'openai':
                    return $this->getOpenAIUsage();
                    
                case 'anthropic':
                    return $this->getAnthropicUsage();
                    
                default:
                    return [];
            }
            
        } catch (\Exception $e) {
            Logger::error('Failed to get usage stats: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get OpenAI usage statistics
     */
    private function getOpenAIUsage(): array
    {
        $apiKey = ConfigManager::get('api_key');
        
        if (empty($apiKey)) {
            return [];
        }
        
        $url = 'https://api.openai.com/v1/dashboard/billing/usage';
        $headers = [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            return [
                'total_usage' => $data['total_usage'] ?? 0,
                'daily_usage' => $data['daily_costs'] ?? []
            ];
        }
        
        return [];
    }
    
    /**
     * Get Anthropic usage statistics
     */
    private function getAnthropicUsage(): array
    {
        // Anthropic doesn't provide usage API in the same way
        // Return empty array for now
        return [];
    }
    
    /**
     * Get supported providers
     */
    public static function getSupportedProviders(): array
    {
        return self::$supportedProviders;
    }
    
    /**
     * Check if provider is supported
     */
    public static function isProviderSupported(string $provider): bool
    {
        return in_array($provider, self::$supportedProviders);
    }
}