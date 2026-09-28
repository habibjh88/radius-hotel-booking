=== Radius Hotel Booking ===
Contributors: radiustheme
Tags: hotel booking, framework, rest-api, react, developer
Requires at least: 5.5.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A WordPress plugin hotel booking: DI container, ORM, migrations, REST router, WP-CLI scaffolding and a React admin.

== Description ==

A starting point for building a WordPress plugin. It ships the plumbing every
plugin needs and one example resource wired through every layer, so you can
delete the example and keep the framework.

* Dependency-injection container with lazy bindings
* Laravel-style ORM with relationships, casts, soft deletes and model events
* Schema builder and migration runner
* REST router with middleware (auth, capability, rate limit) and a validation rule engine
* Capability and role provisioning
* WP-CLI scaffolding: `wp radius-hotel-booking artisan make:module Thing`
* React 19 + Tailwind CSS + shadcn/ui admin, built with @wordpress/scripts
* Gutenberg block, Elementor widget and shortcode, all rendering the same template
* Email system with merge tags and overridable templates
* Three-layer i18n setup that survives content-hashed bundle filenames
* PHPCS and PHPUnit preconfigured

== Installation ==

1. Copy the plugin folder into `wp-content/plugins/`.
2. Run `composer install` and `npm install`.
3. Run `npm run build`.
4. Activate the plugin.

See README.md for how to rename the hotel booking for a new project.

== Changelog ==

= 1.0.0.1 ( UNRELEASE ) =
Removed the wp-env Docker environment and Playwright end-to-end tests; PHPUnit now runs directly.
Moved rtbp_table_prefix() into the core functions file with the other global helpers.
Fixed admin screens failing when another plugin changes WordPress's shared REST request settings.
Added extension points so add-ons can add settings tabs and reuse the admin interface.
New admin dashboard with a grouped sidebar, today's arrivals and departures, and a setup checklist.
The brand colour chosen in Settings now restyles the whole dashboard, including the logo.

= 1.0.0 =
Initial release.
