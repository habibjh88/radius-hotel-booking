# Architecture

This document explains how the product is put together. The framework mechanics (DI container,
ORM, REST router, i18n, Tailwind scoping) are in the root `CLAUDE.md`, and the booking engine has
its own document, `booking-engine.md`.

---

## 1. System view

```
                ┌──────────────────────────── WordPress ────────────────────────────┐
 Staff browser  │  wp-admin page  ──┐                                               │
 (desk, phone)  │  /hotel-dashboard ┴─► admin bundle (React SPA, HashRouter)         │
                │                          │  REST  radius-hotel-booking/v1          │
 Guest browser  │  booking page ─────► site bundle (BookingFlow mode="guest")        │
                │                          │                                         │
                │                ┌─────────▼──────────┐                              │
                │                │ Middleware          │ Auth · Access (M13) ·       │
                │                │                     │ RateLimit · Nonce+Referer   │
                │                ├─────────────────────┤                              │
                │                │ Controllers (thin)  │                              │
                │                ├─────────────────────┤                              │
                │                │ Services (rules)    │──► rtbp_activity() ─► Pro log │
                │                │  Availability       │──► EventDispatcher / hooks   │
                │                │  Pricing · Booking  │──► Emails · PDFs             │
                │                │  Payment · …        │                              │
                │                ├─────────────────────┤                              │
                │                │ Repositories (SQL)  │                              │
                │                └─────────┬───────────┘                              │
                │                          ▼                                          │
                │            MySQL/InnoDB  radius_hotel_booking_* tables              │
                │            uploads/radius-hotel-booking/ (protected files)          │
                │            WP-Cron: holds · deadlines · exports · log archive       │
                └────────────────────────────────────────────────────────────────────┘
```

WooCommerce may be installed for the restaurant. This plugin never touches it (ADR-001).

## 2. Code layout

The target layout. Domains get their own sub-namespace once they have more than two classes.

```
includes/
  Abstracts/            BaseController, BaseModel, BaseRepository, BaseResource, BaseEmail, Migration
  Core/                 framework: Api, Container, Database, ORM, Events, Validation, Support
  Access/               AccessRegistry, Access, AccessMiddleware, PasscodeService            (M13)
  Activity/             rtbp_activity() emitter, ActionCatalog, ChangeDiff                  (M14, free)
  Settings/             SettingsSchema, sections                                            (M17)
  Support/              Money, Dates, Phone, Sequence, Transaction, ProtectedFiles          (M00)
  Exceptions/           DomainException + codes                                            (M00)
  Models/               one class per table
  Repositories/         one per aggregate root + query-specific repos (AvailabilityRepository)
  Services/
    Inventory/          RoomTypeService, RoomService, FloorService                          (M06)
    Pricing/            RatePlanService, PriceResolver, rules/                               (M07)
    Availability/       StayWindow, AvailabilityService, HoldService, BlockService, Ical/    (M08)
    Guests/             GuestService, NoteService                                            (M09)
    Booking/            BookingService, StatusMachine, BookingQuery                          (M02, M03)
    Payment/            PaymentService, InvoiceService, DeadlineService                      (M05)
    Reports/            ReportService                                                        (M10)
    Export/             ExportService, writers/                                              (M11)
    Staff/              EmployeeService                                                      (M12)
    Payroll/            StatutoryTable, PayrollCalculator, RunService, TimeOffService        (M15)
    Import/             LegacyImporter, readers/                                             (M18)
  Controllers/          one per resource; Public/ for unauthenticated endpoints
  Resources/            API shapes
  Emails/               BaseEmail subclasses (Guest/, Staff/)
  Documents/            PdfRenderer + document models (invoice, receipt, payslip)
  Setup/                Installer, Scheduler, PermissionsInstaller, PageInstaller
  Routes/routes.php
src/
  admin/                staff SPA entry, routes.js (grouped, access-keyed)
  site/                 public entry
  components/ui/        shadcn primitives
  components/           shared composites (design-system §5)
  modules/<Module>/     screens + api.js + queries.js
  lib/                  format.js, status.js, access.js, query-client.js
templates/
  emails/  documents/  booking/
```

## 3. Request lifecycle (a staff action)

1. React calls `post( 'bookings/841/approve' )` through `src/api/client.js`, which sends the nonce
   and any passcode token.
