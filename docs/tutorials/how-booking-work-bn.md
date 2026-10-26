# বুকিং কীভাবে কাজ করে: শুরু থেকে শেষ

এই টিউটোরিয়ালে একটা বুকিংকে শুরু থেকে শেষ পর্যন্ত অনুসরণ করা হয়েছে: গেস্ট যখন তারিখ বেছে নেয়,
সেখান থেকে বুকিং সেভ হওয়া পর্যন্ত। প্রতিটা ধাপে ডাটাবেস থেকে কী জিজ্ঞেস করা হয় তা দেখানো হয়েছে।
আর এটাও ব্যাখ্যা করা হয়েছে যে room type, rate plan, দাম আর রুম সবই হোটেল যেকোনো সময় বদলাতে
পারে এমন ডেটা হলেও ফলাফল কেন সবসময় সঠিক থাকে।

নিয়মগুলো নিজে লেখা আছে [`docs/project/booking-engine.md`](../project/booking-engine.md)-এ। এই পাতায়
দেখানো হয়েছে কোড সেই নিয়মগুলো কীভাবে প্রয়োগ করে। দুটোর মধ্যে অমিল থাকলে spec-টাই ঠিক, আর
কোডে bug আছে।

> শব্দ: **hold** = কিছু সময়ের জন্য রুম আটকে রাখা, **token** = একজন ভিজিটরের সব hold-এর চাবি,
> **buffer** = দুই স্টের মাঝে পরিষ্কারের সময়, **window** = শুরু থেকে শেষ পর্যন্ত নির্দিষ্ট সময়ের ঘর।

---

## ১. মূল ধারণা: হোটেল বিক্রি করে নির্দিষ্ট রুমের সময়ের window

ইঞ্জিন কখনো "রাত" হিসেবে চিন্তা করে না। সে চিন্তা করে **window** হিসেবে: একটা নির্দিষ্ট রুমে
একটা শুরুর মুহূর্ত আর একটা শেষের মুহূর্ত।

```
             08:30           17:00  20:00                       08:00 (+1)
Room A2  D   ├──── Half Day ───┤├Rest┤├────────── Overnight ──────────┤
Room A2  D+1                                                          ├── Half Day ──┤
```

চার ধরনের ডেটা থেকে প্রতিটা window আর প্রতিটা দাম তৈরি হয়:

| ডেটা | টেবিল | কী বলে |
|---|---|---|
| **Room type** | `room_types` | *Standard Room*: সর্বোচ্চ adult/child, cleaning buffer, ছবি |
| **Room** | `rooms` | *A2*: **একটাই** room type-এর (`room_type_id`), এর একটা floor আর একটা state আছে (`available`, `maintenance`, `out_of_service`) |
| **Rate plan** | `rate_plans` | আবার ব্যবহারযোগ্য একটা **window-এর রেসিপি**: *Half Day 08:30–17:00*, *Overnight 20:00→08:00*, *24 h flexible*। এর নিজের কোনো রুম নেই, দামও নেই |
| **Room type rate** | `room_type_rates` | "Room type X, plan Y বিক্রি করে P দামে" (sale price, min/max units, চালু/বন্ধ) |

রুম ব্যস্ত (busy) করে যা কিছু, সেগুলো আলাদা টেবিলে থাকে:

| Busy-এর উৎস | টেবিল | কখন রুম আটকে রাখে |
|---|---|---|
| Booking line | `booking_rooms` | status `pending`, `confirmed`, `checked_in`; আর `checked_out` শুধু `occupied_until_gmt` পর্যন্ত |
| Hold | `holds` | `expires_at_gmt` ভবিষ্যতে, আর hold-টা ভিজিটরের **নিজের নয়** |
| Block | `blocks` | রুমটা নিজে, তার room type, তার floor, অথবা পুরো property-কে ঢাকে |
| Room state | `rooms.state` | `available` ছাড়া অন্য যেকোনো state হলে রুম কোনো window-এই বিক্রি হয় না |

এর কোনোটাই কোডে hard-coded নয়। নতুন rate plan মানে `rate_plans`-এ নতুন একটা row; সেটা কোনো room
type-এ বিক্রি করা মানে `room_type_rates`-এ নতুন একটা row। ইঞ্জিন প্রতিটা search-এ row গুলো নতুন করে
পড়ে, তাই কোনো পরিবর্তন পরের request-এই দেখা যায়।

---

## ২. পুরো যাত্রা এক নজরে

