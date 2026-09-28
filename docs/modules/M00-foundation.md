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

Capability `rtbp_view_dashboard` replaces the example capabilities (`MANAGE_ITEMS`). Fine-grained
access arrives in M13. Until then, every hotel endpoint checks this capability.

## Consumes / provides

- **Provides:** `Money`, `Dates`, `Transaction`, `Sequence`, `DomainException`, `Scheduler`,
  `ProtectedFiles`, the shell and composites, `src/lib/format.js`, `src/lib/status.js`, and the
  query client provider.

## Tasks

- [x] T1 [Free] Remove the Item slice everywhere (model, table, repo, service, resource, controller, routes, bindings, events, capability, shortcode, block, Elementor widget, email, template, test, JS module) and the CLAUDE.md lines about the Item template; set PHP 8.0 as the minimum (header, composer.json, phpcs testVersion, readme.txt); add the `VIEW_DASHBOARD` capability; bump `ROLES_VERSION` — done 2026-09-28
- [x] T2 [Free] `Money` + `Dates` (PHP), verified on the Local site (XOF format, rounding, local↔GMT, midnight, month end, leap day), plus the JS twins in `src/lib/format.js` (`formatMoney`, `formatDate`, `formatTime`, `formatDateRange`), localised currency and date settings — done 2026-09-28
- [x] T3 [Free] `DomainException` → ApiResponse; `Transaction`; `Sequence` + `sequences` table (bump `DBVERSION`); `Scheduler` — done 2026-09-28
- [x] T4 [Free] `ProtectedFiles` + `GET /files/{token}` download endpoint — done 2026-09-28
- [x] T5 [Free] `[rtbp_dashboard]` shortcode + front-end mount of the admin bundle + login prompt — done 2026-09-28
- [x] T6 [Free] Finish the shell (most of it is built; see Progress notes): `src/lib/status.js` + `StatusBadge`, mobile bottom tabs for the front-desk routes, a ⌘K command-palette stub (navigation only), and a visual check at 360 px — done 2026-09-28
- [x] T7a [Free] Libraries: react-query (with the provider in the shell), react-hook-form, zod, sonner (with the toaster); `ConfirmDialog` (optional required reason); `FormSection` / `Field` wired to API field errors — done 2026-09-28
- [x] T7b [Free] `DataTable` (server pagination, sorting, row actions, mobile cards), `FilterTabs` with counts, `DateRangePicker` (react-day-picker, presets, arrival/creation mode) — done 2026-09-28
- [x] T7c [Free] `Money` / `DateTime` display components + the `#/dev/ui` demo page (dev builds only) showing every composite in loading, empty, error and populated states — done 2026-09-28
- [x] T8 [Free] French pipeline end to end; WooCommerce-independence guard test; `uninstall.php` honouring "delete data on uninstall"; rewrite the README and readme.txt descriptions as the product — done 2026-09-28
- [x] T9 [Free+Pro+Client] Finish the extension runtime (the Pro repo and the first seams exist; see Progress notes): the `rtbp.api.error` filter in the API client; `React` and the new composites on `window.rtbp`; scaffold `../radius-hotel-booking-residencetata` (the same bootstrap as Pro, requiring free + Pro); a demo route in Pro and in the client add-on through `rtbp.admin.routes`; run Plugin Check on free — done 2026-09-28

**Deferred:** 18.7 (bookings are real records) is delivered by M02/M03. M00 only guarantees there
is no WooCommerce dependency. `PageHeader` is dropped: the shell's Topbar and `usePageActions()`
already cover it.

## Acceptance

