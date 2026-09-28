# 08 — Feature List for Approval

**Project:** Food Menu Hotel Booking — Residence TATA
**Prepared by:** RadiusTheme
**Version:** 1.0 — 13 September 2026
**Status:** For client approval

---

## How to read this document

This is the complete list of what the new hotel management system will do. It was built by going
through **every screen of your current dashboard**, writing down everything it does today, and then
adding the three things you asked for.

Nothing you use today is dropped. The new system is not built on WooCommerce — a reservation
becomes a real record of its own instead of a shop order line — but every screen, every button and
every report you currently rely on has an equivalent here, and most are better.

Each feature carries a status:

| Mark | Meaning |
|---|---|
| **Same** | You have this today. It is rebuilt as-is, on the new foundation. |
| **Better** | You have this today, but it is limited or awkward. It is rebuilt and improved. |
| **New** | You do not have this today. |

Please read each module and tell us: **keep**, **drop**, or **change**. Once the list is agreed we
will produce the plan, the timeline and the price against exactly this list — not against anything
wider.

---

## Contents

| # | Module | Features |
|---|---|---|
| 1 | Front desk dashboard | 12 |
| 2 | Taking a booking | 14 |
| 3 | The booking record and its life | 16 |
| 4 | Guest booking on your website | 10 |
| 5 | **Manual payment, invoice and confirmation** | 14 |
| 6 | Rooms, floors and inventory | 13 |
| 7 | Rate plans and pricing | 16 |
| 8 | Availability calendar and booking rules | 12 |
| 9 | Guest records | 12 |
| 10 | Reports | 17 |
| 11 | Exporting and archiving data | 9 |
| 12 | Staff records and employment | 17 |
| 13 | **Staff permissions and access control** | 16 |
| 14 | **Activity log — who did what** | 18 |
| 15 | Payroll and HR | 21 |
| 16 | Staff self-service | 9 |
| 17 | Settings and system | 13 |
| 18 | Language, currency and foundations | 9 |

**Total: 248 features.**

---

## Module 1 — Front desk dashboard

The screen the reception staff live on all day. It answers "what is happening right now" without a
single click.

| # | Feature | Description | Status |
|---|---|---|---|
| 1.1 | Awaiting-approval counter | A live count of bookings that came in and still need a member of staff to accept them. | Same |
| 1.2 | Check-in today counter | How many guests are due to arrive today. | Same |
| 1.3 | Check-out today counter | How many guests are due to leave today. | Same |
| 1.4 | Available rooms counter | How many physical rooms are free to sell right now. | Same |
| 1.5 | Booking list | Every booking in one table: reference, guest, room type, rate, floor, room number, arrival, departure, payment status and booking status. | Same |
| 1.6 | Quick filter tabs | One-click switching between *All*, *Awaiting approval*, *Check-in today* and *Check-out today*. | Same |
| 1.7 | Date-range filter | Filter the list by a from/until range. | Same |
| 1.8 | Filter by arrival **or** by creation time | Choose whether the date range means "guests arriving in this window" or "bookings taken in this window". These answer very different questions and both are needed. | Same |
| 1.9 | Search | Find a booking by its reference number, guest name or room number. | Same |
| 1.10 | Row actions | Approve, decline, cancel, check in, check out or open a booking straight from its row — the buttons shown depend on the state the booking is actually in. | Same |
| 1.11 | Today's overview panel | A short summary of the day's activity beside the list. | Same |
| 1.12 | New-booking alert | A sound and an on-screen alert on **every** dashboard screen the moment a new booking arrives, so reception never misses one. | Same |

---

## Module 2 — Taking a booking at the front desk

The walk-in flow. This is the screen your staff use most after the dashboard.

