---
name: rtbp-backend-slice
description: How to add a domain entity end-to-end in the Radius Hotel Booking PHP framework — migration table, model, repository, service, resource, controller, route, DI binding, events and verification. Use whenever creating or changing a table, model, repository, service, REST controller or route in this plugin.
---

# Adding a backend slice

The framework is Laravel-flavoured but runs on `$wpdb`. Namespace
`RadiusTheme\RadiusHotelBooking`, PSR-4 from `includes/`. The `Item` slice (being removed in M00)
was the reference; after M00, use the first real slice built (Floors in M06) as the reference.

Scaffold with WP-CLI when a Local shell is available (`wp radius-hotel-booking artisan make:module Floor`),
otherwise create files by hand following the patterns below.

## In Pro or the client add-on

The same layers apply, in the add-on's own namespace (`RadiusTheme\RadiusHotelBookingPro\…` or
`…\RadiusHotelBookingTata\…`). Add-ons extend the free plugin's `Abstracts` and use its
`Container` and `Schema`; they never copy them. Add-ons register through filters, not by editing
free:

| What | How |
|---|---|
| Tables | `rtbp_migration_classes`, with the table prefix `rtbp_table_prefix()` + `pro_…` / `tata_…` and the add-on's own `DBVERSION` |
| Routes | `rtbp_register_addon_routes` |
| Settings | `SettingsSchema::register()` |
| Access keys | `rtbp_access_keys` |
| Activity actions | `rtbp_activity_actions` |

Guard every free symbol with `class_exists()`. Free never references add-on classes.

**Every Pro feature is switchable (ADR-017).** Put the feature's wiring in
`includes/Features/<Area>/<Name>Feature.php`, extending `Features\AbstractFeature`. Its key must
already be in `FeatureRegistry::definitions()`. Register hooks, routes, screens and cron **only
inside `boot()`**, list the free symbols it needs in `requires_free()`, and unschedule cron in
`on_disable()`. Never delete data there. Tables are registered outside the feature (always on).

## Order of work

1. **Table** — `includes/Databases/Table/<Plural>Table.php` extends `Abstracts\Migration`:
   ```php
   Schema::create( $this->tablePrefix . 'floors', function ( Blueprint $table ) {
       $table->id();
       $table->string( 'name', 100 );
       $table->unsignedInteger( 'sort_order' )->default( 0 );
       $table->timestamps();
       $table->index( 'sort_order' );
   } );
   ```
   Register it in `Databases\DatabaseManager::setupMigrations()` (order = dependency order) and
   **bump `RadiusHotelBooking::DBVERSION`** in `radius-hotel-booking.php`. Migration keys embed the
   DB version, so a bump re-runs `Schema::create` (dbDelta-style) for every table — make `up()`
   idempotent. Columns and indexes must match `docs/project/architecture.md` §Data model; if you
   change the model, update that doc in the same task.
   Money: `decimal( 'x', 12, 2 )`. Time: `dateTime( 'start_at' )` + `dateTime( 'start_at_gmt' )`.
   Enum-like values: `string( 'status', 20 )` (not MySQL ENUM — adding a value must not need ALTER).
2. **Model** — `includes/Models/<Singular>.php` extends `BaseModel`: `$table` (without the WP
   prefix, e.g. `'radius_hotel_booking_floors'`), `$fillable`, `$casts` (`int`, `float`, `bool`,
   `json`), relationships via `hasMany` / `belongsTo`. No business logic beyond accessors.
3. **Repository** — `includes/Repositories/<Singular>Repository.php` extends `BaseRepository`
   with `$model`. All non-trivial SQL lives here, named for intent
   (`overlappingLines( array $roomIds, $startGmt, $endGmt )`). Batch-load with `whereIn`.
   Raw SQL: `DB::raw( $sql, $bindings )` / `$wpdb->prepare()` — never interpolate input.
4. **Service** — `includes/Services/<Domain>/<Name>Service.php`. Repositories injected in the
   constructor. Owns validation of business rules, transactions, transitions, activity logging
   (`rtbp-access-and-audit`) and hooks. Throws a domain exception (`Exceptions\DomainException`
   with an error code, created in M00) that the controller turns into `ApiResponse::error()`.
   Message: `__()` (not `esc_html__()`, it is JSON), with the `ExceptionNotEscaped`
   phpcs:ignore line from `conventions.md` §2.3 above the `throw`, so Plugin Check passes.
5. **Resource** — `includes/Resources/<Singular>Resource.php`: the API shape. Never expose
   sensitive fields in `collection()`; use `when()` for fields gated by access.
6. **Controller** — `includes/Controllers/<Singular>Controller.php` extends `BaseController`
   (index/show/store/update/destroy come free). Middleware in the constructor:
   - before M13 is done: `AuthMiddleware` + `PermissionMiddleware( Capabilities::VIEW_DASHBOARD )`
   - after M13: `AccessMiddleware` with per-method access keys (see `rtbp-access-and-audit`).
   Supply `transformItem()`, `transformCollection()`, `getValidationRules()` (rule objects from
   `Core/Api/Validation/Rules/`), `getResourceType()`. Extra endpoints wrap their body in
   `$this->applyMiddleware( $request, fn… )` and call a service.
7. **Route** — `includes/Routes/routes.php`: `$this->router->resource( 'floors', FloorController::class )`
   or explicit verbs for actions: `$this->router->post( 'bookings/(?P<id>\d+)/approve', array( BookingController::class, 'approve' ) )`.
   Actions are `POST <resource>/{id}/<verb>`, not PUT with a status field.
8. **Bindings** — `includes/Core/config/bindings.php`: repositories and services as lazy closures.
9. **Events** — model lifecycle events are `<ModelFqcn>.<event>`; map them to `rtbp_*` hooks in
   `includes/Core/config/events.php` only when an add-on or another module needs the hook.
10. **Verify** — there is no PHPUnit suite. Check the slice on the Local site with a throwaway
    `wp eval` script (create, read, update, delete, and the rules) and through the REST route
    (`docs/project/conventions.md` §4).

## Checks before done

- `./vendor/bin/phpcs --standard=phpcs.xml <files>`
- Response shape is the `ApiResponse` envelope — `src/api/client.js` depends on it.
- The route works with a nonce **and** a Referer starting with `home_url()` (PermissionMiddleware
  rejects requests without it — curl tests must send both).
- PHP syntax: the framework already uses PHP 8.0 features (union types, `mixed`); M00 fixes the
  declared minimum. Don't add PHP 8.1+ syntax (enums, readonly, `never`).
