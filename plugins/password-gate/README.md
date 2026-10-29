# password-gate

A full-site password gate for wawoo-cms, in the style of the Shopify
`/password` page: visitors land on a clean form, enter the shared
password, and get a long-lived cookie that grants access to the entire
site.

## How it works

- Hooks `core.boot` (fires very early in every request).
- Checks the cookie. If absent, the request is short-circuited with the
  gate HTML; the actual blog never renders.
- POST to `/password` validates the password against `password_hash`
  using `password_verify()`, sets a signed cookie, and 303-redirects to
  the original target.
- Wrong attempts are rate-limited per IP (default: 5 attempts → 15 min
  lockout). Counters persist in `cache/gate_*.json` so brute-force
  attempts can't be reset by restarting PHP.

## Configuration

Edit `config.local.php`:

```php
$GLOBALS['PASSWORD_GATE'] = [
    'password'        => 'your-secret',   // plaintext OR 'password_hash' => '...bcrypt...'
    'cookie_name'     => 'wawoo_gate',
    'cookie_ttl'      => 86400,           // seconds (default 1 day)
    'remember_days'   => 7,               // 0 = session cookie
    'max_attempts'    => 5,
    'lockout_seconds' => 900,
    'title'           => 'Members only',
    'message'         => 'Enter the password to continue.',
    'placeholder'     => 'Password',
    'button'          => 'Enter',
    'footer'          => 'Authorized access only.',
    'exempt_paths'    => ['/gate.css'],   // paths that bypass the gate
];
```

For a pre-hashed password (preferred in production):

```php
$GLOBALS['PASSWORD_GATE'] = [
    'password_hash' => '$2y$10$....',  // output of password_hash('...', PASSWORD_BCRYPT)
];
```

Generate one with:

```bash
php -r "echo password_hash('your-secret', PASSWORD_BCRYPT), PHP_EOL;"
```

## Behaviour matrix

| Request | Cookie | Result |
|---------|--------|--------|
| GET /            | absent | Gate HTML (200) |
| GET /            | valid  | Blog (200)      |
| GET /password    | any    | Gate HTML (200) |
| POST /password   | wrong  | Gate HTML + "Incorrect password" (401) |
| POST /password   | right  | Set-Cookie + 303 → original target |
| POST /password   | right, but rate-limited | Gate HTML + "Too many attempts" (429) |

## Hooks used

- `core.boot` → `Wawoo\Plugin\PasswordGate\Guard::handle()`

## Files

```
plugins/password-gate/
├── plugin.json
└── src/
    ├── Plugin.php     (entry — no-op, behaviour is in Guard)
    ├── Config.php     (defaults + $PASSWORD_GATE merge)
    ├── Store.php      (file-backed rate-limit store)
    └── Guard.php      (gate render, auth check, attempt handling)
```

## Security notes

- The cookie value is an HMAC-SHA256 of the literal string `gate` keyed
  by a secret derived from the password. The cookie itself doesn't
  leak the password; rotating the password invalidates all existing
  cookies.
- The password is verified with `password_verify()` (constant-time
  bcrypt comparison).
- Failed attempts always run a bcrypt verification pass to keep timing
  comparable to success.
- The gate fires on every request — including static assets under
  `exempt_paths`. Add static asset paths there if you serve the gate
  page itself with custom CSS/JS.
- Set `$GLOBALS['PASSWORD_GATE']` via a real config file in
  production; do not pass the password through URL parameters.
