<?php
/**
 * wawoo-cms admin console view.
 *
 * Rendered by the admin plugin via Theme::renderFile(), independent of the
 * active front-end theme. Self-contained: CSS/JS are inlined from this theme
 * directory, no CDN or build step.
 *
 * @var array $vars
 */

use Wawoo\Core\Hook;

$base           = (string)($base ?? '/admin/');
$action         = (string)($action ?? 'dashboard');
$pageTitle      = (string)($pageTitle ?? 'Admin');
$posts          = is_array($posts ?? null) ? $posts : [];
$stats          = is_array($stats ?? null) ? $stats : [];
$plugins        = is_array($plugins ?? null) ? $plugins : [];
$themes         = is_array($themes ?? null) ? $themes : [];
$enabledPlugins = is_array($enabledPlugins ?? null) ? $enabledPlugins : [];
$activeTheme    = $activeTheme ?? null;

$e = static fn($s): string => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$token = \Wawoo\Plugin\Admin\Csrf::token();

$actForm = static function (string $act, string $value, string $label, bool $confirm = false, string $field = 'slug', bool $danger = false) use ($base, $e, $token): string {
    $cf = $confirm ? ' data-confirm="' . $e('Delete ' . $value . '?') . '"' : '';
    return '<form method="post" action="' . $e($base) . '"' . $cf . '>'
        . '<input type="hidden" name="csrf" value="' . $e($token) . '">'
        . '<input type="hidden" name="action" value="' . $e($act) . '">'
        . '<input type="hidden" name="' . $e($field) . '" value="' . $e($value) . '">'
        . '<button type="submit" class="wa-btn' . ($danger ? ' wa-btn--danger' : '') . '">' . $e($label) . '</button>'
        . '</form>';
};

$css = (string)@file_get_contents(__DIR__ . '/admin.css');
$js  = (string)@file_get_contents(__DIR__ . '/admin.js');
$siteUrl = defined('BASE_URL') ? BASE_URL : '/';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= $e($pageTitle) ?></title>
    <style><?= $css ?></style>
</head>
<body class="wa-admin">
<aside class="wa-sidebar">
    <div class="wa-sidebar__brand"><span class="wa-login__logo">W</span><span>wawoo admin</span></div>
    <nav class="wa-sidebar__nav">
        <p class="wa-sidebar__label">Content</p>
        <a class="wa-navlink <?= $action === 'dashboard' ? 'is-active' : '' ?>" href="<?= $e($base) ?>">Dashboard</a>
        <p class="wa-sidebar__label">System</p>
        <a class="wa-navlink <?= $action === 'plugins' ? 'is-active' : '' ?>" href="<?= $e($base) ?>?action=plugins">Plugins</a>
        <a class="wa-navlink <?= $action === 'themes' ? 'is-active' : '' ?>" href="<?= $e($base) ?>?action=themes">Themes</a>
        <a class="wa-navlink" href="<?= $e($siteUrl) ?>" target="_blank" rel="noopener">View site</a>
    </nav>
    <div class="wa-sidebar__foot">
        <form method="post" action="<?= $e($base) ?>">
            <input type="hidden" name="csrf" value="<?= $e($token) ?>">
            <input type="hidden" name="action" value="logout">
            <button type="submit" class="wa-btn" style="width:100%;justify-content:center">Log out</button>
        </form>
    </div>
</aside>

