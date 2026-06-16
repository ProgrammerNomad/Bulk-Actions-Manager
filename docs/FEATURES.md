# Features

Detailed feature reference for Bulk Actions Manager.

---

## Dashboard

Quick overview of plugin activity.

| Widget | Displays |
|--------|----------|
| **Statistics** | Total, completed, running, failed, and scheduled jobs |
| **Recent Jobs** | Job name, action, status, date |
| **System Health** | PHP version, WordPress version, memory limit, max execution time, cron status, queue status |
| **Undo Summary** | Undo-available jobs, snapshot storage usage, snapshot retention |

---

## New Job

Main workspace with four steps:

```text
Step 1: Filter Content
Step 2: Preview Results
Step 3: Select Action
Step 4: Execute
```

Preview and result counts load automatically when the page opens (no separate preview click).

### Filter builder

**Content type:** Posts, pages, attachments, custom post types.

**Status:** Publish, draft, pending, future, private, trash.

**Taxonomy:**

| Taxonomy | Operators |
|----------|-----------|
| Categories | Equals, not equals |
| Tags | Contains, does not contain |
| Custom taxonomies | Supported automatically |

**Author:** Single or multiple authors.

**Date:**

| Field | Operators |
|-------|-----------|
| Created date | Before, after, between |
| Modified date | Before, after, between |

**Media:** Has featured image, missing featured image.

**SEO** (when Yoast or Rank Math is active):

| Plugin | Conditions |
|--------|------------|
| Yoast | Empty focus keyword, missing SEO title, missing meta description |
| Rank Math | Missing focus keyword, missing meta description |

**Content:**

| Field | Operators |
|-------|-----------|
| Title | Contains, does not contain |
| Content | Contains, does not contain |

**Content length:**

| Metric | Operators |
|--------|-----------|
| Character count | Less than, greater than |
| Word count | Less than, greater than |

**Metadata:**

| Field | Operators |
|-------|-----------|
| Meta key | Exists, missing |
| Meta value | Equals, not equals, contains, empty |

**Advanced query builder:** `AND` / `OR` with nested condition groups.

Example:

```text
Status = Publish
AND Category = News
AND Focus Keyword = Empty
```

### Results preview

- Total matching records
- Results Summary (status breakdown, top categories)
- First page of results (ID, title, status)
- Pagination for larger sets

### Action selection

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
| **Content** | Find & replace (title, content, excerpt), append, prepend |
| **Export** | Export IDs, CSV, JSON |

Each action shows a description panel with safety level (undo supported, recoverable, or cannot be undone).

### Execution settings

| Setting | Options / default |
|---------|-------------------|
| **Dry run** | Simulates the job without changes |
| **Batch size** | 10, 25 (default), 50, 100 |
| **Processing mode** | AJAX (recommended), background queue |

Destructive actions require confirmation via a WordPress admin dialog before the job starts.

---

## Job processing

**Job states:** Queued, running, paused, completed, failed, cancelled.

**Progress UI:** Progress bar, processed / total, estimated time, per-item errors.

**Controls:** Pause, resume, cancel.

---

## Jobs page

Lists all jobs: ID, name, action, status, records, created, finished.

**Runs view** (default) — one-time and undo executions. Status filters and **Type** column (`One-time`, `Undo`).

**Scheduled view** — `?page=bam-jobs&type=schedule` — recurring configs with Add / Edit / Run Now. Each run creates a job visible under Runs.

### Scheduled jobs

Examples:

- **Daily** — move old content to draft
- **Weekly** — clean posts missing SEO fields
- **Monthly** — orphan media cleanup

---

## Logs

Audit and recovery center.

**Columns:** Log ID, job ID, user, action, affected records, date, undo status.

**Log details:** Filters used, action executed, results, errors, snapshot info, undo availability.

When undo is supported, use **Undo Job** on the log detail screen. That creates a new undo job and a new log entry.

Example:

```text
Job #120  Action: Publish → Draft  Undo: Yes  [Undo Job]
  → Job #121  Action: Undo Job #120
```

---

## Tools

| Category | Tools |
|----------|-------|
| **Cleanup** | Remove revisions, remove auto drafts, empty trash |
| **Orphan cleanup** | Orphan attachments, orphan metadata |
| **Export** | Export jobs, export logs |

---

## Undo system

### Undoable actions

| Category | Restores |
|----------|----------|
| Status changes | Previous status |
| Author changes | Previous author |
| Category / tag actions | Original terms |
| Metadata changes | Original values |
| Featured image removal | Previous attachment |
| Find & replace | Original content |
| Move to trash | Post via `wp_untrash_post()` |

### Non-undoable actions

- Permanent delete
- Delete featured image file
- Delete media files

### Safety levels

| Level | Meaning |
|-------|---------|
| **Safe** | Full undo via snapshots |
| **Recoverable** | WordPress-native recovery where applicable |
| **Destructive** | Cannot be undone |

### Snapshots

Stored in `wp_bam_snapshots`. Only fields required for the action are saved, for example:

```json
{ "post_status": "publish" }
```

```json
{ "post_author": 4 }
```

```json
{ "thumbnail_id": 123 }
```

Retention is configurable under **Settings → Undo** (default 30 days).
