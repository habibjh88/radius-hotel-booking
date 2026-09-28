---
name: rtbp-admin-screen
description: How to build a React screen or UI component for the Radius Hotel Booking staff dashboard or public booking flow — module folder, routes, API file, react-query, shared composites, status badges, i18n, portals and mobile. Use whenever creating or editing anything under src/.
---

# Building a screen

Read `docs/project/design-system.md` first; it fixes layout, tokens, status colours and the
composite components. This skill is the mechanics.

## Files

```
src/modules/<Module>/
  api.js              every REST call the module makes — nothing else calls get/post directly
  queries.js          react-query hooks: useBookings(filters), useApproveBooking() …
  index.jsx           the route's screen
  components/         screen-specific pieces
```

Register the route in `src/admin/routes.js`:

```js
{
  path: '/bookings/:id',
  group: 'frontDesk',          // sidebar group (design-system §2)
  label: __( 'Booking', 'radius-hotel-booking' ),
  icon: BedDouble,
  accessKey: 'page.bookings',  // hides the route when locked (M13)
  hidden: true,                // not in the sidebar (detail pages)
  element: lazy( () => import( '@/modules/Bookings/Detail' ) ),
}
```

## In Pro or the client add-on

Add-on bundles never bundle their own React or copy components. Their webpack config maps
externals to the free runtime:

| Import | Resolves to |
|---|---|
| `react`, `@wordpress/hooks`, `@wordpress/i18n` | WordPress's shared scripts (the wp-scripts default; the same objects as `window.rtbp.React` / `.hooks`) |
| `@rtbp/ui` | `window.rtbp.ui` (Dialog, Select, Tabs, DropdownMenu parts are lazy: wrap in `Suspense`) |
| `@rtbp/lib` | `window.rtbp.lib` (forms: `await rtbp.lib.loadForms()`) |
| `@tanstack/react-query` | `window.rtbp.ReactQuery` (one cache with the free app) |

`../radius-hotel-booking-pro/webpack.config.js` is the reference config. Add-on classes are only
compiled into the free CSS if free uses them too; until a shared Tailwind preset exists (see M00
notes), stick to classes the free app already uses.

Screens are added with `addFilter( 'rtbp.admin.routes', … )`. Panels on detail screens use
`rtbp.booking.panels` / `rtbp.guest.panels`, settings tabs use `rtbp.settings.sections`, and error
handling uses `rtbp.api.error`. The add-on script depends on `radius-hotel-booking-admin`. If an
add-on screen needs a missing shared component, add the component to **free** first.

## Rules

- **Reuse composites** from `src/components/` (`PageHeader`, `DataTable`, `FilterTabs`,
  `DateRangePicker`, `StatusBadge`, `Money`, `DateTime`, `EmptyState`, `ConfirmDialog`,
  `FormSection`, `NotesPanel`). If one is missing and the design system lists
  it, build it there, generically — never a module-local copy.
- **Server state** via `@tanstack/react-query`: query keys `[ 'bookings', filters ]`; every
  mutation invalidates the keys it affects and shows a `sonner` toast. Status transitions are
  optimistic with rollback.
- **Forms**: `react-hook-form` + `zod`. Map `error.errors` from `src/api/client.js` onto fields
  (`setError( field, { message: e.first_message } )`).
- **Access**: `useAccess( 'bookings.approve' )` returns `open | passcode | locked` (M13). Locked →
  don't render the control; passcode → render it, the API client handles the PIN prompt.
- **i18n**: `import { __, _n, sprintf } from '@wordpress/i18n'`, domain `radius-hotel-booking`,
  translator comments for placeholders. Never concatenate.
- **Formatting**: `formatMoney`, `formatDate`, `formatTime`, `formatDateRange` from
  `src/lib/format.js` — site settings drive currency position and date format.
- **Status**: `StatusBadge domain="stay" value={ line.status }` — never pick colours locally.
- **Portals**: `DialogContent`, `SheetContent`, `SelectContent`, `PopoverContent`,
  `DropdownMenuContent` get `className="rtbp-root …"`; otherwise Tailwind (scoped with
  `important: '.rtbp-root'`) won't style them.
- **States**: skeleton while loading, `EmptyState` when empty, inline error with retry, then data.
- **Mobile**: works at 360 px. `DataTable` switches to cards below `md`; toolbars wrap; primary
  action stays reachable (sticky footer on forms).
- **Public site** (`src/site/`): same components; `BookingFlow` takes `mode="desk" | "guest"` —
  never fork it.

## Verify

`npm run build`, then load the screen on the Local site (claude-in-chrome) at
desktop and 360 px, and check loading/empty/error states.
