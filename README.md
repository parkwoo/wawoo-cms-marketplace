# wawoo-cms-marketplace

A curated catalog of **plugins** and **themes** for
[wawoo-cms](https://github.com/parkwoo/wawoo-cms) — the small, file-based,
agent-friendly PHP CMS.

This is a distribution catalog, not a commerce app. An item is a folder with a
`plugin.json` (or `theme.json`) manifest; installing it means placing that
folder under the site's `plugins/` (or `themes/`) directory and enabling it.

## What is here

- `index.json` — machine-readable catalog of every listed plugin/theme.
- `plugins/<name>/` — every first-party plugin, hosted in this repo.
- `themes/<name>/` — first-party themes (none hosted yet; see `clay` below).
- `bundles/default.json` — a starter set of enabled plugins.

The `admin` plugin ships its **own self-contained admin theme** under
`plugins/admin/theme/` (CSS/JS inlined, no CDN, no build step). It is rendered
independently of the site's front-end theme via the core `Theme::renderFile()`.

### Source of truth

- All plugins are **hosted here** (`plugins/<name>/`): `admin`, `rss`, `search`,
  `translation`, `admin-totp`, `analytics`, `code`, `comments`, `i18n`,
  `maintenance`, `mermaid`, `mlang`, `pagination`, `password-gate`, `publish`,
  `s3`, `sitemap-html`, `tag-cloud`. The matching `wawoo-plugin-*` repos are the
  upstream development homes; the copy here is the install source.
- The `clay` theme is referenced from its own repo
  (`wawoo-theme-clay`) via `"source": { "kind": "git", ... }` — the only
  non-hosted item.

## Install an item

From a wawoo-cms checkout, with this repo as a sibling
(`../wawoo-cms-marketplace`) or via `--index <path>`:

```bash
php bin/wawoo plugin:search rss
php bin/wawoo plugin:install rss --enable     # hosted: copied; git: cloned
php bin/wawoo bundle:install default          # a curated set (bundles/default.json)
```

Or install by hand by `plugin:link`-ing a checkout:

```bash
git clone https://github.com/parkwoo/wawoo-cms-marketplace
cd /path/to/wawoo-cms
php bin/wawoo plugin:link /path/to/wawoo-cms-marketplace/plugins/admin
```

Then add the name to `ENABLED_PLUGINS` in `config.local.php`, or run
`php bin/wawoo enable <name>` / `disable <name>`, or use `/admin → Plugins`.

Validate the catalog after editing `index.json`:

```bash
python3 scripts/validate_index.py
```

## Compatibility

Every item declares `requires.core` (a semver range). Core is minimal: it ships
only `contact`, `menu`, and `seo`; everything else — including `admin`, `rss`,
`search` — is an optional item from this catalog.

## Contributing

Add a plugin under `plugins/<name>/` with a valid `plugin.json`, then add an
entry to `index.json`. Keep `name` and `version` in sync with the manifest.

## License

MIT — see `LICENSE`.
