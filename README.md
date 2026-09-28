# Radius Hotel Booking

Hotel room booking for WordPress, by **time window** rather than only by night:
half day, overnight, 24 hours, or any window the hotel defines. Built first for
Residence TATA (Abidjan) to replace their WooCommerce-based hotel stack, and
published on wordpress.org as a free plugin.

The product ships as three plugins:

| Plugin | Folder (siblings) | Ships to |
|---|---|---|
| **Radius Hotel Booking** (this repo) | `radius-hotel-booking` | wordpress.org |
| **Radius Hotel Booking Pro** | `radius-hotel-booking-pro` | paying customers |
| **Residence TATA add-on** | `radius-hotel-booking-residencetata` | the client only |

The free plugin holds no Pro or client code. It exposes hooks, registries and a
JS runtime (`window.rtbp`) that the other two build on (ADR-014 to ADR-017).

**Planning and specs live in [`docs/`](docs/README.md).** Start there: the build
order, the booking engine, the architecture, the design system and the
Definition of Done.

---

## Quick start

```bash
composer install
bun install

bun run build          # production bundles into build/
bun run start          # watch + rebuild (writes to the same build/ directory)
```

Activate the plugin. **Radius Hotel Booking** appears in the admin menu, and a
**Hotel Dashboard** page is created for staff on the front end.

| Command | What it does |
|---|---|
| `bun run start` | Watch mode. Writes to `build/`, the same as a production build. |
| `bun run build` | Production bundles + `*.asset.php` dependency files. |
| `bun run format` | Prettier, via wp-scripts. |
| `composer phpcs` / `composer phpcs:fix` | WordPress coding standards. Judge by the exit code. |
| `bun run check:woocommerce` | Fails if the code calls WooCommerce (feature 18.6). |
| `bun run i18n:pot` | Regenerate `languages/radius-hotel-booking.pot`. |
| `bin/i18n-build.sh --all` | Compile every `.po` into `.mo`, `.l10n.php` and JSON. |
| `bun run package` | Build the distributable zip (runs the WooCommerce check first). |

There is no PHPUnit or ESLint suite. Changes are verified on a local site with
throwaway `wp eval` scripts and in the browser (ADR-018,
`docs/project/conventions.md` §4).

Add `define( 'WP_DEBUG', true );` to open the UI kit at
`#/dev/ui` (administrators only): every shared component in its loading,
empty, error and populated states.

---

## Architecture

```
radius-hotel-booking.php      Bootstrap: constants, lifecycle, component wiring
uninstall.php                 Removes all data, only when the site asked for it
includes/
  Abstracts/                  BaseController, BaseModel, BaseRepository, BaseResource,
                              BaseEmail, Facade, Migration
  Admin/                      Admin menu
  Assets/LoadAssets.php       Enqueues the webpack bundles + the i18n layer
  Commands/                   WP-CLI "artisan" generators
  Common/Keys.php             Every option/transient key
  Controllers/                REST controllers
  Core/
    Api/                      ApiManager, ApiResponse, router, middleware, validation
    Container/                DI container
    Database/                 Connection, Schema builder, MigrationRunner, Transaction
    Events/                   EventDispatcher
    ORM/                      QueryBuilder, relations, pagination
    Permissions/              Capability registry + enforcement
    config/                   bindings.php, events.php
  Databases/                  DatabaseManager + Table/ schemas
  Emails/                     EmailManager, MergeTags, TemplateHooks, sender, renderer
  Exceptions/                 DomainException (code + message + status + field errors)
  Frontend/                   The [rtbp_dashboard] staff page
  Helpers/                    SettingsHelper (sections + defaults), ThemeHelper
  Models/ Repositories/ Resources/ Services/
  Routes/routes.php           Route definitions
  Setup/                      Installer, PageInstaller, PermissionsInstaller, Scheduler
  Storage/ProtectedFiles.php  Private uploads with an authenticated download route
  Support/                    Money, Dates, Sequence
  Utility/                    Procedural helpers, template functions
src/                          React (see below)
templates/                    Overridable dashboard and e-mail templates
views/app.php                 Admin mount point
resources/stubs/              Templates the CLI generators render
```

### The request path

