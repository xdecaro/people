# People

Stable reusable person master data for the xdecaro ecosystem.

- Component: `com_xdecaropeople`
- Package: `pkg_people` (`pkg_xdecaropeople` is retained only as a legacy update/migration identity)
- Namespace: `xdecaro\Component\People`
- Tables: `#__xdecaropeople_*`
- Stable candidate version: `1.7.1`
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


## Membership integration

People 1.5.0 può mostrare una scheda **Membership** di sola lettura quando Membership espone la capability pubblica `membership.person_memberships` v1. Il collegamento usa esclusivamente il `person_uuid` stabile e non interroga tabelle private Membership. Se Membership è assente, incompatibile o non autorizzato, People continua a funzionare normalmente e la scheda non viene mostrata.

People resta proprietario dell'identità personale; categoria, sede, stato associativo, diritti ed eventuale storico restano di proprietà Membership.


## CSV bulk import

People 1.6.0 adds a Joomla administrator CSV import flow with client-side encoding/delimiter detection, explicit column mapping, duplicate consolidation, server-side existing-person analysis and batched writes. It imports only People-owned identity/contact/residence fields, generates the stable People UUID automatically, skips existing people without overwriting them, and intentionally excludes Membership, disability/health and payment/card data. Legacy source locations may be preserved as text without inventing provider IDs; unchanged imported locations remain editable while changed locations must be reselected through Core's world-location service.


People 1.6.1 improves CSV review by showing every row that needs correction before import, including source row number, person name, tax identifier and the specific validation/conflict reason. Invalid rows remain excluded from bulk import.


## Duplicate resolution

People 1.7.0 replaces the old duplicate warning table with a side-by-side review workflow. Strong matches can be resolved by choosing a canonical person: missing fields are copied into the canonical record, conflicting existing values are not overwritten, source records are archived rather than deleted, and source UUIDs continue to resolve to the canonical person through the public People provider. Possible-only matches can be dismissed as not duplicates. Resolution history is retained in dedicated tables.


People 1.7.1 makes duplicate review compact with collapsed comparison groups, highlights same/different/missing identity fields, and blocks merge when matching name/birth records carry different tax identifiers.
