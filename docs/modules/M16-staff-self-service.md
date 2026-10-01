# M16 — Staff self-service

| | |
|---|---|
| **Client module** | 16 — Staff self-service |
| **Features** | 16.1–16.9 |
| **Depends on** | M12 (employee file), M13 (profile-field control), M15 for 16.7–16.9 (D1) |
| **Tier** | **Pro** (T3 payslips: **Client**, it depends on M15) |
| **Engine** | no |
| **Critical review** | no |

## Goal

Each member of staff manages their own profile, password and PIN, limited to exactly the fields
the hotel allows. They also see their own payslips and time off.

## Scope

| Feature | Summary | Task |
|---|---|---|
| 16.1 | *My profile* screen (under the avatar menu) | T1 |
| 16.2 | Editable fields = the effective profile-field map (M13). The server **rejects** a change to a restricted field with a field error; it does not silently keep the old value as the legacy system did | T1 |
| 16.3 | Change password (current password required); all other sessions are signed out | T2 |
| 16.4 | Set own PIN (current password or current PIN required); digits only, 4–8 long; all passcode grants are cleared | T2 |
| 16.5, 16.6 | Own personal, contact and emergency details, where permitted | T1 |
| 16.7, 16.8 | *My payslips*: the list of settled runs (pay date, period, hours, gross, net) and a PDF download of their own payslip only | T3 (needs M15) |
| 16.9 | *My time off*: a calendar of their requests and remaining balance; request time off; cancel a pending request | T3 (needs M15) |

## API

| Method | Route | Access key |
|---|---|---|
| GET/PUT | `me/profile` | (authenticated staff) + field map |
| POST | `me/password` | (authenticated) |
| POST | `me/pin` | (authenticated) |
| GET | `me/payslips` · `me/payslips/{id}/pdf` | `page.my_payroll` |
| GET/POST/DELETE | `me/time-off` | `page.my_payroll` |

## Activity log actions

`self.profile_update` (before/after, masked), `self.password_change`, `self.pin_change`,
`self.timeoff_request`, `self.timeoff_cancel`, `self.payslip_download`.

## Tasks

- [x] T1a [Free] Avatar menu seam: the sidebar's user block (and the mobile *More* sheet) opens a menu of `rtbp.user.menu` items, plus *Sign out* — done 2026-10-01
- [x] T1b [Pro] `me/profile` GET/PUT: the employee file (or the WordPress user when there is none), field map enforced on the server (422 field error + `security.denied` for a restricted field), `self.profile_update` logged masked (16.2, 16.5, 16.6) — done 2026-10-01
- [x] T1c [Pro] *My profile* screen on the avatar menu: restricted fields read-only with a lock icon (16.1, 16.2) — done 2026-10-01
- [ ] T2 [Pro] Password and PIN change flows (session invalidation, grant clearing) (16.3, 16.4)
- [ ] T3 [Client] My payslips + My time off, registered by the client add-on after M15 — **deferred** (D1 open, M15 not built)

## Acceptance

1. With `phone` restricted, the field is read-only, and `PUT me/profile {phone}` returns a field
   error that is logged as denied.
2. Change the password. A second browser session is signed out.
3. A staff member can download only their own payslip. Changing the ID in the URL returns 403.

## Legacy reference

`legacy-reference.md` → Module 16.

## Progress notes

