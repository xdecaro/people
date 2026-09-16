# People

Stable reusable person master data for the xdecaro ecosystem.

- Component: `com_xdecaropeople`
- Package: `pkg_xdecaropeople`
- Namespace: `xdecaro\Component\People`
- Tables: `#__xdecaropeople_*`
- Stable version: `1.3.0`
- Joomla: `6.1.3` only
- PHP: `8.3+`
- Requires xdecaro Core `2.0.1+`
- Author: `Luca De Caro`

People owns person identity/contact master records and an optional Joomla User link. Membership, Courses, Competitions, Events and other products own their domain roles and temporal relationships. Cross-product consumers must use public provider/service surfaces and Core capability/entity-reference contracts, never direct private-table access.

## Person profile

People provides Identity, Contacts, Disability, Accessibility, Residence, Relations, Documents and taxation, Social, Publishing and System areas. `display_name` remains an internal compatibility field generated automatically from first name and last name. Sensitive birth, disability, accessibility, tax and residence data remain behind the `people.view_sensitive` permission boundary.

The public People provider exposes reusable person records and batch UUID resolution without requiring other components to read People tables directly.

## Competitions history integration

People 1.3.0 adds an optional read-only **Competitions** tab to an existing person's administrator profile. When Competitions advertises `competitions.people_history` v1 through Core, People calls the public Competitions history service using the stable People UUID and displays competition, season, team, role, shirt number and status history.

People does not query `#__xdecarocompetitions_*`, does not copy competition history into People, and remains fully usable when Competitions is absent or incompatible. New unsaved people do not show the tab because no stable UUID lookup is required yet.

## Notifications integration

People integrates with the shared `com_xdecaronotifications` center. In **People → Options → Notifications**, an administrator selects the Joomla user who receives People events in the global administrator bell.

People emits privacy-safe in-app notifications when a person is created, important profile data changes, Joomla publication state changes, or a possible duplicate is detected. Notification text contains the person's display name and a generic event description only; sensitive field values are never copied into notification text. If Notifications is unavailable or no recipient is configured, People persistence continues normally.

The 1.3.0 release introduces no People database schema migration.
