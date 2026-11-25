# M19 — Attendance

| | |
|---|---|
| **Client module** | — (new scope, from review R4, 2026-11-25; not one of the 18 signed modules) |
| **Features** | 19.1 – 19.9 |
| **Depends on** | M12 (employees), M15 (work shifts, time off, pay runs), M13, M14, M16 (self check-in) |
| **Tier** | **Pro**, switchable feature `attendance` (requires `payroll`). Nothing country-specific |
| **Engine** | no |
| **Critical review** | yes (it changes pay: hours, premiums, overtime, absences) |
| **Status** | see `docs/project/roadmap.md` |

## Goal

The hotel records what each employee **actually** worked, day by day, and payroll pays from
it: night, Sunday and holiday hours, overtime and absences come from the record instead of
being assumed from the planned shift or typed as a monthly total. Many staff cannot log in, so
the **manager's daily sheet is the main way to record attendance**; self check-in is optional.
A day with no record falls back to the planned shift, so a missed entry never breaks a month.

## Principles

1. **The manager is in control.** Anything a manager enters counts as confirmed. Self check-ins
   count too, but a manager can change any entry. Every write is logged with before and after.
2. **Nothing cuts pay silently.** A missed check-out, a missing day or pending overtime is
   flagged, never guessed. Overtime counts only once approved.
3. **Fallback, per day.** For each day of a pay period: attendance record → approved time off →
   planned shift. A hotel that switches the feature off gets today's behaviour exactly.
4. **Locked with the pay.** Days inside a finalised or settled run of the employee are read-only
   (void the run to change them), like the rest of payroll.
5. **Global.** No rates, labels or rules for one country; tolerances and rounding are settings.

## Scope

| Feature | Summary | Task |
|---|---|---|
| 19.1 | Table `pro_attendance`, model, repository, pure `AttendanceDay` (planned vs recorded, late, early, overtime, missing check-out) | T1 |
| 19.2 | Settings → Payroll → Attendance (tolerance, rounding, overtime threshold, self check-in, allowed networks) | T1 |
| 19.3 | Daily sheet (manager): every employee scheduled that day with the plan pre-filled; *Mark all as planned*; edit a row; absent with reason; extra shift for someone not scheduled | T2 |
| 19.4 | Employee month view (manager): calendar of plan vs record, totals (worked, night, Sunday, holiday, overtime, absences), add or correct any open day | T3 |
| 19.5 | Overtime approvals: list of recorded overtime, approve (full or part) or reject | T4 |
| 19.6 | Payroll integration: a line's hours, premiums, overtime and absences from the record, per-day fallback, *Attendance* card on the line, change warning (like time off) | T5 |
| 19.7 | Self check-in / check-out on the staff dashboard (users linked to an employee only), optional network restriction | T6 |
| 19.8 | Run screen: open items before finalising (missing check-outs, pending overtime, days not recorded) | T5 |
| 19.9 | Access keys, activity log, Pro feature switch, TATA add-on default on | T1, T7 |

**Deferred:** a shared front-desk check-in screen with an employee PIN (PINs today are WordPress
user meta, `rtbp_pro_pin_hash`, so staff without a login have none: it needs a PIN on the
employee file first); time-clock device import (CSV); geolocation; breaks as separate punches
(one *break minutes* figure per entry instead).

## Data

`{prefix}pro_attendance` — one row per worked stretch (a split shift is two rows) or per absence.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `employee_id` | bigint, index | Pro employee |
| `work_date` | date | the day the shift **starts** (a 20:00→08:00 shift belongs to its first day), as `ShiftHours` counts it |
| `kind` | varchar(20) | `worked` · `absent` |
| `start_at` | datetime null | site time; null for `absent` |
| `end_at` | datetime null | null = still checked in / missed check-out |
| `break_minutes` | smallint, default 0 | unpaid break inside the stretch |
| `absence` | varchar(20) null | `kind = absent`: `no_show` · `sick` · `unpaid` · `other` |
| `overtime_minutes` | int, default 0 | worked beyond the plan after the threshold (computed on save) |
| `overtime_status` | varchar(20) | `none` · `pending` · `approved` · `rejected` |
| `overtime_approved_minutes` | int, default 0 | may be less than recorded |
| `source` | varchar(20) | `manager` · `bulk` (Mark all as planned) · `self` |
| `note` | varchar(255) null | |
| `created_by`, `updated_by` | bigint null | WordPress users |
| `created_at`, `updated_at` | datetime | |

