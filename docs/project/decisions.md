# Decisions

Architecture decision records (ADRs) and open questions. Add an ADR whenever a module makes a
choice that a later module has to live with. Never edit an accepted ADR; supersede it with a new one.

---

## Open: needs the client

| # | Question | Blocks | Default if unanswered |
|---|---|---|---|
| **D1** | Is payroll and HR (Module 15, 21 features) in scope? | M15 (client add-on), the pay fields of M12 and the payslips in M16 | Build M12 without the pay fields, and hold M15 and the payslip parts of M16 until answered. **2026-09-30: M12 started with the default — pay fields (12.12–12.15) deferred** |
| **D2** | Is the restaurant "Food Order and Inventory" area in scope? | Nothing | **Out of scope.** Keep the sidebar link to the existing system |
| **D3** | Online payment (Wave / Orange Money) later? | Nothing in this build | Design `payments` so a gateway can attach later (ADR-010) |
| D4 | Buffer time between two stays in the same room (cleaning)? | M08 | 0 minutes, configurable per room type in Settings |
| D5 | Does a *pending* (unapproved) booking hold its room? | M08 | **Yes.** The legacy system does this, and feature 2.13 requires it |
| D6 | Payment deadline default | M05 | 24 hours after booking, capped at 2 hours before arrival |
| D7 | Old 8-digit **landline** numbers on file (first digit 2 or 3): which new prefix (21 Moov, 25 MTN, 27 Orange) does each map to? | Nothing (M09 matches them by their last 8 digits) | Not converted: kept as typed, no E.164, matched by tail; the import (M18) can map them once answered |

## Open: internal

| # | Question | Default |
|---|---|---|
| Q1 | Mount inside the Food Menu Pro front-end dashboard, or our own shell at the same URL? | Our own shell, mounted by shortcode on the existing `/hotel-dashboard` page (ADR-003) |
| Q2 | When does the legacy data import run? | Last module (M18), with a dry-run available from M09 onward for early testing |

---

## ADR-001: Own tables, no WooCommerce — Accepted

A booking is a row in `bookings`, with one row per physical room in `booking_rooms`. Rooms are
not products. The plugin never reads or writes WooCommerce data. WooCommerce stays installed for
the restaurant.
*Why:* this fixes the root defect of the legacy system (a stay stored as an order line) and is
what the client signed up for (feature 18.6, 18.7).

## ADR-002: Terminology — Accepted

| In code and UI | Client email / feature list | Booking-flow diagram | Legacy code |
|---|---|---|---|
| **Room type** (`room_types`) | room type (*Standard Room*, *Room VIP*) | Package | `accommodation` product |
| **Rate plan** (`rate_plans`) | rate plan / stay window | Time schedule | `rate_plan` term |
| **Room type rate** (`room_type_rates`) | price per rate plan per room type | — | per-product rate meta |
| **Room** (`rooms`) | room number | Assigned room | `_hbfwc_room_numbers` |
| **Floor** (`floors`) | floor | Floor | settings list |
| **Booking line** (`booking_rooms`) | a room on a booking | — | order line item |

The diagram's word *package* means room type. A room type **owns** its rooms exclusively: a
room appears under exactly one room type, even when two room types sell the same rate plan.
The UI may later relabel "Room type" to "Package" through a translation string. Code never does.

## ADR-003: One staff app, two mount points — Accepted

The admin bundle mounts in wp-admin and also on any front-end page that carries
`[rtbp_dashboard]` (for logged-in staff; guests see a login prompt). The client keeps the
`/hotel-dashboard` address. The app uses HashRouter in both mounts.
*Why:* the client email promises "same address, same menu", and depending on Food Menu Pro's
hard-coded dashboard routes would couple two release cycles.
*Consequence:* the front-end mount enqueues the admin bundle and its CSS on that page only.
`.rtbp-root` scoping keeps the theme's CSS out and ours in.

## ADR-004: Access control layered on one capability — Accepted

