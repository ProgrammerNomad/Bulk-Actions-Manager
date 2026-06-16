# Bulk Actions Manager

A free, open-source WordPress plugin for filtering, previewing, modifying, exporting, scheduling, and managing large amounts of content safely using batch processing.

Unlike traditional bulk delete plugins, Bulk Actions Manager is built around a simple workflow:

```text
Filter → Preview → Action → Process → Log → Undo
```

**Focus areas:** safety, performance, recoverability, transparency, and large-scale content management.

---

## Table of Contents

- [Core Principles](#core-principles)
- [Requirements](#requirements)
- [Installation](#installation)
- [Main Navigation](#main-navigation)
- [Features](#features)
- [Undo System](#undo-system)
- [Database Tables](#database-tables)
- [Settings & Permissions](#settings--permissions)
- [Version 1.0 Scope](#version-10-scope)
- [Mission](#mission)
- [License](#license)

---

## Core Principles

### Safe by Default

Every action provides:

- Preview
- Dry run
- Confirmation
- Logging
- Undo (when technically possible)

### Scalable

Works on shared hosting, VPS, dedicated servers, and large websites. Operations never process thousands of records in a single request.

All actions use:

- AJAX batch processing
- Background processing
- Scheduled processing

### Lightweight

No frontend frameworks.

| Layer    | Stack |
|----------|-------|
| Backend  | PHP, WordPress APIs, `WP_Query`, REST API, WP Cron |
| Frontend | Vanilla JavaScript, native WordPress components, minimal CSS |

**Avoided:** React build systems, Vue, Bootstrap, jQuery dependencies, and large UI libraries.

---

## Requirements

- WordPress 6.0 or higher (recommended)
- PHP 7.4 or higher
- MySQL 5.7+ or MariaDB 10.3+

---

## Installation

1. Download or clone this repository into `wp-content/plugins/bulk-actions-manager/`.
2. Activate **Bulk Actions Manager** from the WordPress **Plugins** screen.
3. Open **Bulk Actions Manager** in the admin menu to configure settings and create your first job.

---

## Main Navigation

```text
Bulk Actions Manager
├── Dashboard
├── New Job
├── Jobs
├── Logs
├── Scheduled Jobs
├── Tools
└── Settings
```

---

## Features

### Dashboard

Quick overview of plugin activity.

| Widget | Displays |
|--------|----------|
| **Statistics** | Total, completed, running, failed, and scheduled jobs |
| **Recent Jobs** | Job name, action, status, date |
| **System Health** | PHP version, WordPress version, memory limit, max execution time, cron status, queue status |
| **Undo Summary** | Undo-available jobs, snapshot storage usage, snapshot retention |

### New Job

Main workspace with four sections:

```text
+----------------------------------+
| Filter Builder                   |
+----------------------------------+
| Results Preview                  |
+----------------------------------+
| Action Selection                 |
+----------------------------------+
| Execution Settings               |
+----------------------------------+
```

#### Filter Builder

**Content type:** Posts, pages, attachments, custom post types.

**Status filters:** Publish, draft, pending, future, private, trash.

**Taxonomy filters:**

| Taxonomy | Operators |
|----------|-----------|
| Categories | Equals, not equals |
| Tags | Contains, does not contain |
| Custom taxonomies | Supported automatically |

**Author filters:** Single author, multiple authors.

**Date filters:**

| Field | Operators |
|-------|-----------|
| Created date | Before, after, between |
| Modified date | Before, after, between |

**Media filters:** Has featured image, missing featured image.

**SEO filters:**

| Plugin | Conditions |
|--------|------------|
| Yoast | Empty focus keyword, missing SEO title, missing meta description |
| Rank Math | Missing focus keyword, missing meta description |

**Content filters:**

| Field | Operators |
|-------|-----------|
| Title | Contains, does not contain |
| Content | Contains, does not contain |

**Content length:**

| Metric | Operators |
|--------|-----------|
| Character count | Less than, greater than |
| Word count | Less than, greater than |

**Metadata filters:**

| Field | Operators |
|-------|-----------|
| Meta key | Exists, missing |
| Meta value | Equals, not equals, contains, empty |

**Advanced query builder:** Supports `AND` / `OR` with nested conditions.

Example:

```text
Status = Publish
AND Category = News
AND Focus Keyword = Empty
```

#### Results Preview

Shown before any action executes.

- Total matching records
- First 20 results (columns: ID, title, type, status, author, date)
- Refresh preview, export preview, dry run simulation

#### Action Selection

Actions are grouped by type.

| Group | Actions |
|-------|---------|
| **Status** | Publish, draft, pending review, private |
| **Delete (safe)** | Move to trash |
| **Delete (destructive)** | Permanently delete |
| **Media** | Remove featured image, delete featured image file, delete attached media |
| **Author** | Change author |
| **Category** | Add, remove, replace category |
| **Tag** | Add, remove, replace tag |
| **Metadata** | Add, update, remove meta |
| **Content** | Find & replace (title, content, excerpt), append content, prepend content |
| **Export** | Export IDs, CSV, JSON |

#### Execution Settings

| Setting | Options / Default |
|---------|-------------------|
| **Dry run** | No changes made; shows e.g. *This action would affect 2,341 records.* |
| **Batch size** | 10, 25 (default), 50, 100 |
| **Processing mode** | AJAX (recommended), background queue (recommended for very large sites) |

### Job Processing

Each action creates a job.

**Job states:** Queued, running, paused, completed, failed, cancelled.

**Progress UI:** Progress bar, processed/remaining records, estimated time, errors.

**Controls:** Pause, resume, cancel.

### Jobs Page

Lists all jobs with columns: job ID, name, action, status, records, created, finished.

**Filters:** Running, queued, completed, failed, scheduled.

**Job details:** Summary (action, filters, duration, results), progress, undo information (availability and expiration).

### Logs Page

Primary audit and recovery center.

**Columns:** Log ID, job ID, user, action, affected records, date, undo status.

**Log details:** Filters used, action executed, results, errors, snapshot information, undo availability.

When an action supports undo, an **Undo Job** button appears on the log details screen.

**Undo flow:**

1. Snapshot validation
2. Reverse operation creation
3. Undo job execution
4. New log entry created

Example:

```text
Job #120
Action: Publish → Draft
Undo Available: Yes
[Undo Job]
```

Creates:

```text
Job #121
Action: Undo Job #120
```

### Scheduled Jobs

Automate recurring bulk operations.

Examples:

- **Daily** — Move old content to draft
- **Weekly** — Remove empty SEO content
- **Monthly** — Clean orphaned media

### Tools

| Category | Tools |
|----------|-------|
| **Cleanup** | Remove revisions, remove auto drafts, empty trash |
| **Orphan cleanup** | Orphan attachments, orphan metadata |
| **Export** | Export jobs, export logs |

---

## Undo System

Core feature for recoverability after bulk changes.

### Undoable Actions

| Category | Restores |
|----------|----------|
| Status changes | Publish, draft, pending, private |
| Author changes | Previous author |
| Category actions | Original categories |
| Tag actions | Original tags |
| Metadata changes | Original values |
| Featured image removal | Previous attachment |
| Find & replace | Original content |
| Move to trash | Post via `wp_untrash_post()` |

### Non-Undoable Actions

- Permanent delete
- Delete featured image file
- Delete media files

### Safety Levels

| Level | Badge | Meaning |
|-------|-------|---------|
| **Safe** | ✓ Undo Supported | Full undo via snapshots |
| **Recoverable** | ↺ Recoverable | WordPress native recovery |
| **Destructive** | ⚠ Cannot Be Undone | No recovery possible |

### Snapshot System

Undo is powered by snapshots stored in `wp_bam_snapshots`.

Only required data is stored per action, for example:

**Status change:**

```json
{
  "post_status": "publish"
}
```

**Author change:**

```json
{
  "post_author": 4
}
```

**Featured image:**

```json
{
  "thumbnail_id": 123
}
```

**Snapshot fields:** ID, job ID, object type, object ID, action type, snapshot data, created at, expires at.

---

## Database Tables

```text
wp_bam_jobs
wp_bam_job_items
wp_bam_logs
wp_bam_snapshots
wp_bam_schedules
```

---

## Settings & Permissions

### General

- Default batch size
- Default processing mode

### Undo

| Setting | Options | Default |
|---------|---------|---------|
| Snapshot retention | 7 days, 30 days, 90 days, forever | 30 days |
| Enable undo | On / off | — |

### Logging

- Enable logs
- Retention period

### Permissions

Required capability:

```text
manage_bulk_actions_manager
```

---

## Version 1.0 Scope

Included in v1.0:

- Filter builder, preview system, dry run
- Batch processing with AJAX and background modes
- Status, category, tag, author, metadata, and featured image actions
- Find & replace and export actions
- Jobs system, logs system, undo system, snapshot storage
- Scheduled jobs, progress tracking, pause / resume
- WordPress native admin UI

---

## Mission

Bulk Actions Manager provides a safe, scalable, and fully auditable way to perform bulk content operations with preview, logging, scheduling, and undo capabilities—while remaining free, open source, lightweight, and developer-friendly.

---

## License

This plugin is free and open source. See [LICENSE](LICENSE) for details.
