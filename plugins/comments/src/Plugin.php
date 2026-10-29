<?php
namespace Wawoo\Plugin\Comments;

class Plugin
{
    public function boot(): void
    {
        // Routes are wired in Router; nothing to do at boot besides
        // ensuring the data dir exists.
        $dir = dirname(__DIR__) . '/data';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
    }
}
