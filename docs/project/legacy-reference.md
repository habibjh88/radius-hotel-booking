# Legacy system reference

How the client's current system implements each of the 18 modules: where the code is, where the
data lives, which behaviour to keep, and which bugs to avoid. Read the module's section before you
build it (the `rtbp-legacy-lookup` skill). **Copy the behaviour, not the code.**

Surveyed 2026-09-28 from the Local copy of the live site.

- **Root:** `/Users/habib/Local Sites/fmp-client-residencetata/app/public/wp-content/plugins/`
- **CORE** = `hotel-booking-for-woocommerce/includes`
- **PRO** = `hbfwc-pro-addon/includes`
- **RTA** = `hbfwc-residencetata-addon/src`
- **REST:** namespace `hbfwc/v1`, and the dashboard routes are under `/dashboard/...`
- **Running site:** `http://fmp-client-residencetata.local/hotel-dashboard`

**How the front end is built.** The live UI is a React SPA served at `/hotel-dashboard` (alias
`/h-dashboard`) by `RTA/dashboard/class-react-app.php:80 maybe_render_app()`. It injects
`window.HBFWC_BOOT` at :114.

- **Only the built bundle is on disk** (`client/dist/assets/main.js`). The TSX source is not
  here, so screen layouts have to be read from the running site.
- A classic PHP dashboard still exists alongside it (`RTA/dashboard/class-forms.php`,
  `templates/*`), and REST proxies payroll into it.
- `food-menu-pro/.../FrontendDashboardController.php:438` only adds a sidebar link.
- Every REST route calls `check_dashboard_access()` (`RTA/rest/dashboard/class-controller.php:48`),
  which requires an administrator or staff user who is not banned (`_hbfwc_staff_banned`).

> ### ⚠ Security findings in the live system (tell the client)
> 1. **Account takeover:** `auto_login_signup_user` (`RTA/woocommerce/class-booking-actions.php:64`)
>    logs the visitor in as *any* existing user whose username equals the phone number typed at
>    checkout.
> 2. **Public exposure of personal data:** `/customer/lookup?phone=` is public (`__return_true`) and
>    returns full billing details, **including the ID document number**.
> 3. **Public iCal feed:** `/room/{id}/calendar.ics` needs no token and uses guessable IDs.
> 4. **Plaintext PINs:** staff PINs are stored in plaintext; the fallback PIN is sent to the browser,
>    and `verify-passcode` has no brute-force limit.
> 5. **Unguarded booking creation:** the REST endpoint that creates a booking has no permission
>    gate (the `add-booking` check exists only in the client).
> 6. **Audit log can be tampered with:** deleting log backups and purging the log are gated only by
>    the page permission.

---

## 1 Front desk dashboard
- **Code:**
  - `rest/dashboard/class-stats-controller.php:28 get_stats` returns pending, checkin_today,
    checkout_today and available_rooms.
  - `class-timeline-controller.php:73 get_timeline` (with `ACTION_MAP`).
  - `class-booking-controller.php:232 get_bookings`, `:870 get_new_bookings` (polling),
    `:929 stream_events` (SSE).
  - The counts come from `RTA/utils/class-db.php:466-541, 597`.
- **Storage:** tables `{prefix}hbfwc_restata_booking`, `hbfwc_restata_rooms` and
  `hbfwc_restata_staff_log`; options `hbfwc_rta_notify_new_booking` and
  `hbfwc_rta_notify_sound_id`.
- **Keep:**
  - List filter values are `''|pending|check-in-today|check-out-today`.
  - `per_page` defaults to 20, maximum 100.
  - `date_mode` is `checkin|created`; the old value `stay` maps to `checkin`.
  - The date window is one continuous span (`datetime_from`/`datetime_until`, `Y-m-d H:i`), not
    an hour range repeated each day.
  - Search strips a leading `#`. A digits-only term matches the order ID or room number; any other
    term matches the billing name, room title or room number.
  - "Today's overview" is a timeline that merges *scheduled* events (check-in and check-out times
    on bookings) with *actual* events from the log. Each event is `todo|done|overdue`, and on a tie
    a scheduled event sorts before an actual one.
  - New-booking push uses SSE: `retry: 3000`, a 25 s lifetime and a 3 s poll. We use polling
    instead (ADR-006).
