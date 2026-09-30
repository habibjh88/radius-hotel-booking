# M02 — Taking a booking at the front desk

| | |
|---|---|
| **Client module** | 2 — Taking a booking at the front desk |
| **Features** | 2.1–2.14 |
| **Depends on** | M08 (search, holds, `BookingWriter`), M09 (guest lookup / create), M07 (price breakdown) |
| **Tier** | Free |
| **Engine** | **yes**. Read `booking-engine.md` §5–§7 |
| **Critical review** | yes (the create path, ID-document handling) |

## Goal

The walk-in flow staff use most after the dashboard. Pick the dates, see every sellable rate
grouped by room type with live prices (and every unsellable one with its reason), pick a physical
room by floor, find or create the guest with their ID document, and confirm. It supports several
rooms on one booking, never double-sells, and holds the room while the form is completed. It is
built as the shared `BookingFlow` component that M04 mounts publicly.

## Flow (design-system §6 "Booking flow")

1. **Dates and guests**: arrival, departure (defaults to arrival), adults, children; a check-in time
   field appears when a flexible plan is chosen.
2. **Rates**: per room type, `RateCard`s (window, price with a `PriceBreakdown` popover, free
   count) or greyed with the reason. Other stay options are collapsed.
3. **Room**: `RoomPicker`, floors → room tiles. Free rooms are selectable. Others are greyed with
   their reason (for staff, `booked` shows the booking reference on hover). Selecting a room **places a
   hold**, and a countdown chip shows the hold time left.
4. **Add another room** (2.12): repeat steps 2–3. Each line has its own rate, window and room.
   Summary lines are editable and removable.
5. **Guest**: a lookup combobox (name / phone / e-mail) → pick, or *New guest* (first name, last
   name, phone, optional e-mail, ID type and number). A banned guest shows a red banner and blocks
   confirmation.
6. **Summary and confirm** (2.10, 2.11): lines, total, payment state at creation (*Unpaid* /
   *Paid now*, which records a payment through M05 once it exists; until then it stores the flag),
   notes, **Confirm**.

Price or availability changed since the page loaded → the server answers `price_changed` or
`room_unavailable`. The UI shows the new quote or alternatives without clearing the form.

## Scope

| Feature | Summary | Task |
|---|---|---|
| 2.1, 2.6 | Dates and guests bar | T2 |
| 2.2, 2.3, 2.4 | Rates grouped by room type, live prices, unavailable with a reason | T2 |
| 2.5 | Room by floor (`RoomPicker`) | T3 |
| 2.14 | Hold on select, countdown, auto-extend while the form is active (max 2 extensions), release on leave | T3 |
| 2.12 | Several rooms per booking | T3 |
| 2.7, 2.8, 2.9 | Guest lookup / new guest / ID document (M09 `GuestService::findOrCreate`) | T4 |
| 2.10, 2.11 | Summary + payment state on creation | T4 |
| 2.13 | Double booking impossible: `BookingService::create()` → `BookingWriter` locked path, consuming the holds | T1 |
| — | `bookings` + `booking_rooms` tables, `BookingService::create()`, the reference sequence (`RT-{year}-{000001}`), snapshots + frozen price, the initial status per manual-approval setting (desk default *confirmed*), and the `rtbp_booking_created` hook | T1 |
| — | `BookingFlow` component with `mode="desk"` (M04 adds `"guest"`), and `#/bookings/new` | T2–T4 |

## API

| Method | Route | Access key |
|---|---|---|
| GET | `availability` | `bookings.create` |
| POST/PATCH/DELETE | `holds` | `bookings.create` |
| GET | `guests/lookup` | `bookings.create` |
| POST | `bookings` | `bookings.create` |

`POST bookings` body: `{ hold_token, lines: [ { room_id, rate_plan_id, arrival, units, checkin_time?, adults, children } ], guest_id | guest: {…}, payment_state, note, accept_new_price? }`.

## Activity log actions

`bookings.create` (reference, lines, total, guest, source desk), `guests.create` when created
inline. Web-created bookings log with the actor `guest`.

## Tasks

- [ ] T1 [Free] `bookings` + `booking_rooms` tables (all the architecture §4.4 columns) + `BookingService::create()` on `BookingWriter` + verification on the Local site (create, conflict 409, price_changed, banned guest, multi-line all-or-nothing)
- [ ] T2 [Free] `BookingFlow` shell + dates/guests bar + rates by room type (`RateCard`, `PriceBreakdown`, reasons, other options)
- [ ] T3 [Free] `RoomPicker` + holds (place, countdown, extend, release) + multiple lines
- [ ] T4 [Free] Guest step (lookup combobox, new guest with ID, banned banner) + summary + payment state + confirm + success screen linking to the booking (M03)

## Acceptance

1. Walk-in: today, 1 adult, Half Day, Standard → A2 → new guest with CNI → confirm, in under 60
   seconds on a tablet.
2. Two receptionists pick A2 at the same moment. The second sees "Room A2 was just taken" and
   the grid refreshes.
3. A three-room booking where one room gets taken before confirmation. Nothing is created, and the
   form highlights the failed line.
4. Change the Half Day price in another tab before confirming. The confirm shows the new price
   and asks to accept.
5. Choose a banned guest. The banner shows, and confirmation is disabled and also refused by the API.

## Legacy reference

`legacy-reference.md` → Module 2 (field list, ID types, 24 h check-in default 08:00).

## Migration notes

Future legacy bookings (`hbfwc_restata_booking` with checkout ≥ cutover) are imported in M18
through `BookingService` with the `importing` flag.

## Progress notes
- From M08's critical review (2026-09-30): when READ COMMITTED is unavailable (binlog in STATEMENT format), the double-booking guarantee needs **no plain read inside the transaction before `BookingWriter::lockAndCheck()`** — read the booking/guest after it, or add a `FOR SHARE` to `AvailabilityRepository::conflicts()` on such hosts (booking-engine §7.1). Pass `adults` / `children` / `child_ages` on each `lockAndCheck()` request so the type's guest limits are enforced.
