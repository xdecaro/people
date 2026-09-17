# Repository Guidelines — People

People owns reusable person master records, identity/contact data, optional Joomla User links and duplicate warnings. It does not own memberships, sport roles, course enrolments, event participation, payments, bookings or other product workflows.

Dependency direction is `People -> Core`. Cross-product integration uses `PersonProviderService`, Core `CapabilityRegistry` and `EntityReference`; never private cross-product tables. People may publish business events through the public Notifications component services, but must never write Notifications tables directly.

Full sensitive sex, disability, accessibility, tax and residence fields require `people.view_sensitive`. Birth date and birth place remain personal data, but the public provider may expose exactly those two fields for authorised identity disambiguation through the narrower `people.view_identity_details` action; that limited contract must never expose disability, tax, residence or other full-sensitive fields. Disability status is optional and must remain nullable when it is not supplied. Use server-side ACL, CSRF, filtered input, escaped output, bound queries and `#__`. Diagnostics and notifications must not expose sensitive person values.

People is Joomla 6.1.3 only. Do not add Joomla 4, Joomla 5, other Joomla 6 minor-version compatibility work or related CI matrices unless explicitly requested. PHP minimum is 8.3. Child technical identifiers keep the existing `xdecaro` prefix and canonical lowercase `xdecaro\...` namespaces. The Joomla package layer follows the ecosystem standard and is `pkg_people`; do not reintroduce `pkg_xdecaropeople` except in explicit migration compatibility code/tests. Do not introduce new `decaro`-only identifiers. Visible product labels use `People`. Manifest author metadata remains `Luca De Caro`.

Updates preserve existing people and configuration. Install ZIPs must be deterministic and install directly on Joomla 6.1.3. Joomla update discovery uses `updates/pkg_people.xml` and the canonical `pkg_people_<version>.zip` release asset.
