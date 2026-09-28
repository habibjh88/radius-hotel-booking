---
name: next-module
description: Start (or switch to) the next module of the Radius Hotel Booking build — picks it from docs/project/roadmap.md, checks dependencies, creates the module branch, loads the module spec and plans its tasks, then begins the first task.
argument-hint: "[M06 | next] [--force]"
disable-model-invocation: true
---

# /next-module — start or switch module

You are starting a module of the Radius Hotel Booking plugin build. The build plan lives in
`docs/project/roadmap.md`; each module's spec lives in `docs/modules/Mxx-*.md`.

Arguments: `$ARGUMENTS`

## 1. Pick the module

1. Read `docs/project/roadmap.md` (the **Tracker** table is the source of truth).
2. Resolve the target:
   - An explicit id in the arguments (`M07`) → that module. This is also how the user *switches*.
   - Otherwise, if a module is `in-progress` → tell the user which one and that `/next-task`
     continues it; ask whether to continue it or start the next one. Stop until answered.
   - Otherwise → the first `todo` row (in **Order**) whose **Depends on** modules are all `done`.
3. If a dependency is not `done` and `--force` was not passed, stop and list the missing ones.
4. If the module doc lists an **Open decision** marked *blocking* (e.g. D1 for M15), ask the
   user with AskUserQuestion before doing anything else. Record the answer in
   `docs/project/decisions.md` and in the module doc.

## 2. Switching away from another module

If another module is `in-progress`: set it to `paused` in the tracker, note in its module doc
under **Progress notes** which task was next and anything half-done. Do not leave uncommitted
work behind silently — if `git status` is dirty, ask whether to commit, stash, or carry it over.

## 3. Branch (in every repo the module touches)

- The tracker's **Tier** column and the task tags (`[Free]`, `[Pro]`, `[Client]`) say which repos
  are involved (ADR-014):
  - Free = this repo
  - Pro = `../radius-hotel-booking-pro`
  - Client = `../radius-hotel-booking-residencetata`
- If a needed Pro or Client repo does not exist yet, M00 T9 has not been done. Stop and say so.
- In each involved repo, `git status` must be clean, unless the user chose to carry changes over.
- Use the branch name from the tracker's **Branch** column (`module/m07-rate-plans`), the same in
  every repo. Create it from `main` if it does not exist, otherwise check it out.
- Never force-push, and never commit without being asked.

## 4. Load context (read, don't skim)

1. The module doc in full.
2. `docs/project/conventions.md`, `docs/project/architecture.md` (the module's section and the
   data model), `docs/project/design-system.md` if the module has UI.
3. `docs/project/booking-engine.md` if the module doc says **Engine: yes**.
4. The module's rows in `docs/requiremetnt/08-feature-list.md` (feature ids listed in the doc).
5. The module's section of `docs/project/legacy-reference.md`, then open the legacy files it points
   to — behaviour to preserve lives there. Legacy root:
   `/Users/habib/Local Sites/fmp-client-residencetata/app/public/wp-content/plugins/`.
6. Verify every symbol the module doc says it **consumes** from earlier modules actually exists
   in the checked-out code (grep). Anything missing → report it; do not re-implement it here.

## 5. Plan

- Review the doc's **Tasks** list. If a task is bigger than ~half a day of work, split it into
  sub-tasks in the doc (keep the `T<n>` numbering; use `T3a`, `T3b`). Each task should end in
  something runnable or testable.
- Every task keeps its tier tag. Free tasks come before the Pro/Client tasks that hook into
  them. If a Pro task needs a hook that free does not have yet, add a `[Free]` task for the
  seam before it (ADR-015).
- Every feature id the module owns must map to at least one task. Add a task, or add the id
  under **Deferred** with a reason.
- Show the user the plan: module goal, task list with the feature ids each covers, open
  questions (non-blocking ones with the default you'll use).

## 6. Update the tracker

Set the module `in-progress`, fill **Branch** and **Started** (today's date, absolute) in
`docs/project/roadmap.md`.

Show the plan with the same **progress line** that `/next-task` ends with (see
`.claude/skills/next-task/SKILL.md`, "Progress line"), so you can see from the start how many
tasks the module has.

## 7. Begin

Unless the user asked only to plan, continue straight into the first unchecked task by following
the `/next-task` procedure (`.claude/skills/next-task/SKILL.md`).
