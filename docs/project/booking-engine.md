# The booking engine: time-based room booking

**Authoritative.** Read this before touching availability, pricing, holds or any code that
writes a booking. Modules M06, M07, M08, M02, M03, M04, M05 and M10 implement parts of it. The
`rtbp-critical-reviewer` agent reviews code against it.

---

## 1. The model in one picture

The hotel does not sell nights. It sells **time windows** on **physical rooms**:

```
                 08:30        17:00  20:00                    08:00(+1)
Room A2  Day D   ├── Half Day ──┤├Rest┤├──────── Overnight ───────────┤
                                                               │
Room A2  Day D+1                                               ├── Half Day ──┤ …
```

- A **room type** (*Standard Room*, *Room VIP*; the diagram's "package") **owns** a fixed set of
  **rooms**. A room belongs to exactly one room type, forever (`rooms.room_type_id`). Two room
  types never share a room, even when they sell the identical window.
- A **rate plan** is a reusable window from the shared library: *Half Day 08:30–17:00*,
  *Rest Time 17:00–20:00*, *Overnight 20:00→08:00*, *24 h Fixed 08:30→08:00*, *24 h Flexible*…
  Rate plans do not own rooms.
- A **room type rate** says "room type X sells rate plan Y at price P (sale S, min/max units,
  on/off)". The same window can cost 10 000 CFA on Standard and 15 000 CFA on VIP.
- A **booking line** (`booking_rooms`) is *one physical room × one concrete window*
  `[start_at, end_at)`. A booking has one or more lines.
- A room is **available** for a window when no occupying line, active hold or block on that room
  overlaps the window (with the buffer), and the room's state is `available`.

The booking flow from the diagram, as implemented:

```
1 pick dates/guests → 2 each room type × each enabled rate plan → derive the window (§2)
→ 3 engine: rule checks (§5.2) + overlap against that room type's rooms only (§4, §5.3)
→ 4 filter to the room type's own inventory → 5 show rates with price or reason; rooms with state or reason
→ 6 pick a room → hold (§7) → 7 guest details → 8 create under lock (§7)
```

## 2. Rate plan types and window derivation

| Type | Defined by | Window for a line starting on local date `D`, with `n` units |
|---|---|---|
| `fixed` | `start_time`, `end_time` (30-minute steps) | `start = D @ start_time` · `dur = (end_time − start_time) mod 24h`, and **0 means 24h** · `end = start + dur + (n − 1) days` |
| `flexible` | `duration_minutes` (for example 1 440), `checkin_from`–`checkin_until` (the allowed start times) | `start = D @ chosen_time` (validated to lie within the allowed range, 30-minute steps) · `end = start + dur + (n − 1) days` |

- **Midnight crossing** needs no special case: `end_time ≤ start_time` means the end falls on the
  next calendar day (`20:00→08:00` has a duration of 12 h).