WordPress capabilities answer only "may this person enter the hotel dashboard"
(`rtbp_view_dashboard`). Everything finer is Module 13's **access map**: every page and action
key is set to `open`, `passcode` or `locked`. The effective level for a user is
`user override → access role → global default`. It is enforced by `AccessMiddleware` on the
server. Administrators are never *locked* out, but *passcode* still applies to them through the
fallback PIN (feature 13.7).
*Why:* the client's three-level model does not fit boolean WordPress capabilities, and per-person
overrides do not fit roles.

## ADR-005: PDF generation — Accepted

Render invoices, receipts and payslips from PHP templates to HTML, then to PDF with `dompdf`,
installed through Composer and prefixed with PHP-Scoper so it cannot clash with another plugin's
copy. Store the generated files in the protected uploads folder (ADR-009). The browser print view
is always available as a fallback.

**Accepted (M05 T7, 2026-10-01), as built:** Pro renders the free plugin's own document HTML
(`DocumentService::html()`), so the PDF and the print view cannot differ. dompdf 3.1 lives in
Pro's `scoper/` Composer project and is prefixed into `vendor-prefixed/` under
`RadiusTheme\RadiusHotelBookingPro\Vendor` by `composer scope` (gitignored, built for the release
zip; PHP-Scoper needs PHP 8.2–8.4 and silently prefixes nothing on 8.5, so the script checks its
output). dompdf builds some class names from strings, which PHP-Scoper misses or over-prefixes;
`scoper/scoper.inc.php` patches each known case, and they must be re-checked after a dompdf update.
The library loads only when a PDF is made. Remote files, PHP and JS are off; the logo is embedded.
**Deviation:** PDFs are **not stored**. They are rendered on request (fast, always current) and
e-mail copies are temporary files deleted after sending, so ADR-009 storage is not needed.

## ADR-006: New-booking alerts by polling — Accepted

`GET /notifications/poll?since=<cursor>` every 30 s while the tab is visible. The query hits an
indexed `created_at` column and returns in a few milliseconds.
*Why not the Heartbeat API:* it is not loaded on front-end pages, and its 15–60 s cadence cannot be
controlled from our side. *Why not WebSockets:* no persistent server on shared WordPress hosting.

## ADR-007: Time is site-local plus GMT — Accepted

Stay boundaries are stored as `DATETIME` in site-local time (`start_at`, `end_at`) **and** in
GMT (`start_at_gmt`, `end_at_gmt`). Availability compares GMT. Display uses local time. Abidjan is
UTC+0 with no DST today, but the code must not depend on that. See booking-engine §3.

## ADR-008: Double-booking prevention — Accepted

Three layers: (1) the availability query excludes overlapping lines, holds and blocks;
(2) the write path locks the room rows (`SELECT … FOR UPDATE`) inside a transaction and
re-checks the overlap before inserting; (3) a concurrency check (two parallel `wp eval`
processes, run whenever the write path changes) proves exactly one of two parallel requests wins. See booking-engine §7.

## ADR-009: Protected file storage — Accepted

Exports, archives, invoices and employee documents are written to
`wp-content/uploads/radius-hotel-booking/<kind>/` with a deny-all `.htaccess`, an `index.php`,
and random file names. Files are served only through an authenticated REST download endpoint that
checks the access key.
*Amendment (M00 T4, 2026-09-28):* nginx ignores `.htaccess`, and on the nginx Local site the
direct URL answered 200. The protection there is the 128-bit random file name, unless the host
does one of these:
(a) adds a deny rule:
    `location ^~ /wp-content/uploads/radius-hotel-booking/ { deny all; return 404; }`
(b) sets `RTBP_PROTECTED_DIR` in wp-config.php to a folder outside the web root.
The admin notes and the cutover runbook (M18) must include this. A Site Health check that warns
when the folder is publicly reachable is a follow-up for M18 T1.

## ADR-010: Payment is a ledger — Accepted

`payments` rows are append-only: amount, method, reference, staff, time. A correction is a new
row with `type = adjustment` or `refund`, never an update. `bookings.paid_total` and
`bookings.payment_status` are derived from the ledger by `PaymentService::recalculate()`. An
online gateway can later write the same rows (D3).

