# Screenshots

Operational guide for plugin admin screenshots used on WordPress.org and in the GitHub README.

---

## UI design principle (v1.3.0)

> **Feels like core WordPress admin, just more polished and better organized.**

Use default WordPress admin color scheme, native components (`postbox`, `widefat`, `form-table`, Settings API, `WP_List_Table`), and dismiss unrelated admin notices before every capture.

Custom BAM styling is limited to layout, spacing, small status badges, and stat chips - not a separate visual language.

---

## Purpose

Screenshot numbers are **fixed** and shared by:

- [`bulk-actions-manager/readme.txt`](../bulk-actions-manager/readme.txt) - WordPress.org `== Screenshots ==` section (numbered captions only)
- [`README.md`](../README.md) - GitHub image embeds

Do not renumber captions or rename files after release prep. Replace image files in place only.

---

## File naming

All screenshots live in the **plugin root** (same folder as `bulk-actions-manager.php`):

```text
bulk-actions-manager/
├── screenshot-1.png
├── screenshot-2.png
…
└── screenshot-9.png
```

WordPress.org maps caption `1.` to `screenshot-1.png`, `2.` to `screenshot-2.png`, and so on.

GitHub README paths (repo root is one level above the plugin folder):

```markdown
![Dashboard](bulk-actions-manager/screenshot-1.png)
```

---

## Master release matrix (v1.3.0)

| # | Page | Ready level | Code/UI changes | Exact capture state |
|---|------|-------------|-----------------|---------------------|
| 1 | Dashboard | After UI | KPI summary boxes, postbox layout, system health | KPI row + Recent Jobs (5 varied rows) + Activity postbox; curated demo jobs |
| 2 | New Job workflow | Staging | Schedule collapse | Steps 1–3; action selected + safety panel; schedule collapsed; crop before Step 4 |
| 3 | Filter & preview | Near ready | Results Summary stat chips | Tight crop: filter bar + count notice + summary + preview table top rows |
| 4 | Action safety | Staging | Action description panel | Step 3 dominant; undoable action selected (e.g. Move to Draft) |
| 5 | Job progress | Live run | Existing AJAX UI | Start small AJAX job; capture live progress bar + pause/cancel |
| 6 | Jobs Runs | Minor polish | Human-readable action labels | Runs tab; 6–8 varied jobs/statuses; hover row for Edit/Clone/View |
| 7 | Logs | Minor polish | Action labels + merged Undo column | 6–8 varied logs; Tool + Job sources; undo rows at top; non-zero Affected |
| 8 | Tools | After UI | Postbox groups + Details badges | Three postbox groups; Download vs Run Cleanup visible |
| 9 | Settings | Ready | Intro + uninstall option | Full settings form; intro visible; dismiss notices |

---

## Capture checklist

Use a clean local or staging site. Recommended viewport: **1200×900** (or 1280×720). PNG format.

| # | File | Admin URL | What to show | Hide / avoid |
|---|------|-----------|--------------|--------------|
| 1 | `screenshot-1.png` | `wp-admin/admin.php?page=bam-dashboard` | KPI summary row, Recent Jobs postbox, Activity postbox, System Health | Unrelated admin notices, personal data |
| 2 | `screenshot-2.png` | `wp-admin/admin.php?page=bam-new-job` | Steps 1–3 with action selected; schedule collapsed | Full Step 4 + schedule section |
| 3 | `screenshot-3.png` | New Job - Steps 1–2 | Filter bar, results summary stat chips, preview table | Zero-match empty state |
| 4 | `screenshot-4.png` | New Job - Step 3 | Action dropdown + safety / undo description panel | Empty action selection |
| 5 | `screenshot-5.png` | New Job - Step 4 (AJAX mode) | **Live progress**: progress bar, processed/total count, pause/cancel | Background “queued” notice |
| 6 | `screenshot-6.png` | `wp-admin/admin.php?page=bam-jobs` | **Runs** tab: status badges, row actions, varied jobs | Scheduled tab only |
| 7 | `screenshot-7.png` | `wp-admin/admin.php?page=bam-logs` | Source column, affected/failed counts, Undo badge + button | Rows with Affected = 0 only |
| 8 | `screenshot-8.png` | `wp-admin/admin.php?page=bam-tools` | Postbox groups, Details badges, Download vs Run Cleanup | Plain Run-only rows |
| 9 | `screenshot-9.png` | `wp-admin/admin.php?page=bam-settings` | General, Undo, Logging, Data & Uninstall sections | Unrelated notices |

