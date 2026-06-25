# Changelog

All notable changes to Bulk Actions Manager are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).  
Version numbers match the plugin header in `bulk-actions-manager/bulk-actions-manager.php`.

---

## [1.3.0] - 2026-06-25 - Production Hardening Release

### Added

- **Sequential queue processing** - only one background job runs at a time. Each cron tick picks up the active or oldest queued job and advances it through up to 10 batches before stopping. No more concurrent conflicts.
- **Job owner impersonation** - `Job_Processor` calls `wp_set_current_user()` with the original job owner before processing each batch. Permanent delete and other capability-sensitive operations now work correctly when triggered via cron.
- **Schedule anti-flood** - `Schedule_Runner::run_due()` skips the tick entirely if the queue has active work, and creates at most one new job per tick when the queue is idle.
- **Site-local next-run timestamps** - `calculate_next_run()` uses `current_time('timestamp')` instead of `gmdate()`, so daily/weekly schedules fire at the expected local wall-clock time.
- **New Job as single workflow editor** - supports four URL modes: new job, edit schedule (`?schedule_id=`), edit queued/paused job (`?job_id=`), and clone prefill (`?clone_job_id=`). Schedule create/edit is now on this page.
- **Save as Schedule on New Job** - the Execute step now includes a schedule form section with name, frequency, and active toggle, covering both create and edit cases.
- **Job edit rules** - `PUT /jobs/{id}` enforces strict field restrictions: filter, action type, and action payload are locked once `processed_items > 0`; only name, batch size, and processing mode can change on partially processed jobs.
- **Clone job** - `POST /jobs/{id}/clone` creates a new queued job with the same configuration but no progress or log copy. Accessible from job list and detail.
- **Edit and Clone row actions** in the Runs list: Edit links to New Job for queued/paused jobs; Clone links to New Job prefill for terminal jobs.
- **Improved job detail page** - shows mode, failed count, last error, created/finished timestamps, and inline pause/resume/cancel controls without needing the AJAX runner.
- **Destructive tools become tool-jobs** - `empty_trash`, `remove_revisions`, `remove_auto_drafts`, `orphan_attachments`, and `orphan_metadata` now create `tool.*` jobs through the normal Job_Manager and queue engine. Full progress tracking, pause/resume/cancel, and audit logging. No more synchronous timeouts.
- **Export tools trigger real browser downloads** - `export_jobs` and `export_logs` return JSON data with `download: true`; the Tools page JS creates a Blob URL and clicks a temporary link to initiate the download.
- **Tool audit log entries** - immediate tool actions (exports) and tool-jobs both write to the logs table. Tool-jobs use `Logger::create_for_job()`; immediate tools use the new `Logger::create_for_tool()`.
- **Logs page Source column** - distinguishes Job, Tool (immediate), and Tool Job entries. Failed count column added. Job ID column shows "-" for tool entries with no associated job.
- **`Job_Queue::has_active_work()`** and **`Job_Repository::get_running_job_id()`** - helpers used by the sequential queue and schedule anti-flood.
- **`Job_Item_Repository::delete_for_job()`** - used when a queued job's filter is fully replaced on edit.
- **Background mode UX** - when processing mode is `background`, submitting a job from New Job no longer starts the live AJAX runner. A notice with a direct link to the job detail is shown instead.
- **Jobs admin early action handling** - single-job and bulk actions run on `admin_init` before headers are sent, fixing blank pages after Resume, Pause, Cancel, and bulk actions.
- **Reason-aware job outcomes** - supported actions (trash, permanent delete, status, author, featured image remove) return success, skipped, or failed with specific per-item messages.
- **Skipped vs Errors on job and log detail** - job progress and log detail pages show separate Errors (real failures) and Skipped (already in desired state) lists.
- **Per-batch auto-pause** - max errors setting applies to failures in the current batch only; set to `0` to disable auto-pause. Resume no longer instantly re-pauses due to historical failure counts.
- **Sidebar menu title** - admin menu shows **Bulk Actions** (page title remains Bulk Actions Manager).
- **Jobs list mode tabs** - Runs and Scheduled appear as primary `nav-tab` tabs above status/active filters on the Jobs list page.
- **Native dialog modals** - reusable `bamConfirm` / `bamAlert` use the HTML `<dialog>` element instead of jQuery UI; fixes Start Job with Permanently Delete and standardizes confirmations across admin screens.

