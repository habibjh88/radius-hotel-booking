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
validated and sanitised values, defaults for everything, translatable text fields, and a change
history. M17 builds the panel and the sections it owns. Later modules **add** their sections
through the same registry (`rtbp-settings-section` skill).

## Scope

| Feature | Summary | Built in |
|---|---|---|
| — | `Settings\SettingsSchema` registry (type, default, min/max, enum, `sensitive`, `translatable`), `rtbp_setting( $section, $key )` helper, server-side sanitise and validate, `rtbp_settings_updated` hook with before/after | T1 |
| — | Settings UI: vertical tabs, a Select on mobile, `SettingsSection`, `TranslatableField` (FR/EN), `MediaField` (WP media library), a sticky unsaved-changes bar, reset section to defaults | T2 |
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
  `SettingsSection`, `TranslatableField`, `MediaField`, and the `rtbp_settings_updated` hook.

## Tasks

- [ ] T1 [Free] `SettingsSchema` + sanitiser + `rtbp_setting()` + refactor `SettingsHelper` / `SettingsService` onto it + hook; sanitising of each type verified on the Local site
- [ ] T2 [Free] Settings UI framework (tabs registry, `SettingsSection`, `TranslatableField`, `MediaField`, dirty bar, reset)
- [ ] T3 [Free] General and Booking rules sections (PHP schema + UI)
- [ ] T4 [Free] Notifications (with sound upload and preview) and E-mail sections
- [ ] T5 [Free] General → "Delete all data on uninstall" (off by default; the key `general.deleteDataOnUninstall` and `uninstall.php` exist since M00 T8, so only the toggle is left) + the single dismissible Upgrade notice and link on the Settings screen only (ADR-016)

## Acceptance

1. A fresh install shows every section with sensible defaults, without saving anything first.
2. Enter an invalid value (a negative booking window, cut-off time `25:00`). The field shows an
   error and nothing is saved.
3. Switch the translatable field to FR, type, save, reload. Both languages persist.
4. Upload a sound and preview it. Reset the section, and the default chime returns.
5. Usable at 360 px.

## Legacy reference

`legacy-reference.md` → Module 17 (the legacy settings option names and defaults).

## Migration notes

Map each legacy settings option to its new `section.key` (see the legacy reference).

## Progress notes
