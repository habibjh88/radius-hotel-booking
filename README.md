# Radius Hotel Booking

A WordPress plugin hotel booking. It ships the plumbing every plugin needs — DI
container, ORM, migrations, REST router, capabilities, CLI scaffolding, a React
admin — plus **one example resource (`Item`) wired through every layer**, so you
can see how the pieces fit and then delete the example.

Extracted from a production RadiusTheme plugin, with all domain code removed
and Vite replaced by `@wordpress/scripts`/webpack.

---

## Quick start

```bash
composer install
npm install

npm run build          # production bundles into build/
npm run start          # watch + rebuild (writes to the same build/ directory)
```

Then activate the plugin. **Radius Hotel Booking** appears in the admin menu with
Dashboard, Items and Settings screens.

| Command | What it does |
|---|---|
| `npm run start` | Watch mode. Writes to `build/`, same as a production build. |
| `npm run build` | Production bundles + `*.asset.php` dependency files. |
| `npm run lint:js` / `npm run format` | ESLint / Prettier, via wp-scripts. |
| `composer phpcs` / `composer phpcs:fix` | WordPress coding standards. |
| `npm run test:php` | PHPUnit — see Testing below for the required env vars. |
| `npm run package` | Build a distributable zip. |
| `npm run i18n:pot` | Regenerate `languages/radius-hotel-booking.pot`. |

---

## Renaming it for a new project

Everything identity-related lives in a handful of strings. Replace them all —
in that order, so the longer strings match first:

| Find | Replace with | Where |
|---|---|---|
| `RadiusTheme\RadiusHotelBooking` | `YourVendor\YourPlugin` | every PHP file, `composer.json` autoload |
| `RADIUS_HOTEL_BOOKING_` | `YOUR_PLUGIN_` | constants |
| `radius_hotel_booking_` | `your_plugin_` | table prefix, localized JS object names |
| `radius-hotel-booking` | `your-plugin` | slug, text domain, REST namespace, handles, CLI command |
| `rtbp_` | `yp_` | function prefix, option keys, hook names, capabilities |
| `RadiusHotelBooking` | `YourPlugin` | main class name |
| `Radius Hotel Booking` | `Your Plugin` | display strings |

```bash
# From the plugin root. Review the diff before committing.
grep -rl 'RadiusHotelBooking\|radius-hotel-booking\|radius_hotel_booking\|rtbp_\|RADIUS_HOTEL_BOOKING' \
  --exclude-dir={node_modules,vendor,build,.git} . |
xargs perl -pi -e '
  s/RadiusTheme\\RadiusHotelBooking/YourVendor\\YourPlugin/g;
  s/RADIUS_HOTEL_BOOKING/YOUR_PLUGIN/g;
  s/radius_hotel_booking/your_plugin/g;
  s/radius-hotel-booking/your-plugin/g;
  s/rtbp_/yp_/g;
  s/Radius Hotel Booking/Your Plugin/g;
  s/RadiusHotelBooking/YourPlugin/g;
'

mv radius-hotel-booking.php your-plugin.php
composer dump-autoload
```

Then update the plugin header in the main file, `readme.txt`, and
`package.json`/`composer.json` names.

---

## Architecture

```
radius-hotel-booking.php        Bootstrap: constants, lifecycle, component wiring
includes/
  Abstracts/                  BaseController, BaseModel, BaseRepository, BaseResource,
                              BaseEmail, Facade, Migration
  Admin/                      Admin menu
  Assets/LoadAssets.php       Enqueues the webpack bundles + the i18n layer
  Blocks/                     Gutenberg block registration
  Commands/                   WP-CLI "artisan" generators
  Common/Keys.php             Every option/transient key
  Controllers/                REST controllers (ItemController, SettingsController)
  Core/
    Api/                      ApiManager, ApiResponse, router, middleware, validation
    Container/                DI container
    Database/                 Connection, Schema builder, MigrationRunner
    Events/                   EventDispatcher
    ORM/                      QueryBuilder, relations, pagination
    Permissions/              Capability registry + enforcement
    Support/                  Collection, DateTimeParser
    config/                   bindings.php, events.php
  Databases/                  DatabaseManager + Table/ schemas
  Elementor/                  Widget registration
  Emails/                     EmailManager, MergeTags, TemplateHooks, sender, renderer
  Helpers/SettingsHelper.php  Settings sections and their defaults
  Hooks/                      Cross-cutting hooks
  Models/                     Item, Settings
  Repositories/               Data access
  Resources/                  API response shaping
  Routes/routes.php           Route definitions
  Services/                   Business logic
  Setup/                      Installer, PageInstaller, PermissionsInstaller
  Shortcodes/                 Shortcode registry
  Utility/                    Procedural helpers, template functions
src/                          React (see below)
templates/                    Overridable front-end + email templates
views/app.php                 Admin mount point
resources/stubs/              Templates the CLI generators render
```

### The request path

```
REST request
  → Core\Api\ApiManager           loads includes/Routes/routes.php on rest_api_init
  → Core\Api\Routes\RouteRegistrar registers each route with WordPress
  → Controllers\ItemController     middleware → validation → repository
  → Abstracts\BaseController       index/show/store/update/destroy for free
  → Repositories\ItemRepository    wraps the model
  → Models\Item                    ORM + lifecycle events
  → Resources\ItemResource         shapes the JSON
  → Core\Api\ApiResponse           { success, status_code, message, data, errors, meta }
```

### Adding a resource

```bash
wp radius-hotel-booking artisan make:module Product
```

scaffolds the model, controller, service, repository and resource. Then:

