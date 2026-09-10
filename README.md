# People

Stable Joomla 6 component for reusable person master data in the xdecaro ecosystem.

- Component: `com_xdecaropeople`
- Package: `pkg_xdecaropeople`
- Namespace: `xdecaro\Component\People`
- Tables: `#__xdecaropeople_*`
- Stable version: `1.1.0`
- Joomla: `6.x` only
- PHP: `8.3+`
- Requires xdecaro Core `1.4.0+`
- Author: `Luca De Caro`

People owns person identity/contact master records and an optional Joomla User link. Membership, Courses, Competitions, Events and other products own their domain roles and temporal relationships. Cross-product consumers must use the public provider and Core capability/entity-reference contracts, never direct People table access.

## Person profile

People 1.1.0 organises the person sheet into `Identity`, `Contacts`, `Residence`, `Social` and `Publishing` tabs.

Identity includes birth data, optional sex, optional disability status, searchable nationality, and a TIN field whose visible label follows the selected European nationality where a local common name is configured (for example `TIN - Codice fiscale` for Italy and `TIN - Numéro fiscal` for France). Outside that mapping the label remains `TIN`.

Contacts contains email, phone, WhatsApp and communication language. Residence uses a searchable country selector and stores ISO country codes automatically. Social profiles remain part of the person sheet and do not create a separate administration menu.

`display_name` remains an internal compatibility field and is generated automatically from first name and last name. Empty optional birth dates are stored as SQL `NULL`. Disability, birth, tax and residence data remain behind the `people.view_sensitive` permission boundary.

The 1.1.0 upgrade is non-destructive: existing people and configuration are preserved and new profile columns are nullable.
