# 03 — Development Process

**Project:** Food Menu Hotel Booking · **Version:** 4.0 — 9 September 2026
*Supersedes v3.0 (6 weeks / 224 h), v2.0 (8 weeks / 298 h) and v1.0 (17 weeks).*

---

## 1. Delivery model

Six stages over **7.5 weeks**, AI-assisted development with human review on everything that touches
money, availability or migrated data.

- **Demo:** every week, on staging, 20 minutes
- **Written update:** every Friday — done / in progress / blocked / decisions needed
- **Client response window:** 2 working days on decisions, or we proceed on the documented default
- **Acceptance:** each stage is accepted in writing before the next begins and before it is invoiced

### 1.1 On AI-assisted development

This price assumes AI-assisted implementation. That is what makes the figure possible, and it is
worth being precise about where it does and does not reduce effort:

| Reduced a lot | Not reduced |
|---|---|
| Boilerplate — schema, CRUD, REST controllers, React screens, forms, tables | Getting the availability logic right |
| Test scaffolding | Concurrency and edge-case verification |
| Documentation and translations | **Payment gateway integration against live sandboxes** |
| Repetitive UI work | Data migration from three inconsistent sources |
| | Client communication, demos, UAT |

**Human review is not optional** on the availability engine, the payment layer and the migration —
those are where a silent error costs the client real money, and they are reviewed line by line
regardless of how the code was produced.

Gateway work in particular resists acceleration: the code is small, but the provider's sandbox
behaviour, credential onboarding and callback delivery are discovered by testing, not by writing.

---

## 2. Stages

| # | Stage | Calendar | Hours |
|---|---|---|---:|
| 1 | Foundation — rooms, floors, schedules, availability | 1.5 weeks | 64 |
| 2 | Bookings & Front Desk | 2 weeks | 64 |
| 3 | Online Booking & Emails | 1 week | 34 |
| 4 | **Payments — framework, Wave, Orange Money, Cash** | 1.5 weeks | 38 |
| 5 | Reports | 0.5 weeks | 22 |
| 6 | Data Setup & Go-Live | 1 week | 20 |
| | **Development subtotal** | **7.5 weeks** | **242** |
| — | Project management, QA, documentation (12%) | | 29 |
| | **Total** | | **271** |

---

### Stage 1 — Foundation · 1.5 weeks · 64 h

- Data audit of the live site: rooms, floors, schedules, bookings, guests
- **Confirm how abandoned reservations behave today.** The current system marks a room occupied as
  soon as a reservation exists, including a pending unpaid one — verified by the client. The open
  question is whether such a reservation ever releases the room, or whether an abandoned booking
  takes a room out of inventory permanently. If it does, the migration must not carry those rows
  across as live occupancy
- Confirm the eleven schedule windows and their prices per package
- Plugin scaffold, database schema, versioned installer — **build order in `07-booking-engine-design.md` §13**
- **Availability engine** — interval-overlap query, holds, blocks, room states
- **Package-scoped inventory** — a package's rooms are never evaluated for another package
- **Per-schedule availability** evaluated from one fetch, not one query per schedule
- **`overlaps()` unit-tested against all 17 edge cases** (`07` §6.3) *before* any query is written
- Packages, and the ordered floor list in settings
- **Rooms screen** — rows grouped under floor headings, number + state + delete, room count
- **Room generator** — floor + number range creates the rooms in one action
- **Schedule library** and the **Price & Rates grid** per package — regular price, sale price,
  minimum stay, on/off toggle, drag to reorder
- Rate calendar grid
- Dashboard integration (one shell) and permission wiring

**Deliverable:** the whole hotel can be modelled and priced exactly as it is today.
**Gate:** automated concurrency test — two simultaneous requests for the last room, exactly one
wins.

---

### Stage 2 — Bookings & Front Desk · 2 weeks · 64 h

- Booking records with separate stay and payment status
- **The reservation form** — dates → schedule cards per package → room grid by floor → guest →
  create, with unavailable plans and rooms shown greyed and labelled
- Stay times derived from the chosen plan, including windows that cross midnight
- Walk-in booking, returning-guest lookup by name / phone / email
- Modify, cancel with reason, no-show, multi-room
- Today screen — arrivals, departures, in-house, rooms free
- Check-in and check-out with room assignment
- Booking search by reference, name, phone or room
- Total / paid / balance; record a desk payment; check-out warns on a non-zero balance
- Guest records with phone normalisation and stay history

