# admin (wawoo-cms plugin)

Session-based admin console served at `/admin`. Manages posts, plugins, and
themes, and delegates per-plugin settings to each plugin's `src/Settings.php`.

## Own theme (independent of the front-end theme)

The admin console renders through the core `Theme::renderFile()` helper with an
absolute path, so it **does not use the active front-end theme**
(`themes/<name>/templates/`). The UI stays stable when the site theme changes.

```
plugins/admin/theme/
├── dashboard.php   # console shell: fixed navbar + left aside + cards/tables
├── login.php       # password login card
├── schedule.php    # schedule-publish form
├── admin.css       # self-contained styling (Admin One-style, dark-mode aware)
└── admin.js        # mobile sidebar toggle + delete confirm
```

- No CDN, no external fonts/icons, no build step (CSS/JS are inlined by the
  templates). Visual reference: a Bulma "Admin One"-style dashboard
  (navbar + aside + cards + tables).

## Auth

- Set `ADMIN_PASSWORD` or `$GLOBALS['WAWOO_ADMIN']['password_hash']` in
  `config.local.php`.
- Per-IP login throttling; every state-changing action requires POST + CSRF.

## Install

```bash
cd /path/to/wawoo-cms
php bin/wawoo plugin:link /path/to/wawoo-cms-marketplace/plugins/admin
php bin/wawoo enable admin
```
