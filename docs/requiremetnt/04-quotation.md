# 04 — Quotation

**Project:** Food Menu Hotel Booking
**Client:** ResidenceTata · **Prepared by:** RadiusTheme
**Date:** 9 September 2026 · **Valid for:** 30 days · **Currency:** USD
**Version:** 4.0 — *supersedes v3.0 ($4,900), v2.0 ($6,900) and v1.0 ($23,600)*

---

## 1. What changed, and why

| Version | Scope | Price |
|---|---|---|
| v1.0 | Complete property management system | $23,600 |
| v2.0 | Minimal booking system | $6,900 |
| v3.0 | Room booking + reports, paid through WooCommerce | $4,900 |
| **v4.0 — this** | **Room booking + reports + its own payment gateways** | **$5,900** |

v4.0 keeps v3.0's scope and adds one thing: **the system takes its own payments.**

In v3.0, WooCommerce was the payment rail — we bridged to gateways the site already had, which cost
almost nothing to build. Replacing that with **Wave**, **Orange Money** and **cash** means building
a payment layer from scratch: a gateway framework with its own settings, a transaction record, two
real integrations against provider APIs, signed callbacks, duplicate protection, and automatic
reconciliation when a notification goes missing.

That is **+38 hours net** — 46 hours of new work, less 8 hours saved by deleting the WooCommerce
bridge — and it is the entire difference between $4,900 and $5,900. Nothing else was widened.

**What has not been reduced is correctness.** The availability engine, the booking data model, the
payment layer and the migration are human-reviewed line by line. Two gates run on every release:
two guests cannot take the same room, and the same payment notification cannot credit a booking
twice. A silent error in either costs the client real money.

### 1.1 Three screens are the specification

The client's live system was the reference for this version, and where it disagrees with an earlier
document, the screens win:

- **The reservation form** — dates → priced schedules per package → room number by floor → guest.
  Unavailable plans and rooms are shown greyed with a reason, not hidden. Rebuilt identically for
  guests on the public site.
- **The Price & Rates tab** — eleven named schedules, each given its own regular price, sale price,
  minimum stay and on/off switch **per package**.
- **The Rooms screen** — room numbers managed per package, grouped under named floors.

---

## 2. Effort

Hours are billable effort. The 7.5-week calendar allows for review cycles and client feedback.

| Stage | Work | Hours |
|---|---|---:|
| 1 | Data audit, foundation, **availability engine**, rooms & floors, schedule library, Price & Rates grid, rate calendar, dashboard integration | 64 |
| 2 | Reservation form, walk-in, front desk, check-in/out, balances, guest records | 64 |
| 3 | Public booking page, emails, French/English | 34 |
| 4 | **Payment framework + Wave + Orange Money + Cash**, callbacks, reconciliation | 38 |
| 5 | Reports — occupancy, revenue, per package, per payment method, CSV | 22 |
| 6 | Data setup & migration, UAT, training, cutover, monitored week | 20 |
| | **Development subtotal** | **242** |
| — | Project management, QA, documentation (12%) | 29 |
| | **Total** | **271** |

**Blended rate: $25 / hour.**

---

## 3. Price

| | |
|---|---:|
| Effort | 271 hours |
| List value (271 × $25) | $6,775 |
| **Package price** | **$5,900** |

**What is included**

| | |
|---|---|
| Packages ("packages") with individual rooms, floors and states | ✅ |
| Rooms screen grouped by floor, with a range generator | ✅ |
| Schedule library — fixed-block, overnight and nightly windows | ✅ |
| Price & Rates grid per package — regular price, sale price, minimum stay, on/off | ✅ |
| Rate calendar with per-date pricing and open/close | ✅ |
| Race-free availability engine with reservation holds | ✅ |
| **Availability resolved per schedule**, with unavailable options shown and explained | ✅ |
| Real booking records — walk-in, modify, cancel, no-show, multi-room | ✅ |
| Front desk — arrivals, departures, in-house, one-click check-in/out, room assignment | ✅ |
| Public booking page, identical flow to the staff form | ✅ |
| **Wave** payment gateway | ✅ |
| **Orange Money** payment gateway | ✅ |
| **Cash**, and "pay at hotel" | ✅ |
| Gateway settings screen, one panel per provider | ✅ |
| Total / paid / balance per booking, with a check-out warning on money owed | ✅ |
| Guest records with name / phone / email lookup and stay history | ✅ |
| Confirmation, staff alert, payment received and cancellation emails, French and English | ✅ |
| Reports — occupancy, revenue, per package, **per payment method**, CSV export | ✅ |
| Staff permissions reusing the existing Food Menu logins | ✅ |
| Hotel screens inside the existing Food Menu dashboard — one sidebar, one login | ✅ |
| Migration of rooms, floors, schedules, guests and upcoming bookings, then go-live | ✅ |

