# User acceptance test (UAT) script

Run once, end to end, on **staging** during the parallel run, by the desk with one of us. Each step
says what to do and what must happen. Tick ✓ or write the problem in the discrepancy log
(parallel-run.md §4) and its number here. The manager signs at the end.

Tester: ____________  Date: ____________  Build: free ___ / Pro ___ / add-on ___

## 1. Sign in and the dashboard

| # | Do | Expect | ✓ / # |
|---|---|---|---|
| 1.1 | Open the **Hotel Dashboard** page, sign in with your usual password | the dashboard; no warning about the time zone | |
| 1.2 | Look at *Today at the front desk* | arrivals / departures = today's paper list | |
| 1.3 | Open the account menu (your name, bottom left) → **My profile** | your details; a locked field says *Only a manager can change this* | |
| 1.4 | On a phone: open the dashboard | everything readable, no sideways scrolling, the bottom tab bar | |

## 2. Take a walk-in booking

| # | Do | Expect | ✓ / # |
|---|---|---|---|
| 2.1 | **New booking** → tomorrow, 2 adults | the rates of every room type with prices = the old system | |
| 2.2 | Choose *Half Day* in a Standard Room | a room is offered; *Why this price?* explains the price | |
| 2.3 | Guest: type a phone of an existing guest | the guest is found (no duplicate) | |
| 2.4 | Mark *Paid now* (cash), confirm | the booking `RT-2026-…`, paid, the room taken on the Availability screen | |
| 2.5 | Try to book the same room, same window again | refused: the room is not free | |

## 3. The booking record

| # | Do | Expect | ✓ / # |
|---|---|---|---|
| 3.1 | Open an **imported** booking | note *Imported from the old system: order #…*; same room, window, total, guest as in the old system | |
| 3.2 | Add a note; edit it | saved; shown with your name | |
| 3.3 | **Check in** an arrival of today | status *Checked in*; the room shows occupied | |
| 3.4 | **Check out** a departure | status *Checked out* | |
| 3.5 | An action protected by PIN (e.g. cancel) | the PIN prompt; your usual PIN works | |

## 4. Payments and invoices

| # | Do | Expect | ✓ / # |
|---|---|---|---|
| 4.1 | On an unpaid booking: **Record payment**, Wave, part of the total | balance due reduced; *Partially paid* | |
| 4.2 | Open the invoice | hotel details, CFA amounts, the payments listed | |

## 5. Website booking

| # | Do | Expect | ✓ / # |
|---|---|---|---|
| 5.1 | On a phone, the public booking page: search tomorrow | the same rates as the desk | |
| 5.2 | Book (staging: e-mails are not sent) | the confirmation page with payment instructions; the booking appears in the dashboard (pending if approval is on) | |

## 6. Guests, rooms, reports

| # | Do | Expect | ✓ / # |
|---|---|---|---|
| 6.1 | **Guests** → search a regular guest by phone (any form, with or without +225) | found once; stays listed | |
| 6.2 | **Rooms & floors** → a room under maintenance | it disappears from New booking | |
| 6.3 | **Reports → Sales** for yesterday | = the old system's report for yesterday | |
| 6.4 | **Exports** → bookings of last month (CSV) | opens in a spreadsheet, amounts in CFA | |

## 7. Managers only

| # | Do | Expect | ✓ / # |
|---|---|---|---|
| 7.1 | **Staff** → a member flagged without phone → add it | saved | |
| 7.2 | **Permissions** → a staff member's access | the same as in the old system | |
| 7.3 | **Activity log** | today's actions, with who and when | |
| 7.4 | A past order in WooCommerce | the booking reads *Stay / Rate plan / Guests* | |

Accepted by (manager): ____________  Date: ____________  Signature: ____________
