<?php
namespace Wawoo\Plugin\PasswordGate;

/**
 * Plugin entry point. Configuration is read from plugin.json's `config`
 * block; users override values in config.local.php via $PASSWORD_GATE.
 *
 * Example override:
 *   $PASSWORD_GATE = [
 *       'password'      => 'plain-or-bcrypt-hash',
 *       'cookie_ttl'    => 86400,
 *       'title'         => 'Welcome back',
 *       'message'       => 'Enter the password sent to you.',
 *       'exempt_paths'  => ['/rss', '/sitemap.xml'],
 *   ];
 *   define('ENABLED_PLUGINS', ['seo', 'analytics', 'password-gate']);
 */
class Plugin
{
    public function boot(): void
    {
        // Configuration is read lazily by Guard::check() so that values
        // defined later in config.local.php are honoured.
    }
}
