# R1 — Client review of 2026-11-02

| | |
|---|---|
| **Reviewer** | Residence TATA (role **Manager**, account Souhaliho Toure) |
| **Build reviewed** | demo site `https://radiustheme.net/themes/hotel-booking/hotel-dashboard/`, built from free `817c61c` and Pro `04fbbe8` (2026-11-01) |
| **Screenshots** | `~/Desktop/hotel-booking/review-images/` (7 WhatsApp photos of the **old** live dashboard, the look the client wants). Not committed: they show real guests. |
| **Old dashboard** | `http://fmp-client-residencetata.local/hotel-dashboard/` (read-only, see `rtbp-legacy-lookup`) |
| **Command** | `/next-review1` builds the next unchecked task below (`/next-review1 R1-T5` for one task, `/next-review1 all` for the rest) |

## What the client wrote

> I see file export but I do not see file imports back to the dashboard in case we lose some
> orders due to order restored to early date. Basically it is a back up file that we can restore
> back to the orders on the dashboard so once we download/export, we can upload and restore.
>
> The payroll is not complete with all the workers right and bonuses? Do you want me to send you
> the list of bonuses and how they are calculated?
>
> I do not see staff profiles, notes & history and employment and where I can edit them.
>
> I requested that notes, edits and time be added on everything on the dashboard.
>
> On the reports, there has to be a calendar for check date and time and checkout date and time
> so we can have a more accurate reports.
>
> I did not see the rooms report feature. I do not see my paycheck. I do not see my profile.
> All of those are important for staff. I do not see the food menu dashboard.
>
> On the dashboard, I want to be able to see order id, guest name and order time, room number,
> check in and check out, order status, status and most importantly ACTIONS where we are able to
> check clients in and out with a simple click, view, cancel booking.
>
> This I stand report feature is important on the dashboard and everywhere where there is a report.
>
> The search button should be placed right on the top of where order id, guest line is.
>
> Invoice button et edit button everywhere.
>
> Very important feature where we can log in and log out, edit profile, translate language and
> name of the account that is logged in at the top. This is at top right end side.
>
> I need Guest booking history so I know where and how many times that guest has been at the hotel
> with all times notes and edits buttons. So when a order id is clicked or anywhere the guest name
> appears there should be a history records.
>
> I want the dashboard to look exactly as it is right now and add the payment methods to the
> titles. And I want to know if a booking was taken at the desk or online and if at the desk, the
> name of the staff member who added the booking at the desk.

## What the demo already has (checked 2026-11-02 as the Manager)

The client missed these because they sit low in the sidebar (Staff, Access roles, Staff access,
Payroll are below Settings) or in the bottom-left account menu. T5 fixes the findability.

| Asked for | Already at |
|---|---|
| Staff profiles, notes, history, employment, editable | Staff → employee: Personal, Contact, Emergency contact, Employment, Pay, Notes, History, Documents, Account tabs |
| Rooms report | Reports → Rooms (available / rented / empty, empty rooms by floor, booked rooms) |
| My profile, my paycheck | Account menu bottom-left → My profile, My payroll (Payslips, Time off) |
| Desk or online + staff name | Booking detail header: "Made … by Souhaliho Toure · At the desk" |
| Payment method | Booking detail: "Paid by Orange Money" |
| Invoice | Booking detail: invoice number with Print and Download PDF |
| Notes and history | Booking detail: Notes / Guest notes / History. Guest: Stays / Notes / History |
| Cancel booking | Booking detail button, and the ⋯ menu on each booking-list row |

## Open questions (ask the client)

