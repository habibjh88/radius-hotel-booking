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
| `guests` | `id`, `reference` (Sequence `guest`), `first_name`, `last_name`, `name_search` (folded "first last"), `phone`, `phone_e164`, `email`, `email_is_placeholder`, `id_type`, `id_number`, `standing` (normal/banned), `ban_reason`, `banned_at`, `banned_by`, `wp_user_id` (nullable), `stays_count`, `last_stay_at`, `created_by`, timestamps, soft delete. Unique: `phone_e164` (where not null), `email` (where not a placeholder). Indexes: `name_search`, `standing` |
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
| GET | `guests/{id}/id-number` | `guests.view_id` | The full ID number (logged as `guests.view_id`); every other response masks it |
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

- [x] T1a [Free] `guests` + `notes` tables, `Support\Phone` (CI numbering: 10-digit national numbers since 2021 and the legacy 8-digit form → E.164), name folding, placeholder e-mail (9.12), `GuestService` (create with duplicate detection, update with logging, ban/unban, `findOrCreate`, `assertBookable`, lookup); phone normalising and name folding verified on the Local site — done 2026-09-30
- [x] T1b [Free] Guests REST API: list/search with paging, `lookup`, create, show, update, ban/unban, `stays` (empty until M02), ID masked unless `guests.view_id` (the reveal logged as a sensitive read) — done 2026-09-30
- [x] T2 [Free] Guest list screen (search, standing filter, mobile cards) (9.1, 9.2, 9.11) — done 2026-09-30
- [x] T3a [Free] Guest detail screen: header card, contact edit, ID document with masked/full reveal (9.3, 9.5, 9.6, 9.7) — done 2026-09-30
- [x] T3b [Free] Guest detail: stays tab, ban/unban dialog with reason, the `rtbp.guest.panels` slot (Pro's `ActivityTimeline`) (9.4, 9.10, 9.11) — done 2026-09-30
- [x] T4 [Free] Generic notes API + `NotesPanel` component, wired into the guest detail screen (9.8, 9.9) — done 2026-09-30

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
- 2026-09-30 planning: T1 and T3 split (T1a service, T1b API; T3a detail + edit + ID, T3b stays + ban + panels). **Access keys** are M13's existing ones: `page.guests`, `guests.create`, `guests.edit`, `guests.view_id`, `guests.ban`, and per action `guests.note_add|note_edit|note_remove` (not one `guests.notes`); the notes controller maps a notable type to its keys. `guests.ban` defaults to `open` (free never defaults to passcode; Pro can raise it). **ID document labels are English, translatable** (ADR-019), not FR/EN pairs. **Placeholder e-mail** (9.12): the legacy `{phone}@residencetata.com` is client-specific; free derives `{E.164 digits}@no-email.invalid` (`.invalid` can never deliver) through the filter `rtbp_guest_placeholder_email`, so the client add-on can keep the legacy format for the import, and flags it `email_is_placeholder`. **Unique e-mail** where not a placeholder: MySQL has no partial unique index, so a derived `email_key` (lower-case e-mail, NULL for a placeholder) carries the UNIQUE key; `phone_e164` is UNIQUE and NULL when no phone. Pro already hooks `ActivityTimeline` on `rtbp.guest.panels`; the free detail screen applies the filter (T3b).
- T1a (2026-09-30): tables `guests` and `notes` (DB 1.0.8). `Support\Phone`: `toE164()` (the hotel country from `rtbp_default_phone_country`, default 225; `+`, `00` and a bare `225…` accepted; Ivorian 10-digit numbers must start 01/05/07/21/25/27; **an old 8-digit mobile converts by its second digit** — 0–3 → 01, 4–6 → 05, 7–9 → 07; **an old 8-digit landline is not guessed** → no E.164, open question D7) and `tail()` (last 8 digits: the old number inside the new one, so both forms match — the legacy search's idea). `GuestService`: `fold()` (accents, case, spaces), `maskId()` (`••••3456`), `idTypes()` (`rtbp_id_document_types`), `create` (reference `G` + 6 digits from Sequence `guest`, `rtbp_guest_reference`; a duplicate — same E.164, same real e-mail, or same tail when either number has no E.164 — is refused with 409 `guest_exists` + `{ guests: [ { id, reference, name, match } ] }`; a UNIQUE-key race answers the same), `findOrCreate` (returns the existing guest, filling only its empty fields), `update` (only the given fields; a phone/e-mail on another guest → 422 on that field; logged with `ChangeDiff`, the ID number masked as `changed`; no-op → no entry; clearing the e-mail brings the placeholder back), `ban` / `unban` (reason 3–191 chars; `banned_at` / `banned_by`; the last ban reason is kept after an unban; the same standing twice → 409 `no_change`), `assertBookable` (409 `guest_banned`), `page` (q, standing; last stay first) and `lookup` (≤ 8, an exact phone first, 2+ characters). Search: every word of a name must appear in `name_search`; an e-mail by its start; the reference exactly; a number of 4+ digits by tail or within the E.164. Placeholder e-mail `{digits}@no-email.invalid` (`rtbp_guest_placeholder_email`), `email_is_placeholder` set, `email_key` NULL. Activity `guests.create|edit|ban|unban` (+ `guests.view_id`, VIEW kind, for T1b). Verified: 62/62 `wp eval-file` checks (17 phone cases incl. acceptance 1 and 2's forms, old mobiles per operator, an old landline, foreign numbers; folding; placeholder; duplicates by phone in both forms, by landline tail, by e-mail case-insensitively; 6 validation 422s; update / no-op / e-mail clash / placeholder back; ban with reason, who and when, ban twice, unban; `assertBookable`; `findOrCreate` both ways; search `kone` → Koné (acceptance 3), words in any order, phone digits, e-mail start, reference, standing filter; lookup order; no empty-tail match). A name clash (`where()` is public on `BaseRepository`) was found by the check and renamed. No UI, so no changelog line.
- T1b (2026-09-30): `GuestController` + `Resources\GuestResource`. Routes: `GET guests?q=&standing=&page=&per_page=` (+ `meta.total`, and `id_types` for the screens), `GET guests/lookup?q=` (registered before the resource), `POST guests` (201; 409 `guest_exists` with `data.guests`; 422 per field), `GET guests/{id}`, `PUT guests/{id}` (only the fields sent: first_name, last_name, phone, email, id_type, id_number; others ignored), `POST guests/{id}/ban|unban { reason }`, `GET guests/{id}/stays` (bookings with their lines, newest arrival first; empty until M02 writes bookings), **`GET guests/{id}/id-number`** — the only response with the full number, logged `guests.view_id` (VIEW kind, without the number). `DELETE guests/{id}` (registered by `resource()`) has no access key, so the middleware refuses it — guests are not deleted in this module. The resource sends a placeholder e-mail as `email: null` with `email_is_placeholder: true`, and `can_view_id` for the screen. Times: `banned_at` and `last_stay_at` are stored UTC and sent local; `created_at` is the model's local stamp. With Pro active `guests.ban` is PIN-protected for staff (Pro's passcode level: the doc's "passcode by default"); in free alone it is open. Verified: 25/25 REST checks (create / duplicate 409 / 422, the full number absent from every response but the reveal, list search `kone`, paging, detail, reveal logged without the number, **acceptance 5** — with `guests.view_id` locked the detail shows *National ID card* + `••••3456` and the reveal is 403, update of one field, ban 422 without a reason, staff ban → `passcode_required` with Pro, admin ban with who and why and the banned filter (**acceptance 4**), unban, stays empty then with a booking and its line, lookup by phone, DELETE 403 even for an admin, 404, signed-out refused, each of the 7 keys enforced when locked); staff ban 200 with Pro skipped. Test data removed. No UI, so no changelog line.
- T2 (2026-09-30): `#/guests` (`src/modules/Guests/`: `index.jsx`, `api.js` with `useGuests` / `useCreateGuest`, `components/AddGuestDialog.jsx`). `DataTable` with the debounced search (name, phone in any form, e-mail, reference), `FilterTabs` All guests / Banned, columns guest (name + reference), phone, e-mail (*No e-mail* for a placeholder), standing (`StatusBadge` — a new `standing` domain in the status palette: normal outline, banned red), stays, last stay; cards below `md`. Search, tab and page live in the URL. **Added an *Add guest* dialog** (not in the task text, but the list needs a way in and it makes acceptance 1–2 checkable in the browser): first/last name, phone, e-mail, document type and number; a guest already on file (409) is shown with *same phone* / *same e-mail* and *Use this guest*, which filters the list to it. Row click → detail comes with T3a's route. Browser: empty state; acceptance 1 (`07 07 12 34 56`, no e-mail → *No e-mail*, G000001), acceptance 2 (`+225 0707123456` → *already on file: Awa Koné G000001 · same phone*), acceptance 3 (`?q=kone` → Koné), Banned tab empty state, 360 px in a frame (cards, no sideways scroll); no console errors. The search placeholder was shortened to fit the table's 256 px field. Test data removed. Changelog line added.
- T3a (2026-09-30): hidden route `#/guests/:id` (`src/modules/Guests/Detail.jsx`); list rows and the Add guest dialog (new or *Use this guest*) open it. Header card: name, `StatusBadge` standing, reference, stays (plural), last stay, *Edit* (`guests.edit`); a banned guest shows a red line with the reason, who and when. Left column: **Contact** (phone as a `tel:` link on the E.164, e-mail or *None given* for a placeholder) and **Identity document** (`IdDocument`: type + `••••3456`; *Show number* only when `can_view_id` → `GET …/id-number`, a logged read, never cached, hidden again after 60 s, on *Hide*, or when the guest changes); *Added … by …*. `EditGuestDialog` sends only changed fields; the ID number is **never pre-filled** — empty keeps the one on file (the masked value is the placeholder), a new number replaces it, *Remove the document on file* clears type and number; server errors land on their fields. The right column is left for T3b (stays) and T4 (notes). Browser: row click → detail; *Show number* → `CI0012343456`, *Hide* → `••••3456`; edit e-mail + new number → `awa@example.com`, `••••8888`; phone `123` → *Enter a valid phone number.* on the field; remove the document → *No document on file.*; banned banner and 360 px in a frame (no sideways scroll); 404 → *This guest does not exist*; no console errors. Test data removed. Changelog line added.
- T3b (2026-09-30): the detail screen's right column is a **panel host**: free's *Stays* panel (order 10) plus `applyFilters( 'rtbp.guest.panels', panels, { guest } )` (ADR-015 contract `{ key, label, order, render() }`), sorted by `order`, shown as tabs when there are several and as one titled panel otherwise; malformed entries are dropped. Pro's *History* (`ActivityTimeline`, order 90) appears as a tab. `GuestStays`: each booking with reference, stay and payment `StatusBadge`s, total, `formatDateRange`, and its rooms (`Room A1 · Overnight`); empty state *No stays yet*; the link to the booking record comes with M03. **Ban / Lift ban** (`guests.ban`) open `ConfirmDialog` with `requireReason` (ban destructive); on success the cached guest is replaced (badge, banner, button) and the list refreshes; a PIN prompt (Pro) is handled by the API client. Note: `stays_count` / `last_stay_at` are maintained by M02's booking writes, so a booking inserted by hand shows in *Stays* while the header still says *0 stays*. Browser: *Stays* shows a seeded booking (RT-2026-000001, Confirmed, Paid, 15 000, Room A1 · Overnight); *History* (Pro) shows the logged creation; ban with a reason → *Banned* badge, banner with who/when, *Lift ban*; the list's Banned filter shows her; lift → *Normal*; 360 px in a frame (tabs, no sideways scroll); with Pro's `activity_log` switched off only a *Stays* panel, no tabs (the option was restored); no console errors. Test data removed. Changelog line added.
- T4 (2026-09-30): `Models\Note`, `NoteRepository`, `Services\Notes\NoteService` and `NoteController` = `resource( 'notes' )`: `GET notes?type=&id=` (+ `can_add`), `POST notes { notable_type, notable_id, type, body }`, `GET|PUT|DELETE notes/{id}`. **`rtbp_note_types`** (ADR-015) registers each record type with its access keys, a `find()` for the activity subject and the activity prefix; free registers `guest` (`page.guests` to read, `guests.note_add|note_edit|note_remove`; `guests.note_*` added to the catalogue). The middleware resolves the key per request — from the query type, the body type, or the note's own type for `show|update|destroy` — so an unknown type or a missing note has no key and is refused (403, fail closed). Body 1–5 000 characters (`sanitize_textarea_field`, line breaks kept); type `general|caution|warning` (the legacy types), default `general`. Author id and **name** stamped (the name survives the user); an edit keeps the author and sets `edited_by`; a no-op edit logs nothing; the removal logs the removed text. Each note carries `can_edit` / `can_remove` for the viewer. `NotesPanel` (`src/components/common/`, hooks in `src/api/notes.js`, published lazily on `window.rtbp.ui` — eager it pushed the admin entry past webpack's 300 KB budget): a write form (textarea + `SegmentedControl` Note / Caution / Warning), the list newest first with a `StatusBadge` (new `note` domain) for caution/warning, author · time · *edited*, inline edit, remove with `ConfirmDialog`; a change also refreshes the `activity` queries, so Pro's *History* shows it. Wired as the guest detail's *Notes* panel (order 20, beside *History* at 90 — 9.9). With Pro, `guests.note_remove` is PIN-protected for staff. Verified: 19/19 REST checks with Pro and 19/19 with Pro skipped (empty list, add with line breaks, author, logged on the guest, newest first, 3 validation cases, unknown guest 404, unknown type 403 even for an admin, edit before/after with `edited_by`, no-op, `can_edit/can_remove` false and 403 when locked, read refused without `page.guests`, staff removal → PIN with Pro / 200 without, removal logged with the text, unknown note 403). Browser: tabs Stays · Notes · History; add a Caution note with two lines, edit (pre-filled) → *edited*, *History* shows both with before/after, remove → empty state; 360 px in a frame with a long Warning note (wraps, no sideways scroll); no console errors. Test data removed. Changelog line added.
