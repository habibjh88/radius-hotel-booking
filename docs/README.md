# Radius Hotel Booking — project docs

A hotel management plugin for **Residence TATA** (Abidjan) that replaces their WooCommerce-based
hotel stack: time-based room booking (part-day blocks, overnight, 24 hours), a front desk,
manual payment with invoices, staff permissions, an activity log, reports, staff records and
payroll. It has 18 modules, the ones the client signed off in `requiremetnt/client-email-final.txt`.

## Start here

| Read | When |
|---|---|
| [`project/roadmap.md`](project/roadmap.md) | The build order and the **tracker**. What is done and what is next |
| [`project/booking-engine.md`](project/booking-engine.md) | **The core.** How time-based room booking works: rate plans, stay windows, overlap, holds, locking, pricing |
| [`project/architecture.md`](project/architecture.md) | Layers, code layout, request lifecycle, the whole data model, hooks, performance, security |
| [`project/design-system.md`](project/design-system.md) | UI decisions: shell, tokens, status colours, composites, screen patterns |
| [`project/conventions.md`](project/conventions.md) | Coding rules, tests, the Definition of Done, changelog, git |
| [`project/decisions.md`](project/decisions.md) | ADRs and **open questions for the client (D1–D6)** |
| [`project/legacy-reference.md`](project/legacy-reference.md) | How the client's current system does each module: file pointers, data, behaviour to keep, bugs to avoid |
| [`modules/`](modules/) | One spec per module: scope, data, API, screens, settings, access keys, log actions, tasks, acceptance |

`requiremetnt/` holds the proposal pack and the client-approved feature list
(`08-feature-list.md`, 248 features). Documents `01`–`05` there are superseded on scope and payment
(see its README). `08` and the final client e-mail are the baseline.

## Three plugins

**Free** on wordpress.org (this repo) · **Pro** (`../radius-hotel-booking-pro`) · a **Residence TATA
add-on** (`../radius-hotel-booking-residencetata`, holding payroll and the legacy import). Each
module and task is tagged with its tier. See `project/decisions.md` ADR-014 to ADR-016.

## How to build

Use the Claude Code commands (in `.claude/skills/`):

```
/module-status          where are we?
/next-module            start the next module in dependency order (or: /next-module M07)
/next-task              build the next task of the current module (or: /next-task all)
```

Each task ends with lint, build and tests passing, a ticked checkbox in its module doc, and a
changelog line when the change is user-visible. Each module ends with its acceptance script and,
for money, availability, permission and payroll code, a review by the `rtbp-critical-reviewer`
agent.

## Module map

| Client # | Module | Build order |
|---|---|---|
| — | [M00 Foundation and design shell](modules/M00-foundation.md) | 0 |
| 1 | [Front desk dashboard](modules/M01-front-desk-dashboard.md) | 11 |
| 2 | [Taking a booking at the front desk](modules/M02-front-desk-booking.md) | 8 |
| 3 | [The booking record](modules/M03-booking-record.md) | 9 |
| 4 | [Guest booking on the website](modules/M04-public-booking.md) | 12 |
| 5 | [Manual payment, invoice and confirmation](modules/M05-payments-invoices.md) | 10 |
| 6 | [Rooms, floors and inventory](modules/M06-rooms-floors.md) | 4 |
| 7 | [Rate plans and pricing](modules/M07-rate-plans-pricing.md) | 5 |
| 8 | [Availability engine, calendar and rules](modules/M08-availability.md) | 6 |
| 9 | [Guest records](modules/M09-guests.md) | 7 |
| 10 | [Reports](modules/M10-reports.md) | 13 |
| 11 | [Exporting and archiving](modules/M11-export-archive.md) | 14 |
| 12 | [Staff records](modules/M12-staff-records.md) | 15 |
| 13 | [Staff permissions](modules/M13-staff-permissions.md) | 2 |
| 14 | [Activity log](modules/M14-activity-log.md) | 3 |
| 15 | [Payroll and HR](modules/M15-payroll-hr.md) | 17 (needs D1) |
| 16 | [Staff self-service](modules/M16-staff-self-service.md) | 16 |
| 17 | [Settings and system](modules/M17-settings.md) | 1 |
| 18 | [Language, currency, migration and go-live](modules/M18-foundations-migration.md) | 18 |