```
 গেস্ট তারিখ বদলায়
        │
        ▼
 GET public/availability ─────────── AvailabilityService::search()        (শুধু পড়া, ৪টা query)
        │  room types → rates → window → নিয়ম → দাম → রুম ধরে ধরে
        ▼
 বুকিং ফর্মে rate দেখায় (দাম অথবা কারণ) আর রুমের grid (free / booked / held …)
        │
        │  গেস্ট একটা rate আর এক বা একাধিক রুম বেছে নেয়
        ▼
 POST public/holds ───────────────── HoldService::createMany()           (লেখা, রুম LOCK করা)
        │  BookingWriter::lockAndCheck() row lock নিয়ে সবকিছু আবার যাচাই করে
        │  `holds` row ঢোকায় (ডিফল্ট ১৫ মিনিট) → একটা token ফেরত দেয়
        ▼
 গেস্ট নিজের তথ্য পূরণ করে (hold-এর টাইমার চলতে থাকে)
        │
        ▼
 POST public/bookings ────────────── PublicBookingService → BookingService::create()
        │  রুম আবার LOCK, আবার যাচাই, আবার দাম হিসাব, গেস্ট ঠিক করা,
        │  booking + lines + invoice লেখা, hold মুছে ফেলা — সব এক transaction-এ
        ▼
 বুকিং পেজ + ই-মেইল (commit-এর পরে)
```

ফ্রন্ট ডেস্কও একই ইঞ্জিন ব্যবহার করে (`GET availability`, `POST holds`, `POST bookings`)। পার্থক্য শুধু
**audience**-এ: `public`-এর বদলে `staff`। audience ঠিক করে কয়েকটা নিয়ম, যেমন booking window, বা
যে walk-in এর স্টে ইতিমধ্যে শুরু হয়ে গেছে তার জন্য ৩০ মিনিটের ছাড়।

---

## ৩. ধাপ ১: গেস্ট তারিখ বদলায় (search)

### ৩.১ ব্রাউজার কী পাঠায়

বুকিং ফর্ম search-এর মানগুলো (arrival, departure, adults, children, flexible plan-এর জন্য check-in
time) React state-এ রাখে। `src/components/booking/api.js`-এর `useAvailability()` এগুলোকে একটা React
Query call বানায়:

```js
useQuery( {
    queryKey: [ ...AVAILABILITY_KEY, path, params ],   // search-টাই cache key
    queryFn: () => get( path, params ),
    placeholderData: ( previous ) => previous,         // লোড হওয়ার সময় আগের ফলাফল দেখাতে থাকো
    refetchOnWindowFocus: true,                         // রুম দ্রুত বিক্রি হয়: ট্যাবে ফিরলে আবার আনো
} );
```

তারিখ বদলালে `params` বদলায়, ফলে key বদলায় আর React Query নতুন করে ডেটা আনে:

```
GET /wp-json/radius-hotel-booking/v1/public/availability
    ?arrival=2026-10-28&departure=2026-10-28&adults=2&children=0&hold_token=…
```

`hold_token` পাঠানো হয় যাতে ভিজিটরের নিজের hold করা রুম তার নিজের কাছে "held" না দেখায়।

### ৩.২ `AvailabilityService::search()`: হোটেল যত বড়ই হোক, ৪টা query

ফাইল: `includes/Services/Availability/AvailabilityService.php`। পুরনো সিস্টেম প্রতিটা rate plan আর
প্রতিটা রুমের জন্য আলাদা query চালাত (N+1)। এটা সবসময় **৪টা** query চালায়, বাকিটা PHP memory-তে করে।

**প্রথমে request ঠিকঠাক করা** (`normalise()`): তারিখ যাচাই, `nights = departure − arrival` হিসাব, আর
গেস্টের সংখ্যা পড়া (child-এর বয়স সীমার বেশি হলে তাকে adult ধরা হয়)।

**Query ১: catalogue** (`AvailabilityRepository::catalogue()`)

```sql
SELECT t.*, r.*, p.*
  FROM room_types t
  LEFT JOIN room_type_rates r ON r.room_type_id = t.id
  LEFT JOIN rate_plans p      ON p.id = r.rate_plan_id AND p.deleted_at IS NULL
 WHERE t.deleted_at IS NULL AND t.is_active = 1
 ORDER BY t.sort_order, t.id, r.sort_order, r.id
```

প্রতিটা (room type × rate)-এর জন্য একটা row। `catalogueTypes()` row গুলোকে
`types[type_id]['rates'][plan_id]` আকারে সাজায়। বন্ধ থাকা rate, বা যার plan inactive, সেটা বাদ দেওয়া
হয়। তবে request যদি ঠিক সেই plan-টাই চায়, তাহলে রেখে দেওয়া হয় আর `rate_disabled` কারণ দেখানো হয়।

"সবকিছু dynamic" ব্যাপারটা এখানেই সামলানো হয়: room type-এর তালিকা আর প্রতিটা type কোন কোন plan বিক্রি
করে, তা **এই query-র ফলাফলই**।

**Window তৈরি (memory-তে, কোনো query নেই)**

