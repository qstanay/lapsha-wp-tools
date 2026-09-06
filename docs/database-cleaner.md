# Database Cleaner

The Database module in **0.1.1** ships one tool: a safer UI to remove leftover WordPress data. It does not optimize tables, drop plugin tables, or touch published/draft content that is still in use.

Leftovers are WordPress **statuses**, not plugin-specific buckets. There is no Elementor or Divi cleanup category.

## Workflow

```text
Open Database Cleaner
        ↓
Scan database          ← read-only COUNT(*) + preview
        ↓
Select categories
        ↓
Clean selected         ← confirmation screen, nothing deleted yet
        ↓
Confirm cleanup
        ↓
Cleaner re-queries DB and deletes
        ↓
Result summary
```

Scan never deletes. Cleanup is POST-only (`admin_post_lapsha_scan_database`, `admin_post_lapsha_clean_database`). There is no one-shot AJAX job that tries to wipe the whole table in a single request.

Scan results and in-flight jobs are stored in per-user transients (`lapsha_wp_tools_scan_*`, `lapsha_wp_tools_job_*`, `lapsha_wp_tools_clean_*`). They expire; they are not plugin settings.

## Categories

Category keys from the request are intersected with an allowlist (`lapsha_wp_tools_sanitize_category_ids()`). Unknown keys are dropped. A row with count `0` cannot be selected.

| Key | What is counted | How it is deleted |
| --- | --- | --- |
| `revisions` | `wp_posts.post_type = 'revision'` | `wp_delete_post_revision()` |
| `auto_drafts` | `post_status = 'auto-draft'` | `wp_delete_post( $id, true )` |
| `trashed_posts` | `post_status = 'trash'` | `wp_delete_post( $id, true )` |
| `spam_comments` | `comment_approved = 'spam'` | `wp_delete_comment( $id, true )` |
| `trashed_comments` | `comment_approved = 'trash'` | `wp_delete_comment( $id, true )` |
| `expired_transients` | timeout rows whose timestamp is in the past | `delete_transient()` / `delete_site_transient()` |

Regular drafts (`draft`), approved comments, published posts, and **valid** transients are never selected by these rules.

The current published or draft post is not a revision row. Deleting revisions does not remove that current row.

UI counts are `COUNT(*)`. Scan does not `SUM(LENGTH(post_content))` (that full-table read hangs on large revision sets) and preview queries have no `ORDER BY`.

## How scan works

`Lapsha_Database_Scanner::scan()` walks the six keys and, for each, returns label, description, count, and up to eight preview rows.

Post queries use `$wpdb->posts` and `$wpdb->prepare()`. Only `post_type` or `post_status` is allowed as the WHERE column. Comment queries filter `comment_approved`. Table names always come from `$wpdb` (the prefix is never assumed to be `wp_`).

Expired transients are timeout rows:

- `_transient_timeout_*` and `_site_transient_timeout_*` in `$wpdb->options`
- on multisite, the same site-transient prefix in `$wpdb->sitemeta` when that table exists

`LIKE` patterns go through `$wpdb->esc_like()` because `_` is a wildcard. A timeout is expired when its stored timestamp is less than `time()`. Active transients are not counted.

For `auto-draft` and `trash` (status queries only), the scanner appends any active compatibility exclusion:

```sql
AND post_type NOT IN ( …reserved CPT slugs… )
```

Revision scans do **not** apply that exclusion: builder history on a page is still leftover. Comments and transients have no CPT filter.

## How delete works

Cleanup does not reuse preview IDs from the scan table. `Lapsha_Database_Cleaner` selects a fresh batch from the same rules, then calls WordPress delete functions so caches and meta for **that** object stay consistent. There is no raw `DELETE FROM wp_posts`.

Batches:

- posts/comments: 25 IDs
- transients: 80 timeout names

Each HTTP request has a time budget of about **8 seconds** (filter `lapsha_wp_tools_http_time_budget`, clamped 2–15). Confirm runs the first slice in that same request. If the job finishes, the result screen is shown immediately. If not, the progress screen continues with further POSTs (full page or `lapsha_ajax=1` JSON from `admin.js`).

The loop also calls `set_time_limit(25)` when the host allows it, but it still stops on the 8-second budget so a host that ignores `set_time_limit` does not sit until 502.

```text
Confirm
  → first ~8s of work in that request
  → if finished: result summary
  → if not: progress screen, further steps
  → summary when complete
```

If a row disappears between scan and delete (another admin emptied trash, status changed), the cleaner skips it and continues.

Expired transients are removed in chunks via `delete_transient()` / `delete_site_transient()` so timeout and value pairs stay together. Core `delete_expired_transients()` is not used for the whole table at once.

## Plugin and theme compatibility

Cleanup stays status-based until an **integration** reports that a plugin or theme is running. Each pack is a `Lapsha_Database_Compat_Integration`: `is_active()`, then `rules()`. `Lapsha_Database_Compat` merges rules from every active pack.

The only rule interpreted today is `exclude_post_types`: skip those custom types for **auto-draft** and **trash**. That is the case where “leftover” would otherwise mean “template the builder still owns” (for example a library item sitting in trash).

Shipped packs:

| Integration | When it is active | Reserved post types (auto-draft and trash) |
| --- | --- | --- |
| Elementor | Elementor plugin | `elementor_library`, `e-floating-buttons` |
| Divi | Divi/Extra theme or Divi Builder plugin | `et_pb_layout`, `et_template`, `et_header_layout`, `et_body_layout`, `et_footer_layout` |

Not excluded while a pack is active:

- **Revisions** of posts and pages (layout JSON on a revision is history; `wp_delete_post_revision()`)
- **Page/post auto-drafts** (unsaved “Add New”; auto-drafts of reserved CPTs are kept)
- **Ordinary trash** (posts and pages)
- **Spam / trashed comments**
- **Expired** transients (active builder caches are left alone)

When the plugin or theme is **not** active, those CPTs are ordinary leftovers and can be cleaned. That is how leftover builder data is removed after the builder is gone.

A pack is not required for every third-party plugin. Published content, meta attached to a living post, and valid transients are already outside these categories. Integrations exist only for CPT rows that live in trash or auto-draft while the owner is still running.

Hooks:

- `lapsha_wp_tools_database_compat` — append or replace packs
- `lapsha_wp_tools_database_compat_is_active` — force a pack on or off
- `lapsha_wp_tools_database_compat_exclude_post_types` — final CPT list

## What this version does not do

- Automatic or cron cleanup
- Cleanup history log
- Database backup/restore
- Dropping unknown tables
- Deleting **all** transients (active ones must remain)
- Sweeping “orphaned” `postmeta` (`post_id` with no row in `wp_posts`). That SQL is how other cleaners break Elementor/Divi. Posts removed through this cleaner go through `wp_delete_post()`, which already removes that post’s meta.

## Multisite

Usable on a site in a network (`manage_options` on that site). Schema is not single-site-only. Network-wide “clean every site” is not in this version.
