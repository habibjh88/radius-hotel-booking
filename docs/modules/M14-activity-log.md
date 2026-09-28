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
| 14.1 | `rtbp_activity()` (free) → Pro `ActivityLogger`: user, action, subject type/id, description, IP, user agent, parsed device and browser, time | T1 |
| 14.14 | Before and after values (only the fields that changed, secrets masked) | T1 |
| 14.18 | Append-only: no update or delete path; a hash chain (ADR-011); a verification command `wp radius-hotel-booking log:verify` | T1 |
| 14.2 | Booking actions: provided by the booking modules through the logger. M14 defines the action catalogue | T2 |
| 14.3 | Sign-in, sign-out and failed sign-in (`wp_login`, `wp_logout`, `wp_login_failed`) for staff users | T2 |
| 14.4 | Page views: logged by `AccessMiddleware` on `page.*` checks | T2 |
| 14.15 | Payment actions: action keys reserved here and written by M05 | T2 |
| 14.16 | Permission changes: subscribe to M13's `rtbp_access_changed` | T2 |
| 14.17 | Failed attempts: locked page or action, wrong passcode (from M13's hooks) | T2 |
| — | Subscribe to M17's `rtbp_settings_updated` | T2 |
| 14.5–14.9 | Log screen: filter by staff member, date range, kind (data / view / auth / payment / permission / security), specific action, free-text search | T3 |
| 14.10 | Repeated actions grouped: the same user, action and subject within the grouping window (default 900 s, as in the legacy system) increment `repeat_count` on the last row instead of adding a row (page views only; data changes are never grouped) | T1 |
| 14.11 | Automatic archiving every 1/2/3/6/12/24 months (a setting), next run date shown; writes CSV + JSON to protected storage | T4 |
| 14.12 | Optional purge after archive, only of rows fully contained in a verified archive file | T4 |
| 14.13 | Archive on demand; list and download past archives | T4 |
| — | `ActivityTimeline` component: the history of one record, embedded in booking, guest, room and employee screens | T3 |

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

| Section.key | Default | Meaning |
|---|---|---|
| `activity.archiveEvery` | `12` | Months between automatic archives (1, 2, 3, 6, 12, 24; 0 = off) |
| `activity.purgeAfterArchive` | `false` | Remove archived rows from the database |
| `activity.logPageViews` | `true` | Record page views |
| `activity.groupWindow` | `900` | Seconds within which repeated page views are grouped |

## Access keys (added to M13)

`page.activity_log` (default locked for non-managers), `activity.archive` (passcode).

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

- [ ] T1 [Free] `rtbp_activity( $action, $subject, $context )` emitter: a normalised event with actor, IP, user agent, subject, and a before/after diff with secrets masked. Fires the `rtbp_activity` action and **stores nothing**. Plus a filterable `ActionCatalog`, and emits auth / page-view / denied events from free. Unit tests for diffing and masking
- [ ] T2 [Pro] `activity_log` + `log_archives` tables in Pro + `ActivityLogger` subscribed to `rtbp_activity` (device parsing, proxy-aware IP, grouping window, hash chain) + `log:verify` CLI; unit tests for grouping and chain verification
- [ ] T3 [Pro] Log screen (via `rtbp.admin.routes`: filters, search, grouped ×N rows, diff drawer) + `ActivityTimeline` injected through `rtbp.booking.panels` / `rtbp.guest.panels` + the subject endpoint
- [ ] T4 [Pro] Archiving: scheduler, on-demand archive, verified purge, archive list and download

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
