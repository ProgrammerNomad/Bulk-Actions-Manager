# Bulk Actions Manager

Filter, preview, modify, export, schedule, and manage large amounts of WordPress content safely using batch processing.

**Version:** 1.2.2 · **Requires:** WordPress 6.0+, PHP 7.4+  
**Repository:** [github.com/ProgrammerNomad/Bulk-Actions-Manager](https://github.com/ProgrammerNomad/Bulk-Actions-Manager)  
**Author:** [NomadProgrammer](https://github.com/ProgrammerNomad)

```text
Filter → Preview → Action → Process → Log → Undo
```

---

## Quick start

1. Copy the `bulk-actions-manager` folder to `wp-content/plugins/bulk-actions-manager/`.
2. Activate **Bulk Actions Manager** on the **Plugins** screen.
3. Open **Bulk Actions Manager → New Job**, set filters, preview results, choose an action, then run or dry-run the job.

The main plugin file must live at:

```text
wp-content/plugins/bulk-actions-manager/bulk-actions-manager.php
```

---

## Requirements

| Requirement | Minimum |
|-------------|---------|
| WordPress | 6.0 (tested through 6.9) |
| PHP | 7.4 |
| Database | MySQL 5.7+ or MariaDB 10.3+ |
| Capability | `manage_bulk_actions_manager` (granted to administrators on activation) |

Optional: **Yoast SEO** or **Rank Math** for SEO filters on the New Job screen.

---

## Admin menu

```text
Bulk Actions Manager
├── Dashboard
├── New Job
├── Jobs        (runs + scheduled)
├── Logs
├── Tools
└── Settings
```

---

## Highlights

- Edit.php-style filters with live preview and dry run
- Batch processing via AJAX or background queue (WP Cron)
- Jobs with pause, resume, and cancel
- Audit logs with snapshot-based undo (when supported)
- Scheduled recurring jobs
- Export jobs, logs, and filtered content
- Native WordPress admin UI (no React/Vue build step)

---

## Documentation

| Document | Contents |
|----------|----------|
| [Features](docs/FEATURES.md) | Filters, actions, jobs, logs, undo, tools |
| [Configuration](docs/CONFIGURATION.md) | Settings, permissions, cron, scale limits, uninstall |
| [Changelog](docs/CHANGELOG.md) | Version history and upgrade notes |

WordPress.org plugin readme: [`bulk-actions-manager/readme.txt`](bulk-actions-manager/readme.txt)

---

## Contributing

Issues and pull requests are welcome on [GitHub](https://github.com/ProgrammerNomad/Bulk-Actions-Manager).

---

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