- **Units.** `n` is 1 unless the plan has `multi_unit = true`, in which case `n` is the number of
  nights or days requested (bounded by the rate's `min_units` / `max_units`). Part-day plans
  (*Half Day*, *Rest Time*) are `multi_unit = false`. 24 h and overnight plans are
  `multi_unit = true`.
- **Adding days** is calendar arithmetic in the site time zone (`DateTimeImmutable::modify('+1 day')`
  in `Africa/Abidjan`), *then* the result is converted to GMT. Never add 86 400 seconds.
- **Search span → units.** The search gives arrival date `A` and departure date `B ≥ A`.
  `nights = B − A` in days.
  - A single-unit plan fits the span when `nights == 0` (same-day window), or when
    `nights == 1` and the window crosses midnight or lasts 24 h.
  - A multi-unit plan uses `n = max(1, nights)`.
  - A plan that does not fit the span is reported as `incompatible_span` and listed under
    *Other stay options*, not as unavailable.
- **The client's plans**, as the live data stands (legacy `rate_plan` terms, read on 2026-09-28).
  They are seeded in M07 T1:

  | Legacy id | Plan (EN · FR label to confirm) | Type | Window | Duration | Multi-unit |
  |---|---|---|---|---|---|
  | 43 | Half Day Stay · Demi-journée | fixed | 08:30 → 17:00 | 8 h 30 | no |
  | 94 | Extended Half Day Stay · Demi-journée prolongée | fixed | 08:30 → 20:00 | 11 h 30 | no |
  | 91 | Rest Time · Temps de repos | fixed | 17:00 → 20:00 | 3 h | no |
  | 90 | Overnight Stay · Nuitée | fixed | 20:00 → 08:00 (+1) | 12 h | yes |
  | 95 | Extended Overnight Stay · Nuitée prolongée | fixed | 20:00 → 12:00 (+1) | 16 h | yes |
  | 92 | 24 Hours Fixed Stay · Journée 24H | fixed | 08:30 → 08:00 (+1) | 23 h 30 | yes |
  | 93 | 24 Hours Flexible Stay · Journée flexible 24H | flexible | chosen check-in (legacy default 08:00) + 24 h | 24 h | yes |

  Notes: *24 Hours Fixed* really ends at **08:00**, not 08:30. This leaves a 30-minute gap
  before the next Half Day, which acts as a built-in cleaning slot. *Extended Half Day* overlaps
  *Rest Time*, so the two can never sell the same room on the same day.

  The legacy system detected 24 h plans with `strpos($name, '24')`. **We never infer behaviour
  from a name.** Type, duration and `multi_unit` are explicit fields.

## 3. Time representation

- Stored: `start_at` / `end_at` (`DATETIME`, site-local, for display and for "today" queries) **and**
  `start_at_gmt` / `end_at_gmt` (`DATETIME`, UTC, for every comparison). Both are written by
  `StayWindow::toRow()` and nothing else.
- In PHP: `DateTimeImmutable` with an explicit `DateTimeZone`. The `Dates` helper (M00) is the only
  place `wp_timezone()` is read.
- In the API: ISO 8601 with offset (`2026-10-02T20:00:00+00:00`). The UI formats with the site
  time zone, not the browser's.
- Abidjan is UTC+0 with no DST today. The checks still include a DST-zone case
  (`Europe/Paris`), so the arithmetic is proven correct for any site.

## 4. Overlap

Intervals are **half-open**: `[start, end)`. A window ending at 17:00 and one starting at 17:00 do
**not** collide.

```
overlaps(a, b) := a.start < b.end_eff  AND  b.start < a.end_eff
end_eff        := occupied_until + buffer
```

- `occupied_until` = `end_at_gmt` by default. **Checking a guest out early** sets it to the
  actual check-out time, which frees the room from that moment (feature 3.10). A no-show or a
  cancellation removes the line from the occupying set entirely.
- **Buffer** (cleaning time, D4): `room_types.buffer_minutes`, falling back to the setting (default
  0). It is added to the **end** of both the existing and the candidate interval, so the room is
  free *buffer* minutes after any stay ends.
- **What occupies a room:**

  | Kind | Occupies when |
  |---|---|
  | Booking lines | status `pending`, `confirmed` or `checked_in`; and `checked_out` up to `occupied_until` |
  | Holds | `expires_at_gmt > now`, excluding the requester's own hold token |
  | Blocks | scope `room` = that room; `room_type`, `floor` or `property` = every room in scope |
  | Room state | `maintenance` / `out_of_service` makes the room unsellable for any window. State is current, not dated; dated closures are blocks |

  `pending` lines occupy (D5): an unapproved booking holds its room, as in the legacy system and
  as feature 2.13 requires.

## 5. Availability

### 5.1 Request and response

```
GET availability?arrival=2026-10-02&departure=2026-10-02&adults=2&children=0
                &room_type_id=(optional)&rate_plan_id=(optional)&checkin_time=(flexible plans)
                &exclude_booking_line=(when editing a line)
```

```jsonc
{
  "span": { "arrival": "2026-10-02", "departure": "2026-10-02", "nights": 0 },
  "room_types": [{
    "id": 1, "name": "Standard Room", "free_rooms_now": 7,
    "rates": [{
      "rate_plan_id": 3, "name": "Half Day", "window": { "start": "…T08:30…", "end": "…T17:00…" },
      "units": 1, "available": true, "free_count": 5,
      "price": { "total": 10000, "unit": 10000, "regular": 12000, "steps": [ … §6 … ] },
      "reasons": []
    }, {
      "rate_plan_id": 5, "name": "Overnight", "available": false,
      "reasons": [{ "code": "fully_booked" }]
    }],
    "floors": [{ "id": 2, "name": "Floor 1", "rooms": [
      { "id": 11, "number": "A1", "available_for": [3], "reasons_by_rate": { "5": ["booked"] } },
      { "id": 13, "number": "A3", "available_for": [], "state": "maintenance" }
    ]}]
  }],
  "other_options": [ … rates with incompatible_span … ]
}
```

Staff responses add `booking_ref` to `booked` reasons. Public responses never include booking
references, state notes or guest data.

### 5.2 Rule checks (per room type × rate plan), in this order. The first failure is the reason

| Code | Check |
|---|---|
| `past` | the window start is before now (staff may override for a walk-in in progress, up to 30 min) |
| `booking_window` | the arrival is later than today + the booking window (public only; 0 = unlimited) |
| `same_day_cutoff` | the arrival is today, and same-day booking is off or the cut-off has passed (public only) |
| `rate_disabled` | the room type rate is off, or the rate plan is inactive |
| `room_type_closed` | a calendar row with `rate_plan_id NULL` has `is_closed` for any date covered |
| `rate_closed` | a calendar row for this rate has `is_closed` for any covered date |
| `min_units` / `max_units` | `n` is outside the rate's bounds |
| `occupancy` | adults > `max_adults`, or children > `max_children` (children are ages ≤ max child age) |
| `incompatible_span` | §2 |
| `no_rooms` | the room type has no sellable rooms |
| `fully_booked` | none of the room type's sellable rooms is free for the window (§5.3) |

Room-level reasons: `booked`, `held`, `blocked`, `maintenance`, `out_of_service`, `buffer` (free
only once the cleaning buffer passes).

### 5.3 The query plan: four queries, whatever the size of the property

Never run one query per rate plan or per room (the legacy system's N+1).

1. **Catalogue:** the room types in scope + their enabled rates + rate plans (one join query).
2. **Rooms:** `SELECT id, room_type_id, floor_id, number, number_sort, state FROM rooms WHERE room_type_id IN (…) AND deleted_at IS NULL`.
3. **Busy intervals** for those rooms across the **envelope** of every candidate window
   (min start → max end + buffer):
   ```sql
   SELECT room_id, start_at_gmt AS s, occupied_until_gmt AS e, 'line' AS kind, booking_id
     FROM booking_rooms
    WHERE room_id IN (…) AND status IN ('pending','confirmed','checked_in','checked_out')
      AND start_at_gmt < %s AND occupied_until_gmt > %s   -- envelope, minus buffer on the left
   UNION ALL
   SELECT room_id, start_at_gmt, end_at_gmt, 'hold', NULL FROM holds
    WHERE room_id IN (…) AND expires_at_gmt > UTC_TIMESTAMP() AND token <> %s
      AND start_at_gmt < %s AND end_at_gmt > %s
   UNION ALL
   SELECT … FROM blocks WHERE (scope/scope_id matches the rooms, their types, floors, or the property) AND …
   ```
   Uses index `(room_id, start_at_gmt, end_at_gmt)`.
4. **Calendar + pricing rules** for the dates covered (one query each, or one UNION).

Then, in memory: for each room type × rate, derive the window, run §5.2, test each of the room
type's rooms against its busy list (sorted, so a binary search or linear scan suffices), and price
with §6. Complexity O(room types × rates × rooms × busy-per-room), which is trivial at this scale.

**Package scoping** is structural: step 2 selects rooms by `room_type_id`, so another type's rooms
are never evaluated, returned or shown.

## 6. Pricing: the documented order (feature 7.16)

`PriceResolver::quote( roomTypeRate, window, n, context )` returns `{ unit_prices[], total, steps[] }`.
For each unit `i` (the local date `Dᵢ` of the unit's start):

| # | Step | Rule |
|---|---|---|
| 1 | **Base** | `room_type_rates.price` |
| 2 | **Sale** | if `sale_price` is set and `< price`, it replaces the base. Never 0 by accident: NULL means no sale |
| 3 | **Date override** | if `rate_calendar(room_type, rate_plan, Dᵢ).price_override` is set, it replaces the result of 1–2 |
| 4 | **Seasonal** | the single matching `seasonal` rule for `Dᵢ` wins by: higher `priority` → more specific scope (room type + rate > room type > rate > all) → newest. Applies its template (`increase` / `decrease` / `set`, `percent` / `fixed`) |
| 5 | **Occupancy** | the most specific `occupancy` rule, then the highest `threshold` reached by occupancy on `Dᵢ` (the room type's occupied ÷ sellable rooms over that local day). Increase or decrease per rule |
| 6 | **Booking window** | `early_bird` (days between booking and arrival ≥ threshold; the highest threshold wins) **or** `last_minute` (days ≤ threshold; the lowest threshold wins). Specificity first. Only one applies; early-bird wins a tie |
| 7 | **Floor and round** | `max(0, x)`, rounded to the whole franc (XOF has no minor unit) |

- `total = Σ unit prices`. A single-unit plan has exactly one unit (the legacy "one price per
  stay"). A multi-unit plan sums the nightly prices.
- Every step appends `{ step, rule_id, label, before, after }` to `steps`. That array is stored on
  the booking line as `price_breakdown` and rendered by `PriceBreakdown` ("why is it this price?").
- The price is **frozen** on the line at creation (`unit_price`, `total`, `price_breakdown`).
  Nothing ever re-prices an existing line except an explicit line edit (M03), which is logged.
- Extension point: the `rtbp_price_steps` filter can insert a step (for example promo codes later).
- The legacy system skipped pricing rules inside `is_admin()`. We apply the same pipeline
  everywhere. The desk and the web always quote the same price.

## 7. Writing: holds, locks and transactions (ADR-008)

**Holds** (feature 2.14). Selecting a room (desk or web) calls `POST holds`, which runs the §7.1
write path and inserts a `holds` row with a TTL (setting, default 15 min). The token travels with
the form. Creating the booking consumes the holds. Expired holds are ignored by queries at once
and deleted by a cron sweep every 5 min.

### 7.1 The locked write path (used by: create hold, create booking, add line, edit line, change room at check-in, import)

```
Transaction::run(function () {
    // 1. Lock the rooms, in ascending id order (prevents deadlocks)
    SELECT id, state FROM rooms WHERE id IN (…) ORDER BY id FOR UPDATE;
    // 2. For each requested (room, window): re-check inside the lock
    //    state = available · no occupying line · no block · no foreign active hold
    //    (the same predicate as §4, via AvailabilityRepository::conflicts())
    // 3. Re-quote the price inside the lock (rules may have changed since the page loaded).
    //    If it differs from the displayed price, fail with `price_changed` + the new quote
    //    unless the client sent accept_new_price=true
    // 4. Write: booking (Sequence reference), lines (snapshots + frozen price),
    //    delete the consumed holds, invoice number (M05)
    // 5. rtbp_activity(…) inside the same transaction (Pro stores it)
});
// after commit: hooks → e-mails, cache invalidation, notifications
```

- A conflict returns `409 room_unavailable` with the room and, for staff, the conflicting booking
  reference. The UI refreshes availability and keeps the rest of the form.
- MySQL cannot express "no overlapping ranges" as a constraint. **The row lock is the guarantee.**
  Every path that makes a room busy must take it. Code review rejects a write that doesn't.
- Tables must be InnoDB (the installer forces `ENGINE=InnoDB`).

## 8. Release paths

| Event | Effect on inventory |
|---|---|
| Hold expires | Ignored immediately; deleted by the sweep |
| Booking declined / cancelled | Its lines leave the occupying set in the same transaction |
| Line removed / edited | Old window freed, new window checked under lock |
| No-show | Line leaves the occupying set |
| Early check-out | `occupied_until = now` |
| Payment deadline passed + auto-release on (M05) | Cron cancels the booking as `system` (idempotent), with a guest e-mail |
| Overstay (checked in past `end_at`) | Not released automatically. Dashboard flags *overdue check-out*, and the room remains occupied until check-out |

## 9. Status model (what the engine cares about)

Line status: `pending → confirmed → checked_in → checked_out`, plus `declined`, `cancelled`,
`no_show`. Occupying: `pending`, `confirmed`, `checked_in`, and `checked_out` until
`occupied_until`. The transition table is in M03. Payment status is a separate axis (M05) and
never affects inventory, except through the deadline release.

## 10. Edge cases: the verification checklist (check each row on the Local site)

| # | Case | Expected |
|---|---|---|
| 1 | Half Day 08:30–17:00 then Rest Time 17:00–20:00, same room, buffer 0 | both sellable |
| 2 | Same, buffer 30 | Rest Time unavailable, reason `buffer` |
| 3 | Overnight D 20:00→D+1 08:00, then Half Day D+1 08:30 | both sellable |
| 4 | Overnight D, then Half Day D+1 starting 08:00 (a custom plan) | both sellable (08:00 = end) |
| 5 | Extended Overnight D 20:00→D+1 12:00, then Half Day D+1 08:30 | Half Day blocked |
| 6 | 24 h Fixed (08:30→08:00) ×3 from D | end D+3 08:00; Half Day D+3 08:30 sellable |
| 6b | Extended Half Day 08:30–20:00 booked, then Rest Time 17:00–20:00 same room | Rest Time `booked` |
| 7 | 24 h Flexible from D 14:00 ×1 | end D+1 14:00; Half Day D+1 is blocked |
| 8 | Flexible with a check-in time outside the allowed range | `invalid_checkin_time` (validation) |
| 9 | Pending, unpaid booking on A1 | A1 `booked` for overlapping windows |
| 10 | The same room held by another session, not expired | `held` |
| 11 | The requester's own hold | not a conflict |
| 12 | Hold expired 1 s ago | not a conflict |
| 13 | Block on Floor 2 for D | every Floor 2 room `blocked`, other floors fine |
| 14 | Room in maintenance | excluded; reason `maintenance` |
| 15 | Early check-out at 11:00 of a 08:30–17:00 stay | room free from 11:00 (+buffer) |
| 16 | Cancelled line | not a conflict |
| 17 | Rate calendar closes Rest Time on 31 Dec | Rest Time `rate_closed` for that date only |
| 18 | Multi-night Overnight ×2 where night 2 is closed | `rate_closed` |
| 19 | Search nights = 3 on Half Day | `incompatible_span`, listed in other options |
| 20 | Two parallel creates for the last room | exactly one succeeds, the other 409 (concurrency check) |
| 21 | A window crossing a DST change (Europe/Paris fixture) | the duration stays at wall-clock hours; GMT columns correct |
| 22 | 29 Feb multi-night | correct end date |
| 23 | Room moved to another type with a future booking | the booking keeps its line; the room's new type sees it as busy |
| 24 | Seasonal +20 % and early-bird −10 % both apply | the steps show 4 then 6; the total is correct and rounded |
| 25 | Sale price 0 vs NULL | 0 is a real (free) price only if explicitly set; NULL means no sale |
| 26 | Staff creates a walk-in 10 min after the window start | allowed for staff (grace 30 min), refused on the web |

## 11. Where each part lives

| Part | Class | Module |
|---|---|---|
| Window derivation | `Services\Availability\StayWindow` (pure) | M07 T1 (M08 reuses it) |
| Overlap and conflicts | `Repositories\AvailabilityRepository` + `Services\Availability\Overlap` (pure) | M08 T1–T2 |
| Search | `Services\Availability\AvailabilityService::search()` | M08 T2 |
| Holds | `Services\Availability\HoldService` | M08 T3 |
| Pricing | `Services\Pricing\PriceResolver` + `Rules\*` | M07 T4–T6 |
| Locked write path | `Services\Booking\BookingWriter` (used by BookingService, HoldService, the importer) | M02 T1 |
| Occupancy for pricing and reports | `Services\Availability\OccupancyCalculator` | M07 T6 / M10 |
