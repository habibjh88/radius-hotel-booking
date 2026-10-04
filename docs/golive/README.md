# Go-live — Residence TATA

How the hotel moves from the legacy WooCommerce stack (`hotel-booking-for-woocommerce`,
`hbfwc-pro-addon`, `hbfwc-residencetata-addon`) to Radius Hotel Booking (free + Pro + the
Residence TATA add-on). Written in M18 T6 from what the import found on the 30 Aug 2026 copy.

| Document | For | When |
|---|---|---|
| [parallel-run.md](parallel-run.md) | us + the hotel manager | the staging week before cutover |
| [uat-script.md](uat-script.md) | the hotel's staff, with us | during the parallel run |
| [training-notes.md](training-notes.md) | every member of staff (FR / EN) | before the parallel run |
| [cutover-runbook.md](cutover-runbook.md) | us, minute by minute | cutover day |
| [rollback-plan.md](rollback-plan.md) | us | if cutover fails, and the week after |

## Timeline

| When | What | Who |
|---|---|---|
| D − 14 | Staging site from a production copy; plugins installed; first dry run; review the report with the manager | us |
| D − 10 | Staff training (training notes); staff check their own profile, phone and PIN on staging | us + manager |
| D − 9 … D − 2 | **Parallel run**: the desk works in the old system; every evening we refresh staging, import, and compare (parallel-run.md). UAT script run once in full | us + staff |
| D − 1 | Go / no-go meeting: zero unexplained discrepancy, no critical defect, UAT signed | manager + us |
| D (quiet hour) | **Cutover** (cutover-runbook.md), ~60 minutes, the desk on paper meanwhile | us |
| D … D + 7 | Monitored week: daily check-in with the desk, the import report kept, rollback still possible (rollback-plan.md) | us |
| D + 30 | Legacy plugins stay **deactivated, never deleted**; the legacy tables stay in the database | — |

## Open items that change these documents

- **D1 — payroll.** Undecided. Nothing payroll-related is imported; staff contracts come without
  pay amounts (27 contracts "wait for payroll"). If D1 = yes, M15 adds its own import step.
- **A fresh production dump** before D − 14: every count in these documents comes from the 30 Aug
  copy (last booking 13 Apr 2026). The real numbers come from the first staging dry run.