## ADR-011: The activity log is append-only and tamper-evident — Accepted (Pro, see ADR-014)

No update or delete path exists in the API or the repository. Each row stores
`hash = sha256(prev_hash || canonical_row)`. Purging after an archive is the only removal, it
removes a contiguous oldest range, and it records the archive file's final hash as the new chain
anchor (feature 14.18).

## ADR-012: Scheduled work uses WP-Cron — Accepted

Hold expiry, payment-deadline release, scheduled exports and log archiving run on WP-Cron events
registered by `Setup\Scheduler`. Each job is idempotent and safe to run twice. The admin notes
recommend a real system cron calling `wp-cron.php`. There is no Action Scheduler dependency,
because that package comes from WooCommerce.

## ADR-013: Server state in React — Accepted

Use `@tanstack/react-query` for fetching, caching and invalidating server data. Use React local
state for UI only. No Redux, no `@wordpress/data` stores for domain data.

## ADR-014: Three plugins — Free, Pro, client add-on — Accepted (2026-09-28)

| Plugin | Contains |
|---|---|
| **Free** `radius-hotel-booking` (wordpress.org) | The complete core hotel: inventory, rate plans at base, sale and date-override price, the availability engine, calendar and blocks, guests, desk and public booking, the booking record, manual payment with invoice numbers and a print view, the dashboard, basic reports, basic access control (open/locked per access key per role), settings, and translatable strings |
| **Pro** `radius-hotel-booking-pro` | Advanced pricing rules (seasonal, occupancy, early-bird, last-minute) · iCal sync · the **activity log** (M14) · the passcode level, PINs, custom access roles, per-person overrides and profile-field control · PDF invoices and receipts, and the overdue auto-release · advanced reports (occupancy %, revenue breakdowns, XLSX) · scheduled exports and archive-and-remove · staff records (M12) · staff self-service (M16) |
| **Client add-on** `radius-hotel-booking-residencetata` | Côte d'Ivoire payroll (M15), the legacy data import (M18), and the client's defaults and branding |

The per-module split is the **Tier** line in each module doc and the Tier column in `roadmap.md`.

*Why:* wordpress.org needs a free plugin that is useful on its own, with no locked code. A Pro
plugin needs features worth paying for. Code specific to one country or one client belongs in
neither.
*Rules:*
1. The free plugin contains no Pro code, not even disabled. It exposes hooks, registries and JS
   extension points, and nothing else.
2. Pro requires Free, and the client add-on requires Free + Pro. Each checks for its dependency
   on `plugins_loaded` (priority 20), shows an admin notice when it is missing, and does nothing
   else.
3. Add-ons reuse the free framework (`Abstracts`, `Container`, `Schema`, the router, `window.rtbp`)
   and never copy it. Tables are registered through `rtbp_migration_classes`, routes through
   `rtbp_register_addon_routes`, and settings sections through `SettingsSchema::register()`.
4. Each plugin keeps its own `readme.txt` changelog, `DBVERSION` and text domain
   (`radius-hotel-booking-pro`, `radius-hotel-booking-residencetata`).
5. **When Pro is deactivated, everything free keeps working.** Pro-only data stays in Pro's tables
   and is never required by free code.
6. The client receives all three plugins. The free plugin's release cycle is never held up by
   Pro or client work.

## ADR-015: Extension points between the plugins — Accepted (2026-09-28)

**PHP.** Free defines the seam, and Pro fills it:

| Seam (free) | Pro / client use |
|---|---|
| `rtbp_activity( $action, $subject, array $context )` (**live**, M14 T1): builds a normalised event (actor, IP, user agent, subject, before/after diff with secrets masked) and fires `do_action( 'rtbp_activity', $event )`. **Stores nothing**. Also `ActionCatalog` + `rtbp_activity_actions`, `rtbp_activity_event` (alter/drop), `rtbp_activity_ip`, `rtbp_activity_secret_pattern` | Pro's `ActivityLogger` stores it (hash chain, grouping, archive) |
| `AccessRegistry` + `rtbp_access_keys` filter (and `register()` from `rtbp_access_registry_init`, groups via `rtbp_access_groups`); `Access::level()` passes through the `rtbp_access_level` filter `( $level, $key, $user, $definition )`; `AccessMiddleware` handles `open` / `locked`, and hands `passcode` to `rtbp_access_passcode_check` `( null, $key, $request\|null )` → `true` or a `WP_Error` (null = locked). Also `rtbp_access_role_defaults`, `rtbp_access_locked_message`, and the actions `rtbp_access_denied`, `rtbp_page_viewed`, `rtbp_access_changed( $scope, {before, after}, $user_id )` (**live**, M13 T1–T3) | Pro adds the `passcode` level, PIN verification, custom roles and per-person overrides. **Live (M13 T4a):** Pro answers `rtbp_access_passcode_check` from the `X-RTBP-Passcode` header, fires `rtbp_passcode_failed( $user_id, $key, $failures, $locked )` and `rtbp_pro_pin_changed( $user_id, $removed )` |
| `PriceResolver` steps + the `rtbp_price_steps` filter | Pro adds the seasonal, occupancy and booking-window steps |
| `rtbp_document_renderers` (invoice, receipt) with an HTML print view in free (**live**, M05 T4b) | Pro adds the PDF renderer (**live**, M05 T7: `pdf`, and the PDFs attached through `rtbp_be_before_send_email_{id}`) |
| `BookingStatusService::release( $id, $reason, $only_if )` (**live**, M05 T6; `$only_if` M05 T8): releases an overdue booking under the lock, logged `bookings.release_overdue`; `$only_if` adds conditions checked under the same lock — `as_of` (overdue already at that time) and `unless_held` (409 `on_hold`) | Pro's automatic release (M05 T8) runs it with no user signed in (logged as system), a grace period and `unless_held`; an older free ignores the third argument |
| `rtbp_report_definitions`, the `rtbp_export_formats` filter (CSV in free) | Pro adds reports, XLSX, schedules and archiving |
| `rtbp_block_sources` filter (**live**, M08 T5b): `key => { label, editable }`, default `manual` (Staff, editable). Blocks of a non-editable source are listed with its label but refused on update/delete (409 `block_read_only`). Also the action `rtbp_block_changed( $id, create\|update\|delete )` for staff blocks | Pro adds `ical` (not editable) and writes its blocks through the free `BlockRepository` with `source = ical`, `feed_id`, `external_uid`; it reconciles with **`BlockRepository::forFeed( $source, $feed_id )`** (**live**, M08 T6b) → UID => row. The JS status palette gained a generic `sync` domain (`ok`, `warning`, `error`, `never`) for `StatusBadge` |
| `rtbp_note_types` filter (**live**, M09 T4): `type => { read, add, edit, remove (access keys), find( $id ) → activity subject or null, activity (prefix of `<prefix>_add|_edit|_remove`) }`; free registers `guest`. The generic `notes` API resolves its access keys from it, and an unregistered type is refused. JS: `<NotesPanel type id />` (also on `window.rtbp.ui`) | M03 registers `booking`, M12 (Pro) `employee`, each with its own keys and catalogue actions |
| `SettingsSchema::register()`, `rtbp_migration_classes`, `rtbp_register_addon_routes`, `rtbp_email_classes` | registering an add-on's own sections, tables, routes and e-mails. Migration keys carry the **free** DB version, so an add-on also re-runs its own tables when its own DB version changes (Pro: `Databases\Installer`, M13 T5a) |
| `rtbp_settings` filter (**live**) + the generic `GET/PUT settings/{section}` | an add-on settings section with defaults and storage (`rtbp_<section>_settings`) |
| `rtbp_admin_enqueue_scripts( $handle )` action (**live**) | enqueuing an add-on bundle after the free admin app, with `$handle` as a dependency |

**JavaScript.** The free admin bundle publishes a runtime on `window.rtbp` (**live**):

