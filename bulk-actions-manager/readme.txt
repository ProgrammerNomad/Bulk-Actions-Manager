=== Bulk Actions Manager ===
Contributors: ProgrammerNomad
Tags: bulk actions, bulk edit, content management, content cleanup, batch processing, posts, pages, media, automation
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Safely filter, preview, update, export, schedule, and manage WordPress content in bulk using batch processing, audit logs, and undo support.

== Description ==

Bulk Actions Manager is a content operations plugin for WordPress administrators.

Instead of making bulk changes blindly, it follows a safe workflow:

Filter → Preview → Action → Process → Log → Undo

Use it to update thousands of posts, clean up content, change authors, manage media, export records, or schedule recurring maintenance - with preview, logging, and undo where supported.

= Built for safety =

* Preview matching content before changes
* Dry run mode
* Audit logging for every job
* Snapshot-based undo (supported actions)
* Configurable batch processing
* Live progress for AJAX jobs, with pause, resume, and cancel controls
* Clear warnings and confirmations for destructive actions

= Content filters =

* Post type and post status
* Categories and tags
* Authors
* Date created and date modified
* Featured image status
* Meta fields (exists, missing, value)
* Title and content search
* SEO metadata (Yoast SEO or Rank Math, when active)
* Advanced filter panel on the New Job screen

Supported content: posts, pages, media, and public custom post types.

= Available actions =

**Status:** Publish, Draft, Pending Review, Private

**Taxonomy:** Add, remove, or replace categories and tags

**Author:** Change author

**Metadata:** Add, update, or remove meta

**Content:** Find and replace, append, prepend

**Media:** Remove featured image, delete featured image file, delete attached media

**Delete:** Move to trash, permanently delete

**Export:** Export IDs, CSV, or JSON

= Undo system =

Snapshot-based undo is available for status, author, taxonomy, metadata, featured image removal, find and replace, and move-to-trash actions.

Permanent deletes and media file deletions cannot be undone. Each action shows its safety level before execution.

= Batch processing =

Jobs run in small batches (10-100 items per batch) to help avoid PHP timeouts, memory limits, and browser timeouts on shared hosting.

**AJAX mode** - live progress while an admin screen is open.

**Background mode** - WP Cron queue for large jobs.

= Scheduled jobs =

Create and edit recurring jobs from the New Job workflow, then manage them from the Jobs → Scheduled view.

Supported frequencies include:

* Hourly
* Daily
* Weekly
* Monthly

= Native WordPress experience =

Uses WP_List_Table, the Settings API, REST API, WP Cron, Dashicons, and standard admin UI patterns. No React build step or third-party admin UI frameworks.

= Permissions =

Administrators receive the `manage_bulk_actions_manager` capability on activation. Customize with the `bam_capability` filter.

= Uninstall =

