# People by xdecaro

Stable Joomla 6 component for reusable person master data in the xdecaro ecosystem.

- Component: `com_xdecaropeople`
- Package: `pkg_xdecaropeople`
- Namespace: `xdecaro\Component\People`
- Tables: `#__xdecaropeople_*`
- Stable version: `1.0.2`
- Joomla: `6.x` only
- PHP: `8.3+`
- Requires Core by xdecaro `1.4.0+`
- Author: `Luca De Caro`

People owns person identity/contact master records and an optional Joomla User link. Membership, Courses, Competitions, Events and other products own their domain roles and temporal relationships. Cross-product consumers must use the public provider and Core capability/entity-reference contracts, never direct People table access.

`1.0.2` fixes the Joomla 6 People list-state runtime error, installs the administrator language metadata through Joomla's system-language path so the XML description resolves during installation, and simplifies the administrator component label to `People`. The release has no schema or person-data migration.
