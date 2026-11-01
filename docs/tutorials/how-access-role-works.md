# How staff access works: Permissions, access roles and staff access

This tutorial explains how the dashboard decides what one staff member may open and do, and
how the three screens that control it fit together:

| Screen | Where | Plugin | What it sets |
|---|---|---|---|
| **Permissions** | Staff & access → Permissions | Free | One rule set per **WordPress staff role**: *Manager* and *Staff* |
| **Access roles** | Staff & access → Access roles | Pro | Named job profiles (*Receptionist*, *Accountant*…) you can give to people |
| **Staff access** | Staff & access → Staff access | Pro | Per person: which access role they have, personal overrides, a personal PIN |

They are **layers**. Each layer can change, in either direction, what the layer below decided.
Most hotels only ever need the first one or two.

The rules themselves are specified in [`docs/modules/M13-staff-permissions.md`](../modules/M13-staff-permissions.md)
and ADR-004. Where this page and the spec disagree, the spec wins and the code has a bug.

---

## 1. The vocabulary

### Access keys: the things that can be allowed or refused

Every page and every sensitive action has an **access key**:

- pages: `page.bookings`, `page.rates`, `page.settings`, `page.payroll`…
- actions: `bookings.cancel`, `payments.void`, `guests.ban`, `rooms.manage`…

The free plugin registers its keys in `includes/Access/AccessRegistry.php`. Pro and the client
add-on add theirs through the `rtbp_access_keys` filter. Each key has a **default level**: most
front-desk keys default to *Open*, and sensitive ones such as `page.rates`, `page.exports` and
`page.settings` default to *Locked*.

### Levels: what happens when someone tries