2. `AuthMiddleware` checks that the user is logged in. `AccessMiddleware` resolves the level of
   `bookings.approve` (M13): *locked* returns 403, *passcode* without a valid token returns 403
   `passcode_required` (the client opens `PasscodeDialog`, then retries), *open* continues.
3. The controller validates the input and calls `BookingService::approve( 841, $reason )`.
4. The service loads the booking, asks `StatusMachine` whether the transition is legal, and
   writes inside a `Transaction`. It emits `rtbp_activity( 'bookings.approve', … before/after )` (Pro stores it)
   and fires `rtbp_booking_status_changed` and `rtbp_booking_changed`.
5. Listeners send e-mails, invalidate the counter and report caches, and recompute the invoice
   if needed.
6. The controller returns `ApiResponse::success( BookingResource )`.
7. React Query invalidates `['bookings']`, `['dashboard','counters']` and `['booking', 841]`.

## 4. Data model

All tables are prefixed with `{wp_prefix}radius_hotel_booking_` and use InnoDB. Every table has
`created_at` and `updated_at` unless noted. Money is `DECIMAL(12,2)`. Time columns that drive
availability or reports have a `_gmt` twin. Status-like values are `VARCHAR`, not MySQL `ENUM`.

```
floors ◄──┐
          │ N:1
room_types ◄── rooms (room_type_id ★ exclusive, floor_id, number UNIQUE, state)
    │
    │ 1:N                                 rate_plans (library: fixed | flexible, times, hours)
    └── room_type_rates (room_type_id × rate_plan_id: price, sale_price, min_units, enabled, sort)
                         │
rate_calendar (room_type_id, rate_plan_id|NULL, date: price_override, closed)
pricing_templates ◄── pricing_rules (kind: seasonal | occupancy | early_bird | last_minute, scope)
blocks (scope: property | floor | room_type | room, start_at, end_at, source, reason)
ical_feeds (room_id | room_type_id, direction, url, token, last_sync)

guests ◄── bookings ──► booking_rooms (one per physical room × window; frozen price)
              │                 ▲
              │                 └── holds (room_id, window, token, expires_at) — pre-booking
              ├──► payments (ledger)      invoices ──► invoice_versions
              └──► notes (polymorphic: booking | guest | employee)

activity_log · log_archives · access_roles · sequences · exports · import_map
employees ──► employee_contracts · employee_pay_items · employee_documents
pay_schedules · work_shifts · payroll_runs ──► payroll_lines · statutory_* · time_off_*
```

### 4.1 Inventory (M06)

| Table | Columns |
|---|---|
| `floors` | `id`, `name`, `sort_order` |
| `room_types` | `id`, `name`, `slug`, `description`, `short_description`, `gallery` (JSON attachment ids), `featured_image_id`, `amenities` (JSON), `bed_info`, `size_m2`, `max_adults`, `max_children`, `buffer_minutes` (NULL → setting), `is_active`, `sort_order`, soft delete |
| `rooms` | `id`, `room_type_id` ★, `floor_id`, `number` **UNIQUE**, `number_sort` (natural-sort key), `state` (available / maintenance / out_of_service), `state_note`, `sort_order`, soft delete. Index `(room_type_id, state)`, `(floor_id)` |

### 4.2 Pricing (M07)

| Table | Columns |
|---|---|
| `rate_plans` | `id`, `name` (JSON FR/EN), `code`, `type` (fixed / flexible), `start_time`, `end_time` (fixed), `duration_minutes` (derived for fixed, set for flexible), `checkin_from`, `checkin_until` (flexible), `multi_unit` (bool: may span several days/nights), `features` (JSON FR/EN tags), `policy` (JSON FR/EN), `is_active`, `sort_order`, soft delete |
| `room_type_rates` | `id`, `room_type_id`, `rate_plan_id`, `price`, `sale_price` (NULL = none), `min_units`, `max_units`, `enabled`, `sort_order`. UNIQUE `(room_type_id, rate_plan_id)` |
| `pricing_templates` | `id`, `name`, `mode` (increase / decrease / set), `amount_type` (percent / fixed), `amount` |
| `pricing_rules` | `id`, `kind` (seasonal / occupancy / early_bird / last_minute), `template_id` (seasonal) or `mode` + `amount_type` + `amount`, `room_type_ids` (JSON, empty = all), `rate_plan_ids` (JSON, empty = all), `date_from`, `date_to`, `threshold` (occupancy % or days ahead), `priority`, `is_active` |

