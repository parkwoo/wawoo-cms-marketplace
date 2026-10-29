<?php
namespace Wawoo\Plugin\TagCloud;

use Wawoo\Core\Posts;

final class Render
{
    public function handle(?array $vars): void
    {
        $counts = [];
        foreach (Posts::all() as $p) {
            foreach ((array)$p['tags'] as $t) {
                $counts[$t] = ($counts[$t] ?? 0) + 1;
            }
        }
        if ($counts === []) {
            return;
        }
        // Derive the tag list from the same pass so posts are iterated once.
        $tags = array_keys($counts);
        sort($tags);
        $min = min($counts);
        $max = max($counts);
        $cfg = Config::all();
        $lo = (int)$cfg['min_font_px'];
        $hi = (int)$cfg['max_font_px'];
        ?>
        <aside class="wawoo-tagcloud">
            <h3><?= htmlspecialchars((string)$cfg['title']) ?></h3>
            <p>
            <?php foreach ($tags as $t): ?>
                <?php
                $n = $counts[$t];
                $size = $max === $min
                    ? (int)(($lo + $hi) / 2)
                    : (int)($lo + (($n - $min) / ($max - $min)) * ($hi - $lo));
                ?>
                <a class="tag" href="<?= htmlspecialchars(BASE_URL) ?>?tag=<?= urlencode(strtolower($t)) ?>"
                   style="font-size: <?= $size ?>px"><?= htmlspecialchars($t) ?></a>
            <?php endforeach; ?>
            </p>
        </aside>
        <?php
    }
}