1. Add a table class under `includes/Databases/Table/` (or
   `wp radius-hotel-booking artisan make:table Products`) and register it in
   `Databases\DatabaseManager`.
2. Bump `RadiusHotelBooking::DBVERSION` so the migration runs.
3. Register the repository/service in `includes/Core/config/bindings.php`.
4. Add routes in `includes/Routes/routes.php`.
5. Add a capability in `Core\Permissions\Capabilities` and bump
   `PermissionsInstaller::ROLES_VERSION`.
6. Add a screen under `src/modules/` and a line in `src/admin/routes.js`.

### Adding a webpack entry

1. Add `myentry: path.resolve( __dirname, 'src/myentry/main.jsx' )` to
   `entry` in `webpack.config.js`.
2. Create `src/myentry/main.jsx`.
3. Enqueue it from `Assets\LoadAssets` with
   `$this->enqueue_entry( 'myentry', 'radius-hotel-booking-myentry' )`.

---

## Frontend

```
src/
  admin/          Admin SPA entry, App shell, route table
  site/           Public app entry (mounted by the shortcode/block/widget)
  blocks/         Block editor bundle
  components/
    ui/           shadcn/ui primitives (new-york, JSX, lucide icons)
    Layout.jsx    Admin sidebar + header
  modules/        One folder per screen: Dashboard, Items, Settings
  api/client.js   apiFetch wrapper that unwraps the ApiResponse envelope
  lib/utils.jsx   cn()
  index.css       Admin styles + design tokens
  site.css        Public styles + design tokens
```

* `@/` resolves to `src/` (webpack alias + `jsconfig.json`).
* Tailwind utilities are scoped with `important: '.rtbp-root'`, so the plugin's
  CSS can never leak into wp-admin or a theme. Keep `rtbp-root` on your
  outermost element — including inside portalled Radix content (dialogs,
  dropdowns), which render outside the app tree.
* Colours are CSS variables defined in `index.css` / `site.css` and mapped in
  `tailwind.config.js`. Re-theme by editing the variables only.
* Add a shadcn component with `npx shadcn@latest add <name>` — `components.json`
  is already configured.

### How assets are enqueued (no dev/prod switch)

`@wordpress/scripts` emits `build/<entry>.js`, `build/<entry>.css` and
`build/<entry>.asset.php` for every entry. The asset file returns the WordPress
script dependencies the bundle imports plus a content hash:

```php
<?php return array( 'dependencies' => array( 'react', 'wp-i18n' ), 'version' => 'a1b2c3…' );
```

`Assets\LoadAssets` reads it, so dependencies and cache-busting are automatic
and there is **no manifest to parse and no dev-server mode** — `npm run start`
and `npm run build` write the same files to the same place.

If a change doesn't show: rebuild (`npm run build`) or check that
`npm run start` is still running.

---

## Internationalisation

`@wordpress/i18n` is externalised to `wp.i18n` by webpack's dependency
extraction, so bundles share WordPress's locale data — no shim needed.

What still needs a workaround is the *lookup*: WordPress finds a script's JSON
translations by hashing the bundle's source path, which content-hashed builds
and Loco's per-source-file sharding both break.
`LoadAssets::load_merged_script_translations()` fixes that by merging **every**
`radius-hotel-booking-{locale}-*.json` it finds across the three folders Loco can
save to, plus the compiled `.mo`/`.l10n.php` catalog, into a single
`locale_data` block. A translator saving a `.po` in Loco gets working React
translations with no build step.

```js
import { __, sprintf } from '@wordpress/i18n';

__( 'My text', 'radius-hotel-booking' );
sprintf( __( 'Hello %s', 'radius-hotel-booking' ), name ); // never concatenate
```

Regenerate the POT with `npm run i18n:pot`; compile with `bin/i18n-build.sh`.

---

## Free / add-on split

The hotel booking keeps the hooks for a paid add-on, but no licensing logic:

* `rtbp_addon_active()` — true when `RADIUS_HOTEL_BOOKING_PRO_VERSION` is defined.
* `rtbp_api_route_paths` — an add-on appends its own route file.
* `rtbp_register_addon_routes` — or registers routes on the shared router.
* `rtbp_register_addon_integrations` — fires after the plugin boots.
* `is_addon` is localized to both JS apps for UI gating.

Gate a paid feature **both** in the UI and server-side, so a stored setting
can't bypass the gate.

---

## Testing

PHPUnit runs against a WordPress checkout and a throwaway MySQL database. There
is no containerised environment — point the suite at whatever local stack you
already use:

```bash
export WP_CORE_DIR="$HOME/wordpress"     # any WordPress checkout
export WP_TESTS_DB_NAME=wordpress_test   # DROPPED and recreated on every run
export WP_TESTS_DB_USER=root
export WP_TESTS_DB_PASSWORD=
export WP_TESTS_DB_HOST=127.0.0.1

npm run test:php      # PHPUnit — tests/phpunit/
```

Those variables are read by `tests/phpunit/wp-config.php`, which
`phpunit.xml.dist` loads. The test database is wiped on every run, so never
aim it at one you care about.

`tests/phpunit/TestItemCrud.php` is the reference test; copy it for your own
resources.

When you write tests that call the REST API, remember that
`PermissionMiddleware` rejects requests whose `Referer` isn't the site URL —
set that header, or the request comes back 403 "Invalid request source".

---

## What was intentionally left out

No payment gateways, no third-party integrations, no Action Scheduler, no
charting or PDF libraries, no date pickers. Add what you need — the hotel booking
stays small on purpose.
