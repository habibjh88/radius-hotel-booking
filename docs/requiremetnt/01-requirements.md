# 01 — Requirements Specification

**Project:** Food Menu Hotel Booking (`food-menu-hotel-booking`)
**Client:** ResidenceTata
**Version:** 4.0 — 9 September 2026 · *supersedes v3.0, v2.0 and v1.0*

---

## 1. What this version is

| Version | Scope | Price |
|---|---|---|
| v1.0 | Complete property management system | $23,600 |
| v2.0 | Minimal booking system | $6,900 |
| v3.0 | Room booking + reports, paid through WooCommerce | $4,900 |
| **v4.0 — this** | **Room booking + reports + its own payment gateways** | **$5,900** |

v4.0 keeps v3.0's narrow scope and changes one thing: **WooCommerce is no longer the payment
rail.** The system gets its own payment layer with three methods the client actually uses —
**Wave**, **Orange Money** and **cash**. That is the only addition; nothing else was widened.

Three screens from the client's live system are the reference for this specification, and where
this document and those screens disagree, the screens win:

- **The reservation form** — dates → priced schedules per package → room number by floor →
  guest → create. Rebuilt identically for guests on the public site.
- **The Price & Rates tab** — a library of named schedules, each assigned a regular price, sale
  price, minimum stay and on/off switch **per package**.
- **The Rooms screen** — room numbers entered and managed per package, grouped under named floors.

**The guiding rule for what stays and what goes:**

> Anything required to **sell a room, occupy it, get paid for it and report on it** stays.
> Everything else is a priced add-on.

---

## 2. Background — why rebuild at all

The hotel runs on three stacked plugins totalling ~81,600 lines of PHP. The root defect is that
**a reservation is stored as a WooCommerce order line item**, with stay details packed into JSON
in line-item meta. Five consequences matter:

1. **A reservation has no identity.** Arrival and departure dates sit inside a JSON blob. "Who
   arrives tomorrow?" cannot be answered without scanning every order on the site.
2. **WooCommerce stock had to be switched off and re-implemented** privately, because a room is
   not stock. Any plugin that reasons about stock is wrong about rooms.
3. **Quantity is not rooms.** One line item with quantity 3 is three separate stays hidden in one
   row. Cart quantity editing had to be disabled outright.
4. **Rooms pollute the catalogue.** A setting plus a query hack exist to hide them from the shop,
   and they do not hide them everywhere.
5. **Prices were never snapshotted**, so reports re-derived revenue from current prices.

A sixth follows from the payment decision in this version: **a shop checkout is the wrong shape
for a hotel payment.** It assumes a cart, a customer account and a single order state, when what is
actually needed is "take 15,000 CFA against booking RT-2026-000841, by Wave, and tell me whether it
cleared."

> **What the current system gets right, and what we must not claim otherwise.** Room-level
> availability works: a room attached to an existing reservation — *including a pending, unpaid
> one* — is shown as occupied and cannot be selected again. This was verified by the client against
> the live system. Earlier drafts of this document asserted that two guests could book the same room;
> **that claim is withdrawn.** Inventory holds remain in scope (§3.4) as an improvement for the
> window before a reservation record exists, and because mobile-money payment now takes the guest
> off-site mid-booking — not as a fix for a defect.

All are fixed by making a booking a real record with one row per physical room, and money a record
against that booking. That is the core of this project and is **not reducible at any budget**.

### 2.1 What the hotel actually does

- **Part-day and overnight stays priced as fixed blocks** — the live system carries **eleven** named
  schedules (*De 8H30 à 17H — Demi-Journée*, *De Midi à 20H*, *De 20H à 8H — Nuitée*,
  *De 20H à Midi*, *De Minuit à 8H*, *8H30 à 8H — Journée 24H*, *Journée Flexible 24H*, …), each a
  window with a start and end time and a flat price. **This is the client's core business model.**
- **Packages with exclusive room inventory.** Each package owns a fixed set of physical rooms, and
  a room never appears under a second package — even when two packages sell the identical time
  window. Floors are shared between packages; rooms are not.
