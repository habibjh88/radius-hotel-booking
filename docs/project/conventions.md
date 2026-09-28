# Engineering conventions and Definition of Done

Every module follows these rules. `/next-task` checks them before it marks a task done.
Framework details (layers, DI, REST envelope, i18n, Tailwind scoping) are in the root
`CLAUDE.md` and are not repeated here.

---

## 1. Naming

| Thing | Convention | Example |
|---|---|---|
| PHP namespace | `RadiusTheme\RadiusHotelBooking\<Layer>\<Domain>` | `Services\Booking\AvailabilityService` |
| Table | `rtbp_table_prefix()` + plural snake case | `…radius_hotel_booking_booking_rooms` |
| Model | Singular StudlyCase | `BookingRoom` |
| Option | `rtbp_<section>_settings` | `rtbp_booking_settings` |
| Hook | `rtbp_<domain>_<event>` | `rtbp_booking_status_changed` |
| Capability (WP) | `rtbp_<verb>_<noun>` | `rtbp_view_dashboard` |
| Access key (Module 13) | `<area>.<action>` | `bookings.approve` |
| Activity action | the same key as the access key where one exists | `bookings.approve` |
| REST route | plural kebab case, nested for children | `bookings/{id}/payments` |
| React module folder | StudlyCase under `src/modules/` | `src/modules/FrontDesk/` |
| JS API file | `src/modules/<Module>/api.js`, one per module | |

Domain services live in sub-folders by domain (`Services/Booking`, `Services/Pricing`,
`Services/Payment`) once a domain has more than two classes.

## 1b. Which plugin (ADR-014)

Each task in a module doc is tagged `[Free]`, `[Pro]`, `[Client]` or a combination. Write the code
in that repo only:

- `[Free]` → this repo. It must not contain Pro or client code, not even disabled. Never write
  `if ( rtbp_addon_active() )` around a feature. When a Pro task needs a hook, add the hook here
  first (ADR-015).
- `[Pro]` → `../radius-hotel-booking-pro`. `[Client]` → `../radius-hotel-booking-residencetata`.
  They reuse the free framework and `window.rtbp`, and guard every free symbol with
  `class_exists()` / `function_exists()`. They never copy free code.
- Each repo has its own `readme.txt` changelog, `DBVERSION`, text domain and git branch (the same
  branch name as the module).

## 2. Backend rules

1. **Controllers are thin.** Validate, check access, call a service, shape the response
   through a Resource. No SQL and no business rules in a controller.
2. **Services own the rules; repositories own the SQL.** A service may call several
   repositories. A repository never calls a service.
3. **Every write that touches inventory or money runs in a transaction**
   (`Core\Database\Transaction::run()`) and follows `docs/project/booking-engine.md` §7.
   Side effects such as e-mails and hooks go in `Transaction::afterCommit()`. Reference numbers
   come from `Support\Sequence::next()` **inside** that transaction, which keeps them gap-free.
   Business-rule failures throw `Exceptions\DomainException`. Its message is translated with
   `__()`, not `esc_html__()`: it is sent as JSON and React escapes it, so HTML entities would
   show up (`n&#039;a`). Put `// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
   -- sent as JSON; React escapes it (phpcs.xml).` on the line above each such `throw`. Plugin
   Check applies that sniff even though `phpcs.xml` excludes it.
4. **All SQL through `$wpdb->prepare()`**, or the QueryBuilder, which prepares. Build `IN (…)`
   lists with placeholders. Never interpolate a request value.
5. **No N+1 queries.** Load related rows in one query before a loop (`whereIn`), then group
   in PHP.
6. **Money** is `DECIMAL(12,2)` in the database and a PHP `float` only at the edges. Format with
   `Money::format()`. XOF has no minor unit: round to whole francs before storing.
7. **Dates** are stored as `DATETIME` in site-local time (Africa/Abidjan) **plus** a `_gmt` twin
   wherever the column drives availability or reporting. Use the `Dates` helper, not
   `date()` or `strtotime()`. See booking-engine §3.
8. **Every state change goes through a single service method** that validates the transition,
   records the activity log entry with before/after values, and fires a hook.
9. **Every endpoint declares its access key.** A controller without `AccessMiddleware` is a
   review failure, except for endpoints that are public on purpose and listed in the
   `rtbp_public_api_routes` filter.
10. **Sensitive fields** (identity document numbers, PINs, passcode hashes) never appear in list
    responses, in logs or in localized JS data.
