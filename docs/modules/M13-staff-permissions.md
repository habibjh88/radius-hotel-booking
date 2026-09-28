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
| 13.6 | Personal PIN (4–8 digits), stored as `wp_hash_password`, never returned by any API | T3 |
| 13.7 | Fallback PIN for users without their own (hashed; can be disabled) | T3 |
| 13.8 | Passcode validity in minutes (default 5) | T3 |
| 13.9 | Passcode scope: `global` (one unlock covers everything until it expires) or `per_key` | T3 |
| — | Brute-force limit: 5 wrong PINs → locked out for 15 min, logged (`security.passcode_failed`) | T3 |
| 13.10 | Custom locked message | T4 |
| 13.4, 17.5 | Global default permission map: a matrix screen (groups × keys × three-way toggle) | T4 |
| 13.13, 13.14 | Named access roles with editable templates: *Receptionist*, *Night receptionist*, *Supervisor*, *Housekeeping*, *Accountant*, *Manager* | T5 |
| 13.5, 13.15 | Per-person: assign a role, override single keys, and see the effective map with the source of each value | T5 |
| 13.12 | Reset all overrides (everyone, or one person), behind a passcode | T5 |
| 13.11, 17.6 | Profile-field control (allow/restrict per field): global default, role, per person. Enforced by M16 | T6 |
| — | React: `useAccess( key )`, the `PasscodeDialog` interceptor in `src/api/client.js`, and routes hidden when locked | T2, T3 |

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

- [ ] T1 [Free] `AccessRegistry` with the initial keys (defaults `open`/`locked` only), `Access::level()` resolver (built-in role map → `rtbp_access_level` filter → admin relax); resolution verified on the Local site
- [ ] T2 [Free] `AccessMiddleware` (per-method keys; `open`/`locked`; `passcode` delegated to the `rtbp_access_passcode_check` filter, and treated as `locked` when no handler answers), `access/me`, `useAccess`, route hiding (the `rtbp.api.error` hook already exists since M00 T9); migrate the Settings endpoints onto keys
- [ ] T3 [Free] Settings → Permissions: the open/locked matrix per built-in role (`rtbp_manager`, `rtbp_staff`); log via `rtbp_activity()`
- [ ] T4 [Pro] Passcode level: PIN hashing, fallback PIN, `access/unlock` with brute-force lockout, grant tokens (global / per key), `PasscodeDialog` registered on `rtbp.api.error`, and the Settings → Access section (validity, scope, locked message)
- [ ] T5 [Pro] Custom access roles (`access_roles` table in Pro, the six templates seeded) + per-user screen (role, overrides, effective map with sources, set PIN, reset overrides); the matrix gains the third level through `rtbp.settings.sections`
- [ ] T6 [Pro] Profile-field control (defaults / role / override) storage + API, enforced by M16

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
