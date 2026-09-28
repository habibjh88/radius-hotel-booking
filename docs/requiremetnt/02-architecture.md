# 02 — Technical Architecture

**Internal document.** Not for the client.
**Project:** `food-menu-hotel-booking` · **Version:** 4.0 — 9 September 2026
*Supersedes v3.0. Reference screens: the reservation form, the Price & Rates tab, the Rooms screen.*

---

## 1. Design principles

Each principle exists to kill a specific, documented failure in the current stack.

| # | Principle | Kills |
|---|---|---|
| P1 | **A booking is a first-class entity** with its own primary key, reference number and status machine. | Reservation-as-order-line; unqueryable arrivals |
| P2 | **One row per physical room per booking.** Availability is an interval-overlap query on an indexed table. | `qty 3 = 3 reservations`; cart-key hashing; double-booking |
| P3 | **Orthogonal statuses.** Stay and payment are separate columns. | Custom order statuses and custom order types |
| P4 | **Prices are snapshotted at booking time.** Reports read stored figures. | Re-derived revenue; impossible reporting |
| P5 | **Real inventory holds** with expiry, long enough to survive a gateway redirect. | A room unreserved between selection and payment; a stale hold never released |
| P6 | **Dates are `DATETIME` + a `_gmt` twin**, never unix timestamps in session. One `Dates` helper. | The one-day-off changelog |
| P7 | **Money is a record against a booking, taken by our own gateway layer.** No WooCommerce anywhere in the payment path. | Rooms as products; catalogue pollution; a cart shape forced onto a hotel payment |
| P8 | **A schedule is a reusable window; its price belongs to the package.** | Eleven plans duplicated per package |
| P9 | **Reuse Food Menu, don't fork it** — dashboard, permissions, money formatting, licensing. | Two permission systems; a second currency bug |

---

## 2. Data model — see `07-booking-engine-design.md`

The packages / schedules / rooms / bookings model, the availability algorithm, the overlap
predicate, the concurrency guarantee and the admin and customer flows are specified in full in
**`07-booking-engine-design.md`**. That document is authoritative; this one does not repeat it.

The shape, in one diagram:

```
   ┌────────────┐        ┌──────────────────────┐        ┌────────────┐
   │  packages  │◄──────►│  package_schedules   │◄──────►│ schedules  │
   └─────┬──────┘        │ price · sale · min   │        │  (library) │
         │               └──────────────────────┘        └────────────┘
         │ 1:N  ★ exclusive
         ▼
   ┌────────────┐  N:1   ┌────────────┐
   │   rooms    │───────►│   floors   │  (shared across packages)
   └─────┬──────┘        └────────────┘
         │
         ▼
   ┌──────────────────┐        ┌──────────────┐        ┌────────────┐
   │  booking_rooms   │───────►│   bookings   │───────►│   guests   │
   │ ★ one per unit   │        └──────┬───────┘        └────────────┘
   └──────────────────┘               │
                                      ▼
   ┌───────┐  ┌────────────────────┐  ┌──────────────┐
   │ holds │  │ availability_blocks│  │   payments   │
   └───────┘  └────────────────────┘  └──────────────┘
```

Four facts from that document that constrain everything in *this* one:

- **A room belongs to exactly one package**, expressed as a single `rooms.package_id` column. There
  is no package↔room join table, and adding one would break the rule by construction.
- **Room numbers are unique per property**, so two packages cannot both own a `B1`.
- **Schedules are shared; rooms are not.** Two packages selling the identical window share a
  `schedules` row and nothing else.
- **Availability is scoped to the selected package in SQL**, never filtered in the browser.

### 2.1 Terminology change from v4.0

| v4.0 | v5.0 | Why |
|---|---|---|
| `fmhb_room_types` | `fmhb_packages` | The client's own word, on their own screens |
| `fmhb_rate_plans` | `fmhb_schedules` | They are time windows; price lives elsewhere |
| `fmhb_room_type_rates` | `fmhb_package_schedules` | |
| `fmhb_rate_calendar` | `fmhb_schedule_calendar` | |
| `room_type_id`, `rate_plan_id` | `package_id`, `schedule_id` | |
| floors in a settings array | `fmhb_floors` table | Floors are shared across packages and need referential integrity |

