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
| 2.7, 2.8, 2.9 | Guest lookup / new guest / ID document (M09 `GuestService::findOrCreate`) | T4a |
| 2.10, 2.11 | Summary + payment state on creation | T4b |
| 2.13 | Double booking impossible: `BookingService::create()` → `BookingWriter` locked path, consuming the holds | T1 |
| — | `bookings` + `booking_rooms` tables, `BookingService::create()`, the reference sequence (`RT-{year}-{000001}`), snapshots + frozen price, the initial status per manual-approval setting (desk default *confirmed*), and the `rtbp_booking_created` hook | T1 |
| — | `BookingFlow` component with `mode="desk"` (M04 adds `"guest"`), and `#/bookings/new` | T2–T4b |

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

- [x] T1 [Free] `bookings` + `booking_rooms` tables (all the architecture §4.4 columns) + `BookingService::create()` on `BookingWriter` + verification on the Local site (create, conflict 409, price_changed, banned guest, multi-line all-or-nothing) — done 2026-09-30
- [x] T2 [Free] `BookingFlow` shell + dates/guests bar + rates by room type (`RateCard`, `PriceBreakdown`, reasons, other options) — done 2026-09-30
- [x] T3 [Free] `RoomPicker` + holds (place, countdown, extend, release) + multiple lines — done 2026-09-30
- [x] T4a [Free] Guest step: lookup combobox (name / phone / e-mail), new guest with ID type and number, banned banner that blocks confirmation (2.7–2.9) — done 2026-09-30
- [x] T4b [Free] Summary (editable / removable lines, total) + payment state + note + confirm, with `price_changed` (show the new quote, accept) and `room_unavailable` (highlight the line, refresh the grid) + success screen linking to the booking (M03) (2.10, 2.11) — done 2026-09-30

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
- 2026-09-30 planning: the `bookings` / `booking_rooms` tables already exist with every architecture §4.4 column (M08 created them for the overlap queries), so T1 adds the `Booking` / `BookingRoom` models and repositories, `BookingService::create()` on `BookingWriter::lockAndCheck()`, `POST bookings`, the `bookings.create` activity and the `rtbp_booking_created` hook — no schema change. Consumed and present: `BookingWriter::lockAndCheck()`, `HoldService`, `AvailabilityService` (+ `dateRuleReason`, `guestCounts`), `OccupancyCalculator`, `PriceResolver`, `GuestService::findOrCreate()` / `lookup()`, routes `availability`, `holds`, `guests/lookup`, `Sequence`, the `PriceBreakdown` component, the `manualApproval` setting (M17). New in M02: `RateCard`, `RoomPicker`, `BookingFlow`. T4 split into T4a (guest step) and T4b (summary, confirm, conflicts, success). **Defaults (non-blocking):** desk bookings are always created `confirmed` (the legacy behaviour; `manualApproval` applies to web bookings, M04); *Paid now* sets `payment_status = paid`, `paid_total = total`, `balance_due = 0` and is logged — M05 turns it into a real payment record; references `RT-{year}-{000001}` from `Sequence::next( 'booking_' . year )`.
- T1 (2026-09-30, Free): `Models\Booking` (`PAYMENT_STATES`, `SOURCES`, soft deletes) / `Models\BookingRoom`, `BookingRepository` / `BookingRoomRepository` (`forBooking`), **`Services\Booking\BookingService::create( input, actor, source )`**: validates, checks the hold token belongs to the caller (new `HoldService::assertUsable()` — `lockAndCheck()` treats the token's holds as the caller's own, so a foreign token would bypass them), refuses a banned guest early, then in **one transaction**: `BookingWriter::lockAndCheck()` first (nothing read before the lock, §7.1), the guest after it (`GuestService::get` or `findOrCreate`, banned re-checked), the booking (`RT-{year}-{000001}` from `Sequence` `booking_{year}`; status from `BookingWriter::initialStatus('desk')` = confirmed; *paid* sets `paid_total = total`, `balance_due = 0`; currency from `general.currencyCode`; `public_token`), the lines (window `toRow()`, `occupied_until_gmt = end_at_gmt`, snapshots `rate_plan_name` / `room_number` / `floor_name`, frozen `unit_price` / `total`, `price_breakdown` = `{ unit_prices, steps }`), deletes the token's holds, logs `bookings.create`; `rtbp_booking_created( $booking, $source )` fires after the commit. `Resources\BookingResource::detail()` (M03 builds on it), `BookingController::store` → **`POST bookings`** (`bookings.create`), bindings. No schema change. Verified: 21/21 REST checks (hold → book consumes the hold; new guest with CNI; `guests.create` + `bookings.create` logged; the hook once, after the commit; same room → 409 with the booking reference, and the search shows one fewer room; 3-line booking with the 3rd taken → 409 index 2 and nothing written; invalid new guest after the lock → 422 rolled back; same room twice → 409; stale price → `price_changed` with the quote, accepted → 201; consecutive references; banned guest by id and via the *new guest* phone → `guest_banned`; a web session's hold token → 403, and without it the held room → 409 `held`; 422 input; signed out and `bookings.create` locked refused); **concurrency: two processes on the same room at the same instant, exactly one winner in 10 of 10 rounds**, references unbroken. booking-engine §10 rows 27–30 added. Test data removed.
- T2 (2026-09-30, Free): the shared flow lives in **`src/components/booking/`** (so M04's public page mounts the same code): `BookingFlow` (`mode="desk"|"guest"`; one search state drives every step; the chosen rate is kept by `{ room_type_id, rate_plan_id }`, so a new search updates its price and availability in place), `DatesBar` (arrival, departure — same day = day stays, moving the arrival keeps the number of nights —, adults / children steppers, the check-in time), `RateList` (per room type: *N rooms · up to N adults*, `RateCard`s in a sideways-scrolling row on phones, a 3-column grid from md; *Other stay options (n)* collapsed, each with **Use these dates** = departure set to the rate's window end date), `RateCard` (window `08:30–17:00` / `20:00 → 08:00` / `date time → date time · 2 nights|2 days`, `Money` + `PriceBreakdownPopover`, *N rooms free*, Select; unavailable → greyed with the engine's reason), `api.js` (`useAvailability`, refetch on focus), `rates.js`. `#/bookings/new` → `src/modules/Bookings/New.jsx`. **Check-in time:** the search always sends `checkin_time` (fixed plans ignore it): today → the next half hour (a walk-in checks in now), later days → 08:00 (legacy default), clamped to the plan's range; staff edits stick. Without it the engine starts a flexible plan at its earliest time (00:00), so a 24 h walk-in today read *already passed* and could never be chosen. Backend: flexible rates in the search now carry `checkin: { from, until }` (plan configuration, safe for the public search). New `siteNowTime()` in `src/lib/format.js`. Sidebar: `/bookings` no longer lights up on `/bookings/new` (`end`, as the phone tab bar already did). Verified in the browser: today (Half Day *already passed*, Overnight and 24 h from 13:30 selectable), tomorrow → 08:00 and the 24 h choice kept, 2 nights → *2 nights* / *2 days* labels and Half Day under other options, *Use these dates* back to one day, 3 adults → every rate *Too many guests for this room type*, the price breakdown popover per night, 360 px (cards scroll in their row, no page overflow), no console errors. Deviation: the search bar is not sticky yet and the phone stepper (Dates → Rate → Room → Guest → Review) comes with T4b, when all steps exist. Changelog line added.
- T3 (2026-09-30, Free): `RoomPicker` (the chosen type's floors → room tiles; free rooms are buttons, others greyed with *Booked* / *Being booked* / *Blocked* / *Cleaning* / *Maintenance* / *Out of service*, the booking reference in the tooltip for staff; rooms already in this booking for an overlapping window read *In this booking*), `holds.js` (`useHolds`: one token per booking, `place` → `POST holds` with the token, `release( hold_id )`, `releaseAll`; **auto-extend** `PUT holds/{token}` when ≤ 2 min are left, only if the form was used in the last 10 min (keydown / pointerdown), **at most 2 times** — the server does not limit extensions, the flow does; expired → the chip says rooms are checked again on confirm; **release on leave**: unmount (another screen) and `pagehide` (tab closed, reload) with a `keepalive` fetch), `HoldChip` (countdown, warning colour in the last 2 min), `BookingLines` (room · type · rate, window, guests, price, remove = release that hold; T4b's summary builds on it). `BookingFlow`: picking a room holds it and adds a line, then clears the rate so the next room starts from the rates again (2.12: each line has its own rate, window, dates and guest counts, taken from the search at pick time); the search sends the hold token so the booking's own holds are not counted as busy; a 409 `room_unavailable` on hold → *Room X was just taken. Choose another room.* and the grid refreshes (**acceptance 2**). Verified in the browser: hold A1 (chip 14:56, line added); another receptionist's server-side holds on A2 / A3 show *Being booked*, and a stale click on A3 → the toast and a refreshed grid; add B1 (one token, one shared expiry, the other holds not counted as busy); remove A1 → only its hold released; leave the screen → B1 released; reload with A1 held → released; with the hold period at its 5 min minimum, the hold was **extended automatically at 2 min left** (07:20:01 → 07:22:59) — setting restored to 15; 360 px (tiles wrap, no page overflow); no console errors. Test holds removed. Changelog line added.
- T4a (2026-09-30, Free): `GuestStep` (shown once the booking has a room): **find** — a search field (300 ms debounce, ≥ 2 characters) over `GET guests/lookup` (name without accents, phone, e-mail; results as a tap-friendly list with phone · e-mail · reference, *N stays* and a *Banned* badge), pick → the chosen guest card (reference, phone, e-mail, masked ID) with *Change*; **new guest** — first / last name, phone (required), e-mail (optional), identity document type (the M09 list, *Not shown* by default) and number (enabled once a type is chosen), started from what was typed (`startFrom()`: an e-mail, a phone — 4+ digits, spaces allowed —, else the last name), with the note that a phone or e-mail already on file sends the booking to that guest (`findOrCreate`); **banned** — a red banner *This guest is banned — A booking cannot be made for them* (confirmation is blocked in T4b; the API already refuses, T1). The value `{ mode, guest, fields }` lives in `BookingFlow` for T4b's confirm; `errors` prop ready for the server's field errors. Backend: `guests/lookup` also returns `id_types` (its access key is `bookings.create`; the guest list's `page.guests` may be locked for a receptionist). Verified in the browser: *kone* finds Awa Koné and the banned Moussa Koné; picking Moussa shows the banner and badge; *Change*; a phone with spaces → *No guest found* → *New guest* with the phone filled in (first try put it in the last name — fixed); the 6 document types, number enabled after choosing; 360 px (no sideways scroll); no console errors. Test guests and holds removed. Changelog line added.
- T4b (2026-09-30, Free): `SummaryStep` (total, payment *Unpaid* / *Paid now*, note, **Confirm booking**; disabled with the reason until a room and a guest — found, or new with first / last name and a phone of 8+ digits — are there; a banned guest disables it with its own line) and `BookingDone` (*Booking RT-… is confirmed* or *is waiting for approval* by status, guest, rooms with their windows and prices, total, payment badge, **Take another booking** resets the flow). Confirm posts `POST bookings` with the hold token, each line with **`expected_total` = the price shown**, `guest_id` or the new guest's fields, `payment_state`, `note`. Answers: **`price_changed`** → that line is outlined, its price and the total move to the new quote, and a warning *The price of room X changed from A to B* offers **Accept the new price and confirm** (`accept_new_price`); **any refusal with an `index`** (`room_unavailable`, `past`, `rate_closed`, `occupancy`, …) → that line is outlined, the server's message is shown, the grid refreshes, nothing else is lost; **`guest_banned`** → message, and a found guest is marked banned; **422 field errors** → under the new guest's fields. On success the holds are dropped locally (the booking consumed them), the search and guest caches refresh, and the page scrolls to the top. Verified in the browser — **acceptance 1**: today, Overnight, A1, new guest Fatou Diabaté with a CNI, unpaid → RT-2026-000001 (DB: guest with `cni` / number, no hold left; the scripted run from rate to a ready Confirm took about 6 s); **acceptance 4**: the sale price raised from 8 000 to 8 500 on the server after it was shown → the warning, the line outlined, total 8 500 → accept → RT-2026-000002 at 8 500 (price restored afterwards); **acceptance 3**: B1 + B2 + B3 for Awa, B2 booked meanwhile by someone else → 409, *Room B2 is no longer available*, B2 outlined, nothing written (3 bookings, 3 lines), B1 / B3 still held → remove B2 → RT-2026-000004 with B1 and B3; **acceptance 5**: banned Moussa → banner, *cannot be confirmed*, Confirm disabled; the *new guest* with his phone → refused (`guest_banned`), nothing written; an invalid e-mail → *Enter a valid e-mail address.* under the field; 360 px (Confirm full width, above the tab bar); no console errors. Test data removed. Changelog line added. **Deferred** (noted, not built): the sticky search bar and the phone stepper (Dates → Rate → Room → Guest → Review) from design-system §6 — the flow reads top to bottom on a phone and works; to reconsider with M04, where guests book on phones. The *open the booking* link on the success screen comes with the booking record screen (M03).
- Critical review (2026-09-30, `rtbp-critical-reviewer`, verdict *fix first*): 6 findings, **all fixed**. (1, high) *Accept the new price* sent one blanket `accept_new_price`, so a second moved price was charged unseen (and a *Paid now* booking recorded more than was collected) → the desk no longer sends the flag: the accepted line already carries the new quote as its `expected_total`, and any other moved price comes back as its own `price_changed`. (2, high) *Paid now* only needed `bookings.create` → it needs **`payments.record`** (legacy mark-as-paid): the controller asks for it (one PIN prompt for all keys), the service re-checks, and the option is hidden when locked; the move to real payment rows stays M05's (its recalculation must keep these flag-only bookings paid). (3, high) a *new guest* whose phone / e-mail is on file under **another name** was silently booked under that guest and the typed ID dropped → **409 `guest_exists`** with the match; the guest step shows *This phone is already on file for X — Use X*; under the same name (case and accents ignored) that guest is used; the success screen shows the guest the server used (`BookingResource` now returns `guest { id, reference, name }`). (4, medium) a failed line insert did not abort → checked like the booking row (a booking can no longer commit without its room). (5, medium) guest writes from a booking were not re-checked → a new guest needs **`guests.create`** (controller + service; *New guest* hidden when locked), and filling an existing guest's empty details needs `guests.edit` (skipped otherwise). (6, medium) `rtbp_booking_created` fired after the inner transaction → `Transaction::afterCommit()`. **Found while verifying (6), outside M02: Pro's `ActivityLogger` opened its own `START TRANSACTION` for every entry, which in MySQL silently commits the caller's open transaction and releases its row locks** — a booking with a new guest logged `guests.create` after locking the rooms and before writing the lines, so its room locks were released mid-write, and a later rollback undid nothing (same for any service that logs inside a transaction: holds, guests, staff). Fixed in Pro (`ActivityLogger::record()`): inside the free `Transaction`, the row is written through `Transaction::afterCommit()` (guarded with `method_exists`); a rolled-back action leaves no row. Verified: 15/15 regression checks (two moved prices shown one after the other and booked at exactly what was shown; *Paid now* refused without `payments.record`; `guest_exists` for another name, the same guest for the same name; `guests.create` / `guests.edit` locked; a forced line-insert failure rolls everything back; an outer rollback leaves no booking, no hook, no log row, and a normal create logs after its commit); the T1 suite 21/21; the concurrency check again, **each process creating a new guest: exactly one winner in 10 of 10**, and each winner has its `guests.create` + `bookings.create` rows, the losers none. phpcs exit 0 (free and Pro), both builds OK.
- From M08's critical review (2026-09-30): when READ COMMITTED is unavailable (binlog in STATEMENT format), the double-booking guarantee needs **no plain read inside the transaction before `BookingWriter::lockAndCheck()`** — read the booking/guest after it, or add a `FOR SHARE` to `AvailabilityRepository::conflicts()` on such hosts (booking-engine §7.1). Pass `adults` / `children` / `child_ages` on each `lockAndCheck()` request so the type's guest limits are enforced.
- Accepted 2026-09-30 by the user: acceptance 2–5 verified in the browser (see T3, T4b); the 60-second tablet timing of step 1 is left to the client's own trial.