| Level | What the person sees | Plugin |
|---|---|---|
| **Open** | It works. | Free |
| **Passcode** | It asks for a PIN first (their own personal PIN, or the hotel's fallback PIN). | Pro |
| **Locked** | The menu item or button is hidden, and the server refuses the request even if someone calls the API directly. | Free |

Without Pro, there are only *Open* and *Locked*. The check always happens **on the server**;
hiding the button is only for convenience.

### Two different things are called "role"

This is the source of most confusion:

| | **WordPress role** | **Access role** (Pro) |
|---|---|---|
| Examples | *Manager*, *Staff* (also *Administrator*, *Hotel Guest*) | *Receptionist*, *Night receptionist*, *Supervisor*, *Housekeeping*, *Accountant*, *Manager* |
| What it means | **Can this person sign in to the dashboard at all**, and their baseline rules | **Which job they do**: a finer rule set on top of the baseline |
| Where it is set | WordPress Users screen, or automatically when a staff file creates the login | Staff & access → Staff access → *Access role*, or when the staff file is created |
| Where its rules are edited | Staff & access → **Permissions** | Staff & access → **Access roles** |
| How many per person | Usually one | At most one, or none |

So **"Staff" is missing from the Access roles page on purpose**. *Staff* is a WordPress role, and its
rules live on the **Permissions** page. The Access roles page lists only Pro's job profiles. The
*Manager* access role there is a separate built-in role that happens to have the same name as the
*Manager* WordPress role; the two are not linked.

---

## 2. Layer 1: Permissions (free)

Staff & access → Permissions shows one column per WordPress staff role, **Manager** and **Staff**, and a
row for every access key. Each cell is *Open* or *Locked*, plus *Passcode* when Pro is active.

- **Manager**: everything is *Open* by default. The manager runs the hotel.
- **Staff**: any key you don't set follows the key's own default (front desk open; rates,
  exports and settings locked).

What you save here is stored in the option `rtbp_access_settings` → `roleLevels`, for each role
and key. Only the keys you change are stored; everything else keeps following its default.

**Everyone with the same WordPress role gets the same rules.** That is all the free plugin
offers, and for a small hotel it is often enough: put the reception team on *Staff*, the owner
on *Manager*.

> **Residence TATA today:** the import copied the legacy site's staff rules into the *Staff*
> column (`bookings.create`, `guests.ban`, guest notes, `page.availability`, `page.bookings`,
> `page.guests`, `page.rooms`, both reports: all *Open*). The other keys follow their defaults.

---

## 3. Layer 2: Access roles (Pro)

Staff & access → Access roles lists named job profiles. Pro installs six **built-in** roles (marked *Built-in*) the first time it
is activated:

| Built-in role | In short |
|---|---|
| **Receptionist** | Takes bookings, checks guests in and out, records payments. Rates, exports and settings locked. |
| **Night receptionist** | Like Receptionist, but no cancellations, refunds, line removal, bans or rooms report. |
| **Supervisor** | Runs the shift: may cancel and change bookings, sees the sales report, may export. Rooms and availability changes need a passcode. |
| **Housekeeping** | **Everything locked** except the dashboard, rooms and availability. |
| **Accountant** | Payments, reports and exports; voiding or changing a payment needs a passcode; cannot create bookings or check guests in or out. |
| **Manager** | Everything open, but voiding payments, managing permissions, resetting overrides and archiving the activity log need a passcode. |

You can edit the built-in roles (they cannot be deleted) and create your own with **New role**.

In the role editor, each key has four choices:

- **Default**: the role doesn't decide. The person's Layer 1 rule applies (their WordPress
  role's column on the Permissions page, or the key's default).
- **Open**, **Passcode**, **Locked**: the role decides, whatever Layer 1 says.

**Everything else** (stored as `*`) sets every key the role leaves on *Default* in one go.
*Housekeeping* uses it: *Everything else = Locked*, then three pages *Open*. That is how you
build a "deny all, allow a few" role.

**Changing a role changes every person who holds it**, immediately.

Roles are stored in the table `radius_hotel_booking_pro_access_roles` (`levels` is a JSON map of
key → level, with `*` for *everything else*).

---

## 4. Layer 3: Staff access (Pro)

Staff & access → Staff access lists everyone who can use the dashboard: users with the *Manager* or
*Staff* WordPress role, and administrators. Click **Manage** on a person to set:

1. **Access role**: one of the roles from Layer 2, or **No access role (defaults)**.
   *No access role* means "Layer 2 does nothing for this person": they follow the Permissions
   page for their WordPress role (Layer 1). **This is why every person currently shows the
   default**: nobody has been given an access role yet, so everyone follows the *Staff* column.
2. **Personal overrides**: a level for one key, for this person only. Each key starts on
   **Inherit** (no override). Next to it the screen shows **Now: Open · Access role**, meaning
   the level that applies right now and the layer it comes from:

   | Source label | Meaning |
   |---|---|
   | *Personal override* | Set on this person (Layer 3) |
   | *Access role* | Set by their access role (Layer 2) |
   | *Staff role default* | Set on the Permissions page for their WordPress role (Layer 1) |
   | *Default* | Nobody set it; the key's own default applies |
   | *Administrator* | They are a site administrator (see §5) |

   An override replaces **one key**, never the whole map. **Reset overrides** removes all of
   this person's overrides; **Reset all overrides** on the list does it for everyone.
3. **Personal PIN**: the PIN this person types when an action is set to *Passcode*.
4. **Profile fields**: which fields of their own staff file they may change themselves.

Overrides are stored in user meta `rtbp_pro_access_overrides`, the access role in user meta
`rtbp_pro_access_role`.

---

## 5. How the final level is decided

For one person and one key, the first rule that says something wins:

```
1. Personal override for this key?               → use it           (Staff access)
2. Their access role sets this key?              → use it           (Access roles)
3. Their access role sets "Everything else"?     → use it           (Access roles)
4. Permissions page, their WordPress role's cell → use it           (Permissions)
     (Manager has everything Open by default;
      with several staff roles, the most permissive wins)
5. The key's own default                         → use it
6. Administrator and the result is Locked?       → Passcode (with Pro) or Open (free)
```

Two more rules sit around it:

- **No dashboard access at all** (not *Manager*, *Staff* or an administrator, e.g. a *Hotel
  Guest*): every key is *Locked*, whatever the screens say.
- **Administrators can never be locked out.** Anything that would be *Locked* becomes
  *Passcode* when Pro is active, so they confirm with a PIN, or *Open* without Pro. This is
  deliberate: a mistake on the Permissions page must not lock the site owner out.

Steps 1 to 3 run only when Pro is active **and** the **Advanced staff access** feature is on
(Settings → Pro features). Switch it off and everyone falls back to steps 4 to 6.

In code: `Access::resolve()` in `includes/Access/Access.php` (free) does steps 4 to 6 and calls
the `rtbp_access_level` filter in between. Pro's `UserAccessService::resolve()` answers that
filter with steps 1 to 3.

---

## 6. A worked example: Adjoua Pokou

Adjoua Pokou has the WordPress role *Staff* and no access role.

| Key | Override | Access role | Permissions → Staff | Key default | **Result** |
|---|---|---|---|---|---|
| `page.bookings` | | none | Open | Open | **Open** (*Staff role default*) |
| `bookings.cancel` | | none | not set | Open | **Open** (*Default*) |
| `page.rates` | | none | not set | Locked | **Locked** (*Default*) |

Now give them the access role **Night receptionist**:

| Key | Override | Night receptionist | **Result** |
|---|---|---|---|
| `page.bookings` | | Open | **Open** (*Access role*) |
| `bookings.cancel` | | Locked | **Locked** (*Access role*): the cancel button disappears |
| `page.rates` | | Locked | **Locked** (*Access role*) |

Finally, the night manager wants them to see the rate plans, but nobody else on nights:
on their Staff access page, set an override `page.rates` = *Open*.

| Key | Override | Night receptionist | **Result** |
|---|---|---|---|
| `page.rates` | Open | Locked | **Open** (*Personal override*) |

The other night receptionists are not affected.

---

## 7. Recipes

**Give the whole reception team the same rules (free is enough).**
Leave everyone on the *Staff* WordPress role, set the *Staff* column on Staff & access → Permissions,
save. Don't give anyone an access role.

**Have different jobs (reception, housekeeping, accounting).**
Edit the built-in roles on Staff & access → Access roles to match the hotel, then give each person their role
on Staff & access → Staff access. Keep the *Staff* column on the Permissions page as the safe baseline
for anyone without a role.

**Make someone a manager.**
Either give them the *Manager* WordPress role (WordPress → Users → edit → Role), so they follow
the *Manager* column; or keep them on *Staff* and give them the *Manager* access role, which
also asks for a PIN for the most sensitive actions. Use one or the other, not both, or it gets
hard to tell which rule applies.

**One exception for one person.**
A personal override on Staff access. If several people need the same exception, make an access
role instead: overrides are easy to forget.

**New staff member.**
Creating the staff file with a login (Staff & access → Staff → **Add an employee**) creates a WordPress user with the
*Staff* role, and lets you choose their access role at the same time.

**Protect an action with a PIN instead of hiding it.**
Set the key to *Passcode* (on Permissions, in an access role, or as an override), and give
the person a personal PIN on Staff access. The PIN's validity and the fallback PIN are under
Settings → Passcodes.

---

## 8. Troubleshooting

**"I changed the Permissions page but this person still sees the page."**
Open them on Staff access and read the source next to that key. If it says *Access role* or
*Personal override*, a higher layer wins; change it there. If it says *Administrator*, they are a
site administrator and cannot be locked out.

**"A page vanished for everyone with a role."**
Someone edited the access role; it applies to every holder at once. The Activity log records
who changed which role and when.

**"Everyone shows *No access role (defaults)*."**
That is the starting state: no one has an access role, so everyone follows the Permissions page.
Nothing is wrong.

**"Staff isn't on the Access roles page."**
*Staff* is a WordPress role, not an access role. Edit its rules on Staff & access → Permissions (§1).

**"The menus didn't change after I saved."**
Your own menus refresh straight away. Other people get the new rules on their next request, so
they may need to reload the page.