- From the M13 critical review (2026-09-29): changing your **own** PIN must ask for the current PIN first. `PasscodeService::set_pin()` clears the lockout and failure count, so without it anyone at an unattended session could reset the brute-force limit and pick a new PIN. The `passcode` profile field (`ProfileFields::allowed()`) only says whether the person may change it at all.
- 2026-10-01 planning: T1 split into T1a (free seam — no avatar menu exists yet; the sidebar shows the user's initials, name and e-mail only), T1b (backend) and T1c (screen). **T3 deferred**: it needs M15 (payroll), which waits on **D1** (payroll in scope?) — nothing of 16.7–16.9 is built here. Pro feature **`staff_self_service`** (already in `FeatureRegistry`, requires `staff_records`, class `Features\SelfService\SelfServiceFeature`, to be created). **Consumed and present:** `Access\ProfileFields` (`fields()`, `resolve()`, `allowed()` — admins resolve to allow), `PasscodeService::set_pin()` (voids grants and lockout, logs `security.pin_changed`) / `is_valid_pin()`, `Staff\Employee` (`user_id`, names, dob, address, marital status, children, phone, e-mail, emergency), `EmployeeService::update()` (validation, e-mail uniqueness, WP user sync, `staff.edit` log). **Decisions (non-blocking defaults):** (1) the **username is never changed** from self-service (shown read-only): WordPress cannot rename a login safely and the legacy raw-SQL rewrite is on the don't-copy list; (2) a dashboard user **without an employee file** (an administrator, say) gets the WordPress fields only — first and last name, e-mail — under the same field map; (3) the field map applies whether or not *Advanced staff access* is on (the settings default otherwise); setting one's own PIN needs that feature, like M12's `account()`; (4) a restricted field sent **unchanged** is accepted (the screen posts the whole form), only a **changed** value is refused — 422 with the field's error, nothing saved, logged `security.denied`.
- T1a (2026-10-01, Free): the sidebar's user block (initials, name, e-mail — the same component renders in the mobile drawer behind *More*) is now the trigger of an **account menu** (`UserMenu` in `Sidebar.jsx`, Radix dropdown, `rtbp-root` content, opens upwards — to the right when the sidebar is collapsed): the e-mail as a label, then the items of the new JS filter **`rtbp.user.menu`** (`{ key, label, icon, to }`, `to` = a dashboard route, the drawer closes on select; receives `{ user }`), then **Sign out**. The menu is controlled so the items are read again on every open (an item registered after the first render still appears). `current_user.logout_url` added to the admin params — `wp_logout_url()` passed through `wp_specialchars_decode()` (it escapes its `&` for HTML, which would drop the nonce and land on WordPress's "are you sure" page); the app sets `redirect_to` to the page it runs on, so the front-end Hotel Dashboard page comes back to its sign-in screen. ADR-015 row `rtbp.user.menu`. Verified: phpcs clean, build ok (the usual size warnings); the decoded URL carries a nonce that `wp_verify_nonce( …, 'log-out' )` accepts; browser: the menu opens with the e-mail and *Sign out* (not clicked — the session is shared), a test item registered from the console after load appears and navigates to `#/exports`, the same menu at 356 px inside the mobile drawer, no console errors. Changelog line added.
- T1b (2026-10-01, Pro): feature class **`Features\SelfService\SelfServiceFeature`** (`staff_self_service`, requires `staff_records`): routes and the `self.profile_update` action. **`SelfService\ProfileService`** — `get( user )`: username (read-only), `has_file`, the file's employee number / department / job title (read-only header), `values` keyed by **profile field** (`first_name`, `last_name`, `dob`, `address`, `marital_status`, `num_children`, `phone`, `email`, `emergency_contact` as `{ full_name, address, phone, relationship }`), `fields` `[ { key, label, editable } ]` from `ProfileFields::resolve()`, the marital and relationship lists (`EmployeeService::options()`); without an employee file only first / last name and e-mail of the WordPress user. `update( user, input )`: unknown keys dropped (username, CNPS / tax numbers, job title, PIN and password never change here); a **restricted** field sent with a **changed** value (compared trimmed, the emergency contact part by part, numbers as numbers) → **422 `field_restricted`** with that field's error, **nothing saved — not even the allowed fields of the same request**, and **`security.denied`** logged (`key` `self.profile_update`, the field keys); sent unchanged it is dropped. With a file the change goes through **`EmployeeService::update( id, columns, 'self.profile_update' )`** — the method gained an `$action` argument (default `staff.edit`) so validation, the e-mail check, the WordPress user sync (names, e-mail, display name), the transaction and the masking of personal fields (`MASKED`) stay in one place; logged *X updated their own profile*. Without a file: names required, e-mail valid and unique, `wp_update_user()` with the display name, logged against the user. **`SelfService\ProfileController`**: `GET` / `PUT me/profile` behind `GuardsRequests` with **no access key** (any dashboard user — the user is always the signed-in one, never an id from the request). Verified: phpcs clean (Pro); **m16t1b 19/19** — a test employee with phone restricted and address / emergency allowed by override: GET shows the file and the right editable flags, no username / PIN / password fields; the whole form back with an address and emergency phone change → saved, one `self.profile_update` entry with `address` and `emergency` logged as *changed*; a changed phone → 422 `field_restricted` (phone), the name change in the same request not saved, `security.denied` logged; the phone sent back with spaces → 200; an empty first name → 422; username / CNPS / job title ignored; the display name follows; a manager without a file sees name and e-mail only, saves the name, a new e-mail (restricted by default) refused; signed out 401, a subscriber 403, the admin may edit every field; test users and file removed. Changelog line added (Pro).
- T1c (2026-10-01, Pro): **`src/admin/self/ProfileScreen.jsx`** at `#/me/profile` (hidden route, `rtbp_view_dashboard`, no access key) and **My profile** on the account menu (`rtbp.user.menu`), both registered while `staff_self_service` is active. A header (initials, name, username · job title · department · employee number), then *About you* (names, date of birth, marital status, children, address), *Contact* (phone, e-mail — "also the e-mail of your account") and *Emergency contact* (`SettingsSection` cards); a person without a file sees names and e-mail only. A **restricted** field is disabled with a **lock** by its label and *Only a manager can change this.*; a restricted emergency contact puts the lock and that line on the section. Only the **changed, editable** fields are sent (`useSaveProfile`, which sets the cache and invalidates `employees`); field errors land on their field (the file's `emergency.*` keys mapped to `emergency_contact.*`), others go to a toast; a sticky *Undo changes* / *Save* bar appears while something changed — offset `bottom-[4.5rem] md:bottom-4` like free's forms, so it clears the mobile tab bar (found in the 356 px check: at `bottom-0` it sat under the bar). M12's `ChoiceSelect` (`staff/FileTabs.jsx`) exported and reused. Verified: Pro build ok; browser as the admin — *My profile* on the account menu opens the screen (an admin has no file: names and e-mail, all editable); with the `me/profile` responses **mocked in the page** for a staff member (no other user can be signed in here; nothing was written): date of birth, marital status, children, phone, e-mail and the emergency contact locked with their notes, names and address editable; changing the address sends only `{ address }`, a mocked 422 shows the message under *Address*, a good save → *Your profile is saved.* and the bar closes; 356 px (iframe): no horizontal overflow, the save bar above the tab bar; no console errors; the admin's real name untouched. Changelog line added (Pro).