1. Activate on the Local site. No PHP notices, and no Item menu, block or widget remains.
2. Open the dashboard in wp-admin, and on a page containing `[rtbp_dashboard]`, at 1280 px and 360 px.
3. Switch the user's language to French. The shell renders in French.
4. `#/dev/ui` shows every composite in its loading, empty, error and populated states.
5. A `wp eval` check of Money, Dates and Sequence gives the expected values (recorded in Progress notes).

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
- 2026-09-28 **T1 done.**
  - The Item slice is gone from PHP, JS, templates and tests. The block, shortcode, Elementor,
    page-installer and e-mail managers stay as empty scaffolding with filters
    (`rtbp_register_blocks`, `rtbp_shortcodes`, `rtbp_register_elementor_widgets`,
    `rtbp_install_pages`, `rtbp_email_classes`) for M04 and M05 to fill.
  - The public bundle now loads for any `rtbp_shortcodes` tag or any
    `radius-hotel-booking/*` block.
  - The site app renders nothing until M04.
  - Capability: the code keeps `Capabilities::VIEW_DASHBOARD` (`rtbp_view_dashboard`). The docs
    said `ACCESS_DASHBOARD` and are corrected. `Capabilities::retired()` lets the role sync strip
    `rtbp_manage_items`; `ROLES_VERSION` is now 2.
  - PHP 8.0 minimum in the plugin header, composer.json (lock hash refreshed), phpcs testVersion,
    readme.txt and CLAUDE.md.
  - Leftover on existing dev sites: the old `…_items` table is not dropped, which is harmless.
  - README.md still describes the Item example and is rewritten in T8.
  - Verified on food-menu.local: roles re-synced, the dashboard and settings load, and the
    `items` endpoint is gone.
- 2026-09-28 **T2 done.**
  - **PHP:** `Support\Money` (config from Settings → General, `to_minor`/`from_minor` integer
    arithmetic, `round`, `sum`, `equals`, `format` with 4 symbol positions) and
    `Support\Dates` (strict parse, site zone, `at`, `start_of_day`, `end_of_day` as the
    exclusive next midnight, calendar `add_days`, `pair` local+gmt, ISO in/out, `is_date`,
    `format` via wp_date).
  - **Settings defaults** in `SettingsHelper::general()`: USD for the wordpress.org plugin, and
    date/time defaults from WordPress's own settings. The client's XOF / CFA / 24 h / d/m/Y
    defaults belong in the client add-on (ADR-014). The Settings UI for the currency fields is
    added in M17 T3.
  - **JS:** `src/lib/format.js` has `formatMoney`, `formatDate`, `formatTime`,
    `formatDateTime`, `formatDateRange`, with PHP date-format tokens rendered in the site zone
    via Intl. It is localised as `format` on both apps, exposed as `window.rtbp.lib.format`.
  - **Bug found and fixed while verifying:** `Money::round( 1.005 )` returned 1.00 (float
    scaling). It now rounds at precision before scaling.
  - **Verified on food-menu.local:** 30/30 PHP checks (`wp eval-file`: USD and XOF formats,
    rounding, sums, Europe/Paris DST crossing, leap day, month end, strict parsing) and 17/17
    JS checks in the browser (the same money cases, ISO→site zone, DST, local strings, ranges,
    French long dates, 12 h time).
  - `Core\Support\DateTimeParser` (a lenient, mutable framework class, unused) is left as
    is. New code uses `Dates`.
- 2026-09-28 **T3 done.**
  - `Exceptions\DomainException` (code, status, field errors, context; `notFound`, `conflict`,
    `invalid`).
  - `ApiResponse` gains an optional `code`, `withCode()` and `fromThrowable()`. Unexpected
    errors are a 500 `server_error` with the real message only under WP_DEBUG, and they are
    always written to the error log. BaseController's catch blocks use it, and the JS client
    sets `error.code`.
  - `Core\Database\Transaction`: nested calls join the outer transaction; `afterCommit()` is
    dropped on rollback.
  - `Support\Sequence` on the new `sequences` table (DBVERSION 1.0.1): an atomic
    `LAST_INSERT_ID( value + 1 )` counter that is gap-free inside a transaction.
  - `Setup\Scheduler`: the `rtbp_scheduled_events` registry, 5 and 15 minute recurrences, an
    overlap lock with stale-lock takeover, retired jobs unscheduled, everything cleared on
    deactivation.
  - **Bug found by the concurrency check and fixed:** `Sequence::next()` ran INSERT IGNORE
    before UPDATE, and two concurrent transactions deadlocked on the shared→exclusive lock
    upgrade. It now runs UPDATE first and inserts only on a counter's first use. A race on
    that very first use could still deadlock once, which is acceptable for a one-off per
    counter.
  - **Verified on food-menu.local:** 25/25 functional checks (the table exists and is InnoDB,
    migrations re-run cleanly, counters, gap-free after rollback, nested rollback,
    afterCommit, the error envelope, the scheduler lock and retirement). The concurrency check
    ran two parallel processes × 50 numbers, three times: every run interleaved, and each gave
    exactly 1..100 with no duplicates or gaps.
