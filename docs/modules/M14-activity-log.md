# M14 — Activity log: who did what

| | |
|---|---|
| **Client module** | 14 — Activity log ★ (the client's third request) |
| **Features** | 14.1–14.18 |
| **Depends on** | M13 (access keys double as action keys; the log screen is access-controlled) |
| **Tier** | Free (the `rtbp_activity()` emitter only) + **Pro** (storage, screen, timeline, archiving) |
| **Engine** | no |
| **Critical review** | yes (append-only guarantee, hash chain, no secrets in the log) |

## Goal

Every action in the system is recorded: who did it, what changed (before and after), which
record it touched, and when, from which IP and device. The log can be searched and filtered,
is archived automatically, and cannot be altered. From this module onward, every module writes
to the log by calling the free `rtbp_activity()` emitter. **Storage, the log screen, the
timeline and archiving are Pro.** Without Pro the events fire and nothing is stored, and the free
plugin works unchanged. Every table below lives in the Pro plugin.

## Scope

| Feature | Summary | Task |
|---|---|---|
| 14.1 | `rtbp_activity()` (free) → Pro `ActivityLogger`: user, action, subject type/id, description, IP, user agent, parsed device and browser, time | T1, T2a |
| 14.14 | Before and after values (only the fields that changed, secrets masked) | T1 |
| 14.18 | Append-only: no update or delete path; a hash chain (ADR-011); a verification command `wp radius-hotel-booking log:verify` | T2a, T2b |
| 14.2 | Booking actions: provided by the booking modules through the logger. M14 defines the action catalogue | T1 |
| 14.3 | Sign-in, sign-out and failed sign-in (`wp_login`, `wp_logout`, `wp_login_failed`) for staff users | T1 |
| 14.4 | Page views: logged by `AccessMiddleware` on `page.*` checks | T1 |
| 14.15 | Payment actions: action keys reserved here and written by M05 | T1 |
| 14.16 | Permission changes: M13 already emits `permission.*` through `rtbp_activity()` | T1 |
| 14.17 | Failed attempts: locked page or action, wrong passcode (M13 emits `security.*`) | T1 |
| — | Subscribe to M17's `rtbp_settings_updated` | T1 |
| 14.5–14.9 | Log screen: filter by staff member, date range, kind (data / view / auth / payment / permission / security), specific action, free-text search | T3a |
| 14.10 | Repeated actions grouped: the same user, action and subject within the grouping window (default 900 s, as in the legacy system) increment `repeat_count` on the last row instead of adding a row (page views only; data changes are never grouped) | T2a |
| 14.11 | Automatic archiving every 1/2/3/6/12/24 months (a setting), next run date shown; writes CSV + JSON to protected storage | T4 |
| 14.12 | Optional purge after archive, only of rows fully contained in a verified archive file | T4 |
| 14.13 | Archive on demand; list and download past archives | T4 |
| — | `ActivityTimeline` component: the history of one record, embedded in booking, guest, room and employee screens | T3b |

## Data

| Table | Key columns |
|---|---|
| `activity_log` | `id`, `user_id`, `user_name` (snapshot), `action` (varchar 64), `kind` (varchar 16), `subject_type`, `subject_id`, `subject_label`, `description`, `changes` (JSON before/after), `ip` (varbinary 16), `user_agent`, `device`, `repeat_count`, `created_at`, `created_at_gmt`, `prev_hash`, `hash`. Indexes: `(created_at_gmt)`, `(user_id, created_at_gmt)`, `(action, created_at_gmt)`, `(subject_type, subject_id)`, `(kind, created_at_gmt)` |
| `log_archives` | `id`, `period_start`, `period_end`, `row_count`, `first_id`, `last_id`, `file_token`, `file_sha256`, `purged` (bool), `created_by`, `created_at` |

## API

| Method | Route | Access key | Purpose |
|---|---|---|---|
| GET | `activity` | `page.activity_log` | Filtered and paginated list |
| GET | `activity/actions` | `page.activity_log` | The action catalogue (for the filter) |
| GET | `activity/subject/{type}/{id}` | depends on the subject (for example `page.bookings`) | The timeline for one record |
| GET | `activity/archives` | `activity.archive` | List of archives |
| POST | `activity/archives` | `activity.archive` (passcode by default) | Archive now (and optionally purge) |

## Settings (added to M17)

Pro's own section `pro_activity` (option `rtbp_pro_activity_settings`, like `pro_access`); the free
plugin never reads it.

| Section.key | Default | Meaning |
|---|---|---|
| `pro_activity.archiveEvery` | `12` | Months between automatic archives (1, 2, 3, 6, 12, 24; 0 = off) |
| `pro_activity.purgeAfterArchive` | `false` | Remove archived rows from the database |
| `pro_activity.logPageViews` | `true` | Record page views |
| `pro_activity.groupWindow` | `900` | Seconds within which repeated page views are grouped |

## Access keys (added to M13)

`page.activity_log` (default locked for non-managers), `activity.archive` (passcode). Registered by **Pro** through `rtbp_access_keys` (the free registry holds only free features' keys, M13 T1).

## Activity log actions

This module owns the **catalogue** `includes/ActivityLog/ActionCatalog.php`. Every action key
lists its kind and a translatable label. Initial entries: `auth.login`, `auth.logout`,
`auth.failed`, `page.view`, `security.denied`, `security.passcode_failed`, `permission.changed`,
`settings.updated`, `activity.archived`, `activity.purged`. Each later module appends its own
(for example `bookings.approve`, `payments.record`).

## Consumes / provides

- **Consumes:** M13 `AccessMiddleware` hooks (`rtbp_access_denied`, `rtbp_passcode_failed`,
  `rtbp_access_changed`, `rtbp_page_viewed`), M17 `rtbp_settings_updated`, M00 `ProtectedFiles`,
  `Scheduler`.
- **Provides:** free: `rtbp_activity()`, `ActionCatalog` (filter `rtbp_activity_actions`). Pro: `ActivityLogger`, and the
  `ActivityTimeline` component.

## Tasks

- [x] T1 [Free] `rtbp_activity( $action, $subject, $context )` emitter: a normalised event with actor, IP, user agent, subject, and a before/after diff with secrets masked. Fires the `rtbp_activity` action and **stores nothing**. Plus a filterable `ActionCatalog`, and emits auth / page-view / denied events from free. Diffing and masking verified on the Local site — done 2026-09-29
- [x] T2a [Pro] `ActivityLogFeature` + `activity_log` / `log_archives` tables (Pro installer) + `ActivityLogger` on `rtbp_activity` (device parsing, proxy-aware IP, grouping window, hash chain, append-only) + the `pro_activity` settings section + access keys `page.activity_log`, `activity.archive`; storing, grouping and the chain checked on the Local site — done 2026-09-29
- [x] T2b [Pro] `wp radius-hotel-booking log:verify` (walks the chain, reports the first broken row); tamper test (acceptance 4) — done 2026-09-29
- [x] T3a [Pro] `activity`, `activity/actions`, `activity/subject/{type}/{id}` endpoints + the Log screen (via `rtbp.admin.routes`: filters, search, grouped ×N rows, diff drawer) — done 2026-09-29
- [x] T3b [Pro] `ActivityTimeline` component registered on `rtbp.booking.panels` / `rtbp.guest.panels` (those free seams arrive with M03 / M09; the component is ready and shows once they exist) — done 2026-09-29
- [x] T4 [Pro] Archiving: scheduler, on-demand archive, verified purge, archive list and download — done 2026-09-29

## Acceptance

1. Sign in as a receptionist, open three screens, and change a setting. Each event appears with
   IP, device and before/after values.
2. Refresh the same screen ten times within 15 minutes. It shows as one row marked ×10.
3. Try a locked page, then enter a wrong passcode. Both appear under *security*.
4. Edit a log row directly in the database, then run `wp radius-hotel-booking log:verify`. The
   command reports the broken row.
5. Run an archive with purge on. The file downloads, and only the archived rows are gone.

## Legacy reference

`legacy-reference.md` → Module 14 (the legacy action names, the archive option and cron).

## Migration notes

Legacy log rows are **not** imported into the chained table. They are exported once to an archive
file during M18 and listed in `log_archives` as a legacy archive.

## Progress notes

- 2026-09-29 planning: T2 and T3 split into a/b. The scope table's task column now follows the tier tags (the hash chain and grouping are Pro, T2a). Existing M13 code already calls `rtbp_activity()` behind `function_exists()` with a context of `before` / `after` / `description` plus extras (`user_id`, `key`, `keys`, `role_id` …); the emitter keeps extras under `meta`.
- T1 (2026-09-29): `includes/ActivityLog/{ActionCatalog,ChangeDiff,Activity,CoreEvents}.php` + global `rtbp_activity()` (core-functions). Event: `action, kind, time, time_gmt, actor{id,name,roles}, ip, user_agent, subject{type,id,label}|null, description, changes{before,after}|null, meta`. `actor` in the context overrides the current user (sign-in/out, page views). IP is the TCP peer only; forwarded headers only through the `rtbp_activity_ip` filter (Pro, behind trusted proxies). Secrets are masked by field name (`rtbp_activity_secret_pattern`), keeping markers (`changed`, `removed`, booleans) and the profile-field words `allow`/`restrict`. `rtbp_activity_event` may alter or drop an event. Free emits `auth.login/logout/failed` for dashboard users only (failed sign-in only for an existing staff login, so bots do not flood the log), `page.view` from `rtbp_page_viewed`, and `settings.updated` (changed keys, sensitive stripped) except for sections guarded by another key than `settings.<section>` — those are audited as `permission.changed` where they are saved. Catalogue: 37 actions in 7 kinds (data, view, auth, payment, permission, security, system), booking and payment keys reserved. Verified on food-menu.local: 25/25 `wp eval-file` checks.
- T2a (2026-09-29, Pro): `Features\ActivityLog\ActivityLogFeature`, `ActivityLog\{ActivityLogger,ActivitySettings,DeviceParser}`, `Databases\{ActivityLogTable,LogArchivesTable}` (Pro DB 1.1.0). Table names `…pro_activity_log` / `…pro_log_archives` (Pro prefix). **Deviations:** `ip` is `varchar(45)` text (IPv4/IPv6), not `varbinary(16)`; extra columns `user_role` (legacy keeps the role at the time), `meta`, `last_seen_gmt`. **Decision:** grouping updates `repeat_count` + `last_seen_gmt` of the last page-view row; those two columns are **outside the hash**, every other column is inside (`ActivityLogger::HASHED`), so a grouped row still verifies. Rows are inserted under `GET_LOCK('rtbp_pro_activity_chain')`; the logger has no delete path. Raw user agent kept only for `auth`/`security`; other rows keep the parsed device. IP from `X-Forwarded-For` only behind addresses in `rtbp_pro_trusted_proxies` (default none). `pro_activity` writes need `activity.archive` (they can switch on the purge), and Pro audits them as `settings.updated`; the built-in `rtbp_manager` and the Manager template get `activity.archive` = passcode. Found while testing: generated index names exceeded MySQL's 64 characters (the CREATE failed while the Pro DB version was still stamped) → short index names, and `Databases\Installer` now stamps only when every Pro table exists. Verified: 27/27 `wp eval-file` checks (rows, diff, device, UA rule, chain links and recompute, 10 views → 1 row ×10 with a valid hash, window expiry, page views off, settings validation/key/audit, proxy IP, 3 UA samples, no secrets); 4 parallel writers × 25 events → 100 rows, 0 broken links, 0 duplicate `prev_hash`. Test rows removed from the end of the chain.
- T2b (2026-09-29, Pro): `ActivityLog\ChainVerifier` (batches of 1000; reasons `edited` = hash does not recompute, `unlinked` = `prev_hash` is not the previous row's hash; continues from each row's own hash so one edit is one finding, not a cascade; the first row may point at the anchor option `rtbp_pro_activity_anchor`, which T4's verified purge will set) and `ActivityLog\VerifyCommand`, registered from the feature as `wp radius-hotel-booking log:verify [--show=<n>]` (success exit 0, broken exit 1). Acceptance 4 with the real CLI on food-menu.local: 6 rows intact; editing row 128's description → "Row 128 … contents changed", 1 of 6; restored → intact; bumping `repeat_count` → still intact; deleting row 129 → "Row 130 … a row before it was removed or inserted", 1 of 5; exit codes 0 / 1 checked without a pipe. Log emptied afterwards.
- T3a (2026-09-29, Pro): `ActivityLog\{ActivityQuery,ActivityController}` + `src/admin/activity/{api.js,ActivityScreen.jsx,EntryDialog.jsx}`, route `/activity` (group Reports & data, `page.activity_log`, only while `activity_log` is active). `GET activity` filters `user_id`, `from`/`to` (site-local days on `created_at`), `kind`, `action`, `q` (LIKE on description, record label, person; `esc_like`), `page`/`per_page` ≤ 100, newest first, pagination in `meta`. `GET activity/subject/{type}/{id}` is guarded by the record's page key (`rtbp_pro_activity_subject_key`: booking → `page.bookings`, guest → `page.guests`, room/floor → `page.rooms`, rate → `page.rates`, employee → `page.staff`, else `page.activity_log`). The details view is a Dialog (the runtime has no Sheet), with a before/after table. **Bug found in the browser:** the screen's two parallel GETs each logged a page view and both missed the grouping row → grouping now runs inside the chain lock; verified by reloads adding to ×N instead of rows. Verified: 19/19 REST checks (filters, search incl. a quote-injection string, date range, pagination, row shape, filter data, timeline + its access rule, no secrets; a staff member's denied attempt on the log is itself logged as `security.denied`); T2a checks re-run 27/27; browser as admin: list with ×10 grouping, kind and Changes badges, dialog with pending → confirmed and 45000 → 50000, IP and device; 356 px iframe with no horizontal scroll. Seeded rows and user removed; `log:verify` reports the log empty.
- T3b (2026-09-29, Pro): `src/admin/activity/{ActivityTimeline.jsx,timeline.js}`. Panel contract (now in ADR-015; M03 / M09 implement the host side): `applyFilters( 'rtbp.booking.panels', [], { booking } )` / `( 'rtbp.guest.panels', [], { guest } )` → `{ key, label, order, render() }[]`, rendered sorted by `order`; the timeline registers `activity` (label "History", order 90) when the context has an `id`. The timeline hides itself on a 403 (the viewer may not read that record's history), otherwise loading / error-with-retry / "No history yet" / entries newest first with a two-field change summary (`(+N more)`), ×N, and the T3a entry dialog on click. Employee screens (M12, Pro) render `ActivityTimeline` directly. Verified through the Pro dev runtime check (WP_DEBUG), which now applies `rtbp.booking.panels` like a host screen: booking 41's three entries with summaries, booking 42's excluded, dialog with total / adults / notes, empty state for booking 999, 356 px iframe without horizontal scroll. No changelog line: nothing is visible until M03 / M09 add the panels. Log emptied afterwards.
- T4 (2026-09-29, Pro): `ActivityLog\ActivityArchiver`, `GET/POST activity/archives` (`activity.archive`), `src/admin/activity/{ArchivesDialog,ActivitySettingsSection}.jsx`, Settings tab `pro_activity` "Activity log". Pro DB 1.2.0: `pro_log_archives.csv_token`, `last_hash`. An archive covers every row after the previous one: a JSON file (full rows, every hash; its SHA-256 recorded) + a CSV (UTF-8 BOM; cells starting with `= + - @` are prefixed with `'` against formula injection), protected files of kind `activity_archive`. Purge (setting or the dialog's switch): only archives at the start of the chain, oldest first; it checks the file's SHA-256, re-verifies the chain inside the file from the current anchor, and matches the database rows of the range hash for hash, then deletes exactly those rows under the chain lock and moves the anchor (`rtbp_pro_activity_anchor`) to the archive's last hash; the logger chains from the anchor when the table is empty, so `log:verify` stays green. Scheduler job `rtbp_pro_activity_archive` (daily; archives when `last archive (or first row) + archiveEvery months` is past); `GET activity/archives` returns the next run. Downloads go through the free file route; the `activity_archive` kind needs `Access::can( 'activity.archive' )` (open, or passcode with a grant in `X-RTBP-Passcode`), so the dialog downloads with `fetch` + the cached grant (`passcode.js` exports `grantFor()`); each download is logged as `activity.downloaded` (added to the catalogue by Pro). **Bugs found in the browser:** `wp_tempnam()` is not loaded in REST requests (worked in WP-CLI) → loaded on demand; and M13's review fix left the Pro features / Passcodes tabs hidden (their default `settings.*` key no longer exists) → both tabs declare `accessKey: 'access.manage'`. Verified: 23/23 `wp eval-file` checks (files, SHA-256, JSON rows with hashes, CSV lines + formula guard, purge removes only archived rows, anchored chain verifies, tampered file and changed DB row both refuse the purge, API access, download access staff/admin), re-run after the fixes; browser: dialog with next run, Archive now → 8 entries, CSV 200 + JSON rows with hashes, archive + purge → both archives "Removed from the log", `log:verify` intact on the remaining 4 rows, Settings tab renders, 356 px iframe without horizontal scroll. All test files, archives and rows removed.
- Module DoD (2026-09-29): free with Pro **deactivated** — M14 T1 25/25, M13 T2 30/30, `rtbp_activity()` fires, Pro's logger not loaded; Pro reactivated. Acceptance over real HTTP (a receptionist signed in through `wp-login.php`, iPhone user agent): **1** `auth.login`, page views, `settings.updated` with before/after, each with IP and "iPhone · iOS · Safari" (only two screens have endpoints so far: dashboard and settings); **2** ten dashboard refreshes → one row ×11 (first view + 10); **3** a locked page (`security.denied`) and a wrong PIN (`security.passcode_failed`), both kind `security`; **4** in T2b (tamper → `log:verify` names the row); **5** in T4 (archive + purge: only archived rows gone, chain anchored). Test user, permissions and rows removed; setting restored.
- Critical review (2026-09-29, `rtbp-critical-reviewer`, working trees): 5 confirmed + 5 plausible. Fixed:
  1. **Chain fork on lock timeout** (GET_LOCK wrote anyway; a long purge made timeouts likely). Writers now serialise on the head record: `pro_activity_head` (Pro DB 1.3.0), one row created once in autocommit, locked with `SELECT … FOR UPDATE` inside a transaction per insert (5 retries with backoff; a missing head is recreated). Ids commit in order, so an archive's `MAX(id)` never skips an uncommitted row. First attempt deadlocked (INSERT IGNORE inside the transaction took a shared lock) and dropped events under 4 parallel writers → fixed; now 4 × (25 data + 25 views): 100 data rows, views ×100, 0 DB errors, 0 duplicate `prev_hash`.
  2. **Undetectable tail deletion / emptied table / unkeyed hash.** Rows are HMAC-SHA256 with `LogKey`: the `RTBP_PRO_LOG_KEY` constant, else a random key file `.activity-log-key` (0600) in protected storage (not the WP salts; **back it up** — losing it makes the chain unverifiable). The head holds last id, last hash, live row count and anchor, sealed with the key. `log:verify` checks both ends: `tail_missing`, `count_mismatch`, `head_forged`, `head_missing`, `anchor_mismatch` (anchor must be the newest purged archive's last hash, or lie inside the oldest unpurged archive while a purge is part-way: note `purge_incomplete`). `ActivityLogTable::down()` / `ActivityHeadTable::down()` are no-ops. The anchor option `rtbp_pro_activity_anchor` is gone (the anchor lives in the head).
  3. **CSV:** `fputcsv(…, ',', '"', '')` (RFC 4180; a `\"` can no longer break a cell open for a formula).
  4. **Purge atomicity:** batches of 1000, each one transaction (delete + anchor + row count + `purged` on the last batch), the anchor taken from the verified file; the file's chain is checked from the previous archive's last hash, and a re-run resumes from the head's anchor. The JSON file is one row per line and read with a generator (no whole-file decode). `purge_ready()` returns `{removed, error}`; the API message and `error_log` report a refused purge; a failed archive-index insert returns an error.
  5. **Grouping** only into rows after the last archived id and with `last_seen_gmt` not in the future; ×N shown only for `page.view`.
  Plausible, fixed: secret masking now matches camelCase (`fallbackPin`, `smtpPassword`, `idNumber`), `api_key`, `otp`, `cvv`, `passport`, and masks a whole array under a secret key; failed inserts go to `error_log`. **Follow-ups:** M05 / M15 must decide who may see `payments.*` / payroll rows inside a booking's or employee's history (the subject route checks only the record's page key); M18's legacy archive row must be `purged = 1`, `first_id = last_id = 0` so `purge_ready()` and `last_archived_id()` skip it.
  Verified after the fixes: review checks 21/21 (HMAC, key file 0600, tail delete, truncate, forged head, moved anchor, CSV cells, crash-resume purge, refused purge message, grouping limits, masking, `down()`), M14 T1 25/25, T2a 27/27, T3a 19/19, T4 23/23, M13 T4a 45/45, T5a 42/42; parallel writers and the CLI tail-delete (exit 1) re-run. Log reset afterwards.
