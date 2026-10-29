<?php
/**
 * wawoo-cms admin login — Admin One (Bulma).
 *
 * @var string $message
 * @var string $csrf
 */
$message = (string)($message ?? '');
$csrf    = (string)($csrf ?? '');
$e = static fn($s): string => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$asset = '/admin/theme';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Admin login</title>
    <link rel="stylesheet" href="<?= $e($asset) ?>/vendor/admin-one.min.css">
    <link rel="stylesheet" href="<?= $e($asset) ?>/admin.css">
</head>
<body>
<div id="app">
    <section class="section">
        <div class="container" style="max-width:460px;margin-top:8vh">
            <div class="card">
                <header class="card-header"><p class="card-header-title">wawoo admin</p></header>
                <div class="card-content">
                    <?php if ($message !== ''): ?>
                        <p class="notification is-danger is-light" role="alert"><?= $e($message) ?></p>
                    <?php endif; ?>
                    <form method="post" action="/admin/">
                        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                        <div class="field">
                            <label class="label" for="pw">Admin password</label>
                            <div class="control">
                                <input class="input" id="pw" type="password" name="pw" autofocus required autocomplete="current-password">
                            </div>
                        </div>
                        <button type="submit" class="button is-primary is-fullwidth">Sign in</button>
                    </form>
                </div>
            </div>
        </div>
    </section>
</div>
</body>
</html>
