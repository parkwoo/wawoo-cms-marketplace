<?php
// Bootstrap file for translation plugin tests

// Define constants
define('CACHE_DIR', __DIR__ . '/../../../cache');
define('POSTS_DIR', __DIR__ . '/../../../posts');
define('PAGES_DIR', __DIR__ . '/../../../pages');
define('PLUGINS_DIR', __DIR__ . '/../../../plugins');
define('THEMES_DIR', __DIR__ . '/../../../themes');

// Ensure cache directory exists
if (!is_dir(CACHE_DIR)) {
    mkdir(CACHE_DIR, 0755, true);
}

// Include wawoo-cms bootstrap
require_once __DIR__ . '/../../../includes/core/Bootstrap.php';

// Initialize plugin system
$pluginDir = PLUGINS_DIR . '/translation';
if (is_dir($pluginDir)) {
    $pluginJson = $pluginDir . '/plugin.json';
    if (file_exists($pluginJson)) {
        $pluginData = json_decode(file_get_contents($pluginJson), true);
        if (isset($pluginData['namespace'])) {
            $namespace = $pluginData['namespace'];
            $autoloadDir = $pluginDir . '/src';
            if (is_dir($autoloadDir)) {
                spl_autoload_register(function($class) use ($namespace, $autoloadDir) {
                    if (strpos($class, $namespace) === 0) {
                        $relativeClass = substr($class, strlen($namespace) + 1);
                        $file = $autoloadDir . '/' . str_replace('\\', '/', $relativeClass) . '.php';
                        if (file_exists($file)) {
                            require $file;
                        }
                    }
                });
            }
        }
    }
}

// Initialize configuration for tests
if (class_exists('Wawoo\\Plugin\\Translation\\ConfigManager')) {
    \Wawoo\Plugin\Translation\ConfigManager::init();
} else {
    // Fallback for testing without full plugin system
    require_once __DIR__ . '/../src/ConfigManager.php';
    \Wawoo\Plugin\Translation\ConfigManager::init();
}

// Set timezone
date_default_timezone_set('Asia/Tokyo');