| # | Feature | Description | Status |
|---|---|---|---|
| 2.1 | Pick arrival and departure dates | Two date fields at the top drive everything below them. | Same |
| 2.2 | Available rates, grouped by room type | Every sellable rate for those dates, listed under its room type (*Standard Room*, *Room VIP*), each showing the exact window and the price. | Same |
| 2.3 | Live pricing per rate | The price shown is the price for those specific dates, including any seasonal or discount rule in force. | Same |
| 2.4 | Unavailable rates shown, not hidden | A rate that cannot be sold is shown greyed out **with the reason**, instead of quietly disappearing. Staff can explain to the guest why. | Better |
| 2.5 | Room number chosen by floor | After a rate is picked, choose the actual physical room, grouped under its floor. | Same |
| 2.6 | Number of guests | Adults, and children where the room type allows them. | Same |
| 2.7 | Find an existing guest | Search by name, phone or e-mail and reuse the record instead of retyping it. | Same |
| 2.8 | New guest details | First name, last name, phone. E-mail is optional. | Same |
| 2.9 | Identity document | Document type — *National ID (CNI)*, *Passport*, *Consular Card*, *CNI Receipt*, *Identity Card Certificate*, *Driver License* — and its number. | Same |
| 2.10 | Order summary | A running total before anything is saved. | Same |
| 2.11 | Set payment state on creation | Mark the booking *Pending* or *Paid* at the moment it is created — for the guest who pays cash at the desk immediately. | Same |
| 2.12 | Several rooms on one booking | Add more than one room to the same booking, each with its own rate, dates and room number, under one reference and one total. | Better |
| 2.13 | Double-booking impossible | Once a room is on a booking — even an unpaid, unapproved one — it cannot be sold again for an overlapping window. Enforced by the system, not by staff attention. | Better |
| 2.14 | Room held while the booking is completed | A short hold on the chosen room while the form is being filled in, so two receptionists cannot grab the same room at the same second. | New |

---

## Module 3 — The booking record and its life

Everything about one booking, on one screen, from creation to departure.

| # | Feature | Description | Status |
|---|---|---|---|
| 3.1 | Booking detail screen | Reference, date taken, number of rooms, payment state, guest, and every room on the booking with its rate, window, guest count and room number. | Same |
| 3.2 | Money summary | Line prices, subtotal and total, in CFA. | Same |
| 3.3 | Payment method shown | Which method the guest chose or was charged under. | Same |
| 3.4 | Payment status | *Pending · Processing · On hold · Paid · Cancelled · Refunded · Failed*. Cleaned up — the current system carries leftover test statuses that will not be carried over. | Better |
| 3.5 | Stay status per room | Each room moves independently through *Pending → Confirmed → Checked in → Checked out*, so a two-room booking where one guest has arrived is shown correctly. | Better |
| 3.6 | Approve a booking | Accept a booking that is awaiting approval. | Same |
| 3.7 | Decline a booking | Refuse a booking that is awaiting approval, with a reason. | Same |
| 3.8 | Cancel a booking | Cancel an accepted booking and release the room back to inventory. | Same |
| 3.9 | Mark checked in | Record the guest's actual arrival. | Same |
| 3.10 | Mark checked out | Record the guest's actual departure and free the room. | Same |
| 3.11 | Add a room to an existing booking | Extend a booking with another room without creating a second reference. | Same |
| 3.12 | Edit or remove a room on a booking | Change the rate, dates or room number of a line, or take it off the booking. | Same |
| 3.13 | Order notes | Internal notes on the booking — add, edit and remove. Every note records who wrote it and when. | Same |
| 3.14 | Guest billing panel | The guest's contact details and identity document, editable from the booking. | Same |
| 3.15 | Guest notes panel | Notes about the guest, visible from the booking as well as the guest record. | Same |
| 3.16 | Price frozen at booking time | The price on a past booking never changes when you change your tariff later. Your reports stay correct forever. | New |

---

## Module 4 — Guest booking on your website

The public side. Exactly the same availability logic as the front desk — one engine, two skins.

| # | Feature | Description | Status |
|---|---|---|---|
| 4.1 | Search bar | Arrival date and guest count, with a *Find Room* button, placed anywhere on the site. | Same |
| 4.2 | Optional rooms field | An extra "how many rooms" field in the search bar, switchable on or off. | Same |
| 4.3 | Availability results | The same rate-by-room-type layout the front desk sees, priced for the requested dates. | Same |
| 4.4 | Show or hide unavailable rooms | Choose whether sold-out rooms disappear from results or stay visible but unbookable. | Same |
| 4.5 | Room type pages | A page per room type with photos, description, bed information, room size, maximum occupancy and amenities. | Same |
| 4.6 | Booking form | Guest details and identity document, matching what the front desk collects. | Same |
| 4.7 | Rooms kept out of the shop | Room types never appear in the restaurant shop, product listings, search or feeds. Today this is a patch that leaks; here rooms are simply not shop products. | Better |
| 4.8 | Same-day booking control | Allow or block bookings for today, with a cut-off time after which today is no longer offered. | Same |
| 4.9 | Booking window | How many days ahead guests may book — a number of days, or unlimited. | Same |
| 4.10 | Mobile-first booking flow | The whole guest flow designed for a phone, since that is how your guests arrive. | Better |

