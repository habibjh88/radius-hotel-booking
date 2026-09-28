---
name: rtbp-booking-engine
description: Invariants for the Radius Hotel Booking time-based booking engine — rate-plan windows (fixed, midnight-crossing, 24h, flexible), half-open overlap with buffer, what occupies a room, the four-query availability plan, holds, the row-locked write path and the pricing order. Use whenever code touches availability, stay windows, holds, booking/line writes, check-in/out, pricing or occupancy.
---

# Booking engine — non-negotiables

The full design is `docs/project/booking-engine.md`. Read the sections your task touches; this
skill is the checklist you must not violate.

## Model

- Room type **owns** rooms (`rooms.room_type_id`, no join table). Room numbers are unique
  property-wide. Availability for a room type only ever evaluates **its own** rooms.
- Rate plan = reusable window (`fixed`: start/end time; `flexible`: duration + check-in range).
  Price lives on `room_type_rates`, never on the plan.
- Booking line = one room × one window, with frozen price + snapshots.

## Time

- Window: `start = D @ start_time` (or chosen time), `dur = (end − start) mod 24h` with 0 → 24h,
  `end = start + dur + (n − 1) days`. Day arithmetic in the site timezone, then convert to GMT.
  Never add 86400 seconds; never detect plan behaviour from its name.
- Store local + `_gmt`; **compare only `_gmt`**. Use `StayWindow` / `Dates`, never `strtotime`.

## Overlap

- Half-open `[start, end)`; `overlaps = a.start < b.end_eff && b.start < a.end_eff`,
  `end_eff = occupied_until + buffer` (buffer on the end of both sides).
- Occupying: lines `pending|confirmed|checked_in`, and `checked_out` until `occupied_until`;
  unexpired holds except the requester's own token; blocks whose scope covers the room.
  Rooms not `available` are unsellable.

## Queries

- Availability = **4 queries total** (catalogue, rooms, busy UNION over the envelope, calendar +
  rules), evaluated in memory. A query inside a loop over rates or rooms is a bug.
- Busy query uses index `(room_id, start_at_gmt, occupied_until_gmt)`.

## Writes

- Every path that makes a room busy (hold, create, add/edit line, room change at check-in,
  import) goes through `BookingWriter::lockAndCheck()`:
  transaction → `SELECT … FROM rooms WHERE id IN (…) ORDER BY id FOR UPDATE` → re-check
  conflicts inside the lock → re-quote → write → log → commit → hooks/emails after commit.
- Conflict → 409 `room_unavailable` (+ booking ref for staff). Price moved → `price_changed`
  with the new quote unless `accept_new_price`.
- Multi-line bookings are all-or-nothing.

## Pricing order (per unit date)

base → sale (only if set and lower) → date override → seasonal (priority, specificity, newest)
→ occupancy (specificity, highest threshold reached) → early-bird **or** last-minute → max(0)
→ round to franc. Record every step in `price_breakdown`. Same pipeline for desk and web; never
skip rules in admin. Never re-price an existing line except via an explicit, logged line edit.

## Tests you must add or keep green

- The `booking-engine.md` §10 table as a PHPUnit data provider (add a row for every new case).
- Query-count + timing assertion on `AvailabilityService::search()`.
- The concurrency test (two connections, last room, exactly one wins) whenever the write path
  changes.
