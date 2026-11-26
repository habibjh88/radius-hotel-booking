# R4 — Client review of 2026-11-25 (payroll video: the pay statement)

## What the client said (video, Settings → Payroll and the staff Pay tab)

The pay statement must be one list of **every** payroll item: additions and deductions, items
from Settings → Payroll and from the Pay tab, seniority included. For each one he types hours, a
percentage or an amount. Only what he fills in appears on the document. The total at the bottom
is what the employee is paid; it may be below zero (the employee owes the hotel), shown in red.

## Answers (user, 2026-11-25)

| # | Question | Answer |
|---|---|---|
| Q1 | Monthly payslip or final settlement? | The regular monthly payslip. A staff member may borrow money or be fined, so a month can come out negative. |
| Q2 | Statutory items editable? | Calculated automatically by default; managers and admins can change them. |
| Q3 | What happens to a negative net? | Our choice, done professionally: carried to the next pay. |
| Q4 | Pre-filled defaults? | Our choice, done professionally: the Pay tab's setup applies on its own; the line can change it. |

## Decisions

| Request | Decision | Tier |
|---|---|---|
| Every item on the list | The run line lists every pay type, whether or not it is on the Pay tab. A type not on the Pay tab counts only when typed, with plain rules (hourly rate, no yearly quota) | Pro |
| Hours, % or amount | Hours types stay hours/days; amount types (bonus, commission, transport, gratification, vacation pay) switch between amount and % of base pay | Pro |
| Only filled items print | Already so on the payslip; zero amounts are now left out too | Pro |
| Statutory editable | New card *Calculated amounts* on a draft line: base pay, each item, bonus rules (seniority), deductions, every tax and contribution (employee and employer), garnishments, balance from previous pay. Empty = calculated. New access key `payroll.override` (locked; Manager template gets it through `*`), logged with both figures. Settled runs stay locked: void and re-run (audit trail) | Pro |
| Negative total | Finalising no longer refuses it. Ledger `balance_owed` / `balance_recovered`; the next line takes it back as *Balance from previous pay* (a manager can take less). Red on the line, the run, the payslip and *My payslips*; settling needs no method when there is nothing to pay | Pro |

## Tasks

- [x] R4-T1 [Pro] Override any calculated amount of a draft line (`payroll.override`) — done 2026-11-25
- [x] R4-T2 [Pro] Every pay item on every line; amount or % of base pay — done 2026-11-25
- [x] R4-T3 [Pro] Negative net allowed, red, carried to the next pay — done 2026-11-25
- [x] R4-T4 [Pro] Approved time off on the pay line: listed, fills the time-off items, change warning — done 2026-11-25
- [x] R4-T5 [Pro] Attendance (new module M19, `docs/modules/M19-attendance.md`) — done 2026-11-25

## Progress notes

- 2026-11-25: `LineCalculator` takes `overrides`, `entry_modes`, `outstanding`; returns `overridden`
  (key → calculated) and `adjustable` (the rows the UI offers). Override keys: `base`, `item:<type>`,
  `premium:<kind>`, `bonus_rule:<key>`, `addition:<id>`, `gratification`, `vacation`,
  `deduction:<id>`, `stat:<line>`, `garnishment:<id>`, `balance_forward`. An override whose row is
  gone is dropped on the next calculation. `PUT payroll/runs/{id}/lines/{line}/overrides`.
- Checked with a standalone harness (35 checks: overrides, typed items, % mode, negative net and
  its recovery, partial and capped recovery). Not yet clicked through on a Local site: the Local
  sites were stopped (no MySQL socket).