প্রতিটা room type × rate-এর জন্য `candidate()` **units** ঠিক করে আর
`StayWindow::for()` (`includes/Services/Availability/StayWindow.php`) দিয়ে window বানায়:

| Plan type | Arrival তারিখ `D` আর `n` units হলে window |
|---|---|
| `fixed` (`start_time`, `end_time`) | শুরু = `D @ start_time`; দৈর্ঘ্য = `(end − start) mod 24 h` (0 মানে 24 h); শেষ = শুরু + দৈর্ঘ্য + `(n − 1)` দিন |
| `flexible` (`duration_minutes`, `checkin_from`–`checkin_until`) | শুরু = `D @ বেছে নেওয়া check-in`, যা অনুমোদিত সীমার মধ্যে আর ৩০ মিনিটের ধাপে হতে হবে; শেষ = শুরু + duration + `(n − 1)` দিন |

- **multi-unit** plan-এর (overnight, 24 h) জন্য `n = max(1, nights)`। single-stay plan-এর (half day,
  rest time) জন্য সবসময় 1।
- single-stay plan ৩ রাতের search-এ খাপ খায় না। সেটা `incompatible_span` কারণ পায় আর "অন্য স্টে অপশন"-এ
  দেখানো হয়, unavailable হিসেবে নয়।
- **মধ্যরাতের জন্য আলাদা কিছু লাগে না।** `20:00 → 08:00`-এর দৈর্ঘ্য সোজা 12 ঘণ্টা।
- হিসাবটা wall clock (দেয়াল ঘড়ির সময়)-এ করা হয়, তারপর সাইটের time zone-এ পড়া হয়। তাই DST বদলালেও
  "12 ঘণ্টার" স্টে কখনো 11 বা 13 ঘণ্টা হয়ে যায় না। local আর GMT দুটো সময়ই রাখা হয়, আর **প্রতিটা তুলনা
  GMT-তে হয়**।

Window বানানোর সময় search একটা **envelope**-ও হিসাব করে: সব candidate window-এর সবচেয়ে আগের শুরু আর
সবচেয়ে পরের শেষ, cleaning buffer আর পুরো local দিন পর্যন্ত বাড়ানো।

**Query ২: রুমগুলো** (`AvailabilityRepository::rooms()`)

```sql
SELECT r.id, r.room_type_id, r.floor_id, r.number, r.state, r.state_note, f.name
  FROM rooms r LEFT JOIN floors f ON f.id = r.floor_id
 WHERE r.room_type_id IN ( …query ১-এর types… ) AND r.deleted_at IS NULL
 ORDER BY f.sort_order, r.floor_id, r.number_sort, r.id
```

রুম বাছাই করা হয় **room type ধরে**। এ কারণেই Standard-এর rate কখনো VIP রুমে বিক্রি হতে পারে না:
VIP রুমগুলো Standard-এর তালিকাতেই নেই।

**Query ৩: envelope-এর মধ্যে ওই রুমগুলোকে যা কিছু ব্যস্ত রাখে** (`AvailabilityRepository::busy()`)

একটাই `UNION ALL`, `(room_id, start_at_gmt, …)` index দিয়ে:

```sql
-- যে booking line এখনো রুম আটকে রাখে
SELECT br.room_id, br.start_at_gmt AS s, br.occupied_until_gmt AS e, 'line', br.booking_id, bk.reference
  FROM booking_rooms br LEFT JOIN bookings bk ON bk.id = br.booking_id
 WHERE br.room_id IN (…) AND br.status IN ('pending','confirmed','checked_in','checked_out')
   AND br.start_at_gmt < :to AND br.occupied_until_gmt > :from
UNION ALL
-- অন্য ভিজিটরদের চালু hold
SELECT h.room_id, h.start_at_gmt, h.end_at_gmt, 'hold', h.id, ''
  FROM holds h
 WHERE h.room_id IN (…) AND h.expires_at_gmt > :now AND h.token <> :my_token
   AND h.start_at_gmt < :to AND h.end_at_gmt > :from
UNION ALL
-- রুম, তার type, তার floor বা পুরো property-র ওপর block
SELECT r.id, b.start_at_gmt, b.end_at_gmt, 'block', b.id, b.reason
  FROM blocks b JOIN rooms r ON ( b.scope = 'property'
                               OR (b.scope = 'room'      AND b.scope_id = r.id)
                               OR (b.scope = 'room_type' AND b.scope_id = r.room_type_id)
                               OR (b.scope = 'floor'     AND b.scope_id = r.floor_id) )
 WHERE r.id IN (…) AND b.start_at_gmt < :to AND b.end_at_gmt > :from
```

ফলাফল হয় `busy[room_id] = [ {s, e, kind, ref_id, label}, … ]`, শুরুর সময় অনুযায়ী সাজানো।

