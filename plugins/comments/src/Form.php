<?php
namespace Wawoo\Plugin\Comments;

use Wawoo\Core\Posts;

/**
 * Handles comment form submission on post pages and admin moderation
 * actions on /admin/ pages.
 */
final class Form
{
    public static function handle(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return;
        }
        $action = $_POST['wawoo_action'] ?? '';
        if ($action === 'comment_submit') {
            self::submit();
        } elseif ($action === 'comment_moderate') {
            self::moderate();
        }
    }

    public static function csrfToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        if (empty($_SESSION['wawoo_csrf'])) {
            $_SESSION['wawoo_csrf'] = bin2hex(random_bytes(16));
        }
        return (string)$_SESSION['wawoo_csrf'];
    }

    public static function csrfValid(?string $token): bool
    {
        $expected = $_SESSION['wawoo_csrf'] ?? '';
        return is_string($expected) && $expected !== ''
            && is_string($token) && $token !== ''
            && hash_equals($expected, $token);
    }

    private static function submit(): void
    {
        $slug = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($_POST['post_slug'] ?? ''));
        if ($slug === '') {
            return;
        }
        if (!self::csrfValid($_POST['csrf'] ?? null)) {
            self::deny();
        }
        if (Posts::bySlug($slug, true) === null) {
            $_SESSION['wawoo_comment_error'] = 'Post not found.';
            self::redirect($slug);
        }
        $name  = trim((string)($_POST['name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $url   = trim((string)($_POST['url'] ?? ''));
        $body  = trim((string)($_POST['body'] ?? ''));

        $cfg = Config::all();

        // Validation
        if ($name === '' || mb_strlen($name) > 80) {
            $_SESSION['wawoo_comment_error'] = 'Name is required.';
            self::redirect($slug);
        }
        if (mb_strlen($body) < (int)$cfg['min_length'] || mb_strlen($body) > (int)$cfg['max_length']) {
            $_SESSION['wawoo_comment_error'] = sprintf('Comment must be %d-%d characters.', (int)$cfg['min_length'], (int)$cfg['max_length']);
            self::redirect($slug);
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['wawoo_comment_error'] = 'Email is not valid.';
            self::redirect($slug);
        }
        if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) {
            $_SESSION['wawoo_comment_error'] = 'URL is not valid.';
            self::redirect($slug);
        }
        if (!self::checkRateLimit($cfg)) {
            $_SESSION['wawoo_comment_error'] = 'Rate limit exceeded. Try again later.';
            self::redirect($slug);
        }

        $status = ($cfg['moderation'] === 'auto') ? 'approved' : 'pending';

        Store::add($slug, [
            'author'     => $name,
            'email'      => $email,
            'url'        => $url,
            'body'       => $body,
            'created_at' => time(),
            'status'     => $status,
            'ip'         => $_SERVER['REMOTE_ADDR'] ?? '',
            'ua'         => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 200),
        ]);

        $_SESSION['wawoo_comment_ok'] = $status === 'approved'
            ? 'Comment posted.'
            : 'Comment submitted, awaiting moderation.';

        self::redirect($slug);
    }

    private static function moderate(): void
    {
        if (!self::csrfValid($_POST['csrf'] ?? null)) {
            self::deny();
        }
        if (!self::isAdmin()) {
            self::deny();
        }
        $slug = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($_POST['post_slug'] ?? ''));
        $id   = (string)($_POST['comment_id'] ?? '');
        $op   = (string)($_POST['op'] ?? '');
        if ($slug === '' || $id === '') {
            return;
        }
        switch ($op) {
            case 'approve':   Store::updateStatus($slug, $id, 'approved'); break;
            case 'reject':    Store::updateStatus($slug, $id, 'rejected'); break;
            case 'spam':      Store::updateStatus($slug, $id, 'spam');     break;
            case 'delete':    Store::delete($slug, $id);                   break;
        }
        // Redirect back to the admin comments view, but never to an
        // arbitrary external URL (HTTP_REFERER can be attacker-controlled,
        // so only same-origin /admin paths are honoured).
        $back = '/admin/?action=comments';
        $ref  = (string)($_SERVER['HTTP_REFERER'] ?? '');
        if ($ref !== '') {
            $refPath = parse_url($ref, PHP_URL_PATH) ?: '';
            $refHost = parse_url($ref, PHP_URL_HOST) ?: '';
            $self    = parse_url(BASE_URL, PHP_URL_HOST) ?: '';
            if ($refHost === '' || $refHost === $self || $refHost === ($_SERVER['HTTP_HOST'] ?? '')) {
                if (strpos($refPath, '/admin') === 0) {
                    $back = $refPath;
                }
            }
        }
        header('Location: ' . $back);
        exit;
    }

    private static function isAdmin(): bool
    {
        return !empty($_SESSION['wawoo_admin']) || !empty($_SESSION['wawoo_admin_authed']);
    }

    private static function deny(): void
    {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }

    private static function redirect(string $slug): void
    {
        $url = (defined('BASE_URL') ? BASE_URL : '/') . 'post/' . urlencode($slug) . '#comments';
        header('Location: ' . $url);
        exit;
    }

    private static function checkRateLimit(array $cfg): bool
    {
        $ip  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $key = 'rate_' . preg_replace('/[^a-zA-Z0-9_.-]/', '_', $ip) . '_' . date('YmdH');
        $file = Store::dataDir() . '/' . $key . '.json';
        $n = 0;
        if (is_file($file)) {
            $n = (int)file_get_contents($file);
        }
        if ($n >= (int)$cfg['rate_limit_per_hour']) {
            return false;
        }
        file_put_contents($file, (string)($n + 1));
        return true;
    }
}
