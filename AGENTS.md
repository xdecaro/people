# Repository Guidelines — People

People owns reusable person master records, identity/contact data, optional Joomla User links and duplicate warnings. It does not own memberships, sport roles, course enrolments, event participation, payments, bookings or other product workflows.

Dependency direction is `People -> Core`. Cross-product integration uses `PersonProviderService`, Core `CapabilityRegistry` and `EntityReference`; never private cross-product tables.

Sensitive birth, sex, disability, tax and residence fields require `people.view_sensitive`. Disability status is optional and must remain nullable when it is not supplied. Use server-side ACL, CSRF, filtered input, escaped output, bound queries and `#__`. Diagnostics must not expose person data.

People is Joomla 6 only. New technical identifiers use the `xdecaro` prefix and canonical lowercase `xdecaro\...` namespaces. Do not introduce new `decaro`-only identifiers. Visible product labels use `People` rather than `People by xdecaro`. Manifest author metadata remains `Luca De Caro`.

`1.0.0` is the first stable `com_xdecaropeople` / `pkg_xdecaropeople` release. Updates preserve data/configuration and ZIPs install directly in Joomla 6.
