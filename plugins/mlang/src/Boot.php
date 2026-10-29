<?php
namespace Wawoo\Plugin\Mlang;

/**
 * Hooks `core.boot`. No registration is needed here: language-prefixed
 * routing is handled by router.php and content resolution by Posts/Pages,
 * and both read plain GET state. Keeping this empty also makes the plugin
 * inert on the CLI (bin/wawoo boots plugins through Bootstrap too).
 */
final class Boot
{
    public function handle($payload = null): void
    {
    }
}
