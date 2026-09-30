=== Radius Hotel Booking ===
Contributors: radiustheme
Tags: hotel booking, room booking, reservation, hotel, front desk
Requires at least: 6.2
Tested up to: 6.7
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
* **Guest records** with stay history.
* **Online booking** on your website, from a shortcode, a block or an
  Elementor widget.
* **Manual payments** such as cash, bank transfer or mobile money, with
  invoices.
* **Staff accounts** with their own roles. Staff can work from wp-admin or from
  a Hotel Dashboard page on your website.
* **Your brand colour** across the dashboard and the booking pages.
* **English and French** included.

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

= Where are uploaded documents stored? =

In a protected folder in uploads, served only to staff who may see them. On
nginx, add `define( 'RTBP_PROTECTED_DIR', '/path/outside/the/web/root' );` to
wp-config.php to keep them outside the public folder.

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

= 1.0.0 =
Initial release.
