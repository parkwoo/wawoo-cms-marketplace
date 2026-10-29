<?php
namespace Wawoo\Plugin\Translation;

/**
 * Validates translation quality and accuracy
 */
class Validator
{
    /**
     * @var array<string>
     */
    private static array $validationResults = [];
    
    /**
     * Validate translation quality
     */
    public static function validate(string $original, string $translated): array
    {
        $result = [
            'valid' => true,
            'issues' => [],
            'score' => 100,
            'checks' => []
        ];
        
        // Length check
        $lengthRatio = mb_strlen($translated) / mb_strlen($original);
        if ($lengthRatio < 0.5 || $lengthRatio > 2.0) {
            $result['valid'] = false;
            $result['issues'][] = 'Translation length is significantly different from original';
            $result['score'] -= 20;
        }
        $result['checks']['length_ratio'] = $lengthRatio;
        
        // Language detection
        if (!self::isEnglish($translated)) {
            $result['valid'] = false;
            $result['issues'][] = 'Translated text does not appear to be English';
            $result['score'] -= 30;
        }
        $result['checks']['is_english'] = self::isEnglish($translated);
        
        // Character validation
        if (self::containsJapanese($translated)) {
            $result['valid'] = false;
            $result['issues'][] = 'Translated text still contains Japanese characters';
            $result['score'] -= 40;
        }
        $result['checks']['contains_japanese'] = self::containsJapanese($translated);
        
        // Content similarity check
        $similarity = self::calculateSimilarity($original, $translated);
        if ($similarity < 0.3) {
            $result['issues'][] = 'Translation may be too different from original meaning';
            $result['score'] -= 25;
        }
        $result['checks']['similarity'] = $similarity;
        
        // Check for untranslated segments
        $untranslatedSegments = self::findUntranslatedSegments($original, $translated);
        if (count($untranslatedSegments) > 0) {
            $result['issues'] = array_merge($result['issues'], $untranslatedSegments);
            $result['score'] -= 15;
        }
        $result['checks']['untranslated_segments'] = count($untranslatedSegments);
        
        // Grammar check (basic)
        $grammarIssues = self::checkBasicGrammar($translated);
        if (count($grammarIssues) > 0) {
            $result['issues'] = array_merge($result['issues'], $grammarIssues);
            $result['score'] -= 10;
        }
        $result['checks']['grammar_issues'] = count($grammarIssues);
        
        // Store validation result
        $hash = md5($original);
        self::$validationResults[$hash] = $result;
        
        return $result;
    }
    
    /**
     * Check if text is English
     */
    private static function isEnglish(string $text): bool
    {
        // Count English characters vs total characters
        $englishChars = preg_match_all('/[a-zA-Z\s\.\,\!\?\;\:\-\'\"]/', $text);
        $totalChars = mb_strlen($text);
        
        if ($totalChars === 0) {
            return false;
        }
        
        $englishRatio = $englishChars / $totalChars;
        return $englishRatio > 0.7;
    }
    
    /**
     * Check if text contains Japanese characters
     */
    private static function containsJapanese(string $text): bool
    {
        return preg_match('/[\x{3040}-\x{309F}\x{30A0}-\x{30FF}\x{4E00}-\x{9FFF}]/u', $text) > 0;
    }
    
    /**
     * Calculate similarity between original and translated text
     */
    private static function calculateSimilarity(string $original, string $translated): float
    {
        // Simple similarity calculation using word overlap
        $originalWords = preg_split('/\s+/', $original);
        $translatedWords = preg_split('/\s+/', $translated);
        
        if (empty($originalWords) || empty($translatedWords)) {
            return 0;
        }
        
        $intersection = array_intersect($originalWords, $translatedWords);
        $union = array_unique(array_merge($originalWords, $translatedWords));
        
        if (empty($union)) {
            return 1;
        }
        
        return count($intersection) / count($union);
    }
    
    /**
     * Find untranslated segments in translated text
     */
    private static function findUntranslatedSegments(string $original, string $translated): array
    {
        $issues = [];
        
        // Look for common Japanese patterns that might not be translated
        $patterns = [
            '/[\x{3040}-\x{309F}]+/u',  // Hiragana
            '/[\x{30A0}-\x{30FF}]+/u',  // Katakana
            '/[\x{4E00}-\x{9FFF}]+/u',  // Kanji
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $translated)) {
                $issues[] = 'Contains untranslated Japanese characters';
                break;
            }
        }
        
        return $issues;
    }
    
    /**
     * Basic grammar check
     */
    private static function checkBasicGrammar(string $text): array
    {
        $issues = [];
        
        // Check for common grammar issues
        if (preg_match('/\b[a-z][A-Z]+\b/', $text)) {
            $issues[] = 'Possible capitalization error';
        }
        
        if (preg_match('/\s{2,}/', $text)) {
            $issues[] = 'Multiple spaces detected';
        }
        
        if (preg_match('/\.{2,}/', $text)) {
            $issues[] = 'Multiple periods detected';
        }
        
        if (preg_match('/\!{2,}/', $text)) {
            $issues[] = 'Multiple exclamation marks detected';
        }
        
        return $issues;
    }
    
    /**
     * Get validation results for a specific text
     */
    public static function getValidationResult(string $original): ?array
    {
        $hash = md5($original);
        return self::$validationResults[$hash] ?? null;
    }
    
    /**
     * Get all validation results
     */
    public static function getAllValidationResults(): array
    {
        return self::$validationResults;
    }
    
    /**
     * Clear validation results
     */
    public static function clearValidationResults(): void
    {
        self::$validationResults = [];
    }
}