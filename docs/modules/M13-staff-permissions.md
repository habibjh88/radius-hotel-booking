# M13 — Staff permissions and access control

| | |
|---|---|
| **Client module** | 13 — Staff permissions and access control ★ (the client's second request) |
| **Features** | 13.1–13.16, 17.4, 17.5, 17.6 |
| **Depends on** | M17 (settings sections) |
| **Tier** | Free (open/locked per role, server enforcement) + **Pro** (passcode level, PINs, custom roles, per-person overrides, profile-field control) |
| **Engine** | no |
| **Critical review** | yes (server-side enforcement, PIN handling, brute force) |

## Goal

Every page and every action is set to **Open**, **Passcode** or **Locked**. The setting comes from
a global default, a named role, and a per-person override, in that order of increasing priority. It
is enforced by the server on every request. Staff confirm protected actions with a personal PIN
(hashed, rate-limited). From this module on, every endpoint declares an access key.

## Resolution rule

```
effective(user, key) =
    user override[key]        if set
 ?? role levels[key]          if the user has an access role and the role sets the key
 ?? global default[key]       Settings → Permissions
 ?? registry default[key]     code

administrator (manage_options): locked → passcode   (never locked out; still PIN-confirmed)
```

Overrides and roles merge **key by key**. In the legacy system an override replaced the whole map,
which is a bug we do not copy.

## Scope

| Feature | Summary | Task |
|---|---|---|
| 13.1 | Three levels per page and per action | T1 |
| 13.2 | Page-level control (the page keys below) | T1 |
| 13.3 | Action-level control (the action keys below, about 55) | T1 |
| 13.16 | Server enforcement: `AccessMiddleware` on every route, plus `Access::can()` for service-level re-checks | T2 |
| 13.6 | Personal PIN (4–8 digits), stored as `wp_hash_password`, never returned by any API | T4a |
| 13.7 | Fallback PIN for users without their own (hashed; can be disabled) | T4a |
| 13.8 | Passcode validity in minutes (default 5) | T4a |
| 13.9 | Passcode scope: `global` (one unlock covers everything until it expires) or `per_key` | T4a |
| — | Brute-force limit: 5 wrong PINs → locked out for 15 min, logged (`security.passcode_failed`) | T4a |
| 13.10 | Custom locked message | T4a, T4b |
| 13.4, 17.5 | Global default permission map: a matrix screen (groups × keys; open/locked in free, the third level from Pro) | T3, T5b |
| 13.13, 13.14 | Named access roles with editable templates: *Receptionist*, *Night receptionist*, *Supervisor*, *Housekeeping*, *Accountant*, *Manager* | T5a, T5b |
| 13.5, 13.15 | Per-person: assign a role, override single keys, and see the effective map with the source of each value | T5a, T5b |
| 13.12 | Reset all overrides (everyone, or one person), behind a passcode | T5a, T5b |
| 13.11, 17.6 | Profile-field control (allow/restrict per field): global default, role, per person. Enforced by M16 | T6 |
| 17.4 | Passcode settings: validity, scope, locked message, fallback PIN | T4a, T4b |
| — | React: `useAccess( key )` and routes hidden when locked (free); the `PasscodeDialog` on `rtbp.api.error` (Pro) | T2, T4b |

## Access key registry (initial)

Pages (`kind: page`). The legacy key is shown for the M18 import mapping.

| Key | Legacy | Default |
|---|---|---|
| `page.dashboard` | — | open |
| `page.bookings` | `booking` | open |
| `page.rooms` | `rooms` | open |
| `page.rates` | — | locked |
| `page.availability` | `availability` | open |
| `page.guests` | `customers` | open |
| `page.reports_sales` | analytics `sales` | passcode |
| `page.reports_rooms` | analytics `rooms` | open |
| `page.exports` | `booking-backup` | locked |
| `page.activity_log` | `user-activity` | locked |
| `page.staff` | — | locked |
| `page.payroll` | `payroll` | locked |
| `page.my_payroll` | `my-payroll` | open |
| `page.settings` | — | locked |

Actions (`kind: action`). Every legacy key has a home.

| Key | Legacy | Default |
|---|---|---|
| `bookings.create` | add-booking | open |
| `bookings.approve` · `bookings.decline` · `bookings.cancel` | approve-/decline-/cancel-booking | open · open · passcode |
| `bookings.check_in` · `bookings.check_out` · `bookings.no_show` | check-in · check-out · — | open |
| `bookings.line_add` · `bookings.line_edit` · `bookings.line_remove` | add-/edit-/remove-booking-item | open · passcode · passcode |
| `bookings.note_add` · `bookings.note_edit` · `bookings.note_remove` | add-/edit-/remove-order-note | open · open · passcode |
| `payments.record` | mark-as-paid | open |
| `payments.change_status` | modify-order-status | passcode |
| `payments.void` | — | locked |
| `invoices.send` · `invoices.regenerate` | — | open · passcode |
| `guests.create` · `guests.edit` · `guests.view_id` · `guests.ban` | — · edit-customer · — · ban-users | open · open · open · passcode |
| `guests.note_add` · `guests.note_edit` · `guests.note_remove` | add-/edit-/remove-customer-note | open · open · passcode |
| `rooms.manage` | manage-room-number | locked |
| `room_types.manage` · `rates.manage` · `pricing.manage` · `availability.manage` | — | locked |
| `reports.export` | — | passcode |
| `exports.generate` · `exports.download` · `exports.delete` | generate-/download-/remove-booking-backup | locked |
| `activity.archive` | — | locked |
| `staff.create` · `staff.edit` · `staff.deactivate` · `staff.contracts` | add-new-staff · edit-staff · remove-staff · manage-contracts | locked |
| `staff.note_add` · `staff.note_edit` · `staff.note_remove` | add-/edit-/remove-staff-note | locked |
| `payroll.run` · `payroll.finalize` · `payroll.settle` · `payroll.void` · `payroll.revert` · `payroll.remove` · `payroll.edit_line` | run-/finalize-/settle-/void-/revert-payroll · remove-payroll-run · edit-payroll-item | locked |
| `payroll.schedules` · `payroll.shifts` · `payroll.statutory_pay` · `payroll.tax_settings` | manage-pay-schedules · manage-work-shifts · record-statutory-payment · edit-tax-info | locked |
| `timeoff.approve` | approve-time-off | locked |
| `settings.<section>` | edit-business-info (→ `settings.general`) | locked |
| `access.manage` | — | locked |

Each later module registers its keys in its first task. This list is the plan, and the registry in
code is the truth.

Profile fields (M16 enforces them), matching the legacy defaults: `username` restrict ·
`first_name`, `last_name` allow · `dob`, `address`, `marital_status`, `num_children` restrict ·
`phone`, `email` restrict · `emergency_*` restrict · `passcode`, `password` allow.

## Data

| Store | Content |
|---|---|
| table `access_roles` (**Pro**) | `id`, `slug`, `name`, `description`, `levels` (JSON key→level, may be partial), `profile_fields` (JSON), `is_template`, timestamps |
| option `rtbp_access_settings` (free: `defaults` open/locked per built-in role; **Pro** adds the passcode keys to its own option `rtbp_pro_access_settings`) | `defaults` (key→level), `profileFieldDefaults`, `passcodeValidity`, `passcodeScope`, `lockedMessage` {fr,en}, `fallbackEnabled`, `fallbackHash` (**sensitive**) |
| user meta | `rtbp_access_role` (role id), `rtbp_access_overrides` (key→level), `rtbp_profile_field_overrides`, `rtbp_pin_hash`, `rtbp_pin_failures`, `rtbp_pin_locked_until` |
| transient | `rtbp_pc_{user}_{scope}` → a hash of the unlock token, TTL = validity. Changing a PIN clears **all** of the user's grants |

WordPress side: role `rtbp_staff` with `rtbp_view_dashboard`. Staff users are created in M12.
Until then, any user with the capability can be assigned an access role here.

## API

| Method | Route | Access key | Purpose |
|---|---|---|---|
| GET | `access/me` | (authenticated) | The effective map for the current user, used by `useAccess` |
| POST | `access/unlock` | (authenticated, rate-limited) | `{ pin, key }` → `{ token, expires_at }` |
| GET/PUT | `access/defaults` | `access.manage` | The global default map and profile-field defaults |
| CRUD | `access/roles` | `access.manage` | Access roles |
| GET/PUT | `access/users/{id}` | `access.manage` | A user's role, overrides and effective map with sources |
| POST | `access/users/{id}/pin` | `access.manage` | Set or reset a user's PIN |
| POST | `access/reset-overrides` | `access.manage` (passcode) | Reset everyone's or one user's overrides |
| GET | `access/registry` | `access.manage` | Keys with labels and groups, for the matrix UI |

## Settings (added to M17)

Section `access`: `passcodeValidity` (5), `passcodeScope` (`global`), `lockedMessage` (text),
`fallbackEnabled` (true), fallback PIN (write-only field). The permission-map and roles screens
live under Settings → Permissions.

## Activity log actions

`permission.changed` (defaults, role, or user override, with before/after per key),
`permission.role_created|updated|deleted`, `permission.overrides_reset`, `security.denied`,
`security.passcode_ok`, `security.passcode_failed`, `security.pin_locked`, `security.pin_changed`.
Until M14 exists these fire as hooks (`rtbp_access_changed`, `rtbp_access_denied`,
`rtbp_passcode_failed`, `rtbp_page_viewed`), and M14 subscribes to them.

## Consumes / provides

- **Consumes:** M17 `SettingsSchema`, M00 composites.
- **Provides:** `Access\AccessRegistry::register()`, `Access\Access::level( $key, $user = null )`,
  `Access::can( $key )`, `AccessMiddleware`, the hooks above, `useAccess`, `PasscodeDialog`, and
  the `passcode_required` / `access_locked` error codes.

## Tasks

- [x] T1 [Free] `AccessRegistry` with the initial keys (defaults `open`/`locked` only), `Access::level()` resolver (built-in role map → `rtbp_access_level` filter → admin relax); resolution verified on the Local site — done 2026-09-29
- [x] T2 [Free] `AccessMiddleware` (per-method keys; `open`/`locked`; `passcode` delegated to the `rtbp_access_passcode_check` filter, and treated as `locked` when no handler answers), `access/me`, `useAccess`, route hiding (the `rtbp.api.error` hook already exists since M00 T9); migrate the Settings endpoints onto keys — done 2026-09-29
- [x] T3 [Free] Settings → Permissions: the open/locked matrix per built-in role (`rtbp_manager`, `rtbp_staff`); log via `rtbp_activity()` — done 2026-09-29
- [x] T4a [Pro] Passcode backend: `AdvancedAccessFeature` (`advanced_access`), PIN hashing, fallback PIN, `access/unlock` with brute-force lockout, grant tokens (global / per key), the `rtbp_access_passcode_check` handler, `access/users/{id}/pin`, and the `pro_access` settings section (validity, scope, locked message, fallback) — done 2026-09-29
- [x] T4b [Pro] Passcode UI: `PasscodeDialog` registered on `rtbp.api.error` (retry once with the token), the Settings → Access tab (write-only fallback PIN), and the locked message shown on `access_locked` — done 2026-09-29
- [x] T5a [Pro] Custom access roles backend: `access_roles` table, the six templates seeded, roles CRUD, per-user role + key-by-key overrides on `rtbp_access_level`, `access/users/{id}` with sources, `access/reset-overrides` (passcode) — done 2026-09-29
- [x] T5b [Pro] Roles and per-user screens (role, overrides, effective map with sources, set PIN, reset overrides); the matrix gains the third level through `rtbp.settings.sections` — done 2026-09-29
- [x] T6 [Pro] Profile-field control (defaults / role / override) storage + API, enforced by M16 — done 2026-09-29

## Acceptance

1. Set `bookings.cancel` to *Passcode* globally. A receptionist cancelling a booking gets the PIN
   dialog. After a correct PIN, the cancel goes through without a second click.
2. With scope `global` and validity 5 min, a second protected action within 5 min asks no PIN.
   With `per_key`, it asks again.
3. Call `POST bookings/{id}/cancel` with curl as a *Locked* user. The response is 403
   `access_locked` and the attempt is logged.
4. Enter a wrong PIN five times. The user is locked out for 15 min and the log shows it.
5. Assign *Night receptionist*, then override one key. The effective map shows each source
   (role / override / default).
6. An administrator on a *Locked* page gets a PIN prompt, not a block.
7. No API response contains a PIN or a PIN hash (grep the network tab).

## Legacy reference

`legacy-reference.md` → Module 13 (`RTA/dashboard/class-protections.php`).

## Migration notes

Legacy option and user meta `hbfwc_rta_protected_dashboard` (and `_extra`) map to the defaults and
overrides using the table above: `protected` → `passcode`, `closed` → `locked`.
`hbfwc_rta_profile_field_access` maps to the profile-field store. Plaintext legacy PINs
(`user_passcode`, `hbfwc_rta_passcode_fallback`) are **hashed on import**. Nothing is stored in
plaintext.

## Progress notes

- 2026-09-29 planning: T4 and T5 split into a/b (backend, UI). Free resolver order: per-role stored level → registry default; a user with several plugin roles gets the most permissive level; `rtbp_manager` defaults every key to `open` in code (it runs the hotel). `rtbp_activity()` is M14: until then calls are guarded by `function_exists()` and the `rtbp_access_*` hooks fire regardless.
- T1 (2026-09-29): `includes/Access/{AccessRegistry,Access}.php`, section `access` (`roleLevels`, open/locked per built-in role) in `CoreSettings`. The free registry holds only free features' keys (51 incl. one `settings.<section>` per section, add-on sections too); staff, payroll, `page.activity_log`, `activity.archive` and `page.my_payroll` are registered by Pro/Client via `rtbp_access_keys`. Plan "passcode" defaults are `open` in free (Pro raises them). Admin relax gives `passcode` only when `rtbp_access_passcode_check` has a handler, else `open`. Verified on food-menu.local with `wp eval-file`: 28 checks OK (registry defaults, sanitiser, stored/role-default/most-permissive, filter, admin relax with and without a handler, add-on keys, fail-closed); two full maps 0.6 ms.
- T2 (2026-09-29): the router stores the controller method as the `rtbp_action` request attribute; `AccessMiddleware` maps it to keys (string, list = all must pass, or a callable). Settings: `page.settings` to read, `settings.<section>` to write (`access.manage` for `access`), `rtbp_manage_settings` no longer gates the API or the wp-admin submenu (the cap still exists; retire it later). Routes carry `access`; a locked route shows "no access" and is hidden in the sidebar, tab bar, palette and wp-admin submenu. Verified: 30/30 REST checks via `rest_do_request` (locked, multi-section, reset-all, passcode with no/refusing/accepting handler, `Access::can()`, `access/me`, 401 logged-out, admin, nonce first); browser: Settings loads and saves as admin, a simulated locked map hides Settings/Rooms and shows the locked screen. The 360 px check could not run (window resize did not change the viewport); the screen reuses the NotFound `EmptyState` layout. Follow-up: Pro `Settings/FeatureSettings.php` doc comment still names `rtbp_manage_settings`.
- T3 (2026-09-29): Settings tab `access` ("Permissions", key `access.manage`); `GET access/registry` (groups, keys, built-in roles with code defaults); the tab stores only levels that differ from the fallback. `Access\AccessAudit` (booted in `init_classes`) diffs `roleLevels` on `rtbp_settings_updated` → `rtbp_access_changed( 'defaults', {before, after}, $user_id )` + guarded `rtbp_activity( 'permission.changed' )`, with `role:key` => level or `default`. Settings tabs now hide when their `accessKey` (default `settings.<key>`) is locked; the `/permissions` sidebar item redirects to the tab; route field renamed `access` → `accessKey` (matches the admin-screen skill). Verified: 19/19 `wp eval-file` checks (registry 403/200, groups, 51 keys, role defaults, labels, change event before/after/actor, no event on an unchanged save, reset, passcode dropped by the free sanitiser); browser: matrix renders, a click + Save stores `{"rtbp_staff":{"page.rates":"open"}}`, setting it back stores nothing; 360 px checked in a 356 px same-origin iframe (window resize does not change the viewport here): no horizontal scroll, rows stack label → one toggle per role. A user with `access.manage` but `page.settings` locked cannot load the tab (reading settings needs `page.settings`).
- T4a (2026-09-29, Pro): `Features\Access\AdvancedAccessFeature` + `Access\{AccessSettings,PasscodeService,PasscodeController}`. Deviations from **Data**: user meta is Pro-prefixed (`rtbp_pro_pin_hash`, `_failures`, `_locked_until`, `_generation`); the fallback hash is its own option `rtbp_pro_access_fallback_hash` (autoload off), not a settings key, so it never passes through the settings API; grants are transients `rtbp_pro_pc_{uid}_{generation}_{md5(scope)}` holding an HMAC of the token, and a PIN change bumps the generation. Client sends `X-RTBP-Passcode`. Endpoints: `POST access/unlock` (codes `pin_invalid` 403 + `attempts_left`, `pin_locked` 429 + `retry_after`, `pin_not_set` 409, `invalid_key` 422), `POST access/users/{id}/pin` and `POST access/fallback-pin` (`access.manage`; `pin: null` removes). Pro raises the plan's ten "passcode" defaults via `rtbp_access_keys`. **Decision:** an administrator with no PIN to enter (no own PIN, fallback off or unset) passes the passcode check, otherwise a fresh site could never set its first PIN; once a PIN exists admins are prompted (acceptance 6). Verified: 45/45 `wp eval-file` checks via `rest_do_request` (lockout at 5, 15 min, hooks, global vs per-key scope, other user's token, PIN change voids grants, fallback only without an own PIN, fallback off, no PIN/hash in any response, locked message, `Access::can()` via header); with the feature switched off: no handler, no section, `bookings.cancel` back to `open`. Follow-up (T5b): the free matrix shows no selection for a key whose default is `passcode` (only open/locked options).
- T4b (2026-09-29, Pro + small free seams): `src/admin/access/{passcode.js,PasscodeDialog.jsx,AccessSection.jsx}`, registered only while `advanced_access` is `active` (from `window.rtbpPro.features`). The interceptor retries once with a cached grant (global → one grant, per_key → per key; dropped on a `passcode_required` retry), else opens one shared prompt, and rejects with the original error on Cancel. New Pro `GET access/passcode` (flags only: has_pin, has_fallback, can_enter_pin, locked_until, scope, validity) lets the prompt say "no PIN yet" / "locked out" up front. Settings tab `pro_access` = "Passcodes"; the fallback PIN is write-only (`POST access/fallback-pin`). Free: `window.rtbp.ui` now publishes `NumberInput` and `ToggleRow`; `access/me` / localized `access` carries `lockedMessage` (the `rtbp_access_locked_message` filter), which the free "no access" screen shows. Verified in the browser as admin: set fallback PIN from the tab (toast), a protected `PUT settings/general` opened the prompt, a wrong PIN showed "Wrong PIN. 4 attempts left.", the right one completed the original request; a second protected save within 5 min asked nothing (acceptance 1–2); per_key: another key prompted again, Cancel rejected with `passcode_required`; restored scope and removed the fallback through the prompt. 360 px: tab has no horizontal scroll (356 px iframe). Test option deleted afterwards.
- T5a (2026-09-29, Pro): table `…radius_hotel_booking_pro_access_roles` (`Databases\AccessRolesTable`) with Pro's own gate `Databases\Installer` (`RADIUS_HOTEL_BOOKING_PRO_DB_VERSION`, option `rtbp_pro_db_version`): the free installer only re-runs on a **free** DB bump, so Pro re-runs its tables' idempotent `up()` itself and also lists them on `rtbp_migration_classes`. Templates (`RoleTemplates`) seeded on `rtbp_pro_installed` (deferred to `init`), insert-if-missing by slug. `levels` may use `*` (Housekeeping `*: locked`, Manager `*: open` + passcode on `access.manage`, `payments.void`). Meta: `rtbp_pro_access_role`, `rtbp_pro_access_overrides` (Pro prefix). `UserAccessService::filter_level` on `rtbp_access_level`: override → role key → role `*` → free level. Effective sources: `override`, `role`, `wp_role` (free matrix), `default`, `admin`, `no_dashboard`. New key `access.reset_overrides` (passcode) + `access.manage` guard `POST access/reset-overrides`. Endpoints: `access/roles` CRUD, `GET access/users`, `GET/PUT access/users/{id}` (overrides merge key by key; `null` removes). Shared `Access\GuardsRequests` trait (middleware + exception envelope) now used by all Pro access controllers. Verified: 42/42 `wp eval-file` checks (seed ×1 on re-run, `*` vs named key, 422 field errors, unique slug, template delete 409, delete unassigns, key-by-key merge, acceptance 5 sources, reset one/all keeps the role); T4a's 45 checks re-run after the refactor: 45/45.
- T5b (2026-09-29, Pro + free seams): Pro routes `/access/roles` (list; editor at `?role=<id|new>`: name, description, "Everything else" = `*`, grouped key matrix Default/Open/Passcode/Locked with search) and `/access/staff` (list with role/override/PIN badges and "Reset all overrides"; person at `?user=<id>`: access role Select, personal PIN, override matrix Inherit/… with "Now: <level> · <source>" per key, sticky Save/Discard, reset). **Deviation:** the third level reaches the free matrix through new seams, not a Pro settings tab: PHP `rtbp_access_role_levels` (sanitiser allow-list; Pro adds `passcode`, so a stored passcode without Pro is dropped on the next save and enforced as locked meanwhile) and JS `rtbp.access.levels` (matrix options). Free also publishes `window.rtbp.router` (Link, NavLink, Navigate, useLocation, useNavigate, useParams, useSearchParams) — a bundled router in an add-on cannot see the app's HashRouter; Pro maps `react-router-dom` to it. The free matrix now stacks until `xl` (two 232 px columns do not fit beside the Settings tab list at md). Pro chunks now carry a content hash (`chunkFilename: '[name].[contenthash:8].js'`): a rebuilt `530.js` stayed cached in the browser. **Follow-up (free):** the free build has the same un-hashed lazy chunks. Only classes the free CSS compiles may be used in Pro: every class in `src/admin/access/*` was checked against `build/*.css`. Verified in the browser as admin: roles list (6 templates), created "Test bar" (`*` locked, Dashboard open) through the editor; staff list; assigned Night receptionist, set an override on `bookings.cancel`, sources read "Locked · Access role" → "Open · Personal override" (acceptance 5), stored meta confirmed; free Permissions tab shows Open/Passcode/Locked and `bookings.cancel` for Staff shows Passcode; 768 px: matrix stacks, no overflow; 356 px iframe: staff page no page scroll, the 4-option control scrolls inside its row. Sanitiser: passcode kept with Pro on, dropped with `advanced_access` off. Test user and role deleted.
- T6 (2026-09-29, Pro + free seam): `Access\ProfileFields` — 12 fields with the legacy defaults (filter `rtbp_pro_profile_fields`), values `allow`/`restrict`; resolution per field: person override (`rtbp_pro_profile_field_overrides`) → access role (`pro_access_roles.profile_fields`) → `pro_access.profileFields` (partial, sanitised) → code default; administrators always `allow`. `ProfileFields::allowed( $user_id, $field )` is the check M16 calls. API: `GET access/profile-fields` (fields + defaults, `access.manage`), `GET access/me/profile-fields` (any dashboard user), `profile_fields` on role and user saves (merge, `null` removes; events carry `profile.<field>` keys). UI: a "Profile fields" card in Settings → Passcodes (stores only what differs from the code default), in the role editor (Default/Allowed/Restricted) and on the staff page (Inherit/…, saved on change, "Now: … · source"). The task said storage + API; the three editors were added so 13.11/17.6 are usable in M13. **Free seam:** `tailwind.config.js` safelists `md:flex-row`, `md:items-center`, `md:gap-4` — T5b moved the free matrix to `xl:` and that silently removed classes Pro's rows use. Verified: 20/20 `wp eval-file` checks (defaults, settings → role → override order, 422 on bad values, events, `allowed()`, admin, endpoint access); browser: defaults saved as `{"username":"allow"}`, staff page phone "Restricted · Default" → "Allowed · Personal override", role editor section renders. Test user and option removed.
- Module DoD (2026-09-29): free smoke with Pro **deactivated** — T1 28/28, T2 30/30, T3 18/19 (the one miss is the expected 50 vs 51 keys: `settings.pro_features` only exists with Pro), `passcode_supported` false, `bookings.cancel` back to `open`; Pro reactivated. `wp plugin check radius-hotel-booking`: no issue in any M13 file; remaining findings predate M13 (WP-CLI make:* commands' `mkdir`/`unlink`, `bin/*.sh`, framework `error_log`, dev dotfiles, `Tested up to`) — release follow-up (M18).
- Acceptance run (2026-09-29, food-menu.local, real HTTP with a logged-in staff cookie + nonce): **3** `PUT settings/general` as a Locked staff user → 403 `access_locked` `{key: settings.general}`, value unchanged, `rtbp_access_denied` recorded (temporary mu-plugin logger; the stored log arrives with M14); without Referer → 403. `bookings/{id}/cancel` does not exist until M03, so a live locked endpoint stood in. **4** five wrong PINs via `POST access/unlock` → attempts 4…1, the fifth 429 `pin_locked`, the correct PIN then still 429 (899 s left); five `rtbp_passcode_failed` events, the last `locked=yes`. **1, 2** browser (T4b), **5** browser + REST (T5a/T5b), **6** REST (T4a: admin with a fallback PIN gets `passcode_required`, not a block), **7** no PIN/hash in any response (T4a/T5a checks, `access/me` over curl). Temporary user, mu-plugin and session removed.
- Critical review (2026-09-29, `rtbp-critical-reviewer` on the uncommitted working tree of both repos): 6 confirmed + 4 plausible. Fixed:
  1. **Pro security sections were guarded by `settings.<section>`** (a Manager could switch off `advanced_access` or the fallback rules without a PIN). Free: `AccessRegistry::settingsKey()` + filter `rtbp_settings_access_key` (SettingsController uses it; sections mapped elsewhere get no `settings.*` key). Pro maps `pro_access` and `pro_features` to `access.manage`, audits `pro_access` changes (`rtbp_access_changed` scope `passcode`), and the built-in `rtbp_manager` gets `passcode` on `access.manage`, `access.reset_overrides`, `payments.void` (like the Manager template, which also gained `access.reset_overrides`; the seeded row on existing sites keeps its `*: open` for that key).
  2. **Brute force raced by parallel requests.** Per-user PIN-check lock (atomic `INSERT IGNORE` of `rtbp_pro_pin_lock_{uid}`, stale > 30 s cleared only if unchanged; busy → 429 `pin_busy`), and the user's meta cache is dropped after locking (the stale per-request cache was the second cause). Verified over real HTTP: 20 parallel wrong PINs → exactly 5 evaluated (1…5, locked), correct PIN then 429, no lock rows left.
  3. **per_key could not pass two passcode keys** (admin reset-overrides). Free `AccessMiddleware` now reports every pending passcode key (`data.keys`); Pro `unlock` takes `keys` and stores the grant under each; the dialog/cache send and reuse them.
  4. `UserAccessService::update` validates role, overrides and profile fields before writing any (`ProfileFields::merge`).
  5. A missing `pin` is 422 `pin_format`; removal needs an explicit `"pin": null`.
  6. Only an administrator changes an administrator's PIN or permissions (`admin_target`); an administrator resets their **own** PIN without a passcode (never locked out).
  7. (plausible) Grants made with the fallback PIN carry its generation (`rtbp_pro_access_fallback_generation`) and die when the fallback changes or is switched off.
  8. (plausible) A role id that cannot be loaded resolves to `locked`, not to the open defaults.
  Verified: review checks 32/32; T4a 45/45, T5a 42/42, T6 20/20 with Pro; T1 28/28, T2 30/30 with Pro off (T3's registry count is 50 free-only).
  **Follow-ups:** (9, low) the shared fallback PIN gets 5 guesses per account — a global fallback failure counter, before M18; (10) M16 must ask for the current PIN before a staff member changes their own (recorded in the M16 doc); free lazy chunks lack a content hash (Pro fixed).
