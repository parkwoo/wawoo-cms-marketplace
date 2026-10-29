<?php
/**
 * wawoo-cms admin login view (self-contained theme).
 *
 * @var string $message
 */
$message = (string)($message ?? '');
$e   = static fn($s): string => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$css = (string)@file_get_contents(__DIR__ . '/admin.css');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Admin login</title>
    <style><?= $css ?></style>
</head>
<body class="wa-admin">
<div class="wa-login">
    <div class="wa-login__card">
        <div class="wa-login__brand"><span class="wa-login__logo">W</span><strong>wawoo admin</strong></div>
        <?php if ($message !== ''): ?>
            <div class="wa-alert wa-alert--error"><?= $e($message) ?></div>
        <?php endif; ?>
        <form method="post" action="/admin/">
            <div class="wa-field">
                <label for="pw">Admin password</label>
                <input class="wa-input" id="pw" type="password" name="pw" autofocus required autocomplete="current-password">
            </div>
            <button type="submit" class="wa-btn wa-btn--primary" style="width:100%;justify-content:center">Sign in</button>
        </form>
    </div>
</div>
</body>
</html>