---

## Demo data tips

### Screenshot 1 (Dashboard)

- Curate 5–10 jobs with varied names and statuses (not all “Move To Trash / Paused”).
- Balanced KPI values: non-zero Running, Completed, Failed, Undo.

### Screenshots 2–4 (New Job)

- Select a meaningful action before capture (e.g. Move to Draft, Remove Featured Image).
- Leave **Save as recurring schedule** unchecked for shots 2 and 4.
- Use vertical crop - do not capture the full scrollable page.

### Screenshot 5

- Start a small AJAX job (e.g. draft a handful of posts) so the progress UI is visible.

### Screenshot 6 (Jobs)

Example curated jobs:

- Move old News posts to Draft (Completed)
- Remove Featured Images (Completed)
- Add “Wellness” category (Running - partial progress e.g. `184 / 1250`)
- Export posts missing SEO title (Completed)
- Find & Replace outdated name (Failed, optional)

Hover a row before capture so **View / Edit / Clone / Pause / Resume** row actions are visible (e.g. hover a **Paused** row for Resume, or a **Running** row for Pause).

On the **Paused** filter, the bulk dropdown should show **Resume** and **Cancel** (not Delete). Use a mixed status set on the **All** or **Runs** tab for screenshot variety.

### Screenshot 7 (Logs)

Example staged logs:

| Source | Action | Affected | Undo |
|--------|--------|----------|------|
| Job | Change Status to Draft | 120 | Undo Available |
| Job | Remove Featured Image | 84 | Undo Available |
| Tool | Remove Auto Drafts | 53 | none |
| Job | Export CSV | 869 | none |
| Tool Job | Orphan Metadata | 212 | none |
| Job | Move to Trash | 2377 | Undo Available |

Include at least one **Tool** or **Tool Job** source row. Put undoable rows in the top visible viewport.

### Screenshot 8 (Tools)

Capture after UI update: postboxes, Details badges (Destructive on Empty Trash, Export on download tools).

### Screenshot 9 (Settings)

No staging required. Intro paragraph and all sections visible.

---

## Capture rules

- **Format:** PNG
- **Theme:** Default WordPress admin (same color scheme for all 9 shots)
- **Zoom:** Browser at 100%
- **Notices:** Dismiss unrelated update/plugin notices before capturing
- **Privacy:** No production domains, real user emails, or client branding unless intentional
- **Size:** Target ~1200×900; WordPress.org will scale down as needed

---

## Replace-in-place rule

When updating screenshots:

1. Capture the screen per the checklist above.
2. Save as `bulk-actions-manager/screenshot-N.png` (same `N` as the readme caption).
3. **Overwrite** the existing file - do not rename or renumber.
4. Do not change `readme.txt` or `README.md` caption order unless you are deliberately redoing the entire set.

---

## Locked captions (readme.txt)

These captions must stay aligned with file numbers:

1. Dashboard overview with job statistics and system health
2. New Job guided workflow (filter, preview, action, execute)
3. Content filtering, results summary, and preview table
4. Action selection with safety and undo description panel
5. Job execution with live progress and batch processing status
6. Jobs screen: Runs tab with status badges and row actions
7. Logs screen with affected counts, source labels, and undo actions
8. Tools screen with cleanup and export utilities
9. Settings screen for batch size, processing mode, and retention

---

## WordPress.org assets (optional, separate)

Plugin directory **banner** and **icon** assets (e.g. `banner-772x250.png`, `icon-256x256.png`) are uploaded via the WordPress.org SVN `/assets/` folder, not the plugin zip. They are out of scope for the `screenshot-N.png` files above.