---

## 4. Reducing it further

Two scope levers come off the package price first; **resale rights are then 25% off whatever the
package costs.** Applied in that order, so the figures below match `05-client-message.md` exactly.

| Lever | Effect on the package |
|---|---|
| Orange Money deferred to a later add-on ($500 then) | $5,900 → **$5,400.** Launch with Wave and cash; add Orange Money once its merchant account is in place. Worth considering on its own, since the credentials are the one thing that can delay the project. |
| Client supplies French translation | −$300. We ship the translatable framework; the client's team fills the French strings. |
| **Resale rights** | **−25% of the resulting figure.** We may sell the generic hotel booking core as a Food Menu addon. Everything specific to the client — the eleven schedule windows, phone recognition, French interface — stays exclusively theirs, and they receive all future product improvements free, for life. Costs the client nothing operationally. **This is the lever we would put forward first.** |

| Combination | Price |
|---|---:|
| Full package | $5,900 |
| Full package **with resale rights** | **$4,425** |
| Wave and cash only | $5,400 |
| Wave and cash only, with resale rights | $4,050 |
| Wave and cash only, client translates, with resale rights | **$3,825** |

$3,825 is the floor. Below that the package stops being deliverable. What remains is the availability engine, the booking
model, the front desk, one working gateway and the migration — there is nothing left to remove that
does not break one of them.

---

## 5. Optional add-ons

Priced so the client can add them later at a known cost. **None require rework** — the data model is
the full one, so each attaches cleanly.

| Add-on | Price | What it gives |
|---|---:|---|
| **Food & beverage charged to the room** | $1,200 | Restaurant and room-service orders charge to the guest's room and settle at check-out. **No other hotel plugin can do this**, because none of them also run the restaurant. |
| Advanced pricing rules | $1,100 | Seasonal pricing, occupancy-based pricing, early-bird and last-minute discounts |
| Channel manager (iCal) | $900 | Two-way calendar sync with Booking.com, Airbnb and similar |
| Housekeeping board | $700 | Clean / dirty / inspected states, auto-updated at check-out, cleaning tasks assigned to staff |
| Deposits & security bonds | $700 | Part-payment at booking, refundable security deposit |
| WhatsApp / SMS notifications | $600 | The same messages by WhatsApp or SMS |
| Advanced reports | $600 | Average daily rate, revenue per available room, booking sources, hourly analytics |
| Full historical migration | $500 | Every past booking imported and reconciled, not just upcoming ones |
| Guest self-service portal | $500 | Guests view, modify or cancel their own booking |
| **Each additional payment gateway** | $450 | Moov Money, MTN, a card acquirer — the framework is built to take a fourth provider without touching the first three |
| Activity log | $400 | A record of who performed each refund, discount and cancellation |
| Refunds, manual charges & discount lines | $350 | Issue a refund and adjust a booking from the booking screen |
| Bulk rate editing & extra-guest pricing | $350 | Apply a price across a date range by weekday, extra-adult and extra-child pricing, one-click room move |
| Booking add-ons & promo codes | $300 | Breakfast, transfers, discount codes |
| Printed registration card & confirmation slip | $250 | Proper print layouts |
| Editable email templates | $250 | Edit the wording of every email in the admin |

Any two add-ons taken together: **−10%.**

---

## 6. Payment schedule

| Milestone | % | Amount |
|---|---:|---:|
| Contract signing | 25% | $1,475 |
| Stage 1 accepted — rooms, rates and availability working | 25% | $1,475 |
| Stage 2 accepted — full front-desk day works | 20% | $1,180 |
| Stage 4 accepted — Wave and Orange Money taking payment | 15% | $885 |
| Go-live + clean week | 15% | $885 |
| **Total** | **100%** | **$5,900** |

Invoices due 14 days from issue. Each milestone requires written acceptance, following a working
demo on staging. Acceptance is not unreasonably withheld.

---

## 7. Maintenance & support (optional)

| Plan | Monthly | Includes |
|---|---:|---|
| **Basic** | $150 | Bug fixes, WordPress/PHP compatibility updates, security patches, **gateway API changes**, response within 2 business days |
| **Standard** | $300 | Basic plus up to 4 hours of small changes per month and 1-business-day response |

