# How booking works, end to end

This tutorial follows one booking from the moment a guest picks a date to the moment the booking
is saved. It explains what each step asks the database, and why the result is correct even
though room types, rate plans, prices and rooms are all data the hotel can change at any time.

The rules themselves are specified in [`docs/project/booking-engine.md`](../project/booking-engine.md).
This page explains how the code applies them. Where the two disagree, the spec wins and the code
has a bug.

---

## 1. The idea: the hotel sells time windows on physical rooms

The engine never thinks in "nights". It thinks in **windows**: a start instant and an end
instant on one physical room.

```
             08:30           17:00  20:00                       08:00 (+1)
Room A2  D   ├──── Half Day ───┤├Rest┤├────────── Overnight ──────────┤
Room A2  D+1                                                          ├── Half Day ──┤
```

Four kinds of data produce every window and every price:

| Data | Table | What it says |
|---|---|---|
| **Room type** | `room_types` | *Standard Room*: max adults/children, cleaning buffer, photos |
| **Room** | `rooms` | *A2*: belongs to **one** room type (`room_type_id`), has a floor and a state (`available`, `maintenance`, `out_of_service`) |
| **Rate plan** | `rate_plans` | A reusable **window recipe**: *Half Day 08:30–17:00*, *Overnight 20:00→08:00*, *24 h flexible*. It owns no rooms and has no price |
| **Room type rate** | `room_type_rates` | "Room type X sells plan Y at price P" (sale price, min/max units, on/off) |

Everything that makes a room busy is stored separately:

| Busy source | Table | Occupies the room when |
|---|---|---|
| Booking line | `booking_rooms` | status `pending`, `confirmed`, `checked_in`; `checked_out` only until `occupied_until_gmt` |
| Hold | `holds` | `expires_at_gmt` is in the future, and it is **not** the visitor's own hold |
| Block | `blocks` | covers the room itself, its room type, its floor, or the whole property |
| Room state | `rooms.state` | anything other than `available` makes the room unsellable for every window |

None of this is hard-coded. A new rate plan is a new row in `rate_plans`; selling it on a room
type is a new row in `room_type_rates`. The engine reads the rows on every search, so a change
shows up on the next request.

---

## 2. The journey at a glance

```
 Guest changes the date
        │
        ▼
 GET public/availability ─────────── AvailabilityService::search()        (read only, 4 queries)
        │  room types → rates → window → rules → price → room by room
        ▼
 Booking form shows rates (price or reason) and the room grid (free / booked / held …)
        │
        │  guest picks a rate and one or more rooms
        ▼
 POST public/holds ───────────────── HoldService::createMany()           (write, rooms LOCKED)
        │  BookingWriter::lockAndCheck() re-checks everything under a row lock
        │  inserts `holds` rows (15 min by default) → returns a token
        ▼
 Guest fills in their details (the hold timer runs)
        │
        ▼
 POST public/bookings ────────────── PublicBookingService → BookingService::create()
        │  rooms LOCKED again, re-checked, re-priced, guest resolved,
        │  booking + lines + invoice written, holds deleted — one transaction
        ▼
 Booking page + e-mails (after the commit)
```

The same engine serves the front desk (`GET availability`, `POST holds`, `POST bookings`). The
desk only differs in its **audience**: `staff` instead of `public`. The audience decides a few
rules, such as the booking window or a 30-minute grace for a walk-in already under way.

---

## 3. Step 1: the guest changes the date (the search)

### 3.1 What the browser sends

The booking form keeps the search (arrival, departure, adults, children, check-in time for
flexible plans) in React state. `useAvailability()` in `src/components/booking/api.js` turns it
into a React Query call:

```js
useQuery( {
    queryKey: [ ...AVAILABILITY_KEY, path, params ],   // the search IS the cache key
    queryFn: () => get( path, params ),
    placeholderData: ( previous ) => previous,         // keep showing the old result while loading
    refetchOnWindowFocus: true,                         // rooms go fast: refresh on return
} );
```

Changing the date changes `params`, so the key changes and React Query fetches again:

```
GET /wp-json/radius-hotel-booking/v1/public/availability
    ?arrival=2026-10-28&departure=2026-10-28&adults=2&children=0&hold_token=…
```

The `hold_token` is sent so that the visitor's own holds don't show their own rooms as "held".

### 3.2 `AvailabilityService::search()`: four queries, whatever the hotel's size

File: `includes/Services/Availability/AvailabilityService.php`. The old system ran one query per
rate plan per room (N+1). This one always runs **four**, then does the rest in PHP memory.

**First, normalise the request** (`normalise()`): validate the dates, compute
`nights = departure − arrival`, and read the guest counts (a child older than the child age limit
counts as an adult).

**Query 1: the catalogue** (`AvailabilityRepository::catalogue()`)

```sql
SELECT t.*, r.*, p.*
  FROM room_types t
  LEFT JOIN room_type_rates r ON r.room_type_id = t.id
  LEFT JOIN rate_plans p      ON p.id = r.rate_plan_id AND p.deleted_at IS NULL
 WHERE t.deleted_at IS NULL AND t.is_active = 1
 ORDER BY t.sort_order, t.id, r.sort_order, r.id
```

One row per (room type × rate). `catalogueTypes()` groups the rows into
`types[type_id]['rates'][plan_id]`. A rate that is switched off, or whose plan is inactive, is
dropped, unless the request asked for that one plan; then it's kept and reported as
`rate_disabled`.

This is where "everything is dynamic" is handled: the list of room types and the list of plans
each type sells **is** this query's result.

**Window derivation (in memory, no query)**

For every room type × rate, `candidate()` decides the **units** and builds the window with
`StayWindow::for()` (`includes/Services/Availability/StayWindow.php`):

| Plan type | Window for arrival date `D` and `n` units |
|---|---|
| `fixed` (`start_time`, `end_time`) | start = `D @ start_time`; length = `(end − start) mod 24 h` (0 means 24 h); end = start + length + `(n − 1)` days |
| `flexible` (`duration_minutes`, `checkin_from`–`checkin_until`) | start = `D @ chosen check-in`, which must lie within the allowed range on a 30-minute step; end = start + duration + `(n − 1)` days |

- `n = max(1, nights)` for a **multi-unit** plan (overnight, 24 h). It's 1 for a single-stay
  plan (half day, rest time).
- A single-stay plan doesn't fit a 3-night search. It gets the reason `incompatible_span` and
  is listed under "other stay options", not as unavailable.
- **Midnight needs no special case.** `20:00 → 08:00` simply has a length of 12 h.
- The arithmetic is done on the wall clock and then read in the site's time zone, so a DST
  change never makes a "12 h" stay 11 or 13 hours long. Both local and GMT times are kept, and
  **every comparison uses GMT**.

While building the windows, the search also computes the **envelope**: the earliest start and
latest end of all candidate windows, widened by the cleaning buffer and to whole local days.

**Query 2: the rooms** (`AvailabilityRepository::rooms()`)

```sql
SELECT r.id, r.room_type_id, r.floor_id, r.number, r.state, r.state_note, f.name
  FROM rooms r LEFT JOIN floors f ON f.id = r.floor_id
 WHERE r.room_type_id IN ( …types from query 1… ) AND r.deleted_at IS NULL
 ORDER BY f.sort_order, r.floor_id, r.number_sort, r.id
```

Rooms are selected **by room type**. That is why a Standard rate can never be sold on a VIP
room: the VIP rooms are not in the Standard list at all.

**Query 3: everything that keeps those rooms busy in the envelope** (`AvailabilityRepository::busy()`)

One `UNION ALL`, indexed by `(room_id, start_at_gmt, …)`:

```sql
-- booking lines that still occupy the room
SELECT br.room_id, br.start_at_gmt AS s, br.occupied_until_gmt AS e, 'line', br.booking_id, bk.reference
  FROM booking_rooms br LEFT JOIN bookings bk ON bk.id = br.booking_id
 WHERE br.room_id IN (…) AND br.status IN ('pending','confirmed','checked_in','checked_out')
   AND br.start_at_gmt < :to AND br.occupied_until_gmt > :from
UNION ALL
-- live holds of OTHER visitors
SELECT h.room_id, h.start_at_gmt, h.end_at_gmt, 'hold', h.id, ''
  FROM holds h
 WHERE h.room_id IN (…) AND h.expires_at_gmt > :now AND h.token <> :my_token
   AND h.start_at_gmt < :to AND h.end_at_gmt > :from
UNION ALL
-- blocks on the room, its type, its floor or the whole property
SELECT r.id, b.start_at_gmt, b.end_at_gmt, 'block', b.id, b.reason
  FROM blocks b JOIN rooms r ON ( b.scope = 'property'
                               OR (b.scope = 'room'      AND b.scope_id = r.id)
                               OR (b.scope = 'room_type' AND b.scope_id = r.room_type_id)
                               OR (b.scope = 'floor'     AND b.scope_id = r.floor_id) )
 WHERE r.id IN (…) AND b.start_at_gmt < :to AND b.end_at_gmt > :from
```

The result becomes `busy[room_id] = [ {s, e, kind, ref_id, label}, … ]`, sorted by start.

Details that matter:
- `occupied_until_gmt` is the end of the stay, or the actual time if the guest checked out
  early. An early check-out frees the room from that moment.
- "Now" comes from PHP (`Dates::now()`), not the MySQL clock. Every server then agrees on which
  holds have expired.
- If this query fails, it throws `503 busy`. A failed read must never look like "nothing is
  booked".

**Query 4: the rate calendar** (`RateCalendar::prime()`)

The `rate_calendar` rows for every date a unit starts on, for those room types: closed dates
(the whole room type, or one rate) and price overrides. From here on, `RateCalendar::closure()`
and `::override()` answer from memory.

`OccupancyCalculator::primeFrom()` also reuses the busy list from query 3 to count each room
type's occupancy per day. Pro's occupancy pricing reads those counts without running another
query.

### 3.3 The decision, in memory

For each room type, for each of its rates:

**a. Rule checks** (`ruleReason()`, in this order; the first failure is the reason shown)

| Reason | Fails when |
|---|---|
| `past` | the window has already started (staff get 30 minutes of grace) |
| `booking_window` | public only: arrival is further ahead than Settings → Booking allows |
| `same_day_cutoff` | public only: arrival is today and same-day booking is off or past its cut-off |
| `rate_disabled` | the rate is switched off or the plan is inactive |
| `room_type_closed` / `rate_closed` | the rate calendar closes a covered date |
| `min_units` / `max_units` | a multi-unit stay is shorter or longer than the rate allows |
| `occupancy` | more adults or children than the room type takes |
| `no_rooms` | the room type has no room in the `available` state |

**b. Price**: only if the rules pass (§5 below).

**c. Room by room**: for **each room of this type**, `Overlap::roomReason()`:

```php
if ( room.state !== 'available' )            → reason = 'maintenance' / 'out_of_service'
foreach ( busy[room] as interval ) {
    if ( overlaps( window, interval ) )        → 'booked' | 'held' | 'blocked'   (the real reason wins)
    if ( overlaps( window, interval, buffer ) )→ 'buffer'  (free only once cleaning time passes)
}
otherwise                                      → free for this rate
```

with the one formula used everywhere:

```
overlaps(a, b) :=  a.start < b.end + buffer   AND   b.start < a.end + buffer
```

The intervals are **half-open** (`[start, end)`): a stay ending at 17:00 and one starting at
17:00 don't collide. The cleaning buffer (from the room type, else Settings → Booking) is added
to the end of both stays. Blocks get no buffer: they are closures, not stays.

A free room adds the plan to the room's `available_for` list. A busy room gets a reason in
`reasons_by_rate[plan]`. If no room of the type is free, the rate's reason becomes
`fully_booked`.

### 3.4 What the browser receives, and how the grid is drawn

