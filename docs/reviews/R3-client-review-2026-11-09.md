# R3 — Client review of 2026-11-09 (receipt, payments, cash register)

## What the client wrote

> The QR code receipt does not show the payment method. It should be on the QR code receipt and it
> will be good if we can check in and check out right from the QR code receipt so we don't have to
> go to the dashboard. It is some kind of shortcut.
>
> The receipt should have the phone number as well as the email … (225)0788154302
>
> There should be a payment reference number with each payment method so we can register each
> payment and they can be verified.
>
> Here is a nice feature that can be added to the dashboard. At the beginning of each shift, the
> staff member clicks on the button "Open" with date and time, then all transactions get
> automatically tallied in that box. At the end of the shift, they click "Close" with date and
> time and end their shift so they can start counting the cash money to make sure it matches the
> numbers on the cash register. This will help a lot so staff members don't get access to other
> areas.

Sketch: a **Cash register** box with **Open** and **Close** buttons and one total per payment
method (Wave = 19 098 FCFA, Orange Money = 20 000 FCFA, Cash = 220 000 FCFA).

## What exists today (checked 2026-11-09 on live-residencetata-test.local)

- **"QR code receipt" = the guest's booking page** (`templates/booking/confirmation.php`). It is
  where the QR code leads a guest, it shows the code (Pro `QrSlots`), and it is the only
  QR-carrying page without the payment method: its Payment card shows Total / Paid / To pay
  only. The printed receipt (`templates/documents/receipt.php`) and the *payment received*
  e-mail already show the method and reference. *(assumed, Q1)*
- The guest page shows the guest's name only — no phone or e-mail. The printed receipt shows the
  guest's phone but not their e-mail.
- **The hotel phone is empty** in Settings → General (the e-mail `service@residencetata.com` is
  set). Every document prints the hotel's phone · e-mail line, so (225) 07 88 15 43 02 is most
  likely the hotel's number and only needs entering. *(assumed, Q2)*
- A payment already has a `reference` (≤ 100 chars), typed in *Record payment*, but it is
  optional for every method. On the test site all 6 payments (4 cash, 2 Wave) have one.
- Scanning a booking QR (`/?rtbp_scan=`, Pro) takes staff to the booking in the dashboard; the
  header's *Scan* dialog does the same. Check in / out are on the booking screen and the
  Bookings row button (access keys, passcode).
- Per-method payment totals exist only in the sales report (`ReportRepository`, `GROUP BY
  p.method`), which needs `page.reports_sales`. **No cash register / shift session exists.**

## Decisions

| Request | Decision | Tier |
|---|---|---|
| Payment method on the QR receipt | List every payment on the guest page: date, method, reference, amount, receipt link | Free |
| Phone and e-mail | Enter the hotel phone in Settings (data, no code). Add the guest's phone and e-mail to the guest page and the guest's e-mail to the printed receipt | Data + Free |
| Reference per payment method | Each method says whether a reference is **not asked / optional / required**, with its own label ("Transaction ID"). *Record payment* enforces it; the Payments list searches by it | Free (setting); TATA sets Wave / Orange Money to required (data) |
| Check in / out from the QR | A staff scan opens a **quick card** in the dashboard, not the guest page: guest, rooms, status, balance, and Check in / Check out / Record payment buttons under the usual access keys and passcode. The public guest page stays guest-only (it is reachable with the token alone) | Pro (BookingQr) |
| Cash register | New switchable Pro feature `cash_register`: open a session, live per-method tally of the payments that staff member records, close with a count and the difference, printable slip, history for managers. Its own access keys, so front desk staff need neither Payments nor Reports | Pro |

Nothing here is specific to Côte d'Ivoire: Wave and Orange Money are payment methods configured
in Settings, and FCFA is the site currency.

## Open questions (ask the client)

| # | Question | Default if no answer |
|---|---|---|
| Q1 | Is the "QR code receipt" the page the QR code opens on the guest's phone (a screenshot would confirm)? | The guest booking page |
| Q2 | Is (225) 07 88 15 43 02 the hotel's number? | Yes: enter it in Settings → General |
| Q3 | "So they can be verified": is the reference enough, or should a manager tick each mobile-money payment as **verified** against the Wave / Orange Money statement? | Reference only; verification is a later task |
| Q4 | One register per staff member, or one shared register for the front desk at a time? | Per staff member (the shared "Accueil" login then has one register) |
| Q5 | At close, should staff count the cash **before** seeing the expected total (blind count)? | Yes for cash; the other methods are shown, not counted |
| Q6 | Must a register be open to record a payment? | A setting, off by default; turn on for TATA |
| Q7 | Do they start each shift with cash already in the drawer (opening float)? | Yes: an optional opening amount, counted in the expected cash |

## Tasks

- [x] **R3-T1** `[Free]` Guest booking page: a **Payments** list under the totals — date,
      method label, reference, amount, receipt link; refunds and voids shown as such.
- [x] **R3-T2** `[Data]` Settings → General → Phone: (225) 07 88 15 43 02 *(after Q2)*.
      `[Free]` Guest page: the guest's phone and e-mail under their name. Printed receipt: the
      guest's e-mail beside the phone (as on the invoice).