- 2026-09-28 **T4 done.**
  - `Storage\ProtectedFiles`: `put`, `put_file` (copy or move), `get`, `path`, `delete`,
    `can_access`, `url`, and `ensure_protected_dir` (`.htaccess`, `web.config`,
    `index.php`). Files are indexed in the new `files` table (StoredFile model and
    repository; DBVERSION 1.0.2).
  - **Kinds** are registered with the `rtbp_file_kinds` filter (kind => capability). An unknown
    kind is refused. Access goes through the `rtbp_file_access` filter, which M13 uses to map
    kinds to access keys.
  - `GET /files/{token}` (FileController) authenticates with `_wpnonce` because it serves plain
    links. Unknown, forbidden and missing files all answer 404 `file_not_found`. Files stream
    with attachment or inline disposition, a UTF-8 name, `nosniff` and a sandbox CSP; the
    `rtbp_file_download` action fires for M14.
  - JS: `fileUrl( token, inline )` in `src/api/client.js`, also on `window.rtbp.lib.api`.
  - Download names keep accents ("Reçu 1.pdf"); only unsafe characters are replaced.
  - **Finding:** nginx ignores `.htaccess`, and the direct uploads URL answered 200 on the
    Local site. ADR-009 is amended (a deny rule or `RTBP_PROTECTED_DIR` outside the web root),
    and a Site Health warning is a follow-up for M18 T1.
  - **phpcs:** `WordPress.Security.EscapeOutput.ExceptionNotEscaped` is excluded in
    phpcs.xml, because exception messages go out as JSON, never HTML. The T3 doc comments were
    fixed: my T3 lint check read the tail of the report and missed them. `/next-task` now
    checks the phpcs exit code.
  - **Verified on food-menu.local:**
    - 20/20 wp-eval checks: the table, random names in kind folders, guards, size and sha256,
      MIME, token validation, access for admin / subscriber / logged out, unknown kind,
      move, delete.
    - HTTP: logged out 401; logged in without nonce 401; with nonce 200 and the right bytes
      and headers; inline disposition; unknown file 404 `file_not_found`; directory listing
      403.
    - `RTBP_PROTECTED_DIR` stores outside the web root.
    - Test data removed afterwards.
- 2026-09-28 **T5 done** (18.4, ADR-003).
  - `Frontend\DashboardPage`: the `[rtbp_dashboard]` shortcode (registered through
    `rtbp_shortcodes`). A page carrying it is served with the plugin's own full-screen
    template `templates/dashboard/app-page.php` (theme-overridable; opt out with the
    `rtbp_dashboard_fullscreen` filter).
  - **Visitors:** logged out get `dashboard/login.php` (wp_login_form, returns to the page,
    brand colour, plain CSS); signed in without `rtbp_view_dashboard` get `no-access.php`;
    staff get the same app as wp-admin.
  - `LoadAssets::enqueue_staff_app()` is shared by wp-admin and the front end, so it is
    exactly the same bundle, localized data, brand colour, and Pro hook.
  - `PageInstaller` now creates a **Hotel Dashboard** page (`dashboardPage`, slug
    `/hotel-dashboard/`, the same address the client uses today).
  - **App-page isolation:** on this page every script and style that is neither WordPress
    core nor `plugins/radius-hotel-booking*` is dequeued (the filter is
    `rtbp_dashboard_page_keep_asset`). A curated `DashboardPage::footer()` replaces
    `wp_footer()` (admin bar, media templates, scripts, and the `rtbp_dashboard_footer`
    action), so other plugins can't inject markup. On food-menu.local that took 30 foreign
    scripts and 19 styles down to 0, and removed Food Menu's floating cart and location
    picker from the front desk.
  - The shell height uses `--rtbp-top` (`.rtbp-shell`), so it is correct with or without the
    admin bar.
  - **Follow-up (added to M04 T1):** both bundles ship Tailwind's global preflight, which
    resets any theme page it loads on. The dashboard avoids this with its own template;
    guest-facing embeds (M04) need the reset scoped first.
  - **Verified on food-menu.local:**
    - Logged out: HTTP 200 with the login form, redirect_to back to the page, noindex, no
      theme header or footer, and no admin bundle sent.
    - Subscriber: sees the no-access message. Admin: sees the app.
    - In the browser: full app, 0 foreign assets, admin bar, media templates and Pro tab
      present, the dashboard summary loads, settings save from the front end.
    - The wp-admin dashboard is unchanged.
  - **Not yet checked visually:** the login screen (the only browser session is signed in)
    and 360 px width.