**Deliverable:** staff run a complete day at the desk without leaving the dashboard.
**Gate:** client staff complete a scripted full-day scenario on staging unaided.

---

### Stage 3 — Online Booking & Emails · 1 week · 34 h

- The **same booking component** mounted publicly as a shortcode and a block
- Guest-side schedule cards, floor-grouped room grid, guest details, ID fields
- Choice of paying online or paying at the hotel
- Confirmation, staff alert, payment received and cancellation emails
- French and English throughout

**Deliverable:** a guest completes a booking end to end and receives a confirmation.
**Gate:** ten test bookings placed by client staff from outside the office, all correct.

---

### Stage 4 — Payments · 1.5 weeks · 38 h

- Gateway framework — interface, registry, payment records, `txn_ref`, status lifecycle
- Settings tab with a panel per gateway, including the callback URL rendered for copying
- **Wave** — checkout session, redirect, signed webhook, server-side verification
- **Orange Money** — token auth, web-payment request, redirect, notification callback, verification
- **Cash** — recorded at the desk
- Idempotent callbacks; pending-payment reconciliation cron
- Hold extended across the redirect so a paying guest cannot lose the room
- Sandbox testing of both providers, including the abandoned-browser path

**Deliverable:** a guest pays by Wave and by Orange Money on staging and the booking settles itself.
**Gate:** the same provider notification delivered twice credits the booking exactly once; a guest
who closes the browser after paying still ends up paid.

> **This stage is blocked without merchant credentials.** Wave and Orange Money accounts, API keys
> and sandbox access are the client's to obtain and can take weeks in Côte d'Ivoire. They are
> requested at contract signing, not when the stage begins. If they are not in hand by the start of
> Stage 4, the stage is re-sequenced after Stage 5 and the timeline moves, not the price.

---

### Stage 5 — Reports · 0.5 weeks · 22 h

One page, one date-range filter, one aggregate pass:

- Occupancy by date range
- Revenue by date range, from stored booking records
- Revenue and nights sold per package
- **Revenue by payment method** — Wave vs Orange Money vs cash
- Bookings CSV export
- Cancelled bookings excluded from revenue

**Deliverable:** occupancy, revenue and revenue-by-method match hand-checked control figures.

---

### Stage 6 — Data Setup & Go-Live · 1 week · 20 h

- Migration tool with dry-run and a discrepancy report — **rooms, floors, schedules, guests and
  future bookings only**
- Rate-plan deduplication reviewed by a human, not merged silently
- Dry-run against a copy of production; reconcile counts
- **Parallel run** — both systems live, staff working in the new one
- UAT, defect fixes
- One training session, recorded, plus written notes in French and English
- Cutover, then a monitored week
- Old plugins deactivated — **not deleted** — so past bookings stay readable

**Deliverable:** the new system is authoritative and the old plugins are off.
**Gate:** one full week with no data discrepancy and no critical defect.

---

## 3. Timeline

```
Week      1         2         3         4         5         6         7      7.5
        ┌──────────────┐
S1      │██████████████│  Foundation
        └──────────────┘
                       ┌─────────────────────┐
S2                     │█████████████████████│  Bookings & Front Desk
                       └─────────────────────┘
                                             ┌──────────┐
S3                                           │██████████│  Online Booking
                                             └──────────┘
                                                        ┌───────────────┐
S4                                                      │███████████████│  Payments
                                                        └───────────────┘
                                                                        ┌────┐
S5                                                                      │████│  Reports
                                                                        └────┘
                                                                            ┌──────────┐
S6                                                                          │██████████│  Go-Live
                                                                            └──────────┘
```

**Total: 7.5 weeks.**

---

## 4. Engineering standards

Inherited from the Food Menu codebase, **unchanged by the budget**.

| Area | Standard |
|---|---|
| PHP | WordPress-Core / Docs / Extra, enforced by PHPCS |
| JavaScript | `@wordpress/eslint-plugin` + Prettier |
| Performance | No N+1. Batch-load before loops. Rate-plan availability is four queries, never one per plan. |
| Backward compatibility | Never rename or drop a stored key, column, hook or route once shipped |
| Secrets | Gateway credentials never localised to the browser, never logged, stripped from stored payloads |
| Changelog | Every user-visible change logged in `readme.txt` under `[UNRELEASED]`, same task as the change |
| i18n | Every string translatable; French first-class |
| Migrations | Versioned with `version_compare`, idempotent, never destructive |

