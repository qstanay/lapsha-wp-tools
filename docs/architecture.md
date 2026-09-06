# Architecture

Version **0.1.1** is the plugin core plus **Database Cleaner**, with a Russian admin catalog. Later tools should plug into this layout instead of adding a second bootstrap.

## Runtime flow

```text
WordPress loads plugins
        ↓
lapsha-wp-tools.php
  · constants, ABSPATH guard
  · require core classes
        ↓
plugins_loaded → Lapsha_Core::instance()
        ↓
load text domain (init)
        ↓
register modules (Database, …)
        ↓
each module → init()          (no frontend work in 0.1.x)
        ↓
if is_admin()
        ↓
Lapsha_Admin
  · menus, assets, notices
  · each module → register_admin()
```

The main file stays thin. Feature SQL and UI live in `modules/`.

## Repository layout

```text
lapsha-wp-tools/                 ← git repo = plugin slug
├── lapsha-wp-tools.php          ← bootstrap + plugin header
├── readme.txt                   ← WordPress.org readme
├── README.md                    ← GitHub readme
├── LICENSE                      ← GPL-2.0-or-later
├── uninstall.php
├── includes/                    ← core, not features
├── admin/                       ← shared admin shell + dashboard view
├── modules/
│   └── database/                ← Database Cleaner
├── assets/                      ← admin CSS/JS (Lapsha screens only)
├── languages/                   ← ru_RU catalog
├── docs/                        ← architecture and cleaner notes
└── docker-compose.yml           ← local WordPress + MySQL
```

WordPress core is not in git. Compose bind-mounts this directory at:

```text
/var/www/html/wp-content/plugins/lapsha-wp-tools
```

`.distignore` lists paths that must not appear in a WordPress.org ZIP (`docs/`, Compose, development files).

## Core

`Lapsha_Core` is a singleton from `lapsha_wp_tools()`.

It knows which modules exist, loads translations, initializes modules, and creates `Lapsha_Admin` only in `is_admin()`. It does not run cleaner SQL or render HTML.

## Module contract

`Lapsha_Module` is the shared base:

| Piece | Role |
| --- | --- |
| ID | Stable machine name, e.g. `database` |
| Name / description | Translatable labels |
| `init()` | Front-end or always-on hooks (empty in 0.1.x) |
| `register_admin()` | Pages, `admin_post` handlers, submenus |
| `enqueue_assets()` | Optional extra assets on relevant screens |

`modules/database/database.php` loads compatibility packs, scanner, and cleaner, then registers `Lapsha_Database_Module`.

## Admin shell

One top-level menu:

```text
Lapsha WP Tools
├── Dashboard
└── Database Cleaner
```

Shared pieces:

- `Lapsha_Admin` — enqueue CSS/JS, coordinate pages
- `Lapsha_Admin_Menu` — `add_menu_page` / `add_submenu_page`
- `Lapsha_Admin_Page` — capability wrap + `.wrap` output
- `Lapsha_Admin_Progress` — stepped progress panel + JSON for long admin jobs
- PHP views — classes prepare data; templates render

Long work reuses `Lapsha_Admin_Progress` rather than copying bars into a module view.

```text
Module job (one time-boxed step)
        ↓
Lapsha_Admin_Progress::render( … )
Lapsha_Admin_Progress::payload( … )
        ↓
assets/js/admin.js  →  POST continue form with lapsha_ajax=1
        ↓
wp_send_json_success( payload )
```

The JSON shape is `complete`, `redirect`, `current`, `items[]` (`id`, `current`, `total`, `complete`, `percent`), `overall` (`current`, `total`, `percent`). Copy such as “Deleting…” stays in the module; the widget is generic.

The dashboard only summarizes tools and links into them.

## Scanner, cleaner, compatibility

Database work is split so queries are not mixed into templates:

```text
Lapsha_Database_Compat           → registry of plugin/theme integrations
  compat/Elementor, Divi, …      → detect + declare rules
        ↓
Lapsha_Database_Scanner          → counts and preview rows (no HTML, no deletes)
        ↓
Admin view                       → table, checkboxes, notices
        ↓
user confirms
        ↓
Lapsha_Database_Cleaner          → re-query, then WordPress delete APIs
```

Scanner and cleaner do not name third-party plugins. They ask `Lapsha_Database_Compat` for query constraints so the count matches what will be deleted.

A shipped pack is a class under `modules/database/compat/` plus an entry in `Lapsha_Database_Compat::bundled()`. Third parties can append packs with `lapsha_wp_tools_database_compat`. A new kind of exception is a rule on `Lapsha_Database_Compat_Rules`, interpreted in the registry — not an `if ( Elementor )` in the cleaner.

Scan results look like:

```text
category id, label, description, count, preview rows
```

Preview IDs are for the human reviewing the table. Cleanup does **not** delete those IDs. The cleaner always re-queries with the same rules as the scanner, and only allowlisted category keys from the request are accepted.

How scan and delete behave is documented in [database-cleaner.md](database-cleaner.md).

## Security

Admin screens and `admin_post` handlers require `manage_options` (filter `lapsha_wp_tools_capability`) and a matching nonce. Cleanup never runs from a GET link.

## Uninstall

`uninstall.php` runs only when WordPress uninstalls the plugin (`WP_UNINSTALL_PLUGIN`).

**0.1.1** stores no options, custom tables, or persistent Lapsha settings. Per-user scan/job transients expire on their own. Uninstall does not touch site content (revisions, comments, posts). Translation files are plugin files, not site data.

## Frontend

No public shortcodes, REST routes, or front CSS in **0.1.x**.
