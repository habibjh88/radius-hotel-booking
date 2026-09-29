# M08 — Availability engine, calendar and booking rules

| | |
|---|---|
| **Client module** | 8 — Availability calendar and booking rules |
| **Features** | 8.1–8.12 |
| **Depends on** | M07 (`StayWindow`, `PriceResolver`), M06 (rooms) |
| **Tier** | Free (engine, holds, calendar, bulk, blocks) + **Pro** (iCal sync) |
| **Engine** | **yes. This module *is* the engine.** Read all of `booking-engine.md` |
| **Critical review** | **yes, line by line** |

## Goal

Answer "which rooms can I sell, for which window, at what price, and if not, why not?" correctly
and fast, down to the individual room. Give staff a calendar to open, close and override prices
by date. Hold rooms while a booking is completed. Keep external channels in sync with iCal. After
this module, M02 and M04 only build UI on top of it.

## Scope

| Feature | Summary | Task |
|---|---|---|
| 8.7, 8.8 | `Overlap` (pure, half-open, buffer) + `AvailabilityRepository::busy()` (the §5.3 UNION query) + `conflicts()` for the write path | T1 |
| — | `AvailabilityService::search()`: the §5.2 rule checks, the §5.3 four-query plan, per room type × rate × room evaluation, reasons, pricing via `PriceResolver`, public vs staff shaping | T2 |
| 2.14 | `HoldService` (create through the locked write path, extend, release, sweep cron) + `holds` table | T3 |
| — | `BookingWriter::lockAndCheck()`, the shared locked write path (§7.1), used by holds now and by bookings in M02 | T3 |
| — | **Concurrency check**: two parallel `wp eval` processes request the last room; exactly one wins | T3 |
| 8.1, 8.6 | Availability calendar per room type: dates × rate plans grid with the price, open/closed state and free-room count per date. Month navigation, sticky headers, horizontal virtualisation | T4 |
| 8.2, 8.3, 8.4 | Per-date price override; per-date rate on/off; per-date whole room type on/off (`rate_calendar` with `rate_plan_id` NULL) | T4 |
| 8.5 | Bulk update: date range + weekdays + rate plans → set price / clear override / open / close, with a preview count | T5 |
| 8.12 | Blocked dates: property / floor / room type / room, for a date-time range, with a reason; a list + calendar overlay | T5 |
| 8.9 | Max child age (setting from M17) used by the occupancy check | T2 |
| 8.11 | Manual approval mode (setting from M17) decides the initial line status in `BookingWriter` | T3 |
| 8.10 | iCal: an export feed per room (random token URL, VEVENT per occupying line and block, no guest data) + import feeds per room (SSRF-safe fetch, interval cron, creates `blocks` with `source = ical` and `external_uid`, removes vanished ones) | T6 |

## API

| Method | Route | Access key |
|---|---|---|
| GET | `availability` (staff shape) | `page.availability` or `bookings.create` |
| GET | `availability/calendar?room_type_id=&month=` | `page.availability` |
| PUT | `availability/calendar` (cells) · POST `availability/calendar/bulk` | `availability.manage` |
| CRUD | `blocks` | `availability.manage` |
| POST/PUT/DELETE | `holds` / `holds/{token}` (PUT = extend; the router has no PATCH) | `bookings.create` |
| CRUD | `ical-feeds` · POST `ical-feeds/{id}/sync` | `availability.manage` |
| GET | `public/ical/{token}.ics` | public (token), rate-limited |

## Performance gate

`availability` for 2 room types × 11 rate plans × 30 rooms × a 1-month envelope with 500 lines:
**< 300 ms and exactly 4 SQL queries**. Checked on the Local site with a query counter
(`$wpdb->num_queries` delta).

## Activity log actions

`availability.override|close|open|bulk` (range + counts), `blocks.create|update|delete`,
`holds.create|release` (**not** logged individually for web guests; counted instead),
`ical.feed_*`, `ical.sync` (with created/removed counts).

## Consumes / provides

- **Consumes:** `StayWindow`, `PriceResolver`, `RoomRepository::sellableRooms`, `Transaction`,
  settings `booking.*`.
- **Provides:** `AvailabilityService::search( SearchRequest ): SearchResult`,
  `BookingWriter::lockAndCheck( array $requests, ?string $holdToken )`,
  `HoldService`, `OccupancyCalculator` (extended), and the `CalendarGrid` component.

## Tasks