---

## 5. Quality gates

No stage is accepted until all pass:

1. PHPCS and ESLint clean
2. Unit tests green — availability edge cases (back-to-back windows, midnight-crossing blocks, DST,
   leap day), price resolution, `number_sort`
3. **Concurrency test green** — N parallel requests for the last room, exactly one wins. The row
   lock (`07` §8 layer 2) is the guarantee; MySQL cannot express a range exclusion constraint, so
   nothing else covers this
4. **Idempotency test green** (from Stage 4) — a duplicate provider notification credits once
5. Manual test script executed
6. Client demo delivered and accepted in writing

Gates 3 and 4 are **not negotiable at any budget.** Double-booking costs the client a guest;
double-crediting costs them money and trust.

---

## 6. Cutover plan

**Before**
1. Full database and file backup, restore tested
2. Migration dry-run on a production copy; reconciliation signed off
3. **Gateways switched from test to live credentials and re-verified with one real low-value
   transaction each**
4. Low-occupancy night agreed as the window

**During**
5. Maintenance mode → migration run → verified against the reconciliation report
6. Smoke test: create a booking, take a live payment, check in, check out
7. Old plugins deactivated but **not deleted** → site live

**After**
8. One monitored week, daily reconciliation against the old data and against each provider's own
   transaction list
9. Old plugins stay installed indefinitely — they are the client's archive of past bookings

**Rollback:** any data discrepancy or critical defect within 24 hours → reactivate the old plugins
and restore. Because the old plugins are only deactivated and the migration never writes to source
data, rollback takes minutes. Payments already taken through the new gateways are unaffected —
they live with the provider, not with us.

---

## 7. Risk register

| # | Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|---|
| R1 | **Scope creep** — requests for features in the removed list during the build | **High** | **High** | The in-scope list is agreed in writing before work starts. There is **no absorption margin**: anything outside §3 of the requirements is quoted as an add-on, never absorbed. |
| R2 | **Merchant credentials not ready when Stage 4 starts** | **High** | **High** | Requested at contract signing. Stage 4 re-sequences after Stage 5 if needed — timeline moves, price does not. Cash and pay-at-hotel still work, so go-live is not blocked outright. |
| R3 | A provider's sandbox behaves differently from live | Medium | High | One real low-value transaction per gateway at cutover, before the site is live |
| R4 | A callback is never delivered, leaving a guest who paid looking unpaid | Medium | **High** | Reconciliation cron re-verifies pending payments; the return URL is a second path; both converge on the same server-side `verify()` |
| R5 | The same notification is delivered twice and credits the booking twice | Medium | **High** | Enforced at the database by a unique key on (gateway, provider transaction id), not by application logic |
| R6 | Double-booking in production | Low | **High** | Holds table + transaction + automated concurrency test in every release gate. Hold TTL covers the gateway redirect. |
| R7 | Eleven schedules do not deduplicate cleanly into one library at migration | Medium | Medium | Matched on schedule type + start + end time; anything ambiguous is reported in the dry-run for a human decision, never merged silently |
| R8 | Rate-plan availability is implemented as one query per plan | Medium | Medium | Called out explicitly in the architecture and checked at code review; 22 queries per page load on this client's data |
| R9 | AI-generated code contains a subtle error in money or availability logic | Medium | **High** | Availability, payments and migration are human-reviewed line by line and covered by unit tests. Non-negotiable. |
| R10 | Slow client feedback stalls a stage | Medium | Medium | 2-working-day response window; we proceed on the documented default and flag it |
| R11 | A sensitive action is disputed and cannot be attributed, the audit log being out of scope | Medium | Medium | Per-action permissions still restrict who can act; booking-level attribution retained. Flagged in writing at sign-off. |
| R12 | The client wants past booking history inside the new system after go-live | Medium | Medium | Stated plainly before signing: past bookings stay in the old plugin, which is never deleted. Full import is a priced add-on. |

---

## 8. Change control

Anything outside the agreed scope is a change request: written description, price, schedule impact,
client approval before work starts. Small clarifications inside an agreed requirement are absorbed
at no cost.

At this budget this is enforced strictly — not to be difficult, but because the price has no
absorption margin in it.

---

## 9. Handover

- Full source code in the client's repository
- Administrator notes — French and English — including gateway setup and credential rotation
- One recorded training session
- 30 days of post-launch support included
- Optional maintenance proposal for what follows