- **Don't copy:**
  - Check-in-today counts start at `00:00:01`, so midnight check-ins are skipped.
  - The available-rooms count uses `DATE()` comparisons.
  - Status changes are logged as `approve_booking`, so the timeline shows them as approvals.

## 2 Taking a booking at the front desk
- **Code:**
  - `class-booking-controller.php:1021 create_booking` (`POST /dashboard/bookings`),
    `:1180 add_booking_item`, `:1298 remove_booking_item`.
  - Customer creation and sync: `class-controller.php:685 create_new_customer`,
    `:660 sync_customer_data`.
  - Classic form: `templates/forms/add-booking.php`, `new-booking.php`, `parts/rooms.php`,
    `parts/room-number.php`.
- **Storage:**
  - The booking is a WooCommerce order with `payment_method='cod'` ("Pay at Hotel").
  - Item meta: `rate_id`, `booking_date` (JSON `{check_in, check_out}` as timestamps),
    `booking_guests` (JSON `{adults}`), `__room_number_id`, `restata_room_number`.
  - Order meta: `id_type`, `id_number`.
- **Form fields:** `customer_id | first_name, last_name, user_phone, user_email, id_type, id_number,
  product_id, rate_id, guests, checkin_date, checkout_date, checkin_time, room_number_id,
  payment_status (pending|completed)`.
- **ID types** (`woocommerce/class-booking-actions.php:470`): `cni, passport, consular,
  cni_receipt, certificate, driver_license`.
- **Keep:**
  - Stay times come from the rate plan's `start_time`/`end_time`. If the end is earlier than the
    start, check-out moves to the next day.
  - 24-hour rates use `checkin_time` (default `08:00`).
  - A 409 response when the room is taken (`room_number_is_occupied`).
  - Bookings created at the desk are inserted as `confirmed`.
  - A QR code is generated per order (`_booking_qr_url`).
- **Don't copy:**
  - Creation is not gated on the server.
  - 24-hour rates are detected with `strpos($rate->name,'24')`.
  - `payment_status` is not validated.
  - Order meta writes are not HPOS-safe.
  - Guests without an e-mail get a fake `{phone}@residencetata.com` address. We keep the idea
    but flag the address as a placeholder (M09).

## 3 Booking record
- **Code:**
  - `class-booking-controller.php:411 get_booking_detail`, `:591 booking_action`,
    `:823 update_order_status`, `:760 update_or_add_order_note`, `:718 remove_order_note`,
    `:1341 update_booking_item`.
  - `class-customer-controller.php:221 update_booking_customer`.
  - `class-booking-actions.php:202 sync_booking_table_status`.
- **Storage:**
  - Booking row status values: `pending|confirmed|checked_in|checked_out|cancelled|trashed`.
  - Notes are in order meta `hbfwc_order_notes`: an array keyed by `time()`, each entry
    `{id,date,note,addedBy,createdAt,updatedAt}`.
- **Keep:**
  - The actions are `approve|decline|cancel|mark-paid|check-in|check-out`.
  - Check-in marks the booking paid and assigns the room number.
  - WooCommerce order status maps onto the booking: `completed` → confirmed;
    `cancelled|refunded|failed` → cancelled.
  - Payment "completed" is shown as "Paid".
- **Don't copy:**
  - approve, cancel and mark-paid share one permission key (`modify-order-status`).
  - Note IDs from `time()` can collide.
  - Editing an item recalculates the price and collapses multiple rooms into one. Our price
    snapshot rule prevents this.

