<?php
/**
 * wawoo-cms admin schedule-publish — Admin One (Bulma).
 *
 * @var string $slug
 * @var string $title
 */
$slug  = (string)($slug ?? '');
$title = (string)($title ?? $slug);
$e = static fn($s): string => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$token = \Wawoo\Plugin\Admin\Csrf::token();
$asset = '/admin/theme';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Schedule publish</title>
    <link rel="stylesheet" href="<?= $e($asset) ?>/vendor/admin-one.min.css">
    <link rel="stylesheet" href="<?= $e($asset) ?>/admin.css">
</head>
<body>
<div id="app">
    <section class="section">
        <div class="container" style="max-width:520px;margin-top:6vh">
            <div class="card">
                <header class="card-header"><p class="card-header-title">Schedule publish</p></header>
                <div class="card-content">
                    <p class="has-text-grey mb-4">Post: <strong><?= $e($title) ?></strong> (<code><?= $e($slug) ?></code>)</p>
                    <form method="post" action="/admin/">
                        <input type="hidden" name="csrf" value="<?= $e($token) ?>">
                        <input type="hidden" name="action" value="schedule-submit">
                        <input type="hidden" name="slug" value="<?= $e($slug) ?>">
                        <div class="field">
                            <label class="label" for="at">Publish at (local time)</label>
                            <div class="control"><input class="input" id="at" type="datetime-local" name="at" required></div>
                        </div>
                        <div class="buttons">
                            <button type="submit" class="button is-primary">Schedule</button>
                            <a class="button is-light" href="/admin/">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>
</div>
</body>
</html>