### 4.3 Availability (M08)

| Table | Columns |
|---|---|
| `rate_calendar` | `id`, `room_type_id`, `rate_plan_id` (NULL = the whole room type), `date`, `price_override` (NULL), `is_closed`. UNIQUE `(room_type_id, rate_plan_id, date)` |
| `blocks` | `id`, `scope` (property / floor / room_type / room), `scope_id`, `start_at`, `end_at`, `start_at_gmt`, `end_at_gmt`, `source` (manual / ical), `feed_id`, `external_uid`, `reason`, `created_by`. Index `(scope, scope_id, start_at_gmt, end_at_gmt)` |
| `ical_feeds` | `id`, `room_id`, `direction` (import / export), `name`, `url` (import), `token` (export, random), `interval_minutes`, `last_synced_at`, `last_status` |
| `holds` | `id`, `room_id`, `room_type_id`, `rate_plan_id`, `start_at_gmt`, `end_at_gmt`, `token`, `user_id` / `session_key`, `expires_at_gmt`. Index `(room_id, start_at_gmt, end_at_gmt)`, `(expires_at_gmt)` |

### 4.4 Bookings (M02, M03, M05)

| Table | Columns |
|---|---|
| `bookings` | `id`, `reference` UNIQUE (`RT-2026-000841`), `guest_id`, `source` (desk / web / import / ical), `status` (summary of the lines, §5 of the booking-engine doc), `payment_status`, `on_hold`, `subtotal`, `discount_total`, `tax_total`, `total`, `paid_total`, `balance_due`, `currency` (XOF), `payment_due_at`(+gmt), `adults`, `children`, `special_requests`, `language`, `public_token` (confirmation page), `created_by` (user or NULL for web), `approved_by`, `approved_at`, `cancelled_reason`, timestamps + `created_at_gmt`, soft delete. Indexes `(status)`, `(payment_status, payment_due_at_gmt)`, `(created_at_gmt)`, `(guest_id)` |
| `booking_rooms` | `id`, `booking_id`, `room_id`, `room_type_id` (snapshot), `rate_plan_id` (snapshot), `rate_plan_name` (snapshot), `room_number` (snapshot), `floor_name` (snapshot), `start_at`, `end_at`, `start_at_gmt`, `end_at_gmt`, `occupied_until_gmt` (= `end_at_gmt` until an early check-out), `units`, `adults`, `children`, `status` (pending / confirmed / checked_in / checked_out / cancelled / declined / no_show), `unit_price`, `total`, `price_breakdown` (JSON: each pricing step), `checked_in_at`, `checked_out_at`, `checked_in_by`, `checked_out_by`. Indexes **`(room_id, start_at_gmt, occupied_until_gmt)`**, `(start_at_gmt)`, `(end_at_gmt)`, `(status)`, `(booking_id)` |
| `payments`, `invoices`, `invoice_versions` | see M05 |

### 4.5 Platform and people

`sequences`, `files` (M00: the protected-file index; `token` UNIQUE, `kind`, `path`, `original_name`, `mime`, `size`, `sha256`, `created_by`) · `access_roles` (M13) · `activity_log`, `log_archives` (M14) · `guests`, `notes`
(M09) · `exports` (M11) · `employees`, `employee_contracts`, `employee_pay_items`,
`employee_documents` (M12) · the payroll tables (M15) · `import_map` (M18). The column lists are
in each module doc.

## 5. Cross-cutting services

| Service | Module | Used by |
|---|---|---|
| `Money`, `Dates`, `Phone`, `Sequence`, `Transaction` | M00 | everyone |
| `SettingsSchema` / `rtbp_setting()` | M17 | everyone |
| `Access` / `AccessMiddleware` | M13 | every controller |
| `rtbp_activity()` (free emitter; Pro stores) | M14 | every service that changes state |
| `NoteService` / `NotesPanel` | M09 | M03, M12 |
| `PdfRenderer` | M05 | M05, M15 |
| `ExportWriter` | M10/M11 | M10, M11, M14, M15 |
| `AvailabilityService` / `PriceResolver` | M08 / M07 | M02, M03, M04, M10 |

