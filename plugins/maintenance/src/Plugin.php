<?php
namespace Wawoo\Plugin\Maintenance;

/**
 * Plugin entry point. Maintenance mode is OFF by default; admins enable it
 * and set a password from /admin -> Plugins -> Settings (runtime settings
 * are stored in CACHE_DIR/settings/maintenance.json).
 */
class Plugin
{
    public function boot(): void
    {
        // Configuration is read lazily by Guard::check() so that values
        // defined later in config.local.php ($WAWOO_MAINTENANCE) and the
        // runtime settings file are honoured.
    }
}
