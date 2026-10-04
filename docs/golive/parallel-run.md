# Staging parallel run

One week (acceptance 4) in which the desk keeps working in the **old system on production**, and
every evening we bring that day's data into **staging** and compare. Nobody books in the new system
during this week except for the UAT script (uat-script.md), on staging.

## 1. Staging set-up (D − 14)

1. Copy production to staging: database + `wp-content/` (a restorable copy, the same one rollback
   needs). Stop staging from sending e-mail (an SMTP plugin in "log only", or `pre_wp_mail`
   returning true in a mu-plugin) and from running WooCommerce payments.
2. Install free, Pro and the Residence TATA add-on (the build planned for cutover). Settings as
   in cutover-runbook.md §2 (currency, payments, time zone **Africa/Abidjan**).
3. First dry run: `wp radius-hotel-booking import:legacy --dry-run --user=1`. Read the report with
   the manager (below). Fix what is ours; list what the hotel must fix in the old system. On a
   fresh copy the bookings come out *skipped — not checked*: import the inventory first, then
   dry-run again (cutover-runbook.md §3).
4. Real import on staging, then a second dry run: everything *unchanged*.

## 2. What the first report will say (from the 30 Aug copy)

| Area | Expect | What to do |
|---|---|---|
| Room types / floors / rooms | 2 / 5 / 38, all created | none |
| Rate plans | 7, merged by window | check the *flexible* plan: imported as 24 h, check-in any time (the old system stored no hours) |
| Prices | 14 room-type prices | compare a few with the old product pages |
| Pricing rules, blocks, iCal | none in the old system | if any appear: recreate by hand (the report says where) |
| Guests | ~2 868 + ~374 merged duplicates; ~184 of the merges are under **another name** (150 a shared phone, 34 a shared e-mail); 16 guests lose an e-mail several people shared | one phone or e-mail is one guest here: the desk checks the *another name* list (same person, or correct the contact) |
| Staff | 49 files, **22 without a phone** | add the phones (staff file) |
| Staff contracts | 27, pay details not imported | payroll waits for D1 |
| Permissions, PINs | 29 people, PINs carried hashed | a PIN changed in the old system during the week is **not** carried again — staff set it on staging / after cutover |
| Bookings open at cutover | all pending / confirmed / in house | stale "checked in" stays (never checked out) are reported, not imported — the desk checks them out in the old system |

## 3. Every evening (≈ 20 minutes)

1. Refresh staging's **legacy tables** from production (a dump of the `hbfwc_*`, `posts`,
   `postmeta`, `terms*`, `users`, `usermeta`, `options`, `wc_orders*`, `woocommerce_order_*` tables;
   or a full DB copy, then re-import from scratch).
2. `wp radius-hotel-booking import:legacy --dry-run --user=1 --format=json > day-N-dry.json`, then
   the real run, then a dry run again (must be all *unchanged*).
3. Compare, in this order, and write each difference in the discrepancy log (§4):
   - **Tomorrow's arrivals** and **today's in-house**: old desk screen vs the new Dashboard.
   - **Availability, next 7 days**, every room type: old grid vs new Availability screen
     (acceptance 2).
   - **Five bookings** made today in the old system: room, window, total, paid, guest.
   - **Sales today**: old report vs Reports → Sales (same day, same method).
4. Anything changed in the old system *after* it was imported is reported, never re-imported:
   *Changed in the old system since it was imported* (status, room, dates, price or payment) and
   *Now "cancelled" in the old system … apply it to booking RT-… by hand*. On staging we re-import
   from a fresh copy; at cutover the desk applies late changes by hand.

## 4. Discrepancy log

| # | Day | Where | Old system | New system | Cause | Fixed in | Status |
|---|---|---|---|---|---|---|---|
| 1 | | | | | | | |

A discrepancy is closed only with a cause. "Unexplained" blocks go-live.

## 5. Staff during the week

- Training (training-notes.md) on D − 10, on staging.
- Each member of staff signs in to staging with their usual password, opens **My profile**, checks
  their details, adds their phone if missing, and confirms one action with their PIN.
- The UAT script (uat-script.md) run once, end to end, by the desk with us.

## 6. Go / no-go (D − 1)

Go only when **all** of these hold:

- [ ] Five consecutive evenings with **zero unexplained discrepancy** (acceptance 4: one week).
- [ ] No open critical defect (critical = wrong availability, wrong price, lost booking or payment,
      a staff member locked out).
- [ ] UAT script signed by the manager.
- [ ] Every report error of the last dry run has its action agreed (cutover-runbook.md §3 table).
- [ ] Backup + restore rehearsed (rollback-plan.md).
