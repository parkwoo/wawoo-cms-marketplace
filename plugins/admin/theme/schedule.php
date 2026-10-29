<?php
/**
 * wawoo-cms admin schedule-publish view (self-contained theme).
 *
 * @var string $slug
 * @var string $title
 * @var string $siteTitle
 */
$slug   = (string)($slug ?? '');
$title  = (string)($title ?? $slug);
$e      = static fn($s): string => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$css    = (string)@file_get_contents(__DIR__ . '/admin.css');
$token  = \Wawoo\Plugin\Admin\Csrf::token();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Schedule publish</title>
    <style><?= $css ?></style>
</head>
<body class="wa-admin">
<div class="wa-login">
    <div class="wa-login__card">
        <div class="wa-login__brand"><span class="wa-login__logo">W</span><strong>Schedule publish</strong></div>
        <p class="wa-muted">Post: <strong><?= $e($title) ?></strong> (<code><?= $e($slug) ?></code>)</p>
        <form method="post" action="/admin/">
            <input type="hidden" name="csrf" value="<?= $e($token) ?>">
            <input type="hidden" name="action" value="schedule-submit">
            <input type="hidden" name="slug" value="<?= $e($slug) ?>">
            <div class="wa-field">
                <label for="at">Publish at (local time)</label>
                <input class="wa-input" id="at" type="datetime-local" name="at" required>
            </div>
            <button type="submit" class="wa-btn wa-btn--primary">Schedule</button>
            <a class="wa-btn" href="/admin/" style="margin-left:.5rem">Cancel</a>
        </form>
    </div>
</div>
</body>
</html>
