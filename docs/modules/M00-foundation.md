# M00 — Foundation and design shell

| | |
|---|---|
| **Client module** | 18 (foundations part): language, currency, time zone, phone-ready, WooCommerce independence |
| **Features** | 18.1–18.7 (infrastructure only; M18 verifies them at the end) |
| **Depends on** | — |
| **Tier** | Free, plus T9 scaffolds the Pro and client add-on repos |
| **Engine** | no |
| **Critical review** | no |

## Goal

Turn the framework into the hotel product's base. Remove the example `Item` slice. Add the
helpers every module needs (money, dates, time zone, errors, transactions). Build the app shell,
the design tokens and the shared UI composites, and mount the staff app both in wp-admin and on a
front-end page. When M00 is done, a module can be added without touching any framework plumbing.

## Scope

| Feature | Summary | Task |
|---|---|---|
| — | Remove the `Item` example everywhere (PHP, JS, block, Elementor widget, email, template, test) | T1 |
| — | PHP minimum: the framework already uses PHP 8.0 syntax (`string\|array`, `int\|false`). Set `Requires PHP: 8.0` in the header, `composer.json` and `phpcs.xml` testVersion, and check the client host's PHP version | T1 |
| 18.2 | `Support\Money`: XOF formatting (`15 000 CFA`, symbol after, no decimals), rounding, sum. JS twin `formatMoney` | T2 |
| 18.3 | `Support\Dates`: the site time zone (Africa/Abidjan), local↔GMT conversion, `DateTimeImmutable` everywhere, day boundaries. JS twin `formatDate` / `formatTime` | T2 |
| — | `Exceptions\DomainException` (code + message + HTTP status + field errors) and translation to `ApiResponse` in `BaseController` | T3 |
| — | `Core\Database\Transaction::run( callable )`: `START TRANSACTION` / `COMMIT` / `ROLLBACK`, re-throw on failure. Tables forced to InnoDB | T3 |
| — | Reference generator `Support\Sequence`: a locked counter row in `rtbp_sequences`, used for booking refs and invoice numbers | T3 |
| — | `Setup\Scheduler`: registers and unregisters WP-Cron events (ADR-012) | T3 |
| — | `Storage\ProtectedFiles`: the protected uploads folder (ADR-009) and an authenticated download endpoint | T4 |
| 18.4 | `[rtbp_dashboard]` shortcode mounting the staff app on a front-end page. A login prompt for guests, and a capability check (ADR-003) | T5 |
| 18.5 | App shell: grouped sidebar, top bar, mobile Sheet and bottom tabs, a command palette stub, routes carrying `group` / `accessKey` / `hidden` | T6 |
| — | Design tokens (`--success`, `--warning`, `--info`), `src/lib/status.js`, `src/lib/format.js` | T6 |
| — | Composites: `PageHeader`, `DataTable` (with mobile cards), `FilterTabs`, `DateRangePicker`, `StatusBadge`, `Money`, `DateTime`, `EmptyState`, `ConfirmDialog`, `FormSection` / `Field` | T7 |
| — | Libraries: `@tanstack/react-query`, `react-hook-form`, `zod`, `sonner`, `react-day-picker` (ADR-013) | T7 |
| 18.1 | French: `fr_FR` `.po` scaffold, `bin/i18n-build.sh` wired, the plugin locale following the user's locale. The pipeline is verified with one screen | T8 |
| 18.6 | No WooCommerce dependency: a guard test that greps for `wc_` / `WC()` / `woocommerce` calls | T8 |
| — | Rename leftovers: CLAUDE.md, README and readme.txt descriptions still say "plugin hotel booking / boilerplate". Rewrite them as the product | T8 |

## Data

| Table | Purpose |
|---|---|
| `sequences` | `name` (PK), `value` BIGINT, `updated_at`. A counter row locked with `SELECT … FOR UPDATE` |

## Access

Capability `rtbp_access_dashboard` replaces the example capabilities (`MANAGE_ITEMS`). Fine-grained
access arrives in M13. Until then, every hotel endpoint checks this capability.

## Consumes / provides

- **Provides:** `Money`, `Dates`, `Transaction`, `Sequence`, `DomainException`, `Scheduler`,
  `ProtectedFiles`, the shell and composites, `src/lib/format.js`, `src/lib/status.js`, and the
  query client provider.

## Tasks