যে খুঁটিনাটি গুরুত্বপূর্ণ:
- `occupied_until_gmt` হলো স্টে-র শেষ, অথবা গেস্ট আগে check-out করলে আসল check-out-এর সময়। আগে
  check-out করলে রুম সেই মুহূর্ত থেকেই খালি হয়।
- "এখন" (now) আসে PHP থেকে (`Dates::now()`), MySQL-এর ঘড়ি থেকে নয়। ফলে কোন hold মেয়াদ পেরিয়েছে, সে
  ব্যাপারে সব সার্ভার একমত থাকে।
- এই query ব্যর্থ হলে `503 busy` throw করে। পড়া ব্যর্থ হওয়াকে কখনো "কিছুই বুক করা নেই" ধরা যাবে না।

**Query ৪: rate calendar** (`RateCalendar::prime()`)

ওই room type গুলোর জন্য, যে যে তারিখে কোনো unit শুরু হয় সেগুলোর `rate_calendar` row: বন্ধ তারিখ (পুরো
room type, বা একটা rate) আর দামের override। এখান থেকে `RateCalendar::closure()` আর `::override()`
memory থেকেই উত্তর দেয়।

`OccupancyCalculator::primeFrom()` query ৩-এর busy তালিকা থেকেই প্রতিটা room type-এর দিনভিত্তিক
occupancy গুনে রাখে। Pro-র occupancy pricing আর কোনো query না চালিয়েই এই হিসাব পড়ে নেয়।

### ৩.৩ সিদ্ধান্ত, memory-তে

প্রতিটা room type-এর, প্রতিটা rate-এর জন্য:

**ক. নিয়ম যাচাই** (`ruleReason()`, এই ক্রমে; প্রথম যেটায় আটকায় সেটাই কারণ হিসেবে দেখানো হয়)

| কারণ | কখন আটকায় |
|---|---|
| `past` | window ইতিমধ্যে শুরু হয়ে গেছে (staff ৩০ মিনিট ছাড় পায়) |
| `booking_window` | শুধু public: arrival, Settings → Booking যত দিন আগে পর্যন্ত অনুমতি দেয় তার চেয়েও পরে |
| `same_day_cutoff` | শুধু public: arrival আজ, আর same-day booking বন্ধ বা cut-off পেরিয়ে গেছে |
| `rate_disabled` | rate বন্ধ বা plan inactive |
| `room_type_closed` / `rate_closed` | rate calendar স্টে-র কোনো তারিখ বন্ধ রেখেছে |
| `min_units` / `max_units` | multi-unit স্টে rate-এর অনুমোদিত সীমার চেয়ে ছোট বা বড় |
| `occupancy` | room type যত adult বা child নেয় তার চেয়ে বেশি |
| `no_rooms` | room type-এর কোনো রুম `available` state-এ নেই |

**খ. দাম**: শুধু নিয়মগুলো পার হলে (নিচে §৫)।

**গ. রুম ধরে ধরে**: **এই type-এর প্রতিটা রুমের** জন্য `Overlap::roomReason()`:

```php
if ( room.state !== 'available' )            → কারণ = 'maintenance' / 'out_of_service'
foreach ( busy[room] as interval ) {
    if ( overlaps( window, interval ) )        → 'booked' | 'held' | 'blocked'   (আসল কারণই আগে)
    if ( overlaps( window, interval, buffer ) )→ 'buffer'  (পরিষ্কারের সময় পেরোলে তবেই খালি)
}
নাহলে                                         → এই rate-এর জন্য খালি
```

সব জায়গায় একটাই সূত্র ব্যবহার হয়:

```
overlaps(a, b) :=  a.start < b.end + buffer   AND   b.start < a.end + buffer
```

Interval গুলো **half-open** (`[start, end)`): যে স্টে 17:00-এ শেষ আর যেটা 17:00-এ শুরু, তারা ধাক্কা খায়
না। cleaning buffer (room type থেকে, না থাকলে Settings → Booking থেকে) দুটো স্টে-রই শেষে যোগ হয়। block-এ
কোনো buffer নেই: block হলো বন্ধ রাখা, কোনো স্টে নয়।

খালি রুম তার `available_for` তালিকায় plan-টা যোগ করে। ব্যস্ত রুম `reasons_by_rate[plan]`-এ একটা কারণ পায়।
type-এর কোনো রুমই খালি না থাকলে rate-এর কারণ হয় `fully_booked`।

### ৩.৪ ব্রাউজার কী পায়, আর grid কীভাবে আঁকা হয়

