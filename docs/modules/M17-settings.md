# M17 — Settings and system

| | |
|---|---|
| **Client module** | 17 — Settings and system |
| **Features** | 17.1–17.13 (the panel and its sections; several sections are *filled* by later modules) |
| **Depends on** | M00 |
| **Tier** | Free (Pro and the client add-on register their own sections) |
| **Engine** | no |
| **Critical review** | no |

## Goal

One Settings panel for the whole system: a schema-driven registry of sections with typed,
validated and sanitised values, defaults for everything, and a change history. Text is English; other languages come from a
translation plugin (ADR-019). M17 builds the panel and the sections it owns. Later modules **add** their sections
through the same registry (`rtbp-settings-section` skill).

## Scope

| Feature | Summary | Built in |
|---|---|---|
| — | `Settings\SettingsSchema` registry (type, default, min/max, enum, `sensitive`), `rtbp_setting( $section, $key )` helper, server-side sanitise and validate, `rtbp_settings_updated` hook with before/after | T1 |
| — | Settings UI: vertical tabs, a Select on mobile, `SettingsSection`, `MediaField` (WP media library), a sticky unsaved-changes bar, reset section to defaults | T2 |
| 17.10 | **General**: hotel name, legal name, address, phone, e-mail, logo, tax number, CNPS number, date format, time format (12h/24h), currency (code, symbol, symbol position, thousand and decimal separators, decimals; the keys already exist in `SettingsHelper::general()` and are read by `Support\Money` since M00 T2). Shared with invoices (M05) and payslips (M15, feature 15.21) | T3 |
| 17.7 | **Booking rules**: booking window (days, 0 = unlimited) | T3 |
| 17.8 | Same-day booking allowed + cut-off time | T3 |
| 17.9 | Manual approval required | T3 |
| 17.11 | Unavailable-room display: hide / show disabled | T3 |
| 8.9 | Maximum child age | T3 |
| — | Hold duration (minutes), buffer between stays (minutes, D4), default check-in/check-out times for nightly plans | T3 |
| 17.1 | **Notifications**: new-booking alert on/off, poll interval | T4 |
| 17.2 | Custom notification sound (upload) or built-in chime, with a preview button | T4 |
| — | **E-mail**: sender name and address, reply-to, per-template on/off. Carries over the framework's `email` section | T4 |
| 17.12 | Payment instructions: the section is **registered by M05** | M05 |
| 17.13 | Invoice settings: the section is **registered by M05** | M05 |
| 17.4, 17.5, 17.6 | Passcode settings, the permission map and profile-field defaults: **registered by M13** | M13 |
| 17.3 | Vacation quota: **registered by M15** | M15 |
| — | Log archiving schedule: **registered by M14**. Scheduled exports: **registered by M11** | M14, M11 |

## Data

WordPress options `rtbp_<section>_settings`. No tables.

## API

| Method | Route | Access key | Purpose |
|---|---|---|---|
| GET | `settings` | `page.settings` | All sections the user may see (sensitive keys stripped) |
| GET/PUT | `settings/{section}` | `settings.<section>` | Read or update one section |
| PUT | `settings/{section}/reset` | `settings.<section>` | Restore the section's defaults |
| GET | `settings/schema` | `page.settings` | The schema, so the UI can render hints, limits and options |

(Access keys are enforced once M13 lands. Until then the endpoints use `rtbp_manage_settings`.)

## Access keys (registered in M13)

`page.settings`, and `settings.general`, `settings.booking`, `settings.notifications`,
`settings.email`. Each section a later module adds brings its own key.

## Activity log actions (M14)

`settings.updated` (section, with before/after per key, sensitive values masked). M14 subscribes
to `rtbp_settings_updated`.

## Consumes / provides

- **Consumes:** M00 composites, `Dates`.
- **Provides:** `SettingsSchema::register( $section, array $schema )`, `rtbp_setting()`,
  `SettingsSection`, `MediaField`, and the `rtbp_settings_updated` hook.

## Tasks