- [ ] T1 [Free] Remove the Item slice everywhere; update the Dashboard placeholder; set PHP 8.0 as the minimum; add the `ACCESS_DASHBOARD` capability; bump `ROLES_VERSION`
- [ ] T2 [Free] `Money` + `Dates` (PHP + JS twins), with table-driven unit tests: XOF format, rounding, local↔GMT, midnight, month end, leap day
- [ ] T3 [Free] `DomainException` → ApiResponse; `Transaction`; `Sequence` + `sequences` table (bump `DBVERSION`); `Scheduler`
- [ ] T4 [Free] `ProtectedFiles` + `GET /files/{token}` download endpoint
- [ ] T5 [Free] `[rtbp_dashboard]` shortcode + front-end mount of the admin bundle + login prompt
- [ ] T6 [Free] App shell (grouped sidebar, top bar, mobile Sheet + bottom tabs, palette stub), tokens, `status.js`, `format.js`
- [ ] T7 [Free] Composite components + react-query / RHF / zod / sonner / day-picker; a demo page under `#/dev/ui` (dev builds only)
- [ ] T8 [Free] French pipeline end to end; WooCommerce-independence guard test; `uninstall.php` honouring "delete data on uninstall"; rewrite CLAUDE.md, README and readme.txt descriptions; `Requires PHP: 8.0`
- [ ] T9 [Free+Pro+Client] Extension runtime (ADR-015): publish `window.rtbp` (`ui`, `lib`, `React`, `hooks`) and the JS filters `rtbp.admin.routes` / `rtbp.settings.sections` / `rtbp.api.error`. Scaffold the sibling repos `../radius-hotel-booking-pro` and `../radius-hotel-booking-residencetata`: bootstrap with a dependency check and admin notice, Composer PSR-4, webpack externals to `window.rtbp`, `readme.txt`, phpcs, `CLAUDE.md` pointing back to these docs, and git init. Each registers one demo route through `rtbp.admin.routes` to prove the seam. Run Plugin Check on free

## Acceptance

1. Activate on the Local site. No PHP notices, and no Item menu, block or widget remains.
2. Open the dashboard in wp-admin, and on a page containing `[rtbp_dashboard]`, at 1280 px and 360 px.
3. Switch the user's language to French. The shell renders in French.
4. `#/dev/ui` shows every composite in its loading, empty, error and populated states.
5. `npm run test:php` passes the Money / Dates / Sequence tests.

## Progress notes

- 2026-09-28: part of **T9** was done ahead of schedule, when the Pro plugin was created.
  - **Free:** `rtbp_admin_enqueue_scripts` action (`LoadAssets`); `window.rtbp` runtime
    (`src/lib/runtime.js`: `hooks`, `ui` with Badge, Button, Card*, Input, Label, Separator
    and Switch, `lib` with api and cn); `rtbp.settings.sections` filter in the Settings
    screen.
  - **Fix:** `src/api/client.js` no longer registers middleware on WordPress's shared
    `apiFetch`. Another plugin's root-URL middleware (Food Menu on the `food-menu.local` site)
    was hijacking our requests. Each request now carries its absolute URL and nonce.
  - **Pro repo:** `../radius-hotel-booking-pro` is scaffolded with the switchable feature
    system (ADR-017) and verified on `food-menu.local`.
  - **Still to do in T9:** `rtbp.admin.routes` in the app shell, more `ui` composites as they
    are built, a Pro Tailwind build with a shared preset (when the first Pro screen needs
    classes free does not compile), and the client add-on repo.
- 2026-09-28: most of **T6** was done, modelled on the Radius Booking admin
  (`/Users/habib/GithubProjects/radius-booking/radius-booking`, reference only).
  - **Shell:** `src/components/layout/` has AppShell, Sidebar (grouped nav, collapse
    remembered in localStorage, mobile drawer below 960px, user card) and Topbar (title and
    description from the route table, page actions, today's date in the site time zone).
  - **Routes:** `src/admin/routes.js` is the single route table for the router, sidebar and
    header, and runs the `rtbp.admin.routes` filter. Unbuilt screens render
    `ModulePlaceholder`, which names the module that replaces them.
  - **Tokens:** `src/index.css` tokens are scoped to `.rtbp-root`, and every brand shade is
    derived from `--primary` with color-mix. `Helpers\ThemeHelper` prints the saved
    Settings → Display colour inline, and `src/lib/theme.js` applies it live. The Settings
    screen has a `BrandColorField` with presets, and the logo (`components/brand/Logo`) follows
    the brand colour.
  - **Composites:** `components/common/` has EmptyState, Panel, StatCard, SegmentedControl and
    ModulePlaceholder. Button, Badge, Progress and Table use the tokens; opacity modifiers on
    var() colours were not generating CSS.
  - **Dashboard:** `GET dashboard/summary` (`DashboardController`) returns zeros plus a setup
    checklist and runs the `rtbp_dashboard_summary` filter. Each module fills its part in
    (M01 bookings, M05 payments, M06 rooms …).
  - **wp-admin:** a building menu icon (`assets/images/menu-icon.svg`) and real submenu
    entries. Items was removed from the menu; its code goes in T1.
  - **Still to do in T6:** the command palette stub and bottom tabs on mobile. The phone
    layout is not yet checked visually (the browser window could not be resized).
- 2026-09-28: JS linting (ESLint, `npm run lint:js`) was removed from the project at the user's request. PHPCS is the only linter.