---

## Module 5 — Manual payment, invoice and confirmation ★

**This is the change you asked for.** No online payment gateway. The guest books, receives clear
payment instructions and an invoice, pays by whatever means you agree, and a member of your staff
confirms the money has arrived. Only then is the booking treated as paid.

| # | Feature | Description | Status |
|---|---|---|---|
| 5.1 | Booking placed without payment | A guest completes a booking without paying anything online. The room is held for them. | New |
| 5.2 | Payment instructions on the confirmation screen | Immediately after booking, the guest sees exactly how to pay: which methods you accept, the account or number to send money to, the exact amount in CFA, and the booking reference to quote. | New |
| 5.3 | Payment instructions you control | You write these instructions yourself in Settings, per payment method, in French and English. No developer needed to change an account number. | New |
| 5.4 | Payment deadline | Each unpaid booking carries a deadline — for example 24 hours, or 2 hours before arrival. Shown to the guest and counted down. | New |
| 5.5 | Invoice generated automatically | A proper numbered invoice is created for every booking, carrying your hotel name, address, tax and CNPS numbers, logo, the guest's details, every room line, and the total. | New |
| 5.6 | Invoice as a PDF | Download or print the invoice from the booking screen, or send it to the guest. | New |
| 5.7 | Invoice sent to the guest | The invoice and the payment instructions go out by e-mail the moment the booking is created. | New |
| 5.8 | Sequential invoice numbering | Invoice numbers run in an unbroken sequence with a prefix you choose, so your accountant can follow them. | New |
| 5.9 | Staff record a payment | A member of staff opens the booking, records the amount received, the method used and a reference (the Wave or Orange Money transaction number, or "cash at desk"), and the date. | New |
| 5.10 | Mark the booking paid | With the payment recorded, the booking is marked **Paid**. This is the manual confirmation step you asked for. | New |
| 5.11 | Partial payments | Record a deposit now and the balance later. The booking shows *Partially paid* with the outstanding amount, until it is settled. | New |
| 5.12 | Receipt on payment | Once marked paid, a receipt is produced and can be printed or e-mailed to the guest. | New |
| 5.13 | Payment history per booking | Every payment recorded against a booking is listed — amount, method, reference, who recorded it and when. Nothing is overwritten. | New |
| 5.14 | Unpaid booking follow-up | A clear list of bookings past their payment deadline, so reception can chase or release them. Optionally release the room automatically. | New |

> **Note.** Everything in this module assumes no online payment. If you later want guests to pay by
> Wave or Orange Money online, that attaches to this same design without rework — the payment record
> is already there, only the way it gets filled in changes.

---

## Module 6 — Rooms, floors and inventory

| # | Feature | Description | Status |
|---|---|---|---|
| 6.1 | Room types | *Standard Room*, *Room VIP*, and any you add later — name, description, photos. | Same |
| 6.2 | Room type overview cards | Each type shown with its room count, its readiness state and the rates it sells. | Same |
| 6.3 | Floors as a named, ordered list | *Ground Floor, Floor #1, Floor #2…* — named once and shared by every room type. | Same |
| 6.4 | Room numbers per type | Add, rename and remove the physical rooms belonging to a room type. | Same |
| 6.5 | Room assigned to a floor | Every room sits on exactly one floor, chosen from the shared list. | Same |
| 6.6 | Room state | *Available*, *Maintenance*, or *Out of service / Closed*. A room not available is excluded from sale and shown as such in the reports. | Same |
| 6.7 | Rooms sorted naturally | `A9` comes before `A12`, not after it. | Better |
| 6.8 | A room belongs to one room type only | A room can never appear under two types, so it can never be sold twice. | Same |
| 6.9 | Room numbers unique across the property | Two room types cannot both own a room called `A1`. | Better |
| 6.10 | Bulk room creation | Create a whole floor at once from a floor plus a number range, instead of adding rooms one by one. | New |
| 6.11 | Move a room between types | With a warning if it has future bookings. Past bookings keep the type they were sold under. | New |
| 6.12 | Occupancy limits | Maximum adults and maximum children per room type. | Same |
| 6.13 | Bed and size information | Bed configuration and room size shown to the guest. | Same |

---

## Module 7 — Rate plans and pricing

Your business model is part-day and overnight stays sold as fixed time blocks. This module is that
model.

