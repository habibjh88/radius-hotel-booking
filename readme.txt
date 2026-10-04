=== Radius Hotel Booking ===
Contributors: radiustheme
Tags: hotel booking, room booking, reservation, hotel, front desk
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Book hotel rooms by time window (half day, overnight or 24 hours) at the front desk or online. No WooCommerce needed.

== Description ==

Radius Hotel Booking runs a small hotel or residence from WordPress. It books
rooms by **time window**, not only by night, so the same room can be sold as a
half day in the afternoon and again overnight.

= Built around how the front desk works =

* **Stay windows you define.** Half Day 08:30–17:00, Overnight 20:00→08:00,
  24 hours from check-in, or any window you need, each with its own price.
* **No double bookings.** Every booking holds its room for its exact hours,
  including the cleaning time between guests.
* **A dashboard for the desk.** Today's arrivals and departures, bookings
  waiting for approval, and which rooms are free right now.
* **Walk-in bookings in a few taps**, on a computer, tablet or phone.
* **An availability calendar** to see free rooms by day, change a price for a
  date, close dates or block a room for maintenance.
* **Rate plans and prices** per room type, with a price per window.
* **Guest records** with stay history and notes.
* **Online booking** on your website, from a shortcode, a block or an
  Elementor widget, with a confirmation page and e-mails for the guest.
* **Manual payments** such as cash, bank transfer or mobile money, with
  invoices and receipts.
* **Reports** on sales, rooms and availability, with CSV export.
* **Staff accounts** with their own roles and permissions. Staff can work from
  wp-admin or from a Hotel Dashboard page on your website.
* **Your brand colour** across the dashboard and the booking pages.
* **Translation ready.** Every text can be translated with Loco Translate or
  on translate.wordpress.org.

= No WooCommerce =

The plugin does not need WooCommerce and does not change it. It keeps working
if WooCommerce is installed on the same site.

= Your data =

Bookings, guests and settings are kept in the plugin's own tables. Deleting the
plugin keeps them, unless you turn on "Delete all data on uninstall" in
Settings first.

= Pro =

Radius Hotel Booking Pro adds features for larger teams, such as the activity
log. This plugin is fully usable without it.

== Installation ==

1. In wp-admin, go to Plugins → Add New and search for "Radius Hotel Booking",
   or upload the plugin zip.
2. Activate the plugin.
3. Open **Radius Hotel Booking** in the admin menu and follow the setup steps
   on the dashboard: hotel details, room types, rooms, rate plans and payment
   instructions.

A **Hotel Dashboard** page is created for your staff. Staff sign in there with
their WordPress account.

== Frequently Asked Questions ==

= Can a room be booked twice on the same day? =

Yes, if the stays do not overlap. A room booked for a half day until 17:00 can
be booked again from 20:00 overnight.

= Do I need WooCommerce? =

No. Bookings, payments and invoices are handled by the plugin itself. If your
site already runs a WooCommerce shop, it keeps working as before.

= Which payment methods are supported? =

Payments are recorded by your staff: cash, bank transfer, mobile money or any
method you add in Settings → Payments. Guests booking online see your payment
instructions. Online card payments are not included.

= Does it work on a phone? =

Yes. Every staff screen and the guest booking flow work on a phone, so the desk
can take a walk-in booking from a tablet or phone.

= How do I translate it? =

Install Loco Translate, open Loco Translate → Plugins → Radius Hotel Booking and
add your language. The plugin's screens, e-mails and documents all pick up the
translation.

= Why are times one hour off? =

Check Settings → General in WordPress: choose your city as the time zone, not a
UTC offset such as "UTC+1", which ignores daylight saving. The plugin's
dashboard shows a warning to administrators when this needs fixing.

= Where are uploaded documents stored? =

In a protected folder in uploads, served only to staff who may see them. On
nginx, add `define( 'RTBP_PROTECTED_DIR', '/path/outside/the/web/root' );` to
wp-config.php to keep them outside the public folder.

== Screenshots ==

1. The front desk dashboard: today's arrivals and departures, rooms free now and bookings to approve.
2. A walk-in booking: choose the stay window, the room and the guest.
3. The availability calendar with free rooms and prices per date.
4. A booking with its rooms, payments and invoice.
5. Online booking on the website, on a phone.
6. Reports: sales by day and by payment method.