11. **Bump `RadiusHotelBooking::DBVERSION`** in the same task that adds or changes a table.
    **Bump `PermissionsInstaller::ROLES_VERSION`** when the capability set changes.
12. **Never rename or drop** a stored key, column, hook or route after it ships. Before the
    first release, renaming is free.

## 3. Frontend rules

1. Screens live in `src/modules/<Module>/`. Each module has an `api.js`, an `index.jsx` and a
   `components/` folder when it has more than one screen.
2. Every user-visible string goes through `__()` / `_n()` / `sprintf()` with the
   `radius-hotel-booking` domain. Never concatenate translated strings.
3. Money through `formatMoney()`, dates through `formatDate()` / `formatTime()` from
   `src/lib/format.js` (built in M00). Never format by hand.
4. Anything rendered in a portal (Dialog, Sheet, Select, Popover, DropdownMenu) carries
   `rtbp-root` on its content node.
5. Hide or disable controls the user cannot use, **and** rely on the server to refuse them.
   The UI check is a convenience; the server check is the guarantee.
6. Every screen designs four states: loading (skeleton), empty, error, and populated. See
   `design-system.md`.
7. Every screen must work at 360 px wide.

## 4. Verification (no automated test suite)

The project has **no PHPUnit suite**, by decision. Logic is verified on the Local site
(`food-menu.local`) instead:

| Check | How | Required for |
|---|---|---|
| Logic check | A throwaway `wp eval` / `wp eval-file` script run against the Local site. It prints the inputs and results; you compare them with the expected values. Don't commit it | Every pure calculation: pricing, time windows, overlap, money, payroll maths, invoice numbering |
| Edge-case checklist | Walk the relevant table (for example booking-engine §10, or the M15 statutory brackets) row by row with a logic check | Availability, pricing and payroll changes |
| Concurrency check | Two `wp eval` processes started in parallel, both trying to book the last room. Exactly one wins | Any change to the locked write path (booking-engine §7) |
| Acceptance script | The module doc's **Acceptance** section, in the browser | Every module, before it is marked done |

Record what was checked, and the result, in the module's **Progress notes**.

## 5. Definition of Done — per task

- [ ] Code follows the layer rules above
- [ ] `composer phpcs` clean on the changed PHP files
- [ ] `npm run build` succeeds
- [ ] New or changed PHP logic is verified on the Local site (§4), and the result is noted
- [ ] Every new string is translatable
- [ ] Every new endpoint has an access key; every new state change writes an activity log entry
- [ ] `DBVERSION` / `ROLES_VERSION` bumped if tables or capabilities changed
- [ ] `readme.txt` changelog updated **in each repo touched** for anything user-visible (see §7)
- [ ] The code is in the repo its tag says; free contains no Pro/client code; every cross-plugin
      symbol is guarded
- [ ] Free plugin: wordpress.org rules hold (ADR-016). No external calls or CDN assets, no locked
      UI, everything escaped/sanitised/prepared/nonce-checked
- [ ] The task's checkbox in its module doc is ticked

## 6. Definition of Done — per module

All task DoDs, plus:

- [ ] Every feature ID the module owns in `docs/requiremetnt/08-feature-list.md` is implemented or
      explicitly deferred in the module doc, with a reason
- [ ] The module's settings are in the Settings panel (Module 17) with defaults in `SettingsHelper`
- [ ] The module's access keys appear in the permission map (Module 13)
- [ ] The acceptance script in the module doc passes on the Local site (in English; ADR-019)
- [ ] The `rtbp-critical-reviewer` agent has reviewed any availability, money, permission or payroll code
- [ ] `docs/project/roadmap.md` shows the module as `done`
- [ ] Free still works with Pro deactivated (smoke test of the module's screens)
- [ ] `wp plugin check radius-hotel-booking` shows no errors (once Plugin Check is installed on the Local site)

## 7. Changelog

Follow the global rule: match the existing `readme.txt` style (plain sentences, one per line),
append to the topmost `( UNRELEASE )` block, 16 words or fewer, written for the hotel owner. Do
not touch `Stable tag`, the plugin header or the version constant while a block is unreleased.

Documentation-only and internal refactors with no visible effect do not need a line.

## 8. Git

- One branch per module: `module/m06-rooms-floors`. Branch from `main`.
- Commit per task, message `M06: bulk room creation (6.10)`.
- Commit and push only when the user asks.
