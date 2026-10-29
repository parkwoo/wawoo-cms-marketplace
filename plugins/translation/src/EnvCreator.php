<?php
namespace Wawoo\Plugin\Translation;

/**
 * Script to create .env file from template
 */
class EnvCreator
{
    /**
     * Create .env file from template
     */
    public static function create(): bool
    {
        $templatePath = __DIR__ . '/../.env.example';
        $targetPath = self::findEnvPath();
        
        if (file_exists($targetPath)) {
            echo "Error: .env file already exists at {$targetPath}\n";
            return false;
        }
        
        if (!file_exists($templatePath)) {
            echo "Error: Template file not found at {$templatePath}\n";
            return false;
        }
        
        if (!copy($templatePath, $targetPath)) {
            echo "Error: Failed to copy template file\n";
            return false;
        }
        
        echo ".env file created successfully at {$targetPath}\n";
        echo "Please edit the file and set your API key and other configuration values.\n";
        
        return true;
    }
    
    /**
     * Find the appropriate path for .env file
     */
    private static function findEnvPath(): string
    {
        $possiblePaths = [
            __DIR__ . '/../../../../.env',
            __DIR__ . '/../../../.env',
            __DIR__ . '/../../.env',
            __DIR__ . '/../.env',
            __DIR__ . '/.env',
        ];
        
        foreach ($possiblePaths as $path) {
            if (is_dir(dirname($path))) {
                return $path;
            }
        }
        
        // Fallback to current directory
        return '.env';
    }
    
    /**
     * Validate .env file
     */
    public static function validate(): array
    {
        // Include the EnvLoader class
        require_once __DIR__ . '/EnvLoader.php';
        
        EnvLoader::load();
        return EnvLoader::validate();
    }
    
    /**
     * Show .env file status
     */
    public static function status(): void
    {
        $envPath = self::findEnvPath();
        
        if (file_exists($envPath)) {
            echo "✓ .env file found at {$envPath}\n";
            
            // Check if required variables are set
            $errors = self::validate();
            
            if (empty($errors)) {
                echo "✓ Environment variables are valid\n";
            } else {
                echo "✗ Environment validation failed:\n";
                foreach ($errors as $error) {
                    echo "  - {$error}\n";
                }
            }
        } else {
            echo "✗ .env file not found\n";
            echo "Run 'php bin/wawoo translation:env:create' to create one from template\n";
        }
    }
}