Plugin data is retained by default when the plugin is deleted. Enable **Drop all plugin data on uninstall** in Settings to remove database tables and options.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/bulk-actions-manager/`.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Open **Bulk Actions Manager → New Job**.
4. Configure filters and preview matching content.
5. Select an action and run the job or perform a dry run.

== Frequently Asked Questions ==

= Does the plugin support custom post types? =

Yes. Any public custom post type registered in WordPress can be filtered and processed.

= Can I preview changes before running a job? =

Yes. The New Job screen shows a match count, results summary, and preview table on load. Use the preview and dry run options on the New Job screen before starting a real job.

= Can I undo bulk actions? =

Many actions support snapshot-based undo from the Logs screen. Permanent deletes and file deletions cannot be undone.

= Will this work on shared hosting? =

Yes. Jobs process in batches. For large background jobs, ensure WP Cron runs reliably (system cron hitting `wp-cron.php` is recommended on low-traffic sites).

= Does the plugin support large websites? =

Yes. Bulk Actions Manager is designed to handle large content sets using batched processing and configurable limits. Advanced sites can further tune limits and behavior using plugin hooks and settings.

= Does the plugin require Yoast SEO or Rank Math? =

No. SEO-related filters appear only when a supported SEO plugin is installed and active.

== Screenshots ==

1. Dashboard overview with job statistics and system health
2. New Job guided workflow (filter, preview, action, execute)
3. Content filtering, results summary, and preview table
4. Action selection with safety and undo description panel
5. Job execution with live progress and batch processing status
6. Jobs screen: Runs tab with status badges and row actions
7. Logs screen with affected counts, source labels, and undo actions
8. Tools screen with cleanup and export utilities
9. Settings screen for batch size, processing mode, and retention

== Changelog ==

= 1.3.0 =

* Sequential background queue: one active job processed at a time, eliminating concurrent conflicts
* Background jobs now run as the original job owner via wp_set_current_user() - permanent delete and other capability-sensitive actions now work correctly in cron context
* Schedule anti-flood: schedule runner skips a tick when the queue is busy and creates at most one job per tick when idle
* Next-run timestamps use site local time instead of UTC
* New Job is now the single editor for jobs and schedules - supports editing queued/paused jobs and editing/creating schedules from one page
* Jobs page redesigned as operations dashboard - no more schedule builder form on that page; Edit Schedule links to New Job
* Job editing with strict safety rules: filter, action, and payload locked once processing has started; only name, batch size, and mode are editable on partial jobs
* Clone job support from job detail and list view (creates a new queued job with same configuration)
* Job detail page shows mode, failed count, last error, and inline pause/resume/cancel controls
* Destructive tools (empty trash, remove revisions, auto-drafts, orphan attachments, orphan metadata) now run as batched tool-jobs through the normal queue engine - no more timeouts, full audit trail and progress tracking
* Export tools (export_jobs, export_logs) now trigger real browser JSON file downloads
* Tool actions appear in the Logs page with Source column distinguishing Job vs Tool entries
* Logs list table adds Source, Failed, and improved Job link columns
* Jobs admin actions run on admin_init - fixes blank page after Resume, Pause, Cancel, and bulk actions
* Reason-aware outcomes for trash, permanent delete, status, author, and featured image remove (success, skipped, failed with specific messages)
* Job and log detail pages show separate Errors and Skipped item lists; skipped items do not count toward auto-pause
* Per-batch auto-pause threshold (0 disables); resume no longer re-pauses due to historical failure counts
* Admin sidebar menu title is now Bulk Actions
* Jobs list: Runs and Scheduled are primary tabs above status or active/inactive filters
* Native dialog modals for confirmations and alerts; fixes Start Job with Permanently Delete after confirm

= 1.2.2 =

* Fixed New Job filter refresh showing "Cannot load bam-new-job" by using `bam_post_type` instead of WordPress core `post_type` on plugin admin URLs
* Added redirect for legacy filter URLs that still pass `post_type`

= 1.2.1 =

* New Job guided workflow: four step postboxes with always-on preview on page load
* Performance: count and paginated preview query (no full ID resolution on page load)
* Results Summary card with status and category breakdown
* Action description panel with undo and destructive messaging
* Advanced Filters accordion (native details/summary)
* Jobs list status badges; Logs inline Undo column
* Preview Job button; Start Job disabled until an action is selected

= 1.2.0 =

* Native WordPress admin UI: postboxes, form-table, Settings API, widefat tables
* Dashboard server-rendered (no AJAX)
* Minimal custom CSS (status badges, progress bars, hidden utility class)
* Settings page uses WordPress Settings API

= 1.1.3 =

* Unified Jobs page with Runs and Scheduled views
* Removed separate Scheduled Jobs menu; old URLs redirect automatically
* Type column on job runs (One-time, Undo)

= 1.1.2 =

* Hardening: atomic batch claiming, job-level locks, queue mutex
* Filterable capability via `bam_capability`
* Versioned DB migration runner
* SEO filters apply only when Yoast or Rank Math is active
* WordPress 6.9 compatibility for admin month filter dropdown
* REST API permission callback fix for multi-method routes

= 1.1.0 =

* Refactored admin list pages to native WP_List_Table
* Edit.php-style filter bar on New Job page
* Server-rendered Jobs, Logs, and Scheduled pages

= 1.0.0 =

* Initial public release with core filters, preview, dry run, batch processing, jobs, logs, and the initial action framework

== Upgrade Notice ==

= 1.3.0 =

Production hardening release with sequential queue, safer schedules, reason-aware job outcomes (Errors vs Skipped), per-batch auto-pause, fixed Jobs redirects, and Bulk Actions sidebar menu.

= 1.2.2 =

Fixes New Job filter refresh on sites using category, SEO, or other filters. Recommended for all sites.

= 1.2.1 =

New Job workflow redesign with always-on preview and performance-safe queries. Recommended for all sites.

= 1.2.0 =

Admin UI aligned with native WordPress screens. Recommended for all sites.

== License ==

This plugin is licensed under the GPL v2 or later.

== Credits ==

Developed and maintained by NomadProgrammer.

GitHub: https://github.com/ProgrammerNomad/Bulk-Actions-Manager