```jsonc
{
  "span": { "arrival": "2026-10-28", "departure": "2026-10-28", "nights": 0 },
  "room_types": [{
    "id": 1, "name": "Standard Room", "rooms_total": 12, "rooms_sellable": 12,
    "rates": [{
      "rate_plan_id": 3, "name": "24 Hours Fixed Stay",
      "window": { "start": "2026-10-28T08:30:00+00:00", "end": "2026-10-29T08:00:00+00:00" },
      "units": 1, "available": true, "free_count": 11,
      "price": { "total": 20000, "steps": [ … ] }, "reasons": []
    }],
    "floors": [{ "name": "Ground floor", "rooms": [
      { "id": 11, "number": "A1", "available_for": [3] },
      { "id": 12, "number": "A2", "available_for": [], "reasons_by_rate": { "3": { "code": "held" } } }
    ]}]
  }],
  "other_options": [ … plans that don't fit the span … ]
}
```

That is all the form needs:
- The **rate card** shows `price.total` when `available`, otherwise the first reason's message.
- The **room grid** for the chosen rate shows each room as *Free* when the plan is in its
  `available_for`, otherwise with the code from `reasons_by_rate` (booked, held, blocked,
  buffer, maintenance…).
- Public responses never include booking references, block reasons or guest data. Staff
  responses add the booking reference so the desk can open it.

Because one response covers every room type, rate and room for the date, the grid updates in a
single round trip whenever the date changes.

---

## 4. Step 2: the guest picks rooms (the hold)

Clicking **Hold 1 room** calls `POST public/holds` with the room ids, the plan, the arrival, the
units and the guests. `HoldService::createMany()` (`includes/Services/Availability/HoldService.php`)
runs:

```
Transaction::run( function () {
    checked = BookingWriter::lockAndCheck( requests, token, audience )   // §6, rooms locked
    for each checked room:
        same room + window already held by this token? → keep that hold
        otherwise                                       → INSERT INTO holds (…, token, expires_at_gmt)
    every hold of the token gets the same expiry
} )
```

- The **token** groups a visitor's holds. The browser keeps it (`useHolds()`,
  `src/components/booking/holds.js`) and sends it with every later search, hold and booking.
- A hold lasts `holdMinutes` (Settings → Booking, default 15). A web token can be extended, but
  never beyond its first hold plus 3 hold periods.
- Web holds are tied to the visitor's session cookie and network. One visitor can hold at most
  `maxRoomsWeb` rooms.
- An **expired hold is ignored at once** by every query (`expires_at_gmt > now`). A cron sweep
  deletes the rows later; that's just tidying up.

From this moment every other visitor's search sees the room as `held`. Your own search still
shows it as yours, because your token is excluded in query 3.

---

## 5. How the price is worked out

`PriceResolver::price()` / `::quote()` (`includes/Services/Pricing/PriceResolver.php`) runs a
**pipeline of steps** for each unit (each night or day) and adds the units together:

| Pos. | Step | Where it comes from |
|---|---|---|
| 10 | Base price | `room_type_rates.price` |
| 20 | Sale | `sale_price` when set and lower (NULL = no sale; 0 is a real free price) |
| 30 | Date override | the rate calendar's `price_override` for that date (`rtbp_price_date_override`) |
| 40 | Seasonal | **Pro**, added through `rtbp_price_steps` |
| 50 | Occupancy | **Pro**: the room type's occupancy that day, from the primed counts |
| 60 | Early bird / last minute | **Pro** |
| 90 | Floor and round | `max(0, x)`, rounded with the currency's precision |

Each step records `{ step, label, before, after }`. That list is the "why is it this price?"
breakdown, and it's frozen on the booking line when the booking is created. Later rule changes
never re-price an existing booking.

The search and the write path call the **same** pipeline, so the desk and the website always
quote the same price. Occupancy is counted without the visitor's own holds in both places, so
holding a room never makes its own price jump.

---

## 6. The safety net: `BookingWriter::lockAndCheck()`

File: `includes/Services/Booking/BookingWriter.php`. Every path that makes a room busy goes
through it: hold, booking, adding or editing a line, changing room at check-in, import.

The search (§3) is only a **picture** of the moment the page loaded. Between that moment and the
click, another guest or the desk may have taken the room. So the write path never trusts the
search. It does the whole check again, **inside a database transaction, with the rooms locked**:

```
1. SELECT … FROM rooms WHERE id IN (…) ORDER BY id FOR UPDATE      ← row lock, ascending id
2. for each requested (room, plan, arrival, units):
     - the room still exists and still belongs to the room type      ('removed', 'moved')
     - re-quote the price (also refuses a rate no longer sold or units out of range)
     - re-derive the window with StayWindow::for()
     - the room state is 'available'
     - not in the past; the rate calendar does not close the dates
     - the public booking window / same-day cut-off; the guest limits
     - the price shown (`expected_total`) still matches → else 409 price_changed + the new total
3. two requests of the same call on the same room must not overlap each other
4. AvailabilityRepository::conflicts() → busy() again + Overlap::conflict()  ← same predicate as the search
5. any conflict → 409 room_unavailable (naming the room); nothing is written
```

Why this is reliable:
- **The row lock is the guarantee.** MySQL can't enforce "no two stays overlap" as a
  constraint. Two requests for the same room queue on `FOR UPDATE`: the second one waits, then
  re-reads the busy list and sees the first one's hold or booking.
- **Ascending id order** means two writers locking rooms {3, 7} and {7, 3} never deadlock.
- **READ COMMITTED**: `Transaction::run()` sets this isolation level, so the read made after
  waiting for the lock sees what the previous lock holder just committed. Under MySQL's default
  REPEATABLE READ it could read an older snapshot. With it, two parallel bookings for the last
  room give exactly one success and one 409.
- **One predicate.** The search and the write path both use `busy()` + `Overlap`, so "free" means
  the same thing in both places.

---

## 7. Step 3: the guest confirms (the booking)

`POST public/bookings` goes to `PublicBookingService::create()`. It sorts out the guest (signed
in, Express checkout, a returning guest matched by details, or a new one) and the settings
(consent, required fields), then calls `BookingService::create()`
(`includes/Services/Booking/BookingService.php`):

```
Before any lock (fast answers):
  - the hold token belongs to this visitor
  - public: the token must still have live holds, else 410 hold_expired

Transaction::run( function () {
  1–3  lockAndCheck( lines, token )           // rooms locked, everything re-checked and re-priced
       public: each line must match one of the token's live holds (same room and window),
               else 409 hold_mismatch — a visitor cannot book a room they did not hold
       the token's own holds are NOT conflicts (they are the visitor's)
  4    the guest: an existing record, or a new one created now (inside the lock)
       a banned guest → 409, nothing written
  5    INSERT bookings      reference RT-2026-000123 from a numbered sequence,
                            status pending (manual approval) or confirmed, totals
       INSERT booking_rooms one row per room: room, plan, start/end (local + GMT),
                            occupied_until_gmt = end, unit price, total, frozen price breakdown
       payment deadline, invoice number (same transaction: a rollback gives the number back)
       DELETE the token's holds
       rtbp_activity( 'bookings.create' )
} )
after commit: rtbp_booking_created → e-mails, notifications, counters
```

If anything throws, the whole transaction rolls back: no booking, no lines, no new guest, no
invoice number, and the holds stay in place. One booking can contain several rooms. A three-room
booking where the third room was taken meanwhile writes **nothing**.

The new `booking_rooms` rows have an occupying status, so from the next search on, those rooms
come back from query 3 as `booked` for any overlapping window.

---

## 8. How rooms become free again

| Event | Effect |
|---|---|
| Hold expires | Ignored immediately (`expires_at_gmt > now`); rows swept by cron |
| Guest removes a room / leaves | `DELETE public/holds/{token}` releases it at once |
| Booking cancelled / declined, no-show | Lines leave the occupying statuses in the same transaction |
| Early check-out | `occupied_until_gmt = now`; the room is free from then (+ buffer) |
| Payment deadline passed, auto-release on | Cron cancels the booking, with a guest e-mail |
| Overstay (still checked in after the end) | **Not** released: the room stays occupied until check-out |

---

## 9. Worked example

Settings: buffer 0 min. Standard Room has rooms A1–A12 and sells *Half Day 08:30–17:00*
(single stay), *Rest Time 17:00–20:00* (single stay) and *24 h Fixed 08:30→08:00* (multi-unit).
A2 has a confirmed Half Day booking on 28 Oct. Another visitor holds A3 for 24 h Fixed from
28 Oct.

