# M18 — Language, currency, data migration and go-live

| | |
|---|---|
| **Client module** | 18 — Language, currency and foundations |
| **Features** | 18.1–18.9 (18.1–18.7 were built in M00 and are **verified** here; 18.8–18.9 are built here) |
| **Depends on** | all other modules |
| **Tier** | Free (foundations audit, Plugin Check) + **Client** (legacy import, cutover) |
| **Engine** | yes (imported bookings go through the same write path and locks) |
| **Critical review** | yes (the importer) |

## Goal

Prove that the foundations hold across the whole product (every string translatable, CFA
everywhere, Abidjan time, every screen usable on a phone, no WooCommerce dependency). Bring the
client's data across from the legacy system, run both in parallel, and cut over.

## Scope

| Feature | Summary | Task |
|---|---|---|
| 18.1 | Translation completeness (ADR-019): `make-pot` finds every string, no hard-coded English in PHP or JS, and one screen checked translated through Loco Translate. French comes from a translation plugin, not bundled | T1 |
| 18.2 | CFA audit: every amount on screen, in e-mails and on PDFs goes through `Money` (grep for raw `number_format` / `toFixed`) | T1 |
| 18.3 | Time-zone audit: reports crossing midnight, DST-agnostic checks, the site time zone check on activation (warn if it is not `Africa/Abidjan`) | T1 |
| 18.5 | Phone audit: every route at 360 px (a checklist in this doc, ticked per screen) | T1 |
| 18.6 | Independence: the guard test from M00 passes, WooCommerce deactivated on staging, and the plugin still works | T1 |
| 18.8 | **Importer** `wp radius-hotel-booking import:legacy [--dry-run]` plus an admin screen: room types, floors, rooms, rate plans (deduplicated into one library), room-type rates, rate calendar, pricing rules, blocks, iCal feeds, guests, staff + employees + contracts + payroll history (if M15), access settings (PINs hashed), settings, and **future** bookings | T2–T5 |
| 18.9 | Past bookings: stay readable in the old plugins, which are deactivated, never deleted. Optionally import past bookings read-only (`source = legacy`, excluded from inventory writes) if the client asks | T5 |
| — | Parallel run, UAT, training notes (FR + EN), the cutover runbook, and a monitored week | T6 |

## Importer rules

1. **Dry run first.** Report counts per entity plus a discrepancy list. Nothing is written.
2. **Idempotent.** An `import_map` table (`entity`, `legacy_id`, `new_id`, `checksum`) means a
   re-run updates rather than duplicates.
3. **Never touches source data.** Read-only against the legacy tables and meta.
4. **Rate plans are matched** on `type + start_time + end_time` (+ hours for flexible plans).
   Ambiguous ones go to the report for a human decision.
5. **Bookings go through `BookingService`** with an `importing` flag (no e-mails, no hold step,
   but the same overlap lock), so an imported conflict is reported, never silently accepted.
6. **Rooms**: unify the two legacy systems (`hbfwc_restata_rooms` and PRO `_hbfwc_room_numbers`)
   and report any differences.
7. **Guests**: normalise phones and deduplicate. Placeholder e-mails (`{phone}@residencetata.com`)
   are flagged.
8. **Secrets**: legacy plaintext PINs are hashed on import, then the plaintext copies in the
   report are discarded.

Each module's **Migration notes** section is the source map for its entity.

## Tasks

- [ ] T1 [Free] Foundations audit (every string translatable, money, time zone, 360 px checklist, a run with WooCommerce off) + **Plugin Check** clean + the wordpress.org readme (screenshots, FAQ)
- [ ] T2 [Client] Import framework: `import_map`, dry-run report, CLI + screen, source readers
- [ ] T3 [Client] Inventory import: room types, floors, rooms, rate plans (dedupe), rates, calendar, pricing rules (into Pro), blocks, iCal (into Pro)
- [ ] T4 [Client] People import: guests, staff/employees (Pro), access settings (Pro, PINs hashed), payroll data (client)
- [ ] T5 [Client] Future-bookings import through `BookingService`; optional read-only past bookings
- [ ] T6 [Client] Staging parallel run, UAT script, training notes FR/EN, cutover runbook, rollback plan — the cutover runbook checks that protected files are not web-reachable on the client's server (nginx: a deny rule for `uploads/radius-hotel-booking/`, or `RTBP_PROTECTED_DIR` outside the web root; M12 acceptance 3, ADR-009)

## Acceptance

1. A dry run on a copy of production reports counts that match the legacy dashboard, with zero
   unexplained discrepancies.
2. After the import: every future booking is on the right room, at the right window, for the same
   total, and the availability grid for next week matches the legacy grid.
3. Re-running the import changes nothing.
4. One full week of parallel running with no discrepancy and no critical defect.

## Progress notes

- 2026-09-30 (from M05 T4a): the go-live checklist must start the production site with **clean counters** — the `sequences` rows `booking_<year>`, `invoice_<year>` (and `guest`) are advanced by test bookings on any staging copy; invoice numbers must run unbroken from the first real invoice (5.8). Never rewind a counter on a site that has issued real invoices.
