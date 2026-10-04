# Cutover runbook

Cutover day, in order. One person runs it, one checks off. Every command runs on the **production**
server with WP-CLI as an administrator (`--user=<admin id>`). Expected time: about 60 minutes,
the import itself a few minutes. Choose the quietest hour (no arrivals for an hour either side).

> **Golden rules.** Dry run before every real run. Never delete a legacy plugin or table. Never
> rewind a counter on a site that has issued real invoices. Stop and roll back (rollback-plan.md)
> rather than improvise.

## 0. The day before (D − 1)

- [ ] Go / no-go agreed (parallel-run.md §6): zero unexplained discrepancy, no critical defect, UAT
      signed by the manager.
- [ ] **Full backup** of production: database dump **and** `wp-content/uploads/` (with
      `radius-hotel-booking/`). Restore tested on staging once (rollback depends on it).
- [ ] Plugin zips ready: free, Pro, add-on — the same builds that passed the parallel run
      (`bun run package` for free; record the versions in the go-live log).
- [ ] The desk has the paper fallback for the cutover hour: tomorrow's arrivals and today's
      in-house list printed from the old system.

## 1. Freeze the old system (T − 0:00)

- [ ] Take the website booking form offline: in the old system switch off online booking (or put
      the booking page in maintenance) so no web booking arrives mid-import.
- [ ] Tell the desk: **no new booking, payment or check-in in the old system** until §6. Walk-ins
      go on paper.
- [ ] Note the cutover moment (site time) in the go-live log, e.g. `2026-11-03 13:00`.

## 2. Install (T + 0:05)

- [ ] Upload and activate **Radius Hotel Booking** (free), then **Pro**, then the **Residence TATA**
      add-on (it requires both). Do **not** deactivate the legacy plugins yet (the import reads
      their tables; they share the database).
- [ ] Settings → **General**: hotel details, currency **XOF / CFA** (preset), date and time formats.
      WordPress Settings → General: time zone **Africa/Abidjan** (the dashboard warns until it is).
- [ ] Settings → **Payments**: the methods the desk uses (cash, Wave, Orange Money) — the import maps
      the legacy `cod` to **cash**.
- [ ] **Counters clean** (M05 T4a): on a fresh install `booking_2026` / `invoice_2026` / `guest`
      start at 0. If anything was ever created on production for testing, stop: the counters must
      not be rewound; ask before going further.
- [ ] **Protected files not web-reachable** (ADR-009, M12 acceptance 3): on nginx add
      `location ^~ /wp-content/uploads/radius-hotel-booking/ { deny all; }` **or** set
      `define( 'RTBP_PROTECTED_DIR', '/path/outside/web/root' );` in `wp-config.php`. Check: a direct
      URL to a stored file must answer 403/404 (try with the legacy log CSV after §4).

## 3. Dry run (T + 0:15)

```bash
wp radius-hotel-booking import:legacy --dry-run --user=1
```

(No `--source-prefix`, no `--as-of`: production reads its own tables, cutover = now.)

- [ ] The source summary matches the last parallel-run evening (rooms 38, room types, open booking
      lines, customers, staff …). **14 of 14 legacy tables found**.
- [ ] **Errors**: only the ones already explained during the parallel run. Typical, and what to do:

  | Report line | Meaning | Action |
  |---|---|---|
  | *Room number X is already used …* | a room set up by hand clashes | rename / remove the manual room, re-run the dry run |
  | *The stay … does not fit the window of …* | a legacy booking with odd times | re-create it by hand after cutover; keep the line |
  | *The room is not free for …* | two legacy bookings overlap | the desk decides which stands; the other by hand |
  | *X stays … still "checked in" … not imported* | never checked out in the old system | nothing (they are past) |

- [ ] Warnings read (placeholder e-mails, staff without phone, merged guests …) — expected counts
      from the parallel run.
- [ ] Note: the dry run briefly row-locks the rooms of the open bookings it checks (seconds).

## 4. Import (T + 0:25)

```bash
wp radius-hotel-booking import:legacy --user=1
```

Or from the dashboard: Settings menu → **System → Data import** → *Run a dry run* → *Import*
(the screen refuses the import unless the dry run with the same settings came first).

- [ ] **Imported** table = the dry run's *Would import* table (same created / linked / failed).
- [ ] Save the report (`--format=json > import-report.json`) in the go-live log.
- [ ] The **legacy activity log CSV** link from the report: download it, keep it with the backup.
- [ ] **Re-run the dry run**: every entity *unchanged*, nothing to create (acceptance 3).

## 5. Check (T + 0:35) — acceptance 1 and 2

- [ ] Dashboard → **Today**: today's arrivals and in-house guests = the paper list.
- [ ] **Availability** for the next 7 days, room type by room type = the old system's grid
      (acceptance 2).
- [ ] Three bookings picked at random (one paid, one unpaid, one in house): same room, same window,
      same total, same guest, note *Imported from the old system: order #…*.
- [ ] **Bookings** list: no unexpected `pending` (only those pending in the old system).
- [ ] **Staff** (Pro): the list matches; the ones flagged *no phone* get their phone this week.
- [ ] A staff member signs in with their **usual password** (unchanged) and confirms an action with
      their **usual PIN** (carried over, hashed).
- [ ] Guest e-mails: none were sent by the import (by design).

## 6. Switch over (T + 0:45)

- [ ] **Deactivate** (never delete) `hbfwc-residencetata-addon`, `hbfwc-pro-addon`,
      `hotel-booking-for-woocommerce`. WooCommerce **stays active** (the restaurant shop).
- [ ] Open a past order in WooCommerce: the booking lines still read *Stay / Rate plan / Guests*
      (the add-on's formatter takes over from the old plugins).
- [ ] **Hotel Dashboard address**: the free plugin creates a *Hotel Dashboard* page. If the legacy
      plugin already used `/hotel-dashboard`, WordPress may have named the new one
      `/hotel-dashboard-2/`: once the legacy plugins are off, give the new page the slug
      `hotel-dashboard` (Pages → Quick edit) so staff bookmarks keep working (the training notes
      promise the same address).
- [ ] Public booking page: the new booking shortcode / block on the hotel's booking page; make a
      test search (do not book).
- [ ] The desk enters the paper walk-ins of the cutover hour in the new system.
- [ ] Re-open online booking.

## 7. After (D … D + 7)

- [ ] Daily 10-minute check with the desk; log every issue (severity, fix, time).
- [ ] D + 1: Reports → Sales for yesterday vs the desk's cash count.
- [ ] D + 7: monitored week closed with the manager (acceptance 4 is the parallel-run week).
- [ ] Keep: the backup, the import report, the legacy log CSV, the go-live log — 12 months.