```
REST request
  → Core\Api\ApiManager            loads includes/Routes/routes.php on rest_api_init
  → Core\Api\Routes\RouteRegistrar registers each route with WordPress
  → Controllers\…Controller        middleware → validation → service
  → Services\…                     business rules, transactions, hooks
  → Repositories\… → Models\…      data access, ORM, lifecycle events
  → Resources\…                    shapes the JSON
  → Core\Api\ApiResponse           { success, status_code, message, data, errors, meta, code? }
```

`PermissionMiddleware` needs a valid `x-wp-nonce` **and** a `Referer` starting
with `home_url()`. A client without a Referer gets a 403.

### Adding a slice

```bash
wp radius-hotel-booking artisan make:module Floor
```

scaffolds the model, controller, service, repository and resource. Then add the
table under `includes/Databases/Table/`, register it in
`Databases\DatabaseManager`, **bump `RadiusHotelBooking::DBVERSION`**, bind the
repository and service in `includes/Core/config/bindings.php`, and add the
routes. The `rtbp-backend-slice` skill in `.claude/skills/` has the full
checklist.

---

## Frontend

```
src/
  admin/          Admin SPA entry, App, the route table (routes.js)
  site/           Public app entry
  blocks/         Block editor bundle
  components/
    ui/           shadcn/ui primitives (Radix, lucide icons)
    layout/       AppShell, Sidebar, Topbar, MobileTabBar, CommandPalette
    common/       DataTable, FilterTabs, DateRangePicker, StatusBadge, Money,
                  DateTime, EmptyState, ConfirmDialog, Form…
  modules/        One folder per screen
  api/client.js   Unwraps the ApiResponse envelope; errors carry `code` and field errors
  lib/            runtime (window.rtbp), format, status, theme, query-client, toast
  index.css       Design tokens, scoped to .rtbp-root
```

* `@/` resolves to `src/` (webpack alias + `jsconfig.json`).
* Tailwind utilities are scoped with `important: '.rtbp-root'`, so the plugin's
  CSS can never leak into wp-admin or a theme. Portalled Radix content
  (dialogs, popovers, selects) needs `rtbp-root` on its own content node.
* The brand colour comes from Settings → Display and restyles every token that
  derives from `--primary`.

`@wordpress/scripts` emits `build/<entry>.js`, `.css` and `.asset.php` for every
entry. `Assets\LoadAssets` reads the asset file, so dependencies and cache
busting are automatic. There is no dev server: if a change doesn't show, the
build didn't run.

---

## Internationalisation

English strings in code, French shipped in `languages/`
(`radius-hotel-booking-fr_FR.po`). Staff see the language in their WordPress
profile, both in wp-admin and on the Hotel Dashboard page.

```js
import { __, sprintf } from '@wordpress/i18n';

__( 'Arriving today', 'radius-hotel-booking' );
sprintf( __( 'of %d rooms', 'radius-hotel-booking' ), total ); // never concatenate
```

After adding strings:

```bash
bun run i18n:pot                                  # refresh the template
msgmerge --update languages/radius-hotel-booking-fr_FR.po languages/radius-hotel-booking.pot
# translate the new entries, then:
bin/i18n-build.sh fr_FR
```

WordPress finds a script's JSON translations by hashing the bundle path, which
content-hashed builds and Loco Translate's per-file output both break.
`LoadAssets::load_merged_script_translations()` merges every
`radius-hotel-booking-{locale}-*.json` in the three folders Loco can save to,
plus the compiled `.mo`, into one `locale_data` block. A `.mo` alone is enough
for the React screens to translate.

---

## WooCommerce

The plugin must run with WooCommerce absent (feature 18.6).
`bun run check:woocommerce` rejects calls to `wc_*()`, `WC()`, `WC_*` classes,
firing WooCommerce hooks and WooCommerce JS packages. Listening to a WooCommerce
filter so the two coexist is allowed. `PermissionsManager` uses one to stop
WooCommerce locking staff out of wp-admin.

---

## Uninstall

Deleting the plugin keeps every table, option, role and file unless General →
`deleteDataOnUninstall` is on. When it is on, `uninstall.php` removes, on each
site: every table with the plugin's prefix (the Pro and client tables too),
every `rtbp_*` option and transient, the pages the plugin created, the `rtbp_*`
roles and the `rtbp_*` capabilities on other roles, and the protected uploads
folder. A folder set with `RTBP_PROTECTED_DIR` belongs to the host and is not
touched.
