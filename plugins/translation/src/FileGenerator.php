<?php
namespace Wawoo\Plugin\Translation;

/**
 * Generates translated files and manages file operations
 */
class FileGenerator
{
    /**
     * Generate translated version of a file
     */
    public static function generateTranslatedFile(string $sourcePath, string $targetLang, array $translatedContent): string
    {
        $targetPath = self::getTargetPath($sourcePath, $targetLang);
        
        // Ensure target directory exists
        $targetDir = dirname($targetPath);
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }
        
        // Read original file
        if (!file_exists($sourcePath)) {
            throw new \Exception('file_get_contents: No such file or directory');
        }
        $originalContent = file_get_contents($sourcePath);
        
        // Apply translations
        $translatedContent = self::applyTranslations($originalContent, $translatedContent);
        
        // Write translated file
        file_put_contents($targetPath, $translatedContent);
        
        // Update metadata
        self::updateMetadata($sourcePath, $targetPath, $targetLang);
        
        return $targetPath;
    }
    
    /**
     * Get target file path
     */
    private static function getTargetPath(string $sourcePath, string $targetLang): string
    {
        $pathInfo = pathinfo($sourcePath);
        $targetDir = dirname($sourcePath) . '/' . $targetLang;
        
        return $targetDir . '/' . $pathInfo['basename'];
    }
    
    /**
     * Apply translations to content
     */
    private static function applyTranslations(string $content, array $translatedContent): string
    {
        foreach ($translatedContent as $translation) {
            $original = $translation['original'];
            $translated = $translation['translated'];
            
            // Replace original text with translated text
            $content = str_replace($original, $translated, $content);
        }
        
        return $content;
    }
    
    /**
     * Update metadata for translated file
     */
    private static function updateMetadata(string $sourcePath, string $targetPath, string $targetLang): void
    {
        $metadataFile = self::getMetadataPath($sourcePath);
        
        $metadata = [];
        if (file_exists($metadataFile)) {
            $metadata = json_decode(file_get_contents($metadataFile), true) ?? [];
        }
        
        // Add translation metadata
        if (!isset($metadata['translations'])) {
            $metadata['translations'] = [];
        }
        
        $metadata['translations'][$targetLang] = [
            'path' => $targetPath,
            'translated_at' => date('Y-m-d H:i:s'),
            'source_language' => 'ja',
            'target_language' => $targetLang
        ];
        
        // Save metadata
        file_put_contents($metadataFile, json_encode($metadata, JSON_PRETTY_PRINT));
    }
    
    /**
     * Get metadata file path
     */
    public static function getMetadataPath(string $sourcePath): string
    {
        $cacheDir = defined('CACHE_DIR') ? CACHE_DIR : __DIR__ . '/../../../cache';
        return $cacheDir . '/translation_metadata.json';
    }
    
    /**
     * Create translated version of a post/page
     */
    public static function createTranslatedContent(string $sourcePath, string $targetLang, array $translatedContent): array
    {
        $result = [
            'success' => false,
            'target_path' => '',
            'error' => null
        ];
        
        try {
            // Generate translated file
            $targetPath = self::generateTranslatedFile($sourcePath, $targetLang, $translatedContent);
            
            // Update frontmatter
            $updatedContent = self::updateFrontmatter($targetPath, $targetLang);
            
            $result['success'] = true;
            $result['target_path'] = $targetPath;
            $result['updated_content'] = $updatedContent;
            
        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Update frontmatter for translated content
     */
    private static function updateFrontmatter(string $filePath, string $targetLang): string
    {
        $content = file_get_contents($filePath);
        
        // Extract frontmatter
        if (preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $content, $matches)) {
            $frontmatter = $matches[1];
            $body = $matches[2];
            
            // Parse frontmatter
            $fmData = self::parseFrontmatter($frontmatter);
            
            // Update language
            $fmData['language'] = $targetLang;
            
            // Add translation info
            if (!isset($fmData['translations'])) {
                $fmData['translations'] = [];
            }
            
            $fmData['translations'][$targetLang] = [
                'translated_from' => 'ja',
                'translated_at' => date('Y-m-d H:i:s')
            ];
            
            // Rebuild frontmatter
            $newFrontmatter = self::buildFrontmatter($fmData);
            
            // Rebuild content
            $content = "---\n" . $newFrontmatter . "\n---\n" . $body;
            
            // Write back to file
            file_put_contents($filePath, $content);
        }
        
        return $content;
    }
    
    /**
     * Parse frontmatter
     */
    private static function parseFrontmatter(string $frontmatter): array
    {
        $data = [];
        $lines = explode("\n", trim($frontmatter));
        
        foreach ($lines as $line) {
            if (strpos($line, ':') !== false) {
                list($key, $value) = explode(':', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Handle different value types
                if ($value === 'true') {
                    $data[$key] = true;
                } elseif ($value === 'false') {
                    $data[$key] = false;
                } elseif ($value === 'null') {
                    $data[$key] = null;
                } elseif (is_numeric($value)) {
                    $data[$key] = strpos($value, '.') !== false ? (float)$value : (int)$value;
                } elseif (strpos($value, '[') === 0 && strpos($value, ']') !== false) {
                    // Handle arrays
                    $data[$key] = json_decode($value, true);
                } else {
                    // Handle strings
                    $data[$key] = $value;
                }
            }
        }
        
        return $data;
    }
    
    /**
     * Build frontmatter
     */
    private static function buildFrontmatter(array $data): string
    {
        $lines = [];
        
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $lines[] = $key . ': ' . json_encode($value);
            } elseif (is_bool($value)) {
                $lines[] = $key . ': ' . ($value ? 'true' : 'false');
            } elseif (is_null($value)) {
                $lines[] = $key . ': null';
            } else {
                $lines[] = $key . ': ' . $value;
            }
        }
        
        return implode("\n", $lines);
    }
    
    /**
     * Clean up translated files
     */
    public static function cleanupTranslatedFiles(string $sourcePath, string $targetLang): void
    {
        $targetPath = self::getTargetPath($sourcePath, $targetLang);
        
        if (file_exists($targetPath)) {
            unlink($targetPath);
        }
        
        // Update metadata
        $metadataFile = self::getMetadataPath($sourcePath);
        if (file_exists($metadataFile)) {
            $metadata = json_decode(file_get_contents($metadataFile), true) ?? [];
            
            if (isset($metadata['translations'][$targetLang])) {
                unset($metadata['translations'][$targetLang]);
                
                if (empty($metadata['translations'])) {
                    unset($metadata['translations']);
                }
                
                file_put_contents($metadataFile, json_encode($metadata, JSON_PRETTY_PRINT));
            }
        }
    }
}