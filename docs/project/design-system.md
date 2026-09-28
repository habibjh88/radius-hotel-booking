# Design system: the staff dashboard and the guest booking flow

The UI is built on shadcn/ui (Radix + Tailwind), scoped under `.rtbp-root`. This document fixes
the **decisions** so that 18 modules built across several weeks look like one product.

---

## 1. Principles

1. **Reception works fast, standing up, often on a phone.** The most frequent actions (approve,
   check in, check out, record a payment) take one tap from the list. They never need the
   detail screen.
2. **Show state, not just data.** Every booking, room and payment carries a coloured status badge
   from one shared palette (§4). A receptionist should read the day from colour alone.
3. **Never hide what exists.** An unavailable rate or room is shown greyed out, with the reason
   as a badge. It never simply disappears (feature 2.4).
4. **The price explains itself.** Wherever a computed price is shown, a popover lists the steps
   that produced it (feature 7.16).
5. **The same component in both places.** The front desk and the public site render one
   `BookingFlow`, in two modes. The code never forks.
6. **French first.** Layouts are sized for French strings, which run about 20% longer. No
   fixed-width buttons.

## 2. App shell

```
┌───────────────────────────────────────────────────────────────────────┐
│ ☰  Residence TATA        ⌘K Search bookings, guests, rooms   🔔 3  👤 │  top bar
├────────────┬──────────────────────────────────────────────────────────┤
│ Front desk │  Page title                         [Primary action]     │
│  Dashboard │  Breadcrumb / sub-tabs                                   │
│  New       │ ────────────────────────────────────────────────────────  │
│  Bookings  │                                                          │
│ Inventory  │  content                                                 │
│  Rooms     │                                                          │
│  Rates     │                                                          │
│  Calendar  │                                                          │
│ Guests     │                                                          │
│ Reports ▸  │                                                          │
│ Staff ▸    │                                                          │
│ System ▸   │                                                          │
└────────────┴──────────────────────────────────────────────────────────┘
```

- **Sidebar groups:** Front desk · Inventory · Guests · Reports · Staff · System. The sidebar is
  built from `src/admin/routes.js`, and each route declares its `group`, `icon`, `accessKey` and
  `label`. A route whose page access level is *Locked* for the current user is not shown.
- **Below the `md` breakpoint** the sidebar becomes a Sheet opened from ☰, and the four
  front-desk items move to a bottom tab bar.
- **Command palette (⌘K / Ctrl+K)** searches bookings by reference, guests by name or phone,
  and rooms by number. It also works as quick navigation.
- **The bell** counts bookings awaiting approval and plays the new-booking sound (feature 1.12,
  Module 17).
- **Mount points.** The same staff app mounts in wp-admin (`admin.php?page=radius-hotel-booking`)
  and on a front-end page through the `[rtbp_dashboard]` shortcode, so it can live at the client's
  existing `/hotel-dashboard` address. See `decisions.md`, ADR-003.

## 3. Tokens

Colours are CSS variables in `src/index.css`, following the shadcn convention. Use Tailwind
semantic classes such as `bg-primary`, `text-muted-foreground` and `border-border`. **Never use
a raw hex value in a component.**

| Token | Use |
|---|---|
| `--primary` | The one main action per screen, links, the selected state |
| `--destructive` | Cancel booking, decline, delete, ban guest |
| `--muted` | Table headers, disabled or unavailable cards |
| `--success` / `--warning` / `--info` | **To add in M00.** Status badges and alerts |
| `--radius` | 0.5rem. Cards use `rounded-lg`, inputs `rounded-md` |

**Type scale:** page title `text-xl font-semibold`; section title `text-base font-semibold`;
body `text-sm`; table cells `text-sm`; meta and help text `text-xs text-muted-foreground`.
Numbers in tables and money use `tabular-nums`.

**Spacing:** pages use `p-4 md:p-6`, cards `p-4`, and stacks `space-y-4`, or `space-y-6` between
page sections.

## 4. Status palette

This is one map in `src/lib/status.js`, used by every badge, calendar cell and chart legend.

| Domain | Value | Colour | Label (EN / FR) |
|---|---|---|---|
| Stay | `pending` | amber | Awaiting approval / En attente |
| | `confirmed` | blue | Confirmed / Confirmée |
| | `checked_in` | green | Checked in / Arrivé |
| | `checked_out` | slate | Checked out / Parti |
| | `cancelled` | red (outline) | Cancelled / Annulée |
| | `declined` | red (outline) | Declined / Refusée |
| | `no_show` | red (outline) | No-show / Non présenté |
| Payment | `unpaid` | amber | Unpaid / Non payé |
| | `partially_paid` | orange | Partially paid / Partiellement payé |
| | `paid` | green | Paid / Payé |
| | `refunded` | slate | Refunded / Remboursé |
| | `overdue` (derived) | red | Payment overdue / Paiement en retard |
| Room | `available` | green | Available / Disponible |
| | `maintenance` | amber | Maintenance |
| | `out_of_service` | slate | Out of service / Hors service |
| Availability | `free` | green | |
| | `booked` | red | |
| | `held` | amber (striped) | Held / Réservée temporairement |
| | `blocked` | slate (hatched) | Blocked / Bloquée |
| | `closed` | slate | Closed / Fermée |

Colour is never the only signal. Every badge also carries its text label.

## 5. Components

The components in `src/components/ui/` are the shadcn primitives. Build these composites once,
in `src/components/`, and never re-implement them inside a module. The free runtime exposes them
as `window.rtbp.ui` so Pro and the client add-on reuse them. Composites built in Pro modules
(`PasscodeDialog`, `ActivityTimeline`) live in the Pro repo and are injected through the JS filters
(ADR-015):

