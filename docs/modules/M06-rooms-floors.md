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
| 6.3 | Floors: a named, ordered list (*Ground Floor, Floor #1…*), drag to reorder, no 0–4 limit; a floor holding rooms cannot be deleted | T1 |
| 6.1, 6.12, 6.13 | Room types: name (FR/EN), description, short description, photos (gallery), amenities (a tag list), bed info, size m², max adults, max children, buffer minutes (optional override), active, sort order | T2 |
| 6.2 | Room type overview cards: room count by state, readiness (`ready` / `no_rooms`), the rate plans it sells (after M07, a placeholder before) | T3 |
| 6.4, 6.5 | Rooms per type: add, rename, remove; each on exactly one floor | T4 |
| 6.6 | Room state: `available` / `maintenance` / `out_of_service` + a note. Non-available rooms are excluded from sale (the engine reads the state) | T4 |
| 6.7 | Natural sort: `number_sort` generated on save (`A9` < `A12`, `B2` < `B10`) | T1 |
| 6.8, 6.9 | One room type per room (the `rooms.room_type_id` column; there is no join table) + a UNIQUE `number` constraint property-wide | T1 |
| 6.10 | Bulk create: floor + prefix + from/to range (+ zero-pad) → preview → create; clashes with existing numbers are listed and skipped | T5 |
| 6.11 | Move a room to another type: warns with the count and list of future bookings; past bookings keep their snapshot type; refused while the room is in use | T5 |
| — | Removing a room with future bookings is refused; one with only past bookings is soft-deleted (history intact) | T4 |
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

- [ ] T1 [Free] `floors`, `room_types`, `rooms` tables (UNIQUE number, `number_sort`) + models / repos / services + `NaturalSort` with tests; register the access keys and log actions
- [ ] T2 [Free] Room type CRUD API + detail form (gallery via the WP media library, FR/EN fields, amenities tags); delete guard
- [ ] T3 [Free] Room-type overview cards screen
- [ ] T4 [Free] Floors screen + the Rooms tab (grouped by floor, add / rename / state / remove with guards)
- [ ] T5 [Free] Bulk create with preview + move room between types with the future-booking warning (the guard becomes live once M02 writes bookings; test it with fixtures)

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
