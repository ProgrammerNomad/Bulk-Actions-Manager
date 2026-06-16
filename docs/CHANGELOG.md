# Changelog

All notable changes to Bulk Actions Manager are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).  
Version numbers match the plugin header in `bulk-actions-manager/bulk-actions-manager.php`.

---

## [1.2.2] — 2026-06-16

### Fixed

- New Job filter refresh no longer shows **Cannot load bam-new-job** — filter forms use `bam_post_type` instead of WordPress core’s `post_type` query variable on plugin admin URLs.

---

## [1.2.1] — 2026-06-16

### Added

- New Job guided workflow: four step postboxes with always-on preview on page load.
- Results Summary card (status breakdown and top categories).
- Action description panel with undo / destructive messaging.
- Advanced Filters accordion (native `<details>` / `<summary>`).
- Jobs list status badges; Logs inline Undo column.
- Preview Job button; Start Job disabled until an action is selected.

### Changed

- Preview uses `found_posts` and paginated IDs — no full ID resolution on page load.

---

## [1.2.0] — 2026-06-16

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
| 1.2.1 | Recommended — New Job workflow and performance improvements. |
| 1.2.0 | Recommended — native WordPress admin UI refresh. |
| 1.1.3 | Recommended — unified Jobs page. |
| 1.1.2 | Recommended — processing hardening and WP 6.9 fixes. |

---

## Unreleased

### Added

- WordPress-style confirmation dialogs (jQuery UI / `wp-jquery-ui-dialog`) replace browser `confirm()` and `alert()` for destructive actions, job cancel, and tools.