<div class="wa-main">
    <header class="wa-navbar">
        <button type="button" class="wa-toggle" aria-label="Toggle menu">&#9776;</button>
        <span class="wa-navbar__title"><?= $e($pageTitle) ?></span>
    </header>
    <main class="wa-content">

    <?php if ($action === 'plugins'): ?>
        <section class="wa-panel">
            <div class="wa-panel__head"><h2 class="wa-panel__title">Plugins</h2><input class="wa-input wa-search" type="search" data-filter-target=".wa-table" placeholder="Filter plugins…" aria-label="Filter plugins…"></div>
            <div class="wa-table-wrap">
                <table class="wa-table">
                    <thead><tr><th>Name</th><th>Version</th><th>Description</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php if ($plugins === []): ?>
                        <tr><td colspan="4" class="wa-empty">No plugins found.</td></tr>
                    <?php else: foreach ($plugins as $p):
                        $name = (string)($p['name'] ?? '');
                        $on = in_array($name, $enabledPlugins, true);
                    ?>
                        <tr>
                            <td><strong><?= $e($name) ?></strong></td>
                            <td><?= $e($p['version'] ?? '') ?></td>
                            <td class="wa-muted"><?= $e($p['description'] ?? ($p['_path'] ?? '')) ?></td>
                            <td>
                                <div class="wa-actions">
                                    <span class="wa-badge <?= $on ? 'wa-badge--on' : 'wa-badge--off' ?>"><?= $on ? 'enabled' : 'disabled' ?></span>
                                    <?= $on
                                        ? $actForm('disable-plugin', $name, 'Disable', false, 'name', true)
                                        : $actForm('enable-plugin', $name, 'Enable', false, 'name') ?>
                                    <?php if (!empty($p['settings'])): ?>
                                        <a class="wa-btn" href="<?= $e($base) ?>?action=settings&amp;plugin=<?= urlencode($name) ?>">Settings</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

    <?php elseif ($action === 'themes'): ?>
        <section class="wa-panel">
            <div class="wa-panel__head"><h2 class="wa-panel__title">Themes</h2><input class="wa-input wa-search" type="search" data-filter-target=".wa-table" placeholder="Filter themes…" aria-label="Filter themes…"></div>
            <div class="wa-table-wrap">
                <table class="wa-table">
                    <thead><tr><th>Name</th><th>Version</th><th>Description</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php if ($themes === []): ?>
                        <tr><td colspan="4" class="wa-empty">No themes found.</td></tr>
                    <?php else: foreach ($themes as $t):
                        $name = (string)($t['name'] ?? '');
                        $active = ($name === (string)$activeTheme);
                    ?>
                        <tr>
                            <td><strong><?= $e($name) ?></strong></td>
                            <td><?= $e($t['version'] ?? '') ?></td>
                            <td class="wa-muted"><?= $e($t['description'] ?? ($t['_path'] ?? '')) ?></td>
                            <td>
                                <?php if ($active): ?>
                                    <span class="wa-badge wa-badge--on">active</span>
                                <?php else: ?>
                                    <div class="wa-actions"><?= $actForm('enable-theme', $name, 'Activate', false, 'name') ?></div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

    <?php else: ?>
        <div class="wa-cards">
            <div class="wa-card"><div class="wa-card__label">Posts</div><div class="wa-card__value"><?= (int)($stats['post_count'] ?? 0) ?></div></div>
            <div class="wa-card"><div class="wa-card__label">Categories</div><div class="wa-card__value"><?= (int)($stats['category_count'] ?? 0) ?></div></div>
            <div class="wa-card"><div class="wa-card__label">Tags</div><div class="wa-card__value"><?= (int)($stats['tag_count'] ?? 0) ?></div></div>
            <div class="wa-card"><div class="wa-card__label">Plugins</div><div class="wa-card__value"><?= count($enabledPlugins) ?></div></div>
        </div>

        <section class="wa-panel">
            <div class="wa-panel__head"><h2 class="wa-panel__title">Posts</h2><input class="wa-input wa-search" type="search" data-filter-target=".wa-table" placeholder="Filter posts…" aria-label="Filter posts…"></div>
            <div class="wa-table-wrap">
                <table class="wa-table">
                    <thead><tr><th>Title</th><th>Date</th><th>Category</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php if ($posts === []): ?>
                        <tr><td colspan="5" class="wa-empty">No posts yet. Add a markdown bundle under <code>posts/</code> or run <code>bin/wawoo post:new</code>.</td></tr>
                    <?php else: foreach ($posts as $p):
                        $status = (string)($p['status'] ?? '');
                        $slug = (string)($p['slug'] ?? '');
                        $badge = $status === 'published' ? 'wa-badge--published' : 'wa-badge--draft';
                    ?>
                        <tr>
                            <td><a href="<?= $e($siteUrl) ?>post/<?= urlencode($slug) ?>"><?= $e($p['title'] ?? $slug) ?></a></td>
                            <td><?= $e($p['date'] ?? '') ?></td>
                            <td><?= $e($p['category'] ?? '') ?></td>
                            <td><span class="wa-badge <?= $badge ?>"><?= $e($status) ?></span></td>
                            <td>
                                <div class="wa-actions">
                                    <?php if ($status !== 'published'): ?>
                                        <?= $actForm('publish', $slug, 'Publish') ?>
                                    <?php endif; ?>
                                    <a class="wa-btn" href="<?= $e($base) ?>?action=schedule&amp;slug=<?= urlencode($slug) ?>">Schedule</a>
                                    <?= $actForm('toggle', $slug, 'Toggle') ?>
                                    <?= $actForm('delete', $slug, 'Delete', true, 'slug', true) ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>

    <?php if (!empty($vars['admin_panel_comments'])) { echo $vars['admin_panel_comments']; } ?>
    <?php Hook::fire('theme.admin.after', $vars ?? []); ?>
    </main>
</div>
<script><?= $js ?></script>
</body>
</html>
