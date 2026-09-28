# M03 — The booking record and its life

| | |
|---|---|
| **Client module** | 3 — The booking record and its life |
| **Features** | 3.1–3.16 (3.3 and 3.4 are finished in M05) |
| **Depends on** | M02 (bookings exist), M09 (notes, guest panel) |
| **Tier** | Free |
| **Engine** | yes (adding and editing lines re-runs availability under lock; read `booking-engine.md` §4–§7) |
| **Critical review** | yes (transitions, line edits, price freeze) |

## Goal

One screen per booking, from creation to departure. It shows every room line with its own status,
the guest, the money, the notes and the history. Every action is a guarded transition. Prices are
frozen at booking time, so reports stay correct forever.

## Status model (the full rules are in `booking-engine.md` §9)

Each **line** moves on its own:

```
pending ──approve──► confirmed ──check_in──► checked_in ──check_out──► checked_out
   │                    │    └──no_show──► no_show
   ├──decline──► declined
   └──cancel───► cancelled ◄──cancel── confirmed
```

The **booking** `status` is a stored summary: *pending* if any line is pending; *checked_in* if any
line is checked in; *checked_out* if all live lines are checked out; *cancelled* / *declined* if
all lines are; *confirmed* otherwise. Approve, decline and cancel act on the whole booking by
default, or on one line. Check-in and check-out are always per line (3.5).

When manual approval is **off** (M17), desk bookings are created `confirmed`. Web bookings follow
the setting.

## Scope

| Feature | Summary | Task |
|---|---|---|
| 3.1 | Detail screen: header (reference, created, source, author, status and payment badges, primary actions), lines table (room type, rate plan, window, guests, room, line status, price), guest panel, money summary, notes, activity | T1 |
| 3.2 | Money summary: line prices, subtotal, discounts, tax, total, paid, balance, in CFA | T1 |
| 3.5 | Stay status per line, with independent check-in and check-out | T2 |
| 3.6, 3.7 | Approve; decline with a required reason (to the guest's e-mail in their language) | T2 |
| 3.8 | Cancel with a reason; releases the room(s) immediately (lines leave the overlap set) | T2 |
| 3.9, 3.10 | Check in (optionally change the room at check-in, under lock) / check out (warns when the balance is not zero). Record the actual time and the user | T2 |
| — | No-show (after the start time) | T2 |
| 3.11 | Add a room line to an existing booking (the same availability + lock path as creation) | T3 |
| 3.12 | Edit a line (rate plan, dates, room) or remove it: re-checks availability under lock, **re-prices only the edited line**, logs the old and new price, and re-issues the invoice (M05) | T3 |
| 3.16 | Price frozen: `unit_price`, `total` and `price_breakdown` stored on the line and never recomputed except by an explicit line edit | T3 |
| 3.13 | Booking notes (shared `NotesPanel`, types general / caution / warning) | T4 |
| 3.14 | Guest billing panel: view and edit the guest's contact and ID from the booking (M09 service, same logging) | T4 |
| 3.15 | Guest notes shown on the booking (read, add) | T4 |

## API

| Method | Route | Access key |
|---|---|---|
| GET | `bookings/{id}` | `page.bookings` |
| POST | `bookings/{id}/approve` · `/decline` · `/cancel` (`line_id` optional) | `bookings.approve` / `.decline` / `.cancel` |
| POST | `booking-lines/{id}/check-in` · `/check-out` · `/no-show` | `bookings.check_in` / `.check_out` / `.check_in` |
| POST | `bookings/{id}/lines` | `bookings.line_add` |
| PUT/DELETE | `booking-lines/{id}` | `bookings.line_edit` / `.line_remove` |
| notes | `notes?type=booking&id=` | `bookings.note_*` |

Every transition endpoint returns the updated booking, so the UI never needs a second fetch.

## Activity log actions

`bookings.approve|decline|cancel|check_in|check_out|no_show` (line or booking, before/after status),
`bookings.line_add|line_edit|line_remove` (window, room and price before/after),
`bookings.note_add|note_edit|note_remove`. Each fires `rtbp_booking_status_changed` /
`rtbp_booking_changed`.

## Emails

`GuestBookingApproved`, `GuestBookingDeclined` (with the reason), `GuestBookingCancelled`.
Each can be switched on or off in Settings → E-mail.

## Tasks

- [ ] T1 [Free] `BookingQuery` (one-shot loading of the booking + lines + guest + rooms) + the detail screen layout (desktop two-column, mobile tabs) + money summary
- [ ] T2 [Free] `StatusMachine` (a table of legal moves, illegal-move tests) + approve / decline / cancel / check-in (room change) / check-out (balance warning) / no-show + e-mails
- [ ] T3 [Free] Add, edit and remove lines through the engine's locked write path; the price-freeze rule; the invoice-revision hook
- [ ] T4 [Free] Notes panel, guest billing panel, guest notes on the booking, `ActivityTimeline`

## Acceptance

1. A two-room booking: check in one room. The booking shows *Checked in*, and the other line stays *Confirmed*.
2. Change the rate plan's price in M07. The existing booking's price does not move.
3. Edit a line's window onto a room that is taken. It is refused with the conflicting booking's reference.
4. Cancel a booking. The room shows free in the availability grid immediately, and the guest gets
   the e-mail in their language.
5. Try `check_in` on a *pending* line through the API. It is refused with `illegal_transition`.

## Legacy reference

`legacy-reference.md` → Module 3.

## Migration notes

Legacy booking row statuses map as follows: `pending`→pending, `confirmed`→confirmed,
`checked_in`, `checked_out` unchanged, `cancelled`→cancelled, `trashed` → not imported. Order notes
`hbfwc_order_notes` → `notes` (type general).

## Progress notes