| # | Question | Blocks |
|---|---|---|
| Q1 | Language switch in the account menu conflicts with ADR-019 (English only, translate with Loco/WPML). Add a switch that sets the WordPress user locale, or leave it out? | T6 — **Answered 2026-11-02:** show the languages the live site (residencetata.com) uses → English and French, as on the old dashboard; one menu, top right. |
| Q2 | What is the "I stand report"? (Our guess: an *instant* report, the live summary cards.) | T13 — **2026-11-02:** user: infer it from the client's current dashboard on staging (staging-9508-…wpcomstaging.com/hotel-dashboard). |
| Q3 | The list of bonuses and how each is calculated (the client offered to send it). | T17 — **2026-11-02:** user: implement bonuses our own way; the client will comment. |
| Q4 | Which food-menu dashboard: URL or plugin, and who may open it? | T18 — **2026-11-02:** the tlp-food-menu front-end dashboard (`/front-end-order/`): a menu entry to it from the hotel dashboard, and a link back to the hotel dashboard from it. |
| Q5 | Booking restore: replace bookings from the file, or only re-create missing ones (match on order ID)? | T16 — **2026-11-02:** re-create missing bookings only (match on order ID); existing bookings untouched; preview first. |

## Tasks

Bugs and findability first (small, and they answer most of "I do not see…"), then the booking
list, guests, reports, records, and the items waiting on the client.

**Bugs found during the check**

- [x] R1-T1 [Free] Guest record shows "0 stays" and an empty Stays tab although the guest has bookings (G000005 has RT-2026-000005/6); every guest in the list shows 0 stays. — done 2026-11-02
- [x] R1-T2 [Free] Dashboard "Rooms by status" says *No rooms yet*, "Recent bookings" says *No bookings yet* and the setup checklist says rooms are missing, while 21 rooms and 19 bookings exist. — done 2026-11-02
- [x] R1-T3 [Pro] My payroll says "Your account has no employee file" for a user who has staff record E0001. Find out whether the staff↔user link is missing (data) or not read (code); make the empty state say how to link it. — done 2026-11-02
- [x] R1-T4 [Pro] A Manager without a PIN hits "You do not have a PIN yet. Ask a manager to set one" on protected actions. Point managers to My profile → Personal PIN (link in the dialog). — done 2026-11-02

**Findability and shell**

- [x] R1-T5 [Free+Pro] Sidebar like the old dashboard: Staff and Payroll up with the hotel sections; Rooms report and Room availability as their own entries (deep links to the Reports tabs); My paychecks and My profile in the sidebar for every staff member. — done 2026-11-02
- [x] R1-T6 [Free] Top-right account menu in the header: account name and role, Edit profile, Language (Q1), Log out. Keep the bottom-left menu or drop it — one menu only. — done 2026-11-02

- [x] R1-T6b [Free+Pro+Client] Complete French (fr_FR) translations for the three plugins, so Français in the account menu is fully French (asked 2026-11-02 after the switch showed mostly English: Free 223/2,408, Pro 0/1,425, TATA 0/225). Ship them in each zip. — done 2026-11-02

**Dashboard and booking list**

- [x] R1-T7 [Free] Booking list columns: order ID (with NEW badge), guest name + order time ("2 hours ago"), room + rate, floor + room number, check-in, check-out, order status (payment) with payment method, status, source (Desk · staff name / Online). — done 2026-11-02
- [x] R1-T8 [Free] One-click row actions: View, Check in, Check out, Cancel, Invoice, Edit. Cancel asks to confirm. — done 2026-11-02
- [x] R1-T9 [Free] Filter bar like the old one: Check-in / Creation date & time radio with From–Until (date + time) and Apply; search box directly above the ID/Guest header; quick filters (All, Awaiting approval, Check-in today, Check-out today) on the same row. — done 2026-11-02
- [x] R1-T10 [Free] Dashboard shows the booking list (T7–T9) under the summary cards (Awaiting approval, Check-in today, Check-out today, Available rooms), as on the old dashboard. — done 2026-11-02

**Guests**

- [x] R1-T11 [Free] Guest name and order ID are links everywhere (lists, booking detail, reports, payments) and open the guest's history: number of stays, every booking, notes, edit history. — done 2026-11-02

**Reports**

- [x] R1-T12 [Free] Report period filter with date **and time**, by check-in or by check-out (and creation date), on every report tab and its export. — done 2026-11-02
- [x] R1-T13 [Free] "I stand report" on the dashboard and every report — waiting on Q2. — done 2026-11-02 (to confirm with the client)

**Notes, edits and invoices everywhere**