- [x] T1 [Free] Engine tables (`bookings` + `booking_rooms` schema, `holds`, `blocks`) + `Overlap` + `AvailabilityRepository::busy()/conflicts()` + booking-engine §10 rows 1–5 and 9–16 verified on the Local site — done 2026-09-29
- [x] T2a [Free] `rate_calendar` table + `AvailabilityService::search()`: §5.2 rules and reasons, the §5.3 four-query plan, pricing via `PriceResolver`, max child age (8.9); fills `rtbp_occupied_rooms` and `rtbp_price_date_override` — done 2026-09-29
- [x] T2b [Free] `GET availability` with the staff/public resource shapes + the performance gate (4 queries, < 300 ms) — done 2026-09-29
- [x] T3 [Free] `HoldService` + `BookingWriter::lockAndCheck()` + manual approval's initial status (8.11) + the sweep cron + the **concurrency check** (row 20) — done 2026-09-29
- [x] T4a [Free] Calendar API: month grid (price, open/closed, free count per date) + per-date override, rate close, room-type close, with activity — done 2026-09-29
- [x] T4b [Free] Calendar screen `#/availability` (`CalendarGrid`: month navigation, sticky headers, cell editing) — done 2026-09-29
- [ ] T5a [Free] Bulk update (range + weekdays + rate plans → set price / clear / open / close) with a preview count: API + dialog
- [ ] T5b [Free] Blocks (property / floor / room type / room): API + list screen + calendar overlay; the `rtbp_block_sources` filter
- [ ] T6a [Pro] iCal export: `pro_ical_feeds`, a random-token feed per room, public rate-limited `.ics` route (no guest data)
- [ ] T6b [Pro] iCal import: SSRF-safe fetch, interval cron, reconcile blocks by UID (`source = ical`), feeds screen

## Acceptance

1. Staff search today: Standard shows Half Day 5 free, Overnight *fully booked* (greyed, with the
   reason), and room A3 greyed *maintenance*.
2. Book Half Day on A1. Rest Time on A1 stays sellable with buffer 0, and becomes *buffer* with 30.
3. Close Rest Time on 31 Dec in the calendar. Only that date shows `rate_closed`.
4. Two browsers hold A2 at once. One gets it, and the other sees *held* after refreshing.
5. Import a Booking.com iCal feed with an event on A4. A4 shows *blocked* for those dates. Remove
   the event upstream, sync, and the block disappears.
6. The concurrency check gives exactly one winner in 50 out of 50 runs.

## Legacy reference