```jsonc
{
  "span": { "arrival": "2026-10-28", "departure": "2026-10-28", "nights": 0 },
  "room_types": [{
    "id": 1, "name": "Standard Room", "rooms_total": 12, "rooms_sellable": 12,
    "rates": [{
      "rate_plan_id": 3, "name": "24 Hours Fixed Stay",
      "window": { "start": "2026-10-28T08:30:00+00:00", "end": "2026-10-29T08:00:00+00:00" },
      "units": 1, "available": true, "free_count": 11,
      "price": { "total": 20000, "steps": [ … ] }, "reasons": []
    }],
    "floors": [{ "name": "Ground floor", "rooms": [
      { "id": 11, "number": "A1", "available_for": [3] },
      { "id": 12, "number": "A2", "available_for": [], "reasons_by_rate": { "3": { "code": "held" } } }
    ]}]
  }],
  "other_options": [ … যে plan গুলো span-এ খাপ খায় না … ]
}
```

ফর্মের যা দরকার, এখানে সব আছে:
- **rate card** `available` হলে `price.total` দেখায়, নাহলে প্রথম কারণের মেসেজ।
- বেছে নেওয়া rate-এর **রুম grid**-এ প্রতিটা রুম *Free* দেখায় যদি plan-টা তার `available_for`-এ থাকে,
  নাহলে `reasons_by_rate`-এর code দিয়ে (booked, held, blocked, buffer, maintenance…)।
- public response-এ কখনো booking reference, block-এর কারণ বা গেস্টের তথ্য থাকে না। staff response-এ
  booking reference যোগ হয়, যাতে ডেস্ক সেটা খুলতে পারে।

একটা response-এই ওই তারিখের সব room type, rate আর রুম থাকে, তাই তারিখ বদলালে একবারের request-এই grid
আপডেট হয়ে যায়।

---

## ৪. ধাপ ২: গেস্ট রুম বেছে নেয় (hold)

**Hold 1 room**-এ ক্লিক করলে `POST public/holds` যায়, সাথে রুমের id, plan, arrival, units আর গেস্টের
সংখ্যা। `HoldService::createMany()` (`includes/Services/Availability/HoldService.php`) চালায়:

```
Transaction::run( function () {
    checked = BookingWriter::lockAndCheck( requests, token, audience )   // §৬, রুম lock করা
    প্রতিটা checked রুমের জন্য:
        একই রুম + window আগেই এই token-এ hold করা? → সেই hold-ই রাখো
        নাহলে                                       → INSERT INTO holds (…, token, expires_at_gmt)
    token-এর সব hold-এর মেয়াদ একসাথে শেষ হয়
} )
```

- **token** একজন ভিজিটরের সব hold একসাথে রাখে। ব্রাউজার সেটা রেখে দেয় (`useHolds()`,
  `src/components/booking/holds.js`) আর পরের প্রতিটা search, hold আর booking-এর সাথে পাঠায়।
- একটা hold `holdMinutes` (Settings → Booking, ডিফল্ট ১৫) মিনিট চলে। ওয়েবের token বাড়ানো যায়, কিন্তু
  প্রথম hold থেকে ৩টা hold-সময়ের বেশি কখনো নয়।
- ওয়েবের hold ভিজিটরের session cookie আর network-এর সাথে বাঁধা। একজন ভিজিটর একসাথে সর্বোচ্চ
  `maxRoomsWeb`টা রুম hold করতে পারে।
- **মেয়াদ পেরোনো hold সাথে সাথে উপেক্ষা করা হয়** প্রতিটা query-তে (`expires_at_gmt > now`)। cron পরে
  row গুলো মুছে দেয়; সেটা শুধু পরিষ্কার রাখার কাজ।

এই মুহূর্ত থেকে অন্য প্রতিটা ভিজিটরের search-এ রুমটা `held` দেখায়। আপনার নিজের search-এ সেটা আপনারই
থাকে, কারণ query ৩-এ আপনার token বাদ দেওয়া হয়।

---

## ৫. দাম কীভাবে হিসাব হয়

`PriceResolver::price()` / `::quote()` (`includes/Services/Pricing/PriceResolver.php`) প্রতিটা unit (প্রতি
রাত বা দিন)-এর জন্য **ধাপের একটা pipeline** চালায়, তারপর unit গুলো যোগ করে:

| ক্রম | ধাপ | কোথা থেকে আসে |
|---|---|---|
| 10 | Base price | `room_type_rates.price` |
| 20 | Sale | `sale_price` সেট থাকলে আর কম হলে (NULL = কোনো sale নেই; 0 মানে সত্যিই ফ্রি) |
| 30 | Date override | ওই তারিখের rate calendar-এর `price_override` (`rtbp_price_date_override`) |
| 40 | Seasonal | **Pro**, `rtbp_price_steps` দিয়ে যোগ হয় |
| 50 | Occupancy | **Pro**: সেদিন room type-এর occupancy, আগে গুনে রাখা হিসাব থেকে |
| 60 | Early bird / last minute | **Pro** |
| 90 | Floor and round | `max(0, x)`, মুদ্রার নিয়মে round করা |

