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

## People 1.1.0

The person profile is organized into Identity, Contacts, Residence, Social and Publishing tabs.

- Identity keeps UUID, first/last name, Joomla User, optional birth date, sex, disability, birth place, searchable nationality and TIN. Display name remains an internal value generated from first and last name.
- Nationality stores ISO 3166-1 alpha-3 while the administrator chooses a searchable country name. The visible TIN label adapts to configured European national terminology, with `TIN` as the fallback outside the mapping.
- Contacts contains email, phone, WhatsApp and communication language.
- Residence contains address, street number, postal code, town/city, province/region and a searchable country selector stored as ISO alpha-2.
- Social stays inside the person record and supports Instagram, Facebook, LinkedIn, TikTok, Telegram, X/Twitter, YouTube and personal website.
- Disability is optional and protected by the existing `people.view_sensitive` permission.
- Empty optional birth dates are normalized to SQL `NULL` before saving.

The 1.1.0 schema migration is additive and preserves existing person records.