- [x] T1 [Free] `SettingsSchema` + sanitiser + `rtbp_setting()` + refactor `SettingsHelper` / `SettingsService` onto it + hook; sanitising of each type verified on the Local site — done 2026-09-28
- [x] T2a [Free] Settings UI framework: sections registry (vertical tabs, a Select on mobile), `SettingsSection`, server field errors on the fields, sticky unsaved-changes bar, reset section to defaults; move Display (brand colour) and the add-on tabs (`rtbp.settings.sections`) onto it — done 2026-09-28
- [x] T2b [Free] `MediaField` (WP media library, attachment id, mime filter), in the `#/dev/ui` kit. (`TranslatableField` dropped, ADR-019) — done 2026-09-28
- [x] T3 [Free] General and Booking rules sections (PHP schema + UI) — done 2026-09-28
- [x] T4 [Free] Notifications (with sound upload and preview) and E-mail sections — done 2026-09-28
- [x] T5 [Free] General → "Delete all data on uninstall" (off by default; the key `general.deleteDataOnUninstall` and `uninstall.php` exist since M00 T8, so only the toggle is left) + the single dismissible Upgrade notice and link on the Settings screen only (ADR-016) — done 2026-09-28

## Acceptance

1. A fresh install shows every section with sensible defaults, without saving anything first.
2. Enter an invalid value (a negative booking window, cut-off time `25:00`). The field shows an
   error and nothing is saved.
3. Upload a sound and preview it. Reset the section, and the default chime returns.
4. Usable at 360 px.

## Legacy reference

`legacy-reference.md` → Module 17 (the legacy settings option names and defaults).

## Migration notes

Map each legacy settings option to its new `section.key` (see the legacy reference).

| Legacy (`woocommerce_hbfwc_settings` unless noted) | New | Conversion |
|---|---|---|
| `booking_window` (RTA option; days ahead, default 1, `-1` = any date) | `booking.bookingWindowDays` | `-1` → `0` (no limit) |
| `sameday_enabled` (`yes`/`no`) | `booking.sameDayEnabled` | `yes` → true |
| `sameday_cutoff` (`HH:MM`, default 14:00) | `booking.sameDayCutoff` | as is |
| `manual_approval` (`yes`/`''`) | `booking.manualApproval` | `yes` → true |
| `availability_visibility` (`hide`/`show`) | `booking.unavailableRooms` | `show` → `disable` |
| `max_child_age` (default 16) | `booking.maxChildAge` | int, capped at 17 |
| `date_format` | `general.dateFormat` | PHP format, as is |
| `payment_time_limit`, `approval_time_limit`, `approval_expired_action` | M05 (payment deadline, auto-release) | — |
| `block_checkout`, `show_rooms_field`, `exclude_shop`, `addtocart_label` | not carried over (WooCommerce shop behaviour) | — |
| `hbfwc_rta_notify_new_booking` (option) | `notifications.newBookingAlert` | truthy → true |
| `hbfwc_rta_notify_sound_id` (option, attachment id) | `notifications.soundId` | re-map the attachment id after the media import; 0 = chime |