| # | Feature | Description | Status |
|---|---|---|---|
| 7.1 | Rate plan library | Named, reusable stay windows — *Half Day Stay*, *Extended Half Day Stay*, *Rest Time*, *Overnight Stay*, *Extended Overnight Stay*, *24 Hours Fixed Stay*, *24 Hours Flexible Stay*. Defined once, used by every room type. | Same |
| 7.2 | Fixed-window rate plans | A start and an end time — *08:30 → 17:00*, *20:00 → 08:00 next day*. | Same |
| 7.3 | Flexible-window rate plans | Check-in at any time, check-out a fixed number of hours later. | Same |
| 7.4 | Rate plan features | Short tags shown to the guest — *Non-refundable*, *8.5 Hours Stay*, *Flexible Check-in*. | Same |
| 7.5 | Rate plan policy text | The full check-in and check-out policy shown on the booking page. | Same |
| 7.6 | Price per rate plan **per room type** | The same *Half Day Stay* costs 10,000 CFA as a Standard Room and 15,000 CFA as a VIP. One window, two prices. | Same |
| 7.7 | Sale price | A promotional price alongside the regular price. | Same |
| 7.8 | Minimum stay | A minimum number of nights or blocks required for a rate. | Same |
| 7.9 | Rate on/off per room type | Switch a rate plan off for one room type without deleting it or affecting the other. | Same |
| 7.10 | Where a rate plan is in use | See how many bookings and room types use a rate plan before you change or delete it. | Same |
| 7.11 | Seasonal pricing templates | Reusable adjustments — *increase by 20%*, *decrease by 5,000 CFA* — defined once. | Same |
| 7.12 | Seasonal pricing rules | Apply a template to a date range, a room type and a rate plan. | Same |
| 7.13 | Occupancy pricing | Automatically raise rates once occupancy passes a level you set — for example +15% when the hotel is 80% full. | Same |
| 7.14 | Early-bird discounts | An automatic discount for booking a set number of days ahead, as a percentage or a fixed amount. | Same |
| 7.15 | Last-minute discounts | The same, for bookings made close to arrival. | Same |
| 7.16 | Clear pricing order | One documented order in which base price, per-date override, seasonal rule, occupancy rule and discount are applied, shown on screen so you can see exactly why a price is what it is. | New |

---

## Module 8 — Availability calendar and booking rules

| # | Feature | Description | Status |
|---|---|---|---|
| 8.1 | Availability calendar per room type | A grid of dates against rate plans showing what is open and at what price. | Same |
| 8.2 | Per-date price override | Set a different price for one rate on one specific date. | Same |
| 8.3 | Per-date rate on/off | Close one rate plan on one date — for example no *Rest Time* on 31 December. | Same |
| 8.4 | Per-date room-type on/off | Close a whole room type for a date. | Same |
| 8.5 | Bulk update | Apply a price or an open/closed state across a date range in one action, instead of day by day. | Same |
| 8.6 | Availability count per date | How many rooms of a type remain sellable on each date. | Same |
| 8.7 | Room-level availability | Availability resolved down to the individual room number, not just a stock count. | Same |
| 8.8 | Overlapping windows handled correctly | *Half Day* (08:30–17:00) and *Rest Time* (17:00–20:00) can both sell the same room on the same day; *Overnight* (20:00–08:00) blocks the next morning. The system knows which windows collide. | Better |
| 8.9 | Maximum children age | The age up to which a guest counts as a child. | Same |
| 8.10 | Calendar sync (iCal) | Import and export availability to and from Booking.com, Airbnb and similar, so an external booking closes the room here. | Same |
| 8.11 | Manual approval mode | Optionally require a member of staff to accept every booking before it becomes firm. Already how you work — carried over. | Same |
| 8.12 | Blocked dates | Close the whole property or a floor for a date range — renovation, private event, public holiday. | New |

---

## Module 9 — Guest records

| # | Feature | Description | Status |
|---|---|---|---|
| 9.1 | Guest list | Every guest with reference, username, name, e-mail, phone and standing. | Same |
| 9.2 | Search guests | By name, e-mail or phone. | Same |
| 9.3 | Guest detail screen | Everything about one guest on one page. | Same |
| 9.4 | Booking history per guest | Every stay the guest has had, with dates, room, payment state and a link to each booking. | Same |
| 9.5 | Guest billing details | Name, e-mail, phone — editable. | Same |
| 9.6 | Identity document on file | Document type and number, kept with the guest so returning guests are not asked twice. | Same |
| 9.7 | Edit guest details | With every change recorded in the activity log. | Same |
| 9.8 | Guest notes | Free-text notes on a guest — add, edit and remove — each stamped with the author and time. | Same |
| 9.9 | Notes and history tab | Notes and the guest's change history side by side. | Same |
| 9.10 | Ban a guest | Mark a guest as banned so they cannot book again. | Same |
| 9.11 | Guest standing | *Normal* or *Banned*, shown in the list. | Same |
| 9.12 | Automatic e-mail from phone number | Where a guest gives no e-mail, an internal address is derived from their phone number so the record is still complete. | Same |