Indexes: `( employee_id, work_date )`, `( work_date )`, `( overtime_status, work_date )`.
Rules: `end_at > start_at`, a stretch at most 24 h, stretches of one employee never overlap
(checked in the service under a row lock on the employee), `start_at` within `work_date` ± 1 day.
Bump Pro's DB version.

`{prefix}pro_attendance_requests` (T9, Pro DB 1.9.0) — a staff member's correction request.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `employee_id`, `work_date` | bigint, date | own file only |
| `attendance_id` | bigint null | the entry to change; null = a stretch not recorded at all (set to the new entry on approval) |
| `start_at`, `end_at` | datetime | the times asked for (missing ones filled from the entry) |
| `reason` | varchar(500) | required |
| `status` | varchar(20) | `pending` · `approved` · `declined` · `cancelled` (same words as time-off requests) |
| `applied_start_at`, `applied_end_at` | datetime null | what the manager recorded (may differ) |
| `decision_note` | varchar(255) null | shown to the employee |
| `decided_by`, `decided_at`, `created_by`, timestamps | | |

Indexes `( employee_id, work_date )`, `( status, work_date )`. One pending request per employee
and day, at most 10 pending per employee; nothing on a day inside a finalised pay run.

## API

All under `/wp-json/radius-hotel-booking/v1/`, through `GuardsRequests::guard()`.

| Method | Route | Access key | Purpose |
|---|---|---|---|
| GET | `payroll/attendance/day?date=` | `attendance.view` | daily sheet: scheduled + recorded + time off for that date |
| POST | `payroll/attendance/day/planned` | `attendance.record` | *Mark all as planned* for `{ date, employee_ids? }`; skips anyone with a record or approved time off |
| GET | `payroll/attendance?employee_id=&from=&to=` | `attendance.view` | an employee's month: days, totals |
| POST | `payroll/attendance` | `attendance.record` | add an entry |
| PUT | `payroll/attendance/{id}` | `attendance.record` | change an entry |
| DELETE | `payroll/attendance/{id}` | `attendance.record` | remove an entry (the day falls back to the plan) |
| GET | `payroll/attendance/overtime?status=pending` | `attendance.overtime` | overtime to decide |
| PUT | `payroll/attendance/{id}/overtime` | `attendance.overtime` | `{ status: approved|rejected, minutes? }` |
| GET | `my/attendance` | self-service | own status today, every day of `?month=YYYY-MM` (default this month, at most 24 months back) and `period { month, prev, next }` |
| POST | `my/attendance/check-in` · `check-out` | self-service + setting on | the server takes the time; the client never sends one |
| GET · POST | `me/attendance/requests` | `self.attendance` | own correction requests; send one `{ work_date, attendance_id?, start_at?, end_at?, reason }` (T9) |
| POST | `me/attendance/requests/{id}/cancel` | `self.attendance` | withdraw an own pending request |
| GET | `attendance/requests?status=` | `page.attendance` | requests to decide (pending oldest first) |
| POST | `attendance/requests/{id}/decide` | `attendance.record` | `{ status: approved|declined, start_at?, end_at?, note? }`; approving writes the entry through `AttendanceService` (overlap, closed run, overtime), never on one's own file (admins exempt) |

Every write: transaction, employee row lock, period-lock check, `rtbp_activity`.
Rate-limit self check-in (one punch a minute per user).

## Screens

**Payroll → Attendance** (new tab, `attendance.view`):

