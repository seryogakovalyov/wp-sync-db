# WP Sync DB — Plugins & Themes Sync

> Based on WP Sync DB (authorship and original repository): https://github.com/hrsetyono/wp-sync-db

A plugin for transparent migrations between two WordPress sites: database, media files, plugins, and themes.
Adds plugin/theme sync with version comparison before transfer.

## Key Features

- **Compare plugins and themes** between source and target with clear status:
  - Upgrade / Downgrade / Same / Missing
- **Select individual plugins and themes** to transfer
- **Chunked transfer** for plugin/theme files with permission preservation
- **Database migration** (all tables with prefix or selected)
- **“Do not migrate database tables”** option — files only
- **Media Files Sync** (uploads)
- **WP‑CLI** support

## Quick Start

1. Install the plugin on **both** sites (source and target).
2. On source: Tools → Migrate DB → Settings → enable Pull/Push/SSL.
3. Copy **Connection Info**.
4. On target: Tools → Migrate DB → Migrate → choose Pull/Push → paste Connection Info.
5. Review differences and select tables/plugins/themes.
6. Run migration.

**Pull** — transfer from remote to local.  
**Push** — transfer from local to remote.

## Plugins & Themes Sync

- Compare versions on source/target before migration
- Explicit selection of what to sync
- Downgrade scenarios allowed
- Files transferred in chunks with permissions preserved

> If you only need files, choose **Do not migrate database tables**.

## Media Files Sync

Enable **Media Files** to sync uploads.

> For local self‑signed SSL, disable SSL verification in settings.

## WP‑CLI

```
wp wpsdb migrate [profile-number]
```

## How It Works

- Database is transferred via SQL dump with find/replace.
- Plugins and themes are compared by version before transfer.
- Selected files are transferred in chunks between sites.

## Credits

Original project: https://github.com/hrsetyono/wp-sync-db

---

If needed, I can add troubleshooting or screenshots.
