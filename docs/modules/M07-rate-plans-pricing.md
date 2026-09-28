# M07 — Rate plans and pricing

| | |
|---|---|
| **Client module** | 7 — Rate plans and pricing |
| **Features** | 7.1–7.16 |
| **Depends on** | M06 (room types) |
| **Tier** | Free (library, grid, base/sale/override pricing, resolver) + **Pro** (seasonal, occupancy, early-bird, last-minute rules) |
| **Engine** | **yes**. Read `booking-engine.md` §2 and §6 first |
| **Critical review** | yes (window derivation, pricing order) |

## Goal

The hotel's business model as data. A library of named stay windows (rate plans) is defined once
and priced per room type. Seasonal, occupancy, early-bird and last-minute rules sit on top, applied
in one documented order that the screen can explain.

## Scope

| Feature | Summary | Task |
|---|---|---|
| 7.1 | Rate plan library: name (FR/EN), code, active, sort order; the client's plans seeded (confirm the list with the legacy data first) | T1 |
| 7.2 | Fixed-window plans: start and end time (30-minute steps), crossing midnight allowed, `start == end` = 24 h | T1 |
| 7.3 | Flexible plans: duration (hours) + the allowed check-in range | T1 |
| — | `multi_unit` flag + `StayWindow` derivation (pure, table-tested; **shared with M08**) | T1 |
| 7.4 | Features: short FR/EN tags (*Non-refundable*, *8.5 hours stay*, *Flexible check-in*) | T2 |
| 7.5 | Policy text (FR/EN, rich text limited to basic formatting) | T2 |
| 7.10 | "In use": the count of room types and future bookings using the plan; deletion is refused while in use (deactivate instead) | T2 |
| 7.6, 7.7, 7.8, 7.9 | **Price & Rates grid per room type**: rows = plans, columns = on/off · price · sale price · min units · max units · drag to reorder. Inline edit, one Save | T3 |
| 7.11 | Seasonal pricing templates (*increase 20 %*, *decrease 5 000 CFA*, *set 25 000*) | T4 |
| 7.12 | Seasonal rules: template + date range + room types + rate plans + priority | T4 |
| 7.13 | Occupancy rules: threshold % + adjustment + scope | T5 |
| 7.14, 7.15 | Early-bird / last-minute rules: days threshold + discount (percent / fixed) + scope | T5 |
| 7.16 | `PriceResolver` implementing the §6 order + a `PriceBreakdown` popover + a **price simulator** on the grid screen ("Standard × Overnight on 24 Dec, booked today → 18 000 CFA, and why") | T6 |

## Screens

- `#/inventory/rate-plans`: library list + editor Sheet (type switch shows the relevant fields; a
  live preview reads "Check-in 20:00, check-out 08:00 the next day (12 h)").
- `#/inventory/room-types/:id` → **Rates** tab: the Price & Rates grid.
- `#/inventory/pricing`: tabs Seasonal · Occupancy · Booking window · Templates. Each is a list
  with a Sheet editor and a mini timeline of seasonal ranges.

## API

| Method | Route | Access key |
|---|---|---|
| CRUD | `rate-plans` | `page.rates` / `rates.manage` |
| GET/PUT | `room-types/{id}/rates` (the whole grid in one call) | `page.rates` / `rates.manage` |
| CRUD | `pricing/templates`, `pricing/rules?kind=` | `pricing.manage` |
| POST | `pricing/quote` (the simulator: room type, rate plan, arrival, units, booked_at) | `page.rates` |

## Activity log actions

`rate_plans.create|update|delete`, `rates.update` (per changed cell, before/after),
`pricing.template_*`, `pricing.rule_*`.

## Consumes / provides

- **Provides:** `StayWindow::for( RatePlan, localDate, units, ?checkinTime ): StayWindow`
  (start/end local + GMT, crossing flag), `PriceResolver::quote()`, the `PriceBreakdown` component,
  and the `rtbp_price_steps` filter.
- **Consumes:** `OccupancyCalculator` (built in T5 here as a minimal version, which M08 then
  reuses and extends).

## Tasks

- [ ] T1 [Free] `rate_plans` table + model + `StayWindow` (pure), with the booking-engine §10 derivation rows verified on the Local site (midnight, 24 h, flexible, multi-unit, DST fixture, leap day); seed the client's plans
- [ ] T2 [Free] Rate plan library API + screen (editor with a live window preview, features, policy, in-use guard)
- [ ] T3 [Free] `room_type_rates` + the Price & Rates grid (inline edit, drag to reorder, validation: sale < price, min ≤ max)
- [ ] T4 [Free] `PriceResolver`: steps base → sale → date override → floor/round, with the `rtbp_price_steps` filter for inserting steps; `OccupancyCalculator`; `PriceBreakdown` + the simulator; verified against the §6 order on the Local site
- [ ] T5 [Pro] Pricing templates + seasonal rules (tables, API, screens via `rtbp.admin.routes`), inserted as the seasonal step through `rtbp_price_steps`
- [ ] T6 [Pro] Occupancy + early-bird / last-minute rules as `rtbp_price_steps` steps (the §6 order, precedence and rounding verified on the Local site)

## Acceptance

1. Create *Overnight* 20:00 → 08:00. The preview reads "next day, 12 h". Price it at 15 000 on
   Standard and 20 000 on VIP.
2. Set a sale of 13 000 on Standard. The simulator shows base 15 000 → sale 13 000.
3. Add a seasonal +20 % for 20–31 Dec (priority 10) and early-bird −10 % at 30 days. Simulate an
   arrival on 24 Dec booked on 1 Nov: 13 000 → 15 600 → 14 040, with each step explained.
4. Try to delete *Half Day* while a room type sells it. It is refused with the count shown.

## Legacy reference

`legacy-reference.md` → Module 7 (`RTA/woocommerce/class-rate-plans.php`, the PRO pricing chain and
rule precedence).

## Migration notes

`rate_plan` terms + term meta (`schedule_type`, `start_time`, `end_time`, `features`) →
`rate_plans`, deduplicated on type + times. Product `_hbfwc_rate_plans` / `_default_rate_prices` /
`_min_stay(s)` → `room_type_rates`. `hbfwc_seasonal_templates`, `hbfwc_seasonal_rules`,
`hbfwc_occupancy_rules` and `hbfwc_booking_window_rules` → `pricing_templates` / `pricing_rules`.
CSV id lists become JSON arrays, and a `0` meaning "all" becomes an empty array.

## Progress notes
