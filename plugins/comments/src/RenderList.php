<?php
namespace Wawoo\Plugin\Comments;

final class RenderList
{
    public function handle(?array $vars): void
    {
        if (empty($vars['post'])) {
            return;
        }
        $slug = $vars['post']['slug'];
        $list = Store::approved($slug);
        if (empty($list)) {
            return;
        }
        ?>
        <section class="wawoo-comments-list">
            <h4><?= count($list) ?> comment<?= count($list) === 1 ? '' : 's' ?></h4>
            <?php foreach ($list as $c): ?>
                <article class="wawoo-comment">
                    <header>
                        <strong><?= htmlspecialchars($c['author'] ?? 'anon') ?></strong>
                        <time><?= htmlspecialchars(date('Y-m-d H:i', (int)($c['created_at'] ?? 0))) ?></time>
                    </header>
                    <div class="wawoo-comment-body">
                        <?= nl2br(htmlspecialchars($c['body'] ?? '')) ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
        <?php
    }
}
