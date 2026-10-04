# M15 — Payroll and HR

| | |
|---|---|
| **Client module** | 15 — Payroll and HR |
| **Features** | 15.1–15.21, 17.3 |
| **Depends on** | M12, and **D1 must be answered "in scope"** (blocking) |
| **Tier** | **Client add-on** |
| **Engine** | no |
| **Critical review** | yes (statutory maths, run lifecycle) |

## Goal

Côte d'Ivoire payroll: pay runs with a strict lifecycle, statutory contributions (CNPS, IS, CN,
IGR) at the correct rates and caps, payslips, statutory payment tracking, monthly and yearly
reports, and time off with an annual quota.

> **Blocking open decision D1.** `/next-module` must ask before starting. If the answer is *out
> of scope*, set this module to `blocked` in the tracker and skip it.

## Statutory rules (from the legacy code; verify each against current CI law with the client's accountant before go-live)

Base definitions, per employee per month:

- `GROSS` = base pay + taxable additional pay
- `CNPS_EMP` = 6.3 % × min(GROSS, 2 700 000) *(retirement, employee share)*
- `BASE80` = 80 % × (GROSS − CNPS_EMP)
- `IS` = 1.5 % × BASE80
- `CN` on BASE80 by bracket: ≤ 600 000 → 0 % · ≤ 1 560 000 → 1.5 % · ≤ 2 400 000 → 5 % ·
  above → 10 %. Confirm with the accountant whether this is progressive by slice or flat on the
  whole amount, since the legacy code has two copies of the brackets.
- `IGR` on `T` = (BASE80 − IS − CN) × 85 %, progressive: 0 % to 300 000 · 10 % to 547 000 ·
  15 % to 979 000 · 20 % to 1 519 000 · 25 % to 2 644 000 · 35 % to 4 669 000 · 45 % to
  10 106 000 · 60 % above. Family quotient (the *parts* from marital status and children), per
  the legacy implementation.
- Employer: retirement 7.7 % (cap 2 700 000) · family allowance 5.75 % (cap 70 000) · work
  accident at the configured rate of 2–5 % (cap 70 000).
- NET = GROSS − CNPS_EMP − IS − CN − IGR − deductions − garnishments.
- Money is `DECIMAL(14,2)`, calculated with bcmath, and rounded to the whole franc at the end
  of each line.
- Authorities: CNPS, DGI. Codes: `CNPS_PENSION`, `IS`, `CN`, `IGR`, `CNPS_FAMILY_ALLOWANCE`,
  `CNPS_WORK_INJURY`.

All rates, caps and brackets live in **one** `Payroll\StatutoryTable` class (versioned by
effective date), never duplicated. Every bracket boundary is checked on the Local site against the accountant's figures.

## Scope

| Feature | Summary | Task |
|---|---|---|
| 15.14, 15.15 | `StatutoryTable` + `PayrollCalculator` (pure, table-tested) + the work-accident rate setting | T1 |
| 15.9 | Pay schedules: name, interval (weekly / biweekly / twice-monthly / monthly; monthly factors 52/12, 26/12, 2, 1), next payday, period end, employees | T2 |
| 15.10 | Work shifts: name (*Jour*, *Nuit*, *Jour de repos*), hours per weekday, employees | T2 |
| 15.1, 15.2 | Run payroll for a schedule and period → a draft run with one line per employee; run history | T3 |
| 15.8 | Edit an individual line (hours, additional pay, deductions) with recalculation | T3 |
| 15.3 | Finalise (only after the period end; locks the lines) | T3 |
| 15.4 | Settle (a payment method and optional reference per line) → payslips available | T3 |
| 15.5, 15.6, 15.7 | Void · revert (finalised → draft) and re-run · remove (drafts only) | T3 |
| 15.12, 15.13 | Statutory contributions per period, authority and code (amount, paid, balance, state `unpaid|partial|paid|voided`); record a statutory payment | T4 |
| 15.16, 15.17 | Monthly and yearly payroll reports; statutory summary; export to spreadsheet | T5 |
| — | Payslip PDF (the documents template family, ADR-005) | T5 |
| 15.18, 15.19 | Time-off requests (type `day_off|sick|absent|late|unpaid|other`, period, days, reason, balance) and a pending queue with approve / decline + history | T6 |
| 15.20, 17.3 | Vacation quota per year (0 = unlimited), checked on `day_off` | T6 |
| 15.11 | Employment contracts: built in M12 (`employee_contracts`); payroll reads the active contract | — |
| 15.21 | Business details: reuses the M17 *General* section (legal name, logo, tax no., CNPS no., address) | — |