- **Day** (default): date picker (today), filter by work shift. One row per employee scheduled
  that day, plus anyone with a record: name · plan (e.g. 20:00–08:00) · recorded (or *Not
  recorded*, *On leave: Sick*) · status chip (on time, late 25 min, left early, overtime 2 h
  pending, missing check-out, absent). Header button **Mark all as planned** (confirm: "Records
  the planned shift for 28 employees not yet recorded"). Row click → entry dialog: worked / absent,
  start and end time (end may be the next day), break minutes, reason, note. **Add extra shift**
  for someone not scheduled. Phone: rows as cards.
- **Employee**: employee picker + month; a calendar where each day shows plan and record;
  totals for the month or the pay period; click a day to add or correct.
- **Overtime**: pending first; approve all, approve part, reject; who decided and when.
- **Corrections** (T9, `?tab=requests&status=`): Waiting (count) · Approved · Declined · All;
  employee, day, asked for, decision (with "Recorded as …" when other times were approved, and
  the note), what happened. Approve opens the times (editable) and a note; Decline a note.

**Pay run line**: an *Attendance* card above *This pay period*: days recorded / days from the
plan, totals that went into the line, open items. Entries typed on the line still win.

**Run screen**: a banner when lines have open items ("2 missing check-outs, 3 h overtime
pending"); finalising is allowed but asks for confirmation listing them.

**Staff dashboard (self)**: a *Check in* / *Check out* card with today's plan and the time
checked in; *My attendance*: every day of a month (days off and upcoming days included), as
a list or a month calendar (`?view=calendar&month=YYYY-MM`, this month or up to 24 months
back), read-only. Each day up to today (not in a finalised
run) has **Ask for a correction** (T9): pick the entry or "Time not recorded at all", the
times, what happened; *My correction requests* below lists them with the decision, and a
pending one can be withdrawn.

## Settings (added to M17, Pro's payroll section)

| Section.key | Default | Meaning |
|---|---|---|
| `payroll.attendance.grace` | 10 | minutes late or early that still count as on time |
| `payroll.attendance.rounding` | 0 | round recorded times to 0 / 5 / 15 minutes (to the employee's favour: start down, end up — decide in T1) |
| `payroll.attendance.overtimeAfter` | 15 | minutes beyond the planned shift before overtime is recorded |
| `payroll.attendance.selfCheckIn` | true (was false until 2026-11-25) | staff with a login may check in themselves (top bar, account menu, My attendance) |
| `payroll.attendance.networks` | [] | optional IPs / CIDR ranges self check-in is allowed from |
| `payroll.attendance.absentWithoutRecord` | `plan` | a scheduled day with no record: `plan` (paid as planned) or `flag` (shown as *not recorded* on the line, still paid as planned until a manager decides) |

## Access keys (added to M13, via `rtbp_access_keys`)

| Key | Kind | Default |
|---|---|---|
| `attendance.view` | page | locked (Manager template: open through `*`) |
| `attendance.record` | action | locked (Manager: open) |
| `attendance.overtime` | action | locked (Manager: open) |
| `self.attendance` | self | open for users linked to an employee, only when `selfCheckIn` is on |

Administrators hold every key implicitly.

## Activity log actions (M14)

`attendance.record`, `attendance.update`, `attendance.delete`, `attendance.planned` (bulk, one
row with the count and date), `attendance.overtime` (decision, before/after minutes),
`attendance.check_in`, `attendance.check_out` (self, with IP), `attendance.request`,
`attendance.request_cancel`, `attendance.request_decide` (T9).

## Payroll rules (T5)

For each day `d` of a line's period, for the employee:

1. **Records exist for `d`** → worked stretches are what was worked:
   - premiums: night, Sunday and holiday minutes counted on the recorded stretches (refactor
     `WorkPremiums::count()` to take a list of `[start, end]` intervals; the shift becomes one
     interval per day, so both paths share one counter);
   - hourly pay: base hours = recorded hours minus breaks;
   - overtime: `overtime_approved_minutes` → the line's *Overtime pay* hours (pending or rejected
     add nothing);
   - `absent`: `sick` → sick pay, `unpaid` / `no_show` → unpaid time off, `other` → shown only;
     hours = the planned shift of that day;
   - a stretch without `end_at` counts as the planned end and is listed as an open item.
2. **No record, approved time off** → as R4-T4 (`TimeOffOnLine`).
3. **Neither** → the planned shift (today's behaviour).

A record and approved time off on the same day: the record wins, and the line shows a conflict.
A salaried employee's base pay is unchanged; only premiums, overtime and absences move.
The line keeps an attendance fingerprint like `time_off.fingerprint`, so the run warns when the
record changed since calculating. Everything stays overridable on the line (R4-T1).

## Consumes / provides

- **Consumes:** `WorkShiftService::days()`, `ShiftHours`, `WorkPremiums`, `TimeOffOnLine`,
  `TimeOffRepository::approvedInPeriod()`, `PayrollRunService::calculateLine()`,
  `GuardsRequests::guard()`, `rtbp_access_keys`, `rtbp_activity`, `FeatureRegistry`,
  free's `Transaction`, `Dates`, `ApiResponse`.
- **Provides:** `AttendanceRepository::forPeriod( employee_ids, from, to )` (one query per run),
  the pure `AttendanceDay`, and the interval-based `WorkPremiums` counter.

## Tasks

- [x] T1 [Pro] Table, model, repository, `AttendanceDay` (pure), settings, access keys, feature `attendance` (default off), harness
- [x] T2 [Pro] Daily sheet: API + screen, *Mark all as planned*, entry dialog, extra shift, logging
- [x] T3 [Pro] Employee month view
- [x] T4 [Pro] Overtime approvals
- [x] T5 [Pro] Payroll: interval-based premium counter, per-day fallback, line card, run banner, fingerprint; critical review
- [x] T6 [Pro] Self check-in / check-out (setting, networks, rate limit) and *My attendance*
- [x] T7 [Client] TATA add-on: feature on by default; changelog lines in every plugin touched
- [x] T8 Browser acceptance on the test site; R4 doc and client reply updated
- [x] T9 [Pro] Correction requests: staff ask, managers approve or decline; run notice (owner's request)

## Acceptance

On live-residencetata-test.local, desktop and 375 px, with marked test data removed after:

1. Day sheet for a date: scheduled staff listed with their plan; *Mark all as planned* records
   them; a second click records nobody twice.
2. Edit one: arrived 20:25 (grace 10) → *late 25 min*; left 10:00 instead of 08:00 → overtime
   1 h 35 pending (worked 13 h 35 − planned 12 h; threshold 15).
3. Mark one absent (no show) → their line gets unpaid time off for that day's shift hours.
4. Approve the overtime → the line's overtime hours rise; reject → nothing paid.
5. A night shift recorded 22:00–06:00 instead of 20:00–08:00 → night hours follow the record.
6. A day with no record → paid as planned; with approved leave → as R4-T4.
7. Finalise the run → those days can no longer be changed; void → editable again.
8. Self check-in on, a user linked to an employee checks in and out from the dashboard; the
   manager sees and edits it; a second punch within a minute is refused.
9. Feature off → no Attendance tab, pay lines exactly as before.
10. Activity log shows every write with before and after.

## Open questions for the client (send with the R4 reply)

1. Do staff mostly follow their planned shifts, or do their hours change often?
2. Who records attendance each day: one manager, each department's supervisor, or staff
   themselves (those who can log in)?
3. A scheduled day with nothing recorded: pay it as planned, or hold it until a manager decides?
4. Overtime: from how many minutes beyond the shift, and who approves it?

## Legacy reference

None: the legacy system has no attendance (checked 2026-11-25; its payroll only uses
`is_overnight` to count shift hours). Work shifts came from it (M15 T3b).

## Progress notes

- 2026-11-25: spec written after R4-T4, at the owner's request ("a proper automatic solution";
  staff without a login → the manager must be able to manage everything). Awaiting review.
- 2026-11-25: T1–T4 and T6 built in Pro and checked in Chrome on the test site (day sheet,
  employee month, overtime approval incl. partial, My attendance, Settings → Payroll →
  Attendance). Harness `m19-day.php` 21/21.
- 2026-11-25: T5 built. Attendance reaches payroll only through the filters
  `rtbp_pro_payroll_attendance` (AttendanceOnLine summary per line) and
  `rtbp_pro_payroll_attendance_fingerprints` (run "changed" check), which AttendanceFeature
  hooks in `boot()`: switched off, pay runs behave exactly as before. `WorkPremiums::count()`
  takes recorded days (`date => { intervals, factor }`); the factor removes unpaid breaks and
  unapproved overtime from the premium minutes, since where a break fell is not recorded.
  Hourly base = shift hours + Σ (worked − overtime − planned) on recorded days; absences keep
  the planned hours and fill sick pay / unpaid time off. Harness `m19-t5.php` 21/21; run 6
  tested end to end on the site and restored (nets 587244 / 104939 / 106329).
- 2026-11-25: T5 critical review (rtbp-critical-reviewer) fixed: unapproved overtime is now
  cut off the stretches where it lies (after the planned end, then before the planned start)
  instead of scaling every premium; a missing check-out counts as the planned shift at most
  (an early check-in is not paid without approval); a per-shift premium is not paid for a day
  with nothing paid; overtime in days rounds to 4 decimals; *Mark all as planned* re-checks a
  finalised run after the lock and locks in id order; the finalise dialog lists pending
  overtime and missing check-outs. Accepted: one attendance query per line (not per run).
  Harness `m19-t5.php` 27/27.
- 2026-11-25: T7 done. The TATA add-on sets `attendance.default = true` on `rtbp_pro_features`
  (`Settings\LocalDefaults::features()`); a switch the hotel saved is kept. Checked on the test
  site: default true with the add-on, false without its filter. Pro's French catalogue was
  merged with the new `.pot` (212 untranslated, 293 fuzzy, all of R4 and M19): translating and
  compiling it is part of T8.
- 2026-11-25: T8 acceptance on live-residencetata-test.local, draft run 1 (26 Oct – 25 Nov),
  marked test rows removed after. 1: *Mark all as planned* recorded 5, a second call 0.
  2: 20:25–10:00 → late 25 min, overtime 1 h 35 pending (the spec's "1 h 45" was a slip: late
  minutes offset the extra stay). 3: no-show → unpaid time off 12 h on AFFOUE's line. 4: approve
  1 h 35 → *Overtime pay* 1.5833 h = 950, net +880; reject → nothing paid. 5: 22:00–06:00 →
  night hours follow the record. 7: finalise → `day_closed` on a write, days locked; *revert to
  draft* (not void, to keep the client's draft) → editable again. 8: self check-in, a second
  punch within a minute refused (`too_soon`), manager corrected the self punch. 9: feature off →
  no attendance routes, `attendance: null` on every line, same nets as on. 10: every write in
  the activity log with before/after. 375 px: day sheet, employee month, overtime, My attendance.
  Fixed on the way: approved overtime in hours now rounds to 4 decimals (2 gave 1.58 h);
  a missing check-out after an early check-in showed 24 h worked (now the planned shift at most);
  *left early* could exceed the shift (capped); the day arrows wrapped at 375 px.
  Run 1's stored nets were from 16 Nov (before R4); recalculated it now nets 590 895 / 108 991 /
  145 049 with or without attendance. A site administrator can record their own attendance
  (admins hold every capability); a manager without `manage_options` cannot. Pro French
  catalogue fully translated (2 263) and compiled.
- 2026-11-25 (after T8, owner's request, modelled on RadiusTask's account-menu card): check in /
  out from anywhere in the dashboard. Free adds the JS slot `rtbp.user.menu.panels`
  (`{ key, Component }`, gets `{ user, close }`) in `UserMenu`; Pro's `CheckInWidgets.jsx`
  (own lazy chunk) puts a card there (status "Working since 08:12", worked today with a live
  clock, the plan, Check in / Check out) and a top-bar button on `rtbp.topbar.actions`: a live
  `h:mm:ss` timer while checked in (opens My attendance), else *Start work* (not "Check in",
  which the front desk uses for guests). New light `GET me/attendance/now` (`self.attendance`;
  one day, the open stretch's day for a night shift; a sign-in without an employee file gets
  `{ enabled: false }`, no 404 on every page). Self check-in now **defaults to on**
  (`attendanceSelfCheckIn`); the network restriction stays optional. Checked in Chrome as the
  admin linked to an employee: check in from the card → timer runs in the top bar, check out
  after a minute → "2 min", desktop and 375 px; the test punch was removed.
- 2026-11-25 (T9, owner's request): correction requests. Staff ask from My attendance (a day's
  menu); managers decide under Attendance → Corrections. Approval goes through
  `AttendanceService::update()` / `create( …, 'request' )` (source `request`, shown in the
  entry list), so overlap, closed run and overtime recompute apply unchanged; the lock order is
  employee, then the request row. The draft run's lines read waiting requests live through
  `rtbp_pro_payroll_attendance_requests` (employee → dates) and the run notice counts them.
  Harness `t9-site.php` 22/22 on the test site (own entry only, one pending a day, future and
  unchanged times refused, cancel twice refused, approve with other times, decline leaves the
  entry, decide twice refused); checked in Chrome desktop and 375 px; test rows removed.
- 2026-11-25 (T9 critical review, fixed): (1) finalising a run locked `pro_employee_pay` while
  attendance and time-off writes lock `pro_employees`, so a write could slip into a run being
  finalised; `PayrollRunRepository::lockEmployeesOf()` now also locks the run's employee rows
  (id order, before the pay rows; checked with two processes: the write waited). (2) A request
  stores only the times the employee changed (null keeps the entry's time when decided), so
  approving never undoes a manager's later edit. (3) The kept break is dropped when it no longer
  fits the approved stretch; any field error the dialogs don't place is shown. (4) Lists load
  entries and pending days in one query each. Harness 27/27.
- 2026-11-25 (owner's request): My attendance lists every day of the month — days off
  ("Day off") and days still to come were hidden — and gains a month calendar (List | Calendar
  switch, month arrows). A cell's colour is how the day went (worked, late/early/overtime
  waiting, absent or no check-out, leave, day off); clicking a past day not yet paid opens the
  correction dialog. Phones show the date and a dot only. The employee pickers (Employee tab,
  Add extra shift) became searchable (`payroll/EmployeeSelect`).
