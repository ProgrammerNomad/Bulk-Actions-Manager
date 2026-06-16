=== Bulk Actions Manager ===
Contributors: nomadprogrammer
Tags: bulk edit, bulk actions, content management, batch processing, undo
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.2.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Filter, preview, modify, export, schedule, and manage large amounts of WordPress content safely using batch processing.

== Description ==

Bulk Actions Manager helps you perform large-scale content operations safely:

Filter → Preview → Action → Process → Log → Undo

Unlike simple bulk-delete plugins, every workflow supports preview, dry run, logging, and undo (when technically possible).

= Key features =

* Edit.php-style filter bar with post preview table
* Batch processing via AJAX and WP Cron background queue
* Job queue with pause, resume, and cancel
* Full audit logs with undo support
* Scheduled recurring jobs
* Export jobs and logs
* Yoast SEO and Rank Math filter integration (when those plugins are active)

= Safety =

* Dry run mode
* Snapshot-based undo for supported actions
* Confirmation before destructive operations
* Per-item error tracking

= Permissions =

Administrators receive the `manage_bulk_actions_manager` capability on activation. Developers may change the required capability with the `bam_capability` filter.

= Uninstall =

By default, plugin data is retained when the plugin is deleted. Enable **Drop all plugin data on uninstall** in Settings to remove database tables and options on deletion.

== Installation ==

1. Upload the `bulk-actions-manager` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Open **Bulk Actions Manager** in the admin menu.

== Frequently Asked Questions ==

= Does this work on shared hosting? =

Yes. Jobs process in small batches to stay within PHP time and memory limits. Background mode relies on WP Cron; on low-traffic sites, trigger cron via your host or a real cron job hitting `wp-cron.php`.

= How many posts can a single job handle? =

There is no hard limit, but filter resolution defaults to 100,000 matching IDs. Use the `bam_max_filter_results` filter to raise or lower this cap.

= Is undo always available? =

No. Permanent deletes and file deletions cannot be undone. Supported changes store snapshots according to your retention settings.

== Screenshots ==

1. New Job - filter bar and preview table
2. Jobs list with status filters
3. Log detail with undo option

== Changelog ==

= 1.2.2 =
* Fix: New Job filter refresh no longer shows "Cannot load bam-new-job" (avoid core `post_type` query var on plugin admin URLs)

= 1.2.1 =
* New Job guided workflow: 4-step postboxes with always-on preview on page load
* Performance: count + paginated preview query (no full ID resolution on page load)
* Results Summary card with status and category breakdown
* Action description panel with undo/destructive messaging
* Preview Job button; Start Job disabled until action selected
* Advanced Filters accordion (native details/summary)
* Jobs list status badges; Logs inline Undo button column

= 1.2.0 =
* Native WordPress admin UI: postboxes, form-table, Settings API, widefat tables
* Dashboard server-rendered (no AJAX); Recent Jobs first, then Running and Undoable postboxes
* New Job 4-step wizard with on-demand preview (`preview=1`)
* Minimal custom CSS (status badges, progress bars only); removed custom widget/card layout
* Settings page uses WordPress Settings API

= 1.1.3 =
* Unified Jobs admin page: Runs and Scheduled views via `type` query parameter
* Removed separate Scheduled Jobs menu item; old URLs redirect automatically
* Type column on job runs (One-time, Undo)

= 1.1.2 =
* Hardening: atomic batch claiming, job-level locks, queue mutex
* Fix snapshot cleanup log status ordering
* Filterable capability via `bam_capability`
* SEO filters only apply when Yoast or Rank Math is active
* Versioned DB migration runner
* REST API permission callback fix for multi-method routes
* WordPress 6.9 compatibility for admin month filter dropdown

= 1.1.0 =
* Refactored admin list pages to native `WP_List_Table`
* Edit.php-style filter bar on New Job page
* Server-rendered Jobs, Logs, and Scheduled pages

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.2.1 =
New Job workflow redesign with always-on preview and performance-safe queries. Recommended for all sites.

= 1.2.0 =
Admin UI aligned with native WordPress screens. Recommended for all sites.

= 1.1.3 =
Unified Jobs page with Runs and Scheduled tabs. Recommended for all sites.

= 1.1.2 =
