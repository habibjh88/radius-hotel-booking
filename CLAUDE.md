# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Build & Development Commands

```bash
# Initial setup
composer install
npm install

# Frontend (@wordpress/scripts + webpack)
npm run start            # Watch mode — writes to build/, same output as a production build
npm run build            # Production bundles + *.asset.php dependency files
npm run format           # Prettier via wp-scripts

# PHP linting
composer phpcs           # Check all files against WordPress coding standards
composer phpcs:fix       # Auto-fix
./vendor/bin/phpcs --standard=phpcs.xml includes/path/to/File.php  # Lint one file

# Testing (needs WP_CORE_DIR + a throwaway MySQL database — see README)
npm run test:php         # PHPUnit, tests/phpunit/

# WP-CLI scaffolding (any WP-CLI environment)
wp radius-hotel-booking artisan make:model ModelName
wp radius-hotel-booking artisan make:controller ControllerName
wp radius-hotel-booking artisan make:service ServiceName
wp radius-hotel-booking artisan make:repository RepositoryName
wp radius-hotel-booking artisan make:resource ResourceName
wp radius-hotel-booking artisan make:table TableName
wp radius-hotel-booking artisan make:module ModuleName    # model+controller+service+repo+resource
wp radius-hotel-booking artisan migrate
wp radius-hotel-booking artisan migrate:rollback
wp radius-hotel-booking artisan migrate:status

# Packaging & i18n
npm run package          # Build plugin zip via bin/build-plugin-zip.sh
npm run i18n:pot         # Regenerate languages/radius-hotel-booking.pot
bin/i18n-build.sh --all  # Compile .po -> .mo / .l10n.php / .json
```

## What this repository is

A hotel management plugin for **Residence TATA** (Abidjan), replacing their
WooCommerce-based hotel stack. It is built on a framework layer extracted from a
production RadiusTheme plugin. The example `Item` slice is removed in module M00.

**The build is planned in modules. Read `docs/README.md` first.**

- `docs/project/roadmap.md`: the build order and tracker. `/module-status`,
  `/next-module` and `/next-task` read and update it.
- `docs/project/booking-engine.md`: time-based booking (rate-plan windows,
  overlap, holds, row locks, pricing order). Read it before touching
  availability, pricing or booking writes.
- `docs/project/architecture.md`, `design-system.md`, `conventions.md`
  (Definition of Done), `decisions.md` (ADRs and open client questions).
- `docs/modules/Mxx-*.md`: per-module spec and task checklist.
- `docs/project/legacy-reference.md`: how the client's current system behaves.
  Copy its behaviour, never its code.
- Project skills in `.claude/skills/` (`rtbp-backend-slice`, `rtbp-booking-engine`,
  `rtbp-admin-screen`, `rtbp-settings-section`, `rtbp-access-and-audit`,
  `rtbp-legacy-lookup`) and the `rtbp-critical-reviewer` agent.