প্রতিটা ধাপ `{ step, label, before, after }` লিখে রাখে। এই তালিকাটাই "দাম কেন এত?"-এর হিসাব, আর বুকিং
তৈরি হওয়ার সময় সেটা booking line-এ জমিয়ে (frozen) রাখা হয়। পরে নিয়ম বদলালেও পুরনো বুকিংয়ের দাম আর
বদলায় না।

Search আর write path **একই** pipeline ডাকে, তাই ডেস্ক আর ওয়েবসাইট সবসময় একই দাম বলে। দুই জায়গাতেই
occupancy গোনা হয় ভিজিটরের নিজের hold বাদ দিয়ে, তাই রুম hold করলে তার নিজের দাম লাফিয়ে বাড়ে না।

---

## ৬. নিরাপত্তার জাল: `BookingWriter::lockAndCheck()`

ফাইল: `includes/Services/Booking/BookingWriter.php`। রুম ব্যস্ত করে এমন প্রতিটা পথ এর মধ্য দিয়ে যায়:
hold, booking, line যোগ বা এডিট, check-in-এ রুম বদল, import।

Search (§৩) শুধু পেজ লোডের মুহূর্তের একটা **ছবি**। সেই মুহূর্ত আর ক্লিকের মাঝখানে অন্য কোনো গেস্ট বা ডেস্ক
রুমটা নিয়ে নিতে পারে। তাই write path কখনো search-এর ওপর ভরসা করে না। সে পুরো যাচাই আবার করে,
**একটা ডাটাবেস transaction-এর ভেতরে, রুমগুলো lock করে**:

```
1. SELECT … FROM rooms WHERE id IN (…) ORDER BY id FOR UPDATE      ← row lock, id-এর ছোট থেকে বড় ক্রমে
2. প্রতিটা চাওয়া (room, plan, arrival, units)-এর জন্য:
     - রুম এখনো আছে আর এখনো ওই room type-এরই                    ('removed', 'moved')
     - দাম আবার হিসাব (যে rate আর বিক্রি হয় না বা units সীমার বাইরে, তাও আটকায়)
     - StayWindow::for() দিয়ে window আবার বানানো
     - রুমের state 'available'
     - অতীতে নয়; rate calendar তারিখগুলো বন্ধ রাখেনি
     - public booking window / same-day cut-off; গেস্টের সীমা
     - দেখানো দাম (`expected_total`) এখনো মেলে → নাহলে 409 price_changed + নতুন মোট দাম
3. একই call-এর দুটো request একই রুমে একে অপরের সাথে overlap করতে পারবে না
4. AvailabilityRepository::conflicts() → আবার busy() + Overlap::conflict()  ← search-এর একই সূত্র
5. কোনো conflict হলে → 409 room_unavailable (রুমের নাম সহ); কিছুই লেখা হয় না
```

কেন এটা নির্ভরযোগ্য:
- **Row lock-টাই আসল গ্যারান্টি।** MySQL "দুটো স্টে overlap করবে না" এমন কোনো constraint রাখতে পারে
  না। একই রুমের জন্য দুটো request `FOR UPDATE`-এ লাইনে দাঁড়ায়: দ্বিতীয়টা অপেক্ষা করে, তারপর busy তালিকা
  আবার পড়ে আর প্রথমটার hold বা booking দেখতে পায়।
- **id-এর ছোট থেকে বড় ক্রম** মানে রুম {3, 7} আর {7, 3} lock করতে চাওয়া দুই writer কখনো deadlock-এ পড়ে না।
- **READ COMMITTED**: `Transaction::run()` এই isolation level সেট করে, ফলে lock-এর জন্য অপেক্ষার পরের
  পড়ায় আগের lock-ধারী এইমাত্র যা commit করেছে তা দেখা যায়। MySQL-এর ডিফল্ট REPEATABLE READ-এ এটা পুরনো
  snapshot পড়তে পারত। এটা থাকায় শেষ রুমের জন্য একসাথে দুটো বুকিং এলে ঠিক একটা সফল হয়, অন্যটা 409 পায়।
- **একটাই সূত্র।** Search আর write path দুটোই `busy()` + `Overlap` ব্যবহার করে, তাই দুই জায়গায় "খালি"-র
  অর্থ একই।

---

## ৭. ধাপ ৩: গেস্ট নিশ্চিত করে (booking)

`POST public/bookings` যায় `PublicBookingService::create()`-এ। এটা গেস্ট কে তা ঠিক করে (signed in,
Express checkout, তথ্য মিলিয়ে চেনা পুরনো গেস্ট, অথবা নতুন) আর settings দেখে (consent, required field),
তারপর `BookingService::create()` (`includes/Services/Booking/BookingService.php`) ডাকে:

```
কোনো lock নেওয়ার আগে (দ্রুত উত্তর):
  - hold token এই ভিজিটরেরই
  - public: token-এ এখনো চালু hold থাকতে হবে, নাহলে 410 hold_expired

Transaction::run( function () {
  1–3  lockAndCheck( lines, token )           // রুম lock, সবকিছু আবার যাচাই আর দাম আবার হিসাব
       public: প্রতিটা line-কে token-এর কোনো চালু hold-এর সাথে মিলতে হবে (একই রুম আর window),
               নাহলে 409 hold_mismatch — ভিজিটর hold করেনি এমন রুম বুক করতে পারে না
       token-এর নিজের hold কোনো conflict নয় (সেগুলো ভিজিটরেরই)
  4    গেস্ট: পুরনো রেকর্ড, অথবা এখনই (lock-এর ভেতরে) তৈরি নতুন রেকর্ড
       banned গেস্ট → 409, কিছুই লেখা হয় না
  5    INSERT bookings      reference RT-2026-000123 একটা ক্রমিক sequence থেকে,
                            status pending (manual approval) অথবা confirmed, মোট হিসাব
       INSERT booking_rooms প্রতিটা রুমের জন্য একটা row: রুম, plan, শুরু/শেষ (local + GMT),
                            occupied_until_gmt = শেষ, unit price, total, জমানো দামের হিসাব
       payment deadline, invoice নম্বর (একই transaction-এ: rollback হলে নম্বর ফেরত যায়)
       token-এর hold গুলো DELETE
       rtbp_activity( 'bookings.create' )
} )
commit-এর পরে: rtbp_booking_created → ই-মেইল, notification, counter
```

কিছু throw করলে পুরো transaction rollback হয়: কোনো বুকিং নেই, কোনো line নেই, কোনো নতুন গেস্ট নেই, কোনো
invoice নম্বর নেই, আর hold গুলো জায়গামতো থাকে। এক বুকিংয়ে একাধিক রুম থাকতে পারে। তিন রুমের বুকিংয়ে
তৃতীয় রুম এর মধ্যে অন্য কেউ নিয়ে নিলে **কিছুই** লেখা হয় না।

নতুন `booking_rooms` row-গুলোর status রুম আটকে রাখার মতো, তাই পরের search থেকেই query ৩ ওই রুমগুলোকে
overlap হওয়া যেকোনো window-এর জন্য `booked` হিসেবে ফেরত দেয়।

---

## ৮. রুম আবার কীভাবে খালি হয়

| ঘটনা | ফল |
|---|---|
| Hold-এর মেয়াদ শেষ | সাথে সাথে উপেক্ষা (`expires_at_gmt > now`); cron row মুছে দেয় |
| গেস্ট রুম সরায় / চলে যায় | `DELETE public/holds/{token}` সাথে সাথে ছেড়ে দেয় |
| বুকিং cancelled / declined, no-show | একই transaction-এ line রুম আটকে রাখা status থেকে বেরিয়ে যায় |
| আগে check-out | `occupied_until_gmt = now`; তখন থেকে রুম খালি (+ buffer) |
| Payment deadline পেরিয়েছে, auto-release চালু | cron বুকিং cancel করে, গেস্টকে ই-মেইল যায় |
| Overstay (শেষ সময়ের পরেও checked in) | নিজে থেকে **ছাড়া হয় না**: check-out পর্যন্ত রুম আটকে থাকে |

---

## ৯. একটা উদাহরণ

Settings: buffer 0 মিনিট। Standard Room-এর রুম A1–A12, আর এটা বিক্রি করে *Half Day 08:30–17:00*
(single stay), *Rest Time 17:00–20:00* (single stay) আর *24 h Fixed 08:30→08:00* (multi-unit)।
A2-তে ২৮ অক্টোবর একটা confirmed Half Day বুকিং আছে। আরেকজন ভিজিটর ২৮ অক্টোবর থেকে 24 h Fixed-এর জন্য
A3 hold করে রেখেছে।

একজন গেস্ট search করে **২৮ অক্টো → ২৮ অক্টো, ২ জন adult** (`nights = 0`):

| Rate | Window | নিয়ম | A1 | A2 | A3 | ফলাফল |
|---|---|---|---|---|---|---|
| Half Day | ২৮ 08:30 → ২৮ 17:00 | পাস | খালি | `booked` (একই window) | `held` (08:30–08:00-এর সাথে overlap) | available, ১০টা খালি |
| Rest Time | ২৮ 17:00 → ২৮ 20:00 | পাস | খালি | **খালি** (17:00 = Half Day-এর শেষ, half-open) | `held` | available, ১১টা খালি |
| 24 h Fixed | ২৮ 08:30 → ২৯ 08:00 | পাস | খালি | `booked` | `held` | available, ১০টা খালি |