## 4 Guest booking on the website
- **Code:**
  - Shortcodes `hbfwc_availability` and `hbfwc_search_form` (`CORE/class-shortcodes.php:22`).
  - `CORE/wc/class-form-handler.php`, `class-wc-hooks.php`, product type `accommodation`.
  - `RTA/woocommerce/class-hooks.php:42 reset_and_attach`.
  - Guest room picker: `class-room-selection.php`, `templates/misc/room-selector.php`.
  - Phone recognition: `class-phone-recognition.php` (`/customer/lookup`, `/customer/recognize`).
  - PRO: `hbfwc_pro_ajax_get_available_room_numbers:2024`,
    `PRO/features/approval/class-booking-approval.php`.
- **Storage:**
  - Product meta: `_hbfwc_rate_plans`, `_allotment`, `_min_stay(s)`, `_max_adults`,
    `_max_children`, `_room_size`, `_no_of_beds`, `_default_rate_prices`, `_hbfwc_room_numbers`.
  - Taxonomies `rate_plan` and `amenities`.
  - Cookie `rsta_recognized_phone`.
  - User meta: `rsta_linked_phone`, `billing_id_type`, `billing_id_number`, `_hbfwc_banned`.
- **Keep:**
  - The ID type and number are required.
  - Web bookings start as `pending`.
  - Expired holds are dropped.
  - Default `?adults=2`.
  - Banned guests are blocked.
  - `booking_window` (-1 = unlimited) and `sameday_cutoff`.
- **Don't copy:**
  - The public PII lookup and the account-takeover auto-login (see Security findings).
  - `kill_food_on_trash` strips Food Menu Pro callbacks.

## 5 Manual payment and invoice
- **Code:**
  - `mark-paid` (module 3) and the walk-in `payment_status`.
  - PRO deposit: `PRO/features/deposit/*` (custom order type `hbfwc_deposits`,
    `class-payment-reminder.php`).
  - PRO security bond: `PRO/features/security-bond/*`.
- **Storage:**
  - Deposit meta: `_hbfwc_payment_stage (deposit|balance)`, `_hbfwc_deposit_mode (full|partial)`,
    `_hbfwc_deposit_percent`, `_hbfwc_due_today`, `_hbfwc_remaining_due`.
  - Security bond meta: `_security_bond_amount`, `_security_bond_status`.
- **Keep:**
  - The approval settings `manual_approval`, `payment_time_limit`, `approval_time_limit` and
    `approval_expired_action`. These feed the payment deadline (5.4) and the auto-release (5.14).
- **Don't copy:** the sales report counts unpaid "Pay at Hotel" (`processing`) orders as sales.
- **There is no invoice numbering today.** Everything in Module 5 is new.

## 6 Rooms, floors and inventory
- **Code:**
  - `class-rooms-controller.php:84 get_rooms`, `:105 get_room_detail`, `:134 save_room_numbers`.
  - Classic screen: `templates/content/room/setup.php`.
- **Storage:** table `hbfwc_restata_rooms` (`room_id` = product, `number`, `floor`, `status`),
  UNIQUE(`room_id, number, floor`).
- **Keep:**
  - Room status `available|maintenance|closed`.
  - Alphanumeric room numbers (`A10`).
  - Floor labels "Ground Floor", "Floor #1".."#4".
  - Room-type readiness `ready|no_rooms`.
- **Don't copy:**
  - Floors are clamped to 0–4.
  - Deleting a room does not check bookings and is not logged.
  - **Two parallel room-number systems** exist: PRO's `_hbfwc_room_numbers` plus
    `hbfwc_blocked_dates.room_number`, and RTA's rooms table. We have exactly one: `rooms`.