---

## Module 10 — Reports

| # | Feature | Description | Status |
|---|---|---|---|
| 10.1 | Sales report | Revenue for any date range. | Same |
| 10.2 | Total net sales | The money figure, with tax shown separately. | Same |
| 10.3 | Completed bookings count | How many bookings in the range were actually paid. | Same |
| 10.4 | Unsuccessful bookings count | Cancelled, refunded and failed, counted separately so they do not pollute the revenue figure. | Same |
| 10.5 | Sales chart by day | A bar per day across the range, split by payment method. | Same |
| 10.6 | Bookings breakdown by payment method | How many bookings came through each method. | Same |
| 10.7 | Report by arrival **or** by creation date | The same switch as the dashboard — "money for stays happening this month" versus "money taken this month". | Same |
| 10.8 | Rooms report | Room utilisation for any date range. | Same |
| 10.9 | Available / rented / empty room counts | With closed and maintenance rooms called out separately. | Same |
| 10.10 | Bookings table for the period | Created, room type, room number, arrival, departure, payment, status and subtotal. | Same |
| 10.11 | Empty rooms grouped by floor | Exactly which rooms sat empty, floor by floor. | Same |
| 10.12 | Room availability screen | A live grid of every physical room, grouped by room type and floor, showing booked or not booked for the chosen window. | Same |
| 10.13 | Occupancy counters | Total rooms, occupied, available and total bookings for the window. | Same |
| 10.14 | Export any report | Download any report as a spreadsheet. | Better |
| 10.15 | Occupancy rate | Occupancy as a percentage, per day and across the period. | New |
| 10.16 | Revenue by room type and by rate plan | Which room types and which stay windows actually make your money. | New |
| 10.17 | Revenue by staff member | Which member of staff took which bookings — useful alongside the activity log. | New |

---

## Module 11 — Exporting and archiving data

| # | Feature | Description | Status |
|---|---|---|---|
| 11.1 | Booking export | Export complete booking data for a date range — one row per room, carrying the full booking, guest and line detail. | Same |
| 11.2 | Excel or CSV | Choose the file format. | Same |
| 11.3 | Export by arrival or creation date | The same date-mode switch used everywhere else. | Same |
| 11.4 | Archive-and-remove | Optionally delete the exported bookings from the live system **after** the file is written, to keep the database small. Off by default. | Same |
| 11.5 | Export file library | Past exports listed and downloadable at any time. | Same |
| 11.6 | Files kept in a protected folder | Export files are not publicly reachable by URL. | Same |
| 11.7 | Guest list export | Export the guest database as a spreadsheet. | New |
| 11.8 | Scheduled automatic exports | Run the booking export automatically on a schedule rather than by hand. | New |
| 11.9 | Full system backup notes | Documented what is backed up, how often and how to restore it. | New |

---

## Module 12 — Staff records and employment

| # | Feature | Description | Status |
|---|---|---|---|
| 12.1 | Employee list | Every member of staff with name, job title, work shift and active state. | Same |
| 12.2 | Add a new employee | Create a staff account and record. | Same |
| 12.3 | Edit an employee | Change any part of the record. | Same |
| 12.4 | Active / inactive staff | Deactivate a leaver without deleting their history. | Same |
| 12.5 | Account details | Username, password and personal passcode. | Same |
| 12.6 | Personal information | First name, last name, date of birth, address, marital status, number of children. | Same |
| 12.7 | Contact information | Phone and e-mail. | Same |
| 12.8 | Emergency contact | Full name, address, phone and relationship. | Same |
| 12.9 | Employment contract | Status (active or terminated), job type (full-time, part-time, contract, internship, other), job title, department and hire date. | Same |
| 12.10 | Statutory identifiers | CNPS number and tax number per employee. | Same |
| 12.11 | Work shift assignment | Assign the employee to a named shift. | Same |
| 12.12 | Pay schedule assignment | Assign the employee to a pay schedule. | Same |
| 12.13 | Pay definition | Hourly or salaried, with the amount and the period (per week, month or year). | Same |
| 12.14 | Additional pay types | Overtime, paid time off, unpaid time off, sick pay, vacation pay, bonus, commission, transport allowance and gratification. | Same |
| 12.15 | Deductions and garnishments | Recurring deductions against an employee's pay. | Same |
| 12.16 | Employee documents | Upload and store contracts, ID copies and other documents against the employee. | Same |
| 12.17 | Staff notes and history | Notes on an employee — add, edit and remove — alongside a record of every change made to their file. | Same |