- 2026-09-28 **T6 done.**
  - **Status palette:** `src/lib/status.js` (stay / payment / room / availability → label +
    tone, outline for ended states, a pattern for held/blocked; unknown values fall back to
    neutral) and `components/common/StatusBadge` (dot + text; the new `--caution` token is
    for partially paid). Both are exposed on `window.rtbp`.
  - **Mobile:** `MobileTabBar` below 960 px shows routes flagged `mobileTab`, using
    `mobileLabel` when set ("New"), plus "More", which opens the drawer.
  - **Command palette:** `CommandPalette` opens with ⌘K / Ctrl+K or the top-bar Search
    button. It is navigation only for now; record search comes with M01/M09.
    - It is **lazy-loaded**: the palette put the admin entry at 145 KB, and lazy-loading
      brought it back to 97.6 KB. The shortcut lives in `layout/shortcuts.js`.
    - **Custom ranking** (label prefix, then word prefix, then substring, then description).
      cmdk's fuzzy score ranked "Availability" above "Rate plans" for "rate".
  - **Stacking:** dialogs and sheets now sit at z 100000 and dropdowns, popovers, tooltips and
    selects at z 100010. They were under the WordPress admin menu and bar (z-50).
  - The command input overrides wp-admin's global `input:focus` border and shadow.
  - **Mobile fixes found in the 360 px check** (rendered in 360 px iframes, because the browser
    window can't be resized):
    - Grid items and panels needed `min-w-0`: the Today panel was 505 px wide on a 360 px
      screen.
    - Below 600 px WordPress's admin bar scrolls away, so `--rtbp-top` is 0 there. Before,
      there was a 46 px gap above the sticky header.
  - **Verified in the browser:**
    - The palette opens and filters: rate → Rate plans; set → Settings; Enter navigates.
    - wp-admin and /hotel-dashboard at 360 px: no horizontal overflow; the tab bar and the
      More drawer work; the sticky header sits flush.
    - The status-tone classes are compiled.
- 2026-09-28 **T7a done.**
  - **Installed with bun** (the project's package manager: `bun.lock`, no npm lock):
    @tanstack/react-query 5.104, react-hook-form 7.89, zod 3.25, @hookform/resolvers 3.10,
    sonner 2.0.
  - **Data and toasts:** `lib/query-client.js` (one QueryClient: staleTime 30 s; no retry on
    4xx, one retry otherwise); `QueryClientProvider` in `admin/App.jsx`. `lib/toast.js` has
    `toast` and `toastError`; the `<Toaster>` in AppShell sits top-right below the header, or
    top-center on phones.
  - **Forms:** `lib/forms.js` has `useZodForm` (validates on blur, then on change) and
    `applyServerErrors` (maps the API's `errors.{field}.first_message` onto RHF fields).
    `components/common/Form.jsx` has `FormSection` and `Field` (id, aria-invalid,
    aria-describedby and the required marker are wired automatically).
  - `components/common/ConfirmDialog.jsx`: optional required reason with a minimum length, a
    busy state, and an inline error that keeps the dialog open.
  - **Real consumers:** the dashboard summary moved to `useQuery` (cached, refetched every
    60 s and on focus). Settings saves now show toasts, replacing the inline notice. The
    unused boilerplate `hooks/useToast.js` is removed.
  - **Runtime (ADR-015 updated):** adds `ReactQuery`, `lib.queryClient`, `lib.toast`,
    `lib.toastError`, and `ui.Field` / `ui.FormSection`, plus `ui.ConfirmDialog` as a lazy
    component. zod and react-hook-form are not on the runtime: publishing them pushed them
    into every page load, and add-on form screens are lazy anyway, so they bundle their own.
  - **Size budget** in webpack.config.js: 300 KB for the admin entry (JS + CSS), excluding
    the RTL stylesheet (which replaces, never accompanies, admin.css). Current: admin.js
    187 KB (60 KB gzipped) + admin.css 70 KB.
  - **Verified in the browser:**
    - Summary cached across navigation (1 request).
    - Save → success toast below the header (116 px vs header bottom 104 px).
    - ConfirmDialog: disabled without a reason; failure keeps it open and shows the error;
      success closes it.
    - Field: aria-invalid, describedby, and the required marker.
- 2026-09-28 **T7b done.**
  - **`components/common/DataTable.jsx`:**
    - Server-driven: `rows`, `total`, `page`, `perPage`, and `sort {by, dir}` with
      `aria-sort`.
    - A debounced search (300 ms), a toolbar slot, a primary row action plus a ⋯ menu, row
      click and Enter.
    - A skeleton on the first load, rows dimmed while refetching, an inline error with
      retry, and an empty slot.
    - Below `md` it switches to cards (column `mobile: title | subtitle | hidden`).
    - **Not TanStack Table** (design-system updated): everything is server-driven, so
      TanStack would only have added about 50 KB.
  - **`FilterTabs`:** tabs with counts, scrolling sideways on phones with the scrollbar
    hidden (`.rtbp-no-scrollbar`).
  - **`DateRangePicker`:**
    - Presets: today, tomorrow, next 7 days, this week, this month, last month.
    - The optional arrival / booking-date mode switch, shown in the trigger as
      "Arriving:" / "Booked:".
    - Two months on wide screens, one on phones; clear and done.
    - Values are `Y-m-d` in the hotel's zone.
  - **`ui/calendar.jsx`:** react-day-picker **9.14** (added directly; v10 was only a
    transitive dependency). Styled with the tokens; "today" is the hotel's today; the week
    starts on WordPress's start_of_week; FR/EN locale.
  - **Helpers:** `siteToday`, `addDaysYmd`, `ymdToDate` and `dateToYmd` in
    `lib/format.js`, and the `hooks/useMediaQuery` hook.
  - **Runtime:** `ui.FilterTabs`, plus lazy `ui.DataTable` and `ui.DateRangePicker`. The admin
    entry is 189 KB (+2 KB); the picker and date-fns sit in a lazy 81 KB chunk.
  - **Verified in the browser** with a 23-row demo mounted via `window.rtbp`:
    - Table: sort toggles (asc→desc, aria-sort), the pager (1–10 of 23 → page 2 of 3), and
      the tab filter (5 of 5). A row click and the primary action fire separately
      (stopPropagation); the ⋯ menu opens with a red destructive item.
    - Date picker: picking 14→17 Oct gives `{2026-10-14, 2026-10-17}`; the "Next 7 days"
      preset gives `{2026-09-28, 2026-10-04}` and closes; the mode switch changes the label;
      clearing resets.
    - At 360 px: cards, no overflow, tabs swipe with the bar hidden.
- 2026-09-28, **T7c** (display components + UI kit):
  - **`Money`** (signed, negative in red, `formatMoney` settings) and **`DateTime`** (a single
    value, a date-only value, or a range; a same-day range shows the date once). Both are on
    `window.rtbp.ui`.
  - **`#/dev/ui`:** registered only when the localized `dev_ui` flag is true
    (`WP_DEBUG` and `manage_options`). It never shows on a production site, so there is no
    changelog line. A state switcher drives StatCards, FilterTabs + DataTable +
    DateRangePicker through loading / empty / error / populated. The page also shows every
    StatusBadge, Money/DateTime samples, the form (zod on blur; a phone ending `0000` fakes
    a server field error), toasts and ConfirmDialog (the reason "fail" fakes a server error).
  - **Fixes the kit surfaced:**
    - DataTable cells are single-line by default, with a column `wrap` option.
    - The desktop scroller is `relative`, so the sr-only "Actions" label no longer widens the page.
    - Phone cards no longer truncate. Values wrap, and a `wrap` column spans the card.
    - `StatusBadge` inherits nowrap from table cells instead of forcing it, so a long badge
      (French too) wraps inside a card rather than being cut to "Awaiting appro…".
  - **Verified in the browser:**
    - The four states.
    - Blur errors on all three fields.
    - The server error lands on the phone field and takes focus.
    - ConfirmDialog: the error shows inline and the dialog stays open; success closes it and
      shows a toast.
    - Desktop: 20 badges, all single-line, no overflow.
    - 360 px: no overflow, no value clipped.
- 2026-09-28, **T8** (French, WooCommerce guard, uninstall, descriptions):
  - **French:**
    - `languages/radius-hotel-booking-fr_FR.po` is complete (223 strings), with the
      `.mo`, the `.l10n.php` and 22 JSON sidecars compiled by `bin/i18n-build.sh`.
    - Workflow after adding strings: `bun run i18n:pot`, then `msgmerge`, translate,
      then `bin/i18n-build.sh fr_FR` (README → Internationalisation).
    - **Mobile tab labels** are `_x()` strings with a "phone tab bar" context, so French
      uses "Accueil".
    - **"steps done"** is now `_n()`.
  - **The user's language:**
    - wp-admin already uses the profile language.
    - The Hotel Dashboard page now switches too (`DashboardPage::use_staff_locale`). When
      WordPress refuses the switch because core French isn't installed (as on
      food-menu.local), it falls back to filtering `determine_locale` and reloading the
      plugin's textdomain, so the plugin's screens still follow the user.
    - REST calls send `_locale=user` (apiFetch's middleware; seen in the network log).
  - **`load_plugin_textdomain()` on init:** WordPress 7.0 registers the `Domain Path`
    itself (checked), but 5.5–6.x do not. Plugin Check may warn about it (T9 will show).
  - **i18n build fixes:**
    - `make-json` now reads `.jsx`; it wrote only 4 of 22 files before.
    - The patched `.l10n.php` was left at mode 0600, which a web server running as
      another user can't read; it is now chmod 0644.
    - `languages/` is excluded from phpcs (generated files).
  - **WooCommerce guard:**
    - `bin/check-woocommerce-free.sh` (`bun run check:woocommerce`, also run first by
      `bun run package`) rejects `wc_*()`, `WC()`, `WC_*` classes, `class_exists(
      'WooCommerce' )`, firing `woocommerce_*` hooks, `@woocommerce/` imports,
      `window.wc`/`wcSettings`, and a `Requires Plugins` header naming it.
    - Listening to a WooCommerce hook stays allowed: coexistence, e.g.
      PermissionsManager's `woocommerce_prevent_admin_access`.
    - Verified by planting 7 violations (all caught) and by the dashboard summary route
      returning 200 with WooCommerce skipped.
  - **`uninstall.php`:**
    - It does nothing unless `general.deleteDataOnUninstall` is true (a new default,
      `false`). The toggle UI is **M17 T5**.
    - When the setting is on, it removes, per site: tables by prefix, `rtbp_%` options
      and transients, `_rtbp_page` pages, `rtbp_*` roles, `rtbp_*` caps on other roles,
      and the uploads folder (not an `RTBP_PROTECTED_DIR` one).
    - Verified on food-menu.local against a DB snapshot, restored after each run. Off:
      nothing changed. On: 4→0 tables, 7→0 options, 2→0 pages, both roles gone,
      `editor` kept with only its `rtbp_` cap removed.
    - The multisite loop was not exercised (single-site install).
    - `PermissionsInstaller::remove_roles()` no longer removes a non-`rtbp_` role; the
      role map is filterable.
  - **Bug found and fixed: the installer could fatal.**
    - `Installer::run()` created pages from `plugins_loaded`, before `$wp_rewrite`
      exists, so `wp_insert_post()` fatals.
    - It ran on every DB-version bump when a plugin page was missing, and on a fresh
      stamp. The page titles also loaded translations too early.
    - Pages and `rtbp_installed` now run from `Installer::finish()` on `init`, or at once
      if `init` has passed.
    - `PageInstaller::create_pages()` was removed (`create_missing_pages()` covers a
      fresh install).
    - Verified: deleting the page and the DB version, then booting, recreates the page
      with no error (twice).
  - **Package script:**
    - `bin/build-plugin-zip.sh` used `npm ci`; the repo uses bun and has no npm
      lockfile, so the build could not run. It now uses `bun install --frozen-lockfile`.
    - It never copied `uninstall.php`; it does now.
  - **Descriptions:** the plugin header, `readme.txt` (tags, short description,
    Description, Installation, FAQ), `README.md`, `package.json` / `composer.json` and
    CLAUDE.md (the renaming section is removed; commands use bun) now describe the
    product. The readme lists the planned free features; **M18 re-checks it against what
    shipped.**
  - **Follow-ups:**
    - `create_missing_pages()` recreates a page the hotel deliberately deleted, on the
      next DB bump. Decide in M17 whether to respect a deletion.
    - The `itemsPage` key left over from the removed Item slice is still in
      `rtbp_pages_settings` on existing sites (harmless).
- 2026-09-28, **T9** (extension runtime, client add-on, Plugin Check):
  - **`rtbp.api.error`** in `src/api/client.js`: `applyFilters( 'rtbp.api.error', undefined, error,
    { path, options, retry } )`. A handler returns a Promise to take over the request (its
    result becomes the request's), or passes `handled` through so the error throws. `retry()`
    re-sends once without the filter, so nothing loops.
    - Verified from the Pro demo: one request recovered, one retried exactly once (2 requests
      in the network log) and then threw, and the filter ran once per request.
  - **Runtime additions:**
    - `React` (the same object as `window.React`), the dashboard composites (EmptyState, Panel,
      StatCard, SegmentedControl), Skeleton, Textarea and `lib.usePageActions`.
    - `lib.loadForms()` (react-hook-form + zod, now also exporting `z`).
    - Dialog / DropdownMenu / Select / Tabs parts as **lazy stand-ins** (`lazyParts()`).
  - **Why lazy:** importing those four Radix families eagerly took `admin.js` from 191 KB to
    310 KB (over the 300 KB budget). Even unused imports cost that, because the ui modules are
    not tree-shaken. Lazy, the whole T9 runtime costs +4 KB (195 KB).
  - **Fixed a load-order race:** `main.jsx` rendered as soon as the free bundle ran, and
    `getRoutes()` caches on first render. React's first render is a separate task, which the
    browser may run while it is still fetching the next (add-on) script, so add-on routes
    could be missing. The app now mounts on `DOMContentLoaded`.
  - **Pro:**
    - Webpack maps `@tanstack/react-query` to `rtbp.ReactQuery`.
    - `lucide-react` is added (icons are tree-shaken into Pro's own bundle).
    - `#/pro/runtime` is a dev-only screen (registered only when `dev_ui` is set), verified in
      the browser: the route, the shared query cache, eager and lazy components, and the error
      filter.
  - **Client add-on** `../radius-hotel-booking-residencetata`:
    - `git init` on `module/m00-foundation`, not committed.
    - Boots on Pro's `rtbp_pro_loaded`. Without Pro it shows a notice and stays dormant; without
      free and Pro there is no fatal (checked with `--skip-plugins`).
    - Its bundle loads after Pro's; it depends on Pro's `Assets::HANDLE`, behind a `class_exists`
      guard.
    - `#/tata/runtime` (dev-only) reads the free runtime and Pro's data. Scripts are printed in
      the order free, Pro, client.
    - Symlinked into food-menu.local and activated.
  - **Free with Pro and the client deactivated:** the dashboard works (1 script, no errors).
    Both were reactivated.
  - **Plugin Check 2.0.0 on free** (the shipped file set):
    - Fixed: `ExceptionNotEscaped` ×4 (inline ignore plus the convention in conventions.md
      §2.3; `esc_html__` would show `&#039;` in French messages).
    - Fixed: the artisan generators (mkdir, unlink, str_ends_with) are no longer shipped: the zip
      removes `includes/Commands`, and composer.json keeps them out of the optimized classmap.
    - Fixed: the zip removes hidden files.
    - Fixed: **minimum WordPress raised from 5.5 to 6.2**. The code already used `%i` in
      `$wpdb->prepare()` (MigrationRunner, uninstall.php), which needs 6.2.
    - **Remaining:** 1 error, `Tested up to: 6.7` < 7.1. Only verified on 7.0.1 here, so it is
      bumped at release. Warnings: `error_log` ×8 (migrations), UnescapedDBParameter ×8
      (framework Connection and table-name parameters), the deliberate `load_plugin_textdomain`
      (see T8), and the Settings model's direct query.
  - **Pre-existing bug fixed:** `CommandRegistry` never registered `migrate:status`,
    `migrate:rollback` or `migrate:refresh`: it ran `class_exists( 'Class::method' )`. Method
    commands are now registered as `array( new Class(), 'method' )`; all three work.
  - **Follow-ups:**
    - A shared Tailwind preset for add-on screens. The Pro demo's `md:grid-cols-2` was not
      compiled because free doesn't use it.
    - Pro's `phpcs.xml` still has `testVersion 7.4-`; the add-on uses `8.0-`.
    - Add-on repos ignore `bun.lock` (copied from Pro); free tracks it.
