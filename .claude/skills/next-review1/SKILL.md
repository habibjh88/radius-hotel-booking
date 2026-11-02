---
name: next-review1
description: Fix the next unchecked task of the client's first review (R1, 2026-11-02) in Radius Hotel Booking, verify it, tick it off, and report progress.
argument-hint: "[R1-T5 | all]"
disable-model-invocation: true
---

# /next-review1 — fix the next item of client review R1

Arguments: `$ARGUMENTS`

## 1. Find the task

1. Open `docs/reviews/R1-client-review-2026-11-02.md`. The task is the first unchecked
   `- [ ] R1-T…` in **Tasks**, or the one named in the arguments (`R1-T5`).
2. If the task says **waiting on Qn** and the **Open questions** table has no answer for Qn, do not
   build it: ask the user that question (AskUserQuestion), record the answer in the table, then
   continue — or skip to the next task if the user has no answer yet.
3. Read the tier tag and work in that repo, on its checked-out branch (never create a branch):
   - `[Free]` → this repo (`radius-hotel-booking`)
   - `[Pro]` → `../radius-hotel-booking-pro`
   - `[Client]` → `../radius-hotel-booking-residencetata`
   - A combined tag → each named repo, free first (free adds the hook, ADR-015).
4. Before changing anything, reproduce the problem or look at the current screen on
   food-menu.local (`/wp-admin/admin.php?page=radius-hotel-booking#/` or `/hotel-dashboard/`), as a
   **Manager** where it matters. For "look like the old one" tasks, compare with the matching
   screenshot in `~/Desktop/hotel-booking/review-images/` and the old dashboard
   (`http://fmp-client-residencetata.local/hotel-dashboard/`, read-only).

## 2. Load the right skills

Same table as `/next-task` §2: `rtbp-admin-screen` for anything under `src/`,
`rtbp-backend-slice` for tables/models/services/routes, `rtbp-booking-engine` for availability,
check-in/out, booking writes and pricing, `rtbp-access-and-audit` for endpoints, staff actions and
the activity log, `rtbp-settings-section` for settings, `rtbp-legacy-lookup` for old behaviour.

## 3. Implement

- Keep the fix to this task; write follow-ups into the review doc's **Progress notes**.
- Reuse what exists (much of R1 is already built; see the doc's "What the demo already has").
  Move or link existing screens rather than building a second copy.
- Tier rules (ADR-014/016): no Pro or Client code in free; Pro and Client reuse free's framework
  and `window.rtbp`; guard every cross-plugin symbol (`class_exists()` / `function_exists()` /
  `defined()`), never copy it. Free stays global: no Côte d'Ivoire specifics (TATA add-on only).
- English only, every string translatable (ADR-019).

## 4. Verify

In every repo the task touched, show the real output:

```bash
./vendor/bin/phpcs --standard=phpcs.xml --report=summary <changed php files>; echo "phpcs exit: $?"
bun run build
```

- No PHPUnit or ESLint (ADR-018). Check PHP logic with a throwaway `wp eval` on food-menu.local;
  test data is created and deleted by the script itself only (never whole tables).
- UI: load the screen in Chrome (claude-in-chrome) at desktop and 360 px. If Chrome lands on
  wp-login, ask the user to sign in.
- Format only the files you touched (`./node_modules/.bin/prettier --write <files>`), then check
  `git status` for stray changes.
- Changelog: one line (≤16 words) in each touched repo's `readme.txt`, in the topmost
  `( UNRELEASE )` block, matching its style.

## 5. Record

- Tick the task: `- [x] R1-T3 … — done YYYY-MM-DD`, and add a Progress note if anything is worth
  knowing later (root cause, decision, follow-up).
- Offer a commit per repo with message `R1-Tn: <short title>`. Commit only if the user agrees.
  Never push.

## 6. Arguments `all`

Repeat 1–5 for each remaining task, stopping at the first one that fails verification or needs a
client answer. Otherwise stop after one task.

## Progress line (always end with it)

Re-read **Tasks** after ticking and end with:

```
R1 client review — 4 of 18 done (14 left)
✅ T1  ✅ T2  ✅ T3  ✅ T4  ▶ T5  ○ T6 … ⏸ T13  ⏸ T16  ⏸ T17  ⏸ T18
Next: R1-T5 — sidebar like the old dashboard
```

✅ done · ▶ next · ○ not started · ⏸ waiting on a client answer. When all are done, run the
**Acceptance** script with the user and draft the reply to the client (what changed, what is
still waiting on Q1–Q5).