---

## Module 13 — Staff permissions and access control ★

**This is the second change you asked for.** Not every member of staff should be able to do
everything. The system already has the shape of this; it becomes a proper, complete permission
system.

### 13a — How it works today, and what stays

| # | Feature | Description | Status |
|---|---|---|---|
| 13.1 | Three levels per page and per action | Every screen and every action is set to one of: **Open access** (anyone may use it), **Passcode protected** (a PIN is required each time), or **Locked** (not available at all). | Same |
| 13.2 | Page-level control | Rooms, Guests, Bookings, Availability, Payroll, My Payroll, Activity Log, Backups, Sales Report, Rooms Report — each controlled separately. | Same |
| 13.3 | Action-level control | Around fifty individual actions controlled one by one: add a booking, approve, decline, cancel, check in, check out, mark paid, change payment status, add/edit/remove a room on a booking, add/edit/remove notes, ban a guest, edit guest billing, manage room numbers, add/edit/remove staff, run and finalise payroll, approve time off, generate and download backups, and more. | Same |
| 13.4 | Defaults set once | Set the whole permission map once in Settings; it applies to every member of staff. | Same |
| 13.5 | Per-staff override | Override any single permission for one individual, without touching the defaults. | Same |
| 13.6 | Personal passcode | Each member of staff has their own numeric PIN, used to confirm protected actions. | Same |
| 13.7 | Fallback passcode | A shared PIN for accounts without a personal one — notably administrators. Can be switched off. | Same |
| 13.8 | Passcode validity period | How many minutes a PIN entry stays valid before it must be entered again. | Same |
| 13.9 | Passcode scope | Choose whether one PIN entry covers everything until it expires, or whether each protected page and action needs its own. | Same |
| 13.10 | Custom locked message | The message a member of staff sees when they reach something they may not use. | Same |
| 13.11 | Profile field control | Which fields a member of staff may change on their own profile — name, username, date of birth, address, marital status, children, phone, e-mail, emergency contact, passcode and password, each set to allowed or not allowed. Set as a default and overridable per person. | Same |
| 13.12 | Reset all overrides | One switch to clear every per-staff override and return everyone to the defaults. | Same |

### 13b — What is added

| # | Feature | Description | Status |
|---|---|---|---|
| 13.13 | **Named roles** | Instead of setting fifty switches for every new employee, create roles once — *Receptionist*, *Night Receptionist*, *Supervisor*, *Housekeeping*, *Accountant*, *Manager* — and assign the role. A new hire is configured in one click. | New |
| 13.14 | Role templates you can edit | Roles ship pre-filled with sensible permissions and you change them to suit your hotel. Change the role, and every person holding it changes with it. | New |
| 13.15 | Person overrides the role | A single person can still be given one extra permission, or have one taken away, without leaving the role. | New |
| 13.16 | Permission enforced on the server | A permission is not just a hidden button. Even if someone knows the address of a page or the shape of a request, the system refuses it. This is a genuine security guarantee, not a cosmetic one. | Better |

---

## Module 14 — Activity log: who did what ★

**This is the third change you asked for.** A complete, searchable record of every action taken in
the system.

