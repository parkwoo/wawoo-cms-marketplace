<?php
namespace Wawoo\Plugin\Translation;

/**
 * Main plugin class that initializes the translation system
 */
class Plugin
{
    public function boot(): void
    {
        // Initialize configuration
        ConfigManager::init();
        
        // Initialize logging
        Logger::init();
        
        // Register CLI commands
        $this->registerCliCommands();
    }
    
    private function registerCliCommands(): void
    {
        // CLI commands are registered through the plugin system
        // This will be handled by the wawoo-cms CLI system
    }
}