- 2026-11-25: browser test on live-residencetata-test.local (run 1, ADJA MOBIO line, restored after):
  all items listed, commission 10 % = 7 500, a 300 000 fine → net −183 158 in red, IS changed
  1 408 → 500 (net +908, "Changed", logged as `payroll.override`), payslip shows only filled lines,
  zero CN/IGR hidden, red note. Fixed on the way: net row not red in the dialog, a changed tax still
  printed its rate, labels cut at 375 px, no hint on lines calculated before the update.
  The run's own Finalise → Settle with a negative line and the next-pay recovery were not clicked
  (it would finalise the client's real draft); the harness covers that maths.
- Follow-up: the monthly/yearly reports add net pay as is, so a negative line lowers the total;
  a "paid out" column (net above zero only) may read better. Voiding a settled run whose negative
  balance was already taken back leaves the recovery counted (the balance cannot go below zero).
- 2026-11-25, R4-T4 (raised by the owner while reviewing the run screen, not by the client): staff
  who take leave were invisible on the pay line. New pure `Payroll\TimeOffOnLine::summarise()`
  turns the period's approved requests into: the absence days left out of the night, Sunday and
  holiday count; the scheduled hours (or days, by the item's unit) that fill the line's time-off
  items (vacation day off → paid time off, sick → sick pay, unpaid and absent → unpaid time off;
  a typed entry wins); per request, the premium hours it left out; and a fingerprint.
  `late` and `other` are shown only and **no longer** reduce the premiums (before, any approved
  type did, so a late arrival lost the night premium for the whole shift). A day in two requests
  counts once; a shift day off is no time off. `TimeOffRepository::approvedInPeriod()` (one query
  for every employee of a run). The line's settings keep `time_off { requests, fills, fingerprint }`;
  `get()` flags `time_off_changed` on a draft whose approved requests no longer match. UI: card
  *Time off in this period* on the line, time-off entries show "From approved time off: 12 h" as
  placeholder, Night work's help says how many hours time off left out, run screen banner and a
  "Time off changed" marker per row. Harness 13/13 (overlap, clipping, shift days off, Saturday
  night shift running into Sunday, late not skipped, fingerprint). Checked on the test site with
  four marked requests on run 6 (Accueil): night 160 → 144 h, sick pay 12 h, unpaid 12 h deducted
  17 308, banner before Recalculate and gone after; requests deleted and run 6 recalculated back to
  the same nets (587 244 / 104 939 / 106 329).
- 2026-11-25, R4-T5: Attendance built as Pro feature `attendance` (M19), on by default for TATA.
  Managers record each day (staff without a login included), *Mark all as planned* for a normal
  day, overtime is paid only once approved, absences fill sick pay / unpaid time off, and staff
  who can log in may check in and out themselves when the hotel switches it on. A day without a
  record is paid as planned, so payroll is unchanged until the hotel starts recording.
  Acceptance in the M19 progress notes. Four questions for the client are in the reply.
- 2026-11-25, video 2 (`../video-2.txt`): "last statement" is the **final settlement** ("solde de
  tout compte"), a separate feature with its own menu, not the monthly payslip (Q1 above was
  answered for the payslip; that work stands). Wanted: pick the worker, pick and fill the items
  owed, grand total (may be negative), document printed in French (legal requirement), a law
  reference on each item. Sent to the client for confirmation with five questions (only when a
  worker leaves? usual items? law numbers from the accountant? payslips in French too?
  signature line?). **Waiting for the answer — do not build yet.** Likely shape: Pro "Last
  statement" document + per-pay-type legal reference + a document-language setting; TATA turns
  on French and supplies the Ivorian law references.
- 2026-11-26, final settlement confirmed by the client (used when a worker resigns, is dismissed
  or reaches the end of the contract; no answers to the five questions, so built to the video).
  Built as Pro feature `final_settlement` (group Staff, needs payroll), on by default for TATA:
  - **Menu** *Final settlements* (`#/final-settlements`): list by status, editor per worker.
  - **Editor:** pick the worker → contract, start date, service length, monthly/daily/hourly pay,
    balance still owed and open loans load from the file. Pick items from a catalogue (pay,
    leaving indemnities, deductions, the hotel's bonus rules, plus free "other" lines); each item
    is an amount, quantity × rate, or a percentage of a base. Totals are worked out on the server
    and may be negative ("Balance owed by the employee").
  - **Lifecycle:** draft → finalised (locked; legal references frozen; optionally ends the
    contract in the same transaction) → voided with a reason. One live settlement per worker.
    Access keys `page.final_settlements`, `settlement.edit|finalize|void`; every step is logged.
  - **Document:** "Solde de tout compte" in the *document language* (Settings → Payroll → Payroll
    documents; TATA: French), each item with its legal reference, total in words, the receipt
    sentence (Code du travail art. 32.6 / 33.5), "Fait en deux exemplaires… à Abidjan", and the
    handwritten "Lu et approuvé, pour solde de tout compte" signature box. Print or PDF.
  - **Legal references** (Settings → Payroll → Legal references) are global and empty in Pro;
    TATA fills the Ivorian Labour Code (Loi 2015-532), Convention collective interprofessionnelle
    1977, Décret 96-203, Décret 2017-210, CGI (ITS) and CNPS references. Payslips print them too.
    **The accountant should check them** — transport (Arrêté 2020-012) and ITS article numbers are
    the least certain.
  - Not done: the settlement does not write back to the payroll ledger (`balance_recovered`), so
    a balance owed it settles must be cleared by hand on the next pay run if any.
