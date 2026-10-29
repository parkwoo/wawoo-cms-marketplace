<?php
namespace Wawoo\Plugin\I18n;

use Wawoo\Core\Hook;

/**
 * Hooks `core.boot` to initialize translations before any template
 * renders. Also exposes a `core.request` listener for any future i18n
 * request pre-processing.
 */
final class Init
{
    public function handle(...$args): void
    {
        Loader::init();
    }
}
