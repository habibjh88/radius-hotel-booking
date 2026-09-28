# M09 — Guest records

| | |
|---|---|
| **Client module** | 9 — Guest records |
| **Features** | 9.1–9.12 |
| **Depends on** | M14 (edits are logged; notes and history are shown) |
| **Tier** | Free |
| **Engine** | no |
| **Critical review** | no, except that identity-document handling gets a check in M02's review |

## Goal

One record per real person, recognised again when they return, with their identity document on
file, their notes and their full stay history. Guests are **not** WordPress users (the legacy
system made them WooCommerce customers). A guest can optionally be linked to a WP user later.

## Scope

| Feature | Summary | Task |
|---|---|---|
| 9.1 | Guest list: reference, name, e-mail, phone, standing, number of stays, last stay | T2 |
| 9.2 | Search by name, e-mail or phone: indexed, with phones normalised to E.164 (`+225…`) and names folded (accents, case) | T1, T2 |
| 9.3 | Guest detail screen: header card, contact details, ID document, stays, notes, history | T3 |
| 9.4 | Stay history with links to each booking (the list fills in once M02 exists; the endpoint is ready now) | T3 |
| 9.5, 9.7 | Edit contact details, with every change logged with before/after | T1, T3 |
| 9.6 | Identity document: type (from a list) and number, kept on the guest. The number is **masked** in lists and shown in full only on the detail screen with `guests.view_id` | T1, T3 |
| 9.8, 9.9 | Guest notes (add, edit, remove; author and time) in the shared `notes` table, shown beside the history tab | T4 |
| 9.10, 9.11 | Ban and unban a guest, with a reason. A banned guest cannot be booked (checked by `BookingService`, surfaced in M02) and is shown as *Banned* | T3 |
| 9.12 | No e-mail given → derive an internal address from the phone (the format is in the legacy reference), flagged `email_is_placeholder` so no mail is ever sent to it | T1 |
| — | Duplicate detection on create: an exact phone or e-mail match offers the existing record instead | T1 |
| — | `GET guests/lookup?q=` fast endpoint for the booking form (M02, M04): top 8 matches | T1 |

## Data

| Table | Key columns |
|---|---|
| `guests` | `id`, `reference` (Sequence `guest`), `first_name`, `last_name`, `name_search` (folded "first last"), `phone`, `phone_e164`, `email`, `email_is_placeholder`, `id_type`, `id_number`, `language` (fr/en), `standing` (normal/banned), `ban_reason`, `banned_at`, `banned_by`, `wp_user_id` (nullable), `stays_count`, `last_stay_at`, `created_by`, timestamps, soft delete. Unique: `phone_e164` (where not null), `email` (where not a placeholder). Indexes: `name_search`, `standing` |
| `notes` | Shared by bookings, guests and employees. `id`, `notable_type`, `notable_id`, `type` (general / caution / warning), `body`, `author_id`, `author_name`, timestamps, `edited_by`. Index `(notable_type, notable_id)`. **Built in this module** |

Identity document types (keys → FR / EN labels): `cni` (Carte Nationale d'Identité / National ID),
`passport` (Passeport / Passport), `consular_card` (Carte consulaire / Consular card),
`cni_receipt` (Récépissé CNI / CNI receipt), `id_certificate` (Attestation d'identité / Identity
card certificate), `driver_license` (Permis de conduire / Driver license). Filterable with
`rtbp_id_document_types`.

## API

| Method | Route | Access key | Purpose |
|---|---|---|---|
| GET | `guests` | `page.guests` | List and search |
| GET | `guests/lookup` | `bookings.create` | Quick lookup for the booking form |
| POST | `guests` | `guests.create` | Create |
| GET/PUT | `guests/{id}` | `page.guests` / `guests.edit` | Detail / edit |
| POST | `guests/{id}/ban` · `guests/{id}/unban` | `guests.ban` | Change standing (reason required) |
| GET | `guests/{id}/stays` | `page.guests` | Stay history |
| GET/POST/PUT/DELETE | `notes?type=guest&id=` · `notes/{id}` | `guests.notes` | Notes (generic controller, access by type) |

## Access keys (added to M13)

`page.guests`, `guests.create`, `guests.edit`, `guests.view_id`, `guests.ban` (passcode by
default), `guests.notes`.

## Activity log actions

`guests.create`, `guests.edit`, `guests.ban`, `guests.unban`, `guests.note_add`,
`guests.note_edit`, `guests.note_remove`, `guests.view_id` (sensitive read).

## Consumes / provides

- **Consumes:** `rtbp_activity()`, `Sequence`, `DataTable`. The `ActivityTimeline` panel is injected by Pro through `rtbp.guest.panels`.
- **Provides:** `GuestService::findOrCreate( array $data )` (used by M02 and M04),
  `GuestService::assertBookable( Guest $g )`, `Support\Phone::toE164()`, the `notes` table,
  `NoteService` and the `NotesPanel` component (reused by M03 and M12).

## Tasks

- [ ] T1 [Free] `guests` + `notes` tables, `Phone` normaliser (CI numbering: 10-digit national numbers since 2021, and the legacy 8-digit form), name folding, placeholder e-mail, `GuestService` (create with duplicate detection, update with logging, ban/unban, lookup); unit tests for phone and name folding
- [ ] T2 [Free] Guest list screen (search, standing filter, mobile cards)
- [ ] T3 [Free] Guest detail screen (contact, ID with masked/full reveal, stays tab, ban dialog with reason, `ActivityTimeline`)
- [ ] T4 [Free] Generic notes API + `NotesPanel` component, wired into the guest detail screen

## Acceptance

1. Create a guest with phone `07 07 12 34 56` and no e-mail. The phone is stored as
   `+2250707123456`, and a placeholder e-mail is generated and flagged.
2. Create another guest with `+225 0707123456`. The system offers the existing record.
3. Search `kone` and find *Koné*.
4. Ban the guest with a reason. The list shows *Banned*, and the log shows who did it, when and why.
5. A receptionist without `guests.view_id` sees `CNI ••••3456`.

## Legacy reference

`legacy-reference.md` → Module 9 (customer storage, ID meta keys, the phone-derived e-mail format).

## Migration notes

Legacy WooCommerce customers become `guests`: normalise phones, deduplicate on `phone_e164`, and
carry over notes and ban state (see the legacy reference for their meta keys).

## Progress notes
