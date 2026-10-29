<?php
namespace Wawoo\Plugin\Analytics;

/**
 * Emits Google Analytics gtag.js snippet when GA_TRACKING_ID is set.
 */
class Inject
{
    public function handle(?array $vars): void
    {
        if (!defined('GA_TRACKING_ID') || trim((string)GA_TRACKING_ID) === '') {
            return;
        }
        $id = htmlspecialchars(GA_TRACKING_ID, ENT_QUOTES, 'UTF-8');
        echo <<<HTML
<!-- wawoo-cms:analytics -->
<script async src="https://www.googletagmanager.com/gtag/js?id={$id}"></script>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
gtag('config', '{$id}');
</script>
HTML;
    }
}
