<?php
namespace Wawoo\Plugin\Ads;

/**
 * Emits the AdSense loader (once per request) and optional CMP snippet via
 * `theme.head.after`. Client id comes from config; nothing prints when unset.
 */
final class Head
{
    private static bool $emitted = false;

    public function handle(?array $vars = null): void
    {
        if (self::$emitted || !Config::anyEnabled()) {
            return;
        }
        $client = (string)(Config::all()['adsense_client'] ?? '');
        if ($client === '') {
            return;
        }
        self::$emitted = true;
        if (!empty(Config::all()['consent_cmp'])) {
            echo '<script' . \Wawoo\Core\Csp::attr() . ' async src="https://fundingchoicesmessages.google.com/i/'
                . htmlspecialchars($client, ENT_QUOTES, 'UTF-8') . '"></script>' . "\n";
        }
        echo '<script' . \Wawoo\Core\Csp::attr() . ' async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client='
            . htmlspecialchars($client, ENT_QUOTES, 'UTF-8')
            . '" crossorigin="anonymous"></script>' . "\n";
    }

    public static function reset(): void
    {
        self::$emitted = false;
    }
}