`legacy-reference.md` → Module 8 (keep the SSRF guard idea; don't copy the public, guessable feed).

## Migration notes

`hbfwc_rateplans` (price per date) → `rate_calendar.price_override`. `hbfwc_availability` closed
statuses → `rate_calendar.is_closed`. `hbfwc_blocked_dates` → `blocks` (the source maps to
manual/ical, and `room_number` resolves to `room_id`). Product `_hbfwc_sync_calendar` /
`_hbfwc_cal_sync_interval` → `ical_feeds`.

## Progress notes
- 2026-09-29 planning: T2, T4, T5 and T6 split (a = backend, b = screen, except T2b = the endpoint and the performance gate). **Tables the engine reads are created in T1**, including the `bookings` and `booking_rooms` schema from architecture §4.4: the busy query and §10 rows 9, 15 and 16 need real lines, so the schema cannot wait for M02; M02 adds the models, services and screens and must not recreate them (ADR-022). `rate_calendar` comes with the search (T2a), which reads it. **Screens:** the calendar at `#/availability` (sidebar key `page.availability`); the staff "find a room" UI is M02's, so acceptance steps 1–2 are checked through `GET availability` and the calendar's free counts. **iCal** is Pro, so its feeds table is Pro's `pro_ical_feeds`; iCal blocks land in the free `blocks` table through `rtbp_block_sources`. **Holds** key desk holds by `user_id` and web holds by `session_key`.
- T1 (2026-09-29): tables `bookings`, `booking_rooms` (architecture §4.4 as written; index `room_window` = `(room_id, start_at_gmt, occupied_until_gmt)`), `holds` (`room_window`, `expires`, `token`) and `blocks` (`scope_window`, `feed_uid`; `scope_id` 0 for the property), all InnoDB, DB 1.0.6 (ADR-022). `Services\Availability\Overlap` (pure): half-open `overlaps()` with the buffer on both ends; `conflict()` returns `{ reason, interval }` where a real overlap (`booked` / `held` / `blocked`) outranks a buffer-only one (`buffer`); `roomReason()` puts the room state first (`maintenance` / `out_of_service`). **A block ignores the cleaning buffer** (it is a closure, not a stay; booking-engine §4 updated). `Repositories\AvailabilityRepository`: `busy( rooms, from, to, hold_token, exclude_line, now )` = the §5.3 UNION in **one query** (lines in `OCCUPYING` statuses up to `occupied_until_gmt` with the booking reference; holds not expired and not the requester's token, "now" from PHP rather than `UTC_TIMESTAMP()` so the two clocks cannot disagree; blocks joined to rooms by property / room / room type / floor, with the reason), sorted per room; `conflicts( requests, token, exclude_line )` for the write path (one `busy()` over the envelope widened by each buffer); `buffers( types )` = `room_types.buffer_minutes`, NULL → `booking.bufferMinutes` (0 is a real 0). Verified: 45/45 `wp eval-file` checks with temporary fixtures (removed afterwards) — §10 rows 1, 2, 3, 4, 5, 6b, 9, 10, 11, 12, 13, 14, 15 (early check-out at 11:00 with and without buffer), 16 (cancelled, declined, no-show), plus room / room type / property blocks, `exclude_line`, real-vs-buffer precedence, one query for 6 rooms × 13 days, and `conflicts()`. No JS or user-visible change (no changelog line).
- T2a (2026-09-29): `rate_calendar` (DB 1.0.7; **`rate_plan_id` 0 = the whole room type**, not NULL — a UNIQUE key admits any number of NULLs; architecture §4.3 updated), `RateCalendarRepository::between()`, and `Services\Availability\RateCalendar`: a request-wide cache that the search primes in one query and that answers `rtbp_price_date_override` and `closure()` (whole type first, then the rate); a lookup outside a primed range loads that date alone, so `quote()` alone still sees overrides. `OccupancyCalculator` gains `prime()` (the search feeds it the counts it already has) and the engine's `rtbp_occupied_rooms` answer (sellable rooms with a line, live hold or block overlapping the local day, as its M07 docblock promised). `PriceResolver::price()` split out of `quote()` so the search prices loaded rows without querying. **`AvailabilityService::search( input, staff|public, ?now )`**: queries 1–2 are `AvailabilityRepository::catalogue()` / `rooms()` (live, active types; rooms of those types only — package scoping), 3 is `busy()` over whole local days of every candidate window ± buffer (so it also answers occupancy), 4 is the calendar. Rules in §5.2 order; `incompatible_span` rates go to `other_options`; `fully_booked` after the room pass; the price is quoted once the rules pass even when every room is taken (the desk still sees it). Switched-off rates and inactive plans are left out unless that plan is asked for (`rate_disabled`). Child ages (8.9): a child older than `maxChildAge` counts as an adult. Staff get a 30-minute grace on `past` and may pass `exclude_booking_line`; public checks `booking_window` and `same_day_cutoff`. A flexible plan's bad `checkin_time` is a per-rate reason, not a 422. Reasons are objects `{ code, message, … }` (room-level ones add `booking_ref` / `block_reason` and `until`); room types report `rooms_total` / `rooms_sellable` instead of §5.1's `free_rooms_now`, which the per-date free counts of T4a cover (engine doc updated). Verified: 56/56 `wp eval-file` checks with temporary fixtures — basic search and package scoping, acceptance step 1's shape (Overnight fully booked with the booking reference, Half Day 4 free, a room in maintenance), §10 #1, #2, #17, #18, #19, #26, 8.2–8.4, units, occupancy and child ages, public rules via a `pre_option` filter, filters, 422s, `exclude_booking_line`, the occupancy seam, and **4 queries, ~1 ms** for a 2-night search with Pro's pricing steps active; T1's 45 and M07's t4a 23 / t6a 24 / acceptance 7 still pass (the `price()` split).
- T2b (2026-09-29): `GET availability` (`AvailabilityController::search`; query params as booking-engine §5.1 plus `child_ages` as an array or a comma list, `hold_token`, `exclude_booking_line`). **Access "either key":** `AccessMiddleware` ANDs its keys, so the route passes a callable that checks `page.availability` unless it is locked, then `bookings.create` — a receptionist with the page locked can still find a room; both locked → 403 (and the usual `security.denied`). Reads only, so no activity entry beyond the page view the middleware already emits. `Resources\AvailabilityResource`: `staff()` returns the search as is; `public()` removes `state_note` and the room-level `booking_id`, `booking_ref`, `block_id`, `block_reason`, `until`, keeping the code and message. The **public route is M04's** (`public/availability`, rate-limited; M04 doc now points at the resource). Verified: 20/20 `wp eval-file` checks — REST envelope, staff booking reference, child ages, 422, access (defaults, either key, both locked, logged out), the public shape carries no reference / note / reason / free-up time. **Performance gate** on a fixture of 2 room types × 11 rate plans (5 single-unit, 4 multi-unit, 2 flexible) × 30 rooms each and 500 lines over the month, with Pro's pricing steps active: same day 22 rates, median **6.2 ms**; 1 night 4.3 ms; a 30-night search (the 1-month envelope) **67 ms** — **exactly 4 queries** in every run. No screen yet (the desk search UI is M02's), so no changelog line.
- T3 (2026-09-29): **`Services\Booking\BookingWriter::lockAndCheck( requests, hold_token, options )`**, the §7.1 path: must run inside `Transaction::run()` (LogicException otherwise); locks the rooms ascending (`AvailabilityRepository::lockRooms()`, live rooms, 503 `busy` on a lock error); per request re-checks the room (removed / moved to another type since shown / not available), re-quotes (`rate_not_sold`, units), the calendar (`rate_closed` / `room_type_closed`, cache flushed first), `past` (staff grace 30 min); then two requests of the same call on one room (`duplicate`), then `conflicts()` (the search's own predicate); `price_changed` (with the new quote) when `expected_total` differs, unless `accept_new_price`. Refusals are 409 `room_unavailable` with `{ index, room_id, room, reason }` (+ `booking_ref` for staff). All-or-nothing: the caller's transaction rolls back. `BookingWriter::initialStatus( source )` (8.11): **web bookings start `pending` when manual approval is on, desk bookings `confirmed`** (staff making it is the approval); filter `rtbp_booking_initial_status`. **`Transaction::run()` now uses READ COMMITTED** (booking-engine §7.1): the control run showed the room lock alone is not enough under REPEATABLE READ. `HoldRepository` + **`Services\Availability\HoldService`**: `create` (through the writer; one token groups a booking's holds, all expiring together; the same room + window under a token is refreshed, not duplicated; ≤ 10 per token), `extend` (410 `hold_expired`, never revives an expired hold; counts live holds rather than MySQL's "rows affected", which skips unchanged rows — an extension in the same second returned 0 and looked expired, caught by the check), `release` (all or one), `sweep` (cron `rtbp_sweep_holds` every 5 min via the Scheduler). Owners: staff holds carry `user_id`, web holds a `session_key`; a token is refused to the other kind or another session (403 `hold_forbidden`). Activity `holds.create` / `holds.release` for staff; web holds only counted per day (`rtbp_web_hold_counts`, 31 days). Routes `POST holds`, `PUT` / `DELETE holds/{token}` (`bookings.create`); the guest flow's holds are M04's. Verified: 54/54 `wp eval-file` checks (§10 #10, #11, #12, owners, refusals, rollback, READ COMMITTED seen by behaviour with a REPEATABLE READ control, manual approval, sweep, cron, REST and access with a staff user — an administrator is never locked, by design). **Concurrency check (§10 #20):** two `wp eval` processes started together, each reading the holds table before trying (as a longer flow would) while the lock holder lingers 300 ms before its insert: **50 of 50 runs, exactly one winner** (the other 409 `room_unavailable`, one hold row); control with READ COMMITTED off: 0 of 10 (both won every time). T1 45, T2a 56, T2b 20 and M07 acceptance 7 still pass; M06's acceptance and M07's t3 scripts now collide with the real *Standard Room* (A1–A3) made during M07's browser acceptance and a hard-coded DB version — data, not logic. No UI yet, so no changelog line.
- T4a (2026-09-29): `Services\Availability\CalendarService` + `CalendarController`: `GET availability/calendar?room_type_id=&month=YYYY-MM` (`page.availability`) → `{ room_type, month, today, dates, days: [ { date, closed (whole type, 8.4), past, sellable, free_rooms (nothing on them all day) } ], rates: [ { rate_plan_id, name, type, multi_unit, price, sale_price, cells: [ { date, price, override, closed (this rate, 8.3), free } ] } ] }`. A cell's **price** is what a one-unit stay starting that day costs through the whole pipeline (Pro steps included); **free** (8.6) counts sellable rooms free for *that rate's* window that day, since a room with a Half Day booking can still sell Rest Time — `free_rooms` is the stricter whole-day count. Same four sources as the search (catalogue incl. hidden types — `catalogue( id, include_inactive )`, rooms, busy over the month stretched two days for overnight windows, the calendar), priced in memory; occupancy priming moved to `OccupancyCalculator::primeFrom()` and the catalogue grouping to `AvailabilityService::catalogueTypes()` so both reuse one copy. **Warm grid = exactly 4 queries** (the first call per request also loads settings and Pro's rules). `PUT availability/calendar { room_type_id, cells: [ { rate_plan_id (0 = whole type), date, price_override? (null clears), is_closed? } ] }` (`availability.manage`, locked for staff by default, open for managers): every cell validated first (errors `cells.<i>.<field>`: a plan the type has a rate for — on or off, so a price can be prepared —, today to +1 100 days, no price on the whole-type row, price ≥ 0 rounded to the currency, something to change; ≤ 400 cells; a later cell for the same rate and date wins), then one transaction under the room type's row lock (`RateCalendarRepository::upsert()` / `delete()`: a row with no override and not closed is deleted). Activity per kind and rate: `availability.override` (before/after per date), `availability.close`, `availability.open`, subject the room type, the dates in the description; no entry on a no-op; hook `rtbp_rate_calendar_saved` after commit; `RateCalendar` cache flushed. `availability.bulk` added to the catalogue for T5a. Verified: 39/39 `wp eval-file` checks — grid shape, pipeline price with a sale, per-rate and whole-day free counts, an overnight stay from the last day, overrides (rounded) used by the search, rate and whole-type closures seen by the search, reopen deletes rows, clearing an override keeps a closure, no-op, validation and all-or-nothing, 404 / 422, a hidden type, REST (staff 403, admin 200, 422 per cell); T2a / T2b / T3 still pass. No screen yet (T4b), so no changelog line.
- T4b (2026-09-29): **Screen at `#/calendar`** (the sidebar entry M00 reserved as "Availability", `page.availability`; not `#/availability` as planned — the existing route was kept) — `src/modules/Availability/` (`index.jsx`, `api.js`: `useCalendar( type, month )` keeping the previous month on screen while the next loads, `useSaveCalendar()`; `components/CellEditor.jsx`). Room type and month live in the URL (`?type=&month=`); room type select (hidden types marked), previous / next month, "This month"; legend (open, full, closed, price set for the date — colour never alone: every cell says *Closed*, *Full* or *N free*). Rows: **Whole room type** (`free_rooms/sellable free all day` or *Closed*), then each sold rate (price through the pipeline, a dot when a price is set for that date, free count). Clicking a cell (only with `availability.manage`; past dates are read-only and dimmed) opens a dialog: *Open for bookings* switch and, for a rate, *Price for this date* (placeholder = the usual price; empty = usual price; "Use the usual price"); only changed fields are sent; server errors land on the field. Empty states: no room types (→ Rooms), a type with no sold rate (→ its Rates tab, via the new `?tab=rates` on `#/rooms/:id`), error with retry. Shared **`CalendarGrid`** (`src/components/common/`, published on `window.rtbp.ui`, ADR-015): sticky date header and label column, today highlighted and **scrolled into view on open**, weekends shaded in the header only (so grey in the body always means *closed*), accessible names per cell ("Half Day, 30 September 2026: $7,000.00, 7 rooms free"). A month is ≤ 31 columns, so it scrolls natively instead of virtualising. Browser (desktop + 360 px): set 6 500 on 30 Sep (dot, toast), closed the whole type on 2 Oct (every rate *Closed*); three fixes found there — closed cells were not grey (the button's `bg-transparent` won the tailwind-merge; tone now last), weekends and closures shared one grey (weekend shade moved to the header), and at 360 px the page scrolled sideways (the toolbar sat in the Panel's non-shrinking header actions; moved into the body and wrapped; label column narrower on phones) — now no page overflow at 360 px. A detour: a first suspicion of stale lazy chunks was wrong (`@wordpress/scripts` already names chunks `[name].js?ver=[chunkhash]`; the tab had simply not reloaded on a `#` navigation) and the webpack change was reverted. The two test edits were removed from the Local site afterwards. Changelog lines added.

