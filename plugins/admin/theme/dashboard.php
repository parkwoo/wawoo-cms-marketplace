<?php
/**
 * wawoo-cms admin console — rendered with Admin One (Bulma) whose CSS is
 * vendored under theme/vendor/ and served by the admin Gate at
 * /admin/theme/*. Independent of the front-end theme.
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
    return '<form method="post" action="' . $e($base) . '"' . $cf . ' style="display:inline;margin:0">'
        . '<input type="hidden" name="csrf" value="' . $e($token) . '">'
        . '<input type="hidden" name="action" value="' . $e($act) . '">'
        . '<input type="hidden" name="' . $e($field) . '" value="' . $e($value) . '">'
        . '<button type="submit" class="button is-small ' . ($danger ? 'is-danger is-light' : 'is-light') . '">' . $e($label) . '</button>'
        . '</form>';
};

$js = (string)@file_get_contents(__DIR__ . '/admin.js');
$siteUrl = defined('BASE_URL') ? BASE_URL : '/';
$asset = '/admin/theme';
?>
<!doctype html>
<html lang="en" class="has-aside-left has-aside-mobile-transition has-navbar-fixed-top has-aside-expanded">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= $e($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= $e($asset) ?>/vendor/admin-one.min.css">
    <link rel="stylesheet" href="<?= $e($asset) ?>/admin.css">
</head>
<body>
<div id="app">
    <nav class="navbar is-fixed-top">
        <div class="navbar-brand">
            <div class="navbar-item"><span class="has-text-weight-semibold">wawoo admin</span></div>
        </div>
        <div class="navbar-menu is-active">
            <div class="navbar-end">
                <div class="navbar-item">
                    <form method="post" action="<?= $e($base) ?>" style="margin:0">
                        <input type="hidden" name="csrf" value="<?= $e($token) ?>">
                        <input type="hidden" name="action" value="logout">
                        <button class="button is-small is-light" type="submit">Log out</button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    <aside class="aside is-placed-left is-expanded">
        <div class="aside-tools"><div class="aside-tools-label"><span><b>wawoo</b> admin</span></div></div>
        <div class="menu is-menu-main">
            <p class="menu-label">Content</p>
            <ul class="menu-list">
                <li><a class="<?= $action === 'dashboard' ? 'is-active' : '' ?>" href="<?= $e($base) ?>">Dashboard</a></li>
            </ul>
            <p class="menu-label">System</p>
            <ul class="menu-list">
                <li><a class="<?= $action === 'plugins' ? 'is-active' : '' ?>" href="<?= $e($base) ?>?action=plugins">Plugins</a></li>
                <li><a class="<?= $action === 'themes' ? 'is-active' : '' ?>" href="<?= $e($base) ?>?action=themes">Themes</a></li>
                <li><a href="<?= $e($siteUrl) ?>" target="_blank" rel="noopener">View site</a></li>
            </ul>
        </div>
    </aside>

    <section class="hero is-hero-bar"><div class="hero-body"><h1 class="title"><?= $e($pageTitle) ?></h1></div></section>

    <section class="section is-main-section">
    <?php if ($action === 'plugins'): ?>
        <div class="card">
            <header class="card-header"><p class="card-header-title">Plugins</p></header>
            <div class="card-content">
                <div class="field"><div class="control"><input class="input is-small wa-search" type="search" data-filter-target=".wa-table" placeholder="Filter plugins…" aria-label="Filter plugins…"></div></div>
                <div class="table-container">
                    <table class="table is-fullwidth is-striped is-narrow is-hoverable wa-table">
                        <thead><tr><th>Name</th><th>Version</th><th>Description</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php if ($plugins === []): ?>
                            <tr><td colspan="4" class="has-text-grey">No plugins found.</td></tr>
                        <?php else: foreach ($plugins as $p):
                            $name = (string)($p['name'] ?? '');
                            $on = in_array($name, $enabledPlugins, true);
                        ?>
                            <tr>
                                <td><strong><?= $e($name) ?></strong></td>
                                <td><?= $e($p['version'] ?? '') ?></td>
                                <td class="has-text-grey"><?= $e($p['description'] ?? ($p['_path'] ?? '')) ?></td>
                                <td>
                                    <div class="buttons are-small">
                                        <span class="tag <?= $on ? 'is-success is-light' : 'is-light' ?>"><?= $on ? 'enabled' : 'disabled' ?></span>
                                        <?= $on
                                            ? $actForm('disable-plugin', $name, 'Disable', false, 'name', true)
                                            : $actForm('enable-plugin', $name, 'Enable', false, 'name') ?>
                                        <?php if (!empty($p['settings'])): ?>
                                            <a class="button is-small is-light" href="<?= $e($base) ?>?action=settings&amp;plugin=<?= urlencode($name) ?>">Settings</a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <?php elseif ($action === 'themes'): ?>
        <div class="card">
            <header class="card-header"><p class="card-header-title">Themes</p></header>
            <div class="card-content">
                <div class="field"><div class="control"><input class="input is-small wa-search" type="search" data-filter-target=".wa-table" placeholder="Filter themes…" aria-label="Filter themes…"></div></div>
                <div class="table-container">
                    <table class="table is-fullwidth is-striped is-narrow is-hoverable wa-table">
                        <thead><tr><th>Name</th><th>Version</th><th>Description</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php if ($themes === []): ?>
                            <tr><td colspan="4" class="has-text-grey">No themes found.</td></tr>
                        <?php else: foreach ($themes as $t):
                            $name = (string)($t['name'] ?? '');
                            $active = ($name === (string)$activeTheme);
                        ?>
                            <tr>
                                <td><strong><?= $e($name) ?></strong></td>
                                <td><?= $e($t['version'] ?? '') ?></td>
                                <td class="has-text-grey"><?= $e($t['description'] ?? ($t['_path'] ?? '')) ?></td>
                                <td>
                                    <?php if ($active): ?>
                                        <span class="tag is-success is-light">active</span>
                                    <?php else: ?>
                                        <div class="buttons are-small"><?= $actForm('enable-theme', $name, 'Activate', false, 'name') ?></div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <?php else: ?>
        <div class="columns is-multiline">
            <?php foreach ([['Posts', (int)($stats['post_count'] ?? 0)], ['Categories', (int)($stats['category_count'] ?? 0)], ['Tags', (int)($stats['tag_count'] ?? 0)], ['Plugins', count($enabledPlugins)]] as $st): ?>
                <div class="column is-3-desktop is-6-tablet">
                    <div class="card"><div class="card-content"><p class="heading"><?= $e($st[0]) ?></p><p class="title"><?= (int)$st[1] ?></p></div></div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="card">
            <header class="card-header"><p class="card-header-title">Posts</p></header>
            <div class="card-content">
                <div class="field"><div class="control"><input class="input is-small wa-search" type="search" data-filter-target=".wa-table" placeholder="Filter posts…" aria-label="Filter posts…"></div></div>
                <div class="table-container">
                    <table class="table is-fullwidth is-striped is-narrow is-hoverable wa-table">
                        <thead><tr><th>Title</th><th>Date</th><th>Category</th><th>Status</th><th>Actions</th></tr></thead>
                        <tbody>
                        <?php if ($posts === []): ?>
                            <tr><td colspan="5" class="has-text-grey">No posts yet. Add a markdown bundle under <code>posts/</code> or run <code>bin/wawoo post:new</code>.</td></tr>
                        <?php else: foreach ($posts as $p):
                            $status = (string)($p['status'] ?? '');
                            $slug = (string)($p['slug'] ?? '');
                        ?>
                            <tr>
                                <td><a href="<?= $e($siteUrl) ?>post/<?= urlencode($slug) ?>"><?= $e($p['title'] ?? $slug) ?></a></td>
                                <td><?= $e($p['date'] ?? '') ?></td>
                                <td><?= $e($p['category'] ?? '') ?></td>
                                <td><span class="tag <?= $status === 'published' ? 'is-success is-light' : 'is-warning is-light' ?>"><?= $e($status) ?></span></td>
                                <td>
                                    <div class="buttons are-small">
                                        <?php if ($status !== 'published'): ?>
                                            <?= $actForm('publish', $slug, 'Publish') ?>
                                        <?php endif; ?>
                                        <a class="button is-small is-light" href="<?= $e($base) ?>?action=schedule&amp;slug=<?= urlencode($slug) ?>">Schedule</a>
                                        <?= $actForm('toggle', $slug, 'Toggle') ?>
                                        <?= $actForm('delete', $slug, 'Delete', true, 'slug', true) ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($vars['admin_panel_comments'])) { echo $vars['admin_panel_comments']; } ?>
    <?php Hook::fire('theme.admin.after', $vars ?? []); ?>
    </section>
</div>
<script<?= \Wawoo\Core\Csp::attr() ?>><?= $js ?></script>
</body>
</html>