## 7 Rate plans and pricing
- **Code:**
  - `CORE/admin/class-admin-rate-plans.php` (default rate option `hbfwc_default_rate_id`, term meta
    `features`).
  - `RTA/woocommerce/class-rate-plans.php:177-285` (term meta `schedule_type fixed|flex`,
    `start_time`, `end_time`, 30-minute steps).
  - `RTA/woocommerce/class-hooks.php:382 rate_price_for_cart`.
- **Pricing chain** (`PRO/hbfwc-pro-hooks.php` on `hbfwc_pro_get_rate_price`):
  1. seasonal template, priority 5 (`PRO/hbfwc-pro-functions.php:3044`)
  2. occupancy, priority 10 (`:3124`)
  3. booking window (early and late), priority 20 (`:3221`)
- **Rule precedence:**
  - Seasonal: higher `priority` first (default 10), then the more specific scope, then the newer
    rule.
  - Occupancy: the most specific scope, then the highest threshold reached.
  - Booking window: specificity room type 2 / rate 1. Among early rules the highest threshold
    wins; among late rules the lowest wins.
- **Storage:**
  - `hbfwc_rateplans` (price per date).
  - `hbfwc_seasonal_templates` (`adjustment_mode increase|decrease|set`,
    `adjustment_type percent|fixed`).
  - `hbfwc_seasonal_rules` (`post_ids`/`rate_ids` as CSV where 0 means all, `start_date`,
    `end_date`, `priority` default 10).
  - `hbfwc_occupancy_rules` (`threshold` default 50).
  - `hbfwc_booking_window_rules` (`rule_type early|late`, `days_threshold` default 30).
- **Live data:** 7 `rate_plan` terms (ids 43, 90–95). Their exact windows are in `booking-engine.md` §2.
- **Keep:**
  - Prices never go below 0.
  - A fixed-window (not 24-hour) rate charges one price per stay block; other rates add up a
    price per night.
- **Don't copy:**
  - Rules are skipped when `is_admin() && !wp_doing_ajax()`.
  - Occupancy reads `hbfwc_availability.qty`, but the stock hooks were removed, **so occupancy
    pricing never triggers today**.

## 8 Availability calendar and rules
- **Code:**
  - `CORE/admin/class-admin-availability.php` + `assets/client/admin/js/availability.js`;
    `PRO/admin/class-admin-availability.php`.
  - `PRO/utils/class-blocked-db.php` (`insert_blocked_range :396`,
    `recalculate_date_availability :757`, `remove_source_blocks :989`).
  - iCal sync: `class-calendar-sync-handler.php`, `class-auto-sync-manager.php`.
  - iCal export: `hbfwc_pro_generate_room_calendar :1172`.
  - **SSRF guard `hbfwc_pro_is_safe_remote_ical_url :785` — keep this idea.**
- **Storage:**
  - `hbfwc_availability` (`post_id, booking_date, qty, status`).
  - `hbfwc_blocked_dates` (`room_number, source, from_date, until_date`).
  - Product meta: `_hbfwc_sync_calendar`, `_hbfwc_auto_cal_sync`, `_hbfwc_cal_sync_interval`.
- **Keep:**
  - Block sources are internal, external calendar and order.
  - A block can target one room number.
- **Don't copy:** the public, unguessable-free iCal feed.

## 9 Guest records
- **Code:**
  - `class-customer-controller.php:278 search_customers`, `:404 get_customer_detail`,
    `:468 update_customer`, `:515 toggle_ban`, notes at `:135/165/188`.
  - `dashboard/services/class-user-notes-service.php`.
- **Storage:**
  - Guests are WordPress users with the role `customer`.
  - User meta `billing_*`, `billing_id_type`, `billing_id_number`, `_hbfwc_banned`.
  - Notes go in `hbfwc_payroll_notes`, which is shared with staff notes (column `user_role`).
- **Keep:**
  - **Note types `general|caution|warning`** (`Constants::USER_NOTE_TYPES`). Add a `type` column
    to `notes`.
  - Digits-only phone matching in search.
