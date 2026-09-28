---
name: rtbp-legacy-lookup
description: How to look up how the client's current (legacy) hotel system behaves — where its code lives on the Local site, the per-module map, and what not to copy. Use when a module must preserve existing behaviour, field lists, enums, defaults or data shapes, or when writing the data import.
---

# Looking up the legacy system

The client (Residence TATA) runs a WooCommerce-based hotel stack. We rebuild it; we do not port it.

- Code: `/Users/habib/Local Sites/fmp-client-residencetata/app/public/wp-content/plugins/`
  - `hotel-booking-for-woocommerce` — the base booking plugin (rooms as products, rate plans)
  - `hbfwc-pro-addon` — pricing rules, iCal, calendar
  - `hbfwc-residencetata-addon` — the client's front-end hotel dashboard: front desk, rooms,
    customers, reports, availability, payroll, employees, activity log, backups, settings
  - `food-menu-pro` / `tlp-food-menu` — the dashboard shell it currently lives in
- Running site: `http://fmp-client-residencetata.local/hotel-dashboard` (Local by Flywheel;
  must be started in the Local app).
- Map: `docs/project/legacy-reference.md` — per module, the files, storage and behaviours
  worth keeping, plus the bugs not to copy. Start there; open the files it cites.

## Rules

1. **Copy behaviour, not code.** Field lists, enum values, defaults, labels (FR/EN), edge cases —
   yes. Classes, SQL, JSON-in-meta storage, WooCommerce order coupling — no.
2. When the legacy behaviour and `docs/requiremetnt/08-feature-list.md` disagree, the feature
   list wins; note the difference in the module doc's **Progress notes**.
3. Record every legacy storage location a module replaces (option names, meta keys, tables) in
   the module doc's **Migration notes** — M18 builds the importer from those notes.
4. Read-only. Never modify files or data on the legacy site.