- [x] **R3-T3** `[Free]` Payment methods (Settings → Payments): per method **Reference: not
      asked / optional / required** and a **reference label** (default "Reference"). *Record
      payment* shows the field with that label and refuses a missing required one (server
      check too). The Payments list shows and searches the reference. Defaults: cash not asked,
      others optional. `[Data]` TATA: Wave and Orange Money required, label "Transaction ID".
- [x] **R3-T4** `[Pro]` Scan quick card: a staff scan (QR link, *Scan* dialog, hand-typed
      reference) opens a card with the guest, each room line with its status, the balance due,
      and **Check in / Check out** per line, **Record payment** and **Open booking**. Same
      service calls, access keys (`page.bookings`, check-in/out, `payments.record`) and passcode
      as the booking screen; a guest still lands on the guest page.
- [x] **R3-T5** `[Pro]` Cash register — data: `pro_register_sessions` (`user_id`, `opened_at`,
      `opening_float`, `closed_at`, `closed_by`, `expected` JSON per method, `counted` JSON,
      `note`, `status` open|closed). One open session per user (or per desk, Q4). The tally is
      the payments **recorded by that user** between open and close, per method, less refunds
      and voids; it is read from the free payments table, which gets no new column. Open and
      close logged in the activity log.
- [x] **R3-T6** `[Pro]` Cash register — screen: a dashboard page (and a Dashboard card) with
      **Open** (opening cash), the live box (one total per method, as in the sketch), **Close**:
      count the cash (blind, Q5), see expected / counted / difference per method, add a note,
      print the close-out slip.
- [x] **R3-T7** `[Pro]` Cash register — access: `register.use` (own register) and
      `register.manage` (every session, history, discrepancies). Front desk role template:
      `register.use` + `payments.record` + check-in/out, without Payments or Reports.
      Optional setting *Require an open register to record a payment* (Q6).
- [x] **R3-T8** `[Pro]` Cash register — history: list of sessions (who, opened, closed,
      totals, difference), filter by staff and date, the slip again; manager only.

## Acceptance

- A guest opening the QR link sees every payment with its method and reference, and the hotel's
  phone and e-mail.
- *Record payment* with Wave cannot be saved without a transaction ID once Wave is set to
  required.
- A front desk user scans a booking QR and checks the guest in without opening the Bookings
  screen.
- A front desk user without Payments or Reports access opens a register, takes payments, sees
  them tallied per method, closes it and prints the slip; a manager sees the session and its
  difference.

## Progress notes

- 2026-11-09: review recorded; decisions above. Build order T2 (data) → T1 → T3 → T4 → T5–T8.
- 2026-11-09: started with the defaults of Q1–Q7 (user). T2 data: hotel phone set on the test site.
  T1/T2/T3 done in free; TATA add-on defaults make Wave and Orange Money require a "Transaction ID".
  The Payments screen is the follow-up list, not a payment ledger, so the reference is searched
  from **Bookings** (exact match, 4+ characters). Imports (`$import`) skip the required rule.
- 2026-11-09: T4 done. Pro route `#/scan/:id` (`ScanCard`); the QR link, the Scan dialog and a
  typed reference open it. *Record payment* goes to `#/bookings/{id}?pay=1` (free opens the
  dialog). Free `matchRoute()` now falls back to `:param` routes so the card gets its title.
  Check in / out on the card call free's `booking-lines/{id}/check-in|check-out`; not pressed on
  the shared test site (same calls as the Bookings row button).
- 2026-11-09: T5–T8 done as Pro feature `cash_register` (on by default): table
  `pro_cash_sessions` (Pro DB 1.7.2), `CashRegister\*`, route `#/cash-register` (Front desk),
  Settings → Cash register (*Require an open register*, on by default for TATA via
  `LocalDefaults`). Free adds `rtbp_payment_validated` (fired from `PaymentService::validate()`
  when strict) so Pro can refuse a payment with the register closed.
  **Q5 changed:** the client's sketch shows the per-method totals live, so the box shows them and
  Close asks for the counted cash afterwards (no blind count).
  The tally is per user: payments, refunds and voids they recorded between open and close
  (`created_at`); a void of an older payment counts against the register of whoever voids it.
  Not done: a Dashboard card for the register (follow-up). Supervisor and Accountant templates
  get `register.manage` on new sites only (existing roles keep the key default, locked).
- 2026-11-10: Dashboard card done. Free implements the `rtbp.dashboard.widgets` seam from ADR-015
  (`{ key, Component, area: 'top'|'side', order, accessKey }`, hidden when the key is locked);
  Pro's `CashRegisterCard` (area `top`, `register.use`) shows Open (with opening cash) or the live
  per-method totals with Close, and the closed shift's summary and slip. It reuses the screen's
  `OpenForm`, `CloseDialog`, `ClosedSummary` and `printSlip`.
- 2026-11-10: fix (user, staff test): a maintenance user saw Cash register (menu and card).
  `register.use` defaulted to `open`, so every role saved before the key existed got it. Now
  `locked` by default; the Receptionist and Night receptionist templates open it, and a one-time
  step on `rtbp_pro_installed` (Pro DB 1.7.3, option `rtbp_pro_register_use_granted`) adds it to
  existing ones that set neither the key nor `*`. *Require an open register* now skips users
  without `register.use` (they have no register to open). Staff on the built-in Staff role get the
  register only when Settings → Permissions, an access role or an override opens it.
