# wawoo-cms-marketplace

A curated catalog of **plugins** and **themes** for
[wawoo-cms](https://github.com/parkwoo/wawoo-cms) — the small, file-based,
agent-friendly PHP CMS.

This is a distribution catalog, not a commerce app. An item is a folder with a
`plugin.json` (or `theme.json`) manifest; installing it means placing that
folder under the site's `plugins/` (or `themes/`) directory and enabling it.

## What is here

- `index.json` — machine-readable catalog of every listed plugin/theme.
- `plugins/<name>/` — first-party plugins hosted in this repo.
- `themes/<name>/` — first-party themes (none yet).
- `bundles/default.json` — a starter set of enabled plugins.

The `admin` plugin ships its **own self-contained admin theme** under
`plugins/admin/theme/` (CSS/JS inlined, no CDN, no build step). It is rendered
independently of the site's front-end theme via the core `Theme::renderFile()`.

Plugins whose source lives in their own repository are referenced from
`index.json` with `"source": { "kind": "git", ... }` and are not copied here.

## Install an item

Until `bin/wawoo plugin:install` lands in the core, install by hand:

```bash
# hosted item
git clone https://github.com/parkwoo/wawoo-cms-marketplace
cd /path/to/wawoo-cms
php bin/wawoo plugin:link /path/to/wawoo-cms-marketplace/plugins/admin

# git item (own repo)
git clone https://github.com/parkwoo/wawoo-plugin-comments
php bin/wawoo plugin:link /path/to/wawoo-plugin-comments
```

Then add the name to `ENABLED_PLUGINS` in `config.local.php`, or run
`php bin/wawoo enable <name>` / `disable <name>`, or use `/admin → Plugins`.

## Compatibility

Every item declares `requires.core` (a semver range). Core is minimal: it ships
only `contact`, `menu`, and `seo`; everything else — including `admin`, `rss`,
`search` — is an optional item from this catalog.

## Contributing

Add a plugin under `plugins/<name>/` with a valid `plugin.json`, then add an
entry to `index.json`. Keep `name` and `version` in sync with the manifest.

## License

MIT — see `LICENSE`.
