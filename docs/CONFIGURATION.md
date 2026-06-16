# Configuration

Settings, permissions, processing, and maintenance for Bulk Actions Manager.

---

## Settings

### General

- Default batch size
- Default processing mode (AJAX or background)
- Drop all plugin data on uninstall (off by default)

### Undo

| Setting | Options | Default |
|---------|---------|---------|
| Snapshot retention | 7 days, 30 days, 90 days, forever | 30 days |
| Enable undo | On / off | On |

### Logging

- Enable logs
- Log retention period

---

## Permissions

Default capability:

```text
manage_bulk_actions_manager
```

Granted to the **Administrator** role on activation. All admin screens, REST endpoints, and export downloads require this capability.

Custom capability:

```php
add_filter( 'bam_capability', function () {
	return 'edit_others_posts';
} );
```

---

## Background processing and cron

| Mode | Behavior |
|------|----------|
| **AJAX** | Browser-driven batches while an admin keeps the job screen open |
| **Background** | WP Cron queue (`bam_process_queue`) processes jobs server-side |

On low-traffic sites, WP Cron only runs when the site is visited. For reliable background jobs, configure a system cron job to hit `wp-cron.php` on a fixed interval.

### Concurrency safeguards (1.1.2+)

- Queue mutex prevents overlapping queue processors
- Per-job transient locks prevent duplicate batch processing
- Job items are atomically claimed (`pending` → `processing`)
- Stale `processing` items reset to `pending` after 30 minutes

### Scheduled hooks

```text
bam_process_queue
bam_run_schedules
bam_cleanup_snapshots
bam_cleanup_logs
bam_cleanup_stale_jobs
```

---

## Database tables

```text
wp_bam_jobs
wp_bam_job_items
wp_bam_logs
wp_bam_snapshots
wp_bam_schedules
```

Table prefix follows your WordPress `$table_prefix` (default `wp_`).

---

## Scale limits

Filter resolution loads matching post IDs in pages of 500. By default, at most **100,000** IDs are returned per filter:

```php
add_filter( 'bam_max_filter_results', function () {
	return 50000;
} );
```

Jobs process in configurable batch sizes. Very large jobs may take significant time on shared hosting — use background mode and reliable cron for best results.

---

## Uninstall and data removal

By default, deleting the plugin **retains** database tables and settings.

To remove all plugin data on uninstall, enable **Drop all plugin data on uninstall** under **Settings → General**. When enabled, `uninstall.php` drops all `wp_bam_*` tables and removes related options and cron events.

---

## Deployment

Copy only the inner plugin folder to WordPress:

```text
Bulk-Actions-Manager/bulk-actions-manager/  →  wp-content/plugins/bulk-actions-manager/
```

Or clone and copy:

```bash
git clone https://github.com/ProgrammerNomad/Bulk-Actions-Manager.git
# Windows example:
xcopy /E /I Bulk-Actions-Manager\bulk-actions-manager C:\path\to\wordpress\wp-content\plugins\bulk-actions-manager
```

Download a release from [GitHub Releases](https://github.com/ProgrammerNomad/Bulk-Actions-Manager/releases) and upload the `bulk-actions-manager` folder via **Plugins → Add New → Upload**, or copy it manually.

---

## Architecture notes

| Layer | Stack |
|-------|-------|
| Backend | PHP, WordPress APIs, `WP_Query`, REST API, WP Cron |
| Frontend | Vanilla JavaScript, WordPress admin components, minimal CSS |

No React, Vue, or Bootstrap build step. Confirmations use WordPress jQuery UI dialogs (`wp-jquery-ui-dialog`).

### Core principles

- **Safe by default** — preview, dry run, confirmation, logging, undo when possible
- **Scalable** — batched processing, never thousands of records in one request
- **Auditable** — full job and log history with snapshot-based recovery
