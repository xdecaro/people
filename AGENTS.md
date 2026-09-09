# Repository Guidelines — People by xdecaro

People owns reusable person master records, identity/contact data, optional Joomla User links and duplicate warnings. It does not own memberships, sport roles, course enrolments, event participation, payments, bookings or other product workflows.

Dependency direction is `People -> Core`. Cross-product integration uses `PersonProviderService`, Core `CapabilityRegistry` and `EntityReference`; never private cross-product tables.

Sensitive identity/address fields require `people.view_sensitive`. Use server-side ACL, CSRF, filtered input, escaped output, bound queries and `#__`. Diagnostics must not expose person data.

`1.0.0` is the first stable `com_xdecaropeople` / `pkg_xdecaropeople` release. Updates preserve data/configuration and ZIPs install directly in Joomla 5/6.