Renaming is free: **nothing has shipped.** The backward-compatibility rule in `CLAUDE.md` binds from
first release, not during design.

---

## 3. Payments table

Every other table is in `07-booking-engine-design.md` §3. This one belongs here because it is part
of the gateway layer (§6), not the availability engine.

### 3.1 `fmhb_payments` ★ — money against a booking
```sql
id             BIGINT UNSIGNED PK
booking_id     BIGINT UNSIGNED NOT NULL
gateway        VARCHAR(40)  NOT NULL   -- wave | orange_money | cash
type           VARCHAR(20)  NOT NULL DEFAULT 'payment'
amount         DECIMAL(12,2) NOT NULL
currency       VARCHAR(8)   DEFAULT 'XOF'
status         VARCHAR(20)  NOT NULL DEFAULT 'pending'
               -- pending | completed | failed | cancelled | expired
txn_ref        VARCHAR(191) NOT NULL   -- ★ our reference, sent to the provider
gateway_txn    VARCHAR(191) NULL       -- their transaction id
description    VARCHAR(255)
payload        LONGTEXT     NULL       -- raw verified response, for reconciliation
staff_id       BIGINT UNSIGNED NULL    -- who took a cash payment
occurred_at    DATETIME NOT NULL
created_at, updated_at DATETIME

UNIQUE KEY our_ref  (txn_ref)
UNIQUE KEY prov_ref (gateway, gateway_txn)
KEY booking (booking_id, occurred_at)
KEY sweep   (status, created_at)
```

Three things about this table are load-bearing:

- **`UNIQUE KEY prov_ref` is the idempotency guarantee.** Both providers can deliver the same
  notification more than once, and a return-to-site can race the webhook. A second insert for the
  same `(gateway, gateway_txn)` fails at the database, not in application logic — so a booking can
  never be credited twice for one payment.
- **`status`, not just an amount.** A row is written as `pending` *before* the redirect, so a
  payment that never completes is still visible and reconcilable rather than absent.
- **`KEY sweep`** backs the cron that re-queries stale `pending` rows against the provider.

`bookings.paid_total` and `bookings.balance_due` are recalculated from `SUM(amount)` over
`status = 'completed'` rows. The `type` column is kept so the refunds add-on attaches without a
schema change.

**Removed tables:** `fmhb_properties`, `fmhb_pricing_rules`, `fmhb_booking_addons`,
`fmhb_booking_guests`, `fmhb_amenities`, `fmhb_ical_feeds`, `fmhb_activity_log`.

---

---

## 4. Availability

Specified in `07-booking-engine-design.md` §5–§8: the overlap predicate, the SQL, the in-memory
multi-schedule evaluation, seventeen worked edge cases, and the three-layer double-booking guard.

Two points that bind the rest of this architecture:

- **The engine is four to five queries regardless of how many packages, schedules or rooms exist.**
  One availability query per (package × schedule) would be 33 queries per page load on the client's
  current data. This is the single most important performance constraint in the plugin.
- **The write path takes a row lock.** `SELECT id FROM fmhb_rooms WHERE id = %d FOR UPDATE` inside a
  transaction, with the overlap re-check *inside* that transaction. MySQL cannot express a range
  exclusion constraint, so the lock is the guarantee — not a nicety. Every write path takes it:
  public booking, staff walk-in, dashboard edit, importer.

---

## 5. Status machines

**Stay**
```
pending ──► confirmed ──► checked_in ──► checked_out
   │            │             │
   ▼            ▼             ▼
cancelled   cancelled     (no exit — a stay in progress
   ▲        / no_show      can only complete or be voided
   └────────────┘           by a supervisor override)
```

