# M06 — Rooms, floors and inventory

| | |
|---|---|
| **Client module** | 6 — Rooms, floors and inventory |
| **Features** | 6.1–6.13 |
| **Depends on** | M14 (every change logged), M13 (access keys) |
| **Tier** | Free |
| **Engine** | yes (inventory ownership rules; read `booking-engine.md` §1–§2) |
| **Critical review** | yes (room ownership and uniqueness are what prevent double-selling) |

## Goal

Model the hotel's physical inventory. Room types (*Standard Room*, *Room VIP*…; the diagram's
"packages"), a shared ordered list of named floors, and every physical room with its number,
floor and state. A room belongs to exactly one room type. Room numbers are unique across the
property, so a room can never be sold under two types.

## Scope

| Feature | Summary | Task |
|---|---|---|
| 6.3 | Floors: a named, ordered list (*Ground Floor, Floor #1…*), drag to reorder, no 0–4 limit; a floor holding rooms cannot be deleted | T1, T4a |
| 6.1, 6.12, 6.13 | Room types: name, description, short description, photos (gallery), amenities (a tag list), bed info, size m², max adults, max children, buffer minutes (optional override), active, sort order | T2 |
| 6.2 | Room type overview cards: room count by state, readiness (`ready` / `no_rooms`), the rate plans it sells (after M07, a placeholder before) | T3 |
| 6.4, 6.5 | Rooms per type: add, rename, remove; each on exactly one floor | T4b |
| 6.6 | Room state: `available` / `maintenance` / `out_of_service` + a note. Non-available rooms are excluded from sale (the engine reads the state) | T4b |
| 6.7 | Natural sort: `number_sort` generated on save (`A9` < `A12`, `B2` < `B10`) | T1 |
| 6.8, 6.9 | One room type per room (the `rooms.room_type_id` column; there is no join table) + a UNIQUE `number` constraint property-wide | T1 |
| 6.10 | Bulk create: floor + prefix + from/to range (+ zero-pad) → preview → create; clashes with existing numbers are listed and skipped | T5a |
| 6.11 | Move a room to another type: warns with the count and list of future bookings; past bookings keep their snapshot type; refused while the room is in use | T5b |
| — | Removing a room with future bookings is refused; one with only past bookings is soft-deleted (history intact) | T4b |
| — | A room type with rooms cannot be deleted until the rooms are moved or removed | T2 |

## Screens

`#/inventory/room-types` (cards grid) → `#/inventory/room-types/:id` (tabs: Details · Rooms ·
Rates (M07) · Calendar (M08)). The Rooms tab is grouped by floor headings with a room count:

```
Floor 1 (4)                                           [+ Add room] [Bulk add]
  A1  ● Available      ⋯        A2  ● Available      ⋯
  A3  ▲ Maintenance "AC repair"  ⋯   A4  ● Available  ⋯
Floor 2 (6)
  B1 … B6
```

`#/inventory/floors`: a sortable list. Mobile: the tile grid collapses to a list per floor.

## API

| Method | Route | Access key |
|---|---|---|
| CRUD + `PUT floors/order` | `floors` | `page.rooms` / `rooms.manage` |
| CRUD | `room-types` | `page.rooms` / `room_types.manage` |
| GET | `room-types/{id}/rooms` (grouped by floor) | `page.rooms` |
| CRUD | `rooms` | `rooms.manage` |
| POST | `rooms/{id}/state` | `rooms.manage` |
| POST | `rooms/bulk` (`dry_run` flag returns a preview) | `rooms.manage` |
| POST | `rooms/{id}/move` (`confirm` flag) | `rooms.manage` (passcode) |

## Activity log actions

`floors.create|update|delete|reorder`, `room_types.create|update|delete`,
`rooms.create|update|delete|state|move|bulk_create`, each with before/after.

## Consumes / provides

- **Provides:** `RoomRepository::sellableRooms( array $roomTypeIds )` (state = available, not
  deleted), `NaturalSort::key()`, `RoomTypeResource`, and the `RoomPicker` data shape
  `{ floors: [ { id, name, rooms: [ { id, number, state } ] } ] }`.

## Tasks

- [x] T1 [Free] `floors`, `room_types`, `rooms` tables (UNIQUE number, `number_sort`) + models / repositories + `NaturalSort` (verified on the Local site); the activity actions for floors, room types and rooms — done 2026-09-29
- [x] T2 [Free] Room type service + CRUD API + detail form (gallery via the WP media library, amenities tags); delete guard — done 2026-09-29
- [x] T3 [Free] Room-type overview cards screen — done 2026-09-29
- [x] T4a [Free] Floors service + API (`floors` CRUD, `PUT floors/order`, delete guard) + the Floors screen (sortable list) — done 2026-09-29
- [x] T4b [Free] Room service + API (`rooms` CRUD, `rooms/{id}/state`, `room-types/{id}/rooms` grouped by floor, uniqueness and remove guards) + the Rooms tab — done 2026-09-29
- [x] T5a [Free] Bulk create (`rooms/bulk`, dry-run preview, clashes listed and skipped) + its dialog — done 2026-09-29
- [x] T5b [Free] Move a room to another type (`rooms/{id}/move`, future-booking warning through `rtbp_room_future_bookings`, which M02 fills; checked now with a stand-in) — done 2026-09-29

## Acceptance

1. Create floors *Ground Floor, Floor #1…#5*. Bulk-create `A1`–`A12` on Floor 1 for *Standard Room*.
   They sort `A1, A2 … A9, A10, A11, A12`.
2. Try to add `A3` under *Room VIP*. It is refused ("Room A3 already belongs to Standard Room").
3. Set `A3` to maintenance with a note. The card counts update, and the log shows before/after.
4. Delete *Floor #5* while it holds rooms. It is refused with a clear message.

## Legacy reference

`legacy-reference.md` → Module 6. Two legacy room-number systems exist, and we unify them into `rooms`.

## Migration notes

`accommodation` products → `room_types` (post title, content, gallery, `_max_adults`,
`_max_children`, `_room_size`, `_no_of_beds`, the `amenities` terms). `hbfwc_restata_rooms` (`room_id`
= product, `number`, `floor`, `status`: `closed` → `out_of_service`) → `rooms`. The distinct floor
values become `floors`. Cross-check against PRO `_hbfwc_room_numbers` and report differences.

## Progress notes

- 2026-09-29 planning: T4 split into T4a (floors) / T4b (rooms), T5 into T5a (bulk) / T5b (move); services move into the task that uses them. **Screens** keep the existing sidebar path: `#/rooms` = room-type cards, `#/rooms/:id` = one type (Details · Rooms tabs; Rates and Calendar tabs arrive with M07 / M08), `#/rooms/floors` = floors (the doc's `#/inventory/…` paths are not used). **Booking guards** (remove, move): the bookings table arrives with M02, so "future bookings of a room" is the filter `rtbp_room_future_bookings( 0, $room_id )`, filled by M02; until then it is 0 and the guard is tested with a stand-in. Buffer per room type: `buffer_minutes` NULL = the Booking rules setting (D4 default 0). A room removed with only past bookings is soft-deleted and **keeps its number** (UNIQUE across the property, history stays unambiguous).
- T1 (2026-09-29): `Databases\Table\{FloorsTable,RoomTypesTable,RoomsTable}` (DB 1.0.3; short explicit index names: `number` UNIQUE, `type_state`, `floor`, `number_sort`, `slug` UNIQUE, `active_order`, `sort_order`), models `Floor`, `RoomType` (soft delete; `gallery`, `amenities` JSON), `Room` (soft delete; state constants), repositories bound in the container: `FloorRepository` (`ordered`, `roomCount` incl. removed rooms, `nextSortOrder`), `RoomTypeRepository` (`ordered`, `slugTaken` incl. removed), `RoomRepository` (`sellableRooms` — available, not removed —, `ofType`, `findByNumberAny` / `existingNumbers` incl. removed rooms, `stateCounts`). `Support\NaturalSort` (digit runs padded to 8, case-insensitive). 13 inventory actions added to `ActionCatalog`. `rooms.manage`, `room_types.manage`, `page.rooms` already existed (M13). Verified: 18/18 `wp eval-file` checks (migration + indexes, natural order `101 < 1001 < a1 < A2 < A3a < A3b < A9 < A10 < A12 < B2 < B10`, JSON casts, DB rejects a duplicate number under another type, sellable excludes maintenance and removed, state counts, removed room keeps its number, floor count incl. removed, slug check, catalogue). Test rows removed.
- T2 (2026-09-29): `Services\Inventory\RoomTypeService` (validation with field errors; slug from the name, unique incl. removed types; `wp_kses_post` description; amenities deduped, ≤ 40 × 60 chars; gallery must be image attachments; the cover must be one of the photos, else the first; `buffer_minutes` / `size_m2` empty = NULL; PATCH changes only the fields sent; `room_types.create|update|delete` with a `ChangeDiff` (description logged as a hash); hooks `rtbp_room_type_created|updated|deleted`; delete refused with 409 `room_type_has_rooms` while it has rooms). `RoomTypeController` (`room-types` resource; `page.rooms` to read, `room_types.manage` to change), `RoomTypeResource` (gallery URLs, room counts by state, `readiness`). Screen `#/rooms/:id` (`new` = add) with Details · Rooms tabs (Rooms is a placeholder until T4b); new shared `GalleryField` (several images, reorder, cover) and `TagInput`, both on `window.rtbp.ui`. **Framework fix:** `QueryBuilder::insert()/update()` bound NULL as `%s`, storing `''` (0 in numeric columns); NULL is now written as SQL NULL. `RoomType::size_m2` is no longer cast to float (a scalar cast reads NULL as 0). Verified: 23/23 `wp eval-file` checks (validation 422, create/patch/no-op, slug suffixes, delete guard 409, soft delete, events, NULL writes, staff 403), UI at desktop and 360 px (create, server error on field, reorder + cover + save, delete). **Follow-up:** `BaseModel::restore()` cannot work — its UPDATE goes through the soft-delete scope (`deleted_at IS NULL`), so it never matches the removed row; nothing calls it yet.
- T3 (2026-09-29): `#/rooms` = `src/modules/Rooms/index.jsx`, a card grid (1 → 2 → 3 columns): cover (featured, else first photo), readiness badge (new `readiness` status domain: `ready` / `no_rooms` / `hidden` when the type is inactive), guests and beds, room count with a badge per state, and "Rate plans: none yet" until M07 fills it. "Add room type" sits in the top bar (`usePageActions`) only when `room_types.manage` is not locked. Loading, error and empty states. No PHP change. Checked in the browser at desktop and 360 px with seeded types (then removed).
- T4a (2026-09-29): `Services\Inventory\FloorService` + `FloorController` (`floors` resource + `PUT floors/order`; `page.rooms` to read, `rooms.manage` to change; every write answers with the whole ordered list). Names unique ignoring case (≤ 100 chars); new floors go last; reorder needs every floor id exactly once (else 422) and runs in a transaction; a no-op rename or reorder emits nothing. Delete is a hard delete, refused with 409 `floor_has_rooms` while any room — removed ones included — is on the floor; it locks the floor row (`FloorRepository::lock()`, `SELECT … FOR UPDATE`) inside a transaction before counting. **For T4b:** creating or moving a room onto a floor must take the same floor-row lock (or `FOR SHARE`) in its transaction, or a delete can slip between the check and the insert. Screen `#/rooms/floors` (a "Floors" button in the Rooms & floors top bar): add box, rows with native drag-and-drop plus up/down arrows (optimistic, rolled back on error), rename in place, delete disabled with the reason while the floor holds rooms. Verified: 18/18 `wp eval-file` checks, UI at desktop and 360 px (add, duplicate error, move, drag, rename, delete).
- T4b (2026-09-29): `Services\Inventory\RoomService` + `RoomController` (`rooms` resource, `POST rooms/{id}/state`, `GET room-types/{id}/rooms`; reading `page.rooms`, changes `rooms.manage`). A floor is required. Numbers: up to 20 letters/digits/space/`- / .`, unique property-wide incl. removed rooms and ignoring case (the column collation); the 409 `room_number_taken` message names the owning type with the stored spelling ("Room A3 already belongs to Standard Room.") and says so when the owner was removed. PATCH changes number and/or floor only (type change = move, T5b). State: an `available` room carries no note. Remove: the filter `rtbp_room_future_bookings` (> 0 → 409 `room_has_future_bookings`), otherwise a soft delete. **Locking:** create locks the room type row and the floor row (`FOR UPDATE`) in a transaction and re-checks the number under the locks; `RoomTypeService::delete()` now also locks its row (new `RoomTypeRepository::lock()`), so neither delete can pass its "no rooms" check while a room is being added. `groupedByFloor()` = the `RoomPicker` shape, floors in order, rooms in natural order, rooms on a vanished floor last under id 0 "No floor". Hooks `rtbp_room_created|deleted|state_changed` (architecture §6). UI: the Rooms tab (floor panels with a count, tiles with number, state badge and reason, a menu with Change state / Rename or change floor / Remove; Add room at the top and per floor with the floor preset; "Add your floors first" when there are none). Verified: 30/30 `wp eval-file` checks, T1/T2/T4a scripts still pass, 4 parallel adds of one number → 1 win + 3 clean `room_number_taken`, UI at desktop and 360 px.
- T5a (2026-09-29): `RoomService::bulk()` + `POST rooms/bulk` (`rooms.manage`): `{ room_type_id, floor_id, prefix, from, to, pad, dry_run }`, at most 200 rooms per call, `pad` = zero-pad width 0–6 (the dialog's checkbox pads to the width of `to`), whole number ≤ 20 chars. The answer is `{ numbers: [ { number, status: new|taken, owner } ], create, skip, created }`; clashes compare case-insensitively (the column collation) and include removed rooms. Creating plans again under the room-type and floor locks, inserts in one transaction (all or nothing), and emits **one** `rooms.bulk_create` event (rooms + skipped numbers) plus `rtbp_room_created` per room. UI: "Bulk add" next to Add room (top and per floor, floor preset) → dialog with floor, prefix, from, to, pad → Preview (chips: new in green, taken struck through with "A3 already belongs to Room VIP") → "Add N rooms"; changing any field drops the stale preview. Verified: 16/16 `wp eval-file` checks; UI at desktop and 360 px (A1–A12 with A3 owned by VIP → 11 added, 1 skipped, natural order) — acceptance step 1.
- T5b (2026-09-29): `RoomService::move()` + `POST rooms/{id}/move` behind a **new access key `rooms.move`** (locked by default, open for managers like every key; Pro's `AdvancedAccessFeature::PASSCODE_DEFAULTS` now raises it to `passcode`, which is what the API table's "(passcode)" asks for — free cannot default to passcode). Without `confirm` it only answers the warning `{ moved: false, from, to, count, bookings }`; with `confirm` the client passes `bookings` = the count it showed, and a higher current count → 409 `move_needs_confirm` with the fresh list (a booking made after the warning is never moved silently). The move follows booking-engine case 23: upcoming bookings keep their lines and snapshot type; the room is busy under its new type. Refused (even the preview) while `rtbp_room_in_use` is true → 409 `room_in_use`. Upcoming bookings = max( `rtbp_room_future_bookings` count, count of `rtbp_room_future_booking_list` ); remove uses the same helper. The target type row is locked during the update. Event `rooms.move` (type ids + names + upcoming count) and hook `rtbp_room_moved`. UI: "Move to another room type" in the room menu (shown when `rooms.move` is not locked, even without `rooms.manage`) → dialog: choose type → Continue (warning box with the bookings, or "No upcoming bookings") → Move room. Verified: 16/16 `wp eval-file` checks with stand-in filters; T4b 30/30 again; browser with a temporary mu-plugin stand-in (removed): warning lists RTB-7, move succeeds, both types' lists and counts refresh; 360 px dialog fits. **For M02:** fill `rtbp_room_future_bookings`, `rtbp_room_future_booking_list` and `rtbp_room_in_use`.
- Module close (2026-09-29): **Acceptance** script automated through the REST API (`m06-accept`, 8/8 with Pro: A1–A12 natural order; A3 under VIP refused "Room A3 already belongs to Standard Room."; A3 → maintenance updates the detail and overview counts and the **stored** Pro log row shows before/after; deleting Floor #5 holding a room is refused). It exposed one bug, fixed: `Activity::subject()` labelled rooms with an empty string (models were labelled by reference/name/title) — it now also uses `number`. **Pro deactivated:** every M06 script passes (t1 18, t2 23, t4a 18, t4b 30, t5a 16, t5b 16, accept 7), Pro reactivated. **Plugin Check:** multi-line `throw`s now sit between `phpcs:disable`/`enable` (a one-line `phpcs:ignore` only covers the first line); no M06 file is flagged (the remaining findings predate M06, see M13 DoD). **Access map:** `page.rooms`, `rooms.manage`, `room_types.manage`, `rooms.move` (group `rooms`). No M06 settings (buffer default lives in Booking rules). Feature ids 6.1–6.13 all implemented; 6.2's "rates it sells" shows "none yet" until M07; 6.6's "shown in the reports" is M10.
- Critical review (2026-09-29, `rtbp-critical-reviewer`): no double-selling defect; ownership, uniqueness, create/bulk/guard locking, the `Transaction` wrapper, lock order and the QueryBuilder NULL change confirmed sound. Findings, all fixed: **(medium)** room remove counted future bookings without a lock → now inside a transaction behind a lock on the room row (`RoomRepository::lock()`, the lock booking writes take per booking-engine §7); **(medium)** move checked outside any room lock and acknowledged only a count (a cancelled-and-replaced booking could move unseen) → the confirm path locks the room, re-checks `rtbp_room_in_use` and the bookings under the lock, and the client sends `booking_ids` (any unseen id or a higher count → `move_needs_confirm` with the fresh list; `room_changed` if someone moved it meanwhile); **(medium)** a rename refused by the UNIQUE index reported success → `saveOrFail()` checks the write, a duplicate key → 409 `room_number_taken` (the re-check cannot see a row committed after the transaction's snapshot, so the duplicate-key error decides), anything else → 500, rollback; **(low)** bulk preview compared lower-case strings while the collation also folds accents/width (É1 = E1) → when the IN query finds any clash, each number is matched by the database (`findByNumberAny`, ≤ 200 lookups); **(low)** `existingNumbers()` dropped "0" → `array_filter( …, 'strlen' )`; **(low)** room-type create did not check the insert → 409 `room_type_not_saved`; **(low)** floor/type delete ignored the lock result → 404; **(low)** a lock timeout/deadlock looked like a missing row → shared `BaseRepository::lockRow()` throws 503 `busy`. Also: backstop writes run under `Support\Db::quietly()` so wpdb (display on with WP_DEBUG) cannot print HTML into a REST response. Verified: all scripts again (t5a 19, t5b 18 incl. the swap case and accent/"0" cases), and a 12-process race (2 renames + 2 adds of one number, 4 identical type names, a floor delete against 3 room adds on it): exactly one RN1, one type, no rooms on a deleted floor, losers get clean 409/422, no HTML leaked.
- 2026-10-04 (Free, follow-up requested by the user): **shared amenity library** replaces typing amenities per room type. Table `amenities` (DB 1.0.14, seeded once from the existing room type lists, case-insensitive), `AmenityService` (create, rename → every room type, rename onto an existing name → 409 `amenity_name_taken`, with `merge` → folded into it, reorder → room types follow the library order, delete → taken off every room type), `AmenityController` (`amenities` resource + `PUT amenities/order`; `page.rooms` to read, `room_types.manage` to change), activity `amenities.create|update|merge|delete|reorder`. `RoomTypeService` resolves saved names to the library spelling and order and adds unknown ones, so the client importer (which writes through it) fills the library with no add-on change. Screen `#/rooms/amenities` (Floors-style list showing the room types using each, merge confirm) and `AmenityPicker` on the room type form (checkbox grid, select all / clear, filter from 12, add new). Room types still store names, so the public room page and the resource are unchanged. Verified: 16/16 `wp eval-file` checks (test data removed; VVIP Room's order, re-sorted by the reorder check, restored), REST routes registered, browser: list, add, merge dialog, picker tick / discard, narrow width, no console errors. Not done: amenity icons on the public page.
- 2026-10-04 (Free): **Add common amenities**. `AmenityService::common()` returns 33 standard amenities in 5 groups (translatable, filter `rtbp_common_amenities`), each marked `added` when the library already holds it (ignoring case). `createMany()` adds the rest last in one transaction, skipping duplicates, and logs one `amenities.import` entry. Routes `GET amenities/common` (`page.rooms`) and `POST amenities/import` (`room_types.manage`). `CommonAmenitiesDialog`: all new ones ticked, existing ones shown as *Added* and disabled, select all / clear, *Add N amenities*. The button is in the Amenities panel header, and is the empty state's action on a new site. Verified: `wp eval` (grouping, `added` flags, duplicate / existing / empty skipped, a name over 60 characters → 422, test names removed), browser (dialog, clear + one tick → *Add 1 amenity* → toast + row added, reopened shows it as *Added*, no console errors; test row removed).
