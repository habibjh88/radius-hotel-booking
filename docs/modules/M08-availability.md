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
| — | **Concurrency test**: two PHP processes / two DB connections request the last room; exactly one wins (run in CI) | T3 |
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
| POST/PATCH/DELETE | `holds` / `holds/{token}` | `bookings.create` |
| CRUD | `ical-feeds` · POST `ical-feeds/{id}/sync` | `availability.manage` |
| GET | `public/ical/{token}.ics` | public (token), rate-limited |

## Performance gate

`availability` for 2 room types × 11 rate plans × 30 rooms × a 1-month envelope with 500 lines:
**< 300 ms and exactly 4 SQL queries**. Asserted in an integration test with a query counter
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

- [ ] T1 [Free] `Overlap` + `AvailabilityRepository::busy()/conflicts()` + unit tests for booking-engine §10 rows 1–5 and 9–16
- [ ] T2 [Free] `AvailabilityService::search()` with rules and reasons + the staff/public resource shapes + a performance test (4 queries, < 300 ms)
- [ ] T3 [Free] `holds` + `HoldService` + `BookingWriter::lockAndCheck()` + the sweep cron + the **concurrency test** (row 20)
- [ ] T4 [Free] Calendar grid (per-date override, rate close, room-type close, free counts)
- [ ] T5 [Free] Bulk update + blocks (API + screens + calendar overlay); the `rtbp_block_sources` filter for external block sources
- [ ] T6 [Pro] iCal export (tokenised) + import (SSRF guard, cron, reconcile by UID) as a `rtbp_block_sources` provider

## Acceptance

1. Staff search today: Standard shows Half Day 5 free, Overnight *fully booked* (greyed, with the
   reason), and room A3 greyed *maintenance*.
2. Book Half Day on A1. Rest Time on A1 stays sellable with buffer 0, and becomes *buffer* with 30.
3. Close Rest Time on 31 Dec in the calendar. Only that date shows `rate_closed`.
4. Two browsers hold A2 at once. One gets it, and the other sees *held* after refreshing.
5. Import a Booking.com iCal feed with an event on A4. A4 shows *blocked* for those dates. Remove
   the event upstream, sync, and the block disappears.
6. The concurrency test passes 50/50 runs.

## Legacy reference

`legacy-reference.md` → Module 8 (keep the SSRF guard idea; don't copy the public, guessable feed).

## Migration notes

`hbfwc_rateplans` (price per date) → `rate_calendar.price_override`. `hbfwc_availability` closed
statuses → `rate_calendar.is_closed`. `hbfwc_blocked_dates` → `blocks` (the source maps to
manual/ical, and `room_number` resolves to `room_id`). Product `_hbfwc_sync_calendar` /
`_hbfwc_cal_sync_interval` → `ical_feeds`.

## Progress notes