| Component | Built in | Purpose |
|---|---|---|
| `PageHeader` | M00 | Title, description, primary action, secondary actions menu |
| `DataTable` | M00 | Server-driven table (built in-house, not TanStack: every list is paged, sorted and filtered on the server, so an in-browser engine would add ~50 KB for nothing): toolbar (debounced search + filters), sortable headers, pager, row actions (primary button + ⋯ menu), loading/empty/error states, and cards below `md` |
| `FilterTabs` | M00 | Quick-filter tabs with counts (All · Awaiting · Arriving · Leaving) |
| `DateRangePicker` | M00 | Range picker with presets (Today, Tomorrow, This week, This month) and an arrival/creation mode switch |
| `StatusBadge` | M00 | Reads §4 |
| `Money` / `DateTime` | M00 | Formatted, locale-aware display |
| `EmptyState` | M00 | Icon, one sentence, one action |
| `ConfirmDialog` | M00 | Destructive confirmations, with an optional required-reason field |
| `PasscodeDialog` | M13 | The PIN prompt for passcode-protected actions. Mounted once, triggered by the API client on a `passcode_required` response |
| `FormSection` / `Field` | M00 | Label, control, help text and error message, wired to `error.errors` from the API client |
| `NotesPanel` | M03 | The author-stamped notes thread, reused by bookings, guests and employees |
| `ActivityTimeline` | M14 | Before/after history for any record |
| `SettingsSection` | M17 | The card layout every settings tab uses |
| `RoomPicker` | M02 | Rooms grouped by floor, as a grid of tiles showing state and reason |
| `RateCard` | M02 | A rate plan with its window, price and availability, or its unavailable reason |
| `PriceBreakdown` | M07 | The pricing pipeline popover |
| `CalendarGrid` | M08 | Dates × rate plans, with sticky headers, virtualised horizontally |

**Library additions** (approved): `@tanstack/react-query` for server state, caching and
invalidation after mutations; `react-hook-form` + `zod` for forms; `sonner` for toasts;
`recharts` for reports; `react-day-picker` for the date pickers (shadcn Calendar). Add each one in
the module that first needs it, not before.

## 6. Screen patterns

**List screen** (bookings, guests, employees, activity log): `PageHeader` → `FilterTabs` →
`DataTable` toolbar → table. The URL hash keeps the filter state, so a view can be bookmarked and
sent to a colleague. Row actions show the valid next transitions only, with the primary one as a
button and the rest in a `⋯` menu.

**Detail screen** (booking, guest, employee): a header card (reference, status badges, primary
actions) → a two-column layout on desktop (main: lines, money, payments; side: guest, notes,
activity) → a single column on mobile, where the side panels become tabs.

**Form**: a Dialog for four fields or fewer, a Sheet for medium forms, and a full page for the
booking flow. Save stays disabled until the form is dirty and valid. Server field errors appear
under their fields.

**Settings**: vertical tabs on desktop, a Select on mobile. Each tab is a stack of
`SettingsSection` cards. One Save per tab, and a sticky bar appears when a tab has unsaved changes.

**Booking flow** (front desk and public):

```
[ Arrival date | Departure date | Guests ]  ← sticky search bar
─────────────────────────────────────────
Standard Room                     7 rooms free
 ┌────────────┐ ┌────────────┐ ┌────────────┐
 │ Half Day   │ │ Rest Time  │ │ Overnight  │  ← RateCards (horizontal scroll on phone)
 │ 08:30–17:00│ │ 17:00–20:00│ │ 20:00→08:00│
 │ 10 000 CFA │ │  6 000 CFA │ │ Unavailable│
 │  [Select]  │ │  [Select]  │ │  fully booked
 └────────────┘ └────────────┘ └────────────┘
Room VIP                          2 rooms free
 …
─────────────────────────────────────────
Choose a room:  Floor 1  [A1] [A2] [A3▒ maintenance] [A4▒ booked]
                Floor 2  [B1] [B2] …
─────────────────────────────────────────
Guest: [search name / phone / email ▾]   or  + New guest
─────────────────────────────────────────
Summary  (lines · total · payment state)     [Confirm booking]
```

On a phone, the steps become a stepper: Dates → Rate → Room → Guest → Review.

## 7. Feedback and motion

- Toasts (`sonner`) confirm mutations: "Booking RT-000841 approved".
- Optimistic updates for status transitions, rolled back with an error toast if the call fails.
- Skeletons, not spinners, for any content area. A spinner appears only inside buttons.
- Motion: 150 ms colour and opacity transitions only. No layout animation in tables.
- **New-booking alert:** a toast with a *View* action, a sound (the one uploaded in Settings, or
  the built-in chime), and a bell-count increment. Polling runs every 30 s (see ADR-006), and the
  bell pauses when the browser tab is hidden.

## 8. Accessibility

- Every interactive element is reachable and operable by keyboard. Radix provides most of this;
  do not break it with a `div onClick`.
- Visible focus rings (`ring-2 ring-ring`).
- Text contrast of at least 4.5:1 in badges, which the §4 palette meets.
- Icons without text carry an `aria-label`.
- Tables use real `<table>` markup. Mobile cards use `<dl>`.

## 9. Printed and PDF documents

Invoices, receipts and payslips share one HTML template family in `templates/documents/`,
rendered to PDF on the server (see ADR-005). A4, the hotel logo top left, the document number
top right, `Money::format()` for every amount, and French or English according to the guest's
or employee's language.