## Data

`pay_schedules`, `work_shifts` (+ `work_shift_days`), `payroll_runs` (`status`
draft/finalized/settled/voided, `schedule_id`, `period_start`, `period_end`, `pay_date`, totals),
`payroll_lines` (per employee: hours, gross, each statutory amount, deductions, net, `breakdown`
JSON, `payment_method`, `payment_ref`), `statutory_items`, `statutory_payments`, `time_off_requests`,
`time_off_balances` (employee, year, type, used). The full columns are in `architecture.md`.

## Access keys

`page.payroll`, `payroll.run|finalize|settle|void|revert|remove|edit_line|schedules|shifts|statutory_pay|tax_settings`, `timeoff.approve`. All are locked by default.

## Activity log actions

The same keys as the access keys, plus `timeoff.request|approve|decline`, `payroll.payslip_download`.

## Tasks

- [ ] T1 [Client] `StatutoryTable` + `PayrollCalculator` + verification on the Local site of every bracket edge, caps, zero salary and hourly pay
- [ ] T2 [Client] Pay schedules + work shifts (tables, API, screens)
- [ ] T3 [Client] Runs: create, edit line, finalise, settle, void, revert, remove (a state machine; illegal moves checked on the Local site)
- [ ] T4 [Client] Statutory contributions + payments
- [ ] T5 [Client] Payslip PDF + monthly / yearly / statutory reports + export
- [ ] T6 [Client] Time off (requests, approvals, balances, quota) + the vacation quota setting
- [ ] T7 [Client] Staff self-service, carried over from M16 T3: *My payslips* (settled runs; PDF of their own payslip only, another id → 403; `me/payslips`, `me/payslips/{id}/pdf`) and *My time off* (calendar, balance, request, cancel pending; `me/time-off`), behind `page.my_payroll`, logged `self.payslip_download` / `self.timeoff_request` / `self.timeoff_cancel`, registered on the Pro avatar menu (`rtbp.user.menu`) and *My profile* routes (16.7–16.9). Acceptance: M16 step 3

## Acceptance

1. Five hand-calculated payslips supplied by the client's accountant (a low salary, one at each
   CN bracket, one above the CNPS cap) match to the franc.
2. Finalising before the period end is refused. Settling without a payment method is refused.
3. Void, revert and re-run produce a new calculation, and the history keeps both.
4. A `day_off` request beyond the quota is refused with a message.

## Legacy reference

`legacy-reference.md` → Module 15 (`RTA/payroll/class-payroll-tax.php` has the exact formulas).

## Migration notes

Import the legacy `hbfwc_payroll_*` tables: schedules, shifts, contracts, runs (read-only
history), statutory items and payments, time off, and the per-year balances `staff_*_{Y}`. Past
runs import as `settled` or `voided` and are never recalculated.

## Progress notes
- 2026-10-04: M16 closed without its T3; the payslip and time-off self-service (16.7–16.9) moved here as **T7**, since it is Client-tier and needs T3 (runs), T5 (payslip PDF) and T6 (time off). The *My profile* screen, the `staff_self_service` feature and `rtbp.user.menu` (Pro/Free) are in place to hang it on.
