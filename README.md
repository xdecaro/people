# People by xdecaro

Stable Joomla component for reusable person master data in the xdecaro ecosystem.

- Component: `com_xdecaropeople`
- Package: `pkg_xdecaropeople`
- Namespace: `xdecaro\Component\People`
- Tables: `#__xdecaropeople_*`
- Stable version: `1.0.1`
- Requires Core by xdecaro `1.4.0+`

People owns person identity/contact master records and an optional Joomla User link. Membership, Courses, Competitions, Events and other products own their domain roles and temporal relationships. Cross-product consumers must use the public provider and Core capability/entity-reference contracts, never direct People table access.

`1.0.1` repairs upgrade compatibility from the 0.2.x prerelease without coercing legacy nullable first/last-name data.
