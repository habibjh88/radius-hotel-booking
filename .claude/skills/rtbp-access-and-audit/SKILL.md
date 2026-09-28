---
name: rtbp-access-and-audit
description: Rules for staff access control (Module 13 — access keys, AccessMiddleware with open/locked in free and the passcode level in Pro) and the activity log (Module 14 — the free rtbp_activity() emitter, stored by Pro's ActivityLogger). Use whenever adding an endpoint, a staff action, a state change, a page, or anything that must be permission-checked or recorded.
---

# Access control and the activity log

Two rules, no exceptions:

1. **Every staff-facing endpoint declares an access key and is enforced on the server.**
2. **Every state change and every sensitive read emits an activity event.**

Design: `docs/modules/M13-staff-permissions.md`, `docs/modules/M14-activity-log.md`,
ADR-004, ADR-011, ADR-014 and ADR-015 in `docs/project/decisions.md`.

## Who owns what (ADR-014)

| Piece | Plugin |
|---|---|
| `AccessRegistry`, `Access::level()`, `AccessMiddleware` (`open` / `locked`), `useAccess` | Free |
| The `passcode` level, PINs, `PasscodeDialog`, custom access roles, per-person overrides, profile-field control | **Pro**, through the `rtbp_access_level`, `rtbp_access_passcode_check` and `rtbp.api.error` hooks |
| `rtbp_activity()` emitter, `ActionCatalog`, change diffing and masking | Free: it **emits**, it stores nothing |
| `ActivityLogger` (storage, hash chain, grouping), the log screen, `ActivityTimeline`, archiving | **Pro**, subscribed to the `rtbp_activity` action |

Never put Pro logic in the free plugin behind `rtbp_addon_active()`. The free plugin exposes the
hook, and Pro fills it.

## Access keys

- Registered in `includes/Access/AccessRegistry.php` (free, M13). Each key has `area`, a
  translatable `label`, `kind` (`page` | `action`), `default` (`open` | `locked`; free never
  defaults to `passcode`) and a `group` for the matrix UI.
- Naming: pages are `page.<area>` (`page.bookings`); actions are `<area>.<verb>`
  (`bookings.approve`, `payments.record`, `rooms.manage`, `guests.ban`).
- A module registers its keys **in the same task** that adds the endpoint. Pro and the client
  add-on use the `rtbp_access_keys` filter.

## Enforcing (PHP)

```php
$this->middleware[] = new AuthMiddleware();
$this->middleware[] = new AccessMiddleware( array(
    'index'   => 'page.bookings',
    'store'   => 'bookings.create',
    'approve' => 'bookings.approve',
) );
```

The level resolves through the built-in role map, then the `rtbp_access_level` filter (Pro:
custom role, then per-person override), then the admin relax.

| Level | Response |
|---|---|
| `locked` | 403 `access_locked`, and a `security.denied` event is emitted |
| `passcode` | Handled by `rtbp_access_passcode_check` (Pro): it checks the `X-RTBP-Passcode` token, and answers 403 `passcode_required` when the token is missing. **If no handler answers, `passcode` is treated as `locked`** |
| `open` | The request continues |

Before M13 exists, fall back to `PermissionMiddleware( Capabilities::VIEW_DASHBOARD )`, and check
with `class_exists()` first.

Services reachable from several endpoints re-check with `Access::can( 'bookings.approve' )`.

## Enforcing (React)

`useAccess( key )` returns the level. When it is locked, don't render the control. The free API
client runs the `rtbp.api.error` filter on errors. Pro registers the handler that opens its
`PasscodeDialog`, gets a token, and retries the request once. Screens never ask for PINs
themselves.

## Emitting activity (free code)

```php
rtbp_activity( 'bookings.approve', $booking, array(
    'before'      => array( 'status' => 'pending' ),
    'after'       => array( 'status' => 'confirmed' ),
    'description' => sprintf( /* translators: %s booking ref */ __( 'Approved booking %s', 'radius-hotel-booking' ), $booking->reference ),
) );
```

- Call it **inside the service method**, after the DB write succeeds, inside the transaction when
  there is one.
- The emitter fills in the actor, IP, user agent and time. It takes the event kind from the
  `ActionCatalog` and masks secrets: a PIN, password or ID number is recorded as `"changed"`,
  never with its value. It then fires `do_action( 'rtbp_activity', $event )`.
- Pass only the fields that changed (`ChangeDiff::between( $old, $new )` helps).
- Add new action keys to the `ActionCatalog` in the same task.
- Page views and denials are emitted by `AccessMiddleware`. Don't emit them by hand.
- Pro code (`ActivityLogger`) never updates or deletes log rows, except the verified purge after
  an archive.
