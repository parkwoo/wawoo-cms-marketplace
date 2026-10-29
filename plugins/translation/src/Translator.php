<?php
namespace Wawoo\Plugin\Translation;

/**
 * Handles translation using external LLM APIs
 */
class Translator
{
    /**
     * @var \Wawoo\Plugin\Translation\APIClient
     */
    private APIClient $apiClient;
    
    public function __construct()
    {
        $this->apiClient = new APIClient();
    }
    
    /**
     * Handle post/page HTML translation
     */
    public function handle(?string &$html): ?string
    {
        if (!$html || !$this->shouldTranslate()) {
            return $html;
        }
        
        try {
            // Extract translatable content from HTML
            $content = $this->extractContent($html);
            
            // Translate the content
            $translatedContent = $this->translateContent($content);
            
            // Rebuild HTML with translated content
            $html = $this->rebuildHtml($html, $translatedContent);
            
            return $html;
        } catch (\Exception $e) {
            Logger::error('Translation failed: ' . $e->getMessage());
            
            if (ConfigManager::get('fallback_to_original', true)) {
                return $html;
            }
            
            throw $e;
        }
    }
    
    public function shouldTranslate(): bool
    {
        return ConfigManager::get('enabled', false);
    }
    
    public function extractContent(string $html): array
    {
        // Extract text content from HTML, preserving structure
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        
        $content = [];
        $nodes = $dom->getElementsByTagName('*');
        
        foreach ($nodes as $node) {
            if ($node->nodeType === XML_TEXT_NODE && trim($node->textContent)) {
                $text = trim($node->textContent);
                if ($this->containsJapanese($text)) {
                    $content[] = [
                        'text' => $text,
                        'context' => $this->getContext($node),
                        'type' => $this->getContentType($node)
                    ];
                }
            }
        }
        
        return $content;
    }
    
    private function containsJapanese(string $text): bool
    {
        return preg_match('/[\x{3040}-\x{309F}\x{30A0}-\x{30FF}\x{4E00}-\x{9FFF}]/u', $text) > 0;
    }
    
    private function getContext(\DOMText $node): string
    {
        $context = '';
        $parent = $node->parentNode;
        
        while ($parent && $parent->nodeType === XML_ELEMENT_NODE) {
            if ($parent->nodeName === 'body') {
                break;
            }
            
            $context .= $parent->nodeName . ' ';
            $parent = $parent->parentNode;
        }
        
        return trim($context);
    }
    
    private function getContentType(\DOMText $node): string
    {
        $parent = $node->parentNode;
        
        if ($parent && $parent->nodeName === 'title') {
            return 'title';
        }
        
        if ($parent && $parent->nodeName === 'h1') {
            return 'heading';
        }
        
        if ($parent && ($parent->nodeName === 'h2' || $parent->nodeName === 'h3' || 
            $parent->nodeName === 'h4' || $parent->nodeName === 'h5' || $parent->nodeName === 'h6')) {
            return 'heading';
        }
        
        if ($parent && $parent->nodeName === 'p') {
            return 'paragraph';
        }
        
        if ($parent && $parent->nodeName === 'li') {
            return 'list_item';
        }
        
        return 'general';
    }
    
    public function translateContent(array $content): array
    {
        $translatedContent = [];
        $batchSize = ConfigManager::get('batch_size', 5);
        
        for ($i = 0; $i < count($content); $i += $batchSize) {
            $batch = array_slice($content, $i, $batchSize);
            $translatedBatch = $this->translateBatch($batch);
            $translatedContent = array_merge($translatedContent, $translatedBatch);
        }
        
        return $translatedContent;
    }
    
    private function translateBatch(array $batch): array
    {
        $provider = ConfigManager::get('provider', 'openai');
        $model = ConfigManager::get('model', 'gpt-3.5-turbo');
        $customPrompts = ConfigManager::get('custom_prompts', []);
        
        $prompt = $customPrompts['default'] ?? 'Translate the following Japanese content to natural-sounding English. Maintain the original meaning and formatting.';
        
        // Build translation request
        $request = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $prompt
                ]
            ]
        ];
        
        // Add content to translate
        foreach ($batch as $item) {
            $request['messages'][] = [
                'role' => 'user',
                'content' => $item['text']
            ];
        }
        
        // Make API call
        $response = $this->apiClient->translate($request, $provider);
        
        // Process response
        return $this->processTranslationResponse($response, $batch);
    }
    
    private function processTranslationResponse(array $response, array $batch): array
    {
        $translatedContent = [];
        
        if (isset($response['choices']) && is_array($response['choices'])) {
            foreach ($response['choices'] as $index => $choice) {
                if (isset($choice['message']['content'])) {
                    $translatedContent[] = [
                        'original' => $batch[$index]['text'],
                        'translated' => trim($choice['message']['content']),
                        'context' => $batch[$index]['context'],
                        'type' => $batch[$index]['type']
                    ];
                }
            }
        }
        
        return $translatedContent;
    }
    
    private function rebuildHtml(string $originalHtml, array $translatedContent): string
    {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($originalHtml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        
        // Replace text content with translated versions
        $this->replaceTextContent($dom, $translatedContent);
        
        return $dom->saveHTML();
    }
    
    private function replaceTextContent(\DOMDocument $dom, array $translatedContent): void
    {
        $textNodes = [];
        
        // Find all text nodes
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDOMIterator($dom),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $node) {
            if ($node->nodeType === XML_TEXT_NODE && trim($node->textContent)) {
                $textNodes[] = $node;
            }
        }
        
        // Replace text content
        foreach ($textNodes as $node) {
            $textContent = trim($node->textContent);
            
            foreach ($translatedContent as $translation) {
                if ($translation['original'] === $textContent) {
                    $node->textContent = $translation['translated'];
                    break;
                }
            }
        }
    }
}

// Helper class for DOM iteration
class RecursiveDOMIterator extends \RecursiveIteratorIterator
{
    public function __construct(\DOMNode $node)
    {
        parent::__construct(new \DOMRecursiveIterator($node));
    }
}

class DOMRecursiveIterator implements \RecursiveIterator
{
    private \DOMNode $node;
    private \DOMNodeList $childNodes;
    private int $position = 0;
    
    public function __construct(\DOMNode $node)
    {
        $this->node = $node;
        $this->childNodes = $node->childNodes;
    }
    
    public function current(): \DOMNode
    {
        return $this->childNodes->item($this->position);
    }
    
    public function next(): void
    {
        $this->position++;
    }
    
    public function key(): int
    {
        return $this->position;
    }
    
    public function valid(): bool
    {
        return $this->position < $this->childNodes->length;
    }
    
    public function rewind(): void
    {
        $this->position = 0;
    }
    
    public function getChildren(): \RecursiveIterator
    {
        return new self($this->current());
    }
    
    public function hasChildren(): bool
    {
        return $this->current()->hasChildNodes();
    }
}