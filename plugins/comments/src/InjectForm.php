<?php
namespace Wawoo\Plugin\Comments;

/**
 * Hooks:
 *   core.request        -> Form::handle() — processes POST submissions
 *   post.before_render  -> InjectForm::handle() — inject form into post page
 *   theme.post.after    -> RenderList::handle() — render approved comments
 *   admin.before_render -> AdminPanel::handle() — render moderation queue
 */
final class InjectForm
{
    public function handle(?array $vars): void
    {
        if (empty($vars['post'])) {
            return;
        }
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $slug = $vars['post']['slug'];
        $err  = $_SESSION['wawoo_comment_error'] ?? null;
        $ok   = $_SESSION['wawoo_comment_ok']    ?? null;
        unset($_SESSION['wawoo_comment_error'], $_SESSION['wawoo_comment_ok']);

        $action = (defined('BASE_URL') ? BASE_URL : '/') . 'post/' . urlencode($slug) . '#comments';
        ?>
        <section id="comments" class="wawoo-comments">
            <h3>Comments</h3>
            <?php if ($err): ?><div class="wawoo-comment-err"><?= htmlspecialchars($err) ?></div><?php endif; ?>
            <?php if ($ok):  ?><div class="wawoo-comment-ok"><?= htmlspecialchars($ok) ?></div><?php endif; ?>

            <form method="post" action="<?= htmlspecialchars($action) ?>" class="wawoo-comment-form">
                <input type="hidden" name="wawoo_action" value="comment_submit">
                <input type="hidden" name="post_slug" value="<?= htmlspecialchars($slug) ?>">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(Form::csrfToken()) ?>">
                <p>
                    <label>Name <input type="text" name="name" required maxlength="80"></label>
                </p>
                <p>
                    <label>Email (optional) <input type="email" name="email" maxlength="120"></label>
                    <label>Website (optional) <input type="url" name="url" maxlength="200"></label>
                </p>
                <p>
                    <label>Comment<br>
                        <textarea name="body" rows="5" required></textarea>
                    </label>
                </p>
                <p><button type="submit">Submit</button></p>
            </form>
        </section>
        <?php
    }
}