| # | Feature | Description | Status |
|---|---|---|---|
| 14.1 | Every action recorded | Who did it, what they did, which record it touched, from which IP address, on which device and browser, and at exactly what time. | Same |
| 14.2 | Booking actions recorded | Booking created, approved, declined, cancelled, checked in, checked out, edited, removed. | Same |
| 14.3 | Sign-in recorded | Every sign-in, with the device and address. | Same |
| 14.4 | Page views recorded | Which screens each member of staff opened and when. | Same |
| 14.5 | Filter by staff member | Pick any employee and see only their activity. | Same |
| 14.6 | Filter by date range | From and until. | Same |
| 14.7 | Filter by kind of event | Separate *data changes* from *page views*, so real actions are not buried under navigation. | Same |
| 14.8 | Filter by specific action | Show only, for example, every *booking approved*. | Same |
| 14.9 | Search the description | Free-text search across what was recorded. | Same |
| 14.10 | Repeated actions grouped | Ten page views of the same screen in a minute show as one line marked ×10, keeping the log readable. | Same |
| 14.11 | Automatic archiving | The log is exported to a file automatically every 1, 2, 3, 6, 12 or 24 months, with the next run date shown. | Same |
| 14.12 | Optional purge after archiving | Once safely written to a file, old entries can be removed from the database — off by default, and never before the file exists. | Same |
| 14.13 | Archive on demand and download | Run an archive now, and download any past archive file. | Same |
| 14.14 | **Before-and-after values** | For a change, the log records not only *that* a price or a status changed, but **what it was and what it became**. This is what turns the log from a list of events into something you can actually settle a dispute with. | New |
| 14.15 | **Payment actions recorded** | Every payment recorded, every booking marked paid, every amount and reference entered, and by whom. Given manual payment, this is the most important thing the log will hold. | New |
| 14.16 | **Permission changes recorded** | Any change to who may do what is itself logged. | New |
| 14.17 | **Failed attempts recorded** | Someone trying to reach a locked page, or entering a wrong passcode, is recorded. | New |
| 14.18 | **Log cannot be edited** | Entries can be archived but never altered or selectively deleted from within the system. | New |

---

## Module 15 — Payroll and HR

Your current system runs payroll for Côte d'Ivoire. Everything below exists today. **Please tell us
whether payroll is in scope for the new system or stays where it is** — it is a large module and it
is largely independent of the hotel side.

| # | Feature | Description | Status |
|---|---|---|---|
| 15.1 | Run payroll | Produce a pay run for a pay period. | Same |
| 15.2 | Payroll run history | Every past run, kept. | Same |
| 15.3 | Finalise a run | Close a run so it can no longer be edited. | Same |
| 15.4 | Settle a run | Mark a finalised run as paid out. | Same |
| 15.5 | Void a run | Cancel a run. | Same |
| 15.6 | Revert and re-run | Reopen and re-calculate a run. | Same |
| 15.7 | Remove a draft run | Delete an unfinalised run. | Same |
| 15.8 | Edit an individual payroll line | Adjust one employee's figures within a run. | Same |
| 15.9 | Pay schedules | Named schedules with a pay interval, next payday, pay-period end and assigned employees. | Same |
| 15.10 | Work shifts | Named shifts — *Jour*, *Nuit*, *Jour de repos* — with per-day working hours and assigned employees. | Same |
| 15.11 | Employment contracts | Managed per employee (see Module 12). | Same |
| 15.12 | Statutory contributions | Records per period per authority, with amount, amount paid, balance and state. | Same |
| 15.13 | Record a statutory payment | Mark a contribution as paid. | Same |
| 15.14 | Côte d'Ivoire statutory rates | Retirement fund employer 7.70% (capped 2,700,000 CFA/month), family benefits 5.75% (capped 70,000), retirement fund employee 6.30% (capped 2,700,000), salary tax (IS) 1.50% on 80% of gross, national contribution (CN) 0–10% progressive, general income tax (IGR) 0–60% progressive. | Same |
| 15.15 | Work accident / disability rate | Your own configurable rate, 2–5%, capped at 70,000 CFA a month. | Same |
| 15.16 | Payroll reports | Monthly and yearly summaries of gross pay, employee taxes and deductions, net pay and employer contributions. | Same |
| 15.17 | Statutory summary report | All contributions for a period in one view. | Same |
| 15.18 | Time-off requests | Staff request time off; the request carries a period, day count, type, reason and remaining balance. | Same |
| 15.19 | Approve or decline time off | With a pending queue and a full history. | Same |
| 15.20 | Vacation quota | A maximum number of vacation days per year; requests beyond it are refused. Zero means unlimited. | Same |
| 15.21 | Business details | Company name, legal name, logo, tax number, CNPS number and address, used on payslips and documents. | Same |

---

## Module 16 — Staff self-service