## Progress notes
- 2026-09-28, **T1** (schema, validator, service):
  - **`Settings\SettingsSchema`** is the single source for every section: types (`string text
    email url int float bool enum color time media array`), defaults (callables
    resolved lazily), `min`/`max`/`maxLength`/`options`/`mime`, `sensitive`, and a custom
    `sanitize` callable. Core sections are in `Settings\CoreSettings`. Modules and add-ons
    register from the `rtbp_settings_schema_init` action (or the `rtbp_settings_schema` filter).
    `for_client()` is the JSON form, without callables or sensitive defaults.
  - **`Settings\SettingsValidator`:**
    - A whitelist: only schema keys are kept.
    - A value that can't be valid (out of range, not an option, `25:00`, an image where audio is
      expected, a non-http URL) becomes a translated field error, never a silent change.
    - Currency separators have their own sanitiser that keeps a space (XOF `15 000 CFA`).
  - **`SettingsService` is the only write path:** merge, validate (422 `invalid_settings` with
    field errors, nothing saved), save, then `rtbp_settings_updated( $section, $before, $after )`,
    only when something changed. Sensitive keys are stored but never returned.
  - **Sections without a schema** (Pro's `pro_features`, declared through the `rtbp_settings`
    defaults filter) still work: their keys are whitelisted, and the add-on sanitises its own
    option.
  - **`SettingsHelper`** keeps its API (a wrapper over the schema). `get_setting()` drops keys
    no longer declared.
  - **`rtbp_setting( $section, $key, $default )`** is in core-functions.
  - **API:**
    - Added `GET settings/schema` and `PUT settings/{section}/reset`. Fixed paths are registered
      before the `{section}` pattern.
    - `PUT settings` (bulk) validates every section first; errors are keyed `section.key`.
    - The controller's unrouted `export` / `import` (which saved raw input) are gone.
  - **Dropped boilerplate keys with no reader:** `general.perPage`, `general.enableDebug`,
    `display.cardRadius`, `display.layout`, `display.columns`. The old Settings screen still
    shows two of them until T2a replaces it; they are dropped on save.
  - **Verified on food-menu.local:**
    - Every type, through a throwaway section.
    - The service: whitelist, 422 with nothing saved, the hook with before/after only on real
      changes, sensitive keys stored but not returned, reset, a 404 for an unknown section.
    - REST: schema, invalid → 422 per field, XOF → `15 000 CFA`, reset, bulk errors.
    - The browser API client: `error.code` + field message.
    - Options were restored afterwards.
  - **Caught by the REST test, not by phpcs:** the rewritten controller had dropped the abstract
    `getValidationRules()`, which fatals every settings request.
  - **Strings:** all translatable. The `.pot` is regenerated at the end of M17 (T5); no French
    catalogue work (ADR-019).
- 2026-09-28, **T2a** (Settings UI framework):
  - **`src/modules/Settings/index.jsx`**: vertical tabs (a Select below `md`), the active tab in
    the URL (`#/settings?section=display`), a dirty dot per tab, a `beforeunload` warning, a
    sticky unsaved-changes bar (Save / Discard) and "Reset to defaults" behind a `ConfirmDialog`.
    Only tabs whose section the server returned are shown.
  - **`useSettingsDrafts`**: saved values, a draft per section (switching tabs keeps edits),
    server field errors per section (`errors[ section ][ key ]`, cleared as the field is edited),
    save / discard / reset.
  - **`SettingsSection`** (`components/common`, also on `window.rtbp.ui`): the card every tab
    is built from.
  - **Tabs:** `sections/index.js` lists the core tabs (General, Display), lazy-loaded. Add-ons
    use `rtbp.settings.sections` with `{ key, label, description?, icon?, Component }`. The
    older `render( props )` form still works, and Pro's `pro_features` tab is unchanged.
  - **Brand colour:** previews live, Discard puts the saved colour back, and so does leaving
    the screen.
  - **Verified on food-menu.local:** an invalid e-mail shows the server message on the field
    and nothing is saved. Discard, save + reload, and reset to the default colour all work.
    Switching tabs works (Pro features included), and the Select is used on a phone-width
    window. No console errors. The display option was left at its default.
- 2026-09-28, **ADR-019** (English only): the `translatable` settings type is removed from
  `SettingsSchema` and `SettingsValidator` (nothing used it), and `TranslatableField` is dropped
  from T2b. Checked on food-menu.local: the schema still loads (`general`, `display`, `email`).