buffer ৩০ মিনিট হলে Rest Time-এর জন্য A2 `buffer` দেখাত: তার Half Day 17:00-এ শেষ, তাই রুম তৈরি হয়
17:30-এ।

গেস্ট A2-তে Rest Time বেছে নেয় → `POST public/holds` → A2 lock → আবার যাচাই (এখনো খালি) → hold row।
দশ মিনিট পরে নিশ্চিত করে → A2 lock → আবার যাচাই (নিজের hold conflict নয়) → বুকিং `RT-2026-…`, line A2
17:00→20:00, hold মুছে ফেলা।

একই search **২৮ → ৩১ অক্টো** (`nights = 3`) দিলে 24 h Fixed ×3 আসে (শেষ ৩১ অক্টো 08:00), আর Half Day ও
Rest Time *অন্য স্টে অপশন*-এ `incompatible_span` হিসেবে দেখায়।

---

## ১০. সবকিছু configurable হলেও এটা কেন সঠিক থাকে

1. **আচরণ আসে field থেকে, নাম থেকে কখনো নয়।** plan-এর window আসে `type`, `start_time`/`end_time` বা
   `duration_minutes`, `checkin_from`/`until` আর `multi_unit` থেকে। নামে "24" আছে কিনা, এমন কিছু কোথাও
   দেখা হয় না। নতুন plan কোনো কোড ছাড়াই কাজ করে।
2. **Inventory রুমের type অনুসরণ করে।** রুম সবসময় `room_type_id` দিয়ে বাছাই হয়, তাই একটা rate শুধু
   নিজের type-এর রুমেই বিক্রি হতে পারে। রুমকে অন্য type-এ সরালে তার পুরনো বুকিং সাথে যায়। নতুন type
   সেগুলোকে busy দেখে, আর সরানোর আগে করা hold `moved` কারণ দিয়ে ব্যর্থ হয়।
3. **একটাই overlap সূত্র** (`Overlap::overlaps`), GMT timestamp-এ, half-open, buffer সহ, search আর write
   path দুটোতেই।
4. **কম খরচে পড়া, সাবধানে লেখা।** search মাত্র ৪টা query, আর একটু পুরনো হতে পারে। write path রুম lock
   করে সব যাচাই আবার করে, তাই পুরনো স্ক্রিন শুধু একটা পরিষ্কার 409 (`room_unavailable`,
   `price_changed`, `rate_closed`…) দিতে পারে, কখনো double booking নয়।
5. **সময় একবারই সামলানো হয়।** `StayWindow` সাইটের time zone-এ wall clock ধরে window বানায় আর local ও
   GMT দুটোই রাখে। অন্য কোথাও মধ্যরাত বা DST-এর জন্য আলাদা কিছু লাগে না।
6. **সন্দেহ হলে বন্ধ (fail closed)।** availability পড়া ব্যর্থ হলে `503 busy` throw করে; কখনো "খালি" ধরে
   নেয় না।
7. **দাম একটা pipeline, যার হিসাব লিখে রাখা হয়।** সেটা booking line-এ জমানো থাকে, আর Pro
   `rtbp_price_steps` দিয়ে free plugin না ছুঁয়েই এটা বাড়ায়।

---

## ১১. কোডে কোথায় দেখবেন

| অংশ | ফাইল |
|---|---|
| Endpoint | `includes/Routes/routes.php`: `public/availability`, `public/holds`, `public/bookings` (`PublicBookingController`); ডেস্কের `availability` (`AvailabilityController`), `holds` (`HoldController`) |
| Search | `includes/Services/Availability/AvailabilityService.php` → `search()` |
| ৪টা query, lock | `includes/Repositories/AvailabilityRepository.php` |
| Window | `includes/Services/Availability/StayWindow.php` |
| Overlap আর রুমের কারণ | `includes/Services/Availability/Overlap.php` |
| বন্ধ তারিখ, দামের override | `includes/Services/Availability/RateCalendar.php` |
| দিনভিত্তিক occupancy | `includes/Services/Availability/OccupancyCalculator.php` |
| Hold | `includes/Services/Availability/HoldService.php`, `src/components/booking/holds.js` |
| Lock-এর ভেতরে আবার যাচাই | `includes/Services/Booking/BookingWriter.php` → `lockAndCheck()` |
| বুকিং লেখা | `includes/Services/Booking/BookingService.php` → `create()` |
| ওয়েবসাইটের বুকিং (গেস্ট, Express checkout, settings) | `includes/Services/Booking/PublicBookingService.php` |
| দাম | `includes/Services/Pricing/PriceResolver.php` (+ Pro-র `Features/Pricing`) |
| বুকিং ফর্ম | `src/components/booking/` (`BookingFlow.jsx`, `GuestBookingSteps.jsx`, `api.js`) |
| নিয়ম, edge case আর checklist | `docs/project/booking-engine.md` |
