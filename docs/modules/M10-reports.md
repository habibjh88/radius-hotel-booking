# M10 — Reports

| | |
|---|---|
| **Client module** | 10 — Reports |
| **Features** | 10.1–10.17 |
| **Depends on** | M05 (payments ledger), M08 (room-level availability), M13 |
| **Tier** | Free (sales, rooms, availability grid, CSV) + **Pro** (occupancy %, revenue breakdowns, XLSX) |
| **Engine** | yes (occupancy is overlap on booking lines; read `booking-engine.md` §5) |
| **Critical review** | yes (figures must match hand-checked control figures) |

## Goal

The client's Sales, Rooms and Room Availability reports, rebuilt with the same questions, the same
arrival-date / creation-date switch, and correct maths. New: occupancy %, revenue by room type,
by rate plan and by staff member. Every report exports to a spreadsheet. **All figures come from
stored booking lines and payments. Nothing is re-priced.**

## Definitions (write these on screen as help text)

- **Revenue** = the sum of `booking_rooms.total` (the frozen price) for lines whose stay status
  is not `cancelled|declined|no_show`. Tax is shown separately.
- **Collected** = the sum of `payments.amount` (type payment − refunds) in the range.
- **Date mode** `arrival` → lines whose `start_at` falls in the range. `created` → bookings created
  in the range. The same switch as the dashboard (10.7).
- **Occupied room at time t** = a room with a line where `start_at_gmt ≤ t < end_at_gmt`, status
  in `confirmed|checked_in|checked_out`. This is overlap, **not** "check-in inside the window",
  which was the legacy bug.
- **Occupancy % for a day** = occupied room-hours ÷ sellable room-hours (rooms in state
  `available`, not blocked) × 100. Also shown as rooms occupied at the day's peak.
- **Unsuccessful bookings** = lines cancelled, declined, or refunded (10.4).

## Scope

| Feature | Summary | Task |
|---|---|---|
| 10.1, 10.2, 10.3, 10.4 | Sales report: net sales, tax, completed (paid) count, unsuccessful count | T1, T2 |
| 10.5 | Sales chart: ≤ 1 day → by payment method; ≤ 62 days → per day, split by method; longer → per month; empty buckets filled with zero (as in the legacy system) | T2 |
| 10.6 | Bookings by payment method | T2 |
| 10.7 | Arrival / creation date switch on every report | T1 |
| 10.8, 10.9 | Rooms report: available / rented / empty counts, closed and maintenance counted separately | T3 |
| 10.10 | Bookings table for the period (created, room type, room, arrival, departure, payment, status, subtotal) | T3 |
| 10.11 | Empty rooms grouped by floor | T3 |
| 10.12, 10.13 | Room availability screen: a live grid room type → floor → room, booked / free for a chosen window, with counters | T4 |
| 10.15 | Occupancy % per day and for the period (chart + figure) | T5 |
| 10.16 | Revenue by room type and by rate plan | T5 |
| 10.17 | Revenue by staff member (`bookings.created_by`) | T5 |
| 10.14 | Export any report (XLSX / CSV) using M11's writer; if M11 isn't built yet, a CSV writer here that M11 then generalises | T6 |

## API

`GET reports/sales`, `reports/rooms`, `reports/availability`, `reports/occupancy`,
`reports/revenue-breakdown`, and `GET reports/{name}/export?format=`. Each takes `from`, `to` and
`mode`. One aggregate SQL pass per report over `booking_rooms` (+ `payments`): no per-row loads.
Access: `page.reports_sales`, `page.reports_rooms`, `reports.export`.

## Performance

Indexes used: `booking_rooms (start_at_gmt)`, `(room_id, start_at_gmt, end_at_gmt)`,
`bookings (created_at_gmt)`, `payments (received_at_gmt)`. Target under 500 ms for a year of data
at the client's volume. Cache per filter hash in a transient, invalidated by the
`rtbp_booking_changed` and `rtbp_payment_recorded` hooks.

## Tasks

- [ ] T1 [Free] `ReportService` date-range and mode handling + the sales aggregates (tests against a fixture with known answers) + the `rtbp_report_definitions` registry
- [ ] T2 [Free] Sales screen: KPI tiles, chart (recharts), by-method table
- [ ] T3 [Free] Rooms report: counts, bookings table, empty rooms by floor
- [ ] T4 [Free] Room availability grid (live window)
- [ ] T5 [Free] CSV export on every report (the `rtbp_export_formats` filter)
- [ ] T6 [Pro] Occupancy %, revenue by room type / rate plan / staff, as report tabs via `rtbp.reports.tabs`
- [ ] T7 [Pro] XLSX export format

## Acceptance

Seed a known month (a fixture script in `tests/fixtures/`). Every figure on every report matches the
spreadsheet of expected values, in both date modes. A multi-night stay counts as occupied on every
night, not only on its arrival day.

## Legacy reference

`legacy-reference.md` → Module 10.

## Progress notes
