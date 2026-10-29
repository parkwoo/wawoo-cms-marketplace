<?php
namespace Wawoo\Plugin\Comments;

/**
 * Renders the moderation queue on the admin dashboard.
 * Triggered via /admin/?action=comments.
 */
final class AdminPanel
{
    public function handle(?array &$vars): void
    {
        if (($_GET['action'] ?? '') !== 'comments') {
            return;
        }
        if (!self::isAdmin()) {
            return;
        }
        $items   = Store::allPending();
        $pending = array_values(array_filter($items, fn($c) => ($c['status'] ?? '') === 'pending'));
        $token   = Form::csrfToken();
        ob_start();
        ?>
        <div class="clay-card" style="margin-top:1rem">
            <h2>Comment moderation</h2>
            <p>
                <?= count($pending) ?> pending ·
                <?= count(array_filter($items, fn($c) => ($c['status'] ?? '') === 'approved')) ?> approved ·
                <?= count(array_filter($items, fn($c) => ($c['status'] ?? '') === 'spam')) ?> spam ·
                <?= count(array_filter($items, fn($c) => ($c['status'] ?? '') === 'rejected')) ?> rejected
            </p>
            <?php if (empty($items)): ?>
                <p>No comments yet.</p>
            <?php else: ?>
                <table style="width:100%;border-collapse:collapse">
                    <thead>
                        <tr>
                            <th style="text-align:left;padding:.5rem">Author</th>
                            <th style="text-align:left;padding:.5rem">Post</th>
                            <th style="text-align:left;padding:.5rem">Body</th>
                            <th style="text-align:left;padding:.5rem">Status</th>
                            <th style="text-align:right;padding:.5rem">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($items as $c): ?>
                        <tr>
                            <td style="padding:.5rem"><?= htmlspecialchars($c['author'] ?? '') ?></td>
                            <td style="padding:.5rem">
                                <a href="<?= htmlspecialchars(BASE_URL) ?>?post=<?= urlencode($c['post_slug'] ?? '') ?>"><?= htmlspecialchars($c['post_slug'] ?? '') ?></a>
                            </td>
                            <td style="padding:.5rem;max-width:400px"><?= htmlspecialchars(mb_substr($c['body'] ?? '', 0, 200)) ?></td>
                            <td style="padding:.5rem"><?= htmlspecialchars($c['status'] ?? '') ?></td>
                            <td style="padding:.5rem;text-align:right">
                                <?php $slug = $c['post_slug'] ?? ''; $id = $c['id'] ?? ''; ?>
                                <?php if (($c['status'] ?? '') !== 'approved'): ?>
                                <form method="post" action="" style="display:inline">
                                    <input type="hidden" name="wawoo_action" value="comment_moderate">
                                    <input type="hidden" name="post_slug" value="<?= htmlspecialchars($slug) ?>">
                                    <input type="hidden" name="comment_id" value="<?= htmlspecialchars($id) ?>">
                                    <input type="hidden" name="op" value="approve">
                                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($token) ?>">
                                    <button type="submit">Approve</button>
                                </form>
                                <?php endif; ?>
                                <?php if (($c['status'] ?? '') !== 'spam'): ?>
                                <form method="post" action="" style="display:inline">
                                    <input type="hidden" name="wawoo_action" value="comment_moderate">
                                    <input type="hidden" name="post_slug" value="<?= htmlspecialchars($slug) ?>">
                                    <input type="hidden" name="comment_id" value="<?= htmlspecialchars($id) ?>">
                                    <input type="hidden" name="op" value="spam">
                                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($token) ?>">
                                    <button type="submit">Spam</button>
                                </form>
                                <?php endif; ?>
                                <form method="post" action="" style="display:inline" onsubmit="return confirm('Delete?')">
                                    <input type="hidden" name="wawoo_action" value="comment_moderate">
                                    <input type="hidden" name="post_slug" value="<?= htmlspecialchars($slug) ?>">
                                    <input type="hidden" name="comment_id" value="<?= htmlspecialchars($id) ?>">
                                    <input type="hidden" name="op" value="delete">
                                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($token) ?>">
                                    <button type="submit">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            <p style="margin-top:1rem"><a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">&larr; Back to dashboard</a></p>
        </div>
        <?php
        $vars['admin_panel_comments'] = (string)ob_get_clean();
    }

    private static function isAdmin(): bool
    {
        return !empty($_SESSION['wawoo_admin']) || !empty($_SESSION['wawoo_admin_authed']);
    }
}