Payment providers change their APIs without asking. Basic maintenance covers keeping Wave and
Orange Money working; without it, a provider change is billed as an hourly fix.

Cancellable with 30 days' notice. The client owns the code either way.

---

## 8. Not included

| Item | Note |
|---|---|
| Everything in `01-requirements.md` §4 | Available as add-ons — see §5 |
| **Wave and Orange Money merchant accounts, credentials, fees and settlement** | The client's accounts. We integrate; we do not open or operate them. |
| Historical bookings inside the new system | Rooms, floors, schedules, guests and upcoming bookings are migrated. Past bookings stay readable in the old plugin, which is deactivated but never deleted. Full import is a $500 add-on. |
| **Payment history from WooCommerce** | Money already taken through WooCommerce stays in WooCommerce's records; it is not imported. |
| Refunds, deposits, security bonds, manual charge and discount lines | Refunds are issued in the provider's own dashboard |
| Card payments and additional gateways | $450 each — see §5 |
| Payroll / HR, Employees, My Payslips, My Profile | Stays on the existing plugin, untouched |
| Multi-property, multi-currency | Single property, single currency (XOF) |
| Hosting, domain, SSL | Client's infrastructure |
| Content — room descriptions, photography, policy text | Client supplies; we supply the fields |
| Professional certified translation | We ship a translatable system with a working French/English framework |
| Direct OTA API channel manager | Requires per-channel certification; quoted separately if ever wanted |
| Door-lock hardware, accounting sync, native mobile app | Separate |

---

## 9. Assumptions

If any proves wrong, we say so in writing with the cost impact **before** doing the work.

1. **Scope is exactly `01-requirements.md` §3.** This price has no absorption margin whatsoever.
   Requests outside it are quoted as add-ons, not absorbed.
2. **The client obtains Wave and Orange Money merchant accounts, API credentials and sandbox access,
   and provides them at contract signing.** In Côte d'Ivoire this can take weeks. Stage 4 cannot be
   completed without them; if they are late the stage is re-sequenced and the **timeline** moves,
   not the price.
3. **Both providers expose a hosted-checkout or web-payment API with a server-side verification
   endpoint and a notification callback.** This is how both work today. A provider requiring a
   different integration model is a change request.
4. **WooCommerce plays no part in hotel payments.** It stays installed for the restaurant side; this
   plugin neither creates nor reads orders.
5. **Payroll stays on the existing plugin permanently.** Out of scope; we do not modify it.
6. **One property, one currency (XOF), zero decimal places.**
7. **Rooms, floors, schedules, guests and upcoming bookings are migratable.** Stage 1 confirms this.
   Past history is out of scope by design.
8. **A working copy of the site is already in place**, so no further access is needed to begin. The
   client provides about two hours with front-desk staff in week 1, and confirmation of the package,
   room, floor and schedule lists.
9. **Responses within 2 working days.** Longer delays extend the timeline, not the price, provided
   the team can be redeployed.

---

## 10. Why this is worth doing at this price

- The client is replacing three plugins totalling ~81,600 lines of PHP with one system that does the
  essential job correctly. Most of that 81,600 exists only to work around rooms-as-products, and
  disappears with a correct data model.
- Roughly 60–80 hours of infrastructure comes free because Food Menu already has it — the dashboard,
  permissions, currency formatting, settings.
- **Taking payment directly removes a layer.** Money goes from the guest's Wave or Orange Money
  wallet to the client's merchant account, recorded against the booking — with no shop order
  invented in between to represent a hotel stay it was never designed for.
- A commercial hotel system (Cloudbeds, Little Hotelier, RoomRaccoon) costs $150–$500 **per month,
  forever**, cannot be customised, rarely supports West African mobile money, and handles part-day
  stays poorly or not at all. At $5,900 the break-even against a $200/month system is **under two
  and a half years** — and the client owns the result.
- Nothing here is a stepping stone. Every removed module attaches to this data model without rework,
  at the fixed prices in §5.

---

## 11. Summary

| | |
|---|---|
| **Package** | Hotel room booking platform + reports + Wave, Orange Money and cash |
| **Price** | **$5,900** — or **$4,425** with resale rights |
| **Timeline** | 7.5 weeks from signing |
| **First payment** | $1,475 |
| **Needed at signing** | Wave and Orange Money merchant credentials |
| **Ongoing** | From $150/month maintenance (optional, and recommended — it covers gateway API changes) |
| **Add-ons later** | Fixed prices in §5, no rework required |
