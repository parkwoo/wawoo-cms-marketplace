<?php
namespace Wawoo\Plugin\Admin;

use Wawoo\Core\Hook;
use Wawoo\Core\PluginManager;
use Wawoo\Core\Posts;
use Wawoo\Core\Theme;

if (!function_exists('t') && !function_exists(__NAMESPACE__ . '\\t')) {
    function t(string $key, string $default = ''): string
    {
        return $default !== '' ? $default : $key;
    }
}

/**
 * Serves /admin — session-based admin console. Hooks `core.request`.
 *
 * Reproduces the historical admin/index.php behaviour: password gate,
 * post toggle/delete, and plugin/theme management.
 */
final class Gate
{
    /** Login throttle: N failed attempts per window per IP. */
    private const LOGIN_MAX = 5;
    private const LOGIN_WINDOW = 300;

    public function handle(?string $root = null): void
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        if ($path !== '/admin' && strpos($path, '/admin/') !== 0) {
            return;
        }
        $base = rtrim((string)($root ?? dirname(__DIR__, 3)), '/');
        $hasCredential = !empty($GLOBALS['WAWOO_ADMIN']['password_hash'])
            || (defined('ADMIN_PASSWORD') && ADMIN_PASSWORD !== '');
        if (!$hasCredential) {
            http_response_code(503);
            echo '<h1>' . t('admin.disabled', 'Admin disabled') . '</h1><p>'
                . t('admin.disabled_hint', 'Set an admin password (ADMIN_PASSWORD or $GLOBALS[WAWOO_ADMIN][password_hash]) in config.local.php to enable admin.') . '</p>';
            exit;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $isAuthed = !empty($_SESSION['wawoo_admin']);

        // Login: rate-limited password check, regenerate session id on success.
        if (!$isAuthed && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['pw'])) {
            $lock = self::loginLockout((string)($_SERVER['REMOTE_ADDR'] ?? ''));
            if ($lock['locked']) {
                $this->renderLogin(t('admin.locked', 'Too many attempts. Try again later.'), 429);
                exit;
            }
            $passwordOk = false;
            $hash = (string)($GLOBALS['WAWOO_ADMIN']['password_hash'] ?? '');
            if ($hash !== '') {
                $passwordOk = password_verify((string)$_POST['pw'], $hash);
            } elseif (defined('ADMIN_PASSWORD')) {
                // Legacy path: plaintext ADMIN_PASSWORD constant. Prefer
                // password_hash in $GLOBALS['WAWOO_ADMIN'] in production.
                $passwordOk = hash_equals(ADMIN_PASSWORD, (string)$_POST['pw']);
            }
            if ($passwordOk) {
                self::recordLoginSuccess();
                session_regenerate_id(true);
                $_SESSION['wawoo_admin'] = true;
                $isAuthed = true;
            } else {
                self::recordLoginFailure((string)($_SERVER['REMOTE_ADDR'] ?? ''));
                $this->renderLogin(t('admin.login_failed', 'Incorrect password.'), 401);
                exit;
            }
        }

        if (!$isAuthed) {
            $this->renderLogin('', 200);
            exit;
        }

        // All state-changing admin actions require POST + CSRF token.
        $action = $_GET['action'] ?? 'dashboard';
        if (in_array($action, [
            'logout', 'delete', 'toggle', 'publish', 'unpublish', 'schedule-submit',
            'enable-plugin', 'disable-plugin', 'enable-theme',
        ], true)) {
            $isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
            if (!$isPost || !Csrf::verify(Csrf::posted())) {
                http_response_code(403);
                echo '<h1>Invalid or missing CSRF token</h1><p><a href="/admin/">Back</a></p>';
                exit;
            }
        }

