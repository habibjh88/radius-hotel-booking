---
name: rtbp-settings-section
description: How to add or extend a section of the Radius Hotel Booking Settings panel (Module 17) — PHP defaults and sanitising in SettingsHelper, access key, activity logging, the React tab, and reading the setting elsewhere. Use whenever a module introduces a configurable option.
---

# Adding a settings section

Every configurable behaviour lives in the Settings panel. Modules **add** sections; they don't
build their own settings screens.

## PHP

1. `includes/Helpers/SettingsHelper.php` — add `public static function <section>(): array` with
   every key and its default, and add it to `all()`. Keys are camelCase; the option is
   `rtbp_<section>_settings`. Document each key with a one-line comment (unit, allowed values).
2. Sanitise and validate on save: add the section's schema to the settings schema registry built
   in M17 (`Settings\SettingsSchema::sections()`): type, allowed values, min/max,
   `sensitive => true` for anything that must never be localised to JS (fallback PIN hash, etc.).
3. Reading a value anywhere in PHP: `rtbp_setting( 'booking', 'sameDayCutoff' )` (helper added
   in M17) — never `get_option` directly, so defaults always apply.
4. Access key `settings.<section>` in the access registry (M13); saving is logged by the
   settings service with before/after values (M14) — you get this for free by going through
   `SettingsService::updateSection()`.

## React

1. `src/modules/Settings/sections/<Section>.jsx` — a stack of `SettingsSection` cards using
   `FormSection`/`Field`. Register it in `src/modules/Settings/sections/index.js` with
   `key`, `label`, `icon`, `accessKey`, `group`.
2. Translatable text settings (payment instructions, locked message, invoice footer) use the
   `TranslatableField` component (FR + EN tabs) and are stored as `{ fr: '', en: '' }`.
3. Values needed by other screens at load time are localised through
   `LoadAssets::admin_params()['settings'][<section>]` — **never** sensitive keys.

## Checklist

- Default exists for every key (a fresh install must work without visiting Settings).
- The feature reading the setting is checked on the Local site with the default and one non-default value.
- Changelog line for the new option.
