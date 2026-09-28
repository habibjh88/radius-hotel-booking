---
name: next-task
description: Implement the next unchecked task of the in-progress Radius Hotel Booking module, verify it against the Definition of Done, tick it off, and close the module when its last task is done.
argument-hint: "[M06 T3 | all]"
disable-model-invocation: true
---

# /next-task — build the next task of the current module

Arguments: `$ARGUMENTS`

## 1. Find the task

1. Read `docs/project/roadmap.md`. The target module is the one `in-progress`, or the module id
   in the arguments. No module in progress → tell the user to run `/next-module` and stop.
2. Open the module doc `docs/modules/Mxx-*.md`. The task is the first unchecked `- [ ] T…` in
   **Tasks**, or the one named in the arguments (`T3`).
3. Read the task's tier tag and work in that repo:
   - `[Free]` → this repo (`radius-hotel-booking`)
   - `[Pro]` → `../radius-hotel-booking-pro`
   - `[Client]` → `../radius-hotel-booking-residencetata`
   - A combined tag → each of the named repos, free first.

   Confirm each repo is on the module's branch (tracker **Branch** column); if not, check it out.
   The docs and tracker always live in this repo.
4. If this is a fresh session, load the context listed in `/next-module` step 4 for this module
   (module doc, conventions, the relevant architecture/booking-engine sections, legacy refs).

## 2. Load the right skills

Pick every one that applies to the task, and follow it:

| Task touches | Skill |
|---|---|
| a table, model, repository, service, controller or route | `rtbp-backend-slice` |
| availability, time windows, pricing, holds, booking writes | `rtbp-booking-engine` |
| a React screen or component | `rtbp-admin-screen` |
| a settings section | `rtbp-settings-section` |
| an endpoint, an action, or anything a staff member does | `rtbp-access-and-audit` |
| behaviour copied from the old system | `rtbp-legacy-lookup` |

## 3. Implement

- Follow the task's description and the feature ids it lists. Where the doc is silent, choose
  the smallest design consistent with `docs/project/architecture.md`, and write the choice into the
  module doc under **Progress notes** (or an ADR in `decisions.md` if later modules depend on it).
- **Tier discipline (ADR-014/016):**
  - Free code never contains Pro or client logic, not even disabled or behind
    `rtbp_addon_active()`.
  - A Pro or Client task that needs a free hook adds the hook to free first, as its own step, and
    records it in ADR-015.
  - Pro and Client code reuse the free framework and `window.rtbp`; they never copy free code.
- Symbols from other modules or plugins: verify they exist first; guard with `class_exists()` /
  `method_exists()` / `function_exists()` if they may be absent. Never duplicate them.
- Keep the change to this task. Note follow-ups in the module doc instead of doing them.

## 4. Verify (Definition of Done, `docs/project/conventions.md` §5)

Run and fix until clean, **in every repo the task touched**. Show the user the real output, and
never claim a pass you did not see:

```bash
./vendor/bin/phpcs --standard=phpcs.xml <changed php files>
npm run build
npm run test:php            # if WP_CORE_DIR / test DB are configured; otherwise say it was skipped
```

Then check: strings translatable · access key on every new endpoint · activity-log entry on every
state change · `DBVERSION` / `ROLES_VERSION` bumped if needed · `readme.txt` changelog line added
for anything user-visible **in each repo touched** (append to the topmost `( UNRELEASE )` block,
match its style, ≤16 words) · free contains no Pro/Client code, and free screens still work with
Pro deactivated · free stays wordpress.org-compliant (ADR-016: no external calls or CDN assets, no
locked UI).

If the task has UI and the Local site is reachable, load the screen in the browser
(claude-in-chrome) and check it at desktop and 360 px width.

## 5. Record

- Tick the task: `- [x] T3 … — done YYYY-MM-DD` and add one line under **Progress notes** if
  anything is worth knowing later (a decision, a deviation, a follow-up).
- Offer to commit with message `Mxx: <task title> (<feature ids>)`. Commit only if the user agrees.

## 6. Module complete?

If that was the last unchecked task:

1. Run the module DoD (`conventions.md` §6).
2. If the module doc says **Critical review: yes**, launch the `rtbp-critical-reviewer` agent
   on the module's diff (`git diff main...HEAD`) and fix confirmed findings.
3. Walk the user through the doc's **Acceptance** script (or run what can be automated).
4. When the user confirms acceptance: set the module `done` (with **Finished** date) in
   `docs/project/roadmap.md` and name the next module `/next-module` would pick.

## 7. Arguments `all`

With `all`, repeat steps 1–5 for each remaining task of the module, stopping at the first task
that fails verification or needs a user decision, and summarise at the end.

Otherwise stop after one task: summarise what was built, what was verified, and what the next
task is (`/next-task` to continue).
