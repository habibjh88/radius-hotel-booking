# M01 — Front desk dashboard

| | |
|---|---|
| **Client module** | 1 — Front desk dashboard |
| **Features** | 1.1–1.12, 17.1, 17.2 (runtime) |
| **Depends on** | M03 (booking list and actions), M05 (payment state, overdue) |
| **Tier** | Free |
| **Engine** | yes (the "available rooms now" counter; read `booking-engine.md` §5) |
| **Critical review** | no |

## Goal

The screen reception lives on. It answers "what is happening right now" with no clicks: the
counters, the booking list with quick tabs and one-click row actions, today's overview, and a
sound alert on every screen the moment a new booking arrives.

## Layout

```
┌ Awaiting approval ┐┌ Arriving today ┐┌ Leaving today ┐┌ Free rooms now ┐┌ Payment overdue ┐
│        3          ││      7         ││      5        ││     12 / 30    ││        2        │
└───────────────────┘└────────────────┘└───────────────┘└────────────────┘└─────────────────┘
[All] [Awaiting 3] [Arriving 7] [Leaving 5] [In house 9] [Overdue 2]   🔍 ref / guest / room
Date: [range ▾] (Arrival | Created)                                      [+ New booking]
┌──────────────────────────────────────────────────────────┐ ┌ Today ───────────────┐
│ Ref · Guest · Type · Rate · Floor · Room · In · Out ·    │ │ 08:30 ● A3 arrives    │
│ Payment · Status · [primary action] [⋯]                  │ │ 10:12 ✓ B2 checked in │
│ …                                                         │ │ 12:00 ● A1 leaves (!) │
└──────────────────────────────────────────────────────────┘ └──────────────────────┘
```

On a phone the counters become a horizontally scrollable strip, the list becomes cards, and
*Today* becomes a tab.

## Scope

| Feature | Summary | Task |
|---|---|---|
| 1.1–1.4 | Counters: awaiting approval, arriving today, leaving today, free rooms right now (+ new: overdue payments, in house). Each counter is a link to its tab | T1 |
| 1.5 | Booking list (per line): ref, guest, room type, rate plan, floor, room, arrival, departure, payment status, stay status | T2 |
| 1.6 | Quick tabs: All · Awaiting approval · Arriving today · Leaving today (+ In house · Overdue) with counts | T2 |
| 1.7, 1.8 | Date range + the arrival / creation mode switch, kept in the URL | T2 |
| 1.9 | Search by reference (leading `#` ignored), guest name or phone, room number | T2 |
| 1.10 | Row actions: only the valid next transitions (approve / decline / cancel / check in / check out / record payment / open), each access-checked | T3 |
| 1.11 | Today's overview: scheduled arrivals and departures merged with actual events from the log, each `todo | done | overdue` | T4 |
| 1.12, 17.1, 17.2 | New-booking alert: a poll every 30 s (ADR-006) mounted in the **shell** (so it works on every screen), a toast with *View*, the configured sound, and the bell count. It respects the setting and browser autoplay rules (the first click unlocks audio) | T5 |

"Today" and "now" use the site time zone through `Dates`. "Arriving today" = lines with
`start_at` inside [today 00:00, tomorrow 00:00) local, **including midnight**, which the legacy
code skipped.

## API

| Method | Route | Access key |
|---|---|---|
| GET | `dashboard/counters` | `page.dashboard` |
| GET | `booking-lines?tab=&from=&to=&mode=&q=&page=` | `page.bookings` |
| GET | `dashboard/today` | `page.dashboard` |
| GET | `notifications/poll?since=` | `page.dashboard` |

The counters run as one SQL statement with conditional `SUM(CASE …)` over indexed columns, cached
for 15 s and invalidated by `rtbp_booking_changed`.

## Tasks

- [ ] T1 [Free] Counters endpoint + KPI strip
- [ ] T2 [Free] Booking-lines list (tabs with counts, date mode, search, pagination, mobile cards)
- [ ] T3 [Free] Row actions wired to the M03/M05 services with optimistic updates
- [ ] T4 [Free] Today's overview timeline
- [ ] T5 [Free] Shell-level new-booking poller + toast + sound + bell

## Acceptance

1. A booking arriving at 00:00 today is counted in *Arriving today*.
2. Place a booking on the public site. Within 30 s every open staff screen chimes and shows the toast.
3. A receptionist with `bookings.approve` locked sees no *Approve* button.
4. Usable one-handed at 360 px.

## Legacy reference

`legacy-reference.md` → Module 1.

## Progress notes
