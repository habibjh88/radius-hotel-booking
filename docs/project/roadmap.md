# Roadmap and tracker

The client numbers the modules 1–18 in the order they explain them (`08-feature-list.md`). We
**build** them in dependency order: the platform first, then inventory, then the booking engine,
then everything that reads bookings. **M00** is the foundation work that every module stands on.

Commands:

| Command | Does |
|---|---|
| `/module-status` | Shows this table with task counts, what's next, and what's blocked |
| `/next-module` | Starts the next module (or `/next-module M07` to switch to one) |
| `/next-task` | Builds the next unchecked task of the current module (`/next-task all` for the rest) |

## Three plugins

| Plugin | Folder (siblings) | Ships to |
|---|---|---|
| Free | `../radius-hotel-booking` (this repo) | wordpress.org |
| Pro | `../radius-hotel-booking-pro` (**created 2026-09-28**; feature switches, ADR-017) | customers |
| Client add-on | `../radius-hotel-booking-residencetata` (created in M00 T9) | Residence TATA |

Free is built first in every module. The Pro and Client tasks of a module come after the free
tasks they hook into. See `decisions.md` ADR-014 to ADR-016.

## Phases

```
Phase 0  Platform        M00 ─► M17 ─► M13 ─► M14
Phase 1  Inventory       M06 ─► M07 ─► M08           ← the time-based booking engine
Phase 2  Front desk      M09 ─► M02 ─► M03 ─► M05 ─► M01
Phase 3  Public site     M04
Phase 4  Insight         M10 ─► M11
Phase 5  People          M12 ─► M16 ─► M15 (D1)
Phase 6  Go-live         M18
```

**Milestones the client can see**

| After | Demo |
|---|---|
| Phase 1 | The whole hotel modelled: room types, floors, rooms, rate plans with prices, the availability calendar |
| Phase 2 | Staff run a full day at the desk: walk-in booking, approve, check in, record payment, invoice, check out |
| Phase 3 | A guest books on a phone and receives the invoice and payment instructions |
| Phase 4 | Reports match hand-checked figures; exports and archives work |
| Phase 5 | Staff records, self-service, payroll (if D1 = yes) |
| Phase 6 | Legacy data imported, parallel run, cutover |

## Tracker

**Tier** says which plugin(s) the module's tasks land in (ADR-014). All work happens on `main`
in every repo; the **Branch** column is only a label, and no branch is created for it.

Status values: `todo` · `in-progress` · `paused` · `review` · `done` · `blocked`.
The commands read and update this table. Keep the columns.

| Order | ID | Module | Tier | Depends on | Status | Branch | Started | Finished |
|---|---|---|---|---|---|---|---|---|
| 0 | M00 | [Foundation and design shell](../modules/M00-foundation.md) | Free (+ add-on skeletons) | — | done | module/m00-foundation | 2026-09-28 | 2026-09-28 |
| 1 | M17 | [Settings and system](../modules/M17-settings.md) | Free | M00 | done | module/m17-settings | 2026-09-28 | 2026-09-28 |
| 2 | M13 | [Staff permissions and access control](../modules/M13-staff-permissions.md) | Free + Pro | M17 | done | module/m13-permissions | 2026-09-29 | 2026-09-29 |
| 3 | M14 | [Activity log](../modules/M14-activity-log.md) | Free (emitter) + **Pro** | M13 | done | module/m14-activity-log | 2026-09-29 | 2026-09-29 |
| 4 | M06 | [Rooms, floors and inventory](../modules/M06-rooms-floors.md) | Free | M14 | done | module/m06-rooms-floors | 2026-09-29 | 2026-09-29 |
| 5 | M07 | [Rate plans and pricing](../modules/M07-rate-plans-pricing.md) | Free + Pro | M06 | done | module/m07-rate-plans | 2026-09-29 | 2026-09-29 |
| 6 | M08 | [Availability engine, calendar and rules](../modules/M08-availability.md) | Free + Pro | M07 | done | module/m08-availability | 2026-09-29 | 2026-09-30 |
| 7 | M09 | [Guest records](../modules/M09-guests.md) | Free | M14 | done | module/m09-guests | 2026-09-30 | 2026-09-30 |
| 8 | M02 | [Taking a booking at the front desk](../modules/M02-front-desk-booking.md) | Free | M08, M09 | done | module/m02-front-desk-booking | 2026-09-30 | 2026-09-30 |
| 9 | M03 | [The booking record](../modules/M03-booking-record.md) | Free | M02 | done | module/m03-booking-record | 2026-09-30 | 2026-09-30 |
| 10 | M05 | [Manual payment, invoice and confirmation](../modules/M05-payments-invoices.md) | Free + Pro | M03 | done | module/m05-payments | 2026-09-30 | 2026-10-01 |
| 11 | M01 | [Front desk dashboard](../modules/M01-front-desk-dashboard.md) | Free | M05 | todo | module/m01-dashboard | | |
| 12 | M04 | [Guest booking on the website](../modules/M04-public-booking.md) | Free | M05 | todo | module/m04-public-booking | | |
| 13 | M10 | [Reports](../modules/M10-reports.md) | Free + Pro | M05 | todo | module/m10-reports | | |
| 14 | M11 | [Exporting and archiving data](../modules/M11-export-archive.md) | Free + Pro | M10 | todo | module/m11-export | | |
| 15 | M12 | [Staff records and employment](../modules/M12-staff-records.md) | **Pro** | M13 | done | module/m12-staff | 2026-09-30 | 2026-09-30 |
| 16 | M16 | [Staff self-service](../modules/M16-staff-self-service.md) | **Pro** | M12 | todo | module/m16-self-service | | |
| 17 | M15 | [Payroll and HR](../modules/M15-payroll-hr.md) | **Client** | M12, D1 | todo | module/m15-payroll | | |
| 18 | M18 | [Language, currency, migration and go-live](../modules/M18-foundations-migration.md) | Free + Client | all | todo | module/m18-go-live | | |

## Why this order

- **M17 → M13 → M14 come before any hotel feature.** Every later module adds settings, access
  keys and log entries. Building those three first means each later module plugs in, with
  nothing to retrofit. A log cannot be backdated.
- **M06 → M07 → M08 is the heart of the build.** Rooms, then what they sell (rate plans and
  prices), then when they are free (the availability engine). Nothing that takes a booking can be
  built before the engine exists. See `booking-engine.md`.
- **M09 comes before M02** because the booking form looks guests up.
- **M05 comes before M01**, because the dashboard shows payment state and its row actions include
  *mark paid*.
- **M04 comes after M05.** The public flow ends in payment instructions and an invoice.
- **People (M12, M16, M15) is independent of the hotel side** and can run in parallel with
  Phases 3–4 if a second developer is available.
- **M18** finishes localisation QA, imports legacy data and runs the cutover. The foundations
  part of Module 18 (currency, time zone, translatable strings, mobile, independence from WooCommerce) is built
  in M00 and applied by every module.