- `ui`: the shadcn primitives and composites. Dialog, DropdownMenu, Select and Tabs parts, plus
  DataTable, DateRangePicker, ConfirmDialog and NotesPanel (M09), are `React.lazy` stand-ins under their usual names.
  They load as one chunk on first render (eager, they would add about 220 KB to every page), so
  wrap them in a `Suspense`.
- `lib`: format, status, the API client, the shared `queryClient`, `toast`, `usePageActions`, and
  `loadForms()`, a promise of `{ useZodForm, applyServerErrors, z }`.
- `ReactQuery`: map `@tanstack/react-query` to it so there is one cache.
- `hooks`: `@wordpress/hooks`.
- `React`: the same object as WordPress's shared `react` script.

Add-on bundles import them through webpack externals (`@rtbp/ui` → `window.rtbp.ui` …), so there
is one React and one copy of the components. The free app mounts on `DOMContentLoaded`, after every
add-on script has registered its filters. Filters:

| JS filter | Use |
|---|---|
| `rtbp.admin.routes` (**live**) | Add screens and sidebar items |
| `rtbp.settings.sections` (**live**) | Add settings tabs: `{ key, label, render( { value, setField, save, saving } ) }`, where `key` is a PHP section from `rtbp_settings` |
| `rtbp.booking.panels`, `rtbp.guest.panels` (contract fixed M14 T3b; host side in M03 / M09) | `applyFilters( 'rtbp.booking.panels', [], { booking } )` (guest: `{ guest }`) returns `{ key, label, order, render() }[]`; the host renders them sorted by `order`. Pro adds the activity timeline (`activity`, order 90) |
| `window.rtbp.lib.access` (**live**) | `useAccess( key )`, `useAccessMap()`, `canAccess( keys )`, `refreshAccess()`. A refused request's error carries `error.data.key` |
| `window.rtbp.ui.NumberInput`, `window.rtbp.ui.ToggleRow` (**live**, M13 T4b) | The Settings field rows, for add-on settings tabs |
| `window.rtbp.ui` Sheet parts, `PriceBreakdown`, `PriceBreakdownPopover`, `GalleryField`, `TagInput` (**live**, M06–M07; Sheet and PriceBreakdown lazy) | Side-panel editors (`Sheet`, `SheetContent`, `SheetHeader`, `SheetFooter`, `SheetTitle`, `SheetDescription`, `SheetClose`; wrap in `Suspense`) and the pricing breakdown, for add-on screens. Free main after M07 T5b; an add-on falls back to the Dialog parts when `Sheet` is missing |
| `window.rtbp.ui.CalendarGrid` (**live**, M08 T4b; eager) | Dates × rows with sticky headers: `dates`, `today`, `rows[{ key, label, sublabel, cells }]`, `renderCell`, `onCellClick`, `cellClassName`, `cellLabel`, `isDisabled`. For add-on calendars (Pro's iCal overlay, reports). Free main after M08 T4b; guard with `window.rtbp?.ui?.CalendarGrid` |
| `window.rtbp.router` (**live**, M13 T5b) | The app's react-router (`Link`, `NavLink`, `Navigate`, `useLocation`, `useNavigate`, `useParams`, `useSearchParams`); add-ons map `react-router-dom` to it in webpack externals |
| `rtbp_settings_access_key` (**live**, M13 review) + `AccessRegistry::settingsKey()` | The access key guarding a settings section's writes; Pro routes `pro_access` and `pro_features` to `access.manage`. A passcode refusal lists every pending key in `data.keys` so one PIN unlocks them |
| Tailwind `safelist` in free (**live**, M13 T6) | Add-on bundles are not scanned by the free Tailwind build: a class an add-on needs that free stops using disappears. Responsive row classes add-ons rely on are safelisted in `tailwind.config.js`; check new add-on classes against `build/*.css` |
| `rtbp.access.levels` (**live**, M13 T5b) | The level options of the free permission matrix; the PHP twin `rtbp_access_role_levels` is the server allow-list |
| `rtbp.api.error` (**live**) | `( handled, error, { path, options, retry } )`: return a Promise to take over the request (e.g. Pro turns `passcode_required` into its PIN dialog, then `retry( { headers } )`), or pass `handled` through to let the error throw. A retried request does not run the filter again |
| `rtbp.dashboard.widgets`, `rtbp.reports.tabs` | Add dashboard widgets and report tabs |

The add-on scripts declare `radius-hotel-booking-admin` as a dependency, so they load after the
runtime.

## ADR-016: wordpress.org compliance for the free plugin — Accepted (2026-09-28)

The free plugin must pass the Plugin Directory guidelines and Plugin Check (`wp plugin check`):

- **No locked features, no trial periods, no disabled Pro code.** Upsells are allowed only as one
  dismissible notice on the plugin's own Settings screen plus an "Upgrade" link. They never
  appear inside the working screens.
- **No external calls without consent.** No CDN assets, no remote fonts, no tracking. The
  licence and update checks live in Pro only.
- Everything GPL-compatible. Third-party libraries are vendored with their licences and prefixed
  (PHP-Scoper).
- The `readme.txt` headers are accurate (`Requires PHP: 8.0`, `Tested up to`, the stable tag),
  with a short description and no keyword stuffing.
- Everything is escaped on output and sanitised on input, all SQL is prepared, every endpoint is
  capability-checked, and nonces are used on every state change.
- Prefixes and slugs are unique (`rtbp_`, `radius-hotel-booking`). No functions or classes are
  defined in the global namespace without the prefix.
- Data is only removed on uninstall when the "delete data on uninstall" setting is on.

## ADR-017: Every Pro feature is switchable — Accepted (2026-09-28)

The Pro plugin (`../radius-hotel-booking-pro`) lets the site owner switch each Pro feature on or
off in **Settings → Pro features**, a tab the Pro plugin adds to the free plugin's Settings screen
through `rtbp.settings.sections`. The tab is managed from the free app; Pro has no screen of its
own.

- **Catalogue:** `Features\FeatureRegistry` (Pro) lists every feature: key, module, class,
  requirements and default. The initial keys are `activity_log`, `advanced_access`,
  `pricing_rules`, `ical_sync`, `pdf_documents`, `auto_release` (off by default),
  `advanced_reports`, `advanced_exports`, `staff_records`, and `staff_self_service` (requires
  `staff_records`). A client add-on can add features through `rtbp_pro_features`.
- **Storage:** option `rtbp_pro_features_settings` (the `pro_features` section registered on
  `rtbp_settings`, and saved by the free `PUT settings/pro_features` endpoint). It is sanitised
  on every write: booleans only, known keys only, and a feature whose requirement is off is
  switched off too.
- **Runtime:** `FeatureManager` boots a feature only when it is `active`, meaning switched on,
  its class built, its requirements active, and the free symbols it needs present. The other
  states are `disabled`, `blocked`, `needs_free` and `in_development`. A switched-off feature
  registers nothing: no hooks, routes, screens or cron. Its data and tables stay.
- **Tables** of Pro features are registered regardless of the switch, so turning a feature back
  on never needs a migration.
- A change fires `rtbp_pro_features_changed( $before, $after )` and emits an `rtbp_activity`
  event `settings.pro_features` once the M14 free emitter exists.
- Pro and client code check a feature with `rtbp_pro_feature_active( $key )`. **The free plugin
  never checks it** (ADR-014).
- Each module's `[Pro]` task creates `includes/Features/<Area>/<Name>Feature.php` for its feature
  key. Until then, the feature shows as *In development*.

## ADR-020: The activity log is an HMAC chain with a sealed head — Accepted (2026-09-29, M14 review)

Refines ADR-011. Rows are chained with HMAC-SHA256 under a key kept outside the database
(`RTBP_PRO_LOG_KEY` in wp-config.php, else a key file in protected storage; never the WP salts).
One head record (last id, last hash, live rows, anchor), sealed with the same key, is locked for
every write (`SELECT … FOR UPDATE`), so writers never fork the chain and `log:verify` can check
both ends. Purges delete in batches that move the anchor inside the same transaction.
*Why:* an unkeyed SHA-256 chain can be recomputed by anyone with database access, and without a
head record deleting the newest rows or emptying the table goes unnoticed.
*Consequence:* the key must be backed up with the site; losing it makes the existing chain
unverifiable (new rows chain on).

## ADR-018: No automated test suite, no JS linting — Accepted (2026-09-28)

The owner does not want PHPUnit tests or ESLint in the project. Both are removed: the `tests/`
folder, `phpunit.xml.dist`, the PHPUnit Composer packages, and the `test:php` / `lint:js`
scripts. PHPCS stays as the only linter.
*Consequence:* the guarantees that tests would have given are now checked on the Local site
(`conventions.md` §4). That covers the booking-engine edge cases (§10), the concurrency check
(ADR-008), pricing and payroll maths. The results are recorded in each module's Progress notes.
Money, availability and payroll code still goes through the `rtbp-critical-reviewer` agent.

## ADR-019: English only; languages come from translation plugins — Accepted (2026-09-28)

The owner decided that the plugin is built in English only. Every string, in PHP and JS, is
wrapped for translation (`__()` and friends, text domain `radius-hotel-booking`), and the `.pot`
is kept current. French, or any other language, is added later by the client through a
translation plugin (Loco Translate for the interface; WPML or Polylang for multilingual content).
The plugin does not build its own language layer.

*Consequences:*

- **No per-language fields.** Room type and rate-plan names, rate-plan features and policy,
  payment instructions, the invoice footer and the locked message are plain text columns and
  settings, not `{ en, fr }` JSON. The `translatable` settings type and the planned
  `TranslatableField` component are dropped (M17).
- **No in-plugin language switch** and no `guests.language` column. The interface follows the
  WordPress site or user locale; e-mails and PDFs use the site locale.
- **No French catalogue maintained per module.** The `fr_FR` files shipped in M00 T8 stay as
  they are, but modules no longer update them. Acceptance runs in English.
- Layouts still leave room for longer translations (no fixed-width buttons).
- The client documents in `docs/requiremetnt/` promise French and English. This decision meets
  that through a translation plugin, not bundled French. Confirm with the client before handover.

## ADR-021: The free plugin seeds generic rate plans, not the client's — Accepted (2026-09-29, M07)

The owner decided that a fresh install of the free plugin gets a small **generic starter set**
of rate plans, created only when the `rate_plans` table is empty: *Half Day* (fixed 08:30 →
17:00), *Overnight* (fixed 20:00 → 08:00, multi-unit) and *24 Hours Flexible* (24 h, check-in
any time, multi-unit). The free plugin ships to wordpress.org, so it does not carry Residence
TATA's own list.

*Consequences:*

- The client's seven plans (booking-engine §2) reach their site through the **M18 import** of the
  legacy `rate_plan` terms, which removes duplicates by type and times, so the starter plans are
  matched, not doubled.
- `StayWindow` is still verified against all seven client windows (T1), since those are the
  windows the engine must get right.
- The starter plans are ordinary rows: the hotel can rename, change or deactivate them. Seeding
  never runs again once the table has rows (it does not recreate a deleted plan).

## ADR-022: The booking tables are created with the engine (M08), not with the booking form (M02) — Accepted (2026-09-29, M08)

The availability engine reads booking lines (booking-engine §4, §5.3), and its edge-case checks
(§10 rows 9, 15, 16) need real lines. M08 T1 therefore creates the `bookings` and
`booking_rooms` tables exactly as architecture §4.4 defines them, together with `holds` and
`blocks`.

*Consequences:*

- M08 owns only the schema and the read side (`AvailabilityRepository`). M02 adds the models,
  `BookingService` and the screens, and must not recreate or re-declare the tables; a column it
  needs is added by an ordinary schema change and a `DBVERSION` bump.
- The M08 checks insert test lines with plain SQL and remove them afterwards.