- **Don't copy:**
  - Search pagination is wrong on page 2 and later.
  - Customer edits are logged as `approve_booking`.

## 10 Reports
- **Code:**
  - `class-analytics-controller.php:113 get_sales`, `:347 get_rooms`, `:252 chart_mode`.
  - `class-availability-controller.php:72` (the room grid).
  - Classic: `utils/class-reports.php`, `templates/analytics/*`.
- **Keep:**
  - **Chart grouping:** a range of 1 day or less groups by payment method; up to 62 days groups by
    day; anything longer groups by month; empty buckets are filled with zero.
  - Sales = the item subtotal, with tax shown separately.
  - Failed = `cancelled|failed|refunded`.
  - Room cards: `available|closed|maintenance|rented|empty`.
  - The availability grid is grouped room type → floor → room, and occupancy means overlap.
- **Don't copy:**
  - "Rented" counts only check-ins inside the window (not overlap), so rooms occupied by
    multi-night stays show as empty.
  - One order load per row (N+1).

## 11 Export and archive (booking backup)
- **Code:**
  - `utils/class-booking-backup.php` (`export :100`, `record_for_row :186`, XLSX writer `:375`,
    `delete_orders :521`).
  - `rest/dashboard/class-booking-backup-controller.php:116/171/194`.
- **Storage:** folder `wp-content/hbfwc-booking-backups-{suffix}` with `.htaccess` + `index.php`,
  streamed through REST.
- **Keep:**
  - CSV (with a UTF-8 BOM) or XLSX.
  - `delete_after` runs only after the file has been written.
  - **The column set is at `:299-329`.** Match it so the client's spreadsheets keep working.
- **Don't copy:**
  - A silent `LIMIT 20000`.
  - Broken numeric typing.
  - No protection against CSV formula injection.
  - Deletions are not logged.

## 12 Staff records
- **Code:**
  - `class-employees-controller.php`: `:257` list, `:211` detail, `:285` profile fields,
    `:386` create, `:447` update, `:652` save contract, `:714` contract file, `:753-796` notes.
  - `dashboard/class-staff.php`, `class-payroll-contract.php`.
- **Storage:**
  - Role `staff`.
  - User meta: `user_phone`, `user_passcode`, `staff_dob`, `staff_address`,
    `staff_marital_status`, `staff_num_kids`, `staff_em_contact` (`{full_name,address,phone,relasi}`),
    `_hbfwc_staff_banned`, `hbfwc_profile_image`, `hbfwc_active_shift`.
  - Per-year user meta: `staff_pto_{Y}`, `staff_uto_{Y}`, `staff_sick_{Y}`, `staff_vb_{Y}`,
    `staff_vp_{Y}`, `payroll_ytd_{Y}`.
  - User meta `staff_deductions`, `staff_additional_pays`.
  - Tables `hbfwc_payroll_contracts`, `hbfwc_payroll_notes`.
- **Enums:**
  - Marital status: `single|married|divorced|separated|widow|widower`.
  - Relation: `father|mother|spouse|brother|sister|friend|relative|guardian|other`.
  - Job type: `full-time|part-time|contract|internship|other`.
  - Pay: `hourly|salary`, paid `weekly|monthly|yearly`.
- **Don't copy:**
  - The plaintext passcode is returned in the detail payload.
  - Inactive staff are the same thing as the ban flag.
  - `remove-staff` is never enforced.

## 13 Staff permissions
- **Code:**
  - `RTA/dashboard/class-protections.php` (the whole file).
  - `class-controller.php:72 require_action_passcode`, `:118 require_page_access`.
  - `class-auth-controller.php:182 verify_passcode`, `:216 passcode_status`.