A guest searches **28 Oct → 28 Oct, 2 adults** (`nights = 0`):

| Rate | Window | Rules | A1 | A2 | A3 | Result |
|---|---|---|---|---|---|---|
| Half Day | 28 08:30 → 28 17:00 | pass | free | `booked` (same window) | `held` (overlaps 08:30–08:00) | available, 10 free |
| Rest Time | 28 17:00 → 28 20:00 | pass | free | **free** (17:00 = end of Half Day, half-open) | `held` | available, 11 free |
| 24 h Fixed | 28 08:30 → 29 08:00 | pass | free | `booked` | `held` | available, 10 free |

With a 30-minute buffer, A2 would show `buffer` for Rest Time: its Half Day ends at 17:00, so
the room is only ready at 17:30.

The guest picks Rest Time on A2 → `POST public/holds` → lock A2 → re-check (still free) → hold
row. Ten minutes later they confirm → lock A2 → re-check (their own hold is not a conflict) →
booking `RT-2026-…`, line A2 17:00→20:00, hold deleted.

The same search for **28 → 31 Oct** (`nights = 3`) gives 24 h Fixed ×3 (ends 31 Oct 08:00) and
lists Half Day and Rest Time under *Other stay options* as `incompatible_span`.

---

## 10. Why it stays correct when everything is configurable

1. **Behaviour comes from fields, never from names.** A plan's window comes from `type`,
   `start_time`/`end_time` or `duration_minutes`, `checkin_from`/`until` and `multi_unit`.
   Nothing checks whether a name contains "24". A new plan works without code.
2. **Inventory follows the room's type.** Rooms are always selected by `room_type_id`, so a
   rate can only ever be sold on its own type's rooms. Moving a room to another type takes its
   existing bookings along. The new type sees them as busy, and a hold made before the move
   fails with `moved`.
3. **One overlap formula** (`Overlap::overlaps`) on GMT timestamps, half-open, with the buffer,
   used by both the search and the write path.
4. **Read cheaply, write carefully.** The search is four queries and may be slightly stale. The
   write path locks the rooms and repeats every check, so a stale screen can only end in a clear
   409 (`room_unavailable`, `price_changed`, `rate_closed`…), never in a double booking.
5. **Time is handled once.** `StayWindow` builds windows on the wall clock in the site's time
   zone and stores both local and GMT. Midnight and DST need no special cases elsewhere.
6. **Fail closed.** A failed availability read throws `503 busy`; it never reads as "free".
7. **Prices are a pipeline with a recorded breakdown**, frozen on the booking line, and Pro
   extends it through `rtbp_price_steps` without touching the free plugin.

---

## 11. Where to look in the code

| Part | File |
|---|---|
| Endpoints | `includes/Routes/routes.php`: `public/availability`, `public/holds`, `public/bookings` (`PublicBookingController`); desk `availability` (`AvailabilityController`), `holds` (`HoldController`) |
| Search | `includes/Services/Availability/AvailabilityService.php` → `search()` |
| The four queries, the lock | `includes/Repositories/AvailabilityRepository.php` |
| Windows | `includes/Services/Availability/StayWindow.php` |
| Overlap and room reasons | `includes/Services/Availability/Overlap.php` |
| Closed dates, price overrides | `includes/Services/Availability/RateCalendar.php` |
| Occupancy per day | `includes/Services/Availability/OccupancyCalculator.php` |
| Holds | `includes/Services/Availability/HoldService.php`, `src/components/booking/holds.js` |
| Locked re-check | `includes/Services/Booking/BookingWriter.php` → `lockAndCheck()` |
| Booking write | `includes/Services/Booking/BookingService.php` → `create()` |
| Website booking (guest, Express checkout, settings) | `includes/Services/Booking/PublicBookingService.php` |
| Pricing | `includes/Services/Pricing/PriceResolver.php` (+ Pro's `Features/Pricing`) |
| Booking form | `src/components/booking/` (`BookingFlow.jsx`, `GuestBookingSteps.jsx`, `api.js`) |
| The rules, edge cases and checklist | `docs/project/booking-engine.md` |