**Payment** — independent: `unpaid` / `on_hold` → `partially_paid` → `paid`.
(`on_hold` mirrors the value the client's reservation form already sets by hand.)

The two axes never collapse into one column — that is the fix for the custom-order-type sprawl in
the existing Pro plugin.

Transitions run through `Booking\StatusManager::transition()`, which validates the move, fires
`fmhb/booking/status_changed`, and triggers notifications. Illegal moves throw. **Keep
`fmhb/booking/status_changed` firing with the full before/after payload even though nothing
consumes it today** — it is the seam the activity-log add-on attaches to, and it costs nothing now.

---

## 6. Payment gateways

**There is no WooCommerce in the payment path.** WooCommerce remains installed on the site for the
restaurant side, but this plugin neither creates orders nor reads them. Rooms never enter the
catalogue, so there is no shop-exclusion hack, no feed pollution and no stock override.

### 6.1 The contract

```php
interface GatewayInterface {
    public function get_id(): string;               // 'wave'
    public function get_label(): string;            // display title, translatable
    public function get_settings_fields(): array;   // rendered into the settings panel
    public function is_available( Booking $b ): bool;
    public function start( Booking $b, float $amount ): PaymentIntent;  // → redirect or done
    public function handle_callback( WP_REST_Request $r ): CallbackResult;
    public function verify( Payment $p ): PaymentStatus;   // authoritative, server-to-server
}
```

Gateways register through the **`fmhb_payment_gateways`** filter. Three ship:

| Gateway | Shape | Notes |
|---|---|---|
| **Wave** | Hosted checkout session → redirect → signed webhook | Amount in XOF (integer minor units are not used — XOF has no subunit). Session carries our `txn_ref` as the client reference. |
| **Orange Money** | Token-authenticated web-payment request → redirect → notification callback | Requires an access token obtained per request cycle and cached until expiry; the merchant key and the token are separate credentials. |
| **Cash** | No integration | `start()` writes a `completed` payment row directly and returns no redirect. |

Requirement 7.13 — a fourth provider must not touch the first three — is met by this interface plus
the filter. Moov Money or a card acquirer is a new class and a settings panel, nothing else.

### 6.2 The flow

```
 guest picks Wave ──► POST /payments/start
                          │
                          ├─ write payments row  status=pending, txn_ref=RT-…-841-01
                          ├─ hold extended to cover the redirect
                          └─ gateway->start() → provider checkout URL
                                   │
                        ┌──────────┴───────────┐
                        ▼                      ▼
              guest returns to us      provider posts callback
              (return_url)             (notif/webhook URL)
                        │                      │
                        └──────────┬───────────┘
                                   ▼
                        gateway->verify()  ← server-to-server, authoritative
                                   ▼
                    payments.status = completed | failed
                                   ▼
                 recalc paid_total / balance_due → payment_status
                                   ▼
                    release hold · confirm booking · send emails
```

Five rules, each one a defect that would otherwise reach production:

1. **A return URL never confirms a payment.** The guest's browser is not a trusted party; `verify()`
   runs server-to-server and is the only thing that writes `completed`. The return URL only decides
   which page the guest sees.
2. **The callback is the primary path, the return is the fallback.** A guest who pays and closes the
   browser must still end up with a paid booking — this is normal behaviour on a phone, not an edge
   case, and it is success criterion 3.
3. **Idempotency is enforced at the database**, by `UNIQUE KEY prov_ref`, not by an application
   check that races itself.
4. **Pending payments are swept.** `fmhb_reconcile_pending_payments` (WP-Cron, every 15 min)
   re-runs `verify()` on `pending` rows older than a few minutes and resolves or expires them, so a
   dropped callback self-heals instead of leaving a guest who paid looking unpaid.
5. **Callback routes are public by necessity** — the provider has no nonce and no cookie. They are
   authenticated by the provider's own signature or by re-verifying against the provider's API, and
   they accept only a `txn_ref` that exists and is still `pending`.

### 6.3 Settings

One settings tab, one collapsible panel per gateway, each with: enable toggle, display title
(translatable, shown to the guest), test/live mode, credentials, and **the callback URL rendered
read-only for copying into the provider's dashboard** — the single most common setup failure is a
notification URL never registered on the provider side.

Credentials are written to the settings option but **never localised to the browser and never
logged**. `payload` on a payment row stores the verified response with credential fields stripped.

**Pay at hotel** is not a gateway. It is the absence of one: the booking is created with
`payment_status = unpaid` and a balance due, and check-out warns until it clears.

---

## 7. Food & beverage → room (deliberately not built)

Charging a restaurant order to a guest's room is the strongest differentiator this plugin could
have, because no competing hotel plugin also runs the restaurant. It is **out of scope** and must
not be implied anywhere client-facing.

The seam is already in place and costs nothing to leave open: `fmhb_payments.type` accepts a value
other than `payment`, and in-house guests resolve off `KEY arrivals` with
`stay_status = 'checked_in'`.

---

## 8. Plugin structure

Mirrors `food-menu-pro` so the team needs no new mental model.

```
food-menu-hotel-booking/
├── food-menu-hotel-booking.php     bootstrap, constants, dependency check
├── composer.json                   PSR-4  RT\FoodMenuHotel\  →  app/
├── package.json / webpack/         same pipeline as Pro
├── app/
│   ├── FMHB.php                    final singleton, init on plugins_loaded @ 25
│   ├── Traits/SingletonTrait.php   own copy — do not import Pro's
│   ├── Abstracts/Controller.php
│   ├── Database/
│   │   ├── SchemaInstaller.php     dbDelta, version_compare gated
│   │   └── tables/*.php
│   ├── Models/                     Booking, BookingRoom, Room, Package, Schedule,
│   │                               PackageSchedule, Guest, Payment
│   ├── Repositories/               BookingRepo, AvailabilityRepo, RateRepo, GuestRepo, PaymentRepo
│   ├── Services/
│   │   ├── AvailabilityService.php   the query above + per-plan evaluation + holds
│   │   ├── PricingService.php        calendar → sale → regular
│   │   ├── BookingService.php        create / modify / cancel
│   │   ├── CheckinService.php
│   │   └── ReconcileService.php      pending-payment sweep
│   ├── Payments/
│   │   ├── GatewayInterface.php
│   │   ├── GatewayRegistry.php       fmhb_payment_gateways filter
│   │   ├── PaymentService.php        start / verify / credit / recalc
│   │   ├── Gateways/Wave.php
│   │   ├── Gateways/OrangeMoney.php
│   │   └── Gateways/Cash.php
│   ├── Api/                        fmhb/v1 controllers (incl. public callbacks)
│   ├── Controllers/
│   │   ├── DashboardController.php   mounts hotel pages into the FM dashboard
│   │   ├── FrontendController.php    booking shortcode / block
│   │   └── NotificationController.php
│   ├── Helpers/  Fns, Dates, Money, Options, Install, Migrator, Licence
│   └── Integrations/FoodMenu/       permissions, money formatting adapters
├── src/js/hotel/                   React app — shared BookingFlow used by staff and guests
├── src/scss/
├── templates/                      front-end booking flow
├── languages/
└── readme.txt                      [UNRELEASED] changelog discipline
```

Bootstrap contract, copied from Pro:
- `Requires Plugins: tlp-food-menu` header + a runtime `class_exists('TLPFoodMenu')` guard with an
  admin notice, so a missing dependency degrades instead of fataling.
- `add_action('plugins_loaded', 'FMHB', 25)` — **after** Pro's 20.
- Controllers registered through `Helpers\Fns::instances()`.
- Schema install hooked to `init` with a one-time option marker, **not only** `register_activation_hook`
  — per the `Install::maybe_seed_menu_type` lesson already documented in `tlp-food-menu/CLAUDE.md`
  (the activation hook is missed by WP-CLI, bulk activation and sandboxed activation).
- Version gate uses `version_compare()`, **not** `<` — Pro's existing `get_option(...) < CONST`
  string comparison reports `'1.10.0' < '1.9.0'` as true. Do not copy that bug.

---

## 9. REST API — `fmhb/v1`

Own namespace. `fmp/v1` already holds ~35 Food Menu routes; collision risk is real.

Every route: `permission_callback` → capability check, **plus** an `X-WP-Nonce` check inside the
callback — **except the two public routes**, marked ★, which providers call with neither and which
are authenticated by provider signature instead.

| Route | Methods | Purpose |
|---|---|---|
| `/availability` | GET | Per package: schedules priced, windowed and flagged available/why-not, plus rooms with state |
| `/availability/calendar` | GET | Month grid: price, min-stay, closed, free count |
| `/holds` | POST, DELETE | Place / release an inventory hold |
| `/packages` | GET POST | |
| `/packages/{id}` | GET PUT DELETE | |
| `/packages/{id}/schedules` | GET PUT | **The Price & Rates grid** — assignments, prices, order |
| `/rooms` | GET POST | Physical units; `?type=` / `?floor=` filters, grouped by floor |
| `/rooms/{id}` | GET PUT DELETE | |
| `/rooms/{id}/status` | POST | Operational state |
| `/rooms/generate` | POST | Create a floor's rooms from a number range |
| `/schedules` | GET POST | The library |
| `/schedules/{id}` | GET PUT DELETE | |
| `/rates/calendar` | GET POST | Read / write the rate calendar |
| `/blocks` | GET POST DELETE | Manual availability blocks |
| `/bookings` | GET POST | List (arrivals, departures, in-house) / create |
| `/bookings/{id}` | GET PUT DELETE | |
| `/bookings/{id}/status` | POST | Guarded transition (incl. cancel, no-show) |
| `/bookings/{id}/rooms` | POST DELETE | Add / remove a room line, assign a unit |
| `/bookings/{id}/checkin` | POST | |
| `/bookings/{id}/checkout` | POST | Warns on a non-zero balance |
| `/payments` | GET POST | List for a booking / record a desk payment |
| `/payments/start` | POST | Begin a gateway payment; returns a redirect URL |
| `/payments/{id}/verify` | POST | Force a server-side re-check |
| ★ `/payments/callback/{gateway}` | POST GET | Provider notification / webhook |
| ★ `/payments/return/{gateway}` | GET | Guest lands here after paying; verifies, then redirects |
| `/guests` | GET POST | `?q=` searches name, phone, email |
| `/guests/{id}` | GET PUT | |
| `/guests/lookup` | GET | Indexed name / E.164 phone / email recognition |
| `/reports` | GET | Occupancy, revenue, per package, per payment method — one payload |
| `/reports/export` | GET | Bookings CSV |
| `/settings` | GET POST | Includes floors and gateway configuration |
| `/migration/dry-run` \| `/migration/run` | POST | Importer |

**One `/reports` endpoint, not five.** The figures share a date range and a single pass over
`booking_rooms` + `payments`; separate routes would mean repeated scans of the same rows for one
screen.

---

## 10. Dashboard integration

**Verified fact.** Pro's dashboard navigation *and* its routes are hardcoded module-level constants
in `food-menu-pro/src/js/frontend-dashboard/App.js` (`ALL_NAV_ITEMS`, then the `<Routes>` block).
There is **no JS hook registry** — confirmed by search: zero `wp.hooks` / `addFilter` usage in
either plugin's `src/`. The permission list is a PHP class constant,
`FnsPro::DASHBOARD_MENUS` (`food-menu-pro/app/Helpers/FnsPro.php:26`), also not filterable.

The one purpose-built seam is `fmp_frontend_dashboard_nav_extra_items`
(`FrontendDashboardController.php:446`), which renders **plain `<a>` links that leave the SPA**.
Its own docblock uses a hotel example. That is exactly what the client's current sidebar does with
its "Food Order and Inventory" link.

### Option A — separate app, cross-linked (works today, zero upstream change)
Two shells, a full page load between them. Ships immediately.

### Option B — one shell (recommended, ~2 days upstream) ★
Two small, backward-compatible changes to `food-menu-pro`:

1. Wrap the menu constant:
   ```php
   public static function dashboard_menus() {
       return apply_filters( 'fmp_dashboard_menus', self::DASHBOARD_MENUS );
   }
   ```
   and route every existing read through it. `DASHBOARD_MENUS` stays as a constant, so nothing
   that already reads it breaks.
2. Add a route registry in `App.js`:
   ```js
   const EXTRA_ROUTES = window.fmpDashboardExtraRoutes || [];
   // merged into ALL_NAV_ITEMS and rendered as <Route> with a mount-node bridge
   ```

**Recommendation: Option B, inside Stage 1's hours.** "One dashboard, one login" is the promise made
to the client, and it is the only upstream work in this package. Option A is the fallback if the
upstream change is rejected — it changes no hotel-side code.

### React instance caveat
Pro **bundles its own React 18** (`webpack/config.production.js` externalises only
`@wordpress/i18n`), while the free plugin externalises to `wp.element` (React 19). The hotel bundle
therefore **cannot share Pro's React instance**. Under Option B the bridge is a mount-function
handoff, not a shared component tree. This is why Option B is 2 days, not 2 hours.

### One booking component, two mounts
`BookingFlow` is written once and mounted twice — into the dashboard for staff, and into a
shortcode/block root for guests. The staff mount enables the payment-status selector and walk-in
fields; the guest mount enables gateway selection. **They must not fork**, or the two forms drift
and the "same as the front-end" requirement quietly stops being true.

### UI stack
Match the dashboard, not the settings panel: **Ant Design 5**, `lucide-react`, `recharts`,
`@tanstack/react-query`, `react-router-dom` v7 (HashRouter), `react-toastify`, `@wordpress/api-fetch`.

---

## 11. Reuse map

Roughly 60–80 hours of infrastructure comes free because Food Menu already has it.

| Need | Reuse from Food Menu | Path |
|---|---|---|
| Permission model | `fmp_dashboard_permissions` + `get_allowed_dashboard_menus()` / `has_order_manage_capability()` | `food-menu-pro/app/Helpers/FnsPro.php` |
| Money formatting | `FnsPro::get_price_format()` → `formatPrice()` | `src/js/frontend-dashboard/utils/price.js` |
| Sale-price rule | `FnsPro::get_product_price_fields()` — sale only when it undercuts regular; `null` never `0` | `food-menu-pro/app/Helpers/FnsPro.php` |
| Licensing | `Admin/Ajax/Licence.php` + `EDD_Plugin_Updater`; own item id and option keys; register via `fmp_settings_protected_keys` | `food-menu-pro/app/Controllers/Admin/` |
| Settings persistence | `wp_ajax_fmpNewSettingsUpdate` has **no key whitelist** — hotel keys save with zero PHP registration. **Gateway credentials must still be excluded from any localised payload.** | `tlp-food-menu/app/Controllers/Admin/Ajax/Settings.php:210` |
| Time format helpers | `FnsPro::php_to_dayjs_time_format()` / `dayjs_format_is_12h()` | `food-menu-pro/app/Helpers/FnsPro.php` |
| Table schema pattern | `Database/*Table.php` dbDelta — but fix the version compare | `food-menu-pro/app/Database/` |

**Namespace conflicts to avoid.** Already taken by *table* reservations: the `fmp_reservation` CPT,
the `fmp/reservation/*` filter family, `fmp_resi_*` meta and options, the `fmp/v1` REST namespace.
Use `fmhb_*` and `fmhb/*` throughout.

---

## 12. Performance

| Concern | Approach |
|---|---|
| **Rate-plan availability** | Four queries regardless of plan count — see §4.1. Never one query per plan. |
| Calendar grid (30 days × 2 types) | One grouped query. Availability counts aggregated in SQL. |
| Room grid | One query, grouped into floors in PHP against the settings order. |
| Guest lookup | Indexed `phone_e164` / `name_search` — never `LIKE '%…'` variant matching. |
| Reports | One aggregate pass over `booking_rooms` + `payments`; no per-booking loops. |
| Dashboard stats | Transient keyed by the filter hash — mirroring the `fmp_dashboard_stats` lesson that a hardcoded transient key stops clearing once a filter is configured. |
| Gateway calls | Never inside a page render. `start()` is its own request; `verify()` runs on callback or cron. |
| N+1 | Batch-load rooms, types and schedules before any loop. |

---

## 13. Security

- Every REST callback: capability check **and** nonce check, except the two public gateway routes.
- **Gateway callbacks**: verify the provider signature where one exists; otherwise re-verify
  server-to-server against the provider before crediting. Accept only an existing, still-`pending`
  `txn_ref`. Rate-limit. Never trust an amount supplied in the callback — compare it to the stored
  payment row and reject a mismatch.
- **Credentials**: stored in the settings option, never localised to the browser, never logged,
  stripped from `payload` before storage. Test and live credentials held separately so switching
  mode cannot silently charge real money.
- All `$wpdb` calls through `prepare()`; SQL fragments spliced only where they contain no `%`.
- Guest ID documents treated as sensitive: never logged, never in REST list responses, only on the
  single-booking view with an explicit capability.
- Rate limiting on public availability search.
- Licence key never written to the database before validation (copy Pro's `Licence.php`).

---

## 14. Migration design (reduced)

**Rooms, floors, schedules, guests and future bookings only** — past history is deliberately not
imported and stays readable in the old plugins, which are deactivated but never deleted.

| Source | Target |
|---|---|
| `accommodation` products | `fmhb_room_types` (+ `fmhb_rooms` from `_hbfwc_room_numbers` / the client's `hbfwc_restata_rooms`) |
| Distinct floor values on existing rooms | the `fmhb_floors` settings list, ordered on review |
| `rate_plan` terms + `_hbfwc_rate_plans` meta | `fmhb_schedules` (the eleven windows, deduplicated into one library) |
| Per-product rate prices | `fmhb_package_schedules` — regular, sale, min stay, enabled |
| `hbfwc_rateplans` + `_default_rate_prices` | `fmhb_rate_calendar` |
| `hbfwc_restata_booking` **filtered to `checkout >= today`** | `fmhb_bookings` + `fmhb_booking_rooms` |
| WC customers | `fmhb_guests`, phone normalised to E.164 |

Not imported: past bookings, historical order line meta, blocked dates, amenity terms, and **no
payment history** — money taken through WooCommerce stays in WooCommerce's records.

Rules: idempotent (re-runnable, keyed on a stored source id), **dry-run first** with a discrepancy
report, never deletes source data.

**The schedule deduplication is the one judgement call in the importer.** The source stores plans
per product; the target stores one library plus per-type prices. Plans are matched on
`schedule_type + start_time + end_time`, and anything that does not match cleanly is reported in the
dry-run for a human decision rather than merged silently.

---

## 15. Testing

| Layer | Coverage |
|---|---|
| Unit (PHPUnit) | Availability overlap edge cases (back-to-back windows, midnight-crossing blocks, DST boundary, leap day), the price resolution chain, balance arithmetic, status transitions incl. illegal moves, `number_sort` generation. |
| Integration | Booking lifecycle end to end; **each gateway against its sandbox**; callback replay; guest abandons the browser after paying. |
| Concurrency | Two parallel requests for the last room — exactly one succeeds. Automated, run every release. |
| Idempotency | The same provider notification delivered twice credits the booking once. |
| Migration | Full dry-run against a copy of production; room, floor, schedule, guest and future-booking counts reconciled. |
| Manual UAT | Client staff run real days on staging before cutover. |
| Static | PHPCS + ESLint + Stylelint in the pre-release gate. |

---

## 16. Open technical questions

| # | Question | Default if unanswered |
|---|---|---|
| Q1 | Are Wave and Orange Money merchant accounts and sandbox credentials already held? | **Blocking for Stage 4.** Requested at signing, not at stage start. |
| Q2 | Does Orange Money settlement need the same-day reconciliation report their dashboard offers, or is per-transaction verification enough? | Per-transaction verification |
| Q3 | Should a guest be allowed to retry a failed payment on the same booking, or must they rebook? | Retry on the same booking, new `txn_ref` |
| Q4 | Does the client accept losing one-click access to past bookings inside the new dashboard? | Yes — old plugin stays installed and readable; full import is a priced add-on |

**Resolved:** dashboard integration is Option B with Option A as fallback; properties are settings,
not a table; payroll is permanently out of scope; currency is XOF only; WooCommerce is not in the
payment path.