- **Storage:**
  - `hbfwc_rta_protected_dashboard` (option, or user meta for an override):
    `{pages:{section|analytics:{key:mode}}, action:{key:mode}}`.
  - User meta `hbfwc_rta_protected_dashboard_extra`.
  - `hbfwc_rta_profile_field_access` (option / user meta).
  - Options `hbfwc_rta_passcode_fallback` (6 digits), `hbfwc_rta_passcode_expiration`
    (default 5 min), `hbfwc_rta_passcode_require` (`yes` = one grant per key, otherwise a global
    grant), `hbfwc_rta_locked_message`.
  - Grants are transients `rta_rest_passcode_{uid}_{md5(key)|global}`.
- **Modes:**
  - Pages and actions: `open|protected|locked`. We rename `protected` to `passcode`.
  - Extras: `open|closed`.
  - Profile fields: `allow|restrict`.
- **Pages:**
  - Sections: `rooms, customers, booking, availability, payroll, my-payroll, user-activity,
    booking-backup`.
  - Analytics: `sales, rooms`.
  - Locked by default: `payroll, user-activity, booking-backup`.
- **Actions (43):** `add-booking, ban-users, approve-booking, decline-booking, cancel-booking,
  check-in, check-out, mark-as-paid, modify-order-status, add-booking-item, edit-booking-item,
  remove-booking-item, add-order-note, edit-order-note, remove-order-note, add-new-staff,
  edit-staff, remove-staff, add-staff-note, edit-staff-note, remove-staff-note, edit-customer,
  add-customer-note, edit-customer-note, remove-customer-note, manage-room-number, run-payroll,
  finalize-payroll, settle-payroll, void-payroll, revert-payroll, remove-payroll-run,
  edit-payroll-item, manage-pay-schedules, manage-work-shifts, manage-contracts,
  record-statutory-payment, edit-business-info, edit-tax-info, approve-time-off,
  generate-booking-backup, download-booking-backup, remove-booking-backup`.
  The last 17, from `run-payroll` on, are locked by default.
- **Profile fields** (field → group, default):
  - `username` → name, restrict
  - `first_name`, `last_name` → name, allow
  - `dob, address, marital_status, num_kids` → personal, restrict
  - `phone, email` → contact, restrict
  - `em_full_name, em_address, em_phone, em_relasi` → emergency, restrict
  - `passcode, password` → account, allow
- **Keep:**
  - Payroll defaults to locked for staff.
  - For administrators, *locked* is relaxed to *passcode*.
  - Profile-field restrictions are enforced on the server.
  - "Reset staff access" wipes all overrides.
- **Don't copy:**
  - The plaintext PINs, the fallback PIN sent to the client, and the lack of a brute-force limit.
  - A per-user override replaces the whole map instead of merging key by key.
  - Changing a PIN clears only the global grant.

## 14 Activity log
- **Code:**
  - `RTA/utils/class-logger.php` (`log :77`, schema in option `hbfwc_restata_log_schema`).
  - `rest/dashboard/class-activity-controller.php:142 get_activity`, `:216 action_labels`,
    `:296 record_view`.
  - `utils/class-log-backup.php`: cron `hbfwc_rta_activity_log_backup`; option
    `hbfwc_rta_activity_backup_settings` (`enabled=true, interval_months=3,
    delete_after_export=false, keep_months=12`).
- **Table `hbfwc_restata_staff_log`:** `staff_id, user_role, event_type (audit|view), action,
  description, object_type, object_id, ip_address, user_agent, user_agent_raw, dedupe_key,
  hit_count, created_at, updated_at`.
- **Actions:** `login, logout, create_booking, approve_booking, decline_booking, checkin_booking,
  checkout_booking, remove_booking, view_page, passcode_success, passcode_failed,
  forbidden_access, ban_user, unban_user, add_room, delete_room, add_customer_note,
  delete_customer_note, custom`.
- **Keep:**
  - Page views are grouped within **900 s** (the legacy window). Use 900 s as the default
    grouping window, not 60 s.
  - The raw user agent is kept only for auth and security events.
  - The user's role at the time of the event is stored on the row.
