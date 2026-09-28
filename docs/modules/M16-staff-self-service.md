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

- [ ] T1 [Pro] `me/profile` with server-side field enforcement + the My profile screen (restricted fields read-only with a lock icon)
- [ ] T2 [Pro] Password and PIN change flows (session invalidation, grant clearing)
- [ ] T3 [Client] My payslips + My time off, registered by the client add-on after M15; skip and defer if D1 = out of scope

## Acceptance

1. With `phone` restricted, the field is read-only, and `PUT me/profile {phone}` returns a field
   error that is logged as denied.
2. Change the password. A second browser session is signed out.
3. A staff member can download only their own payslip. Changing the ID in the URL returns 403.

## Legacy reference

`legacy-reference.md` → Module 16.

## Progress notes
