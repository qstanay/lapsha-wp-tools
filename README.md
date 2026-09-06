# Lapsha WP Tools

Free and open-source WordPress toolkit for administrators and developers. Every feature stays free — no Premium, Pro, or locked modules.

**0.1.1** ships a **Database Cleaner** (scan leftover WordPress data, review it, confirm, then delete) and a **Russian** translation of that UI.

## Requirements

- WordPress 6.4+
- PHP 7.4+

## Local WordPress

The repository **is** the plugin. Compose mounts this folder into `wp-content/plugins/lapsha-wp-tools`:

```bash
docker compose up -d
```

Then open http://localhost:8080/wp-admin (`admin` / `admin`).

## Documentation

- [Architecture](docs/architecture.md) — bootstrap, modules, admin shell
- [Database Cleaner](docs/database-cleaner.md) — how scan and delete work

## License

[GPL-2.0-or-later](LICENSE)