        switch ($action) {
            case 'logout':
                unset($_SESSION['wawoo_admin']);
                header('Location: /admin/');
                exit;
            case 'delete':
                $slug = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($_POST['slug'] ?? ''));
                if ($slug !== '' && Posts::deleteBySlug($slug)) {
                    \Wawoo\Core\Cache::flush();
                }
                header('Location: /admin/');
                exit;
            case 'toggle':
                $slug = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($_POST['slug'] ?? ''));
                if ($slug !== '') {
                    $file = Posts::mdFile($slug);
                    if ($file !== null) {
                        $raw = file_get_contents($file);
                        if (preg_match('/^---\s*\n(.*?)\n---/s', $raw, $m)) {
                            $new = stripos($m[1], "status: draft") !== false
                                ? str_ireplace('status: draft', 'status: published', $m[1])
                                : $m[1] . "\nstatus: draft";
                            file_put_contents($file, "---\n" . $new . "\n---\n" . substr($raw, strlen($m[0])));
                            \Wawoo\Core\Cache::flush();
                        }
                        header('Location: /admin/');
                        exit;
                    }
                }
                break;
            case 'publish':
                $slug = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($_POST['slug'] ?? ''));
                if ($slug !== '') {
                    Posts::setStatus($slug, 'published');
                    header('Location: /admin/');
                    exit;
                }
                break;
            case 'unpublish':
                $slug = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($_POST['slug'] ?? ''));
                if ($slug !== '') {
                    Posts::setStatus($slug, 'draft');
                    header('Location: /admin/');
                    exit;
                }
                break;
            case 'schedule':
                // GET: show the schedule form (read-only navigation).
                $slug = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($_GET['slug'] ?? ''));
                $this->renderScheduleForm($slug, SITE_TITLE);
                exit;
            case 'schedule-submit':
                $slug = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($_POST['slug'] ?? ''));
                $at   = trim((string)($_POST['at'] ?? ''));
                if ($slug !== '' && $at !== '') {
                    Posts::setStatus($slug, 'scheduled', $at);
                }
                header('Location: /admin/');
                exit;
            case 'enable-plugin':
                $name = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($_POST['name'] ?? ''));
                if ($name !== '') {
                    $enabled = PluginManager::enabledPlugins();
                    if (!in_array($name, $enabled, true)) {
                        $enabled[] = $name;
                        PluginManager::setEnabledPlugins($enabled);
                    }
                }
                header('Location: /admin/?action=plugins');
                exit;
            case 'disable-plugin':
                $name = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($_POST['name'] ?? ''));
                if ($name !== '') {
                    $enabled = PluginManager::enabledPlugins();
                    $enabled = array_values(array_filter($enabled, fn($p) => $p !== $name));
                    PluginManager::setEnabledPlugins($enabled);
                }
                header('Location: /admin/?action=plugins');
                exit;
            case 'enable-theme':
                $name = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($_POST['name'] ?? ''));
                if ($name !== '') {
                    PluginManager::setActiveTheme($name);
                }
                header('Location: /admin/?action=themes');
                exit;
            case 'settings':
                // Delegate to a plugin's own Settings page when the plugin
                // declares "settings": true in plugin.json and ships
                // src/Settings.php (class with handle(?string $root)).
                $name = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($_GET['plugin'] ?? ''));
                $manifest = self::findPlugin($base, $name);
                if ($manifest !== null && !empty($manifest['settings'])) {
                    // Register the plugin's PSR-4 src dir so its Settings
                    // class (and its Config/Store dependencies) can load even
                    // when the plugin is currently disabled.
                    $ns = rtrim((string)($manifest['namespace'] ?? ''), '\\');
                    $srcDir = $base . '/plugins/' . $name . '/src';
                    if ($ns !== '' && is_dir($srcDir)) {
                        \Wawoo\Core\Autoloader::add($ns, $srcDir);
                    }
                    $file = $base . '/plugins/' . $name . '/src/Settings.php';
                    if (is_file($file)) {
                        require_once $file;
                        $cls = $ns . '\\Settings';
                        if (class_exists($cls)) {
                            $handler = new $cls();
                            if (method_exists($handler, 'handle')) {
                                $handler->handle($base);
                                exit;
                            }
                        }
                    }
                }
                http_response_code(404);
                echo '<h1>Settings not available</h1><p><a href="/admin/?action=plugins">Back to plugins</a></p>';
                exit;
        }

        $vars = [
            'pageTitle' => sprintf(t('admin.title', 'Admin - %s'), SITE_TITLE),
            'base'      => '/admin/',
            'posts'     => Posts::all(true),
            'stats'     => Posts::stats(true),
            'plugins'   => PluginManager::discoverPlugins($base),
            'themes'    => PluginManager::discoverThemes($base),
            'enabledPlugins' => PluginManager::enabledPlugins(),
            'activeTheme'    => PluginManager::activeTheme(),
            'action'    => $action,
        ];
        Hook::fire('admin.before_render', $vars);
        Theme::render('admin/dashboard', $vars);
        exit;
    }

    private static function findPlugin(string $base, string $name): ?array
    {
        foreach (PluginManager::discoverPlugins($base) as $p) {
            if (($p['name'] ?? '') === $name) {
                return $p;
            }
        }
        return null;
    }

    private function renderScheduleForm(string $slug, string $siteTitle): void
    {
        $e = static fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
        $post = Posts::bySlug($slug, true);
        $title = $post['title'] ?? $slug;
        echo '<!doctype html><meta charset="utf-8"><title>Schedule - ' . $e($siteTitle) . '</title>'
            . '<body style="font-family:system-ui,sans-serif;max-width:420px;margin:4rem auto;background:#fff;color:#1d1d1f">'
            . '<h1>Schedule publish</h1>'
            . '<p>Post: <strong>' . $e($title) . '</strong> (<code>' . $e($slug) . '</code>)</p>'
            . '<form method="post" action="/admin/">'
            . Csrf::field()
            . '<input type="hidden" name="action" value="schedule-submit">'
            . '<input type="hidden" name="slug" value="' . $e($slug) . '">'
            . '<label>Publish at<br><input type="datetime-local" name="at" required style="width:100%;padding:.5rem;margin-top:.25rem"></label>'
            . '<p style="color:#6e6e73;font-size:.85rem">Local time. Examples: 2026-09-10T09:00, or any strtotime string.</p>'
            . '<button style="background:#1d1d1f;color:#fff;border:0;border-radius:8px;padding:.6rem 1.1rem;margin-top:.5rem">Schedule</button>'
            . ' <a href="/admin/" style="color:#0071e3">Cancel</a>'
            . '</form></body>';
    }

    private function renderLogin(string $message, int $status): void
    {
        http_response_code($status);
        $e = static fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
        $msg = $message !== '' ? '<p style="color:#b00020">' . $e($message) . '</p>' : '';
        echo '<!doctype html><meta charset="utf-8"><title>' . $e(t('admin.login_title', 'Admin login')) . '</title>'
            . '<body style="font-family:system-ui,sans-serif;max-width:320px;margin:4rem auto;background:#fff;color:#1d1d1f">'
            . '<h1>' . $e(t('admin.login_title', 'Admin login')) . '</h1>'
            . $msg
            . '<form method="post" action="/admin/">'
            . '<input type="password" name="pw" placeholder="' . $e(t('admin.password', 'Password')) . '" autofocus required '
            . 'style="width:100%;padding:.6rem;font-size:1rem;box-sizing:border-box">'
            . '<button type="submit" style="margin-top:1rem;padding:.6rem 1.2rem;background:#1d1d1f;color:#fff;border:0;border-radius:8px">'
            . $e(t('admin.sign_in', 'Sign in')) . '</button>'
            . '</form></body>';
    }

    private static function loginFile(): string
    {
        $dir = rtrim(\CACHE_DIR, '/\\') . DIRECTORY_SEPARATOR . 'settings';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir . DIRECTORY_SEPARATOR . 'admin-login.json';
    }

    /** @return array{locked:bool, wait:int} */
    private static function loginLockout(string $ip): array
    {
        $data = self::loginState();
        $key  = 'ip:' . preg_replace('/[^0-9a-fA-F.:]/', '', $ip);
        $entry = $data[$key] ?? ['fail' => 0, 'until' => 0];
        $wait = (int)$entry['until'] - time();
        return ['locked' => $wait > 0, 'wait' => max(0, $wait)];
    }

    private static function recordLoginFailure(string $ip): void
    {
        $data = self::loginState();
        $key = 'ip:' . preg_replace('/[^0-9a-fA-F.:]/', '', $ip);
        $entry = $data[$key] ?? ['fail' => 0, 'until' => 0];
        $entry['fail'] = (int)$entry['fail'] + 1;
        if ($entry['fail'] >= self::LOGIN_MAX) {
            $entry['fail'] = 0;
            $entry['until'] = time() + self::LOGIN_WINDOW;
        }
        $data[$key] = $entry;
        self::writeLoginState($data);
    }

    private static function recordLoginSuccess(): void
    {
        $data = self::loginState();
        foreach (array_keys($data) as $k) {
            unset($data[$k]);
        }
        self::writeLoginState($data);
    }

    private static function loginState(): array
    {
        $file = self::loginFile();
        if (!is_file($file)) {
            return [];
        }
        $data = json_decode((string)file_get_contents($file), true);
        return is_array($data) ? $data : [];
    }

    private static function writeLoginState(array $data): void
    {
        $file = self::loginFile();
        $tmp  = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($tmp, json_encode($data)) !== false) {
            @rename($tmp, $file);
        } else {
            @unlink($tmp);
        }
    }
}
