# Mxx — Module name

| | |
|---|---|
| **Client module** | n — name in `08-feature-list.md` |
| **Features** | x.1 – x.n |
| **Depends on** | Mxx (what it consumes) |
| **Engine** | yes / no — read `booking-engine.md` first if yes |
| **Critical review** | yes / no — run `rtbp-critical-reviewer` before closing |
| **Status** | see `docs/project/roadmap.md` |

## Goal

One paragraph: what the hotel can do when this module ships.

## Scope

| Feature | Summary | Task |
|---|---|---|
| x.1 | … | T1 |

**Deferred:** feature ids not built here, and why.

## Data

Tables (defined in `architecture.md` §Data model), with any module-specific notes.

## API

| Method | Route | Access key | Purpose |
|---|---|---|---|

## Screens

Route, layout sketch, key interactions, mobile notes.

## Settings (added to M17)

| Section.key | Default | Meaning |
|---|---|---|

## Access keys (added to M13)

| Key | Kind | Default |
|---|---|---|

## Activity log actions (M14)

List of the action keys this module records.

## Consumes / provides

- **Consumes:** classes, functions and hooks from earlier modules. `/next-module` verifies they exist.
- **Provides:** what later modules rely on. Keep these stable.

## Tasks

- [ ] T1 …
- [ ] T2 …

## Acceptance

A manual script to run on the Local site, in FR and EN, at desktop and phone width.

## Legacy reference

Pointers into `legacy-reference.md` and the legacy files.

## Migration notes

The legacy storage this module replaces (for M18's importer).

## Progress notes

(Filled in during the build: decisions, deviations, follow-ups.)