## 6. Hooks (public contract)

| Hook | Fired by | Payload |
|---|---|---|
| `rtbp_booking_created` | BookingService | booking |
| `rtbp_booking_status_changed` | BookingService | booking, line or null, from, to, actor |
| `rtbp_booking_changed` | any booking write | booking id |
| `rtbp_payment_recorded` | PaymentService | payment, booking |
| `rtbp_settings_updated` | SettingsService | section, before, after |
| `rtbp_access_changed` / `rtbp_access_denied` / `rtbp_passcode_failed` / `rtbp_page_viewed` | M13 | … |
| `rtbp_price_steps` (filter) | PriceResolver | steps, context. An add-on can add a pricing step |
| `rtbp_access_keys`, `rtbp_activity_actions`, `rtbp_settings`, `rtbp_email_classes`, `rtbp_id_document_types` (filters) | registries | add-on extension points |

## 7. Performance budget

| Operation | Budget | How |
|---|---|---|
| Availability search (all room types × rate plans, 30 rooms) | < 300 ms | Four queries in total (booking-engine §5.3), evaluated in memory |
| Dashboard counters | < 100 ms | One conditional-aggregate query, cached for 15 s |
| Booking list page | < 300 ms | Indexed filters, `LIMIT` pagination, batch-loaded guests and rooms |
| Reports for one year | < 500 ms | One aggregate pass, transient cache per filter hash |
| New-booking poll | < 30 ms | An indexed `created_at_gmt > ?` check |

## 8. Security

- Nonce + Referer + AccessMiddleware on every staff endpoint. Public endpoints (availability
  search, create a guest booking, the confirmation page, the iCal export) are listed in the
  `rtbp_public_api_routes` filter, rate-limited, and never return personal data beyond the
  requester's own booking (tokenised).
- The legacy security holes in `legacy-reference.md` are explicitly **not** reproduced. The
  public lookup endpoint is replaced by a staff-only lookup (`guests/lookup`). No auto-login.
- PINs and the fallback PIN are hashed. Identity numbers are masked in lists and logs.
- The iCal export uses a random token per feed, and the import URL is SSRF-checked (no private or
  loopback IPs, http/https only).
- Files live only in protected storage.

## 9. Three plugins and their extension points

The product ships as Free (wordpress.org), Pro, and a Residence TATA add-on (ADR-014). Everything
above describes the **free** plugin. Pro and the add-on are sibling repos built on the same
framework:

```
../radius-hotel-booking-pro/                ../radius-hotel-booking-residencetata/
  radius-hotel-booking-pro.php  (requires free)      …-residencetata.php  (requires free + pro)
  includes/  RadiusTheme\RadiusHotelBookingPro\     includes/  RadiusTheme\RadiusHotelBookingTata\
    Access/  ActivityLog/  Pricing/  Ical/            Payroll/  Import/  Branding/
    Documents/Pdf/  Reports/  Export/  Staff/  SelfService/
  src/  (webpack externals → window.rtbp)             src/
```

- **PHP seams:** the tables in ADR-015 (`rtbp_activity`, `rtbp_access_level`,
  `rtbp_access_passcode_check`, `rtbp_price_steps`, `rtbp_document_renderers`,
  `rtbp_report_definitions`, `rtbp_export_formats`, `rtbp_block_sources`,
  `rtbp_migration_classes`, `rtbp_register_addon_routes`, `SettingsSchema::register()`,
  `rtbp_email_classes`, `rtbp_access_keys`, `rtbp_activity_actions`).
- **JS seams:** the `window.rtbp` runtime (`ui`, `lib`, `React`, `hooks`) and the filters
  `rtbp.admin.routes`, `rtbp.settings.sections`, `rtbp.booking.panels`, `rtbp.guest.panels`,
  `rtbp.api.error`, `rtbp.dashboard.widgets`, `rtbp.reports.tabs`.
- **Adding a seam** is a free-plugin task. It lands in the free repo *before* the Pro task that
  uses it, and it is documented in ADR-015 in the same task.
- **Data ownership:** Pro's tables, options and meta use `rtbp_pro_`, and the add-on's use
  `rtbp_tata_`. Free never reads them. Deactivating Pro leaves free fully working.