Terminology: **room type** (the diagram's "package"; it owns its rooms exclusively),
**rate plan** (a reusable stay window such as Half Day 08:30–17:00, Overnight
20:00→08:00 or 24 h flexible), **booking line** (one physical room × one window).

## Architecture Overview

**Namespace:** `RadiusTheme\RadiusHotelBooking` — PSR-4 autoloaded from `includes/`.

### Backend layers

1. **Controllers** (`includes/Controllers/`) — extend `BaseController` (which
   extends `WP_REST_Controller`). `BaseController` already implements
   index/show/store/update/destroy against the injected repository; a subclass
   supplies middleware, `transformItem()`, `transformCollection()`,
   `getValidationRules()` and `getResourceType()`.
2. **Services** (`includes/Services/`) — business logic, repositories injected
   via the constructor.
3. **Repositories** (`includes/Repositories/`) — data access, extend
   `BaseRepository`, which wraps the model with all/find/create/update/delete/paginate.
4. **Models** (`includes/Models/`) — extend `BaseModel`: `fillable`, `casts`,
   `rules`, relationships (`hasMany`/`belongsTo`/`hasOne`), soft deletes, and
   lifecycle events (`creating`, `created`, `updating`, `deleted`, …).
5. **Resources** (`includes/Resources/`) — API response shaping.

### DI container

`Container::bind()` / `singleton()` / `resolve()` / `make()`. Every binding is
registered in `includes/Core/config/bindings.php`, loaded by the `Config` class
on `plugins_loaded`. Bindings are lazy closures.

### REST API

* Namespace: `/wp-json/radius-hotel-booking/v1/`
* Routes in `includes/Routes/routes.php`, via `$this->router->resource()` for CRUD
  or `get`/`post`/`put`/`delete` for individual endpoints.
* `ApiResponse` fluent interface: `success()`, `created()`, `error()`,
  `unauthorized()`, `notFound()`, `validationError()`. Every response is
  `{ success, status_code, message, data, errors, meta }` — `src/api/client.js`
  unwraps exactly this shape.
* Middleware in `includes/Core/Api/Middleware/`: Auth, Permission, RateLimit.
  **`PermissionMiddleware` requires a valid `x-wp-nonce` AND a `Referer` that
  starts with `home_url()`** — a REST client without a Referer gets a 403
  "Invalid request source". Public endpoints opt out through the
  `rtbp_public_api_routes` / `rtbp_public_api_route_patterns` filters rather
  than being hard-coded.
* Validation: rule objects in `includes/Core/Api/Validation/Rules/`.

### Frontend (React)

Three webpack entry points (`webpack.config.js`):

* `admin` → `src/admin/main.jsx` — admin SPA, mounts to `#radius-hotel-booking`, HashRouter
* `site` → `src/site/main.jsx` — public app, mounts to `#radius-hotel-booking-site`
* `blocks` → `src/blocks/index.js` — Gutenberg block editor UI

Path alias `@/` → `src/`. Screens live in `src/modules/`. UI components are
shadcn/ui (`src/components/ui/`) on Radix primitives. API calls go through
`src/api/client.js`.

**Asset loading (important, and different from the Vite original):**
`@wordpress/scripts` emits `build/<entry>.js` plus `build/<entry>.asset.php`,
which returns the entry's WordPress script dependencies and a content-hash
version. `includes/Assets/LoadAssets.php` reads that file, so dependencies and
cache busting are automatic. There is **no dev-server mode and no manifest** —
`npm run start` and `npm run build` write the same files to `build/`. "My change
doesn't show" therefore means the build didn't run, not that a dev server needs
starting.

The public bundle is **registered** on every front-end request and **enqueued**
only where the shortcode or block is present. Anything rendering outside the
post content (an Elementor widget, a theme template) calls
`LoadAssets::enqueue_site_app()`.

### Tailwind scoping

`tailwind.config.js` sets `important: '.rtbp-root'`, so every utility is scoped
under that class and the plugin's CSS cannot leak into wp-admin or a theme.
Anything rendered in a portal (Radix dialogs, dropdowns, selects) mounts outside
the app tree, so **those elements need `rtbp-root` on their own content node** —
see `ItemFormDialog`'s `DialogContent` and `SelectContent`.

### Event system

`EventDispatcher::dispatch()` / `listen()`, configured in
`includes/Core/config/events.php`. Model lifecycle events fire automatically and
are named `<ModelFqcn>.<event>`.

### Database

Tables are prefixed with `radius_hotel_booking_` via `rtbp_table_prefix()`. Table
schemas live in `includes/Databases/Table/` and are registered in
`Databases\DatabaseManager`. Migrations run on activation and on the first
request after `RadiusHotelBooking::DBVERSION` changes — `Setup\Installer` is
hooked on both `admin_init` and `plugins_loaded`, so a schema upgrade also
happens on anonymous front-end traffic (this matters: `admin_init` never fires
for logged-out visitors, and without it a release can serve "Unknown column"
errors until an admin logs in).

**Bump `DBVERSION` whenever a table changes**, or the migration will not run.

### Permissions

`Core\Permissions\Capabilities` is the single source of truth: capability
constants, the role→cap map, the UI catalog. `PermissionsInstaller::sync()`
provisions the roles, version-gated by `ROLES_VERSION` — **bump it when the cap
set changes**. Administrators implicitly hold every `rtbp_*` capability via
`PermissionsManager`, so a plugin cap can never lock a site admin out.

### Emails

`Emails\EmailManager` instantiates each `BaseEmail` subclass listed in the
`rtbp_email_classes` filter; the base class hooks itself onto the action the
subclass names in `get_trigger_action()`. Templates live in
`templates/emails/` and are overridable from a theme at
`radius-hotel-booking/emails/…`. The header/footer are pure hook scaffolding —
`Emails\TemplateHooks` supplies everything they render.

No scheduler ships with the hotel booking: `should_queue()` is false unless
something hooks `rtbp_queue_email`.

### i18n (three layers — read before touching translations)

1. **Externalised runtime.** `@wordpress/i18n` is externalised to `wp.i18n` by
   webpack's dependency-extraction plugin, so bundles share WordPress's locale
   data. (The Vite original needed a shim for this; webpack does not.)
2. **PHP merge filter.** `LoadAssets::load_merged_script_translations()`, hooked
   on `pre_load_script_translations`, merges every
   `radius-hotel-booking-{locale}-*.json` across the three folders Loco can save
   to (plugin-local `languages/`, `wp-content/languages/loco/plugins/`,
   `wp-content/languages/plugins/`) **and** the compiled `.mo`/`.l10n.php`
   catalog into one `locale_data` block. This exists because WordPress finds a
   script's JSON by hashing the bundle path, which content hashes and Loco's
   per-source sharding both break. Files are merged newest-last.
   `resolve_newest_mofile()` does the same for the PHP catalog.
3. **Enqueue.** Every entry calls `register_script_translations()` with domain
   `radius-hotel-booking` and path `<plugin>/languages`.

In JS: `import { __ } from '@wordpress/i18n'` then
`__( 'My text', 'radius-hotel-booking' )`. Use `sprintf` for interpolation —
never concatenate, since `wp i18n make-pot` only extracts string literals.

### Free / Pro / client add-on split (ADR-014 to ADR-017)

The product ships as **three plugins**. Every module doc has a **Tier** line
that says which plugin each task belongs to.

| Plugin | Repo folder (sibling of this one) | Namespace, prefix | Ships to |
|---|---|---|---|
| **Free** (this repo) | `radius-hotel-booking` | `RadiusTheme\RadiusHotelBooking`, `rtbp_` | wordpress.org |
| **Pro** | `radius-hotel-booking-pro` | `RadiusTheme\RadiusHotelBookingPro`, `rtbp_pro_` | paying customers |
| **Client add-on** | `radius-hotel-booking-residencetata` | `RadiusTheme\RadiusHotelBookingTata`, `rtbp_tata_` | Residence TATA only |

- **Pro code never lives in the free plugin.** wordpress.org rejects
  "trialware", meaning features locked behind payment. The free plugin exposes
  **hooks, registries and JS extension points**. The Pro plugin supplies the
  code that uses them. Never write `if ( rtbp_addon_active() ) { …pro feature… }`
  in this repo. The `is_addon` flag is only for showing an upsell notice.
- Pro and the client add-on reuse this plugin's framework: `Abstracts`,
  `Container`, `Schema`, the router, and the `window.rtbp` JS runtime. They
  never copy it. Every call into the free plugin is guarded with
  `class_exists()` or `function_exists()`, and each add-on loads only when the
  free plugin is active.
- Existing extension points: `rtbp_api_route_paths`,
  `rtbp_register_addon_routes`, `rtbp_register_addon_integrations`,
  `rtbp_email_classes`, `rtbp_settings`, `rtbp_capabilities`,
  `rtbp_migration_classes`.
- Extension points added as the modules are built: `rtbp_access_keys`,
  `rtbp_access_level`, `rtbp_activity` (the free plugin *emits* activity; Pro
  *stores* it), `rtbp_price_steps`, and the JS filters `rtbp.admin.routes`,
  `rtbp.booking.panels`, `rtbp.api.error`. The full list is in
  `docs/project/architecture.md` §9.
- **Every Pro feature is switchable** (ADR-017): it is listed in Pro's
  `FeatureRegistry`, lives in a `Features\<Area>\<Name>Feature` class, and hooks in
  only from `boot()`. The switches are under Settings → Pro features, a tab Pro adds to
  this plugin's Settings screen.

### Bootstrap flow

`radius-hotel-booking.php` → Composer autoloader → constants →
`RadiusHotelBooking::init()` (singleton) → `Config` on `plugins_loaded` (loads
`bindings.php` + `events.php`) → components resolved lazily from the container.

Key constants: `RADIUS_HOTEL_BOOKING_VERSION`, `_DB_VERSION`, `_SLUG`, `_DIR`,
`_PATH`, `_INCLUDES`, `_URL`, `_BUILD`, `_ASSETS`, `_TEMPLATE_PATH`,
`_TEMPLATES_DIR`.

Multisite is supported: network activation installs per-site, and
`wp_insert_site` sets new subsites up.

## Key conventions

- Settings live in `wp_options` as `rtbp_<section>_settings`; sections and their
  defaults are declared in `Helpers\SettingsHelper`.
- Hook names are prefixed `rtbp_`; so are capabilities, option keys and global
  functions.
- Text domain: `radius-hotel-booking`.
- PHP 7.4+ / WordPress 5.5.0+.
- WordPress coding standards enforced via PHPCS (`phpcs.xml`); database queries
  must use `$wpdb->prepare()` and output must be escaped.
- The shortcode, the block and the Elementor widget all render the **same**
  template (`templates/items/item-list.php`) — keep it that way so the three
  paths can't drift.

## Renaming the hotel booking

See the table and the one-shot `perl` command in README.md. Order matters:
replace the longest strings first (`RadiusTheme\RadiusHotelBooking` before
`RadiusHotelBooking`, `radius_hotel_booking` before `radius-hotel-booking`).
