---
name: rtbp-settings-section
description: How to add or extend a section of the Radius Hotel Booking Settings panel (Module 17) — PHP defaults and sanitising in SettingsHelper, access key, activity logging, the React tab, and reading the setting elsewhere. Use whenever a module introduces a configurable option.
---

# Adding a settings section

Every configurable behaviour lives in the Settings panel. Modules **add** sections; they don't
build their own settings screens.

## PHP

1. Declare the section once, in the schema (defaults, sanitising, validation and the schema the
   UI reads all come from it). Core sections live in `includes/Settings/CoreSettings.php`; a
   module adds its own with `SettingsSchema::register()` from the `rtbp_settings_schema_init`
   action:
   ```php
   add_action( 'rtbp_settings_schema_init', static function () {
       SettingsSchema::register( 'booking', array(
           // Days ahead a guest may book; 0 = unlimited.
           'bookingWindowDays' => array( 'type' => 'int', 'default' => 0, 'min' => 0, 'max' => 3650 ),
           'sameDayCutoff'     => array( 'type' => 'time', 'default' => '14:00' ),
           'unavailableRooms'  => array( 'type' => 'enum', 'options' => array( 'hide', 'disable' ), 'default' => 'hide' ),
       ) );
   } );
   ```
   Types: `string text email url int float bool enum color time media array`.
   Options: `default` (a callable is resolved lazily), `min`/`max`, `maxLength`, `options`,
   `mime` (media), `sensitive => true` (never sent to the browser: a PIN hash, a secret),
   `sanitize` (a callable returning the value or a `WP_Error`). Keys are camelCase; the option
   is `rtbp_<section>_settings`. One-line comment per key (unit, allowed values).
   Add-ons (Pro, client) call the same `SettingsSchema::register()`, guarded with
   `class_exists()`.
2. Reading a value anywhere in PHP: `rtbp_setting( 'booking', 'sameDayCutoff' )`, never
   `get_option` directly, so defaults always apply. A whole section:
   `SettingsHelper::get_setting( 'booking' )`.
3. Writing: only through `SettingsService::updateSection()` / `resetSection()`. They validate
   (422 with field errors, nothing saved), save, and fire
   `rtbp_settings_updated( $section, $before, $after )`, which the activity log (M14) listens
   to. Mask sensitive keys with `SettingsSchema::strip_sensitive()` before logging.
4. Access key `settings.<section>` in the access registry (M13).

## React

1. `src/modules/Settings/sections/<Section>.jsx` — a stack of `SettingsSection` cards using
   `FormSection`/`Field`. Register it in `src/modules/Settings/sections/index.js` with
   `key`, `label`, `icon`, `accessKey`, `group`.
2. Text settings (payment instructions, locked message, invoice footer) are plain English
   strings. No per-language fields: other languages come from a translation plugin (ADR-019).
3. Values needed by other screens at load time are localised through
   `LoadAssets::admin_params()['settings'][<section>]` — **never** sensitive keys.

## Checklist

- Default exists for every key (a fresh install must work without visiting Settings).
- The feature reading the setting is checked on the Local site with the default and one non-default value.
- Changelog line for the new option.