- **Thirty rooms across five floors** in the live system today, numbered `A1`–`A40`
  non-contiguously, with more packages planned.
- **Walk-in booking at the front desk**, and the same flow online for guests.
- Returning guests recognised by name, phone or email.
- **Payment by Wave, Orange Money or cash.**
- French interface, XOF / CFA currency.

---

## 3. In scope

Priority key: **M** = must have (go-live blocker) · **S** = should have (in scope, can slip).

### Module 1 — Packages, floors & rooms

> **Terminology.** A **package** is a sellable room category (*Package 1*, *Standard Room*, *VIP
> Room*). Hotel software normally calls this a package; we use the client's word throughout.
> Full design in `07-booking-engine-design.md`.

| # | Requirement | Pri |
|---|---|---|
| 1.1 | Packages — name, description, photos, occupancy limits | M |
| 1.2 | Floors as an ordered, named list (*Ground floor, Floor #1…*), **shared across packages** | M |
| 1.3 | Physical rooms — number, floor, state (*available, occupied, maintenance, out of order*) | M |
| 1.4 | **A room belongs to exactly one package and can never appear under another** | M |
| 1.5 | **Room numbers are unique across the whole property**, so two packages cannot both hold a room called `B1` | M |
| 1.6 | Rooms screen per package — rows grouped under floor headings, with the room count | M |
| 1.7 | Room generator — floor + number range creates the rooms in one action | M |
| 1.8 | Rooms sorted naturally within a floor (`A9` before `A12`) | M |
| 1.9 | Move a room to another package, with a warning when it has future bookings; past bookings keep the package they were sold under | M |
| 1.10 | A package holding rooms cannot be deleted until they are moved or removed | M |
| 1.11 | Admin view of which package owns a given room | S |
| 1.12 | Amenities as a free-text list on the package | S |

> **A room number does not encode its floor or its package.** Floor 2 holds `B1`–`B6` (Package 1)
> *and* `B7`–`B10` (Package 2). Floors are shared; inventory is not.

### Module 2 — Schedules & pricing

| # | Requirement | Pri |
|---|---|---|
| 2.1 | **Schedule library** — named time windows reusable across packages (*8 AM – 4 PM*, *De 20H à 8H — Nuitée*, *Full Day*) | M |
| 2.2 | **Fixed-block** schedules — a flat price for a window, including windows crossing midnight | M |
| 2.3 | **Nightly** schedules — priced per night | M |
| 2.4 | **Two packages may use the same schedule; this must not share their rooms** | M |
| 2.5 | **Price & Rates grid per package** — assign any schedule with its own **regular price, sale price, minimum stay** and **on/off toggle** | M |
| 2.6 | Schedules reorderable by drag within a package | M |
| 2.7 | Schedule calendar — per-date price override for a package + schedule | M |
| 2.8 | Open / close a date for sale | M |
| 2.9 | Sale price applies only when set and lower than the regular price | M |
| 2.10 | A schedule in use by a package cannot be deleted | M |

### Module 3 — Availability

| # | Requirement | Pri |
|---|---|---|
| 3.1 | Availability answered by an interval-overlap query — correct for fixed-block, overnight and nightly stays alike | M |
| 3.2 | Availability is per **physical room**, so a specific unit can never be double-booked | M |
| 3.3 | **Availability is always computed from the selected package's own inventory** — another package's rooms are never evaluated, never returned, never displayed | M |
| 3.4 | **Availability is resolved per schedule**, because each is a different window — one schedule can be full while another on the same package is free | M |
| 3.5 | **"No rooms available" is a designed state**, offering the schedules and dates that do have rooms | M |
| 3.6 | **Inventory holds** with expiry, long enough to survive a payment redirect | M |
| 3.7 | **A row lock at write time**, so two simultaneous requests for the last room resolve with exactly one winner | M |
| 3.8 | Manual blocks — take a room out of inventory for a range, with a reason | M |
| 3.9 | Availability respects room state (*maintenance* and *out of order* are not sellable; *occupied* is) | M |
| 3.10 | Month calendar view — rooms free and price per package per day | M |

### Module 4 — Bookings

| # | Requirement | Pri |
|---|---|---|
| 4.1 | A booking is a real record with its own reference number and history | M |
| 4.2 | Separate stay status and payment status — not one combined field | M |
| 4.3 | **Walk-in booking** created in the dashboard | M |
| 4.4 | **One-screen booking flow** — arrival & departure dates → schedule (which also picks the package) → room number → guest → create | M |
| 4.5 | **Unavailable options are shown, not hidden** — an unavailable schedule is greyed and labelled *(unavailable)*; an unavailable room is greyed with its reason as a badge (*maintenance*, *occupied*, *booked*) | M |
| 4.6 | Stay times **derived from the chosen schedule**, never typed; a window crossing midnight rolls the departure to the next day automatically | M |
| 4.7 | Modify a booking — dates, schedule, occupancy | M |
| 4.8 | Cancel a booking, recording the reason | M |
| 4.9 | Mark no-show | M |
| 4.10 | Multi-room booking in one reservation | M |

> 4.5 is a correction, not a preference. Hiding a room that exists makes staff think it was deleted;
> showing it greyed with *maintenance* on it answers the question before it is asked.

### Module 5 — Online booking (guest-facing)

| # | Requirement | Pri |
|---|---|---|
| 5.1 | **The public booking page is the same flow as the staff reservation form**, in the same order, with the same unavailable-state rendering | M |
| 5.2 | Search by arrival and departure date. Shortcode + block | M |
| 5.3 | Packages shown side by side with their schedules and prices | M |
| 5.4 | Room number selectable by the guest, grouped by floor | M |
| 5.5 | Guest details — name, phone (required), email (optional), number of guests, special requests | M |
| 5.6 | Returning-guest lookup — search by name, phone or email and fill the form from the match | M |
| 5.7 | ID type and ID number captured at booking, **required** | M |
| 5.8 | **Guest chooses to pay online (Wave / Orange Money) or to pay at the hotel** | M |
| 5.9 | Full French and English | M |

### Module 6 — Front desk

| # | Requirement | Pri |
|---|---|---|
| 6.1 | Today screen — arrivals, departures, in-house, rooms free | M |
| 6.2 | Arrivals list with one-click check-in | M |
| 6.3 | Departures list with one-click check-out | M |
| 6.4 | Assign or change the physical room at check-in | M |
| 6.5 | Search bookings by reference, guest name, phone or room | M |
| 6.6 | Returning-guest lookup on the walk-in form — one search fills name, phone, email and ID | M |
| 6.7 | Payment status settable at creation (*on hold, pending, paid*) | M |

### Module 7 — Payments & gateways

| # | Requirement | Pri |
|---|---|---|
| 7.1 | **Own payment layer — WooCommerce is not used for hotel payments** | M |
| 7.2 | **Wave** gateway — hosted checkout, redirect, server-side verification, callback | M |
| 7.3 | **Orange Money** gateway — token-authenticated web payment, redirect, notification callback | M |
| 7.4 | **Cash** — recorded at the desk, no integration | M |
| 7.5 | Separate settings screen with a panel per gateway — enable/disable, display title, live/test mode, credentials, and the callback URL shown for copying into the provider's dashboard | M |
| 7.6 | Every attempt recorded — method, amount, our reference, the provider's transaction id, status | M |
| 7.7 | A payment is only ever confirmed by **server-side verification**, never by the guest returning to a success URL | M |
| 7.8 | Callbacks are idempotent — the same notification arriving twice credits the booking once | M |
| 7.9 | Payments left pending are re-checked automatically against the provider and resolved | M |
| 7.10 | Each booking shows total, paid and balance due | M |
| 7.11 | Record a payment taken at the desk, with method | M |
| 7.12 | Check-out warns if the balance is not zero | M |
| 7.13 | Adding a fourth gateway later must not require touching the first three | M |

> Refunds, deposits, part-payments and security bonds remain out of scope (§4). A refund is issued
> in the provider's own dashboard and the balance corrected on the booking.

### Module 8 — Guests

| # | Requirement | Pri |
|---|---|---|
| 8.1 | Guest record — first name, surname, phone, email, ID type, ID number | M |
| 8.2 | Phone number normalised so `+225 07…` and `07…` are the same guest | M |
| 8.3 | Stay history per guest | M |
| 8.4 | Search guests by name, phone or email | M |

### Module 9 — Reports

| # | Requirement | Pri |
|---|---|---|
| 9.1 | Occupancy rate by date range | M |
| 9.2 | Revenue by date range | M |
| 9.3 | Revenue and nights sold per package | M |
| 9.4 | **Revenue by payment method** — Wave vs Orange Money vs cash | M |
| 9.5 | Bookings list export to CSV | M |
| 9.6 | **All figures from stored booking records**, never re-derived from current prices | M |
| 9.7 | Cancelled bookings excluded from revenue | M |

> All reports live on **one page** with a shared date-range filter, not five separate screens.

### Module 10 — Notifications

| # | Requirement | Pri |
|---|---|---|
| 10.1 | Booking confirmation email to the guest | M |
| 10.2 | New-booking alert email to staff | M |
| 10.3 | Payment received email | M |
| 10.4 | Cancellation email | M |
| 10.5 | French and English | M |

### Module 11 — Staff access

| # | Requirement | Pri |
|---|---|---|
| 11.1 | Hotel screens respect the **existing Food Menu permission system** — no second system built | M |
| 11.2 | Permission to view bookings vs. edit bookings vs. take payments vs. change gateway settings | M |

### Module 12 — Settings

| # | Requirement | Pri |
|---|---|---|
| 12.1 | Hotel details — name, address, phone, logo, currency, timezone | M |
| 12.2 | Default check-in and check-out times | M |
| 12.3 | Tax rate | M |
| 12.4 | Floor list — ordered, named floors used everywhere rooms are shown | M |
| 12.5 | Payment gateway settings (Module 7.5) | M |

### Module 13 — Data setup & migration

| # | Requirement | Pri |
|---|---|---|
| 13.1 | Import packages, floors, physical rooms and guests from the current plugins | M |
| 13.2 | Import the eleven existing schedules and their per-room-type prices | M |
| 13.3 | Import bookings whose **departure date is in the future**, so nothing upcoming is lost | M |
| 13.4 | Dry-run first, with a report of what will import and what cannot | M |
| 13.5 | Never modifies or deletes source data; old plugins stay installed and readable | M |
| 13.6 | Re-runnable without creating duplicates | M |

> **Past bookings are deliberately not imported.** They remain readable in the old plugin, which is
> deactivated but never deleted. Importing full history across three inconsistent sources was the
> largest single cost and the largest single unknown; it is available as an add-on.

---

## 4. Out of scope

Each is priced in `04-quotation.md` §5. **None require rework** of this build.

| Removed | Why it is safe to defer |
|---|---|
| **Food & beverage charged to the room** | The commercially strongest feature, but not needed to book a room correctly. |
| **Refunds, manual charges and discount lines** | Refunds are issued in the Wave / Orange Money dashboard and the balance corrected on the booking. |
| **Deposits, part-payments, security bonds** | Payment is recorded as a simple total/paid/balance. |
| **Card payments and additional gateways** (Moov Money, Visa/Mastercard, MTN) | The gateway layer is built to take a fourth provider without touching the first three. |
| **Channel manager (iCal sync)** | Only matters if the hotel lists on Booking.com / Airbnb. Dates can be blocked manually. |
| **Housekeeping board & cleaning tasks** | Room state is still tracked; the cleaning workflow on top of it is not. |
| **Seasonal, occupancy and early-bird pricing rules** | The rate calendar allows any price on any date, set manually. |
| **Booking add-ons (breakfast, transfers)** | Add them to the room price or take payment separately. |
| **Promo / discount codes** | Use the sale price on a schedule, or the rate calendar. |
| **WhatsApp / SMS notifications** | Email only. |
| **Guest self-service portal** | Guests contact the hotel to change a booking. |
| **QR arrival codes** | Check-in is by name or reference. |
| **Timeline / Gantt calendar view** | Month calendar only. |
| **Group bookings, split folios** | Multi-room bookings in one reservation are still supported. |
| **Advanced reports** — ADR, RevPAR, source breakdown, forecast, hourly analytics | Occupancy, revenue, per package and per payment method only. |
| **Invoice PDF with numbering series** | Not included. |
| **Full historical migration** | Rooms, guests, rates and future bookings are imported; past bookings stay readable in the old plugin. |
| **Bulk rate editing across a date range** | The rate calendar grid allows any price on any date. Bulk *room* creation is in scope — see 1.5. |
| **Extra-adult / extra-child pricing** | Occupancy limits are enforced; price by package or by a separate schedule. |
| **One-click room move** | Change the assigned room by editing the booking. |
| **Printed registration card / confirmation slip** | The booking screen prints from the browser. |
| **Editable email templates in the admin** | Templates ship as translatable text. |
| **Guest internal notes and blacklist** | Special requests are still recorded on the booking. |
| **Multi-property, multi-currency** | One property, XOF only. |
| **Payroll / HR, Employees, My Payslips, My Profile** | Stays on the existing plugin, untouched. |
| **Activity log / audit trail** | See §4.1. |

### 4.1 Note on the removed audit trail

The client's current dashboard has an **Activity Log** menu. It is the only record of **who**
performed a refund, a discount or a cancellation. Without it those actions cannot be attributed to
a person afterwards. Booking-level attribution (who created and last changed each booking) **is**
retained, so the reservation itself is traceable — only the site-wide action log is gone.

Flagged because it is cheap now and impossible to add retroactively: a log cannot be backdated.

---

## 5. Non-functional requirements

| # | Requirement |
|---|---|
| N1 | Availability search returns in under 300 ms for 30 rooms × 11 schedules × 2 packages |
| N2 | No N+1 query patterns; batch-load before loops. Rate-plan availability is evaluated from **one** fetch of the period's bookings, holds and blocks — never one query per plan |
| N3 | All dates stored with explicit timezone; no unix timestamps in session data |
| N4 | Money stored as fixed-precision decimals and formatted through the shared formatter — **XOF has zero decimals and the symbol follows the amount** (`15 000 CFA`) |
| N5 | Every REST endpoint has a capability check **and** a nonce check. Gateway callbacks are public by necessity and are instead authenticated by provider signature |
| N6 | Gateway credentials stored so they are never exposed to the browser and never written to logs |
| N7 | Works on WordPress 5.0+, PHP 7.4+ |
| N8 | Fully translatable; French shipped as first-class |
| N9 | Dashboard usable on a tablet at the front desk |
| N10 | Passes PHPCS and ESLint before release |
| N11 | Database upgrades versioned and idempotent; never destructive |

**None of these were reduced with the budget.**

---

## 6. Success criteria

The project is done when, on the live site:

1. Staff take a walk-in booking, pick a schedule, assign a room from the floor grid, check the
   guest in, record a cash payment and check them out — entirely in the dashboard.
2. A guest books online, pays by **Wave**, and the booking shows as paid without anyone touching it.
3. A guest books online, pays by **Orange Money**, closes the browser before returning to the site,
   and the booking still shows as paid — because the provider's callback settled it.
4. A guest chooses **pay at hotel**; the booking is confirmed with a balance due, and check-out
   warns until it is cleared.
5. "Who arrives tomorrow?" is one screen, loading instantly.
6. Two people booking the last room at the same moment — exactly one succeeds.
7. A schedule that is genuinely unavailable is visible and greyed, not missing.
8. Occupancy, revenue and revenue-by-payment-method for a period match hand-checked control figures.
9. Existing rooms, floors, schedules, guests and upcoming bookings are readable after migration.
10. The three old plugins are deactivated, with past history still readable in them.

---

## 7. The main risks at this budget

**Scope creep.** This package is priced for exactly the list in §3, and there is **no absorption
margin**. Requests during the build for anything in §4, however small they sound, are quoted as
add-ons. The §3 list is agreed in writing before work starts.

**Gateway credentials.** Wave and Orange Money merchant accounts, API credentials and sandbox
access are the client's to obtain, and in Côte d'Ivoire that can take weeks. **Stage 4 cannot be
completed without them.** They are requested at contract signing, not when the stage starts.
