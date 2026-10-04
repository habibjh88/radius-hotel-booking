# Rollback plan

The old system is never deleted, and the import only **reads** it, so going back is always
possible. What changes is how much of the new system's work must be carried back by hand.

## When to roll back

| Situation | Decision |
|---|---|
| During cutover, before §6 (the old plugins still active) | **Roll back now** if the import report differs from the last parallel-run evening in a way nobody can explain, or acceptance checks (§5) fail |
| Day 0–2 after cutover | Roll back on a **critical** defect without a same-day fix: wrong availability (double booking possible), wrong prices, lost bookings or payments, staff locked out |
| Day 3–7 | Prefer fixing forward; roll back only for a critical defect that cannot be fixed within 24 h |
| After day 7 | No rollback; fix forward |

The decision is the manager's, with us. Write it in the go-live log with the time.

## A. Before switch-over (cutover-runbook.md §1–§5)

Nothing has happened in the old system since the freeze.

1. Deactivate the Residence TATA add-on, Pro and free (WP-CLI or Plugins screen).
2. Leave their tables: they hold nothing the old system needs. (To clean up later, after a decision:
   free's *Delete data on uninstall* setting, then delete the plugins — never the legacy ones.)
3. Re-open the old system: online booking back on, the desk resumes. Enter the paper walk-ins there.

Time: 10 minutes. Data loss: none.

## B. After switch-over (cutover-runbook.md §6 done)

The new system has taken bookings and payments the old one does not know.

1. **Freeze the new system**: online booking off; the desk on paper.
2. Export from the new system everything since the cutover moment, before touching anything:
   Exports → bookings since the cutover date (CSV), Reports → payments since then, and the activity
   log (Pro) for the same period.
3. **Re-activate** the legacy plugins (`hotel-booking-for-woocommerce`, `hbfwc-pro-addon`,
   `hbfwc-residencetata-addon`) and deactivate the add-on, Pro and free.
4. Re-create in the old system, by hand, each booking / payment / check-in made since cutover (from
   the export in step 2). The desk and one of us, booking by booking; tick each off.
5. Check tomorrow's arrivals and the next 7 days' availability against the export.
6. Re-open online booking.

Time: about 1 hour plus 5 minutes per booking made since cutover. Data loss: none if step 2 was
done first.

## C. Full restore (last resort)

Only if the database itself is damaged: restore the D − 1 backup (database + uploads), then do B's
step 4 for everything since the backup. Everything since the backup must come from the exports,
the paper log or the old system's e-mails.

## Afterwards

- Write what happened, why, and what must change before the next attempt.
- The next attempt starts again from the parallel run (at least 3 clean evenings).
