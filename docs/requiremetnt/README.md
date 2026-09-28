# Food Menu Hotel Booking — Proposal Package

**Client:** ResidenceTata (Abidjan, Côte d'Ivoire)
**Prepared by:** RadiusTheme
**Version:** 5.0 — 9 September 2026
**Status:** Draft for client review

---

## What this pack is

The client asked us to rebuild his hotel booking system from scratch and to propose our own
approach — he supplied no requirements document and no budget. This pack is that proposal.

## Version history — read this before sending anything

| Version | Scope | Price | Timeline |
|---|---|---|---|
| v1.0 | Complete property management system | $23,600 | 17 weeks |
| v2.0 | Minimal booking system | $6,900 | 8 weeks |
| v3.0 | Room booking + reports, paid through WooCommerce | $4,900 | 6 weeks |
| v4.0 | Room booking + reports + its own payment gateways | $5,900 | 7.5 weeks |
| **v5.0 — current** | **v4.0 + the package / schedule / room-availability model** | **see below** | **see below** |

**⚠ Scope is being re-baselined — 13 Sep 2026.** The client has since asked for three things that
change the shape of this proposal: **payment is manual** (guest gets instructions and an invoice, a
member of staff confirms the money and marks the booking paid — no online gateway), an **activity
log**, and **staff permissions**. He also asked for **parity with everything his current dashboard
does**, done properly and off WooCommerce. `08-feature-list.md` is the new baseline and goes to him
for sign-off first; the plan, timeline and price are rebuilt against whatever he approves.
Documents `01`–`05` predate this and are **superseded on scope, price and payment method** —
do not send them.

**⚠ The price and scope are OPEN.** `06-code-audit.md` §3 found that `04-quotation.md` sells
$2,700 of "add-ons" the client already owns (seasonal pricing, iCal sync, deposits and security
bonds), and that the approval workflow we removed is a live feature. As scoped, v5.0 is a **feature
downgrade**. Nothing goes to the client until that is resolved — either those features come into
scope and the price rises, or the proposal changes shape.

**Never send an older copy of `05-client-message.md`** — earlier versions quote different prices
and promise modules that are not in scope. Superseded drafts are in `archive/v4.0/`.

**Why v4.0 costs more than v3.0.** v3.0 used WooCommerce as the payment rail, which cost almost
nothing to build. The client then asked for **Wave, Orange Money and cash** instead, which means
building a payment layer from scratch — gateway framework, settings, transaction records, two real
provider integrations, signed callbacks, duplicate protection and reconciliation. That is +38 hours
net and is the entire $1,000 difference. Nothing else was widened.

## How to read it

| Doc | Audience | Purpose |
|---|---|---|
| [`01-requirements.md`](01-requirements.md) | Internal + client | What the system must do. 13 modules, MoSCoW priorities, explicit out-of-scope. |
| [`02-architecture.md`](02-architecture.md) | Internal (developers) | Database schema, entity model, availability engine, payment layer, REST surface, plugin layout. **Not for the client.** |
| [`03-development-process.md`](03-development-process.md) | Internal + client | Six stages, milestones, QA gates, cutover plan, risk register. |
| [`04-quotation.md`](04-quotation.md) | Internal → client | Hours per stage, price, add-on menu, payment terms, exclusions. |
| [`05-client-message.md`](05-client-message.md) | **Send to client** | Plain-language message. No file paths, no class names, no jargon. |
| [`06-code-audit.md`](06-code-audit.md) | Internal | **Read before quoting anything.** Every claim in `01`–`05` checked against the client's actual source, with file:line evidence. Several were false; several "add-ons" already exist. |
| [`07-booking-engine-design.md`](07-booking-engine-design.md) | Internal (developers) | **Authoritative** design of packages, schedules, rooms, the availability algorithm, overlap logic, concurrency, API and admin/customer flows. `02` defers to it. |
| [`08-feature-list.md`](08-feature-list.md) | **Send to client** | **Current.** The full feature list for client sign-off — every screen of the live dashboard surveyed and carried over, plus manual payment/invoicing, the activity log and role-based permissions. 248 features across 18 modules. No prices, no timeline. |

**Send the client:** `05-client-message.md`, plus `01-requirements.md` and `04-quotation.md` as
attachments if he asks for detail. `02-architecture.md` stays internal.

---

## The one-paragraph summary

The existing system stores a hotel reservation as a WooCommerce order line item. Everything that is
painful about it — rooms appearing in the shop, stock being faked, three rooms hiding inside one
line, the inability to answer "who arrives tomorrow" — follows from that
single decision. We propose a new plugin, `food-menu-hotel-booking`, that makes a booking a real
record in its own right, takes money directly through **Wave, Orange Money and cash**, and renders
its screens inside the Food Menu front-end dashboard the client already uses. Scope is deliberately
narrow: **sell a room, fill it, get paid for it, report on it.** Price is **$5,900 over 7.5 weeks**,
or $4,425 with resale rights. Payroll, employee records, staff self-service and the activity log are
excluded at the client's request; everything else removed is priced as an add-on that attaches to
the same data model without rework.

---

## The client's three screens are the specification

Where an earlier document disagrees with these, the screens win:

| Screen | What it fixed in our design |
|---|---|
| **Reservation form** | Availability is resolved **per schedule**, and always scoped to the selected **package** — another package's rooms are never evaluated. Unavailable schedules and rooms are **shown greyed with a reason**, never hidden. The public guest page is the same flow. |
| **Price & Rates tab** | Rate plans are a **shared library** (eleven named windows); price, sale price, minimum stay and on/off live on a **per-room-type assignment**. Standard and VIP share the windows at different prices. |
| **Rooms screen** | Rooms are managed per package, grouped under **named, ordered floors** that are shared between packages. A room number encodes neither its floor nor its package, and a room belongs to exactly one package forever. |

---

## Assumptions we made

The client gave us no brief, so we committed to a recommendation on each open question.

| # | Question | Our assumption |
|---|---|---|
| A1 | What happens to the payroll/HR module? | **Out of scope** (client decision). The existing plugin keeps running payroll untouched. Employees, My Payslips, My Profile and the Activity Log are out too. |
| A2 | How do payments work? | **Our own gateway layer — Wave, Orange Money and cash.** WooCommerce plays no part in the hotel payment path; it stays installed for the restaurant side only. Guests pay online or choose "pay at hotel". |
| A3 | What about existing data? | **Packages, floors, rooms, schedules, guests and bookings that have not happened yet.** Past history stays readable in the old plugin, which is deactivated but never deleted. Full history import is a priced add-on. |
| A4 | One-off build or product? | Hybrid — a generic hotel addon plus a thin ResidenceTata layer. We offer a 25% discount in exchange for resale rights. |

---

## The one thing that can delay this project

**Wave and Orange Money merchant credentials.** They are the client's to obtain, they can take weeks
in Côte d'Ivoire, and Stage 4 cannot complete without them. They are requested at contract signing,
not when the stage starts. If they are late, Stage 4 re-sequences after Stage 5 and the timeline
moves — the price does not.

---

## What is deliberately not in this build

Listed here so nobody re-adds one by accident mid-project. Every item is priced in
`04-quotation.md` §5 and attaches to the shipped data model without rework:

Food & beverage charged to the room · housekeeping board · channel manager (iCal) · deposits and
security bonds · refunds, manual charges and discount lines · additional payment gateways (Moov
Money, MTN, cards) · seasonal / occupancy / early-bird pricing rules · promo codes · booking add-ons
· WhatsApp and SMS · guest self-service portal · QR arrival codes · timeline/Gantt view · ADR,
RevPAR, source and hourly analytics · printed registration cards · editable email templates · bulk
rate editing · extra-guest pricing · one-click room move · guest notes and blacklist · multi-property
· multi-currency · full historical migration · WooCommerce payment history · activity log ·
payroll / HR.

---

## Naming

- Plugin folder / slug: `food-menu-hotel-booking`
- PHP namespace: `RT\FoodMenuHotel\`
- Prefix for tables, options, hooks: `fmhb_` / `fmhb/`
- REST namespace: `fmhb/v1`

Deliberately distinct from `fmp_` — the Food Menu plugins already own `fmp_reservation`,
`fmp/reservation/*` and the `fmp/v1` REST namespace for **table** reservations.