### Changed

- `Job_Manager::resume()` clears stale `error_message` when resuming a paused job.
- Job detail live UI syncs status badge, controls, and last-error row from REST/batch responses.
- Bulk action redirects land on the appropriate status tab (Running, Paused, Cancelled).
- Jobs page schedule "Add Schedule" button now links to New Job instead of an on-page form. Schedule row "Edit" links to `?page=bam-new-job&schedule_id=`.
- `Jobs_List_Table::column_name_schedule()` "Edit" link updated to New Job.
- `Job_Manager::resume()` no longer immediately fires a batch - the cron queue picks it up on the next tick (avoids double-processing on manual resume from the REST API).
- `Tool_Action` class registered in `Action_Registry` for all five destructive tool slugs. Tool actions do not appear in the New Job action selector (they are created programmatically).

---

## [1.2.2] - 2026-06-16

### Fixed

- New Job filter refresh no longer shows **Cannot load bam-new-job** - filter forms use `bam_post_type` instead of WordPress core’s `post_type` query variable on plugin admin URLs.

---

## [1.2.1] - 2026-06-16

### Added

- New Job guided workflow: four step postboxes with always-on preview on page load.
- Results Summary card (status breakdown and top categories).
- Action description panel with undo / destructive messaging.
- Advanced Filters accordion (native `<details>` / `<summary>`).
- Jobs list status badges; Logs inline Undo column.
- Preview Job button; Start Job disabled until an action is selected.

### Changed

- Preview uses `found_posts` and paginated IDs - no full ID resolution on page load.

---

## [1.2.0] - 2026-06-16

### Changed

- Admin UI aligned with native WordPress screens (postboxes, `form-table`, Settings API, `widefat` tables).
- Dashboard server-rendered (no AJAX); Recent Jobs, Running, and Undoable sections.
- Settings page uses WordPress Settings API.
- Minimal custom CSS (status badges, progress bars, `.bam-hidden` only).

---

## [1.1.3]

### Changed

- Unified Jobs admin page: **Runs** and **Scheduled** views via `?page=bam-jobs&type=schedule`.
- Removed separate Scheduled Jobs menu item; old `bam-scheduled` URLs redirect automatically.
- Type column on job runs (One-time, Undo).

---

## [1.1.2]

### Added

- Atomic batch claiming, job-level locks, and queue mutex for safer concurrent processing.
- Filterable capability via `bam_capability`.
- Versioned DB migration runner.

### Fixed

- Snapshot cleanup log status ordering.
- REST API permission callback for multi-method routes.
- WordPress 6.9 compatibility for admin month filter dropdown.

### Changed

- SEO filters apply only when Yoast SEO or Rank Math is active.

---

## [1.1.0]

### Changed

- Refactored admin list pages to native `WP_List_Table`.
- Edit.php-style filter bar on New Job page.
- Server-rendered Jobs, Logs, and Scheduled pages.

---

## [1.0.0]

### Added

- Initial release: filter builder, preview, dry run, batch processing (AJAX + background).
- Status, category, tag, author, metadata, and featured image actions.
- Find & replace, export, jobs, logs, undo snapshots, scheduled jobs.
- Pause, resume, cancel, and progress tracking.

---

## Upgrade notes

| Version | Recommendation |
|---------|----------------|
| 1.2.2 | Recommended for all sites using New Job filters. |
| 1.2.1 | Recommended - New Job workflow and performance improvements. |
| 1.2.0 | Recommended - native WordPress admin UI refresh. |
| 1.1.3 | Recommended - unified Jobs page. |
| 1.1.2 | Recommended - processing hardening and WP 6.9 fixes. |

---

## Unreleased

### Added

- WordPress-style confirmation dialogs (jQuery UI / `wp-jquery-ui-dialog`) replace browser `confirm()` and `alert()` for destructive actions, job cancel, and tools.