- [x] R1-T14 [Free+Pro] Audit every record type (booking, line, guest, room, floor, rate plan, payment, staff, pay run, time off) for Notes + Edit + who/when history; add what is missing. List the result in Progress notes first. — done 2026-11-02
- [x] R1-T15 [Free] Invoice and Edit buttons on every booking surface (list row, booking detail, booking line, guest stays, reports' booked rooms). — done 2026-11-02

**Waiting on the client**

- [x] R1-T16 [Free] Booking backup import: upload an exported bookings file and restore missing orders (Q5). Update `docs/admin/backup-and-restore.md`, which says exports cannot be imported. — done 2026-11-02 (built in Free: the export is Free)
- [x] R1-T17 [Client] Payroll for all workers with the client's bonuses — waiting on Q3. — done 2026-11-02 (our design; client to comment)
- [x] R1-T18 [Client] Food menu dashboard entry in the sidebar ("Food Order and Inventory") — waiting on Q4. — done 2026-11-02

## Acceptance

Sign in on food-menu.local as a **Manager** (not an administrator) and, at desktop and 360 px:

1. Every page the client named is one click from the sidebar or the top-right menu.
2. Guest G000005-style records show their real stay count and stays; the dashboard shows rooms and recent bookings.
3. From the booking list, check a guest in and out, view, cancel, open the invoice and edit, without leaving the list.
4. Filter bookings and each report by check-in or check-out date and time.
5. Click a guest name anywhere and see their history.
6. Send the client the list of what changed and the answers still needed (Q1–Q5).

## Progress notes

- 2026-11-02 — Tracker created from the client's message and a walk-through of the demo as the Manager.
- 2026-11-02 — R1-T1: two causes. (1) `stays_count` / `last_stay_at` were never written by any code; now `GuestRepository::refreshStays()` recounts them (stay = confirmed, checked-in or checked-out booking) from `GuestStays` on `rtbp_booking_created|changed|status_changed`, once for every guest on upgrade (DBVERSION 1.0.21, option `rtbp_guest_stays_counted`), and at the end of the TATA legacy import (guarded). (2) The Stays query used `GROUP BY b.id` with non-grouped columns, which MariaDB with ONLY_FULL_GROUP_BY rejects (demo returned `[]` while booking 5 belonged to guest 5); rewritten with subqueries. Verified on food-menu (0 mismatches, guest 455 = 5 stays) and site-2 (2,800 guests, 0 mismatches, BALLO SARA 2 stays in the UI). The demo can only be confirmed after the next upload.
- 2026-11-02 — Found for R1-T2: nothing fills `recent_bookings`, `rooms_by_state` or the setup steps' `done` in the `rtbp_dashboard_summary` payload (`DashboardController::build()` defaults only).
- 2026-11-02 — R1-T2: new `Services/Dashboard/DashboardOverview` on `rtbp_dashboard_summary` fills `recent_bookings` (latest 6, now `{ rooms[], arrival, departure, status, payment_status }`; the panel shows status badges and `formatDateRange` instead of the never-sent `*_label` fields), `rooms_by_state` (reuses `ReportRepository::roomStates()`) and the setup `done` flags (room types, rooms, a rate price > 0, payment settings saved with an enabled method that has instructions). Checked on food-menu (24 rooms, 6 recent, 5/5 steps) and site-2 (38 rooms, 2 recent, 4/5: payments never saved).
- 2026-11-02 — R1-T3: data, not a read bug. On the demo E0001 is linked to sign-in `souhaliho.toure` (tourebts@gmail.com) while the client signs in as `service@residencetata…`; and nothing in the UI could give an existing sign-in a file (only the import used `EmployeeService::attach()`). User chose "link existing sign-in": `GET employees/accounts?q=` (`staff.create`) lists users whose role opens the dashboard and who have no file (administrators only to administrators; guest accounts never); `POST employees` with `user_id` calls `EmployeeService::linkAccount()` → `attach()` (keeps password, role, PIN). New staff step 2 has New sign-in / Existing sign-in. My payroll's 404 message now says how to get linked. Tested on food-menu with throwaway users (guest refused, admin refused for a manager) and on site-2 in Chrome (lists Omar, Residence TATA/j, techlabpro, wpfmenu). **Demo action:** link `service@` via Staff → Add an employee → Existing sign-in, or let the client sign in as `souhaliho.toure`.
- 2026-11-02 — R1-T4: `PasscodeService::can_set_own_pin()` (staff self-service on + `passcode` profile field allowed) and `no_pin_message()`; `GET access/passcode` returns both, `access/unlock`'s `pin_not_set` uses the message. The PIN dialog shows "Choose one in My profile → Personal PIN" with a **Set my PIN** button (→ `#/me/profile`); when the hotel does not let them set it, it still says to ask a manager. Checked on both Local sites (throwaway manager) and in Chrome on site-2 by firing the `rtbp.api.error` filter with a no-op retry (desktop and 579 px).
- 2026-11-02 — R1-T5: Free `NAV_GROUPS` order is now Overview, Front desk, **My account** (new, empty in free), Reports & data, Staff & access, Hotel setup, System. New route keys `navOnly` + `navSearch` (menu/search entries that link to another route with a query; the router, page header and phone tab bar skip them; `navTarget()` / `navKey()` in `src/admin/routes.js`); the sidebar marks them active on their query and the plain entry of the same path yields. Free adds "Rooms report" (`/reports?tab=rooms`) and "Room availability" (`?tab=availability`), both `page.reports_rooms`. Pro shows `/me/payroll` ("My paychecks", page title My payroll) and `/me/profile` in `me`. Staff & Payroll were already in Staff & access on current code (the demo build predates that). Checked in Chrome on site-2: order matches the old dashboard; active item correct on Sales/Rooms/Availability tabs and My paychecks; ⌘K finds "Rooms report"; phone drawer OK.
- 2026-11-02 — R1-T6: live site (legacy copy) runs `fr_FR` with `en_CA` installed and GTranslate; the old dashboard's top bar offered 🇬🇧 `en_US` / 🇫🇷 `fr_FR`, stored in user meta `hbfwc_dash_locale`. Free: `UserLanguage` (options from installed languages, filter `rtbp_dashboard_languages`; choice in core `locale` user meta, filter `rtbp_user_language`; `determine_locale` applies it on the dashboard page and `/radius-hotel-booking/v1/` REST for dashboard users; role line with `rtbp_user_role_label`), `POST me/language` (`AccountController`), `current_user.role|locale|languages` in the page data. The account menu moved from the sidebar foot to the top bar (`src/components/layout/UserMenu.jsx`), with a Language submenu that saves and reloads. TATA `Settings/LocalLanguages`: English + Français with flags, old `hbfwc_dash_locale` used until a new choice. ADR-019 amended. Verified with a throwaway manager on site-2 (options, legacy fallback, de_DE refused, REST/dashboard page → chosen locale, other pages untouched) and in Chrome (menu and Language list; not clicked, to leave the real account's language alone). **Not checked at phone width** (Chrome window would not resize). **Follow-up:** Pro and TATA ship no `fr_FR` catalogue yet, and Free's `fr_FR` is from M00 — French will be partly English until translated with Loco on the live site.
- 2026-11-02 — R1-T6b: fr_FR now complete — Free 2,417/2,417 (kept the 223 M00 strings), Pro 1,438/1,438, TATA 224/224; .pot regenerated in all three; translated in parallel batches against one glossary (`Passcode` → Code PIN, `Work shift` → Horaire de travail, `Express checkout` → Réservation express, `Blocked dates` → Dates bloquées), 0 placeholder mismatches; `.po/.mo/.l10n.php` (+ Free's JSON sidecars via `bin/i18n-build.sh`). Free's JS translation merge is now per text domain (`LoadAssets::script_translation_domains()`, filter `rtbp_script_translation_domains`); Pro and TATA register theirs (`Assets::translation_domain`), so their bundles read the .mo like free does. The three zip scripts now ship translations (only editor backups dropped). Verified on site-2 in Chrome (dashboard, Ma paie, Import de données in French). **Follow-ups:** native-speaker review before go-live (payroll terms); booking export headers are translated per user — check against R1-T16 (import must accept both languages or match by column position); the saved "Dernier import" report keeps the language it was written in.
- 2026-11-02 — R1-T7: `BookingLineList` rows gain `created_by` (users.display_name) and `payment_method` (latest non-voided payment, labelled by `PaymentSettings::method_label`). Bookings list columns: Booking (reference + NEW badge when made < 1 h ago + "At the desk · {staff}" / "Online" / "Imported" / "Calendar sync"), Guest (name, `formatTimeAgo` — new shared helper in `src/lib/format.js`, Intl.RelativeTimeFormat in the page language, date after 7 days — phone), Room, Check-in, Check-out (short `j M Y` + time, two lines), Payment (badge + method), Status. Fits 1,189 px (front-end dashboard at a 1,568 px window); narrower screens scroll inside the card with the actions column sticky. Checked in Chrome on food-menu (desk booking shows "At the desk · Md Habib", paid one "Cash at the desk"). NEW badge and phone width not seen (no booking < 1 h; window would not resize).
- 2026-11-02 — R1-T8: `DataTable` row actions take `inline: true` (icon button, label as tooltip/aria-label; tighter gap only when a row has inline icons). Bookings list rows: next step (approve / check in / check out), View, Invoice (`invoice_url` from `DocumentService::url()`, rows now `EXISTS` an invoice), Edit (`line_edit` added to row actions under `BookingLineService::EDITABLE`; opens `/bookings/{id}?edit={line}`, which Detail turns into the room editor once and clears) and Cancel (still the reason dialog); decline, other moves and Record payment stay in ⋯. Actions column 210 px; table 1,214 px in a 1,204 px card at a 1,583 px window (10 px of padding under the sticky column). Checked in Chrome: Edit → "Change room A1" editor, Cancel → "Cancel room A1?" dialog (closed), Invoice → FAC-2026-000339 in a new tab.
- 2026-11-02 — R1-T9: no API change (BookingLineList already took `Y-m-d H:i` and `mode=arrival|created`). New `Bookings/components/FilterBar.jsx`: radio Check-in / Creation date & time, From and Until as `DateTimePicker`s (midnight = whole day), Apply filter and Clear; the list only reloads on Apply. `DataTable` gains `toolbarEnd` (right of the search row); the bookings list puts its FilterTabs there, so search and quick filters share the row above the table; the old DateRangePicker is gone from this screen. Checked in Chrome: created 25 Oct 15:00 → 26 Oct gives exactly RT-…484/496/507/508/509; switching to Check-in + Apply keeps the dates (2 arrivals); Clear resets.
- 2026-11-02 — R1-T10: the Bookings screen body is now `export function BookingList( { withPageActions } )` in `src/modules/Bookings/index.jsx` (the default export wraps it); the dashboard renders `<BookingList withPageActions={ false } />` under the counters (filters in the dashboard URL, e.g. `#/?tab=leaving`). Below it: Today at the front desk (2/3) beside a column with the setup checklist and Rooms by status (`self-start`, as both panels are `h-full`). `RecentBookingsPanel.jsx` is removed (duplicate of the list); the summary API still returns `recent_bookings` (DashboardOverview) for add-ons. Checked in Chrome on food-menu: counters → filter card → list; Leaving today tab gives 2 rows on the dashboard; lower panels at their own height.
- 2026-11-02 — R1-T11: new `src/components/common/GuestLink.jsx` (link to `/guests/{id}` when `page.guests` is open, plain text otherwise; stops the row click). Guest id added to the bookings list rows (`guest.id`), the payments follow-up rows (`guest_id`, `BookingResource::summary`) and the Rooms report's booked rooms (`guest_id`); GuestLink used in all three. Booking detail: a visible "Stays and history (N stays)" link under the guest. Order IDs already open the booking everywhere. Today at the front desk rows stay booking links (the whole row is a link). The guest page already had Stays / Notes / History (with T1's stay count). Checked in Chrome: list name → Jewel Rana (5 stays); booking page link; payments names link to `#/guests/…`; report rows carry `guest_id` (wp eval, the report itself is cached).
- 2026-11-02 — R1-T12: `ReportRange` gains mode `departure` (`br.end_at_gmt`), optional times on `from`/`to` (`Y-m-d H:i`; end = that minute inclusive), `from_time`/`to_time` in `toArray()` (so the cache key includes them) and `column()`; ReportRepository sales/sold/collected/rooms use it (departure buckets by `br.end_at`, rooms = stays ending in the period); report titles and export names know departure. Pro RevenueReport uses `column()` behind `method_exists()` (needs free 1.0.21). Booking filter moved to `src/components/common/PeriodFilter.jsx` (`modes`, `withTime`, `clearable`, `idPrefix`); Reports show it under the tabs: all three modes, times except where a tab says `mode: false` (Pro occupancy/payroll/taxes → dates only, times stripped from the URL) or `time: false` (availability, which also limits modes to arrival/created). Card stacks below 2xl so the pickers keep room. Checked: wp eval of every query per mode and a timed range (bounds 09:00→06:01 GMT, reversed/unknown refused); Chrome: Rooms by check-out, Revenue by check-out from 8:00 AM, Occupancy dates-only, Availability 2 modes, booking list unchanged. Export with a time not run (it writes a library file); it uses the same `ReportRange`.
- 2026-11-02 — R1-T13 still waits on Q2 (user: ask the client first).
- 2026-11-02 — R1-T14 audit (code + food-menu activity log). The log already records who/when for every record type below; the gap is showing it, and notes.

  | Record | Notes | Edit | Who/when history on screen |
  |---|---|---|---|
  | Booking | ✅ booking notes + guest notes | ✅ guest, rooms, discount | ✅ History tab (Pro) |
  | Booked room (line) | via the booking | ✅ room editor | ✅ inside the booking's History |
  | Guest | ✅ | ✅ | ✅ History tab (Pro) |
  | Payment | via the booking | ⛔ by design: void / refund (ledger is append-only) | ✅ inside the booking's History |
  | Staff file | ✅ (Pro) | ✅ | ✅ History tab (Pro) |
  | Time off | — | approve / reject / cancel | ✅ in the staff History (logged on the employee) |
  | Room type | ❌ | ✅ | ❌ (logged as `room_type`) |
  | Room | ❌ | ✅ | ❌ (logged as `room`) |
  | Floor | ❌ | ✅ | ❌ (logged as `floor`) |
  | Rate plan | ❌ | ✅ | ❌ (logged as `rate_plan`) |
  | Pricing rule (Pro) | ❌ | ✅ | ❌ (logged as `pricing_rule`) |
  | Blocked dates | ❌ | ✅ | ❌ (logged as `block`) |
  | Pay run (Pro) | ❌ | draft lines | ❌ (logged as `payroll_run`) |
