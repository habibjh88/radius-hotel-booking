# M11 — Exporting and archiving data

| | |
|---|---|
| **Client module** | 11 — Exporting and archiving data (the legacy "Booking Backup") |
| **Features** | 11.1–11.9 |
| **Depends on** | M10 (report export writer), M00 (`ProtectedFiles`, `Scheduler`) |
| **Tier** | Free (CSV booking and guest export, file library) + **Pro** (XLSX, archive-and-remove, scheduled exports) |
| **Engine** | no |
| **Critical review** | yes (archive-and-remove deletes data) |

## Goal

Export complete booking data for any date range to XLSX or CSV, optionally archive-and-remove it
from the live database, and keep a protected library of past files. Also export the guest list,
and run exports on a schedule.

## Scope

| Feature | Summary | Task |
|---|---|---|
| 11.1 | Booking export: one row per booking line, carrying the full booking, guest, line and payment summary. **The columns match the legacy set** (`class-booking-backup.php:299-329`) first, then the new columns | T1 |
| 11.2 | XLSX (a streamed writer: OpenSpout via Composer, PHP-Scoper prefixed) or CSV (UTF-8 BOM, `;` or `,`, a setting) | T1 |
| — | CSV formula-injection guard: cells starting with `= + - @` get a `'` prefix | T1 |
| — | No silent row limit: stream in chunks of 500 | T1 |
| 11.3 | Arrival / creation date mode | T1 |
| 11.4 | Archive-and-remove: **only after** the file is written and its checksum verified. Removes bookings in the range that are in a terminal state (checked out, cancelled, declined, no-show); refuses to remove future or in-house stays. Logged with counts | T2 |
| 11.5, 11.6 | File library: list, download and delete (protected storage, ADR-009) | T2 |
| 11.7 | Guest list export | T3 |
| 11.8 | Scheduled exports (weekly or monthly, with the range and format) and a *next run* date | T3 |
| 11.9 | Backup notes: a doc page `docs/admin/backup-and-restore.md` (FR + EN), covering what the plugin exports versus what the host must back up | T3 |

## Data

`exports` (`id`, `kind` bookings/guests/report/activity, `params` JSON, `format`, `row_count`,
`file_token`, `file_sha256`, `removed_count`, `status`, `created_by`, `created_at`).

## Access keys

`page.exports`, `exports.generate`, `exports.download`, `exports.delete`,
`exports.archive_remove` (passcode, and locked by default).

## Activity log actions

`exports.generate`, `exports.download`, `exports.delete`, `exports.archive_remove` (with counts
and the booking reference range).

## Tasks

- [ ] T1 [Free] `ExportService` + streamed CSV writer + the booking export (legacy column parity, injection guard, no row limit)
- [ ] T2 [Free] File library (list, download, delete) + guest list export + the backup notes doc
- [ ] T3 [Pro] Streamed XLSX writer (OpenSpout, scoped) + archive-and-remove (verified, terminal-state only, transactional per chunk)
- [ ] T4 [Pro] Scheduled exports + the next-run display

## Acceptance

1. Export a month with 1 200 lines. Every line is present, and the file opens in Excel with correct
   accents and numbers.
2. Archive-and-remove a past month. The file is verified, the rows are gone, and future stays are
   untouched. The log shows who did it and the counts.
3. The file URL is not reachable without signing in.

## Legacy reference

`legacy-reference.md` → Module 11.

## Progress notes
