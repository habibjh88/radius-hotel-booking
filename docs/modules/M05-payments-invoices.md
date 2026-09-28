# M05 — Manual payment, invoice and confirmation

| | |
|---|---|
| **Client module** | 5 — Manual payment, invoice and confirmation ★ (the client's first request) |
| **Features** | 5.1–5.14, 17.12, 17.13, 3.3, 3.4 |
| **Depends on** | M03 (booking record) |
| **Tier** | Free (ledger, deadlines, invoice numbers + print view, e-mails, overdue list) + **Pro** (PDF invoices/receipts, auto-release) |
| **Engine** | yes (deadline release frees inventory; read `booking-engine.md` §8) |
| **Critical review** | yes (money, ledger, invoice numbering) |

## Goal

No online gateway. A booking is placed unpaid, and the room is held. The guest sees and receives
payment instructions and a numbered PDF invoice. Staff record each payment (amount, method,
reference, date). The booking becomes *partially paid* or *paid* from the ledger, never from a
button that sets a status. A receipt goes out, and overdue unpaid bookings are followed up or
released automatically.

## Model

- **Payment methods** are configured in Settings: key, label (FR/EN), instructions (FR/EN, with
  merge tags `{amount}`, `{reference}`, `{deadline}`, `{hotel_name}`), account details, enabled,
  and sort order. The defaults are *Cash at desk*, *Wave*, *Orange Money* and *Bank transfer*.
  Nothing is hard-coded.
- **Ledger (ADR-010).** A `payments` row is `type` payment / refund / adjustment, with `amount`,
  `method`, `reference`, `received_at`, `recorded_by` and `note`. Append-only. A mistake is
  corrected with a `void` (a new row referencing the original, amount negated, reason required),
  never with an edit.
- **Payment status** is derived by `PaymentService::recalculate( $booking )`:
  `paid_total = Σ amounts`, `balance = total − paid_total`, and
  `status = unpaid | partially_paid | paid | refunded`. `on_hold` is a manual flag the desk can
  set (legacy parity), and it is cleared by the first payment.
- **Deadline.** `payment_due_at = min( created_at + dueHours, first line start − beforeArrivalHours )`,
  from the booking-rules settings (D6). *Overdue* is derived: unpaid or partial, and past the
  deadline.
- **Invoice.** One invoice per booking, created when the booking is created. Its number comes
  from `Sequence('invoice')` inside the booking transaction: `{prefix}{year}-{000123}`, unbroken
  (5.8). If lines change, the invoice is **re-issued** (version 2 or later) under the **same
  number**. The PDF shows *Revised*, and all versions are kept. A cancelled booking's invoice is
  marked *Cancelled*; it is not deleted.
- **Receipt.** Generated for each payment row (`R-{invoice number}-{n}`), printable and e-mailable.

## Scope

| Feature | Summary | Task |
|---|---|---|
| 5.1 | A booking placed without payment keeps its room (holds become lines; `payment_status = unpaid`) | T1 |
| 17.12, 5.3 | Settings → Payments: the methods list with FR/EN instructions and merge tags | T1 |
| 17.13 | Settings → Invoices: prefix, starting number, logo, hotel details (from General), footer text FR/EN, tax label and rate | T1 |
| 5.9, 5.10, 5.11 | Record a payment (amount prefilled with the balance, method, reference, date and time, note), partial payments, status from the ledger | T2 |
| 5.13 | Payment history panel on the booking (who, when, method, reference; voids shown struck through with a reason) | T2 |
| 3.3, 3.4 | Payment method shown; the cleaned-up payment statuses | T2 |
| 5.4 | Deadline per booking, shown with a countdown on the booking and on the guest confirmation | T3 |
| 5.5, 5.6, 5.8 | Invoice: sequential number, PDF (ADR-005), download and print from the booking | T4 |
| 5.12 | Receipt PDF per payment; print and e-mail | T4 |
| 5.2, 5.7 | Confirmation e-mail + screen: instructions for the enabled methods, the exact CFA amount, the reference to quote, the deadline, and the invoice attached (the guest's language) | T5 |
| 5.14 | Overdue list (a filter on the dashboard + its own screen) with *remind* (e-mail) and *release*; optional auto-release by cron after X hours past the deadline (a setting, off by default) | T6 |

## Data

| Table | Key columns |
|---|---|
| `payments` | `id`, `booking_id`, `type` (payment/refund/adjustment/void), `amount` DECIMAL(12,2), `method`, `reference`, `note`, `voids_payment_id`, `received_at`, `received_at_gmt`, `recorded_by`, `receipt_no`, `created_at`. Index `(booking_id)`, `(received_at_gmt)`, `(method, received_at_gmt)` |
| `invoices` | `id`, `booking_id` (unique), `number` (unique), `version`, `status` (issued/revised/cancelled), `totals` JSON snapshot, `file_token`, `issued_at`, `sent_at`, timestamps |
| `invoice_versions` | `id`, `invoice_id`, `version`, `snapshot` JSON, `file_token`, `created_at` |

`bookings` gains (created in M03, maintained here): `paid_total`, `balance_due`,
`payment_status`, `payment_due_at`, `payment_due_at_gmt`, `on_hold`.

## API

| Method | Route | Access key |
|---|---|---|
| GET/POST | `bookings/{id}/payments` | `page.bookings` / `payments.record` |
| POST | `payments/{id}/void` | `payments.void` |
| POST | `bookings/{id}/payment-status` (on_hold toggle only) | `payments.change_status` |
| GET | `bookings/{id}/invoice` · `…/invoice/pdf` | `page.bookings` |
| POST | `bookings/{id}/invoice/send` · `…/invoice/regenerate` | `invoices.send` / `invoices.regenerate` |
| GET | `payments/{id}/receipt/pdf` · POST `…/receipt/send` | `page.bookings` / `invoices.send` |
| GET | `bookings?overdue=1` | `page.bookings` |
| POST | `bookings/{id}/release` | `bookings.cancel` |
| GET | `public/bookings/{token}` | public (tokenised confirmation page for the guest) |

## Emails (BaseEmail subclasses; templates overridable)

`GuestBookingReceived` (instructions + invoice), `GuestPaymentReceived` (receipt),
`GuestPaymentReminder`, `GuestBookingReleased`, `StaffPaymentRecorded` (optional). Each can be
switched on or off in Settings → E-mail.

## Activity log actions

`payments.record` (amount, method, reference), `payments.void` (reason), `payments.on_hold`,
`invoices.issue`, `invoices.revise`, `invoices.send`, `receipts.send`, `bookings.release_overdue`
(manual or cron, with the actor `system`).

## Tasks

- [ ] T1 [Free] Settings sections Payments + Invoices; the `payments`, `invoices` and `invoice_versions` tables; register the access keys
- [ ] T2 [Free] `PaymentService` (record, void, recalculate; table-driven tests), the record-payment dialog, and the payment history panel
- [ ] T3 [Free] Deadline computation + countdown UI + the `overdue` derived state
- [ ] T4 [Free] Invoice + receipt: sequential numbering, versioning, an HTML print view (`templates/documents/`), and the `rtbp_document_renderers` registry
- [ ] T5 [Free] Confirmation e-mail + public confirmation page with instructions (invoice link to the print view); guest language
- [ ] T6 [Free] Overdue follow-up: list, remind, manual release
- [ ] T7 [Pro] PDF renderer (dompdf, PHP-Scoper prefixed) registered in `rtbp_document_renderers`; PDFs attached to the confirmation and receipt e-mails
- [ ] T8 [Pro] Auto-release cron for overdue bookings (idempotent, logged as `system`) + its setting

## Acceptance

1. Create a booking of 25 000 CFA. Invoice `FAC-2026-000001` is e-mailed with the Wave
   instructions, the amount written as `25 000 CFA`, and the reference.
2. Record 10 000 by Wave (ref `W123`). The booking shows *Partially paid*, balance 15 000, and a
   receipt is issued.
3. Record 15 000 cash. It shows *Paid*. Void the cash payment with a reason. It returns to
   *Partially paid*, and the history shows both rows.
4. Edit a line's dates (the price changes). The invoice is re-issued as v2 under the same number.
5. Let a booking pass its deadline with auto-release on. The room is freed, the guest is e-mailed,
   and the log shows `system`.
6. Numbers never skip, even when two bookings are created at the same second (the concurrency test).

## Legacy reference

`legacy-reference.md` → Module 5 (approval and payment time-limit settings). There is no legacy
invoicing.

## Migration notes

Legacy orders with WooCommerce status `completed` import with a single `payments` row (method from
the order, note "imported"). Invoices are issued only for imported **future** bookings.

## Progress notes
