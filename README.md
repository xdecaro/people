# People

Stable reusable person master data for the xdecaro ecosystem.

- Component: `com_xdecaropeople`
- Package: `pkg_people` (`pkg_xdecaropeople` is retained only as a legacy update/migration identity)
- Namespace: `xdecaro\Component\People`
- Tables: `#__xdecaropeople_*`
- Stable candidate version: `1.4.0`
- Joomla: `6.1.3` only
- PHP: `8.3+`
- Requires xdecaro Core `2.0.1+`
- Author: `Luca De Caro`

People owns person identity/contact master records and an optional Joomla User link. Membership, Courses, Competitions, Organizations, Events and other products own their domain roles and temporal relationships. Cross-product consumers must use public provider/service surfaces and Core capability/entity-reference contracts, never direct private-table access.

## Person profile

People provides Identity, Contacts, Disability, Accessibility, Residence, Relations, Documents and taxation, Social, Publishing and System areas. `display_name` remains an internal compatibility field generated automatically from first name and last name. Full sensitive birth, disability, accessibility, tax and residence data remain behind `people.view_sensitive`.

The public identity-disambiguation contract `searchPeopleForIdentity()` exposes only birth date and birth place under the narrower `people.view_identity_details` permission. People 1.4.0 also exposes `personExists()`, `getRelations()` and `getCurrentAddress()` through the same public provider boundary; relationship and residence detail reads remain protected by the sensitive-data ACL.

## Person relationships

People 1.4.0 enriches relationships without moving domain logic into other components. Each relation has a stable relation UUID, related People UUID, type, optional validity dates, state and note. Parent/child, spouse/partner, guardian/ward, curator/assisted person, support administrator/supported person and legal representative/represented person are kept reciprocal. Generic `responsible` and `other` relations remain directional.

The relation editor validates the target person server-side, prevents self/dangling links, validates date ranges and preserves reciprocal metadata. Existing JSON-based relation storage is retained for non-destructive upgrades.

## Organizations appointments integration

People 1.3.2 adds an optional read-only **Organizations** tab to an existing person's administrator profile. When Organizations advertises `organizations.people_appointments` v1 through Core and the current user is authorised by Organizations, People calls the public Organizations person-appointments service using the stable People UUID.

The tab separates **Current appointments** from **Appointment history** using the `is_current` classification owned by Organizations. It shows organization, role, mandate dates, status/end reason and links back to the Organizations record.

People does not query `#__xdecaroorganizations_*`, does not copy appointment history into People, and remains fully usable when Organizations is absent, incompatible or unavailable.

## Competitions history integration

People also supports the optional read-only **Competitions** tab. When Competitions advertises `competitions.people_history` v1 through Core, People calls the public Competitions history service using the stable People UUID and displays competition, season, team, role, shirt number and status history.

People does not query `#__xdecarocompetitions_*` and does not copy competition history into People.

## Notifications integration

People integrates with the shared `com_xdecaronotifications` center. In **People → Options → Notifications**, an administrator selects the Joomla user who receives People events in the global administrator bell.

People emits privacy-safe in-app notifications when a person is created, important profile data changes, Joomla publication state changes, or a possible duplicate is detected. Notification text contains the person's display name and a generic event description only; sensitive field values are never copied into notification text. If Notifications is unavailable or no recipient is configured, People persistence continues normally.

People 1.4.0 adds no People database columns or tables; the upgrade is non-destructive.