- **Don't copy:**
  - One action key reused for different events.
  - Unlogged logout, notes, payroll, staff and backup events.
  - Log purge gated only by the page permission.

## 15 Payroll and HR
- **Code:**
  - `rest/dashboard/class-payroll-controller.php`: calc `:435`, create run `:1441`, finalize
    `:1529`, settle `:1558`, void `:1599`, revert `:1617`, remove `:1632`, run item `:1788`,
    statutory `:1107-1172`, time off `:1953-2048`.
  - `RTA/payroll/class-payroll-tax.php`, `class-payroll.php`, `class-request.php:822 run_payroll`,
    `class-additional-pay.php`, `class-deductions.php`, `class-business-info.php`.
  - Payslip templates: `templates/admin/payroll/prints/*`.
- **Storage:**
  - Tables `hbfwc_payroll_{contracts,notes,shifts,time_off,pay_schedule,runs,items,itemmeta,
    statutory_items,statutory_payments,statutory_payment_items,statutory_payment_transactions,
    garnishments,garnishment_payments}`.
  - Options `hbfwc_payroll_injury_rate` (2–5 %), `hbfwc-payroll-business-info`,
    `hbfwc-payroll-business-logo`, `hbfwc_rta_max_vacation_days` (0 = unlimited).
- **Keep:** the rules are in `docs/modules/M15-payroll-hr.md` (statutory maths, run lifecycle,
  pay intervals, time off).
- **Don't copy:**
  - The fake `$_POST` + `wp_redirect` capture to call the classic handlers.
  - An un-namespaced `InvalidArgumentException` (fatal).
  - Bracket tables duplicated in two places.
  - Colliding payslip file names.

## 16 Staff self-service
- **Code:**
  - `class-profile-controller.php:56 get`, `:67 update_profile`, `:179 enforce_field_access`,
    avatar at `:228/286`.
  - `class-payroll-controller.php:802 get_my_paychecks` (settled runs only), `:852 download_payslip`.
  - `/time-off/mine`.
- **Keep:**
  - Restricted fields keep their stored values; the server ignores changes to them.
  - Phone is required.
  - The PIN is digits only.
  - Changing the password signs out the other sessions.
  - Only pending time-off requests can be cancelled.
- **Don't copy:**
  - The raw-SQL username rewrite.
  - The `edit-profile` gate ignored in REST.

## 17 Settings
- **Code:** `class-settings-controller.php:73 settings_payload`, `:127 save_settings`,
  `:186 upload_sound` (mp3/wav/ogg/m4a/webm, admin only).
- **Core settings** (option `woocommerce_hbfwc_settings`): `manual_approval, block_checkout,
  payment_time_limit, approval_time_limit, approval_expired_action, show_rooms_field,
  availability_visibility, exclude_shop, sameday_enabled, sameday_cutoff, max_child_age,
  date_format, addtocart_label`, plus RTA `booking_window` (default 1).
- **Keep:** protection settings are saved from a whitelist of keys, never taken raw from input.

## 18 i18n, currency and foundations
- **Text domains:** `hbfwc-residencetata-addon` (`languages/*-fr_FR.po`) and
  `hotel-booking-for-woocommerce`.
- **SPA strings:** a dictionary of about 700 strings in `class-react-app.php:268 i18n_strings()`.
  Mine it for French labels.
- **Locale:** user meta `hbfwc_dash_locale`, set with `?lang=`.
- **Boot payload:** `locale, timezone, currency` (symbol only), `idTypes`.
- **Money:** payroll uses `decimal(26,8)` with bcmath.
- **Don't copy:**
  - A global `locale` filter that overrides the locale site-wide.
  - `?lang=` without a nonce.
  - Only the currency symbol sent to the SPA.
  - Ionicons loaded from unpkg at runtime.
  - A `staff` role that is removed on deactivation.