== Changelog ==

= 1.0.0.1 ( UNRELEASE ) =
Removed the wp-env Docker environment, Playwright end-to-end tests and the PHPUnit test setup.
Moved rtbp_table_prefix() into the core functions file with the other global helpers.
Fixed admin screens failing when another plugin changes WordPress's shared REST request settings.
Added extension points so add-ons can add settings tabs and reuse the admin interface.
New admin dashboard with a grouped sidebar, today's arrivals and departures, and a setup checklist.
The brand colour chosen in Settings now restyles the whole dashboard, including the logo.
Removed the example Items screen, shortcode, block, widget and e-mail.
Now requires PHP 8.0 or later.
New Hotel Dashboard page: staff use the dashboard on your website, with a sign-in screen.
On phones, the dashboard has a bottom tab bar; press Ctrl+K (⌘K) to jump to any screen.
Saving settings now shows a short confirmation message in the corner.
French translation included; staff see the dashboard in their own profile language.
Fixed a possible error after an update when the Hotel Dashboard page had been deleted.
Longer labels on the dashboard and phone tab bar now wrap instead of being cut off.
Now requires WordPress 6.2 or later.
Add-ons can now add screens, handle request errors, and reuse dialogs, menus and forms.
Fixed add-on screens sometimes missing when the admin page loaded slowly.
Settings are now checked before saving; invalid values are refused with a message per field.
Redesigned Settings screen: side tabs, an unsaved-changes bar, and reset each tab to defaults.
New Booking rules settings: booking window, same-day cut-off, manual approval, holds and cleaning time.
General settings now include address, phone, logo, tax and CNPS numbers, with live previews.
Fixed the hotel logo never appearing in e-mails.
New Notifications settings: new-booking alert, check interval, and a built-in chime or your own sound.
New E-mail settings: master switch, sender, reply-to, and an on/off switch for each e-mail.
New General setting to delete all plugin data when the plugin is deleted (off by default).
Added an Upgrade link and one dismissible Pro notice on the Settings screen.
Staff pages and settings are now open or locked per role, enforced on the server.
New Settings → Permissions tab: choose what Managers and Staff may open and do.
New room types: photos, amenities, occupancy, bed and size details; types with rooms cannot be deleted.
Fixed empty optional numbers being saved as zero instead of left blank.
New Rooms & floors screen: a card per room type with photo, room counts and readiness.
New Floors screen: add, rename, reorder and delete floors; floors holding rooms stay protected.
Room types now list their rooms by floor: add, rename, set maintenance or out of service, remove.
Room numbers are unique across the hotel; removed rooms keep their number.
New Bulk add: create a range of rooms like A1–A12 with a preview; existing numbers skipped.
Move a room to another room type, with a warning listing its upcoming bookings.
New Rate plans screen: stay windows like Half Day or Overnight, with a live window preview.
Fresh installs start with three rate plans: Half Day, Overnight and 24 Hours Flexible.
New Rates tab per room type: price, sale price and minimum or maximum stay per rate plan.
Room type cards now list the rate plans each type sells.
New price simulator on the Rates tab: see what a stay costs and each step behind it.
New Availability calendar: prices, free rooms and closed dates per room type and month.
Set a one-day price, or close a rate or a whole room type that day.
Update many calendar dates at once: set or clear prices, open or close, by weekday.
New Blocked dates screen: close a room, room type, floor or the whole property for a while.
New Guests screen: search by name, phone or e-mail; guests already on file are offered.
Guest page: edit contact details, and reveal the ID number (logged) when allowed.
Guest page: stay history, and ban or lift a ban with a recorded reason.
Guest notes: add, edit and remove notes marked note, caution or warning, with author and time.
New booking screen: choose dates and guests, then see every rate with its live price.
Pick rooms floor by floor; each is held while the booking is completed, several per booking.
Find the guest by name, phone or e-mail, or add them with their ID; banned guests are flagged.
Confirm a booking with its payment state; a changed price or taken room is shown first.
New booking record screen: rooms with their own status, money summary, guest and note.
Approve, decline, cancel, check in, check out or mark a no-show, per room or booking.
Checking in can move the guest to another free room of the same type.
Guests get an e-mail when their booking is approved, declined or cancelled.
A declined or cancelled room no longer counts towards the booking total.
Add a room to a booking, change its room, rate or dates, or remove it.
Changing a booked room keeps its price unless the rate, room type or times change.
Booking notes for the team, and the guest's own notes, right on the booking.
Edit the guest's contact and identity document from the booking.
Early arrivals can be checked in on the arrival day; the room is held from then.
New Settings → Payments: the payment methods you accept, with instructions guests receive.
New Settings → Invoices: invoice number prefix, first number, included tax and footer text.
Booking rules gain a payment deadline: hours after booking, capped before arrival.
Record payments and refunds on a booking; the status follows what was actually received.
Payment history on each booking; a mistaken payment is voided with a reason, never edited.
Paid now at the front desk records the method and reference as a real payment.
Unpaid bookings get a payment deadline, counted down on the booking; overdue ones are flagged.
Every booking gets a numbered invoice; changes re-issue it under the same number.
Print the invoice and a receipt for each payment from the booking screen.
Guests get an e-mail with how to pay, the deadline and their invoice.
Guests get a receipt e-mail for each payment recorded.
A booking page for guests: status, amount to pay, instructions, invoice and receipts.
New Payments screen lists unpaid bookings past their deadline, to remind or release.
Remind a guest to pay by e-mail, or release an overdue booking to free its rooms.
Dashboard counters now show live figures, plus in-house guests and overdue payments.
New Bookings screen: quick tabs with counts, date range by arrival or booking date, search.
Approve, check in, check out, cancel or record a payment straight from a booking row.
Choosing an item from a list row's menu no longer opens the row as well.
Today's overview on the dashboard: arrivals, departures and check-ins in one timeline, late ones flagged.
New bookings now chime and pop up on every staff screen; the bell counts those awaiting approval.
New Settings → Public booking tab: booking page, preselected adults, rooms field, room choice, privacy consent.
New hotel search bar for your website: [rtbp_search] shortcode, block or Elementor widget.
Settings → Public booking can create the booking page for you in one click.
Guests can now book online: rates, room choice, their details and ID, then payment instructions.
Website booking requests are rate-limited per visitor; forged forwarded-IP headers are ignored.
New page per room type at /rooms/{name}: photos, details, prices "from" and Book this room.
Fixed booking scripts loading on search results pages when the booking page was found.
Website bookings use a guest on file only when the name and identity document match.
Banned guests are now also recognised on the website by their identity document.
Website room holds are capped per visitor and can no longer be extended indefinitely.
New Sales report: net sales, tax, money collected, paid and unsuccessful bookings, by payment method.
Reports can be read by arrival date or by booking date.
Fixed the date range picker overflowing its box on narrow screens.
New Rooms report: rented and empty rooms, empty rooms by floor, and the period's bookings.
New Room availability report: every room booked, held, blocked or free for any time window.
Every report can be exported as a CSV spreadsheet; exports are recorded in the activity log.
New booking export: one row per booked room, legacy columns first, any date range.
Settings → Exports: choose a comma or semicolon separator for CSV files.
New Exports screen: make a booking file for any period and see the file library.
Export the whole guest list; download or delete export files, each recorded in the activity log.
Pro features can now add their own sections to the Exports screen.
Fixed: stored files could be reached directly by anyone who had seen their download link.
Fixed: a failed database read or disk write could leave an export file silently incomplete.
Only an administrator can delete an archive file whose bookings were removed.
Click your name in the dashboard sidebar for the account menu, with Sign out.
Fixed: some screen-reader labels, error messages and room counts could not be translated.
Administrators are warned when the site time zone is a fixed UTC offset.
Fixed: the dashboard's Timeline / Arrivals / Departures switch pushed the page sideways on phones.
The plugin zip no longer bundles a partial French translation; translate with Loco Translate instead.
Database updates no longer write to the PHP error log unless WP_DEBUG is on.
Fixed: a room's guest count could not be changed while its room was under maintenance.
Fixed: a booking's room could not be edited once its rate plan was no longer sold.
New status colours for documents that are drafted, finalised, settled or voided.
New status colours for requests awaiting approval, approved, declined or cancelled.
New shared amenity list: tick amenities on each room type instead of typing them again.
Renaming or deleting an amenity updates every room type; duplicates can be merged into one.
New "Add common amenities" button adds a ready-made list of standard hotel amenities in one click.

= 1.0.0 =
Initial release.