- 2026-11-02 — R1-T14 built (user chose "History + notes on rooms"): free note types `room` and `room_type` (NoteService; keys `rooms.note_add|edit|remove`, open by default; actions `rooms.note_*` in ActionCatalog). New `src/components/common/RecordHistory.jsx` ("Notes and history" / "History" button → right drawer: Notes tab when the type takes notes, plus panels from the new `rtbp.record.panels` filter), published as `rtbp.ui.RecordHistory`. Placed on room type header, room dialog, each floor row, rate plan sheet, block editor. Pro: `rtbp.record.panels` → History (ActivityTimeline) for any type; `subject_key()` maps room_type/rate_plan → page.rooms/rates, block → page.availability, pricing_rule → pricing.manage, payroll_run → page.payroll; RecordHistory on pricing rule editors (seasonal, adjusted) and the pay run header, guarded when free lacks it. Checked: wp eval add/list/remove a note on a room type and a room (activity logged, test notes deleted — their two log entries stay, the log is append-only); Chrome: room type drawer Notes + History, floor rows' History buttons, rate plan History. Pricing rule and pay run buttons not opened in Chrome.
- 2026-11-02 — R1-T15: list rows already had both (T8). Booking page: header Invoice button (`booking.invoice.urls.html`, new tab); each room shows a visible Edit button (it was "Change room, rate or dates" in ⋯). Guest Stays tab: Invoice + Edit links per stay (`GuestRepository::stays` EXISTS an invoice → `GuestService::stays().invoice_url`; Edit opens the booking). Rooms report booked rooms: inline Invoice + Edit row actions (`bookedRooms().invoice_url`; Edit opens the room editor while pending/confirmed, else the booking). Checked on food-menu (report cache flushed): header Invoice, Edit per room, 5/5 stays with both links, 25 report rows with both icons.
- 2026-11-02 — R1-T13: the client's current dashboard (staging, old system, French) has no "instant"/"I stand" item; the old bundle and .po have no such label either. The one feature it shows "on the dashboard and everywhere where there is a report" is the period filter card (Arrivée / Date et heure de création, From–Until with times, Appliquer le filtre) with the applied period written under it ("2 novembre 2026 00:00 — 23:59 · Arrivée"); the red button beside Apply only resets to today. Reading: "I stand report" = that filter. R1-T9 / R1-T12 built the card on the booking list (dashboard included) and every report; this task adds the applied-period line under `PeriodFilter` (shown once a period is applied; reports always have one). **Confirm with the client** in the R1 reply.
- 2026-11-02 — R1-T18 (user: two menus, one in each dashboard): no change in tlp-food-menu / food-menu-pro — Food Menu Pro already has `fmp_frontend_dashboard_nav_extra_items`. Free: menu-only routes take `href` (another page; plain link in the sidebar, the ⌘K palette opens it) and a new empty `apps` group "Other dashboards". TATA `Settings/DashboardLinks`: "Hotel Dashboard" in the Food Menu menu (for users with the hotel dashboard capability, free `Capabilities::VIEW_DASHBOARD` read through `defined()`), and `rtbp_tata.food_menu_url` (Food Menu Pro's `FnsPro::get_frontend_order_page()`, class/method guarded; only for administrators or users Food Menu gives a dashboard menu) → "Food Order and Inventory" under Other dashboards. Checked on food-menu: URLs for admin, empty when logged out; Chrome round trip hotel → /front-end-order/ (its menu shows Hotel Dashboard) → back.
- 2026-11-02 — Follow-up (user, with screenshots): dashboard counters restyled like the old dashboard. `StatCard` takes an optional `tone` (red / green / orange / blue / purple / amber): label + coloured outline icon on top, 32 px figure, tint of the tone fading to white, matching border (values read from the old dashboard's computed styles). Dashboard counters: Awaiting approval red (Clock3), Arriving today green (CalendarArrowUp), Leaving today orange (CalendarArrowDown), In house purple, Rooms free blue, Payment overdue amber; three per row below 1,600 px (was 1,800, user) so labels don't truncate. Report cards (no `tone`) unchanged.
- 2026-11-02 — R1-T17 (user: implement our way; the old payroll's "Bonus" was only an amount typed per run, which Pro already has). **Pro:** setting `payroll.bonusRules` (`Payroll/BonusRules`: `fixed` amount / `percent` of base pay, taxable or not, all or chosen employees, on/off; more kinds via `rtbp_pro_payroll_bonus_kinds` + `rtbp_pro_payroll_bonus_resolve`). `PayrollRunService::calculateLine` freezes the employee's resolved bonuses in the line; `LineCalculator` step 2b: taxable → earnings (`bonus_rule`, in gross before contributions/tax), non-taxable → net addition with the custom items (screens and payslips render both generically). Settings → Payroll → Bonuses (`BonusRulesSection.jsx`, shown with any calculator; kinds from `rtbpPro.bonusKinds`). "All workers": run payload `unscheduled` (active staff without a pay schedule) shown as a notice on the run with links to their Pay tab; Recalculate now also adds employees put on the schedule since the run was made (same terms as create). **TATA:** `Payroll/SeniorityBonus` kind `seniority_ci` (2 % after 2 full years, +1 %/year, max 25 %, years from the first contract start). Checked in memory (no writes): 200 000 base + Performance 10 % taxable + Transport 30 000 non-taxable → gross 220 000, net 185 151 → 233 666; chosen-employees rule skipped for others; site-2 seniority 3 % at 3 years, 2 % at 2; Settings UI on site-2 (row + kinds incl. seniority; discarded, nothing saved). Run notice and Recalculate join not exercised (would write pay runs).
- 2026-11-02 — R1-T16 (Q5: re-create missing only). Built in **Free**, not Pro: the bookings export it reads is Free's. Exports → **Restore bookings** (`Exports/RestorePanel.jsx`, access key `exports.restore`, locked; activity `exports.restore`): upload CSV → `POST exports/restore` mode `preview` (per-order *Will be restored / Already on the site / Cannot be restored* + reason) then `restore`. `Services/Export/BookingRestore` reads columns **by position** (headers are translated per user, see T6b follow-up; status/method/ID-type labels match this site's language or English), comma or semicolon, BOM stripped, ≤5 000 rows, 20 MB. Each order goes through `BookingService::create(…, 'import')`, so room overlap is still refused; new **import-mode `reference`** (`IMPORT_MODE` 3, `keptReference()`: kept when free, `Sequence::atLeast()` moves the `RT-YYYY` counter past it, so no number is burned); creation date and source put back; stayed rooms created checked in then checked out at their end time; cancelled/declined/no-show cancelled with the reason. Limits (shown as warnings): in a booking where some rooms stayed, a still-waiting room comes back checked in (one status per booking on the write path); an unknown payment method drops the paid amount; invoices are re-issued under new FAC numbers; payment rows, notes and documents are not in the export. Checked on food-menu with an exported booking (scoped cleanup of the test booking, rooms, payments, invoice and the 2030 counter): existing → skipped; same dates → refused "no longer available", nothing written; moved to 2030 → restored with its order ID, created date, source, prices and statuses; again → skipped; 2026 counter unchanged (515). Chrome: Preview shows 1 to restore · 1 already on the site (Restore not clicked). `docs/admin/backup-and-restore.md` (EN/FR) rewritten. Test side effects on the Local site: a few activity-log rows and gaps in the RT-2026 (≈512–515) and FAC-2026 (≈340–343) counters from runs before the reference fix.
- 2026-11-02 — Follow-up (user: we keep every customer record): Pro's **Archive and remove** dropped — `ArchivePanel.jsx`, `Export/ArchiveController` (`POST exports/archive`), `Export/ArchiveService` and the `exports.archive_remove` access key removed; the activity label stays so earlier entries read. A period file is a normal export. Free keeps the admin-only delete on library files from past archive runs (still the only copy of what they removed). `docs/admin/backup-and-restore.md` sections removed (EN/FR). Decision 2026-10-01 (copy legacy *Booking Backup* delete) is superseded.
- 2026-11-02 — R1-T16 follow-ups (user): **batch restore** for 10 000+ bookings — `start()` keeps a *grouped copy* of the upload as a protected file (`groupFile()`: an export lists booked rooms by line id, so a booking's rooms can be far apart; rows are regrouped per order from 12-byte offsets), a transient session keeps the byte offset, `runBatch()` restores ≤250 orders / ≤15 s per request, `cancel()` logs and deletes; rows streamed (`orders()` generator), so memory stays flat; caps 200 000 rows / 50 MB (host limit reported). Panel: progress bar, Stop after this batch, 3 tries then Carry on, leave-page warning, first 300 orders listed (invalid first), first 200 failures kept. **File library → ⋯ → Restore** previews a bookings CSV from the library (`export_id`). Exports: **Any date** preset (ReportRange::allDates(), not held to MAX_DAYS), × resets to this month, *Next 7 days* hidden there. Checked (rolled-back transactions on food-menu): 12 000 two-room bookings — preview 0.4 s / 12 MB, 49 batches ≤ 5.7 s, stop + resume skips what is back; 12 000 shuffled rows (semicolons, BOM) all restored with both rooms; 50 000-booking preview 1.5 s / 12 MB (full run stopped early). Chrome: library Restore preview (1 to restore — RT-2026-000452 — · 10 on the site); 1 001-order file restored with the progress bar (test booking deleted).