| # | Feature | Description | Status |
|---|---|---|---|
| 16.1 | My profile | A member of staff views and updates their own record. | Same |
| 16.2 | Fields limited by permission | Only the fields you allow are editable; the rest are read-only, and the system refuses a change to them even if attempted directly. | Same |
| 16.3 | Change own password | With sign-out from other devices. | Same |
| 16.4 | Set own passcode | The personal PIN used for protected actions. | Same |
| 16.5 | Own personal and contact details | Where permitted. | Same |
| 16.6 | Own emergency contact | Where permitted. | Same |
| 16.7 | My paychecks | The employee's own payslips — pay date, period, hours, gross and net. | Same |
| 16.8 | Download own payslip | As a document. | Same |
| 16.9 | Vacation calendar | The employee's own time off and remaining balance. | Same |

---

## Module 17 — Settings and system

| # | Feature | Description | Status |
|---|---|---|---|
| 17.1 | New-booking notification | Sound and on-screen alert on every dashboard screen when a booking arrives. | Same |
| 17.2 | Custom notification sound | Upload your own audio file, or use the built-in chime. | Same |
| 17.3 | Vacation quota setting | Maximum vacation days per year. | Same |
| 17.4 | Passcode settings | Validity period, scope, locked message and fallback PIN. | Same |
| 17.5 | Permission defaults | The full page and action permission map (Module 13). | Same |
| 17.6 | Profile field defaults | Which profile fields staff may edit (Module 13). | Same |
| 17.7 | Booking window | How far ahead guests may book. | Same |
| 17.8 | Same-day booking and cut-off time | Whether today can be booked, and until what time. | Same |
| 17.9 | Manual approval toggle | Whether every booking needs staff acceptance. | Same |
| 17.10 | Date format | How dates appear across the system and on documents. | Same |
| 17.11 | Unavailable-room display | Hide sold-out rooms, or show them disabled. | Same |
| 17.12 | **Payment instruction text** | The instructions guests see and receive, per method, in both languages (Module 5). | New |
| 17.13 | **Invoice settings** | Numbering prefix, starting number, logo, hotel details and footer text. | New |

---

## Module 18 — Language, currency and foundations

| # | Feature | Description | Status |
|---|---|---|---|
| 18.1 | French and English | The whole dashboard and the whole guest flow available in both, switchable per user. | Better |
| 18.2 | CFA / XOF currency | Correct formatting everywhere, including documents. | Same |
| 18.3 | Local time zone | All times in Abidjan time; no drift on reports crossing midnight. | Better |
| 18.4 | Runs inside your existing dashboard | Same address, same look, same sidebar. Your staff will not need retraining. | Same |
| 18.5 | Works on a phone | Every screen usable on a phone, including the front desk screens. | Better |
| 18.6 | Independent of WooCommerce | The hotel side no longer depends on the shop. The restaurant and its shop keep running exactly as they do now. | Better |
| 18.7 | Bookings are real records | Arrival, departure, room, price and status are proper fields, not text hidden inside a shop order. This is what makes every report on this list fast and correct. | Better |
| 18.8 | Existing data brought across | Room types, floors, rooms, rate plans, guests, staff and all future bookings move to the new system. | Same |
| 18.9 | Past bookings stay readable | Historical records remain accessible; the old system is switched off but never deleted. | Same |

---

## Three things we need you to decide

| # | Question | Why it matters |
|---|---|---|
| **D1** | **Is payroll and HR in scope?** (Module 15, 21 features) | It is a large, self-contained module with nothing to do with selling rooms. If it stays where it is, this project is meaningfully smaller and faster. If it comes across, it needs its own stage. |
| **D2** | **Is the restaurant "Food Order and Inventory" area in scope?** | It appears in your sidebar today but is a separate system. We have assumed **not in scope** and have not listed it. |
| **D3** | **Do you want online payment later?** | The answer does not change this build — Module 5 is designed so a Wave or Orange Money gateway can be added afterwards without rework. We only need to know so we do not design it out. |

---

## What is deliberately not in this list

So that nothing is added by accident later. Each of these can be added afterwards without rebuilding
anything:

Food and drink charged to the room · a housekeeping board · deposits and security bonds ·
refunds and manual charges handled inside the system · promo codes · booking extras and add-ons
sold with the room · WhatsApp and SMS messaging · a guest self-service portal · QR arrival codes ·
a timeline / Gantt occupancy view · ADR and RevPAR analytics · printed registration cards ·
editable e-mail templates · one-click room moves · a guest blacklist beyond the ban switch ·
multiple properties · multiple currencies · point of sale.

---

## Next step

Mark each module **keep / drop / change** and return this document. We will then produce the
development plan, the stage-by-stage timeline and the price against exactly the list you approve.
