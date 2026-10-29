<?php
namespace Wawoo\Plugin\Comments;

/**
 * Hooks `core.request` to process incoming POSTs early in the request
 * lifecycle, before any output is generated. Forms for comment
 * submission and moderation are handled here.
 */
final class Bootstrap
{
    public function handle(?string $root = null): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        Form::handle();
    }
}