- 2026-09-28, **T2b** (`MediaField`):
  - **`components/common/MediaField.jsx`** (also on `window.rtbp.ui`) stores an attachment id
    (0 = none) and opens the WordPress media modal.
    - `accept` takes the same mime prefixes as a `media` setting's `mime` option. It filters
      the modal's library and refuses a wrong upload on the client too.
    - The preview is an image thumbnail, an audio player, or the file name. A stored id that
      was deleted shows "no longer in the media library".
    - Without `wp.media` (or without `upload_files`) the field says so instead of opening.
    - `wp_enqueue_media()` was already called for the staff app, in wp-admin and on the
      front-end dashboard.
  - **Verified on food-menu.local (`#/dev/ui`):**
    - The audio field's modal lists audio only. Picking a file shows the player and sets the
      id; picking an image shows the thumbnail; Remove returns 0.
    - The missing-id and server-error states render correctly.
    - `wp.media.attachment( id ).fetch()` (the reload path) returns the right file.
    - At 320 px the field fits with no overflow, and there are no console errors.
  - **Test file:** `rtbp-test-chime.wav` (attachment 6556) was added to the site's media
    library and is kept for T4's notification sound.
- 2026-09-28, **T3** (General and Booking rules):
  - **General** gains `legalName`, `address` (multi-line), `phone`, `logo` (media, images only),
    `taxNumber` and `cnpsNumber`.
    - The tab has four cards: Hotel details, Registration numbers, Date and time, Currency.
    - The date format is chosen from a list shown as sample dates, or typed as a custom PHP
      format.
    - Currency has quick-setup buttons (XOF, EUR, USD) and a live preview of the unsaved
      values (`formatMoney( …, { currency } )`, `formatDateAs()` in `lib/format.js`).
  - **Booking** (new core section, access key `settings.booking` in M13):
    - `bookingWindowDays` 0 = no limit; `sameDayEnabled` off and `sameDayCutoff` 14:00;
      `manualApproval` off; `unavailableRooms` hide|disable; `maxChildAge` 16. These follow
      the legacy defaults, except the window (legacy 1 day; 0 here, and the importer copies the
      client's value).
    - `holdMinutes` 15, `bufferMinutes` 0 (D4), `checkInTime` 14:00, `checkOutTime` 12:00.
    - Nothing reads these yet. M08 (availability), M02/M04 (booking flows) and holds are the
      readers, through `rtbp_setting( 'booking', … )`.
  - **Fixed:** the e-mail header read `general.logoUrl`, a key that never existed, so the logo
    never showed. It now uses the `logo` attachment.
  - **Saving General:**
    - `Money::flush()` runs, so the same request formats with the new currency.
    - A tab's `onSaved( values )` hook lets General call `setFormatConfig()`, so amounts and
      dates on other screens update without a reload.
  - **Verified on food-menu.local:**
    - `wp eval`: fresh defaults; nothing saved after a negative window, `25:00`, child age 18,
      an unknown enum, or an audio file as the logo.
    - `wp eval`: a valid save gives `sameDayCutoff` `9:30` → `09:30`; Money changes within the
      same request; the e-mail header shows the logo.
    - Browser: −5 shows "Enter 0 or more." on the field; REST with `25:00` returns 422 with
      the field message; valid values survive a reload.
    - Browser: the CFA preset previews `1 234 568 CFA` and, after saving, `formatMoney(15000)`
      is `15 000 CFA` without a reload.
    - Both tabs at 358 px (in an iframe, because the Chrome window would not resize): no
      horizontal overflow, no console errors.
    - Options were restored afterwards.
- 2026-09-28, **T4** (Notifications and E-mail):
  - **Notifications** (new core section, access key `settings.notifications` in M13):
    - `newBookingAlert` on, `playSound` on, `soundId` (media, audio only; 0 = built-in chime),
      `pollSeconds` 30 (10–300, ADR-006).
    - Localised for the poller (M01) as `settings.notifications` and `notify_sound_url`. The
      tab's `onSaved` updates both without a reload.
  - **Built-in chime:** `src/lib/sound.js` synthesises two sine notes with the Web Audio API, so
    no audio file ships (nothing to license, ADR-016).
    - `playNotificationSound( url )` plays the hotel's file or the chime and resolves `false`
      when the browser blocks it (the preview then says so).
    - It is on `window.rtbp.lib.sound`.
  - **E-mail:**
    - The master switch `email.enabled` existed but nothing read it. `BaseEmail::is_enabled()`
      now checks it first, then `email.templates[ id ]` (new, sanitised by
      `CoreSettings::switches`), then the template's own default (`is_enabled_by_default()`).
    - The tab lists the registered templates (localised as `email_templates` from
      `radius_hotel_booking()->emails`). It shows an empty state until M05 adds the first ones.
      The template switches are greyed out while the master switch is off.
    - `email.use_queue` is kept but not shown: it is developer-only, and no queue ships.
  - **Verified on food-menu.local:**
    - `wp eval`: defaults; a 5 s poll interval and an image as the sound are refused; a WAV is
      accepted; `templates` keys are sanitised, and a non-array is refused.
    - `wp eval`, `is_enabled()` with a throwaway test template (a temporary mu-plugin, removed
      afterwards): default on, switched off → false, master off → false, master back on → true.
    - Browser: the chime preview creates its two oscillators. Picking the WAV and previewing
      plays that file. Saving updates the localised sound URL. Reset brings back "Built-in
      chime" (acceptance 4).
    - Browser: the E-mail tab lists the test template, the master switch disables its switch,
      and turning it off saves `{ t4_test_confirmed: false }`.
    - Both tabs at 358 px (iframe) have no overflow.
    - With Pro deactivated, the free tabs (General, Booking rules, Notifications, E-mail,
      Display) and the empty e-mail list show with no console errors. Pro was reactivated and
      the options restored.
- 2026-09-28, **T5** (uninstall switch, upgrade notice):
  - **General → Uninstall:** a switch for `deleteDataOnUninstall`, which `uninstall.php` has read
    since M00 T8. Turning it on asks for confirmation first; turning it off doesn't.
  - **`Admin\UpgradeNotice`** (ADR-016) is the only upsell, shown only while Pro is inactive:
    - An "Upgrade" link on the Plugins screen row.
    - A dismissible notice at the top of Settings, for `manage_options` users. It never
      appears on working screens.
    - The dismissal is user meta `rtbp_upgrade_notice_dismissed`, registered with
      `show_in_rest` (the user can only write their own). The screen writes it through core
      `wp/v2/users/me`, so there is no endpoint of our own.
    - The URL defaults to `https://radiustheme.com` and can be changed with the
      `rtbp_upgrade_url` filter. **Open:** the real Pro product page URL.
  - **`.pot` regenerated** with every M17 string. No French catalogue work (ADR-019).
  - **Verified on food-menu.local:**
    - `wp eval`: the Plugins row has "Settings" only while Pro is active, and "Settings" plus
      "Upgrade" once Pro is deactivated. `UpgradeNotice::params()` is `show: false` with Pro
      active.
    - Browser, with Pro off: the notice shows on Settings but not on the Dashboard. Dismissing
      it stores meta `1` and it stays gone after a reload. At 358 px it fits with no overflow.
    - Browser: the uninstall switch opens the confirmation; confirming turns it on and marks
      the tab unsaved; Discard turns it off. It was not saved.
    - The dismissal meta was cleared, Pro reactivated, and no console errors.
- 2026-09-28, **Module close checks** (`conventions.md` §6):
  - **Feature ids:** 17.1, 17.2 and 17.7–17.11 are built here, and so is 8.9. 17.3–17.6,
    17.12 and 17.13 belong to M15, M13 and M05 (the Scope table).
  - **Access keys:** `settings.general|booking|notifications|email|display` are enforced once M13
    lands. Until then `rtbp_manage_settings` applies.
  - **Acceptance 1** (`wp eval`, every option ignored): each section returns its defaults.
    Steps 2–4 were verified in T3, T4 and T2a–T5.
  - **Critical review:** not required (no money, availability or permission code).
  - **Pro deactivated:** the free Settings screen works (T4, T5).
  - **`wp plugin check`** on the dev checkout (a symlink to the repo) flags only things the
    release zip leaves out: hidden files, `bin/*.sh`, and `includes/Commands` (the WP-CLI
    generators). What remains for the zip is **`Tested up to: 6.7` < 7.1** in `readme.txt`,
    release metadata to update when the UNRELEASE block ships.
