# R2 — Client review of 2026-11-08 (payroll)

## What the client wrote

> On "Pay" setup: overtime pay, paid time off, unpaid time off and sick pay are per hour or per
> day. Vacation pay can be a percentage or an amount. Bonus and commission can be an amount or a
> percentage (add a box). Gratification is set up like vacation pay. Transport is an amount, now
> 30 000 FCFA (add a box). Seniority is 1 % after each year: 5 years on the job adds 5 %, from the
> start of the following year's paycheck, synchronised with the hiring date. Earnings that count:
> regular pay, overtime, bonus, commission, transport allowance, gratification, seniority. Add
> Additions like Deductions and garnishments, so custom lines can go on the paycheck. A printable
> work schedule calendar with every employee.

## What exists today (checked 2026-11-08)

- Overtime, paid / unpaid time off and sick pay are entered **in hours** only.
- Vacation pay: percentage only, of regular pay plus optionally overtime, bonus, commission.
- Bonus, commission, transport, gratification: **no setting at all** — an amount is typed on
  every pay run line. Gratification has no percentage.
- A % or fixed bonus exists, but only as a **Bonus rule** in Settings → Payroll → Bonuses — not
  on the employee's Pay tab, so the client did not find it. *(exists, not understood)*
- Seniority exists in the TATA add-on as a Bonus rule kind, but with the collective-agreement
  rule (2 % after 2 years, +1 % a year, max 25 %), and **no rule is set up** on the site, so it
  is never paid. *(exists, not understood)*
- One-off additions and deductions can be typed on a run line (custom items); there are no
  recurring **additions** like the recurring deductions.
- Work shifts exist (Payroll → Work shifts) but only **1 of 35** employees has one assigned,
  and nothing prints a schedule.

## Tasks

- [x] **R2-T1** Overtime, paid time off, unpaid time off, sick pay: choose **per hour or per day**
      (rate, yearly allowance and run entries in that unit; a day = the shift's hours that day).
- [x] **R2-T2** Vacation pay: **percentage or amount**.
- [x] **R2-T3** Bonus and commission: an **amount or percentage** box on the Pay tab, paid each
      period (a run line can still change it).
- [x] **R2-T4** Gratification: same setup as vacation pay (retain / pay, percentage or amount,
      earnings that count).
- [x] **R2-T5** Transport allowance: an **amount** box on the Pay tab, paid each period.
- [x] **R2-T6** Earnings that count (vacation pay, gratification): regular pay, overtime, bonus,
      commission, transport allowance, gratification, seniority.
- [x] **R2-T7** Seniority (TATA): **1 % per full year** since the hiring date (first contract
      start), rising on each work anniversary; shown on the Pay tab.
- [x] **R2-T8** **Additions** on the Pay tab, like Deductions and garnishments (recurring, amount
      or percentage, taxable or not).
- [x] **R2-T9** **Printable work schedule**: every employee × the days of a week or month, with
      their shift and time off.
