---
name: module-status
description: Show build progress for the Radius Hotel Booking modules — tracker status, task counts per module, what's next, and open client decisions.
argument-hint: "[M06]"
disable-model-invocation: true
---

# /module-status

Arguments: `$ARGUMENTS`

1. Read `docs/project/roadmap.md` (Tracker table).
2. For each module doc in `docs/modules/`, count `- [x] T` vs `- [ ] T` lines under **Tasks**.
3. Print a compact table: Order · ID · Module · Tier · Status · Tasks done/total, split by tag
   (e.g. `Free 3/4 · Pro 0/2`) · Branch.
4. Below it:
   - **Now:** the `in-progress` module and its next unchecked task.
   - **Next:** what `/next-module` would pick (first `todo` with all dependencies `done`).
   - **Blocked:** modules whose dependencies are not done, or with a blocking open decision.
   - **Open decisions:** the rows of "Open: needs the client" in `docs/project/decisions.md`.
5. With a module id argument, instead show that module's full task list with status and its
   **Progress notes**.

Read-only: change nothing.
