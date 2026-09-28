# M04 — Guest booking on your website

| | |
|---|---|
| **Client module** | 4 — Guest booking on your website |
| **Features** | 4.1–4.10 |
| **Depends on** | M02 (`BookingFlow`), M05 (instructions, invoice, confirmation), M08 |
| **Tier** | Free |
| **Engine** | yes (public holds, rate limiting; read `booking-engine.md` §5–§8) |
| **Critical review** | yes (public endpoints, holds, personal data) |

## Goal

The same availability engine and the same `BookingFlow` component on the public site, designed for
a phone. The steps are search bar → results by room type → room pages → booking form → confirmation
with payment instructions and the invoice. Rooms are not shop products, so nothing leaks into the
restaurant shop.

## Scope

| Feature | Summary | Task |
|---|---|---|
| 4.1, 4.2 | Search bar block + shortcode `[rtbp_search]`: arrival, departure, adults / children, an optional rooms count (a setting); submits to the results page | T1 |
| 4.3 | Results: `[rtbp_booking]` mounts `BookingFlow mode="guest"`, with the same rate-by-room-type layout and prices for the dates | T2 |
| 4.4 | Show or hide unavailable rates and rooms (setting 17.11). When shown, they are greyed with the reason | T2 |
| 4.5 | Room type pages: a virtual route `/rooms/{slug}` (a rewrite rule + template `templates/booking/room-type.php`, theme-overridable): gallery, description, beds, size, occupancy, amenities, the rate plans with "from" prices, and a *Book* button prefilled into the flow | T3 |
| 4.6 | Guest form: first and last name, phone (required), e-mail (optional), ID type and number (**required**), special requests, language; consent checkbox (privacy page link) | T2 |
| — | Room choice by the guest: a setting (`guestPicksRoom`, default **on** as in the legacy system). When off, the system assigns the lowest `number_sort` free room | T2 |
| 4.7 | Kept out of the shop: nothing to do by construction (ADR-001); verify there is no product, feed or search leak | T4 |
| 4.8, 4.9 | Same-day cut-off and the booking window enforced on the server (and reflected in the date picker) | T2 |
| 4.10 | Mobile-first: a stepper on small screens (Dates → Rate → Room → Details → Review), sticky summary + CTA, tap targets ≥ 44 px, fast on 3G (the site bundle stays under 150 KB gzip for this route) | T2, T4 |
| — | Confirmation page `/booking/confirmation/{public_token}`: reference, lines, total, instructions, deadline countdown, invoice download | T3 |
| — | Returning guests are **not** looked up publicly (the legacy PII leak). A guest simply types their details; the server matches `phone_e164` to the existing guest record silently | T2 |
| — | Banned guests: the server refuses with a generic message and the attempt is logged | T2 |

## Public API (listed in `rtbp_public_api_routes`, rate-limited)

| Method | Route | Purpose | Limit |
|---|---|---|---|
| GET | `public/availability` | The same payload as the desk, without internal fields (no room state notes, no booking refs) | 30/min/IP |
| POST | `public/holds` · DELETE `public/holds/{token}` | Hold the chosen room(s) for the setting's hold minutes | 10/min/IP |
| POST | `public/bookings` | Create from a hold token + guest details (creates the booking `pending` or `confirmed` per the manual-approval setting) | 5/min/IP |
| GET | `public/bookings/{token}` | Confirmation data | 30/min/IP |
| GET | `public/room-types` · `/{slug}` | Room type pages | cached |

The nonce comes from the localised `wp_rest` nonce. Endpoints also accept requests without login.
A honeypot field plus the rate limits provide spam protection; no CAPTCHA dependency.

## Settings (added to M17 → "Public booking")

`guestPicksRoom` (true), `showRoomsField` (false, 4.2), `resultsPageId`, `confirmationPageId`,
`privacyConsent` (true), `defaultAdults` (2).

## Tasks

- [ ] T1 [Free] Search bar block + shortcode + page installer (results and confirmation pages)
- [ ] T2 [Free] `BookingFlow mode="guest"` + public endpoints (availability, holds, create) + rate limits + server-side rule enforcement + the phone-first stepper
- [ ] T3 [Free] Room-type virtual pages + the confirmation page
- [ ] T4 [Free] Hardening: no-leak checks (search, feeds, sitemap), a bundle-size check, a Lighthouse mobile pass

## Acceptance

1. On a phone over throttled 3G: search, choose Half Day on Standard, choose room A2, fill in the
   details, confirm. The confirmation shows the Wave instructions, and the invoice e-mail arrives.
2. Two phones hold the last room at the same moment. The second is told the room was just taken
   and is offered alternatives.
3. Book for today after the cut-off. Today is disabled in the picker, and the API refuses it.
4. Nothing hotel-related appears in the shop, the product search or the feeds.
5. Two hundred rapid requests to `public/availability` from one IP are rate-limited, and staff
   pages are unaffected.

## Legacy reference

`legacy-reference.md` → Module 4, including the account-takeover and PII-leak **anti-patterns**.

## Progress notes
