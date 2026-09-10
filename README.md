# People

Stable Joomla 6 component for reusable person master data in the xdecaro ecosystem.

- Component: `com_xdecaropeople`
- Package: `pkg_xdecaropeople`
- Namespace: `xdecaro\Component\People`
- Tables: `#__xdecaropeople_*`
- Stable version: `1.0.3`
- Joomla: `6.x` only
- PHP: `8.3+`
- Requires xdecaro Core `1.4.0+`
- Author: `Luca De Caro`

People owns person identity/contact master records and an optional Joomla User link. Membership, Courses, Competitions, Events and other products own their domain roles and temporal relationships. Cross-product consumers must use the public provider and Core capability/entity-reference contracts, never direct People table access.

`1.0.3` fixes the Joomla 6 new-person route so it always targets `com_xdecaropeople`, reads the installed component version from Joomla instead of hardcoding it in Information, and removes the visible `by xdecaro` package/release label while preserving xdecaro technical identifiers and `Luca De Caro` author metadata. The release has no schema or person-data migration.
